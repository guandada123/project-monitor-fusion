<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricFactory extends Factory
{
    protected $model = \App\Models\Metric::class;

    public function definition(): array
    {
        $key = fake()->unique()->word();
        return [
            'project_id' => Project::factory(),
            'key' => $key,
            'name' => ucfirst($key),
            'kind' => fake()->randomElement(['system', 'custom']),
            'definition' => ['type' => 'count', 'field' => 'tasks', 'filter' => ['status' => 'done']],
            'dimensions' => ['project_id', 'status'],
            'filters' => null,
            'schedule_cron' => fake()->randomElement(['0 */6 * * *', '0 0 * * *', '*/30 * * * *']),
            'last_computed_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'current_value' => fake()->randomFloat(2, 0, 100),
            'current_json_value' => ['breakdown' => ['frontend' => fake()->numberBetween(5, 30), 'backend' => fake()->numberBetween(5, 30)]],
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attrs) => ['kind' => 'system']);
    }
}
