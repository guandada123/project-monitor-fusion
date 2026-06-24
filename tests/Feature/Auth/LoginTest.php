<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('login page is accessible', function () {
    get('/login')
        ->assertStatus(200);
});

test('user can authenticate with valid credentials via livewire', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->call('login')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();
});

test('user cannot authenticate with invalid password', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    Livewire::test(Login::class)
        ->set('email', 'test@example.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('user can logout', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});

test('authenticated user with admin role can access dashboard', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    get('/')->assertStatus(200);
});

test('guest is redirected to login when accessing protected pages', function () {
    get('/projects')->assertRedirect('/login');
    get('/metrics')->assertRedirect('/login');
    get('/reports')->assertRedirect('/login');
    get('/resources')->assertRedirect('/login');
    get('/settings')->assertRedirect('/login');
    get('/integrations')->assertRedirect('/login');
    get('/roles')->assertRedirect('/login');
});
