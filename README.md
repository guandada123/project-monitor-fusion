# 🚀 Project Monitor

> **企业级项目管理监控平台** — Laravel 11 + Livewire 4 + PostgreSQL 16
> 一站式项目管理、任务追踪、风险预警、资源负载、指标大盘与自动化日报推送。

---

## ✨ 功能概览

| 模块 | 描述 |
|------|------|
| **📊 仪表板** | 12 个 KPI 卡片 + ApexCharts（面积图/甘特图/环形图），30 秒自动轮询刷新 |
| **📋 项目管理** | 项目列表/详情/CRUD，状态流转，自定义字段（JSONB） |
| **📌 任务看板** | 拖拽式看板（SortableJS），状态/优先级/人员筛选 |
| **⚠️ 风险中心** | 6 条自动化检测规则，严重度分级，智能建议，去重与自动解决 |
| **👥 资源负载** | 人员分配热力图，超载预警，工作量分布 |
| **📈 指标系统** | 8 个默认 KPI + 自定义指标，SQL/Math 公式，自动快照采集 |
| **📄 报表中心** | CSV/XLSX/PDF 多格式导出，支持项目/任务/风险三种实体 |
| **🔗 数据集成** | GitHub Issues/PR/Commits 自动同步（每 30 分钟，失败重试 ×3） |
| **🔔 飞书通知** | 每日 08:30 日报推送，严重风险即时告警，可配置 Webhook |
| **🕵️ 慢查询监控** | 实时 SQL 性能追踪，≥500ms 告警，≥1000ms 飞书推送，可视化面板 |
| **🔍 全局搜索** | 跨项目/任务/风险全文搜索，Cmd+K 快捷键 |
| **⚙️ 系统设置** | 项目发现规则、通知配置（Webhook AES-256-CBC 加密存储） |
| **👑 角色权限** | 3 个预设角色（管理员/项目经理/查看者），14 个细粒度权限 |

---

## 🛠️ 技术栈

### 后端
| 技术 | 用途 |
|------|------|
| **PHP 8.3** | 运行时 |
| **Laravel 11** | 应用框架 |
| **Livewire 4** | 全栈动态 UI（无 JavaScript 框架） |
| **PostgreSQL 16** | 主数据库（生产） |
| **PostgreSQL** | 测试数据库（独立库 `project_monitor_test`，与生产同驱动） |
| **DomPDF** | PDF 导出 |
| **OpenSpout** | CSV/XLSX 导出 |

### 前端
| 技术 | 用途 |
|------|------|
| **Tailwind CSS 4** | 样式系统 |
| **Alpine.js**（Livewire 内置） | 交互增强 |
| **ApexCharts 5** | 图表（面积图/甘特图/环形图） |
| **SortableJS** | 看板拖拽 |
| **Three.js** | 数据可视化增强（3D 粒子背景） |
| **Vite 6** | 前端构建 |

### DevOps
| 技术 | 用途 |
|------|------|
| **Docker**（多阶段构建） | 容器化部署 |
| **Nginx** | Web 服务器 |
| **PHP-FPM** | PHP 进程管理 |
| **Supervisor** | 进程守护 |
| **GitHub Actions** | CI/CD |

---

## 📋 环境要求

- **PHP 8.2+**（推荐 8.3）
- **Composer 2.x**
- **Node.js 22+**
- **PostgreSQL 16**（开发/生产）
- **Docker 24+**（容器部署）
- **扩展**: `pdo_pgsql`, `pgsql`, `bcmath`, `zip`, `sockets`, `opcache`, `redis`, `pcntl`, `intl`

---

## 🚀 快速开始（本地开发）

### 1. 克隆项目

```bash
git clone https://github.com/guandada123/project-monitor.git
cd project-monitor
```

### 2. 安装 PHP 依赖

```bash
composer install
```

### 3. 安装前端依赖

```bash
npm install
```

### 4. 配置环境变量

```bash
cp .env.example .env
php artisan key:generate
```

编辑 `.env`，配置数据库连接：

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=project_monitor
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### 5. 创建数据库并迁移

```bash
createdb project_monitor
php artisan migrate
php artisan db:seed
```

### 6. 启动开发服务

```bash
# 终端 1：Laravel 开发服务器
php artisan serve

# 终端 2：Vite 热更新
npm run dev

# 终端 3：队列监听（如需异步任务）
php artisan queue:listen --tries=1
```

或使用 Laravel 的 `composer dev` 一键启动：

```bash
composer dev
```

### 7. 访问系统

打开浏览器访问 `http://localhost:8000`

**默认管理员账号**（Seeder 创建）：
- 邮箱: `admin@project-monitor.dev`
- 密码: `password`

---

## 🐳 Docker 部署

### 首次部署

```bash
# 一键部署
./deploy.sh

# 或手动
docker compose up -d
```

### 升级部署

```bash
./deploy.sh upgrade
```

### 完全重建

```bash
./deploy.sh rebuild
```

### 查看日志

```bash
docker compose logs -f
# 或
./deploy.sh logs
```

### 服务架构

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│   Nginx     │────▶│   PHP-FPM    │────▶│ PostgreSQL  │
│  (80/tcp)   │     │  (app:9000)  │     │ (5432/tcp)  │
└─────────────┘     └──────┬───────┘     └─────────────┘
                           │
                    ┌──────┴───────┐
                    │   Queue      │
                    │  (Worker)    │
                    └──────┬───────┘
                           │
                    ┌──────┴───────┐
                    │  Scheduler   │
                    │ (schedule:work)│
                    └──────────────┘
```

---

## ⏰ 调度任务

系统包含 4 个自动调度任务，需要在 crontab 中注册：

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

| 命令 | 时间 | 描述 |
|------|------|------|
| `daily:report` | 每日 08:30 | 生成项目日报并推送飞书 |
| `metrics:collect` | 每小时 | 指标快照自动采集 |
| `risk:scan --no-push` | 每 30 分钟 | 风险检测（不推送） |
| `github:sync` | 每 30 分钟 | GitHub 数据同步（重试 ×3） |

---

## 🧪 测试

### 前置：创建测试库（仅首次）

测试跑在与生产同驱动的 PostgreSQL 上（`phpunit.xml` 固定 `DB_DATABASE=project_monitor_test`，
`RefreshDatabase` 会对其执行 `migrate:fresh`，**绝不能用业务库**）：

```bash
createdb -h 127.0.0.1 -U <你的数据库用户> project_monitor_test
```

CI 里由 workflow 的「Create test database」步骤自动建库；本地缺失该库时测试会直接报连接错误。

### 运行全量测试

```bash
# 全部测试
php artisan test

# 并行测试（10 进程）
php artisan test --parallel

# 指定测试套件
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# 指定文件
php artisan test tests/Feature/SystemTest.php

# 带覆盖率（需 Xdebug 或 PCOV）
php artisan test --coverage
```

### 测试统计

| 指标 | 数值 |
|------|------|
| ✅ 全量测试 | 382 passed / 982 assertions（2026-10-24，PostgreSQL 驱动） |
| ⚡ 并行执行 | 7.14s（10 进程） |
| 📁 测试文件 | 31 个 |
| 🧩 测试范围 | Unit + Feature |
| 🐘 E2E 测试 | Dusk（5 个浏览器测试） |

### 测试模式

- **数据库**: SQLite `:memory:`（`phpunit.xml` 配置），测试隔离无状态
- **HTTP**: `Http::fake()` + `Http::preventStrayRequests()` 模拟外部 API
- **权限**: `TestCase::setUp()` 自动 seed `RolePermissionSeeder`
- **用户工厂**: `UserFactory::admin()` / `UserFactory::viewer()` 自包含

---

## 📁 项目结构

```
project-monitor/
├── app/
│   ├── Console/Commands/         # Artisan 命令
│   │   ├── DailyReport.php       # 日报生成推送
│   │   ├── GitHubSyncCommand.php # GitHub 同步（重试 ×3）
│   │   ├── MetricsCollect.php    # 指标快照采集
│   │   └── RiskScanCommand.php   # 风险扫描
│   ├── Exports/                  # 导出引擎
│   │   ├── BaseExport.php        # 抽象基类
│   │   ├── ProjectExport.php     # 项目导出
│   │   ├── TaskExport.php        # 任务导出
│   │   └── RiskExport.php        # 风险导出
│   ├── Livewire/                 # Livewire 组件（18 个）
│   │   ├── Dashboard.php         # 仪表板
│   │   ├── ProjectList.php       # 项目列表
│   │   ├── ProjectDetail.php     # 项目详情
│   │   ├── TaskBoard.php         # 任务看板
│   │   ├── RiskManager.php       # 风险中心
│   │   ├── Settings.php          # 系统设置
│   │   └── ...
│   ├── Models/                   # Eloquent 模型（12 个）
│   ├── Services/                 # 业务服务
│   │   ├── ExportEngine.php      # 导出引擎（CSV/XLSX/PDF）
│   │   ├── FeishuService.php     # 飞书推送
│   │   ├── GitHubService.php     # GitHub API 集成
│   │   ├── RiskDetector.php      # 风险检测（6 规则）
│   │   ├── SettingsService.php   # 配置管理（加密存储）
│   │   └── SlowQueryMonitor.php  # SQL 慢查询监控
│   └── Notifications/            # 通知类
├── database/
│   ├── migrations/               # 19 个迁移
│   ├── factories/                # 13 个工厂类
│   └── seeders/                  # 4 个种子
├── resources/views/              # 36 个 Blade 视图
│   ├── layouts/                  # 布局模板
│   └── livewire/                 # Livewire 组件视图
├── tests/
│   ├── Unit/                     # 单元测试
│   └── Feature/                  # 功能测试
├── docs/
│   └── gate-1-acceptance-report.md
├── docker/                       # Docker 配置
│   ├── nginx/                    # Nginx 配置
│   ├── php/                      # PHP 配置
│   └── entrypoint.sh             # 容器入口
├── Dockerfile                    # 多阶段构建
├── docker-compose.yml            # 服务编排
└── deploy.sh                     # 一键部署脚本
```

---

## 🔐 权限系统

### 3 个预设角色

| 角色 | 权限 | 适用人群 |
|------|------|---------|
| **管理员** | 全部 14 项权限 | 系统管理员 |
| **项目经理** | 查看 + 管理项目/指标/集成/风险 | 项目负责人 |
| **查看者** | 只读访问（7 项查看权限） | 业务方/访客 |

### 14 项细粒度权限

| 权限 | 分组 | 描述 |
|------|------|------|
| `view_dashboard` | dashboard | 查看仪表板 |
| `view_board` | board | 查看看板 |
| `view_projects` | projects | 查看项目 |
| `manage_projects` | projects | 管理项目 |
| `view_resources` | resources | 查看资源负载 |
| `view_metrics` | metrics | 查看指标 |
| `manage_metrics` | metrics | 管理指标 |
| `view_reports` | reports | 查看报表 |
| `manage_integrations` | integrations | 管理集成 |
| `manage_settings` | settings | 管理系统设置 |
| `manage_roles` | roles | 管理角色权限 |
| `view_risks` | risks | 查看风险中心 |
| `manage_risks` | risks | 管理风险 |
| `view_monitoring` | monitoring | 查看慢查询监控 |

---

## 🔧 环境变量

| 变量 | 默认值 | 描述 |
|------|--------|------|
| `APP_NAME` | Project Monitor | 应用名称 |
| `APP_ENV` | local | 运行环境 |
| `APP_DEBUG` | true | 调试模式 |
| `APP_URL` | http://localhost:8000 | 应用 URL |
| `APP_TIMEZONE` | Asia/Shanghai | 时区 |
| `APP_LOCALE` | zh_CN | 语言 |
| `DB_CONNECTION` | pgsql | 数据库类型 |
| `DB_HOST` | 127.0.0.1 | 数据库主机 |
| `DB_PORT` | 5432 | 数据库端口 |
| `DB_DATABASE` | project_monitor | 数据库名 |
| `SESSION_DRIVER` | file | 会话驱动 |
| `QUEUE_CONNECTION` | database | 队列驱动 |
| `CACHE_STORE` | file | 缓存驱动 |

---

## 🔬 性能与监控

- **30 秒自动轮询**: 仪表板数据自动刷新，ApexCharts 平滑过渡
- **SQL 慢查询监控**: ≥500ms 记录日志，≥1000ms 推送到飞书
- **调用栈定位**: 自动排除 vendor/，定位业务代码文件:行号
- **查询缓存**: SettingsService 1 小时缓存，写入自动清除
- **队列**: 数据库驱动队列，失败重试 3 次

---

## 🧹 代码质量

- **Larastan Level 5**: 0 错误 ✅
- **Laravel Pint**: PSR-12 编码规范
- **安全审计**: 38 项修复（8 P0 + 30 P1）
- **CSP 头**: 生产环境 Content-Security-Policy
- **SESSION_ENCRYPT**: 会话加密
- **登录限速**: 防止暴力破解

---

## 📈 版本历史

| 版本 | 日期 | 描述 |
|------|------|------|
| v1.0.0 | 2026-06-14 | Phase 1 — 核心功能 + Gate-1 验收通过 |
| v1.1.0 | 2026-06-14 | Phase 2 — 高级筛选/慢查询/GitHub 同步/设置加密 |

---

## 🤝 贡献指南

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交改动 (`git commit -m 'feat: add amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 创建 Pull Request

### 提交规范

遵循 [Conventional Commits](https://www.conventionalcommits.org/)：
- `feat:` 新功能
- `fix:` 修复
- `test:` 测试
- `docs:` 文档
- `refactor:` 重构
- `perf:` 性能优化
- `chore:` 构建/工具

---

## 📄 许可证

MIT License — 详见 [LICENSE](LICENSE) 文件。

---

## 👤 作者

- **管海林**（[@guandada123](https://github.com/guandada123)）— 武汉腾讯产品经理兼保险经纪

---

## 🙏 致谢

- [Laravel](https://laravel.com)
- [Livewire](https://livewire.laravel.com)
- [Flux UI](https://fluxui.dev)
- [ApexCharts](https://apexcharts.com)
- [DomPDF](https://github.com/dompdf/dompdf)
- [OpenSpout](https://github.com/openspout/openspout)
