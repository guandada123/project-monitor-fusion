<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DailyReportNotification extends Notification
{
    use Queueable;

    public function __construct(
        public array $reportData,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $activeProjects = $this->reportData['active_projects'] ?? 0;
        $totalTasks = $this->reportData['total_tasks'] ?? 0;
        $openRisks = $this->reportData['open_risks'] ?? 0;
        $criticalRisks = $this->reportData['critical_risks'] ?? 0;

        // 2026-10-31：tasks/risks 表无数据时，"N 任务 · N 风险" 恒为 0，
        // 日报每天推送的信息量为零。无任务数据时改用采集快照的真实指标。
        $totalLoc = (int) ($this->reportData['total_loc'] ?? 0);
        $commits7d = (int) ($this->reportData['commits_7d'] ?? 0);
        $ciRed = (int) ($this->reportData['ci_red_projects'] ?? 0);

        $message = $totalTasks > 0
            ? "日报 {$activeProjects} 项目 · {$totalTasks} 任务 · {$openRisks} 风险 ({$criticalRisks} 严重)"
            : sprintf(
                '日报 %d 项目 · %s 万行 · 近 7 天 %d 次提交%s',
                $activeProjects,
                number_format($totalLoc / 10000, 1),
                $commits7d,
                $ciRed > 0 ? " · {$ciRed} 个主分支 CI 红" : ''
            );

        return [
            'type' => 'daily_report',
            'active_projects' => $activeProjects,
            'total_tasks' => $totalTasks,
            'open_risks' => $openRisks,
            'critical_risks' => $criticalRisks,
            'total_loc' => $totalLoc,
            'commits_7d' => $commits7d,
            'ci_red_projects' => $ciRed,
            'date' => now()->toDateString(),
            'url' => '/',
            'icon' => 'calendar',
            'message' => $message,
        ];
    }
}
