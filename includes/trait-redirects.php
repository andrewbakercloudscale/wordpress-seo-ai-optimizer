<?php
/**
 * Automatic 301 redirects on post-slug rename.
 *
 * When enabled, captures the old permalink before a slug change and stores a
 * redirect mapping from the old path to the new URL. A template_redirect hook
 * serves those redirects on any 404 that matches a stored path.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.19.101
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
     * @since 4.19.101
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
     * @since 4.19.101
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
     * @since 4.19.101
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
     * @since 4.19.101
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
     * @since 4.19.101
     * @return void
     */
    public function redirect_serve(): void {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- used only for path comparison, never output.
        $request_path = isset( $_SERVER['REQUEST_URI'] )
            ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH )
            : '';

        if ( ! is_404() ) return;

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
                wp_redirect( $r['to'], 301 );
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
     * @since 4.19.101
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
     * @since 4.19.101
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
     * @since 4.19.101
     * @return void
     */
    public function ajax_clear_redirects(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

        delete_option( 'cs_seo_redirects' );
        wp_send_json_success();
    }

    // -------------------------------------------------------------------------
    // Admin UI
    // -------------------------------------------------------------------------

    /**
     * Renders the Redirects settings tab content.
     *
     * @since 4.19.101
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
                <p style="margin-bottom:12px">
                    <button id="cs-clear-redirects" class="button"
                            style="background:#d63638;border-color:#d63638;color:#fff"
                            data-nonce="<?php echo esc_attr( $nonce ); ?>">
                        <?php esc_html_e( 'Delete All Redirects', 'cloudscale-seo-ai-optimizer' ); ?>
                    </button>
                </p>
                <table class="widefat striped" style="max-width:960px">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Old path (301 from)', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th><?php esc_html_e( 'Hits', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th><?php esc_html_e( 'Last hit', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th><?php esc_html_e( 'New URL (301 to)', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th><?php esc_html_e( 'Post', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="cs-redirects-tbody">
                        <?php foreach ( $redirects as $r ) : ?>
                        <tr>
                            <td><a href="<?php echo esc_url( home_url( $r['from'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $r['from'] ); ?></code></a></td>
                            <td><?php echo isset( $r['hits'] ) ? (int) $r['hits'] : 0; ?></td>
                            <td><?php echo ! empty( $r['last_hit'] ) ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $r['last_hit'] ) ) : '<span style="color:#999">—</span>'; ?></td>
                            <td>
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
                            <td><?php echo ! empty( $r['created'] ) ? esc_html( date_i18n( get_option( 'date_format' ), (int) $r['created'] ) ) : '—'; ?></td>
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
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td><a href="' + encodeURI(window.location.origin + r.from) + '" target="_blank" rel="noopener"><code>' + escHtml(r.from) + '</code></a></td>'
                            + '<td>0</td>'
                            + '<td><span style="color:#999">—</span></td>'
                            + '<td><a href="' + escHtml(r.to) + '" target="_blank" rel="noopener">' + escHtml(r.to) + '</a></td>'
                            + '<td><span style="font-size:11px;background:#e0f0ff;color:#0073aa;padding:1px 6px;border-radius:3px;font-weight:600"><?php echo esc_js( __( 'Manual', 'cloudscale-seo-ai-optimizer' ) ); ?></span></td>'
                            + '<td>—</td>'
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
            });
        }());
        <?php wp_add_inline_script( 'cs-seo-admin-js', ob_get_clean() ); ?>
        <?php
    }
}
