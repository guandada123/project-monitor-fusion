<div class="min-h-screen">
    {{-- Page Header --}}
    <div class="glass-card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold">📊 项目对比分析</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    已选 {{ $selectedCount }} / {{ $totalCount }} 个项目
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <button wire:click="selectAll"
                    class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                           text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    ✅ 全选
                </button>
                <button wire:click="clearAll"
                    class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                           text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    ✕ 清空
                </button>
                <button onclick="pushCompareToFeishu()"
                    class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                           text-sm font-medium bg-blue-50 dark:bg-blue-900/20
                           hover:bg-blue-100 dark:hover:bg-blue-900/40 transition-colors
                           {{ $selectedCount === 0 ? 'opacity-50 pointer-events-none' : '' }}"
                    id="btn-push-compare"
                    @if($selectedCount === 0) disabled @endif>
                    📤 批量推送飞书
                </button>
                <a href="/"
                   class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700
                          text-sm font-medium hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    ← 返回首页
                </a>
            </div>
        </div>

        {{-- Project Selector Pills --}}
        <div class="flex flex-wrap gap-2">
            @foreach($allProjects as $idx => $proj)
                @php
                    $isSelected = in_array($proj->slug, $selected);
                    $pillColor = ['#4f46e5','#10b981','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899','#06b6d4'][$idx % 8];
                    $comp = $proj->completionRate();
                @endphp
                <button wire:click="toggleProject('{{ $proj->slug }}')"
                    class="relative px-4 py-2.5 rounded-xl text-sm font-medium border-2 transition-all duration-200
                           {{ $isSelected ? 'shadow-md scale-[1.02]' : 'opacity-60 hover:opacity-90 border-dashed' }}"
                    style="border-color: {{ $isSelected ? $pillColor : '#d1d5db' }};
                           background: {{ $isSelected ? $pillColor.'15' : 'transparent' }};
                           color: {{ $isSelected ? $pillColor : 'var(--text-sub)' }};">
                    <span class="relative z-10">{{ $proj->name }}</span>
                    <span class="ml-1 text-xs opacity-70">({{ $comp }}%)</span>
                    @if($isSelected)
                        <span class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full flex items-center justify-center text-white text-[10px]"
                              style="background:{{ $pillColor }};">✓</span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    @if($selectedCount === 0)
        {{-- Empty State --}}
        <div class="glass-card p-12 text-center">
            <div class="text-5xl mb-4">📋</div>
            <h2 class="text-lg font-semibold text-gray-500 dark:text-gray-400 mb-2">请选择要对比的项目</h2>
            <p class="text-sm text-gray-400 dark:text-gray-500">点击上方项目标签开始对比分析</p>
        </div>
    @else
        {{-- KPI Comparison Matrix --}}
        <div class="glass-card p-6 mb-6">
            <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-4 uppercase tracking-wide">关键指标矩阵</h2>
            <div class="table-scroll">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b-2 border-gray-200 dark:border-gray-700">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">指标</th>
                            @foreach($comparisons as $comp)
                                <th class="text-center py-3 px-4 text-xs font-semibold uppercase tracking-wider whitespace-nowrap"
                                    style="color:{{ $comp['color'] }};">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full mr-1.5" style="background:{{ $comp['color'] }};"></span>
                                    {{ $comp['project']->name }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">{{ collect($comparisons)->every(fn($c) => !($c['hasTaskData'] ?? false)) ? '📐 代码规模' : '📈 完成率' }}</td>
                            @foreach($comparisons as $comp)
                                @php
                                    $cc = $comp['completion'];
                                    $hasTask = $comp['hasTaskData'] ?? false;
                                    $cStyle = $cc >= 80 ? 'color:#10b981;' : ($cc >= 50 ? 'color:#f59e0b;' : 'color:#ef4444;');
                                @endphp
                                {{-- 2026-10-31：无任务数据时 completion 恒为 0，一排 0% 毫无信息量；
                                     改显示代码规模，多项目之间才有可比性。 --}}
                                <td class="py-3 px-4 text-center font-bold text-base" style="{{ $hasTask ? $cStyle : 'color:#6366f1;' }}">
                                    @if($hasTask)
                                        {{ $cc }}%
                                    @else
                                        {{ ($comp['codeLoc'] ?? 0) > 0 ? number_format($comp['codeLoc'] / 10000, 1).' 万行' : '未采集' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">📋 总任务</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center">{{ $comp['totalTasks'] }} <span class="text-xs text-gray-400">个</span></td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">✅ 已完成</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center" style="color:#10b981;">{{ $comp['doneTasks'] }}</td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">🔄 进行中</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center" style="color:#3b82f6;">{{ $comp['inProgress'] }}</td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">⏳ 待处理</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center" style="color:#f59e0b;">{{ $comp['openTasks'] }}</td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">🚫 已阻塞</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center" style="{{ $comp['blocked'] > 0 ? 'color:#ef4444;font-weight:600;' : 'color:#9ca3af;' }}">
                                    {{ $comp['blocked'] }}
                                </td>
                            @endforeach
                        </tr>
                        <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">⚠️ 风险总数</td>
                            @foreach($comparisons as $comp)
                                @php $rStyle = $comp['openRisks'] > 3 ? 'color:#ef4444;' : ($comp['openRisks'] > 0 ? 'color:#f59e0b;' : 'color:#10b981;'); @endphp
                                <td class="py-3 px-4 text-center" style="{{ $rStyle }}">{{ $comp['totalRisks'] }} <span class="text-xs opacity-70">({{ $comp['openRisks'] }}开)</span></td>
                            @endforeach
                        </tr>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-medium text-gray-600 dark:text-gray-300">📅 周期</td>
                            @foreach($comparisons as $comp)
                                <td class="py-3 px-4 text-center text-xs text-gray-500">{{ $comp['startAt'] }} ~ {{ $comp['endAt'] }}</td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SVG Comparison Charts Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="glass-card p-6 flex flex-col items-center justify-center">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ collect($comparisons)->every(fn($c) => !($c['hasTaskData'] ?? false)) ? '📐 代码规模对比' : '📈 完成率对比' }}</h3>
                <div class="overflow-x-auto w-full flex justify-center">
                    {!! $completionChart !!}
                </div>
            </div>
            <div class="glass-card p-6 flex flex-col items-center justify-center">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">📋 任务分布对比</h3>
                <div class="overflow-x-auto w-full flex justify-center">
                    {!! $tasksChart !!}
                </div>
            </div>
            <div class="glass-card p-6 flex flex-col items-center justify-center">
                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">⚠️ 风险分布对比</h3>
                <div class="overflow-x-auto w-full flex justify-center">
                    {!! $risksChart !!}
                </div>
            </div>
        </div>

        {{-- Per-Project Detail Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-6">
            @foreach($comparisons as $comp)
                <div class="glass-card p-5" style="border-left:4px solid {{ $comp['color'] }};">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-3 h-3 rounded-full flex-shrink-0" style="background:{{ $comp['color'] }};"></span>
                        <a href="/projects/{{ $comp['project']->slug }}" class="font-semibold text-sm hover:underline hover:text-blue-600 dark:hover:text-blue-400 transition-colors">
                            {{ $comp['project']->name }}
                        </a>
                        <span class="ml-auto px-2 py-0.5 rounded-full text-[10px] font-medium uppercase
                            {{ $comp['project']->status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                            {{ $comp['project']->status }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-gray-400 text-xs">完成率</span>
                            <div class="font-bold" style="color:{{ $comp['color'] }};">{{ ($comp['hasTaskData'] ?? false) ? $comp['completion'].'%' : (($comp['codeLoc'] ?? 0) > 0 ? number_format($comp['codeLoc'] / 10000, 1).' 万行' : '未采集') }}</div>
                        </div>
                        <div>
                            <span class="text-gray-400 text-xs">阻塞</span>
                            <div class="font-bold {{ $comp['blocked'] > 0 ? 'text-red-500' : 'text-gray-400' }}">{{ $comp['blocked'] }}</div>
                        </div>
                        <div>
                            <span class="text-gray-400 text-xs">进行中</span>
                            <div class="font-bold text-blue-500">{{ $comp['inProgress'] }}</div>
                        </div>
                        <div>
                            <span class="text-gray-400 text-xs">待处理</span>
                            <div class="font-bold text-amber-500">{{ $comp['openTasks'] }}</div>
                        </div>
                    </div>

                    {{-- Mini progress bar --}}
                    <div class="mt-3 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700"
                             style="width:{{ $comp['completion'] }}%;background:{{ $comp['color'] }};"></div>
                    </div>

                    {{-- Quick actions --}}
                    <div class="mt-3 flex gap-1">
                        <a href="/projects/{{ $comp['project']->slug }}/report"
                           class="flex-1 text-center text-[11px] px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700
                                  hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors text-gray-500">
                            📄 报告
                        </a>
                        <button onclick="pushSingleFeishu('{{ $comp['project']->slug }}', this)"
                           class="flex-1 text-[11px] px-2 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700
                                  hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors text-gray-500">
                            📤 推送
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="text-center text-xs text-gray-400 dark:text-gray-500 pb-8">
        项目对比分析 · 由 Project Monitor 生成 · {{ now()->format('Y-m-d H:i') }}
    </div>
</div>

@push('scripts')
<script>
async function pushCompareToFeishu() {
    const btn = document.getElementById('btn-push-compare');
    if (!btn || btn.disabled) return;
    const origText = btn.innerHTML;
    btn.innerHTML = '⏳ 推送中...';
    btn.disabled = true;

    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const slugs = @json($selected);
        const resp = await fetch('/projects/compare/push-feishu', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ slugs: slugs })
        });
        const data = await resp.json();
        if (data.ok) {
            btn.innerHTML = '✅ 已推送 ' + data.message.split('\n').filter(m => m.startsWith('✅')).length + ' 个项目';
            btn.className = btn.className.replace(/bg-\S+/g, 'bg-green-100 dark:bg-green-900/30');
        } else {
            btn.innerHTML = '❌ 失败';
            alert(data.message || data.error || '推送失败');
        }
    } catch(e) {
        btn.innerHTML = '❌ 网络错误';
        alert('推送失败: ' + e.message);
    }
    setTimeout(() => {
        btn.innerHTML = origText;
        btn.disabled = false;
    }, 3000);
}

async function pushSingleFeishu(slug, el) {
    const orig = el.innerHTML;
    el.innerHTML = '⏳...';
    el.disabled = true;
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/projects/' + slug + '/push-feishu', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' }
        });
        const data = await resp.json();
        el.innerHTML = data.ok ? '✅' : '❌';
    } catch(e) {
        el.innerHTML = '❌';
    }
    setTimeout(() => {
        el.innerHTML = orig;
        el.disabled = false;
    }, 2000);
}
</script>
@endpush
