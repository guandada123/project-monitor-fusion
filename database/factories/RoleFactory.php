<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->jobTitle(),
            'slug' => fake()->slug(1),
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }

    /**
     * Assign all existing permissions to this role.
     */
    public function withAllPermissions(): static
    {
        return $this->afterCreating(function (Role $role) {
            $ids = \App\Models\Permission::pluck('id');
            if ($ids->isNotEmpty()) {
                $role->permissions()->sync($ids);
            }
        });
    }

    /**
     * Assign specific permission slugs to this role.
     */
    public function withPermissions(array $slugs): static
    {
        return $this->afterCreating(function (Role $role) use ($slugs) {
            $ids = \App\Models\Permission::whereIn('slug', $slugs)->pluck('id');
            if ($ids->isNotEmpty()) {
                $role->permissions()->sync($ids);
            }
        });
    }
}
