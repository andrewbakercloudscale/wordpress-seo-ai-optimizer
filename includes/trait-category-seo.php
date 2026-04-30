<?php
/**
 * Category & taxonomy SEO — custom titles, meta descriptions, and intro content.
 *
 * Adds SEO fields to the WordPress category/tag edit screens and injects
 * the intro above the post list on archive pages via the_archive_description
 * filter (primary) and loop_start action (fallback for themes that skip it).
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.20.82
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Category_SEO {

    private static bool $cs_cat_intro_printed = false;

    // =========================================================================
    // Admin: taxonomy edit form fields
    // =========================================================================

    /**
     * Renders SEO fields on category/tag edit screens.
     *
     * @since 4.20.82
     * @param \WP_Term $term The term being edited.
     * @return void
     */
    public function cat_seo_edit_fields( \WP_Term $term ): void {
        $title = (string) get_term_meta( $term->term_id, self::META_TERM_TITLE, true );
        $desc  = (string) get_term_meta( $term->term_id, self::META_TERM_DESC,  true );
        $intro = (string) get_term_meta( $term->term_id, self::META_TERM_INTRO, true );
        $nonce = wp_create_nonce( 'cs_seo_term_save_' . $term->term_id );
        $ai_nonce = wp_create_nonce( 'cs_seo_nonce' );
        ?>
        <tr class="form-field cs-seo-term-divider">
            <th scope="row" colspan="2" style="padding-top:20px">
                <hr style="margin:0 0 4px">
                <strong style="font-size:13px;color:#1d2327">&#128269; CloudScale SEO</strong>
            </th>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="cs_seo_term_title"><?php esc_html_e( 'SEO Title', 'cloudscale-seo-ai-optimizer' ); ?></label>
            </th>
            <td>
                <input type="text" id="cs_seo_term_title" name="cs_seo_term_title"
                       value="<?php echo esc_attr( $title ); ?>" maxlength="70"
                       style="width:100%;max-width:600px" />
                <p class="description"><?php esc_html_e( 'Overrides the default "Category: Name" title in search results. Aim for 50–60 characters.', 'cloudscale-seo-ai-optimizer' ); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="cs_seo_term_desc"><?php esc_html_e( 'Meta Description', 'cloudscale-seo-ai-optimizer' ); ?></label>
            </th>
            <td>
                <textarea id="cs_seo_term_desc" name="cs_seo_term_desc"
                          rows="3" style="width:100%;max-width:600px"><?php echo esc_textarea( $desc ); ?></textarea>
                <p class="description">
                    <?php esc_html_e( 'Shown in search results. Aim for 140–160 characters.', 'cloudscale-seo-ai-optimizer' ); ?>
                    <span id="cs-seo-desc-count" style="color:#666"> (<?php echo esc_html( (string) mb_strlen( $desc ) ); ?> chars)</span>
                </p>
            </td>
        </tr>
        <tr class="form-field">
            <th scope="row">
                <label for="cs_seo_term_intro"><?php esc_html_e( 'Category Intro', 'cloudscale-seo-ai-optimizer' ); ?></label>
            </th>
            <td>
                <textarea id="cs_seo_term_intro" name="cs_seo_term_intro"
                          rows="7" style="width:100%;max-width:600px"><?php echo esc_textarea( $intro ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Displayed above posts on the category page. 2–3 paragraphs. Separate paragraphs with a blank line. Basic HTML allowed.', 'cloudscale-seo-ai-optimizer' ); ?></p>
                <p>
                    <button type="button" id="cs-seo-cat-ai-gen"
                            data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
                            data-nonce="<?php echo esc_attr( $ai_nonce ); ?>"
                            class="button">
                        &#x2728; <?php esc_html_e( 'Generate with AI', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                    <span id="cs-seo-cat-ai-status" style="margin-left:10px;color:#666;font-style:italic"></span>
                </p>
                <input type="hidden" name="cs_seo_term_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
            </td>
        </tr>
        <script>
        (function() {
            /* Meta description character counter */
            var descTA = document.getElementById('cs_seo_term_desc');
            var descCt = document.getElementById('cs-seo-desc-count');
            if (descTA && descCt) {
                descTA.addEventListener('input', function() {
                    var n = descTA.value.length;
                    descCt.textContent = ' (' + n + ' chars)';
                    descCt.style.color = (n >= 140 && n <= 160) ? '#00a32a' : (n > 160 ? '#cc0000' : '#666');
                });
            }

            /* AI Generate button */
            var btn    = document.getElementById('cs-seo-cat-ai-gen');
            var status = document.getElementById('cs-seo-cat-ai-status');
            if (!btn) return;
            btn.addEventListener('click', function() {
                btn.disabled = true;
                btn.textContent = '⟳ Generating…';
                status.textContent = '';
                var fd = new FormData();
                fd.append('action',  'cs_seo_cat_seo_ai_gen');
                fd.append('term_id', btn.dataset.termId);
                fd.append('nonce',   btn.dataset.nonce);
                fetch(ajaxurl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success) {
                            var r = data.data;
                            if (r.title) document.getElementById('cs_seo_term_title').value = r.title;
                            if (r.desc)  {
                                document.getElementById('cs_seo_term_desc').value = r.desc;
                                if (descTA) descTA.dispatchEvent(new Event('input'));
                            }
                            if (r.intro) document.getElementById('cs_seo_term_intro').value = r.intro;
                            status.textContent = '✓ Generated — review and save.';
                            status.style.color = '#00a32a';
                        } else {
                            status.textContent = '✗ ' + (data.data || 'Error');
                            status.style.color = '#cc0000';
                        }
                    })
                    .catch(function() {
                        status.textContent = '✗ Network error';
                        status.style.color = '#cc0000';
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.textContent = '✨ Generate with AI';
                    });
            });
        })();
        </script>
        <?php
    }

    // =========================================================================
    // Save taxonomy term SEO fields on native WP form submit
    // =========================================================================

    /**
     * Saves SEO term meta when a category/tag edit form is submitted.
     *
     * @since 4.20.82
     * @param int $term_id The term ID being updated.
     * @return void
     */
    public function cat_seo_save_fields( int $term_id ): void {
        if ( ! isset( $_POST['cs_seo_term_nonce'] ) ) return;
        $nonce = sanitize_text_field( wp_unslash( $_POST['cs_seo_term_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce, 'cs_seo_term_save_' . $term_id ) ) return;
        if ( ! current_user_can( 'manage_categories' ) ) return;

        $title = isset( $_POST['cs_seo_term_title'] )
            ? sanitize_text_field( wp_unslash( $_POST['cs_seo_term_title'] ) )
            : '';
        $desc  = isset( $_POST['cs_seo_term_desc'] )
            ? sanitize_textarea_field( wp_unslash( $_POST['cs_seo_term_desc'] ) )
            : '';
        $intro = isset( $_POST['cs_seo_term_intro'] )
            ? wp_kses_post( wp_unslash( $_POST['cs_seo_term_intro'] ) )
            : '';

        update_term_meta( $term_id, self::META_TERM_TITLE, $title );
        update_term_meta( $term_id, self::META_TERM_DESC,  $desc );
        update_term_meta( $term_id, self::META_TERM_INTRO, $intro );
    }

    // =========================================================================
    // Frontend: inject intro above posts
    // =========================================================================

    /**
     * Prepends the category SEO intro to the archive description.
     *
     * Primary injection path — fires when the theme calls the_archive_description().
     *
     * @since 4.20.82
     * @param string $description Existing archive description HTML.
     * @return string
     */
    public function cat_seo_archive_description( string $description ): string {
        if ( ! ( is_category() || is_tag() ) ) return $description;
        $term = get_queried_object();
        if ( ! ( $term instanceof \WP_Term ) ) return $description;
        $intro = trim( (string) get_term_meta( $term->term_id, self::META_TERM_INTRO, true ) );
        if ( ! $intro ) return $description;
        self::$cs_cat_intro_printed = true;
        return '<div class="cs-seo-cat-intro">' . wp_kses_post( $this->cs_format_intro( $intro ) ) . '</div>'
            . $description;
    }

    /**
     * Fallback intro injection: fires at the start of the main loop.
     *
     * Used for themes that do not call the_archive_description(). Skipped if
     * the intro was already output via cat_seo_archive_description().
     *
     * @since 4.20.82
     * @param \WP_Query $query The current WP_Query object.
     * @return void
     */
    public function cat_seo_loop_start_intro( \WP_Query $query ): void {
        if ( self::$cs_cat_intro_printed ) return;
        if ( ! $query->is_main_query() ) return;
        if ( ! ( is_category() || is_tag() ) ) return;
        $term = get_queried_object();
        if ( ! ( $term instanceof \WP_Term ) ) return;
        $intro = trim( (string) get_term_meta( $term->term_id, self::META_TERM_INTRO, true ) );
        if ( ! $intro ) return;
        self::$cs_cat_intro_printed = true;
        echo '<div class="cs-seo-cat-intro">' . wp_kses_post( $this->cs_format_intro( $intro ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post applied
    }

    /**
     * Wraps plain-text paragraphs in <p> tags when no block-level elements are present.
     *
     * @since 4.20.82
     * @param string $intro Raw intro text.
     * @return string HTML-ready intro.
     */
    private function cs_format_intro( string $intro ): string {
        if ( strpos( $intro, '<p' ) !== false || strpos( $intro, '<h' ) !== false ) {
            return $intro;
        }
        $paras = array_filter( array_map( 'trim', preg_split( '/\n{2,}/', $intro ) ) );
        return '<p>' . implode( '</p><p>', $paras ) . '</p>';
    }

    // =========================================================================
    // AJAX: AI generate title, meta desc, and intro for a term
    // =========================================================================

    /**
     * AJAX handler: uses AI to generate SEO title, meta description, and intro for a term.
     *
     * @since 4.20.82
     * @return void
     */
    public function ajax_cat_seo_ai_gen(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $term_id = isset( $_POST['term_id'] ) ? absint( wp_unslash( $_POST['term_id'] ) ) : 0;
        if ( ! $term_id ) { wp_send_json_error( 'Missing term_id' ); return; }

        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) ) { wp_send_json_error( 'Term not found' ); return; }

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
            : trim( (string) ( $this->ai_opts['anthropic_key'] ?? '' ) );
        $model    = $this->resolve_model( trim( (string) ( $this->ai_opts['model'] ?? '' ) ), $provider );

        if ( ! $key ) { wp_send_json_error( 'No API key configured' ); return; }

        // Gather recent post titles from this category
        $post_ids = get_posts( [
            'category'            => $term_id,
            'posts_per_page'      => 15,
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ] );
        _prime_post_caches( $post_ids, false, false );
        $titles = array_map( 'get_the_title', $post_ids );

        $site_name   = get_bloginfo( 'name' );
        $term_name   = $term->name;
        $term_count  = (int) $term->count;
        $native_desc = trim( (string) $term->description );
        $titles_text = implode( "\n- ", $titles );

        $system = 'You are an SEO expert writing metadata and intro content for a WordPress category page. '
            . 'Write in the first-person voice of the site owner — expert, practical, direct. '
            . 'Respond ONLY with a valid JSON object. No markdown fences, no explanation.';

        $user_msg = "Site: {$site_name}\n"
            . "Category: {$term_name}\n"
            . "Post count: {$term_count}\n"
            . ( $native_desc ? "Existing category description: {$native_desc}\n" : '' )
            . ( $titles_text ? "Recent posts:\n- {$titles_text}\n" : '' )
            . "\nGenerate the following three fields for this category page. Return ONLY a JSON object:\n"
            . "- \"title\": SEO page title, 50–60 chars, keyword-rich, no brand suffix\n"
            . "- \"desc\": meta description, 140–160 chars, summarises the category value with a soft call to action\n"
            . "- \"intro\": 2–3 paragraph intro (separate paragraphs with \\n\\n). Establishes what the category covers, "
            . "why the reader should explore it, and what they will learn. Natural language. No bullet points. No hype.";

        try {
            $raw    = $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 800 );
            $clean  = trim( (string) preg_replace( '/^```(?:json)?\s*/i', '', preg_replace( '/```\s*$/i', '', trim( $raw ) ) ) );
            $result = json_decode( $clean, true );
            if ( ! is_array( $result ) || empty( $result['title'] ) ) {
                wp_send_json_error( 'AI returned unexpected format. Raw: ' . substr( $raw, 0, 200 ) );
                return;
            }
            wp_send_json_success( [
                'title' => sanitize_text_field( (string) ( $result['title'] ?? '' ) ),
                'desc'  => sanitize_textarea_field( (string) ( $result['desc']  ?? '' ) ),
                'intro' => wp_kses_post( (string) ( $result['intro'] ?? '' ) ),
            ] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( 'AI error: ' . $e->getMessage() );
        }
    }

    // =========================================================================
    // AJAX: load all categories with their SEO meta (for admin overview)
    // =========================================================================

    /**
     * AJAX handler: returns all categories with their current SEO meta values.
     *
     * @since 4.20.82
     * @return void
     */
    public function ajax_cat_seo_list(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $cats = get_categories( [ 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC' ] );
        $rows = [];
        foreach ( $cats as $cat ) {
            if ( strtolower( $cat->slug ) === 'uncategorized' ) continue;
            $tid    = (int) $cat->term_id;
            $rows[] = [
                'term_id'  => $tid,
                'name'     => $cat->name,
                'slug'     => $cat->slug,
                'count'    => (int) $cat->count,
                'edit_url' => (string) get_edit_term_link( $tid, 'category' ),
                'view_url' => (string) get_term_link( $tid, 'category' ),
                'title'    => (string) get_term_meta( $tid, self::META_TERM_TITLE, true ),
                'desc'     => (string) get_term_meta( $tid, self::META_TERM_DESC,  true ),
                'has_intro' => ( trim( (string) get_term_meta( $tid, self::META_TERM_INTRO, true ) ) !== '' ),
            ];
        }
        wp_send_json_success( $rows );
    }
}
