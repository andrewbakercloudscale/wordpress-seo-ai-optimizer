/**
 * reddit-post.js — opens Reddit r/Wordpress submit page and pre-fills the post.
 *
 * Usage:
 *   node reddit-post.js
 *
 * The browser opens headed so you can log in if needed, review the post, and hit Submit.
 * This script fills in the title and body — you click Post.
 */

const { chromium } = require('playwright');
const readline     = require('readline');

// ── Post content ─────────────────────────────────────────────────────────────

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

// ── Helpers ───────────────────────────────────────────────────────────────────

function prompt(question) {
    const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
    return new Promise(resolve => rl.question(question, ans => { rl.close(); resolve(ans); }));
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

// ── Main ──────────────────────────────────────────────────────────────────────

(async () => {
    console.log('\n🚀  Opening Reddit…\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 80,
        args: [
            '--disable-blink-features=AutomationControlled',
            '--no-sandbox',
        ],
    });
    const context = await browser.newContext({
        viewport: { width: 1280, height: 900 },
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        locale: 'en-US',
    });

    // Remove the webdriver flag that sites use to detect automation
    await context.addInitScript(() => {
        Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
    });

    const page = await context.newPage();

    // Go directly to Reddit native login — avoid Google/Apple OAuth (blocked in automation)
    console.log('👉  Please log in with your Reddit username + password (NOT Google sign-in).\n');
    await page.goto('https://www.reddit.com/login/', { waitUntil: 'domcontentloaded' });
    await sleep(2000);

    // Wait up to 3 minutes for login to complete
    const deadline = Date.now() + 3 * 60 * 1000;
    while (Date.now() < deadline) {
        await sleep(2000);
        const u = page.url();
        if (!u.includes('/login') && !u.includes('/register') && !u.includes('account/')) {
            console.log('✅  Login detected — navigating to submit page…');
            break;
        }
    }

    await page.goto('https://www.reddit.com/r/Wordpress/submit?type=self', { waitUntil: 'domcontentloaded' });
    await sleep(3000);

    console.log('📝  Filling in post details…\n');

    // ── Title ────────────────────────────────────────────────────────────────
    let titleFilled = false;
    const titleSelectors = [
        'textarea[placeholder="Title"]',
        'input[placeholder="Title"]',
        '[name="title"]',
        'textarea[id*="title"]',
        'input[id*="title"]',
        '.title-input textarea',
        '.title-input input',
    ];

    for (const sel of titleSelectors) {
        try {
            const el = await page.$(sel);
            if (el) {
                await el.click();
                await el.fill(TITLE);
                titleFilled = true;
                console.log('✅  Title filled');
                break;
            }
        } catch { /* try next */ }
    }

    if (!titleFilled) {
        console.log('⚠️  Could not auto-fill title. Please paste it manually:');
        console.log('─'.repeat(70));
        console.log(TITLE);
        console.log('─'.repeat(70));
    }

    await sleep(800);

    // ── Body (Reddit uses a contenteditable rich-text editor) ────────────────
    let bodyFilled = false;
    const bodySelectors = [
        'div[data-lexical-editor="true"]',
        '.public-DraftEditor-content',
        '[contenteditable="true"][role="textbox"]',
        'div[contenteditable="true"]',
        '.DraftEditor-root',
        'textarea[name="text"]',
    ];

    for (const sel of bodySelectors) {
        try {
            const el = await page.$(sel);
            if (el) {
                await el.click();
                await sleep(400);
                // Use clipboard paste for rich-text editors (most reliable method)
                await context.grantPermissions(['clipboard-read', 'clipboard-write']);
                await page.evaluate((text) => {
                    navigator.clipboard.writeText(text).catch(() => {});
                }, BODY);
                await sleep(300);
                await page.keyboard.press('Meta+a');   // select all (mac)
                await page.keyboard.press('Meta+v');   // paste
                await sleep(500);
                // Verify something appeared
                const val = await el.textContent();
                if (val && val.length > 20) {
                    bodyFilled = true;
                    console.log('✅  Body filled via clipboard paste');
                    break;
                }
            }
        } catch { /* try next */ }
    }

    if (!bodyFilled) {
        // Copy body to clipboard via xclip / pbcopy for manual paste
        console.log('⚠️  Could not auto-fill body. The body text has been printed below — copy & paste it:');
        console.log('\n' + '─'.repeat(70));
        console.log(BODY);
        console.log('─'.repeat(70) + '\n');
    }

    await sleep(500);

    console.log('\n✅  Done. Please review the post in the browser and click "Post" when ready.');
    console.log('   The browser will stay open. Press Ctrl+C here when you\'re finished.\n');

    // Keep alive until Ctrl+C
    await new Promise(() => {});
})();
