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
            'post_id'    => $post_id,
            'post_title' => get_the_title( $post_id ),
            'post_edit'  => (string) get_edit_post_link( $post_id, 'raw' ),
            'links'      => $links,
        ]);
    }

    // =========================================================================
    // AJAX — check a single URL
    // =========================================================================

    /**
     * Performs a HEAD request against a URL and returns its HTTP status.
     *
     * Falls back to 0 / "Connection failed" if wp_remote_head() returns a WP_Error.
     *
     * @since 4.19.145
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

        $response = wp_remote_head( $url, [
            'timeout'     => 8,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (compatible; CloudScale-BLC/1.0; +'
                             . home_url() . ')',
            'sslverify'   => false,
        ]);

        if ( is_wp_error( $response ) ) {
            wp_send_json_success([
                'url'    => $url,
                'status' => 0,
                'label'  => 'Connection failed',
                'ok'     => false,
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        $ok   = ( $code >= 200 && $code < 400 );

        wp_send_json_success([
            'url'    => $url,
            'status' => $code,
            'label'  => $this->blc_status_label( $code ),
            'ok'     => $ok,
        ]);
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
