<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = \App\Models\Event::class;

    public function definition(): array
    {
        $eventTypes = [
            'github' => ['push', 'pull_request.opened', 'pull_request.merged', 'issue.opened', 'issue.closed'],
            'jira' => ['issue.created', 'issue.updated', 'sprint.started', 'sprint.closed'],
            'feishu_base' => ['record.created', 'record.updated'],
        ];

        $source = fake()->randomElement(['github', 'jira', 'feishu_base']);
        $type = fake()->randomElement($eventTypes[$source]);

        return [
            'source' => $source,
            'external_id' => (string) fake()->unique()->randomNumber(8),
            'event_type' => $type,
            'occurred_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'payload' => [
                'action' => explode('.', $type)[1] ?? $type,
                'resource' => fake()->word(),
                'url' => fake()->url(),
                'actor' => fake()->userName(),
            ],
            'fingerprint' => sha1(fake()->unique()->uuid()),
            'processed_at' => fake()->optional(0.8)->dateTimeBetween('-5 days', 'now'),
            'project_id' => Project::factory(),
            'error' => null,
            'retry_count' => 0,
        ];
    }

    /**
     * Mark as unprocessed (processed_at = null).
     */
    public function unprocessed(): static
    {
        return $this->state(fn (array $attrs) => [
            'processed_at' => null,
        ]);
    }

    /**
     * Mark as failed (with error message).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attrs) => [
            'processed_at' => null,
            'error' => fake()->sentence(),
            'retry_count' => fake()->numberBetween(1, 5),
        ]);
    }

    /**
     * Set a specific source.
     */
    public function fromSource(string $source): static
    {
        return $this->state(fn (array $attrs) => [
            'source' => $source,
            'event_type' => $source . '.' . fake()->randomElement([
                'created', 'updated', 'deleted', 'synced',
            ]),
        ]);
    }

    /**
     * Create without a project (standalone event).
     */
    public function withoutProject(): static
    {
        return $this->state(fn (array $attrs) => [
            'project_id' => null,
        ]);
    }
}
