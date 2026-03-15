<?php
/**
 * Post/page metabox — SEO title, meta description, OG image, and AI summary fields.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Metabox {
    /**
     * Registers the CloudScale Meta Boxes metabox for posts and pages.
     *
     * @since 4.0.0
     * @return void
     */
    public function add_metabox(): void {
        foreach (['post', 'page'] as $pt) {
            add_meta_box('cs_seo_adv', 'CloudScale Meta Boxes', [$this, 'render_metabox'], $pt, 'normal', 'high');
        }
    }

    /**
     * Renders the SEO metabox fields including title, description, OG image, and AI summary.
     *
     * @since 4.0.0
     * @param WP_Post $post The post being edited.
     * @return void
     */
    public function render_metabox(WP_Post $post): void {
        wp_nonce_field('cs_seo_save', 'cs_seo_nonce');
        $noindex = (int) get_post_meta($post->ID, self::META_NOINDEX, true);
        $title   = (string) get_post_meta($post->ID, self::META_TITLE,    true);
        $desc    = (string) get_post_meta($post->ID, self::META_DESC,     true);
        $ogimg   = (string) get_post_meta($post->ID, self::META_OGIMG,    true);
        $sum_what = (string) get_post_meta($post->ID, self::META_SUM_WHAT, true);
        $sum_why  = (string) get_post_meta($post->ID, self::META_SUM_WHY,  true);
        $sum_key  = (string) get_post_meta($post->ID, self::META_SUM_KEY,  true);
        $has_key = !empty($this->ai_opts['anthropic_key']) || !empty($this->ai_opts['gemini_key']);
        ?>
        <p style="margin:0 0 12px;padding:8px 10px;background:<?php echo esc_attr($noindex ? '#fff3cd' : '#f6f7f7'); ?>;border:1px solid <?php echo esc_attr($noindex ? '#ffc107' : '#ddd'); ?>;border-radius:4px;display:flex;align-items:center;gap:8px">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600;margin:0">
                <input type="checkbox" name="cs_seo_noindex" value="1" <?php checked($noindex, 1); ?>>
                <span style="color:<?php echo esc_attr($noindex ? '#856404' : '#3c434a'); ?>">
                    <?php echo $noindex ? esc_html__( '⛔ Noindex — hidden from search engines', 'cloudscale-seo-ai-optimizer' ) : esc_html__( 'Noindex this post/page', 'cloudscale-seo-ai-optimizer' ); ?>
                </span>
            </label>
            <?php if (!$noindex): ?>
            <span style="font-size:11px;color:#888;font-weight:400"><?php esc_html_e( '— tick to exclude from search engines', 'cloudscale-seo-ai-optimizer' ); ?></span>
            <?php endif; ?>
        </p>
        <p><strong><?php esc_html_e( 'Custom SEO title', 'cloudscale-seo-ai-optimizer' ); ?></strong> — <?php esc_html_e( 'leave blank to auto-generate', 'cloudscale-seo-ai-optimizer' ); ?><br>
            <input class="widefat" name="cs_seo_title" value="<?php echo esc_attr($title); ?>"></p>
        <p>
            <strong><?php esc_html_e( 'Meta description', 'cloudscale-seo-ai-optimizer' ); ?></strong> — <?php esc_html_e( 'leave blank to use excerpt / post content', 'cloudscale-seo-ai-optimizer' ); ?><br>
            <textarea class="widefat" rows="3" name="cs_seo_desc" id="cs_seo_desc_<?php echo (int) $post->ID; ?>"><?php echo esc_textarea($desc); ?></textarea>
            <span id="cs_seo_char_<?php echo (int) $post->ID; ?>" style="font-size:11px;color:#888;">
                <?php echo $desc ? esc_html( (string) mb_strlen($desc) ) . ' ' . esc_html__( 'chars', 'cloudscale-seo-ai-optimizer' ) : esc_html__( 'No description set', 'cloudscale-seo-ai-optimizer' ); ?>
            </span>
        </p>
        <?php if ($has_key): ?>
        <p>
            <button type="button" class="button" id="cs_seo_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoGenOne(<?php echo (int) $post->ID; ?>)">
                <?php esc_html_e( '✦ Generate with Claude', 'cloudscale-seo-ai-optimizer' ); ?>
            </button>
            <span id="cs_seo_gen_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <?php ob_start(); ?>
        function csSeoGenOne(postId) {
            const btn    = document.getElementById('cs_seo_gen_' + postId);
            const status = document.getElementById('cs_seo_gen_status_' + postId);
            const field  = document.getElementById('cs_seo_desc_' + postId);
            const chars  = document.getElementById('cs_seo_char_' + postId);
            btn.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_ai_generate_one',
                    post_id: postId,
                    nonce: csSeoMetabox.nonce
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    field.value = data.data.description;
                    chars.textContent = data.data.chars + ' chars';
                    chars.style.color = data.data.chars >= 140 && data.data.chars <= 160 ? '#46b450' : '#dc3232';
                    status.textContent = '✓ Done — save post to keep';
                    status.style.color = '#46b450';
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; });
        }
        <?php wp_add_inline_script('cs-seo-metabox-js', ob_get_clean()); ?>
        <?php else: ?>
        <p style="color:#888;font-size:12px;"><em><?php
            /* translators: %s: link to the AI Meta Writer settings section */
            printf( esc_html__( 'Add an Anthropic API key in %s to enable per-post generation.', 'cloudscale-seo-ai-optimizer' ), '<a href="' . esc_url( admin_url( 'options-general.php?page=cs-seo-optimizer#ai' ) ) . '">' . esc_html__( 'SEO Settings → AI Meta Writer', 'cloudscale-seo-ai-optimizer' ) . '</a>' );
        ?></em></p>
        <?php endif; ?>
        <?php
        $thumb_id  = get_post_thumbnail_id($post->ID);
        $thumb_src = $thumb_id ? wp_get_attachment_image_src($thumb_id, 'thumbnail') : false;
        $using_custom = !empty($ogimg);
        ?>
        <p>
            <strong><?php esc_html_e( 'OG image URL', 'cloudscale-seo-ai-optimizer' ); ?></strong> — <?php esc_html_e( 'leave blank to use featured image', 'cloudscale-seo-ai-optimizer' ); ?><br>
            <input class="widefat" name="cs_seo_ogimg" id="cs_seo_ogimg_<?php echo (int) $post->ID; ?>" value="<?php echo esc_attr($ogimg); ?>">
            <?php if ($using_custom): ?>
            <button type="button" class="button" style="margin-top:4px" onclick="
                document.getElementById('cs_seo_ogimg_<?php echo (int) $post->ID; ?>').value = '';
                this.parentNode.querySelector('.cs-og-status').textContent = '⚠ Cleared — save post to apply';
                this.parentNode.querySelector('.cs-og-status').style.color = '#e67e00';
                this.style.display = 'none';
            "><?php esc_html_e( '✕ Clear (use featured image)', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#c3372b;margin-top:3px"><?php esc_html_e( '⚠ Custom URL set — featured image changes will not appear until this is cleared', 'cloudscale-seo-ai-optimizer' ); ?></span>
            <?php elseif ($thumb_src): ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#1a7a34;margin-top:3px"><?php esc_html_e( '✓ Using featured image', 'cloudscale-seo-ai-optimizer' ); ?></span>
            <?php else: ?>
            <span class="cs-og-status" style="display:block;font-size:11px;color:#888;margin-top:3px"><?php esc_html_e( 'No featured image set — using site default OG image', 'cloudscale-seo-ai-optimizer' ); ?></span>
            <?php endif; ?>
        </p>

        <hr style="margin:16px 0;border:none;border-top:1px solid #ddd">
        <?php $hide_summary = (int) get_post_meta($post->ID, self::META_HIDE_SUMMARY, true); ?>
        <p style="margin:0 0 8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:6px">
            <span><strong><?php esc_html_e( 'AI Summary Box', 'cloudscale-seo-ai-optimizer' ); ?></strong> <span style="font-size:11px;font-weight:400;color:#888"><?php esc_html_e( '— shown at the top of the post for readers and AI search engines', 'cloudscale-seo-ai-optimizer' ); ?></span></span>
            <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;">
                <input type="checkbox" name="cs_seo_hide_summary" value="1" <?php checked($hide_summary, 1); ?>>
                <span style="color:#c3372b;font-weight:600"><?php esc_html_e( 'Hide on this post', 'cloudscale-seo-ai-optimizer' ); ?></span>
            </label>
        </p>

        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555"><?php esc_html_e( 'What it is', 'cloudscale-seo-ai-optimizer' ); ?></label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_what" id="cs_seo_sum_what_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_what); ?></textarea>
        </p>
        <p style="margin:0 0 6px">
            <label style="font-size:12px;font-weight:600;color:#555"><?php esc_html_e( 'Why it matters', 'cloudscale-seo-ai-optimizer' ); ?></label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_why" id="cs_seo_sum_why_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_why); ?></textarea>
        </p>
        <p style="margin:0 0 10px">
            <label style="font-size:12px;font-weight:600;color:#555"><?php esc_html_e( 'Key takeaway', 'cloudscale-seo-ai-optimizer' ); ?></label><br>
            <textarea class="widefat" rows="2" name="cs_seo_sum_key" id="cs_seo_sum_key_<?php echo (int) $post->ID; ?>" style="font-size:13px"><?php echo esc_textarea($sum_key); ?></textarea>
        </p>

        <?php if ($has_key): ?>
        <p style="margin:0">
            <button type="button" class="button" id="cs_seo_sum_gen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>)">
                <?php esc_html_e( '✦ Generate Summary', 'cloudscale-seo-ai-optimizer' ); ?>
            </button>
            <button type="button" class="button" style="margin-left:6px" id="cs_seo_sum_regen_<?php echo (int) $post->ID; ?>"
                onclick="csSeoSumGenOne(<?php echo (int) $post->ID; ?>, true)">
                <?php esc_html_e( '↺ Regenerate', 'cloudscale-seo-ai-optimizer' ); ?>
            </button>
            <span id="cs_seo_sum_status_<?php echo (int) $post->ID; ?>" style="margin-left:8px;font-size:12px;color:#888;"></span>
        </p>
        <?php ob_start(); ?>
        function csSeoSumGenOne(postId, force) {
            const btn    = document.getElementById('cs_seo_sum_gen_' + postId);
            const regen  = document.getElementById('cs_seo_sum_regen_' + postId);
            const status = document.getElementById('cs_seo_sum_status_' + postId);
            const fWhat  = document.getElementById('cs_seo_sum_what_' + postId);
            const fWhy   = document.getElementById('cs_seo_sum_why_' + postId);
            const fKey   = document.getElementById('cs_seo_sum_key_' + postId);
            btn.disabled = true;
            regen.disabled = true;
            status.textContent = '⟳ Generating...';
            status.style.color = '#888';
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'cs_seo_summary_generate_one',
                    post_id: postId,
                    force: force ? 1 : 0,
                    nonce: csSeoMetabox.nonce
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (data.data.skipped) {
                        status.textContent = '✓ Already generated — use Regenerate to overwrite';
                        status.style.color = '#888';
                    } else {
                        fWhat.value = data.data.what;
                        fWhy.value  = data.data.why;
                        fKey.value  = data.data.takeaway;
                        status.textContent = '✓ Done — save post to keep';
                        status.style.color = '#46b450';
                    }
                } else {
                    status.textContent = '✗ ' + (data.data || 'Error');
                    status.style.color = '#dc3232';
                }
            })
            .catch(e => {
                status.textContent = '✗ ' + e.message;
                status.style.color = '#dc3232';
            })
            .finally(() => { btn.disabled = false; regen.disabled = false; });
        }
        <?php wp_add_inline_script('cs-seo-metabox-js', ob_get_clean()); ?>
        <?php endif; ?>

        <?php
    }

    /**
     * Saves SEO metabox fields when a post is saved.
     *
     * @since 4.0.0
     * @param int     $post_id The ID of the post being saved.
     * @param WP_Post $post    The post object.
     * @return void
     */
    public function save_metabox(int $post_id, WP_Post $post): void {
        if (!isset($_POST['cs_seo_nonce'])) return;
        if (!wp_verify_nonce( sanitize_key( wp_unslash( $_POST['cs_seo_nonce'] ) ), 'cs_seo_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $this->set_meta($post_id, self::META_TITLE,    sanitize_text_field( wp_unslash( (string) ($_POST['cs_seo_title'] ?? '') ) ));
        $this->set_meta($post_id, self::META_DESC,     sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_desc'] ?? '') ) ));
        $this->set_meta($post_id, self::META_OGIMG,    esc_url_raw( wp_unslash( (string) ($_POST['cs_seo_ogimg'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHAT, sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_what'] ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_WHY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_why']  ?? '') ) ));
        $this->set_meta($post_id, self::META_SUM_KEY,  sanitize_textarea_field( wp_unslash( (string) ($_POST['cs_seo_sum_key']  ?? '') ) ));
        $hide = isset($_POST['cs_seo_hide_summary']) ? 1 : 0;
        $hide ? update_post_meta($post_id, self::META_HIDE_SUMMARY, 1) : delete_post_meta($post_id, self::META_HIDE_SUMMARY);
        $noindex = isset($_POST['cs_seo_noindex']) ? 1 : 0;
        $noindex ? update_post_meta($post_id, self::META_NOINDEX, 1) : delete_post_meta($post_id, self::META_NOINDEX);
    }

    /**
     * Updates or deletes a post meta field depending on whether the value is empty.
     *
     * @since 4.0.0
     * @param int    $id  Post ID.
     * @param string $key Meta key.
     * @param string $val Meta value; empty string deletes the key.
     * @return void
     */
    private function set_meta(int $id, string $key, string $val): void {
        $val === '' ? delete_post_meta($id, $key) : update_post_meta($id, $key, $val);
    }

    /**
     * When the featured image (_thumbnail_id) is changed, clear our custom OG image
     * so og_image_data() falls through to the new featured image automatically.
     */
    public function on_thumbnail_updated(int $meta_id, int $post_id, string $meta_key, $meta_value): void {
        if ($meta_key !== '_thumbnail_id') return;
        // Only clear if our custom OG image field is set — if it's empty, nothing to do.
        $custom = get_post_meta($post_id, self::META_OGIMG, true);
        if ($custom) {
            delete_post_meta($post_id, self::META_OGIMG);
        }
    }

}
