<?php

namespace Database\Factories;

use App\Models\Metric;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class MetricFactory extends Factory
{
    protected $model = Metric::class;

    public function definition(): array
    {
        // 使用 bothify 扩大唯一空间，避免 word() 在大量创建时 OverflowException
        $key = fake()->unique()->bothify('metric_????????');

        // 随机生成不同类型 definition，覆盖更多测试场景
        $definitions = [
            ['type' => 'count', 'field' => 'tasks', 'filter' => ['status' => 'done']],
            ['type' => 'ratio', 'numerator' => 'tasks.done', 'denominator' => 'tasks.total'],
            ['type' => 'avg', 'field' => 'cycle_time', 'group' => 'project_id'],
            ['type' => 'sum', 'field' => 'story_points', 'filter' => ['sprint' => 'current']],
        ];
        $definition = fake()->randomElement($definitions);

        // dimensions 随 definition 类型变化
        $dimensionSets = [
            ['project_id', 'status'],
            ['project_id', 'sprint'],
            ['team_id', 'priority'],
            ['project_id'],
        ];
        $dimensions = $dimensionSets[array_rand($dimensionSets)];

        // current_json_value 结构随 definition 类型变化
        $jsonValues = [
            ['breakdown' => ['frontend' => fake()->numberBetween(5, 30), 'backend' => fake()->numberBetween(5, 30)]],
            ['numerator' => fake()->numberBetween(10, 100), 'denominator' => fake()->numberBetween(50, 200), 'ratio' => fake()->randomFloat(2, 0.1, 1.0)],
            ['avg_per_project' => fake()->randomFloat(1, 1, 10)],
            ['total' => fake()->numberBetween(100, 5000)],
            null,
        ];

        return [
            'project_id' => Project::factory(),
            'key' => $key,
            'name' => ucfirst(str_replace('_', ' ', $key)),
            'kind' => fake()->randomElement(['system', 'custom']),
            'definition' => $definition,
            'dimensions' => $dimensions,
            'filters' => null,
            'schedule_cron' => fake()->optional(0.8)->randomElement(['0 */6 * * *', '0 0 * * *', '*/30 * * * *']),
            'last_computed_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'current_value' => fake()->randomFloat(2, 0, 100),
            'current_json_value' => fake()->randomElement($jsonValues),
        ];
    }

    public function system(): static
    {
        return $this->state(fn (array $attrs) => ['kind' => 'system']);
    }

    public function custom(): static
    {
        return $this->state(fn (array $attrs) => ['kind' => 'custom']);
    }
}
