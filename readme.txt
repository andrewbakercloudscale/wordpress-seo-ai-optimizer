=== CloudScale SEO AI Optimizer ===
Contributors: andrewbaker007
Tags: seo, meta description, ai, opengraph, schema
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 4.10.34
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight AI powered SEO with meta description and ALT text generation via Anthropic Claude or Google Gemini. No upsells, no restrictions.

== Description ==

CloudScale SEO AI Optimizer is a completely free SEO plugin built for technical bloggers and site owners who want full control without the overhead of Yoast or RankMath. There is no Pro version, no upsells, no feature gates, and no licence keys.

It handles the essentials cleanly and adds an AI Meta Writer that uses either the Anthropic Claude API or the Google Gemini API to generate, fix, and bulk process meta descriptions and ALT text across your entire site directly from WP Admin.

= Core SEO Features =

* Custom meta title and description per post and page
* Canonical URL output on every page
* OpenGraph tags (title, description, image, type, locale)
* Twitter/X Card tags
* JSON-LD structured data: Person schema for author pages, Article/BlogPosting schema for posts, WebSite schema for the homepage, Breadcrumb schema
* Configurable site name, locale, Twitter handle, and default OG image
* XML sitemap generation with configurable post types and taxonomy support
* Custom robots.txt editor with AI bot blocking (GPTBot, CCBot, Claude-Web, anthropic-ai and others)
* llms.txt support for AI crawler guidance
* noindex controls for search results, 404 pages, attachment pages, author archives, and tag archives
* UTM parameter stripping in canonical URLs

= AI Meta Writer =

* Choose your AI provider: Anthropic Claude or Google Gemini
* Model selector: Claude Sonnet, Claude Haiku, Gemini Flash, or Gemini Pro
* Generate meta descriptions for individual posts or in bulk across your entire site
* Fix existing descriptions that are too short or too long
* Fix titles that are outside the optimal 50 to 60 character range
* Configurable character range (min/max) injected into the prompt automatically
* Automatic retry if the AI returns a description outside your target range
* Rate limit handling with automatic backoff on HTTP 429 responses
* Fully editable system prompt with reset to default
* Live progress log with timestamps during bulk runs
* Stop button for interrupting bulk runs
* Scheduled batch generation via WP Cron with per day scheduling
* Test Key button to verify your API key before running

= ALT Text Generator =

* Audit all images across your posts for missing ALT text
* Generate ALT text using AI with article context for better relevance
* Configurable article excerpt length sent to the AI (100 to 2000 characters)
* Bulk generation with progress tracking
* Show All toggle to display images that already have ALT text

= Performance Features =

* Font display optimization with font-display: swap to eliminate Flash of Invisible Text (FOIT)
* Font metric overrides (size-adjust, ascent-override, descent-override) to reduce Cumulative Layout Shift (CLS)
* Defer font CSS loading using media="print" swap technique
* Auto-download CDN fonts (Google Fonts) to local server for faster loading and GDPR compliance
* Font CSS file scanner with terminal style console output
* Auto-Fix All with backup and undo capability
* Defer render-blocking JavaScript with configurable exclusions
* HTML, CSS, and JS minification (5 to 15 percent page size reduction)
* HTTPS mixed content scanner and one click fixer across posts, pages, metadata, options, and comments

= Dashboard Integration =

* WordPress dashboard widget with SEO status overview
* Post editor metabox with custom title, description, OG image, and inline AI generation
* Per post status badges showing description length and health

= What This Plugin Does Not Do =

* No redirect management
* No keyword analysis or readability scoring
* No paid tiers, no upsells, no tracking

== External Services ==

This plugin connects to external AI APIs to generate meta descriptions and ALT text. Connections are made when you use the AI Meta Writer or ALT Text Generator buttons in WP Admin, and optionally on a scheduled basis via WP Cron.

= Anthropic Claude API =

**Service:** Anthropic PBC
**Website:** https://anthropic.com
**Endpoint:** https://api.anthropic.com/v1/messages
**Data sent:** Post title and post content (up to 6,000 characters), plus your configured system prompt
**When data is sent:** Only when you click Generate, Fix, or the Generate with Claude button in the post editor, or when the scheduled batch runs for posts without a meta description.
**API key:** You must supply your own Anthropic API key. The key is stored in your WordPress database and is never transmitted anywhere except directly to api.anthropic.com.

Anthropic Privacy Policy: https://www.anthropic.com/privacy
Anthropic Terms of Service: https://www.anthropic.com/terms
Anthropic API documentation: https://docs.anthropic.com

= Google Gemini API =

**Service:** Google LLC
**Website:** https://ai.google.dev
**Endpoint:** https://generativelanguage.googleapis.com/v1beta/models/
**Data sent:** Post title and post content (up to 6,000 characters), plus your configured system prompt
**When data is sent:** Only when you click Generate, Fix, or the scheduled batch runs, and only when Gemini is selected as your AI provider.
**API key:** You must supply your own Google AI API key. The key is stored in your WordPress database and is never transmitted anywhere except directly to Google.

Google Privacy Policy: https://policies.google.com/privacy
Google Terms of Service: https://policies.google.com/terms
Gemini API documentation: https://ai.google.dev/docs

== Installation ==

1. Upload the plugin zip via Plugins > Add New > Upload Plugin, or extract to wp-content/plugins/cloudscale-seo-ai-optimizer/
2. Activate the plugin through the Plugins menu
3. Go to Settings > CloudScale SEO to configure your site name, OG image, and Person schema
4. To use the AI Meta Writer, go to the Optimise SEO tab and enter your API key for either Anthropic Claude or Google Gemini
5. Click Test Key to verify your key, then Load Posts to see your site description status
6. Use Generate Missing to create descriptions for posts that have none, or Fix Descriptions to correct any that are outside your configured character range
7. Visit the Performance tab to enable font optimization, JavaScript deferral, and HTML minification

**Important:** Deactivate any other SEO plugins (Yoast, RankMath, All in One SEO) before using this plugin to avoid duplicate meta tags in your page output.

== Frequently Asked Questions ==

= Do I need an API key? =

Only if you want to use the AI Meta Writer or ALT Text Generator features. The core SEO functionality (canonical URLs, OpenGraph, Twitter Cards, structured data, custom titles and descriptions) works without any API key.

= Which AI provider should I choose? =

Both work well. Anthropic Claude tends to produce slightly more nuanced descriptions. Google Gemini Flash is extremely fast and cost effective for large sites. You can switch providers at any time without losing any generated content.

= How much does the API cost? =

Generating descriptions for a typical 200 post blog costs approximately $1.20 to $1.50 using Claude Sonnet, or under $0.50 using Gemini Flash. Both providers charge per token. You control your own API key and billing directly with each provider.

= Will this conflict with Yoast or RankMath? =

Yes. Running two SEO plugins simultaneously produces duplicate meta tags. Deactivate your existing SEO plugin before activating this one.

= Where are my meta descriptions stored? =

In WordPress post meta, using the key _cs_seo_desc. Titles use _cs_seo_title. OG images use _cs_seo_ogimg. These are standard post meta fields that persist if you deactivate the plugin.

= Can I use a custom system prompt? =

Yes. The AI Meta Writer section includes a fully editable system prompt. The character range you configure in the min/max fields is injected automatically at call time.

= What happens if the generated description is the wrong length? =

The plugin measures the returned description before saving it. If it is outside your configured range, it automatically sends a correction request to the AI with the exact character count and direction. The corrected version is what gets saved.

= How does font display optimization work? =

The plugin scans your theme and plugin CSS files for @font-face rules and injects font-display: swap to prevent fonts from blocking page rendering. It also adds metric overrides to reduce layout shift when fonts load. All changes create a backup that you can undo with one click. Enable these features in the Performance tab.

= How does JavaScript deferral work? =

Adding the defer attribute to script tags allows them to download in parallel with HTML parsing and execute only after the document is ready. jQuery and other commonly problematic scripts are excluded automatically. You can add additional exclusions by script handle or URL substring.

= Can I schedule automatic description generation? =

Yes. The Scheduled Batch tab lets you select which days of the week to run automatic generation. The batch runs at midnight server time and only processes posts that do not yet have a meta description. It never overwrites existing ones.

== Screenshots ==

1. SEO Settings tab showing site identity, OG tags, and schema configuration
2. AI Meta Writer with provider selection, API key, model chooser, and system prompt
3. Generate Descriptions panel with summary cards, bulk action buttons, and live log
4. Post table showing per post description status badges and individual Generate buttons
5. Post editor metabox with custom title, description field, and Generate with AI button
6. Performance tab with font optimization, JavaScript deferral, and minification settings
7. ALT Text Generator with image audit table and bulk generation
8. Scheduled Batch configuration with day selector and last run status

== Changelog ==

= 4.10.34 =
* OG image now uses a dedicated 1200x630 crop for correct WhatsApp and social media thumbnail aspect ratio
* Added og:image:secure_url meta tag required by WhatsApp scraper for HTTPS pages
* ALT text scanner now includes featured images, not just images embedded in post content
* ALT text generator now writes ALT for featured images via attachment meta
* Added one-line summary to Explain modals on Update Posts and ALT Text panels
* S3Deploy.sh scripts made self-contained across all plugin repos

= 4.10.30 =
* Changed dashboard widget button icon from gear to browser emoji

= 4.10.29 =
* Renamed dashboard widget button from SEO Settings to View SEO AI Optimizer

= 4.10.28 =
* Removed duplicate version from widget body text, version now only in widget header

= 4.10.27 =
* Added version number to dashboard widget header title

= 4.10.26 =
* Added version number to browser title bar via add_management_page page_title argument

= 4.10.25 =
* Added version number to settings page h1 heading and dashboard widget

= 4.10.24 =
* Escalating correction prompts across the 3 retry passes — attempt 1 is polite, attempt 2 is firm, attempt 3 is a final hard instruction with exact over/under character count and a strip-words directive
* Fixed final attempt message to correctly state over/under direction for both too long and too short cases

= 4.10.23 =
* Added multi-turn length correction loop (up to 3 passes) in generate flow — when AI returns a description that is too long or too short, follow-up messages tell it exactly how many characters to add or remove
* Correction messages include the exact character count and delta so Claude can self-correct rather than guess

= 4.10.22 =
* Fixed Plugin URI pointing to 404 — updated to correct blog post URL
* Converted inline style echo in admin_head_css to wp_add_inline_style via admin_enqueue_scripts
* Moved reset-prompt, defer-toggle, and schedule-days inline script blocks to wp_add_inline_script
* Registered no-op cs-seo-admin and cs-seo-admin-js handles for proper WP enqueue compliance
* Added wp_localize_script to pass PHP values (defaultPrompt) to enqueued scripts

= 4.10.21 =
* Fixed PHPCS NonceVerification, MissingUnslash, and InputNotSanitized warnings on ALT text force regenerate parameter
* Replaced direct unlink() calls with wp_delete_file() in deactivation hook and version change detector
* Replaced direct rmdir() calls with WP_Filesystem rmdir() for WordPress coding standards compliance
* Added WP_Filesystem initialisation guard with null check before directory removal

= 4.10.20 =
* Fixed PHPCS NonceVerification, MissingUnslash, and InputNotSanitized warnings on ALT text force regenerate parameter
* Replaced direct unlink() calls with wp_delete_file() in deactivation hook and version change detector
* Replaced direct rmdir() calls with WP_Filesystem rmdir() for WordPress coding standards compliance
* Added WP_Filesystem initialisation guard with null check before directory removal

= 4.10.19 =
* Changed No AI description badge colour from blue to purple for better visual distinction

= 4.10.18 =
* Added deactivation hook that removes stale asset files for clean reinstalls
* Added version change detector on admin_init that cleans leftover assets/ subdirectory and resets OPcache
* Upgrade path is now Deactivate, Delete, Upload zip, Activate with no manual file cleanup required

= 4.10.17 =
* Fixed Generate All Missing ALT text processing all posts instead of only posts with missing ALT
* Added Force Regenerate All button to overwrite all existing ALT text across every post
* Added ALT text length validation (5 to 15 words) with automatic retry if out of range
* Progress bar now reflects actual number of posts to process

= 4.10.16 =
* Recommended checkboxes in Features & Robots now have a green background for at a glance visibility
* Optional checkboxes shown with neutral grey background to differentiate from recommended

= 4.10.15 =
* Moved Scheduled Batch tab to last position (tab order: Optimise SEO, Sitemap & Robots, Performance, Scheduled Batch)

= 4.10.14 =
* Batch run history now keeps 28 days of runs instead of just the last one
* All runs shown newest first with expandable per run post logs
* Automatic migration from legacy single run storage
* Entries older than 28 days are pruned automatically on each new run

= 4.10.13 =
* Fixed Last Batch Run always showing empty: changed from transient (expired after 24h) to persistent option
* Last batch results now persist until the next batch run overwrites them

= 4.10.12 =
* Moved all performance controls (Defer JS, Minify HTML, Defer Font CSS) into the Performance tab
* Fixed Defer Font CSS Loading toggle not saving (missing from defaults and form recognition)
* Added hidden form fields to ensure checkbox state saves correctly when all toggles are off
* Font optimization buttons restyled with unique colours per action
* Font console restyled with dark terminal theme and reduced height
* Swapped Scan CSS Files and Auto-Download CDN Fonts button order
* Fixed div nesting issue that caused Render & Minification card to appear in wrong tab
* Updated readme with Gemini support documentation and complete feature list

= 4.10.11 =
* Version bump with Performance tab restructuring

= 4.10.10 =
* Fixed plugin header format for WordPress.org compliance
* Updated readme.txt with correct stable tag and tested up to values
* Font display UI improvements and clearer labeling

= 4.10.9 =
* Font display swap optimization now correctly applies to all font stylesheets
* Added console logging for font optimization traceability

= 4.10.8 =
* Font metric overrides feature for reducing Cumulative Layout Shift
* Improved Auto Fix All button behavior with clear UI feedback

= 4.10.7 =
* Added font display optimization with swap/optional selector
* Performance tab reorganization for clarity

= 4.10.6 =
* HTTPS scanner improvements for mixed content detection
* Better handling of edge cases in URL replacement

= 4.10.5 =
* ALT text generation now includes article context for better results
* Increased max tokens for ALT text generation

= 4.10.4 =
* Added llms.txt support for AI crawler guidance
* Robots.txt improvements for AI bot blocking

= 4.10.3 =
* Sitemap pagination for large sites (50,000 URLs per file)
* Sitemap preview in admin panel

= 4.10.2 =
* Fixed batch generation progress tracking
* Improved error handling for API failures

= 4.10.1 =
* Dashboard widget improvements
* Settings page UI polish

= 4.10.0 =
* Major release with font display optimization
* Performance tab with defer JS and minification
* HTTPS mixed content scanner and fixer

= 4.9.17 =
* Dashboard widget title updated to AndrewBaker.Ninja AI SEO Optimizer

= 4.9.16 =
* Fixed SEO Settings link in dashboard widget
* Removed unrelated Backups button from dashboard widget

= 4.9.15 =
* ALT text audit table now shows actual generated ALT text after generation
* Fixed post titles with special characters rendering as raw HTML entities

= 4.9.14 =
* Added WordPress dashboard widget with links to AndrewBaker.Ninja and SEO Settings

= 4.9.13 =
* Badge button styling updated with gradient and hover animation

= 4.9.12 =
* Added AndrewBaker.Ninja badge link at top of settings page

= 4.9.11 =
* Show All checkbox moved into toolbar layout

= 4.9.10 =
* Fixed duplicate status message in ALT generator log
* Fixed post titles with HTML entities rendering incorrectly

= 4.9.9 =
* Added mobile left padding for better card layout
* Fixed contradictory log message for missing images

= 4.9.8 =
* Added left border and padding to description hint text

= 4.9.7 =
* ALT text article excerpt length now configurable (default 600, range 100 to 2000)

= 4.9.6 =
* ALT text generation now sends article excerpt alongside image filename
* Increased ALT text max_tokens from 60 to 80

= 4.9.5 =
* ALT Text Generator card header styling updated

= 4.9.4 =
* Added ALT text audit view showing all images and their current ALT text
* Added Show All toggle to display images that already have ALT text

= 4.9.3 =
* Added defer render blocking JavaScript feature
* Configurable exclusions for script handles and URLs

= 4.2.3 =
* Fix Descriptions now retries up to 3 times for out of range results

= 4.2.2 =
* Added Fix Descriptions button for targeted correction
* Badge logic now uses configured min/max values

= 4.2.1 =
* Increased request gap to 2,500ms for Anthropic rate limits
* Added automatic retry on HTTP 429 responses

= 4.2.0 =
* Character range decoupled from system prompt

= 4.1.0 =
* Added automatic correction pass for out of range descriptions

= 4.0.0 =
* Initial release of AI Meta Writer tab
* Bulk generation with live progress log
* Per post generation from post editor metabox

== Upgrade Notice ==

= 4.10.13 =
Fixed Last Batch Run always appearing empty. Batch results now persist permanently instead of expiring after 24 hours.

= 4.10.12 = Fixes save issue with Defer Font CSS Loading. Updated readme with full Gemini support documentation.

= 4.10.10 =
Fixes plugin header format for WordPress.org submission. Includes font display optimization for Core Web Vitals improvements.

= 4.10.0 =
Major release with font display optimization, defer JS, and HTML minification. Recommended for all users seeking better Core Web Vitals scores.
