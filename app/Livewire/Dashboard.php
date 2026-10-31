<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    // ——— 常量 ———
    /** 趋势数据回溯天数（含今天） */
    private const DEFAULT_TREND_DAYS = 7;

    private const int TREND_OFFSET = self::DEFAULT_TREND_DAYS - 1; // 6

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

    // ——— Ops 快照（Claw 采集的系统健康与成本，2026-10-31 接入）———
    // 数据源：/host/WorkBuddy/Claw/.workbuddy/data/ops_workbench/ops_snapshot.json
    // 为什么不落库：快照本身就是最新真值，Claw 每次覆盖写；pmf 不需要存历史，
    // 与 OpsApiController 读 /host/* 的做法一致。
    public array $opsHealth = [];

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

        $this->sourceOptions = Cache::remember('dashboard:source_options', 3600, function () {
            return Project::select('source')
                ->distinct()
                ->whereNotNull('source')
                ->pluck('source')
                ->toArray();
        });
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
    public function updatedFilterStatus(): void
    {
        $this->needsDispatch = true;
    }

    public function updatedFilterSource(): void
    {
        $this->needsDispatch = true;
    }

    public function updatedFilterDateStart(): void
    {
        $this->needsDispatch = true;
    }

    public function updatedFilterDateEnd(): void
    {
        $this->needsDispatch = true;
    }

    public function updatedSearch(): void
    {
        $this->needsDispatch = true;
    }

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
        $cacheKey = 'dashboard_stats:'.auth()->id().':'.md5(implode('|', [
            $this->filterStatus,
            $this->filterSource,
            $this->filterDateStart ?? '',
            $this->filterDateEnd ?? '',
            $this->search,
        ]));

        // 预提取方法引用，供闭包内使用（PHP 闭包中 $this 可能不可自动绑定）
        $buildTrend = fn () => $this->buildTrendData();
        $buildKpi = fn () => $this->buildKpiTrend();

        $cached = Cache::remember($cacheKey, 60, function () use ($buildTrend, $buildKpi) {
            // 1. Build filtered project query — eager load owner (tasks loaded via DB aggregation instead)
            $query = Project::with(['owner']);

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

            // 2. Compute project stats — use DB aggregations instead of Collection filtering
            $projectIds = $projects->pluck('id');

            // Single query for per-project stats; global totals derived from it (saves one SQL round-trip)
            $perProjectTaskStats = Task::selectRaw("
                    project_id,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
                    SUM(CASE WHEN status != 'done' AND due_at IS NOT NULL AND due_at < ? THEN 1 ELSE 0 END) as overdue
                ")
                ->whereIn('project_id', $projectIds)
                ->groupBy('project_id')
                ->addBinding(now()->startOfDay(), 'select')
                ->get()
                ->keyBy('project_id');

            $totalTasks = (int) $perProjectTaskStats->sum('total');
            $doneTasks = (int) $perProjectTaskStats->sum('done');
            $overdueTasks = (int) $perProjectTaskStats->sum('overdue');

            $projectStats = [
                'total_projects' => $projects->count(),
                'active_projects' => $projects->where('status', 'active')->count(),
                'total_tasks' => $totalTasks,
                'done_tasks' => $doneTasks,
                'overdue_tasks' => $overdueTasks,
                'completion_rate' => $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0,
            ];

            // 3. KPI summary (project cards)
            $riskCountsByProject = Risk::selectRaw('project_id, COUNT(*) as open_count')
                ->whereIn('project_id', $projectIds)
                ->where('status', 'open')
                ->groupBy('project_id')
                ->pluck('open_count', 'project_id');

            $kpiSummary = $projects->map(function ($project) use ($riskCountsByProject, $perProjectTaskStats) {
                $stats = $perProjectTaskStats->get($project->id);
                $total = (int) ($stats->total ?? 0);
                $done = (int) ($stats->done ?? 0);
                $overdue = (int) ($stats->overdue ?? 0);
                $notDone = $total - $done;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'slug' => $project->slug,
                    'status' => $project->status,
                    'completion_rate' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
                    // 区分「没有任务」与「任务完成 0%」——前者不该显示成 0%，那是拿未知冒充事实
                    'has_tasks' => $total > 0,
                    // tutor-init 进度画像（Claw 采集写入 meta.vibe）；无 .vibe 时为 null = 未接入
                    'vibe' => is_array($project->meta) ? ($project->meta['vibe'] ?? null) : null,
                    // 代码规模与活跃度（Claw 采集写入 meta.code）；非 git 项目为 null
                    'code' => is_array($project->meta) ? ($project->meta['code'] ?? null) : null,
                    'commits_7d' => is_array($project->meta) ? ($project->meta['commits_7d'] ?? null) : null,
                    'last_commit' => is_array($project->meta) ? ($project->meta['head_ts'] ?? null) : null,
                    'overdue_rate' => $notDone > 0 ? round(($overdue / max($notDone, 1)) * 100, 1) : 0,
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
        $startDate = now()->subDays(self::TREND_OFFSET)->startOfDay();

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
        for ($i = self::TREND_OFFSET; $i >= 0; $i--) {
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
            ->where('captured_at', '>=', now()->subDays(self::TREND_OFFSET)->startOfDay())
            ->select('metric_id', 'value', DB::raw('DATE(captured_at) as date'))
            ->limit(500)
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

        for ($i = self::TREND_OFFSET; $i >= 0; $i--) {
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

    /**
     * 读 Claw 采集的运维快照，提取「健康检查」与「成本/额度」。
     *
     * 快照不可读或陈旧时**显式标注**，不静默显示空值或旧值——
     * 这是审计里反复强调的：不许把"未知"当"正常"。
     */
    protected function loadOpsSnapshot(): void
    {
        $path = (string) config('ops.paths.ops_snapshot', '');
        $unavailable = [
            'available' => false,
            'note' => '快照路径未配置',
            'stale' => false,
            'age_hours' => null,
            'generated_at' => null,
            'checks_source_ts' => null,
            'counts' => ['total' => 0, 'pass' => 0, 'fail' => 0, 'observe' => 0],
            'failing' => [],
            'observing' => [],
            'cost' => null,
            'discovery' => null,
            'credit' => null,
        ];

        if ($path === '' || ! is_file($path)) {
            $this->opsHealth = array_merge($unavailable, ['note' => '快照文件不可读（Claw 是否已采集？）']);

            return;
        }

        $raw = @file_get_contents($path);
        $snap = $raw === false ? null : json_decode($raw, true);
        if (! is_array($snap)) {
            $this->opsHealth = array_merge($unavailable, ['note' => '快照 JSON 解析失败']);

            return;
        }

        $checks = is_array($snap['checks'] ?? null) ? $snap['checks'] : [];
        $items = is_array($checks['items'] ?? null) ? $checks['items'] : [];
        $counts = is_array($checks['counts'] ?? null) ? $checks['counts'] : [];

        // 只挑需要人看的：fail（必须处理）与 observe（观察中，不阻断但要可见）
        $failing = [];
        $observing = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $status = (string) ($it['status'] ?? '');
            if ($status !== 'fail' && $status !== 'observe') {
                continue;
            }
            $row = [
                'name' => (string) ($it['name'] ?? '(未命名)'),
                'status' => $status,
                'alert_count' => (int) ($it['alert_count'] ?? 0),
                'detail' => $this->firstAlertText($it),
            ];
            if ($status === 'fail') {
                $failing[] = $row;
            } else {
                $observing[] = $row;
            }
        }

        $generatedAt = $snap['generated_at'] ?? null;
        $ageHours = null;
        if (is_string($generatedAt) && ($ts = strtotime($generatedAt)) !== false) {
            $ageHours = round((time() - $ts) / 3600, 1);
        }

        $cost = is_array($snap['cost'] ?? null) ? $snap['cost'] : [];
        $costSummary = null;
        if (isset($cost['by_class']) && is_array($cost['by_class'])) {
            $human = is_array($cost['by_class']['human'] ?? null) ? $cost['by_class']['human'] : [];
            $auto = is_array($cost['by_class']['auto'] ?? null) ? $cost['by_class']['auto'] : [];
            $humanCny = (float) ($human['cny'] ?? 0);
            $autoCny = (float) ($auto['cny'] ?? 0);
            $costSummary = [
                'month' => (string) ($cost['month'] ?? ''),
                'human_cny' => $humanCny,
                'auto_cny' => $autoCny,
                'total_cny' => $humanCny + $autoCny,
                'join_rate' => (float) ($cost['coverage']['join_rate'] ?? 0),
                'source_ts' => (string) ($cost['source_ts'] ?? ''),
            ];
        }

        $credit = is_array($snap['credit'] ?? null) ? $snap['credit'] : null;

        // 发现回路：回答「有多少想法待我决策」。pool=池子总量，pending=等决策，landed=已落地
        $disc = is_array($snap['discovery'] ?? null) ? $snap['discovery'] : [];
        $discCounts = is_array($disc['counts'] ?? null) ? $disc['counts'] : [];
        $discovery = empty($discCounts) ? null : [
            'pool' => (int) ($discCounts['pool'] ?? 0),
            'pending' => (int) ($discCounts['pending'] ?? 0),
            'blocked' => (int) ($discCounts['blocked_pending'] ?? 0),
            'new_7d' => (int) ($discCounts['new_7d'] ?? 0),
            'landed' => (int) ($discCounts['landed'] ?? 0),
            'landed_7d' => (int) ($discCounts['landed_7d'] ?? 0),
        ];

        $this->opsHealth = [
            'available' => true,
            'note' => null,
            'stale' => $ageHours !== null && $ageHours > 6,
            'age_hours' => $ageHours,
            'generated_at' => is_string($generatedAt) ? $generatedAt : null,
            // 检查与快照是两个时刻：巡检 11:43 跑、采集 12:16 读。
            // 只显示一个时间会被误读成「检查是采集那一刻做的」。
            'checks_source_ts' => is_string($checks['source_ts'] ?? null) ? $checks['source_ts'] : null,
            'counts' => [
                'total' => (int) ($counts['total'] ?? count($items)),
                'pass' => (int) ($counts['pass'] ?? 0),
                'fail' => (int) ($counts['fail'] ?? count($failing)),
                'observe' => (int) ($counts['observe'] ?? count($observing)),
            ],
            'failing' => $failing,
            'observing' => $observing,
            'cost' => $costSummary,
            'discovery' => $discovery,
            'credit' => $credit === null ? null : [
                'used' => (float) ($credit['used'] ?? 0),
                'budget' => (float) ($credit['budget'] ?? 0),
                'pct' => (float) ($credit['pct'] ?? 0),
                'tier' => (string) ($credit['tier'] ?? ''),
            ],
        ];
    }

    /** 取告警第一条的前 120 字，避免整段证据挤爆列表。 */
    protected function firstAlertText(array $item): string
    {
        $alerts = $item['alerts'] ?? [];
        if (! is_array($alerts) || $alerts === []) {
            return (string) ($item['note'] ?? '');
        }

        return (string) Str::limit((string) ($alerts[0] ?? ''), 120);
    }

    public function render()
    {
        $this->loadStats();
        $this->loadOpsSnapshot();

        if ($this->needsDispatch) {
            $this->dispatch('dashboard-refreshed');
            $this->needsDispatch = false;
        }

        return view('livewire.dashboard')
            ->layout('layouts.app')
            ->title('仪表板 — Project Monitor');
    }
}
