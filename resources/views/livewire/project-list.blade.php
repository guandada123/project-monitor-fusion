<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--color-text-primary);">项目列表</h1>
            <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">共 {{ $projects->count() }} 个项目</p>
        </div>
        <div class="flex items-center gap-3">
            <button class="btn-premium text-sm" wire:click="$dispatch('open-export-panel', { entityType: 'project' })">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                导出项目
            </button>
            <button class="btn-premium text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                新建项目
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($projects as $project)
        <a href="/projects/{{ $project->slug }}" wire:navigate.hover class="glass-card p-6 block group">
            <div class="flex items-start justify-between mb-3">
                <h3 class="font-semibold group-hover:text-blue-500 transition-colors" style="color: var(--color-text-primary);">{{ $project->name }}</h3>
                <span class="badge {{ $project->status === 'active' ? 'badge-info' : ($project->status === 'completed' ? 'badge-success' : 'badge-warning') }}">
                    {{ $project->status === 'active' ? '进行中' : ($project->status === 'completed' ? '已完成' : ($project->status === 'on_hold' ? '暂停' : $project->status)) }}
                </span>
            </div>
            <p class="text-sm mb-4 line-clamp-2" style="color: var(--color-text-secondary);">{{ $project->description ?? '暂无描述' }}</p>
            <div class="space-y-2">
                <div class="flex justify-between text-xs">
                    <span style="color: var(--color-text-tertiary);">完成率</span>
                    <span class="font-semibold" style="color: var(--color-accent);">{{ $project->completion_rate }}%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $project->completion_rate }}%"></div>
                </div>
            </div>
            <div class="flex items-center gap-4 mt-4 text-xs" style="color: var(--color-text-tertiary);">
                <span>📋 {{ $project->tasks_count }} 任务</span>
                <span>⚠️ {{ $project->risks_count }} 风险</span>
                <span>📡 {{ $project->source }}</span>
            </div>
        </a>
        @endforeach
    </div>
</div>
