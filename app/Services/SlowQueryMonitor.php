<?php

namespace App\Services;

use App\Models\SlowQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;

/**
 * SQL 慢查询监控服务
 *
 * 通过 DB::listen 监听所有数据库查询，超过阈值的记录结构化日志。
 * - >= 500ms: 记录 warning 日志（含 SQL、参数、耗时、调用栈）
 * - >= 1000ms: 同时推送飞书告警卡片
 *
 * 在 AppServiceProvider::boot() 中通过 SlowQueryMonitor::boot() 注册。
 */
class SlowQueryMonitor
{
    /** 日志记录阈值（毫秒） */
    const LOG_THRESHOLD_MS = 500;

    /** 飞书推送阈值（毫秒） */
    const FEISHU_THRESHOLD_MS = 1000;

    /**
     * 注册 DB::listen 监听器
     *
     * 在 AppServiceProvider::boot() 中调用：
     * SlowQueryMonitor::boot();
     */
    public static function boot(): void
    {
        DB::listen(function ($query) {
            if ($query->time >= static::LOG_THRESHOLD_MS) {
                (new self)->handle($query);
            }
        });
    }

    /**
     * 处理慢查询事件
     */
    protected function handle(object $query): void
    {
        $isSlow = $query->time >= static::LOG_THRESHOLD_MS;
        $isCritical = $query->time >= static::FEISHU_THRESHOLD_MS;
        $caller = $this->findCaller();
        $bindings = array_map(fn ($v) => is_string($v) ? $v : json_encode($v), $query->bindings ?? []);

        // 1. 记录日志
        $context = [
            'sql' => $query->sql,
            'bindings' => $bindings,
            'duration_ms' => $query->time,
            'connection' => $query->connectionName,
            'caller' => $caller,
        ];

        Log::warning(
            sprintf('[慢查询] %dms — %s', $query->time, $this->truncateSql($query->sql)),
            $context
        );

        // 2. 持久化到数据库
        // 🔴 2026-11-01 修（Dusk CI 真因，不是浏览器 flaky）：
        //    Dusk job 在 `migrate` 阶段偶发 25P02「current transaction is aborted」，
        //    报错位置在 0001_01_01_000002_create_jobs_table 的建索引语句，
        //    PG 服务端日志才露出真因：此前有一条 `INSERT INTO slow_queries` 失败
        //    （relation does not exist）。链条是：
        //      建表迁移耗时 >500ms → DB::listen 命中 → 这里写 slow_queries
        //      → 该表此刻还没建出来 → INSERT 报错 → **PostgreSQL 把当前事务标记为已中止**
        //      → 后面任何语句都是 25P02。PHP 这里的 try/catch 拦不住，
        //        因为捕到异常时事务已经废了（同「框架成功 ≠ 产出」：catch 只兜代码，兜不住 DB 语义）。
        //    做法：表不存在就只写日志、不落库。监控不该有能力拖垮迁移。
        if ($this->tableReady()) {
            try {
                SlowQuery::create([
                    'query' => $query->sql,
                    'bindings' => $bindings,
                    'time' => $query->time,
                    'connection_name' => $query->connectionName,
                    'caller' => $caller,
                    'request_uri' => Request::path(),
                    'request_method' => Request::method(),
                    'user_id' => auth()->id(),
                    'is_slow' => $isSlow,
                    'is_critical' => $isCritical,
                    'captured_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('慢查询持久化失败', ['error' => $e->getMessage()]);
            }
        }

        // 3. 超过 1000ms 的推送飞书告警
        //   2026-11-28：合成睡眠语句（select pg_sleep(...)）**不推**。
        //   这类行是取证探针自己造的，不是业务查询；它仍要落库（`is_critical` 照记，作为停顿证据：
        //   睡 1.2s 却记 5546ms = 卡了 4.3s），但进告警会让「告警里一半是自己造的」。
        //   口径与 Claw 仓 scripts/pgsql_stall_watch.py 的 is_probe_query() 一致。
        if ($isCritical && ! $this->isProbeQuery($query->sql)) {
            $this->pushFeishuAlert($query);
        }
    }

    /**
     * 是否是取证探针自己睡出来的语句（`select pg_sleep(...)`）。
     *
     * 业务代码不可能这么查。保留落库（作为停顿证据），但**不进告警**——
     * 与 Claw 仓 scripts/pgsql_stall_watch.py 的 is_probe_query() 同口径，
     * 两边判据必须一致，否则同一批行在一边沉默、在另一边告警。
     */
    protected function isProbeQuery(string $sql): bool
    {
        $normalized = strtolower(preg_replace('/\s+/', ' ', trim($sql)) ?? '');

        return str_starts_with($normalized, 'select pg_sleep(');
    }

    /**
     * 慢查询落地表是否就绪。
     *
     * 迁移过程中（表还没建出来）返回 false —— 此时只写日志，不写库，
     * 否则 INSERT 会把调用方的事务毒化（PG 事务中止不可撤销）。
     *
     * 用静态标志防重入：hasTable 本身也是一条查询，若它自己超过阈值就会递归。
     */
    private static bool $inspecting = false;

    protected function tableReady(): bool
    {
        if (self::$inspecting) {
            return false;
        }

        self::$inspecting = true;
        try {
            return Schema::hasTable('slow_queries');
        } catch (\Throwable) {
            return false;
        } finally {
            self::$inspecting = false;
        }
    }

    /**
     * 推送飞书慢查询告警
     */
    protected function pushFeishuAlert(object $query): void
    {
        try {
            $feishu = app(FeishuService::class);
            if (! $feishu->isConfigured()) {
                return;
            }

            // 🔴 2026-11-01：同一 SQL 指纹冷却，防同一症状反复刷群。
            // 背景（实测）：全站慢查询日均个位数，但来源高度集中 —— 空队列表轮询
            // `select * from "jobs" ...` 反复命中 4s 级，看得到也处理不了。
            // 同一症状一天内重复推送没有新增信息量，只会让人开始忽略这个群
            // （告警价值 = 症状准 × 药方对，重复推送把「药方」稀释成 0）。
            // **只降推送频次，不静音**：日志与 slow_queries 表照常全量记录，
            // 复盘要数据的时候一条不少。窗口可配，设 0 即关闭冷却。
            $cooldownMin = (int) config('ops.slow_query_alert_cooldown_min', 30);
            $fingerprint = 'slowq_alert:'.md5((string) $query->sql);

            if ($cooldownMin > 0 && Cache::has($fingerprint)) {
                Log::info('慢查询告警冷却中，本次不推送', [
                    'sql' => $this->truncateSql($query->sql, 120),
                    'duration_ms' => $query->time,
                    'cooldown_min' => $cooldownMin,
                ]);

                return;
            }

            $feishu->sendText(sprintf(
                "⚠️ 慢查询告警\n耗时：%dms\n连接：%s\nSQL：%s",
                $query->time,
                $query->connectionName,
                $this->truncateSql($query->sql, 200)
            ));

            if ($cooldownMin > 0) {
                Cache::put($fingerprint, now()->toIso8601String(), now()->addMinutes($cooldownMin));
            }
        } catch (\Throwable $e) {
            Log::error('慢查询飞书推送失败', [
                'error' => $e->getMessage(),
                'duration_ms' => $query->time,
            ]);
        }
    }

    /**
     * 从调用栈定位业务代码调用点（排除 vendor/ 目录与监控自身）
     *
     * 🔴 2026-11-14 修（慢查询告警无法定位来源）：
     *   原实现从 frame 0 起找「不含 /vendor/ 的帧」，但 frame 0 恒为
     *   `$caller = $this->findCaller();` 这一行（本文件）—— 于是永远返回自己。
     *   线上 slow_queries 台账 33 条的 caller 全是 SlowQueryMonitor.php:49/50/51，
     *   等于没有定位能力：看到告警也不知道是谁、哪条链路发的 SQL。
     *   现在先跳过本文件自身的帧（findCaller / handle / boot 闭包都在此），
     *   再返回第一个项目内调用点；只剩 vendor 与自身时返回 null。
     *
     * @param  array<int, array<string, mixed>>|null  $trace  便于单测注入调用栈
     */
    protected function findCaller(?array $trace = null): ?string
    {
        $trace ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);

        foreach ($trace as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }

            // 监控自身不算业务调用点（否则永远只报到自己）
            if ($frame['file'] === __FILE__) {
                continue;
            }

            if (str_contains($frame['file'], '/vendor/')) {
                continue;
            }

            return sprintf('%s:%d', $frame['file'], $frame['line'] ?? 0);
        }

        return null;
    }

    /**
     * 截断过长的 SQL 语句
     */
    protected function truncateSql(string $sql, int $maxLen = 500): string
    {
        return mb_strlen($sql) > $maxLen
            ? mb_substr($sql, 0, $maxLen).'...'
            : $sql;
    }
}
