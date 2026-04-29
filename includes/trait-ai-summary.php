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
     * Generates a 3-field AI summary for a post: what it is, why it matters, key takeaway.
     *
     * @since 4.0.0
     * @param int $post_id The post ID to summarise.
     * @return array Associative array with keys 'what', 'why', 'takeaway'.
     * @throws \RuntimeException If the post is not found, no API key is configured, or the AI response is invalid.
     */
    private function call_ai_generate_summary(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException("Post {$post_id} not found"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content = Cs_Seo_Utils::text_from_html((string) $post->post_content);
        $content = mb_substr($content, 0, 6000);

        $audience = trim( (string) ( $this->opts['audience'] ?? '' ) );
        $tone     = trim( (string) ( $this->opts['writing_tone'] ?? '' ) );
        $context  = '';
        if ( $audience ) $context .= "\nTarget audience: {$audience}";
        if ( $tone )     $context .= "\nWriting tone: {$tone}";

        $system = 'You are an SEO content strategist. Given an article title and content, write a 3-part SEO-optimised summary.' . "\n\n"
            . 'Critical SEO rules for every field:' . "\n"
            . '- Front-load the primary keyword — place it in the first 5 words of each field where natural.' . "\n"
            . '- Include 2–3 secondary keywords naturally across all three fields combined.' . "\n"
            . '- Write for search intent: answer "what is X", "how to X", or "best X" directly.' . "\n"
            . '- Use short, scannable sentences. Active voice. Power words (proven, essential, complete, step-by-step).' . "\n"
            . '- No filler phrases: "In this article", "This post", "I will show you".' . "\n"
            . '- Do not start any field with the article title.' . "\n\n"
            . 'Field rules:' . "\n"
            . '- "what": 1–2 sentences. State exactly what the reader will learn, with primary keyword early. Be specific.' . "\n"
            . '- "why": 1–2 sentences. Explain the practical, real-world benefit. Include a secondary keyword.' . "\n"
            . '- "takeaway": 1 sentence max. The single most actionable insight — make it keyword-rich and memorable.' . "\n\n"
            . 'Respond ONLY with valid JSON in exactly this format, no other text:' . "\n"
            . '{"what": "...", "why": "...", "takeaway": "..."}';

        $user_msg = "Article title: \"{$post->post_title}\"\n{$context}\n\nArticle content:\n{$content}";

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
     * AJAX handler: generates the three-field AI summary for a single post.
     *
     * @since 4.10.47
     * @return void
     */
    public function ajax_summary_generate_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if (!$post_id) wp_send_json_error('Missing post_id');

        $force = !empty($_POST['force']);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        // Skip if already generated (all three fields present) and not forced.
        if (!$force) {
            if (
                !empty(get_post_meta($post_id, self::META_SUM_WHAT, true)) &&
                !empty(get_post_meta($post_id, self::META_SUM_WHY,  true)) &&
                !empty(get_post_meta($post_id, self::META_SUM_KEY,  true))
            ) {
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
     * AJAX handler: returns all posts with their AI summary status for the bulk generator panel.
     *
     * @since 4.10.48
     * @return void
     */
    public function ajax_summary_load(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

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
            // Require all three fields — matches prepend_summary_box() render logic.
            $has_sum = !empty(get_post_meta($id, self::META_SUM_WHAT, true))
                    && !empty(get_post_meta($id, self::META_SUM_WHY,  true))
                    && !empty(get_post_meta($id, self::META_SUM_KEY,  true));
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
     * AJAX handler: batch wrapper for ajax_summary_generate_one(), used by the bulk polling loop.
     *
     * @since 4.10.49
     * @return void
     */
    public function ajax_summary_generate_all(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $force      = !empty($_POST['force']);
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $done_count = $force ? absint($_POST['done_count'] ?? 0) : 0;

        if ($force) {
            // Force Regenerate All: iterate through every published post by offset so each
            // call processes the next one without re-processing the same post.
            $ids = get_posts([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'offset'         => $done_count,
                'fields'         => 'ids',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            if (empty($ids)) {
                wp_send_json_success(['done' => true, 'remaining' => 0]);
            }
            $post_id   = (int) $ids[0];
            $total     = (int) wp_count_posts('post')->publish;
            $remaining = max(0, $total - $done_count - 1);
        } else {
            // Generate Missing: scan all posts and find the first where any of the three
            // summary fields is absent or empty. This matches the has_sum logic in
            // ajax_summary_load() and correctly handles empty-string meta values that
            // 'NOT EXISTS' queries would silently skip.
            $all_ids = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'fields'              => 'ids',
                'orderby'             => 'date',
                'order'               => 'DESC',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ]);
            if (empty($all_ids)) {
                wp_send_json_success(['done' => true, 'remaining' => 0]);
            }
            // Prime the meta cache so each get_post_meta() below is served from cache.
            update_meta_cache('post', $all_ids);
            $post_id   = null;
            $remaining = 0;
            foreach ($all_ids as $id) {
                $has_sum = !empty(get_post_meta($id, self::META_SUM_WHAT, true))
                        && !empty(get_post_meta($id, self::META_SUM_WHY,  true))
                        && !empty(get_post_meta($id, self::META_SUM_KEY,  true));
                if (!$has_sum) {
                    if ($post_id === null) {
                        $post_id = (int) $id;
                    }
                    $remaining++;
                }
            }
            if ($post_id === null) {
                wp_send_json_success(['done' => true, 'remaining' => 0]);
            }
            $remaining--; // subtract the one we're about to process
        }

        try {
            $summary = $this->call_ai_generate_summary($post_id);
            update_post_meta($post_id, self::META_SUM_WHAT, $summary['what']);
            update_post_meta($post_id, self::META_SUM_WHY,  $summary['why']);
            update_post_meta($post_id, self::META_SUM_KEY,  $summary['takeaway']);

            wp_send_json_success([
                'post_id'   => $post_id,
                'title'     => get_the_title($post_id),
                'what'      => $summary['what'],
                'why'       => $summary['why'],
                'takeaway'  => $summary['takeaway'],
                'done'      => $remaining === 0,
                'remaining' => max(0, $remaining),
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
