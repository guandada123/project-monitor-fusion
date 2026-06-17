<?php

use App\Livewire\SlowQueryMonitor;
use App\Models\SlowQuery;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->admin()->create());
});

describe('SlowQueryMonitor component', function () {
    it('renders the page', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('慢查询监控');
    });

    it('shows stats with data', function () {
        SlowQuery::factory()->count(5)->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('5');
    });

    it('filters by time range', function () {
        SlowQuery::factory()->create(['captured_at' => now()->subMinutes(30), 'time' => 1000]);
        SlowQuery::factory()->create(['captured_at' => now()->subHours(3), 'time' => 1000]);

        Livewire::test(SlowQueryMonitor::class)
            ->set('filterMinDuration', 0)
            ->set('filterRange', '1h')
            ->assertSet('stats.total', 1);
    });

    it('filters by type', function () {
        SlowQuery::factory()->slow()->create(['captured_at' => now()->subHour()]);
        SlowQuery::factory()->critical()->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->set('filterType', 'critical')
            ->assertSee('严重');
    });

    it('filters by min duration', function () {
        SlowQuery::factory()->create(['time' => 200, 'captured_at' => now()->subHour()]);
        SlowQuery::factory()->create(['time' => 1500, 'captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->set('filterMinDuration', 1000)
            ->assertSet('stats.total', 1);
    });

    it('searches by SQL content', function () {
        SlowQuery::factory()->create(['query' => 'SELECT * FROM users', 'captured_at' => now()->subHour()]);
        SlowQuery::factory()->create(['query' => 'DELETE FROM logs', 'captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->set('search', 'SELECT')
            ->assertSee('SELECT');
    });

    it('toggles sort field', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->call('toggleSort', 'time')
            ->assertSet('sortField', 'time')
            ->assertSet('sortDirection', 'desc');
    });

    it('toggles sort direction on same field', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->call('toggleSort', 'time')
            ->call('toggleSort', 'time')
            ->assertSet('sortDirection', 'asc');
    });

    it('toggles expanded query detail', function () {
        $sq = SlowQuery::factory()->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->call('toggleExpand', $sq->id)
            ->assertSet('expandedId', $sq->id);
    });

    it('collapses on second toggle', function () {
        $sq = SlowQuery::factory()->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->call('toggleExpand', $sq->id)
            ->call('toggleExpand', $sq->id)
            ->assertSet('expandedId', null);
    });

    it('computes active filter count', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->set('filterRange', '1h')
            ->set('filterMinDuration', 1000)
            ->assertSet('activeFilterCount', 2);
    });

    it('loads trend chart data', function () {
        SlowQuery::factory()->count(3)->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->assertSet('trendChart.periods', fn($v) => count($v) > 0);
    });

    it('shows empty state', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('暂无慢查询记录');
    });

    it('paginates results', function () {
        SlowQuery::factory()->count(25)->create(['captured_at' => now()->subHour()]);

        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('25'); // stats show total 25
    });
});
