<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 计划任务 Schedule
|--------------------------------------------------------------------------
|
| 需要在服务器 crontab 中添加：
| * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// 每日 08:30 — 生成日报并推送到飞书
Schedule::command('daily:report')
    ->dailyAt('08:30')
    ->appendOutputTo(storage_path('logs/daily-report.log'))
    ->description('项目日报生成与推送');

// 每小时 — 指标快照采集
Schedule::command('metrics:collect')
    ->hourly()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/metrics-collect.log'))
    ->description('指标快照自动采集');

// 每 30 分钟 — 风险扫描（仅记录，不推送）
Schedule::command('risk:scan --no-push')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/risk-scan.log'))
    ->description('风险自动扫描');

// 每 30 分钟 — GitHub 数据同步（失败自动重试 ×3）
Schedule::command('github:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping(15)
    ->appendOutputTo(storage_path('logs/github-sync.log'))
    ->description('GitHub 数据自动同步');

// ──────────────────────────────────────────────
// Ops Center 合并任务（原独立 Flask 应用功能）
// ──────────────────────────────────────────────

// 每 5 分钟 — 外部项目健康探测 (QTS/Claw/Marvis)
Schedule::command('ops:health-scan')
    ->everyFiveMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/ops-health.log'))
    ->description('外部项目健康探测（原 Ops Center）');

// 每小时 — AI 成本采集
Schedule::command('ops:collect-cost')
    ->hourly()
    ->appendOutputTo(storage_path('logs/ops-cost.log'))
    ->description('AI 成本采集（原 Ops Center）');

// 每 30 分钟 — WorkBuddy 自动化同步
Schedule::command('ops:sync-automations')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/ops-automations.log'))
    ->description('WorkBuddy 自动化同步（原 Ops Center）');

// 每 30 分钟 — 导入 Claw 采集的运维快照（2026-10-31 新增）
// 数据链：Claw(宿主) collect → ops_snapshot.json → 本命令 upsert projects 表
// 为什么要这一步：pmf 在容器内看不到宿主进程/git/gh，采集只能由 Claw 做；
// 此前 pmf 自己用 OpsProbe 探测，是残缺版（代码注释自认「结构上永远 DOWN」）。
Schedule::command('ops:import-snapshot')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)
    ->appendOutputTo(storage_path('logs/ops-import-snapshot.log'))
    ->description('导入 Claw 运维快照到项目表');
