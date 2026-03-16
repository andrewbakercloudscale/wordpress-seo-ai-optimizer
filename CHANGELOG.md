# Changelog

All notable changes to CloudScale SEO AI Optimizer are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased] - 2026-03-16
### Fixed
- Medium: Inline `onmouseover`/`onmouseout` event handlers removed from `render_rc_block()` output in `trait-related-articles.php`; replaced with CSS class `cs-rc-link` and a `wp_add_inline_style()` call via the new `enqueue_rc_front_styles()` method (hooked to `wp_enqueue_scripts`)
- Medium: DocBlocks added to five private methods in `trait-font-optimizer.php`: `scan_enqueued_css`, `resolve_css_path`, `fix_css_fonts`, `patch_font_face_blocks`, `undo_font_fixes`
- Medium: DocBlocks completed for five private methods in `trait-https-fixer.php`: `https_replace_value`, `has_incomplete_class`, `https_replace_serialized_raw`, `https_replace_recursive`, `https_replace_string`
- Medium: DocBlocks added to ten private RC pipeline methods in `trait-related-articles.php`: `rc_step_load` through `rc_step_complete`, `rc_fingerprint`, `rc_keywords`
- Medium: `@since` tag added to `compute_alt_content_hash()` DocBlock in `trait-seo-health.php`
- Medium: DocBlocks added to three private methods in `trait-sitemap.php`: `get_all_sitemap_urls`, `build_sitemap_index`, `build_sitemap_page`
- Low: Redundant `[CloudScale SEO]` prefix stripped from all `debug_log()` call-site strings in `trait-font-optimizer.php` — `Utils::log()` already prepends the prefix, so passing it again produced double-prefixed log lines
- Low: `phpcs:ignore NonceVerification.Missing` added to `$_POST` reads in `ajax_summary_generate_one()` and `ajax_summary_generate_all()` (`trait-ai-summary.php`), `ajax_https_fix()` and `ajax_https_delete()` (`trait-https-fixer.php`), and `ajax_sitemap_preview()` (`trait-sitemap.php`) — nonce is verified by `ajax_check()` in each case; PHPCS cannot trace the delegation
- Low: `phpcs:ignore NonPrefixedHooknameFound` added to the `apply_filters( 'https_local_ssl_verify', ... )` call in `trait-auto-pipeline.php` — `https_local_ssl_verify` is a WordPress core filter, not a plugin-owned hook


### Fixed
- Show/Hide Details buttons broken on all admin screens — `getElementById` in the toggle listener replaced with `querySelector('.' + cardId)` since cards use CSS classes, not IDs; auto-load logic for update-posts, alt, and summary cards restored; two pre-existing broken `if/else` blocks in dashboard widget fetch handlers corrected (`trait-admin.php`)
- `sumGenOne` activity-log line used `({}).textContent` when `querySelector` returned `null`, producing `"✓ undefined"` — now falls back to `"Post #N"` (`trait-settings-page.php`)
### Added
- SEO Score badge click now opens a modal showing the AI feedback notes with a **Copy Feedback** button and a **Re-score** button; unscored badges still trigger scoring directly (`trait-settings-page.php`)
- Per-row **✦ Generate** button added to AI Summary Box Generator table — calls `cs_seo_summary_generate_one` with `force: 1` for targeted single-article regeneration (`trait-settings-page.php`)
- Per-row **✦ Generate** button now shown on every row in the AI Image ALT Text Generator table (previously hidden for posts with no missing ALT); always calls with `force: 1` (`trait-settings-page.php`)
### Changed
- `readme.txt` `Tested up to` restored to `6.9` (current WordPress stable) after an incorrect downgrade to `6.8` during standards review

## [4.19.5] - 2026-03-15
### Added
- `sitemap.txt` endpoint — plain-text sitemap (one URL per line) now served at `/sitemap.txt` alongside the existing XML sitemap; reuses the cached URL list built for `sitemap.xml`

## [4.19.4] - 2026-03-14
### Fixed
- Critical: `debug_log()` missing from main class — font optimizer and OG letterbox AJAX handlers threw fatal `Call to undefined method` errors; method added and delegated to `Utils::log()` for a single logging code path
- Medium: Orphaned duplicate DocBlock before `ajax_fix_title()` and `ajax_get_posts()` in `trait-ai-meta-writer.php` removed
- Medium: Missing DocBlocks added to `call_ai_generate_desc()`, `collect_images_needing_alt()`, `call_ai_generate_all()`, trait-level declarations, and main class `__construct()` / `register_rest_meta()`
- Medium: `@package` tag added to `trait-settings-assets.php` file DocBlock

## [4.19.3] - 2026-03-14
### Fixed
- PCP: `post__not_in` phpcs:ignore comments moved to same line as violation in trait-auto-pipeline.php, trait-ai-meta-writer.php, and trait-sitemap.php — PHPCS only recognises inline suppression
- Standards reference `references/performance.md` updated with `post__not_in` guidance

## [4.19.2] - 2026-03-14
### Fixed
- Critical: Echoed `<script>` tag in `render_auto_run_metabox()` replaced with `wp_add_inline_script('cs-seo-metabox-js', ...)` via `ob_start` capture
- Medium: Orphaned DocBlock on `on_post_delete` restored; duplicate consecutive DocBlock removed
- Medium: Button label `'\u21ba Re-run AI Automation'` used PHP single-quoted string — PHP does not expand `\u` escapes; replaced with literal UTF-8 character `↺`
- Medium: Inline conditional colour echo in metabox log wrapped with `esc_attr()`
- Medium: `render_rc_block()` DocBlock missing `@since` and `@return` — added
### Changed
- File-level DocBlock updated to reflect non-blocking HTTP approach (not WP-Cron)

## [4.19.1] - 2026-03-14
### Added
- Explain buttons added to Auto Pipeline, Mixed Content Fix, and Render & Minification cards
### Changed
- Auto Pipeline card description updated to reflect non-blocking HTTP (not cron)

## [4.19.0] - 2026-03-14
### Added
- Minimum 50-word content guard on all AI pipeline steps — stubs and test posts are silently skipped

## [4.18.9] - 2026-03-14
### Changed
- AI auto pipeline replaced WP-Cron with non-blocking `wp_remote_post()` to `admin-ajax.php` — fires immediately on publish with no cron dependency
- HMAC token (120 s TTL transient) authenticates the async pipeline request
- `cs_seo_auto_run_pipeline` cron hook removed; cleanup pipeline retains cron

## [4.18.8] - 2026-03-14
### Added
- Related Articles pipeline now runs synchronously via `transition_post_status` — no cron dependency
### Changed
- AI pipeline added `spawn_cron()` on shutdown as a cron-kick fallback (superseded by 4.18.9)

## [4.18.7] - 2026-03-14
### Fixed
- Reload button moved to left of Show/Hide Details; uses `visibility:hidden` so layout never shifts on first load

## [4.18.6] - 2026-03-14
### Added
- Auto Pipeline card moved to AI Tools tab with its own Save button
- "Re-run on update" toggle — re-triggers full pipeline 5 s after any published post is saved
### Changed
- Auto Pipeline settings removed from Scheduled Batch card

## [4.18.5] - 2026-03-14
### Fixed
- Duplicate Reload buttons removed from inside AI Meta Writer, ALT Text, and Summary Box toolbars

## [4.18.4] - 2026-03-14
### Fixed
- `altLoad()` and `sumLoad()` crashed on removed button elements preventing auto-load on card expand

## [4.18.3] - 2026-03-14
### Changed
- Removed all "Hide Posts" buttons from AI Meta Writer, ALT Text, Summary Box, Category Fixer, and Category Health cards

## [4.18.2] - 2026-03-14
### Changed
- Removed "Load Posts" / "Scan Posts" CTA buttons; cards now auto-load on first "Show Details" expand

## [4.18.1] - 2026-03-14
### Added
- "Auto Run on publish" toggle (disabled by default) gates the pipeline

## [4.18.0] - 2026-03-14
### Added
- `trait-auto-pipeline.php` — full AI auto pipeline on publish: meta description, focus keyword, ALT text, internal links, AI summary, Related Articles
- Gutenberg-safe internal link injection via `parse_blocks` / `serialize_blocks`
- Delete cleanup pipeline removes all `_cs_*` post meta and run log transients
- Post edit screen metabox "CloudScale SEO Auto Run" with status, last-run time, re-run button, and step log
- `CSEO_ASYNC_ENABLED` constant for synchronous debug mode
- Dashboard widget: posts missing auto run + queued pipeline jobs metrics
- New meta keys: `_cs_seo_auto_run_complete`, `_cs_seo_focus_keyword`

## [4.17.6] - 2026-03-14
### Fixed
- Related Articles "All Posts" filter uses direct DB query — bypasses WP_Query environment issue that returned 0 results
- "All Posts" is now the default filter so new posts are immediately visible

## [4.17.5] - 2026-03-14
### Added
- File-level DocBlocks (`@package`, `@since`) added to all 20 remaining trait files
### Fixed
- Settings page `<th>` labels and RC table labels wrapped in `esc_html_e()`
- `ajax_rc_sync_counts` `@since` history corrected

## [4.17.4] - 2026-03-14
### Fixed
- PCP: removed discouraged `load_plugin_textdomain()` call
- PCP: added missing `translators` comment to `printf` in metabox
- PCP: removed `set_time_limit()` call in `ajax_rc_sync_counts`
- PCP: prefixed global variables in `uninstall.php`
- PCP: added `phpcs:ignore` to Utils class declaration with explanation

## [4.17.3] - 2026-03-13
### Changed
- Settings page restores the active tab after saving (stored in `localStorage`)

## [4.17.2] - 2026-03-13
### Fixed
- Generate & Sync correctly decreases link counts when `_cs_rc_scores` was deleted
- Button label showed `&amp;` literal after completion

## [4.17.1] - 2026-03-13
### Changed
- Merged "Generate Missing" and "Sync Counts" into single "Generate & Sync" server-side pass

## [4.17.0] - 2026-03-13
### Changed
- Related Articles Post Status table: post title links open the live post URL

## [4.16.9] - 2026-03-13
### Fixed
- Post title links in Related Articles Post Status table now open the post editor (not category editor)

## [4.16.8] - 2026-03-13
### Fixed
- Clicking Generate Missing when all posts are complete no longer shows "No posts found" — table restores to previous filter/page

## [4.16.7] - 2026-03-13
### Changed
- Sync Counts now fills upward as well as trimming when count setting increases

## [4.16.6] - 2026-03-13
### Fixed
- Related Articles settings (`rc_top_count`, `rc_bottom_count`, `rc_enable`) were silently discarded on save

## [4.16.5] - 2026-03-13
### Added
- "Sync Counts" button — single server-side pass to trim stored links to current count settings

## [4.16.4] - 2026-03-13
### Fixed
- Generate Missing now force-reloads with `filter=pending` before collecting IDs

## [4.16.3] - 2026-03-13
### Fixed
- `rcRunOne` final state fetch uses `rcCurrentFilter` instead of hardcoded `filter=all`
- Reset All reloads with current filter
- Batch bar shows post count at start

## [4.16.2] - 2026-03-13
### Fixed
- Related Articles table autoload uses `filter=complete` instead of `filter=all`

## [4.16.1] - 2026-03-13
### Fixed
- `rcBatch` reads post IDs from visible DOM rows instead of pre-fetch API call

## [4.16.0] - 2026-03-13
### Fixed
- Refresh Stale queries `filter=complete` — resolves "No posts to process" in some environments

## [4.15.9] - 2026-03-13
### Changed
- `rcBatch` rewritten to use page-1 probe then process page-by-page

## [4.15.8] - 2026-03-13
### Fixed
- Refresh Stale / Retry Failed now reset posts to pending before regenerating

## [4.15.7] - 2026-03-13
### Fixed
- Related Articles batch now fetches all pages before building the queue

## [4.15.6] - 2026-03-13
### Added
- AI Tools post table: inline ✏ Edit button to manually enter or edit meta descriptions

## [4.15.5] - 2026-03-13
### Added
- Utils class `includes/class-cloudscale-seo-ai-optimizer-utils.php` with `log()`, `get_int()`, `get_text()`, `plain_text()` helpers
### Fixed
- `load_textdomain` moved from `plugins_loaded` to `init`
- PHP version notice uses i18n with `translators` comment
- DocBlocks completed on all methods in `trait-options.php` and key methods across engine and schema traits

## [4.15.4] - 2026-03-12
### Fixed
- Critical: JSON-LD structured data output via `wp_print_inline_script_tag()` — removes the last echoed `<script>` string

## [4.15.3] - 2026-03-12
### Fixed
- PHP Warning "Undefined array key message" in batch scheduler log display

## [4.15.2] - 2026-03-12
### Changed
- Scoring status bar shows "(Post N of Total)" counter

## [4.15.1] - 2026-03-12
### Fixed
- Generate Missing phase 2 scoring does its own fresh post fetch

## [4.15.0] - 2026-03-12
### Added
- Generate Missing runs a second phase to score posts still missing an SEO score
### Changed
- "Score All" button renamed "Calculate SEO Scores"

## [4.14.9] - 2026-03-12
### Added
- AI Tools post table: Description, Title, ALT, Date, SEO Score columns all sortable

## [4.14.8] - 2026-03-12
### Fixed
- Homepage SEO score no longer disappears on reload

## [4.14.7] - 2026-03-12
### Added
- AI Tools post table: Date column and sortable headers for Post, Date, SEO Score

## [4.14.6] - 2026-03-12
### Changed
- Swapped Categories and Scheduled Batch tab order

## [4.14.5] - 2026-03-12
### Fixed
- High: `load_plugin_textdomain()` was never called; text domain now registered on `plugins_loaded`
- High: Raw HTML echo in robots.txt writable status wrapped with `wp_kses_post()`
- Medium: Eight bare `echo` of hardcoded ternary CSS/attribute values wrapped with `esc_attr()`
- Medium: Batch log entry count echo cast to `(int)`
### Changed
- CHANGELOG.md updated to cover 4.13.x–4.14.x releases

## [4.14.4] - 2026-03-12
### Fixed
- Category Drift: `cdRenderDrift()` now uses `post_ids` for matching on page reload, preventing reversion to stale unanalysed count

## [4.14.3] - 2026-03-12
### Changed
- Category Drift: debug fields removed from server response; status line cleaned to `(N move groups, M posts matched)`

## [4.14.2] - 2026-03-12
### Fixed
- Category Drift: PHP title→ID lookup now normalises smart/curly apostrophes, en-dashes, em-dashes, and HTML entities before comparing — resolves 0-match failures on posts with non-ASCII punctuation in titles

## [4.14.1] - 2026-03-12
### Changed
- Category Drift: unmatched titles now always shown in status line (not only when totalIds === 0)

## [4.14.0] - 2026-03-12
### Added
- Category Drift: debug info (unmatched AI titles, known title keys) added to server response to diagnose title→ID mismatches

## [4.13.9] - 2026-03-12
### Fixed
- Category Drift: combined ID + title matching restores correct unanalysed count after fixing regression introduced in 4.13.8

## [4.13.8] - 2026-03-12
### Fixed
- Category Drift: `stillUnassigned` now uses post_ids (ID-based exact match) instead of title fuzzy match for counting remaining unanalysed posts

## [4.13.7] - 2026-03-12
### Added
- Category Drift: PHP server-side title→ID resolution; each move group now returns `post_ids` for reliable client-side matching
- Category Drift: visible `(N move groups, M posts matched)` status line shown after each Analyse run

## [4.13.6] - 2026-03-12
### Fixed
- Category Drift: `▼ N unanalysed posts` toggle button text now updates after analysis (was static from initial render)

## [4.13.5] - 2026-03-12
### Fixed
- Category Drift: `cdMatchPost` was defined inside `cdRenderDrift()` making it invisible to `cdAnalyseRemaining()` — caused silent ReferenceError leaving the Analyse button permanently disabled after each run

## [4.13.4] - 2026-03-12
### Fixed
- Version bump only; no functional changes from 4.13.3

## [4.13.3] - 2026-03-12
### Changed
- Category Drift: elapsed timer moved inside Analyse button text (`🤖 Analysing N posts… (8s)`) for more visible feedback

## [4.13.2] - 2026-03-12
### Added
- Category Drift: Stop button with AbortController on both `cdLoad()` (Run Analysis) and `cdAnalyseRemaining()` (Analyse N remaining)
- Category Drift: elapsed time counter on Run Analysis; post count shown in Analyse button label during run

## [4.13.1] - 2026-03-12
### Fixed
- PHP operator-precedence bug in `defer_font_css()` noscript href — preg_match result was concatenated before ternary, making href always empty; also added `esc_attr()` to href output
### Changed
- i18n: `esc_html__()` / `esc_html_e()` added to user-visible strings in admin notice, metabox labels/buttons, and frontend summary box
- `admin_page_css()`, `llms_preview_js()`, `sitemap_preview_js()` extracted to new `trait-settings-assets.php` (reduces trait-settings-page.php by ~300 lines)

## [4.13.0] - 2026-03-12
### Added
- CHANGELOG.md created in Keep-a-Changelog format
### Changed
- Dashboard widget title wrapped with `wp_kses_post()` and `esc_html()` for the version span
- `@since`, `@param`, `@return` DocBlocks added to all public methods across all 23 trait files

## [4.12.9] - 2026-03-12
### Security
- Removed `wp_ajax_nopriv_` registration from `ajax_download_fonts` (admin-only handler)
- Added `esc_attr()` to three unescaped ternary echo outputs in `render_metabox()`
### Changed
- Replaced all `file_get_contents`/`file_put_contents` calls in font optimizer with WP_Filesystem equivalents

## [4.12.8] - 2026-03-12
### Fixed
- SEO score not returned when post title needed fixing — `call_ai_generate_all()` was overwriting `$json_shape` and omitting `seo_score`/`seo_notes` fields

## [4.12.7] - 2026-03-12
### Changed
- Dashboard widget Posts pill colour changed from grey (`#475569`) to blue (`#2271b1`)

## [4.12.6] - 2026-03-12
### Fixed
- PHP parse error (invalid heredoc body indentation) in `admin_page_css()`, `llms_preview_js()`, `sitemap_preview_js()` — nowdoc closing markers moved to column 0
### Security
- Dashboard widget health-refresh and health-run `<script>` blocks moved to `wp_add_inline_script` via `ob_start` capture
- Post editor metabox `csSeoGenOne` and `csSeoSumGenOne` `<script>` blocks moved to `wp_add_inline_script` via `ob_start` capture
- Registered `cs-seo-dashboard-js` (dashboard) and `cs-seo-metabox-js` (post edit screens) handles for inline script delivery
### Added
- `uninstall.php` — cleans up all options, post meta, transients, and cron on plugin deletion

## [4.12.5] - 2026-03-12
### Security
- HTTPS scanner script moved from echoed `<script>` block to `wp_add_inline_script` via `ob_start` capture
- Font optimizer script moved from echoed `<script>` block to `wp_add_inline_script` via `ob_start` capture
- Main settings page script (abTab, abState, category fixer, related articles) moved to `wp_add_inline_script` via `ob_start` capture
- PHP values `abNonce`, `abAjax`, `abMinChar`, `abMaxChar`, `abHasApiKey`, `cfNonce`, `chNonce`, `cdNonce`, `rcNonce` now passed via `csSeoAdmin` (`wp_localize_script`)

## [4.12.4] - 2026-03-12
### Security
- Settings page CSS moved from echoed `<style>` block to `wp_add_inline_style()`
- llms.txt preview JS moved from echoed `<script>` block to `wp_add_inline_script()`
- Sitemap preview JS moved from echoed `<script>` block to `wp_add_inline_script()`
- PHP values `ajaxUrl`, `nonce`, `sitemapIndexUrl` now passed via `wp_localize_script`

## [4.12.3] - 2026-03-11
### Added
- ABSPATH direct access protection on all trait files
### Changed
- Refactored monolithic plugin into 23 trait files for maintainability
- AI SEO scoring per post added to admin panel

## [4.11.38] - 2026-03-11
### Changed
- Panel UI: bold blue drop shadow, contrast body background
- Post list pagination set to 50 posts/page with consistent page numbers

## [4.11.33] - 2026-03-11
### Changed
- Post titles in AI Image ALT Text Generator and AI Summary Box panels are now clickable links to the post editor

## [4.11.32] - 2026-03-11
### Fixed
- Noindex posts now excluded from the Update Posts with AI Descriptions panel and post count stats

## [4.11.26] - 2026-03-11
### Added
- Dashboard widget SEO Health pills: Posts, SEO, Images, Links, Summaries with colour coding
- Health data timestamp and Refresh link added to widget
- Run Health Check CTA shown when no cache exists
- Health cache auto-rebuilds after bulk runs complete

## [4.10.68] - 2026-03-11
### Added
- Category Fixer colour legend above table

## [4.10.66] - 2026-03-11
### Added
- AI Analyse All button added to Category Fixer toolbar
- Per-row reanalyse now calls AI instead of local scorer

## [4.10.65] - 2026-03-11
### Added
- `ajax_catfix_ai_one()` handler: analyses a single post via Claude and returns proposed category IDs

## [4.10.59] - 2026-03-11
### Added
- Category Fixer tab with local keyword scoring engine, paginated review table, bulk Apply All Changed, filter bar

## [4.10.50] - 2026-03-11
### Added
- AI Summary Box generator panel completed with paginated post list and Force Regenerate All button

## [4.10.46] - 2026-03-11
### Added
- AI Summary Box renderer — prepends styled summary card to singular post content

## [4.10.44] - 2026-03-11
### Added
- Gutenberg sidebar panel with custom SEO title, description, OG image, AI summary fields

## [4.10.34] - 2026-03-11
### Added
- OG image 1200×630 crop with `og:image:secure_url` for WhatsApp scraper
- ALT text scanner now includes featured images

## [4.10.22] - 2026-03-11
### Fixed
- Converted inline style echo to `wp_add_inline_style`
- Moved inline script blocks to `wp_add_inline_script`
- Added `wp_localize_script` for PHP values

## [4.10.18] - 2026-03-11
### Added
- Deactivation hook removes stale asset files
- Version change detector cleans leftover assets and resets OPcache

## [4.10.12] - 2026-03-11
### Changed
- All performance controls moved into the Performance tab

## [4.10.0] - 2026-03-11
### Added
- Font display optimisation with font-display swap, metric overrides, and CDN download
- JavaScript deferral with configurable exclusions
- HTML, CSS, JS minification
- HTTPS mixed content scanner and one-click fixer

## [4.9.14] - 2026-03-11
### Added
- WordPress dashboard widget

## [4.9.7] - 2026-03-11
### Added
- ALT text article excerpt length now configurable (100–2000 chars)

## [4.9.3] - 2026-03-11
### Added
- Defer render-blocking JavaScript feature with configurable exclusions

## [4.2.0] - 2026-03-11
### Changed
- Character range decoupled from system prompt

## [4.1.0] - 2026-03-11
### Added
- Automatic correction pass for out-of-range descriptions

## [4.0.0] - 2026-03-11
### Added
- Initial release of AI Meta Writer tab
- Bulk meta description generation with live progress log
- Per-post generation from post editor metabox
