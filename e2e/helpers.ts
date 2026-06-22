import { Page } from "@playwright/test";

/**
 * Freeze all dynamic page content before screenshots.
 *
 * Stops Livewire polling, Alpine counter animations, ApexCharts rendering,
 * and all CSS transitions/animations. Also flushes pending rAF callbacks
 * and removes chart skeletons.
 *
 * Call this immediately before toHaveScreenshot().
 */
export async function freezePage(page: Page): Promise<void> {
  // 1. Inject CSS freeze early
  await page.addStyleTag({
    content:
      "*,*::before,*::after{animation:none!important;transition:none!important}",
  });

  // 2. Flush pending rAF callbacks (two frames to settle)
  await page.evaluate(() => {
    return new Promise<void>((resolve) => {
      requestAnimationFrame(() => requestAnimationFrame(() => resolve()));
    });
  });

  // 3. Nuke all timers & override APIs
  await page.evaluate(() => {
    // Clear all pending timers/animations
    for (var i = 1; i <= 99999; i++) {
      clearInterval(i);
      clearTimeout(i);
      cancelAnimationFrame(i);
    }
    // Prevent any new callbacks
    window.setTimeout = function () {
      return 0;
    };
    window.setInterval = function () {
      return 0;
    };
    window.requestAnimationFrame = function () {
      return 0;
    };
    window.cancelAnimationFrame = function () {};

    // Remove chart skeletons
    var skeletons = document.querySelectorAll(".chart-skeleton");
    for (var s = 0; s < skeletons.length; s++) {
      skeletons[s].remove();
    }

    // Fix Alpine counter animations — set to final target value
    var counters = document.querySelectorAll("[x-intersect\\.once]");
    for (var c = 0; c < counters.length; c++) {
      var attr = counters[c].getAttribute("x-intersect.once");
      if (!attr) continue;
      var match = attr.match(/target\s*=\s*(\d+(?:\.\d+)?)/);
      if (match) {
        var target = Math.round(parseFloat(match[1])).toString();
        var textSpan = counters[c].querySelector("[x-text]");
        if (textSpan) {
          textSpan.removeAttribute("x-text");
          textSpan.textContent = target;
        }
      }
    }

    // Force progress bars to final widths
    var bars = document.querySelectorAll(".progress-fill");
    for (var b = 0; b < bars.length; b++) {
      bars[b].style.transition = "none";
      var wAttr = bars[b].getAttribute("x-intersect.once");
      if (wAttr) {
        var wMatch = wAttr.match(/w\s*=\s*([\d.]+)/);
        if (wMatch) bars[b].style.width = wMatch[1] + "%";
      }
    }
  });

  // 4. Let DOM settle
  await page.waitForTimeout(300);
}
