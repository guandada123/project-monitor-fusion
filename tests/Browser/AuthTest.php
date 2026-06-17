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
            ->assertSee('登录')
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
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
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
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->check('remember')
            ->press('登录')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

// ─────────────── Login with Invalid Credentials ───────────────

test('login with invalid credentials shows error', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->type('email', 'wrong@example.com')
            ->type('password', 'wrongpassword')
            ->press('登录')
            ->waitForText('邮箱或密码不正确')
            ->assertSee('邮箱或密码不正确')
            ->assertPathIs('/login');
    });
});

test('login with empty fields shows validation error', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->press('登录')
            ->waitForText('required')
            ->assertSee('required');
    });
});

// ─────────────── Logout ───────────────

test('user can log out', function () {
    $user = User::factory()->admin()->create([
        'email' => 'logout@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
            ->assertPathIs('/');

        // Click logout button (in the sidebar footer)
        $browser->click('button[title="登出"]')
            ->waitForLocation('/login')
            ->assertPathIs('/login');
    });
});

test('after logout user cannot access protected pages', function () {
    $user = User::factory()->admin()->create([
        'email' => 'logout2@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        // Login
        $browser->visit('/login')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('登录')
            ->waitForLocation('/')
            ->assertPathIs('/');

        // Logout
        $browser->click('button[title="登出"]')
            ->waitForLocation('/login')
            ->assertPathIs('/login');

        // Try to access dashboard
        $browser->visit('/')
            ->assertPathIs('/login');
    });
});
