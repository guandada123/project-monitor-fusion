<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpsHealthScanCommand extends Command
{
    protected $signature = 'ops:health-scan';

    protected $description = '外部项目健康探测（原 Ops Center）';

    private array $targets = [
        'qts_engine' => ['host' => '127.0.0.1', 'port' => 8000, 'path' => '/health'],
        'qts_backtest' => ['host' => '127.0.0.1', 'port' => 8001, 'path' => '/health'],
        'qts_monitor' => ['host' => '127.0.0.1', 'port' => 8002, 'path' => '/health'],
    ];

    public function handle(): int
    {
        $results = [];

        // 1) HTTP 服务探测
        foreach ($this->targets as $name => $cfg) {
            $results[$name] = $this->httpProbe($cfg);
        }

        // 2) 进程探测（Marvis Bridge）
        $results['marvis_watcher'] = $this->pidProbe('file_watcher.sh');
        $results['marvis_consumer'] = $this->pidProbe('workbuddy_consumer');

        // 3) 仓库体积
        $results['repo_claw_size'] = $this->dirSize('/Users/guan/WorkBuddy/Claw');

        // 输出
        $this->table(
            ['Service', 'Status', 'Detail'],
            collect($results)->map(fn ($r, $k) => [$k, $r['status'], $r['detail']])->toArray()
        );

        Log::channel('daily')->info('OpsHealthScan', $results);

        return Command::SUCCESS;
    }

    private function httpProbe(array $cfg): array
    {
        try {
            $resp = Http::timeout(3)->get("http://{$cfg['host']}:{$cfg['port']}{$cfg['path']}");

            return ['status' => $resp->successful() ? 'UP' : 'DEGRADED', 'detail' => "HTTP {$resp->status()}"];
        } catch (\Throwable $e) {
            return ['status' => 'DOWN', 'detail' => $e->getMessage()];
        }
    }

    private function pidProbe(string $filter): array
    {
        $output = [];
        exec("pgrep -f {$filter} 2>/dev/null", $output, $exitCode);

        return $exitCode === 0
            ? ['status' => 'UP', 'detail' => 'PID: '.implode(',', $output)]
            : ['status' => 'DOWN', 'detail' => 'process not found'];
    }

    private function dirSize(string $path): array
    {
        $output = [];
        exec("du -sh {$path} 2>/dev/null", $output, $exitCode);

        return $exitCode === 0
            ? ['status' => 'OK', 'detail' => trim($output[0] ?? '')]
            : ['status' => 'ERR', 'detail' => 'path not accessible'];
    }
}
