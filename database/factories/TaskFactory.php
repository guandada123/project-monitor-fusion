<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'external_id' => fake()->boolean(80) ? (string) fake()->unique()->randomNumber(8) : null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'assignee_id' => fake()->boolean(80) ? User::factory() : null,
            'status' => fake()->randomElement(['todo', 'in_progress', 'review', 'blocked', 'done']),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'estimate' => fake()->randomFloat(2, 1, 40),
            'spent' => fake()->randomFloat(2, 0.5, 30),
            'start_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'due_at' => fake()->dateTimeBetween('-1 week', '+2 weeks'),
            'completed_at' => null,
            'tags' => fake()->randomElements(['bug', 'feature', 'tech-debt', 'docs'], rand(0, 3)),
            'meta' => ['sprint' => fake()->randomElement(['S1', 'S2', 'S3'])],
        ];
    }

    public function done(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'done',
            'completed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
