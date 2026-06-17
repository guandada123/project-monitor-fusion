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
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/metrics-collect.log'))
    ->description('指标快照自动采集');

// 每 30 分钟 — 风险扫描（仅记录，不推送）
Schedule::command('risk:scan --no-push')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/risk-scan.log'))
    ->description('风险自动扫描');

// 每 30 分钟 — GitHub 数据同步（失败自动重试 ×3）
Schedule::command('github:sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/github-sync.log'))
    ->description('GitHub 数据自动同步');
