<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Services\IntegrationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
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
    // 安全约定：下面这些 public 属性**只承载用户本次输入**，
    // 编辑态一律不回填数据库中已有值 —— 因为 public 属性会随 Livewire
    // 快照下发到浏览器，回填即等于把持久化的凭据明文暴露到 DOM。
    // 留空保留原值的合并逻辑见 handleUpdate()。
    public string $ghToken = '';

    public string $ghOrg = '';

    public string $ghRepo = '';

    // Lark specific
    public string $larkWebhook = '';

    public string $larkAppId = '';

    public string $larkAppSecret = '';

    // DingTalk specific
    public string $dtWebhook = '';

    public string $dtSecret = '';

    /** 已配置的敏感字段提示（仅用于表单 placeholder，不含凭据明文） */
    public string $webhookHint = '';

    public string $secretHint = '';

    /** 集成类型显示标签 */
    private const TYPE_LABELS = [
        'github' => 'GitHub',
        'lark' => '飞书',
        'dingtalk' => '钉钉',
    ];

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
        $this->webhookHint = '';
        $this->secretHint = '';
        $this->resetValidation();
    }

    public function openCreate(string $type = 'github'): void
    {
        $this->resetForm();
        $this->formType = $type;
        $this->formName = (self::TYPE_LABELS[$type] ?? 'GitHub').' 集成';
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
            default => Log::warning('IntegrationManager: unknown type in edit()', [
                'type' => $integration->type,
                'id' => $integration->id,
            ]),
        };

        // 清除敏感字段（密钥 + Webhook），防止 Livewire 序列化到前端
        // 这些值一律通过 request()->input() 单独传递
        foreach ($this->sensitiveKeys() as $key) {
            unset($this->formConfig[$key]);
        }

        $this->showModal = true;
    }

    private function loadGitHubForm(array $config): void
    {
        $this->ghOrg = $config['org'] ?? '';
        $this->ghRepo = $config['repo'] ?? '';

        // 不回填已有 token 明文
        $this->ghToken = '';
        $this->secretHint = ! empty($config['token']) ? '已配置，留空则不修改' : '';
    }

    private function loadLarkForm(array $config): void
    {
        $this->larkAppId = $config['app_id'] ?? '';

        // 不回填已有 webhook / app_secret 明文
        $this->larkWebhook = '';
        $this->larkAppSecret = '';
        $this->webhookHint = ! empty($config['webhook_url']) ? '已配置，留空则不修改' : '';
        $this->secretHint = ! empty($config['app_secret']) ? '已配置，留空则不修改' : '';
    }

    private function loadDingTalkForm(array $config): void
    {
        // 不回填已有 webhook 明文（secret 本就可选，无需回填）
        $this->dtWebhook = '';
        $this->dtSecret = '';
        $this->webhookHint = ! empty($config['webhook_url']) ? '已配置，留空则不修改' : '';
    }

    /**
     * 校验当前 formType 的专属字段（含凭据类字段）。
     *
     * 安全约定：凭据字段（token / app_secret / webhook_url / secret）必须是 public 属性
     * 才能被 wire:model 绑定，但**编辑态不回填**数据库已有值，
     * 因此这里统一用 nullable —— 留空即由 handleUpdate() 保留原值，新建态则必填。
     */
    private function validateTypeFields(): void
    {
        $rules = match ($this->formType) {
            'github' => [
                'ghToken' => $this->editingId ? 'nullable|string' : 'required|string',
                'ghOrg' => 'required|string',
                'ghRepo' => 'required|string',
            ],
            'lark' => [
                'larkWebhook' => $this->editingId ? 'nullable|url' : 'required|url',
                'larkAppId' => 'required|string',
                'larkAppSecret' => $this->editingId ? 'nullable|string' : 'required|string',
            ],
            'dingtalk' => [
                'dtWebhook' => $this->editingId ? 'nullable|url' : 'required|url',
                'dtSecret' => 'nullable|string',
            ],
            default => throw new \InvalidArgumentException("未知的集成类型: {$this->formType}"),
        };

        $this->validate($rules);
    }

    public function save()
    {
        $this->authorizeIntegration();
        $this->validate([
            'formName' => 'required|string|max:255',
            'formType' => 'required|in:github,lark,dingtalk',
        ]);

        $this->validateTypeFields();

        $config = $this->buildConfig();

        if ($this->editingId) {
            $this->handleUpdate($config);
        } else {
            $this->handleCreate($config);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    /** 更新已有集成（合并已有密钥） */
    private function handleUpdate(array $config): void
    {
        $integration = Integration::findOrFail($this->editingId);
        $existingConfig = $integration->config ?? [];

        // 所有敏感字段统一合并：新值为空时保留数据库中的原值
        foreach ($this->sensitiveKeys() as $key) {
            if (empty($config[$key]) && ! empty($existingConfig[$key] ?? null)) {
                $config[$key] = $existingConfig[$key];
            }
        }
        $integration->update([
            'name' => $this->formName,
            'type' => $this->formType,
            'config' => $config,
            'status' => $this->formStatus,
        ]);
        session()->flash('message', '集成已更新');
    }

    /** 创建新集成 */
    private function handleCreate(array $config): void
    {
        Integration::create([
            'type' => $this->formType,
            'name' => $this->formName,
            'config' => $config,
            'status' => $this->formStatus,
            'created_by' => auth()->id(),
        ]);
        session()->flash('message', '集成已添加');
    }

    /**
     * 当前 formType 下所有"凭据类"的 config 键名。
     * 用于 handleUpdate() 的「留空即保留原值」合并。
     * 单一真源：新增凭据字段只需改这里。
     *
     * @return list<string>
     */
    private function sensitiveKeys(): array
    {
        return match ($this->formType) {
            'github' => ['token'],
            'lark' => ['webhook_url', 'app_secret'],
            'dingtalk' => ['webhook_url', 'secret'],
            default => [],
        };
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
            default => throw new \InvalidArgumentException("未知的集成类型: {$this->formType}"),
        };
    }

    public function toggleStatus(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);
        $newStatus = app(IntegrationService::class)->toggleStatus($integration);
        session()->flash('message', $newStatus === 'active' ? '集成已启用' : '集成已停用');
    }

    public function testConnection(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);

        try {
            $result = app(IntegrationService::class)->testConnection($integration);
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: testConnection exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', '连接测试异常: '.e($e->getMessage()));

            return;
        }

        if ($result['ok']) {
            $repo = e($result['repo'] ?? '');
            $stars = isset($result['stars']) ? ' (⭐ '.e($result['stars']).')' : '';
            session()->flash('message', "✅ 连接成功 — {$repo}{$stars}");
        } else {
            session()->flash('error', '连接失败: '.e($result['error'] ?? '未知错误'));
        }
    }

    public function syncNow(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);

        try {
            $result = app(IntegrationService::class)->sync($integration);
        } catch (\Throwable $e) {
            Log::error('IntegrationManager: sync exception', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            session()->flash('error', '同步异常: '.e($e->getMessage()));

            return;
        }

        if ($result['ok']) {
            $output = e($result['output'] ?? '');
            session()->flash('message', "GitHub 同步完成!\n{$output}");
        } else {
            session()->flash('error', '同步失败: '.e($result['error'] ?? '未知错误'));
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeIntegration();
        $integration = Integration::findOrFail($id);
        app(IntegrationService::class)->delete($integration);
        session()->flash('message', '集成已删除');
    }

    public function getIntegrationsProperty()
    {
        return Integration::where('created_by', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.integration-manager', [
            'integrations' => $this->integrations,
        ])->layout('layouts.app')->title('集成管理 — Project Monitor');
    }
}
