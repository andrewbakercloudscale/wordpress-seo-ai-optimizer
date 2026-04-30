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
        if (!in_the_loop() || !is_main_query()) return $content;

        $pid      = (int) get_the_ID();
        if ((int) get_post_meta($pid, self::META_HIDE_SUMMARY, true)) return $content;
        $sum_what = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        $sum_why  = trim((string) get_post_meta($pid, self::META_SUM_WHY,  true));
        $sum_key  = trim((string) get_post_meta($pid, self::META_SUM_KEY,  true));

        if (!$sum_what || !$sum_why || !$sum_key) return $content;

        // ── Same palette as render_rc_block ────────────────────────────────────
        $style = (string)($this->opts['rc_style'] ?? '1');
        $pal   = [
            '1'  => ['fmt' => 'gradient', 'accent' => '#4f46e5',
                     'grad' => 'linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%)'],
            '2'  => ['fmt' => 'dark',     'accent' => '#fbbf24', 'dark_bg' => '#1e1b4b'],
            '3'  => ['fmt' => 'minimal',  'accent' => '#2563eb'],
            '4'  => ['fmt' => 'cards',    'accent' => '#059669'],
            '5'  => ['fmt' => 'stripe',   'accent' => '#64748b'],
            '6'  => ['fmt' => 'gradient', 'accent' => '#dc2626',
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

        $title = esc_html__( 'CloudScale AI SEO - Article Summary', 'cloudscale-seo-ai-optimizer' );
        $icon  = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';

        // ── Body — list format, colors vary for dark vs light styles ───────────
        $is_dark   = ( $fmt === 'dark' );
        $lbl_col   = $accent; // already esc_attr()'d above
        $val_col   = esc_attr( $is_dark ? '#e2e8f0' : '#374151' );
        $sep_col   = esc_attr( $is_dark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.08)' );
        $body_bg   = $is_dark ? $dk : esc_attr( $fmt === 'stripe' ? '#fafafa' : '#ffffff' );

        $rows = [
            [ esc_html__( 'What it is',     'cloudscale-seo-ai-optimizer' ), esc_html( $sum_what ) ],
            [ esc_html__( 'Why it matters', 'cloudscale-seo-ai-optimizer' ), esc_html( $sum_why  ) ],
            [ esc_html__( 'Key takeaway',   'cloudscale-seo-ai-optimizer' ), esc_html( $sum_key  ) ],
        ];
        $body = '<ul style="margin:0;padding:16px 24px;list-style:none;display:flex;flex-direction:column;gap:0;background:' . $body_bg . ';">';
        foreach ( $rows as $i => [ $label, $text ] ) {
            $sep   = ( $i < 2 ) ? 'border-bottom:1px solid ' . $sep_col . ';' : '';
            $body .= '<li style="display:flex;align-items:flex-start;gap:10px;padding:10px 0;' . $sep . '">';
            $body .= '<span style="color:' . $lbl_col . ';font-size:12px;font-weight:700;min-width:18px;flex-shrink:0;padding-top:2px;">' . ( $i + 1 ) . '.</span>';
            $body .= '<div><div style="font-size:10px;font-weight:700;color:' . $lbl_col . ';text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px;">' . $label . '</div>';
            $body .= '<div style="font-size:14px;color:' . $val_col . ';line-height:1.6;">' . $text . '</div></div>';
            $body .= '</li>';
        }
        $body .= '</ul>';

        // ── Container + header per format ────────────────────────────────────────
        switch ($fmt) {
            case 'dark':
                $dicon = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="' . $accent . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>';
                $box  = '<div class="cs-seo-summary-box" style="background:' . $dk . ';border-radius:14px;overflow:hidden;margin:0 0 36px;box-shadow:0 2px 8px rgba(0,0,0,0.3);">';
                $box .= '<div style="background:rgba(0,0,0,0.25);padding:12px 24px;display:flex;align-items:center;gap:9px;">';
                $box .= $dicon;
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            case 'minimal':
                $box  = '<div class="cs-seo-summary-box" style="background:#ffffff;border-top:3px solid ' . $accent . ';margin:0 0 36px;">';
                $box .= '<div style="padding:12px 24px 8px;">';
                $box .= '<span style="font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:' . $accent . ';">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            case 'cards':
                $box  = '<div class="cs-seo-summary-box" style="background:#ffffff;border:1px solid #e5e7eb;border-left:4px solid ' . $accent . ';border-radius:6px;overflow:hidden;margin:0 0 36px;">';
                $box .= '<div style="background:' . $accent . ';padding:10px 16px;display:flex;align-items:center;gap:9px;">';
                $box .= $icon;
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            case 'stripe':
                $box  = '<div class="cs-seo-summary-box" style="background:#fafafa;border-left:4px solid ' . $accent . ';margin:0 0 36px;">';
                $box .= '<div style="padding:12px 20px 8px;">';
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            case 'bordered':
                $box  = '<div class="cs-seo-summary-box" style="background:#ffffff;border:1.5px solid ' . $accent . ';border-radius:12px;overflow:hidden;margin:0 0 36px;">';
                $box .= '<div style="padding:12px 24px 8px;">';
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $accent . ';">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            case 'pill':
                $box  = '<div class="cs-seo-summary-box" style="background:#ffffff;border-radius:14px;overflow:hidden;margin:0 0 36px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">';
                $box .= '<div style="padding:12px 24px 8px;">';
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
                break;

            default: // gradient (styles 1, 6, 7, 9, 11, 12, 13)
                $box  = '<div class="cs-seo-summary-box" style="background:#ffffff;border-radius:14px;overflow:hidden;margin:0 0 36px;box-shadow:0 2px 8px rgba(0,0,0,0.06),0 8px 32px rgba(0,0,0,0.08),0 1px 2px rgba(0,0,0,0.04);">';
                $box .= '<div style="background:' . $grad . ';padding:12px 24px;display:flex;align-items:center;gap:9px;">';
                $box .= $icon;
                $box .= '<span style="font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,0.95);">' . $title . '</span>';
                $box .= '</div>' . $body . '</div><!-- /cs-seo-summary-box -->';
        }

        return $box . $content;
    }

    /**
     * Prepends the AEO direct-answer paragraph as a naked <p> before the summary box.
     * Runs at the_content priority 8 — before prepend_summary_box (priority 10).
     * Google's featured snippet extractor reads this as the page's primary prose answer.
     *
     * @since 4.20.87
     * @param string $content The post content.
     * @return string Content with AEO answer prepended, or original if not applicable.
     */
    public function prepend_aeo_answer(string $content): string {
        if (!is_singular('post') || is_admin()) return $content;
        if (!in_the_loop() || !is_main_query()) return $content;
        $answer = trim((string) get_post_meta((int) get_the_ID(), self::META_AEO_ANSWER, true));
        if (!$answer) return $content;
        return '<p>' . esc_html($answer) . '</p>' . "\n" . $content;
    }
}
