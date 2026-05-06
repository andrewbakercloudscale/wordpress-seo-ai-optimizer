/**
 * One-off spec: screenshot the onboarding Get Started pane.
 * Run via: CSDT_ENV_TEST=... bash run-ui-tests.sh --grep "screenshot onboarding"
 */
const { test, expect } = require('@playwright/test');
const path = require('path');
const fs   = require('fs');

const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';
const OUT_DIR = path.join(__dirname, '..', 'screenshots');

test('screenshot onboarding — Get Started pane', async ({ page, request }) => {
    if (!fs.existsSync(OUT_DIR)) fs.mkdirSync(OUT_DIR, { recursive: true });

    // ── Reset welcome_shown via WP AJAX ──
    await page.goto(`${process.env.WP_BASE_URL}${PLUGIN_PAGE}`, { waitUntil: 'domcontentloaded' });
    const nonce = await page.evaluate(() => (typeof csSeoAdmin !== 'undefined' ? csSeoAdmin.nonce : null));
    expect(nonce, 'csSeoAdmin.nonce should be defined').toBeTruthy();

    // Call complete_onboarding in reverse: we need to delete the option.
    // Use the plugin's existing reset via direct DB call via options.php trick,
    // or use a WordPress core AJAX.
    // Best available: post to admin-ajax.php — action will 404 but that's ok for now.
    // Instead, use options.php to set cs_seo_welcome_shown to 0.
    await page.goto(`${process.env.WP_BASE_URL}/wp-admin/options.php`, { waitUntil: 'networkidle' });
    const wpnonce = await page.$eval('input[name="_wpnonce"]', el => el.value).catch(() => null);

    if (wpnonce) {
        await page.evaluate(async ({ nonce }) => {
            // Use fetch to post to admin-ajax.php with a custom action
            const fd = new FormData();
            fd.append('action', 'cs_seo_reset_onboarding');
            fd.append('nonce', nonce);
            await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'include' }).catch(() => {});
        }, { nonce });
    }

    // ── Reload plugin page ──
    await page.goto(`${process.env.WP_BASE_URL}${PLUGIN_PAGE}`, { waitUntil: 'networkidle' });
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });

    const startVisible = await page.locator('[data-tab="start"]').isVisible().catch(() => false);
    console.log('Get Started tab visible:', startVisible);
    console.log('Active tab:', await page.evaluate(() => document.querySelector('.ab-tab.active')?.getAttribute('data-tab') || 'none'));

    // Click Get Started tab to show the onboarding pane
    if (startVisible) {
        await page.locator('[data-tab="start"]').click();
        await page.waitForTimeout(400);
    }

    // Above-fold viewport (first impression)
    await page.screenshot({ path: path.join(OUT_DIR, 'onboarding-viewport.png') });
    console.log('Saved: onboarding-viewport.png');

    // Full page
    await page.screenshot({ path: path.join(OUT_DIR, 'onboarding-full.png'), fullPage: true });
    console.log('Saved: onboarding-full.png');
});
