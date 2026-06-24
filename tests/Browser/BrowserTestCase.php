<?php

namespace Tests\Browser;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\DuskTestCase;

abstract class BrowserTestCase extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * The seeder to run after each database truncation/migration.
     * Ensures roles and permissions are always available for browser tests.
     */
    protected $seeder = \Database\Seeders\RolePermissionSeeder::class;

    /**
     * Create a user, log them in via Dusk's built-in loginAs(),
     * and return the user.
     *
     * loginAs() uses the /dusk/login/{userId} route which is much
     * more reliable than going through the Livewire login form.
     */
    protected function loginViaBrowser(\Laravel\Dusk\Browser $browser, array $attrs = []): \App\Models\User
    {
        $user = \App\Models\User::factory()->admin()->create($attrs);

        $browser->loginAs($user)
            ->visit('/')
            ->assertPathIs('/');

        return $user;
    }

    /**
     * Helper: create and return an admin user (already persisted, not logged in via browser).
     */
    protected function createAdminUser(): \App\Models\User
    {
        return \App\Models\User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'name' => '管理员',
        ]);
    }
}
