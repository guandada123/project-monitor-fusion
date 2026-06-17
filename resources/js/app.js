import './bootstrap';
import ApexCharts from 'apexcharts';
import Sortable from 'sortablejs';

// Make libraries globally available
window.ApexCharts = ApexCharts;
window.Sortable = Sortable;

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
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.attributeName === 'class') {
            document.documentElement.style.transition = 'background-color 0.3s ease, color 0.3s ease';
        }
    });
});
observer.observe(document.documentElement, { attributes: true });
