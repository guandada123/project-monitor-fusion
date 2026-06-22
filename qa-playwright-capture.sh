#!/bin/bash
#===============================================================================
# QA Playwright Capture
# --------------------
# Portable screenshot capture for project-monitor-fusion.
# Uses Playwright to log in via Dusk, navigate to each key page,
# and capture full-page screenshots for visual QA review.
#
# Usage:
#   ./qa-playwright-capture.sh [base_url] [output_dir]
#
# Defaults:
#   base_url   = http://localhost:8000
#   output_dir = public/qa-screenshots
#
# Examples:
#   ./qa-playwright-capture.sh
#   ./qa-playwright-capture.sh http://localhost:8000 public/qa-screenshots
#   ./qa-playwright-capture.sh https://staging.example.com /tmp/screenshots
#===============================================================================

set -euo pipefail

BASE_URL="${1:-http://localhost:8000}"
OUTPUT_DIR="${2:-public/qa-screenshots}"

# Compute paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_ABS="$SCRIPT_DIR/$OUTPUT_DIR"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# --- Preflight Checks ---
echo -e "${YELLOW}[QA] Preflight checks...${NC}"

# 1. Check required command
if ! command -v npx &>/dev/null; then
    echo -e "${RED}[FAIL] npx not found. Is Node.js installed?${NC}"
    exit 1
fi

# 2. Check Playwright chromium is installed
if ! npx playwright --version &>/dev/null; then
    echo -e "${RED}[FAIL] Playwright not found. Run: npm install && npx playwright install chromium${NC}"
    exit 1
fi

# 3. Ping the base URL
if ! curl -sf "$BASE_URL" > /dev/null 2>&1; then
    echo -e "${RED}[FAIL] Cannot reach $BASE_URL — is the dev server running?${NC}"
    echo "  Start it with: php artisan serve &"
    exit 1
fi

# 4. Create output directory
mkdir -p "$OUTPUT_ABS"

echo -e "${GREEN}[OK] All preflight checks passed.${NC}"
echo ""

# --- Capture Script ---
SCRIPT=$(cat <<'PLAYWRIGHT_SCRIPT'
const { chromium } = require("playwright");
const path = require("path");

const BASE_URL = process.env.CAPTURE_BASE_URL || "http://localhost:8000";
const OUTPUT_DIR = process.env.CAPTURE_OUTPUT_DIR || process.cwd();
const SLOW_MO = parseInt(process.env.CAPTURE_SLOW_MO || "0", 10);

const PAGES = [
  { path: "/dashboard", name: "dashboard", waitFor: "h1" },
  { path: "/projects", name: "projects-list", waitFor: "h1" },
  { path: "/projects/1", name: "projects-detail", waitFor: "h1" },
  { path: "/metrics", name: "metrics-builder", waitFor: "h1:has-text('指标构建器')" },
  { path: "/risk-timeline", name: "risk-timeline", waitFor: "h1" },
  { path: "/settings", name: "settings", waitFor: "h1" },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
  });
  const page = await context.newPage();

  // Step 1: Authenticate via Dusk login (user ID 1 = admin)
  console.log(`  Logging in as admin (user #1)...`);
  await page.goto(`${BASE_URL}/dusk/login/1`, {
    waitUntil: "networkidle",
  });

  // Step 2: Capture each page
  for (const p of PAGES) {
    const url = `${BASE_URL}${p.path}`;
    const filename = `${p.name}.png`;
    const filepath = path.join(OUTPUT_DIR, filename);

    console.log(`  📸 ${url} → ${filename}`);

    try {
      await page.goto(url, { waitUntil: "networkidle", timeout: 15000 });
      // Wait for the page-specific selector
      if (p.waitFor) {
        await page.waitForSelector(p.waitFor, { timeout: 10000 });
      }
      // Allow animations to settle
      await page.waitForTimeout(500);

      await page.screenshot({
        path: filepath,
        fullPage: true,
      });

      console.log(`     ✅ Saved (${filename})`);
    } catch (err) {
      console.error(`     ❌ Failed: ${err.message}`);
    }
  }

  await browser.close();
  console.log("\n  All captures complete.");
})();
PLAYWRIGHT_SCRIPT
)

echo -e "${YELLOW}[QA] Capturing screenshots...${NC}"
echo "  Base URL:  $BASE_URL"
echo "  Output:    $OUTPUT_ABS"
echo ""

CAPTURE_BASE_URL="$BASE_URL" \
CAPTURE_OUTPUT_DIR="$OUTPUT_ABS" \
npx playwright test --browser chromium --reporter=list --timeout=60000 \
  2>&1 || true

# Alternative: use the inline script directly
CAPTURE_BASE_URL="$BASE_URL" \
CAPTURE_OUTPUT_DIR="$OUTPUT_ABS" \
CAPTURE_SLOW_MO="0" \
  node -e "$SCRIPT"

echo ""
echo -e "${GREEN}[QA] Done!${NC}"
echo "  Screenshots saved to: $OUTPUT_ABS"
echo ""

# Summary
COUNT=$(find "$OUTPUT_ABS" -name "*.png" 2>/dev/null | wc -l | tr -d ' ')
if [ "$COUNT" -gt 0 ]; then
  echo -e "${GREEN}  ✅ $COUNT screenshot(s) captured.${NC}"
  ls -lh "$OUTPUT_ABS"/*.png 2>/dev/null
else
  echo -e "${RED}  ⚠️  No screenshots were captured.${NC}"
fi
