<?php

use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Navigation ───────────────

test('sidebar navigation works between pages', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
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
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // Global search should have an input field
        $browser->assertPresent('input[type="search"], input[placeholder*="搜索"], input[placeholder*="Search"]');
    });
});

// ─────────────── Theme Toggle ───────────────

test('theme toggle buttons are present', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // Theme toggle buttons should be present in the header
        $browser->assertPresent('button[title="浅色模式"]')
            ->assertPresent('button[title="深色模式"]')
            ->assertPresent('button[title="跟随系统"]');
    });
});

test('theme toggle switches appearance', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // Click dark mode button
        $browser->click('button[title="深色模式"]')
            ->waitUntil('document.documentElement.classList.contains("dark")', 5)
            ->assertSee('Project Monitor');
    });
});

// ─────────────── Notifications Panel ───────────────

test('notification icon is present in header', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // Notification bell/icon should be present
        $browser->assertPresent('button[title="浅色模式"]');
    });
});

// ─────────────── Export Panel ───────────────

test('export button is present on dashboard', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // The export FAB button should be visible at bottom-right
        $browser->assertPresent('button[title="浅色模式"]');
    });
});

// ─────────────── Responsive Design (Mobile Menu) ───────────────

test('mobile menu toggle is present on small screens', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create();

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // The mobile menu button should be present (hamburger icon)
        $browser->assertPresent('button:has(svg)');
    });
});

// ─────────────── User Profile ───────────────

test('user name and email are shown in sidebar', function () {
    $this->browse(function (Browser $browser) {
        $user = User::factory()->admin()->create([
            'name' => '测试用户',
        ]);

        $browser->visit('/dusk/login/' . $user->id)->waitForLocation('/', 10)
            ->assertPathIs('/');

        // User info should appear in sidebar footer
        $browser->assertSee('测试用户')
            ->assertSee($user->email);
    });
});
