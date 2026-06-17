<div
    x-data="{
        open: $wire.$entangle('show'),
        selected: $wire.$entangle('selectedIndex'),
        inputRef: null,
        panelRef: null,

        init() {
            this.inputRef = this.$refs.searchInput;
            this.panelRef = this.$refs.searchPanel;

            // 点击外部关闭
            document.addEventListener('click', (e) => {
                if (!this.$el.contains(e.target)) {
                    $wire.closeSearch();
                }
            });

            // Cmd/Ctrl+K 快捷键打开搜索
            document.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.inputRef?.focus();
                    if ($wire.query.length > 0) {
                        $wire.updatedQuery();
                    }
                }
                // Esc 关闭
                if (e.key === 'Escape') {
                    $wire.closeSearch();
                    this.inputRef?.blur();
                }
            });
        },

        focusInput() {
            this.$nextTick(() => this.inputRef?.focus());
        }
    }"
    class="relative w-full max-w-lg"
    @keydown.meta.k.window="focusInput()"
    @keydown.ctrl.k.window="focusInput()"
>
    <!-- Search Input -->
    <div class="relative group">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"
             style="color: var(--color-text-tertiary);">
            <svg class="w-4.5 h-4.5 transition-all duration-300 group-focus-within:text-accent"
                 :class="open && 'text-accent'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>

        <input
            x-ref="searchInput"
            type="text"
            wire:model.live.debounce.250ms="query"
            placeholder="搜索项目、任务、风险... (⌘K)"
            @focus="$wire.show = $wire.query.length > 0"
            @keydown.escape="$wire.closeSearch()"
            @keydown.down.prevent="$wire.selectNext()"
            @keydown.up.prevent="$wire.selectPrev()"
            @keydown.enter.prevent="$wire.selectResult(selected)"
            autocomplete="off"
            class="w-full pl-10 pr-4 py-2.5 rounded-xl text-sm transition-all duration-300 outline-none"
            style="background: var(--color-bg-tertiary);
                   color: var(--color-text-primary);
                   border: 1px solid transparent;"
            @focusin="$el.style.borderColor = 'var(--color-accent)'; $el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.15)'"
            @focusout="$el.style.borderColor = 'transparent'; $el.style.boxShadow = 'none'"
        />

        <!-- Clear button -->
        <button
            x-show="$wire.query.length > 0"
            x-cloak
            wire:click="resetSearch"
            class="absolute inset-y-0 right-0 pr-3 flex items-center transition-all duration-200"
            style="color: var(--color-text-tertiary);">
            <svg class="w-4 h-4 hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>

        <!-- Loading indicator -->
        <div wire:loading wire:target="query"
             class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <svg class="w-4 h-4 animate-spin" style="color: var(--color-accent);" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
    </div>

    <!-- Results Dropdown -->
    <div
        x-ref="searchPanel"
        x-show="open"
        x-cloak
        x-transition:enter="transition-all duration-200 ease-out"
        x-transition:enter-start="opacity-0 scale-95 translate-y-[-8px]"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition-all duration-150 ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute top-full left-0 right-0 mt-2 z-50 overflow-hidden"
        style="background: var(--color-surface);
               border: 1px solid var(--glass-border);
               border-radius: var(--radius-xl);
               box-shadow: 0 20px 60px rgba(0,0,0,0.12), 0 8px 24px rgba(0,0,0,0.06);
               backdrop-filter: blur(40px) saturate(200%);
               -webkit-backdrop-filter: blur(40px) saturate(200%);">

        @if (count($results) > 0)
            <div class="p-2 space-y-1 max-h-[420px] overflow-y-auto">
                @foreach ($results as $index => $result)
                    <button
                        wire:click="selectResult({{ $index }})"
                        @mouseenter="selected = {{ $index }}"
                        class="w-full flex items-start gap-3 px-3 py-2.5 rounded-xl text-left transition-all duration-150 group"
                        :class="selected === {{ $index }}
                            ? 'bg-accent/10 scale-[1.02]'
                            : 'hover:bg-accent/5'"
                        style="animation: slide-up-fade 0.3s ease-out both;
                               animation-delay: {{ $index * 30 }}ms;">
                        <!-- Type Icon -->
                        <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                             :class="selected === {{ $index }} ? 'shadow-md' : ''"
                             style="background: linear-gradient(135deg,
                                {{ $result['type'] === 'project' ? 'var(--color-accent), #6366f1' : '' }}
                                {{ $result['type'] === 'task' ? 'var(--color-success), #059669' : '' }}
                                {{ $result['type'] === 'risk' ? 'var(--color-danger), #dc2626' : '' }});
                                transition: transform 0.2s;">
                            @if ($result['type'] === 'project')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                                </svg>
                            @elseif ($result['type'] === 'task')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            @endif
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium truncate" style="color: var(--color-text-primary);">
                                    {{ $result['title'] }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="text-xs" style="color: var(--color-text-tertiary);">
                                    {{ $result['subtitle'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Badge -->
                        @php
                            $bgClasses = ['done' => 'rgba(16,185,129,0.1)', 'closed' => 'rgba(16,185,129,0.1)', 'in_progress' => 'rgba(245,158,11,0.1)', 'open' => 'rgba(245,158,11,0.1)', 'critical' => 'rgba(239,68,68,0.1)', 'high' => 'rgba(249,115,22,0.1)', 'active' => 'rgba(59,130,246,0.1)'];
                            $colorClasses = ['done' => 'var(--color-success)', 'closed' => 'var(--color-success)', 'in_progress' => 'var(--color-warning)', 'open' => 'var(--color-warning)', 'critical' => 'var(--color-danger)', 'high' => '#f97316', 'active' => 'var(--color-accent)'];
                            $badgeBg = $bgClasses[$result['badge']] ?? 'rgba(99,102,241,0.1)';
                            $badgeColor = $colorClasses[$result['badge']] ?? 'var(--color-info)';
                        @endphp
                        <div class="flex-shrink-0 flex items-center gap-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium uppercase tracking-wider"
                                  style="background: {{ $badgeBg }}; color: {{ $badgeColor }};">
                                {{ \Illuminate\Support\Str::of($result['badge'])->replace('_', ' ') }}
                            </span>

                            @if (isset($result['meta']))
                                <span class="text-[10px] font-mono" style="color: var(--color-text-tertiary);">
                                    {{ $result['meta'] }}
                                </span>
                            @endif
                        </div>

                        <!-- Arrow hint on hover/select -->
                        <div class="flex-shrink-0 opacity-0 -translate-x-2 transition-all duration-150"
                             :class="selected === {{ $index }} ? 'opacity-100 translate-x-0' : ''"
                             style="color: var(--color-accent);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                        </div>
                    </button>
                @endforeach
            </div>

            <!-- Footer -->
            <div class="px-4 py-2.5 flex items-center justify-between text-xs border-t"
                 style="border-color: var(--glass-border); color: var(--color-text-tertiary);">
                <div class="flex items-center gap-3">
                    @if (isset($counts['projects']) && $counts['projects'] > 0)
                        <span>📁 {{ $counts['projects'] }} 项目</span>
                    @endif
                    @if (isset($counts['tasks']) && $counts['tasks'] > 0)
                        <span>✓ {{ $counts['tasks'] }} 任务</span>
                    @endif
                    @if (isset($counts['risks']) && $counts['risks'] > 0)
                        <span>⚠ {{ $counts['risks'] }} 风险</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <kbd class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                         style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">↑↓</kbd>
                    <span>导航</span>
                    <kbd class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                         style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">↵</kbd>
                    <span>打开</span>
                    <kbd class="px-1.5 py-0.5 rounded text-[10px] font-medium"
                         style="background: var(--color-bg-tertiary); color: var(--color-text-secondary);">Esc</kbd>
                    <span>关闭</span>
                </div>
            </div>
        @elseif (mb_strlen(trim($query)) >= 1 && !$searching)
            <!-- Empty State -->
            <div class="flex flex-col items-center justify-center py-10 px-6 text-center">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4"
                     style="background: var(--color-bg-tertiary);">
                    <svg class="w-7 h-7" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium" style="color: var(--color-text-secondary);">未找到匹配结果</p>
                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">
                    尝试其他关键词，如项目名、任务标题或风险描述
                </p>
            </div>
        @elseif (mb_strlen(trim($query)) >= 1 && $searching)
            <!-- Loading Skeleton -->
            <div class="p-3 space-y-2">
                @foreach(range(1, 3) as $i)
                    <div class="flex items-center gap-3 px-3 py-3 rounded-xl skeleton">
                        <div class="w-9 h-9 rounded-lg bg-slate-200 dark:bg-slate-700"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3.5 w-3/5 rounded bg-slate-200 dark:bg-slate-700"></div>
                            <div class="h-3 w-2/5 rounded bg-slate-200 dark:bg-slate-700"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <style>
        @keyframes slide-up-fade {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Search dropdown backdrop highlight */
        .bg-accent\/10 { background: rgba(59, 130, 246, 0.1); }
        .bg-accent\/5 { background: rgba(59, 130, 246, 0.05); }
        .dark .bg-accent\/10 { background: rgba(96, 165, 250, 0.12); }
        .dark .bg-accent\/5 { background: rgba(96, 165, 250, 0.06); }
        .text-accent { color: var(--color-accent); }
        .scale-\[1\.02\] { transform: scale(1.02); }
    </style>
</div>
