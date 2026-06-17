<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExportHistoryFactory extends Factory
{
    protected $model = \App\Models\ExportHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'entity_type' => $this->faker->randomElement(['project', 'task', 'risk']),
            'format' => $this->faker->randomElement(['csv', 'xlsx']),
            'filters' => [],
            'selected_columns' => ['name', 'status'],
            'record_count' => $this->faker->numberBetween(1, 500),
            'file_path' => 'exports/test_file.csv',
            'file_size' => $this->faker->randomElement(['1.2 KB', '45 KB', '2.3 MB']),
            'status' => 'completed',
            'completed_at' => now(),
        ];
    }
}
