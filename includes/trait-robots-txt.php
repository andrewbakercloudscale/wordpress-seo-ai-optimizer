<?php
/**
 * Robots.txt management — virtual robots.txt served via WordPress rewrites.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Robots_Txt {
    /**
     * AJAX handler: returns the current robots.txt content from file or dynamic filter.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_fetch_robots(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
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

    /**
     * AJAX handler: renames a physical robots.txt file to robots.txt.bak in the WordPress root.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_rename_robots(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Forbidden', 403);
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

    /**
     * Filters the WordPress robots.txt output to add custom rules, AI bot blocking, and sitemap directive.
     *
     * @since 4.0.0
     * @param string $output  The current robots.txt content.
     * @param bool   $public  Whether the site is set to allow search indexing.
     * @return string Modified robots.txt content.
     */
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
