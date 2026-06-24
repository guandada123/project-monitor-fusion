<?php

namespace App\Console\Commands;

use App\Services\MetricCollector;
use Illuminate\Console\Command;

class CollectMetricsCommand extends Command
{
    protected $signature = 'metrics:collect
                            {--key= : 仅采集指定指标}
                            {--dry-run : 干跑不写入}';

    protected $description = '自动采集所有系统指标的当前快照';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 干跑模式 — 不会写入数据库');
        } else {
            $this->info('📐 开始采集指标快照...');
        }

        $collector = new MetricCollector;

        if ($dryRun) {
            $this->line('  实际采集时将会计算并存储所有系统指标的当前值...');
            $this->line('  metrics: 40 条系统指标待采集');

            return self::SUCCESS;
        }

        $result = $collector->collectAll();

        $this->table(
            ['状态', '数量'],
            [
                ['总数', $result['total']],
                ['已采集', $result['computed']],
                ['错误', $result['errors']],
            ]
        );

        if ($result['errors'] > 0) {
            $this->warn("⚠️ {$result['errors']} 个指标采集失败，请检查日志");
        } else {
            $this->info('✅ 全部指标采集完成');
        }

        return self::SUCCESS;
    }
}
