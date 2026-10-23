<div>
    {{-- Page Header --}}
    <div class="glass-card-intense p-6 mb-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) both;">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: var(--color-text-primary);">系统设置</h1>
                <p class="text-sm mt-1" style="color: var(--color-text-tertiary);">配置项目发现策略和飞书通知推送</p>
            </div>
            @if(session('message'))
            <div class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium animate-fade-in"
                 style="background: color-mix(in srgb, var(--color-success) 12%, transparent); color: var(--color-success);">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                {{ session('message') }}
            </div>
            @endif
        </div>
    </div>

    {{-- Flash Message for Save --}}
    @if($saved)
    <div class="mb-6 px-5 py-3 rounded-2xl flex items-center gap-3 text-sm font-medium animate-fade-in"
         style="background: color-mix(in srgb, var(--color-success) 10%, transparent); color: var(--color-success); border: 1px solid color-mix(in srgb, var(--color-success) 20%, transparent);">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>设置已保存</span>
        <button wire:click="$set('saved', false)" class="ml-auto p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    @endif

    {{-- Push Test Result --}}
    @if(session('push_result'))
    <div class="mb-6 px-5 py-3 rounded-2xl flex items-center gap-3 text-sm font-medium animate-fade-in"
         style="background: color-mix(in srgb, {{ session('push_result')['ok'] ? 'var(--color-success)' : 'var(--color-danger)' }} 10%, transparent);
                color: {{ session('push_result')['ok'] ? 'var(--color-success)' : 'var(--color-danger)' }};
                border: 1px solid color-mix(in srgb, {{ session('push_result')['ok'] ? 'var(--color-success)' : 'var(--color-danger)' }} 20%, transparent);">
        @if(session('push_result')['ok'])
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>✅ 飞书推送测试成功！请查看飞书群消息确认。</span>
        @else
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>❌ 推送失败：{{ session('push_result')['error'] }}</span>
        @endif
        <button onclick="this.parentElement.remove()" class="ml-auto p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Left Column --}}

        {{-- Notification Settings --}}
        <div class="space-y-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.1s both;">
            <div class="glass-card-intense p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                         style="background: color-mix(in srgb, var(--color-accent) 12%, transparent);">
                        <svg class="w-5 h-5" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold tracking-tight" style="color: var(--color-text-primary);">飞书通知推送</h3>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">配置 Webhook 后，系统将在每日 {{ $feishuDailyTime }} 自动推送</p>
                    </div>
                </div>

                <form wire:submit="saveNotifications" class="space-y-5">

                    {{-- Webhook URL --}}
                    <div>
                        <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">Webhook URL</label>

                        @if($editingWebhook)
                            {{-- 编辑模式：从空白开始输入（已存明文绝不回填进快照） --}}
                            <div class="flex items-center gap-2">
                                <input type="url" wire:model="feishuWebhookUrl"
                                       placeholder="{{ $hasStoredWebhook ? '已配置，留空则不修改' : 'https://open.feishu.cn/open-apis/bot/v2/hook/...' }}"
                                       class="flex-1 px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border
                                              focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                                       style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border);">
                                @if($hasStoredWebhook)
                                    <button type="button" wire:click="clearStoredWebhook"
                                            class="btn-ghost text-sm py-2.5 px-3 whitespace-nowrap flex-shrink-0"
                                            style="color: var(--color-danger);">
                                        清除
                                    </button>
                                @endif
                                <button type="button" wire:click="cancelWebhookEdit"
                                        class="btn-ghost text-sm py-2.5 px-3 whitespace-nowrap flex-shrink-0">
                                    取消
                                </button>
                            </div>
                        @else
                            {{-- 展示模式：显示脱敏 URL（掩码由服务端从存储值计算） --}}
                            <div class="flex items-center gap-2">
                                @if($hasStoredWebhook && ! $clearWebhook)
                                    <div class="flex-1 px-4 py-2.5 rounded-xl text-sm font-mono tracking-wider truncate"
                                         style="background: var(--color-bg-tertiary); color: var(--color-text-secondary); border: 1px solid var(--color-border);">
                                        {{ $this->maskedUrl }}
                                    </div>
                                    <button type="button" wire:click="revealWebhook"
                                            class="btn-ghost text-sm py-2.5 px-3 whitespace-nowrap flex-shrink-0">
                                        编辑
                                    </button>
                                @elseif($clearWebhook)
                                    <div class="flex-1 px-4 py-2.5 rounded-xl text-sm"
                                         style="background: color-mix(in srgb, var(--color-danger) 8%, transparent); color: var(--color-danger); border: 1px solid color-mix(in srgb, var(--color-danger) 25%, transparent);">
                                        已标记清除，保存后生效
                                    </div>
                                    <button type="button" wire:click="cancelWebhookEdit"
                                            class="btn-ghost text-sm py-2.5 px-3 whitespace-nowrap flex-shrink-0">
                                        撤销
                                    </button>
                                @else
                                    <div class="flex-1 px-4 py-2.5 rounded-xl text-sm"
                                         style="background: var(--color-bg-tertiary); color: var(--color-text-tertiary); border: 1px solid var(--color-border);">
                                        尚未配置 Webhook URL
                                    </div>
                                    <button type="button" wire:click="revealWebhook"
                                            class="btn-premium text-sm py-2.5 px-3 whitespace-nowrap flex-shrink-0">
                                        添加
                                    </button>
                                @endif
                            </div>
                        @endif

                        @error('feishuWebhookUrl') <p class="text-xs mt-1.5" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                        <p class="text-[11px] mt-1.5" style="color: var(--color-text-tertiary);">
                            在飞书群设置中添加 Webhook 机器人，复制 URL 粘贴到此
                        </p>
                    </div>

                    {{-- Daily Time --}}
                    <div>
                        <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">日报推送时间</label>
                        <div class="flex items-center gap-4">
                            <input type="time" wire:model="feishuDailyTime"
                                   class="px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border
                                          focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                                   style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border); width: 130px;">
                            @error('feishuDailyTime') <p class="text-xs" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                            <span class="text-xs" style="color: var(--color-text-tertiary);">
                                每日此时自动推送项目日报
                            </span>
                        </div>
                    </div>

                    {{-- Partition Tag & Bot Name --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">分区标签</label>
                            <input type="text" wire:model="feishuPartitionTag"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border
                                          focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                                   style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border);">
                            @error('feishuPartitionTag') <p class="text-xs mt-1.5" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">Bot 名称</label>
                            <input type="text" wire:model="feishuBotName"
                                   class="w-full px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border
                                          focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                                   style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border);">
                            @error('feishuBotName') <p class="text-xs mt-1.5" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Toggle Switches --}}
                    <div class="space-y-3 pt-2">
                        <label class="flex items-center justify-between px-4 py-3 rounded-xl cursor-pointer transition-all duration-200"
                               style="background: var(--color-bg-tertiary);"
                               onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                     style="background: color-mix(in srgb, var(--color-info) 12%, transparent);">
                                    <svg class="w-4 h-4" style="color: var(--color-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-sm font-medium" style="color: var(--color-text-primary);">日报推送</span>
                                    <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">每日定时推送项目汇总到飞书</p>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('feishuDailyReport', {{ $feishuDailyReport ? 'false' : 'true' }})"
                                    class="relative w-11 h-6 rounded-full transition-all duration-300 flex-shrink-0"
                                    style="background: {{ $feishuDailyReport ? 'var(--color-accent)' : 'var(--color-bg-primary)' }}; border: 2px solid {{ $feishuDailyReport ? 'var(--color-accent)' : 'var(--color-border)' }};">
                                <span class="absolute top-0.5 left-0.5 w-3.5 h-3.5 rounded-full bg-white transition-all duration-300 shadow-sm"
                                      style="transform: {{ $feishuDailyReport ? 'translateX(20px)' : 'translateX(0)' }};"></span>
                            </button>
                        </label>

                        <label class="flex items-center justify-between px-4 py-3 rounded-xl cursor-pointer transition-all duration-200"
                               style="background: var(--color-bg-tertiary);"
                               onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center"
                                     style="background: color-mix(in srgb, var(--color-danger) 12%, transparent);">
                                    <svg class="w-4 h-4" style="color: var(--color-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-sm font-medium" style="color: var(--color-text-primary);">风险告警推送</span>
                                    <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">检测到严重风险时即时推送</p>
                                </div>
                            </div>
                            <button type="button" wire:click="$set('feishuRiskAlert', {{ $feishuRiskAlert ? 'false' : 'true' }})"
                                    class="relative w-11 h-6 rounded-full transition-all duration-300 flex-shrink-0"
                                    style="background: {{ $feishuRiskAlert ? 'var(--color-accent)' : 'var(--color-bg-primary)' }}; border: 2px solid {{ $feishuRiskAlert ? 'var(--color-accent)' : 'var(--color-border)' }};">
                                <span class="absolute top-0.5 left-0.5 w-3.5 h-3.5 rounded-full bg-white transition-all duration-300 shadow-sm"
                                      style="transform: {{ $feishuRiskAlert ? 'translateX(20px)' : 'translateX(0)' }};"></span>
                            </button>
                        </label>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit" wire:loading.attr="disabled"
                                class="btn-premium text-sm py-2.5 px-5 font-bold flex items-center gap-2">
                            <span wire:loading.remove wire:target="saveNotifications">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <span wire:loading wire:target="saveNotifications" class="flex items-center gap-1">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            保存通知设置
                        </button>

                        <button type="button" wire:click="testPush" wire:loading.attr="disabled"
                                class="btn-ghost text-sm py-2.5 px-4 flex items-center gap-2">
                            <span wire:loading.remove wire:target="testPush">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                            </span>
                            <span wire:loading wire:target="testPush" class="flex items-center gap-1">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </span>
                            发送测试
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right Column --}}

        {{-- General Settings --}}
        <div class="space-y-6" style="animation: slide-up 0.4s cubic-bezier(0.16,1,0.3,1) 0.15s both;">
            <div class="glass-card-intense p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                         style="background: color-mix(in srgb, var(--color-warning) 12%, transparent);">
                        <svg class="w-5 h-5" style="color: var(--color-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold tracking-tight" style="color: var(--color-text-primary);">项目发现策略</h3>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">自动发现新项目的命名规则和默认模板</p>
                    </div>
                </div>

                <form wire:submit="saveGeneral" class="space-y-5">
                    <div>
                        <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">项目命名规则</label>
                        <input type="text" wire:model="namingRule"
                               class="w-full px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border
                                      focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                               style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border);">
                        @error('namingRule') <p class="text-xs mt-1.5" style="color: var(--color-danger);">{{ $message }}</p> @enderror
                        <p class="text-[11px] mt-1.5" style="color: var(--color-text-tertiary);">使用通配符匹配项目名，如 <code class="px-1 py-0.5 rounded text-[10px]" style="background: var(--color-bg-tertiary);">project:*</code></p>
                    </div>

                    <div>
                        <label class="text-sm font-medium block mb-2" style="color: var(--color-text-secondary);">默认模板</label>
                        <select wire:model="defaultTemplate"
                                class="w-full px-4 py-2.5 rounded-xl text-sm transition-all duration-200 border appearance-none
                                       focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]/30 focus:border-[var(--color-accent)]"
                                style="background: var(--color-bg-primary); color: var(--color-text-primary); border-color: var(--color-border);">
                            <option value="default">标准模板</option>
                            <option value="agile">敏捷开发</option>
                            <option value="waterfall">瀑布模型</option>
                        </select>
                        <p class="text-[11px] mt-1.5" style="color: var(--color-text-tertiary);">新建项目时应用的默认任务模板</p>
                    </div>

                    <button type="submit" wire:loading.attr="disabled"
                            class="btn-premium text-sm py-2.5 px-5 font-bold flex items-center gap-2">
                        <span wire:loading.remove wire:target="saveGeneral">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <span wire:loading wire:target="saveGeneral" class="flex items-center gap-1">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </span>
                        保存项目策略
                    </button>
                </form>
            </div>

            {{-- System Info Card --}}
            <div class="glass-card-intense p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                         style="background: color-mix(in srgb, var(--color-info) 12%, transparent);">
                        <svg class="w-5 h-5" style="color: var(--color-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold tracking-tight" style="color: var(--color-text-primary);">系统信息</h3>
                        <p class="text-xs mt-0.5" style="color: var(--color-text-tertiary);">当前运行状态与调度概览</p>
                    </div>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between px-4 py-3 rounded-xl"
                         style="background: var(--color-bg-tertiary);">
                        <span style="color: var(--color-text-secondary);">日报推送时间</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold tabular-nums" style="color: var(--color-text-primary);">{{ $feishuDailyTime }}</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                  style="background: color-mix(in srgb, var(--color-info) 12%, transparent); color: var(--color-info);">
                                {{ $feishuDailyReport ? '已启用' : '已禁用' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-4 py-3 rounded-xl"
                         style="background: var(--color-bg-tertiary);">
                        <span style="color: var(--color-text-secondary);">风险扫描频率</span>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold" style="color: var(--color-text-primary);">每 30 分钟</span>
                            <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                                  style="background: color-mix(in srgb, var(--color-success) 12%, transparent); color: var(--color-success);">
                                运行中
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-4 py-3 rounded-xl"
                         style="background: var(--color-bg-tertiary);">
                        <span style="color: var(--color-text-secondary);">指标采集频率</span>
                        <span class="font-semibold" style="color: var(--color-text-primary);">每小时</span>
                    </div>

                    <div class="flex items-center justify-between px-4 py-3 rounded-xl"
                         style="background: var(--color-bg-tertiary);">
                        <span style="color: var(--color-text-secondary);">Webhook 状态</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded font-medium"
                              style="background: color-mix(in srgb, {{ $hasStoredWebhook && ! $clearWebhook ? 'var(--color-success)' : 'var(--color-warning)' }} 12%, transparent);
                                     color: {{ $hasStoredWebhook && ! $clearWebhook ? 'var(--color-success)' : 'var(--color-warning)' }};">
                            {{ $hasStoredWebhook && ! $clearWebhook ? '已配置' : '未配置' }}
                        </span>
                    </div>
                </div>

                @if($hasStoredWebhook && ! $clearWebhook)
                <div class="mt-4 px-4 py-3 rounded-xl text-xs leading-relaxed"
                     style="background: color-mix(in srgb, var(--color-info) 6%, transparent); color: var(--color-text-secondary); border: 1px solid color-mix(in srgb, var(--color-info) 12%, transparent);">
                    <p class="font-medium mb-1" style="color: var(--color-text-primary);">💡 部署提示</p>
                    <p>确保在服务器 crontab 中添加以下命令以激活定时调度：</p>
                    <code class="block mt-1.5 px-3 py-2 rounded-lg text-[11px] font-mono"
                          style="background: var(--color-bg-primary); color: var(--color-text-primary);">
* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1
                    </code>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
