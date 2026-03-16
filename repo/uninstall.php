<?php
/**
 * Uninstall — removes all plugin data from the database on plugin deletion.
 *
 * Runs only when the user deletes the plugin via WP Admin > Plugins > Delete.
 * Deactivation does not trigger this file.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// ── Named options ─────────────────────────────────────────────────────────────
$cs_seo_options = [
    'cs_seo_options',
    'cs_seo_ai_options',
    'cs_seo_batch_history',
    'cs_seo_robots_bak',
    'cs_seo_loaded_version',
    'cs_seo_health_cache',
    'cs_seo_font_display_log',
];
foreach ( $cs_seo_options as $cs_seo_opt ) {
    delete_option( $cs_seo_opt );
}

// ── Wildcard options (font backup keys, attachment ID transient cache) ─────────
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'cs_seo_font_backup_%'
        OR option_name LIKE '_transient_cs_seo_attid_%'
        OR option_name LIKE '_transient_timeout_cs_seo_attid_%'"
);

// ── Named transients ─────────────────────────────────────────────────────────
delete_transient( 'cs_seo_llms_txt' );
delete_transient( 'cs_seo_sitemap_urls' );

// ── Post meta ─────────────────────────────────────────────────────────────────
$cs_seo_meta_keys = [
    // Core SEO
    '_cs_seo_title',
    '_cs_seo_desc',
    '_cs_seo_ogimg',
    '_cs_seo_noindex',
    // AI Summary Box
    '_cs_seo_summary_what',
    '_cs_seo_summary_why',
    '_cs_seo_summary_takeaway',
    '_cs_seo_hide_summary',
    // AI SEO Score
    '_cs_seo_score',
    '_cs_seo_score_notes',
    // ALT text cache
    '_cs_alt_all_done',
    '_cs_alt_content_hash',
    // OG letterbox image
    '_cs_seo_og_letterbox_url',
    // Related Articles
    '_cs_rc_top_ids',
    '_cs_rc_bottom_ids',
    '_cs_rc_candidate_ids',
    '_cs_rc_scores',
    '_cs_rc_fingerprint',
    '_cs_rc_version',
    '_cs_rc_generated_at',
    '_cs_rc_last_step',
    '_cs_rc_status',
    '_cs_rc_error',
];
foreach ( $cs_seo_meta_keys as $cs_seo_key ) {
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
    delete_post_meta_by_key( $cs_seo_key );
}

// Remove any remaining _cs_* post meta not covered by named keys above
// (e.g. category fixer working data stored per-post).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_cs\_%'"
);

// ── Scheduled cron event ──────────────────────────────────────────────────────
wp_clear_scheduled_hook( 'cs_seo_daily_batch' );
