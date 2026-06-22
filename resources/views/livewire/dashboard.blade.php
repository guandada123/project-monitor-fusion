<div wire:poll.30s x-data="{ mounted: false, filtersOpen: true }" x-init="mounted = true">
    {{-- ============================================= --}}
    {{-- Advanced Filter Bar — Premium Glass --}}
    {{-- ============================================= --}}
    <div class="glass-card-intense p-5 mb-6 transition-all duration-300"
         x-show="filtersOpen"
         x-transition:enter="transition-all ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition-all ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2"
         style="background: var(--glass-bg); backdrop-filter: blur(30px) saturate(200%);">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span class="text-sm font-semibold tracking-tight" style="color: var(--color-text-primary);">高级筛选</span>
                @if($this->activeFilterCount > 0)
                    <span class="badge badge-info text-[10px] px-1.5 py-0.5">
                        {{ $this->activeFilterCount }} 个筛选
                    </span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @if($this->activeFilterCount > 0)
                <button wire:click="resetFilters"
                        class="text-xs font-medium px-2.5 py-1 rounded-lg transition-all duration-200 hover:bg-red-50 dark:hover:bg-red-900/20"
                        style="color: var(--color-danger);">
                    <span class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        清除筛选
                    </span>
                </button>
                @endif
                <button @click="filtersOpen = !filtersOpen"
                        class="p-1.5 rounded-lg transition-all duration-200 hover:bg-[var(--color-bg-tertiary)]"
                        style="color: var(--color-text-tertiary);">
                    <svg class="w-4 h-4 transition-transform duration-300" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Filter Controls Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Search --}}
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-tertiary);">搜索项目</label>
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input wire:model.live.debounce.300ms="search" type="text"
                           placeholder="项目名称或描述..."
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary);">
                </div>
            </div>

            {{-- Status filter --}}
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-tertiary);">项目状态</label>
                <select wire:model.live="filterStatus"
                        class="w-full px-3 py-2 text-sm rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 appearance-none cursor-pointer"
                        style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary);">
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Source filter --}}
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-tertiary);">数据来源</label>
                <select wire:model.live="filterSource"
                        class="w-full px-3 py-2 text-sm rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 appearance-none cursor-pointer"
                        style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary);">
                    <option value="all">全部来源</option>
                    @foreach($sourceOptions as $source)
                        <option value="{{ $source }}">{{ $source }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Date range --}}
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--color-text-tertiary);">日期范围</label>
                <div class="flex items-center gap-2">
                    <input wire:model.live="filterDateStart" type="date"
                           class="flex-1 px-3 py-2 text-sm rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary);">
                    <span class="text-xs flex-shrink-0" style="color: var(--color-text-tertiary);">至</span>
                    <input wire:model.live="filterDateEnd" type="date"
                           class="flex-1 px-3 py-2 text-sm rounded-xl border transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30"
                           style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary);">
                </div>
            </div>
        </div>

        {{-- Active filter chips --}}
        @if($this->activeFilterCount > 0)
        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t" style="border-color: var(--color-border);">
            <span class="text-[10px] font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">活跃筛选项</span>
            @if($filterStatus !== 'all')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full"
                      style="background: rgba(99,102,241,0.1); color: var(--color-info);">
                    状态: {{ $statusOptions[$filterStatus] ?? $filterStatus }}
                    <button wire:click="$set('filterStatus', 'all')" class="hover:opacity-70">&times;</button>
                </span>
            @endif
            @if($filterSource !== 'all')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full"
                      style="background: rgba(16,185,129,0.1); color: var(--color-success);">
                    来源: {{ $filterSource }}
                    <button wire:click="$set('filterSource', 'all')" class="hover:opacity-70">&times;</button>
                </span>
            @endif
            @if($filterDateStart || $filterDateEnd)
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full"
                      style="background: rgba(245,158,11,0.1); color: var(--color-warning);">
                    日期: {{ $filterDateStart ?? '不限' }} ~ {{ $filterDateEnd ?? '不限' }}
                    <button wire:click="$set('filterDateStart', null); $set('filterDateEnd', null)" class="hover:opacity-70">&times;</button>
                </span>
            @endif
            @if($search !== '')
                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full"
                      style="background: rgba(59,130,246,0.1); color: var(--color-accent);">
                    搜索: "{{ $search }}"
                    <button wire:click="$set('search', '')" class="hover:opacity-70">&times;</button>
                </span>
            @endif
        </div>
        @endif
    </div>

    {{-- Filter toggle button (when bar is collapsed) --}}
    <div class="mb-6" x-show="!filtersOpen" x-cloak>
        <button @click="filtersOpen = true"
                class="glass-card-intense px-4 py-2.5 flex items-center gap-2 text-sm transition-all duration-200 hover:translate-y-[-1px] cursor-pointer"
                style="background: var(--glass-bg);">
            <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <span style="color: var(--color-text-secondary);">筛选</span>
            @if($this->activeFilterCount > 0)
                <span class="badge badge-info text-[10px] px-1.5 py-0.5">{{ $this->activeFilterCount }}</span>
            @endif
        </button>
    </div>

    {{-- ============================================= --}}
    {{-- Empty state when no projects match filters --}}
    {{-- ============================================= --}}
    @if(count($kpiSummary) === 0)
    <div class="glass-card-intense p-12 text-center animate-fade-in">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center"
             style="background: rgba(99,102,241,0.08);">
            <svg class="w-8 h-8" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
        </div>
        <h3 class="text-lg font-semibold mb-1" style="color: var(--color-text-primary);">没有匹配的项目</h3>
        <p class="text-sm mb-6" style="color: var(--color-text-tertiary);">当前筛选条件没有找到项目，试试调整筛选项</p>
        @if($this->activeFilterCount > 0)
        <button wire:click="resetFilters"
                class="btn-premium text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            清除全部筛选
        </button>
        @endif
    </div>
    @else
    {{-- ============================================= --}}
    {{-- Stats Overview Row — Premium Glass Cards --}}
    {{-- ============================================= --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-5 mb-8">
        @php $statCards = [
            ['label' => '活跃项目', 'value' => $projectStats['active_projects'], 'icon' => 'projects', 'color' => '#3b82f6', 'gradient' => 'from-blue-500 to-blue-600'],
            ['label' => '总任务数', 'value' => $projectStats['total_tasks'], 'icon' => 'tasks', 'color' => '#6366f1', 'gradient' => 'from-indigo-500 to-purple-600'],
            ['label' => '完成率', 'value' => $projectStats['completion_rate'], 'suffix' => '%', 'icon' => 'completion', 'color' => '#10b981', 'gradient' => 'from-emerald-500 to-teal-600'],
            ['label' => '延期任务', 'value' => $projectStats['overdue_tasks'], 'icon' => 'overdue', 'color' => '#ef4444', 'gradient' => 'from-red-500 to-rose-600'],
        ]; @endphp
        @foreach($statCards as $i => $stat)
        <div class="stat-card group cursor-default"
             style="animation-delay: {{ $i * 0.1 }}s;
                    --card-accent: {{ $stat['color'] }};">
            <div class="absolute inset-0 rounded-[var(--radius-xl)] opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"
                 style="box-shadow: 0 0 60px -12px {{ $stat['color'] }}33;"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4">
                    <span class="text-sm font-medium tracking-wide" style="color: var(--color-text-secondary);">{{ $stat['label'] }}</span>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3"
                         style="background: {{ $stat['color'] }}15; box-shadow: 0 4px 12px {{ $stat['color'] }}10;">
                        @if($stat['icon'] === 'projects')
                        <svg class="w-5 h-5" style="color: {{ $stat['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                        @elseif($stat['icon'] === 'tasks')
                        <svg class="w-5 h-5" style="color: {{ $stat['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        @elseif($stat['icon'] === 'completion')
                        <svg class="w-5 h-5" style="color: {{ $stat['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                        <svg class="w-5 h-5" style="color: {{ $stat['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                </div>

                <div class="text-3xl font-extrabold tracking-tight tabular-nums"
                     style="color: var(--color-text-primary);"
                     x-data="{ display: 0 }"
                     x-intersect.once="let target = {{ $stat['value'] ?? 0 }}; let step = target / 30; let interval = setInterval(() => { display = Math.min(display + step, target); if (display >= target) clearInterval(interval) }, 20);">
                    <span x-text="Math.round(display)"></span>
                    <span class="text-lg font-medium" style="color: var(--color-text-tertiary);" x-show="{{ isset($stat['suffix']) ? 'true' : 'false' }}">{{ $stat['suffix'] ?? '' }}</span>
                </div>

                @if($stat['icon'] === 'completion')
                <div class="mt-4">
                    <div class="progress-bar" style="height: 4px;">
                        <div class="progress-fill" x-data="{ w: 0 }" x-intersect.once="w = {{ $projectStats['completion_rate'] }}"
                             :style="'width: ' + w + '%'"
                             style="width: 0%; background: linear-gradient(90deg, {{ $stat['color'] }}, #34d399);"></div>
                    </div>
                </div>
                @endif

                <div class="absolute bottom-0 left-6 right-6 h-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                     style="background: {{ $stat['color'] }}40;"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ============================================= --}}
    {{-- Two column premium layout --}}
    {{-- ============================================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Main content (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Project Cards — Premium Bento Grid --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">项目总览</h2>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ count($kpiSummary) }} 个项目实时状态</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="btn-ghost text-sm"
                                wire:click="$dispatch('open-export-panel', { entityType: 'project' })">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            导出
                        </button>
                        <a href="/projects" wire:navigate.hover class="btn-ghost text-sm">
                            查看全部
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($kpiSummary as $project)
                    <a href="/projects/{{ $project['slug'] }}" wire:navigate.hover class="block p-4 rounded-xl border transition-all duration-300 group/card relative overflow-hidden"
                       style="background: var(--color-bg-tertiary); border-color: var(--color-border);"
                       onmouseover="this.style.borderColor='var(--color-accent)';this.style.transform='translateY(-3px)';this.style.boxShadow='0 12px 40px -12px rgba(59,130,246,0.15)';"
                       onmouseout="this.style.borderColor='var(--color-border)';this.style.transform='translateY(0)';this.style.boxShadow='none';">
                        <div class="absolute top-0 left-0 right-0 h-0.5 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300"
                             style="background: linear-gradient(90deg, var(--color-accent), #6366f1);"></div>

                        <div class="flex items-start justify-between mb-3">
                            <h3 class="font-semibold text-sm truncate pr-2 group-hover/card:text-[var(--color-accent)] transition-colors duration-200"
                                style="color: var(--color-text-primary);">{{ $project['name'] }}</h3>
                            <span class="badge flex-shrink-0 {{ $project['status'] === 'active' ? 'badge-info' : ($project['status'] === 'completed' ? 'badge-success' : 'badge-warning') }}">
                                {{ $project['status'] === 'active' ? '进行中' : ($project['status'] === 'completed' ? '已完成' : ($project['status'] === 'on_hold' ? '暂停' : $project['status'])) }}
                            </span>
                        </div>

                        <div class="flex items-center gap-4 text-xs mb-3" style="color: var(--color-text-tertiary);">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                {{ $project['task_count'] }} 任务
                            </span>
                            <span>·</span>
                            <span>来源: {{ $project['source'] }}</span>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs mb-1.5">
                                <span style="color: var(--color-text-secondary);">完成率</span>
                                <span class="font-semibold tabular-nums" style="color: {{ $project['completion_rate'] >= 70 ? 'var(--color-success)' : ($project['completion_rate'] >= 40 ? 'var(--color-warning)' : 'var(--color-danger)') }};">
                                    {{ $project['completion_rate'] }}%
                                </span>
                            </div>
                            <div class="progress-bar" style="height: 5px;">
                                <div class="progress-fill" style="width: {{ $project['completion_rate'] }}%;
                                    background: linear-gradient(90deg,
                                        {{ $project['completion_rate'] >= 70 ? 'var(--color-success)' : ($project['completion_rate'] >= 40 ? 'var(--color-warning)' : 'var(--color-danger)') }},
                                        {{ $project['completion_rate'] >= 70 ? '#34d399' : ($project['completion_rate'] >= 40 ? '#fbbf24' : '#f87171') }});"></div>
                            </div>
                        </div>

                        @if($project['open_risks'] > 0)
                        <div class="mt-2.5 flex items-center gap-1.5 text-xs font-medium" style="color: var(--color-danger);">
                            <span class="w-1.5 h-1.5 rounded-full animate-pulse-soft" style="background: var(--color-danger);"></span>
                            {{ $project['open_risks'] }} 个风险待处理
                        </div>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Weekly Trend Chart --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center justify-between mb-1">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">近 7 日动态趋势</h2>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">任务完成 / 新建 / 风险发现</p>
                    </div>
                </div>
                <div class="chart-container relative" id="trend-chart" wire:ignore>
                    <div class="chart-skeleton absolute inset-0 flex flex-col items-center justify-center gap-3 z-10 pointer-events-none">
                        <div class="flex items-end gap-2 w-4/5 h-28">
                            @foreach(range(1, 7) as $i)
                            <div class="flex-1 skeleton rounded-t" style="height: {{ rand(40, 100) }}%; animation-delay: {{ $i * 0.1 }}s;"></div>
                            @endforeach
                        </div>
                        <div class="flex gap-3 mt-2">
                            <div class="skeleton w-14 h-3 rounded"></div>
                            <div class="skeleton w-14 h-3 rounded"></div>
                            <div class="skeleton w-14 h-3 rounded"></div>
                        </div>
                    </div>
                </div>
                <script id="trend-chart-data" type="application/json">@json($trendData)</script>
            </div>

            {{-- KPI Trend Chart --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center justify-between mb-1">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">核心指标趋势</h2>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">完成率 / 延期率 / 开放风险 · 7 日快照</p>
                    </div>
                </div>
                <div class="chart-container relative" id="kpi-trend-chart" wire:ignore>
                    <div class="chart-skeleton absolute inset-0 flex flex-col items-center justify-center gap-3 z-10 pointer-events-none">
                        <div class="flex items-end gap-2 w-4/5 h-28">
                            @foreach(range(1, 7) as $i)
                            <div class="flex-1 skeleton rounded-t" style="height: {{ rand(30, 90) }}%; animation-delay: {{ $i * 0.12 }}s;"></div>
                            @endforeach
                        </div>
                        <div class="flex gap-3 mt-2">
                            <div class="skeleton w-14 h-3 rounded"></div>
                            <div class="skeleton w-14 h-3 rounded"></div>
                            <div class="skeleton w-14 h-3 rounded"></div>
                        </div>
                    </div>
                </div>
                <script id="kpi-trend-data" type="application/json">@json(['kpiDates' => $kpiDates, 'kpiTrendData' => $kpiTrendData])</script>
            </div>
        </div>

        {{-- Sidebar (1/3) --}}
        <div class="space-y-6">
            {{-- Resource Heatmap --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">团队负载</h2>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ count($resourceHeatmap) }} 名成员</p>
                    </div>
                </div>
                @if(count($resourceHeatmap) > 0)
                <div class="space-y-4">
                    @foreach($resourceHeatmap as $i => $member)
                    <div class="group/member" style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.1 + 0.2 }}s both;">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-sm font-medium truncate mr-2" style="color: var(--color-text-secondary);">{{ $member['name'] }}</span>
                            <span class="text-xs font-bold tabular-nums flex-shrink-0"
                                  style="color: {{ $member['workload'] > 100 ? 'var(--color-danger)' : ($member['workload'] > 70 ? 'var(--color-warning)' : 'var(--color-success)') }};">
                                {{ $member['workload'] }}%
                            </span>
                        </div>
                        <div class="progress-bar group-hover/member:h-[7px] transition-all duration-200" style="height: 5px;">
                            <div class="progress-fill transition-all duration-700"
                                 style="width: {{ min($member['workload'], 200) / 2 }}%;
                                        background: {{ $member['workload'] > 100 ? 'linear-gradient(90deg, #f59e0b, #ef4444)' : ($member['workload'] > 70 ? 'linear-gradient(90deg, #fbbf24, #f59e0b)' : ($member['workload'] > 50 ? 'linear-gradient(90deg, #3b82f6, #6366f1)' : 'linear-gradient(90deg, #10b981, #34d399)')) }};"></div>
                        </div>
                        <div class="flex gap-3 mt-1 text-xs" style="color: var(--color-text-tertiary);">
                            <span>{{ $member['active_tasks'] }} 活跃</span>
                            @if($member['overdue_tasks'] > 0)
                            <span style="color: var(--color-danger);">{{ $member['overdue_tasks'] }} 延期</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <p class="text-sm" style="color: var(--color-text-tertiary);">暂无团队成员数据</p>
                </div>
                @endif
            </div>

            {{-- Risk Alert --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">风险预警</h2>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ count($riskSummary) }} 项待关注</p>
                    </div>
                    @if(count($riskSummary) > 0)
                    <span class="badge badge-danger animate-pulse-soft">{{ count($riskSummary) }} 活跃</span>
                    @endif
                </div>
                <div class="space-y-2.5">
                    @forelse($riskSummary as $risk)
                    <div class="p-3 rounded-xl border transition-all duration-200 group/risk cursor-default"
                         style="background: var(--color-bg-tertiary); border-color: var(--color-border);"
                         onmouseover="this.style.borderColor='var(--color-accent)';this.style.transform='translateX(4px)';"
                         onmouseout="this.style.borderColor='var(--color-border)';this.style.transform='translateX(0)';">
                        <div class="flex items-start gap-2.5">
                            <span class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 {{ $risk['severity'] === 'critical' ? 'animate-pulse-soft' : '' }}"
                                  style="background: {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : ($risk['severity'] === 'medium' ? 'var(--color-info)' : 'var(--color-text-tertiary)')) }};
                                         box-shadow: 0 0 8px {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : 'transparent') }}66;"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-xs" style="color: var(--color-text-tertiary);">{{ $risk['project_name'] }}</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                          style="background: {{ $risk['severity'] === 'critical' ? 'rgba(239,68,68,0.12)' : ($risk['severity'] === 'high' ? 'rgba(245,158,11,0.12)' : 'rgba(99,102,241,0.1)') }};
                                                 color: {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : 'var(--color-info)') }};">
                                        {{ $risk['severity'] === 'critical' ? '严重' : ($risk['severity'] === 'high' ? '高' : ($risk['severity'] === 'medium' ? '中' : '低')) }}
                                    </span>
                                </div>
                                <p class="text-sm font-medium truncate group-hover/risk:text-[var(--color-accent)] transition-colors duration-200"
                                   style="color: var(--color-text-primary);">{{ $risk['title'] }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ $risk['last_seen'] }}</p>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-8">
                        <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background: rgba(16,185,129,0.08);">
                            <svg class="w-6 h-6" style="color: var(--color-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <p class="text-sm font-medium" style="color: var(--color-text-secondary);">暂无风险</p>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">一切顺利 ✨</p>
                    </div>
                    @endforelse
                </div>
                <div class="mt-4 pt-3 border-t text-center" style="border-color: var(--color-border);">
                    <a href="{{ route('risks') }}" class="inline-flex items-center gap-1.5 text-xs font-medium transition-all duration-200 hover:gap-2"
                       style="color: var(--color-accent);">
                        查看全部风险
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================= --}}
    {{-- Initialize Charts --}}
    {{-- ============================================= --}}
    @push('scripts')
    <script>
    document.addEventListener('livewire:navigated', function() {
        initTrendChart();
        initKpiTrendChart();
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(() => { initTrendChart(); initKpiTrendChart(); }, 100);
    }

    // ——— Poll refresh: re-initialize charts with fresh data ———
    document.addEventListener('dashboard-refreshed', function() {
        initTrendChart();
        initKpiTrendChart();
    });

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function readJsonData(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch(e) { return null; }
    }

    function hideSkeleton(chartId) {
        const el = document.getElementById(chartId);
        if (!el) return;
        const skeleton = el.querySelector('.chart-skeleton');
        if (skeleton) {
            skeleton.style.transition = 'opacity 0.4s ease-out';
            skeleton.style.opacity = '0';
            setTimeout(() => skeleton.remove(), 500);
        }
    }

    function initTrendChart() {
        const el = document.getElementById('trend-chart');
        if (!el || !window.ApexCharts) return;

        const trendData = readJsonData('trend-chart-data');
        if (!trendData || !trendData.length) return;

        const dark = isDark();

        const options = {
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                fontFamily: 'Inter, system-ui, sans-serif',
                animations: { enabled: false },
                background: 'transparent',
            },
            colors: ['#3b82f6', '#10b981', '#ef4444'],
            series: [
                { name: '完成任务', data: trendData.map(d => d.completed) },
                { name: '新建任务', data: trendData.map(d => d.created) },
                { name: '新风险', data: trendData.map(d => d.risks) },
            ],
            xaxis: {
                categories: trendData.map(d => d.date),
                labels: { style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '12px', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: { style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px', fontWeight: 500 } },
                min: 0,
                tickAmount: 4,
            },
            grid: {
                borderColor: dark ? '#334155' : '#e2e8f0',
                strokeDashArray: 5,
                padding: { top: 10, right: 10, bottom: 0, left: 10 },
            },
            stroke: { curve: 'smooth', width: 2.5, lineCap: 'round' },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: dark ? 0.2 : 0.15,
                    opacityTo: 0.02,
                    stops: [0, 100],
                }
            },
            markers: { size: 0, strokeWidth: 2, hover: { size: 5 } },
            dataLabels: { enabled: false },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                x: { show: true },
                marker: { show: true },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                fontWeight: 500,
                labels: { colors: dark ? '#94a3b8' : '#64748b', useSeriesColors: false },
                markers: { radius: 5, strokeWidth: 0 },
                itemMargin: { horizontal: 16, vertical: 4 },
            },
        };

        if (el._chart) el._chart.destroy();
        el._chart = new ApexCharts(el, options);
        el._chart.render().then(() => { hideSkeleton('trend-chart'); });
    }

    function initKpiTrendChart() {
        const el = document.getElementById('kpi-trend-chart');
        if (!el || !window.ApexCharts) return;

        const data = readJsonData('kpi-trend-data');
        if (!data || !data.kpiDates) return;

        const kpiDates = data.kpiDates;
        const kpiTrend = data.kpiTrendData;
        const dark = isDark();

        const options = {
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                fontFamily: 'Inter, system-ui, sans-serif',
                animations: { enabled: false },
                background: 'transparent',
            },
            colors: ['#6366f1', '#f59e0b', '#ef4444'],
            series: [
                { name: '完成率 %', data: kpiTrend.completion },
                { name: '延期率 %', data: kpiTrend.overdue },
                { name: '开放风险', data: kpiTrend.risks },
            ],
            xaxis: {
                categories: kpiDates,
                labels: { style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '12px', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: { style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px', fontWeight: 500 } },
                min: 0,
                tickAmount: 4,
            },
            grid: {
                borderColor: dark ? '#334155' : '#e2e8f0',
                strokeDashArray: 5,
                padding: { top: 10, right: 10, bottom: 0, left: 10 },
            },
            stroke: { curve: 'smooth', width: 2.5, lineCap: 'round' },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: dark ? 0.2 : 0.12,
                    opacityTo: 0.01,
                    stops: [0, 100],
                }
            },
            markers: { size: 0, strokeWidth: 2, hover: { size: 5 } },
            dataLabels: { enabled: false },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                x: { show: true },
                y: {
                    formatter: function(val, { seriesIndex }) {
                        return seriesIndex === 2 ? val + ' 个' : val + '%';
                    }
                },
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                fontWeight: 500,
                labels: { colors: dark ? '#94a3b8' : '#64748b', useSeriesColors: false },
                markers: { radius: 5, strokeWidth: 0 },
                itemMargin: { horizontal: 16, vertical: 4 },
            },
        };

        if (el._chart) el._chart.destroy();
        el._chart = new ApexCharts(el, options);
        el._chart.render().then(() => { hideSkeleton('kpi-trend-chart'); });
    }
    </script>
    @endpush
</div>
