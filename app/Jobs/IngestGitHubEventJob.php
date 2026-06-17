<?php
namespace App\Jobs;

use App\Services\ProjectDiscoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestGitHubEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $deliveryId,
        public string $event,
        public array $payload,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        // 幂等：写入 events 表
        $exists = \DB::table('events')
            ->where('source', 'github')
            ->where('external_id', $this->deliveryId)
            ->exists();

        if ($exists) {
            Log::info('IngestGitHubEvent: duplicate skipped', ['delivery' => $this->deliveryId]);
            return;
        }

        \DB::table('events')->insert([
            'source'      => 'github',
            'external_id' => $this->deliveryId,
            'event_type'  => $this->event,
            'occurred_at' => now(),
            'payload'     => json_encode($this->payload),
            'processed_at'=> now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // 自动发现
        if (in_array($this->event, ['projects_v2_item', 'issues'])) {
            $project = ProjectDiscoveryService::discoverFromGitHub($this->payload);
            Log::info($project
                ? "IngestGitHubEvent: project discovered: {$project->slug}"
                : 'IngestGitHubEvent: not a managed project'
            );
        }
    }
}
