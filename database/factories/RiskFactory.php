<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Risk;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiskFactory extends Factory
{
    protected $model = Risk::class;

    public function definition(): array
    {
        $severity = fake()->randomElement(['low', 'medium', 'high', 'critical']);

        return [
            'project_id' => Project::factory(),
            'severity' => $severity,
            'rule_key' => fake()->randomElement(['overdue_tasks', 'low_coverage', 'high_defect_rate', 'schedule_slip']),
            'title' => fake()->sentence(5),
            'detail' => fake()->paragraph(),
            'evidence' => ['links' => [fake()->url()], 'data_points' => [fake()->randomNumber(2)]],
            'status' => fake()->randomElement(['open', 'acknowledged', 'closed']),
            'first_seen_at' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'last_seen_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'occurrence_count' => fake()->numberBetween(1, 10),
            'meta' => ['source' => fake()->randomElement(['auto', 'manual'])],
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'open']);
    }
}
