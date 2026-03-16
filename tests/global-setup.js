const { chromium } = require('@playwright/test');

module.exports = async () => {
    const baseURL    = process.env.WP_BASE_URL;
    const cookiesRaw = process.env.WP_COOKIES;

    if (!baseURL || !cookiesRaw) {
        throw new Error('Missing env vars: WP_BASE_URL, WP_COOKIES — these are set by run-ui-tests.sh');
    }

    const c = JSON.parse(cookiesRaw);

    // Inject WordPress auth cookies directly — bypasses login form, 2FA, and
    // WPS Hide Login. Playwright never touches the login page at all.
    const browser = await chromium.launch();
    const context = await browser.newContext();

    await context.addCookies([
        { name: c.auth_name,  value: c.auth_value,  domain: c.domain, path: '/wp-admin',   httpOnly: true, secure: true, expires: c.expiration },
        { name: c.auth_name,  value: c.auth_value,  domain: c.domain, path: '/wp-content', httpOnly: true, secure: true, expires: c.expiration },
        { name: c.sec_name,   value: c.sec_value,   domain: c.domain, path: '/wp-admin',   httpOnly: true, secure: true, expires: c.expiration },
        { name: c.sec_name,   value: c.sec_value,   domain: c.domain, path: '/wp-content', httpOnly: true, secure: true, expires: c.expiration },
        { name: c.login_name, value: c.login_value, domain: c.domain, path: '/',           httpOnly: true, secure: true, expires: c.expiration },
    ]);

    // Verify we can reach wp-admin with these cookies
    const page = await context.newPage();
    await page.goto(`${baseURL}/wp-admin/`, { waitUntil: 'domcontentloaded' });

    if (!page.url().includes('/wp-admin/')) {
        throw new Error(`Cookie injection failed — ended up at: ${page.url()}`);
    }

    // Persist auth state for all tests — file is gitignored
    await context.storageState({ path: 'auth.json' });
    await browser.close();
};
