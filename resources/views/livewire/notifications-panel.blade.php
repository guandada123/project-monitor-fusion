<div
    x-data="{
        open: $wire.$entangle('show'),
        init() {
            // 点击外部关闭
            document.addEventListener('click', (e) => {
                if (!this.$el.contains(e.target)) {
                    $wire.closePanel();
                }
            });
            // 监听通知清除事件
            Livewire.on('notifications-cleared', () => {
                // visual feedback handled by Livewire re-render
            });
        }
    }"
    class="relative"
>
    <!-- Bell Button -->
    <button
        @click="open = !open"
        class="relative p-2 rounded-xl transition-all duration-200 hover:scale-110 hover:bg-accent/5 active:scale-95"
        style="color: var(--color-text-secondary);"
        title="通知"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>

        <!-- Unread Badge -->
        <span
            x-show="$wire.unreadCount > 0"
            x-cloak
            x-transition:enter="transition-all duration-200 ease-out"
            x-transition:enter-start="opacity-0 scale-50"
            x-transition:enter-end="opacity-100 scale-100"
            class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] flex items-center justify-center rounded-full text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-800 animate-pulse-soft"
            style="background: linear-gradient(135deg, #ef4444, #dc2626);">
            <span x-text="$wire.unreadCount > 99 ? '99+' : $wire.unreadCount"></span>
        </span>
    </button>

    <!-- Dropdown Panel -->
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition-all duration-200 ease-out"
        x-transition:enter-start="opacity-0 scale-95 translate-y-[-8px]"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition-all duration-150 ease-in"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 top-full mt-2 w-[400px] z-50 overflow-hidden"
        style="background: var(--color-surface);
               border: 1px solid var(--glass-border);
               border-radius: var(--radius-xl);
               box-shadow: 0 20px 60px rgba(0,0,0,0.12);
               backdrop-filter: blur(40px) saturate(200%);
               -webkit-backdrop-filter: blur(40px) saturate(200%);">

        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3.5 border-b"
             style="border-color: var(--glass-border);">
            <h3 class="text-sm font-semibold" style="color: var(--color-text-primary);">通知</h3>
            <div class="flex items-center gap-2">
                <!-- Filter toggle -->
                <button
                    wire:click="toggleFilter"
                    class="text-xs px-2.5 py-1 rounded-lg transition-all duration-200"
                    :style="{
                        background: $wire.filter === 'unread' ? 'var(--color-accent)' : 'var(--color-bg-tertiary)',
                        color: $wire.filter === 'unread' ? 'white' : 'var(--color-text-secondary)'
                    }">
                    仅未读
                </button>
                <!-- Mark all read -->
                <button
                    x-show="$wire.unreadCount > 0"
                    x-cloak
                    wire:click="markAllAsRead"
                    class="text-xs px-2.5 py-1 rounded-lg transition-all duration-200 hover:bg-accent/10"
                    style="color: var(--color-accent); background: transparent;">
                    全部已读
                </button>
            </div>
        </div>

        <!-- Notification List -->
        <div class="max-h-[360px] overflow-y-auto">
            @if ($notifications->count() > 0)
                <div class="py-1">
                    @foreach ($notifications as $n)
                        <button
                            wire:click="handleNotificationClick('{{ $n['id'] }}')"
                            class="w-full flex items-start gap-3 px-5 py-3 text-left transition-all duration-150"
                            :class="!{{ $n['read'] ? '1' : '0' }} ? 'bg-accent/5' : 'hover:bg-accent/3'"
                            style="animation: notif-fade-in 0.25s ease-out both;
                                   animation-delay: {{ $loop->index * 20 }}ms;
                                   border-left: 3px solid {{ $n['read'] ? 'transparent' : ($n['severity_color'] ?? 'var(--color-accent)') }};">
                            <!-- Icon -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center"
                                 style="background: {{ $n['read'] ? 'var(--color-bg-tertiary)' : 'rgba(239,68,68,0.08)' }};">
                                @if ($n['type'] === 'risk_alert')
                                    <svg class="w-4 h-4" style="color: {{ $n['severity_color'] ?? 'var(--color-danger)' }};" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                @elseif ($n['type'] === 'daily_report')
                                    <svg class="w-4 h-4" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                @endif
                            </div>

                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm leading-snug" style="color: var(--color-text-primary);"
                                   :style="!{{ $n['read'] ? '1' : '0' }} ? 'font-weight: 500' : ''">
                                    {{ $n['message'] }}
                                </p>
                                <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">
                                    {{ $n['time'] }}
                                </p>
                            </div>

                            <!-- Unread dot -->
                            @if (!$n['read'])
                                <div class="flex-shrink-0 mt-1.5">
                                    <span class="block w-2 h-2 rounded-full"
                                          style="background: var(--color-accent);"></span>
                                </div>
                            @endif
                        </button>
                    @endforeach
                </div>
            @else
                <!-- Empty State -->
                <div class="flex flex-col items-center justify-center py-10 px-6 text-center">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-3"
                         style="background: var(--color-bg-tertiary);">
                        <svg class="w-7 h-7" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium" style="color: var(--color-text-secondary);">
                        {{ $filter === 'unread' ? '没有未读通知' : '暂无通知' }}
                    </p>
                    <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">
                        {{ $filter === 'unread' ? '新通知会在这里显示' : '风险告警和日报会自动推送至此' }}
                    </p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        @if ($notifications->count() > 0 && $unreadCount > 0)
            <div class="px-5 py-2.5 border-t text-center"
                 style="border-color: var(--glass-border);">
                <span class="text-xs" style="color: var(--color-text-tertiary);">
                    还有 <strong style="color: var(--color-accent);">{{ $unreadCount }}</strong> 条未读通知
                </span>
            </div>
        @endif
    </div>

    <style>
        @keyframes notif-fade-in {
            from { opacity: 0; transform: translateX(-8px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .bg-accent\/5 { background: rgba(59, 130, 246, 0.05); }
        .bg-accent\/3 { background: rgba(59, 130, 246, 0.03); }
        .hover\:bg-accent\/10:hover { background: rgba(59, 130, 246, 0.1); }
        .dark .bg-accent\/5 { background: rgba(96, 165, 250, 0.06); }
        .dark .bg-accent\/3 { background: rgba(96, 165, 250, 0.03); }
        .dark .hover\:bg-accent\/10:hover { background: rgba(96, 165, 250, 0.08); }
        .active\:scale-95:active { transform: scale(0.95); }
    </style>
</div>
