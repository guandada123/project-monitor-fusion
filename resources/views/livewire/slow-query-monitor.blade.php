<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">慢查询监控</h1>
        <p class="mt-1 text-sm" style="color: var(--color-text-tertiary);">
            监控数据库慢查询，分析性能瓶颈
            @if($this->activeFilterCount > 0)
            <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-medium"
                  style="background: rgba(59,130,246,0.1); color: var(--color-accent);">
                {{ $this->activeFilterCount }} 个筛选
            </span>
            @endif
        </p>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl p-4" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
            <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">总查询数</p>
            <p class="text-2xl font-bold mt-1" style="color: var(--color-text-primary);">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
            <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">平均耗时</p>
            <p class="text-2xl font-bold mt-1" style="color: var(--color-text-primary);">
                {{ $stats['avg_time'] >= 1000 ? sprintf('%.2fs', $stats['avg_time'] / 1000) : sprintf('%dms', (int)$stats['avg_time']) }}
            </p>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
            <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">最大耗时</p>
            <p class="text-2xl font-bold mt-1" style="color: var(--color-text-primary);">
                {{ $stats['max_time'] >= 1000 ? sprintf('%.2fs', $stats['max_time'] / 1000) : sprintf('%dms', (int)$stats['max_time']) }}
            </p>
        </div>
        <div class="rounded-2xl p-4" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
            <p class="text-xs font-medium uppercase tracking-wider" style="color: var(--color-text-tertiary);">严重告警</p>
            <p class="text-2xl font-bold mt-1" style="{{ $stats['critical_count'] > 0 ? 'color: #ef4444;' : 'color: var(--color-text-primary);' }}">
                {{ $stats['critical_count'] }}
            </p>
        </div>
    </div>

    {{-- Filter Bar --}}
    <div class="rounded-2xl p-4 mb-6" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
        <div class="flex flex-wrap items-center gap-3">
            {{-- Time Range --}}
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--color-text-tertiary);">时间范围</label>
                <select wire:model.live="filterRange"
                        class="rounded-xl px-3 py-1.5 text-sm border"
                        style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--glass-border);">
                    <option value="1h">过去 1 小时</option>
                    <option value="6h">过去 6 小时</option>
                    <option value="24h">过去 24 小时</option>
                    <option value="7d">过去 7 天</option>
                    <option value="30d">过去 30 天</option>
                    <option value="all">全部时间</option>
                </select>
            </div>

            {{-- Min Duration --}}
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--color-text-tertiary);">最小时长</label>
                <select wire:model.live="filterMinDuration"
                        class="rounded-xl px-3 py-1.5 text-sm border"
                        style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--glass-border);">
                    <option value="100">&ge; 100ms</option>
                    <option value="200">&ge; 200ms</option>
                    <option value="500">&ge; 500ms</option>
                    <option value="1000">&ge; 1000ms</option>
                    <option value="2000">&ge; 2000ms</option>
                    <option value="5000">&ge; 5000ms</option>
                </select>
            </div>

            {{-- Type --}}
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--color-text-tertiary);">类型</label>
                <select wire:model.live="filterType"
                        class="rounded-xl px-3 py-1.5 text-sm border"
                        style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--glass-border);">
                    <option value="all">全部</option>
                    <option value="slow">慢查询</option>
                    <option value="critical">严重告警</option>
                </select>
            </div>

            {{-- Connection --}}
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--color-text-tertiary);">连接</label>
                <select wire:model.live="filterConnection"
                        class="rounded-xl px-3 py-1.5 text-sm border"
                        style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--glass-border);">
                    <option value="">全部连接</option>
                    @foreach($connectionOptions as $conn)
                        <option value="{{ $conn }}">{{ $conn }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Search --}}
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium mb-1" style="color: var(--color-text-tertiary);">搜索 SQL</label>
                <input wire:model.live.debounce.300ms="search" type="text"
                       placeholder="搜索 SQL 或调用位置..."
                       class="w-full rounded-xl px-3 py-1.5 text-sm border"
                       style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--glass-border);">
            </div>

            {{-- Actions --}}
            <div class="flex items-end gap-2 self-end">
                <button wire:click="clearAll"
                        onclick="return confirm('确定要清空所有慢查询记录吗？')"
                        class="px-3 py-1.5 rounded-xl text-sm font-medium transition-all duration-200 hover:opacity-80"
                        style="background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2);">
                    清空记录
                </button>
            </div>
        </div>
    </div>

    {{-- Trend Chart --}}
    <div class="rounded-2xl p-4 mb-6" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
        <h3 class="text-sm font-semibold mb-3" style="color: var(--color-text-primary);">慢查询趋势
            <span class="text-xs font-normal ml-2" style="color: var(--color-text-tertiary);">{{ $this->rangeLabel }}</span>
        </h3>
        <div id="slow-query-trend-chart" style="height: 220px;"></div>
    </div>

    {{-- Table --}}
    <div class="rounded-2xl overflow-hidden" style="background: var(--color-bg-secondary); border: 1px solid var(--glass-border);">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th class="px-4 py-3 text-left font-medium cursor-pointer select-none"
                            style="color: var(--color-text-tertiary);"
                            wire:click="toggleSort('captured_at')">
                            时间
                            @if($sortField === 'captured_at')
                            <span class="ml-1">{{ $sortDirection === 'desc' ? '↓' : '↑' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium cursor-pointer select-none"
                            style="color: var(--color-text-tertiary);"
                            wire:click="toggleSort('time')">
                            耗时
                            @if($sortField === 'time')
                            <span class="ml-1">{{ $sortDirection === 'desc' ? '↓' : '↑' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--color-text-tertiary);">类型</th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--color-text-tertiary);">连接</th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--color-text-tertiary);">SQL</th>
                        <th class="px-4 py-3 text-left font-medium" style="color: var(--color-text-tertiary);">调用位置</th>
                        <th class="px-4 py-3 text-center font-medium" style="color: var(--color-text-tertiary);">详情</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($queries as $query)
                    <tr class="transition-colors duration-150 hover:opacity-90"
                        style="border-bottom: 1px solid var(--glass-border);"
                        wire:key="sq-{{ $query->id }}">
                        <td class="px-4 py-3 whitespace-nowrap" style="color: var(--color-text-primary);">
                            {{ $query->captured_at->format('H:i:s') }}<br>
                            <span class="text-xs" style="color: var(--color-text-tertiary);">{{ $query->captured_at->format('m-d') }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono">
                            <span style="color: {{ $query->is_critical ? '#ef4444' : ($query->time >= 500 ? '#f59e0b' : 'var(--color-text-primary)') }};">
                                {{ $query->duration_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($query->is_critical)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" style="background: rgba(239,68,68,0.12); color: #ef4444;">严重</span>
                            @elseif($query->is_slow)
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" style="background: rgba(245,158,11,0.12); color: #f59e0b;">慢查询</span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium" style="background: rgba(59,130,246,0.1); color: #3b82f6;">一般</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="text-xs font-mono" style="color: var(--color-text-secondary);">{{ $query->connection_name }}</span>
                        </td>
                        <td class="px-4 py-3 max-w-md">
                            <code class="text-xs leading-relaxed block truncate" style="color: var(--color-text-secondary);"
                                  title="{{ $query->query }}">{{ $query->query_preview }}</code>
                        </td>
                        <td class="px-4 py-3 max-w-[200px]">
                            @if($query->caller)
                            <span class="text-xs truncate block" style="color: var(--color-text-tertiary);" title="{{ $query->caller }}">
                                {{ basename(dirname($query->caller)) }}/{{ basename(explode(':', $query->caller)[0]) }}:{{ explode(':', $query->caller)[1] ?? '' }}
                            </span>
                            @else
                            <span class="text-xs" style="color: var(--color-text-tertiary);">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button wire:click="toggleExpand({{ $query->id }})"
                                    class="p-1 rounded-lg transition-all duration-200 hover:opacity-70"
                                    style="color: var(--color-text-tertiary);">
                                <svg class="w-4 h-4 transition-transform duration-200 {{ $expandedId === $query->id ? 'rotate-180' : '' }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    {{-- Expandable detail row --}}
                    @if($expandedId === $query->id)
                    <tr style="background: var(--color-bg-tertiary);">
                        <td colspan="7" class="px-4 py-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">SQL 原文</h4>
                                    <pre class="p-3 rounded-xl text-xs leading-relaxed overflow-x-auto whitespace-pre-wrap"
                                         style="background: var(--color-bg-primary); color: var(--color-text-secondary); border: 1px solid var(--glass-border); max-height: 200px;">{{ $query->query }}</pre>
                                </div>
                                <div>
                                    <h4 class="text-xs font-semibold uppercase tracking-wider mb-2" style="color: var(--color-text-tertiary);">执行详情</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span style="color: var(--color-text-tertiary);">耗时</span>
                                            <span class="font-mono font-medium" style="color: var(--color-text-primary);">{{ $query->duration_label }}</span>
                                        </div>
                                        <div class="flex justify-between p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span style="color: var(--color-text-tertiary);">连接</span>
                                            <span class="font-mono" style="color: var(--color-text-primary);">{{ $query->connection_name ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span style="color: var(--color-text-tertiary);">请求路径</span>
                                            <span class="font-mono" style="color: var(--color-text-primary);">{{ $query->request_uri ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span style="color: var(--color-text-tertiary);">请求方法</span>
                                            <span class="font-mono" style="color: var(--color-text-primary);">{{ $query->request_method ?? '—' }}</span>
                                        </div>
                                        <div class="flex justify-between p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span style="color: var(--color-text-tertiary);">调用位置</span>
                                            <span class="font-mono text-xs" style="color: var(--color-text-primary);">{{ $query->caller ?? '—' }}</span>
                                        </div>
                                        @if($query->bindings && count($query->bindings) > 0)
                                        <div class="p-2 rounded-lg" style="background: var(--color-bg-primary);">
                                            <span class="block mb-1" style="color: var(--color-text-tertiary);">绑定参数</span>
                                            <pre class="text-xs" style="color: var(--color-text-secondary);">{{ json_encode($query->bindings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @endif
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <p class="text-sm" style="color: var(--color-text-tertiary);">暂无慢查询记录</p>
                            <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">所有数据库查询均在正常范围内</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($queries->hasPages())
        <div class="px-4 py-3" style="border-top: 1px solid var(--glass-border);">
            {{ $queries->links() }}
        </div>
        @endif
    </div>

    @push('scripts')
    <script>
    document.addEventListener('livewire:init', function () {
        initSlowQueryTrendChart();
    });

    document.addEventListener('livewire:update', function () {
        initSlowQueryTrendChart();
    });

    function isDark() {
        return document.documentElement.classList.contains('dark');
    }

    function initSlowQueryTrendChart() {
        const el = document.getElementById('slow-query-trend-chart');
        if (!el || !window.ApexCharts) return;

        const trendData = @json($trendChart);
        const dark = isDark();

        const options = {
            chart: {
                type: 'area',
                height: 220,
                toolbar: { show: false },
                animations: { enabled: true, easing: 'easeinout', speed: 800, animateGradually: { enabled: true, delay: 150 } },
                background: 'transparent',
                stacked: false,
            },
            colors: ['#3b82f6', '#f59e0b', '#ef4444'],
            series: [
                { name: '查询数', data: trendData.counts || [] },
                { name: '平均耗时(ms)', data: trendData.avgTimes || [] },
                { name: '严重告警', data: trendData.criticalCounts || [] },
            ],
            stroke: {
                curve: 'smooth',
                width: [2, 2, 2],
                dashArray: [0, 3, 0],
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.35,
                    opacityTo: 0.05,
                    stops: [0, 95, 100],
                },
            },
            xaxis: {
                categories: trendData.periods || [],
                type: 'category',
                labels: {
                    style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px', fontWeight: 500 },
                    rotate: -30,
                    trim: true,
                },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: [
                {
                    seriesName: '查询数',
                    min: 0,
                    labels: {
                        style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px' },
                        formatter: function(v) { return Math.floor(v); },
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
                {
                    seriesName: '平均耗时(ms)',
                    opposite: true,
                    min: 0,
                    labels: {
                        style: { colors: dark ? '#64748b' : '#94a3b8', fontSize: '11px' },
                        formatter: function(v) { return v >= 1000 ? (v/1000).toFixed(1)+'s' : Math.floor(v) + 'ms'; },
                    },
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                },
            ],
            grid: {
                borderColor: dark ? 'rgba(255,255,255,0.05)' : 'rgba(0,0,0,0.06)',
                strokeDashArray: 4,
                xaxis: { lines: { show: false } },
            },
            legend: {
                show: true,
                position: 'top',
                horizontalAlign: 'right',
                fontSize: '12px',
                labels: { colors: dark ? '#cbd5e1' : '#475569' },
                itemMargin: { horizontal: 12, vertical: 2 },
                markers: { width: 8, height: 8, radius: 4 },
            },
            tooltip: {
                theme: dark ? 'dark' : 'light',
                x: { show: true },
                y: {
                    formatter: function(v, { seriesIndex }) {
                        if (seriesIndex === 1) return v >= 1000 ? (v/1000).toFixed(1) + 's' : Math.floor(v) + 'ms';
                        return v;
                    },
                },
            },
            dataLabels: { enabled: false },
        };

        if (el._chart) el._chart.destroy();
        el._chart = new ApexCharts(el, options);
        el._chart.render();
    }
    </script>
    @endpush
</div>
