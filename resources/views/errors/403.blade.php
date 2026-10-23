<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 禁止访问 — Project Monitor</title>
    <style>
        @import url('https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --color-bg-primary: #f8fafc;
            --color-bg-secondary: #ffffff;
            --color-bg-tertiary: #f1f5f9;
            --color-surface: #ffffff;
            --color-text-primary: #0f172a;
            --color-text-secondary: #475569;
            --color-text-tertiary: #94a3b8;
            --color-accent: #3b82f6;
            --color-accent-hover: #2563eb;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.2);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.04);
        }

        .dark {
            --color-bg-primary: #0f172a;
            --color-bg-secondary: #1e293b;
            --color-bg-tertiary: #1e293b;
            --color-surface: #1e293b;
            --color-text-primary: #f1f5f9;
            --color-text-secondary: #94a3b8;
            --color-text-tertiary: #64748b;
            --color-accent: #60a5fa;
            --color-accent-hover: #3b82f6;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(51, 65, 85, 0.2);
            --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-bg-primary);
            color: var(--color-text-primary);
            padding: 2rem;
        }

        .error-card {
            max-width: 480px;
            width: 100%;
            padding: 3rem;
            border-radius: 1.5rem;
            text-align: center;
            background: var(--glass-bg);
            backdrop-filter: blur(30px) saturate(200%);
            -webkit-backdrop-filter: blur(30px) saturate(200%);
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .error-code {
            font-size: 6rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, var(--color-danger), #f97316);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .error-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1.5rem;
            color: var(--color-danger);
            opacity: 0.8;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .error-message {
            font-size: 0.95rem;
            color: var(--color-text-secondary);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .error-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--color-accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--color-accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: var(--color-bg-tertiary);
            color: var(--color-text-secondary);
        }

        .btn-secondary:hover {
            background: var(--color-bg-secondary);
            transform: translateY(-1px);
        }

        .theme-toggle {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            display: flex;
            gap: 0.25rem;
            padding: 0.25rem;
            border-radius: 0.75rem;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
        }

        .theme-toggle button {
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            background: transparent;
            color: var(--color-text-tertiary);
            transition: all 0.2s;
            line-height: 0;
        }

        .theme-toggle button.active {
            background: var(--color-surface);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .theme-toggle button svg {
            width: 16px;
            height: 16px;
        }

        .theme-toggle button.active svg {
            color: var(--color-accent);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 640px) {
            .error-card { padding: 2rem; }
            .error-code { font-size: 4rem; }
        }
    </style>
</head>
<body>
    <!-- Theme Toggle -->
    <div class="theme-toggle">
        <button type="button" data-theme-btn="light" title="浅色">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>
        <button type="button" data-theme-btn="dark" title="深色">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>
        <button type="button" data-theme-btn="system" title="跟随系统">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </button>
    </div>

    <!-- Error Card -->
    <div class="error-card">
        <!-- Lock icon -->
        <svg class="error-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>

        <div class="error-code">403</div>
        <h1 class="error-title">禁止访问</h1>
        <p class="error-message">
            抱歉，您没有权限访问此页面。<br>
            请确认您已登录并且拥有所需权限。
            @if($exception?->getMessage() && !str_contains($exception->getMessage(), '403'))
                <br><span style="font-size:0.85rem;color:var(--color-text-tertiary);">{{ $exception->getMessage() }}</span>
            @endif
        </p>

        <div class="error-actions">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                返回上页
            </a>
            <a href="/" class="btn btn-secondary">
                回到首页
            </a>
        </div>
    </div>

    {{-- 2026-10-16 审计修复（P1）：原先此处从 //unpkg.com/alpinejs@3.x.x 加载未固定版本
         的 Alpine —— 既引入第三方 CDN 依赖（版本漂移 + 供应链风险），又被本页 CSP
         （script-src 'self' 'unsafe-inline'）拦截，主题切换实际从未生效。改用不依赖
         任何框架的内联普通脚本，兼容原 localStorage key（pm_theme）。 --}}
    <script>
    (function () {
        'use strict';
        var KEY = 'pm_theme';
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        var root = document.documentElement;
        var buttons = document.querySelectorAll('[data-theme-btn]');

        function isDark(mode) {
            return mode === 'dark' || (mode === 'system' && media.matches);
        }

        function apply(mode) {
            root.classList.toggle('dark', isDark(mode));
            buttons.forEach(function (btn) {
                btn.classList.toggle('active', btn.dataset.themeBtn === mode);
            });
        }

        var mode = localStorage.getItem(KEY) || 'system';
        apply(mode);

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                mode = btn.dataset.themeBtn;
                localStorage.setItem(KEY, mode);
                apply(mode);
            });
        });

        media.addEventListener('change', function () {
            if (mode === 'system') apply(mode);
        });
    })();
    </script>
</body>
</html>
