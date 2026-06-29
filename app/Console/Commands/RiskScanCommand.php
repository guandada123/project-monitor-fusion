<?php

namespace App\Console\Commands;

use App\Services\FeishuService;
use App\Services\RiskDetector;
use App\Services\RiskNotifierService;
use Illuminate\Console\Command;

class RiskScanCommand extends Command
{
    protected $signature = 'risk:scan
                            {--no-push : 不推送飞书告警}
                            {--no-notify : 不发送站内通知}
                            {--severity= : 仅报告指定严重级别以上的风险 (critical|high|medium|low)}';

    protected $description = '自动扫描项目风险并可选推送飞书告警';

    public function __construct(
        private readonly RiskDetector $detector,
        private readonly FeishuService $feishu,
        private readonly RiskNotifierService $riskNotifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 扫描项目风险...');

        $result = $this->detector->scanAll();

        $this->table(
            ['统计', '数量'],
            [
                ['新增风险', $result['new']],
                ['更新风险', $result['updated']],
                ['已解决', $result['resolved']],
            ]
        );

        // 按规则分类展示
        if (! empty($result['by_rule'])) {
            $this->info('按规则分类：');
            foreach ($result['by_rule'] as $rule => $count) {
                $title = RiskDetector::RULES[$rule]['title'] ?? $rule;
                $this->line("  {$title}: {$count} 条");
            }
        }

        // ── 站内通知 ──
        if (! $this->option('no-notify') && $result['new'] > 0) {
            $this->info('🔔 发送站内通知...');
            $sent = $this->riskNotifier->notifyNewRisks($result['new_risk_ids']);
            $this->line("  ✅ 已发送 {$sent['risks_count']} 条通知给 {$sent['users_count']} 位管理员");
        }

        // 推送飞书严重风险告警
        if (! $this->option('no-push')) {
            $criticalRisks = $this->detector->getCriticalRisks();
            if (! empty($criticalRisks)) {
                if ($this->feishu->shouldSendRiskAlert()) {
                    $this->info('📤 推送风险告警...');
                    foreach ($criticalRisks as $risk) {
                        $card = $this->feishu->buildRiskAlertCard($risk);
                        $pushResult = $this->feishu->sendInteractiveCard($card);
                        $status = $pushResult['ok'] ? '✅' : '❌';
                        $this->line("  {$status} {$risk['title']}");
                    }
                } else {
                    $this->warn('⚠️ 飞书未配置或已禁用风险告警');
                }
            } else {
                $this->info('✅ 暂无严重/高风险');
            }
        }

        $this->info('✅ 风险扫描完成');

        return self::SUCCESS;
    }
}
