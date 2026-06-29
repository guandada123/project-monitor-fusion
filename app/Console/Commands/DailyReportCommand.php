<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use App\Services\DailyReportService;
use App\Services\FeishuService;
use App\Services\RiskDetector;
use App\Services\RiskNotifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class DailyReportCommand extends Command
{
    protected $signature = 'daily:report
                            {--date= : 指定日期 Y-m-d（默认今天）}
                            {--no-push : 不推送到飞书，仅生成}
                            {--format=markdown : 输出格式 markdown|json}';

    protected $description = '生成项目日报并推送飞书';

    public function __construct(
        private readonly RiskDetector $detector,
        private readonly FeishuService $feishu,
        private readonly RiskNotifierService $riskNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->toDateString();

        $this->info("📊 生成项目日报 — {$date}");

        // 1. 先执行风险扫描
        $this->info('🔍 执行风险扫描...');
        $scanResult = $this->detector->scanAll();
        $this->line("  新增: {$scanResult['new']} | 更新: {$scanResult['updated']} | 已解决: {$scanResult['resolved']}");

        // 拉取通知目标（后续日报通知复用）
        $users = User::whereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'manager']))->get();

        // ── 站内通知：风险扫描结果 — 使用 RiskNotifierService ──
        if ($scanResult['new'] > 0) {
            $sent = $this->riskNotifier->notifyNewRisks($scanResult['new_risk_ids']);
            $this->line("  🔔 已发送 {$sent['risks_count']} 条风险通知给 {$sent['users_count']} 位管理员");
        }

        // 2. 生成日报
        $this->info('📝 生成日报内容...');
        $reportService = new DailyReportService($date);
        $report = $reportService->generate();

        $s = $report['summary'];
        $this->table(
            ['指标', '数值'],
            [
                ['活跃项目', $s['active_projects']],
                ['待办任务', $s['todo_tasks']],
                ['进行中', $s['in_progress_tasks']],
                ['已完成', $s['done_tasks']],
                ['延期任务', $s['overdue_tasks']],
                ['开放风险', $s['open_risks']],
                ['严重风险', $s['critical_risks']],
            ]
        );

        // 3. 项目详情
        $this->info('📋 各项目进度：');
        foreach ($report['projects'] as $p) {
            $bar = $this->output->createProgressBar(100);
            $emoji = ['active' => '🟢', 'pending' => '🔵', 'completed' => '✅', 'archived' => '⚫'][$p['status']] ?? '⚪';
            $this->line("  {$emoji} {$p['name']}: {$p['completion_rate']}% ({$p['done']}/{$p['total']})");
        }

        // 4. 风险预警
        if (! empty($report['risks'])) {
            $this->warn('⚠️ 风险预警：');
            foreach ($report['risks'] as $r) {
                $sev = ['critical' => '<error>严重</error>', 'high' => '<fg=red>高</>', 'medium' => '<fg=yellow>中</>', 'low' => '<fg=green>低</>'][$r['severity']] ?? '未知';
                $this->line("  {$sev} {$r['title']} — {$r['project_name']}");
            }
        }

        // ── 站内通知：日报推送 ──
        Notification::send($users, new DailyReportNotification($report['summary']));
        $this->info("🔔 日报通知已发送给 {$users->count()} 位管理员");

        // 5. 输出格式
        if ($this->option('format') === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $md = $reportService->toMarkdown($report);
            $this->line("\n".$md);
        }

        // 6. 推送到飞书
        if (! $this->option('no-push')) {
            if ($this->feishu->shouldSendDaily()) {
                $this->info('📤 推送飞书日报...');
                $card = $this->feishu->buildDailyCard($report);
                $result = $this->feishu->sendInteractiveCard($card);

                if ($result['ok']) {
                    $this->info('✅ 飞书推送成功');
                } else {
                    $this->error('❌ 飞书推送失败：'.($result['error'] ?? '未知错误'));
                    $this->line('  提示：请在系统设置中配置飞书 Webhook URL');
                }
            } else {
                $this->warn('⚠️ 飞书未配置或已禁用日报推送');
            }
        }

        $this->info('✅ 日报生成完成');

        return self::SUCCESS;
    }
}
