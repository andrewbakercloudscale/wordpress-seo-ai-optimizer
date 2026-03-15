# CloudScale SEO AI Optimizer

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple) ![License](https://img.shields.io/badge/License-GPLv2-green) ![Version](https://img.shields.io/badge/Version-4.10.34-orange)

Enterprise grade WordPress SEO with AI powered meta description and ALT text generation via Anthropic Claude or Google Gemini. Completely free.

No Pro version. No upsells. No feature gates. No licence keys.

The only cost is the Claude API or Gemini tokens you actually use, and at Haiku prices that is roughly **$0.001 to $0.003 per post**. A site with 200 posts costs about $0.20 to $0.60 total for a full batch run.

> Full write up with screenshots: [CloudScale SEO AI Optimiser: Enterprise Grade WordPress SEO, Completely Free](https://andrewbaker.ninja/2026/02/24/cloudscale-seo-ai-optimiser-enterprise-grade-wordpress-seo-completely-free/)

## Features

### Core SEO

- Custom titles with configurable suffix
- Meta descriptions (manual or AI generated)
- Canonical URLs (suppresses duplicates from Yoast, RankMath, and Jetpack)
- OpenGraph and Twitter Card tags
- JSON-LD schema: Person, WebSite, Article/BlogPosting, BreadcrumbList
- noindex controls for search results, 404s, attachment pages, author archives, and tag archives
- Tracking parameter stripping

### AI Meta Writer

- Generate meta descriptions using Anthropic Claude API or Google Gemini API
- Generate ALT text for images with AI vision
- Single post generation, bulk batch processing, or scheduled nightly runs
- Fix descriptions that are too long or too short
- Force regenerate all descriptions or ALT text in one pass
- Configurable prompt, model selection, and token limits
- ALT text length validation (5 to 15 words) with automatic retry

### Technical SEO

- Dynamic XML sitemap generated fresh on every request
- Full robots.txt editor with AI bot blocking
- llms.txt support
- JavaScript defer for render blocking scripts (with configurable exclusions)
- HTML, CSS, and inline JS minification
- Font display optimization (scans and patches `font-display: swap`)
- HTTPS mixed content scanner and one click fixer

### Performance (Core Web Vitals)

**Font Display Optimization.** Scans all theme and plugin stylesheets for `@font-face` rules missing `font-display: swap`. Auto fixes them with backup and undo. Typically saves 500ms to 2 seconds off LCP for sites using Google Fonts.

**Defer Render Blocking JavaScript.** Adds the `defer` attribute to front end scripts so they download in parallel and execute after HTML parsing. Configurable exclusions for jQuery, WooCommerce, reCAPTCHA, and others.

**HTML Minification.** Strips whitespace, comments, and unnecessary characters from HTML, CSS, and inline JavaScript. Shaves 5 to 15 percent off page size without layout changes.

**HTTPS Mixed Content Scanner.** Finds `http://` references to your own domain across posts, pages, metadata, options, and comments. One click replaces them all with `https://`.

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- An Anthropic or Google Gemini API key (for AI features only)

## Installation

1. Download the latest release zip from the [Releases](../../releases) page
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file, click **Install Now**, then **Activate Plugin**
4. The plugin appears under **Tools > CloudScale SEO AI**

### Upgrading

Deactivate > Delete > Upload zip > Activate.

The plugin cleans up stale files automatically on deactivation and detects version changes on activation. No SSH or manual file cleanup required.

## Setup

### 1. Site Identity

Fill in your site name, home title, and home description (140 to 155 characters). These feed into JSON-LD schema and OpenGraph tags.

### 2. Person Schema

Add your name, job title, profile URL, and headshot link. Add social profiles (LinkedIn, GitHub, etc.) one per line in the SameAs field. Google uses this to build your author entity.

### 3. Features and Robots

Click the **? Explain** button in the card header for a plain English guide to every option with recommendations. For most sites, enable OpenGraph, all three JSON-LD schemas, the sitemap, and noindex on search results, 404s, attachment pages, author archives, and tag archives.

### 4. API Key

Go to [console.anthropic.com](https://console.anthropic.com), create an account, navigate to **Settings > API Keys**, and create a key. It looks like `sk-ant-api03-...` and you only see it once.

Back in WordPress, go to **Tools > CloudScale SEO AI > Optimise SEO tab**, paste your key into the **API Key** field, click **Test Key**, then **Save AI Settings**.

The plugin never sends your key to any third party. It calls the Anthropic API directly from your server.

For Google Gemini, follow the instructions at [ai.google.dev/gemini-api/docs/api-key](https://ai.google.dev/gemini-api/docs/api-key).

### 5. Generate Meta Descriptions

Go to **Optimise SEO > Update Posts with AI Descriptions**, click **Load Posts**, then **Generate Missing** for a batch run. Fix outliers with **Fix Long/Short**. Set up a scheduled batch in the **Scheduled Batch** tab for ongoing automation.

### 6. Sitemap

Enable the sitemap in Features and submit `https://yoursite.com/sitemap.xml` to Google Search Console.

## Cost Model

| Item | Cost |
|---|---|
| Plugin | Free, forever |
| Claude Haiku per post | ~$0.001 to $0.003 |
| 200 posts full batch | ~$0.20 to $0.60 total |
| Ongoing new posts | Fractions of a cent each |

Compare that to $99 to $199 per year for a premium SEO plugin.

## Configuration

All settings are under **Tools > CloudScale SEO AI** in the WordPress admin. The interface is organised into tabs: Site Identity, Features and Robots, Sitemap, Optimise SEO (AI Meta Writer, ALT Text, Scheduled Batch), and Performance.

AI features require an API key configured in the **AI Settings** section of the Optimise SEO tab.

## License

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

## Author

[Andrew Baker](https://andrewbaker.ninja/) - CIO at Capitec Bank, South Africa.

Built because software should work this way.
