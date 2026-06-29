<!DOCTYPE html>
<html lang="zh-CN" x-data="{ theme: localStorage.getItem('theme') || 'system' }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Project Monitor' }}</title>
    {{-- Blocking theme init: runs before any paint, eliminates FOUC --}}
    <script>
        (function() {
            var theme = localStorage.getItem('theme') || 'system';
            var isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) document.documentElement.classList.add('dark');
            window.__applyTheme = function(t) {
                var d = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', d);
            };
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @livewireNavigation
</head>
<body class="font-sans antialiased min-h-screen flex items-center justify-center p-4 transition-colors duration-300"
      style="background: var(--color-bg-primary);">
    <div class="w-full max-w-md">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
