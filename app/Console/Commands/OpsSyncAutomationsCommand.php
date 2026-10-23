<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class OpsSyncAutomationsCommand extends Command
{
    protected $signature = 'ops:sync-automations';

    protected $description = 'WorkBuddy 自动化注册同步（原 Ops Center）';

    private string $dbPath = '/Users/guan/.workbuddy/workbuddy.db';

    public function handle(): int
    {
        if (! file_exists($this->dbPath)) {
            $this->warn("WorkBuddy DB 不存在: {$this->dbPath}");

            return Command::SUCCESS;
        }

        try {
            $pdo = new \PDO("sqlite:{$this->dbPath}");
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $total = $pdo->query('SELECT COUNT(*) FROM automations')->fetchColumn();
            $active = $pdo->query("SELECT COUNT(*) FROM automations WHERE status = 'ACTIVE'")->fetchColumn();
            $paused = $pdo->query("SELECT COUNT(*) FROM automations WHERE status = 'PAUSED'")->fetchColumn();
            $latestRun = $pdo->query('SELECT MAX(created_at) FROM automation_runs')->fetchColumn();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Automations', $total],
                    ['Active', $active],
                    ['Paused', $paused],
                    ['Latest Run', $latestRun ?? 'N/A'],
                ]
            );

            Log::channel('daily')->info('OpsAutomationSync', [
                'total' => $total,
                'active' => $active,
                'paused' => $paused,
                'latest_run' => $latestRun,
            ]);
        } catch (\Throwable $e) {
            $this->error("DB 读取失败: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
