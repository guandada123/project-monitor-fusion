<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    // ——— Filter state (URL-persisted via Livewire 3 #[Url]) ———
    #[Url(as: 'status', history: true)]
    public string $filterStatus = 'all';

    #[Url(as: 'source', history: true)]
    public string $filterSource = 'all';

    #[Url(as: 'from', history: true)]
    public ?string $filterDateStart = null;

    #[Url(as: 'to', history: true)]
    public ?string $filterDateEnd = null;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    // ——— Data ———
    public array $projectStats = [];

    public array $kpiSummary = [];

    public array $riskSummary = [];

    public array $resourceHeatmap = [];

    public array $trendData = [];

    public array $kpiTrendData = [];

    public array $kpiDates = [];

    // ——— Filter metadata exposed to view ———
    public array $statusOptions = [];

    public array $sourceOptions = [];

    // ——— Dispatch guard: prevent render-loop when frontend reacts to 'dashboard-refreshed' ———
    protected bool $needsDispatch = true;

    public function mount(): void
    {
        $this->statusOptions = [
            'all' => '全部状态',
            'active' => '进行中',
            'completed' => '已完成',
            'on_hold' => '已暂停',
        ];

        $this->sourceOptions = Project::select('source')
            ->distinct()
            ->whereNotNull('source')
            ->pluck('source')
            ->toArray();
    }

    // ——— Filter change handlers ———
    // Note: no longer call loadStats() individually — render() handles it for all paths

    public function resetFilters(): void
    {
        $this->filterStatus = 'all';
        $this->filterSource = 'all';
        $this->filterDateStart = null;
        $this->filterDateEnd = null;
        $this->search = '';
        $this->needsDispatch = true;
    }

    // ——— Filter update hooks: mark dirty so render() dispatches again ———
    public function updatedFilterStatus(): void    { $this->needsDispatch = true; }
    public function updatedFilterSource(): void    { $this->needsDispatch = true; }
    public function updatedFilterDateStart(): void { $this->needsDispatch = true; }
    public function updatedFilterDateEnd(): void   { $this->needsDispatch = true; }
    public function updatedSearch(): void          { $this->needsDispatch = true; }

    // ——— Active filter count (for badge) ———
    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        if ($this->filterStatus !== 'all') {
            $count++;
        }
        if ($this->filterSource !== 'all') {
            $count++;
        }
        if ($this->filterDateStart) {
            $count++;
        }
        if ($this->filterDateEnd) {
            $count++;
        }
        if ($this->search !== '') {
            $count++;
        }

        return $count;
    }

    // ——— Filtered data loading (with 60s Cache::remember) ———
    protected function loadStats(): void
    {
        $cacheKey = 'dashboard_stats:' . auth()->id() . ':' . md5(implode('|', [
            $this->filterStatus,
            $this->filterSource,
            $this->filterDateStart ?? '',
            $this->filterDateEnd ?? '',
            $this->search,
        ]));

        // 预提取方法引用，供闭包内使用（PHP 闭包中 $this 可能不可自动绑定）
        $buildTrend = fn () => $this->buildTrendData();
        $buildKpi = fn () => $this->buildKpiTrend();
        $now = now();

        $cached = Cache::remember($cacheKey, 60, function () use ($buildTrend, $buildKpi, $now) {
            // 1. Build filtered project query
            $query = Project::with(['tasks', 'owner']);

            if ($this->filterStatus !== 'all') {
                $query->where('status', $this->filterStatus);
            }
            if ($this->filterSource !== 'all') {
                $query->where('source', $this->filterSource);
            }
            if ($this->search !== '') {
                $search = $this->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
            if ($this->filterDateStart) {
                $query->where(function ($q) {
                    $q->whereNull('end_at')
                        ->orWhere('end_at', '>=', $this->filterDateStart);
                });
            }
            if ($this->filterDateEnd) {
                $query->where('start_at', '<=', $this->filterDateEnd);
            }

            $projects = $query->get();

            // 2. Compute project stats
            /** @var Collection $allTasks */
            $allTasks = $projects->flatMap->tasks;
            $totalTasks = $allTasks->count();
            $doneTasks = $allTasks->where('status', 'done')->count();
            $today = $now->toDateString();
            $overdueTasks = $allTasks->filter(fn ($t) => $t->status !== 'done' && $t->due_at && $t->due_at->toDateString() < $today)->count();

            $projectStats = [
                'total_projects' => $projects->count(),
                'active_projects' => $projects->where('status', 'active')->count(),
                'total_tasks' => $totalTasks,
                'done_tasks' => $doneTasks,
                'overdue_tasks' => $overdueTasks,
                'completion_rate' => $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0,
            ];

            // 3. KPI summary (project cards)
            $projectIds = $projects->pluck('id');
            $riskCountsByProject = Risk::selectRaw('project_id, COUNT(*) as open_count')
                ->whereIn('project_id', $projectIds)
                ->where('status', 'open')
                ->groupBy('project_id')
                ->pluck('open_count', 'project_id');

            $kpiSummary = $projects->map(function ($project) use ($riskCountsByProject, $today) {
                $projectTasks = $project->tasks;
                $total = $projectTasks->count();
                $done = $projectTasks->where('status', 'done')->count();
                /** @var \Illuminate\Database\Eloquent\Collection<int, Task> $notDone */
                $notDone = $projectTasks->where('status', '!=', 'done');
                $overdue = $notDone->filter(fn (Task $t) => $t->due_at && $t->due_at->toDateString() < $today)->count();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status,
                    'completion_rate' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
                    'overdue_rate' => $notDone->count() > 0 ? round(($overdue / max($notDone->count(), 1)) * 100, 1) : 0,
                    'open_risks' => $riskCountsByProject->get($project->id, 0),
                    'task_count' => $total,
                    'source' => $project->source,
                    'start_at' => $project->start_at?->format('Y-m-d'),
                    'end_at' => $project->end_at?->format('Y-m-d'),
                ];
            })->toArray();

            // 4. Risk summary (scoped to filtered projects)
            $riskSummary = Risk::with('project')
                ->whereIn('project_id', $projectIds)
                ->open()
                ->orderBy('severity')
                ->orderBy('last_seen_at', 'desc')
                ->limit(10)
                ->get()
                ->map(fn (Risk $r) => [
                    'id' => $r->id,
                    'project_name' => $r->project->name ?? '全局',
                    'severity' => $r->severity,
                    'title' => $r->title,
                    'rule_key' => $r->rule_key,
                    'last_seen' => ($r->last_seen_at ?? $r->created_at)->diffForHumans(),
                ])->toArray();

            // 5. Resource heatmap (scoped to filtered projects)
            $projectUserIds = User::whereHas('tasks', fn ($q) => $q->whereIn('project_id', $projectIds))
                ->pluck('id');

            $allUsers = User::whereIn('id', $projectUserIds)->withCount([
                'tasks as pending_tasks' => fn ($q) => $q->whereIn('project_id', $projectIds)->pending(),
                'tasks as overdue_tasks' => fn ($q) => $q->whereIn('project_id', $projectIds)->pending()->overdue(),
            ])->get();

            $estimateSums = Task::selectRaw('assignee_id, COALESCE(SUM(estimate), 0) as total_estimate')
                ->whereIn('assignee_id', $allUsers->pluck('id'))
                ->whereIn('project_id', $projectIds)
                ->whereNotIn('status', ['done'])
                ->groupBy('assignee_id')
                ->pluck('total_estimate', 'assignee_id');

            $resourceHeatmap = $allUsers->map(function ($user) use ($estimateSums) {
                $estimateHours = (float) ($estimateSums->get($user->id, 0));

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'workload' => min(round($estimateHours / 40 * 100, 1), 200),
                    'active_tasks' => $user->pending_tasks,
                    'overdue_tasks' => $user->overdue_tasks,
                ];
            })->toArray();

            // 6. Trends (always global; unaffected by filters)
            $trendData = $buildTrend();
            [$kpiDates, $kpiTrendData] = $buildKpi();

            return compact(
                'projectStats', 'kpiSummary', 'riskSummary',
                'resourceHeatmap', 'trendData', 'kpiDates', 'kpiTrendData'
            );
        });

        // 从缓存结果解包到组件属性
        $this->projectStats = $cached['projectStats'];
        $this->kpiSummary = $cached['kpiSummary'];
        $this->riskSummary = $cached['riskSummary'];
        $this->resourceHeatmap = $cached['resourceHeatmap'];
        $this->trendData = $cached['trendData'];
        $this->kpiDates = $cached['kpiDates'];
        $this->kpiTrendData = $cached['kpiTrendData'];
    }

    protected function buildTrendData(): array
    {
        $startDate = now()->subDays(6)->startOfDay();

        $completedByDay = Task::selectRaw('DATE(completed_at) as d, COUNT(*) as cnt')
            ->where('completed_at', '>=', $startDate)
            ->whereNotNull('completed_at')
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $createdByDay = Task::selectRaw('DATE(created_at) as d, COUNT(*) as cnt')
            ->where('created_at', '>=', $startDate)
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $risksByDay = Risk::selectRaw('DATE(first_seen_at) as d, COUNT(*) as cnt')
            ->where('first_seen_at', '>=', $startDate)
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $days[] = [
                'date' => now()->subDays($i)->format('m/d'),
                'completed' => (int) ($completedByDay[$date] ?? 0),
                'created' => (int) ($createdByDay[$date] ?? 0),
                'risks' => (int) ($risksByDay[$date] ?? 0),
            ];
        }

        return $days;
    }

    protected function buildKpiTrend(): array
    {
        $metricKeys = ['completion_rate', 'overdue_ratio', 'unclosed_risks'];
        $metricIds = DB::table('metrics')
            ->whereNull('project_id')
            ->whereIn('key', $metricKeys)
            ->pluck('id', 'key');

        $snapshots = DB::table('metric_snapshots')
            ->whereIn('metric_id', $metricIds->values())
            ->where('captured_at', '>=', now()->subDays(6)->startOfDay())
            ->select('metric_id', 'value', DB::raw('DATE(captured_at) as date'))
            ->get()
            ->groupBy(fn ($r) => $r->metric_id.'_'.$r->date);

        $dates = [];
        $series = [
            'completion' => [],
            'overdue' => [],
            'risks' => [],
        ];
        $keyMap = [
            'completion_rate' => 'completion',
            'overdue_ratio' => 'overdue',
            'unclosed_risks' => 'risks',
        ];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $dates[] = now()->subDays($i)->format('m/d');

            foreach ($keyMap as $metricKey => $seriesKey) {
                $id = $metricIds[$metricKey] ?? 0;
                $snap = $snapshots->get("{$id}_{$day}")?->first();
                $series[$seriesKey][] = $snap ? round((float) $snap->value, 1) : null;
            }
        }

        return [$dates, $series];
    }

    public function render()
    {
        $this->loadStats();

        if ($this->needsDispatch) {
            $this->dispatch('dashboard-refreshed');
            $this->needsDispatch = false;
        }

        return view('livewire.dashboard')
            ->layout('layouts.app')
            ->title('仪表板 — Project Monitor');
    }
}
