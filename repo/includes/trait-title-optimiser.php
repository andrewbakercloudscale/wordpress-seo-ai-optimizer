<?php
/**
 * Title Optimiser — AI-powered SEO title suggestions with before/after scoring.
 *
 * Scans all published posts, suggests keyword-rich replacement titles, shows
 * SEO score before and after, and applies titles with automatic 301 redirects.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.20.2
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Title_Optimiser {

    // =========================================================================
    // Title Optimiser
    // =========================================================================

    /**
     * Calls the AI to suggest an SEO-optimised title for a post.
     *
     * Returns the suggested title, identified keywords, and SEO scores
     * for both the current and suggested titles.
     *
     * @since 4.20.2
     * @param int $post_id The post ID.
     * @return array{suggested_title: string, keywords: string[], score_before: int, score_after: int, notes: string}
     * @throws \RuntimeException If the post is not found or no API key is configured.
     */
    private function call_ai_optimise_title( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) throw new \RuntimeException( "Post {$post_id} not found" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) $this->ai_opts['anthropic_key'] );
        $model    = $this->resolve_model( trim( (string) $this->ai_opts['model'] ), $provider );

        if ( ! $key ) throw new \RuntimeException( $provider === 'gemini' ? 'No Gemini API key configured' : 'No Anthropic API key configured' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

        $content      = mb_substr( Cs_Seo_Utils::text_from_html( (string) $post->post_content ), 0, 4000 );
        $current_title = get_the_title( $post_id );
        $audience      = trim( (string) ( $this->opts['audience'] ?? '' ) );
        $tone          = trim( (string) ( $this->opts['writing_tone'] ?? '' ) );

        $context = '';
        if ( $audience ) $context .= "\nTarget audience: {$audience}";
        if ( $tone )     $context .= "\nWriting tone: {$tone}";

        $system = 'You are an expert SEO consultant. Your task is to analyse an article and suggest a keyword-optimised title.' . "\n\n"
            . 'SEO title rules:' . "\n"
            . '- Ideal length: 50–60 characters (strict). Count every character including spaces.' . "\n"
            . '- Lead with the primary keyword — place it as early as possible in the title.' . "\n"
            . '- Match clear search intent: how-to, what-is, best, guide, tips, etc.' . "\n"
            . '- Be specific and concrete — avoid vague words like "things", "stuff", "everything".' . "\n"
            . '- Include numbers where natural (e.g. "5 Ways", "3 Steps").' . "\n"
            . '- No clickbait. No "You Won\'t Believe", "Shocking", etc.' . "\n"
            . '- Do not include the site name or brand suffix.' . "\n\n"
            . 'Scoring rules (0–100 integer):' . "\n"
            . '- score_before: score the CURRENT title on keyword clarity, length (ideal 50–60 chars), search intent alignment.' . "\n"
            . '- score_after: score your SUGGESTED title using the same criteria.' . "\n\n"
            . 'Respond ONLY with valid JSON, no markdown, no other text:' . "\n"
            . '{"suggested_title":"...","keywords":["keyword1","keyword2","keyword3"],"score_before":42,"score_after":78,"notes":"One sentence on what was weak and what was fixed."}';

        $user_msg = "Current title: \"{$current_title}\"\n{$context}\n\nArticle content:\n{$content}";

        $raw = $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 300 );
        $raw = trim( $raw );
        $raw = preg_replace( '/^```(?:json)?\s*/i', '', $raw );
        $raw = preg_replace( '/\s*```$/', '', $raw );

        $json = json_decode( $raw, true );

        if (
            ! is_array( $json )
            || empty( $json['suggested_title'] )
            || ! isset( $json['score_before'], $json['score_after'] )
        ) {
            throw new \RuntimeException( 'Invalid title optimiser response from AI: ' . esc_html( mb_substr( $raw, 0, 200 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $keywords = [];
        if ( ! empty( $json['keywords'] ) && is_array( $json['keywords'] ) ) {
            foreach ( $json['keywords'] as $kw ) {
                $keywords[] = sanitize_text_field( (string) $kw );
            }
        }

        return [
            'suggested_title' => sanitize_text_field( trim( $json['suggested_title'] ) ),
            'keywords'        => $keywords,
            'score_before'    => min( 100, max( 0, (int) $json['score_before'] ) ),
            'score_after'     => min( 100, max( 0, (int) $json['score_after'] ) ),
            'notes'           => sanitize_text_field( (string) ( $json['notes'] ?? '' ) ),
        ];
    }

    // -------------------------------------------------------------------------
    // AJAX: Load post list
    // -------------------------------------------------------------------------

    /**
     * AJAX: returns all published posts with title optimiser status for the bulk panel.
     *
     * @since 4.20.2
     * @return void
     */
    public function ajax_title_optimiser_load(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $sort = isset( $_POST['sort'] ) ? sanitize_key( wp_unslash( $_POST['sort'] ) ) : 'date';

        $orderby = $sort === 'comments' ? 'comment_count' : 'date';

        $all_ids = get_posts( [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'orderby'             => $orderby,
            'order'               => 'DESC',
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ] );

        _prime_post_caches( $all_ids, false, false );
        update_meta_cache( 'post', $all_ids );

        $posts     = [];
        $optimised = 0;
        $applied   = 0;

        foreach ( $all_ids as $id ) {
            $suggested   = get_post_meta( $id, self::META_TITLE_OPT_SUGGESTED, true );
            $status      = get_post_meta( $id, self::META_TITLE_OPT_STATUS, true );
            $analysed_at = (string) get_post_meta( $id, self::META_TITLE_OPT_ANALYSED_AT, true );
            $modified    = (string) get_post_field( 'post_modified', $id );

            // Post is stale if it was edited more than 60 seconds after the last analysis.
            // The 60-second grace prevents false positives caused by wp_update_post() setting
            // post_modified ~1 second after we stored analysed_at in apply handlers.
            $stale = $analysed_at && $modified
                && ( strtotime( $modified ) - strtotime( $analysed_at ) > 60 );

            if ( $suggested )              $optimised++;
            if ( $status === 'applied' )   $applied++;

            $posts[] = [
                'id'             => $id,
                'title'          => get_the_title( $id ),
                'original_title' => (string) get_post_meta( $id, self::META_TITLE_OPT_ORIGINAL, true ),
                'date'           => get_the_date( 'Y-m-d', $id ),
                'comments'      => (int) get_post_field( 'comment_count', $id ),
                'suggested'     => $suggested ?: '',
                'keywords'      => get_post_meta( $id, self::META_TITLE_OPT_KEYWORDS, true ) ?: [],
                'score_before'  => (int) get_post_meta( $id, self::META_TITLE_OPT_SCORE_BEFORE, true ),
                'score_after'   => (int) get_post_meta( $id, self::META_TITLE_OPT_SCORE_AFTER, true ),
                'notes'         => (string) get_post_meta( $id, self::META_TITLE_OPT_NOTES, true ),
                'status'        => $status ?: 'pending',
                'post_url'      => (string) get_permalink( $id ),
                'edit_link'     => get_edit_post_link( $id ),
                'analysed_at'   => $analysed_at ? substr( $analysed_at, 0, 10 ) : '',
                'stale'         => $stale,
            ];
        }

        wp_send_json_success( [
            'total'     => count( $all_ids ),
            'optimised' => $optimised,
            'applied'   => $applied,
            'posts'     => $posts,
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Analyse single post (AI suggestion only, no apply)
    // -------------------------------------------------------------------------

    /**
     * AJAX: runs AI title optimisation for a single post and stores the suggestion.
     *
     * @since 4.20.2
     * @return void
     */
    public function ajax_title_optimise_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Missing post_id' );

        try {
            // Capture the title before AI rewrites it so we can always show the "before" state.
            update_post_meta( $post_id, self::META_TITLE_OPT_ORIGINAL, get_the_title( $post_id ) );

            $result = $this->call_ai_optimise_title( $post_id );

            update_post_meta( $post_id, self::META_TITLE_OPT_SUGGESTED,    $result['suggested_title'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_KEYWORDS,     $result['keywords'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_BEFORE, $result['score_before'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_AFTER,  $result['score_after'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_NOTES,        $result['notes'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_STATUS,       'suggested' );
            update_post_meta( $post_id, self::META_TITLE_OPT_ANALYSED_AT,  current_time( 'mysql' ) );

            wp_send_json_success( array_merge( [ 'post_id' => $post_id ], $result ) );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Batch analyse all posts
    // -------------------------------------------------------------------------

    /**
     * AJAX: processes one post per call in a polling batch loop (AI suggestion pass).
     *
     * @since 4.20.2
     * @return void
     */
    public function ajax_title_analyse_all(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $force = ! empty( $_POST['force'] );

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( ! $force ) {
            $args['meta_query'] = [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'key'     => self::META_TITLE_OPT_SUGGESTED,
                'compare' => 'NOT EXISTS',
            ] ];
        }

        $ids = get_posts( $args );

        if ( empty( $ids ) ) {
            wp_send_json_success( [ 'done' => true, 'remaining' => 0 ] );
        }

        $post_id = (int) $ids[0];

        try {
            update_post_meta( $post_id, self::META_TITLE_OPT_ORIGINAL, get_the_title( $post_id ) );

            $result = $this->call_ai_optimise_title( $post_id );

            update_post_meta( $post_id, self::META_TITLE_OPT_SUGGESTED,    $result['suggested_title'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_KEYWORDS,     $result['keywords'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_BEFORE, $result['score_before'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_AFTER,  $result['score_after'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_NOTES,        $result['notes'] );
            update_post_meta( $post_id, self::META_TITLE_OPT_ANALYSED_AT,  current_time( 'mysql' ) );
            // Only mark as suggested if not already applied.
            if ( get_post_meta( $post_id, self::META_TITLE_OPT_STATUS, true ) !== 'applied' ) {
                update_post_meta( $post_id, self::META_TITLE_OPT_STATUS, 'suggested' );
            }

            // Count remaining.
            $rem_args                   = $args;
            $rem_args['no_found_rows']  = false;
            $rem_q = new \WP_Query( $rem_args );

            wp_send_json_success( [
                'post_id'         => $post_id,
                'title'           => get_the_title( $post_id ),
                'suggested_title' => $result['suggested_title'],
                'keywords'        => $result['keywords'],
                'score_before'    => $result['score_before'],
                'score_after'     => $result['score_after'],
                'notes'           => $result['notes'],
                'done'            => $rem_q->found_posts === 0,
                'remaining'       => (int) $rem_q->found_posts,
            ] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX: Apply a suggested title to a single post
    // -------------------------------------------------------------------------

    /**
     * AJAX: applies the suggested title to a post — updates post_title and post_name,
     * then creates an automatic 301 redirect from the old slug to the new permalink.
     *
     * @since 4.20.2
     * @return void
     */
    public function ajax_title_apply_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
        if ( ! $post_id ) wp_send_json_error( 'Missing post_id' );

        $post = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            wp_send_json_error( 'Post not found or not published' );
        }

        $suggested = get_post_meta( $post_id, self::META_TITLE_OPT_SUGGESTED, true );
        if ( ! $suggested ) {
            wp_send_json_error( 'No suggested title found — run Analyse first' );
        }

        // Capture the old permalink before changing anything.
        $old_url = get_permalink( $post_id );

        // Build the new slug from the suggested title.
        $new_slug = sanitize_title( $suggested );

        // Apply new title and slug.
        $updated = wp_update_post( [
            'ID'         => $post_id,
            'post_title' => $suggested,
            'post_name'  => $new_slug,
        ], true );

        if ( is_wp_error( $updated ) ) {
            wp_send_json_error( $updated->get_error_message() );
        }

        $new_url = get_permalink( $post_id );

        // Store a 301 redirect and update internal links if the slug actually changed.
        if ( $old_url && $new_url && $old_url !== $new_url ) {
            $this->store_redirect( $old_url, (string) $new_url, $post_id );
            $this->title_opt_update_internal_links( $old_url, (string) $new_url );
        }

        // Mark as applied. Use the actual post_modified written by wp_update_post so
        // post_modified === analysed_at and the post is never flagged as stale immediately.
        $post_modified = (string) get_post_field( 'post_modified', $post_id );
        update_post_meta( $post_id, self::META_TITLE_OPT_STATUS,      'applied' );
        update_post_meta( $post_id, self::META_TITLE_OPT_ANALYSED_AT, $post_modified ?: current_time( 'mysql' ) );

        wp_send_json_success( [
            'post_id'     => $post_id,
            'new_title'   => $suggested,
            'new_url'     => $new_url,
            'old_url'     => $old_url,
            'redirected'  => ( $old_url !== $new_url ),
        ] );
    }

    // -------------------------------------------------------------------------
    // AJAX: Apply all suggested titles in batch
    // -------------------------------------------------------------------------

    /**
     * AJAX: applies all suggested (not yet applied) titles in a single batch call.
     *
     * Processes only posts that have a suggested title and status !== 'applied'.
     * Applies up to 50 per call to avoid PHP timeouts.
     *
     * @since 4.20.2
     * @return void
     */
    public function ajax_title_apply_all(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $candidates = get_posts( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'relation' => 'AND',
                [
                    'key'     => self::META_TITLE_OPT_SUGGESTED,
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => self::META_TITLE_OPT_STATUS,
                    'value'   => 'suggested',
                    'compare' => '=',
                ],
            ],
        ] );

        if ( empty( $candidates ) ) {
            wp_send_json_success( [ 'applied' => 0, 'message' => 'Nothing to apply — run Analyse All first.' ] );
        }

        $applied       = 0;
        $redirects     = 0;
        $errors        = [];
        $applied_list  = [];

        foreach ( $candidates as $post_id ) {
            $post      = get_post( $post_id );
            $suggested = get_post_meta( $post_id, self::META_TITLE_OPT_SUGGESTED, true );
            if ( ! $post || ! $suggested ) continue;

            $old_url  = get_permalink( $post_id );
            $new_slug = sanitize_title( $suggested );

            $updated = wp_update_post( [
                'ID'         => $post_id,
                'post_title' => $suggested,
                'post_name'  => $new_slug,
            ], true );

            if ( is_wp_error( $updated ) ) {
                $errors[] = "Post {$post_id}: " . $updated->get_error_message();
                continue;
            }

            $new_url = get_permalink( $post_id );

            $redirected = false;
            if ( $old_url && $new_url && $old_url !== $new_url ) {
                $this->store_redirect( $old_url, (string) $new_url, $post_id );
                $this->title_opt_update_internal_links( $old_url, (string) $new_url );
                $redirects++;
                $redirected = true;
            }

            $post_modified = (string) get_post_field( 'post_modified', $post_id );
            update_post_meta( $post_id, self::META_TITLE_OPT_STATUS,      'applied' );
            update_post_meta( $post_id, self::META_TITLE_OPT_ANALYSED_AT, $post_modified ?: current_time( 'mysql' ) );
            $applied++;
            $applied_list[] = [
                'id'         => $post_id,
                'title'      => $suggested,
                'redirected' => $redirected,
            ];
        }

        wp_send_json_success( [
            'applied'      => $applied,
            'redirects'    => $redirects,
            'errors'       => $errors,
            'applied_list' => $applied_list,
        ] );
    }

    // =========================================================================
    // Background queue — server-side batch analysis
    // =========================================================================

    /**
     * AJAX: enqueue all unanalysed (or all, if force) posts for background processing.
     *
     * @since 4.20.14
     * @return void
     */
    public function ajax_title_queue_start(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $force = ! empty( $_POST['force'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        $args = [
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ];
        if ( ! $force ) {
            $args['meta_query'] = [ [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                'key'     => self::META_TITLE_OPT_SUGGESTED,
                'compare' => 'NOT EXISTS',
            ] ];
        }

        $ids = get_posts( $args );
        if ( empty( $ids ) ) {
            wp_send_json_success( [ 'queued' => 0, 'message' => 'Nothing to queue.' ] );
            return;
        }

        update_option( self::OPT_TITLE_QUEUE, array_values( $ids ), false );
        update_option( self::OPT_TITLE_JOB, [
            'running'     => true,
            'total'       => count( $ids ),
            'processed'   => 0,
            'started_at'  => current_time( 'mysql' ),
            'force'       => $force,
            'last_post_id' => 0,
            'last_title'   => '',
            'last_scores'  => [],
            'last_error'   => '',
        ], false );

        wp_clear_scheduled_hook( self::CRON_TITLE_OPT );
        wp_schedule_single_event( time(), self::CRON_TITLE_OPT );
        spawn_cron();

        wp_send_json_success( [ 'queued' => count( $ids ), 'total' => count( $ids ) ] );
    }

    /**
     * AJAX: stop the background queue immediately.
     *
     * @since 4.20.14
     * @return void
     */
    public function ajax_title_queue_stop(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        delete_option( self::OPT_TITLE_QUEUE );
        $job            = (array) get_option( self::OPT_TITLE_JOB, [] );
        $job['running'] = false;
        update_option( self::OPT_TITLE_JOB, $job, false );
        wp_clear_scheduled_hook( self::CRON_TITLE_OPT );

        wp_send_json_success( [ 'stopped' => true, 'processed' => (int) ( $job['processed'] ?? 0 ) ] );
    }

    /**
     * AJAX: return current background queue progress.
     *
     * @since 4.20.14
     * @return void
     */
    public function ajax_title_queue_status(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $job   = (array) get_option( self::OPT_TITLE_JOB,   [] );
        $queue = (array) get_option( self::OPT_TITLE_QUEUE, [] );

        wp_send_json_success( [
            'running'     => (bool) ( $job['running']     ?? false ),
            'total'       => (int)  ( $job['total']       ?? 0 ),
            'processed'   => (int)  ( $job['processed']   ?? 0 ),
            'remaining'   => count( $queue ),
            'last_post_id' => (int)    ( $job['last_post_id'] ?? 0 ),
            'last_title'   => (string) ( $job['last_title']   ?? '' ),
            'last_scores'  => $job['last_scores'] ?? [],
            'last_error'   => (string) ( $job['last_error']   ?? '' ),
        ] );
    }

    /**
     * WP Cron handler: process the entire queue in one loop.
     *
     * Loops until the queue is empty or a stop signal is received.
     * Re-reads job status from DB each iteration so the Stop button works.
     * Chaining single events was broken because spawn_cron() blocks while
     * the doing_cron lock is held by the currently-running job.
     *
     * @since 4.20.14
     * @return void
     */
    public function cron_title_opt_process(): void {
        @ignore_user_abort( true ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
        if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- required to prevent PHP timeout during long queue processing

        while ( true ) {
            // Re-read on every iteration so Stop button is respected immediately.
            $queue = (array) get_option( self::OPT_TITLE_QUEUE, [] );
            $job   = (array) get_option( self::OPT_TITLE_JOB,   [] );

            if ( empty( $queue ) || empty( $job['running'] ) ) {
                $job['running'] = false;
                update_option( self::OPT_TITLE_JOB, $job, false );
                delete_option( self::OPT_TITLE_QUEUE );
                return;
            }

            $post_id = (int) array_shift( $queue );
            update_option( self::OPT_TITLE_QUEUE, array_values( $queue ), false );

            try {
                update_post_meta( $post_id, self::META_TITLE_OPT_ORIGINAL, get_the_title( $post_id ) );

                $result = $this->call_ai_optimise_title( $post_id );

                update_post_meta( $post_id, self::META_TITLE_OPT_SUGGESTED,    $result['suggested_title'] );
                update_post_meta( $post_id, self::META_TITLE_OPT_KEYWORDS,     $result['keywords'] );
                update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_BEFORE, $result['score_before'] );
                update_post_meta( $post_id, self::META_TITLE_OPT_SCORE_AFTER,  $result['score_after'] );
                update_post_meta( $post_id, self::META_TITLE_OPT_NOTES,        $result['notes'] );
                update_post_meta( $post_id, self::META_TITLE_OPT_ANALYSED_AT,  current_time( 'mysql' ) );
                if ( get_post_meta( $post_id, self::META_TITLE_OPT_STATUS, true ) !== 'applied' ) {
                    update_post_meta( $post_id, self::META_TITLE_OPT_STATUS, 'suggested' );
                }

                $job['processed']    = ( $job['processed'] ?? 0 ) + 1;
                $job['last_post_id'] = $post_id;
                $job['last_title']   = $result['suggested_title'];
                $job['last_scores']  = [ $result['score_before'], $result['score_after'] ];
                $job['last_at']      = current_time( 'mysql' );
                $job['last_error']   = '';
            } catch ( \Throwable $e ) {
                $job['last_error'] = "Post {$post_id}: " . $e->getMessage();
            }

            update_option( self::OPT_TITLE_JOB, $job, false );
        }
    }

    // =========================================================================
    // Internal link repair
    // =========================================================================

    /**
     * Replaces all occurrences of $old_url with $new_url in the post_content of
     * all published posts. Called whenever a slug change is applied so internal
     * links are kept in sync without relying on the redirect chain.
     *
     * @since 4.20.39
     * @param string $old_url Full old permalink (as returned by get_permalink()).
     * @param string $new_url Full new permalink.
     * @return int Number of posts whose content was updated.
     */
    private function title_opt_update_internal_links( string $old_url, string $new_url ): int {
        global $wpdb;
        if ( ! $old_url || $old_url === $new_url ) return 0;

        $like = '%' . $wpdb->esc_like( $old_url ) . '%';

        // Fetch affected IDs first so we can clear their object-cache entries after
        // the direct SQL update (required when a persistent cache like Redis is in use).
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $affected_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                $like
            )
        );

        if ( ! $affected_ids ) return 0;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_status = 'publish' AND post_content LIKE %s",
                $old_url,
                $new_url,
                $like
            )
        );

        // Purge object-cache entries so subsequent get_post() calls return fresh content.
        foreach ( $affected_ids as $post_id ) {
            clean_post_cache( (int) $post_id );
        }

        return $updated;
    }

    /**
     * AJAX: scan for internal links still pointing to old redirect sources.
     *
     * Checks every redirect entry against post_content to find published posts
     * that still contain old/redirected URLs. Returns a list of affected posts
     * without modifying anything.
     *
     * @since 4.20.49
     * @return void
     */
    public function ajax_title_scan_broken_links(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        global $wpdb;

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];

        $all_redirects = array_filter(
            $redirects,
            static fn( $r ) => ! empty( $r['from'] ) && ! empty( $r['to'] )
        );

        $broken_posts      = [];
        $redirects_checked = 0;

        foreach ( $all_redirects as $r ) {
            $old_url = trailingslashit( (string) home_url( $r['from'] ) );
            $new_url = trailingslashit( (string) $r['to'] );
            if ( ! $old_url || $old_url === $new_url ) continue;

            $redirects_checked++;
            $like = '%' . $wpdb->esc_like( $old_url ) . '%';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                    $like
                )
            );

            foreach ( $ids as $raw_id ) {
                $pid = (int) $raw_id;
                if ( ! isset( $broken_posts[ $pid ] ) ) {
                    clean_post_cache( $pid );
                    $post = get_post( $pid );
                    $broken_posts[ $pid ] = [
                        'post_id'    => $pid,
                        'post_title' => $post ? get_the_title( $pid ) : "Post #{$pid}",
                        'post_edit'  => $post ? (string) get_edit_post_link( $pid, 'raw' ) : '',
                        'old_urls'   => [],
                    ];
                }
                $broken_posts[ $pid ]['old_urls'][] = $old_url;
            }
        }

        wp_send_json_success( [
            'redirects_checked' => $redirects_checked,
            'broken_posts'      => array_values( $broken_posts ),
        ] );
    }

    /**
     * AJAX: retrospective internal-link repair.
     *
     * Iterates every redirect stored in cs_seo_redirects and replaces the old URL
     * with the new URL inside all published post_content. Run after
     * ajax_title_scan_broken_links() to apply the fixes.
     *
     * Safe to re-run — REPLACE() is a no-op when the search string is absent.
     *
     * @since 4.20.39
     * @return void
     */
    public function ajax_title_fix_internal_links(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];

        // Process all redirects that have valid from/to paths — both auto and manual.
        $all_redirects = array_filter(
            $redirects,
            static fn( $r ) => ! empty( $r['from'] ) && ! empty( $r['to'] )
        );

        $processed     = 0;
        $posts_updated = 0;

        foreach ( $all_redirects as $r ) {
            // WordPress permalinks always have a trailing slash; add one so the
            // REPLACE() finds an exact match rather than a partial-substring match
            // (which would leave a double-slash in the replaced URL).
            $old_url = trailingslashit( (string) home_url( $r['from'] ) );
            $new_url = trailingslashit( (string) $r['to'] );
            if ( ! $old_url || $old_url === $new_url ) continue;

            $posts_updated += $this->title_opt_update_internal_links( $old_url, $new_url );
            $processed++;
        }

        wp_send_json_success( [
            'processed'     => $processed,
            'posts_updated' => $posts_updated,
        ] );
    }
}
