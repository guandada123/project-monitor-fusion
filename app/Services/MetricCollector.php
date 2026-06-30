<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 指标快照自动采集器
 *
 * 支持 8 种基础指标 × 5 作用域（4 项目 + 1 全局）
 * Key 结构：全局为 {base_key}，项目为 {project_slug}-{base_key}
 */
class MetricCollector
{
    // 8 种基础指标 key
    const BASE_KEYS = [
        'completion_rate',
        'overdue_ratio',
        'burndown_deviation',
        'avg_workload',
        'wip_count',
        'avg_lead_time',
        'defect_rate',
        'unclosed_risks',
    ];

    /**
     * 采集全部系统指标
     */
    public function collectAll(): array
    {
        $results = ['total' => 0, 'computed' => 0, 'errors' => 0];

        $metrics = Metric::system()->get();
        foreach ($metrics as $metric) {
            try {
                $value = $this->compute($metric);
                if ($value !== null) {
                    $this->updateMetricWithSnapshot($metric, $value);
                    $results['computed']++;
                }
            } catch (\Throwable $e) {
                Log::warning("MetricCollector failed for {$metric->key}", [
                    'metric_id' => $metric->id,
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
            $results['total']++;
        }

        return $results;
    }

    /**
     * 采集单个指标
     */
    public function collectOne(string $key): array
    {
        $results = ['total' => 0, 'computed' => 0, 'errors' => 0];

        $metric = Metric::where('key', $key)->first();

        if (! $metric) {
            return [
                'total' => 0,
                'computed' => 0,
                'errors' => 0,
                'message' => "指标 '{$key}' 未找到",
            ];
        }

        try {
            $value = $this->compute($metric);
            if ($value !== null) {
                $this->updateMetricWithSnapshot($metric, $value);
                $results['computed']++;
            }
        } catch (\Throwable $e) {
            Log::warning("MetricCollector failed for {$metric->key}", [
                'metric_id' => $metric->id,
                'error' => $e->getMessage(),
            ]);
            $results['errors']++;
        }
        $results['total']++;

        return $results;
    }

    /**
     * 在事务中更新指标值并创建快照
     */
    private function updateMetricWithSnapshot(Metric $metric, mixed $value): void
    {
        DB::transaction(function () use ($metric, $value) {
            $metric->update([
                'current_value' => is_numeric($value) ? $value : null,
                'current_json_value' => is_array($value) ? $value : null,
                'last_computed_at' => now(),
            ]);

            MetricSnapshot::create([
                'metric_id' => $metric->id,
                'value' => is_numeric($value) ? $value : null,
                'json_value' => is_array($value) ? $value : null,
                'captured_at' => now(),
            ]);
        });
    }

    /**
     * 解析指标 key，返回 [project_id|null, base_key]
     */
    protected function parseKey(string $key): array
    {
        // Check if global (no project prefix)
        if (in_array($key, self::BASE_KEYS)) {
            return [null, $key];
        }

        // Try project-scoped: {slug}-{base_key}
        foreach (self::BASE_KEYS as $bk) {
            $suffix = "-{$bk}";
            if (str_ends_with($key, $suffix)) {
                $slug = substr($key, 0, -strlen($suffix));
                $project = Project::where('slug', $slug)->first();

                return [$project?->id, $bk];
            }
        }

        return [null, null];
    }

    /**
     * 计算单个指标
     */
    protected function compute(Metric $metric): mixed
    {
        // Try JSONLogic definition first
        $def = $metric->definition ?? [];
        if (! empty($def) && isset($def['logic'])) {
            // Fall through to builtin for now
        }

        // Use metric's own project_id as scope
        $projectId = $metric->project_id;
        [$parsedProjectId, $baseKey] = $this->parseKey($metric->key);

        // Prefer parsed project ID from key, fallback to metric's project_id
        $scopeId = $parsedProjectId ?? $projectId;

        if (! $baseKey) {
            return null;
        }

        return $this->computeBuiltin($baseKey, $scopeId);
    }

    /**
     * 内置指标计算 — 支持按项目作用域筛选
     */
    protected function computeBuiltin(string $baseKey, ?int $projectId): mixed
    {
        return match ($baseKey) {
            'completion_rate' => $this->calcCompletionRate($projectId),
            'overdue_ratio' => $this->calcOverdueRatio($projectId),
            'burndown_deviation' => $this->calcBurndownDeviation($projectId),
            'avg_workload' => $this->calcAvgWorkload($projectId),
            'wip_count' => $this->calcWipCount($projectId),
            'avg_lead_time' => $this->calcAvgLeadTime($projectId),
            'defect_rate' => $this->calcDefectRate($projectId),
            'unclosed_risks' => $this->calcUnclosedRisks($projectId),
            default => null,
        };
    }

    // ──────── 8 核心指标计算 ────────

    /**
     * 整体完成率 (%) — done / total * 100
     */
    protected function calcCompletionRate(?int $projectId): float
    {
        $result = Task::selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', ['done'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->first();

        return $result->total > 0 ? round(($result->done / $result->total) * 100, 2) : 0;
    }

    /**
     * 延期率 (%) — 延期未完成 / 总任务 * 100
     */
    protected function calcOverdueRatio(?int $projectId): float
    {
        $q = Task::query();
        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        $total = $q->count();
        if ($total === 0) {
            return 0;
        }

        $overdue = (clone $q)
            ->where('status', '!=', 'done')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now()->toDateString())
            ->count();

        return round(($overdue / $total) * 100, 1);
    }

    /**
     * 燃尽偏差 — 实际完成率 vs 时间线期望值的差异
     */
    protected function calcBurndownDeviation(?int $projectId): float
    {
        $q = Task::query();
        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        $total = $q->count();
        if ($total === 0) {
            return 0;
        }

        $done = (clone $q)->where('status', 'done')->count();
        $actualRate = $done / $total;

        // Expected progress based on time passed
        $pq = Project::query();
        if ($projectId) {
            $pq->where('id', $projectId);
        }

        $project = $pq->whereIn('status', ['active', 'pending'])->first();

        if ($project && $project->start_at && $project->end_at) {
            $totalDays = $project->start_at->diffInDays($project->end_at);
            $elapsed = $project->start_at->diffInDays(now());
            $expected = $totalDays > 0 ? min(1.0, max(0, $elapsed / $totalDays)) : 0.5;
        } else {
            $expected = 0.5;
        }

        return round(($actualRate - $expected) * 100, 1);
    }

    /**
     * 人均负载 — 进行中任务 / 活跃用户数
     */
    protected function calcAvgWorkload(?int $projectId): float
    {
        $baseQuery = Task::where('status', 'in_progress')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId));

        $inProgress = (clone $baseQuery)->count();

        // Count unique assignees for in_progress tasks
        $assignees = (clone $baseQuery)
            ->whereNotNull('assignee_id')
            ->distinct('assignee_id')
            ->count('assignee_id');

        if ($assignees === 0) {
            return 0;
        }

        return round($inProgress / $assignees, 1);
    }

    /**
     * WIP 在制品数 — 当前进行中的任务数
     */
    protected function calcWipCount(?int $projectId): int
    {
        $q = Task::where('status', 'in_progress');
        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        return $q->count();
    }

    /**
     * 平均周期 (天) — 任务创建到完成的平均天数
     */
    protected function calcAvgLeadTime(?int $projectId): float
    {
        $q = Task::where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereNotNull('created_at');
        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        $tasks = $q->get(['created_at', 'completed_at']);
        if ($tasks->isEmpty()) {
            return 0;
        }

        $totalHours = $tasks->sum(fn ($t) => $t->created_at->diffInHours($t->completed_at));

        return round($totalHours / $tasks->count() / 24, 1); // convert to days
    }

    /**
     * 缺陷率 — 开放风险数 / 总任务数 * 100
     */
    protected function calcDefectRate(?int $projectId): float
    {
        $q = Risk::where('status', 'open');
        if ($projectId) {
            $q->where('project_id', $projectId);
        }
        $risks = $q->count();

        $tq = Task::query();
        if ($projectId) {
            $tq->where('project_id', $projectId);
        }
        $tasks = $tq->count();

        if ($tasks === 0) {
            return 0;
        }

        return round(($risks / $tasks) * 100, 2);
    }

    /**
     * 风险未闭环数 — 当前开放的风险总数
     */
    protected function calcUnclosedRisks(?int $projectId): int
    {
        $q = Risk::where('status', 'open');
        if ($projectId) {
            $q->where('project_id', $projectId);
        }

        return $q->count();
    }
}
