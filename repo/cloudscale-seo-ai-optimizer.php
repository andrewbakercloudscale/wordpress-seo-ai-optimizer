<?php
/**
 * Plugin Name: CloudScale SEO AI Optimizer
 * Plugin URI:  https://andrewbaker.ninja/2026/02/24/cloudscale-seo-ai-optimiser-enterprise-grade-wordpress-seo-completely-free/
 * Description: Lightweight SEO with AI meta descriptions via Claude API. Titles, canonicals, OpenGraph, Twitter Cards, JSON-LD schema, sitemaps, robots.txt, and font display optimization.
 * Version:     4.21.47
 * Author:      Andrew Baker
 * Author URI:  https://andrewbaker.ninja/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloudscale-seo-ai-optimizer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// PHP version guard — before any PHP 8-only syntax so older versions get a clean message.
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="notice notice-error"><p>';
        echo wp_kses(
            sprintf(
                /* translators: 1: plugin name, 2: required PHP version, 3: server PHP version */
                esc_html__( '%1$s requires PHP %2$s or higher. Your server is running PHP %3$s. Please upgrade PHP or contact your host.', 'cloudscale-seo-ai-optimizer' ),
                '<strong>CloudScale SEO AI Optimizer</strong>',
                '8.0',
                esc_html( PHP_VERSION )
            ),
            array( 'strong' => array() )
        );
        echo '</p></div>';
    });
    add_action('admin_init', function(): void {
        deactivate_plugins(plugin_basename(__FILE__));
    });
    return;
}

require_once __DIR__ . '/includes/class-cs-seo-utils.php';
require_once __DIR__ . '/includes/trait-options.php';
require_once __DIR__ . '/includes/trait-minifier.php';
require_once __DIR__ . '/includes/trait-frontend-head.php';
require_once __DIR__ . '/includes/trait-summary-box.php';
require_once __DIR__ . '/includes/trait-schema.php';
require_once __DIR__ . '/includes/trait-related-articles.php';
require_once __DIR__ . '/includes/trait-metabox.php';
require_once __DIR__ . '/includes/trait-batch-scheduler.php';
require_once __DIR__ . '/includes/trait-ai-engine.php';
require_once __DIR__ . '/includes/trait-ai-meta-writer.php';
require_once __DIR__ . '/includes/trait-ai-scoring.php';
require_once __DIR__ . '/includes/trait-ai-alt-text.php';
require_once __DIR__ . '/includes/trait-ai-summary.php';
require_once __DIR__ . '/includes/trait-admin.php';
require_once __DIR__ . '/includes/trait-gutenberg.php';
require_once __DIR__ . '/includes/trait-font-optimizer.php';
require_once __DIR__ . '/includes/trait-category-fixer.php';
require_once __DIR__ . '/includes/trait-settings-page.php';
require_once __DIR__ . '/includes/trait-settings-assets.php';
require_once __DIR__ . '/includes/trait-robots-txt.php';
require_once __DIR__ . '/includes/trait-https-fixer.php';
require_once __DIR__ . '/includes/trait-sitemap.php';
require_once __DIR__ . '/includes/trait-llms-txt.php';
require_once __DIR__ . '/includes/trait-seo-health.php';
require_once __DIR__ . '/includes/trait-auto-pipeline.php';
require_once __DIR__ . '/includes/trait-readability.php';
require_once __DIR__ . '/includes/trait-redirects.php';
require_once __DIR__ . '/includes/trait-broken-links.php';
require_once __DIR__ . '/includes/trait-image-seo.php';
require_once __DIR__ . '/includes/trait-title-optimiser.php';

/**
 * Main plugin class. Composes all feature traits and wires up WordPress hooks.
 *
 * @package Cs_Seo_Plugin
 * @since   1.0.0
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
final class Cs_Seo_Plugin {

    use CS_SEO_Options;
    use CS_SEO_Minifier;
    use CS_SEO_Frontend_Head;
    use CS_SEO_Summary_Box;
    use CS_SEO_Schema;
    use CS_SEO_Related_Articles;
    use CS_SEO_Metabox;
    use CS_SEO_Batch_Scheduler;
    use CS_SEO_AI_Engine;
    use CS_SEO_AI_Meta_Writer;
    use CS_SEO_AI_Scoring;
    use CS_SEO_AI_Alt_Text;
    use CS_SEO_AI_Summary;
    use CS_SEO_Admin;
    use CS_SEO_Gutenberg;
    use CS_SEO_Font_Optimizer;
    use CS_SEO_Category_Fixer;
    use CS_SEO_Settings_Page;
    use CS_SEO_Settings_Assets;
    use CS_SEO_Robots_Txt;
    use CS_SEO_HTTPS_Fixer;
    use CS_SEO_Sitemap;
    use CS_SEO_LLMS_Txt;
    use CS_SEO_SEO_Health;
    use CS_SEO_Auto_Pipeline;
    use CS_SEO_Readability;
    use CS_SEO_Redirects;
    use CS_SEO_Broken_Links;
    use CS_SEO_Image_SEO;
    use CS_SEO_Title_Optimiser;

    const OPT        = 'cs_seo_options';
    const META_TITLE    = '_cs_seo_title';
    const META_DESC     = '_cs_seo_desc';
    const META_OGIMG    = '_cs_seo_ogimg';
    const META_SUM_WHAT    = '_cs_seo_summary_what';
    const META_SUM_WHY     = '_cs_seo_summary_why';
    const META_SUM_KEY     = '_cs_seo_summary_takeaway';
    const META_HIDE_SUMMARY = '_cs_seo_hide_summary';
    const META_NOINDEX      = '_cs_seo_noindex';

    // Related Articles meta keys
    const META_RC_TOP        = '_cs_rc_top_ids';
    const META_RC_BOTTOM     = '_cs_rc_bottom_ids';
    const META_RC_CANDIDATES = '_cs_rc_candidate_ids';
    const META_RC_SCORES     = '_cs_rc_scores';
    const META_RC_FINGERPRINT= '_cs_rc_fingerprint';
    const META_RC_VERSION    = '_cs_rc_version';
    const META_RC_GENERATED  = '_cs_rc_generated_at';
    const META_RC_LAST_STEP  = '_cs_rc_last_step';
    const META_RC_STATUS     = '_cs_rc_status';
    const META_RC_ERROR      = '_cs_rc_error';

    // SEO Health cache
    const META_ALT_ALL_DONE     = '_cs_alt_all_done';
    const META_ALT_CONTENT_HASH = '_cs_alt_content_hash';
    const OPT_HEALTH_CACHE      = 'cs_seo_health_cache';

    // SEO score (AI-generated, stored per post)
    const META_SEO_SCORE = '_cs_seo_score';
    const META_SEO_NOTES = '_cs_seo_score_notes';

    // Readability score (pure-PHP, stored per post as JSON)
    const META_READABILITY = '_cs_seo_readability';

    // Auto pipeline
    const META_AUTO_COMPLETE = '_cs_seo_auto_run_complete';
    const META_FOCUS_KW      = '_cs_seo_focus_keyword';

    // Title Optimiser meta keys
    const META_TITLE_OPT_SUGGESTED    = '_cs_seo_title_opt_suggested';
    const META_TITLE_OPT_ORIGINAL     = '_cs_seo_title_opt_original';
    const META_TITLE_OPT_KEYWORDS     = '_cs_seo_title_opt_keywords';
    const META_TITLE_OPT_SCORE_BEFORE = '_cs_seo_title_opt_score_before';
    const META_TITLE_OPT_SCORE_AFTER  = '_cs_seo_title_opt_score_after';
    const META_TITLE_OPT_NOTES        = '_cs_seo_title_opt_notes';
    const META_TITLE_OPT_STATUS       = '_cs_seo_title_opt_status';
    const META_TITLE_OPT_ANALYSED_AT  = '_cs_seo_title_opt_analysed_at';
    const OPT_TITLE_QUEUE             = 'cs_seo_title_opt_queue';
    const OPT_TITLE_JOB               = 'cs_seo_title_opt_job';
    const CRON_TITLE_OPT              = 'cs_seo_title_opt_process';

    // AEO (Answer Engine Optimisation) direct-answer paragraph
    const META_AEO_ANSWER = '_cs_seo_aeo_answer';

    // Related Articles step constants
    const RC_STEP_LOAD         = 1;
    const RC_STEP_VALIDATE     = 2;
    const RC_STEP_CANDIDATES   = 3;
    const RC_STEP_SCORE        = 4;
    const RC_STEP_TOP          = 5;
    const RC_STEP_BOTTOM       = 6;
    const RC_STEP_VALIDATE_OUT = 7;
    const RC_STEP_COMPLETE     = 8;

    // Related Articles generator version — bump when scoring logic changes
    const RC_VERSION = '1.0';

    const VERSION    = '4.21.47';

    // Separate option key for AI config — keeps sensitive data isolated.
    const AI_OPT     = 'cs_seo_ai_options';
    const FONT_DISPLAY_LOG = 'cs_seo_font_display_log';

    // Sitemap
    const SITEMAP_PER_FILE    = 5000; // URLs per XML sitemap file served to Google
    const SITEMAP_PREVIEW_PER = 200;  // Rows per page in the admin preview table
    const SITEMAP_URLS_CACHE  = 'cs_seo_sitemap_urls'; // Transient key for the full URL list

    private array $opts;
    private array $ai_opts;

    /**
     * Writes a message to the log via the shared Utils logger (requires WP_DEBUG_LOG).
     *
     * Delegates to Cs_Seo_Utils::log() so all plugin logging
     * goes through a single code path with a consistent prefix.
     *
     * @since 4.19.4
     * @param string $message The message to log.
     * @return void
     */
    private static function debug_log(string $message): void {
        Cs_Seo_Utils::log($message);
    }

    /**
     * Initialises plugin options and registers all WordPress action/filter hooks.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->opts    = $this->get_opts();
        $this->ai_opts = $this->get_ai_opts();

        // Text domain is auto-loaded by WordPress 4.6+ from the languages directory.
        add_action('admin_menu',     [$this, 'admin_menu']);
        add_action('admin_notices',  [$this, 'admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_ajax_cs_seo_download_fonts', [$this, 'ajax_download_fonts']);

        // Only defer font CSS loading if user has explicitly enabled it
        if (!is_admin() && !empty($this->opts['defer_fonts'])) {
            add_filter('style_loader_tag', [$this, 'defer_font_css'], 10, 2);
        }
        add_filter('admin_footer_text', [$this, 'admin_footer_text']);
        add_filter('update_footer',     [$this, 'admin_footer_version'], 11);
        add_action('admin_init',     [$this, 'register_settings']);
        add_action('wp_dashboard_setup', [$this, 'register_dashboard_widget']);
        add_action('add_meta_boxes', [$this, 'add_metabox']);
        add_action('save_post',      [$this, 'save_metabox'], 10, 2);
        add_action('save_post',      [$this, 'on_save_post_readability'], 20, 2);
        add_action('save_post',    function() { delete_transient('cs_seo_llms_txt'); delete_transient(self::SITEMAP_URLS_CACHE); });
        add_action('deleted_post', function() { delete_transient('cs_seo_llms_txt'); delete_transient(self::SITEMAP_URLS_CACHE); });
        add_filter('the_content',    [$this, 'prepend_aeo_answer'], 25);
        add_filter('the_content',    [$this, 'prepend_summary_box']);
        add_filter('the_content',    [$this, 'inject_related_links'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_rc_front_styles']);
        // Clear stale custom OG image when the featured image is changed.
        add_action('updated_post_meta', [$this, 'on_thumbnail_updated'], 10, 4);
        add_action('added_post_meta',   [$this, 'on_thumbnail_updated'], 10, 4);

        add_filter('pre_get_document_title', [$this, 'filter_title'], 20);
        add_action('wp_head', [$this, 'render_head'], 1);

        // Suppress WordPress core canonical (prevents duplicate canonical error).
        remove_action('wp_head', 'rel_canonical');
        add_filter('wpseo_canonical',             '__return_false', 99);
        add_filter('rank_math/frontend/canonical', '__return_false', 99);

        // Suppress Jetpack's Open Graph output — this plugin manages all OG tags.
        add_filter('jetpack_enable_open_graph', '__return_false', 99);

        // Register a dedicated 1200×630 crop for OG images — matches the WhatsApp/Facebook
        // required aspect ratio so thumbnails appear correctly in social previews.
        add_action('after_setup_theme', [$this, 'register_og_image_size']);

        add_action('init', [$this, 'maybe_register_sitemap']);
        add_action('init', [$this, 'maybe_register_llms_txt']);
        add_filter('robots_txt', [$this, 'filter_robots_txt'], 99, 2);
        add_action('init', [$this, 'register_rest_meta']);

        // WP Cron batch job for scheduled generation.
        add_action('cs_seo_daily_batch', [$this, 'run_scheduled_batch']);

        // Auto pipeline — publish/update triggers (non-blocking HTTP, no cron dependency).
        add_action('transition_post_status', [$this, 'on_post_publish'], 10, 3);
        add_action('post_updated',           [$this, 'on_post_update'],  10, 3);
        add_action('before_delete_post',     [$this, 'on_post_delete'],  10, 1);
        add_action('cs_seo_cleanup_pipeline',            [$this, 'run_cleanup_pipeline']);
        add_action('wp_ajax_cs_seo_pipeline_run',        [$this, 'ajax_pipeline_run']);
        // nopriv is intentional — the handler authenticates via a single-use HMAC token
        // (stored as a transient, expiring after 120 s) generated at fire time. No session needed.
        add_action('wp_ajax_nopriv_cs_seo_pipeline_run', [$this, 'ajax_pipeline_run']); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.wp_ajax_nopriv -- secured by HMAC token; see ajax_pipeline_run() in trait-auto-pipeline.php.
        add_action('wp_ajax_cs_seo_auto_rerun',          [$this, 'ajax_auto_rerun']);
        add_action('add_meta_boxes', [$this, 'add_auto_run_metabox']);

        // Defer JS to eliminate render-blocking scripts.
        if ((int)($this->opts['defer_js'] ?? 0)) {
            add_filter('script_loader_tag', [$this, 'defer_script_tag'], 10, 3);
        }

        // HTML/CSS/JS minification.
        if ((int)($this->opts['minify_html'] ?? 0)) {
            if (!is_admin() && !wp_doing_ajax() && !wp_doing_cron()) {
                add_action('template_redirect', [$this, 'minify_start'], 999);
                add_action('shutdown',          [$this, 'minify_end'],   0);
            }
        }

        // AJAX handlers for AI meta writer — both logged-in admin calls.
        add_action('wp_ajax_cs_seo_ai_generate_one',  [$this, 'ajax_generate_one']);
        add_action('wp_ajax_cs_seo_ai_generate_all',  [$this, 'ajax_generate_all']);
        add_action('wp_ajax_cs_seo_score_one',        [$this, 'ajax_score_one']);
        add_action('wp_ajax_cs_seo_save_desc',          [$this, 'ajax_save_desc']);
        add_action('wp_ajax_cs_seo_ai_fix_desc',        [$this, 'ajax_fix_desc']);
        add_action('wp_ajax_cs_seo_ai_fix_title',       [$this, 'ajax_fix_title']);
        add_action('wp_ajax_cs_seo_aeo_gen_one',        [$this, 'ajax_aeo_gen_one']);
        add_action('wp_ajax_cs_seo_ai_get_posts',       [$this, 'ajax_get_posts']);
        add_action('wp_ajax_cs_seo_ai_test_key',        [$this, 'ajax_test_key']);
        add_action('wp_ajax_cs_seo_ai_get_batch_log',   [$this, 'ajax_get_batch_log']);
        add_action('wp_ajax_cs_seo_regen_static',       [$this, 'ajax_regen_static']);
        add_action('wp_ajax_cs_seo_readability_score_one', [$this, 'ajax_readability_score_one']);
        add_action('wp_ajax_cs_seo_sitemap_preview',  [$this, 'ajax_sitemap_preview']);
        add_action('wp_ajax_cs_seo_llms_preview',     [$this, 'ajax_llms_preview']);
        add_action('wp_ajax_cs_seo_rename_robots',    [$this, 'ajax_rename_robots']);
        add_action('wp_ajax_cs_seo_fetch_robots',     [$this, 'ajax_fetch_robots']);
        add_action('wp_ajax_cs_seo_https_scan',       [$this, 'ajax_https_scan']);
        add_action('wp_ajax_cs_seo_https_fix',        [$this, 'ajax_https_fix']);
        add_action('wp_ajax_cs_seo_https_delete',     [$this, 'ajax_https_delete']);
        add_action('wp_ajax_cs_seo_alt_get_posts',    [$this, 'ajax_alt_get_posts']);
        add_action('wp_ajax_cs_seo_alt_generate_one',     [$this, 'ajax_alt_generate_one']);
        add_action('wp_ajax_cs_seo_alt_generate_all',     [$this, 'ajax_alt_generate_all']);
        add_action('wp_ajax_cs_seo_summary_generate_one', [$this, 'ajax_summary_generate_one']);
        add_action('wp_ajax_cs_seo_summary_load',         [$this, 'ajax_summary_load']);
        add_action('wp_ajax_cs_seo_summary_generate_all', [$this, 'ajax_summary_generate_all']);

        // Category Fixer AJAX
        add_action('wp_ajax_cs_seo_catfix_list_ids', [$this, 'ajax_catfix_list_ids']);
        add_action('wp_ajax_cs_seo_catfix_load',     [$this, 'ajax_catfix_load']);
        add_action('wp_ajax_cs_seo_catfix_analyse',  [$this, 'ajax_catfix_analyse']);
        add_action('wp_ajax_cs_seo_catfix_apply',    [$this, 'ajax_catfix_apply']);
        add_action('wp_ajax_cs_seo_catfix_skip',     [$this, 'ajax_catfix_skip']);
        add_action('wp_ajax_cs_seo_catfix_bulk_apply', [$this, 'ajax_catfix_bulk_apply']);
        add_action('wp_ajax_cs_seo_catfix_ai_one',    [$this, 'ajax_catfix_ai_one']);
        add_action('wp_ajax_cs_seo_catfix_health',      [$this, 'ajax_catfix_health']);
        add_action('wp_ajax_cs_seo_catfix_health_list', [$this, 'ajax_catfix_health_list']);
        add_action('wp_ajax_cs_seo_catfix_health_cat',  [$this, 'ajax_catfix_health_cat']);
        add_action('wp_ajax_cs_seo_catfix_drift',                  [$this, 'ajax_catfix_drift']);
        add_action('wp_ajax_cs_seo_catfix_drift_cache_get',       [$this, 'ajax_catfix_drift_cache_get']);
        add_action('wp_ajax_cs_seo_catfix_drift_analyse_remaining', [$this, 'ajax_catfix_drift_analyse_remaining']);
        add_action('wp_ajax_cs_seo_catfix_drift_move',             [$this, 'ajax_catfix_drift_move']);
        add_action('wp_ajax_cs_seo_catmig_list',   [$this, 'ajax_catmig_list']);
        add_action('wp_ajax_cs_seo_catmig_posts',  [$this, 'ajax_catmig_posts']);
        add_action('wp_ajax_cs_seo_catmig_apply',  [$this, 'ajax_catmig_apply']);
        add_action('wp_ajax_cs_seo_catmig_delete', [$this, 'ajax_catmig_delete']);

        // Related Articles — run pipeline synchronously on publish (no API, no cron dependency).
        add_action('transition_post_status', [$this, 'rc_on_post_publish'], 20, 3);

        add_action('wp_ajax_cs_seo_rc_get_posts',    [$this, 'ajax_rc_get_posts']);
        add_action('wp_ajax_cs_seo_rc_sync_counts', [$this, 'ajax_rc_sync_counts']);
        add_action('wp_ajax_cs_seo_rc_step',      [$this, 'ajax_rc_step']);
        add_action('wp_ajax_cs_seo_rc_reset',     [$this, 'ajax_rc_reset']);

        add_action('wp_ajax_cs_seo_font_scan', [$this, 'ajax_font_scan']);
        add_action('wp_ajax_cs_seo_font_fix', [$this, 'ajax_font_fix']);
        add_action('wp_ajax_cs_seo_font_undo', [$this, 'ajax_font_undo']);

        // SEO Health cache rebuild
        add_action('wp_ajax_cs_seo_rebuild_health', [$this, 'ajax_rebuild_health_cache']);

        // Redirects
        $this->init_redirects();
        add_action('wp_ajax_cs_seo_delete_redirect', [$this, 'ajax_delete_redirect']);
        add_action('wp_ajax_cs_seo_clear_redirects',  [$this, 'ajax_clear_redirects']);
        add_action('wp_ajax_cs_seo_add_redirect',     [$this, 'ajax_add_redirect']);

        // Broken Link Checker
        add_action('wp_ajax_cs_seo_blc_get_posts',     [$this, 'ajax_blc_get_posts']);
        add_action('wp_ajax_cs_seo_blc_extract_links', [$this, 'ajax_blc_extract_links']);
        add_action('wp_ajax_cs_seo_blc_check_url',     [$this, 'ajax_blc_check_url']);

        // Image SEO Audit
        add_action('wp_ajax_cs_seo_imgseo_scan', [$this, 'ajax_imgseo_scan']);

        // Title Optimiser
        add_action('wp_ajax_cs_seo_title_optimiser_load',  [$this, 'ajax_title_optimiser_load']);
        add_action('wp_ajax_cs_seo_title_optimise_one',    [$this, 'ajax_title_optimise_one']);
        add_action('wp_ajax_cs_seo_title_analyse_all',     [$this, 'ajax_title_analyse_all']);
        add_action('wp_ajax_cs_seo_title_apply_one',       [$this, 'ajax_title_apply_one']);
        add_action('wp_ajax_cs_seo_title_apply_all',       [$this, 'ajax_title_apply_all']);
        add_action('wp_ajax_cs_seo_title_fix_links',       [$this, 'ajax_title_fix_internal_links']);
        // Title Optimiser — background queue
        add_action('wp_ajax_cs_seo_title_queue_start',     [$this, 'ajax_title_queue_start']);
        add_action('wp_ajax_cs_seo_title_queue_stop',      [$this, 'ajax_title_queue_stop']);
        add_action('wp_ajax_cs_seo_title_queue_status',    [$this, 'ajax_title_queue_status']);
        add_action(self::CRON_TITLE_OPT,                   [$this, 'cron_title_opt_process']);
    }

    // =========================================================================
    // REST meta registration
    // =========================================================================

    /**
     * Registers all plugin-managed post meta fields with the REST API.
     *
     * @since 4.0.0
     * @return void
     */
    public function register_rest_meta(): void {
        foreach (['post', 'page'] as $post_type) {
            register_post_meta($post_type, self::META_TITLE, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => fn() => current_user_can('edit_posts'),
                'sanitize_callback' => 'sanitize_text_field',
            ]);
            register_post_meta($post_type, self::META_DESC, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => fn() => current_user_can('edit_posts'),
                'sanitize_callback' => 'sanitize_textarea_field',
            ]);
            register_post_meta($post_type, self::META_OGIMG, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'auth_callback'     => fn() => current_user_can('edit_posts'),
                'sanitize_callback' => 'esc_url_raw',
            ]);
            foreach ([self::META_SUM_WHAT, self::META_SUM_WHY, self::META_SUM_KEY] as $sum_key) {
                register_post_meta($post_type, $sum_key, [
                    'show_in_rest'      => true,
                    'single'            => true,
                    'type'              => 'string',
                    'auth_callback'     => fn() => current_user_can('edit_posts'),
                    'sanitize_callback' => 'sanitize_textarea_field',
                ]);
            }
            register_post_meta($post_type, self::META_HIDE_SUMMARY, [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'integer',
                'auth_callback'     => fn() => current_user_can('edit_posts'),
                'sanitize_callback' => 'absint',
            ]);
        }
    }

}

// Flush rewrite rules on activation so sitemap URLs work immediately,
// and on deactivation to clean up.
register_activation_hook(__FILE__, function(): void {
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            wp_kses(
                sprintf(
                    /* translators: %s: current PHP version on the server */
                    __( 'CloudScale SEO AI Optimizer requires PHP 8.0 or higher. Your server is running PHP %s.', 'cloudscale-seo-ai-optimizer' ),
                    esc_html( PHP_VERSION )
                ),
                array()
            ),
            esc_html__( 'Plugin Activation Error', 'cloudscale-seo-ai-optimizer' ),
            array( 'back_link' => true )
        );
    }
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__( 'CloudScale SEO AI Optimizer requires WordPress 6.0 or higher.', 'cloudscale-seo-ai-optimizer' ),
            esc_html__( 'Plugin Activation Error', 'cloudscale-seo-ai-optimizer' ),
            array( 'back_link' => true )
        );
    }
    // If a physical robots.txt exists in the WordPress root, rename it so
    // WordPress's robots_txt filter can take over. We keep the original as
    // robots.txt.bak so it can be restored if needed.
    $root      = ABSPATH;
    $physical  = $root . 'robots.txt';
    $backup    = $root . 'robots.txt.bak';
    if (file_exists($physical) && wp_is_writable($physical)) {
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        // Save the old content into plugin options so the user can review it.
        $old_content = $wp_filesystem->get_contents($physical);
        update_option('cs_seo_robots_bak', $old_content);
        $wp_filesystem->move($physical, $backup, true);
    }
    // Register rewrites first so flush has something to work with.
    $opts = get_option('cs_seo_options');
    if ($opts['enable_sitemap'] ?? 0) {
        add_rewrite_rule('^sitemap\.xml$',       'index.php?cs_seo_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-(\d+)\.xml$', 'index.php?cs_seo_sitemap=page&cs_seo_sitemap_pg=$matches[1]', 'top');
    }
    if ($opts['enable_llms_txt'] ?? 0) {
        add_rewrite_rule('^llms\.txt$', 'index.php?cs_seo_llms=1', 'top');
    }
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function(): void {
    flush_rewrite_rules();
    wp_clear_scheduled_hook('cs_seo_daily_batch');

    // Wipe any root level asset files so Deactivate > Delete > Upload
    // never leaves stale JS/CSS on disk.
    $dir = plugin_dir_path(__FILE__);
    foreach (glob($dir . 'admin.{js,css}', GLOB_BRACE) as $f) {
        wp_delete_file($f);
    }
    // Clean old assets/ subdirectory from previous versions.
    $assets = $dir . 'assets/';
    if (is_dir($assets)) {
        foreach (glob($assets . '*') as $f) {
            if (is_file($f)) {
                wp_delete_file($f);
            }
        }
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($assets);
        }
    }
    delete_option('cs_seo_loaded_version');
});

// Version change detector: cleans stale assets on upgrade (even via FTP)
// and resets OPcache so PHP serves the new code immediately.
add_action('admin_init', function(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $cached = get_option('cs_seo_loaded_version', '');
    if ($cached !== Cs_Seo_Plugin::VERSION) {
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        // Delete any old assets/ subfolder left by previous versions.
        $assets = plugin_dir_path(__FILE__) . 'assets/';
        if (is_dir($assets)) {
            foreach (glob($assets . '*') as $f) {
                if (is_file($f)) {
                    wp_delete_file($f);
                }
            }
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            if ($wp_filesystem) {
                $wp_filesystem->rmdir($assets);
            }
        }
        update_option('cs_seo_loaded_version', Cs_Seo_Plugin::VERSION);
    }
});

new Cs_Seo_Plugin();
