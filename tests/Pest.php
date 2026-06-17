<?php

uses(
    Tests\DuskTestCase::class,
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
    Tests\TestCase::class,
    Illuminate\Foundation\Testing\RefreshDatabase::class,
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

function createUser(?string $roleSlug = null, array $attrs = []): \App\Models\User
{
    $user = \App\Models\User::factory()->create($attrs);

    if ($roleSlug && class_exists(\App\Models\Role::class)) {
        $role = \App\Models\Role::where('slug', $roleSlug)->first();
        if (! $role) {
            $role = \App\Models\Role::factory()->create(['slug' => $roleSlug, 'name' => ucfirst($roleSlug)]);
        }
        $user->roles()->attach($role);
        $user->clearPermissionCache();
    }

    return $user;
}

function actingAsUser(?string $roleSlug = null, array $attrs = []): \App\Models\User
{
    $user = createUser($roleSlug, $attrs);
    test()->actingAs($user);
    return $user;
}
