<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['assignee', 'reviewer', 'observer']),
            'allocation_percent' => fake()->randomElement([25, 50, 75, 100]),
        ];
    }

    /**
     * Assign as the main assignee (100% allocation).
     */
    public function assignee(): static
    {
        return $this->state(fn (array $attrs) => [
            'role' => 'assignee',
            'allocation_percent' => 100,
        ]);
    }

    /**
     * Assign as reviewer (50% allocation).
     */
    public function reviewer(): static
    {
        return $this->state(fn (array $attrs) => [
            'role' => 'reviewer',
            'allocation_percent' => 50,
        ]);
    }

    /**
     * Assign as observer (no direct work).
     */
    public function observer(): static
    {
        return $this->state(fn (array $attrs) => [
            'role' => 'observer',
            'allocation_percent' => 0,
        ]);
    }
}
