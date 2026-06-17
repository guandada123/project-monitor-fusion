<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * 系统配置服务 — 替代 settings.json 文件存储
 *
 * 提供 Key-Value 配置读写，支持分组操作和缓存。
 */
final class SettingsService
{
    private const CACHE_KEY = 'settings_all';
    private const CACHE_TTL = 3600; // 1 小时

    /**
     * 需要加密存储的敏感配置键
     */
    private const SENSITIVE_KEYS = [
        'feishu_webhook_url',
    ];

    /**
     * 加密值前缀标记
     */
    private const ENC_PREFIX = '~enc~';

    /**
     * 获取单个配置值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $setting = $this->all()->firstWhere('key', $key);

        if ($setting === null) {
            return $default;
        }

        return $this->castValue($setting->value);
    }

    /**
     * 获取字符串值
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : (string) $value;
    }

    /**
     * 获取布尔值
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return in_array((string) $value, ['true', '1', 'yes'], true);
    }

    /**
     * 设置单个配置值
     */
    public function set(string $key, mixed $value, ?string $group = null): void
    {
        $stringValue = $this->serializeValue($value);

        // Encrypt sensitive values before storing
        if ($this->shouldEncrypt($key)) {
            $stringValue = $this->encryptValue($stringValue);
        }

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $stringValue,
                'group' => $group,
            ]
        );

        $this->clearCache();
    }

    /**
     * 批量设置配置
     */
    public function setMultiple(array $settings, ?string $group = null): void
    {
        $now = now();

        foreach ($settings as $key => $value) {
            $stringValue = $this->serializeValue($value);

            // Encrypt sensitive values before storing
            if ($this->shouldEncrypt((string) $key)) {
                $stringValue = $this->encryptValue($stringValue);
            }

            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $stringValue,
                    'group' => $group ?? $this->inferGroup($key),
                    'updated_at' => $now,
                ]
            );
        }

        $this->clearCache();
    }

    /**
     * 获取指定分组的所有配置
     */
    public function getGroup(string $group): array
    {
        return $this->all()
            ->where('group', $group)
            ->mapWithKeys(fn (Setting $s) => [$s->key => $this->castValue($s->value)])
            ->all();
    }

    /**
     * 删除配置项
     */
    public function delete(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->clearCache();
    }

    /**
     * 获取所有配置项（缓存）
     */
    private function all(): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::all()
                ->each(function (Setting $setting) {
                    // Decrypt sensitive values transparently
                    if ($this->shouldEncrypt($setting->key)) {
                        $setting->value = $this->decryptIfEncrypted($setting->value);
                    }
                });
        });
    }

    private function decryptIfEncrypted(string $value): string
    {
        if (!str_starts_with($value, self::ENC_PREFIX)) {
            return $value;
        }

        try {
            return Crypt::decryptString(substr($value, strlen(self::ENC_PREFIX)));
        } catch (\Throwable) {
            return $value;
        }
    }

    private function encryptValue(string $value): string
    {
        return self::ENC_PREFIX . Crypt::encryptString($value);
    }

    private function shouldEncrypt(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS, true);
    }

    private function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * 将存储的字符串转为 PHP 类型
     */
    private function castValue(?string $value): bool|int|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * 将 PHP 值序列化为存储字符串
     */
    private function serializeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * 根据 key 名推测分组
     */
    private function inferGroup(string $key): string
    {
        $map = [
            'naming_rule'       => 'general',
            'default_template'  => 'general',
            'feishu_'           => 'notification',
        ];

        foreach ($map as $prefix => $group) {
            if (str_starts_with($key, $prefix)) {
                return $group;
            }
        }

        return 'general';
    }
}
