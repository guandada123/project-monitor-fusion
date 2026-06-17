<?php

namespace App\Traits;

/**
 * 资源级权限检查 Trait
 *
 * 为 Livewire 组件提供统一的资源操作权限验证能力。
 * 所有涉及 findOrFail 或数据修改的方法应在入口处调用 authorizeResourceAccess()。
 *
 * @see \App\Livewire\IntegrationManager 使用类似的私有方法模式
 */
trait AuthorizesResourceAccess
{
    /**
     * 授权当前用户对指定资源执行操作。
     *
     * @param  string  $permission  所需的权限标识（如 'manage_roles'、'manage_risks'）
     * @param  string|null  $message  自定义错误消息（可选）
     */
    protected function authorizeResourceAccess(string $permission, ?string $message = null): void
    {
        $message ??= "您没有权限执行此操作（需要「{$permission}」权限）。";

        abort_unless(auth()->check(), 403, '未认证，请先登录。');
        abort_unless(auth()->user()->hasPermission($permission), 403, $message);
    }
}
