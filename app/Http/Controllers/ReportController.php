<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ChartSvgService;
use App\Services\RiskEngine\FeishuAlertService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class ReportController extends Controller
{
    /**
     * Build common report data (shared by html + pdf).
     */
    protected function buildReportData(Project $project): array
    {
        $completion = $project->completionRate();
        $openTasks = $project->tasks()->whereIn('status', ['open', 'todo', 'in_progress'])->count();
        $closedTasks = $project->tasks()->whereNotIn('status', ['open', 'todo', 'in_progress'])->count();

        // Task status distribution for chart
        $statusCounts = $project->tasks()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')->pluck('cnt', 'status')->toArray();
        $taskBars = [
            ['label' => '已完成', 'value' => ($statusCounts['done'] ?? 0) + ($statusCounts['closed'] ?? 0) + ($statusCounts['completed'] ?? 0), 'color' => '#10b981'],
            ['label' => '进行中', 'value' => $statusCounts['in_progress'] ?? 0, 'color' => '#3b82f6'],
            ['label' => '待处理', 'value' => $statusCounts['open'] ?? 0, 'color' => '#f59e0b'],
            ['label' => '已阻塞', 'value' => $statusCounts['blocked'] ?? 0, 'color' => '#ef4444'],
        ];

        // Risk severity breakdown
        $riskCounts = $project->risks()
            ->selectRaw('severity, count(*) as cnt')
            ->groupBy('severity')->pluck('cnt', 'severity')->toArray();
        $riskBars = [
            ['label' => '严重', 'value' => ($riskCounts['critical'] ?? 0) + ($riskCounts['high'] ?? 0), 'color' => '#ef4444'],
            ['label' => '中等', 'value' => $riskCounts['medium'] ?? 0, 'color' => '#f59e0b'],
            ['label' => '低', 'value' => $riskCounts['low'] ?? 0, 'color' => '#10b981'],
        ];

        // SVG charts
        $charts = [
            'donut' => ChartSvgService::donutGauge($completion, '完成率'),
            'kpi_open' => ChartSvgService::kpiCard('未完成任务', (string) $openTasks, $openTasks > 0 ? 'up' : 'down', '#ef4444'),
            'kpi_done' => ChartSvgService::kpiCard('已完成', (string) $closedTasks, $closedTasks > 0 ? 'up' : 'down', '#10b981'),
            'kpi_risk' => ChartSvgService::kpiCard('风险数', (string) array_sum($riskCounts), array_sum($riskCounts) > 0 ? 'up' : 'down', '#f59e0b'),
            'bars_tasks' => ChartSvgService::horizontalBars($taskBars),
            'bars_risks' => ChartSvgService::horizontalBars($riskBars),
            'progress' => ChartSvgService::progressBar($completion, '项目完成率'),
        ];

        return [
            'project' => $project,
            'tasks' => $project->tasks()->orderBy('due_at')->limit(100)->get(),
            'risks' => $project->risks()->orderByDesc('severity')->limit(100)->get(),
            'completion' => $completion,
            'openTasks' => $openTasks,
            'closedTasks' => $closedTasks,
            'generatedAt' => now(),
            'charts' => $charts,
        ];
    }

    /**
     * HTML 预览：/projects/{slug}/report
     */
    public function html(string $slug)
    {
        $project = Project::with(['tasks', 'risks', 'metrics'])->where('slug', $slug)->firstOrFail();

        return view('reports.project', $this->buildReportData($project));
    }

    /**
     * PDF 导出：/projects/{slug}/report.pdf
     */
    public function pdf(string $slug)
    {
        $project = Project::with(['tasks', 'risks', 'metrics'])->where('slug', $slug)->firstOrFail();

        $data = $this->buildReportData($project);
        $data['isPdf'] = true;

        $html = view('reports.project', $data)->render();

        $filename = $project->slug.'-report-'.now()->format('Ymd-His').'.pdf';

        // 优先使用 Browsershot（需要 Node/Puppeteer）
        if (class_exists(Browsershot::class)) {
            try {
                $dir = storage_path('app/reports');
                if (! is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $path = $dir.DIRECTORY_SEPARATOR.$filename;

                Browsershot::html($html)
                    ->format('A4')
                    ->margins(10, 10, 10, 10)
                    ->scale(1)
                    ->timeout(60)
                    ->save($path);

                return response()->download($path)->deleteFileAfterSend(true);
            } catch (\Throwable $e) {
                Log::warning('Browsershot 生成 PDF 失败，将回退 DomPDF: '.$e->getMessage());
                // 继续回退
            }
        }

        // 回退使用 DomPDF（barryvdh/laravel-dompdf）
        if (class_exists(Pdf::class)) {
            try {
                $pdf = Pdf::setOptions([
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ])->loadHTML($html)->setPaper('a4');

                return $pdf->download($filename);
            } catch (\Throwable $e) {
                Log::error('DomPDF 生成 PDF 失败: '.$e->getMessage());

                return response()->json([
                    'message' => 'PDF 生成失败',
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        // 两个引擎都不可用
        return response()->json([
            'message' => 'PDF 引擎未安装：请先安装 spatie/browsershot 或 barryvdh/laravel-dompdf',
            'install' => [
                'composer require spatie/browsershot:^3.63',
                'composer require barryvdh/laravel-dompdf:^3.0',
            ],
        ], 503);
    }

    /**
     * 推送项目报告到飞书群：POST /projects/{slug}/push-feishu
     */
    public function pushToFeishu(string $slug)
    {
        $project = Project::with(['tasks', 'risks'])->where('slug', $slug)->firstOrFail();

        $reportUrl = route('projects.report.html', $project->slug);

        $service = new FeishuAlertService;
        $result = $service->sendReportSummary($project, $reportUrl);

        if ($result['ok']) {
            return response()->json([
                'ok' => true,
                'message' => '✅ 报告已推送到飞书群【项目监控】',
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => $result['error'] ?? '❌ 推送失败，请检查飞书 Webhook 配置',
            'detail' => $result,
        ], 500);
    }

    /**
     * 批量推送多个项目报告到飞书：POST /projects/compare/push-feishu
     */
    public function pushCompareToFeishu(Request $request)
    {
        $slugs = $request->input('slugs', []);
        if (empty($slugs)) {
            return response()->json(['ok' => false, 'error' => '请提供要推送的项目 slug 列表'], 400);
        }

        $projects = Project::whereIn('slug', $slugs)->get();
        if ($projects->isEmpty()) {
            return response()->json(['ok' => false, 'error' => '未找到匹配的项目'], 404);
        }

        $service = new FeishuAlertService;
        $messages = [];

        foreach ($projects as $project) {
            $reportUrl = route('projects.report.html', $project->slug);
            $result = $service->sendReportSummary($project, $reportUrl);
            $messages[] = $result['ok']
                ? "✅ {$project->name} 已推送"
                : "❌ {$project->name}: ".($result['error'] ?? '推送失败');
        }

        $okCount = count(array_filter($messages, fn ($m) => str_starts_with($m, '✅')));

        return response()->json([
            'ok' => true,
            'message' => implode("\n", $messages),
            'success' => $okCount,
            'total' => count($messages),
        ]);
    }
}
