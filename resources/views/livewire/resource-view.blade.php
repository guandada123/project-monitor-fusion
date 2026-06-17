<div x-data="{ mounted: false }" x-init="mounted = true">
    {{-- Page Header — Premium --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">资源视图</h1>
                <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">团队成员工作量与任务分配</p>
            </div>
            @php
                $totalMembers = count($members);
                $avgWorkload = $totalMembers > 0 ? round(collect($members)->avg('workload')) : 0;
                $overloaded = collect($members)->filter(fn($m) => $m['workload'] > 100)->count();
                $healthyCount = collect($members)->filter(fn($m) => $m['workload'] <= 70)->count();
            @endphp
            <div class="flex items-center gap-4 text-xs">
                <div class="text-center px-3 py-2 rounded-xl" style="background: var(--color-bg-tertiary);">
                    <div class="text-lg font-bold tabular-nums" style="color: var(--color-text-primary);"
                         x-data="{ display: 0 }"
                         x-intersect.once="let t = {{ $totalMembers }}; let s = t / 20; let iv = setInterval(() => { display = Math.min(display + s, t); if (display >= t) clearInterval(iv) }, 25);">
                        <span x-text="Math.round(display)"></span>
                    </div>
                    <div style="color: var(--color-text-tertiary);">总人数</div>
                </div>
                <div class="text-center px-3 py-2 rounded-xl" style="background: var(--color-bg-tertiary);">
                    <div class="text-lg font-bold tabular-nums"
                         style="color: {{ $avgWorkload > 100 ? 'var(--color-danger)' : ($avgWorkload > 70 ? 'var(--color-warning)' : 'var(--color-success)') }};"
                         x-data="{ display: 0 }"
                         x-intersect.once="let t = {{ $avgWorkload }}; let s = t / 20; let iv = setInterval(() => { display = Math.min(display + s, t); if (display >= t) clearInterval(iv) }, 25);">
                        <span x-text="Math.round(display)"></span><span class="text-sm ml-0.5">%</span>
                    </div>
                    <div style="color: var(--color-text-tertiary);">平均负载</div>
                </div>
                @if($overloaded > 0)
                <div class="text-center px-3 py-2 rounded-xl" style="background: rgba(239,68,68,0.08);">
                    <div class="text-lg font-bold tabular-nums animate-pulse-soft" style="color: var(--color-danger);">{{ $overloaded }}</div>
                    <div style="color: var(--color-danger); opacity: 0.8;">超载</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Team Load Overview — Premium Card --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.1s both;">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-lg font-semibold tracking-tight" style="color: var(--color-text-primary);">团队负载概览</h2>
                <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">{{ $totalMembers }} 名成员 · {{ $healthyCount }} 名负载正常</p>
            </div>
            <div class="flex items-center gap-3 text-xs" style="color: var(--color-text-tertiary);">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background: #10b981;"></span>正常</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background: #f59e0b;"></span>偏高</span>
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full" style="background: #ef4444;"></span>超载</span>
            </div>
        </div>
        <div class="space-y-4">
            @foreach($members as $i => $m)
            <div class="group/member p-4 rounded-xl border transition-all duration-300 relative overflow-hidden"
                 style="background: var(--color-bg-tertiary); border-color: var(--color-border);
                        animation: scale-in 0.35s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 + 0.15 }}s both;"
                 onmouseover="this.style.borderColor='var(--color-accent)';this.style.transform='translateX(6px)';this.style.boxShadow='0 8px 30px -8px var(--color-accent)15';"
                 onmouseout="this.style.borderColor='var(--color-border)';this.style.transform='translateX(0)';this.style.boxShadow='none';">
                {{-- Hover gradient strip --}}
                <div class="absolute top-0 left-0 right-0 h-0.5 opacity-0 group-hover/member:opacity-100 transition-opacity duration-300"
                     style="background: linear-gradient(90deg, var(--color-accent), #6366f1);"></div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                    {{-- Avatar — Premium gradient --}}
                    <div class="w-11 h-11 rounded-2xl flex items-center justify-center text-white font-bold text-sm flex-shrink-0 shadow-lg transition-transform duration-300 group-hover/member:scale-110 group-hover/member:rotate-3"
                         style="background: {{ $m['workload'] > 100 ? 'linear-gradient(135deg, #ef4444, #f97316)' : ($m['workload'] > 70 ? 'linear-gradient(135deg, #f59e0b, #f97316)' : 'linear-gradient(135deg, #3b82f6, #6366f1)') }};
                                box-shadow: 0 4px 16px {{ $m['workload'] > 100 ? 'rgba(239,68,68,0.25)' : ($m['workload'] > 70 ? 'rgba(245,158,11,0.25)' : 'rgba(59,130,246,0.2)') }};">
                        {{ mb_substr($m['name'], 0, 1) }}
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm" style="color: var(--color-text-primary);">{{ $m['name'] }}</span>
                                @if($m['workload'] > 100)
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium" style="background: rgba(239,68,68,0.12); color: var(--color-danger);">超载</span>
                                @elseif($m['workload'] > 70)
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium" style="background: rgba(245,158,11,0.12); color: var(--color-warning);">偏高</span>
                                @endif
                            </div>
                            <span class="text-sm font-bold tabular-nums"
                                  style="color: {{ $m['workload'] > 100 ? 'var(--color-danger)' : ($m['workload'] > 70 ? 'var(--color-warning)' : 'var(--color-success)') }};
                                         animation: scale-in 0.4s cubic-bezier(0.16,1,0.3,1) {{ $i * 0.08 + 0.35 }}s both;"
                                  x-data="{ w: 0 }"
                                  x-intersect.once="let t = {{ $m['workload'] }}; let s = t / 25; let iv = setInterval(() => { w = Math.min(w + s, t); if (w >= t) clearInterval(iv) }, 20);">
                                <span x-text="Math.round(w)"></span>%
                            </span>
                        </div>

                        {{-- Progress bar with animated width --}}
                        <div class="progress-bar group-hover/member:h-[9px] transition-all duration-200" style="height: 7px;">
                            <div class="progress-fill transition-all duration-1000 ease-out"
                                 x-data="{ pw: 0 }"
                                 x-intersect.once="pw = {{ min($m['workload'], 200) / 2 }}"
                                 :style="'width: ' + pw + '%; background: {{ $m['workload'] > 100 ? 'linear-gradient(90deg, #f59e0b, #ef4444)' : ($m['workload'] > 70 ? 'linear-gradient(90deg, #10b981, #f59e0b)' : 'linear-gradient(90deg, #3b82f6, #10b981)') }};'"
                                 style="width: 0%;"></div>
                        </div>

                        <div class="flex gap-4 mt-2 text-xs" style="color: var(--color-text-tertiary);">
                            <span class="flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                {{ $m['pending'] }} 待处理
                            </span>
                            @if($m['overdue'] > 0)
                            <span class="flex items-center gap-1 font-medium" style="color: var(--color-danger);">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $m['overdue'] }} 延期
                            </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Heatmap Placeholder — Premium --}}
    <div class="glass-card-intense p-8 text-center" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.3s both;">
        <div class="w-16 h-16 mx-auto mb-4 rounded-2xl flex items-center justify-center" style="background: rgba(99,102,241,0.06);">
            <svg class="w-8 h-8" style="color: var(--color-accent); opacity: 0.4;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-sm font-medium" style="color: var(--color-text-secondary);">工时热力图</p>
        <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">将在接入完整数据源后展示 · 敬请期待</p>
    </div>
</div>
