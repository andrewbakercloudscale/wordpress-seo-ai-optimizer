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
<p>The <strong>Site Identity</strong> panel controls the core information used across all SEO (Search Engine Optimisation) outputs — meta tags, OG (OpenGraph) tags, and JSON-LD (JavaScript Object Notation for Linked Data) structured data.</p>
<ul>
<li><strong>Site name</strong> — The name of your website as it should appear in search results and social media previews. Used in the <code>og:site_name</code> tag and WebSite JSON-LD schema.</li>
<li><strong>Locale</strong> — Your language and region code in the format <code>en-US</code>, <code>en-GB</code>, <code>fr-FR</code>, etc. Used in the HTML <code>lang</code> attribute and the <code>og:locale</code> OpenGraph tag. This helps Google serve the right language to the right audience.</li>
<li><strong>Title suffix</strong> — Text appended to every page title in the browser tab and search results, e.g. <code> | My Blog</code>. Helps users recognise your brand when multiple tabs are open.</li>
<li><strong>Twitter handle</strong> — Your Twitter/X username including the @ symbol, e.g. <code>@yourusername</code>. Added to the <code>twitter:site</code> meta tag so Twitter can attribute your shared content.</li>
<li><strong>Home title</strong> — The SEO title specifically for your homepage. This overrides the default WordPress site title for the front page only.</li>
<li><strong>Default OG image URL</strong> — A fallback image used in social media previews when a post or page has no featured image. Recommended size: 1200 × 630 pixels. A visually consistent default makes your links look professional when shared on Facebook, LinkedIn, Slack, etc.</li>
</ul>`,

'ab-card-person': `
<p>The <strong>Person Schema</strong> panel adds JSON-LD structured data that identifies you as the author and owner of the website. This is especially important for personal blogs and authority sites — Google uses Person schema to associate your content with your real-world identity, which supports E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) signals.</p>
<ul>
<li><strong>Name</strong> — Your full name as you want it to appear in structured data and author rich results.</li>
<li><strong>Job title</strong> — Your professional title (e.g. "Software Engineer", "Technical Writer"). Appears in Person structured data.</li>
<li><strong>URL</strong> — The canonical URL for your identity, usually your homepage or an about page.</li>
<li><strong>Person image URL</strong> — A headshot or profile photo URL. Recommended: square image, at least 400 × 400 pixels. Used in Person JSON-LD and can appear in Google's Knowledge Panel.</li>
<li><strong>sameAs URLs</strong> — Links to your profiles on other authoritative platforms: LinkedIn, GitHub, Twitter, Wikipedia, Wikidata, etc. These cross-references help Google confirm your identity. Add one URL per line.</li>
</ul>
<p>This panel only affects the JSON-LD output in your page source — nothing visible is added to the page itself.</p>`,

'ab-card-ai': `
<p>The <strong>AI (Artificial Intelligence) Settings</strong> panel connects the plugin to your chosen AI provider. The plugin uses AI to generate meta descriptions, ALT text, article summaries, and related article suggestions. You supply your own API (Application Programming Interface) key — there is no shared key and no usage limit imposed by this plugin.</p>
<ul>
<li><strong>AI provider</strong> — Choose between <strong>Anthropic Claude</strong> or <strong>Google Gemini</strong>:
  <ul>
    <li>To get a Claude API key: visit <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a> → sign up → API Keys → Create Key. Claude is highly capable at writing natural, accurate meta descriptions.</li>
    <li>To get a Gemini API key: visit <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">aistudio.google.com/app/apikey</a> → sign in with your Google account → Create API Key. Gemini has a generous free tier.</li>
  </ul>
</li>
<li><strong>API key</strong> — Paste your key here. It is stored in your WordPress database and sent only to the chosen provider's API endpoint. It is never shared with this plugin's author or any third party.</li>
<li><strong>Model</strong> — The specific AI model to use. Leave blank to use the provider's recommended default. Advanced users can specify a model ID (e.g. <code>claude-haiku-4-5-20251001</code> for faster/cheaper processing, <code>claude-opus-4-6</code> for higher quality).</li>
<li><strong>Meta description character targets (min / max)</strong> — Google displays meta descriptions up to approximately 158 characters in search results. The default range of 120–158 characters produces descriptions that display fully without being truncated. Descriptions below 120 characters may appear thin; descriptions above 158 will be cut off.</li>
<li><strong>ALT text excerpt length</strong> — How many characters of post content to send to the AI when generating image ALT text. A longer excerpt gives the AI more context but costs slightly more API tokens. Default 600 characters is sufficient for most images.</li>
</ul>
<p><strong>Test your key</strong> using the "Test Key" button before running bulk operations — this makes a minimal API call to confirm your key is valid and your account has available credits.</p>`,

'ab-card-auto-pipeline': `
<p>The <strong>Auto Pipeline</strong> automatically processes each post the moment it is published — with no manual intervention required. When enabled, publishing a post triggers: meta description generation → SEO scoring → AI summary box creation, all in the background.</p>
<ul>
<li><strong>Enable Auto Pipeline on publish</strong> — When ticked, every new post you publish will automatically receive an AI-generated meta description, an SEO score, and an AI summary box. Untick this if you prefer to generate content manually or in scheduled batches.</li>
<li><strong>Overwrite existing descriptions</strong> — When ticked, the pipeline will regenerate the meta description even if one already exists. Leave unticked to only fill in missing descriptions (safer for existing content).</li>
</ul>
<p>The Auto Pipeline runs synchronously on publish — the post save may take a couple of seconds longer than usual while the AI generates content. If your API key has rate limits or if you publish very frequently, consider using Scheduled Batch processing instead.</p>`,

'ab-card-update-posts': `
<p>The <strong>AI Meta Description Writer</strong> is the main bulk AI tool. A meta description is the short paragraph that appears under your page title in Google Search results — it is the first thing a searcher reads and directly influences whether they click through to your site.</p>
<p>The table shows all your posts and pages with their current meta description status:</p>
<ul>
<li><strong>Has desc</strong> — A meta description is already written. Shows the current text and character count.</li>
<li><strong>Missing</strong> — No meta description has been written. These are the highest priority to fix.</li>
<li><strong>Too short / Too long</strong> — A description exists but falls outside your configured character range. Descriptions under 120 characters may appear thin; over 158 characters will be cut off by Google.</li>
<li><strong>SEO score</strong> — A 0–100 score produced by the AI evaluating the description's relevance, keyword presence, and readability. Scores above 70 are good; below 50 indicate the description needs improvement.</li>
</ul>
<p>Available actions:</p>
<ul>
<li><strong>✦ Generate</strong> (per row) — Generates an AI description for that specific post.</li>
<li><strong>Generate Missing</strong> — Generates descriptions for all posts that have none. Skips posts that already have one.</li>
<li><strong>Generate All (overwrite)</strong> — Regenerates descriptions for every post, replacing any existing ones.</li>
<li><strong>Fix Out-of-Range</strong> — Regenerates only the descriptions that are too short or too long.</li>
<li><strong>Score All</strong> — Runs the AI scoring pass on all posts without regenerating descriptions.</li>
<li><strong>Stop</strong> — Halts a running bulk operation at the end of the current post.</li>
</ul>`,

'ab-card-alt': `
<p>The <strong>AI ALT Text (Alternative Text) Generator</strong> scans your site for images missing their ALT attribute and uses AI to write descriptive, contextually relevant ALT text for each one.</p>
<p><strong>Why ALT text matters:</strong> ALT text serves two purposes. First, <strong>accessibility</strong> — screen readers used by visually impaired visitors read the ALT text aloud instead of the image. Second, <strong>SEO</strong> — search engine crawlers cannot see images, so they rely on ALT text to understand what an image contains. Well-written ALT text can help images rank in Google Image Search and reinforces the keyword relevance of the surrounding content.</p>
<ul>
<li><strong>Scan for missing ALT text</strong> — Finds all images attached to your posts and pages that have an empty or missing ALT attribute.</li>
<li><strong>Show all</strong> checkbox — When ticked, shows every image including those that already have ALT text. Useful for reviewing or overwriting existing ALT attributes.</li>
<li><strong>✦ Generate</strong> (per image) — Sends the image and surrounding post context to the AI, which writes a concise, descriptive ALT attribute and saves it to the WordPress attachment metadata.</li>
<li><strong>Generate All Missing</strong> — Processes all images with no ALT text in one bulk operation.</li>
</ul>
<p>The AI receives a short excerpt of the post content around the image to provide context — this is why the "ALT text excerpt length" setting in AI Settings matters.</p>`,

'ab-card-summary': `
<p>The <strong>AI Summary Box</strong> generates a three-field structured summary for each post and displays it as a styled card at the top of the post content — before the article text begins. This serves two purposes: it helps readers quickly decide if the article is relevant to them (reducing bounce rate), and it writes content directly into the Article JSON-LD schema, which can appear as structured snippets in search results.</p>
<p>The three fields generated per post:</p>
<ul>
<li><strong>What it is</strong> — A one-sentence definition of the topic.</li>
<li><strong>Why it matters</strong> — A one-sentence explanation of why the reader should care.</li>
<li><strong>Key takeaway</strong> — The single most important thing to take away from the article.</li>
</ul>
<p>Available actions:</p>
<ul>
<li><strong>✦ Generate</strong> (per row) — Generates or regenerates the summary for a single post.</li>
<li><strong>Generate Missing</strong> — Generates summaries for all posts that do not yet have one.</li>
<li><strong>Generate All (force)</strong> — Regenerates summaries for every post, replacing existing ones.</li>
</ul>
<p>Whether the summary box is visible on the frontend is controlled by the <strong>"Show AI summary box on posts"</strong> checkbox in the SEO Features panel. Unticking that hides the box without deleting the generated summaries — they remain in the database and in the JSON-LD schema.</p>`,

'ab-card-rc-settings-card': `
<p><strong>Related Articles</strong> is one of the key AEO (Answer Engine Optimisation) features of this plugin. AEO is the practice of structuring your content to be understood and surfaced by AI-powered answer engines (such as Google's AI Overviews, Perplexity, and ChatGPT's Browse feature) as well as traditional search engines.</p>
<p>Related Articles automatically inserts links to contextually similar posts at the top and bottom of each post. This creates an internal linking network that helps both readers and search engine crawlers discover more of your content.</p>
<ul>
<li><strong>Enable Related Articles and You Might Also Like on posts</strong> — Master switch. Untick to disable all related article output site-wide without losing your settings.</li>
<li><strong>Show "Related Articles" block at the top</strong> — Adds a "Related Articles" section immediately after the AI summary box (or at the top of the post if no summary box is present). Good for keeping readers on your site after reading the opening.</li>
<li><strong>Number of links (top)</strong> — How many related post links to show in the top block. 3–4 is recommended; too many links before the article content can feel intrusive.</li>
<li><strong>Show "You Might Also Like" block at the bottom</strong> — Adds a "You Might Also Like" section at the end of the post, before comments. This is the traditional position for related content and tends to have higher engagement.</li>
<li><strong>Number of links (bottom)</strong> — How many links to show in the bottom block. 5 is a good default.</li>
<li><strong>Pool size</strong> — The number of candidate posts to consider when scoring relatedness. A larger pool gives better results but takes marginally longer to process. Default 20 is sufficient for most sites.</li>
<li><strong>Match by Categories</strong> — Include posts from the same categories as a relevance signal. Recommended on.</li>
<li><strong>Match by Tags</strong> — Include posts sharing tags as a relevance signal. Recommended on if you use tags consistently.</li>
<li><strong>Match by AI summary overlap</strong> — Use semantic similarity between AI-generated summaries to find related posts. This produces the most contextually accurate matches but requires summaries to have been generated first.</li>
<li><strong>Excluded categories</strong> — Posts in these categories will never appear as related article suggestions. Useful for excluding promotional posts, announcements, or unrelated categories.</li>
</ul>`,

'ab-card-rc-table': `
<p>The <strong>Related Articles Management</strong> table shows the processing status of every post. After changing your Related Articles settings, you need to regenerate the related links for existing posts.</p>
<ul>
<li><strong>Status badges:</strong>
  <ul>
    <li><strong>Pending</strong> — Related articles have not yet been generated for this post.</li>
    <li><strong>Complete</strong> — Related articles have been generated and are live.</li>
    <li><strong>Error</strong> — Something went wrong during generation. The error message is shown in the row.</li>
  </ul>
</li>
<li><strong>Top / Bottom counts</strong> — How many links are currently being shown at the top and bottom of the post.</li>
<li><strong>▶ Run</strong> (per row) — Generates or regenerates related articles for a single post.</li>
<li><strong>🗑 Reset</strong> (per row) — Clears the related articles data for a post, setting it back to Pending.</li>
<li><strong>Batch buttons</strong> — Run all Pending posts, re-run all Complete posts (useful after settings changes), or re-run all Error posts.</li>
<li><strong>Sync Counts</strong> — Recalculates the top/bottom counts for all posts without regenerating the related links.</li>
</ul>`,

'ab-card-features': `
<p>The <strong>SEO Features</strong> panel is the master control board — a single checklist of every SEO feature the plugin provides. Tick the features you want active; untick to disable them without losing any settings.</p>
<ul>
<li><strong>OpenGraph + Twitter Cards</strong> — Adds <code>og:*</code> and <code>twitter:*</code> meta tags to every page. These tags control how your pages appear when shared on Facebook, LinkedIn, Twitter/X, Slack, WhatsApp, and iMessage — including the preview image, title, and description. <em>Strongly recommended.</em></li>
<li><strong>WebSite JSON-LD (front page)</strong> — Adds <code>{"@type":"WebSite"}</code> structured data to your homepage. This enables a <strong>Sitelinks Searchbox</strong> in Google Search results (a search box directly under your homepage result). <em>Recommended.</em></li>
<li><strong>Person JSON-LD schema</strong> — Outputs your Person structured data (configured in the Person Schema panel) on author and homepage templates. Supports Google's Knowledge Panel and E-E-A-T signals. <em>Recommended for personal sites and blogs.</em></li>
<li><strong>BlogPosting JSON-LD schema</strong> — Adds <code>{"@type":"BlogPosting"}</code> or <code>{"@type":"Article"}</code> structured data to every post. Helps Google understand your content type and can enable rich results including article dates and author details. <em>Recommended.</em></li>
<li><strong>Breadcrumb JSON-LD schema</strong> — Adds <code>{"@type":"BreadcrumbList"}</code> structured data. This can display the page's position in your site hierarchy directly in Google Search results (e.g. Home › Category › Post Title). Useful for sites with a clear hierarchy.</li>
<li><strong>Show AI summary box on posts</strong> — Controls whether the AI-generated summary card is visible on the frontend. Untick to hide it without deleting the underlying data. <em>Recommended if summaries have been generated.</em></li>
<li><strong>Strip UTM params in canonical URLs</strong> — UTM (Urchin Tracking Module) parameters (e.g. <code>?utm_source=newsletter</code>) are used for marketing analytics but should not appear in canonical URLs. Enabling this strips them from the <code>rel="canonical"</code> tag, preventing Google from indexing tracking URLs as separate pages. <em>Recommended if you use UTM tracking.</em></li>
<li><strong>Enable /sitemap.xml</strong> — Activates the XML sitemap. Same as the toggle in the Sitemap Settings panel below. <em>Recommended.</em></li>
<li><strong>noindex search results</strong> — Adds a <code>&lt;meta name="robots" content="noindex"&gt;</code> tag to WordPress search results pages (URLs containing <code>?s=</code>). Search results pages are thin, auto-generated content that Google should not index. <em>Recommended.</em></li>
<li><strong>noindex 404 pages</strong> — Adds noindex to 404 error pages. These pages have no content and should never be indexed. <em>Recommended.</em></li>
<li><strong>noindex attachment pages</strong> — WordPress automatically creates a page for every uploaded file (e.g. <code>/your-site.com/?attachment_id=123</code>). These pages contain almost no content and dilute your site's quality signal. <em>Recommended.</em></li>
<li><strong>noindex author archives</strong> — Author archive pages (e.g. <code>/author/username/</code>) duplicate post content. If your site has only one author, noindexing these removes a source of duplicate content. Use with caution on multi-author sites where author pages have unique value.</li>
<li><strong>noindex tag archives</strong> — Tag pages can produce thin, low-value content. Noindex if your tags are loosely applied. Leave indexed if your tag pages are genuinely curated topic hubs.</li>
</ul>`,

'ab-card-sitemap-settings': `
<p>The <strong>XML Sitemap</strong> is a file at <code>/sitemap.xml</code> that lists every important URL on your site. Search engines use it to discover and re-crawl your content efficiently. Without a sitemap, Google may miss pages or take longer to find new content.</p>
<ul>
<li><strong>Enable /sitemap.xml</strong> — Activates the sitemap. Once enabled, visit <code>yoursite.com/sitemap.xml</code> to confirm it is working, then submit it in <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a>.</li>
<li><strong>Post types to include</strong> — Tick each post type (Posts, Pages, and any custom post types) that should be included in the sitemap. Exclude post types that do not contain indexable public content.</li>
<li><strong>Include taxonomy pages</strong> — When ticked, category and tag archive pages are included in the sitemap. Only enable this if those pages have unique, valuable content worth indexing.</li>
<li><strong>Sitemap exclude list</strong> — A list of URL paths to exclude from the sitemap, one per line. Use this for pages like <code>/privacy-policy/</code> or <code>/contact/</code> that you do not want prominently crawled.</li>
</ul>
<p>The sitemap is automatically paginated — if you have more than 5,000 URLs, the plugin creates multiple child sitemaps (<code>/sitemap-1.xml</code>, <code>/sitemap-2.xml</code>, etc.) and lists them in the index at <code>/sitemap.xml</code>. A plain-text version is also available at <code>/sitemap.txt</code>.</p>
<p>Use the <strong>Sitemap Preview</strong> panel to browse the generated sitemap directly in your admin area.</p>`,

'ab-card-robots': `
<p>The <strong>Robots.txt Editor</strong> lets you manage your site's <code>/robots.txt</code> file — the first file a search engine crawler reads when it arrives at your domain. It tells crawlers which parts of your site they are allowed to access.</p>
<ul>
<li><strong>Block AI bots</strong> — When ticked, adds <code>Disallow</code> rules for known AI content scrapers including GPTBot (OpenAI), CCBot (Common Crawl), Claude-Web (Anthropic), anthropic-ai, Google-Extended, and others. These bots crawl your content to train AI language models. Tick this if you do not want your content used for AI training.</li>
<li><strong>Custom robots.txt rules</strong> — A freeform text editor for your complete robots.txt content. The plugin provides sensible defaults. You can add custom rules such as:
  <ul>
    <li><code>Disallow: /wp-admin/</code> — Prevent crawling the admin area (usually already blocked by WordPress defaults)</li>
    <li><code>Disallow: /checkout/</code> — Block crawling of WooCommerce checkout pages</li>
    <li><code>Sitemap: https://yoursite.com/sitemap.xml</code> — Advertise your sitemap location (added automatically when sitemap is enabled)</li>
  </ul>
</li>
</ul>
<p><strong>Important:</strong> Robots.txt controls crawl access, not indexing. A page blocked by robots.txt may still appear in Google's index if other sites link to it — it just won't be re-crawled. To prevent indexing, use the noindex meta tag instead.</p>`,

'ab-card-llms': `
<p><strong>llms.txt</strong> is an emerging standard (similar to <code>robots.txt</code> but for AI language models) that provides a structured, human-readable summary of your website for AI crawlers and chat interfaces. When an AI assistant like ChatGPT, Claude, or Perplexity wants to understand what your site is about, it looks for this file at <code>/llms.txt</code>.</p>
<ul>
<li><strong>Enable /llms.txt</strong> — Activates the endpoint. Once enabled, visit <code>yoursite.com/llms.txt</code> to see the generated file. It lists your key pages, recent posts, and a brief description of your site.</li>
</ul>
<p>Publishing an <code>llms.txt</code> file is an AEO (Answer Engine Optimisation) best practice. It gives you control over how AI systems summarise and represent your content — rather than letting them infer it from raw HTML. The <a href="https://llmstxt.org/" target="_blank" rel="noopener">llms.txt specification</a> is maintained as an open standard.</p>`,

'ab-card-https': `
<p>The <strong>HTTPS URL Fixer</strong> scans your WordPress database for hardcoded <code>http://</code> URLs and replaces them with <code>https://</code>. This is essential after migrating a site from HTTP to HTTPS — old HTTP links in your content cause mixed-content browser warnings and can prevent pages from loading correctly over a secure connection.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan</strong> to find all HTTP URLs currently in your database.</li>
<li>Review the results — the scanner shows which domains it found and how many occurrences of each.</li>
<li>Tick the domains you want to fix (typically your own domain).</li>
<li>Click <strong>Fix Selected</strong> to replace those URLs with HTTPS equivalents.</li>
</ol>
<p>The fix updates post content, post meta, and common option values. It does not modify PHP files or your theme — only database content. Always take a database backup before running bulk find-and-replace operations.</p>`,

'ab-card-fonts': `
<p>The <strong>Font Optimiser</strong> downloads Google Fonts to your own server and replaces the external Google Fonts CDN (Content Delivery Network) requests with locally hosted files. This solves two problems:</p>
<ol>
<li><strong>Performance</strong> — Each Google Fonts request adds a DNS lookup and network round-trip that delays page rendering. Hosting fonts locally eliminates this latency and can improve your Core Web Vitals scores, particularly LCP (Largest Contentful Paint).</li>
<li><strong>Privacy / GDPR (General Data Protection Regulation)</strong> — When a visitor loads a Google Font from Google's CDN, their IP address is sent to Google. Under GDPR this is a data transfer to a third party that requires disclosure and potentially consent. Hosting fonts locally keeps all data on your own server.</li>
</ol>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan &amp; Download</strong> — the plugin detects Google Fonts CDN URLs used by your theme and downloads the font files to your server.</li>
<li>Click <strong>Apply Fixes</strong> — the plugin rewrites the font references in your theme to point to the local files.</li>
<li>Click <strong>Undo</strong> if you need to revert the changes.</li>
</ol>`,

'ab-card-render': `
<p>The <strong>JS, CSS &amp; HTML Optimisation</strong> panel provides three performance improvements that reduce page load time.</p>
<ul>
<li><strong>Defer non-Google fonts</strong> — Loads non-critical font files asynchronously so they do not block the initial page render. Fonts that are not in the critical rendering path are loaded after the visible content appears.</li>
<li><strong>Defer JavaScript</strong> — Adds the <code>defer</code> attribute to non-critical JavaScript files. By default, the browser pauses HTML parsing whenever it encounters a <code>&lt;script&gt;</code> tag. Deferring scripts allows the page to render first and execute scripts afterwards, significantly reducing Time to Interactive (TTI). You can add a list of JS file patterns to <em>exclude</em> from deferring — add any scripts that must run before the page is visible (e.g. analytics, anti-flicker snippets).
  <br><strong>Note:</strong> If deferring JS causes layout issues or broken functionality, check the exclusion list first.
</li>
<li><strong>Minify HTML output</strong> — Removes unnecessary whitespace, comments, and line breaks from the HTML sent to the browser. A typical page can be 10–15% smaller after minification, reducing bandwidth usage and transfer time. This is a safe optimisation that has no effect on how the page looks or functions.</li>
</ul>`,

'ab-card-catfix': `
<p>The <strong>Category Fixer</strong> analyses every published post on your site and suggests whether each post is in the most appropriate category. Posts that are miscategorised hurt your site's topical authority — Google prefers sites where each category contains a coherent set of closely related content.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Scan Posts</strong> — the plugin scans all published posts and proposes category changes based on keyword scoring and AI analysis.</li>
<li>Review the results table. Each row shows the post title, its current categories, and the proposed categories.</li>
<li>Click <strong>Apply</strong> on individual rows to accept the suggestion, or <strong>Skip</strong> to dismiss it.</li>
<li>Use <strong>Apply All Changed</strong> to accept all pending suggestions at once.</li>
</ol>
<p><strong>Filter tabs</strong> at the top let you view: All posts, Changed (proposals pending), Unchanged (no proposal), Low-confidence proposals, and posts with no category assigned.</p>
<p>The <strong>🤖 AI Analyse</strong> button re-analyses selected posts using your AI provider for more nuanced category suggestions beyond the keyword-scoring approach.</p>`,

'ab-card-cathealth': `
<p>The <strong>Category Health Dashboard</strong> grades every category on your site based on how many published posts it contains and how recently they were written. Weak or empty categories dilute your site's topical focus and waste crawl budget.</p>
<p><strong>Health grades:</strong></p>
<ul>
<li><strong>Strong</strong> (green) — 10 or more published posts. Well established.</li>
<li><strong>Moderate</strong> (orange) — 4 to 9 posts. Healthy but could grow.</li>
<li><strong>New</strong> (blue) — 1 to 3 posts, all published within the last 180 days. Growing topic — do not merge or delete.</li>
<li><strong>Weak</strong> (yellow) — 2 to 3 posts, none recent. Consider whether this topic needs its own category or should be merged into a broader one.</li>
<li><strong>Empty</strong> (red) — 0 or 1 posts. This category adds no value to your taxonomy. Consider deleting it or reassigning the post.</li>
<li><strong>Uncategorized</strong> (grey) — WordPress's default fallback category. Posts here were never properly assigned a category.</li>
</ul>
<p>Click any grade pill at the top to filter the table. Click <strong>▼ Show posts</strong> in any row to see which posts are in that category. Click <strong>Edit</strong> to open the category editor.</p>`,

'ab-card-catdrift': `
<p><strong>Category Drift Detection</strong> uses AI to identify categories that are being used as "catch-alls" — containing posts on unrelated topics that would be better served by more specific categories. Drift weakens your site's topical authority because it sends mixed signals to search engines about what a category is actually about.</p>
<p><strong>How to use it:</strong></p>
<ol>
<li>Click <strong>Run Fresh AI Analysis</strong> — the AI reads a sample of post titles from each category and identifies semantic drift.</li>
<li>Click <strong>Load Cached Results</strong> to reload the last analysis without making another API call (results are cached).</li>
<li>Review the results. Each flagged category shows:
  <ul>
    <li><strong>Verdict</strong> — <em>Catch-all</em> (posts on completely unrelated topics) or <em>Drifting</em> (loosely related but diverging).</li>
    <li><strong>Confidence</strong> — How certain the AI is: High, Medium, or Low.</li>
    <li><strong>AI Reasoning</strong> — A brief explanation of why this category was flagged.</li>
    <li><strong>Where to move posts</strong> — Specific suggestions for which posts should move to which category, with a reason for each move. Click <strong>→ Move</strong> on individual posts or <strong>→ Move all</strong> to apply the suggestion.</li>
  </ul>
</li>
<li>Use <strong>🤖 Analyse N remaining</strong> to ask the AI to suggest destinations for any posts not yet covered by the initial analysis.</li>
</ol>
<p>Move actions update the post's WordPress category assignments immediately. The cache is updated on each move so refreshing the page shows the current state.</p>`,

'ab-card-schedule': `
<p>The <strong>Scheduled Batch</strong> panel configures automatic AI processing to run on a recurring schedule — so your new content is always processed without any manual effort.</p>
<ul>
<li><strong>Enable scheduled batch processing</strong> — Master switch. When ticked, a WordPress Cron job runs the batch processor at the configured frequency.</li>
<li><strong>Schedule frequency</strong> — How often to run: Hourly, Twice Daily, or Daily. Choose based on how frequently you publish new content. Daily is sufficient for most blogs; Hourly is useful if you publish multiple times per day.</li>
<li><strong>Generate missing meta descriptions</strong> — When ticked, the batch will generate AI meta descriptions for any posts that do not have one.</li>
<li><strong>Generate missing AI summaries</strong> — When ticked, the batch will generate summary boxes for posts that do not have one.</li>
<li><strong>Score posts</strong> — When ticked, runs the SEO scoring pass on posts that have not been scored yet.</li>
</ul>
<p>The batch processor only processes posts that are missing the specific content type — it will not overwrite manually written descriptions or existing summaries. To force regeneration, use the individual bulk tools in the AI Tools tab.</p>
<p><strong>Note on WordPress Cron:</strong> WordPress's built-in scheduler (WP-Cron) runs when someone visits your site. If your site has low traffic overnight, the scheduled job may run later than expected. For precise scheduling on low-traffic sites, set up a real server cron job to ping your site's <code>wp-cron.php</code> at the desired interval.</p>`,

'ab-card-lastrun': `
<p>The <strong>Last Run Log</strong> shows a detailed record of the most recent scheduled batch run. Use this to verify the automation is working correctly and to diagnose any issues.</p>
<ul>
<li><strong>Run date/time</strong> — When the last batch ran.</li>
<li><strong>Posts processed</strong> — How many posts were checked.</li>
<li><strong>Meta descriptions generated</strong> — How many new descriptions were written.</li>
<li><strong>Summaries generated</strong> — How many new summary boxes were created.</li>
<li><strong>Scores updated</strong> — How many SEO scores were calculated.</li>
<li><strong>Errors</strong> — Any posts that failed, with the error reason. Common errors include API rate limits (the AI provider temporarily rejecting requests) or posts with no readable content.</li>
</ul>
<p>If the log shows no recent runs, check that the "Enable scheduled batch processing" checkbox is ticked and that WordPress Cron is functioning. You can test this by clicking <strong>Run Now</strong> to trigger a manual batch run immediately.</p>`,

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
    const { buildParentIndex } = require('/Users/cp363412/Desktop/github/shared-help-docs/help-lib.js');
    const PARENT_ID_FILE = '/Users/cp363412/Desktop/github/shared-help-docs/.parent-page-id';
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
    const { buildParentIndex } = require('/Users/cp363412/Desktop/github/shared-help-docs/help-lib.js');
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
        seo:     'Configure your site identity, structured data schemas, AI provider connection, and the Auto Pipeline that processes new posts automatically on publish.',
        aitools: 'AI-powered tools for generating and managing meta descriptions, image ALT text, article summary boxes, and automatic internal linking.',
        sitemap: 'Control which URLs are indexed by search engines, manage your robots.txt, and configure the llms.txt file for AI crawlers.',
        perf:    'Performance optimisations including the HTTPS URL fixer, font hosting, JavaScript deferral, and HTML minification.',
        catfix:  'Taxonomy management tools: fix miscategorised posts, review category health grades, and detect AI-identified category drift.',
        batch:   'Configure automated scheduled batch processing so new content is always processed without manual effort.',
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
<div class="cs-badge">Free &amp; Open Source</div>
<h1>CloudScale SEO AI Optimizer</h1>
<p>Complete SEO &amp; AEO documentation — every panel, every setting explained. Bring your own Claude or Gemini API key. No Pro version, no upsells, no subscriptions.</p>
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
    const { id, url } = await createOrUpdatePage('Help & Documentation', finalContent, 'seo-ai-optimizer', parentId);
    console.log(`  Page ID  : ${id}`);
    console.log(`  Page URL : ${url}`);

    console.log('Updating parent index...');
    await updateParentIndex(parentId);
    console.log('  Parent index updated.');

})().catch(err => {
    console.error('\nERROR:', err.message);
    process.exit(1);
});
