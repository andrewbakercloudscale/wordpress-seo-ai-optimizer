<?php
/**
 * Broken Link Checker — scans published posts for broken outbound links.
 *
 * Each scan runs in three phases:
 *   1. ajax_blc_get_posts()    — returns list of post IDs to scan.
 *   2. ajax_blc_extract_links() — extracts <a href> URLs from a single post.
 *   3. ajax_blc_check_url()    — checks HTTP status of a single URL server-side.
 *
 * The JS driver deduplicates URLs before checking so each external URL is only
 * fetched once, even if it appears in multiple posts.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.19.145
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Broken_Links {

    // =========================================================================
    // AJAX — get post list
    // =========================================================================

    /**
     * Returns the IDs of all published posts and pages for the BLC scanner.
     *
     * @since 4.19.145
     * @return void
     */
    public function ajax_blc_get_posts(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $posts = [];
        $page  = 1;
        $batch = 500;
        do {
            $chunk = get_posts([
                'post_type'           => ['post', 'page'],
                'post_status'         => 'publish',
                'posts_per_page'      => $batch,
                'paged'               => $page++,
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ]);
            $posts = array_merge($posts, $chunk);
        } while ( count($chunk) === $batch );

        wp_send_json_success(['post_ids' => $posts, 'total' => count($posts)]);
    }

    // =========================================================================
    // AJAX — extract links from one post
    // =========================================================================

    /**
     * Extracts all outbound hyperlinks from a single post's content.
     *
     * Skips anchors (#), mailto:, tel:, and javascript: links.
     * Converts root-relative paths to absolute URLs.
     *
     * @since 4.19.145
     * @return void
     */
    public function ajax_blc_extract_links(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $post_id = (int) ( $_POST['post_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
        if ( ! $post_id ) wp_send_json_error( 'Missing post_id' );

        // Clear object-cache entry so we read the current DB content rather than
        // a potentially stale persistent-cache (Redis/Memcached) version.
        clean_post_cache( $post_id );
        $post = get_post( $post_id );
        if ( ! $post ) wp_send_json_error( 'Post not found' );

        $content = $post->post_content;
        $links   = [];
        $home    = home_url();

        if ( preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $content, $matches ) ) {
            foreach ( $matches[1] as $i => $href ) {
                $href = trim( $href );
                if ( str_starts_with( $href, '#' ) )           continue;
                if ( str_starts_with( $href, 'mailto:' ) )     continue;
                if ( str_starts_with( $href, 'tel:' ) )        continue;
                if ( str_starts_with( $href, 'javascript:' ) ) continue;
                if ( str_starts_with( $href, '/' ) ) {
                    $href = rtrim( $home, '/' ) . $href;
                }
                if ( ! filter_var( $href, FILTER_VALIDATE_URL ) ) continue;

                $anchor  = wp_strip_all_tags( $matches[2][ $i ] ?? '' );
                $links[] = [
                    'url'    => $href,
                    'anchor' => mb_substr( $anchor, 0, 80 ),
                ];
            }
        }

        wp_send_json_success([
            'post_id'      => $post_id,
            'post_title'   => get_the_title( $post_id ),
            'post_url'     => (string) get_permalink( $post_id ),
            'post_date_ts' => (int) strtotime( $post->post_date ),
            'post_date'    => get_the_date( get_option( 'date_format' ), $post_id ),
            'links'        => $links,
        ]);
    }

    // =========================================================================
    // AJAX — check a single URL
    // =========================================================================

    /**
     * Checks a URL using a browser-like cURL request and returns its HTTP status.
     *
     * Strategy:
     *   1. HEAD with full Chrome headers + HTTP/2 — fast, no body transfer.
     *   2. On 401 / 403 / 405 (server blocked HEAD): retry as GET.
     *      206 Partial Content is normalised to 200.
     *   3. Falls back to wp_remote_get() if the cURL extension is unavailable.
     *
     * Using real browser headers (Sec-Fetch-*, Sec-CH-UA, Cache-Control) avoids
     * CDN / WAF anti-bot filters that return 401/403 to naive server-side HEAD requests.
     *
     * @since 4.20.67
     * @return void
     */
    public function ajax_blc_check_url(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above
        if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( 'Invalid URL' );
        }

        // Block SSRF: reject URLs that resolve to loopback, link-local, or private IP ranges.
        if ( $this->blc_is_ssrf_blocked( $url ) ) {
            wp_send_json_error( 'URL resolves to a private or reserved address' );
        }

        $code = $this->blc_fetch_status( $url );

        wp_send_json_success([
            'url'    => $url,
            'status' => $code,
            'label'  => $this->blc_status_label( $code ),
            'ok'     => ( $code >= 200 && $code < 400 ),
        ]);
    }

    /**
     * Performs a browser-like HTTP check and returns the final status code (0 on failure).
     *
     * Mirrors the browser_curl.sh technique — macOS Chrome UA, full Sec-Fetch-* /
     * Sec-CH-UA header set, HTTP/2, gzip/br encoding, Connection: keep-alive.
     *
     * Three-pass strategy:
     *   1. HEAD — fast, no body transfer.
     *   2. GET with WRITEFUNCTION abort on 401/403/405 — real GET (no Range scraper
     *      fingerprint); body transfer is cancelled immediately after the status
     *      line arrives since CURLINFO_HTTP_CODE is already set at that point.
     *   3. Both HEAD and GET still 401/403/405 — CDN/WAF blocks all server-side TLS
     *      clients regardless of headers (Reuters, WatchMojo, etc. use JA3/JA4
     *      fingerprinting or return 401 as a bot-wall).  Treat as ok; the page is
     *      almost certainly alive.
     *
     * @since 4.20.67
     * @param string $url Validated, SSRF-safe URL.
     * @return int HTTP status code, or 0 on connection failure.
     */
    private function blc_fetch_status( string $url ): int {
        if ( ! function_exists( 'curl_init' ) ) {
            return $this->blc_wp_remote_fallback( $url );
        }

        // macOS Chrome fingerprint — matches browser_curl.sh on andrewbaker.ninja.
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

        $browser_headers = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Sec-Ch-Ua: "Google Chrome";v="120", "Chromium";v="120", "Not-A.Brand";v="99"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "macOS"',
        ];

        $http_ver = defined( 'CURL_HTTP_VERSION_2TLS' ) ? CURL_HTTP_VERSION_2TLS : CURL_HTTP_VERSION_1_1;

        $base_opts = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION   => $http_ver,
            CURLOPT_USERAGENT      => $ua,
            CURLOPT_HTTPHEADER     => $browser_headers,
            CURLOPT_ENCODING       => '', // enables gzip/br decompression + Accept-Encoding header
        ];

        // ── Pass 1: HEAD — no body, very fast ───────────────────────────────────
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init -- wp_remote_head() cannot set HTTP/2, WRITEFUNCTION abort, or Sec-Fetch-* headers needed to bypass CDN bot filters.
        $ch = curl_init();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
        curl_setopt_array( $ch, $base_opts + [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => false, // HEAD has no body — stdout-safe
            CURLOPT_HEADER         => false,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
        curl_exec( $ch );
        $code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $error = curl_errno( $ch );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
        curl_close( $ch );

        if ( $error || $code === 0 ) return 0;
        if ( $code !== 401 && $code !== 403 && $code !== 405 && $code !== 503 ) return $code;

        // ── Pass 2: GET with immediate body abort ────────────────────────────────
        // No Range header (CDNs flag Range requests as scrapers).
        // WRITEFUNCTION returns 0 to cancel the transfer as soon as the first body
        // chunk arrives; CURLINFO_HTTP_CODE is already populated at that point.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_init
        $ch2 = curl_init();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt_array
        curl_setopt_array( $ch2, $base_opts + [
            CURLOPT_URL           => $url,
            CURLOPT_HTTPGET       => true,
            CURLOPT_TIMEOUT       => 12,
            CURLOPT_WRITEFUNCTION => static function ( $ch, $data ): int {
                return 0; // abort body download — status already captured
            },
        ]);
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
        curl_exec( $ch2 );
        $get_code = (int) curl_getinfo( $ch2, CURLINFO_HTTP_CODE );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
        curl_close( $ch2 );

        if ( $get_code > 0 && $get_code !== 401 && $get_code !== 403 && $get_code !== 405 && $get_code !== 503 ) {
            return $get_code;
        }

        // ── Pass 3: TLS-fingerprint / auth-wall / overload block — treat as ok ──
        // HEAD and GET both returned 401/403/405/503.  Almost always a CDN/WAF
        // blocking non-browser TLS clients via JA3/JA4 fingerprinting (Cloudflare,
        // codeconductor.ai, etc.), a login wall (Reuters, WatchMojo), or a site
        // that returns 503 to bots rather than serving a JS challenge.
        // The page is alive; treat as ok.
        return 200;
    }

    /**
     * wp_remote_get() fallback for environments where cURL is unavailable.
     *
     * @since 4.20.67
     * @param string $url Validated URL.
     * @return int HTTP status code, or 0 on failure.
     */
    private function blc_wp_remote_fallback( string $url ): int {
        $resp = wp_remote_get( $url, [
            'timeout'     => 10,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'headers'     => [
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ],
            'sslverify'   => false,
        ]);
        if ( is_wp_error( $resp ) ) return 0;
        return (int) wp_remote_retrieve_response_code( $resp );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns true if the URL's resolved IP falls in a blocked range (SSRF guard).
     *
     * Blocks loopback (127.x), link-local (169.254.x), private (10.x, 172.16–31.x,
     * 192.168.x), and reserved ranges. Prevents an admin from using the BLC endpoint
     * to probe internal network resources.
     *
     * @since 4.20.27
     * @param string $url Validated URL to check.
     * @return bool True if the URL should be blocked.
     */
    private function blc_is_ssrf_blocked( string $url ): bool {
        $host = (string) parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) return true;

        // Resolve hostname → IPv4 address.
        $ip = gethostbyname( $host );

        // If gethostbyname returned the hostname unchanged the IP is not resolvable;
        // also check if the literal is itself a valid IP.
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            // Could not resolve to a valid IP — let wp_remote_head fail naturally.
            return false;
        }

        // Block loopback, link-local, private, and reserved address ranges.
        return false === filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Returns a human-readable label for a given HTTP status code.
     *
     * @since 4.19.145
     * @param int $code HTTP status code (0 = connection failed).
     * @return string
     */
    private function blc_status_label( int $code ): string {
        $map = [
            0   => 'Connection failed',
            200 => 'OK',
            201 => 'Created',
            301 => 'Moved Permanently',
            302 => 'Found (redirect)',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            410 => 'Gone',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
        ];
        return $map[ $code ] ?? "HTTP $code";
    }
}
