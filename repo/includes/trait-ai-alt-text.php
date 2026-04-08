<?php
/**
 * AI-powered ALT text generation for images using Anthropic Claude or Google Gemini.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Alt_Text {
    /**
     * AJAX handler: returns all published posts with their image and ALT text status.
     *
     * @since 4.9.4
     * @return void
     */
    public function ajax_alt_get_posts(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $posts = [];
        $page  = 1;
        $batch = 500;
        do {
            $chunk = get_posts([
                'post_type'           => ['post', 'page'],
                'post_status'         => 'publish',
                'posts_per_page'      => $batch,
                'paged'               => $page++,
                'orderby'             => 'date',
                'order'               => 'DESC',
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ]);
            $posts = array_merge($posts, $chunk);
        } while (count($chunk) === $batch);

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
                    'edit_link'    => get_edit_post_link($post_id),
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
    /**
     * AJAX handler: generates AI ALT text for all images missing it in a single post.
     *
     * @since 4.9.5
     * @return void
     */
    public function ajax_alt_generate_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function
        if (!$post_id) wp_send_json_error('Missing post_id');
        $force = (int) sanitize_text_field( wp_unslash( $_POST['force'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() at the top of this function

        $post = get_post($post_id);
        if (!$post) wp_send_json_error('Post not found');

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        if (!$key) wp_send_json_error($provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured');

        $model   = $this->resolve_model(trim((string) $this->ai_opts['model']), $provider);
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
                $warnings[] = sprintf( '%s: %s', esc_url( $src ), esc_html( $e->getMessage() ) );
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
                    $warnings[] = sprintf( 'Featured image %s: %s', esc_url( $thumb_url ), esc_html( $e->getMessage() ) );
                }
            }
        }

        if ($updated > 0 && $new_content !== $content) {
            // Save updated post content only if content images were changed.
            // wp_slash() is required for programmatic wp_update_post() calls: WordPress's
            // content_save_pre filter chain calls stripslashes() internally, which would
            // strip backslashes from block comment JSON (e.g. \" → " and \n → n),
            // corrupting Gutenberg block attributes. The REST API always uses wp_slash()
            // before wp_update_post() for the same reason.
            wp_update_post([
                'ID'           => $post_id,
                'post_content' => wp_slash( $new_content ),
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
    /**
     * AJAX handler: batch wrapper for ajax_alt_generate_one(), used by the bulk polling loop.
     *
     * @since 4.10.17
     * @return void
     */
    public function ajax_alt_generate_all(): void {
        $this->ajax_alt_generate_one();
    }
}
