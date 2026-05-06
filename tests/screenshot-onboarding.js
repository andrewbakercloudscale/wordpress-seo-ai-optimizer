/**
 * Screenshot the Get Started onboarding pane.
 * Run: node tests/screenshot-onboarding.js
 */
const { chromium } = require('@playwright/test');
const path = require('path');
const fs   = require('fs');

const BASE    = process.env.WP_BASE_URL || 'https://andrewbaker.ninja';
const AUTH    = path.join(__dirname, 'auth.json');
const OUT_DIR = path.join(__dirname, 'screenshots');
const PLUGIN  = `${BASE}/wp-admin/tools.php?page=cs-seo-optimizer`;

(async () => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    const browser = await chromium.launch({ headless: true });
    const ctx     = await browser.newContext({ storageState: AUTH, viewport: { width: 1440, height: 900 } });
    const page    = await ctx.newPage();

    console.log('Loading plugin page…');
    await page.goto(PLUGIN, { waitUntil: 'networkidle' });

    const url = page.url();
    console.log('Landed at:', url);

    // If redirected away from plugin page, bail
    if (!url.includes('cs-seo-optimizer')) {
        console.error('Not on plugin page — auth may have redirected. URL:', url);
        await page.screenshot({ path: path.join(OUT_DIR, 'debug-redirect.png') });
        await browser.close();
        process.exit(1);
    }

    // Check what's visible
    const startTabVisible = await page.locator('[data-tab="start"]').isVisible().catch(() => false);
    console.log('Get Started tab visible:', startTabVisible);

    const activeTab = await page.evaluate(() => {
        const t = document.querySelector('.ab-tab.active');
        return t ? t.getAttribute('data-tab') : 'none';
    });
    console.log('Active tab:', activeTab);

    // Full page
    const full = path.join(OUT_DIR, 'onboarding-full.png');
    await page.screenshot({ path: full, fullPage: true });
    console.log('Saved full:', full);

    // Viewport (above fold)
    const vp = path.join(OUT_DIR, 'onboarding-viewport.png');
    await page.screenshot({ path: vp, fullPage: false });
    console.log('Saved viewport:', vp);

    // If Get Started is active, scroll to cards and screenshot
    if (startTabVisible) {
        await page.locator('#ab-onboard-cards').scrollIntoViewIfNeeded().catch(() => {});
        await page.waitForTimeout(300);
        const cards = path.join(OUT_DIR, 'onboarding-cards.png');
        await page.screenshot({ path: cards, fullPage: false });
        console.log('Saved cards:', cards);
    }

    await browser.close();
    console.log('Done.');
})();
