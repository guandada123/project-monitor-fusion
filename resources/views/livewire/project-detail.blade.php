<div x-data="{ mounted: false }" x-init="mounted = true">
    {{-- Project Header — Premium Glass --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.45s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">{{ $project->name }}</h1>
                    <span class="badge {{ $project->status === 'active' ? 'badge-info' : ($project->status === 'completed' ? 'badge-success' : 'badge-warning') }}"
                          style="animation: scale-in 0.3s cubic-bezier(0.16,1,0.3,1) 0.2s both;">
                        {{ $project->status === 'active' ? '进行中' : ($project->status === 'completed' ? '已完成' : ($project->status === 'on_hold' ? '已暂停' : $project->status)) }}
                    </span>
                </div>
                <p class="text-sm max-w-2xl" style="color: var(--color-text-secondary);">{{ $project->description }}</p>
                <div class="flex flex-wrap gap-4 mt-3 text-xs" style="color: var(--color-text-tertiary);">
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        {{ $project->start_at?->format('Y-m-d') ?? '未设置' }} — {{ $project->end_at?->format('Y-m-d') ?? '未设置' }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.858 15.355-5.858 21.213 0"/></svg>
                        来源: {{ $project->source }}
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        负责人: {{ $project->owner?->name ?? '未分配' }}
                    </span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button class="btn-ghost text-sm group" wire:click="$refresh">
                    <svg class="w-4 h-4 transition-transform duration-500 group-hover:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    刷新
                </button>
                <button class="btn-premium text-sm"
                        wire:click="$dispatch('open-export-panel', { entityType: 'project' })">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    导出报表
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Row — Premium Dashboard-style stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @php
            $totalTasks = $project->tasks->count();
            $doneTasks = $project->tasks->where('status', 'done')->count();
            $overdueTasks = $project->tasks->filter(fn($t) => $t->is_overdue)->count();
            $openRisks = $project->risks->where('status', 'open')->count();
            $wipTasks = $project->tasks->whereIn('status', ['in_progress', 'review'])->count();
        @endphp
        @foreach([
            ['label' => '完成率', 'value' => $project->completion_rate, 'suffix' => '%', 'color' => '#10b981', 'icon' => 'check', 'sub' => "$doneTasks/$totalTasks 任务"],
            ['label' => '延期率', 'value' => $project->overdue_rate, 'suffix' => '%', 'color' => '#ef4444', 'icon' => 'alert', 'sub' => "$overdueTasks 个延期"],
            ['label' => '风险数', 'value' => $openRisks, 'color' => '#f59e0b', 'icon' => 'shield', 'sub' => '待处理'],
            ['label' => '在制品', 'value' => $wipTasks, 'color' => '#3b82f6', 'icon' => 'play', 'sub' => '进行中'],
        ] as $i => $kpi)
        <div class="stat-card group cursor-default"
             style="animation: scale-in 0.4s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.1 }}s both;
                    --card-accent: {{ $kpi['color'] }};">
            {{-- Hover glow --}}
            <div class="absolute inset-0 rounded-[var(--radius-xl)] opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"
                 style="box-shadow: 0 0 50px -10px {{ $kpi['color'] }}33;"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-3">
                    <span class="text-xs font-medium tracking-wide" style="color: var(--color-text-tertiary);">{{ $kpi['label'] }}</span>
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center transition-transform duration-300 group-hover:scale-110 group-hover:rotate-3"
                         style="background: {{ $kpi['color'] }}15; box-shadow: 0 4px 12px {{ $kpi['color'] }}10;">
                        @if($kpi['icon'] === 'check')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif($kpi['icon'] === 'alert')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @elseif($kpi['icon'] === 'shield')
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        @else
                        <svg class="w-4.5 h-4.5" style="color: {{ $kpi['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @endif
                    </div>
                </div>

                <div class="text-2xl font-extrabold tracking-tight tabular-nums"
                     style="color: var(--color-text-primary);"
                     x-data="{ display: 0 }"
                     x-intersect.once="let target = {{ $kpi['value'] ?? 0 }}; let step = Math.max(target / 30, 0.5); let interval = setInterval(() => { display = Math.min(display + step, target); if (display >= target) clearInterval(interval) }, 20);">
                    <span x-text="Math.round(display)"></span>
                    @if(isset($kpi['suffix']))
                    <span class="text-base font-medium" style="color: var(--color-text-tertiary);">{{ $kpi['suffix'] }}</span>
                    @endif
                </div>

                <div class="mt-1.5 text-xs" style="color: var(--color-text-tertiary);">{{ $kpi['sub'] }}</div>

                {{-- Bottom accent bar --}}
                <div class="absolute bottom-0 left-4 right-4 h-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                     style="background: {{ $kpi['color'] }}40;"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Gantt Chart — Premium Glass --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.3s both;">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">任务时间线</h2>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ count($ganttData) }} 个任务 · 甘特图</p>
            </div>
            <div class="flex items-center gap-3 text-xs" style="color: var(--color-text-tertiary);">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded" style="background: #10b981;"></span>已完成</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded" style="background: #3b82f6;"></span>进行中</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded" style="background: #94a3b8;"></span>待办</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded" style="background: #ef4444;"></span>阻塞</span>
            </div>
        </div>
        <div class="chart-container" id="gantt-chart" wire:ignore style="min-height: {{ max(count($ganttData) * 40, 300) }}px;"></div>
    </div>

    {{-- Task Distribution + Risk Timeline — Side by Side --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.4s both;">
        {{-- Task Distribution Donut --}}
        <div class="glass-card-intense p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">任务状态分布</h2>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ array_sum(array_column($taskDistribution, 'count')) }} 个任务</p>
            </div>
            <div class="chart-container" id="task-distribution-chart" wire:ignore></div>
        </div>

        {{-- Risk Timeline — Enhanced --}}
        <div class="glass-card-intense p-6">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">风险时间轴</h2>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ count($riskTimeline) }} 条记录</p>
                </div>
                @if(count($riskTimeline) > 0)
                <span class="badge badge-danger animate-pulse-soft">{{ collect($riskTimeline)->where('status', 'open')->count() }} 活跃</span>
                @endif
            </div>
            <div class="space-y-2.5 max-h-[340px] overflow-y-auto custom-scrollbar">
                @forelse($riskTimeline as $i => $risk)
                <div class="p-3 rounded-xl border transition-all duration-200 group/risk cursor-default"
                     style="background: var(--color-bg-tertiary); border-color: var(--color-border);
                            animation: slide-up 0.3s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.06 + 0.3 }}s both;"
                     onmouseover="this.style.borderColor='var(--color-accent)';this.style.transform='translateX(4px)';this.style.boxShadow='0 4px 16px -8px var(--color-accent)22';"
                     onmouseout="this.style.borderColor='var(--color-border)';this.style.transform='translateX(0)';this.style.boxShadow='none';">
                    <div class="flex items-start gap-3">
                        <span class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0 {{ $risk['severity'] === 'critical' ? 'animate-pulse-soft' : '' }}"
                              style="background: {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : ($risk['severity'] === 'medium' ? 'var(--color-info)' : 'var(--color-text-tertiary)')) }};
                                     box-shadow: 0 0 8px {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : 'transparent') }}66;"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate group-hover/risk:text-[var(--color-accent)] transition-colors duration-200"
                               style="color: var(--color-text-primary);">{{ $risk['title'] }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs" style="color: var(--color-text-tertiary);">{{ $risk['date'] }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                      style="background: {{ $risk['status'] === 'open' ? 'rgba(239,68,68,0.12)' : ($risk['status'] === 'acknowledged' ? 'rgba(245,158,11,0.12)' : 'rgba(16,185,129,0.1)') }};
                                             color: {{ $risk['status'] === 'open' ? 'var(--color-danger)' : ($risk['status'] === 'acknowledged' ? 'var(--color-warning)' : 'var(--color-success)') }};">
                                    {{ $risk['status'] === 'open' ? '未处理' : ($risk['status'] === 'acknowledged' ? '已确认' : '已关闭') }}
                                </span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                      style="background: {{ $risk['severity'] === 'critical' ? 'rgba(239,68,68,0.12)' : ($risk['severity'] === 'high' ? 'rgba(245,158,11,0.12)' : 'rgba(99,102,241,0.1)') }};
                                             color: {{ $risk['severity'] === 'critical' ? 'var(--color-danger)' : ($risk['severity'] === 'high' ? 'var(--color-warning)' : 'var(--color-info)') }};">
                                    {{ $risk['severity'] === 'critical' ? '严重' : ($risk['severity'] === 'high' ? '高' : ($risk['severity'] === 'medium' ? '中' : '低')) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <div class="text-center py-10">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background: rgba(16,185,129,0.08);">
                        <svg class="w-6 h-6" style="color: var(--color-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm font-medium" style="color: var(--color-text-secondary);">暂无风险记录</p>
                    <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">一切顺利 ✨</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Task List — Premium Table --}}
    <div class="glass-card-intense p-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.5s both;">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">任务列表</h2>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ $totalTasks }} 个任务 · {{ $doneTasks }} 已完成</p>
            </div>
            @if($totalTasks > 0)
            <div class="flex items-center gap-1">
                <div class="progress-bar" style="width: 120px; height: 5px;">
                    <div class="progress-fill" style="width: {{ $totalTasks > 0 ? ($doneTasks / $totalTasks) * 100 : 0 }}%;
                        background: linear-gradient(90deg, #10b981, #34d399);"></div>
                </div>
                <span class="text-xs font-medium tabular-nums ml-2" style="color: var(--color-text-tertiary);">{{ $doneTasks }}/{{ $totalTasks }}</span>
            </div>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b" style="border-color: var(--color-border);">
                        <th class="text-left py-3 px-3 font-medium text-xs tracking-wider uppercase" style="color: var(--color-text-tertiary);">任务</th>
                        <th class="text-left py-3 px-3 font-medium text-xs tracking-wider uppercase" style="color: var(--color-text-tertiary);">状态</th>
                        <th class="text-left py-3 px-3 font-medium text-xs tracking-wider uppercase hidden sm:table-cell" style="color: var(--color-text-tertiary);">优先级</th>
                        <th class="text-left py-3 px-3 font-medium text-xs tracking-wider uppercase hidden md:table-cell" style="color: var(--color-text-tertiary);">负责人</th>
                        <th class="text-right py-3 px-3 font-medium text-xs tracking-wider uppercase" style="color: var(--color-text-tertiary);">耗时/预估</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($project->tasks as $i => $task)
                    <tr class="border-b transition-all duration-200 group/row"
                        style="border-color: var(--color-border);
                               animation: fade-in 0.3s ease-out {{ $i * 0.04 + 0.2 }}s both;"
                        onmouseover="this.style.background='var(--color-bg-tertiary)';this.style.boxShadow='inset 3px 0 0 var(--color-accent)';"
                        onmouseout="this.style.background='transparent';this.style.boxShadow='none';">
                        <td class="py-3 px-3">
                            <div class="flex items-center gap-2.5">
                                @if($task->status === 'blocked')
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 animate-pulse-soft" style="background: var(--color-danger);"></span>
                                @endif
                                <span class="{{ $task->status === 'done' ? 'line-through' : '' }}"
                                      style="color: var(--color-text-primary);">{{ $task->title }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <span class="badge {{ $task->status === 'done' ? 'badge-success' : ($task->status === 'in_progress' ? 'badge-info' : ($task->status === 'blocked' ? 'badge-danger' : ($task->status === 'review' ? 'badge-warning' : ''))) }}">
                                {{ $task->status === 'todo' ? '待办' : ($task->status === 'in_progress' ? '进行中' : ($task->status === 'done' ? '已完成' : ($task->status === 'review' ? '审核中' : '阻塞'))) }}
                            </span>
                        </td>
                        <td class="py-3 px-3 hidden sm:table-cell">
                            <span class="text-xs font-medium"
                                  style="color: {{ $task->priority === 'critical' ? 'var(--color-danger)' : ($task->priority === 'high' ? 'var(--color-warning)' : 'var(--color-text-tertiary)') }};">
                                {{ $task->priority === 'critical' ? '紧急' : ($task->priority === 'high' ? '高' : ($task->priority === 'medium' ? '中' : '低')) }}
                            </span>
                        </td>
                        <td class="py-3 px-3 hidden md:table-cell">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0"
                                     style="background: linear-gradient(135deg, var(--color-accent), #6366f1); color: #fff;">
                                    {{ mb_substr($task->assignee?->name ?? '?', 0, 1) }}
                                </div>
                                <span style="color: var(--color-text-secondary);">{{ $task->assignee?->name ?? '未分配' }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <span class="font-mono text-xs tabular-nums" style="color: var(--color-text-secondary);">
                                    {{ $task->spent }}h
                                </span>
                                <span class="text-xs" style="color: var(--color-text-tertiary);">/</span>
                                <span class="font-mono text-xs tabular-nums" style="color: var(--color-text-tertiary);">
                                    {{ $task->estimate }}h
                                </span>
                                @if($task->spent > 0 && $task->estimate > 0)
                                <div class="progress-bar ml-2" style="width: 48px; height: 4px;">
                                    <div class="progress-fill" style="width: {{ min(($task->spent / $task->estimate) * 100, 100) }}%;
                                        background: {{ ($task->spent / $task->estimate) > 1 ? 'linear-gradient(90deg, #f59e0b, #ef4444)' : ($task->status === 'done' ? 'linear-gradient(90deg, #10b981, #34d399)' : 'linear-gradient(90deg, var(--color-accent), #6366f1)') }};"></div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12">
                            <div class="text-center">
                                <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background: rgba(99,102,241,0.08);">
                                    <svg class="w-6 h-6" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                </div>
                                <p class="text-sm font-medium" style="color: var(--color-text-secondary);">暂无任务</p>
                                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">创建第一个任务开始追踪进度</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('livewire:navigated', function() {
        initGanttChart();
        initTaskDistributionChart();
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        setTimeout(() => { initGanttChart(); initTaskDistributionChart(); }, 100);
    }

    function initGanttChart() {
        const el = document.getElementById('gantt-chart');
        if (!el || !window.ApexCharts) return;

        const ganttData = @json($ganttData);
        const series = ganttData.map(t => ({
            x: t.name,
            y: [t.start, t.end],
            fillColor: t.status === 'done' ? '#10b981' : (t.status === 'blocked' ? '#ef4444' : (t.status === 'in_progress' ? '#3b82f6' : '#94a3b8')),
        }));

        const dark = document.documentElement.classList.contains('dark');

        const options = {
            chart: {
                type: 'rangeBar',
                height: Math.max(ganttData.length * 40, 300),
                toolbar: { show: false },
                fontFamily: 'Inter, system-ui, sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 800, animateGradually: { enabled: true, delay: 100 } },
                background: 'transparent',
            },
            plotOptions: {
                bar: {
                    horizontal: true,
                    barHeight: '60%',
                    rangeBarGroupRows: true,
                    borderRadiusApplication: 'end',
                    borderRadius: 4,
                }
            },
            series: [{ name: '任务周期', data: series }],
            xaxis: {
                type: 'datetime',
                labels: { style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px', fontWeight: 500 } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: {
                labels: { style: { colors: dark ? '#cbd5e1' : '#475569', fontSize: '12px', fontWeight: 500 }, maxWidth: 220 },
            },
            grid: {
                borderColor: dark ? '#334155' : '#e2e8f0',
                strokeDashArray: 5,
                padding: { top: 10, right: 10, bottom: 0, left: 10 },
            },
            tooltip: { theme: dark ? 'dark' : 'light', x: { format: 'yyyy-MM-dd' } },
            legend: { show: false },
        };

        if (el._chart) el._chart.destroy();
        el._chart = new ApexCharts(el, options);
        el._chart.render();
    }

    function initTaskDistributionChart() {
        const el = document.getElementById('task-distribution-chart');
        if (!el || !window.ApexCharts) return;

        const td = @json($taskDistribution);
        const statusLabels = { todo: '待办', in_progress: '进行中', review: '审核中', done: '已完成', blocked: '阻塞' };
        const statusColors = { todo: '#94a3b8', in_progress: '#3b82f6', review: '#8b5cf6', done: '#10b981', blocked: '#ef4444' };

        const dark = document.documentElement.classList.contains('dark');

        const options = {
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: 'Inter, system-ui, sans-serif',
                animations: { enabled: true, easing: 'easeinout', speed: 1000, animateGradually: { enabled: true, delay: 150 } },
                background: 'transparent',
            },
            labels: td.map(d => statusLabels[d.status] || d.status),
            colors: td.map(d => statusColors[d.status] || '#94a3b8'),
            series: td.map(d => d.count),
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: '总计',
                                fontSize: '14px',
                                fontWeight: 600,
                                color: dark ? '#cbd5e1' : '#475569',
                            },
                            value: {
                                fontSize: '20px',
                                fontWeight: 800,
                                color: dark ? '#f1f5f9' : '#1e293b',
                            },
                        },
                    },
                },
            },
            legend: {
                position: 'bottom',
                fontSize: '12px',
                fontWeight: 500,
                labels: { colors: dark ? '#94a3b8' : '#64748b', useSeriesColors: false },
                markers: { radius: 5, strokeWidth: 0 },
                itemMargin: { horizontal: 12, vertical: 4 },
            },
            tooltip: { theme: dark ? 'dark' : 'light' },
            stroke: { width: 3, colors: ['transparent'] },
            dataLabels: { enabled: false },
        };

        if (el._chart) el._chart.destroy();
        el._chart = new ApexCharts(el, options);
        el._chart.render();
    }
    </script>
    @endpush
</div>
