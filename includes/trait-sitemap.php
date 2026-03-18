<?php
/**
 * XML sitemap generation — serves paginated sitemap index and sub-sitemaps.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Sitemap {
    /**
     * Registers sitemap rewrite rules and hooks the render callback when the sitemap is enabled.
     *
     * @since 4.10.3
     * @return void
     */
    public function maybe_register_sitemap(): void {
        if (!(int) $this->opts['enable_sitemap']) return;
        // /sitemap.xml          → sitemap index listing all child sitemaps
        // /sitemap-1.xml etc.   → child sitemaps with up to 200 URLs each
        add_rewrite_rule('^sitemap\.xml$',       'index.php?cs_seo_sitemap=index', 'top');
        add_rewrite_rule('^sitemap-(\d+)\.xml$', 'index.php?cs_seo_sitemap=page&cs_seo_sitemap_pg=$matches[1]', 'top');
        add_rewrite_rule('^sitemap\.txt$',       'index.php?cs_seo_sitemap_txt=1', 'top');
        add_rewrite_tag('%cs_seo_sitemap%',    '(index|page)');
        add_rewrite_tag('%cs_seo_sitemap_pg%', '\d+');
        add_rewrite_tag('%cs_seo_sitemap_txt%', '1');
        add_action('template_redirect', [$this, 'maybe_render_sitemap']);
        add_action('template_redirect', [$this, 'maybe_render_sitemap_txt']);
    }

    /**
     * Outputs the sitemap XML response when a sitemap URL is requested.
     *
     * @since 4.10.3
     * @return void
     */
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

    /**
     * Outputs a plain-text sitemap (one URL per line) at /sitemap.txt.
     *
     * @since 4.19.10
     * @return void
     */
    public function maybe_render_sitemap_txt(): void {
        if (!get_query_var('cs_seo_sitemap_txt')) return;
        status_header(200);
        // phpcs:ignore WordPress.PHP.DiscouragedFunctions.header_header -- text/plain has no WordPress wrapper; required for sitemap.txt plain-text response.
        header('Content-Type: text/plain; charset=utf-8');
        // phpcs:ignore WordPress.PHP.DiscouragedFunctions.header_header -- Public caching directive; nocache_headers() would send the opposite instruction.
        header('Cache-Control: public, max-age=3600');
        $urls = $this->get_all_sitemap_urls();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text output; HTML escaping would corrupt URLs.
        echo implode("\n", array_column($urls, 'loc')) . "\n";
        exit;
    }

    /**
     * Returns the complete ordered list of URLs for the sitemap, using a transient cache.
     *
     * Includes the homepage, all published posts/pages of the configured post types,
     * and optionally public taxonomy term URLs. Results are cached for one hour.
     *
     * @since 4.10.3
     * @return array List of URL records, each with keys: loc, lastmod, type, title.
     */
    private function get_all_sitemap_urls(): array {
        $cached = get_transient(self::SITEMAP_URLS_CACHE);
        if ($cached !== false) return $cached;

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
                $sitemap_query_args['post__not_in'] = $exclude_ids; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- user-defined sitemap exclusion list; admin-only, runs once on sitemap build
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

        set_transient(self::SITEMAP_URLS_CACHE, $urls, HOUR_IN_SECONDS);
        return $urls;
    }

    /**
     * Builds the sitemap index XML document, listing all child sitemap files.
     *
     * Each child sitemap covers up to SITEMAP_PER_FILE URLs. The number of
     * <sitemap> entries is derived from the total URL count.
     *
     * @since 4.10.3
     * @return string Complete sitemap index XML string.
     */
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

    /**
     * Builds a child sitemap XML document for the given page number.
     *
     * Slices SITEMAP_PER_FILE URL records starting at the correct offset and
     * outputs a standard <urlset> document with <loc> and optional <lastmod> per URL.
     *
     * @since 4.10.3
     * @param int $pg 1-based page index corresponding to sitemap-{pg}.xml.
     * @return string Complete child sitemap XML string.
     */
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
    /**
     * AJAX handler: returns a paginated list of sitemap entries for the admin preview table.
     *
     * @since 4.10.3
     * @return void
     */
    public function ajax_sitemap_preview(): void {
        $this->ajax_check();
        // Preview works regardless of enable_sitemap so you can check before enabling
        $all      = $this->get_all_sitemap_urls();
        $total    = count($all);
        $per_page = self::SITEMAP_PREVIEW_PER;
        $pages    = max(1, (int) ceil($total / $per_page));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via ajax_check()
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

}
