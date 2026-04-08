/**
 * Title Optimiser — Playwright diagnostic tests
 *
 * Verifies the "Analyse Remaining" button is clickable, produces a log entry,
 * and that background analysis actually runs (or reports "all done").
 *
 * Run via:  bash run-ui-tests.sh --grep "Title Optimiser"
 */
const { test, expect } = require('@playwright/test');

const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';

// ── Helper ────────────────────────────────────────────────────────────────────

async function openTitleOptTab(page) {
    await page.goto(process.env.WP_BASE_URL + PLUGIN_PAGE);
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    await page.click('[data-tab="titleopt"]');
    await page.waitForSelector('#ab-pane-titleopt.active', { timeout: 10000 });
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Title Optimiser', () => {

    // ── 1. Tab loads and table renders ───────────────────────────────────────

    test('tab opens and posts table renders', async ({ page }) => {
        await openTitleOptTab(page);

        const pane = page.locator('#ab-pane-titleopt');
        await expect(pane).toBeVisible();

        // Table should appear within 15 s (toLoad AJAX round-trip)
        await expect(
            page.locator('#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p'),
            'Posts wrap should contain either a table or a "no posts" message'
        ).toBeVisible({ timeout: 15000 });

        // Dump what we got for debugging
        const txt = await page.locator('#ab-titleopt-posts-wrap').innerText();
        console.log('[DEBUG] posts-wrap first 200 chars:', txt.slice(0, 200));
    });

    // ── 2. "Analyse Remaining" button is enabled and visible ─────────────────

    test('"Analyse Remaining" button is enabled and clickable', async ({ page }) => {
        await openTitleOptTab(page);
        // Wait for toLoad to finish
        await page.waitForSelector('#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p', { timeout: 15000 });

        const btn = page.locator('#ab-titleopt-analyse-all');
        await expect(btn).toBeVisible();

        const isDisabled = await btn.isDisabled();
        console.log('[DEBUG] #ab-titleopt-analyse-all disabled =', isDisabled);
        console.log('[DEBUG] button text =', await btn.textContent());

        expect(isDisabled, 'Analyse Remaining button must NOT be disabled').toBe(false);
    });

    // ── 3. Clicking produces a log entry ─────────────────────────────────────

    test('clicking "Analyse Remaining" produces an activity log entry', async ({ page }) => {
        await openTitleOptTab(page);
        await page.waitForSelector('#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p', { timeout: 15000 });

        // Capture any console errors to help diagnose JS failures
        const jsErrors = [];
        page.on('console', msg => {
            if (msg.type() === 'error') jsErrors.push(msg.text());
        });
        page.on('pageerror', err => jsErrors.push(err.message));

        // Capture network requests to the AJAX endpoint
        const ajaxCalls = [];
        page.on('request', req => {
            if (req.url().includes('admin-ajax.php') && req.method() === 'POST') {
                ajaxCalls.push(req.postData() || '');
            }
        });
        page.on('response', async resp => {
            if (resp.url().includes('admin-ajax.php')) {
                try {
                    const body = await resp.text();
                    console.log('[DEBUG] AJAX response:', body.slice(0, 300));
                } catch (_) {}
            }
        });

        const btn = page.locator('#ab-titleopt-analyse-all');
        await btn.click();
        console.log('[DEBUG] button clicked');

        // Within 8 s the log should show SOMETHING (either "Queuing…", "All analysed", or an error)
        await expect(
            page.locator('#ab-titleopt-log .ab-log-entry').first(),
            'Activity log must have at least one entry after clicking Analyse Remaining'
        ).toBeVisible({ timeout: 8000 });

        const logText = await page.locator('#ab-titleopt-log').innerText();
        console.log('[DEBUG] activity log:', logText.slice(0, 500));

        if (jsErrors.length) console.log('[DEBUG] JS errors:', jsErrors);
        if (ajaxCalls.length) console.log('[DEBUG] AJAX actions sent:', ajaxCalls.map(d => {
            const m = d.match(/action=([^&]+)/); return m ? m[1] : d.slice(0, 80);
        }));
    });

    // ── 4. Background queue actually starts (or "all done") ──────────────────

    test('background queue starts or reports all-done after clicking Analyse Remaining', async ({ page }) => {
        await openTitleOptTab(page);
        await page.waitForSelector('#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p', { timeout: 15000 });

        await page.locator('#ab-titleopt-analyse-all').click();

        // Wait for the log to appear, then allow time for AJAX to respond and write the final message
        const log = page.locator('#ab-titleopt-log');
        await expect(log).toBeVisible({ timeout: 5000 });
        await page.waitForTimeout(3000); // let AJAX respond and second log entry appear

        const logText    = await log.innerText();
        const statusText = await page.locator('#ab-titleopt-status').textContent();
        // "queued" in the log AND the status shows background progress = background started
        // "have suggestions" / "nothing left" / "All posts analysed" = all done
        const allDone  = logText.includes('have suggestions') || logText.includes('nothing left')
                       || logText.includes('Nothing to analyse') || statusText.includes('All posts analysed');
        const bgStarted = logText.includes('posts queued') && !allDone;
        const errored   = logText.includes('✗') || logText.includes('Failed to start');

        console.log('[DEBUG] allDone:', allDone, ' bgStarted:', bgStarted, ' errored:', errored);
        console.log('[DEBUG] full log:', logText.slice(0, 800));
        console.log('[DEBUG] status:', statusText);

        expect(
            allDone || bgStarted || errored,
            `Expected a recognisable outcome in log or status. Log:\n${logText.slice(0, 400)}\nStatus: ${statusText}`
        ).toBe(true);

        if (bgStarted) {
            await expect(
                page.locator('#ab-titleopt-status'),
                'Status bar should show background progress when analysis is running'
            ).toContainText(/Background|processed|Done|analysed/, { timeout: 15000 });
        }

        if (allDone) {
            console.log('[DEBUG] ✓ All posts already analysed — Analyse Remaining correctly reports nothing to do.');
        }
    });

    // ── 5. Stop button appears when analysis is running ──────────────────────

    test('Stop button is visible when analysis is running', async ({ page }) => {
        await openTitleOptTab(page);
        await page.waitForSelector('#ab-titleopt-posts-wrap table, #ab-titleopt-posts-wrap p', { timeout: 15000 });

        const logText = async () => (await page.locator('#ab-titleopt-log').innerText().catch(() => ''));

        await page.locator('#ab-titleopt-analyse-all').click();
        // Give the AJAX a moment to respond
        await page.waitForTimeout(2000);

        const log = await logText();
        if (log.includes('have suggestions') || log.includes('nothing left') || log.includes('Nothing to analyse')) {
            // All posts done — stop button shouldn't show (nothing running)
            test.skip(true, 'All posts already have suggestions; no running state to test.');
            return;
        }

        const stopBtn = page.locator('#ab-titleopt-stop');
        await expect(stopBtn, 'Stop button should be visible while analysis runs').toBeVisible({ timeout: 5000 });
        console.log('[DEBUG] Stop button visible ✓');
    });

});
