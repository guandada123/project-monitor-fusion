<?php

namespace Database\Factories;

use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['github', 'jira', 'feishu_base', 'csv']),
            'name' => fake()->company().' '.fake()->randomElement(['Sync', 'Connector', 'Bridge']),
            'config' => [
                'url' => fake()->url(),
                'token_prefix' => substr(fake()->uuid(), 0, 8).'...',
                'enabled' => true,
            ],
            'status' => fake()->randomElement(['active', 'active', 'active', 'paused']), // weighted
            'last_sync_at' => fake()->optional(0.7)->dateTimeBetween('-3 days', 'now'),
            'sync_state' => ['last_event_id' => (string) fake()->randomNumber(8)],
            'error_message' => null,
            'created_by' => \App\Models\User::factory(),
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
                'repository' => fake()->randomElement(['laravel/laravel', 'livewire/livewire', 'owner/project']),
                'webhook_secret' => substr(fake()->sha256(), 0, 16),
                'sync_issues' => true,
                'sync_prs' => true,
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

    /**
     * CSV/static import integration.
     */
    public function csv(): static
    {
        return $this->state(fn (array $attrs) => [
            'type' => 'csv',
            'name' => 'CSV Import '.fake()->randomDigitNotNull(),
            'config' => [
                'file_path' => '/imports/projects_'.fake()->date('Ymd').'.csv',
                'delimiter' => ',',
                'has_header' => true,
            ],
        ]);
    }
}
