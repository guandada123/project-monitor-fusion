<?php

namespace App\Services\RiskEngine;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FeishuAlertService
{
    protected string $webhookUrl;

    public function __construct()
    {
        $this->webhookUrl = config('services.feishu.webhook_url', '');
    }

    public function sendRiskAlert(Project $project, array $risks): void
    {
        if (empty($risks) || empty($this->webhookUrl)) {
            return;
        }

        $riskLines = '';
        foreach ($risks as $risk) {
            $emoji = match ($risk['severity']) {
                'critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🟢', default => '⚪',
            };
            $riskLines .= "{$emoji} **{$risk['title']}** — {$risk['detail']}\n";
        }

        $body = [
            'msg_type' => 'interactive',
            'card' => [
                'header' => [
                    'title' => ['content' => '⚠️ 项目风险预警', 'tag' => 'plain_text'],
                    'template' => 'red',
                ],
                'elements' => [
                    ['tag' => 'div', 'text' => ['content' => "**项目**: {$project->name}\n\n{$riskLines}\n— 项目监控系统", 'tag' => 'lark_md']],
                ],
            ],
        ];

        $this->post($body, 'FeishuAlertService: risk alert sent');
    }

    /**
     * Push project report summary to Feishu group.
     */
    public function sendReportSummary(Project $project, string $reportUrl = ''): array
    {
        if (empty($this->webhookUrl)) {
            return ['ok' => false, 'error' => '飞书 Webhook URL 未配置，请在 .env 中设置 FEISHU_WEBHOOK_URL'];
        }

        $completion = $project->completionRate();
        $totalTasks = $project->tasks()->count();
        $openRisks = $project->risks()->where('status', 'open')->count();
        $closedRisks = $project->risks()->where('status', 'closed')->count();

        $statusEmoji = $completion >= 80 ? '🟢' : ($completion >= 50 ? '🟡' : '🔴');
        $riskEmoji = $openRisks > 3 ? '🔴' : ($openRisks > 0 ? '🟡' : '🟢');

        $md = "{$statusEmoji} **{$project->name}** — 项目进度报告\n\n";
        $md .= "📊 完成率: **{$completion}%**\n";
        $md .= "📋 总任务: {$totalTasks} 个\n";
        $md .= "{$riskEmoji} 未闭环风险: {$openRisks} 个（已闭环 {$closedRisks} 个）\n";
        $md .= "🏷 状态: {$project->status}\n";
        $md .= "📅 周期: {$project->start_at} ~ {$project->end_at}\n\n";

        if ($reportUrl) {
            $md .= "[📄 查看完整报告]({$reportUrl})";
        }

        $body = [
            'msg_type' => 'interactive',
            'card' => [
                'header' => [
                    'title' => ['content' => '📊 项目进度报告', 'tag' => 'plain_text'],
                    'template' => $openRisks > 3 ? 'red' : ($completion >= 80 ? 'green' : 'blue'),
                ],
                'elements' => [
                    ['tag' => 'div', 'text' => ['content' => $md, 'tag' => 'lark_md']],
                    ['tag' => 'hr'],
                    ['tag' => 'note', 'elements' => [
                        ['tag' => 'plain_text', 'content' => '— 项目监控仪表板 · '.now()->format('Y-m-d H:i')],
                    ]],
                ],
            ],
        ];

        return $this->post($body, 'FeishuAlertService: report sent');
    }

    /**
     * Execute the HTTP POST and return result.
     */
    protected function post(array $body, string $logLabel): array
    {
        try {
            $resp = Http::timeout(10)->post($this->webhookUrl, $body);
            $ok = $resp->successful();
            Log::info($logLabel, ['status' => $resp->status(), 'ok' => $ok]);

            return ['ok' => $ok, 'status' => $resp->status(), 'body' => $resp->body()];
        } catch (\Throwable $e) {
            Log::error('FeishuAlertService error', ['msg' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
