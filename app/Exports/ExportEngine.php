<?php

namespace App\Exports;

use App\Models\ExportHistory;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ExportEngine
{
    /**
     * 实体类型到模型的映射
     */
    protected array $modelMap = [
        'project' => Project::class,
        'task' => Task::class,
        'risk' => Risk::class,
    ];

    /**
     * 实体类型到导出器类的映射
     */
    protected array $exporterMap = [
        'project' => ProjectExport::class,
        'task' => TaskExport::class,
        'risk' => RiskExport::class,
    ];

    /**
     * 执行导出
     */
    public function export(
        string $entityType,
        string $format,
        array $filters = [],
        ?array $columns = null,
    ): ExportHistory {
        $user = auth()->user();

        // Resolve exporter
        $exporterClass = $this->exporterMap[$entityType] ?? null;
        if (! $exporterClass) {
            throw new \InvalidArgumentException("不支持的导出实体类型: {$entityType}");
        }

        /** @var BaseExport $exporter */
        $exporter = app($exporterClass);
        $data = $exporter->query($filters);

        // Determine columns
        $columns = $columns ?? $exporter->defaultColumns();

        // Create export history record
        $history = ExportHistory::create([
            'user_id' => $user->id,
            'entity_type' => $entityType,
            'format' => $format,
            'filters' => $filters,
            'selected_columns' => $columns,
            'record_count' => $data->count(),
            'status' => 'processing',
        ]);

        try {
            // Generate file
            $filename = $this->generateFilename($entityType, $format);
            $filePath = "exports/{$filename}";

            if ($format === 'csv') {
                $this->writeCsv($filePath, $data, $columns);
            } elseif ($format === 'xlsx') {
                $this->writeXlsx($filePath, $data, $columns);
            } elseif ($format === 'pdf') {
                $this->writePdf($filePath, $entityType, $data, $columns);
            } else {
                throw new \InvalidArgumentException("不支持的导出格式: {$format}");
            }

            // Update history with file info
            $fullPath = Storage::disk('local')->path($filePath);
            $fileSize = $this->formatFileSize(filesize($fullPath));

            $history->update([
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $history->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
            throw $e;
        }

        return $history->fresh();
    }

    /**
     * 可导出实体类型列表
     */
    public function getEntityTypes(): array
    {
        return [
            'project' => '项目',
            'task' => '任务',
            'risk' => '风险',
        ];
    }

    /**
     * 获取指定实体类型的可用列
     */
    public function getColumnsFor(string $entityType): array
    {
        $exporterClass = $this->exporterMap[$entityType] ?? null;
        if (! $exporterClass) {
            return [];
        }

        /** @var BaseExport $exporter */
        $exporter = app($exporterClass);

        return $exporter->availableColumns();
    }

    protected function generateFilename(string $entityType, string $format): string
    {
        $label = match ($entityType) {
            'project' => 'projects',
            'task' => 'tasks',
            'risk' => 'risks',
            default => $entityType,
        };
        $timestamp = now()->format('Ymd_His');
        $suffix = Str::random(4);

        return "{$label}_{$timestamp}_{$suffix}.{$format}";
    }

    protected function writeCsv(string $filePath, Collection $data, array $columns): void
    {
        $this->writeSpreadsheet($filePath, $data, $columns, CsvWriter::class);
    }

    protected function writeXlsx(string $filePath, Collection $data, array $columns): void
    {
        $this->writeSpreadsheet($filePath, $data, $columns, XlsxWriter::class);
    }

    protected function writeSpreadsheet(string $filePath, Collection $data, array $columns, string $writerClass): void
    {
        Storage::disk('local')->makeDirectory('exports');

        $fullPath = Storage::disk('local')->path($filePath);

        $writer = new $writerClass;
        $writer->openToFile($fullPath);

        // Header row
        $headerRow = Row::fromValues(array_values($columns));
        $writer->addRow($headerRow);

        // Data rows
        $columnKeys = array_keys($columns);
        foreach ($data as $item) {
            $values = [];
            foreach ($columnKeys as $key) {
                $values[] = $this->extractValue($item, $key);
            }
            $writer->addRow(Row::fromValues($values));
        }

        $writer->close();
    }

    /**
     * @param  Collection<int, Project|Task|Risk>  $data
     */
    protected function writePdf(string $filePath, string $entityType, Collection $data, array $columns): void
    {
        Storage::disk('local')->makeDirectory('exports');

        $fullPath = Storage::disk('local')->path($filePath);

        // Build headers
        $headers = array_values($columns);

        // Build rows with formatted HTML cells
        $rows = [];
        $columnKeys = array_keys($columns);
        foreach ($data as $item) {
            $row = [];
            foreach ($columnKeys as $key) {
                $value = $this->extractValue($item, $key);
                $cell = $this->formatPdfCell($key, $value, $entityType);
                $row[] = $cell;
            }
            $rows[] = $row;
        }

        // Build summary
        $summary = $this->buildPdfSummary($entityType, $data);

        // Entity type label
        $entityLabels = ['project' => '项目', 'task' => '任务', 'risk' => '风险'];
        $entityLabel = $entityLabels[$entityType] ?? $entityType;

        // 中文字体较大（~13MB），临时提高内存限制确保 PDF 渲染完整
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            $pdf = Pdf::loadView('exports.pdf.export', [
                'title' => "{$entityLabel}导出报告",
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'recordCount' => $data->count(),
                'summary' => $summary,
                'headers' => $headers,
                'rows' => $rows,
            ]);

            $pdf->save($fullPath);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    protected function formatPdfCell(string $key, mixed $value, string $entityType): string
    {
        $escaped = e((string) $value);

        // Boolean values (data-driven, not badge-based)
        if ($escaped === '是' || $escaped === '否') {
            $color = $escaped === '是' ? '#10b981' : '#94a3b8';

            return "<span style=\"color: {$color}; font-weight: 600;\">{$escaped}</span>";
        }

        // Unified badge lookup — no more cascading if/match blocks
        $config = $this->resolveBadgeConfig($entityType, $key);
        if ($config === null) {
            return $escaped;
        }

        [$classMap, $labelMap] = $config;
        $class = $classMap[$escaped] ?? '';
        $label = $labelMap[$escaped] ?? $escaped;

        return "<span class=\"status-badge {$class}\">{$label}</span>";
    }

    /**
     * Badge 配置查表 — 按 (entityType, key) 返回 [class映射, label映射] 二元组。
     * 添加新 badge 类型只需在这里加一行数据，无需新增条件分支。
     */
    protected function resolveBadgeConfig(string $entityType, string $key): ?array
    {
        $entityBadges = [
            'project' => [
                'status' => [
                    ['active' => 'status-active', 'completed' => 'status-completed', 'on_hold' => 'status-on_hold'],
                    ['active' => '进行中', 'completed' => '已完成', 'on_hold' => '已暂停'],
                ],
            ],
            'risk' => [
                'status' => [
                    ['open' => 'status-open', 'acknowledged' => 'status-acknowledged', 'closed' => 'status-closed'],
                    ['open' => '未处理', 'acknowledged' => '已确认', 'closed' => '已关闭'],
                ],
            ],
            'task' => [
                'status' => [
                    ['todo' => '', 'in_progress' => 'status-active', 'review' => 'status-acknowledged', 'done' => 'status-done', 'blocked' => 'status-open'],
                    ['todo' => '待办', 'in_progress' => '进行中', 'review' => '审核中', 'done' => '已完成', 'blocked' => '阻塞'],
                ],
            ],
        ];

        $sharedBadges = [
            'severity' => [
                ['critical' => 'severity-critical', 'high' => 'severity-high', 'medium' => 'severity-medium', 'low' => 'severity-low'],
                ['critical' => '严重', 'high' => '高', 'medium' => '中', 'low' => '低'],
            ],
            'priority' => [
                ['critical' => 'priority-critical', 'high' => 'priority-high', 'medium' => 'priority-medium', 'low' => 'priority-low'],
                ['critical' => '紧急', 'high' => '高', 'medium' => '中', 'low' => '低'],
            ],
        ];

        return $entityBadges[$entityType][$key] ?? $sharedBadges[$key] ?? null;
    }

    /**
     * @param  Collection<int, Project|Task|Risk>  $data
     */
    protected function buildPdfSummary(string $entityType, Collection $data): array
    {
        $summary = [];

        if ($entityType === 'project') {
            $total = $data->count();
            $active = $data->filter(fn ($p) => $p->status === 'active')->count();
            $completed = $data->filter(fn ($p) => $p->status === 'completed')->count();
            $totalTasks = $data->sum(fn ($p) => $p->task_count ?? 0);
            $totalRisks = $data->sum(fn ($p) => $p->risk_count ?? 0);

            $summary[] = ['label' => '项目总数', 'value' => $total];
            $summary[] = ['label' => '进行中', 'value' => $active];
            $summary[] = ['label' => '已完成', 'value' => $completed];
            $summary[] = ['label' => '总任务数', 'value' => $totalTasks];
            $summary[] = ['label' => '总风险数', 'value' => $totalRisks];
        } elseif ($entityType === 'task') {
            $total = $data->count();
            $done = $data->where('status', 'done')->count();
            $overdue = $data->filter(fn ($t) => $t->is_overdue ?? false)->count();
            $totalEstimate = $data->sum(fn ($t) => $t->estimate ?? 0);
            $totalSpent = $data->sum(fn ($t) => $t->spent ?? 0);

            $summary[] = ['label' => '任务总数', 'value' => $total];
            $summary[] = ['label' => '已完成', 'value' => $done];
            $summary[] = ['label' => '逾期', 'value' => $overdue];
            $summary[] = ['label' => '预估工时', 'value' => $totalEstimate.'h'];
            $summary[] = ['label' => '实际工时', 'value' => $totalSpent.'h'];
        } elseif ($entityType === 'risk') {
            $total = $data->count();
            $open = $data->where('status', 'open')->count();
            $critical = $data->where('severity', 'critical')->count();
            $high = $data->where('severity', 'high')->count();

            $summary[] = ['label' => '风险总数', 'value' => $total];
            $summary[] = ['label' => '未处理', 'value' => $open];
            $summary[] = ['label' => '严重', 'value' => $critical];
            $summary[] = ['label' => '高', 'value' => $high];
        }

        return $summary;
    }

    protected function extractValue($item, string $key): mixed
    {
        // Handle dot notation like "project.name"
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $item;
            foreach ($keys as $segment) {
                if (is_null($value)) {
                    return '';
                }
                $value = is_object($value) ? ($value->{$segment} ?? null) : ($value[$segment] ?? null);
            }

            return $this->formatValue($value);
        }

        $value = is_object($item) ? ($item->{$key} ?? null) : ($item[$key] ?? null);

        return $this->formatValue($value);
    }

    protected function formatValue($value): mixed
    {
        if (is_null($value)) {
            return '';
        }
        if ($value instanceof Carbon || $value instanceof \DateTime) {
            return $value->format('Y-m-d H:i');
        }
        if (is_bool($value)) {
            return $value ? '是' : '否';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }

    /**
     * 获取用户的导出历史
     */
    public function getHistory(int $limit = 10): Collection
    {
        return ExportHistory::where('user_id', auth()->id())
            ->recent()
            ->limit($limit)
            ->get();
    }
}
