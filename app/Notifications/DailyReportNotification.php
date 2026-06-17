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

        return [
            'type' => 'daily_report',
            'active_projects' => $activeProjects,
            'total_tasks' => $totalTasks,
            'open_risks' => $openRisks,
            'critical_risks' => $criticalRisks,
            'date' => now()->toDateString(),
            'url' => '/',
            'icon' => 'calendar',
            'message' => "日报 {$activeProjects} 项目 · {$totalTasks} 任务 · {$openRisks} 风险 ({$criticalRisks} 严重)",
        ];
    }
}
