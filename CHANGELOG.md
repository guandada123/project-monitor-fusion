# Changelog

## [2026-09-28] Ops 探测在容器部署下全瞎 —— 修 6/6 恒失败 + 镜像重建失败

### 修：`ops:health-scan` 6/6 检查**结构性恒失败**（从建成起就没查过任何东西）
- 真因：命令与面板 API 都把 `127.0.0.1` 与宿主绝对路径写死，而它们**跑在容器里**
  （容器内 127.0.0.1 = 自己；宿主路径不存在）。实测 ops-health.log 13 万行全是失败。
- 现在：探测主机走 `config('ops.probe_host')`（默认 `host.docker.internal`，容器内实测可达宿主已发布端口），
  宿主数据走 docker-compose 的**只读挂载** `/host/*`；两者都可 env 覆盖。
  探测目标与路径抽进 `config/ops.php` + `app/Support/Ops/OpsProbe.php`（原先命令与控制器**各抄一份**，改一处不改另一处）。
- 实测：qts_engine / qts_backtest / qts_monitor 由长期 DOWN → **UP(HTTP 200)**；
  仓库体积由 `ERR path not accessible` → 942 MB / 2 GB / 2 GB。
- 移除 `marvis_watcher` / `marvis_consumer` 两个 `pgrep` 探测：容器 PID namespace 看不到宿主进程
  （结构上恒 DOWN），且目标已休眠（宿主实测 pgrep 为空）。要监控宿主进程请放宿主侧。

### 修：面板不再「取不到证就说健康」
- 体积取不到 → `n/a`（旧实现当 0，喂给 `sizeProgress` 后 roadmap 直接显示**体积目标 100% 完成**）；
  体积目标进度取不到时返回 `null`（未知），前端渲染为「数值不可得，未评判」。
- 探测入口本身不通 → `MISCONFIG` / `unknown`（并带 `probe_input` 自证块），不再把"我连不出去"报成"服务挂了"。
- `workbuddy.db` 是 **WAL 模式**，只读挂载下 SQLite 打不开（要写 `-wal/-shm`）→
  改为复制**只读快照**到容器可写区（TTL 刷新），API 带 `source=snapshot` + `snapshot_at` 如实标注。

### 修：镜像**重建失败**（基础镜像刷新后必现 → 这个服务谁都重新部署不了）
- 真因：`docker-php-ext-install opcache` —— **基础镜像已内置** `Zend OPcache v8.5.11`，
  再装会走到 `install-modules` 却产不出 `modules/*` → `cp: can't stat 'modules/*'` → 构建失败。
- 现改为不装 opcache（其余 5 个扩展实测全 OK）。**不是并行 make 的问题**，`-j$(nproc)` 保持原样。

### 契约测试
- `tests/Feature/OpsApiContractTest.php`：钉住「取不到 → MISCONFIG / n/a / null，不许 0 或 healthy」。

## [2026-07-04] Phase 3: 统一制式

### 基础设施
- .gitmessage 统一 commit 模板
- .pre-commit-config.yaml 接入 gitleaks 密钥扫描
- pre-commit 本地钩子配置（Pint + PHPStan）

### 项目整合
- ops-center 3 个 Artisan 命令迁移（health-scan/collect-cost/sync-automations）
- ops-center 前端资产迁移（dashboard/app.js/style.css）
- routes/console.php 注册 ops 系列定时任务

### 新增
- docs/ops-center/ 操作文档

### CI
- pmf-ci.yml 新增 PHPStan 静态分析 + PHPUnit 测试 + pre-commit 验证
