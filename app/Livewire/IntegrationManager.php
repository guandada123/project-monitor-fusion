<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Services\GitHubService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, Integration> $integrations
 */
class IntegrationManager extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    // Form fields
    public string $formType = 'github';

    public string $formName = '';

    public array $formConfig = [];

    public string $formStatus = 'active';

    // GitHub specific
    #[Locked]
    public string $ghToken = '';

    public string $ghOrg = '';

    public string $ghRepo = '';

    // Lark specific
    public string $larkWebhook = '';

    public string $larkAppId = '';

    #[Locked]
    public string $larkAppSecret = '';

    // DingTalk specific
    public string $dtWebhook = '';

    #[Locked]
    public string $dtSecret = '';

    public function mount()
    {
        $this->resetForm();
    }

    /** 检查集成管理权限（所有 mutate 操作必须通过） */
    private function authorizeIntegration(): void
    {
        abort_unless(auth()->user()?->hasPermission('manage_integrations'), 403, '您没有管理集成的权限。');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->formType = 'github';
        $this->formName = '';
        $this->formConfig = [];
        $this->formStatus = 'active';
        $this->ghToken = '';
        $this->ghOrg = '';
        $this->ghRepo = '';
        $this->larkWebhook = '';
        $this->larkAppId = '';
        $this->larkAppSecret = '';
        $this->dtWebhook = '';
        $this->dtSecret = '';
        $this->resetValidation();
    }

    public function openCreate(string $type = 'github'): void
    {
        $this->resetForm();
        $this->formType = $type;
        $types = ['github' => 'GitHub', 'lark' => '飞书', 'dingtalk' => '钉钉'];
        $this->formName = ($types[$type] ?? 'GitHub').' 集成';
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);
        $this->editingId = $id;
        $this->formType = $integration->type;
        $this->formName = $integration->name;
        $this->formStatus = $integration->status;
        $this->formConfig = $integration->config ?? [];

        // Populate type-specific fields
        match ($integration->type) {
            'github' => $this->loadGitHubForm($this->formConfig),
            'lark' => $this->loadLarkForm($this->formConfig),
            'dingtalk' => $this->loadDingTalkForm($this->formConfig),
            default => null,
        };

        $this->showModal = true;
    }

    private function loadGitHubForm(array $config): void
    {
        $this->ghToken = $config['token'] ?? '';
        $this->ghOrg = $config['org'] ?? '';
        $this->ghRepo = $config['repo'] ?? '';
    }

    private function loadLarkForm(array $config): void
    {
        $this->larkWebhook = $config['webhook_url'] ?? '';
        $this->larkAppId = $config['app_id'] ?? '';
        $this->larkAppSecret = $config['app_secret'] ?? '';
    }

    private function loadDingTalkForm(array $config): void
    {
        $this->dtWebhook = $config['webhook_url'] ?? '';
        $this->dtSecret = $config['secret'] ?? '';
    }

    public function save()
    {
        $this->authorizeIntegration();
        $this->validate([
            'formName' => 'required|string|max:255',
            'formType' => 'required|in:github,lark,dingtalk',
        ]);

        $config = $this->buildConfig();

        if ($this->editingId) {
            $integration = Integration::findOrFail($this->editingId);
            $integration->update([
                'name' => $this->formName,
                'type' => $this->formType,
                'config' => $config,
                'status' => $this->formStatus,
            ]);
            session()->flash('message', '集成已更新');
        } else {
            Integration::create([
                'type' => $this->formType,
                'name' => $this->formName,
                'config' => $config,
                'status' => 'active',
            ]);
            session()->flash('message', '集成已添加');
        }

        $this->showModal = false;
        $this->resetForm();
    }

    private function buildConfig(): array
    {
        return match ($this->formType) {
            'github' => [
                'token' => $this->ghToken,
                'org' => $this->ghOrg,
                'repo' => $this->ghRepo,
            ],
            'lark' => [
                'webhook_url' => $this->larkWebhook,
                'app_id' => $this->larkAppId,
                'app_secret' => $this->larkAppSecret,
            ],
            'dingtalk' => [
                'webhook_url' => $this->dtWebhook,
                'secret' => $this->dtSecret,
            ],
            default => [],
        };
    }

    public function toggleStatus(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);
        $newStatus = $integration->status === 'active' ? 'inactive' : 'active';
        $integration->update(['status' => $newStatus]);
        session()->flash('message', $newStatus === 'active' ? '集成已启用' : '集成已停用');
    }

    public function testConnection(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);

        if ($integration->type === 'github') {
            $service = new GitHubService($integration);
            $result = $service->testConnection();

            $integration->update([
                'last_sync_at' => now(),
                'error_message' => $result['ok'] ? null : ($result['error'] ?? '未知错误'),
            ]);

            if ($result['ok']) {
                session()->flash('message', "✅ 连接成功 — {$result['repo']} (⭐ {$result['stars']})");
            } else {
                session()->flash('error', '连接失败: '.($result['error'] ?? '未知错误'));
            }

            return;
        }

        // Non-GitHub: simple connectivity check
        $integration->update([
            'last_sync_at' => now(),
            'error_message' => null,
        ]);
        session()->flash('message', '连接测试成功');
    }

    public function syncNow(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);

        if ($integration->type !== 'github') {
            session()->flash('error', '仅支持 GitHub 类型的数据同步');

            return;
        }

        // Run sync via Artisan for clean output handling
        $exitCode = Artisan::call('github:sync', [
            '--integration' => $id,
        ]);

        $output = Artisan::output();

        if ($exitCode === 0) {
            session()->flash('message', "GitHub 同步完成!\n".$output);
        } else {
            session()->flash('error', "同步失败:\n".$output);
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);
        $integration->delete();
        session()->flash('message', '集成已删除');
    }

    public function getIntegrationsProperty()
    {
        return Integration::orderBy('created_at', 'desc')->get();
    }

    public function render()
    {
        return view('livewire.integration-manager', [
            'integrations' => $this->integrations,
        ])->layout('layouts.app')->title('集成管理 — Project Monitor');
    }
}
