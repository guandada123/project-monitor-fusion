<?php

// Ops 探测与宿主数据路径的**单一真源**（2026-09-28）
//
// 背景（实测，不是推测）：pmf 的三个容器（app/queue/scheduler）跑在 `project-monitor` 网络里，
// 既不是 host 网络、也没挂宿主目录。于是：
//   · `http://127.0.0.1:8000/8001/8002` 指的是**容器自己** → 三个服务恒 DOWN（cURL error 7）
//   · 宿主路径（/Users/guan/...、/Volumes/ZHITAI/...）在容器内**不存在** → 体积恒 `0 MB`
// 后果：`ops:health-scan` **6/6 恒失败**（ops-health.log 13 万行全是失败），面板 API 全瞎。
//
// 修法两条：① 探测走 `probe_host`（容器内已实测可解析、可连通宿主已发布端口）；
//           ② 需要宿主数据的**小文件/目录只读挂载**进来（见 docker-compose.yml 的 `/host/*`），
//              路径一律可被 env 覆盖 —— 换部署方式（host 网络 / 换挂载点）不用改代码。

return [
    // 容器 → 宿主已发布端口的入口。Docker Desktop 下 `host.docker.internal` 实测可解析可达；
    // 若改成 host 网络部署，把 OPS_PROBE_HOST 设成 127.0.0.1 即可。
    'probe_host' => env('OPS_PROBE_HOST', 'host.docker.internal'),

    // 2026-11-01：CI 状态不是实时探测的 —— pmf 只消费 Claw 采集的 ops_snapshot.json，
    // 与真实 CI 之间有约 1 小时延迟（实测：07:30 快照仍报 Claw 全绿，而它当时两次 failure）。
    // 日报必须把「数据时点」一并报出来，超过本阈值再加显式陈旧警告。
    'ci_stale_hours' => (int) env('OPS_CI_STALE_HOURS', 6),

    // 2026-11-01：慢查询飞书告警的同 SQL 冷却窗口（分钟），0 = 关闭冷却。
    // 只降推送频次，日志与 slow_queries 表照常全量记录 —— 不静音，只防复读。
    'slow_query_alert_cooldown_min' => (int) env('OPS_SLOW_QUERY_COOLDOWN_MIN', 30),

    // HTTP 探测目标（端口可用 env 覆盖，与 QTS compose 的端口映射对齐）
    'http_targets' => [
        'qts_engine' => ['port' => (int) env('OPS_QTS_ENGINE_PORT', 8000), 'path' => '/health'],
        'qts_backtest' => ['port' => (int) env('OPS_QTS_BACKTEST_PORT', 8001), 'path' => '/health'],
        'qts_monitor' => ['port' => (int) env('OPS_QTS_MONITOR_PORT', 8002), 'path' => '/health'],
    ],

    // 宿主侧数据（只读挂载；默认挂到 /host/*）
    'paths' => [
        'workbuddy_db' => env('OPS_WORKBUDDY_DB', '/host/workbuddy.db'),
        'ai_cost_log' => env('OPS_AI_COST_LOG', '/host/ai_cost_log.jsonl'),
        'state_anchor' => env('OPS_STATE_ANCHOR', '/host/cross_project_state.json'),
        // Claw 采集的运维快照（宿主侧 ops_workbench_collect.py 产出）。
        // pmf 不再自己探测宿主状态 —— 容器内看不到宿主进程，采集只能由宿主侧完成。
        'ops_snapshot' => env('OPS_SNAPSHOT_PATH', '/host/WorkBuddy/Claw/.workbuddy/data/ops_workbench/ops_snapshot.json'),
        'repos' => [
            'claw' => env('OPS_CLAW_PATH', '/host/WorkBuddy/Claw'),
            'qts' => env('OPS_QTS_PATH', '/host/WorkBuddy/QuantTradingSystem'),
            'stockinsight' => env('OPS_SI_PATH', '/host/WorkBuddy/StockInsight'),
            'marvis' => env('OPS_MARVIS_PATH', '/host/WorkBuddy/marvis_bridge'),
        ],
    ],

    // `du` 是重活（宿主实测 4 个仓 2.4s）；面板会被频繁请求，故缓存。
    // 缓存到期后重新实测 —— 缓存只是省时间，不是把旧值当现值（读出来会带 measured_at）。
    'dir_size_cache_minutes' => (int) env('OPS_DIR_SIZE_CACHE_MIN', 360),

    // workbuddy.db 快照刷新周期（分钟）。该库是 WAL 模式，只读挂载下无法直接打开
    // （SQLite 要写 -wal/-shm），故拷进容器可写区再读；API 会带 snapshot_at 如实标注。
    'db_snapshot_minutes' => (int) env('OPS_DB_SNAPSHOT_MIN', 10),

    // 取不到体积时对外显示的占位（**不许显示 0** —— 0 会被下游读成"很好"，实测踩过：
    // 旧实现的 sizeProgress 把无法获取的体积当 0 → roadmap 直接显示 100% 完成）
    'size_unavailable' => 'n/a',
];
