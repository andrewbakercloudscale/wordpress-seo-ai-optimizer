<?php
/**
 * AI Summary Box — injects What/Why/Takeaway summary block into post content.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Summary_Box {
    /**
     * Prepends the AI-generated summary box to singular post content.
     *
     * @since 4.10.46
     * @param string $content The post content.
     * @return string Content with summary box prepended, or original if conditions are not met.
     */
    public function prepend_summary_box(string $content): string {
        if (!is_singular('post') || is_admin() || !(int)($this->opts['show_summary_box'] ?? 1)) {
            return $content;
        }
        // Only run on the main query to avoid duplicating in widgets or shortcodes.
        if (!in_the_loop() || !is_main_query()) return $content;

        $pid      = (int) get_the_ID();
        if ((int) get_post_meta($pid, self::META_HIDE_SUMMARY, true)) return $content;
        $sum_what = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        $sum_why  = trim((string) get_post_meta($pid, self::META_SUM_WHY,  true));
        $sum_key  = trim((string) get_post_meta($pid, self::META_SUM_KEY,  true));

        if (!$sum_what || !$sum_why || !$sum_key) return $content;

        $row_sep = 'border-bottom:1px solid rgba(79,70,229,0.15);';
        $lbl     = 'padding:14px 20px 14px 24px;vertical-align:top;width:148px;font-weight:600;font-size:12.5px;color:#4f46e5;white-space:nowrap;letter-spacing:.01em;border:none!important;border-bottom:inherit;border-right:none!important;';
        $val     = 'padding:14px 24px 14px 0;color:#374151;font-size:14px;line-height:1.7;border:none!important;border-bottom:inherit;border-right:none!important;';

        $box  = '<div class="cs-seo-summary-box" style="';
        $box .= 'background:#ffffff;border-radius:14px;overflow:hidden;';
        $box .= 'margin:0 0 36px;';
        $box .= 'box-shadow:0 2px 8px rgba(0,0,0,0.06),0 8px 32px rgba(79,70,229,0.12),0 1px 2px rgba(0,0,0,0.04);';
        $box .= '">';
        $box .= '<div style="background:linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%);padding:12px 24px;display:flex;align-items:center;gap:9px;">';
        $box .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';
        $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.95);">' . esc_html__( 'CloudScale SEO — AI Article Summary', 'cloudscale-seo-ai-optimizer' ) . '</span>';
        $box .= '</div>';
        $box .= '<table style="width:100%;border-collapse:collapse;background:#ffffff;">';
        $box .= '<tr style="' . $row_sep . '"><td style="' . $lbl . '">' . esc_html__( 'What it is', 'cloudscale-seo-ai-optimizer' ) . '</td><td style="' . $val . '">' . esc_html($sum_what) . '</td></tr>';
        $box .= '<tr style="' . $row_sep . '"><td style="' . $lbl . '">' . esc_html__( 'Why it matters', 'cloudscale-seo-ai-optimizer' ) . '</td><td style="' . $val . '">' . esc_html($sum_why) . '</td></tr>';
        $last_lbl = str_replace('border-bottom:inherit', 'border-bottom:none!important', $lbl);
        $last_val = str_replace('border-bottom:inherit', 'border-bottom:none!important', $val);
        $box .= '<tr><td style="' . $last_lbl . '">' . esc_html__( 'Key takeaway', 'cloudscale-seo-ai-optimizer' ) . '</td><td style="' . $last_val . '">' . esc_html($sum_key) . '</td></tr>';
        $box .= '</table>';
        $box .= '</div><!-- /cs-seo-summary-box -->';

        return $box . $content;
    }
}
