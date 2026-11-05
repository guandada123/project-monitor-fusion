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

    /**
     * 是否是飞书侧限流（HTTP 200 + code≠0 的 frequency limited，或 429）。
     *
     * 抽成公共判据：调用方要据此决定「退避重试」还是「直接放弃」，
     * 关键字不能各写一套 —— 2026-11-05 之前就是因为只在命令里认「frequency limited」，
     * 而 service 层这个分支连日志都不打，导致限流在 laravel.log 里完全不可见。
     */
    public static function isRateLimitError(string $error): bool
    {
        $lower = mb_strtolower($error);

        return str_contains($lower, 'frequency limited')
            || str_contains($lower, 'rate limit')
            || str_contains($lower, 'too many request')
            || str_contains($lower, 'http 429');
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

                // 2026-11-05：这分支原来只 return、不打日志。飞书限流是 HTTP 200 + code≠0，
                // 于是 laravel.log 里查不到任何痕迹，排障只能靠调用方打印的那行中文。
                Log::warning('Feishu webhook rejected', [
                    'code' => $body['code'] ?? null,
                    'msg' => $body['msg'] ?? null,
                    'msg_type' => 'interactive',
                    'rate_limited' => self::isRateLimitError((string) ($body['msg'] ?? '')),
                ]);

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
        // 🔴 2026-11-01 修：卡片曾漏掉 10-31 的「假 0%」修复。
        //    当时只改了命令行表格与 Markdown 两个渲染路径，卡片是第三个，
        //    因为 webhook 一直没配、卡片从未真正发出去，所以没人发现它还在报
        //    「待办 0/进行中 0/已完成 0」和「进度 0% (0/0)」。
        //    教训：**同一份数据有几个渲染出口，就有几个地方会漏**——口径必须三处一致。
        $s = $report['summary'];
        $freshness = app(DailyReportService::class)->ciFreshnessNote($s);

        $overview = [
            '📊 **总览**',
            '活跃项目：'.($s['active_projects'] ?? 0),
            '代码规模：'.number_format(($s['total_loc'] ?? 0) / 10000, 1).' 万行',
            '近 7 天提交：'.($s['commits_7d'] ?? 0).' 次',
            '主分支 CI 红：'.($s['ci_red_projects'] ?? 0).' 个'.$freshness,
        ];
        if (($s['total_tasks'] ?? 0) > 0) {
            $overview[] = sprintf(
                '待办 %d | 进行中 %d | 已完成 %d | 延期 %d',
                $s['todo_tasks'] ?? 0,
                $s['in_progress_tasks'] ?? 0,
                $s['done_tasks'] ?? 0,
                $s['overdue_tasks'] ?? 0,
            );
        } else {
            // 与 Markdown 同款说法：明确「没有数据源」，不让人读成「全线停滞」
            $overview[] = '任务指标：未接入（无任务数据源）';
        }
        $overview[] = '开放风险：'.($s['open_risks'] ?? 0).'（严重 '.($s['critical_risks'] ?? 0).'）';

        $elements[] = ['tag' => 'markdown', 'content' => implode("\n", $overview)];

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
                // 有任务数据才谈完成率；没有就报代码规模与提交活跃度（同 Markdown 口径）
                if (! empty($p['has_task_data'])) {
                    $projLines[] = sprintf(
                        '%d. %s **%s** 进度 %.0f%%（完成 %d/%d）',
                        $idx++,
                        $statusEmoji,
                        $p['name'],
                        $p['completion_rate'] ?? 0,
                        $p['done'] ?? 0,
                        $p['total'] ?? 0,
                    );
                } else {
                    $locRaw = (int) ($p['code_loc'] ?? 0);
                    $loc = $locRaw > 0 ? number_format($locRaw / 10000, 1).' 万行' : '未采集代码';
                    $projLines[] = sprintf(
                        '%d. %s **%s** %s · 近 7 天 %d 次提交',
                        $idx++,
                        $statusEmoji,
                        $p['name'],
                        $loc,
                        (int) ($p['commits_7d'] ?? 0),
                    );
                }
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
