<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">项目聚合视图</h1>
            <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">所有项目横向对比，快速掌握全局状态</p>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="glass-card-intense p-4 text-center">
            <div class="text-2xl font-bold gradient-text">{{ $totalProjects }}</div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">总项目数</div>
        </div>
        <div class="glass-card-intense p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--color-info);">{{ $totalTasks }}</div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">总任务数</div>
        </div>
        <div class="glass-card-intense p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--color-success);">{{ $completedTasks }}</div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">已完成任务</div>
        </div>
        <div class="glass-card-intense p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--color-danger);">{{ $totalRisks }}</div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">风险总数</div>
            @if($criticalRisks > 0)
                <span class="text-[10px] font-semibold" style="color: var(--color-danger);">其中 {{ $criticalRisks }} 个严重</span>
            @endif
        </div>
        <div class="glass-card-intense p-4 text-center">
            <div class="text-2xl font-bold" style="color: var(--color-accent);">{{ $avgCompletion }}%</div>
            <div class="text-xs mt-1" style="color: var(--color-text-tertiary);">平均完成率</div>
        </div>
    </div>

    {{-- Search + Sort Controls --}}
    <div class="glass-card-intense p-4 mb-6 flex flex-wrap items-center gap-4">
        {{-- Search --}}
        <div class="relative flex-1 min-w-[200px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="搜索项目名称或描述..."
                   class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border-0 outline-none transition-all duration-200"
                   style="background: var(--color-bg-tertiary); color: var(--color-text-primary);">
        </div>

        {{-- Sort --}}
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs font-medium" style="color: var(--color-text-tertiary);">排序：</span>
            @foreach($sortOptions as $field => $label)
                <button wire:click="sortBy('{{ $field }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 flex items-center gap-1"
                        style="{{ $sortField === $field
                            ? 'background: rgba(99,102,241,0.15); color: var(--color-accent);'
                            : 'background: var(--color-bg-tertiary); color: var(--color-text-secondary);' }}">
                    {{ $label }}
                    @if($sortField === $field)
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($sortDirection === 'asc')
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            @endif
                        </svg>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- Empty State --}}
    @if($projects->isEmpty())
        <div class="glass-card-intense p-12 text-center animate-fade-in">
            <svg class="w-16 h-16 mx-auto mb-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            <h3 class="text-lg font-semibold mb-1" style="color: var(--color-text-primary);">没有匹配的项目</h3>
            <p class="text-sm mb-6" style="color: var(--color-text-tertiary);">
                @if($search !== '')
                    未找到包含「{{ $search }}」的项目
                @else
                    还没有项目数据，先去创建项目吧
                @endif
            </p>
            @if($search !== '')
                <button wire:click="$set('search', '')" class="btn-premium text-sm">清除搜索</button>
            @endif
        </div>
    @else
        {{-- Projects Table --}}
        <div class="glass-card-intense overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th class="text-left px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">项目</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">状态</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">健康度</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">完成率</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">任务</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">风险</th>
                            <th class="text-center px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">来源</th>
                            <th class="text-right px-4 py-3 font-semibold text-xs uppercase tracking-wider" style="color: var(--color-text-tertiary);">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $project)
                        <tr class="transition-all duration-200 hover:brightness-110"
                            style="border-bottom: 1px solid var(--glass-border);">
                            {{-- Name --}}
                            <td class="px-4 py-3">
                                <div class="font-medium" style="color: var(--color-text-primary);">{{ $project->name }}</div>
                            </td>

                            {{-- Status Badge --}}
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusMap = [
                                        'active'    => ['label' => '进行中', 'class' => 'badge badge-info'],
                                        'completed' => ['label' => '已完成', 'class' => 'badge badge-success'],
                                        'on_hold'   => ['label' => '已暂停', 'class' => 'badge badge-warning'],
                                    ];
                                    $status = $statusMap[$project->status] ?? ['label' => $project->status, 'class' => 'badge'];
                                @endphp
                                <span class="{{ $status['class'] }}">{{ $status['label'] }}</span>
                            </td>

                            {{-- Health Indicator --}}
                            <td class="px-4 py-3 text-center">
                                @php
                                    $healthMap = [
                                        'healthy'  => ['icon' => '🟢', 'class' => 'badge badge-success'],
                                        'warning'  => ['icon' => '🟡', 'class' => 'badge badge-warning'],
                                        'critical' => ['icon' => '🔴', 'class' => 'badge badge-danger'],
                                    ];
                                    $health = $healthMap[$project->health] ?? $healthMap['warning'];
                                @endphp
                                <span class="{{ $health['class'] }}">
                                    {{ $health['icon'] }}
                                    {{ $project->health === 'healthy' ? '健康' : ($project->health === 'warning' ? '注意' : '严重') }}
                                </span>
                            </td>

                            {{-- Progress Bar --}}
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 progress-bar h-2">
                                        <div class="progress-fill h-2" style="width: {{ $project->completion_rate }}%"></div>
                                    </div>
                                    <span class="text-xs font-semibold min-w-[3rem] text-right" style="color: var(--color-accent);">{{ $project->completion_rate }}%</span>
                                </div>
                            </td>

                            {{-- Task Count --}}
                            <td class="px-4 py-3 text-center">
                                <span style="color: var(--color-text-primary);">{{ $project->completed_tasks }}/{{ $project->tasks_count }}</span>
                            </td>

                            {{-- Risk Count --}}
                            <td class="px-4 py-3 text-center">
                                @if($project->risks_count > 0)
                                    <span class="inline-flex items-center gap-1">
                                        @if($project->critical_risks > 0)
                                            <span class="text-xs font-semibold" style="color: var(--color-danger);">{{ $project->critical_risks }}严重</span>
                                        @endif
                                        @if($project->high_risks > 0)
                                            <span class="text-xs font-semibold" style="color: #f97316;">{{ $project->high_risks }}高</span>
                                        @endif
                                        <span class="text-xs" style="color: var(--color-text-tertiary);">({{ $project->risks_count }})</span>
                                    </span>
                                @else
                                    <span class="text-xs" style="color: var(--color-success);">无风险</span>
                                @endif
                            </td>

                            {{-- Source --}}
                            <td class="px-4 py-3 text-center">
                                <span class="text-xs" style="color: var(--color-text-secondary);">{{ $project->source ?? '—' }}</span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-right">
                                <a href="/projects/{{ $project->slug }}" wire:navigate.hover
                                   class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg transition-all duration-200 hover:brightness-110"
                                   style="background: rgba(99,102,241,0.1); color: var(--color-accent);">
                                    查看详情
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
