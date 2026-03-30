/**
 * End-to-end tests for the Automatic Redirects feature.
 *
 * Flow:
 *  1. Navigate to the Redirects tab and ensure the feature is enabled.
 *  2. Create a published post via the REST API with a known slug.
 *  3. Rename the slug via the REST API — this triggers the WP hooks that
 *     capture the old permalink and store the redirect entry.
 *  4. Assert the entry appears in the admin redirect table.
 *  5. Visit the old URL and assert that Playwright lands on the new URL
 *     (i.e. the 301 redirect was served).
 *  6. Assert the hit counter incremented and last-hit timestamp is shown.
 *  7. Clean up: delete the test post and the stored redirect entry.
 */
const { test, expect } = require('@playwright/test');

const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Navigate to the plugin page and activate a tab by data-tab value. */
async function openTab(page, tab) {
    await page.goto(process.env.WP_BASE_URL + PLUGIN_PAGE);
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    await page.click(`[data-tab="${tab}"]`);
    await page.waitForSelector(`#ab-pane-${tab}.active`, { timeout: 10000 });
}

/**
 * Retrieve the REST API nonce by loading the Gutenberg post-new page.
 * window.wpApiSettings.nonce is injected by WordPress on every admin page.
 */
async function getRestNonce(page) {
    await page.goto(process.env.WP_BASE_URL + '/wp-admin/post-new.php');
    const nonce = await page.evaluate(() => window.wpApiSettings?.nonce);
    if (!nonce) throw new Error('Could not read wpApiSettings.nonce from post-new.php');
    return nonce;
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Automatic Redirects', () => {

    test('enable → slug rename → 301 serves → hit counter increments', async ({ page }) => {
        const BASE   = process.env.WP_BASE_URL;
        let postId   = null;
        let oldPath  = null;

        try {
            // ── Step 1: ensure redirects are enabled and saved ────────────────
            await openTab(page, 'redirects');

            const checkbox = page.locator('#ab-pane-redirects input[name*="[enable_redirects]"]');
            if (!await checkbox.isChecked()) {
                await checkbox.check();
                await page.locator('#ab-pane-redirects button[type="submit"]').click();
                await page.waitForLoadState('domcontentloaded');
                await page.waitForSelector('.ab-tabs', { timeout: 15000 });
                // Navigate back to redirects tab after options.php redirect
                await openTab(page, 'redirects');
            }

            // Confirm the checkbox is checked after save
            await expect(
                page.locator('#ab-pane-redirects input[name*="[enable_redirects]"]')
            ).toBeChecked();

            // ── Step 2: get REST nonce ────────────────────────────────────────
            const nonce = await getRestNonce(page);

            // ── Step 3: create a published post with a known slug ─────────────
            const ts    = Date.now();
            const slug1 = `pw-redir-${ts}`;

            const created = await page.evaluate(async ({ base, nonce, slug }) => {
                const r = await fetch(`${base}/wp-json/wp/v2/posts`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body:    JSON.stringify({ title: 'PW Redirect Test', slug, status: 'publish' }),
                });
                return r.json();
            }, { base: BASE, nonce, slug: slug1 });

            expect(created.id,   `Post creation failed: ${JSON.stringify(created)}`).toBeTruthy();
            expect(created.link, 'Post has no link').toBeTruthy();
            postId  = created.id;
            const oldLink = created.link;
            oldPath = new URL(oldLink).pathname;

            // ── Step 4: rename slug → WP hooks capture redirect ───────────────
            const slug2   = `pw-redir-new-${ts}`;
            const updated = await page.evaluate(async ({ base, nonce, id, slug }) => {
                const r = await fetch(`${base}/wp-json/wp/v2/posts/${id}`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body:    JSON.stringify({ slug }),
                });
                return r.json();
            }, { base: BASE, nonce, id: postId, slug: slug2 });

            expect(updated.slug, `Slug update failed: ${JSON.stringify(updated)}`).toBe(slug2);
            const newLink = updated.link;

            // ── Step 5: redirect entry appears in admin table ─────────────────
            await openTab(page, 'redirects');

            const row = page.locator('#ab-pane-redirects tbody tr')
                .filter({ hasText: oldPath });

            await expect(row).toBeVisible({ timeout: 10000 });

            // Old path should be a clickable link
            await expect(row.locator(`a[href*="${encodeURIComponent(oldPath).replace(/%2F/g, '/')}"]`))
                .toBeVisible();

            // ── Step 6: 301 redirect fires ────────────────────────────────────
            await page.goto(oldLink);
            await page.waitForLoadState('domcontentloaded');

            expect(
                page.url(),
                `Expected redirect from\n  ${oldLink}\nto\n  ${newLink}\nbut ended up at:\n  ${page.url()}`
            ).toBe(newLink);

            // ── Step 7: hit counter incremented, last-hit shown ───────────────
            await openTab(page, 'redirects');

            const updatedRow = page.locator('#ab-pane-redirects tbody tr')
                .filter({ hasText: oldPath });

            // Hits column (first td after old-path td contains a number ≥ 1)
            const hitsCell = updatedRow.locator('td').nth(1);
            const hitsText = await hitsCell.innerText();
            expect(
                parseInt(hitsText, 10),
                `Hit counter should be ≥ 1, got "${hitsText}"`
            ).toBeGreaterThanOrEqual(1);

            // Last-hit column should not be the dash placeholder
            const lastHitCell = updatedRow.locator('td').nth(2);
            await expect(lastHitCell).not.toHaveText('—');

        } finally {
            // ── Cleanup ───────────────────────────────────────────────────────
            if (postId) {
                const cleanNonce = await getRestNonce(page);

                // Delete the test post (force=true skips trash)
                await page.evaluate(async ({ base, nonce, id }) => {
                    await fetch(`${base}/wp-json/wp/v2/posts/${id}?force=true`, {
                        method:  'DELETE',
                        headers: { 'X-WP-Nonce': nonce },
                    });
                }, { base: BASE, nonce: cleanNonce, id: postId });

                // Delete the stored redirect entry via the admin AJAX endpoint
                if (oldPath) {
                    await page.goto(BASE + PLUGIN_PAGE);
                    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
                    const deleteBtn = page.locator('.cs-del-redirect').filter({ hasText: '' })
                        .locator(`[data-from="${oldPath}"]`);
                    if (await deleteBtn.count() > 0) {
                        await deleteBtn.click();
                        await page.waitForTimeout(500);
                    }
                }
            }
        }
    });

});
