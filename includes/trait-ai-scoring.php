<?php
/**
 * AI SEO scoring — rates post content from 0-100 via AI and stores per-post.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.12.2
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Scoring {
    // =========================================================================
    // SEO Scoring
    // =========================================================================

    /**
     * Scores a single post's SEO effectiveness using a lightweight AI call.
     *
     * Sends the post title, meta description, and a content excerpt to the configured
     * AI provider. Does not modify any post data.
     *
     * @since 4.12.2
     * @param int $post_id The post ID to score.
     * @return array{seo_score: int, seo_notes: string}
     * @throws \RuntimeException If the post is not found or no API key is configured.
     */
    private function call_ai_score_post(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException("Post {$post_id} not found"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-6');

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content   = mb_substr(CloudScale_SEO_AI_Optimizer_Utils::text_from_html((string) $post->post_content), 0, 3000);
        $desc      = trim((string) get_post_meta($post_id, self::META_DESC, true));
        $custom    = trim((string) get_post_meta($post_id, self::META_TITLE, true));
        $title     = $custom !== '' ? $custom : get_the_title($post_id);
        $title_len = mb_strlen($title);

        $system = "You are an SEO expert. Rate an article's search engine optimisation from 0-100 (integer).\n"
            . "Score based on: title keyword clarity and length (ideal 50-60 chars), "
            . "meta description quality and length (ideal 140-160 chars), "
            . "content depth and specificity, clear search intent alignment, and overall quality.\n"
            . "Respond ONLY with valid JSON, no markdown fences:\n"
            . "{\"seo_score\": 75, \"seo_notes\": \"One sentence naming the biggest strength or weakness.\"}";

        $user_msg = "Title ({$title_len} chars): \"{$title}\"\n"
            . ($desc ? "Meta description (" . mb_strlen($desc) . " chars): \"{$desc}\"\n" : "Meta description: none\n")
            . "Content:\n{$content}";

        $raw  = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 120);
        $raw  = trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($raw))));
        $json = json_decode($raw, true);

        $raw_score = isset($json['seo_score']) ? (int) $json['seo_score'] : 0;
        return [
            'seo_score' => $raw_score > 0 ? min(100, $raw_score) : null,
            'seo_notes' => sanitize_text_field((string)($json['seo_notes'] ?? '')),
        ];
    }

    /**
     * AJAX handler: runs an AI SEO score for a single post and stores the result.
     *
     * @since 4.12.2
     * @return void
     */
    public function ajax_score_one(): void {
        $this->ajax_check();
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in ajax_check()
        if (!$post_id) wp_send_json_error('Missing post_id');

        try {
            $result = $this->call_ai_score_post($post_id);
            if ($result['seo_score'] !== null) {
                update_post_meta($post_id, self::META_SEO_SCORE, $result['seo_score']);
                update_post_meta($post_id, self::META_SEO_NOTES, $result['seo_notes']);
            }
            wp_send_json_success([
                'post_id'   => $post_id,
                'seo_score' => $result['seo_score'],
                'seo_notes' => $result['seo_notes'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
