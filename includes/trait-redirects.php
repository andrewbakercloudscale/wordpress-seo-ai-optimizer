<?php
/**
 * Automatic 301 redirects on post-slug rename.
 *
 * When enabled, captures the old permalink before a slug change and stores a
 * redirect mapping from the old path to the new URL. A template_redirect hook
 * serves those redirects on any 404 that matches a stored path.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.19.126
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Redirects {

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    /**
     * Registers all redirect hooks. Called from __construct.
     *
     * @since 4.19.126
     * @return void
     */
    private function init_redirects(): void {
        add_action( 'pre_post_update',    [$this, 'redirect_capture_old_url'],  10, 2 );
        add_action( 'post_updated',       [$this, 'redirect_on_slug_change'],   10, 3 );

        if ( ! empty( $this->opts['enable_redirects'] ) ) {
            // Priority 0: must fire before cs_pcr_maybe_custom_404 (priority 1)
            // which renders a custom 404 page and exits, blocking our redirect.
            add_action( 'template_redirect', [$this, 'redirect_serve'], 0 );
        }
    }

    // -------------------------------------------------------------------------
    // Slug-change detection
    // -------------------------------------------------------------------------

    /**
     * Captures the current permalink before a post is saved so we can compare
     * after the update and record a redirect if the slug changed.
     *
     * @since 4.19.126
     * @param int   $post_id Post ID being updated.
     * @param array $data    New post data (unused here).
     * @return void
     */
    public function redirect_capture_old_url( int $post_id, array $data ): void {
        if ( empty( $this->opts['enable_redirects'] ) ) return;

        $post = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) return;

        $post_type = get_post_type_object( $post->post_type );
        if ( ! $post_type || ! $post_type->public ) return;

        set_transient( 'cs_seo_old_perm_' . $post_id, get_permalink( $post_id ), 120 );
    }

    /**
     * After a post is saved, if the slug changed, records a 301 redirect from
     * the old URL path to the new permalink.
     *
     * @since 4.19.126
     * @param int       $post_id     Post ID.
     * @param \WP_Post  $post_after  Post object after the update.
     * @param \WP_Post  $post_before Post object before the update.
     * @return void
     */
    public function redirect_on_slug_change( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
        $old_url = get_transient( 'cs_seo_old_perm_' . $post_id );
        delete_transient( 'cs_seo_old_perm_' . $post_id );

        if ( ! $old_url ) return;
        if ( $post_before->post_name === $post_after->post_name ) return;
        if ( 'publish' !== $post_after->post_status ) return;

        $new_url = get_permalink( $post_id );
        if ( ! $new_url || $old_url === $new_url ) return;

        $this->store_redirect( $old_url, $new_url, $post_id );
    }

    // -------------------------------------------------------------------------
    // Storage
    // -------------------------------------------------------------------------

    /**
     * Persists a redirect mapping to the wp_options store.
     *
     * @since 4.19.126
     * @param string $from_url Full old URL.
     * @param string $to_url   Full new URL.
     * @param int    $post_id  Associated post ID.
     * @return void
     */
    private function store_redirect( string $from_url, string $to_url, int $post_id ): void {
        $from_path = (string) wp_parse_url( $from_url, PHP_URL_PATH );
        if ( ! $from_path ) return;

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];

        // Remove any pre-existing entry for this source path.
        $redirects = array_values(
            array_filter( $redirects, static fn( $r ) => ( $r['from'] ?? '' ) !== $from_path )
        );

        // Chain-squash: any existing redirect whose destination was the old URL
        // should now point directly to the new URL, avoiding multi-hop chains.
        // e.g. /original/ → /first/ becomes /original/ → /second/ when /first/ → /second/ is added.
        $from_path_trimmed = rtrim( $from_path, '/' );
        foreach ( $redirects as &$r ) {
            if ( empty( $r['to'] ) ) continue;
            $existing_to_path = rtrim( (string) wp_parse_url( $r['to'], PHP_URL_PATH ), '/' );
            if ( $existing_to_path === $from_path_trimmed ) {
                $r['to'] = $to_url;
            }
        }
        unset( $r );

        $redirects[] = [
            'from'     => $from_path,
            'to'       => $to_url,
            'post_id'  => $post_id,
            'created'  => time(),
            'hits'     => 0,
            'last_hit' => null,
        ];

        update_option( 'cs_seo_redirects', $redirects, false );
    }

    // -------------------------------------------------------------------------
    // Serve redirects
    // -------------------------------------------------------------------------

    /**
     * On a WordPress 404, checks stored redirects and issues a 301 if matched.
     * Records the hit count and last-hit timestamp before redirecting.
     *
     * @since 4.19.126
     * @return void
     */
    public function redirect_serve(): void {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only for path comparison, never output.
        $request_path = isset( $_SERVER['REQUEST_URI'] )
            ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
            : '';

        // Fire for any path with a configured redirect — not just 404s.
        // Slug-change redirects also benefit from this: if a page was recreated at the old slug, the redirect still fires.
        if ( is_admin() || wp_doing_ajax() ) return;

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( empty( $redirects ) ) return;

        if ( ! $request_path ) return;

        $request_trimmed = rtrim( $request_path, '/' );

        foreach ( $redirects as $i => $r ) {
            if ( ! isset( $r['from'], $r['to'] ) ) continue;
            if ( rtrim( $r['from'], '/' ) === $request_trimmed ) {
                $redirects[ $i ]['hits']     = (int) ( $r['hits'] ?? 0 ) + 1;
                $redirects[ $i ]['last_hit'] = time();
                update_option( 'cs_seo_redirects', $redirects, false );
                // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- redirect manager intentionally supports external destinations; destination is admin-controlled and validated with esc_url_raw() at write time
                wp_redirect( esc_url_raw( $r['to'] ), 301 );
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    /**
     * AJAX: deletes the redirect matching the submitted 'from' path.
     *
     * @since 4.19.126
     * @return void
     */
    public function ajax_delete_redirect(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        $from      = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
        $redirects = get_option( 'cs_seo_redirects', [] );
        $redirects = array_values(
            array_filter( $redirects, static fn( $r ) => ( $r['from'] ?? '' ) !== $from )
        );
        update_option( 'cs_seo_redirects', $redirects, false );
        wp_send_json_success();
    }

    /**
     * AJAX: adds or updates a manually entered redirect.
     *
     * @since 4.19.126
     * @return void
     */
    public function ajax_add_redirect(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        $from = sanitize_text_field( wp_unslash( $_POST['from'] ?? '' ) );
        $to   = esc_url_raw( wp_unslash( $_POST['to'] ?? '' ) );

        // Ensure from starts with /
        if ( ! $from || '/' !== $from[0] ) {
            wp_send_json_error( __( '"From" must be an absolute path starting with /.', 'cloudscale-seo-ai-optimizer' ) );
        }
        if ( ! $to ) {
            wp_send_json_error( __( '"To" URL is required.', 'cloudscale-seo-ai-optimizer' ) );
        }

        $from      = rtrim( $from, '/' ) ?: '/';
        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];

        // Remove any pre-existing entry for this source path.
        $redirects = array_values(
            array_filter( $redirects, static fn( $r ) => rtrim( $r['from'] ?? '', '/' ) !== rtrim( $from, '/' ) )
        );

        $new_entry = [
            'from'     => $from,
            'to'       => $to,
            'post_id'  => 0,
            'created'  => time(),
            'hits'     => 0,
            'last_hit' => null,
            'manual'   => true,
        ];
        $redirects[] = $new_entry;
        update_option( 'cs_seo_redirects', $redirects, false );

        wp_send_json_success( $new_entry );
    }

    /**
     * AJAX: deletes all stored redirects.
     *
     * @since 4.19.126
     * @return void
     */
    public function ajax_clear_redirects(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        delete_option( 'cs_seo_redirects' );
        wp_send_json_success();
    }

    /**
     * AJAX: deletes redirects not hit within the last 90 days.
     *
     * Keeps a redirect if it was hit within the last 90 days.
     * Never-hit redirects created within the last 90 days are also kept
     * (they may be new and not yet crawled). Never-hit redirects older
     * than 90 days are pruned as stale.
     *
     * @since 4.20.49
     * @return void
     */
    public function ajax_prune_unused_redirects(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        $cutoff    = time() - 90 * DAY_IN_SECONDS;
        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];

        $before    = count( $redirects );
        $redirects = array_values( array_filter( $redirects, static function ( $r ) use ( $cutoff ) {
            if ( empty( $r['last_hit'] ) ) {
                // Never hit — keep only if created within the last 90 days.
                return ! empty( $r['created'] ) && (int) $r['created'] >= $cutoff;
            }
            // Hit at least once — keep if the last hit was within 90 days.
            return (int) $r['last_hit'] >= $cutoff;
        } ) );

        $pruned = $before - count( $redirects );
        update_option( 'cs_seo_redirects', $redirects, false );
        wp_send_json_success( [ 'pruned' => $pruned ] );
    }

    /**
     * AJAX: identifies redirect chains without modifying anything.
     *
     * Returns every redirect whose destination URL is itself the source of
     * another redirect (i.e. A→B where B→C exists). Read-only — use
     * ajax_squash_redirect_chains() to fix the chains.
     *
     * @since 4.20.49
     * @return void
     */
    public function ajax_analyse_redirect_chains(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) || empty( $redirects ) ) {
            wp_send_json_success( [ 'chains' => [], 'count' => 0 ] );
            return;
        }

        // Build lookup: normalised-from-path → destination URL.
        $map = [];
        foreach ( $redirects as $r ) {
            if ( ! empty( $r['from'] ) && ! empty( $r['to'] ) ) {
                $map[ rtrim( $r['from'], '/' ) ] = $r['to'];
            }
        }

        $chains = [];
        foreach ( $redirects as $r ) {
            if ( empty( $r['from'] ) || empty( $r['to'] ) ) continue;
            $dest_path = rtrim( (string) wp_parse_url( $r['to'], PHP_URL_PATH ), '/' );
            if ( isset( $map[ $dest_path ] ) ) {
                // This redirect's destination is itself a redirect source — it's a chain.
                $chains[] = [
                    'from'        => $r['from'],
                    'to'          => $r['to'],
                    'resolves_to' => $map[ $dest_path ],
                ];
            }
        }

        wp_send_json_success( [ 'chains' => $chains, 'count' => count( $chains ) ] );
    }

    /**
     * AJAX: collapses redirect chains so A→B→C becomes A→C.
     *
     * Iterates every stored redirect and resolves its destination by following
     * the chain until it reaches a URL that is not itself a redirect source.
     * Detects cycles with a visited-set guard. Updates only entries whose
     * destination actually changed.
     *
     * @since 4.20.49
     * @return void
     */
    public function ajax_squash_redirect_chains(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) || empty( $redirects ) ) {
            wp_send_json_success( [ 'squashed' => 0 ] );
            return;
        }

        // Build a lookup map: normalised-from-path → destination URL.
        $map = [];
        foreach ( $redirects as $r ) {
            if ( ! empty( $r['from'] ) && ! empty( $r['to'] ) ) {
                $map[ rtrim( $r['from'], '/' ) ] = $r['to'];
            }
        }

        $squashed = 0;
        foreach ( $redirects as &$r ) {
            if ( empty( $r['from'] ) || empty( $r['to'] ) ) continue;
            $visited = [];
            $current = $r['to'];
            for ( $i = 0; $i < 20; $i++ ) {
                $path = rtrim( (string) wp_parse_url( $current, PHP_URL_PATH ), '/' );
                if ( isset( $visited[ $path ] ) ) break; // cycle — stop
                $visited[ $path ] = true;
                if ( ! isset( $map[ $path ] ) ) break;   // end of chain
                $current = $map[ $path ];
            }
            if ( $current !== $r['to'] ) {
                $r['to'] = $current;
                $squashed++;
            }
        }
        unset( $r );

        update_option( 'cs_seo_redirects', $redirects, false );
        wp_send_json_success( [ 'squashed' => $squashed ] );
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    /**
     * Renders the Redirects settings tab content.
     *
     * @since 4.19.126
     * @return void
     */
    private function render_redirects_tab(): void {
        $o         = $this->opts;
        $enabled   = ! empty( $o['enable_redirects'] );
        $redirects = get_option( 'cs_seo_redirects', [] );
        if ( ! is_array( $redirects ) ) $redirects = [];
        $nonce = wp_create_nonce( 'cs_seo_nonce' );
        ?>
        <div class="ab-zone-card ab-card-redirects">
        <div class="ab-zone-header" style="justify-content:space-between">
            <span><span class="ab-zone-icon">🔀</span> <?php esc_html_e( 'Automatic Redirects', 'cloudscale-seo-ai-optimizer' ); ?></span>
            <span style="display:flex;align-items:center;gap:8px;">
                <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-redirects" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                <?php $this->explain_btn( 'redirects', '🔀 Automatic Redirects — How it works', [
                    ['rec' => '✅ Recommended', 'name' => 'Enable automatic redirects', 'desc' => 'When a published post or page slug is renamed, the old URL is automatically captured and stored as a 301 redirect. Visitors and search engines following the old URL are sent to the new location. Without this, any link pointing to the old slug — from Google, other sites, or your own internal links — returns a 404.'],
                    ['rec' => '✅ Recommended', 'name' => 'Why 301 and not 302', 'desc' => 'A 301 is a permanent redirect. It tells Google to transfer all ranking signals (PageRank, backlinks, cached content) from the old URL to the new one. A 302 is temporary — Google keeps the old URL in its index and does not transfer signals. Always use 301 for slug renames.'],
                    ['rec' => 'ℹ️ Info', 'name' => 'When the redirect is created', 'desc' => 'The redirect is captured at the moment the post is saved in the editor. The old slug is recorded before the save, the new slug after — if they differ, a redirect entry is stored. The redirect is served on the next 404 request to the old path.'],
                    ['rec' => 'ℹ️ Info', 'name' => 'Hit counter and last hit', 'desc' => 'Each time a visitor or crawler follows a stored redirect, the hit counter increments and the last-hit timestamp is updated. Use this to see which old URLs are still receiving traffic and whether it is safe to eventually retire the redirect.'],
                    ['rec' => 'ℹ️ Info', 'name' => 'Manual redirects', 'desc' => 'Use the Add Manual Redirect form to redirect any path — not just renamed posts. This covers old image URLs (/wp-content/uploads/old.jpg), pages moved to a new domain, or any legacy path you want to send somewhere specific. The "from" field must be a path starting with /.'],
                    ['rec' => '🗑 Maintenance', 'name' => 'Delete Unused (3+ months)', 'desc' => 'Removes redirects that have not been hit in the last 90 days. Redirects created within the last 90 days are kept even if never hit — they may not yet have been crawled. Run this periodically to keep the redirect table clean. Only delete after confirming the old URLs are no longer referenced anywhere.'],
                    ['rec' => '🔗 Maintenance', 'name' => 'Analyse Chains / Remove Chained Redirects', 'desc' => 'A redirect chain is when A→B and B→C both exist. Every visitor following A takes two redirect hops instead of one — wasting a round trip and diluting SEO signals. Click "Analyse Chains" to scan the table and highlight any chains. The matching rows are highlighted amber in the table. Click "Remove X Chained Redirects" to collapse them all so A→C directly. No redirects are deleted — only the destination URLs are updated.'],
                    ['rec' => '⬜ Optional', 'name' => 'When to delete a redirect', 'desc' => 'Once the old URL has zero hits for several months and you are confident no external links still point to it, it is safe to delete. Keep redirects with active hits in place — removing them while traffic still arrives will send those visitors to a 404.'],
                ] ); ?>
            </span>
        </div>
        <div class="ab-zone-body">
            <p style="margin:16px 20px 0;color:#50575e"><?php esc_html_e( 'When enabled, a 301 redirect is automatically created whenever you rename a published post or page slug. Old URLs continue to work — visitors and search engines are redirected to the new location.', 'cloudscale-seo-ai-optimizer' ); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields( 'cs_seo_group' ); ?>
                <input type="hidden" name="<?php echo esc_attr( self::OPT ); ?>[enable_redirects]" value="0">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Enable automatic redirects', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( self::OPT ); ?>[enable_redirects]"
                                       value="1"<?php checked( $enabled ); ?>>
                                <?php esc_html_e( 'Create a 301 redirect when a post or page slug is changed', 'cloudscale-seo-ai-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Changes', 'cloudscale-seo-ai-optimizer' ); ?></button>
                </p>
            </form>

            <hr style="border:none;border-top:1px solid #dcdcde;margin:8px 20px 20px">

            <div style="padding:0 20px">
                <h3 style="margin-top:0"><?php esc_html_e( 'Add Manual Redirect', 'cloudscale-seo-ai-optimizer' ); ?></h3>
                <p style="color:#50575e;margin-top:0"><?php esc_html_e( 'Manually redirect any path — posts, pages, images, or any old URL — to a new destination.', 'cloudscale-seo-ai-optimizer' ); ?></p>
                <div style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;max-width:900px">
                    <div style="flex:1;min-width:200px">
                        <label style="display:block;margin-bottom:4px;font-weight:600;font-size:12px"><?php esc_html_e( 'From (old path)', 'cloudscale-seo-ai-optimizer' ); ?></label>
                        <input id="cs-add-from" type="text" class="regular-text" style="width:100%"
                               placeholder="/old-path/ or /wp-content/uploads/old-image.jpg">
                    </div>
                    <div style="flex:1;min-width:200px">
                        <label style="display:block;margin-bottom:4px;font-weight:600;font-size:12px"><?php esc_html_e( 'To (new URL)', 'cloudscale-seo-ai-optimizer' ); ?></label>
                        <input id="cs-add-to" type="text" class="regular-text" style="width:100%"
                               placeholder="https://example.com/new-page/">
                    </div>
                    <div style="padding-top:20px">
                        <button id="cs-add-redirect" class="button button-primary"
                                data-nonce="<?php echo esc_attr( $nonce ); ?>">
                            <?php esc_html_e( 'Add Redirect', 'cloudscale-seo-ai-optimizer' ); ?>
                        </button>
                    </div>
                </div>
                <p id="cs-add-redirect-msg" style="margin-top:8px;display:none"></p>
            </div>

            <div style="padding:0 20px 20px">
                <h3>
                    <?php esc_html_e( 'Stored Redirects', 'cloudscale-seo-ai-optimizer' ); ?>
                    <span style="font-weight:400;color:#999;font-size:13px;margin-left:6px">(<?php echo count( $redirects ); ?>)</span>
                </h3>
            <?php if ( empty( $redirects ) ) : ?>
                <p style="color:#999"><?php esc_html_e( 'No redirects stored yet. Rename a published post or page slug and an entry will appear here.', 'cloudscale-seo-ai-optimizer' ); ?></p>
            <?php else : ?>
                <p style="margin-bottom:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <button id="cs-prune-redirects" class="button"
                            style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        🗑 <?php esc_html_e( 'Delete Unused (3+ months)', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                    <button id="cs-analyse-chains" class="button"
                            style="background:#0073aa;border-color:#005177;color:#fff;font-weight:600"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        🔍 <?php esc_html_e( 'Analyse Chains', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                    <button id="cs-squash-redirects" class="button"
                            style="background:#6b3fa0;border-color:#4a2a7a;color:#fff;font-weight:600;display:none"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        🔗 <?php esc_html_e( 'Remove 0 Chained Redirects', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                    <button id="cs-clear-redirects" class="button"
                            style="background:#d63638;border-color:#d63638;color:#fff"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Delete All Redirects', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                    <span id="cs-redirects-action-msg" style="font-size:13px;font-weight:600;padding:2px 10px;border-radius:12px;display:none"></span>
                </p>
                <p style="margin-bottom:8px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                    <span style="font-size:13px;font-weight:600;color:#1d2327;"><?php esc_html_e( 'Filter:', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <button class="button cs-rtype-btn button-primary" data-filter="all"><?php esc_html_e( 'All', 'cloudscale-seo-ai-optimizer' ); ?></button>
                    <button class="button cs-rtype-btn" data-filter="manual"><?php esc_html_e( 'Manual', 'cloudscale-seo-ai-optimizer' ); ?></button>
                    <button class="button cs-rtype-btn" data-filter="auto"><?php esc_html_e( 'Automatic', 'cloudscale-seo-ai-optimizer' ); ?></button>
                </p>
                <p id="cs-chain-filter-bar" style="display:none;margin-bottom:10px;font-size:13px;color:#50575e">
                    <label style="cursor:pointer">
                        <input type="checkbox" id="cs-show-chains-only" style="margin-right:4px">
                        <?php esc_html_e( 'Show chained redirects only', 'cloudscale-seo-ai-optimizer' ); ?>
                    </label>
                </p>
                <div style="overflow-x:auto;max-width:100%">
                <table class="widefat striped" style="min-width:700px;table-layout:fixed;width:100%">
                    <thead>
                        <tr>
                            <th style="width:26%;word-break:break-all;white-space:normal"><?php esc_html_e( 'Old path (301 from)', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th style="width:5%;cursor:pointer;user-select:none" data-sort-col="hits" title="<?php esc_attr_e( 'Sort by Hits', 'cloudscale-seo-ai-optimizer' ); ?>"><?php esc_html_e( 'Hits', 'cloudscale-seo-ai-optimizer' ); ?> <span class="cs-sort-icon" style="opacity:0.5">&#8597;</span></th>
                            <th style="width:10%;white-space:nowrap;cursor:pointer;user-select:none" data-sort-col="last_hit" title="<?php esc_attr_e( 'Sort by Last hit', 'cloudscale-seo-ai-optimizer' ); ?>"><?php esc_html_e( 'Last hit', 'cloudscale-seo-ai-optimizer' ); ?> <span class="cs-sort-icon" style="opacity:0.5">&#8597;</span></th>
                            <th style="width:8%;white-space:nowrap;cursor:pointer;user-select:none" data-sort-col="created" title="<?php esc_attr_e( 'Sort by Created', 'cloudscale-seo-ai-optimizer' ); ?>"><?php esc_html_e( 'Created', 'cloudscale-seo-ai-optimizer' ); ?> <span class="cs-sort-icon" style="opacity:0.5">&#8597;</span></th>
                            <th style="width:30%;word-break:break-all;white-space:normal"><?php esc_html_e( 'New URL (301 to)', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th style="width:13%"><?php esc_html_e( 'Post', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th style="width:8%"></th>
                        </tr>
                    </thead>
                    <tbody id="cs-redirects-tbody">
                        <?php foreach ( $redirects as $r ) : ?>
                        <tr data-redirect-from="<?php echo esc_attr( $r['from'] ); ?>" data-redirect-type="<?php echo ! empty( $r['manual'] ) ? 'manual' : 'auto'; ?>">
                            <td style="word-break:break-all;white-space:normal"><a href="<?php echo esc_url( home_url( $r['from'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $r['from'] ); ?></code></a></td>
                            <td data-val="<?php echo isset( $r['hits'] ) ? (int) $r['hits'] : 0; ?>"><?php echo isset( $r['hits'] ) ? (int) $r['hits'] : 0; ?></td>
                            <td style="white-space:nowrap" data-val="<?php echo ! empty( $r['last_hit'] ) ? (int) $r['last_hit'] : 0; ?>"><?php echo ! empty( $r['last_hit'] ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $r['last_hit'] ) ) : '<span style="color:#999">—</span>'; ?></td>
                            <td style="white-space:nowrap" data-val="<?php echo ! empty( $r['created'] ) ? (int) $r['created'] : 0; ?>"><?php echo ! empty( $r['created'] ) ? esc_html( date_i18n( get_option( 'date_format' ), (int) $r['created'] ) ) : '—'; ?></td>
                            <td style="word-break:break-all;white-space:normal">
                                <a href="<?php echo esc_url( $r['to'] ); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html( $r['to'] ); ?>
                                </a>
                            </td>
                            <td><?php
                                if ( ! empty( $r['manual'] ) ) {
                                    echo '<span style="font-size:11px;background:#e0f0ff;color:#0073aa;padding:1px 6px;border-radius:3px;font-weight:600">' . esc_html__( 'Manual', 'cloudscale-seo-ai-optimizer' ) . '</span>';
                                } else {
                                    $post = get_post( (int)( $r['post_id'] ?? 0 ) );
                                    if ( $post ) {
                                        echo '<a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html( $post->post_title ) . '</a>';
                                    } else {
                                        echo '—';
                                    }
                                }
                            ?></td>
                            <td>
                                <button class="button cs-del-redirect"
                                        data-from="<?php echo esc_attr( $r['from'] ); ?>"
                                        data-nonce="<?php echo esc_attr( $nonce ); ?>">
                                    <?php esc_html_e( 'Delete', 'cloudscale-seo-ai-optimizer' ); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div><!-- /overflow-x scroll wrapper -->
                <div id="cs-redirects-pager" style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;margin-top:12px;"></div>
            <?php endif; ?>
            </div><!-- /stored redirects -->
        </div><!-- /ab-zone-body -->
        </div><!-- /ab-zone-card ab-card-redirects -->
        <?php ob_start(); ?>
        (function () {
            var addBtn = document.getElementById('cs-add-redirect');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    var from  = (document.getElementById('cs-add-from').value || '').trim();
                    var to    = (document.getElementById('cs-add-to').value || '').trim();
                    var msg   = document.getElementById('cs-add-redirect-msg');
                    var nonce = addBtn.dataset.nonce;

                    msg.style.display = 'none';

                    if (!from || from[0] !== '/') {
                        msg.textContent = <?php echo wp_json_encode( __( '"From" must start with /  e.g. /old-page/ or /wp-content/uploads/old.jpg', 'cloudscale-seo-ai-optimizer' ) ); ?>;
                        msg.style.cssText = 'display:block;color:#d63638';
                        return;
                    }
                    if (!to) {
                        msg.textContent = <?php echo wp_json_encode( __( 'Please enter a destination URL.', 'cloudscale-seo-ai-optimizer' ) ); ?>;
                        msg.style.cssText = 'display:block;color:#d63638';
                        return;
                    }

                    addBtn.disabled = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_add_redirect&nonce=' + encodeURIComponent(nonce)
                            + '&from=' + encodeURIComponent(from)
                            + '&to='   + encodeURIComponent(to)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        addBtn.disabled = false;
                        if (!d.success) {
                            msg.textContent = d.data || <?php echo wp_json_encode( __( 'Error adding redirect.', 'cloudscale-seo-ai-optimizer' ) ); ?>;
                            msg.style.cssText = 'display:block;color:#d63638';
                            return;
                        }
                        // Insert new row into the widefat table; reload if table not yet rendered (empty state).
                        var tbody = document.getElementById('cs-redirects-tbody');
                        if (!tbody) { location.reload(); return; }
                        var r = d.data;
                        var createdTs  = r.created ? parseInt(r.created, 10) : 0;
                        var createdStr = createdTs ? new Date(createdTs * 1000).toLocaleDateString() : '—';
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><a href="' + encodeURI(window.location.origin + r.from) + '" target="_blank" rel="noopener"><code>' + escHtml(r.from) + '</code></a></td>'
                            + '<td data-val="0">0</td>'
                            + '<td data-val="0"><span style="color:#999">—</span></td>'
                            + '<td data-val="' + createdTs + '" style="white-space:nowrap">' + escHtml(createdStr) + '</td>'
                            + '<td><a href="' + escHtml(r.to) + '" target="_blank" rel="noopener">' + escHtml(r.to) + '</a></td>'
                            + '<td><span style="font-size:11px;background:#e0f0ff;color:#0073aa;padding:1px 6px;border-radius:3px;font-weight:600"><?php echo esc_js( __( 'Manual', 'cloudscale-seo-ai-optimizer' ) ); ?></span></td>'
                            + '<td><button class="button cs-del-redirect" data-from="' + escHtml(r.from) + '" data-nonce="' + escHtml(nonce) + '"><?php echo esc_js( __( 'Delete', 'cloudscale-seo-ai-optimizer' ) ); ?></button></td>';
                        tbody.appendChild(tr);
                        document.getElementById('cs-add-from').value = '';
                        document.getElementById('cs-add-to').value   = '';
                        msg.textContent = <?php echo wp_json_encode( __( 'Redirect added.', 'cloudscale-seo-ai-optimizer' ) ); ?>;
                        msg.style.cssText = 'display:block;color:#00a32a';
                    }).catch(function () {
                        addBtn.disabled = false;
                        msg.textContent = <?php echo wp_json_encode( __( 'Network error — please try again.', 'cloudscale-seo-ai-optimizer' ) ); ?>;
                        msg.style.cssText = 'display:block;color:#d63638';
                    });
                });
            }

            function escHtml(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('cs-del-redirect')) {
                    if (!confirm(<?php echo wp_json_encode( __( 'Delete this redirect?', 'cloudscale-seo-ai-optimizer' ) ); ?>)) return;
                    var btn = e.target;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_delete_redirect&nonce=' + encodeURIComponent(btn.dataset.nonce) + '&from=' + encodeURIComponent(btn.dataset.from)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        if (d.success) btn.closest('tr').remove();
                    }).catch(function (err) {
                        console.error('cs-seo: delete redirect request failed', err);
                    });
                }
                if (e.target.id === 'cs-clear-redirects') {
                    if (!confirm(<?php echo wp_json_encode( __( 'Delete ALL stored redirects? This cannot be undone.', 'cloudscale-seo-ai-optimizer' ) ); ?>)) return;
                    var nonce = e.target.dataset.nonce;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_clear_redirects&nonce=' + encodeURIComponent(nonce)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        if (d.success) location.reload();
                    }).catch(function (err) {
                        console.error('cs-seo: clear redirects request failed', err);
                    });
                }
                if (e.target.id === 'cs-prune-redirects') {
                    var btn = e.target;
                    var nonce = btn.dataset.nonce;
                    var cutoff = Math.floor(Date.now() / 1000) - (90 * 86400);
                    var rows = Array.prototype.slice.call(document.querySelectorAll('#cs-redirects-tbody tr'));
                    var toDelete = rows.filter(function (row) {
                        var lastHit = row.cells[2] ? (parseInt(row.cells[2].dataset.val, 10) || 0) : 0;
                        var created = row.cells[3] ? (parseInt(row.cells[3].dataset.val, 10) || 0) : 0;
                        if (lastHit === 0) return !created || created < cutoff;
                        return lastHit < cutoff;
                    });
                    var preview = document.getElementById('cs-prune-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'cs-prune-preview';
                        btn.closest('p').insertAdjacentElement('afterend', preview);
                    }
                    if (toDelete.length === 0) {
                        preview.innerHTML = '<div style="margin-top:10px;padding:10px 14px;background:#dcfce7;border-radius:6px;font-size:13px;color:#1a7a34;font-weight:600;">✓ No unused redirects — everything was hit within the last 3 months.</div>';
                        return;
                    }
                    var listHtml = '';
                    toDelete.forEach(function (row) {
                        var from        = row.dataset.redirectFrom || '—';
                        var lastHitTxt  = row.cells[2] ? row.cells[2].textContent.trim() : '—';
                        var createdTxt  = row.cells[3] ? row.cells[3].textContent.trim() : '—';
                        listHtml += '<tr><td style="font-family:monospace;font-size:11px;word-break:break-all">' + escHtml(from) + '</td>'
                            + '<td style="white-space:nowrap;font-size:12px">' + escHtml(lastHitTxt || 'Never') + '</td>'
                            + '<td style="white-space:nowrap;font-size:12px">' + escHtml(createdTxt) + '</td></tr>';
                    });
                    preview.innerHTML = '<div style="background:#fff8f0;border:1px solid #f59e0b;border-radius:6px;padding:16px;margin-top:12px;">'
                        + '<strong style="color:#92400e;">⚠ ' + toDelete.length + ' redirect' + (toDelete.length !== 1 ? 's' : '') + ' will be deleted</strong>'
                        + '<p style="margin:6px 0 10px;font-size:13px;color:#50575e;">These have not been hit in the last 3 months. Review before confirming:</p>'
                        + '<div style="max-height:260px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:4px;">'
                        + '<table class="widefat striped" style="font-size:12px;">'
                        + '<thead><tr><th>Old path</th><th>Last hit</th><th>Created</th></tr></thead>'
                        + '<tbody>' + listHtml + '</tbody></table></div>'
                        + '<div style="margin-top:12px;display:flex;gap:8px;">'
                        + '<button id="cs-prune-confirm" class="button" style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600" data-nonce="' + escHtml(nonce) + '">🗑 Delete These ' + toDelete.length + ' Redirect' + (toDelete.length !== 1 ? 's' : '') + '</button>'
                        + '<button id="cs-prune-cancel" class="button">Cancel</button>'
                        + '</div></div>';
                    preview.scrollIntoView({behavior: 'smooth', block: 'nearest'});
                }
                if (e.target.id === 'cs-prune-confirm') {
                    var btn = e.target;
                    var nonce = btn.dataset.nonce;
                    btn.disabled = true;
                    btn.textContent = '⟳ Deleting…';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_prune_unused_redirects&nonce=' + encodeURIComponent(nonce)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        btn.disabled = false;
                        if (d.success) {
                            var msg = document.getElementById('cs-redirects-action-msg');
                            if (msg) { msg.textContent = '✓ Removed ' + d.data.pruned + ' unused redirect(s).'; msg.style.display = ''; }
                            if (d.data.pruned > 0) setTimeout(function () { location.reload(); }, 1200);
                        }
                    }).catch(function (err) {
                        btn.disabled = false;
                        console.error('cs-seo: prune redirects failed', err);
                    });
                }
                if (e.target.id === 'cs-prune-cancel') {
                    var preview = document.getElementById('cs-prune-preview');
                    if (preview) preview.innerHTML = '';
                }
                if (e.target.id === 'cs-analyse-chains') {
                    var btn = e.target;
                    var nonce = btn.dataset.nonce;
                    btn.disabled = true;
                    btn.textContent = '⟳ Analysing…';
                    var msg = document.getElementById('cs-redirects-action-msg');
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_analyse_redirect_chains&nonce=' + encodeURIComponent(nonce)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        btn.disabled = false;
                        btn.textContent = '🔍 Analyse Chains';
                        if (!d.success) return;
                        var chains = d.data.chains || [];
                        var count  = d.data.count || 0;
                        // Clear previous highlights
                        document.querySelectorAll('#cs-redirects-tbody tr').forEach(function(row) {
                            row.style.background = '';
                            var badge = row.querySelector('.cs-chain-badge');
                            if (badge) badge.remove();
                        });
                        if (count === 0) {
                            if (msg) { msg.textContent = '✓ No chained redirects found.'; msg.style.cssText = 'display:inline-block;font-size:13px;font-weight:600;padding:2px 10px;border-radius:12px;background:#dcfce7;color:#1a7a34'; }
                            var squashBtn = document.getElementById('cs-squash-redirects');
                            if (squashBtn) squashBtn.style.display = 'none';
                            var filterBar = document.getElementById('cs-chain-filter-bar');
                            if (filterBar) filterBar.style.display = 'none';
                            return;
                        }
                        // Highlight chained rows
                        var chainedPaths = chains.map(function(c) { return c.from; });
                        document.querySelectorAll('#cs-redirects-tbody tr').forEach(function(row) {
                            var from = row.dataset.redirectFrom;
                            if (from && chainedPaths.indexOf(from) !== -1) {
                                row.style.background = '#fef3c7';
                                var chain = chains.find(function(c) { return c.from === from; });
                                var firstTd = row.querySelector('td');
                                if (firstTd && chain) {
                                    var badge = document.createElement('span');
                                    badge.className = 'cs-chain-badge';
                                    badge.style.cssText = 'display:inline-block;background:#f59e0b;color:#fff;font-size:10px;font-weight:700;padding:1px 5px;border-radius:3px;margin-left:5px;vertical-align:middle';
                                    badge.title = '→ ' + chain.to + ' → ' + chain.resolves_to;
                                    badge.textContent = '⛓ Chain';
                                    firstTd.appendChild(badge);
                                }
                            }
                        });
                        if (msg) { msg.textContent = '⚠ ' + count + ' chained redirect' + (count !== 1 ? 's' : '') + ' found'; msg.style.cssText = 'display:inline-block;font-size:13px;font-weight:600;padding:2px 10px;border-radius:12px;background:#fef3c7;color:#92400e'; }
                        var squashBtn = document.getElementById('cs-squash-redirects');
                        if (squashBtn) {
                            squashBtn.style.display = '';
                            squashBtn.textContent = '🔗 Remove ' + count + ' Chained Redirect' + (count !== 1 ? 's' : '');
                            squashBtn.dataset.nonce = nonce;
                        }
                        var filterBar = document.getElementById('cs-chain-filter-bar');
                        if (filterBar) filterBar.style.display = '';
                    }).catch(function (err) {
                        btn.disabled = false;
                        btn.textContent = '🔍 Analyse Chains';
                        console.error('cs-seo: analyse chains failed', err);
                    });
                }
                if (e.target.id === 'cs-squash-redirects') {
                    if (!confirm(<?php echo wp_json_encode( __( 'Collapse redirect chains (A→B→C becomes A→C directly)? Destination URLs will be updated.', 'cloudscale-seo-ai-optimizer' ) ); ?>)) return;
                    var btn = e.target;
                    var nonce = btn.dataset.nonce;
                    btn.disabled = true;
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_squash_redirect_chains&nonce=' + encodeURIComponent(nonce)
                    }).then(function (r) { return r.json(); }).then(function (d) {
                        btn.disabled = false;
                        if (d.success) {
                            var msg = document.getElementById('cs-redirects-action-msg');
                            if (msg) { msg.textContent = '✓ Fixed ' + d.data.squashed + ' chain' + (d.data.squashed !== 1 ? 's' : '') + '.'; msg.style.cssText = 'display:inline-block;font-size:13px;font-weight:600;padding:2px 10px;border-radius:12px;background:#dcfce7;color:#1a7a34'; }
                            if (d.data.squashed > 0) setTimeout(function () { location.reload(); }, 1200);
                        }
                    }).catch(function (err) {
                        btn.disabled = false;
                        console.error('cs-seo: squash redirects failed', err);
                    });
                }
                if (e.target.id === 'cs-show-chains-only') {
                    if (typeof window.csRRender === 'function') window.csRRender();
                }
            });
        }());
        (function () {
            var tbody = document.getElementById('cs-redirects-tbody');
            if (!tbody) return;
            var sortState = { col: '', dir: 1 };
            tbody.closest('table').querySelectorAll('thead [data-sort-col]').forEach(function (th) {
                th.addEventListener('click', function () {
                    var col     = th.dataset.sortCol;
                    var colIdx  = Array.prototype.indexOf.call(th.parentNode.children, th);
                    var dir     = (sortState.col === col && sortState.dir === -1) ? 1 : -1;
                    sortState   = { col: col, dir: dir };
                    // Update icons
                    tbody.closest('table').querySelectorAll('thead .cs-sort-icon').forEach(function (ic) {
                        ic.textContent = '↕'; ic.style.opacity = '0.5';
                    });
                    var icon = th.querySelector('.cs-sort-icon');
                    if (icon) { icon.textContent = dir === -1 ? '↓' : '↑'; icon.style.opacity = '1'; }
                    // Sort rows
                    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                    rows.sort(function (a, b) {
                        var aCell = a.children[colIdx], bCell = b.children[colIdx];
                        var aVal  = aCell ? (parseFloat(aCell.dataset.val) || 0) : 0;
                        var bVal  = bCell ? (parseFloat(bCell.dataset.val) || 0) : 0;
                        return (aVal - bVal) * dir;
                    });
                    rows.forEach(function (row) { tbody.appendChild(row); });
                    if (typeof window.csRRender === 'function') window.csRRender();
                });
            });
        }());
        (function () {
            var tbody = document.getElementById('cs-redirects-tbody');
            if (!tbody) return;
            var CS_R_PER = 50;
            var csRFilter = 'all';
            var csRPage   = 1;
            window.csRRender = function () {
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                var chainsOnly = document.getElementById('cs-show-chains-only');
                if (chainsOnly && chainsOnly.checked) {
                    rows.forEach(function (row) {
                        row.style.display = row.querySelector('.cs-chain-badge') ? '' : 'none';
                    });
                    var pager = document.getElementById('cs-redirects-pager');
                    if (pager) pager.innerHTML = '';
                    return;
                }
                var visible = rows.filter(function (row) {
                    var type = row.dataset.redirectType || 'auto';
                    return csRFilter === 'all' || type === csRFilter;
                });
                var totalPages = Math.max(1, Math.ceil(visible.length / CS_R_PER));
                if (csRPage > totalPages) csRPage = 1;
                var start = (csRPage - 1) * CS_R_PER;
                var end   = start + CS_R_PER;
                rows.forEach(function (row) {
                    var type = row.dataset.redirectType || 'auto';
                    var typeMatch = csRFilter === 'all' || type === csRFilter;
                    var idx = visible.indexOf(row);
                    row.style.display = (typeMatch && idx >= start && idx < end) ? '' : 'none';
                });
                var pager = document.getElementById('cs-redirects-pager');
                if (!pager) return;
                if (totalPages <= 1) { pager.innerHTML = ''; return; }
                var html = '<span style="font-size:13px;color:#555;margin-right:4px;">Page</span>';
                for (var i = 1; i <= totalPages; i++) {
                    var cls = 'button' + (i === csRPage ? ' button-primary' : '') + ' cs-rpage-btn';
                    html += '<button type="button" class="' + cls + '" data-pg="' + i + '" style="min-width:32px;">' + i + '</button> ';
                }
                html += '<span style="font-size:12px;color:#999;margin-left:4px;">(' + visible.length + ' total)</span>';
                pager.innerHTML = html;
            };
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('cs-rtype-btn')) {
                    csRFilter = e.target.dataset.filter;
                    csRPage   = 1;
                    document.querySelectorAll('.cs-rtype-btn').forEach(function (b) {
                        b.classList.toggle('button-primary', b.dataset.filter === csRFilter);
                    });
                    window.csRRender();
                }
                if (e.target.classList.contains('cs-rpage-btn')) {
                    csRPage = parseInt(e.target.dataset.pg, 10);
                    window.csRRender();
                }
            });
            window.csRRender();
        }());
        <?php wp_add_inline_script( 'cs-seo-admin-js', ob_get_clean() ); ?>
        <?php
    }
}
