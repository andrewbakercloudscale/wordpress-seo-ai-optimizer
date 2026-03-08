<?php
/**
 * Plugin Name: CloudScale SEO AI Optimizer
 * Plugin URI:  https://andrewbaker.ninja/2026/02/24/cloudscale-seo-ai-optimiser-enterprise-grade-wordpress-seo-completely-free/
 * Description: Lightweight SEO with AI meta descriptions via Claude API. Titles, canonicals, OpenGraph, Twitter Cards, JSON-LD schema, sitemaps, robots.txt, and font display optimization.
 * Version:     4.10.45
 * Author:      Andrew Baker
 * Author URI:  https://andrewbaker.ninja/
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloudscale-seo-ai-optimizer
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) exit;

// PHP version guard — before any PHP 8-only syntax so older versions get a clean message.
if (version_compare(PHP_VERSION, '8.0', '<')) {
    add_action('admin_notices', function(): void {
        echo '<div class="notice notice-error"><p><strong>CloudScale SEO AI Optimizer</strong> requires PHP 8.0 or higher. Your server is running PHP ' . esc_html(PHP_VERSION) . '. Please upgrade PHP or contact your host.</p></div>';
    });
    add_action('admin_init', function(): void {
        deactivate_plugins(plugin_basename(__FILE__));
    });
    return;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
final class CloudScale_SEO_AI_Optimizer {

    const OPT        = 'cs_seo_options';
    const META_TITLE    = '_cs_seo_title';
    const META_DESC     = '_cs_seo_desc';
    const META_OGIMG    = '_cs_seo_ogimg';
    const META_SUM_WHAT = '_cs_seo_summary_what';
    const META_SUM_WHY  = '_cs_seo_summary_why';
    const META_SUM_KEY  = '_cs_seo_summary_takeaway';
    const VERSION    = '4.10.45';

    // Separate option key for AI config — keeps sensitive data isolated.
    const AI_OPT     = 'cs_seo_ai_options';
    const FONT_DISPLAY_LOG = 'cs_seo_font_display_log';

    private array $opts;
    private array $ai_opts;

    /**
     * Log debug messages only when WP_DEBUG is enabled.
     *
     * @param string $message The message to log.
     * @return void
     */
    private static function debug_log(string $message): void {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }
    }

    public function __construct() {
        $this->opts    = $this->get_opts();
        $this->ai_opts = $this->get_ai_opts();

        add_action('admin_menu',     [$this, 'admin_menu']);
        add_action('admin_notices',  [$this, 'admin_notices']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_assets']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_block_editor_assets']);
        add_action('wp_ajax_nopriv_cs_seo_download_fonts', [$this, 'ajax_download_fonts']);
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
        add_filter('the_content',    [$this, 'prepend_summary_box']);
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
        add_action('wp_ajax_cs_seo_ai_fix_desc',        [$this, 'ajax_fix_desc']);
        add_action('wp_ajax_cs_seo_ai_fix_title',       [$this, 'ajax_fix_title']);
        add_action('wp_ajax_cs_seo_ai_get_posts',       [$this, 'ajax_get_posts']);
        add_action('wp_ajax_cs_seo_ai_test_key',        [$this, 'ajax_test_key']);
        add_action('wp_ajax_cs_seo_ai_get_batch_log',   [$this, 'ajax_get_batch_log']);
        add_action('wp_ajax_cs_seo_regen_static',       [$this, 'ajax_regen_static']);
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
        add_action('wp_ajax_cs_seo_summary_generate_all', [$this, 'ajax_summary_generate_all']);

        // Font-display optimization
        add_action('wp_ajax_cs_seo_font_scan', [$this, 'ajax_font_scan']);
        add_action('wp_ajax_cs_seo_font_fix', [$this, 'ajax_font_fix']);
        add_action('wp_ajax_cs_seo_font_undo', [$this, 'ajax_font_undo']);
    }

    // =========================================================================
    // Options
    // =========================================================================

    public static function defaults(): array {
        $site = get_bloginfo('name');
        return [
            'site_name'               => $site,
            'site_lang'               => 'en-US',
            'title_suffix'            => ' | ' . $site,
            'home_title'              => $site,
            'home_desc'               => '',
            'default_desc'            => '',
            'default_og_image'        => '',
            'twitter_handle'          => '',
            'enable_og'               => 1,
            'enable_schema_person'    => 1,
            'enable_schema_website'   => 1,
            'enable_schema_article'   => 1,
            'enable_schema_breadcrumbs' => 1,
            'show_summary_box'          => 1,
            'strip_tracking_params'   => 1,
            'enable_sitemap'          => 0,
            'enable_llms_txt'         => 0,
            'noindex_search'          => 1,
            'noindex_404'             => 1,
            'noindex_attachment'      => 1,
            'noindex_author_archives' => 0,
            'noindex_tag_archives'    => 0,
            'person_name'             => '',
            'person_job_title'        => '',
            'person_url'              => home_url('/'),
            'person_image'            => '',
            'sameas'                  => '',
            'robots_txt'              => self::default_robots_txt(),
            'block_ai_bots'           => 1,
            'sitemap_post_types'      => ['post', 'page'],
            'sitemap_taxonomies'      => 0,
            'sitemap_exclude'         => '',
            'defer_js'                => 0,
            'defer_js_excludes'       => '',
            'defer_fonts'             => 0,
            'minify_html'             => 0,
            'font_display_enabled'    => 1,
            'font_display_value'      => 'swap',
            'font_metric_overrides'   => 1,
        ];
    }

    public static function ai_defaults(): array {
        return [
            'ai_provider'      => 'anthropic',
            'anthropic_key'    => '',
            'gemini_key'       => '',
            'model'            => 'claude-sonnet-4-20250514',
            'overwrite'        => 0,
            'min_chars'        => 140,
            'max_chars'        => 155,
            'alt_excerpt_chars'=> 600,
            'prompt'           => self::default_prompt(),
            'schedule_enabled' => 0,
            'schedule_days'    => [],
        ];
    }

    public static function default_robots_txt(): string {
        return "User-agent: Googlebot\nAllow: /\nDisallow: /wp-admin/\nDisallow: /wp-login.php\nDisallow: /xmlrpc.php\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?prp_page_paginated_recent_posts\n\nUser-agent: *\nAllow: /\nDisallow: /wp-admin/\nDisallow: /wp-login.php\nDisallow: /xmlrpc.php\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?prp_page_paginated_recent_posts";
    }

    private static function default_prompt(): string {
        return 'You are an expert SEO copywriter. Site context will be injected automatically from the site settings below.

Write a single meta description for the article provided. Rules:
- HARD LIMIT: The character range is specified separately — count carefully before outputting. If your draft exceeds the maximum, shorten it. If it is under the minimum, expand it.
- Include the primary keyword or topic naturally in the first half
- Must be a complete, compelling sentence that makes a reader want to click
- No marketing fluff. No "In this post..." or "This article covers..." openers
- Write as a factual, punchy statement about what the article delivers
- Output ONLY the meta description text — no quotes, no labels, nothing else';
    }

    private function get_opts(): array {
        $saved = get_option(self::OPT, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    private function get_ai_opts(): array {
        $saved = get_option(self::AI_OPT, []);
        return array_merge(self::ai_defaults(), is_array($saved) ? $saved : []);
    }

    // =========================================================================
    // REST meta registration
    // =========================================================================

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
        }
    }

    // =========================================================================
    // Title filter
    // =========================================================================

    public function filter_title(string $default): string {
        if (is_admin()) return $default;

        if (is_front_page() || is_home()) {
            $t = trim((string) $this->opts['home_title']);
            return $t ?: $default;
        }

        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_TITLE, true));
            if ($custom !== '') return $custom;
            return $default;
        }

        $suffix = (string) $this->opts['title_suffix'];
        if ($suffix && substr($default, -strlen($suffix)) !== $suffix) {
            return $default . $suffix;
        }
        return $default;
    }

    // =========================================================================
    // Head output
    // =========================================================================

    public function render_head(): void {
        if (is_admin()) return;
        echo $this->build_seo_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_seo_block() returns pre-escaped HTML
    }

    /**
     * Add defer attribute to enqueued frontend scripts to eliminate render blocking.
     * Skips the admin area, login page, and a built-in safety exclusion list, plus
     * any handles or URL substrings the site owner has added in settings.
     *
     * Why defer and not async?
     *   defer  — scripts execute after HTML parsing, in order. Safe for almost everything.
     *   async  — scripts execute as soon as downloaded, out of order. Breaks scripts that
     *            depend on each other (e.g. jQuery + plugins).
     */
    public function defer_script_tag(string $tag, string $handle, string $src): string {
        // Never touch admin or login pages.
        if (is_admin()) return $tag;

        // Never add defer to a tag that already has defer or async.
        if (strpos($tag, ' defer') !== false || strpos($tag, ' async') !== false) return $tag;

        // Built-in exclusion list — handles and URL substrings that must not be deferred.
        $builtin_excludes = [
            // jQuery must load synchronously so dependent scripts can call $(document).ready()
            // before DOMContentLoaded fires — deferring it breaks virtually every theme.
            'jquery',
            'jquery-core',
            'jquery-migrate',
            // WordPress core inline scripts that call wp.apiFetch etc. immediately.
            'wp-embed',
            // WooCommerce checkout/cart — timing sensitive.
            'wc-checkout',
            'wc-cart',
            'wc-add-to-cart',
            // reCAPTCHA / hCaptcha — must load synchronously for form validation.
            'recaptcha',
            'hcaptcha',
            // Google Analytics / Tag Manager — usually self-async but let them manage it.
            'google-tag-manager',
            'gtag',
            // Elementor frontend must load before the DOM is painted.
            'elementor-frontend',
        ];

        // User-defined exclusions (handle names or URL substrings, one per line).
        $user_excludes_raw = trim((string)($this->opts['defer_js_excludes'] ?? ''));
        $user_excludes     = $user_excludes_raw
            ? array_filter(array_map('trim', explode("\n", $user_excludes_raw)))
            : [];

        $all_excludes = array_merge($builtin_excludes, $user_excludes);

        $handle_lower = strtolower($handle);
        $src_lower    = strtolower($src);

        foreach ($all_excludes as $ex) {
            $ex = strtolower(trim($ex));
            if ($ex === '') continue;
            if (strpos($handle_lower, $ex) !== false) return $tag;
            if (strpos($src_lower,   $ex) !== false) return $tag;
        }

        // Inject defer — replace the first <script occurrence to handle both
        // <script and <script type="text/javascript".
        return str_replace('<script ', '<script defer ', $tag);
    }

    // =========================================================================
    // HTML / CSS / JS Minification
    // =========================================================================

    public function minify_start(): void {
        ob_start([$this, 'minify_html_output']);
    }

    public function minify_end(): void {
        if (ob_get_level() > 0) ob_end_flush();
    }

    public function minify_html_output(string $html): string {
        if (trim($html) === '') return $html;
        if (stripos($html, '<html') === false) return $html;

        $placeholders = [];
        $index        = 0;

        // Protect <pre> blocks
        $html = preg_replace_callback('#<pre(\s[^>]*)?>.*?</pre>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // Protect <textarea> blocks
        $html = preg_replace_callback('#<textarea(\s[^>]*)?>.*?</textarea>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // Extract, minify, protect <script> blocks
        $html = preg_replace_callback('#<script(\s[^>]*)?>.*?</script>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $this->minify_js_block($m[0]);
            return $key;
        }, $html);

        // Extract, minify, protect <style> blocks
        $html = preg_replace_callback('#<style(\s[^>]*)?>.*?</style>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $this->minify_css_block($m[0]);
            return $key;
        }, $html);

        // Remove HTML comments (keep IE conditionals and placeholders)
        $html = preg_replace('#<!--(?!\[if|\s*MINIFY_PH).*?-->#is', '', $html);

        // Collapse whitespace between tags
        $html = preg_replace('/>\s+</s', '> <', $html);

        // Remove leading/trailing whitespace per line
        $html = preg_replace('/^[ \t]+|[ \t]+$/m', '', $html);

        // Collapse multiple blank lines
        $html = preg_replace('/\n{2,}/', "\n", $html);

        // Restore protected blocks
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);

        return trim($html);
    }

    private function minify_js_block(string $block): string {
        if (!preg_match('#(<script[^>]*>)(.*?)(</script>)#is', $block, $m)) return $block;
        $open    = $m[1];
        $content = $m[2];
        $close   = $m[3];
        if (trim($content) === '') return $block;
        // Skip JSON-LD structured data
        if (stripos($open, 'application/ld+json') !== false) return $block;
        return $open . $this->minify_js_content($content) . $close;
    }

    private function minify_css_block(string $block): string {
        if (!preg_match('#(<style[^>]*>)(.*?)(</style>)#is', $block, $m)) return $block;
        $open    = $m[1];
        $content = $m[2];
        $close   = $m[3];
        if (trim($content) === '') return $block;
        return $open . $this->minify_css_content($content) . $close;
    }

    private function minify_css_content(string $css): string {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }

    private function minify_js_content(string $js): string {
        $js = preg_replace('#(?<!:)//(?!["\']).*$#m', '', $js);
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('/[ \t]+/', ' ', $js);
        $js = preg_replace('/^\s*$/m', '', $js);
        $js = preg_replace('/\n{2,}/', "\n", $js);
        return trim($js);
    }

    private function build_seo_block(): string {
        $out = "\n<!-- CloudScale SEO AI Optimizer " . self::VERSION . " -->\n";

        $canonical = $this->canonical_url();
        if ($canonical) $out .= '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";

        $desc = $this->meta_desc();
        if ($desc) $out .= '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";

        $pname = trim((string) $this->opts['person_name']);
        if ($pname) $out .= '<meta name="author" content="' . esc_attr($pname) . '">' . "\n";

        $robots = $this->robots();
        if ($robots) $out .= '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";

        if ((int) $this->opts['enable_og']) {
            $out .= $this->render_og_tags();
        }

        $out .= $this->render_schemas();
        $out .= "<!-- /CloudScale SEO AI Optimizer -->\n";
        return $out;
    }

    // =========================================================================
    // Canonical / URL helpers
    // =========================================================================

    private function canonical_url(): string {
        if (is_singular()) return $this->clean_url((string) get_permalink((int) get_queried_object_id()));
        if (is_front_page() || is_home()) return $this->clean_url(home_url('/'));
        if (is_archive()) return $this->clean_url((string) get_pagenum_link(max(1, (int) get_query_var('paged'))));
        return '';
    }

    private function clean_url(string $url): string {
        if (!(int) $this->opts['strip_tracking_params']) return $url;
        $p = wp_parse_url($url);
        if (!$p) return $url;
        $scheme = $p['scheme'] ?? 'https';
        $host   = $p['host']   ?? '';
        $path   = $p['path']   ?? '/';
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $qs     = '';
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
            foreach (array_keys($q) as $k) {
                $kl = strtolower((string) $k);
                if (
                    strpos($kl, 'utm_') === 0 ||
                    strpos($kl, 'prp_page_') === 0 ||
                    in_array($kl, ['fbclid','gclid','msclkid'], true)
                ) unset($q[$k]);
            }
            if ($q) $qs = '?' . http_build_query($q);
        }
        return $scheme . '://' . $host . $port . $path . $qs;
    }

    // =========================================================================
    // Meta description
    // =========================================================================

    private function meta_desc(): string {
        if (is_front_page() || is_home()) {
            $h = trim((string) $this->opts['home_desc']);
            if ($h) return $this->clip($h, 160);
        }
        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_DESC, true));
            if ($custom) return $this->clip($custom, 160);
            $post = get_post($pid);
            if ($post) {
                if (!empty($post->post_excerpt)) {
                    return $this->clip($this->text_from_html((string) $post->post_excerpt), 160);
                }
                return $this->clip($this->text_from_html((string) $post->post_content), 160);
            }
        }
        $d = trim((string) $this->opts['default_desc']);
        return $d ? $this->clip($d, 160) : '';
    }

    private function text_from_html(string $raw): string {
        $raw = strip_shortcodes($raw);
        $raw = wp_strip_all_tags($raw);
        return (string) preg_replace('/\s+/', ' ', $raw);
    }

    // =========================================================================
    // Robots
    // =========================================================================

    private function robots(): string {
        if ((int) $this->opts['noindex_search']          && is_search())     return 'noindex,follow';
        if ((int) $this->opts['noindex_404']             && is_404())        return 'noindex,follow';
        if ((int) $this->opts['noindex_attachment']      && is_attachment()) return 'noindex,follow';
        if ((int) $this->opts['noindex_author_archives'] && is_author())     return 'noindex,follow';
        if ((int) $this->opts['noindex_tag_archives']    && is_tag())        return 'noindex,follow';
        return '';
    }

    private function is_noindexed(): bool {
        return $this->robots() !== '';
    }

    // =========================================================================
    // OG image size registration
    // =========================================================================

    /**
     * Register a 1200×630 hard-cropped image size for OG tags.
     * This matches the aspect ratio required by WhatsApp, Facebook, and LinkedIn
     * for reliable thumbnail display in link previews.
     */
    public function register_og_image_size(): void {
        add_image_size('cs_seo_og_image', 1200, 630, true);
    }

    // =========================================================================
    // OG image
    // =========================================================================

    private function og_image_data(): array {
        $url = ''; $width = 0; $height = 0; $type = ''; $alt = '';

        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_OGIMG, true));
            if ($custom) {
                $url = $custom;
                $att_id = attachment_url_to_postid($custom);
                if ($att_id) {
                    $meta = wp_get_attachment_metadata($att_id);
                    if (!empty($meta['width']))  $width  = (int) $meta['width'];
                    if (!empty($meta['height'])) $height = (int) $meta['height'];
                    $type = get_post_mime_type($att_id) ?: '';
                    $alt  = trim((string) get_post_meta($att_id, '_wp_attachment_image_alt', true));
                }
            } elseif (has_post_thumbnail($pid)) {
                $thumb_id = (int) get_post_thumbnail_id($pid);
                // Prefer the 1200×630 OG crop — WhatsApp requires ~1.91:1 aspect ratio.
                // If the crop does not exist (e.g. portrait source image that is too narrow),
                // generate a letterboxed 1200×630 JPEG with white padding and cache it.
                $src = wp_get_attachment_image_src($thumb_id, 'cs_seo_og_image');
                if (empty($src[0]) || (isset($src[1]) && (int)$src[1] !== 1200)) {
                    $letterbox_url = $this->generate_og_letterbox($thumb_id);
                    if ($letterbox_url) {
                        $src = [$letterbox_url, 1200, 630, false];
                    } else {
                        $src = wp_get_attachment_image_src($thumb_id, 'full');
                    }
                }
                if (!empty($src[0])) {
                    $url    = (string) $src[0];
                    $width  = isset($src[1]) ? (int) $src[1] : 0;
                    $height = isset($src[2]) ? (int) $src[2] : 0;
                    $type   = get_post_mime_type($thumb_id) ?: '';
                    $alt    = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
                }
            }
        }

        if (!$url) {
            $url = trim((string) $this->opts['default_og_image']);
            if ($url) {
                $att_id = attachment_url_to_postid($url);
                if ($att_id) {
                    $meta = wp_get_attachment_metadata($att_id);
                    if (!empty($meta['width']))  $width  = (int) $meta['width'];
                    if (!empty($meta['height'])) $height = (int) $meta['height'];
                    $type = get_post_mime_type($att_id) ?: '';
                    $alt  = trim((string) get_post_meta($att_id, '_wp_attachment_image_alt', true));
                }
            }
        }

        return compact('url', 'width', 'height', 'type', 'alt');
    }

    // =========================================================================
    // OG letterbox generator
    // =========================================================================

    /**
     * Generate a 1200×630 letterboxed JPEG for a featured image that is too narrow
     * to be hard-cropped to 1200×630 (e.g. portrait or square images).
     *
     * The source image is scaled to fit within 1200×630 while preserving its aspect
     * ratio, then centred on a white 1200×630 canvas. The result is saved alongside
     * the original upload and the URL is cached in post meta so the GD work only
     * runs once per attachment.
     *
     * @param int $attachment_id WordPress attachment ID.
     * @return string|false URL of the letterboxed image, or false on failure.
     */
    private function generate_og_letterbox(int $attachment_id): string|false {
        // Return cached result if already generated.
        $cached = get_post_meta($attachment_id, '_cs_seo_og_letterbox_url', true);
        if ($cached) return $cached;

        // GD is required.
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return false;

        $mime = mime_content_type($file) ?: '';
        $src_img = match(true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($file),
            str_contains($mime, 'png')                                 => @imagecreatefrompng($file),
            str_contains($mime, 'webp')                                => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            str_contains($mime, 'gif')                                 => @imagecreatefromgif($file),
            default                                                    => false,
        };

        if (!$src_img) return false;

        $src_w = imagesx($src_img);
        $src_h = imagesy($src_img);

        $canvas_w = 1200;
        $canvas_h = 630;

        // Scale source to fit inside the canvas, preserving aspect ratio.
        $scale  = min($canvas_w / $src_w, $canvas_h / $src_h);
        $dst_w  = (int) round($src_w * $scale);
        $dst_h  = (int) round($src_h * $scale);
        $dst_x  = (int) round(($canvas_w - $dst_w) / 2);
        $dst_y  = (int) round(($canvas_h - $dst_h) / 2);

        // Create white canvas.
        $canvas = imagecreatetruecolor($canvas_w, $canvas_h);
        if (!$canvas) { imagedestroy($src_img); return false; }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        // Copy scaled source onto canvas.
        imagecopyresampled($canvas, $src_img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
        imagedestroy($src_img);

        // Save next to original file using a -og1200x630 suffix.
        $dir       = dirname($file);
        $base      = pathinfo($file, PATHINFO_FILENAME);
        $out_file  = $dir . '/' . $base . '-og1200x630.jpg';
        $saved     = imagejpeg($canvas, $out_file, 88);
        imagedestroy($canvas);

        if (!$saved) return false;

        // Build URL from file path.
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']);
        $base_url   = trailingslashit($upload_dir['baseurl']);
        if (str_starts_with($out_file, $base_dir)) {
            $relative = substr($out_file, strlen($base_dir));
            $url      = $base_url . $relative;
        } else {
            return false;
        }

        // Cache so we do not re-generate on every page load.
        update_post_meta($attachment_id, '_cs_seo_og_letterbox_url', $url);

        self::debug_log("CS SEO: Generated OG letterbox for attachment {$attachment_id}: {$url}");

        return $url;
    }

    // =========================================================================
    // OG / Twitter
    // =========================================================================

    private function render_og_tags(): string {
        $title   = $this->page_title();
        $desc    = $this->meta_desc();
        $url     = $this->canonical_url() ?: home_url('/');
        $site    = (string) $this->opts['site_name'];
        $locale  = str_replace('-', '_', (string) $this->opts['site_lang']);
        $twitter = (string) $this->opts['twitter_handle'];
        $type    = is_singular('post') ? 'article' : 'website';
        $img     = $this->og_image_data();
        $out     = '';

        $og = [
            'og:locale'      => $locale,
            'og:type'        => $type,
            'og:title'       => $title,
            'og:description' => $desc,
            'og:url'         => $url,
            'og:site_name'   => $site,
        ];

        if ($type === 'article' && is_singular()) {
            $pid = (int) get_queried_object_id();
            $published = get_post_time('c', true, $pid);
            $modified  = get_post_modified_time('c', true, $pid);
            if ($published) $og['article:published_time'] = $published;
            if ($modified)  $og['article:modified_time']  = $modified;
            $og['article:author'] = (string) $this->opts['person_url'];
            $cats = get_the_category($pid);
            if (!empty($cats[0])) $og['article:section'] = $cats[0]->name;
        }

        foreach ($og as $k => $v) {
            if ((string)$v === '') continue;
            $out .= '<meta property="' . esc_attr($k) . '" content="' . esc_attr((string)$v) . '">' . "\n";
        }

        if ($img['url']) {
            $out .= '<meta property="og:image" content="'        . esc_attr($img['url'])            . '">' . "\n";
            // og:image:secure_url is required by WhatsApp's scraper for HTTPS pages to reliably show link preview thumbnails.
            if (str_starts_with($img['url'], 'https://')) {
                $out .= '<meta property="og:image:secure_url" content="' . esc_attr($img['url']) . '">' . "\n";
            }
            if ($img['width'])  $out .= '<meta property="og:image:width" content="'  . esc_attr((string)$img['width'])  . '">' . "\n";
            if ($img['height']) $out .= '<meta property="og:image:height" content="' . esc_attr((string)$img['height']) . '">' . "\n";
            if ($img['type'])   $out .= '<meta property="og:image:type" content="'   . esc_attr($img['type'])           . '">' . "\n";
            if ($img['alt'])    $out .= '<meta property="og:image:alt" content="'    . esc_attr($img['alt'])            . '">' . "\n";
        }

        $out .= '<meta name="twitter:card" content="'        . esc_attr($img['url'] ? 'summary_large_image' : 'summary') . '">' . "\n";
        $out .= '<meta name="twitter:title" content="'       . esc_attr($title)      . '">' . "\n";
        if ($desc)       $out .= '<meta name="twitter:description" content="' . esc_attr($desc)      . '">' . "\n";
        if ($img['url']) $out .= '<meta name="twitter:image" content="'       . esc_attr($img['url']) . '">' . "\n";
        if ($img['alt']) $out .= '<meta name="twitter:image:alt" content="'   . esc_attr($img['alt']) . '">' . "\n";
        if ($twitter)    $out .= '<meta name="twitter:site" content="'        . esc_attr($twitter)    . '">' . "\n";
        if ($twitter)    $out .= '<meta name="twitter:creator" content="'     . esc_attr($twitter)    . '">' . "\n";

        return $out;
    }

    // =========================================================================
    // Schemas
    // =========================================================================

    // =========================================================================
    // AI Summary Box — front end render
    // =========================================================================

    /**
     * Prepends the AI summary box to singular post content when all 3 fields are
     * populated and the show_summary_box option is enabled.
     */
    public function prepend_summary_box(string $content): string {
        if (!is_singular('post') || is_admin() || !(int)($this->opts['show_summary_box'] ?? 1)) {
            return $content;
        }
        // Only run on the main query to avoid duplicating in widgets or shortcodes.
        if (!in_the_loop() || !is_main_query()) return $content;

        $pid      = (int) get_the_ID();
        $sum_what = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        $sum_why  = trim((string) get_post_meta($pid, self::META_SUM_WHY,  true));
        $sum_key  = trim((string) get_post_meta($pid, self::META_SUM_KEY,  true));

        if (!$sum_what || !$sum_why || !$sum_key) return $content;

        $box  = '<div class="cs-seo-summary-box" style="';
        $box .= 'background:#f8f9fa;border:1px solid #e2e8f0;border-left:4px solid #4f46e5;';
        $box .= 'border-radius:6px;padding:20px 24px;margin:0 0 28px;font-size:15px;line-height:1.6;';
        $box .= '">';
        $box .= '<p style="margin:0 0 14px;font-size:11px;font-weight:700;letter-spacing:.08em;';
        $box .= 'text-transform:uppercase;color:#6b7280;">In this article</p>';
        $box .= '<table style="width:100%;border-collapse:collapse;">';
        $box .= '<tr><td style="padding:6px 0 6px;vertical-align:top;width:130px;font-weight:700;font-size:13px;color:#374151;">What it is</td>';
        $box .= '<td style="padding:6px 0 6px;color:#1f2937;">' . esc_html($sum_what) . '</td></tr>';
        $box .= '<tr><td style="padding:6px 0 6px;vertical-align:top;font-weight:700;font-size:13px;color:#374151;">Why it matters</td>';
        $box .= '<td style="padding:6px 0 6px;color:#1f2937;">' . esc_html($sum_why) . '</td></tr>';
        $box .= '<tr><td style="padding:6px 0 6px;vertical-align:top;font-weight:700;font-size:13px;color:#374151;">Key takeaway</td>';
        $box .= '<td style="padding:6px 0 6px;color:#1f2937;">' . esc_html($sum_key) . '</td></tr>';
        $box .= '</table>';
        $box .= '</div>';

        return $box . $content;
    }

    private function render_schemas(): string {
        $out     = '';
        $noindex = $this->is_noindexed();

        if ((int) $this->opts['enable_schema_website'] && (is_front_page() || is_home())) {
            $out .= $this->schema_tag($this->schema_website());
        }
        if ((int) $this->opts['enable_schema_person'] && !$noindex) {
            $out .= $this->schema_tag($this->schema_person());
        }
        if ((int) $this->opts['enable_schema_breadcrumbs'] && !$noindex) {
            $bc = $this->schema_breadcrumbs();
            if ($bc) $out .= $this->schema_tag($bc);
        }
        if ((int) $this->opts['enable_schema_article'] && is_singular('post') && !$noindex) {
            $art = $this->schema_article();
            if ($art) $out .= $this->schema_tag($art);
        }
        return $out;
    }

    private function schema_tag(array $schema): string {
        return '<script type="application/ld+json">'
            . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            . "</script>\n";
    }

    private function schema_website(): array {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => (string) $this->opts['site_name'],
            'url'             => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => home_url('/?s={search_term_string}')],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function schema_person(): array {
        $sameAs = array_values(array_filter(
            array_map('trim', (array) preg_split('/\r\n|\r|\n/', (string) $this->opts['sameas']))
        ));
        $s = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => (string) $this->opts['person_name'],
            'jobTitle' => (string) $this->opts['person_job_title'],
            'url'      => (string) $this->opts['person_url'],
        ];
        if ($sameAs) $s['sameAs'] = $sameAs;
        $img = trim((string) $this->opts['person_image']);
        if ($img) $s['image'] = $img;
        return $s;
    }

    private function schema_article(): ?array {
        if (!is_singular()) return null;
        $pid  = (int) get_queried_object_id();
        $post = get_post($pid);
        if (!$post) return null;

        $img        = $this->og_image_data();
        $cats       = get_the_category($pid);
        $tags       = wp_get_post_tags($pid, ['fields' => 'names']);
        $word_count = str_word_count(wp_strip_all_tags((string) $post->post_content));
        $mins       = max(1, (int) ceil($word_count / 200));
        $published  = get_post_time('c', true, $pid);
        $modified   = get_post_modified_time('c', true, $pid);

        $s = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $this->canonical_url()],
            'headline'         => get_the_title($pid),
            'description'      => $this->meta_desc(),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', (int) $post->post_author) ?: (string) $this->opts['person_name'],
                'url'   => (string) $this->opts['person_url'],
            ],
            'publisher' => [
                '@type' => 'Person',
                'name'  => (string) $this->opts['person_name'],
                'url'   => (string) $this->opts['person_url'],
            ],
            'wordCount'    => $word_count,
            'timeRequired' => 'PT' . $mins . 'M',
        ];

        if ($published) $s['datePublished'] = $published;
        if ($modified)  $s['dateModified']  = $modified;

        if ($img['url']) {
            $image = ['@type' => 'ImageObject', 'url' => $img['url']];
            if ($img['width'])  $image['width']  = $img['width'];
            if ($img['height']) $image['height'] = $img['height'];
            $s['image'] = [$image];
        }

        $pimg = trim((string) $this->opts['person_image']);
        if ($pimg) $s['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => $pimg];

        if (!empty($cats[0])) $s['articleSection'] = $cats[0]->name;
        if (!empty($tags))    $s['keywords']       = implode(', ', $tags);

        // Enrich description with the AI summary 'what' field if available.
        $sum_what = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        if ($sum_what) $s['description'] = $sum_what;

        return $s;
    }

    private function schema_breadcrumbs(): ?array {
        $items = [];
        $pos   = 1;
        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => 'Home', 'item' => home_url('/')];

        if (is_singular('post')) {
            $pid  = (int) get_queried_object_id();
            $cats = get_the_category($pid);
            if (!empty($cats[0])) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $cats[0]->name, 'item' => get_category_link($cats[0]->term_id)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($pid), 'item' => get_permalink($pid)];
        } elseif (is_page()) {
            $pid = (int) get_queried_object_id();
            foreach (array_reverse(get_post_ancestors($pid)) as $anc) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($anc), 'item' => get_permalink($anc)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($pid), 'item' => get_permalink($pid)];
        } elseif (is_category() || is_tag() || is_author()) {
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $this->page_title(), 'item' => $this->canonical_url()];
        } else {
            return null;
        }

        if (count($items) <= 1) return null;
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function page_title(): string {
        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_TITLE, true));
            if ($custom !== '') return $custom;
            return get_the_title($pid);
        }
        if (is_front_page() || is_home()) {
            $t = trim((string) $this->opts['home_title']);
            return $t ?: (string) $this->opts['site_name'];
        }
        return wp_get_document_title();
    }

    private function clip(string $s, int $max): string {
        $s = trim((string) preg_replace('/\s+/', ' ', $s));
        if ($s === '' || mb_strlen($s) <= $max) return $s;
        return rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }

    // =========================================================================
    // Metabox
    // =========================================================================

    public function add_metabox(): void {
        foreach (['post', 'page'] as $pt) {
            add_meta_box('cs_seo_adv', 'CloudScale Meta Boxes', [$this, 'render_metabox'], $pt, 'normal', 'high');
        }
    }

    public function render_metabox(WP_Post $post): void {
        wp_nonce_field('cs_seo_save', 'cs_seo_nonce');
        $title   = (string) get_post_meta($post->ID, self::META_TITLE,    true);
        $desc    = (string) get_post_meta($post->ID, self::META_DESC,     true);
        $ogimg   = (string) get_post_meta($post->ID, self::META_OGIMG,    true);
        $sum_what = (string) get_post_meta($post->ID, self::META_SUM_WHAT, true);
        $sum_why  = (string) get_post_meta($post->ID, self::META_SUM_WHY,  true);
        $sum_key  = (string) get_post_meta($post->ID, self::META_SUM_KEY,  true);
        $has_key = !empty($this->ai_opts['anthropic_key']) || !empty($this->ai_opts['gemini_key']);
        ?>
        <p><strong>Custom SEO title</strong> — leave blank to auto-generate<br>
            <input class="widefat" name="cs_seo_title" value="<?php echo esc_attr($title); ?>"></p>
        <p>
            <strong>Meta description</strong> — leave blank to use excerpt / post content<br>
            <textarea class="widefat" rows="3" name="cs_seo_desc" id="cs_seo_desc_<?php echo (int) $post->ID; ?>"><?php echo esc_textarea($desc); ?></textarea>
            <span id="cs_seo_char_<?php echo (int) $post->ID; ?>" style="font-size:11px;color:#888;">
                <?php echo $desc ? esc_html( (string) mb_strlen($desc) ) . ' chars' : 'No description set'; ?>
            </span>
        </p>
        <?php if ($has_key): ?>
        <p>
            <button type="button" class="button" id="cs_seo_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoGenOne(<?php echo (int) $post->ID; ?>)">
                ✦ Generate with Claude
            </button>
            <span id="cs_seo_gen_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <script>
        function csSeoGenOne(postId) {
            const btn    = document.getElementById('cs_seo_gen_' + postId);
            const status = document.getElementById('cs_seo_gen_status_' + postId);
            const field  = document.getElementById('cs_seo_desc_' + postId);
            const chars  = document.getElementById('cs_seo_char_' + postId);
            btn.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_ai_generate_one',
                    post_id: postId,
                    nonce: '<?php echo esc_js( wp_create_nonce('cs_seo_nonce') ); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    field.value = data.data.description;
                    chars.textContent = data.data.chars + ' chars';
                    chars.style.color = data.data.chars >= 140 && data.data.chars <= 160 ? '#46b450' : '#dc3232';
                    status.textContent = '✓ Done — save post to keep';
                    status.style.color = '#46b450';
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; });
        }
        </script>
        <?php else: ?>
        <p style="color:#888;font-size:12px;"><em>Add an Anthropic API key in <a href="<?php echo esc_url( admin_url('options-general.php?page=cs-seo-optimizer#ai') ); ?>">SEO Settings → AI Meta Writer</a> to enable per-post generation.</em></p>
        <?php endif; ?>
        <?php
        $thumb_id  = get_post_thumbnail_id($post->ID);
        $thumb_src = $thumb_id ? wp_get_attachment_image_src($thumb_id, 'thumbnail') : false;
        $using_custom = !empty($ogimg);
        ?>
        <p>
            <strong>OG image URL</strong> — leave blank to use featured image<br>
            <input class="widefat" name="cs_seo_ogimg" id="cs_seo_ogimg_<?php echo (int) $post->ID; ?>" value="<?php echo esc_attr($ogimg); ?>">
            <?php if ($using_custom): ?>
            <button type="button" class="button" style="margin-top:4px" onclick="
                document.getElementById('cs_seo_ogimg_<?php echo (int) $post->ID; ?>').value = '';
                this.parentNode.querySelector('.cs-og-status').textContent = '⚠ Cleared — save post to apply';
                this.parentNode.querySelector('.cs-og-status').style.color = '#e67e00';
                this.style.display = 'none';
            ">✕ Clear (use featured image)</button>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#c3372b;margin-top:3px">⚠ Custom URL set — featured image changes will not appear until this is cleared</span>
            <?php elseif ($thumb_src): ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#1a7a34;margin-top:3px">✓ Using featured image</span>
            <?php else: ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#888;margin-top:3px">No featured image set — using site default OG image</span>
            <?php endif; ?>
        </p>

        <hr style="margin:16px 0;border:none;border-top:1px solid #ddd">
        <p style="margin:0 0 8px"><strong>AI Summary Box</strong> <span style="font-size:11px;font-weight:400;color:#888">— shown at the top of the post for readers and AI search engines</span></p>

        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555">What it is</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_what" id="cs_seo_sum_what_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_what); ?></textarea>
        </p>
        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555">Why it matters</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_why" id="cs_seo_sum_why_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_why); ?></textarea>
        </p>
        <p style="margin:0 0 10px">
            <label style="font-size:12px;font-weight:600;color:#555">Key takeaway</label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_key" id="cs_seo_sum_key_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_key); ?></textarea>
        </p>

        <?php if ($has_key): ?>
        <p style="margin:0">
            <button type="button" class="button" id="cs_seo_sum_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>)">
                ✦ Generate Summary
            </button>
            <button type="button" class="button" style="margin-left:6px" id="cs_seo_sum_regen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>, true)">
                ↺ Regenerate
            </button>
            <span id="cs_seo_sum_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <script>
        function csSeoSumGenOne(postId, force) {
            const btn    = document.getElementById('cs_seo_sum_gen_' + postId);
            const regen  = document.getElementById('cs_seo_sum_regen_' + postId);
            const status = document.getElementById('cs_seo_sum_status_' + postId);
            const fWhat  = document.getElementById('cs_seo_sum_what_' + postId);
            const fWhy   = document.getElementById('cs_seo_sum_why_' + postId);
            const fKey   = document.getElementById('cs_seo_sum_key_' + postId);
            btn.disabled = true;
            regen.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_summary_generate_one',
                    post_id: postId,
                    force: force ? 1 : 0,
                    nonce: '<?php echo esc_js( wp_create_nonce('cs_seo_nonce') ); ?>'
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.data.skipped) {
                        status.textContent = '✓ Already generated — use Regenerate to overwrite';
                        status.style.color = '#888';
                    } else {
                        fWhat.value = data.data.what;
                        fWhy.value  = data.data.why;
                        fKey.value  = data.data.takeaway;
                        status.textContent = '✓ Done — save post to keep';
                        status.style.color = '#46b450';
                    }
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; regen.disabled = false; });
        }
        </script>
        <?php endif; ?>

        <?php
    }

    public function save_metabox(int $post_id, WP_Post $post): void {
        if (!isset($_POST['cs_seo_nonce'])) return;
        if (!wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cs_seo_nonce'] ) ), 'cs_seo_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $this->set_meta($post_id, self::META_TITLE,    sanitize_text_field( wp_unslash( (string) ($_POST['cs_seo_title'] ?? '') ) ));
        $this->set_meta($post_id, self::META_DESC,     sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_desc'] ?? '') ) ));
        $this->set_meta($post_id, self::META_OGIMG,    esc_url_raw( wp_unslash( (string) ($_POST['cs_seo_ogimg'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHAT, sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_what'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_why']  ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_KEY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_key']  ?? '') ) ));
    }

    private function set_meta(int $id, string $key, string $val): void {
        $val === '' ? delete_post_meta($id, $key) : update_post_meta($id, $key, $val);
    }

    /**
     * When the featured image (_thumbnail_id) is changed, clear our custom OG image
     * so og_image_data() falls through to the new featured image automatically.
     */
    public function on_thumbnail_updated(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        if ($meta_key !== '_thumbnail_id') return;
        // Only clear if our custom OG image field is set — if it's empty, nothing to do.
        $custom = get_post_meta($post_id, self::META_OGIMG, true);
        if ($custom) {
            delete_post_meta($post_id, self::META_OGIMG);
        }
    }

    // =========================================================================
    // AJAX handlers
    // =========================================================================
    // Scheduled batch
    // =========================================================================

    /**
     * WP Cron callback — fires daily. Checks if today is a scheduled day, then:
     *   Pass 1 — generates meta descriptions for posts that don't have one yet.
     *   Pass 2 — generates ALT text for images that are missing it across all posts.
     * Both passes are missing-only: existing data is never overwritten.
     * Works with both Anthropic and Gemini via dispatch_ai().
     */
    public function run_scheduled_batch(): void {
        $ai = $this->get_ai_opts();
        if (!(int) $ai['schedule_enabled']) return;

        $days = (array) $ai['schedule_days'];
        if (empty($days)) return;

        // Check if today (server time) is a scheduled day.
        $today = strtolower(gmdate('D')); // 'mon','tue' etc.
        if (!in_array($today, $days, true)) return;

        $log     = [];
        $done    = 0;
        $errors  = 0;
        $skipped = 0;
        $start   = time();

        $q = new WP_Query([
            'post_type'           => ['post', 'page'],
            'post_status'         => 'publish',
            'posts_per_page'      => 500,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
        ]);

        // ── Pass 1: generate missing meta descriptions ────────────────────────
        foreach ($q->posts as $p) {
            $existing = trim((string) get_post_meta($p->ID, self::META_DESC, true));
            if ($existing) {
                $skipped++;
                continue; // Already has a description — skip.
            }
            try {
                $desc = $this->call_ai_generate_desc($p->ID);
                update_post_meta($p->ID, self::META_DESC, sanitize_textarea_field($desc));
                $log[] = ['status' => 'ok', 'title' => get_the_title($p->ID), 'chars' => mb_strlen($desc)];
                $done++;
            } catch (\Throwable $e) {
                $log[] = ['status' => 'err', 'title' => get_the_title($p->ID), 'message' => $e->getMessage()];
                $errors++;
                sleep(2); // Back off on error.
            }
            sleep(1); // Pace requests — T4g Micro friendly.
        }

        // ── Pass 2: generate missing ALT text across all posts ────────────────
        // Re-query all posts — Pass 1 skipped posts with existing descriptions,
        // but those posts may still have images with missing ALT text.
        $alt_done          = 0;
        $alt_errors        = 0;
        $alt_skipped_posts = 0;

        $q2 = new WP_Query([
            'post_type'           => ['post', 'page'],
            'post_status'         => 'publish',
            'posts_per_page'      => 500,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
        ]);

        foreach ($q2->posts as $p) {
            $images = $this->collect_images_needing_alt($p->ID);
            if (empty($images)) {
                $alt_skipped_posts++;
                continue; // All images already have ALT text — nothing to do.
            }
            try {
                $saved = $this->batch_generate_alt_for_post($p->ID, $images);
                $alt_done += $saved;
            } catch (\Throwable $e) {
                $log[] = ['status' => 'alt_err', 'title' => get_the_title($p->ID), 'message' => $e->getMessage()];
                $alt_errors++;
                sleep(2);
            }
            sleep(1); // Pace requests — T4g Micro friendly.
        }

        // ── Pass 3: generate missing AI summaries across all posts ──────────
        $sum_done   = 0;
        $sum_errors = 0;

        $q3 = new WP_Query([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 500,
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'meta_query'          => [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- NOT EXISTS has no alternative
                'key'     => self::META_SUM_WHAT,
                'compare' => 'NOT EXISTS',
            ]],
        ]);

        foreach ($q3->posts as $p) {
            try {
                $summary = $this->call_ai_generate_summary($p->ID);
                update_post_meta($p->ID, self::META_SUM_WHAT, $summary['what']);
                update_post_meta($p->ID, self::META_SUM_WHY,  $summary['why']);
                update_post_meta($p->ID, self::META_SUM_KEY,  $summary['takeaway']);
                $log[] = ['status' => 'sum_ok', 'title' => get_the_title($p->ID)];
                $sum_done++;
            } catch (\Throwable $e) {
                $log[] = ['status' => 'sum_err', 'title' => get_the_title($p->ID), 'message' => $e->getMessage()];
                $sum_errors++;
                sleep(2);
            }
            sleep(1); // Pace requests — T4g Micro friendly.
        }

        // Append to batch history (keep 28 days of runs).
        $history = get_option('cs_seo_batch_history', []);
        if (!is_array($history)) $history = [];
        $history[] = [
            'date'              => gmdate('Y-m-d H:i:s'),
            'day'               => ucfirst($today),
            'done'              => $done,
            'skipped'           => $skipped,
            'errors'            => $errors,
            'alt_done'          => $alt_done,
            'alt_skipped_posts' => $alt_skipped_posts,
            'alt_errors'        => $alt_errors,
            'sum_done'          => $sum_done,
            'sum_errors'        => $sum_errors,
            'elapsed'           => round((time() - $start) / 60, 1),
            'log'               => array_slice($log, 0, 100), // Keep last 100 entries per run.
        ];
        // Prune entries older than 28 days.
        $cutoff  = gmdate('Y-m-d H:i:s', strtotime('-28 days'));
        $history = array_values(array_filter($history, fn($r) => ($r['date'] ?? '') >= $cutoff));
        update_option('cs_seo_batch_history', $history, false);
    }

    /**
     * Generate ALT text for images that are missing it in a single post.
     * Used by run_scheduled_batch (Pass 2). Missing-only — never overwrites existing ALT.
     * Works with both Anthropic and Gemini via dispatch_ai().
     *
     * @param int   $post_id Post to process.
     * @param array $images  Output of collect_images_needing_alt() — pass it in to avoid
     *                       a duplicate call when the caller has already run the scan.
     * @return int  Number of ALT texts saved.
     */
    private function batch_generate_alt_for_post(int $post_id, array $images): int {
        $post = get_post($post_id);
        if (!$post || empty($images)) return 0;

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        if (!$key) return 0;

        // Use a lighter model for ALT text — same as the manual ALT generator.
        $model = trim((string) $this->ai_opts['model'])
            ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001');

        $title         = get_the_title($post_id);
        $excerpt_limit = max(100, min(2000, (int)($this->ai_opts['alt_excerpt_chars'] ?? 600)));
        $article_text  = wp_strip_all_tags((string) $post->post_content);
        $article_text  = (string) preg_replace('/\s+/', ' ', $article_text);
        $article_text  = trim($article_text);
        if (mb_strlen($article_text) > $excerpt_limit) {
            $article_text = mb_substr($article_text, 0, $excerpt_limit) . '…';
        }

        $new_content     = (string) $post->post_content;
        $content_changed = false;
        $saved           = 0;
        $min_words       = 5;
        $max_words       = 15;

        $system = 'You write concise, descriptive image alt text for blog post images. '
            . 'Alt text should describe what the image shows in 5–15 words, relevant to the post context. '
            . 'Do not start with "Image of" or "Photo of". Output ONLY the alt text, nothing else.';

        foreach ($images as $img) {
            $filename = $img['filename'] ?? '';
            $user_msg = "Post title: \"{$title}\"\n"
                . "Article excerpt: \"{$article_text}\"\n"
                . "Image filename hint: \"{$filename}\"\n"
                . "Write appropriate alt text for this image.";

            $alt_text = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 80);
            $alt_text = trim(trim($alt_text, '"\''));
            if (!$alt_text) continue;

            // One retry if word count is out of range.
            $word_count = str_word_count($alt_text);
            if ($word_count < $min_words || $word_count > $max_words) {
                $retry_msg = "Your previous alt text was {$word_count} words: \"{$alt_text}\"\n"
                    . "Post title: \"{$title}\"\n"
                    . "Image filename hint: \"{$filename}\"\n"
                    . "Rewrite the alt text to be between {$min_words} and {$max_words} words. Output ONLY the alt text.";
                $retry = $this->dispatch_ai($provider, $key, $model, $system, $retry_msg, null, 80);
                $retry = trim(trim($retry, '"\''));
                if ($retry) $alt_text = $retry;
            }

            $alt_text = sanitize_text_field($alt_text);

            // Save to attachment meta if we have an ID.
            if (!empty($img['attach_id'])) {
                update_post_meta((int) $img['attach_id'], '_wp_attachment_image_alt', $alt_text);
            }

            // Replace alt="" in post content.
            $new_tag     = preg_replace('/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img['img_tag'], 1);
            $new_content = str_replace($img['img_tag'], $new_tag, $new_content);
            $content_changed = true;
            $saved++;

            sleep(1); // Pace per-image requests — T4g Micro friendly.
        }

        if ($content_changed) {
            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
        }

        return $saved;
    }

    /**
     * AJAX: return the last scheduled batch result for display in the UI.
     */
    public function ajax_get_batch_log(): void {
        $this->ajax_check();
        $history = get_option('cs_seo_batch_history', []);
        if (!empty($history) && is_array($history)) {
            wp_send_json_success($history);
        } else {
            // Migrate legacy single-run option if present.
            $legacy = get_option('cs_seo_last_batch', null);
            if ($legacy) {
                $history = [$legacy];
                update_option('cs_seo_batch_history', $history, false);
                delete_option('cs_seo_last_batch');
                wp_send_json_success($history);
            } else {
                wp_send_json_success(null);
            }
        }
    }

    // =========================================================================

    private function ajax_check(): void {
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        if (!check_ajax_referer('cs_seo_nonce', 'nonce', false)) wp_send_json_error('Bad nonce', 403);
    }

    /**
     * Central AI dispatcher — routes to Anthropic or Gemini and returns the response text.
     * $extra_messages: additional turns to append after the initial user_msg (for multi-turn correction).
     */
    private function dispatch_ai(string $provider, string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        if ($provider === 'gemini') {
            return $this->call_gemini($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
        }
        return $this->call_claude($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
    }

    /**
     * Make an Anthropic Claude API call, with 429 retry.
     */
    private function call_claude(string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        $messages = [['role' => 'user', 'content' => $user_msg]];
        if ($extra_messages) {
            $messages = array_merge($messages, $extra_messages);
        }
        $payload = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system,
            'messages'   => $messages,
        ];
        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode API request as JSON — post content may contain invalid UTF-8.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 45,
            'headers' => $headers,
            'body'    => $body,
        ]);
        if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429 || $code === 529) {
            $wait = $code === 529 ? 20 : 10;
            sleep($wait);
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 45, 'headers' => $headers, 'body' => $body,
            ]);
            if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error after retry: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in Anthropic response: ' . json_last_error_msg()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code !== 200) {
            $label = match($code) {
                429 => '429 - Rate Limited',
                529 => '529 - Service Overloaded',
                500 => '500 - Anthropic Server Error',
                401 => '401 - Invalid API Key',
                403 => '403 - Forbidden',
                default => "HTTP {$code}",
            };
            $detail = $body['error']['message'] ?? '';
            $msg    = "Response: {$label}" . ($detail ? " — {$detail}" : '');
            throw new \RuntimeException( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim($body['content'][0]['text'] ?? '');
    }

    /**
     * Make a Google Gemini API call.
     * Gemini uses a flat contents array with role:user/model alternating.
     * The system prompt is injected as the first user turn since Gemini
     * supports a systemInstruction field in the v1beta API.
     */
    private function call_gemini(string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        // Convert Anthropic-style messages to Gemini contents array.
        $contents = [
            ['role' => 'user', 'parts' => [['text' => $user_msg]]],
        ];
        if ($extra_messages) {
            foreach ($extra_messages as $m) {
                $gemini_role = $m['role'] === 'assistant' ? 'model' : 'user';
                $contents[]  = ['role' => $gemini_role, 'parts' => [['text' => $m['content']]]];
            }
        }
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $contents,
            'generationConfig'  => ['maxOutputTokens' => $max_tokens],
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode API request as JSON — post content may contain invalid UTF-8.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $response = wp_remote_post($url, [
            'timeout' => 45,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $body,
        ]);
        if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        // Gemini uses 429 for quota exceeded — retry once.
        if (wp_remote_retrieve_response_code($response) === 429) {
            sleep(10);
            $response = wp_remote_post($url, [
                'timeout' => 45, 'headers' => ['Content-Type' => 'application/json'], 'body' => $body,
            ]);
            if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error after retry: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in Gemini response: ' . json_last_error_msg()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code !== 200) {
            $label = match($code) {
                429 => '429 - Rate Limited',
                500 => '500 - Gemini Server Error',
                401 => '401 - Invalid API Key',
                403 => '403 - Forbidden',
                default => "HTTP {$code}",
            };
            $detail = $body['error']['message'] ?? '';
            $msg    = "Response: {$label}" . ($detail ? " — {$detail}" : '');
            throw new \RuntimeException( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    /**
     * Single AI call that generates meta description, fixes the SEO title, and
     * writes ALT text for any images — all in one request.
     *
     * Returns an array:
     *   description  string
     *   title        string|null   null = already in range, leave as-is
     *   title_was    string|null
     *   title_chars  int
     *   title_status 'in_range'|'fixed'|'fixed_imperfect'
     *   alts_saved   int
     */
    private function call_ai_generate_all(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-20250514');
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        // ── Build context ─────────────────────────────────────────────────────
        $content = $this->text_from_html((string) $post->post_content);
        $content = mb_substr($content, 0, 6000);

        $site_context_parts = [];
        $site_name = trim((string) $this->opts['site_name']);
        $person    = trim((string) $this->opts['person_name']);
        $job_title = trim((string) $this->opts['person_job_title']);
        $home_desc = trim((string) $this->opts['home_desc']);
        $def_desc  = trim((string) $this->opts['default_desc']);

        if ($site_name) $site_context_parts[] = "Site name: {$site_name}";
        if ($person)    $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic) $site_context_parts[] = "Site description: {$topic}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT:\n" . implode("\n", $site_context_parts)
            : '';

        // ── Current title ─────────────────────────────────────────────────────
        $custom_title  = trim((string) get_post_meta($post_id, self::META_TITLE, true));
        $current_title = $custom_title !== '' ? $custom_title : get_the_title($post_id);
        $title_len     = mb_strlen($current_title);
        $needs_title   = ($title_len < 50 || $title_len > 60);
        $title_direction = $title_len > 60 ? 'too long' : 'too short';

        // ── Images needing ALT ────────────────────────────────────────────────
        $images_needing_alt = $this->collect_images_needing_alt($post_id);
        $has_images         = !empty($images_needing_alt);

        // ── Build unified prompt ──────────────────────────────────────────────
        $json_shape = '{"description": "...", "title": "..."}';
        $title_instruction = '';
        if ($needs_title) {
            $title_instruction = "\n\nSEO TITLE: The current title is {$title_direction} at {$title_len} chars: \"{$current_title}\". "
                . "Rewrite it so it is between 50 and 60 characters. Keep the core topic and keywords. "
                . "Do not add quotes or punctuation at start/end.";
            $json_shape = '{"description": "...", "title": "..."}';
        } else {
            // Title is fine — still include it in the schema but echo it back unchanged.
            $title_instruction = "\n\nSEO TITLE: The current title is already a good length ({$title_len} chars): \"{$current_title}\". "
                . "Return it unchanged in the title field.";
        }

        if ($has_images) {
            $image_list = '';
            foreach ($images_needing_alt as $i => $img) {
                $image_list .= ($i + 1) . ". filename: \"{$img['filename']}\"\n";
            }
            $json_shape  = '{"description": "...", "title": "...", "alts": ["alt for image 1", "alt for image 2"]}';
            $image_instruction = "\n\nALT TEXT: Write concise ALT text (5–15 words, no 'Image of' prefix) for each image listed below.";
        } else {
            $image_list        = '';
            $image_instruction = '';
        }

        $system = $prompt . $site_context
            . "\n\nDESCRIPTION: The meta description MUST be between {$min} and {$max} characters including spaces. Count carefully."
            . $title_instruction
            . $image_instruction
            . "\n\nRespond ONLY with valid JSON in exactly this format, no other text, no markdown fences:\n{$json_shape}";

        $user_msg = "Article title: \"{$post->post_title}\"\n\nArticle content:\n{$content}"
            . ($has_images ? "\n\nImages needing ALT text:\n{$image_list}" : '');

        // ── Call AI ───────────────────────────────────────────────────────────
        $raw = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 700);
        $raw = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($raw))));
        $json = json_decode($raw, true);

        // If JSON parse fails, make a second attempt with a stricter reminder.
        if (!is_array($json) || !isset($json['description'])) {
            $retry = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, [
                ['role' => 'assistant', 'content' => $raw],
                ['role' => 'user',      'content' => 'Your response was not valid JSON. Respond ONLY with the JSON object, no explanation, no markdown.'],
            ], 700);
            $retry = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($retry))));
            $json  = json_decode($retry, true);
            // If still broken, fall back to description-only plain text.
            if (!is_array($json) || !isset($json['description'])) {
                $json = ['description' => trim($retry, '"\''), 'title' => $current_title];
            }
        }

        // ── Description ───────────────────────────────────────────────────────
        $desc = trim($json['description'] ?? '', '"\'');
        if (!$desc) throw new \RuntimeException('Empty description in AI response'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        // ── Length correction loop (up to 3 passes, escalating) ──────────────
        $extra_messages = [];
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $len = mb_strlen($desc);
            if ($len >= $min && $len <= $max) break;
            $direction = $len > $max ? 'too long' : 'too short';
            $delta     = $len > $max ? $len - $max : $min - $len;
            $trim_or_add = $len > $max
                ? "trim {$delta} characters off the end — cut a word or shorten a phrase."
                : "add {$delta} characters — expand a phrase or add a concrete detail.";

            if ($attempt === 0) {
                $correction = "That description is {$direction} at {$len} characters. "
                    . "It must be between {$min} and {$max} characters. "
                    . "Please {$trim_or_add} "
                    . "Output ONLY the revised description, nothing else.";
            } elseif ($attempt === 1) {
                $correction = "Still {$direction} at {$len} characters. The hard limit is {$min}\u2013{$max} characters. "
                    . "You MUST {$trim_or_add} "
                    . "Do not explain. Do not add quotes. Output the description text only.";
            } else {
                $over_or_under = $len > $max ? "over the maximum by {$delta}" : "under the minimum by {$delta}";
                $correction = "FINAL ATTEMPT. Your output is {$len} characters — {$over_or_under} characters. "
                    . "It MUST be between {$min} and {$max}. {$trim_or_add} "
                    . "Output ONLY the raw description text. No quotes. No labels. No explanation. Just the text.";
            }

            $extra_messages = array_merge($extra_messages, [
                ['role' => 'assistant', 'content' => $desc],
                ['role' => 'user',      'content' => $correction],
            ]);
            $retry = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, $extra_messages, 300);
            $retry = trim(trim($retry, '"\''));
            if ($retry) $desc = $retry;
        }

        // ── Title ─────────────────────────────────────────────────────────────
        $new_title    = trim($json['title'] ?? '', '"\'') ?: $current_title;
        $new_title_len = mb_strlen($new_title);
        $title_status  = 'in_range';
        $title_fixed   = null;
        $title_was     = null;

        if ($needs_title) {
            $title_was    = $current_title;
            $title_fixed  = $new_title;
            $title_status = ($new_title_len >= 50 && $new_title_len <= 60) ? 'fixed' : 'fixed_imperfect';
            update_post_meta($post_id, self::META_TITLE, sanitize_text_field($new_title));
        }

        // ── ALT texts ─────────────────────────────────────────────────────────
        $alts_saved = 0;
        if ($has_images && !empty($json['alts']) && is_array($json['alts'])) {
            $this->save_alts_from_combined($post_id, $images_needing_alt, $json['alts']);
            $alts_saved = min(count($json['alts']), count($images_needing_alt));
        }

        return [
            'description'  => $desc,
            'title'        => $title_fixed ?? $new_title,
            'title_was'    => $title_was,
            'title_chars'  => $needs_title ? $new_title_len : $title_len,
            'title_status' => $title_status,
            'alts_saved'   => $alts_saved,
        ];
    }

    /**
     * Call the configured AI provider and return a generated description for a single post.
     * Works with both Anthropic and Gemini — routes through dispatch_ai().
     * Used by the scheduled batch and fix-description flow.
     */
    private function call_ai_generate_desc(int $post_id): string {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-20250514');
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content  = $this->text_from_html((string) $post->post_content);
        $content  = mb_substr($content, 0, 6000);

        // Collect images that need ALT text — bundle into the same API call.
        $images_needing_alt = $this->collect_images_needing_alt($post_id);
        $has_images         = !empty($images_needing_alt);

        $site_context_parts = [];
        $site_name  = trim((string) $this->opts['site_name']);
        $person     = trim((string) $this->opts['person_name']);
        $job_title  = trim((string) $this->opts['person_job_title']);
        $home_desc  = trim((string) $this->opts['home_desc']);
        $def_desc   = trim((string) $this->opts['default_desc']);

        if ($site_name)  $site_context_parts[] = "Site name: {$site_name}";
        if ($person)     $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic)      $site_context_parts[] = "Site description: {$topic}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT:\n" . implode("\n", $site_context_parts)
            : '';

        if ($has_images) {
            // Combined prompt: description + ALT texts in one JSON response.
            $image_list = '';
            foreach ($images_needing_alt as $i => $img) {
                $image_list .= ($i + 1) . ". filename: \"{$img['filename']}\"\n";
            }
            $system = $prompt . $site_context
                . "\n\nCHARACTER REQUIREMENT: The meta description MUST be between {$min} and {$max} characters including spaces."
                . "\n\nYou will also write ALT text for each image. ALT text must be 5-15 words, descriptive, no 'Image of' prefix."
                . "\n\nRespond ONLY with valid JSON in exactly this format, no other text:\n"
                . "{\"description\": \"...\", \"alts\": [\"alt for image 1\", \"alt for image 2\"]}";
            $user_msg = "Article title: \"{$post->post_title}\"\n\nArticle content:\n{$content}\n\nImages needing ALT text:\n{$image_list}";

            $raw  = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 600);
            $raw  = trim($raw);
            // Strip markdown code fences if present.
            $raw  = preg_replace('/^```(?:json)?\s*/i', '', $raw);
            $raw  = preg_replace('/\s*```$/', '', $raw);
            $json = json_decode($raw, true);

            if (is_array($json) && isset($json['description'])) {
                $desc = trim($json['description'], '"\'');
                // Save ALT texts in the background.
                if (!empty($json['alts']) && is_array($json['alts'])) {
                    $this->save_alts_from_combined($post_id, $images_needing_alt, $json['alts']);
                }
                if (!$desc) throw new \RuntimeException('Empty description in combined response'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

                $len = mb_strlen($desc);
                if ($len >= $min && $len <= $max) return $desc;

                // Out of range — one correction pass (description only now).
                $direction      = $len > $max ? 'too long' : 'too short';
                $correction_msg = "That description is {$direction} at {$len} characters. "
                    . "Rewrite it so it is between {$min} and {$max} characters. "
                    . "Output ONLY the new description text, nothing else.";
                $text2 = $this->dispatch_ai($provider, $key, $model,
                    $prompt . $site_context . "\n\nCHARACTER REQUIREMENT: Between {$min} and {$max} characters.",
                    $user_msg,
                    [['role' => 'assistant', 'content' => $desc], ['role' => 'user', 'content' => $correction_msg]],
                    300);
                $text2 = trim(trim($text2, '"\''));
                return $text2 ?: $desc;
            }
            // JSON parse failed — fall through to plain description-only call.
        }

        // Plain description-only call (no images, or JSON parse failed).
        $system = $prompt . $site_context
            . "\n\nCHARACTER REQUIREMENT: The description MUST be between {$min} and {$max} characters including spaces. Count every character carefully. Do not produce output outside this range.";
        $user_msg = "Article title: \"{$post->post_title}\"\n\nArticle content:\n{$content}";

        $text = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 300);
        $text = trim($text, '"\'');
        if (!$text) throw new \RuntimeException('Empty response from AI'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $len = mb_strlen($text);
        if ($len >= $min && $len <= $max) return $text;

        // Out of range — one correction attempt.
        $direction      = $len > $max ? 'too long' : 'too short';
        $correction_msg = "That description is {$direction} at {$len} characters. "
            . "Rewrite it so it is between {$min} and {$max} characters. "
            . "Output ONLY the new description text, nothing else.";

        $text2 = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, [
            ['role' => 'assistant', 'content' => $text],
            ['role' => 'user',      'content' => $correction_msg],
        ], 300);
        $text2 = trim(trim($text2, '"\''));
        if ($text2) return $text2;

        return $text;
    }

    /**
     * Collect images in a post that have empty ALT attributes.
     * Returns array of ['img_tag'=>..., 'src'=>..., 'filename'=>..., 'attach_id'=>...].
     */
    private function collect_images_needing_alt(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) return [];

        preg_match_all('/<img[^>]+>/i', (string) $post->post_content, $img_tags);
        $images = [];
        foreach ($img_tags[0] as $img_tag) {
            if (!preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $alt_m)) continue;
            if ($alt_m[1] !== '') continue; // already has ALT
            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_m)) $src = $src_m[1];
            if (!$src) continue;
            $attach_id = 0;
            if (preg_match('/wp-image-(\d+)/i', $img_tag, $id_m)) $attach_id = (int) $id_m[1];
            $filename = pathinfo(wp_parse_url($src, PHP_URL_PATH), PATHINFO_FILENAME);
            $filename = preg_replace('/[-_](\d+x\d+)$/', '', $filename);
            $filename = str_replace(['-', '_'], ' ', $filename);
            $images[] = ['img_tag' => $img_tag, 'src' => $src, 'filename' => $filename, 'attach_id' => $attach_id];
        }
        return $images;
    }

    /**
     * Save ALT texts returned by the combined API call into post content and attachment meta.
     */
    private function save_alts_from_combined(int $post_id, array $images, array $alts): void {
        $post = get_post($post_id);
        if (!$post) return;
        $new_content = (string) $post->post_content;
        $changed     = false;
        foreach ($images as $i => $img) {
            $alt_text = trim($alts[$i] ?? '', '"\'');
            if (!$alt_text) continue;
            $alt_text = sanitize_text_field($alt_text);
            if ($img['attach_id']) update_post_meta($img['attach_id'], '_wp_attachment_image_alt', $alt_text);
            $new_tag     = preg_replace('/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img['img_tag'], 1);
            $new_content = str_replace($img['img_tag'], $new_tag, $new_content);
            $changed     = true;
        }
        if ($changed) {
            wp_update_post(['ID' => $post_id, 'post_content' => $new_content]);
        }
    }

    public function ajax_generate_one(): void {
        $this->ajax_check();
        $post_id = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        if (!$post_id) wp_send_json_error('Missing post_id');

        try {
            $result = $this->call_ai_generate_all($post_id);
            update_post_meta($post_id, self::META_DESC, sanitize_textarea_field($result['description']));
            wp_send_json_success([
                'post_id'      => $post_id,
                'description'  => $result['description'],
                'chars'        => mb_strlen($result['description']),
                'alts_saved'   => $result['alts_saved'],
                'title'        => $result['title'],
                'title_was'    => $result['title_was'],
                'title_chars'  => $result['title_chars'],
                'title_status' => $result['title_status'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Fix an existing description that is too short or too long.
     */
    private function call_ai_fix_desc(int $post_id, string $existing_desc): string {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-20250514');
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $len       = mb_strlen($existing_desc);
        $direction = $len > $max ? 'too long' : 'too short';
        $content   = $this->text_from_html((string) $post->post_content);
        $content   = mb_substr($content, 0, 6000);

        $site_context_parts = [];
        $site_name = trim((string) $this->opts['site_name']);
        $person    = trim((string) $this->opts['person_name']);
        $job_title = trim((string) $this->opts['person_job_title']);
        $home_desc = trim((string) $this->opts['home_desc']);
        $def_desc  = trim((string) $this->opts['default_desc']);

        if ($site_name) $site_context_parts[] = "Site name: {$site_name}";
        if ($person)    $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic) $site_context_parts[] = "Site description: {$topic}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT:\n" . implode("\n", $site_context_parts)
            : '';

        $system   = $prompt . $site_context
            . "\n\nCHARACTER REQUIREMENT: The description MUST be between {$min} and {$max} characters including spaces. Count every character carefully. Do not produce output outside this range.";
        $user_msg = "Article title: \"{$post->post_title}\"\n\nArticle content:\n{$content}";
        $correction = "The existing meta description for this article is {$direction} at {$len} characters:\n\n"
            . "\"{$existing_desc}\"\n\n"
            . "Rewrite it so it is between {$min} and {$max} characters. Keep the meaning and keyword focus. "
            . "Output ONLY the rewritten description, nothing else.";

        $extra_messages = [
            ['role' => 'assistant', 'content' => $existing_desc],
            ['role' => 'user',      'content' => $correction],
        ];

        $text = trim(trim($this->dispatch_ai($provider, $key, $model, $system, $user_msg, $extra_messages, 300), '"\''));
        if (!$text) throw new \RuntimeException('Empty response from AI'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        // Up to 3 correction retries if still out of range.
        $attempt = 0;
        while (mb_strlen($text) < $min || mb_strlen($text) > $max) {
            if (++$attempt > 3) break;
            $current_len = mb_strlen($text);
            $dir2        = $current_len > $max ? 'too long' : 'too short';
            $retry_extra = array_merge($extra_messages, [
                ['role' => 'assistant', 'content' => $text],
                ['role' => 'user', 'content'
                    => "FAILED. Your previous response was {$current_len} characters which is {$dir2}. "
                    . "You did not follow the instructions. "
                    . "The description MUST be between {$min} and {$max} characters — this is a hard requirement. "
                    . "Before you write anything, count out {$min} to {$max} characters in your head, then write a description that fits exactly within that count. "
                    . "Check your character count before outputting. "
                    . "Output ONLY the description text, no explanation, no quotes, nothing else."],
            ]);
            $retry_text = trim(trim($this->dispatch_ai($provider, $key, $model, $system, $user_msg, $retry_extra, 300), '"\''));
            if (!$retry_text) break;
            $text = $retry_text;
        }

        return $text;
    }

    public function ajax_fix_desc(): void {
        $this->ajax_check();
        $post_id = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        if (!$post_id) wp_send_json_error('Missing post_id');

        $min = max(100, (int) $this->ai_opts['min_chars']);
        $max = min(200, (int) $this->ai_opts['max_chars']);

        $existing = trim((string) get_post_meta($post_id, self::META_DESC, true));
        $len      = mb_strlen($existing);

        // Only fix if actually out of range.
        if (!$existing) {
            wp_send_json_success(['post_id' => $post_id, 'status' => 'skipped', 'message' => 'No description to fix']);
            return;
        }
        if ($len >= $min && $len <= $max) {
            wp_send_json_success(['post_id' => $post_id, 'status' => 'skipped', 'message' => 'Already in range (' . $len . ' chars)']);
            return;
        }

        try {
            $desc      = $this->call_ai_fix_desc($post_id, $existing);
            $new_len   = mb_strlen($desc);
            $in_range  = ($new_len >= $min && $new_len <= $max);
            update_post_meta($post_id, self::META_DESC, sanitize_textarea_field($desc));
            wp_send_json_success([
                'post_id'       => $post_id,
                'status'        => $in_range ? 'fixed' : 'fixed_imperfect',
                'description'   => $desc,
                'chars'         => $new_len,
                'was_chars'     => $len,
                'in_range'      => $in_range,
                'message'       => ($in_range
                    ? 'Fixed: was ' . $len . ' chars, now ' . $new_len . ' chars'
                    : 'Saved but still out of range: was ' . $len . ' chars, now ' . $new_len . ' chars'),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['post_id' => $post_id, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Fix a single post title that is outside the 50-60 character ideal range.
     * Saves the AI-generated title as the custom SEO title (META_TITLE), leaving
     * the original WordPress post title untouched.
     */
    public function ajax_fix_title(): void {
        $this->ajax_check();
        $post_id = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$post_id) wp_send_json_error('Missing post_id');

        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Post not found');

        $custom   = trim((string) get_post_meta($post_id, self::META_TITLE, true));
        $current  = $custom !== '' ? $custom : get_the_title($post_id);
        $len      = mb_strlen($current);

        if ($len >= 50 && $len <= 60) {
            wp_send_json_success(['post_id' => $post_id, 'status' => 'skipped',
                'message' => 'Already in range (' . $len . ' chars)', 'title' => $current, 'chars' => $len]);
            return;
        }

        try {
            $new_title = $this->call_ai_fix_title($post_id, $current);
            $new_len   = mb_strlen($new_title);
            $in_range  = ($new_len >= 50 && $new_len <= 60);
            update_post_meta($post_id, self::META_TITLE, sanitize_text_field($new_title));
            wp_send_json_success([
                'post_id'  => $post_id,
                'status'   => $in_range ? 'fixed' : 'fixed_imperfect',
                'title'    => $new_title,
                'chars'    => $new_len,
                'was_chars'=> $len,
                'in_range' => $in_range,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error(['post_id' => $post_id, 'message' => $e->getMessage()]);
        }
    }

    private function call_ai_fix_title(int $post_id, string $current_title): string {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001');
        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $len       = mb_strlen($current_title);
        $direction = $len > 60 ? 'too long' : 'too short';
        $content   = $this->text_from_html((string) $post->post_content);
        $content   = mb_substr($content, 0, 2000);

        $system   = 'You write concise, compelling SEO title tags for blog posts. '
            . 'The title MUST be between 50 and 60 characters including spaces — count carefully. '
            . 'Keep the core topic and keywords. Do not add quotes or punctuation at start/end. '
            . 'Output ONLY the title text, nothing else.';
        $user_msg = "Current title ({$direction} at {$len} chars): \"{$current_title}\"\n\n"
            . "Article excerpt:\n{$content}\n\n"
            . "Rewrite the title so it is between 50 and 60 characters. Output only the title.";

        $text = trim(trim($this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 100), '"\''));
        if (!$text) throw new \RuntimeException('Empty response from AI'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        // One correction pass if still out of range.
        $new_len = mb_strlen($text);
        if ($new_len < 50 || $new_len > 60) {
            $dir2  = $new_len > 60 ? 'too long' : 'too short';
            $fix   = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, [
                ['role' => 'assistant', 'content' => $text],
                ['role' => 'user', 'content' => "That is {$dir2} at {$new_len} chars. Rewrite it to be between 50 and 60 characters. Output only the title."],
            ], 100);
            $fix = trim(trim($fix, '"\''));
            if ($fix) $text = $fix;
        }

        return $text;
    }

    /**
     * Generate all — called once per post by the JS polling loop.
     * Returns result for a single post_id; JS calls this repeatedly.
     */
    public function ajax_generate_all(): void {
        $this->ajax_check();
        $post_id   = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) );   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        $overwrite = (int) sanitize_key( wp_unslash( $_POST['overwrite'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if (!$post_id) wp_send_json_error('Missing post_id');

        // Skip if already has a description and overwrite is off.
        $existing = trim((string) get_post_meta($post_id, self::META_DESC, true));
        if ($existing && !$overwrite) {
            wp_send_json_success([
                'post_id'     => $post_id,
                'status'      => 'skipped',
                'description' => $existing,
                'chars'       => mb_strlen($existing),
                'message'     => 'Skipped — description already exists',
            ]);
            return;
        }

        try {
            $result = $this->call_ai_generate_all($post_id);
            update_post_meta($post_id, self::META_DESC, sanitize_textarea_field($result['description']));
            wp_send_json_success([
                'post_id'      => $post_id,
                'status'       => 'generated',
                'description'  => $result['description'],
                'chars'        => mb_strlen($result['description']),
                'message'      => 'Generated: ' . mb_strlen($result['description']) . ' chars',
                'title'        => $result['title'],
                'title_was'    => $result['title_was'],
                'title_chars'  => $result['title_chars'],
                'title_status' => $result['title_status'],
                'alts_saved'   => $result['alts_saved'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'post_id' => $post_id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    // ALT Text AI generation
    // =========================================================================

    /**
     * Return posts that have images without ALT text in their content,
     * along with attachment images missing ALT text.
     */
    public function ajax_alt_get_posts(): void {
        $this->ajax_check();

        $posts = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        $results      = [];
        $total_images = 0;
        $missing_alt  = 0;

        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            if (!$post) continue;
            $content = (string) $post->post_content;

            // Find all <img> tags in post content.
            preg_match_all('/<img[^>]+>/i', $content, $img_tags);

            $post_images = [];
            foreach ($img_tags[0] as $img_tag) {
                // Extract attachment ID from class wp-image-NNN.
                $attach_id = 0;
                if (preg_match('/wp-image-(\d+)/i', $img_tag, $m)) {
                    $attach_id = (int) $m[1];
                }
                // Extract src.
                $src = '';
                if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $m)) {
                    $src = $m[1];
                }
                // Extract current alt — note whether attribute is present at all.
                $has_alt_attr = (bool) preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $m);
                $alt          = $has_alt_attr ? $m[1] : '';
                $missing      = !$has_alt_attr || $alt === '';

                if ($src) {
                    $post_images[] = [
                        'attach_id' => $attach_id,
                        'src'       => $src,
                        'alt'       => $alt,
                        'missing'   => $missing,
                    ];
                    $total_images++;
                    if ($missing) $missing_alt++;
                }
            }

            // Also check the featured image — it lives outside post content so
            // the img-tag scan above will never find it.
            $thumb_id = (int) get_post_thumbnail_id($post_id);
            if ($thumb_id) {
                $thumb_src = wp_get_attachment_image_src($thumb_id, 'full');
                $thumb_alt = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
                $missing_thumb = ($thumb_alt === '');
                if ($thumb_src && !empty($thumb_src[0])) {
                    // Avoid double-counting if the featured image is also embedded in content.
                    $already_listed = false;
                    foreach ($post_images as $pi) {
                        if ($pi['attach_id'] === $thumb_id) { $already_listed = true; break; }
                    }
                    if (!$already_listed) {
                        $post_images[] = [
                            'attach_id'  => $thumb_id,
                            'src'        => $thumb_src[0],
                            'alt'        => $thumb_alt,
                            'missing'    => $missing_thumb,
                            'is_featured'=> true,
                        ];
                        $total_images++;
                        if ($missing_thumb) $missing_alt++;
                    }
                }
            }

            if (!empty($post_images)) {
                $post_missing = count(array_filter($post_images, fn($i) => $i['missing']));
                $results[] = [
                    'id'           => $post_id,
                    'title'        => get_the_title($post_id),
                    'type'         => get_post_type($post_id),
                    'date'         => get_the_date('d M Y', $post_id),
                    'missing_count'=> $post_missing,
                    'images'       => $post_images,
                ];
            }
        }

        wp_send_json_success([
            'posts'        => $results,
            'total_posts'  => count($results),
            'total_images' => $total_images,
            'missing_alt'  => $missing_alt,
        ]);
    }

    /**
     * Generate ALT text for all images missing it in a single post.
     * Updates the attachment meta AND replaces alt="" in post content.
     */
    public function ajax_alt_generate_one(): void {
        $this->ajax_check();
        $post_id = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$post_id) wp_send_json_error('Missing post_id');
        $force = (int) sanitize_text_field( wp_unslash( $_POST['force'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_check() above

        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Post not found');

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        if (!$key) wp_send_json_error($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured');

        $model   = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001');
        $content = (string) $post->post_content;
        $title   = get_the_title($post_id);

        preg_match_all('/<img[^>]+>/i', $content, $img_tags);

        // Strip HTML and truncate article text to give the AI context for what
        // the images are illustrating. Limit is configurable in AI Settings.
        $excerpt_limit = max(100, min(2000, (int)($this->ai_opts['alt_excerpt_chars'] ?? 600)));
        $article_text  = wp_strip_all_tags($content);
        $article_text  = preg_replace('/\s+/', ' ', $article_text);
        $article_text  = trim($article_text);
        if (mb_strlen($article_text) > $excerpt_limit) {
            $article_text = mb_substr($article_text, 0, $excerpt_limit) . '…';
        }

        $updated     = 0;
        $new_content = $content;
        $generated   = [];
        $warnings    = [];

        // ALT text length bounds (words).
        $min_words = 5;
        $max_words = 15;

        foreach ($img_tags[0] as $img_tag) {
            // Check current alt value.
            $has_alt_attr = (bool) preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $alt_m);
            $current_alt  = $has_alt_attr ? $alt_m[1] : '';

            // In normal mode: only process images with empty alt.
            // In force mode: process all images.
            if (!$force && $current_alt !== '') continue;

            $src = '';
            if (preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_m)) {
                $src = $src_m[1];
            }
            if (!$src) continue;

            // Extract filename as context hint.
            $filename = pathinfo(wp_parse_url($src, PHP_URL_PATH), PATHINFO_FILENAME);
            $filename = preg_replace('/[-_](\d+x\d+)$/', '', $filename); // strip size suffix
            $filename = str_replace(['-', '_'], ' ', $filename);

            // Build prompt — include article excerpt so AI understands image context.
            $system   = 'You write concise, descriptive image alt text for blog post images. '
                . 'Alt text should describe what the image shows in 5–15 words, relevant to the post context. '
                . 'Do not start with "Image of" or "Photo of". Output ONLY the alt text, nothing else.';
            $user_msg = "Post title: \"{$title}\"\n"
                . "Article excerpt: \"{$article_text}\"\n"
                . "Image filename hint: \"{$filename}\"\n"
                . "Write appropriate alt text for this image.";

            try {
                $alt_text = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 80);
                $alt_text = trim(trim($alt_text, '"\''));
                if (!$alt_text) continue;

                // Validate word count — retry once if too short or too long.
                $word_count = str_word_count($alt_text);
                if ($word_count < $min_words || $word_count > $max_words) {
                    $retry_msg = "Your previous alt text was {$word_count} words: \"{$alt_text}\"\n"
                        . "Post title: \"{$title}\"\n"
                        . "Image filename hint: \"{$filename}\"\n"
                        . "Rewrite the alt text to be between {$min_words} and {$max_words} words. Output ONLY the alt text.";
                    $retry_text = $this->dispatch_ai($provider, $key, $model, $system, $retry_msg, null, 80);
                    $retry_text = trim(trim($retry_text, '"\''));
                    if ($retry_text) {
                        $alt_text = $retry_text;
                    }
                }

                // Sanitize.
                $alt_text = sanitize_text_field($alt_text);

                // Update attachment alt meta if we have an ID.
                $attach_id = 0;
                if (preg_match('/wp-image-(\d+)/i', $img_tag, $id_m)) {
                    $attach_id = (int) $id_m[1];
                    update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
                }

                // Replace alt in post content (handles both alt="" and alt="existing").
                if ($has_alt_attr) {
                    $new_tag = preg_replace('/alt=["\'][^"\']*["\']/', 'alt="' . esc_attr($alt_text) . '"', $img_tag, 1);
                } else {
                    $new_tag = str_replace('<img ', '<img alt="' . esc_attr($alt_text) . '" ', $img_tag);
                }
                $new_content = str_replace($img_tag, $new_tag, $new_content);

                $generated[] = ['src' => $src, 'alt' => $alt_text, 'attach_id' => $attach_id];
                $updated++;
            } catch (\Throwable $e) {
                // Skip this image on error — continue with remaining images.
                $warnings[] = sprintf( '%s: %s', esc_url( $src ), $e->getMessage() );
            }
        }

        // Also handle the featured image — it lives in post meta, not post_content,
        // so the img-tag loop above will never reach it.
        $thumb_id = (int) get_post_thumbnail_id($post_id);
        if ($thumb_id) {
            $thumb_alt = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
            if ($force || $thumb_alt === '') {
                $thumb_src  = wp_get_attachment_image_src($thumb_id, 'full');
                $thumb_url  = $thumb_src ? (string) $thumb_src[0] : '';
                $filename   = pathinfo(wp_parse_url($thumb_url, PHP_URL_PATH), PATHINFO_FILENAME);
                $filename   = preg_replace('/[-_](\d+x\d+)$/', '', $filename);
                $filename   = str_replace(['-', '_'], ' ', $filename);

                $system   = 'You write concise, descriptive image alt text for blog post images. '
                    . 'Alt text should describe what the image shows in 5–15 words, relevant to the post context. '
                    . 'Do not start with "Image of" or "Photo of". Output ONLY the alt text, nothing else.';
                $user_msg = "Post title: \"{$title}\"\n"
                    . "Article excerpt: \"{$article_text}\"\n"
                    . "Image filename hint: \"{$filename}\"\n"
                    . "Write appropriate alt text for this image.";

                try {
                    $alt_text = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 80);
                    $alt_text = trim(trim($alt_text, '"\''));
                    if ($alt_text) {
                        $word_count = str_word_count($alt_text);
                        if ($word_count < $min_words || $word_count > $max_words) {
                            $retry_msg  = "Your previous alt text was {$word_count} words: \"{$alt_text}\"\n"
                                . "Post title: \"{$title}\"\n"
                                . "Image filename hint: \"{$filename}\"\n"
                                . "Rewrite the alt text to be between {$min_words} and {$max_words} words. Output ONLY the alt text.";
                            $retry_text = $this->dispatch_ai($provider, $key, $model, $system, $retry_msg, null, 80);
                            $retry_text = trim(trim($retry_text, '"\''));
                            if ($retry_text) $alt_text = $retry_text;
                        }
                        $alt_text = sanitize_text_field($alt_text);
                        update_post_meta($thumb_id, '_wp_attachment_image_alt', $alt_text);
                        $generated[] = ['src' => $thumb_url, 'alt' => $alt_text, 'attach_id' => $thumb_id];
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $warnings[] = sprintf( 'Featured image %s: %s', esc_url( $thumb_url ), $e->getMessage() );
                }
            }
        }

        if ($updated > 0 && $new_content !== $content) {
            // Save updated post content only if content images were changed.
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => $new_content,
            ]);
        }

        wp_send_json_success([
            'post_id'   => $post_id,
            'updated'   => $updated,
            'generated' => $generated,
            'warnings'  => $warnings,
        ]);
    }

    /**
     * Batch ALT — same as generate_one but designed for polling loop.
     */
    public function ajax_alt_generate_all(): void {
        $this->ajax_alt_generate_one();
    }

    // =========================================================================
    // AI Summary Box
    // =========================================================================

    /**
     * Generate a 3-field AI summary for a post: what it is, why it matters, key takeaway.
     * Returns an array with keys 'what', 'why', 'takeaway', or throws on failure.
     */
    private function call_ai_generate_summary(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException("Post {$post_id} not found"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-20250514');

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content = $this->text_from_html((string) $post->post_content);
        $content = mb_substr($content, 0, 6000);

        $system = <<<'PROMPT'
You are a technical writing assistant. Given an article title and content, write a concise 3-part summary.

Rules:
- "what": 1-2 sentences. What is this article about? Be specific and concrete.
- "why": 1-2 sentences. Why does this matter to the reader? Focus on practical impact.
- "takeaway": 1 sentence. The single most important thing to remember.
- Plain language. No jargon introductions like "In this article" or "This post".
- Do not start any field with the article title.
- Respond ONLY with valid JSON in exactly this format, no other text:
{"what": "...", "why": "...", "takeaway": "..."}
PROMPT;

        $user_msg = "Article title: \"{$post->post_title}\"\n\nArticle content:\n{$content}";

        $raw = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 400);
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $json = json_decode($raw, true);
        if (!is_array($json) || empty($json['what']) || empty($json['why']) || empty($json['takeaway'])) {
            throw new \RuntimeException('Invalid summary response from AI: ' . esc_html(mb_substr($raw, 0, 200))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return [
            'what'     => sanitize_textarea_field(trim($json['what'])),
            'why'      => sanitize_textarea_field(trim($json['why'])),
            'takeaway' => sanitize_textarea_field(trim($json['takeaway'])),
        ];
    }

    /**
     * AJAX: generate summary for a single post.
     */
    public function ajax_summary_generate_one(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Forbidden', 403);

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id) wp_send_json_error('Missing post_id');

        $force = !empty($_POST['force']);

        // Skip if already generated and not forced.
        if (!$force) {
            $existing_what = get_post_meta($post_id, self::META_SUM_WHAT, true);
            if ($existing_what) {
                wp_send_json_success(['post_id' => $post_id, 'skipped' => true]);
            }
        }

        try {
            $summary = $this->call_ai_generate_summary($post_id);
            update_post_meta($post_id, self::META_SUM_WHAT, $summary['what']);
            update_post_meta($post_id, self::META_SUM_WHY,  $summary['why']);
            update_post_meta($post_id, self::META_SUM_KEY,  $summary['takeaway']);
            wp_send_json_success([
                'post_id'  => $post_id,
                'what'     => $summary['what'],
                'why'      => $summary['why'],
                'takeaway' => $summary['takeaway'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX: generate summaries for all posts missing them (batch).
     * Processes one post per call — JS loops until done (same pattern as ALT batch).
     */
    public function ajax_summary_generate_all(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Forbidden', 403);

        $force = !empty($_POST['force']);

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!$force) {
            $args['meta_query'] = [[ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- NOT EXISTS has no alternative
                'key'     => self::META_SUM_WHAT,
                'compare' => 'NOT EXISTS',
            ]];
        }

        $ids = get_posts($args);

        if (empty($ids)) {
            wp_send_json_success(['done' => true, 'remaining' => 0]);
        }

        $post_id = (int) $ids[0];

        try {
            $summary = $this->call_ai_generate_summary($post_id);
            update_post_meta($post_id, self::META_SUM_WHAT, $summary['what']);
            update_post_meta($post_id, self::META_SUM_WHY,  $summary['why']);
            update_post_meta($post_id, self::META_SUM_KEY,  $summary['takeaway']);

            // Count remaining after this one.
            $remaining_args = $args;
            $remaining_args['posts_per_page'] = -1;
            $remaining = count(get_posts($remaining_args));

            wp_send_json_success([
                'post_id'   => $post_id,
                'what'      => $summary['what'],
                'why'       => $summary['why'],
                'takeaway'  => $summary['takeaway'],
                'done'      => $remaining === 0,
                'remaining' => $remaining,
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Regenerate Static — clears stale custom OG image meta for a single post
     * so the featured image takes over, and returns the resolved image URL.
     */
    public function ajax_regen_static(): void {
        $this->ajax_check();
        $post_id = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        if (!$post_id) wp_send_json_error('Missing post_id');

        // Clear stale custom OG image — fall through to featured image.
        $had_custom = (bool) get_post_meta($post_id, self::META_OGIMG, true);
        delete_post_meta($post_id, self::META_OGIMG);

        // Resolve what image will now be used.
        $thumb_id  = (int) get_post_thumbnail_id($post_id);
        $thumb_src = $thumb_id ? wp_get_attachment_image_src($thumb_id, 'full') : false;
        $image_url = $thumb_src ? $thumb_src[0] : trim((string) $this->opts['default_og_image']);

        wp_send_json_success([
            'post_id'     => $post_id,
            'had_custom'  => $had_custom,
            'image_url'   => $image_url,
            'source'      => $thumb_id ? 'featured_image' : ($image_url ? 'site_default' : 'none'),
        ]);
    }

    /**
     * Return paginated list of posts with their current SEO desc status.
     */
    public function ajax_get_posts(): void {
        global $wpdb;
        $this->ajax_check();
        $page = max(1, (int) sanitize_key( wp_unslash( $_POST['page'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        $per_page = 50;

        // Build homepage row — pinned at top on page 1 regardless of Reading settings.
        $homepage = null;
        $front_page_id = (int) get_option('page_on_front');
        $show_on_front = get_option('show_on_front'); // 'page' or 'posts'

        if ($show_on_front === 'page' && $front_page_id) {
            // Static page set as front page.
            $hp = get_post($front_page_id);
            if ($hp) {
                $desc            = trim((string) get_post_meta($front_page_id, self::META_DESC, true));
                $custom_hp_title = trim((string) get_post_meta($front_page_id, self::META_TITLE, true));
                preg_match_all('/<img[^>]+>/i', (string) $hp->post_content, $img_tags);
                $missing_alt = 0;
                foreach ($img_tags[0] as $img_tag) {
                    if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $m) && $m[1] === '') $missing_alt++;
                }
                $homepage = [
                    'id'              => $front_page_id,
                    'title'           => get_the_title($front_page_id),
                    'effective_title' => $custom_hp_title !== '' ? $custom_hp_title : get_the_title($front_page_id),
                    'title_chars'     => mb_strlen($custom_hp_title !== '' ? $custom_hp_title : get_the_title($front_page_id)),
                    'type'            => 'homepage',
                    'date'            => get_the_date('Y-m-d', $front_page_id),
                    'has_desc'        => $desc !== '',
                    'desc'            => $desc,
                    'desc_chars'      => mb_strlen($desc),
                    'missing_alt'     => $missing_alt,
                    'is_homepage'     => true,
                ];
            }
        } elseif ($show_on_front === 'posts') {
            // Blog posts index — no post object, use a virtual row with ID 0.
            $desc = trim((string) get_option('blogdescription'));
            $homepage = [
                'id'          => 0,
                'title'       => get_bloginfo('name'),
                'type'        => 'homepage',
                'date'        => '',
                'has_desc'    => false,
                'desc'        => '',
                'desc_chars'  => 0,
                'missing_alt' => 0,
                'is_homepage' => true,
                'no_post'     => true, // flags that AI generation is not possible
            ];
        }

        $q = new WP_Query([
            'post_type'           => ['post', 'page'],
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => $page,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            // Exclude front page from main list — it's already pinned at top.
            // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excluding front page from sitemap batch is intentional and low-volume
            'post__not_in'        => $front_page_id ? [$front_page_id] : [],
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $desc = trim((string) get_post_meta($p->ID, self::META_DESC, true));
            // Effective title = custom SEO title if set, otherwise post title.
            $custom_title    = trim((string) get_post_meta($p->ID, self::META_TITLE, true));
            $effective_title = $custom_title !== '' ? $custom_title : get_the_title($p->ID);
            // Count images with empty ALT in post content.
            preg_match_all('/<img[^>]+>/i', (string) $p->post_content, $img_tags);
            $missing_alt = 0;
            foreach ($img_tags[0] as $img_tag) {
                if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $m) && $m[1] === '') $missing_alt++;
            }
            $items[] = [
                'id'               => $p->ID,
                'title'            => get_the_title($p->ID),
                'effective_title'  => $effective_title,
                'title_chars'      => mb_strlen($effective_title),
                'type'             => $p->post_type,
                'date'             => get_the_date('Y-m-d', $p->ID),
                'has_desc'         => $desc !== '',
                'desc'             => $desc,
                'desc_chars'       => mb_strlen($desc),
                'missing_alt'      => $missing_alt,
            ];
        }

        // Prepend homepage row on page 1 only.
        if ($page === 1 && $homepage) {
            array_unshift($items, $homepage);
        }

        wp_send_json_success([
            'posts'           => $items,
            'homepage'        => $homepage,
            'total'           => (int) $q->found_posts,
            'total_pages'     => (int) $q->max_num_pages,
            'page'            => $page,
            'total_with_desc' => (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT p.ID)
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                     WHERE p.post_type IN ('post','page')
                     AND p.post_status = 'publish'
                     AND pm.meta_key = %s
                     AND pm.meta_value != ''",
                    self::META_DESC
                )
            ),
        ]);
    }

    /**
     * Test the stored API key with a minimal API call — supports Anthropic and Gemini.
     */
    public function ajax_test_key(): void {
        $this->ajax_check();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? $this->ai_opts['ai_provider'] ?? 'anthropic'));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        $key = sanitize_text_field(wp_unslash($_POST['live_key'] ?? ''));
        if (!$key) {
            $saved_key = $provider === 'gemini' ? ($this->ai_opts['gemini_key'] ?? '') : $this->ai_opts['anthropic_key'];
            $key = $saved_key;
        }
        if (!$key) wp_send_json_error('No API key entered');

        if ($provider === 'gemini') {
            // Gemini: use generateContent endpoint with a minimal prompt
            $model = 'gemini-2.0-flash';
            $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
            $response = wp_remote_post($url, [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'contents' => [['parts' => [['text' => 'Reply with: OK']]]],
                    'generationConfig' => ['maxOutputTokens' => 10],
                ]),
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed: ' . $response->get_error_message());
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code === 200) {
                wp_send_json_success('API key valid. Provider: Google Gemini');
            } else {
                $msg = $body['error']['message'] ?? "HTTP {$code}";
                wp_send_json_error("API error: {$msg}");
            }

        } else {
            // Anthropic
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 15,
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $key,
                    'anthropic-version' => '2023-06-01',
                ],
                'body' => wp_json_encode([
                    'model'      => $this->ai_opts['model'] ?: 'claude-sonnet-4-20250514',
                    'max_tokens' => 10,
                    'messages'   => [['role' => 'user', 'content' => 'Reply with: OK']],
                ]),
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed: ' . $response->get_error_message());
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code === 200) {
                wp_send_json_success('API key valid. Model: ' . ($body['model'] ?? 'unknown'));
            } else {
                $msg = $body['error']['message'] ?? "HTTP {$code}";
                wp_send_json_error("API error: {$msg}");
            }
        }
    }

    // =========================================================================
    // Admin menu & settings
    // =========================================================================

    public function admin_notices(): void {
        // Show post-rename confirmation if the backup flag is set
        $bak = get_option('cs_seo_robots_bak');
        if ($bak !== false) {
            // Dismiss handler
            if (isset($_GET['_cs_dismiss_robotsbak']) && check_admin_referer('cs_dismiss_robotsbak')) {
                delete_option('cs_seo_robots_bak');
            } else {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>CloudScale SEO:</strong> The physical <code>robots.txt</code> file has been renamed to <code>robots.txt.bak</code>. ';
                echo 'The plugin is now managing your robots.txt. Your original rules have been preserved — review the Robots.txt card and merge anything you want to keep.</p>';
                echo '<p><a href="' . esc_url(wp_nonce_url(admin_url('tools.php?page=cs-seo-optimizer&_cs_dismiss_robotsbak=1'), 'cs_dismiss_robotsbak')) . '">Dismiss</a></p>';
                echo '</div>';
            }
        }
    }

    public function admin_enqueue_assets(): void {
        if (!$this->is_our_page()) return;

        // Register a no-op handle so we can attach inline style and scripts to it.
        wp_register_style('cs-seo-admin', false, [], self::VERSION);
        wp_enqueue_style('cs-seo-admin');
        wp_add_inline_style('cs-seo-admin', '#wpfooter { display:none !important; } #wpcontent, #wpbody-content { padding-bottom:0 !important; }');

        // Register a no-op script handle for inline scripts that have no external file.
        wp_register_script('cs-seo-admin-js', false, [], self::VERSION, true);
        wp_enqueue_script('cs-seo-admin-js');

        // Pass PHP values needed by inline scripts as a JS object.
        wp_localize_script('cs-seo-admin-js', 'csSeoAdmin', [
            'defaultPrompt' => self::default_prompt(),
        ]);

        // Reset-prompt button (was inline at line 3540).
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var resetBtn = document.getElementById("ab-reset-prompt");
                if (resetBtn) {
                    resetBtn.addEventListener("click", function() {
                        document.getElementById("ab-prompt-field").value = csSeoAdmin.defaultPrompt;
                    });
                }
            });'
        );

        // Defer JS excludes toggle (was inline at line 4574).
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var deferToggle = document.getElementById("ab-defer-toggle");
                if (deferToggle) {
                    deferToggle.addEventListener("change", function() {
                        document.getElementById("ab-defer-excludes-wrap").style.display = this.checked ? "" : "none";
                    });
                }
            });'
        );

        // Scheduled batch day toggle (was inline at line 4654).
        wp_add_inline_script('cs-seo-admin-js',
            'function csToggleSchedDays(enabled) {
                document.querySelectorAll(".cs-sched-day").forEach(function(cb) {
                    cb.disabled = !enabled;
                    cb.closest("label").style.opacity = enabled ? "1" : "0.4";
                });
            }'
        );
    }

    // =========================================================================
    // Gutenberg sidebar panel
    // =========================================================================

    public function enqueue_block_editor_assets(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['post', 'page'], true)) return;

        $post_id  = (int) (isset($_GET['post']) ? $_GET['post'] : 0); // phpcs:ignore
        $has_key  = !empty($this->ai_opts['anthropic_key']) || !empty($this->ai_opts['gemini_key']);
        $nonce    = wp_create_nonce('cs_seo_nonce');
        $settings_url = esc_url(admin_url('options-general.php?page=cs-seo-optimizer#ai'));

        // Existing meta values passed to JS so the panel can pre-populate.
        $title    = $post_id ? (string) get_post_meta($post_id, self::META_TITLE,    true) : '';
        $desc     = $post_id ? (string) get_post_meta($post_id, self::META_DESC,     true) : '';
        $ogimg    = $post_id ? (string) get_post_meta($post_id, self::META_OGIMG,    true) : '';
        $sum_what = $post_id ? (string) get_post_meta($post_id, self::META_SUM_WHAT, true) : '';
        $sum_why  = $post_id ? (string) get_post_meta($post_id, self::META_SUM_WHY,  true) : '';
        $sum_key  = $post_id ? (string) get_post_meta($post_id, self::META_SUM_KEY,  true) : '';

        wp_register_script('cs-seo-block-panel', false, [
            'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'jquery'
        ], self::VERSION, true);
        wp_enqueue_script('cs-seo-block-panel');

        wp_localize_script('cs-seo-block-panel', 'csSeoPanel', [
            'postId'      => $post_id,
            'nonce'       => $nonce,
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'hasKey'      => $has_key,
            'settingsUrl' => $settings_url,
            'metaKeys'    => [
                'title'   => self::META_TITLE,
                'desc'    => self::META_DESC,
                'ogimg'   => self::META_OGIMG,
                'sumWhat' => self::META_SUM_WHAT,
                'sumWhy'  => self::META_SUM_WHY,
                'sumKey'  => self::META_SUM_KEY,
            ],
            'initial'     => [
                'title'   => $title,
                'desc'    => $desc,
                'ogimg'   => $ogimg,
                'sumWhat' => $sum_what,
                'sumWhy'  => $sum_why,
                'sumKey'  => $sum_key,
            ],
        ]);

        wp_add_inline_script('cs-seo-block-panel', $this->get_block_panel_js());
    }

    private function get_block_panel_js(): string {
        return <<<'JSCODE'
(function() {
    var cfg     = window.csSeoPanel || {};
    var el      = wp.element.createElement;
    var Panel   = wp.editPost.PluginDocumentSettingPanel;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var Spinner = wp.components.Spinner;

    function CsSeoPanel() {
        var meta = useSelect(function(select) {
            return select('core/editor').getEditedPostAttribute('meta') || {};
        });
        var editPost = useDispatch('core/editor').editPost;

        var keys    = cfg.metaKeys || {};
        var initial = cfg.initial  || {};

        var title   = meta[keys.title]   !== undefined ? meta[keys.title]   : (initial.title   || '');
        var desc    = meta[keys.desc]    !== undefined ? meta[keys.desc]    : (initial.desc    || '');
        var ogimg   = meta[keys.ogimg]   !== undefined ? meta[keys.ogimg]   : (initial.ogimg   || '');
        var sumWhat = meta[keys.sumWhat] !== undefined ? meta[keys.sumWhat] : (initial.sumWhat || '');
        var sumWhy  = meta[keys.sumWhy]  !== undefined ? meta[keys.sumWhy]  : (initial.sumWhy  || '');
        var sumKey  = meta[keys.sumKey]  !== undefined ? meta[keys.sumKey]  : (initial.sumKey  || '');

        var setMeta = function(key, val) {
            var patch = {};
            patch[key] = val;
            editPost({ meta: patch });
        };

        var descLen  = desc.length;
        var descColor = descLen >= 140 && descLen <= 160 ? '#46b450' : (descLen > 0 ? '#dc3232' : '#888');
        var descHint  = descLen > 0 ? descLen + ' chars' : 'No description set';

        var genStatus = useState('');
        var genLoading = useState(false);
        var sumStatus = useState('');
        var sumLoading = useState(false);

        function doGenDesc() {
            genLoading[1](true);
            genStatus[1]('⟳ Generating...');
            fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_ai_generate_one',
                    post_id: cfg.postId,
                    nonce: cfg.nonce
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var patch = {};
                    patch[keys.desc] = data.data.description;
                    editPost({ meta: patch });
                    genStatus[1]('✓ Done — save post to keep');
                } else {
                    genStatus[1]('✗ ' + (data.data || 'Error'));
                }
            })
            .catch(function(e) { genStatus[1]('✗ ' + e.message); })
            .finally(function() { genLoading[1](false); });
        }

        function doGenSummary(force) {
            sumLoading[1](true);
            sumStatus[1]('⟳ Generating...');
            fetch(cfg.ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_summary_generate_one',
                    post_id: cfg.postId,
                    force: force ? 1 : 0,
                    nonce: cfg.nonce
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.data.skipped) {
                        sumStatus[1]('✓ Already set — use Regenerate to overwrite');
                    } else {
                        var patch = {};
                        patch[keys.sumWhat] = data.data.what;
                        patch[keys.sumWhy]  = data.data.why;
                        patch[keys.sumKey]  = data.data.takeaway;
                        editPost({ meta: patch });
                        sumStatus[1]('✓ Done — save post to keep');
                    }
                } else {
                    sumStatus[1]('✗ ' + (data.data || 'Error'));
                }
            })
            .catch(function(e) { sumStatus[1]('✗ ' + e.message); })
            .finally(function() { sumLoading[1](false); });
        }

        return el(Panel,
            { name: 'cs-seo-panel', title: 'CloudScale Meta Boxes', icon: el('span', { style: { fontSize: '14px' } }, '🥷') },

            // SEO Title
            el('div', { style: { marginBottom: '12px' } },
                el('p', { style: { margin: '0 0 4px', fontWeight: '600', fontSize: '12px' } }, 'Custom SEO title'),
                el('p', { style: { margin: '0 0 4px', fontSize: '11px', color: '#888' } }, 'Leave blank to auto-generate'),
                el('input', {
                    className: 'widefat',
                    style: { width: '100%', fontSize: '12px' },
                    value: title,
                    onChange: function(e) { setMeta(keys.title, e.target.value); }
                })
            ),

            // Meta Description
            el('div', { style: { marginBottom: '12px' } },
                el('p', { style: { margin: '0 0 4px', fontWeight: '600', fontSize: '12px' } }, 'Meta description'),
                el('p', { style: { margin: '0 0 4px', fontSize: '11px', color: '#888' } }, 'Leave blank to use excerpt'),
                el('textarea', {
                    className: 'widefat',
                    rows: 3,
                    style: { width: '100%', fontSize: '12px', resize: 'vertical' },
                    value: desc,
                    onChange: function(e) { setMeta(keys.desc, e.target.value); }
                }),
                el('span', { style: { fontSize: '11px', color: descColor } }, descHint)
            ),

            // Generate desc button
            cfg.hasKey
                ? el('div', { style: { marginBottom: '16px', display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' } },
                    el(Button, { variant: 'secondary', isSmall: true, isBusy: genLoading[0], disabled: genLoading[0], onClick: doGenDesc }, '✦ Generate with AI'),
                    genStatus[0] ? el('span', { style: { fontSize: '11px', color: genStatus[0].startsWith('✓') ? '#46b450' : '#dc3232' } }, genStatus[0]) : null
                  )
                : el('p', { style: { fontSize: '11px', color: '#888', marginBottom: '16px' } },
                    'Add an API key in ',
                    el('a', { href: cfg.settingsUrl }, 'SEO Settings'),
                    ' to enable AI generation.'
                  ),

            // Divider
            el('hr', { style: { margin: '4px 0 12px', borderTop: '1px solid #ddd', border: 'none', borderTopStyle: 'solid', borderTopWidth: '1px', borderTopColor: '#ddd' } }),

            // OG Image
            el('div', { style: { marginBottom: '16px' } },
                el('p', { style: { margin: '0 0 4px', fontWeight: '600', fontSize: '12px' } }, 'OG image URL'),
                el('p', { style: { margin: '0 0 4px', fontSize: '11px', color: '#888' } }, 'Leave blank to use featured image'),
                el('input', {
                    className: 'widefat',
                    style: { width: '100%', fontSize: '12px' },
                    value: ogimg,
                    onChange: function(e) { setMeta(keys.ogimg, e.target.value); }
                })
            ),

            // Divider
            el('hr', { style: { margin: '4px 0 12px', border: 'none', borderTopStyle: 'solid', borderTopWidth: '1px', borderTopColor: '#ddd' } }),

            // AI Summary
            el('p', { style: { margin: '0 0 8px', fontWeight: '600', fontSize: '12px' } }, 'AI Summary Box'),
            el('p', { style: { margin: '0 0 8px', fontSize: '11px', color: '#888' } }, 'Shown at top of post for readers'),

            el('div', { style: { marginBottom: '8px' } },
                el('label', { style: { display: 'block', fontSize: '11px', fontWeight: '600', color: '#555', marginBottom: '3px' } }, 'What it is'),
                el('textarea', {
                    className: 'widefat',
                    rows: 2,
                    style: { width: '100%', fontSize: '12px', resize: 'vertical' },
                    value: sumWhat,
                    onChange: function(e) { setMeta(keys.sumWhat, e.target.value); }
                })
            ),
            el('div', { style: { marginBottom: '8px' } },
                el('label', { style: { display: 'block', fontSize: '11px', fontWeight: '600', color: '#555', marginBottom: '3px' } }, 'Why it matters'),
                el('textarea', {
                    className: 'widefat',
                    rows: 2,
                    style: { width: '100%', fontSize: '12px', resize: 'vertical' },
                    value: sumWhy,
                    onChange: function(e) { setMeta(keys.sumWhy, e.target.value); }
                })
            ),
            el('div', { style: { marginBottom: '8px' } },
                el('label', { style: { display: 'block', fontSize: '11px', fontWeight: '600', color: '#555', marginBottom: '3px' } }, 'Key takeaway'),
                el('textarea', {
                    className: 'widefat',
                    rows: 2,
                    style: { width: '100%', fontSize: '12px', resize: 'vertical' },
                    value: sumKey,
                    onChange: function(e) { setMeta(keys.sumKey, e.target.value); }
                })
            ),

            // Generate summary buttons
            cfg.hasKey
                ? el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', flexWrap: 'wrap' } },
                    el(Button, { variant: 'secondary', isSmall: true, isBusy: sumLoading[0], disabled: sumLoading[0], onClick: function() { doGenSummary(false); } }, '✦ Generate'),
                    el(Button, { variant: 'secondary', isSmall: true, isBusy: sumLoading[0], disabled: sumLoading[0], onClick: function() { doGenSummary(true); } }, '↺ Regenerate'),
                    sumStatus[0] ? el('span', { style: { fontSize: '11px', color: sumStatus[0].startsWith('✓') ? '#46b450' : '#dc3232', width: '100%' } }, sumStatus[0]) : null
                  )
                : el('p', { style: { fontSize: '11px', color: '#888', margin: '0' } },
                    'Add an API key in ',
                    el('a', { href: cfg.settingsUrl }, 'SEO Settings'),
                    ' to enable AI generation.'
                  )
        );
    }

    wp.domReady(function() {
        wp.plugins.registerPlugin('cs-seo-block-panel', {
            render: CsSeoPanel,
            icon: 'admin-generic'
        });
    });
})();
JSCODE;
    }

    /**
     * Render an "? Explain" button and its modal.
     * $id      — unique ID suffix, e.g. 'identity'
     * $title   — modal heading
     * $items   — array of ['rec' => '✅ Recommended', 'name' => '...', 'desc' => '...']
     *            rec values: '✅ Recommended' | '⬜ Optional' | 'ℹ️ Info'
     */
    private function explain_btn(string $id, string $title, array $items): void {
        $btn_id   = 'ab-explain-btn-' . $id;
        $modal_id = 'ab-explain-modal-' . $id;
        ?>
        <button type="button" id="<?php echo esc_attr($btn_id); ?>"
            onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='flex'"
            style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:12px;font-weight:600;padding:5px 14px;cursor:pointer">
            Explain...
        </button>
        <div id="<?php echo esc_attr($modal_id); ?>" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:16px">
            <div style="background:#fff;border-radius:10px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.4)">
                <div style="background:#1a4a7a;border-radius:10px 10px 0 0;padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
                    <strong style="color:#fff;font-size:15px"><?php echo esc_html($title); ?></strong>
                    <button type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='none'"
                        style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:16px;font-weight:700;padding:2px 10px;cursor:pointer;line-height:1">✕</button>
                </div>
                <div style="padding:20px 24px;font-size:13px;line-height:1.6;color:#1d2327">
                    <?php foreach ($items as $item):
                        $rec = $item['rec'];
                        $is_on  = strpos($rec, 'Recommended') !== false;
                        $is_opt = strpos($rec, 'Optional') !== false;
                        $bg  = $is_on ? '#edfaef' : ($is_opt ? '#f6f7f7' : '#f0f6fc');
                        $col = $is_on ? '#1a7a34' : ($is_opt ? '#50575e' : '#1a4a7a');
                        $bdr = $is_on ? '#1a7a34' : ($is_opt ? '#c3c4c7' : '#2271b1');
                    ?>
                    <div style="border:1px solid #e0e0e0;border-radius:6px;padding:12px 14px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;flex-wrap:wrap">
                            <strong style="font-size:13px"><?php echo esc_html($item['name']); ?></strong>
                            <span style="background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($col); ?>;border:1px solid <?php echo esc_attr($bdr); ?>;border-radius:4px;font-size:11px;font-weight:600;padding:1px 8px;white-space:nowrap"><?php echo esc_html($rec); ?></span>
                        </div>
                        <p style="margin:0;color:#50575e;font-size:12px;line-height:1.5"><?php echo esc_html($item['desc']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:12px 24px 20px;text-align:right">
                    <button type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='none'"
                        style="background:#1a4a7a;border:none;border-radius:6px;color:#fff;font-size:13px;font-weight:600;padding:8px 24px;cursor:pointer">
                        Got it
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    public function admin_footer_text($text): string {
        if (!$this->is_our_page()) return $text;
        return '';
    }

    public function admin_footer_version($text): string {
        if (!$this->is_our_page()) return $text;
        return '';
    }

    private function is_our_page(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not processing form data, only reading page slug for admin UI routing
        return isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'cs-seo-optimizer';
    }

    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'cs_seo_dashboard_widget',
            '🥷 AndrewBaker.Ninja AI SEO Optimizer <span style="font-size:11px;font-weight:400;color:#999;margin-left:6px">v' . self::VERSION . '</span>',
            [$this, 'render_dashboard_widget']
        );
    }

    public function render_dashboard_widget(): void {
        // ── Batch status line ─────────────────────────────────────────────────
        $ai_opts         = $this->get_ai_opts();
        $schedule_enabled = (int) ($ai_opts['schedule_enabled'] ?? 0);
        $history         = get_option('cs_seo_batch_history', []);
        $last_run        = (is_array($history) && !empty($history))
            ? $history[count($history) - 1]
            : null;

        if (!$schedule_enabled) {
            $batch_line  = '⏸ Batch disabled';
            $batch_style = 'background:linear-gradient(135deg,#6b7280 0%,#4b5563 100%);box-shadow:0 3px 10px rgba(107,114,128,0.35);';
        } elseif (!$last_run) {
            $batch_line  = '⏳ Batch pending';
            $batch_style = 'background:linear-gradient(135deg,#f59e0b 0%,#b45309 100%);box-shadow:0 3px 10px rgba(245,158,11,0.4);';
        } else {
            $date_fmt  = gmdate('d M y', strtotime($last_run['date'] ?? ''));
            $desc_done = (int) ($last_run['done']      ?? 0);
            $alt_done  = (int) ($last_run['alt_done']  ?? 0);
            $sum_done  = (int) ($last_run['sum_done']  ?? 0);
            $errors    = (int) ($last_run['errors']    ?? 0) + (int) ($last_run['alt_errors'] ?? 0);
            $batch_line = 'Batch: ' . $date_fmt . ' · ' . $desc_done . ' Posts and ' . $alt_done . ' Images';
            if ($sum_done > 0) $batch_line .= ' and ' . $sum_done . ' Summaries';
            if ($errors) $batch_line .= ' · ' . $errors . ' err';
            $batch_style = 'background:linear-gradient(135deg,#22c55e 0%,#15803d 100%);box-shadow:0 3px 10px rgba(34,197,94,0.4);';
        }
        ?>
        <div style="padding:4px 0 8px">
            <p style="margin:0 0 10px;font-size:13px;color:#50575e;line-height:1.5">
                CloudScale SEO AI Optimizer is keeping your site sharp —
                meta descriptions, ALT text, sitemaps, and render-blocking scripts all handled.
            </p>
            <div style="display:flex;flex-direction:column;gap:10px">
                <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer&tab=batch')); ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          <?php echo esc_attr($batch_style); ?>
                          color:#fff;font-weight:700;font-size:12px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <?php echo esc_html($batch_line); ?>
                </a>
                <a href="https://andrewbaker.ninja" target="_blank" rel="noopener"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          background:linear-gradient(135deg,#f953c6 0%,#b91d73 40%,#4f46e5 100%);
                          color:#fff;font-weight:700;font-size:13px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          box-shadow:0 3px 10px rgba(249,83,198,0.4);
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <span style="font-size:15px">🥷</span> Visit AndrewBaker.Ninja
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer')); ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          background:linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%);
                          color:#fff;font-weight:700;font-size:13px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          box-shadow:0 3px 10px rgba(14,165,233,0.35);
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <span style="font-size:15px">🔭</span> View SEO AI Optimizer
                </a>
            </div>
        </div>
    <?php }

    public function admin_menu(): void {
        add_management_page(
            'CloudScale SEO AI Optimizer v' . self::VERSION,
            'CloudScale SEO AI',
            'manage_options',
            'cs-seo-optimizer',
            [$this, 'settings_page']
        );
    }

    public function register_settings(): void {
        register_setting('cs_seo_group', self::OPT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_opts'],
            'default'           => self::defaults(),
        ]);
        register_setting('cs_seo_ai_group', self::AI_OPT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_ai_opts'],
            'default'           => self::ai_defaults(),
        ]);
    }

    public function sanitize_opts($in): array {
        $in  = is_array($in) ? $in : [];
        $d   = self::defaults();

        // If the incoming data has no recognisable fields it's likely a spurious
        // call (e.g. plugin reinstall touching the option). Preserve existing data.
        $known_fields = ['site_name','enable_og','robots_txt','sitemap_post_types','enable_sitemap','enable_llms_txt',
                         'home_title','person_name','block_ai_bots','noindex_search','title_suffix','defer_js'];
        $has_known = false;
        foreach ($known_fields as $f) {
            if (array_key_exists($f, $in)) { $has_known = true; break; }
        }
        if (!$has_known) {
            return $this->opts ?: $d;
        }

        $out = [];

        // Merge with existing saved values — partial form submissions (e.g. Sitemap tab only)
        // must not wipe fields that live on other tabs.
        $existing = $this->opts ?: $d;

        $was_sitemap  = (int)($existing['enable_sitemap'] ?? 0);
        $now_sitemap  = array_key_exists('enable_sitemap', $in) ? (empty($in['enable_sitemap']) ? 0 : 1) : $was_sitemap;
        $was_llms     = (int)($existing['enable_llms_txt'] ?? 0);
        $now_llms     = array_key_exists('enable_llms_txt', $in) ? (empty($in['enable_llms_txt']) ? 0 : 1) : $was_llms;
        if ($now_sitemap !== $was_sitemap || $now_llms !== $was_llms) {
            add_action('shutdown', 'flush_rewrite_rules');
        }
        foreach (['site_name','site_lang','title_suffix','home_title','twitter_handle','person_name','person_job_title'] as $k) {
            $out[$k] = sanitize_text_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['home_desc','default_desc','sameas','robots_txt','sitemap_exclude','defer_js_excludes'] as $k) {
            $out[$k] = sanitize_textarea_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['default_og_image','person_url','person_image'] as $k) {
            $out[$k] = esc_url_raw(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach ([
            'enable_og','enable_schema_person','enable_schema_website','enable_schema_article',
            'enable_schema_breadcrumbs','show_summary_box','strip_tracking_params','enable_sitemap','enable_llms_txt',
            'noindex_search','noindex_404','noindex_attachment','noindex_author_archives','noindex_tag_archives',
            'block_ai_bots','sitemap_taxonomies','defer_js','minify_html','defer_fonts',
        ] as $k) {
            $out[$k] = array_key_exists($k, $in) ? (empty($in[$k]) ? 0 : 1) : (int)($existing[$k] ?? $d[$k]);
        }
        // Sitemap post types — array of sanitized strings
        $allowed_types = array_map(fn($pt) => $pt->name, get_post_types(['public' => true], 'objects'));
        if (array_key_exists('sitemap_post_types', $in)) {
            $chosen = array_intersect((array)$in['sitemap_post_types'], $allowed_types);
            $out['sitemap_post_types'] = array_values($chosen) ?: ['post'];
        } else {
            $out['sitemap_post_types'] = $existing['sitemap_post_types'] ?? $d['sitemap_post_types'];
        }
        return $out;
    }

    public function sanitize_ai_opts($in): array {
        $in      = is_array($in) ? $in : [];
        $d       = self::ai_defaults();
        $current = $this->get_ai_opts(); // existing saved values — preserve anything not in $in

        $days = array_intersect(
            (array)($in['schedule_days'] ?? $current['schedule_days'] ?? []),
            ['sun','mon','tue','wed','thu','fri','sat']
        );
        $was_enabled = (int) $current['schedule_enabled'];
        $now_enabled = array_key_exists('schedule_enabled', $in) ? (empty($in['schedule_enabled']) ? 0 : 1) : $was_enabled;

        // Schedule cron when enabled, unschedule when disabled.
        if ($now_enabled && !$was_enabled) {
            if (!wp_next_scheduled('cs_seo_daily_batch')) {
                wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'cs_seo_daily_batch');
            }
        } elseif (!$now_enabled && $was_enabled) {
            wp_clear_scheduled_hook('cs_seo_daily_batch');
        }

        // Use submitted value if present, otherwise fall back to current saved value, then default.
        return [
            'ai_provider'      => in_array($in['ai_provider'] ?? $current['ai_provider'] ?? 'anthropic', ['anthropic','gemini'], true) ? ($in['ai_provider'] ?? $current['ai_provider'] ?? 'anthropic') : 'anthropic',
            'anthropic_key'    => sanitize_text_field((string)(array_key_exists('anthropic_key', $in) ? $in['anthropic_key'] : $current['anthropic_key'])),
            'gemini_key'       => sanitize_text_field((string)(array_key_exists('gemini_key', $in) ? $in['gemini_key'] : ($current['gemini_key'] ?? ''))),
            'model'            => sanitize_text_field((string)($in['model'] ?? $current['model'] ?? $d['model'])),
            'overwrite'        => array_key_exists('overwrite', $in) ? (empty($in['overwrite']) ? 0 : 1) : ($current['overwrite'] ?? 0),
            'min_chars'        => max(100, min(160, (int)($in['min_chars'] ?? $current['min_chars'] ?? $d['min_chars']))),
            'max_chars'        => max(100, min(200, (int)($in['max_chars'] ?? $current['max_chars'] ?? $d['max_chars']))),
            'alt_excerpt_chars'=> max(100, min(2000, (int)($in['alt_excerpt_chars'] ?? $current['alt_excerpt_chars'] ?? $d['alt_excerpt_chars']))),
            'prompt'           => sanitize_textarea_field((string)($in['prompt'] ?? $current['prompt'] ?? $d['prompt'])),
            'schedule_enabled' => $now_enabled,
            'schedule_days'    => array_values($days),
        ];
    }

    // =========================================================================
    // Font Display Optimization - Scanner & Fixer
    // =========================================================================

    private function scan_enqueued_css(): array {
        global $wp_styles;
        self::debug_log('[CloudScale SEO] Font Display Scan: Initializing CSS file scanner');
        
        $results = [
            'total_files' => 0, 'total_fonts' => 0, 'missing_fonts' => 0,
            'files' => [], 'total_savings_ms' => 0,
        ];
        if (!$wp_styles || !isset($wp_styles->queue)) {
            self::debug_log('[CloudScale SEO] Font Display Scan: No styles queue found');
            return $results;
        }
        
        $savings_map = [
            'Montserrat' => ['400' => 1820, '700' => 780, 'italic' => 760],
            'Merriweather' => ['400' => 1000, '700' => 780, '400i' => 760, '700i' => 730],
        ];
        
        self::debug_log('[CloudScale SEO] Font Display Scan: Scanning ' . count($wp_styles->queue) . ' stylesheets');
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                self::debug_log('[CloudScale SEO] Font Display Scan: Handle not registered: ' . $handle);
                continue;
            }
            $src = $wp_styles->registered[$handle]->src;
            if (!$src) {
                self::debug_log('[CloudScale SEO] Font Display Scan: No src for handle: ' . $handle);
                continue;
            }
            
            $file_path = $this->resolve_css_path($src);
            if (!$file_path || !file_exists($file_path)) {
                self::debug_log('[CloudScale SEO] Font Display Scan: File not found or unresolvable: ' . $src);
                continue;
            }
            
            self::debug_log('[CloudScale SEO] Font Display Scan: Processing file: ' . basename($file_path));
            
            $css_content = @file_get_contents($file_path);
            if (!$css_content) {
                self::debug_log('[CloudScale SEO] Font Display Scan: Cannot read file: ' . $file_path);
                continue;
            }
            
            $file_info = [
                'handle' => $handle, 'url' => $src, 'path' => $file_path,
                'writable' => wp_is_writable(dirname($file_path)), 'fonts' => [],
                'missing_count' => 0, 'total_savings' => 0,
            ];
            
            if (preg_match_all('/@font-face\s*\{([^}]+)\}/i', $css_content, $matches)) {
                self::debug_log('[CloudScale SEO] Font Display Scan: Found ' . count($matches[0]) . ' @font-face blocks in ' . basename($file_path));
                
                foreach ($matches[0] as $idx => $full_block) {
                    $block = $matches[1][$idx];
                    $family = 'Unknown';
                    if (preg_match('/font-family\s*:\s*[\'"]?([^\'";\n]+)/i', $block, $m)) {
                        $family = trim($m[1], '\'"');
                    }
                    $weight = '400';
                    if (preg_match('/font-weight\s*:\s*(\d+|bold|normal)/i', $block, $m)) {
                        $weight = trim($m[1]);
                        if ($weight === 'normal') $weight = '400';
                        if ($weight === 'bold') $weight = '700';
                    }
                    $style = 'normal';
                    if (preg_match('/font-style\s*:\s*(\w+)/i', $block, $m)) {
                        $style = trim($m[1]);
                    }
                    $has_display = strpos($block, 'font-display') !== false;
                    $savings = 0;
                    foreach ($savings_map as $fname => $weights) {
                        if (stripos($family, $fname) !== false) {
                            $key = $style === 'italic' ? $weight . 'i' : $weight;
                            $savings = $weights[$key] ?? $weights[$weight] ?? 0;
                            break;
                        }
                    }
                    
                    $font_status = $has_display ? 'has font-display' : 'MISSING font-display';
                    self::debug_log('[CloudScale SEO] Font Display Scan:   • ' . $family . ' ' . $weight . '/' . $style . ' (' . $font_status . ', ' . $savings . 'ms potential)');
                    
                    $file_info['fonts'][] = [
                        'family' => $family, 'weight' => $weight, 'style' => $style,
                        'has_display' => $has_display, 'savings_ms' => $has_display ? 0 : $savings,
                    ];
                    if (!$has_display) {
                        $file_info['missing_count']++;
                        $file_info['total_savings'] += $savings;
                        $results['total_savings_ms'] += $savings;
                    }
                }
            // Add to results regardless of missing fonts
            if (count($file_info['fonts']) > 0) {
                // Only add files that have fonts (even if all optimized)
                if ($file_info['missing_count'] > 0) {
                    $results['total_files']++;
                    $results['missing_fonts'] += $file_info['missing_count'];
                    self::debug_log('[CloudScale SEO] Font Display Scan: File needs fixing: ' . basename($file_path) . ' (' . $file_info['missing_count'] . ' fonts)');
                } else {
                    // File has fonts but all are optimized - still track it
                    self::debug_log('[CloudScale SEO] Font Display Scan: File optimized: ' . basename($file_path) . ' (' . count($file_info['fonts']) . ' fonts already have font-display)');
                }
                $results['files'][] = $file_info;
            }
            }
            $results['total_fonts'] += count($file_info['fonts']);
        }
        
        self::debug_log('[CloudScale SEO] Font Display Scan: Complete - ' . $results['total_files'] . ' files need fixing, ' . $results['missing_fonts'] . ' fonts total, ' . $results['total_savings_ms'] . 'ms potential savings');
        return $results;
    }

    private function resolve_css_path(string $src): ?string {
        if (strpos($src, ABSPATH) === 0) return $src;
        $site_url = home_url('/');
        $content_url = content_url('/');
        if (strpos($src, $content_url) === 0) {
            $rel_path = str_replace($content_url, '', $src);
            return WP_CONTENT_DIR . '/' . $rel_path;
        }
        if (strpos($src, $site_url) === 0) {
            $rel_path = str_replace($site_url, '', $src);
            return ABSPATH . $rel_path;
        }
        if ($src[0] === '/') return ABSPATH . ltrim($src, '/');
        return null;
    }

    private function fix_css_fonts(string $file_path, string $display_value = 'swap', bool $add_metrics = true): array {
        self::debug_log('[CloudScale SEO] Font Fix: Starting fix for ' . basename($file_path) . ' with display=' . $display_value . ', metrics=' . ($add_metrics ? 'yes' : 'no'));
        
        $original = @file_get_contents($file_path);
        if (!$original) {
            self::debug_log('[CloudScale SEO] Font Fix ERROR: Cannot read file ' . $file_path);
            return ['success' => false, 'error' => 'Cannot read file'];
        }
        
        $backup_key = 'cs_seo_font_backup_' . md5($file_path);
        self::debug_log('[CloudScale SEO] Font Fix: Creating backup with key ' . $backup_key);
        
        update_option($backup_key, [
            'file_path' => $file_path, 'content' => $original, 'date' => current_time('mysql'),
        ]);
        
        $patched = $this->patch_font_face_blocks($original, $display_value, $add_metrics);
        if ($patched === $original) {
            self::debug_log('[CloudScale SEO] Font Fix WARNING: No changes detected in ' . basename($file_path));
            return ['success' => false, 'error' => 'No changes needed'];
        }
        
        $written = @file_put_contents($file_path, $patched);
        if (!$written) {
            self::debug_log('[CloudScale SEO] Font Fix ERROR: Cannot write file ' . $file_path . ' (permission denied)');
            delete_option($backup_key);
            return ['success' => false, 'error' => 'Cannot write file (permission denied)'];
        }
        
        self::debug_log('[CloudScale SEO] Font Fix: Successfully wrote changes to ' . basename($file_path));
        wp_cache_flush();
        if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
        
        self::debug_log('[CloudScale SEO] Font Fix: Backup saved. Can be restored with backup key: ' . $backup_key);
        return ['success' => true, 'file_path' => $file_path, 'backup_key' => $backup_key, 'changed' => true];
    }

    private function patch_font_face_blocks(string $css, string $display_value = 'swap', bool $add_metrics = true): string {
        self::debug_log('[CloudScale SEO] Font Patch: Starting patch with display=' . $display_value . ', metrics=' . ($add_metrics ? 'yes' : 'no'));
        
        $patched_count = 0;
        $result = preg_replace_callback('/@font-face\s*\{([^}]+)\}/i', function($matches) use ($display_value, $add_metrics, &$patched_count) {
            $block = $matches[1];
            
            if (strpos($block, 'font-display') !== false) {
                self::debug_log('[CloudScale SEO] Font Patch: Skipping block with existing font-display');
                return $matches[0];
            }
            
            $patched_count++;
            self::debug_log('[CloudScale SEO] Font Patch: Patching block #' . $patched_count);
            
            // Add font-display after the first property (more reliable)
            // If font-style exists, add after it; otherwise add after font-family or src
            if (preg_match('/(font-style\s*:\s*[^;]+;)/i', $block)) {
                $block = preg_replace('/(font-style\s*:\s*[^;]+;)/i', '$1' . "\n    font-display: $display_value;", $block);
            } elseif (preg_match('/(font-weight\s*:\s*[^;]+;)/i', $block)) {
                $block = preg_replace('/(font-weight\s*:\s*[^;]+;)/i', '$1' . "\n    font-display: $display_value;", $block);
            } else {
                // Fallback: add before the closing brace
                $block = rtrim($block, ';') . ";\n    font-display: $display_value;";
            }
            
            if ($add_metrics && strpos($block, 'ascent-override') === false) {
                self::debug_log('[CloudScale SEO] Font Patch: Adding metric overrides to block #' . $patched_count);
                $block .= "\n    ascent-override: 108%;\n    descent-override: 27%;\n    line-gap-override: 0%;";
            }
            return "@font-face {" . $block . "}";
        }, $css);
        
        self::debug_log('[CloudScale SEO] Font Patch: Complete - patched ' . $patched_count . ' @font-face blocks');
        return $result;
    }

    private function undo_font_fixes(string $file_path): array {
        self::debug_log('[CloudScale SEO] Font Undo: Attempting to restore ' . basename($file_path));
        
        $backup_key = 'cs_seo_font_backup_' . md5($file_path);
        $backup = get_option($backup_key);
        if (!$backup) {
            self::debug_log('[CloudScale SEO] Font Undo ERROR: No backup found for key ' . $backup_key);
            return ['success' => false, 'error' => 'No backup found'];
        }
        
        self::debug_log('[CloudScale SEO] Font Undo: Found backup from ' . ($backup['date'] ?? 'unknown date'));
        
        $written = @file_put_contents($file_path, $backup['content']);
        if (!$written) {
            self::debug_log('[CloudScale SEO] Font Undo ERROR: Cannot write file ' . $file_path . ' (permission denied)');
            return ['success' => false, 'error' => 'Cannot write file'];
        }
        
        delete_option($backup_key);
        wp_cache_flush();
        
        self::debug_log('[CloudScale SEO] Font Undo: Successfully restored ' . basename($file_path) . ' and deleted backup');
        return ['success' => true];
    }

    /**
     * Defer font CSS loading to prevent render-blocking
     * Loads fonts asynchronously so they don't block initial page render
     */
    public function defer_font_css(string $html, string $handle): string {
        // List of font-related stylesheet handles to defer
        $font_handles = [
            'twentysixteen-fonts',      // Twenty Sixteen theme fonts
            'custom-fonts',             // Generic custom fonts
            'google-fonts',             // Google Fonts
            'local-fonts',              // Locally hosted fonts
        ];
        
        // Check if this is a font stylesheet
        $is_font = false;
        foreach ($font_handles as $font_handle) {
            if (strpos($handle, $font_handle) !== false) {
                $is_font = true;
                break;
            }
        }
        
        // Also check if the handle contains 'font' or 'merriweather' or 'montserrat'
        if (!$is_font && (strpos($handle, 'font') !== false || strpos($handle, 'merriweather') !== false || strpos($handle, 'montserrat') !== false)) {
            $is_font = true;
        }
        
        if (!$is_font) {
            return $html;
        }
        
        // Check if it's already deferred/async
        if (strpos($html, 'media=') !== false && strpos($html, 'print') !== false) {
            return $html; // Already deferred
        }
        
        // Defer the font loading: load as print media, then switch to all via JS
        // This prevents render-blocking while still loading the fonts
        $html = str_replace(
            "media='all'",
            "media='print' onload=\"this.media='all'\"",
            $html
        );
        
        // Add noscript fallback for users without JavaScript
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- This is a noscript fallback for an already-enqueued style
        $html .= '<noscript><link rel="stylesheet" href="' . 
                 preg_match('/href=["\']([^"\']+)["\']/', $html, $m) ? $m[1] : '' . 
                 '" /></noscript>';
        
        self::debug_log('[CloudScale SEO] Deferred font CSS: ' . $handle);
        
        return $html;
    }

    /**
     * Auto-detect and download Google Fonts from CDN to local
     */
    public function ajax_download_fonts(): void {
        try {
            self::debug_log('[CloudScale SEO] Font Download: Starting auto-download process');
            
            check_ajax_referer('cs_seo_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }
            
            global $wp_styles;
            if (!$wp_styles || !isset($wp_styles->queue)) {
                wp_send_json_error(['message' => 'No CSS files found']);
            }
            
            $downloaded = 0;
            $messages = [];
            
            foreach ($wp_styles->queue as $handle) {
                if (!isset($wp_styles->registered[$handle])) continue;
                
                $src = $wp_styles->registered[$handle]->src ?? '';
                if (empty($src)) continue;
                
                // Check if it's a Google Fonts URL
                if (strpos($src, 'fonts.googleapis.com') !== false || strpos($src, 'fonts.gstatic.com') !== false) {
                    self::debug_log('[CloudScale SEO] Font Download: Found Google Fonts URL: ' . $src);
                    $messages[] = 'Detected: ' . basename($src);
                    
                    // Download the CSS file
                    $response = wp_remote_get($src, ['sslverify' => false, 'timeout' => 30]);
                    
                    if (is_wp_error($response)) {
                        self::debug_log('[CloudScale SEO] Font Download ERROR: ' . $response->get_error_message());
                        continue;
                    }
                    
                    $css_content = wp_remote_retrieve_body($response);
                    if (empty($css_content)) {
                        self::debug_log('[CloudScale SEO] Font Download ERROR: Empty response');
                        continue;
                    }
                    
                    // Create fonts directory if it doesn't exist
                    $fonts_dir = WP_CONTENT_DIR . '/fonts';
                    if (!file_exists($fonts_dir)) {
                        wp_mkdir_p($fonts_dir);
                        self::debug_log('[CloudScale SEO] Font Download: Created fonts directory');
                    }
                    
                    // Save CSS file with optimizations
                    $css_file = $fonts_dir . '/' . sanitize_file_name(basename($src));
                    
                    // Add font-display and metric overrides to all @font-face blocks
                    $optimized_css = preg_replace_callback(
                        '/@font-face\s*\{([^}]+)\}/i',
                        function($matches) {
                            $block = $matches[1];
                            // Add font-display if missing
                            if (strpos($block, 'font-display') === false) {
                                $block = preg_replace(
                                    '/(font-style\s*:\s*[^;]+;)/i',
                                    '$1' . "\n    font-display: swap;",
                                    $block
                                );
                            }
                            // Add metric overrides if missing
                            if (strpos($block, 'ascent-override') === false) {
                                $block .= "\n    ascent-override: 108%;\n    descent-override: 27%;\n    line-gap-override: 0%;";
                            }
                            return "@font-face {" . $block . "}";
                        },
                        $css_content
                    );
                    
                    // Write to local file
                    if (file_put_contents($css_file, $optimized_css, LOCK_EX)) {
                        self::debug_log('[CloudScale SEO] Font Download: Successfully saved ' . $css_file);
                        $messages[] = '✓ Downloaded: ' . basename($css_file);
                        $downloaded++;
                    } else {
                        self::debug_log('[CloudScale SEO] Font Download ERROR: Cannot write file ' . $css_file);
                        $messages[] = '✗ Failed to save: ' . basename($css_file);
                    }
                }
            }
            
            if ($downloaded > 0) {
                self::debug_log('[CloudScale SEO] Font Download: Complete - downloaded ' . $downloaded . ' file(s)');
                $messages[] = '';
                $messages[] = '✓ Fonts downloaded successfully!';
                $messages[] = '✓ Font-display and metric overrides added automatically';
                $messages[] = '✓ Next step: Run "Scan CSS Files" again to verify';
                wp_send_json(['success' => true, 'downloaded' => $downloaded, 'messages' => $messages]);
            } else {
                self::debug_log('[CloudScale SEO] Font Download: No Google Fonts found to download');
                $messages[] = '';
                $messages[] = 'ℹ No Google Fonts CDN URLs detected';
                wp_send_json(['success' => false, 'message' => 'No fonts to download', 'messages' => $messages]);
            }
            
        } catch (Exception $e) {
            self::debug_log('[CloudScale SEO] Font Download Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_font_scan(): void {
        self::debug_log('[CloudScale SEO] AJAX Handler: font_scan started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_scan - permission denied');
            wp_die();
        }
        
        $results = $this->scan_enqueued_css();
        $console_lines = [];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => 'FONT-DISPLAY OPTIMIZATION SCANNER'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        if ($results['total_files'] === 0) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_scan - checking results');
            
            // Show summary first
            $total_checked = count($results['files']);
            $console_lines[] = ['type' => 'info', 'text' => sprintf('✓ Scanned %d CSS file(s) total', $total_checked)];
            $console_lines[] = ['type' => 'info', 'text' => sprintf('✓ Found %d @font-face block(s) overall', $results['total_fonts'])];
            
            if (count($results['files']) > 0) {
                $console_lines[] = ['type' => 'ok', 'text' => '✓ All fonts already have font-display property'];
                $console_lines[] = ['type' => 'info', 'text' => ''];
                $console_lines[] = ['type' => 'info', 'text' => 'OPTIMIZED FONTS:'];
                $console_lines[] = ['type' => 'info', 'text' => ''];
                
                // Show all fonts with details
                foreach ($results['files'] as $file) {
                    $console_lines[] = ['type' => 'info', 'text' => '📄 ' . basename($file['path'])];
                    if (!empty($file['fonts'])) {
                        foreach ($file['fonts'] as $font) {
                            $console_lines[] = ['type' => 'ok', 'text' => sprintf('  ✓ %s %s/%s - has font-display', $font['family'], $font['weight'], $font['style'])];
                        }
                    } else {
                        $console_lines[] = ['type' => 'skip', 'text' => '  (No fonts found in this file)'];
                    }
                    $console_lines[] = ['type' => 'info', 'text' => ''];
                }
            } else {
                $console_lines[] = ['type' => 'warn', 'text' => 'ℹ No @font-face blocks found in any CSS files'];
                $console_lines[] = ['type' => 'skip', 'text' => 'This means either:'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • No fonts are loaded in WordPress'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • Fonts are loaded from external CDN (not locally)'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • Font CSS files aren\'t registered with WordPress'];
            }
            
            self::debug_log('[CloudScale SEO] AJAX Handler: font_scan - all fonts optimized or no fonts found');
            wp_send_json(['success' => true, 'console' => $console_lines, 'findings' => $results]);
        }
        
        self::debug_log('[CloudScale SEO] AJAX Handler: font_scan - found ' . $results['total_files'] . ' files needing fixes');
        
        $console_lines[] = ['type' => 'warn', 'text' => sprintf('⚠ Found %d file(s) with %d font(s) missing font-display', $results['total_files'], $results['missing_fonts'])];
        $console_lines[] = ['type' => 'warn', 'text' => sprintf('⚠ Potential PageSpeed savings: %d ms', $results['total_savings_ms'])];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        $console_lines[] = ['type' => 'info', 'text' => 'SCANNING FILES:'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        foreach ($results['files'] as $file) {
            $writable_status = $file['writable'] ? '✓' : '✗';
            $console_lines[] = ['type' => 'info', 'text' => sprintf('%s %s', $writable_status, basename($file['path']))];
            foreach ($file['fonts'] as $font) {
                $status = $font['has_display'] ? '✓ has' : '✗ missing';
                $savings = $font['savings_ms'] > 0 ? ' (' . $font['savings_ms'] . 'ms)' : '';
                $console_lines[] = ['type' => $font['has_display'] ? 'ok' : 'err', 'text' => sprintf('  • %s %s %s/%s%s', $status, $font['family'], $font['weight'], $font['style'], $savings)];
            }
            $console_lines[] = ['type' => 'info', 'text' => ''];
        }
        
        $console_lines[] = ['type' => 'info', 'text' => 'WHAT WILL BE FIXED:'];
        $console_lines[] = ['type' => 'skip', 'text' => 'Each @font-face block missing font-display will be patched with:'];
        $console_lines[] = ['type' => 'skip', 'text' => ''];
        $console_lines[] = ['type' => 'skip', 'text' => '  font-display: swap;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  ascent-override: 108%;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  descent-override: 27%;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  line-gap-override: 0%;'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        self::debug_log('[CloudScale SEO] AJAX Handler: font_scan complete, sending response');
        wp_send_json(['success' => true, 'console' => $console_lines, 'findings' => $results]);
    }

    public function ajax_font_fix(): void {
        self::debug_log('[CloudScale SEO] AJAX Handler: font_fix started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_fix - permission denied');
            wp_die();
        }
        
        $results = $this->scan_enqueued_css();
        $console_lines = [];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => 'FONT-DISPLAY OPTIMIZATION: AUTO-FIX'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        $fixed_count = 0;
        $failed_count = 0;
        
        self::debug_log('[CloudScale SEO] AJAX Handler: font_fix - processing ' . count($results['files']) . ' files');
        
        foreach ($results['files'] as $file) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_fix - processing ' . basename($file['path']));
            
            $console_lines[] = ['type' => 'info', 'text' => 'Processing: ' . basename($file['path'])];
            if (!$file['writable']) {
                self::debug_log('[CloudScale SEO] AJAX Handler: font_fix ERROR - ' . basename($file['path']) . ' not writable');
                $console_lines[] = ['type' => 'err', 'text' => '  ✗ ERROR: File not writable (permission denied)'];
                $failed_count++;
                continue;
            }
            
            $fix_result = $this->fix_css_fonts($file['path'], 'swap', true);
            if ($fix_result['success']) {
                self::debug_log('[CloudScale SEO] AJAX Handler: font_fix SUCCESS - fixed ' . $file['missing_count'] . ' fonts in ' . basename($file['path']));
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Fixed ' . $file['missing_count'] . ' @font-face block(s)'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Added font-display: swap'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Added metric overrides (CLS prevention)'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Backup created (can undo)'];
                $fixed_count++;
            } else {
                self::debug_log('[CloudScale SEO] AJAX Handler: font_fix ERROR - ' . $fix_result['error']);
                $console_lines[] = ['type' => 'err', 'text' => '  ✗ ERROR: ' . $fix_result['error']];
                $failed_count++;
            }
            $console_lines[] = ['type' => 'info', 'text' => ''];
        }
        
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'ok', 'text' => sprintf('✓ Fixed: %d file(s)', $fixed_count)];
        if ($failed_count > 0) {
            $console_lines[] = ['type' => 'err', 'text' => sprintf('✗ Failed: %d file(s)', $failed_count)];
        }
        $console_lines[] = ['type' => 'warn', 'text' => '⚠ Estimated savings: ' . $results['total_savings_ms'] . 'ms on first page load'];
        $console_lines[] = ['type' => 'skip', 'text' => 'Next: Run a PageSpeed test to verify improvement'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        
        self::debug_log('[CloudScale SEO] AJAX Handler: font_fix complete - fixed ' . $fixed_count . ', failed ' . $failed_count);
        wp_send_json(['success' => true, 'console' => $console_lines, 'fixed' => $fixed_count, 'failed' => $failed_count]);
    }

    public function ajax_font_undo(): void {
        self::debug_log('[CloudScale SEO] AJAX Handler: font_undo started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_undo - permission denied');
            wp_die();
        }
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';
        if (!$file_path) {
            self::debug_log('[CloudScale SEO] AJAX Handler: font_undo - no file path provided');
            wp_send_json(['success' => false, 'error' => 'No file specified']);
        }
        
        self::debug_log('[CloudScale SEO] AJAX Handler: font_undo - restoring ' . basename($file_path));
        $result = $this->undo_font_fixes($file_path);
        wp_send_json($result);
    }

    // =========================================================================
    // Settings Page
    // =========================================================================

    public function settings_page(): void {
        if (!current_user_can('manage_options')) return;
        $o     = $this->opts;
        $ai    = $this->ai_opts;
        $nonce = wp_create_nonce('cs_seo_nonce');
        ?>
        <div class="wrap">
        <h1>CloudScale SEO AI Optimizer <span style="font-size:13px;font-weight:400;color:#999;margin-left:6px">v<?php echo esc_html(self::VERSION); ?></span></h1>
        <a href="https://andrewbaker.ninja" target="_blank" rel="noopener" style="
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:linear-gradient(135deg, #f953c6 0%, #b91d73 40%, #4f46e5 100%);
            color:#fff;
            font-weight:700;
            font-size:13px;
            padding:8px 18px;
            border-radius:20px;
            text-decoration:none;
            margin-bottom:18px;
            box-shadow:0 3px 10px rgba(249,83,198,0.45);
            letter-spacing:0.03em;
            transition:filter 0.15s, transform 0.15s;
        " onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.03)'"
           onmouseout="this.style.filter='';this.style.transform=''">
            <span style="font-size:16px">🥷</span> Totally Free by AndrewBaker.Ninja
        </a>

        <?php /* ── TAB NAV ── */ ?>
        <style>
            .ab-tabs {
                display:flex; gap:6px; margin:20px 0 0; padding:0;
                border-bottom:3px solid #1d2327;
            }
            .ab-tab {
                padding:10px 22px; cursor:pointer;
                border:none; border-radius:6px 6px 0 0;
                font-size:13px; font-weight:600; letter-spacing:0.01em;
                background:#e0e0e0; color:#50575e;
                transition:background 0.15s, color 0.15s;
                margin-bottom:0; position:relative; bottom:-1px;
            }
            .ab-tab:hover:not(.active) { background:#c3c4c7; color:#1d2327; }
            .ab-tab[data-tab="seo"].active    { background:#2271b1; color:#fff; }
            .ab-tab[data-tab="sitemap"].active { background:#1a7a34; color:#fff; }
            .ab-tab[data-tab="batch"].active  { background:#e67e00; color:#fff; }
            .ab-tab[data-tab="perf"].active   { background:#d946a6; color:#fff; }
            .ab-pane { display:none; padding-top:24px; }
            .ab-pane.active { display:block; }
            /* AI Writer styles */
            #ab-ai-writer { font-family: -apple-system, sans-serif; }
            .ab-ai-toolbar { display:flex; gap:10px; align-items:center; margin-bottom:16px; flex-wrap:wrap; }
            #ab-log { background:#1a1a2e; color:#a8b4c8; font-family:'Courier New',monospace;
                      font-size:12px; padding:14px; border-radius:6px; max-height:260px;
                      overflow-y:auto; margin:16px 0; display:none; border:1px solid #2a2a4a; }
            #ab-log.visible { display:block; }
            #ab-alt-log { background:#1a1a2e; color:#a8b4c8; font-family:'Courier New',monospace;
                          font-size:12px; padding:14px; border-radius:6px; max-height:260px;
                          overflow-y:auto; margin:8px 0; border:1px solid #2a2a4a;
                          display:none; }
            .ab-log-line { color:#ffffff; margin-bottom:2px; }
            .ab-log-ok   { color:#00d084; }
            .ab-log-err  { color:#ff6b6b; }
            .ab-log-skip { color:#f0c040; }
            .ab-log-warn { color:#f0a040; }
            .ab-log-info { color:#8080b0; }
            .ab-progress { background:#f0f0f1; border-radius:4px; height:8px; margin:8px 0 4px; overflow:hidden; display:none; }
            .ab-progress.visible { display:block; }
            .ab-progress-fill { height:100%; background:#2271b1; border-radius:4px; transition:width 0.3s; width:0%; }
            .ab-stats { font-size:12px; color:#50575e; margin-bottom:12px; }
            .ab-stat-val { font-weight:600; color:#1d2327; }
            table.ab-posts { width:100%; border-collapse:collapse; margin-top:12px; }
            table.ab-posts th { text-align:left; padding:8px 10px; border-bottom:2px solid #c3c4c7;
                                font-size:12px; color:#50575e; font-weight:600; }
            table.ab-posts td { padding:9px 10px; border-bottom:1px solid #f0f0f1; font-size:13px; vertical-align:top; }
            table.ab-posts tr:hover td { background:#f6f7f7; }
            .ab-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
            .ab-badge-none   { background:#f0e8fb; color:#4a1a7a; border:1px solid #c4b2e0; }
            .ab-badge-ok     { background:#edfaef; color:#1a7a34; border:1px solid #b2dfc0; }
            .ab-badge-short  { background:#fcf9e8; color:#7a5c00; border:1px solid #f0d676; }
            .ab-badge-long   { background:#fcf0ef; color:#8a2424; border:1px solid #f5bcbb; }
            .ab-badge-gen    { background:#e8f3fb; color:#1a4a7a; border:1px solid #b2cfe0; }
            .ab-badge-gen-short { background:#f0e8fb; color:#4a1a7a; border:1px solid #c4b2e0; }
            .ab-badge-gen-long  { background:#fcf0ef; color:#8a2424; border:1px solid #f5bcbb; }
            .ab-desc-text { font-size:12px; color:#50575e; margin-top:3px; line-height:1.4; word-wrap:break-word; white-space:normal; }
            .ab-desc-gen  { font-size:12px; color:#1a4a7a; margin-top:4px; background:#e8f3fb;
                            border-left:3px solid #2271b1; padding:4px 8px; border-radius:0 3px 3px 0; }
            .ab-row-btn { font-size:11px; padding:3px 8px; }
            .ab-key-row { display:flex; gap:8px; align-items:center; }
            .ab-key-status { font-size:12px; font-weight:600; }
            .ab-key-ok  { color:#1a7a34; }
            .ab-key-err { color:#8a2424; }
            #ab-ai-gen-all { position:relative; }
            .ab-spinner { display:inline-block; animation:ab-spin 0.8s linear infinite; margin-right:4px; }
            @keyframes ab-spin { to { transform:rotate(360deg); } }
            .ab-pager { display:flex; gap:8px; align-items:center; margin-top:12px; }
            .ab-summary-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin:12px 0; }
            .ab-summary-card { background:#f6f7f7; border:1px solid #c3c4c7; border-radius:6px;
                               padding:12px; text-align:center; }
            .ab-summary-num  { font-size:24px; font-weight:700; color:#2271b1; line-height:1; }
            .ab-summary-lbl  { font-size:11px; color:#50575e; margin-top:4px; }
            /* Section zone headers */
            /* ── Zone cards (matching CloudScale Backup style) ── */
            .ab-zone-card {
                border-radius:8px; overflow:hidden;
                box-shadow:0 2px 8px rgba(0,0,0,0.10);
                margin:24px 0 0;
            }
            .ab-zone-header {
                display:flex; align-items:center; gap:10px;
                padding:13px 20px;
                font-size:15px; font-weight:700; color:#fff;
                letter-spacing:0.01em;
            }
            .ab-zone-header .ab-zone-icon { font-size:17px; }
            .ab-zone-body {
                background:#fff;
                padding:4px 0 8px;
            }
            .ab-zone-body .form-table th { padding-left:20px; }
            .ab-zone-body .form-table td { padding-right:20px; }
            @media (max-width:782px) {
                .ab-zone-body .form-table th { padding-left:16px; }
                .ab-zone-body .form-table td { padding-left:16px; padding-right:16px; }
                .ab-zone-body > *:not(.form-table):not(.ab-zone-body) { padding-left:16px; padding-right:16px; }
                .ab-alt-posts-wrap, #ab-alt-posts-wrap,
                #ab-alt-log, #ab-alt-prog-label,
                #ab-alt-toolbar, #ab-alt-summary,
                #ab-alt-load-cta { padding-left:16px; padding-right:16px; }
            }
            /* Colour per section — matches backup plugin palette */
            .ab-zone-card.ab-card-identity .ab-zone-header  { background:#2271b1; } /* blue   */
            .ab-zone-card.ab-card-features .ab-zone-header  { background:#1a7a34; } /* green  */
            .ab-checkbox-grid {
                display:grid; grid-template-columns:repeat(3,1fr); gap:10px 24px; padding:4px 0 8px;
            }
            .ab-checkbox-grid label { display:flex; align-items:flex-start; gap:6px; font-size:13px; cursor:pointer; padding:6px 8px; border-radius:4px; }
            .ab-checkbox-grid label.ab-rec { background:#edf7ed; border:1px solid #c3e6c3; }
            .ab-checkbox-grid label:not(.ab-rec) { background:#f9f9f9; border:1px solid #e5e5e5; }
            /* Slider toggle */
            .ab-toggle-row {
                display:flex; align-items:center; justify-content:space-between;
                padding:12px 0; border-bottom:1px solid #f0f0f0;
            }
            .ab-toggle-row:last-child { border-bottom:none; }
            .ab-toggle-label { font-size:13px; font-weight:600; color:#1d2327; }
            .ab-toggle-label span { display:block; font-weight:400; color:#666; font-size:12px; margin-top:2px; }
            .ab-toggle-switch { position:relative; display:inline-block; width:44px; height:24px; flex-shrink:0; }
            .ab-toggle-switch input { opacity:0; width:0; height:0; }
            .ab-toggle-slider {
                position:absolute; cursor:pointer; inset:0;
                background:#ccc; border-radius:24px;
                transition:background 0.2s;
            }
            .ab-toggle-slider:before {
                content:''; position:absolute;
                width:18px; height:18px; border-radius:50%;
                left:3px; bottom:3px; background:#fff;
                transition:transform 0.2s;
                box-shadow:0 1px 3px rgba(0,0,0,0.2);
            }
            .ab-toggle-switch input:checked + .ab-toggle-slider { background:#1a7a34; }
            .ab-toggle-switch input:checked + .ab-toggle-slider:before { transform:translateX(20px); }
            .ab-zone-card.ab-card-robots .ab-zone-header    { background:#b45309; } /* amber  */
            .ab-physical-robots-warn {
                display:flex; gap:16px; align-items:flex-start;
                background:#fff8e1; border:2px solid #f0ad00;
                border-radius:6px; padding:16px 20px; margin:16px 20px 4px;
                font-size:13px; line-height:1.6;
            }
            .ab-zone-card.ab-card-person .ab-zone-header    { background:#6b3fa0; } /* purple */
            .ab-zone-card.ab-card-ai .ab-zone-header        { background:#c3372b; } /* red    */
            .ab-zone-card.ab-card-ai .ab-zone-body          { background:#fdf7f7; }
            .ab-zone-card.ab-card-schedule .ab-zone-header  { background:#e67e00; } /* orange */
            .ab-zone-card.ab-card-lastrun  .ab-zone-header  { background:#1a4a7a; } /* dark blue */
            .ab-zone-card.ab-card-alt      .ab-zone-header  { background:#0e6b6b; } /* teal */
            .ab-zone-card.ab-card-sitemap-settings .ab-zone-header { background:#1a7a34; }
            .ab-zone-card.ab-card-sitemap-preview .ab-zone-header  { background:#0e5229; }
            .ab-zone-card.ab-card-llms .ab-zone-header             { background:#1a4a8a; }
            .ab-zone-card.ab-card-https .ab-zone-header            { background:#7a1a1a; }
            /* Sitemap preview table */
            .ab-sitemap-url { font-size:12px; color:#0a6be0; word-break:break-all; font-weight:500; }
            .ab-sitemap-type { font-size:11px; font-weight:700; padding:2px 7px; border-radius:3px; white-space:nowrap; }
            .ab-sitemap-type-home { background:#c8a8f0; color:#2d1060; }
            .ab-sitemap-type-post { background:#a8cff0; color:#0a2a5a; }
            .ab-sitemap-type-page { background:#a8f0b8; color:#0a4a1a; }
            .ab-sitemap-type-tax  { background:#f0e0a8; color:#4a3000; }
            .ab-sitemap-type-cpt  { background:#f0c0a8; color:#5a1a00; }
            table.ab-sitemap-tbl { width:100%; border-collapse:collapse; font-size:13px; }
            table.ab-sitemap-tbl th { text-align:left; padding:8px 12px; border-bottom:2px solid #8c8f94;
                                      background:#f0f0f1; font-size:12px; color:#1d2327; font-weight:700; text-transform:uppercase; letter-spacing:0.04em; }
            table.ab-sitemap-tbl td { padding:8px 12px; border-bottom:1px solid #dcdcde; vertical-align:middle; color:#1d2327; }
            table.ab-sitemap-tbl tr:hover td { background:#e8f0fa; }
            table.ab-sitemap-tbl tr:nth-child(even) td { background:#fafafa; }
            table.ab-sitemap-tbl tr:nth-child(even):hover td { background:#e8f0fa; }
            .ab-sitemap-count { font-size:13px; color:#1d2327; margin:0 0 12px; font-weight:500; }
            .ab-sitemap-count strong { color:#1d2327; }
            .ab-zone-card.ab-card-update-posts .ab-zone-header { background:#1d2327; font-size:17px; padding:16px 22px; }
            .ab-zone-card.ab-card-update-posts .ab-zone-header .ab-zone-icon { color:#f0c040; font-size:20px; }
            /* Load Posts CTA strip */
            .ab-load-cta {
                display:flex; align-items:center; gap:18px;
                background:linear-gradient(135deg, #1d2327 0%, #2c3338 100%);
                border-radius:6px; padding:20px 24px; margin-bottom:20px;
                border-left:5px solid #f0c040;
            }
            .ab-load-cta-icon { font-size:32px; line-height:1; flex-shrink:0; }
            .ab-load-cta-text { flex:1; }
            .ab-load-cta-text strong { display:block; color:#fff; font-size:15px; margin-bottom:3px; }
            .ab-load-cta-text span { color:#a7aaad; font-size:13px; }
            .ab-load-btn {
                flex-shrink:0;
                background:#f0c040 !important; border-color:#d4a800 !important;
                color:#1d2327 !important; font-weight:700 !important;
                font-size:15px !important; padding:10px 28px !important;
                border-radius:4px; cursor:pointer; white-space:nowrap;
                box-shadow:0 2px 6px rgba(0,0,0,0.25);
            }
            .ab-load-btn:hover { background:#f5d060 !important; }
            /* Action buttons in toolbar */
            .ab-action-btn { font-size:13px !important; padding:6px 14px !important; height:auto !important; }
            .ab-fix-btn    { background:#e67e00 !important; border-color:#c26900 !important; color:#fff !important; }
            .ab-regen-btn  { background:#1a7a34 !important; border-color:#155f28 !important; color:#fff !important; }
            .ab-static-btn { background:#c2185b !important; border-color:#ad1457 !important; color:#fff !important; }
            .ab-zone-divider {
                border:none; border-top:2px solid #dcdcde;
                margin:32px 0 0; opacity:1;
            }
            /* Force black text in all plugin textareas — overrides mobile browser defaults */
            #cs-robots-txt,
            textarea[name="cs_seo_options[sitemap_exclude]"] {
                background:#1a1a2e !important;
                color:#e0e0f0 !important;
                font-family:'Courier New',monospace !important;
                font-size:12px !important;
                line-height:1.6 !important;
                border:1px solid #2a2a4a !important;
                border-radius:4px !important;
            }
            textarea[name="cs_seo_options[home_desc]"],
            textarea[name="cs_seo_options[default_desc]"],
            textarea[name="cs_seo_options[sameas]"] {
                color:#1d2327 !important;
            }
            /* Field hints — italic, muted green */
            .ab-zone-body p.description,
            .ab-zone-body .description {
                color:#2a7a3a !important;
                font-style:italic !important;
                font-size:12px !important;
                padding-left:8px !important;
                margin-top:4px !important;
                border-left:3px solid #b8dfc0 !important;
            }
            /* Form labels — bold, with colon */
            .ab-zone-body .form-table th,
            .ab-zone-body .form-table th label {
                font-weight:700 !important;
                color:#1d2327 !important;
            }
            .ab-api-key-warning {
                display:none; align-items:flex-start; gap:12px;
                background:#fff8e1; border:2px solid #f0ad00;
                border-radius:6px; padding:14px 18px; margin:0 0 16px;
            }
            .ab-api-key-warning.visible { display:flex; }
            .ab-api-key-warning .ab-warn-icon { font-size:22px; line-height:1; flex-shrink:0; }
            .ab-api-key-warning .ab-warn-body { font-size:13px; color:#1d2327; }
            .ab-api-key-warning .ab-warn-body strong { display:block; margin-bottom:4px; font-size:14px; }
            .ab-api-key-warning .ab-warn-body a { color:#2271b1; font-weight:600; }
        </style>

        <div class="ab-tabs">
            <button class="ab-tab active" data-tab="seo"     onclick="abTab('seo',this)">📊 Optimise SEO</button>
            <button class="ab-tab"        data-tab="sitemap" onclick="abTab('sitemap',this)">🗺 Sitemap &amp; Robots</button>
            <button class="ab-tab"        data-tab="perf"    onclick="abTab('perf',this)">⚡ Performance</button>
            <button class="ab-tab"        data-tab="batch"   onclick="abTab('batch',this)">🔄 Scheduled Batch</button>
        </div>
        </div>

        <?php /* ══════════════════ SETTINGS PANE (SEO + AI combined) ══════════════════ */ ?>
        <div class="ab-pane active" id="ab-pane-seo">

            <?php /* ── SEO Settings form ── */ ?>
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>

                <div class="ab-zone-card ab-card-identity">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🌐</span> Site Identity</span>
                    <?php $this->explain_btn('identity', '🌐 Site Identity — What each field does', [
                        ['rec'=>'✅ Recommended','name'=>'Site name','desc'=>'The name of your site as it appears in search results, browser tabs, and social sharing. Used in JSON-LD schema and OpenGraph tags. e.g. "Andrew Baker" or "Andrew Baker\'s Tech Blog".'],
                        ['rec'=>'✅ Recommended','name'=>'Title suffix','desc'=>'Appended to every page title in search results. e.g. if your suffix is "| Andrew Baker" then a post titled "AWS Lambda Tips" appears as "AWS Lambda Tips | Andrew Baker". Helps with brand recognition in SERPs.'],
                        ['rec'=>'✅ Recommended','name'=>'Home title','desc'=>'The SEO title for your homepage specifically. This is what Google shows as the blue link for your homepage in search results. Make it descriptive and keyword-rich — e.g. "Andrew Baker – CIO, Cloud Architect & Technology Leader".'],
                        ['rec'=>'✅ Recommended','name'=>'Home description','desc'=>'The meta description for your homepage. Shown as the snippet under your homepage title in Google. Aim for 140–155 characters. Write for humans — this is your elevator pitch to someone seeing your site for the first time.'],
                        ['rec'=>'✅ Recommended','name'=>'Default OG image URL','desc'=>'The fallback image used when a post is shared on social media and has no featured image. Should be 1200×630px. Use a branded image with your name/logo — this appears as the preview card on LinkedIn, Twitter/X, and WhatsApp.'],
                        ['rec'=>'⬜ Optional','name'=>'Locale','desc'=>'BCP 47 language tag used in OpenGraph metadata. "en-US" is fine for most English sites. Use "en-ZA" if you want to signal a South African audience to Facebook/LinkedIn. Has minimal impact on Google rankings.'],
                        ['rec'=>'⬜ Optional','name'=>'Twitter handle','desc'=>'Your Twitter/X username including the @ symbol. Added to Twitter Card metadata so when your posts are shared on X, your account gets attributed as the author. Only matters if you actively use Twitter/X.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label>Site name:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[site_name]" value="<?php echo esc_attr((string)($o['site_name'] ?? '')); ?>" placeholder="My Tech Blog">
                        <p class="description">Used in JSON-LD schema and OG tags. e.g. My Tech Blog</p></td>
                        <th><label>Locale:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[site_lang]" value="<?php echo esc_attr((string)($o['site_lang'] ?? '')); ?>" placeholder="en-US">
                        <p class="description">BCP 47 language tag. e.g. en-US, en-GB, fr-FR</p></td>
                    </tr>
                    <tr>
                        <th><label>Title suffix:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[title_suffix]" value="<?php echo esc_attr((string)($o['title_suffix'] ?? '')); ?>" placeholder=" | My Tech Blog">
                        <p class="description">Appended to every page title. e.g. " | My Blog"</p></td>
                        <th><label>Twitter handle:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[twitter_handle]" value="<?php echo esc_attr((string)($o['twitter_handle'] ?? '')); ?>" placeholder="@yourhandle">
                        <p class="description">Your Twitter/X handle including the @ symbol.</p></td>
                    </tr>
                    <tr>
                        <th><label>Home title:</label></th>
                        <td><input class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[home_title]" value="<?php echo esc_attr((string)($o['home_title'] ?? '')); ?>" placeholder="My Blog – Tech Writer & Developer">
                        <p class="description">Full SEO title for your homepage.</p></td>
                        <th><label>Default OG image URL:</label></th>
                        <td><input class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[default_og_image]" value="<?php echo esc_attr($o['default_og_image']); ?>" placeholder="https://yoursite.com/wp-content/uploads/og-default.jpg">
                        <p class="description">Fallback image for social sharing. 1200×630px ideal.</p></td>
                    </tr>
                    <tr><th>Home description:</th>
                        <td colspan="3">
                            <textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPT); ?>[home_desc]" placeholder="A blog about technology, software development, and cloud architecture. Written for engineers and technical leaders."><?php echo esc_textarea($o['home_desc']); ?></textarea>
                            <p class="description">Meta description for your homepage. Aim for 140–155 characters.</p>
                        </td></tr>
                </table>
                </div>
                </div><!-- /ab-card-identity -->

                <div class="ab-zone-card ab-card-person">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">👤</span> Person Schema</span>
                    <?php $this->explain_btn('person', '👤 Person Schema — What each field does', [
                        ['rec'=>'✅ Recommended','name'=>'Full name','desc'=>'Your name as it appears in Google search results and Knowledge Graph. Use your real name exactly as you want it attributed — this is what Google uses to connect your content to you as an individual author.'],
                        ['rec'=>'✅ Recommended','name'=>'Profile URL','desc'=>'The canonical URL for your personal profile — usually your homepage (https://yoursite.com/). Google uses this as the authoritative identifier for you as a person in its Knowledge Graph.'],
                        ['rec'=>'✅ Recommended','name'=>'Job title','desc'=>'Your current job title, e.g. "Chief Information Officer". Included in your Person JSON-LD schema and helps Google understand your professional authority in your subject area.'],
                        ['rec'=>'✅ Recommended','name'=>'Person image URL','desc'=>'URL to your headshot or profile photo. Used in Person schema so Google can associate a face with your content. Ideally a square image of at least 400×400px already uploaded to your media library.'],
                        ['rec'=>'✅ Recommended','name'=>'Social profiles (sameAs)','desc'=>'One URL per line — your LinkedIn, Twitter/X, GitHub, Google Scholar etc. Google uses these to verify your identity and connect your various online presences. The more authoritative profiles you link, the stronger your author entity signal.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label>Name:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_name]" value="<?php echo esc_attr((string)($o['person_name'] ?? '')); ?>" placeholder="Jane Smith">
                        <p class="description">Your full name as it appears in Google.</p></td>
                        <th><label>Job title:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_job_title]" value="<?php echo esc_attr((string)($o['person_job_title'] ?? '')); ?>" placeholder="Software Engineer">
                        <p class="description">Your current job title.</p></td>
                    </tr>
                    <tr>
                        <th><label>URL:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_url]" value="<?php echo esc_attr((string)($o['person_url'] ?? '')); ?>" placeholder="https://yoursite.com">
                        <p class="description">Canonical URL for your personal profile.</p></td>
                        <th><label>Person image URL:</label></th>
                        <td><input class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_image]" value="<?php echo esc_attr($o['person_image']); ?>" placeholder="https://yoursite.com/wp-content/uploads/headshot.jpg">
                        <p class="description">URL of your profile photo for Person JSON-LD schema.</p></td>
                    </tr>
                    <tr><th>SameAs URLs (one per line):</th>
                        <td colspan="3">
                            <textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPT); ?>[sameas]" placeholder="https://www.linkedin.com/in/yourname&#10;https://twitter.com/yourhandle&#10;https://github.com/yourname"><?php echo esc_textarea($o['sameas']); ?></textarea>
                            <p class="description">Your profiles on other platforms — one URL per line. Helps Google connect your identity across the web.</p>
                        </td></tr>
                </table>
                </div>
                </div><!-- /ab-card-person -->

                <?php submit_button('Save SEO Settings'); ?>
            </form>

            <hr class="ab-zone-divider">

            <?php /* ── AI Meta Writer config form ── */ ?>
            <form method="post" action="options.php" id="ab-ai-config-form">
                <?php settings_fields('cs_seo_ai_group'); ?>

                <div class="ab-zone-card ab-card-ai">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">✦</span> AI Meta Writer — Anthropic Claude</span>
                    <?php $this->explain_btn('ai', '✦ AI Meta Writer — What each setting does', [
                        ['rec'=>'✅ Recommended','name'=>'Anthropic API key','desc'=>'Your secret key from console.anthropic.com. Required to call the Claude AI to generate meta descriptions. Keep this private — anyone with this key can use your Anthropic account. The key is stored securely in your WordPress database.'],
                        ['rec'=>'ℹ️ Info','name'=>'Claude model','desc'=>'Which version of Claude to use for generation. Claude Haiku is fast and cheap — ideal for bulk processing hundreds of posts. Claude Sonnet is slower and costs more but produces higher quality, more nuanced descriptions. For a blog with 100+ posts, Haiku is usually the right choice.'],
                        ['rec'=>'⬜ Optional','name'=>'Overwrite existing','desc'=>'When enabled, the AI will regenerate descriptions for posts that already have one. Leave OFF to only fill in missing descriptions — this protects any manually written descriptions you\'ve already crafted.'],
                        ['rec'=>'⬜ Optional','name'=>'Min / Max characters','desc'=>'Target character range for generated descriptions. Google typically shows 140–160 characters in search results before truncating. Descriptions shorter than 120 characters look thin; longer than 165 get cut off with an ellipsis.'],
                        ['rec'=>'⬜ Optional','name'=>'Custom prompt','desc'=>'Advanced: override the default instructions sent to Claude. The default prompt is tuned for technical blog posts. Only change this if you want a different tone, language, or specific instructions about what to include or exclude in descriptions.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body ab-zone-ai">
                <table class="form-table" role="presentation">
                    <tr>
                        <th>AI Provider:</th>
                        <td>
                            <select name="<?php echo esc_attr(self::AI_OPT); ?>[ai_provider]" id="ab-ai-provider" onchange="abProviderChanged()">
                                <option value="anthropic" <?php selected($ai['ai_provider'] ?? 'anthropic', 'anthropic'); ?>>Anthropic Claude</option>
                                <option value="gemini"    <?php selected($ai['ai_provider'] ?? 'anthropic', 'gemini'); ?>>Google Gemini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>API Key:</th>
                        <td>
                            <div class="ab-key-row">
                                <input type="password" class="regular-text"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[anthropic_key]"
                                    id="ab-anthropic-key-field"
                                    value="<?php echo esc_attr($ai['anthropic_key']); ?>"
                                    placeholder="sk-ant-api03-..."
                                    style="<?php echo ($ai['ai_provider'] ?? 'anthropic') === 'gemini' ? 'display:none' : ''; ?>">
                                <input type="password" class="regular-text"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[gemini_key]"
                                    id="ab-gemini-key-field"
                                    value="<?php echo esc_attr($ai['gemini_key'] ?? ''); ?>"
                                    placeholder="AIza..."
                                    style="<?php echo ($ai['ai_provider'] ?? 'anthropic') !== 'gemini' ? 'display:none' : ''; ?>">
                                <button type="button" class="button" onclick="abTestKey()">Test Key</button>
                                <span id="ab-key-status" class="ab-key-status"></span>
                            </div>
                            <p class="description" id="ab-key-hint-anthropic" style="<?php echo ($ai['ai_provider'] ?? 'anthropic') === 'gemini' ? 'display:none' : ''; ?>">
                                Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>. Stored in wp_options — never output to frontend.
                            </p>
                            <p class="description" id="ab-key-hint-gemini" style="<?php echo ($ai['ai_provider'] ?? 'anthropic') !== 'gemini' ? 'display:none' : ''; ?>">
                                Get your key at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>. Stored in wp_options — never output to frontend.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td>
                            <select name="<?php echo esc_attr(self::AI_OPT); ?>[model]" id="ab-model-select">
                                <?php
                                $provider = $ai['ai_provider'] ?? 'anthropic';
                                $anthropic_models = [
                                    'claude-sonnet-4-20250514'  => 'Claude Sonnet 4 (recommended — best quality)',
                                    'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (faster, cheaper)',
                                ];
                                $gemini_models = [
                                    'gemini-2.0-flash'          => 'Gemini 2.0 Flash (recommended — fast and cheap)',
                                    'gemini-2.0-flash-lite'     => 'Gemini 2.0 Flash Lite (fastest, cheapest)',
                                    'gemini-2.0-pro-exp'        => 'Gemini 2.0 Pro Experimental (high quality)',
                                    'gemini-2.5-pro-preview-03-25' => 'Gemini 2.5 Pro Preview (best quality)',
                                    'gemini-1.5-pro'            => 'Gemini 1.5 Pro',
                                ];
                                $all_models = array_merge($anthropic_models, $gemini_models);
                                foreach ($all_models as $v => $l):
                                    $is_anthropic = array_key_exists($v, $anthropic_models);
                                    $group = $is_anthropic ? 'anthropic' : 'gemini';
                                    $hidden = ($provider !== $group) ? 'style="display:none"' : '';
                                ?>
                                    <option value="<?php echo esc_attr($v); ?>"
                                        data-provider="<?php echo esc_attr($group); ?>"
                                        <?php selected($ai['model'], $v); ?>
                                        <?php echo $hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        ><?php echo esc_html($l); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Target length:</th>
                        <td>
                            <input type="number" style="width:70px" name="<?php echo esc_attr(self::AI_OPT); ?>[min_chars]" value="<?php echo esc_attr($ai['min_chars']); ?>" min="100" max="160"> min &nbsp;
                            <input type="number" style="width:70px" name="<?php echo esc_attr(self::AI_OPT); ?>[max_chars]" value="<?php echo esc_attr($ai['max_chars']); ?>" min="100" max="200"> max characters
                            <p class="description">Google shows 120–160 chars. The range you set here is automatically injected into the prompt — you do not need to mention it in the system prompt above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>ALT text article excerpt:</th>
                        <td>
                            <input type="number" style="width:80px" name="<?php echo esc_attr(self::AI_OPT); ?>[alt_excerpt_chars]" value="<?php echo esc_attr((string)($ai['alt_excerpt_chars'] ?? 600)); ?>" min="100" max="2000"> characters
                            <p class="description">How much of the article text to send alongside each image when generating ALT text. More context produces better results for images with generic filenames, but increases API token usage. 600 is a good balance — enough to cover the intro and first heading. Increase to 1200+ for dense technical posts where images appear mid-article. Range: 100–2000.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>System prompt:</th>
                        <td>
                            <textarea class="large-text" rows="10"
                                id="ab-prompt-field"
                                name="<?php echo esc_attr(self::AI_OPT); ?>[prompt]"><?php echo esc_textarea($ai['prompt']); ?></textarea>
                            <div style="display:flex;gap:6px;margin-top:4px">
                                <button type="button" class="button" id="ab-copy-prompt" onclick="abCopyPrompt()">⎘ Copy</button>
                                <button type="button" class="button" id="ab-reset-prompt">
                                    Reset to default
                                </button>
                            </div>
                            <?php /* reset-prompt listener moved to admin_enqueue_assets() */ ?>
                        </td>
                    </tr>
                </table>
                </div><!-- /ab-zone-body -->
                </div><!-- /ab-card-ai -->
                <?php submit_button('Save AI Settings'); ?>
            </form>

            <hr class="ab-zone-divider">

            <div class="ab-zone-card ab-card-update-posts">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">✦</span> Update Posts with AI Descriptions</span>
                    <?php $this->explain_btn('updateposts', '✦ Update Posts — How this works', [
                        ['rec'=>'ℹ️ Summary','name'=>'What this panel does','desc'=>'Writes the short text snippet that appears under your page title in Google search results — using AI to craft a compelling 140–155 character summary for each post.'],
                        ['rec'=>'ℹ️ Info','name'=>'Total Posts','desc'=>'The total number of published posts and pages on your site that are eligible for meta description generation.'],
                        ['rec'=>'ℹ️ Info','name'=>'Have Description','desc'=>'Posts that already have a meta description saved — either written manually or previously generated by the AI.'],
                        ['rec'=>'ℹ️ Info','name'=>'Unprocessed','desc'=>'Posts with no meta description yet. These are the ones Google is currently generating its own snippet for — which is often not the best representation of your content.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generated This Session','desc'=>'How many descriptions have been written by the AI since you opened this page. Resets each time you load the page.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate Missing','desc'=>'Runs the AI on every post that has no meta description yet. For each post, the AI also automatically generates ALT text for any images that are missing it — both tasks happen in a single API call, saving cost. Will never overwrite descriptions you\'ve already written.'],
                        ['rec'=>'⬜ Optional','name'=>'Regenerate All','desc'=>'Forces the AI to rewrite descriptions for every post, including ones that already have descriptions. Also generates missing ALT text for images in each post in the same call. Use this if you\'ve changed your prompt or want a fresh pass. Note: this will overwrite any manually written descriptions.'],
                        ['rec'=>'⬜ Optional','name'=>'Fix Long/Short','desc'=>'Finds descriptions that fall outside your target character range and rewrites only those. Does not touch ALT text — use the ALT Text Generator panel for that.'],
                        ['rec'=>'⬜ Optional','name'=>'Fix Titles','desc'=>'Scans all posts for title tags that fall outside the ideal 50–60 character range and AI-rewrites them to fit. The rewritten title is saved as a custom SEO title — your original WordPress post title is never changed. Skips the homepage (fix that manually) and any titles already in range.'],
                        ['rec'=>'⬜ Optional','name'=>'Regenerate Static','desc'=>'Fixes stale static data for every post — specifically, clears any custom OG image URL that has been overridden, so the post falls back to its current featured image. Run this if you have updated featured images on posts and LinkedIn, Twitter/X, or other platforms are still showing the old image. It does not touch AI descriptions or ALT text.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate (per row)','desc'=>'Rewrites the description for a single post and also generates missing image ALT text for that post in the same API call. Click this next to any post to manually trigger the AI for just that one entry.'],
                        ['rec'=>'ℹ️ Info','name'=>'ALT Images column','desc'=>'Shows how many images in each post are still missing ALT text. ⚠ yellow means images need attention — generating the description will fix them automatically. ✓ green means all images have ALT text.'],
                        ['rec'=>'ℹ️ Info','name'=>'Title column','desc'=>'Shows the character count of each post\'s effective title tag (custom SEO title if set, otherwise the WordPress post title). Green = 50–60 chars (ideal). Amber = 40–69 chars (acceptable). Red = outside that range (too short or too long for Google). Hover the badge to see the full title text. Use Fix Titles to auto-fix all out-of-range titles in one pass.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px">

                <?php /* ── API key warning banner ── */ ?>
                <div class="ab-api-key-warning" id="ab-api-warn">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong>No Anthropic API key saved — AI generation is disabled.</strong>
                        To use the AI buttons you need to:
                        <ol style="margin:6px 0 0 16px;padding:0">
                            <li>Get a free API key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a></li>
                            <li>Paste it into the <strong>API Key</strong> field in the <strong>✦ AI Meta Writer</strong> section above</li>
                            <li>Click <strong>Save AI Settings</strong></li>
                            <li>Return here and reload the page</li>
                        </ol>
                    </div>
                </div>

                <?php /* ── Load Posts CTA ── */ ?>
                <div class="ab-load-cta" id="ab-load-cta">
                    <div class="ab-load-cta-icon">⬇</div>
                    <div class="ab-load-cta-text">
                        <strong>Load your posts to get started</strong>
                        <span>Fetch all published posts and pages so you can generate or fix their meta descriptions</span>
                    </div>
                    <button class="ab-load-btn" id="ab-load-posts" onclick="abLoadPosts()">Load Posts</button>
                </div>

                <?php /* ── Summary cards ── */ ?>
                <div class="ab-summary-row" id="ab-summary" style="display:none">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-total">0</div><div class="ab-summary-lbl">Total Posts</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-has" style="color:#1a7a34">0</div><div class="ab-summary-lbl">Have Description</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-missing" style="color:#6b3fa0">0</div><div class="ab-summary-lbl">Unprocessed</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-generated" style="color:#2271b1">0</div><div class="ab-summary-lbl">Generated This Session</div></div>
                </div>

                <?php /* ── Toolbar ── */ ?>
                <div class="ab-ai-toolbar" id="ab-ai-toolbar" style="display:none">
                    <button class="button button-primary ab-action-btn" id="ab-ai-gen-missing" onclick="abGenAll(0)" disabled>✦ Generate Missing</button>
                    <button class="button ab-action-btn ab-regen-btn" id="ab-ai-gen-all" onclick="abGenAll(1)" disabled>↺ Regenerate All</button>
                    <button class="button ab-action-btn ab-fix-btn" id="ab-ai-fix" onclick="abFixAll()" disabled>⚑ Fix Long/Short</button>
                    <button class="button ab-action-btn" id="ab-ai-fix-titles" onclick="abFixTitles()" disabled style="background:#7c3aed;color:#fff;border-color:#6d28d9">✎ Fix Titles</button>
                    <button class="button ab-action-btn ab-static-btn" id="ab-ai-static" onclick="abRegenStatic()" disabled>🖼 Regenerate Static</button>
                    <button class="button" id="ab-load-posts-again" onclick="abLoadPosts()" style="margin-left:auto">↻ Reload</button>
                    <button class="button" id="ab-ai-stop" onclick="abStop()" style="display:none">◻ Stop</button>
                    <span id="ab-toolbar-status" style="font-size:12px;color:#50575e;"></span>
                </div>

                <?php /* ── Progress bar ── */ ?>
                <div class="ab-progress" id="ab-progress">
                    <div class="ab-progress-fill" id="ab-progress-fill"></div>
                </div>
                <div class="ab-stats" id="ab-prog-label"></div>

                <?php /* ── Log ── */ ?>
                <div id="ab-log-wrap" style="display:none">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-log"></div>
                </div>

                <?php /* ── Post table ── */ ?>
                <div id="ab-posts-wrap"></div>
                <div class="ab-pager" id="ab-pager" style="display:none">
                    <button class="button" id="ab-prev" onclick="abPage(-1)">← Prev</button>
                    <span id="ab-page-info" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-next" onclick="abPage(1)">Next →</button>
                </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-update-posts -->

            <div class="ab-zone-card ab-card-alt">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🖼</span> AI Image ALT Text Generator</span>
                    <?php $this->explain_btn('alttext', '🖼 ALT Text — How this works', [
                        ['rec'=>'ℹ️ Summary','name'=>'What this panel does','desc'=>'Adds descriptive labels to every image on your site — used by screen readers for accessibility and by Google to understand image content for search ranking.'],
                        ['rec'=>'✅ Recommended','name'=>'Why ALT text matters','desc'=>'ALT (alternative) text describes images to screen readers and search engines. Missing ALT text is an accessibility failure and an SEO missed opportunity — Google uses ALT text to understand image content and rank your images in Google Images search.'],
                        ['rec'=>'ℹ️ Info','name'=>'Posts with missing ALT','desc'=>'Shows how many posts have at least one image with an empty ALT attribute. Click Load to scan your site.'],
                        ['rec'=>'ℹ️ Info','name'=>'Images missing ALT','desc'=>'The total count of individual image tags across all posts that have empty ALT attributes.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate All Missing','desc'=>'Runs the AI on every post that has images with missing ALT text. For each image, Claude reads the post title and image filename to write a concise, contextually appropriate ALT description (5 to 15 words). If the AI returns text outside that range, it automatically retries once. The post content is updated in place and the attachment media library entry is also updated.'],
                        ['rec'=>'ℹ️ Info','name'=>'Force Regenerate All','desc'=>'Overwrites ALL existing ALT text across every post, not just missing ones. Useful if you want to improve previously generated ALT text or standardise quality across your site. A confirmation prompt appears before running. The same 5 to 15 word validation with retry applies.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate (per row)','desc'=>'Process a single post — useful to check results before running the full batch. All images in that post with empty ALT will be processed.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px">

                <div class="ab-api-key-warning" id="ab-alt-api-warn" style="<?php
                    $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
                    $alt_has_key = $provider === 'gemini'
                        ? !empty(trim((string)($this->ai_opts['gemini_key'] ?? '')))
                        : !empty(trim((string)($this->ai_opts['anthropic_key'] ?? '')));
                    echo $alt_has_key ? 'display:none' : '';
                ?>">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong>No AI API key saved — ALT text generation is disabled.</strong>
                        Add an Anthropic API key in the <strong>✦ AI Meta Writer</strong> section above and save.
                    </div>
                </div>

                <div class="ab-load-cta" id="ab-alt-load-cta">
                    <div class="ab-load-cta-icon">🔍</div>
                    <div class="ab-load-cta-text">
                        <strong>Scan your posts for images missing ALT text</strong>
                        <span>Finds all images in published posts and pages that have an empty ALT attribute</span>
                    </div>
                    <button class="ab-load-btn" id="ab-alt-load-btn" onclick="altLoad()">Scan Posts</button>
                </div>

                <div class="ab-summary-row" id="ab-alt-summary" style="display:none">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-posts">0</div><div class="ab-summary-lbl">Posts with Missing ALT</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-images" style="color:#c3372b">0</div><div class="ab-summary-lbl">Images Missing ALT</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-done" style="color:#1a7a34">0</div><div class="ab-summary-lbl">Fixed This Session</div></div>
                </div>

                <div class="ab-ai-toolbar" id="ab-alt-toolbar" style="display:none">
                    <button class="button button-primary ab-action-btn" id="ab-alt-gen-all" onclick="altGenAll(false)" <?php echo ($alt_has_key ? '' : 'disabled'); ?>>✦ Generate All Missing</button>
                    <button class="button ab-action-btn" id="ab-alt-force-all" onclick="altGenAll(true)" style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600" <?php echo ($alt_has_key ? '' : 'disabled'); ?>>🔄 Force Regenerate All</button>
                    <button class="button" id="ab-alt-stop" onclick="altStop()" style="display:none">◻ Stop</button>
                    <span id="ab-alt-status" style="font-size:12px;color:#50575e;margin-left:8px"></span>
                    <label id="ab-alt-show-all-wrap" style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin-left:auto">
                        <input type="checkbox" id="ab-alt-show-all" onchange="altState.showAll=this.checked;altRenderTable()"> Show all
                    </label>
                </div>

                <div class="ab-progress" id="ab-alt-progress">
                    <div class="ab-progress-fill" id="ab-alt-progress-fill"></div>
                </div>
                <div class="ab-stats" id="ab-alt-prog-label"></div>
                <div id="ab-alt-log-wrap" style="display:none">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-alt-log"></div>
                </div>
                <div id="ab-alt-posts-wrap"></div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-alt -->

        </div><!-- /ab-pane-seo -->

        <?php /* ══════════════════ SITEMAP PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-sitemap">
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>

                <?php
                $pub_types = get_post_types(['public' => true], 'objects');
                $sel_types = (array)($o['sitemap_post_types'] ?? ['post', 'page']);
                ?>

                <div class="ab-zone-card ab-card-features">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⚙</span> Features &amp; Robots</span>
                    <?php $this->explain_btn('features', '⚙ Features & Robots — What each option does', [
                        ['rec'=>'✅ Recommended','name'=>'OpenGraph + Twitter Cards','desc'=>'Adds structured metadata so your posts display with a title, description and image when shared on LinkedIn, Twitter/X, WhatsApp or any other platform. Without this, shared links look blank or use random images.'],
                        ['rec'=>'✅ Recommended','name'=>'WebSite JSON-LD (front page)','desc'=>'Tells Google the name and URL of your site in structured data format. Helps Google display your site name correctly in search results and can unlock sitelinks beneath your homepage listing.'],
                        ['rec'=>'✅ Recommended','name'=>'Person JSON-LD schema','desc'=>'Embeds your name, job title, photo, and social profiles into your site so Google can connect your content to you as an individual. Important for personal brand and author authority signals.'],
                        ['rec'=>'✅ Recommended','name'=>'BlogPosting JSON-LD schema','desc'=>'Marks up each post as an article with author, publish date, and headline. Google uses this for rich results and to better understand your content type. Can improve click-through rates in search.'],
                        ['rec'=>'⬜ Optional','name'=>'Breadcrumb JSON-LD schema','desc'=>'Adds breadcrumb trail markup to posts. Most useful on large sites with deep category hierarchies. For a flat personal blog this adds little value — Google will figure out your structure without it.'],
                        ['rec'=>'⬜ Optional','name'=>'Strip UTM params in canonical URLs','desc'=>'If you use UTM tracking parameters on your own internal links (e.g. ?utm_source=newsletter), this stops them creating duplicate pages in Google\'s index. Only needed if you track internal clicks with UTM.'],
                        ['rec'=>'✅ Recommended','name'=>'Enable /sitemap.xml','desc'=>'Generates a sitemap listing all your posts and pages. Submit this URL to Google Search Console so Google knows exactly what to crawl. Also automatically added to your robots.txt.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex search results','desc'=>'Prevents Google from indexing your WordPress search result pages (e.g. /?s=keyword). These pages have no unique value and waste Google\'s crawl budget — always block them.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex 404 pages','desc'=>'Stops Google indexing error pages. A 404 page has no content worth ranking — keeping these out of the index keeps your crawl budget focused on real content.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex attachment pages','desc'=>'WordPress creates a separate page for every uploaded image or file. These pages are near-empty and often outrank your actual posts for image searches. Always block them.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex author archives','desc'=>'On a single-author blog, your author archive page (/author/yourname/) is essentially a duplicate of your homepage. Blocking it prevents a duplicate content penalty.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex tag archives','desc'=>'Tag archive pages (/tag/aws/) often duplicate post content and can dilute your rankings. Unless your tag pages have unique introductory text and real editorial value, block them.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body" style="padding:16px 20px">
                <div class="ab-checkbox-grid">
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_og]" value="1" <?php checked((int)($o['enable_og'] ?? 0), 1); ?>> OpenGraph + Twitter Cards</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_website]" value="1" <?php checked((int)($o['enable_schema_website'] ?? 0), 1); ?>> WebSite JSON-LD (front page)</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_person]" value="1" <?php checked((int)($o['enable_schema_person'] ?? 0), 1); ?>> Person JSON-LD schema</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_article]" value="1" <?php checked((int)($o['enable_schema_article'] ?? 0), 1); ?>> BlogPosting JSON-LD schema</label>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_breadcrumbs]" value="1" <?php checked((int)($o['enable_schema_breadcrumbs'] ?? 0), 1); ?>> Breadcrumb JSON-LD schema</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[show_summary_box]" value="1" <?php checked((int)($o['show_summary_box'] ?? 1), 1); ?>> Show AI summary box on posts</label>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[strip_tracking_params]" value="1" <?php checked((int)($o['strip_tracking_params'] ?? 0), 1); ?>> Strip UTM params in canonical URLs</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_sitemap]" value="1" <?php checked((int)($o['enable_sitemap'] ?? 0), 1); ?>> Enable /sitemap.xml</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_search]" value="1" <?php checked((int)($o['noindex_search'] ?? 0), 1); ?>> noindex search results</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_404]" value="1" <?php checked((int)($o['noindex_404'] ?? 0), 1); ?>> noindex 404 pages</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_attachment]" value="1" <?php checked((int)($o['noindex_attachment'] ?? 0), 1); ?>> noindex attachment pages</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_author_archives]" value="1" <?php checked((int)($o['noindex_author_archives'] ?? 0), 1); ?>> noindex author archives</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_tag_archives]" value="1" <?php checked((int)($o['noindex_tag_archives'] ?? 0), 1); ?>> noindex tag archives</label>
                </div>
                </div>
                </div><!-- /ab-card-features -->

                <?php submit_button('Save Features &amp; Robots Settings'); ?>

                <div class="ab-zone-card ab-card-sitemap-settings">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⚙</span> Sitemap Settings</span>
                    <?php $this->explain_btn('sitemap', '⚙ Sitemap Settings — What each option does', [
                        ['rec'=>'✅ Recommended','name'=>'Enable /sitemap.xml','desc'=>'Generates a sitemap at yoursite.com/sitemap.xml listing all your published content. Submit this URL to Google Search Console so Google knows exactly what pages to crawl. Also automatically appends the sitemap URL to your robots.txt.'],
                        ['rec'=>'✅ Recommended','name'=>'Include Posts','desc'=>'Adds all your published blog posts to the sitemap. This should always be on — posts are your primary content and the main thing you want Google to discover and index.'],
                        ['rec'=>'✅ Recommended','name'=>'Include Pages','desc'=>'Adds your WordPress pages (About, Contact etc.) to the sitemap. Keep this on — pages like your About and Contact pages should be indexed.'],
                        ['rec'=>'⬜ Optional','name'=>'Taxonomy archives','desc'=>'Includes category, tag, and custom taxonomy archive pages in the sitemap. Turn this on only if your archive pages have unique introductory content and genuine value for visitors. For most blogs, leave it off — archive pages often duplicate post content.'],
                        ['rec'=>'⬜ Optional','name'=>'Exclude URLs or IDs','desc'=>'Enter specific URLs or post IDs to omit from the sitemap — one per line. Use this for thank-you pages, landing pages, privacy policy pages, or any content you don\'t want Google to prioritise. Numeric IDs (e.g. 42) refer to the WordPress post/page ID shown in the edit URL.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Enable sitemap:</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_sitemap]" value="1" <?php checked((int)($o['enable_sitemap'] ?? 0), 1); ?>>
                            Generate sitemap at <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_html(home_url('/sitemap.xml')); ?></a></label>
                            <p class="description">When enabled the Sitemap URL is also appended to your robots.txt automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Include post types:</th>
                        <td>
                            <div style="display:flex;gap:16px;flex-wrap:wrap">
                            <?php foreach ($pub_types as $pt): ?>
                                <?php if (in_array($pt->name, ['attachment'], true)) continue; ?>
                                <label>
                                    <input type="checkbox"
                                        name="<?php echo esc_attr(self::OPT); ?>[sitemap_post_types][]"
                                        value="<?php echo esc_attr($pt->name); ?>"
                                        <?php checked(in_array($pt->name, $sel_types, true), true); ?>>
                                    <?php echo esc_html($pt->labels->name); ?>
                                    <span style="color:#888;font-size:11px">(<?php echo esc_html($pt->name); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            </div>
                            <p class="description">Select which post types to include. Uncheck types that are not meaningful for search engines (e.g. WooCommerce order pages).</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Taxonomy archives:</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[sitemap_taxonomies]" value="1" <?php checked((int)($o['sitemap_taxonomies'] ?? 0), 1); ?>>
                            Include category, tag, and custom taxonomy archive pages</label>
                            <p class="description">Off by default. Enable if your category or tag archive pages have unique, valuable content worth indexing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Exclude URLs or IDs:</th>
                        <td>
                            <textarea name="<?php echo esc_attr(self::OPT); ?>[sitemap_exclude]"
                                rows="6" style="width:100%"
                                placeholder="e.g. https://yoursite.com/thank-you/<?php echo "\n"; ?>e.g. https://yoursite.com/privacy-policy/<?php echo "\n"; ?>e.g. 42<?php echo "\n"; ?>e.g. 156"><?php echo esc_textarea((string)($o['sitemap_exclude'] ?? '')); ?></textarea>
                            <p class="description">One entry per line. Enter full URLs or numeric post/page IDs. These will be omitted from the sitemap.</p>
                        </td>
                    </tr>
                </table>
                </div>
                </div><!-- /ab-card-sitemap-settings -->

                <?php submit_button('Save Sitemap Settings'); ?>
            </form>

            <hr class="ab-zone-divider">

            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>

                <div class="ab-zone-card ab-card-robots">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🤖</span> Robots.txt</span>
                    <?php $this->explain_btn('robots', '🤖 Robots.txt — What this all means', [
                        ['rec'=>'ℹ️ Info','name'=>'What is robots.txt?','desc'=>'A plain text file at yoursite.com/robots.txt that tells search engine crawlers which pages they are and aren\'t allowed to visit. It doesn\'t prevent indexing — it prevents crawling. Google respects it; malicious bots ignore it entirely.'],
                        ['rec'=>'ℹ️ Info','name'=>'Physical file warning','desc'=>'If a robots.txt file exists on disk, the web server serves it directly — bypassing WordPress and this plugin completely. You must rename or delete it to let the plugin take control. The plugin offers a one-click rename to robots.txt.bak.'],
                        ['rec'=>'⬜ Optional','name'=>'Block AI training bots','desc'=>'Adds Disallow: / rules for GPTBot, CCBot, Claude-Web, anthropic-ai and other AI training crawlers. Turn this ON if you don\'t want AI companies training their models on your content. Leave OFF if you want AI assistants to surface your content when users ask relevant questions.'],
                        ['rec'=>'✅ Recommended','name'=>'Custom robots.txt rules','desc'=>'The full content of your robots.txt file. The plugin automatically appends your sitemap URL and the AI bot blocklist (if enabled) — do not add those here manually. Changes take effect immediately on every request — there is no caching.'],
                        ['rec'=>'ℹ️ Info','name'=>'User-agent: Googlebot','desc'=>'Rules that apply specifically to Google\'s crawler. Googlebot respects these rules more strictly than other crawlers. Disallowing /wp-admin/, /wp-login.php and search pages stops Google wasting crawl budget on admin and junk pages.'],
                        ['rec'=>'ℹ️ Info','name'=>'User-agent: *','desc'=>'Rules that apply to all other crawlers not specifically named above. This is the catch-all for Bing, DuckDuckGo, and any other well-behaved search engine crawler.'],
                        ['rec'=>'ℹ️ Info','name'=>'Live preview','desc'=>'Shows exactly what search engines see when they fetch yoursite.com/robots.txt right now. If the sitemap URL appears at the bottom, everything is working correctly.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">

                <?php
                $physical_exists   = file_exists(ABSPATH . 'robots.txt');
                $physical_writable = $physical_exists && wp_is_writable(ABSPATH . 'robots.txt');
                $physical_contents = $physical_exists ? file_get_contents(ABSPATH . 'robots.txt') : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

                // Also check one level up in case WordPress is in a subdirectory
                $alt_path          = dirname(rtrim(ABSPATH, '/')) . '/robots.txt';
                $alt_exists        = file_exists($alt_path);
                ?>
                <div style="background:#f0f6fc;border:1px solid #c2d9f0;border-radius:4px;padding:10px 14px;margin:12px 20px;font-size:12px;font-family:monospace">
                    <strong>File detection:</strong><br>
                    ABSPATH: <code><?php echo esc_html(ABSPATH); ?></code><br>
                    Looking for: <code><?php echo esc_html(ABSPATH . 'robots.txt'); ?></code> → <?php echo $physical_exists ? '<span style="color:#1a7a34">found</span>' : '<span style="color:#1a7a34">not found</span>'; ?><br>
                    <?php if (!$physical_exists): ?>
                    Also checking: <code><?php echo esc_html($alt_path); ?></code> → <?php echo $alt_exists ? '<span style="color:#e67e00">found here!</span>' : '<span style="color:#1a7a34">not found</span>'; ?>
                    <?php endif; ?>
                </div>

                <?php
                // If file not at ABSPATH, try one level up (WordPress in subdirectory)
                $robots_path = ABSPATH . 'robots.txt';
                if (!$physical_exists && $alt_exists) {
                    $robots_path     = $alt_path;
                    $physical_exists = true;
                    $physical_writable = wp_is_writable($alt_path);
                    $physical_contents = file_get_contents($alt_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                }
                ?>
                <?php if ($physical_exists): ?>
                <div class="ab-physical-robots-warn" id="ab-physical-robots-warn">
                    <div style="font-size:22px;flex-shrink:0">⚠️</div>
                    <div style="flex:1">
                        <strong>A physical robots.txt file exists on your server</strong><br>
                        WordPress (and this plugin) cannot control your robots.txt while a real file exists on disk — the web server serves the file directly, bypassing WordPress entirely. To let the plugin manage your robots.txt, the file needs to be renamed.<br><br>
                        <strong>Current file location:</strong> <code><?php echo esc_html($robots_path); ?></code>
                        &nbsp;·&nbsp; <strong>Writable:</strong> <?php echo $physical_writable ? '<span style="color:#1a7a34">Yes</span>' : '<span style="color:#c3372b">No</span>'; ?><br><br>
                        <?php if ($physical_contents): ?>
                        <strong>Current file contents:</strong><br>
                        <pre style="background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;padding:10px;font-size:12px;line-height:1.6;max-height:200px;overflow-y:auto;margin:6px 0 12px"><?php echo esc_html($physical_contents); ?></pre>
                        <?php endif; ?>
                        <strong>What happens when you click Rename:</strong> The file is renamed to <code>robots.txt.bak</code> in the same directory. WordPress then takes over and this plugin generates robots.txt dynamically on every request.<br><br>
                        <?php if ($physical_writable): ?>
                        <button type="button" class="button button-primary" id="ab-rename-robots-btn" onclick="abRenameRobots()">
                            ✎ Rename robots.txt → robots.txt.bak
                        </button>
                        <span id="ab-rename-robots-status" style="margin-left:10px;font-size:13px"></span>
                        <?php else: ?>
                        <div style="background:#fef0f0;border:1px solid #f5bcbb;border-radius:4px;padding:10px;margin-top:4px">
                            <strong style="color:#c3372b">File is not writable</strong> — the web server does not have permission to rename this file.<br>
                            Fix via FTP or your host's file manager: right-click <code>robots.txt</code> → set permissions to <strong>644</strong>, then reload this page.<br><br>
                            Alternatively, rename the file manually via FTP: rename <code>robots.txt</code> to <code>robots.txt.bak</code> in your WordPress root.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="display:flex;gap:12px;align-items:flex-start;background:#edfaef;border:1px solid #1a7a34;border-radius:6px;padding:14px 18px;margin:12px 20px">
                    <div style="font-size:22px;flex-shrink:0">✅</div>
                    <div style="font-size:13px">
                        <strong>No physical robots.txt file detected</strong> — this plugin is managing your robots.txt dynamically. Search engines will see the content shown in the Live robots.txt preview below.<br><br>
                        <span style="color:#50575e">If you recently deleted or renamed the file manually, this is correct. The Live preview below shows exactly what Google will see.</span>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Live robots.txt preview */ ?>
                <div style="padding:16px 20px 4px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <div>
                            <strong style="font-size:13px">Live robots.txt</strong>
                            &nbsp;<a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" style="font-size:12px">↗ view in browser</a>
                        </div>
                        <div style="display:flex;gap:6px">
                            <button type="button" class="button" style="font-size:11px;padding:2px 10px" id="ab-robots-live-copy" onclick="abCopyRobotsLive()">⎘ Copy</button>
                            <button type="button" class="button" style="font-size:11px;padding:2px 10px" onclick="abRefreshRobotsPreview()">↻ Refresh</button>
                        </div>
                    </div>
                    <pre id="ab-robots-live-preview" style="background:#1a1a2e;color:#e0e0f0;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;padding:14px;border-radius:6px;max-height:320px;overflow-y:auto;margin:0;white-space:pre-wrap;word-break:break-word">Loading…</pre>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th>Block AI training bots:</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[block_ai_bots]" value="1" <?php checked((int)($o['block_ai_bots'] ?? 1), 1); ?>>
                            Block GPTBot, ChatGPT-User, CCBot, anthropic-ai, Claude-Web, FacebookBot, Bytespider, Applebot-Extended</label>
                            <p class="description">Adds <code>Disallow: /</code> for each AI training crawler. Appended automatically after your custom rules below.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cs-robots-txt">Custom robots.txt rules</label></th>
                        <td>
                            <textarea id="cs-robots-txt" name="<?php echo esc_attr(self::OPT); ?>[robots_txt]"
                                rows="16" style="width:100%"><?php echo esc_textarea((string)($o['robots_txt'] ?? self::default_robots_txt())); ?></textarea>
                            <p class="description">Full robots.txt content. The AI bot blocklist (if enabled) and your sitemap URL are appended automatically — do not add them here. Changes take effect immediately at <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/robots.txt')); ?></a></p>
                            <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:8px">
                                <button type="button" class="button" id="ab-robots-copy" onclick="abCopyRobots()">⎘ Copy</button>
                                <button type="button" class="button" onclick="document.getElementById('cs-robots-txt').value=<?php echo wp_json_encode(self::default_robots_txt()); ?>">Reset to default</button>
                            </div>
                        </td>
                    </tr>
                </table>
                </div>
                </div><!-- /ab-card-robots -->

                <?php submit_button('Save Robots Settings'); ?>
            </form>

            <hr class="ab-zone-divider">

            <div class="ab-zone-card ab-card-sitemap-preview">
            <div class="ab-zone-header" style="justify-content:space-between;align-items:center">
                <span><span class="ab-zone-icon">🔍</span> Sitemap Preview</span>
                <div style="display:flex;gap:8px;align-items:center">
                    <?php $this->explain_btn('sitemappreview', '🔍 Sitemap Preview — How to use this', [
                        ['rec'=>'ℹ️ Info','name'=>'What this shows','desc'=>'A table of every URL that will appear in your sitemap.xml when Google crawls it. This is the live data — if a post appears here, it is in your sitemap. If it doesn\'t appear, Google won\'t find it via the sitemap.'],
                        ['rec'=>'ℹ️ Info','name'=>'Type badges','desc'=>'Each row shows the content type: Post (blog post), Page (WordPress page), Home (your homepage), Taxonomy (category/tag archive). Use this to verify the right content types are being included based on your Sitemap Settings.'],
                        ['rec'=>'ℹ️ Info','name'=>'Last Modified','desc'=>'The date the post was last updated. Google uses this to decide how often to re-crawl a page. Recently updated posts get re-crawled sooner. If a post shows an old date, consider updating it to signal freshness.'],
                        ['rec'=>'ℹ️ Info','name'=>'Pagination','desc'=>'Results are shown 200 at a time. Use Prev/Next to browse all your URLs. The count at the bottom right shows which URLs you\'re viewing out of the total.'],
                        ['rec'=>'ℹ️ Info','name'=>'View live sitemap','desc'=>'The link opens your actual sitemap.xml in a new tab — this is what Google sees. The index file lists all your sub-sitemaps (one per post type). Click through to see the raw XML.'],
                    ]); ?>
                    <button id="ab-sitemap-load" onclick="abLoadSitemap();return false;"
                        style="background:#f0b429;border:none;border-radius:6px;color:#1d2327;font-size:13px;font-weight:700;padding:7px 18px;cursor:pointer;letter-spacing:0.02em;box-shadow:0 2px 6px rgba(0,0,0,0.25);transition:background 0.15s">
                        ⬇ Load Preview
                    </button>
                    <button id="ab-sitemap-copy" onclick="abCopySitemap()" class="button"
                        style="font-size:11px;padding:2px 10px;margin-left:6px">
                        ⎘ Copy URLs
                    </button>
                </div>
            </div>
            <div class="ab-zone-body" style="padding:16px 20px">
                <p style="color:#50575e;margin:0 0 14px;font-size:13px">Shows all URLs that will appear in your sitemap. Paginated at 200 rows — use Prev/Next to browse. Save settings before previewing.</p>
                <div id="ab-sitemap-preview-wrap">
                    <p style="color:#a7aaad;font-size:13px">Click <strong>Load Preview</strong> to fetch the current sitemap contents.</p>
                </div>
            </div>
            </div><!-- /ab-card-sitemap-preview -->

            <?php /* ── llms.txt Card ── */ ?>
            <div class="ab-zone-card ab-card-llms">
            <div class="ab-zone-header" style="justify-content:space-between">
                <span><span class="ab-zone-icon">🤖</span> llms.txt — LLM Crawler Guidance</span>
                <?php $this->explain_btn('llms', '🤖 llms.txt — What this does', [
                    ['rec'=>'✅ Recommended','name'=>'What is llms.txt','desc'=>'llms.txt is an emerging standard (proposed 2024) that helps large language model crawlers like ChatGPT, Claude, and Perplexity understand your site\'s content structure. It\'s a plain-text markdown file served at yoursite.com/llms.txt listing your posts, pages, and descriptions — similar to what sitemap.xml does for traditional search engines, but optimised for AI indexing.'],
                    ['rec'=>'✅ Recommended','name'=>'Enable /llms.txt','desc'=>'Serves a dynamically generated llms.txt at yoursite.com/llms.txt. The file is built from your published posts and pages, using your AI-generated meta descriptions as the per-post summaries. Enable this if you want AI assistants and LLM-powered search engines to have an accurate, structured view of your site content.'],
                    ['rec'=>'ℹ️ Info','name'=>'What it contains','desc'=>'The file includes your site name, site description, author name and title, and a structured list of all published posts and pages with their URLs and meta descriptions. Posts with no meta description are listed without a summary — another reason to run Generate Missing first.'],
                    ['rec'=>'ℹ️ Info','name'=>'Preview','desc'=>'Click Load Preview to see exactly what the file currently contains. The preview reflects live data — if you generate new meta descriptions, reload the preview to see the updated content.'],
                ]); ?>
            </div>
            <div class="ab-zone-body" style="padding:20px 24px 24px">
                <form method="post" action="options.php">
                    <?php settings_fields('cs_seo_group'); ?>
                    <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[_partial]" value="1">
                    <table class="form-table" role="presentation" style="margin-top:0">
                        <tr>
                            <th style="width:200px">Enable /llms.txt:</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_llms_txt]" value="1" <?php checked((int)($o['enable_llms_txt'] ?? 0), 1); ?>>
                                    Serve <code>llms.txt</code> at <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/llms.txt')); ?></a>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save llms.txt Settings'); ?>
                </form>

                <div style="margin-top:8px;border-top:1px solid #f0f0f0;padding-top:16px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <button id="ab-llms-load" class="button" style="background:#1a4a8a;color:#fff;border-color:#143a6e">🔍 Load Preview</button>
                        <button id="ab-llms-copy" class="button" style="font-size:11px;padding:2px 10px" onclick="abCopyLlms()">⎘ Copy</button>
                        <?php if ((int)($o['enable_llms_txt'] ?? 0)): ?>
                        <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="button">↗ View Live File</a>
                        <?php endif; ?>
                    </div>
                    <div id="ab-llms-preview-wrap">
                        <p style="color:#a7aaad;font-size:13px">Click <strong>Load Preview</strong> to see what LLM crawlers will receive.</p>
                    </div>
                </div>
            </div>
            </div><!-- /ab-card-llms -->
            <script>
            (function() {
                var _ajax  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                var _nonce = <?php echo wp_json_encode(wp_create_nonce('cs_seo_nonce')); ?>;
                document.addEventListener('DOMContentLoaded', function() {
                    var btn  = document.getElementById('ab-llms-load');
                    var wrap = document.getElementById('ab-llms-preview-wrap');
                    if (!btn || !wrap) return;
                    btn.addEventListener('click', function() {
                        btn.disabled = true;
                        btn.textContent = '⟳ Loading...';
                        wrap.innerHTML = '<p style="color:#666;font-size:13px">Fetching llms.txt content…</p>';
                        fetch(_ajax, {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'action=cs_seo_llms_preview&nonce=' + encodeURIComponent(_nonce)
                        })
                        .then(function(r){ return r.json(); })
                        .then(function(data) {
                            btn.disabled = false;
                            btn.textContent = '↻ Reload Preview';
                            if (data.success && data.data.content) {
                                var lines = data.data.content.split('\n').length;
                                // Store raw content for copy button
                                wrap.dataset.raw = data.data.content;
                                // Syntax-highlight the markdown
                                var highlighted = data.data.content
                                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                                    .replace(/^(# .+)$/gm, '<span style="color:#e2c97e;font-size:14px;font-weight:700">$1</span>')
                                    .replace(/^(## .+)$/gm, '<span style="color:#7eb8e2;font-weight:600">$1</span>')
                                    .replace(/^(&gt; .+)$/gm, '<span style="color:#a8d8a8;font-style:italic">$1</span>')
                                    .replace(/^(Author:.+)$/gm, '<span style="color:#c9a8e2">$1</span>')
                                    .replace(/(\[([^\]]+)\]\([^)]+\))/g, function(m) {
                                        return m.replace(/\[([^\]]+)\]/, '<span style="color:#7eb8e2">[$1]</span>');
                                    });
                                wrap.innerHTML =
                                    '<div style="margin-bottom:8px;font-size:12px;color:#50575e">' + lines + ' lines — ' + data.data.content.length + ' characters</div>' +
                                    '<pre id="ab-llms-pre" style="background:#1a1a2e;color:#d0d8e8;font-family:Courier New,monospace;font-size:12px;line-height:1.7;padding:16px;border-radius:6px;max-height:400px;overflow:auto;white-space:pre-wrap;word-break:break-word;border:1px solid #2a2a4a">' +
                                    highlighted +
                                    '</pre>';
                            } else {
                                wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Failed to load preview.</div>';
                            }
                        })
                        .catch(function(e) {
                            btn.disabled = false;
                            btn.textContent = '↻ Reload Preview';
                            wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Network error: ' + e.message + '</div>';
                        });
                    });
                });
            })();
            </script>
            <script>
            (function() {
                var _ajax  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                var _nonce = <?php echo wp_json_encode(wp_create_nonce('cs_seo_nonce')); ?>;

                function loadSitemapPreview(pg) {
                    var wrap = document.getElementById('ab-sitemap-preview-wrap');
                    var btn  = document.getElementById('ab-sitemap-load');
                    if (!wrap || !btn) return;
                    btn.disabled = true;
                    btn.textContent = '⟳ Loading...';
                    btn.style.background = '#c0882a';
                    wrap.innerHTML = '<p style="color:#666;font-size:13px">Fetching sitemap entries…</p>';
                    if (!(pg > 1)) window._abSitemapUrls = [];

                    var body = 'action=cs_seo_sitemap_preview&nonce='+encodeURIComponent(_nonce)+'&sitemap_pg='+(pg||1);
                    fetch(_ajax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:body})
                        .then(function(r){ return r.text(); })
                        .then(function(txt) {
                            btn.disabled = false;
                            btn.textContent = '↻ Reload';
                            btn.style.background = '#f0b429';
                            var data;
                            try { data = JSON.parse(txt); } catch(e) {
                                wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Response was not JSON. Check for PHP errors.</div>';
                                return;
                            }
                            if (!data.success) {
                                wrap.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Error: '+(data.data||'unknown')+'</div>';
                                return;
                            }
                            var d=data.data, entries=d.entries, total=d.total, page=d.page, pages=d.pages, per=d.per_page;
                            window._abSitemapUrls = (window._abSitemapUrls || []).concat(entries.map(function(e){ return e.loc; }));
                            var cols={home:'#6b3fa0',post:'#1a4a7a',page:'#1a7a34',tax:'#7a5c00',cpt:'#8a3a00'};
                            var labels={home:'Home',post:'Post',page:'Page',tax:'Taxonomy',cpt:'CPT'};
                            var rows=entries.map(function(e){
                                return '<tr style="border-bottom:1px solid #f0f0f0">'+
                                    '<td style="padding:6px 8px"><a href="'+e.loc+'" target="_blank" style="font-size:12px;color:#2271b1">'+e.loc+'</a>'+(e.title?'<br><small style="color:#888;font-size:11px">'+e.title+'</small>':'')+'</td>'+
                                    '<td style="padding:6px 8px"><span style="background:'+(cols[e.type]||'#444')+';color:#fff;border-radius:3px;padding:2px 8px;font-size:11px;white-space:nowrap">'+(labels[e.type]||e.type)+'</span></td>'+
                                    '<td style="padding:6px 8px;color:#888;font-size:12px;white-space:nowrap">'+(e.lastmod||'—')+'</td></tr>';
                            }).join('');
                            var pager='';
                            if(pages>1){
                                pager='<div style="display:flex;gap:10px;align-items:center;margin-top:14px;flex-wrap:wrap">'+
                                    '<button class="button" '+(page<=1?'disabled':'onclick="window._abSitemapLoad('+(page-1)+')"')+'>← Prev</button>'+
                                    '<span style="font-size:13px;color:#50575e">Page <strong>'+page+'</strong> of <strong>'+pages+'</strong></span>'+
                                    '<button class="button button-primary" '+(page>=pages?'disabled':'onclick="window._abSitemapLoad('+(page+1)+')"')+'>Next →</button>'+
                                    '<span style="font-size:12px;margin-left:auto;color:#888">'+((page-1)*per+1)+'–'+Math.min(page*per,total)+' of '+total+' URLs</span>'+
                                    '</div>';
                            }
                            wrap.innerHTML=
                                '<p style="font-size:13px;margin:0 0 12px;color:#1d2327"><strong>'+total+'</strong> total URLs across <strong>'+pages+'</strong> sitemap file'+(pages>1?'s':'')+
                                ' &nbsp;·&nbsp; <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank" style="color:#2271b1">View live sitemap ↗</a></p>'+
                                '<table style="width:100%;border-collapse:collapse;font-size:13px;background:#fff;border:1px solid #e0e0e0;border-radius:4px;overflow:hidden">'+
                                '<thead><tr style="background:#f6f7f7;border-bottom:2px solid #e0e0e0">'+
                                '<th style="text-align:left;padding:8px 8px;font-size:12px;color:#50575e;font-weight:600">URL</th>'+
                                '<th style="text-align:left;padding:8px 8px;font-size:12px;color:#50575e;font-weight:600">Type</th>'+
                                '<th style="text-align:left;padding:8px 8px;font-size:12px;color:#50575e;font-weight:600">Last Modified</th></tr></thead>'+
                                '<tbody>'+rows+'</tbody></table>'+pager;
                        })
                        .catch(function(e){
                            btn.disabled=false; btn.textContent='↻ Reload'; btn.style.background='#f0b429';
                            wrap.innerHTML='<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px;border-radius:4px">Network error: '+e.message+'</div>';
                        });
                }
                window.abLoadSitemap  = loadSitemapPreview;
                window._abSitemapLoad = loadSitemapPreview;
                document.addEventListener('DOMContentLoaded', function() {
                    var b = document.getElementById('ab-sitemap-load');
                    if (b) b.addEventListener('click', function(e){ e.preventDefault(); loadSitemapPreview(1); });
                });
            })();
            </script>

            <?php /* ── HTTPS Fix Card ── */ ?>
            <div class="ab-zone-card ab-card-https">
            <div class="ab-zone-header">
                <span><span class="ab-zone-icon">🔒</span> Mixed Content Fix — HTTP → HTTPS</span>
            </div>
            <div class="ab-zone-body" style="padding:20px 24px 24px">
                <p style="color:#50575e;font-size:13px;margin:0 0 16px">Scans your database for assets and links still using <code>http://</code> and replaces them with <code>https://</code>. Fixes posts, pages, metadata, options, and comments in one operation.</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
                    <button type="button" class="button" id="ab-https-scan-btn" style="background:#b45309;color:#fff;border-color:#92400e;font-weight:600">
                        🔍 Scan for HTTP references
                    </button>
                    <button type="button" class="button" id="ab-https-fix-btn" style="display:none;background:#7a1a1a;color:#fff;border-color:#5a0e0e;font-weight:600">
                        🔧 Fix all HTTP → HTTPS
                    </button>
                    <span id="ab-https-status" style="font-size:13px;color:#50575e"></span>
                </div>
                <div id="ab-https-results"></div>
            </div>
            </div><!-- /ab-card-https -->
            <script>
            (function() {
                var _ajax  = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
                var _nonce = <?php echo wp_json_encode(wp_create_nonce('cs_seo_nonce')); ?>;
                var scanBtn   = document.getElementById('ab-https-scan-btn');
                var fixBtn    = document.getElementById('ab-https-fix-btn');
                var statusEl  = document.getElementById('ab-https-status');
                var resultsEl = document.getElementById('ab-https-results');

                var th  = 'padding:6px 12px;border-bottom:2px solid #8c8f94;background:#f0f0f1;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#1d2327;font-weight:700;position:sticky;top:0';
                var td  = 'padding:6px 10px;border-bottom:1px solid #dcdcde;font-family:monospace;font-size:11px;color:#1d2327;word-break:break-all';

                function setStatus(msg, color) {
                    statusEl.textContent = msg;
                    statusEl.style.color = color || '#50575e';
                }
                function esc(s) {
                    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                }
                function getCheckedDomains() {
                    return Array.from(document.querySelectorAll('.ab-https-domain-cb:checked')).map(function(cb){ return cb.value; });
                }

                // Safe fetch wrapper — always reads raw text first so a PHP fatal
                // (which returns HTML, not JSON) shows the actual error message
                // rather than a useless "Unexpected token '<'" SyntaxError.
                function safeFetch(url, opts) {
                    return fetch(url, opts).then(function(r) {
                        return r.text().then(function(text) {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                // Strip HTML tags to surface the plain-text PHP message
                                var plain = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 400);
                                throw new Error('Server returned non-JSON response:\n' + plain);
                            }
                        });
                    });
                }

                if (scanBtn) scanBtn.addEventListener('click', function() {
                    scanBtn.disabled = true;
                    fixBtn.style.display = 'none';
                    setStatus('Scanning…', '#50575e');
                    resultsEl.innerHTML = '';
                    safeFetch(_ajax, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_https_scan&nonce=' + encodeURIComponent(_nonce)
                    })
                    .then(function(data) {
                        scanBtn.disabled = false;

                        if (!data.success) {
                            var msg = typeof data.data === 'string' ? data.data : (data.data && data.data.message) ? data.data.message : JSON.stringify(data.data);
                            setStatus('Scan failed: ' + msg, '#c3372b');
                            return;
                        }

                        var d = data.data;
                        if (d.total === 0) {
                            setStatus('\u2705 No HTTP references found \u2014 your site is clean!', '#1a7a34');
                            return;
                        }
                        setStatus('', '');

                        // Summary by table/column
                        var summaryRows = d.counts.map(function(c) {
                            return '<tr><td style="' + td + '">' + esc(c.table) + '</td>' +
                                '<td style="' + td + '">' + esc(c.column) + '</td>' +
                                '<td style="' + td + ';font-weight:700;color:#b45309;text-align:right">' + c.count + '</td></tr>';
                        }).join('');

                        // Per-domain rows — behaviour differs by domain type
                        var domainRows = Object.entries(d.domain_meta).map(function(entry) {
                            var domain = entry[0], meta = entry[1];
                            var uid = 'ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-');

                            // Build collapsible URL sample list
                            var urls = meta.urls || [];
                            var sampleHtml = '';
                            if (urls.length > 0) {
                                var initialShow = 3;
                                var makeUrlDiv = function(u) {
                                    return '<div style="color:#555;font-size:10px;margin-left:22px;margin-top:1px;word-break:break-all;font-family:monospace">' + esc(u.url) +
                                        ' <span style="color:#8080b0;font-size:9px">(' + esc(u.table) + '.' + esc(u.column) + ')</span></div>';
                                };
                                var visibleUrls = urls.slice(0, initialShow).map(makeUrlDiv).join('');
                                var hiddenUrls  = urls.length > initialShow ? urls.slice(initialShow).map(makeUrlDiv).join('') : '';
                                var showMoreBtn = hiddenUrls
                                    ? '<button type="button" onclick="var h=document.getElementById(\'' + uid + '\');h.style.display=h.style.display===\'none\'?\'block\':\'none\';this.textContent=h.style.display===\'none\'?\'\u25b6 Show all ' + urls.length + ' URLs\':\'\u25b2 Hide\'" style="background:none;border:none;color:#2271b1;font-size:10px;cursor:pointer;padding:2px 0 0 22px;margin:0">\u25b6 Show all ' + urls.length + ' URLs</button>'
                                    : '';
                                sampleHtml = visibleUrls +
                                    (hiddenUrls ? '<div id="' + uid + '" style="display:none">' + hiddenUrls + '</div>' + showMoreBtn : '');
                                if (meta.count > urls.length) {
                                    sampleHtml += '<div style="color:#8080b0;font-size:10px;margin-left:22px;margin-top:2px">\u2026 and ' + (meta.count - urls.length) + ' more rows in the database (showing first ' + urls.length + ' samples)</div>';
                                }
                            }

                            var domainBadge = '<code style="background:#f0f0f1;padding:1px 5px;border-radius:3px;font-size:12px">' + esc(domain) + '</code>' +
                                '<span style="color:#888;font-size:11px;margin-left:6px">' + meta.count + ' row' + (meta.count !== 1 ? 's' : '') + ' in DB</span>';

                            // --- IP address: cannot be fixed, offer Remove action ---
                            if (meta.is_ip) {
                                return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1" id="ab-domain-row-' + uid + '">' +
                                    '<div style="display:flex;align-items:flex-start;gap:8px">' +
                                    '<span style="margin-top:2px;font-size:14px">\u26d4</span>' +
                                    '<div style="flex:1">' +
                                    domainBadge +
                                    '<span style="color:#c3372b;font-size:10px;margin-left:6px">\u26a0 IP address \u2014 cannot have an SSL cert. These URLs must be removed or replaced, not flipped to HTTPS.</span>' +
                                    '<br><button type="button" data-domain="' + esc(domain) + '" class="ab-https-remove-ip button button-small" style="margin-top:5px;background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px">\u{1F5D1} Remove these ' + meta.count + ' row' + (meta.count !== 1 ? 's' : '') + '</button>' +
                                    '</div></div>' +
                                    sampleHtml + '</div>';
                            }

                            // --- Spam (comment-only domain): offer Delete comments action ---
                            if (meta.is_spam) {
                                return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1" id="ab-domain-row-' + uid + '">' +
                                    '<div style="display:flex;align-items:flex-start;gap:8px">' +
                                    '<span style="margin-top:2px;font-size:14px">\u{1F6AB}</span>' +
                                    '<div style="flex:1">' +
                                    domainBadge +
                                    '<span style="color:#c3372b;font-size:10px;margin-left:6px">\u26a0 spam comment domain \u2014 delete the comments rather than fixing the URL</span>' +
                                    '<br><button type="button" data-domain="' + esc(domain) + '" class="ab-https-delete-spam button button-small" style="margin-top:5px;background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px">\u{1F5D1} Delete comments from ' + esc(domain) + '</button>' +
                                    '</div></div>' +
                                    sampleHtml + '</div>';
                            }

                            // --- Normal fixable domain ---
                            var checked = ' checked';
                            if (domain.match(/example\.|yoursite\.|placeholder/i)) {
                                checked = '';  // placeholder — opt-out by default
                            }
                            var ownBadge = meta.is_own ? '<span style="color:#1a7a34;font-size:10px;margin-left:6px">\u2713 your domain</span>' : '';

                            // Core URL options (siteurl / home) that appear in wp_options
                            var coreOpts = meta.core_url_options || [];
                            var overridden = meta.overridden_by_wpconfig || [];
                            var coreWarn = '';
                            if (overridden.length > 0) {
                                // wp-config.php has WP_HOME/WP_SITEURL defined as http://
                                // Fixing the DB row is pointless — the constant overwrites it on every request
                                coreWarn = '<div style="margin-top:6px;padding:8px 10px;background:#fff8e1;border:1px solid #f0c040;border-radius:3px;font-size:11px;color:#5a4000">' +
                                    '\u26a0 <strong>This row keeps reverting because <code>WP_' + overridden.map(function(o){return o.toUpperCase();}).join('</code> / <code>WP_') + '</code> ' +
                                    (overridden.length === 1 ? 'is' : 'are') + ' hardcoded in <code>wp-config.php</code>.</strong><br>' +
                                    'Database fixes are overwritten every time WordPress loads. To permanently fix this, edit <code>wp-config.php</code> and change the constant' +
                                    (overridden.length > 1 ? 's' : '') + ' to use <code>https://</code>:<br>' +
                                    '<code style="display:block;margin-top:4px;background:#f5f5f5;padding:4px 6px;border-radius:2px">' +
                                    overridden.map(function(o) {
                                        return "define( 'WP_" + o.toUpperCase() + "', 'https://" + esc(domain) + "' );";
                                    }).join('<br>') + '</code></div>';
                                checked = '';  // don't offer the DB fix when it won't stick
                            } else if (coreOpts.length > 0) {
                                // In wp_options but no wp-config override — DB fix will work,
                                // but warn that this is the core site URL
                                coreWarn = '<div style="margin-top:5px;font-size:11px;color:#5a4000">' +
                                    '\u2139 This appears in the core WordPress <code>' + coreOpts.join('</code> / <code>') + '</code> option' +
                                    (coreOpts.length > 1 ? 's' : '') + '. Fixing it here will work, but also update <code>wp-config.php</code> if those constants are defined there.</div>';
                            }

                            return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1">' +
                                '<label style="display:flex;align-items:flex-start;gap:6px;cursor:pointer">' +
                                '<input type="checkbox" class="ab-https-domain-cb" value="' + esc(domain) + '"' + checked + ' style="margin-top:3px;flex-shrink:0">' +
                                '<span>' + domainBadge + ownBadge + '</span></label>' +
                                coreWarn +
                                sampleHtml + '</div>';
                        }).join('');

                        resultsEl.innerHTML =
                            '<p style="font-size:13px;font-weight:600;color:#b45309;margin:0 0 10px">Found ' + d.total + ' row' + (d.total!==1?'s':'') + ' with HTTP references</p>' +
                            '<table style="width:100%;border-collapse:collapse;margin-bottom:14px">' +
                            '<thead><tr><th style="' + th + '">Table</th><th style="' + th + '">Column</th><th style="' + th + ';text-align:right">Rows</th></tr></thead>' +
                            '<tbody>' + summaryRows + '</tbody></table>' +
                            '<p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 6px">Select domains to fix:</p>' +
                            '<div style="background:#fafafa;border:1px solid #dcdcde;border-radius:4px;padding:8px 12px;margin-bottom:10px">' + domainRows + '</div>' +
                            '<p style="font-size:11px;color:#888;margin:0">Serialized data (theme settings, widget options) will be re-serialized safely to preserve byte counts.</p>';

                        // Wire up Delete spam buttons
                        resultsEl.querySelectorAll('.ab-https-delete-spam').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var domain = btn.dataset.domain;
                                if (!confirm('Permanently delete all comments from ' + domain + '?\n\nThis cannot be undone.')) return;
                                btn.disabled = true;
                                btn.textContent = 'Deleting\u2026';
                                safeFetch(_ajax, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=cs_seo_https_delete&nonce=' + encodeURIComponent(_nonce) + '&domain=' + encodeURIComponent(domain)
                                }).then(function(r) {
                                    if (r.success) {
                                        var row = document.getElementById('ab-domain-row-ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-'));
                                        if (row) {
                                            row.innerHTML = '<div style="color:#1a7a34;font-size:12px;padding:4px 0">\u2705 Deleted ' + r.data.deleted + ' comment' + (r.data.deleted !== 1 ? 's' : '') + ' from ' + esc(domain) + '</div>';
                                        }
                                    } else {
                                        btn.disabled = false;
                                        btn.textContent = '\u{1F5D1} Delete comments from ' + domain;
                                        alert('Delete failed: ' + (r.data || 'unknown error'));
                                    }
                                }).catch(function(e) {
                                    btn.disabled = false;
                                    alert('Delete error: ' + e.message);
                                });
                            });
                        });

                        // Wire up Remove IP buttons
                        resultsEl.querySelectorAll('.ab-https-remove-ip').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var domain = btn.dataset.domain;
                                if (!confirm('Permanently delete all database rows containing ' + domain + '?\n\nThis cannot be undone. Ensure you have a backup.')) return;
                                btn.disabled = true;
                                btn.textContent = 'Deleting\u2026';
                                safeFetch(_ajax, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=cs_seo_https_delete&nonce=' + encodeURIComponent(_nonce) + '&domain=' + encodeURIComponent(domain)
                                }).then(function(r) {
                                    if (r.success) {
                                        var row = document.getElementById('ab-domain-row-ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-'));
                                        if (row) {
                                            row.innerHTML = '<div style="color:#1a7a34;font-size:12px;padding:4px 0">\u2705 Deleted ' + r.data.deleted + ' item' + (r.data.deleted !== 1 ? 's' : '') + ' containing ' + esc(domain) + '</div>';
                                        }
                                    } else {
                                        btn.disabled = false;
                                        btn.textContent = '\u{1F5D1} Remove these rows';
                                        alert('Delete failed: ' + (r.data || 'unknown error'));
                                    }
                                }).catch(function(e) {
                                    btn.disabled = false;
                                    alert('Delete error: ' + e.message);
                                });
                            });
                        });

                        fixBtn.style.display = '';
                    })
                    .catch(function(e) {
                        scanBtn.disabled = false;
                        setStatus('', '');
                        resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Scan error:\n' + esc(e.message) + '</div>';
                    });
                });

                if (fixBtn) fixBtn.addEventListener('click', function() {
                    var domains = getCheckedDomains();
                    if (!domains.length) { setStatus('Select at least one domain to fix.', '#c3372b'); return; }
                    if (!confirm('Replace http:// with https:// for ' + domains.length + ' selected domain' + (domains.length !== 1 ? 's' : '') + '.\n\nEnsure you have a recent database backup before proceeding.')) return;
                    fixBtn.disabled = true;
                    scanBtn.disabled = true;
                    setStatus('Fixing — this may take a moment…', '#50575e');
                    resultsEl.innerHTML = '';
                    safeFetch(_ajax, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_https_fix&nonce=' + encodeURIComponent(_nonce) + '&domains=' + encodeURIComponent(domains.join(','))
                    })
                    .then(function(data) {
                        fixBtn.disabled = false;
                        scanBtn.disabled = false;
                        if (!data.success) {
                            setStatus('', '');
                            resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Fix error:\n' + esc(data.data) + '</div>';
                            return;
                        }
                        var d = data.data;
                        fixBtn.style.display = 'none';
                        setStatus('✅ Fixed ' + d.fixed + ' row' + (d.fixed!==1?'s':''), '#1a7a34');

                        if (!d.changes || d.changes.length === 0) {
                            resultsEl.innerHTML = '<p style="color:#50575e;font-size:13px">No changes recorded.</p>';
                            return;
                        }
                        var changeRows = d.changes.map(function(c) {
                            return '<tr>' +
                                '<td style="' + td + '">' + esc(c.table) + '</td>' +
                                '<td style="' + td + '">' + esc(c.column) + '</td>' +
                                '<td style="' + td + '">' + esc(c.id) + '</td>' +
                                '<td style="' + td + ';color:#c3372b">' + esc(c.from) + '</td>' +
                                '<td style="' + td + ';color:#1a7a34">' + esc(c.to) + '</td>' +
                                '</tr>';
                        }).join('');
                        resultsEl.innerHTML =
                            '<p style="font-size:13px;font-weight:600;color:#1a7a34;margin:0 0 8px">✅ ' + d.changes.length + ' URL' + (d.changes.length!==1?'s':'') + ' updated across ' + d.fixed + ' row' + (d.fixed!==1?'s':'') + ':</p>' +
                            '<div style="max-height:320px;overflow-y:auto;border:1px solid #dcdcde;border-radius:4px">' +
                            '<table style="width:100%;border-collapse:collapse">' +
                            '<thead><tr>' +
                            '<th style="' + th + '">Table</th>' +
                            '<th style="' + th + '">Column</th>' +
                            '<th style="' + th + '">ID</th>' +
                            '<th style="' + th + '">From</th>' +
                            '<th style="' + th + '">To</th>' +
                            '</tr></thead><tbody>' + changeRows + '</tbody></table></div>';
                    })
                    .catch(function(e) {
                        fixBtn.disabled = false;
                        scanBtn.disabled = false;
                        setStatus('', '');
                        resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Fix error:\n' + esc(e.message) + '</div>';
                    });
                });
            })();
            </script>

        </div><!-- /ab-pane-sitemap -->

        <div class="ab-pane" id="ab-pane-perf">

            <div class="ab-zone-card ab-card-fonts" style="margin-top:0">
                <div class="ab-zone-header" style="background:#0066cc;justify-content:space-between">
                    <span><span class="ab-zone-icon">🔤</span> Font-Display Optimization</span>
                    <?php $this->explain_btn('perf', '⚡ Performance Tab — What each feature does', [
                        ['rec'=>'✅ Recommended','name'=>'Font-Display: Swap','desc'=>'Adds font-display: swap to your @font-face rules. This tells browsers to show text immediately using a fallback font, then swap in the custom font once loaded. Eliminates the "Flash of Invisible Text" (FOIT) and dramatically improves Largest Contentful Paint (LCP) scores. Typical savings: 500ms–2s.'],
                        ['rec'=>'✅ Recommended','name'=>'Font Metric Overrides','desc'=>'Adds size-adjust, ascent-override, and descent-override properties to match your web font metrics to the fallback font. This prevents layout shift (CLS) when the custom font loads. Without this, text may jump or reflow as fonts swap.'],
                        ['rec'=>'⬜ Optional','name'=>'Defer Font CSS Loading','desc'=>'Changes font stylesheets to load with media="print" and swap to media="all" after page load. This prevents font CSS from blocking initial render. Enable this for maximum LCP improvement, but test thoroughly — some themes may show a brief flash of unstyled text.'],
                        ['rec'=>'⬜ Optional','name'=>'Auto-Download CDN Fonts','desc'=>'Detects Google Fonts loaded from CDN (fonts.googleapis.com) and downloads them to your local server. Local fonts load faster and eliminate third-party requests. Also improves privacy compliance (GDPR) by keeping font requests on your domain.'],
                        ['rec'=>'✅ Recommended','name'=>'Defer Render-Blocking JavaScript','desc'=>'Adds the defer attribute to JavaScript files, allowing them to download in parallel and execute after HTML parsing. This prevents scripts from blocking page rendering. Some scripts (jQuery, payment widgets) should be excluded — use the exclusions field.'],
                        ['rec'=>'⬜ Optional','name'=>'HTML/CSS/JS Minification','desc'=>'Removes whitespace, comments, and unnecessary characters from your HTML output. Reduces page size by 5–15% with zero visual change. Safe and conservative — protects pre-formatted content, JSON-LD, and textareas.'],
                        ['rec'=>'✅ Recommended','name'=>'HTTPS Mixed Content Scanner','desc'=>'Scans your database for http:// references to your own domain that should be https://. Mixed content triggers browser warnings and hurts SEO. One-click fix replaces all instances across posts, pages, meta, options, and comments.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">
                    <p style="padding:0 20px; margin-top:12px; font-size:13px; color:#555; line-height:1.6;">
                        <strong>font-display: swap</strong> ensures text is visible while web fonts load. This eliminates the "Flash of Invisible Text" (FOIT) and improves perceived performance.
                    </p>
                    
                    <div style="padding:0 20px; margin:8px 0 0 0; font-size:13px; color:#666; border-top:1px solid #e5e5e5; padding-top:12px;">
                        <strong>ℹ How font optimization works:</strong><br>
                        1. Click "Scan CSS Files" to analyze your fonts<br>
                        2. Click "Auto-Fix All" to apply optimizations:<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• Adds <code>font-display: swap</code> to missing fonts<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• Adds metric overrides to prevent layout shift<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• If defer is enabled: defers CSS loading (media="print")<br>
                        3. Creates backup (you can undo anytime)<br>
                        <strong>No changes happen until you click "Auto-Fix All"</strong>
                    </div>
                    
                    <div style="padding:0 20px; margin:16px 0; display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="button" id="ab-font-scan-btn" onclick="abFontScan(this)" style="background:#0066cc;border-color:#004d99;color:#fff;font-weight:600">
                            🔍 Scan CSS Files
                        </button>
                        <button type="button" class="button" id="ab-font-download-btn" onclick="abFontDownload(this)" style="background:#1a7a34;border-color:#145a27;color:#fff;font-weight:600">
                            ⬇️ Auto-Download CDN Fonts
                        </button>
                        <button type="button" class="button" id="ab-font-fix-btn" onclick="abFontFix(this)" style="background:#7c3aed;border-color:#5b21b6;color:#fff;font-weight:600">
                            ✨ Auto-Fix All
                        </button>
                        <button type="button" class="button" id="ab-font-clear-btn" onclick="abFontClearConsole()" style="background:#d946a6;border-color:#b5348a;color:#fff;font-weight:600">
                            🧹 Clear Console
                        </button>
                    </div>
                    
                    <div id="ab-font-console" class="ab-log" style="margin:16px 20px; min-height:120px; max-height:250px; overflow-y:auto; background:#1a1a2e; border:1px solid #333; border-radius:4px; padding:12px; font-family:monospace; font-size:13px; line-height:1.6; color:#e0e0e0; display:block;">
                        <div style="text-align:center; color:#888; padding:12px;">Click "Scan CSS Files" to analyze your fonts...</div>
                    </div>
                </div>
            </div>

            <div class="ab-zone-card ab-card-render" style="margin-top:16px">
                <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[defer_js]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[minify_html]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[defer_fonts]" value="0">
                <div class="ab-zone-header" style="background:#7c3aed">
                    <span><span class="ab-zone-icon">🚀</span> Render &amp; Minification</span>
                </div>
                <div class="ab-zone-body" style="padding:16px 20px">

                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Defer Font CSS Loading
                            <span>Loads font stylesheets as print, swaps to all after page load. Prevents render-blocking.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[defer_fonts]" value="1" <?php checked((int)($o['defer_fonts'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>

                    <div style="margin-top:16px;border-top:1px solid #e5e5e5;padding-top:16px">
                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Defer render-blocking JavaScript
                            <span>Downloads JS in parallel, executes after HTML parsing. Fast PageSpeed win.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[defer_js]" value="1" id="ab-defer-toggle" <?php checked((int)($o['defer_js'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>

                    <div id="ab-defer-excludes-wrap" style="margin-top:12px;<?php echo (int)($o['defer_js'] ?? 0) ? '' : 'display:none'; ?>">
                        <label style="font-weight:600;display:block;margin-bottom:4px">Defer exclusions (one handle or URL substring per line):</label>
                        <textarea class="large-text" rows="4"
                            name="<?php echo esc_attr(self::OPT); ?>[defer_js_excludes]"
                            placeholder="jquery&#10;woocommerce&#10;my-critical-script"><?php echo esc_textarea((string)($o['defer_js_excludes'] ?? '')); ?></textarea>
                        <p class="description">Scripts whose handle name or URL contains any of these strings will be excluded from deferring. jQuery and a set of other commonly problematic scripts are excluded automatically — you only need to add scripts that are still breaking your site after enabling defer.</p>
                    </div>
                    <?php /* defer-toggle listener moved to admin_enqueue_assets() */ ?>
                    </div>

                    <div style="margin-top:16px;border-top:1px solid #e5e5e5;padding-top:16px">
                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Minify HTML output
                            <span>Strips whitespace &amp; comments. Minifies inline CSS and JS. 5–15% size reduction.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[minify_html]" value="1" <?php checked((int)($o['minify_html'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>
                    </div>

                    <?php submit_button('Save Performance Settings'); ?>
                </div>
                </form>
            </div>

        </div><!-- /ab-pane-perf -->

        <?php /* ══════════════════ SCHEDULED BATCH PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-batch">
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_ai_group'); ?>

                <div class="ab-zone-card ab-card-schedule">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⏱</span> Scheduled Batch Generation</span>
                    <?php $this->explain_btn('schedule', '⏱ Scheduled Batch — How this works', [
                        ['rec'=>'ℹ️ Info','name'=>'What this does','desc'=>'Automatically runs the AI meta description generator on a schedule — no need to manually click Generate Missing. The batch only processes posts that don\'t yet have a description, so it never overwrites existing ones.'],
                        ['rec'=>'⬜ Optional','name'=>'Enable schedule','desc'=>'Turns the scheduled batch on or off. When enabled, the batch runs automatically at midnight (server time) on the days you select. When disabled, no automatic generation happens — you can still run it manually from the Optimise SEO tab.'],
                        ['rec'=>'⬜ Optional','name'=>'Days of the week','desc'=>'Choose which days the batch runs. For a high-volume blog that publishes daily, tick every day. For a weekly blog, once or twice a week is sufficient. The batch only does work if there are unprocessed posts — if everything is up to date, it completes instantly.'],
                        ['rec'=>'ℹ️ Info','name'=>'Midnight server time','desc'=>'The batch runs at midnight based on your server\'s timezone, not your local time. Check your WordPress timezone setting under Settings → General if the timing seems off.'],
                        ['rec'=>'ℹ️ Info','name'=>'API costs','desc'=>'Each description generated makes one API call to Anthropic Claude. At typical blog post lengths, Claude Haiku costs roughly $0.001–$0.003 per post. A full run across 100 unprocessed posts costs around $0.10–$0.30.'],
                    ]); ?>
                </div>
                <div class="ab-zone-body">
                <p style="padding:12px 20px 0;color:#50575e;margin:0">The batch runs automatically on selected days at midnight (server time). <strong style="color:#6b3fa0">It only processes posts that do not yet have a meta description</strong> — it never overwrites existing ones.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th>Enable schedule:</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    id="cs-sched-enabled"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[schedule_enabled]"
                                    value="1" <?php checked((int)($ai['schedule_enabled'] ?? 0), 1); ?>
                                    onchange="csToggleSchedDays(this.checked)">
                                Enable automatic scheduled batch
                            </label>
                            <p class="description">Requires an Anthropic API key saved in the Optimise SEO tab → AI Meta Writer section.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Run on these days:</th>
                        <td>
                            <div style="display:flex;gap:16px;flex-wrap:wrap" id="cs-sched-days">
                            <?php
                            $day_labels  = ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'];
                            $sched_days  = (array)($ai['schedule_days'] ?? []);
                            $sched_on    = (int)($ai['schedule_enabled'] ?? 0);
                            foreach ($day_labels as $val => $label): ?>
                                <label style="<?php echo $sched_on ? '' : 'opacity:0.4'; ?>">
                                    <input type="checkbox"
                                        class="cs-sched-day"
                                        name="<?php echo esc_attr(self::AI_OPT); ?>[schedule_days][]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked(in_array($val, $sched_days, true), true); ?>
                                        <?php echo $sched_on ? '' : 'disabled'; ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            </div>
                            <?php /* csToggleSchedDays moved to admin_enqueue_assets() */ ?>
                            <p class="description" style="margin-top:10px">
                                <?php
                                $cron_next = wp_next_scheduled('cs_seo_daily_batch');
                                if ($cron_next && !empty($sched_days)) {
                                    $day_map = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>0];
                                    $target_dow = array_map(fn($d) => $day_map[$d] ?? -1, $sched_days);
                                    $found = null;
                                    for ($i = 0; $i <= 7; $i++) {
                                        $ts  = strtotime("midnight +{$i} days");
                                        $dow = (int) gmdate('w', $ts);
                                        if (in_array($dow, $target_dow, true)) {
                                            $found = $ts;
                                            break;
                                        }
                                    }
                                    if ($found) {
                                        echo 'Next scheduled run: <strong>' . esc_html(gmdate('D d M Y H:i:s', $found)) . '</strong> (server time)';
                                    } else {
                                        echo 'No matching days selected.';
                                    }
                                } elseif ($cron_next && (int)($ai['schedule_enabled'] ?? 0)) {
                                    echo '<span style="color:#c3372b">No days selected — tick at least one day above.</span>';
                                } elseif ((int)($ai['schedule_enabled'] ?? 0)) {
                                    echo '<span style="color:#c3372b">No cron event found — try saving settings again.</span>';
                                } else {
                                    echo 'Schedule is disabled.';
                                } ?>
                            </p>
                        </td>
                    </tr>
                </table>
                </div><!-- /ab-zone-body -->
                </div><!-- /ab-card-schedule -->
                <?php submit_button('Save Schedule Settings'); ?>
            </form>

            <hr class="ab-zone-divider">

            <div class="ab-zone-card ab-card-lastrun">
            <div class="ab-zone-header" style="justify-content:space-between">
                <span><span class="ab-zone-icon">📋</span> Batch Run History (28 days)</span>
                <?php $this->explain_btn('lastrun', '📋 Batch Run History — Reading the results', [
                    ['rec'=>'ℹ️ Info','name'=>'Run history','desc'=>'Shows all batch runs from the last 28 days, newest first. Each entry shows when the batch ran, how many posts were processed, and any errors. Entries older than 28 days are automatically pruned.'],
                    ['rec'=>'ℹ️ Info','name'=>'Processed','desc'=>'How many posts the batch attempted to generate descriptions for in each run. Posts that already had descriptions are skipped and not counted here.'],
                    ['rec'=>'ℹ️ Info','name'=>'Succeeded','desc'=>'Posts that were successfully updated with a new AI-generated description. These posts now have meta descriptions and will be skipped in future batch runs.'],
                    ['rec'=>'ℹ️ Info','name'=>'Errors','desc'=>'Posts where generation failed — usually due to an API error, rate limit, or the post having no readable content. The batch will retry these on the next scheduled run. Check your API key if errors are consistently high.'],
                    ['rec'=>'ℹ️ Info','name'=>'Next scheduled run','desc'=>'When the batch will next execute automatically. If this shows "Not scheduled" but the schedule is enabled, try saving your schedule settings again — this re-registers the WordPress cron event.'],
                ]); ?>
            </div>
            <div class="ab-zone-body">
            <?php
                $history = get_option('cs_seo_batch_history', []);
                // Migrate legacy single-run option if present.
                if (empty($history)) {
                    $legacy = get_option('cs_seo_last_batch', null);
                    if ($legacy) {
                        $history = [$legacy];
                        update_option('cs_seo_batch_history', $history, false);
                        delete_option('cs_seo_last_batch');
                    }
                }
                if (!empty($history) && is_array($history)):
                    // Show newest first.
                    $history = array_reverse($history);
            ?>
                <div style="padding:16px 20px;max-height:500px;overflow-y:auto">
                <?php foreach ($history as $idx => $batch): ?>
                    <div style="<?php echo $idx > 0 ? 'margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5;' : ''; ?>">
                        <p style="margin:0 0 4px">
                            <strong><?php echo esc_html($batch['day'] ?? ''); ?> <?php echo esc_html($batch['date'] ?? ''); ?></strong> —
                            <span style="color:#1a7a34"><?php echo (int)($batch['done'] ?? 0); ?> generated</span>,
                            <?php echo (int)($batch['skipped'] ?? 0); ?> skipped<?php if (($batch['errors'] ?? 0) > 0): ?>,
                                <span style="color:#c3372b"><?php echo (int)$batch['errors']; ?> errors</span><?php endif; ?>,
                            <?php echo esc_html($batch['elapsed'] ?? '0'); ?> minutes total
                        </p>
                        <?php if (!empty($batch['log'])): ?>
                        <details style="margin-top:4px">
                            <summary style="cursor:pointer;font-size:12px;color:#50575e">Show post log (<?php echo count($batch['log']); ?> entries)</summary>
                            <div style="background:#1a1a2e;color:#e0e0f0;font-family:'Courier New',monospace;font-size:11px;padding:10px;border-radius:4px;margin-top:8px;max-height:200px;overflow-y:auto">
                            <?php foreach ($batch['log'] as $entry): ?>
                                <?php if ($entry['status'] === 'ok'): ?>
                                    <div style="color:#00d084">✓ <?php echo esc_html($entry['title']); ?> → <?php echo (int)$entry['chars']; ?> chars</div>
                                <?php else: ?>
                                    <div style="color:#ff6b6b">✗ <?php echo esc_html($entry['title']); ?>: <?php echo esc_html($entry['message']); ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="padding:16px 20px;margin:0;color:#50575e">No batch has run yet.</p>
            <?php endif; ?>
            </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-lastrun -->

        </div><!-- /ab-pane-batch -->

        <script>
        function abFontLog(type, text) {
            const consoleEl = document.getElementById('ab-font-console');
            if (!consoleEl) return;
            
            // Clear placeholder text on first log
            if (consoleEl.innerHTML.includes('Click "Scan')) {
                consoleEl.innerHTML = '';
            }
            
            const line = document.createElement('div');
            line.className = 'ab-log-line ab-log-' + type;
            line.textContent = text;
            line.style.marginBottom = '4px';
            
            // Color coding
            if (type === 'err') line.style.color = '#d32f2f';
            if (type === 'ok') line.style.color = '#388e3c';
            if (type === 'warn') line.style.color = '#f57c00';
            if (type === 'info') line.style.color = '#1976d2';
            
            consoleEl.appendChild(line);
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        function abFontClearConsole() {
            const consoleEl = document.getElementById('ab-font-console');
            if (!consoleEl) return;
            consoleEl.innerHTML = '<div style="text-align:center; color:#999; padding:20px;">Click "Scan CSS Files" to analyze your fonts...</div>';
        }

        async function abFontDownload(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '⏳ Downloading...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Detecting Google Fonts CDN URLs...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Connecting to server...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_download_fonts',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (data.messages && Array.isArray(data.messages)) {
                    data.messages.forEach(msg => {
                        if (msg.includes('✓')) {
                            abFontLog('ok', msg);
                        } else if (msg.includes('✗')) {
                            abFontLog('err', msg);
                        } else if (msg.includes('ℹ')) {
                            abFontLog('warn', msg);
                        } else if (msg === '') {
                            abFontLog('info', '');
                        } else {
                            abFontLog('info', msg);
                        }
                    });
                }
                
                if (data.success && data.downloaded > 0) {
                    abFontLog('ok', '✓ Fonts downloaded! Run "Scan CSS Files" to verify.');
                }
                
            } catch (e) {
                abFontLog('err', 'Download failed: ' + e.message);
                console.error('[CloudScale Font Download]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '⬇️ Auto-Download CDN Fonts';
            }
        }

        async function abFontScan(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '🔄 Scanning...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Initializing font scanner...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Connecting to server...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_font_scan',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                abFontLog('info', 'Processing response...');
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (!data || !data.console || !Array.isArray(data.console)) {
                    abFontLog('err', 'ERROR: Invalid response structure');
                    throw new Error('Invalid response');
                }
                
                abFontLog('info', 'Displaying results...');
                
                data.console.forEach(line => {
                    if (line && line.type && line.text) {
                        abFontLog(line.type, line.text);
                    }
                });
                
                    if (data.findings && data.findings.missing_fonts > 0) {
                        const fixBtn = document.getElementById('ab-font-fix-btn');
                        if (fixBtn) {
                            fixBtn.style.display = 'inline-block';
                            fixBtn.textContent = '✨ Auto-Fix All (' + data.findings.missing_fonts + ' fonts)';
                        }
                    }
                
            } catch (e) {
                abFontLog('err', 'Scan failed: ' + e.message);
                console.error('[CloudScale Font Scan]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '🔍 Scan CSS Files';
            }
        }

        async function abFontFix(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '⏳ Checking fonts...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Checking for unoptimized fonts...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Scanning fonts...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_font_fix',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                abFontLog('info', 'Processing results...');
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (!data || !data.console || !Array.isArray(data.console)) {
                    abFontLog('err', 'ERROR: Invalid response structure');
                    throw new Error('Invalid response');
                }
                
                // Check if there's actually anything to fix
                const hasUnoptimized = data.console && data.console.some(line => 
                    line.text && line.text.includes('MISSING') || line.text.includes('unoptimized')
                );
                
                if (!hasUnoptimized && data.console.length > 0) {
                    abFontLog('info', '');
                    abFontLog('ok', '✓ All fonts are already optimized!');
                    abFontLog('info', 'No changes needed.');
                    abFontLog('info', '');
                    abFontLog('skip', 'Your fonts already have font-display and are properly deferred.');
                } else {
                    abFontLog('info', 'Applying optimizations...');
                }
                
                data.console.forEach(line => {
                    if (line && line.type && line.text) {
                        abFontLog(line.type, line.text);
                    }
                });
                
            } catch (e) {
                abFontLog('err', 'Fix failed: ' + e.message);
                console.error('[CloudScale Font Fix]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '✨ Auto-Fix All';
            }
        }
        </script>

        <script>
        // ── Tab switching ────────────────────────────────────────────────────
        function abTab(id, btn) {
            document.querySelectorAll('.ab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.ab-tab').forEach(b  => b.classList.remove('active'));
            document.getElementById('ab-pane-' + id).classList.add('active');
            btn.classList.add('active');
            if (id === 'sitemap') abRefreshRobotsPreview();
        }

        // ── State ────────────────────────────────────────────────────────────
        const abState = {
            posts:         [],
            page:          1,
            totalPages:    1,
            total:         0,
            totalWithDesc: 0,
            generated:     0,
            stopped:       false,
            running:       false,
        };

        const abNonce   = <?php echo wp_json_encode($nonce); ?>;
        const abAjax    = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
        const abMinChar = <?php echo (int) $this->ai_opts['min_chars']; ?>;
        const abMaxChar = <?php echo (int) $this->ai_opts['max_chars']; ?>;
        const abHasApiKey = <?php echo wp_json_encode(!empty(trim((string)($this->ai_opts['anthropic_key'] ?? '')))); ?>;

        // ── Live robots.txt preview ──────────────────────────────────────────
        function abRefreshRobotsPreview() {
            try {
            const pre = document.getElementById('ab-robots-live-preview');
            if (!pre) return;
            pre.textContent = 'Loading…';
            const params = new URLSearchParams({action: 'cs_seo_fetch_robots', nonce: abNonce});
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        pre.textContent = data.data.content;
                    } else {
                        pre.textContent = '(error: ' + data.data + ')';
                    }
                })
                .catch(e => { pre.textContent = '(fetch error: ' + e.message + ')'; });
            } catch(e) { console.warn('abRefreshRobotsPreview error:', e); }
        }

        function abCopyRobots() {
            const btn  = document.getElementById('ab-robots-copy');
            const ta   = document.getElementById('cs-robots-txt');
            if (!btn || !ta) return;
            const text = ta.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }

        function abCopyRobotsLive() {
            const btn = document.getElementById('ab-robots-live-copy');
            const pre = document.getElementById('ab-robots-live-preview');
            if (!btn || !pre) return;
            const text = pre.textContent;
            if (!text || text === 'Loading…') {
                btn.textContent = '⚠ Load first';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
                return;
            }
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }
        function abCopySitemap() {
            const btn  = document.getElementById('ab-sitemap-copy');
            const urls = window._abSitemapUrls || [];
            if (!btn) return;
            if (!urls.length) { btn.textContent = '⚠ Load first'; setTimeout(function(){ btn.textContent = '⎘ Copy URLs'; }, 2000); return; }
            const text = urls.join('\n');
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy URLs'; }, 2000);
            });
        }
        function abCopyPrompt() {
            const btn = document.getElementById('ab-copy-prompt');
            const ta  = document.getElementById('ab-prompt-field');
            if (!btn || !ta) return;
            const text = ta.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta2 = document.createElement('textarea');
                ta2.value = text;
                ta2.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta2);
                ta2.select();
                document.execCommand('copy');
                document.body.removeChild(ta2);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }

        function abCopyLlms() {
            const btn  = document.getElementById('ab-llms-copy');
            const wrap = document.getElementById('ab-llms-preview-wrap');
            if (!btn || !wrap) return;
            const text = wrap.dataset.raw || '';
            if (!text) { btn.textContent = '⚠ Load first'; setTimeout(function(){ btn.textContent = '⎘ Copy'; }, 2000); return; }
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }
        // Auto-load robots preview on page load if sitemap tab is active
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('ab-pane-sitemap')?.classList.contains('active')) {
                abRefreshRobotsPreview();
            }

            // Delegated click handler for title badges — works even after table is re-rendered
            document.addEventListener('click', function(e) {
                const badge = e.target.closest('[data-titleid]');
                if (badge) {
                    e.stopPropagation();
                    abShowTitlePopup(parseInt(badge.getAttribute('data-titleid'), 10), badge);
                } else {
                    // Click outside any badge — dismiss popup if open
                    const popup = document.getElementById('ab-title-popup');
                    if (popup) popup.remove();
                }
            });
        });

        // ── Rename physical robots.txt ───────────────────────────────────────
        function abRenameRobots() {
            const btn    = document.getElementById('ab-rename-robots-btn');
            const status = document.getElementById('ab-rename-robots-status');
            btn.disabled = true;
            btn.textContent = '⟳ Renaming...';
            status.style.color = '#50575e';
            status.textContent = 'Working...';

            const params = new URLSearchParams({action: 'cs_seo_rename_robots', nonce: abNonce});
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const warn = document.getElementById('ab-physical-robots-warn');
                        if (warn) {
                            warn.style.background = '#edfaef';
                            warn.style.borderColor = '#1a7a34';
                            warn.innerHTML = '<div style="font-size:22px">✅</div>' +
                                '<div><strong>Done!</strong> robots.txt has been renamed to robots.txt.bak. ' +
                                'The plugin is now managing your robots.txt. ' +
                                'Purge your Cloudflare cache, then <a href="' + window.location.href + '">reload this page</a> to confirm.</div>';
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Rename robots.txt → robots.txt.bak';
                        status.style.color = '#c3372b';
                        status.textContent = '✗ ' + data.data;
                    }
                })
                .catch(e => {
                    btn.disabled = false;
                    btn.textContent = 'Rename robots.txt → robots.txt.bak';
                    status.style.color = '#c3372b';
                    status.textContent = '✗ Network error: ' + e.message;
                });
        }
        let abSitemapPage = 1;

        function abLoadSitemap(pg) {
            abSitemapPage = pg || 1;
            const wrap = document.getElementById('ab-sitemap-preview-wrap');
            const btn  = document.getElementById('ab-sitemap-load');
            if (!wrap || !btn) {
                console.error('CloudScale SEO: sitemap preview elements not found');
                return;
            }
            console.log('CloudScale SEO: loading sitemap preview page', abSitemapPage);
            btn.disabled = true;
            btn.textContent = '⟳ Loading...';
            if (abSitemapPage === 1) {
                wrap.innerHTML = '<p style="color:#50575e;font-size:13px">Fetching sitemap entries...</p>';
            }

            const params = new URLSearchParams({
                action: 'cs_seo_sitemap_preview',
                nonce: abNonce,
                sitemap_pg: abSitemapPage,
            });
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = '↻ Reload';
                    if (!data.success) {
                        wrap.innerHTML = '<div style="background:#fef0f0;border:1px solid #f5bcbb;border-radius:4px;padding:12px;color:#c3372b"><strong>Preview failed:</strong> ' + (data.data || 'Unknown error') + '<br><small>Check that your API key is set and the plugin settings have been saved.</small></div>';
                        return;
                    }
                    const d        = data.data;
                    const entries  = d.entries;
                    const total    = d.total;
                    const page     = d.page;
                    const pages    = d.pages;
                    const per_page = d.per_page;
                    const start    = (page - 1) * per_page + 1;
                    const end      = Math.min(page * per_page, total);

                    const typeLabels = {home:'Home', post:'Post', page:'Page', tax:'Taxonomy', cpt:'CPT'};
                    const typeClass  = t => 'ab-sitemap-type ab-sitemap-type-' + (t || 'post');

                    let rows = entries.map(e =>
                        '<tr>' +
                        '<td><a class="ab-sitemap-url" href="' + e.loc + '" target="_blank">' + e.loc + '</a>' +
                        (e.title ? '<br><small style="color:#50575e;font-size:11px">' + e.title + '</small>' : '') + '</td>' +
                        '<td><span class="' + typeClass(e.type) + '">' + (typeLabels[e.type] || e.type) + '</span></td>' +
                        '<td style="color:#50575e;font-size:12px;white-space:nowrap">' + (e.lastmod || '—') + '</td>' +
                        '</tr>'
                    ).join('');

                    // Pager
                    let pager = '';
                    if (pages > 1) {
                        const sitemapLinks = Array.from({length: pages}, (_, i) => {
                            const n = i + 1;
                            const active = n === page ? 'font-weight:700;color:#1d2327' : 'color:#2271b1;cursor:pointer';
                            return '<span style="' + active + ';padding:0 4px" ' +
                                (n !== page ? 'onclick="abLoadSitemap(' + n + ')"' : '') + '>' + n + '</span>';
                        }).join(' ');
                        pager = '<div style="display:flex;align-items:center;gap:10px;margin-top:12px;flex-wrap:wrap">' +
                            '<button class="button" ' + (page <= 1 ? 'disabled' : '') + ' onclick="abLoadSitemap(' + (page-1) + ')">← Prev</button>' +
                            '<span style="font-size:12px;color:#50575e">Page ' + page + ' of ' + pages + '</span>' +
                            '<button class="button" ' + (page >= pages ? 'disabled' : '') + ' onclick="abLoadSitemap(' + (page+1) + ')">Next →</button>' +
                            '<span style="font-size:12px;color:#888;margin-left:auto">Showing ' + start + '–' + end + ' of ' + total + ' URLs</span>' +
                            '</div>';
                    }

                    wrap.innerHTML =
                        '<p class="ab-sitemap-count"><strong>' + total + '</strong> total URLs across <strong>' + pages + '</strong> sitemap file' + (pages > 1 ? 's' : '') +
                        ' &nbsp;·&nbsp; <a href="' + <?php echo wp_json_encode(home_url('/sitemap.xml')); ?> + '" target="_blank">View sitemap index ↗</a></p>' +
                        '<table class="ab-sitemap-tbl">' +
                        '<thead><tr><th>URL</th><th>Type</th><th>Last Modified</th></tr></thead>' +
                        '<tbody>' + rows + '</tbody></table>' +
                        pager;
                })
                .catch(e => {
                    btn.disabled = false;
                    btn.textContent = '⬇ Load Preview';
                    wrap.innerHTML = '<div style="background:#fef0f0;border:1px solid #f5bcbb;border-radius:4px;padding:12px;color:#c3372b"><strong>Network error:</strong> ' + e.message + '</div>';
                    wrap.innerHTML = '<p style="color:#c3372b">Error: ' + e.message + '</p>';
                });
        }

        // ── API key guard ─────────────────────────────────────────────────────
        function abCheckApiKey() {
            if (abHasApiKey) return true;
            document.getElementById('ab-api-warn').classList.add('visible');
            abLog('⚠ No API key saved. Scroll up to the ✦ AI Meta Writer section, enter your Anthropic API key and click Save AI Settings, then reload the page.', 'err');
            return false;
        }

        // Show warning banner on page load if no key saved
        if (!abHasApiKey) {
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('ab-api-warn').classList.add('visible');
            });
        }

        // ── Utilities ────────────────────────────────────────────────────────
        function abLog(msg, type) {
            const wrap = document.getElementById('ab-log-wrap');
            const el   = document.getElementById('ab-log');
            if (wrap) wrap.style.display = '';
            el.classList.add('visible');
            const ts  = new Date().toLocaleTimeString('en-GB');
            const cls = type ? 'ab-log-' + type : 'ab-log-line';
            el.innerHTML += '<div class="' + cls + '">[' + ts + '] ' + abEsc(msg) + '</div>';
            el.scrollTop = el.scrollHeight;
        }

        function abEsc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        }

        // Title popup — store titles in a plain object to avoid HTML attribute escaping issues
        const abTitleMap = {};

        function abShowTitlePopup(postId, el) {
            // Remove any existing popup
            const existing = document.getElementById('ab-title-popup');
            if (existing) {
                existing.remove();
                if (existing.dataset.postId === String(postId)) return;
            }
            const raw   = abTitleMap[postId] || el.getAttribute('data-title') || '';
            if (!raw) return;
            // Decode HTML entities (WordPress stores titles with e.g. &#8230; &#8217;)
            const txt   = document.createElement('textarea');
            txt.innerHTML = raw;
            const title = txt.value;
            const chars = title.length;
            const isAi  = abTitleMap['_ai_' + postId] === true;

            const rect   = el.getBoundingClientRect();
            const popup  = document.createElement('div');
            popup.id = 'ab-title-popup';
            popup.dataset.postId = String(postId);
            popup.style.cssText = [
                'position:fixed',
                'z-index:99999',
                'background:#1a1a2e',
                'color:#fff',
                'border:1px solid #4f46e5',
                'border-radius:8px',
                'padding:12px 16px',
                'max-width:420px',
                'min-width:240px',
                'box-shadow:0 8px 24px rgba(0,0,0,0.4)',
                'font-size:13px',
                'line-height:1.5',
            ].join(';');
            popup.innerHTML =
                '<div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#8080b0;margin-bottom:6px">' +
                    (isAi ? '✦ AI Rewritten Title' : 'SEO Title') + ' · ' + chars + ' chars' +
                '</div>' +
                '<div style="color:#fff;font-weight:600">' + abEsc(title) + '</div>' +
                '<div style="margin-top:8px;font-size:11px;color:#6060a0">Click anywhere to dismiss</div>';
            document.body.appendChild(popup);
            const top  = rect.bottom + 6;
            const left = Math.min(rect.left, window.innerWidth - 440);
            popup.style.top  = top + 'px';
            popup.style.left = Math.max(8, left) + 'px';
        }

        // Decode HTML entities WordPress puts in titles (e.g. &#8211; → –)
        function abDecodeTitle(s) {
            const txt = document.createElement('textarea');
            txt.innerHTML = String(s);
            return txt.value;
        }

        function abSetStatus(msg) {
            document.getElementById('ab-toolbar-status').textContent = msg;
        }

        function abSetProgress(done, total) {
            const pct = total > 0 ? Math.round(done/total*100) : 0;
            document.getElementById('ab-progress').classList.add('visible');
            document.getElementById('ab-progress-fill').style.width = pct + '%';
            document.getElementById('ab-prog-label').textContent =
                done + ' / ' + total + ' processed (' + pct + '%)';
        }

        function abUpdateSummary() {
            const total   = abState.total;
            const hasDesc = abState.totalWithDesc + abState.generated;
            const missing = Math.max(0, total - hasDesc);
            document.getElementById('sum-total').textContent     = total;
            document.getElementById('sum-has').textContent       = hasDesc;
            document.getElementById('sum-missing').textContent   = missing;
            document.getElementById('sum-generated').textContent = abState.generated;
            document.getElementById('ab-summary').style.display  = 'grid';
        }

        function abPost(action, extra) {
            const params = new URLSearchParams({action, nonce: abNonce, ...extra});
            return fetch(abAjax, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            }).then(r => r.json());
        }

        // ── Test API key ─────────────────────────────────────────────────────
        function abProviderChanged() {
            const provider = document.getElementById('ab-ai-provider').value;
            const isGemini = provider === 'gemini';
            document.getElementById('ab-anthropic-key-field').style.display = isGemini ? 'none' : '';
            document.getElementById('ab-gemini-key-field').style.display    = isGemini ? '' : 'none';
            document.getElementById('ab-key-hint-anthropic').style.display  = isGemini ? 'none' : '';
            document.getElementById('ab-key-hint-gemini').style.display     = isGemini ? '' : 'none';
            // Show/hide model options for the active provider
            document.querySelectorAll('#ab-model-select option').forEach(opt => {
                opt.style.display = opt.dataset.provider === provider ? '' : 'none';
            });
            // Select first visible model if current is wrong provider
            const sel = document.getElementById('ab-model-select');
            const cur = sel.options[sel.selectedIndex];
            if (cur && cur.dataset.provider !== provider) {
                const first = sel.querySelector('option[data-provider="' + provider + '"]');
                if (first) sel.value = first.value;
            }
            document.getElementById('ab-key-status').textContent = '';
        }

        function abTestKey() {
            const status   = document.getElementById('ab-key-status');
            const provider = document.getElementById('ab-ai-provider').value;
            const keyField = provider === 'gemini'
                ? document.getElementById('ab-gemini-key-field')
                : document.getElementById('ab-anthropic-key-field');
            const key      = keyField.value.trim();
            if (!key) {
                status.textContent = '✗ Enter a key first';
                status.className   = 'ab-key-status ab-key-err';
                return;
            }
            status.textContent = '⟳ Testing...';
            status.className   = 'ab-key-status';

            fetch(abAjax, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action:   'cs_seo_ai_test_key',
                    nonce:    abNonce,
                    live_key: key,
                    provider: provider,
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    status.textContent = '✓ ' + data.data;
                    status.className   = 'ab-key-status ab-key-ok';
                } else {
                    status.textContent = '✗ ' + data.data;
                    status.className   = 'ab-key-status ab-key-err';
                }
            })
            .catch(e => {
                status.textContent = '✗ Network error: ' + e.message;
                status.className   = 'ab-key-status ab-key-err';
            });
        }

        // ── Load posts ───────────────────────────────────────────────────────
        function abLoadPosts(page) {
            page = page || 1;
            abState.page = page;
            document.getElementById('ab-load-posts').disabled = true;
            abSetStatus('Loading posts...');
            abPost('cs_seo_ai_get_posts', {page}).then(data => {
                document.getElementById('ab-load-posts').disabled = false;
                if (!data.success) { abLog('Failed to load posts: ' + data.data, 'err'); return; }
                abState.posts      = data.data.posts;
                abState.total          = data.data.total;
                abState.totalWithDesc  = data.data.total_with_desc;
                abState.totalPages     = data.data.total_pages;
                abState.page       = data.data.page;
                abUpdateSummary();
                abRenderTable();
                abSetStatus(data.data.total + ' posts loaded');
                // Hide the load CTA, show the action toolbar
                document.getElementById('ab-load-cta').style.display = 'none';
                document.getElementById('ab-ai-toolbar').style.display = 'flex';
                document.getElementById('ab-ai-gen-missing').disabled = false;
                document.getElementById('ab-ai-gen-all').disabled = false;
                document.getElementById('ab-ai-fix').disabled = false;
            document.getElementById('ab-ai-fix-titles').disabled = false;
                document.getElementById('ab-ai-static').disabled = false;
                // Pager
                const pager = document.getElementById('ab-pager');
                pager.style.display = abState.totalPages > 1 ? 'flex' : 'none';
                document.getElementById('ab-page-info').textContent =
                    'Page ' + abState.page + ' of ' + abState.totalPages;
                document.getElementById('ab-prev').disabled = abState.page <= 1;
                document.getElementById('ab-next').disabled = abState.page >= abState.totalPages;
            }).catch(e => {
                document.getElementById('ab-load-posts').disabled = false;
                abLog('Error: ' + e.message, 'err');
            });
        }

        function abPage(dir) {
            abLoadPosts(abState.page + dir);
        }

        // ── Render table ─────────────────────────────────────────────────────
        function abBadge(post) {
            if (!post.has_desc && !post._gen) return '<span class="ab-badge ab-badge-none">No AI description</span>';
            const desc  = post._gen || post.desc;
            const chars = desc ? desc.length : 0;
            if (post._gen) {
                if (chars > 0 && chars < abMinChar) return '<span class="ab-badge ab-badge-gen-short">✦ Generated · ' + chars + 'c</span>';
                if (chars > abMaxChar)              return '<span class="ab-badge ab-badge-gen-long">✦ Generated · ' + chars + 'c</span>';
                return '<span class="ab-badge ab-badge-gen">✦ Generated · ' + chars + 'c</span>';
            }
            if (chars >= abMinChar && chars <= abMaxChar) return '<span class="ab-badge ab-badge-ok">✓ ' + chars + 'c</span>';
            if (chars > 0 && chars < abMinChar)           return '<span class="ab-badge ab-badge-short">Short · ' + chars + 'c</span>';
            if (chars > abMaxChar)                         return '<span class="ab-badge ab-badge-long">Long · ' + chars + 'c</span>';
            return '<span class="ab-badge ab-badge-none">No AI description</span>';
        }

        function abRenderTable() {
            const wrap = document.getElementById('ab-posts-wrap');
            if (!abState.posts.length) {
                wrap.innerHTML = '<p style="color:#50575e">No posts found.</p>';
                return;
            }
            let rows = abState.posts.map(p => {
                const existDesc = p.desc
                    ? '<div class="ab-desc-text">' + abEsc(p.desc) + '</div>'
                    : '';
                const genDesc = p._gen
                    ? '<div class="ab-desc-gen">✦ ' + abEsc(p._gen) + '</div>'
                    : '';
                const canGen = !p._processing && !p.no_post;
                // ALT badge — update after generation using alts_saved
                const missingAlt = (p.missing_alt || 0) - (p._alts_saved || 0);
                const altCell = missingAlt > 0
                    ? '<span style="display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;white-space:nowrap">⚠ ' + missingAlt + ' missing</span>'
                    : (p.missing_alt !== undefined
                        ? '<span style="display:inline-block;background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;white-space:nowrap">✓ OK</span>'
                        : '');

                // Title length badge
                const tChars = p._new_title_chars !== undefined ? p._new_title_chars : (p.title_chars || 0);
                const tTitle = p._new_title || p.effective_title || p.title || '';
                const isAiTitle = p._new_title !== undefined;
                // Store title in map for popup — avoids all HTML attribute escaping issues
                if (tTitle) {
                    abTitleMap[p.id] = tTitle;
                    abTitleMap['_ai_' + p.id] = isAiTitle;
                }
                const titleCursor = tTitle ? 'cursor:pointer;' : '';
                const titleDataId = tTitle ? 'data-titleid="' + p.id + '" ' : '';
                let titleBadge;
                if (tChars === 0) {
                    titleBadge = '<span style="color:#aaa;font-size:11px">—</span>';
                } else if (tChars >= 50 && tChars <= 60) {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">' + tChars + 'c ✓' + (isAiTitle ? ' ✦' : '') + '</span>';
                } else if (tChars >= 40 && tChars <= 69) {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">⚠ ' + tChars + 'c' + (isAiTitle ? ' ✦' : '') + '</span>';
                } else {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#f8d7da;color:#842029;border:1px solid #f5c2c7;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">✗ ' + tChars + 'c' + (isAiTitle ? ' ✦' : '') + '</span>';
                }

                // Homepage row gets a pinned style and special label
                const isHome   = p.is_homepage;
                const rowStyle = isHome
                    ? 'background:linear-gradient(90deg,#f0f7ff 0%,#fff 100%);border-left:3px solid #2271b1'
                    : '';
                const typeLabel = isHome
                    ? '<span style="background:#2271b1;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700;margin-right:4px">🏠 Homepage</span>'
                    : '';
                const noPostNote = p.no_post
                    ? '<span style="color:#888;font-size:12px">Blog posts index — no post object. Set a static front page to enable AI generation.</span>'
                    : '';
                const actionCell = p.no_post
                    ? '<span style="color:#aaa;font-size:12px">N/A</span>'
                    : '<button class="button ab-row-btn" onclick="abGenOne(' + p.id + ')" ' + (canGen?'':'disabled') + ' id="ab-btn-' + p.id + '">' +
                      (p._processing ? '<span class="ab-spinner">⟳</span>' : '✦') + ' Generate</button>';

                return '<tr id="ab-row-' + p.id + '" style="' + rowStyle + '">' +
                    '<td><strong>' + typeLabel + abEsc(abDecodeTitle(p.title)) + '</strong>' +
                    (p.date ? '<br><small style="color:#888">' + p.type + ' · ' + p.date + '</small>' : '') +
                    noPostNote + '</td>' +
                    '<td>' + abBadge(p) + existDesc + genDesc + '</td>' +
                    '<td style="text-align:center">' + titleBadge + '</td>' +
                    '<td style="text-align:center">' + altCell + '</td>' +
                    '<td>' + actionCell + '</td>' +
                '</tr>';
            }).join('');

            wrap.innerHTML = '<table class="ab-posts">' +
                '<thead><tr>' +
                '<th style="width:32%">Post</th>' +
                '<th style="width:38%">Description</th>' +
                '<th style="width:9%;text-align:center">Title</th>' +
                '<th style="width:9%;text-align:center">ALT Images</th>' +
                '<th style="width:12%">Action</th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody></table>';
        }

        // ── Generate one post ─────────────────────────────────────────────────
        function abGenOne(postId) {
            if (!abCheckApiKey()) return;
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            post._processing = true;
            abRenderTable();

            abPost('cs_seo_ai_generate_one', {post_id: postId}).then(data => {
                post._processing = false;
                if (data.success) {
                    const d = data.data;
                    // ── Description ──────────────────────────────────────────
                    post._gen     = d.description;
                    post.has_desc = true;
                    post.desc     = d.description;
                    if (d.alts_saved > 0) {
                        post._alts_saved = (post._alts_saved || 0) + d.alts_saved;
                    }
                    const altNote = d.alts_saved > 0 ? ' + ' + d.alts_saved + ' ALT(s)' : '';
                    abLog('✓ Description → ' + d.chars + 'c' + altNote + ': ' + d.description, 'ok');

                    // ── Title ─────────────────────────────────────────────────
                    if (d.title_status === 'fixed' || d.title_status === 'fixed_imperfect') {
                        post._new_title       = d.title;
                        post._new_title_chars = d.title_chars;
                        const titleQuality = d.title_status === 'fixed' ? '✓' : '⚠';
                        abLog(titleQuality + ' Title fixed ' + d.title_chars + 'c: ' + d.title, d.title_status === 'fixed' ? 'ok' : 'warn');
                        if (d.title_was) {
                            abLog('  was: ' + d.title_was, 'info');
                        }
                    } else {
                        abLog('  Title already in range (' + d.title_chars + 'c): ' + d.title, 'info');
                    }

                    abState.generated++;
                    abUpdateSummary();
                } else {
                    abLog('✗ "' + post.title.slice(0,45) + '": ' + (data.data || 'Unknown error'), 'err');
                }
                abRenderTable();
            }).catch(e => {
                post._processing = false;
                abLog('✗ Network error: ' + e.message, 'err');
                abRenderTable();
            });
        }

        // ── Generate all ──────────────────────────────────────────────────────
        async function abGenAll(overwrite) {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled = true;
            document.getElementById('ab-ai-fix').disabled = true;
            document.getElementById('ab-ai-fix-titles').disabled = true;
            document.getElementById('ab-ai-static').disabled = true;
            document.getElementById('ab-ai-stop').style.display = 'inline-block';

            abLog(overwrite ? 'Starting full regeneration run...' : 'Starting generation run (missing only)...', 'info');

            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) {}
            }

            const targets = allPosts.filter(p => !p.is_homepage && !p.no_post && (!p.has_desc || overwrite));
            abLog('Found ' + targets.length + ' posts to process', 'info');

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                abSetStatus('Processing: "' + post.title.slice(0,50) + '"...');
                abSetProgress(done, targets.length);

                try {
                    const data = await abPost('cs_seo_ai_generate_all', {
                        post_id:   post.id,
                        overwrite: overwrite ? 1 : 0,
                    });

                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — skipped (has desc)', 'skip');
                        } else {
                            done++;
                            abState.generated++;
                            const bulkAltNote = r.alts_saved > 0 ? ' + ' + r.alts_saved + ' ALT(s)' : '';
                            abLog('✓ Description → ' + r.chars + 'c' + bulkAltNote + ': ' + r.description, 'ok');
                            if (r.title_status === 'fixed' || r.title_status === 'fixed_imperfect') {
                                const tq = r.title_status === 'fixed' ? '✓' : '⚠';
                                abLog(tq + ' Title fixed ' + r.title_chars + 'c: ' + r.title, r.title_status === 'fixed' ? 'ok' : 'warn');
                                if (r.title_was) abLog('  was: ' + r.title_was, 'info');
                            }
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) {
                                local._gen = r.description; local.has_desc = true; local.desc = r.description;
                                if (r.alts_saved > 0) local._alts_saved = (local._alts_saved || 0) + r.alts_saved;
                                if (r.title_status === 'fixed' || r.title_status === 'fixed_imperfect') {
                                    local._new_title       = r.title;
                                    local._new_title_chars = r.title_chars;
                                }
                            }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000); // longer pause on error — likely a rate limit
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abUpdateSummary();
                abRenderTable();
                await abSleep(2500); // ~24 posts/min — stays under Anthropic's 30k token/min limit
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Done — ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Run complete: ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            document.getElementById('ab-ai-gen-missing').disabled = false;
            document.getElementById('ab-ai-gen-all').disabled      = false;
            document.getElementById('ab-ai-fix').disabled          = false;
            document.getElementById('ab-ai-static').disabled       = false;
            document.getElementById('ab-ai-stop').style.display    = 'none';
            abState.running = false;
        }

        // ── Fix out-of-range descriptions ──────────────────────────────────────
        async function abFixAll() {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled     = true;
            document.getElementById('ab-ai-fix').disabled         = true;
            document.getElementById('ab-ai-static').disabled      = true;
            document.getElementById('ab-ai-stop').style.display   = 'inline-block';

            abLog('Starting fix run — scanning for short and long descriptions...', 'info');

            // Fetch all posts across all pages.
            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) {}
            }

            // Target only posts that have a description but it's outside the configured range.
            const targets = allPosts.filter(p => {
                if (!p.has_desc || !p.desc) return false;
                const len = p.desc.length;
                return len < abMinChar || len > abMaxChar;
            });

            if (targets.length === 0) {
                abLog('No out-of-range descriptions found — nothing to fix.', 'info');
                abSetStatus('Nothing to fix.');
                document.getElementById('ab-ai-gen-missing').disabled = false;
                document.getElementById('ab-ai-gen-all').disabled     = false;
                document.getElementById('ab-ai-fix').disabled         = false;
                document.getElementById('ab-ai-stop').style.display   = 'none';
                abState.running = false;
                return;
            }

            abLog('Found ' + targets.length + ' descriptions to fix (' + abMinChar + '–' + abMaxChar + ' char target)', 'info');

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                const len = post.desc ? post.desc.length : 0;
                const issue = len < abMinChar ? 'too short (' + len + 'c)' : 'too long (' + len + 'c)';
                abSetStatus('Fixing: "' + post.title.slice(0,50) + '" — ' + issue);
                abSetProgress(done, targets.length);

                try {
                    const data = await abPost('cs_seo_ai_fix_desc', {post_id: post.id});

                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — ' + r.message, 'skip');
                        } else {
                            done++;
                            abState.generated++;
                            if (r.in_range) {
                                abLog('✓ "' + post.title.slice(0,55) + '" fixed: ' + r.was_chars + 'c → ' + r.chars + 'c', 'ok');
                            } else {
                                abLog('⚠ "' + post.title.slice(0,55) + '" still out of range after retries: ' + r.was_chars + 'c → ' + r.chars + 'c', 'err');
                            }
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) { local._gen = r.description; local.has_desc = true; local.desc = r.description; }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000);
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abUpdateSummary();
                abRenderTable();
                await abSleep(2500);
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Fix run done — ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Fix run complete: ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            document.getElementById('ab-ai-gen-missing').disabled    = false;
            document.getElementById('ab-ai-gen-all').disabled         = false;
            document.getElementById('ab-ai-fix').disabled             = false;
            document.getElementById('ab-ai-fix-titles').disabled      = false;
            document.getElementById('ab-ai-static').disabled          = false;
            document.getElementById('ab-ai-stop').style.display       = 'none';
            abState.running = false;
        }

        // ── Fix Titles ────────────────────────────────────────────────────────
        async function abFixTitles() {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled    = true;
            document.getElementById('ab-ai-gen-all').disabled         = true;
            document.getElementById('ab-ai-fix').disabled             = true;
            document.getElementById('ab-ai-fix-titles').disabled      = true;
            document.getElementById('ab-ai-static').disabled          = true;
            document.getElementById('ab-ai-stop').style.display       = 'inline-block';

            abLog('Starting title fix run — scanning for titles outside 50–60 chars...', 'info');

            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) {}
            }

            const targets = allPosts.filter(p => !p.is_homepage && !p.no_post && p.title_chars > 0 && (p.title_chars < 50 || p.title_chars > 60));
            abLog('Found ' + targets.length + ' title(s) outside 50–60 char range', 'info');

            if (targets.length === 0) {
                abLog('All titles are within range — nothing to fix.', 'info');
                abSetStatus('Nothing to fix.');
                document.getElementById('ab-ai-gen-missing').disabled  = false;
                document.getElementById('ab-ai-gen-all').disabled       = false;
                document.getElementById('ab-ai-fix').disabled           = false;
                document.getElementById('ab-ai-fix-titles').disabled    = false;
                document.getElementById('ab-ai-static').disabled        = false;
                document.getElementById('ab-ai-stop').style.display     = 'none';
                abState.running = false;
                return;
            }

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                const issue = post.title_chars < 50 ? 'too short (' + post.title_chars + 'c)' : 'too long (' + post.title_chars + 'c)';
                abSetStatus('Fixing title: "' + post.title.slice(0,50) + '" — ' + issue);
                abSetProgress(done, targets.length);

                try {
                    const data = await abPost('cs_seo_ai_fix_title', {post_id: post.id});
                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — already in range', 'skip');
                        } else {
                            done++;
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) {
                                local._new_title       = r.title;
                                local._new_title_chars = r.chars;
                            }
                            if (r.in_range) {
                                abLog('✓ Title fixed ' + r.was_chars + 'c → ' + r.chars + 'c: ' + r.title, 'ok');
                                abLog('  was: ' + (post.effective_title || post.title), 'info');
                            } else {
                                abLog('⚠ Title still out of range ' + r.was_chars + 'c → ' + r.chars + 'c: ' + r.title, 'warn');
                                abLog('  was: ' + (post.effective_title || post.title), 'info');
                            }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000);
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abRenderTable();
                await abSleep(2000);
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Title fix done — ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Title fix complete: ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            document.getElementById('ab-ai-gen-missing').disabled  = false;
            document.getElementById('ab-ai-gen-all').disabled       = false;
            document.getElementById('ab-ai-fix').disabled           = false;
            document.getElementById('ab-ai-fix-titles').disabled    = false;
            document.getElementById('ab-ai-static').disabled        = false;
            document.getElementById('ab-ai-stop').style.display     = 'none';
            abState.running = false;
        }

        async function abRegenStatic() {
            if (abState.running) return;
            abState.running = true;
            abState.stopped = false;

            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled     = true;
            document.getElementById('ab-ai-fix').disabled         = true;
            document.getElementById('ab-ai-static').disabled      = true;
            document.getElementById('ab-ai-stop').style.display   = 'inline-block';

            abLog('Starting static regeneration — clearing stale OG image data for all posts...', 'info');
            abSetStatus('Regenerating static data...');

            let done = 0, cleared = 0, errors = 0;

            for (const post of abState.posts) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                abSetStatus('Processing: "' + post.title.slice(0,50) + '"');
                abSetProgress(done, abState.posts.length);

                try {
                    const data = await abPost('cs_seo_regen_static', {post_id: post.id});
                    if (data.success) {
                        const r = data.data;
                        done++;
                        if (r.had_custom) {
                            cleared++;
                            const src = r.source === 'featured_image' ? 'now using featured image'
                                      : r.source === 'site_default'   ? 'now using site default OG image'
                                      : 'no image found';
                            abLog('✓ "' + post.title.slice(0,55) + '" — cleared stale custom image, ' + src, 'ok');
                        } else {
                            abLog('⊘ "' + post.title.slice(0,55) + '" — no custom image was set, nothing to clear', 'skip');
                        }
                    } else {
                        errors++;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + data.data, 'err');
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                }

                await abSleep(300);
            }

            abSetProgress(done, abState.posts.length);
            abSetStatus('Static regen done — ' + cleared + ' posts updated, ' + errors + ' errors');
            abLog('Static regeneration complete: ' + cleared + ' OG images cleared, ' + (done - cleared) + ' already clean, ' + errors + ' errors', cleared > 0 ? 'ok' : 'info');

            document.getElementById('ab-ai-gen-missing').disabled = false;
            document.getElementById('ab-ai-gen-all').disabled     = false;
            document.getElementById('ab-ai-fix').disabled         = false;
            document.getElementById('ab-ai-static').disabled      = false;
            document.getElementById('ab-ai-stop').style.display   = 'none';
            abState.running = false;
        }

        function abStop() { abState.stopped = true; abSetStatus('Stopping...'); }
        function abSleep(ms) { return new Promise(r => setTimeout(r, ms)); }

        // ═══════════════════════════════════════════════════════════════════════
        // ALT Text Generator
        // ═══════════════════════════════════════════════════════════════════════

        const altState = {
            posts:   [],
            running: false,
            stopped: false,
            fixed:   0,
        };

        function altLog(msg, type) {
            const wrap = document.getElementById('ab-alt-log-wrap');
            const log  = document.getElementById('ab-alt-log');
            if (wrap) wrap.style.display = '';
            if (log)  log.style.display  = 'block';
            const ts   = new Date().toLocaleTimeString('en-GB');
            const line = document.createElement('div');
            line.className = type ? 'ab-log-' + type : 'ab-log-line';
            line.textContent = '[' + ts + '] ' + msg;
            log.appendChild(line);
            log.scrollTop = log.scrollHeight;
        }

        function altSetStatus(msg) {
            document.getElementById('ab-alt-status').textContent = msg;
        }

        function altSetProgress(done, total) {
            const pct = total > 0 ? Math.round(done/total*100) : 0;
            document.getElementById('ab-alt-progress').classList.add('visible');
            document.getElementById('ab-alt-progress-fill').style.width = pct + '%';
            document.getElementById('ab-alt-prog-label').textContent =
                done + ' / ' + total + ' processed (' + pct + '%)';
        }

        function altUpdateSummary() {
            const totalMissing = altState.posts.reduce((a, p) => a + p.missing_count, 0);
            const remaining    = Math.max(0, totalMissing - altState.fixed);
            document.getElementById('alt-sum-posts').textContent  = altState.posts.reduce((a,p)=>a+(p.missing_count>0?1:0),0);
            document.getElementById('alt-sum-images').textContent = remaining;
            document.getElementById('alt-sum-done').textContent   = altState.fixed;
            document.getElementById('ab-alt-summary').style.display = 'grid';
        }

        function altRenderTable() {
            const wrap = document.getElementById('ab-alt-posts-wrap');

            // Sync the toolbar checkbox to current state
            const cbx = document.getElementById('ab-alt-show-all');
            if (cbx) cbx.checked = altState.showAll || false;

            const showAll = altState.showAll || false;
            const visiblePosts = showAll
                ? altState.posts
                : altState.posts.filter(p => p.missing_count > 0 || p._done);

            if (!altState.posts.length) {
                wrap.innerHTML = '<p style="color:#1a7a34;margin-top:12px">✓ No images found in posts or featured images.</p>';
                return;
            }

            if (!visiblePosts.length) {
                wrap.innerHTML = '<p style="color:#1a7a34">✓ All images across all posts already have ALT text.</p>';
                return;
            }

            let rows = visiblePosts.map(p => {
                const hasMissing = p.missing_count > 0 && !p._done;
                const statusBadge = p._done
                    ? '<span class="ab-badge ab-badge-ok">✓ Fixed</span>'
                    : hasMissing
                        ? '<span class="ab-badge ab-badge-none">' + p.missing_count + ' missing</span>'
                        : '<span class="ab-badge ab-badge-ok">✓ All ALT set</span>';

                // Build image rows
                const imgRows = (p.images || []).map(img => {
                    const filename = img.src.split('/').pop().split('?')[0];
                    const missing  = img.missing && !p._done;
                    const altText  = img.alt || (p._done ? '(generated this session)' : '');
                    return '<tr style="background:' + (missing ? '#fff8f8' : '#f8fff8') + '">' +
                        '<td style="padding:6px 8px;width:60px;vertical-align:middle">' +
                            '<img src="' + abEsc(img.src) + '" style="width:52px;height:40px;object-fit:cover;border-radius:3px;border:1px solid #ddd">' +
                        '</td>' +
                        '<td style="padding:6px 8px;font-size:12px;color:#555;vertical-align:middle;word-break:break-all">' +
                            abEsc(filename) +
                        '</td>' +
                        '<td style="padding:6px 8px;font-size:12px;vertical-align:middle">' +
                            (missing
                                ? '<span style="color:#c00">✗ Missing</span>'
                                : '<span style="color:#1a7a34">✓ </span><em style="color:#444">' + abEsc(altText) + '</em>') +
                        '</td>' +
                    '</tr>';
                }).join('');

                const expanded = p._expanded || false;
                const imgCount = (p.images || []).length;
                const toggleId = 'ab-alt-toggle-' + p.id;

                return '<tr id="ab-alt-row-' + p.id + '" style="border-top:2px solid #e0e0e0">' +
                    '<td style="padding:8px 10px;vertical-align:middle">' +
                        '<strong>' + abEsc(abDecodeTitle(p.title)) + '</strong>' +
                        '<br><small style="color:#888">' + p.type + ' · ' + p.date + ' · ' + imgCount + ' image(s)</small>' +
                    '</td>' +
                    '<td style="padding:8px 10px;vertical-align:middle">' + statusBadge + '</td>' +
                    '<td style="padding:8px 10px;vertical-align:middle;white-space:nowrap">' +
                        (hasMissing ? '<button class="button ab-row-btn" onclick="altGenOne(' + p.id + ')" id="ab-alt-btn-' + p.id + '" ' + (p._processing?'disabled':'') + '>' +
                            (p._processing ? '<span class="ab-spinner">⟳</span>' : '✦') + ' Generate</button> ' : '') +
                        '<button class="button" style="font-size:11px;padding:2px 8px" id="' + toggleId + '" onclick="altToggleImages(' + p.id + ')">' +
                            (expanded ? '▲ Hide' : '▼ Images') +
                        '</button>' +
                    '</td>' +
                '</tr>' +
                '<tr id="ab-alt-imgs-' + p.id + '" style="display:' + (expanded?'table-row':'none') + '">' +
                    '<td colspan="3" style="padding:0 0 8px 20px;background:#fafafa">' +
                        '<table style="width:100%;border-collapse:collapse">' +
                        '<thead><tr>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left;width:60px">Thumb</th>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left">Filename</th>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left">ALT Text</th>' +
                        '</tr></thead>' +
                        '<tbody>' + imgRows + '</tbody>' +
                        '</table>' +
                    '</td>' +
                '</tr>';
            }).join('');

            wrap.innerHTML =
                '<table class="ab-posts" style="width:100%">' +
                '<thead><tr><th style="width:45%">Post</th><th style="width:20%">Status</th><th style="width:35%">Actions</th></tr></thead>' +
                '<tbody>' + rows + '</tbody></table>';
        }

        function altToggleImages(postId) {
            const post = altState.posts.find(p => p.id === postId);
            if (!post) return;
            post._expanded = !post._expanded;
            altRenderTable();
        }

        function altLoad() {
            document.getElementById('ab-alt-load-btn').disabled = true;
            altSetStatus('Scanning posts...');
            abPost('cs_seo_alt_get_posts', {}).then(data => {
                document.getElementById('ab-alt-load-btn').disabled = false;
                if (!data.success) { altLog('Failed to scan: ' + data.data, 'err'); return; }
                altState.posts = data.data.posts;
                // Auto-enable show-all when nothing is missing so the audit view is useful
                if (data.data.missing_alt === 0) altState.showAll = true;
                const cbx = document.getElementById('ab-alt-show-all');
                if (cbx) cbx.checked = altState.showAll;
                altUpdateSummary();
                altRenderTable();
                document.getElementById('ab-alt-load-cta').style.display = 'none';
                document.getElementById('ab-alt-toolbar').style.display  = 'flex';
                document.getElementById('ab-alt-gen-all').disabled       = data.data.missing_alt === 0;
                const total = data.data.missing_alt;
                altSetStatus(total > 0
                    ? total + ' image(s) missing ALT across ' + altState.posts.filter(p=>p.missing_count>0).length + ' post(s)'
                    : '✓ All images have ALT text');
                if (total === 0) {
                    altLog('✓ All images across all posts already have ALT text.', 'ok');
                }
            }).catch(e => {
                document.getElementById('ab-alt-load-btn').disabled = false;
                altLog('Error: ' + e.message, 'err');
            });
        }

        function altGenOne(postId) {
            if (!abCheckApiKey()) return;
            const post = altState.posts.find(p => p.id === postId);
            if (!post) return;
            post._processing = true;
            post._expanded   = true;
            altRenderTable();

            abPost('cs_seo_alt_generate_one', {post_id: postId}).then(data => {
                post._processing = false;
                if (data.success) {
                    const updated   = data.data.updated;
                    const generated = data.data.generated || [];
                    post._done = true;
                    post.missing_count = 0;
                    // Store the generated alt text back onto each image object by src match
                    (post.images || []).forEach(img => {
                        img.missing = false;
                        const match = generated.find(g => g.src === img.src);
                        if (match) img.alt = match.alt;
                    });
                    altState.fixed += updated;
                    altLog('✓ "' + abDecodeTitle(post.title).slice(0,55) + '" — ' + updated + ' image(s) updated', 'ok');
                    altUpdateSummary();
                } else {
                    altLog('✗ "' + abDecodeTitle(post.title).slice(0,45) + '": ' + (data.data || 'Unknown error'), 'err');
                }
                altRenderTable();
            }).catch(e => {
                post._processing = false;
                altLog('✗ Network error: ' + e.message, 'err');
                altRenderTable();
            });
        }

        async function altGenAll(force) {
            if (!abCheckApiKey()) return;
            if (altState.running) return;

            if (force && !confirm('This will regenerate ALT text for ALL images across ALL posts, overwriting existing ALT text. Continue?')) return;

            altState.running = true;
            altState.stopped = false;

            document.getElementById('ab-alt-gen-all').disabled     = true;
            document.getElementById('ab-alt-force-all').disabled   = true;
            document.getElementById('ab-alt-stop').style.display   = 'inline-block';

            altLog('Starting ALT text generation run' + (force ? ' (FORCE mode — all images)' : '') + '...', 'info');

            // In force mode: process all posts with images. In normal mode: only posts with missing ALT.
            const postsToProcess = force
                ? altState.posts.filter(p => !p._done)
                : altState.posts.filter(p => !p._done && p.missing_count > 0);
            altLog(postsToProcess.length + ' post(s) to process', 'info');

            let done = 0, errors = 0, totalFixed = 0;

            for (const post of postsToProcess) {
                if (altState.stopped) { altLog('Stopped after ' + done + ' posts', 'skip'); break; }
                if (post._done) continue;

                altSetStatus('Processing: "' + post.title.slice(0,50) + '"...');
                altSetProgress(done, postsToProcess.length);
                post._processing = true;
                altRenderTable();

                try {
                    const data = await abPost('cs_seo_alt_generate_all', {post_id: post.id, force: force ? 1 : 0});
                    post._processing = false;
                    if (data.success) {
                        const updated   = data.data.updated;
                        const generated = data.data.generated || [];
                        post._done = true;
                        post.missing_count = 0;
                        (post.images || []).forEach(img => {
                            img.missing = false;
                            const match = generated.find(g => g.src === img.src);
                            if (match) img.alt = match.alt;
                        });
                        totalFixed += updated;
                        altState.fixed += updated;
                        altLog('✓ "' + abDecodeTitle(post.title).slice(0,55) + '" — ' + updated + ' image(s) updated', 'ok');
                    } else {
                        errors++;
                        altLog('✗ "' + post.title.slice(0,45) + '": ' + (data.data || 'Error'), 'err');
                        await new Promise(r => setTimeout(r, 5000));
                    }
                } catch(e) {
                    post._processing = false;
                    errors++;
                    altLog('✗ Network error: ' + e.message, 'err');
                    await new Promise(r => setTimeout(r, 5000));
                }

                done++;
                altUpdateSummary();
                altRenderTable();
                await new Promise(r => setTimeout(r, 1500));
            }

            altSetProgress(done, postsToProcess.length);
            altSetStatus('Done — ' + totalFixed + ' image(s) updated across ' + done + ' post(s), ' + errors + ' errors');
            altLog('Run complete: ' + totalFixed + ' images updated, ' + errors + ' errors', totalFixed > 0 ? 'ok' : 'info');

            document.getElementById('ab-alt-gen-all').disabled     = false;
            document.getElementById('ab-alt-force-all').disabled   = false;
            document.getElementById('ab-alt-stop').style.display   = 'none';
            altState.running = false;
        }

        function altStop() { altState.stopped = true; altSetStatus('Stopping...'); }
        </script>
        </div><!-- /wrap -->
        <?php
    }

    private function tr_text(string $k, string $label, array $o, string $placeholder = '', string $hint = ''): void { ?>
        <tr><th><label><?php echo esc_html($label); ?></label></th>
            <td>
                <input class="regular-text"
                    name="<?php echo esc_attr(self::OPT); ?>[<?php echo esc_attr($k); ?>]"
                    value="<?php echo esc_attr((string)($o[$k] ?? '')); ?>"
                    <?php if ($placeholder) echo 'placeholder="' . esc_attr($placeholder) . '"'; ?>>
                <?php if ($hint): ?>
                    <p class="description"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td></tr>
    <?php }

    private function tr_bool(string $k, string $label, array $o): void { ?>
        <tr><th><?php echo esc_html($label); ?></th>
            <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[<?php echo esc_attr($k); ?>]" value="1" <?php checked((int)($o[$k] ?? 0), 1); ?>> Enabled</label></td></tr>
    <?php }

    // =========================================================================
    public function ajax_fetch_robots(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }
        $physical = ABSPATH . 'robots.txt';
        if (file_exists($physical)) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
                WP_Filesystem();
            }
            $content = $wp_filesystem->get_contents($physical);
            if ($content === false) {
                wp_send_json_error('Could not read robots.txt — check file permissions.');
            }
            wp_send_json_success(['content' => $content, 'source' => 'file']);
        } else {
            // Generate what WordPress/plugin would serve
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- 'robots_txt' is a WordPress core filter
            $content = apply_filters('robots_txt', '', (bool) get_option('blog_public'));
            wp_send_json_success(['content' => $content ?: '(empty — WordPress default)', 'source' => 'dynamic']);
        }
    }

    public function ajax_https_scan(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }

        try {
            global $wpdb;

            $comment_tables  = [$wpdb->comments, $wpdb->commentmeta];
            // Core URL options — fixing these in the DB is overridden by wp-config.php constants
            $core_url_option_names = ['siteurl', 'home'];

            $tables = [
                $wpdb->posts       => ['post_content', 'post_excerpt', 'guid'],
                $wpdb->postmeta    => ['meta_value'],
                $wpdb->options     => ['option_value'],
                $wpdb->comments    => ['comment_content', 'comment_author_url'],
                $wpdb->commentmeta => ['meta_value'],
            ];

            // $by_domain[domain] = list of {table, column, url} entries
            // $domain_tables[domain] = set of distinct tables the domain appears in
            // $domain_option_names[domain] = list of wp_options.option_name values where this domain appears
            $by_domain          = [];
            $domain_tables      = [];
            $domain_option_names = [];
            $counts             = [];
            $total              = 0;

            foreach ($tables as $table => $cols) {
                foreach ($cols as $col) {
                    // For wp_options, also select option_name so we can detect siteurl/home
                    $is_options_table = ($table === $wpdb->options);
                    $select_cols = $is_options_table
                        ? "`option_name`, `{$col}`"
                        : "`{$col}`";

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                        "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE %s",
                        '%' . $wpdb->esc_like('http://') . '%'
                    ));
                    if ($count === 0) continue;

                    $counts[] = ['table' => $table, 'column' => $col, 'count' => $count];
                    $total   += $count;

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                    $rows = $wpdb->get_results($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col/select_cols from $wpdb object properties
                        "SELECT {$select_cols} FROM `{$table}` WHERE `{$col}` LIKE %s LIMIT 20",
                        '%' . $wpdb->esc_like('http://') . '%'
                    ), ARRAY_A);

                    foreach ((array)$rows as $row) {
                        $val         = $row[$col];
                        $option_name = $is_options_table ? ($row['option_name'] ?? '') : '';
                        preg_match_all('#http://[^\s\'"<>()\[\]]+#', $val, $matches);
                        foreach ($matches[0] as $url) {
                            $url    = rtrim($url, '.,;');
                            $domain = (string) wp_parse_url($url, PHP_URL_HOST);
                            if (!$domain) continue;
                            if (!isset($by_domain[$domain]))          $by_domain[$domain]          = [];
                            if (!isset($domain_tables[$domain]))      $domain_tables[$domain]      = [];
                            if (!isset($domain_option_names[$domain])) $domain_option_names[$domain] = [];
                            $entry = ['table' => $table, 'column' => $col, 'url' => $url];
                            if (!in_array($entry, $by_domain[$domain], true)) {
                                $by_domain[$domain][] = $entry;
                            }
                            $domain_tables[$domain][$table] = true;
                            if ($option_name && in_array($option_name, $core_url_option_names, true)) {
                                $domain_option_names[$domain][$option_name] = true;
                            }
                        }
                    }
                }
            }

            // Detect if WP_HOME or WP_SITEURL are hardcoded in wp-config.php
            // (defined as http:// constants — these override the DB and cause perpetual reversion)
            $wp_config_overrides = [];
            if (defined('WP_HOME') && strncmp((string)WP_HOME, 'http://', 7) === 0) {
                $wp_config_overrides['home'] = (string)WP_HOME;
            }
            if (defined('WP_SITEURL') && strncmp((string)WP_SITEURL, 'http://', 7) === 0) {
                $wp_config_overrides['siteurl'] = (string)WP_SITEURL;
            }

            $home_host   = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            $domain_meta = [];
            foreach (array_keys($by_domain) as $domain) {
                $is_ip   = (bool) filter_var($domain, FILTER_VALIDATE_IP);
                $is_own  = (stripos($domain, $home_host) !== false);

                // A domain is spam-only when every table it appears in is a comment table
                $tables_found    = array_keys($domain_tables[$domain]);
                $all_in_comments = !$is_own && !empty($tables_found) && empty(
                    array_diff($tables_found, $comment_tables)
                );

                // Detect if this domain appears in siteurl/home options
                $option_names_found = array_keys($domain_option_names[$domain] ?? []);
                // Check if any of those options are overridden by a wp-config.php constant
                $overridden_by_wpconfig = [];
                foreach ($option_names_found as $opt_name) {
                    if (isset($wp_config_overrides[$opt_name])) {
                        $overridden_by_wpconfig[] = $opt_name;
                    }
                }

                $domain_meta[$domain] = [
                    'is_ip'                  => $is_ip,
                    'is_own'                 => $is_own,
                    'is_spam'                => $all_in_comments,
                    'core_url_options'       => $option_names_found,     // e.g. ['home', 'siteurl']
                    'overridden_by_wpconfig' => $overridden_by_wpconfig, // e.g. ['home']
                    'count'                  => count($by_domain[$domain]),
                    'urls'                   => array_slice($by_domain[$domain], 0, 20),
                ];
            }

            wp_send_json_success([
                'total'              => $total,
                'counts'             => $counts,
                'domain_meta'        => $domain_meta,
                'wp_config_overrides' => $wp_config_overrides,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

    /**
     * Safely replace http:// with https:// in a value, handling PHP serialized strings
     * by re-serializing with correct byte counts.
     *
     * When the serialized value contains objects with unknown classes, PHP creates
     * __PHP_Incomplete_Class instances that cannot be traversed safely.  In that case
     * we fall back to a regex replacement directly on the raw serialized string and
     * fix up the byte-length prefixes so the result stays valid.
     */
    private function https_replace_value(string $value, array $domains): string {
        if (!is_serialized($value)) {
            return $this->https_replace_string($value, $domains);
        }

        // Attempt to unserialize with allowed_classes => false.  Unknown classes
        // become __PHP_Incomplete_Class objects rather than triggering a fatal.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- intentional: restoring serialized DB data with safe options
        $data = @unserialize($value, ['allowed_classes' => false]);

        if ($data === false) {
            // Corrupt serialized data — patch URLs in-place at the string level.
            return $this->https_replace_serialized_raw($value, $domains);
        }

        // If the unserialized graph contains any __PHP_Incomplete_Class objects
        // we cannot safely re-serialize (properties may be lost).  Fall back to
        // the raw string approach which is class-agnostic.
        if ($this->has_incomplete_class($data)) {
            return $this->https_replace_serialized_raw($value, $domains);
        }

        $data = $this->https_replace_recursive($data, $domains);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- intentional: writing back safely modified serialized data
        return serialize($data);
    }

    /** Recursively check whether $data contains any __PHP_Incomplete_Class instances. */
    private function has_incomplete_class(mixed $data): bool {
        if (is_object($data)) {
            if ($data instanceof \__PHP_Incomplete_Class) {
                return true;
            }
            foreach (@get_object_vars($data) as $v) {
                if ($this->has_incomplete_class($v)) return true;
            }
        }
        if (is_array($data)) {
            foreach ($data as $v) {
                if ($this->has_incomplete_class($v)) return true;
            }
        }
        return false;
    }

    /**
     * Replace http:// URLs inside a raw serialized PHP string without deserializing.
     * Handles the s:<len>:"<value>" format by recalculating byte lengths after substitution.
     */
    private function https_replace_serialized_raw(string $value, array $domains): string {
        // Do the URL substitution on the raw string first.
        $replaced = $this->https_replace_string($value, $domains);

        if ($replaced === $value) {
            return $value; // Nothing changed — skip the expensive regex fix-up.
        }

        // Fix s:<len>:"<value>" byte-count prefixes that may now be wrong because
        // "http://" (7 bytes) was replaced with "https://" (8 bytes).
        // We rebuild every string token with the correct length.
        return preg_replace_callback(
            '/s:(\d+):"(.*?)";/s',
            static function (array $m): string {
                return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
            },
            $replaced
        ) ?? $replaced;
    }

    private function https_replace_recursive(mixed $data, array $domains): mixed {
        if (is_string($data)) {
            return $this->https_replace_string($data, $domains);
        }
        if (is_array($data)) {
            return array_map(fn($v) => $this->https_replace_recursive($v, $domains), $data);
        }
        if (is_object($data)) {
            // Use get_object_vars with error suppression in case of edge-case objects.
            foreach (@get_object_vars($data) as $k => $v) {
                $data->$k = $this->https_replace_recursive($v, $domains);
            }
        }
        return $data;
    }

    private function https_replace_string(string $value, array $domains): string {
        if (empty($domains)) {
            return preg_replace('#http://#', 'https://', $value);
        }
        foreach ($domains as $domain) {
            $value = str_replace('http://' . $domain, 'https://' . $domain, $value);
        }
        return $value;
    }

    public function ajax_https_fix(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }


        try {
            global $wpdb;

            $domains_raw = isset($_POST['domains']) ? sanitize_text_field(wp_unslash($_POST['domains'])) : '';
            $domains = array_filter(array_map('trim', explode(',', $domains_raw)));

            $tables = [
                $wpdb->posts       => ['post_content', 'post_excerpt', 'guid'],
                $wpdb->postmeta    => ['meta_value'],
                $wpdb->options     => ['option_value'],
                $wpdb->comments    => ['comment_content', 'comment_author_url'],
                $wpdb->commentmeta => ['meta_value'],
            ];

            $changes = [];
            $total   = 0;

            foreach ($tables as $table => $cols) {
                foreach ($cols as $col) {
                    if (!empty($domains)) {
                        $where_parts = array_map(function($d) use ($wpdb, $col) {
                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            return $wpdb->prepare("`{$col}` LIKE %s", '%' . $wpdb->esc_like('http://' . $d) . '%');
                        }, $domains);
                        $where = implode(' OR ', $where_parts);
                    } else {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $where = $wpdb->prepare("`{$col}` LIKE %s", '%' . $wpdb->esc_like('http://') . '%');
                    }

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object; where built via $wpdb->prepare()
                    $affected_rows = $wpdb->get_results(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object; where built via $wpdb->prepare()
                        "SELECT * FROM `{$table}` WHERE {$where}",
                        ARRAY_A
                    );

                    if (empty($affected_rows)) continue;

                    foreach ($affected_rows as $row) {
                        $old_val = $row[$col];
                        preg_match_all('#http://[^\s\'"<>()\[\]]+#', $old_val, $old_url_matches);

                        $new_val = $this->https_replace_value($old_val, $domains);
                        if ($new_val === $old_val) continue;

                        $pk = null; $pk_val = null;
                        foreach (['ID', 'meta_id', 'option_id', 'comment_ID'] as $pk_name) {
                            if (isset($row[$pk_name])) { $pk = $pk_name; $pk_val = $row[$pk_name]; break; }
                        }
                        if (!$pk) continue;

                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct update required for HTTPS migration across arbitrary tables
                        $updated = $wpdb->update($table, [$col => $new_val], [$pk => $pk_val]);

                        if ($updated !== false && $updated > 0) {
                            foreach (array_unique($old_url_matches[0]) as $url) {
                                $url = rtrim($url, '.,;');
                                $domain = (string) wp_parse_url($url, PHP_URL_HOST);
                                if (!empty($domains) && !in_array($domain, $domains, true)) continue;
                                $changes[] = [
                                    'table'  => $table,
                                    'column' => $col,
                                    'id'     => $pk_val,
                                    'from'   => $url,
                                    'to'     => preg_replace('#^http://#', 'https://', $url),
                                ];
                            }
                            $total++;
                        }
                    }
                }
            }

            wp_cache_flush();

            wp_send_json_success([
                'fixed'   => $total,
                'changes' => $changes,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

    /**
     * Remove all database references to a given domain.
     *
     * For comment tables: deletes the entire comment (and its meta) via wp_delete_comment().
     * For posts/postmeta/options: strips the bare http://domain... URL pattern from the
     * column value rather than deleting the whole row, since those rows contain real content.
     * GUIDs in wp_posts are set to empty string (WordPress re-generates them on save).
     */
    public function ajax_https_delete(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        if (empty($domain)) {
            wp_send_json_error('No domain provided.');
        }

        try {
            global $wpdb;
            $like    = '%' . $wpdb->esc_like($domain) . '%';
            $deleted = 0;

            // 1. Delete entire comments (and meta) that reference this domain
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $comment_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments}
                  WHERE comment_content LIKE %s OR comment_author_url LIKE %s",
                $like, $like
            ));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $meta_comment_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT comment_id FROM {$wpdb->commentmeta} WHERE meta_value LIKE %s",
                $like
            ));
            $all_comment_ids = array_unique(array_merge(
                array_map('intval', (array)$comment_ids),
                array_map('intval', (array)$meta_comment_ids)
            ));
            foreach ($all_comment_ids as $cid) {
                if (wp_delete_comment($cid, true)) $deleted++;
            }

            // 2. Strip URL references from posts (post_content, post_excerpt)
            foreach (['post_content', 'post_excerpt'] as $col) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- col is a hardcoded string literal from foreach
                $rows = $wpdb->get_results($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- col is a hardcoded string literal from foreach
                    "SELECT ID, `{$col}` FROM {$wpdb->posts} WHERE `{$col}` LIKE %s",
                    $like
                ), ARRAY_A);
                foreach ((array)$rows as $row) {
                    $new_val = preg_replace(
                        '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                        '',
                        $row[$col]
                    );
                    if ($new_val !== $row[$col]) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->update($wpdb->posts, [$col => $new_val], ['ID' => (int)$row['ID']]);
                        $deleted++;
                    }
                }
            }

            // 3. Clear guid entirely for attachment rows that used this IP
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $guid_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$guid_rows as $row) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->posts, ['guid' => ''], ['ID' => (int)$row['ID']]);
                $deleted++;
            }

            // 4. Strip from postmeta values
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- direct query required for domain removal; meta_value scan is intentional
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$meta_rows as $row) {
                $new_val = preg_replace(
                    '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                    '',
                    $row['meta_value']
                );
                if ($new_val !== $row['meta_value']) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- update by meta_id PK, not a slow scan
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $new_val], ['meta_id' => (int)$row['meta_id']]);
                    $deleted++;
                }
            }

            // 5. Strip from options
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $opt_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_value LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$opt_rows as $row) {
                $new_val = preg_replace(
                    '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                    '',
                    $row['option_value']
                );
                if ($new_val !== $row['option_value']) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($wpdb->options, ['option_value' => $new_val], ['option_id' => (int)$row['option_id']]);
                    $deleted++;
                }
            }

            wp_cache_flush();

            wp_send_json_success([
                'deleted' => $deleted,
                'domain'  => $domain,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

    public function ajax_rename_robots(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }
        // Check ABSPATH first, then one level up for subdirectory installs
        $physical = ABSPATH . 'robots.txt';
        if (!file_exists($physical)) {
            $alt = dirname(rtrim(ABSPATH, '/')) . '/robots.txt';
            if (file_exists($alt)) {
                $physical = $alt;
            }
        }
        $backup = preg_replace('/robots\.txt$/', 'robots.txt.bak', $physical);
        if (!file_exists($physical)) {
            wp_send_json_error('No physical robots.txt file found — nothing to rename.');
        }
        if (!wp_is_writable(dirname($physical))) {
            wp_send_json_error('robots.txt exists but is not writable. Check file permissions (should be 644).');
        }
        $old_content = file_get_contents($physical); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ($wp_filesystem->move($physical, $backup, true)) {
            update_option('cs_seo_robots_bak', $old_content);
            wp_send_json_success(['message' => 'robots.txt renamed to robots.txt.bak. The plugin is now managing your robots.txt.']);
        } else {
            wp_send_json_error('rename() failed — check that the web server has write access to the WordPress root directory.');
        }
    }

    // Sitemap  (paginated index — 200 URLs per child sitemap)
    // =========================================================================

    const SITEMAP_PER_FILE    = 5000; // URLs per XML sitemap file served to Google
    const SITEMAP_PREVIEW_PER = 200;  // Rows per page in the admin preview table

    public function maybe_register_sitemap(): void {
        if (!(int) $this->opts['enable_sitemap']) return;
        // /sitemap.xml          → sitemap index listing all child sitemaps
        // /sitemap-1.xml etc.   → child sitemaps with up to 200 URLs each
        add_rewrite_rule('^sitemap\.xml$',       'index.php?cs_seo_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-(\d+)\.xml$', 'index.php?cs_seo_sitemap=page&cs_seo_sitemap_pg=$matches[1]', 'top');
        add_rewrite_tag('%cs_seo_sitemap%',    '(index|page)');
        add_rewrite_tag('%cs_seo_sitemap_pg%', '\d+');
        add_action('template_redirect', [$this, 'maybe_render_sitemap']);
    }

    public function maybe_render_sitemap(): void {
        $mode = get_query_var('cs_seo_sitemap');
        if (!$mode) return;
        header('Content-Type: application/xml; charset=utf-8');
        header('X-Robots-Tag: noindex, follow');
        header('Cache-Control: public, max-age=3600');
        if ($mode === 'index') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $this->build_sitemap_index();
        } else {
            $pg = max(1, (int) get_query_var('cs_seo_sitemap_pg'));
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $this->build_sitemap_page($pg);
        }
        exit;
    }

    // =========================================================================
    // llms.txt
    // =========================================================================

    public function maybe_register_llms_txt(): void {
        if (!(int)($this->opts['enable_llms_txt'] ?? 0)) return;
        add_rewrite_rule('^llms\.txt$', 'index.php?cs_seo_llms=1', 'top');
        add_rewrite_tag('%cs_seo_llms%', '1');
        add_action('template_redirect', [$this, 'maybe_render_llms_txt']);
    }

    public function maybe_render_llms_txt(): void {
        if (!get_query_var('cs_seo_llms')) return;
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->build_llms_txt();
        exit;
    }

    private function build_llms_txt(): string {
        $site_name  = trim((string)($this->opts['site_name'] ?? '')) ?: get_bloginfo('name');
        $site_desc  = trim((string)($this->opts['home_desc'] ?? ''))
            ?: trim((string)($this->opts['default_desc'] ?? ''))
            ?: get_bloginfo('description');
        $person     = trim((string)($this->opts['person_name'] ?? ''));
        $job_title  = trim((string)($this->opts['person_job_title'] ?? ''));
        $home_url   = home_url('/');

        $lines   = [];
        $lines[] = '# ' . $site_name;
        $lines[] = '';
        if ($site_desc) {
            $lines[] = '> ' . $site_desc;
            $lines[] = '';
        }
        if ($person) {
            $byline = $person . ($job_title ? ', ' . $job_title : '');
            $lines[] = 'Author: ' . $byline;
            $lines[] = '';
        }
        $lines[] = '## Site';
        $lines[] = '';
        $lines[] = '- [Homepage](' . $home_url . ')';
        $lines[] = '';

        // All published posts grouped by post type, ordered by date desc.
        $posts = get_posts([
            'post_type'      => ['post', 'page'],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        $by_type = ['post' => [], 'page' => []];
        foreach ($posts as $pid) {
            $p    = get_post($pid);
            $type = $p->post_type;
            $desc = trim((string) get_post_meta($pid, self::META_DESC, true));
            $entry = '- [' . get_the_title($pid) . '](' . get_permalink($pid) . ')';
            if ($desc) $entry .= ': ' . $desc;
            $by_type[$type][] = $entry;
        }

        if (!empty($by_type['post'])) {
            $lines[] = '## Blog Posts';
            $lines[] = '';
            foreach ($by_type['post'] as $entry) $lines[] = $entry;
            $lines[] = '';
        }
        if (!empty($by_type['page'])) {
            $lines[] = '## Pages';
            $lines[] = '';
            foreach ($by_type['page'] as $entry) $lines[] = $entry;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    public function ajax_llms_preview(): void {
        $this->ajax_check();
        wp_send_json_success(['content' => $this->build_llms_txt()]);
    }


    private function get_all_sitemap_urls(): array {
        $post_types  = (array)($this->opts['sitemap_post_types'] ?? ['post', 'page']);
        $inc_tax     = (int)($this->opts['sitemap_taxonomies'] ?? 0);
        $exclude_raw = trim((string)($this->opts['sitemap_exclude'] ?? ''));

        $exclude_urls = [];
        $exclude_ids  = [];
        if ($exclude_raw !== '') {
            foreach (preg_split('/\r?\n/', $exclude_raw) as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (is_numeric($line)) {
                    $exclude_ids[] = (int) $line;
                } else {
                    $exclude_urls[] = trailingslashit($line);
                }
            }
        }

        $urls = [['loc' => home_url('/'), 'lastmod' => gmdate('c'), 'type' => 'home', 'title' => 'Homepage']];

        if (!empty($post_types)) {
            $sitemap_query_args = [
                'post_type'           => $post_types,
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
                'orderby'             => 'modified',
                'order'               => 'DESC',
                'fields'              => 'ids',
            ];
            if (!empty($exclude_ids)) {
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- required for user-defined URL exclusions
                $sitemap_query_args['post__not_in'] = $exclude_ids;
            }
            $q = new WP_Query($sitemap_query_args);
            foreach ($q->posts as $pid) {
                $pid       = (int) $pid;
                $permalink = get_permalink($pid);
                if (in_array(trailingslashit($permalink), $exclude_urls, true)) continue;
                $pt   = get_post_type($pid);
                $type = $pt === 'page' ? 'page' : ($pt === 'post' ? 'post' : 'cpt');
                $urls[] = [
                    'loc'     => $permalink,
                    'lastmod' => get_post_modified_time('c', true, $pid),
                    'type'    => $type,
                    'title'   => get_the_title($pid),
                ];
            }
        }

        if ($inc_tax) {
            foreach (get_taxonomies(['public' => true], 'names') as $tax) {
                $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'number' => 0]);
                if (is_wp_error($terms)) continue;
                foreach ($terms as $term) {
                    $link = get_term_link($term);
                    if (is_wp_error($link)) continue;
                    if (in_array(trailingslashit($link), $exclude_urls, true)) continue;
                    $urls[] = [
                        'loc'     => $link,
                        'lastmod' => '',
                        'type'    => 'tax',
                        'title'   => $term->name . ' (' . $tax . ')',
                    ];
                }
            }
        }

        return $urls;
    }

    private function build_sitemap_index(): string {
        $all        = $this->get_all_sitemap_urls();
        $total      = count($all);
        $per_page   = self::SITEMAP_PER_FILE;
        $page_count = max(1, (int) ceil($total / $per_page));

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        for ($i = 1; $i <= $page_count; $i++) {
            $xml .= "  <sitemap>\n";
            $xml .= "    <loc>" . esc_url(home_url("/sitemap-{$i}.xml")) . "</loc>\n";
            $xml .= "    <lastmod>" . esc_html(gmdate('c')) . "</lastmod>\n";
            $xml .= "  </sitemap>\n";
        }
        $xml .= "</sitemapindex>\n";
        return $xml;
    }

    private function build_sitemap_page(int $pg): string {
        $all      = $this->get_all_sitemap_urls();
        $per_page = self::SITEMAP_PER_FILE;
        $slice    = array_slice($all, ($pg - 1) * $per_page, $per_page);

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($slice as $u) {
            $xml .= "  <url>\n";
            $xml .= "    <loc>" . esc_url($u['loc']) . "</loc>\n";
            if (!empty($u['lastmod'])) {
                $xml .= "    <lastmod>" . esc_html($u['lastmod']) . "</lastmod>\n";
            }
            $xml .= "  </url>\n";
        }
        $xml .= "</urlset>\n";
        return $xml;
    }

    // AJAX preview — returns paginated entries for the UI table
    public function ajax_sitemap_preview(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorised');
        }
        // Preview works regardless of enable_sitemap so you can check before enabling
        $all      = $this->get_all_sitemap_urls();
        $total    = count($all);
        $per_page = self::SITEMAP_PREVIEW_PER;
        $pages    = max(1, (int) ceil($total / $per_page));
        $pg       = max(1, min($pages, absint(wp_unslash($_POST['sitemap_pg'] ?? 1))));
        $slice    = array_slice($all, ($pg - 1) * $per_page, $per_page);

        // Normalise lastmod to Y-m-d for display
        $entries = array_map(function($u) {
            $lm = $u['lastmod'] ?? '';
            if ($lm) {
                $ts = strtotime($lm);
                $lm = $ts ? gmdate('Y-m-d', $ts) : '';
            }
            return [
                'loc'     => $u['loc'],
                'type'    => $u['type'],
                'lastmod' => $lm,
                'title'   => $u['title'] ?? '',
            ];
        }, $slice);

        wp_send_json_success([
            'entries'   => $entries,
            'total'     => $total,
            'page'      => $pg,
            'pages'     => $pages,
            'per_page'  => $per_page,
        ]);
    }

    // =========================================================================
    // Robots.txt
    // =========================================================================

    public function filter_robots_txt(string $output, bool $public): string {
        if (!$public) {
            return "User-agent: *\nDisallow: /\n";
        }

        // Use saved custom robots.txt content, falling back to default.
        $custom = trim((string)($this->opts['robots_txt'] ?? ''));
        if ($custom === '') {
            $custom = self::default_robots_txt();
        }

        $lines = explode("\n", $custom);

        // Append AI training bot blocklist if enabled.
        if ((int)($this->opts['block_ai_bots'] ?? 1)) {
            $lines[] = '';
            foreach ([
                'GPTBot', 'ChatGPT-User', 'CCBot', 'anthropic-ai', 'Claude-Web',
                'Omgilibot', 'FacebookBot', 'Bytespider', 'Applebot-Extended',
            ] as $bot) {
                $lines[] = 'User-agent: ' . $bot;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        // Append sitemap directive if enabled.
        if ((int)($this->opts['enable_sitemap'] ?? 0)) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . home_url('/sitemap.xml');
        }

        // Append llms.txt reference if enabled.
        if ((int)($this->opts['enable_llms_txt'] ?? 0)) {
            $lines[] = '';
            $lines[] = '# LLM crawler guidance';
            $lines[] = 'LLMs-txt: ' . home_url('/llms.txt');
        }

        $content = implode("\n", $lines);
        $content = preg_replace('/[ \t]+$/m', '', $content);
        return rtrim($content) . "\n";
    }
}

// Flush rewrite rules on activation so sitemap URLs work immediately,
// and on deactivation to clean up.
register_activation_hook(__FILE__, function(): void {
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'CloudScale SEO AI Optimizer requires PHP 8.0 or higher. Your server is running PHP ' . esc_html(PHP_VERSION) . '.',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            'CloudScale SEO AI Optimizer requires WordPress 6.0 or higher.',
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    // If a physical robots.txt exists in the WordPress root, rename it so
    // WordPress's robots_txt filter can take over. We keep the original as
    // robots.txt.bak so it can be restored if needed.
    $root      = ABSPATH;
    $physical  = $root . 'robots.txt';
    $backup    = $root . 'robots.txt.bak';
    if (file_exists($physical) && wp_is_writable($physical)) {
        // Save the old content into plugin options so the user can review it.
        $old_content = file_get_contents($physical); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        update_option('cs_seo_robots_bak', $old_content);
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
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
    $cached = get_option('cs_seo_loaded_version', '');
    if ($cached !== CloudScale_SEO_AI_Optimizer::VERSION) {
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
        update_option('cs_seo_loaded_version', CloudScale_SEO_AI_Optimizer::VERSION);
    }
});

new CloudScale_SEO_AI_Optimizer();
