=== CloudScale SEO AI Optimizer ===
Contributors: andrewjbaker
Tags: free yoast alternative, ai seo, claude ai, seo audit, schema generator
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 4.21.44
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free Yoast alternative with AI. Meta descriptions, schema, site audit & one-click fixes. Use your own Claude or Gemini key. No subscription, ever.

== Description ==

**Stop paying $99/year for Yoast Premium.** CloudScale SEO AI Optimizer replaces Yoast Premium and RankMath Pro with a completely free plugin that uses your own Anthropic Claude or Google Gemini API key. No subscription. No Pro version. No licence keys. No upsells. Everything works out of the box.

The AI generation costs less than $0.10 to run across 100 posts using Claude Haiku. Google Gemini has a free tier sufficient for most personal blogs. You pay the AI provider directly — the plugin charges nothing.

**Setup takes 2 minutes:** Install → paste your free API key (get one at console.anthropic.com or aistudio.google.com) → click Generate Missing. Done.

It handles all the technical SEO WordPress leaves out — sitemaps, robots.txt, structured data, OpenGraph, canonical URLs — and adds a full AI toolkit that generates meta descriptions, ALT text, article summaries, FAQ schema, and related article links in bulk. A built-in **Site Audit** scores your entire SEO setup across 20+ checks and surfaces **one-click quick fixes** so you can resolve issues without leaving the audit panel.

= Core SEO Features =

* Custom meta title and description per post and page
* Canonical URL output on every page
* OpenGraph tags (title, description, image, type, locale)
* Twitter/X Card tags
* JSON-LD structured data: Person schema for author pages, Article/BlogPosting schema for posts, WebSite schema for the homepage, Breadcrumb schema
* Configurable site name, locale, Twitter handle, and default OG image
* XML sitemap generation (sitemap.xml index + child sitemaps) with configurable post types and taxonomy support
* Plain-text sitemap at /sitemap.txt (one URL per line) for AI crawlers and simple scrapers
* Custom robots.txt editor with AI bot blocking (GPTBot, CCBot, Claude-Web, anthropic-ai and others)
* llms.txt support for AI crawler guidance
* noindex controls for search results, 404 pages, attachment pages, author archives, and tag archives
* UTM parameter stripping in canonical URLs

= AI Auto Pipeline =

* Automatically runs all AI operations in a separate background process the moment a post is published — no WP-Cron dependency
* Steps run per publish: meta description, SEO score, focus keyword, ALT text for all post images, AI-suggested internal links, AI summary box, and Related Articles
* Re-run on update toggle: re-triggers the full pipeline whenever a published post is saved
* Gutenberg-safe internal link injection using block-level parsing; classic editor fallback via str_replace
* Minimum 50-word content guard prevents meaningless output on stub or test posts
* HMAC-authenticated async request (120-second TTL) keeps the pipeline secure
* "Re-run AI Automation" button in the post metabox with live log output
* Auto Pipeline settings live in a dedicated card at the top of the AI Tools tab

= AI Meta Writer =

* Choose your AI provider: Anthropic Claude or Google Gemini
* Model selector: Automatic (always resolves to the current recommended model), Claude 3.5/3.7 Sonnet, Claude Haiku, Gemini 2.0 Flash, Gemini 1.5 Pro, or a Custom model string
* Generate meta descriptions for individual posts or in bulk across your entire site
* Fix existing descriptions that are too short or too long
* Fix titles that are outside the optimal 50 to 60 character range
* Inline edit button on each post row — opens a textarea to manually enter or correct a description without leaving the panel
* Configurable character range (min/max) injected into the prompt automatically
* Automatic retry if the AI returns a description outside your target range
* Rate limit handling with automatic backoff on HTTP 429 responses
* Fully editable system prompt with reset to default
* Sortable post table: sort by title, date, SEO score, description length, title length, or ALT status
* Live progress log with timestamps during bulk runs
* Stop button for interrupting bulk runs
* Scheduled batch generation via WP Cron with per-day scheduling
* Test Key button to verify your API key before running

= AI SEO Scoring =

* AI rates each post from 0 to 100 with a one-sentence strengths or weaknesses note
* Calculate SEO Scores button runs a bulk scoring pass across all posts
* Generate Missing automatically scores any post that lacks a score after descriptions are written
* Per post score badges shown in the AI Tools post table and dashboard widget
* Scores stored in post meta (_cs_seo_score, _cs_seo_notes) and survive plugin deactivation

= Title Optimiser =

* New 🎯 Title Optimiser tab — AI scans all published posts and suggests SEO-optimised replacement titles
* Before/after SEO score (0–100) for every suggestion so you can see the improvement at a glance
* Identifies primary and secondary keywords the article is actually about
* One-click Apply per post: updates title and URL slug, automatically creates a 301 redirect from the old URL
* "Apply All Suggested" bulk action with confirmation — applies and redirects all in one shot
* Sort posts by date or by most-commented to prioritise which titles to fix first
* Suggestions stored in post meta — safe to pause, review, and apply selectively

= AI Summary Box =

* AI-generated article summary box automatically prepended to post content
* Three fields generated per post: What it is, Why it matters, Key takeaway
* Summaries now written SEO-first: primary keyword front-loaded, secondary keywords woven in, optimised for search intent rather than conversational reading
* Bulk generation panel with progress tracking, stop button, and paginated post list
* Force regenerate option to overwrite all existing summaries
* Summary fields written to Article JSON-LD schema: description, abstract, and disambiguatingDescription
* Collapsible display with modern card styling including gradient header and drop shadow
* Toggle to show or hide the summary box globally without deleting generated content

= ALT Text Generator =

* Audit all images across your posts for missing ALT text
* Generate ALT text using AI with article context for better relevance
* Configurable article excerpt length sent to the AI (100 to 2000 characters)
* Bulk generation with progress tracking
* Show All toggle to display images that already have ALT text

= Related Articles =

* Automatically injects contextually related post links at the top and bottom of every post
* AI-scored candidate pool built across the full post library; top and bottom counts configurable (2 to 5 top, 3 to 10 bottom)
* Separate top and bottom toggles — enable or disable each block independently
* Generate Missing button runs the scoring pipeline for unprocessed posts
* Refresh Stale button re-runs previously completed posts when content has changed
* Sync Counts button trims or fills all posts to match updated count settings without full regeneration
* Post Status table shows per-post pipeline state (pending, complete, failed) with filter tabs
* All injection is block-safe and works with both Gutenberg and classic editor posts
* Related Articles links are also generated automatically via the Auto Pipeline on publish

= Performance Features =

* Font display optimization with font-display: swap to eliminate Flash of Invisible Text (FOIT)
* Font metric overrides (size-adjust, ascent-override, descent-override) to reduce Cumulative Layout Shift (CLS)
* Defer font CSS loading using media="print" swap technique
* Auto-download CDN fonts (Google Fonts) to local server for faster loading and GDPR compliance
* Font CSS file scanner with terminal-style console output
* Auto-Fix All with backup and undo capability
* Defer render-blocking JavaScript with configurable exclusions
* HTML, CSS, and JS minification (5 to 15 percent page size reduction)
* HTTPS mixed content scanner and one-click fixer across posts, pages, metadata, options, and comments

= SEO Health Dashboard =

* Dashboard widget shows five health pillars: Posts (meta coverage), SEO (score coverage), Images (ALT coverage), Links (related articles coverage), Summaries (AI summary coverage)
* Colour-coded pills: green >= 90%, amber >= 60%, red < 60%
* Refresh link rebuilds the health cache on demand; cache auto-rebuilds after any bulk AI run completes
* "Posts need AI auto run" and "pipeline jobs queued" counters keep you informed of pending work

= Dashboard Integration =

* WordPress dashboard widget with SEO health overview and per-pillar coverage pills
* Post editor metabox with custom title, description, OG image, and inline AI generation
* Gutenberg sidebar panel (CloudScale Meta Boxes) with custom title, description, OG image, AI summary fields, and one-click generation without leaving the editor
* Per-post status badges showing description length, title length, SEO score, and ALT status
* Tab state persists across page reloads — the settings page returns to your last active tab

= Category Fixer =

* Scans all published posts and suggests improved category assignments using AI
* Uses Claude to analyse post title, slug, tags, and AI summary box against your full category list
* Proposes up to four categories per post — only from categories that already exist in WordPress
* Never assigns Uncategorized
* Colour-coded review table: green for additions, red for removals, grey for kept categories
* Per post Apply and Skip buttons, plus bulk Apply All Changed
* Filter bar: All, Changed, Unchanged, Low Confidence, Missing
* Reload button re-analyses all posts with fresh AI calls
* Per row re-analyse button for individual posts
* AI analysis badge shows confidence score
* No categories are changed until you explicitly click Apply

= Category Health and Drift Detection =

* Category Health tab shows post counts per category with a pass/fail coverage indicator
* Category Drift Detection uses AI to identify categories that have drifted from their original focus or become catch-all buckets
* Drift analysis returns a verdict (drifting or catch-all) with a confidence score and AI reasoning for each flagged category
* Results sorted by verdict type then confidence so the most actionable items appear first
* Elapsed time counter and Stop button during long analysis runs

= Readability Analyser =

* Pure-PHP readability scoring — no AI call required
* Scores 0–100 with Easy / Moderate / Hard label based on sentence length, heading density, and passive-voice rate
* Colour-coded badge in the post metabox with sub-metrics (average words per sentence, words per heading, passive voice percentage)
* Sortable Readability column in the Meta Writer post list
* Scores automatically recalculate on post save and after every Auto Pipeline run

= Broken Link Checker =

* Scans all published posts and pages for outbound links with HTTP errors (4xx, 5xx) or connection failures
* Server-side HEAD request per URL for accurate status — no browser-side fetch limitations
* Deduplicates URLs across posts so each external URL is checked only once
* Results table shows post title, anchor text, URL, and HTTP status with colour-coded labels
* SSRF-safe: link-local, loopback, and private IP ranges are blocked server-side

= Image SEO Audit =

* Scans the entire Media Library and flags images with SEO issues
* Detects missing ALT text, camera-default filenames (IMG_001, DSC_0045, screenshot2, etc.), and oversized files (> 500 KB)
* Results sorted by issue count with thumbnail previews and direct edit links
* Summary counters for each issue type

= What This Plugin Does Not Do =

* No third-party SEO data, keyword research databases, or rank tracking
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

= Broken Link Checker (server-side URL probing) =

**What it does:** When you use the Broken Link Checker in WP Admin, the plugin extracts all outbound hyperlinks from your published posts and pages, then sends an HTTP HEAD request from your server to each unique URL to check its status.
**Data sent:** Only the URL itself is fetched — no post content, no user data. Standard HTTP headers (User-Agent identifying your site) are sent with each request.
**When it fires:** Only when you open the Broken Link Checker tab and start a scan. No automatic or scheduled scanning.
**Note:** `sslverify` is disabled for these requests so that sites with expired or self-signed certificates can be checked. Requests to loopback, link-local, and private IP ranges are blocked.

There is no separate terms of service for outbound HTTP HEAD requests — your server is simply fetching publicly reachable URLs listed in your own content.

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

= What does Auto Pipeline do? =

Auto Pipeline fires a background process the moment a post is published. It runs every AI step in sequence: meta description, SEO score, focus keyword, ALT text for all post images, AI-suggested internal links, AI summary box, and Related Articles. The process runs in a separate PHP request so it does not slow down the publish action. You can also trigger a re-run manually from the post metabox using the "Re-run AI Automation" button.

= What is the Automatic model option? =

Selecting Automatic tells the plugin to always use the current recommended model for your chosen provider (currently claude-sonnet-4-6 for Anthropic and gemini-2.0-flash for Google). When Anthropic or Google release a better default model the plugin will automatically switch without any settings change on your part. Users who have already pinned a specific model are not affected.

= How does Related Articles work? =

The plugin scores all posts against each other using shared categories, tags, and content signals, then stores a ranked candidate pool per post. The top two to five links appear above the post content and three to ten appear below. You control the counts from the Related Articles settings. The Generate Missing button processes unscored posts; Sync Counts adjusts existing results to match updated count settings without running the full pipeline again.

== Screenshots ==

1. SEO Settings tab showing site identity, OG tags, and schema configuration
2. AI Tools tab with Auto Pipeline card, provider selection, API key, and model chooser
3. Generate Descriptions panel with summary cards, bulk action buttons, and live progress log
4. Post table showing per-post description, title, SEO score, and ALT status badges
5. Post editor metabox with custom title, description field, and Re-run AI Automation button
6. Performance tab with font optimization, JavaScript deferral, and minification settings
7. ALT Text Generator with image audit table and bulk generation
8. Related Articles Post Status table with pipeline state filters and Sync Counts button
9. Category Fixer review table with colour-coded category pills and Apply/Skip controls
10. Dashboard widget showing SEO health pills with colour-coded coverage scores

== Changelog ==

= 4.21.44 =
* Add: Schema column in AI Meta Description Writer table — shows ✓/✗ per-post JSON-LD schema coverage; sortable
* Add: First-run welcome banner — guides new installs through getting an API key with direct links to Anthropic and Gemini
* Add: FAQ schema auto-generation in Auto Pipeline — every new post publish generates FAQPage JSON-LD automatically
* Add: SEO Site Audit section added to help documentation
* Change: WordPress.org tags updated to free yoast alternative, ai seo, claude ai, seo audit, schema generator
* Change: readme.txt short description and opening paragraph rewritten to lead with the value proposition
* Fix: Help docs table of contents numbering — replaced CSS columns layout with two separate ordered lists

= 4.20.93 =
* feat: Add "Generate Missing Titles" button — batch-generates SEO title tags for posts with no _cs_seo_title set
* feat: Add "Have Title Tag" counter card in the summary row alongside "Have Description"

= 4.20.67 =
* Add: Broken Link Checker — Date Created column in results table; Post, Date Created, and Status headers are now sortable
* Fix: Broken Link Checker — sites returning 503 to server-side requests (Cloudflare JS-challenge) now treated as alive, eliminating false positives
* Add: Redirects — Created column moved next to Last hit and made sortable; Hits, Last hit, and Created all support click-to-sort
* Fix: Broken Link Checker — sites returning 401 to server-side requests (Reuters, WatchMojo) now treated as alive, eliminating false positives

= 4.20.27 =
* Security: Broken Link Checker SSRF guard — `blc_is_ssrf_blocked()` now rejects URLs that resolve to loopback, link-local, or private IP ranges before making the `wp_remote_head()` call
* Fix: Title Optimiser stale detection — added 60-second grace period so applying a title never immediately flags the post as "Edited since analysis" due to `wp_update_post()` timing
* Fix: Title Optimiser applied-post hint text corrected from "re-analyse to compare originals" to "Title was changed to suggested title"
* Docs: Added Readability, Broken Link Checker, and Image SEO Audit feature sections to readme description
* Docs: Corrected "What This Plugin Does Not Do" — removed incorrect "no readability scoring" claim; added BLC to External Services section

= 4.20.2 =
* Add: Title Optimiser — new 🎯 Title Optimiser tab; AI suggests keyword-rich replacement titles for all published posts; shows before/after SEO score and identified keywords; apply individually or in bulk; applying a title auto-creates a 301 redirect from the old URL
* Change: AI Summary Box now uses SEO-first prompts — primary keyword front-loaded in every field, secondary keywords woven in naturally, written for search intent; existing summaries unchanged until regenerated

= 4.19.142 =
* Add: Readability scoring — pure-PHP analysis of sentence length, heading density, and passive-voice rate; no AI call required; scores 0–100 with Easy / Moderate / Hard labels
* Add: Readability badge in post metabox — colour-coded score with sub-metrics (avg words/sentence, words per heading, passive voice %); Score button for on-demand rescoring; auto-refreshes after meta description generation
* Add: Sortable Readability column in Meta Writer post list — badge updates live during pipeline run
* Add: Auto-pipeline and save_post hook both trigger readability scoring to keep scores fresh
* Add: Migrate Categories panel in the Categories tab — lists all categories sorted by post count (fewest first) and lets you migrate posts category-by-category
* Add: Single-category posts require a swap target; multi-category posts can be removed or swapped per row
* Add: Apply per-row or Apply All to batch-migrate every pending post in one click
* Add: Delete Category button appears automatically once a category is empty — available in both the category list (for already-empty categories) and in the migration view once all posts are migrated; server-side guard prevents deletion if posts still exist
* Fix: Settings save safety net — new option keys no longer silently drop on save

= 4.19.126 =
* Add: Target audience and Writing tone fields in Site Identity — injected into every AI request; largest quality improvement available without editing the system prompt
* Add: Prominent callout banner above new fields nudging users to fill them in before generating
* Improve: Default AI system prompt rewritten to actively use site context for niche and voice matching
* Improve: Site context header in all AI call sites updated from passive label to active instruction
* Improve: Help documentation — Auto Pipeline and Redirects now have screenshots; new fields documented; Explain buttons tip added
* Fix: Right-side padding too tight — added padding-right to .ab-pane so card shadow is not clipped

= 4.19.99 =
* Add: Initial implementation of Target audience and Writing tone site context fields

= 4.19.98 =
* Fix: PCP critical — raw script tag in redirects admin moved to wp_add_inline_script to comply with WordPress.org standards
* Fix: delete-redirect and clear-all-redirects fetch calls now have catch handlers for network errors
* Fix: PCP NonceVerification.Missing — all AJAX handlers now call check_ajax_referer() directly in scope (was delegated via helper wrapper)
* Fix: esc_attr() added to colour ternary expression in batch history table
* Fix: removed stale "No redirect management" line from readme

= 4.19.90 =
* Fix: Settings save broken for all checkbox fields — added hidden fallback inputs so unchecked checkboxes correctly save 0
* Fix: Redirects zone-header white-on-white — added teal background colour to redirects card header
* Fix: Manual redirect form and stored redirects table were rendering outside the card container
* Fix: Save button left padding corrected across all admin cards
* Add: Playwright settings-save tests covering all panels

= 4.19.89 =
* Fix: Redirects zone-header white-on-white — added background colour to redirects card header
* Fix: Add Manual Redirect and Stored Redirects sections moved inside the card container
* Fix: Save button left padding corrected across all admin cards

= 4.19.88 =
* Change: Sitemap & Robots tab renamed to Sitemap, Robots & Redirects
* Change: Redirects section moved to Sitemap, Robots & Redirects tab

= 4.19.87 =
* Change: Redirects section moved to bottom of Optimise SEO tab with zone-card styling and Explain button

= 4.19.86 =
* Fix: Manual redirect row was inserting into wrong table element — fixed tbody selector

= 4.19.85 =
* Add: Automatic Redirects — 301 redirect automatically captured and served when a published post or page slug is renamed
* Add: Manual redirect form — add custom path→URL redirects for any resource including image paths and arbitrary old paths
* Add: Hit counter and last-hit timestamp on every redirect entry, displayed inline next to the old path
* Add: Clickable old-path links in the redirect table for one-click testing
* Fix: Save Changes on the Redirects tab was silently ignored — enable_redirects added to sanitize_opts known-fields guard
* Fix: 301 redirects not firing — moved hook to template_redirect priority 0 to run before cs_pcr_maybe_custom_404 which exits at priority 1
* Fix: enable_redirects default changed to enabled (1) for fresh installs

= 4.19.72 =
* Update: version bump

= 4.19.50 =
* Add: "Automatic" model option — new default that always resolves to the current recommended model per provider (claude-sonnet-4-6 / gemini-2.0-flash); existing users with a pinned model are unaffected

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

= 4.0.0 – 4.12.9 =
* For the complete version history of all earlier releases see CHANGELOG.md in the plugin directory.

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

= 4.0.0 – 4.14.6 =
For earlier upgrade notices and full version history see CHANGELOG.md in the plugin directory.
