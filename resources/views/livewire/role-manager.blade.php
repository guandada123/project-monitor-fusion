<div>
    {{-- Premium Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold gradient-text">角色管理</h1>
        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">管理角色 · 分配权限 · 用户授权</p>
    </div>

    @if (session()->has('message'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2"
         style="animation: fade-in 0.3s ease-out both; background: color-mix(in srgb, var(--color-success) 12%, transparent); color: var(--color-success); border: 1px solid color-mix(in srgb, var(--color-success) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        {{ session('message') }}
    </div>
    @endif

    @if (session()->has('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2"
         style="animation: fade-in 0.3s ease-out both; background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ session('error') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Role List --}}
        <div class="lg:col-span-2 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold" style="color: var(--color-text-primary);">角色列表</h3>
                <button wire:click="startCreate"
                        class="btn-premium px-4 py-2 rounded-xl text-sm font-medium font-semibold flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    新建角色
                </button>
            </div>

            @foreach($roles as $i => $role)
            <div class="glass-card-intense p-5 transition-all duration-300 group"
                 style="animation: scale-in 0.35s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 + 0.1 }}s both;"
                 onmouseover="this.style.transform='translateX(4px)';this.style.boxShadow='0 8px 30px -8px rgba(0,0,0,0.12), inset 2px 0 0 var(--color-accent)';"
                 onmouseout="this.style.transform='';this.style.boxShadow='';">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h4 class="font-semibold group-hover:translate-x-1 transition-transform duration-300" style="color: var(--color-text-primary);">{{ $role->name }}</h4>
                            <span class="px-2 py-0.5 rounded-md text-xs font-mono"
                                  style="background: var(--color-bg-tertiary); color: var(--color-text-tertiary);">
                                {{ $role->slug }}
                            </span>
                            @if($role->is_system)
                            <span class="px-2 py-0.5 rounded-md text-xs font-medium"
                                  style="background: color-mix(in srgb, var(--color-accent) 15%, transparent); color: var(--color-accent); border: 1px solid color-mix(in srgb, var(--color-accent) 30%, transparent);">
                                系统内置
                            </span>
                            @endif
                        </div>
                        @if($role->description)
                        <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">{{ $role->description }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0 ml-4">
                        <span class="text-xs font-medium px-2 py-0.5 rounded-md"
                              style="background: color-mix(in srgb, var(--color-accent) 10%, transparent); color: var(--color-accent);">
                            {{ $role->users_count }} 用户
                        </span>
                        <button wire:click="startEdit({{ $role->id }})"
                                class="p-1.5 rounded-lg transition-all duration-200 hover:scale-110"
                                style="background: color-mix(in srgb, var(--color-accent) 8%, transparent); color: var(--color-accent);" title="编辑">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        <button wire:click="startAssign({{ $role->id }})"
                                class="p-1.5 rounded-lg transition-all duration-200 hover:scale-110"
                                style="background: color-mix(in srgb, var(--color-success) 8%, transparent); color: var(--color-success);" title="分配用户">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </button>
                        @if(! $role->is_system)
                        <button wire:click="confirmDelete({{ $role->id }})"
                                class="p-1.5 rounded-lg transition-all duration-200 hover:scale-110"
                                style="background: color-mix(in srgb, var(--color-danger) 8%, transparent); color: var(--color-danger);" title="删除">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Permissions tags --}}
                <div class="flex flex-wrap gap-1.5">
                    @foreach($role->permissions as $perm)
                    <span class="px-2.5 py-0.5 rounded-md text-xs font-medium transition-all duration-200 hover:scale-105"
                          style="background: color-mix(in srgb, var(--color-accent) 10%, transparent); color: var(--color-accent); border: 1px solid color-mix(in srgb, var(--color-accent) 25%, transparent); box-shadow: 0 1px 3px color-mix(in srgb, var(--color-accent) 8%, transparent);">
                        {{ $perm->name }}
                    </span>
                    @endforeach
                </div>

                {{-- Assigned users --}}
                @if($role->users_count > 0)
                <div class="mt-3 pt-3 border-t" style="border-color: var(--color-border);">
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-xs" style="color: var(--color-text-tertiary);">成员：</span>
                        @foreach($role->users as $user)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs group/user transition-all duration-200"
                              style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">
                            {{ $user->name }}
                            <button wire:click="removeUser({{ $role->id }}, {{ $user->id }})"
                                    class="opacity-0 group-hover/user:opacity-100 transition-all duration-200 ml-0.5 p-0.5 rounded hover:scale-110"
                                    style="color: var(--color-danger);"
                                    title="移除">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- Form Panel / Info --}}
        <div class="space-y-4">
            @if($editingRole)
            {{-- Create/Edit Form --}}
            <div class="glass-card-intense p-5"
                 style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) both;">
                <h3 class="font-semibold mb-4 gradient-text">
                    {{ $editingRole === 'new' ? '新建角色' : '编辑角色' }}
                </h3>

                <form wire:submit="save" class="space-y-4">
                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">角色名称</label>
                        <input type="text" wire:model="formName"
                               class="w-full px-3 py-2.5 rounded-xl text-sm border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0"
                               style="background: var(--color-bg-tertiary); color: var(--color-text-primary); border-color: var(--color-border); --tw-ring-color: var(--color-accent);"
                               placeholder="例：项目经理">
                        @error('formName') <p class="text-xs mt-1" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                    </div>

                    {{-- Slug --}}
                    @if($editingRole === 'new')
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">
                            标识符
                            <span class="font-normal" style="color: var(--color-text-tertiary);">(只允许小写字母和下划线)</span>
                        </label>
                        <input type="text" wire:model="formSlug"
                               class="w-full px-3 py-2.5 rounded-xl text-sm font-mono border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0"
                               style="background: var(--color-bg-tertiary); color: var(--color-text-primary); border-color: var(--color-border); --tw-ring-color: var(--color-accent);"
                               placeholder="例：project_manager">
                        @error('formSlug') <p class="text-xs mt-1" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                    </div>
                    @endif

                    {{-- Description --}}
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">描述</label>
                        <textarea wire:model="formDescription" rows="2"
                                  class="w-full px-3 py-2.5 rounded-xl text-sm border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0 resize-none"
                                  style="background: var(--color-bg-tertiary); color: var(--color-text-primary); border-color: var(--color-border); --tw-ring-color: var(--color-accent);"
                                  placeholder="该角色的职责说明..."></textarea>
                        @error('formDescription') <p class="text-xs mt-1" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                    </div>

                    {{-- Permissions --}}
                    <div>
                        <label class="block text-sm font-medium mb-2" style="color: var(--color-text-secondary);">权限分配</label>

                        @php $currentGroup = ''; @endphp
                        @foreach($permissions as $perm)
                            @if($perm->group !== $currentGroup)
                                @php $currentGroup = $perm->group; @endphp
                                <div class="text-xs font-semibold uppercase tracking-widest mt-3 mb-1.5 px-1"
                                     style="color: var(--color-accent); opacity: 0.8;">
                                    {{ $currentGroup }}
                                </div>
                            @endif
                            <label class="flex items-center gap-2.5 py-1.5 px-1.5 rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-bg-tertiary)]">
                                <input type="checkbox"
                                       wire:model="formPermissions"
                                       value="{{ $perm->slug }}"
                                       class="rounded accent-[var(--color-accent)] focus:ring-2 focus:ring-offset-0"
                                       style="--tw-ring-color: var(--color-accent);">
                                <span class="text-sm" style="color: var(--color-text-primary);">{{ $perm->name }}</span>
                                <span class="text-xs font-mono opacity-50 ml-auto" style="color: var(--color-text-tertiary);">{{ $perm->slug }}</span>
                            </label>
                        @endforeach
                        @error('formPermissions') <p class="text-xs mt-1" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                    </div>

                    {{-- Buttons --}}
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="btn-premium px-5 py-2.5 rounded-xl text-sm font-medium font-semibold">
                            保存
                        </button>
                        <button type="button" wire:click="cancel"
                                class="btn-ghost px-5 py-2.5 rounded-xl text-sm font-medium">
                            取消
                        </button>
                    </div>
                </form>
            </div>
            @endif

            @if($assigningRole)
            {{-- Assign User Form --}}
            <div class="glass-card-intense p-5"
                 style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) both;">
                <h3 class="font-semibold mb-4 gradient-text">分配用户</h3>
                <form wire:submit="assignUser" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1.5" style="color: var(--color-text-secondary);">选择用户</label>
                        <select wire:model="assignUserId"
                                class="w-full px-3 py-2.5 rounded-xl text-sm border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-0"
                                style="background: var(--color-bg-tertiary); color: var(--color-text-primary); border-color: var(--color-border); --tw-ring-color: var(--color-accent);">
                            <option value="">— 请选择 —</option>
                            @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                            @endforeach
                        </select>
                        @error('assignUserId') <p class="text-xs mt-1" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="submit"
                                class="btn-premium px-5 py-2.5 rounded-xl text-sm font-medium font-semibold">
                            分配
                        </button>
                        <button type="button" wire:click="cancel"
                                class="btn-ghost px-5 py-2.5 rounded-xl text-sm font-medium">
                            取消
                        </button>
                    </div>
                </form>
            </div>
            @endif

            @if($deletingRole)
            {{-- Delete Confirmation --}}
            <div class="glass-card-intense p-5"
                 style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) both; border-color: color-mix(in srgb, var(--color-danger) 30%, transparent);">
                <h3 class="font-semibold mb-2 flex items-center gap-2" style="color: var(--color-danger);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    确认删除
                </h3>
                <p class="text-sm mb-4" style="color: var(--color-text-secondary);">
                    删除角色后，已分配给用户的该角色将被自动移除。此操作不可恢复。
                </p>
                <div class="flex items-center gap-3">
                    <button wire:click="deleteRole"
                            class="px-5 py-2.5 rounded-xl text-sm font-medium font-semibold transition-all duration-200 hover:scale-105 active:scale-95"
                            style="background: var(--color-danger); color: #fff; box-shadow: 0 4px 15px -6px var(--color-danger);">
                        确认删除
                    </button>
                    <button wire:click="cancel"
                            class="btn-ghost px-5 py-2.5 rounded-xl text-sm font-medium">
                        取消
                    </button>
                </div>
            </div>
            @endif

            {{-- Info card when nothing selected --}}
            @if(!$editingRole && !$assigningRole && !$deletingRole)
            <div class="glass-card-intense p-5"
                 style="animation: fade-in 0.4s ease-out 0.15s both;">
                <div class="text-center py-8">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center"
                         style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-accent) 10%, transparent), color-mix(in srgb, #6366f1 6%, transparent));">
                        <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: var(--color-accent);">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium" style="color: var(--color-text-secondary);">选择一个角色进行管理</p>
                    <p class="text-xs mt-1.5" style="color: var(--color-text-tertiary);">点击编辑按钮修改权限，或新建自定义角色</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
