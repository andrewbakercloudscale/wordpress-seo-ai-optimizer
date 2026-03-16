const { test, expect } = require('@playwright/test');

const PLUGIN_PAGE = '/wp-admin/tools.php?page=cs-seo-optimizer';

// All tabs: [data-tab value, expected pane id]
const TABS = [
    { tab: 'seo',     pane: 'ab-pane-seo'     },
    { tab: 'aitools', pane: 'ab-pane-aitools'  },
    { tab: 'sitemap', pane: 'ab-pane-sitemap'  },
    { tab: 'perf',    pane: 'ab-pane-perf'     },
    { tab: 'catfix',  pane: 'ab-pane-catfix'   },
    { tab: 'batch',   pane: 'ab-pane-batch'    },
];

// Explain buttons: [tab to activate first (null = default SEO tab), button id suffix,
//                   existenceOnly: true = button is inside API-key-gated hidden section]
const EXPLAIN_BUTTONS = [
    // SEO tab (default active)
    { tab: null,       id: 'identity'                    },
    { tab: null,       id: 'person'                      },
    { tab: null,       id: 'ai'                          },
    { tab: null,       id: 'auto_pipeline', existenceOnly: true }, // inside API-key-gated section
    // AI Tools tab
    { tab: 'aitools',  id: 'updateposts'                 },
    { tab: 'aitools',  id: 'alttext'                     },
    { tab: 'aitools',  id: 'summary'                     },
    { tab: 'aitools',  id: 'rc_settings'                 },
    { tab: 'aitools',  id: 'rc_table'                    },
    // Sitemap tab
    { tab: 'sitemap',  id: 'features'                    },
    { tab: 'sitemap',  id: 'sitemap'                     },
    { tab: 'sitemap',  id: 'robots'                      },
    { tab: 'sitemap',  id: 'llms'                        },
    // Performance tab
    { tab: 'perf',     id: 'https',         existenceOnly: true }, // inside API-key-gated section
    { tab: 'perf',     id: 'perf'                        },
    { tab: 'perf',     id: 'render'                      },
    // Batch tab
    { tab: 'batch',    id: 'schedule'                    },
    { tab: 'batch',    id: 'lastrun'                     },
    // Categories tab
    { tab: 'catfix',   id: 'catfix'                      },
    { tab: 'catfix',   id: 'cathealth'                   },
    { tab: 'catfix',   id: 'catdrift'                    },
];

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Change a text input, save, reload, assert value persisted, then restore.
 * saveButtonName: the exact button value text (e.g. 'Save SEO Settings').
 */
async function testTextFieldPersists(page, paneId, fieldSelector, testValue, saveButtonName) {
    const field = page.locator(`#${paneId}`).locator(fieldSelector);
    const original = await field.inputValue();

    await field.fill(testValue);
    await page.getByRole('button', { name: saveButtonName }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });

    await page.goto(PLUGIN_PAGE);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    if (paneId !== 'ab-pane-seo') {
        await page.click(`[data-tab="${paneId.replace('ab-pane-', '')}"]`);
    }

    await expect(page.locator(`#${paneId}`).locator(fieldSelector)).toHaveValue(testValue);

    const restored = page.locator(`#${paneId}`).locator(fieldSelector);
    await restored.fill(original);
    await page.getByRole('button', { name: saveButtonName }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
}

/**
 * Toggle a checkbox, save, reload, assert new state, then restore.
 */
async function testCheckboxPersists(page, paneId, fieldSelector, saveButtonName) {
    const field    = page.locator(`#${paneId}`).locator(fieldSelector);
    const original = await field.isChecked();

    if (original) { await field.uncheck(); } else { await field.check(); }
    await page.getByRole('button', { name: saveButtonName }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });

    await page.goto(PLUGIN_PAGE);
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    const tabId = paneId.replace('ab-pane-', '');
    if (tabId !== 'seo') await page.click(`[data-tab="${tabId}"]`);

    const reloaded = page.locator(`#${paneId}`).locator(fieldSelector);
    if (original) { await expect(reloaded).not.toBeChecked(); }
    else          { await expect(reloaded).toBeChecked(); }

    if (original) { await reloaded.check(); } else { await reloaded.uncheck(); }
    await page.getByRole('button', { name: saveButtonName }).first().click();
    await page.waitForLoadState('domcontentloaded');
    await page.waitForSelector('.ab-tabs', { timeout: 15000 });
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('SEO AI Optimizer — admin page regression', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto(PLUGIN_PAGE);
        await page.waitForLoadState('domcontentloaded');
        // Confirm the plugin page loaded — not redirected elsewhere
        await page.waitForSelector('.ab-tabs', { timeout: 15000 });
    });

    // ── 1. Page header and version ──────────────────────────────────────────
    test('page title and version badge are visible', async ({ page }) => {
        const h1 = page.locator('h1').first();
        await expect(h1).toContainText('CloudScale SEO AI Optimizer');
        await expect(h1).toContainText(/v\d+\.\d+\.\d+/);
    });

    test('"Totally Free" author badge is present', async ({ page }) => {
        await expect(page.getByText('Totally Free by AndrewBaker.Ninja')).toBeVisible();
    });

    // ── 2. All tabs present ─────────────────────────────────────────────────
    test('all 6 tab buttons are visible', async ({ page }) => {
        for (const { tab } of TABS) {
            await expect(page.locator(`[data-tab="${tab}"]`)).toBeVisible();
        }
    });

    // ── 3. Each tab switches its pane ───────────────────────────────────────
    for (const { tab, pane } of TABS) {
        test(`clicking "${tab}" tab activates its pane`, async ({ page }) => {
            await page.click(`[data-tab="${tab}"]`);
            await expect(page.locator(`#${pane}`)).toHaveClass(/active/);
            await expect(page.locator(`[data-tab="${tab}"]`)).toHaveClass(/active/);
        });
    }

    // ── 4. Explain buttons open and close their modals ──────────────────────
    for (const { tab, id, existenceOnly } of EXPLAIN_BUTTONS) {
        test(`explain button "${id}" opens and closes its modal`, async ({ page }) => {
            if (tab) await page.click(`[data-tab="${tab}"]`);

            const btn   = page.locator(`#ab-explain-btn-${id}`);
            const modal = page.locator(`#ab-explain-modal-${id}`);

            // All buttons must exist in the DOM — catches regressions where a button is removed.
            await expect(btn).toBeAttached();
            await expect(modal).toBeAttached();

            if (existenceOnly) {
                // Button is inside an API-key-gated section that's hidden until an API key
                // is configured. The modal is also inside that hidden container, so even
                // setting display:flex on it won't make it visible (parent display:none wins).
                // We only verify existence here; the click/open/close is tested on other buttons.
                return;
            }

            await expect(modal).toBeHidden();
            await btn.evaluate(el => el.click());
            await expect(modal).toBeVisible();

            await modal.getByText('Got it').click();
            await expect(modal).toBeHidden();
        });
    }

    test('explain modal closes with the X button', async ({ page }) => {
        const btn   = page.locator('#ab-explain-btn-identity');
        const modal = page.locator('#ab-explain-modal-identity');

        await btn.evaluate(el => el.click());
        await expect(modal).toBeVisible();

        await modal.getByText('✕').click();
        await expect(modal).toBeHidden();
    });

    // ── 5. Core form fields exist ───────────────────────────────────────────
    test('SEO tab contains core settings fields', async ({ page }) => {
        await expect(page.locator('input[name="cs_seo_options[site_name]"]')).toBeAttached();
        await expect(page.locator('input[name="cs_seo_options[title_suffix]"]')).toBeAttached();
        await expect(page.locator('input[name="cs_seo_options[site_lang]"]')).toBeAttached();
    });

    // ── 6. Form save and restore — one field per tab ────────────────────────
    test('SEO tab: locale field saves and persists after reload', async ({ page }) => {
        await testTextFieldPersists(
            page,
            'ab-pane-seo',
            'input[name="cs_seo_options[site_lang]"]',
            'en-ZZ',
            'Save SEO Settings'
        );
    });

    test('SEO tab: AI min_chars field saves and persists after reload', async ({ page }) => {
        await testTextFieldPersists(
            page,
            'ab-pane-seo',
            'input[name="cs_seo_ai_options[min_chars]"]',
            '141',
            'Save AI Settings'
        );
    });

    test('Sitemap tab: sitemap_exclude field saves and persists after reload', async ({ page }) => {
        await page.click('[data-tab="sitemap"]');
        await testTextFieldPersists(
            page,
            'ab-pane-sitemap',
            'textarea[name="cs_seo_options[sitemap_exclude]"]',
            'playwright-test-marker',
            'Save Sitemap Settings'
        );
    });

    test('Performance tab: defer_js_excludes field saves and persists after reload', async ({ page }) => {
        await page.click('[data-tab="perf"]');
        await testTextFieldPersists(
            page,
            'ab-pane-perf',
            'textarea[name="cs_seo_options[defer_js_excludes]"]',
            'playwright-test-marker',
            'Save Performance Settings'
        );
    });

});
