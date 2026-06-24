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
        $status = fake()->randomElement(['open', 'acknowledged', 'closed']);
        $acknowledgedAt = $status === 'acknowledged'
            ? fake()->dateTimeBetween('-1 month', '-1 day')
            : null;
        $closedAt = $status === 'closed'
            ? fake()->dateTimeBetween('-1 month', '-1 day')
            : null;
        $suggestion = fake()->boolean(30)
            ? fake()->sentence()
            : null;
        $suggestionData = $suggestion && fake()->boolean(70)
            ? [
                'steps' => [fake()->sentence(6), fake()->sentence(6)],
                'resources' => [fake()->url()],
                'expected_impact' => fake()->randomElement(['降低 30%', '减少 50%', '消除风险']),
            ]
            : null;
        $suggestionAppliedAt = $suggestion && fake()->boolean(30)
            ? fake()->dateTimeBetween('-1 week', 'now')
            : null;

        return [
            'project_id' => Project::factory(),
            'severity' => $severity,
            'rule_key' => fake()->randomElement(['overdue_tasks', 'low_coverage', 'high_defect_rate', 'schedule_slip']),
            'title' => fake()->sentence(5),
            'detail' => fake()->paragraph(),
            'evidence' => ['links' => [fake()->url()], 'data_points' => [fake()->randomNumber(2)]],
            'status' => $status,
            'first_seen_at' => fake()->dateTimeBetween('-2 months', '-1 week'),
            'last_seen_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'acknowledged_at' => $acknowledgedAt,
            'closed_at' => $closedAt,
            'occurrence_count' => fake()->numberBetween(1, 10),
            'meta' => ['source' => fake()->randomElement(['auto', 'manual'])],
            'suggestion' => $suggestion,
            'suggestion_data' => $suggestionData,
            'suggestion_applied_at' => $suggestionAppliedAt,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attrs) => ['status' => 'open']);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'acknowledged',
            'acknowledged_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
            'closed_at' => null,
        ]);
    }
}
