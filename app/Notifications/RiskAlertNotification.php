<?php

namespace App\Notifications;

use App\Models\Risk;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RiskAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Risk $risk,
        public string $action = 'created', // created | acknowledged | resolved
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $severityColors = [
            'critical' => '#ef4444',
            'high' => '#f97316',
            'medium' => '#f59e0b',
            'low' => '#10b981',
        ];

        return [
            'type' => 'risk_alert',
            'action' => $this->action,
            'risk_id' => $this->risk->id,
            'risk_title' => $this->risk->title,
            'severity' => $this->risk->severity,
            'severity_color' => $severityColors[$this->risk->severity] ?? '#6366f1',
            'rule_key' => $this->risk->rule_key,
            'project_id' => $this->risk->project_id,
            'project_name' => ($this->risk->project->name ?? '通用'),
            'detail' => \Illuminate\Support\Str::limit($this->risk->detail ?? '', 120),
            'url' => '/risks',
            'icon' => 'alert-triangle',
            'message' => $this->action === 'created'
                ? "发现新风险：{$this->risk->title}"
                : ($this->action === 'acknowledged'
                    ? "风险已确认：{$this->risk->title}"
                    : "风险已解决：{$this->risk->title}"),
        ];
    }
}
