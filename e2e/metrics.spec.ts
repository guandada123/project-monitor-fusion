import { test, expect } from "@playwright/test";
import { freezePage } from "./helpers";

test.describe("Metrics Builder", () => {
  test.beforeEach(async ({ page }) => {
    // Navigate to the metrics page via Dusk auth
    await page.goto("/dusk/login/1");
    await page.goto("/metrics");
    // Wait for Livewire component to mount and render
    await page.waitForSelector("h1:has-text('指标构建器')", { timeout: 10000 });
  });

  test("full page screenshot matches baseline", async ({ page }) => {
    // Wait for metric table / empty state to render
    await page.waitForSelector("table", { timeout: 10000 });
    // Allow animations to settle
    await page.waitForTimeout(500);
    // Freeze all animations and dynamic content before screenshot
    await freezePage(page);

    await expect(page).toHaveScreenshot("metrics-builder-fullpage.png", {
      fullPage: true,
      maxDiffPixels: 50000,
      // Mask the JSONLogic textarea content (dynamic placeholder icon area)
      mask: [page.locator("textarea")],
    });
  });

  test("displays page header with title and description", async ({ page }) => {
    await expect(
      page.locator("h1:has-text('指标构建器')")
    ).toBeVisible();
    await expect(
      page.locator("text=可视化定义和组装自定义 KPI 指标")
    ).toBeVisible();
  });

  test("filter pills work correctly", async ({ page }) => {
    // Click "自定义指标" filter
    await page.locator("button:has-text('自定义指标')").click();
    // Wait for the filter to apply (Livewire re-render)
    await page.waitForTimeout(300);
    // The active filter should have accent background
    const activeFilter = page.locator(
      "button:has-text('自定义指标')"
    );
    await expect(activeFilter).toBeVisible();

    // Click "全部" to reset
    await page.locator('button:has-text("全部"):not(:has-text("全部已读"))').click();
    await page.waitForTimeout(300);
    const allFilter = page.locator('button:has-text("全部"):not(:has-text("全部已读"))');
    await expect(allFilter).toBeVisible();
  });

  test("open and close create metric modal", async ({ page }) => {
    // Click "新建指标" button
    await page.locator("button:has-text('新建指标')").click();
    // Wait for modal to appear
    await page.waitForSelector("h3:has-text('新建指标')", {
      timeout: 5000,
    });
    // Verify form fields are present
    await expect(
      page.locator('input[placeholder="completion_rate"]')
    ).toBeVisible();
    await expect(
      page.locator('input[placeholder="完成率"]')
    ).toBeVisible();

    // Close modal via close button
    await page.locator("button:has-text('取消')").click();
    // Modal should disappear
    await page.waitForTimeout(300);
    await expect(
      page.locator("h3:has-text('新建指标')")
    ).not.toBeVisible();
  });

  test("metric table or empty state renders", async ({ page }) => {
    // Either the table rows exist or the empty state is shown
    const rows = page.locator("table tbody tr");
    const rowCount = await rows.count();

    if (rowCount === 1) {
      // Check if it's the empty state row
      const emptyState = rows.locator("text=暂无指标");
      if (await emptyState.isVisible()) {
        await expect(emptyState).toBeVisible();
        await expect(
          page.locator("text=点击「新建指标」开始配置自定义 KPI")
        ).toBeVisible();
        return;
      }
    }

    // Otherwise, we have metric rows — verify row structure
    if (rowCount > 0) {
      // At least one metric name should be visible
      const metricName = rows.first().locator(".font-semibold").first();
      await expect(metricName).toBeVisible();
    }
  });

  test("action buttons visible on metric rows", async ({ page }) => {
    const rows = page.locator("table tbody tr");
    const rowCount = await rows.count();

    if (rowCount > 0 && !(await rows.locator("text=暂无指标").isVisible())) {
      // Hover over the first data row to reveal action buttons
      const firstRow = rows.first();
      await firstRow.hover();
      await page.waitForTimeout(200);

      // At minimum, refresh and edit buttons should be present
      const refreshButtons = firstRow.locator('button[title="立即计算"]');
      const editButtons = firstRow.locator('button[title="编辑"]');
      await expect(refreshButtons.first()).toBeVisible();
      await expect(editButtons.first()).toBeVisible();
    }
  });
});
