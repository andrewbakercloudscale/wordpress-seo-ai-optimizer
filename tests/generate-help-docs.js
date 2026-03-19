/**
 * generate-help-docs.js
 *
 * Screenshots every individual panel of the CloudScale SEO AI Optimizer admin,
 * uploads them to the WordPress Media Library, and creates/updates a fully
 * detailed "Help & Documentation" WordPress Page.
 *
 * Run via:  bash generate-help-docs.sh
 */

'use strict';

const { chromium } = require('playwright-core');
const fs     = require('fs');
const path   = require('path');
const https  = require('https');
const http   = require('http');
const { execSync } = require('child_process');

// ── Environment ───────────────────────────────────────────────────────────────
const BASE_URL  = process.env.WP_BASE_URL;
const COOKIES   = JSON.parse(process.env.WP_COOKIES);
const REST_USER = process.env.WP_REST_USER;
const REST_PASS = process.env.WP_REST_PASS;
const DOCS_DIR  = process.env.WP_DOCS_DIR;

if (!BASE_URL || !COOKIES || !REST_USER || !REST_PASS || !DOCS_DIR) {
    console.error('ERROR: Missing required environment variables. Run via generate-help-docs.sh');
    process.exit(1);
}

const PLUGIN_PAGE = `${BASE_URL}/wp-admin/tools.php?page=cs-seo-optimizer`;
const SCREENSHOTS = path.join(DOCS_DIR, 'screenshots');
const REST_BASE   = `${BASE_URL}/wp-json/wp/v2`;
const AUTH_HEADER = 'Basic ' + Buffer.from(`${REST_USER}:${REST_PASS}`).toString('base64');

// ── Panel screenshot plan ─────────────────────────────────────────────────────
// cardClass: the CSS class on the ab-zone-card div
// expand: click the Show Details toggle before screenshotting
const PANELS = [
    // SEO Tab
    { tab: 'seo',     cardClass: 'ab-card-identity',        file: 'panel-identity.png',      label: 'Site Identity & Schema',         expand: false },
    { tab: 'seo',     cardClass: 'ab-card-person',          file: 'panel-person.png',         label: 'Person Schema',                  expand: false },
    { tab: 'seo',     cardClass: 'ab-card-ai',              file: 'panel-ai.png',             label: 'AI Settings',                    expand: false },
    // ab-card-auto-pipeline is hidden until an API key is configured — documented inline, no screenshot

    // AI Tools Tab — expand:true panels auto-load rows; trimRows hides rows >2; rcTrigger calls rcLoadTable
    { tab: 'aitools', cardClass: 'ab-card-update-posts',    file: 'panel-metadesc.png',       label: 'AI Meta Description Writer',     expand: true,  trimRows: true },
    { tab: 'aitools', cardClass: 'ab-card-alt',             file: 'panel-alttext.png',        label: 'AI ALT Text Generator',          expand: true,  trimRows: true },
    { tab: 'aitools', cardClass: 'ab-card-summary',         file: 'panel-summary.png',        label: 'AI Summary Box',                 expand: true,  trimRows: true },
    { tab: 'aitools', cardClass: 'ab-card-rc-settings-card',file: 'panel-rc-settings.png',    label: 'Related Articles — Settings',    expand: true  },
    { tab: 'aitools', cardClass: 'ab-card-rc-table',        file: 'panel-rc-table.png',       label: 'Related Articles — Management',  expand: true,  trimRows: true, rcTrigger: true },
    // Sitemap & Robots Tab
    { tab: 'sitemap', cardClass: 'ab-card-features',        file: 'panel-features.png',       label: 'SEO Features',                   expand: false },
    { tab: 'sitemap', cardClass: 'ab-card-sitemap-settings',file: 'panel-sitemap.png',        label: 'XML Sitemap Settings',           expand: false },
    { tab: 'sitemap', cardClass: 'ab-card-robots',          file: 'panel-robots.png',         label: 'Robots.txt Editor',              expand: false },
    { tab: 'sitemap', cardClass: 'ab-card-llms',            file: 'panel-llms.png',           label: 'llms.txt',                       expand: false },
    // Performance Tab
    // ab-card-https is hidden until an API key is configured — documented inline, no screenshot

    { tab: 'perf',    cardClass: 'ab-card-fonts',           file: 'panel-fonts.png',          label: 'Font Optimiser',                 expand: false },
    { tab: 'perf',    cardClass: 'ab-card-render',          file: 'panel-render.png',         label: 'JS, CSS & HTML Optimisation',    expand: false },
    // Categories Tab
    { tab: 'catfix',  cardClass: 'ab-card-catfix',          file: 'panel-catfix.png',         label: 'Category Fixer',                 expand: false, preScan: true, trimRows: true },
    { tab: 'catfix',  cardClass: 'ab-card-cathealth',       file: 'panel-cathealth.png',      label: 'Category Health',                expand: false },
    { tab: 'catfix',  cardClass: 'ab-card-catdrift',        file: 'panel-catdrift.png',       label: 'Category Drift Detection',       expand: false },
    // Batch Tab
    { tab: 'batch',   cardClass: 'ab-card-schedule',        file: 'panel-schedule.png',       label: 'Batch Schedule',                 expand: false },
    { tab: 'batch',   cardClass: 'ab-card-lastrun',         file: 'panel-lastrun.png',        label: 'Last Run Log',                   expand: false },
];

// ── Documentation content ─────────────────────────────────────────────────────
// Keyed by cardClass. Write in plain HTML — goes directly into a WordPress page.
const DOCS = {

'ab-card-identity': `
<p>When someone searches for your name or your site on Google, how does Google decide what to show? It reads structured signals embedded in your pages — your site name, your homepage title, your preferred language, your social profiles. Without those signals, Google guesses, and it often gets things wrong. The <strong>Site Identity</strong> panel is where you set the record straight.</p>
<p>Everything you configure here is automatically embedded into every page on your site as meta tags, OpenGraph tags (which control how your pages look when shared on social media), and JSON-LD structured data (which Google reads to build rich search results). You only configure this once, and the plugin handles the rest.</p>
<ul>
<li><strong>Site name</strong> — Your website's name as it should appear in Google Search results and social media link previews. This is the brand name Google associates with your content — make it consistent with how you refer to your site everywhere else.</li>
<li><strong>Locale</strong> — Your language and region code, e.g. <code>en-US</code>, <code>en-GB</code>, <code>fr-FR</code>. This tells Google which language audience your content is written for, helping it serve your pages to the right searchers in the right country.</li>
<li><strong>Title suffix</strong> — Text appended after every page title in browser tabs and search results, e.g. <code> | Andrew Baker</code>. When someone has ten tabs open, your brand name in the tab title is the only way they recognise which tab is yours.</li>
<li><strong>Twitter handle</strong> — Your Twitter/X username (including the @), e.g. <code>@yourusername</code>. Added to the <code>twitter:site</code> meta tag so Twitter attributes shared content to your account and shows it correctly in Cards.</li>
<li><strong>Home title</strong> — The specific SEO title for your homepage. Your homepage is usually your most important page for brand searches — write a title that includes your name or brand and your main topic area, e.g. <em>"Andrew Baker — Cloud Architecture &amp; Technology Leadership"</em>.</li>
<li><strong>Home description</strong> — The meta description for your homepage. This is the sentence or two that appears under your homepage link in Google Search results. It should explain who you are and what value your site provides to a first-time visitor.</li>
<li><strong>Default OG image URL</strong> — The fallback image shown when any of your pages are shared on Facebook, LinkedIn, WhatsApp, or Slack and that page has no featured image of its own. Use a branded image at 1200 × 630 pixels. A consistent, professional-looking default image makes every shared link from your site look intentional rather than blank.</li>
</ul>`,

'ab-card-person': `
<p>Have you ever noticed how some people have a "Knowledge Panel" on Google — the box on the right side of search results that shows their photo, title, and links to their social profiles? That's powered by Person structured data. Even if you never reach Knowledge Panel status, telling Google who you are helps it connect your articles to your identity, which strengthens your E-E-A-T signals (Experience, Expertise, Authoritativeness, Trustworthiness) — one of Google's key quality criteria for ranking content, especially in technical and professional subject areas.</p>
<p>The <strong>Person Schema</strong> panel embeds this identity information into your pages as JSON-LD structured data. Nothing visible is added to your pages — it sits in the page source where Google can read it. The key is the <strong>sameAs</strong> field: by linking to your LinkedIn, GitHub, Twitter/X, and other profiles, you give Google the cross-references it needs to confirm that the "Andrew Baker" who wrote this post is the same "Andrew Baker" on LinkedIn — and that they're a real, credible person.</p>
<ul>
<li><strong>Full name</strong> — Your name exactly as you want it attributed across the web. Use the same form consistently everywhere.</li>
<li><strong>Job title</strong> — Your professional title, e.g. "Chief Information Officer" or "Cloud Architect". Appears in Person JSON-LD and can show in Google rich results.</li>
<li><strong>Profile URL</strong> — The canonical URL for your identity — usually your homepage. Google uses this as the definitive identifier for you as a person.</li>
<li><strong>Person image URL</strong> — A URL to your headshot or profile photo. A square image of at least 400 × 400 pixels works best. Google can use this in Knowledge Panels and author rich results.</li>
<li><strong>sameAs URLs (one per line)</strong> — Links to your profiles on LinkedIn, GitHub, Twitter/X, Wikipedia, Google Scholar, or any other authoritative platform. Each link is a cross-reference Google can verify. The more authoritative the profiles you list, the stronger the identity signal — LinkedIn and GitHub are especially valuable for technical authors.</li>
</ul>`,

'ab-card-ai': `
<p>All the AI features in this plugin — meta description generation, ALT text writing, article summaries, SEO scoring, and category analysis — run through a single connection to your chosen AI provider. This plugin does not run its own AI service and does not charge for AI usage. You bring your own API key from either <strong>Anthropic Claude</strong> or <strong>Google Gemini</strong>, and the plugin sends your content directly to that provider's API. Your API key is stored in your WordPress database and never shared with anyone.</p>
<p>The cost of running AI through your own API key is typically very small. For a site with 100 posts, generating meta descriptions for all of them using Claude Haiku costs less than $0.10. Google Gemini has a free tier that is sufficient for most personal blogs. There is no subscription, no per-site licence, and no hidden fee imposed by this plugin — you pay the AI provider directly, and only for what you use.</p>
<ul>
<li><strong>AI provider</strong> — Choose between Anthropic Claude and Google Gemini:
  <ul>
    <li><strong>Anthropic Claude:</strong> get a free API key at <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a> → sign up → API Keys → Create Key. Claude Sonnet 4.6 is the recommended default — fast and excellent at writing natural, accurate meta descriptions.</li>
    <li><strong>Google Gemini:</strong> get a free API key at <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a> → sign in with your Google account → Create API Key. Gemini 2.0 Flash has a generous free tier and is a strong choice for bulk processing on a budget.</li>
  </ul>
</li>
<li><strong>API key</strong> — Paste your key here. It is stored in your WordPress database and sent only to the chosen provider's API endpoint when you trigger generation. It is never transmitted to this plugin's author or any third party.</li>
<li><strong>Model</strong> — The AI model to use for generation. The plugin provides a dropdown of current, stable models for each provider. If you want to use a specific model not in the list, choose "Custom model ID" and type the model identifier. For Anthropic: <code>claude-sonnet-4-6</code> is the recommended default — use <code>claude-haiku-4-5-20251001</code> if you have a very large site and want faster, cheaper processing, or <code>claude-opus-4-6</code> for the highest quality output. For Gemini: <code>gemini-2.0-flash</code> is the stable recommended choice.</li>
<li><strong>Meta description character range (min / max)</strong> — Google shows meta descriptions of up to approximately 155–160 characters before truncating them with "…". The plugin defaults to 140–155 characters, which reliably fits within Google's display limit. If your descriptions are being cut off in search results, lower the max. If they look too short, raise the min.</li>
<li><strong>ALT text excerpt length</strong> — When generating image ALT text, the AI receives a short excerpt of the surrounding post content to understand what the image is illustrating. The default of 600 characters gives the AI enough context without wasting API tokens. Increase this for posts with images that are only explained later in the article.</li>
</ul>
<p>Always click <strong>Test Key</strong> after adding your API key and before running any bulk operations. This makes a minimal API call to confirm the key is valid, the provider account is active, and the selected model is available.</p>`,

'ab-card-auto-pipeline': `
<p>If you're starting from scratch with a new post, the Auto Pipeline means you never have to think about SEO again. Write your post, hit Publish, and within seconds the plugin has generated your meta description, scored your content, and created your AI summary box — all without you touching anything. This is the "set it and forget it" approach to keeping your site SEO-optimised as you publish.</p>
<p>The pipeline runs as a background HTTP request immediately after WordPress saves the post, so the Publish button itself does not feel slow. Each step runs independently — if the meta description generation succeeds but the SEO scoring fails (e.g. due to a temporary API timeout), the description is still saved. The pipeline is designed to be safe: by default, it only fills in missing content and will not overwrite descriptions you've already written manually.</p>
<ul>
<li><strong>Enable Auto Pipeline on first publish</strong> — When ticked, every post that goes from any non-published status to Published will automatically be processed. New posts only — it does not re-run on edits to already-published posts unless "Re-run on update" is also enabled.</li>
<li><strong>Re-run on update</strong> — When ticked, the pipeline re-runs every time you save an already-published post. Use this if you frequently make major content updates and want the AI content to stay current. Be aware that enabling this will regenerate AI content on every save, which uses API credits.</li>
<li><strong>Overwrite existing descriptions</strong> — When ticked, the pipeline regenerates the meta description even if one already exists. Leave unticked (the default) to protect manually written or previously generated descriptions.</li>
</ul>
<p><strong>Note on timing:</strong> The pipeline fires a background HTTP request, so it won't block your post save. However, if you check the meta description immediately after publishing, it may not have been written yet — wait a few seconds and refresh the post.</p>`,

'ab-card-update-posts': `
<p>The text snippet that appears under your page title in Google Search results — the two or three lines that describe what the page is about — is called the meta description. It is often the deciding factor in whether a searcher clicks your result or the one below it. When you don't write one, Google generates its own, usually by pulling a random sentence from your page content. That sentence is rarely your best pitch to a potential reader.</p>
<p>The <strong>AI Meta Description Writer</strong> solves this problem in bulk. It shows you every post and page on your site, the current status of each meta description, and lets you generate AI-written descriptions for all of them in a single operation. The AI reads your post title and content, then writes a compelling, correctly-sized description in your site's voice. For a site with 100 posts, this typically takes 2–3 minutes and costs less than $0.10 in API fees.</p>
<p><strong>Understanding the status badges:</strong></p>
<ul>
<li><strong>No AI description</strong> — No meta description has been written for this post. These are the highest priority to fix — Google is currently writing its own snippet for these pages.</li>
<li><strong>✓ [number]c</strong> (green) — A meta description exists and its length is within your configured character range. No action needed.</li>
<li><strong>Short · [number]c</strong> (amber) — A description exists but is shorter than your minimum. It may appear thin in search results.</li>
<li><strong>Long · [number]c</strong> (amber) — A description exists but exceeds your maximum. Google will truncate it with "…" in search results.</li>
<li><strong>✦ Generated · [number]c</strong> (blue) — A description was just generated in this session. It has been saved but you haven't reloaded the page yet.</li>
</ul>
<p><strong>What the bulk action buttons do:</strong></p>
<ul>
<li><strong>Generate Missing</strong> — The recommended starting point for any new site. Generates descriptions for every post that doesn't have one. Skips posts that already have a description, so it is safe to run at any time without overwriting your existing content.</li>
<li><strong>Regenerate All</strong> — Forces new descriptions for every post, replacing whatever is already there. Use this if you've changed your custom prompt or want a fresh pass across your entire site. This will overwrite manually written descriptions — use with care.</li>
<li><strong>Fix Long/Short</strong> — Only rewrites descriptions that fall outside your character range. Useful if you've changed your min/max settings and want to bring everything into line without touching descriptions that are already correct.</li>
<li><strong>Fix Titles</strong> — Rewrites post title tags that fall outside the ideal 50–60 character range for search results. The rewritten title is saved as a custom SEO title — your original WordPress post title is never changed.</li>
<li><strong>Regenerate Static</strong> — Refreshes cached OG image data so LinkedIn and other social platforms pick up featured image changes.</li>
<li><strong>✦ Generate</strong> (per row) — Generates or regenerates the description for a single post only. Use this to test the AI output on one post before running a bulk operation across your entire site.</li>
<li><strong>SEO Score badge</strong> — Each post shows an AI-generated SEO score from 0–100. Click the badge to see the AI's specific feedback and suggestions. Green = 75+, amber = 50–74, red = below 50.</li>
<li><strong>Stop</strong> — Halts a running bulk operation after the current post finishes. Any posts already processed are saved.</li>
</ul>`,

'ab-card-alt': `
<p>When someone uses a screen reader because they have a visual impairment, every image on your site that has no ALT text is invisible to them — they get silence where there should be a description. At the same time, Google's web crawler cannot "see" images either. It reads ALT text to understand what an image depicts, whether that image is relevant to the page topic, and whether it should appear in Google Image Search results. A site full of images with empty ALT attributes is failing both its users and its search rankings.</p>
<p>The <strong>AI ALT Text Generator</strong> fixes this across your entire site in one bulk operation. It scans every post and page, identifies images with missing ALT attributes, and uses AI to write concise, contextually accurate descriptions for each one. The AI reads both the image filename and a short excerpt of the surrounding post content, so the ALT text it generates actually describes the image in context — not just a generic label.</p>
<p>ALT text is also generated automatically during meta description generation when you use Generate Missing or Generate in the Meta Description Writer — so if you've already run that, your images may already have ALT text. This dedicated panel is for auditing and bulk-processing images specifically.</p>
<ul>
<li><strong>Load</strong> — Scans your site and builds the list of posts with missing ALT text. Shows you the count of affected posts and total missing images before you commit to generating anything.</li>
<li><strong>Show all images</strong> checkbox — By default, only posts with at least one missing ALT image are shown. Tick this to see all posts including those already fully covered — useful for reviewing or spot-checking existing ALT text.</li>
<li><strong>✦ Generate</strong> (per row) — Processes all missing ALT images in a single post. Use this to test the AI output on one post before running the full site.</li>
<li><strong>Generate All Missing</strong> — Processes every post with missing ALT text in one bulk operation. This is the recommended approach for auditing an existing site for the first time.</li>
<li><strong>Force Regenerate All</strong> — Rewrites ALL ALT text across your entire site, including images that already have it. Use this if you want to improve the quality of previously generated ALT text or standardise the style. A confirmation prompt appears before it runs.</li>
</ul>`,

'ab-card-summary': `
<p>Most readers decide within the first few seconds whether an article is worth their time. If your post opens with a long preamble before getting to the point, readers bounce — and Google notices bounce rates. The <strong>AI Summary Box</strong> solves this by generating a three-sentence structured summary that appears at the very top of each post, before the article text. Readers see at a glance what the article is about, why it matters, and what they'll take away from it.</p>
<p>The summary box also has an SEO benefit that isn't visible to readers. The three fields are embedded into the Article JSON-LD structured data for each post, which Google can read and potentially surface as featured snippets or in AI Overviews. Providing a clean, structured summary increases the chance that Google quotes your content directly in answer boxes — a core goal of AEO (Answer Engine Optimisation).</p>
<p>The appearance of the summary box on your site is controlled by the <strong>"Show AI summary box on posts"</strong> checkbox in the SEO Features panel. You can generate and store summaries without showing the box — the JSON-LD benefit exists regardless of whether the box is visible on your pages.</p>
<p><strong>The three fields generated for each post:</strong></p>
<ul>
<li><strong>What it is</strong> — One sentence defining the core topic of the article. Written for a reader who's never heard of the subject before.</li>
<li><strong>Why it matters</strong> — One sentence explaining why this topic is relevant or important. Answers the reader's "why should I care?" question.</li>
<li><strong>Key takeaway</strong> — The single most important insight or conclusion from the article. This is often the sentence Google pulls into a featured snippet.</li>
</ul>
<p><strong>Actions:</strong></p>
<ul>
<li><strong>✦ Generate</strong> (per row) — Generates or regenerates the summary for a single post. Use this to preview the output before running the full bulk operation.</li>
<li><strong>Generate Missing</strong> — Generates summaries for all posts that don't have one yet. Safe to run at any time — it will not overwrite existing summaries.</li>
<li><strong>Generate All (force)</strong> — Regenerates summaries for every post, replacing all existing ones. Use this if you've changed your AI prompt or want to refresh the content style across your site.</li>
</ul>`,

'ab-card-rc-settings-card': `
<p>One of the best signals Google uses to understand a site's topical authority is internal linking — how well your posts link to each other around a common topic. A site where every post on cloud computing links to related cloud computing posts tells Google (and readers) that you are a serious resource on that topic. Building this network manually across dozens or hundreds of posts is impractical. The <strong>Related Articles</strong> feature builds it automatically.</p>
<p>Related Articles analyses every post on your site and builds a relevance network — matching posts by shared categories, tags, and (if you've generated AI summaries) the semantic similarity of the content itself. It then injects a "Related Articles" block near the top of each post and a "You Might Also Like" block at the bottom. This keeps readers on your site longer, increases pages-per-session, and creates the kind of internal linking structure that Google rewards with stronger topical authority scores.</p>
<p>This is a purely local feature — it makes no API calls and costs nothing to run. All matching is done server-side using your existing WordPress data and your AI-generated summaries.</p>
<ul>
<li><strong>Enable Related Articles and You Might Also Like on posts</strong> — Master switch. Turn this off to hide all related article blocks site-wide without losing your generated data or settings.</li>
<li><strong>Show "Related Articles" at the top</strong> — Adds a block near the top of each post (after the AI summary box if present). This position has high visibility — readers see it before they've committed to reading the full article, which increases click-through to related content.</li>
<li><strong>Number of links (top)</strong> — How many posts to show in the top block. 3 is a good starting point — enough to offer options without cluttering the reading experience before the article begins.</li>
<li><strong>Show "You Might Also Like" at the bottom</strong> — Adds a block at the end of each post, before the comments section. This is the traditional position for related content and captures readers who have just finished reading and are ready to explore more.</li>
<li><strong>Number of links (bottom)</strong> — How many posts to show in the bottom block. 4–6 is typical.</li>
<li><strong>Pool size</strong> — How many candidate posts the algorithm considers when selecting the best matches. A larger pool gives more accurate results for sites with many posts. Default 20 is fine for sites under 500 posts.</li>
<li><strong>Match by Categories</strong> — Posts in the same category count as more relevant. Recommended on — categories are your primary topical grouping signal.</li>
<li><strong>Match by Tags</strong> — Posts sharing tags count as more relevant. Enable this if you use tags consistently and meaningfully across your content.</li>
<li><strong>Match by AI summary overlap</strong> — Uses the semantic content of AI-generated article summaries to find posts that are truly about the same topic, even if they're in different categories. This produces the most accurate matches but requires AI summaries to have been generated first. Highly recommended if you've run the AI Summary Generator.</li>
<li><strong>Excluded categories</strong> — Posts in these categories will never appear as related article suggestions on any post. Use this to exclude categories like "Announcements", "About", or "Sponsored" that shouldn't appear in contextual article recommendations.</li>
</ul>`,

'ab-card-rc-table': `
<p>The <strong>Related Articles Management</strong> table is your control panel for the related articles network. When you first enable the feature, all posts start as "Pending" — they haven't been processed yet and are not showing related article blocks on the frontend. Run the batch operation here to process your entire site and go from zero to a complete internal linking network in one click.</p>
<p>After changing your settings — for example, adding a new matching criterion, changing the number of links shown, or excluding a category — you'll need to re-run existing posts for the change to take effect. Use the "Re-run Complete" batch button to refresh all already-processed posts.</p>
<ul>
<li><strong>Status badges:</strong>
  <ul>
    <li><strong>Pending</strong> — This post hasn't been processed yet. No related article blocks are being shown for it on the frontend.</li>
    <li><strong>Complete</strong> — Related articles have been generated and are live. The Top and Bottom columns show how many links are being displayed.</li>
    <li><strong>Error</strong> — Processing failed for this post. The error reason is shown in the row — usually a database issue or a post with no matchable content.</li>
  </ul>
</li>
<li><strong>Top / Bottom counts</strong> — The number of related article links currently displayed at the top and bottom of this post on the frontend. If these numbers are lower than your configured settings, the algorithm couldn't find enough matching posts — this is normal for posts on niche topics.</li>
<li><strong>▶ Run</strong> (per row) — Generates or regenerates related articles for a single post. Use this to test the feature on one post before running the full site batch.</li>
<li><strong>🗑 Reset</strong> (per row) — Clears the related articles data for a post and sets it back to Pending. The post will no longer show related article blocks until it is processed again.</li>
<li><strong>Generate &amp; Sync</strong> — Processes all Pending posts and updates link counts for Complete posts. This is the main batch button — run this after enabling the feature for the first time, or after adding new posts that haven't been processed yet.</li>
<li><strong>Refresh Stale</strong> — Re-runs all Complete posts. Use this after changing your settings to apply the new configuration to your existing related article data.</li>
<li><strong>Retry Failed</strong> — Re-runs all posts that are in the Error state.</li>
<li><strong>Reset All</strong> — Clears all related article data across your entire site. Use this only if you want to start completely fresh — all posts will return to Pending and no related article blocks will be shown until you re-run the batch.</li>
</ul>`,

'ab-card-features': `
<p>The <strong>SEO Features</strong> panel is the central switch board for every technical SEO capability the plugin provides. Each feature can be toggled independently — turn on what you need, turn off what you don't, and none of your settings are lost when you toggle something off.</p>
<p>If you're setting up the plugin for the first time, the recommended approach is to enable all the Recommended items below, save, then come back and fine-tune after you've checked how your pages look in Google Search Console.</p>
<p><strong>Social sharing (OpenGraph &amp; Twitter Cards):</strong></p>
<ul>
<li><strong>OpenGraph + Twitter Cards</strong> — When someone shares one of your pages on LinkedIn, Facebook, Twitter/X, WhatsApp, or Slack, the platform reads OpenGraph tags to build the link preview — the card with a title, description, and image. Without these tags, the platform guesses, often producing blank or incorrect previews. Enable this and every shared link from your site will look intentional and professional. <em>Strongly recommended.</em></li>
</ul>
<p><strong>Structured data (JSON-LD schema):</strong></p>
<ul>
<li><strong>WebSite JSON-LD (front page)</strong> — Adds structured data to your homepage that tells Google your site's name and URL. This is the foundation for Google potentially showing a Sitelinks Searchbox (a search field) directly under your homepage result in search. <em>Recommended.</em></li>
<li><strong>Person JSON-LD schema</strong> — Embeds your name, title, photo, and social profiles (configured in the Person Schema panel) into every page's structured data. This is how you build your author identity signal with Google and strengthen your E-E-A-T (Expertise, Authoritativeness, Trustworthiness) standing — critical for content in technical and professional niches. <em>Recommended for personal blogs and authority sites.</em></li>
<li><strong>BlogPosting JSON-LD schema</strong> — Marks up every post as an Article with author, publish date, and headline in structured data. Google uses this to display article rich results — including author name, date, and publication — which can significantly improve click-through rates in search. <em>Recommended.</em></li>
<li><strong>Breadcrumb JSON-LD schema</strong> — Adds breadcrumb structured data so Google can display the page's hierarchy directly in search results (e.g. Home › AWS › Lambda Tutorial). Useful for sites with well-organised category structures.</li>
</ul>
<p><strong>Content features:</strong></p>
<ul>
<li><strong>Show AI summary box on posts</strong> — Controls whether the three-sentence AI summary (What it is / Why it matters / Key takeaway) appears at the top of each post for readers. The summaries are embedded in JSON-LD schema regardless of this setting. Enable once you've generated summaries for your posts. <em>Recommended if summaries have been generated.</em></li>
<li><strong>Related Articles and You Might Also Like</strong> — Same as the master switch in the Related Articles Settings panel. Controls whether related article blocks appear on posts.</li>
</ul>
<p><strong>Technical SEO (canonical URLs &amp; noindex):</strong></p>
<ul>
<li><strong>Strip UTM params in canonical URLs</strong> — If you use UTM tracking parameters in your links (e.g. <code>?utm_source=newsletter</code>), this prevents them from being included in your canonical URL tag. Without this, Google may treat <code>/post/?utm_source=twitter</code> and <code>/post/</code> as two separate pages. <em>Recommended if you use UTM tracking.</em></li>
<li><strong>noindex search results</strong> — WordPress's internal search results pages (<code>?s=query</code>) are auto-generated, thin content with no SEO value. Noindexing them prevents Google from wasting crawl budget on them. <em>Recommended.</em></li>
<li><strong>noindex 404 pages</strong> — Error pages should never appear in search results. <em>Recommended.</em></li>
<li><strong>noindex attachment pages</strong> — WordPress creates a page for every uploaded image or file (e.g. <code>/?attachment_id=123</code>). These pages contain almost nothing and dilute your site's overall quality score in Google's eyes. <em>Recommended.</em></li>
<li><strong>noindex author archives</strong> — Author archive pages duplicate your post content under a different URL. If you're a solo blogger, these add no value and create duplicate content. Enable with caution on multi-author sites where author pages serve a genuine purpose.</li>
<li><strong>noindex tag archives</strong> — Tag archive pages are thin if your tags are used loosely. Enable this if your tags are inconsistent or duplicative. Leave off if your tag pages are genuinely curated topic hubs with unique value.</li>
</ul>`,

'ab-card-sitemap-settings': `
<p>A sitemap is the most direct line of communication between your website and Google. When you publish a new post, Google might not discover it for days or weeks if it has to find the page by following links. With a sitemap, you're effectively handing Google a complete, up-to-date list of every important URL on your site — and Google re-crawls that list regularly to find new content fast.</p>
<p>The first thing you should do after enabling the sitemap is submit its URL to <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a> under Sitemaps. This is a one-time step that tells Google to start monitoring your sitemap. After that, the plugin keeps the sitemap updated automatically every time you publish or update a post.</p>
<ul>
<li><strong>Enable /sitemap.xml</strong> — Activates the sitemap at <code>yoursite.com/sitemap.xml</code>. Once enabled, visit that URL directly to confirm it's working before submitting it to Google Search Console.</li>
<li><strong>Post types to include</strong> — Select which content types appear in the sitemap. Include Posts and Pages. If you have custom post types (e.g. portfolio items, products), include those too if they contain public content you want Google to index. Exclude post types that are administrative or contain private data.</li>
<li><strong>Include taxonomy pages</strong> — Whether category and tag archive pages appear in the sitemap. Enable this only if those pages contain unique, valuable content beyond a simple list of posts. If your category pages are thin, excluding them keeps your sitemap focused on your highest-quality URLs.</li>
<li><strong>Sitemap exclude list</strong> — URL paths to remove from the sitemap, one per line (e.g. <code>/privacy-policy/</code>, <code>/contact/</code>, <code>/thank-you/</code>). These pages will still be accessible to visitors — they just won't appear in the sitemap, telling Google they're not priority crawl targets.</li>
</ul>
<p>The sitemap automatically handles large sites: if you have more than 5,000 URLs, it creates multiple child sitemaps (<code>/sitemap-1.xml</code>, <code>/sitemap-2.xml</code>, etc.) and the index at <code>/sitemap.xml</code> lists them all. A plain-text version is available at <code>/sitemap.txt</code> for tools that prefer that format.</p>
<p>Use the <strong>Sitemap Preview</strong> panel (visible when you scroll down on this tab) to browse exactly which URLs are in your sitemap before submitting it to Google.</p>`,

'ab-card-robots': `
<p>When a search engine crawler (or any bot) arrives at your website, the very first file it reads is <code>robots.txt</code> at the root of your domain. This file is a set of instructions that tells crawlers what they are and are not allowed to access. Without a well-configured robots.txt, crawlers may waste time in your admin area, checkout pages, or other non-public sections — consuming server resources and crawl budget that would be better spent on your actual content.</p>
<p>The <strong>Robots.txt Editor</strong> gives you full control over this file directly from your WordPress admin, without needing FTP or server access.</p>
<ul>
<li><strong>Block AI training bots</strong> — When ticked, adds Disallow rules for the major AI content scraping bots: GPTBot (OpenAI), CCBot (Common Crawl), Claude-Web and anthropic-ai (Anthropic), Google-Extended, and others. These bots crawl your content specifically to train large language models. If you don't want your writing used to train commercial AI systems, enable this. Note: this only affects well-behaved bots that honour robots.txt — it doesn't guarantee all AI scrapers comply.</li>
<li><strong>Custom robots.txt content</strong> — The full text of your robots.txt file. The plugin provides a sensible default that allows all search engine crawlers while blocking your admin area. Common customisations include:
  <ul>
    <li><code>Disallow: /checkout/</code> or <code>Disallow: /cart/</code> — Block crawling of WooCommerce transaction pages</li>
    <li><code>Disallow: /wp-login.php</code> — Explicitly block the login page</li>
    <li><code>Crawl-delay: 10</code> — Ask aggressive crawlers to slow down (only honoured by some bots)</li>
  </ul>
</li>
</ul>
<p><strong>Critical distinction:</strong> robots.txt controls <em>crawl access</em>, not <em>indexing</em>. A page you block in robots.txt may still appear in Google's search results if other sites link to it — Google knows it exists, it just can't read it. If you want a page removed from Google's index, use the noindex meta tag (configured in the SEO Features panel) instead of robots.txt. If you want both — no crawling AND no indexing — you need both.</p>`,

'ab-card-llms': `
<p>When someone asks ChatGPT, Perplexity, or Claude a question that your site could answer, the AI either knows about your site (because it was included in training data) or it visits your site in real time via web browsing. In both cases, a machine is trying to understand your content from raw HTML — which is full of navigation menus, sidebars, cookie banners, and other noise that gets in the way of the actual content.</p>
<p><strong>llms.txt</strong> is the solution. It's a plain-text file at <code>/llms.txt</code> that gives AI systems a clean, structured summary of your website: your name, your site's purpose, and a list of your key posts and pages with their URLs and meta descriptions. Think of it as <code>robots.txt</code> for AI, or a table of contents written specifically for machine readers. It's an emerging standard proposed in 2024 and gaining adoption rapidly as AI-powered search becomes mainstream.</p>
<p>Publishing a well-structured llms.txt is one of the most important AEO (Answer Engine Optimisation) steps you can take. It gives you direct control over how AI systems represent your content in their answers — rather than leaving them to guess from your raw HTML.</p>
<ul>
<li><strong>Enable /llms.txt</strong> — Activates the endpoint. Once enabled, visit <code>yoursite.com/llms.txt</code> to see exactly what AI crawlers will read. The file is generated dynamically from your live post data and AI-generated meta descriptions — which is another reason to run Generate Missing in the Meta Description Writer first. Posts with no meta description are listed without a summary, which gives AI systems less to work with.</li>
</ul>
<p>Use the <strong>Load Preview</strong> button to see a live preview of your llms.txt in your admin area before sharing it or submitting it to AI search indices. The <a href="https://llmstxt.org/" target="_blank" rel="noopener">llms.txt specification</a> is an open standard — you can read more about it and why it matters at llmstxt.org.</p>`,

'ab-card-https': `
<p>When a site moves from HTTP to HTTPS, the server-side redirect ensures visitors always land on the secure version. But the database is full of old hardcoded <code>http://</code> links — in image URLs embedded in post content, in internal links written years ago, in theme option values. These old HTTP references cause "mixed content" browser warnings, where the browser detects that a secure page is loading resources over an insecure connection and blocks or flags them. Mixed content warnings can also suppress the padlock icon in the browser's address bar — which erodes visitor trust.</p>
<p>The <strong>HTTPS URL Fixer</strong> solves this with a targeted database search and replace. It finds every occurrence of <code>http://yourdomain.com</code> in your WordPress database and rewrites them to <code>https://yourdomain.com</code>. It handles serialised PHP data correctly (used by themes and some plugins to store settings), so the replace operation doesn't break anything.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan</strong> — the plugin searches your database for HTTP URLs and shows you a summary: which domains appear, in which tables, and how many rows are affected.</li>
<li>Review the results and tick the domains you want to fix. Usually this is just your own domain, but the scanner may also find third-party embed URLs that can safely be upgraded.</li>
<li>Click <strong>Fix Selected</strong> — the plugin updates all matching rows. The operation is logged so you can see exactly what changed.</li>
</ol>
<p><strong>Important:</strong> Always take a full database backup before running any bulk find-and-replace operation. While the HTTPS Fixer is designed to be safe and reversible for standard WordPress data, a backup is your safety net if something unexpected happens in a complex database with non-standard plugins or custom tables.</p>`,

'ab-card-fonts': `
<p>Most WordPress themes load their fonts from Google Fonts — a free CDN that serves web fonts from Google's servers. This is convenient for theme developers, but it has two significant downsides for your site. First, it's slow: every page load requires the browser to make an extra network request to <code>fonts.googleapis.com</code>, wait for a DNS lookup, establish a connection, and download the font stylesheet before text on your page can render. This directly hurts your Core Web Vitals scores — specifically LCP (Largest Contentful Paint), which Google uses as a direct ranking factor. Second, it's a GDPR problem: when a visitor's browser requests a font from Google's CDN, their IP address is sent to Google. Under the EU's General Data Protection Regulation, this is a transfer of personal data to a third party without your visitor's explicit consent — and German and Austrian courts have already ruled against sites doing this.</p>
<p>The <strong>Font Optimiser</strong> solves both problems by downloading the font files from Google's CDN to your own server and rewriting the references in your theme to point to the local copies. The fonts look identical, but they now load from your domain — no external request, no GDPR exposure, faster rendering.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan &amp; Download</strong> — the plugin detects Google Fonts stylesheet URLs registered by your theme, downloads all the font files (in woff2 and woff formats for broad compatibility), and stores them in your WordPress uploads directory.</li>
<li>Click <strong>Apply Fixes</strong> — the plugin registers local versions of the font stylesheets and dequeues the Google CDN versions, so all font requests now resolve locally.</li>
<li>Click <strong>Undo</strong> — if anything looks wrong after applying, this restores the original Google CDN references immediately.</li>
</ol>
<p>After applying, check your site's PageSpeed Insights score at <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener">pagespeed.web.dev</a> — the "Eliminate render-blocking resources" warning for Google Fonts should be gone.</p>`,

'ab-card-render': `
<p>Page load speed is a confirmed Google ranking factor, and Google measures it using Core Web Vitals — real-world performance metrics visible in Google Search Console. The three optimisations in this panel target specific speed metrics that appear in those reports. None of them change how your site looks — they only change how quickly it gets to a usable state in the browser.</p>
<ul>
<li><strong>Defer non-critical fonts</strong> — Fonts that aren't used to render the visible text on a page (for example, icon fonts loaded in the footer) can be loaded asynchronously. This allows the browser to render visible content first, rather than waiting for every font file to download before showing anything. Helps with the First Contentful Paint (FCP) metric.</li>
<li><strong>Defer JavaScript</strong> — By default, when a browser encounters a <code>&lt;script&gt;</code> tag while parsing your page's HTML, it stops everything, downloads the script, executes it, then resumes parsing. For JavaScript that doesn't affect the initial visible page (analytics, social embeds, chat widgets, etc.), this is wasteful. Deferring scripts lets the page render first and runs scripts afterwards, which significantly improves Time to Interactive (TTI) and the Interaction to Next Paint (INP) metric.
  <br><br><strong>Exclusion list:</strong> Some scripts must run before the page is visible — typically anti-flicker snippets for A/B testing tools, or cookie consent managers that modify the page on load. Add patterns for those file names here to exclude them from deferring. If enabling defer breaks something on your site, add the offending script's filename to the exclusion list.
</li>
<li><strong>Minify HTML</strong> — Removes whitespace, comments, and redundant formatting from the HTML your server sends to the browser. A typical WordPress page has 10–20% of its file size consumed by whitespace and comments that are invisible to visitors but take up bandwidth. Minifying this reduces transfer time and server bandwidth usage. This is the safest of the three optimisations — it has no effect on how the page looks or functions.</li>
</ul>
<p><strong>How to verify the improvements:</strong> Run your site through <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener">PageSpeed Insights</a> before and after enabling these features. The "Reduce unused JavaScript" and "Avoid chaining critical requests" diagnostics should improve after enabling JS deferral. The overall page size should decrease after enabling HTML minification.</p>`,

'ab-card-catfix': `
<p>Imagine a filing cabinet where half the folders have the wrong documents in them. That's what a miscategorised WordPress site looks like to Google. When a blog post about AWS Lambda is filed under "Personal" instead of "Cloud Computing", Google gets a confused signal about what topics each section of your site covers — which weakens your topical authority across the board. Google rewards sites that demonstrate consistent expertise in a topic, and consistent categorisation is a fundamental part of that signal.</p>
<p>The <strong>Category Fixer</strong> audits every published post on your site and compares its content to its current category assignment. It uses keyword scoring to identify mismatches and can also use your configured AI provider for more nuanced analysis of ambiguous cases. The result is a prioritised list of suggested recategorisations — you review each one and decide whether to apply it.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan Posts</strong> — the plugin reads every published post and scores how well its content matches its assigned categories. This runs locally and makes no API calls.</li>
<li>Review the results. Posts with a high mismatch score are shown first. Each row shows the post's current category and the suggested better category.</li>
<li>Click <strong>Apply</strong> on individual rows to move the post to the suggested category, or <strong>Skip</strong> to dismiss the suggestion without making a change.</li>
<li>Click <strong>Apply All Changed</strong> to accept every pending suggestion at once — useful if the scan has produced a large number of clear-cut corrections.</li>
</ol>
<p><strong>Filter tabs</strong> at the top let you narrow the view to: All posts, Changed (suggestions pending), Unchanged (post is already well-categorised), Low-confidence (suggestions the algorithm is less certain about), and Uncategorised (posts with no category at all — these should be addressed first).</p>
<p>The <strong>🤖 AI Analyse</strong> button sends the selected posts to your AI provider for a deeper, semantic analysis. Use this for posts where keyword scoring produces a suggestion you're not sure about, or for posts that cover multiple topics and need a human-plus-AI judgment call.</p>`,

'ab-card-cathealth': `
<p>Not all categories are created equal. A category with 25 posts about AWS architecture sends a strong topical signal to Google. A category with one post from 2021 that was never followed up sends essentially no signal at all — and worse, it wastes crawl budget. Google allocates a limited amount of crawl budget to each site, and spending some of it on thin, empty category pages is a wasted opportunity to get your actual content crawled more frequently.</p>
<p>The <strong>Category Health Dashboard</strong> gives you an at-a-glance quality grade for every category on your site, so you can see immediately which categories are working for you and which ones are dragging down your topical authority. The grading is based on post count and recency — because a category you stopped writing about three years ago signals to Google that your site has abandoned that topic.</p>
<p><strong>Health grades and what to do about each:</strong></p>
<ul>
<li><strong>Strong</strong> (green, 10+ posts) — This category is well established. Google has strong evidence that your site covers this topic in depth. Keep publishing in it.</li>
<li><strong>Moderate</strong> (orange, 4–9 posts) — Healthy and indexed. Plan to grow this category with more posts over time to reach Strong status.</li>
<li><strong>New</strong> (blue, 1–3 posts within the last 180 days) — A category you're actively building. Don't merge or delete it — give it time to grow.</li>
<li><strong>Weak</strong> (yellow, 2–3 posts, none recent) — This category exists but isn't being maintained. Consider whether these posts belong in a broader, stronger category instead, or whether you plan to publish more on this topic soon.</li>
<li><strong>Empty</strong> (red, 0–1 posts) — This category is contributing nothing and consuming crawl budget. Either delete it, merge it into a related category, or write new posts for it to bring it up to Moderate.</li>
<li><strong>Uncategorized</strong> (grey) — The WordPress default category. Posts here were published without being categorised. These should all be moved to appropriate categories using the Category Fixer above.</li>
</ul>
<p>Click any grade badge at the top of the table to filter the view. Click <strong>▼ Show posts</strong> in any row to see which posts belong to that category — useful when deciding whether to merge or delete a weak category.</p>`,

'ab-card-catdrift': `
<p>Over time, a category that started with a clear focus can quietly drift into something much less coherent. A "Technology" category that began with cloud computing posts might now contain posts about personal productivity, book reviews, and hardware reviews — because they're all vaguely "tech". This kind of category drift is one of the most common reasons sites fail to rank well for their core topics. Google reads the aggregate of everything in a category to understand what that section of your site is about, and a catch-all category produces a confused, diluted signal.</p>
<p><strong>Category Drift Detection</strong> uses AI to do what's hard to see manually — read the titles of all the posts in each category and identify when they've stopped talking about the same topic. It flags categories that have become catch-alls and gives you specific recommendations for which posts to move where, so you can restore topical coherence to your category structure.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Run Fresh AI Analysis</strong> — the AI reads a sample of post titles from each category and analyses the semantic coherence. It flags categories where the posts are clearly not all about the same topic. Results are cached so you don't have to re-run the analysis on every visit.</li>
<li>Click <strong>Load Cached Results</strong> — reloads the most recent analysis from the cache without making a new API call. Use this when you return to act on previous recommendations.</li>
<li>Review the flagged categories. Each entry shows:
  <ul>
    <li><strong>Verdict</strong> — Either <em>Catch-all</em> (posts on completely unrelated topics are mixed together) or <em>Drifting</em> (posts are loosely related but the category has grown beyond its original focus).</li>
    <li><strong>Confidence</strong> — How certain the AI is about the verdict: High, Medium, or Low. High-confidence catch-alls should be addressed first.</li>
    <li><strong>AI Reasoning</strong> — A plain-language explanation of specifically why this category was flagged and what topics it has drifted into.</li>
    <li><strong>Suggested moves</strong> — Specific posts the AI recommends moving to different, more appropriate categories, with a reason for each recommendation.</li>
  </ul>
</li>
<li>Click <strong>→ Move</strong> on individual post suggestions to apply them one at a time, or <strong>→ Move all</strong> to apply all moves for a category at once.</li>
<li>Click <strong>🤖 Analyse remaining</strong> to ask the AI for additional suggestions covering posts in the flagged category that weren't covered in the initial analysis.</li>
</ol>
<p>Moves are applied immediately to the post's WordPress category assignments. The analysis cache is updated after each move so the displayed state always reflects the current situation.</p>`,

'ab-card-schedule': `
<p>The AI generation tools in the AI Tools tab are all manually triggered — you click a button, they run. That's fine for a one-time audit of your existing content, but what about the post you publish next week, or the week after? Without automation, every new post needs a manual visit to the admin to generate its meta description, ALT text, and summary. The <strong>Scheduled Batch</strong> solves this by running a background job on a schedule, automatically filling in AI content for any posts that don't have it yet.</p>
<p>The batch processor is designed to be safe and non-destructive: it only creates content where none exists. It will never overwrite a meta description you've written manually, a summary that was generated last week, or an SEO score from the previous run. If you want to force-regenerate content, use the individual bulk tools in the AI Tools tab. The batch processor's job is to catch anything that slips through — new posts, posts that failed during a previous run, or posts that existed before you installed the plugin.</p>
<ul>
<li><strong>Enable scheduled batch processing</strong> — Master switch. When ticked, a WordPress Cron job runs the processor at your chosen frequency. Untick this to pause all scheduled processing without losing your settings.</li>
<li><strong>Schedule frequency</strong> — How often to run: Hourly, Twice Daily, or Daily. For most bloggers publishing a few times a week, Daily is more than sufficient and consumes minimal API credits. Choose Hourly only if you publish multiple posts per day and want AI content available immediately.</li>
<li><strong>Generate missing meta descriptions</strong> — Each run will write AI meta descriptions for any published posts that don't have one. This is the most important item to enable.</li>
<li><strong>Generate missing AI summaries</strong> — Each run will generate the three-sentence summary box for any posts that don't have one yet.</li>
<li><strong>Score posts</strong> — Each run will calculate an SEO score for posts that haven't been scored yet. This is a lightweight operation but consumes one API call per unscored post.</li>
</ul>
<p><strong>A note on WordPress Cron:</strong> WordPress's built-in cron system runs when a visitor loads a page on your site — it doesn't run on a true server-side schedule. If your site has very low overnight traffic, a scheduled "Daily" job might not run until the first visitor the next morning. For sites that need precise scheduling, set up a real server cron job (via cPanel, Linux crontab, or your hosting control panel) to call <code>wp-cron.php</code> on your preferred schedule. This is a one-time server configuration that makes WordPress Cron behave like a real scheduler.</p>`,

'ab-card-lastrun': `
<p>After enabling the Scheduled Batch, how do you know it's actually running? The <strong>Last Run Log</strong> is your confirmation. After each batch run, the plugin records exactly what it did — how many posts it checked, how many descriptions it wrote, how many summaries it created, how many SEO scores it calculated, and whether any posts failed. Check this log periodically to confirm the automation is working and to catch any recurring errors.</p>
<ul>
<li><strong>Run date/time</strong> — When the most recent batch ran. If this is more than 24 hours ago and you have "Daily" frequency enabled, the batch may not be running — check that WordPress Cron is functioning (see the note about WordPress Cron in the Batch Schedule section above).</li>
<li><strong>Duration</strong> — How long the batch took to complete. Longer run times usually mean there were many posts to process. If the batch is timing out, reduce the number of tasks enabled or switch to a more efficient AI model (e.g. Claude Haiku instead of Sonnet).</li>
<li><strong>Posts processed</strong> — The total number of published posts the batch checked. This includes posts that already had content (and were therefore skipped) as well as posts that needed processing.</li>
<li><strong>Meta descriptions generated</strong> — How many new meta descriptions were written in this run.</li>
<li><strong>Summaries generated</strong> — How many new AI summary boxes were created in this run.</li>
<li><strong>Scores updated</strong> — How many SEO scores were calculated in this run.</li>
<li><strong>Errors</strong> — Posts that failed to process, with the specific error reason. The most common errors are:
  <ul>
    <li><strong>API rate limit</strong> — Your AI provider temporarily rejected the request because too many calls were made too quickly. The batch will retry the post on the next run.</li>
    <li><strong>No content</strong> — The post has no readable text content (it might be a placeholder, a gallery-only post, or have content stored in page builder blocks that the plugin can't parse). These posts will continue to be skipped.</li>
    <li><strong>API key invalid</strong> — Your API key has expired, been deleted, or run out of credits. Check your provider console and update the key in AI Settings.</li>
  </ul>
</li>
</ul>
<p>Click <strong>Run Now</strong> to trigger a manual batch run immediately — useful for testing that the batch is configured correctly, or to process a batch outside its scheduled time after making changes.</p>`,

};

// ── REST API helpers ──────────────────────────────────────────────────────────

function restRequest(method, endpoint, body, contentType) {
    return new Promise((resolve, reject) => {
        const url  = new URL(endpoint.startsWith('http') ? endpoint : `${REST_BASE}${endpoint}`);
        const mod  = url.protocol === 'https:' ? https : http;
        const opts = {
            hostname: url.hostname,
            port:     url.port || (url.protocol === 'https:' ? 443 : 80),
            path:     url.pathname + url.search,
            method,
            headers:  { Authorization: AUTH_HEADER },
        };

        let bodyBuf = null;
        if (body) {
            bodyBuf = Buffer.isBuffer(body) ? body : Buffer.from(JSON.stringify(body));
            opts.headers['Content-Type']   = contentType || 'application/json';
            opts.headers['Content-Length'] = bodyBuf.length;
        }

        const req = mod.request(opts, res => {
            const chunks = [];
            res.on('data', c => chunks.push(c));
            res.on('end', () => {
                const text = Buffer.concat(chunks).toString();
                try { resolve({ status: res.statusCode, body: JSON.parse(text) }); }
                catch (_) { resolve({ status: res.statusCode, body: text }); }
            });
        });
        req.on('error', reject);
        if (bodyBuf) req.write(bodyBuf);
        req.end();
    });
}

function toJpeg(pngPath) {
    const jpgPath = pngPath.replace(/\.png$/, '.jpg');
    // Use sips (macOS built-in) to convert PNG → JPEG at 75% quality
    execSync(`sips -s format jpeg -s formatOptions 75 "${pngPath}" --out "${jpgPath}" 2>/dev/null`);
    return jpgPath;
}

async function uploadMedia(pngPath, pngFilename) {
    // Convert to JPEG before uploading to keep file sizes small
    const filePath = toJpeg(pngPath);
    const filename = pngFilename.replace(/\.png$/, '.jpg');

    const data     = fs.readFileSync(filePath);
    const boundary = `----FormBoundary${Date.now()}`;
    const header   = Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="file"; filename="${filename}"\r\nContent-Type: image/jpeg\r\n\r\n`);
    const footer   = Buffer.from(`\r\n--${boundary}--\r\n`);
    const body     = Buffer.concat([header, data, footer]);

    // Retry up to 3 times on transient Cloudflare errors
    for (let attempt = 1; attempt <= 4; attempt++) {
        try {
            const res = await restRequest('POST', '/media', body, `multipart/form-data; boundary=${boundary}`);
            if (res.status === 201) return { id: res.body.id, url: res.body.source_url };
            if (attempt < 4) {
                process.stdout.write(` (attempt ${attempt} failed ${res.status}, retrying...)`);
                await new Promise(r => setTimeout(r, 3000));
            } else {
                throw new Error(`Media upload failed (${res.status}): ${JSON.stringify(res.body).slice(0, 200)}`);
            }
        } catch (err) {
            if (attempt < 4 && (err.code === 'ECONNRESET' || err.code === 'ECONNREFUSED' || err.code === 'ETIMEDOUT')) {
                process.stdout.write(` (${err.code} retry ${attempt}...)`);
                await new Promise(r => setTimeout(r, 4000));
            } else {
                throw err;
            }
        }
    }
}

async function findPageBySlug(slug) {
    // Cache-bust to bypass Cloudflare's GET cache on the REST API endpoint
    const res = await restRequest('GET', `/pages?slug=${encodeURIComponent(slug)}&per_page=1&_t=${Date.now()}`);
    return Array.isArray(res.body) && res.body.length ? res.body[0] : null;
}

async function findOrCreateParentPage() {
    const { buildParentIndex } = require('REPO_BASE/shared-help-docs/help-lib.js');
    const PARENT_ID_FILE = 'REPO_BASE/shared-help-docs/.parent-page-id';
    // Use persisted ID so all plugin scripts share the same parent page
    if (fs.existsSync(PARENT_ID_FILE)) {
        const stored = parseInt(fs.readFileSync(PARENT_ID_FILE, 'utf8').trim(), 10);
        if (stored > 0) return stored;
    }
    const existing = await findPageBySlug('wordpress-plugin-help');
    if (existing) { fs.writeFileSync(PARENT_ID_FILE, String(existing.id)); return existing.id; }
    console.log('  Creating parent page "WordPress Plugin Help"...');
    const res = await restRequest('POST', '/pages', {
        title:   'WordPress Plugin Help',
        slug:    'wordpress-plugin-help',
        status:  'publish',
        content: buildParentIndex([]),
    });
    if (res.status !== 201) throw new Error(`Parent page create failed (${res.status})`);
    console.log(`  Parent page created (ID ${res.body.id})`);
    fs.writeFileSync(PARENT_ID_FILE, String(res.body.id));
    return res.body.id;
}

async function updateParentIndex(parentId) {
    const res = await restRequest('GET', `/pages?parent=${parentId}&per_page=20&orderby=title&order=asc`);
    const children = Array.isArray(res.body) ? res.body : [];
    const { buildParentIndex } = require('REPO_BASE/shared-help-docs/help-lib.js');
    await restRequest('POST', `/pages/${parentId}`, {
        title:   'WordPress Plugin Help',
        content: buildParentIndex(children),
        status:  'publish',
    });
}

async function createOrUpdatePage(title, content, slug, parentId) {
    const existing = await findPageBySlug(slug);
    if (existing) {
        console.log(`  Updating existing page (ID ${existing.id})...`);
        const res = await restRequest('POST', `/pages/${existing.id}`, { title, content, slug, status: 'publish', parent: parentId });
        if (res.status !== 200) throw new Error(`Page update failed (${res.status}): ${JSON.stringify(res.body).slice(0, 200)}`);
        return { id: res.body.id, url: res.body.link };
    }
    console.log('  Creating new page...');
    const res = await restRequest('POST', '/pages', { title, content, slug, status: 'publish', parent: parentId });
    if (res.status !== 201) throw new Error(`Page create failed (${res.status}): ${JSON.stringify(res.body).slice(0, 200)}`);
    return { id: res.body.id, url: res.body.link };
}

// ── HTML builder ──────────────────────────────────────────────────────────────

function buildHtml(imageMap) {
    const img = (file, alt) => {
        const src = imageMap[file] || `screenshots/${file}`;
        return `<figure class="cs-screenshot"><img src="${src}" alt="${alt}" /></figure>`;
    };

    const section = (panel) => {
        const docText = DOCS[panel.cardClass] || `<p>${panel.label}</p>`;
        return `
<div class="cs-panel-section">
<h3 class="cs-panel-heading" id="${panel.cardClass}">${panel.label}</h3>
${img(panel.file, panel.label)}
<div class="cs-panel-body">${docText}</div>
</div>`;
    };

    // Text-only sections (no screenshot — panel hidden until API key is configured)
    const textSection = (id, title, docText) => `
<div class="cs-panel-section cs-panel-no-screenshot">
<h3 class="cs-panel-heading" id="${id}">${title}</h3>
<div class="cs-panel-body">${docText}
<p class="cs-gated-note"><strong>Note:</strong> this panel only appears once an AI API key has been saved in the AI Settings panel above.</p>
</div>
</div>`;

    const panelsByTab = {};
    for (const p of PANELS) {
        if (!panelsByTab[p.tab]) panelsByTab[p.tab] = [];
        panelsByTab[p.tab].push(p);
    }

    const TAB_TITLES = {
        seo:     '📊 SEO Settings',
        aitools: '✨ AI Tools',
        sitemap: '🗺 Sitemap &amp; Robots',
        perf:    '⚡ Performance',
        catfix:  '🏷 Categories',
        batch:   '🔄 Scheduled Batch',
    };

    const TAB_INTROS = {
        seo:     'Before any AI tools or sitemaps can do their job, Google needs to understand the basic facts about your website — who runs it, what it\'s called, and what kind of content it contains. The SEO Settings tab is where you establish that foundation. Fill in your site identity and author details once, and the plugin will embed that information into every page as structured data that search engines can read directly. This tab is also where you connect your AI provider (Anthropic Claude or Google Gemini) and configure the Auto Pipeline, which automatically processes every post you publish so you never have to manually trigger AI generation again.',
        aitools: 'If you have a WordPress site with tens or hundreds of posts, there\'s a good chance most of them are missing a meta description — the short paragraph that appears under your page title in Google Search results. Without it, Google writes its own snippet, which is often a random sentence pulled from your content. The AI Tools tab fixes this in bulk. Load your posts, click Generate Missing, and the AI writes compelling, correctly-sized meta descriptions for every post that doesn\'t have one. The same tab handles image ALT text (important for both accessibility and Google Image Search), AI-generated article summary boxes that help readers decide whether to read, and the Related Articles system that builds an internal linking network across your entire site automatically.',
        sitemap: 'A sitemap tells Google which pages exist on your site and when they were last updated — it\'s the fastest way to ensure new content gets discovered and indexed. Without one, Google has to find your pages by following links, which can take weeks for new posts. This tab controls your XML sitemap, your robots.txt file (which tells crawlers what they are and are not allowed to access), and your llms.txt file — a new standard that gives AI assistants like ChatGPT and Perplexity a structured, accurate summary of your site so they represent your content correctly in their answers.',
        perf:    'Page speed is a direct Google ranking factor. Slow pages rank lower, and Google measures speed using Core Web Vitals — real-world performance metrics that appear in Google Search Console. This tab provides three performance optimisations that are safe to enable on virtually any WordPress site: local font hosting (which eliminates a slow Google Fonts network request and improves GDPR compliance), JavaScript deferral (which lets your page render before scripts run, reducing Time to Interactive), and HTML minification (which shrinks page size by removing unnecessary whitespace). The HTTPS URL Fixer is also here — essential if you ever migrated your site from HTTP to HTTPS and have old hardcoded links still pointing to the insecure version.',
        catfix:  'Your category structure is more important for SEO than most WordPress site owners realise. Google uses categories as a signal for topical authority — if a category contains a jumbled mix of unrelated posts, it tells Google your site lacks focus on that topic. The Categories tab gives you three tools to fix this: the Category Fixer scans every post and tells you if it\'s in the wrong category; the Category Health dashboard grades each category based on post count and recency so you can see at a glance which topics are strong and which are thin; and Category Drift Detection uses AI to identify categories that have become catch-all buckets for loosely related content, with specific suggestions for where to move each post.',
        batch:   'Most AI generation tasks in this plugin are triggered manually — you click a button and it runs. The Scheduled Batch tab automates this so new posts that haven\'t been processed yet get picked up automatically on a schedule. Enable it, choose whether to generate missing meta descriptions, summaries, or SEO scores on each run, and set a frequency. The batch processor only fills in missing content — it will never overwrite descriptions or summaries you\'ve already written. Check the Last Run Log after each run to confirm it\'s working and see exactly what was processed.',
    };

    let body = `
<style>
/* ── CloudScale SEO AI Optimizer — Help Page Styles ─────────────────────── */
.cs-help-docs { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a202c; line-height: 1.7; max-width: 900px; }
.cs-help-docs a { color: #2563eb; }
.cs-help-docs code { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 1px 6px; font-size: 0.88em; }

/* Hero header */
.cs-hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0e6b8f 100%); color: #fff; border-radius: 12px; padding: 48px 40px; margin-bottom: 40px; }
.cs-hero h1 { font-size: 2.2em; font-weight: 800; margin: 0 0 12px; letter-spacing: -0.02em; color: #fff; }
.cs-hero p { font-size: 1.1em; margin: 0; opacity: 0.85; max-width: 680px; }
.cs-hero .cs-badge { display: inline-block; background: rgba(255,255,255,0.15); border-radius: 20px; padding: 4px 14px; font-size: 0.8em; font-weight: 600; margin-bottom: 16px; letter-spacing: 0.05em; text-transform: uppercase; }

/* Table of contents */
.cs-toc { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 28px 36px; margin: 32px 0; }
.cs-toc-title { font-size: 1em; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin: 0 0 16px; }
.cs-toc > ol { columns: 2; gap: 0 32px; margin: 0; padding-left: 22px; }
.cs-toc > ol > li { break-inside: avoid; margin-bottom: 6px; }
.cs-toc > ol > li > ol { margin: 4px 0 0; padding-left: 18px; list-style-type: lower-alpha; }
.cs-toc > ol > li > ol > li { margin: 2px 0; }
.cs-toc li a { color: #2563eb; text-decoration: none; font-weight: 500; font-size: 0.95em; }
.cs-toc li a:hover { text-decoration: underline; }

/* Setup checklist */
.cs-setup { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 28px 36px; margin: 32px 0; }
.cs-setup-title { font-size: 1.2em; font-weight: 700; color: #14532d; margin: 0 0 16px; }
.cs-setup ol { margin: 0; padding-left: 20px; }
.cs-setup li { margin: 8px 0; color: #166534; }
.cs-setup li strong { color: #14532d; }

/* Tab section dividers */
.cs-tab-section { margin: 56px 0 0; }
.cs-tab-heading { font-size: 2em; font-weight: 800; color: #0f172a; padding: 0 0 16px; border-bottom: 3px solid #0e6b8f; margin: 0 0 8px; letter-spacing: -0.01em; }
.cs-tab-intro { color: #475569; font-size: 1.05em; margin: 10px 0 32px; }

/* Panel sections */
.cs-panel-section { margin: 36px 0 0; }
.cs-panel-no-screenshot { background: #fafafa; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px 28px; }
.cs-panel-heading { font-size: 1.45em; font-weight: 700; color: #1e293b; margin: 0 0 16px; padding: 0 0 10px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
.cs-panel-heading::before { content: ""; display: inline-block; width: 4px; height: 1.2em; background: #0e6b8f; border-radius: 2px; flex-shrink: 0; }

/* Screenshots */
.cs-screenshot { margin: 20px 0 24px; }
.cs-screenshot img { max-width: 100%; border-radius: 8px; border: 1px solid #d1d5db; box-shadow: 0 4px 20px rgba(0,0,0,0.10); display: block; }

/* Panel body */
.cs-panel-body { color: #334155; }
.cs-panel-body p { margin: 0 0 12px; }
.cs-panel-body ul, .cs-panel-body ol { padding-left: 22px; margin: 8px 0 16px; }
.cs-panel-body li { margin: 6px 0; }
.cs-panel-body strong { color: #1e293b; }

/* Gated panel note */
.cs-gated-note { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 14px; font-size: 0.92em; color: #92400e; margin-top: 16px; }

/* Section dividers */
.cs-divider { border: none; border-top: 1px solid #e2e8f0; margin: 40px 0; }

/* Glossary */
.cs-glossary { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 28px 36px; margin-top: 40px; }
.cs-glossary h2 { font-size: 1.8em; font-weight: 800; color: #0f172a; margin: 0 0 24px; }
.cs-glossary dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 24px; align-items: baseline; }
.cs-glossary dt { font-weight: 700; color: #0e6b8f; font-size: 0.95em; }
.cs-glossary dd { margin: 0; color: #475569; font-size: 0.95em; }
</style>

<div class="cs-hero">
<div class="cs-badge">Free &amp; Open Source — No Pro Version, No Upsells, No Subscriptions</div>
<h1>CloudScale SEO AI Optimizer — Help &amp; Documentation</h1>
<p>CloudScale SEO AI Optimizer is a free WordPress plugin that combines complete technical SEO with an AI-powered content suite. It writes your meta descriptions, ALT text, and article summaries using your own Anthropic Claude or Google Gemini API key, builds an automatic internal linking network across your entire site, and handles all the technical SEO that WordPress leaves out — sitemaps, robots.txt, structured data schemas, social sharing tags, and performance optimisations that improve your Core Web Vitals scores.</p>
<p style="margin-top:12px;opacity:0.85">There is no Pro version, no upsell, no monthly subscription, and no feature locked behind a licence key. Everything the plugin does is documented on this page.</p>
</div>

<div class="cs-toc">
<div class="cs-toc-title">Contents</div>
<ol>
<li><a href="#setup">First-time setup</a></li>
<li><a href="#seo">📊 SEO Settings</a>
  <ol>
    <li><a href="#ab-card-identity">Site Identity &amp; Schema</a></li>
    <li><a href="#ab-card-person">Person Schema</a></li>
    <li><a href="#ab-card-ai">AI Settings</a></li>
    <li><a href="#ab-card-auto-pipeline">Auto Pipeline</a></li>
  </ol>
</li>
<li><a href="#aitools">✨ AI Tools</a>
  <ol>
    <li><a href="#ab-card-update-posts">AI Meta Description Writer</a></li>
    <li><a href="#ab-card-alt">AI ALT Text Generator</a></li>
    <li><a href="#ab-card-summary">AI Summary Box</a></li>
    <li><a href="#ab-card-rc-settings-card">Related Articles — Settings</a></li>
    <li><a href="#ab-card-rc-table">Related Articles — Management</a></li>
  </ol>
</li>
<li><a href="#sitemap">🗺 Sitemap &amp; Robots</a>
  <ol>
    <li><a href="#ab-card-features">SEO Features</a></li>
    <li><a href="#ab-card-sitemap-settings">XML Sitemap Settings</a></li>
    <li><a href="#ab-card-robots">Robots.txt Editor</a></li>
    <li><a href="#ab-card-llms">llms.txt</a></li>
  </ol>
</li>
<li><a href="#perf">⚡ Performance</a>
  <ol>
    <li><a href="#ab-card-fonts">Font Optimiser</a></li>
    <li><a href="#ab-card-render">JS, CSS &amp; HTML Optimisation</a></li>
    <li><a href="#ab-card-https">HTTPS URL Fixer</a></li>
  </ol>
</li>
<li><a href="#catfix">🏷 Categories</a>
  <ol>
    <li><a href="#ab-card-catfix">Category Fixer</a></li>
    <li><a href="#ab-card-cathealth">Category Health</a></li>
    <li><a href="#ab-card-catdrift">Category Drift Detection</a></li>
  </ol>
</li>
<li><a href="#batch">🔄 Scheduled Batch</a>
  <ol>
    <li><a href="#ab-card-schedule">Batch Schedule</a></li>
    <li><a href="#ab-card-lastrun">Last Run Log</a></li>
  </ol>
</li>
<li><a href="#glossary">Glossary of terms</a></li>
</ol>
</div>

<div class="cs-setup" id="setup">
<div class="cs-setup-title">✅ First-time setup checklist</div>
<ol>
<li>Go to the <strong>SEO Settings</strong> tab → <strong>Site Identity</strong> panel and fill in your Site name, Locale, and Default OG image.</li>
<li>In the <strong>AI Settings</strong> panel, add your Anthropic Claude or Google Gemini API key and click <strong>Test Key</strong> to confirm it works. (<a href="https://console.anthropic.com/" target="_blank" rel="noopener">Get a Claude key</a> · <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Get a Gemini key</a>)</li>
<li>In the <strong>SEO Features</strong> panel (Sitemap &amp; Robots tab), tick the features you want active and save.</li>
<li>In the <strong>AI Tools</strong> tab, click <strong>Generate Missing</strong> in the Meta Description Writer to fill in all missing descriptions.</li>
<li>Submit your sitemap URL to <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>.</li>
</ol>
</div>

<hr class="cs-divider"/>
`;

    for (const [tabKey, panels] of Object.entries(panelsByTab)) {
        body += `\n<div class="cs-tab-section">\n<h2 class="cs-tab-heading" id="${tabKey}">${TAB_TITLES[tabKey] || tabKey}</h2>\n<p class="cs-tab-intro">${TAB_INTROS[tabKey] || ''}</p>\n`;
        for (const p of panels) {
            body += section(p);
            body += '\n<hr class="cs-divider"/>\n';
            // Inject text-only sections for API-key-gated panels
            if (p.cardClass === 'ab-card-ai') {
                body += textSection('ab-card-auto-pipeline', 'Auto Pipeline', DOCS['ab-card-auto-pipeline']);
                body += '\n<hr class="cs-divider"/>\n';
            }
            if (p.cardClass === 'ab-card-render') {
                body += textSection('ab-card-https', 'HTTPS URL Fixer', DOCS['ab-card-https']);
                body += '\n<hr class="cs-divider"/>\n';
            }
        }
        body += `</div>\n`;
    }

    // Glossary
    body += `
<div class="cs-glossary" id="glossary">
<h2>Glossary of terms</h2>
<dl>
<dt>AEO</dt><dd>Answer Engine Optimisation — the practice of structuring content to be understood and surfaced by AI-powered answer engines such as Google's AI Overviews, Perplexity, and ChatGPT Browse, as well as traditional search engines.</dd>
<dt>API</dt><dd>Application Programming Interface — a way for software to communicate with another service. In this plugin, you supply an API key so WordPress can send requests to Anthropic Claude or Google Gemini to generate content.</dd>
<dt>ALT text</dt><dd>Alternative text — a written description of an image, stored in the HTML <code>alt</code> attribute. Read by screen readers for accessibility and used by search engines to understand image content.</dd>
<dt>Canonical URL</dt><dd>The preferred URL for a page when multiple URLs could serve the same content. Set via <code>&lt;link rel="canonical" href="..."&gt;</code> to tell search engines which version to index.</dd>
<dt>CDN</dt><dd>Content Delivery Network — a globally distributed network of servers that delivers files (e.g. fonts, images, scripts) to visitors from the closest physical location, reducing load time.</dd>
<dt>CSS</dt><dd>Cascading Style Sheets — the language that controls the visual appearance of web pages.</dd>
<dt>E-E-A-T</dt><dd>Experience, Expertise, Authoritativeness, Trustworthiness — Google's quality evaluation framework for web content. Person schema and author information contribute to E-E-A-T signals.</dd>
<dt>GDPR</dt><dd>General Data Protection Regulation — EU privacy law requiring disclosure and often consent for processing personal data, including IP addresses sent to third-party services such as Google Fonts CDN.</dd>
<dt>JSON-LD</dt><dd>JavaScript Object Notation for Linked Data — a format for embedding structured data in a web page. Google reads JSON-LD to understand page type, author, organisation, and content, enabling rich search results.</dd>
<dt>JS</dt><dd>JavaScript — the programming language that runs in web browsers. Deferring JS improves page load speed.</dd>
<dt>LCP</dt><dd>Largest Contentful Paint — a Core Web Vitals metric measuring how long it takes the largest visible element (usually an image or heading) to appear. Google uses LCP as a ranking factor.</dd>
<dt>llms.txt</dt><dd>A plain-text file at <code>/llms.txt</code> that provides a structured summary of your website for AI language models. Analogous to <code>robots.txt</code> but for AI systems.</dd>
<dt>noindex</dt><dd>A directive in the <code>&lt;meta name="robots"&gt;</code> tag that tells search engines not to include a page in their index. The page can still be crawled but will not appear in search results.</dd>
<dt>OG / OpenGraph</dt><dd>OpenGraph — a protocol developed by Facebook that standardises how web pages are represented when shared on social media. OG tags control the title, description, and image shown in social previews.</dd>
<dt>SEO</dt><dd>Search Engine Optimisation — the practice of improving a website's visibility in organic (unpaid) search engine results.</dd>
<dt>Schema / Structured Data</dt><dd>Machine-readable data embedded in a web page (using formats like JSON-LD) that tells search engines what a page is about — its type, author, publication date, etc. Can enable rich results in Google Search.</dd>
<dt>TTI</dt><dd>Time to Interactive — a performance metric measuring how long until a page is fully interactive. Deferring JavaScript improves TTI.</dd>
<dt>UTM</dt><dd>Urchin Tracking Module — parameters appended to URLs for marketing analytics (e.g. <code>?utm_source=newsletter</code>). Should be stripped from canonical URLs to prevent duplicate content.</dd>
<dt>XML</dt><dd>eXtensible Markup Language — the format used for sitemaps. An XML sitemap is a structured list of URLs that search engines can parse to discover your content.</dd>
</dl>
</div>
`;

    return `<!-- wp:html -->\n<div class="cs-help-docs" style="max-width:900px;margin:0 auto;">\n${body}\n</div>\n<!-- /wp:html -->`;
}

// ── Main ──────────────────────────────────────────────────────────────────────

(async () => {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });

    // Inject WordPress auth cookies
    const c = COOKIES;
    await context.addCookies([
        { name: c.auth_name,  value: c.auth_value,  domain: c.domain, path: '/wp-admin',   httpOnly: true, secure: true, sameSite: 'None', expires: c.expiration },
        { name: c.auth_name,  value: c.auth_value,  domain: c.domain, path: '/wp-content', httpOnly: true, secure: true, sameSite: 'None', expires: c.expiration },
        { name: c.sec_name,   value: c.sec_value,   domain: c.domain, path: '/wp-admin',   httpOnly: true, secure: true, sameSite: 'None', expires: c.expiration },
        { name: c.sec_name,   value: c.sec_value,   domain: c.domain, path: '/wp-content', httpOnly: true, secure: true, sameSite: 'None', expires: c.expiration },
        { name: c.login_name, value: c.login_value, domain: c.domain, path: '/',           httpOnly: true, secure: true, sameSite: 'None', expires: c.expiration },
    ]);

    const page = await context.newPage();
    let currentTab = null;

    console.log('\nNavigating to plugin admin page...');
    await page.goto(PLUGIN_PAGE, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.ab-tabs', { timeout: 20000 });

    // Remove admin notices that could overlap content
    await page.evaluate(() => {
        document.querySelectorAll('.notice, .update-nag, .wp-pointer').forEach(el => el.remove());
    });

    console.log('\nCapturing panel screenshots...');
    const imageMap = {};

    for (const panel of PANELS) {
        process.stdout.write(`  [${panel.tab}] ${panel.label}... `);

        // Switch tab if needed
        if (panel.tab !== currentTab) {
            await page.click(`[data-tab="${panel.tab}"]`);
            await page.waitForTimeout(500);
            currentTab = panel.tab;
        }

        const card = page.locator(`.${panel.cardClass}`).first();

        // Expand collapsed cards before screenshotting — this also triggers auto-load for AI Tools panels
        if (panel.expand) {
            const toggleBtn = card.locator('.ab-toggle-card-btn').first();
            const btnText = await toggleBtn.innerText().catch(() => '');
            if (btnText.includes('Show Details') || btnText.includes('▶')) {
                await toggleBtn.click();
                // Wait for async content to load (abLoadPosts / altLoad / sumLoad fire after expand)
                await page.waitForTimeout(3500);
            }
        }

        // RC table is not auto-loaded by toggle — call rcLoadTable explicitly
        if (panel.rcTrigger) {
            await page.evaluate(() => { if (typeof rcLoadTable === 'function') rcLoadTable(1, 'all'); });
            await page.waitForTimeout(3000);
        }

        // Category Fixer: click Scan Posts and wait for at least 2 rows
        if (panel.preScan) {
            const scanBtn = page.locator('#cf-scan-btn');
            if (await scanBtn.isVisible().catch(() => false)) {
                await scanBtn.click();
                await page.waitForFunction(
                    () => { const s = document.getElementById('cf-status'); return s && s.textContent && !s.textContent.includes('Fetching') && !s.textContent.includes('Scanning'); },
                    { timeout: 30000 }
                ).catch(() => {});
                await page.waitForTimeout(800);
            }
        }

        // Screenshot the body only (not the card header) — avoids duplicate title in the docs page
        const bodyEl = card.locator('.ab-zone-body').first();
        await bodyEl.scrollIntoViewIfNeeded();
        await page.waitForTimeout(300);

        const outPath = path.join(SCREENSHOTS, panel.file);

        if (panel.trimRows) {
            // Hide table rows beyond the first 2 before screenshotting, then restore
            await page.evaluate((cls) => {
                const c = document.querySelector('.' + cls);
                if (!c) return;
                c.querySelectorAll('tbody tr').forEach((row, i) => {
                    row.dataset.docHidden = i >= 2 ? '1' : '0';
                    if (i >= 2) row.style.display = 'none';
                });
            }, panel.cardClass);

            await bodyEl.screenshot({ path: outPath, animations: 'disabled' });

            await page.evaluate((cls) => {
                const c = document.querySelector('.' + cls);
                if (!c) return;
                c.querySelectorAll('tbody tr').forEach(row => { row.style.display = ''; delete row.dataset.docHidden; });
            }, panel.cardClass);
        } else {
            await bodyEl.screenshot({ path: outPath, animations: 'disabled' });
        }

        // Store locally first
        imageMap[panel.file] = `screenshots/${panel.file}`;
        console.log('done');
    }

    await browser.close();

    // Build page content with local screenshot paths first
    const content = buildHtml(imageMap);

    // Save local HTML file before uploading
    const localHtml = `<!DOCTYPE html>\n<html lang="en">\n<head><meta charset="UTF-8"><title>CloudScale SEO AI Optimizer — Help</title></head>\n<body>\n${content}\n</body>\n</html>`;
    const localPath = path.join(DOCS_DIR, 'help-page.html');
    fs.writeFileSync(localPath, localHtml, 'utf8');
    console.log(`\nLocal HTML saved: ${localPath}`);

    // Upload screenshots to WordPress Media Library
    console.log('\nUploading screenshots to WordPress Media Library...');
    for (const panel of PANELS) {
        process.stdout.write(`  Uploading ${panel.file}... `);
        const filePath = path.join(SCREENSHOTS, panel.file);
        const { url } = await uploadMedia(filePath, panel.file);
        imageMap[panel.file] = url;
        console.log('done');
    }

    // Rebuild HTML with real Media Library URLs
    const finalContent = buildHtml(imageMap);

    // Update the local file with live URLs too
    const finalHtml = `<!DOCTYPE html>\n<html lang="en">\n<head><meta charset="UTF-8"><title>CloudScale SEO AI Optimizer — Help</title></head>\n<body>\n${finalContent}\n</body>\n</html>`;
    fs.writeFileSync(localPath, finalHtml, 'utf8');

    // Find or create the parent page, then publish child under it
    console.log('\nSetting up parent page...');
    const parentId = await findOrCreateParentPage();

    console.log('Publishing to WordPress...');
    const { id, url } = await createOrUpdatePage('CloudScale SEO AI Optimizer — Help & Documentation', finalContent, 'seo-ai-optimizer', parentId);
    console.log(`  Page ID  : ${id}`);
    console.log(`  Page URL : ${url}`);

    console.log('Updating parent index...');
    await updateParentIndex(parentId);
    console.log('  Parent index updated.');

})().catch(err => {
    console.error('\nERROR:', err.message);
    process.exit(1);
});
