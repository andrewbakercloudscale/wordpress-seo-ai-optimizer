<?php
/**
 * Related Articles — scores, ranks, and injects contextually related post links.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Related_Articles {
    /**
     * Injects Related Articles sections (top and bottom) into singular post content.
     *
     * @since 4.10.0
     * @param string $content The post content.
     * @return string Content with related article links injected, or original if not applicable.
     */
    public function inject_related_links(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;
        if (!in_the_loop() || !is_main_query()) return $content;
        if (!(int)($this->opts['rc_enable'] ?? 1)) return $content;

        $pid    = (int) get_the_ID();
        $status = get_post_meta($pid, self::META_RC_STATUS, true);
        if ($status !== 'complete') return $content;

        $top_raw    = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP,    true));
        $bottom_raw = maybe_unserialize(get_post_meta($pid, self::META_RC_BOTTOM, true));
        $top_ids    = array_values(array_filter(array_map('intval', is_array($top_raw)    ? $top_raw    : [])));
        $bottom_ids = array_values(array_filter(array_map('intval', is_array($bottom_raw) ? $bottom_raw : [])));

        // Slice to the current settings — decreasing the count just hides links immediately
        // without needing regeneration. Increasing requires a regenerate (handled in admin UI).
        $top_count    = max(2, min(5,  (int)($this->opts['rc_top_count']    ?? 3)));
        $bottom_count = max(3, min(10, (int)($this->opts['rc_bottom_count'] ?? 5)));
        $top_ids      = array_slice($top_ids,    0, $top_count);
        $bottom_ids   = array_slice($bottom_ids, 0, $bottom_count);

        // Top block — inject after the summary box div if present, otherwise prepend.
        // prepend_summary_box() runs first (priority 10) so its output is already in $content.
        // The summary box wrapper ends with a known closing </table></div> sequence we can
        // anchor on via the unique class marker.
        if ((int)($this->opts['rc_top_enabled'] ?? 1) && count($top_ids) >= 2) {
            $top_html = $this->render_rc_block('Related Articles', $top_ids, 'top');
            if (strpos($content, 'cs-seo-summary-box') !== false) {
                // Append a sentinel comment to the summary box output so we have a
                // reliable anchor regardless of nesting depth.
                $anchor = '<!-- /cs-seo-summary-box -->';
                if (strpos($content, $anchor) !== false) {
                    $content = str_replace($anchor, $anchor . $top_html, $content);
                } else {
                    // Fallback: insert after the first occurrence of the wrapper closing tag.
                    // We know the summary box is a single-level <div> wrapping a <table>.
                    // Find end of div that contains class cs-seo-summary-box.
                    $pos = strpos($content, 'cs-seo-summary-box');
                    if ($pos !== false) {
                        $end = strpos($content, '</div>', $pos);
                        if ($end !== false) {
                            $end += strlen('</div>');
                            $content = substr($content, 0, $end) . $top_html . substr($content, $end);
                        } else {
                            $content = $top_html . $content;
                        }
                    } else {
                        $content = $top_html . $content;
                    }
                }
            } else {
                $content = $top_html . $content;
            }
        }

        // Bottom block — append after content
        if ((int)($this->opts['rc_bottom_enabled'] ?? 1) && count($bottom_ids) >= 3) {
            $content .= $this->render_rc_block('You Might Also Like', $bottom_ids, 'bottom');
        }

        return $content;
    }

    /**
     * Renders a Related Articles or You Might Also Like link block.
     *
     * @since 4.10.0
     * @param string $heading  Block heading text.
     * @param int[]  $post_ids Ordered list of post IDs to link.
     * @param string $position 'top' or 'bottom' — controls accent colour.
     * @return string Rendered HTML block, or empty string if no valid links.
     */
    private function render_rc_block(string $heading, array $post_ids, string $position): string {
        $style  = (string)($this->opts['rc_style'] ?? '1');
        $is_top = $position === 'top';
        $slug   = esc_attr($position);
        $cls    = esc_attr( 'cs-rc-block cs-rc-' . $slug . ' cs-rc-style-' . $style );

        $links = [];
        foreach ($post_ids as $tid) {
            $post = get_post($tid);
            if (!$post || $post->post_status !== 'publish') continue;
            $links[] = ['url' => get_permalink($post), 'title' => get_the_title($post)];
        }
        if (empty($links)) return '';

        // ── Palette: each style declares fmt, accent, and optionally grad/dark_bg ──
        $pal = [
            '1'  => ['fmt' => 'gradient', 'accent' => $is_top ? '#4f46e5' : '#0e7490',
                     'grad' => $is_top ? 'linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%)'
                                       : 'linear-gradient(120deg,#0c4a6e 0%,#0e7490 60%,#22d3ee 100%)'],
            '2'  => ['fmt' => 'dark',     'accent' => '#fbbf24', 'dark_bg' => '#1e1b4b'],
            '3'  => ['fmt' => 'minimal',  'accent' => '#2563eb'],
            '4'  => ['fmt' => 'cards',    'accent' => '#059669'],
            '5'  => ['fmt' => 'stripe',   'accent' => '#64748b'],
            '6'  => ['fmt' => 'magazine', 'accent' => '#dc2626',
                     'grad' => 'linear-gradient(120deg,#7f1d1d 0%,#dc2626 60%,#f87171 100%)'],
            '7'  => ['fmt' => 'gradient', 'accent' => '#0891b2',
                     'grad' => 'linear-gradient(120deg,#0c4a6e 0%,#0891b2 60%,#38bdf8 100%)'],
            '8'  => ['fmt' => 'dark',     'accent' => '#f59e0b', 'dark_bg' => '#1c1917'],
            '9'  => ['fmt' => 'gradient', 'accent' => '#1e40af',
                     'grad' => 'linear-gradient(120deg,#0f172a 0%,#1e40af 60%,#3b82f6 100%)'],
            '10' => ['fmt' => 'minimal',  'accent' => '#374151'],
            '11' => ['fmt' => 'gradient', 'accent' => '#16a34a',
                     'grad' => 'linear-gradient(120deg,#14532d 0%,#16a34a 60%,#4ade80 100%)'],
            '12' => ['fmt' => 'gradient', 'accent' => '#e11d48',
                     'grad' => 'linear-gradient(120deg,#881337 0%,#e11d48 60%,#fb7185 100%)'],
            '13' => ['fmt' => 'gradient', 'accent' => '#ea580c',
                     'grad' => 'linear-gradient(120deg,#7c2d12 0%,#ea580c 60%,#fb923c 100%)'],
            '14' => ['fmt' => 'dark',     'accent' => '#38bdf8', 'dark_bg' => '#020617'],
            '15' => ['fmt' => 'dark',     'accent' => '#a78bfa', 'dark_bg' => '#2d1b69'],
            '16' => ['fmt' => 'minimal',  'accent' => '#0d9488'],
            '17' => ['fmt' => 'minimal',  'accent' => '#e11d48'],
            '18' => ['fmt' => 'stripe',   'accent' => '#d97706'],
            '19' => ['fmt' => 'bordered', 'accent' => '#475569'],
            '20' => ['fmt' => 'pill',     'accent' => '#7c3aed'],
        ];
        $p      = $pal[$style] ?? $pal['1'];
        $fmt    = $p['fmt'];
        $accent = esc_attr( $p['accent'] );
        $grad   = esc_attr( $p['grad'] ?? '' );
        $dk     = esc_attr( $p['dark_bg'] ?? '' );

        // ── Format rendering ────────────────────────────────────────────────────
        switch ($fmt) {

            case 'dark':
                $out  = '<div class="' . $cls . '" style="background:' . $dk . ';border-radius:12px;margin:0 0 36px;padding:20px 24px;">';
                $out .= '<div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin:0 0 14px;">' . esc_html($heading) . '</div>';
                $out .= '<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:baseline;gap:10px;">';
                    $out .= '<span style="font-size:12px;font-weight:700;color:' . $accent . ';min-width:18px;flex-shrink:0;">' . ($i + 1) . '.</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#fff;font-size:14px;font-weight:500;text-decoration:none;line-height:1.5;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;

            case 'minimal':
                $out  = '<div class="' . $cls . '" style="background:#fff;border-top:3px solid ' . $accent . ';padding:18px 24px;margin:0 0 36px;">';
                $out .= '<div style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:' . $accent . ';margin:0 0 12px;">' . esc_html($heading) . '</div>';
                $out .= '<ul style="margin:0;padding:0;list-style:none;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:baseline;gap:8px;padding:7px 0;border-bottom:1px solid #f3f4f6;">';
                    $out .= '<span style="font-size:11px;color:#9ca3af;min-width:16px;flex-shrink:0;">' . ($i + 1) . '.</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#374151;font-size:14px;font-weight:500;text-decoration:none;line-height:1.5;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;

            case 'cards':
                $out  = '<div class="' . $cls . '" style="margin:0 0 36px;">';
                $out .= '<div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin:0 0 10px;">' . esc_html($heading) . '</div>';
                $out .= '<div style="display:flex;flex-direction:column;gap:8px;">';
                foreach ($links as $i => $link) {
                    $out .= '<div style="display:flex;align-items:stretch;border:1px solid #e5e7eb;border-left:4px solid ' . $accent . ';border-radius:6px;overflow:hidden;">';
                    $out .= '<span style="background:' . $accent . ';color:#fff;font-size:12px;font-weight:700;padding:10px 12px;min-width:36px;text-align:center;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' . ($i + 1) . '</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#1f2937;font-size:14px;font-weight:500;text-decoration:none;padding:10px 14px;line-height:1.4;flex:1;display:flex;align-items:center;">' . esc_html($link['title']) . '</a>';
                    $out .= '</div>';
                }
                $out .= '</div></div>';
                return $out;

            case 'stripe':
                $out  = '<div class="' . $cls . '" style="border-left:4px solid ' . $accent . ';padding:16px 20px;margin:0 0 36px;background:#fafafa;">';
                $out .= '<div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin:0 0 12px;">' . esc_html($heading) . '</div>';
                $out .= '<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:9px;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:baseline;gap:8px;">';
                    $out .= '<span style="color:' . $accent . ';font-weight:700;font-size:13px;min-width:20px;flex-shrink:0;">' . ($i + 1) . '.</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#374151;font-size:14px;font-weight:500;text-decoration:none;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;

            case 'magazine':
                $out  = '<div class="' . $cls . '" style="background:#fff;border-radius:12px;overflow:hidden;margin:0 0 36px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">';
                $out .= '<div style="background:' . $grad . ';padding:10px 20px;"><span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.95);">' . esc_html($heading) . '</span></div>';
                foreach ($links as $i => $link) {
                    $bg   = esc_attr( ($i % 2 === 1) ? '#f9fafb' : '#fff' );
                    $out .= '<div style="display:flex;align-items:stretch;background:' . $bg . ';border-bottom:1px solid #f3f4f6;">';
                    $out .= '<span style="background:' . $grad . ';color:#fff;font-size:13px;font-weight:800;width:44px;min-width:44px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">' . ($i + 1) . '</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#111827;font-size:14px;font-weight:500;text-decoration:none;padding:12px 16px;flex:1;line-height:1.4;display:flex;align-items:center;">' . esc_html($link['title']) . '</a>';
                    $out .= '</div>';
                }
                $out .= '</div>';
                return $out;

            case 'bordered':
                $out  = '<div class="' . $cls . '" style="background:#fff;border:1.5px solid ' . $accent . ';border-radius:12px;margin:0 0 36px;padding:20px 24px;">';
                $out .= '<div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';margin:0 0 14px;">' . esc_html($heading) . '</div>';
                $out .= '<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:baseline;gap:10px;">';
                    $out .= '<span style="font-size:12px;font-weight:700;color:' . $accent . ';min-width:18px;flex-shrink:0;">' . ($i + 1) . '.</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#1f2937;font-size:14px;font-weight:500;text-decoration:none;line-height:1.5;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;

            case 'pill':
                $out  = '<div class="' . $cls . '" style="background:#fff;border-radius:14px;margin:0 0 36px;padding:20px 24px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">';
                $out .= '<div style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;margin:0 0 14px;">' . esc_html($heading) . '</div>';
                $out .= '<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:center;gap:12px;">';
                    $out .= '<span style="background:' . $accent . ';color:#fff;font-size:11px;font-weight:700;border-radius:20px;min-width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;padding:0 7px;">' . ($i + 1) . '</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:#1f2937;font-size:14px;font-weight:500;text-decoration:none;line-height:1.4;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;

            default: // gradient (styles 1, 7, 9, 11, 12, 13)
                $icon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                $out  = '<div class="' . $cls . '" style="background:#ffffff;border-radius:14px;overflow:hidden;margin:0 0 36px;box-shadow:0 2px 8px rgba(0,0,0,0.06),0 4px 24px rgba(0,0,0,0.08),0 1px 2px rgba(0,0,0,0.04);">';
                $out .= '<div style="background:' . $grad . ';padding:12px 24px;display:flex;align-items:center;gap:9px;">';
                $out .= $icon;
                $out .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.95);">' . esc_html($heading) . '</span>';
                $out .= '</div>';
                $out .= '<ul style="margin:0;padding:16px 24px;list-style:none;display:flex;flex-direction:column;gap:10px;">';
                foreach ($links as $i => $link) {
                    $out .= '<li style="display:flex;align-items:baseline;gap:8px;">';
                    $out .= '<span style="color:' . $accent . ';font-size:12px;font-weight:700;min-width:18px;flex-shrink:0;">' . ($i + 1) . '.</span>';
                    $out .= '<a href="' . esc_url($link['url']) . '" class="cs-rc-link" style="color:' . $accent . ';font-size:14px;font-weight:500;text-decoration:none;line-height:1.5;">' . esc_html($link['title']) . '</a>';
                    $out .= '</li>';
                }
                $out .= '</ul></div>';
                return $out;
        }
    }
    /**
     * Enqueues the minimal frontend CSS needed by the Related Articles blocks.
     *
     * Registers a no-op style handle so wp_add_inline_style() can attach the
     * .cs-rc-link:hover rule without echoing a raw <style> tag into the page.
     * Only enqueued on singular post pages when the feature is active.
     *
     * @since 4.10.0
     * @return void
     */
    public function enqueue_rc_front_styles(): void {
        if ( ! is_singular( 'post' ) ) return;
        if ( ! (int) ( $this->opts['rc_enable'] ?? 1 ) ) return;
        wp_register_style( 'cs-rc-styles', false ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- inline-only handle; no external file or version needed
        wp_enqueue_style( 'cs-rc-styles' );
        wp_add_inline_style( 'cs-rc-styles', implode('', [
            '.cs-rc-link:hover{text-decoration:underline}',
            '.cs-rc-style-2 .cs-rc-link:hover,.cs-rc-style-8 .cs-rc-link:hover{color:#fff!important}',
            '.cs-rc-style-3 .cs-rc-link:hover{color:#2563eb!important}',
            '.cs-rc-style-4 .cs-rc-link:hover{color:#059669!important}',
            '.cs-rc-style-5 .cs-rc-link:hover{color:#64748b!important}',
            '.cs-rc-style-6 .cs-rc-link:hover{color:#dc2626!important}',
            '.cs-rc-style-9 .cs-rc-link:hover{color:#1e40af!important}',
            '.cs-rc-style-10 .cs-rc-link:hover{color:#374151!important}',
            '.cs-rc-style-11 .cs-rc-link:hover{color:#16a34a!important}',
            '.cs-rc-style-12 .cs-rc-link:hover{color:#e11d48!important}',
            '.cs-rc-style-13 .cs-rc-link:hover{color:#ea580c!important}',
            '.cs-rc-style-14 .cs-rc-link:hover,.cs-rc-style-15 .cs-rc-link:hover{color:#fff!important}',
            '.cs-rc-style-16 .cs-rc-link:hover{color:#0d9488!important}',
            '.cs-rc-style-17 .cs-rc-link:hover{color:#e11d48!important}',
            '.cs-rc-style-18 .cs-rc-link:hover{color:#d97706!important}',
            '.cs-rc-style-19 .cs-rc-link:hover{color:#475569!important}',
            '.cs-rc-style-20 .cs-rc-link:hover{color:#7c3aed!important}',
        ]) );
    }

    /**
     * Runs the Related Articles pipeline synchronously when a post is published or updated.
     *
     * RC generation is purely local (no API calls) and fast, so it runs inline on the
     * publish request rather than via cron. rc_step_validate exits immediately if the
     * post's fingerprint is unchanged and output is already valid, so re-saves are cheap.
     *
     * @since 4.18.8
     * @param string  $new_status New post status.
     * @param string  $old_status Previous post status.
     * @param WP_Post $post       Post object.
     * @return void
     */
    public function rc_on_post_publish( string $new_status, string $old_status, WP_Post $post ): void {
        if ( $new_status !== 'publish' ) return;
        if ( $post->post_type !== 'post' ) return;

        try {
            $this->rc_step_load( $post->ID );
            if ( get_post_meta( $post->ID, self::META_RC_STATUS, true ) === 'complete' ) return;
            $this->rc_step_validate( $post->ID );
            if ( get_post_meta( $post->ID, self::META_RC_STATUS, true ) === 'complete' ) return;
            $this->rc_step_candidates( $post->ID );
            $this->rc_step_score( $post->ID );
            $this->rc_step_top( $post->ID );
            $this->rc_step_bottom( $post->ID );
            $this->rc_step_validate_out( $post->ID );
            $this->rc_step_complete( $post->ID );
        } catch ( \Throwable $e ) {
            update_post_meta( $post->ID, self::META_RC_STATUS, 'error' );
            update_post_meta( $post->ID, self::META_RC_ERROR,  $e->getMessage() );
        }
    }

    /**
     * AJAX handler: returns posts with their Related Articles generation status for the admin panel.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_rc_get_posts(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by check_ajax_referer() at the top of this function
        $page     = max(1, (int)(wp_unslash($_POST['page'] ?? 1)));
        $per_page = 50;
        $filter   = sanitize_text_field(wp_unslash($_POST['filter'] ?? 'all'));
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        $posts  = [];
        $total  = 0;
        $offset = ($page - 1) * $per_page;

        if ($filter === 'all') {
            // WP_Query with no meta_query returns 0 in some environments — use direct DB query.
            global $wpdb;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only paginated list; no persistent cache needed
            $total   = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = %s",
                'post'
            ) );
            $raw_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                  WHERE post_status = 'publish' AND post_type = %s
                  ORDER BY post_date DESC
                  LIMIT %d OFFSET %d",
                'post', $per_page, $offset
            ) );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            foreach ( $raw_ids as $raw_pid ) {
                $pid      = (int) $raw_pid;
                $status   = get_post_meta($pid, self::META_RC_STATUS, true) ?: 'pending';
                $gen_at   = (int) get_post_meta($pid, self::META_RC_GENERATED, true);
                $last_step= (int) get_post_meta($pid, self::META_RC_LAST_STEP, true);
                $error    = get_post_meta($pid, self::META_RC_ERROR, true);
                $top_raw  = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP, true));
                $bot_raw  = maybe_unserialize(get_post_meta($pid, self::META_RC_BOTTOM, true));
                $posts[]  = [
                    'id'         => $pid,
                    'title'      => get_the_title($pid),
                    'status'     => $status,
                    'last_step'  => $last_step,
                    'top_count'  => is_array($top_raw) ? count($top_raw) : 0,
                    'bot_count'  => is_array($bot_raw) ? count($bot_raw) : 0,
                    'generated'  => $gen_at ? human_time_diff($gen_at) . ' ago' : '',
                    'error'      => $error ?: '',
                    'permalink'  => (string) get_permalink($pid),
                ];
            }
            $total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
        } else {
            // Build meta_query for status-based filters
            $meta_query = [];
            if ($filter === 'pending') {
                $meta_query = [['key' => self::META_RC_STATUS, 'compare' => 'NOT EXISTS']];
            } elseif ($filter === 'complete') {
                $meta_query = [['key' => self::META_RC_STATUS, 'value' => 'complete']];
            } elseif ($filter === 'error') {
                $meta_query = [['key' => self::META_RC_STATUS, 'value' => 'error']];
            }

            $args = [
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ];
            if (!empty($meta_query)) $args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- filtered list view with fixed per_page

            $q     = new WP_Query($args);
            $total = $q->found_posts;
            $total_pages = $q->max_num_pages ?: 1;
            foreach ($q->posts as $pid) {
                $status   = get_post_meta($pid, self::META_RC_STATUS, true) ?: 'pending';
                $gen_at   = (int) get_post_meta($pid, self::META_RC_GENERATED, true);
                $last_step= (int) get_post_meta($pid, self::META_RC_LAST_STEP, true);
                $error    = get_post_meta($pid, self::META_RC_ERROR, true);
                $top_raw  = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP, true));
                $bot_raw  = maybe_unserialize(get_post_meta($pid, self::META_RC_BOTTOM, true));
                $posts[]  = [
                    'id'         => $pid,
                    'title'      => get_the_title($pid),
                    'status'     => $status,
                    'last_step'  => $last_step,
                    'top_count'  => is_array($top_raw) ? count($top_raw) : 0,
                    'bot_count'  => is_array($bot_raw) ? count($bot_raw) : 0,
                    'generated'  => $gen_at ? human_time_diff($gen_at) . ' ago' : '',
                    'error'      => $error ?: '',
                    'permalink'  => (string) get_permalink($pid),
                ];
            }
        }

        wp_send_json_success([
            'posts'       => $posts,
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
        ]);
    }

    /**
     * AJAX handler: runs one step of the multi-step Related Articles generation pipeline for a post.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_rc_step(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by check_ajax_referer() at the top of this function
        $pid = (int)(wp_unslash($_POST['post_id'] ?? 0));
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        if (!$pid || get_post_status($pid) !== 'publish') {
            wp_send_json(['success' => false, 'error' => 'Invalid post.']);
            return;
        }

        $last_step = (int)(get_post_meta($pid, self::META_RC_LAST_STEP, true) ?: 0);
        $status    = get_post_meta($pid, self::META_RC_STATUS, true) ?: 'pending';

        // If already complete, return immediately
        if ($status === 'complete') {
            wp_send_json_success(['status' => 'complete', 'step' => self::RC_STEP_COMPLETE, 'done' => true]);
            return;
        }

        $next_step = $last_step + 1;
        if ($next_step < self::RC_STEP_LOAD) $next_step = self::RC_STEP_LOAD;

        try {
            switch ($next_step) {
                case self::RC_STEP_LOAD:
                    $this->rc_step_load($pid);
                    break;
                case self::RC_STEP_VALIDATE:
                    $this->rc_step_validate($pid);
                    break;
                case self::RC_STEP_CANDIDATES:
                    $this->rc_step_candidates($pid);
                    break;
                case self::RC_STEP_SCORE:
                    $this->rc_step_score($pid);
                    break;
                case self::RC_STEP_TOP:
                    $this->rc_step_top($pid);
                    break;
                case self::RC_STEP_BOTTOM:
                    $this->rc_step_bottom($pid);
                    break;
                case self::RC_STEP_VALIDATE_OUT:
                    $this->rc_step_validate_out($pid);
                    break;
                case self::RC_STEP_COMPLETE:
                    $this->rc_step_complete($pid);
                    break;
                default:
                    wp_send_json(['success' => false, 'error' => 'Unknown step ' . $next_step]);
                    return;
            }
        } catch (\Throwable $e) {
            update_post_meta($pid, self::META_RC_STATUS,    'error');
            update_post_meta($pid, self::META_RC_ERROR,     $e->getMessage());
            wp_send_json(['success' => false, 'error' => $e->getMessage()]);
            return;
        }

        $status_now = get_post_meta($pid, self::META_RC_STATUS, true);
        $step_now   = (int) get_post_meta($pid, self::META_RC_LAST_STEP, true);

        wp_send_json_success([
            'status'  => $status_now,
            'step'    => $step_now,
            'done'    => ($status_now === 'complete' || $status_now === 'skipped'),
        ]);
    }

    /**
     * AJAX handler: single server-side pass that both generates missing Related
     * Articles AND syncs counts for existing ones.
     *
     * For posts with stored scores: re-applies top/bottom selection with current
     * count settings (trim or fill).
     * For posts with no scores (never generated): runs the full generation pipeline.
     *
     * Uses a direct DB query so no WP_Query environment issues apply.
     *
     * @since 4.16.5
     * @since 4.17.1 Extended to run the full generation pipeline for posts with no stored scores.
     * @since 4.17.2 Added trim-only path for posts with output but no scores (fixes decreasing counts).
     * @return void
     */
    public function ajax_rc_sync_counts(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        global $wpdb;

        $top_count    = max(1, (int)($this->opts['rc_top_count']    ?? 3));
        $bottom_count = max(1, (int)($this->opts['rc_bottom_count'] ?? 5));

        // All published posts of type 'post' via direct query — bypasses
        // the WP_Query filter='all' issue present in this environment.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
              WHERE post_status = 'publish'
                AND post_type   = %s
              ORDER BY post_date DESC",
            'post'
        ) );

        $synced   = 0;
        $generated = 0;

        foreach ( $post_ids as $raw_pid ) {
            $pid        = (int) $raw_pid;
            $scores_raw = maybe_unserialize( get_post_meta( $pid, self::META_RC_SCORES, true ) );

            $top_raw = maybe_unserialize( get_post_meta( $pid, self::META_RC_TOP,    true ) );
            $bot_raw = maybe_unserialize( get_post_meta( $pid, self::META_RC_BOTTOM, true ) );
            $top     = is_array( $top_raw ) ? array_values( array_map( 'intval', $top_raw ) ) : [];
            $bot     = is_array( $bot_raw ) ? array_values( array_map( 'intval', $bot_raw ) ) : [];

            if ( is_array( $scores_raw ) && ! empty( $scores_raw ) ) {
                // ── Has stored scores: re-apply selection (trim or fill) ──
                arsort( $scores_raw );
                $new_top = array_values( array_map( 'intval', array_keys( array_slice( $scores_raw, 0, $top_count, true ) ) ) );
                $remaining = array_diff_key( $scores_raw, array_flip( $new_top ) );
                $new_bot   = array_values( array_map( 'intval', array_keys( array_slice( $remaining, 0, $bottom_count, true ) ) ) );

                $new_top = array_values( array_filter( $new_top,
                    fn( $id ) => $id !== $pid && get_post_status( $id ) === 'publish' ) );
                $new_bot = array_values( array_filter( $new_bot,
                    fn( $id ) => $id !== $pid && get_post_status( $id ) === 'publish'
                              && ! in_array( $id, $new_top, true ) ) );

                if ( $new_top !== $top || $new_bot !== $bot ) {
                    update_post_meta( $pid, self::META_RC_TOP,       $new_top );
                    update_post_meta( $pid, self::META_RC_BOTTOM,    $new_bot );
                    update_post_meta( $pid, self::META_RC_GENERATED, time() );
                    update_post_meta( $pid, self::META_RC_STATUS,    'complete' );
                    $synced++;
                }
            } elseif ( ! empty( $top ) || ! empty( $bot ) ) {
                // ── Has output but no scores: trim only (cannot fill without scores) ──
                $new_top = array_values( array_slice( $top, 0, $top_count ) );
                $new_bot = array_values( array_slice( $bot, 0, $bottom_count ) );

                if ( $new_top !== $top || $new_bot !== $bot ) {
                    update_post_meta( $pid, self::META_RC_TOP,       $new_top );
                    update_post_meta( $pid, self::META_RC_BOTTOM,    $new_bot );
                    update_post_meta( $pid, self::META_RC_GENERATED, time() );
                    update_post_meta( $pid, self::META_RC_STATUS,    'complete' );
                    $synced++;
                }
            } else {
                // ── No output at all: run the full generation pipeline ──
                try {
                    $this->rc_step_load( $pid );
                    $status = get_post_meta( $pid, self::META_RC_STATUS, true );
                    if ( $status === 'complete' ) continue; // rc_step_validate skipped it
                    $this->rc_step_validate( $pid );
                    $status = get_post_meta( $pid, self::META_RC_STATUS, true );
                    if ( $status === 'complete' ) { $generated++; continue; }
                    $this->rc_step_candidates( $pid );
                    $this->rc_step_score( $pid );
                    $this->rc_step_top( $pid );
                    $this->rc_step_bottom( $pid );
                    $this->rc_step_validate_out( $pid );
                    $this->rc_step_complete( $pid );
                    $generated++;
                } catch ( \Throwable $e ) {
                    update_post_meta( $pid, self::META_RC_STATUS, 'error' );
                    update_post_meta( $pid, self::META_RC_ERROR,  $e->getMessage() );
                }
            }
        }

        wp_send_json_success( [
            'synced'       => $synced,
            'generated'    => $generated,
            'total'        => count( $post_ids ),
            'top_count'    => $top_count,
            'bottom_count' => $bottom_count,
        ] );
    }

    /**
     * AJAX handler: resets the Related Articles generation state for a post.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_rc_reset(): void {
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_die();

        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce verified by check_ajax_referer() at the top of this function
        $pid  = (int)(wp_unslash($_POST['post_id'] ?? 0));
        $mode = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'one'));
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        $meta_keys = [
            self::META_RC_TOP, self::META_RC_BOTTOM, self::META_RC_CANDIDATES,
            self::META_RC_SCORES, self::META_RC_FINGERPRINT, self::META_RC_VERSION,
            self::META_RC_GENERATED, self::META_RC_LAST_STEP, self::META_RC_STATUS,
            self::META_RC_ERROR,
        ];

        if ($mode === 'all') {
            // Delete meta in batches using direct DB query for performance
            global $wpdb;
            $placeholders = implode(',', array_fill(0, count($meta_keys), '%s'));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- bulk delete of plugin meta keys; $placeholders contains only %s tokens built by array_fill; no WP API for multi-key delete; cache invalidated immediately
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders})",
                    ...$meta_keys
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            wp_send_json_success(['reset' => 'all']);
        } else {
            if (!$pid) { wp_send_json(['success' => false, 'error' => 'Missing post_id']); return; }
            foreach ($meta_keys as $k) delete_post_meta($pid, $k);
            wp_send_json_success(['reset' => $pid]);
        }
    }

    // ── RC Step implementations ───────────────────────────────────────────────

    /**
     * Step 1 — initialises a new RC generation run for a post.
     *
     * Records the current fingerprint, sets status to 'processing', and clears any
     * previous error so later steps start from a clean slate.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_load(int $pid): void {
        $fp   = $this->rc_fingerprint($pid);
        update_post_meta($pid, self::META_RC_FINGERPRINT, $fp);
        update_post_meta($pid, self::META_RC_STATUS,      'processing');
        update_post_meta($pid, self::META_RC_ERROR,       '');
        update_post_meta($pid, self::META_RC_LAST_STEP,   self::RC_STEP_LOAD);
    }

    /**
     * Step 2 — skips regeneration if existing output is still valid.
     *
     * Marks the post complete without further steps when the fingerprint, generator
     * version, and minimum link counts all match and every linked post is still published.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_validate(int $pid): void {
        // Check if existing output is still valid — skip if nothing changed
        $stored_fp  = (string) get_post_meta($pid, self::META_RC_FINGERPRINT, true);
        $current_fp = $this->rc_fingerprint($pid);
        $stored_ver = (string) get_post_meta($pid, self::META_RC_VERSION, true);
        $top_raw    = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP, true));
        $bot_raw    = maybe_unserialize(get_post_meta($pid, self::META_RC_BOTTOM, true));
        $has_output = is_array($top_raw) && count($top_raw) >= 2
                   && is_array($bot_raw) && count($bot_raw) >= 3;

        if ($stored_fp === $current_fp && $stored_ver === self::RC_VERSION && $has_output) {
            // Validate that linked posts still exist
            $all_valid = true;
            foreach (array_merge($top_raw, $bot_raw) as $lid) {
                if (get_post_status((int)$lid) !== 'publish') { $all_valid = false; break; }
            }
            if ($all_valid) {
                // Mark skipped — already valid
                update_post_meta($pid, self::META_RC_STATUS,    'complete');
                update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_COMPLETE);
                return;
            }
        }

        // Store current fingerprint and continue
        update_post_meta($pid, self::META_RC_FINGERPRINT, $current_fp);
        update_post_meta($pid, self::META_RC_LAST_STEP,   self::RC_STEP_VALIDATE);
    }

    /**
     * Step 3 — builds the candidate pool of posts to consider as related articles.
     *
     * Queries published posts sharing categories and/or tags with the source post,
     * deduplicates, and stores the capped list in post meta for the next scoring step.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_candidates(int $pid): void {
        $opts         = $this->opts;
        $pool_size    = max(10, min(50, (int)($opts['rc_pool_size'] ?? 20)));
        $use_cats     = (int)($opts['rc_use_categories'] ?? 1);
        $use_tags     = (int)($opts['rc_use_tags'] ?? 1);
        $exclude_cats = array_map('intval', (array)($opts['rc_exclude_cats'] ?? []));

        $post_cats = wp_get_post_categories($pid, ['fields' => 'ids']);
        $post_tags = wp_get_post_tags($pid, ['fields' => 'ids']);

        $candidate_ids = [];

        // By shared category
        if ($use_cats && !empty($post_cats)) {
            $active_cats = array_diff($post_cats, $exclude_cats);
            if (!empty($active_cats)) {
                $q = new WP_Query([
                    'post_type'      => 'post',
                    'post_status'    => 'publish',
                    'posts_per_page' => $pool_size,
                    'post__not_in'   => [$pid], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- pool limited to $pool_size; exclusion of current post is necessary for related articles
                    'fields'         => 'ids',
                    'category__in'   => $active_cats,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ]);
                $candidate_ids = array_merge($candidate_ids, $q->posts);
            }
        }

        // By shared tag
        if ($use_tags && !empty($post_tags)) {
            $q = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $pool_size,
                'post__not_in'   => [$pid], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- pool limited to $pool_size; exclusion of current post is necessary for related articles
                'fields'         => 'ids',
                'tag__in'        => $post_tags,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $candidate_ids = array_merge($candidate_ids, $q->posts);
        }

        // Deduplicate, exclude self, cap at pool_size
        $candidate_ids = array_values(array_unique(array_map('intval', $candidate_ids)));
        $candidate_ids = array_slice($candidate_ids, 0, $pool_size);

        // Fallback: if the category/tag pool is too small to fill both blocks,
        // pad it with the most popular posts (by comment count) so every post
        // always gets related articles even when its category is sparse.
        $min_needed = (int)($this->opts['rc_top_count'] ?? 3) + (int)($this->opts['rc_bottom_count'] ?? 5);
        if (count($candidate_ids) < $min_needed) {
            $need_more   = $pool_size - count($candidate_ids);
            $exclude_ids = array_merge([$pid], $candidate_ids);
            $fq = new WP_Query([
                'post_type'      => 'post',
                'post_status'    => 'publish',
                'posts_per_page' => $need_more,
                'post__not_in'   => $exclude_ids, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- pool capped; excluding self + already-found candidates is required
                'fields'         => 'ids',
                'orderby'        => 'comment_count',
                'order'          => 'DESC',
            ]);
            if (!empty($fq->posts)) {
                $candidate_ids = array_values(array_unique(
                    array_merge($candidate_ids, array_map('intval', $fq->posts))
                ));
            }
        }

        update_post_meta($pid, self::META_RC_CANDIDATES, $candidate_ids);
        update_post_meta($pid, self::META_RC_LAST_STEP,  self::RC_STEP_CANDIDATES);
    }

    /**
     * Step 4 — scores every candidate post against the source post.
     *
     * Awards points for shared primary category, shared categories/tags, title keyword
     * overlap, summary keyword overlap, and a recency bonus. Stores the score map sorted
     * descending so step 5 and 6 can simply slice the top entries.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_score(int $pid): void {
        $opts      = $this->opts;
        $use_cats  = (int)($opts['rc_use_categories'] ?? 1);
        $use_tags  = (int)($opts['rc_use_tags'] ?? 1);
        $use_summ  = (int)($opts['rc_use_summary'] ?? 1);

        $candidates = maybe_unserialize(get_post_meta($pid, self::META_RC_CANDIDATES, true));
        if (!is_array($candidates) || empty($candidates)) {
            update_post_meta($pid, self::META_RC_SCORES,    []);
            update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_SCORE);
            return;
        }

        // Source signals
        $src_cats  = $use_cats ? wp_get_post_categories($pid, ['fields' => 'ids']) : [];
        $src_tags  = $use_tags ? array_map(fn($t) => $t->term_id, wp_get_post_tags($pid)) : [];
        $src_title = $this->rc_keywords(get_the_title($pid));
        $src_summ  = [];
        if ($use_summ) {
            $w = (string) get_post_meta($pid, self::META_SUM_WHAT, true);
            $y = (string) get_post_meta($pid, self::META_SUM_WHY,  true);
            $k = (string) get_post_meta($pid, self::META_SUM_KEY,  true);
            $src_summ = $this->rc_keywords($w . ' ' . $y . ' ' . $k);
        }
        $now = time();

        $scores = [];
        foreach ($candidates as $cid) {
            $score = 0;

            if ($use_cats) {
                $cand_cats = wp_get_post_categories($cid, ['fields' => 'ids']);
                $shared    = array_intersect($src_cats, $cand_cats);
                // Primary category match: first category in each list
                if (!empty($src_cats) && !empty($cand_cats) && $src_cats[0] === $cand_cats[0]) {
                    $score += 40;
                } else {
                    $score += min(30, count($shared) * 15);
                }
            }

            if ($use_tags) {
                $cand_tags = array_map(fn($t) => $t->term_id, wp_get_post_tags($cid));
                $shared    = count(array_intersect($src_tags, $cand_tags));
                $score    += min(40, $shared * 10);
            }

            // Title keyword overlap
            $cand_title_kw = $this->rc_keywords(get_the_title($cid));
            $shared_title  = count(array_intersect($src_title, $cand_title_kw));
            $score        += min(20, $shared_title * 5);

            // Summary keyword overlap
            if ($use_summ && !empty($src_summ)) {
                $cw = (string) get_post_meta($cid, self::META_SUM_WHAT, true);
                $cy = (string) get_post_meta($cid, self::META_SUM_WHY,  true);
                $ck = (string) get_post_meta($cid, self::META_SUM_KEY,  true);
                $cand_summ_kw = $this->rc_keywords($cw . ' ' . $cy . ' ' . $ck);
                $shared_summ  = count(array_intersect($src_summ, $cand_summ_kw));
                $score       += min(15, $shared_summ * 3);
            }

            // Recency bonus — published within 180 days
            $pub = get_post_time('U', true, $cid);
            if ($pub && ($now - $pub) < (180 * DAY_IN_SECONDS)) {
                $score += 5;
            }

            $scores[$cid] = $score;
        }

        // Sort descending by score
        arsort($scores);

        update_post_meta($pid, self::META_RC_SCORES,    $scores);
        update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_SCORE);
    }

    /**
     * Step 5 — selects the top-scoring posts for the 'Related Articles' block.
     *
     * Slices the first rc_top_count entries from the sorted score map and stores
     * their IDs in META_RC_TOP. An empty score map yields an empty array.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_top(int $pid): void {
        $count  = max(2, min(5, (int)($this->opts['rc_top_count'] ?? 3)));
        $scores = maybe_unserialize(get_post_meta($pid, self::META_RC_SCORES, true));
        if (!is_array($scores) || empty($scores)) {
            update_post_meta($pid, self::META_RC_TOP,       []);
            update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_TOP);
            return;
        }
        $top = array_keys(array_slice($scores, 0, $count, true));
        update_post_meta($pid, self::META_RC_TOP,       $top);
        update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_TOP);
    }

    /**
     * Step 6 — selects posts for the 'You Might Also Like' block.
     *
     * Takes the next rc_bottom_count highest-scoring posts from the candidates
     * that were not already selected for the top block, storing them in META_RC_BOTTOM.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_bottom(int $pid): void {
        $count   = max(3, min(10, (int)($this->opts['rc_bottom_count'] ?? 5)));
        $scores  = maybe_unserialize(get_post_meta($pid, self::META_RC_SCORES, true));
        $top_ids = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP, true));
        if (!is_array($scores) || empty($scores)) {
            update_post_meta($pid, self::META_RC_BOTTOM,    []);
            update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_BOTTOM);
            return;
        }
        $top_ids = is_array($top_ids) ? array_map('intval', $top_ids) : [];
        // Exclude posts already in the top block
        $remaining = array_diff_key($scores, array_flip($top_ids));
        $bottom    = array_keys(array_slice($remaining, 0, $count, true));
        update_post_meta($pid, self::META_RC_BOTTOM,    $bottom);
        update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_BOTTOM);
    }

    /**
     * Step 7 — validates and filters the top and bottom output lists.
     *
     * Removes self-references and any post IDs that are no longer published. Also
     * ensures no post appears in both blocks. Updates the stored lists in place.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_validate_out(int $pid): void {
        $top_raw = maybe_unserialize(get_post_meta($pid, self::META_RC_TOP,    true));
        $bot_raw = maybe_unserialize(get_post_meta($pid, self::META_RC_BOTTOM, true));
        $top_ids = is_array($top_raw) ? array_map('intval', $top_raw) : [];
        $bot_ids = is_array($bot_raw) ? array_map('intval', $bot_raw) : [];

        // Remove self-references and unpublished posts
        $top_ids = array_values(array_filter($top_ids,
            fn($id) => $id !== $pid && get_post_status($id) === 'publish'));
        $bot_ids = array_values(array_filter($bot_ids,
            fn($id) => $id !== $pid && get_post_status($id) === 'publish' && !in_array($id, $top_ids)));

        update_post_meta($pid, self::META_RC_TOP,       $top_ids);
        update_post_meta($pid, self::META_RC_BOTTOM,    $bot_ids);
        update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_VALIDATE_OUT);
    }

    /**
     * Step 8 — marks the pipeline run as complete.
     *
     * Writes status = 'complete', the current RC_VERSION, and the current timestamp
     * so the validate step can detect staleness on the next run.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return void
     */
    private function rc_step_complete(int $pid): void {
        update_post_meta($pid, self::META_RC_STATUS,    'complete');
        update_post_meta($pid, self::META_RC_VERSION,   self::RC_VERSION);
        update_post_meta($pid, self::META_RC_GENERATED, time());
        update_post_meta($pid, self::META_RC_LAST_STEP, self::RC_STEP_COMPLETE);
    }

    // ── RC helpers ────────────────────────────────────────────────────────────

    /**
     * Computes a fingerprint for a post based on the signals used in RC scoring.
     *
     * Covers title, categories, tags, and the three summary meta fields. Any change
     * to these values invalidates the existing Related Articles output.
     *
     * @since 4.10.0
     * @param int $pid Post ID.
     * @return string MD5 hash string representing the current scoring inputs.
     */
    private function rc_fingerprint(int $pid): string {
        $cats = wp_get_post_categories($pid, ['fields' => 'ids']);
        $tags = array_map(fn($t) => $t->term_id, wp_get_post_tags($pid));
        sort($cats); sort($tags);
        return md5(serialize([
            get_the_title($pid),
            $cats,
            $tags,
            get_post_meta($pid, self::META_SUM_WHAT, true),
            get_post_meta($pid, self::META_SUM_WHY,  true),
            get_post_meta($pid, self::META_SUM_KEY,  true),
        ]));
    }

    /**
     * Extracts significant lowercase keywords from a text string for RC overlap scoring.
     *
     * Strips HTML, lowercases, removes punctuation, and filters out words shorter than
     * three characters and a hardcoded list of common English stop words.
     *
     * @since 4.10.0
     * @param string $text Plain or HTML text to tokenise.
     * @return string[] Unique, lowercased, stop-word-filtered keyword tokens.
     */
    private function rc_keywords(string $text): array {
        static $stop = ['the','a','an','is','in','to','of','and','for','with','on','at','by',
                        'from','as','it','its','be','or','that','this','was','are','how','why',
                        'what','when','your','you','we','our','has','have','had','not','but',
                        'more','also','can','will','about','up','if','do','so','all','into'];
        $text  = strtolower(wp_strip_all_tags($text));
        $text  = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stop));
        return array_values(array_unique($words));
    }

}
