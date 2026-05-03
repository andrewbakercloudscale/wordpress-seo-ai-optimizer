<?php
/**
 * Auto Pipeline — runs all AI operations on post publish; cleans up on delete.
 *
 * On publish, a non-blocking wp_remote_post() to admin-ajax.php fires the pipeline
 * immediately in a separate PHP process. No WP-Cron dependency.
 * Set CSEO_ASYNC_ENABLED to false for synchronous execution during local debugging.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.18.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Auto_Pipeline {

    // =========================================================================
    // Content helpers
    // =========================================================================

    /**
     * Returns clean, AI-ready text for a post by rendering block markup first.
     *
     * When $return_html is true, returns rendered HTML before tag stripping —
     * used by the ALT text scanner to run DOMDocument on proper HTML.
     *
     * @since 4.18.0
     * @param int  $post_id     Post ID.
     * @param bool $return_html When true, return rendered HTML instead of plain text.
     * @return string Plain text (default) or rendered HTML.
     */
    private function get_clean_content( int $post_id, bool $return_html = false ): string {
        // apply_filters( 'the_content', ... ) renders Gutenberg block markup to HTML.
        $rendered = apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core WP filter
        if ( $return_html ) return (string) $rendered;
        $plain = wp_strip_all_tags( $rendered );
        return trim( (string) preg_replace( '/\s+/', ' ', $plain ) );
    }

    /**
     * Truncates text to $max_words words, appending a notice if truncation occurred.
     *
     * @since 4.18.0
     * @param string $text      Input text.
     * @param int    $max_words Maximum word count.
     * @return string Truncated (or unchanged) text.
     */
    private function truncate_content( string $text, int $max_words = 6000 ): string {
        $words = explode( ' ', $text );
        if ( count( $words ) <= $max_words ) return $text;
        return implode( ' ', array_slice( $words, 0, $max_words ) ) . ' [content truncated]';
    }

    // =========================================================================
    // WordPress hooks
    // =========================================================================

    /**
     * Fires on post status transition. Schedules the publish pipeline for new posts.
     *
     * @since 4.18.0
     * @param string  $new_status New post status.
     * @param string  $old_status Previous post status.
     * @param WP_Post $post       Post object.
     * @return void
     */
    public function on_post_publish( string $new_status, string $old_status, WP_Post $post ): void {
        if ( ! (int) ( $this->ai_opts['auto_run_enabled'] ?? 0 ) ) return;
        if ( $new_status !== 'publish' || $old_status === 'publish' ) return;
        if ( $post->post_type !== 'post' ) return;
        // Do not re-run if this post was already processed (e.g. unpublish → republish).
        if ( get_post_meta( $post->ID, self::META_AUTO_COMPLETE, true ) ) return;

        if ( defined( 'CSEO_ASYNC_ENABLED' ) && ! CSEO_ASYNC_ENABLED ) {
            $this->run_auto_pipeline( $post->ID );
            return;
        }

        $this->fire_pipeline_async( $post->ID );
    }

    /**
     * Fires when an already-published post is updated.
     * Re-runs the full pipeline if auto_run_on_update is enabled.
     *
     * @since 4.18.6
     * @param int     $post_id     Post ID.
     * @param WP_Post $post_after  Post object after update.
     * @param WP_Post $post_before Post object before update.
     * @return void
     */
    public function on_post_update( int $post_id, WP_Post $post_after, WP_Post $post_before ): void {
        if ( ! (int) ( $this->ai_opts['auto_run_on_update'] ?? 0 ) ) return;
        if ( $post_after->post_type !== 'post' ) return;
        if ( $post_before->post_status !== 'publish' || $post_after->post_status !== 'publish' ) return;

        // Clear previous run so the pipeline treats this as a fresh run.
        delete_post_meta( $post_id, self::META_AUTO_COMPLETE );

        if ( defined( 'CSEO_ASYNC_ENABLED' ) && ! CSEO_ASYNC_ENABLED ) {
            $this->run_auto_pipeline( $post_id );
            return;
        }

        $this->fire_pipeline_async( $post_id );
    }

    /**
     * Fires before a post is permanently deleted. Schedules the cleanup pipeline.
     *
     * Uses before_delete_post (not trash_post) so the post ID still resolves at
     * schedule time; the cleanup cron fires moments after deletion completes.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    public function on_post_delete( int $post_id ): void {
        if ( ! (int) ( $this->ai_opts['auto_run_enabled'] ?? 0 ) ) return;
        if ( get_post_type( $post_id ) !== 'post' ) return;

        if ( defined( 'CSEO_ASYNC_ENABLED' ) && ! CSEO_ASYNC_ENABLED ) {
            $this->run_cleanup_pipeline( $post_id );
            return;
        }

        wp_schedule_single_event( time() + 2, 'cs_seo_cleanup_pipeline', [ $post_id ] );
    }

    // =========================================================================
    // Publish pipeline
    // =========================================================================

    /**
     * Fires a non-blocking HTTP POST to admin-ajax.php to run the pipeline asynchronously.
     *
     * wp_remote_post() with blocking=false returns immediately; the receiving PHP process
     * runs independently. A short-lived HMAC token authenticates the request.
     *
     * @since 4.18.9
     * @param int $post_id Post ID.
     * @return void
     */
    private function fire_pipeline_async( int $post_id ): void {
        $token = hash_hmac( 'sha256', 'cs_seo_pipeline|' . $post_id . '|' . time(), wp_salt( 'secure_auth' ) );
        set_transient( 'cs_seo_pipeline_token_' . $post_id, $token, 120 );

        wp_remote_post(
            admin_url( 'admin-ajax.php' ),
            [
                'blocking'  => false,
                'timeout'   => 0.01,
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- https_local_ssl_verify is a WordPress core filter; same pattern as WP core spawn_cron()
                'body'      => [
                    'action'  => 'cs_seo_pipeline_run',
                    'post_id' => $post_id,
                    'token'   => $token,
                ],
            ]
        );
    }

    /**
     * AJAX handler: receives the non-blocking pipeline trigger and runs the pipeline.
     *
     * Accessible without a logged-in session because the HMAC token authenticates the
     * request. The token is single-use and expires after 120 seconds.
     *
     * @since 4.18.9
     * @return void
     */
    public function ajax_pipeline_run(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- HMAC token authentication used instead of WP nonce; verified via hash_equals() below
        $post_id = (int) wp_unslash( $_POST['post_id'] ?? 0 );
        $token   = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( ! $post_id || ! $token ) wp_die( '', '', [ 'response' => 403 ] );

        $stored = get_transient( 'cs_seo_pipeline_token_' . $post_id );
        if ( ! $stored || ! hash_equals( (string) $stored, $token ) ) {
            wp_die( '', '', [ 'response' => 403 ] );
        }
        delete_transient( 'cs_seo_pipeline_token_' . $post_id );

        if ( get_post_status( $post_id ) !== 'publish' ) wp_die();

        $this->run_auto_pipeline( $post_id );
        wp_die();
    }

    /**
     * Runs all AI pipeline steps for a newly published post.
     * Called by ajax_pipeline_run (non-blocking HTTP) or directly when CSEO_ASYNC_ENABLED=false.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    public function run_auto_pipeline( int $post_id ): void {
        if ( get_post_status( $post_id ) !== 'publish' ) return;

        $log   = [];
        $steps = [
            'meta_desc'        => [ $this, 'auto_step_meta_desc' ],
            'focus_keyword'    => [ $this, 'auto_step_focus_keyword' ],
            'alt_text'         => [ $this, 'auto_step_alt_text' ],
            'internal_links'   => [ $this, 'auto_step_internal_links' ],
            'ai_summary'       => [ $this, 'auto_step_ai_summary' ],
            'related_articles' => [ $this, 'auto_step_related_articles' ],
            'readability'      => [ $this, 'auto_step_readability' ],
            'aeo_answer'       => [ $this, 'auto_step_aeo_answer' ],
        ];

        foreach ( $steps as $step_name => $callable ) {
            $started = time();
            try {
                call_user_func( $callable, $post_id );
                $log[] = [
                    'step'      => $step_name,
                    'status'    => 'ok',
                    'message'   => '',
                    'timestamp' => $started,
                ];
            } catch ( \Throwable $e ) {
                $log[] = [
                    'step'      => $step_name,
                    'status'    => 'error',
                    'message'   => $e->getMessage(),
                    'timestamp' => $started,
                ];
            }
            // Save after each step so partial results survive a timeout.
            set_transient( 'cs_seo_auto_run_log_' . $post_id, $log, 30 * DAY_IN_SECONDS );
        }

        update_post_meta( $post_id, self::META_AUTO_COMPLETE, time() );
    }

    /**
     * Step 1 — meta description, SEO score, and inline image ALT text (single AI call).
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_meta_desc( int $post_id ): void {
        if ( str_word_count( $this->get_clean_content( $post_id ) ) < 50 ) return;

        $result = $this->call_ai_generate_all( $post_id );
        update_post_meta( $post_id, self::META_DESC, sanitize_textarea_field( $result['description'] ) );
        if ( ! is_null( $result['seo_score'] ) ) {
            update_post_meta( $post_id, self::META_SEO_SCORE, (int) $result['seo_score'] );
            update_post_meta( $post_id, self::META_SEO_NOTES, sanitize_text_field( $result['seo_notes'] ?? '' ) );
        }
    }

    /**
     * Step 2 — focus keyword extraction (skips if already set).
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_focus_keyword( int $post_id ): void {
        if ( trim( (string) get_post_meta( $post_id, self::META_FOCUS_KW, true ) ) ) return;
        if ( str_word_count( $this->get_clean_content( $post_id ) ) < 50 ) return;

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) $this->ai_opts['anthropic_key'] );
        if ( ! $key ) return;

        $model    = $this->resolve_model( trim( (string) $this->ai_opts['model'] ), $provider );
        $title    = get_the_title( $post_id );
        $content  = $this->truncate_content( $this->get_clean_content( $post_id ), 2000 );

        $system   = 'You are an SEO expert. Given a post title and content, return the single best focus keyword or short phrase. Reply with ONLY the keyword, nothing else. No punctuation at the end.';
        $user_msg = "Post title: \"{$title}\"\n\nContent:\n{$content}";

        $kw = trim( $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 30 ), ' "\'.' );
        if ( $kw ) {
            update_post_meta( $post_id, self::META_FOCUS_KW, sanitize_text_field( $kw ) );
        }
    }

    /**
     * Step 3 — ALT text for formally attached images missing ALT.
     *
     * Inline content images are already handled by call_ai_generate_all in step 1.
     * This step covers attachments uploaded to the post but not embedded in content.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_alt_text( int $post_id ): void {
        $attached = get_attached_media( 'image', $post_id );
        if ( empty( $attached ) ) return;

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) $this->ai_opts['anthropic_key'] );
        if ( ! $key ) return;

        $model  = $this->resolve_model( trim( (string) $this->ai_opts['model'] ), $provider );
        $title  = get_the_title( $post_id );
        $system = 'You write concise, descriptive image alt text. Return 5–15 words describing the image in context. Do not start with "Image of" or "Photo of". Output ONLY the alt text.';

        foreach ( $attached as $attachment ) {
            if ( trim( (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ) ) continue;

            $file     = (string) get_attached_file( $attachment->ID );
            $filename = pathinfo( $file, PATHINFO_FILENAME );
            $filename = (string) preg_replace( '/[\s_-]+\d+x\d+$/', '', $filename );
            $filename = str_replace( [ '-', '_' ], ' ', $filename );

            $user_msg = "Post title: \"{$title}\"\nImage filename hint: \"{$filename}\"\nWrite descriptive alt text for this image.";
            $alt      = trim( $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 60 ), ' "\'' );
            if ( $alt ) {
                update_post_meta( $attachment->ID, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
            }
        }
    }

    /**
     * Step 4 — suggest and inject up to 3 internal links.
     *
     * Uses parse_blocks / serialize_blocks for Gutenberg; direct str_replace for classic.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_internal_links( int $post_id ): void {
        if ( str_word_count( $this->get_clean_content( $post_id ) ) < 50 ) return;

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) $this->ai_opts['anthropic_key'] );
        if ( ! $key ) return;

        $model   = $this->resolve_model( trim( (string) $this->ai_opts['model'] ), $provider );
        $content = $this->truncate_content( $this->get_clean_content( $post_id ), 6000 );
        $title   = get_the_title( $post_id );

        $other_ids = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'post__not_in'   => [ $post_id ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- pool capped at 20; current post exclusion required for internal link suggestions
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ] );
        if ( empty( $other_ids ) ) return;

        $link_list = '';
        foreach ( $other_ids as $oid ) {
            $link_list .= '- ' . get_the_title( $oid ) . ' | ' . get_permalink( $oid ) . "\n";
        }

        $system   = 'You are an SEO expert. Identify up to 3 places in this article where an internal link would improve SEO. CRITICAL: only suggest anchor_text that appears VERBATIM (exact characters, exact case) in the content. Respond ONLY with a valid JSON array, no other text. Format: [{"anchor_text":"...","url":"...","context_sentence":"..."}]';
        $user_msg = "Article title: \"{$title}\"\n\nArticle content:\n{$content}\n\nAvailable links:\n{$link_list}";

        $raw = trim( $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 400 ) );
        $raw = (string) preg_replace( '/^```(?:json)?\s*/i', '', $raw );
        $raw = (string) preg_replace( '/\s*```$/', '', $raw );

        $suggestions = json_decode( $raw, true );
        if ( ! is_array( $suggestions ) || empty( $suggestions ) ) return;

        // Validate AI-returned URLs are on this site — prevents prompt-injection
        // from causing off-site URLs to be written into post content.
        $home_host   = wp_parse_url( home_url(), PHP_URL_HOST );
        $suggestions = array_filter( $suggestions, function( $s ) use ( $home_host ) {
            $url  = $s['url'] ?? '';
            $host = wp_parse_url( $url, PHP_URL_HOST );
            return $host === $home_host || $host === null; // null = relative URL, allowed
        } );
        if ( empty( $suggestions ) ) return;

        $this->apply_internal_links( $post_id, array_values( $suggestions ) );
    }

    /**
     * Applies internal link suggestions to post content.
     *
     * Uses parse_blocks / serialize_blocks for Gutenberg posts (operating only within
     * core/paragraph and core/heading block innerHTML to preserve block structure).
     * Falls back to direct str_replace for classic editor posts.
     *
     * @since 4.18.0
     * @param int   $post_id     Post ID.
     * @param array $suggestions Array of {anchor_text, url, context_sentence} items from AI.
     * @return void
     */
    private function apply_internal_links( int $post_id, array $suggestions ): void {
        $post = get_post( $post_id );
        if ( ! $post ) return;

        $post_content = $post->post_content;
        $changed      = false;

        if ( has_blocks( $post_content ) ) {
            $blocks = parse_blocks( $post_content );

            foreach ( $blocks as &$block ) {
                if ( ! in_array( $block['blockName'], [ 'core/paragraph', 'core/heading' ], true ) ) continue;

                foreach ( $suggestions as $s ) {
                    $anchor = sanitize_text_field( $s['anchor_text'] ?? '' );
                    $url    = esc_url_raw( $s['url'] ?? '' );
                    if ( ! $anchor || ! $url ) continue;
                    if ( strpos( $block['innerHTML'], $anchor ) === false ) continue;
                    // Skip block if it already contains a link to avoid double-linking.
                    if ( strpos( $block['innerHTML'], 'href=' ) !== false ) continue;

                    $escaped  = preg_quote( $anchor, '/' );
                    $link     = '<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ) . '</a>'; // $anchor is sanitize_text_field'd; esc_html encodes & and other chars for valid HTML in post_content
                    // Use preg_replace_callback to avoid '$' backreference expansion in the replacement string.
                    $new_html = (string) preg_replace_callback( '/' . $escaped . '/', static function() use ( $link ) { return $link; }, $block['innerHTML'], 1 );
                    if ( $new_html === $block['innerHTML'] ) continue;

                    $block['innerHTML'] = $new_html;
                    // serialize_blocks uses innerContent, not innerHTML — update both.
                    $block['innerContent'] = array_map(
                        function ( $piece ) use ( $escaped, $link ) {
                            return is_string( $piece )
                                ? (string) preg_replace( '/' . $escaped . '/', $link, $piece, 1 )
                                : $piece;
                        },
                        $block['innerContent']
                    );
                    $changed = true;
                    break; // one link per block
                }
            }
            unset( $block );

            if ( $changed ) {
                // wp_slash() required — see trait-ai-alt-text.php for explanation.
                wp_update_post( [ 'ID' => $post_id, 'post_content' => wp_slash( serialize_blocks( $blocks ) ) ] );
            }
        } else {
            // Classic editor: direct str_replace is safe (no block delimiters to corrupt).
            $new_content = $post_content;
            foreach ( $suggestions as $s ) {
                $anchor = sanitize_text_field( $s['anchor_text'] ?? '' );
                $url    = esc_url_raw( $s['url'] ?? '' );
                if ( ! $anchor || ! $url ) continue;
                if ( strpos( $new_content, $anchor ) === false ) continue;
                $link        = '<a href="' . esc_url( $url ) . '">' . $anchor . '</a>'; // $anchor is sanitize_text_field'd; no esc_html to avoid double-encoding on storage
                // Use preg_replace_callback to avoid '$' backreference expansion in the replacement string.
                $new_content = (string) preg_replace_callback( '/' . preg_quote( $anchor, '/' ) . '/', static function() use ( $link ) { return $link; }, $new_content, 1 );
                $changed     = true;
            }
            if ( $changed ) {
                // wp_slash() required — see trait-ai-alt-text.php for explanation.
                wp_update_post( [ 'ID' => $post_id, 'post_content' => wp_slash( $new_content ) ] );
            }
        }
    }

    /**
     * Step 5 — AI summary box (skips if summary already exists).
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_ai_summary( int $post_id ): void {
        // Only skip if all three fields exist — matches prepend_summary_box() render logic.
        if ( trim( (string) get_post_meta( $post_id, self::META_SUM_WHAT, true ) )
          && trim( (string) get_post_meta( $post_id, self::META_SUM_WHY,  true ) )
          && trim( (string) get_post_meta( $post_id, self::META_SUM_KEY,  true ) ) ) return;

        // Skip if content is too short to meaningfully summarise.
        $content = $this->get_clean_content( $post_id );
        if ( str_word_count( $content ) < 50 ) return;

        $result = $this->call_ai_generate_summary( $post_id );
        update_post_meta( $post_id, self::META_SUM_WHAT, sanitize_text_field( $result['what'] ) );
        update_post_meta( $post_id, self::META_SUM_WHY,  sanitize_text_field( $result['why'] ) );
        update_post_meta( $post_id, self::META_SUM_KEY,  sanitize_text_field( $result['takeaway'] ) );
    }

    /**
     * Step 6 — AEO (Answer Engine Optimisation) answer paragraph.
     *
     * @since 4.20.93
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_aeo_answer( int $post_id ): void {
        if ( trim( (string) get_post_meta( $post_id, self::META_AEO_ANSWER, true ) ) ) return;

        $content = $this->get_clean_content( $post_id );
        if ( str_word_count( $content ) < 50 ) return;

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) $this->ai_opts['anthropic_key'] );
        if ( ! $key ) return;

        $model   = $this->resolve_model( trim( (string) $this->ai_opts['model'] ), $provider );
        $title   = get_the_title( $post_id );
        $excerpt = mb_substr( $this->truncate_content( $content, 800 ), 0, 800 );

        $system   = 'You are an SEO specialist writing AEO answer paragraphs for featured snippets. Write a single paragraph of exactly 40–60 words that directly answers the implicit question behind the post title. Plain prose only — no bullet points, no headings, no markdown. Do not start with "I" or the post title. Output only the paragraph, nothing else.';
        $user_msg = "Post title: \"{$title}\"\n\nOpening content:\n{$excerpt}";

        $answer = trim( (string) $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 150 ), ' "\'' );
        if ( $answer ) {
            update_post_meta( $post_id, self::META_AEO_ANSWER, sanitize_textarea_field( $answer ) );
        }
    }

    /**
     * Step 7 — Related Articles generation pipeline.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_related_articles( int $post_id ): void {
        // rc_on_post_publish already ran this synchronously on publish — skip if complete.
        if ( get_post_meta( $post_id, self::META_RC_STATUS, true ) === 'complete' ) return;

        $this->rc_step_load( $post_id );
        if ( get_post_meta( $post_id, self::META_RC_STATUS, true ) === 'complete' ) return;

        $this->rc_step_validate( $post_id );
        if ( get_post_meta( $post_id, self::META_RC_STATUS, true ) === 'complete' ) return;

        $this->rc_step_candidates( $post_id );
        $this->rc_step_score( $post_id );
        $this->rc_step_top( $post_id );
        $this->rc_step_bottom( $post_id );
        $this->rc_step_validate_out( $post_id );
        $this->rc_step_complete( $post_id );
    }

    // =========================================================================
    // Delete cleanup pipeline
    // =========================================================================

    /**
     * Purges all plugin data for a deleted post.
     * Called by the cs_seo_cleanup_pipeline cron event.
     *
     * By the time this fires, WordPress has already deleted postmeta via
     * wp_delete_post(). This catches any surviving rows and handles
     * transient / log cleanup which WordPress does not perform.
     *
     * @since 4.18.0
     * @param int $post_id Post ID.
     * @return void
     */
    public function run_cleanup_pipeline( int $post_id ): void {
        global $wpdb;

        // Delete any remaining plugin postmeta (idempotent — WP may have already done this).
        $like         = $wpdb->esc_like( '_cs_' ) . '%';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk cleanup of plugin meta; no WP API supports LIKE on meta_key
        $keys_deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
                $post_id,
                $like
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

        // Delete run log transient (lives in wp_options, not wp_postmeta — WP won't clean this).
        delete_transient( 'cs_seo_auto_run_log_' . $post_id );

        // Write cleanup log entry (capped at 50 entries).
        $cleanup_log = (array) get_option( 'cs_seo_cleanup_log', [] );
        array_unshift( $cleanup_log, [
            'post_id'      => $post_id,
            'timestamp'    => time(),
            'keys_deleted' => $keys_deleted,
        ] );
        update_option( 'cs_seo_cleanup_log', array_slice( $cleanup_log, 0, 50 ), false );
    }

    // =========================================================================
    // Admin — metabox
    // =========================================================================

    /**
     * Registers the Auto Run metabox on the post edit screen.
     *
     * @since 4.18.0
     * @return void
     */
    public function add_auto_run_metabox(): void {
        add_meta_box(
            'cs_seo_auto_run',
            esc_html__( 'CloudScale SEO Auto Run', 'cloudscale-seo-ai-optimizer' ),
            [ $this, 'render_auto_run_metabox' ],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Renders the Auto Run metabox.
     *
     * @since 4.18.0
     * @param WP_Post $post Post object.
     * @return void
     */
    public function render_auto_run_metabox( WP_Post $post ): void {
        $complete = (int) get_post_meta( $post->ID, self::META_AUTO_COMPLETE, true );
        $queued   = (bool) wp_next_scheduled( 'cs_seo_auto_run_pipeline', [ $post->ID ] );
        $log      = get_transient( 'cs_seo_auto_run_log_' . $post->ID );
        $nonce    = wp_create_nonce( 'cs_seo_nonce' );

        if ( $queued ) {
            $status_label = esc_html__( 'Queued', 'cloudscale-seo-ai-optimizer' );
            $status_color = '#d97706';
        } elseif ( $complete ) {
            $status_label = esc_html__( 'Complete', 'cloudscale-seo-ai-optimizer' );
            $status_color = '#16a34a';
        } else {
            $status_label = esc_html__( 'Not yet run', 'cloudscale-seo-ai-optimizer' );
            $status_color = '#6b7280';
        }
        ?>
        <div id="cs-auto-run-box" style="font-size:13px;">
            <p style="margin:0 0 6px;">
                <strong><?php esc_html_e( 'Status:', 'cloudscale-seo-ai-optimizer' ); ?></strong>
                <span style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:700;margin-left:4px;"><?php echo esc_html( $status_label ); ?></span>
            </p>
            <?php if ( $complete ) : ?>
            <p style="margin:0 0 8px;color:#6b7280;font-size:12px;">
                <?php
                /* translators: %s: human-readable time difference, e.g. "2 hours" */
                printf( esc_html__( 'Last run: %s ago', 'cloudscale-seo-ai-optimizer' ), esc_html( human_time_diff( $complete ) ) );
                ?>
            </p>
            <?php endif; ?>
            <button type="button" id="cs-auto-rerun-btn"
                    class="button button-secondary"
                    style="width:100%;margin-bottom:10px;"
                    data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
                    data-nonce="<?php echo esc_attr( $nonce ); ?>">
                <?php esc_html_e( '↺ Re-run AI Automation', 'cloudscale-seo-ai-optimizer' ); ?>
            </button>
            <?php if ( is_array( $log ) && ! empty( $log ) ) : ?>
            <details>
                <summary style="cursor:pointer;font-size:12px;color:#6b7280;margin-bottom:6px;"><?php esc_html_e( 'Run log', 'cloudscale-seo-ai-optimizer' ); ?></summary>
                <ul style="margin:0;padding:0 0 0 14px;font-size:11px;color:#374151;">
                    <?php foreach ( $log as $entry ) : ?>
                    <?php $status_ok = ( ( $entry['status'] ?? '' ) === 'ok' ); ?>
                    <li style="margin-bottom:3px;">
                        <strong><?php echo esc_html( $entry['step'] ?? '' ); ?>:</strong>
                        <span style="color:<?php echo esc_attr( $status_ok ? '#16a34a' : '#dc2626' ); ?>;">
                            <?php echo esc_html( $entry['status'] ?? '' ); ?>
                        </span>
                        <?php if ( ! empty( $entry['message'] ) ) : ?>
                            &mdash; <?php echo esc_html( mb_substr( $entry['message'], 0, 120 ) ); ?>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
            <?php endif; ?>
        </div>
        <?php
        // Inline JS attached to the registered metabox script handle — no echoed <script> tags.
        ob_start(); ?>
        document.addEventListener('DOMContentLoaded', function() {
            var btn = document.getElementById('cs-auto-rerun-btn');
            if (btn) {
                btn.addEventListener('click', function() {
                    var postId = btn.getAttribute('data-post-id');
                    var nonce = btn.getAttribute('data-nonce');
                    btn.disabled = true;
                    btn.textContent = '...Scheduling';
                    var fd = new FormData();
                    fd.append('action',  'cs_seo_auto_rerun');
                    fd.append('nonce',   nonce);
                    fd.append('post_id', postId);
                    fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            if (d.success) {
                                btn.textContent = '\u2713 Queued \u2014 reload in a moment';
                                btn.style.color = '#16a34a';
                            } else {
                                btn.textContent = '\u2717 Failed';
                                btn.disabled = false;
                            }
                        })
                        .catch(function() {
                            btn.textContent = '\u2717 Error';
                            btn.disabled = false;
                        });
                });
            }
        });
        <?php $cs_seo_js = ob_get_clean(); if ( wp_script_is( 'cs-seo-metabox-js', 'registered' ) ) { wp_add_inline_script( 'cs-seo-metabox-js', $cs_seo_js ); }
    }

    // =========================================================================
    // Admin — AJAX
    // =========================================================================

    /**
     * AJAX handler: clears the auto-run complete flag and reschedules the pipeline.
     *
     * @since 4.18.0
     * @return void
     */
    public function ajax_auto_rerun(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $post_id = (int) wp_unslash( $_POST['post_id'] ?? 0 );

        if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
            wp_send_json_error( 'Invalid post.' );
            return;
        }

        delete_post_meta( $post_id, self::META_AUTO_COMPLETE );
        delete_transient( 'cs_seo_auto_run_log_' . $post_id );

        $this->fire_pipeline_async( $post_id );

        wp_send_json_success( [ 'scheduled' => true ] );
    }

    // =========================================================================
    // Dashboard helpers
    // =========================================================================

    /**
     * Returns the count of published posts that have never completed auto-run.
     *
     * @since 4.18.0
     * @return int
     */
    public function count_posts_missing_auto_run(): int {
        global $wpdb;
        // Count published posts that either have no meta description at all, or were
        // edited after the last auto-pipeline run (description may be stale).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- live count for dashboard widget; acceptable overhead
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID)
                   FROM {$wpdb->posts} p
              LEFT JOIN {$wpdb->postmeta} desc_pm
                     ON desc_pm.post_id = p.ID AND desc_pm.meta_key = %s
              LEFT JOIN {$wpdb->postmeta} run_pm
                     ON run_pm.post_id  = p.ID AND run_pm.meta_key  = %s
                  WHERE p.post_status = 'publish'
                    AND p.post_type   = %s
                    AND (
                        (desc_pm.meta_value IS NULL OR desc_pm.meta_value = '')
                        OR
                        (run_pm.meta_value IS NOT NULL
                            AND UNIX_TIMESTAMP(p.post_modified_gmt) > CAST(run_pm.meta_value AS UNSIGNED))
                    )",
                self::META_DESC,
                self::META_AUTO_COMPLETE,
                'post'
            )
        );
    }

}
