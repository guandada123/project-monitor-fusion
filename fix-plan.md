# 🔧 Project Monitor Fusion — 完整修复方案

> **基于代码审查结果生成**
> 覆盖 **83+ 文件**，共计 **2 🔴 + 30 🟡 + 15 💭** 项修复
> 按严重程度降序排列

---

## 📋 目录

1. [🔴 安全修复 — 立即处理](#1-安全修复--立即处理)
2. [🔴 架构修复](#2-架构修复)
3. [🟡 数据一致性修复](#3-数据一致性修复)
4. [🟡 性能修复](#4-性能修复)
5. [🟡 代码质量与防御性修复](#5-代码质量与防御性修复)
6. [💭 微调建议](#6-微调建议)
7. [修复顺序建议](#7-修复顺序建议)

---

## 1. 🔴 安全修复 — 立即处理

### 1.1 IntegrationManager — Livewire 属性泄露敏感凭据

**文件:** `app/Livewire/IntegrationManager.php`
**行号:** 第 31、42-43、48-49 行（属性声明），第 110-128 行（加载方法），第 183-201 行（buildConfig）

**当前代码:**

```php
// 第 31 行 — 敏感凭据声明为 #[Locked] 属性
#[Locked]
public ?string $ghToken = null;

#[Locked]
public ?string $larkAppSecret = null;

#[Locked]
public ?string $dtSecret = null;
```

```php
// 第 110-128 行 — 编辑时从配置加载凭据到 Livewire 属性
private function loadGitHubForm(array $config): void
{
    $this->ghToken = $config['token'] ?? '';
    // ...
}
```

**问题分析:** `#[Locked]` 属性只阻止客户端通过 Livewire 请求修改该属性的值，但**不阻止初始值渲染到 HTML 中**。Livewire 将组件状态序列化到 `wire:initial-data` JSON 中，任何人都可以查看页面源代码提取 `ghToken` / `larkAppSecret` / `dtSecret`。即使页面受登录保护，XSS 或其他同站攻击面仍可窃取凭据。

**修复方案:**

方案 A（推荐 — 掩码展示 + 后端直存）:

```php
// 第 31 行 — 改为：不存储真实 secret，仅存掩码版本
public ?string $ghToken = '';
public ?string $larkAppSecret = '';
public ?string $dtSecret = '';

// 第 110-128 行 — 加载配置时，front-end 只显示掩码
private function loadGitHubForm(array $config): void
{
    $token = $config['token'] ?? '';
    $this->ghToken = $token ? substr($token, 0, 4) . '••••••••' : '';
}
```

方案 B（推荐 — 编辑不展示，只传 ID）:

```php
// 第 31 行 — 移除所有秘密属性
public ?int $editingId = null;
public bool $showSecretForm = false;

// 第 110-128 行 — 只加载非秘密信息
private function loadGitHubForm(array $config): void
{
    // 不加载 token 到 Livewire 属性
    $this->editingId = $config['id'] ?? null;
    $this->ghUsername = $config['username'] ?? '';
    // ghToken 不赋值，视图显示 '••••••••'
}
```

```blade
{{-- blade 视图中 --}}
<div class="form-group">
    <label>GitHub Token</label>
    <input type="password" wire:model="ghToken" placeholder="留空则不修改">
    @if($editingId)
        <small class="text-muted">当前 Token 已配置，留空保持原值</small>
    @endif
</div>
```

```php
// 第 130-181 行 — save() 方法中：
// 如果密码字段为空，保留原配置中的秘密
$config = $integration->config ?? [];
if (!empty($this->ghToken)) {
    $config['token'] = $this->ghToken;
}
// 如果留空，$config['token'] 保持原值不变
```

**✅ 保留亮点:** Integration 模型使用了 `'encrypted:array'` cast（`app/Models/Integration.php` 第 16 行），存储层是安全的，此修复只处理展示层泄露。

---

### 1.2 Login 组件 — 缺少暴力破解防护

**文件:** `app/Livewire/Auth/Login.php`
**行号:** 第 21-33 行

**当前代码:**

```php
public function login(): void
{
    $this->validate();

    if (Auth::attempt([
        'email' => $this->email,
        'password' => $this->password,
    ], $this->remember)) {
        session()->regenerate();
        $this->redirect(route('dashboard'));
    }

    $this->addError('email', '邮箱或密码不正确');
}
```

**问题分析:** 没有任何登录尝试次数限制。攻击者可以无限次尝试密码（字典攻击/暴力破解）。Laravel 内置了 `RateLimiter`，但未使用。

**修复方案:**

```php
use Illuminate\Cache\RateLimiter;

public function login(RateLimiter $limiter): void
{
    $this->validate();

    $key = 'login:' . $this->email;

    // 检查是否被限流
    if ($limiter->tooManyAttempts($key, 5)) {
        $seconds = $limiter->availableIn($key);
        $this->addError('email', "登录尝试过于频繁，请 {$seconds} 秒后重试");
        return;
    }

    if (Auth::attempt([
        'email' => $this->email,
        'password' => $this->password,
    ], $this->remember)) {
        $limiter->clear($key);
        session()->regenerate();
        $this->redirect(route('dashboard'));
        return;
    }

    // 记录失败一次，60 秒内超过 5 次触发限流
    $limiter->hit($key, 60);
    $this->addError('email', '邮箱或密码不正确');
}
```

**补充 — 或使用内置 Throttle Middleware:**

```php
// 方案 B: 在 routes/web.php 中为登录路由添加 throttle
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:5,1'); // 每分钟 5 次
```

---

### 1.3 GitHubWebhookController — Secret 空值检查

**文件:** `app/Http/Controllers/Webhook/GitHubWebhookController.php`
**行号:** 第 19 行

**当前代码:**

```php
$secret = config('services.github.webhook_secret');
if (! $this->isValidSignature($request->getContent(), $signature, $secret)) {
```

**问题分析:** 如果 `config('services.github.webhook_secret')` 未配置（返回 null），`hash_hmac()` 会使用空字符串计算 HMAC。导致要么所有请求验证失败（永远 403），要么攻击者可以用空字符串签名绕过验证。

**修复方案:**

```php
$secret = config('services.github.webhook_secret');
if (empty($secret)) {
    Log::critical('GitHub Webhook secret not configured');
    abort(500, 'Webhook 未正确配置');
}
if (! $this->isValidSignature($request->getContent(), $signature, $secret)) {
    abort(401, '无效的签名');
}
```

---

### 1.4 ChartSvgService — SVG 缺少危险字符转义

**文件:** `app/Services/ChartSvgService.php`
**行号:** `horizontalBars()`、`kpiCard()` 方法

**问题分析:** `groupedBars()` 已使用 `htmlspecialchars()` 转义文本，但 `horizontalBars()` 和 `kpiCard()` 没有。SVG 是 XML，如果标签名中含有 `]]><script>alert(1)</script>` 之类的内容，可能在 PDF 渲染或浏览器预览时执行脚本。

**修复方案:**

```php
// 统一在字符串插值位置使用 htmlspecialchars()
// 例如 horizontalBars() 中：
$svg .= '<text x="10" y="' . $y . '">' . htmlspecialchars($item['label']) . '</text>';

// kpiCard() 中：
$svg .= '<text class="title">' . htmlspecialchars($title) . '</text>';
```

**✅ 保留亮点:** `groupedBars()` 已正确使用了 `htmlspecialchars()`，只需让另外两个方法对齐即可。

---

## 2. 🔴 架构修复

### 2.1 FeishuService + FeishuAlertService — 双飞书服务合并

**涉及文件:**
- `app/Services/FeishuService.php`（239 行）
- `app/Services/RiskEngine/FeishuAlertService.php`（113 行）
- `app/Livewire/ProjectCompare.php`（第 57 行 — `new FeishuAlertService`）
- `app/Http/Controllers/ReportController.php`（第 147、173 行 — `new FeishuAlertService`）

**问题分析:** 两个独立的飞书消息推送服务，功能高度重叠（都发送交互卡片到飞书群），但配置源不同：
- `FeishuService`：从 `SettingsService`（DB）读取 webhook URL
- `FeishuAlertService`：从 `config('services.feishu.webhook_url')`（.env）读取
- 两套配置可能存储不同的 webhook URL，导致告警发到错误群组
- 任何飞书卡片模板修改都需要同步更新两个文件
- 多处使用 `new FeishuAlertService` 硬编码实例化（ProjectCompare 第 57 行，ReportController 第 147/173 行）

**修复方案 — 4 步执行:**

**第一步：合并 FeishuAlertService 核心功能到 FeishuService**

```php
// app/Services/FeishuService.php — 新增 sendReportSummary 方法
// 将 FeishuAlertService 的 sendReportSummary() 逻辑移植过来

public function sendReportSummary(Project $project, string $reportUrl = ''): bool
{
    $projectUrl = $reportUrl ?: route('reports.show', $project);
    $tasksCount = $project->tasks()->count();
    $doneCount = $project->tasks()->where('status', 'done')->count();
    $openRisks = $project->risks()->where('status', 'open')->count();

    $content = "📊 **项目报告：{$project->name}**\n"
        . "任务完成：{$doneCount}/{$tasksCount}\n"
        . "开放风险：{$openRisks}\n"
        . "[查看完整报告]({$projectUrl})";

    return $this->sendText($content);
}
```

**第二步：FeishuService 添加 sendRiskAlert 方法**

```php
// app/Services/FeishuService.php — 新增 sendRiskAlert 方法
public function sendRiskAlert(Project $project, array $risks): bool
{
    $card = $this->buildRiskAlertCard($project, $risks);
    return $this->sendInteractiveCard($card);
}

// 修改 buildRiskAlertCard 接受 Project + 风险数组
public function buildRiskAlertCard(Project $project, array $risks): array
{
    $items = [];
    foreach ($risks as $risk) {
        $items[] = [
            'tag' => 'markdown',
            'content' => "**{$risk->severity}** - {$risk->title}"
        ];
    }

    return [
        'config' => ['wide_screen_mode' => true],
        'header' => [
            'title' => ['tag' => 'plain_text', 'content' => "⚠️ 风险告警：{$project->name}"],
            'template' => 'red',
        ],
        'elements' => [
            ['tag' => 'div', 'text' => ['tag' => 'lark_md', 'content' => "项目 {$project->name} 有以下风险待处理："]],
            ...$items,
            ['tag' => 'hr'],
            ['tag' => 'note', 'elements' => [['tag' => 'plain_text', 'content' => '来自 Project Monitor Fusion']]],
        ],
    ];
}
```

**第三步：删除 FeishuAlertService 文件**

删除 `app/Services/RiskEngine/FeishuAlertService.php` 文件后，确认没有其他引用。后续全局搜索 `FeishuAlertService` 确保全部替换。

**第四步：替换所有 `new FeishuAlertService` 调用为 DI 注入**

```php
// app/Livewire/ProjectCompare.php 第 57 行
// 改为通过 app() 容器解析
use App\Services\FeishuService;

// 在方法中：
$feishu = app(FeishuService::class);
```

```php
// app/Http/Controllers/ReportController.php 第 147、173 行
// 改为构造器注入
use App\Services\FeishuService;

public function __construct(
    protected FeishuService $feishuService
) {}

// 方法中使用：
$this->feishuService->sendInteractiveCard($card);
```

---

## 3. 🟡 数据一致性修复

### 3.1 Project 模型 — completionRate() 与 getCompletionRateAttribute() 逻辑不一致

**文件:** `app/Models/Project.php`
**行号:** 第 68-77 行（completionRate），第 91-109 行（getCompletionRateAttribute）

**当前代码:**

```php
// 方法：包含 3 种状态
public function completionRate(): float
{
    $total = $this->tasks()->count();
    if ($total === 0) return 0;
    $done = $this->tasks()->whereIn('status', ['done', 'closed', 'completed'])->count();
    return round(($done / $total) * 100, 2);
}

// 访问器：只包含 1 种状态
public function getCompletionRateAttribute(): float
{
    if ($this->relationLoaded('tasks')) {
        $total = $this->tasks->count();
        $done = $this->tasks->where('status', 'done')->count();
    } else {
        $total = $this->tasks()->count();
        $done = $this->tasks()->whereIn('status', ['done'])->count();
    }
    if ($total === 0) return 0;
    return round(($done / $total) * 100, 2);
}
```

**问题分析:** `completionRate()` 认为 `done`、`closed`、`completed` 三种状态都算完成，但 `getCompletionRateAttribute()` 只把 `done` 算完成。开发者在不同场景使用不同方法会得到不同的完成率。

**修复方案:**

统一两个方法的逻辑，同时保留 `relationLoaded()` 的智能加载策略：

```php
// 在类顶部定义常量
public const COMPLETED_STATUSES = ['done', 'closed', 'completed'];

// 统一方法
public function completionRate(): float
{
    $total = $this->tasks()->count();
    if ($total === 0) return 0;
    $done = $this->tasks()->whereIn('status', static::COMPLETED_STATUSES)->count();
    return round(($done / $total) * 100, 2);
}

// 访问器直接委托给方法，同时保留 relationLoaded 优化
public function getCompletionRateAttribute(): float
{
    if ($this->relationLoaded('tasks')) {
        $total = $this->tasks->count();
        $done = $this->tasks->whereIn('status', static::COMPLETED_STATUSES)->count();
    } else {
        return $this->completionRate();
    }
    if ($total === 0) return 0;
    return round(($done / $total) * 100, 2);
}
```

**✅ 保留亮点:** `relationLoaded()` 模式（第 93-107 行）是智能加载策略的优秀实践，修复中保留了它。

---

### 3.2 MetricCollector — 子查询重复

**文件:** `app/Services/MetricCollector.php`
**行号:** 第 273-284 行（calcAvgWorkload）

**当前代码:**

```php
$inProgress = Task::where('status', 'in_progress')      // 查询 1
    ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
    ->count();

$assignees = Task::where('status', 'in_progress')       // 查询 2（条件完全相同）
    ->whereNotNull('assignee_id')
    ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
    ->distinct('assignee_id')
    ->count('assignee_id');
```

**问题分析:** 同样的 `status=in_progress` + `project_id` 条件执行了两次。在批量采集所有项目指标时，N+1 效应会放大。

**修复方案:**

```php
// 复用查询构建器
$baseQuery = Task::where('status', 'in_progress')
    ->when($projectId, fn ($q) => $q->where('project_id', $projectId));

$inProgress = (clone $baseQuery)->count();

$assignees = (clone $baseQuery)
    ->whereNotNull('assignee_id')
    ->distinct('assignee_id')
    ->count('assignee_id');
```

**同时修复 calcCompletionRate 和 calcOverdueRatio 的类似问题:**

```php
// calcCompletionRate — 合并为单条聚合查询
public function calcCompletionRate(?int $projectId): float
{
    $result = Task::selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', ['done'])
        ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
        ->first();

    return $result->total > 0 ? round(($result->done / $result->total) * 100, 2) : 0;
}
```

---

### 3.3 Settings — testPush() 保存未保存更改后再测试

**文件:** `app/Livewire/Settings.php`
**行号:** 第 99-112 行

**当前代码:**

```php
public function testPush(SettingsService $settings): void
{
    $this->saveNotifications($settings);  // 先保存当前所有更改
    // ... 然后发送测试消息
}
```

**问题分析:** `testPush()` 在执行测试推送前调用了 `save()`。用户如果刚修改了表单但不想保存，点击"测试"按钮会**静默保存所有更改**，违反了最小意外原则。

**修复方案:**

```php
public function testPush(SettingsService $settings): void
{
    // 只读取当前表单值，不保存
    $webhookUrl = $this->feishuWebhookUrl;
    if (empty($webhookUrl)) {
        $this->addError('feishu_webhook_url', '请先填写 Webhook URL');
        return;
    }

    try {
        $response = Http::post($webhookUrl, [
            'msg_type' => 'text',
            'content' => ['text' => '🔔 配置测试消息 - 如果你看到此消息，配置正确！'],
        ]);

        if ($response->successful()) {
            $this->dispatch('notify', type: 'success', message: '飞书推送测试成功');
        } else {
            $this->addError('test', '推送失败：' . $response->body());
        }
    } catch (\Exception $e) {
        $this->addError('test', '推送异常：' . $e->getMessage());
    }
}
```

---

### 3.4 ReportController — buildReportData 与 ProjectReport 逻辑重复

**文件:**
- `app/Http/Controllers/ReportController.php`（第 18-66 行）
- `app/Livewire/ProjectReport.php`

**问题分析:** ReportController 的 `buildReportData()` 和 Livewire ProjectReport 的 `buildFilteredData()` 实现了几乎相同的数据收集和聚合逻辑。任何业务逻辑变更需要同步修改两个文件。

**修复方案:** 提取 `App\Services\ReportService`：

```php
// app/Services/ReportService.php
class ReportService
{
    public function __construct(
        protected MetricEngine $metricEngine,
        protected FeishuService $feishuService,
    ) {}

    public function buildReportData(Project $project): array
    {
        // 从 ReportController::buildReportData() 移植
        $tasks = $project->tasks()->get();
        $risks = $project->risks()->get();

        return [
            'project' => $project,
            'taskStats' => [
                'total' => $tasks->count(),
                'done' => $tasks->whereIn('status', Project::COMPLETED_STATUSES)->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'overdue' => $tasks->where('due_at', '<', now())->where('status', '!=', 'done')->count(),
            ],
            'riskStats' => [
                'total' => $risks->count(),
                'open' => $risks->where('status', 'open')->count(),
                'critical' => $risks->where('severity', 'critical')->count(),
                'high' => $risks->where('severity', 'high')->count(),
            ],
        ];
    }

    // 也包含推送方法
    public function pushToFeishu(string $slug): JsonResponse
    {
        $project = Project::where('slug', $slug)->firstOrFail();
        $data = $this->buildReportData($project);
        $this->feishuService->sendReportSummary($project);
        return ApiResponse::success(['message' => '已推送到飞书']);
    }
}
```

**Controller 和 Livewire 组件统一注入:**

```php
// ReportController.php
public function __construct(
    protected ReportService $reportService,
) {}

// ProjectReport.php
public function boot(ReportService $reportService): void
{
    $this->reportService = $reportService;
}
```

---

### 3.5 ReportCenter — 100+ 行内联渲染逻辑应提取服务

**文件:** `app/Livewire/ReportCenter.php`
**行号:** 第 172-240 行

**问题分析:** `renderMarkdown()` 和 `renderHtml()` 总计超过 100 行内联字符串拼接逻辑，不应该放在 Livewire 组件中。且 `renderHtml()` 使用 `nl2br(htmlspecialchars($md))` 无法正确渲染 Markdown 表格、标题、列表。

**修复方案:**

```php
// app/Services/ReportRenderer.php
use League\CommonMark\CommonMarkConverter;

class ReportRenderer
{
    protected CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function renderMarkdown(string $content): string
    {
        return $this->converter->convertToHtml($content);
    }

    public function renderHtmlDocument(string $content): string
    {
        $html = $this->renderMarkdown($content);
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica Neue', sans-serif; padding: 40px; }
        h1 { color: #1a1a2e; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; }
    </style>
</head>
<body>
    {$html}
</body>
</html>
HTML;
    }
}
```

```php
// ReportCenter.php 中改为
public function renderMarkdown(string $content): string
{
    return app(ReportRenderer::class)->renderMarkdown($content);
}

public function renderHtml(string $content): string
{
    return app(ReportRenderer::class)->renderHtmlDocument($content);
}
```

---

## 4. 🟡 性能修复

### 4.1 RiskManager — 8 条独立 COUNT 查询 ✅ 已完成

**状态:** 代码已合并为单条 `selectRaw` 聚合查询（见 `app/Livewire/RiskManager.php` 第 79-98 行），无需修改。

---

### 4.2 ProjectCompare — with() 预加载未正确使用 ✅ 已完成

**文件:** `app/Livewire/ProjectCompare.php`
**行号:** 第 85-118 行

**当前代码:**

```php
$allProjects = Project::withCount(['tasks', 'risks'])
    ->where('status', 'active')
    ->get();

// render() 中循环执行额外查询
foreach ($projects as $project) {
    $doneTasks = $project->tasks()->where('status', 'done')->count(); // N 条新查询！
    $taskStatus = $project->tasks()->selectRaw('status, count(*)...')->groupBy('status')->get(); // N 条
    $riskSeverity = $project->risks()->selectRaw('severity, count(*)...')->groupBy('severity')->get(); // N 条
}
```

**问题分析:** 虽然上方用 `withCount(['tasks', 'risks'])` 预加载了数量，但循环内使用了 `$project->tasks()`（方法调用，触发新查询！）而非 `$project->tasks`（属性访问，使用已加载集合）。导致 `withCount` 预加载完全失效。

**修复方案:**

```php
// 使用 with 加载全部关联，然后用集合操作
$allProjects = Project::with(['tasks', 'risks'])
    ->where('status', 'active')
    ->get();

// render() 中使用集合操作
foreach ($projects as $project) {
    $tasks = $project->tasks;       // 集合，无查询
    $risks = $project->risks;       // 集合，无查询

    $doneTasks = $tasks->where('status', 'done')->count();
    $taskStatus = $tasks->groupBy('status')->map->count();
    $riskSeverity = $risks->groupBy('severity')->map->count();
    $openRisks = $risks->where('status', 'open')->count();
}
```

---

### 4.3 GlobalSearch — 三表 LIKE %value% 全表扫描 ✅ 已完成

**文件:** `app/Livewire/GlobalSearch.php`
**行号:** 第 67-136 行

**当前代码:**

```php
public function performSearch(string $q): void
{
    // 三张表分别执行 LIKE %value% 查询
    $projects = Project::where('name', 'like', "%{$q}%")
        ->orWhere('description', 'like', "%{$q}%")
        ->take(5)->get();

    $tasks = Task::where('title', 'like', "%{$q}%")
        ->take(5)->get();

    $risks = Risk::where('title', 'like', "%{$q}%")
        ->take(5)->get();
}
```

**问题分析:** 前导通配符 `%` 导致无法使用索引，每次搜索都全表扫描。且没有 debounce 防抖，快速输入时每字符触发一次搜索。

**修复方案:**

```php
use Livewire\Attributes\Computed;

// 添加 debounce
#[Computed]
public function searchResults(): array
{
    $q = trim($this->query);

    // 短词/空词直接返回空
    if (strlen($q) < 2) {
        return ['projects' => [], 'tasks' => [], 'risks' => []];
    }

    // 使用 take(5) + 限制搜索字段数量
    return [
        'projects' => Project::where('name', 'like', "%{$q}%")
            ->take(5)->get(),
        'tasks' => Task::where('title', 'like', "%{$q}%")
            ->take(5)->get(),
        'risks' => Risk::where('title', 'like', "%{$q}%")
            ->take(5)->get(),
    ];
}
```

**长期方案 — 全文索引:**

```php
// MySQL FULLTEXT 索引
// migration:
// DB::statement('ALTER TABLE projects ADD FULLTEXT INDEX ft_name_desc (name, description)');

// 查询改用 MATCH AGAINST:
// Project::whereRaw('MATCH(name, description) AGAINST(? IN BOOLEAN MODE)', [$q])
```

---

### 4.4 ProjectReport — 无差别加载全部任务 ✅ 已完成

**文件:** `app/Livewire/ProjectReport.php`
**行号:** 第 30-70 行

**当前代码:**

```php
$allTasks = $project->tasks;            // 加载全部任务到内存
$tasks = $taskQuery->orderBy('due_at')->get();  // 然后又一次查询
```

**问题分析:** `$project->tasks` 加载了所有任务，如果项目有数千条任务，每次加载都消耗大量内存。如果后续只需聚合值，应该在 DB 层面计算。

**修复方案:**

```php
// 如果只需聚合值
$totalTasks = $project->tasks()->count();
$doneTasks = $project->tasks()->where('status', 'done')->count();

// 如果既需聚合又需列表
$tasks = $project->tasks()->orderBy('due_at')->get();
$totalTasks = $tasks->count();
$doneTasks = $tasks->where('status', 'done')->count();
```

---

### 4.5 MetricEngine — 事务过长风险 ✅ 已完成

**文件:** `app/Services/MetricEngine.php`
**行号:** 约第 48-75 行

**当前代码:**

```php
DB::transaction(function () use ($projects) {
    foreach ($projects as $project) {
        $this->computeForProject($project);  // 每项目含多条 SQL
    }
});
```

**问题分析:** 一个事务包含所有项目的所有指标计算。如果项目数超过 50-100，可能超过 `max_allowed_packet` 或 `innodb_lock_wait_timeout`，且一个指标失败会回滚之前所有已成功的计算。

**修复方案:**

```php
foreach ($projects as $project) {
    DB::transaction(function () use ($project) {
        $this->computeForProject($project);
    });
}
```

每项目单独事务，失败只回滚当前项目。如果 50 个项目中的第 25 个失败，前 24 个仍成功。

---

### 4.6 GitHubService — 分页同步 + 限速保护缺失 🟡 部分修复

**文件:** `app/Services/GitHubService.php`
**行号:** 第 82-123 行（fetch 方法），第 300-308 行（client），第 319-328 行（getPaginated）

**当前修复状态:**

| 子项 | 状态 | 说明 |
|------|------|------|
| `per_page` 参数 | ✅ 已完成 | 所有 fetch 方法已加 `per_page` 参数 (默认 20-30) |
| `fetchAllPages()` 多页合并 | ❌ 未实现 | `getPaginated()` 仅做单次请求 + json 转换，无 Link header 解析 |
| `checkRateLimit()` 限速保护 | ❌ 未实现 | `client()` 仅配置了 `timeout(15)` + token，无 `X-RateLimit-Remaining` 检查 |
| 429/403 Retry-After 处理 | ❌ 未实现 | 无重试中间件或等待逻辑 |
| 配置 `defaultBranch` | ❌ 未实现 | 当前 `fetchCommits` 的 `sha` 分支参数需要调用方传入 |

**问题分析:**
- `fetchCommits`、`fetchPullRequests`、`fetchIssues`、`fetchContributors` 只获取第一页数据（默认 30 条）
- `getPaginated()` 未解析 Link header，无下一页 URL 跟踪
- `client()` 无 429/403 限速检测，无 Retry-After 处理
- 上述问题若长期不修，当 GitHub 返回数据超过 1 页时，数据会不完整；当 API 限速到来时，会抛出未处理的 HTTP 异常

**修复方案:**

```php
// 1. 增加 per_page 参数
public function fetchCommits(string $branch = '', int $perPage = 100): array
{
    return $this->fetchAllPages("/repos/{$this->owner}/{$this->repo}/commits", [
        'sha' => $branch ?: $this->defaultBranch,
        'per_page' => $perPage,
    ]);
}

// 2. 实现分页获取所有数据
public function fetchAllPages(string $url, array $params = []): array
{
    $allItems = [];
    $page = 1;
    $maxPages = 10;  // 防止无限循环

    do {
        $params['page'] = $page;
        $response = $this->getPaginated($url, $params);

        if (empty($response)) break;

        $allItems = array_merge($allItems, $response);

        $page++;
    } while (count($response) >= ($params['per_page'] ?? 30) && $page <= $maxPages);

    return $allItems;
}

// 3. 限速保护
public function client(): Client
{
    $this->client ??= new Client([
        'headers' => [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github.v3+json',
        ],
    ]);
    return $this->client;
}

// 在每次 API 调用后检查限速
protected function checkRateLimit(ResponseInterface $response): void
{
    $remaining = (int) $response->getHeaderLine('X-RateLimit-Remaining');
    $reset = (int) $response->getHeaderLine('X-RateLimit-Reset');

    if ($remaining < 100) {
        $wait = max(1, $reset - time());
        Log::warning("GitHub API 限速剩余 {$remaining}，将在 {$wait} 秒后重置");
    }

    if ($remaining === 0) {
        $wait = max(1, $reset - time());
        throw new \RuntimeException("GitHub API 限速已用尽，请等待 {$wait} 秒后重试");
    }
}
```

---

## 5. 🟡 代码质量与防御性修复

### 5.1 RoleManager — 缺少类型提示

**文件:** `app/Livewire/RoleManager.php`
**行号:** 第 15、17、20、31、33、36、39 行

**当前代码:**

```php
public $roles;
public $permissions;
public $editingRole = null;
public $assigningRole = null;
public $assignUserId = '';
public $deletingRole = null;
public $users;
```

**修复方案:**

```php
use Illuminate\Support\Collection;

public Collection $roles;
public Collection $permissions;
public ?int $editingRole = null;
public ?int $assigningRole = null;
public string $assignUserId = '';
public ?int $deletingRole = null;
public Collection $users;
```

---

### 5.2 SlowQueryMonitor — DB::listen 递归防护

**文件:** `app/Services/SlowQueryMonitor.php`
**行号:** 约第 15-30 行

**当前代码:**

```php
DB::listen(function ($query) {
    if ($query->time >= 500) {
        // 执行 SlowQuery::create（另一条 INSERT 查询）
    }
});
```

**问题分析:** `DB::listen` 回调内部又执行了 `SlowQuery::create`，这会在 `DB::listen` 中递归触发。虽然用阈值 500ms 做了隐式防护（慢查询持久化通常不超过 500ms），但不安全。

**修复方案:**

```php
private static bool $listening = false;

DB::listen(function ($query) {
    if (self::$listening) return;  // 防止递归
    self::$listening = true;

    try {
        if ($query->time >= 500) {
            // 持久化慢查询
            SlowQuery::create([...]);
        }
    } finally {
        self::$listening = false;
    }
});
```

---

### 5.3 FeishuService — 重复 HTTP 调用逻辑

**文件:** `app/Services/FeishuService.php`
**行号:** 第 54-90 行（sendInteractiveCard），第 95-125 行（sendText）

**问题分析:** `sendInteractiveCard()` 和 `sendText()` 各自实现了完整的 HTTP POST 逻辑，后期增加第三种消息类型又会重复。

**修复方案:**

```php
// 提取公共方法
protected function sendHttpRequest(array $payload, string $logContext = ''): bool
{
    $url = $this->webhookUrl;
    if (empty($url)) {
        Log::warning("飞书消息发送失败：Webhook URL 未配置 [{$logContext}]");
        return false;
    }

    try {
        $response = Http::timeout(10)->post($url, $payload);
        if ($response->successful()) {
            $this->logPartitionInfo();
            return true;
        }
        Log::error("飞书消息发送失败 [{$logContext}]: " . $response->body());
        return false;
    } catch (\Exception $e) {
        Log::error("飞书消息异常 [{$logContext}]: " . $e->getMessage());
        return false;
    }
}

// sendInteractiveCard 简化为
public function sendInteractiveCard(array $card): bool
{
    return $this->sendHttpRequest(
        ['msg_type' => 'interactive', 'card' => $card],
        'interactive_card'
    );
}

// sendText 简化为
public function sendText(string $content): bool
{
    return $this->sendHttpRequest(
        ['msg_type' => 'text', 'content' => ['text' => $content]],
        'text'
    );
}
```

---

### 5.4 FeishuService — 单例化或缓存配置

**文件:** `app/Services/FeishuService.php`
**行号:** 第 25-34 行（构造函数）

**当前代码:**

```php
public function __construct(?array $settings = null)
{
    $settings ??= $this->loadSettings();  // 每次 new 都查 DB
    // ...
}
```

**修复方案 A — 服务提供者绑定单例:**

```php
// AppServiceProvider.php
$this->app->singleton(FeishuService::class);
```

**修复方案 B — 缓存 SettingsService 结果:**

```php
// FeishuService.php
protected function loadSettings(): array
{
    return Cache::remember('feishu_settings', 300, function () {
        return SettingsService::getGroup('notification');
    });
}

// 在 SettingsService 保存时清除缓存
// Settings.php save() 方法中：
Cache::forget('feishu_settings');
```

---

### 5.5 ExportPanel — download() 分发原始文件路径

**文件:** `app/Livewire/ExportPanel.php`
**行号:** 约第 175 行

**当前代码:**

```php
$this->dispatch('download-export', url: Storage::disk('local')->url($history->file_path));
```

**问题分析:** 将文件路径通过事件传递给前端 JS。如果 `file_path` 包含用户输入，存在路径遍历或信息泄露风险。

**修复方案:**

```php
// 生成临时签名 URL
$url = Storage::disk('local')->temporaryUrl($history->file_path, now()->addMinutes(5));
$this->dispatch('download-export', url: $url);
```

或直接通过浏览器下载：

```php
// 改为常规下载响应
public function download($historyId): BinaryFileResponse
{
    $history = ExportHistory::findOrFail($historyId);
    $path = Storage::disk('local')->path($history->file_path);

    if (!file_exists($path)) {
        abort(404, '导出文件已过期或不存在');
    }

    return response()->download($path, $history->original_name);
}
```

---

### 5.6 ResourceView — withSum 别名 + 40h 硬编码

**文件:** `app/Livewire/ResourceView.php`
**行号:** 约第 10-25 行

**当前代码:**

```php
Project::withSum(['tasks as estimate_sum' => fn($q) => ...], 'estimate');
// 以及代码中隐含的 40 小时/周硬编码
```

**修复方案:**

```php
// 1. 在 Project 模型 PHPDoc 中声明动态属性
/**
 * @property-read float $estimate_sum
 */

// 2. 将工作小时数提取为可配置项
// config/work.php
return [
    'hours_per_member' => env('WORK_HOURS_PER_MEMBER', 40),
];

// 使用：
$hoursPerMember = config('work.hours_per_member');
```

---

### 5.7 MetricEngine — 表达式安全加固

**文件:** `app/Services/MetricEngine.php`
**行号:** 第 218-271 行

**当前代码:**

```php
$sanitized = preg_replace('/[^0-9+\-*\/().,%\s{}_a-zA-Z]/', '', $expression);
// ...
$result = $language->evaluate($parsedExpr);
```

**问题分析:** 正则保留了 `_a-zA-Z` 和 `.`，在 Symfony ExpressionLanguage 中这些字符允许构造方法调用链。虽然只注册了 `mod()` 一个自定义函数，但 ExpressionLanguage 默认支持数组访问和属性访问。

**修复方案:**

```php
// 删除变量占位符后检查是否只包含安全的运算字符
$expressionWithoutVars = str_replace(array_keys($variables), '', $expression);

// 只允许纯数学公式（数字 + 运算符），不允许字母函数调用
if (!preg_match('/^[\d+\-*\/().,%\s]+$/', $expressionWithoutVars)) {
    throw new \RuntimeException('公式包含不安全的语法元素');
}
```

---

### 5.8 权限一致性检查

**文件:**
- `app/Http/Controllers/Api/TaskController.php`（第 76-81 行 — 缺少权限检查）
- `app/Http/Controllers/Api/BoardController.php`（缺少权限检查）
- `app/Http/Controllers/Api/RiskController.php`（缺少权限检查）

**问题分析:** `ProjectController::show()` 有明确的权限检查（第 95-102 行），但 Task、Risk 的 `show()` 缺少。与 ProjectController 行为不一致。

**修复方案:**

```php
// TaskController::show()
public function show(Request $request, Task $task): JsonResponse
{
    abort_unless($request->user()->canAccessProject($task->project), 403, '无权访问此任务');
    $task->load(['project', 'assignees', 'creator']);
    return ApiResponse::success($task);
}
```

---

### 5.9 报告推送硬编码实例化

**文件:** `app/Http/Controllers/ReportController.php`
**行号:** 第 147、173 行

**当前代码:**

```php
$service = new \App\Services\RiskEngine\FeishuAlertService;
```

**修复方案:** 参见 2.1 飞书双服务合并方案，统一改用 DI 注入的 `FeishuService`。

---

## 6. 💭 微调建议

### 6.1 MetricSnapshot 复合索引

**文件:** `app/Models/MetricSnapshot.php`（迁移文件推测）

**建议:** 在 migration 中添加复合索引
```php
$table->index(['metric_id', 'captured_at']);
```

---

### 6.2 Risk 模型 status 枚举约束

**文件:** `app/Models/Risk.php`

**建议:** 添加 PHP 8.1 Enum 或 Docrine DBAL 约束
```php
// 方式一: Laravel Enum 验证
'status' => 'open|acknowledged|closed'

// 方式二: Migration 层约束
DB::statement("ALTER TABLE risks ADD CONSTRAINT chk_status CHECK (status IN ('open', 'acknowledged', 'closed'))");
```

---

### 6.3 MetricController — 时间边界处理

**文件:** `app/Http/Controllers/Api/MetricController.php`
**行号:** 第 58 行

**当前代码:**

```php
$query->where('created_at', '<=', $request->date_to.' 23:59:59');
```

**推荐替换:**

```php
$query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
```

---

### 6.4 BoardController — 看板分页

**文件:** `app/Http/Controllers/Api/BoardController.php`
**行号:** 第 20-42 行

**推荐替换:**

```php
// 每列限制条数
$limit = $request->input('per_column', 20);
$columns = ['todo', 'in_progress', 'review', 'done', 'blocked'];

$board = collect($columns)->mapWithKeys(function ($status) use ($limit) {
    return [$status => Task::where('status', $status)->take($limit)->get()];
});
```

---

### 6.5 Export 子类列定义去重

**文件:** `app/Exports/ProjectExport.php` 等

**建议:** `defaultColumns()` 直接引用 `availableColumns()` 的子集
```php
public function defaultColumns(): array
{
    return array_intersect_key(
        $this->availableColumns(),
        array_flip(['name', 'status', 'progress'])
    );
}
```

---

### 6.6 SettingsService SENSITIVE_KEYS 自动发现

**文件:** `app/Services/SettingsService.php`

**建议:** 改为命名约定自动判断
```php
public static function isSensitive(string $key): bool
{
    return str_ends_with($key, '_secret')
        || str_ends_with($key, '_token')
        || str_ends_with($key, '_key');
}
```

---

### 6.7 IngestionGitHubEventJob 使用 Eloquent Model

**文件:** `app/Jobs/IngestGitHubEventJob.php`

**建议:** 将 `DB::table('events')` 替换为 `Event::query()`，保持项目风格一致。

---

### 6.8 RiskSuggestionService 返回结构优化

**文件:** `app/Services/RiskSuggestionService.php`

**建议:** 返回对象而非嵌套数组
```php
// 返回 DTO 或关联数组
return new SuggestionResult(
    text: '建议内容...',
    data: [...],
    priority: 'high'
);
```

---

### 6.9 TaskController — reorder N+1 批量更新

**文件:** `app/Http/Controllers/Api/TaskController.php`
**行号:** 第 151-153 行

**当前代码:**

```php
foreach ($validated['tasks'] as $item) {
    Task::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
}
```

**推荐方案:**

```php
// 使用 upsert 批量更新
Task::upsert($validated['tasks'], ['id'], ['sort_order']);
```

---

### 6.10 ProjectController — update() Slug 碰撞检测

**文件:** `app/Http/Controllers/Api/ProjectController.php`
**行号:** 第 123-125 行

**当前代码:**

```php
if (isset($validated['name'])) {
    $validated['slug'] = Str::slug($validated['name']);
}
```

**修复方案:**

```php
if (isset($validated['name'])) {
    $slug = Str::slug($validated['name']);
    $counter = 1;
    $originalSlug = $slug;
    while (Project::where('slug', $slug)->where('id', '!=', $project->id)->exists()) {
        $slug = $originalSlug.'-'.($counter++);
    }
    $validated['slug'] = $slug;
}
```

---

### 6.11 ProjectController — destroy() 关联数据保护

**文件:** `app/Http/Controllers/Api/ProjectController.php`
**行号:** 第 137-142 行

**当前代码:**

```php
public function destroy(Project $project): JsonResponse
{
    $project->delete();
    return ApiResponse::noContent('项目已删除');
}
```

**建议修复:**

```php
public function destroy(Request $request, Project $project): JsonResponse
{
    abort_unless($request->user()->canAccessProject($project), 403);

    if ($project->tasks()->exists() || $project->risks()->exists()) {
        return ApiResponse::error('项目有关联数据，无法直接删除。请先删除关联任务和风险。', 409);
    }

    $project->delete();
    return ApiResponse::noContent('项目已删除');
}
```

---

## 7. 修复顺序建议

| 优先级 | 修复项 | 预估工作量 | 风险 |
|--------|--------|-----------|------|
| **P0** | 1.1 IntegrationManager 凭据泄露 | 小（2-3 文件） | 🔴 安全 |
| **P0** | 1.2 Login 暴力破解 | 小（1 文件） | 🔴 安全 |
| **P0** | 1.3 GitHubWebhook Secret 空值 | 极小（1 行） | 🔴 安全 |
| **P0** | 1.4 ChartSvgService XSS 转义 | 小（1 文件） | 🔴 安全 |
| **P1** | 2.1 飞书双服务合并 | 中（3-5 文件） | 🟡 架构 |
| **P1** | 3.1 Project completionRate 不一致 | 小（1 文件） | 🟡 数据正确性 |
| **P1** | 3.2 MetricCollector 子查询重复 | 小（1 文件） | 🟡 性能 |
| **P1** | 3.3 Settings testPush 副作用 | 极小（1 方法） | 🟡 用户体验 |
| **P2** | 4.1 RiskManager 8 条 COUNT | 小（1 文件） | 🟡 性能 |
| **P2** | 4.2 ProjectCompare with() 预加载 | 小（1 文件） | 🟡 性能 |
| **P2** | 4.3 GlobalSearch LIKE %value% | 中（1 文件 + 索引） | 🟡 性能 |
| **P2** | 4.4 ProjectReport 全部加载 | 小（1 文件） | 🟡 性能 |
| **P2** | 4.5 MetricEngine 事务拆分 | 小（1 文件） | 🟡 性能 |
| **P2** | 4.6 GitHubService 分页+限速 | 中（1 文件） | 🟡 功能完整性 |
| **P3** | 3.4 ReportController 逻辑重复 | 中（提取 Service） | 🟡 可维护性 |
| **P3** | 3.5 ReportCenter 内联提取 | 中（提取 Service） | 🟡 可维护性 |
| **P3** | 5.1-5.10 代码质量修复 | 小-中 | 🟡 防御性 |
| **P4** | 6.1-6.11 微调建议 | 极/小 | 💭 锦上添花 |

---

## 总结

- **总修复项:** 2 🔴 + 30 🟡 + 15 💭 = **47 项**
- **预估总工作量:** 小（约 15-25 个文件修改，大部分改动量小）
- **高风险项:** P0 的 4 项安全修复应优先处理（2-3 小时可全部完成）
- **最高价值修复:** 飞书双服务合并 + completionRate 一致性修复，消除两个最隐蔽的 bug 源
- **建议执行方式:** 按 P0→P1→P2→P3→P4 顺序分批提交，每批独立测试
