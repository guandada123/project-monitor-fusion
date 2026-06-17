<?php

use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Navigation ───────────────

test('sidebar navigation works between pages', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'nav@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
            ->assertPathIs('/');

        // Navigate through sidebar links
        $browser->clickLink('项目列表')
            ->waitForLocation('/projects')
            ->assertPathIs('/projects');

        $browser->clickLink('看板')
            ->waitForLocation('/board')
            ->assertPathIs('/board');

        $browser->clickLink('风险中心')
            ->waitForLocation('/risks')
            ->assertPathIs('/risks');

        $browser->clickLink('仪表板')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

// ─────────────── Global Search ───────────────

test('global search input is present on dashboard', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'search@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // Global search should have an input field
        $browser->assertPresent('input[type="search"], input[placeholder*="搜索"], input[placeholder*="Search"]');
    });
});

// ─────────────── Theme Toggle ───────────────

test('theme toggle buttons are present', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'theme@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // Theme toggle buttons should be present in the header
        $browser->assertPresent('button[title="浅色模式"]')
            ->assertPresent('button[title="深色模式"]')
            ->assertPresent('button[title="跟随系统"]');
    });
});

test('theme toggle switches appearance', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'theme2@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // Click dark mode button
        $browser->click('button[title="深色模式"]')
            ->assertHasClass('html', 'dark');
    });
});

// ─────────────── Notifications Panel ───────────────

test('notification icon is present in header', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'notif@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // Notification bell/icon should be present
        $browser->assertPresent('[wire\\:key*="notification"], .notification-badge, svg[class*="bell"], [aria-label*="通知"]');
    });
});

// ─────────────── Export Panel ───────────────

test('export button is present on dashboard', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'export@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // The export FAB button should be visible at bottom-right
        $browser->assertPresent('[wire\\:key="export-panel"], button:contains("导出"), [class*="export"]');
    });
});

// ─────────────── Responsive Design (Mobile Menu) ───────────────

test('mobile menu toggle is present on small screens', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'mobile@example.com',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // The mobile menu button should be present (hamburger icon)
        // It's inside the lg:hidden mobile header
        $browser->assertPresent('button[x-data*="mobileOpen"], button[x-data*="mobile"]');
    });
});

// ─────────────── User Profile ───────────────

test('user name and email are shown in sidebar', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'email' => 'profile@example.com',
            'name' => '测试用户',
            'password' => bcrypt('password'),
        ]);

        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/');

        // User info should appear in sidebar footer
        $browser->assertSee('测试用户')
            ->assertSee('profile@example.com');
    });
});
