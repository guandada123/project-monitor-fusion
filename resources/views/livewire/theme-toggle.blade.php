<div
    x-data="{
        theme: 'system',
        init() {
            const saved = localStorage.getItem('pm-theme') || 'system';
            this.theme = saved;
            this.apply(saved);
        },
        apply(t) {
            const isDark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
            localStorage.setItem('pm-theme', t);
            this.theme = t;
        },
        label(t) {
            return {light:'☀️', dark:'🌙', system:'🖥️'}[t] || t;
        },
        next() {
            const cycle = {light:'dark', dark:'system', system:'light'};
            this.apply(cycle[this.theme]);
        }
    }"
    x-init="init()"
    class="theme-toggle"
>
    <button
        x-on:click="next()"
        class="theme-btn"
        :aria-label="'当前主题: ' + theme"
        title="切换主题"
    >
        <span x-text="label(theme)"></span>
    </button>
    <style>
        .theme-toggle { display: inline-flex; }
        .theme-btn {
            background: var(--card);
            border: 1px solid var(--stroke);
            border-radius: 12px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            color: var(--text);
        }
        .theme-btn:hover {
            transform: scale(1.06);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
    </style>
</div>
