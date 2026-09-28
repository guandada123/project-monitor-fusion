<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Support\Ops\OpsProbe;
use Illuminate\Http\JsonResponse;

/**
 * OpsApiController — 原 ops-center (Flask) 监控能力迁移至 pmf
 *
 * 公开路由（无 auth:sanctum），供 public/ops/ 前端面板调用。
 *
 * 2026-09-28 重写：本控制器与 `OpsHealthScanCommand` 原先各自**抄了一份**写死
 * `127.0.0.1` + 宿主绝对路径的探测逻辑，而二者都跑在容器里 → 面板全瞎
 * （探测恒 unhealthy、体积恒 '0 MB'、state/cost/db 恒 not_found），
 * 且改一处不改另一处。现统一走 `App\Support\Ops\OpsProbe` + `config/ops.php`（单一真源）。
 *
 * 两处刻意的"不撒谎"：
 *   · 体积取不到 → `n/a`（而不是 `0 MB`）—— 旧实现把 0 喂给 sizeProgress，
 *     roadmap 直接显示**体积目标 100% 完成**，把"取不到证"渲染成了"很好"。
 *   · 探测入口本身不通 → 报 `unknown` + `probe_input` 说明，而不是把服务标成 unhealthy
 *     （那是"我连不出去"，不是"它挂了"）。
 */
class OpsApiController extends Controller
{
    /**
     * GET /api/ops/overview — 跨项目聚合状态
     */
    public function overview(): JsonResponse
    {
        $projects = [];
        $self = OpsProbe::selfCheck();

        // ── QTS ──
        $qtsServices = [];
        $qtsOk = 0;
        $qtsMisconfig = 0;
        foreach (OpsProbe::httpTargets() as $name => $cfg) {
            $r = OpsProbe::httpProbe((int) $cfg['port'], $cfg['path'] ?? '/health');
            $qtsServices[] = [
                'name' => $name,
                'port' => (int) $cfg['port'],
                'status' => match ($r['status']) {
                    'UP' => 'healthy',
                    'DEGRADED' => 'degraded',
                    'MISCONFIG' => 'unknown',
                    default => 'unhealthy',
                },
                'detail' => $r['detail'],
            ];
            if ($r['status'] === 'UP') {
                $qtsOk++;
            }
            if ($r['status'] === 'MISCONFIG') {
                $qtsMisconfig++;
            }
        }
        $qtsTotal = count($qtsServices);
        $qtsPath = OpsProbe::repoPath('qts');
        $projects['qts'] = [
            'name' => 'QuantTradingSystem',
            'services_healthy' => $qtsOk,
            'services_total' => $qtsTotal,
            'status' => match (true) {
                // 全都探不出去（探测入口不可用）→ unknown，**不是** unhealthy
                $qtsMisconfig > 0 && $qtsMisconfig === $qtsTotal => 'unknown',
                $qtsTotal === 0 => 'unknown',
                $qtsOk === $qtsTotal => 'healthy',
                $qtsOk > 0 => 'degraded',
                default => 'unhealthy',
            },
            'services' => $qtsServices,
            'size' => $qtsPath ? OpsProbe::dirSizeHuman($qtsPath) : 'n/a',
        ];

        // ── Claw ──
        $clawPath = OpsProbe::repoPath('claw');
        $clawVisible = (bool) ($clawPath && is_dir($clawPath));
        $clawScripts = $clawVisible && is_dir($clawPath.'/scripts')
            ? max(0, count(scandir($clawPath.'/scripts')) - 2) : null;
        $projects['claw'] = [
            'name' => 'Claw',
            'status' => $clawVisible ? 'healthy' : 'unknown',
            'scripts' => $clawScripts,
            'note' => $clawVisible ? null : '宿主目录未挂载（见 docker-compose /host/*）',
            'size' => $clawPath ? OpsProbe::dirSizeHuman($clawPath) : 'n/a',
        ];

        // ── StockInsight ──
        $siPath = OpsProbe::repoPath('stockinsight');
        $projects['stockinsight'] = [
            'name' => 'StockInsight',
            'services_healthy' => 0,
            'services_total' => 0,
            'status' => $siPath && is_dir($siPath) ? 'healthy' : 'unknown',
            'services' => [],
            'size' => $siPath ? OpsProbe::dirSizeHuman($siPath) : 'n/a',
            'note' => $siPath && is_dir($siPath) ? null : '宿主目录未挂载（见 docker-compose /host/*）',
        ];

        // ── Marvis Bridge ──
        // 不再做 `pgrep` 宿主进程探测：容器 PID namespace 隔离看不到宿主进程（结构上永远 DOWN），
        // 且目标本身已休眠（宿主实测 pgrep 为空）。要监控宿主进程请放宿主侧。
        $marvisPath = OpsProbe::repoPath('marvis');
        $marvisVisible = (bool) ($marvisPath && is_dir($marvisPath));
        $projects['marvis'] = [
            'name' => 'Marvis Bridge',
            'status' => 'n/a',
            'note' => $marvisVisible
                ? '宿主进程探测不在容器内可得（目标已休眠）；仅报体积'
                : '宿主目录未挂载',
            'size' => $marvisPath ? OpsProbe::dirSizeHuman($marvisPath) : 'n/a',
        ];

        return ApiResponse::success([
            'timestamp' => now()->toIso8601String(),
            'date' => now()->toDateString(),
            'probe_input' => $self,     // 探测输入自证（前端可用来区分"服务挂"与"我连不出去"）
            'projects' => $projects,
        ]);
    }

    /**
     * GET /api/ops/automations — 自动化注册表概览
     */
    public function automations(): JsonResponse
    {
        return ApiResponse::success($this->getDbAutomations());
    }

    /**
     * GET /api/ops/cost — AI 成本与缓存统计
     */
    public function cost(): JsonResponse
    {
        $cost = $this->getCostData();
        $monthly = $cost ? $cost['total_cost'] : null;

        return ApiResponse::success([
            'monthly_estimate' => $monthly,
            'target' => 30,
            'details' => $cost,
            'note' => $cost ? null : '成本日志不可读（未挂载？见 docker-compose /host/*）',
        ]);
    }

    /**
     * GET /api/ops/roadmap — Q3 2026 Roadmap 进度
     */
    public function roadmap(): JsonResponse
    {
        $cost = $this->getCostData();
        $currentCost = $cost ? $cost['total_cost'] : null;
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

        $qtsMb = ($p = OpsProbe::repoPath('qts')) ? OpsProbe::dirSizeMb($p) : null;
        $siMb = ($p = OpsProbe::repoPath('stockinsight')) ? OpsProbe::dirSizeMb($p) : null;

        return ApiResponse::success([
            'period' => '2026-07 ~ 2026-09',
            'goals' => [
                [
                    'name' => '成本控制',
                    'target' => '¥30/月',
                    'current' => $currentCost === null ? 'n/a' : '¥'.number_format($currentCost, 1).'/月',
                    'progress' => $currentCost === null
                        ? null : ($currentCost <= 30 ? min(100 - intval($currentCost / 30 * 100), 100) : 20),
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
                    'current' => $qtsMb === null ? 'n/a' : OpsProbe::human($qtsMb),
                    // 取不到体积 → progress = null（未知），**不是 100**
                    'progress' => $this->sizeProgress($qtsMb, 700),
                ],
                [
                    'name' => 'StockInsight 体积',
                    'target' => '≤ 1.2GB',
                    'current' => $siMb === null ? 'n/a' : OpsProbe::human($siMb),
                    'progress' => $this->sizeProgress($siMb, 1228.8),
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
            'version' => '1.1.0',
            'time' => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/ops/state — 全局状态锚(cross_project_state.json)展示
     * 统一监控入口：跨项目 todo/backoff/evidence 一览
     */
    public function state(): JsonResponse
    {
        $path = (string) OpsProbe::path('state_anchor');
        if (! $path || ! file_exists($path)) {
            return ApiResponse::success([
                'error' => 'state_anchor_not_found',
                'path' => $path,
                'note' => '状态锚不可读（未挂载？见 docker-compose /host/*）',
            ]);
        }
        $data = json_decode((string) file_get_contents($path), true);
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

    private function getDbAutomations(): array
    {
        // 只读挂载的库是 WAL 模式、打不开 → 走「快照副本」（见 OpsProbe::workbuddyDbSnapshot）
        $db = OpsProbe::workbuddyDbSnapshot();
        if (! $db) {
            return ['count' => 0, 'active' => 0, 'paused' => 0, 'deleted' => 0,
                'error' => 'db_unavailable', 'path' => OpsProbe::path('workbuddy_db')];
        }
        try {
            $pdo = new \PDO('sqlite:'.$db);
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

            return [
                'count' => $count, 'active' => $active, 'paused' => $paused, 'deleted' => $deleted,
                // 如实标注数据来源与快照时刻（不是实时值，别让读者以为这是当前值）
                'source' => 'snapshot',
                'snapshot_at' => date(DATE_ATOM, (int) filemtime($db)),
            ];
        } catch (\Throwable $e) {
            return ['count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function getCostData(): ?array
    {
        $log = (string) OpsProbe::path('ai_cost_log');
        if (! $log || ! file_exists($log)) {
            return null;
        }
        $thisMonth = now()->format('Y-m');
        $totalCost = 0.0;
        $totalInput = 0;
        $totalOutput = 0;
        $recordCount = 0;
        $fh = fopen($log, 'r');
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

    /**
     * 体积目标进度。**取不到体积（null）就返回 null（未知）**。
     *
     * 旧实现接收 "0 MB" 这类字符串，解析不出时 `return 100` —— 于是"路径不可达"
     * 被渲染成"体积目标 100% 完成"。取不到证 ≠ 达标。
     */
    private function sizeProgress(?int $mb, float $targetMb): ?int
    {
        if ($mb === null) {
            return null;
        }
        if ($mb <= 0) {
            return 100;
        }

        return max(0, min(100, 100 - intval($mb / $targetMb * 100)));
    }
}
