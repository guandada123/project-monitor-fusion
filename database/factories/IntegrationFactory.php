<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        $type = fake()->randomElement(['github', 'lark', 'dingtalk']);

        $config = match ($type) {
            'github' => [
                'token' => 'ghp_'.substr(fake()->sha256(), 0, 36),
                'org' => fake()->company(),
                'repo' => fake()->randomElement(['laravel/laravel', 'livewire/livewire', 'owner/project']),
            ],
            'lark' => [
                'webhook_url' => fake()->url().'/hook',
                'app_id' => 'cli_'.substr(fake()->uuid(), 0, 24),
                'app_secret' => substr(fake()->sha256(), 0, 32),
            ],
            'dingtalk' => [
                'webhook_url' => fake()->url().'/robot/send',
                'secret' => substr(fake()->sha256(), 0, 32),
            ],
        };

        return [
            'type' => $type,
            'name' => fake()->company().' '.fake()->randomElement(['Sync', 'Connector', 'Bridge']),
            'config' => $config,
            'status' => fake()->randomElement(['active', 'active', 'active', 'paused']), // weighted
            'last_sync_at' => fake()->optional(0.7)->dateTimeBetween('-3 days', 'now'),
            'sync_state' => ['last_event_id' => (string) fake()->randomNumber(8)],
            'error_message' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * GitHub integration.
     */
    public function github(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => 'github',
            'name' => 'GitHub Sync',
            'config' => [
                'token' => 'ghp_'.substr(fake()->sha256(), 0, 36),
                'org' => fake()->company(),
                'repo' => fake()->randomElement(['laravel/laravel', 'livewire/livewire', 'owner/project']),
            ],
        ]);
    }

    /**
     * Lark integration.
     */
    public function lark(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => 'lark',
            'name' => '飞书集成',
            'config' => [
                'webhook_url' => fake()->url().'/hook',
                'app_id' => 'cli_'.substr(fake()->uuid(), 0, 24),
                'app_secret' => substr(fake()->sha256(), 0, 32),
            ],
        ]);
    }

    /**
     * DingTalk integration.
     */
    public function dingtalk(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => 'dingtalk',
            'name' => '钉钉集成',
            'config' => [
                'webhook_url' => fake()->url().'/robot/send',
                'secret' => substr(fake()->sha256(), 0, 32),
            ],
        ]);
    }

    /**
     * Active integration.
     */
    public function active(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'active',
            'last_sync_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'error_message' => null,
        ]);
    }

    /**
     * Paused integration.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'paused',
            'sync_state' => null,
        ]);
    }

    /**
     * Errored integration.
     */
    public function errored(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'error',
            'error_message' => fake()->sentence(),
            'last_sync_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
        ]);
    }
}
