<?php

namespace App\Console\Commands;

use App\Models\Integration;
use App\Models\Project;
use App\Services\GitHubService;
use Illuminate\Console\Command;

class GitHubSyncCommand extends Command
{
    protected $signature = 'github:sync
                            {--integration= : Integration ID (default: first active GitHub)}
                            {--project= : Project slug to sync into}
                            {--full : Full sync including older items}
                            {--dry-run : Preview without saving}';

    protected $description = '从 GitHub 拉取 Issues/PRs/Commits 同步到 Project Monitor';

    /** 失败时最大重试次数（含首次尝试） */
    protected int $maxRetries = 3;

    /** 重试间隔基数（秒），实际延迟 = baseDelay * attempt */
    protected int $baseRetryDelay = 2;

    public function handle(): int
    {
        // Resolve integration
        $integration = $this->resolveIntegration();
        if (!$integration) {
            $this->error('未找到活跃的 GitHub 集成配置。请先在集成管理中配置。');
            return self::FAILURE;
        }

        $this->info("🔗 使用集成: {$integration->name}");
        $this->info("📦 仓库: " . ($integration->config['org'] ?? '?') . '/' . ($integration->config['repo'] ?? '?'));

        // Resolve project (DB query, no retry needed)
        $project = $this->resolveProject();
        if (!$project) {
            $this->error('未找到关联项目。请用 --project= 指定项目 slug。');
            return self::FAILURE;
        }

        $this->info("📋 目标项目: {$project->name}");

        $service = new GitHubService($integration);

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY-RUN 模式 — 不会实际写入');
            $this->line('');
            $this->previewSync($service);
            return self::SUCCESS;
        }

        return $this->syncWithRetry($integration, $service, $project);
    }

    /**
     * 执行同步（含自动重试），最大尝试 $maxRetries 次
     */
    protected function syncWithRetry(Integration $integration, GitHubService $service, Project $project): int
    {
        $lastError = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                // Connection test
                $connTest = $service->testConnection();
                if (!$connTest['ok']) {
                    throw new \RuntimeException($connTest['error'] ?? '连接失败');
                }

                $this->info("✅ 连接成功 — {$connTest['repo']} (⭐ {$connTest['stars']})");

                if ($attempt > 1) {
                    $this->info("🔄 执行同步（第 {$attempt} 次尝试）...");
                } else {
                    $this->info('🔄 开始同步...');
                }

                $result = $service->sync($project);

                if (!$result['ok']) {
                    throw new \RuntimeException($result['error'] ?? '同步返回失败状态');
                }

                // ── 成功 ──
                $stats = $result['stats'];
                $this->info('✅ 同步完成!');
                $this->table(['数据项', '数量'], [
                    ['Commits (事件)', $stats['commits']],
                    ['Issues (任务)', $stats['issues']],
                    ['Pull Requests (任务)', $stats['pull_requests']],
                    ['新事件', $stats['events']],
                ]);

                $integration->update([
                    'last_sync_at' => now(),
                    'error_message' => null,
                    'sync_state' => [
                        'last_sync' => now()->toIsoString(),
                        'stats' => $stats,
                        'attempts' => $attempt,
                    ],
                ]);

                return self::SUCCESS;

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $this->error("❌ 第 {$attempt}/{$this->maxRetries} 次尝试失败: {$lastError}");

                if ($attempt < $this->maxRetries) {
                    $delay = $this->baseRetryDelay * $attempt; // 2s, 4s 线性退避
                    $this->warn("⏳ {$delay} 秒后重试...");
                    sleep($delay);
                }
            }
        }

        // ── 全部重试失败 ──
        $this->error('⚠️ 已达最大重试次数（' . $this->maxRetries . ' 次），同步终止。');

        $integration->update([
            'error_message' => "[{$this->maxRetries}次重试后失败] {$lastError}",
            'last_sync_at' => now(),
            'sync_state' => [
                'last_sync' => now()->toIsoString(),
                'error' => $lastError,
                'attempts' => $this->maxRetries,
            ],
        ]);

        return self::FAILURE;
    }

    protected function resolveIntegration(): ?Integration
    {
        $id = $this->option('integration');
        if ($id) {
            return Integration::find($id);
        }

        return Integration::active()->byType('github')->first();
    }

    protected function resolveProject(): ?Project
    {
        $slug = $this->option('project');
        if ($slug) {
            return Project::where('slug', $slug)->first();
        }

        // Try to match project by integration's repo name
        $integration = $this->resolveIntegration();
        if ($integration && !empty($integration->config['repo'])) {
            $repoName = $integration->config['repo'];
            return Project::where('slug', 'like', "%{$repoName}%")
                ->orWhere('source', 'github')
                ->first();
        }

        return null;
    }

    protected function previewSync(GitHubService $service): void
    {
        try {
            $issues = $service->fetchIssues('all', 10);
            $this->info('Issues 预览 (前 10):');
            foreach ($issues as $issue) {
                $isPR = !empty($issue['pull_request']) ? ' [PR]' : '';
                $this->line("  #{$issue['number']}: {$issue['title']}{$isPR} [{$issue['state']}]");
            }
        } catch (\Throwable $e) {
            $this->error('获取 Issues 失败: ' . $e->getMessage());
        }
    }
}
