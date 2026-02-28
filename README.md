# CloudScale SEO AI Optimizer

Lightweight, completely free SEO plugin for WordPress with AI powered meta description and ALT text generation via Anthropic Claude or Google Gemini.

No Pro version. No upsells. No feature gates. No licence keys.

## Features

**Core SEO**: Custom titles, meta descriptions, canonical URLs, OpenGraph, Twitter Cards, JSON-LD schema (Person, WebSite, Article, BreadcrumbList).

**AI Meta Writer**: Generate, fix, and bulk process meta descriptions and ALT text across your entire site from WP Admin using the Anthropic Claude API or Google Gemini API.

**Technical SEO**: XML sitemaps, robots.txt editor, AI bot blocking, JavaScript defer, HTML minification, font display optimization, HTTPS mixed content scanner, and llms.txt support.

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
- An Anthropic or Google Gemini API key (for AI features only)

## Installation

1. Download the latest release zip from the [Releases](../../releases) page
2. In WordPress admin go to Plugins > Add New > Upload Plugin
3. Upload the zip file and activate

**Upgrading**: Deactivate > Delete > Upload zip > Activate. The plugin cleans up stale files automatically on deactivation and detects version changes on activation.

## Configuration

All settings are under **Settings > CloudScale SEO** in the WordPress admin. AI features require an API key configured in the **AI Settings** tab.

## License

GPLv2 or later. See [LICENSE](LICENSE) for the full text.

## Author

[Andrew Baker](https://andrewbaker.ninja/) - CIO at Capitec Bank, South Africa.
