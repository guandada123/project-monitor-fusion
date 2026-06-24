<?php

namespace Tests\Browser;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

abstract class BrowserTestCase extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * The seeder to run after each database truncation/migration.
     * Ensures roles and permissions are always available for browser tests.
     */
    protected $seeder = RolePermissionSeeder::class;

    /**
     * Create a user, log them in via Dusk's built-in loginAs(),
     * and return the user.
     *
     * loginAs() uses the /dusk/login/{userId} route which is much
     * more reliable than going through the Livewire login form.
     */
    protected function loginViaBrowser(Browser $browser, array $attrs = []): User
    {
        $user = User::factory()->admin()->create($attrs);

        $browser->loginAs($user)
            ->visit('/')
            ->assertPathIs('/');

        return $user;
    }

    /**
     * Helper: create and return an admin user (already persisted, not logged in via browser).
     */
    protected function createAdminUser(): User
    {
        return User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'name' => '管理员',
        ]);
    }
}
