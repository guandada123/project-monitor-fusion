<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\MetricSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricSnapshotFactory extends Factory
{
    protected $model = MetricSnapshot::class;

    public function definition(): array
    {
        return [
            'metric_id' => Metric::factory(),
            'captured_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'value' => fake()->randomFloat(2, 10, 100),
            'json_value' => ['detail' => fake()->word().': '.fake()->randomNumber(2)],
        ];
    }
}
