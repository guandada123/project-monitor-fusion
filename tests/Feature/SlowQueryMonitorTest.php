<?php

use App\Livewire\SlowQueryMonitor;
use App\Models\SlowQuery;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

describe('SlowQueryMonitor component', function () {
    it('renders the page', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('慢查询监控');
    });

    it('shows stats with data', function () {
        // time 必须显式固定：factory 默认生成 100~3000ms 随机值，
        // 而组件默认 filterMinDuration=500ms，随机值会导致 stats.total 每次不同
        SlowQuery::factory()->count(5)->create([
            'captured_at' => now()->subHour(),
            'time' => 1200,
        ]);

        Livewire::test(SlowQueryMonitor::class)
            ->assertSet('stats.total', 5);
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
        // 必须固定 time >= 组件默认 filterMinDuration(500ms)，
        // 否则随机耗时会让记录被默认过滤掉，命中数量随机 → 断言时好时坏
        SlowQuery::factory()->create([
            'query' => 'SELECT * FROM users',
            'captured_at' => now()->subHour(),
            'time' => 1200,
        ]);
        SlowQuery::factory()->create([
            'query' => 'DELETE FROM logs',
            'captured_at' => now()->subHour(),
            'time' => 1200,
        ]);

        // 同时断言"命中"与"未命中"：只 assertSee('SELECT') 是假阳性断言，
        // 即使搜索完全失效，另一条 DELETE 记录外泄到页面上断言依然会通过
        Livewire::test(SlowQueryMonitor::class)
            ->set('search', 'SELECT')
            ->assertSee('SELECT * FROM users')
            ->assertDontSee('DELETE FROM logs');
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
            ->assertSet('trendChart.periods', fn ($v) => count($v) > 0);
    });

    it('shows empty state', function () {
        Livewire::test(SlowQueryMonitor::class)
            ->assertSee('暂无慢查询记录');
    });

    it('paginates results', function () {
        // 同上：固定 time 以避开默认 500ms 过滤，并直接断言统计值
        // 原实现用 assertSee('25') 匹配整个 HTML，属假阳性断言——
        // 只要页面任意位置出现 "25" 子串就通过，统计真的错了也发现不了
        SlowQuery::factory()->count(25)->create([
            'captured_at' => now()->subHour(),
            'time' => 1200,
        ]);

        Livewire::test(SlowQueryMonitor::class)
            ->assertSet('stats.total', 25);
    });
});
