<?php
/**
 * SEO Site Audit — runs live checks against the site and stores last 50 audits.
 *
 * Checks: Security Headers, Homepage Metadata, llms.txt, robots.txt,
 * WordPress Hardening, Structured Data/Schema, AEO answer paragraphs,
 * Category Pages.
 *
 * Results stored in WP option `cs_seo_audit_history` (array, last 50).
 * Each entry: { timestamp, date, overall, sections:{...}, findings:[...] }
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.21.6
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Site_Audit {

    // ── AJAX registration ─────────────────────────────────────────────────────

    public function init_site_audit(): void {
        add_action( 'wp_ajax_cs_seo_run_site_audit', [ $this, 'ajax_run_site_audit' ] );
    }

    public function ajax_audit_quickfix(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $action = sanitize_key( wp_unslash( $_POST['quickfix'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( $action === 'enable_breadcrumbs' ) {
            $opts = get_option( self::OPT, [] );
            $opts['enable_schema_breadcrumbs'] = 1;
            update_option( self::OPT, $opts );
            wp_send_json_success( [ 'message' => 'Breadcrumb schema enabled. Re-run audit to confirm.' ] );
            return;
        }

        if ( $action === 'enable_speakable_schema' ) {
            $opts = get_option( self::OPT, [] );
            $opts['enable_schema_speakable'] = 1;
            update_option( self::OPT, $opts );
            wp_send_json_success( [ 'message' => 'Speakable schema enabled. Re-run audit to confirm.' ] );
            return;
        }

        if ( $action === 'add_archive_redirects' ) {
            $posts_url  = get_post_type_archive_link( 'post' ) ?: home_url( '/' );
            $redirects  = get_option( 'cs_seo_redirects', [] );
            if ( ! is_array( $redirects ) ) $redirects = [];
            $added = [];
            foreach ( [ '/blog/', '/posts/' ] as $path ) {
                $already = array_filter( $redirects, static fn( $r ) => rtrim( $r['from'] ?? '', '/' ) === rtrim( $path, '/' ) );
                if ( ! $already ) {
                    $redirects[] = [ 'from' => $path, 'to' => $posts_url, 'post_id' => 0, 'created' => time(), 'hits' => 0, 'last_hit' => null ];
                    $added[] = $path;
                }
            }
            update_option( 'cs_seo_redirects', $redirects, false );
            $msg = $added ? implode( ', ', $added ) . ' → ' . $posts_url . ' added.' : 'Redirects already exist.';
            wp_send_json_success( [ 'message' => $msg ] );
            return;
        }

        if ( $action === 'save_cat_seo' ) {
            // phpcs:disable WordPress.Security.NonceVerification.Missing
            $term_id = absint( wp_unslash( $_POST['term_id'] ?? 0 ) );
            $title   = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
            $desc    = sanitize_textarea_field( wp_unslash( $_POST['desc']  ?? '' ) );
            $intro   = wp_kses_post( wp_unslash( $_POST['intro'] ?? '' ) );
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            if ( ! $term_id ) { wp_send_json_error( 'Missing term_id.' ); return; }
            if ( $title ) update_term_meta( $term_id, self::META_TERM_TITLE, $title );
            if ( $desc )  update_term_meta( $term_id, self::META_TERM_DESC,  $desc );
            if ( $intro ) update_term_meta( $term_id, self::META_TERM_INTRO, $intro );
            wp_send_json_success( [ 'term_id' => $term_id ] );
            return;
        }

        if ( $action === 'generate_howto_schema' ) {
            $posts = get_posts( [ 'numberposts' => 3, 'post_status' => 'publish', 'orderby' => 'comment_count', 'order' => 'DESC' ] );
            if ( empty( $posts ) ) { wp_send_json_error( 'No published posts found.' ); return; }

            $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
            $key      = $provider === 'gemini'
                ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
                : trim( (string) ( $this->ai_opts['anthropic_key'] ?? '' ) );
            $model    = $this->resolve_model( trim( (string) ( $this->ai_opts['model'] ?? '' ) ), $provider );
            if ( ! $key ) { wp_send_json_error( 'No AI API key configured.' ); return; }

            $saved = 0;
            foreach ( $posts as $p ) {
                $content = wp_strip_all_tags( (string) $p->post_content );
                if ( mb_strlen( $content ) < 100 ) continue;
                $excerpt = mb_substr( $content, 0, 3000 );

                $system   = 'You are an SEO expert. Generate a HowTo JSON-LD schema for the given article by identifying the key actionable steps a reader would take. Return ONLY a valid JSON object — no markdown, no explanation.';
                $user_msg = "Article title: " . get_the_title( $p ) . "\n\nContent excerpt:\n" . $excerpt
                    . "\n\nGenerate a HowTo schema with 4-6 logical steps extracted or inferred from this article. Return ONLY this JSON:\n"
                    . '{"@context":"https://schema.org","@type":"HowTo","name":"...","description":"...","step":[{"@type":"HowToStep","name":"...","text":"..."},...]}'  ;

                try {
                    $raw    = $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 1000 );
                    $clean  = trim( (string) preg_replace( '/^```(?:json)?\s*/i', '', preg_replace( '/```\s*$/i', '', trim( $raw ) ) ) );
                    $schema = json_decode( $clean, true );
                    if ( is_array( $schema ) && isset( $schema['@type'] ) && $schema['@type'] === 'HowTo' ) {
                        update_post_meta( $p->ID, self::META_PAGE_SCHEMA, wp_json_encode( $schema ) );
                        $saved++;
                        break;
                    }
                } catch ( \Throwable $e ) {
                    // continue
                }
            }
            if ( $saved === 0 ) { wp_send_json_error( 'AI did not return valid HowTo schema.' ); return; }
            wp_send_json_success( [ 'message' => "HowTo schema generated and saved to {$saved} post(s). Re-run audit to confirm." ] );
            return;
        }

        if ( $action === 'generate_faq_schema' ) {
            $posts = get_posts( [ 'numberposts' => 20, 'post_status' => 'publish', 'orderby' => 'comment_count', 'order' => 'DESC', 'post_type' => 'post' ] );
            if ( empty( $posts ) ) { wp_send_json_error( 'No published posts found.' ); return; }

            $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
            $key      = $provider === 'gemini'
                ? trim( (string) ( $this->ai_opts['gemini_key'] ?? '' ) )
                : trim( (string) ( $this->ai_opts['anthropic_key'] ?? '' ) );
            $model    = $this->resolve_model( trim( (string) ( $this->ai_opts['model'] ?? '' ) ), $provider );
            if ( ! $key ) { wp_send_json_error( 'No AI API key configured.' ); return; }

            $saved = 0;
            foreach ( $posts as $p ) {
                if ( trim( (string) get_post_meta( $p->ID, self::META_PAGE_SCHEMA, true ) ) ) continue;
                $content = wp_strip_all_tags( (string) $p->post_content );
                if ( mb_strlen( $content ) < 100 ) continue;
                $excerpt = mb_substr( $content, 0, 3000 );

                $system   = 'You are an SEO expert. Generate a FAQPage JSON-LD schema object for the given article. Return ONLY a valid JSON object — no markdown, no explanation.';
                $user_msg = "Article title: " . get_the_title( $p ) . "\n\nContent excerpt:\n" . $excerpt
                    . "\n\nGenerate a FAQPage schema with 4-5 natural questions a reader would ask, with concise answers derived from the article. Return ONLY this JSON object:\n"
                    . '{"@context":"https://schema.org","@type":"FAQPage","mainEntity":[{"@type":"Question","name":"...","acceptedAnswer":{"@type":"Answer","text":"..."}},...]}';

                try {
                    $raw    = $this->dispatch_ai( $provider, $key, $model, $system, $user_msg, null, 1000 );
                    $clean  = trim( (string) preg_replace( '/^```(?:json)?\s*/i', '', preg_replace( '/```\s*$/i', '', trim( $raw ) ) ) );
                    $schema = json_decode( $clean, true );
                    if ( is_array( $schema ) && isset( $schema['@type'] ) && $schema['@type'] === 'FAQPage' ) {
                        update_post_meta( $p->ID, self::META_PAGE_SCHEMA, wp_json_encode( $schema ) );
                        $saved++;
                    }
                } catch ( \Throwable $e ) {
                    // continue to next post
                }
            }
            if ( $saved === 0 ) { wp_send_json_error( 'AI did not return valid FAQPage schema — posts may already have schema set.' ); return; }
            wp_send_json_success( [ 'message' => "FAQPage schema generated and saved to {$saved} post(s). Re-run audit to confirm." ] );
            return;
        }

        wp_send_json_error( 'Unknown action.' );
    }

    public function ajax_run_site_audit(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions' );
        }
        set_time_limit( 90 );

        $raw_url    = isset( $_POST['target_url'] ) ? sanitize_url( wp_unslash( $_POST['target_url'] ) ) : '';
        $target_url = ( filter_var( $raw_url, FILTER_VALIDATE_URL ) ) ? $raw_url : home_url( '/' );
        $is_adhoc   = rtrim( $target_url, '/' ) !== rtrim( home_url( '/' ), '/' );

        $result               = $this->run_seo_audit_checks( $target_url );
        $result['target_url'] = $target_url;
        $result['is_adhoc']   = $is_adhoc;

        ob_start();
        $this->render_seo_audit_dashboard( $result );
        $result['dashboard_html'] = ob_get_clean();

        $opt_key = $is_adhoc ? 'cs_seo_audit_history_adhoc' : 'cs_seo_audit_history';
        $history = get_option( $opt_key, [] );
        if ( ! is_array( $history ) ) {
            $history = [];
        }
        array_unshift( $history, $result );
        $history = array_slice( $history, 0, 50 );
        update_option( $opt_key, $history, false );

        wp_send_json_success( $result );
    }

    // ── Internal HTTP helper ──────────────────────────────────────────────────

    private function seo_audit_http( string $url, int $timeout = 10, bool $follow = false ): array {
        // Add cache-buster so Cloudflare and any reverse proxy always returns a fresh response.
        $url  = add_query_arg( 'cs_audit', time(), $url );
        $resp = wp_remote_get( $url, [
            'timeout'     => $timeout,
            'sslverify'   => false,
            'redirection' => $follow ? 5 : 0,
            'user-agent'  => 'Mozilla/5.0 (compatible; CS-SEO-Audit/1.0)',
            'headers'     => [ 'Cache-Control' => 'no-cache', 'Pragma' => 'no-cache' ],
        ] );
        if ( is_wp_error( $resp ) ) {
            return [ 'code' => 0, 'headers' => [], 'body' => '', 'error' => $resp->get_error_message() ];
        }
        $headers_obj = wp_remote_retrieve_headers( $resp );
        return [
            'code'    => (int) wp_remote_retrieve_response_code( $resp ),
            'headers' => method_exists( $headers_obj, 'getAll' ) ? $headers_obj->getAll() : (array) $headers_obj,
            'body'    => wp_remote_retrieve_body( $resp ),
            'error'   => '',
        ];
    }

    // ── Core audit logic ──────────────────────────────────────────────────────

    private function run_seo_audit_checks( string $target_url = '' ): array {
        $site_url    = ( $target_url !== '' ) ? $target_url : home_url( '/' );
        $target_base = rtrim( $site_url, '/' );
        $is_external = rtrim( $site_url, '/' ) !== rtrim( home_url( '/' ), '/' );
        $findings    = [];
        $sections    = [];

        // Fetch homepage (follow redirects so we land on the real page)
        $home = $this->seo_audit_http( $site_url, 15, true );
        $home_body    = $home['body'];
        $home_headers = $home['headers'];

        // ── 1. Security Headers ───────────────────────────────────────────────
        $sec_score = 0;
        $sec_w     = 0;

        foreach ( [
            'strict-transport-security' => [ 'HSTS (Strict-Transport-Security)', 2 ],
            'x-frame-options'           => [ 'X-Frame-Options', 1 ],
            'x-content-type-options'    => [ 'X-Content-Type-Options', 1 ],
            'referrer-policy'           => [ 'Referrer-Policy', 1 ],
            'permissions-policy'        => [ 'Permissions-Policy', 1 ],
        ] as $hname => [ $label, $w ] ) {
            $val = $home_headers[ $hname ] ?? null;
            $ok  = ! empty( $val );
            $sec_score += $ok ? $w : 0;
            $sec_w     += $w;
            $findings[] = [
                'section' => 'security_headers',
                'check'   => $label,
                'status'  => $ok ? 'ok' : 'fail',
                'value'   => $val ?: 'absent',
                'note'    => $ok ? '' : 'Header missing — add via nginx or Cloudflare.',
            ];
        }
        // CSP (enforcement > report-only > absent)
        $csp_enforced = ! empty( $home_headers['content-security-policy'] );
        $csp_report   = ! empty( $home_headers['content-security-policy-report-only'] );
        $sec_w += 2;
        if ( $csp_enforced ) {
            $sec_score += 2;
            $csp_status = 'ok'; $csp_val = 'enforcing'; $csp_note = '';
        } elseif ( $csp_report ) {
            $sec_score += 1;
            $csp_status = 'warn'; $csp_val = 'report-only';
            $csp_note   = 'CSP in report-only — promote to enforcement for full XSS protection.';
        } else {
            $csp_status = 'fail'; $csp_val = 'absent'; $csp_note = 'No CSP header found.';
        }
        $findings[] = [ 'section' => 'security_headers', 'check' => 'Content-Security-Policy', 'status' => $csp_status, 'value' => $csp_val, 'note' => $csp_note ];

        // X-Powered-By should be absent
        $xpb = $home_headers['x-powered-by'] ?? null;
        $sec_w++;
        if ( empty( $xpb ) ) {
            $sec_score++;
            $findings[] = [ 'section' => 'security_headers', 'check' => 'X-Powered-By removed', 'status' => 'ok', 'value' => 'absent', 'note' => '' ];
        } else {
            $findings[] = [ 'section' => 'security_headers', 'check' => 'X-Powered-By removed', 'status' => 'fail', 'value' => (string) $xpb, 'note' => 'PHP version visible to scanners. Remove via nginx fastcgi_hide_header.' ];
        }
        // Server header — Cloudflare or absent = good
        $srv = strtolower( (string) ( $home_headers['server'] ?? '' ) );
        $sec_w++;
        if ( str_contains( $srv, 'cloudflare' ) || empty( $srv ) ) {
            $sec_score++;
            $findings[] = [ 'section' => 'security_headers', 'check' => 'Server header masked', 'status' => 'ok', 'value' => (string) ( $home_headers['server'] ?? 'absent' ), 'note' => '' ];
        } else {
            $findings[] = [ 'section' => 'security_headers', 'check' => 'Server header masked', 'status' => 'warn', 'value' => (string) ( $home_headers['server'] ?? '' ), 'note' => 'Server header reveals origin stack. Route via Cloudflare or use nginx server_tokens off.' ];
        }
        $sections['security_headers'] = [
            'label' => 'Security Headers',
            'icon'  => '🔒',
            'score' => $sec_w > 0 ? (int) round( ( $sec_score / $sec_w ) * 100 ) : 0,
        ];

        // ── 2. Homepage Metadata ──────────────────────────────────────────────
        $meta_score = 0;
        $meta_w     = 0;

        // Title tag
        preg_match( '/<title[^>]*>(.*?)<\/title>/is', $home_body, $tm );
        $title_val = isset( $tm[1] ) ? html_entity_decode( trim( $tm[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
        $title_len = mb_strlen( $title_val );
        $meta_w += 3;
        if ( $title_len >= 10 && $title_len <= 65 ) {
            $meta_score += 3;
            $t_status = 'ok'; $t_note = '';
        } elseif ( $title_len > 65 ) {
            $meta_score += 1;
            $t_status = 'warn'; $t_note = "Title is {$title_len} chars — Google truncates at ~60. Shorten it.";
        } elseif ( $title_len > 0 ) {
            $meta_score += 1;
            $t_status = 'warn'; $t_note = 'Title is very short. Add role and keyword.';
        } else {
            $t_status = 'fail'; $t_note = 'No title tag on homepage.';
        }
        $findings[] = [ 'section' => 'homepage_meta', 'check' => 'Homepage title tag', 'status' => $t_status, 'value' => $title_val ? "{$title_val} ({$title_len} chars)" : 'absent', 'note' => $t_note ];

        // Meta description
        preg_match( '/<meta\s[^>]*name=["\']description["\']\s[^>]*content=["\'](.*?)["\']/is', $home_body, $dm );
        if ( empty( $dm ) ) {
            preg_match( '/<meta\s[^>]*content=["\'](.*?)["\']\s[^>]*name=["\']description["\']/is', $home_body, $dm );
        }
        $desc_val = $dm[1] ?? '';
        $desc_len = mb_strlen( html_entity_decode( $desc_val, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
        $meta_w  += 3;
        if ( $desc_len >= 100 && $desc_len <= 160 ) {
            $meta_score += 3; $d_status = 'ok'; $d_note = '';
        } elseif ( $desc_len > 160 ) {
            $meta_score += 1; $d_status = 'warn'; $d_note = "Description is {$desc_len} chars — Google truncates at ~155.";
        } elseif ( $desc_len > 0 ) {
            $meta_score += 1; $d_status = 'warn'; $d_note = 'Description is short — aim for 120–155 characters.';
        } else {
            $d_status = 'fail'; $d_note = 'No meta description on homepage.';
        }
        $findings[] = [ 'section' => 'homepage_meta', 'check' => 'Homepage meta description', 'status' => $d_status, 'value' => $desc_len > 0 ? "{$desc_len} chars" : 'absent', 'note' => $d_note ];

        // OpenGraph
        preg_match( '/<meta\s[^>]*property=["\']og:title["\']\s[^>]*content=["\'](.*?)["\']/is', $home_body, $ogt );
        preg_match( '/<meta\s[^>]*property=["\']og:description["\']\s[^>]*content=["\'](.*?)["\']/is', $home_body, $ogd );
        preg_match( '/<meta\s[^>]*property=["\']og:image["\']\s[^>]*content=["\'](.*?)["\']/is', $home_body, $ogi );
        $og_ok = ! empty( $ogt ) && ! empty( $ogd ) && ! empty( $ogi );
        $meta_w += 2;
        $meta_score += $og_ok ? 2 : ( ! empty( $ogt ) ? 1 : 0 );
        $findings[] = [
            'section' => 'homepage_meta',
            'check'   => 'OpenGraph tags (og:title, og:description, og:image)',
            'status'  => $og_ok ? 'ok' : ( ! empty( $ogt ) ? 'warn' : 'fail' ),
            'value'   => $og_ok ? 'all present' : ( ! empty( $ogt ) ? 'partial — og:image missing' : 'absent' ),
            'note'    => $og_ok ? '' : 'Missing OG tags degrade social sharing preview cards.',
        ];

        // Canonical
        preg_match( '/<link\s[^>]*rel=["\']canonical["\']\s[^>]*href=["\'](.*?)["\']/is', $home_body, $cm );
        if ( empty( $cm ) ) {
            preg_match( '/<link\s[^>]*href=["\'](.*?)["\']\s[^>]*rel=["\']canonical["\']/is', $home_body, $cm );
        }
        $meta_w++;
        if ( ! empty( $cm[1] ) ) {
            $meta_score++;
            $findings[] = [ 'section' => 'homepage_meta', 'check' => 'Canonical URL', 'status' => 'ok', 'value' => $cm[1], 'note' => '' ];
        } else {
            $findings[] = [ 'section' => 'homepage_meta', 'check' => 'Canonical URL', 'status' => 'fail', 'value' => 'absent', 'note' => 'No canonical link — risk of duplicate content indexing.' ];
        }
        $sections['homepage_meta'] = [
            'label' => 'Homepage Metadata',
            'icon'  => '🏠',
            'score' => $meta_w > 0 ? (int) round( ( $meta_score / $meta_w ) * 100 ) : 0,
        ];

        // ── 3. llms.txt ───────────────────────────────────────────────────────
        $llms_r = $this->seo_audit_http( $target_base . '/llms.txt', 10, true );
        $llms_w = 2;
        if ( $llms_r['code'] === 200 ) {
            $ct = strtolower( (string) ( $llms_r['headers']['content-type'] ?? '' ) );
            $ct_ok = str_contains( $ct, 'text' );
            $llms_score = $ct_ok ? 2 : 1;
            $findings[] = [
                'section' => 'llms_txt',
                'check'   => 'llms.txt accessible',
                'status'  => $ct_ok ? 'ok' : 'warn',
                'value'   => "HTTP 200, Content-Type: {$ct}",
                'note'    => $ct_ok ? '' : 'Serving but wrong Content-Type — should be text/plain.',
            ];
        } elseif ( $llms_r['code'] >= 301 && $llms_r['code'] <= 308 ) {
            $llms_score = 0;
            $loc = (string) ( $llms_r['headers']['location'] ?? '?' );
            $findings[] = [ 'section' => 'llms_txt', 'check' => 'llms.txt accessible', 'status' => 'fail', 'value' => "HTTP {$llms_r['code']} → {$loc}", 'note' => 'Redirect loop. Create a static llms.txt with an nginx location block before the WP catch-all.' ];
        } else {
            $llms_score = 0;
            $findings[] = [ 'section' => 'llms_txt', 'check' => 'llms.txt accessible', 'status' => 'fail', 'value' => "HTTP {$llms_r['code']}", 'note' => 'llms.txt not found. Create /var/www/html/llms.txt and add a location block in nginx.' ];
        }
        $sections['llms_txt'] = [
            'label' => 'llms.txt (AI Crawlers)',
            'icon'  => '🤖',
            'score' => (int) round( ( $llms_score / $llms_w ) * 100 ),
        ];

        // ── 4. robots.txt ─────────────────────────────────────────────────────
        $robots_r    = $this->seo_audit_http( $target_base . '/robots.txt', 10, true );
        $robots_body = $robots_r['body'];
        $rob_score   = 0; $rob_w = 3;

        $rob_wp_json = str_contains( $robots_body, '/wp-json' );
        $rob_score += $rob_wp_json ? 1 : 0;
        $findings[] = [ 'section' => 'robots_txt', 'check' => 'robots.txt — /wp-json/ disallowed', 'status' => $rob_wp_json ? 'ok' : 'warn', 'value' => $rob_wp_json ? 'present' : 'missing', 'note' => $rob_wp_json ? '' : 'Add Disallow: /wp-json/ to prevent crawl budget waste.' ];

        $rob_author = str_contains( $robots_body, '/author/' );
        $rob_score += $rob_author ? 1 : 0;
        $findings[] = [ 'section' => 'robots_txt', 'check' => 'robots.txt — /author/ disallowed', 'status' => $rob_author ? 'ok' : 'warn', 'value' => $rob_author ? 'present' : 'missing', 'note' => $rob_author ? '' : 'Add Disallow: /author/ to block thin author archive pages.' ];

        $rob_sitemap = str_contains( strtolower( $robots_body ), 'sitemap:' );
        $rob_score += $rob_sitemap ? 1 : 0;
        $findings[] = [ 'section' => 'robots_txt', 'check' => 'robots.txt — Sitemap declared', 'status' => $rob_sitemap ? 'ok' : 'warn', 'value' => $rob_sitemap ? 'present' : 'missing', 'note' => $rob_sitemap ? '' : 'Add Sitemap: https://example.com/sitemap.xml for crawler discovery.' ];

        $sections['robots_txt'] = [
            'label' => 'robots.txt',
            'icon'  => '📄',
            'score' => (int) round( ( $rob_score / $rob_w ) * 100 ),
        ];

        // ── 5. WordPress Hardening ────────────────────────────────────────────
        $hard_score = 0; $hard_w = 0;

        $xmlrpc_r  = $this->seo_audit_http( $target_base . '/xmlrpc.php', 8 );
        $xmlrpc_ok = in_array( $xmlrpc_r['code'], [ 403, 404, 410, 0 ], true );
        $hard_w += 2; $hard_score += $xmlrpc_ok ? 2 : 0;
        $findings[] = [ 'section' => 'wp_hardening', 'check' => 'xmlrpc.php blocked', 'status' => $xmlrpc_ok ? 'ok' : 'fail', 'value' => "HTTP {$xmlrpc_r['code']}", 'note' => $xmlrpc_ok ? '' : 'xmlrpc.php is accessible — block at nginx to prevent brute-force and DDoS amplification.' ];

        $users_r  = $this->seo_audit_http( $target_base . '/wp-json/wp/v2/users', 8 );
        $users_ok = in_array( $users_r['code'], [ 401, 403, 404, 0 ], true );
        $hard_w += 2; $hard_score += $users_ok ? 2 : 0;
        $findings[] = [ 'section' => 'wp_hardening', 'check' => 'wp-json/v2/users blocked (user enumeration)', 'status' => $users_ok ? 'ok' : 'fail', 'value' => "HTTP {$users_r['code']}", 'note' => $users_ok ? '' : 'User enumeration exposed via REST API. Block /wp-json/wp/v2/users in nginx.' ];

        $gen_present = str_contains( $home_body, 'name="generator"' );
        $hard_w++; $hard_score += $gen_present ? 0 : 1;
        $findings[] = [ 'section' => 'wp_hardening', 'check' => 'WordPress version hidden', 'status' => $gen_present ? 'warn' : 'ok', 'value' => $gen_present ? 'version tag in source' : 'hidden', 'note' => $gen_present ? "Remove via add_filter('the_generator', '__return_empty_string')." : '' ];

        $blog_r  = $this->seo_audit_http( $target_base . '/blog/', 6 );
        $posts_r = $this->seo_audit_http( $target_base . '/posts/', 6 );
        $both_200 = ( $blog_r['code'] === 200 ) && ( $posts_r['code'] === 200 );
        $hard_w++; $hard_score += $both_200 ? 0 : 1;
        $findings[] = [ 'section' => 'wp_hardening', 'check' => '/blog/ and /posts/ duplicate archive pages', 'status' => $both_200 ? 'warn' : 'ok', 'value' => "/blog/ HTTP {$blog_r['code']}, /posts/ HTTP {$posts_r['code']}", 'note' => $both_200 ? 'Both return 200 — duplicate archives dilute crawl budget. 301 one to the other.' : '' ];

        $sections['wp_hardening'] = [
            'label' => 'WordPress Hardening',
            'icon'  => '🛡',
            'score' => $hard_w > 0 ? (int) round( ( $hard_score / $hard_w ) * 100 ) : 0,
        ];

        // ── 6. Structured Data & Schema ───────────────────────────────────────
        $sch_score = 0; $sch_w = 0;

        // Parse JSON-LD from homepage
        $all_schema = $this->seo_audit_extract_schema( $home_body );

        // Also check a popular post (own site only)
        if ( ! $is_external ) {
            $top_post = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish', 'orderby' => 'comment_count', 'order' => 'DESC' ] );
            if ( ! empty( $top_post ) ) {
                $post_r = $this->seo_audit_http( (string) get_permalink( $top_post[0] ), 12, true );
                if ( $post_r['code'] === 200 && $post_r['body'] ) {
                    $all_schema = array_merge( $all_schema, $this->seo_audit_extract_schema( $post_r['body'] ) );
                }
            }
        }

        $find_type = function( string $type ) use ( $all_schema ): ?array {
            foreach ( $all_schema as $node ) {
                $t = $node['@type'] ?? '';
                if ( ( is_array( $t ) && in_array( $type, $t, true ) ) || $t === $type ) {
                    return $node;
                }
            }
            return null;
        };

        // BlogPosting / Article
        $bp = $find_type( 'BlogPosting' ) ?? $find_type( 'Article' );
        $sch_w += 3;
        if ( $bp ) {
            $bp_core = array_filter( [ 'headline', 'description', 'author', 'datePublished', 'dateModified' ], fn( $f ) => isset( $bp[ $f ] ) );
            $bp_cnt  = count( $bp_core );
            $sch_score += $bp_cnt >= 4 ? 3 : ( $bp_cnt >= 2 ? 2 : 1 );
            $findings[] = [ 'section' => 'schema', 'check' => 'BlogPosting core fields', 'status' => $bp_cnt >= 4 ? 'ok' : 'warn', 'value' => "{$bp_cnt}/5 fields present", 'note' => $bp_cnt < 4 ? 'Add missing BlogPosting fields: headline, description, author, datePublished, dateModified.' : '' ];

            // abstract
            $sch_w++; $sch_score += isset( $bp['abstract'] ) ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'BlogPosting — abstract field', 'status' => isset( $bp['abstract'] ) ? 'ok' : 'warn', 'value' => isset( $bp['abstract'] ) ? 'present' : 'missing', 'note' => isset( $bp['abstract'] ) ? '' : 'abstract gives LLMs a pre-packaged author-approved summary to quote.' ];

            // disambiguatingDescription
            $sch_w++; $sch_score += isset( $bp['disambiguatingDescription'] ) ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'BlogPosting — disambiguatingDescription', 'status' => isset( $bp['disambiguatingDescription'] ) ? 'ok' : 'warn', 'value' => isset( $bp['disambiguatingDescription'] ) ? 'present' : 'missing', 'note' => isset( $bp['disambiguatingDescription'] ) ? '' : 'One-sentence summary LLMs can cite directly — high AI citation value.' ];

            // publisher @type = Organization
            $pub_type = is_array( $bp['publisher'] ?? null ) ? ( $bp['publisher']['@type'] ?? '' ) : '';
            $pub_ok   = $pub_type === 'Organization';
            $sch_w++; $sch_score += $pub_ok ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'BlogPosting publisher @type', 'status' => $pub_ok ? 'ok' : 'warn', 'value' => $pub_type ?: 'missing', 'note' => $pub_ok ? '' : 'Publisher should be @type Organization (not Person) for rich result eligibility.' ];
        } else {
            $findings[] = [ 'section' => 'schema', 'check' => 'BlogPosting schema', 'status' => 'fail', 'value' => 'absent', 'note' => 'No BlogPosting schema found on posts.' ];
        }

        // WebSite with SearchAction
        $ws   = $find_type( 'WebSite' );
        $ws_ok = $ws && isset( $ws['potentialAction'] );
        $sch_w++; $sch_score += $ws_ok ? 1 : 0;
        $findings[] = [ 'section' => 'schema', 'check' => 'WebSite with SearchAction', 'status' => $ws_ok ? 'ok' : 'warn', 'value' => $ws_ok ? 'present' : ( $ws ? 'WebSite present — no SearchAction' : 'absent' ), 'note' => $ws_ok ? '' : 'SearchAction enables SiteLinksSearchBox in branded SERPs.' ];

        // Person schema
        $person = $find_type( 'Person' );
        $sch_w++;
        if ( $person ) {
            $sch_score++;
            $findings[] = [ 'section' => 'schema', 'check' => 'Person schema', 'status' => 'ok', 'value' => 'present', 'note' => '' ];

            $sa          = (array) ( $person['sameAs'] ?? [] );
            $has_twitter = (bool) array_filter( $sa, fn( $u ) => str_contains( (string) $u, 'twitter' ) || str_contains( (string) $u, 'x.com' ) );
            $sch_w++; $sch_score += $has_twitter ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'Person sameAs — Twitter/X', 'status' => $has_twitter ? 'ok' : 'warn', 'value' => $has_twitter ? 'present' : 'missing', 'note' => $has_twitter ? '' : 'Add Twitter/X URL to Person sameAs array.' ];

            $has_wf = isset( $person['worksFor'] );
            $sch_w++; $sch_score += $has_wf ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'Person worksFor', 'status' => $has_wf ? 'ok' : 'warn', 'value' => $has_wf ? 'present' : 'missing', 'note' => $has_wf ? '' : 'worksFor is the most important YMYL trust signal — declare institutional affiliation.' ];

            $has_ka = isset( $person['knowsAbout'] );
            $sch_w++; $sch_score += $has_ka ? 1 : 0;
            $findings[] = [ 'section' => 'schema', 'check' => 'Person knowsAbout', 'status' => $has_ka ? 'ok' : 'warn', 'value' => $has_ka ? 'present' : 'missing', 'note' => $has_ka ? '' : 'Add a knowsAbout array to strengthen topical authority knowledge graph signals.' ];
        } else {
            $findings[] = [ 'section' => 'schema', 'check' => 'Person schema', 'status' => 'fail', 'value' => 'absent', 'note' => 'No Person schema found.' ];
        }

        // BreadcrumbList
        $bc = $find_type( 'BreadcrumbList' );
        $sch_w++; $sch_score += $bc ? 1 : 0;
        $findings[] = [ 'section' => 'schema', 'check' => 'BreadcrumbList schema', 'status' => $bc ? 'ok' : 'warn', 'value' => $bc ? 'present' : 'absent', 'note' => $bc ? '' : 'BreadcrumbList enables breadcrumb rich results in SERPs.' ];

        // FAQPage / QAPage
        $faq = $find_type( 'FAQPage' ) ?? $find_type( 'QAPage' );
        $sch_w++; $sch_score += $faq ? 1 : 0;
        $findings[] = [ 'section' => 'schema', 'check' => 'FAQPage / QAPage schema', 'status' => $faq ? 'ok' : 'warn', 'value' => $faq ? 'present' : 'absent', 'note' => $faq ? '' : 'FAQPage is the schema type most frequently cited by ChatGPT and Perplexity.' ];

        // HowTo
        $howto = $find_type( 'HowTo' );
        $sch_w++; $sch_score += $howto ? 1 : 0;
        $findings[] = [ 'section' => 'schema', 'check' => 'HowTo schema on step-by-step posts', 'status' => $howto ? 'ok' : 'warn', 'value' => $howto ? 'present' : 'absent', 'note' => $howto ? '' : 'HowTo markup makes numbered-step posts eligible for step rich results.' ];

        $sections['schema'] = [
            'label' => 'Structured Data & Schema',
            'icon'  => '🗂',
            'score' => $sch_w > 0 ? (int) round( ( $sch_score / $sch_w ) * 100 ) : 0,
        ];

        // ── 7. AEO — Answer Engine Optimisation (own site only) ───────────────
        if ( ! $is_external ) {
            $aeo_score = 0; $aeo_w = 0;
            $top_posts = get_posts( [ 'numberposts' => 6, 'post_status' => 'publish', 'orderby' => 'comment_count', 'order' => 'DESC' ] );
            $aeo_passing = 0;
            foreach ( $top_posts as $p ) {
                $aeo_w++;
                // Pass if AEO answer meta is set (generated by auto pipeline).
                $aeo_meta = trim( (string) get_post_meta( $p->ID, self::META_AEO_ANSWER, true ) );
                if ( $aeo_meta !== '' ) {
                    $aeo_passing++;
                    $aeo_score++;
                    continue;
                }
                // Otherwise fall back to content heuristic.
                $stripped      = preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $p->post_content ) ) );
                $word_count    = str_word_count( substr( $stripped, 0, 1000 ) );
                $not_narrative = ! preg_match( '/^(once upon|back in|imagine|in the world|the story|when i|i was|have you|did you know|welcome to)/i', ltrim( $stripped ) );
                if ( $not_narrative && $word_count >= 30 && $word_count <= 150 ) {
                    $aeo_passing++;
                    $aeo_score++;
                }
            }
            $aeo_total = count( $top_posts );
            $aeo_pct   = $aeo_total > 0 ? (int) round( ( $aeo_passing / $aeo_total ) * 100 ) : 0;
            $findings[] = [
                'section' => 'aeo',
                'check'   => 'Answer-first paragraphs on top posts',
                'status'  => $aeo_pct >= 80 ? 'ok' : ( $aeo_pct >= 40 ? 'warn' : 'fail' ),
                'value'   => "{$aeo_passing}/{$aeo_total} posts pass (AEO answer meta or content heuristic)",
                'note'    => $aeo_pct >= 80 ? '' : 'Auto Pipeline generates AEO answers automatically on publish. Run it on older posts via the AI Tools tab.',
            ];
            $speakable = $find_type( 'SpeakableSpecification' );
            $aeo_w++; $aeo_score += $speakable ? 1 : 0;
            $findings[] = [ 'section' => 'aeo', 'check' => 'Speakable schema', 'status' => $speakable ? 'ok' : 'warn', 'value' => $speakable ? 'present' : 'absent', 'note' => $speakable ? '' : 'Speakable marks answer paragraphs for Google Assistant voice responses.' ];
            $sections['aeo'] = [
                'label' => 'AEO & Answer Engine',
                'icon'  => '💬',
                'score' => $aeo_w > 0 ? (int) round( ( $aeo_score / $aeo_w ) * 100 ) : 0,
            ];
        }

        // ── 8. Category Pages (own site only) ────────────────────────────────
        if ( ! $is_external ) {
            $cat_score = 0; $cat_w = 0;
            $cats       = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => true, 'number' => 8 ] );
            $cats_total = is_array( $cats ) ? count( $cats ) : 0;
            $cats_desc  = 0;
            $cats_meta  = 0;
            if ( is_array( $cats ) ) {
                foreach ( $cats as $cat ) {
                    if ( trim( $cat->description ) || get_term_meta( $cat->term_id, self::META_TERM_INTRO, true ) || get_term_meta( $cat->term_id, self::META_TERM_DESC, true ) ) $cats_desc++;
                    if ( get_term_meta( $cat->term_id, self::META_TERM_DESC, true ) ) $cats_meta++;
                }
            }
            $cat_w += 2;
            if ( $cats_total > 0 ) {
                $cat_score += $cats_desc >= $cats_total ? 2 : ( $cats_desc > 0 ? 1 : 0 );
                $findings[] = [ 'section' => 'category_pages', 'check' => 'Category intro text (descriptions)', 'status' => $cats_desc >= $cats_total ? 'ok' : ( $cats_desc > 0 ? 'warn' : 'fail' ), 'value' => "{$cats_desc}/{$cats_total} categories", 'note' => $cats_desc >= $cats_total ? '' : 'Add description text to all categories — thin category pages hurt crawl budget.' ];
            }
            $cat_w++;
            $cat_score += $cats_meta >= $cats_total && $cats_total > 0 ? 1 : 0;
            $findings[] = [ 'section' => 'category_pages', 'check' => 'Category SEO meta descriptions set', 'status' => ( $cats_meta >= $cats_total && $cats_total > 0 ) ? 'ok' : ( $cats_meta > 0 ? 'warn' : 'fail' ), 'value' => "{$cats_meta}/{$cats_total} set", 'note' => $cats_meta < $cats_total ? 'Set SEO meta descriptions in the Categories tab.' : '' ];

            // Missing SEO title tags on posts/pages
            $missing_titles = get_posts( [
                'post_type'      => [ 'post', 'page' ],
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'meta_query'     => [
                    'relation' => 'OR',
                    [ 'key' => self::META_TITLE, 'compare' => 'NOT EXISTS' ],
                    [ 'key' => self::META_TITLE, 'value' => '', 'compare' => '=' ],
                ],
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ] );
            $missing_count = count( $missing_titles );
            $cat_w += 2;
            if ( $missing_count === 0 ) {
                $cat_score += 2;
                $findings[] = [ 'section' => 'category_pages', 'check' => 'Missing SEO title tags on posts/pages', 'status' => 'ok', 'value' => 'all set', 'note' => '' ];
            } else {
                $sample = array_slice( $missing_titles, 0, 5 );
                $sample_titles = implode( ', ', array_map( fn( $id ) => '"' . get_the_title( $id ) . '"', $sample ) );
                $more = $missing_count > 5 ? ' +' . ( $missing_count - 5 ) . ' more' : '';
                $findings[] = [
                    'section' => 'category_pages',
                    'check'   => 'Missing SEO title tags on posts/pages',
                    'status'  => $missing_count <= 3 ? 'warn' : 'fail',
                    'value'   => "{$missing_count} post(s)/page(s)",
                    'note'    => "No custom SEO title set — WordPress falls back to the post name. Google sees it, but it is not keyword-optimised: {$sample_titles}{$more}. Use Title Optimiser to AI-generate them.",
                ];
            }

            $sections['category_pages'] = [
                'label' => 'Category Pages',
                'icon'  => '🏷',
                'score' => $cat_w > 0 ? (int) round( ( $cat_score / $cat_w ) * 100 ) : 0,
            ];
        }

        // ── Overall weighted score ─────────────────────────────────────────────
        $weights = [
            'security_headers' => 15,
            'homepage_meta'    => 20,
            'llms_txt'         =>  8,
            'robots_txt'       =>  7,
            'wp_hardening'     => 15,
            'schema'           => 20,
            'aeo'              => 10,
            'category_pages'   =>  5,
        ];
        $tw = 0; $tws = 0;
        foreach ( $weights as $k => $w ) {
            if ( isset( $sections[ $k ] ) ) {
                $tws += $sections[ $k ]['score'] * $w;
                $tw  += $w;
            }
        }
        $overall = $tw > 0 ? (int) round( $tws / $tw ) : 0;

        return [
            'timestamp' => time(),
            'date'      => current_time( 'Y-m-d H:i:s' ),
            'overall'   => $overall,
            'sections'  => $sections,
            'findings'  => $findings,
        ];
    }

    private function seo_audit_extract_schema( string $html ): array {
        $blocks = [];
        preg_match_all( '/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m );
        foreach ( ( $m[1] ?? [] ) as $raw ) {
            $decoded = json_decode( trim( $raw ), true );
            if ( ! $decoded ) continue;
            if ( isset( $decoded['@graph'] ) && is_array( $decoded['@graph'] ) ) {
                foreach ( $decoded['@graph'] as $node ) {
                    if ( is_array( $node ) ) $blocks[] = $node;
                }
            } else {
                $blocks[] = $decoded;
            }
        }
        return $blocks;
    }

    // ── Admin pane renderer ───────────────────────────────────────────────────

    public function render_seo_site_audit_pane(): void {
        $history       = get_option( 'cs_seo_audit_history', [] );
        if ( ! is_array( $history ) ) $history = [];
        $adhoc_history = get_option( 'cs_seo_audit_history_adhoc', [] );
        if ( ! is_array( $adhoc_history ) ) $adhoc_history = [];
        $latest        = $history[0] ?? null;
        $nonce         = wp_create_nonce( 'cs_seo_nonce' );
        ?>
        <div class="ab-zone-card ab-card-siteaudit" style="margin-top:0">
            <div class="ab-zone-header" style="background:#0f172a;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span><span class="ab-zone-icon">🔍</span> SEO Site Audit</span>
                <span style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <span id="cs-audit-last-run" style="font-size:11px;color:rgba(255,255,255,0.5)">
                        <?php echo $latest ? 'Last run: ' . esc_html( $latest['date'] ) : 'Not yet run'; ?>
                    </span>
                    <button id="cs-run-audit-btn" type="button" class="button" style="background:#3b82f6;color:#fff;border-color:#2563eb;font-weight:600;padding:6px 18px">
                        ▶ Run Audit
                    </button>
                </span>
            </div>
            <div class="ab-zone-body">

                <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;flex-wrap:wrap">
                    <label for="cs-audit-url" style="font-size:13px;font-weight:600;color:#1d2327;white-space:nowrap">Audit URL:</label>
                    <input type="url" id="cs-audit-url" value="<?php echo esc_url( home_url( '/' ) ); ?>"
                           placeholder="https://example.com/"
                           style="flex:1;min-width:200px;padding:5px 10px;font-size:13px;border:1px solid #c3c4c7;border-radius:4px" />
                    <span style="font-size:11px;color:#9ca3af">Other sites run as Ad-hoc Audit</span>
                </div>

                <p style="font-size:13px;color:#50575e;margin-bottom:16px">
                    Runs live HTTP checks — security headers, metadata, llms.txt, robots.txt, WordPress hardening, structured data, and AEO. Results are saved (own-site: up to 50 history entries; other URLs: stored in Ad-hoc Audits below).
                </p>

                <div id="cs-audit-running" style="display:none;padding:20px;text-align:center;color:#50575e;font-size:13px;background:#f8f9fa;border-radius:6px;margin-bottom:16px">
                    <span style="font-size:22px;display:inline-block;animation:cs-spin 1s linear infinite">⏳</span><br>
                    Running checks — this may take 20–40 seconds&hellip;
                </div>

                <div id="cs-audit-results" <?php echo $latest ? '' : 'style="display:none"'; ?>>
                    <?php $this->render_seo_audit_dashboard( $latest ); ?>
                </div>

                <?php if ( count( $history ) >= 2 ) : ?>
                <div id="cs-audit-chart-wrap" style="margin-top:32px">
                    <h3 style="font-size:13px;font-weight:600;color:#1d2327;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Score History (<?php echo count( $history ); ?> audits)</h3>
                    <canvas id="cs-audit-chart" width="880" height="200" style="max-width:100%;border:1px solid #e0e0e0;border-radius:6px;background:#fff;display:block"></canvas>
                </div>
                <?php else : ?>
                <div id="cs-audit-chart-wrap" style="display:none">
                    <h3 style="font-size:13px;font-weight:600;color:#1d2327;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">Score History</h3>
                    <canvas id="cs-audit-chart" width="880" height="200" style="max-width:100%;border:1px solid #e0e0e0;border-radius:6px;background:#fff;display:block"></canvas>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <?php if ( ! empty( $adhoc_history ) ) : ?>
        <div class="ab-zone-card" id="cs-adhoc-zone-card" style="margin-top:24px">
            <div class="ab-zone-header" style="background:#374151;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span><span class="ab-zone-icon">🔗</span> Ad-hoc Audits (<?php echo count( $adhoc_history ); ?>)</span>
                <button type="button" class="button ab-toggle-card-btn" data-card-id="cs-adhoc-zone-card" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">&#9658; Show</button>
            </div>
            <div class="ab-zone-body" style="display:none">
                <div id="cs-adhoc-list">
                <?php foreach ( $adhoc_history as $entry ) :
                    $score = $entry['overall'] ?? 0;
                    $col   = $score >= 80 ? '#10b981' : ( $score >= 60 ? '#f59e0b' : '#ef4444' );
                    ?>
                    <div style="border:1px solid #e0e0e0;border-radius:6px;margin-bottom:12px;overflow:hidden">
                        <div style="background:#f8f9fa;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
                            <span style="font-size:13px;font-weight:600;color:#1d2327;word-break:break-all"><?php echo esc_html( $entry['target_url'] ?? '' ); ?></span>
                            <span style="display:flex;align-items:center;gap:10px">
                                <span style="font-size:20px;font-weight:700;color:<?php echo esc_attr( $col ); ?>"><?php echo esc_html( $score ); ?>/100</span>
                                <span style="font-size:11px;color:#9ca3af"><?php echo esc_html( $entry['date'] ?? '' ); ?></span>
                            </span>
                        </div>
                        <div style="padding:0">
                            <?php $this->render_seo_audit_dashboard( $entry ); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php else : ?>
        <div class="ab-zone-card" id="cs-adhoc-zone-card" style="margin-top:24px;display:none">
            <div class="ab-zone-header" style="background:#374151;justify-content:space-between">
                <span><span class="ab-zone-icon">🔗</span> Ad-hoc Audits</span>
                <button type="button" class="button ab-toggle-card-btn" data-card-id="cs-adhoc-zone-card" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">&#9658; Show</button>
            </div>
            <div class="ab-zone-body" style="display:none">
                <div id="cs-adhoc-list"></div>
            </div>
        </div>
        <?php endif; ?>

        <style>
        @keyframes cs-spin { to { transform:rotate(360deg); } }
        .cs-audit-hero { display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;background:#0f172a;border-radius:8px;padding:28px;margin-bottom:20px }
        .cs-audit-hero-info { display:flex;align-items:center;gap:20px;flex-shrink:0 }
        .cs-audit-hero-grid { flex:1;display:flex;flex-wrap:wrap;gap:8px;min-width:0 }
        .cs-audit-bar-lbl { width:190px;flex-shrink:0;color:#3d3d3d }
        @media (max-width:782px) {
            .cs-audit-hero { flex-direction:column;padding:18px }
            .cs-audit-hero-info { width:100% }
            .cs-audit-hero-grid { width:100%;flex:none;display:grid;grid-template-columns:repeat(2,1fr);gap:8px }
            .cs-audit-hero-grid .cs-audit-score-card { min-width:0 !important }
            .cs-audit-bar-lbl { width:120px;font-size:11px }
        }
        </style>

        <script>
        (function(){
            var history  = <?php echo wp_json_encode( array_slice( $history, 0, 50 ) ); ?>;
            var ajaxUrl  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
            var nonce    = <?php echo wp_json_encode( $nonce ); ?>;

            // ── Draw history chart ────────────────────────────────────────────
            function drawChart() {
                var canvas = document.getElementById('cs-audit-chart');
                if (!canvas || history.length < 2) return;
                var ctx  = canvas.getContext('2d');
                var W = canvas.offsetWidth || canvas.width;
                canvas.width = W;
                var H = 200;
                canvas.height = H;
                var pad = {t:24, r:20, b:36, l:40};
                var cw = W - pad.l - pad.r;
                var ch = H - pad.t - pad.b;
                ctx.clearRect(0, 0, W, H);

                // Grid
                ctx.strokeStyle = '#f0f0f1'; ctx.lineWidth = 1;
                [0, 25, 50, 75, 100].forEach(function(v) {
                    var gy = pad.t + ch - (v / 100) * ch;
                    ctx.beginPath(); ctx.moveTo(pad.l, gy); ctx.lineTo(W - pad.r, gy); ctx.stroke();
                    ctx.fillStyle = '#9ca3af'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
                    ctx.fillText(v, pad.l - 4, gy + 3);
                });

                var data   = history.slice().reverse();
                var n      = data.length;
                var colors = {
                    overall:          '#1d2327',
                    security_headers: '#ef4444',
                    homepage_meta:    '#3b82f6',
                    schema:           '#8b5cf6',
                    wp_hardening:     '#10b981',
                    aeo:              '#f59e0b',
                    llms_txt:         '#6366f1',
                    robots_txt:       '#06b6d4',
                    category_pages:   '#f97316',
                };
                var labels = {
                    overall:          'Overall',
                    security_headers: 'Sec Headers',
                    homepage_meta:    'Homepage Meta',
                    schema:           'Schema',
                    wp_hardening:     'WP Hardening',
                    aeo:              'AEO',
                    llms_txt:         'llms.txt',
                    robots_txt:       'robots.txt',
                    category_pages:   'Categories',
                };
                var skeys = data[0] && data[0].sections ? Object.keys(data[0].sections) : [];
                var keys  = ['overall'].concat(skeys);

                keys.forEach(function(key) {
                    ctx.beginPath();
                    ctx.strokeStyle = colors[key] || '#aaa';
                    ctx.lineWidth   = key === 'overall' ? 2.5 : 1.5;
                    ctx.setLineDash(key === 'overall' ? [] : [4, 3]);
                    var first = true;
                    data.forEach(function(d, i) {
                        var score = key === 'overall' ? d.overall : (d.sections && d.sections[key] ? d.sections[key].score : null);
                        if (score === null || score === undefined) return;
                        var x = pad.l + (n <= 1 ? cw / 2 : (i / (n - 1)) * cw);
                        var y = pad.t + ch - (score / 100) * ch;
                        if (first) { ctx.moveTo(x, y); first = false; } else { ctx.lineTo(x, y); }
                    });
                    ctx.stroke();
                    ctx.setLineDash([]);
                });

                // Legend
                ctx.font = '10px sans-serif'; ctx.textAlign = 'left';
                var lx = pad.l, ly = H - 8;
                keys.forEach(function(key) {
                    ctx.fillStyle = colors[key] || '#aaa';
                    ctx.fillRect(lx, ly - 7, 14, 3);
                    ctx.fillStyle = '#6b7280';
                    var lbl = labels[key] || key;
                    ctx.fillText(lbl, lx + 17, ly);
                    lx += ctx.measureText(lbl).width + 30;
                    if (lx > W - 100) { lx = pad.l; ly -= 13; }
                });
            }

            drawChart();

            // ── Run Audit ─────────────────────────────────────────────────────
            var runBtn = document.getElementById('cs-run-audit-btn');
            if (runBtn) {
                runBtn.addEventListener('click', function() {
                    runBtn.disabled = true;
                    runBtn.textContent = '⏳ Running…';
                    document.getElementById('cs-audit-running').style.display = 'block';
                    document.getElementById('cs-audit-results').style.display = 'none';

                    var urlInput = document.getElementById('cs-audit-url');
                    var targetUrl = (urlInput && urlInput.value.trim()) ? urlInput.value.trim() : ajaxUrl.replace('/wp-admin/admin-ajax.php', '/');

                    var fd = new FormData();
                    fd.append('action',     'cs_seo_run_site_audit');
                    fd.append('nonce',      nonce);
                    fd.append('target_url', targetUrl);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            runBtn.disabled = false;
                            runBtn.textContent = '▶ Run Audit';
                            document.getElementById('cs-audit-running').style.display = 'none';
                            if (res.success) {
                                if (res.data.is_adhoc) {
                                    addAdhocResult(res.data);
                                } else {
                                    history.unshift(res.data);
                                    if (history.length > 50) history = history.slice(0, 50);
                                    var lrEl = document.getElementById('cs-audit-last-run');
                                    if (lrEl) lrEl.textContent = 'Last run: ' + res.data.date;
                                    refreshResults(res.data);
                                    // Show/update chart
                                    if (history.length >= 2) {
                                        var cw = document.getElementById('cs-audit-chart-wrap');
                                        if (cw) { cw.style.display = ''; cw.querySelector('h3').textContent = 'Score History (' + history.length + ' audits)'; }
                                        drawChart();
                                    }
                                }
                            } else {
                                alert('Audit failed — check console for details.');
                                console.error('cs_seo_run_site_audit error:', res);
                            }
                        })
                        .catch(function(e) {
                            runBtn.disabled = false;
                            runBtn.textContent = '▶ Run Audit';
                            document.getElementById('cs-audit-running').style.display = 'none';
                            alert('Audit error: ' + e.message);
                        });
                });
            }

            // ── Add adhoc result to adhoc section ────────────────────────────
            function addAdhocResult(data) {
                var card = document.getElementById('cs-adhoc-zone-card');
                if (!card) return;
                card.style.display = '';

                // Show zone body
                var body = card.querySelector('.ab-zone-body');
                if (body) body.style.display = '';
                var toggleBtn = card.querySelector('.ab-toggle-card-btn');
                if (toggleBtn) toggleBtn.innerHTML = '&#9660; Hide';

                // Update heading count
                var heading = card.querySelector('.ab-zone-header span:first-child');

                var list = document.getElementById('cs-adhoc-list');
                if (!list) return;

                var score = data.overall || 0;
                var scoreColor = score >= 80 ? '#10b981' : (score >= 60 ? '#f59e0b' : '#ef4444');
                var wrapper = document.createElement('div');
                wrapper.style.cssText = 'border:1px solid #e0e0e0;border-radius:6px;margin-bottom:12px;overflow:hidden';
                wrapper.innerHTML =
                    '<div style="background:#f8f9fa;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">' +
                        '<span style="font-size:13px;font-weight:600;color:#1d2327;word-break:break-all">' + esc(data.target_url || '') + '</span>' +
                        '<span style="display:flex;align-items:center;gap:10px">' +
                            '<span style="font-size:20px;font-weight:700;color:' + scoreColor + '">' + score + '/100</span>' +
                            '<button onclick="csAuditPdf(this)" data-html="' + esc(data.dashboard_html || '') + '" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:4px;padding:3px 10px;font-size:11px;cursor:pointer;color:#475569">📄 PDF</button>' +
                            '<span style="font-size:11px;color:#9ca3af">' + esc(data.date || '') + '</span>' +
                        '</span>' +
                    '</div>' +
                    '<div>' + (data.dashboard_html || '') + '</div>';
                list.prepend(wrapper);

                // Update count in heading
                var existingCount = list.children.length;
                if (heading) heading.innerHTML = '🔗 Ad-hoc Audits (' + existingCount + ')';
            }

            // ── PDF download ──────────────────────────────────────────────────
            window.csAuditPdf = function(btnOrHtml) {
                var html, title;
                if (typeof btnOrHtml === 'string') {
                    html  = btnOrHtml;
                    title = 'SEO Audit Report';
                } else {
                    html  = btnOrHtml.getAttribute('data-html') || document.getElementById('cs-audit-results').innerHTML;
                    title = 'SEO Audit — ' + (btnOrHtml.closest('[data-url]') || {dataset:{url:'Ad-hoc'}}).dataset.url;
                    title = 'SEO Audit Report';
                }

                var css = document.querySelector('style') ? Array.from(document.querySelectorAll('style')).map(function(s){ return s.innerHTML; }).join('\n') : '';
                var win = window.open('', '_blank', 'width=900,height=700');
                if (!win) { alert('Please allow pop-ups to generate PDF.'); return; }
                win.document.write(
                    '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' + title + '</title>' +
                    '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;padding:24px;background:#fff}' +
                    '.cs-audit-hero{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start;background:#0f172a;border-radius:8px;padding:28px;margin-bottom:20px}' +
                    '.cs-audit-hero-info{display:flex;align-items:center;gap:20px}' +
                    '.cs-audit-hero-grid{flex:1;display:flex;flex-wrap:wrap;gap:8px;min-width:0}' +
                    '.cs-audit-bar-lbl{width:190px;flex-shrink:0;color:#3d3d3d}' +
                    '@media print{@page{margin:10mm}button{display:none!important}}' +
                    '</style></head><body>' +
                    '<h2 style="font-size:16px;color:#374151;margin:0 0 16px">' + title + '</h2>' +
                    html +
                    '<script>window.onload=function(){window.print();}<\/script>' +
                    '</body></html>'
                );
                win.document.close();
            };

            // Wire up PDF button for pre-rendered (page-load) results
            (function() {
                var pdfBtn = document.getElementById('cs-audit-pdf-btn');
                var res    = document.getElementById('cs-audit-results');
                if (pdfBtn && res && res.style.display !== 'none') {
                    pdfBtn.onclick = function() { csAuditPdf(res.innerHTML); };
                }
            })();

            function esc(s) {
                return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function badgeHtml(status) {
                var styles = {
                    ok:   'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0',
                    warn: 'background:#fffbeb;color:#b45309;border:1px solid #fde68a',
                    fail: 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca',
                };
                var texts = { ok: '✓ OK', warn: '⚠ WARN', fail: '✗ FAIL' };
                var st = styles[status] || styles.fail;
                var tx = texts[status]  || status;
                return '<span style="' + st + ';padding:2px 8px;border-radius:2px;font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap">' + tx + '</span>';
            }

            function refreshResults(data) {
                var container = document.getElementById('cs-audit-results');
                if (!container || !data.dashboard_html) return;
                container.style.display = 'block';
                container.innerHTML = data.dashboard_html;

                // Auto-expand detail section on new audit
                var detailSection = container.querySelector('.cs-audit-detail-section');
                if (detailSection) detailSection.style.display = '';
                var detailToggle = container.querySelector('.cs-audit-detail-toggle');
                if (detailToggle) detailToggle.innerHTML = '&#9660; Hide Section Details';

                // Wire PDF button
                var pdfBtn = container.querySelector('#cs-audit-pdf-btn');
                if (pdfBtn) pdfBtn.onclick = function() { csAuditPdf(data.dashboard_html); };
            }

        })();
        </script>
        <?php
    }

    private function render_seo_audit_dashboard( ?array $data ): void {
        if ( ! $data ) return;
        $overall  = $data['overall']  ?? 0;
        $sections = $data['sections'] ?? [];
        $findings = $data['findings'] ?? [];
        $label    = $overall >= 80 ? 'GOOD' : ( $overall >= 60 ? 'NEEDS WORK' : 'CRITICAL' );
        ?>
        <!-- Score dashboard -->
        <div class="cs-audit-hero">
            <div class="cs-audit-hero-info">
                <div style="width:84px;height:84px;border-radius:50%;border:3px solid rgba(255,255,255,.12);display:flex;flex-direction:column;align-items:center;justify-content:center;background:rgba(255,255,255,.05)">
                    <span class="cs-audit-overall" style="font-size:32px;font-weight:700;color:#86efac;line-height:1"><?php echo esc_html( $overall ); ?></span>
                    <span style="font-size:10px;color:rgba(255,255,255,.35);font-family:monospace">/100</span>
                </div>
                <div>
                    <div style="font-size:18px;font-weight:600;color:#fff;margin-bottom:6px">SEO &amp; AEO Audit</div>
                    <div style="font-size:11px;color:rgba(255,255,255,.45);margin-bottom:8px"><?php echo esc_html( $data['date'] ?? '' ); ?></div>
                    <span style="background:rgba(134,239,172,.1);color:#86efac;border:1px solid rgba(134,239,172,.2);font-family:monospace;font-size:9px;letter-spacing:.12em;text-transform:uppercase;padding:4px 10px;border-radius:2px"><?php echo esc_html( $label ); ?></span>
                </div>
            </div>
            <div class="cs-audit-hero-grid">
                <?php
                $card_action_map = [
                    'security_headers' => [ 'tab' => 'devtools', 'label' => 'DevTools'  ],
                    'homepage_meta'    => [ 'tab' => 'seo',      'label' => 'SEO'       ],
                    'llms_txt'         => [ 'tab' => 'sitemap',  'label' => 'Sitemap'   ],
                    'robots_txt'       => [ 'tab' => 'sitemap',  'label' => 'Sitemap'   ],
                    'schema'           => [ 'tab' => 'seo',      'label' => 'SEO'       ],
                    'aeo'              => [ 'tab' => 'aitools',  'label' => 'AI Tools'  ],
                    'category_pages'   => [ 'tab' => '', 'label' => 'Generate with AI', 'inline' => 'gen_cat_descs' ],
                ];
                foreach ( $sections as $key => $sec ) :
                    $sc     = $sec['score'];
                    $col    = $sc >= 80 ? '#86efac' : ( $sc >= 60 ? '#fcd34d' : '#fca5a5' );
                    $card_a = $card_action_map[ $key ] ?? null;
                    $show_fix = $card_a && $sc < 100;
                    $card_inline = $card_a['inline'] ?? '';
                    if ( $key === 'security_headers' ) {
                        $fix_onclick = "window.location.href='" . esc_js( admin_url( 'admin.php?page=cloudscale-devtools' ) ) . "'";
                    } elseif ( $card_inline ) {
                        $fix_onclick = '';
                    } else {
                        $fix_onclick = "document.querySelector('.ab-tab[data-tab=\\'" . esc_js( $card_a['tab'] ?? '' ) . "\\']').click();window.scrollTo({top:0,behavior:'smooth'})";
                    }
                    ?>
                    <div class="cs-audit-score-card" data-section="<?php echo esc_attr( $key ); ?>" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:4px;padding:10px 13px;min-width:100px;display:flex;flex-direction:column;justify-content:space-between">
                        <div>
                            <div style="font-size:9px;color:rgba(255,255,255,.4);font-family:monospace;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;line-height:1.4"><?php echo esc_html( $sec['icon'] . ' ' . $sec['label'] ); ?></div>
                            <div class="cs-audit-card-num" style="font-size:22px;font-weight:700;color:<?php echo esc_attr( $col ); ?>;line-height:1"><?php echo esc_html( $sc ); ?></div>
                            <div style="font-size:9px;color:rgba(255,255,255,.25);font-family:monospace">/100</div>
                        </div>
                        <?php if ( $show_fix ) : ?>
                        <div style="margin-top:8px">
                            <?php if ( $card_inline === 'gen_cat_descs' ) : ?>
                                <button type="button" onclick="csAuditGenCatDescs(this,'desc')"
                                    style="width:100%;padding:4px 0;font-size:10px;font-weight:700;background:rgba(2,132,199,.35);border:1px solid rgba(2,132,199,.5);border-radius:4px;color:#7dd3fc;cursor:pointer;letter-spacing:.04em">
                                    ✦ Generate with AI
                                </button>
                                <span style="display:block;font-size:9px;color:rgba(255,255,255,.35);margin-top:3px;text-align:center"></span>
                            <?php else : ?>
                                <button type="button" onclick="<?php echo esc_attr( $fix_onclick ); ?>" style="width:100%;padding:4px 0;font-size:10px;font-weight:700;background:rgba(59,130,246,.25);border:1px solid rgba(59,130,246,.4);border-radius:4px;color:#93c5fd;cursor:pointer;letter-spacing:.04em">Fix &#8594; <?php echo esc_html( $card_a['label'] ); ?></button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Action bar -->
        <div style="margin:16px 0 0;background:#1e293b;border-radius:10px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div style="font-size:12px;font-weight:600;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em">Detailed Findings</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button type="button" class="cs-audit-detail-toggle" onclick="(function(b){var s=b.closest('[id]');var wrap=s?s.querySelector('.cs-audit-detail-section'):document.getElementById('cs-audit-detail-section');if(!wrap)return;var hidden=wrap.style.display==='none';wrap.style.display=hidden?'':'none';b.innerHTML=hidden?'&#9660;&nbsp;Hide Details':'&#9654;&nbsp;Show All Findings';})(this)" style="display:inline-flex;align-items:center;gap:5px;background:#3b82f6;border:none;border-radius:7px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;color:#fff;line-height:1">&#9654;&nbsp;Show All Findings</button>
                <button type="button" id="cs-audit-pdf-btn" onclick="csAuditPdf(this.closest('#cs-audit-results')?document.getElementById('cs-audit-results').innerHTML:this.closest('.cs-adhoc-entry').querySelector('.cs-audit-wrap').innerHTML)" style="display:inline-flex;align-items:center;gap:5px;background:#475569;border:none;border-radius:7px;padding:7px 16px;font-size:12px;font-weight:600;cursor:pointer;color:#fff;line-height:1">&#128196;&nbsp;Save as PDF</button>
            </div>
        </div>

        <div class="cs-audit-detail-section" style="display:none">

        <!-- Section score bars -->
        <?php
        $findings_by_section = [];
        foreach ( $findings as $f ) {
            $findings_by_section[ $f['section'] ][] = $f;
        }
        ?>
        <div style="margin-bottom:24px;background:#fff;border:1px solid #e0e0e0;border-radius:6px;overflow:hidden">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#50575e;padding:12px 16px;border-bottom:1px solid #e0e0e0">Section Scores</div>
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:560px">
                <thead>
                    <tr style="background:#f8f9fa">
                        <th style="text-align:left;padding:8px 14px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;min-width:200px">Section</th>
                        <th style="text-align:right;padding:8px 14px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;white-space:nowrap;width:80px">Score</th>
                        <th style="text-align:left;padding:8px 14px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;min-width:260px">What to fix</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $sections as $key => $sec ) :
                    $sc        = $sec['score'];
                    $bar_color = $sc >= 80 ? '#10b981' : ( $sc >= 60 ? '#f59e0b' : '#ef4444' );
                    $bad       = array_filter( $findings_by_section[ $key ] ?? [], fn( $f ) => $f['status'] !== 'ok' );
                    ?>
                    <tr style="border-bottom:1px solid #f0f0f1" data-section="<?php echo esc_attr( $key ); ?>">
                        <td style="padding:10px 14px;vertical-align:middle">
                            <div style="font-size:12px;font-weight:500;color:#1d2327;margin-bottom:6px"><?php echo esc_html( $sec['icon'] . ' ' . $sec['label'] ); ?></div>
                            <div style="height:6px;background:#f0f0f1;border-radius:3px;overflow:hidden">
                                <div style="height:100%;width:<?php echo esc_attr( $sc ); ?>%;background:<?php echo esc_attr( $bar_color ); ?>;border-radius:3px;transition:width .5s"></div>
                            </div>
                        </td>
                        <td style="padding:10px 14px;text-align:right;vertical-align:middle;white-space:nowrap">
                            <span style="font-size:16px;font-weight:700;font-family:monospace;color:<?php echo esc_attr( $bar_color ); ?>"><?php echo esc_html( $sc ); ?></span>
                            <span style="font-size:10px;color:#9ca3af;font-family:monospace">/100</span>
                        </td>
                        <td style="padding:10px 14px;vertical-align:middle">
                            <?php if ( ! empty( $bad ) ) : ?>
                            <ul style="margin:0;padding:0;list-style:none">
                                <?php foreach ( $bad as $bf ) :
                                    $icon = $bf['status'] === 'warn' ? '⚠' : '✗';
                                    $col  = $bf['status'] === 'warn' ? '#b45309' : '#b91c1c';
                                    $note = trim( $bf['note'] );
                                    ?>
                                    <li style="font-size:11px;color:<?php echo esc_attr( $col ); ?>;line-height:1.5;margin-bottom:3px">
                                        <strong><?php echo esc_html( $icon . ' ' . $bf['check'] ); ?></strong><?php echo $note ? ': ' . esc_html( $note ) : ''; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php else : ?>
                            <span style="font-size:11px;color:#10b981">&#10003; All checks passed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php
        // Map each section to the plugin tab that fixes it (empty = manual/no in-plugin action).
        $devtools_url = esc_url( admin_url( 'admin.php?page=cloudscale-devtools' ) );

        // Per-check targeted actions: tab to switch to + JS querySelector to focus/scroll to.
        $check_action_map = [
            'Person sameAs — Twitter/X'          => [ 'tab' => 'seo',     'label' => 'Add Twitter handle',    'sel' => '#cs_seo_twitter_handle',   'href' => '' ],
            'Person worksFor'                    => [ 'tab' => 'seo',     'label' => 'Add employer name',     'sel' => '#cs_seo_works_for_name',   'href' => '' ],
            'Person knowsAbout'                  => [ 'tab' => 'seo',     'label' => 'Add sameAs/knowsAbout', 'sel' => 'textarea[name*="sameas"]', 'href' => '' ],
            'BlogPosting publisher @type'        => [ 'tab' => 'seo',     'label' => 'Schema Settings',       'sel' => '',                         'href' => '' ],
            'BreadcrumbList schema'              => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'enable_breadcrumbs' ],
            'FAQPage / QAPage schema'            => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'generate_faq_schema' ],
            'HowTo schema on step-by-step posts' => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'generate_howto_schema' ],
            'Answer-first paragraphs on top posts' => [ 'tab' => 'aitools', 'label' => 'Generate AEO',        'sel' => '#ab-ai-gen-aeo',           'href' => '' ],
            'Speakable schema'                   => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'enable_speakable_schema' ],
            'Category intro text (descriptions)' => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'gen_cat_descs' ],
            'Category SEO meta descriptions set' => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'gen_cat_descs' ],
            'Missing SEO title tags on posts/pages' => [ 'tab' => 'titleopt', 'label' => 'Fix It — Open SEO AI', 'sel' => '#ab-titleopt-analyse-all', 'href' => '' ],
            '/blog/ and /posts/ duplicate archive pages' => [ 'tab' => '', 'label' => '', 'sel' => '', 'href' => '', 'inline' => 'add_archive_redirects' ],
            'security_headers'                   => [ 'tab' => '',        'label' => 'Open DevTools',         'sel' => '',                         'href' => $devtools_url ],
        ];
        ?>
        <script>
        function csAuditFix(tab, sel, href) {
            if (href) { window.location.href = href; return; }
            var tabEl = tab ? document.querySelector('.ab-tab[data-tab="' + tab + '"]') : null;
            if (tabEl) tabEl.click();
            setTimeout(function() {
                var target = sel ? document.querySelector(sel) : null;
                if (target) {
                    target.scrollIntoView({behavior:'smooth', block:'center'});
                    if (target.focus) target.focus();
                    target.style.outline = '2px solid #3b82f6';
                    setTimeout(function(){ target.style.outline = ''; }, 2500);
                } else {
                    window.scrollTo({top:0, behavior:'smooth'});
                }
            }, 250);
        }

        // One-click: enable Speakable schema.
        function csAuditEnableSpeakable(btn) {
            btn.disabled = true; btn.textContent = '⏳ Enabling…';
            var fd = new FormData();
            fd.append('action','cs_seo_audit_quickfix'); fd.append('quickfix','enable_speakable_schema'); fd.append('nonce',csSeoAdmin.nonce);
            fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                btn.textContent = r.success ? '✅ Enabled — re-run audit' : '❌ ' + (r.data||'Error');
                btn.style.background = r.success ? '#10b981' : '#ef4444';
            }).catch(function(){ btn.textContent = '❌ Network error'; btn.style.background='#ef4444'; });
        }

        // AI: generate HowTo JSON-LD schema for top post.
        function csAuditGenHowToSchema(btn) {
            btn.disabled = true; btn.textContent = '⏳ Generating…';
            var fd = new FormData();
            fd.append('action','cs_seo_audit_quickfix'); fd.append('quickfix','generate_howto_schema'); fd.append('nonce',csSeoAdmin.nonce);
            fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                btn.textContent = r.success ? '✅ Done — re-run audit' : '❌ ' + (r.data||'Error');
                btn.style.background = r.success ? '#10b981' : '#ef4444';
                if (r.success && btn.nextElementSibling) btn.nextElementSibling.textContent = r.data && r.data.message ? r.data.message : '';
            }).catch(function(){ btn.textContent = '❌ Network error'; btn.style.background='#ef4444'; });
        }

        // AI: generate FAQPage JSON-LD schema for top posts.
        function csAuditGenFaqSchema(btn) {
            btn.disabled = true; btn.textContent = '⏳ Generating…';
            var fd = new FormData();
            fd.append('action','cs_seo_audit_quickfix'); fd.append('quickfix','generate_faq_schema'); fd.append('nonce',csSeoAdmin.nonce);
            fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                btn.textContent = r.success ? '✅ Done — re-run audit' : '❌ ' + (r.data||'Error');
                btn.style.background = r.success ? '#10b981' : '#ef4444';
                if (r.success && btn.nextElementSibling) btn.nextElementSibling.textContent = r.data && r.data.message ? r.data.message : '';
            }).catch(function(){ btn.textContent = '❌ Network error'; btn.style.background='#ef4444'; });
        }

        // One-click: add 301 redirects for /blog/ and /posts/ duplicate archives.
        function csAuditAddArchiveRedirects(btn) {
            btn.disabled = true; btn.textContent = '⏳ Adding…';
            var fd = new FormData();
            fd.append('action','cs_seo_audit_quickfix'); fd.append('quickfix','add_archive_redirects'); fd.append('nonce',csSeoAdmin.nonce);
            fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                btn.textContent = r.success ? '✅ Done — re-run audit' : '❌ ' + (r.data||'Error');
                btn.style.background = r.success ? '#10b981' : '#ef4444';
                if (r.success && btn.nextElementSibling) btn.nextElementSibling.textContent = r.data && r.data.message ? r.data.message : '';
            }).catch(function(){ btn.textContent = '❌ Network error'; btn.style.background='#ef4444'; });
        }

        // One-click: enable breadcrumb schema option.
        function csAuditEnableBreadcrumbs(btn) {
            btn.disabled = true; btn.textContent = '⏳ Enabling…';
            var fd = new FormData();
            fd.append('action','cs_seo_audit_quickfix'); fd.append('quickfix','enable_breadcrumbs'); fd.append('nonce',csSeoAdmin.nonce);
            fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(r){
                btn.textContent = r.success ? '✅ Enabled — re-run audit' : '❌ ' + (r.data||'Error');
                btn.style.background = r.success ? '#10b981' : '#ef4444';
            }).catch(function(){ btn.textContent = '❌ Network error'; btn.style.background='#ef4444'; });
        }

        // Batch AI: generate meta description + intro for all categories missing them.
        async function csAuditGenCatDescs(btn, field) {
            btn.disabled = true;
            var status = btn.nextElementSibling;
            function setStatus(msg) { if(status) status.textContent = msg; }

            // 1. Load category list.
            setStatus('Loading categories…');
            var fd1 = new FormData(); fd1.append('action','cs_seo_cat_seo_list'); fd1.append('nonce',csSeoAdmin.nonce);
            var listR = await fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd1}).then(r=>r.json()).catch(()=>null);
            if (!listR || !listR.success) { setStatus('❌ Could not load categories.'); btn.disabled=false; return; }

            var cats = listR.data.filter(function(c){ return !c.desc; });
            if (!cats.length) { setStatus('✅ All categories already have descriptions.'); btn.disabled=false; return; }

            var done = 0, total = cats.length;
            for (var i = 0; i < cats.length; i++) {
                var cat = cats[i];
                setStatus('Generating ' + (i+1) + '/' + total + ': ' + cat.name + '…');
                var fd2 = new FormData(); fd2.append('action','cs_seo_cat_seo_ai_gen'); fd2.append('nonce',csSeoAdmin.nonce); fd2.append('term_id',cat.term_id);
                var genR = await fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd2}).then(r=>r.json()).catch(()=>null);
                if (!genR || !genR.success) { setStatus('⚠ Skipped ' + cat.name + ' (AI error)'); continue; }
                // Save.
                var fd3 = new FormData(); fd3.append('action','cs_seo_audit_quickfix'); fd3.append('quickfix','save_cat_seo'); fd3.append('nonce',csSeoAdmin.nonce);
                fd3.append('term_id', cat.term_id); fd3.append('title', genR.data.title||''); fd3.append('desc', genR.data.desc||''); fd3.append('intro', genR.data.intro||'');
                await fetch(csSeoAdmin.ajaxUrl,{method:'POST',body:fd3});
                done++;
            }
            btn.textContent = '✅ Done';
            btn.style.background = '#10b981';
            setStatus(done + '/' + total + ' categories updated. Re-run audit to confirm.');
        }
        </script>
        <!-- Findings table -->
        <div style="background:#fff;border:1px solid #e0e0e0;border-radius:6px;overflow:hidden">
            <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.08em;color:#50575e;padding:12px 16px;border-bottom:1px solid #e0e0e0">All Findings (<?php echo count( $findings ); ?>)</div>
            <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:#f8f9fa">
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;min-width:160px">Check</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;white-space:nowrap">Status</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;min-width:120px">Value</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;min-width:280px">Notes</th>
                        <th style="text-align:left;padding:8px 12px;border-bottom:1px solid #e0e0e0;font-size:11px;color:#50575e;font-weight:600;white-space:nowrap">Fix</th>
                    </tr>
                </thead>
                <tbody id="cs-audit-findings-tbody">
                <?php foreach ( $findings as $f ) :
                    $st = $f['status'];
                    $bs = match( $st ) {
                        'ok'   => 'background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0',
                        'warn' => 'background:#fffbeb;color:#b45309;border:1px solid #fde68a',
                        default=> 'background:#fef2f2;color:#b91c1c;border:1px solid #fecaca',
                    };
                    $bt  = match( $st ) { 'ok' => '✓ OK', 'warn' => '⚠ WARN', default => '✗ FAIL' };
                    $ca  = $check_action_map[ $f['check'] ] ?? null;
                    // For security headers the key is the check name
                    if ( ! $ca && $f['section'] === 'security_headers' ) {
                        $ca = $check_action_map['security_headers'];
                    }
                    ?>
                    <tr style="border-bottom:1px solid #f8f9fa">
                        <td style="padding:8px 12px;font-weight:500;color:#1d2327"><?php echo esc_html( $f['check'] ); ?></td>
                        <td style="padding:8px 12px;white-space:nowrap"><span style="<?php echo esc_attr( $bs ); ?>;padding:2px 8px;border-radius:2px;font-family:monospace;font-size:10px;text-transform:uppercase;letter-spacing:.08em"><?php echo esc_html( $bt ); ?></span></td>
                        <td style="padding:8px 12px;font-family:monospace;font-size:11px;color:#50575e;max-width:180px;word-break:break-all"><?php echo esc_html( $f['value'] ); ?></td>
                        <td style="padding:8px 12px;font-size:12px;color:#374151;line-height:1.5"><?php echo esc_html( $f['note'] ); ?></td>
                        <td style="padding:8px 12px">
                        <?php if ( $st !== 'ok' && $ca ) :
                            $inline = $ca['inline'] ?? '';
                            if ( $inline === 'enable_breadcrumbs' ) : ?>
                                <button type="button" onclick="csAuditEnableBreadcrumbs(this)"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#7c3aed;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ⚡ Enable Now
                                </button>
                            <?php elseif ( $inline === 'gen_cat_descs' ) : ?>
                                <button type="button" onclick="csAuditGenCatDescs(this,'desc')"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#0284c7;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ✦ Generate All with AI
                                </button>
                                <span style="display:block;font-size:10px;color:#6b7280;margin-top:3px"></span>
                            <?php elseif ( $inline === 'enable_speakable_schema' ) : ?>
                                <button type="button" onclick="csAuditEnableSpeakable(this)"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#7c3aed;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ⚡ Enable Now
                                </button>
                            <?php elseif ( $inline === 'generate_howto_schema' ) : ?>
                                <button type="button" onclick="csAuditGenHowToSchema(this)"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#0e7490;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ✦ Generate HowTo with AI
                                </button>
                                <span style="display:block;font-size:10px;color:#6b7280;margin-top:3px"></span>
                            <?php elseif ( $inline === 'generate_faq_schema' ) : ?>
                                <button type="button" onclick="csAuditGenFaqSchema(this)"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#7c3aed;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ✦ Generate FAQ with AI
                                </button>
                                <span style="display:block;font-size:10px;color:#6b7280;margin-top:3px"></span>
                            <?php elseif ( $inline === 'add_archive_redirects' ) : ?>
                                <button type="button" onclick="csAuditAddArchiveRedirects(this)"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#b45309;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    ⚡ Add 301 Redirects
                                </button>
                                <span style="display:block;font-size:10px;color:#6b7280;margin-top:3px"></span>
                            <?php elseif ( $ca['label'] ) :
                                $js_tab  = esc_js( $ca['tab'] ?? '' );
                                $js_sel  = esc_js( $ca['sel'] ?? '' );
                                $js_href = esc_js( $ca['href'] ?? '' );
                                ?>
                                <button type="button"
                                    onclick="csAuditFix('<?php echo $js_tab; ?>','<?php echo $js_sel; ?>','<?php echo $js_href; ?>')"
                                    style="padding:4px 11px;font-size:11px;font-weight:600;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;white-space:nowrap">
                                    &#9656; <?php echo esc_html( $ca['label'] ); ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        </div><!-- /cs-audit-detail-section -->
        <?php
    }
}
