<?php
/**
 * AI meta description, title, and alt-text generation — bulk and single-post AJAX handlers.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Meta_Writer {
    /**
     * Calls the configured AI provider to generate SEO title, description, and focus keyword for a post.
     *
     * @since 4.0.0
     * @param int $post_id The post ID to generate meta for.
     * @return array Associative array with keys 'description', 'title', 'title_was', 'title_chars', 'title_status', 'alts_saved', 'seo_score', 'seo_notes'.
     * @throws \RuntimeException If the post is not found or no API key is configured.
     */
    private function call_ai_generate_all(int $post_id, string $seo_feedback = '', int $old_score = 0): array {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        // ── Build context ─────────────────────────────────────────────────────
        $content = Cs_Seo_Utils::text_from_html((string) $post->post_content);
        $content = mb_substr($content, 0, 6000);

        $site_context_parts = [];
        $site_name = trim((string) $this->opts['site_name']);
        $person    = trim((string) $this->opts['person_name']);
        $job_title = trim((string) $this->opts['person_job_title']);
        $home_desc = trim((string) $this->opts['home_desc']);
        $def_desc  = trim((string) $this->opts['default_desc']);
        $audience  = trim((string) ($this->opts['target_audience'] ?? ''));
        $tone      = trim((string) ($this->opts['writing_tone'] ?? ''));

        if ($site_name) $site_context_parts[] = "Site name: {$site_name}";
        if ($person)    $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic)    $site_context_parts[] = "Site description: {$topic}";
        if ($audience) $site_context_parts[] = "Target audience: {$audience}";
        if ($tone)     $site_context_parts[] = "Writing tone: {$tone}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT (use this to match the site's voice, audience, and niche):\n" . implode("\n", $site_context_parts)
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
        $json_shape = '{"description": "...", "title": "...", "seo_score": 75, "seo_notes": "One sentence."}';
        $title_instruction = '';
        if ($needs_title) {
            $title_instruction = "\n\nSEO TITLE: The current title is {$title_direction} at {$title_len} chars: \"{$current_title}\". "
                . "Rewrite it so it is between 50 and 60 characters. Keep the core topic and keywords. "
                . "Do not add quotes or punctuation at start/end.";
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
            $json_shape  = '{"description": "...", "title": "...", "alts": ["alt for image 1", "alt for image 2"], "seo_score": 75, "seo_notes": "One sentence."}';
            $image_instruction = "\n\nALT TEXT: Write concise ALT text (5–15 words, no 'Image of' prefix) for each image listed below.";
        } else {
            $image_list        = '';
            $image_instruction = '';
        }

        $score_context = $old_score > 0 ? " The previous SEO score was {$old_score}%." : '';
        $feedback_instruction = $seo_feedback
            ? "\n\nSEO IMPROVEMENT GUIDANCE:{$score_context} A previous analysis flagged: \"{$seo_feedback}\". Address this directly when writing the meta description and title. Your seo_score should reflect the improvements you are making."
            : '';

        $is_front_page = ( 'page' === get_option('show_on_front') && (int) get_option('page_on_front') === $post_id );
        $score_criteria = $is_front_page
            ? "Rate this homepage's SEO from 0-100 (integer). Criteria: meta description clarity and brand messaging, title keyword alignment, value proposition strength, and overall site identity clarity. Do NOT penalise for absence of article body content — this is a homepage, not an article."
            : "Rate this article's search engine optimisation from 0-100 (integer). Consider: title keyword clarity and length, meta description quality, content depth and specificity, clear search intent alignment, and overall article quality.";

        $system = $prompt . $site_context
            . "\n\nDESCRIPTION: The meta description MUST be between {$min} and {$max} characters including spaces. Count carefully."
            . $title_instruction
            . $image_instruction
            . $feedback_instruction
            . "\n\nSEO SCORE: {$score_criteria} Set seo_notes to one concise sentence naming the single biggest strength or weakness."
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

        // Treat missing or zero as null — 0 means the AI omitted the field, not a real score.
        $raw_score = isset($json['seo_score']) ? (int) $json['seo_score'] : 0;
        $seo_score = $raw_score > 0 ? min(100, $raw_score) : null;
        $seo_notes = sanitize_text_field((string)($json['seo_notes'] ?? ''));

        return [
            'description'  => $desc,
            'title'        => $title_fixed ?? $new_title,
            'title_was'    => $title_was,
            'title_chars'  => $needs_title ? $new_title_len : $title_len,
            'title_status' => $title_status,
            'alts_saved'   => $alts_saved,
            'seo_score'    => $seo_score,
            'seo_notes'    => $seo_notes,
        ];
    }

    /**
     * Calls the configured AI provider to generate a meta description for a single post.
     *
     * Works with both Anthropic and Gemini providers — routes through dispatch_ai().
     * Used by the scheduled batch processor and the fix-description AJAX flow.
     *
     * @since 4.0.0
     * @param int $post_id The post ID to generate a description for.
     * @return string The AI-generated meta description.
     * @throws \RuntimeException If the post is not found or no API key is configured.
     */
    private function call_ai_generate_desc(int $post_id): string {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content  = Cs_Seo_Utils::text_from_html((string) $post->post_content);
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
        $audience   = trim((string) ($this->opts['target_audience'] ?? ''));
        $tone       = trim((string) ($this->opts['writing_tone'] ?? ''));

        if ($site_name)  $site_context_parts[] = "Site name: {$site_name}";
        if ($person)     $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic)      $site_context_parts[] = "Site description: {$topic}";
        if ($audience)   $site_context_parts[] = "Target audience: {$audience}";
        if ($tone)       $site_context_parts[] = "Writing tone: {$tone}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT (use this to match the site's voice, audience, and niche):\n" . implode("\n", $site_context_parts)
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
     * Collects all images in a post that have empty or missing ALT attributes.
     *
     * @since 4.0.0
     * @param int $post_id The post ID to scan.
     * @return array Array of associative arrays with keys 'img_tag', 'src', 'filename', 'attach_id'.
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
     * Saves ALT texts returned by the combined AI API call into post content and attachment meta.
     *
     * @since 4.0.0
     * @param int   $post_id The post ID to update.
     * @param array $images  Array of image data from collect_images_needing_alt().
     * @param array $alts    Array of AI-generated ALT text strings, indexed to match $images.
     * @return void
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
            // wp_slash() required — see trait-ai-alt-text.php for explanation.
            wp_update_post(['ID' => $post_id, 'post_content' => wp_slash( $new_content )]);
        }
    }

    /**
     * AJAX handler: generates meta description, fixes title, writes ALT text, and scores a single post.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_generate_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id      = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $seo_feedback = sanitize_text_field( wp_unslash( $_POST['seo_notes'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $old_score    = absint( wp_unslash( $_POST['seo_score'] ?? 0 ) );               // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$post_id) wp_send_json_error('Missing post_id');

        try {
            $result = $this->call_ai_generate_all($post_id, $seo_feedback, $old_score);
            update_post_meta($post_id, self::META_DESC, sanitize_textarea_field($result['description']));
            if ($result['seo_score'] !== null) {
                update_post_meta($post_id, self::META_SEO_SCORE, $result['seo_score']);
                update_post_meta($post_id, self::META_SEO_NOTES, $result['seo_notes']);
            }
            wp_send_json_success([
                'post_id'      => $post_id,
                'description'  => sanitize_text_field( $result['description'] ),
                'chars'        => mb_strlen($result['description']),
                'alts_saved'   => $result['alts_saved'],
                'title'        => sanitize_text_field( (string) $result['title'] ),
                'title_was'    => $result['title_was'],
                'title_chars'  => $result['title_chars'],
                'title_status' => $result['title_status'],
                'seo_score'    => $result['seo_score'],
                'seo_notes'    => $result['seo_notes'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Calls the AI provider to rewrite an existing description that is outside the target length range.
     *
     * @since 4.0.0
     * @param int    $post_id       The post ID to fix the description for.
     * @param string $existing_desc The current description that needs rewriting.
     * @return string The AI-generated replacement meta description.
     * @throws \RuntimeException If the post is not found or no API key is configured.
     */
    private function call_ai_fix_desc(int $post_id, string $existing_desc): string {
        $post = get_post($post_id);
        if (!$post) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);
        $prompt   = trim((string) $this->ai_opts['prompt']) ?: self::default_prompt();
        $min      = max(100, (int) $this->ai_opts['min_chars']);
        $max      = min(200, (int) $this->ai_opts['max_chars']);

        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $len       = mb_strlen($existing_desc);
        $direction = $len > $max ? 'too long' : 'too short';
        $content   = Cs_Seo_Utils::text_from_html((string) $post->post_content);
        $content   = mb_substr($content, 0, 6000);

        $site_context_parts = [];
        $site_name = trim((string) $this->opts['site_name']);
        $person    = trim((string) $this->opts['person_name']);
        $job_title = trim((string) $this->opts['person_job_title']);
        $home_desc = trim((string) $this->opts['home_desc']);
        $def_desc  = trim((string) $this->opts['default_desc']);
        $audience  = trim((string) ($this->opts['target_audience'] ?? ''));
        $tone      = trim((string) ($this->opts['writing_tone'] ?? ''));

        if ($site_name) $site_context_parts[] = "Site name: {$site_name}";
        if ($person)    $site_context_parts[] = "Author: {$person}" . ($job_title ? ", {$job_title}" : '');
        $topic = $home_desc ?: $def_desc;
        if ($topic)    $site_context_parts[] = "Site description: {$topic}";
        if ($audience) $site_context_parts[] = "Target audience: {$audience}";
        if ($tone)     $site_context_parts[] = "Writing tone: {$tone}";

        $site_context = $site_context_parts
            ? "\n\nSITE CONTEXT (use this to match the site's voice, audience, and niche):\n" . implode("\n", $site_context_parts)
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

    /**
     * AJAX handler: saves a manually entered meta description for a post.
     *
     * @since 4.15.6
     * @return void
     */
    public function ajax_save_desc(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $desc    = sanitize_textarea_field( wp_unslash( $_POST['desc'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        if ( ! $post_id ) wp_send_json_error( 'Missing post_id' );
        if ( ! get_post( $post_id ) ) wp_send_json_error( 'Post not found' );
        update_post_meta( $post_id, self::META_DESC, $desc );
        wp_send_json_success( [
            'post_id' => $post_id,
            'desc'    => $desc,
            'chars'   => mb_strlen( $desc ),
        ] );
    }

    /**
     * AJAX handler: rewrites an existing meta description that is outside the configured character range.
     *
     * @since 4.2.2
     * @return void
     */
    public function ajax_fix_desc(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
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
     * AJAX handler: rewrites an existing SEO title that is outside the 50–60 character range.
     *
     * @since 4.10.24
     * @return void
     */
    public function ajax_fix_title(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
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
        $model    = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);
        if (!$key) throw new \RuntimeException($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $len       = mb_strlen($current_title);
        $direction = $len > 60 ? 'too long' : 'too short';
        $content   = Cs_Seo_Utils::text_from_html((string) $post->post_content);
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
    /**
     * AJAX handler: bulk meta description generation polling endpoint for the admin panel.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_generate_all(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id   = (int) sanitize_key( wp_unslash( $_POST['post_id'] ?? 0 ) );   // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
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
            $seo_feedback_raw = sanitize_text_field( wp_unslash( $_POST['seo_notes'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $seo_feedback     = $seo_feedback_raw !== ''
                ? $seo_feedback_raw
                : sanitize_text_field( (string) get_post_meta( $post_id, self::META_SEO_NOTES, true ) );
            $old_score_raw = absint( wp_unslash( $_POST['seo_score'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $old_score     = $old_score_raw > 0
                ? $old_score_raw
                : (int) get_post_meta( $post_id, self::META_SEO_SCORE, true );
            $result = $this->call_ai_generate_all($post_id, $seo_feedback, $old_score);
            update_post_meta($post_id, self::META_DESC, sanitize_textarea_field($result['description']));
            if ($result['seo_score'] !== null) {
                update_post_meta($post_id, self::META_SEO_SCORE, $result['seo_score']);
                update_post_meta($post_id, self::META_SEO_NOTES, $result['seo_notes']);
            }
            wp_send_json_success([
                'post_id'      => $post_id,
                'status'       => 'generated',
                'description'  => sanitize_text_field( $result['description'] ),
                'chars'        => mb_strlen($result['description']),
                'message'      => 'Generated: ' . mb_strlen($result['description']) . ' chars',
                'title'        => sanitize_text_field( (string) $result['title'] ),
                'title_was'    => sanitize_text_field( (string) $result['title_was'] ),
                'title_chars'  => $result['title_chars'],
                'title_status' => $result['title_status'],
                'alts_saved'   => $result['alts_saved'],
                'seo_score'    => $result['seo_score'],
                'seo_notes'    => $result['seo_notes'],
            ]);
        } catch (\Throwable $e) {
            wp_send_json_error([
                'post_id' => $post_id,
                'message' => $e->getMessage(),
            ]);
        }
    }
    /**
     * AJAX handler: regenerates the static admin JS/CSS assets and refreshes OPcache.
     *
     * @since 4.10.18
     * @return void
     */
    public function ajax_regen_static(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
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
            'image_url'   => esc_url_raw( $image_url ),
            'source'      => $thumb_id ? 'featured_image' : ($image_url ? 'site_default' : 'none'),
        ]);
    }

    /**
     * AJAX handler: returns all published posts with their description status and SEO score.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_get_posts(): void {
        global $wpdb;
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $page = max(1, (int) sanitize_key( wp_unslash( $_POST['page'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
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
                $hp_score_raw = get_post_meta($front_page_id, self::META_SEO_SCORE, true);
                $hp_r_raw     = (string) get_post_meta($front_page_id, self::META_READABILITY, true);
                $hp_r_data    = $hp_r_raw ? json_decode($hp_r_raw, true) : null;
                $hp_raw_title = html_entity_decode((string) get_the_title($front_page_id), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $homepage = [
                    'id'                => $front_page_id,
                    'title'             => $hp_raw_title,
                    'effective_title'   => $custom_hp_title !== '' ? $custom_hp_title : $hp_raw_title,
                    'title_chars'       => mb_strlen($custom_hp_title !== '' ? $custom_hp_title : $hp_raw_title),
                    'type'              => 'homepage',
                    'date'              => get_the_date('Y-m-d', $front_page_id),
                    'has_desc'          => $desc !== '',
                    'desc'              => $desc,
                    'desc_chars'        => mb_strlen($desc),
                    'missing_alt'       => $missing_alt,
                    'is_homepage'       => true,
                    'seo_score'         => $hp_score_raw !== '' ? (int) $hp_score_raw : null,
                    'seo_notes'         => (string) get_post_meta($front_page_id, self::META_SEO_NOTES, true),
                    'readability_score' => isset($hp_r_data['score']) ? (int) $hp_r_data['score'] : null,
                    'readability_data'  => $hp_r_data ?: null,
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
            'post__not_in'        => $front_page_id ? [$front_page_id] : [], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- excludes at most one ID (the static front page); low-volume admin-only query
            // Exclude posts marked as noindex — no value in generating SEO descriptions for them.
            'meta_query'          => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                [
                    'relation' => 'OR',
                    ['key' => self::META_NOINDEX, 'compare' => 'NOT EXISTS'],
                    ['key' => self::META_NOINDEX, 'value' => '1', 'compare' => '!='],
                ],
            ],
        ]);

        // Bulk-prime the meta cache so get_post_meta() hits cache, not DB, for each post.
        update_meta_cache('post', array_column($q->posts, 'ID'));

        $items = [];
        foreach ($q->posts as $p) {
            $desc = trim((string) get_post_meta($p->ID, self::META_DESC, true));
            // Effective title = custom SEO title if set, otherwise post title.
            $custom_title    = trim((string) get_post_meta($p->ID, self::META_TITLE, true));
            $raw_title       = html_entity_decode((string) get_the_title($p->ID), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $effective_title = $custom_title !== '' ? $custom_title : $raw_title;
            // Count images with empty ALT in post content.
            preg_match_all('/<img[^>]+>/i', (string) $p->post_content, $img_tags);
            $missing_alt = 0;
            foreach ($img_tags[0] as $img_tag) {
                if (preg_match('/alt=["\']([^"\']*)["\']/i', $img_tag, $m) && $m[1] === '') $missing_alt++;
            }
            $score_raw = get_post_meta($p->ID, self::META_SEO_SCORE, true);
            $r_raw     = (string) get_post_meta($p->ID, self::META_READABILITY, true);
            $r_data    = $r_raw ? json_decode($r_raw, true) : null;
            $items[] = [
                'id'                => $p->ID,
                'title'             => $raw_title,
                'effective_title'   => $effective_title,
                'title_chars'       => mb_strlen($effective_title),
                'type'              => $p->post_type,
                'date'              => get_the_date('Y-m-d', $p->ID),
                'has_desc'          => $desc !== '',
                'desc'              => $desc,
                'desc_chars'        => mb_strlen($desc),
                'missing_alt'       => $missing_alt,
                'edit_link'         => get_edit_post_link($p->ID, ''),
                'permalink'         => get_permalink($p->ID),
                'seo_score'         => $score_raw !== '' ? (int) $score_raw : null,
                'seo_notes'         => (string) get_post_meta($p->ID, self::META_SEO_NOTES, true),
                'readability_score' => isset($r_data['score']) ? (int) $r_data['score'] : null,
                'readability_data'  => $r_data ?: null,
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
                     AND pm.meta_value != ''
                     AND p.ID NOT IN (
                         SELECT post_id FROM {$wpdb->postmeta}
                         WHERE meta_key = %s AND meta_value = '1'
                     )",
                    self::META_DESC,
                    self::META_NOINDEX
                )
            ),
        ]);
    }

    /**
     * Convert a raw cURL error string into a human-readable explanation.
     *
     * @since 4.19.102
     * @param string $raw_error  The cURL error message returned by WP_Error.
     * @param string $host       The hostname being contacted (used in the message).
     * @return string            A user-friendly error string.
     */
    private static function friendly_curl_error( string $raw_error, string $host ): string {
        if ( preg_match( '/cURL error (\d+)/i', $raw_error, $m ) ) {
            switch ( (int) $m[1] ) {
                case 6:
                    return "DNS lookup failed — your server cannot resolve {$host}. Check your hosting DNS settings or contact your host.";
                case 7:
                    return "Connection refused by {$host}. A firewall or security rule on your server is likely blocking outbound connections on port 443. Ask your host to whitelist {$host}:443.";
                case 28:
                    return "Connection timed out — your server could not reach {$host} within 15 seconds. "
                        . "This is almost always caused by a firewall or hosting restriction blocking outbound HTTPS traffic. "
                        . "Contact your host and ask them to allow outbound connections to {$host}:443. "
                        . "This is a server networking issue, not a problem with your API key.";
                case 35:
                case 51:
                case 58:
                case 60:
                    return "SSL/TLS error connecting to {$host}. Your server may have outdated CA certificates or a proxy is intercepting HTTPS traffic. Contact your hosting provider.";
                default:
                    return "Connection failed (cURL error {$m[1]}) — your server cannot reach {$host}. Check firewall and proxy settings. Raw error: {$raw_error}";
            }
        }
        return 'Connection failed: ' . $raw_error;
    }

    /**
     * AJAX handler: tests the configured AI API key by sending a minimal request.
     *
     * @since 4.0.0
     * @return void
     */
    public function ajax_test_key(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $provider = sanitize_key(wp_unslash($_POST['provider'] ?? $this->ai_opts['ai_provider'] ?? 'anthropic'));
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        $key = sanitize_text_field(wp_unslash($_POST['live_key'] ?? ''));
        if (!$key) {
            $saved_key = $provider === 'gemini' ? ($this->ai_opts['gemini_key'] ?? '') : $this->ai_opts['anthropic_key'];
            $key = $saved_key;
        }
        if (!$key) wp_send_json_error('No API key entered');

        // ── Firewall / connectivity pre-check ─────────────────────────────────
        $api_host    = $provider === 'gemini' ? 'generativelanguage.googleapis.com' : 'api.anthropic.com';
        $resolved_ip = gethostbyname($api_host);
        if ($resolved_ip === $api_host) {
            // gethostbyname returns the input unchanged when DNS fails
            wp_send_json_error(
                "Firewall check — DNS lookup failed: your server cannot resolve {$api_host}. " .
                'This indicates a DNS misconfiguration on your hosting server. Contact your host.'
            );
        }
        // Plain TCP connect on port 443 — tests reachability without needing a valid key
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fsockopen
        $sock = @fsockopen($api_host, 443, $errno, $errstr, 5);
        if (false === $sock) {
            wp_send_json_error(
                "Firewall check failed — your server resolved {$api_host} to {$resolved_ip} " .
                "but cannot open a TCP connection to port 443 (error {$errno}: {$errstr}). " .
                'A firewall is blocking outbound HTTPS traffic. ' .
                "Ask your host to allow outbound connections to {$api_host}:443."
            );
        }
        fclose($sock);

        if ($provider === 'gemini') {
            // Gemini: use generateContent endpoint with a minimal prompt
            $model = 'gemini-2.0-flash';
            $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
            $response = wp_remote_post($url, [
                'timeout' => 15,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode([
                    'contents' => [['parts' => [['text' => 'Reply with: OK']]]],
                    'generationConfig' => ['maxOutputTokens' => 10],
                ]),
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error( self::friendly_curl_error( $response->get_error_message(), 'generativelanguage.googleapis.com' ) );
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if ($code === 200) {
                wp_send_json_success('API key valid and credits confirmed. Provider: Google Gemini');
            } elseif ($code === 401 || $code === 403) {
                wp_send_json_error('Invalid API key — check that you copied it correctly from aistudio.google.com.');
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
                    'model'      => $this->resolve_model(trim((string) $this->ai_opts['model']), 'anthropic'),
                    'max_tokens' => 10,
                    'messages'   => [['role' => 'user', 'content' => 'Reply with: OK']],
                ]),
            ]);

            if (is_wp_error($response)) {
                wp_send_json_error( self::friendly_curl_error( $response->get_error_message(), 'api.anthropic.com' ) );
            }
            $code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);
            $err_type = $body['error']['type'] ?? '';
            $err_msg  = $body['error']['message'] ?? '';
            if ($code === 200) {
                $model = $body['model'] ?? 'unknown';
                wp_send_json_success( 'API key valid and credits confirmed. Model: ' . $model );
            } elseif ($code === 401) {
                wp_send_json_error('Invalid API key — check that you copied it correctly from console.anthropic.com/settings/keys.');
            } elseif ($code === 402 || $err_type === 'billing_error' || false !== stripos($err_msg, 'credit') || false !== stripos($err_msg, 'billing')) {
                wp_send_json_error('Your Anthropic account has insufficient credits. Top up your balance at console.anthropic.com/settings/billing.' . ($err_msg ? ' (' . $err_msg . ')' : ''));
            } elseif ($code === 429 && $err_type === 'rate_limit_error') {
                wp_send_json_error('Rate limit reached — your API key is valid but you\'ve hit a request limit. Try again in a minute.');
            } elseif ($code === 529) {
                wp_send_json_error('Anthropic API is currently overloaded. Your key appears valid — try again shortly.');
            } else {
                $msg = $err_msg ?: "HTTP {$code}";
                wp_send_json_error("API error: {$msg}");
            }
        }
    }

    // =========================================================================
    // Admin menu & settings
    // =========================================================================
}
