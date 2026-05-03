/**
 * Quick debug script: navigate to SEO Site Audit tab, click Run Audit,
 * capture console errors and screenshots to diagnose why button does nothing.
 *
 * Usage: node tests/debug-audit-btn.js
 */
const { chromium } = require('playwright');
const fs   = require('fs');
const path = require('path');

const ENV_TEST = path.join(__dirname, '../../.env.test');
const BASE_URL  = 'https://andrewbaker.ninja';
const PLUGIN_URL = `${BASE_URL}/cleanshirt/admin.php?page=cs-seo-optimizer`;

function readEnv(file) {
    const out = {};
    fs.readFileSync(file, 'utf8').split('\n').forEach(l => {
        const m = l.match(/^([^#=]+)=(.*)$/);
        if (m) out[m[1].trim()] = m[2].trim();
    });
    return out;
}

async function getSession(env) {
    const resp = await fetch(env.CSDT_TEST_SESSION_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `secret=${encodeURIComponent(env.CSDT_TEST_SECRET)}&role=${encodeURIComponent(env.CSDT_TEST_ROLE)}&ttl=3600`,
    });
    return resp.json();
}

(async () => {
    const env = readEnv(ENV_TEST);
    console.log('Getting test session...');
    const sess = await getSession(env);
    if (!sess.session_token) {
        console.error('Session failed:', JSON.stringify(sess));
        process.exit(1);
    }
    console.log('Session OK for:', sess.username);

    const expiry = sess.expires_at;
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();

    await context.addCookies([
        { name: sess.secure_auth_cookie_name, value: sess.secure_auth_cookie, domain: sess.cookie_domain, path: '/', httpOnly: true, secure: true, expires: expiry },
        { name: sess.logged_in_cookie_name,   value: sess.logged_in_cookie,   domain: sess.cookie_domain, path: '/', httpOnly: true, secure: true, expires: expiry },
    ]);

    const page = await context.newPage();
    const consoleLogs = [];
    page.on('console', msg => {
        const type = msg.type();
        if (type === 'error' || type === 'warn') {
            consoleLogs.push(`[${type.toUpperCase()}] ${msg.text()}`);
        }
    });
    page.on('pageerror', err => consoleLogs.push(`[PAGEERROR] ${err.message}`));

    console.log('Loading plugin settings page...');
    await page.goto(PLUGIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 });
    await page.screenshot({ path: path.join(__dirname, 'debug-audit-1-loaded.png') });
    console.log('Screenshot 1: page loaded');

    // Check where we landed
    console.log('Current URL:', page.url());
    const pageTitle = await page.title();
    console.log('Page title:', pageTitle);

    // Click the SEO Site Audit tab
    const tab = await page.$('[data-tab="siteaudit"]');
    if (!tab) {
        console.error('Tab not found! Saving page HTML...');
        const html = await page.content();
        fs.writeFileSync(path.join(__dirname, 'debug-audit-page.html'), html.substring(0, 5000));
        console.log('First 5000 chars saved to debug-audit-page.html');
        await browser.close();
        process.exit(1);
    }
    await tab.click();
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(__dirname, 'debug-audit-2-tab-active.png') });
    console.log('Screenshot 2: audit tab active');

    // Check the Run Audit button
    const btn = await page.$('#cs-run-audit-btn');
    if (!btn) {
        console.error('Run Audit button NOT found in DOM!');
        const html = await page.content();
        fs.writeFileSync(path.join(__dirname, 'debug-audit-page.html'), html);
        console.log('Page HTML saved to debug-audit-page.html');
        await browser.close();
        process.exit(1);
    }
    console.log('Run Audit button found');

    // Check for JS errors before click
    if (consoleLogs.length > 0) {
        console.log('JS errors/warnings BEFORE click:');
        consoleLogs.forEach(l => console.log(' ', l));
    }

    // Click Run Audit
    console.log('Clicking Run Audit...');
    await btn.click();
    await page.waitForTimeout(1000);

    // Check button state immediately after click
    const btnText = await btn.textContent();
    const btnDisabled = await btn.getAttribute('disabled');
    console.log(`Button state 1s after click: text="${btnText?.trim()}", disabled=${btnDisabled}`);

    if (btnText?.includes('Run Audit') && !btnDisabled) {
        console.log('⚠ Button did NOT change state — event listener may not be attached');
    } else {
        console.log('✓ Button changed state — event listener fired OK');
    }

    await page.screenshot({ path: path.join(__dirname, 'debug-audit-3-after-click.png') });
    console.log('Screenshot 3: after click');

    // Wait up to 50s for audit to complete
    console.log('Waiting up to 50s for audit to complete...');
    try {
        await page.waitForFunction(
            () => document.getElementById('cs-run-audit-btn')?.textContent?.includes('Run Audit') &&
                  !document.getElementById('cs-run-audit-btn')?.disabled,
            { timeout: 50000 }
        );
        console.log('✓ Audit completed');
    } catch (e) {
        console.log('Timed out waiting for audit to complete');
    }

    await page.screenshot({ path: path.join(__dirname, 'debug-audit-4-completed.png') });
    console.log('Screenshot 4: final state');

    // JS errors after
    if (consoleLogs.length > 0) {
        console.log('All JS errors/warnings during test:');
        consoleLogs.forEach(l => console.log(' ', l));
    } else {
        console.log('No JS errors detected');
    }

    // Check what's visible in results
    const resultsVisible = await page.isVisible('#cs-audit-results');
    const runningVisible = await page.isVisible('#cs-audit-running');
    console.log(`Results section visible: ${resultsVisible}`);
    console.log(`Running spinner visible: ${runningVisible}`);

    await browser.close();
    console.log('Done. Check debug-audit-*.png for screenshots.');
})();
