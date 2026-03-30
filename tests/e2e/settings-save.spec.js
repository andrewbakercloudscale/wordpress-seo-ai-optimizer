/**
 * Settings save tests — verifies every panel's Save button actually persists
 * the change to the database.
 *
 * For each field:
 *   1. Toggle the checkbox off (or on if already off)
 *   2. Click Save
 *   3. Reload the page and navigate back to the same tab
 *   4. Assert the new state persisted
 *   5. Restore the original value and save again
 *   6. Assert restored
 */
const { test, expect } = require('@playwright/test');

const BASE        = () => process.env.WP_BASE_URL;
const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';

// ── Helper ────────────────────────────────────────────────────────────────────

async function openTab(page, tab) {
    await page.goto(BASE() + PLUGIN_PAGE);
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    if (tab !== 'seo') {
        await page.click(`[data-tab="${tab}"]`);
        await page.waitForSelector(`#ab-pane-${tab}.active`, { timeout: 10000 });
    }
}

/**
 * Toggle a checkbox, save, reload, assert state persisted, then restore.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} tab         - data-tab value (seo / sitemap / perf / batch / aitools)
 * @param {string} selector    - checkbox selector scoped to the pane
 * @param {string} saveLabel   - exact text of the Save button in that form
 */
async function testCheckboxSave(page, tab, selector, saveLabel) {
    await openTab(page, tab);

    const pane   = page.locator(`#ab-pane-${tab}`);
    const field  = pane.locator(selector).first();
    const before = await field.isChecked();

    // Step 1: toggle (force:true handles opacity:0 / visually-hidden custom toggles)
    if (before) { await field.uncheck({ force: true }); } else { await field.check({ force: true }); }

    // Step 2: save
    await pane.getByRole('button', { name: saveLabel, exact: true }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });

    // Step 3–4: reload and verify persisted
    await openTab(page, tab);
    const afterField = page.locator(`#ab-pane-${tab}`).locator(selector).first();
    const after      = await afterField.isChecked();
    expect(
        after,
        `[${tab}] "${saveLabel}" — save did NOT persist.\n` +
        `  selector: ${selector}\n` +
        `  expected: ${!before}, got: ${after}`
    ).toBe(!before);

    // Step 5: restore
    if (before) { await afterField.check({ force: true }); } else { await afterField.uncheck({ force: true }); }
    await page.locator(`#ab-pane-${tab}`)
        .getByRole('button', { name: saveLabel, exact: true }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });

    // Step 6: verify restored
    await openTab(page, tab);
    const restored = await page.locator(`#ab-pane-${tab}`).locator(selector).first().isChecked();
    expect(
        restored,
        `[${tab}] "${saveLabel}" — restore did NOT persist.\n` +
        `  expected: ${before}, got: ${restored}`
    ).toBe(before);
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Settings save — all panels', () => {

    // ── SITEMAP TAB ───────────────────────────────────────────────────────────
    // Features & Robots form

    test('Sitemap — OpenGraph toggle persists (Save Features & Robots Settings)', async ({ page }) => {
        await testCheckboxSave(
            page, 'sitemap',
            'input[type="checkbox"][name*="[enable_og]"]',
            'Save Features & Robots Settings'
        );
    });

    test('Sitemap — noindex search results persists (Save Features & Robots Settings)', async ({ page }) => {
        await testCheckboxSave(
            page, 'sitemap',
            'input[type="checkbox"][name*="[noindex_search]"]',
            'Save Features & Robots Settings'
        );
    });

    test('Sitemap — noindex 404 pages persists (Save Features & Robots Settings)', async ({ page }) => {
        await testCheckboxSave(
            page, 'sitemap',
            'input[type="checkbox"][name*="[noindex_404]"]',
            'Save Features & Robots Settings'
        );
    });

    // Sitemap settings form — enable_sitemap appears in TWO places (features grid + sitemap card).
    // Both must be toggled together since both are in the same <form>.
    test('Sitemap — enable sitemap persists (Save Sitemap Settings)', async ({ page }) => {
        await openTab(page, 'sitemap');
        const pane     = page.locator('#ab-pane-sitemap');
        const fields   = pane.locator('input[type="checkbox"][name*="[enable_sitemap]"]');
        const count    = await fields.count();
        const before   = await fields.first().isChecked();

        // Toggle ALL instances so they agree
        for (let i = 0; i < count; i++) {
            if (before) { await fields.nth(i).uncheck({ force: true }); }
            else        { await fields.nth(i).check({ force: true }); }
        }
        await pane.getByRole('button', { name: 'Save Sitemap Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });

        await openTab(page, 'sitemap');
        const after = await page.locator('#ab-pane-sitemap')
            .locator('input[type="checkbox"][name*="[enable_sitemap]"]').first().isChecked();
        expect(after, `enable_sitemap save did not persist. Expected ${!before}, got ${after}`).toBe(!before);

        // Restore
        const restoreFields = page.locator('#ab-pane-sitemap')
            .locator('input[type="checkbox"][name*="[enable_sitemap]"]');
        const rCount = await restoreFields.count();
        for (let i = 0; i < rCount; i++) {
            if (before) { await restoreFields.nth(i).check({ force: true }); }
            else        { await restoreFields.nth(i).uncheck({ force: true }); }
        }
        await page.locator('#ab-pane-sitemap')
            .getByRole('button', { name: 'Save Sitemap Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    });

    // llms.txt form
    test('Sitemap — enable llms.txt persists (Save llms.txt Settings)', async ({ page }) => {
        await testCheckboxSave(
            page, 'sitemap',
            'input[type="checkbox"][name*="[enable_llms_txt]"]',
            'Save llms.txt Settings'
        );
    });

    // Redirects form (inside Sitemap pane)
    test('Sitemap — enable redirects persists (Save Changes)', async ({ page }) => {
        await testCheckboxSave(
            page, 'sitemap',
            'input[type="checkbox"][name*="[enable_redirects]"]',
            'Save Changes'
        );
    });

    // ── PERFORMANCE TAB ───────────────────────────────────────────────────────

    test('Performance — defer JS persists', async ({ page }) => {
        await testCheckboxSave(
            page, 'perf',
            'input[type="checkbox"][name*="[defer_js]"]',
            'Save Performance Settings'
        );
    });

    test('Performance — minify HTML persists', async ({ page }) => {
        await testCheckboxSave(
            page, 'perf',
            'input[type="checkbox"][name*="[minify_html]"]',
            'Save Performance Settings'
        );
    });

    // ── SCHEDULED BATCH TAB ───────────────────────────────────────────────────

    test('Batch — schedule enabled persists', async ({ page }) => {
        await testCheckboxSave(
            page, 'batch',
            'input[type="checkbox"][name*="[schedule_enabled]"]',
            'Save Schedule Settings'
        );
    });

    // ── AI TOOLS TAB ──────────────────────────────────────────────────────────

    test('AI Tools — related articles enabled persists', async ({ page }) => {
        // rc_enable lives inside an ab-zone-body that may be collapsed.
        // We can't use testCheckboxSave because openTab() would re-collapse it.
        // So we inline the logic with an explicit expand step after each navigation.

        async function expandRcCard() {
            const body = page.locator('.ab-card-rc-settings-card .ab-zone-body');
            const hidden = await body.evaluate(
                el => el.style.display === 'none' || getComputedStyle(el).display === 'none'
            );
            if (hidden) {
                await page.locator('.ab-card-rc-settings-card .ab-toggle-card-btn').click();
                await page.waitForTimeout(300);
            }
        }

        await openTab(page, 'aitools');
        await expandRcCard();

        const pane   = page.locator('#ab-pane-aitools');
        const field  = pane.locator('input[type="checkbox"][name*="[rc_enable]"]').first();
        const before = await field.isChecked();

        if (before) { await field.uncheck({ force: true }); }
        else        { await field.check({ force: true }); }

        await pane.getByRole('button', { name: 'Save SEO Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });

        await openTab(page, 'aitools');
        await expandRcCard();

        const after = await page.locator('#ab-pane-aitools')
            .locator('input[type="checkbox"][name*="[rc_enable]"]').first().isChecked();
        expect(after, `rc_enable save did not persist. Expected ${!before}, got ${after}`).toBe(!before);

        // Restore
        await openTab(page, 'aitools');
        await expandRcCard();
        const restoreField = page.locator('#ab-pane-aitools')
            .locator('input[type="checkbox"][name*="[rc_enable]"]').first();
        if (before) { await restoreField.check({ force: true }); }
        else        { await restoreField.uncheck({ force: true }); }
        await page.locator('#ab-pane-aitools')
            .getByRole('button', { name: 'Save SEO Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    });

    // ── SEO TAB ───────────────────────────────────────────────────────────────
    // The SEO pane contains text/select fields; test that a text field round-trips.

    test('SEO tab — pane loads and Save buttons are present', async ({ page }) => {
        await openTab(page, 'seo');
        const pane = page.locator('#ab-pane-seo');
        await expect(pane).toBeVisible();
        // Should have at least one Save button
        const saveBtns = pane.getByRole('button', { name: /Save/i });
        await expect(saveBtns.first()).toBeVisible();
    });

    test('SEO tab — site name field round-trips through Save SEO Settings', async ({ page }) => {
        await openTab(page, 'seo');
        const pane  = page.locator('#ab-pane-seo');
        const field = pane.locator('input[name*="[site_name]"]').first();

        const original = await field.inputValue();
        const testVal  = original + '-PW-TEST';

        await field.fill(testVal);
        await pane.getByRole('button', { name: 'Save SEO Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });

        await openTab(page, 'seo');
        const saved = await page.locator('#ab-pane-seo').locator('input[name*="[site_name]"]').first().inputValue();
        expect(saved, 'site_name did not persist').toBe(testVal);

        // Restore
        const restoreField = page.locator('#ab-pane-seo').locator('input[name*="[site_name]"]').first();
        await restoreField.fill(original);
        await page.locator('#ab-pane-seo').getByRole('button', { name: 'Save SEO Settings', exact: true }).first().click();
        await page.waitForLoadState('domcontentloaded');
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });

        await openTab(page, 'seo');
        const restored = await page.locator('#ab-pane-seo').locator('input[name*="[site_name]"]').first().inputValue();
        expect(restored, 'site_name restore did not persist').toBe(original);
    });

});
