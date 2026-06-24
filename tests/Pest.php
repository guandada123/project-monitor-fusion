<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\DuskTestCase;
use Tests\TestCase;

uses(
    DuskTestCase::class,
    // Illuminate\Foundation\Testing\DatabaseMigrations::class,
)->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you return to your test functions provides a "fresh" Laravel
| application instance each test run, with the application container
| already configured for testing.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're ready to start using Expectations, write something like
| this example, or delete this file entirely.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing
| code specific to your project that you don't want to keep repeating.
| Feel free to create your own functions here to DRY up your tests.
|
*/

function createUser(?string $roleSlug = null, array $attrs = []): User
{
    $user = User::factory()->create($attrs);

    if ($roleSlug && class_exists(Role::class)) {
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role) {
            $role = Role::factory()->create(['slug' => $roleSlug, 'name' => ucfirst($roleSlug)]);
        }
        $user->roles()->attach($role);
        $user->clearPermissionCache();
    }

    return $user;
}

function actingAsUser(?string $roleSlug = null, array $attrs = []): User
{
    $user = createUser($roleSlug, $attrs);
    test()->actingAs($user);

    return $user;
}
