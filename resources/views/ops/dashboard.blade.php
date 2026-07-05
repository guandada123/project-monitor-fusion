<!DOCTYPE html>
<html lang="zh-CN" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Ops Center — 全项目统一监控看板">
    <title>Ops Center · 项目监控</title>
    <link rel="stylesheet" href="/static/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='0.9em' font-size='90'>🔭</text></svg>">
</head>
<body>

    <!-- ═══ Header ═══ -->
    <header class="header">
        <div class="header-left">
            <div class="header-logo">O</div>
            <div>
                <div class="header-title">Ops Center</div>
                <div class="header-subtitle">全项目统一监控</div>
            </div>
        </div>
        <div class="header-right">
            <div class="refresh-indicator">
                <span class="refresh-dot"></span>
                <span id="refreshTime">--:--:--</span>
                <span style="color:var(--text-tertiary);opacity:0.5">|</span>
                <span id="refreshDate">----/--/--</span>
            </div>
            <button class="header-btn" id="refreshBtn" title="手动刷新">↻</button>
            <button class="theme-toggle" id="themeToggle" title="切换主题">🌙</button>
        </div>
    </header>

    <!-- ═══ Health Marquee Banner ═══ -->
    <div id="healthMarquee" class="marquee-banner" style="display:none">
        <div class="marquee-icon">⚠️</div>
        <div class="marquee-track">
            <div class="marquee-content" id="marqueeContent"></div>
        </div>
        <button class="marquee-close" id="marqueeClose" title="关闭">✕</button>
    </div>

    <!-- ═══ Main Content ═══ -->
    <main class="container">

        <!-- ─── Project Status ─── -->
        <section class="section">
            <div class="section-header">
                <h2>🖥️ 项目状态</h2>
                <span class="section-badge">实时</span>
                <span style="font-size:12px;color:var(--text-tertiary);margin-left:8px">
                    每 30s 自动刷新
                </span>
            </div>
            <div class="project-grid" id="projectGrid">
                <div class="skeleton skeleton-card" style="height:180px"></div>
                <div class="skeleton skeleton-card" style="height:180px"></div>
                <div class="skeleton skeleton-card" style="height:180px"></div>
                <div class="skeleton skeleton-card" style="height:180px"></div>
            </div>
        </section>

        <!-- ─── Cost + Automations ─── -->
        <section class="section">
            <div class="grid-2">
                <!-- Cost -->
                <div class="data-card">
                    <div class="data-card-header">
                        <span class="data-card-title">💰 AI 成本概览</span>
                        <span class="data-card-action">Q3 目标 ¥30/月</span>
                    </div>
                    <div id="costContent">
                        <div class="skeleton" style="height:140px"></div>
                    </div>
                </div>

                <!-- Automations -->
                <div class="data-card">
                    <div class="data-card-header">
                        <span class="data-card-title">⚡ 自动化状态</span>
                        <span class="data-card-action">共 <span id="autoTotal">—</span> 项</span>
                    </div>
                    <div id="automationContent">
                        <div class="skeleton" style="height:140px"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ─── Roadmap ─── -->
        <section class="section">
            <div class="data-card">
                <div class="data-card-header">
                    <span class="data-card-title">🗺️ Q3 2026 Roadmap 进度</span>
                    <span class="data-card-action">周期 2026-07 ~ 2026-09</span>
                </div>
                <div id="roadmapContent">
                    <div class="skeleton" style="height:200px"></div>
                </div>
            </div>
        </section>

    </main>

    <!-- ═══ Footer ═══ -->
    <footer class="footer">
        <p>Ops Center v1.0 · 数据源: QTS / Claw / StockInsight / Marvis Bridge · WorkBuddy 生态</p>
        <p class="footer-timestamp">最近更新: <span id="lastUpdateTime">--:--:--</span></p>
    </footer>

    <script src="/static/app.js"></script>
</body>
</html>
