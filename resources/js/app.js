import './bootstrap';
import Sortable from 'sortablejs';

// Make libraries globally available
window.Sortable = Sortable;

// ── ApexCharts — 按需加载（2026-10-16 审计修复 P1）─────────────────────
// 此前 `import ApexCharts from 'apexcharts'` 是**静态导入**，于是 Vite 把
// ApexCharts 作为 app.js entry 的依赖打成 vendor-apex-*.js（实测 602KB），
// 每个页面首屏都会同步拉取 —— 包括登录、设置这些根本没有图表的页面。
//
// 现在只在「页面上确实存在图表容器」时才 import()，并把同一个 Promise 通过
// window.pmLoadApexCharts() 暴露给 Livewire 视图里的内联脚本 await，
// 重复调用不会重复下载（模块级单例 Promise）。
// 兼容性：视图侧必须保证 ApexCharts 就绪后再 new，不能再直接读 window.ApexCharts。
const CHART_CONTAINER_IDS = [
    'trend-chart',
    'kpi-trend-chart',
    'gantt-chart',
    'task-distribution-chart',
    'slow-query-trend-chart',
];

function pageHasChartContainer() {
    return CHART_CONTAINER_IDS.some((id) => document.getElementById(id) !== null);
}

let apexChartsPromise = null;

window.pmLoadApexCharts = function pmLoadApexCharts() {
    if (!apexChartsPromise) {
        apexChartsPromise = import('apexcharts').then((mod) => {
            window.ApexCharts = mod.default;
            // 供不便于 await 的调用方监听（当前视图统一用 pmLoadApexCharts().then）
            window.dispatchEvent(new CustomEvent('pm:charts-ready'));
            return window.ApexCharts;
        });
    }
    return apexChartsPromise;
};

// 首屏：仅当存在图表容器时才触发下载（模块是 defer 的，此时 DOM 已解析完）
if (pageHasChartContainer()) {
    window.pmLoadApexCharts().catch(() => {
        // 图表是非关键增强，加载失败不阻断页面
    });
}

// ── SPA Navigation Progress Bar ──
// NProgress-style lightweight progress indicator
let nprogressTimer = null;
const NPROGRESS = {
    bar: null,
    min: 0.08,
    max: 0.94,
    speed: 200,
    easing: 'cubic-bezier(0.16, 1, 0.3, 1)',

    init() {
        this.bar = document.getElementById('nprogress');
        if (!this.bar) return;
    },

    start() {
        if (!this.bar) this.init();
        if (!this.bar) return;
        // Reset and begin animation
        this.bar.style.transform = 'scaleX(0)';
        this.bar.style.display = 'block';
        this.bar.style.transition = 'none';
        // Force reflow
        this.bar.offsetHeight;
        this.bar.style.transition = `transform ${this.speed}ms ${this.easing}`;
        this.bar.style.transform = `scaleX(${this.min})`;
        // Trickle up gradually
        if (nprogressTimer) clearInterval(nprogressTimer);
        nprogressTimer = setInterval(() => {
            if (!this.bar) return;
            const current = parseFloat(this.bar.style.transform.replace('scaleX(', '').replace(')', '') || '0');
            const increment = (this.max - current) * 0.08 + 0.01;
            const next = Math.min(current + increment, this.max);
            this.bar.style.transform = `scaleX(${next})`;
        }, 400);
    },

    done() {
        if (nprogressTimer) {
            clearInterval(nprogressTimer);
            nprogressTimer = null;
        }
        if (!this.bar) return;
        // Snap to full, then fade out
        this.bar.style.transition = `transform 150ms ${this.easing}`;
        this.bar.style.transform = 'scaleX(1)';
        setTimeout(() => {
            if (!this.bar) return;
            this.bar.style.transition = 'opacity 300ms ease';
            this.bar.style.opacity = '0';
            setTimeout(() => {
                if (!this.bar) return;
                this.bar.style.display = 'none';
                this.bar.style.opacity = '1';
                this.bar.style.transform = 'scaleX(0)';
            }, 300);
        }, 200);
    },
};

document.addEventListener('livewire:navigating', () => {
    NPROGRESS.start();
});

document.addEventListener('livewire:navigated', () => {
    NPROGRESS.done();

    // Update document title from the new page's <title>
    const titleEl = document.querySelector('title');
    if (titleEl) {
        document.title = titleEl.textContent || 'Project Monitor — PM';
    }

    // SPA 导航到含图表的页面时补触发 ApexCharts 下载（无图表页面永不下载）
    if (pageHasChartContainer()) {
        window.pmLoadApexCharts().catch(() => {});
    }
});

// ── Three.js Immersive Background ──
// Dynamic import to avoid impacting initial payload
// Loads after first meaningful paint
if (typeof window !== 'undefined' && window.requestIdleCallback) {
    window.requestIdleCallback(() => {
        import('./three-background.js').catch(() => {
            // Silently fail — Three.js background is non-critical enhancement
        });
    }, { timeout: 3000 });
} else {
    // Fallback: defer to after load
    window.addEventListener('load', () => {
        import('./three-background.js').catch(() => {});
    });
}

// Smooth theme transition
// 2026-10-16 审计修复（P1）：原先此处裸建 MutationObserver 且从不 disconnect。
// 本文件是 Vite entry、模块体只会求值一次，因此现实中没有叠加；但仍加显式
// 幂等守卫，避免打包产物被重复求值（二次 import / 预渲染双执行）时叠加监听。
let themeTransitionObserver = null;

function installThemeTransitionObserver() {
    if (themeTransitionObserver) return;

    themeTransitionObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class') {
                document.documentElement.style.transition = 'background-color 0.3s ease, color 0.3s ease';
            }
        });
    });
    themeTransitionObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
}

installThemeTransitionObserver();
