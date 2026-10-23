# 运维工作台（pmf）优化报告

> 项目：`project-monitor-fusion`（pmf 监控面板，Laravel + Livewire 3 + FluxUI + Tailwind v4 + Pest）
> 日期：2026-10-24 · 基线：367 项测试 / 12.4s · 优化后：375 项测试 / 956 断言

---

## 一、结论先行

本次不是"加功能"，而是**先修坏掉的，再收紧门禁，最后做性能**。共落地 **9 项 P0 + 1 项 P1**，识别未改 **3 项 P2**（风险高，列入后续）。

最关键的三件事：

1. 🔴 **367 个测试从未在 CI 真正执行过**——CI 跑的是 `phpunit`，但项目用 Pest，`vendor/bin/phpunit` 会直接抛错退出；又被 4 处 `|| echo "::warning::"` 掩盖成绿色。等于质量门禁长期形同虚设。
2. 🔴 **集成密钥保存功能本来就是坏的**——`request()->input()` 在 `wire:submit` 下拿不到原生表单字段（Livewire 请求体只有 `_token` + `components`），GitHub token / 飞书 App Secret / 钉钉密钥实际都存不进去。
3. 🔴 **"项目对比"功能从入口上就是死的**——`/projects/compare` 被 `/projects/{slug}` 抢先匹配，实际走的是项目详情页，且全站没有任何链接指向它。

---

## 二、对标参考（GitHub 开源项目）

| 参考项目 | 类型 | 可借鉴之处 | 本次落地 |
|---|---|---|---|
| **Grafana** | 可观测性面板 | 面板查询与刷新解耦、聚合查询下沉到 SQL、避免逐项循环查询 | ReportCenter N+1 改批量聚合；Dashboard 已有 `Cache::remember` 模式被确认为正解并向 ReportCenter 推广 |
| **Laravel Pulse / Horizon / Nightwatch** | Laravel 官方监控生态 | 慢查询面板的设计范式：按阈值过滤 + 分页 + 汇总统计分离 | 定位 SlowQueryMonitor 的 flaky 根因（阈值过滤与 factory 随机值冲突），确认其结构与 Pulse 一致、无需重构 |
| **Uptime Kuma / Gatus** | 自托管状态页 | 状态色语义单一真源（open=待处理 / acknowledged=已确认 / closed=已关闭）、侧边导航信息架构 | 用"全代码库口径一致性"方法揪出 ReportCenter 三处离群口径（'active'/'severe' 均为不存在的取值） |
| **Cachet** | 状态页 | 指标卡片"恒显示 0"比不显示更糟——错误数据会误导决策 | 报表"活跃风险/严重风险"两张卡片恒为 0 的口径 bug 修复 |
| **Livewire 官方性能实践** | 框架 | 敏感字段不进快照、N+1 防护、`wire:poll` 间隔治理 | IntegrationManager 凭据处理重构 |

**方法论借鉴**（比具体项目更重要）：Grafana/Pulse 的共同点是"**监控数据本身必须可信**"。一个显示恒为 0 的风险卡片、一个从未执行的 CI 门禁，比没有监控更危险——它们制造虚假的安全感。本次所有改动都遵循这一原则。

---

## 三、现状梳理摘要

### 技术栈
Laravel 12 · Livewire 3 · FluxUI 组件库 · Tailwind v4（CSS-first，无 tailwind.config.js）· Pest 3 · Vite · SQLite/MySQL

### 功能模块（路由 + Livewire 组件）
| 模块 | 组件 | 状态 |
|---|---|---|
| 总览 Dashboard | `Dashboard.php` | ✅ 健康（已用缓存+聚合+预加载） |
| 项目列表/详情/对比/报告 | `ProjectList/ProjectDetail/ProjectCompare/ProjectReport` | 🔴 对比页路由被劫持（已修） |
| 任务管理 | Tasks 相关组件 | ✅ |
| 风险管理 | `RiskManager.php` | ✅ 口径权威实现 |
| 报表中心 | `ReportCenter.php` | 🔴 口径错误 + N+1（已修） |
| 集成管理 | `IntegrationManager.php` | 🔴 凭据暴露 + 保存损坏（已修） |
| 慢查询监控 | `SlowQueryMonitor.php` | 🟡 测试 flaky（已修） |
| 系统设置 | `Settings.php` | 🟡 同类 webhook 明文回填（P2，未改） |
| REST API | `Api/*Controller` | ✅ 契约测试在 |

### 文档与代码的偏差（重要教训）
`code-review-report.md`（81KB）等文档声称的"42 条 SQL 的 N+1"是 6 月旧闻——Dashboard 已被优化过。**不能盲信文档，一切以代码实测为准。**

---

## 四、改进项清单（按优先级）

### P0 — 正确性 / 安全 / 质量门禁

#### P0-1 路由顺序：项目对比页不可达
- **依据**：`/projects/compare` 声明在 `/projects/{slug}` 之后，被通配符抢先匹配；全站无任何链接指向该页（291 行死页面）。
- **处置**：静态路径前置于参数路由；为 `{slug}` 增加 `where('slug', '[A-Za-z0-9._-]+')` 约束；`/projects/compare/push-feishu` 同样调整顺序。
- **预期效果**：项目对比功能从入口可达。
- **风险**：低。已用 `router->match()` 实测四种路径均命中正确路由名。
- **验证**：`/projects/compare` → `projects.compare`；`/projects/abc` → `project.detail`；两个 push-feishu 同理。✅

#### P0-2 Webhook 凭据随 Livewire 快照下发浏览器
- **依据**：`IntegrationManager` 的 `larkWebhook`/`dtWebhook` 为 public 属性，`wire:model` 绑定后，完整 webhook URL（内嵌 access_token）会序列化进 Livewire 快照下发前端。
- **处置**：编辑态**绝不回填**数据库中已有值，只给占位提示"已配置，留空则不修改"；保存时留空即触发 `handleUpdate()` 保留原值。
- **预期效果**：凭据不再进入前端快照。
- **风险**：中。改动表单回填语义，需保证"留空保留原值"合并逻辑正确。
- **验证**：新增 7 项 `IntegrationManagerTest`（新建/编辑/保留原值/不泄漏双重断言），全过。✅

#### P0-3 集成密钥保存功能实际损坏
- **依据**：`livewire.js:6361` 证明请求体只有 `_token` + `components`，**不含原生表单字段**；全库仅一处 `new FormData()`（文件上传）。所以 `request()->input('gh_token')` 在 `wire:submit` 时恒为空——GitHub token / 飞书 App Secret / 钉钉密钥一直都存不进去。
- **处置**：敏感密钥改回 `wire:model` 绑定承载**用户本次输入**；编辑态不回填数据库值（避免明文进快照）；留空保留原值。
- **预期效果**：密钥保存功能从"静默存空值"恢复为真正可用。
- **风险**：高（触及核心功能）。分两步走：先用探针证实 Livewire 测试环境行为，再定稿设计。
- **验证**：7 项集成测试覆盖三条集成类型的保存与保留原值路径。✅

#### P0-4 CI 门禁软化 + 测试命令失效
- **依据**：CI 测试 job 跑 `phpunit --testdox`，但项目用 Pest——该命令必然失败；另有 4 处 `|| echo "::warning::"` 把 Pint/PHPStan/测试失败全部降级为警告。**367 个测试从未在 CI 执行过，却长期显示绿色。**
- **处置**：`phpunit` → `vendor/bin/pest --colors=never`；移除全部 4 处 warning 掩盖，恢复硬阻断。
- **预期效果**：CI 恢复真实门禁。
- **风险**：中。收紧后可能暴露存量失败——已先在本地把全量测试跑到全绿再收紧。
- **验证**：本地 Pest 375 passed（含新增 8 项）后再改 CI，顺序不可反。✅

#### P0-5 PHPStan 因内存崩溃被掩盖
- **依据**：默认 128M 内存下 PHPStan 直接崩溃退出（结果标记 incomplete），崩溃又被 warning 掩盖。
- **处置**：CI 命令加 `--memory-limit=1G`；本地实测 1G 下 **0 error** 后才恢复硬阻断。
- **验证**：`[OK] No errors`。✅

#### P0-6 报表风险统计口径错误 + N+1
- **依据**：`ReportCenter` 用 `status='active'`（真实域为 open/acknowledged/closed）和 `severity='severe'`（真实域为 critical/high/medium/low）——**"活跃风险/严重风险"两张卡片恒显示 0**；另有两处 emoji/颜色映射同样含不存在的 'severe'，导致 critical 风险渲染成 ⚪。同时存在循环内逐项目查询的 N+1。
- **处置**：口径改为 `whereIn('status', ['open','acknowledged'])` 与 `whereIn('severity', ['high','critical'])`（与 RiskManager/Dashboard/Project 模型等权威实现对齐）；emoji 与颜色映射补 `critical`；N+1 改批量 `whereIn` + `groupBy`。
- **预期效果**：报表风险数字从恒 0 变为真实值；报表生成查询从 2N+1 降到 2。
- **风险**：中。口径变更会影响历史报表的可比性——但原口径输出的是错误数据，修正方向明确。
- **验证**：新增测试 `report risk summary uses the real domain values`，并做**证伪验证**：撤回修复后该测试 FAIL，恢复后 PASS（证明测试有效而非恒绿）。✅

#### P0-7 两个 flaky 测试（factory 随机性）
- **依据**：
  - `SlowQueryMonitorTest`：factory 的 `time` 随机 100~3000ms，组件默认过滤 `<500ms`，25 条只有约 86% 达标，`assertSee('25')` 靠 HTML 里偶然出现 "25" 子串通过——实测 flaky 率 1/10。
  - `RiskTest`：`RiskFactory::open()` 只覆盖 status，`definition()` 仍按 30% 概率随机填 `suggestion_applied_at`，"open"记录可能带着已应用建议的时间戳，语义自相矛盾。
- **处置**：测试显式固定 `time` 值 + 用精确断言替代子串匹配 + 增加反向断言；`RiskFactory::open()` 同时清空 `acknowledged_at/closed_at/suggestion_applied_at`。
- **预期效果**：消除假红，恢复 CI 信号可信度。
- **风险**：低。只改测试与工厂，不改生产代码。
- **验证**：修复前 2/10 失败；修复后 15/15 + 10/10 全过。✅

#### P0-8 敏感信息隔离（.gitignore）
- **依据**：`.mcp.json`、`mcp.json`、`.env.*`、`secrets/`、`*token*.json`、`*.pem`、`proxy.env` 等未被忽略，存在凭据入版本库风险。
- **处置**：补 19 行忽略模式，`.env.example` 显式放行。
- **验证**：`git status` 干净，无敏感文件被跟踪。✅

### P1 — 性能

#### P1-1 首屏字体请求优化
- **依据**：`<head>` 中两个 `fonts.bunny.net/css` 请求**阻塞渲染**，且第二个请求要等第一个的 HTML 解析间隙才发。
- **处置**：合并为单个请求（一个 family 参数带两个字族）+ `media="print" onload="this.media='all'"` 异步加载，消除阻塞。
- **预期效果**：减少 1 个阻塞请求，首屏渲染提前。
- **风险**：低。字体异步加载有 FOUT（无样式文本闪烁）权衡——已保留 `display=swap` 语义。
- **验证**：构建通过；页面无 JS 错误（Livewire 依赖的 Alpine 由本地构建产物提供，不依赖 CDN）。✅

#### P1-2 ReportCenter N+1（已并入 P0-6）

### P2 — 识别未改（风险高，列入后续）

| # | 问题 | 为何暂不改 | 建议 |
|---|---|---|---|
| P2-1 | **CSS 产物 248 KB** | 根因是 FluxUI 的 1775 个 `.dark:*` 变体类组合爆炸，属组件库固有成本。动它等于换 UI 组件库，风险与工作量都超本次范围 | 评估按需引入 Flux 组件 / 裁剪未用组件；或接受现状（有浏览器缓存） |
| P2-2 | **Settings.php 同类 webhook 明文回填** | `mount()` 把真实 webhook 回填进 public 属性，与 P0-2 同源。但改它会破坏既有测试契约（SystemTest 依赖当前行为） | 先补测试锁定期望行为，再改实现；需单独立项 |
| P2-3 | **信息架构/导航优化** | 对标 Uptime Kuma/Gatus 的侧边导航与状态色语义，属体验层改进，需产品决策 | 本次未动导航结构，建议作为独立迭代 |

---

## 五、验证结果（全部本地实测）

| 门禁 | 命令 | 结果 |
|---|---|---|
| 测试 | `vendor/bin/pest --colors=never` | **375 passed (956 assertions)**，exit 0 |
| 代码风格 | `vendor/bin/pint --test` | **PASS**（181 files），exit 0 |
| 静态分析 | `vendor/bin/phpstan analyse --memory-limit=1G` | **[OK] No errors**，exit 0 |
| 前端构建 | `npm run build` | ✅ 64 modules / 1.77s，exit 0 |

**额外证据**：
- flaky 测试修复前后对比：修复前 `SlowQueryMonitorTest` 10 跑 2 败；修复后 15 连过 + 全量 10 连过。
- 口径测试证伪：撤回修复 → FAIL；恢复修复 → PASS。
- 路由修复实测：`/projects/compare` 与 `/projects/{slug}` 均命中正确路由名。

---

## 六、改动文件清单

| 文件 | 改动 |
|---|---|
| `routes/web.php` | 静态路由前置 + slug 约束（P0-1） |
| `app/Livewire/IntegrationManager.php` | 凭据处理重构（P0-2/3） |
| `resources/views/livewire/integration-manager.blade.php` | 表单字段不回填 + 占位提示（P0-2/3） |
| `app/Livewire/ReportCenter.php` | 口径修正 + N+1 修复 + emoji 映射（P0-6） |
| `resources/views/livewire/report-center.blade.php` | 颜色映射补 critical（P0-6） |
| `database/factories/RiskFactory.php` | `open()` 状态语义完整化（P0-7） |
| `tests/Feature/SlowQueryMonitorTest.php` | 固定数据 + 精确断言（P0-7） |
| `tests/Feature/IntegrationManagerTest.php` | **新增** 7 项集成测试（P0-2/3） |
| `tests/Feature/ReportTest.php` | **新增** 口径测试（P0-6） |
| `.github/workflows/pmf-ci.yml` | 门禁收紧 + 测试命令修正 + 内存限制（P0-4/5） |
| `resources/views/layouts/app.blade.php` | 字体合并 + 异步加载（P1-1） |
| `.gitignore` | 敏感信息隔离（P0-8） |

---

## 七、下一步动作项

1. **P2-2**：Settings.php webhook 明文回填——先补测试契约再改实现（单独立项）。
2. **P2-1**：CSS 248 KB——评估 Flux 组件按需引入的可行性。
3. **提交与推送**：本次改动尚未 commit；建议按"先本地 commit → 推送 → 回读 CI 实际状态"的顺序收口（CI 收紧后的首次真实运行结果需要回读确认）。
4. **监控**：观察 CI 收紧后首个真实绿灯，确认 375 项测试在 CI 环境（非本地）同样通过。
