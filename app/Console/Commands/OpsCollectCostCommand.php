<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OpsCollectCostCommand extends Command
{
    protected $signature = 'ops:collect-cost';

    protected $description = 'AI 成本采集（原 Ops Center）';

    private string $costLogPath = '/Users/guan/.ai_cost_log.jsonl';

    private float $monthlyTarget = 30.0; // 月成本目标 ¥30

    public function handle(): int
    {
        if (! file_exists($this->costLogPath)) {
            $this->warn("成本日志不存在: {$this->costLogPath}");

            return Command::SUCCESS;
        }

        $currentMonth = date('Y-m');
        $totalTokens = 0;
        $totalCost = 0.0;
        $recordCount = 0;

        $handle = fopen($this->costLogPath, 'r');
        while (($line = fgets($handle)) !== false) {
            $entry = json_decode($line, true);
            if (! $entry || ! isset($entry['timestamp'])) {
                continue;
            }
            if (str_starts_with($entry['timestamp'], $currentMonth)) {
                $totalTokens += $entry['total_tokens'] ?? 0;
                $totalCost += $entry['total_cost'] ?? 0;
                $recordCount++;
            }
        }
        fclose($handle);

        $progress = $this->monthlyTarget > 0
            ? round(($totalCost / $this->monthlyTarget) * 100, 1)
            : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Month', $currentMonth],
                ['Total Cost (¥)', number_format($totalCost, 2)],
                ['Progress (%)', $progress],
                ['Total Tokens', number_format($totalTokens)],
                ['Records', $recordCount],
            ]
        );

        Log::channel('daily')->info('OpsCostCollect', [
            'month' => $currentMonth,
            'cost' => $totalCost,
            'progress' => $progress,
            'tokens' => $totalTokens,
            'records' => $recordCount,
        ]);

        return Command::SUCCESS;
    }
}
