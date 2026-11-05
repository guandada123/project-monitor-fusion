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
        // 2026-10-31：tasks/risks 无数据时那四项恒为 0，直接列出来只会显示一片 0。
        // 有任务数据才展示任务口径，否则展示采集快照的真实指标。
        $rows = [
            ['活跃项目', $s['active_projects']],
            ['代码规模', number_format(($s['total_loc'] ?? 0) / 10000, 1).' 万行'],
            ['近 7 天提交', ($s['commits_7d'] ?? 0).' 次'],
            ['主分支 CI 红', ($s['ci_red_projects'] ?? 0).' 个项目'.$reportService->ciFreshnessNote($s)],
        ];
        if (($s['total_tasks'] ?? 0) > 0) {
            $rows[] = ['待办任务', $s['todo_tasks']];
            $rows[] = ['进行中', $s['in_progress_tasks']];
            $rows[] = ['已完成', $s['done_tasks']];
            $rows[] = ['延期任务', $s['overdue_tasks']];
        } else {
            $rows[] = ['任务指标', '未接入（无任务数据源）'];
        }
        $rows[] = ['开放风险', $s['open_risks']];
        $rows[] = ['严重风险', $s['critical_risks']];

        $this->table(['指标', '数值'], $rows);

        // 3. 项目详情
        $this->info('📋 各项目进度：');
        foreach ($report['projects'] as $p) {
            $emoji = ['active' => '🟢', 'pending' => '🔵', 'completed' => '✅', 'archived' => '⚫'][$p['status']] ?? '⚪';
            if (! empty($p['has_task_data'])) {
                $this->line("  {$emoji} {$p['name']}: {$p['completion_rate']}% ({$p['done']}/{$p['total']})");
            } else {
                $locRaw = (int) ($p['code_loc'] ?? 0);
                $loc = $locRaw > 0 ? number_format($locRaw / 10000, 1).' 万行' : '未采集代码';
                $this->line("  {$emoji} {$p['name']}: {$loc} · 近 7 天 {$p['commits_7d']} 次提交");
            }
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
        $pushFailed = false;
        if (! $this->option('no-push')) {
            if ($this->feishu->shouldSendDaily()) {
                $this->info('📤 推送飞书日报...');
                $card = $this->feishu->buildDailyCard($report);
                $result = $this->feishu->sendInteractiveCard($card);

                if ($result['ok']) {
                    $this->info('✅ 飞书推送成功');
                } else {
                    $pushFailed = true;
                    $error = (string) ($result['error'] ?? '未知错误');
                    $this->error('❌ 飞书推送失败：'.$error);
                    // 2026-11-05 08:30 真实事故：飞书应用侧限流（frequency limited），
                    // 旧代码却一律提示「请配置 Webhook URL」，把可恢复的限流误导成配置缺失；
                    // 且失败后照样打印 ✅ 并退出 0 —— 调度器永远看不到「日报没送到」。
                    $this->line('  '.$this->feishuFailureHint($error, $date));
                }
            } else {
                $this->warn('⚠️ 飞书未配置或已禁用日报推送');
            }
        }

        if ($pushFailed) {
            $this->warn('⚠️ 日报已生成，但飞书推送失败（见上方错误）—— 本次按失败退出');

            return self::FAILURE;
        }

        $this->info('✅ 日报生成完成');

        return self::SUCCESS;
    }

    /**
     * 按真实失败原因给可操作的提示，不给「万能模板」。
     */
    private function feishuFailureHint(string $error, string $date): string
    {
        $lower = mb_strtolower($error);

        if (str_contains($lower, 'frequency limited')
            || str_contains($lower, 'rate limit')
            || str_contains($lower, 'too many request')
            || str_contains($lower, 'http 429')) {
            return '原因：飞书应用侧限流（非配置问题，Webhook 已配置）。稍后可补发：php artisan daily:report --date='.$date;
        }

        if (str_contains($lower, '未配置') || str_contains($lower, 'webhook')) {
            return '原因：Webhook 未配置或地址无效 —— 请在系统设置中配置飞书 Webhook URL';
        }

        return '原因：请求未能送达（网络 / HTTP / 飞书返回错误）—— 详见 storage/logs/laravel.log 的 Feishu webhook 记录';
    }
}
