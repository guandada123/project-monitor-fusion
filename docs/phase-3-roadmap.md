# Phase 3 路线图 — SPA 模式 · 国际化 · API 文档

> **版本**: 1.0  
> **日期**: 2026-06-15  
> **状态**: 📋 规划完成，待执行  
> **前置**: Phase 2 已验收通过（Gate-2 ✅）

---

## 📊 当前项目基线

| 维度 | 值 |
|------|-----|
| PHP | 56 文件 / 6,941 行 |
| 视图 | 36 文件 / 6,671 行 |
| 测试 | 31 文件 / 5,603 行（301 passed / 768 assertions） |
| 路由 | 14 条 Web 路由（全 Livewire），**无 API 路由** |
| 语言 | `APP_LOCALE=zh_CN`，`APP_FALLBACK_LOCALE=en`，**无 lang 目录** |
| 前端 | Tailwind v4 + FluxUI + Alpine.js + Three.js |
| 构建 | Vite 6 + Laravel Vite Plugin |
| SPA | 已有 `livewire:navigated` 事件监听；Three.js 已处理导航生命周期 |

---

## 🔷 P3-A: SPA 模式（Livewire Navigate）

### 🎯 目标

利用 Livewire 3 原生 `wire:navigate` 实现完整 SPA 体验，消除全页刷新，配合过渡动画和加载指示器。

### 📋 任务分解

| 编号 | 任务 | 工作量评估 | 依赖 |
|------|------|-----------|------|
| A-1 | **基础 SPA 框架** — 在所有 `flux:link` / 导航链接添加 `wire:navigate`，配置 `@livewireNavigation` 布局指令 | 小 | 无 |
| A-2 | **导航进度条** — 顶部细进度条（NProgress 风格），导航开始 → 完成自动过渡 | 小 | A-1 |
| A-3 | **页面过渡动画** — 使用 `x-transition` 或 Livewire 的 `wire:navigate.hover` 实现平滑的页面进场/离场动画 | 中 | A-1 |
| A-4 | **Prefetch 预加载** — 高频导航链接（仪表板、项目列表）使用 `wire:navigate.hover` 实现悬停预加载 | 中 | A-1 |
| A-5 | **Three.js 导航兼容** — 优化 Three.js 背景在 SPA 导航下的重建逻辑（当前已有 `navigating`/`navigated` 监听，验证并加固） | 小 | A-1 |
| A-6 | **ApexCharts 导航重绘** — 确保所有图表组件在 `livewire:navigated` 后自动重绘 | 小 | A-1 |
| A-7 | **分块加载优化** — 将大型组件视图按路由拆分为异步 chunk，降低首屏 JS 体积 | 中 | A-1 |
| A-8 | **E2E 测试** — Dusk 测试覆盖 SPA 导航场景（页面跳转、URL 正确性、图表渲染） | 中 | A-1~A-7 |

### 🧩 技术方案

```blade
{{-- app.blade.php — 开启 SPA 导航 --}}
<head>
    @livewireNavigation
</head>

{{-- nav-link.blade.php — SPA 友好链接 --}}
<a href="{{ $href }}" wire:navigate.hover 
   @class="[...]">
    {{ $slot }}
</a>
```

```js
// 导航进度条
document.addEventListener('livewire:navigating', () => {
    window.NProgress?.start();
});
document.addEventListener('livewire:navigated', () => {
    window.NProgress?.done();
    // 重设标题
    document.title = document.querySelector('title')?.textContent || 'Project Monitor';
});
```

```css
/* 页面过渡 */
[wire\\:navigate\\.enter] {
    animation: slide-up 0.4s cubic-bezier(0.16, 1, 0.3, 1) both;
}
```

### ⚠️ 风险与注意

- Three.js 在每次导航时销毁/重建，需确保不产生内存泄漏
- ApexCharts 组件在 `wire:key` 不正确时可能无法自动重绘
- FluxUI 组件（`flux:modal`, `flux:dropdown`）在 SPA 导航后需验证状态一致性
- FluxUI 的 `flux:link` 组件可能需要显式传递 `wire:navigate` 属性

---

## 🔷 P3-B: 国际化（i18n）

### 🎯 目标

基于 Laravel 内置本地化系统，实现中/英文双语界面切换，用户偏好持久化，并确保所有 UI 文案的国际化覆盖。

### 📋 任务分解

| 编号 | 任务 | 工作量评估 | 依赖 |
|------|------|-----------|------|
| B-1 | **语言文件结构** — 创建 `lang/zh_CN/` 和 `lang/en/` 目录，按模块分组（common, dashboard, projects, risks, settings, nav, auth, metrics, board, reports, export, monitoring） | 大 | 无 |
| B-2 | **核心文案提取** — 扫描所有 Blade 视图（36 个文件）提取硬编码中文文案并替换为 `@lang()` / `{{ __() }}` / `{{ trans() }}` | 特大 | B-1 |
| B-3 | **语言选择器 UI** — 在顶部导航栏添加语言切换下拉菜单（简体中文 / English），使用 Flux `flux:select` 或自定义下拉 | 中 | B-1 |
| B-4 | **语言持久化** — 通过 SettingsService 存储用户语言偏好，自动应用；未登录用户检测浏览器语言 | 中 | B-3 |
| B-5 | **日期/数字本地化** — 替换所有 `Carbon::format()` 为 `Carbon::isoFormat()` 或 Localized Carbon，确保日期/时间/数字格式随语言切换 | 中 | B-1 |
| B-6 | **ApexCharts 本地化** — ApexCharts 内置 locale 配置（`zh.json`, `en.json`），按当前语言动态切换图表本地化 | 小 | B-1 |
| B-7 | **飞书通知本地化** — 日报推送和风险告警内容根据用户语言偏好生成本地化内容 | 中 | B-1, B-4 |
| B-8 | **Fallback / RTL 基础** — 配置 lang fallback 链（zh_CN → zh → en），添加基本 RTL 样式支持框架 | 小 | B-1 |
| B-9 | **测试覆盖** — 测试语言切换功能、语言文件完整性（所有 key 均有翻译）、日期格式化 | 中 | B-1~B-8 |

### 🧩 技术方案

**语言文件结构：**
```
lang/
├── zh_CN/
│   ├── common.php       # 通用/共用文案
│   ├── nav.php           # 导航
│   ├── dashboard.php     # 仪表板
│   ├── projects.php      # 项目
│   ├── risks.php         # 风险
│   ├── settings.php      # 设置
│   ├── metrics.php       # 指标
│   ├── board.php         # 看板
│   ├── reports.php       # 报表
│   ├── export.php        # 导出
│   ├── monitoring.php    # 监控
│   ├── auth.php          # 认证
│   └── validation.php    # 验证消息
└── en/
    └── (同上结构)
```

**语言切换 Livewire 组件：**
```php
class LanguageSwitcher extends Component
{
    public string $locale;

    public function mount(): void
    {
        $this->locale = app(SettingsService::class)
            ->getString('locale', config('app.locale'));
    }

    public function updatedLocale(string $value): void
    {
        app(SettingsService::class)->set('locale', $value);
        session()->put('locale', $value);
        app()->setLocale($value);
        $this->dispatch('locale-changed');
    }

    public function render(): View
    {
        return view('livewire.language-switcher');
    }
}
```

**本地化中间件：**
```php
class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()
            ? app(SettingsService::class)->getString('locale', config('app.locale'))
            : $request->getPreferredLanguage(['zh_CN', 'en']) ?? config('app.fallback_locale');

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
```

### ⚠️ 风险与注意

- **36 个 Blade 文件文案提取**是 Phase 3 中工作量最大的单项任务，约 300-500 个独立字符串
- 第三方包（FluxUI, ApexCharts, DomPDF 导出模板）的文案本地化需单独处理
- 数据库存储的枚举值（状态：active/archived，优先级：high/medium/low）是否本地化展示？
- 飞书通知中如包含中英文混杂的数据，需定义清晰的本地化边界

---

## 🔷 P3-C: API 文档

### 🎯 目标

构建完整的 RESTful API 层，使用 Sanctum 认证，配合 Scramble（或 Scribe）自动生成 OpenAPI 文档。

### 📋 任务分解

| 编号 | 任务 | 工作量评估 | 依赖 |
|------|------|-----------|------|
| C-1 | **API 基础设施** — 安装 Laravel Sanctum；创建 `routes/api.php`；配置 API 中间件组（auth:sanctum, throttle, json） | 中 | 无 |
| C-2 | **API 认证** — Token 创建/撤销端点；API 令牌管理 UI（设置在 Settings 页面） | 中 | C-1 |
| C-3 | **安装 Scramble** — 安装 `dedoc/scramble` 或 `knuckleswtf/scribe`，配置 API 文档路由 | 小 | C-1 |
| C-4 | **项目 API** — `GET/POST /api/projects`，`GET/PUT/DELETE /api/projects/{id}`，筛选/分页 | 中 | C-1, C-2 |
| C-5 | **任务 API** — 与项目关联的 CRUD + 状态批量更新 | 中 | C-4 |
| C-6 | **风险 API** — 风险列表/详情/状态变更 | 中 | C-4 |
| C-7 | **指标 API** — 指标快照查询（时间范围筛选），聚合统计端点 | 中 | C-4 |
| C-8 | **看板 API** — 看板数据（按状态分组任务），拖拽更新 | 中 | C-4 |
| C-9 | **聚合视图 API** — 跨项目 KPI 汇总数据 | 小 | C-4 |
| C-10 | **OpenAPI 文档** — 集成 Scramble 自动生成交互式 API 文档页面（`/docs/api`） | 中 | C-3~C-9 |
| C-11 | **API 测试** — 使用 Pest + Sanctum 的 `actingAs()` 覆盖所有 API 端点 | 大 | C-4~C-9 |
| C-12 | **速率限制** — `RateLimiter` 配置（全局 60/min，认证后 300/min），错误响应格式化 | 小 | C-1 |
| C-13 | **API 错误处理** — 统一 JSON 错误格式（code, message, errors）；异常→JSON 转换（`ApiExceptionHandler`） | 小 | C-1 |

### 🧩 技术方案

**API 路由设计：**
```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // 项目
    Route::apiResource('projects', ProjectController::class);
    Route::patch('projects/{project}/status', [ProjectController::class, 'updateStatus']);

    // 任务
    Route::apiResource('projects.tasks', TaskController::class)->shallow();
    Route::patch('tasks/batch-status', [TaskController::class, 'batchUpdateStatus']);
    Route::put('tasks/reorder', [TaskController::class, 'reorder']);

    // 风险
    Route::apiResource('risks', RiskController::class)->except(['store', 'destroy']);
    Route::patch('risks/{risk}/status', [RiskController::class, 'updateStatus']);

    // 指标
    Route::get('metrics', [MetricController::class, 'index']);
    Route::get('metrics/snapshots', [MetricController::class, 'snapshots']);
    Route::get('metrics/aggregate', [MetricController::class, 'aggregate']);

    // 看板
    Route::get('board', [BoardController::class, 'index']);
    Route::put('board/tasks/{task}', [BoardController::class, 'updateTask']);

    // 聚合
    Route::get('aggregate', [AggregateController::class, 'index']);

    // 令牌管理
    Route::post('tokens', [TokenController::class, 'store']);
    Route::get('tokens', [TokenController::class, 'index']);
    Route::delete('tokens/{token}', [TokenController::class, 'destroy']);
});
```

**统一 JSON 响应格式：**
```php
// app\Http\Responses\ApiResponse.php
class ApiResponse
{
    public static function success(mixed $data, string $message = 'OK', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    public static function error(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }
}
```

**Scramble 集成：**
```php
// 在 API 控制器中使用注解
class ProjectController extends Controller
{
    #[OA\Get(
        path: '/api/projects',
        tags: ['Projects'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query'),
            new OA\Parameter(name: 'source', in: 'query'),
            new OA\Parameter(name: 'per_page', in: 'query'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        // ...
    }
}
```

### ⚠️ 风险与注意

- Sanctum vs Passport：For SPA + API 混合场景，Sanctum 更轻量，且与 `auth:sanctum` 中间件无缝集成
- Scramble（Laravel 原生 OpenAPI 生成器）vs Scribe：Scramble 更现代化，支持 Laravel 11+ 原生类型推断；Scribe 生态更成熟但维护频率降低
- 已有 Permission 系统需要与 API 集成 — API 端点应复用现有的 `$this->authorize()` 或 `middleware('permission:view_projects')`
- API 文档路由 `/docs/api` 需仅管理员可访问（生产环境）

---

## 📅 执行顺序建议

### 阶段化执行（推荐）

```
Phase 3-A (SPA) ───────────────────────────── 推荐优先
  A-1 基础导航 (小)
  A-2 进度条   (小)
  A-3 过渡动画 (中)
  A-7 分块优化 (中)
  A-5/A-6 兼容 (小)
  A-4 Prefetch (中)
  A-8 E2E测试  (中)

Phase 3-B (i18n) ──────────────────────────── 与 C 并行
  B-1 语言文件结构 (大)
  B-2 文案提取替换 (特大) ← 最耗时
  B-3/B-4 选择器+持久化 (中)
  B-5 日期数字 (中)
  B-8 Fallback (小)
  B-6/B-7 图表/通知 (中)
  B-9 测试 (中)

Phase 3-C (API) ───────────────────────────── 与 B 并行
  C-1/C-2 基础设施+认证 (中)
  C-3 Scramble 集成 (小)
  C-4~C-9 业务 API (大)
  C-10 文档生成 (小)
  C-12/C-13 限制+错误 (小)
  C-11 API 测试 (大)
```

### 并行执行策略

| 并行组 | 任务 | 说明 |
|--------|------|------|
| **Track 1** | A-1 → A-2 → A-3 → A-4 → A-5,6 → A-7 → A-8 | SPA 主线，严格顺序 |
| **Track 2** | B-1 → B-2（持续）→ B-3,4 → B-5 → B-6,7 → B-8 → B-9 | i18n 主线，B-2 可与 Track 3 并行 |
| **Track 3** | C-1,2 → C-3 → C-4~9（迭代）→ C-10 → C-11~13 | API 主线，与 Track 2 完全独立 |

---

## 📊 工作量粗估

| 模块 | 文件数（新增） | 代码行（新增） | 测试数（新增） | 工时评估 |
|------|--------------|--------------|--------------|---------|
| **SPA** | ~6 | ~350 | ~50 | 3-4 天 |
| **i18n** | ~30（语言文件） | ~2,500（文案） + ~400（代码） | ~80 | 5-7 天 |
| **API** | ~18 | ~2,200 | ~300 | 5-7 天 |
| **合计** | ~54 | ~5,450 | ~430 | **13-18 天** |

> 注：工时评估基于单人开发，含测试和调试时间。B-2（文案提取替换）是最大单项，但可工具化扫描完成 70% 以上。

---

## 🔗 依赖关系图

```
A-1 ─┬→ A-2 → A-3 ─→ A-4
     │                   │
     └──→ A-5 ──────────┼→ A-7 → A-8
         → A-6 ─────────┘

B-1 → B-2 ─→ B-3 → B-4 → B-5 → B-6 → B-7 → B-9
                └→ B-8 ─┘

C-1 → C-2 → C-3 → C-4 → C-5 → C-6 → C-7 → C-8 → C-9 → C-10 → C-11
                  └─── C-12 ─── C-13 ───────┘
```

---

## 🎯 Gate-3 验收标准

| 标准 | 描述 |
|------|------|
| **SPA** | 所有内部导航无全页刷新；页面过渡动画流畅；导航进度条正确显示；Three.js 在导航前后正常；E2E 测试通过 |
| **i18n** | 中/英文界面完全覆盖；语言切换即时生效且持久化；日期/时间/数字格式本地化正确；语言文件无缺失 key |
| **API** | 所有核心资源 RESTful 端点完整；OpenAPI 文档 `/docs/api` 可交互浏览；Sanctum 认证正常工作；400+ API 测试通过 |
| **质量** | 全量测试通过；Larastan Level 5 无新增错误；无安全漏洞 |

---

## 📋 后续步骤

1. **决策**: 选择 API 文档工具（Scramble / Scribe）和 SPA 过渡方案
2. **清理**: 创建 `lang/` 目录结构，扫描提取所有 Blade 文案
3. **基础设施**: 安装 Sanctum、Scramble、NProgress 依赖
4. **执行**: 按并行策略分 Track 执行，每个 Track 完成后运行全量测试

---

*文档结束。准备就绪后即可开始 Phase 3 执行。*
