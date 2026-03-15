<?php
/**
 * Admin menu, settings registration, asset enqueuing, and admin notices.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Admin {
    /**
     * Displays an admin notice after the physical robots.txt has been renamed on activation.
     *
     * @since 4.10.0
     * @return void
     */
    public function admin_notices(): void {
        // Show post-rename confirmation if the backup flag is set
        $bak = get_option('cs_seo_robots_bak');
        if ($bak !== false) {
            // Dismiss handler
            if (isset($_GET['_cs_dismiss_robotsbak']) && check_admin_referer('cs_dismiss_robotsbak')) {
                delete_option('cs_seo_robots_bak');
            } else {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>' . esc_html__( 'CloudScale SEO', 'cloudscale-seo-ai-optimizer' ) . ':</strong> ';
                echo esc_html__( 'The physical robots.txt file has been renamed to robots.txt.bak. The plugin is now managing your robots.txt. Your original rules have been preserved — review the Robots.txt card and merge anything you want to keep.', 'cloudscale-seo-ai-optimizer' ) . '</p>';
                echo '<p><a href="' . esc_url( wp_nonce_url( admin_url( 'tools.php?page=cs-seo-optimizer&_cs_dismiss_robotsbak=1' ), 'cs_dismiss_robotsbak' ) ) . '">' . esc_html__( 'Dismiss', 'cloudscale-seo-ai-optimizer' ) . '</a></p>';
                echo '</div>';
            }
        }
    }

    /**
     * Enqueues styles and scripts for the settings page, dashboard widget, and post metabox.
     *
     * @since 4.10.22
     * @return void
     */
    public function admin_enqueue_assets(): void {
        $screen = get_current_screen();

        // Dashboard page — no-op handle for dashboard widget JS.
        if ($screen && $screen->id === 'dashboard') {
            wp_register_script('cs-seo-dashboard-js', false, [], self::VERSION, true);
            wp_enqueue_script('cs-seo-dashboard-js');
            wp_localize_script('cs-seo-dashboard-js', 'csSeoWidget', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('cs_seo_nonce'),
            ]);
        }

        // Post/page edit screens — no-op handle for metabox JS.
        if ($screen && in_array($screen->base, ['post', 'page'], true)) {
            wp_register_script('cs-seo-metabox-js', false, [], self::VERSION, true);
            wp_enqueue_script('cs-seo-metabox-js');
            wp_localize_script('cs-seo-metabox-js', 'csSeoMetabox', [
                'nonce' => wp_create_nonce('cs_seo_nonce'),
            ]);
        }

        if (!$this->is_our_page()) return;

        // Register a no-op handle so we can attach inline style and scripts to it.
        wp_register_style('cs-seo-admin', false, [], self::VERSION);
        wp_enqueue_style('cs-seo-admin');
        wp_add_inline_style('cs-seo-admin', '#wpfooter { display:none !important; } #wpcontent, #wpbody-content { padding-bottom:0 !important; }');
        // Settings page CSS — moved from an echoed <style> block to comply with PCP.
        wp_add_inline_style('cs-seo-admin', $this->admin_page_css());

        // Register a no-op script handle for inline scripts that have no external file.
        wp_register_script('cs-seo-admin-js', false, [], self::VERSION, true);
        wp_enqueue_script('cs-seo-admin-js');

        // Pass PHP values needed by inline scripts as a JS object.
        wp_localize_script('cs-seo-admin-js', 'csSeoAdmin', [
            'defaultPrompt'   => self::default_prompt(),
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('cs_seo_nonce'),
            'sitemapIndexUrl' => home_url('/sitemap.xml'),
            'minChars'        => (int) ($this->ai_opts['min_chars'] ?? 140),
            'maxChars'        => (int) ($this->ai_opts['max_chars'] ?? 160),
            'hasApiKey'       => !empty(trim((string) ($this->ai_opts['anthropic_key'] ?? ''))),
        ]);

        // Sitemap + llms.txt preview scripts — moved from echoed <script> blocks to comply with PCP.
        wp_add_inline_script('cs-seo-admin-js', $this->llms_preview_js());
        wp_add_inline_script('cs-seo-admin-js', $this->sitemap_preview_js());

        // Reset-prompt button (was inline at line 3540).
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var resetBtn = document.getElementById("ab-reset-prompt");
                if (resetBtn) {
                    resetBtn.addEventListener("click", function() {
                        document.getElementById("ab-prompt-field").value = csSeoAdmin.defaultPrompt;
                    });
                }
            });'
        );

        // Defer JS excludes toggle (was inline at line 4574).
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var deferToggle = document.getElementById("ab-defer-toggle");
                if (deferToggle) {
                    deferToggle.addEventListener("change", function() {
                        document.getElementById("ab-defer-excludes-wrap").style.display = this.checked ? "" : "none";
                    });
                }
            });'
        );

        // Scheduled batch day toggle (was inline at line 4654).
        wp_add_inline_script('cs-seo-admin-js',
            'function csToggleSchedDays(enabled) {
                document.querySelectorAll(".cs-sched-day").forEach(function(cb) {
                    cb.disabled = !enabled;
                    cb.closest("label").style.opacity = enabled ? "1" : "0.4";
                });
            }'
        );
    }

    /**
     * Outputs an "Explain..." button and modal dialog describing a settings group.
     *
     * @since 4.0.0
     * @param string $id    Unique suffix for button and modal element IDs.
     * @param string $title Modal heading text.
     * @param array  $items Array of items, each with 'name', 'desc', and 'rec' keys.
     * @return void
     */
    private function explain_btn(string $id, string $title, array $items): void {
        $btn_id   = 'ab-explain-btn-' . $id;
        $modal_id = 'ab-explain-modal-' . $id;
        ?>
        <button type="button" id="<?php echo esc_attr($btn_id); ?>"
            onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='flex'"
            style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:12px;font-weight:600;padding:5px 14px;cursor:pointer">
            Explain...
        </button>
        <div id="<?php echo esc_attr($modal_id); ?>" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:16px">
            <div style="background:#fff;border-radius:10px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.4)">
                <div style="background:#1a4a7a;border-radius:10px 10px 0 0;padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
                    <strong style="color:#fff;font-size:15px"><?php echo esc_html($title); ?></strong>
                    <button type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='none'"
                        style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:16px;font-weight:700;padding:2px 10px;cursor:pointer;line-height:1">✕</button>
                </div>
                <div style="padding:20px 24px;font-size:13px;line-height:1.6;color:#1d2327">
                    <?php foreach ($items as $item):
                        $rec = $item['rec'];
                        $is_on  = strpos($rec, 'Recommended') !== false;
                        $is_opt = strpos($rec, 'Optional') !== false;
                        $bg  = $is_on ? '#edfaef' : ($is_opt ? '#f6f7f7' : '#f0f6fc');
                        $col = $is_on ? '#1a7a34' : ($is_opt ? '#50575e' : '#1a4a7a');
                        $bdr = $is_on ? '#1a7a34' : ($is_opt ? '#c3c4c7' : '#2271b1');
                    ?>
                    <div style="border:1px solid #e0e0e0;border-radius:6px;padding:12px 14px;margin-bottom:10px">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;flex-wrap:wrap">
                            <strong style="font-size:13px"><?php echo esc_html($item['name']); ?></strong>
                            <span style="background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($col); ?>;border:1px solid <?php echo esc_attr($bdr); ?>;border-radius:4px;font-size:11px;font-weight:600;padding:1px 8px;white-space:nowrap"><?php echo esc_html($rec); ?></span>
                        </div>
                        <p style="margin:0;color:#50575e;font-size:12px;line-height:1.5"><?php echo esc_html($item['desc']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:12px 24px 20px;text-align:right">
                    <button type="button" onclick="document.getElementById('<?php echo esc_attr($modal_id); ?>').style.display='none'"
                        style="background:#1a4a7a;border:none;border-radius:6px;color:#fff;font-size:13px;font-weight:600;padding:8px 24px;cursor:pointer">
                        Got it
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Clears the admin footer text on the plugin settings page.
     *
     * @since 4.0.0
     * @param string $text Default footer text.
     * @return string
     */
    public function admin_footer_text($text): string {
        if (!$this->is_our_page()) return $text;
        return '';
    }

    /**
     * Clears the admin footer version string on the plugin settings page.
     *
     * @since 4.0.0
     * @param string $text Default footer version text.
     * @return string
     */
    public function admin_footer_version($text): string {
        if (!$this->is_our_page()) return $text;
        return '';
    }

    /**
     * Returns true when the current admin page is the plugin settings page.
     *
     * @since 4.0.0
     * @return bool
     */
    private function is_our_page(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- not processing form data, only reading page slug for admin UI routing
        return isset($_GET['page']) && sanitize_key(wp_unslash($_GET['page'])) === 'cs-seo-optimizer';
    }

    /**
     * Registers the SEO health dashboard widget.
     *
     * @since 4.9.14
     * @return void
     */
    public function register_dashboard_widget(): void {
        wp_add_dashboard_widget(
            'cs_seo_dashboard_widget',
            wp_kses_post( '🥷 AndrewBaker.Ninja AI SEO Optimizer <span style="font-size:11px;font-weight:400;color:#999;margin-left:6px">v' . esc_html( self::VERSION ) . '</span>' ),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Renders the SEO health dashboard widget content.
     *
     * @since 4.9.14
     * @return void
     */
    public function render_dashboard_widget(): void {
        // ── Auto pipeline metrics ─────────────────────────────────────────────
        $missing_auto_run = $this->count_posts_missing_auto_run();
        $cron_array       = _get_cron_array() ?: [];
        $pending_pipeline = 0;
        foreach ( $cron_array as $hooks ) {
            if ( isset( $hooks['cs_seo_auto_run_pipeline'] ) ) $pending_pipeline += count( $hooks['cs_seo_auto_run_pipeline'] );
            if ( isset( $hooks['cs_seo_cleanup_pipeline'] ) )  $pending_pipeline += count( $hooks['cs_seo_cleanup_pipeline'] );
        }

        // ── SEO Health cache ──────────────────────────────────────────────────
        $health       = get_option(self::OPT_HEALTH_CACHE, null);
        $health_valid = is_array($health) && isset($health['total'], $health['seo'], $health['images'], $health['links'], $health['summaries'], $health['built_at']);

        if ($health_valid) {
            $h_total     = (int) $health['total'];
            $h_built     = (int) $health['built_at'];
            $h_date      = gmdate('d M y', $h_built);
            $h_nonce     = wp_create_nonce('cs_seo_nonce');
            $h_ajax      = admin_url('admin-ajax.php');

            // Compute colour for each metric pill.
            // Posts pill is always slate — it is the baseline, not a health signal.
            // All other pills: green >= 90%, amber >= 60%, red < 60%.
            $pill_color  = static function(int $count, int $total): string {
                if ($total === 0) return '#6b7280'; // slate — nothing to measure
                $pct = $count / $total * 100;
                if ($pct >= 90) return '#16a34a'; // green
                if ($pct >= 60) return '#d97706'; // amber
                return '#dc2626'; // red
            };

            $pills = [
                ['label' => 'Posts',     'value' => $h_total,                    'color' => '#2271b1'], // blue baseline
                ['label' => 'SEO',       'value' => (int) $health['seo'],        'color' => $pill_color((int) $health['seo'],        $h_total)],
                ['label' => 'Images',    'value' => (int) $health['images'],     'color' => $pill_color((int) $health['images'],     $h_total)],
                ['label' => 'Links',     'value' => (int) $health['links'],      'color' => $pill_color((int) $health['links'],      $h_total)],
                ['label' => 'Summaries', 'value' => (int) $health['summaries'],  'color' => $pill_color((int) $health['summaries'],  $h_total)],
            ];
        }

        // ── Batch status line ─────────────────────────────────────────────────
        $ai_opts         = $this->get_ai_opts();
        $schedule_enabled = (int) ($ai_opts['schedule_enabled'] ?? 0);
        $history         = get_option('cs_seo_batch_history', []);
        $last_run        = (is_array($history) && !empty($history))
            ? $history[count($history) - 1]
            : null;

        if (!$schedule_enabled) {
            $batch_line  = '⏸ Batch disabled';
            $batch_style = 'background:linear-gradient(135deg,#6b7280 0%,#4b5563 100%);box-shadow:0 3px 10px rgba(107,114,128,0.35);';
        } elseif (!$last_run) {
            $batch_line  = '⏳ Batch pending';
            $batch_style = 'background:linear-gradient(135deg,#f59e0b 0%,#b45309 100%);box-shadow:0 3px 10px rgba(245,158,11,0.4);';
        } else {
            $date_fmt  = gmdate('d M y', strtotime($last_run['date'] ?? ''));
            $desc_done = (int) ($last_run['done']      ?? 0);
            $alt_done  = (int) ($last_run['alt_done']  ?? 0);
            $sum_done  = (int) ($last_run['sum_done']  ?? 0);
            $errors    = (int) ($last_run['errors']    ?? 0) + (int) ($last_run['alt_errors'] ?? 0);
            $batch_line = 'Batch: ' . $date_fmt . ' · ' . $desc_done . ' Posts and ' . $alt_done . ' Images';
            if ($sum_done > 0) $batch_line .= ' and ' . $sum_done . ' Summaries';
            if ($errors) $batch_line .= ' · ' . $errors . ' err';
            $batch_style = 'background:linear-gradient(135deg,#22c55e 0%,#15803d 100%);box-shadow:0 3px 10px rgba(34,197,94,0.4);';
        }
        ?>
        <div style="padding:4px 0 8px">
            <p style="margin:0 0 10px;font-size:13px;color:#50575e;line-height:1.5">
                CloudScale SEO AI Optimizer is keeping your site sharp —
                meta descriptions, ALT text, sitemaps, and render-blocking scripts all handled.
            </p>
            <?php if ($health_valid): ?>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin:0 0 12px">
                <?php foreach ($pills as $pill): ?>
                <span style="display:inline-flex;align-items:center;gap:5px;
                             background:<?php echo esc_attr($pill['color']); ?>;
                             color:#fff;font-size:11px;font-weight:700;
                             padding:4px 10px;border-radius:20px;white-space:nowrap">
                    <?php echo esc_html($pill['value'] . ' ' . $pill['label']); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($health_valid): ?>
            <p style="margin:0 0 10px;font-size:11px;color:#9ca3af">
                Health data from <?php echo esc_html($h_date); ?> &middot;
                <a href="#" id="cs-health-refresh"
                   style="color:#6366f1;text-decoration:none;font-weight:600"
                   onmouseover="this.style.textDecoration='underline'"
                   onmouseout="this.style.textDecoration='none'">Refresh</a>
                <span id="cs-health-refresh-status" style="margin-left:6px;color:#9ca3af"></span>
            </p>
            <?php ob_start(); ?>
            document.getElementById('cs-health-refresh').addEventListener('click', function(e) {
                e.preventDefault();
                var status = document.getElementById('cs-health-refresh-status');
                status.textContent = '⟳ Rebuilding…';
                var params = new URLSearchParams({
                    action: 'cs_seo_rebuild_health',
                    nonce:  csSeoWidget.nonce
                });
                fetch(csSeoWidget.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) { location.reload(); }
                    else { status.textContent = '✗ Failed'; }
                })
                .catch(function() { status.textContent = '✗ Error'; });
            });
            <?php wp_add_inline_script('cs-seo-dashboard-js', ob_get_clean()); ?>
            <?php else: ?>
            <p style="margin:0 0 10px">
                <button type="button" id="cs-health-run"
                        style="background:#6366f1;color:#fff;border:none;border-radius:6px;
                               font-size:12px;font-weight:700;padding:6px 14px;cursor:pointer">
                    ▦ Run Health Check
                </button>
                <span id="cs-health-run-status" style="margin-left:8px;font-size:11px;color:#9ca3af"></span>
            </p>
            <?php ob_start(); ?>
            document.getElementById('cs-health-run').addEventListener('click', function() {
                var btn    = this;
                var status = document.getElementById('cs-health-run-status');
                btn.disabled = true;
                status.textContent = '⟳ Building…';
                var params = new URLSearchParams({
                    action: 'cs_seo_rebuild_health',
                    nonce:  csSeoWidget.nonce
                });
                fetch(csSeoWidget.ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: params
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) { location.reload(); }
                    else { status.textContent = '✗ Failed'; btn.disabled = false; }
                })
                .catch(function() { status.textContent = '✗ Error'; btn.disabled = false; });
            });
            <?php wp_add_inline_script('cs-seo-dashboard-js', ob_get_clean()); ?>
            <?php endif; ?>
            <p style="margin:0 0 10px;font-size:12px;color:#6b7280;">
                <?php if ( $missing_auto_run > 0 ) : ?>
                <span style="color:#dc2626;font-weight:700;"><?php echo esc_html( (string) $missing_auto_run ); ?></span>
                <?php esc_html_e( 'posts need AI auto run', 'cloudscale-seo-ai-optimizer' ); ?>
                <?php else : ?>
                <span style="color:#16a34a;font-weight:700;"><?php esc_html_e( 'All posts have run AI auto run', 'cloudscale-seo-ai-optimizer' ); ?></span>
                <?php endif; ?>
                <?php if ( $pending_pipeline > 0 ) : ?>
                &middot;
                <span style="color:#d97706;font-weight:700;"><?php echo esc_html( (string) $pending_pipeline ); ?></span>
                <?php esc_html_e( 'pipeline job(s) queued', 'cloudscale-seo-ai-optimizer' ); ?>
                <?php endif; ?>
            </p>
            <div style="display:flex;flex-direction:column;gap:10px">
                <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer&tab=batch')); ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          <?php echo esc_attr($batch_style); ?>
                          color:#fff;font-weight:700;font-size:12px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <?php echo esc_html($batch_line); ?>
                </a>
                <a href="https://andrewbaker.ninja" target="_blank" rel="noopener"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          background:linear-gradient(135deg,#f953c6 0%,#b91d73 40%,#4f46e5 100%);
                          color:#fff;font-weight:700;font-size:13px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          box-shadow:0 3px 10px rgba(249,83,198,0.4);
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <span style="font-size:15px">🥷</span> Visit AndrewBaker.Ninja
                </a>
                <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer')); ?>"
                   style="display:flex;align-items:center;justify-content:center;gap:8px;
                          background:linear-gradient(135deg,#0ea5e9 0%,#0369a1 100%);
                          color:#fff;font-weight:700;font-size:13px;padding:10px 16px;
                          border-radius:8px;text-decoration:none;
                          box-shadow:0 3px 10px rgba(14,165,233,0.35);
                          transition:filter 0.15s,transform 0.15s"
                   onmouseover="this.style.filter='brightness(1.15)';this.style.transform='scale(1.02)'"
                   onmouseout="this.style.filter='';this.style.transform=''">
                    <span style="font-size:15px">🔭</span> View SEO AI Optimizer
                </a>
            </div>
        </div>
    <?php }

    /**
     * Adds the plugin page to the WordPress Tools menu.
     *
     * @since 4.0.0
     * @return void
     */
    public function admin_menu(): void {
        add_management_page(
            'CloudScale SEO AI Optimizer v' . self::VERSION,
            'CloudScale SEO AI',
            'manage_options',
            'cs-seo-optimizer',
            [$this, 'settings_page']
        );
    }

    /**
     * Registers plugin option groups with the WordPress Settings API.
     *
     * @since 4.0.0
     * @return void
     */
    public function register_settings(): void {
        register_setting('cs_seo_group', self::OPT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_opts'],
            'default'           => self::defaults(),
        ]);
        register_setting('cs_seo_ai_group', self::AI_OPT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_ai_opts'],
            'default'           => self::ai_defaults(),
        ]);

        // Cron self-heal: if schedule is enabled but the cron event is missing
        // (e.g. after plugin update or deactivation/reactivation), re-register it silently.
        $ai = get_option(self::AI_OPT, []);
        if ((int)($ai['schedule_enabled'] ?? 0) && !wp_next_scheduled('cs_seo_daily_batch')) {
            wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'cs_seo_daily_batch');
        }
    }

    /**
     * Sanitises and validates the main plugin options array for the Settings API.
     *
     * @since 4.0.0
     * @param mixed $in Raw input from the Settings API.
     * @return array Sanitised options array.
     */
    public function sanitize_opts($in): array {
        $in  = is_array($in) ? $in : [];
        $d   = self::defaults();

        // If the incoming data has no recognisable fields it's likely a spurious
        // call (e.g. plugin reinstall touching the option). Preserve existing data.
        $known_fields = ['site_name','enable_og','robots_txt','sitemap_post_types','enable_sitemap','enable_llms_txt',
                         'home_title','person_name','block_ai_bots','noindex_search','title_suffix','defer_js',
                         'rc_enable','rc_top_count','rc_bottom_count'];
        $has_known = false;
        foreach ($known_fields as $f) {
            if (array_key_exists($f, $in)) { $has_known = true; break; }
        }
        if (!$has_known) {
            return $this->opts ?: $d;
        }

        $out = [];

        // Merge with existing saved values — partial form submissions (e.g. Sitemap tab only)
        // must not wipe fields that live on other tabs.
        $existing = $this->opts ?: $d;

        $was_sitemap  = (int)($existing['enable_sitemap'] ?? 0);
        $now_sitemap  = array_key_exists('enable_sitemap', $in) ? (empty($in['enable_sitemap']) ? 0 : 1) : $was_sitemap;
        $was_llms     = (int)($existing['enable_llms_txt'] ?? 0);
        $now_llms     = array_key_exists('enable_llms_txt', $in) ? (empty($in['enable_llms_txt']) ? 0 : 1) : $was_llms;
        if ($now_sitemap !== $was_sitemap || $now_llms !== $was_llms) {
            add_action('shutdown', 'flush_rewrite_rules');
        }
        foreach (['site_name','site_lang','title_suffix','home_title','twitter_handle','person_name','person_job_title'] as $k) {
            $out[$k] = sanitize_text_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['home_desc','default_desc','sameas','robots_txt','sitemap_exclude','defer_js_excludes'] as $k) {
            $out[$k] = sanitize_textarea_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['default_og_image','person_url','person_image'] as $k) {
            $out[$k] = esc_url_raw(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach ([
            'enable_og','enable_schema_person','enable_schema_website','enable_schema_article',
            'enable_schema_breadcrumbs','show_summary_box','strip_tracking_params','enable_sitemap','enable_llms_txt',
            'noindex_search','noindex_404','noindex_attachment','noindex_author_archives','noindex_tag_archives',
            'block_ai_bots','sitemap_taxonomies','defer_js','minify_html','defer_fonts',
            'rc_enable','rc_top_enabled','rc_bottom_enabled','rc_use_categories','rc_use_tags','rc_use_summary',
        ] as $k) {
            $out[$k] = array_key_exists($k, $in) ? (empty($in[$k]) ? 0 : 1) : (int)($existing[$k] ?? $d[$k]);
        }
        // RC integer settings
        foreach (['rc_top_count','rc_bottom_count','rc_pool_size'] as $k) {
            $val = array_key_exists($k, $in) ? (int)$in[$k] : (int)($existing[$k] ?? $d[$k]);
            $out[$k] = max(1, min(20, $val));
        }
        // RC excluded categories — array of integer term ids
        if (array_key_exists('rc_exclude_cats', $in)) {
            $out['rc_exclude_cats'] = array_map('absint', (array)$in['rc_exclude_cats']);
        } else {
            $out['rc_exclude_cats'] = $existing['rc_exclude_cats'] ?? $d['rc_exclude_cats'];
        }
        // Sitemap post types — array of sanitized strings
        $allowed_types = array_map(fn($pt) => $pt->name, get_post_types(['public' => true], 'objects'));
        if (array_key_exists('sitemap_post_types', $in)) {
            $chosen = array_intersect((array)$in['sitemap_post_types'], $allowed_types);
            $out['sitemap_post_types'] = array_values($chosen) ?: ['post'];
        } else {
            $out['sitemap_post_types'] = $existing['sitemap_post_types'] ?? $d['sitemap_post_types'];
        }
        return $out;
    }

    /**
     * Sanitises and validates the AI configuration options array for the Settings API.
     *
     * @since 4.0.0
     * @param mixed $in Raw input from the Settings API.
     * @return array Sanitised AI options array.
     */
    public function sanitize_ai_opts($in): array {
        $in      = is_array($in) ? $in : [];
        $d       = self::ai_defaults();
        $current = $this->get_ai_opts(); // existing saved values — preserve anything not in $in

        $days = array_intersect(
            (array)($in['schedule_days'] ?? $current['schedule_days'] ?? []),
            ['sun','mon','tue','wed','thu','fri','sat']
        );
        $was_enabled = (int) $current['schedule_enabled'];
        $now_enabled = array_key_exists('schedule_enabled', $in) ? (empty($in['schedule_enabled']) ? 0 : 1) : $was_enabled;

        // Schedule cron when enabled, unschedule when disabled.
        // Also self-heal: if enabled but cron is missing (e.g. after plugin update/reactivation), re-register it.
        if ($now_enabled) {
            if (!wp_next_scheduled('cs_seo_daily_batch')) {
                wp_schedule_event(strtotime('tomorrow midnight'), 'daily', 'cs_seo_daily_batch');
            }
        } else {
            wp_clear_scheduled_hook('cs_seo_daily_batch');
        }

        // Use submitted value if present, otherwise fall back to current saved value, then default.
        return [
            'ai_provider'      => in_array($in['ai_provider'] ?? $current['ai_provider'] ?? 'anthropic', ['anthropic','gemini'], true) ? ($in['ai_provider'] ?? $current['ai_provider'] ?? 'anthropic') : 'anthropic',
            'anthropic_key'    => sanitize_text_field((string)(array_key_exists('anthropic_key', $in) ? $in['anthropic_key'] : $current['anthropic_key'])),
            'gemini_key'       => sanitize_text_field((string)(array_key_exists('gemini_key', $in) ? $in['gemini_key'] : ($current['gemini_key'] ?? ''))),
            'model'            => sanitize_text_field((string)($in['model'] ?? $current['model'] ?? $d['model'])),
            'overwrite'        => array_key_exists('overwrite', $in) ? (empty($in['overwrite']) ? 0 : 1) : ($current['overwrite'] ?? 0),
            'min_chars'        => max(100, min(160, (int)($in['min_chars'] ?? $current['min_chars'] ?? $d['min_chars']))),
            'max_chars'        => max(100, min(200, (int)($in['max_chars'] ?? $current['max_chars'] ?? $d['max_chars']))),
            'alt_excerpt_chars'=> max(100, min(2000, (int)($in['alt_excerpt_chars'] ?? $current['alt_excerpt_chars'] ?? $d['alt_excerpt_chars']))),
            'prompt'           => sanitize_textarea_field((string)($in['prompt'] ?? $current['prompt'] ?? $d['prompt'])),
            'auto_run_enabled'   => array_key_exists('auto_run_enabled',   $in) ? (empty($in['auto_run_enabled'])   ? 0 : 1) : ($current['auto_run_enabled']   ?? 0),
            'auto_run_on_update' => array_key_exists('auto_run_on_update', $in) ? (empty($in['auto_run_on_update']) ? 0 : 1) : ($current['auto_run_on_update'] ?? 0),
            'schedule_enabled' => $now_enabled,
            'schedule_days'    => array_values($days),
        ];
    }
}
