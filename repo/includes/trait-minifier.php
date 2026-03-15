<?php
/**
 * HTML minifier — strips unnecessary whitespace from page output.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Minifier {
    /**
     * Starts output buffering to capture the full HTML page for minification.
     *
     * @since 4.10.0
     * @return void
     */
    public function minify_start(): void {
        ob_start([$this, 'minify_html_output']);
    }

    /**
     * Flushes the output buffer, triggering minification of the buffered HTML.
     *
     * @since 4.10.0
     * @return void
     */
    public function minify_end(): void {
        if (ob_get_level() > 0) ob_end_flush();
    }

    /**
     * Minifies the full HTML page by collapsing whitespace and minifying inline JS/CSS.
     *
     * @since 4.10.0
     * @param string $html The raw HTML page output.
     * @return string Minified HTML.
     */
    public function minify_html_output(string $html): string {
        if (trim($html) === '') return $html;
        if (stripos($html, '<html') === false) return $html;

        $placeholders = [];
        $index        = 0;

        // Protect <pre> blocks
        $html = preg_replace_callback('#<pre(\s[^>]*)?>.*?</pre>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // Protect <textarea> blocks
        $html = preg_replace_callback('#<textarea(\s[^>]*)?>.*?</textarea>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $m[0];
            return $key;
        }, $html);

        // Extract, minify, protect <script> blocks
        $html = preg_replace_callback('#<script(\s[^>]*)?>.*?</script>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $this->minify_js_block($m[0]);
            return $key;
        }, $html);

        // Extract, minify, protect <style> blocks
        $html = preg_replace_callback('#<style(\s[^>]*)?>.*?</style>#is', function($m) use (&$placeholders, &$index) {
            $key = '<!--MINIFY_PH_' . $index++ . '-->';
            $placeholders[$key] = $this->minify_css_block($m[0]);
            return $key;
        }, $html);

        // Remove HTML comments (keep IE conditionals and placeholders)
        $html = preg_replace('#<!--(?!\[if|\s*MINIFY_PH).*?-->#is', '', $html);

        // Collapse whitespace between tags
        $html = preg_replace('/>\s+</s', '> <', $html);

        // Remove leading/trailing whitespace per line
        $html = preg_replace('/^[ \t]+|[ \t]+$/m', '', $html);

        // Collapse multiple blank lines
        $html = preg_replace('/\n{2,}/', "\n", $html);

        // Restore protected blocks
        $html = str_replace(array_keys($placeholders), array_values($placeholders), $html);

        return trim($html);
    }

    private function minify_js_block(string $block): string {
        if (!preg_match('#(<script[^>]*>)(.*?)(</script>)#is', $block, $m)) return $block;
        $open    = $m[1];
        $content = $m[2];
        $close   = $m[3];
        if (trim($content) === '') return $block;
        // Skip JSON-LD structured data
        if (stripos($open, 'application/ld+json') !== false) return $block;
        return $open . $this->minify_js_content($content) . $close;
    }

    private function minify_css_block(string $block): string {
        if (!preg_match('#(<style[^>]*>)(.*?)(</style>)#is', $block, $m)) return $block;
        $open    = $m[1];
        $content = $m[2];
        $close   = $m[3];
        if (trim($content) === '') return $block;
        return $open . $this->minify_css_content($content) . $close;
    }

    private function minify_css_content(string $css): string {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = str_replace(';}', '}', $css);
        return trim($css);
    }

    private function minify_js_content(string $js): string {
        $js = preg_replace('#(?<!:)//(?!["\']).*$#m', '', $js);
        $js = preg_replace('#/\*.*?\*/#s', '', $js);
        $js = preg_replace('/[ \t]+/', ' ', $js);
        $js = preg_replace('/^\s*$/m', '', $js);
        $js = preg_replace('/\n{2,}/', "\n", $js);
        return trim($js);
    }

}
