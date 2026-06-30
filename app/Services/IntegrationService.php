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

        $output = $this->sanitizeArtisanOutput(Artisan::output());

        if ($exitCode === 0) {
            return ['ok' => true, 'output' => $output];
        }

        return ['ok' => false, 'error' => $output ?: '同步命令执行失败'];
    }

    /**
     * 过滤 Artisan 命令输出中的敏感信息。
     *
     * 移除: 命令调用行、PHP 堆栈跟踪、绝对文件路径。
     * 截断: 最大 2000 字符。
     */
    private function sanitizeArtisanOutput(string $output): string
    {
        if (empty($output)) {
            return '';
        }

        $lines = explode("\n", $output);
        $filtered = [];

        foreach ($lines as $line) {
            // 跳过命令调用行（如 "  0 => '--integration=123'"）
            if (preg_match('/^\s*\d+\s*=>\s*\'--/', $line)) {
                continue;
            }
            // 跳过 PHP 堆栈跟踪中的文件路径行
            if (preg_match('/^#\d+\s+.*\.php\(/', $line)) {
                continue;
            }
            // 跳过绝对路径引用行
            if (preg_match('/^(?>File|Line):\s+\//', $line)) {
                continue;
            }
            // 替换剩余行中的绝对路径（如 /app/Http/... 等）为相对路径提示
            $line = preg_replace('#/[a-zA-Z0-9_/]+\.php:\d+#', '[internal path]', $line);
            $filtered[] = $line;
        }

        $result = implode("\n", $filtered);

        // 截断过长输出
        return mb_strlen($result) > 2000 ? mb_substr($result, 0, 2000)."\n... (已截断)" : $result;
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
