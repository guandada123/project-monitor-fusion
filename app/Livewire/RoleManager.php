<?php

namespace App\Livewire;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Traits\AuthorizesResourceAccess;
use Livewire\Component;

class RoleManager extends Component
{
    use AuthorizesResourceAccess;

    public $roles;

    public $permissions;

    // Form
    public $editingRole = null;

    public $formName = '';

    public $formSlug = '';

    public $formDescription = '';

    public $formPermissions = [];

    // User assignment
    public $assigningRole = null;

    public $assignUserId = '';

    // Delete
    public $deletingRole = null;

    // Available users for assignment
    public $users;

    protected $rules = [
        'formName' => 'required|string|max:255',
        'formSlug' => 'required|string|max:255|regex:/^[a-z_]+$/',
        'formDescription' => 'nullable|string|max:500',
        'formPermissions' => 'array',
    ];

    public function mount()
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->roles = Role::with('permissions')->withCount('users')->get();
        $this->permissions = Permission::orderBy('group')->orderBy('id')->get();
        $this->users = User::orderBy('name')->get(['id', 'name', 'email']);
    }

    // ──── Create ────

    public function startCreate(): void
    {
        $this->resetForm();
        $this->editingRole = 'new';
    }

    // ──── Edit ────

    public function startEdit(int $id): void
    {
        $this->authorizeResourceAccess('manage_roles');
        $role = Role::with('permissions')->findOrFail($id);
        $this->editingRole = $id;
        $this->formName = $role->name;
        $this->formSlug = $role->slug;
        $this->formDescription = $role->description ?? '';
        $this->formPermissions = $role->permissions->pluck('slug')->toArray();
    }

    // ──── Save ────

    public function save(): void
    {
        $this->authorizeResourceAccess('manage_roles');
        $this->validate();

        if ($this->editingRole === 'new') {
            // Ensure slug is unique
            $this->validateOnly('formSlug');
            if (Role::where('slug', $this->formSlug)->exists()) {
                $this->addError('formSlug', '该标识已被使用。');

                return;
            }

            $role = Role::create([
                'name' => $this->formName,
                'slug' => $this->formSlug,
                'description' => $this->formDescription,
                'is_system' => false,
            ]);
        } else {
            $role = Role::findOrFail($this->editingRole);
            $role->update([
                'name' => $this->formName,
                'description' => $this->formDescription,
            ]);
        }

        // Sync permissions
        $permIds = Permission::whereIn('slug', $this->formPermissions)->pluck('id');
        $role->permissions()->sync($permIds);

        $this->resetForm();
        $this->refreshData();
        session()->flash('message', '角色保存成功。');
    }

    // ──── Delete ────

    public function confirmDelete(int $id): void
    {
        $this->deletingRole = $id;
    }

    public function deleteRole(): void
    {
        $this->authorizeResourceAccess('manage_roles');
        $role = Role::findOrFail($this->deletingRole);

        if ($role->is_system) {
            session()->flash('error', '系统内置角色不可删除。');
            $this->deletingRole = null;

            return;
        }

        $role->delete();
        $this->deletingRole = null;
        $this->refreshData();
        session()->flash('message', '角色已删除。');
    }

    // ──── User Assignment ────

    public function startAssign(int $roleId): void
    {
        $this->assigningRole = $roleId;
        $this->assignUserId = '';
    }

    public function assignUser(): void
    {
        $this->authorizeResourceAccess('manage_roles');
        $this->validate(['assignUserId' => 'required|exists:users,id']);

        $user = User::findOrFail($this->assignUserId);
        $role = Role::findOrFail($this->assigningRole);

        if (! $user->hasRole($role->slug)) {
            $user->assignRole($role->slug);
            session()->flash('message', "已为用户 {$user->name} 分配角色 {$role->name}。");
        } else {
            session()->flash('error', '该用户已有此角色。');
        }

        $this->assigningRole = null;
        $this->assignUserId = '';
        $this->refreshData();
    }

    public function removeUser(int $roleId, int $userId): void
    {
        $this->authorizeResourceAccess('manage_roles');
        $role = Role::findOrFail($roleId);
        $user = User::findOrFail($userId);

        $user->roles()->detach($role);
        $user->clearPermissionCache();
        $this->refreshData();
        session()->flash('message', "已从 {$user->name} 移除角色 {$role->name}。");
    }

    // ──── Helpers ────

    public function cancel(): void
    {
        $this->resetForm();
        $this->deletingRole = null;
        $this->assigningRole = null;
    }

    private function resetForm(): void
    {
        $this->editingRole = null;
        $this->formName = '';
        $this->formSlug = '';
        $this->formDescription = '';
        $this->formPermissions = [];
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.role-manager')
            ->layout('layouts.app')
            ->title('角色管理 — Project Monitor');
    }
}
