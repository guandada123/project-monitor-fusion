# Changelog

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
