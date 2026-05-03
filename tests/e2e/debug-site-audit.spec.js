const { test, expect } = require('@playwright/test');

const PLUGIN_URL = '/wp-admin/tools.php?page=cs-seo-optimizer';

test('SEO Site Audit — button click and results', async ({ page }) => {
    const consoleLogs = [];
    page.on('console', msg => consoleLogs.push(`[${msg.type().toUpperCase()}] ${msg.text()}`));
    page.on('pageerror', err => consoleLogs.push(`[PAGEERROR] ${err.message}`));
    page.on('dialog', async dialog => { await dialog.dismiss(); });

    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.click('[data-tab="siteaudit"]');
    await page.waitForTimeout(300);

    const btn = await page.$('#cs-run-audit-btn');
    expect(btn).not.toBeNull();
    await btn.scrollIntoViewIfNeeded();

    // Intercept fetch to confirm listener fires
    await page.evaluate(() => {
        const origFetch = window.fetch;
        window.__fetchCalled = false;
        window.fetch = function() { window.__fetchCalled = true; return origFetch.apply(this, arguments); };
    });

    await btn.click();

    // Audit typically completes in < 5s; wait for button to return to Run Audit state
    await page.waitForFunction(
        () => {
            const b = document.getElementById('cs-run-audit-btn');
            return b && !b.disabled && b.textContent?.includes('Run Audit');
        },
        { timeout: 90000 }
    );

    const fetchCalled = await page.evaluate(() => window.__fetchCalled || false);
    expect(fetchCalled).toBe(true);

    // Results section should be visible after audit completes
    const resultsVisible = await page.isVisible('#cs-audit-results');
    expect(resultsVisible).toBe(true);

    // Last run timestamp should be updated
    const lastRun = await page.$eval('#cs-audit-last-run', el => el.textContent);
    expect(lastRun).toContain('Last run:');

    console.log('✅ Audit completed. Last run:', lastRun);
    console.log('JS errors during test:', consoleLogs.filter(l => l.includes('ERROR') || l.includes('PAGEERROR')).join('\n') || 'none');

    await page.screenshot({ path: 'test-results/audit-completed.png' });
});
