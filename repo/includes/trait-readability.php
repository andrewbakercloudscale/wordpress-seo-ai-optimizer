<?php
/**
 * Readability scoring — pure-PHP analysis of sentence length, heading density,
 * and passive-voice rate. No AI call required.
 *
 * @package Cs_Seo_Plugin
 * @since   4.19.126
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Readability {

    // =========================================================================
    // Core scoring
    // =========================================================================

    /**
     * Analyses post HTML and returns a readability score plus three sub-metrics.
     *
     * Sub-metrics stored in the returned array:
     *   sentence_len  — average words per sentence (float)
     *   heading_density — average words between consecutive H2/H3 headings (int|null)
     *   passive_pct   — percentage of sentences containing a passive-voice pattern (int)
     *   word_count    — total word count of the plain-text content (int)
     *   score         — combined 0-100 score (int|null — null if content too short)
     *   generated_at  — Unix timestamp of when the score was computed (int)
     *
     * @since 4.19.126
     * @param string $html Raw post HTML (post_content).
     * @return array{score:int|null,sentence_len:float|null,heading_density:int|null,passive_pct:int,word_count:int,generated_at:int}
     */
    private function score_readability( string $html ): array {
        $empty = [
            'score'           => null,
            'sentence_len'    => null,
            'heading_density' => null,
            'passive_pct'     => 0,
            'word_count'      => 0,
            'generated_at'    => time(),
        ];

        // Count H2/H3 headings before stripping tags.
        preg_match_all( '/<h[23][^>]*>/i', $html, $h_matches );
        $heading_count = count( $h_matches[0] );

        // Plain text.
        $text = wp_strip_all_tags( $html );
        $text = (string) preg_replace( '/\s+/', ' ', trim( $text ) );

        $word_count = str_word_count( $text );
        if ( $word_count < 50 ) {
            return array_merge( $empty, [ 'word_count' => $word_count ] );
        }

        // ── Sentence length ───────────────────────────────────────────────────
        // Split on sentence-ending punctuation followed by whitespace or EOS.
        $raw_sentences = (array) preg_split( '/[.!?]+(?:\s|$)/', $text, -1, PREG_SPLIT_NO_EMPTY );
        // Keep only segments that look like real sentences (≥ 4 words).
        $sentences = array_values(
            array_filter( $raw_sentences, static fn( string $s ): bool => str_word_count( $s ) >= 4 )
        );
        $sentence_count = max( 1, count( $sentences ) );
        $avg_sentence   = round( $word_count / $sentence_count, 1 );

        // ── Heading density ───────────────────────────────────────────────────
        // words_per_heading = average words between headings (or null for short posts).
        if ( $word_count >= 300 && $heading_count > 0 ) {
            $words_per_heading = (int) round( $word_count / $heading_count );
        } else {
            $words_per_heading = null; // neutral — post is short or has no headings
        }

        // ── Passive voice ─────────────────────────────────────────────────────
        // Heuristic: "to-be" verb immediately followed by a past participle.
        $to_be     = 'am|is|are|was|were|be|been|being';
        $pp_suffix = '\w+ed';     // regular past participles
        $pp_irreg  = 'written|spoken|given|taken|made|done|said|shown|known|seen'
                    . '|used|found|called|built|held|led|brought|sent|told|felt'
                    . '|lost|kept|set|left|put|cut|read|run|drawn|driven|fallen'
                    . '|grown|stolen|thrown|worn|broken|chosen|frozen|hidden'
                    . '|ridden|risen|shaken|spoken|torn|woken|born|beaten|begun'
                    . '|bitten|blown|caught|dealt|dreamt|drunk|fed|fought|flown'
                    . '|forbidden|forgotten|gone|hung|hurt|kept|meant|met|paid'
                    . '|proven|quit|rung|sat|shot|shut|slept|slid|sold|spent'
                    . '|split|spread|stood|struck|swum|taught|thought|understood'
                    . '|won|wound';
        $passive_re = '/\b(?:' . $to_be . ')\s+(?:being\s+)?(?:\w+ly\s+)?(?:' . $pp_irreg . '|' . $pp_suffix . ')\b/i';

        $passive_count = 0;
        foreach ( $sentences as $s ) {
            if ( preg_match( $passive_re, $s ) ) {
                $passive_count++;
            }
        }
        $passive_pct = (int) round( $passive_count / $sentence_count * 100 );

        // ── Sub-scores ────────────────────────────────────────────────────────
        // Sentence length score (40 % weight). Ideal ≤ 15 words.
        if ( $avg_sentence <= 12 ) {
            $s_score = 100;
        } elseif ( $avg_sentence <= 18 ) {
            $s_score = (int) round( 100 - ( ( $avg_sentence - 12 ) / 6 ) * 20 );
        } elseif ( $avg_sentence <= 25 ) {
            $s_score = (int) round( 80 - ( ( $avg_sentence - 18 ) / 7 ) * 30 );
        } elseif ( $avg_sentence <= 35 ) {
            $s_score = (int) round( 50 - ( ( $avg_sentence - 25 ) / 10 ) * 30 );
        } else {
            $s_score = 10;
        }

        // Heading density score (35 % weight).
        if ( null === $words_per_heading ) {
            $h_score = 75; // neutral for short posts / no headings needed
        } elseif ( $words_per_heading <= 200 ) {
            $h_score = 90; // very frequent — slightly penalise
        } elseif ( $words_per_heading <= 350 ) {
            $h_score = 100; // ideal zone
        } elseif ( $words_per_heading <= 500 ) {
            $h_score = (int) round( 100 - ( ( $words_per_heading - 350 ) / 150 ) * 25 );
        } elseif ( $words_per_heading <= 800 ) {
            $h_score = (int) round( 75 - ( ( $words_per_heading - 500 ) / 300 ) * 35 );
        } else {
            $h_score = 25;
        }
        // No headings in a long post — override to 15.
        if ( $word_count >= 300 && $heading_count === 0 ) {
            $h_score = 15;
        }

        // Passive voice score (25 % weight). Target < 5 %.
        if ( $passive_pct <= 5 ) {
            $p_score = 100;
        } elseif ( $passive_pct <= 10 ) {
            $p_score = (int) round( 100 - ( ( $passive_pct - 5 ) / 5 ) * 20 );
        } elseif ( $passive_pct <= 20 ) {
            $p_score = (int) round( 80 - ( ( $passive_pct - 10 ) / 10 ) * 40 );
        } elseif ( $passive_pct <= 30 ) {
            $p_score = (int) round( 40 - ( ( $passive_pct - 20 ) / 10 ) * 20 );
        } else {
            $p_score = 15;
        }

        $score = (int) round( $s_score * 0.40 + $h_score * 0.35 + $p_score * 0.25 );

        return [
            'score'           => $score,
            'sentence_len'    => $avg_sentence,
            'heading_density' => $words_per_heading,
            'passive_pct'     => $passive_pct,
            'word_count'      => $word_count,
            'generated_at'    => time(),
        ];
    }

    // =========================================================================
    // Storage helpers
    // =========================================================================

    /**
     * Scores a post and writes the result to post meta.
     *
     * @since 4.19.126
     * @param int $post_id Post ID.
     * @return array The readability result array (same shape as score_readability()).
     */
    public function calculate_and_store_readability( int $post_id ): array {
        $post = get_post( $post_id );
        if ( ! $post ) return [];

        $result = $this->score_readability( (string) $post->post_content );
        if ( ! empty( $result ) ) {
            update_post_meta( $post_id, self::META_READABILITY, wp_json_encode( $result ) );
        }
        return $result;
    }

    /**
     * Recalculates readability on every non-autosave post save.
     *
     * @since 4.19.126
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @return void
     */
    public function on_save_post_readability( int $post_id, WP_Post $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( wp_is_post_revision( $post_id ) ) return;
        if ( ! in_array( $post->post_type, [ 'post', 'page' ], true ) ) return;

        $this->calculate_and_store_readability( $post_id );
    }

    // =========================================================================
    // AJAX
    // =========================================================================

    /**
     * AJAX handler: recalculates and stores readability for a single post.
     *
     * @since 4.19.126
     * @return void
     */
    public function ajax_readability_score_one(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via check_ajax_referer()
        if ( ! $post_id ) wp_send_json_error( 'Missing post_id' );

        $result = $this->calculate_and_store_readability( $post_id );
        if ( empty( $result ) || null === $result['score'] ) {
            wp_send_json_error( 'Content too short to score (need ≥ 50 words)' );
        }
        wp_send_json_success( $result );
    }

    // =========================================================================
    // Auto-pipeline step
    // =========================================================================

    /**
     * Pipeline step: calculate and store readability for a newly published post.
     *
     * @since 4.19.126
     * @param int $post_id Post ID.
     * @return void
     */
    private function auto_step_readability( int $post_id ): void {
        $this->calculate_and_store_readability( $post_id );
    }
}
