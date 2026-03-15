<?php
/**
 * Gutenberg / block editor integration — SEO sidebar panel and post meta.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Gutenberg {
    // =========================================================================
    // Gutenberg sidebar panel
    // =========================================================================

    /**
     * Enqueues the Gutenberg sidebar panel script and passes required data via wp_localize_script.
     *
     * @since 4.10.44
     * @return void
     */
    public function enqueue_block_editor_assets(): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['post', 'page'], true)) return;

        $post_id  = (int) (isset($_GET['post']) ? $_GET['post'] : 0); // phpcs:ignore
        $has_key  = !empty($this->ai_opts['anthropic_key']) || !empty($this->ai_opts['gemini_key']);
        $nonce    = wp_create_nonce('cs_seo_nonce');
        $settings_url = esc_url(admin_url('options-general.php?page=cs-seo-optimizer#ai'));

        // Existing meta values passed to JS so the panel can pre-populate.
        $title    = $post_id ? (string) get_post_meta($post_id, self::META_TITLE,    true) : '';
        $desc     = $post_id ? (string) get_post_meta($post_id, self::META_DESC,     true) : '';
        $ogimg    = $post_id ? (string) get_post_meta($post_id, self::META_OGIMG,    true) : '';
        $sum_what    = $post_id ? (string) get_post_meta($post_id, self::META_SUM_WHAT,     true) : '';
        $sum_why     = $post_id ? (string) get_post_meta($post_id, self::META_SUM_WHY,      true) : '';
        $sum_key     = $post_id ? (string) get_post_meta($post_id, self::META_SUM_KEY,      true) : '';
        $hide_summary = $post_id ? (int)   get_post_meta($post_id, self::META_HIDE_SUMMARY, true) : 0;

        wp_register_script('cs-seo-block-panel', false, [
            'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'jquery'
        ], self::VERSION, true);
        wp_enqueue_script('cs-seo-block-panel');

        wp_localize_script('cs-seo-block-panel', 'csSeoPanel', [
            'postId'      => $post_id,
            'nonce'       => $nonce,
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'hasKey'      => $has_key,
            'settingsUrl' => $settings_url,
            'metaKeys'    => [
                'title'       => self::META_TITLE,
                'desc'        => self::META_DESC,
                'ogimg'       => self::META_OGIMG,
                'sumWhat'     => self::META_SUM_WHAT,
                'sumWhy'      => self::META_SUM_WHY,
                'sumKey'      => self::META_SUM_KEY,
                'hideSummary' => self::META_HIDE_SUMMARY,
            ],
            'initial'     => [
                'title'       => $title,
                'desc'        => $desc,
                'ogimg'       => $ogimg,
                'sumWhat'     => $sum_what,
                'sumWhy'      => $sum_why,
                'sumKey'      => $sum_key,
                'hideSummary' => $hide_summary,
            ],
        ]);

        wp_add_inline_script('cs-seo-block-panel', $this->get_block_panel_js());
    }

    private function get_block_panel_js(): string {
        return '(function() {
    var cfg     = window.csSeoPanel || {};
    var el      = wp.element.createElement;
    var Panel   = wp.editPost.PluginDocumentSettingPanel;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var Button = wp.components.Button;
    var Notice = wp.components.Notice;
    var Spinner = wp.components.Spinner;

    function CsSeoPanel() {
        var meta = useSelect(function(select) {
            return select(\'core/editor\').getEditedPostAttribute(\'meta\') || {};
        });
        var editPost = useDispatch(\'core/editor\').editPost;

        var keys    = cfg.metaKeys || {};
        var initial = cfg.initial  || {};

        var title   = meta[keys.title]   !== undefined ? meta[keys.title]   : (initial.title   || \'\');
        var desc    = meta[keys.desc]    !== undefined ? meta[keys.desc]    : (initial.desc    || \'\');
        var ogimg   = meta[keys.ogimg]   !== undefined ? meta[keys.ogimg]   : (initial.ogimg   || \'\');
        var sumWhat     = meta[keys.sumWhat]     !== undefined ? meta[keys.sumWhat]     : (initial.sumWhat    || \'\');
        var sumWhy      = meta[keys.sumWhy]      !== undefined ? meta[keys.sumWhy]      : (initial.sumWhy     || \'\');
        var sumKey      = meta[keys.sumKey]      !== undefined ? meta[keys.sumKey]      : (initial.sumKey     || \'\');
        var hideSummary = meta[keys.hideSummary] !== undefined ? meta[keys.hideSummary] : (initial.hideSummary || 0);

        var setMeta = function(key, val) {
            var patch = {};
            patch[key] = val;
            editPost({ meta: patch });
        };

        var titleLen   = title.length;
        var titleColor = titleLen >= 50 && titleLen <= 60 ? \'#46b450\' : (titleLen > 0 ? \'#dc3232\' : \'#888\');
        var titleHint  = titleLen > 0 ? titleLen + \' chars (ideal 50–60)\' : \'No title set\';

        var descLen  = desc.length;
        var descColor = descLen >= 140 && descLen <= 160 ? \'#46b450\' : (descLen > 0 ? \'#dc3232\' : \'#888\');
        var descHint  = descLen > 0 ? descLen + \' chars (ideal 140–160)\' : \'No description set\';

        var genStatus = useState(\'\');
        var genLoading = useState(false);
        var sumStatus = useState(\'\');
        var sumLoading = useState(false);

        function doGenDesc() {
            genLoading[1](true);
            genStatus[1](\'⟳ Generating...\');
            fetch(cfg.ajaxUrl, {
                method: \'POST\',
                headers: {\'Content-Type\': \'application/x-www-form-urlencoded\'},
                body: new URLSearchParams({
                    action: \'cs_seo_ai_generate_one\',
                    post_id: cfg.postId,
                    nonce: cfg.nonce
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var patch = {};
                    patch[keys.desc] = data.data.description;
                    editPost({ meta: patch });
                    genStatus[1](\'✓ Done — save post to keep\');
                } else {
                    genStatus[1](\'✗ \' + (data.data || \'Error\'));
                }
            })
            .catch(function(e) { genStatus[1](\'✗ \' + e.message); })
            .finally(function() { genLoading[1](false); });
        }

        function doGenSummary(force) {
            sumLoading[1](true);
            sumStatus[1](\'⟳ Generating...\');
            fetch(cfg.ajaxUrl, {
                method: \'POST\',
                headers: {\'Content-Type\': \'application/x-www-form-urlencoded\'},
                body: new URLSearchParams({
                    action: \'cs_seo_summary_generate_one\',
                    post_id: cfg.postId,
                    force: force ? 1 : 0,
                    nonce: cfg.nonce
                })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.data.skipped) {
                        sumStatus[1](\'✓ Already set — use Regenerate to overwrite\');
                    } else {
                        var patch = {};
                        patch[keys.sumWhat] = data.data.what;
                        patch[keys.sumWhy]  = data.data.why;
                        patch[keys.sumKey]  = data.data.takeaway;
                        editPost({ meta: patch });
                        sumStatus[1](\'✓ Done — save post to keep\');
                    }
                } else {
                    sumStatus[1](\'✗ \' + (data.data || \'Error\'));
                }
            })
            .catch(function(e) { sumStatus[1](\'✗ \' + e.message); })
            .finally(function() { sumLoading[1](false); });
        }

        return el(Panel,
            { name: \'cs-seo-panel\', title: \'CloudScale Meta Boxes\', icon: el(\'span\', { style: { fontSize: \'14px\' } }, \'🥷\') },

            // SEO Title
            el(\'div\', { style: { marginBottom: \'12px\' } },
                el(\'p\', { style: { margin: \'0 0 4px\', fontWeight: \'600\', fontSize: \'12px\' } }, \'Custom SEO title\'),
                el(\'p\', { style: { margin: \'0 0 4px\', fontSize: \'11px\', color: \'#888\' } }, \'Leave blank to auto-generate\'),
                el(\'input\', {
                    className: \'widefat\',
                    style: { width: \'100%\', fontSize: \'12px\' },
                    value: title,
                    onChange: function(e) { setMeta(keys.title, e.target.value); }
                }),
                el(\'span\', { style: { fontSize: \'11px\', color: titleColor } }, titleHint)
            ),

            // Meta Description
            el(\'div\', { style: { marginBottom: \'12px\' } },
                el(\'p\', { style: { margin: \'0 0 4px\', fontWeight: \'600\', fontSize: \'12px\' } }, \'Meta description\'),
                el(\'p\', { style: { margin: \'0 0 4px\', fontSize: \'11px\', color: \'#888\' } }, \'Leave blank to use excerpt\'),
                el(\'textarea\', {
                    className: \'widefat\',
                    rows: 3,
                    style: { width: \'100%\', fontSize: \'12px\', resize: \'vertical\' },
                    value: desc,
                    onChange: function(e) { setMeta(keys.desc, e.target.value); }
                }),
                el(\'span\', { style: { fontSize: \'11px\', color: descColor } }, descHint)
            ),

            // Generate desc button
            cfg.hasKey
                ? el(\'div\', { style: { marginBottom: \'16px\', display: \'flex\', alignItems: \'center\', gap: \'8px\', flexWrap: \'wrap\' } },
                    el(Button, { variant: \'secondary\', isSmall: true, isBusy: genLoading[0], disabled: genLoading[0], onClick: doGenDesc }, \'✦ Generate with AI\'),
                    genStatus[0] ? el(\'span\', { style: { fontSize: \'11px\', color: genStatus[0].startsWith(\'✓\') ? \'#46b450\' : \'#dc3232\' } }, genStatus[0]) : null
                  )
                : el(\'p\', { style: { fontSize: \'11px\', color: \'#888\', marginBottom: \'16px\' } },
                    \'Add an API key in \',
                    el(\'a\', { href: cfg.settingsUrl }, \'SEO Settings\'),
                    \' to enable AI generation.\'
                  ),

            // Divider
            el(\'hr\', { style: { margin: \'4px 0 12px\', borderTop: \'1px solid #ddd\', border: \'none\', borderTopStyle: \'solid\', borderTopWidth: \'1px\', borderTopColor: \'#ddd\' } }),

            // OG Image
            el(\'div\', { style: { marginBottom: \'16px\' } },
                el(\'p\', { style: { margin: \'0 0 4px\', fontWeight: \'600\', fontSize: \'12px\' } }, \'OG image URL\'),
                el(\'p\', { style: { margin: \'0 0 4px\', fontSize: \'11px\', color: \'#888\' } }, \'Leave blank to use featured image\'),
                el(\'input\', {
                    className: \'widefat\',
                    style: { width: \'100%\', fontSize: \'12px\' },
                    value: ogimg,
                    onChange: function(e) { setMeta(keys.ogimg, e.target.value); }
                })
            ),

            // Divider
            el(\'hr\', { style: { margin: \'4px 0 12px\', border: \'none\', borderTopStyle: \'solid\', borderTopWidth: \'1px\', borderTopColor: \'#ddd\' } }),

            // AI Summary
            el(\'div\', { style: { margin: \'0 0 8px\', display: \'flex\', alignItems: \'center\', justifyContent: \'space-between\', flexWrap: \'wrap\', gap: \'6px\' } },
                el(\'p\', { style: { margin: \'0\', fontWeight: \'600\', fontSize: \'12px\' } }, \'AI Summary Box\'),
                el(\'label\', { style: { display: \'flex\', alignItems: \'center\', gap: \'4px\', fontSize: \'11px\', cursor: \'pointer\' } },
                    el(\'input\', {
                        type: \'checkbox\',
                        checked: !!hideSummary,
                        onChange: function(e) { setMeta(keys.hideSummary, e.target.checked ? 1 : 0); }
                    }),
                    el(\'span\', { style: { color: \'#c3372b\', fontWeight: \'600\' } }, \'Hide on this post\')
                )
            ),
            el(\'p\', { style: { margin: \'0 0 8px\', fontSize: \'11px\', color: \'#888\' } }, \'Shown at top of post for readers\'),

            el(\'div\', { style: { marginBottom: \'8px\' } },
                el(\'label\', { style: { display: \'block\', fontSize: \'11px\', fontWeight: \'600\', color: \'#555\', marginBottom: \'3px\' } }, \'What it is\'),
                el(\'textarea\', {
                    className: \'widefat\',
                    rows: 2,
                    style: { width: \'100%\', fontSize: \'12px\', resize: \'vertical\' },
                    value: sumWhat,
                    onChange: function(e) { setMeta(keys.sumWhat, e.target.value); }
                })
            ),
            el(\'div\', { style: { marginBottom: \'8px\' } },
                el(\'label\', { style: { display: \'block\', fontSize: \'11px\', fontWeight: \'600\', color: \'#555\', marginBottom: \'3px\' } }, \'Why it matters\'),
                el(\'textarea\', {
                    className: \'widefat\',
                    rows: 2,
                    style: { width: \'100%\', fontSize: \'12px\', resize: \'vertical\' },
                    value: sumWhy,
                    onChange: function(e) { setMeta(keys.sumWhy, e.target.value); }
                })
            ),
            el(\'div\', { style: { marginBottom: \'8px\' } },
                el(\'label\', { style: { display: \'block\', fontSize: \'11px\', fontWeight: \'600\', color: \'#555\', marginBottom: \'3px\' } }, \'Key takeaway\'),
                el(\'textarea\', {
                    className: \'widefat\',
                    rows: 2,
                    style: { width: \'100%\', fontSize: \'12px\', resize: \'vertical\' },
                    value: sumKey,
                    onChange: function(e) { setMeta(keys.sumKey, e.target.value); }
                })
            ),

            // Generate summary buttons
            cfg.hasKey
                ? el(\'div\', { style: { display: \'flex\', alignItems: \'center\', gap: \'6px\', flexWrap: \'wrap\' } },
                    el(Button, { variant: \'secondary\', isSmall: true, isBusy: sumLoading[0], disabled: sumLoading[0], onClick: function() { doGenSummary(false); } }, \'✦ Generate\'),
                    el(Button, { variant: \'secondary\', isSmall: true, isBusy: sumLoading[0], disabled: sumLoading[0], onClick: function() { doGenSummary(true); } }, \'↺ Regenerate\'),
                    sumStatus[0] ? el(\'span\', { style: { fontSize: \'11px\', color: sumStatus[0].startsWith(\'✓\') ? \'#46b450\' : \'#dc3232\', width: \'100%\' } }, sumStatus[0]) : null
                  )
                : el(\'p\', { style: { fontSize: \'11px\', color: \'#888\', margin: \'0\' } },
                    \'Add an API key in \',
                    el(\'a\', { href: cfg.settingsUrl }, \'SEO Settings\'),
                    \' to enable AI generation.\'
                  )
        );
    }

    wp.domReady(function() {
        wp.plugins.registerPlugin(\'cs-seo-block-panel\', {
            render: CsSeoPanel,
            icon: \'admin-generic\'
        });
    });
})();
';
    }
}
