<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the admin role (all permissions).
     * Roles/permissions are auto-seeded via TestCase::setUp().
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $role = \App\Models\Role::where('slug', 'admin')->first();
            if ($role) {
                $user->roles()->attach($role);
                $user->clearPermissionCache();
            }
        });
    }

    /**
     * Assign the viewer role (read-only permissions).
     * Roles/permissions are auto-seeded via TestCase::setUp().
     */
    public function viewer(): static
    {
        return $this->afterCreating(function ($user) {
            $role = \App\Models\Role::where('slug', 'viewer')->first();
            if ($role) {
                $user->roles()->attach($role);
                $user->clearPermissionCache();
            }
        });
    }
}
