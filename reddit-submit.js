/**
 * reddit-submit.js — posts to r/Wordpress using old.reddit.com (simple HTML form).
 * Logs in, fills title + body, submits.
 */
const { chromium } = require('playwright');

const SUBREDDIT = 'Wordpress';

const TITLE = `I built a free WordPress SEO plugin with Claude/Gemini AI baked in — no Pro tier, no upsells, ever. Roast it.`;

const BODY = `I got fed up paying Yoast $99/year for features that felt half-finished, so I built my own.

**What it does:**

- Full meta/OG/schema/sitemap stack (the boring-but-necessary stuff)
- Auto-pipeline: when you publish a post, it runs in the background and writes your meta description, scores the post 0–100, generates ALT text for images, injects internal links, and adds a summary box — all without touching WP-Cron
- Bulk meta generation for your whole site using either **Anthropic Claude or Google Gemini** (your API key, your cost — about $1.50 for 200 posts with Claude Sonnet)
- Readability scoring (pure PHP, no AI call needed)
- robots.txt editor with AI bot blocking built in
- \`llms.txt\` support for AI crawler guidance
- Performance tab: local font download (GDPR), defer JS, HTML/CSS/JS minification, mixed content fixer

**What it doesn't do:**

No Pro tier. No upsell modals. No phoning home. No "upgrade to unlock" anything. It's fully free and open source on WordPress.org.

**The honest caveats:**

- You need to deactivate Yoast/RankMath — can't run two SEO plugins
- AI features need an API key (Anthropic or Google). It's not magic-free.
- It's new — published ~3 weeks ago, fewer than 10 active installs so far, zero reviews
- Built by one person (me), so if something breaks, I want to know

**Why I'm posting:**

I want real users. Not installs for vanity metrics — I want people who'll actually tell me what's broken or missing.

Plugin: https://wordpress.org/plugins/cloudscale-seo-ai-optimizer/

Happy to answer anything.`;

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

(async () => {
    const browser = await chromium.launch({
        headless: false,
        slowMo: 60,
        args: ['--disable-blink-features=AutomationControlled'],
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
    });
    await context.addInitScript(() => {
        Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    });

    const page = await context.newPage();

    // ── Step 1: Login via old Reddit ──────────────────────────────────────────
    console.log('\n🔐  Opening old.reddit.com login…');
    console.log('    Log in with your Reddit username + password.\n');

    await page.goto('https://old.reddit.com/login', { waitUntil: 'domcontentloaded' });
    await sleep(1500);

    // Wait for successful login (URL changes away from /login)
    const deadline = Date.now() + 3 * 60 * 1000;
    while (Date.now() < deadline) {
        await sleep(1500);
        const u = page.url();
        if (!u.includes('/login')) {
            console.log('✅  Logged in!');
            break;
        }
    }

    // ── Step 2: Navigate to submit page ──────────────────────────────────────
    console.log('\n📝  Opening submit page…');
    const submitUrl = `https://old.reddit.com/r/${SUBREDDIT}/submit?selftext=true`;
    await page.goto(submitUrl, { waitUntil: 'domcontentloaded' });
    await sleep(2000);

    // ── Step 3: Fill title ────────────────────────────────────────────────────
    const titleInput = await page.$('#title');
    if (titleInput) {
        await titleInput.click();
        await titleInput.fill(TITLE);
        console.log('✅  Title filled');
    } else {
        console.log('⚠️  Title input not found — check the browser');
    }

    await sleep(500);

    // ── Step 4: Fill body ─────────────────────────────────────────────────────
    const bodyInput = await page.$('#text');
    if (bodyInput) {
        await bodyInput.click();
        await bodyInput.fill(BODY);
        console.log('✅  Body filled');
    } else {
        console.log('⚠️  Body textarea not found — check the browser');
    }

    await sleep(800);

    // ── Step 5: Submit ────────────────────────────────────────────────────────
    console.log('\n🚀  Submitting post…');
    const submitBtn = await page.$('button[type="submit"], input[type="submit"][value*="submit"], #submit-text-based');
    if (submitBtn) {
        await submitBtn.click();
        await sleep(4000);
        const finalUrl = page.url();
        if (finalUrl.includes('/comments/') || finalUrl.includes('/r/Wordpress')) {
            console.log('\n🎉  POST SUBMITTED!');
            console.log('    URL: ' + finalUrl);
        } else {
            console.log('\n⚠️  Submitted — check the browser for result.');
            console.log('    URL: ' + finalUrl);
        }
    } else {
        console.log('⚠️  Submit button not found. Review the browser and click Post manually.');
    }

    console.log('\nPress Ctrl+C to close the browser when done.\n');
    await new Promise(() => {}); // keep alive
})();
