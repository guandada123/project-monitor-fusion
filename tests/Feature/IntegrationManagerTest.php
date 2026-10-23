<?php

use App\Livewire\IntegrationManager;
use App\Models\Integration;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

describe('IntegrationManager — 集成创建/更新', function () {
    it('创建飞书集成时凭据正确入库', function () {
        Livewire::test(IntegrationManager::class)
            ->set('formType', 'lark')
            ->set('formName', '飞书机器人')
            ->set('larkWebhook', 'https://open.feishu.cn/open-apis/bot/v2/hook/secret-abc')
            ->set('larkAppId', 'cli_a1b2c3')
            ->set('larkAppSecret', 'sec-xyz')
            ->call('save');

        $integration = Integration::latest('id')->first();

        expect($integration->type)->toBe('lark')
            ->and($integration->config['webhook_url'])->toBe('https://open.feishu.cn/open-apis/bot/v2/hook/secret-abc')
            ->and($integration->config['app_id'])->toBe('cli_a1b2c3')
            ->and($integration->config['app_secret'])->toBe('sec-xyz');
    });

    it('创建钉钉集成时凭据正确入库', function () {
        Livewire::test(IntegrationManager::class)
            ->set('formType', 'dingtalk')
            ->set('formName', '钉钉机器人')
            ->set('dtWebhook', 'https://oapi.dingtalk.com/robot/send?access_token=secret-999')
            ->set('dtSecret', 'SEC-prefix')
            ->call('save');

        $integration = Integration::latest('id')->first();

        expect($integration->type)->toBe('dingtalk')
            ->and($integration->config['webhook_url'])->toBe('https://oapi.dingtalk.com/robot/send?access_token=secret-999')
            ->and($integration->config['secret'])->toBe('SEC-prefix');
    });

    it('更新时凭据留空保留数据库原值', function () {
        $integration = Integration::create([
            'type' => 'lark',
            'name' => '原名',
            'config' => [
                'webhook_url' => 'https://open.feishu.cn/hook/original',
                'app_id' => 'cli_orig',
                'app_secret' => 'sec_orig',
            ],
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        // 编辑态：不填任何凭据字段，只改名
        Livewire::test(IntegrationManager::class)
            ->call('edit', $integration->id)
            ->set('formName', '改名后')
            ->call('save');

        $integration->refresh();

        expect($integration->name)->toBe('改名后')
            ->and($integration->config['webhook_url'])->toBe('https://open.feishu.cn/hook/original')
            ->and($integration->config['app_secret'])->toBe('sec_orig');
    });
});

describe('IntegrationManager — 凭据泄漏防护', function () {
    it('编辑态不把库中已有凭据回填到前端', function () {
        $integration = Integration::create([
            'type' => 'lark',
            'name' => '集成',
            'config' => [
                'webhook_url' => 'https://open.feishu.cn/hook/should-not-appear',
                'app_id' => 'cli_1',
                'app_secret' => 'secret-should-not-appear',
            ],
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        $test = Livewire::test(IntegrationManager::class)
            ->call('edit', $integration->id);

        // public 属性会被序列化进 wire:snapshot 下发浏览器，
        // 因此编辑态必须保持为空，只靠 placeholder 提示"已配置"
        expect($test->get('larkWebhook'))->toBe('')
            ->and($test->get('larkAppSecret'))->toBe('')
            ->and($test->get('webhookHint'))->toBe('已配置，留空则不修改');
    });

    it('页面渲染输出中不含凭据明文', function () {
        Integration::create([
            'type' => 'lark',
            'name' => '含凭据的集成',
            'config' => [
                'webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/ultra-secret-token',
                'app_id' => 'cli_secret',
                'app_secret' => 'app-secret-value',
            ],
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);

        $html = Livewire::test(IntegrationManager::class)->html();

        expect($html)->not->toContain('ultra-secret-token')
            ->and($html)->not->toContain('app-secret-value');
    });

    it('新建态缺失必填凭据时拒绝保存', function () {
        Livewire::test(IntegrationManager::class)
            ->set('formType', 'lark')
            ->set('formName', '缺凭据')
            ->set('larkWebhook', '')
            ->call('save')
            ->assertHasErrors('larkWebhook');

        expect(Integration::count())->toBe(0);
    });
});

describe('IntegrationManager — 权限控制', function () {
    it('无 manage_integrations 权限的用户无法保存', function () {
        $this->actingAs(User::factory()->create());

        Livewire::test(IntegrationManager::class)
            ->set('formType', 'lark')
            ->set('formName', '越权尝试')
            ->call('save')
            ->assertStatus(403);
    });
});
