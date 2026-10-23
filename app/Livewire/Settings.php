<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class Settings extends Component
{
    // Project auto-discovery settings
    public $namingRule = 'project:*';

    public $defaultTemplate = 'default';

    // Lark/Feishu notification settings
    public $feishuDailyReport = true;

    public $feishuRiskAlert = true;

    public $feishuDailyTime = '08:30';

    /**
     * 安全约束：Webhook URL 内嵌机器人 token，等同凭据。
     * 该属性仅承载「用户本次输入」，**绝不回填数据库已有值** ——
     * public 属性会随 Livewire 快照下发到浏览器，回填即明文泄露。
     * 留空保存 = 保留原值；显式清空用 clearStoredWebhook() + 保存。
     */
    public $feishuWebhookUrl = '';

    /** 是否已配置 Webhook（只暴露布尔，不暴露值） */
    public $hasStoredWebhook = false;

    /** 清除意图标记（保存后生效） */
    public $clearWebhook = false;

    public $feishuPartitionTag = '[项目监控]';

    public $feishuBotName = 'Project Monitor';

    // UI state
    public $saved = false;

    public $editingWebhook = false;

    protected $rules = [
        'namingRule' => 'required|string|max:255',
        'defaultTemplate' => 'required|string',
        'feishuDailyTime' => 'required|date_format:H:i',
        'feishuWebhookUrl' => 'nullable|url',
        'feishuPartitionTag' => 'required|string|max:50',
        'feishuBotName' => 'required|string|max:100',
    ];

    public function mount(SettingsService $settings)
    {
        $this->namingRule = $settings->getString('naming_rule', 'project:*');
        $this->defaultTemplate = $settings->getString('default_template', 'default');
        $this->feishuDailyReport = $settings->getBool('feishu_daily_report', true);
        $this->feishuRiskAlert = $settings->getBool('feishu_risk_alert', true);
        $this->feishuDailyTime = $settings->getString('feishu_daily_time', '08:30');
        // 不回填 webhook 明文（见属性注释）；仅记录「是否已配置」布尔
        $this->feishuWebhookUrl = '';
        $this->hasStoredWebhook = $settings->getString('feishu_webhook_url', '') !== '';
        $this->clearWebhook = false;
        $this->feishuPartitionTag = $settings->getString('feishu_partition_tag', '[项目监控]');
        $this->feishuBotName = $settings->getString('feishu_bot_name', 'Project Monitor');
    }

    /**
     * 获取脱敏后的 Webhook URL（仅用于展示）
     *
     * 从服务端存储值计算，不依赖 public 属性 —— 计算结果（掩码串）才会进 HTML。
     */
    public function getMaskedUrlProperty(): string
    {
        $url = app(SettingsService::class)->getString('feishu_webhook_url', '');

        if (empty($url)) {
            return '';
        }

        $last4 = substr($url, -4);

        $lastSlash = strrpos($url, '/');
        if ($lastSlash !== false) {
            $prefix = substr($url, 0, $lastSlash + 1);

            return $prefix.'****'.$last4;
        }

        return '****'.$last4;
    }

    /**
     * 进入 Webhook URL 编辑模式
     *
     * 编辑态从空白开始：绝不把已存明文带进浏览器快照。
     */
    public function revealWebhook(): void
    {
        $this->editingWebhook = true;
        $this->feishuWebhookUrl = '';
        $this->clearWebhook = false;
        $this->resetValidation('feishuWebhookUrl');
    }

    /**
     * 取消 Webhook URL 编辑，丢弃未保存输入
     */
    public function cancelWebhookEdit(): void
    {
        $this->editingWebhook = false;
        $this->feishuWebhookUrl = '';
        $this->clearWebhook = false;
        $this->resetValidation('feishuWebhookUrl');
    }

    /**
     * 标记清除已配置的 Webhook（保存后生效，避免误触即删）
     */
    public function clearStoredWebhook(): void
    {
        $this->clearWebhook = true;
        $this->editingWebhook = false;
        $this->feishuWebhookUrl = '';
        $this->resetValidation('feishuWebhookUrl');
    }

    /**
     * 测试飞书推送连通性 — 不保存未保存更改
     *
     * 输入框为空时回落到已存配置（服务端读取），便于直接验证既有配置。
     */
    public function testPush(): void
    {
        $webhookUrl = $this->feishuWebhookUrl !== ''
            ? $this->feishuWebhookUrl
            : app(SettingsService::class)->getString('feishu_webhook_url', '');

        if (empty($webhookUrl)) {
            $this->addError('feishu_webhook_url', '请先填写或配置 Webhook URL');

            return;
        }

        try {
            $response = Http::post($webhookUrl, [
                'msg_type' => 'text',
                'content' => ['text' => '🔔 配置测试消息 - 如果你看到此消息，配置正确！'],
            ]);

            if ($response->successful()) {
                $this->dispatch('notify', type: 'success', message: '飞书推送测试成功');
            } else {
                $this->addError('test', '推送失败：'.$response->body());
            }
        } catch (\Exception $e) {
            $this->addError('test', '推送异常：'.$e->getMessage());
        }
    }

    public function saveGeneral(SettingsService $settings)
    {
        $this->validateOnly('namingRule');
        $this->validateOnly('defaultTemplate');

        $settings->setMultiple([
            'naming_rule' => $this->namingRule,
            'default_template' => $this->defaultTemplate,
        ], 'general');

        $this->saved = true;
        session()->flash('message', '项目策略已保存');
    }

    public function saveNotifications(SettingsService $settings)
    {
        $this->validateOnly('feishuDailyTime');
        $this->validateOnly('feishuPartitionTag');
        $this->validateOnly('feishuBotName');
        $this->validateOnly('feishuWebhookUrl');

        // Webhook 合并语义：清除标记 > 本次输入 > 保留原值
        $existing = $settings->getString('feishu_webhook_url', '');
        $webhook = $this->clearWebhook
            ? ''
            : ($this->feishuWebhookUrl !== '' ? $this->feishuWebhookUrl : $existing);

        $settings->setMultiple([
            'feishu_daily_report' => $this->feishuDailyReport,
            'feishu_risk_alert' => $this->feishuRiskAlert,
            'feishu_daily_time' => $this->feishuDailyTime,
            'feishu_webhook_url' => $webhook,
            'feishu_partition_tag' => $this->feishuPartitionTag,
            'feishu_bot_name' => $this->feishuBotName,
        ], 'notification');

        $this->resetWebhookStateAfterSave($webhook);
    }

    /** 保存后重置 webhook 相关 UI 状态（输入清空，只保留布尔与掩码） */
    private function resetWebhookStateAfterSave(string $webhook): void
    {
        $this->hasStoredWebhook = $webhook !== '';
        $this->editingWebhook = false;
        $this->feishuWebhookUrl = '';
        $this->clearWebhook = false;
        $this->saved = true;
        session()->flash('message', '推送配置已保存');
    }

    public function render()
    {
        return view('livewire.settings')
            ->layout('layouts.app')
            ->title('系统设置 — Project Monitor');
    }
}
