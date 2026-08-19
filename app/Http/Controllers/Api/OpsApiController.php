<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

/**
 * OpsApiController — 原 ops-center (Flask) 监控能力迁移至 pmf
 *
 * 公开路由（无 auth:sanctum），供 public/ops/ 前端面板调用。
 * 数据来源与 ops-center/app.py 一致：
 *   - overview: HTTP probe 各项目服务端口 + 目录体积
 *   - automations: ~/.workbuddy/workbuddy.db (SQLite)
 *   - cost: ~/.ai_cost_log.jsonl 当月汇总
 *   - roadmap: 组合 cost + automations + dir_size 进度计算
 */
class OpsApiController extends Controller
{
    private const WORKBUDDY_DB = '/Users/guan/.workbuddy/workbuddy.db';

    private const AI_COST_LOG = '/Users/guan/.ai_cost_log.jsonl';

    private array $projects = [
        'qts' => [
            'name' => 'QuantTradingSystem',
            'path' => '/Volumes/ZHITAI/WorkBuddy/QuantTradingSystem',
            'services' => [
                ['name' => 'qts_engine', 'port' => 8000, 'path' => '/health'],
                ['name' => 'qts_backtest', 'port' => 8001, 'path' => '/health'],
                ['name' => 'qts_monitor', 'port' => 8002, 'path' => '/health'],
            ],
        ],
        'claw' => [
            'name' => 'Claw',
            'path' => '/Volumes/ZHITAI/WorkBuddy/Claw',
        ],
        'stockinsight' => [
            'name' => 'StockInsight',
            'path' => '/Volumes/ZHITAI/WorkBuddy/StockInsight',
            'services' => [],
        ],
        'marvis' => [
            'name' => 'Marvis Bridge',
            'path' => '/Volumes/ZHITAI/WorkBuddy/marvis_bridge',
        ],
    ];

    /**
     * GET /api/ops/overview — 跨项目聚合状态
     */
    public function overview(): JsonResponse
    {
        $projects = [];

        // QTS
        $qtsServices = [];
        $qtsOk = 0;
        foreach ($this->projects['qts']['services'] as $svc) {
            $ok = $this->httpProbe($svc['port'], $svc['path']);
            $qtsServices[] = [
                'name' => $svc['name'],
                'port' => $svc['port'],
                'status' => $ok ? 'healthy' : 'unhealthy',
            ];
            if ($ok) {
                $qtsOk++;
            }
        }
        $qtsTotal = count($qtsServices);
        $projects['qts'] = [
            'name' => 'QuantTradingSystem',
            'services_healthy' => $qtsOk,
            'services_total' => $qtsTotal,
            'status' => $qtsTotal === 0 ? 'unknown' : ($qtsOk === $qtsTotal ? 'healthy' : ($qtsOk > 0 ? 'degraded' : 'unhealthy')),
            'services' => $qtsServices,
            'size' => $this->dirSize($this->projects['qts']['path']),
        ];

        // Claw
        $clawScripts = is_dir($this->projects['claw']['path'].'/scripts')
            ? count(scandir($this->projects['claw']['path'].'/scripts')) - 2 : 0;
        $projects['claw'] = [
            'name' => 'Claw',
            'status' => 'healthy',
            'scripts' => $clawScripts,
            'size' => $this->dirSize($this->projects['claw']['path']),
        ];

        // StockInsight
        $siServices = [];
        $siOk = 0;
        foreach ($this->projects['stockinsight']['services'] as $svc) {
            $ok = $this->httpProbe($svc['port'], $svc['path']);
            $siServices[] = [
                'name' => $svc['name'],
                'port' => $svc['port'],
                'status' => $ok ? 'healthy' : 'unhealthy',
            ];
            if ($ok) {
                $siOk++;
            }
        }
        $projects['stockinsight'] = [
            'name' => 'StockInsight',
            'services_healthy' => $siOk,
            'services_total' => count($siServices),
            'status' => $siOk > 0 ? 'healthy' : 'unhealthy',
            'services' => $siServices,
            'size' => $this->dirSize($this->projects['stockinsight']['path']),
        ];

        // Marvis Bridge
        $watcherOk = $this->pidRunning('file_watcher.sh');
        $consumerOk = $this->pidRunning('workbuddy_consumer');
        $marvisStatus = ($watcherOk || $consumerOk) ? ($watcherOk && $consumerOk ? 'healthy' : 'degraded') : 'unhealthy';
        $projects['marvis'] = [
            'name' => 'Marvis Bridge',
            'status' => $marvisStatus,
            'watcher_running' => $watcherOk,
            'consumer_running' => $consumerOk,
            'size' => $this->dirSize($this->projects['marvis']['path']),
        ];

        return ApiResponse::success([
            'timestamp' => now()->toIso8601String(),
            'date' => now()->toDateString(),
            'projects' => $projects,
        ]);
    }

    /**
     * GET /api/ops/automations — 自动化注册表概览
     */
    public function automations(): JsonResponse
    {
        $data = $this->getDbAutomations();

        return ApiResponse::success($data);
    }

    /**
     * GET /api/ops/cost — AI 成本与缓存统计
     */
    public function cost(): JsonResponse
    {
        $cost = $this->getCostData();
        $monthly = $cost ? $cost['total_cost'] : 0;

        return ApiResponse::success([
            'monthly_estimate' => $monthly,
            'target' => 30,
            'details' => $cost,
        ]);
    }

    /**
     * GET /api/ops/roadmap — Q3 2026 Roadmap 进度
     */
    public function roadmap(): JsonResponse
    {
        $cost = $this->getCostData();
        $currentCost = $cost ? $cost['total_cost'] : 0;
        $automations = $this->getDbAutomations();
        $autoCount = $automations['count'] ?? 0;

        $autoProgress = match (true) {
            $autoCount <= 0 => 95,
            $autoCount <= 15 => 80,
            $autoCount <= 25 => 60,
            $autoCount <= 35 => 40,
            $autoCount <= 45 => 25,
            default => 10,
        };

        $qtsSize = $this->dirSize($this->projects['qts']['path']);
        $siSize = $this->dirSize($this->projects['stockinsight']['path']);

        return ApiResponse::success([
            'period' => '2026-07 ~ 2026-09',
            'goals' => [
                [
                    'name' => '成本控制',
                    'target' => '¥30/月',
                    'current' => '¥'.number_format($currentCost, 1).'/月',
                    'progress' => $currentCost <= 30 ? min(100 - intval($currentCost / 30 * 100), 100) : 20,
                ],
                [
                    'name' => '自动化活跃数',
                    'target' => '≤ 35',
                    'current' => (string) $autoCount,
                    'progress' => $autoProgress,
                ],
                [
                    'name' => 'CI 通过率',
                    'target' => '100%',
                    'current' => '95%',
                    'progress' => 80,
                ],
                [
                    'name' => 'QTS 仓库体积',
                    'target' => '≤ 700MB',
                    'current' => $qtsSize,
                    'progress' => $this->sizeProgress($qtsSize, 700),
                ],
                [
                    'name' => 'StockInsight 体积',
                    'target' => '≤ 1.2GB',
                    'current' => $siSize,
                    'progress' => $this->sizeProgress($siSize, 1228.8),
                ],
            ],
        ]);
    }

    /**
     * GET /api/ops/health — ops 模块健康
     */
    public function health(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'healthy',
            'service' => 'ops-api',
            'version' => '1.0.0',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/ops/state — 全局状态锚(cross_project_state.json)展示
     * 统一监控入口：跨项目 todo/backoff/evidence 一览
     */
    public function state(): JsonResponse
    {
        $path = '/Users/guan/.workbuddy/cross_project_state.json';
        if (! file_exists($path)) {
            return ApiResponse::success(['error' => 'state_anchor_not_found']);
        }
        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (! $data) {
            return ApiResponse::success(['error' => 'state_anchor_parse_failed']);
        }

        // 只暴露安全字段（不泄露 absolute path 等敏感元数据）
        return ApiResponse::success([
            'updated_at' => $data['updated_at'] ?? null,
            'north_star' => $data['vision']['north_star'] ?? null,
            'active_projects' => $data['active_projects'] ?? [],
            'todo_frontier' => $data['todo_frontier'] ?? [],
            'backoff_state' => $data['backoff_state'] ?? [],
            'evidence_log' => array_slice($data['evidence_log'] ?? [], -10),
        ]);
    }

    // ── 私有辅助 ──

    private function httpProbe(int $port, string $path): bool
    {
        try {
            $resp = Http::timeout(3)->get("http://127.0.0.1:{$port}{$path}");

            return $resp->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function pidRunning(string $filter): bool
    {
        $output = shell_exec('pgrep -f '.escapeshellarg($filter).' 2>/dev/null');

        return ! empty(trim($output ?? ''));
    }

    private function dirSize(string $path): string
    {
        if (! is_dir($path)) {
            return '0 MB';
        }
        $output = shell_exec('du -sm '.escapeshellarg($path).' 2>/dev/null');
        if (preg_match('/^\s*(\d+)/', (string) $output, $m)) {
            $mb = (int) $m[1];
            if ($mb >= 1024) {
                return round($mb / 1024, 1).' GB';
            }

            return $mb.' MB';
        }

        return '0 MB';
    }

    private function getDbAutomations(): array
    {
        if (! file_exists(self::WORKBUDDY_DB)) {
            return ['count' => 0, 'active' => 0, 'paused' => 0, 'deleted' => 0, 'error' => 'db_not_found'];
        }
        try {
            $pdo = new \PDO('sqlite:'.self::WORKBUDDY_DB);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM automations GROUP BY status')->fetchAll(\PDO::FETCH_ASSOC);
            $count = $active = $paused = $deleted = 0;
            foreach ($rows as $r) {
                $count += (int) $r['c'];
                if ($r['status'] === 'ACTIVE') {
                    $active = (int) $r['c'];
                } elseif ($r['status'] === 'PAUSED') {
                    $paused = (int) $r['c'];
                } else {
                    $deleted += (int) $r['c'];
                }
            }

            return ['count' => $count, 'active' => $active, 'paused' => $paused, 'deleted' => $deleted];
        } catch (\Throwable $e) {
            return ['count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function getCostData(): ?array
    {
        if (! file_exists(self::AI_COST_LOG)) {
            return null;
        }
        $thisMonth = now()->format('Y-m');
        $totalCost = 0.0;
        $totalInput = 0;
        $totalOutput = 0;
        $recordCount = 0;
        $fh = fopen(self::AI_COST_LOG, 'r');
        if (! $fh) {
            return null;
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if (! $line) {
                continue;
            }
            try {
                $rec = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                continue;
            }
            if (! isset($rec['date']) || ! str_starts_with($rec['date'], $thisMonth)) {
                continue;
            }
            $totalCost += (float) ($rec['cost_cny'] ?? 0);
            $totalInput += (int) ($rec['input'] ?? 0);
            $totalOutput += (int) ($rec['output'] ?? 0);
            $recordCount++;
        }
        fclose($fh);

        return [
            'total_records' => $recordCount,
            'total_tokens' => $totalInput + $totalOutput,
            'total_cost' => round($totalCost, 4),
        ];
    }

    private function sizeProgress(string $sizeStr, float $targetMb): int
    {
        // 解析 "X MB" / "X.X GB"
        if (preg_match('/([\d.]+)\s*GB/i', $sizeStr, $m)) {
            $mb = (float) $m[1] * 1024;
        } elseif (preg_match('/(\d+)\s*MB/i', $sizeStr, $m)) {
            $mb = (float) $m[1];
        } else {
            return 100;
        }
        if ($mb <= 0) {
            return 100;
        }
        $progress = 100 - intval($mb / $targetMb * 100);

        return max(0, min(100, $progress));
    }
}
