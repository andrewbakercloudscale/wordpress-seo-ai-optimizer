// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Blog page style', () => {

  test('renders dark hero and posts-section layout', async ({ page }) => {
    await page.goto('https://andrewbaker.ninja/blog/');

    // Dark hero must be present
    const hero = page.locator('.blog-hero');
    await expect(hero).toBeVisible();

    // Hero has dark background
    const bg = await hero.evaluate(el => getComputedStyle(el).backgroundColor);
    expect(bg).toBe('rgb(17, 17, 17)'); // #111

    // Title says "All Posts."
    await expect(page.locator('.blog-hero-title')).toHaveText('Blog: All Posts');

    // Subtitle mentions post count
    const sub = await page.locator('.blog-hero-sub').innerText();
    expect(sub).toMatch(/\d+ posts/);

    // Posts section with grey background
    const section = page.locator('section.posts-section');
    await expect(section).toBeVisible();

    // At least 10 post rows visible
    const rows = page.locator('.post-list .post-row');
    await expect(rows).toHaveCount(10);

    // Post rows have title links
    await expect(rows.first().locator('a')).toBeVisible();

    // Blue eye SVG present in first row
    const svg = rows.first().locator('.post-views svg');
    await expect(svg).toBeVisible();
    const fill = await svg.locator('path').getAttribute('fill');
    expect(fill).toBe('#3b82f6');

    // Pagination is rendered
    await expect(page.locator('.ab-pagination')).toBeVisible();

    // Old content-wrap layout must NOT be the primary container
    const oldWrap = page.locator('.content-wrap');
    await expect(oldWrap).toHaveCount(0);
  });

});
