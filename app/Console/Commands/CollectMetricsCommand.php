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

    public function __construct(
        private readonly MetricCollector $collector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $key = $this->option('key');

        if ($dryRun) {
            $this->warn('🔍 干跑模式 — 不会写入数据库');

            if ($key) {
                $this->line("  将采集指标: {$key}");
            } else {
                $count = \App\Models\Metric::system()->count();
                $this->line("  metrics: {$count} 条系统指标待采集");
            }

            return self::SUCCESS;
        }

        $this->info('📐 开始采集指标快照...');

        $result = $key
            ? $this->collector->collectOne($key)
            : $this->collector->collectAll();

        // 指标不存在
        if (isset($result['message'])) {
            $this->error($result['message']);
            return self::FAILURE;
        }

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

            return self::FAILURE;
        }

        $this->info('✅ 全部指标采集完成');

        return self::SUCCESS;
    }
}
