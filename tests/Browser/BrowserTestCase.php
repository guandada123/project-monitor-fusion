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
     * Create a user, log them in by visiting the custom /dusk/login/{id} route,
     * and return the user.
     *
     * Uses the manually-defined /dusk/login/{userId} route (without underscore)
     * which is always available in web.php, unlike Dusk's built-in /_dusk/login/{id}
     * route which requires the Dusk service provider to be active on the app server.
     */
    protected function loginViaBrowser(Browser $browser, array $attrs = []): User
    {
        $user = User::factory()->admin()->create($attrs);

        $browser->visit('/dusk/login/' . $user->id)
            ->waitForLocation('/', 10)
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
