<div x-data="kanbanBoard()" x-init="init()" class="min-h-[calc(100vh-8rem)]">
    {{-- Header — Premium Glass --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">看板</h1>
                <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">拖拽任务卡片更改状态</p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Project Selector --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 group"
                            style="background: var(--color-bg-tertiary); color: var(--color-text-primary);"
                            onmouseover="this.style.background='var(--color-border)'"
                            onmouseout="this.style.background='var(--color-bg-tertiary)'">
                        <span class="w-2 h-2 rounded-full" style="background: var(--color-accent); box-shadow: 0 0 6px var(--color-accent);"></span>
                        <span x-text="projects.find(p => p.id === projectId)?.name || '选择项目'"></span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-2 w-56 py-2 rounded-2xl z-50 border shadow-2xl"
                         style="background: var(--color-surface); border-color: var(--color-border);"
                         x-transition:enter="transition-all duration-200 ease-out"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                        <template x-for="p in projects" :key="p.id">
                            <button @click="selectProject(p.id); open = false"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-all duration-150 flex items-center gap-3"
                                    :class="p.id === projectId ? 'font-semibold' : ''"
                                    :style="p.id === projectId ? 'background: rgba(59,130,246,0.08); color: var(--color-accent);' : 'color: var(--color-text-secondary);'"
                                    onmouseover="this.style.background='var(--color-bg-tertiary)'"
                                    onmouseout="this.style.background='transparent'">
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 transition-all"
                                      :style="p.id === projectId ? 'background: var(--color-accent); box-shadow: 0 0 6px var(--color-accent); transform: scale(1.5);' : 'background: var(--color-text-tertiary);'">
                                </span>
                                <span x-text="p.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Assignee Filter --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false"
                            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm transition-all duration-200"
                            :style="assigneeFilter ? 'background: rgba(59,130,246,0.1); color: var(--color-accent);' : 'background: var(--color-bg-tertiary); color: var(--color-text-secondary);'"
                            onmouseover="this.style.background='var(--color-border)'"
                            onmouseout="this.style.background= assigneeFilter ? 'rgba(59,130,246,0.1)' : 'var(--color-bg-tertiary)'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span x-text="assigneeFilter ? (assignees.find(a => a.id === assigneeFilter)?.name || '负责人') : '全部负责人'"></span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="open ? 'rotate-180' : ''" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="open" x-cloak
                         class="absolute right-0 mt-2 w-52 py-2 rounded-2xl z-50 border shadow-2xl"
                         style="background: var(--color-surface); border-color: var(--color-border);"
                         x-transition:enter="transition-all duration-200 ease-out"
                         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0">
                        <button @click="assigneeFilter = null; open = false; $wire.set('assigneeFilter', null)"
                                class="w-full text-left px-4 py-2.5 text-sm transition-all flex items-center gap-3"
                                :style="!assigneeFilter ? 'background: rgba(59,130,246,0.08); color: var(--color-accent);' : 'color: var(--color-text-secondary);'"
                                onmouseover="this.style.background='var(--color-bg-tertiary)'"
                                onmouseout="this.style.background='transparent'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857"/></svg>
                            全部负责人
                        </button>
                        <template x-for="a in assignees" :key="a.id">
                            <button @click="assigneeFilter = a.id; open = false; $wire.set('assigneeFilter', a.id)"
                                    class="w-full text-left px-4 py-2.5 text-sm transition-all flex items-center gap-3"
                                    :class="a.id === assigneeFilter ? 'font-semibold' : ''"
                                    :style="a.id === assigneeFilter ? 'background: rgba(59,130,246,0.08); color: var(--color-accent);' : 'color: var(--color-text-secondary);'"
                                    onmouseover="this.style.background='var(--color-bg-tertiary)'"
                                    onmouseout="this.style.background='transparent'">
                                <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0"
                                      style="background: linear-gradient(135deg, var(--color-accent), #6366f1);">
                                    <span x-text="a.name.charAt(0)"></span>
                                </span>
                                <span x-text="a.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Export Button --}}
                <button class="btn-premium text-sm flex-shrink-0" wire:click="$dispatch('open-export-panel', { entityType: 'task' })">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    导出任务
                </button>
            </div>
        </div>

        {{-- Column stats row --}}
        <div class="flex gap-4 mt-4 pt-4 border-t" style="border-color: var(--color-border);">
            @php
                $statusLabels = ['todo' => '待办', 'in_progress' => '进行中', 'review' => '审核中', 'done' => '已完成', 'blocked' => '阻塞'];
                $statusColors = ['todo' => '#94a3b8', 'in_progress' => '#3b82f6', 'review' => '#8b5cf6', 'done' => '#10b981', 'blocked' => '#ef4444'];
                $sum = collect($columns)->sum(fn($c) => count($c));
            @endphp
            @foreach(['todo', 'in_progress', 'review', 'done', 'blocked'] as $s)
            <div class="flex items-center gap-1.5 text-xs font-medium" style="color: var(--color-text-tertiary);">
                <span class="w-2 h-2 rounded-full" style="background: {{ $statusColors[$s] }}; box-shadow: 0 0 4px {{ $statusColors[$s] }}66;"></span>
                {{ $statusLabels[$s] }}
                <span class="tabular-nums font-bold" style="color: var(--color-text-secondary);"
                      x-data="{ count: {{ count($columns[$s] ?? []) }} }"
                      x-text="count">0</span>
            </div>
            @endforeach
            <div class="flex-1 text-right text-xs font-medium" style="color: var(--color-text-tertiary);">
                共 <span class="tabular-nums font-bold" style="color: var(--color-text-secondary);">{{ $sum }}</span> 个任务
            </div>
        </div>
    </div>

    {{-- Kanban Board — Horizontal Scroll --}}
    <div class="overflow-x-auto pb-6 custom-scrollbar" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.1s both;"
         x-ref="board">
        <div class="flex gap-5 min-w-[900px]">
            @php
                $columnConfig = [
                    'todo'        => ['label' => '待办',       'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', 'color' => '#94a3b8', 'bg' => 'rgba(148,163,184,0.06)'],
                    'in_progress' => ['label' => '进行中',     'icon' => 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.06)'],
                    'review'      => ['label' => '审核中',     'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.06)'],
                    'done'        => ['label' => '已完成',     'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.06)'],
                    'blocked'     => ['label' => '阻塞',       'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.06)'],
                ];
                $priorityLabels = ['critical' => '紧急', 'high' => '高', 'medium' => '中', 'low' => '低'];
                $priorityColors = ['critical' => '#ef4444', 'high' => '#f59e0b', 'medium' => '#6366f1', 'low' => '#94a3b8'];
                $priorityBgs = ['critical' => 'rgba(239,68,68,0.12)', 'high' => 'rgba(245,158,11,0.12)', 'medium' => 'rgba(99,102,241,0.1)', 'low' => 'rgba(148,163,184,0.1)'];
            @endphp

            @foreach(['todo', 'in_progress', 'review', 'done', 'blocked'] as $status)
            @php $cfg = $columnConfig[$status]; @endphp
            <div class="flex-1 min-w-[200px]">
                {{-- Column Header --}}
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center" style="background: {{ $cfg['bg'] }};">
                            <svg class="w-4 h-4" style="color: {{ $cfg['color'] }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $cfg['icon'] }}"/>
                            </svg>
                        </div>
                        <span class="text-sm font-semibold" style="color: var(--color-text-primary);">{{ $cfg['label'] }}</span>
                    </div>
                    <span class="text-xs font-bold tabular-nums px-2 py-0.5 rounded-lg"
                          style="background: {{ $cfg['bg'] }}; color: {{ $cfg['color'] }};"
                          x-data="{ count: {{ count($columns[$status] ?? []) }} }"
                          x-text="count">{{ count($columns[$status] ?? []) }}</span>
                </div>

                {{-- Drop Zone --}}
                <div class="rounded-2xl p-2.5 min-h-[400px] transition-all duration-200"
                     style="background: {{ $cfg['bg'] }}; border: 2px dashed transparent;"
                     :class="dragOverStatus === '{{ $status }}' ? 'border-[{{ $cfg["color"] }}] scale-[1.01]' : ''"
                     x-ref="column-{{ $status }}"
                     data-status="{{ $status }}"
                     @dragover.prevent="dragOverStatus = '{{ $status }}'"
                     @dragleave="dragOverStatus = null"
                     @drop="onDrop($event, '{{ $status }}')">

                    @if(!empty($columns[$status]))
                    @foreach($columns[$status] as $i => $task)
                    <div class="task-card group mb-2.5 cursor-grab active:cursor-grabbing select-none"
                         style="animation: slide-up 0.3s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.05 }}s both;
                                --card-color: {{ $cfg['color'] }};"
                         draggable="true"
                         data-task-id="{{ $task['id'] }}"
                         data-status="{{ $status }}"
                         @dragstart="onDragStart($event, {{ $task['id'] }}, '{{ $status }}')"
                         @dragend="onDragEnd()"
                         x-data="{ hover: false }">

                        {{-- Card body --}}
                        <div class="relative z-10">
                            {{-- Top row: priority badge + drag indicator --}}
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-md leading-none"
                                      style="background: {{ $priorityBgs[$task['priority']] ?? $priorityBgs['low'] }}; color: {{ $priorityColors[$task['priority']] ?? $priorityColors['low'] }};">
                                    {{ $priorityLabels[$task['priority']] ?? $task['priority'] }}
                                </span>
                                <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity duration-200" style="color: var(--color-text-tertiary);" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M10 9h4V6h3l-5-5-5 5h3v3zm-1 1H6V7l-5 5 5 5v-3h3v-4zm14 2l-5-5v3h-3v4h3v3l5-5zm-9 3h-4v3H7l5 5 5-5h-3v-3z"/>
                                </svg>
                            </div>

                            {{-- Title --}}
                            <p class="text-sm font-medium leading-snug mb-2.5 transition-colors duration-200"
                               style="color: var(--color-text-primary);"
                               :class="hover ? 'text-[var(--card-color)]' : ''"
                               x-text="'{{ str_replace("'", "\\'", $task['title']) }}'">{{ $task['title'] }}</p>

                            {{-- Bottom row: assignee + due date + effort --}}
                            <div class="flex items-center justify-between mt-auto">
                                <div class="flex items-center gap-2">
                                    {{-- Assignee avatar --}}
                                    @if($task['assignee'])
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center text-[9px] font-bold text-white flex-shrink-0 shadow-sm transition-transform duration-200 group-hover:scale-110"
                                         style="background: linear-gradient(135deg, var(--color-accent), #6366f1);"
                                         :title="'{{ $task['assignee']['name'] }}'">
                                        {{ mb_substr($task['assignee']['name'], 0, 1) }}
                                    </div>
                                    @else
                                    <div class="w-6 h-6 rounded-lg flex items-center justify-center flex-shrink-0"
                                         style="background: var(--color-bg-tertiary); color: var(--color-text-tertiary);">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    @endif

                                    {{-- Due date --}}
                                    @if($task['due_at'])
                                    <span class="text-[10px] font-medium tabular-nums flex items-center gap-1"
                                          style="color: {{ $task['is_overdue'] ? 'var(--color-danger)' : 'var(--color-text-tertiary)' }};">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span>{{ $task['due_at'] }}</span>
                                        @if($task['is_overdue'])
                                        <span class="animate-pulse-soft" style="color: var(--color-danger);">●</span>
                                        @endif
                                    </span>
                                    @endif

                                    {{-- Effort --}}
                                    @if(($task['estimate'] ?? 0) > 0 || ($task['spent'] ?? 0) > 0)
                                    <span class="text-[10px] tabular-nums" style="color: var(--color-text-tertiary);">
                                        {{ $task['spent'] ?? 0 }}h/{{ $task['estimate'] ?? 0 }}h
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Bottom accent bar --}}
                        <div class="absolute bottom-0 left-3 right-3 h-0.5 rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                             style="background: linear-gradient(90deg, transparent, {{ $cfg['color'] }}40, transparent);"></div>
                    </div>
                    @endforeach
                    @else
                    {{-- Empty state --}}
                    <div class="flex flex-col items-center justify-center py-10 text-center">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-2"
                             style="background: var(--color-bg-tertiary);">
                            <svg class="w-5 h-5" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $cfg['icon'] }}"/>
                            </svg>
                        </div>
                        <p class="text-xs" style="color: var(--color-text-tertiary);">暂无任务</p>
                    </div>
                    @endif

                    {{-- Drop zone indicator --}}
                    <div class="hidden-drop-indicator h-1 rounded-full mx-2 opacity-0 transition-all duration-200"
                         style="background: {{ $cfg['color'] }}33;"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Premium Toast: Drag feedback --}}
    <div x-show="showToast" x-cloak
         x-transition:enter="transition-all duration-300 ease-out"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition-all duration-200 ease-in"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3"
         style="background: var(--color-surface); border: 1px solid var(--color-border);">
        <svg class="w-5 h-5" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 13l4 4L19 7"/>
        </svg>
        <span class="text-sm font-medium" style="color: var(--color-text-primary);" x-text="toastMsg"></span>
    </div>

    @push('scripts')
    <script>
        function kanbanBoard() {
            return {
                projects: @json($projects),
                projectId: {{ $projectId ?? 'null' }},
                assigneeFilter: {{ $assigneeFilter ?? 'null' }},
                assignees: @json($assignees),
                dragOverStatus: null,
                showToast: false,
                toastMsg: '',
                toastTimeout: null,
                draggedTaskId: null,

                init() {
                    // Re-initialize SortableJS after Livewire navigation
                    this.initSortable();
                },

                selectProject(id) {
                    this.projectId = id;
                    this.$wire.set('projectId', id);
                },

                onDragStart(e, taskId, status) {
                    this.draggedTaskId = taskId;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', JSON.stringify({ taskId, status }));
                    // Add drag ghost styling
                    e.target.classList.add('opacity-40', 'scale-95', 'rotate-1');
                },

                onDragEnd() {
                    this.draggedTaskId = null;
                    this.dragOverStatus = null;
                    // Remove ghost classes from all cards
                    document.querySelectorAll('.task-card').forEach(el => {
                        el.classList.remove('opacity-40', 'scale-95', 'rotate-1');
                    });
                },

                onDrop(e, newStatus) {
                    e.preventDefault();
                    this.dragOverStatus = null;

                    try {
                        const data = JSON.parse(e.dataTransfer.getData('text/plain'));
                        const { taskId, status: oldStatus } = data;

                        if (oldStatus === newStatus) return;

                        // Update via Livewire
                        this.$wire.moveTask(taskId, newStatus).then(() => {
                            this.showToastMsg(`任务已移至「{{ $columnConfig['todo']['label'] }}」
                                .replace('{{ $columnConfig['todo']['label'] }}',
                                    {todo:'待办',in_progress:'进行中',review:'审核中',done:'已完成',blocked:'阻塞'}[newStatus]
                                )`);
                        });
                    } catch (err) {
                        console.warn('Drop error:', err);
                    }
                },

                showToastMsg(msg) {
                    this.toastMsg = msg;
                    this.showToast = true;
                    if (this.toastTimeout) clearTimeout(this.toastTimeout);
                    this.toastTimeout = setTimeout(() => { this.showToast = false; }, 1800);
                },

                initSortable() {
                    // Initialize SortableJS on each column
                    const columns = ['todo', 'in_progress', 'review', 'done', 'blocked'];
                    const statusLabels = { todo: '待办', in_progress: '进行中', review: '审核中', done: '已完成', blocked: '阻塞' };

                    columns.forEach(status => {
                        const el = this.$refs['column-' + status];
                        if (!el || el._sortable) return;

                        el._sortable = new Sortable(el, {
                            group: 'kanban',
                            animation: 200,
                            easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
                            ghostClass: 'task-ghost',
                            dragClass: 'task-dragging',
                            delay: 100,
                            delayOnTouchOnly: true,
                            touchStartThreshold: 3,
                            onStart: (evt) => {
                                this.draggedTaskId = parseInt(evt.item.dataset.taskId);
                                evt.item.style.transition = 'transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease';
                            },
                            onEnd: (evt) => {
                                this.draggedTaskId = null;
                                // Clean up
                                evt.item.style.transition = '';
                                evt.item.style.opacity = '';
                                evt.item.style.transform = '';
                            },
                            onChange: (evt) => {
                                // Visual feedback: show which column is receiving
                                const targetColumn = evt.to.closest('[data-status]');
                                if (targetColumn) {
                                    this.dragOverStatus = targetColumn.dataset.status;
                                }
                            },
                            onMove: (evt) => {
                                // Allow movement between columns
                                return true;
                            },
                            onAdd: (evt) => {
                                // Task was moved to a new column
                                const newStatus = evt.to.dataset.status;
                                const taskId = parseInt(evt.item.dataset.taskId);
                                const oldStatus = evt.from.dataset.status;

                                if (newStatus && taskId && newStatus !== oldStatus) {
                                    this.$wire.moveTask(taskId, newStatus).then(() => {
                                        this.showToastMsg(`任务已移至「${statusLabels[newStatus] || newStatus}」`);
                                    });
                                }
                            },
                        });
                    });
                },
            };
        }

        // Reinitialize Sortable after Livewire page updates
        document.addEventListener('livewire:navigated', () => {
            const boardEl = document.querySelector('[x-data="kanbanBoard()"]');
            if (boardEl && boardEl.__x) {
                const instance = boardEl.__x.$data;
                if (instance.initSortable) {
                    setTimeout(() => instance.initSortable(), 50);
                }
            }
        });

        // ── Premium Drag Ghost Styles ──
        const styleId = 'kanban-drag-styles';
        if (!document.getElementById(styleId)) {
            const style = document.createElement('style');
            style.id = styleId;
            style.textContent = `
                .task-ghost {
                    opacity: 0.3 !important;
                    transform: scale(0.92) rotate(-1deg) !important;
                    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
                    border: 2px dashed var(--color-accent) !important;
                    border-radius: 1rem !important;
                    box-shadow: 0 0 30px rgba(59,130,246,0.15) !important;
                }
                .task-dragging {
                    transform: rotate(3deg) scale(1.02) !important;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 2px var(--color-accent) !important;
                    border-radius: 1rem !important;
                    opacity: 0.9 !important;
                    z-index: 1000 !important;
                    pointer-events: none !important;
                }
                .task-dragging * {
                    pointer-events: none !important;
                }
                .sortable-chosen {
                    opacity: 0.95 !important;
                }
                .sortable-ghost {
                    opacity: 0.2 !important;
                }
            `;
            document.head.appendChild(style);
        }
    </script>
    @endpush
</div>
