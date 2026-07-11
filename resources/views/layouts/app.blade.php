<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      x-data="{
          mobileOpen: false,
          theme: localStorage.getItem('pm_theme') || 'system',
          init() {
              this.applyTheme();
              this.$watch('theme', () => {
                  localStorage.setItem('pm_theme', this.theme);
                  this.applyTheme();
              });
              window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
                  if (this.theme === 'system') this.applyTheme();
              });
          },
          applyTheme() {
              const isDark = this.theme === 'dark' || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
              document.documentElement.classList.toggle('dark', isDark);
          }
      }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Project Monitor') — PM</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500&display=swap" rel="stylesheet" />

    <!-- Flux Appearance (handles dark mode class on <html>) -->
    @fluxAppearance

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @livewireNavigation
    <style>
        .skip-link { position:absolute; left:-999em; top:0; z-index:9999; padding:8px 16px;
            background:var(--color-accent); color:#fff; border-radius:0 0 8px 0; text-decoration:none; }
        .skip-link:focus { left:0; }
    </style>
</head>
<body class="min-h-screen transition-colors duration-300"
      style="background-color: var(--color-bg-primary); color: var(--color-text-primary);">

    <!-- Skip to main content (accessibility) -->
    <a href="#main-content" class="skip-link">跳到主要内容</a>

    <!-- SPA Navigation Progress Bar -->
    <div id="nprogress" class="nprogress-bar" style="transform: scaleX(0);"></div>

    <!-- Sidebar + Main Layout (stacked above Three.js background) -->
    <div class="flex min-h-screen app-layout-wrapper">
        <!-- Sidebar — Glass Morphism -->
        <aside class="w-64 flex-shrink-0 hidden lg:flex flex-col border-r transition-all duration-300"
               style="background: var(--glass-bg);
                      backdrop-filter: blur(30px) saturate(200%);
                      -webkit-backdrop-filter: blur(30px) saturate(200%);
                      border-color: var(--glass-border);">
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 h-16 border-b" style="border-color: var(--glass-border);">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg"
                     style="background: linear-gradient(135deg, var(--color-accent), #6366f1);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <span class="text-lg font-bold tracking-tight gradient-text">Project Monitor</span>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                <x-nav-link href="/" :active="request()->is('/')" icon="dashboard">
                    仪表板
                </x-nav-link>
                <x-nav-link href="/projects" :active="request()->is('projects*')" icon="projects">
                    项目列表
                </x-nav-link>
                <x-nav-link href="/aggregate" :active="request()->is('aggregate*')" icon="aggregate">
                    聚合视图
                </x-nav-link>
                <x-nav-link href="/board" :active="request()->is('board*')" icon="board">
                    看板
                </x-nav-link>
                <x-nav-link href="/risks" :active="request()->is('risks*')" icon="risks">
                    风险中心
                </x-nav-link>
                <x-nav-link href="/resources" :active="request()->is('resources*')" icon="resources">
                    资源视图
                </x-nav-link>
                <x-nav-link href="/metrics" :active="request()->is('metrics*')" icon="metrics">
                    指标构建器
                </x-nav-link>
                <x-nav-link href="/reports" :active="request()->is('reports*')" icon="reports">
                    报表中心
                </x-nav-link>
                <x-nav-link href="/monitoring" :active="request()->is('monitoring*')" icon="monitoring">
                    慢查询监控
                </x-nav-link>

                <div class="pt-4">
                    <div class="px-3 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider" style="color: var(--color-text-tertiary);">设置</span>
                    </div>
                    <x-nav-link href="/integrations" :active="request()->is('integrations*')" icon="integrations">
                        集成管理
                    </x-nav-link>
                    <x-nav-link href="/settings" :active="request()->is('settings*')" icon="settings">
                        系统设置
                    </x-nav-link>
                    <x-nav-link href="/roles" :active="request()->is('roles*')" icon="roles">
                        角色管理
                    </x-nav-link>
                </div>
            </nav>

            <!-- User Footer -->
            @auth
            <div class="p-4 border-t" style="border-color: var(--glass-border);">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-semibold text-white flex-shrink-0 shadow-md"
                         style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate" style="color: var(--color-text-primary);">{{ auth()->user()->name }}</p>
                        <p class="text-xs truncate" style="color: var(--color-text-tertiary);">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="flex-shrink-0">
                        @csrf
                        <button type="submit" class="p-2 rounded-xl transition-all duration-200 hover:bg-red-50 dark:hover:bg-red-900/20"
                                style="color: var(--color-text-tertiary);" title="登出">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
            @endauth
        </aside>

        <!-- Mobile Header — Glass -->
        <div class="lg:hidden fixed top-0 left-0 right-0 z-40 h-14 flex items-center justify-between px-4 border-b"
             style="background: var(--glass-bg);
                    backdrop-filter: blur(30px) saturate(200%);
                    -webkit-backdrop-filter: blur(30px) saturate(200%);
                    border-color: var(--glass-border);">
            <button @click="mobileOpen = !mobileOpen" class="p-2 rounded-xl" style="color: var(--color-text-secondary);">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="font-bold gradient-text text-lg">PM</span>
            <div class="w-8"></div>
        </div>

        <!-- Main Content -->
        <main id="main-content" class="flex-1 min-w-0 lg:pl-0 pt-14 lg:pt-0">
            <!-- Top Bar — Premium Glass Style -->
            <header class="hidden lg:flex items-center justify-between h-16 px-8 border-b gap-4"
                    style="background: var(--glass-bg);
                           backdrop-filter: blur(30px) saturate(200%);
                           -webkit-backdrop-filter: blur(30px) saturate(200%);
                           border-color: var(--glass-border);">
                <div class="flex items-center gap-4 min-w-0">
                    <h1 class="text-lg font-semibold tracking-tight flex-shrink-0" style="color: var(--color-text-primary);">
                        @yield('page-title', '仪表板')
                    </h1>
                    <!-- Global Search -->
                    @livewire('global-search')
                </div>
                <div class="flex items-center gap-3 flex-shrink-0">
                    <!-- Notifications -->
                    @auth
                        @livewire('notifications-panel')
                    @endauth

                    <!-- Theme Toggle — Styled as premium pill -->
                    <div class="flex items-center gap-1 p-1 rounded-xl" style="background: var(--color-bg-tertiary);">
                        <button @click="theme = 'light'"
                                :class="theme === 'light' ? 'bg-white dark:bg-slate-700 shadow-sm' : ''"
                                class="p-1.5 rounded-lg transition-all duration-200"
                                title="浅色模式">
                            <svg class="w-4 h-4" :class="theme === 'light' ? 'text-amber-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </button>
                        <button @click="theme = 'dark'"
                                :class="theme === 'dark' ? 'bg-slate-700 shadow-sm' : ''"
                                class="p-1.5 rounded-lg transition-all duration-200"
                                title="深色模式">
                            <svg class="w-4 h-4" :class="theme === 'dark' ? 'text-indigo-400' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                            </svg>
                        </button>
                        <button @click="theme = 'system'"
                                :class="theme === 'system' ? 'bg-white dark:bg-slate-700 shadow-sm' : ''"
                                class="p-1.5 rounded-lg transition-all duration-200"
                                title="跟随系统">
                            <svg class="w-4 h-4" :class="theme === 'system' ? 'text-blue-500' : 'text-slate-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-4 lg:p-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    <!-- Export Panel (global floating button) -->
    @auth
        @livewire('export-panel')
    @endauth

    @livewireScripts
    @fluxScripts
</body>
</html>
