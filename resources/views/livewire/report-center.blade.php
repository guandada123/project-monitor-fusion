<div>
    {{-- Page Header — Premium Glass --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">报表中心</h1>
                <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">一键生成项目报表，支持 Markdown / HTML 导出</p>
            </div>
            @if($reportGenerated)
            <div class="flex items-center gap-4 text-xs" style="color: var(--color-text-tertiary);">
                <span>{{ count($selectedProjects) }} 个项目选中</span>
                <span class="w-1 h-1 rounded-full" style="background: var(--color-text-tertiary);"></span>
                <span>{{ $reportType === 'weekly' ? '周报' : '月报' }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Control Panel — Premium --}}
        <div class="space-y-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.1s both;">
            {{-- Report Config --}}
            <div class="glass-card-intense p-5">
                <h3 class="font-semibold mb-5 text-xs uppercase tracking-widest" style="color: var(--color-text-tertiary);">报表配置</h3>

                {{-- Report Type --}}
                <div class="mb-5">
                    <label class="text-sm font-medium block mb-2.5" style="color: var(--color-text-secondary);">报表类型</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button wire:click="$set('reportType', 'weekly')"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-300 hover:scale-105 active:scale-95"
                                style="background: {{ $reportType === 'weekly' ? 'var(--color-accent)' : 'var(--color-bg-tertiary)' }};
                                       color: {{ $reportType === 'weekly' ? '#fff' : 'var(--color-text-secondary)' }};
                                       box-shadow: {{ $reportType === 'weekly' ? '0 4px 16px -4px var(--color-accent)40' : 'none' }};">
                            周报
                        </button>
                        <button wire:click="$set('reportType', 'monthly')"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-300 hover:scale-105 active:scale-95"
                                style="background: {{ $reportType === 'monthly' ? 'var(--color-accent)' : 'var(--color-bg-tertiary)' }};
                                       color: {{ $reportType === 'monthly' ? '#fff' : 'var(--color-text-secondary)' }};
                                       box-shadow: {{ $reportType === 'monthly' ? '0 4px 16px -4px var(--color-accent)40' : 'none' }};">
                            月报
                        </button>
                    </div>
                </div>

                {{-- Period --}}
                <div class="mb-5">
                    <label class="text-sm font-medium block mb-2.5" style="color: var(--color-text-secondary);">周期</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button wire:click="$set('period', 'current')"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-300 hover:scale-105 active:scale-95"
                                style="background: {{ $period === 'current' ? 'var(--color-accent)' : 'var(--color-bg-tertiary)' }};
                                       color: {{ $period === 'current' ? '#fff' : 'var(--color-text-secondary)' }};
                                       box-shadow: {{ $period === 'current' ? '0 4px 16px -4px var(--color-accent)40' : 'none' }};">
                            本{{ $reportType === 'monthly' ? '月' : '周' }}
                        </button>
                        <button wire:click="$set('period', 'last')"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold transition-all duration-300 hover:scale-105 active:scale-95"
                                style="background: {{ $period === 'last' ? 'var(--color-accent)' : 'var(--color-bg-tertiary)' }};
                                       color: {{ $period === 'last' ? '#fff' : 'var(--color-text-secondary)' }};
                                       box-shadow: {{ $period === 'last' ? '0 4px 16px -4px var(--color-accent)40' : 'none' }};">
                            上{{ $reportType === 'monthly' ? '月' : '周' }}
                        </button>
                    </div>
                </div>

                {{-- Include Options --}}
                <div class="space-y-3 mb-5">
                    <label class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm cursor-pointer transition-all duration-200 group/toggle"
                           style="color: var(--color-text-secondary);"
                           onmouseover="this.style.background='var(--color-bg-tertiary)';"
                           onmouseout="this.style.background='transparent';">
                        <input type="checkbox" wire:model="includeTasks" class="rounded w-4 h-4 accent-[var(--color-accent)]">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            任务统计
                        </span>
                    </label>
                    <label class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm cursor-pointer transition-all duration-200 group/toggle"
                           style="color: var(--color-text-secondary);"
                           onmouseover="this.style.background='var(--color-bg-tertiary)';"
                           onmouseout="this.style.background='transparent';">
                        <input type="checkbox" wire:model="includeRisks" class="rounded w-4 h-4 accent-[var(--color-accent)]">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            风险汇总
                        </span>
                    </label>
                    <label class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm cursor-pointer transition-all duration-200 group/toggle"
                           style="color: var(--color-text-secondary);"
                           onmouseover="this.style.background='var(--color-bg-tertiary)';"
                           onmouseout="this.style.background='transparent';">
                        <input type="checkbox" wire:model="includeResources" class="rounded w-4 h-4 accent-[var(--color-accent)]">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            团队负载
                        </span>
                    </label>
                </div>

                {{-- Generate Button --}}
                <button wire:click="generate" wire:loading.attr="disabled"
                        class="w-full btn-premium text-sm py-3.5 font-bold flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="generate">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        生成报表
                    </span>
                    <span wire:loading wire:target="generate" class="flex items-center gap-1">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        生成中...
                    </span>
                </button>
            </div>

            {{-- Project Selection — Premium --}}
            <div class="glass-card-intense p-5">
                <h3 class="font-semibold mb-4 text-xs uppercase tracking-widest" style="color: var(--color-text-tertiary);">项目筛选</h3>
                <div class="space-y-1 max-h-56 overflow-y-auto custom-scrollbar">
                    @forelse($projects as $project)
                    <label class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm cursor-pointer transition-all duration-200 group/proj"
                           style="color: var(--color-text-secondary);"
                           onmouseover="this.style.background='var(--color-bg-tertiary)';"
                           onmouseout="this.style.background='transparent';">
                        <input type="checkbox" wire:model="selectedProjects" value="{{ $project->id }}" class="rounded w-4 h-4 accent-[var(--color-accent)]">
                        <span class="flex-1 truncate">{{ $project->name }}</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium flex-shrink-0"
                              style="background: {{ $project->status === 'active' ? 'color-mix(in srgb, var(--color-info) 12%, transparent)' : ($project->status === 'completed' ? 'color-mix(in srgb, var(--color-success) 12%, transparent)' : 'color-mix(in srgb, var(--color-warning) 12%, transparent)') }};
                                     color: {{ $project->status === 'active' ? 'var(--color-info)' : ($project->status === 'completed' ? 'var(--color-success)' : 'var(--color-warning)') }};">
                            {{ $project->status === 'active' ? '活跃' : ($project->status === 'completed' ? '完成' : '暂停') }}
                        </span>
                    </label>
                    @empty
                    <p class="text-xs text-center py-4" style="color: var(--color-text-tertiary);">暂无项目</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Report Preview — Premium --}}
        <div class="lg:col-span-2" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.2s both;">
            @if($reportGenerated)
            <div class="glass-card-intense p-6 animate-fade-in">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-bold tracking-tight" style="color: var(--color-text-primary);">{{ $reportData['title'] }}</h2>
                        <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">
                            {{ $reportData['period'][0]->format('Y-m-d') }} ~ {{ $reportData['period'][1]->format('Y-m-d') }}
                            · 生成于 {{ $reportData['generated_at'] }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button wire:click="exportMarkdown"
                                class="btn-ghost text-xs px-3 py-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Markdown
                        </button>
                        <button wire:click="exportHtml"
                                class="btn-ghost text-xs px-3 py-2 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            HTML
                        </button>
                    </div>
                </div>

                {{-- Summary Cards — Premium Stat Cards --}}
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                    @php
                        $summaryItems = [
                            ['label' => '项目数', 'value' => $reportData['summary']['total_projects'], 'color' => '#3b82f6'],
                            ['label' => '任务总数', 'value' => $reportData['summary']['total_tasks'], 'color' => '#6366f1'],
                            ['label' => '已完成', 'value' => $reportData['summary']['completed_tasks'], 'color' => '#10b981'],
                            ['label' => '已延期', 'value' => $reportData['summary']['overdue_tasks'], 'color' => '#f59e0b'],
                            ['label' => '活跃风险', 'value' => $reportData['summary']['active_risks'], 'color' => '#ef4444'],
                            ['label' => '严重风险', 'value' => $reportData['summary']['severe_risks'], 'color' => '#dc2626'],
                        ];
                    @endphp
                    @foreach($summaryItems as $i => $item)
                    <div class="stat-card p-3 text-center group" style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 + 0.2 }}s both;">
                        <p class="text-xl font-extrabold tracking-tight"
                           style="color: {{ $item['color'] }};"
                           x-data="{ d: 0 }"
                           x-intersect.once="let t = {{ $item['value'] }}; let s = Math.max(t / 20, 0.5); let iv = setInterval(() => { d = Math.min(d + s, t); if (d >= t) clearInterval(iv) }, 25);">
                            <span x-text="Math.round(d)"></span>
                        </p>
                        <p class="text-[10px] font-medium mt-1" style="color: var(--color-text-tertiary);">{{ $item['label'] }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Project Details — Premium Glass Cards --}}
                @foreach($reportData['projects'] as $pi => $project)
                <div class="mb-4 p-5 rounded-2xl border transition-all duration-200 group/project relative overflow-hidden"
                     style="background: var(--color-bg-tertiary); border-color: var(--color-border);
                            animation: slide-up 0.35s cubic-bezier(0.16,1,0.3,1) {{ $pi * 0.08 + 0.3 }}s both;"
                     onmouseover="this.style.borderColor='var(--color-accent)';this.style.boxShadow='0 4px 20px -6px var(--color-accent)12';"
                     onmouseout="this.style.borderColor='var(--color-border)';this.style.boxShadow='none';">
                    {{-- Hover accent strip --}}
                    <div class="absolute top-0 left-0 right-0 h-0.5 opacity-0 group-hover/project:opacity-100 transition-opacity duration-300"
                         style="background: linear-gradient(90deg, var(--color-accent), #6366f1);"></div>

                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ $project['name'] }}</h3>
                        <span class="badge {{ $project['status'] === 'active' ? 'badge-info' : ($project['status'] === 'completed' ? 'badge-success' : 'badge-warning') }}">
                            {{ $project['status'] === 'active' ? '进行中' : ($project['status'] === 'completed' ? '已完成' : ($project['status'] === 'on_hold' ? '暂停' : $project['status'])) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-4 mb-3 text-xs" style="color: var(--color-text-tertiary);">
                        <span>负责人: {{ $project['owner'] }}</span>
                        <span>·</span>
                        <span class="font-semibold" style="color: var(--color-accent);">进度 {{ $project['progress'] }}%</span>
                    </div>

                    {{-- Task Stats — Premium Mini Grid --}}
                    @if($includeTasks)
                    <div class="grid grid-cols-5 gap-2 mb-3">
                        @foreach(['total' => '总计', 'todo' => '待办', 'in_progress' => '进行中', 'done' => '已完成', 'overdue' => '延期'] as $key => $label)
                        <div class="text-center px-2 py-2 rounded-xl text-xs transition-all duration-200 hover:scale-105"
                             style="background: {{ $key === 'done' ? 'color-mix(in srgb, var(--color-success) 12%, transparent)' : ($key === 'overdue' ? 'color-mix(in srgb, var(--color-danger) 12%, transparent)' : 'var(--color-bg-primary)') }};">
                            <p class="font-bold" style="color: {{ $key === 'done' ? 'var(--color-success)' : ($key === 'overdue' ? 'var(--color-danger)' : 'var(--color-text-primary)') }};">
                                {{ $project['task_stats'][$key] }}
                            </p>
                            <p class="mt-0.5" style="color: var(--color-text-tertiary);">{{ $label }}</p>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Risks — Premium List --}}
                    @if($includeRisks && !empty($project['risks']))
                    <div class="space-y-1.5">
                        @foreach($project['risks'] as $risk)
                        <div class="flex items-center gap-2 text-xs py-1 px-2 rounded-lg transition-colors duration-150 hover:bg-red-50/30 dark:hover:bg-red-900/10">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                  style="background: {{ ['critical' => 'var(--color-danger)', 'high' => '#f97316', 'medium' => 'var(--color-warning)', 'low' => 'var(--color-success)'][$risk['severity']] ?? 'var(--color-text-tertiary)' }};"></span>
                            <span class="flex-1 truncate" style="color: var(--color-text-secondary);">{{ $risk['title'] }}</span>
                            <span class="badge {{ $risk['status'] === 'open' ? 'badge-danger' : 'badge-info' }}" style="font-size: 0.6rem;">{{ $risk['status'] === 'open' ? '活跃' : '关闭' }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endforeach

                {{-- Team Workload — Premium --}}
                @if($includeResources && !empty($reportData['workload']))
                <div class="pt-5 border-t mt-5" style="border-color: var(--color-border);">
                    <h4 class="font-semibold text-sm mb-4 tracking-tight" style="color: var(--color-text-primary);">团队负载</h4>
                    <div class="space-y-3">
                        @foreach($reportData['workload'] as $wi => $member)
                        <div class="flex items-center gap-3 group/wl"
                             style="animation: fade-in 0.3s ease-out {{ $wi * 0.06 + 0.3 }}s both;">
                            <span class="text-sm w-20 truncate font-medium" style="color: var(--color-text-secondary);">{{ $member['name'] }}</span>
                            <div class="flex-1 progress-bar group-hover/wl:h-[7px] transition-all duration-200" style="height: 5px;">
                                <div class="progress-fill transition-all duration-700"
                                     style="width: {{ min($member['workload_pct'], 200) / 2 }}%;
                                            background: {{ $member['workload_pct'] > 100 ? 'linear-gradient(90deg, #f59e0b, #ef4444)' : ($member['workload_pct'] > 70 ? 'linear-gradient(90deg, #fbbf24, #f59e0b)' : 'linear-gradient(90deg, var(--color-accent), #6366f1)') }};"></div>
                            </div>
                            <span class="text-xs w-12 text-right font-mono font-bold tabular-nums"
                                  style="color: {{ $member['workload_pct'] > 100 ? 'var(--color-danger)' : ($member['workload_pct'] > 70 ? 'var(--color-warning)' : 'var(--color-text-tertiary)') }};">
                                {{ $member['workload_pct'] }}%
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @else
            {{-- Empty State — Premium --}}
            <div class="glass-card-intense p-20 text-center" style="animation: fade-in 0.5s ease-out 0.2s both;">
                <div class="w-20 h-20 mx-auto mb-6 rounded-2xl flex items-center justify-center"
                     style="background: color-mix(in srgb, var(--color-accent) 6%, transparent);">
                    <svg class="w-10 h-10" style="color: var(--color-text-tertiary); opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold mb-2 tracking-tight" style="color: var(--color-text-primary);">选择配置并生成报表</h3>
                <p class="text-sm max-w-md mx-auto" style="color: var(--color-text-tertiary);">报表将汇总选定项目的数据，支持导出为 Markdown 或 HTML 格式</p>
            </div>
            @endif
        </div>
    </div>
</div>
