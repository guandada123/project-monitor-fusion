<?php

namespace App\Services;

use App\Models\SlowQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

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
            'sql'       => $query->sql,
            'bindings'  => $bindings,
            'duration_ms' => $query->time,
            'connection' => $query->connectionName,
            'caller'    => $caller,
        ];

        Log::warning(
            sprintf('[慢查询] %dms — %s', $query->time, $this->truncateSql($query->sql)),
            $context
        );

        // 2. 持久化到数据库
        try {
            SlowQuery::create([
                'query'           => $query->sql,
                'bindings'        => $bindings,
                'time'            => $query->time,
                'connection_name' => $query->connectionName,
                'caller'          => $caller,
                'request_uri'     => Request::path(),
                'request_method'  => Request::method(),
                'user_id'         => auth()->id(),
                'is_slow'         => $isSlow,
                'is_critical'     => $isCritical,
                'captured_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('慢查询持久化失败', ['error' => $e->getMessage()]);
        }

        // 3. 超过 1000ms 的推送飞书告警
        if ($isCritical) {
            $this->pushFeishuAlert($query);
        }
    }

    /**
     * 推送飞书慢查询告警
     */
    protected function pushFeishuAlert(object $query): void
    {
        try {
            $feishu = app(FeishuService::class);
            if (!$feishu->isConfigured()) {
                return;
            }

            $feishu->sendText(sprintf(
                "⚠️ 慢查询告警\n耗时：%dms\n连接：%s\nSQL：%s",
                $query->time,
                $query->connectionName,
                $this->truncateSql($query->sql, 200)
            ));
        } catch (\Throwable $e) {
            Log::error('慢查询飞书推送失败', [
                'error' => $e->getMessage(),
                'duration_ms' => $query->time,
            ]);
        }
    }

    /**
     * 从调用栈定位业务代码调用点（排除 vendor/ 目录）
     */
    protected function findCaller(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 30);
        foreach ($trace as $frame) {
            if (isset($frame['file']) && !str_contains($frame['file'], '/vendor/')) {
                return sprintf('%s:%d', $frame['file'], $frame['line']);
            }
        }
        return null;
    }

    /**
     * 截断过长的 SQL 语句
     */
    protected function truncateSql(string $sql, int $maxLen = 500): string
    {
        return mb_strlen($sql) > $maxLen
            ? mb_substr($sql, 0, $maxLen) . '...'
            : $sql;
    }
}
