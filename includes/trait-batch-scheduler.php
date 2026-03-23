<?php
/**
 * Scheduled batch — runs AI meta description generation on a WP-Cron schedule.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Batch_Scheduler {
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
     *
     * @since 4.0.0
     * @return void
     */
    public function run_scheduled_batch(): void {
        $ai = $this->get_ai_opts();
        if (!(int) $ai['schedule_enabled']) return;

        $days = (array) $ai['schedule_days'];
        if (empty($days)) return;

        // Check if today (server time) is a scheduled day.
        $today = strtolower(gmdate('D')); // 'mon','tue' etc.
        if (!in_array($today, $days, true)) return;

        $log      = [];
        $done     = 0;
        $errors   = 0;
        $skipped  = 0;
        $start    = time();
        $deadline = $start + 1740; // 29 min — leave 60 s for the history write

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
            if (time() >= $deadline) { $log[] = ['status' => 'timeout', 'title' => 'Pass 1 time limit reached']; break; }
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
            if (time() >= $deadline) { $log[] = ['status' => 'timeout', 'title' => 'Pass 2 time limit reached']; break; }
            $images = $this->collect_images_needing_alt($p->ID);
            if (empty($images)) {
                $alt_skipped_posts++;
                continue; // All images already have ALT text — nothing to do.
            }
            try {
                $saved = $this->batch_generate_alt_for_post($p->ID, $images);
                if ($saved > 0) {
                    $log[] = ['status' => 'alt_ok', 'title' => get_the_title($p->ID), 'count' => $saved];
                }
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
            if (time() >= $deadline) { $log[] = ['status' => 'timeout', 'title' => 'Pass 3 time limit reached']; break; }
            try {
                $summary = $this->call_ai_generate_summary($p->ID);
                update_post_meta($p->ID, self::META_SUM_WHAT, sanitize_text_field($summary['what']));
                update_post_meta($p->ID, self::META_SUM_WHY,  sanitize_text_field($summary['why']));
                update_post_meta($p->ID, self::META_SUM_KEY,  sanitize_text_field($summary['takeaway']));
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
            'elapsed'           => (time() - $start),
            'log'               => array_slice($log, 0, 100), // Keep last 100 entries per run.
        ];
        // Prune entries older than 28 days.
        $cutoff  = gmdate('Y-m-d H:i:s', strtotime('-28 days'));
        $history = array_values(array_filter($history, fn($r) => ($r['date'] ?? '') >= $cutoff));
        update_option('cs_seo_batch_history', $history, false);
    }

    /**
     * Generates ALT text for images that are missing it in a single post.
     * Used by run_scheduled_batch (Pass 2). Missing-only — never overwrites existing ALT.
     * Works with both Anthropic and Gemini via dispatch_ai().
     *
     * @since 4.0.0
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
        $model = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);

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
            // wp_slash() required — see trait-ai-alt-text.php for explanation.
            wp_update_post(['ID' => $post_id, 'post_content' => wp_slash( $new_content )]);
        }

        return $saved;
    }

    /**
     * AJAX handler: returns the batch run history for display in the admin panel.
     *
     * @since 4.10.14
     * @return void
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

}
