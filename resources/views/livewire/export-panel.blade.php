<div>
    <!-- Floating Export Button -->
    <button
        wire:click="openPanel"
        class="fixed bottom-6 right-6 z-40 w-12 h-12 rounded-xl flex items-center justify-center shadow-lg transition-all duration-300 hover:scale-110 hover:shadow-xl group"
        style="background: linear-gradient(135deg, var(--color-accent), #6366f1);"
        title="导出数据"
    >
        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span class="absolute right-full mr-3 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200"
              style="background: var(--glass-bg); backdrop-filter: blur(20px); color: var(--color-text-primary); border: 1px solid var(--glass-border);">
            导出数据
        </span>
    </button>

    <!-- Export Modal -->
    @if($showPanel)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data x-trap.noreturn="showPanel"
         @keydown.escape.window="$wire.closePanel()">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" wire:click="closePanel"></div>

        <!-- Modal Card -->
        <div class="relative w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden transition-all duration-300"
             style="background: var(--glass-bg); backdrop-filter: blur(40px) saturate(200%); border: 1px solid var(--glass-border);">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--glass-border);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shadow-sm"
                         style="background: linear-gradient(135deg, var(--color-accent), #6366f1);">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold" style="color: var(--color-text-primary);">导出数据</h3>
                        <p class="text-xs" style="color: var(--color-text-tertiary);">选择实体类型和格式</p>
                    </div>
                </div>
                <button wire:click="closePanel" class="p-2 rounded-lg transition-colors duration-200 hover:bg-white/10 dark:hover:bg-slate-800/50" style="color: var(--color-text-tertiary);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            @if($step === 'select')
            <!-- Step: Select Configuration -->
            <div class="p-6 space-y-6">
                <!-- Entity Type -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--color-text-primary);">导出类型</label>
                    <div class="grid grid-cols-3 gap-3">
                        @foreach($entityTypes as $type => $label)
                        <button wire:click="$set('entityType', '{{ $type }}')"
                                class="relative p-3 rounded-xl text-center transition-all duration-200 border-2"
                                style="{{ $entityType === $type ? 'border-color: var(--color-accent); background: rgba(99,102,241,0.08);' : 'border-color: var(--glass-border); background: transparent;' }} color: var(--color-text-primary);">
                            <div class="text-lg mb-1">
                                @if($type === 'project')
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                                @elseif($type === 'task')
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                @elseif($type === 'risk')
                                <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                                @endif
                            </div>
                            <span class="text-sm font-medium">{{ $label }}</span>
                            @if($entityType === $type)
                            <div class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                 style="background: var(--color-accent);">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                <!-- Format -->
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--color-text-primary);">导出格式</label>
                    <div class="grid grid-cols-3 gap-3">
                        <button wire:click="$set('format', 'csv')"
                                class="relative p-3 rounded-xl text-center transition-all duration-200 border-2"
                                style="{{ $format === 'csv' ? 'border-color: var(--color-accent); background: rgba(99,102,241,0.08);' : 'border-color: var(--glass-border); background: transparent;' }} color: var(--color-text-primary);">
                            <span class="text-sm font-medium">CSV</span>
                            <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">通用格式，兼容所有表格软件</p>
                            @if($format === 'csv')
                            <div class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                 style="background: var(--color-accent);">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            @endif
                        </button>
                        <button wire:click="$set('format', 'xlsx')"
                                class="relative p-3 rounded-xl text-center transition-all duration-200 border-2"
                                style="{{ $format === 'xlsx' ? 'border-color: var(--color-accent); background: rgba(99,102,241,0.08);' : 'border-color: var(--glass-border); background: transparent;' }} color: var(--color-text-primary);">
                            <span class="text-sm font-medium">Excel (XLSX)</span>
                            <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">高级格式，支持样式和自动筛选</p>
                            @if($format === 'xlsx')
                            <div class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                 style="background: var(--color-accent);">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            @endif
                        </button>
                        <button wire:click="$set('format', 'pdf')"
                                class="relative p-3 rounded-xl text-center transition-all duration-200 border-2"
                                style="{{ $format === 'pdf' ? 'border-color: var(--color-accent); background: rgba(99,102,241,0.08);' : 'border-color: var(--glass-border); background: transparent;' }} color: var(--color-text-primary);">
                            <span class="text-sm font-medium">PDF</span>
                            <p class="text-xs mt-1" style="color: var(--color-text-tertiary);">格式化报告，适合打印和分享</p>
                            @if($format === 'pdf')
                            <div class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full flex items-center justify-center shadow-sm"
                                 style="background: var(--color-accent);">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            @endif
                        </button>
                    </div>
                </div>

                <!-- Columns -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium" style="color: var(--color-text-primary);">导出列</label>
                        <div class="flex gap-2">
                            <button wire:click="toggleAllColumns(true)" class="text-xs px-2 py-1 rounded-lg transition-colors hover:bg-white/10" style="color: var(--color-accent);">全选</button>
                            <button wire:click="toggleAllColumns(false)" class="text-xs px-2 py-1 rounded-lg transition-colors hover:bg-white/10" style="color: var(--color-text-tertiary);">取消全选</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto p-2 rounded-xl"
                         style="background: var(--color-bg-tertiary);">
                        @forelse($availableColumns as $key => $label)
                        <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer transition-colors hover:bg-white/5 dark:hover:bg-slate-800/30"
                               style="color: var(--color-text-primary);">
                            <input type="checkbox"
                                   wire:model.live="selectedColumns.{{ $key }}"
                                   class="w-4 h-4 rounded transition-colors"
                                   style="accent-color: var(--color-accent);">
                            <span class="text-sm">{{ $label }}</span>
                        </label>
                        @empty
                        <p class="text-sm col-span-2 py-4 text-center" style="color: var(--color-text-tertiary);">暂无可用列</p>
                        @endforelse
                    </div>
                </div>

                <!-- Error Message -->
                @if($errorMessage)
                <div class="p-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); color: rgb(239,68,68); border: 1px solid rgba(239,68,68,0.2);">
                    {{ $errorMessage }}
                </div>
                @endif

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button wire:click="closePanel"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 hover:bg-white/10 dark:hover:bg-slate-800/50"
                            style="color: var(--color-text-secondary);">
                        取消
                    </button>
                    <button wire:click="startExport" wire:loading.attr="disabled"
                            class="px-5 py-2 rounded-xl text-sm font-medium text-white transition-all duration-200 hover:shadow-lg hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed"
                            style="background: linear-gradient(135deg, var(--color-accent), #6366f1);">
                        <span wire:loading.remove wire:target="startExport">开始导出</span>
                        <span wire:loading wire:target="startExport" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            处理中...
                        </span>
                    </button>
                </div>
            </div>

            @elseif($step === 'progress')
            <!-- Step: Progress -->
            <div class="p-6 space-y-4 text-center">
                <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center"
                     style="background: rgba(99,102,241,0.1);">
                    <svg class="w-8 h-8" style="color: var(--color-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-lg" style="color: var(--color-text-primary);">正在导出...</h4>
                <p class="text-sm" style="color: var(--color-text-tertiary);">正在生成 {{ $entityTypes[$entityType] ?? $entityType }} 的 {{ strtoupper($format) }} 文件</p>
                <div class="max-w-xs mx-auto">
                    <div class="h-1.5 rounded-full overflow-hidden" style="background: var(--color-bg-tertiary);">
                        <div class="h-full rounded-full animate-pulse" style="background: linear-gradient(90deg, var(--color-accent), #6366f1); width: 60%;"></div>
                    </div>
                </div>
            </div>

            @elseif($step === 'done')
            <!-- Step: Done -->
            <div class="p-6 space-y-4 text-center">
                <div class="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center"
                     style="background: rgba(34,197,94,0.1);">
                    <svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h4 class="font-semibold text-lg" style="color: var(--color-text-primary);">导出完成</h4>

                @if(!empty($exportProgress))
                <div class="p-4 rounded-xl text-left" style="background: var(--color-bg-tertiary);">
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <span class="text-xs" style="color: var(--color-text-tertiary);">类型</span>
                            <p class="font-medium" style="color: var(--color-text-primary);">{{ $entityTypes[$exportProgress['entity_type'] ?? $entityType] ?? $entityType }}</p>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--color-text-tertiary);">格式</span>
                            <p class="font-medium" style="color: var(--color-text-primary);">{{ strtoupper($exportProgress['format'] ?? $format) }}</p>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--color-text-tertiary);">记录数</span>
                            <p class="font-medium" style="color: var(--color-text-primary);">{{ number_format($exportProgress['record_count'] ?? 0) }} 条</p>
                        </div>
                        <div>
                            <span class="text-xs" style="color: var(--color-text-tertiary);">文件大小</span>
                            <p class="font-medium" style="color: var(--color-text-primary);">{{ $exportProgress['file_size'] ?? '-' }}</p>
                        </div>
                    </div>
                </div>
                @endif

                <div class="flex items-center justify-center gap-3 pt-2">
                    <button wire:click="resetExport"
                            class="px-4 py-2 rounded-xl text-sm font-medium transition-all duration-200 hover:bg-white/10 dark:hover:bg-slate-800/50"
                            style="color: var(--color-text-secondary);">
                        再次导出
                    </button>
                    <button wire:click="closePanel"
                            class="px-5 py-2 rounded-xl text-sm font-medium text-white transition-all duration-200 hover:shadow-lg hover:scale-105"
                            style="background: linear-gradient(135deg, var(--color-accent), #6366f1);">
                        完成
                    </button>
                </div>
            </div>
            @endif

            <!-- Export History Footer -->
            @if(!empty($history) && $step !== 'progress')
            <div class="px-6 py-3 border-t" style="border-color: var(--glass-border); background: var(--color-bg-tertiary);">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-3.5 h-3.5" style="color: var(--color-text-tertiary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-medium" style="color: var(--color-text-tertiary);">最近导出</span>
                </div>
                <div class="flex gap-2 overflow-x-auto pb-1">
                    @foreach($history as $entry)
                    <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs whitespace-nowrap transition-colors hover:bg-white/5 dark:hover:bg-slate-800/30"
                         style="background: var(--glass-bg); border: 1px solid var(--glass-border); color: var(--color-text-secondary);">
                        <span class="font-medium">{{ $entry['entity_label'] ?? $entry['entity_type'] }}</span>
                        <span class="opacity-60">{{ strtoupper($entry['format']) }}</span>
                        <span class="opacity-60">·</span>
                        <span class="opacity-60">{{ $entry['record_count'] }} 条</span>
                        <span class="opacity-40">{{ \Illuminate\Support\Carbon::parse($entry['created_at'])->diffForHumans() }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
