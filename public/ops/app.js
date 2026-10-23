/* ── Ops Center — Dashboard Logic ── */

const API_BASE = '';
let refreshInterval = null;
const FETCH_TIMEOUT_MS = 10000; // 10s skeleton timeout protection

// ── Skeleton Templates ──
const SKELETON = {
    projectGrid: Array(4)
        .fill('<div class="skeleton skeleton-card" style="height:180px"></div>')
        .join(''),
    costContent: '<div class="skeleton" style="height:140px"></div>',
    automationContent: '<div class="skeleton" style="height:140px"></div>',
    roadmapContent: '<div class="skeleton" style="height:200px"></div>',
};

// ── Theme ──
function initTheme() {
    const saved = localStorage.getItem('ops-theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('ops-theme', next);
    updateThemeIcon(next);
}

function updateThemeIcon(theme) {
    const btn = document.getElementById('themeToggle');
    // Light mode → show moon (click to switch to dark)
    // Dark mode  → show sun  (click to switch to light)
    if (btn) btn.textContent = theme === 'dark' ? '☀️' : '🌙';
}

// ── Formatting Helpers ──
function fmtTime(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function fmtDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function statusText(s) {
    const map = { healthy: '正常', degraded: '降级', unhealthy: '异常' };
    return map[s] || s;
}

function errorHtml(msg) {
    return `<div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">${msg}</div></div>`;
}

// ── Skeleton → Content ──
function setLoading(containerId) {
    const el = document.getElementById(containerId);
    if (el && SKELETON[containerId]) {
        el.innerHTML = SKELETON[containerId];
    }
}

function setLoaded(containerId, html) {
    const el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML = html;
}

// ── Refresh Button Animation ──
function refreshBtnSpin(spinning) {
    const btn = document.getElementById('refreshBtn');
    if (btn) {
        if (spinning) {
            btn.classList.add('refreshing');
        } else {
            btn.classList.remove('refreshing');
        }
    }
}

// ── Render Overview ──
function renderOverview(data) {
    const projects = data.projects;
    const grid = document.getElementById('projectGrid');
    const emojiMap = { qts: '📈', claw: '🦞', stockinsight: '🔍', marvis: '🌉' };
    const iconClassMap = { qts: 'qts', claw: 'claw', stockinsight: 'stockinsight', marvis: 'marvis' };

    let html = '';
    for (const [key, proj] of Object.entries(projects)) {
        let details = '';
        let servicesHtml = '';

        if (proj.services) {
            servicesHtml = '<div class="service-list">';
            proj.services.forEach(s => {
                const dotClass = s.status === 'healthy' ? 'healthy' : 'unhealthy';
                servicesHtml += `
                    <div class="service-item">
                        <span class="service-name">${s.name}:${s.port}</span>
                        <span class="service-status">
                            <span class="service-status-dot ${dotClass}"></span>
                            ${statusText(s.status)}
                        </span>
                    </div>`;
            });
            servicesHtml += '</div>';
        }

        if (proj.watcher_running !== undefined) {
            details = `
                <div class="project-detail-item">
                    <span class="project-detail-label">文件监听</span>
                    <span class="project-detail-value">${proj.watcher_running ? '✅ 运行中' : '❌ 已停止'}</span>
                </div>
                <div class="project-detail-item">
                    <span class="project-detail-label">消费者</span>
                    <span class="project-detail-value">${proj.consumer_running ? '✅ 运行中' : '❌ 已停止'}</span>
                </div>`;
        } else if (proj.scripts !== undefined) {
            details = `
                <div class="project-detail-item">
                    <span class="project-detail-label">脚本数</span>
                    <span class="project-detail-value">${proj.scripts}</span>
                </div>`;
        } else if (proj.services_total !== undefined) {
            details = `
                <div class="project-detail-item">
                    <span class="project-detail-label">服务健康</span>
                    <span class="project-detail-value">${proj.services_healthy}/${proj.services_total}</span>
                </div>`;
        }

        const delay = Object.keys(projects).indexOf(key) * 0.08;
        html += `
            <div class="project-card status-${proj.status}" style="animation-delay: ${delay}s">
                <div class="project-card-top">
                    <div class="project-icon ${iconClassMap[key]}">${emojiMap[key]}</div>
                    <span class="project-status-badge ${proj.status}">● ${statusText(proj.status)}</span>
                </div>
                <div class="project-name">${proj.name}</div>
                ${proj.size ? `<div class="project-detail" style="margin-bottom:8px"><span class="project-detail-label">体积</span> <strong>${proj.size}</strong></div>` : ''}
                <div class="project-detail">${details}</div>
                ${servicesHtml}
            </div>`;
    }
    grid.innerHTML = html;
}

// ── Render Cost ──
function renderCost(data) {
    const container = document.getElementById('costContent');
    const total = data.monthly_estimate || 0;
    const target = data.target || 30;
    const progress = total <= target
        ? Math.min(Math.round(100 - total / target * 100), 100)
        : 20;

    // SVG ring
    const r = 48;
    const circumference = 2 * Math.PI * r;
    const offset = circumference - (progress / 100) * circumference;

    const details = data.details;
    const records = details ? (details.total_records || 0).toLocaleString() : '—';
    const tokens = details ? (details.total_tokens || 0).toLocaleString() : '—';

    container.innerHTML = `
        <div class="cost-ring-container">
            <div class="cost-ring">
                <svg width="120" height="120" viewBox="0 0 120 120">
                    <circle class="cost-ring-bg" cx="60" cy="60" r="${r}"/>
                    <circle class="cost-ring-fill" cx="60" cy="60" r="${r}"
                        stroke-dasharray="${circumference}"
                        stroke-dashoffset="${offset}"
                        style="stroke: ${progress > 80 ? 'var(--accent-red)' : progress > 50 ? 'var(--accent-yellow)' : 'var(--accent-green)'}"/>
                </svg>
                <div class="cost-ring-center">
                    <div class="cost-ring-value">¥${total}</div>
                    <div class="cost-ring-label">月估算</div>
                </div>
            </div>
            <div class="cost-stats">
                <div class="cost-stat">
                    <span class="cost-stat-dot" style="background:var(--accent-blue)"></span>
                    <span class="cost-stat-label">记录数</span>
                    <span class="cost-stat-value">${records}</span>
                </div>
                <div class="cost-stat">
                    <span class="cost-stat-dot" style="background:var(--accent-purple)"></span>
                    <span class="cost-stat-label">总 Tokens</span>
                    <span class="cost-stat-value">${tokens}</span>
                </div>
            </div>
        </div>
        <div class="progress-item" style="margin-top: 12px;">
            <div class="progress-header">
                <span class="progress-name">成本目标进度</span>
                <span class="progress-stats">¥${total} → ¥${target}</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: ${progress}%"></div>
            </div>
        </div>`;
}

// ── Render Automations ──
function renderAutomations(data) {
    const container = document.getElementById('automationContent');

    const total = data.count || 0;
    const active = data.active || 0;
    const paused = data.paused || 0;

    // Update badge count
    const badge = document.getElementById('autoTotal');
    if (badge) badge.textContent = total;

    let listHtml = '';
    if (data.recent && data.recent.length > 0) {
        data.recent.forEach(a => {
            const name = a.name || a.id;
            const status = a.status || 'ACTIVE';
            const lastRun = a.last_run ? fmtDate(a.last_run) : '—';
            listHtml += `
                <div class="automation-row">
                    <span class="automation-name" title="${name}">${name}</span>
                    <span class="automation-status ${status}">${status}</span>
                    <span class="automation-last-run">${lastRun}</span>
                </div>`;
        });
    } else {
        listHtml = `<div class="empty-state"><div class="empty-state-icon">📭</div><div class="empty-state-text">暂无数据</div></div>`;
    }

    container.innerHTML = `
        <div class="metric-grid" style="margin-bottom: 16px;">
            <div class="metric-item">
                <div class="metric-value green">${total}</div>
                <div class="metric-label">总计</div>
            </div>
            <div class="metric-item">
                <div class="metric-value blue">${active}</div>
                <div class="metric-label">活跃</div>
            </div>
            <div class="metric-item">
                <div class="metric-value ${paused > 0 ? 'red' : 'green'}">${paused}</div>
                <div class="metric-label">暂停</div>
            </div>
        </div>
        <div class="automation-list">
            ${listHtml}
        </div>`;
}

// ── Render Roadmap ──
function renderRoadmap(data) {
    const container = document.getElementById('roadmapContent');
    if (!data.goals || data.goals.length === 0) {
        container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">🗺️</div><div class="empty-state-text">暂无 Roadmap 数据</div></div>`;
        return;
    }

    let html = '';
    data.goals.forEach(g => {
        const isInverted = g.name.includes('体积');
        // progress === null 表示"数值不可得（未评判）"——不许当 0 或 100 画
        const unknown = g.progress === null || g.progress === undefined;
        const pct = unknown ? 0 : g.progress;
        let fillClass = '';
        if (isInverted && !unknown) {
            if (pct >= 60) fillClass = ' progress-fill-ok';
            else if (pct >= 40) fillClass = ' progress-fill-warn';
            else fillClass = ' progress-fill-bad';
        }
        html += `
            <div class="progress-item">
                <div class="progress-header">
                    <span class="progress-name">${g.name}</span>
                    <span class="progress-stats">${g.current} → ${g.target}${unknown ? '（数值不可得，未评判）' : ''}</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill${fillClass}" style="width: ${pct}%"></div>
                </div>
            </div>`;
    });
    container.innerHTML = html;
}

// ── Fetch Individual Modules Independently ──
async function fetchModule(url, renderFn, containerId, errorLabel) {
    try {
        const res = await fetch(url, { signal: AbortSignal.timeout(FETCH_TIMEOUT_MS) });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        renderFn(data);
    } catch (err) {
        console.error(`${errorLabel} fetch error:`, err);
        const reason = err.name === 'TimeoutError' ? `${errorLabel} 请求超时` : `${errorLabel} 加载失败`;
        setLoaded(containerId, errorHtml(reason));
    }
}

// ── Fetch All Data ──
async function fetchAll() {
    refreshBtnSpin(true);

    // Show skeletons during loading
    setLoading('projectGrid');
    setLoading('costContent');
    setLoading('automationContent');
    setLoading('roadmapContent');

    // Fetch each module independently — one failure doesn't crash others
    await Promise.all([
        fetchModule(`${API_BASE}/api/ops/overview`, (data) => {
            renderOverview(data);
            document.getElementById('refreshTime').textContent = fmtTime(data.timestamp);
            document.getElementById('refreshDate').textContent = data.date || '----/--/--';
        }, 'projectGrid', '项目概览'),

        fetchModule(`${API_BASE}/api/ops/cost`, renderCost, 'costContent', '成本数据'),

        fetchModule(`${API_BASE}/api/ops/automations`, renderAutomations, 'automationContent', '自动化数据'),

        fetchModule(`${API_BASE}/api/ops/roadmap`, renderRoadmap, 'roadmapContent', 'Roadmap 数据'),
    ]);

    // Update footer timestamp
    document.getElementById('lastUpdateTime').textContent = fmtTime(new Date().toISOString());

    refreshBtnSpin(false);
}

// ── Init ──
function init() {
    initTheme();
    document.getElementById('themeToggle').addEventListener('click', toggleTheme);

    // Manual refresh button
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) refreshBtn.addEventListener('click', fetchAll);

    // Initial load
    fetchAll();

    // Auto-refresh every 30s
    refreshInterval = setInterval(fetchAll, 30000);
}

document.addEventListener('DOMContentLoaded', init);
