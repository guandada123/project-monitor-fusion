# runInBackground() 静默杀死 3 个调度作业（4~23 天零产出）

- 日期：2026-09-01
- 发现者：统一巡检中枢 run#55（Claw/.workbuddy/scripts/unified_ops_center.py）
- 级别：P1（已修复）
- ✅已升级(2026-09-06) ×5

## 现象

`project-monitor-fusion`（pmf）调度器共 7 个作业，其中 3 个的日志与数据库长期零产出：

| 作业 | 定义频率 | 日志最后写入（发现时） | 停滞 |
|---|---|---|---|
| `metrics:collect` | hourly | 2026-08-09 12:00 | 23 天 |
| `github:sync` | every 30 min | 2026-08-28 04:30 | 4.4 天 |
| `risk:scan --no-push` | every 30 min | 2026-08-30 06:30 | 2.3 天 |

- 目标表 `metric_snapshots` / `metrics` / `risks` 全部 **0 行**。
- 同时段另外 4 个作业（daily:report / ops:health-scan / ops:collect-cost / ops:sync-automations）**全部正常**。

## 根因

`routes/console.php` 里这 3 个作业带 `->runInBackground()`，另 4 个不带 —— 这是唯一差异。

在本环境（Laravel 12 + php:8.3-alpine 容器）下，`runInBackground()` 的后台派发**静默失效**：

- `php artisan schedule:test --name="metrics:collect"` → `0.43ms DONE`（真实执行约 117ms），
  `ps -ef` 里**没有任何** artisan 子进程存活，日志 mtime 不变。
- 同一命令**前台直跑完全正常**（exit 0，输出完整结果表）→ 命令本身没坏，坏的是后台派发路径。
- `appendOutputTo()` 的输出在子进程结束时才追加；子进程从未跑完 → 日志永不变动。

## 修复

移除 `routes/console.php` 中 3 处 `->runInBackground()`（**保留** `withoutOverlapping()` 防重叠），
`docker cp` 进容器 + `docker restart` scheduler 容器（`schedule:work` 启动时才加载 console.php，必须重启）。

## 验证（端到端，15:30 自然调度窗口由调度器自行派发）

- `risk-scan.log` / `github-sync.log` mtime → **15:30:00**（此前 08-30 06:30 / 08-28 04:30）
- 对照组 `ops-health.log` → 15:30:00
- `metrics:collect` 经 `schedule:test` 验证 117.61ms 真实执行、日志落盘 15:10:16

## ✅已升级(2026-09-06)（可复用方法论）

1. **同批次对照实验是定位"部分作业失效"最快的手段**：同一调度器、同一分钟的作业里，
   动的 vs 不动的两组，差异项即变量。比逐个翻代码快一个数量级。
2. **日志 mtime 是廉价而可靠的"作业心跳"**：`stat -c %y <log>` 比读日志内容更快判断作业死活，
   配合"最后一次写入时间 vs 定义频率"就能算出停滞时长。
3. **`runInBackground()` 与 `appendOutputTo()` 组合会掩盖失败**：后台子进程的输出在结束时才落盘，
   子进程静默退出 = 日志完全无痕。用后台执行就必须配套 `onFailure`/独立心跳，否则失败不可见。
4. **"部分成功掩盖完全失败"是第二次复现**（首次：08-31 QTS backtest_reports 46 天零落库）。
   同一进程里 4 个作业正常，会让"调度器在跑"这个信号掩盖 3 个作业的死亡。
   → 监控必须**逐作业**校验产出，不能只校验调度器存活。
5. **修改容器内代码前先确认挂载关系**：pmf 只有 `/var/www/storage` 是 volume，
   `/var/www/routes` 是镜像内置的。改容器 = 临时热补丁（重建即丢失），
   改宿主机仓库 = 持久修复。两者要一起做，且重启进程才会加载新配置。

## 遗留

- 宿主机 `project-monitor-fusion/routes/console.php` 已改，**未 commit**（等用户决定）。
- 容器内 `PHP Startup: Unable to load dynamic library 'zip'`（缺 libzip.so.5）每次执行打 3 条 warning，
  属于 Dockerfile 依赖缺失，未修（P2）。
- `ops-health.log` 显示 `qts_backtest`(:8001) / `qts_monitor`(:8002) 长期 DOWN、
  `repo_claw_size` path not accessible —— pmf 健康探测配置与实际部署不符（P2，待核对）。
