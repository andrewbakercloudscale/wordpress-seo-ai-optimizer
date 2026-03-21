# Changelog

All notable changes to CloudScale SEO AI Optimizer are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [4.19.46] - 2026-03-21
### Fixed
- **Model selection reverting to Automatic on page load** — `abProviderChanged()` runs on initial page load to sync provider UI; it now only resets the model selector when the current selection belongs to a different provider, preserving saved pinned model on load while still defaulting to Automatic on an active provider switch (`trait-settings-page.php`)

## [4.19.45] - 2026-03-21
### Added
- **Automatic model option** — new `_auto` model setting (now the default) always resolves to the current recommended model for the active provider at runtime (`claude-sonnet-4-6` / `gemini-2.0-flash`); pinned model selections are unaffected (`trait-options.php`, `trait-ai-engine.php`, `trait-settings-page.php`, all AI call sites)
- **`recommended_model(string $provider)`** — static method on the plugin class; single source of truth for the recommended model per provider (`trait-options.php`)
- **`resolve_model(string $model, string $provider)`** — private method; maps `_auto` or empty string to `recommended_model()`; all 14 AI dispatch call sites updated (`trait-ai-engine.php`)

## [4.19.35] - 2026-03-18
### Fixed
- **Provider switch model reset** — switching AI provider (Anthropic ↔ Gemini) now always resets the model selector to the first option for the new provider using `option.selected = true` for reliable cross-browser behaviour; previously the old provider's model remained selected (`trait-settings-page.php`)
- **Duplicate "Custom" option** — the model dropdown was showing "Custom (enter below)…" twice (once per provider); replaced with a single entry at the bottom of the list (`trait-settings-page.php`)
- **Silent `catch(e) {}` in `abGenAll`** — page-fetch pagination loop using variable `d2` was missed by the earlier batch fix; now logs via `console.error` (`trait-settings-page.php`)
- **`const VERSION` drift in `repo/`** — `repo/cloudscale-seo-ai-optimizer.php` had `const VERSION = '4.19.5'`; corrected to current version; `build.sh` now also patches the constant on every bump

## [4.19.34] - 2026-03-18
### Fixed
- **Provider switch** — first fix attempt; superseded by 4.19.35

## [4.19.33] - 2026-03-18
### Fixed
- **`repo/` version sync** — `build.sh` now updates both `* Version:` header and `const VERSION` constant in `repo/PLUGIN.php` on every version bump, and also syncs `readme.txt`; previously `repo/` drifted from the main plugin file between SVN deployments
- **`const VERSION` in `repo/`** — corrected stale value to match current version

## [4.19.32] - 2026-03-18
### Fixed
- **PCP: hidden files in zip** — `.svn-working-copy/` directory (containing hundreds of `.svn/` hidden files) was being bundled into the distribution zip; WordPress.org would reject with `hidden_files` error. Added `.svn-working-copy`, `.svn`, `.svn-credentials.sh`, `docs/`, `generate-help-docs.sh`, `build-review.sh`, `MANUAL-deploy-svn.sh`, `CloudScaleSEOAI.jpg` to `build.sh` rsync exclusions (`build.sh`)
- **PCP: version bump reading wrong file** — `build.sh` was finding `.svn-working-copy/tags/*/cloudscale-seo-ai-optimizer.php` before the real plugin file, silently bumping versions inside the SVN working copy instead of the plugin header. Fixed by excluding `.svn-working-copy` from both the `MAIN_PHP` grep and the version bump sed loop (`build.sh`)
- **PCP: inline `onchange` in PHP-rendered HTML** — removed `onchange="abModelSelectChanged()"` attribute from the `<select>` element; handler is already wired via `addEventListener` in the JS setup block (`trait-settings-page.php`)
- **CHANGELOG** — added entries for v4.19.30 and v4.19.31 (`CHANGELOG.md`)

## [4.19.31] - 2026-03-18
### Fixed
- **Gemini deprecated model** — replaced `gemini-2.0-flash` (no longer available to new users) with `gemini-2.5-flash-preview-04-17` as the default fallback across all AI traits (`trait-ai-meta-writer.php`, `trait-ai-scoring.php`, `trait-ai-summary.php`, `trait-ai-alt-text.php`, `trait-auto-pipeline.php`, `trait-batch-scheduler.php`, `trait-category-fixer.php`)
- **Inline `onchange` event handler** — removed `onchange="abModelSelectChanged()"` from PHP-rendered `<select>` tag; handler already wired via `addEventListener` in the JS setup block (`trait-settings-page.php`)
- **Hidden files in zip** — added `.svn-working-copy`, `.svn`, `docs/`, `generate-help-docs.sh`, `build-review.sh`, `MANUAL-deploy-svn.sh`, and `CloudScaleSEOAI.jpg` to the `build.sh` rsync exclusion list; these were being bundled into the distribution zip and would have caused WordPress.org `hidden_files` rejection
- **Blog category filter** — `wp:latest-posts` block on the Blog page was filtering to only 5 categories, excluding AWS Cloud, AI, Banking, Cyber, Databases and others; filter removed so all published posts appear
- **S3 public access** — added bucket policy to `andrewninjawordpress` S3 bucket to allow public-read on all objects
### Added
- **Custom model input** — "Custom (enter below)…" option in the AI model dropdown reveals a text field for entering any model ID, with examples for both Claude and Gemini and links to each provider's model docs (`trait-settings-page.php`)
- **Updated Gemini model list** — dropdown now lists Gemini 2.5 Flash Preview, 2.0 Flash 001 (stable), 2.0 Flash Lite, and 2.5 Pro Preview; removed deprecated `gemini-2.0-flash` and `gemini-1.5-pro` (`trait-settings-page.php`)
- **"View latest models" links** — provider-specific docs links shown below the model selector, switching automatically when the provider changes (`trait-settings-page.php`)

## [4.19.30] - 2026-03-18
### Added
- **WordPress.org SVN support** — `MANUAL-deploy-svn.sh` script syncs `repo/` to trunk, commits trunk, and tags the release; `shared-help-docs/help-runner.sh` shared library for help doc generation across all plugins
- **Help documentation system** — `generate-help-docs.sh` + `tests/generate-help-docs.js` take panel-level screenshots of every admin section, upload to WordPress Media Library, and create/update the "Help & Documentation" page under `/wordpress-plugin-help/seo-ai-optimizer/`; pages survive temp-user deletion via `--reassign` fix
- **"CloudScale WordPress Plugins" nav dropdown** — added to site navigation with dropdown items for all 6 CloudScale plugins
- **Batch run history in seconds** — `elapsed` field changed from minutes to integer seconds in `trait-batch-scheduler.php`
### Fixed
- **`readme.txt` Contributors field** — changed `andrewbaker007` to `andrewjbaker` to match WordPress.org SVN username
- **Short description** — trimmed to exactly 150 characters (`and` → `&`)
- **`build.sh` syntax error** — removed orphaned `else/fi` block that was causing `syntax error near unexpected token 'else'`
- **`repo/readme.txt` sync** — `build.sh` now copies `readme.txt` into `repo/` after every version bump so SVN trunk always has the correct `Stable tag`

## [4.19.29] - 2026-03-17
### Fixed
- **`rcRunOne`** — `fetch` and `r.json()` inside the `while` step-loop were not wrapped in `try/catch`; a network error or non-JSON response silently rejected the Promise, halting the run with no user feedback. Both are now guarded; errors surface via `rcUpdateRow` with the error message (`trait-settings-page.php`)
- **`rcBatch`** — `await rcRunOne(postId)` was not wrapped per-iteration; a single failing post rejected the entire batch loop, leaving the progress bar stuck and `rcBatchRunning = true`. Each call is now wrapped in `try/catch` so the batch continues to the next post on failure (`trait-settings-page.php`)
- **`cdMoveAll`** — entire function body had no `try/catch`; any runtime error inside the move loop silently rejected the Promise with no user feedback and the button stayed disabled. Added outer `try/catch` with button re-enable on failure (`trait-settings-page.php`)
- **Silent `catch(e) {}`** in page-fetch pagination loops inside `abScoreAll`, `abGenAll`, `abFixAll`, `abFixTitles`, and `abRegenStatic` — failures were swallowed with no developer or user visibility; all five now call `console.error('[cs-seo] page-fetch failed', e)` (`trait-settings-page.php`)
- **Silent `catch(e) {}`** in `rcBatch` page-fetch pagination and `rcRunOne` row-refresh loop — same fix; both now log to console (`trait-settings-page.php`)
- **`rcRunOne`** — `row.querySelector('td:nth-child(2)')` result was used without a null-check before `.innerHTML` assignment; the result is now stored in a variable and guarded (`trait-settings-page.php`)

## [4.19.28] - 2026-03-17
### Fixed
- **Category Drift** — AI was occasionally suggesting "Uncategorized" as a move destination. Added explicit `NEVER suggest moving posts to the "Uncategorized" category` instruction to both drift prompts (`ajax_catfix_drift` and `ajax_catfix_drift_analyse_remaining`), client-side filter that skips any merged move group whose `to` value is "uncategorized" (case-insensitive), and server-side guard in `ajax_catfix_drift_move` that rejects such requests with an error (`trait-category-fixer.php`, `trait-settings-page.php`)

## [4.19.27] - 2026-03-17
### Fixed
- **Category Drift** — after using "Move" or "Move all", refreshing the page reloaded the cached drift results and showed moved posts as if they had not been moved. `ajax_catfix_drift_move` now updates the `cs_seo_drift_cache` option after each successful move, removing the post from the relevant entry's `posts` list and from every move group's `post_ids` array (`trait-category-fixer.php`)

## [4.19.26] - 2026-03-17
### Fixed
- **Category Drift** — "Move all" button was absent from move groups containing only one matched post (condition was `postCount > 1`); changed to `postCount > 0` so every non-empty group gets the button (`trait-settings-page.php`)
- **Category Drift** — the same post could appear in multiple move buckets (different destinations); moving it in one bucket left stale "→ Move" buttons active in the others. Added `cdMovedPostIds` (a session-level `Set`) that is populated on every successful move; on move success, all `.cd-move-btn[data-post-id="N"]` elements across the entire table are found and dimmed simultaneously (`trait-settings-page.php`)
- **Category Drift** — "Move all N posts" button was rendered inside the collapsible post list div, so it was hidden until the list was expanded. Moved outside the collapsible div to sit inline next to the "▼ N posts" toggle (`trait-settings-page.php`)

## [4.19.25] - 2026-03-17
### Fixed
- **Category Drift** — the AI sometimes returns multiple move groups with the same `to` destination for a single category. These are now merged client-side before rendering: groups sharing the same destination (case-insensitive) are collapsed into one bucket with deduplicated post IDs (`trait-settings-page.php`)

## [4.19.24] - 2026-03-17
### Fixed
- **Category Health filter pills** — label and count were rendering on separate lines because the `<button>` template literal contained newlines between the `<span>` dot, the label text, and the `<strong>` count, creating whitespace text nodes inside the flex container. Collapsed to a single line with `white-space:nowrap` (`trait-settings-page.php`)

## [4.19.23] - 2026-03-17
### Added
- **Category Drift — Move Post / Move all** — each matched post in a drift move group now has a "→ Move" button that moves it from the drift-flagged category to the AI-suggested destination. Groups with multiple posts get a "→ Move all N" button. Moving is done via the new `cs_catfix_drift_move` AJAX endpoint which resolves the target category by name (creating it if absent) and removes the post from the source category. The button turns green and shows "✓ Moved" on success. New PHP method `ajax_catfix_drift_move` registered at `wp_ajax_cs_catfix_drift_move` (`trait-category-fixer.php`, `trait-settings-page.php`, `cloudscale-seo-ai-optimizer.php`)
- **Category Health — clickable filter pills** — the static legend row (● Strong ● Moderate…) and the separate stats row (Strong: 5, Moderate: 3…) were merged into a single row of clickable filter buttons. Each pill shows the colour dot, grade label, and count; clicking it filters the table to show only that grade. An "All N" pill resets the filter. Active pill is highlighted. Filter resets to "All" on each reload (`trait-settings-page.php`)
### Fixed
- **Category Health filter pills** — the static legend is removed from the HTML; `chLoad()` no longer references the removed `ch-legend` element (`trait-settings-page.php`)

## [4.19.22] - 2026-03-17
### Added
- **Category Health — per-category progress** — loading now runs in two phases: a fast `cs_catfix_health_list` call returns all category IDs/names (no post queries), then JS processes each category individually via `cs_catfix_health_cat`, showing "Processing category N of M: [name]" so stuck queries are immediately visible. New PHP methods `ajax_catfix_health_list` and `ajax_catfix_health_cat` registered at `wp_ajax_cs_catfix_health_list` and `wp_ajax_cs_catfix_health_cat`. `ajax_catfix_health` retained but marked deprecated (`trait-category-fixer.php`, `cloudscale-seo-ai-optimizer.php`)
- **Category Health — sort and filter** — `chLoad()` resets `chCurrentFilter` to `'all'` on each reload; client-side grade sort mirrors the original server-side order (`trait-settings-page.php`)
### Fixed
- **Category Health — Reload button** — `chLoad()` was a single blocking request; when it stalled the Reload button appeared to do nothing because reloading produced the same stalled state. The batched approach makes each category independently observable. Added `chLoading` guard to prevent parallel loads (`trait-settings-page.php`)
- **Font Optimizer — path traversal** — `ajax_font_undo` accepted a raw `file_path` from `$_POST` and passed it directly to `$wp_filesystem->put_contents()`; an attacker with `manage_options` could write arbitrary files outside ABSPATH. Added `realpath()` validation to confirm the resolved path is within `ABSPATH` before any write (`trait-font-optimizer.php`)

## [4.19.21] - 2026-03-17
### Fixed
- **Category Fixer** — posts were sorted by title ascending in `ajax_catfix_list_ids` and `ajax_catfix_load`; changed to `date DESC` so the scan and table display newest posts first (`trait-category-fixer.php`)

## [4.19.20] - 2026-03-16
### Added
- **Category Fixer — batched scan** — "Scan Posts" now fetches post IDs in a single fast call then processes configurable-sized batches with live progress ("Scanning post N of M"), matching the existing AI analysis loop pattern; individual batch failures are caught and logged without aborting the full scan
- **Playwright UI tests** — end-to-end test suite covering login, admin navigation, and Category Fixer scan flow added under `tests/`
### Fixed
- Error handling hardened across Category Fixer AJAX paths

## [4.19.7] - 2026-03-16
### Fixed
- **Scan Posts** button (Categories tab) not responding to clicks — button had no `id` after the PCP refactor removed its `onclick`; added `id="cf-scan-btn"` and replaced broken `querySelector('[onclick=...]')` with `on('cf-scan-btn', fn)` (`trait-settings-page.php`)
- **Analyse Categories** button had the same issue — added `id="ch-analyse-btn"` and wired via `on()` (`trait-settings-page.php`)
- **↻ Refresh** (Robots live preview) not responding — same root cause; added `id="ab-robots-refresh-btn"` and wired via `on()` (`trait-settings-page.php`)
- **Reset to default** (Robots textarea) not responding — same root cause; added `id="ab-robots-reset-btn"` and wired via `on()` (`trait-settings-page.php`)

## [4.19.6] - 2026-03-16
### Added
- SEO Score badge click now opens a modal showing the AI feedback notes with a **Copy Feedback** button and a **Re-score** button; unscored badges still trigger scoring directly (`trait-settings-page.php`)
- Per-row **✦ Generate** button added to AI Summary Box Generator table — calls `cs_seo_summary_generate_one` with `force: 1` for targeted single-article regeneration (`trait-settings-page.php`)
- Per-row **✦ Generate** button now shown on every row in the AI Image ALT Text Generator table (previously hidden for posts with no missing ALT); always calls with `force: 1` (`trait-settings-page.php`)
### Fixed
- Tab selection no longer resets to "AI Tools" on every page refresh — the `DOMContentLoaded` tab click handler in `trait-admin.php` now delegates to `abTab()` so `localStorage` is updated on every tab click (`trait-admin.php`)
- Show/Hide Details buttons broken on all admin screens — `getElementById` replaced with `querySelector('.' + cardId)`; auto-load logic for update-posts, alt, and summary cards restored (`trait-admin.php`)
- `sumGenOne` activity-log line produced `"✓ undefined"` when `querySelector` returned `null`; now falls back to `"Post #N"` (`trait-settings-page.php`)
- Inline `onmouseover`/`onmouseout` event handlers removed from `render_rc_block()` frontend output; replaced with `.cs-rc-link` CSS class delivered via `wp_add_inline_style()` (`trait-related-articles.php`)
- PHPCS `NonceVerification.Missing` false-positive suppressions added to `$_POST` reads in `ajax_summary_generate_one()`, `ajax_summary_generate_all()`, `ajax_https_fix()`, `ajax_https_delete()`, and `ajax_sitemap_preview()` — nonce verified by `ajax_check()` in every case
- PHPCS `NonPrefixedHooknameFound` false-positive suppression added to `apply_filters('https_local_ssl_verify',…)` — WordPress core filter, not a plugin-owned hook (`trait-auto-pipeline.php`)
- DocBlocks completed (missing `@since`/`@param`/`@return`) for private methods in `trait-font-optimizer.php`, `trait-https-fixer.php`, `trait-related-articles.php`, `trait-sitemap.php`, and `trait-seo-health.php`
- Redundant `[CloudScale SEO]` prefix removed from all `debug_log()` call-site strings in `trait-font-optimizer.php` — `Utils::log()` already prepends it
### Changed
- `readme.txt` `Tested up to` restored to `6.9` after an incorrect downgrade to `6.8` during a prior standards review

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
