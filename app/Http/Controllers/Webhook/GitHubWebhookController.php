<?php

namespace App\Http\Controllers\Webhook;

use App\Jobs\IngestGitHubEventJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // 重放攻击防护: 同一 delivery ID 在 10 分钟内重复请求直接拒绝
        if (Cache::has("webhook:delivery:{$id}")) {
            Log::warning('GitHub webhook replay detected', ['id' => $id, 'event' => $event]);
            return response()->json(['message' => 'duplicate delivery'], Response::HTTP_OK);
        }

        $secret = config('services.github.webhook_secret');
        if (empty($secret)) {
            Log::critical('GitHub webhook secret not configured');
            abort(500, 'Webhook 未正确配置');
        }

        // 只读取一次原始 body，避免多次调用 $request->getContent()
        $rawBody = $request->getContent();

        if (! $this->isValidSignature($rawBody, $signature, $secret)) {
            $computed = 'sha256='.hash_hmac('sha256', $rawBody, $secret);
            Log::warning('GitHub webhook signature invalid', [
                'id' => $id,
                'provided' => $signature,
                'computed_prefix' => substr($computed, 0, 20),
                'payload_len' => strlen($rawBody),
            ]);

            return response()->json(['message' => 'invalid signature'], Response::HTTP_FORBIDDEN);
        }

        // 签名验证通过后标记 delivery 已处理
        // IngestGitHubEventJob 内部已通过 external_id 唯一约束做 DB 层幂等，作为兜底
        Cache::add("webhook:delivery:{$id}", true, 600);

        $payload = json_decode($rawBody, true) ?: [];
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
