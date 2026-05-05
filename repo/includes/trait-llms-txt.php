<?php
/**
 * llms.txt generation — serves an AI-crawler-friendly plain-text site index at /llms.txt.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_LLMS_Txt {
    // =========================================================================
    // llms.txt
    // =========================================================================

    /**
     * Registers the llms.txt rewrite rule and render callback when llms.txt is enabled.
     *
     * @since 4.10.4
     * @return void
     */
    public function maybe_register_llms_txt(): void {
        if (!(int)($this->opts['enable_llms_txt'] ?? 0)) return;
        add_rewrite_rule('^llms\.txt$', 'index.php?cs_seo_llms=1', 'top');
        add_rewrite_tag('%cs_seo_llms%', '1');
        add_action('template_redirect', [$this, 'maybe_render_llms_txt']);
        // Self-heal: if the stored rewrite rules don't yet include the llms.txt rule,
        // flush on shutdown so the next request works without manual Permalinks save.
        $stored = get_option('rewrite_rules', []);
        if (!isset($stored['^llms\\.txt$'])) {
            add_action('shutdown', 'flush_rewrite_rules');
        }
    }

    /**
     * Outputs the llms.txt plain-text response when the rewrite URL is requested.
     *
     * @since 4.10.4
     * @return void
     */
    public function maybe_render_llms_txt(): void {
        if (!get_query_var('cs_seo_llms')) return;
        status_header(200);
        // phpcs:ignore WordPress.PHP.DiscouragedFunctions.header_header -- Content-Type text/plain has no WordPress wrapper; required for llms.txt plain-text response.
        header('Content-Type: text/plain; charset=utf-8');
        // phpcs:ignore WordPress.PHP.DiscouragedFunctions.header_header -- Public caching directive; nocache_headers() would send the opposite instruction.
        header('Cache-Control: public, max-age=3600');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text content; HTML escaping would corrupt the output.
        echo $this->build_llms_txt();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Terminates template_redirect to prevent WordPress loading a theme template for this virtual URL.
        exit;
    }

    /**
     * Builds the llms.txt document content, using a transient cache for performance.
     *
     * @since 4.17.0
     * @return string The complete llms.txt document as a plain-text string.
     */
    private function build_llms_txt(): string {
        $cached = get_transient('cs_seo_llms_txt');
        if ($cached !== false) return $cached;

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
        $post_types = (array) ($this->opts['sitemap_post_types'] ?? ['post', 'page']);
        if (empty($post_types)) $post_types = ['post', 'page'];
        $posts = get_posts([
            'post_type'           => $post_types,
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        // Bulk-prime post objects and meta cache — avoids N+1 on cold transient build.
        _prime_post_caches($posts, false, false);
        update_meta_cache('post', $posts);

        $by_type = [];
        foreach ($posts as $pid) {
            $p    = get_post($pid);
            $type = $p->post_type;
            $desc = trim((string) get_post_meta($pid, self::META_DESC, true));
            $entry = '- [' . get_the_title($pid) . '](' . get_permalink($pid) . ')';
            if ($desc) $entry .= ': ' . $desc;
            $by_type[$type][] = $entry;
        }

        // Section headings per post type.
        $headings = ['post' => 'Blog Posts', 'page' => 'Pages'];
        foreach ($by_type as $type => $entries) {
            if (empty($entries)) continue;
            $label = $headings[$type] ?? ucwords(str_replace(['-', '_'], ' ', $type)) . 's';
            $lines[] = '## ' . $label;
            $lines[] = '';
            foreach ($entries as $entry) $lines[] = $entry;
            $lines[] = '';
        }

        $output = implode("\n", $lines);
        set_transient('cs_seo_llms_txt', $output, HOUR_IN_SECONDS);
        return $output;
    }

    /**
     * AJAX handler: returns the generated llms.txt content for the admin preview panel.
     *
     * @since 4.10.4
     * @return void
     */
    public function ajax_llms_preview(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        wp_send_json_success(['content' => $this->build_llms_txt()]);
    }


}
