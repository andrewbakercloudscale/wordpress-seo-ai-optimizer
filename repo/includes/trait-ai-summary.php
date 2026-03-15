<?php
/**
 * AI Summary Box generation — What/Why/Takeaway fields per post.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Summary {
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

        $system = 'You are a technical writing assistant. Given an article title and content, write a concise 3-part summary.' . "\n\n"
            . 'Rules:' . "\n"
            . '- "what": 1-2 sentences. What is this article about? Be specific and concrete.' . "\n"
            . '- "why": 1-2 sentences. Why does this matter to the reader? Focus on practical impact.' . "\n"
            . '- "takeaway": 1 sentence. The single most important thing to remember.' . "\n"
            . '- Plain language. No jargon introductions like "In this article" or "This post".' . "\n"
            . '- Do not start any field with the article title.' . "\n"
            . '- Respond ONLY with valid JSON in exactly this format, no other text:' . "\n"
            . '{"what": "...", "why": "...", "takeaway": "..."}';

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
    /**
     * AJAX handler: generates the three-field AI summary for a single post.
     *
     * @since 4.10.47
     * @return void
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
     * AJAX: count posts with/without AI summaries for the bulk panel.
     */
    /**
     * AJAX handler: returns all posts with their AI summary status for the bulk generator panel.
     *
     * @since 4.10.48
     * @return void
     */
    public function ajax_summary_load(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Forbidden', 403);

        $total = (int) wp_count_posts('post')->publish;

        // Fetch all published post IDs — fields=ids avoids loading post_content/excerpt objects.
        $all_ids = get_posts([
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        // Bulk-prime the post object cache and meta cache in 2 queries instead of N×3.
        _prime_post_caches($all_ids, false, false);
        update_meta_cache('post', $all_ids);

        $posts = [];
        $has   = 0;
        foreach ($all_ids as $id) {
            $has_sum = !empty(get_post_meta($id, self::META_SUM_WHAT, true));
            if ($has_sum) $has++;
            $posts[] = [
                'id'       => $id,
                'title'    => get_the_title($id),
                'has_sum'  => $has_sum,
                'edit_link'=> get_edit_post_link($id),
            ];
        }

        wp_send_json_success([
            'total'   => $total,
            'has'     => $has,
            'missing' => max(0, $total - $has),
            'posts'   => $posts,
        ]);
    }

    /**
     * AJAX: generate summaries for all posts missing them (batch).
     * Processes one post per call — JS loops until done (same pattern as ALT batch).
     */
    /**
     * AJAX handler: batch wrapper for ajax_summary_generate_one(), used by the bulk polling loop.
     *
     * @since 4.10.49
     * @return void
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

            // Count remaining after this one — use found_posts to avoid loading IDs.
            $remaining_args                    = $args;
            $remaining_args['posts_per_page']  = 1;
            $remaining_args['no_found_rows']   = false;
            $remaining_q = new \WP_Query( $remaining_args );
            $remaining   = (int) $remaining_q->found_posts;

            wp_send_json_success([
                'post_id'   => $post_id,
                'title'     => get_the_title($post_id),
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
}
