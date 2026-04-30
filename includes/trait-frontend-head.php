<?php
/**
 * Frontend <head> output — canonical, meta description, OG tags, schema, robots, and JS defer.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Frontend_Head {
    /**
     * Filters the document title to use a custom SEO title when set.
     *
     * @since 4.0.0
     * @param string $default The default title from WordPress.
     * @return string Filtered title string.
     */
    public function filter_title(string $default): string {
        if (is_admin()) return $default;

        if (is_front_page() || is_home()) {
            $t = trim((string) $this->opts['home_title']);
            return $t ?: $default;
        }

        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_TITLE, true));
            if ($custom !== '') return $custom;
            return $default;
        }

        if (is_category() || is_tag()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $custom = trim((string) get_term_meta($term->term_id, self::META_TERM_TITLE, true));
                if ($custom !== '') return $custom;
            }
        }

        $suffix = (string) $this->opts['title_suffix'];
        if ($suffix && substr($default, -strlen($suffix)) !== $suffix) {
            return $default . $suffix;
        }
        return $default;
    }

    // =========================================================================
    // Head output
    // =========================================================================

    /**
     * Outputs the full SEO head block (canonical, meta, OG tags, schema) on wp_head.
     *
     * @since 4.0.0
     * @return void
     */
    public function render_head(): void {
        if (is_admin()) return;
        echo $this->build_seo_block(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- build_seo_block() returns pre-escaped HTML
        $this->print_schema_tags();
    }

    /**
     * Adds the defer attribute to frontend script tags to eliminate render blocking.
     *
     * Uses defer (not async): defer preserves execution order and is safe for scripts
     * with dependencies (e.g. jQuery + plugins). Async executes out-of-order and breaks them.
     *
     * @since 4.9.3
     * @param string $tag    The full `<script>` HTML tag.
     * @param string $handle The registered script handle.
     * @param string $src    The script source URL.
     * @return string Modified script tag.
     */
    public function defer_script_tag(string $tag, string $handle, string $src): string {
        // Never touch admin or login pages.
        if (is_admin()) return $tag;

        // Never add defer to a tag that already has defer or async.
        if (strpos($tag, ' defer') !== false || strpos($tag, ' async') !== false) return $tag;

        // Built-in exclusion list — handles and URL substrings that must not be deferred.
        $builtin_excludes = [
            // jQuery must load synchronously so dependent scripts can call $(document).ready()
            // before DOMContentLoaded fires — deferring it breaks virtually every theme.
            'jquery',
            'jquery-core',
            'jquery-migrate',
            // WordPress core inline scripts that call wp.apiFetch etc. immediately.
            'wp-embed',
            // WooCommerce checkout/cart — timing sensitive.
            'wc-checkout',
            'wc-cart',
            'wc-add-to-cart',
            // reCAPTCHA / hCaptcha — must load synchronously for form validation.
            'recaptcha',
            'hcaptcha',
            // Google Analytics / Tag Manager — usually self-async but let them manage it.
            'google-tag-manager',
            'gtag',
            // Elementor frontend must load before the DOM is painted.
            'elementor-frontend',
        ];

        // User-defined exclusions (handle names or URL substrings, one per line).
        $user_excludes_raw = trim((string)($this->opts['defer_js_excludes'] ?? ''));
        $user_excludes     = $user_excludes_raw
            ? array_filter(array_map('trim', explode("\n", $user_excludes_raw)))
            : [];

        $all_excludes = array_merge($builtin_excludes, $user_excludes);

        $handle_lower = strtolower($handle);
        $src_lower    = strtolower($src);

        foreach ($all_excludes as $ex) {
            $ex = strtolower(trim($ex));
            if ($ex === '') continue;
            if (strpos($handle_lower, $ex) !== false) return $tag;
            if (strpos($src_lower,   $ex) !== false) return $tag;
        }

        // Inject defer attribute. Plugin requires WP 6.0; wp_enqueue_script() 'strategy'
        // parameter was added in WP 6.3. Filter approach is used for 6.0–6.2 compatibility.
        // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion -- defer applied via filter, not enqueue strategy; WP 6.0 compat
        return str_replace('<script ', '<script defer ', $tag);
    }
    /**
     * Builds the complete SEO <head> block: canonical, meta description, robots, and OG tags.
     *
     * @since 4.0.0
     * @return string HTML string ready for output in <head>.
     */
    private function build_seo_block(): string {
        $out = "\n<!-- CloudScale SEO AI Optimizer " . self::VERSION . " -->\n";

        $canonical = $this->canonical_url();
        if ($canonical) $out .= '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";

        $desc = $this->meta_desc();
        if ($desc) $out .= '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";

        $pname = trim((string) $this->opts['person_name']);
        if ($pname) $out .= '<meta name="author" content="' . esc_attr($pname) . '">' . "\n";

        $robots = $this->robots();
        if ($robots) $out .= '<meta name="robots" content="' . esc_attr($robots) . '">' . "\n";

        if ((int) $this->opts['enable_og']) {
            $out .= $this->render_og_tags();
        }

        $out .= "<!-- /CloudScale SEO AI Optimizer -->\n";
        return $out;
    }

    // =========================================================================
    // Canonical / URL helpers
    // =========================================================================

    /**
     * Returns the canonical URL for the current page, or an empty string if not applicable.
     *
     * @since 4.0.0
     * @return string Canonical URL, or empty string.
     */
    private function canonical_url(): string {
        if (is_singular()) return $this->clean_url((string) get_permalink((int) get_queried_object_id()));
        if (is_front_page() || is_home()) return $this->clean_url(home_url('/'));
        if (is_archive()) return $this->clean_url((string) get_pagenum_link(max(1, (int) get_query_var('paged'))));
        return '';
    }

    /**
     * Strips UTM and other tracking parameters from a URL when the option is enabled.
     *
     * @since 4.0.0
     * @param string $url The URL to clean.
     * @return string The URL with tracking parameters removed, or the original URL unchanged.
     */
    private function clean_url(string $url): string {
        if (!(int) $this->opts['strip_tracking_params']) return $url;
        $p = wp_parse_url($url);
        if (!$p) return $url;
        $scheme = $p['scheme'] ?? 'https';
        $host   = $p['host']   ?? '';
        $path   = $p['path']   ?? '/';
        $port   = isset($p['port']) ? ':' . $p['port'] : '';
        $qs     = '';
        if (!empty($p['query'])) {
            parse_str($p['query'], $q);
            foreach (array_keys($q) as $k) {
                $kl = strtolower((string) $k);
                if (
                    strpos($kl, 'utm_') === 0 ||
                    strpos($kl, 'prp_page_') === 0 ||
                    in_array($kl, ['fbclid','gclid','msclkid'], true)
                ) unset($q[$k]);
            }
            if ($q) $qs = '?' . http_build_query($q);
        }
        return $scheme . '://' . $host . $port . $path . $qs;
    }

    // =========================================================================
    // Meta description
    // =========================================================================

    /**
     * Returns the meta description for the current page.
     *
     * Priority: custom SEO meta field → post excerpt → post content → site default.
     *
     * @since 4.0.0
     * @return string Meta description, clipped to 160 characters.
     */
    private function meta_desc(): string {
        if (is_front_page() || is_home()) {
            $h = trim((string) $this->opts['home_desc']);
            if ($h) return $this->clip($h, 160);
        }
        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_DESC, true));
            if ($custom) return $this->clip($custom, 160);
            $post = get_post($pid);
            if ($post) {
                if (!empty($post->post_excerpt)) {
                    return $this->clip(Cs_Seo_Utils::text_from_html((string) $post->post_excerpt), 160);
                }
                return $this->clip(Cs_Seo_Utils::text_from_html((string) $post->post_content), 160);
            }
        }

        if (is_category() || is_tag()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $custom = trim((string) get_term_meta($term->term_id, self::META_TERM_DESC, true));
                if ($custom) return $this->clip($custom, 160);
            }
        }

        $d = trim((string) $this->opts['default_desc']);
        return $d ? $this->clip($d, 160) : '';
    }


    // =========================================================================
    // Robots
    // =========================================================================

    /**
     * Returns the robots meta content string for the current page, or empty if no restrictions apply.
     *
     * @since 4.0.0
     * @return string Robots directive (e.g. 'noindex,follow') or empty string.
     */
    private function robots(): string {
        if ((int) $this->opts['noindex_search']          && is_search())     return 'noindex,follow';
        if ((int) $this->opts['noindex_404']             && is_404())        return 'noindex,follow';
        if ((int) $this->opts['noindex_attachment']      && is_attachment()) return 'noindex,follow';
        if ((int) $this->opts['noindex_author_archives'] && is_author())     return 'noindex,follow';
        if ((int) $this->opts['noindex_tag_archives']    && is_tag())        return 'noindex,follow';
        if (is_singular()) {
            $pid = (int) get_queried_object_id();
            if ($pid && (int) get_post_meta($pid, self::META_NOINDEX, true)) return 'noindex,follow';
        }
        return '';
    }

    /**
     * Returns true if the current page has a noindex robots directive.
     *
     * @since 4.0.0
     * @return bool
     */
    private function is_noindexed(): bool {
        return $this->robots() !== '';
    }

    // =========================================================================
    // OG image size registration
    // =========================================================================

    /**
     * Registers a 1200×630 hard-cropped image size for OG/social preview images.
     *
     * @since 4.10.34
     * @return void
     */
    public function register_og_image_size(): void {
        add_image_size('cs_seo_og_image', 1200, 630, true);
    }

    // =========================================================================
    // OG image
    // =========================================================================

    /**
     * Returns OG image data for the current page.
     *
     * Priority: custom OG image → featured image → site default → none.
     *
     * @since 4.0.0
     * @return array Associative array with keys 'url', 'width', 'height', 'type', 'alt'.
     */
    private function og_image_data(): array {
        $url = ''; $width = 0; $height = 0; $type = ''; $alt = '';

        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_OGIMG, true));
            if ($custom) {
                $url    = $custom;
                $ck     = 'cs_seo_attid_' . md5($custom);
                $cached = get_transient($ck);
                if ($cached !== false) {
                    $att_id = (int) $cached;
                } else {
                    $att_id = attachment_url_to_postid($custom);
                    set_transient($ck, (int) $att_id, 12 * HOUR_IN_SECONDS);
                }
                if ($att_id) {
                    $meta = wp_get_attachment_metadata($att_id);
                    if (!empty($meta['width']))  $width  = (int) $meta['width'];
                    if (!empty($meta['height'])) $height = (int) $meta['height'];
                    $type = get_post_mime_type($att_id) ?: '';
                    $alt  = trim((string) get_post_meta($att_id, '_wp_attachment_image_alt', true));
                }
            } elseif (has_post_thumbnail($pid)) {
                $thumb_id = (int) get_post_thumbnail_id($pid);
                // Prefer the 1200×630 OG crop — WhatsApp requires ~1.91:1 aspect ratio.
                // If the crop does not exist (e.g. portrait source image that is too narrow),
                // generate a letterboxed 1200×630 JPEG with white padding and cache it.
                $src = wp_get_attachment_image_src($thumb_id, 'cs_seo_og_image');
                if (empty($src[0]) || (isset($src[1]) && (int)$src[1] !== 1200)) {
                    $letterbox_url = $this->generate_og_letterbox($thumb_id);
                    if ($letterbox_url) {
                        $src = [$letterbox_url, 1200, 630, false];
                    } else {
                        $src = wp_get_attachment_image_src($thumb_id, 'full');
                    }
                }
                if (!empty($src[0])) {
                    $url    = (string) $src[0];
                    $width  = isset($src[1]) ? (int) $src[1] : 0;
                    $height = isset($src[2]) ? (int) $src[2] : 0;
                    $type   = get_post_mime_type($thumb_id) ?: '';
                    $alt    = trim((string) get_post_meta($thumb_id, '_wp_attachment_image_alt', true));
                }
            }
        }

        if (!$url) {
            $url = trim((string) $this->opts['default_og_image']);
            if ($url) {
                $ck     = 'cs_seo_attid_' . md5($url);
                $cached = get_transient($ck);
                if ($cached !== false) {
                    $att_id = (int) $cached;
                } else {
                    $att_id = attachment_url_to_postid($url);
                    set_transient($ck, (int) $att_id, 12 * HOUR_IN_SECONDS);
                }
                if ($att_id) {
                    $meta = wp_get_attachment_metadata($att_id);
                    if (!empty($meta['width']))  $width  = (int) $meta['width'];
                    if (!empty($meta['height'])) $height = (int) $meta['height'];
                    $type = get_post_mime_type($att_id) ?: '';
                    $alt  = trim((string) get_post_meta($att_id, '_wp_attachment_image_alt', true));
                }
            }
        }

        return compact('url', 'width', 'height', 'type', 'alt');
    }

    // =========================================================================
    // OG letterbox generator
    // =========================================================================

    /**
     * Generate a 1200×630 letterboxed JPEG for a featured image that is too narrow
     * to be hard-cropped to 1200×630 (e.g. portrait or square images).
     *
     * The source image is scaled to fit within 1200×630 while preserving its aspect
     * ratio, then centred on a white 1200×630 canvas. The result is saved alongside
     * the original upload and the URL is cached in post meta so the GD work only
     * runs once per attachment.
     *
     * @param int $attachment_id WordPress attachment ID.
     * @return string|false URL of the letterboxed image, or false on failure.
     */
    private function generate_og_letterbox(int $attachment_id): string|false {
        // Return cached result if already generated.
        $cached = get_post_meta($attachment_id, '_cs_seo_og_letterbox_url', true);
        if ($cached) return $cached;

        // GD is required.
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) return false;

        $mime = mime_content_type($file) ?: '';
        $src_img = match(true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => @imagecreatefromjpeg($file),
            str_contains($mime, 'png')                                 => @imagecreatefrompng($file),
            str_contains($mime, 'webp')                                => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file) : false,
            str_contains($mime, 'gif')                                 => @imagecreatefromgif($file),
            default                                                    => false,
        };

        if (!$src_img) return false;

        $src_w = imagesx($src_img);
        $src_h = imagesy($src_img);

        $canvas_w = 1200;
        $canvas_h = 630;

        // Scale source to fit inside the canvas, preserving aspect ratio.
        $scale  = min($canvas_w / $src_w, $canvas_h / $src_h);
        $dst_w  = (int) round($src_w * $scale);
        $dst_h  = (int) round($src_h * $scale);
        $dst_x  = (int) round(($canvas_w - $dst_w) / 2);
        $dst_y  = (int) round(($canvas_h - $dst_h) / 2);

        // Create white canvas.
        $canvas = imagecreatetruecolor($canvas_w, $canvas_h);
        if (!$canvas) { imagedestroy($src_img); return false; }
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        // Copy scaled source onto canvas.
        imagecopyresampled($canvas, $src_img, $dst_x, $dst_y, 0, 0, $dst_w, $dst_h, $src_w, $src_h);
        imagedestroy($src_img);

        // Save next to original file using a -og1200x630 suffix.
        $dir       = dirname($file);
        $base      = pathinfo($file, PATHINFO_FILENAME);
        $out_file  = $dir . '/' . $base . '-og1200x630.jpg';
        $saved     = imagejpeg($canvas, $out_file, 88);
        imagedestroy($canvas);

        if (!$saved) return false;

        // Build URL from file path.
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']);
        $base_url   = trailingslashit($upload_dir['baseurl']);
        if (str_starts_with($out_file, $base_dir)) {
            $relative = substr($out_file, strlen($base_dir));
            $url      = $base_url . $relative;
        } else {
            return false;
        }

        // Cache so we do not re-generate on every page load.
        update_post_meta($attachment_id, '_cs_seo_og_letterbox_url', $url);

        self::debug_log("CS SEO: Generated OG letterbox for attachment {$attachment_id}: {$url}");

        return $url;
    }

    // =========================================================================
    // OG / Twitter
    // =========================================================================

    /**
     * Renders the Open Graph and Twitter Card meta tags for the current page.
     *
     * @since 4.0.0
     * @return string HTML meta tags ready for output in <head>.
     */
    private function render_og_tags(): string {
        $title   = $this->page_title();
        $desc    = $this->meta_desc();
        $url     = $this->canonical_url() ?: home_url('/');
        $site    = (string) $this->opts['site_name'];
        $locale  = str_replace('-', '_', (string) $this->opts['site_lang']);
        $twitter = (string) $this->opts['twitter_handle'];
        $type    = is_singular('post') ? 'article' : 'website';
        $img     = $this->og_image_data();
        $out     = '';

        $og = [
            'og:locale'      => $locale,
            'og:type'        => $type,
            'og:title'       => $title,
            'og:description' => $desc,
            'og:url'         => $url,
            'og:site_name'   => $site,
        ];

        if ($type === 'article' && is_singular()) {
            $pid = (int) get_queried_object_id();
            $published = get_post_time('c', true, $pid);
            $modified  = get_post_modified_time('c', true, $pid);
            if ($published) $og['article:published_time'] = $published;
            if ($modified)  $og['article:modified_time']  = $modified;
            $og['article:author'] = (string) $this->opts['person_url'];
            $cats = get_the_category($pid);
            if (!empty($cats[0])) $og['article:section'] = $cats[0]->name;
        }

        $url_keys = ['og:url', 'article:author'];
        foreach ($og as $k => $v) {
            if ((string)$v === '') continue;
            $escaped = in_array($k, $url_keys, true) ? esc_url((string)$v) : esc_attr((string)$v);
            $out .= '<meta property="' . esc_attr($k) . '" content="' . $escaped . '">' . "\n";
        }

        if ($img['url']) {
            $out .= '<meta property="og:image" content="'        . esc_url($img['url'])            . '">' . "\n";
            // og:image:secure_url is required by WhatsApp's scraper for HTTPS pages to reliably show link preview thumbnails.
            if (str_starts_with($img['url'], 'https://')) {
                $out .= '<meta property="og:image:secure_url" content="' . esc_url($img['url']) . '">' . "\n";
            }
            if ($img['width'])  $out .= '<meta property="og:image:width" content="'  . esc_attr((string)$img['width'])  . '">' . "\n";
            if ($img['height']) $out .= '<meta property="og:image:height" content="' . esc_attr((string)$img['height']) . '">' . "\n";
            if ($img['type'])   $out .= '<meta property="og:image:type" content="'   . esc_attr($img['type'])           . '">' . "\n";
            if ($img['alt'])    $out .= '<meta property="og:image:alt" content="'    . esc_attr($img['alt'])            . '">' . "\n";
        }

        $out .= '<meta name="twitter:card" content="'        . esc_attr($img['url'] ? 'summary_large_image' : 'summary') . '">' . "\n";
        $out .= '<meta name="twitter:title" content="'       . esc_attr($title)      . '">' . "\n";
        if ($desc)       $out .= '<meta name="twitter:description" content="' . esc_attr($desc)      . '">' . "\n";
        if ($img['url']) $out .= '<meta name="twitter:image" content="'       . esc_url($img['url']) . '">' . "\n";
        if ($img['alt']) $out .= '<meta name="twitter:image:alt" content="'   . esc_attr($img['alt']) . '">' . "\n";
        if ($twitter)    $out .= '<meta name="twitter:site" content="'        . esc_attr($twitter)    . '">' . "\n";
        if ($twitter)    $out .= '<meta name="twitter:creator" content="'     . esc_attr($twitter)    . '">' . "\n";

        return $out;
    }

}
