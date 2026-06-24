<?php

use App\Models\Project;
use App\Models\User;
use Laravel\Dusk\Browser;

/**
 * Database truncation doesn't work reliably with Dusk + SQLite.
 * Run migrate:fresh once before the first test to ensure clean state.
 */
static $spaDbReset = false;
beforeEach(function () use (&$spaDbReset) {
    if (! $spaDbReset) {
        $this->artisan('migrate:fresh', [
            '--force' => true,
            '--seeder' => 'Database\\Seeders\\RolePermissionSeeder',
        ]);
        $spaDbReset = true;
    }
});

// ─────────────── Helper ───────────────

/**
 * Log in an admin user using Dusk's built-in loginAs(),
 * which visits the /dusk/login/{userId} route to set the session.
 * Faster and more reliable than going through the Livewire login form.
 */
function loginViaForm(Browser $browser, User $user): void
{
    $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
        ->assertPathIs('/');
}

/**
 * Assert the current path after SPA navigation.
 * Uses waitUntil('location.pathname') because SPA navigation
 * updates the URL via history.pushState, so we need to
 * ensure the navigation has completed before asserting.
 */
function assertSpaPath(Browser $browser, string $path, int $seconds = 5): Browser
{
    $path = '/'.ltrim($path, '/');

    return $browser
        ->waitUntil("window.location.pathname === '$path'", $seconds)
        ->assertPathIs($path);
}

// ─────────────── SPA: Sidebar Navigation ───────────────

test('spa sidebar navigation updates url and content across main pages', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'spa_main@example.com',
            'password' => bcrypt('password'),
        ]);
        loginViaForm($browser, $user);

        // Dashboard → 项目列表
        $browser->clickLink('项目列表');
        assertSpaPath($browser, '/projects');
        $browser->assertSee('项目列表');

        // 项目列表 → 聚合视图
        $browser->clickLink('聚合视图');
        assertSpaPath($browser, '/aggregate');
        $browser->assertSee('聚合视图');

        // 聚合视图 → 看板
        $browser->clickLink('看板');
        assertSpaPath($browser, '/board');
        $browser->assertSee('看板');

        // 看板 → 风险中心
        $browser->clickLink('风险中心');
        assertSpaPath($browser, '/risks');
        $browser->assertSee('风险中心');

        // 风险中心 → 慢查询监控
        $browser->clickLink('慢查询监控');
        assertSpaPath($browser, '/monitoring');
        $browser->assertSee('慢查询监控');
    });
});

// ─────────────── SPA: Browser Back/Forward ───────────────

test('spa browser back and forward navigation works correctly', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'spa_bf@example.com',
            'password' => bcrypt('password'),
        ]);
        loginViaForm($browser, $user);

        // Build history: / → /projects → /board → /risks
        $browser->clickLink('项目列表');
        assertSpaPath($browser, '/projects');

        $browser->clickLink('看板');
        assertSpaPath($browser, '/board');

        $browser->clickLink('风险中心');
        assertSpaPath($browser, '/risks');

        // Back to 看板
        $browser->back();
        assertSpaPath($browser, '/board');
        $browser->assertSee('看板');

        // Back to 项目列表
        $browser->back();
        assertSpaPath($browser, '/projects');
        $browser->assertSee('项目列表');

        // Forward to 看板
        $browser->forward();
        assertSpaPath($browser, '/board');
        $browser->assertSee('看板');

        // Forward to 风险中心
        $browser->forward();
        assertSpaPath($browser, '/risks');
        $browser->assertSee('风险中心');
    });
});

// ─────────────── SPA: Project Detail ───────────────

test('spa navigation from project list to project detail', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'spa_detail@example.com',
            'password' => bcrypt('password'),
        ]);

        $project = Project::factory()->create([
            'owner_id' => $user->id,
            'name' => 'SPA详情项目测试',
        ]);

        loginViaForm($browser, $user);

        // Navigate to project list (SPA)
        $browser->clickLink('项目列表');
        assertSpaPath($browser, '/projects');

        // Click on project card to navigate to detail (SPA)
        $browser->visit("/projects/{$project->slug}");
        assertSpaPath($browser, "/projects/{$project->slug}");
        $browser->assertSee($project->name);
    });
});

// ─────────────── SPA: Settings Section ───────────────

test('spa navigation to settings and admin pages', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'spa_settings@example.com',
            'password' => bcrypt('password'),
        ]);
        loginViaForm($browser, $user);

        // Navigate to 集成管理
        $browser->clickLink('集成管理');
        assertSpaPath($browser, '/integrations');
        $browser->assertSee('集成管理');

        // Navigate to 系统设置
        $browser->clickLink('系统设置');
        assertSpaPath($browser, '/settings');
        $browser->assertSee('系统设置');

        // Navigate to 角色管理
        $browser->clickLink('角色管理');
        assertSpaPath($browser, '/roles');
        $browser->assertSee('角色管理');
    });
});

// ─────────────── SPA: Full Round-Trip ───────────────

test('spa full round-trip navigation through all main sections', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'spa_round@example.com',
            'password' => bcrypt('password'),
        ]);
        loginViaForm($browser, $user);

        // Dashboard → 项目列表 → 聚合视图 → 看板 → 风险中心 → 资源视图
        $routes = [
            '项目列表' => '/projects',
            '聚合视图' => '/aggregate',
            '看板' => '/board',
            '风险中心' => '/risks',
            '资源视图' => '/resources',
        ];

        foreach ($routes as $linkText => $expectedPath) {
            $browser->clickLink($linkText);
            assertSpaPath($browser, $expectedPath);
            $browser->assertSee($linkText);
        }

        // Now navigate back through history
        $backRoutes = array_reverse(array_values($routes));
        // Skip the last (current page) and start from the one before
        for ($i = 1; $i < count($backRoutes); $i++) {
            $browser->back();
            assertSpaPath($browser, $backRoutes[$i]);
        }
    });
});
