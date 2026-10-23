<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 飞书/Lark Webhook 消息推送服务
 *
 * 支持分区标签、富文本卡片、多项目日报推送
 */
class FeishuService
{
    /**
     * Explicit settings passed to constructor (null = lazy-load from DB).
     *
     * @var array<string, mixed>
     */
    private array $settings;

    /**
     * Whether settings have been loaded from the database.
     */
    private bool $settingsLoaded = false;

    public function __construct(?array $settings = null)
    {
        // When settings are explicitly provided, use them immediately.
        // When null (container auto-resolution), defer DB load until first use.
        if ($settings !== null) {
            $this->settings = $settings;
            $this->settingsLoaded = true;
        }
    }

    // ─── Lazy settings access ───────────────────

    private function ensureSettingsLoaded(): void
    {
        if (! $this->settingsLoaded) {
            $this->settings = $this->loadSettings();
            $this->settingsLoaded = true;
        }
    }

    private function getWebhookUrl(): string
    {
        $this->ensureSettingsLoaded();

        return (string) ($this->settings['feishu_webhook_url'] ?? '');
    }

    private function getPartitionTag(): string
    {
        $this->ensureSettingsLoaded();

        return (string) ($this->settings['feishu_partition_tag'] ?? '[项目监控]');
    }

    private function getBotName(): string
    {
        $this->ensureSettingsLoaded();

        return (string) ($this->settings['feishu_bot_name'] ?? 'Project Monitor');
    }

    private function isDailyReportEnabled(): bool
    {
        $this->ensureSettingsLoaded();

        return (bool) ($this->settings['feishu_daily_report'] ?? true);
    }

    private function isRiskAlertEnabled(): bool
    {
        $this->ensureSettingsLoaded();

        return (bool) ($this->settings['feishu_risk_alert'] ?? true);
    }

    // ─── Public API ─────────────────────────────

    public function isConfigured(): bool
    {
        return $this->getWebhookUrl() !== '';
    }

    public function shouldSendDaily(): bool
    {
        return $this->isConfigured() && $this->isDailyReportEnabled();
    }

    public function shouldSendRiskAlert(): bool
    {
        return $this->isConfigured() && $this->isRiskAlertEnabled();
    }

    /**
     * 发送富文本卡片消息（日报用）
     */
    public function sendInteractiveCard(array $card): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => '未配置飞书 Webhook'];
        }

        try {
            $payload = [
                'msg_type' => 'interactive',
                'card' => $card,
            ];

            $resp = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->getWebhookUrl(), $payload);

            if ($resp->successful()) {
                $body = $resp->json();
                if (($body['code'] ?? -1) === 0) {
                    return ['ok' => true];
                }

                return ['ok' => false, 'error' => $body['msg'] ?? '飞书返回错误'];
            }

            Log::warning('Feishu webhook failed', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return ['ok' => false, 'error' => "HTTP {$resp->status()}"];
        } catch (\Throwable $e) {
            Log::error('Feishu webhook exception', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 发送简单文本消息（风险告警用）
     */
    public function sendText(string $content): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => '未配置飞书 Webhook'];
        }

        try {
            $payload = [
                'msg_type' => 'text',
                'content' => [
                    'text' => "{$this->getPartitionTag()} {$content}",
                ],
            ];

            $resp = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->getWebhookUrl(), $payload);

            if ($resp->successful()) {
                $body = $resp->json();

                return ['ok' => ($body['code'] ?? -1) === 0];
            }

            return ['ok' => false, 'error' => "HTTP {$resp->status()}"];
        } catch (\Throwable $e) {
            Log::error('Feishu text push failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 构建日报卡片
     */
    public function buildDailyCard(array $report): array
    {
        $tag = $this->getPartitionTag();
        $date = now()->toDateString();
        $weekday = ['日', '一', '二', '三', '四', '五', '六'][now()->dayOfWeek];

        // Header
        $elements = [
            [
                'tag' => 'markdown',
                'content' => "**{$tag} 项目日报**  {$date} 周{$weekday}",
            ],
            ['tag' => 'hr'],
        ];

        // Summary stats
        $s = $report['summary'];
        $elements[] = [
            'tag' => 'markdown',
            'content' => sprintf(
                "📊 **总览**\n活跃项目：%d | 待办任务：%d | 进行中：%d | 已完成：%d",
                $s['active_projects'] ?? 0,
                $s['todo_tasks'] ?? 0,
                $s['in_progress_tasks'] ?? 0,
                $s['done_tasks'] ?? 0,
            ),
        ];

        // Risk alerts
        if (! empty($report['risks'])) {
            $elements[] = ['tag' => 'hr'];
            $riskLines = ['⚠️ **风险预警**'];
            foreach (array_slice($report['risks'], 0, 5) as $r) {
                $sevEmoji = ['critical' => '🔴', 'high' => '🟠', 'medium' => '🟡', 'low' => '🟢'][$r['severity']] ?? '⚪';
                $riskLines[] = "{$sevEmoji} **{$r['title']}** — {$r['project_name']}";
            }
            $elements[] = ['tag' => 'markdown', 'content' => implode("\n", $riskLines)];
        }

        // Per-project detail
        if (! empty($report['projects'])) {
            $elements[] = ['tag' => 'hr'];
            $projLines = ['📋 **项目详情**'];
            $idx = 1;
            foreach ($report['projects'] as $p) {
                $statusEmoji = ['active' => '🟢', 'pending' => '🔵', 'completed' => '✅', 'archived' => '⚫'][$p['status']] ?? '⚪';
                $projLines[] = sprintf(
                    '%d. %s **%s** 进度 %.0f%%  (完成 %d/%d)',
                    $idx++,
                    $statusEmoji,
                    $p['name'],
                    $p['completion_rate'] ?? 0,
                    $p['done'] ?? 0,
                    $p['total'] ?? 0,
                );
            }
            $elements[] = ['tag' => 'markdown', 'content' => implode("\n", $projLines)];
        }

        // Footer
        $elements[] = ['tag' => 'hr'];
        $elements[] = [
            'tag' => 'note',
            'elements' => [
                ['tag' => 'plain_text', 'content' => "🤖 由 {$this->getBotName()} 自动生成 · ".now()->format('H:i')],
            ],
        ];

        return [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => ['tag' => 'plain_text', 'content' => "{$tag} 项目日报"],
                'template' => 'blue',
            ],
            'elements' => $elements,
        ];
    }

    /**
     * 构建风险告警卡片
     */
    public function buildRiskAlertCard(array $risk): array
    {
        $sevColor = ['critical' => 'red', 'high' => 'orange', 'medium' => 'yellow', 'low' => 'blue'][$risk['severity']] ?? 'default';
        $sevText = ['critical' => '严重', 'high' => '高', 'medium' => '中', 'low' => '低'][$risk['severity']] ?? '未知';

        return [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => ['tag' => 'plain_text', 'content' => "⚠️ {$this->getPartitionTag()} 风险告警"],
                'template' => $sevColor,
            ],
            'elements' => [
                ['tag' => 'markdown', 'content' => "**风险等级：{$sevText}**"],
                ['tag' => 'markdown', 'content' => "**标题：** {$risk['title']}"],
                ['tag' => 'markdown', 'content' => "**所属项目：** {$risk['project_name']}"],
                ['tag' => 'markdown', 'content' => "**详情：** {$risk['detail']}"],
                ['tag' => 'hr'],
                ['tag' => 'note', 'elements' => [
                    ['tag' => 'plain_text', 'content' => "检测规则：{$risk['rule_key']} · 首次发现：".($risk['first_seen_at'] ?? '—')],
                ]],
            ],
        ];
    }

    /**
     * 推送项目进度报告到飞书群
     *
     * 从 FeishuAlertService 迁移合并而来。
     */
    public function sendReportSummary(Project $project, string $reportUrl = ''): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'error' => '飞书 Webhook 未配置'];
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

        $card = [
            'config' => ['wide_screen_mode' => true],
            'header' => [
                'title' => ['tag' => 'plain_text', 'content' => '📊 项目进度报告'],
                'template' => $openRisks > 3 ? 'red' : ($completion >= 80 ? 'green' : 'blue'),
            ],
            'elements' => [
                ['tag' => 'markdown', 'content' => $md],
                ['tag' => 'hr'],
                ['tag' => 'note', 'elements' => [
                    ['tag' => 'plain_text', 'content' => '— 项目监控仪表板 · '.now()->format('Y-m-d H:i')],
                ]],
            ],
        ];

        return $this->sendInteractiveCard($card);
    }

    // ─── Settings loading ───────────────────────

    protected function loadSettings(): array
    {
        return app(SettingsService::class)->getGroup('notification');
    }
}
