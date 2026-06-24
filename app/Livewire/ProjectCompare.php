<?php

namespace App\Livewire;

use App\Models\Project;
use App\Services\ChartSvgService;
use App\Services\RiskEngine\FeishuAlertService;
use Livewire\Component;

class ProjectCompare extends Component
{
    /** @var array<string> Slugs of selected projects */
    public array $selected = [];

    /** Predefined palette for project color-coding */
    protected array $palette = [
        '#4f46e5', '#10b981', '#f59e0b', '#ef4444',
        '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4',
    ];

    protected $queryString = ['selected'];

    public function mount(): void
    {
        // Default: select all active projects
        if (empty($this->selected)) {
            $this->selected = Project::where('status', 'active')->pluck('slug')->toArray();
        }
    }

    public function toggleProject(string $slug): void
    {
        if (in_array($slug, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$slug]));
        } else {
            $this->selected[] = $slug;
        }
    }

    public function selectAll(): void
    {
        $this->selected = Project::where('status', 'active')->pluck('slug')->toArray();
    }

    public function clearAll(): void
    {
        $this->selected = [];
    }

    public function pushToFeishu(): array
    {
        $projects = $this->getSelectedProjects();
        if ($projects->isEmpty()) {
            return ['ok' => false, 'error' => '请至少选择一个项目'];
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

        return [
            'ok' => true,
            'message' => implode("\n", $messages),
        ];
    }

    public function render()
    {
        $allProjects = Project::withCount(['tasks', 'risks'])->where('status', 'active')->get();
        $selectedProjects = $this->getSelectedProjects();

        $comparisons = [];
        $completionBars = [];
        $taskBarsGroups = [];
        $riskBarsGroups = [];
        $kpiRows = [];

        foreach ($selectedProjects as $idx => $project) {
            $color = $this->palette[$idx % count($this->palette)];
            $completion = $project->completionRate();

            $taskStatusCounts = $project->tasks()
                ->selectRaw('status, count(*) as cnt')
                ->groupBy('status')->pluck('cnt', 'status')->toArray();

            $riskSeverityCounts = $project->risks()
                ->selectRaw('severity, count(*) as cnt')
                ->groupBy('severity')->pluck('cnt', 'severity')->toArray();

            $totalTasks = $project->tasks_count;
            $doneTasks = ($taskStatusCounts['done'] ?? 0) + ($taskStatusCounts['closed'] ?? 0) + ($taskStatusCounts['completed'] ?? 0);
            $inProgressTasks = $taskStatusCounts['in_progress'] ?? 0;
            $openTasks = ($taskStatusCounts['open'] ?? 0) + ($taskStatusCounts['todo'] ?? 0);
            $blockedTasks = $taskStatusCounts['blocked'] ?? 0;
            $totalRisks = $project->risks_count;
            $openRisks = $project->risks()->where('status', 'open')->count();

            $comparisons[] = [
                'project' => $project,
                'color' => $color,
                'completion' => $completion,
                'totalTasks' => $totalTasks,
                'doneTasks' => $doneTasks,
                'inProgress' => $inProgressTasks,
                'openTasks' => $openTasks,
                'blocked' => $blockedTasks,
                'totalRisks' => $totalRisks,
                'openRisks' => $openRisks,
                'startAt' => optional($project->start_at)->format('Y-m-d') ?? '-',
                'endAt' => optional($project->end_at)->format('Y-m-d') ?? '-',
            ];

            // For grouped bar chart: completion rate
            $completionBars[] = [
                'name' => $project->name,
                'color' => $color,
                'bars' => [['label' => '完成率', 'value' => $completion, 'color' => $color]],
            ];

            // For grouped bar chart: task distribution
            $taskBarsGroups[] = [
                'name' => $project->name,
                'color' => $color,
                'bars' => [
                    ['label' => '已完成', 'value' => $doneTasks, 'color' => $color],
                    ['label' => '进行中', 'value' => $inProgressTasks, 'color' => $color],
                    ['label' => '待处理', 'value' => $openTasks, 'color' => $color],
                    ['label' => '已阻塞', 'value' => $blockedTasks, 'color' => $color],
                ],
            ];

            // For grouped bar chart: risks
            $riskBarsGroups[] = [
                'name' => $project->name,
                'color' => $color,
                'bars' => [
                    ['label' => '严重/高', 'value' => ($riskSeverityCounts['critical'] ?? 0) + ($riskSeverityCounts['high'] ?? 0), 'color' => $color],
                    ['label' => '中等', 'value' => $riskSeverityCounts['medium'] ?? 0, 'color' => $color],
                    ['label' => '低', 'value' => $riskSeverityCounts['low'] ?? 0, 'color' => $color],
                ],
            ];
        }

        $completionChart = ChartSvgService::groupedBars($completionBars, '完成率对比');
        $tasksChart = ChartSvgService::groupedBars($taskBarsGroups, '任务分布对比');
        $risksChart = ChartSvgService::groupedBars($riskBarsGroups, '风险分布对比');

        return view('livewire.project-compare', [
            'allProjects' => $allProjects,
            'comparisons' => $comparisons,
            'completionChart' => $completionChart,
            'tasksChart' => $tasksChart,
            'risksChart' => $risksChart,
            'selectedCount' => count($comparisons),
            'totalCount' => $allProjects->count(),
        ])->layout('layouts.app');
    }

    /**
     * Get Project models for selected slugs (preserve selection order).
     */
    protected function getSelectedProjects()
    {
        if (empty($this->selected)) {
            return collect();
        }

        $projects = Project::with(['tasks', 'risks'])
            ->withCount(['tasks', 'risks'])
            ->whereIn('slug', $this->selected)
            ->get();

        // Preserve selection order
        $order = array_flip($this->selected);

        return $projects->sortBy(fn ($p) => $order[$p->slug] ?? 999)->values();
    }
}
