# Project Monitor — 项目审计报告

> **审计日期:** 2026-06-14 | **审计范围:** 全栈代码审计
> **项目路径:** `project-monitor/` | **框架:** Laravel 11 + Livewire 3 + PostgreSQL 16

---

## 目录

1. [项目概览](#1-项目概览)
2. [架构评估](#2-架构评估)
3. [代码质量](#3-代码质量)
4. [测试覆盖](#4-测试覆盖)
5. [安全审计](#5-安全审计)
6. [性能评估](#6-性能评估)
7. [依赖健康](#7-依赖健康)
8. [Docker 与部署](#8-docker-与部署)
9. [改进建议与优先级](#9-改进建议与优先级)
10. [审计结论](#10-审计结论)

---

## 1. 项目概览

### 基本信息

| 项目 | 值 |
|------|-----|
| **项目名称** | Project Monitor（项目监控管理系统） |
| **定位** | 面向管理者的多项目进度、KPI、风险一体化监控 Dashboard |
| **总代码量** | 33 Blade 视图、15 Livewire 组件、13 个 Eloquent Model、6 个 Service、4 个 Artisan 命令、5 个导出引擎、16 个迁移文件 |
| **测试规模** | 146 个 Feature 测试 + 42 个 Dusk 浏览器测试（共 ~188 测试） |
| **构建产物** | 1.12s 构建，5 assets（CSS 244KB + JS 690KB） |

### 技术栈

| 层级 | 技术选型 | 版本 |
|------|---------|------|
| **后端框架** | Laravel | 11.31+ |
| **全栈组件** | Livewire + FluxUI | 4.3 / 2.14 |
| **前端构建** | Vite + Tailwind CSS | 6.x / 4.x |
| **数据库** | PostgreSQL (生产) / SQLite (测试) | 16 |
| **图表** | ApexCharts | 5.15 |
| **3D 背景** | Three.js | 0.184 |
| **拖拽** | SortableJS | 1.15 |
| **导出** | OpenSpout | 5.3 |
| **测试** | Pest + PHPUnit + Laravel Dusk | 3.8 / 11.x / 8.x |
| **部署** | Docker Compose (5 服务) | - |

### 功能模块清单

| # | 模块 | 状态 | 测试覆盖 |
|---|------|------|---------|
| 1 | 认证系统（Login/Logout/Remember Me） | ✅ | 6 Test + 10 Dusk |
| 2 | RBAC 权限（Role/Permission 10 项） | ✅ | SystemTest 包含 |
| 3 | Dashboard 驾驶舱（统计卡片 + 趋势图） | ✅ | 4 Test |
| 4 | 项目列表/详情 | ✅ | 5 Test |
| 5 | Kanban 看板（拖拽列切换） | ✅ | 2 Dusk |
| 6 | 风险检测引擎（6 条规则） | ✅ | 59 Test (**高覆盖**) |
| 7 | 风险处理建议（RiskSuggestionService） | ✅ | 18 Test |
| 8 | 指标构建器（8 KPI 系统指标） | ✅ | 3 Test |
| 9 | 日报系统（每日 08:30 自动推送） | ✅ | 4 Test |
| 10 | GitHub 集成（Issues/PRs/Commits 同步） | ✅ | 间接覆盖 |
| 11 | 飞书推送（Webhook 富文本卡片） | ✅ | 间接覆盖 |
| 12 | 批量导出（CSV/XLSX，3 实体类型） | ✅ | **50 Test (高覆盖)** |
| 13 | 全局搜索（跨表 + 键盘导航） | ✅ | 5 Test |
| 14 | 通知中心（铃铛面板） | ✅ | 4 Test |
| 15 | 主题切换（Light/Dark/System） | ✅ | 3 Dusk |
| 16 | 资源负载视图 | ✅ | SystemTest 包含 |
| 17 | 报表中心（周报/月报） | ✅ | 2 Test |
| 18 | 集成管理（GitHub/Lark/DingTalk CRUD） | ✅ | SystemTest 包含 |
| 19 | 设置页（通知偏好 + JSON 持久化） | ✅ | SystemTest 包含 |
| 20 | 角色管理（CRUD + 系统角色防删） | ✅ | SystemTest 包含 |
| 21 | 日报 HTML 模板 + 飞书卡片模板 | ✅ | - |
| 22 | Docker 部署（5 服务 + CI） | ✅ | - |
| 23 | 自定义错误页（403/404/419/500） | ✅ | - |
| 24 | 定时调度（日报/指标/风险） | ✅ | ConsoleTest 包含 |

---

## 2. 架构评估

### 2.1 整体架构评分: **A-**

**优点:**
- ✅ 清晰的 **Service 层**分离（RiskDetector、FeishuService、GitHubService、DailyReportService、MetricCollector）
- ✅ **Livewire 驱动**的组件架构，15 个组件职责分明
- ✅ **抽象基类模式**的导出系统（`BaseExport` → `ProjectExport/TaskExport/RiskExport`）
- ✅ **RBAC** 权限体系（10 permission × 3 role）
- ✅ 16 个迁移文件按时间戳有序组织，外键依赖正确
- ✅ Docker 5 服务架构，队列和调度器容器分离

**可改进:**
- ⚠️ 无传统 Controller 层（所有页面逻辑在 Livewire，导出和推送逻辑在路由闭包）
- ⚠️ 缺少 FormRequest 或专门的验证类（验证逻辑散落在 Livewire 组件的 `rules()` 方法中）
- ⚠️ 缺少 Repository 模式（Model 直接承担了部分数据聚合逻辑）
- ⚠️ `app/Services/ChartSvgService.php` 报告中有引用但实际项目已重构为 ApexCharts，可能遗留了无用引用

### 2.2 目录结构健康度

```
app/
├── Console/Commands/        ✅ 4 个命令，结构清晰
├── Exports/                 ✅ 5 文件，抽象基类模式
├── Http/Controllers/       ⚠️ 仅有 base Controller
├── Http/Middleware/         ✅ 2 个权限中间件
├── Livewire/                ✅ 15 组件，职责分明
├── Models/                  ✅ 13 模型，关系映射完整
├── Notifications/           ✅ 2 个自定义通知类
├── Providers/               ⚠️ 仅有 AppServiceProvider
└── Services/                ✅ 6 个服务类
```

---

## 3. 代码质量

### 3.1 代码规范

| 项目 | 评价 |
|------|------|
| PSR-4 自动加载 | ✅ 一致 |
| PSR-12 编码风格 | ✅ **已集成 Laravel Pint** |
| 类型声明 | ✅ PHP 8.x typed properties + return types |
| 命名约定 | ✅ StudlyCase 类名、camelCase 属性/方法、snake_case 数据库列 |

### 3.2 N+1 查询问题

#### 🔴 中风险 — Dashboard 趋势图函数

**`buildTrendData()` 循环内聚合查询（7天×3类=21条SQL）：**
```php
// app/Livewire/Dashboard.php:127-142
for ($i = 6; $i >= 0; $i--) {
    $dayTasks  = Task::whereDate('completed_at', $date)->count();
    $dayCreated = Task::whereDate('created_at', $date)->count();
    $dayRisks  = Risk::whereDate('first_seen_at', $date)->count();
}
```

**`buildKpiTrend()` 同样模式（7天×3指标=21条SQL）：**
- 每循环一次执行 3 条独立 SQL 查询 `metric_snapshots` 表

**合计：2 个函数每次页面渲染触发 42 条 SQL 查询**

**建议方案：** 改为按 `GROUP BY DATE(...)` 批量聚合，降至 6 条 SQL：

```php
$completedByDay = Task::selectRaw('DATE(completed_at) as date, COUNT(*) as total')
    ->where('completed_at', '>=', now()->subDays(6))
    ->groupBy('date')->pluck('total', 'date');
// 同理 createdByDay, riskByDay, KPI 快照
```

#### 🟡 低风险（潜伏） — Model Accessor 触发独立查询

`app/Models/Project.php` 的 `completion_rate`、`overdue_rate`、`open_risk_count` 等 Accessor：
- 每次调用触发独立 1-2 条 COUNT 查询
- **当前未在 Blade 循环中使用**，无运行时问题
- 但未来若在 `@foreach($projects as $project)` 中调用，会立即引发 N+1

### 3.3 代码异味

| # | 问题 | 位置 | 建议 |
|---|------|------|------|
| 1 | 模型 Accessor 触发数据库查询 | `app/Models/Project.php:67-90` | 改为 computed property + withCount |
| 2 | Service 直接依赖 Config/Env | `FeishuService.php` | 通过构造函数注入配置 |
| 3 | 路由闭包处理 POST | `routes/web.php:logout` | 提取为 Invokable Controller |
| 4 | JSON 设置文件存储 | `storage/app/settings.json` | 考虑迁移到数据库表 |

---

## 4. 测试覆盖

### 4.1 总体数据

| 类型 | 文件数 | 测试数 | 断言数 | 执行时间 |
|------|--------|--------|--------|---------|
| **Feature Tests** | 9 | 146 | 404 | 3.88s |
| **Browser (Dusk)** | 5 | ~42 | ~75+ | (需浏览器) |
| **总计** | 14 | **~188** | **~480+** | - |

### 4.2 各模块测试覆盖矩阵

| 模块 | 覆盖文件 | 测试数 | 覆盖率评估 |
|------|---------|--------|-----------|
| 导出系统 | `ExportTest.php` | **50** | 🟢 **高** |
| 风险检测 | `RiskTest.php` | **59** | 🟢 **高** |
| 系统集成 | `SystemTest.php` | 13 | 🟡 中 |
| 认证登录 | `LoginTest.php` | 6 | 🟡 中 |
| 项目模块 | `ProjectTest.php` | 5 | 🟢 核心覆盖 |
| Dashboard | `DashboardTest.php` | 4 | 🟡 中 |
| 日报命令 | `DailyReportTest.php` | 4 | 🟡 中 |
| 指标模块 | `MetricTest.php` | 3 | 🔴 **低** |
| 报表中心 | `ReportTest.php` | 2 | 🔴 **低** |
| 单元测试 | `tests/Unit/` | **0** | 🔴 **空白** |
| Dusk E2E | 5 文件 | ~42 | 🟡 基础覆盖 |

### 4.3 关键覆盖缺口

#### 🔴 高优先级

| # | 缺口 | 说明 |
|---|------|------|
| 1 | **Unit 目录完全空白** | 无任何纯逻辑单元测试（Model 规则、验证逻辑、辅助函数） |
| 2 | **权限控制测试缺失** | 未测试 non-admin 用户访问受限路由的行为（403 响应） |
| 3 | **API 测试缺失** | 无 JSON/HTTP Controller 测试 |


#### 🟡 中优先级

| # | 缺口 | 说明 |
|---|------|------|
| 4 | **Metric 模块严重不足** | 仅 3 个页面加载测试，MetricCollector 服务逻辑、MetricSnapshot 模型无测试 |
| 5 | **Report 模块严重不足** | 仅 2 个页面加载测试，报表生成逻辑无测试 |
| 6 | **Task 模型独立测试缺失** | 无 Task scope/accessor/cast 测试 |
| 7 | **通知发送逻辑无测试** | RiskAlertNotification 格式化等无测试 |

### 4.4 测试基础设施

| 项目 | 状态 |
|------|------|
| 测试框架 | Pest 3.8 + PHPUnit 11 |
| 数据库隔离 | ✅ SQLite `:memory:` |
| Factory 覆盖 | ✅ **12/13 models** 有 Factory（仅 User 为自带） |
| Seeder 自动运行 | ✅ `TestCase::setUp()` 自动 seed RolePermissionSeeder |
| CI 集成 | ✅ GitHub Actions 三 pipeline |

---

## 5. 安全审计

### 5.1 风险矩阵

| 等级 | 数量 | 说明 |
|------|------|------|
| 🔴 **高危** | 3 | 需立即修复 |
| 🟡 **中危** | 6 | 应尽快处理 |
| 🟢 **低危** | 4 | 建议修复 |
| ✅ **已满足** | 8 | 正面实践 |

### 5.2 🔴 高危（立即修复）

#### H1: 集成凭据暴露到前端

| 项目 | 值 |
|------|-----|
| **位置** | `app/Livewire/IntegrationManager.php` |
| **严重性** | 🔴 **高** |
| **说明** | GitHub Token、飞书 App Secret、钉钉 Secret 设为 Livewire `public` 属性，会以 JSON 形式暴露在 HTML 源码中 |
| **影响** | 任意登录用户可通过 DevTools 获取集成凭据 |
| **建议** | 使用 `#[Locked]` 属性或专用服务类在服务器端处理 |

#### H2: Composer 安全公告被忽略

| 项目 | 值 |
|------|-----|
| **位置** | `composer.json` → `audit.ignore` |
| **严重性** | 🔴 **高** |
| **说明** | 4 个 PKSA 公告被主动忽略: `PKSA-mdq4-51ck-6kdq`, `PKSA-8qx3-n5y5-vvnd`, `PKSA-q46n-4fdk-zjr4`, `PKSA-qzrn-rnz3-85w1` |
| **建议** | 运行 `composer audit` 查看具体公告内容，评估风险是否可接受 |

#### H3: 缺少 CSP 头

| 项目 | 值 |
|------|-----|
| **位置** | `docker/nginx/default.conf` |
| **严重性** | 🔴 **高** |
| **说明** | 生产 Nginx 未配置 `Content-Security-Policy` 头 |
| **影响** | 增加 XSS 和脚本注入的风险面 |
| **建议** | 添加 CSP 策略，控制允许加载的脚本/样式源 |

### 5.3 🟡 中危

| # | 问题 | 位置 | 建议 |
|---|------|------|------|
| M1 | 无 HTTPS/TLS | Nginx `listen 80` | 生产环境配置 SSL 证书 |
| M2 | APP_KEY 运行时被更改 | `entrypoint.sh` | 在 .env 固定，仅首次生成 |
| M3 | 无 HSTS 头 | Nginx | 添加 `Strict-Transport-Security` |
| M4 | Livewire CSP 安全模式未启用 | `config/livewire.php:263` | 启用 `csp_safe => true` |
| M5 | `SESSION_ENCRYPT=false` | `.env.example` | 生产环境建议启用 |
| M6 | 无速率限制 | Nginx/Docker | 添加 `limit_req` 防暴力破解 |

### 5.4 ✅ 正面发现

| # | 良好实践 | 位置 |
|---|----------|------|
| ✅ | Nginx 拦截 `.env`/`.log`/隐藏文件 | `default.conf` |
| ✅ | 所有查询通过 Eloquent ORM，无 SQL 注入 | 全项目 |
| ✅ | CSRF 保护开启，未显式绕过 | `bootstrap/app.php` |
| ✅ | 权限中间件正确实现 | `CheckPermission.php` |
| ✅ | Docker Healthcheck 已配置 | `Dockerfile` |
| ✅ | 会话登录后重新生成 | `Login.php` |
| ✅ | 密码 `hashed` cast 保护 | `User.php` |
| ✅ | 安全响应头 X-Frame-Options + X-Content-Type-Options | Nginx |

---

## 6. 性能评估

### 6.1 构建性能

| 指标 | 值 |
|------|-----|
| **Vite 构建时间** | **1.12s** |
| **CSS** | 243.60 kB (gzip: 33.13 kB) |
| **JS (app)** | 82.51 kB (gzip: 30.28 kB) |
| **JS (Vendor-Apex)** | 602.04 kB (gzip: 166.86 kB) |
| **JS (Three.js)** | 4.99 kB (gzip: 2.10 kB) |
| **总 JS** | 689.54 kB (gzip: 199.24 kB) |

**评价：** 构建时间优秀（1.12s），但 ApexCharts 占用 602kB 原始体积。ApexCharts chunk 已正确分离（vendor-apex），浏览器可缓存，不影响首屏加载。

### 6.2 运行时性能问题

| 问题 | 严重性 | 详情 |
|------|--------|------|
| Dashboard 循环内 42 条 SQL | 🟡 **中** | `buildTrendData()` + `buildKpiTrend()` 各 21 条 |
| Model Accessor 潜伏 N+1 | 🟢 **低** | `Project.php` 的 Accessor 当前未处于循环中 |

### 6.3 Docker 镜像优化

| 问题 | 建议 |
|------|------|
| `COPY . .` 复制整个项目 | 添加 `.dockerignore` 排除 `node_modules/`, `tests/`, `storage/logs/` |
| 运行时镜像含 `nano`/`git` | 可移除减少约 20MB |
| 无 OPcache 预热脚本 | 可添加预热提升首次请求响应 |

---

## 7. 依赖健康

### 7.1 PHP 依赖

| 包名 | 版本 | 用途 | 健康状况 |
|------|------|------|---------|
| `laravel/framework` | ^11.31 | 框架 | 🟢 最新 LTS |
| `livewire/livewire` | ^4.3 | 全栈 | 🟢 稳定 |
| `livewire/flux` | ^2.14 | UI 组件 | 🟢 稳定 |
| `openspout/openspout` | ^5.3 | 导出 | 🟢 稳定 |
| `laravel/tinker` | ^2.9 | REPL | 🟢 稳定 |

### 7.2 JS 依赖

| 包名 | 版本 | 用途 | 健康状况 |
|------|------|------|---------|
| `apexcharts` | ^5.15 | 图表 | 🟢 成熟 |
| `three` | ^0.184 | 3D 渲染 | 🟢 仅背景动画，攻击面有限 |
| `sortablejs` | ^1.15 | 拖拽 | 🟢 成熟 |
| `tailwindcss` | ^4.3 | CSS | 🟢 最新 v4 |
| `vite` | ^6.0 | 构建 | 🟢 最新 |

### 7.3 ⚠️ 被忽略的安全公告

```json
"audit": {
    "ignore": [
        "PKSA-mdq4-51ck-6kdq",
        "PKSA-8qx3-n5y5-vvnd",
        "PKSA-q46n-4fdk-zjr4",
        "PKSA-qzrn-rnz3-85w1"
    ]
}
```

**需人工审查** — 这些 PKSA 通常涉及依赖传递性问题（如 symfony/phpunit-bridge、guzzlehttp 等），但必须逐个确认。

---

## 8. Docker 与部署

### 8.1 服务架构

```
                     ┌─────────────┐
                     │   Nginx     │
                     │   :8000     │
                     └──────┬──────┘
                            │
                     ┌──────▼──────┐
            ┌────────│ App (fpm)   │────────┐
            │        │  :9000      │        │
            │        └─────────────┘        │
            │                               │
     ┌──────▼──────┐                 ┌──────▼──────┐
     │   Queue     │                 │  Scheduler  │
     │ queue:work  │                 │ schedule:run│
     └─────────────┘                 └─────────────┘
                                        │
                                ┌───────▼────────┐
                                │   PostgreSQL   │
                                │     :5432      │
                                └────────────────┘
```

### 8.2 部署评分: **A**

| 项目 | 评价 |
|------|------|
| 多阶段构建 | ✅ 三阶段（composer→node→runtime） |
| 健康检查 | ✅ 所有容器配置 HEALTHCHECK |
| 入口脚本 | ✅ wait-db、自动迁移、哨兵种子 |
| 持久化卷 | ✅ pgdata + app-storage 分离 |
| CI 配置 | ✅ GitHub Actions 三 pipeline |
| 一键部署 | ✅ deploy.sh（5 种操作） |
| 缓存优化 | ✅ config/route/view cache |

### 8.3 改进建议

| # | 建议 | 优先级 |
|---|------|--------|
| 1 | 添加 `.dockerignore` | 🟡 中 |
| 2 | `risk:scan` 添加 `->withoutOverlapping()` | 🟡 中 |
| 3 | 容器降权为非 root 运行 | 🟡 中 |

---

## 9. 改进建议与优先级

### 9.1 🔴 P0 — 立即修复

| # | 行动 | 模块 | 预期效果 |
|---|------|------|---------|
| 1 | GitHub Token/飞书 Secret 改用 `#[Locked]` 或服务端处理 | 安全 | 消除凭据前端暴露 |
| 2 | 审计 4 个被忽略的 PKSA 公告 | 依赖 | 消除已知漏洞风险 |
| 3 | 添加 CSP 头到 Nginx 配置 | 安全 | 降低 XSS 风险面 |

### 9.2 🟡 P1 — 尽快处理

| # | 行动 | 模块 | 预期效果 |
|---|------|------|---------|
| 4 | `buildTrendData()` 改为 GROUP BY 批量聚合 | 性能 | Dashboard 42→6 SQL |
| 5 | `buildKpiTrend()` 同样优化 | 性能 | Dashboard 42→6 SQL |
| 6 | 添加 `.dockerignore` | 部署 | 减小 Docker 镜像体积 |
| 7 | 编写 Unit 测试（至少 Model 基础测试） | 测试 | 填补测试空白 |
| 8 | 为 Metric/Report 模块补充测试 | 测试 | 提升低覆盖模块 |
| 9 | 配置生产 HTTPS | 安全 | 数据传输加密 |

### 9.3 🟢 P2 — 建议改进

| # | 行动 | 模块 | 预期效果 |
|---|------|------|---------|
| 10 | Model Accessor 改为 `withCount` 安全模式 | 架构 | 防止未来 N+1 |
| 11 | 权限控制测试（non-admin 403 验证） | 测试 | 权限边界覆盖 |
| 12 | 添加 `->withoutOverlapping()` 到 scheduler | 部署 | 防止 cron 重叠 |
| 13 | 移除 `readme.md` 默认 Laravel 模板内容 | 文档 | 项目文档个性化 |
| 14 | `.env.example` 更新为生产推荐值 | 配置 | 开箱即安全 |

---

## 10. 审计结论

### 总体评分: **A-** (85/100)

| 维度 | 得分 | 评级 |
|------|------|------|
| **架构设计** | 45/50 | 🟢 A |
| **代码质量** | 40/50 | 🟢 A |
| **测试覆盖** | 35/50 | 🟡 B+ |
| **安全防护** | 40/50 | 🟡 B+ |
| **性能优化** | 42/50 | 🟢 A- |
| **依赖健康** | 43/50 | 🟢 A- |
| **部署就绪** | 45/50 | 🟢 A |
| **文档完整** | 38/50 | 🟡 B+ |
| **总计** | **328/400** | **🟢 A- (82%)** |

### 项目健康总结

**优势：** 
- 架构分层清晰，Service 层设计合理，Livewire 组件职责分明
- 风险检测和导出系统测试覆盖充分（59 + 50 tests）
- Docker 部署架构成熟，5 服务 + CI 全链路
- Premium 设计系统（Light/Dark/System 三态主题，glass morphism）

**短板：**
- **3 个高危安全项**（凭据前端暴露、忽略的 PKSA 公告、缺少 CSP）
- **2 个 N+1 批量查询**（Dashboard 42 SQL）
- **Metric/Report 模块测试严重不足**（2-3 tests）
- **Unit 测试完全空白**（`tests/Unit/` 目录空）

### 投产建议

```
立即投产?  ⚠️ 建议修复 3 个 P0 安全项后投产
预计修复工时:  P0 (3h) + P1 (5h) + P2 (4h) ≈ 12 小时全量修复
最低可行投产:  修复 P0 3 项后即可部署（约 3 小时工作）
```

---

*审计人: Senior Developer (高级开发工程师)*
*审计日期: 2026-06-14*
