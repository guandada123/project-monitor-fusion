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

    public $feishuWebhookUrl = '';

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
        $this->feishuWebhookUrl = $settings->getString('feishu_webhook_url', '');
        $this->feishuPartitionTag = $settings->getString('feishu_partition_tag', '[项目监控]');
        $this->feishuBotName = $settings->getString('feishu_bot_name', 'Project Monitor');
    }

    /**
     * 获取脱敏后的 Webhook URL（仅用于展示）
     */
    public function getMaskedUrlProperty(): string
    {
        if (empty($this->feishuWebhookUrl)) {
            return '';
        }

        $url = $this->feishuWebhookUrl;
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
     */
    public function revealWebhook(): void
    {
        $this->editingWebhook = true;
    }

    /**
     * 取消 Webhook URL 编辑，恢复原始值
     */
    public function cancelWebhookEdit(): void
    {
        $this->editingWebhook = false;
        $this->feishuWebhookUrl = app(SettingsService::class)->getString('feishu_webhook_url', '');
    }

    /**
     * 测试飞书推送连通性 — 不保存未保存更改
     */
    public function testPush(): void
    {
        $webhookUrl = $this->feishuWebhookUrl;
        if (empty($webhookUrl)) {
            $this->addError('feishu_webhook_url', '请先填写 Webhook URL');

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

        $settings->setMultiple([
            'feishu_daily_report' => $this->feishuDailyReport,
            'feishu_risk_alert' => $this->feishuRiskAlert,
            'feishu_daily_time' => $this->feishuDailyTime,
            'feishu_webhook_url' => $this->feishuWebhookUrl,
            'feishu_partition_tag' => $this->feishuPartitionTag,
            'feishu_bot_name' => $this->feishuBotName,
        ], 'notification');

        $this->editingWebhook = false;
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
