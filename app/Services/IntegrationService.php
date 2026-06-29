<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Artisan;

/**
 * 集成管理服务
 *
 * 封装集成模块的核心业务逻辑：测试连接、数据同步、状态管理等。
 * 从 Livewire 组件中抽离，使业务逻辑可测试、可复用。
 */
class IntegrationService
{
    /**
     * 测试集成连接。
     *
     * @param  Integration  $integration  待测试的集成实例
     * @return array{ok: bool, error?: string, ...}
     */
    public function testConnection(Integration $integration): array
    {
        return match ($integration->type) {
            'github' => $this->testGitHubConnection($integration),
            default => $this->testGenericConnection($integration),
        };
    }

    /**
     * 执行数据同步（仅 GitHub 类型支持）。
     *
     * @param  Integration  $integration  待同步的集成实例
     * @return array{ok: bool, error?: string, output?: string}
     */
    public function sync(Integration $integration): array
    {
        if ($integration->type !== 'github') {
            return [
                'ok' => false,
                'error' => '仅支持 GitHub 类型的数据同步',
            ];
        }

        $exitCode = Artisan::call('github:sync', [
            '--integration' => $integration->id,
            '--no-interaction' => true,
        ]);

        $output = Artisan::output();

        if ($exitCode === 0) {
            return ['ok' => true, 'output' => $output];
        }

        return ['ok' => false, 'error' => $output ?: '同步命令执行失败'];
    }

    /**
     * 切换集成启用/停用状态。
     */
    public function toggleStatus(Integration $integration): string
    {
        $newStatus = $integration->status === 'active' ? 'inactive' : 'active';
        $integration->update(['status' => $newStatus]);

        return $newStatus;
    }

    /**
     * 删除集成记录。
     */
    public function delete(Integration $integration): bool
    {
        return (bool) $integration->delete();
    }

    // ─── 类型特定的连接测试 ──────────────────────────────────────────

    /**
     * 通过 GitHubService 测试 GitHub 集成连接。
     */
    protected function testGitHubConnection(Integration $integration): array
    {
        $service = app()->makeWith(GitHubService::class, ['config' => $integration]);
        $result = $service->testConnection();

        $integration->update([
            'last_sync_at' => now(),
            'error_message' => $result['ok'] ? null : ($result['error'] ?? '未知错误'),
        ]);

        return $result;
    }

    /**
     * 非 GitHub 类型的通用连接测试。
     *
     * 对于 webhook 类集成，会校验 webhook_url 是否已配置。
     */
    protected function testGenericConnection(Integration $integration): array
    {
        $config = $integration->config ?? [];

        // Webhook 类集成需要检查 webhook_url 是否已配置
        if (in_array($integration->type, ['lark', 'dingtalk'], true) && empty($config['webhook_url'])) {
            $integration->update([
                'last_sync_at' => now(),
                'error_message' => '未配置 Webhook URL',
            ]);

            return ['ok' => false, 'error' => 'Webhook URL 未配置，请在集成设置中填写。'];
        }

        $integration->update([
            'last_sync_at' => now(),
            'error_message' => null,
        ]);

        return ['ok' => true];
    }
}
