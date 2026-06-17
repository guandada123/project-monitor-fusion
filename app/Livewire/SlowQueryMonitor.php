<?php

namespace App\Livewire;

use App\Models\SlowQuery;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SlowQueryMonitor extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // ——— Filter state ———

    #[Url(as: 'range', history: true)]
    public string $filterRange = '24h';

    #[Url(as: 'min', history: true)]
    public int $filterMinDuration = 500;

    #[Url(as: 'type', history: true)]
    public string $filterType = 'all';

    #[Url(as: 'conn', history: true)]
    public string $filterConnection = '';

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'sort', history: true)]
    public string $sortField = 'captured_at';

    #[Url(as: 'dir', history: true)]
    public string $sortDirection = 'desc';

    // ——— Data ———

    public array $stats = [];
    public array $trendChart = [];
    public array $connectionOptions = [];

    // ——— Query detail expand ———

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->connectionOptions = SlowQuery::select('connection_name')
            ->distinct()
            ->whereNotNull('connection_name')
            ->pluck('connection_name')
            ->toArray();

        $this->loadStats();
        $this->loadTrend();
    }

    // ——— Filter change handlers ———

    public function updatedFilterRange(): void       { $this->resetPage(); $this->loadStats(); $this->loadTrend(); }
    public function updatedFilterMinDuration(): void { $this->resetPage(); $this->loadStats(); $this->loadTrend(); }
    public function updatedFilterType(): void        { $this->resetPage(); $this->loadStats(); $this->loadTrend(); }
    public function updatedFilterConnection(): void  { $this->resetPage(); $this->loadStats(); $this->loadTrend(); }
    public function updatedSearch(): void            { $this->resetPage(); }

    public function updatedSortField(): void         { $this->resetPage(); }
    public function updatedSortDirection(): void     { $this->resetPage(); }

    public function toggleSort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function clearAll(): void
    {
        $this->authorize('manage_settings');

        SlowQuery::truncate();
        $this->loadStats();
        $this->loadTrend();
        $this->dispatch('notify', '已清空所有慢查询记录');
    }

    // ——— Filter metadata ———

    public function getActiveFilterCountProperty(): int
    {
        $count = 0;
        if ($this->filterRange !== '24h') $count++;
        if ($this->filterMinDuration !== 500) $count++;
        if ($this->filterType !== 'all') $count++;
        if ($this->filterConnection !== '') $count++;
        if ($this->search !== '') $count++;
        return $count;
    }

    public function getRangeLabelProperty(): string
    {
        return match ($this->filterRange) {
            '1h'    => '过去 1 小时',
            '6h'    => '过去 6 小时',
            '24h'   => '过去 24 小时',
            '7d'    => '过去 7 天',
            '30d'   => '过去 30 天',
            'all'   => '全部时间',
            default => $this->filterRange,
        };
    }

    // ——— Query builder ———

    protected function buildQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = SlowQuery::query();

        // Time range
        $query->when($this->filterRange !== 'all', function ($q) {
            $hours = match ($this->filterRange) {
                '1h'  => 1,
                '6h'  => 6,
                '24h' => 24,
                '7d'  => 168,
                '30d' => 720,
                default => 24,
            };
            $q->where('captured_at', '>=', now()->subHours($hours));
        });

        // Type filter
        $query->when($this->filterType === 'slow', fn($q) => $q->where('is_slow', true))
              ->when($this->filterType === 'critical', fn($q) => $q->where('is_critical', true));

        // Connection filter
        $query->when($this->filterConnection !== '', fn($q) => $q->where('connection_name', $this->filterConnection));

        // Min duration
        if ($this->filterMinDuration > 0) {
            $query->where('time', '>=', $this->filterMinDuration);
        }

        // Search in SQL
        $query->when($this->search !== '', function ($q) {
            $search = $this->search;
            $q->where(function ($sub) use ($search) {
                $sub->where('query', 'like', "%{$search}%")
                     ->orWhere('caller', 'like', "%{$search}%");
            });
        });

        return $query;
    }

    // ——— Stats ———

    protected function loadStats(): void
    {
        $query = $this->buildQuery();

        $stats = (clone $query)->select([
            DB::raw('COUNT(*) as total'),
            DB::raw('COALESCE(ROUND(AVG(time), 1), 0) as avg_time'),
            DB::raw('COALESCE(MAX(time), 0) as max_time'),
            DB::raw('SUM(CASE WHEN is_critical = true THEN 1 ELSE 0 END) as critical_count'),
        ])->first();

        $this->stats = [
            'total'          => (int) ($stats->total ?? 0),
            'avg_time'       => (float) ($stats->avg_time ?? 0),
            'max_time'       => (float) ($stats->max_time ?? 0),
            'critical_count' => (int) ($stats->critical_count ?? 0),
        ];
    }

    // ——— Trend chart ———

    protected function loadTrend(): void
    {
        $query = $this->buildQuery();

        // Group by hour for last 24h, by day for longer ranges
        $isShortRange = in_array($this->filterRange, ['1h', '6h', '24h']);
        $driver = DB::connection()->getDriverName();

        if ($isShortRange) {
            $groupExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m-%d %H:00', captured_at)"
                : "to_char(captured_at, 'YYYY-MM-DD HH24:00')";
        } else {
            $groupExpr = 'DATE(captured_at)';
        }

        $rows = (clone $query)
            ->select([
                DB::raw($groupExpr . ' as period'),
                DB::raw('COUNT(*) as cnt'),
                DB::raw('COALESCE(ROUND(AVG(time), 1), 0) as avg_t'),
                DB::raw('SUM(CASE WHEN is_critical = true THEN 1 ELSE 0 END) as critical_cnt'),
            ])
            ->groupBy(DB::raw($groupExpr))
            ->orderBy(DB::raw('MIN(captured_at)'))
            ->get();

        $this->trendChart = [
            'periods'        => $rows->pluck('period')->toArray(),
            'counts'         => $rows->pluck('cnt')->toArray(),
            'avgTimes'       => $rows->pluck('avg_t')->toArray(),
            'criticalCounts' => $rows->pluck('critical_cnt')->toArray(),
        ];
    }

    // ——— Render ———

    public function render()
    {
        $queries = $this->buildQuery()
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(20);

        return view('livewire.slow-query-monitor', [
            'queries' => $queries,
        ])
            ->layout('layouts.app')
            ->title('慢查询监控 — Project Monitor');
    }
}
