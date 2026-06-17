# Project Monitor — 深度代码审计报告

> **审计版本:** v2.0（含完整代码分析及修复方案）
> **审计日期:** 2026-06-14 | **代码来源:** 基于真实源码逐行分析
> **审计范围:** 安全漏洞 / 性能瓶颈 / 代码质量 / 架构缺陷

---

## 问题总览

| 等级 | 数量 | 描述 |
|------|------|------|
| 🔴 **P0 — 高危** | 5 | 需立即修复，有安全或数据完整性风险 |
| 🟠 **P1 — 中危** | 7 | 影响性能或代码可维护性，尽快处理 |
| 🟡 **P2 — 低危** | 6 | 最佳实践改进，建议版本迭代中修复 |
| **总计** | **18** | |

---

## 🔴 P0 — 高危问题（立即修复）

---

### P0-1：集成凭据以明文暴露在前端 DOM

**问题描述**

`app/Livewire/IntegrationManager.php` 将 GitHub Token、飞书 App Secret、钉钉 Secret 声明为 `public` 属性。Livewire 会将所有 `public` 属性序列化为 JSON 注入 HTML 初始快照（`wire:initial-data`）。任何登录用户打开 DevTools → Network 或直接查看页面源码，即可获取完整凭据。

**风险等级:** 🔴 高危

**影响范围:**
- `app/Livewire/IntegrationManager.php` 第 22–34 行
- 所有拥有 `manage_integrations` 权限的用户可直接获取第三方凭据
- 受影响字段：`$ghToken`、`$larkAppSecret`、`$dtSecret`

**当前问题代码（第 22–34 行）:**

```php
// ❌ 危险：public 属性直接注入 HTML
public string $ghToken = '';        // GitHub Token 明文暴露
public string $larkAppId = '';
public string $larkAppSecret = '';  // 飞书 App Secret 明文暴露
public string $dtWebhook = '';
public string $dtSecret = '';       // 钉钉 Secret 明文暴露
```

**修复方案（Livewire v4 `#[Locked]` 属性）:**

```php
<?php

use Livewire\Attributes\Locked;

class IntegrationManager extends Component
{
    // ─── 敏感字段：仅服务端可写，不序列化到前端 ───
    #[Locked]
    public string $ghToken = '';

    #[Locked]
    public string $larkAppSecret = '';

    #[Locked]
    public string $dtSecret = '';

    // ─── 非敏感字段可保持 public ───
    public string $ghOrg = '';
    public string $ghRepo = '';
    public string $larkWebhook = '';
    public string $larkAppId = '';
    public string $dtWebhook = '';
```

**更彻底的方案（完全不暴露到前端）：** 将敏感字段存储在 `session()` 或 `cache()` 中，表单提交时从后端读取，Blade 层仅显示掩码（`str_repeat('*', 20)`）。

---

### P0-2：被忽略的 Composer 安全公告（4 个 PKSA）

**问题描述**

`composer.json` 第 74–81 行主动忽略 4 个 PKSA 安全公告，导致 `composer audit` 不再发出警告。这些公告可能涉及传递依赖中的真实漏洞。

**风险等级:** 🔴 高危

**影响范围:**
- `composer.json` `config.audit.ignore` 块
- 忽略了已知漏洞的自动检测

**当前问题代码（第 74–81 行）:**

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

**修复步骤:**

```bash
# 第一步：查看每个公告的具体内容
composer audit --format=json | jq '.advisories'

# 第二步：如果确认是误报（如仅影响 CLI 工具的漏洞），
# 为每个忽略项添加注释说明原因，并写入团队 wiki
# 第三步：若存在真实漏洞，升级对应包
composer update --with-all-dependencies
```

**正确的 ignore 写法（附注原因）:**

```json
"audit": {
    "ignore": {
        "PKSA-mdq4-51ck-6kdq": "只影响 CLI 环境，非 web 请求路径，已评估可接受",
        "PKSA-8qx3-n5y5-vvnd": "影响 symfony/http-client 仅在非生产代码中使用"
    }
}
```

---

### P0-3：Nginx 缺少 Content-Security-Policy 头

**问题描述**

`docker/nginx/default.conf` 配置了 `X-Frame-Options`、`X-Content-Type-Options`、`X-XSS-Protection`，但缺少 **Content-Security-Policy（CSP）** 头。CSP 是防御 XSS 攻击最有效的机制，缺少它意味着一旦应用存在 XSS 漏洞（如动态输出 JSONB 数据未正确转义），攻击者可以加载任意外部脚本。

**风险等级:** 🔴 高危

**影响范围:**
- `docker/nginx/default.conf` 第 15–19 行
- 全站所有页面

**当前配置（第 15–19 行）:**

```nginx
# ─── 安全头 ───
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;  # ← 已过时的头
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
# ❌ 缺少 Content-Security-Policy
```

**修复方案（添加 CSP + 现代安全头）:**

```nginx
# ─── 安全头（升级版）───
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

# ─── 移除已过时的 X-XSS-Protection，改用 CSP ───
# add_header X-XSS-Protection "1; mode=block" always;  ← 删除

# ─── CSP（Livewire + Alpine + Three.js + ApexCharts 适配版）───
add_header Content-Security-Policy "
    default-src 'self';
    script-src  'self' 'nonce-$request_id' 'unsafe-eval';
    style-src   'self' 'unsafe-inline';
    img-src     'self' data: blob:;
    font-src    'self';
    connect-src 'self' wss:;
    worker-src  'blob:';
    frame-ancestors 'none';
" always;
```

> **注意:** Livewire 依赖内联脚本，需配合 Laravel 的 `@nonce` 指令或启用 `livewire.php` 中的 `csp_safe` 模式（见 P1-4）。

---

### P0-4：`logout` 路由缺少认证中间件保护

**问题描述**

`routes/web.php` 第 82–87 行，`/logout` POST 路由定义在所有中间件组之外，未添加 `auth` 中间件。虽然 `auth()->logout()` 对未登录用户是无操作的，但此路由可被匿名用户访问，可能被用于 CSRF 探测或工具自动化扫描时触发意外行为。

**风险等级:** 🔴 中高危

**影响范围:**
- `routes/web.php` 第 82–87 行

**当前问题代码:**

```php
// ❌ 定义在 auth 中间件组外部
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login');
})->name('logout');
```

**修复方案:**

```php
// ✅ 移入 auth 组，或使用 Invokable Controller
Route::middleware(['auth'])->group(function () {
    // ...其他路由...
    Route::post('/logout', function () {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
        return redirect()->route('login');
    })->middleware('auth')->name('logout');
});
```

---

### P0-5：`entrypoint.sh` 中的 `key:generate --force` 可能破坏生产加密数据

**问题描述**

`docker/entrypoint.sh` 第 17–20 行，当 `APP_KEY` 为空或为占位符时自动调用 `key:generate --force`。在生产环境如果因为容器重启导致环境变量未正确注入，这个逻辑会重新生成密钥，**导致所有已加密的 Cookie、Session、加密字段立即失效**，用户被踢出登录，且加密数据永久不可解密。

**风险等级:** 🔴 高危（数据完整性）

**影响范围:**
- `docker/entrypoint.sh` 第 17–20 行
- 生产环境所有 Session、加密字段

**当前问题代码:**

```sh
# ❌ 危险：可能在错误时机重置密钥
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=" ]; then
    echo "→ 生成 APP_KEY..."
    php artisan key:generate --force
fi
```

**修复方案:**

```sh
# ✅ 生产环境：key 必须由外部注入，不自动生成
if [ -z "$APP_KEY" ]; then
    if [ "${APP_ENV:-production}" = "production" ]; then
        echo "❌ FATAL: APP_KEY is not set in production. Aborting."
        exit 1
    else
        echo "→ 开发环境：生成 APP_KEY..."
        php artisan key:generate --force
    fi
fi
```

正确做法是在 Docker Compose 的 `environment` 或 Secrets Manager 中注入 `APP_KEY`，绝不在启动脚本中自动覆盖。

---

## 🟠 P1 — 中危问题（尽快处理）

---

### P1-1：Dashboard 循环内 N+1 查询（每次渲染 42 条 SQL）

**问题描述**

`app/Livewire/Dashboard.php` 存在两个独立的循环查询函数，合计每次 Dashboard 渲染触发 **42 条 SQL**：

- `buildTrendData()`（第 127–142 行）：7天 × 3类 = 21 条 SELECT
- `buildKpiTrend()`（第 144–187 行）：7天 × 3指标 = 21 条 SELECT

**风险等级:** 🟠 中危（性能）

**影响范围:**
- `app/Livewire/Dashboard.php` 第 127–187 行
- Dashboard 主页每次渲染性能

**当前问题代码（buildTrendData，第 127–142 行）:**

```php
// ❌ 7 次循环，每次 3 条 SQL = 21 条
for ($i = 6; $i >= 0; $i--) {
    $date = now()->subDays($i);
    $dayTasks  = Task::whereDate('completed_at', $date)->count();   // SQL #1
    $dayCreated = Task::whereDate('created_at', $date)->count();    // SQL #2
    $dayRisks  = Risk::whereDate('first_seen_at', $date)->count();  // SQL #3
    $days[] = [...];
}
```

**当前问题代码（buildKpiTrend，第 164–184 行）:**

```php
// ❌ 7 次循环，每次 3 条 DB::table = 21 条
for ($i = 6; $i >= 0; $i--) {
    $day = now()->subDays($i)->toDateString();
    $val = DB::table('metric_snapshots')   // SQL #1
        ->where('metric_id', $metricIds['completion_rate'] ?? 0)
        ->whereDate('captured_at', $day)->value('value');
    // ... 同样重复 overdue_ratio 和 unclosed_risks
}
```

**修复方案（GROUP BY 批量聚合，42 → 6 条 SQL）:**

```php
protected function buildTrendData(): array
{
    $startDate = now()->subDays(6)->startOfDay();

    // ✅ 1 条 SQL：按日期分组聚合完成任务
    $completedByDay = Task::selectRaw("DATE(completed_at) as d, COUNT(*) as cnt")
        ->where('completed_at', '>=', $startDate)
        ->whereNotNull('completed_at')
        ->groupBy('d')
        ->pluck('cnt', 'd');

    // ✅ 1 条 SQL：按日期分组聚合新建任务
    $createdByDay = Task::selectRaw("DATE(created_at) as d, COUNT(*) as cnt")
        ->where('created_at', '>=', $startDate)
        ->groupBy('d')
        ->pluck('cnt', 'd');

    // ✅ 1 条 SQL：按日期分组聚合新增风险
    $risksByDay = Risk::selectRaw("DATE(first_seen_at) as d, COUNT(*) as cnt")
        ->where('first_seen_at', '>=', $startDate)
        ->groupBy('d')
        ->pluck('cnt', 'd');

    // 组装（无 SQL，纯集合操作）
    $days = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = now()->subDays($i)->toDateString();
        $days[] = [
            'date'      => now()->subDays($i)->format('m/d'),
            'completed' => (int) ($completedByDay[$date] ?? 0),
            'created'   => (int) ($createdByDay[$date]   ?? 0),
            'risks'     => (int) ($risksByDay[$date]      ?? 0),
        ];
    }
    return $days;
}

protected function buildKpiTrend(): array
{
    $metricKeys = ['completion_rate', 'overdue_ratio', 'unclosed_risks'];
    $metricIds  = DB::table('metrics')
        ->whereNull('project_id')
        ->whereIn('key', $metricKeys)
        ->pluck('id', 'key');

    // ✅ 1 条 SQL：批量取 7 天内所有 KPI 快照
    $snapshots = DB::table('metric_snapshots')
        ->whereIn('metric_id', $metricIds->values())
        ->where('captured_at', '>=', now()->subDays(6)->startOfDay())
        ->select('metric_id', 'value', DB::raw('DATE(captured_at) as date'))
        ->get()
        ->groupBy(fn($r) => $r->metric_id . '_' . $r->date);

    $dates = [];
    $series = ['completion' => [], 'overdue' => [], 'risks' => []];
    $keyMap = [
        'completion_rate' => 'completion',
        'overdue_ratio'   => 'overdue',
        'unclosed_risks'  => 'risks',
    ];

    for ($i = 6; $i >= 0; $i--) {
        $day = now()->subDays($i)->toDateString();
        $dates[] = now()->subDays($i)->format('m/d');

        foreach ($keyMap as $metricKey => $seriesKey) {
            $id      = $metricIds[$metricKey] ?? 0;
            $snap    = $snapshots->get("{$id}_{$day}")?->first();
            $series[$seriesKey][] = $snap ? round((float) $snap->value, 1) : null;
        }
    }
    return [$dates, $series];
}
```

**性能提升预估：** 42 条 SQL → 6 条 SQL，Dashboard 加载时间预计从 ~300ms 降至 ~50ms。

---

### P1-2：Project Model Accessor 触发隐式数据库查询

**问题描述**

`app/Models/Project.php` 第 67–90 行定义了三个 Accessor（`getCompletionRateAttribute`、`getOverdueRateAttribute`、`getOpenRiskCountAttribute`），每个 Accessor 被调用时各触发 2 条独立 COUNT SQL。当前项目列表页、看板页若在循环中访问这些属性，将发生严重的 N+1 问题。

**风险等级:** 🟠 中危（潜伏 N+1，随数据量增长爆发）

**影响范围:**
- `app/Models/Project.php` 第 67–90 行
- 任何在集合循环中调用 `$project->completion_rate`、`$project->overdue_rate`、`$project->open_risk_count` 的地方

**当前问题代码（第 67–73 行）:**

```php
// ❌ 每次调用 = 2 条 SQL
public function getCompletionRateAttribute(): float
{
    $total = $this->tasks()->count();         // SQL #1
    if ($total === 0) return 0;
    $done  = $this->tasks()->whereIn('status', ['done'])->count();  // SQL #2
    return round(($done / $total) * 100, 1);
}

// ❌ 每次调用 = 1 条 SQL
public function getOpenRiskCountAttribute(): int
{
    return $this->risks()->where('status', 'open')->count();  // SQL #1
}
```

**修复方案（使用 `withCount` + 集合计算）:**

```php
// ✅ 方案一：Model 中使用 withCount 声明
// 调用方改为：Project::withCount(['tasks', 'tasks as done_tasks_count' => fn($q) => $q->where('status', 'done')])->get()

// ✅ 方案二：Accessor 改为安全模式（检测关系是否已预加载）
public function getCompletionRateAttribute(): float
{
    // 若关系已预加载（eager load），从集合计算，避免额外 SQL
    if ($this->relationLoaded('tasks')) {
        $total = $this->tasks->count();
        if ($total === 0) return 0;
        $done = $this->tasks->where('status', 'done')->count();
        return round(($done / $total) * 100, 1);
    }

    // 关系未预加载时降级为 DB 查询（明确行为）
    $total = $this->tasks()->count();
    if ($total === 0) return 0;
    $done  = $this->tasks()->where('status', 'done')->count();
    return round(($done / $total) * 100, 1);
}

// ✅ 推荐调用方式（一次查询加载所有需要的数据）
$projects = Project::with(['tasks', 'risks'])
    ->withCount(['risks as open_risk_count' => fn($q) => $q->where('status', 'open')])
    ->get();
```

---

### P1-3：定时任务缺少 `withoutOverlapping()` 防并发保护

**问题描述**

`routes/console.php` 中的 `risk:scan` 任务每 30 分钟执行一次，但未设置 `->withoutOverlapping()`。如果上一轮扫描（数据量大时可能超时）尚未结束，下一轮就会启动，导致：
- 数据库写入并发冲突（risk 记录重复写）
- occurrence_count 计数错误
- 队列 Worker 过载

**风险等级:** 🟠 中危（数据一致性）

**影响范围:**
- `routes/console.php` 第 34–38 行
- `metrics:collect` 同样存在此问题

**当前问题代码:**

```php
// ❌ 缺少并发保护
Schedule::command('risk:scan --no-push')
    ->everyThirtyMinutes()
    ->appendOutputTo(storage_path('logs/risk-scan.log'));

Schedule::command('metrics:collect')
    ->hourly()
    ->appendOutputTo(storage_path('logs/metrics-collect.log'));
```

**修复方案:**

```php
// ✅ 加入 withoutOverlapping() + 超时设置
Schedule::command('risk:scan --no-push')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)          // 最多等待 5 分钟，超时则跳过本轮
    ->runInBackground()              // 后台运行，不阻塞 Scheduler 进程
    ->appendOutputTo(storage_path('logs/risk-scan.log'));

Schedule::command('metrics:collect')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/metrics-collect.log'));
```

---

### P1-4：Livewire CSP 安全模式未启用

**问题描述**

Livewire 4.x 提供 `csp_safe` 模式，启用后将用 `nonce` 替代 `unsafe-inline` 脚本执行，显著提升 XSS 防御能力。当前 `config/livewire.php` 中该选项为默认 `false`。

**风险等级:** 🟠 中危（与 P0-3 CSP 问题联动）

**影响范围:**
- `config/livewire.php`
- 所有 Livewire 页面的内联脚本

**修复方案:**

```php
// config/livewire.php
return [
    // ...
    'inject_assets' => true,

    // ✅ 启用 CSP nonce 支持
    'csp_safe' => true,
];
```

配合 `app.blade.php` 布局：

```html
<!-- resources/views/layouts/app.blade.php -->
<head>
    @livewireStyles
    <!-- Livewire 会自动将 nonce 注入所有内联脚本 -->
</head>
```

---

### P1-5：Unit 测试目录完全空白

**问题描述**

`tests/Unit/` 目录存在但为空，无任何单元测试。Service 层（`RiskDetector`、`FeishuService`、`MetricCollector`、`DailyReportService`）的核心业务逻辑、Model 的 Accessor/Scope 逻辑、数据验证规则均缺少独立单元测试覆盖。

**风险等级:** 🟠 中危（质量保障缺口）

**影响范围:** `tests/Unit/` 目录

**建议补充的核心单元测试:**

```php
// tests/Unit/Services/RiskDetectorTest.php
test('overdue_tasks rule generates correct risk data', function () {
    $project = Project::factory()->create();
    Task::factory()->count(3)->overdue()->for($project)->create();

    $detector = new RiskDetector();
    $result = $detector->checkOverdueTasks($project);

    expect($result)->toHaveKey('rule_key', 'overdue_tasks')
        ->and($result['severity'])->toBe('high');
});

// tests/Unit/Models/ProjectAccessorTest.php
test('completion_rate accessor uses loaded relation without extra queries', function () {
    $project = Project::factory()
        ->has(Task::factory()->count(4))
        ->has(Task::factory()->done()->count(2))
        ->create();

    $project->load('tasks');  // 预加载关系

    DB::enableQueryLog();
    $rate = $project->completion_rate;
    $queries = DB::getQueryLog();

    expect($rate)->toBe(33.3)
        ->and($queries)->toBeEmpty(); // ✅ 关系已加载，无额外 SQL
});
```

---

### P1-6：`SESSION_ENCRYPT=false` 默认未启用

**问题描述**

`.env.example` 第 37 行，`SESSION_ENCRYPT=false` 为显式关闭状态。Session 中存储用户 ID、CSRF token 等敏感数据，启用加密可防止 Session 文件被直接读取（如文件驱动下服务器被入侵时）。

**风险等级:** 🟠 中危（纵深防御）

**修复方案:**

```env
# .env.example
SESSION_ENCRYPT=true     # ← 改为 true（生产推荐）
SESSION_DRIVER=file      # 或 redis（生产推荐 redis）
SESSION_LIFETIME=120
```

---

### P1-7：`Integration::findOrFail()` 缺少权限作用域隔离

**问题描述**

`app/Livewire/IntegrationManager.php` 多处直接使用 `Integration::findOrFail($id)`，未验证当前用户是否拥有该资源的访问权限（仅检查了路由级别的 `manage_integrations` 权限，但未检查资源级别的所有权）。如果系统未来支持多租户，或 ID 遍历（IDOR），攻击者可访问他人的集成配置。

**风险等级:** 🟠 中危（对象级授权）

**影响范围:** `app/Livewire/IntegrationManager.php` 第 69、161、168、197、221 行

**修复方案（添加作用域保护）:**

```php
// ❌ 当前（无隔离）
$integration = Integration::findOrFail($id);

// ✅ 修复（添加策略检查或作用域）
$integration = Integration::findOrFail($id);
$this->authorize('update', $integration);  // 使用 Policy

// 或者更简单地添加查询作用域
$integration = Integration::where('created_by', auth()->id())
    ->findOrFail($id);
```

---

## 🟡 P2 — 低危问题（建议改进）

---

### P2-1：`X-XSS-Protection` 头已被现代浏览器废弃

**问题描述**

`docker/nginx/default.conf` 第 18 行的 `X-XSS-Protection "1; mode=block"` 头在 Chrome 78+、Firefox、Edge 中已被移除并忽略，甚至在某些场景下会引入新的安全问题（Chrome 安全团队已明确建议移除）。

**修复方案:**

```nginx
# ❌ 移除此行
# add_header X-XSS-Protection "1; mode=block" always;

# ✅ 改用 CSP（见 P0-3）
add_header Content-Security-Policy "..." always;
```

---

### P2-2：`logout` 使用路由闭包而非 Controller

**问题描述**

`routes/web.php` 第 82–87 行，logout 逻辑定义在路由闭包中，无法被单元测试、无法复用、不利于中间件扩展。

**修复方案:**

```php
// app/Http/Controllers/Auth/LogoutController.php
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}

// routes/web.php
Route::post('/logout', LogoutController::class)
    ->middleware('auth')
    ->name('logout');
```

---

### P2-3：`readme.md` 仍为 Laravel 默认模板内容

**问题描述**

`readme.md` 完全是 Laravel 框架自带的默认 README，未替换为项目实际说明文档。这对于项目审计、新成员入职、代码仓库专业性均有负面影响。

**建议内容结构:**

```markdown
# Project Monitor

> 多项目进度、KPI、风险一体化监控 Dashboard

## 技术栈
## 快速开始（本地开发）
## Docker 部署
## 测试
## 功能模块
## 贡献指南
```

---

### P2-4：`storage/app/settings.json` 不适合生产配置存储

**问题描述**

飞书 Webhook URL 等运行时配置保存在 `storage/app/settings.json` 文件中，在多容器/无状态部署场景下，容器重建后文件会丢失，且多实例间配置不同步。

**修复方案:** 迁移到数据库表或 Redis。

```php
// 新建 settings 表迁移
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->text('value')->nullable();
    $table->timestamps();
});

// 替换文件操作
Setting::set('feishu.webhook_url', $url);
Setting::get('feishu.webhook_url');
```

---

### P2-5：Nginx 无速率限制（防暴力破解）

**问题描述**

Nginx 配置中未对 `/login` 端点添加速率限制，攻击者可无限制地暴力破解登录表单。Laravel 自带 throttle 中间件，但 Nginx 层面的限速是第一道防线，响应更快且资源消耗更低。

**修复方案:**

```nginx
# nginx.conf 或 default.conf http 块
limit_req_zone $binary_remote_addr zone=login:10m rate=5r/m;

server {
    # ...
    location = /login {
        limit_req zone=login burst=5 nodelay;
        limit_req_status 429;
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

---

### P2-6：Docker 容器以 root 权限运行

**问题描述**

`Dockerfile` 未指定 `USER` 指令，容器进程默认以 root 运行。如果 PHP-FPM 进程被利用（RCE 漏洞），攻击者将直接获得容器内 root 权限。

**修复方案:**

```dockerfile
# Dockerfile 末尾添加
RUN addgroup -g 1001 -S www && \
    adduser -u 1001 -S www -G www

# 修改目录权限
RUN chown -R www:www /var/www/storage /var/www/bootstrap/cache

USER www
EXPOSE 9000
CMD ["php-fpm"]
```

---

## 修复优先级路线图

```
Week 1 (P0，约 6-8h):
├── P0-1  凭据保护       (#[Locked] 属性)    ~2h
├── P0-3  CSP 头         (Nginx 配置)        ~1h
├── P0-4  logout 中间件  (路由调整)          ~30min
└── P0-5  key:generate   (entrypoint 守卫)   ~30min
    P0-2  PKSA 公告审查  (需团队评估)        ~2h

Week 2 (P1，约 8-10h):
├── P1-1  Dashboard N+1  (SQL 重构)          ~3h（含测试）
├── P1-2  Accessor 保护  (关系预加载)        ~2h
├── P1-3  任务并发保护   (withoutOverlapping) ~30min
├── P1-4  Livewire CSP   (config 启用)       ~1h
└── P1-7  IDOR 防护      (资源权限验证)      ~2h

Week 3 (P2，按需):
├── P2-4  Settings 迁移  (数据库方案)        ~3h
├── P2-6  Docker 降权    (USER 指令)         ~1h
└── P2-5  Nginx 限速     (limit_req)         ~30min
```

---

## 修复后预期状态

| 维度 | 修复前 | 修复后 |
|------|--------|--------|
| **安全评分** | B+ | **A** |
| **Dashboard SQL 数量** | 42 条 | **6 条** |
| **凭据暴露风险** | 存在 | **消除** |
| **CSP 防护** | 无 | **启用** |
| **容器权限** | root | **非特权用户** |
| **定时任务并发** | 无保护 | **withoutOverlapping** |

---

*深度审计人: Senior Developer (高级开发工程师)*
*审计日期: 2026-06-14 | 基于真实代码逐行分析*
