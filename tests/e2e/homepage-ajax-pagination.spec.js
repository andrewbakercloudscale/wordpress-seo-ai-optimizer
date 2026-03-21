// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Homepage AJAX pagination', () => {

  test('Recent Posts: clicking page 2 does not reload the page', async ({ page }) => {
    const consoleErrors = [];
    page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()); });

    await page.goto('https://andrewbaker.ninja/');

    // Capture the first post title before paginating
    const firstTitle = await page.locator('#recent-posts-wrap .post-row a').first().innerText();

    // Listen for main-frame navigation only
    let navigated = false;
    let navigatedTo = '';
    page.on('framenavigated', frame => {
      if (frame === page.mainFrame()) {
        navigated = true;
        navigatedTo = frame.url();
      }
    });

    // Click the page 2 link in the recent posts pagination
    const page2Link = page.locator('#recent-posts-wrap .ab-pagination a.page-numbers').filter({ hasText: '2' }).first();
    await expect(page2Link).toBeVisible();
    await page2Link.click();

    // Wait for the wrap to be updated (opacity restored = request complete)
    await expect(page.locator('#recent-posts-wrap')).toHaveCSS('opacity', '1', { timeout: 8000 });

    // The first post title should have changed (we're on page 2 now)
    const newFirstTitle = await page.locator('#recent-posts-wrap .post-row a').first().innerText();
    expect(newFirstTitle).not.toBe(firstTitle);

    // No full page navigation should have occurred
    if (navigated) console.log('NAVIGATED TO:', navigatedTo);
    if (consoleErrors.length) console.log('JS ERRORS:', consoleErrors);
    expect(navigated).toBe(false);

    // data-page should be updated to 2
    const dataPage = await page.locator('#recent-posts-wrap').getAttribute('data-page');
    expect(dataPage).toBe('2');
  });

  test('Top Posts: clicking page 2 does not reload the page', async ({ page }) => {
    await page.goto('https://andrewbaker.ninja/');

    const firstTitle = await page.locator('#top-posts-wrap .top-post-text a').first().innerText();

    let navigated = false;
    let navigatedTo = '';
    page.on('framenavigated', frame => {
      if (frame === page.mainFrame()) { navigated = true; navigatedTo = frame.url(); }
    });

    const page2Link = page.locator('#top-posts-wrap .ab-pagination a.page-numbers').filter({ hasText: '2' }).first();
    await expect(page2Link).toBeVisible();
    await page2Link.click();

    await expect(page.locator('#top-posts-wrap')).toHaveCSS('opacity', '1', { timeout: 8000 });

    const newFirstTitle = await page.locator('#top-posts-wrap .top-post-text a').first().innerText();
    expect(newFirstTitle).not.toBe(firstTitle);

    if (navigated) console.log('TOP NAVIGATED TO:', navigatedTo);
    expect(navigated).toBe(false);

    const dataPage = await page.locator('#top-posts-wrap').getAttribute('data-page');
    expect(dataPage).toBe('2');
  });

  test('Recent Posts: paginating back to page 1 works', async ({ page }) => {
    await page.goto('https://andrewbaker.ninja/');

    // Go to page 2
    await page.locator('#recent-posts-wrap .ab-pagination a.page-numbers').filter({ hasText: '2' }).first().click();
    await expect(page.locator('#recent-posts-wrap')).toHaveCSS('opacity', '1', { timeout: 8000 });

    // Go back to page 1 via prev («) or page 1 link
    const prevLink = page.locator('#recent-posts-wrap .ab-pagination a.page-numbers').first();
    await prevLink.click();
    await expect(page.locator('#recent-posts-wrap')).toHaveCSS('opacity', '1', { timeout: 8000 });

    const dataPage = await page.locator('#recent-posts-wrap').getAttribute('data-page');
    expect(parseInt(dataPage)).toBeLessThan(2);
  });

});
