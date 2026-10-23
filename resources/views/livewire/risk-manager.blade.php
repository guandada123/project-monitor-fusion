<div x-data="{ showFilters: false }">
    {{-- Flash Messages --}}
    @if(session('suggestion_result'))
    <div class="glass-card-intense p-4 mb-6 flex items-center gap-3" style="animation: slide-up 0.35s cubic-bezier(0.16,1,0.3,1) both;
                background: color-mix(in srgb, var(--color-success) 10%, transparent); border: 1px solid color-mix(in srgb, var(--color-success) 20%, transparent);">
        <svg class="w-5 h-5 flex-shrink-0" style="color: var(--color-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm font-medium" style="color: var(--color-text-primary);">{{ session('suggestion_result') }}</span>
    </div>
    @endif

    {{-- Page Header --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.45s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">风险中心</h1>
                    <span class="badge badge-danger">{{ $openRisks }} 待处理</span>
                </div>
                <p class="text-sm" style="color: var(--color-text-secondary);">集中管理所有项目风险，查看自动生成的处理建议</p>
            </div>
            <div class="flex items-center gap-3">
                <button class="btn-ghost text-sm" wire:click="$refresh">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    刷新
                </button>
                <button class="btn-premium text-sm" wire:click="$dispatch('open-export-panel', { entityType: 'risk' })">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    导出风险
                </button>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.1s both;">
        @foreach([
            ['label' => '待处理', 'value' => $stats['open'] ?? 0, 'color' => '#ef4444', 'icon' => 'alert'],
            ['label' => '已确认', 'value' => $stats['acknowledged'] ?? 0, 'color' => '#f59e0b', 'icon' => 'eye'],
            ['label' => '严重风险', 'value' => $stats['critical'] ?? 0, 'color' => '#dc2626', 'icon' => 'shield', 'sub' => 'critical + high: ' . (($stats['critical'] ?? 0) + ($stats['high'] ?? 0))],
            ['label' => '已有建议', 'value' => $stats['withSuggestion'] ?? 0, 'color' => '#8b5cf6', 'icon' => 'lightbulb', 'sub' => ($stats['noSuggestion'] ?? 0) . ' 条待生成'],
        ] as $i => $kpi)
        <div class="stat-card group cursor-default"
             style="--card-accent: {{ $kpi['color'] }};
                    animation: scale-in 0.4s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 + 0.15 }}s both;">
            <div class="relative z-10">
                <div class="flex items-start justify-between mb-3">
                    <span class="text-xs font-medium tracking-wide" style="color: var(--color-text-tertiary);">{{ $kpi['label'] }}</span>
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110"
                         style="background: {{ $kpi['color'] }}15;">
                        @if($kpi['icon'] === 'alert')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif($kpi['icon'] === 'eye')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        @elseif($kpi['icon'] === 'shield')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        @else
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        @endif
                    </div>
                </div>
                <div class="text-2xl font-extrabold tracking-tight tabular-nums" style="color: var(--color-text-primary);"
                     x-data="{ display: 0 }"
                     x-intersect.once="let target = {{ $kpi['value'] }}; let step = Math.max(target / 30, 0.5); let interval = setInterval(() => { display = Math.min(display + step, target); if (display >= target) clearInterval(interval) }, 20);">
                    <span x-text="Math.round(display)"></span>
                </div>
                @if(isset($kpi['sub']))
                <div class="mt-1 text-xs" style="color: var(--color-text-tertiary);">{{ $kpi['sub'] }}</div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Filters & Actions --}}
    <div class="glass-card-intense p-5 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.2s both;">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Status Filter --}}
            <select wire:model.live="statusFilter" class="rounded-xl px-4 py-2 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none transition-all duration-200"
                    style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
                <option value="open">● 待处理</option>
                <option value="acknowledged">◐ 已确认</option>
                <option value="closed">○ 已关闭</option>
                <option value="all">全部</option>
            </select>

            {{-- Severity Filter --}}
            <select wire:model.live="severityFilter" class="rounded-xl px-4 py-2 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none transition-all duration-200"
                    style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
                <option value="all">所有级别</option>
                <option value="critical">严重</option>
                <option value="high">高</option>
                <option value="medium">中</option>
                <option value="low">低</option>
            </select>

            {{-- Project Filter --}}
            <select wire:model.live="projectFilter" class="rounded-xl px-4 py-2 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none transition-all duration-200"
                    style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
                <option value="all">所有项目</option>
                @foreach($projects as $project)
                <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>

            {{-- Rule Filter --}}
            <select wire:model.live="ruleFilter" class="rounded-xl px-4 py-2 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none transition-all duration-200"
                    style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
                <option value="all">所有规则</option>
                @foreach($ruleOptions as $rule)
                <option value="{{ $rule }}">{{ $rule }}</option>
                @endforeach
            </select>

            {{-- Search --}}
            <div class="relative flex-1 min-w-[180px]">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="搜索风险标题..."
                       class="w-full rounded-xl pl-9 pr-4 py-2 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none transition-all duration-200"
                       style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);">
                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            {{-- Generate All Suggestions --}}
            @if(($stats['noSuggestion'] ?? 0) > 0 && $statusFilter === 'open')
            <button class="btn-premium text-sm" wire:click="generateAllSuggestions" wire:loading.attr="disabled">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <span wire:loading.remove wire:target="generateAllSuggestions">生成全部建议</span>
                <span wire:loading wire:target="generateAllSuggestions">生成中…</span>
            </button>
            @endif

            {{-- Bulk mode toggle --}}
            <button class="btn-ghost text-sm" wire:click="$set('bulkMode', !bulkMode)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                {{ $bulkMode ? '退出批量' : '批量操作' }}
            </button>
        </div>

        {{-- Bulk Actions Bar --}}
        @if($bulkMode)
        <div class="mt-4 pt-4 border-t flex items-center gap-3" style="border-color: var(--color-border);">
            <span class="text-xs font-medium" style="color: var(--color-text-tertiary);">已选 {{ count($selectedIds) }} 项</span>
            <button class="btn-premium text-xs" wire:click="bulkGenerateSuggestions" @if(empty($selectedIds)) disabled @endif>
                生成建议
            </button>
            <button class="btn-ghost text-xs" wire:click="bulkAcknowledge" @if(empty($selectedIds)) disabled @endif>
                批量确认
            </button>
            <button class="btn-ghost text-xs" wire:click="$set('selectedIds', [])">清除选择</button>
        </div>
        @endif
    </div>

    {{-- Risk List --}}
    <div class="space-y-4">
        @forelse($risks as $risk)
        <div class="glass-card-intense p-5 transition-all duration-300 group/risk"
             style="animation: slide-up 0.35s cubic-bezier(0.16,1,0.3,1) both;
                    border-left: 3px solid {{ $risk->severity === 'critical' ? '#dc2626' : ($risk->severity === 'high' ? '#f59e0b' : ($risk->severity === 'medium' ? '#3b82f6' : '#94a3b8')) }};">
            <div class="flex items-start gap-4">
                {{-- Bulk Checkbox --}}
                @if($bulkMode)
                <div class="pt-1">
                    <input type="checkbox" value="{{ $risk->id }}"
                           @checked(in_array($risk->id, $selectedIds))
                           wire:click="toggleSelect({{ $risk->id }})"
                           class="w-4 h-4 rounded accent-[var(--color-accent)] cursor-pointer">
                </div>
                @endif

                {{-- Severity Indicator --}}
                <div class="flex-shrink-0 pt-0.5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover/risk:scale-110"
                         style="background: {{ $risk->severity === 'critical' ? '#dc262615' : ($risk->severity === 'high' ? '#f59e0b15' : ($risk->severity === 'medium' ? '#3b82f615' : '#94a3b815')) }};">
                        @if($risk->severity === 'critical')
                        <svg class="w-5 h-5" style="color: #dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                        @elseif($risk->severity === 'high')
                        <svg class="w-5 h-5" style="color: #f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                        <svg class="w-5 h-5" style="color: #3b82f6;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ $risk->title }}</h3>
                                <span class="badge {{ $risk->severity === 'critical' ? 'badge-danger' : ($risk->severity === 'high' ? 'badge-warning' : ($risk->severity === 'medium' ? 'badge-info' : '')) }}">
                                    {{ $risk->severity === 'critical' ? '严重' : ($risk->severity === 'high' ? '高' : ($risk->severity === 'medium' ? '中' : '低')) }}
                                </span>
                                <span class="badge {{ $risk->status === 'open' ? 'badge-danger' : ($risk->status === 'acknowledged' ? 'badge-warning' : 'badge-success') }}">
                                    {{ $risk->status === 'open' ? '待处理' : ($risk->status === 'acknowledged' ? '已确认' : '已关闭') }}
                                </span>
                            </div>
                            <p class="text-xs mb-2" style="color: var(--color-text-secondary);">{{ $risk->detail }}</p>
                            <div class="flex flex-wrap items-center gap-3 text-[11px]" style="color: var(--color-text-tertiary);">
                                <span>📁 {{ $risk->project?->name ?? '全局' }}</span>
                                <span>🔍 {{ $risk->rule_key }}</span>
                                <span>📅 {{ $risk->last_seen_at?->diffForHumans() }}</span>
                                <span>🔄 第 {{ $risk->occurrence_count }} 次出现</span>
                            </div>
                        </div>
                    </div>

                    {{-- Suggestion Section --}}
                    @if($risk->suggestion)
                    <div class="mt-4 p-4 rounded-xl border transition-all duration-200"
                         style="background: color-mix(in srgb, var(--color-accent) 3%, transparent);
                                border-color: color-mix(in srgb, var(--color-accent) 12%, transparent);">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                                <span class="text-xs font-semibold" style="color: var(--color-text-primary);">处理建议</span>
                                @if(isset($risk->suggestion_data['priority']))
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                      style="background: {{ $risk->suggestion_data['priority'] === 'urgent' ? '#dc262615' : ($risk->suggestion_data['priority'] === 'high' ? '#f59e0b15' : '#3b82f615') }};
                                             color: {{ $risk->suggestion_data['priority'] === 'urgent' ? '#dc2626' : ($risk->suggestion_data['priority'] === 'high' ? '#f59e0b' : '#3b82f6') }};">
                                    {{ $risk->suggestion_data['priority'] === 'urgent' ? '紧急' : ($risk->suggestion_data['priority'] === 'high' ? '优先' : '常规') }}
                                </span>
                                @endif
                                @if(isset($risk->suggestion_data['estimated_effort']))
                                <span class="text-[10px] px-1.5 py-0.5 rounded" style="background: rgba(148,163,184,0.15); color: var(--color-text-tertiary);">
                                    预计 {{ $risk->suggestion_data['estimated_effort'] }}
                                </span>
                                @endif
                            </div>
                        </div>
                        <div class="text-xs leading-relaxed whitespace-pre-line" style="color: var(--color-text-secondary);">{{ $risk->suggestion }}</div>

                        {{-- Action Steps --}}
                        @if(isset($risk->suggestion_data['action_steps']) && count($risk->suggestion_data['action_steps']) > 0)
                        <div class="mt-3 space-y-1">
                            @foreach($risk->suggestion_data['action_steps'] as $stepIndex => $step)
                            <div class="flex items-start gap-2 text-xs" style="color: var(--color-text-tertiary);">
                                <span class="mt-0.5">{{ $step }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @elseif($risk->status === 'open')
                    <div class="mt-3">
                        <button class="btn-ghost text-xs" wire:click="generateSuggestion({{ $risk->id }})" wire:loading.attr="disabled">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                            生成建议
                        </button>
                    </div>
                    @endif

                    {{-- Actions --}}
                    @if($risk->status === 'open')
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t" style="border-color: var(--color-border);">
                        @if($risk->suggestion)
                        <button class="btn-premium text-xs" wire:click="applySuggestion({{ $risk->id }})" wire:loading.attr="disabled">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            应用建议
                        </button>
                        @endif
                        <button class="btn-ghost text-xs" wire:click="acknowledge({{ $risk->id }})" wire:loading.attr="disabled">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            确认已读
                        </button>
                        <button class="btn-ghost text-xs" style="color: var(--color-danger);" wire:click="confirmDismiss({{ $risk->id }})">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            忽略
                        </button>
                    </div>
                    @elseif($risk->status === 'closed')
                    <div class="flex items-center gap-2 mt-4 pt-3 border-t" style="border-color: var(--color-border);">
                        <button class="btn-ghost text-xs" wire:click="reopen({{ $risk->id }})">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            重新打开
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="glass-card-intense p-12 text-center" style="animation: slide-up 0.35s cubic-bezier(0.16,1,0.3,1) both;">
            <div class="w-14 h-14 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: rgba(16,185,129,0.08);">
                <svg class="w-7 h-7" style="color: var(--color-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="font-semibold" style="color: var(--color-text-primary);">没有匹配的风险记录</p>
            <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">尝试调整筛选条件，或一切安好 ✨</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($risks->hasPages())
    <div class="mt-6">
        {{ $risks->links() }}
    </div>
    @endif

    {{-- Dismiss Dialog --}}
    <div x-cloak x-show="$wire.dismissingId !== null"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);"
         x-transition:enter="transition-all duration-200 ease-out"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-all duration-150 ease-in"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="$wire.cancelDismiss()">
        <div class="glass-card-intense p-6 max-w-md w-full" @click.stop>
            <h3 class="font-semibold mb-2" style="color: var(--color-text-primary);">忽略风险</h3>
            <p class="text-xs mb-4" style="color: var(--color-text-tertiary);">提供忽略原因（可选），风险将被标记为已关闭。</p>
            <textarea wire:model="dismissReason" placeholder="忽略原因…"
                      class="w-full rounded-xl p-3 text-sm border focus:ring-2 focus:ring-[var(--color-accent)]/30 outline-none resize-none transition-all duration-200"
                      style="background: var(--color-bg-secondary); border-color: var(--color-border); color: var(--color-text-primary);"
                      rows="3"></textarea>
            <div class="flex items-center justify-end gap-3 mt-4">
                <button class="btn-ghost text-sm" wire:click="cancelDismiss">取消</button>
                <button class="btn-premium text-sm" style="background: var(--color-danger);" wire:click="dismiss">确认忽略</button>
            </div>
        </div>
    </div>
</div>
