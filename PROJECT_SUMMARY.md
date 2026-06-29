# Project Monitor Fusion — 项目上下文速查

## 项目名称与目标
**Project Monitor** — 企业级项目管理监控平台。一站式项目管理、任务追踪看板、风险自动预警、资源负载分析、指标大盘与自动化日报推送。

## 技术栈
- **后端**: PHP 8.2+ / Laravel 12 / Livewire 4 / PostgreSQL 16
- **前端**: Tailwind CSS 4 / Alpine.js / ApexCharts 5 / SortableJS / Three.js / Vite 6
- **关键依赖**: DomPDF(PDF导出) / OpenSpout(CSV/XLSX) / Symfony ExpressionLanguage(自定义公式)
- **测试**: Pest 3.8 + Laravel Dusk / PHPUnit / SQLite :memory: / Playwright E2E
- **DevOps**: Docker 多阶段构建 / Nginx + PHP-FPM / Supervisor / GitHub Actions CI

## 已完成模块
| 模块 | 状态 | 核心文件 |
|------|------|---------|
| 仪表板 | ✅ | `app/Livewire/Dashboard.php` — 12 KPI卡片 + ApexCharts图表,30s轮询 |
| 项目管理 | ✅ | `app/Livewire/ProjectList.php` + `ProjectDetail.php` — CRUD+状态流转 |
| 任务看板 | ✅ | `app/Livewire/TaskBoard.php` — SortableJS拖拽,状态/优先级/人员筛选 |
| 风险中心 | ✅ | `app/Services/RiskDetector.php` — 6条自动检测规则,去重+自动解决 |
| 指标系统 | ✅ | `app/Services/MetricEngine.php` — count/formula/cycle等原子操作,自动快照 |
| 报表导出 | ✅ | `app/Exports/` — CSV/XLSX/PDF三格式导出引擎 |
| GitHub同步 | ✅ | `app/Services/GitHubService.php` — Issues/PR/Commits双相同步(upsert) |
| 飞书通知 | ✅ | `app/Services/FeishuService.php` — 日报推送+风险告警,Webhook AES-256加密 |
| 慢查询监控 | ✅ | `app/Services/SlowQueryMonitor.php` — ≥500ms日志,≥1000ms飞书推送 |
| 角色权限 | ✅ | 3角色(管理员/PM/查看者) + 14细粒度权限, `app/Traits/AuthorizesResourceAccess.php` |
| 系统设置 | ✅ | `app/Services/SettingsService.php` — 1h缓存,写入自动清除 |
| 全局搜索 | ✅ | `app/Livewire/GlobalSearch.php` — Cmd+K快捷键 |
| 资源负载 | ✅ | `app/Livewire/ResourceView.php` — 人员热力图+超载预警 |
| API层 | ✅ | `routes/api.php` — Sanctum认证+14权限中间件+速率限制 |

## 当前进行中/近期完成
- **代码审查**: 刚完成 SettingsService.php 调试追踪代码移除,待继续审查其余文件
- **审计修复**: 38项安全问题已修复(8 P0 + 30 P1), Gate-1/Gate-2验收通过
- **Phase 3规划**: 路线图已出(`docs/phase-3-roadmap.md`) — SPA模式/国际化/API文档,待启动

## 尚未实现
- SPA模式(wire:navigate全站)
- 多语言国际化(lang目录)
- API 自动文档(Scramble 已安装,待配置)

## 已知技术债务
- `IntegrationManager.php` 中敏感凭据以 public 属性暴露(已有 `#[Locked]` 修复方案)
- `SettingsService.php` 调试追踪代码已清除(刚完成)
- 部分 N+1 查询可在 MetricCollector 中用 `with()` 预加载优化

## 目录结构
```
app/
├── Console/Commands/     # 5个Artisan命令(daily:report/risk:scan/github:sync等)
├── Exports/              # 导出引擎(BaseExport/ProjectExport/TaskExport/RiskExport)
├── Http/
│   ├── Controllers/Api/  # 7个API控制器, Webhook/GitHubWebhookController
│   ├── Middleware/       # CheckPermission + CheckRole
│   └── Responses/        # ApiResponse统一格式
├── Livewire/             # 18个Livewire全栈组件
├── Models/               # 12个Eloquent模型
├── Notifications/        # DailyReportNotification + RiskAlertNotification
├── Services/             # 14个业务服务(MetricEngine/RiskDetector/GitHubService/FeishuService等)
└── Traits/               # AuthorizesResourceAccess(多模型权限统一)
routes/api.php            # 20+ API路由, Sanctum+throttle+permission中间件链
tests/                    # 31测试文件,301 passed/768 assertions
e2e/                      # Playwright E2E(4个spec)
```

## 关键架构决策
1. **Livewire 全栈** — 无独立前端框架,所有交互通过 Livewire 组件实现
2. **统一API响应** — `ApiResponse` trait,格式 `{success, message, data, errors}`
3. **三级权限链** — 路由级 `auth:sanctum` → `permission:` 中间件 → 资源级 `AuthorizesResourceAccess`
4. **GitHub双相同步** — Issues→Tasks / PR→Tasks / Commits→Events, 通过 `external_id` 幂等去重
5. **风险自动闭环** — 触发→创建→重复→计数+1→消失→自动解决→再次出现→重新打开
6. **指标公式引擎** — 基于Symfony ExpressionLanguage,支持自定义表达式 `{var}+mod()`
7. **飞书加密存储** — Webhook Secret 使用 AES-256-CBC 加密, SettingsService 统一管理

## 环境配置与运行
```bash
# 本地开发
cp .env.example .env    # 配置 PostgreSQL(pgsql) + 本地DB
composer install && npm install
php artisan key:generate && php artisan migrate --seed
composer dev            # 一键启动(server+vite+queue+logs)

# 测试
php artisan test --parallel    # 301 tests, SQLite :memory:

# Docker部署
./deploy.sh             # docker compose up -d
# 默认管理员: admin@project-monitor.dev / password
```
