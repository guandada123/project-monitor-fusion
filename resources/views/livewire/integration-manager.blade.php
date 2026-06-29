<div>
    {{-- Premium Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold gradient-text">集成管理</h1>
            <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">管理外部数据源和通知渠道的连接</p>
        </div>
        <div class="flex items-center gap-2">
            <button wire:click="openCreate('github')"
                    class="btn-ghost px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                GitHub
            </button>
            <button wire:click="openCreate('lark')"
                    class="btn-premium px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="16" text-anchor="middle" font-size="10" fill="currentColor">飞</text></svg>
                飞书
            </button>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('message'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2"
         style="animation: fade-in 0.3s ease-out both; background: color-mix(in srgb, var(--color-success) 12%, transparent); color: var(--color-success); border: 1px solid color-mix(in srgb, var(--color-success) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="whitespace-pre-wrap">{{ session('message') }}</span>
    </div>
    @endif

    @if (session()->has('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2"
         style="animation: fade-in 0.3s ease-out both; background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span class="whitespace-pre-wrap">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Integration Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse ($integrations as $i => $integration)
        <div class="glass-card-intense p-6 relative group cursor-default"
             style="animation: scale-in 0.4s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 }}s both;"
             onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 40px -12px rgba(0,0,0,0.15)';"
             onmouseout="this.style.transform='';this.style.boxShadow='';">
            {{-- Hover Gradient Strip --}}
            <div class="absolute top-0 left-4 right-4 h-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"
                 style="background: linear-gradient(90deg, var(--color-accent), #6366f1);"></div>

            {{-- Status Dot --}}
            <div class="absolute top-4 right-4">
                <span class="relative flex h-2.5 w-2.5">
                    @if($integration->status === 'active')
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: var(--color-success);"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5" style="background: var(--color-success);"></span>
                    @else
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5" style="background: var(--color-text-tertiary);"></span>
                    @endif
                </span>
            </div>

            {{-- Type Icon --}}
            <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4 transition-all duration-300 group-hover:scale-110 group-hover:rotate-3"
                 style="background: color-mix(in srgb, var(--color-accent) 12%, transparent);">
                @if($integration->type === 'github')
                <svg class="w-5 h-5" style="color: var(--color-accent);" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                @elseif($integration->type === 'lark')
                <svg class="w-5 h-5" style="color: var(--color-accent);" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="3" fill="none" stroke="currentColor" stroke-width="1.5"/><text x="12" y="16" text-anchor="middle" font-size="10" fill="currentColor">飞</text></svg>
                @else
                <svg class="w-5 h-5" style="color: var(--color-accent);" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                @endif
            </div>

            {{-- Info --}}
            <h3 class="font-semibold mb-1" style="color: var(--color-text-primary);">{{ $integration->name }}</h3>
            <p class="text-xs mb-3" style="color: var(--color-text-tertiary);">
                {{ ucfirst($integration->type) }} ·
                <span style="color: {{ $integration->status === 'active' ? 'var(--color-success)' : 'var(--color-text-tertiary)' }};">
                    {{ $integration->status === 'active' ? '已连接' : '已断开' }}
                </span>
                @if($integration->last_sync_at)
                · 上次同步 {{ $integration->last_sync_at->diffForHumans() }}
                @endif
            </p>

            {{-- Error Message --}}
            @if($integration->error_message)
            <div class="mb-3 px-3 py-2 rounded-lg text-xs"
                 style="background: color-mix(in srgb, var(--color-danger) 10%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 20%, transparent);">
                {{ $integration->error_message }}
            </div>
            @endif

            {{-- Actions --}}
            <div class="flex items-center gap-2 pt-3 border-t flex-wrap" style="border-color: var(--color-border);">
                <button wire:click="testConnection({{ $integration->id }})"
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105"
                        style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">
                    <span wire:loading.remove wire:target="testConnection({{ $integration->id }})">测试连接</span>
                    <span wire:loading wire:target="testConnection({{ $integration->id }})">测试中...</span>
                </button>
                @if($integration->type === 'github')
                <button wire:click="syncNow({{ $integration->id }})"
                        wire:loading.attr="disabled"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105 flex items-center gap-1"
                        style="background: color-mix(in srgb, var(--color-accent) 12%, transparent); color: var(--color-accent);">
                    <span wire:loading.remove wire:target="syncNow({{ $integration->id }})">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        同步
                    </span>
                    <span wire:loading wire:target="syncNow({{ $integration->id }})">同步中...</span>
                </button>
                @endif
                <button wire:click="toggleStatus({{ $integration->id }})"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105"
                        style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">
                    {{ $integration->status === 'active' ? '停用' : '启用' }}
                </button>
                <button wire:click="edit({{ $integration->id }})"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105"
                        style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">
                    编辑
                </button>
                <button wire:click="confirmDelete({{ $integration->id }})"
                        onclick="return confirm('确定要删除此集成吗？此操作不可撤销。')"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-200 hover:scale-105 ml-auto"
                        style="background: color-mix(in srgb, var(--color-danger) 10%, transparent); color: var(--color-danger);">
                    删除
                </button>
            </div>
        </div>
        @empty
        {{-- Premium Empty State --}}
        <div class="col-span-full glass-card-intense p-12 text-center"
             style="animation: fade-in 0.5s ease-out 0.1s both;">
            <div class="w-20 h-20 mx-auto mb-5 rounded-2xl flex items-center justify-center"
                 style="background: linear-gradient(135deg, color-mix(in srgb, var(--color-accent) 12%, transparent), color-mix(in srgb, #6366f1 8%, transparent));">
                <svg class="w-9 h-9" style="color: var(--color-accent); opacity: 0.7;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <p class="text-base font-semibold mb-1" style="color: var(--color-text-secondary);">暂无集成</p>
            <p class="text-sm" style="color: var(--color-text-tertiary);">点击上方按钮添加数据源或通知渠道，开始监控你的项目</p>
        </div>
        @endforelse
    </div>

    {{-- Premium Modal --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data
         x-init="$el.querySelector('[autofocus]')?.focus()"
         style="background: rgba(0,0,0,0.45); backdrop-filter: blur(6px);">
        <div class="glass-card-intense p-6 w-full max-w-lg"
             style="animation: scale-in 0.25s cubic-bezier(0.16,1,0.3,1) both;"
             @click.outside="$wire.set('showModal', false)">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold gradient-text">
                    {{ $editingId ? '编辑集成' : '添加集成' }}
                </h3>
                <button wire:click="$set('showModal', false)"
                        class="p-1.5 rounded-lg transition-all duration-200 hover:scale-110 hover:rotate-90"
                        style="color: var(--color-text-tertiary); background: var(--color-bg-tertiary);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">
                {{-- Type --}}
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">类型</label>
                    <select wire:model.live="formType"
                            class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                            style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);">
                        <option value="github">GitHub</option>
                        <option value="lark">飞书 (Lark)</option>
                        <option value="dingtalk">钉钉 (DingTalk)</option>
                    </select>
                </div>

                {{-- Name --}}
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">名称</label>
                    <input type="text" wire:model="formName" autofocus
                           class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="我的 GitHub 仓库">
                </div>

                {{-- GitHub Fields --}}
                @if($formType === 'github')
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">Personal Access Token</label>
                    <input type="password" name="gh_token"
                           class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="{{ $editingId ? '留空则不修改' : 'ghp_...' }}">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">组织 (可选)</label>
                        <input type="text" wire:model="ghOrg"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                               placeholder="my-org">
                    </div>
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">仓库</label>
                        <input type="text" wire:model="ghRepo"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                               placeholder="my-repo">
                    </div>
                </div>
                @endif

                {{-- Lark Fields --}}
                @if($formType === 'lark')
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">Webhook URL</label>
                    <input type="url" wire:model="larkWebhook"
                           class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="https://open.feishu.cn/open-apis/bot/v2/hook/...">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">App ID</label>
                        <input type="text" wire:model="larkAppId"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);">
                    </div>
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">App Secret</label>
                        <input type="password" name="lark_app_secret"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                               placeholder="{{ $editingId ? '留空则不修改' : '请输入 App Secret' }}">
                    </div>
                </div>
                @endif

                {{-- DingTalk Fields --}}
                @if($formType === 'dingtalk')
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">Webhook URL</label>
                    <input type="url" wire:model="dtWebhook"
                           class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="https://oapi.dingtalk.com/robot/send?access_token=...">
                </div>
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">加签密钥 (可选)</label>
                    <input type="password" name="dt_secret"
                           class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="{{ $editingId ? '留空则不修改' : 'SEC...' }}">
                </div>
                @endif

                {{-- Status (only for editing) --}}
                @if($editingId)
                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">状态</label>
                    <select wire:model="formStatus"
                            class="w-full px-3 py-2.5 rounded-xl border text-sm focus:ring-2 focus:ring-offset-0 focus:outline-none transition-all duration-200"
                            style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);">
                        <option value="active">已启用</option>
                        <option value="inactive">已停用</option>
                    </select>
                </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="btn-ghost flex-1 px-4 py-2.5 rounded-xl text-sm font-medium">
                        取消
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="btn-premium flex-1 px-4 py-2.5 rounded-xl text-sm font-medium font-semibold">
                        <span wire:loading.remove wire:target="save">保存</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-1.5">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            保存中...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
