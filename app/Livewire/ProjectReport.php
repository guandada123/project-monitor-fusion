<?php

namespace App\Livewire;

use App\Models\Project;
use App\Services\ChartSvgService;
use Livewire\Component;

class ProjectReport extends Component
{
    public string $slug;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $dimension = 'progress'; // progress | quality | load

    protected $queryString = ['dateFrom', 'dateTo', 'dimension'];

    public function mount(string $slug): void
    {
        $this->slug = $slug;
        $this->dateFrom = request('dateFrom', now()->subDays(30)->format('Y-m-d'));
        $this->dateTo = request('dateTo', now()->format('Y-m-d'));
    }

    public function render()
    {
        $project = Project::with(['risks', 'metrics'])
            ->where('slug', $this->slug)
            ->firstOrFail();

        $data = $this->buildFilteredData($project);

        return view('livewire.project-report', $data)->layout('layouts.app');
    }

    /**
     * Build report data filtered by date range and dimension.
     */
    protected function buildFilteredData(Project $project): array
    {
        // Aggregate task status counts via SQL — zero full task records in PHP memory
        $statusCounts = $project->tasks()
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        $openTasks = ($statusCounts['open'] ?? 0) + ($statusCounts['todo'] ?? 0) + ($statusCounts['in_progress'] ?? 0);
        $closedTasks = array_sum($statusCounts->toArray()) - $openTasks;

        // Date-filtered, paginated task list for display table
        $tasks = $project->tasks()
            ->when($this->dateFrom, fn ($q) => $q->where('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->where('created_at', '<=', $this->dateTo.' 23:59:59'))
            ->orderBy('due_at')
            ->paginate(50);

        // Risk data — use pre-loaded collection, sort in PHP
        $risks = $project->risks->sortByDesc('severity')->values();

        $completion = $project->completionRate();

        // Task status distribution bars
        $taskBars = [
            ['label' => '已完成', 'value' => ($statusCounts['done'] ?? 0) + ($statusCounts['closed'] ?? 0) + ($statusCounts['completed'] ?? 0), 'color' => '#10b981'],
            ['label' => '进行中', 'value' => $statusCounts['in_progress'] ?? 0, 'color' => '#3b82f6'],
            ['label' => '待处理', 'value' => $statusCounts['open'] ?? 0, 'color' => '#f59e0b'],
            ['label' => '已阻塞', 'value' => $statusCounts['blocked'] ?? 0, 'color' => '#ef4444'],
        ];

        // Risk severity breakdown
        $riskCounts = $risks->groupBy('severity')->map->count();
        $riskBars = [
            ['label' => '严重', 'value' => ($riskCounts['critical'] ?? 0) + ($riskCounts['high'] ?? 0), 'color' => '#ef4444'],
            ['label' => '中等', 'value' => $riskCounts['medium'] ?? 0, 'color' => '#f59e0b'],
            ['label' => '低',   'value' => $riskCounts['low'] ?? 0, 'color' => '#10b981'],
        ];

        // Dimension-specific charts — no longer receives all tasks
        $dimensionCharts = $this->buildDimensionCharts($project);

        $charts = [
            'donut' => ChartSvgService::donutGauge($completion, '完成率'),
            'kpi_open' => ChartSvgService::kpiCard('未完成', (string) $openTasks, $openTasks > 0 ? 'up' : 'down', '#ef4444'),
            'kpi_done' => ChartSvgService::kpiCard('已完成', (string) $closedTasks, $closedTasks > 0 ? 'up' : 'down', '#10b981'),
            'kpi_risk' => ChartSvgService::kpiCard('风险数', (string) $risks->count(), $risks->count() > 0 ? 'up' : 'down', '#f59e0b'),
            'bars_tasks' => ChartSvgService::horizontalBars($taskBars),
            'bars_risks' => ChartSvgService::horizontalBars($riskBars),
            'progress' => ChartSvgService::progressBar($completion, '项目完成率'),
            'dimension' => $dimensionCharts,
        ];

        return [
            'project' => $project,
            'tasks' => $tasks,
            'risks' => $risks,
            'completion' => $completion,
            'openTasks' => $openTasks,
            'closedTasks' => $closedTasks,
            'charts' => $charts,
            'generatedAt' => now(),
        ];
    }

    /**
     * Build dimension-specific SVG charts based on selected dimension.
     */
    protected function buildDimensionCharts(Project $project): array
    {
        $charts = [];

        switch ($this->dimension) {
            case 'progress':
                // Velocity: tasks completed per week (simulated)
                $charts['header'] = '📈 进度趋势';
                $charts['svg'] = ChartSvgService::progressBar(
                    $project->completionRate(),
                    '当前进度',
                    400,
                    40
                );
                break;

            case 'quality':
                // Quality: bug/defect density — use aggregation, not full task records
                $totalTasks = $project->tasks()->count();
                $blockedTasks = $project->tasks()->where('status', 'blocked')->count();
                $bugRate = $totalTasks > 0
                    ? round($blockedTasks / $totalTasks * 100, 1)
                    : 0;
                $qualityScore = max(0, 100 - $bugRate * 3);

                // Use pre-loaded collection for risks (no extra query)
                $closedRisks = $project->risks->where('status', 'closed')->count();

                $qualityBars = [
                    ['label' => '质量分',   'value' => (int) $qualityScore, 'color' => '#10b981'],
                    ['label' => '阻塞率',   'value' => (int) $bugRate, 'color' => '#ef4444'],
                    ['label' => '已闭环风险', 'value' => $closedRisks, 'color' => '#3b82f6'],
                ];

                $charts['header'] = '🔍 质量指标';
                $charts['svg'] = ChartSvgService::horizontalBars($qualityBars);
                break;

            case 'load':
                // Resource load: tasks per assignee — only load tasks when this dimension is selected
                $tasks = $project->tasks()->with('assignee')->get();
                $assigneeCounts = [];
                foreach ($tasks->groupBy('assignee_id') as $uid => $group) {
                    $name = $group->first()->assignee->name ?? "用户#{$uid}";
                    $assigneeCounts[] = ['label' => $name, 'value' => $group->count(), 'color' => '#6366f1'];
                }
                if (empty($assigneeCounts)) {
                    $assigneeCounts[] = ['label' => '未分配', 'value' => $tasks->count(), 'color' => '#9ca3af'];
                }

                $charts['header'] = '👥 负载分布';
                $charts['svg'] = ChartSvgService::horizontalBars($assigneeCounts, 400, 28, 6);
                break;
        }

        return $charts;
    }

    /**
     * Handle dimension change — Livewire will re-render automatically.
     */
    public function updatedDimension(): void
    {
        // Triggers re-render
    }

    public function updatedDateFrom(): void
    {
        // Triggers re-render
    }

    public function updatedDateTo(): void
    {
        // Triggers re-render
    }
}
