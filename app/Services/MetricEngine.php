<?php

namespace App\Services;

use App\Models\Metric;
use App\Models\MetricSnapshot;
use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;

/**
 * 指标计算引擎 — 根据 definition JSON 计算自定义指标值
 *
 * Definition 支持的操作类型:
 *  - count_tasks   统计符合状态筛选的任务数
 *  - count_risks   统计符合条件风险数
 *  - completion_rate  计算完成率
 *  - wip_count     进行中任务数
 *  - cycle_days    平均任务周期
 *  - formula       基于上述结果的自定义公式
 */
class MetricEngine
{
    /**
     * 计算单个指标并返回结果。
     */
    public function compute(Metric $metric, Project $project): array
    {
        $def = $metric->definition ?: [];

        return match ($def['type'] ?? 'count_tasks') {
            'count_tasks' => $this->countTasks($project, $def),
            'count_risks' => $this->countRisks($project, $def),
            'completion_rate' => $this->completionRate($project, $def),
            'wip_count' => $this->wipCount($project, $def),
            'cycle_days' => $this->cycleDays($project, $def),
            'formula' => $this->formula($project, $def),
            default => $this->countTasks($project, $def),
        };
    }

    /**
     * 为项目计算所有已定义的指标，并写入快照。
     */
    public function computeForProject(Project $project): array
    {
        $results = [];
        $metrics = Metric::where('project_id', $project->id)->get();

        foreach ($metrics as $metric) {
            $value = $this->compute($metric, $project);

            // 写入快照
            MetricSnapshot::create([
                'metric_id' => $metric->id,
                'captured_at' => now(),
                'value' => $value,
            ]);

            // 更新指标当前值
            $metric->update([
                'current_json_value' => $value,
                'last_computed_at' => now(),
            ]);

            $results[$metric->key] = $value;
        }

        return $results;
    }

    /**
     * 为所有活跃项目批量计算并快照。
     */
    public function computeAll(): array
    {
        $results = [];
        $projects = Project::where('status', 'active')->with('tasks', 'risks')->get();

        foreach ($projects as $project) {
            $results[$project->slug] = $this->computeForProject($project);
        }

        return $results;
    }

    // ── 原子操作 ──────────────────────────────────────

    private function countTasks(Project $project, array $def): array
    {
        $statuses = $def['statuses'] ?? [];
        $priorities = $def['priorities'] ?? [];

        $query = Task::where('project_id', $project->id);

        if (! empty($statuses)) {
            $query->whereIn('status', $statuses);
        }
        if (! empty($priorities)) {
            $query->whereIn('priority', $priorities);
        }

        $count = $query->count();

        return [
            'value' => $count,
            'label' => $def['label'] ?? '任务数',
            'unit' => '个',
        ];
    }

    private function countRisks(Project $project, array $def): array
    {
        $statuses = $def['statuses'] ?? ['open'];
        $severities = $def['severities'] ?? [];

        $query = Risk::where('project_id', $project->id)->whereIn('status', $statuses);

        if (! empty($severities)) {
            $query->whereIn('severity', $severities);
        }

        $count = $query->count();

        return [
            'value' => $count,
            'label' => $def['label'] ?? '风险数',
            'unit' => '个',
        ];
    }

    private function completionRate(Project $project, array $def): array
    {
        $doneStatuses = $def['done_statuses'] ?? ['done', 'closed', 'completed'];
        $total = Task::where('project_id', $project->id)->count();

        if ($total === 0) {
            return ['value' => 0, 'label' => $def['label'] ?? '完成率', 'unit' => '%'];
        }

        $done = Task::where('project_id', $project->id)
            ->whereIn('status', $doneStatuses)
            ->count();

        $rate = round(($done / $total) * 100, 1);

        return [
            'value' => $rate,
            'label' => $def['label'] ?? '完成率',
            'unit' => '%',
            'detail' => "{$done}/{$total}",
        ];
    }

    private function wipCount(Project $project, array $def): array
    {
        $wipStatuses = $def['wip_statuses'] ?? ['in_progress', 'doing'];
        $count = Task::where('project_id', $project->id)
            ->whereIn('status', $wipStatuses)
            ->count();

        return [
            'value' => $count,
            'label' => $def['label'] ?? '在制品',
            'unit' => '个',
        ];
    }

    private function cycleDays(Project $project, array $def): array
    {
        $doneStatuses = $def['done_statuses'] ?? ['done', 'closed', 'completed'];
        $tasks = Task::where('project_id', $project->id)
            ->whereIn('status', $doneStatuses)
            ->whereNotNull('start_at')
            ->whereNotNull('completed_at')
            ->get();

        if ($tasks->isEmpty()) {
            return ['value' => 0, 'label' => $def['label'] ?? '平均周期', 'unit' => '天'];
        }

        $avgDays = round(
            $tasks->avg(fn (Task $t) => $t->start_at->diffInDays($t->completed_at ?: now())),
            1
        );

        return [
            'value' => $avgDays,
            'label' => $def['label'] ?? '平均周期',
            'unit' => '天',
        ];
    }

    private function formula(Project $project, array $def): array
    {
        // 支持简单公式：{metric_key} 替换为原子结果
        $expression = $def['expression'] ?? '0';
        $subMetrics = $def['metrics'] ?? [];

        $vars = [];
        foreach ($subMetrics as $key => $subDef) {
            $subMetric = new Metric(['definition' => $subDef]);
            $result = $this->compute($subMetric, $project);
            $vars[$key] = $result['value'] ?? 0;
        }

        // 简单算术替换 — 安全执行
        $evalExpr = $expression;
        foreach ($vars as $k => $v) {
            $evalExpr = str_replace('{'.$k.'}', (string) $v, $evalExpr);
        }

        // 仅允许数字/运算符/括号/空格
        if (! preg_match('/^[\d\s+\-*\/().%]+$/', $evalExpr)) {
            return ['value' => 0, 'label' => $def['label'] ?? '公式结果', 'unit' => '', 'error' => '非法公式'];
        }

        try {
            $value = round((float) eval("return {$evalExpr};"), 2);
        } catch (\Throwable) {
            return ['value' => 0, 'label' => $def['label'] ?? '公式结果', 'unit' => '', 'error' => '计算错误'];
        }

        return [
            'value' => $value,
            'label' => $def['label'] ?? '公式结果',
            'unit' => $def['unit'] ?? '',
        ];
    }

    /**
     * 获取指标的历史快照（用于趋势图）。
     */
    public function getSnapshots(int $metricId, int $limit = 30): array
    {
        return MetricSnapshot::where('metric_id', $metricId)
            ->orderBy('captured_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->toArray();
    }
}
