<?php

namespace App\Livewire;

use App\Models\Integration;
use App\Services\IntegrationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
    public string $ghOrg = '';

    public string $ghRepo = '';

    // Lark specific
    public string $larkWebhook = '';

    public string $larkAppId = '';

    // larkAppSecret 不绑定到 Livewire 属性（防止序列化到前端）

    // DingTalk specific
    public string $dtWebhook = '';

    // dtSecret 不绑定到 Livewire 属性（防止序列化到前端）

    /** 集成类型显示标签 */
    private const TYPE_LABELS = [
        'github' => 'GitHub',
        'lark' => '飞书',
        'dingtalk' => '钉钉',
    ];

    /** 各集成类型的密钥字段映射: formType → [input名, config key]
     *  input: 前端 input name（用于 validateSecrets/buildConfig）
     *  key:   config 数组中的键名（用于 handleUpdate 合并已有密钥） */
    private const SECRET_INPUT_MAP = [
        'github' => ['input' => 'gh_token', 'key' => 'token'],
        'lark' => ['input' => 'lark_app_secret', 'key' => 'app_secret'],
        'dingtalk' => ['input' => 'dt_secret', 'key' => 'secret'],
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
        $this->ghOrg = '';
        $this->ghRepo = '';
        $this->larkWebhook = '';
        $this->larkAppId = '';
        $this->dtWebhook = '';
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

        // 清除密钥字段，防止 Livewire 序列化到前端（密钥通过 request()->input() 单独传递）
        $secretKey = match ($integration->type) {
            'github' => 'token',
            'lark' => 'app_secret',
            'dingtalk' => 'secret',
            default => null,
        };
        if ($secretKey !== null) {
            unset($this->formConfig[$secretKey]);
        }

        $this->showModal = true;
    }

    private function loadGitHubForm(array $config): void
    {
        // ghToken 不加载到前端 — 空值表示"保留原值"
        $this->ghOrg = $config['org'] ?? '';
        $this->ghRepo = $config['repo'] ?? '';
    }

    private function loadLarkForm(array $config): void
    {
        $this->larkWebhook = $config['webhook_url'] ?? '';
        $this->larkAppId = $config['app_id'] ?? '';
        // larkAppSecret 不加载到前端 — 空值表示"保留原值"
    }

    private function loadDingTalkForm(array $config): void
    {
        $this->dtWebhook = $config['webhook_url'] ?? '';
        // dtSecret 不加载到前端 — 空值表示"保留原值"
    }

    /** 校验非敏感专属字段 */
    private function validateTypeFields(): void
    {
        $this->validate(match ($this->formType) {
            'github' => [
                'ghOrg' => 'required|string',
                'ghRepo' => 'required|string',
            ],
            'lark' => [
                'larkWebhook' => 'required|url',
                'larkAppId' => 'required|string',
            ],
            'dingtalk' => [
                'dtWebhook' => 'required|url',
            ],
            default => throw new \InvalidArgumentException("未知的集成类型: {$this->formType}"),
        });
    }

    /**
     * 校验敏感字段（通过 request()->input()，不走 Livewire 属性防止序列化泄漏）
     *
     * @return bool true=通过 false=有错误
     */
    private function validateSecrets(): bool
    {
        $fieldMap = self::SECRET_INPUT_MAP[$this->formType] ?? null;
        if (! $fieldMap) {
            return true;
        }

        $rule = [$fieldMap['input'] => $this->editingId ? 'nullable|string' : 'required|string'];
        $validator = Validator::make(request()->only($fieldMap['input']), $rule);

        if ($validator->fails()) {
            foreach ($validator->errors()->toArray() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }

            return false;
        }

        return true;
    }

    public function save()
    {
        $this->authorizeIntegration();
        $this->validate([
            'formName' => 'required|string|max:255',
            'formType' => 'required|in:github,lark,dingtalk',
        ]);

        $this->validateTypeFields();

        if (! $this->validateSecrets()) {
            return;
        }

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
        $secretKey = (self::SECRET_INPUT_MAP[$this->formType] ?? [])['key'] ?? null;
        if ($secretKey && empty($config[$secretKey]) && ! empty($existingConfig[$secretKey] ?? null)) {
            $config[$secretKey] = $existingConfig[$secretKey];
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

    private function buildConfig(): array
    {
        return match ($this->formType) {
            'github' => [
                'token' => request()->input('gh_token', ''),
                'org' => $this->ghOrg,
                'repo' => $this->ghRepo,
            ],
            'lark' => [
                'webhook_url' => $this->larkWebhook,
                'app_id' => $this->larkAppId,
                'app_secret' => request()->input('lark_app_secret', ''),
            ],
            'dingtalk' => [
                'webhook_url' => $this->dtWebhook,
                'secret' => request()->input('dt_secret', ''),
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
