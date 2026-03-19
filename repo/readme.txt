=== CloudScale SEO AI Optimizer ===
Contributors: andrewjbaker
Tags: seo, meta description, ai, opengraph, schema
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 4.19.37
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered SEO & AEO: meta descriptions, auto linking, category management, ALT text. Bring your own Claude or Gemini API key. Free, open source.

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

= AI Summary Box =

* AI generated article summary box automatically prepended to post content
* Three fields generated per post: What it is, Why it matters, Key takeaway
* Bulk generation panel with progress tracking, stop button, and paginated post list
* Force regenerate option to overwrite all existing summaries
* Summary fields written to Article JSON-LD schema: description, abstract, and disambiguatingDescription
* Collapsible display with modern card styling including gradient header and drop shadow
* Toggle to show or hide the summary box globally without deleting generated content

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
* Gutenberg sidebar panel (CloudScale Meta Boxes) with custom title, description, OG image, AI summary fields, and one click generation without leaving the editor
* Per post status badges showing description length and health

= Category Fixer =

* Scans all published posts and suggests improved category assignments using AI
* Uses Claude to analyse post title, slug, tags, and AI summary box against your full category list
* Proposes up to four categories per post — only from categories that already exist in WordPress
* Never assigns Uncategorized
* Colour coded review table: green for additions, red for removals, grey for kept categories
* Per post Apply and Skip buttons, plus bulk Apply All Changed
* Filter bar: All, Changed, Unchanged, Low Confidence, Missing
* Reload button re-analyses all posts with fresh AI calls
* Per row re-analyse button for individual posts
* AI analysis badge shows confidence score
* No categories are changed until you explicitly click Apply

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
**When data is sent:** Only when you click Generate, Fix, or the Generate with Claude button in the post editor, when the scheduled batch runs for posts without a meta description, or automatically on post publish/update when Auto Pipeline is enabled in AI Tools settings.
**API key:** You must supply your own Anthropic API key. The key is stored in your WordPress database and is never transmitted anywhere except directly to api.anthropic.com.

Anthropic Privacy Policy: https://www.anthropic.com/privacy
Anthropic Terms of Service: https://www.anthropic.com/terms
Anthropic API documentation: https://docs.anthropic.com

= Google Gemini API =

**Service:** Google LLC
**Website:** https://ai.google.dev
**Endpoint:** https://generativelanguage.googleapis.com/v1beta/models/
**Data sent:** Post title and post content (up to 6,000 characters), plus your configured system prompt
**When data is sent:** Only when you click Generate, Fix, or the scheduled batch runs, when Gemini is selected as your AI provider, or automatically on post publish/update when Auto Pipeline is enabled in AI Tools settings.
**API key:** You must supply your own Google AI API key. The key is stored in your WordPress database and is never transmitted anywhere except directly to Google.

Google Privacy Policy: https://policies.google.com/privacy
Google Terms of Service: https://policies.google.com/terms
Gemini API documentation: https://ai.google.dev/docs

= Google Fonts CDN =

**Service:** Google LLC
**Websites:** https://fonts.googleapis.com, https://fonts.gstatic.com
**When contacted:** Only when you use the Font Display Optimizer's "Download Fonts" feature in WP Admin (Performance tab). This feature downloads Google Fonts files to your server so they can be served locally.
**Data sent:** The URL of the Google Font stylesheet registered on your site. No personal data or post content is transmitted.
**Purpose:** To copy font files from Google's CDN to your own server, eliminating the external Google Fonts request from your frontend pages (improves GDPR compliance and Core Web Vitals).

Google Privacy Policy: https://policies.google.com/privacy
Google Terms of Service: https://policies.google.com/terms

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

= 4.19.11 =
* Add: sitemap.txt endpoint — plain-text sitemap (one URL per line) served at /sitemap.txt alongside the existing XML sitemap

= 4.19.4 =
* Fix: debug_log() method added to main class — font optimizer and OG letterbox AJAX handlers threw fatal errors in production; now delegates to Utils::log() for a single logging code path
* Fix: orphaned duplicate DocBlocks removed from trait-ai-meta-writer.php
* Fix: missing DocBlocks added across main class, trait-ai-meta-writer.php, and trait-settings-assets.php
* Fix: @package tag added to trait-settings-assets.php file DocBlock

= 4.19.3 =
* PCP: moved phpcs:ignore for post__not_in to same line as violation in trait-auto-pipeline.php, trait-ai-meta-writer.php, and trait-sitemap.php — inline suppression is required for PHPCS to recognise it

= 4.19.2 =
* PCP fix: echoed <script> tag in auto-run metabox replaced with wp_add_inline_script()
* Fix: button label '\u21ba' in single-quoted PHP string replaced with literal UTF-8 ↺
* Fix: inline conditional colour echo in metabox log wrapped with esc_attr()
* Fix: orphaned DocBlock on on_post_delete restored; duplicate consecutive DocBlock removed
* Fix: render_rc_block() DocBlock missing @since and @return — added
* CHANGELOG.md synced — all versions from 4.14.6 through 4.19.1 back-filled

= 4.19.1 =
* Added Explain buttons to Auto Pipeline, Mixed Content Fix, and Render & Minification cards
* Updated Auto Pipeline description to reflect non-blocking HTTP approach (not cron)

= 4.19.0 =
* Auto pipeline: all AI steps (meta description, focus keyword, internal links, AI summary) now require a minimum of 50 words of post content before running — prevents meaningless output on stub/test posts

= 4.18.9 =
* AI auto pipeline now uses non-blocking wp_remote_post() instead of WP-Cron — fires immediately on publish with no cron dependency
* HMAC token (120s TTL transient) authenticates the async pipeline request
* Removed cs_seo_auto_run_pipeline cron hook; cleanup pipeline retains cron

= 4.18.8 =
* Related Articles now run synchronously on publish via transition_post_status — no cron dependency
* AI pipeline now calls spawn_cron() on shutdown after scheduling to force immediate cron execution

= 4.18.7 =
* Reload button moved left of Show/Hide Details button and uses visibility:hidden so the layout never shifts when it appears

= 4.18.6 =
* Auto Pipeline card moved to AI Tools tab (top of panel) with its own Save button
* Added "Re-run on update" toggle — re-runs full pipeline 5 seconds after any published post is saved
* Auto Pipeline settings removed from Scheduled Batch card

= 4.18.5 =
* Removed duplicate Reload buttons from inside AI Meta Writer, ALT Text, and Summary Box toolbars — Reload only appears in the card header

= 4.18.4 =
* Fix: altLoad() and sumLoad() were crashing on removed button elements, preventing auto-load on card expand

= 4.18.3 =
* Removed all "Hide Posts" buttons from AI Meta Writer, ALT Text, Summary Box, Category Fixer, and Category Health cards

= 4.18.2 =
* Removed "Load Posts" and "Scan Posts" CTA buttons from AI Meta Writer, ALT Text, and Summary Box cards
* Cards now auto-load their posts on first "Show Details" expand via abToggleCard

= 4.18.1 =
* Auto Pipeline: added "Auto Run on publish" toggle in Batch/Schedule settings — pipeline is disabled by default until explicitly enabled
* Auto Pipeline: on_post_publish and on_post_delete now check the toggle before scheduling cron events

= 4.18.0 =
* Auto Pipeline: new trait-auto-pipeline.php — all AI operations run automatically via WP-Cron when a post is published
* Auto Pipeline: steps — meta description + SEO score, focus keyword, ALT text for attached images, internal links (AI-suggested, block-safe injection), AI summary box, Related Articles generation
* Auto Pipeline: Gutenberg-safe internal link injection using parse_blocks / serialize_blocks on core/paragraph and core/heading blocks; classic editor fallback via str_replace
* Auto Pipeline: delete cleanup pipeline removes all _cs_ post meta and run log transients for permanently deleted posts
* Auto Pipeline: "Re-run AI Automation" button in post edit screen metabox with live log display
* Auto Pipeline: CSEO_ASYNC_ENABLED constant for synchronous debugging mode
* Dashboard widget: "posts need AI auto run" and "pipeline jobs queued" metrics added
* New meta keys: _cs_seo_auto_run_complete, _cs_seo_focus_keyword

= 4.17.6 =
* Related Articles Post Status: "All Posts" now uses a direct DB query instead of WP_Query, fixing the environment where WP_Query with no meta restriction returned 0 results (new posts were invisible)
* Related Articles Post Status: "All Posts" is now the default filter so newly published articles appear immediately on table load

= 4.17.5 =
* File-level DocBlocks (@package, @since) added to all 20 remaining trait files
* Settings page: all form <th> field labels and RC table labels wrapped in esc_html_e()
* ajax_rc_sync_counts @since history corrected to reflect 4.16.5, 4.17.1, 4.17.2 changes

= 4.17.4 =
* PCP: removed load_plugin_textdomain() call — discouraged since WP 4.6, auto-loaded by WordPress.org
* PCP: added missing translators comment to printf in metabox
* PCP: removed set_time_limit() call in ajax_rc_sync_counts
* PCP: prefixed global variables in uninstall.php ($cs_seo_options, $cs_seo_meta_keys, etc.)
* PCP: added phpcs:ignore to Utils class declaration with explanation

= 4.17.3 =
* Settings page now returns to the active tab after saving — active tab is saved to localStorage and restored on page reload

= 4.17.2 =
* Fix: Generate & Sync now correctly decreases link counts for posts where _cs_rc_scores was deleted by earlier reset attempts — added a third code path that directly trims top/bottom arrays without requiring scores or re-running the pipeline
* Fix: button label showed "&amp;" literal after completion — changed to textContent with plain "&"

= 4.17.1 =
* Merged "Generate Missing" and "Sync Counts" into one "Generate & Sync" button — single server-side pass that runs the full pipeline for posts with no scores and re-applies count settings for posts that already have scores

= 4.17.0 =
* Related Articles Post Status table: post title links now open the live post URL (so related links are visible) instead of the editor

= 4.16.9 =
* Fix: post title links in Related Articles Post Status table now open the post editor instead of the category editor (was using a relative URL that inherited the settings page context)

= 4.16.8 =
* Fix: clicking Generate Missing when all posts are complete no longer leaves the table showing "No posts found." — the table restores to its previous filter/page after the alert

= 4.16.7 =
* Sync Counts now fills upward as well as trimming — re-applies the stored candidate scores to fill additional slots when the count setting is increased, so no full regeneration is needed

= 4.16.6 =
* Fix: Related Articles settings (rc_top_count, rc_bottom_count, rc_enable) were silently discarded on save — the options sanitizer's known-fields guard did not include any RC keys so every RC form submission was rejected as spurious

= 4.16.5 =
* New: "Sync Counts" button in Related Articles Post Status — single server-side pass that trims all complete posts' stored links to match current Top/Bottom count settings using a direct DB query, bypassing all WP_Query environment issues

= 4.16.4 =
* Fix: Generate Missing now force-reloads with filter=pending before collecting IDs — previously it used the complete-post DOM rows and then filtered them all out, yielding 0 posts

= 4.16.3 =
* Fix: rcRunOne final state fetch now uses rcCurrentFilter instead of hardcoded filter=all (which returns 0 in some environments), so row counts update live during a batch run
* Fix: Reset All reloads with current filter rather than filter=all
* Batch bar now shows post count at start so you can confirm the batch is running

= 4.16.2 =
* Fix: Related Articles table autoload now uses filter=complete instead of filter=all (all-posts query returns 0 in some environments)
* Fix: rcBatch auto-reloads the table with the target filter before collecting IDs if the DOM is empty, guaranteeing posts are found

= 4.16.1 =
* Fix: rcBatch now reads post IDs from the visible DOM table rows instead of a pre-fetch API call; eliminates "No posts to process" caused by cs_rc_get_posts returning 0 for certain filter/query combinations

= 4.16.0 =
* Fix: Refresh Stale now queries filter='complete' instead of filter='all' — semantically correct (re-runs previously completed posts) and resolves "No posts to process" caused by the all-posts query returning nothing in some environments

= 4.15.9 =
* Refactor: rcBatch rewritten to use a page-1 probe for total count then process page-by-page, eliminating the pre-fetch bulk-collect approach that returned 0 posts in some environments

= 4.15.8 =
* Fix: Refresh Stale and Retry Failed now actually regenerate posts — previously the step handler returned immediately for complete posts without running any steps; each post is now reset to pending before re-running

= 4.15.7 =
* Fix: Related Articles batch (Refresh Stale, Generate Missing, Retry Failed) now fetches all pages before building the queue — previously only the first 50 posts were ever processed

= 4.15.6 =
* AI Tools post table: ✏ Edit button on each row opens an inline textarea to manually enter or edit the meta description, with Save and Cancel

= 4.15.5 =
* PCP medium fixes: load_textdomain moved from plugins_loaded to init
* PHP version notice now uses i18n functions with translators comment
* DocBlocks added to all methods in trait-options.php
* File-level DocBlocks added to trait-schema.php and trait-ai-engine.php
* dispatch_ai() and ajax_check() DocBlocks completed with @since, @param, @return
* Created includes/class-cloudscale-seo-ai-optimizer-utils.php Utils class
* Settings page: tab labels, zone headers, and all submit_button labels wrapped in i18n functions

= 4.15.4 =
* PCP compliance: JSON-LD structured data now output via wp_print_inline_script_tag() instead of echoed <script> strings — eliminates the only remaining critical PCP violation

= 4.15.3 =
* Fix: PHP Warning "Undefined array key message" in batch scheduler log display — timeout and sum_ok entries have no message key

= 4.15.2 =
* Scoring status bar now shows "(Post N of Total)" counter for both Calculate SEO Scores and Generate Missing phase 2

= 4.15.1 =
* Fix: Generate Missing phase 2 scoring now does its own fresh post fetch instead of relying on phase 1 data; always logs phase 2 status

= 4.15.0 =
* Generate Missing now runs a second phase that scores any posts still missing an SEO score after descriptions are done
* Renamed "Score All" button to "Calculate SEO Scores"

= 4.14.9 =
* AI Tools post table: Description, Title, and ALT columns are now sortable; all six data columns now have clickable sort headers

= 4.14.8 =
* Fix: homepage SEO score no longer disappears on reload — static front page row now reads seo_score/seo_notes from post meta

= 4.14.7 =
* AI Tools post table: added Date column and sortable headers for Post (title), Date, and SEO Score

= 4.14.6 =
* Swapped Categories and Scheduled Batch tab order in settings page

= 4.13.2 =
* Category Drift: cdLoad() now shows elapsed time counter and Stop button during analysis
* Category Drift: cdAnalyseRemaining() now shows post count in loading label and Stop button
* Both use AbortController so Stop cancels the in-flight fetch immediately

= 4.13.1 =
* Fixed PHP operator-precedence bug in defer_font_css() noscript href — preg_match result was being concatenated before the ternary, making href always empty
* Added i18n (esc_html__/esc_html_e) to user-visible strings in admin notice, metabox labels/buttons, and frontend summary box
* Extracted admin_page_css(), llms_preview_js(), sitemap_preview_js() to trait-settings-assets.php (reduces trait-settings-page.php by ~300 lines)

= 4.13.0 =
* Created CHANGELOG.md in Keep-a-Changelog format
* Dashboard widget title now uses wp_kses_post() with esc_html() for the version span
* Added @since, @param, @return DocBlocks to all public methods across all 23 trait files

= 4.12.9 =
* Security: removed wp_ajax_nopriv_ registration from ajax_download_fonts (admin-only handler)
* Security: added esc_attr() to three unescaped ternary echo outputs in render_metabox()
* Standards: replaced file_get_contents/file_put_contents with WP_Filesystem in all font optimizer operations

= 4.12.8 =
* Fix: SEO score not returned when title needed fixing — json_shape for title-needs-fix case was missing seo_score/seo_notes fields

= 4.12.7 =
* Dashboard widget: Posts pill colour changed from grey to blue

= 4.12.6 =
* PCP fix: dashboard widget health-refresh and health-run <script> blocks moved to wp_add_inline_script via ob_start capture
* PCP fix: post editor metabox csSeoGenOne and csSeoSumGenOne <script> blocks moved to wp_add_inline_script via ob_start capture
* Registered cs-seo-dashboard-js handle (dashboard page) and cs-seo-metabox-js handle (post edit screens) for inline script delivery
* Added uninstall.php — cleans up all options, post meta, transients, and cron on plugin deletion

= 4.12.5 =
* PCP fix: HTTPS scanner script moved from echoed <script> block to wp_add_inline_script via ob_start capture
* PCP fix: font optimizer script moved from echoed <script> block to wp_add_inline_script via ob_start capture
* PCP fix: main settings page script (abTab, abState, category fixer, related articles) moved to wp_add_inline_script via ob_start capture
* PHP values abNonce, abAjax, abMinChar, abMaxChar, abHasApiKey, cfNonce, chNonce, cdNonce, rcNonce now passed via csSeoAdmin (wp_localize_script)

= 4.12.4 =
* PCP fix: settings page CSS moved from echoed <style> block to wp_add_inline_style()
* PCP fix: llms.txt preview JS moved from echoed <script> block to wp_add_inline_script()
* PCP fix: sitemap preview JS moved from echoed <script> block to wp_add_inline_script()
* PHP values (ajaxUrl, nonce, sitemapIndexUrl) now passed via wp_localize_script

= 4.12.3 =
* Refactored monolithic plugin into 23 trait files for maintainability
* AI SEO scoring per post added to admin panel

= 4.11.38 =
* Panel UI: bold blue drop shadow, contrast body background
* Post list pagination set to 50 posts/page with consistent page numbers

= 4.11.23 =
* PHPCS: disable/enable blocks for multi-column POST warnings and meta_query suppression
* Save button left padding fix

= 4.11.33 =
* Post titles in AI Image ALT Text Generator and AI Summary Box panels are now clickable links to the post editor

= 4.11.32 =
* Fix: Noindex posts are now excluded from the Update Posts with AI Descriptions panel and post count stats

= 4.11.26 =
* Dashboard widget now shows SEO Health pills: Posts, SEO, Images, Links, Summaries with colour coding (green >= 90%, amber >= 60%, red < 60%)
* Health data timestamp and Refresh link added to widget — rebuilds cache on demand
* Run Health Check CTA shown when no cache exists yet
* Health cache auto-rebuilds after SEO meta, ALT text, Summary, and Related Articles bulk runs complete
* Health cache AJAX endpoint switched to cs_seo_nonce for consistency

= 4.10.68 =
* Category Fixer colour legend added above table showing Added, Removed, Kept pill meanings

= 4.10.67 =
* Category Fixer proposed column pill colours updated to standard WordPress traffic light palette
* Changed flag now compares sorted arrays to eliminate false positives from category ID ordering

= 4.10.66 =
* AI Analyse All button added to Category Fixer toolbar
* Per row reanalyse button now calls AI instead of local scorer
* cfAiOne() merges AI results back into post list without full page reload
* Progress counter shows live status during bulk AI analysis

= 4.10.65 =
* ajax_catfix_ai_one() handler added: analyses a single post via Claude and returns proposed category IDs
* AI prompt constrains Claude to only return IDs from the existing category list, max 4, never Uncategorized
* Results stored in post meta with source=ai

= 4.10.64 =
* Uncategorized fully excluded from Category Fixer scoring and fallback path

= 4.10.63 =
* Category Fixer proposed column now uses colour coded pills: green for additions, red for removals, grey for kept

= 4.10.62 =
* Category Fixer default view after scan now shows Changed posts only
* Active filter button highlights in forest green

= 4.10.61 =
* Category Fixer confidence badge now uses white-space:nowrap to prevent label wrapping
* Confidence column width increased to 110px

= 4.10.60 =
* Fixed explain_btn call signature in Category Fixer panel

= 4.10.59 =
* Category Fixer tab added with forest green styling
* Local keyword scoring engine: tokenises title, slug, tags, and AI summary against category list
* Weighted scoring: title 4pts, summary 3pts, tags 3pts, slug 2pts, continuity bonus 2pts
* Paginated review table with current and proposed category pills
* Per post Apply, Skip, and reanalyse buttons
* Bulk Apply All Changed with checkbox selection
* Filter bar: All, Changed, Unchanged, Low Confidence, Missing
* Results cached in post meta with MD5 fingerprint for change detection
* Reload button in panel header for full rescan

= 4.10.58 =
* Summary box row dividers now use indigo tint overriding theme td border styles with !important
* Last row explicitly gets border-bottom:none to keep clean bottom padding

= 4.10.57 =
* Summary box redesigned with modern card styling: gradient indigo header, drop shadow, rounded corners
* Row separators changed to subtle indigo tint, right cell border removed, content padding increased
* Header changed to flex layout with layers SVG icon alongside title text

= 4.10.56 =
* Summary box header updated to CloudScale SEO AI Article Summary
* Header given solid indigo background with white uppercase text
* Label column colours changed to indigo, row dividers added between rows

= 4.10.55 =
* Article JSON-LD schema now includes abstract field from Why it matters summary
* Article JSON-LD schema now includes disambiguatingDescription field from Key takeaway summary

= 4.10.54 =
* Reload and Hide Posts buttons moved into panel card headers, visible once panel is loaded
* Header buttons styled with semi-transparent white to sit cleanly on coloured headers

= 4.10.53 =
* Status text repositioned to left of toolbar buttons to prevent flex overflow at narrow viewports
* Fixes Hide Posts button being pushed off screen on 1024px displays

= 4.10.52 =
* Pagination added to ALT Text panel: 100 posts per page with Prev and Next controls
* Pagination added to AI Summary panel: 100 posts per page with Prev and Next controls

= 4.10.51 =
* Reload and Hide Posts buttons added to ALT Text and Summary panel toolbars
* Load CTA always hides after load regardless of post count

= 4.10.50 =
* AI Summary Box generator panel completed with paginated post list, Missing and Has Summary badges
* Posts sorted A to Z, Generated This Session counter, Force Regenerate All button

= 4.10.49 =
* AI Summary Box bulk generator panel added with purple header, stats row, and Generate Missing button
* Summary panel toolbar with stop button and live status text

= 4.10.48 =
* AI Summary Box admin panel scaffolded with Load Posts CTA and post list table

= 4.10.47 =
* AI Summary Box generation endpoint added: generates What it is, Why it matters, Key takeaway via Claude
* Summary stored in three separate post meta keys per post
* Returns JSON with what, why, takeaway fields

= 4.10.46 =
* AI Summary Box renderer added: prepends styled summary card to singular post content
* Summary box only renders when all three fields are populated
* show_summary_box toggle added to plugin settings

= 4.10.45 =
* PHPCS fixes: sanitized post_id inputs with absint and wp_unslash across AJAX handlers
* Suppressed meta_query slow query PHPCS notices

= 4.10.44 =
* Gutenberg sidebar panel registered via enqueue_block_editor_assets using PluginDocumentSettingPanel
* Panel includes custom SEO title, meta description, OG image URL, AI summary fields, and generate buttons
* Summary fields editable and saveable directly from the Gutenberg document sidebar

= 4.10.43 =
* Post meta keys for summary fields registered via register_post_meta for REST API and Gutenberg access

= 4.10.35 =
* Bulk Update Posts panel header buttons (Reload, Hide Posts) added to toolbar
* Toolbar status text added with live feedback during generation runs

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

= 4.17.5 =
Code quality pass: DocBlocks on all trait files, settings page i18n coverage expanded.

= 4.17.4 =
PCP compliance pass — resolves all WordPress.org Plugin Check errors and warnings.

= 4.17.3 =
Settings page now stays on the current tab after saving.

= 4.17.2 =
Fix: Generate & Sync now correctly trims link counts downward in all cases.

= 4.17.1 =
Generate Missing and Sync Counts merged into one Generate & Sync button.

= 4.17.0 =
Related Articles table post links now open the live post for previewing related links.

= 4.16.9 =
Fix: Related Articles table post links now open the correct post editor.

= 4.16.8 =
Fix: table no longer blanks out when Generate Missing finds nothing to process.

= 4.16.7 =
Sync Counts can now fill additional slots when you increase the link count setting.

= 4.16.6 =
Fix: Related Articles link count settings now save correctly.

= 4.16.5 =
New: Sync Counts button instantly normalises all Related Articles link counts to match your current settings.

= 4.16.4 =
Fix: Generate Missing now correctly finds pending posts regardless of which filter the table is currently showing.

= 4.16.3 =
Fix: Related Articles batch now correctly updates row counts live and reloads with the right filter.

= 4.16.2 =
Fix: Refresh Stale batch now reliably finds and processes posts.

= 4.16.1 =
Fix: batch operations now read directly from the visible table rows, resolving persistent "No posts to process" issue.

= 4.16.0 =
Fix: Refresh Stale now correctly finds and reprocesses completed posts.

= 4.15.9 =
Refresh Stale batch rewritten — more robust page-by-page processing, fixes "No posts to process" in certain environments.

= 4.15.8 =
Bug fix: Refresh Stale now genuinely regenerates all posts with current settings.

= 4.15.7 =
Bug fix: Related Articles batch now processes all posts, not just the first 50.

= 4.15.6 =
New: manually edit any post's meta description inline from the AI Tools table.

= 4.15.5 =
Code quality and i18n improvements; no functional changes.

= 4.15.4 =
PCP compliance fix: JSON-LD schema output now uses WordPress API, eliminating the critical echoed script tag violation.

= 4.15.3 =
Bug fix: eliminates PHP warning from batch scheduler log display.

= 4.15.2 =
Scoring progress now shows post count (e.g. "Post 23 of 186") in the status bar.

= 4.15.1 =
Generate Missing now reliably scores unscored posts in a second phase with its own fresh post fetch.

= 4.15.0 =
Generate Missing now also calculates SEO scores for any unscored posts in a second pass.

= 4.14.9 =
All data columns in the AI Tools post table are now sortable by clicking the header.

= 4.14.8 =
Bug fix: homepage SEO score now persists across page reloads.

= 4.14.7 =
AI Tools post table now has a Date column and sortable headers for Post, Date, and SEO Score.

= 4.14.6 =
Swapped Categories and Scheduled Batch tab order in settings page.

= 4.12.6 =
PCP compliance: all remaining echoed script blocks eliminated. uninstall.php added. Zero critical PCP violations remain.

= 4.12.5 =
PCP compliance: HTTPS scanner, font optimizer, and main settings page JS moved to wp_add_inline_script. No functional changes.

= 4.12.4 =
PCP compliance: settings page CSS and sitemap/llms.txt preview JS moved to wp_add_inline_style/script. No functional changes.

= 4.12.3 =
Major refactor: plugin split into 23 trait files. AI SEO scoring added per post. Recommended update for all users.

= 4.10.13 =
Fixed Last Batch Run always appearing empty. Batch results now persist permanently instead of expiring after 24 hours.

= 4.10.12 = Fixes save issue with Defer Font CSS Loading. Updated readme with full Gemini support documentation.

= 4.10.10 =
Fixes plugin header format for WordPress.org submission. Includes font display optimization for Core Web Vitals improvements.

= 4.10.0 =
Major release with font display optimization, defer JS, and HTML minification. Recommended for all users seeking better Core Web Vitals scores.
