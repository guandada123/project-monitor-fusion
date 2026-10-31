<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;

/**
 * 把 Claw 采集的 ops_snapshot.json 同步进 projects 表。
 *
 * 数据流：Claw(.workbuddy/scripts/ops_workbench_collect.py，跑在宿主)
 *        → 落盘 ops_snapshot.json（只读挂载进容器 /host/WorkBuddy/...）
 *        → 本命令 upsert 进 pmf 的 projects 表 → 各页面消费。
 *
 * 为什么这样做而不是让 pmf 自己探测：pmf 跑在容器内，PID namespace 隔离
 * 看不到宿主进程，也无法执行宿主的 git/gh；采集必须由宿主侧的 Claw 完成。
 * 详见《工作台合并可行性评估-20261031.md》§2.2。
 *
 * 幂等键：source='ops_snapshot' + external_id=repo。重复执行只更新不新增。
 */
class OpsImportSnapshotCommand extends Command
{
    protected $signature = 'ops:import-snapshot
                            {--dry-run : 只解析并报告，不写库}
                            {--stale-hours=6 : 快照超过该小时数视为陈旧（仍导入，但告警）}';

    protected $description = '把 Claw 采集的 ops_snapshot.json 同步进 projects 表';

    public function handle(): int
    {
        $path = (string) config('ops.paths.ops_snapshot', '/host/WorkBuddy/Claw/.workbuddy/data/ops_workbench/ops_snapshot.json');

        if (! is_file($path)) {
            $this->error("快照不存在：{$path}");
            $this->line('  请先在宿主侧执行：python3 Claw/.workbuddy/scripts/ops_workbench_collect.py');

            return self::FAILURE;
        }

        $snap = json_decode((string) file_get_contents($path), true);
        if (! is_array($snap) || ! isset($snap['projects']['items'])) {
            $this->error('快照 JSON 无效或缺少 projects.items');

            return self::FAILURE;
        }

        $generatedAt = $snap['generated_at'] ?? null;
        $staleHours = (int) $this->option('stale-hours');
        $ageHours = null;

        if ($generatedAt) {
            $ts = strtotime($generatedAt);
            if ($ts !== false) {
                $ageHours = round((time() - $ts) / 3600, 1);
            }
        }

        $this->info("📦 快照：{$path}");
        $this->line('  生成于：'.($generatedAt ?? '未知').($ageHours !== null ? "（{$ageHours} 小时前）" : ''));

        if ($ageHours !== null && $ageHours > $staleHours) {
            // 不阻断：陈旧数据仍比没有强，但必须让人看见"这是旧数据"
            $this->warn("⚠️  快照已陈旧 {$ageHours}h（阈值 {$staleHours}h）—— 页面数据可能不是最新");
        }

        if ($this->option('dry-run')) {
            $this->line('  DRY-RUN：将处理 '.count($snap['projects']['items']).' 个仓库');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($snap['projects']['items'] as $it) {
            $repo = $it['repo'] ?? null;
            if (! $repo) {
                $skipped++;

                continue;
            }

            // slug 由 repo 派生，不用 project 名：wechat_api 与 wechat-download-api
            // 的 project 名相同，用 project 名会撞 projects_slug_unique。
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $repo), '-'));

            $attrs = [
                'name' => $it['project'] ?? $repo,
                'slug' => $slug,
                'status' => 'active',
                'description' => $it['role'] ?? null,
                'meta' => $it,
                'tags' => array_filter([$it['priority'] ?? null]),
                'config' => ['snapshot_generated_at' => $generatedAt],
            ];

            $existing = Project::where('source', 'ops_snapshot')
                ->where('external_id', $repo)
                ->first();

            if ($existing) {
                $existing->fill($attrs)->save();
                $updated++;
            } else {
                // start_at 只在「首次纳入监控」时写，之后不再改（update 分支的 $attrs 不含它）。
                // 为什么必须给值：Dashboard 的日期筛选用 `where('start_at','<=',...)`，
                // SQL 三值逻辑下 start_at IS NULL 会被判为不匹配 → 用户一选日期范围，
                // 项目就全部消失。留 NULL 是个定时炸弹。
                Project::create($attrs + [
                    'source' => 'ops_snapshot',
                    'external_id' => $repo,
                    'start_at' => now()->toDateString(),
                ]);
                $created++;
            }
        }

        $this->info("✅ created={$created} updated={$updated} skipped={$skipped} 库内共 ".Project::count().' 个项目');

        return self::SUCCESS;
    }
}
