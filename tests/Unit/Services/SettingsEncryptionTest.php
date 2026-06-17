<?php

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;

// ─────────────────────────────────────────────
// SettingsService — Webhook 加密/解密往返
// ─────────────────────────────────────────────

beforeEach(function () {
    $this->service = app(SettingsService::class);
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

// ─── 加密往返 ─────────────────────────────────

describe('sensitive value encryption round-trip', function () {

    test('sensitive webhook URL encrypts on set and decrypts on get', function () {
        $original = 'https://open.feishu.cn/open-apis/bot/v2/hook/abc-123-def-456';

        $this->service->set('feishu_webhook_url', $original, 'notification');

        $retrieved = $this->service->get('feishu_webhook_url');

        expect($retrieved)->toBe($original);
    });

    test('sensitive value is actually encrypted in the database', function () {
        $original = 'https://open.feishu.cn/open-apis/bot/v2/hook/top-secret';

        $this->service->set('feishu_webhook_url', $original, 'notification');

        // Query DB directly — bypasses the transparent decryption
        $setting = Setting::where('key', 'feishu_webhook_url')->first();

        expect($setting)->not->toBeNull();
        // Must start with the encryption prefix
        expect($setting->value)->toStartWith('~enc~');
        // Must not contain the original plaintext
        expect($setting->value)->not->toContain('top-secret');
        expect($setting->value)->not->toContain('feishu.cn');
        // The actual stored value should be longer than the prefix + original (encrypted + base64 overhead)
        expect(strlen($setting->value))->toBeGreaterThan(strlen('~enc~') + strlen($original));
    });

    test('can update and get updated sensitive value', function () {
        $this->service->set('feishu_webhook_url', 'https://example.com/v1/webhook/old', 'notification');
        expect($this->service->get('feishu_webhook_url'))->toBe('https://example.com/v1/webhook/old');

        $this->service->set('feishu_webhook_url', 'https://example.com/v1/webhook/new', 'notification');
        expect($this->service->get('feishu_webhook_url'))->toBe('https://example.com/v1/webhook/new');

        // DB has new encrypted value
        $setting = Setting::where('key', 'feishu_webhook_url')->first();
        expect($setting->value)->toStartWith('~enc~');
    });

    test('multiple sensitive values round-trip correctly', function () {
        $values = [
            'feishu_webhook_url' => 'https://open.feishu.cn/open-apis/bot/v2/hook/aaa',
        ];

        $this->service->setMultiple($values, 'notification');

        $group = $this->service->getGroup('notification');
        expect($group['feishu_webhook_url'])->toBe('https://open.feishu.cn/open-apis/bot/v2/hook/aaa');
    });

    test('sensitive value accessible via getGroup', function () {
        $this->service->set('feishu_webhook_url', 'https://open.feishu.cn/hook/group-test', 'notification');

        $group = $this->service->getGroup('notification');

        expect($group)->toHaveKey('feishu_webhook_url');
        expect($group['feishu_webhook_url'])->toBe('https://open.feishu.cn/hook/group-test');
    });
});

// ─── 非敏感键不加密 ─────────────────────────────

describe('non-sensitive values are not encrypted', function () {

    test('setting a non-sensitive key stores plain value', function () {
        $this->service->set('naming_rule', '[项目] {name}', 'general');

        $setting = Setting::where('key', 'naming_rule')->first();
        expect($setting->value)->not->toStartWith('~enc~');
        expect($setting->value)->toBe('[项目] {name}');
    });

    test('non-sensitive key returns correct value from get()', function () {
        $this->service->set('naming_rule', 'custom-rule', 'general');

        expect($this->service->get('naming_rule'))->toBe('custom-rule');
    });

    test('mixing sensitive and non-sensitive in setMultiple', function () {
        $this->service->setMultiple([
            'naming_rule' => 'test-rule',
            'feishu_webhook_url' => 'https://open.feishu.cn/hook/mixed',
            'default_template' => 'default',
        ], 'general');

        // Non-sensitive: plain in DB
        $naming = Setting::where('key', 'naming_rule')->first();
        expect($naming->value)->toBe('test-rule');

        // Sensitive: encrypted in DB
        $webhook = Setting::where('key', 'feishu_webhook_url')->first();
        expect($webhook->value)->toStartWith('~enc~');

        // Both return correct values
        expect($this->service->get('naming_rule'))->toBe('test-rule');
        expect($this->service->get('feishu_webhook_url'))->toBe('https://open.feishu.cn/hook/mixed');
        expect($this->service->get('default_template'))->toBe('default');
    });
});

// ─── 篡改 / 异常数据容错 ───────────────────────

describe('tampered or edge-case encrypted data', function () {

    test('tampered encrypted value returns raw value (graceful fallback)', function () {
        Setting::create([
            'key' => 'feishu_webhook_url',
            'value' => '~enc~tampered-garbage-data',
            'group' => 'notification',
        ]);

        Cache::flush();

        // Should not throw; returns the raw value as-is
        $result = $this->service->get('feishu_webhook_url');
        expect($result)->toBe('~enc~tampered-garbage-data');
    });

    test('empty string sensitive value is stored encrypted', function () {
        $this->service->set('feishu_webhook_url', '', 'notification');

        $setting = Setting::where('key', 'feishu_webhook_url')->first();
        // Empty string still gets encrypted
        expect($setting->value)->toStartWith('~enc~');

        // Getting it back returns empty string
        expect($this->service->get('feishu_webhook_url'))->toBe('');
    });

    test('encrypted value without prefix returns raw value', function () {
        Setting::create([
            'key' => 'feishu_webhook_url',
            'value' => Crypt::encryptString('https://example.com/hook'),
            'group' => 'notification',
        ]);

        Cache::flush();

        $result = $this->service->get('feishu_webhook_url');
        // Without ~enc~ prefix, it won't try to decrypt; returns raw encrypted string
        // But the encrypted string won't match the original URL, so it's basically opaque
        expect($result)->not->toBe('https://example.com/hook');
        expect($result)->toBeString();
    });
});

// ─── 删除操作 ─────────────────────────────────

describe('delete with encrypted values', function () {

    test('deleting a sensitive key removes it completely', function () {
        $this->service->set('feishu_webhook_url', 'https://open.feishu.cn/hook/delete-me', 'notification');
        expect($this->service->get('feishu_webhook_url'))->not->toBeNull();

        $this->service->delete('feishu_webhook_url');

        expect($this->service->get('feishu_webhook_url'))->toBeNull();
        expect(Setting::where('key', 'feishu_webhook_url')->exists())->toBeFalse();
    });
});
