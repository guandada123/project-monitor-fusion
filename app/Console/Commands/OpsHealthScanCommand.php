<?php

namespace App\Console\Commands;

use App\Support\Ops\OpsProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 外部项目健康探测（原 Ops Center）。
 *
 * 2026-09-28 重写：原实现把 `127.0.0.1` 与宿主绝对路径写死，而本命令跑在容器里 →
 * **6/6 检查结构性恒失败**（ops-health.log 13 万行全是失败，从建成起就没查过任何东西）。
 * 现改为：探测走 config('ops.probe_host')、宿主数据走只读挂载、路径全部可 env 覆盖。
 */
class OpsHealthScanCommand extends Command
{
    protected $signature = 'ops:health-scan';

    protected $description = '外部项目健康探测（原 Ops Center）';

    public function handle(): int
    {
        $results = [];

        // 0) 输入自证 —— 先答"我的输入还在不在"。
        //    否则下面报出来的 DOWN 会把人引去查服务，而真因是"探测入口/挂载没配"。
        $self = OpsProbe::selfCheck();
        $inputOk = $self['probe_host_resolvable'] && empty($self['missing_inputs']);
        $results['probe_input'] = [
            'status' => $inputOk ? 'OK' : 'MISCONFIG',
            'detail' => sprintf(
                'probe_host=%s resolvable=%s missing=%s',
                $self['probe_host'],
                $self['probe_host_resolvable'] ? 'yes' : 'no',
                $self['missing_inputs'] ? implode(',', $self['missing_inputs']) : '-'
            ),
        ];

        // 1) HTTP 服务探测（目标见 config/ops.php）
        foreach (OpsProbe::httpTargets() as $name => $cfg) {
            $results[$name] = OpsProbe::httpProbe((int) $cfg['port'], $cfg['path'] ?? '/health');
        }

        // 2) 仓库体积（宿主目录只读挂载；取不到 → N/A，**不谎报 0**）
        foreach (['claw', 'qts', 'stockinsight'] as $key) {
            $path = OpsProbe::repoPath($key);
            $mb = $path ? OpsProbe::dirSizeMb($path) : null;
            $results["repo_{$key}_size"] = $mb === null
                ? ['status' => 'N/A', 'detail' => "路径不可达: {$path}（未挂载？见 compose 的 /host/*）"]
                : ['status' => 'OK', 'detail' => OpsProbe::human($mb)];
        }

        // 说明：原先还有 marvis_watcher / marvis_consumer 两个 `pgrep` 探测，已移除 ——
        //   ① 容器 PID namespace 隔离，pgrep **看不到宿主进程** → 在本部署下结构上永远 DOWN；
        //   ② 目标本身已休眠：宿主实测 `pgrep -fl "file_watcher.sh|workbuddy_consumer"` 为空。
        //   要监控宿主进程应放在**宿主侧**（统一巡检中枢 / launchd），而不是容器里。

        // 输出
        $this->table(
            ['Service', 'Status', 'Detail'],
            collect($results)->map(fn ($r, $k) => [$k, $r['status'], $r['detail']])->toArray()
        );

        Log::channel('daily')->info('OpsHealthScan', $results);

        return Command::SUCCESS;
    }
}
