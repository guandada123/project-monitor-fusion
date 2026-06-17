<div>
    {{-- Page Header — Premium Glass --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">指标构建器</h1>
                <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">可视化定义和组装自定义 KPI 指标 · JSONLogic DSL</p>
            </div>
            <button wire:click="openCreate"
                    class="btn-premium text-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                新建指标
            </button>
        </div>
    </div>

    {{-- Flash Messages — Premium --}}
    @if (session()->has('message'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 animate-fade-in"
         style="background: color-mix(in srgb, var(--color-success) 12%, transparent); color: var(--color-success); border: 1px solid color-mix(in srgb, var(--color-success) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('message') }}
    </div>
    @endif
    @if (session()->has('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2 animate-fade-in"
         style="background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- Filter Pills — Premium --}}
    <div class="flex items-center gap-2 mb-6" style="animation: fade-in 0.35s ease-out 0.1s both;">
        @foreach([
            ['label' => '全部', 'value' => ''],
            ['label' => '系统指标', 'value' => 'system'],
            ['label' => '自定义指标', 'value' => 'custom'],
        ] as $filter)
        <button wire:click="$set('filterKind', '{{ $filter['value'] }}')"
                class="px-4 py-2 rounded-xl text-xs font-semibold transition-all duration-300 hover:scale-105 active:scale-95"
                style="background: {{ $filterKind === $filter['value'] ? 'var(--color-accent)' : 'var(--color-bg-tertiary)' }};
                       color: {{ $filterKind === $filter['value'] ? '#fff' : 'var(--color-text-secondary)' }};
                       box-shadow: {{ $filterKind === $filter['value'] ? '0 4px 16px -4px var(--color-accent)40' : 'none' }};">
            {{ $filter['label'] }}
        </button>
        @endforeach
    </div>

    {{-- Metrics Table — Premium Glass --}}
    <div class="glass-card-intense overflow-hidden" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.15s both;">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b" style="background: var(--color-bg-tertiary); border-color: var(--color-border);">
                    <th class="text-left px-4 py-3.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">指标</th>
                    <th class="text-left px-4 py-3.5 text-xs font-semibold uppercase tracking-wider hidden md:table-cell" style="color: var(--color-text-tertiary);">类型</th>
                    <th class="text-left px-4 py-3.5 text-xs font-semibold uppercase tracking-wider hidden md:table-cell" style="color: var(--color-text-tertiary);">关联项目</th>
                    <th class="text-center px-4 py-3.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">当前值</th>
                    <th class="text-left px-4 py-3.5 text-xs font-semibold uppercase tracking-wider hidden lg:table-cell" style="color: var(--color-text-tertiary);">上次计算</th>
                    <th class="text-right px-4 py-3.5 text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($metrics as $i => $metric)
                <tr class="border-b transition-all duration-200 group/row"
                    style="border-color: var(--color-border);
                           animation: fade-in 0.3s ease-out {{ $i * 0.05 + 0.15 }}s both;"
                    onmouseover="this.style.background='var(--color-bg-tertiary)';this.style.boxShadow='inset 3px 0 0 var(--color-accent)';"
                    onmouseout="this.style.background='transparent';this.style.boxShadow='none';">
                    <td class="px-4 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 transition-transform duration-300 group-hover/row:scale-110"
                                 style="background: {{ $metric->kind === 'system' ? 'color-mix(in srgb, var(--color-accent) 12%, transparent)' : 'color-mix(in srgb, var(--color-info) 12%, transparent)' }};">
                                <svg class="w-4.5 h-4.5" style="color: {{ $metric->kind === 'system' ? 'var(--color-accent)' : 'var(--color-info)' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold" style="color: var(--color-text-primary);">{{ $metric->name }}</p>
                                <p class="text-xs font-mono mt-0.5" style="color: var(--color-text-tertiary);">{{ $metric->key }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 hidden md:table-cell">
                        <span class="badge {{ $metric->kind === 'system' ? 'badge-info' : 'badge-success' }}">
                            {{ $metric->kind === 'system' ? '系统' : '自定义' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 hidden md:table-cell text-sm" style="color: var(--color-text-secondary);">
                        {{ $metric->project?->name ?? '全局' }}
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        @if($metric->current_value !== null)
                        <span class="font-bold font-mono text-sm" style="color: var(--color-accent);">{{ number_format($metric->current_value, 2) }}</span>
                        @else
                        <span class="text-xs" style="color: var(--color-text-tertiary);">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 hidden lg:table-cell text-xs" style="color: var(--color-text-tertiary);">
                        {{ $metric->last_computed_at ? $metric->last_computed_at->diffForHumans() : '从未' }}
                    </td>
                    <td class="px-4 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-1 opacity-60 group-hover/row:opacity-100 transition-opacity duration-200">
                            <button wire:click="computeNow({{ $metric->id }})" title="立即计算"
                                    class="p-2 rounded-lg transition-all duration-200 hover:scale-110"
                                    style="color: var(--color-success);"
                                    onmouseover="this.style.background='color-mix(in srgb, var(--color-success) 12%, transparent)';"
                                    onmouseout="this.style.background='transparent';">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            </button>
                            <button wire:click="edit({{ $metric->id }})" title="编辑"
                                    class="p-2 rounded-lg transition-all duration-200 hover:scale-110"
                                    style="color: var(--color-text-tertiary);"
                                    onmouseover="this.style.background='color-mix(in srgb, var(--color-text-tertiary) 10%, transparent)';"
                                    onmouseout="this.style.background='transparent';">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            @if($metric->kind !== 'system')
                            <button wire:click="delete({{ $metric->id }})" title="删除"
                                    onclick="return confirm('确定删除指标「{{ $metric->name }}」? ')"
                                    class="p-2 rounded-lg transition-all duration-200 hover:scale-110"
                                    style="color: var(--color-danger);"
                                    onmouseover="this.style.background='color-mix(in srgb, var(--color-danger) 12%, transparent)';"
                                    onmouseout="this.style.background='transparent';">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-20 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: color-mix(in srgb, var(--color-accent) 6%, transparent);">
                            <svg class="w-8 h-8" style="color: var(--color-text-tertiary); opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <p class="text-sm font-medium" style="color: var(--color-text-secondary);">暂无指标</p>
                        <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">点击「新建指标」开始配置自定义 KPI</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal — Premium --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="animation: fade-in 0.2s ease-out both; background: rgba(0,0,0,0.45); backdrop-filter: blur(6px);">
        <div class="glass-card-intense p-6 w-full max-w-xl max-h-[85vh] overflow-y-auto custom-scrollbar"
             style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) both;">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">
                        {{ $editingId ? '编辑指标' : '新建指标' }}
                    </h3>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">使用 JSONLogic DSL 定义计算规则</p>
                </div>
                <button wire:click="$set('showModal', false)"
                        class="p-2 rounded-lg transition-all duration-200 hover:scale-110 hover:rotate-90"
                        style="color: var(--color-text-tertiary);"
                        onmouseover="this.style.background='var(--color-bg-tertiary)';"
                        onmouseout="this.style.background='transparent';">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">标识 Key</label>
                        <input type="text" wire:model="formKey"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                               placeholder="completion_rate">
                    </div>
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">名称</label>
                        <input type="text" wire:model="formName"
                               class="w-full px-3 py-2.5 rounded-xl border text-sm transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none"
                               style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                               placeholder="完成率">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">类型</label>
                        <select wire:model="formKind"
                                class="w-full px-3 py-2.5 rounded-xl border text-sm transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none"
                                style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);">
                            <option value="custom">自定义</option>
                            <option value="system">系统</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">关联项目</label>
                        <select wire:model="formProjectId"
                                class="w-full px-3 py-2.5 rounded-xl border text-sm transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none"
                                style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);">
                            <option value="">全局指标</option>
                            @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">调度 Cron</label>
                    <input type="text" wire:model="formScheduleCron"
                           class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                           placeholder="0 8 * * *">
                    <p class="text-xs mt-1.5" style="color: var(--color-text-tertiary);">Cron 表达式，默认每日 08:00 计算</p>
                </div>

                <div>
                    <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">JSONLogic 定义</label>
                    <textarea wire:model="formDefinition" rows="8"
                              class="w-full px-3 py-2.5 rounded-xl border text-sm font-mono transition-all duration-200 focus:ring-2 focus:ring-offset-0 focus:border-transparent outline-none resize-y"
                              style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"></textarea>
                    @error('formDefinition')
                    <p class="text-xs mt-1.5 font-medium" style="color: var(--color-danger);">{{ $message }}</p>
                    @enderror
                    <p class="text-xs mt-1.5" style="color: var(--color-text-tertiary);">使用 JSONLogic 语法定义指标计算规则</p>
                </div>

                <div class="flex items-center gap-3 pt-3">
                    <button type="button" wire:click="$set('showModal', false)"
                            class="flex-1 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 hover:bg-opacity-80"
                            style="background: var(--color-bg-tertiary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                        取消
                    </button>
                    <button type="submit" wire:loading.attr="disabled"
                            class="flex-1 px-4 py-3 rounded-xl text-sm font-semibold transition-all duration-200 hover:scale-[1.02] active:scale-95"
                            style="background: var(--color-accent); color: #fff; box-shadow: 0 4px 16px -4px var(--color-accent)40;">
                        <span wire:loading.remove wire:target="save">保存指标</span>
                        <span wire:loading wire:target="save" class="flex items-center justify-center gap-1">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            保存中...
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
