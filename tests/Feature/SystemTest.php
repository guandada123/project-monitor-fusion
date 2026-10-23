<?php

use App\Models\Project;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

// ─── Resources ───

test('resources page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/resources')
        ->assertStatus(200)
        ->assertSeeLivewire('resource-view');
});

// ─── Settings ───

test('settings page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/settings')
        ->assertStatus(200)
        ->assertSeeLivewire('settings');
});

test('settings shows masked webhook url', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // Pre-set a webhook URL in settings
    $settings = app(SettingsService::class);
    $settings->set('feishu_webhook_url', 'https://open.feishu.cn/open-apis/bot/v2/hook/abc123def456', 'notification');

    Livewire::test('settings')
        ->assertSet('editingWebhook', false)
        ->assertSee('hook/****f456');
});

test('settings reveal starts from blank and cancel discards input', function () {
    // 安全契约（2026-10-24）：已存 webhook 明文绝不回填 public 属性
    // （会随 Livewire 快照下发浏览器）。编辑态从空白开始，留空保存=保留原值。
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $settings = app(SettingsService::class);
    $settings->set('feishu_webhook_url', 'https://open.feishu.cn/open-apis/bot/v2/hook/abc123def456', 'notification');

    Livewire::test('settings')
        ->assertSet('editingWebhook', false)
        ->assertSet('feishuWebhookUrl', '')
        ->assertSet('hasStoredWebhook', true)
        ->call('revealWebhook')
        ->assertSet('editingWebhook', true)
        ->assertSet('feishuWebhookUrl', '')
        ->set('feishuWebhookUrl', 'https://open.feishu.cn/open-apis/bot/v2/hook/changed789')
        ->call('cancelWebhookEdit')
        ->assertSet('editingWebhook', false)
        ->assertSet('feishuWebhookUrl', '');
});

test('settings never leaks stored webhook into rendered html', function () {
    // 核心安全回归：真实 URL 不得出现在页面输出（含 Livewire 快照）
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // token 用易数的值：last4 = 1234，掩码应为 hook/****1234
    $secretUrl = 'https://open.feishu.cn/open-apis/bot/v2/hook/SECRET1234';
    app(SettingsService::class)->set('feishu_webhook_url', $secretUrl, 'notification');

    Livewire::test('settings')
        ->assertDontSee('SECRET1234')
        ->assertSee('hook/****1234');
});

test('saving empty webhook input keeps the stored value', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $settings = app(SettingsService::class);
    $settings->set('feishu_webhook_url', 'https://open.feishu.cn/open-apis/bot/v2/hook/keep123', 'notification');

    Livewire::test('settings')
        ->call('revealWebhook')
        ->set('feishuWebhookUrl', '')
        ->call('saveNotifications')
        ->assertSet('feishuWebhookUrl', '')
        ->assertSet('hasStoredWebhook', true);

    expect(app(SettingsService::class)->getString('feishu_webhook_url', ''))
        ->toBe('https://open.feishu.cn/open-apis/bot/v2/hook/keep123');
});

test('clear then save removes the stored webhook', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $settings = app(SettingsService::class);
    $settings->set('feishu_webhook_url', 'https://open.feishu.cn/open-apis/bot/v2/hook/remove456', 'notification');

    Livewire::test('settings')
        ->call('revealWebhook')
        ->call('clearStoredWebhook')
        ->assertSet('clearWebhook', true)
        ->assertSet('hasStoredWebhook', true)
        ->call('saveNotifications')
        ->assertSet('clearWebhook', false)
        ->assertSet('hasStoredWebhook', false);

    expect(app(SettingsService::class)->getString('feishu_webhook_url', ''))->toBe('');
});

test('settings masked url property derives from stored value', function () {
    // 掩码由服务端从存储值计算（不依赖 public 属性，避免明文进快照）
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    app(SettingsService::class)->set(
        'feishu_webhook_url',
        'https://open.feishu.cn/open-apis/bot/v2/hook/xyz789',
        'notification'
    );

    Livewire::test('settings')
        ->assertSet('editingWebhook', false)
        ->assertSet('feishuWebhookUrl', '')
        ->assertSet('maskedUrl', 'https://open.feishu.cn/open-apis/bot/v2/hook/****z789');
});

test('settings editing closes after save', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Livewire::test('settings')
        ->call('revealWebhook')
        ->assertSet('editingWebhook', true)
        ->set('feishuWebhookUrl', 'https://open.feishu.cn/open-apis/bot/v2/hook/newhook123')
        ->set('feishuDailyTime', '08:30')
        ->set('feishuPartitionTag', '[项目监控]')
        ->set('feishuBotName', 'Project Monitor')
        ->call('saveNotifications')
        ->assertSet('editingWebhook', false);
});

// ─── Integrations ───

test('integrations page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/integrations')
        ->assertStatus(200)
        ->assertSeeLivewire('integration-manager');
});

// ─── Roles ───

test('roles page loads', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/roles')
        ->assertStatus(200)
        ->assertSeeLivewire('role-manager');
});

// ─── Global Search ───

test('global search renders on dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/')
        ->assertStatus(200)
        ->assertSeeLivewire('global-search');
});

test('global search searches across models', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // Create some searchable data
    Project::factory()->create(['name' => 'Searchable Test Project', 'slug' => 'searchable-test']);

    Livewire::test('global-search')
        ->set('query', 'Searchable')
        ->assertSet('show', true)
        ->assertCount('results', 1);
});

test('global search empty state', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Livewire::test('global-search')
        ->set('query', '___nonexistent___')
        ->assertSet('show', false);
});

test('global search keyboard navigation', function () {
    Project::factory()->create(['name' => 'Alpha Project']);
    Project::factory()->create(['name' => 'Beta Project']);

    Livewire::test('global-search')
        ->set('query', 'Project')
        ->assertSet('selectedIndex', 0)
        ->call('selectNext')
        ->assertSet('selectedIndex', 1)
        ->call('selectPrev')
        ->assertSet('selectedIndex', 0);
});

test('global search resets properly', function () {
    Livewire::test('global-search')
        ->set('query', 'test')
        ->call('resetSearch')
        ->assertSet('query', '')
        ->assertSet('show', false)
        ->assertSet('results', []);
});

// ─── Notifications Panel ───

test('notifications panel renders on dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/')
        ->assertStatus(200)
        ->assertSeeLivewire('notifications-panel');
});

test('notifications panel shows unread count', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // Create a notification for the user
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\Notifications\RiskAlertNotification',
        'data' => ['message' => 'Test notification', 'type' => 'risk_alert', 'icon' => 'alert-triangle', 'url' => '/risks'],
    ]);

    Livewire::test('notifications-panel')
        ->assertSet('unreadCount', 1);
});

test('notifications panel mark all as read', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    // Create 3 notifications
    foreach (range(1, 3) as $i) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\RiskAlertNotification',
            'data' => ['message' => "Test {$i}", 'type' => 'risk_alert', 'icon' => 'alert-triangle', 'url' => '/risks'],
        ]);
    }

    Livewire::test('notifications-panel')
        ->assertSet('unreadCount', 3)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);
});

test('notifications panel empty state', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    Livewire::test('notifications-panel')
        ->assertSet('unreadCount', 0);
});
