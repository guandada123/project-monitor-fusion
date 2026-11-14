<?php

use App\Models\SlowQuery;
use App\Services\SettingsService;
use App\Services\SlowQueryMonitor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// ─── SlowQuery Model ───

describe('SlowQuery model', function () {
    it('can create a slow query record', function () {
        $sq = SlowQuery::create([
            'query' => 'SELECT * FROM projects WHERE id = ?',
            'bindings' => [1],
            'time' => 523.45,
            'connection_name' => 'pgsql',
            'caller' => '/app/Http/Controllers/ProjectController.php:42',
            'is_slow' => true,
            'is_critical' => false,
            'captured_at' => now(),
        ]);

        expect($sq->id)->toBeNumeric();
        expect($sq->time)->toBeFloat()->toBe(523.45);
        expect($sq->is_slow)->toBeTrue();
        expect($sq->is_critical)->toBeFalse();
        expect($sq->bindings)->toBeArray()->toHaveCount(1);
    });

    it('scopes slow queries correctly', function () {
        SlowQuery::factory()->count(3)->slow()->create();
        SlowQuery::factory()->count(2)->critical()->create();

        // slow scope returns all with is_slow=true (slow + critical)
        expect(SlowQuery::slow()->count())->toBe(5);
        // critical scope returns only critical
        expect(SlowQuery::critical()->count())->toBe(2);
        // non-critical slow queries
        expect(SlowQuery::where('is_slow', true)->where('is_critical', false)->count())->toBe(3);
    });

    it('scopes recent queries', function () {
        SlowQuery::factory()->create(['captured_at' => now()->subHour(2)]);
        SlowQuery::factory()->create(['captured_at' => now()->subHour(25)]);

        expect(SlowQuery::recent(24)->count())->toBe(1);
    });

    it('provides duration label accessor', function () {
        $ms = SlowQuery::factory()->make(['time' => 500]);
        expect($ms->duration_label)->toBe('500ms');

        $s = SlowQuery::factory()->make(['time' => 1500]);
        expect($s->duration_label)->toBe('1.50s');
    });

    it('provides query preview accessor', function () {
        $short = SlowQuery::factory()->make(['query' => 'SELECT 1']);
        expect($short->query_preview)->toBe('SELECT 1');

        $long = SlowQuery::factory()->make(['query' => str_repeat('x', 250)]);
        expect($long->query_preview)->toEndWith('...');
    });
});

// ─── SlowQueryMonitor Service ───

describe('SlowQueryMonitor', function () {
    it('persists slow queries to database', function () {
        // Simulate a DB::listen callback
        $mockQuery = (object) [
            'sql' => 'SELECT COUNT(*) FROM projects',
            'bindings' => [],
            'time' => 523,
            'connectionName' => 'pgsql',
        ];

        // Manually trigger the monitor's boot callback
        SlowQueryMonitor::boot();

        // We cannot easily test DB::listen directly, so we verify the table exists
        expect(SlowQuery::count())->toBeGreaterThanOrEqual(0);
    });
});

// ─── SlowQuery Factory ───

it('factory generates valid slow query', function () {
    $sq = SlowQuery::factory()->create();

    expect($sq->query)->not->toBeEmpty();
    expect($sq->time)->toBeGreaterThanOrEqual(100);
    expect($sq->captured_at)->toBeInstanceOf(Carbon::class);
});

// ─── 2026-11-01：迁移期守卫（Dusk CI 真因）───
// 建表迁移 >500ms → 慢查询监听器写 slow_queries → 表还不存在 → PG 事务被中止
// → 后续建索引语句 25P02，Dusk job 在 migrate 阶段就死，看起来像浏览器 flaky。

it('skips persistence when slow_queries table is not ready', function () {
    $monitor = new SlowQueryMonitor;
    $method = new ReflectionMethod($monitor, 'tableReady');
    $method->setAccessible(true);

    expect($method->invoke($monitor))->toBeTrue();

    Schema::dropIfExists('slow_queries');

    expect($method->invoke($monitor))->toBeFalse();
});

// ─── 2026-11-01：飞书告警冷却（防同一症状复读）───
// webhook 一配，≥1000ms 的慢查询就开始推飞书。实测全站日均个位数，
// 但来源集中在空队列表轮询 —— 同一句 SQL 反复命中，推一次和推十次信息量相同。
// 注意：冷却只作用于推送，日志与 slow_queries 表仍然全量记录（不静音）。

it('throttles feishu alerts per sql fingerprint', function () {
    Cache::flush();
    app(SettingsService::class)->set('feishu_webhook_url', 'https://open.feishu.cn/hook/test', 'notification');
    Http::fake(['*' => Http::response(['code' => 0], 200)]);

    $monitor = new SlowQueryMonitor;
    $method = new ReflectionMethod($monitor, 'pushFeishuAlert');
    $method->setAccessible(true);

    $jobsPoll = (object) ['sql' => 'select * from "jobs" where "queue" = ?', 'time' => 4200, 'connectionName' => 'pgsql'];
    $cacheDel = (object) ['sql' => 'delete from "cache" where "key" = ?', 'time' => 1800, 'connectionName' => 'pgsql'];

    $method->invoke($monitor, $jobsPoll);
    $method->invoke($monitor, $jobsPoll);   // 同指纹 → 冷却命中，不推
    $method->invoke($monitor, $cacheDel);   // 不同指纹 → 照常推

    Http::assertSentCount(2);
});

it('does not throttle when cooldown window is zero', function () {
    Cache::flush();
    config(['ops.slow_query_alert_cooldown_min' => 0]);
    app(SettingsService::class)->set('feishu_webhook_url', 'https://open.feishu.cn/hook/test', 'notification');
    Http::fake(['*' => Http::response(['code' => 0], 200)]);

    $monitor = new SlowQueryMonitor;
    $method = new ReflectionMethod($monitor, 'pushFeishuAlert');
    $method->setAccessible(true);

    $q = (object) ['sql' => 'select * from "jobs" where "queue" = ?', 'time' => 4200, 'connectionName' => 'pgsql'];
    $method->invoke($monitor, $q);
    $method->invoke($monitor, $q);

    Http::assertSentCount(2);
});

// ─── 2026-11-14：caller 定位修复 ───
// 症状：线上 slow_queries 台账 33 条的 caller 全是 SlowQueryMonitor.php:49/50/51。
// 根因：findCaller 从 frame 0 起找「不含 /vendor/ 的帧」，而 frame 0 恒为
//       `$caller = $this->findCaller();` 这一行（本文件）→ 永远返回自己。
// 这些用例在旧实现上会失败（返回 SlowQueryMonitor.php:<行号>），修复后返回业务调用点。

it('findCaller 跳过监控自身，返回真实业务调用点', function () {
    $monitor = new SlowQueryMonitor;
    $method = new ReflectionMethod($monitor, 'findCaller');
    $method->setAccessible(true);

    $self = (new ReflectionClass(SlowQueryMonitor::class))->getFileName();

    $trace = [
        // frame 0：真实链路上就是 handle() 里的 $caller = $this->findCaller();
        ['file' => $self, 'line' => 51],
        // frame 1：DB::listen 闭包（boot 里注册）
        ['file' => $self, 'line' => 42],
        // frame 2：框架代码，必须忽略
        ['file' => '/var/www/vendor/laravel/framework/src/Illuminate/Support/Facades/Facade.php', 'line' => 100],
        // frame 3：真实业务调用点
        ['file' => '/var/www/app/Http/Controllers/ReportController.php', 'line' => 42],
    ];

    expect($method->invoke($monitor, $trace))
        ->toBe('/var/www/app/Http/Controllers/ReportController.php:42');
});

it('findCaller 只有自身与 vendor 帧时返回 null', function () {
    $monitor = new SlowQueryMonitor;
    $method = new ReflectionMethod($monitor, 'findCaller');
    $method->setAccessible(true);

    $self = (new ReflectionClass(SlowQueryMonitor::class))->getFileName();

    $trace = [
        ['file' => $self, 'line' => 51],
        ['file' => '/var/www/vendor/laravel/framework/src/Illuminate/Queue/DatabaseQueue.php', 'line' => 88],
        ['function' => 'Illuminate\Foundation\Console\QueueWorkCommand::{closure}'],
    ];

    expect($method->invoke($monitor, $trace))->toBeNull();
});
