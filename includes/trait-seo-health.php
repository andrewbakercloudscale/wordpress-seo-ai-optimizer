<?php
/**
 * SEO health dashboard — aggregates meta coverage, title length, and ALT text stats.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_SEO_Health {
    // =========================================================================
    // SEO Health Cache
    // =========================================================================

    /**
     * Computes an MD5 fingerprint of a post's image content.
     *
     * Combines post_content with all attachment IDs found in that content plus
     * the featured image ID. If the hash changes between runs it means images
     * were added or removed, so the ALT-done flag is stale.
     *
     * @since 4.10.0
     * @param int $post_id Post ID.
     * @return string MD5 hash string, or empty string if the post does not exist.
     */
    public static function compute_alt_content_hash(int $post_id): string {
        $post = get_post($post_id);
        if (!$post) return '';

        // Collect all src URLs from post_content.
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $post->post_content, $matches);
        $srcs = $matches[1] ?? [];
        sort($srcs);

        // Include featured image ID so swapping it invalidates the hash.
        $thumb_id = (int) get_post_thumbnail_id($post_id);

        return md5($post->post_content . implode('|', $srcs) . '|thumb:' . $thumb_id);
    }

    /**
     * Rebuilds the SEO health cache by counting posts with complete SEO, ALT, links, and summaries.
     *
     * Runs five EXISTS-subquery counts against postmeta — no slow meta_query joins.
     * For the Images metric, validates the stored ALT content hash against the
     * current post content before counting, clearing stale flags on the fly.
     *
     * @since 4.11.26
     * @return array Health cache array with keys: total, seo, images, links, summaries, built_at.
     */
    public function rebuild_health_cache(): array {
        global $wpdb;

        // 1. Total published posts and pages.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional; results stored in cs_seo_health_cache option
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_type IN (%s, %s)",
            'publish', 'post', 'page'
        ) );

        // 2. Posts/pages with a non-empty meta description.
        $seo = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional; results stored in cs_seo_health_cache option
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ('post','page')
                   AND EXISTS (
                       SELECT 1 FROM {$wpdb->postmeta} pm
                       WHERE pm.post_id = p.ID
                         AND pm.meta_key = %s
                         AND pm.meta_value != ''
                   )",
                self::META_DESC
            )
        );

        // 3. Posts/pages with related article links generated (rc_status = 'complete').
        $links = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional; results stored in cs_seo_health_cache option
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ('post','page')
                   AND EXISTS (
                       SELECT 1 FROM {$wpdb->postmeta} pm
                       WHERE pm.post_id = p.ID
                         AND pm.meta_key = %s
                         AND pm.meta_value = 'complete'
                   )",
                self::META_RC_STATUS
            )
        );

        // 4. Posts/pages with a complete AI summary box (what field populated).
        $summaries = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional; results stored in cs_seo_health_cache option
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} p
                 WHERE p.post_status = 'publish'
                   AND p.post_type IN ('post','page')
                   AND EXISTS (
                       SELECT 1 FROM {$wpdb->postmeta} pm
                       WHERE pm.post_id = p.ID
                         AND pm.meta_key = %s
                         AND pm.meta_value != ''
                   )",
                self::META_SUM_WHAT
            )
        );

        // 5. Posts/pages where every <img> in post_content has a non-empty alt attribute.
        //    No AI calls — scans post_content directly. Posts with no images count as
        //    fully covered (nothing missing). Processed in batches to avoid loading all
        //    post_content into memory at once on large sites.
        $images     = 0;
        $batch_size = 200;
        $offset     = 0;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional; results stored in cs_seo_health_cache option
            $batch = $wpdb->get_results($wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('post','page')
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));
            foreach ($batch as $p) {
                $content = (string) $p->post_content;
                preg_match_all('/<img[^>]+>/i', $content, $img_tags);
                if (empty($img_tags[0])) {
                    // No images in this post — counts as fully covered.
                    $images++;
                    continue;
                }
                $all_have_alt = true;
                foreach ($img_tags[0] as $tag) {
                    // Check alt attribute exists and is non-empty.
                    if (!preg_match('/alt=["\']([^"\']+)["\']/i', $tag)) {
                        $all_have_alt = false;
                        break;
                    }
                }
                if ($all_have_alt) $images++;
            }
            $offset += $batch_size;
        } while (count($batch) === $batch_size);

        $cache = [
            'total'     => $total,
            'seo'       => $seo,
            'images'    => $images,
            'links'     => $links,
            'summaries' => $summaries,
            'built_at'  => time(),
        ];

        update_option(self::OPT_HEALTH_CACHE, $cache, false);
        return $cache;
    }

    /**
     * AJAX handler: rebuild health cache and return result as JSON.
     * Nonce: cs_seo_nonce.
     */
    /**
     * AJAX handler: rebuilds the SEO health cache and returns the updated stats.
     *
     * @since 4.11.26
     * @return void
     */
    public function ajax_rebuild_health_cache(): void {
        $this->ajax_check();
        $cache = $this->rebuild_health_cache();
        wp_send_json_success($cache);
    }
}
