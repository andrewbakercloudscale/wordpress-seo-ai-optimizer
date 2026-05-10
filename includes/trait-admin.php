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
        // ── Welcome / API key setup notice (shown after first activation) ────
        if (current_user_can('manage_options') && get_option('cs_seo_show_welcome')) {
            if (isset($_GET['_cs_dismiss_welcome']) && check_admin_referer('cs_dismiss_welcome')) {
                delete_option('cs_seo_show_welcome');
                update_option('cs_seo_welcome_shown', 1);
            } else {
                $settings_url = esc_url(admin_url('tools.php?page=cs-seo-optimizer#ai'));
                $dismiss_url  = esc_url(wp_nonce_url(add_query_arg('_cs_dismiss_welcome', '1'), 'cs_dismiss_welcome'));
                echo '<div class="notice" style="border-left:4px solid #22c55e;padding:0;overflow:hidden;background:#fff;">';
                echo '<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#0e6b8f 100%);color:#fff;padding:14px 20px;display:flex;align-items:center;gap:12px;">';
                echo '<span style="font-size:24px">🚀</span>';
                echo '<strong style="font-size:15px">' . esc_html__( 'CloudScale SEO Optimizer is installed! You\'re 2 minutes from AI-powered SEO.', 'cloudscale-seo-ai-optimizer' ) . '</strong>';
                echo '</div>';
                echo '<div style="padding:16px 20px;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">';
                echo '<div style="flex:1;min-width:260px;">';
                echo '<p style="margin:0 0 8px;font-weight:700;color:#0f172a;">Step 1 — Get a free API key (takes 60 seconds)</p>';
                echo '<p style="margin:0 0 6px;color:#374151;">• <strong>Anthropic Claude</strong> (recommended): ';
                echo '<a href="https://console.anthropic.com/" target="_blank" rel="noopener" style="color:#2563eb;">console.anthropic.com</a>';
                echo ' → Sign up → API Keys → Create Key</p>';
                echo '<p style="margin:0;color:#374151;">• <strong>Google Gemini</strong> (free tier): ';
                echo '<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener" style="color:#2563eb;">aistudio.google.com</a>';
                echo ' → Sign in → Create API Key</p>';
                echo '</div>';
                echo '<div style="flex:1;min-width:200px;">';
                echo '<p style="margin:0 0 8px;font-weight:700;color:#0f172a;">Step 2 — Paste it in AI Settings</p>';
                echo '<p style="margin:0;color:#374151;">Go to <strong>SEO Optimizer → AI Settings</strong>, paste your key, click <strong>Save</strong>, then click <strong>Test Key</strong> to confirm it works.</p>';
                echo '</div>';
                echo '<div style="display:flex;flex-direction:column;gap:8px;align-items:flex-start;">';
                echo '<a href="' . $settings_url . '" class="button button-primary" style="white-space:nowrap">' . esc_html__( '→ Go to AI Settings', 'cloudscale-seo-ai-optimizer' ) . '</a>';
                echo '<a href="' . $dismiss_url . '" style="font-size:12px;color:#6b7280;">' . esc_html__( 'Dismiss', 'cloudscale-seo-ai-optimizer' ) . '</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
            }
        }

        // ── Robots.txt rename confirmation ───────────────────────────────────
        $bak = get_option('cs_seo_robots_bak');
        if ($bak !== false) {
            // Dismiss handler — requires both capability and valid nonce.
            if (isset($_GET['_cs_dismiss_robotsbak']) && current_user_can('manage_options') && check_admin_referer('cs_dismiss_robotsbak')) {
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

        // ── Deactivation modal (plugins page only) ────────────────────────────
        if ($screen && $screen->id === 'plugins') {
            $is_subscriber = !empty($this->ai_opts['proxy_enabled'])
                && !empty($this->ai_opts['proxy_license_key'])
                && ($this->ai_opts['proxy_status'] ?? '') === 'active';
            $manage_url = $is_subscriber
                ? esc_url('https://api.andrewbaker.ninja/manage?key=' . rawurlencode($this->ai_opts['proxy_license_key']))
                : '';
            wp_register_script('cs-seo-deact-js', false, [], self::VERSION, true);
            wp_enqueue_script('cs-seo-deact-js');
            wp_add_inline_script('cs-seo-deact-js', '(function(){
                var IS_SUB = ' . ($is_subscriber ? 'true' : 'false') . ';
                var MANAGE = ' . wp_json_encode($manage_url) . ';
                function initDeactModal() {
                    var deactLink = document.querySelector("a[href*=deactivate][href*=cloudscale-seo-ai-optimizer]");
                    if (!deactLink) return;
                    var modal = document.createElement("div");
                    modal.style.cssText = "display:none;position:fixed;inset:0;z-index:999999;background:rgba(0,0,0,.55);align-items:center;justify-content:center";
                    var inner = IS_SUB
                        ? "<div style=\"background:#fff;border-radius:12px;padding:32px 28px;max-width:440px;width:90%;text-align:center\"><div style=\"font-size:32px;margin-bottom:12px\">⚡</div><h2 style=\"font-size:17px;font-weight:700;color:#1d2327;margin:0 0 10px\">Wait — you have an active AI subscription!</h2><p style=\"font-size:13px;color:#50575e;margin:0 0 16px\">Deactivating pauses the plugin but <strong>does not cancel your subscription</strong>.<br>To cancel your billing, <a href=\""+MANAGE+"\" target=\"_blank\" style=\"color:#6366f1\">visit your manage page</a>.<br><strong>Deleting the plugin auto-cancels your subscription.</strong></p><div style=\"display:flex;gap:10px;justify-content:center\"><button id=\"cs-deact-cancel-btn\" style=\"padding:9px 20px;border:1px solid #d1d5db;background:#f9fafb;border-radius:6px;font-size:13px;cursor:pointer\">Keep Plugin</button><a id=\"cs-deact-go\" href=\"\" style=\"padding:9px 20px;background:#1d2327;color:#fff;border-radius:6px;font-size:13px;text-decoration:none\">Deactivate</a></div></div>"
                        : "<div style=\"background:#fff;border-radius:12px;padding:32px 28px;max-width:420px;width:90%;text-align:center\"><div style=\"font-size:32px;margin-bottom:12px\">👋</div><h2 style=\"font-size:17px;font-weight:700;color:#1d2327;margin:0 0 10px\">Thanks for using CloudScale SEO AI</h2><p style=\"font-size:13px;color:#50575e;margin:0 0 16px\">Any feedback helps us improve. Totally optional!</p><div style=\"display:flex;gap:10px;justify-content:center\"><button id=\"cs-deact-cancel-btn\" style=\"padding:9px 20px;border:1px solid #d1d5db;background:#f9fafb;border-radius:6px;font-size:13px;cursor:pointer\">Keep Plugin</button><a id=\"cs-deact-go\" href=\"\" style=\"padding:9px 20px;background:#1d2327;color:#fff;border-radius:6px;font-size:13px;text-decoration:none\">Continue Deactivating</a></div></div>";
                    modal.innerHTML = inner;
                    document.body.appendChild(modal);
                    deactLink.addEventListener("click", function(e) {
                        e.preventDefault();
                        modal.style.display = "flex";
                        document.getElementById("cs-deact-go").href = deactLink.href;
                    });
                    document.getElementById("cs-deact-cancel-btn") && document.getElementById("cs-deact-cancel-btn").addEventListener("click", function() {
                        modal.style.display = "none";
                    });
                    modal.addEventListener("click", function(e) {
                        if (e.target === modal) modal.style.display = "none";
                    });
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", initDeactModal);
                } else {
                    initDeactModal();
                }
            })();');
        }

        if (!$this->is_our_page()) return;

        // Register a no-op handle so we can attach inline style and scripts to it.
        wp_register_style('cs-seo-admin', false, [], self::VERSION);
        wp_enqueue_style('cs-seo-admin');
        wp_add_inline_style('cs-seo-admin', '#wpfooter { display:none !important; } #wpcontent, #wpbody-content { padding-bottom:0 !important; }');
        // Settings page CSS — moved from an echoed <style> block to comply with PCP.
        wp_add_inline_style('cs-seo-admin', $this->admin_page_css());
        wp_add_inline_style('cs-seo-admin', $this->onboarding_page_css());
        wp_add_inline_style('cs-seo-admin', $this->audit_page_css());

        // Register a no-op script handle for inline scripts that have no external file.
        wp_register_script('cs-seo-admin-js', false, [], self::VERSION, true);
        wp_enqueue_script('cs-seo-admin-js');

        // Pass PHP values needed by inline scripts as a JS object.
        wp_localize_script('cs-seo-admin-js', 'csSeoAdmin', [
            'defaultPrompt'   => self::default_prompt(),
            'defaultRobotsTxt' => self::default_robots_txt(),
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('cs_seo_nonce'),
            'sitemapIndexUrl' => home_url('/sitemap.xml'),
            'minChars'        => (int) ($this->ai_opts['min_chars'] ?? 140),
            'maxChars'        => (int) ($this->ai_opts['max_chars'] ?? 160),
            'hasApiKey'       => !empty(trim((string) ($this->ai_opts['anthropic_key'] ?? '')))
                || !empty(trim((string) ($this->ai_opts['gemini_key'] ?? '')))
                || (!empty($this->ai_opts['proxy_enabled']) && !empty($this->ai_opts['proxy_license_key'])),
            'siteUrl'         => home_url('/'),
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

        // Explain modal buttons and widget link hover effects (from trait-admin.php)
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var explainBtns = document.querySelectorAll(".ab-explain-btn");
                explainBtns.forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        var modalId = btn.getAttribute("data-modal-id");
                        var modal = document.getElementById(modalId);
                        if (modal) modal.style.display = "flex";
                    });
                });
                var closeBtns = document.querySelectorAll(".ab-modal-close");
                closeBtns.forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        var modalId = btn.getAttribute("data-modal-id");
                        var modal = document.getElementById(modalId);
                        if (modal) modal.style.display = "none";
                    });
                });
                var widgetLinks = document.querySelectorAll(".cs-widget-link");
                widgetLinks.forEach(function(link) {
                    link.addEventListener("mouseover", function() {
                        this.style.filter = "brightness(1.15)";
                        this.style.transform = "scale(1.02)";
                    });
                    link.addEventListener("mouseout", function() {
                        this.style.filter = "";
                        this.style.transform = "";
                    });
                });
                var settingsLinks = document.querySelectorAll(".cs-settings-link");
                settingsLinks.forEach(function(link) {
                    link.addEventListener("mouseover", function() {
                        this.style.filter = "brightness(1.15)";
                        this.style.transform = "scale(1.03)";
                    });
                    link.addEventListener("mouseout", function() {
                        this.style.filter = "";
                        this.style.transform = "";
                    });
                });
            });'
        );

        // Tab switching and card toggle (from trait-settings-page.php)
        wp_add_inline_script('cs-seo-admin-js',
            'document.addEventListener("DOMContentLoaded", function() {
                var tabBtns = document.querySelectorAll(".ab-tab");
                tabBtns.forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        var tab = btn.getAttribute("data-tab");
                        // Delegate to abTab() so localStorage is kept in sync.
                        if (typeof window.abTab === "function") {
                            window.abTab(tab, btn);
                        } else {
                            document.querySelectorAll(".ab-tab").forEach(function(t) { t.classList.remove("active"); });
                            document.querySelectorAll(".ab-pane").forEach(function(p) { p.classList.remove("active"); });
                            btn.classList.add("active");
                            var pane = document.getElementById("ab-pane-" + tab);
                            if (pane) pane.classList.add("active");
                        }
                    });
                });
                var toggleBtns = document.querySelectorAll(".ab-toggle-card-btn");
                toggleBtns.forEach(function(btn) {
                    btn.addEventListener("click", function() {
                        var cardId = btn.getAttribute("data-card-id");
                        var card = document.querySelector("." + cardId);
                        if (!card) return;
                        var body = card.querySelector(".ab-zone-body");
                        if (!body) return;
                        var isHidden = body.style.display === "none";
                        body.style.display = isHidden ? "" : "none";
                        btn.innerHTML = isHidden ? "&#9660; Hide Details" : "&#9658; Show Details";
                        if (isHidden && !card.dataset.loaded) {
                            card.dataset.loaded = "1";
                            var autoLoaders = {
                                "ab-card-update-posts": function() { if (typeof abLoadPosts === "function") abLoadPosts(); },
                                "ab-card-alt":          function() { if (typeof altLoad    === "function") altLoad(); },
                                "ab-card-summary":      function() { if (typeof sumLoad    === "function") sumLoad(); },
                            };
                            if (autoLoaders[cardId]) autoLoaders[cardId]();
                        }
                    });
                });
            });'
        );

        // Related Articles style preview (was an inline <script> tag — moved to comply with PCP).
        wp_add_inline_script( 'cs-seo-admin-js', <<<'JS'
        (function(){
            var links = [
                'How to optimise your WordPress site for speed',
                'Top 10 SEO mistakes and how to fix them',
                'Complete guide to schema markup'
            ];
            var pal = {
                '1':  {fmt:'gradient',accent:'#4f46e5',grad:'linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%)'},
                '2':  {fmt:'dark',    accent:'#fbbf24',dk:'#1e1b4b'},
                '3':  {fmt:'minimal', accent:'#2563eb'},
                '4':  {fmt:'cards',   accent:'#059669'},
                '5':  {fmt:'stripe',  accent:'#64748b'},
                '6':  {fmt:'magazine',accent:'#dc2626',grad:'linear-gradient(120deg,#7f1d1d 0%,#dc2626 60%,#f87171 100%)'},
                '7':  {fmt:'gradient',accent:'#0891b2',grad:'linear-gradient(120deg,#0c4a6e 0%,#0891b2 60%,#38bdf8 100%)'},
                '8':  {fmt:'dark',    accent:'#f59e0b',dk:'#1c1917'},
                '9':  {fmt:'gradient',accent:'#1e40af',grad:'linear-gradient(120deg,#0f172a 0%,#1e40af 60%,#3b82f6 100%)'},
                '10': {fmt:'minimal', accent:'#374151'},
                '11': {fmt:'gradient',accent:'#16a34a',grad:'linear-gradient(120deg,#14532d 0%,#16a34a 60%,#4ade80 100%)'},
                '12': {fmt:'gradient',accent:'#e11d48',grad:'linear-gradient(120deg,#881337 0%,#e11d48 60%,#fb7185 100%)'},
                '13': {fmt:'gradient',accent:'#ea580c',grad:'linear-gradient(120deg,#7c2d12 0%,#ea580c 60%,#fb923c 100%)'},
                '14': {fmt:'dark',    accent:'#38bdf8',dk:'#020617'},
                '15': {fmt:'dark',    accent:'#a78bfa',dk:'#2d1b69'},
                '16': {fmt:'minimal', accent:'#0d9488'},
                '17': {fmt:'minimal', accent:'#e11d48'},
                '18': {fmt:'stripe',  accent:'#d97706'},
                '19': {fmt:'bordered',accent:'#475569'},
                '20': {fmt:'pill',    accent:'#7c3aed'}
            };
            function preview(style) {
                var p = pal[style] || pal['1'];
                var a = p.accent, g = p.grad||'', dk = p.dk||'';
                var items = links.map(function(t,i){ return item(i+1,t,p); }).join('');
                if (p.fmt==='dark') {
                    return '<div style="background:'+dk+';border-radius:10px;padding:14px 16px;">'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:'+a+';margin:0 0 10px;">Related Articles</div>'
                        +'<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;">'+items+'</ul></div>';
                }
                if (p.fmt==='minimal') {
                    return '<div style="background:#fff;border-top:3px solid '+a+';padding:12px 16px;">'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:'+a+';margin:0 0 8px;">Related Articles</div>'
                        +'<ul style="margin:0;padding:0;list-style:none;">'+items+'</ul></div>';
                }
                if (p.fmt==='cards') {
                    return '<div>'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:'+a+';margin:0 0 8px;">Related Articles</div>'
                        +'<div style="display:flex;flex-direction:column;gap:6px;">'+items+'</div></div>';
                }
                if (p.fmt==='stripe') {
                    return '<div style="border-left:4px solid '+a+';padding:12px 16px;background:#fafafa;">'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:'+a+';margin:0 0 8px;">Related Articles</div>'
                        +'<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:7px;">'+items+'</ul></div>';
                }
                if (p.fmt==='magazine') {
                    return '<div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.08);">'
                        +'<div style="background:'+g+';padding:8px 14px;"><span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;">Related Articles</span></div>'
                        +items+'</div>';
                }
                if (p.fmt==='bordered') {
                    return '<div style="background:#fff;border:1.5px solid '+a+';border-radius:10px;padding:14px 16px;">'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:'+a+';margin:0 0 10px;">Related Articles</div>'
                        +'<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;">'+items+'</ul></div>';
                }
                if (p.fmt==='pill') {
                    return '<div style="background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">'
                        +'<div style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7280;margin:0 0 10px;">Related Articles</div>'
                        +'<ul style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:8px;">'+items+'</ul></div>';
                }
                return '<div style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">'
                    +'<div style="background:'+g+';padding:10px 16px;"><span style="font-size:9px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;">Related Articles</span></div>'
                    +'<ul style="margin:0;padding:12px 16px;list-style:none;display:flex;flex-direction:column;gap:8px;">'+items+'</ul></div>';
            }
            function item(n,title,p) {
                var a=p.accent,g=p.grad||'',dk=p.dk||'';
                if (p.fmt==='dark')
                    return '<li style="display:flex;align-items:baseline;gap:8px;margin:0;padding:0;">'
                        +'<span style="font-size:11px;font-weight:700;color:'+a+';min-width:16px;flex-shrink:0;">'+n+'.</span>'
                        +'<span style="color:#fff;font-size:12px;">'+title+'</span></li>';
                if (p.fmt==='minimal')
                    return '<li style="display:flex;align-items:baseline;gap:7px;padding:5px 0;border-bottom:1px solid #f3f4f6;">'
                        +'<span style="font-size:10px;color:#9ca3af;min-width:14px;flex-shrink:0;">'+n+'.</span>'
                        +'<span style="color:#374151;font-size:12px;">'+title+'</span></li>';
                if (p.fmt==='cards')
                    return '<div style="display:flex;align-items:stretch;border:1px solid #e5e7eb;border-left:3px solid '+a+';border-radius:5px;overflow:hidden;">'
                        +'<span style="background:'+a+';color:#fff;font-size:11px;font-weight:700;padding:7px 9px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'+n+'</span>'
                        +'<span style="color:#1f2937;font-size:12px;padding:7px 10px;line-height:1.3;">'+title+'</span></div>';
                if (p.fmt==='stripe')
                    return '<li style="display:flex;align-items:baseline;gap:7px;">'
                        +'<span style="color:'+a+';font-weight:700;font-size:12px;min-width:16px;flex-shrink:0;">'+n+'.</span>'
                        +'<span style="color:#374151;font-size:12px;">'+title+'</span></li>';
                if (p.fmt==='magazine') {
                    var bg=(n%2===0)?'#f9fafb':'#fff';
                    return '<div style="display:flex;align-items:stretch;background:'+bg+';border-bottom:1px solid #f3f4f6;">'
                        +'<span style="background:'+g+';color:#fff;font-size:12px;font-weight:800;width:36px;min-width:36px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">'+n+'</span>'
                        +'<span style="color:#111827;font-size:12px;padding:8px 12px;line-height:1.3;">'+title+'</span></div>';
                }
                if (p.fmt==='bordered')
                    return '<li style="display:flex;align-items:baseline;gap:8px;">'
                        +'<span style="font-size:11px;font-weight:700;color:'+a+';min-width:16px;flex-shrink:0;">'+n+'.</span>'
                        +'<span style="color:#1f2937;font-size:12px;">'+title+'</span></li>';
                if (p.fmt==='pill')
                    return '<li style="display:flex;align-items:center;gap:10px;">'
                        +'<span style="background:'+a+';color:#fff;font-size:10px;font-weight:700;border-radius:20px;min-width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;padding:0 6px;">'+n+'</span>'
                        +'<span style="color:#1f2937;font-size:12px;">'+title+'</span></li>';
                return '<li style="display:flex;align-items:baseline;gap:7px;margin:0;padding:0;">'
                    +'<span style="color:'+a+';font-size:11px;font-weight:700;min-width:16px;flex-shrink:0;">'+n+'.</span>'
                    +'<span style="color:'+a+';font-size:12px;">'+title+'</span></li>';
            }
            var sel = document.getElementById('rc-style-select');
            var box = document.getElementById('rc-style-preview');
            if (!sel || !box) return;
            function update(){ box.innerHTML = preview(sel.value); }
            sel.addEventListener('change', update);
            update();
        })();
        JS
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
            class="ab-explain-btn"
            data-modal-id="<?php echo esc_attr($modal_id); ?>"
            style="background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;cursor:pointer">
            Explain...
        </button>
        <div id="<?php echo esc_attr($modal_id); ?>" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.6);align-items:center;justify-content:center;padding:16px">
            <div style="background:#fff;border-radius:10px;max-width:640px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.4)">
                <div style="background:#1a4a7a;border-radius:10px 10px 0 0;padding:16px 20px;display:flex;justify-content:space-between;align-items:center">
                    <strong style="color:#fff;font-size:15px"><?php echo esc_html($title); ?></strong>
                    <button type="button" class="ab-modal-close" data-modal-id="<?php echo esc_attr($modal_id); ?>"
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
                    <button type="button" class="ab-modal-close" data-modal-id="<?php echo esc_attr($modal_id); ?>"
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
        $health_valid = is_array($health) && isset($health['total'], $health['seo'], $health['images'], $health['posts_with_images'], $health['links'], $health['summaries'], $health['built_at']);

        if ($health_valid) {
            $h_total     = (int) $health['total'];
            $h_built     = (int) $health['built_at'];
            $h_date      = gmdate('d M y', $h_built);
        }

        // ── Site audit (latest scheduled run for this site only — never adhoc) ──
        $audit_sched  = get_option('cs_seo_audit_history', []);
        $last_sched   = (is_array($audit_sched) && !empty($audit_sched)) ? $audit_sched[0] : null;
        $latest_audit = $last_sched;
        $audit_score    = null;
        $audit_fails    = 0;
        $audit_warns    = 0;
        $audit_date     = '';
        if (is_array($latest_audit) && isset($latest_audit['overall'])) {
            $audit_score = (int) $latest_audit['overall'];
            $audit_date  = gmdate('d M y', (int)($latest_audit['timestamp'] ?? 0));
            foreach ((array)($latest_audit['findings'] ?? []) as $f) {
                if (($f['status'] ?? '') === 'fail') $audit_fails++;
                if (($f['status'] ?? '') === 'warn') $audit_warns++;
            }
        }

        // ── Batch status line ─────────────────────────────────────────────────
        $ai_opts         = $this->get_ai_opts();
        $schedule_enabled = (int) ($ai_opts['schedule_enabled'] ?? 0);
        $history         = get_option('cs_seo_batch_history', []);
        $last_run        = (is_array($history) && !empty($history))
            ? $history[count($history) - 1]
            : null;

        if (!$schedule_enabled) {
            $batch_line = '⏸ Batch disabled';
        } elseif (!$last_run) {
            $batch_line = '⏳ Batch pending';
        } else {
            $date_fmt  = gmdate('d M y', strtotime($last_run['date'] ?? ''));
            $desc_done = (int) ($last_run['done']      ?? 0);
            $alt_done  = (int) ($last_run['alt_done']  ?? 0);
            $sum_done  = (int) ($last_run['sum_done']  ?? 0);
            $errors    = (int) ($last_run['errors']    ?? 0) + (int) ($last_run['alt_errors'] ?? 0);
            $batch_line = 'Batch: ' . $date_fmt . ' · ' . $desc_done . ' Posts and ' . $alt_done . ' Images';
            if ($sum_done > 0) $batch_line .= ' and ' . $sum_done . ' Summaries';
            if ($errors) $batch_line .= ' · ' . $errors . ' err';
        }

        // ── Donut chart maths ─────────────────────────────────────────────────
        // Four rings (SEO, Images, Links, Summaries). Each ring sits on its own
        // concentric circle. cx=cy=60, rings from r=46 (outer) down to r=22.
        // stroke-dasharray trick: circumference * pct, circumference * (1-pct).
        // Rings are only drawn when health data is available.
        $donut_rings = [];
        if ($health_valid && $h_total > 0) {
            $ring_defs = [
                ['key' => 'seo',       'label' => 'SEO',       'r' => 46, 'color' => '#818cf8', 'total' => $h_total],
                ['key' => 'summaries', 'label' => 'Summaries', 'r' => 37, 'color' => '#34d399', 'total' => $h_total],
                ['key' => 'links',     'label' => 'Links',     'r' => 29, 'color' => '#fb923c', 'total' => $h_total],
                ['key' => 'images',    'label' => 'Images',    'r' => 21, 'color' => '#f472b6', 'total' => (int)($health['posts_with_images'] ?? $h_total)],
            ];
            foreach ($ring_defs as $rd) {
                $val   = (int)($health[$rd['key']] ?? 0);
                $denom = $rd['total'] > 0 ? $rd['total'] : 1;
                $pct   = min(1.0, $val / $denom);
                $circ  = round(2 * M_PI * $rd['r'], 2);
                $filled  = round($circ * $pct, 2);
                $gap     = round($circ * (1 - $pct), 2);
                $donut_rings[] = [
                    'label'  => $rd['label'],
                    'val'    => $val,
                    'denom'  => $rd['total'],
                    'pct'    => $pct,
                    'r'      => $rd['r'],
                    'color'  => $rd['color'],
                    'circ'   => $circ,
                    'filled' => $filled,
                    'gap'    => $gap,
                ];
            }
        }
        ?>
        <div style="margin:-12px -12px 0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">

            <?php /* ── Dark header band ──────────────────────────────────── */ ?>
            <div style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 60%,#0f172a 100%);
                        padding:14px 16px 12px;border-radius:0;
                        border-bottom:1px solid rgba(99,102,241,0.25)">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <span style="font-size:18px;line-height:1">🥷</span>
                        <span style="color:#e2e8f0;font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase">
                            SEO AI Optimizer
                        </span>
                    </div>
                    <span style="background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.4);
                                 color:#a5b4fc;font-size:10px;font-weight:700;letter-spacing:0.06em;
                                 padding:2px 7px;border-radius:20px">
                        v<?php echo esc_html(self::VERSION); ?>
                    </span>
                </div>
                <p style="margin:0;font-size:11px;color:#94a3b8;line-height:1.4">
                    Meta · ALT text · Sitemaps · Script optimisation
                </p>
            </div>

            <?php /* ── Main body ──────────────────────────────────────────── */ ?>
            <div style="padding:14px 16px 4px;background:#fff">

                <?php if ($health_valid): ?>
                <?php /* ── Donut + legend row ──────────────────────────────── */ ?>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:14px">

                    <?php /* Donut SVG */ ?>
                    <div style="flex-shrink:0;position:relative;width:120px;height:120px">
                        <svg width="120" height="120" viewBox="0 0 120 120"
                             style="transform:rotate(-90deg);overflow:visible">
                            <?php foreach ($donut_rings as $ring): ?>
                            <?php /* track */ ?>
                            <circle cx="60" cy="60"
                                    r="<?php echo esc_attr((string)$ring['r']); ?>"
                                    fill="none"
                                    stroke="rgba(0,0,0,0.06)"
                                    stroke-width="6"/>
                            <?php /* filled arc */ ?>
                            <circle cx="60" cy="60"
                                    r="<?php echo esc_attr((string)$ring['r']); ?>"
                                    fill="none"
                                    stroke="<?php echo esc_attr($ring['color']); ?>"
                                    stroke-width="6"
                                    stroke-linecap="round"
                                    stroke-dasharray="<?php echo esc_attr($ring['filled'] . ' ' . $ring['gap']); ?>"
                                    style="transition:stroke-dasharray 0.8s cubic-bezier(0.4,0,0.2,1)"/>
                            <?php endforeach; ?>
                        </svg>
                        <?php /* Centre label */ ?>
                        <div style="position:absolute;inset:0;display:flex;flex-direction:column;
                                    align-items:center;justify-content:center;pointer-events:none;gap:1px">
                            <span style="font-size:26px;font-weight:900;color:#0f172a;line-height:1;text-shadow:0 0 6px #fff,0 0 12px #fff,0 0 18px #fff">
                                <?php echo esc_html((string)$h_total); ?>
                            </span>
                            <span style="font-size:11px;font-weight:900;color:#0f172a;letter-spacing:0.1em;text-transform:uppercase;line-height:1;text-shadow:0 0 4px #fff,0 0 8px #fff,0 0 12px #fff">
                                posts
                            </span>
                        </div>
                    </div>

                    <?php /* Legend */ ?>
                    <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:7px">
                        <?php /* Posts — baseline row */ ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:3px">
                                <span style="font-size:10px;font-weight:600;color:#475569;letter-spacing:0.03em">Posts</span>
                                <span style="font-size:10px;font-weight:700;color:#2271b1"><?php echo esc_html((string)$h_total); ?></span>
                            </div>
                            <div style="height:4px;background:#f1f5f9;border-radius:2px;overflow:hidden">
                                <div style="height:100%;width:100%;background:#2271b1;border-radius:2px"></div>
                            </div>
                        </div>
                        <?php foreach ($donut_rings as $ring):
                            $pct_int = (int)round($ring['pct'] * 100);
                        ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:3px">
                                <span style="font-size:10px;font-weight:600;color:#475569;letter-spacing:0.03em">
                                    <?php echo esc_html($ring['label']); ?>
                                </span>
                                <span style="font-size:10px;font-weight:700;color:<?php echo esc_attr($ring['color']); ?>">
                                    <?php echo esc_html($ring['val'] . '/' . $ring['denom']); ?>
                                    <span style="color:#94a3b8;font-weight:400">&nbsp;<?php echo esc_html((string)$pct_int); ?>%</span>
                                </span>
                            </div>
                            <div style="height:4px;background:#f1f5f9;border-radius:2px;overflow:hidden">
                                <div style="height:100%;width:<?php echo esc_attr((string)$pct_int); ?>%;
                                            background:<?php echo esc_attr($ring['color']); ?>;
                                            border-radius:2px;transition:width 0.8s ease"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php /* ── Health timestamp + refresh ───────────────────────── */ ?>
                <div style="display:flex;align-items:center;justify-content:space-between;
                            background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;
                            padding:6px 10px;margin-bottom:12px">
                    <span style="font-size:10px;color:#94a3b8">
                        Data from <strong style="color:#64748b"><?php echo esc_html($h_date); ?></strong>
                    </span>
                    <a href="#" id="cs-health-refresh"
                       style="font-size:10px;font-weight:700;color:#6366f1;text-decoration:none;
                              display:inline-flex;align-items:center;gap:3px">
                        <span style="font-size:11px">↺</span> Refresh
                    </a>
                    <span id="cs-health-refresh-status" style="font-size:10px;color:#94a3b8;display:none"></span>
                </div>
                <?php ob_start(); ?>
                document.getElementById('cs-health-refresh').addEventListener('click', function(e) {
                    e.preventDefault();
                    var lnk    = this;
                    var status = document.getElementById('cs-health-refresh-status');
                    lnk.style.opacity = '0.4';
                    status.style.display = 'inline';
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
                        else { status.textContent = '✗ Failed'; lnk.style.opacity = '1'; }
                    })
                    .catch(function() { status.textContent = '✗ Error'; lnk.style.opacity = '1'; });
                });
                <?php wp_add_inline_script('cs-seo-dashboard-js', ob_get_clean()); ?>

                <?php else: ?>
                <?php /* ── No health data yet ────────────────────────────────── */ ?>
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                            padding:14px 16px;margin-bottom:12px;text-align:center">
                    <p style="margin:0 0 8px;font-size:12px;color:#64748b">No health data yet.</p>
                    <button type="button" id="cs-health-run"
                            style="background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff;
                                   border:none;border-radius:6px;font-size:11px;font-weight:700;
                                   padding:6px 14px;cursor:pointer;letter-spacing:0.04em">
                        ▦ Run Health Check
                    </button>
                    <span id="cs-health-run-status" style="display:block;margin-top:6px;font-size:10px;color:#94a3b8"></span>
                </div>
                <?php ob_start(); ?>
                document.getElementById('cs-health-run').addEventListener('click', function() {
                    var btn    = this;
                    var status = document.getElementById('cs-health-run-status');
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
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
                        else { status.textContent = '✗ Failed'; btn.disabled = false; btn.style.opacity = '1'; }
                    })
                    .catch(function() { status.textContent = '✗ Error'; btn.disabled = false; btn.style.opacity = '1'; });
                });
                <?php wp_add_inline_script('cs-seo-dashboard-js', ob_get_clean()); ?>
                <?php endif; ?>

                <?php /* ── Site audit score strip ────────────────────────────── */ ?>
                <?php if ($audit_score !== null):
                    $a_color  = $audit_score >= 80 ? '#16a34a' : ($audit_score >= 60 ? '#d97706' : '#dc2626');
                    $a_bg     = $audit_score >= 80 ? '#f0fdf4' : ($audit_score >= 60 ? '#fffbeb' : '#fef2f2');
                    $a_label  = $audit_score >= 80 ? 'Good' : ($audit_score >= 60 ? 'Needs work' : 'Critical');
                    $a_days   = $audit_date ? (int)round((time() - (int)($latest_audit['timestamp'] ?? 0)) / 86400) : null;
                    $a_ago    = $a_days !== null ? ($a_days === 0 ? 'today' : ($a_days === 1 ? '1 day ago' : $a_days . ' days ago')) : $audit_date;
                ?>
                <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer&tab=siteaudit')); ?>"
                   style="display:flex;align-items:center;gap:14px;
                          background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                          padding:12px 14px;margin-bottom:12px;text-decoration:none;
                          transition:border-color 0.15s"
                   onmouseover="this.style.borderColor='#a5b4fc'" onmouseout="this.style.borderColor='#e2e8f0'">
                    <?php /* Score circle */ ?>
                    <div style="flex-shrink:0;width:52px;height:52px;border-radius:50%;
                                border:3px solid <?php echo esc_attr($a_color); ?>;
                                background:<?php echo esc_attr($a_bg); ?>;
                                display:flex;align-items:center;justify-content:center">
                        <span style="font-size:18px;font-weight:800;color:<?php echo esc_attr($a_color); ?>;line-height:1">
                            <?php echo esc_html((string)$audit_score); ?>
                        </span>
                    </div>
                    <?php /* Centre: label + date + badges */ ?>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:2px">
                            <?php echo esc_html($a_label); ?>
                        </div>
                        <div style="font-size:11px;color:#94a3b8;margin-bottom:8px">
                            Audited <?php echo esc_html($a_ago); ?>
                        </div>
                        <div style="display:flex;gap:8px">
                            <div style="background:<?php echo esc_attr( $audit_fails === 0 ? '#f0fdf4' : '#fef2f2' ); ?>;
                                        border-radius:6px;padding:4px 10px;text-align:center;min-width:48px">
                                <div style="font-size:15px;font-weight:800;color:<?php echo esc_attr( $audit_fails === 0 ? '#16a34a' : '#dc2626' ); ?>;line-height:1.2">
                                    <?php echo esc_html((string)$audit_fails); ?>
                                </div>
                                <div style="font-size:8px;font-weight:700;color:<?php echo esc_attr( $audit_fails === 0 ? '#16a34a' : '#dc2626' ); ?>;letter-spacing:0.06em;text-transform:uppercase">
                                    Critical
                                </div>
                            </div>
                            <div style="background:<?php echo esc_attr( $audit_warns === 0 ? '#f8fafc' : '#fffbeb' ); ?>;
                                        border-radius:6px;padding:4px 10px;text-align:center;min-width:48px">
                                <div style="font-size:15px;font-weight:800;color:<?php echo esc_attr( $audit_warns === 0 ? '#64748b' : '#d97706' ); ?>;line-height:1.2">
                                    <?php echo esc_html((string)$audit_warns); ?>
                                </div>
                                <div style="font-size:8px;font-weight:700;color:<?php echo esc_attr( $audit_warns === 0 ? '#64748b' : '#d97706' ); ?>;letter-spacing:0.06em;text-transform:uppercase">
                                    Warnings
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php /* Right: quick actions */ ?>
                    <div style="flex-shrink:0;display:flex;flex-direction:column;gap:6px;align-items:stretch;min-width:90px">
                        <span style="display:block;background:linear-gradient(135deg,#6366f1,#4f46e5);
                                     color:#fff;font-size:10px;font-weight:700;letter-spacing:0.03em;
                                     padding:5px 10px;border-radius:5px;text-align:center">
                            View Report
                        </span>
                        <span onclick="event.preventDefault();event.stopPropagation();
                                       window.location='<?php echo esc_js(admin_url('tools.php?page=cs-seo-optimizer&tab=siteaudit')); ?>';"
                              style="display:block;background:#f1f5f9;border:1px solid #e2e8f0;
                                     color:#475569;font-size:10px;font-weight:700;letter-spacing:0.03em;
                                     padding:5px 10px;border-radius:5px;text-align:center;cursor:pointer">
                            Run Audit
                        </span>
                        <span onclick="event.preventDefault();event.stopPropagation();
                                       window.location='<?php echo esc_js(admin_url('tools.php?page=cs-seo-optimizer&tab=siteaudit')); ?>';"
                              style="display:block;background:#f1f5f9;border:1px solid #e2e8f0;
                                     color:#475569;font-size:10px;font-weight:700;letter-spacing:0.03em;
                                     padding:5px 10px;border-radius:5px;text-align:center;cursor:pointer">
                            Fix Issues
                        </span>
                    </div>
                </a>
                <?php endif; ?>

                <?php /* ── Status strip: missing meta + pipeline queue ─────── */ ?>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;flex-wrap:wrap">
                    <?php if ($missing_auto_run > 0): ?>
                    <div style="display:inline-flex;align-items:center;gap:5px;
                                background:#fef2f2;border:1px solid #fecaca;
                                border-radius:5px;padding:4px 9px">
                        <span style="width:6px;height:6px;border-radius:50%;background:#ef4444;flex-shrink:0"></span>
                        <span style="font-size:10px;font-weight:600;color:#dc2626">
                            <?php echo esc_html((string)$missing_auto_run); ?> posts missing meta
                        </span>
                    </div>
                    <?php else: ?>
                    <div style="display:inline-flex;align-items:center;gap:5px;
                                background:#f0fdf4;border:1px solid #bbf7d0;
                                border-radius:5px;padding:4px 9px">
                        <span style="width:6px;height:6px;border-radius:50%;background:#22c55e;flex-shrink:0"></span>
                        <span style="font-size:10px;font-weight:600;color:#16a34a">All meta up-to-date</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pending_pipeline > 0): ?>
                    <div style="display:inline-flex;align-items:center;gap:5px;
                                background:#fffbeb;border:1px solid #fde68a;
                                border-radius:5px;padding:4px 9px">
                        <span style="font-size:10px;font-weight:600;color:#d97706">
                            ⟳ <?php echo esc_html((string)$pending_pipeline); ?> job<?php echo $pending_pipeline !== 1 ? 's' : ''; ?> queued
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php /* ── CTA buttons ───────────────────────────────────────── */ ?>
                <div style="display:flex;flex-direction:column;gap:8px;padding-bottom:12px">
                    <?php
                        if (!$schedule_enabled) {
                            $btn_bg     = 'linear-gradient(135deg,#6b7280 0%,#4b5563 100%)';
                            $btn_shadow = 'rgba(107,114,128,0.35)';
                            $btn_icon   = '⏸';
                        } elseif (!$last_run) {
                            $btn_bg     = 'linear-gradient(135deg,#f59e0b 0%,#b45309 100%)';
                            $btn_shadow = 'rgba(245,158,11,0.4)';
                            $btn_icon   = '⏳';
                        } else {
                            $btn_bg     = 'linear-gradient(135deg,#22c55e 0%,#15803d 100%)';
                            $btn_shadow = 'rgba(34,197,94,0.4)';
                            $btn_icon   = '✓';
                        }
                    ?>
                    <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer&tab=batch')); ?>"
                       style="display:flex;align-items:center;justify-content:space-between;
                              background:<?php echo esc_attr($btn_bg); ?>;
                              color:#fff;font-weight:700;font-size:12px;
                              padding:9px 14px;border-radius:7px;text-decoration:none;
                              box-shadow:0 3px 12px <?php echo esc_attr($btn_shadow); ?>;
                              transition:opacity 0.15s ease,transform 0.15s ease"
                       onmouseover="this.style.opacity='0.9';this.style.transform='translateY(-1px)'"
                       onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'">
                        <span style="display:flex;align-items:center;gap:6px">
                            <span style="font-size:13px"><?php echo esc_html($btn_icon); ?></span>
                            <span><?php echo esc_html($batch_line); ?></span>
                        </span>
                        <span style="opacity:0.6;font-size:11px">→</span>
                    </a>
                    <a href="<?php echo esc_url(admin_url('tools.php?page=cs-seo-optimizer')); ?>"
                       style="display:flex;align-items:center;justify-content:space-between;
                              background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 100%);
                              color:#c7d2fe;font-weight:700;font-size:12px;
                              padding:9px 14px;border-radius:7px;text-decoration:none;
                              box-shadow:0 3px 12px rgba(99,102,241,0.3);
                              border:1px solid rgba(99,102,241,0.3);
                              transition:opacity 0.15s ease,transform 0.15s ease"
                       onmouseover="this.style.opacity='0.9';this.style.transform='translateY(-1px)'"
                       onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'">
                        <span style="display:flex;align-items:center;gap:6px">
                            <span style="font-size:14px">🔭</span>
                            <span>Open SEO AI Optimizer</span>
                        </span>
                        <span style="opacity:0.4;font-size:11px">→</span>
                    </a>
                </div>

            </div><?php /* end main body */ ?>
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
            '🤖 CloudScale SEO AI',
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
                         'rc_enable','rc_top_count','rc_bottom_count','enable_redirects'];
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
        foreach (['site_name','site_lang','title_suffix','home_title','twitter_handle','person_name','person_job_title','works_for_name','target_audience','writing_tone'] as $k) {
            $out[$k] = sanitize_text_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['home_desc','default_desc','sameas','knows_about','robots_txt','sitemap_exclude','defer_js_excludes'] as $k) {
            $out[$k] = sanitize_textarea_field(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach (['default_og_image','person_url','person_image','works_for_url'] as $k) {
            $out[$k] = esc_url_raw(array_key_exists($k, $in) ? (string)$in[$k] : (string)($existing[$k] ?? $d[$k]));
        }
        foreach ([
            'enable_og','enable_schema_person','enable_schema_website','enable_schema_article',
            'enable_schema_breadcrumbs','show_summary_box','strip_tracking_params','enable_sitemap','enable_llms_txt',
            'noindex_search','noindex_404','noindex_attachment','noindex_author_archives','noindex_tag_archives',
            'block_ai_bots','sitemap_taxonomies','defer_js','minify_html','defer_fonts',
            'rc_enable','rc_top_enabled','rc_bottom_enabled','rc_use_categories','rc_use_tags','rc_use_summary',
            'enable_redirects',
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
        // RC display style — one of '1'–'20'
        $rc_style_in = sanitize_text_field(wp_unslash($in['rc_style'] ?? ''));
        $out['rc_style'] = in_array($rc_style_in, ['1','2','3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','20'], true) ? $rc_style_in : '1';
        // Sitemap post types — array of sanitized strings
        $allowed_types = array_map(fn($pt) => $pt->name, get_post_types(['public' => true], 'objects'));
        if (array_key_exists('sitemap_post_types', $in)) {
            $chosen = array_intersect((array)$in['sitemap_post_types'], $allowed_types);
            $out['sitemap_post_types'] = array_values($chosen) ?: ['post'];
        } else {
            $out['sitemap_post_types'] = $existing['sitemap_post_types'] ?? $d['sitemap_post_types'];
        }
        // Safety net: any key present in defaults() but not yet handled above is preserved
        // as a sanitized string. This prevents new options from silently dropping on save.
        foreach ($d as $k => $default_val) {
            if ( array_key_exists($k, $out) ) continue;
            $raw = array_key_exists($k, $in) ? $in[$k] : ($existing[$k] ?? $default_val);
            $out[$k] = is_array($raw) ? array_map('sanitize_text_field', (array)$raw) : sanitize_text_field((string)$raw);
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
