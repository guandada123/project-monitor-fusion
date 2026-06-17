<script>
/**
 * Project Monitor — ApexCharts Theme Manager
 * Watches html.dark class changes and updates all registered chart instances.
 */
;(function() {
  // ── Theme palettes ──────────────────────────────────────────────────
  const LIGHT = {
    foreColor: '#475569',
    grid: '#e2e8f0',
    axisBorder: '#e2e8f0',
    tooltip: 'light',
    tooltipBg: '#ffffff',
    tooltipFg: '#0f172a',
    legend: '#475569',
    title: '#0f172a',
    accent: '#4f46e5',
    accent2: '#22c55e',
    accent3: '#f59e0b',
    accent4: '#ef4444',
    accent5: '#8b5cf6',
  };

  const DARK = {
    foreColor: '#94a3b8',
    grid: 'rgba(255,255,255,0.06)',
    axisBorder: 'rgba(255,255,255,0.08)',
    tooltip: 'dark',
    tooltipBg: '#1e293b',
    tooltipFg: '#f1f5f9',
    legend: '#94a3b8',
    title: '#f1f5f9',
    accent: '#818cf8',
    accent2: '#34d399',
    accent3: '#fbbf24',
    accent4: '#f87171',
    accent5: '#a78bfa',
  };

  // ── Chart registry ──────────────────────────────────────────────────
  window.__pmCharts = [];

  /**
   * Create a theme-aware ApexCharts instance.
   * Usage: window.pmChart(el, { ...baseOptions }, { ...themeOverrides })
   * The returned chart is auto-registered for theme updates.
   */
  window.pmChart = function(el, baseOptions, themeOverrides) {
    const theme = document.documentElement.classList.contains('dark') ? DARK : LIGHT;
    const overrides = themeOverrides || {};

    const themed = {
      ...baseOptions,
      chart: {
        ...(baseOptions.chart || {}),
        foreColor: theme.foreColor,
        toolbar: { show: false },
        animations: { enabled: true, easing: 'easeOutExpo', speed: 600 },
      },
      grid: {
        borderColor: theme.grid,
        strokeDashArray: 4,
        ...(baseOptions.grid || {}),
      },
      xaxis: {
        axisBorder: { color: theme.axisBorder },
        labels: { style: { colors: theme.foreColor, fontSize: '11px' } },
        ...(baseOptions.xaxis || {}),
      },
      yaxis: (baseOptions.yaxis || [{}]).map(function(a) {
        return {
          labels: { style: { colors: theme.foreColor, fontSize: '11px' } },
          ...a,
        };
      }),
      tooltip: {
        theme: theme.tooltip,
        style: { fontSize: '12px', fontFamily: 'inherit' },
        ...(baseOptions.tooltip || {}),
      },
      legend: {
        labels: { colors: theme.legend },
        fontSize: '12px',
        ...(baseOptions.legend || {}),
      },
      title: {
        style: { color: theme.title },
        ...(baseOptions.title || {}),
      },
      ...overrides,
    };

    const chart = new ApexCharts(el, themed);
    window.__pmCharts.push(chart);
    return chart;
  };

  /**
   * Re-theme all registered charts.
   */
  function applyTheme(theme) {
    window.__pmCharts.forEach(function(chart) {
      try {
        chart.updateOptions({
          chart: { foreColor: theme.foreColor },
          grid: { borderColor: theme.grid },
          xaxis: { labels: { style: { colors: theme.foreColor } }, axisBorder: { color: theme.axisBorder } },
          yaxis: chart.opts.yaxis.map(function() {
            return { labels: { style: { colors: theme.foreColor } } };
          }),
          tooltip: { theme: theme.tooltip },
          legend: { labels: { colors: theme.legend } },
          title: { style: { color: theme.title } },
        }, false, false);
      } catch(e) { /* chart may not be rendered yet */ }
    });
  }

  // ── Observe theme changes ───────────────────────────────────────────
  function isDark() {
    return document.documentElement.classList.contains('dark');
  }

  // Listen for class changes on <html>
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(m) {
      if (m.attributeName === 'class') {
        applyTheme(isDark() ? DARK : LIGHT);
      }
    });
  });
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

  // Also listen for system preference changes (when mode = 'system')
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
    const stored = localStorage.getItem('pm-theme');
    if (stored === 'system') {
      applyTheme(e.matches ? DARK : LIGHT);
    }
  });

  // Expose theme colors for manual use
  window.pmTheme = function() {
    return isDark() ? DARK : LIGHT;
  };
})();
</script>
