# WordPress SEO AI Optimizer

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple) ![License](https://img.shields.io/badge/License-GPLv2-green) ![Version](https://img.shields.io/badge/Version-4.19.66-orange)

Enterprise-grade WordPress SEO with a full AI automation suite — meta descriptions, ALT text, SEO scoring, focus keywords, internal links, summaries, related articles, and category management. Powered by Anthropic Claude or Google Gemini. Completely free.

**No Pro version. No upsells. No feature gates. No licence keys.**

The only cost is the API tokens you actually use. At Gemini Flash prices a site with 200 posts costs under $0.50 for a full batch run.

> Full write-up with screenshots: [CloudScale SEO AI Optimiser: Enterprise Grade WordPress SEO, Completely Free](https://andrewbaker.ninja/2026/02/24/cloudscale-seo-ai-optimiser-enterprise-grade-wordpress-seo-completely-free/)
>
> **Available on WordPress.org:** [wordpress.org/plugins/cloudscale-seo-ai-optimizer](https://wordpress.org/plugins/cloudscale-seo-ai-optimizer/)

---

## Features

### Core SEO

- Custom meta title per post and page, with configurable site name suffix
- Custom meta description per post and page (manual or AI-generated)
- Canonical URLs on every page — suppresses duplicates from Yoast, RankMath, and Jetpack
- OpenGraph tags: title, description, image, type, locale
- Twitter/X Card tags
- JSON-LD structured data: Person (author), WebSite (homepage), Article/BlogPosting (posts), BreadcrumbList
- noindex controls for search results, 404 pages, attachment pages, author archives, and tag archives
- UTM and tracking parameter stripping from canonical URLs
- XML sitemap at `/sitemap.xml` (index + paginated child sitemaps, 5,000 URLs per file)
- Plain-text sitemap at `/sitemap.txt` — one URL per line for AI crawlers and scrapers
- Custom robots.txt editor with one-click AI bot blocking (GPTBot, CCBot, Claude-Web, anthropic-ai, and others)
- `llms.txt` support for AI crawler guidance, served at `/llms.txt`

### AI Auto Pipeline

Fires all AI steps automatically in a background process the moment a post is published — no WP-Cron dependency.

- **Meta description** — generated and saved immediately
- **SEO score** — 0–100 AI rating with a one-sentence note
- **Focus keyword** — extracted from post content and stored in post meta
- **ALT text** — generated for every image attached to or embedded in the post
- **Internal links** — AI-suggested links injected into paragraph and heading blocks (Gutenberg-safe; classic editor fallback via `str_replace`)
- **AI Summary Box** — three-field summary (What it is / Why it matters / Key takeaway) prepended to post content
- **Related Articles** — scored and stored so related links appear immediately on the live post

Additional controls:

- **Re-run on update** toggle — re-triggers the full pipeline whenever a published post is saved
- Minimum 50-word content guard — prevents meaningless output on stub or test posts
- HMAC-authenticated async request (120-second TTL) — keeps the pipeline endpoint secure
- **Re-run AI Automation** button in the post metabox with a live log
- Auto Pipeline card lives at the top of the AI Tools tab with its own Save button

### AI Meta Writer

- Choose your provider: **Anthropic Claude** or **Google Gemini**
- Model selector: **Automatic** (always resolves to the current recommended model), Claude 3.5/3.7 Sonnet, Claude Haiku, Gemini 2.0 Flash, Gemini 1.5 Pro, or a **Custom** model string
- Generate descriptions for individual posts or bulk-process the entire site
- Fix descriptions that are too short or too long
- Fix titles outside the optimal 50–60 character range
- Inline edit button on each post row — opens a textarea for manual correction without leaving the panel
- Configurable character range (min/max) injected into the prompt automatically
- Automatic retry if the AI returns a description outside your target range
- Rate-limit handling with automatic backoff on HTTP 429 responses
- Fully editable system prompt with reset-to-default
- Sortable post table — sort by title, date, SEO score, description length, title length, or ALT status
- Live progress log with timestamps during bulk runs
- Stop button for interrupting bulk runs
- Test Key button to verify your API key before running

### AI SEO Scoring

- AI rates each post from **0 to 100** with a one-sentence strengths/weaknesses note
- **Calculate SEO Scores** button runs a bulk pass across all posts
- **Generate Missing** automatically scores posts that lack a score after descriptions are written
- Per-post score badges displayed in the AI Tools table and the dashboard widget
- Scores stored as post meta (`_cs_seo_score`, `_cs_seo_notes`) and survive plugin deactivation

### AI Summary Box

- Three AI-generated fields prepended to post content: **What it is**, **Why it matters**, **Key takeaway**
- Bulk generation panel with progress tracking and Stop button
- Force-regenerate option to overwrite all existing summaries
- Summary fields written to Article JSON-LD schema: `description`, `abstract`, and `disambiguatingDescription`
- Collapsible card with gradient header and drop shadow
- Global toggle to show or hide the box without deleting generated content

### ALT Text Generator

- Audit all images across all posts for missing ALT text
- Generate ALT text using AI with post content as context for better relevance
- Configurable excerpt length sent to the AI (100–2,000 characters)
- Bulk generation with progress tracking
- Show All toggle to display images that already have ALT text

### Related Articles

- Automatically injects contextually scored related post links above and below post content
- Configurable top count (2–5 links) and bottom count (3–10 links)
- Separate toggles for top and bottom blocks
- Scoring uses shared categories, tags, and AI summary content — all configurable
- Configurable candidate pool size and category exclusions
- **Generate Missing** — runs the scoring pipeline for all unprocessed posts
- **Refresh Stale** — re-runs previously completed posts when content changes
- **Generate & Sync** — single pass that scores missing posts and re-applies count settings to existing ones
- Post Status table shows per-post pipeline state (pending, complete, failed) with filter tabs
- Block-safe injection — works with both Gutenberg and classic editor posts
- Related Articles are also generated automatically by the Auto Pipeline on publish

### Category Fixer

- Scans all published posts and suggests improved category assignments using AI
- Analyses post title, slug, tags, and AI summary against your full category list
- Proposes up to four categories per post — only from categories that already exist
- Never assigns Uncategorized
- Colour-coded review table: green for additions, red for removals, grey for kept categories
- Per-post Apply and Skip buttons; bulk **Apply All Changed**
- Filter bar: All, Changed, Unchanged, Low Confidence, Missing
- Per-row re-analyse button; full Reload button for a fresh AI pass
- AI confidence score badge on each row
- No categories are changed until you explicitly click Apply

### Category Health and Drift Detection

- **Category Health** tab — post counts per category with pass/fail coverage indicator
- **Category Drift Detection** — AI identifies categories that have drifted from their focus or become catch-all buckets
- Each flagged category gets a verdict (`drifting` or `catch-all`), confidence score, and AI reasoning
- Results sorted by verdict type then confidence — most actionable items first
- Elapsed time counter and Stop button during analysis runs

### Performance (Core Web Vitals)

**Font Display Optimization.** Scans all theme and plugin stylesheets for `@font-face` rules missing `font-display: swap`. Auto-fixes with backup and undo. Adds metric overrides (`size-adjust`, `ascent-override`, `descent-override`) to reduce Cumulative Layout Shift. Defers font CSS loading via the `media="print"` swap technique. Typically saves 500 ms–2 seconds off LCP for sites using Google Fonts.

**Auto-Download Google Fonts.** Downloads Google Fonts to your server so they are served locally — eliminates the external Google Fonts request, improves GDPR compliance and Core Web Vitals.

**Defer Render-Blocking JavaScript.** Adds the `defer` attribute to front-end scripts so they download in parallel and execute after HTML parsing. Configurable exclusions for jQuery, WooCommerce, reCAPTCHA, and others.

**HTML/CSS/JS Minification.** Strips whitespace, comments, and unnecessary characters from HTML, CSS, and inline JavaScript. Shaves 5–15 percent off page size without layout changes.

**HTTPS Mixed Content Scanner.** Finds `http://` references to your own domain across posts, pages, metadata, options, and comments. One click replaces them all with `https://`.

### SEO Health Dashboard

- Dashboard widget shows five colour-coded health pillars: **Posts** (meta coverage), **SEO** (score coverage), **Images** (ALT coverage), **Links** (related articles coverage), **Summaries** (AI summary coverage)
- Green ≥ 90 %, amber ≥ 60 %, red < 60 %
- Refresh link rebuilds the health cache on demand
- Cache auto-rebuilds after any bulk AI run completes
- "Posts need AI auto run" and "pipeline jobs queued" counters

### Editor Integration

- **Post metabox** — custom title, description, OG image, and inline AI generation with live log; Re-run AI Automation button
- **Gutenberg sidebar panel** (CloudScale Meta Boxes) — title, description, OG image, AI summary fields, and one-click generation without leaving the editor
- Per-post status badges: description length, title length, SEO score, ALT status
- Tab state persists across page reloads

---

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- An Anthropic or Google Gemini API key (AI features only — core SEO works without a key)

---

## Installation

### From WordPress.org (recommended)

1. In WordPress admin go to **Plugins > Add New**
2. Search for **CloudScale SEO AI Optimizer**
3. Click **Install Now**, then **Activate**

Or install directly from [wordpress.org/plugins/cloudscale-seo-ai-optimizer](https://wordpress.org/plugins/cloudscale-seo-ai-optimizer/).

### From GitHub

1. Download the latest release zip from the [Releases](../../releases) page
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**
3. Upload the zip, click **Install Now**, then **Activate**

The plugin appears under **Tools > 🤖 CloudScale SEO AI** in the admin menu.

### Upgrading

Deactivate > Delete > Install new version > Activate. Post meta and options are preserved. The plugin detects version changes on activation and migrates settings automatically.

---

## Setup

### 1. Site Identity

Go to **Settings > CloudScale SEO**. Fill in your site name, home title, and home description (140–155 characters). These feed into JSON-LD schema and OpenGraph tags.

### 2. Person Schema

Add your name, job title, profile URL, and headshot URL. Add social profiles one per line in the SameAs field. Google uses this to build your author entity in Knowledge Graph.

### 3. Features

Enable OpenGraph, the JSON-LD schemas you need, the XML sitemap, and noindex controls. Click **? Explain** on any card for a plain-English guide.

### 4. API Key

**Anthropic Claude:** Go to [console.anthropic.com](https://console.anthropic.com), create an API key, paste it into the **API Key** field on the AI Tools tab, click **Test Key**, then **Save AI Settings**.

**Google Gemini:** Follow the instructions at [ai.google.dev](https://ai.google.dev/gemini-api/docs/api-key) and paste the key into the same field after switching the provider to Gemini.

Your key is stored in your WordPress database and is never transmitted to any third party — only directly to the AI provider you select.

### 5. Generate Content

Go to **AI Tools > Update Posts with AI Descriptions**, expand the card, and click **Generate Missing** for a bulk run. Use **Calculate SEO Scores** to score all posts. Enable Auto Pipeline to automate everything on future publishes.

### 6. Sitemap

Enable the sitemap in Features, then submit `https://yoursite.com/sitemap.xml` to Google Search Console. The plain-text version at `/sitemap.txt` is picked up automatically by most AI crawlers.

### 7. Performance

Go to the **Performance** tab. Enable Font Display Optimization and run **Auto-Fix All**. Enable JavaScript deferral and HTML minification if your theme supports it. Run the HTTPS scanner to fix any mixed-content warnings.

---

## Cost Model

| Item | Cost |
|---|---|
| Plugin | Free, forever |
| Gemini Flash per post | ~$0.001 or less |
| Claude Haiku per post | ~$0.001 to $0.003 |
| 200-post full batch (Gemini Flash) | Under $0.50 total |
| 200-post full batch (Claude Sonnet) | ~$1.20 to $1.50 total |
| Ongoing new posts via Auto Pipeline | Fractions of a cent each |

Compare that to $99–$199 per year for a premium SEO plugin, before the AI add-on fees.

---

## License

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

---

## Author

[Andrew Baker](https://andrewbaker.ninja/) — CIO at Capitec Bank, South Africa.

Built because software should work this way.
