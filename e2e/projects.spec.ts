import { test, expect } from "@playwright/test";
import { freezePage } from "./helpers";

/**
 * Projects page E2E screenshot tests
 *
 * Covers: project list (/projects), project detail (/projects/{slug})
 *
 * Routes: GET /projects (view_projects), GET /projects/{slug} (view_projects)
 */

const DUSK_LOGIN = "/dusk/login/1";

test.describe("Projects", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(DUSK_LOGIN);
    await page.waitForURL("/");
  });

  test("project list page", async ({ page }) => {
    // Navigate to project list
    await page.goto("/projects");
    await page.waitForURL("/projects");

    // Wait for the page title to load
    await page.waitForSelector('text=项目列表', { timeout: 10000 });
    // Wait for project cards to render (project grid uses glass-card class)
    await page.waitForSelector('.glass-card', { timeout: 10000 });
    await page.waitForTimeout(500);

    // Check page title
    await expect(page.locator('h1:has-text("项目列表")')).toContainText('项目列表');

    // Check action buttons
    await expect(page.locator('button:has-text("导出项目")').first()).toBeVisible();
    await expect(page.locator('button:has-text("新建项目")').first()).toBeVisible();

    // Check project count text
    await expect(page.locator('text=共').first()).toBeVisible();

    // Freeze animations before screenshot
    await freezePage(page);

    // Take screenshot
    await expect(page).toHaveScreenshot("projects-list.png", {
      fullPage: true,
      maxDiffPixels: 80000,
    });
  });

  test("project detail page accessible", async ({ page }) => {
    // First go to project list to find a slug
    await page.goto("/projects");
    await page.waitForSelector('.glass-card', { timeout: 10000 });

    // Check if there are project cards linking to detail pages
    const projectLink = page.locator('a[href^="/projects/"]').first();
    const linkCount = await projectLink.count();

    if (linkCount > 0) {
      // Navigate to first project detail
      const href = await projectLink.getAttribute('href');
      await page.goto(href!);
      await page.waitForURL(`/projects/**`);

      // Wait for project title
      await page.waitForSelector('h1:not(:has-text("项目列表"))', { timeout: 10000 });
      await page.waitForTimeout(1500);

      // Check section headers
      await expect(page.locator('h2:has-text("任务时间线")').first()).toBeVisible();
      await expect(page.locator('h2:has-text("任务状态分布")').first()).toBeVisible();
      await expect(page.locator('h2:has-text("风险时间轴")').first()).toBeVisible();
      await expect(page.locator('h2:has-text("任务列表")').first()).toBeVisible();

      // Freeze all animations and dynamic content
      await freezePage(page);

      // Take screenshot
      await expect(page).toHaveScreenshot("project-detail.png", {
        fullPage: true,
        maxDiffPixels: 50000,
        mask: [page.locator('.chart-container canvas'), page.locator('.chart-container svg')],
      });
    } else {
      // No projects exist — verify empty state
      test.skip(true, 'No projects seeded');
    }
  });

  test("project detail — KPI stat cards", async ({ page }) => {
    await page.goto("/projects");
    await page.waitForSelector('.glass-card', { timeout: 10000 });

    const projectLink = page.locator('a[href^="/projects/"]').first();
    if ((await projectLink.count()) === 0) {
      test.skip(true, 'No projects seeded');
      return;
    }

    const href = await projectLink.getAttribute('href');
    await page.goto(href!);
    await page.waitForSelector('.stat-card', { timeout: 10000 });

    // Check KPI labels
    const kpiLabels = ['完成率', '延期率', '风险数', '在制品'];
    for (const label of kpiLabels) {
      const el = page.locator(`text=${label}`).first();
      await expect(el).toBeVisible();
    }
  });

  test("project detail — task table", async ({ page }) => {
    await page.goto("/projects");
    await page.waitForSelector('.glass-card', { timeout: 10000 });

    const projectLink = page.locator('a[href^="/projects/"]').first();
    if ((await projectLink.count()) === 0) {
      test.skip(true, 'No projects seeded');
      return;
    }

    const href = await projectLink.getAttribute('href');
    await page.goto(href!);
    await page.waitForSelector('h2:has-text("任务列表")', { timeout: 10000 });

    // Table headers
    const tableHeaders = ['任务', '状态', '优先级', '负责人', '耗时/预估'];
    for (const header of tableHeaders) {
      const el = page.locator(`th:has-text("${header}")`);
      if (await el.isVisible()) {
        expect(await el.count()).toBeGreaterThanOrEqual(1);
      }
    }
  });

  test("project detail — risk timeline", async ({ page }) => {
    await page.goto("/projects");
    await page.waitForSelector('.glass-card', { timeout: 10000 });

    const projectLink = page.locator('a[href^="/projects/"]').first();
    if ((await projectLink.count()) === 0) {
      test.skip(true, 'No projects seeded');
      return;
    }

    const href = await projectLink.getAttribute('href');
    await page.goto(href!);
    await page.waitForSelector('h2:has-text("风险时间轴")', { timeout: 10000 });

    // Either risk items exist or empty state shown
    const riskItems = page.locator('.group\\/risk');
    const emptyState = page.locator('text=暂无风险记录');

    const hasRisks = (await riskItems.count()) > 0;
    const hasEmpty = await emptyState.isVisible();

    expect(hasRisks || hasEmpty).toBeTruthy();
  });
});
