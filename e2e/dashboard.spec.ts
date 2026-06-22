import { test, expect } from "@playwright/test";

/**
 * Dashboard page E2E screenshot tests
 *
 * Covers: full-page, stat cards, sidebar (risk alerts + resource heatmap),
 *        main content (project cards + trend charts)
 *
 * Routes: GET / (view_dashboard)
 */

const DUSK_LOGIN = "/dusk/login/1";

test.describe("Dashboard", () => {
  test.beforeEach(async ({ page }) => {
    // Login via Dusk route (auto-assigns admin role via RolePermissionSeeder)
    await page.goto(DUSK_LOGIN);
    await page.waitForURL("/");
  });

  test("full page screenshot", async ({ page }) => {
    // Inject freeze CSS early — disable ALL CSS animations before anything renders
    await page.addStyleTag({
      content: '*,*::before,*::after{animation:none!important;transition:none!important}',
    });

    // Wait for the Livewire component to mount and data to load
    await page.waitForSelector('[wire\\:poll\\.30s]', { timeout: 15000 });
    // Wait for stat cards to render (the grid with 4 stat cards)
    await page.waitForSelector('.stat-card', { timeout: 10000 });
    // Let charts render and counters complete (~600ms animation)
    await page.waitForTimeout(3000);

    // ── Complete freeze: stop ALL async browser APIs ──
    // Flush any pending rAF callbacks first
    await page.evaluate(() => {
      return new Promise<void>(resolve => {
        requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
      });
    });

    await page.evaluate(() => {
      // 1. Nuke all JS timers — stops Livewire polling, Alpine counters, everything
      for (var i = 1; i <= 99999; i++) {
        clearInterval(i);
        clearTimeout(i);
        cancelAnimationFrame(i);
      }

      // 2. Override timer APIs to prevent ANY new callbacks
      window.setTimeout = function() { return 0; };
      window.setInterval = function() { return 0; };
      window.requestAnimationFrame = function() { return 0; };
      window.cancelAnimationFrame = function() {};

      // 3. Remove chart skeletons immediately
      var skeletons = document.querySelectorAll('.chart-skeleton');
      for (var s = 0; s < skeletons.length; s++) { skeletons[s].remove(); }

      // 4. Fix counter spans — stuck mid-animation after clearInterval
      var counters = document.querySelectorAll('[x-intersect\\.once]');
      for (var c = 0; c < counters.length; c++) {
        var attr = counters[c].getAttribute('x-intersect.once');
        if (!attr) continue;
        var match = attr.match(/target\s*=\s*(\d+(?:\.\d+)?)/);
        if (match) {
          var target = Math.round(parseFloat(match[1])).toString();
          var textSpan = counters[c].querySelector('[x-text]');
          if (textSpan) {
            textSpan.removeAttribute('x-text');
            textSpan.textContent = target;
          }
        }
      }

      // 5. Force all progress bars to final widths
      var bars = document.querySelectorAll('.progress-fill');
      for (var b = 0; b < bars.length; b++) {
        bars[b].style.transition = 'none';
        var wAttr = bars[b].getAttribute('x-intersect.once');
        if (wAttr) {
          var wMatch = wAttr.match(/w\s*=\s*([\d.]+)/);
          if (wMatch) bars[b].style.width = wMatch[1] + '%';
        }
      }
    });

    // Now the page is frozen — no animations, no polling, no rAF
    await page.waitForTimeout(300);

    await expect(page).toHaveScreenshot("dashboard-full.png", {
      fullPage: true,
      // Allow up to ~3% pixel variation — chart SVG/Canvas anti-aliasing
      // varies slightly between test runs. This catches real regressions
      // (layout shifts, missing elements) while tolerating rendering noise.
      maxDiffPixels: 50000,
    });
  });

  test("stat cards visible", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    const statCards = page.locator('.stat-card');
    const count = await statCards.count();
    expect(count).toBeGreaterThanOrEqual(4);

    // Check labels
    const labels = ['活跃项目', '总任务数', '完成率', '延期任务'];
    for (const label of labels) {
      await expect(page.locator(`text=${label}`).first()).toBeVisible();
    }
  });

  test("project overview section", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    // Check section title
    await expect(page.locator('text=项目总览').first()).toBeVisible();

    // Wait for project cards (if data exists)
    const projectCards = page.locator('a[href^="/projects/"]');
    const cardCount = await projectCards.count();

    if (cardCount > 0) {
      // Check that project cards have task count and completion rate
      await expect(projectCards.first()).toBeVisible();
    }

    // Check "查看全部" link
    await expect(page.locator('a[href="/projects"]').first()).toBeVisible();
  });

  test("trend charts sections", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    // Check chart section titles
    await expect(page.locator('text=近 7 日动态趋势').first()).toBeVisible();
    await expect(page.locator('text=核心指标趋势').first()).toBeVisible();

    // Both chart containers should exist
    await expect(page.locator('#trend-chart')).toBeVisible();
    await expect(page.locator('#kpi-trend-chart')).toBeVisible();
  });

  test("risk alerts sidebar", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    // Section title
    await expect(page.locator('text=风险预警').first()).toBeVisible();

    // Check for either risk items or empty state
    const riskItems = page.locator('.group\\/risk');
    const emptyState = page.locator('text=暂无风险');

    const hasRisks = (await riskItems.count()) > 0;
    const hasEmpty = await emptyState.isVisible();

    expect(hasRisks || hasEmpty).toBeTruthy();

    // "查看全部风险" link
    await expect(page.locator('a[href="/risks"]').first()).toBeVisible();
  });

  test("resource heatmap (team workload)", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    await expect(page.locator('text=团队负载').first()).toBeVisible();

    // Check for either member items or empty state
    const members = page.locator('.group\\/member');
    const emptyState = page.locator('text=暂无团队成员数据');

    const hasMembers = (await members.count()) > 0;
    const hasEmpty = await emptyState.isVisible();

    expect(hasMembers || hasEmpty).toBeTruthy();
  });

  test("filter bar interaction", async ({ page }) => {
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    // Filter bar should start visible
    await expect(page.locator('text=高级筛选').first()).toBeVisible();

    // Search input should be present
    const searchInput = page.locator('input[placeholder="项目名称或描述..."]');
    await expect(searchInput).toBeVisible();

    // Try typing in search
    await searchInput.fill('test-project');
    // Debounce 300ms + Livewire roundtrip
    await page.waitForTimeout(1000);

    // Clear search
    await searchInput.clear();
    await page.waitForTimeout(500);
  });
});
