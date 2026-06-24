<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $name = fake()->company().' '.fake()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'source' => fake()->randomElement(['manual', 'github', 'gitlab']),
            'external_id' => (string) fake()->unique()->randomNumber(8),
            'owner_id' => User::factory(),
            'status' => fake()->randomElement(['active', 'on_hold', 'completed', 'archived']),
            'description' => fake()->sentence(),
            'start_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'end_at' => fake()->dateTimeBetween('+1 month', '+6 months'),
            'meta' => ['type' => fake()->word(), 'priority' => fake()->randomElement(['low', 'medium', 'high'])],
            'tags' => fake()->randomElements(['frontend', 'backend', 'infra', 'mobile'], rand(1, 3)),
            'config' => ['kpi_enabled' => true, 'auto_sync' => fake()->boolean()],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'active']);
    }
}
