<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{
    theme: localStorage.getItem('pm_theme') || 'system',
    init() { this.applyTheme(); this.$watch('theme', () => { localStorage.setItem('pm_theme', this.theme); this.applyTheme(); }); window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if (this.theme === 'system') this.applyTheme(); }); },
    applyTheme() { const isDark = this.theme === 'dark' || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches); document.documentElement.classList.toggle('dark', isDark); }
}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>419 会话过期 — Project Monitor</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        @import url('https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap');
        :root{--color-bg-primary:#f8fafc;--color-bg-secondary:#fff;--color-bg-tertiary:#f1f5f9;--color-surface:#fff;--color-text-primary:#0f172a;--color-text-secondary:#475569;--color-text-tertiary:#94a3b8;--color-accent:#3b82f6;--color-accent-hover:#2563eb;--color-info:#6366f1;--glass-bg:rgba(255,255,255,.7);--glass-border:rgba(255,255,255,.2);--glass-shadow:0 8px 32px rgba(0,0,0,.04)}
        .dark{--color-bg-primary:#0f172a;--color-bg-secondary:#1e293b;--color-bg-tertiary:#1e293b;--color-surface:#1e293b;--color-text-primary:#f1f5f9;--color-text-secondary:#94a3b8;--color-text-tertiary:#64748b;--color-accent:#60a5fa;--color-accent-hover:#3b82f6;--glass-bg:rgba(30,41,59,.7);--glass-border:rgba(51,65,85,.2);--glass-shadow:0 8px 32px rgba(0,0,0,.2)}
        body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--color-bg-primary);color:var(--color-text-primary);padding:2rem}
        .error-card{max-width:480px;width:100%;padding:3rem;border-radius:1.5rem;text-align:center;background:var(--glass-bg);backdrop-filter:blur(30px) saturate(200%);-webkit-backdrop-filter:blur(30px) saturate(200%);border:1px solid var(--glass-border);box-shadow:var(--glass-shadow);animation:fadeIn .6s cubic-bezier(.16,1,.3,1) forwards}
        .error-code{font-size:6rem;font-weight:800;line-height:1;background:linear-gradient(135deg,var(--color-info),#8b5cf6);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:.5rem}
        .error-icon{width:64px;height:64px;margin:0 auto 1.5rem;color:var(--color-info);opacity:.8}
        .error-title{font-size:1.5rem;font-weight:700;margin-bottom:.75rem}
        .error-message{font-size:.95rem;color:var(--color-text-secondary);line-height:1.6;margin-bottom:2rem}
        .error-actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:.75rem;font-size:.9rem;font-weight:600;text-decoration:none;transition:all .3s cubic-bezier(.16,1,.3,1);cursor:pointer;border:none}
        .btn-primary{background:var(--color-accent);color:#fff}.btn-primary:hover{background:var(--color-accent-hover);transform:translateY(-1px);box-shadow:0 4px 20px rgba(59,130,246,.3)}
        .btn-secondary{background:var(--color-bg-tertiary);color:var(--color-text-secondary)}.btn-secondary:hover{background:var(--color-bg-secondary);transform:translateY(-1px)}
        .theme-toggle{position:fixed;top:1.5rem;right:1.5rem;display:flex;gap:.25rem;padding:.25rem;border-radius:.75rem;background:var(--glass-bg);backdrop-filter:blur(20px);border:1px solid var(--glass-border)}
        .theme-toggle button{padding:.5rem;border-radius:.5rem;border:none;cursor:pointer;background:transparent;color:var(--color-text-tertiary);transition:all .2s;line-height:0}
        .theme-toggle button.active{background:var(--color-surface);box-shadow:0 1px 3px rgba(0,0,0,.1)}
        .theme-toggle button svg{width:16px;height:16px}.theme-toggle button.active svg{color:var(--color-accent)}
        @keyframes fadeIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @media(max-width:640px){.error-card{padding:2rem}.error-code{font-size:4rem}}
    </style>
</head>
<body>
    <div class="theme-toggle" x-cloak>
        <button @click="theme='light'" :class="theme==='light'?'active':''" title="浅色"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg></button>
        <button @click="theme='dark'" :class="theme==='dark'?'active':''" title="深色"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></button>
        <button @click="theme='system'" :class="theme==='system'?'active':''" title="跟随系统"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></button>
    </div>
    <div class="error-card">
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
        <div class="error-code">419</div>
        <h1 class="error-title">会话已过期</h1>
        <p class="error-message">您的登录会话已过期，请重新登录后继续操作。</p>
        <div class="error-actions">
            <a href="{{ route('login') }}" class="btn btn-primary"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>重新登录</a>
            <a href="/" class="btn btn-secondary">回到首页</a>
        </div>
    </div>
    <script defer src="//unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
</html>
