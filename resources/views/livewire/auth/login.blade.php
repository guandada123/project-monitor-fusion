<div class="glass-card p-8 animate-fade-in">
    {{-- Logo & Title --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4"
             style="background: color-mix(in srgb, var(--color-accent) 15%, transparent);">
            <svg class="w-7 h-7" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">Project Monitor</h1>
        <p class="text-sm mt-2" style="color: var(--color-text-tertiary);">项目监控 · 数据驱动 · 风险预警</p>
    </div>

    {{-- Theme Toggle --}}
    <div class="flex justify-center gap-1 mb-6 p-1 rounded-xl"
         style="background: var(--color-bg-tertiary);">
        <button @click="theme = 'light'"
                :class="theme === 'light' ? 'shadow-sm' : ''"
                class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'light'
                    ? 'background: var(--color-bg-primary); color: var(--color-text-primary);'
                    : 'color: var(--color-text-tertiary);'">
            <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            浅色
        </button>
        <button @click="theme = 'system'"
                :class="theme === 'system' ? 'shadow-sm' : ''"
                class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'system'
                    ? 'background: var(--color-bg-primary); color: var(--color-text-primary);'
                    : 'color: var(--color-text-tertiary);'">
            <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            系统
        </button>
        <button @click="theme = 'dark'"
                :class="theme === 'dark' ? 'shadow-sm' : ''"
                class="px-4 py-1.5 rounded-lg text-xs font-medium transition-all duration-200"
                :style="theme === 'dark'
                    ? 'background: var(--color-bg-primary); color: var(--color-text-primary);'
                    : 'color: var(--color-text-tertiary);'">
            <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            深色
        </button>
    </div>

    {{-- Error Alert --}}
    @error('email')
    <div class="mb-4 px-4 py-3 rounded-xl text-sm flex items-center gap-2 animate-fade-in"
         style="background: color-mix(in srgb, var(--color-danger) 12%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 25%, transparent);">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        {{ $message }}
    </div>
    @enderror

    {{-- Login Form --}}
    <form wire:submit="login" class="space-y-4">
        <div>
            <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">邮箱</label>
            <input type="email" name="email" wire:model="email" autofocus
                   class="w-full px-4 py-3 rounded-xl border text-sm transition-all duration-200 focus:ring-2 focus:outline-none"
                   style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                   placeholder="guan@pm.local">
        </div>
        <div>
            <label class="text-sm font-medium block mb-1.5" style="color: var(--color-text-secondary);">密码</label>
            <input type="password" name="password" wire:model="password"
                   class="w-full px-4 py-3 rounded-xl border text-sm transition-all duration-200 focus:ring-2 focus:outline-none"
                   style="background: var(--color-bg-tertiary); border-color: var(--color-border); color: var(--color-text-primary); --tw-ring-color: var(--color-accent);"
                   placeholder="••••••••">
        </div>

        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 cursor-pointer" style="color: var(--color-text-tertiary);">
                <input type="checkbox" wire:model="remember"
                       class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                记住我
            </label>
        </div>

        <button type="submit" wire:loading.attr="disabled"
                class="w-full py-3 rounded-xl text-sm font-semibold transition-all duration-200 hover:scale-[1.01] active:scale-95 flex items-center justify-center gap-2"
                style="background: var(--color-accent); color: #fff;">
            <span wire:loading.remove wire:target="login">登录</span>
            <span wire:loading wire:target="login" class="flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                登录中...
            </span>
        </button>
    </form>

    {{-- Footer --}}
    <p class="text-center text-xs mt-6" style="color: var(--color-text-tertiary);">
        Project Monitor v1.0 · 内部工具
    </p>
</div>
