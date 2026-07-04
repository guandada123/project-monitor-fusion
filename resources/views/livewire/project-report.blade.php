<div class="min-h-screen" x-data="{
    get isDark() { return document.documentElement.classList.contains('dark'); }
}">
    {{-- Header --}}
    <div class="glass-card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">📊 {{ $project->name }} — 项目报告</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Slug: {{ $project->slug }} ｜ 生成时间: {{ $generatedAt->format('Y-m-d H:i') }}
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <a href="/projects/{{ $project->slug }}"
                   class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                          text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    ← 返回详情
                </a>
                <button onclick="pushToFeishu('{{ $project->slug }}')"
                        class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                               text-sm font-medium bg-blue-50 dark:bg-blue-900/20
                               hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors"
                        id="btn-push-feishu">
                    📤 推送飞书
                </button>
                <a href="/projects/{{ $project->slug }}/report.pdf?dateFrom={{ $dateFrom }}&dateTo={{ $dateTo }}&dimension={{ $dimension }}"
                   class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                          text-sm font-medium hover:bg-green-50 dark:hover:bg-green-900/20 transition-colors">
                    📥 下载 PDF
                </a>
            </div>
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">开始日期</label>
                <input type="date" wire:model.live="dateFrom"
                       class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800
                              px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent
                              dark:text-gray-100 transition-all">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">结束日期</label>
                <input type="date" wire:model.live="dateTo"
                       class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800
                              px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent
                              dark:text-gray-100 transition-all">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">分析维度</label>
                <select wire:model.live="dimension"
                        class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800
                               px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent
                               dark:text-gray-100 transition-all">
                    <option value="progress">📈 进度趋势</option>
                    <option value="quality">🔍 质量指标</option>
                    <option value="load">👥 负载分布</option>
                </select>
            </div>
            <div class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                <span wire:loading wire:target="dimension,dateFrom,dateTo"
                      class="inline-flex items-center gap-1 text-blue-500">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    更新中...
                </span>
            </div>
        </div>
    </div>

    {{-- KPI Cards + Donut --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="glass-card p-6 flex flex-col items-center justify-center">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">项目完成率</h3>
            <div class="flex justify-center">{!! $charts['donut'] !!}</div>
        </div>
        <div class="glass-card p-6 space-y-4">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">关键指标</h3>
            <div class="grid grid-cols-2 gap-3">
                {!! $charts['kpi_done'] !!}
                {!! $charts['kpi_open'] !!}
                {!! $charts['kpi_risk'] !!}
            </div>
            <div class="pt-2">{!! $charts['progress'] !!}</div>
        </div>
        <div class="glass-card p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
                {{ $charts['dimension']['header'] ?? '分析视图' }}
            </h3>
            <div class="flex justify-center">
                {!! $charts['dimension']['svg'] ?? '' !!}
            </div>
        </div>
    </div>

    {{-- Task Distribution + Risk Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="glass-card p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">📋 任务分布</h3>
            <div class="flex justify-center">{!! $charts['bars_tasks'] !!}</div>
        </div>
        <div class="glass-card p-6">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">⚠️ 风险概览</h3>
            <div class="flex justify-center">{!! $charts['bars_risks'] !!}</div>
        </div>
    </div>

    {{-- Task Table --}}
    <div class="glass-card p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">
            📝 任务清单
            <span class="text-xs text-gray-400 ml-2">({{ $tasks->total() }} 条)</span>
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">标题</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">状态</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">优先级</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">截止时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $t)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="py-2 px-3">{{ $t->title }}</td>
                            <td class="py-2 px-3">
                                @php
                                    $status = strtolower($t->status ?? 'open');
                                    $statusLabels = [
                                        'done' => '已完成', 'closed' => '已关闭', 'completed' => '已完成',
                                        'in_progress' => '进行中', 'doing' => '进行中',
                                        'open' => '待处理', 'todo' => '待处理',
                                        'blocked' => '已阻塞',
                                    ];
                                    $statusLabel = $statusLabels[$status] ?? $status;
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ in_array($status, ['done','closed','completed']) ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                    {{ in_array($status, ['in_progress','doing']) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                    {{ in_array($status, ['open','todo']) ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    {{ $status === 'blocked' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-xs">{{ $t->priority ?? '-' }}</td>
                            <td class="py-2 px-3 text-xs text-gray-500">{{ optional($t->due_at)->format('Y-m-d') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">暂无任务数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{-- Pagination links --}}
        <div class="mt-4">
            {{ $tasks->links() }}
        </div>
    </div>

    {{-- Risks Table --}}
    <div class="glass-card p-6 mb-6">
        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">⚠️ 风险清单</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">描述</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">等级</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">状态</th>
                        <th class="text-left py-2 px-3 text-xs font-medium text-gray-500">创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($risks as $r)
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="py-2 px-3">{{ $r->title ?? '-' }}</td>
                            <td class="py-2 px-3">
                                @php
                                    $sev = $r->severity ?? 'low';
                                    $sevLabels = ['critical' => '严重', 'high' => '严重', 'medium' => '中等', 'low' => '低'];
                                    $sevLabel = $sevLabels[$sev] ?? $sev;
                                @endphp
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ in_array($sev, ['critical','high']) ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                    {{ $sev === 'medium' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    {{ $sev === 'low' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}">
                                    {{ $sevLabel }}
                                </span>
                            </td>
                            <td class="py-2 px-3 text-xs">{{ $r->status ?? '-' }}</td>
                            <td class="py-2 px-3 text-xs text-gray-500">{{ optional($r->created_at)->format('Y-m-d') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-400">暂无风险记录</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-center text-xs text-gray-400 dark:text-gray-500 pb-8">
        本报告由 Project Monitor 自动生成 · {{ $generatedAt->format('Y-m-d H:i:s') }}
    </div>
</div>

@push('scripts')
<script>
async function pushToFeishu(slug) {
    const btn = document.getElementById('btn-push-feishu');
    const orig = btn.innerHTML;
    btn.innerHTML = '⏳ 推送中...';
    btn.disabled = true;

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/projects/' + slug + '/push-feishu', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
        });
        const data = await resp.json();
        if (data.ok) {
            btn.innerHTML = '✅ 已推送';
            btn.className = btn.className.replace(/bg-\S+/g, 'bg-green-100 dark:bg-green-900/30');
        } else {
            btn.innerHTML = '❌ 失败';
            btn.className = btn.className.replace(/bg-\S+/g, 'bg-red-100 dark:bg-red-900/30');
            alert(data.message || '推送失败');
        }
    } catch(e) {
        btn.innerHTML = '❌ 网络错误';
        btn.className = btn.className.replace(/bg-\S+/g, 'bg-red-100 dark:bg-red-900/30');
    }
    setTimeout(() => {
        btn.innerHTML = orig;
        btn.className = btn.className.replace(/bg-\S+/g, 'bg-blue-50 dark:bg-blue-900/20');
        btn.disabled = false;
    }, 2500);
}
</script>
@endpush
