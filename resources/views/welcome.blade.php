<!DOCTYPE html>
<html lang="zh-CN" x-data="{ theme: localStorage.getItem('theme') || 'system' }"
      x-init="$watch('theme', v => { localStorage.setItem('theme', v); applyTheme(v) });
               applyTheme(theme);
               window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => { if(theme === 'system') applyTheme('system') });
               function applyTheme(t) {
                   const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                   document.documentElement.classList.toggle('dark', isDark);
               }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Monitor — 智能项目监控平台</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .hero-gradient { background: linear-gradient(135deg, #3b82f6 0%, #6366f1 50%, #8b5cf6 100%); }
        .feature-card { transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
        .feature-card:hover { transform: translateY(-4px); }
        .pulse-dot { animation: pulse-dot 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="min-h-screen antialiased transition-colors duration-300" style="background: var(--color-bg-primary); color: var(--color-text-primary); font-family: 'Inter', system-ui, sans-serif;">

    {{-- Theme Toggle (corner) --}}
    <div class="fixed top-4 right-4 z-50 flex items-center gap-1 p-1 rounded-xl" style="background: var(--color-bg-tertiary);">
        <button @click="theme = 'light'" :class="theme === 'light' ? 'shadow-sm' : ''"
                class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'light' ? 'background: var(--color-bg-secondary); color: var(--color-text-primary);' : 'color: var(--color-text-tertiary);'">
            浅色</button>
        <button @click="theme = 'system'" :class="theme === 'system' ? 'shadow-sm' : ''"
                class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'system' ? 'background: var(--color-bg-secondary); color: var(--color-text-primary);' : 'color: var(--color-text-tertiary);'">
            系统</button>
        <button @click="theme = 'dark'" :class="theme === 'dark' ? 'shadow-sm' : ''"
                class="px-2.5 py-1 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'dark' ? 'background: var(--color-bg-secondary); color: var(--color-text-primary);' : 'color: var(--color-text-tertiary);'">
            深色</button>
    </div>

    {{-- Hero --}}
    <div class="relative overflow-hidden">
        <div class="absolute inset-0 opacity-[0.03]" style="background: radial-gradient(circle at 20% 50%, var(--color-accent) 0%, transparent 50%), radial-gradient(circle at 80% 50%, var(--color-info) 0%, transparent 50%);"></div>
        <div class="max-w-6xl mx-auto px-4 py-24 md:py-36 text-center relative">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium mb-6"
                 style="background: color-mix(in srgb, var(--color-accent) 10%, transparent); color: var(--color-accent);">
                <span class="pulse-dot w-1.5 h-1.5 rounded-full" style="background: var(--color-accent);"></span>
                项目监控 v1.0
            </div>
            <h1 class="text-4xl md:text-6xl font-extrabold tracking-tight mb-4" style="color: var(--color-text-primary);">
                项目监控
                <span class="hero-gradient bg-clip-text" style="-webkit-background-clip: text; -webkit-text-fill-color: transparent;">尽在掌握</span>
            </h1>
            <p class="text-lg md:text-xl max-w-2xl mx-auto mb-8" style="color: var(--color-text-tertiary);">
                多源数据聚合 · 智能 KPI 追踪 · 风险实时预警 · 飞书一键推送<br>
                <span class="text-sm">为现代工程团队打造的智能项目管理中枢</span>
            </p>

            <div class="flex items-center justify-center gap-3 flex-wrap">
                <a href="{{ route('login') }}"
                   wire:navigate
                   class="px-8 py-3.5 rounded-xl font-semibold text-sm transition-all duration-300 hover:scale-105 active:scale-95 shadow-lg"
                   style="background: linear-gradient(135deg, #3b82f6, #6366f1); color: #fff; box-shadow: 0 4px 20px rgba(59,130,246,0.3);">
                    立即登录
                </a>
                <a href="#features"
                   class="px-8 py-3.5 rounded-xl font-medium text-sm transition-all duration-300 hover:scale-105 active:scale-95"
                   style="background: var(--color-bg-tertiary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                    了解更多
                </a>
            </div>
        </div>
    </div>

    {{-- Features --}}
    <div id="features" class="max-w-6xl mx-auto px-4 py-20">
        <div class="text-center mb-14">
            <h2 class="text-2xl md:text-3xl font-bold mb-3" style="color: var(--color-text-primary);">核心能力</h2>
            <p class="text-sm" style="color: var(--color-text-tertiary);">一站式项目管理，数据驱动决策</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
            $features = [
                ['icon' => 'dashboard', 'title' => '实时仪表板', 'desc' => 'Gantt 图 · Donut 分布 · Area 趋势 · 多维度统计卡片'],
                ['icon' => 'metrics', 'title' => '智能 KPI', 'desc' => '8 项预设指标 + 自定义 JSONLogic 规则 · 自动计算推送'],
                ['icon' => 'integrations', 'title' => '多源集成', 'desc' => 'GitHub → 飞书 → 钉钉 · 一键连接 · 数据同步'],
                ['icon' => 'projects', 'title' => '项目管理', 'desc' => '多项目并行 · 进度追踪 · 任务分配 · 资源负载'],
                ['icon' => 'reports', 'title' => '自动报表', 'desc' => '周报/月报一键生成 · Markdown/HTML 导出 · 定时推送'],
                ['icon' => 'resources', 'title' => '风险预警', 'desc' => '多级风险规则 · 实时告警 · 飞书即时通知'],
            ];
            @endphp
            @foreach($features as $feature)
            <div class="glass-card p-6 feature-card text-center">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mx-auto mb-4"
                     style="background: color-mix(in srgb, var(--color-accent) 12%, transparent);">
                    <svg class="w-6 h-6" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </div>
                <h3 class="font-semibold mb-2" style="color: var(--color-text-primary);">{{ $feature['title'] }}</h3>
                <p class="text-sm" style="color: var(--color-text-tertiary);">{{ $feature['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Footer --}}
    <footer class="text-center py-8 border-t" style="border-color: var(--color-border);">
        <p class="text-xs" style="color: var(--color-text-tertiary);">Project Monitor · 内部工具 · 数据驱动项目管理</p>
    </footer>
</body>
</html>
