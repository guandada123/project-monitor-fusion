<?php

namespace App\Http\Controllers\Webhook;

use App\Jobs\IngestGitHubEventJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class GitHubWebhookController
{
    public function __invoke(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256');
        $event = $request->header('X-GitHub-Event', 'unknown');
        $id = $request->header('X-GitHub-Delivery', (string) str()->uuid());

        $secret = config('services.github.webhook_secret');
        if (empty($secret)) {
            Log::critical('GitHub webhook secret not configured');
            abort(500, 'Webhook 未正确配置');
        }
        if (! $this->isValidSignature($request->getContent(), $signature, $secret)) {
            $computed = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            Log::warning('GitHub webhook signature invalid', [
                'id' => $id,
                'provided' => $signature,
                'computed_prefix' => substr($computed, 0, 20),
                'payload_len' => strlen($request->getContent()),
            ]);

            return response()->json(['message' => 'invalid signature'], Response::HTTP_FORBIDDEN);
        }

        $payload = json_decode($request->getContent(), true) ?: [];
        Queue::push(new IngestGitHubEventJob($id, $event, $payload));

        return response()->json(['ok' => true]);
    }

    private function isValidSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (! $signature || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }
        // GitHub uses the raw body as-is for HMAC. Ensure identical input by not re-encoding.
        $computed = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($computed, $signature);
    }
}
