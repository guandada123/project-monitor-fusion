<?php

namespace Database\Factories;

use App\Models\SlowQuery;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlowQueryFactory extends Factory
{
    protected $model = SlowQuery::class;

    public function definition(): array
    {
        $queries = [
            'SELECT * FROM projects WHERE status = ? ORDER BY created_at DESC LIMIT ?',
            'SELECT p.*, COUNT(t.id) as task_count FROM projects p LEFT JOIN tasks t ON t.project_id = p.id GROUP BY p.id HAVING COUNT(t.id) > ?',
            'UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND project_id = ?',
            'INSERT INTO metric_snapshots (metric_id, value, captured_at) VALUES (?, ?, ?)',
            'SELECT * FROM risks WHERE severity = ? AND status = ? ORDER BY last_seen_at DESC',
            'SELECT u.*, COUNT(a.id) as assignment_count FROM users u JOIN assignments a ON a.user_id = u.id WHERE a.project_id = ? GROUP BY u.id',
            'DELETE FROM events WHERE created_at < ? AND retry_count > ?',
            'SELECT DISTINCT source FROM projects WHERE status = ? AND deleted_at IS NULL',
        ];

        return [
            'query'           => $this->faker->randomElement($queries),
            'bindings'        => $this->faker->randomElements(['1', '2', '3', 'active', 'done', '2026-06-14 10:00:00'], 2),
            'time'            => $this->faker->randomFloat(2, 100, 3000),
            'connection_name' => $this->faker->randomElement(['pgsql', 'mysql', 'sqlite']),
            'caller'          => '/app/' . $this->faker->randomElement(['Http/Controllers', 'Models', 'Services', 'Livewire']) . '/' .
                                $this->faker->word() . '.php:' . $this->faker->numberBetween(10, 200),
            'request_uri'     => '/' . $this->faker->randomElement(['projects', 'tasks', 'risks', 'dashboard', 'reports']),
            'request_method'  => 'GET',
            'user_id'         => null,
            'is_slow'         => $this->faker->boolean(80),
            'is_critical'     => $this->faker->boolean(20),
            'captured_at'     => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function slow(): static
    {
        return $this->state(fn(array $attrs) => [
            'time'        => $this->faker->randomFloat(2, 500, 999),
            'is_slow'     => true,
            'is_critical' => false,
        ]);
    }

    public function critical(): static
    {
        return $this->state(fn(array $attrs) => [
            'time'        => $this->faker->randomFloat(2, 1000, 5000),
            'is_slow'     => true,
            'is_critical' => true,
        ]);
    }
}
