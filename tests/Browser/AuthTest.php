<?php

use App\Models\User;
use Laravel\Dusk\Browser;

// ─────────────── Unauthenticated Redirect ───────────────

test('unauthenticated user is redirected to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/')
            ->assertPathIs('/login');
    });
});

test('unauthenticated user sees login page on any protected route', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/projects')
            ->assertPathIs('/login')
            ->visit('/board')
            ->assertPathIs('/login')
            ->visit('/risks')
            ->assertPathIs('/login');
    });
});

// ─────────────── Login Page ───────────────

test('login page displays form elements', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitForText('登录', 10)
            ->assertPresent('input[type="email"]')
            ->assertPresent('input[type="password"]')
            ->assertPresent('button[type="submit"]');
    });
});

test('login page has correct title', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertTitleContains('登录');
    });
});

// ─────────────── Login with Valid Credentials ───────────────

test('user can log in with valid credentials', function () {
    $user = User::factory()->admin()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        // First visit / which redirects to login, setting intended URL to /
        $browser->visit('/')
            ->assertPathIs('/login')
            ->waitFor('input[name="email"]', 10)
            // 等 Livewire 完成首屏 hydrate：否则 wire:submit 尚未注册，
            // 点击提交会落到原生提交（停在 /login）→ 偶发 30s 超时。
            // 等 Livewire 完成首屏 hydrate（官方 Dusk 宏未注册，直接用同一信号）：
            // 否则 wire:submit 尚未注册，点击会落到原生提交（停在 /login）
            ->waitUntil('typeof window.Livewire !== "undefined" && window.Livewire.initialRenderIsFinished === true', 15)
            ->type('email', $user->email)
            ->type('password', 'password')
            ->click('button[type="submit"]')
            ->waitForLocation('/', 30)
            ->assertPathIs('/')
            ->assertSee('仪表板');
    });
});

test('login remembers the user with remember me', function () {
    $user = User::factory()->admin()->create([
        'email' => 'remember@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        // Log out first to ensure clean session (Dusk reuses browser across tests)
        $browser->logout()
            ->visit('/')
            ->assertPathIs('/login')
            ->waitFor('input[name="email"]', 10)
            // 等 Livewire 完成首屏 hydrate：否则 wire:submit 尚未注册，
            // 点击提交会落到原生提交（停在 /login）→ 偶发 30s 超时。
            // 等 Livewire 完成首屏 hydrate（官方 Dusk 宏未注册，直接用同一信号）：
            // 否则 wire:submit 尚未注册，点击会落到原生提交（停在 /login）
            ->waitUntil('typeof window.Livewire !== "undefined" && window.Livewire.initialRenderIsFinished === true', 15)
            ->type('email', $user->email)
            ->type('password', 'password')
            ->check('input[wire\\:model="remember"]')
            // 2026-10-31：勾选 remember 会触发一次 Livewire 请求（更新 $remember 并重新渲染），
            // 紧接着的 click 若落在这轮渲染替换 DOM 的间隙里就会点空 → 表单从未提交
            // → 停在 /login → waitForLocation 超时。
            // 这解释了为什么只有本用例 flaky：其他登录用例不勾 remember，没有这次额外请求。
            //
            // 注意：**不要**用 `[wire\:loading] 元素数为 0` 做"空闲"判据 ——
            // wire:loading 是常驻属性（靠 CSS 控显隐），查询永远非空，会一直等到超时。
            // 改用固定缓冲等这轮请求落地，简单且可预期。
            ->pause(1200)
            ->click('button[type="submit"]')
            ->waitForLocation('/', 30)
            ->assertPathIs('/');
    });
});

// ─────────────── Login with Invalid Credentials ───────────────

test('login with invalid credentials shows error', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('input[name="email"]', 10)
            // 等 Livewire 完成首屏 hydrate（官方 Dusk 宏未注册，直接用同一信号）：
            // 否则 wire:submit 尚未注册，点击会落到原生提交（停在 /login）
            ->waitUntil('typeof window.Livewire !== "undefined" && window.Livewire.initialRenderIsFinished === true', 15)
            ->type('email', 'wrong@example.com')
            ->type('password', 'wrongpassword')
            ->click('button[type="submit"]')
            ->pause(3000)
            // After failed login, should still be on login page
            ->assertPathIs('/login')
            // The error message should appear somewhere
            ->assertPresent('.text-red-500, [style*="color"], .animate-fade-in');
    });
});

test('login with empty fields shows validation error', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('button[type="submit"]', 10)
            // 等 Livewire 完成首屏 hydrate（官方 Dusk 宏未注册，直接用同一信号）：
            // 否则 wire:submit 尚未注册，点击会落到原生提交（停在 /login）
            ->waitUntil('typeof window.Livewire !== "undefined" && window.Livewire.initialRenderIsFinished === true', 15)
            ->click('button[type="submit"]')
            ->pause(3000)
            // After failed validation, should still be on login page
            ->assertPathIs('/login');
    });
});

// ─────────────── Logout ───────────────

test('user can log out', function () {
    $user = User::factory()->admin()->create();

    $this->browse(function (Browser $browser) use ($user) {
        // Login via loginAs
        $browser->loginAs($user)
            ->visit('/')
            ->assertPathIs('/')
            ->assertSee('仪表板');

        // Submit the logout form directly (more reliable than clicking)
        $browser->script(
            "document.querySelector('form[action*=\"logout\"]').submit();"
        );
        $browser->waitForLocation('/login', 10)
            ->assertPathIs('/login');
    });
});

test('after logout user cannot access protected pages', function () {
    $user = User::factory()->admin()->create();

    $this->browse(function (Browser $browser) use ($user) {
        // Login via loginAs
        $browser->loginAs($user)
            ->visit('/')
            ->assertPathIs('/')
            ->assertSee('仪表板');

        // Submit the logout form via JavaScript
        $browser->script(
            "document.querySelector('form[action*=\"logout\"]').submit();"
        );
        $browser->waitForLocation('/login', 10)
            ->assertPathIs('/login');

        // Try to access dashboard
        $browser->visit('/')
            ->assertPathIs('/login');
    });
});
