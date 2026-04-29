<?php
/**
 * Plugin settings page — renders all tabs, forms, and admin panels.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Settings_Page {
    // =========================================================================
    // Settings Page
    // =========================================================================

    /**
     * Renders the full plugin settings page including all tabs, forms, and admin panels.
     *
     * @since 4.0.0
     * @return void
     */
    public function settings_page(): void {
        if (!current_user_can('manage_options')) return;
        $o     = $this->opts;
        $ai    = $this->ai_opts;
        $nonce = wp_create_nonce('cs_seo_nonce');
        ?>
        <div class="wrap">
        <h1>CloudScale SEO AI Optimizer <span style="font-size:13px;font-weight:400;color:#999;margin-left:6px">v<?php echo esc_html(self::VERSION); ?></span></h1>
        <?php if ( isset( $_GET['settings-updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
        <div id="ab-settings-saved-toast" style="position:fixed;top:46px;right:20px;z-index:99999;background:#fff;border:1px solid #c3e6cb;border-left:4px solid #00a32a;color:#155724;padding:12px 20px;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,.18);font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;transition:opacity 0.4s;">
            &#x2705; <?php esc_html_e( 'Settings saved.', 'cloudscale-seo-ai-optimizer' ); ?>
        </div>
        <script>
        (function(){
            var t = document.getElementById('ab-settings-saved-toast');
            if (!t) return;
            setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ if(t.parentNode) t.parentNode.removeChild(t); },400); },3000);
        })();
        </script>
        <?php endif; ?>
        <a href="https://andrewbaker.ninja" target="_blank" rel="noopener" style="
            display:inline-flex;
            align-items:center;
            gap:8px;
            background:linear-gradient(135deg, #f953c6 0%, #b91d73 40%, #4f46e5 100%);
            color:#fff;
            font-weight:700;
            font-size:13px;
            padding:8px 18px;
            border-radius:20px;
            text-decoration:none;
            margin-bottom:18px;
            box-shadow:0 3px 10px rgba(249,83,198,0.45);
            letter-spacing:0.03em;
            transition:filter 0.15s, transform 0.15s;
        " class="cs-settings-link">
            <span style="font-size:16px">🥷</span> Totally Free by AndrewBaker.Ninja
        </a>
        <a href="https://andrewbaker.ninja/wordpress-plugin-help/seo-ai-optimizer/" target="_blank" rel="noopener" style="
            display:inline-flex;
            align-items:center;
            gap:6px;
            background:#1d2327;
            color:#fff;
            font-weight:600;
            font-size:13px;
            padding:8px 16px;
            border-radius:20px;
            text-decoration:none;
            margin-bottom:18px;
            margin-left:8px;
            box-shadow:0 3px 10px rgba(0,0,0,0.25);
            letter-spacing:0.03em;
            transition:filter 0.15s, transform 0.15s;
        " class="cs-settings-link">
            <span style="font-size:15px">📖</span> Help &amp; Documentation
        </a>

        <?php /* ── TAB NAV ── */ ?>

        <div class="ab-tabs">
            <button class="ab-tab active" data-tab="seo">📊 <?php esc_html_e( 'Optimise SEO', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="aitools">✨ <?php esc_html_e( 'AI Tools', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="sitemap">🗺 <?php esc_html_e( 'Sitemap, Robots &amp; Redirects', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="perf">⚡ <?php esc_html_e( 'Performance', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="catfix">🏷 <?php esc_html_e( 'Categories', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="batch">🔄 <?php esc_html_e( 'Scheduled Batch', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="blc">🔗 <?php esc_html_e( 'Broken Links', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="imgseo">🖼 <?php esc_html_e( 'Image SEO', 'cloudscale-seo-ai-optimizer' ); ?></button>
            <button class="ab-tab"        data-tab="titleopt">🎯 <?php esc_html_e( 'Title Optimiser', 'cloudscale-seo-ai-optimizer' ); ?></button>
        </div>
        </div>

        <?php /* ══════════════════ SETTINGS PANE (SEO + AI combined) ══════════════════ */ ?>
        <div class="ab-pane active" id="ab-pane-seo">

            <?php /* ── SEO Settings form ── */ ?>
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>

                <div class="ab-zone-card ab-card-identity">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🌐</span> <?php esc_html_e( 'Site Identity', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-identity" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('identity', '🌐 Site Identity — What each field does', [
                        ['rec'=>'✅ Recommended','name'=>'Site name','desc'=>'The name of your site as it appears in search results, browser tabs, and social sharing. Used in JSON-LD schema and OpenGraph tags. e.g. "Andrew Baker" or "Andrew Baker\'s Tech Blog".'],
                        ['rec'=>'✅ Recommended','name'=>'Title suffix','desc'=>'Appended to every page title in search results. e.g. if your suffix is "| Andrew Baker" then a post titled "AWS Lambda Tips" appears as "AWS Lambda Tips | Andrew Baker". Helps with brand recognition in SERPs.'],
                        ['rec'=>'✅ Recommended','name'=>'Home title','desc'=>'The SEO title for your homepage specifically. This is what Google shows as the blue link for your homepage in search results. Make it descriptive and keyword-rich — e.g. "Andrew Baker – CIO, Cloud Architect & Technology Leader".'],
                        ['rec'=>'✅ Recommended','name'=>'Home description','desc'=>'The meta description for your homepage. Shown as the snippet under your homepage title in Google. Aim for 140–155 characters. Write for humans — this is your elevator pitch to someone seeing your site for the first time.'],
                        ['rec'=>'✅ Recommended','name'=>'Default OG image URL','desc'=>'The fallback image used when a post is shared on social media and has no featured image. Should be 1200×630px. Use a branded image with your name/logo — this appears as the preview card on LinkedIn, Twitter/X, and WhatsApp.'],
                        ['rec'=>'✅ Recommended','name'=>'Target audience','desc'=>'Who reads your site — e.g. "software engineers, CTOs" or "first-time homebuyers". This is injected into every AI request as site context and has the biggest single impact on output quality. The AI uses it to calibrate vocabulary, assumed knowledge level, and what makes a description compelling for your specific readers. Fill this in before running any AI generation.'],
                        ['rec'=>'✅ Recommended','name'=>'Writing tone','desc'=>'The voice and style of your content — e.g. "direct and technical", "warm and encouraging", or "authoritative and concise". Combined with target audience, this tells the AI how to write for your brand rather than producing generic SEO copy. Fill in both this and Target audience before generating anything.'],
                        ['rec'=>'⬜ Optional','name'=>'Locale','desc'=>'BCP 47 language tag used in OpenGraph metadata. "en-US" is fine for most English sites. Use "en-ZA" if you want to signal a South African audience to Facebook/LinkedIn. Has minimal impact on Google rankings.'],
                        ['rec'=>'⬜ Optional','name'=>'Twitter handle','desc'=>'Your Twitter/X username including the @ symbol. Added to Twitter Card metadata so when your posts are shared on X, your account gets attributed as the author. Only matters if you actively use Twitter/X.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cs_seo_site_name">Site name:</label></th>
                        <td><input id="cs_seo_site_name" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[site_name]" value="<?php echo esc_attr((string)($o['site_name'] ?? '')); ?>" placeholder="My Tech Blog">
                        <p class="description">Used in JSON-LD schema and OG tags. e.g. My Tech Blog</p></td>
                        <th><label for="cs_seo_site_lang">Locale:</label></th>
                        <td><input id="cs_seo_site_lang" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[site_lang]" value="<?php echo esc_attr((string)($o['site_lang'] ?? '')); ?>" placeholder="en-US">
                        <p class="description">BCP 47 language tag. e.g. en-US, en-GB, fr-FR</p></td>
                    </tr>
                    <tr>
                        <th><label for="cs_seo_title_suffix">Title suffix:</label></th>
                        <td><input id="cs_seo_title_suffix" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[title_suffix]" value="<?php echo esc_attr((string)($o['title_suffix'] ?? '')); ?>" placeholder=" | My Tech Blog">
                        <p class="description">Appended to every page title. e.g. " | My Blog"</p></td>
                        <th><label for="cs_seo_twitter_handle">Twitter handle:</label></th>
                        <td><input id="cs_seo_twitter_handle" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[twitter_handle]" value="<?php echo esc_attr((string)($o['twitter_handle'] ?? '')); ?>" placeholder="@yourhandle">
                        <p class="description">Your Twitter/X handle including the @ symbol.</p></td>
                    </tr>
                    <tr>
                        <th><label for="cs_seo_home_title">Home title:</label></th>
                        <td><input id="cs_seo_home_title" class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[home_title]" value="<?php echo esc_attr((string)($o['home_title'] ?? '')); ?>" placeholder="My Blog – Tech Writer & Developer">
                        <p class="description">Full SEO title for your homepage.</p></td>
                        <th><label for="cs_seo_default_og_image">Default OG image URL:</label></th>
                        <td><input id="cs_seo_default_og_image" class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[default_og_image]" value="<?php echo esc_attr($o['default_og_image']); ?>" placeholder="https://yoursite.com/wp-content/uploads/og-default.jpg">
                        <p class="description">Fallback image for social sharing. 1200×630px ideal.</p></td>
                    </tr>
                    <tr><th><?php esc_html_e( 'Home description:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td colspan="3">
                            <textarea class="large-text" rows="3" name="<?php echo esc_attr(self::OPT); ?>[home_desc]" placeholder="A blog about technology, software development, and cloud architecture. Written for engineers and technical leaders."><?php echo esc_textarea($o['home_desc']); ?></textarea>
                            <p class="description">Meta description for your homepage. Aim for 140–155 characters.</p>
                        </td></tr>
                    <tr>
                        <td colspan="4" style="padding:0">
                            <div style="display:flex;gap:14px;align-items:flex-start;background:#f0effe;border:2px solid #6366f1;border-radius:6px;padding:14px 18px;margin:4px 0 8px">
                                <div style="font-size:22px;flex-shrink:0">✨</div>
                                <div style="flex:1;font-size:13px;line-height:1.6;color:#1e1b4b">
                                    <strong>Fill these in to unlock significantly better AI meta descriptions.</strong><br>
                                    Without them the AI writes generic copy. With them it writes for <em>your</em> readers in <em>your</em> voice — the single biggest quality lever available without touching the system prompt.<br>
                                    <span style="display:inline-block;margin-top:6px;background:#e0e7ff;border-radius:4px;padding:4px 10px;font-size:12px;font-family:monospace;color:#3730a3">Target audience: WordPress developers, agency owners &nbsp;·&nbsp; Writing tone: direct and technical</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cs_seo_target_audience">Target audience:</label></th>
                        <td><input id="cs_seo_target_audience" class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[target_audience]" value="<?php echo esc_attr((string)($o['target_audience'] ?? '')); ?>" placeholder="e.g. software engineers, CTOs, freelance developers">
                        <p class="description">Who reads this site — the AI uses this to match vocabulary and assumed knowledge level.</p></td>
                        <th><label for="cs_seo_writing_tone">Writing tone:</label></th>
                        <td><input id="cs_seo_writing_tone" class="regular-text" style="width:100%" name="<?php echo esc_attr(self::OPT); ?>[writing_tone]" value="<?php echo esc_attr((string)($o['writing_tone'] ?? '')); ?>" placeholder="e.g. direct and technical, casual and friendly, authoritative">
                        <p class="description">The voice of your content — the AI uses this to match your brand's tone.</p></td>
                    </tr>
                </table>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save SEO Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-identity -->

                <div class="ab-zone-card ab-card-person">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">👤</span> <?php esc_html_e( 'Person Schema', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-person" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('person', '👤 Person Schema — What each field does', [
                        ['rec'=>'✅ Recommended','name'=>'Full name','desc'=>'Your name as it appears in Google search results and Knowledge Graph. Use your real name exactly as you want it attributed — this is what Google uses to connect your content to you as an individual author.'],
                        ['rec'=>'✅ Recommended','name'=>'Profile URL','desc'=>'The canonical URL for your personal profile — usually your homepage (https://yoursite.com/). Google uses this as the authoritative identifier for you as a person in its Knowledge Graph.'],
                        ['rec'=>'✅ Recommended','name'=>'Job title','desc'=>'Your current job title, e.g. "Chief Information Officer". Included in your Person JSON-LD schema and helps Google understand your professional authority in your subject area.'],
                        ['rec'=>'✅ Recommended','name'=>'Person image URL','desc'=>'URL to your headshot or profile photo. Used in Person schema so Google can associate a face with your content. Ideally a square image of at least 400×400px already uploaded to your media library.'],
                        ['rec'=>'✅ Recommended','name'=>'Social profiles (sameAs)','desc'=>'One URL per line — your LinkedIn, Twitter/X, GitHub, Google Scholar etc. Google uses these to verify your identity and connect your various online presences. The more authoritative profiles you link, the stronger your author entity signal.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="cs_seo_person_name">Name:</label></th>
                        <td><input id="cs_seo_person_name" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_name]" value="<?php echo esc_attr((string)($o['person_name'] ?? '')); ?>" placeholder="Jane Smith">
                        <p class="description">Your full name as it appears in Google.</p></td>
                        <th><label for="cs_seo_person_job_title">Job title:</label></th>
                        <td><input id="cs_seo_person_job_title" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_job_title]" value="<?php echo esc_attr((string)($o['person_job_title'] ?? '')); ?>" placeholder="Software Engineer">
                        <p class="description">Your current job title.</p></td>
                    </tr>
                    <tr>
                        <th><label for="cs_seo_person_url">URL:</label></th>
                        <td><input id="cs_seo_person_url" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_url]" value="<?php echo esc_attr((string)($o['person_url'] ?? '')); ?>" placeholder="https://yoursite.com">
                        <p class="description">Canonical URL for your personal profile.</p></td>
                        <th><label for="cs_seo_person_image">Person image URL:</label></th>
                        <td><input id="cs_seo_person_image" class="regular-text" name="<?php echo esc_attr(self::OPT); ?>[person_image]" value="<?php echo esc_attr($o['person_image']); ?>" placeholder="https://yoursite.com/wp-content/uploads/headshot.jpg">
                        <p class="description">URL of your profile photo for Person JSON-LD schema.</p></td>
                    </tr>
                    <tr><th><?php esc_html_e( 'SameAs URLs (one per line):', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td colspan="3">
                            <textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPT); ?>[sameas]" placeholder="https://www.linkedin.com/in/yourname&#10;https://twitter.com/yourhandle&#10;https://github.com/yourname"><?php echo esc_textarea($o['sameas']); ?></textarea>
                            <p class="description">Your profiles on other platforms — one URL per line. Helps Google connect your identity across the web.</p>
                        </td></tr>
                </table>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save SEO Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-person -->

            </form>

            <hr class="ab-zone-divider">

            <?php /* ── AI Meta Writer config form ── */ ?>
            <form method="post" action="options.php" id="ab-ai-config-form">
                <?php settings_fields('cs_seo_ai_group'); ?>

                <div class="ab-zone-card ab-card-ai">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">✦</span> <?php esc_html_e( 'AI Provider and Model', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-ai" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('ai', '✦ AI Provider and Model — What each setting does', [
                        ['rec'=>'✅ Recommended','name'=>'AI Provider','desc'=>'Choose between Anthropic Claude or Google Gemini. Both support all generation features — pick based on your existing API key or pricing preference.'],
                        ['rec'=>'✅ Recommended','name'=>'API Key (Anthropic)','desc'=>'Your secret key from console.anthropic.com. Required when using Anthropic Claude. Keep this private — anyone with this key can use your Anthropic account. The key is stored securely in your WordPress database.'],
                        ['rec'=>'✅ Recommended','name'=>'API Key (Gemini)','desc'=>'Your secret key from aistudio.google.com. Required when using Google Gemini. Keep this private — anyone with this key can use your Google AI account. The key is stored securely in your WordPress database.'],
                        ['rec'=>'ℹ️ Info','name'=>'Model','desc'=>'Which model to use for generation. "Automatic" (the default) always uses the current recommended model for your provider — it updates automatically when a newer recommended model is available. If you need a specific version for cost or quality reasons, pin it manually. For Anthropic: Haiku is fast and cheap (ideal for bulk), Sonnet is higher quality. For Gemini: Flash models are fast and affordable, Pro models offer higher quality and longer context.'],
                        ['rec'=>'⬜ Optional','name'=>'Overwrite existing','desc'=>'When enabled, the AI will regenerate descriptions for posts that already have one. Leave OFF to only fill in missing descriptions — this protects any manually written descriptions you\'ve already crafted.'],
                        ['rec'=>'⬜ Optional','name'=>'Min / Max characters','desc'=>'Target character range for generated descriptions. Google typically shows 140–160 characters in search results before truncating. Descriptions shorter than 120 characters look thin; longer than 165 get cut off with an ellipsis.'],
                        ['rec'=>'⬜ Optional','name'=>'Custom prompt','desc'=>'Advanced: override the default AI instructions. The best way to improve output quality is to fill in Target audience and Writing tone in the Site Identity panel — those are injected automatically into every request. Only edit the prompt here if you need structural changes: writing in a language other than English, enforcing a specific format, or other niche requirements. Use Reset to default to restore the original prompt.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body ab-zone-ai">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'AI Provider:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(self::AI_OPT); ?>[ai_provider]" id="ab-ai-provider">
                                <option value="anthropic" <?php selected($ai['ai_provider'] ?? 'anthropic', 'anthropic'); ?>>Anthropic Claude</option>
                                <option value="gemini"    <?php selected($ai['ai_provider'] ?? 'anthropic', 'gemini'); ?>>Google Gemini</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'API Key:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <div class="ab-key-row">
                                <input type="password" class="regular-text"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[anthropic_key]"
                                    id="ab-anthropic-key-field"
                                    value="<?php echo esc_attr($ai['anthropic_key']); ?>"
                                    placeholder="sk-ant-api03-..."
                                    style="<?php echo esc_attr( ($ai['ai_provider'] ?? 'anthropic') === 'gemini' ? 'display:none' : '' ); ?>">
                                <input type="password" class="regular-text"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[gemini_key]"
                                    id="ab-gemini-key-field"
                                    value="<?php echo esc_attr($ai['gemini_key'] ?? ''); ?>"
                                    placeholder="AIza..."
                                    style="<?php echo esc_attr( ($ai['ai_provider'] ?? 'anthropic') !== 'gemini' ? 'display:none' : '' ); ?>">
                                <button type="button" class="button" id="ab-test-key-btn">Test Key</button>
                                <span id="ab-key-status" class="ab-key-status"></span>
                            </div>
                            <p class="description" id="ab-key-hint-anthropic" style="<?php echo esc_attr( ($ai['ai_provider'] ?? 'anthropic') === 'gemini' ? 'display:none' : '' ); ?>">
                                Get your key at <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com</a>. Stored in wp_options — never output to frontend.
                            </p>
                            <p class="description" id="ab-key-hint-gemini" style="<?php echo esc_attr( ($ai['ai_provider'] ?? 'anthropic') !== 'gemini' ? 'display:none' : '' ); ?>">
                                Get your key at <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a>. Stored in wp_options — never output to frontend.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Model:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <select name="<?php echo esc_attr(self::AI_OPT); ?>[model]" id="ab-model-select">
                                <option value="_auto" <?php selected($ai['model'], '_auto'); ?>>&#x2728; Automatic (recommended model, always up to date)</option>
                                <?php
                                $provider = $ai['ai_provider'] ?? 'anthropic';
                                $anthropic_models = [
                                    'claude-opus-4-6'           => 'Claude Opus 4.6 (best quality, highest cost)',
                                    'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (recommended — quality + speed)',
                                    'claude-sonnet-4-5'         => 'Claude Sonnet 4.5',
                                    'claude-sonnet-4.20.140514'  => 'Claude Sonnet 4 (stable pinned)',
                                    'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (fastest, cheapest)',
                                ];
                                $gemini_models = [
                                    'gemini-2.0-flash'      => 'Gemini 2.0 Flash (recommended — stable, fast)',
                                    'gemini-2.0-flash-001'  => 'Gemini 2.0 Flash 001 (pinned stable)',
                                    'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite (fast, cheapest 2.0)',
                                    'gemini-1.5-pro'        => 'Gemini 1.5 Pro (high quality, long context)',
                                    'gemini-1.5-flash'      => 'Gemini 1.5 Flash (fast, stable)',
                                    'gemini-1.5-flash-8b'   => 'Gemini 1.5 Flash 8B (smallest, cheapest)',
                                ];
                                $all_models = array_merge($anthropic_models, $gemini_models);
                                foreach ($all_models as $v => $l):
                                    $is_anthropic = array_key_exists($v, $anthropic_models);
                                    $group = $is_anthropic ? 'anthropic' : 'gemini';
                                ?>
                                    <option value="<?php echo esc_attr($v); ?>"
                                        data-provider="<?php echo esc_attr($group); ?>"
                                        <?php selected($ai['model'], $v); ?>
                                        <?php if ( $provider !== $group ) echo 'style="display:none"'; ?>
                                        ><?php echo esc_html($l); ?></option>
                                <?php endforeach; ?>
                                <option value="_custom">— Custom model ID (enter below) —</option>
                            </select>
                            <p style="margin:4px 0 0;font-size:12px;">
                                <a href="https://docs.anthropic.com/en/docs/about-claude/models/overview" target="_blank" rel="noopener" id="ab-model-link-anthropic" style="<?php echo esc_attr( ($provider === 'gemini') ? 'display:none' : '' ); ?>">View latest Claude models &rarr;</a>
                                <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener" id="ab-model-link-gemini" style="<?php echo esc_attr( ($provider !== 'gemini') ? 'display:none' : '' ); ?>">View latest Gemini models &rarr;</a>
                            </p>
                            <div id="ab-model-custom-wrap" style="margin-top:6px;<?php echo esc_attr( ($ai['model'] === '_auto' || in_array($ai['model'], array_keys($anthropic_models)) || in_array($ai['model'], array_keys($gemini_models))) ? 'display:none' : '' ); ?>">
                                <input type="text"
                                    id="ab-model-custom-input"
                                    value="<?php echo esc_attr($ai['model']); ?>"
                                    placeholder="Enter a model ID…"
                                    class="regular-text"
                                    style="width:340px;">
                                <p class="description">
                                    Enter the exact model ID from your provider.<br>
                                    <strong>Claude examples:</strong>
                                    <code>claude-opus-4-6</code>
                                    <code>claude-sonnet-4-6</code>
                                    <code>claude-haiku-4-5-20251001</code><br>
                                    <strong>Gemini examples:</strong>
                                    <code>gemini-2.0-flash</code>
                                    <code>gemini-1.5-pro</code>
                                    <code>gemini-1.5-flash</code><br>
                                    Find all available IDs at
                                    <a href="https://docs.anthropic.com/en/docs/about-claude/models/overview" target="_blank" rel="noopener">Anthropic docs</a>
                                    or
                                    <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener">Gemini docs</a>.
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Target length:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <input type="number" style="width:70px" name="<?php echo esc_attr(self::AI_OPT); ?>[min_chars]" value="<?php echo esc_attr($ai['min_chars']); ?>" min="100" max="160"> min &nbsp;
                            <input type="number" style="width:70px" name="<?php echo esc_attr(self::AI_OPT); ?>[max_chars]" value="<?php echo esc_attr($ai['max_chars']); ?>" min="100" max="200"> max characters
                            <p class="description">Google shows 120–160 chars. The range you set here is automatically injected into the prompt — you do not need to mention it in the system prompt above.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'ALT text article excerpt:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <input type="number" style="width:80px" name="<?php echo esc_attr(self::AI_OPT); ?>[alt_excerpt_chars]" value="<?php echo esc_attr((string)($ai['alt_excerpt_chars'] ?? 600)); ?>" min="100" max="2000"> characters
                            <p class="description">How much of the article text to send alongside each image when generating ALT text. More context produces better results for images with generic filenames, but increases API token usage. 600 is a good balance — enough to cover the intro and first heading. Increase to 1200+ for dense technical posts where images appear mid-article. Range: 100–2000.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'System prompt:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <textarea class="large-text" rows="10"
                                id="ab-prompt-field"
                                name="<?php echo esc_attr(self::AI_OPT); ?>[prompt]"><?php echo esc_textarea($ai['prompt']); ?></textarea>
                            <div style="display:flex;gap:6px;margin-top:4px">
                                <button type="button" class="button" id="ab-copy-prompt">⎘ Copy</button>
                                <button type="button" class="button" id="ab-reset-prompt">
                                    Reset to default
                                </button>
                            </div>
                            <?php /* reset-prompt listener moved to admin_enqueue_assets() */ ?>
                        </td>
                    </tr>
                </table>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save AI Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div><!-- /ab-zone-body -->
                </div><!-- /ab-card-ai -->
            </form>

        </div><!-- /ab-pane-seo -->

        <?php /* ══════════════════ AI TOOLS PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-aitools">

            <?php /* ── Auto Pipeline Card ── */ ?>
            <form method="post" action="options.php" style="margin-bottom:24px;">
                <?php settings_fields('cs_seo_ai_group'); ?>
                <div class="ab-zone-card ab-card-auto-pipeline">
                <div class="ab-zone-header" style="background:linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%);justify-content:space-between;">
                    <span><span class="ab-zone-icon">⚡</span> <?php esc_html_e( 'Auto Pipeline', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                    <?php $this->explain_btn('auto_pipeline', '⚡ Auto Pipeline — How it works', [
                        ['rec'=>'ℹ️ Info',         'name'=>'What it does',          'desc'=>'Automatically runs every AI operation — meta description, focus keyword, ALT text for attached images, internal link suggestions, AI summary box, Related Articles, and readability scoring — immediately when a post is published or updated. Each step runs in a background HTTP request so publish is never blocked.'],
                        ['rec'=>'✅ Recommended',  'name'=>'Run on first publish',  'desc'=>'Triggers once when a post goes from any status to Published. Will not re-run on subsequent saves unless "Re-run on update" is also enabled. Prevents duplicate API calls on minor edits.'],
                        ['rec'=>'⬜ Optional',     'name'=>'Re-run on update',      'desc'=>'Re-triggers the full pipeline every time an already-published post is saved. Useful for keeping AI content fresh when you make major content changes. Each re-run replaces all previous AI-generated data for that post.'],
                        ['rec'=>'ℹ️ Info',         'name'=>'Minimum content',       'desc'=>'All AI steps silently skip if the post has fewer than 50 words. This prevents generating meaningless output for stubs, drafts accidentally published, or test posts.'],
                        ['rec'=>'ℹ️ Info',         'name'=>'Related Articles',      'desc'=>'Related Articles generation always runs synchronously on publish regardless of whether the Auto Pipeline toggle is enabled — it is purely local (no API calls) and fast enough to run inline.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px;">
                    <p style="margin:0 0 16px;color:#50575e;"><?php esc_html_e( 'Automatically run all AI operations (meta description, ALT text, internal links, AI summary, Related Articles, readability score) in a background request immediately on publish. Requires an API key. Posts under 50 words are skipped.', 'cloudscale-seo-ai-optimizer' ); ?></p>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th style="width:220px;"><?php esc_html_e( 'Run on first publish:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                        name="<?php echo esc_attr(self::AI_OPT); ?>[auto_run_enabled]"
                                        value="1" <?php checked((int)($ai['auto_run_enabled'] ?? 1), 1); ?>>
                                    <?php esc_html_e( 'Run all AI operations when a post is first published', 'cloudscale-seo-ai-optimizer' ); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Re-run on update:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                        name="<?php echo esc_attr(self::AI_OPT); ?>[auto_run_on_update]"
                                        value="1" <?php checked((int)($ai['auto_run_on_update'] ?? 1), 1); ?>>
                                    <?php esc_html_e( 'Re-run all AI operations when an already-published post is updated', 'cloudscale-seo-ai-optimizer' ); ?>
                                </label>
                                <p class="description"><?php esc_html_e( 'Clears previous results and re-runs the full pipeline 5 seconds after each save.', 'cloudscale-seo-ai-optimizer' ); ?></p>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save Auto Pipeline Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-auto-pipeline -->
            </form>

            <div class="ab-zone-card ab-card-update-posts">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">✦</span> <?php esc_html_e( 'Update Posts with AI Descriptions', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <button class="button" id="ab-reload-hdr" style="visibility:hidden;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">↻ Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-update-posts" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9658; Show Details</button>
                        <?php $this->explain_btn('updateposts', '✦ Update Posts — How this works', [
                        ['rec'=>'ℹ️ Summary','name'=>'What this panel does','desc'=>'Writes the short text snippet that appears under your page title in Google search results — using AI to craft a compelling 140–155 character summary for each post.'],
                        ['rec'=>'ℹ️ Info','name'=>'Total Posts','desc'=>'The total number of published posts and pages on your site that are eligible for meta description generation.'],
                        ['rec'=>'ℹ️ Info','name'=>'Have Description','desc'=>'Posts that already have a meta description saved — either written manually or previously generated by the AI.'],
                        ['rec'=>'ℹ️ Info','name'=>'Unprocessed','desc'=>'Posts with no meta description yet. These are the ones Google is currently generating its own snippet for — which is often not the best representation of your content.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generated This Session','desc'=>'How many descriptions have been written by the AI since you opened this page. Resets each time you load the page.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate Missing','desc'=>'Runs the AI on every post that has no meta description yet. For each post, the AI also automatically generates ALT text for any images that are missing it — both tasks happen in a single API call, saving cost. Will never overwrite descriptions you\'ve already written.'],
                        ['rec'=>'⬜ Optional','name'=>'Regenerate All','desc'=>'Forces the AI to rewrite descriptions for every post, including ones that already have descriptions. Also generates missing ALT text for images in each post in the same call. Use this if you\'ve changed your prompt or want a fresh pass. Note: this will overwrite any manually written descriptions.'],
                        ['rec'=>'⬜ Optional','name'=>'Fix Long/Short','desc'=>'Finds descriptions that fall outside your target character range and rewrites only those. Does not touch ALT text — use the ALT Text Generator panel for that.'],
                        ['rec'=>'⬜ Optional','name'=>'Fix Titles','desc'=>'Scans all posts for title tags that fall outside the ideal 50–60 character range and AI-rewrites them to fit. The rewritten title is saved as a custom SEO title — your original WordPress post title is never changed. Skips the homepage (fix that manually) and any titles already in range.'],
                        ['rec'=>'⬜ Optional','name'=>'Regenerate Static','desc'=>'Fixes stale static data for every post — specifically, clears any custom OG image URL that has been overridden, so the post falls back to its current featured image. Run this if you have updated featured images on posts and LinkedIn, Twitter/X, or other platforms are still showing the old image. It does not touch AI descriptions or ALT text.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate (per row)','desc'=>'Rewrites the description for a single post and also generates missing image ALT text for that post in the same API call. Click this next to any post to manually trigger the AI for just that one entry.'],
                        ['rec'=>'ℹ️ Info','name'=>'ALT Images column','desc'=>'Shows how many images in each post are still missing ALT text. ⚠ yellow means images need attention — generating the description will fix them automatically. ✓ green means all images have ALT text.'],
                        ['rec'=>'ℹ️ Info','name'=>'Title column','desc'=>'Shows the character count of each post\'s effective title tag (custom SEO title if set, otherwise the WordPress post title). Green = 50–60 chars (ideal). Amber = 40–69 chars (acceptable). Red = outside that range (too short or too long for Google). Hover the badge to see the full title text. Use Fix Titles to auto-fix all out-of-range titles in one pass.'],
                        ['rec'=>'ℹ️ Info','name'=>'SEO Score column','desc'=>'AI-generated score (0–100%) rating how well each article is optimised for search engine indexing. Considers: title keyword clarity, meta description quality, content depth and specificity, and search intent alignment. Score is generated automatically when you click Generate on a row, or run Score All. Click any badge to re-score that post. Green ≥ 75, Amber 50–74, Red < 50. Run time for Score All depends on your selected model — faster/cheaper models score more posts per minute than higher-quality ones.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px;display:none;">

                <?php /* ── API key warning banner ── */ ?>
                <div class="ab-api-key-warning" id="ab-api-warn">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong><?php esc_html_e( 'No Anthropic API key saved — AI generation is disabled.', 'cloudscale-seo-ai-optimizer' ); ?></strong>
                        <?php esc_html_e( 'To use the AI buttons you need to:', 'cloudscale-seo-ai-optimizer' ); ?>
                        <ol style="margin:6px 0 0 16px;padding:0">
                            <li><?php echo wp_kses( __( 'Get a free API key at <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>', 'cloudscale-seo-ai-optimizer' ), array( 'a' => array( 'href' => array(), 'target' => array() ) ) ); ?></li>
                            <li><?php echo wp_kses( __( 'Paste it into the <strong>API Key</strong> field in the <strong>✦ AI Meta Writer</strong> section above', 'cloudscale-seo-ai-optimizer' ), array( 'strong' => array() ) ); ?></li>
                            <li><?php echo wp_kses( __( 'Click <strong>Save AI Settings</strong>', 'cloudscale-seo-ai-optimizer' ), array( 'strong' => array() ) ); ?></li>
                            <li><?php esc_html_e( 'Return here and reload the page', 'cloudscale-seo-ai-optimizer' ); ?></li>
                        </ol>
                    </div>
                </div>


                <?php /* ── Summary cards ── */ ?>
                <div class="ab-summary-row" id="ab-summary" style="display:none">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-total">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Total Posts & Pages', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-has" style="color:#1a7a34">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Have Description', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-missing-title" style="color:#6b7280">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Missing Title Tag', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-missing" style="color:#6b3fa0">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Unprocessed', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-generated" style="color:#2271b1">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Generated This Session', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                </div>

                <?php /* ── Toolbar ── */ ?>
                <div class="ab-ai-toolbar" id="ab-ai-toolbar" style="display:none">
                    <button class="button button-primary ab-action-btn" id="ab-ai-gen-missing" disabled>✦ Generate Missing</button>
                    <button class="button ab-action-btn ab-regen-btn" id="ab-ai-gen-all" disabled>↺ Regenerate All</button>
                    <button class="button ab-action-btn ab-fix-btn" id="ab-ai-fix" disabled>⚑ Fix Long/Short</button>
                    <button class="button ab-action-btn" id="ab-ai-fix-titles" disabled style="background:#7c3aed;color:#fff;border-color:#6d28d9">✎ Fix Titles</button>
                    <button class="button ab-action-btn" id="ab-ai-gen-missing-titles" disabled style="background:#0e6b6b;color:#fff;border-color:#0a5050">✦ Generate Missing Titles</button>
                    <button class="button ab-action-btn ab-static-btn" id="ab-ai-static" disabled>🖼 Regenerate Static</button>
                    <button class="button ab-action-btn" id="ab-ai-score-all" disabled style="background:#0e6b6b;border-color:#0a5050;color:#fff;font-weight:600">📊 Calculate SEO Scores</button>
                    <span id="ab-toolbar-status" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-ai-stop" style="display:none">◻ Stop</button>
                </div>

                <?php /* ── Progress bar ── */ ?>
                <div class="ab-progress" id="ab-progress">
                    <div class="ab-progress-fill" id="ab-progress-fill"></div>
                </div>
                <div class="ab-stats" id="ab-prog-label"></div>

                <?php /* ── Log ── */ ?>
                <div id="ab-log-wrap" style="display:none">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-log"></div>
                </div>

                <?php /* ── Post table ── */ ?>
                <div id="ab-posts-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>
                <div class="ab-pager" id="ab-pager" style="display:none">
                    <button class="button" id="ab-prev">← Prev</button>
                    <span id="ab-page-info" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-next">Next →</button>
                </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-update-posts -->

            <div class="ab-zone-card ab-card-alt">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🖼</span> <?php esc_html_e( 'AI Image ALT Text Generator', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <button class="button" id="ab-alt-reload-hdr" style="visibility:hidden;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">↻ Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-alt" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9658; Show Details</button>
                        <?php $this->explain_btn('alttext', '🖼 ALT Text — How this works', [
                        ['rec'=>'ℹ️ Summary','name'=>'What this panel does','desc'=>'Adds descriptive labels to every image on your site — used by screen readers for accessibility and by Google to understand image content for search ranking.'],
                        ['rec'=>'✅ Recommended','name'=>'Why ALT text matters','desc'=>'ALT (alternative) text describes images to screen readers and search engines. Missing ALT text is an accessibility failure and an SEO missed opportunity — Google uses ALT text to understand image content and rank your images in Google Images search.'],
                        ['rec'=>'ℹ️ Info','name'=>'Posts with missing ALT','desc'=>'Shows how many posts have at least one image with an empty ALT attribute. Click Load to scan your site.'],
                        ['rec'=>'ℹ️ Info','name'=>'Images missing ALT','desc'=>'The total count of individual image tags across all posts that have empty ALT attributes.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate All Missing','desc'=>'Runs the AI on every post that has images with missing ALT text. For each image, the AI reads the post title and image filename to write a concise, contextually appropriate ALT description (5 to 15 words). If the AI returns text outside that range, it automatically retries once. The post content is updated in place and the attachment media library entry is also updated.'],
                        ['rec'=>'ℹ️ Info','name'=>'Force Regenerate All','desc'=>'Overwrites ALL existing ALT text across every post, not just missing ones. Useful if you want to improve previously generated ALT text or standardise quality across your site. A confirmation prompt appears before running. The same 5 to 15 word validation with retry applies.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate (per row)','desc'=>'Process a single post — useful to check results before running the full batch. All images in that post with empty ALT will be processed.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px;display:none;">

                <div class="ab-api-key-warning" id="ab-alt-api-warn" style="<?php
                    $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
                    $alt_has_key = $provider === 'gemini'
                        ? !empty(trim((string)($this->ai_opts['gemini_key'] ?? '')))
                        : !empty(trim((string)($this->ai_opts['anthropic_key'] ?? '')));
                    echo esc_attr( $alt_has_key ? 'display:none' : '' );
                ?>">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong><?php esc_html_e( 'No AI API key saved — ALT text generation is disabled.', 'cloudscale-seo-ai-optimizer' ); ?></strong>
                        <?php echo wp_kses( __( 'Add an Anthropic API key in the <strong>✦ AI Meta Writer</strong> section above and save.', 'cloudscale-seo-ai-optimizer' ), array( 'strong' => array() ) ); ?>
                    </div>
                </div>


                <div class="ab-summary-row" id="ab-alt-summary" style="display:none">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-posts">0</div><div class="ab-summary-lbl">Posts with Missing ALT</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-images" style="color:#c3372b">0</div><div class="ab-summary-lbl">Images Missing ALT</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="alt-sum-done" style="color:#1a7a34">0</div><div class="ab-summary-lbl">Fixed This Session</div></div>
                </div>

                <div class="ab-ai-toolbar" id="ab-alt-toolbar" style="display:none">
                    <button class="button button-primary ab-action-btn" id="ab-alt-gen-all" <?php echo esc_attr( $alt_has_key ? '' : 'disabled' ); ?>>✦ Generate All Missing</button>
                    <button class="button ab-action-btn" id="ab-alt-force-all" style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600" <?php echo esc_attr( $alt_has_key ? '' : 'disabled' ); ?>>🔄 Force Regenerate All</button>
                    <span id="ab-alt-status" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-alt-stop" style="display:none">◻ Stop</button>
                    <label id="ab-alt-show-all-wrap" style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                        <input type="checkbox" id="ab-alt-show-all"> Show all
                    </label>
                </div>

                <div class="ab-progress" id="ab-alt-progress">
                    <div class="ab-progress-fill" id="ab-alt-progress-fill"></div>
                </div>
                <div class="ab-stats" id="ab-alt-prog-label"></div>
                <div id="ab-alt-log-wrap" style="display:none">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-alt-log"></div>
                </div>
                <div id="ab-alt-posts-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-alt -->

            <div class="ab-zone-card ab-card-summary">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">📋</span> <?php esc_html_e( 'AI Summary Box Generator', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <button class="button" id="ab-sum-reload-hdr" style="visibility:hidden;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">↻ Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-summary" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9658; Show Details</button>
                        <?php $this->explain_btn('summary', '📋 AI Summary Box — How this works', [
                        ['rec'=>'ℹ️ Summary','name'=>'What this panel does','desc'=>'Generates the three-field AI Summary Box shown at the top of each post — What it is, Why it matters, and Key takeaway. Summaries are now written SEO-first: primary keyword in the opening sentence, secondary keywords woven in naturally, and sentences written to match search intent rather than for conversational reading.'],
                        ['rec'=>'✅ Recommended','name'=>'Why SEO summaries matter','desc'=>'AI-powered search engines like Perplexity and SearchGPT use structured, keyword-rich summaries to decide whether to cite your content. A summary that leads with keywords and answers search intent directly is far more likely to be cited than a vague human-readable blurb.'],
                        ['rec'=>'ℹ️ Info','name'=>'Generate Missing','desc'=>'Processes every published post that has no existing AI summary. Runs one post at a time and shows progress. Safe to stop and restart at any time.'],
                        ['rec'=>'ℹ️ Info','name'=>'Force Regenerate All','desc'=>'Overwrites all existing AI summaries across every post. Use this after the SEO-first prompt upgrade to refresh older human-readable summaries with keyword-optimised versions.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px;display:none;">

                <div class="ab-api-key-warning" id="ab-sum-api-warn" style="<?php echo esc_attr( $alt_has_key ? 'display:none' : '' ); ?>">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong>No AI API key saved — summary generation is disabled.</strong>
                        Add an Anthropic API key in the <strong>✦ AI Meta Writer</strong> section above and save.
                    </div>
                </div>


                <div class="ab-summary-row" id="ab-sum-summary" style="display:none">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-s-total">0</div><div class="ab-summary-lbl">Total Posts</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-s-has" style="color:#1a7a34">0</div><div class="ab-summary-lbl">Have Summary</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-s-missing" style="color:#6b3fa0">0</div><div class="ab-summary-lbl">Missing Summary</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="sum-s-done" style="color:#2271b1">0</div><div class="ab-summary-lbl">Generated This Session</div></div>
                </div>

                <div class="ab-ai-toolbar" id="ab-sum-toolbar" style="display:none">
                    <button class="button button-primary ab-action-btn" id="ab-sum-gen-all" <?php disabled( ! $alt_has_key ); ?>>✦ Generate Missing</button>
                    <button class="button ab-action-btn" id="ab-sum-force-all" style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600" <?php disabled( ! $alt_has_key ); ?>>🔄 Force Regenerate All</button>
                    <span id="ab-sum-status" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-sum-stop" style="display:none">◻ Stop</button>
                </div>

                <div class="ab-progress" id="ab-sum-progress">
                    <div class="ab-progress-fill" id="ab-sum-progress-fill"></div>
                </div>
                <div class="ab-stats" id="ab-sum-prog-label"></div>
                <div id="ab-sum-posts-wrap" style="margin-top:12px;overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>

                <div id="ab-sum-log-wrap" style="display:none">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-sum-log"></div>
                </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-summary -->

            <div style="margin:44px 0 28px;display:flex;align-items:center;gap:14px;">
                <div style="flex:1;height:3px;background:linear-gradient(90deg,#6366f1,#c7d2fe);border-radius:2px;"></div>
                <span style="font-size:12px;font-weight:700;color:#4338ca;text-transform:uppercase;letter-spacing:0.09em;white-space:nowrap;">&#128279; Related Articles</span>
                <div style="flex:1;height:3px;background:linear-gradient(90deg,#c7d2fe,#6366f1);border-radius:2px;"></div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_enable]"          value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_top_enabled]"     value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_bottom_enabled]"  value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_use_categories]"  value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_use_tags]"        value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[rc_use_summary]"     value="0">

                <!-- Related Articles Settings Card -->
                <div class="ab-zone-card ab-card-rc-settings-card" style="margin-top:24px;">
                    <div class="ab-zone-header" style="background:#0e7490;display:flex;align-items:center;justify-content:space-between;">
                        <span>&#128279; Related Articles</span>
                        <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-rc-settings-card" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9658; Show Details</button>
                        <?php $this->explain_btn('rc_settings', '&#128279; Related Articles — Settings', [
                            ['name'=>'Enable feature',       'rec'=>'ℹ️ Info',      'desc'=>'Enables or disables the Related Articles and You Might Also Like blocks on all posts. Disabling hides the blocks from the front end immediately without deleting any stored data.'],
                            ['name'=>'Related Articles block','rec'=>'ℹ️ Info',     'desc'=>'The block that appears near the top of each post, directly after the AI summary box. Shows the closest conceptual matches based on shared categories, tags, and keyword overlap. Requires at least 2 links to display.'],
                            ['name'=>'You Might Also Like',  'rec'=>'ℹ️ Info',      'desc'=>'The block that appears at the bottom of each post before the comments section. Draws from a broader pool of related posts to extend session depth. Requires at least 3 links to display.'],
                            ['name'=>'Decreasing count',     'rec'=>'✅ Instant',   'desc'=>'If you reduce the number of links to show, the change takes effect immediately on the front end without any regeneration. The extra stored links are simply not displayed.'],
                            ['name'=>'Increasing count',     'rec'=>'⚠️ Regenerate','desc'=>'If you increase the number of links to show, existing posts may not have enough stored links to fill the new count. Run Refresh Stale in the Related Articles Post Status table below to regenerate all posts with the new count.'],
                            ['name'=>'Candidate pool size',  'rec'=>'ℹ️ Info',      'desc'=>'Controls how many candidate posts are evaluated per source when scoring. A larger pool improves accuracy but takes slightly longer to process. The default of 20 is suitable for most sites.'],
                            ['name'=>'Scoring signals',      'rec'=>'ℹ️ Info',      'desc'=>'The signals used to score candidate posts. Categories and tags provide structural signals. AI summary overlap uses the generated summary text to find semantic connections. At least one signal must be enabled.'],
                            ['name'=>'Exclude categories',   'rec'=>'ℹ️ Info',      'desc'=>'Posts in excluded categories will not appear as related link suggestions on any post. Use this to prevent utility categories, news, or announcements from appearing as related content.'],
                        ]); ?>
                        </span>
                    </div>
                    <div class="ab-zone-body ab-card-rc-settings" style="padding:20px 24px;display:none;">
                        <p style="color:#555;margin:0 0 16px;">Controls where and how Related Articles and You Might Also Like link blocks appear on your posts. Links are generated using local signals only &mdash; no AI calls, no timeouts.</p>
                        <table class="form-table" style="margin:0;">
                            <tr>
                                <th style="width:220px;padding:12px 0;"><?php esc_html_e( 'Enable feature', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_enable]" value="1" <?php checked((int)($o['rc_enable'] ?? 1), 1); ?>> Enable Related Articles and You Might Also Like on posts</label>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'Related Articles block', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_top_enabled]" value="1" <?php checked((int)($o['rc_top_enabled'] ?? 1), 1); ?>> Show &ldquo;Related Articles&rdquo; block at the top (after AI summary)</label><br>
                                    <span style="display:inline-flex;align-items:center;gap:8px;margin-top:6px;">
                                        Links to show:
                                        <input type="number" id="rc_top_count_input" name="<?php echo esc_attr(self::OPT); ?>[rc_top_count]" value="<?php echo esc_attr((int)($o['rc_top_count'] ?? 3)); ?>" min="1" max="5" style="width:60px;" data-saved="<?php echo esc_attr((int)($o['rc_top_count'] ?? 3)); ?>">
                                        <span style="color:#888;font-size:12px;">(min 2, max 5)</span>
                                    </span>
                                    <p id="rc-top-warn" style="display:none;margin:6px 0 0;padding:8px 12px;background:#fffbeb;border-left:3px solid #f59e0b;color:#92400e;font-size:12px;">&#9888; You have increased the link count. Existing posts will only show the new amount after you run <strong>Refresh Stale</strong> in the Related Articles table below.</p>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'You Might Also Like block', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_bottom_enabled]" value="1" <?php checked((int)($o['rc_bottom_enabled'] ?? 1), 1); ?>> Show &ldquo;You Might Also Like&rdquo; block at the bottom (before comments)</label><br>
                                    <span style="display:inline-flex;align-items:center;gap:8px;margin-top:6px;">
                                        Links to show:
                                        <input type="number" id="rc_bottom_count_input" name="<?php echo esc_attr(self::OPT); ?>[rc_bottom_count]" value="<?php echo esc_attr((int)($o['rc_bottom_count'] ?? 5)); ?>" min="1" max="10" style="width:60px;" data-saved="<?php echo esc_attr((int)($o['rc_bottom_count'] ?? 5)); ?>">
                                        <span style="color:#888;font-size:12px;">(min 3, max 10)</span>
                                    </span>
                                    <p id="rc-bottom-warn" style="display:none;margin:6px 0 0;padding:8px 12px;background:#fffbeb;border-left:3px solid #f59e0b;color:#92400e;font-size:12px;">&#9888; You have increased the link count. Existing posts will only show the new amount after you run <strong>Refresh Stale</strong> in the Related Articles table below.</p>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'Display style', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <?php
                                    $rc_styles = [
                                        '1'  => 'Style 1  — Purple gradient header (default)',
                                        '2'  => 'Style 2  — Dark gold: dark navy, gold accents',
                                        '3'  => 'Style 3  — Royal blue minimal',
                                        '4'  => 'Style 4  — Emerald green bordered cards',
                                        '5'  => 'Style 5  — Steel grey side stripe',
                                        '6'  => 'Style 6  — Crimson magazine rows',
                                        '7'  => 'Style 7  — Ocean teal gradient header',
                                        '8'  => 'Style 8  — Warm amber dark panel',
                                        '9'  => 'Style 9  — Navy blue gradient header',
                                        '10' => 'Style 10 — Charcoal minimal',
                                        '11' => 'Style 11 — Forest green gradient header',
                                        '12' => 'Style 12 — Rose pink gradient header',
                                        '13' => 'Style 13 — Sunset orange gradient header',
                                        '14' => 'Style 14 — Midnight dark (sky blue on black)',
                                        '15' => 'Style 15 — Deep purple dark panel',
                                        '16' => 'Style 16 — Teal minimal',
                                        '17' => 'Style 17 — Rose pink minimal',
                                        '18' => 'Style 18 — Amber side stripe',
                                        '19' => 'Style 19 — Slate bordered box',
                                        '20' => 'Style 20 — Violet pill badges',
                                    ];
                                    $rc_style_val = (string)($o['rc_style'] ?? '1');
                                    ?>
                                    <select id="rc-style-select" name="<?php echo esc_attr(self::OPT); ?>[rc_style]" style="max-width:420px;">
                                        <?php foreach ($rc_styles as $val => $label) : ?>
                                            <option value="<?php echo esc_attr($val); ?>" <?php selected($rc_style_val, $val); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" style="margin-top:4px;">Changes take effect immediately — no need to regenerate.</p>
                                    <div id="rc-style-preview" style="margin-top:14px;max-width:400px;"></div>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'Candidate pool size', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <input type="number" name="<?php echo esc_attr(self::OPT); ?>[rc_pool_size]" value="<?php echo esc_attr((int)($o['rc_pool_size'] ?? 20)); ?>" min="10" max="50" style="width:70px;">
                                    <span style="color:#888;font-size:12px;margin-left:6px;">posts evaluated per source (10&ndash;50)</span>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'Scoring signals', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <label style="margin-right:16px;"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_use_categories]" value="1" <?php checked((int)($o['rc_use_categories'] ?? 1), 1); ?>> Categories</label>
                                    <label style="margin-right:16px;"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_use_tags]" value="1" <?php checked((int)($o['rc_use_tags'] ?? 1), 1); ?>> Tags</label>
                                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_use_summary]" value="1" <?php checked((int)($o['rc_use_summary'] ?? 1), 1); ?>> AI summary overlap</label>
                                    <p class="description" style="margin-top:4px;">These signals are combined to score candidate posts. At least one must be enabled.</p>
                                </td>
                            </tr>
                            <?php
                            $all_cats      = get_categories(['hide_empty' => false]);
                            $excluded_cats = (array)($o['rc_exclude_cats'] ?? []);
                            if (!empty($all_cats)) : ?>
                            <tr>
                                <th style="padding:12px 0;"><?php esc_html_e( 'Exclude categories', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                <td style="padding:12px 0;">
                                    <div style="display:flex;flex-wrap:wrap;gap:6px 16px;">
                                    <?php foreach ($all_cats as $cat) : ?>
                                        <label style="white-space:nowrap;">
                                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[rc_exclude_cats][]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked(in_array((int)$cat->term_id, array_map('intval', $excluded_cats))); ?>>
                                            <?php echo esc_html($cat->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                    </div>
                                    <p class="description" style="margin-top:4px;">Posts in these categories will not appear as related link suggestions.</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                        <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save SEO Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                    </div>
                </div><!-- /ab-card-rc-settings -->

            </form>

            <?php /* ── Related Articles Generation Table ── */ ?>
            <div class="ab-zone-card ab-card-rc-table" style="margin-top:24px;">
                <div class="ab-zone-header" style="background:linear-gradient(120deg,#4338ca 0%,#6366f1 60%,#818cf8 100%);display:flex;align-items:center;justify-content:space-between;">
                    <span>&#128279; Related Articles — Post Status</span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-rc-table" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9658; Show Details</button>
                        <?php $this->explain_btn('rc_table', '&#128279; Related Articles — How it works', [
                            ['name'=>'What it does',       'rec'=>'ℹ️ Info',      'desc'=>'For every published post, Related Articles finds and ranks other posts on your site that are topically related. It surfaces two blocks on the front end: a &ldquo;Related Articles&rdquo; block near the top of the article (closest conceptual matches) and a &ldquo;You Might Also Like&rdquo; block at the bottom (broader related posts).'],
                            ['name'=>'No AI required',     'rec'=>'✅ Free',       'desc'=>'Generation uses only signals already on your site — shared categories, shared tags, title keyword overlap, and your existing AI summary text. There are zero API calls and no cost. It scores every candidate post locally in PHP and ranks by relevance.'],
                            ['name'=>'Generate Missing',   'rec'=>'✅ Recommended','desc'=>'Processes all posts that have not yet been generated. Run this once after installing the plugin to populate your full post library. Each post takes under a second and the batch runs with a small delay between posts to avoid overloading the server.'],
                            ['name'=>'Refresh Stale',      'rec'=>'⬜ Optional',   'desc'=>'Re-runs generation for all posts regardless of status. Use this after making significant changes to your category or tag structure, or after adding AI summaries to posts that previously lacked them.'],
                            ['name'=>'Retry Failed',       'rec'=>'⬜ Optional',   'desc'=>'Re-runs only posts that errored during a previous batch. Useful if a batch was interrupted or a post had missing data.'],
                            ['name'=>'Reset All',          'rec'=>'⚠️ Caution',   'desc'=>'Deletes all Related Articles data for every post. The front-end blocks will disappear from all posts immediately. You will need to run Generate Missing again to rebuild. Use this only if you want to start fresh after major structural changes.'],
                            ['name'=>'Per-row Run button', 'rec'=>'ℹ️ Info',      'desc'=>'Runs the full generation pipeline for a single post. Updates the row in place without reloading the page. Use this to regenerate a specific post after editing its categories, tags, or AI summary.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body ab-card-rc-table-body" style="padding:20px 24px;display:none;">

                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                        <span style="font-weight:600;color:#1d2327;font-size:13px;">Filter:</span>
                        <button type="button" class="button rc-filter-btn rc-filter-active" data-filter="all"    >All Posts</button>
                        <button type="button" class="button rc-filter-btn"                  data-filter="pending" >&#9711; Pending</button>
                        <button type="button" class="button rc-filter-btn"                  data-filter="complete">&#9989; Complete</button>
                        <button type="button" class="button rc-filter-btn"                  data-filter="error"   >&#10060; Error</button>
                        <span style="flex:1;"></span>
                        <button type="button" class="button button-primary" id="rc-btn-sync-counts"      title="Generates missing Related Articles for new posts and syncs link counts for existing ones — single server-side pass">&#9881; Generate &amp; Sync</button>
                        <button type="button" class="button"               id="rc-btn-refresh-stale"   >&#8635; Refresh Stale</button>
                        <button type="button" class="button"               id="rc-btn-retry-failed"    >&#128257; Retry Failed</button>
                        <button type="button" class="button"               id="rc-btn-reset-all"        style="color:#b91c1c;border-color:#b91c1c;">&#128465; Reset All</button>
                    </div>

                    <div id="rc-batch-bar" style="display:none;background:#f0f4ff;border:1px solid #c7d2fe;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="flex:1;background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden;">
                                <div id="rc-batch-progress-bar" style="height:100%;background:#6366f1;border-radius:4px;width:0%;transition:width 0.3s;"></div>
                            </div>
                            <span id="rc-batch-label" style="font-size:12px;color:#4338ca;font-weight:600;white-space:nowrap;">0 / 0</span>
                            <button type="button" class="button" id="rc-btn-stop" style="color:#b91c1c;border-color:#b91c1c;">&#9646;&#9646; Stop</button>
                        </div>
                    </div>

                    <div id="rc-table-wrap" style="overflow-x:auto;">
                        <table class="widefat fixed striped" id="rc-posts-table" style="min-width:680px;">
                            <thead>
                                <tr>
                                    <th style="width:40%;">Post</th>
                                    <th style="width:14%;text-align:center;">Status</th>
                                    <th style="width:9%;text-align:center;">Top</th>
                                    <th style="width:9%;text-align:center;">Bottom</th>
                                    <th style="width:14%;text-align:center;">Generated</th>
                                    <th style="width:14%;text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="rc-posts-tbody">
                                <tr><td colspan="6" style="text-align:center;padding:24px;color:#999;">Loading…</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div id="rc-pagination" style="display:flex;gap:8px;align-items:center;margin-top:12px;"></div>

                </div>
            </div><!-- /ab-card-rc-table -->

        </div><!-- /ab-pane-aitools -->

        <?php /* ══════════════════ SITEMAP PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-sitemap">
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>
                <?php /* Hidden fallback: unchecked checkboxes aren't submitted, so these ensure 0 is saved when unchecked */ ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_og]"                   value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_website]"     value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_person]"      value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_article]"     value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_breadcrumbs]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[show_summary_box]"          value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[strip_tracking_params]"     value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[noindex_search]"            value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[noindex_404]"               value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[noindex_attachment]"        value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[noindex_author_archives]"   value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[noindex_tag_archives]"      value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[block_ai_bots]"             value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_sitemap]"            value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[sitemap_taxonomies]"        value="0">

                <?php
                $pub_types = get_post_types(['public' => true], 'objects');
                $sel_types = (array)($o['sitemap_post_types'] ?? ['post', 'page']);
                ?>

                <div class="ab-zone-card ab-card-features">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⚙</span> <?php esc_html_e( 'Features &amp; Robots', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-features" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('features', '⚙ Features & Robots — What each option does', [
                        ['rec'=>'✅ Recommended','name'=>'OpenGraph + Twitter Cards','desc'=>'Adds structured metadata so your posts display with a title, description and image when shared on LinkedIn, Twitter/X, WhatsApp or any other platform. Without this, shared links look blank or use random images.'],
                        ['rec'=>'✅ Recommended','name'=>'WebSite JSON-LD (front page)','desc'=>'Tells Google the name and URL of your site in structured data format. Helps Google display your site name correctly in search results and can unlock sitelinks beneath your homepage listing.'],
                        ['rec'=>'✅ Recommended','name'=>'Person JSON-LD schema','desc'=>'Embeds your name, job title, photo, and social profiles into your site so Google can connect your content to you as an individual. Important for personal brand and author authority signals.'],
                        ['rec'=>'✅ Recommended','name'=>'BlogPosting JSON-LD schema','desc'=>'Marks up each post as an article with author, publish date, and headline. Google uses this for rich results and to better understand your content type. Can improve click-through rates in search.'],
                        ['rec'=>'⬜ Optional','name'=>'Breadcrumb JSON-LD schema','desc'=>'Adds breadcrumb trail markup to posts. Most useful on large sites with deep category hierarchies. For a flat personal blog this adds little value — Google will figure out your structure without it.'],
                        ['rec'=>'⬜ Optional','name'=>'Strip UTM params in canonical URLs','desc'=>'If you use UTM tracking parameters on your own internal links (e.g. ?utm_source=newsletter), this stops them creating duplicate pages in Google\'s index. Only needed if you track internal clicks with UTM.'],
                        ['rec'=>'✅ Recommended','name'=>'Enable /sitemap.xml','desc'=>'Generates a sitemap listing all your posts and pages. Submit this URL to Google Search Console so Google knows exactly what to crawl. Also automatically added to your robots.txt.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex search results','desc'=>'Prevents Google from indexing your WordPress search result pages (e.g. /?s=keyword). These pages have no unique value and waste Google\'s crawl budget — always block them.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex 404 pages','desc'=>'Stops Google indexing error pages. A 404 page has no content worth ranking — keeping these out of the index keeps your crawl budget focused on real content.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex attachment pages','desc'=>'WordPress creates a separate page for every uploaded image or file. These pages are near-empty and often outrank your actual posts for image searches. Always block them.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex author archives','desc'=>'On a single-author blog, your author archive page (/author/yourname/) is essentially a duplicate of your homepage. Blocking it prevents a duplicate content penalty.'],
                        ['rec'=>'✅ Recommended','name'=>'noindex tag archives','desc'=>'Tag archive pages (/tag/aws/) often duplicate post content and can dilute your rankings. Unless your tag pages have unique introductory text and real editorial value, block them.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:16px 20px">
                <div class="ab-checkbox-grid">
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_og]" value="1" <?php checked((int)($o['enable_og'] ?? 0), 1); ?>> OpenGraph + Twitter Cards</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_website]" value="1" <?php checked((int)($o['enable_schema_website'] ?? 0), 1); ?>> WebSite JSON-LD (front page)</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_person]" value="1" <?php checked((int)($o['enable_schema_person'] ?? 0), 1); ?>> Person JSON-LD schema</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_article]" value="1" <?php checked((int)($o['enable_schema_article'] ?? 0), 1); ?>> BlogPosting JSON-LD schema</label>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_schema_breadcrumbs]" value="1" <?php checked((int)($o['enable_schema_breadcrumbs'] ?? 0), 1); ?>> Breadcrumb JSON-LD schema</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[show_summary_box]" value="1" <?php checked((int)($o['show_summary_box'] ?? 1), 1); ?>> Show AI summary box on posts</label>
                    <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[strip_tracking_params]" value="1" <?php checked((int)($o['strip_tracking_params'] ?? 0), 1); ?>> Strip UTM params in canonical URLs</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_sitemap]" value="1" <?php checked((int)($o['enable_sitemap'] ?? 0), 1); ?>> Enable /sitemap.xml</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_search]" value="1" <?php checked((int)($o['noindex_search'] ?? 0), 1); ?>> noindex search results</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_404]" value="1" <?php checked((int)($o['noindex_404'] ?? 0), 1); ?>> noindex 404 pages</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_attachment]" value="1" <?php checked((int)($o['noindex_attachment'] ?? 0), 1); ?>> noindex attachment pages</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_author_archives]" value="1" <?php checked((int)($o['noindex_author_archives'] ?? 0), 1); ?>> noindex author archives</label>
                    <label class="ab-rec"><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[noindex_tag_archives]" value="1" <?php checked((int)($o['noindex_tag_archives'] ?? 0), 1); ?>> noindex tag archives</label>
                </div>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save Features &amp; Robots Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-features -->


                <div class="ab-zone-card ab-card-sitemap-settings">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⚙</span> <?php esc_html_e( 'Sitemap Settings', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-sitemap-settings" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('sitemap', '⚙ Sitemap Settings — What each option does', [
                        ['rec'=>'✅ Recommended','name'=>'Enable /sitemap.xml','desc'=>'Generates a sitemap at yoursite.com/sitemap.xml listing all your published content. Submit this URL to Google Search Console so Google knows exactly what pages to crawl. Also automatically appends the sitemap URL to your robots.txt.'],
                        ['rec'=>'✅ Recommended','name'=>'Include Posts','desc'=>'Adds all your published blog posts to the sitemap. This should always be on — posts are your primary content and the main thing you want Google to discover and index.'],
                        ['rec'=>'✅ Recommended','name'=>'Include Pages','desc'=>'Adds your WordPress pages (About, Contact etc.) to the sitemap. Keep this on — pages like your About and Contact pages should be indexed.'],
                        ['rec'=>'⬜ Optional','name'=>'Taxonomy archives','desc'=>'Includes category, tag, and custom taxonomy archive pages in the sitemap. Turn this on only if your archive pages have unique introductory content and genuine value for visitors. For most blogs, leave it off — archive pages often duplicate post content.'],
                        ['rec'=>'⬜ Optional','name'=>'Exclude URLs or IDs','desc'=>'Enter specific URLs or post IDs to omit from the sitemap — one per line. Use this for thank-you pages, landing pages, privacy policy pages, or any content you don\'t want Google to prioritise. Numeric IDs (e.g. 42) refer to the WordPress post/page ID shown in the edit URL.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Enable sitemap:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_sitemap]" value="1" <?php checked((int)($o['enable_sitemap'] ?? 0), 1); ?>>
                            Generate sitemap at <a href="<?php echo esc_url(home_url('/sitemap.xml')); ?>" target="_blank"><?php echo esc_html(home_url('/sitemap.xml')); ?></a></label>
                            <p class="description">When enabled the Sitemap URL is also appended to your robots.txt automatically.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Include post types:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <div style="display:flex;gap:16px;flex-wrap:wrap">
                            <?php foreach ($pub_types as $pt): ?>
                                <?php if (in_array($pt->name, ['attachment'], true)) continue; ?>
                                <label>
                                    <input type="checkbox"
                                        name="<?php echo esc_attr(self::OPT); ?>[sitemap_post_types][]"
                                        value="<?php echo esc_attr($pt->name); ?>"
                                        <?php checked(in_array($pt->name, $sel_types, true), true); ?>>
                                    <?php echo esc_html($pt->labels->name); ?>
                                    <span style="color:#888;font-size:11px">(<?php echo esc_html($pt->name); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            </div>
                            <p class="description">Select which post types to include. Uncheck types that are not meaningful for search engines (e.g. WooCommerce order pages).</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Taxonomy archives:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[sitemap_taxonomies]" value="1" <?php checked((int)($o['sitemap_taxonomies'] ?? 0), 1); ?>>
                            Include category, tag, and custom taxonomy archive pages</label>
                            <p class="description">Off by default. Enable if your category or tag archive pages have unique, valuable content worth indexing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Exclude URLs or IDs:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <textarea name="<?php echo esc_attr(self::OPT); ?>[sitemap_exclude]"
                                rows="6" style="width:100%"
                                placeholder="e.g. https://yoursite.com/thank-you/<?php echo "\n"; ?>e.g. https://yoursite.com/privacy-policy/<?php echo "\n"; ?>e.g. 42<?php echo "\n"; ?>e.g. 156"><?php echo esc_textarea((string)($o['sitemap_exclude'] ?? '')); ?></textarea>
                            <p class="description">One entry per line. Enter full URLs or numeric post/page IDs. These will be omitted from the sitemap.</p>
                        </td>
                    </tr>
                </table>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save Sitemap Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-sitemap-settings -->

            </form>

            <hr class="ab-zone-divider">

            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[block_ai_bots]" value="0">

                <div class="ab-zone-card ab-card-robots">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🤖</span> <?php esc_html_e( 'Robots.txt', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-robots" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('robots', '🤖 Robots.txt — What this all means', [
                        ['rec'=>'ℹ️ Info','name'=>'What is robots.txt?','desc'=>'A plain text file at yoursite.com/robots.txt that tells search engine crawlers which pages they are and aren\'t allowed to visit. It doesn\'t prevent indexing — it prevents crawling. Google respects it; malicious bots ignore it entirely.'],
                        ['rec'=>'ℹ️ Info','name'=>'Physical file warning','desc'=>'If a robots.txt file exists on disk, the web server serves it directly — bypassing WordPress and this plugin completely. You must rename or delete it to let the plugin take control. The plugin offers a one-click rename to robots.txt.bak.'],
                        ['rec'=>'⬜ Optional','name'=>'Block AI training bots','desc'=>'Adds Disallow: / rules for GPTBot, CCBot, Claude-Web, anthropic-ai and other AI training crawlers. Turn this ON if you don\'t want AI companies training their models on your content. Leave OFF if you want AI assistants to surface your content when users ask relevant questions.'],
                        ['rec'=>'✅ Recommended','name'=>'Custom robots.txt rules','desc'=>'The full content of your robots.txt file. The plugin automatically appends your sitemap URL and the AI bot blocklist (if enabled) — do not add those here manually. Changes take effect immediately on every request — there is no caching.'],
                        ['rec'=>'ℹ️ Info','name'=>'User-agent: Googlebot','desc'=>'Rules that apply specifically to Google\'s crawler. Googlebot respects these rules more strictly than other crawlers. Disallowing /wp-admin/, /wp-login.php and search pages stops Google wasting crawl budget on admin and junk pages.'],
                        ['rec'=>'ℹ️ Info','name'=>'User-agent: *','desc'=>'Rules that apply to all other crawlers not specifically named above. This is the catch-all for Bing, DuckDuckGo, and any other well-behaved search engine crawler.'],
                        ['rec'=>'ℹ️ Info','name'=>'Live preview','desc'=>'Shows exactly what search engines see when they fetch yoursite.com/robots.txt right now. If the sitemap URL appears at the bottom, everything is working correctly.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">

                <?php
                $physical_exists   = file_exists(ABSPATH . 'robots.txt');
                $physical_writable = $physical_exists && wp_is_writable(ABSPATH . 'robots.txt');
                $physical_contents = $physical_exists ? file_get_contents(ABSPATH . 'robots.txt') : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

                // Also check one level up in case WordPress is in a subdirectory
                $alt_path          = dirname(rtrim(ABSPATH, '/')) . '/robots.txt';
                $alt_exists        = file_exists($alt_path);
                ?>
                <div style="background:#f0f6fc;border:1px solid #c2d9f0;border-radius:4px;padding:10px 14px;margin:12px 20px;font-size:12px;font-family:monospace">
                    <strong>File detection:</strong><br>
                    ABSPATH: <code><?php echo esc_html(ABSPATH); ?></code><br>
                    Looking for: <code><?php echo esc_html(ABSPATH . 'robots.txt'); ?></code> → <?php echo wp_kses( $physical_exists ? '<span style="color:#1a7a34">found</span>' : '<span style="color:#1a7a34">not found</span>', array( 'span' => array( 'style' => array() ) ) ); ?><br>
                    <?php if (!$physical_exists): ?>
                    Also checking: <code><?php echo esc_html($alt_path); ?></code> → <?php echo wp_kses( $alt_exists ? '<span style="color:#e67e00">found here!</span>' : '<span style="color:#1a7a34">not found</span>', array( 'span' => array( 'style' => array() ) ) ); ?>
                    <?php endif; ?>
                </div>

                <?php
                // If file not at ABSPATH, try one level up (WordPress in subdirectory)
                $robots_path = ABSPATH . 'robots.txt';
                if (!$physical_exists && $alt_exists) {
                    $robots_path     = $alt_path;
                    $physical_exists = true;
                    $physical_writable = wp_is_writable($alt_path);
                    $physical_contents = file_get_contents($alt_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
                }
                ?>
                <?php if ($physical_exists): ?>
                <div class="ab-physical-robots-warn" id="ab-physical-robots-warn">
                    <div style="font-size:22px;flex-shrink:0">⚠️</div>
                    <div style="flex:1">
                        <strong>A physical robots.txt file exists on your server</strong><br>
                        WordPress (and this plugin) cannot control your robots.txt while a real file exists on disk — the web server serves the file directly, bypassing WordPress entirely. To let the plugin manage your robots.txt, the file needs to be renamed.<br><br>
                        <strong>Current file location:</strong> <code><?php echo esc_html($robots_path); ?></code>
                        &nbsp;·&nbsp; <strong>Writable:</strong> <?php echo wp_kses_post( $physical_writable ? '<span style="color:#1a7a34">Yes</span>' : '<span style="color:#c3372b">No</span>' ); ?><br><br>
                        <?php if ($physical_contents): ?>
                        <strong>Current file contents:</strong><br>
                        <pre style="background:#f6f7f7;border:1px solid #c3c4c7;border-radius:4px;padding:10px;font-size:12px;line-height:1.6;max-height:200px;overflow-y:auto;margin:6px 0 12px"><?php echo esc_html($physical_contents); ?></pre>
                        <?php endif; ?>
                        <strong>What happens when you click Rename:</strong> The file is renamed to <code>robots.txt.bak</code> in the same directory. WordPress then takes over and this plugin generates robots.txt dynamically on every request.<br><br>
                        <?php if ($physical_writable): ?>
                        <button type="button" class="button button-primary" id="ab-rename-robots-btn">
                            ✎ Rename robots.txt → robots.txt.bak
                        </button>
                        <span id="ab-rename-robots-status" style="margin-left:10px;font-size:13px"></span>
                        <?php else: ?>
                        <div style="background:#fef0f0;border:1px solid #f5bcbb;border-radius:4px;padding:10px;margin-top:4px">
                            <strong style="color:#c3372b">File is not writable</strong> — the web server does not have permission to rename this file.<br>
                            Fix via FTP or your host's file manager: right-click <code>robots.txt</code> → set permissions to <strong>644</strong>, then reload this page.<br><br>
                            Alternatively, rename the file manually via FTP: rename <code>robots.txt</code> to <code>robots.txt.bak</code> in your WordPress root.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div style="display:flex;gap:12px;align-items:flex-start;background:#edfaef;border:1px solid #1a7a34;border-radius:6px;padding:14px 18px;margin:12px 20px">
                    <div style="font-size:22px;flex-shrink:0">✅</div>
                    <div style="font-size:13px">
                        <strong>No physical robots.txt file detected</strong> — this plugin is managing your robots.txt dynamically. Search engines will see the content shown in the Live robots.txt preview below.<br><br>
                        <span style="color:#50575e">If you recently deleted or renamed the file manually, this is correct. The Live preview below shows exactly what Google will see.</span>
                    </div>
                </div>
                <?php endif; ?>

                <?php /* Live robots.txt preview */ ?>
                <div style="padding:16px 20px 4px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                        <div>
                            <strong style="font-size:13px">Live robots.txt</strong>
                            &nbsp;<a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank" style="font-size:12px">↗ view in browser</a>
                        </div>
                        <div style="display:flex;gap:6px">
                            <button type="button" class="button" style="font-size:11px;padding:2px 10px" id="ab-robots-live-copy">⎘ Copy</button>
                            <button type="button" class="button" id="ab-robots-refresh-btn" style="font-size:11px;padding:2px 10px">↻ Refresh</button>
                        </div>
                    </div>
                    <pre id="ab-robots-live-preview" style="background:#1a1a2e;color:#e0e0f0;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;padding:14px;border-radius:6px;max-height:320px;overflow-y:auto;margin:0;white-space:pre-wrap;word-break:break-word">Loading…</pre>
                </div>

                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Block AI training bots:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[block_ai_bots]" value="1" <?php checked((int)($o['block_ai_bots'] ?? 1), 1); ?>>
                            Block GPTBot, ChatGPT-User, CCBot, anthropic-ai, Claude-Web, FacebookBot, Bytespider, Applebot-Extended</label>
                            <p class="description">Adds <code>Disallow: /</code> for each AI training crawler. Appended automatically after your custom rules below.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cs-robots-txt">Custom robots.txt rules</label></th>
                        <td>
                            <textarea id="cs-robots-txt" name="<?php echo esc_attr(self::OPT); ?>[robots_txt]"
                                rows="16" style="width:100%"><?php echo esc_textarea((string)($o['robots_txt'] ?? self::default_robots_txt())); ?></textarea>
                            <p class="description">Full robots.txt content. The AI bot blocklist (if enabled) and your sitemap URL are appended automatically — do not add them here. Changes take effect immediately at <a href="<?php echo esc_url(home_url('/robots.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/robots.txt')); ?></a></p>
                            <div style="display:flex;justify-content:flex-end;gap:6px;margin-top:8px">
                                <button type="button" class="button" id="ab-robots-copy">⎘ Copy</button>
                                <button type="button" class="button" id="ab-robots-reset-btn">Reset to default</button>
                            </div>
                        </td>
                    </tr>
                </table>
                <div style="margin-top:16px;padding:0 20px;"><?php submit_button( __( 'Save Robots Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></div>
                </div>
                </div><!-- /ab-card-robots -->

            </form>

            <hr class="ab-zone-divider">

            <div class="ab-zone-card ab-card-sitemap-preview">
            <div class="ab-zone-header" style="justify-content:space-between;align-items:center">
                <span><span class="ab-zone-icon">🔍</span> <?php esc_html_e( 'Sitemap Preview', 'cloudscale-seo-ai-optimizer' ); ?></span>
                <div style="display:flex;gap:8px;align-items:center">
                    <?php $this->explain_btn('sitemappreview', '🔍 Sitemap Preview — How to use this', [
                        ['rec'=>'ℹ️ Info','name'=>'What this shows','desc'=>'A table of every URL that will appear in your sitemap.xml when Google crawls it. This is the live data — if a post appears here, it is in your sitemap. If it doesn\'t appear, Google won\'t find it via the sitemap.'],
                        ['rec'=>'ℹ️ Info','name'=>'Type badges','desc'=>'Each row shows the content type: Post (blog post), Page (WordPress page), Home (your homepage), Taxonomy (category/tag archive). Use this to verify the right content types are being included based on your Sitemap Settings.'],
                        ['rec'=>'ℹ️ Info','name'=>'Last Modified','desc'=>'The date the post was last updated. Google uses this to decide how often to re-crawl a page. Recently updated posts get re-crawled sooner. If a post shows an old date, consider updating it to signal freshness.'],
                        ['rec'=>'ℹ️ Info','name'=>'Pagination','desc'=>'Results are shown 200 at a time. Use Prev/Next to browse all your URLs. The count at the bottom right shows which URLs you\'re viewing out of the total.'],
                        ['rec'=>'ℹ️ Info','name'=>'View live sitemap','desc'=>'The link opens your actual sitemap.xml in a new tab — this is what Google sees. The index file lists all your sub-sitemaps (one per post type). Click through to see the raw XML.'],
                    ]); ?>
                    <button id="ab-sitemap-load"                        style="background:#f0b429;border:none;border-radius:6px;color:#1d2327;font-size:13px;font-weight:700;padding:7px 18px;cursor:pointer;letter-spacing:0.02em;box-shadow:0 2px 6px rgba(0,0,0,0.25);transition:background 0.15s">
                        ⬇ Load Preview
                    </button>
                    <button id="ab-sitemap-copy" class="button"
                        style="font-size:11px;padding:2px 10px;margin-left:6px">
                        ⎘ Copy URLs
                    </button>
                </div>
            </div>
            <div class="ab-zone-body" style="padding:16px 20px">
                <p style="color:#50575e;margin:0 0 14px;font-size:13px">Shows all URLs that will appear in your sitemap. Paginated at 200 rows — use Prev/Next to browse. Save settings before previewing.</p>
                <div id="ab-sitemap-preview-wrap">
                    <p style="color:#a7aaad;font-size:13px">Click <strong>Load Preview</strong> to fetch the current sitemap contents.</p>
                </div>
            </div>
            </div><!-- /ab-card-sitemap-preview -->

            <?php /* ── llms.txt Card ── */ ?>
            <div class="ab-zone-card ab-card-llms">
            <div class="ab-zone-header" style="justify-content:space-between">
                <span><span class="ab-zone-icon">🤖</span> llms.txt — LLM Crawler Guidance</span>
                <span style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-llms" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                    <?php $this->explain_btn('llms', '🤖 llms.txt — What this does', [
                    ['rec'=>'✅ Recommended','name'=>'What is llms.txt','desc'=>'llms.txt is an emerging standard (proposed 2024) that helps large language model crawlers like ChatGPT, Claude, and Perplexity understand your site\'s content structure. It\'s a plain-text markdown file served at yoursite.com/llms.txt listing your posts, pages, and descriptions — similar to what sitemap.xml does for traditional search engines, but optimised for AI indexing.'],
                    ['rec'=>'✅ Recommended','name'=>'Enable /llms.txt','desc'=>'Serves a dynamically generated llms.txt at yoursite.com/llms.txt. The file is built from your published posts and pages, using your AI-generated meta descriptions as the per-post summaries. Enable this if you want AI assistants and LLM-powered search engines to have an accurate, structured view of your site content.'],
                    ['rec'=>'ℹ️ Info','name'=>'What it contains','desc'=>'The file includes your site name, site description, author name and title, and a structured list of all published posts and pages with their URLs and meta descriptions. Posts with no meta description are listed without a summary — another reason to run Generate Missing first.'],
                    ['rec'=>'ℹ️ Info','name'=>'Preview','desc'=>'Click Load Preview to see exactly what the file currently contains. The preview reflects live data — if you generate new meta descriptions, reload the preview to see the updated content.'],
                ]); ?>
                    </span>
            </div>
            <div class="ab-zone-body" style="padding:20px 24px 24px">
                <form method="post" action="options.php">
                    <?php settings_fields('cs_seo_group'); ?>
                    <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[enable_llms_txt]" value="0">
                    <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[_partial]" value="1">
                    <table class="form-table" role="presentation" style="margin-top:0">
                        <tr>
                            <th style="width:200px"><?php esc_html_e( 'Enable /llms.txt:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[enable_llms_txt]" value="1" <?php checked((int)($o['enable_llms_txt'] ?? 0), 1); ?>>
                                    Serve <code>llms.txt</code> at <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank"><?php echo esc_html(home_url('/llms.txt')); ?></a>
                                </label>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save llms.txt Settings'); ?>
                </form>

                <div style="margin-top:8px;border-top:1px solid #f0f0f0;padding-top:16px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                        <button id="ab-llms-load" class="button" style="background:#1a4a8a;color:#fff;border-color:#143a6e">🔍 Load Preview</button>
                        <button id="ab-llms-copy" class="button" style="font-size:11px;padding:2px 10px">⎘ Copy</button>
                        <?php if ((int)($o['enable_llms_txt'] ?? 0)): ?>
                        <a href="<?php echo esc_url(home_url('/llms.txt')); ?>" target="_blank" class="button">↗ View Live File</a>
                        <?php endif; ?>
                    </div>
                    <div id="ab-llms-preview-wrap">
                        <p style="color:#a7aaad;font-size:13px">Click <strong>Load Preview</strong> to see what LLM crawlers will receive.</p>
                    </div>
                </div>
            </div>
            </div><!-- /ab-card-llms -->

            <?php /* ── HTTPS Fix Card ── */ ?>
            <div class="ab-zone-card ab-card-https">
            <div class="ab-zone-header" style="justify-content:space-between">
                <span><span class="ab-zone-icon">🔒</span> Mixed Content Fix — HTTP → HTTPS</span>
                <span style="display:flex;align-items:center;gap:8px;">
                <?php $this->explain_btn('https', '🔒 Mixed Content Fix — How it works', [
                    ['rec'=>'ℹ️ Info',        'name'=>'What is mixed content?', 'desc'=>'Mixed content is when an HTTPS page loads resources (images, scripts, stylesheets) over HTTP. Browsers block or warn about these, causing broken images, console errors, and security warnings. It most commonly happens when a site migrates from HTTP to HTTPS but old URLs remain in the database.'],
                    ['rec'=>'ℹ️ Info',        'name'=>'What Scan does',         'desc'=>'Counts http:// references across your posts, pages, metadata, options, and comments without changing anything. Run this first to understand the scope before committing to a fix.'],
                    ['rec'=>'⚠️ Caution',     'name'=>'What Fix does',          'desc'=>'Replaces all found http:// references with https://. This is a bulk database update — take a backup before running. The operation is not reversible from within this tool. It covers post_content, post_excerpt, postmeta, options, and comments.'],
                    ['rec'=>'ℹ️ Info',        'name'=>'External links',         'desc'=>'The fix also updates external URLs in your content from http to https where present. This is generally safe but worth reviewing if you link to sites that may not support HTTPS.'],
                ]); ?>
                <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-https" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                </span>
            </div>
            <div class="ab-zone-body" style="padding:20px 24px 24px">
                <p style="color:#50575e;font-size:13px;margin:0 0 16px">Scans your database for assets and links still using <code>http://</code> and replaces them with <code>https://</code>. Fixes posts, pages, metadata, options, and comments in one operation.</p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
                    <button type="button" class="button" id="ab-https-scan-btn" style="background:#b45309;color:#fff;border-color:#92400e;font-weight:600">
                        🔍 Scan for HTTP references
                    </button>
                    <button type="button" class="button" id="ab-https-fix-btn" style="display:none;background:#7a1a1a;color:#fff;border-color:#5a0e0e;font-weight:600">
                        🔧 Fix all HTTP → HTTPS
                    </button>
                    <span id="ab-https-status" style="font-size:13px;color:#50575e"></span>
                </div>
                <div id="ab-https-results"></div>
            </div>
            </div><!-- /ab-card-https -->
            <?php ob_start(); ?>
            (function() {
                var _ajax  = csSeoAdmin.ajaxUrl;
                var _nonce = csSeoAdmin.nonce;
                var scanBtn   = document.getElementById('ab-https-scan-btn');
                var fixBtn    = document.getElementById('ab-https-fix-btn');
                var statusEl  = document.getElementById('ab-https-status');
                var resultsEl = document.getElementById('ab-https-results');

                var th  = 'padding:6px 12px;border-bottom:2px solid #8c8f94;background:#f0f0f1;text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:0.04em;color:#1d2327;font-weight:700;position:sticky;top:0';
                var td  = 'padding:6px 10px;border-bottom:1px solid #dcdcde;font-family:monospace;font-size:11px;color:#1d2327;word-break:break-all';

                function setStatus(msg, color) {
                    statusEl.textContent = msg;
                    statusEl.style.color = color || '#50575e';
                }
                function esc(s) {
                    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                }
                function getCheckedDomains() {
                    return Array.from(document.querySelectorAll('.ab-https-domain-cb:checked')).map(function(cb){ return cb.value; });
                }

                // Safe fetch wrapper — always reads raw text first so a PHP fatal
                // (which returns HTML, not JSON) shows the actual error message
                // rather than a useless "Unexpected token '<'" SyntaxError.
                function safeFetch(url, opts) {
                    return fetch(url, opts).then(function(r) {
                        return r.text().then(function(text) {
                            try {
                                return JSON.parse(text);
                            } catch (e) {
                                // Strip HTML tags to surface the plain-text PHP message
                                var plain = text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 400);
                                throw new Error('Server returned non-JSON response:\n' + plain);
                            }
                        });
                    });
                }

                if (scanBtn) scanBtn.addEventListener('click', function() {
                    scanBtn.disabled = true;
                    fixBtn.style.display = 'none';
                    setStatus('Scanning…', '#50575e');
                    resultsEl.innerHTML = '';
                    safeFetch(_ajax, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_https_scan&nonce=' + encodeURIComponent(_nonce)
                    })
                    .then(function(data) {
                        scanBtn.disabled = false;

                        if (!data.success) {
                            var msg = typeof data.data === 'string' ? data.data : (data.data && data.data.message) ? data.data.message : JSON.stringify(data.data);
                            setStatus('Scan failed: ' + msg, '#c3372b');
                            return;
                        }

                        var d = data.data;
                        if (d.total === 0) {
                            setStatus('\u2705 No HTTP references found \u2014 your site is clean!', '#1a7a34');
                            return;
                        }
                        setStatus('', '');

                        // Summary by table/column
                        var summaryRows = d.counts.map(function(c) {
                            return '<tr><td style="' + td + '">' + esc(c.table) + '</td>' +
                                '<td style="' + td + '">' + esc(c.column) + '</td>' +
                                '<td style="' + td + ';font-weight:700;color:#b45309;text-align:right">' + c.count + '</td></tr>';
                        }).join('');

                        // Per-domain rows — behaviour differs by domain type
                        var domainRows = Object.entries(d.domain_meta).map(function(entry) {
                            var domain = entry[0], meta = entry[1];
                            var uid = 'ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-');

                            // Build collapsible URL sample list
                            var urls = meta.urls || [];
                            var sampleHtml = '';
                            if (urls.length > 0) {
                                var initialShow = 3;
                                var makeUrlDiv = function(u) {
                                    return '<div style="color:#555;font-size:10px;margin-left:22px;margin-top:1px;word-break:break-all;font-family:monospace">' + esc(u.url) +
                                        ' <span style="color:#8080b0;font-size:9px">(' + esc(u.table) + '.' + esc(u.column) + ')</span></div>';
                                };
                                var visibleUrls = urls.slice(0, initialShow).map(makeUrlDiv).join('');
                                var hiddenUrls  = urls.length > initialShow ? urls.slice(initialShow).map(makeUrlDiv).join('') : '';
                                var showMoreBtn = hiddenUrls
                                    ? '<button type="button" onclick="var h=document.getElementById(\'' + uid + '\');h.style.display=h.style.display===\'none\'?\'block\':\'none\';this.textContent=h.style.display===\'none\'?\'\u25b6 Show all ' + urls.length + ' URLs\':\'\u25b2 Hide\'" style="background:none;border:none;color:#2271b1;font-size:10px;cursor:pointer;padding:2px 0 0 22px;margin:0">\u25b6 Show all ' + urls.length + ' URLs</button>'
                                    : '';
                                sampleHtml = visibleUrls +
                                    (hiddenUrls ? '<div id="' + uid + '" style="display:none">' + hiddenUrls + '</div>' + showMoreBtn : '');
                                if (meta.count > urls.length) {
                                    sampleHtml += '<div style="color:#8080b0;font-size:10px;margin-left:22px;margin-top:2px">\u2026 and ' + (meta.count - urls.length) + ' more rows in the database (showing first ' + urls.length + ' samples)</div>';
                                }
                            }

                            var domainBadge = '<code style="background:#f0f0f1;padding:1px 5px;border-radius:3px;font-size:12px">' + esc(domain) + '</code>' +
                                '<span style="color:#888;font-size:11px;margin-left:6px">' + meta.count + ' row' + (meta.count !== 1 ? 's' : '') + ' in DB</span>';

                            // --- IP address: cannot be fixed, offer Remove action ---
                            if (meta.is_ip) {
                                return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1" id="ab-domain-row-' + uid + '">' +
                                    '<div style="display:flex;align-items:flex-start;gap:8px">' +
                                    '<span style="margin-top:2px;font-size:14px">\u26d4</span>' +
                                    '<div style="flex:1">' +
                                    domainBadge +
                                    '<span style="color:#c3372b;font-size:10px;margin-left:6px">\u26a0 IP address \u2014 cannot have an SSL cert. These URLs must be removed or replaced, not flipped to HTTPS.</span>' +
                                    '<br><button type="button" data-domain="' + esc(domain) + '" class="ab-https-remove-ip button button-small" style="margin-top:5px;background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px">\u{1F5D1} Remove these ' + meta.count + ' row' + (meta.count !== 1 ? 's' : '') + '</button>' +
                                    '</div></div>' +
                                    sampleHtml + '</div>';
                            }

                            // --- Spam (comment-only domain): offer Delete comments action ---
                            if (meta.is_spam) {
                                return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1" id="ab-domain-row-' + uid + '">' +
                                    '<div style="display:flex;align-items:flex-start;gap:8px">' +
                                    '<span style="margin-top:2px;font-size:14px">\u{1F6AB}</span>' +
                                    '<div style="flex:1">' +
                                    domainBadge +
                                    '<span style="color:#c3372b;font-size:10px;margin-left:6px">\u26a0 spam comment domain \u2014 delete the comments rather than fixing the URL</span>' +
                                    '<br><button type="button" data-domain="' + esc(domain) + '" class="ab-https-delete-spam button button-small" style="margin-top:5px;background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px">\u{1F5D1} Delete comments from ' + esc(domain) + '</button>' +
                                    '</div></div>' +
                                    sampleHtml + '</div>';
                            }

                            // --- Normal fixable domain ---
                            var checked = ' checked';
                            if (domain.match(/example\.|yoursite\.|placeholder/i)) {
                                checked = '';  // placeholder — opt-out by default
                            }
                            var ownBadge = meta.is_own ? '<span style="color:#1a7a34;font-size:10px;margin-left:6px">\u2713 your domain</span>' : '';

                            // Core URL options (siteurl / home) that appear in wp_options
                            var coreOpts = meta.core_url_options || [];
                            var overridden = meta.overridden_by_wpconfig || [];
                            var coreWarn = '';
                            if (overridden.length > 0) {
                                // wp-config.php has WP_HOME/WP_SITEURL defined as http://
                                // Fixing the DB row is pointless — the constant overwrites it on every request
                                coreWarn = '<div style="margin-top:6px;padding:8px 10px;background:#fff8e1;border:1px solid #f0c040;border-radius:3px;font-size:11px;color:#5a4000">' +
                                    '\u26a0 <strong>This row keeps reverting because <code>WP_' + overridden.map(function(o){return o.toUpperCase();}).join('</code> / <code>WP_') + '</code> ' +
                                    (overridden.length === 1 ? 'is' : 'are') + ' hardcoded in <code>wp-config.php</code>.</strong><br>' +
                                    'Database fixes are overwritten every time WordPress loads. To permanently fix this, edit <code>wp-config.php</code> and change the constant' +
                                    (overridden.length > 1 ? 's' : '') + ' to use <code>https://</code>:<br>' +
                                    '<code style="display:block;margin-top:4px;background:#f5f5f5;padding:4px 6px;border-radius:2px">' +
                                    overridden.map(function(o) {
                                        return "define( 'WP_" + o.toUpperCase() + "', 'https://" + esc(domain) + "' );";
                                    }).join('<br>') + '</code></div>';
                                checked = '';  // don't offer the DB fix when it won't stick
                            } else if (coreOpts.length > 0) {
                                // In wp_options but no wp-config override — DB fix will work,
                                // but warn that this is the core site URL
                                coreWarn = '<div style="margin-top:5px;font-size:11px;color:#5a4000">' +
                                    '\u2139 This appears in the core WordPress <code>' + coreOpts.join('</code> / <code>') + '</code> option' +
                                    (coreOpts.length > 1 ? 's' : '') + '. Fixing it here will work, but also update <code>wp-config.php</code> if those constants are defined there.</div>';
                            }

                            return '<div style="padding:8px 0;border-bottom:1px solid #f0f0f1">' +
                                '<label style="display:flex;align-items:flex-start;gap:6px;cursor:pointer">' +
                                '<input type="checkbox" class="ab-https-domain-cb" value="' + esc(domain) + '"' + checked + ' style="margin-top:3px;flex-shrink:0">' +
                                '<span>' + domainBadge + ownBadge + '</span></label>' +
                                coreWarn +
                                sampleHtml + '</div>';
                        }).join('');

                        resultsEl.innerHTML =
                            '<p style="font-size:13px;font-weight:600;color:#b45309;margin:0 0 10px">Found ' + d.total + ' row' + (d.total!==1?'s':'') + ' with HTTP references</p>' +
                            '<table style="width:100%;border-collapse:collapse;margin-bottom:14px">' +
                            '<thead><tr><th style="' + th + '">Table</th><th style="' + th + '">Column</th><th style="' + th + ';text-align:right">Rows</th></tr></thead>' +
                            '<tbody>' + summaryRows + '</tbody></table>' +
                            '<p style="font-size:12px;font-weight:600;color:#1d2327;margin:0 0 6px">Select domains to fix:</p>' +
                            '<div style="background:#fafafa;border:1px solid #dcdcde;border-radius:4px;padding:8px 12px;margin-bottom:10px">' + domainRows + '</div>' +
                            '<p style="font-size:11px;color:#888;margin:0">Serialized data (theme settings, widget options) will be re-serialized safely to preserve byte counts.</p>';

                        // Wire up Delete spam buttons
                        resultsEl.querySelectorAll('.ab-https-delete-spam').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var domain = btn.dataset.domain;
                                if (!confirm('Permanently delete all comments from ' + domain + '?\n\nThis cannot be undone.')) return;
                                btn.disabled = true;
                                btn.textContent = 'Deleting\u2026';
                                safeFetch(_ajax, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=cs_seo_https_delete&nonce=' + encodeURIComponent(_nonce) + '&domain=' + encodeURIComponent(domain)
                                }).then(function(r) {
                                    if (r.success) {
                                        var row = document.getElementById('ab-domain-row-ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-'));
                                        if (row) {
                                            row.innerHTML = '<div style="color:#1a7a34;font-size:12px;padding:4px 0">\u2705 Deleted ' + esc(r.data.deleted) + ' comment' + (r.data.deleted !== 1 ? 's' : '') + ' from ' + esc(domain) + '</div>';
                                        }
                                    } else {
                                        btn.disabled = false;
                                        btn.textContent = '\u{1F5D1} Delete comments from ' + domain;
                                        alert('Delete failed: ' + (r.data || 'unknown error'));
                                    }
                                }).catch(function(e) {
                                    btn.disabled = false;
                                    alert('Delete error: ' + e.message);
                                });
                            });
                        });

                        // Wire up Remove IP buttons
                        resultsEl.querySelectorAll('.ab-https-remove-ip').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                var domain = btn.dataset.domain;
                                if (!confirm('Permanently delete all database rows containing ' + domain + '?\n\nThis cannot be undone. Ensure you have a backup.')) return;
                                btn.disabled = true;
                                btn.textContent = 'Deleting\u2026';
                                safeFetch(_ajax, {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=cs_seo_https_delete&nonce=' + encodeURIComponent(_nonce) + '&domain=' + encodeURIComponent(domain)
                                }).then(function(r) {
                                    if (r.success) {
                                        var row = document.getElementById('ab-domain-row-ab-https-urls-' + domain.replace(/[^a-z0-9]/gi, '-'));
                                        if (row) {
                                            row.innerHTML = '<div style="color:#1a7a34;font-size:12px;padding:4px 0">\u2705 Deleted ' + esc(r.data.deleted) + ' item' + (r.data.deleted !== 1 ? 's' : '') + ' containing ' + esc(domain) + '</div>';
                                        }
                                    } else {
                                        btn.disabled = false;
                                        btn.textContent = '\u{1F5D1} Remove these rows';
                                        alert('Delete failed: ' + (r.data || 'unknown error'));
                                    }
                                }).catch(function(e) {
                                    btn.disabled = false;
                                    alert('Delete error: ' + e.message);
                                });
                            });
                        });

                        fixBtn.style.display = '';
                    })
                    .catch(function(e) {
                        scanBtn.disabled = false;
                        setStatus('', '');
                        resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Scan error:\n' + esc(e.message) + '</div>';
                    });
                });

                if (fixBtn) fixBtn.addEventListener('click', function() {
                    var domains = getCheckedDomains();
                    if (!domains.length) { setStatus('Select at least one domain to fix.', '#c3372b'); return; }
                    if (!confirm('Replace http:// with https:// for ' + domains.length + ' selected domain' + (domains.length !== 1 ? 's' : '') + '.\n\nEnsure you have a recent database backup before proceeding.')) return;
                    fixBtn.disabled = true;
                    scanBtn.disabled = true;
                    setStatus('Fixing — this may take a moment…', '#50575e');
                    resultsEl.innerHTML = '';
                    safeFetch(_ajax, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=cs_seo_https_fix&nonce=' + encodeURIComponent(_nonce) + '&domains=' + encodeURIComponent(domains.join(','))
                    })
                    .then(function(data) {
                        fixBtn.disabled = false;
                        scanBtn.disabled = false;
                        if (!data.success) {
                            setStatus('', '');
                            resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Fix error:\n' + esc(data.data) + '</div>';
                            return;
                        }
                        var d = data.data;
                        fixBtn.style.display = 'none';
                        setStatus('✅ Fixed ' + d.fixed + ' row' + (d.fixed!==1?'s':''), '#1a7a34');

                        if (!d.changes || d.changes.length === 0) {
                            resultsEl.innerHTML = '<p style="color:#50575e;font-size:13px">No changes recorded.</p>';
                            return;
                        }
                        var changeRows = d.changes.map(function(c) {
                            return '<tr>' +
                                '<td style="' + td + '">' + esc(c.table) + '</td>' +
                                '<td style="' + td + '">' + esc(c.column) + '</td>' +
                                '<td style="' + td + '">' + esc(c.id) + '</td>' +
                                '<td style="' + td + ';color:#c3372b">' + esc(c.from) + '</td>' +
                                '<td style="' + td + ';color:#1a7a34">' + esc(c.to) + '</td>' +
                                '</tr>';
                        }).join('');
                        resultsEl.innerHTML =
                            '<p style="font-size:13px;font-weight:600;color:#1a7a34;margin:0 0 8px">✅ ' + d.changes.length + ' URL' + (d.changes.length!==1?'s':'') + ' updated across ' + d.fixed + ' row' + (d.fixed!==1?'s':'') + ':</p>' +
                            '<div style="max-height:320px;overflow-y:auto;border:1px solid #dcdcde;border-radius:4px">' +
                            '<table style="width:100%;border-collapse:collapse">' +
                            '<thead><tr>' +
                            '<th style="' + th + '">Table</th>' +
                            '<th style="' + th + '">Column</th>' +
                            '<th style="' + th + '">ID</th>' +
                            '<th style="' + th + '">From</th>' +
                            '<th style="' + th + '">To</th>' +
                            '</tr></thead><tbody>' + changeRows + '</tbody></table></div>';
                    })
                    .catch(function(e) {
                        fixBtn.disabled = false;
                        scanBtn.disabled = false;
                        setStatus('', '');
                        resultsEl.innerHTML = '<div style="color:#c3372b;background:#fef0f0;border:1px solid #f5bcbb;padding:12px 14px;border-radius:4px;font-size:12px;font-family:monospace;white-space:pre-wrap">Fix error:\n' + esc(e.message) + '</div>';
                    });
                });
            })();
            <?php wp_add_inline_script('cs-seo-admin-js', ob_get_clean()); ?>

        <?php /* ══════════════════ REDIRECTS (bottom of Sitemap pane) ══════════════════ */ ?>
        <div style="margin-top:32px">
            <?php $this->render_redirects_tab(); ?>
        </div>

        </div><!-- /ab-pane-sitemap -->

        <div class="ab-pane" id="ab-pane-perf">

            <div class="ab-zone-card ab-card-fonts" style="margin-top:0">
                <div class="ab-zone-header" style="background:#0066cc;justify-content:space-between">
                    <span><span class="ab-zone-icon">🔤</span> <?php esc_html_e( 'Font-Display Optimization', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-fonts" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('perf', '⚡ Performance Tab — What each feature does', [
                        ['rec'=>'✅ Recommended','name'=>'Font-Display: Swap','desc'=>'Adds font-display: swap to your @font-face rules. This tells browsers to show text immediately using a fallback font, then swap in the custom font once loaded. Eliminates the "Flash of Invisible Text" (FOIT) and dramatically improves Largest Contentful Paint (LCP) scores. Typical savings: 500ms–2s.'],
                        ['rec'=>'✅ Recommended','name'=>'Font Metric Overrides','desc'=>'Adds size-adjust, ascent-override, and descent-override properties to match your web font metrics to the fallback font. This prevents layout shift (CLS) when the custom font loads. Without this, text may jump or reflow as fonts swap.'],
                        ['rec'=>'⬜ Optional','name'=>'Defer Font CSS Loading','desc'=>'Changes font stylesheets to load with media="print" and swap to media="all" after page load. This prevents font CSS from blocking initial render. Enable this for maximum LCP improvement, but test thoroughly — some themes may show a brief flash of unstyled text.'],
                        ['rec'=>'⬜ Optional','name'=>'Auto-Download CDN Fonts','desc'=>'Detects Google Fonts loaded from CDN (fonts.googleapis.com) and downloads them to your local server. Local fonts load faster and eliminate third-party requests. Also improves privacy compliance (GDPR) by keeping font requests on your domain.'],
                        ['rec'=>'✅ Recommended','name'=>'Defer Render-Blocking JavaScript','desc'=>'Adds the defer attribute to JavaScript files, allowing them to download in parallel and execute after HTML parsing. This prevents scripts from blocking page rendering. Some scripts (jQuery, payment widgets) should be excluded — use the exclusions field.'],
                        ['rec'=>'⬜ Optional','name'=>'HTML/CSS/JS Minification','desc'=>'Removes whitespace, comments, and unnecessary characters from your HTML output. Reduces page size by 5–15% with zero visual change. Safe and conservative — protects pre-formatted content, JSON-LD, and textareas.'],
                        ['rec'=>'✅ Recommended','name'=>'HTTPS Mixed Content Scanner','desc'=>'Scans your database for http:// references to your own domain that should be https://. Mixed content triggers browser warnings and hurts SEO. One-click fix replaces all instances across posts, pages, meta, options, and comments.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">
                    <p style="padding:0 20px; margin-top:12px; font-size:13px; color:#555; line-height:1.6;">
                        <strong>font-display: swap</strong> ensures text is visible while web fonts load. This eliminates the "Flash of Invisible Text" (FOIT) and improves perceived performance.
                    </p>
                    
                    <div style="padding:0 20px; margin:8px 0 0 0; font-size:13px; color:#666; border-top:1px solid #e5e5e5; padding-top:12px;">
                        <strong>ℹ How font optimization works:</strong><br>
                        1. Click "Scan CSS Files" to analyze your fonts<br>
                        2. Click "Auto-Fix All" to apply optimizations:<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• Adds <code>font-display: swap</code> to missing fonts<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• Adds metric overrides to prevent layout shift<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;• If defer is enabled: defers CSS loading (media="print")<br>
                        3. Creates backup (you can undo anytime)<br>
                        <strong>No changes happen until you click "Auto-Fix All"</strong>
                    </div>
                    
                    <div style="padding:0 20px; margin:16px 0; display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="button" id="ab-font-scan-btn" style="background:#0066cc;border-color:#004d99;color:#fff;font-weight:600">
                            🔍 Scan CSS Files
                        </button>
                        <button type="button" class="button" id="ab-font-download-btn" style="background:#1a7a34;border-color:#145a27;color:#fff;font-weight:600">
                            ⬇️ Auto-Download CDN Fonts
                        </button>
                        <button type="button" class="button" id="ab-font-fix-btn" style="background:#7c3aed;border-color:#5b21b6;color:#fff;font-weight:600">
                            ✨ Auto-Fix All
                        </button>
                        <button type="button" class="button" id="ab-font-clear-btn" style="background:#d946a6;border-color:#b5348a;color:#fff;font-weight:600">
                            🧹 Clear Console
                        </button>
                    </div>
                    
                    <div id="ab-font-console" class="ab-log" style="margin:16px 20px; min-height:120px; max-height:250px; overflow-y:auto; background:#1a1a2e; border:1px solid #333; border-radius:4px; padding:12px; font-family:monospace; font-size:13px; line-height:1.6; color:#e0e0e0; display:block;">
                        <div style="text-align:center; color:#888; padding:12px;">Click "Scan CSS Files" to analyze your fonts...</div>
                    </div>
                </div>
            </div>

            <div class="ab-zone-card ab-card-render" style="margin-top:16px">
                <form method="post" action="options.php">
                <?php settings_fields('cs_seo_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[defer_js]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[minify_html]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::OPT); ?>[defer_fonts]" value="0">
                <div class="ab-zone-header" style="background:#7c3aed;justify-content:space-between">
                    <span><span class="ab-zone-icon">🚀</span> <?php esc_html_e( 'Render &amp; Minification', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                    <?php $this->explain_btn('render', '🚀 Render & Minification — What each option does', [
                        ['rec'=>'⬜ Optional',   'name'=>'Defer JavaScript',   'desc'=>'Adds defer to all script tags, preventing JavaScript from blocking page rendering. Text and images load first; scripts execute after. Safe for most themes and plugins. Disable if your site breaks — some scripts must run before content renders (e.g. anti-flicker scripts for A/B testing tools).'],
                        ['rec'=>'⬜ Optional',   'name'=>'Minify HTML',        'desc'=>'Strips whitespace, comments, and redundant characters from HTML output. Typical savings of 5–15% page size. Purely cosmetic — does not change content or break functionality. The minified HTML is served directly; no files are written to disk.'],
                        ['rec'=>'⬜ Optional',   'name'=>'Defer web fonts',    'desc'=>'Defers font stylesheet loading so text renders immediately using fallback fonts, then swaps once the font file arrives. Eliminates render-blocking from Google Fonts and similar CDN-hosted fonts. Works in combination with Font-Display: Swap in the Font Optimizer above.'],
                    ]); ?>
                    <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-render" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:16px 20px">

                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Defer Font CSS Loading
                            <span>Loads font stylesheets as print, swaps to all after page load. Prevents render-blocking.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[defer_fonts]" value="1" <?php checked((int)($o['defer_fonts'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>

                    <div style="margin-top:16px;border-top:1px solid #e5e5e5;padding-top:16px">
                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Defer render-blocking JavaScript
                            <span>Downloads JS in parallel, executes after HTML parsing. Fast PageSpeed win.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[defer_js]" value="1" id="ab-defer-toggle" <?php checked((int)($o['defer_js'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>

                    <div id="ab-defer-excludes-wrap" style="margin-top:12px;<?php echo esc_attr( (int)($o['defer_js'] ?? 0) ? '' : 'display:none' ); ?>">
                        <label style="font-weight:600;display:block;margin-bottom:4px">Defer exclusions (one handle or URL substring per line):</label>
                        <textarea class="large-text" rows="4"
                            name="<?php echo esc_attr(self::OPT); ?>[defer_js_excludes]"
                            placeholder="jquery&#10;woocommerce&#10;my-critical-script"><?php echo esc_textarea((string)($o['defer_js_excludes'] ?? '')); ?></textarea>
                        <p class="description">Scripts whose handle name or URL contains any of these strings will be excluded from deferring. jQuery and a set of other commonly problematic scripts are excluded automatically — you only need to add scripts that are still breaking your site after enabling defer.</p>
                    </div>
                    <?php /* defer-toggle listener moved to admin_enqueue_assets() */ ?>
                    </div>

                    <div style="margin-top:16px;border-top:1px solid #e5e5e5;padding-top:16px">
                    <div class="ab-toggle-row">
                        <div class="ab-toggle-label">
                            Minify HTML output
                            <span>Strips whitespace &amp; comments. Minifies inline CSS and JS. 5–15% size reduction.</span>
                        </div>
                        <label class="ab-toggle-switch">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[minify_html]" value="1" <?php checked((int)($o['minify_html'] ?? 0), 1); ?>>
                            <span class="ab-toggle-slider"></span>
                        </label>
                    </div>
                    </div>

                    <?php submit_button( __( 'Save Performance Settings', 'cloudscale-seo-ai-optimizer' ) ); ?>
                </div>
                </form>
            </div>

        </div><!-- /ab-pane-perf -->

        <?php /* ══════════════════ SCHEDULED BATCH PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-batch">
            <form method="post" action="options.php">
                <?php settings_fields('cs_seo_ai_group'); ?>
                <input type="hidden" name="<?php echo esc_attr(self::AI_OPT); ?>[schedule_enabled]"   value="0">
                <input type="hidden" name="<?php echo esc_attr(self::AI_OPT); ?>[auto_run_enabled]"   value="0">
                <input type="hidden" name="<?php echo esc_attr(self::AI_OPT); ?>[auto_run_on_update]" value="0">
                <input type="hidden" name="<?php echo esc_attr(self::AI_OPT); ?>[overwrite]"          value="0">

                <div class="ab-zone-card ab-card-schedule">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">⏱</span> <?php esc_html_e( 'Scheduled Batch Generation', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-schedule" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('schedule', '⏱ Scheduled Batch — How this works', [
                        ['rec'=>'ℹ️ Info','name'=>'What this does','desc'=>'Automatically runs the AI meta description generator on a schedule — no need to manually click Generate Missing. The batch only processes posts that don\'t yet have a description, so it never overwrites existing ones.'],
                        ['rec'=>'⬜ Optional','name'=>'Enable schedule','desc'=>'Turns the scheduled batch on or off. When enabled, the batch runs automatically at midnight (server time) on the days you select. When disabled, no automatic generation happens — you can still run it manually from the Optimise SEO tab.'],
                        ['rec'=>'⬜ Optional','name'=>'Days of the week','desc'=>'Choose which days the batch runs. For a high-volume blog that publishes daily, tick every day. For a weekly blog, once or twice a week is sufficient. The batch only does work if there are unprocessed posts — if everything is up to date, it completes instantly.'],
                        ['rec'=>'ℹ️ Info','name'=>'Midnight server time','desc'=>'The batch runs at midnight based on your server\'s timezone, not your local time. Check your WordPress timezone setting under Settings → General if the timing seems off.'],
                        ['rec'=>'ℹ️ Info','name'=>'API costs','desc'=>'Each description generated makes one API call to your chosen provider. For Anthropic, Claude Haiku costs roughly $0.001–$0.003 per post; for Google, Gemini Flash is similarly priced. A full run across 100 unprocessed posts typically costs $0.10–$0.30 with a fast/cheap model.'],
                    ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body">
                <p style="padding:12px 20px 0;color:#50575e;margin:0">The batch runs automatically on selected days at midnight (server time). <strong style="color:#6b3fa0">It only processes posts that do not yet have a meta description</strong> — it never overwrites existing ones.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><?php esc_html_e( 'Enable schedule:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    id="cs-sched-enabled"
                                    name="<?php echo esc_attr(self::AI_OPT); ?>[schedule_enabled]"
                                    value="1" <?php checked((int)($ai['schedule_enabled'] ?? 0), 1); ?>
                                   >
                                Enable automatic scheduled batch
                            </label>
                            <p class="description">Requires an Anthropic API key saved in the Optimise SEO tab → AI Meta Writer section.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Run on these days:', 'cloudscale-seo-ai-optimizer' ); ?></th>
                        <td>
                            <div style="display:flex;gap:16px;flex-wrap:wrap" id="cs-sched-days">
                            <?php
                            $day_labels  = ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'];
                            $sched_days  = (array)($ai['schedule_days'] ?? []);
                            $sched_on    = (int)($ai['schedule_enabled'] ?? 0);
                            foreach ($day_labels as $val => $label): ?>
                                <label style="<?php echo esc_attr( $sched_on ? '' : 'opacity:0.4' ); ?>">
                                    <input type="checkbox"
                                        class="cs-sched-day"
                                        name="<?php echo esc_attr(self::AI_OPT); ?>[schedule_days][]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked(in_array($val, $sched_days, true), true); ?>
                                        <?php echo esc_attr( $sched_on ? '' : 'disabled' ); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            </div>
                            <?php /* csToggleSchedDays moved to admin_enqueue_assets() */ ?>
                            <p class="description" style="margin-top:10px">
                                <?php
                                $cron_next = wp_next_scheduled('cs_seo_daily_batch');
                                if ($cron_next && !empty($sched_days)) {
                                    $day_map = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>0];
                                    $target_dow = array_map(fn($d) => $day_map[$d] ?? -1, $sched_days);
                                    $found = null;
                                    for ($i = 0; $i <= 7; $i++) {
                                        $ts  = strtotime("midnight +{$i} days");
                                        $dow = (int) gmdate('w', $ts);
                                        if (in_array($dow, $target_dow, true)) {
                                            $found = $ts;
                                            break;
                                        }
                                    }
                                    if ($found) {
                                        echo wp_kses( 'Next scheduled run: <strong>' . esc_html( gmdate( 'D d M Y H:i:s', $found ) ) . '</strong> (server time)', array( 'strong' => array() ) );
                                    } else {
                                        esc_html_e( 'No matching days selected.', 'cloudscale-seo-ai-optimizer' );
                                    }
                                } elseif ($cron_next && (int)($ai['schedule_enabled'] ?? 0)) {
                                    echo wp_kses( '<span style="color:#c3372b">No days selected — tick at least one day above.</span>', array( 'span' => array( 'style' => array() ) ) );
                                } elseif ((int)($ai['schedule_enabled'] ?? 0)) {
                                    echo wp_kses( '<span style="color:#c3372b">No cron event found — try saving settings again.</span>', array( 'span' => array( 'style' => array() ) ) );
                                } else {
                                    esc_html_e( 'Schedule is disabled.', 'cloudscale-seo-ai-optimizer' );
                                } ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th></th>
                        <td style="padding-top:4px;"><?php submit_button( __( 'Save Schedule Settings', 'cloudscale-seo-ai-optimizer' ), 'primary', 'submit', false ); ?></td>
                    </tr>
                </table>
                </div><!-- /ab-zone-body -->
                </div><!-- /ab-card-schedule -->
            </form>

            <hr class="ab-zone-divider">

            <div class="ab-zone-card ab-card-lastrun">
            <div class="ab-zone-header" style="justify-content:space-between">
                <span><span class="ab-zone-icon">📋</span> <?php esc_html_e( 'Batch Run History (28 days)', 'cloudscale-seo-ai-optimizer' ); ?></span>
                <span style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-lastrun" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                    <?php $this->explain_btn('lastrun', '📋 Batch Run History — Reading the results', [
                    ['rec'=>'ℹ️ Info','name'=>'Run history','desc'=>'Shows all batch runs from the last 28 days, newest first. Each entry shows when the batch ran, how many posts were processed, and any errors. Entries older than 28 days are automatically pruned.'],
                    ['rec'=>'ℹ️ Info','name'=>'Processed','desc'=>'How many posts the batch attempted to generate descriptions for in each run. Posts that already had descriptions are skipped and not counted here.'],
                    ['rec'=>'ℹ️ Info','name'=>'Succeeded','desc'=>'Posts that were successfully updated with a new AI-generated description. These posts now have meta descriptions and will be skipped in future batch runs.'],
                    ['rec'=>'ℹ️ Info','name'=>'Errors','desc'=>'Posts where generation failed — usually due to an API error, rate limit, or the post having no readable content. The batch will retry these on the next scheduled run. Check your API key if errors are consistently high.'],
                    ['rec'=>'ℹ️ Info','name'=>'Next scheduled run','desc'=>'When the batch will next execute automatically. If this shows "Not scheduled" but the schedule is enabled, try saving your schedule settings again — this re-registers the WordPress cron event.'],
                ]); ?>
                    </span>
            </div>
            <div class="ab-zone-body">
            <?php
                $history = get_option('cs_seo_batch_history', []);
                // One-time migration: cs_seo_last_batch → cs_seo_batch_history.
                // Runs at most once: the legacy option is deleted on success so subsequent
                // renders return null from WP object cache and skip the write entirely.
                if (empty($history) && get_option('cs_seo_last_batch', null)) {
                    $legacy  = get_option('cs_seo_last_batch');
                    $history = [$legacy];
                    update_option('cs_seo_batch_history', $history, false);
                    delete_option('cs_seo_last_batch');
                }
                if (!empty($history) && is_array($history)):
                    // Show newest first.
                    $history = array_reverse($history);
            ?>
                <div style="padding:16px 20px;max-height:500px;overflow-y:auto">
                <?php foreach ($history as $idx => $batch): ?>
                    <div style="<?php echo esc_attr( $idx > 0 ? 'margin-top:12px;padding-top:12px;border-top:1px solid #e5e5e5;' : '' ); ?>">
                        <p style="margin:0 0 4px">
                            <strong><?php echo esc_html($batch['day'] ?? ''); ?> <?php echo esc_html($batch['date'] ?? ''); ?></strong> —
                            <?php $batch_done = (int)($batch['done'] ?? 0) + (int)($batch['alt_done'] ?? 0) + (int)($batch['sum_done'] ?? 0); ?>
                            <span style="color:<?php echo esc_attr( $batch_done > 0 ? '#2271b1' : '#1a7a34' ); ?>"><?php echo (int)($batch['done'] ?? 0); ?> generated</span>,
                            <?php echo (int)($batch['skipped'] ?? 0); ?> skipped<?php if (($batch['errors'] ?? 0) > 0): ?>,
                                <span style="color:#c3372b"><?php echo (int)$batch['errors']; ?> errors</span><?php endif; ?>,
                            <?php echo (int)($batch['elapsed'] ?? 0); ?>s total
                        </p>
                        <?php if (!empty($batch['log'])): ?>
                        <details style="margin-top:4px">
                            <summary style="cursor:pointer;font-size:12px;color:#50575e">Show post log (<?php echo (int) count( $batch['log'] ); ?> entries)</summary>
                            <div style="background:#1a1a2e;color:#e0e0f0;font-family:'Courier New',monospace;font-size:11px;padding:10px;border-radius:4px;margin-top:8px;max-height:200px;overflow-y:auto">
                            <?php foreach ($batch['log'] as $entry): ?>
                                <?php if ($entry['status'] === 'ok'): ?>
                                    <div style="color:#00d084">✓ <?php echo esc_html($entry['title']); ?> → <?php echo (int)$entry['chars']; ?> chars</div>
                                <?php elseif ($entry['status'] === 'sum_ok'): ?>
                                    <div style="color:#00d084">✓ <?php echo esc_html($entry['title']); ?> → summary</div>
                                <?php elseif ($entry['status'] === 'alt_ok'): ?>
                                    <div style="color:#00d084">✓ <?php echo esc_html($entry['title']); ?> → <?php echo (int)$entry['count']; ?> ALT text(s)</div>
                                <?php elseif ($entry['status'] === 'timeout'): ?>
                                    <div style="color:#ffa500">⏱ <?php echo esc_html($entry['title']); ?></div>
                                <?php else: ?>
                                    <div style="color:#ff6b6b">✗ <?php echo esc_html($entry['title']); ?><?php if ( ! empty( $entry['message'] ) ) : ?>: <?php echo esc_html($entry['message']); ?><?php endif; ?></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </div>
                        </details>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="padding:16px 20px;margin:0;color:#50575e">No batch has run yet.</p>
            <?php endif; ?>
            </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-lastrun -->

        </div><!-- /ab-pane-batch -->

        <?php /* ══════════════════ CATEGORY FIXER PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-catfix">

            <div class="ab-zone-card ab-card-catfix">
                <div class="ab-zone-header" style="display:flex;align-items:center;justify-content:space-between;">
                    <span>🏷 Category Fixer</span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button class="button" id="cf-reload-hdr" style="display:none;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#8635; Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-catfix" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('catfix', 'Category Fixer', [
                            ['name'=>'How it works','rec'=>'Info','desc'=>'Scans all posts using local keyword matching against your category list. No AI calls are made.'],
                            ['name'=>'Scoring','rec'=>'Info','desc'=>'Compares post title (4pts), AI summary (3pts), tags (3pts), and slug (2pts) against each category name. Existing categories get a continuity bonus.'],
                            ['name'=>'Proposal','rec'=>'Info','desc'=>'Up to four categories are proposed per post. Posts where no category scores above 8 keep their existing categories and are flagged.'],
                            ['name'=>'Apply','rec'=>'Info','desc'=>'You review every suggestion before anything changes. Use Apply to set categories on one post or Apply All Changed for a bulk update.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px;">

                    <div id="cf-cta" style="text-align:center;padding:32px 0;">
                        <p style="color:#555;margin:0 0 16px;">Scan all posts and suggest improved category assignments.</p>
                        <button id="cf-scan-btn" class="button button-primary button-hero">&#128269; Scan Posts</button>
                    </div>

                    <div id="cf-toolbar" style="display:none;margin-bottom:16px;align-items:center;gap:8px;flex-wrap:wrap;">
                        <span id="cf-status" style="color:#555;font-size:13px;flex:1;"></span>
                        <button class="button" id="cf-f-all">All</button>
                        <button class="button" id="cf-f-changed">Changed</button>
                        <button class="button" id="cf-f-unchanged">Unchanged</button>
                        <button class="button" id="cf-f-low">Low Confidence</button>
                        <button class="button" id="cf-f-missing">Missing</button>
                        <button class="button" id="cf-ai-btn" style="background:#1a4a7a;border-color:#1a4a7a;color:#fff;">&#129302; AI Analyse All</button>
                        <button class="button button-primary" id="cf-bulk-btn" style="margin-left:auto;background:#2d6a4f;border-color:#2d6a4f;color:#fff;">&#10003; Apply All Changed</button>
                    </div>

                    <div id="cf-stats" style="display:none;margin-bottom:16px;gap:12px;flex-wrap:wrap;"></div>

                    <div id="cf-legend" style="display:none;margin-bottom:12px;font-size:12px;color:#555;">
                        <span style="margin-right:16px;">Proposed changes:</span>
                        <span style="display:inline-block;background:#1a7a34;color:#fff;border-radius:10px;padding:1px 10px;margin-right:8px;">+ Added</span>
                        <span style="display:inline-block;background:#d63638;color:#fff;border-radius:10px;padding:1px 10px;margin-right:8px;">− Removed</span>
                        <span style="display:inline-block;background:#787c82;color:#fff;border-radius:10px;padding:1px 10px;">Kept</span>
                    </div>

                    <div id="cf-posts-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
                        <table id="cf-table" style="display:none;width:100%;min-width:700px;border-collapse:collapse;font-size:13px;">
                            <thead>
                                <tr style="background:#f0f0f0;">
                                    <th style="padding:8px 10px;text-align:left;width:24px;"><input type="checkbox" id="cf-check-all"></th>
                                    <th style="padding:8px 10px;text-align:left;">Post</th>
                                    <th style="padding:8px 10px;text-align:left;width:180px;">Current</th>
                                    <th style="padding:8px 10px;text-align:left;width:180px;">Proposed</th>
                                    <th style="padding:8px 10px;text-align:left;width:110px;">Confidence</th>
                                    <th style="padding:8px 10px;text-align:left;width:140px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="cf-tbody"></tbody>
                        </table>
                        <div id="cf-pager" style="display:none;margin-top:12px;text-align:center;font-size:13px;"></div>
                    </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-catfix -->

            <div class="ab-zone-card ab-card-cathealth" style="margin-top:24px;">
                <div class="ab-zone-header" style="display:flex;align-items:center;justify-content:space-between;background:#0e5a6e;">
                    <span>&#128202; Category Health</span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button class="button" id="ch-reload-hdr" style="display:none;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#8635; Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-cathealth" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('cathealth', 'Category Health Dashboard', [
                            ['name'=>'Strong','rec'=>'✅ Recommended','desc'=>'10 or more published posts. This category is well established and should remain.'],
                            ['name'=>'Moderate','rec'=>'⬜ Optional','desc'=>'4 to 9 posts. Healthy but could grow further.'],
                            ['name'=>'New','rec'=>'⬜ Optional','desc'=>'1 to 3 posts published within the last 180 days. This category is growing and should not be treated as weak yet.'],
                            ['name'=>'Weak','rec'=>'⬜ Optional','desc'=>'2 to 3 posts, none recent. Consider whether this topic needs its own category or should be merged.'],
                            ['name'=>'Empty','rec'=>'ℹ️ Info','desc'=>'0 to 1 posts. This category adds no value to your taxonomy. Consider deleting it.'],
                            ['name'=>'Uncategorized','rec'=>'ℹ️ Info','desc'=>'WordPress default fallback. Posts here were never assigned a real category.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px;">

                    <div id="ch-cta" style="text-align:center;padding:32px 0;">
                        <p style="color:#555;margin:0 0 16px;">Analyse all categories and show post counts, health grades, and per-category post lists.</p>
                        <button id="ch-analyse-btn" class="button button-primary button-hero">&#128202; Analyse Categories</button>
                    </div>

                    <div id="ch-stats" style="display:none;margin-bottom:12px;gap:6px;flex-wrap:wrap;align-items:center;"></div>

                    <div id="ch-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>

                </div>
            </div><!-- /ab-card-cathealth -->

            <div class="ab-zone-card ab-card-catdrift" style="margin-top:24px;">
                <div class="ab-zone-header" style="display:flex;align-items:center;justify-content:space-between;background:#6b3fa0;">
                    <span>&#9889; Category Drift</span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button class="button" id="cd-reload-hdr" style="display:none;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#129302; Re-run Analysis</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-catdrift" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('catdrift', 'Category Drift Detection', [
                            ['name'=>'How it works','rec'=>'ℹ️ Info','desc'=>'AI analyses the post titles in each category to determine whether the category covers a coherent topic or is being used as a catch-all for unrelated subjects. It uses semantic understanding, not pattern counting, so it can tell the difference between a legitimately broad category and a genuinely overloaded one.'],
                            ['name'=>'Catch-all','rec'=>'ℹ️ Info','desc'=>'The category contains posts on clearly unrelated topics with no coherent theme. These are the most actionable findings and are listed first.'],
                            ['name'=>'Drifting','rec'=>'ℹ️ Info','desc'=>'The category has a recognisable core theme but also includes posts that do not belong. The AI is less certain these are problems, so check the reasoning before acting.'],
                            ['name'=>'Confidence','rec'=>'ℹ️ Info','desc'=>'High confidence means the AI saw clear evidence in the titles. Medium or low confidence means the sample was ambiguous. Always review the example titles and reasoning before making changes.'],
                            ['name'=>'What to do','rec'=>'⬜ Optional','desc'=>'Each flagged category shows a specific suggestion from the AI. Common actions are: split into two more specific categories, rename to better reflect the actual content, merge into an existing category, or delete if the topic is already covered elsewhere.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px;">

                    <div id="cd-cta" style="text-align:center;padding:32px 0;">
                        <p style="color:#555;margin:0 0 16px;">Detect categories being used inconsistently across posts.</p>
                        <div style="display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
                            <button class="button" id="cd-btn-cache" style="background:#2d6a4f;color:#fff;border-color:#2d6a4f;padding:6px 16px;">&#128336; Load Cached Results</button>
                            <button class="button button-primary" id="cd-btn-fresh">&#9889; Run Fresh AI Analysis</button>
                        </div>
                        <p id="cd-cta-msg" style="color:#888;font-size:12px;margin:12px 0 0;">Load cached results instantly, or run a fresh AI analysis.</p>
                    </div>

                    <div id="cd-summary" style="display:none;margin-bottom:16px;"></div>
                    <div id="cd-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>

                </div>
            </div><!-- /ab-card-catdrift -->

            <div class="ab-zone-card ab-card-catmig" style="margin-top:24px;">
                <div class="ab-zone-header" style="display:flex;align-items:center;justify-content:space-between;background:#b35900;">
                    <span>&#128260; Migrate Categories</span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-catmig" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('catmig', 'Migrate Categories', [
                            ['name'=>'When to use','rec'=>'ℹ️ Info','desc'=>'Use this panel when you want to retire or consolidate a low-traffic category. Categories are listed from fewest posts to most — the lowest-count ones are the best candidates to migrate away.'],
                            ['name'=>'Single-category posts','rec'=>'ℹ️ Info','desc'=>'A post assigned to only this category must be swapped to another category — it cannot simply be removed or it would end up uncategorised.'],
                            ['name'=>'Multi-category posts','rec'=>'ℹ️ Info','desc'=>'A post already in two or more categories can either have this category removed (keeping the others) or swapped to a different one.'],
                            ['name'=>'Applying changes','rec'=>'ℹ️ Info','desc'=>'Set the action for each post, then click Apply on individual rows or Apply All to process every pending row at once.'],
                            ['name'=>'Deleting the category','rec'=>'ℹ️ Info','desc'=>'Once all posts have been migrated away, a red Delete Category button appears. Click it to permanently delete the empty category — no need to leave the plugin.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px;">

                    <?php /* Phase 1: category list */ ?>
                    <div id="cm-phase1">
                        <div id="cm-cta" style="text-align:center;padding:32px 0;">
                            <p style="color:#555;margin:0 0 16px;">View all categories sorted by post count. Select one to migrate its posts.</p>
                            <button id="cm-load-btn" class="button button-primary button-hero">&#128260; Load Categories</button>
                        </div>
                        <div id="cm-cat-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>
                    </div>

                    <?php /* Phase 2: posts within the selected category */ ?>
                    <div id="cm-phase2" style="display:none;">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                            <button id="cm-back-btn" class="button" style="background:#b35900;border-color:#b35900;color:#fff;">&#8592; Back to Categories</button>
                            <span id="cm-p2-title" style="font-weight:600;font-size:14px;color:#333;flex:1;"></span>
                            <button id="cm-delete-cat-btn" class="button" style="background:#c3372b;border-color:#c3372b;color:#fff;display:none;">&#128465; Delete Category</button>
                            <span id="cm-apply-result" style="display:none;font-size:12px;font-weight:600;padding:4px 10px;border-radius:12px;background:#d1fae5;color:#1a7a34;"></span>
                            <button id="cm-apply-all-btn" class="button button-primary" style="background:#2d6a4f;border-color:#2d6a4f;color:#fff;">&#10003; Apply All</button>
                        </div>
                        <div id="cm-p2-status" style="font-size:13px;color:#555;margin-bottom:12px;"></div>
                        <div id="cm-post-wrap" style="overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>
                    </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-catmig -->

        </div><!-- /ab-pane-catfix -->

        <?php /* ══════════════════ BROKEN LINK CHECKER PANE ══════════════════ */ ?>

        <div class="ab-pane" id="ab-pane-blc">

            <div class="ab-zone-card ab-card-blc" style="margin-top:0">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🔗</span> <?php esc_html_e( 'Broken Link Checker', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <?php $this->explain_btn( 'blc', '🔗 Broken Link Checker — How it works', [
                            [ 'rec' => 'ℹ️ Info', 'name' => 'What it scans',         'desc' => 'Checks every outbound <a href="…"> link found in published posts and pages. Internal links (same domain) are skipped — only external URLs are verified.' ],
                            [ 'rec' => 'ℹ️ Info', 'name' => 'Deduplication',          'desc' => 'Each unique URL is fetched only once, no matter how many posts contain it. This keeps the scan fast even on large sites.' ],
                            [ 'rec' => 'ℹ️ Info', 'name' => 'HTTP status codes',     'desc' => '2xx responses are OK. 3xx redirects are flagged as warnings (the link still works but points to an outdated URL). 4xx/5xx responses are flagged as broken.' ],
                            [ 'rec' => '⚡ Tip',  'name' => 'Stopping a scan',        'desc' => 'You can stop the scan at any time — results found so far are still shown in the table below. Re-running a scan clears the previous results and starts fresh.' ],
                            [ 'rec' => '⚡ Tip',  'name' => 'Fixing broken links',    'desc' => 'Click the post title in the results table to open the editor. Find the broken URL and either update it to the new address or remove the link entirely.' ],
                        ] ); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px">
                    <p style="margin:0 0 16px;color:#50575e;font-size:13px">
                        <?php esc_html_e( 'Scans all published posts and pages for broken outbound links. Each unique URL is checked server-side once, regardless of how many posts use it.', 'cloudscale-seo-ai-optimizer' ); ?>
                    </p>
                    <div class="ab-ai-toolbar">
                        <button id="blc-scan-btn" class="button button-primary ab-action-btn"><?php esc_html_e( '🔍 Scan for Broken Links', 'cloudscale-seo-ai-optimizer' ); ?></button>
                        <button id="blc-stop-btn" class="button ab-action-btn" style="display:none"><?php esc_html_e( '⏹ Stop', 'cloudscale-seo-ai-optimizer' ); ?></button>
                        <span id="blc-status" style="font-size:13px;color:#50575e"></span>
                    </div>
                    <div class="ab-progress" id="blc-progress"><div class="ab-progress-fill" id="blc-progress-fill"></div></div>
                    <div id="blc-summary" class="ab-summary-row" style="display:none">
                        <div class="ab-summary-card"><div class="ab-summary-num" id="blc-total-posts">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Posts Scanned', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="blc-total-links">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Links Checked', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="blc-broken-count" style="color:#9b1c1c">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Broken Links', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="blc-redirect-count" style="color:#92400e">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Redirects', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    </div>
                    <div id="blc-results-wrap" style="overflow-x:auto;display:none">
                        <table class="ab-posts" id="blc-table">
                            <thead>
                                <tr>
                                    <th style="min-width:160px;cursor:pointer;user-select:none" data-blc-sort="post_title"><?php esc_html_e( 'Post', 'cloudscale-seo-ai-optimizer' ); ?> <span class="blc-sort-icon" style="opacity:0.5">&#8597;</span></th>
                                    <th style="min-width:260px"><?php esc_html_e( 'URL', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th style="min-width:100px"><?php esc_html_e( 'Anchor Text', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th style="min-width:100px;cursor:pointer;user-select:none" data-blc-sort="date_ts"><?php esc_html_e( 'Date Created', 'cloudscale-seo-ai-optimizer' ); ?> <span class="blc-sort-icon" style="opacity:0.5">&#8597;</span></th>
                                    <th style="min-width:100px;cursor:pointer;user-select:none" data-blc-sort="status_code"><?php esc_html_e( 'Status', 'cloudscale-seo-ai-optimizer' ); ?> <span class="blc-sort-icon" style="opacity:0.5">&#8597;</span></th>
                                </tr>
                            </thead>
                            <tbody id="blc-tbody"></tbody>
                        </table>
                    </div>
                    <p id="blc-all-ok" style="display:none;color:#1a7a34;font-weight:600;font-size:14px;margin-top:16px">✅ <?php esc_html_e( 'No broken links found!', 'cloudscale-seo-ai-optimizer' ); ?></p>
                </div>
            </div>

        </div><!-- /ab-pane-blc -->

        <?php /* ══════════════════ IMAGE SEO PANE ══════════════════ */ ?>

        <div class="ab-pane" id="ab-pane-imgseo">

            <div class="ab-zone-card ab-card-imgseo" style="margin-top:0">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🖼</span> <?php esc_html_e( 'Image SEO Audit', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;">
                        <?php $this->explain_btn( 'imgseo', '🖼 Image SEO Audit — What each issue means', [
                            [ 'rec' => '🔴 Fix',  'name' => 'Missing ALT text',       'desc' => 'The image has no alt attribute set in the Media Library. ALT text is read by screen readers and used by Google to understand image content. Add a short, descriptive phrase — e.g. "laptop on a wooden desk" rather than "image1".' ],
                            [ 'rec' => '🔴 Fix',  'name' => 'Non-descriptive filename','desc' => 'The filename looks like a camera default (IMG_001.jpg, screenshot2.png, DSC_0042.jpg etc.). Google uses filenames as a ranking signal. Rename images to something descriptive before uploading — e.g. "cloud-architecture-diagram.png".' ],
                            [ 'rec' => '🟡 Warn', 'name' => 'Oversized file (>500 KB)','desc' => 'The image file on disk exceeds 500 KB. Large images slow page load times and hurt Core Web Vitals scores. Compress or resize the image using a tool like Squoosh, TinyPNG, or ShortPixel.' ],
                            [ 'rec' => 'ℹ️ Info', 'name' => 'Results scope',          'desc' => 'Only images with at least one issue are listed. Images that pass all three checks are not shown. The scan covers all media in your WordPress Media Library, not just images attached to posts.' ],
                            [ 'rec' => '⚡ Tip',  'name' => 'Bulk fixing',             'desc' => 'Click the image title to open the Media Library attachment editor where you can update the ALT text directly. For filenames, re-upload the renamed file and update any posts that reference the old URL.' ],
                        ] ); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px">
                    <p style="margin:0 0 16px;color:#50575e;font-size:13px">
                        <?php esc_html_e( 'Scans your Media Library for SEO issues: missing ALT text, non-descriptive filenames (IMG_001.jpg etc.), and oversized files (>500 KB).', 'cloudscale-seo-ai-optimizer' ); ?>
                    </p>
                    <div class="ab-ai-toolbar">
                        <button id="imgseo-scan-btn" class="button button-primary ab-action-btn"><?php esc_html_e( '🔍 Scan Media Library', 'cloudscale-seo-ai-optimizer' ); ?></button>
                        <span id="imgseo-status" style="font-size:13px;color:#50575e"></span>
                    </div>
                    <div id="imgseo-summary" class="ab-summary-row" style="display:none">
                        <div class="ab-summary-card"><div class="ab-summary-num" id="imgseo-total">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Total Images', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="imgseo-missing-alt" style="color:#9b1c1c">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Missing ALT', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="imgseo-bad-fname" style="color:#92400e">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Bad Filenames', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                        <div class="ab-summary-card"><div class="ab-summary-num" id="imgseo-large" style="color:#7c3aed">0</div><div class="ab-summary-lbl"><?php esc_html_e( 'Large Files (>500KB)', 'cloudscale-seo-ai-optimizer' ); ?></div></div>
                    </div>
                    <div id="imgseo-results-wrap" style="overflow-x:auto;display:none">
                        <table class="ab-posts" id="imgseo-table">
                            <thead>
                                <tr>
                                    <th style="width:60px"><?php esc_html_e( 'Preview', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'Filename', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'Used In', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'Size', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'ALT Text', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'Issues', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                    <th><?php esc_html_e( 'Action', 'cloudscale-seo-ai-optimizer' ); ?></th>
                                </tr>
                            </thead>
                            <tbody id="imgseo-tbody"></tbody>
                        </table>
                    </div>
                    <p id="imgseo-all-ok" style="display:none;color:#1a7a34;font-weight:600;font-size:14px;margin-top:16px">✅ <?php esc_html_e( 'No image SEO issues found!', 'cloudscale-seo-ai-optimizer' ); ?></p>
                </div>
            </div>

        </div><!-- /ab-pane-imgseo -->

        <?php /* ══════════════════ TITLE OPTIMISER PANE ══════════════════ */ ?>
        <div class="ab-pane" id="ab-pane-titleopt">

            <div class="ab-zone-card ab-card-titleopt" style="margin-top:0">
                <div class="ab-zone-header" style="justify-content:space-between">
                    <span><span class="ab-zone-icon">🎯</span> <?php esc_html_e( 'Title Optimiser', 'cloudscale-seo-ai-optimizer' ); ?></span>
                    <span style="display:flex;align-items:center;gap:8px;margin-left:auto">
                        <button class="button" id="ab-titleopt-reload-hdr" style="visibility:hidden;background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3)">↻ Reload</button>
                        <button type="button" class="button ab-toggle-card-btn" data-card-id="ab-card-titleopt" style="background:rgba(255,255,255,0.15);color:#fff;border-color:rgba(255,255,255,0.3);">&#9660; Hide Details</button>
                        <?php $this->explain_btn('titleopt', '🎯 Title Optimiser — How this works', [
                            ['rec'=>'ℹ️ Info','name'=>'What this panel does','desc'=>'Scans all your published posts and uses AI to suggest SEO-optimised titles. Each suggestion includes the primary keywords identified, a before/after SEO score, and a note on what was weak and what was fixed. You can apply titles individually or in bulk.'],
                            ['rec'=>'✅ Recommended','name'=>'Why title SEO matters','desc'=>'Google weighs the title heavily when deciding rankings. A title that leads with the primary keyword, is 50–60 characters long, and matches clear search intent (how-to, best, guide) consistently outranks a clever or conversational title. Most blog titles are written for humans first — this tool rewrites them for search engines first.'],
                            ['rec'=>'ℹ️ Info','name'=>'SEO score','desc'=>'The score (0–100) measures keyword clarity, title length (ideal 50–60 chars), and search intent alignment. A score below 50 means the title is likely too vague or missing keywords. Above 70 is good. The goal is to see a meaningful jump from before to after.'],
                            ['rec'=>'ℹ️ Info','name'=>'Analyse All','desc'=>'Runs the AI suggestion pass across every post that has not yet been analysed. Runs one post at a time in a polling loop. Safe to stop and restart. Does not change any post titles.'],
                            ['rec'=>'⚠️ Important','name'=>'Apply','desc'=>'Applying a title updates the post title and URL slug. A 301 redirect is automatically created from the old URL to the new one — so existing links and search engine rankings are preserved. The redirect appears in the Sitemap & Redirects tab.'],
                            ['rec'=>'ℹ️ Info','name'=>'Min. gain % threshold','desc'=>'Set a minimum percentage improvement before a title is eligible for bulk apply. For example, 10% means only apply titles where the AI score improved by at least 10% relative to the original. The "Will Apply" column in the table updates live as you type. Posts below the threshold show their actual gain % so you can decide individually. Leave the field blank or set to 0 to apply all suggested titles.'],
                            ['rec'=>'ℹ️ Info','name'=>'Will Apply column','desc'=>'Shows whether each post will be included in the next "Apply to X posts" bulk run, based on the current Min. gain threshold. Green badge = will be applied. "Below threshold" = suggested but gain is below your cutoff. You can sort and filter by this column to preview exactly what will change before committing.'],
                            ['rec'=>'ℹ️ Info','name'=>'Sort options','desc'=>'"Sort by Date" shows newest posts first. "Sort by Comments" shows most-engaged posts first — useful for prioritising high-traffic posts for optimisation.'],
                        ]); ?>
                    </span>
                </div>
                <div class="ab-zone-body" style="padding:20px 24px 24px;">

                <div class="ab-api-key-warning" id="ab-titleopt-api-warn" style="<?php echo esc_attr( $alt_has_key ? 'display:none' : '' ); ?>">
                    <div class="ab-warn-icon">⚠️</div>
                    <div class="ab-warn-body">
                        <strong>No AI API key saved — title analysis is disabled.</strong>
                        Add an Anthropic API key in the <strong>✨ AI Tools</strong> tab → AI Meta Writer section and save.
                    </div>
                </div>

                <div id="ab-titleopt-summary" class="ab-summary-row">
                    <div class="ab-summary-card"><div class="ab-summary-num" id="titleopt-s-total">—</div><div class="ab-summary-lbl">Total Posts</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="titleopt-s-analysed" style="color:#6b3fa0">—</div><div class="ab-summary-lbl">Analysed</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="titleopt-s-applied" style="color:#1a7a34">—</div><div class="ab-summary-lbl">Applied</div></div>
                    <div class="ab-summary-card"><div class="ab-summary-num" id="titleopt-s-session" style="color:#2271b1">0</div><div class="ab-summary-lbl">Analysed This Session</div></div>
                </div>

                <div class="ab-ai-toolbar" id="ab-titleopt-toolbar">
                    <button class="button button-primary ab-action-btn" id="ab-titleopt-analyse-all" <?php disabled( ! $alt_has_key ); ?>>🔍 Analyse Remaining</button>
                    <button class="button ab-action-btn" id="ab-titleopt-force-all" style="background:#b45309;border-color:#92400e;color:#fff;font-weight:600" <?php disabled( ! $alt_has_key ); ?>>🔄 Re-analyse All</button>
                    <span style="display:inline-flex;align-items:center;gap:4px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:3px;padding:2px 7px;height:30px">
                        <label for="ab-titleopt-threshold" style="font-size:11px;color:#50575e;white-space:nowrap;cursor:default" title="Only apply titles where the score improves by at least this percentage">Min. gain</label>
                        <input type="number" id="ab-titleopt-threshold" min="0" max="100" value="10" style="width:44px;border:none;background:transparent;font-size:13px;text-align:center;padding:0;-moz-appearance:textfield" placeholder="—" title="Leave blank to apply all suggested titles, or enter a % threshold (e.g. 20 = only apply where score improves by ≥20%)">
                        <span style="font-size:11px;color:#50575e">%</span>
                    </span>
                    <button class="button ab-action-btn" id="ab-titleopt-apply-all" style="background:#1a7a34;border-color:#155724;color:#fff;font-weight:600" disabled>✅ Apply to — posts</button>
                    <button class="button ab-action-btn" id="ab-titleopt-scan-links" style="background:#6b3fa0;border-color:#4a2a7a;color:#fff;font-weight:600" title="Scan posts for internal links still pointing to old slugs">🔍 Scan Broken Links</button>
                    <button class="button ab-action-btn" id="ab-titleopt-fix-links" style="background:#0073aa;border-color:#005177;color:#fff;font-weight:600" title="Rewrite internal post links from old slugs to new URLs" disabled>🔗 Fix Broken Links</button>
                    <?php $this->explain_btn('fix_links', '🔗 Fix Broken Internal Links — How it works', [
                        ['rec' => 'ℹ️ Info', 'name' => 'What these buttons do', 'desc' => 'When a post slug is renamed, any other posts that link to the old URL will still have the old URL in their content — they rely on the 301 redirect. These buttons let you update the actual post content so the links point directly to the new URL, eliminating the redirect hop.'],
                        ['rec' => '✅ Step 1 — Scan', 'name' => 'Scan for Broken Links', 'desc' => 'Scans all published posts and checks whether any contain URLs that now have a redirect on them. Returns a list of affected posts. No changes are made at this stage.'],
                        ['rec' => '✅ Step 2 — Fix', 'name' => 'Fix Broken Links', 'desc' => 'Rewrites the old URLs found during the scan directly to the new destination URL inside the post content. Safe to run multiple times — if a URL is already correct the rewrite is a no-op.'],
                        ['rec' => 'ℹ️ Info', 'name' => 'Why bother if the redirect already works', 'desc' => 'A redirect adds a round-trip for every visitor and crawler. Google does transfer PageRank through redirects, but direct links are faster and cleaner. For internal links on your own site there is no reason to keep the redirect hop in place.'],
                    ]); ?>
                    <span id="ab-titleopt-link-scan-result" style="display:none;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap"></span>
                    <span id="ab-titleopt-status" style="font-size:12px;color:#50575e;"></span>
                    <button class="button" id="ab-titleopt-stop" style="display:none;background:#9b1c1c;border-color:#7f1d1d;color:#fff;font-weight:700;font-size:13px;padding:0 18px">⏹ Stop</button>
                </div>

                <div class="ab-progress" id="ab-titleopt-progress">
                    <div class="ab-progress-fill" id="ab-titleopt-progress-fill"></div>
                </div>

                <div id="ab-titleopt-posts-wrap" style="margin-top:12px;overflow-x:auto;-webkit-overflow-scrolling:touch;"></div>

                <div id="ab-titleopt-log-wrap" style="display:none;margin-top:16px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
                        <span style="background:linear-gradient(135deg,#f953c6 0%,#4f46e5 100%);color:#fff;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:3px 10px;border-radius:20px">⚡ Activity Log</span>
                    </div>
                    <div id="ab-titleopt-log"></div>
                </div>

                </div><!-- /ab-zone-body -->
            </div><!-- /ab-card-titleopt -->

        </div><!-- /ab-pane-titleopt -->

        <?php /* ══════════════════ REDIRECTS PANE ══════════════════ */ ?>

        <?php ob_start(); ?>
        function abFontLog(type, text) {
            const consoleEl = document.getElementById('ab-font-console');
            if (!consoleEl) return;
            
            // Clear placeholder text on first log
            if (consoleEl.innerHTML.includes('Click "Scan')) {
                consoleEl.innerHTML = '';
            }
            
            const line = document.createElement('div');
            line.className = 'ab-log-line ab-log-' + type;
            line.textContent = text;
            line.style.marginBottom = '4px';
            
            // Color coding
            if (type === 'err') line.style.color = '#d32f2f';
            if (type === 'ok') line.style.color = '#388e3c';
            if (type === 'warn') line.style.color = '#f57c00';
            if (type === 'info') line.style.color = '#1976d2';
            
            consoleEl.appendChild(line);
            consoleEl.scrollTop = consoleEl.scrollHeight;
        }

        function abFontClearConsole() {
            const consoleEl = document.getElementById('ab-font-console');
            if (!consoleEl) return;
            consoleEl.innerHTML = '<div style="text-align:center; color:#999; padding:20px;">Click "Scan CSS Files" to analyze your fonts...</div>';
        }

        async function abFontDownload(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '⏳ Downloading...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Detecting Google Fonts CDN URLs...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Connecting to server...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_download_fonts',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (data.messages && Array.isArray(data.messages)) {
                    data.messages.forEach(msg => {
                        if (msg.includes('✓')) {
                            abFontLog('ok', msg);
                        } else if (msg.includes('✗')) {
                            abFontLog('err', msg);
                        } else if (msg.includes('ℹ')) {
                            abFontLog('warn', msg);
                        } else if (msg === '') {
                            abFontLog('info', '');
                        } else {
                            abFontLog('info', msg);
                        }
                    });
                }
                
                if (data.success && data.downloaded > 0) {
                    abFontLog('ok', '✓ Fonts downloaded! Run "Scan CSS Files" to verify.');
                }
                
            } catch (e) {
                abFontLog('err', 'Download failed: ' + e.message);
                console.error('[CloudScale Font Download]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '⬇️ Auto-Download CDN Fonts';
            }
        }

        async function abFontScan(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '🔄 Scanning...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Initializing font scanner...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Connecting to server...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 30000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_font_scan',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                abFontLog('info', 'Processing response...');
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (!data || !data.console || !Array.isArray(data.console)) {
                    abFontLog('err', 'ERROR: Invalid response structure');
                    throw new Error('Invalid response');
                }
                
                abFontLog('info', 'Displaying results...');
                
                data.console.forEach(line => {
                    if (line && line.type && line.text) {
                        abFontLog(line.type, line.text);
                    }
                });
                
                    if (data.findings && data.findings.missing_fonts > 0) {
                        const fixBtn = document.getElementById('ab-font-fix-btn');
                        if (fixBtn) {
                            fixBtn.style.display = 'inline-block';
                            fixBtn.textContent = '✨ Auto-Fix All (' + data.findings.missing_fonts + ' fonts)';
                        }
                    }
                
            } catch (e) {
                abFontLog('err', 'Scan failed: ' + e.message);
                console.error('[CloudScale Font Scan]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '🔍 Scan CSS Files';
            }
        }

        async function abFontFix(btn) {
            try {
                btn.disabled = true;
                btn.textContent = '⏳ Checking fonts...';
                
                // Clear console on start
                const consoleEl = document.getElementById('ab-font-console');
                if (consoleEl) consoleEl.innerHTML = '';
                
                abFontLog('info', 'Checking for unoptimized fonts...');
                
                if (typeof ajaxurl === 'undefined') {
                    abFontLog('err', 'ERROR: WordPress AJAX not available');
                    throw new Error('WordPress AJAX not initialized');
                }
                
                abFontLog('info', 'Scanning fonts...');
                
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 60000);
                
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'cs_seo_font_fix',
                        nonce: '<?php echo esc_attr($nonce); ?>'
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    abFontLog('err', 'ERROR: Server returned ' + response.status);
                    throw new Error('HTTP ' + response.status);
                }
                
                abFontLog('info', 'Processing results...');
                
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    abFontLog('err', 'ERROR: Invalid server response');
                    throw new Error('JSON parse error');
                }
                
                if (!data || !data.console || !Array.isArray(data.console)) {
                    abFontLog('err', 'ERROR: Invalid response structure');
                    throw new Error('Invalid response');
                }
                
                // Check if there's actually anything to fix
                const hasUnoptimized = data.console && data.console.some(line => 
                    line.text && line.text.includes('MISSING') || line.text.includes('unoptimized')
                );
                
                if (!hasUnoptimized && data.console.length > 0) {
                    abFontLog('info', '');
                    abFontLog('ok', '✓ All fonts are already optimized!');
                    abFontLog('info', 'No changes needed.');
                    abFontLog('info', '');
                    abFontLog('skip', 'Your fonts already have font-display and are properly deferred.');
                } else {
                    abFontLog('info', 'Applying optimizations...');
                }
                
                data.console.forEach(line => {
                    if (line && line.type && line.text) {
                        abFontLog(line.type, line.text);
                    }
                });
                
            } catch (e) {
                abFontLog('err', 'Fix failed: ' + e.message);
                console.error('[CloudScale Font Fix]', e);
            } finally {
                btn.disabled = false;
                btn.textContent = '✨ Auto-Fix All';
            }
        }
        <?php wp_add_inline_script('cs-seo-admin-js', ob_get_clean()); ?>

        <?php ob_start(); ?>
        // ── Tab switching ────────────────────────────────────────────────────
        function abToggleCard(cardClass, btn) {
            const card = document.querySelector('.' + cardClass);
            if (!card) return;
            const body = card.querySelector('.ab-zone-body');
            if (!body) return;
            const isHidden = body.style.display === 'none';
            body.style.display = isHidden ? '' : 'none';
            btn.innerHTML = isHidden ? '&#9660; Hide Details' : '&#9658; Show Details';
            // Auto-load posts on first expand for cards that require it.
            if (isHidden && !card.dataset.loaded) {
                card.dataset.loaded = '1';
                const autoLoaders = {
                    'ab-card-update-posts': () => abLoadPosts(),
                    'ab-card-alt':          () => typeof altLoad  === 'function' && altLoad(),
                    'ab-card-summary':      () => typeof sumLoad  === 'function' && sumLoad(),
                };
                if (autoLoaders[cardClass]) autoLoaders[cardClass]();
            }
        }

        function abTab(id, btn) {
            document.querySelectorAll('.ab-pane').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.ab-tab').forEach(b  => b.classList.remove('active'));
            document.getElementById('ab-pane-' + id).classList.add('active');
            btn.classList.add('active');
            try { localStorage.setItem('cs_seo_tab', id); } catch(e) {}
            if (id === 'sitemap') abRefreshRobotsPreview();
            if (id === 'catfix') {
                if (cdDrift && cdDrift.length > 0) cdRender(cdTotalPosts);
            }
        }

        // Restore the active tab — URL ?tab= takes priority over localStorage.
        (function() {
            try {
                const urlTab = new URLSearchParams(window.location.search).get('tab');
                const saved  = urlTab || localStorage.getItem('cs_seo_tab');
                if (saved) {
                    const btn = document.querySelector('.ab-tab[data-tab="' + saved + '"]');
                    if (btn) abTab(saved, btn);
                }
            } catch(e) {}
        })();

        // ── State ────────────────────────────────────────────────────────────
        const abState = {
            posts:          [],
            page:           1,
            totalPages:     1,
            total:          0,
            totalWithDesc:  0,
            totalWithTitle: 0,
            generated:      0,
            generatedTitles:0,
            stopped:        false,
            running:        false,
            sortKey:       null,
            sortDir:       'desc',
        };

        const abNonce     = csSeoAdmin.nonce;
        const abAjax      = csSeoAdmin.ajaxUrl;
        const abMinChar   = csSeoAdmin.minChars;
        const abMaxChar   = csSeoAdmin.maxChars;
        const abHasApiKey = csSeoAdmin.hasApiKey;

        // ── Live robots.txt preview ──────────────────────────────────────────
        function abRefreshRobotsPreview() {
            try {
            const pre = document.getElementById('ab-robots-live-preview');
            if (!pre) return;
            pre.textContent = 'Loading…';
            const params = new URLSearchParams({action: 'cs_seo_fetch_robots', nonce: abNonce});
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        pre.textContent = data.data.content;
                    } else {
                        pre.textContent = '(error: ' + data.data + ')';
                    }
                })
                .catch(e => { pre.textContent = '(fetch error: ' + e.message + ')'; });
            } catch(e) { console.warn('abRefreshRobotsPreview error:', e); }
        }

        function abCopyRobots() {
            const btn  = document.getElementById('ab-robots-copy');
            const ta   = document.getElementById('cs-robots-txt');
            if (!btn || !ta) return;
            const text = ta.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }

        function abCopyRobotsLive() {
            const btn = document.getElementById('ab-robots-live-copy');
            const pre = document.getElementById('ab-robots-live-preview');
            if (!btn || !pre) return;
            const text = pre.textContent;
            if (!text || text === 'Loading…') {
                btn.textContent = '⚠ Load first';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
                return;
            }
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }
        function abCopySitemap() {
            const btn  = document.getElementById('ab-sitemap-copy');
            const urls = window._abSitemapUrls || [];
            if (!btn) return;
            if (!urls.length) { btn.textContent = '⚠ Load first'; setTimeout(function(){ btn.textContent = '⎘ Copy URLs'; }, 2000); return; }
            const text = urls.join('\n');
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy URLs'; }, 2000);
            });
        }
        function abCopyPrompt() {
            const btn = document.getElementById('ab-copy-prompt');
            const ta  = document.getElementById('ab-prompt-field');
            if (!btn || !ta) return;
            const text = ta.value;
            if (!text) return;
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta2 = document.createElement('textarea');
                ta2.value = text;
                ta2.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta2);
                ta2.select();
                document.execCommand('copy');
                document.body.removeChild(ta2);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }

        function abCopyLlms() {
            const btn  = document.getElementById('ab-llms-copy');
            const wrap = document.getElementById('ab-llms-preview-wrap');
            if (!btn || !wrap) return;
            const text = wrap.dataset.raw || '';
            if (!text) { btn.textContent = '⚠ Load first'; setTimeout(function(){ btn.textContent = '⎘ Copy'; }, 2000); return; }
            navigator.clipboard.writeText(text).then(function() {
                const orig = btn.textContent;
                btn.textContent = '✓ Copied!';
                btn.style.color = '#0a3622';
                btn.style.background = '#d1e7dd';
                btn.style.borderColor = '#a3cfbb';
                setTimeout(function() {
                    btn.textContent = orig;
                    btn.style.color = '';
                    btn.style.background = '';
                    btn.style.borderColor = '';
                }, 2000);
            }).catch(function() {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.cssText = 'position:fixed;opacity:0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                btn.textContent = '✓ Copied!';
                setTimeout(function() { btn.textContent = '⎘ Copy'; }, 2000);
            });
        }
        // Auto-load robots preview on page load if sitemap tab is active
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('ab-pane-sitemap')?.classList.contains('active')) {
                abRefreshRobotsPreview();
            }

            // Delegated click handler for title badges — works even after table is re-rendered
            document.addEventListener('click', function(e) {
                const badge = e.target.closest('[data-titleid]');
                if (badge) {
                    e.stopPropagation();
                    abShowTitlePopup(parseInt(badge.getAttribute('data-titleid'), 10), badge);
                } else {
                    // Click outside any badge — dismiss popup if open
                    const popup = document.getElementById('ab-title-popup');
                    if (popup) popup.remove();
                }
            });
        });

        // ── Rename physical robots.txt ───────────────────────────────────────
        function abRenameRobots() {
            const btn    = document.getElementById('ab-rename-robots-btn');
            const status = document.getElementById('ab-rename-robots-status');
            btn.disabled = true;
            btn.textContent = '⟳ Renaming...';
            status.style.color = '#50575e';
            status.textContent = 'Working...';

            const params = new URLSearchParams({action: 'cs_seo_rename_robots', nonce: abNonce});
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const warn = document.getElementById('ab-physical-robots-warn');
                        if (warn) {
                            warn.style.background = '#edfaef';
                            warn.style.borderColor = '#1a7a34';
                            warn.innerHTML = '<div style="font-size:22px">✅</div>' +
                                '<div><strong>Done!</strong> robots.txt has been renamed to robots.txt.bak. ' +
                                'The plugin is now managing your robots.txt. ' +
                                'Purge your Cloudflare cache, then <a href="' + (window.location.href.startsWith('https://') || window.location.href.startsWith('http://') ? window.location.href : '#') + '">reload this page</a> to confirm.</div>';
                        }
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Rename robots.txt → robots.txt.bak';
                        status.style.color = '#c3372b';
                        status.textContent = '✗ ' + data.data;
                    }
                })
                .catch(e => {
                    btn.disabled = false;
                    btn.textContent = 'Rename robots.txt → robots.txt.bak';
                    status.style.color = '#c3372b';
                    status.textContent = '✗ Network error: ' + e.message;
                });
        }
        let abSitemapPage = 1;

        function abLoadSitemap(pg) {
            abSitemapPage = pg || 1;
            const wrap = document.getElementById('ab-sitemap-preview-wrap');
            const btn  = document.getElementById('ab-sitemap-load');
            if (!wrap || !btn) {
                console.error('CloudScale SEO: sitemap preview elements not found');
                return;
            }
            console.log('CloudScale SEO: loading sitemap preview page', abSitemapPage);
            btn.disabled = true;
            btn.textContent = '⟳ Loading...';
            if (abSitemapPage === 1) {
                wrap.innerHTML = '<p style="color:#50575e;font-size:13px">Fetching sitemap entries...</p>';
            }

            const params = new URLSearchParams({
                action: 'cs_seo_sitemap_preview',
                nonce: abNonce,
                sitemap_pg: abSitemapPage,
            });
            fetch(abAjax, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params})
                .then(r => r.json())
                .then(data => {
                    btn.disabled = false;
                    btn.textContent = '↻ Reload';
                    if (!data.success) {
                        wrap.innerHTML = '<div style="background:#fef0f0;border:1px solid #f5bcbb;border-radius:4px;padding:12px;color:#c3372b"><strong>Preview failed:</strong> ' + abEsc(data.data || 'Unknown error') + '<br><small>Check that your API key is set and the plugin settings have been saved.</small></div>';
                        return;
                    }
                    const d        = data.data;
                    const entries  = d.entries;
                    const total    = d.total;
                    const page     = d.page;
                    const pages    = d.pages;
                    const per_page = d.per_page;
                    const start    = (page - 1) * per_page + 1;
                    const end      = Math.min(page * per_page, total);

                    const typeLabels = {home:'Home', post:'Post', page:'Page', tax:'Taxonomy', cpt:'CPT'};
                    const typeClass  = t => 'ab-sitemap-type ab-sitemap-type-' + (t || 'post');

                    let rows = entries.map(e =>
                        '<tr>' +
                        '<td><a class="ab-sitemap-url" href="' + safeHref(e.loc) + '" target="_blank">' + abEsc(e.loc) + '</a>' +
                        (e.title ? '<br><small style="color:#50575e;font-size:11px">' + abEsc(e.title) + '</small>' : '') + '</td>' +
                        '<td><span class="' + typeClass(e.type) + '">' + abEsc(typeLabels[e.type] || e.type) + '</span></td>' +
                        '<td style="color:#50575e;font-size:12px;white-space:nowrap">' + abEsc(e.lastmod || '—') + '</td>' +
                        '</tr>'
                    ).join('');

                    // Pager
                    let pager = '';
                    if (pages > 1) {
                        const sitemapLinks = Array.from({length: pages}, (_, i) => {
                            const n = i + 1;
                            const active = n === page ? 'font-weight:700;color:#1d2327' : 'color:#2271b1;cursor:pointer';
                            return '<span style="' + active + ';padding:0 4px" ' +
                                (n !== page ? 'onclick="abLoadSitemap(' + n + ')"' : '') + '>' + n + '</span>';
                        }).join(' ');
                        pager = '<div style="display:flex;align-items:center;gap:10px;margin-top:12px;flex-wrap:wrap">' +
                            '<button class="button" ' + (page <= 1 ? 'disabled' : '') + ' onclick="abLoadSitemap(' + (page-1) + ')">← Prev</button>' +
                            '<span style="font-size:12px;color:#50575e">Page ' + page + ' of ' + pages + '</span>' +
                            '<button class="button" ' + (page >= pages ? 'disabled' : '') + ' onclick="abLoadSitemap(' + (page+1) + ')">Next →</button>' +
                            '<span style="font-size:12px;color:#888;margin-left:auto">Showing ' + start + '–' + end + ' of ' + total + ' URLs</span>' +
                            '</div>';
                    }

                    wrap.innerHTML =
                        '<p class="ab-sitemap-count"><strong>' + total + '</strong> total URLs across <strong>' + pages + '</strong> sitemap file' + (pages > 1 ? 's' : '') +
                        ' &nbsp;·&nbsp; <a href="' + safeHref(csSeoAdmin.sitemapIndexUrl) + '" target="_blank">View sitemap index ↗</a></p>' +
                        '<table class="ab-sitemap-tbl">' +
                        '<thead><tr><th>URL</th><th>Type</th><th>Last Modified</th></tr></thead>' +
                        '<tbody>' + rows + '</tbody></table>' +
                        pager;
                })
                .catch(e => {
                    btn.disabled = false;
                    btn.textContent = '⬇ Load Preview';
                    wrap.innerHTML = '<p style="color:#c3372b">Error: ' + abEsc(e.message) + '</p>';
                });
        }

        // ── API key guard ─────────────────────────────────────────────────────
        function abCheckApiKey() {
            if (abHasApiKey) return true;
            document.getElementById('ab-api-warn').classList.add('visible');
            abLog('⚠ No API key saved. Scroll up to the ✦ AI Meta Writer section, enter your Anthropic API key and click Save AI Settings, then reload the page.', 'err');
            return false;
        }

        // Show warning banner on page load if no key saved
        if (!abHasApiKey) {
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('ab-api-warn').classList.add('visible');
            });
        }

        // ── Utilities ────────────────────────────────────────────────────────
        function abLog(msg, type) {
            const wrap = document.getElementById('ab-log-wrap');
            const el   = document.getElementById('ab-log');
            if (wrap) wrap.style.display = '';
            el.classList.add('visible');
            const ts  = new Date().toLocaleTimeString('en-GB');
            const cls = type ? 'ab-log-' + type : 'ab-log-line';
            el.innerHTML += '<div class="' + cls + '">[' + ts + '] ' + abEsc(msg) + '</div>';
            el.scrollTop = el.scrollHeight;
        }

        function abEsc(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
        }
        function safeHref(url) {
            var s = String(url).replace(/\s/g,'').toLowerCase();
            if (s.indexOf('javascript:') === 0 || s.indexOf('data:') === 0 || s.indexOf('vbscript:') === 0) return '#';
            return abEsc(url);
        }

        // Title popup — store titles in a plain object to avoid HTML attribute escaping issues
        const abTitleMap = {};

        function abShowTitlePopup(postId, el) {
            // Remove any existing popup
            const existing = document.getElementById('ab-title-popup');
            if (existing) {
                existing.remove();
                if (existing.dataset.postId === String(postId)) return;
            }
            const raw   = abTitleMap[postId] || el.getAttribute('data-title') || '';
            if (!raw) return;
            // Decode HTML entities (WordPress stores titles with e.g. &#8230; &#8217;)
            const txt   = document.createElement('textarea');
            txt.innerHTML = raw;
            const title = txt.value;
            const chars = title.length;
            const isAi  = abTitleMap['_ai_' + postId] === true;

            const rect   = el.getBoundingClientRect();
            const popup  = document.createElement('div');
            popup.id = 'ab-title-popup';
            popup.dataset.postId = String(postId);
            popup.style.cssText = [
                'position:fixed',
                'z-index:99999',
                'background:#1a1a2e',
                'color:#fff',
                'border:1px solid #4f46e5',
                'border-radius:8px',
                'padding:12px 16px',
                'max-width:420px',
                'min-width:240px',
                'box-shadow:0 8px 24px rgba(0,0,0,0.4)',
                'font-size:13px',
                'line-height:1.5',
            ].join(';');
            popup.innerHTML =
                '<div style="font-size:10px;text-transform:uppercase;letter-spacing:0.08em;color:#8080b0;margin-bottom:6px">' +
                    (isAi ? '✦ AI Rewritten Title' : 'SEO Title') + ' · ' + chars + ' chars' +
                '</div>' +
                '<div style="color:#fff;font-weight:600">' + abEsc(title) + '</div>' +
                '<div style="margin-top:8px;font-size:11px;color:#6060a0">Click anywhere to dismiss</div>';
            document.body.appendChild(popup);
            const top  = rect.bottom + 6;
            const left = Math.min(rect.left, window.innerWidth - 440);
            popup.style.top  = top + 'px';
            popup.style.left = Math.max(8, left) + 'px';
        }

        // Decode HTML entities WordPress puts in titles (e.g. &#8211; → –)
        function abDecodeTitle(s) {
            const txt = document.createElement('textarea');
            txt.innerHTML = String(s);
            return txt.value;
        }

        function abSetStatus(msg) {
            document.getElementById('ab-toolbar-status').textContent = msg;
        }

        function abSetProgress(done, total) {
            const pct = total > 0 ? Math.round(done/total*100) : 0;
            document.getElementById('ab-progress').classList.add('visible');
            document.getElementById('ab-progress-fill').style.width = pct + '%';
            document.getElementById('ab-prog-label').textContent =
                done + ' / ' + total + ' processed (' + pct + '%)';
        }

        function abUpdateSummary() {
            const total        = abState.total;
            const hasDesc      = abState.totalWithDesc + abState.generated;
            const hasTitle     = abState.totalWithTitle + abState.generatedTitles;
            const missing      = Math.max(0, total - hasDesc);
            const missingTitle = Math.max(0, total - hasTitle);
            document.getElementById('sum-total').textContent     = total;
            document.getElementById('sum-has').textContent       = hasDesc;
            const mtEl = document.getElementById('sum-missing-title');
            mtEl.textContent = missingTitle;
            mtEl.style.color = missingTitle > 0 ? '#dc2626' : '#6b7280';
            document.getElementById('sum-missing').textContent   = missing;
            document.getElementById('sum-generated').textContent = abState.generated;
            document.getElementById('ab-summary').style.display  = 'grid';
        }

        function abPost(action, extra) {
            const params = new URLSearchParams({action, nonce: abNonce, ...extra});
            return fetch(abAjax, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: params
            }).then(r => r.json());
        }

        // Model lists — kept in JS so we can rebuild the <select> on provider change.
        // display:none on <option> is ignored by Safari; DOM removal is the only reliable cross-browser approach.
        var abAnthropicModels = [
            {value: 'claude-opus-4-6',           label: 'Claude Opus 4.6 (best quality, highest cost)'},
            {value: 'claude-sonnet-4-6',         label: 'Claude Sonnet 4.6 (recommended — quality + speed)'},
            {value: 'claude-sonnet-4-5',         label: 'Claude Sonnet 4.5'},
            {value: 'claude-sonnet-4.20.140514', label: 'Claude Sonnet 4 (stable pinned)'},
            {value: 'claude-haiku-4-5-20251001', label: 'Claude Haiku 4.5 (fastest, cheapest)'},
        ];
        var abGeminiModels = [
            {value: 'gemini-2.0-flash',      label: 'Gemini 2.0 Flash (recommended — stable, fast)'},
            {value: 'gemini-2.0-flash-001',  label: 'Gemini 2.0 Flash 001 (pinned stable)'},
            {value: 'gemini-2.0-flash-lite', label: 'Gemini 2.0 Flash Lite (fast, cheapest 2.0)'},
            {value: 'gemini-1.5-pro',        label: 'Gemini 1.5 Pro (high quality, long context)'},
            {value: 'gemini-1.5-flash',      label: 'Gemini 1.5 Flash (fast, stable)'},
            {value: 'gemini-1.5-flash-8b',   label: 'Gemini 1.5 Flash 8B (smallest, cheapest)'},
        ];

        // ── Test API key ─────────────────────────────────────────────────────
        function abProviderChanged() {
            const provider = document.getElementById('ab-ai-provider').value;
            const isGemini = provider === 'gemini';
            document.getElementById('ab-anthropic-key-field').style.display = isGemini ? 'none' : '';
            document.getElementById('ab-gemini-key-field').style.display    = isGemini ? '' : 'none';
            document.getElementById('ab-key-hint-anthropic').style.display  = isGemini ? 'none' : '';
            document.getElementById('ab-key-hint-gemini').style.display     = isGemini ? '' : 'none';

            // Rebuild model options by removing all provider-specific options and re-inserting
            // only those for the active provider. display:none on <option> is unreliable in
            // Safari on macOS, so we manipulate the DOM directly instead.
            var sel = document.getElementById('ab-model-select');
            if (sel) {
                var prev = sel.value;
                var prevBelongsHere = sel.options[sel.selectedIndex] &&
                    sel.options[sel.selectedIndex].getAttribute('data-provider') === provider;

                // Remove all provider-keyed options
                for (var i = sel.options.length - 1; i >= 0; i--) {
                    if (sel.options[i].getAttribute('data-provider')) sel.remove(i);
                }

                // Find insertion point (before the _custom option)
                var insertBefore = null;
                for (var j = 0; j < sel.options.length; j++) {
                    if (sel.options[j].value === '_custom') { insertBefore = sel.options[j]; break; }
                }

                // Insert models for the active provider
                var models = isGemini ? abGeminiModels : abAnthropicModels;
                models.forEach(function(m) {
                    var opt = document.createElement('option');
                    opt.value = m.value;
                    opt.textContent = m.label;
                    opt.setAttribute('data-provider', provider);
                    sel.insertBefore(opt, insertBefore);
                });

                // Restore previous selection or fall back to _auto
                if (prevBelongsHere) {
                    sel.value = prev;
                } else if (prev !== '_auto' && prev !== '_custom') {
                    sel.value = '_auto';
                    abModelSelectChanged();
                }
            }

            document.getElementById('ab-key-status').textContent = '';

            // Toggle model docs links
            const linkA = document.getElementById('ab-model-link-anthropic');
            const linkG = document.getElementById('ab-model-link-gemini');
            if (linkA) linkA.style.display = isGemini ? 'none' : '';
            if (linkG) linkG.style.display = isGemini ? '' : 'none';
        }

        function abModelSelectChanged() {
            const sel   = document.getElementById('ab-model-select');
            const wrap  = document.getElementById('ab-model-custom-wrap');
            const input = document.getElementById('ab-model-custom-input');
            if (!sel || !wrap) return;
            if (sel.value === '_custom') {
                wrap.style.display = '';
                if (input) input.focus();
            } else {
                wrap.style.display = 'none';
                if (input) input.value = sel.value;
            }
        }

        // On page load: wire select change + sync hidden input
        (function() {
            const sel   = document.getElementById('ab-model-select');
            const input = document.getElementById('ab-model-custom-input');
            if (!sel) return;
            sel.addEventListener('change', abModelSelectChanged);
            // Copy custom model into select value on form submit
            const form = sel.closest('form');
            if (form) {
                form.addEventListener('submit', function() {
                    if (sel.value === '_custom' && input && input.value.trim()) {
                        // Swap the option value so the custom text is what gets submitted
                        const customOpt = sel.querySelector('[value="_custom"]');
                        if (customOpt) customOpt.value = input.value.trim();
                    }
                });
            }
        })();

        function abTestKey() {
            const status   = document.getElementById('ab-key-status');
            const testBtn  = document.getElementById('ab-test-key-btn');
            const provider = document.getElementById('ab-ai-provider').value;
            const keyField = provider === 'gemini'
                ? document.getElementById('ab-gemini-key-field')
                : document.getElementById('ab-anthropic-key-field');
            const key      = keyField.value.trim();
            if (!key) {
                status.textContent = '✗ Enter a key first';
                status.className   = 'ab-key-status ab-key-err';
                return;
            }
            status.textContent = '⟳ Checking firewall & API key…';
            status.className   = 'ab-key-status';
            if (testBtn) { testBtn.disabled = true; testBtn.textContent = '⟳ Testing…'; }

            fetch(abAjax, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action:   'cs_seo_ai_test_key',
                    nonce:    abNonce,
                    live_key: key,
                    provider: provider,
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    status.textContent = '✓ ' + data.data;
                    status.className   = 'ab-key-status ab-key-ok';
                } else {
                    status.textContent = '✗ ' + data.data;
                    status.className   = 'ab-key-status ab-key-err';
                }
            })
            .catch(e => {
                status.textContent = '✗ Network error: ' + e.message;
                status.className   = 'ab-key-status ab-key-err';
            })
            .finally(() => {
                if (testBtn) { testBtn.disabled = false; testBtn.textContent = 'Test Key'; }
            });
        }

        // ── Load posts ───────────────────────────────────────────────────────
        function abTogglePosts(btn) {
            const wrap = document.getElementById('ab-posts-wrap');
            if (!wrap) return;
            const hidden = wrap.style.display === 'none';
            wrap.style.display = hidden ? '' : 'none';
            btn.textContent = hidden ? '↑ Hide Posts' : '↓ Show Posts';
        }

        function abLoadPosts(page) {
            page = page || 1;
            abState.page = page;
            abSetStatus('Loading posts...');
            const rldBtn = document.getElementById('ab-reload-hdr');
            if (rldBtn) { rldBtn.disabled = true; rldBtn.textContent = '⟳ Loading…'; }
            abPost('cs_seo_ai_get_posts', {page}).then(data => {
                if (!data.success) { abLog('Failed to load posts: ' + data.data, 'err'); return; }
                abState.posts          = data.data.posts;
                abState.total          = data.data.total;
                abState.totalWithDesc  = data.data.total_with_desc;
                abState.totalWithTitle = data.data.total_with_title || 0;
                abState.totalPages     = data.data.total_pages;
                abState.page           = data.data.page;
                abUpdateSummary();
                abRenderTable();
                abSetStatus(data.data.total + ' posts & pages loaded');
                document.getElementById('ab-ai-toolbar').style.display = 'flex';
                const rldBtnDone = document.getElementById('ab-reload-hdr');
                if (rldBtnDone) { rldBtnDone.disabled = false; rldBtnDone.textContent = '↻ Reload'; rldBtnDone.style.visibility = 'visible'; }
                document.getElementById('ab-ai-gen-missing').disabled          = false;
                document.getElementById('ab-ai-gen-all').disabled               = false;
                document.getElementById('ab-ai-fix').disabled                   = false;
                document.getElementById('ab-ai-fix-titles').disabled            = false;
                document.getElementById('ab-ai-gen-missing-titles').disabled    = false;
                document.getElementById('ab-ai-static').disabled                = false;
                document.getElementById('ab-ai-score-all').disabled             = false;
                // Pager
                const pager = document.getElementById('ab-pager');
                pager.style.display = abState.totalPages > 1 ? 'flex' : 'none';
                document.getElementById('ab-page-info').textContent =
                    'Page ' + abState.page + ' of ' + abState.totalPages;
                document.getElementById('ab-prev').disabled = abState.page <= 1;
                document.getElementById('ab-next').disabled = abState.page >= abState.totalPages;
            }).catch(e => {
                abLog('Error: ' + e.message, 'err');
                const rldBtnErr = document.getElementById('ab-reload-hdr');
                if (rldBtnErr) { rldBtnErr.disabled = false; rldBtnErr.textContent = '↻ Reload'; }
            });
        }

        function abPage(dir) {
            abLoadPosts(abState.page + dir);
        }

        // ── Sort ─────────────────────────────────────────────────────────────
        function abSortBy(key) {
            if (abState.sortKey === key) {
                abState.sortDir = abState.sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                abState.sortKey = key;
                abState.sortDir = (key === 'date' || key === 'score' || key === 'readability' || key === 'alt') ? 'desc' : 'asc';
            }
            abRenderTable();
        }

        function abSortIcon(key) {
            if (abState.sortKey !== key) return ' <span style="color:#ccc;font-size:10px">\u21c5</span>';
            return abState.sortDir === 'asc'
                ? ' <span style="color:#2271b1;font-size:10px">\u25b2</span>'
                : ' <span style="color:#2271b1;font-size:10px">\u25bc</span>';
        }

        // ── Inline description editor ─────────────────────────────────────────
        function abEditDesc(postId) {
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            post._editing = true;
            post._editDraft = post._gen !== undefined ? post._gen : (post.desc || '');
            abRenderTable();
            const ta = document.getElementById('ab-desc-ta-' + postId);
            if (ta) { ta.focus(); ta.setSelectionRange(ta.value.length, ta.value.length); }
        }

        function abDescInput(postId, value) {
            const post = abState.posts.find(p => p.id === postId);
            if (post) post._editDraft = value;
        }

        function abCancelDesc(postId) {
            const post = abState.posts.find(p => p.id === postId);
            if (post) { post._editing = false; post._editDraft = undefined; }
            abRenderTable();
        }

        function abSaveDesc(postId) {
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            const desc   = post._editDraft !== undefined ? post._editDraft : '';
            const status = document.getElementById('ab-desc-save-status-' + postId);
            if (status) status.textContent = '⟳ Saving…';
            abPost('cs_seo_save_desc', {post_id: postId, desc: desc}).then(data => {
                if (data.success) {
                    post.desc     = data.data.desc;
                    post.has_desc = data.data.chars > 0;
                    post._gen     = undefined;
                    post._editing = false;
                    post._editDraft = undefined;
                    abUpdateSummary();
                    abRenderTable();
                } else {
                    if (status) status.textContent = '✗ ' + (data.data || 'Error');
                }
            }).catch(e => {
                if (status) status.textContent = '✗ ' + e.message;
            });
        }

        // ── Render table ─────────────────────────────────────────────────────
        function abScoreBadge(post) {
            const s = (post._seo_score !== undefined) ? post._seo_score : post.seo_score;
            if (!s) {
                const click = post.no_post ? '' : ' onclick="abScoreOne(' + post.id + ')"';
                return '<span class="ab-score-badge ab-score-none"' + click + ' title="Click to score">Score</span>';
            }
            const cls          = s >= 90 ? 'ab-score-great' : s >= 75 ? 'ab-score-good' : s >= 50 ? 'ab-score-fair' : 'ab-score-poor';
            const scoreClick   = post.no_post ? '' : ' onclick="abScoreOne(' + post.id + ')"';
            const detailsClick = post.no_post ? '' : ' onclick="abShowScoreModal(' + post.id + ')"';
            return '<span class="ab-score-badge ' + cls + '"' + scoreClick + ' title="Click to re-score" style="cursor:pointer">' + s + '%</span>' +
                   '<br><span class="ab-score-badge ab-score-details"' + detailsClick + ' title="View AI feedback">Details</span>';
        }

        function abReadabilityBadge(post) {
            const s = (post._readability_score !== undefined) ? post._readability_score : post.readability_score;
            if (s === null || s === undefined) {
                return '<span class="ab-score-badge ab-score-none" title="Saved on next post save">—</span>';
            }
            const cls = s >= 80 ? 'ab-score-great' : s >= 60 ? 'ab-score-good' : s >= 40 ? 'ab-score-fair' : 'ab-score-poor';
            const lbl = s >= 80 ? 'Easy' : s >= 60 ? 'Moderate' : 'Hard';
            const d   = post.readability_data || {};
            const tip = [
                d.sentence_len    !== undefined ? 'Avg sentence: ' + d.sentence_len + ' words'       : '',
                d.heading_density !== undefined && d.heading_density !== null ? '1 heading / ' + d.heading_density + ' words' : '',
                d.passive_pct     !== undefined ? d.passive_pct + '% passive'                         : '',
            ].filter(Boolean).join(' · ');
            const detailsClick = post.no_post ? '' : ' onclick="abShowReadabilityModal(' + post.id + ')"';
            return '<span class="ab-score-badge ' + cls + '" title="' + (tip || lbl) + '">' + s + '% ' + lbl + '</span>' +
                   '<br><span class="ab-score-badge ab-score-details"' + detailsClick + ' title="View readability details">Details</span>';
        }

        function abShowReadabilityModal(postId) {
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            const s   = (post._readability_score !== undefined) ? post._readability_score : post.readability_score;
            const d   = post.readability_data || {};
            const lbl = !s ? '—' : s >= 80 ? 'Easy' : s >= 60 ? 'Moderate' : 'Hard';

            if (!document.getElementById('ab-r-modal')) {
                const el = document.createElement('div');
                el.id = 'ab-r-modal';
                el.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:16px';
                el.innerHTML =
                    '<div style="background:#fff;border-radius:10px;max-width:440px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.35);overflow:hidden">' +
                        '<div id="ab-r-modal-hdr" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">' +
                            '<strong id="ab-r-modal-title" style="color:#fff;font-size:14px;line-height:1.4;padding-right:12px"></strong>' +
                            '<button type="button" onclick="document.getElementById(\'ab-r-modal\').style.display=\'none\'" style="flex-shrink:0;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:16px;font-weight:700;padding:2px 10px;cursor:pointer;line-height:1">&#10005;</button>' +
                        '</div>' +
                        '<div style="padding:20px 24px">' +
                            '<table id="ab-r-modal-table" style="width:100%;border-collapse:collapse;font-size:13px"></table>' +
                            '<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">' +
                                '<button type="button" onclick="document.getElementById(\'ab-r-modal\').style.display=\'none\'" class="button">Close</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(el);
                el.addEventListener('click', function(e) { if (e.target === el) el.style.display = 'none'; });
            }

            const modal   = document.getElementById('ab-r-modal');
            const hdr     = document.getElementById('ab-r-modal-hdr');
            const titleEl = document.getElementById('ab-r-modal-title');
            const table   = document.getElementById('ab-r-modal-table');

            const bg = !s ? '#666' : s >= 80 ? '#1a7a34' : s >= 60 ? '#2271b1' : s >= 40 ? '#b45309' : '#b91c1c';
            hdr.style.background = bg;
            titleEl.textContent  = post.title + ' — Readability: ' + (s ? s + '% ' + lbl : 'unscored');

            const rows = [
                ['Score',            s !== null && s !== undefined ? s + '% — ' + lbl : '—'],
                ['Avg sentence',     d.sentence_len    !== undefined && d.sentence_len !== null ? d.sentence_len + ' words (ideal ≤ 15)' : '—'],
                ['Heading density',  d.heading_density !== undefined && d.heading_density !== null ? '1 heading per ' + d.heading_density + ' words' : '—'],
                ['Passive voice',    d.passive_pct     !== undefined ? d.passive_pct + '% (target < 5%)' : '—'],
                ['Word count',       d.word_count      !== undefined && d.word_count !== null ? d.word_count + ' words' : '—'],
            ];
            table.innerHTML = rows.map(function(r, i) {
                const bg2 = i % 2 === 0 ? '#f6f7f7' : '#fff';
                return '<tr style="background:' + bg2 + '">' +
                    '<td style="padding:8px 10px;font-weight:600;color:#50575e;width:45%;border-bottom:1px solid #e0e0e0">' + r[0] + '</td>' +
                    '<td style="padding:8px 10px;color:#1d2327;border-bottom:1px solid #e0e0e0">' + r[1] + '</td>' +
                    '</tr>';
            }).join('');

            modal.style.display = 'flex';
        }

        function abBadge(post) {
            if (!post.has_desc && !post._gen) return '<span class="ab-badge ab-badge-none">No AI description</span>';
            const desc  = post._gen || post.desc;
            const chars = desc ? desc.length : 0;
            if (post._gen) {
                if (chars > 0 && chars < abMinChar) return '<span class="ab-badge ab-badge-gen-short">✦ Generated · ' + chars + 'c</span>';
                if (chars > abMaxChar)              return '<span class="ab-badge ab-badge-gen-long">✦ Generated · ' + chars + 'c</span>';
                return '<span class="ab-badge ab-badge-gen">✦ Generated · ' + chars + 'c</span>';
            }
            if (chars >= abMinChar && chars <= abMaxChar) return '<span class="ab-badge ab-badge-ok">✓ ' + chars + 'c</span>';
            if (chars > 0 && chars < abMinChar)           return '<span class="ab-badge ab-badge-short">Short · ' + chars + 'c</span>';
            if (chars > abMaxChar)                         return '<span class="ab-badge ab-badge-long">Long · ' + chars + 'c</span>';
            return '<span class="ab-badge ab-badge-none">No AI description</span>';
        }

        function abRenderTable() {
            const wrap = document.getElementById('ab-posts-wrap');
            if (!abState.posts.length) {
                wrap.innerHTML = '<p style="color:#50575e">No posts found.</p>';
                return;
            }
            let sorted = abState.posts.slice();
            if (abState.sortKey) {
                const pinIdx = sorted.findIndex(p => p.is_homepage);
                const pinned = pinIdx !== -1 ? sorted.splice(pinIdx, 1)[0] : null;
                sorted.sort((a, b) => {
                    let av, bv;
                    if (abState.sortKey === 'title') {
                        av = (a.title || '').toLowerCase();
                        bv = (b.title || '').toLowerCase();
                    } else if (abState.sortKey === 'date') {
                        av = a.date || '';
                        bv = b.date || '';
                    } else if (abState.sortKey === 'desc') {
                        av = (a._gen || a.has_desc) ? 1 : 0;
                        bv = (b._gen || b.has_desc) ? 1 : 0;
                    } else if (abState.sortKey === 'title_chars') {
                        av = a.title_chars || 0;
                        bv = b.title_chars || 0;
                    } else if (abState.sortKey === 'alt') {
                        av = a.missing_alt || 0;
                        bv = b.missing_alt || 0;
                    } else if (abState.sortKey === 'readability') {
                        av = (a._readability_score !== undefined ? a._readability_score : a.readability_score) ?? -1;
                        bv = (b._readability_score !== undefined ? b._readability_score : b.readability_score) ?? -1;
                    } else {
                        av = (a._seo_score !== undefined ? a._seo_score : a.seo_score) ?? -1;
                        bv = (b._seo_score !== undefined ? b._seo_score : b.seo_score) ?? -1;
                    }
                    if (av < bv) return abState.sortDir === 'asc' ? -1 : 1;
                    if (av > bv) return abState.sortDir === 'asc' ? 1 : -1;
                    return 0;
                });
                if (pinned) sorted.unshift(pinned);
            }
            let rows = sorted.map(p => {
                const existDesc = p.desc
                    ? '<div class="ab-desc-text">' + abEsc(p.desc) + '</div>'
                    : '';
                const genDesc = p._gen
                    ? '<div class="ab-desc-gen">✦ ' + abEsc(p._gen) + '</div>'
                    : '';
                let descCell;
                if (p._editing) {
                    descCell =
                        '<textarea id="ab-desc-ta-' + p.id + '" style="width:100%;font-size:12px;min-height:58px;resize:vertical;box-sizing:border-box;margin:4px 0 6px" oninput="abDescInput(' + p.id + ',this.value)">' + abEsc(p._editDraft || '') + '</textarea>' +
                        '<div style="display:flex;gap:6px;align-items:center">' +
                        '<button class="button button-primary" style="font-size:11px;height:24px;line-height:22px;padding:0 10px" onclick="abSaveDesc(' + p.id + ')">Save</button>' +
                        '<button class="button" style="font-size:11px;height:24px;line-height:22px;padding:0 10px" onclick="abCancelDesc(' + p.id + ')">Cancel</button>' +
                        '<span id="ab-desc-save-status-' + p.id + '" style="font-size:11px;color:#888"></span>' +
                        '</div>';
                } else {
                    const editBtn = p.no_post ? '' :
                        ' <button class="button" style="font-size:10px;height:20px;line-height:18px;padding:0 7px;margin-left:5px" onclick="abEditDesc(' + p.id + ')">✏</button>';
                    descCell = abBadge(p) + editBtn + existDesc + genDesc;
                }
                const canGen = !p._processing && !p.no_post;
                // ALT badge — update after generation using alts_saved
                const missingAlt = (p.missing_alt || 0) - (p._alts_saved || 0);
                const altCell = missingAlt > 0
                    ? '<span style="display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;white-space:nowrap">⚠ ' + missingAlt + ' missing</span>'
                    : (p.missing_alt !== undefined
                        ? '<span style="display:inline-block;background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;white-space:nowrap">✓ OK</span>'
                        : '');

                // Title length badge
                const tChars = p._new_title_chars !== undefined ? p._new_title_chars : (p.title_chars || 0);
                const tTitle = p._new_title || p.effective_title || p.title || '';
                const isAiTitle = p._new_title !== undefined;
                // Store title in map for popup — avoids all HTML attribute escaping issues
                if (tTitle) {
                    abTitleMap[p.id] = tTitle;
                    abTitleMap['_ai_' + p.id] = isAiTitle;
                }
                const titleCursor = tTitle ? 'cursor:pointer;' : '';
                const titleDataId = tTitle ? 'data-titleid="' + p.id + '" ' : '';
                let titleBadge;
                if (tChars === 0) {
                    titleBadge = '<span style="color:#aaa;font-size:11px">—</span>';
                } else if (tChars >= 50 && tChars <= 60) {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#d1e7dd;color:#0a3622;border:1px solid #a3cfbb;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">' + tChars + 'c ✓' + (isAiTitle ? ' ✦' : '') + '</span>';
                } else if (tChars >= 40 && tChars <= 69) {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">⚠ ' + tChars + 'c' + (isAiTitle ? ' ✦' : '') + '</span>';
                } else {
                    titleBadge = '<span ' + titleDataId + 'style="display:inline-block;background:#f8d7da;color:#842029;border:1px solid #f5c2c7;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;' + titleCursor + '">✗ ' + tChars + 'c' + (isAiTitle ? ' ✦' : '') + '</span>';
                }

                // Homepage row gets a pinned style and special label
                const isHome   = p.is_homepage;
                const rowStyle = isHome
                    ? 'background:linear-gradient(90deg,#f0f7ff 0%,#fff 100%);border-left:3px solid #2271b1'
                    : '';
                const typeLabel = isHome
                    ? '<span style="background:#2271b1;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700;margin-right:4px">🏠 Homepage</span>'
                    : p.type === 'page'
                    ? '<span style="background:#6b3fa0;color:#fff;border-radius:3px;padding:1px 6px;font-size:10px;font-weight:700;margin-right:4px">📄 Page</span>'
                    : '';
                const noPostNote = p.no_post
                    ? '<span style="color:#888;font-size:12px">Blog posts index — no post object. Set a static front page to enable AI generation.</span>'
                    : '';
                const actionCell = p.no_post
                    ? '<span style="color:#aaa;font-size:12px">N/A</span>'
                    : '<button class="button ab-row-btn" onclick="abGenOne(' + p.id + ')" ' + (canGen?'':'disabled') + ' id="ab-btn-' + p.id + '">' +
                      (p._processing ? '<span class="ab-spinner">⟳</span>' : '✦') + ' Generate</button>';

                const titleLink = p.permalink
                    ? '<a href="' + safeHref(p.permalink) + '" target="_blank" style="color:inherit;text-decoration:none;border-bottom:1px dotted #aaa" title="View page">' + abEsc(abDecodeTitle(p.title)) + '</a>'
                    : abEsc(abDecodeTitle(p.title));
                return '<tr id="ab-row-' + p.id + '" style="' + rowStyle + '">' +
                    '<td><strong>' + typeLabel + titleLink + '</strong>' +
                    noPostNote + '</td>' +
                    '<td style="text-align:center;font-size:12px;color:#555;white-space:nowrap">' + abEsc(p.date || '—') + '</td>' +
                    '<td>' + descCell + '</td>' +
                    '<td style="text-align:center">' + titleBadge + '</td>' +
                    '<td style="text-align:center">' + altCell + '</td>' +
                    '<td style="text-align:center" class="ab-score-cell">' + abScoreBadge(p) + '</td>' +
                    '<td style="text-align:center" class="ab-r-cell">' + abReadabilityBadge(p) + '</td>' +
                    '<td>' + actionCell + '</td>' +
                '</tr>';
            }).join('');

            wrap.innerHTML = '<table class="ab-posts" style="min-width:900px">' +
                '<thead><tr>' +
                '<th style="width:20%;cursor:pointer;user-select:none" onclick="abSortBy(\'title\')">Post' + abSortIcon('title') + '</th>' +
                '<th style="width:8%;text-align:center;cursor:pointer;user-select:none" onclick="abSortBy(\'date\')">Date' + abSortIcon('date') + '</th>' +
                '<th style="width:25%;cursor:pointer;user-select:none" onclick="abSortBy(\'desc\')">Description' + abSortIcon('desc') + '</th>' +
                '<th style="width:6%;text-align:center;cursor:pointer;user-select:none" onclick="abSortBy(\'title_chars\')">Title' + abSortIcon('title_chars') + '</th>' +
                '<th style="width:6%;text-align:center;cursor:pointer;user-select:none" onclick="abSortBy(\'alt\')">ALT' + abSortIcon('alt') + '</th>' +
                '<th style="width:8%;text-align:center;cursor:pointer;user-select:none" onclick="abSortBy(\'score\')">SEO Score' + abSortIcon('score') + '</th>' +
                '<th style="width:10%;text-align:center;cursor:pointer;user-select:none" onclick="abSortBy(\'readability\')">Readability' + abSortIcon('readability') + '</th>' +
                '<th style="width:10%">Action</th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody></table>';
        }

        // ── Generate one post ─────────────────────────────────────────────────
        function abGenOne(postId) {
            if (!abCheckApiKey()) return;
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            post._processing = true;
            abRenderTable();

            const seoNotes = (post._seo_notes !== undefined ? post._seo_notes : (post.seo_notes || ''));
            const oldScore = (post._seo_score !== undefined ? post._seo_score : (post.seo_score || 0)) || 0;
            abLog('→ Sending: score=' + (oldScore || 'none') + (seoNotes ? ', feedback="' + seoNotes + '"' : ', feedback=none'), 'info');
            abPost('cs_seo_ai_generate_one', {post_id: postId, seo_notes: seoNotes, seo_score: oldScore}).then(data => {
                post._processing = false;
                if (data.success) {
                    const d = data.data;
                    // ── Description ──────────────────────────────────────────
                    post._gen     = d.description;
                    post.has_desc = true;
                    post.desc     = d.description;
                    if (d.alts_saved > 0) {
                        post._alts_saved = (post._alts_saved || 0) + d.alts_saved;
                    }
                    if (d.seo_score !== null && d.seo_score !== undefined) {
                        post._seo_score = d.seo_score;
                        post._seo_notes = d.seo_notes || '';
                    }
                    const altNote = d.alts_saved > 0 ? ' + ' + d.alts_saved + ' ALT(s)' : '';
                    const newScore = (d.seo_score !== null && d.seo_score !== undefined) ? d.seo_score : 'none';
                    const scoreDelta = (oldScore && newScore !== 'none') ? ' (' + (newScore >= oldScore ? '+' : '') + (newScore - oldScore) + ')' : '';
                    abLog('← Returned: score=' + newScore + scoreDelta + ', notes="' + (d.seo_notes || '') + '"', 'info');
                    abLog('✓ Description → ' + d.chars + 'c' + altNote + ': ' + d.description, 'ok');

                    // ── Title ─────────────────────────────────────────────────
                    if (d.title_status === 'fixed' || d.title_status === 'fixed_imperfect') {
                        post._new_title       = d.title;
                        post._new_title_chars = d.title_chars;
                        const titleQuality = d.title_status === 'fixed' ? '✓' : '⚠';
                        abLog(titleQuality + ' Title fixed ' + d.title_chars + 'c: ' + d.title, d.title_status === 'fixed' ? 'ok' : 'warn');
                        if (d.title_was) {
                            abLog('  was: ' + d.title_was, 'info');
                        }
                    } else {
                        abLog('  Title already in range (' + d.title_chars + 'c): ' + d.title, 'info');
                    }

                    abState.generated++;
                    abUpdateSummary();
                } else {
                    abLog('✗ "' + post.title.slice(0,45) + '": ' + (data.data || 'Unknown error'), 'err');
                }
                abRenderTable();
                const scoreCell = document.querySelector('#ab-row-' + postId + ' .ab-score-cell');
                if (scoreCell) { scoreCell.style.transition = 'background 0.3s'; scoreCell.style.background = '#d1e7dd'; setTimeout(() => { scoreCell.style.background = ''; }, 1200); }
                // Readability score (pure PHP — no API cost)
                abPost('cs_seo_readability_score_one', {post_id: postId}).then(rd => {
                    if (rd.success) {
                        post._readability_score = rd.data.score;
                        post.readability_data   = rd.data;
                        abRenderTable();
                    }
                }).catch(() => {});
            }).catch(e => {
                post._processing = false;
                abLog('✗ Network error: ' + e.message, 'err');
                abRenderTable();
            });
        }

        // ── Score one post ────────────────────────────────────────────────────
        function abScoreOne(postId) {
            if (!abCheckApiKey()) return;
            const post = abState.posts.find(p => p.id === postId);
            if (!post || post.no_post) return;
            const cell = document.querySelector('#ab-row-' + postId + ' .ab-score-cell');
            if (cell) cell.innerHTML = '<span style="color:#888;font-size:11px">⟳ Scoring…</span>';
            abPost('cs_seo_score_one', {post_id: postId}).then(data => {
                if (data.success) {
                    post._seo_score = data.data.seo_score;
                    post._seo_notes = data.data.seo_notes || '';
                    abLog('📊 SEO score: ' + data.data.seo_score + '% — ' + (data.data.seo_notes || ''), 'info');
                } else {
                    post._seo_score = undefined;
                    abLog('✗ Score error for post ' + postId + ': ' + (data.data || 'Unknown error'), 'err');
                }
                if (cell) cell.innerHTML = abScoreBadge(post);
            }).catch(e => {
                if (cell) cell.innerHTML = abScoreBadge(post);
                abLog('✗ Score network error: ' + e.message, 'err');
            });
        }

        // ── Score feedback modal ──────────────────────────────────────────────
        function abShowScoreModal(postId) {
            const post = abState.posts.find(p => p.id === postId);
            if (!post) return;
            const s = (post._seo_score !== undefined) ? post._seo_score : post.seo_score;
            const n = (post._seo_notes !== undefined) ? post._seo_notes : (post.seo_notes || '');

            if (!document.getElementById('ab-score-modal')) {
                const el = document.createElement('div');
                el.id = 'ab-score-modal';
                el.style.cssText = 'display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.55);align-items:center;justify-content:center;padding:16px';
                el.innerHTML =
                    '<div style="background:#fff;border-radius:10px;max-width:480px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,0.35);overflow:hidden">' +
                        '<div id="ab-score-modal-hdr" style="padding:16px 20px;display:flex;justify-content:space-between;align-items:center">' +
                            '<strong id="ab-score-modal-title" style="color:#fff;font-size:14px;line-height:1.4;padding-right:12px"></strong>' +
                            '<button type="button" onclick="document.getElementById(\'ab-score-modal\').style.display=\'none\'" style="flex-shrink:0;background:rgba(255,255,255,0.2);border:1px solid rgba(255,255,255,0.4);border-radius:5px;color:#fff;font-size:16px;font-weight:700;padding:2px 10px;cursor:pointer;line-height:1">&#10005;</button>' +
                        '</div>' +
                        '<div style="padding:20px 24px">' +
                            '<p id="ab-score-modal-notes" style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#1d2327;background:#f6f7f7;border-radius:6px;padding:12px 14px;border:1px solid #e0e0e0;white-space:pre-wrap"></p>' +
                            '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                                '<button type="button" id="ab-score-modal-copy" class="button button-primary" style="flex:1;min-width:130px">&#128203; Copy Feedback</button>' +
                                '<button type="button" id="ab-score-modal-rescore" class="button">&#8635; Re-score</button>' +
                                '<button type="button" onclick="document.getElementById(\'ab-score-modal\').style.display=\'none\'" class="button">Close</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(el);
                el.addEventListener('click', function(e) { if (e.target === el) el.style.display = 'none'; });
            }

            const modal   = document.getElementById('ab-score-modal');
            const hdr     = document.getElementById('ab-score-modal-hdr');
            const titleEl = document.getElementById('ab-score-modal-title');
            const notesEl = document.getElementById('ab-score-modal-notes');
            const copyBtn = document.getElementById('ab-score-modal-copy');
            const rescore = document.getElementById('ab-score-modal-rescore');

            const bg = !s ? '#666' : s >= 90 ? '#1a7a34' : s >= 75 ? '#2271b1' : s >= 50 ? '#b45309' : '#b91c1c';
            hdr.style.background  = bg;
            titleEl.textContent   = post.title + ' — SEO Score: ' + (s ? s + '%' : 'unscored');
            notesEl.textContent   = n || 'No feedback yet — click Re-score to generate.';

            copyBtn.innerHTML = '&#128203; Copy Feedback';
            copyBtn.onclick = function() {
                if (!n) return;
                navigator.clipboard.writeText(n).then(function() {
                    copyBtn.textContent = '✓ Copied!';
                    setTimeout(function() { copyBtn.innerHTML = '&#128203; Copy Feedback'; }, 2000);
                }).catch(function() {
                    const ta = document.createElement('textarea');
                    ta.value = n;
                    ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy'); // phpcs:ignore
                    document.body.removeChild(ta);
                    copyBtn.textContent = '✓ Copied!';
                    setTimeout(function() { copyBtn.innerHTML = '&#128203; Copy Feedback'; }, 2000);
                });
            };

            rescore.onclick = function() {
                modal.style.display = 'none';
                abScoreOne(postId);
            };

            modal.style.display = 'flex';
        }

        // ── Score all posts ───────────────────────────────────────────────────
        async function abScoreAll() {
            if (!abCheckApiKey()) return;
            if (!abState.posts.length) { abSetStatus('Load posts first'); return; }
            if (abState.running) return;
            abState.running  = true;
            abState.stopped  = false;
            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled = true;
            document.getElementById('ab-ai-fix').disabled = true;
            document.getElementById('ab-ai-fix-titles').disabled = true;
            document.getElementById('ab-ai-static').disabled = true;
            document.getElementById('ab-ai-score-all').disabled = true;
            document.getElementById('ab-ai-stop').style.display = 'inline-block';
            try {
            abLog('Starting SEO scoring run...', 'info');

            let allPosts = [];
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
            }
            const targets = allPosts.filter(p => !p.no_post);
            let done = 0, errors = 0;
            for (let si = 0; si < targets.length; si++) {
                const post = targets[si];
                if (abState.stopped) { abLog('Stopped after ' + done + ' posts scored', 'skip'); break; }
                abSetStatus('Scoring "' + post.title.slice(0, 50) + '" (' + (si + 1) + ' of ' + targets.length + ')');
                abSetProgress(si, targets.length);
                try {
                    const data = await abPost('cs_seo_score_one', {post_id: post.id});
                    if (data.success) {
                        const local = abState.posts.find(p => p.id === post.id);
                        if (local) { local._seo_score = data.data.seo_score; local._seo_notes = data.data.seo_notes || ''; }
                        const cell = document.querySelector('#ab-row-' + post.id + ' .ab-score-cell');
                        if (cell && local) cell.innerHTML = abScoreBadge(local);
                        done++;
                    } else { errors++; }
                } catch(e) { errors++; }
                await abSleep(300);
            }
            abSetProgress(done, targets.length);
            abSetStatus('✓ Scored ' + done + ' posts' + (errors > 0 ? ', ' + errors + ' errors' : ''));
            abLog('Score run complete: ' + done + ' scored, ' + errors + ' errors', done > 0 ? 'ok' : 'info');
            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            abState.running = false;
            document.getElementById('ab-ai-gen-missing').disabled         = false;
            document.getElementById('ab-ai-gen-all').disabled              = false;
            document.getElementById('ab-ai-fix').disabled                  = false;
            document.getElementById('ab-ai-fix-titles').disabled           = false;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = false;
            document.getElementById('ab-ai-static').disabled               = false;
            document.getElementById('ab-ai-score-all').disabled            = false;
            document.getElementById('ab-ai-stop').style.display            = 'none';
            }
        }

        // ── Generate all ──────────────────────────────────────────────────────
        async function abGenAll(overwrite) {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled         = true;
            document.getElementById('ab-ai-gen-all').disabled              = true;
            document.getElementById('ab-ai-fix').disabled                  = true;
            document.getElementById('ab-ai-fix-titles').disabled           = true;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = true;
            document.getElementById('ab-ai-static').disabled               = true;
            document.getElementById('ab-ai-stop').style.display            = 'inline-block';

            try {
            abLog(overwrite ? 'Starting full regeneration run...' : 'Starting generation run (missing only)...', 'info');

            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
            }

            const targets = allPosts.filter(p => !p.is_homepage && !p.no_post && (!p.has_desc || overwrite));
            abLog('Found ' + targets.length + ' posts to process', 'info');

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                abSetStatus('Processing: "' + post.title.slice(0,50) + '"...');
                abSetProgress(done, targets.length);

                try {
                    const local = abState.posts.find(p => p.id === post.id);
                    const bulkSeoNotes = local
                        ? (local._seo_notes !== undefined ? local._seo_notes : (local.seo_notes || ''))
                        : (post.seo_notes || '');
                    const bulkOldScore = local
                        ? ((local._seo_score !== undefined ? local._seo_score : (local.seo_score || 0)) || 0)
                        : (post.seo_score || 0);
                    const data = await abPost('cs_seo_ai_generate_all', {
                        post_id:   post.id,
                        overwrite: overwrite ? 1 : 0,
                        seo_notes: bulkSeoNotes,
                        seo_score: bulkOldScore,
                    });

                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — skipped (has desc)', 'skip');
                        } else {
                            done++;
                            abState.generated++;
                            const bulkAltNote = r.alts_saved > 0 ? ' + ' + r.alts_saved + ' ALT(s)' : '';
                            abLog('✓ Description → ' + r.chars + 'c' + bulkAltNote + ': ' + r.description, 'ok');
                            if (r.title_status === 'fixed' || r.title_status === 'fixed_imperfect') {
                                const tq = r.title_status === 'fixed' ? '✓' : '⚠';
                                abLog(tq + ' Title fixed ' + r.title_chars + 'c: ' + r.title, r.title_status === 'fixed' ? 'ok' : 'warn');
                                if (r.title_was) abLog('  was: ' + r.title_was, 'info');
                            }
                            if (local) {
                                local._gen = r.description; local.has_desc = true; local.desc = r.description;
                                if (r.alts_saved > 0) local._alts_saved = (local._alts_saved || 0) + r.alts_saved;
                                if (r.title_status === 'fixed' || r.title_status === 'fixed_imperfect') {
                                    local._new_title       = r.title;
                                    local._new_title_chars = r.title_chars;
                                }
                                if (r.seo_score !== undefined) { local._seo_score = r.seo_score; local._seo_notes = r.seo_notes || ''; }
                            }
                            // Readability score (pure PHP — no API cost)
                            const rdG = await abPost('cs_seo_readability_score_one', {post_id: post.id}).catch(() => null);
                            if (rdG && rdG.success && local) { local._readability_score = rdG.data.score; local.readability_data = rdG.data; }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000); // longer pause on error — likely a rate limit
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abUpdateSummary();
                abRenderTable();
                await abSleep(2500); // ~24 posts/min — stays under Anthropic's 30k token/min limit
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Done — ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Run complete: ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');
            if (done > 0) abPost('cs_seo_rebuild_health', {}).catch(() => {});

            // Phase 2 (Generate Missing only): score any posts still missing an SEO or readability score.
            if (!overwrite && !abState.stopped) {
                abLog('Phase 2: fetching post list to find unscored posts...', 'info');
                let scoreAllPosts = [];
                for (let pg = 1; pg <= abState.totalPages; pg++) {
                    if (abState.stopped) break;
                    try {
                        const d2 = await abPost('cs_seo_ai_get_posts', {page: pg});
                        if (d2.success) scoreAllPosts = scoreAllPosts.concat(d2.data.posts);
                    } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
                }
                const toScore = scoreAllPosts.filter(p => {
                    if (p.no_post) return false;
                    const local = abState.posts.find(lp => lp.id === p.id);
                    const score = local ? (local._seo_score !== undefined ? local._seo_score : local.seo_score) : p.seo_score;
                    return !score;
                });
                abLog('Phase 2: ' + toScore.length + ' post(s) need scoring', 'info');
                let scoresDone = 0, scoresErr = 0;
                for (let si = 0; si < toScore.length; si++) {
                    const post = toScore[si];
                    if (abState.stopped) { abLog('Stopped during scoring phase after ' + scoresDone + ' scored', 'skip'); break; }
                    abSetStatus('Scoring: "' + post.title.slice(0, 50) + '" (' + (si + 1) + ' of ' + toScore.length + ')');
                    try {
                        const data = await abPost('cs_seo_score_one', {post_id: post.id});
                        if (data.success) {
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) { local._seo_score = data.data.seo_score; local._seo_notes = data.data.seo_notes || ''; }
                            const cell = document.querySelector('#ab-row-' + post.id + ' .ab-score-cell');
                            if (cell && local) cell.innerHTML = abScoreBadge(local);
                            scoresDone++;
                        } else { scoresErr++; }
                        // Also run readability score in the same pass
                        const rdS = await abPost('cs_seo_readability_score_one', {post_id: post.id}).catch(() => null);
                        if (rdS && rdS.success) {
                            const rLocal = abState.posts.find(p => p.id === post.id);
                            if (rLocal) { rLocal._readability_score = rdS.data.score; rLocal.readability_data = rdS.data; }
                        }
                    } catch(e) { scoresErr++; }
                    await abSleep(300);
                }
                if (toScore.length > 0) {
                    abLog('Phase 2 complete: ' + scoresDone + ' scored' + (scoresErr > 0 ? ', ' + scoresErr + ' errors' : ''), scoresDone > 0 ? 'ok' : 'info');
                    abSetStatus('Done — ' + scoresDone + ' posts scored');
                }
            }

            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-ai-gen-missing').disabled = false;
            document.getElementById('ab-ai-gen-all').disabled      = false;
            document.getElementById('ab-ai-fix').disabled          = false;
            document.getElementById('ab-ai-static').disabled       = false;
            document.getElementById('ab-ai-stop').style.display    = 'none';
            abState.running = false;
            }
        }

        // ── Fix out-of-range descriptions ──────────────────────────────────────
        async function abFixAll() {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled     = true;
            document.getElementById('ab-ai-fix').disabled         = true;
            document.getElementById('ab-ai-static').disabled      = true;
            document.getElementById('ab-ai-stop').style.display   = 'inline-block';

            try {
            abLog('Starting fix run — scanning for short and long descriptions...', 'info');

            // Fetch all posts across all pages.
            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
            }

            // Target only posts that have a description but it's outside the configured range.
            const targets = allPosts.filter(p => {
                if (!p.has_desc || !p.desc) return false;
                const len = p.desc.length;
                return len < abMinChar || len > abMaxChar;
            });

            if (targets.length === 0) {
                abLog('No out-of-range descriptions found — nothing to fix.', 'info');
                abSetStatus('Nothing to fix.');
                return;
            }

            abLog('Found ' + targets.length + ' descriptions to fix (' + abMinChar + '–' + abMaxChar + ' char target)', 'info');

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                const len = post.desc ? post.desc.length : 0;
                const issue = len < abMinChar ? 'too short (' + len + 'c)' : 'too long (' + len + 'c)';
                abSetStatus('Fixing: "' + post.title.slice(0,50) + '" — ' + issue);
                abSetProgress(done, targets.length);

                try {
                    const data = await abPost('cs_seo_ai_fix_desc', {post_id: post.id});

                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — ' + r.message, 'skip');
                        } else {
                            done++;
                            abState.generated++;
                            if (r.in_range) {
                                abLog('✓ "' + post.title.slice(0,55) + '" fixed: ' + r.was_chars + 'c → ' + r.chars + 'c', 'ok');
                            } else {
                                abLog('⚠ "' + post.title.slice(0,55) + '" still out of range after retries: ' + r.was_chars + 'c → ' + r.chars + 'c', 'err');
                            }
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) { local._gen = r.description; local.has_desc = true; local.desc = r.description; }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000);
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abUpdateSummary();
                abRenderTable();
                await abSleep(2500);
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Fix run done — ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Fix run complete: ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-ai-gen-missing').disabled    = false;
            document.getElementById('ab-ai-gen-all').disabled         = false;
            document.getElementById('ab-ai-fix').disabled             = false;
            document.getElementById('ab-ai-fix-titles').disabled      = false;
            document.getElementById('ab-ai-static').disabled          = false;
            document.getElementById('ab-ai-stop').style.display       = 'none';
            abState.running = false;
            }
        }

        // ── Fix Titles ────────────────────────────────────────────────────────
        async function abFixTitles() {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled         = true;
            document.getElementById('ab-ai-gen-all').disabled              = true;
            document.getElementById('ab-ai-fix').disabled                  = true;
            document.getElementById('ab-ai-fix-titles').disabled           = true;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = true;
            document.getElementById('ab-ai-static').disabled               = true;
            document.getElementById('ab-ai-stop').style.display            = 'inline-block';

            try {
            abLog('Starting title fix run — scanning for titles outside 50–60 chars...', 'info');

            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
            }

            const targets = allPosts.filter(p => !p.is_homepage && !p.no_post && p.title_chars > 0 && (p.title_chars < 50 || p.title_chars > 60));
            abLog('Found ' + targets.length + ' title(s) outside 50–60 char range', 'info');

            if (targets.length === 0) {
                abLog('All titles are within range — nothing to fix.', 'info');
                abSetStatus('Nothing to fix.');
                return;
            }

            let done = 0, errors = 0, skipped = 0;

            for (const post of targets) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                const issue = post.title_chars < 50 ? 'too short (' + post.title_chars + 'c)' : 'too long (' + post.title_chars + 'c)';
                abSetStatus('Fixing title: "' + post.title.slice(0,50) + '" — ' + issue);
                abSetProgress(done, targets.length);

                try {
                    const data = await abPost('cs_seo_ai_fix_title', {post_id: post.id});
                    if (data.success) {
                        const r = data.data;
                        if (r.status === 'skipped') {
                            skipped++;
                            abLog('⊘ "' + post.title.slice(0,55) + '" — already in range', 'skip');
                        } else {
                            done++;
                            const local = abState.posts.find(p => p.id === post.id);
                            if (local) {
                                local._new_title       = r.title;
                                local._new_title_chars = r.chars;
                            }
                            if (r.in_range) {
                                abLog('✓ Title fixed ' + r.was_chars + 'c → ' + r.chars + 'c: ' + r.title, 'ok');
                                abLog('  was: ' + (post.effective_title || post.title), 'info');
                            } else {
                                abLog('⚠ Title still out of range ' + r.was_chars + 'c → ' + r.chars + 'c: ' + r.title, 'warn');
                                abLog('  was: ' + (post.effective_title || post.title), 'info');
                            }
                        }
                    } else {
                        errors++;
                        const msg = typeof data.data === 'object' ? data.data.message : data.data;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + msg, 'err');
                        await abSleep(12000);
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                    await abSleep(12000);
                }

                abRenderTable();
                await abSleep(2000);
            }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Title fix done — ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Title fix complete: ' + done + ' fixed, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-ai-gen-missing').disabled         = false;
            document.getElementById('ab-ai-gen-all').disabled              = false;
            document.getElementById('ab-ai-fix').disabled                  = false;
            document.getElementById('ab-ai-fix-titles').disabled           = false;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = false;
            document.getElementById('ab-ai-static').disabled               = false;
            document.getElementById('ab-ai-stop').style.display            = 'none';
            abState.running = false;
            }
        }

        async function abGenMissingTitles() {
            if (!abCheckApiKey()) return;
            if (abState.running) return;
            abState.stopped = false;
            abState.running = true;

            document.getElementById('ab-ai-gen-missing').disabled         = true;
            document.getElementById('ab-ai-gen-all').disabled              = true;
            document.getElementById('ab-ai-fix').disabled                  = true;
            document.getElementById('ab-ai-fix-titles').disabled           = true;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = true;
            document.getElementById('ab-ai-static').disabled               = true;
            document.getElementById('ab-ai-stop').style.display            = 'inline-block';

            try {
            abLog('Generating missing titles — fetching full post list...', 'info');

            let allPosts = [];
            abSetStatus('Fetching full post list...');
            for (let pg = 1; pg <= abState.totalPages; pg++) {
                if (abState.stopped) break;
                try {
                    const data = await abPost('cs_seo_ai_get_posts', {page: pg});
                    if (data.success) allPosts = allPosts.concat(data.data.posts);
                } catch(e) { console.error('[cs-seo] page-fetch failed (pg=' + pg + ')', e); }
            }

            const targets = allPosts.filter(p => !p.is_homepage && !p.no_post && !p.has_title);
            abLog('Found ' + targets.length + ' post(s) with no SEO title', 'info');

            if (targets.length === 0) {
                abLog('All posts have a title tag — nothing to generate.', 'info');
                abSetStatus('Nothing to generate.');
                return;
            }

            let done = 0, errors = 0, skipped = 0;
            const queue = targets.slice();

            async function titleWorker() {
                while (queue.length > 0 && !abState.stopped) {
                    const post = queue.shift();
                    if (!post) break;

                    abSetStatus('Generating titles — ' + (done + skipped + errors) + '/' + targets.length + ' done, ' + queue.length + ' remaining');
                    abSetProgress(done + skipped + errors, targets.length);

                    try {
                        const data = await abPost('cs_seo_ai_gen_missing_title', {post_id: post.id});
                        if (data.success) {
                            const r = data.data;
                            if (r.status === 'skipped') {
                                skipped++;
                                abLog('⊘ "' + post.title.slice(0, 55) + '" — already has a title', 'skip');
                            } else {
                                done++;
                                abState.generatedTitles++;
                                const local = abState.posts.find(p => p.id === post.id);
                                if (local) {
                                    local.has_title        = true;
                                    local._new_title       = r.title;
                                    local._new_title_chars = r.chars;
                                }
                                if (r.in_range) {
                                    abLog('✓ Title → ' + r.chars + 'c: ' + r.title, 'ok');
                                } else {
                                    abLog('⚠ Title generated (' + r.chars + 'c, outside range): ' + r.title, 'warn');
                                }
                                abUpdateSummary();
                            }
                        } else {
                            errors++;
                            const msg = typeof data.data === 'object' ? data.data.message : data.data;
                            abLog('✗ "' + post.title.slice(0, 45) + '": ' + msg, 'err');
                            await abSleep(5000);
                        }
                    } catch(e) {
                        errors++;
                        abLog('✗ Network error: ' + e.message, 'err');
                        await abSleep(5000);
                    }

                    abRenderTable();
                }
            }

            if (abState.stopped) { abLog('Stopped by user', 'skip'); }
            else { await Promise.all(Array.from({length: Math.min(3, targets.length)}, titleWorker)); }

            abSetProgress(done + skipped, targets.length);
            abSetStatus('Done — ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors');
            abLog('Generate missing titles complete: ' + done + ' generated, ' + skipped + ' skipped, ' + errors + ' errors', done > 0 ? 'ok' : 'info');

            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-ai-gen-missing').disabled         = false;
            document.getElementById('ab-ai-gen-all').disabled              = false;
            document.getElementById('ab-ai-fix').disabled                  = false;
            document.getElementById('ab-ai-fix-titles').disabled           = false;
            document.getElementById('ab-ai-gen-missing-titles').disabled   = false;
            document.getElementById('ab-ai-static').disabled               = false;
            document.getElementById('ab-ai-stop').style.display            = 'none';
            abState.running = false;
            }
        }

        async function abRegenStatic() {
            if (abState.running) return;
            abState.running = true;
            abState.stopped = false;

            document.getElementById('ab-ai-gen-missing').disabled = true;
            document.getElementById('ab-ai-gen-all').disabled     = true;
            document.getElementById('ab-ai-fix').disabled         = true;
            document.getElementById('ab-ai-static').disabled      = true;
            document.getElementById('ab-ai-stop').style.display   = 'inline-block';

            try {
            abLog('Starting static regeneration — clearing stale OG image data for all posts...', 'info');
            abSetStatus('Regenerating static data...');

            let done = 0, cleared = 0, errors = 0;

            for (const post of abState.posts) {
                if (abState.stopped) { abLog('Stopped by user after ' + done + ' posts', 'skip'); break; }

                abSetStatus('Processing: "' + post.title.slice(0,50) + '"');
                abSetProgress(done, abState.posts.length);

                try {
                    const data = await abPost('cs_seo_regen_static', {post_id: post.id});
                    if (data.success) {
                        const r = data.data;
                        done++;
                        if (r.had_custom) {
                            cleared++;
                            const src = r.source === 'featured_image' ? 'now using featured image'
                                      : r.source === 'site_default'   ? 'now using site default OG image'
                                      : 'no image found';
                            abLog('✓ "' + post.title.slice(0,55) + '" — cleared stale custom image, ' + src, 'ok');
                        } else {
                            abLog('⊘ "' + post.title.slice(0,55) + '" — no custom image was set, nothing to clear', 'skip');
                        }
                    } else {
                        errors++;
                        abLog('✗ "' + post.title.slice(0,45) + '": ' + data.data, 'err');
                    }
                } catch(e) {
                    errors++;
                    abLog('✗ Network error: ' + e.message, 'err');
                }

                await abSleep(300);
            }

            abSetProgress(done, abState.posts.length);
            abSetStatus('Static regen done — ' + cleared + ' posts updated, ' + errors + ' errors');
            abLog('Static regeneration complete: ' + cleared + ' OG images cleared, ' + (done - cleared) + ' already clean, ' + errors + ' errors', cleared > 0 ? 'ok' : 'info');

            } catch(e) { abLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-ai-gen-missing').disabled = false;
            document.getElementById('ab-ai-gen-all').disabled     = false;
            document.getElementById('ab-ai-fix').disabled         = false;
            document.getElementById('ab-ai-static').disabled      = false;
            document.getElementById('ab-ai-stop').style.display   = 'none';
            abState.running = false;
            }
        }

        function abStop() { abState.stopped = true; abSetStatus('Stopping...'); }
        function abSleep(ms) { return new Promise(r => setTimeout(r, ms)); }

        // ═══════════════════════════════════════════════════════════════════════
        // ALT Text Generator
        // ═══════════════════════════════════════════════════════════════════════

        const altState = {
            posts:   [],
            running: false,
            stopped: false,
            fixed:   0,
            page:    0,
        };

        function altLog(msg, type) {
            const wrap = document.getElementById('ab-alt-log-wrap');
            const log  = document.getElementById('ab-alt-log');
            if (wrap) wrap.style.display = '';
            if (log)  log.style.display  = 'block';
            const ts   = new Date().toLocaleTimeString('en-GB');
            const line = document.createElement('div');
            line.className = type ? 'ab-log-' + type : 'ab-log-line';
            line.textContent = '[' + ts + '] ' + msg;
            log.appendChild(line);
            log.scrollTop = log.scrollHeight;
        }

        function altSetStatus(msg) {
            document.getElementById('ab-alt-status').textContent = msg;
        }

        function altSetProgress(done, total) {
            const pct = total > 0 ? Math.round(done/total*100) : 0;
            document.getElementById('ab-alt-progress').classList.add('visible');
            document.getElementById('ab-alt-progress-fill').style.width = pct + '%';
            document.getElementById('ab-alt-prog-label').textContent =
                done + ' / ' + total + ' processed (' + pct + '%)';
        }

        function altUpdateSummary() {
            const totalMissing = altState.posts.reduce((a, p) => a + p.missing_count, 0);
            const remaining    = Math.max(0, totalMissing - altState.fixed);
            document.getElementById('alt-sum-posts').textContent  = altState.posts.reduce((a,p)=>a+(p.missing_count>0?1:0),0);
            document.getElementById('alt-sum-images').textContent = remaining;
            document.getElementById('alt-sum-done').textContent   = altState.fixed;
            document.getElementById('ab-alt-summary').style.display = 'grid';
        }

        function altRenderTable() {
            const wrap = document.getElementById('ab-alt-posts-wrap');

            // Sync the toolbar checkbox to current state
            const cbx = document.getElementById('ab-alt-show-all');
            if (cbx) cbx.checked = altState.showAll || false;

            const showAll = altState.showAll || false;
            const visiblePosts = showAll
                ? altState.posts
                : altState.posts.filter(p => p.missing_count > 0 || p._done);

            if (!altState.posts.length) {
                wrap.innerHTML = '<p style="color:#1a7a34;margin-top:12px">✓ No images found in posts or featured images.</p>';
                return;
            }

            if (!visiblePosts.length) {
                wrap.innerHTML = '<p style="color:#1a7a34">✓ All images across all posts already have ALT text.</p>';
                return;
            }

            const PAGE_SIZE  = 50;
            const totalPages = Math.ceil(visiblePosts.length / PAGE_SIZE);
            if (altState.page >= totalPages) altState.page = Math.max(0, totalPages - 1);
            const pageStart  = altState.page * PAGE_SIZE;
            const pagePosts  = visiblePosts.slice(pageStart, pageStart + PAGE_SIZE);

            let rows = pagePosts.map(p => {
                const hasMissing = p.missing_count > 0 && !p._done;
                const statusBadge = p._done
                    ? '<span class="ab-badge ab-badge-ok">✓ Fixed</span>'
                    : hasMissing
                        ? '<span class="ab-badge ab-badge-none">' + p.missing_count + ' missing</span>'
                        : '<span class="ab-badge ab-badge-ok">✓ All ALT set</span>';

                // Build image rows
                const imgRows = (p.images || []).map(img => {
                    const filename = img.src.split('/').pop().split('?')[0];
                    const missing  = img.missing && !p._done;
                    const altText  = img.alt || (p._done ? '(generated this session)' : '');
                    return '<tr style="background:' + (missing ? '#fff8f8' : '#f8fff8') + '">' +
                        '<td style="padding:6px 8px;width:60px;vertical-align:middle">' +
                            '<img src="' + abEsc(img.src) + '" style="width:52px;height:40px;object-fit:cover;border-radius:3px;border:1px solid #ddd">' +
                        '</td>' +
                        '<td style="padding:6px 8px;font-size:12px;color:#555;vertical-align:middle;word-break:break-all">' +
                            abEsc(filename) +
                        '</td>' +
                        '<td style="padding:6px 8px;font-size:12px;vertical-align:middle">' +
                            (missing
                                ? '<span style="color:#c00">✗ Missing</span>'
                                : '<span style="color:#1a7a34">✓ </span><em style="color:#444">' + abEsc(altText) + '</em>') +
                        '</td>' +
                    '</tr>';
                }).join('');

                const expanded = p._expanded || false;
                const imgCount = (p.images || []).length;
                const toggleId = 'ab-alt-toggle-' + p.id;

                const altTitleLink = p.edit_link
                    ? '<a href="' + safeHref(p.edit_link) + '" target="_blank" style="color:inherit;text-decoration:none;border-bottom:1px dotted #aaa" title="Edit post">' + abEsc(abDecodeTitle(p.title)) + '</a>'
                    : abEsc(abDecodeTitle(p.title));
                return '<tr id="ab-alt-row-' + p.id + '" style="border-top:2px solid #e0e0e0">' +
                    '<td style="padding:8px 10px;vertical-align:middle">' +
                        '<strong>' + altTitleLink + '</strong>' +
                        '<br><small style="color:#888">' + abEsc(p.type) + ' · ' + abEsc(p.date) + ' · ' + abEsc(imgCount) + ' image(s)</small>' +
                    '</td>' +
                    '<td style="padding:8px 10px;vertical-align:middle">' + statusBadge + '</td>' +
                    '<td style="padding:8px 10px;vertical-align:middle;white-space:nowrap">' +
                        '<button class="button ab-row-btn" onclick="altGenOne(' + p.id + ', 1)" id="ab-alt-btn-' + p.id + '" ' + (p._processing?'disabled':'') + '>' +
                            (p._processing ? '<span class="ab-spinner">⟳</span>' : '✦') + ' Generate</button> ' +
                        '<button class="button" style="font-size:11px;padding:2px 8px" id="' + toggleId + '" onclick="altToggleImages(' + p.id + ')">' +
                            (expanded ? '▲ Hide' : '▼ Images') +
                        '</button>' +
                    '</td>' +
                '</tr>' +
                '<tr id="ab-alt-imgs-' + p.id + '" style="display:' + (expanded?'table-row':'none') + '">' +
                    '<td colspan="3" style="padding:0 0 8px 20px;background:#fafafa">' +
                        '<table style="width:100%;border-collapse:collapse">' +
                        '<thead><tr>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left;width:60px">Thumb</th>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left">Filename</th>' +
                            '<th style="padding:4px 8px;font-size:11px;color:#888;text-align:left">ALT Text</th>' +
                        '</tr></thead>' +
                        '<tbody>' + imgRows + '</tbody>' +
                        '</table>' +
                    '</td>' +
                '</tr>';
            }).join('');

            let altPager = '';
            if (totalPages > 1) {
                const from = pageStart + 1;
                const to   = Math.min(pageStart + PAGE_SIZE, visiblePosts.length);
                altPager = '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#50575e">' +
                    '<button class="button" onclick="altState.page--;altRenderTable()" ' + (altState.page === 0 ? 'disabled' : '') + '>← Prev</button>' +
                    '<span>Page ' + (altState.page + 1) + ' of ' + totalPages + ' &nbsp;·&nbsp; ' + from + '–' + to + ' of ' + visiblePosts.length + '</span>' +
                    '<button class="button" onclick="altState.page++;altRenderTable()" ' + (altState.page >= totalPages - 1 ? 'disabled' : '') + '>Next →</button>' +
                    '</div>';
            }

            wrap.innerHTML =
                '<table class="ab-posts" style="width:100%;min-width:560px">' +
                '<thead><tr><th style="width:45%">Post</th><th style="width:20%">Status</th><th style="width:35%">Actions</th></tr></thead>' +
                '<tbody>' + rows + '</tbody></table>' + altPager;
        }

        function altToggleImages(postId) {
            const post = altState.posts.find(p => p.id === postId);
            if (!post) return;
            post._expanded = !post._expanded;
            altRenderTable();
        }

        function altTogglePosts(btn) {
            const wrap = document.getElementById('ab-alt-posts-wrap');
            if (!wrap) return;
            const hidden = wrap.style.display === 'none';
            wrap.style.display = hidden ? '' : 'none';
            btn.textContent = hidden ? '↑ Hide Posts' : '↓ Show Posts';
        }

        function altLoad() {
            altSetStatus('Scanning posts...');
            const altRldBtn = document.getElementById('ab-alt-reload-hdr');
            if (altRldBtn) { altRldBtn.disabled = true; altRldBtn.textContent = '⟳ Loading…'; }
            abPost('cs_seo_alt_get_posts', {}).then(data => {
                if (!data.success) { altLog('Failed to scan: ' + data.data, 'err'); if (altRldBtn) { altRldBtn.disabled = false; altRldBtn.textContent = '↻ Reload'; } return; }
                altState.posts = data.data.posts;
                // Auto-enable show-all when nothing is missing so the audit view is useful
                if (data.data.missing_alt === 0) altState.showAll = true;
                const cbx = document.getElementById('ab-alt-show-all');
                if (cbx) cbx.checked = altState.showAll;
                altUpdateSummary();
                altRenderTable();
                altState.page = 0;
                document.getElementById('ab-alt-toolbar').style.display  = 'flex';
                if (altRldBtn) { altRldBtn.disabled = false; altRldBtn.textContent = '↻ Reload'; altRldBtn.style.visibility = 'visible'; }
                document.getElementById('ab-alt-gen-all').disabled       = data.data.missing_alt === 0;
                const total = data.data.missing_alt;
                altSetStatus(total > 0
                    ? total + ' image(s) missing ALT across ' + altState.posts.filter(p=>p.missing_count>0).length + ' post(s)'
                    : '✓ All images have ALT text');
                if (total === 0) {
                    altLog('✓ All images across all posts already have ALT text.', 'ok');
                }
            }).catch(e => {
                altLog('Error: ' + e.message, 'err');
                if (altRldBtn) { altRldBtn.disabled = false; altRldBtn.textContent = '↻ Reload'; }
            });
        }

        function altGenOne(postId, force) {
            if (!abCheckApiKey()) return;
            const post = altState.posts.find(p => p.id === postId);
            if (!post) return;
            post._processing = true;
            post._expanded   = true;
            altRenderTable();

            abPost('cs_seo_alt_generate_one', {post_id: postId, force: force ? 1 : 0}).then(data => {
                post._processing = false;
                if (data.success) {
                    const updated   = data.data.updated;
                    const generated = data.data.generated || [];
                    post._done = true;
                    post.missing_count = 0;
                    // Store the generated alt text back onto each image object by src match
                    (post.images || []).forEach(img => {
                        img.missing = false;
                        const match = generated.find(g => g.src === img.src);
                        if (match) img.alt = match.alt;
                    });
                    altState.fixed += updated;
                    altLog('✓ "' + abDecodeTitle(post.title).slice(0,55) + '" — ' + updated + ' image(s) updated', 'ok');
                    altUpdateSummary();
                } else {
                    altLog('✗ "' + abDecodeTitle(post.title).slice(0,45) + '": ' + (data.data || 'Unknown error'), 'err');
                }
                altRenderTable();
            }).catch(e => {
                post._processing = false;
                altLog('✗ Network error: ' + e.message, 'err');
                altRenderTable();
            });
        }

        async function altGenAll(force) {
            if (!abCheckApiKey()) return;
            if (altState.running) return;

            if (force && !confirm('This will regenerate ALT text for ALL images across ALL posts, overwriting existing ALT text. Continue?')) return;

            altState.running = true;
            altState.stopped = false;

            document.getElementById('ab-alt-gen-all').disabled     = true;
            document.getElementById('ab-alt-force-all').disabled   = true;
            document.getElementById('ab-alt-stop').style.display   = 'inline-block';

            try {
            altLog('Starting ALT text generation run' + (force ? ' (FORCE mode — all images)' : '') + '...', 'info');

            // In force mode: process all posts with images. In normal mode: only posts with missing ALT.
            const postsToProcess = force
                ? altState.posts.filter(p => !p._done)
                : altState.posts.filter(p => !p._done && p.missing_count > 0);
            altLog(postsToProcess.length + ' post(s) to process', 'info');

            let done = 0, errors = 0, totalFixed = 0;

            for (const post of postsToProcess) {
                if (altState.stopped) { altLog('Stopped after ' + done + ' posts', 'skip'); break; }
                if (post._done) continue;

                altSetStatus('Processing: "' + post.title.slice(0,50) + '"...');
                altSetProgress(done, postsToProcess.length);
                post._processing = true;
                altRenderTable();

                try {
                    const data = await abPost('cs_seo_alt_generate_all', {post_id: post.id, force: force ? 1 : 0});
                    post._processing = false;
                    if (data.success) {
                        const updated   = data.data.updated;
                        const generated = data.data.generated || [];
                        post._done = true;
                        post.missing_count = 0;
                        (post.images || []).forEach(img => {
                            img.missing = false;
                            const match = generated.find(g => g.src === img.src);
                            if (match) img.alt = match.alt;
                        });
                        totalFixed += updated;
                        altState.fixed += updated;
                        altLog('✓ "' + abDecodeTitle(post.title).slice(0,55) + '" — ' + updated + ' image(s) updated', 'ok');
                    } else {
                        errors++;
                        altLog('✗ "' + post.title.slice(0,45) + '": ' + (data.data || 'Error'), 'err');
                        await new Promise(r => setTimeout(r, 5000));
                    }
                } catch(e) {
                    post._processing = false;
                    errors++;
                    altLog('✗ Network error: ' + e.message, 'err');
                    await new Promise(r => setTimeout(r, 5000));
                }

                done++;
                altUpdateSummary();
                altRenderTable();
                await new Promise(r => setTimeout(r, 1500));
            }

            altSetProgress(done, postsToProcess.length);
            altSetStatus('Done — ' + totalFixed + ' image(s) updated across ' + done + ' post(s), ' + errors + ' errors');
            altLog('Run complete: ' + totalFixed + ' images updated, ' + errors + ' errors', totalFixed > 0 ? 'ok' : 'info');
            if (totalFixed > 0) abPost('cs_seo_rebuild_health', {}).catch(() => {});

            } catch(e) { altLog('✗ Unexpected error: ' + e.message, 'err'); } finally {
            document.getElementById('ab-alt-gen-all').disabled     = false;
            document.getElementById('ab-alt-force-all').disabled   = false;
            document.getElementById('ab-alt-stop').style.display   = 'none';
            altState.running = false;
            }
        }

        function altStop() { altState.stopped = true; altSetStatus('Stopping...'); }

        // ── AI Summary Box bulk generator ─────────────────────────────────────
        const sumState = { running: false, stopped: false, done: 0, total: 0, missing: 0, page: 0 };

        function sumLog(msg, cls) {
            const wrap = document.getElementById('ab-sum-log-wrap');
            const log  = document.getElementById('ab-sum-log');
            wrap.style.display = 'block';
            const d = document.createElement('div');
            d.className = 'ab-log-entry' + (cls ? ' ' + cls : '');
            d.textContent = msg;
            log.prepend(d);
        }

        function sumSetStatus(msg) {
            document.getElementById('ab-sum-status').textContent = msg;
            document.getElementById('ab-sum-prog-label').textContent = msg;
        }

        function sumSetProgress(pct) {
            document.getElementById('ab-sum-progress-fill').style.width = pct + '%';
        }

        async function sumLoad() {
            sumSetStatus('Loading...');
            const sumRldBtn = document.getElementById('ab-sum-reload-hdr');
            if (sumRldBtn) { sumRldBtn.disabled = true; sumRldBtn.textContent = '⟳ Loading…'; }
            try {
                const data = await abPost('cs_seo_summary_load', {});
                if (!data.success) { sumSetStatus('Error: ' + (data.data || 'Unknown')); return; }
                const d = data.data;
                sumState.page    = 0;
                sumState.total   = d.total;
                sumState.missing = d.missing;
                document.getElementById('sum-s-total').textContent   = d.total;
                document.getElementById('sum-s-has').textContent     = d.has;
                document.getElementById('sum-s-missing').textContent = d.missing;
                document.getElementById('sum-s-done').textContent    = 0;
                document.getElementById('ab-sum-summary').style.display = '';
                document.getElementById('ab-sum-toolbar').style.display = '';
                document.getElementById('ab-sum-gen-all').disabled = d.missing === 0;
                sumSetStatus(d.missing === 0 ? '✓ All posts have summaries' : d.missing + ' posts need summaries');
                sumState.posts = d.posts || [];
                sumRenderTable();
                if (sumRldBtn) { sumRldBtn.style.visibility = 'visible'; }
            } finally {
                if (sumRldBtn) { sumRldBtn.disabled = false; sumRldBtn.textContent = '↻ Reload'; }
            }
        }

        function sumRenderTable() {
            const wrap = document.getElementById('ab-sum-posts-wrap');
            if (!wrap) return;
            const posts = sumState.posts || [];
            if (!posts.length) {
                wrap.innerHTML = '<p style="color:#1a7a34;margin-top:8px">✓ No posts found.</p>';
                return;
            }
            const SUM_PAGE_SIZE  = 50;
            const sumTotalPages  = Math.ceil(posts.length / SUM_PAGE_SIZE);
            if (sumState.page >= sumTotalPages) sumState.page = Math.max(0, sumTotalPages - 1);
            const sumPageStart   = sumState.page * SUM_PAGE_SIZE;
            const pagePosts      = posts.slice(sumPageStart, sumPageStart + SUM_PAGE_SIZE);

            let rows = pagePosts.map(function(p) {
                const done   = p._done;
                const badge  = done
                    ? '<span class="ab-badge ab-badge-ok">✓ Generated</span>'
                    : p.has_sum
                        ? '<span class="ab-badge ab-badge-ok">✓ Has Summary</span>'
                        : '<span class="ab-badge ab-badge-none">Missing</span>';
                const sumTitleLink = p.edit_link
                    ? '<a href="' + safeHref(p.edit_link) + '" target="_blank" style="color:inherit;text-decoration:none;border-bottom:1px dotted #aaa" title="Edit post">' + abEsc(p.title) + '</a>'
                    : abEsc(p.title);
                return '<tr id="ab-sum-row-' + p.id + '">' +
                    '<td style="padding:6px 10px;font-size:13px;color:#1d2327">' + sumTitleLink + '</td>' +
                    '<td style="padding:6px 10px;text-align:center" class="ab-sum-status-cell">' + badge + '</td>' +
                    '<td style="padding:6px 10px;text-align:right"><button class="button ab-row-btn" onclick="sumGenOne(' + p.id + ')">✦ Generate</button></td>' +
                    '</tr>';
            }).join('');

            let sumPager = '';
            if (sumTotalPages > 1) {
                const from = sumPageStart + 1;
                const to   = Math.min(sumPageStart + SUM_PAGE_SIZE, posts.length);
                sumPager = '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#50575e">' +
                    '<button class="button" onclick="sumState.page--;sumRenderTable()" ' + (sumState.page === 0 ? 'disabled' : '') + '>← Prev</button>' +
                    '<span>Page ' + (sumState.page + 1) + ' of ' + sumTotalPages + ' &nbsp;·&nbsp; ' + from + '–' + to + ' of ' + posts.length + '</span>' +
                    '<button class="button" onclick="sumState.page++;sumRenderTable()" ' + (sumState.page >= sumTotalPages - 1 ? 'disabled' : '') + '>Next →</button>' +
                    '</div>';
            }

            wrap.innerHTML = '<table style="width:100%;border-collapse:collapse;margin-top:4px">' +
                '<thead><tr style="background:#f0f0f0">' +
                '<th style="padding:6px 10px;text-align:left;font-size:12px;color:#50575e;font-weight:600">Post Title</th>' +
                '<th style="padding:6px 10px;text-align:center;font-size:12px;color:#50575e;font-weight:600">Status</th>' +
                '<th style="padding:6px 10px;text-align:right;font-size:12px;color:#50575e;font-weight:600">Action</th>' +
                '</tr></thead>' +
                '<tbody>' + rows + '</tbody>' +
                '</table>' + sumPager;
        }

        function sumTogglePosts(btn) {
            const wrap = document.getElementById('ab-sum-posts-wrap');
            if (!wrap) return;
            const hidden = wrap.style.display === 'none';
            wrap.style.display = hidden ? '' : 'none';
            btn.textContent = hidden ? '↑ Hide Posts' : '↓ Show Posts';
        }

        function sumToggle() {
            const btn      = document.getElementById('ab-sum-load-btn');
            const summary  = document.getElementById('ab-sum-summary');
            const toolbar  = document.getElementById('ab-sum-toolbar');
            const postsWrap = document.getElementById('ab-sum-posts-wrap');
            const isHidden = summary.style.display === 'none';
            summary.style.display   = isHidden ? '' : 'none';
            toolbar.style.display   = isHidden ? '' : 'none';
            if (postsWrap) postsWrap.style.display = isHidden ? '' : 'none';
            btn.textContent = isHidden ? '↑ Hide' : '↺ Refresh';
            btn.onclick = isHidden ? function() { sumToggle(); } : function() { sumLoad(); };
        }

        async function sumGenAll(force) {
            if (sumState.running) return;
            if (force && !confirm('This will overwrite ALL existing AI summaries. Continue?')) return;
            sumState.running = true;
            sumState.stopped = false;
            sumState.done    = 0;
            document.getElementById('ab-sum-gen-all').disabled   = true;
            document.getElementById('ab-sum-force-all').disabled = true;
            document.getElementById('ab-sum-stop').style.display = '';
            sumSetProgress(0);

            try {
                while (!sumState.stopped) {
                    const data = await abPost('cs_seo_summary_generate_all', { force: force ? 1 : 0, done_count: sumState.done });
                    if (!data.success) {
                        sumLog('✗ Error: ' + (data.data || 'Unknown'), 'ab-log-error');
                        break;
                    }
                    const d = data.data;
                    if (d.done) break;
                    sumState.done++;
                    document.getElementById('sum-s-done').textContent = sumState.done;
                    const title = d.title || 'Post #' + d.post_id;
                    sumLog('✓ ' + title, 'ab-log-ok');
                    const total = force ? sumState.total : sumState.missing;
                    const pct   = total > 0 ? Math.round((sumState.done / total) * 100) : 0;
                    sumSetProgress(pct);
                    sumSetStatus(sumState.done + ' generated' + (d.remaining > 0 ? ', ' + d.remaining + ' remaining' : ''));
                }
            } catch (err) {
                console.error('sumGenAll error:', err);
                sumLog('✗ Unexpected error: ' + err.message, 'ab-log-error');
            } finally {
                sumState.running = false;
                document.getElementById('ab-sum-gen-all').disabled   = false;
                document.getElementById('ab-sum-force-all').disabled = false;
                document.getElementById('ab-sum-stop').style.display = 'none';
            }
            if (!sumState.stopped) {
                sumSetProgress(100);
                sumSetStatus('✓ Done — ' + sumState.done + ' summaries generated');
                sumLog('✓ Batch complete: ' + sumState.done + ' generated', 'ab-log-ok');
                document.getElementById('ab-sum-gen-all').disabled = true;
                if (sumState.done > 0) abPost('cs_seo_rebuild_health', {}).catch(() => {});
            } else {
                sumSetStatus('Stopped after ' + sumState.done + ' generated');
            }
        }

        function sumStop() { sumState.stopped = true; sumSetStatus('Stopping...'); }

        async function sumGenOne(postId) {
            const row    = document.getElementById('ab-sum-row-' + postId);
            if (!row) return;
            const btn    = row.querySelector('button');
            const cell   = row.querySelector('.ab-sum-status-cell');
            if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ab-spinner">⟳</span> Generating…'; }
            try {
                const data = await abPost('cs_seo_summary_generate_one', { post_id: postId, force: 1 });
                if (data.success) {
                    if (cell) cell.innerHTML = '<span class="ab-badge ab-badge-ok">✓ Generated</span>';
                    const p = (sumState.posts || []).find(function(x) { return x.id === postId; });
                    if (p) { p.has_sum = true; p._done = true; }
                    const done = parseInt(document.getElementById('sum-s-done').textContent || '0', 10) + 1;
                    document.getElementById('sum-s-done').textContent = done;
                    const sumTitle = row.querySelector('td a') || row.querySelector('td');
                    sumLog('✓ ' + (data.data && data.data.skipped ? 'Already had summary: ' : '') + (sumTitle ? sumTitle.textContent : 'Post #' + postId), 'ab-log-ok');
                } else {
                    if (cell) cell.innerHTML = '<span class="ab-badge ab-badge-none">✗ Error</span>';
                    sumLog('✗ Error generating post #' + postId + ': ' + (data.data || 'Unknown'), 'ab-log-error');
                }
            } catch (e) {
                if (cell) cell.innerHTML = '<span class="ab-badge ab-badge-none">✗ Error</span>';
                sumLog('✗ Network error for post #' + postId + ': ' + e.message, 'ab-log-error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = '✦ Generate'; }
            }
        }

        // ── Category Fixer ───────────────────────────────────────────────────
        const cfNonce = csSeoAdmin.nonce;
        let cfAllPosts   = [];
        let cfFiltered   = [];
        let cfPage       = 1;
        const CF_PER_PAGE = 50;

        function cfPills(names, colour) {
            if (!names || !names.length) return '<span style="color:#aaa;font-size:12px;">None</span>';
            return names.map(n => `<span style="display:inline-block;background:${colour};color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;margin:2px 2px;white-space:nowrap;">${abEsc(String(n))}</span>`).join('');
        }

        function cfConfBadge(score) {
            const bg = score >= 60 ? '#2d6a4f' : score >= 30 ? '#e67e00' : '#c3372b';
            const label = score >= 60 ? 'High' : score >= 30 ? 'Medium' : 'Low';
            return `<span style="background:${bg};color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;white-space:nowrap;">${label} ${score}%</span>`;
        }

        function cfStatusBadge(status) {
            if (status === 'applied')  return '<span style="background:#2d6a4f;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;">Applied</span>';
            if (status === 'skipped')  return '<span style="background:#888;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;">Skipped</span>';
            return '';
        }

        function cfRender() {
            const tbody = document.getElementById('cf-tbody');
            const start = (cfPage - 1) * CF_PER_PAGE;
            const slice = cfFiltered.slice(start, start + CF_PER_PAGE);
            tbody.innerHTML = slice.map(p => {
                const rowStyle = p.status === 'applied' ? 'opacity:.55;' : p.status === 'skipped' ? 'opacity:.4;' : '';
                const changedCols = p.changed
                    ? [
                        p.add_names.map(n => `<span style="display:inline-block;background:#1a7a34;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;margin:2px 2px;white-space:nowrap;">+ ${abEsc(n)}</span>`).join(''),
                        p.remove_names.map(n => `<span style="display:inline-block;background:#d63638;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;margin:2px 2px;white-space:nowrap;">− ${abEsc(n)}</span>`).join(''),
                        p.unchanged_names.map(n => `<span style="display:inline-block;background:#787c82;color:#fff;border-radius:10px;padding:2px 8px;font-size:11px;margin:2px 2px;white-space:nowrap;">${abEsc(n)}</span>`).join(''),
                      ].join('')
                    : cfPills(p.proposed_names, '#2d6a4f');
                const effectivelyMatched = !p.changed || (p.add_names.length === 0 && p.remove_names.length === 0);
                const actions = (p.status === 'applied' || p.status === 'skipped')
                    ? cfStatusBadge(p.status)
                    : effectivelyMatched
                        ? `<span style="display:inline-block;background:#d0f0d0;color:#1a7a34;border:1px solid #a8d5a8;border-radius:10px;padding:2px 10px;font-size:11px;font-weight:600;">✓ Matched</span>
                           <button class="button button-small" onclick="cfSkipOne(${p.post_id})">Skip</button>
                           <button class="button button-small" title="${abEsc(p.reason)}" onclick="cfReanalyse(${p.post_id})">&#8635;</button>`
                        : `<button class="button button-small" style="background:#2d6a4f;color:#fff;border-color:#2d6a4f;" onclick="cfApplyOne(${p.post_id})">Apply</button>
                           <button class="button button-small" onclick="cfSkipOne(${p.post_id})">Skip</button>
                           <button class="button button-small" title="${abEsc(p.reason)}" onclick="cfReanalyse(${p.post_id})">&#8635;</button>`;
                return `<tr data-pid="${p.post_id}" data-changed="${p.changed?1:0}" data-conf="${p.confidence}" data-status="${p.status}" style="border-bottom:1px solid #f0f0f0;${rowStyle}">
                    <td style="padding:8px 10px;"><input type="checkbox" class="cf-chk" data-pid="${p.post_id}"></td>
                    <td style="padding:8px 10px;"><a href="/wp-admin/post.php?post=${p.post_id}&action=edit" target="_blank">${abEsc(p.title)}</a></td>
                    <td style="padding:8px 10px;">${cfPills(p.current_names, '#555')}</td>
                    <td style="padding:8px 10px;">${changedCols}</td>
                    <td style="padding:8px 10px;">${cfConfBadge(p.confidence)}</td>
                    <td style="padding:8px 10px;white-space:nowrap;">${actions}</td>
                </tr>`;
            }).join('');

            // Pager
            const total = cfFiltered.length;
            const pages = Math.ceil(total / CF_PER_PAGE);
            const pager = document.getElementById('cf-pager');
            if (pages > 1) {
                let html = `<span style="color:#555;">Page ${cfPage} of ${pages} &nbsp;</span>`;
                if (cfPage > 1) html += `<button class="button button-small" onclick="cfPage--;cfRender()">&#8592; Prev</button> `;
                if (cfPage < pages) html += `<button class="button button-small" onclick="cfPage++;cfRender()">Next &#8594;</button>`;
                pager.innerHTML = html;
                pager.style.display = 'block';
            } else {
                pager.style.display = 'none';
            }

            // Stats
            const changed   = cfAllPosts.filter(p => p.changed).length;
            const applied   = cfAllPosts.filter(p => p.status === 'applied').length;
            const low       = cfAllPosts.filter(p => p.confidence < 30).length;
            const missing   = cfAllPosts.filter(p => !p.current_ids || p.current_ids.length === 0).length;
            const statsEl   = document.getElementById('cf-stats');
            statsEl.innerHTML = [
                cfStatPill('Total', cfAllPosts.length, '#555'),
                cfStatPill('Changed', changed, '#e67e00'),
                cfStatPill('Applied', applied, '#2d6a4f'),
                cfStatPill('Low Conf', low, '#c3372b'),
                cfStatPill('Missing', missing, '#1a4a7a'),
            ].join('');
            statsEl.style.display = 'flex';

            document.getElementById('cf-status').textContent =
                `${cfFiltered.length} posts shown (${changed} changed, ${applied} applied)`;

            // Re-apply post column visibility if hidden
            if (!cfPostsVisible) {
                document.getElementById('cf-table').querySelectorAll('tr').forEach(row => {
                    if (row.children[1]) row.children[1].style.display = 'none';
                });
            }
        }

        function cfStatPill(label, val, colour) {
            return `<span style="background:${colour};color:#fff;border-radius:6px;padding:4px 12px;font-size:12px;font-weight:600;">${label}: ${val}</span>`;
        }

        function cfFilter(type) {
            cfPage = 1;
            if (type === 'changed')   cfFiltered = cfAllPosts.filter(p => p.changed);
            else if (type === 'unchanged') cfFiltered = cfAllPosts.filter(p => !p.changed);
            else if (type === 'low')  cfFiltered = cfAllPosts.filter(p => p.confidence < 30);
            else if (type === 'missing') cfFiltered = cfAllPosts.filter(p => !p.current_ids || p.current_ids.length === 0);
            else cfFiltered = [...cfAllPosts];
            // Highlight active filter button
            ['all','changed','unchanged','low','missing'].forEach(t => {
                const btn = document.getElementById('cf-f-' + t);
                if (btn) btn.style.background = (t === type) ? '#2d6a4f' : '';
                if (btn) btn.style.color = (t === type) ? '#fff' : '';
                if (btn) btn.style.borderColor = (t === type) ? '#2d6a4f' : '';
            });
            cfRender();
        }

        function cfToggleAll(cb) {
            document.querySelectorAll('.cf-chk').forEach(c => c.checked = cb.checked);
        }

        function cfErr(label, err) {
            console.error('[catfix] ' + label, err);
            const st = document.getElementById('cf-status');
            if (st) st.textContent = 'Error: ' + (err ? err.message : label);
        }

        function cfSetLoadingState(loading) {
            // Only disable action buttons (not filter buttons).
            // Filters must always be clickable so the user can browse partial results
            // while batches are still loading. Disabling them caused "nothing works"
            // if any batch call hung and the finally block was never reached.
            ['cf-ai-btn','cf-bulk-btn','cf-reload-hdr'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.disabled = loading;
            });
        }

        async function cfFetchWithTimeout(url, opts, ms) {
            // Wraps fetch with an AbortController timeout so a hung batch
            // doesn't freeze the entire scan indefinitely.
            const ctrl = new AbortController();
            const tid  = setTimeout(() => ctrl.abort(), ms);
            try {
                return await fetch(url, Object.assign({}, opts, {signal: ctrl.signal}));
            } finally { clearTimeout(tid); }
        }

        async function cfLoad() {
            // Two-phase batched scan: Phase 1 gets all post IDs instantly (~10ms, no analysis).
            // Phase 2 analyses in batches of 25 with a live counter — each batch is fast
            // and the table populates progressively so filters work throughout.
            const CF_BATCH   = 25;
            const CF_TIMEOUT = 30000; // 30 s per batch before skipping
            try {
                document.getElementById('cf-cta').style.display = 'none';
                document.getElementById('cf-toolbar').style.display = 'flex';
                document.getElementById('cf-stats').style.display = 'none';
                document.getElementById('cf-table').style.display = 'none';
                document.getElementById('cf-reload-hdr').style.display = '';
                const cfHideBtn = document.getElementById('cf-hideposts-hdr');
                if (cfHideBtn) cfHideBtn.style.display = '';
                cfSetLoadingState(true);
                cfAllPosts = [];

                // ── Phase 1: get post ID list (fast, no analysis) ───────────────
                document.getElementById('cf-status').textContent = 'Fetching post list…';
                const fd1 = new FormData();
                fd1.append('action', 'cs_seo_catfix_list_ids');
                fd1.append('nonce', cfNonce);
                const r1 = await cfFetchWithTimeout(ajaxurl, {method:'POST', body:fd1}, CF_TIMEOUT);
                const d1 = await r1.json();
                if (!d1.success) { document.getElementById('cf-status').textContent = 'Error fetching post list.'; return; }

                const allIds = d1.ids;
                const total  = allIds.length;
                if (total === 0) {
                    document.getElementById('cf-status').textContent = 'No published posts found.';
                    cfFilter('all');
                    return;
                }

                document.getElementById('cf-table').style.display = 'table';
                document.getElementById('cf-legend').style.display = 'block';

                // ── Phase 2: analyse in batches, live progress ──────────────────
                for (let i = 0; i < total; i += CF_BATCH) {
                    const batchIds = allIds.slice(i, i + CF_BATCH);
                    document.getElementById('cf-status').textContent =
                        `Scanning post ${i + 1} of ${total}`;

                    try {
                        const fd2 = new FormData();
                        fd2.append('action', 'cs_seo_catfix_load');
                        fd2.append('nonce', cfNonce);
                        batchIds.forEach(id => fd2.append('post_ids[]', id));

                        const r2 = await cfFetchWithTimeout(ajaxurl, {method:'POST', body:fd2}, CF_TIMEOUT);
                        const d2 = await r2.json();
                        if (d2.success) {
                            cfAllPosts = cfAllPosts.concat(d2.posts);
                            cfPage = 1;
                            cfFilter('all');
                        } else {
                            console.error('[catfix] batch at offset ' + i + ' returned success:false');
                        }
                    } catch(batchErr) {
                        console.error('[catfix] batch at offset ' + i + ' failed:', batchErr);
                        // Continue with remaining batches — don't abort the whole scan
                    }
                }

                document.getElementById('cf-status').textContent =
                    cfAllPosts.length + ' posts scanned';
                cfFilter('all');
            } catch(err) { cfErr('cfLoad', err); }
            finally { cfSetLoadingState(false); }
        }

        async function cfApplyOne(postId) {
            try {
                const p = cfAllPosts.find(x => x.post_id === postId);
                if (!p) return;
                const fd = new FormData();
                fd.append('action', 'cs_seo_catfix_apply');
                fd.append('nonce', cfNonce);
                fd.append('post_id', postId);
                p.proposed_ids.forEach(id => fd.append('proposed_ids[]', id));
                const r = await fetch(ajaxurl, {method:'POST', body:fd});
                const d = await r.json();
                if (d.success) { p.status = 'applied'; cfRender(); }
                else cfErr('cfApplyOne: server error for post ' + postId, null);
            } catch(err) { cfErr('cfApplyOne', err); }
        }

        async function cfSkipOne(postId) {
            try {
                const p = cfAllPosts.find(x => x.post_id === postId);
                if (!p) return;
                const fd = new FormData();
                fd.append('action', 'cs_seo_catfix_skip');
                fd.append('nonce', cfNonce);
                fd.append('post_id', postId);
                await fetch(ajaxurl, {method:'POST', body:fd});
                p.status = 'skipped';
                cfRender();
            } catch(err) { cfErr('cfSkipOne', err); }
        }

        async function cfAiOne(postId) {
            try {
                const fd = new FormData();
                fd.append('action', 'cs_seo_catfix_ai_one');
                fd.append('nonce', cfNonce);
                fd.append('post_id', postId);
                const r = await fetch(ajaxurl, {method:'POST', body:fd});
                const d = await r.json();
                if (!d.success) return null;
                const idx = cfAllPosts.findIndex(p => p.post_id === postId);
                if (idx !== -1) {
                    cfAllPosts[idx] = Object.assign(cfAllPosts[idx], {
                        proposed_ids:    d.proposed_ids,
                        proposed_names:  d.proposed_names,
                        add_ids:         d.add_ids,
                        add_names:       d.add_names,
                        remove_ids:      d.remove_ids,
                        remove_names:    d.remove_names,
                        unchanged_names: d.unchanged_names,
                        confidence:      d.confidence,
                        changed:         d.changed,
                        source:          'ai',
                        status:          'pending',
                    });
                }
                return d;
            } catch(err) { cfErr('cfAiOne', err); return null; }
        }

        async function cfAiAnalyseAll() {
            const btn = document.getElementById('cf-ai-btn');
            try {
                btn.disabled = true;
                const posts = cfAllPosts.filter(p => p.status !== 'applied' && p.status !== 'skipped');
                const total = posts.length;
                let done = 0;
                for (const p of posts) {
                    document.getElementById('cf-status').textContent = `AI analysing ${++done} / ${total}...`;
                    await cfAiOne(p.post_id);
                    const active = document.querySelector('[id^="cf-f-"][style*="#2d6a4f"]');
                    const type = active ? active.id.replace('cf-f-','') : 'changed';
                    cfFilter(type);
                }
                document.getElementById('cf-status').textContent = `AI analysis complete. ${total} posts analysed.`;
            } catch(err) { cfErr('cfAiAnalyseAll', err); }
            finally { btn.disabled = false; }
        }

        async function cfReanalyse(postId) {
            try {
                document.getElementById('cf-status').textContent = `AI analysing post ${postId}...`;
                const d = await cfAiOne(postId);
                if (d) {
                    const active = document.querySelector('[id^="cf-f-"][style*="#2d6a4f"]');
                    const type = active ? active.id.replace('cf-f-','') : 'changed';
                    cfFilter(type);
                    document.getElementById('cf-status').textContent = `AI re-analysis complete.`;
                }
            } catch(err) { cfErr('cfReanalyse', err); }
        }

        async function cfBulkApply() {
            try {
                const checked = Array.from(document.querySelectorAll('.cf-chk:checked')).map(c => parseInt(c.dataset.pid));
                const targets = checked.length
                    ? cfAllPosts.filter(p => checked.includes(p.post_id) && p.changed && p.status !== 'applied')
                    : cfAllPosts.filter(p => p.changed && p.status !== 'applied');
                if (!targets.length) { alert('No changed posts to apply.'); return; }
                if (!confirm(`Apply category changes to ${targets.length} posts?`)) return;

                const fd = new FormData();
                fd.append('action', 'cs_seo_catfix_bulk_apply');
                fd.append('nonce', cfNonce);
                targets.forEach((p, i) => {
                    fd.append(`items[${i}][post_id]`, p.post_id);
                    p.proposed_ids.forEach(id => fd.append(`items[${i}][proposed_ids][]`, id));
                });
                const r = await fetch(ajaxurl, {method:'POST', body:fd});
                const d = await r.json();
                if (d.success) {
                    targets.forEach(p => p.status = 'applied');
                    document.getElementById('cf-status').textContent = `Applied ${d.applied} posts.`;
                    cfRender();
                } else { cfErr('cfBulkApply: server error', null); }
            } catch(err) { cfErr('cfBulkApply', err); }
        }

        // ── Category Health ───────────────────────────────────────────────────
        const chNonce = csSeoAdmin.nonce;
        let chData          = [];
        let chLoading       = false;
        let chCurrentFilter = 'all';

        async function chLoad() {
            if (chLoading) return;
            chLoading = true;
            const cta     = document.getElementById('ch-cta');
            const wrap    = document.getElementById('ch-wrap');
            const stats   = document.getElementById('ch-stats');
            const reload  = document.getElementById('ch-reload-hdr');
            const hideBtn = document.getElementById('ch-hideposts-hdr');
            cta.style.display    = 'none';
            stats.style.display  = 'none';
            chData          = [];
            chCurrentFilter = 'all';

            function chProgress(msg) {
                wrap.innerHTML = '<p style="color:#555;font-size:13px;padding:12px 0;">&#9203; ' + msg + '</p>';
            }

            try {
                // Phase 1: lightweight category list (no post queries)
                chProgress('Fetching category list\u2026');
                const fd1 = new FormData();
                fd1.append('action', 'cs_seo_catfix_health_list');
                fd1.append('nonce', chNonce);
                const r1 = await fetch(ajaxurl, {method:'POST', body:fd1});
                const d1 = await r1.json();
                if (!d1.success) { wrap.innerHTML = '<p style="color:#c3372b;">Error loading category list.</p>'; return; }

                const cats  = d1.categories;
                const total = cats.length;
                if (!total) { wrap.innerHTML = '<p style="color:#555;">No categories found.</p>'; return; }

                // Phase 2: process each category individually so a slow query is visible
                for (let i = 0; i < total; i++) {
                    const c = cats[i];
                    chProgress('Processing category ' + (i + 1) + ' of ' + total + ': <strong>' + abEsc(c.name) + '</strong>');
                    try {
                        const fd2 = new FormData();
                        fd2.append('action', 'cs_seo_catfix_health_cat');
                        fd2.append('nonce', chNonce);
                        fd2.append('cat_id', c.id);
                        const r2 = await fetch(ajaxurl, {method:'POST', body:fd2});
                        const d2 = await r2.json();
                        if (d2.success) chData.push(d2.cat);
                        else console.error('[cathealth] category ' + c.id + ' (' + c.name + ') returned success:false');
                    } catch (catErr) {
                        console.error('[cathealth] category ' + c.id + ' (' + c.name + ') failed:', catErr);
                    }
                }

                // Sort: grade order then count desc (mirrors original server-side sort)
                const chGradeOrder = {strong:0, moderate:1, new:2, weak:3, empty:4, uncategorized:5};
                chData.sort((a, b) => {
                    const ga = chGradeOrder[a.grade] ?? 5, gb = chGradeOrder[b.grade] ?? 5;
                    return ga !== gb ? ga - gb : b.count - a.count;
                });

                reload.style.display = '';
                if (hideBtn) hideBtn.style.display = '';
                stats.style.display  = 'flex';
                chRenderStats();
                chRenderTable();
            } catch (err) {
                wrap.innerHTML = '<p style="color:#c3372b;">Error: ' + abEsc(String(err.message || err)) + '</p>';
            } finally {
                chLoading = false;
            }
        }

        function chGradeBadge(grade) {
            const map = {
                strong:        {bg:'#1a7a34', label:'Strong'},
                moderate:      {bg:'#e67e00', label:'Moderate'},
                new:           {bg:'#2271b1', label:'New'},
                weak:          {bg:'#b8a200', label:'Weak'},
                empty:         {bg:'#d63638', label:'Empty'},
                uncategorized: {bg:'#787c82', label:'Uncategorized'},
            };
            const g = map[grade] || {bg:'#555', label:grade};
            return `<span style="display:inline-block;background:${g.bg};color:#fff;border-radius:10px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap;">${g.label}</span>`;
        }

        function chFilter(grade) {
            chCurrentFilter = grade;
            chRenderStats();
            chRenderTable();
        }

        function chRenderStats() {
            const counts = {strong:0, moderate:0, new:0, weak:0, empty:0, uncategorized:0};
            chData.forEach(c => { if (counts[c.grade] !== undefined) counts[c.grade]++; });
            const colors = {strong:'#1a7a34', moderate:'#e67e00', new:'#2271b1', weak:'#b8a200', empty:'#d63638', uncategorized:'#787c82'};
            const labels = {strong:'Strong', moderate:'Moderate', new:'New', weak:'Weak', empty:'Empty', uncategorized:'Uncategorized'};
            const total  = chData.length;
            const allActive = chCurrentFilter === 'all';
            const allBtn = `<button onclick="chFilter('all')" style="display:inline-flex;align-items:center;gap:5px;background:${allActive ? '#1d2327' : '#f6f7f7'};color:${allActive ? '#fff' : '#1d2327'};border:1px solid ${allActive ? '#1d2327' : '#ddd'};border-radius:8px;padding:4px 12px;font-size:12px;cursor:pointer;font-weight:${allActive ? '600' : '400'};">All&nbsp;<strong>${total}</strong></button>`;
            const pills = Object.entries(counts).map(([g, n]) => {
                const active = chCurrentFilter === g;
                const dot = `<span style="width:9px;height:9px;border-radius:50%;background:${active ? '#fff' : colors[g]};display:inline-block;flex-shrink:0;margin-right:5px;"></span>`;
                return `<button onclick="chFilter('${g}')" style="display:inline-flex;align-items:center;background:${active ? colors[g] : '#f6f7f7'};color:${active ? '#fff' : '#1d2327'};border:1px solid ${active ? colors[g] : '#ddd'};border-radius:8px;padding:4px 12px;font-size:12px;cursor:pointer;font-weight:${active ? '600' : '400'};white-space:nowrap;">${dot}${labels[g]}&nbsp;<strong>${n}</strong></button>`;
            }).join('');
            document.getElementById('ch-stats').innerHTML = allBtn + pills;
        }

        function chRenderTable() {
            const wrap = document.getElementById('ch-wrap');
            if (!chData.length) { wrap.innerHTML = '<p style="color:#555;">No categories found.</p>'; return; }
            const visible = chCurrentFilter === 'all' ? chData : chData.filter(c => c.grade === chCurrentFilter);
            if (!visible.length) { wrap.innerHTML = '<p style="color:#555;padding:12px 0;">No categories match this filter.</p>'; return; }
            const rows = visible.map(c => {
                const postRows = c.posts.length
                    ? c.posts.map(p =>
                        `<li style="margin:2px 0;"><a href="/wp-admin/post.php?post=${p.id}&action=edit" target="_blank" style="color:#2271b1;font-size:12px;">${abEsc(p.title)}</a></li>`
                      ).join('')
                    : '<li style="color:#888;font-size:12px;">No published posts</li>';
                const expandId = `ch-posts-${c.id}`;
                const postToggle = `<button class="button button-small" onclick="chToggle(${c.id})" id="ch-btn-${c.id}" style="font-size:11px;">&#9660; Show posts</button>`;
                const editLink   = `<a href="${safeHref(c.edit_url)}" target="_blank" class="button button-small" style="font-size:11px;">Edit</a>`;
                const rowBg = c.grade === 'uncategorized' ? '#fff8e1' : (c.grade === 'empty' ? '#fff5f5' : '#fff');
                return `<tr style="border-bottom:1px solid #f0f0f0;background:${rowBg};">
                    <td style="padding:10px 12px;font-weight:600;font-size:13px;">${abEsc(c.name)}</td>
                    <td style="padding:10px 12px;text-align:center;font-size:13px;">${c.count}</td>
                    <td style="padding:10px 12px;">${chGradeBadge(c.grade)}</td>
                    <td style="padding:10px 12px;white-space:nowrap;">${postToggle} ${editLink}</td>
                </tr>
                <tr id="${expandId}" style="display:none;background:#fafafa;">
                    <td colspan="4" style="padding:8px 24px 12px;">
                        <ul style="margin:0;padding:0;list-style:none;columns:2;gap:16px;">${postRows}</ul>
                    </td>
                </tr>`;
            }).join('');

            wrap.innerHTML = `<table style="width:100%;min-width:500px;border-collapse:collapse;font-size:13px;">
                <thead><tr style="background:#f0f0f0;">
                    <th style="padding:8px 12px;text-align:left;">Category</th>
                    <th style="padding:8px 12px;text-align:center;width:80px;">Posts</th>
                    <th style="padding:8px 12px;text-align:left;width:130px;">Health</th>
                    <th style="padding:8px 12px;text-align:left;width:160px;">Actions</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
        }

        function chToggle(catId) {
            const row = document.getElementById('ch-posts-' + catId);
            const btn = document.getElementById('ch-btn-' + catId);
            if (!row) return;
            const visible = row.style.display !== 'none';
            row.style.display = visible ? 'none' : 'table-row';
            btn.innerHTML = visible ? '&#9660; Show posts' : '&#9650; Hide posts';
        }

        let chPostsVisible = true;
        function chToggleAllPosts() {
            chPostsVisible = !chPostsVisible;
            document.querySelectorAll('[id^="ch-posts-"]').forEach(row => {
                row.style.display = chPostsVisible ? 'table-row' : 'none';
            });
            document.querySelectorAll('[id^="ch-btn-"]').forEach(btn => {
                btn.innerHTML = chPostsVisible ? '&#9650; Hide posts' : '&#9660; Show posts';
            });
            const hdr = document.getElementById('ch-hideposts-hdr');
            if (hdr) hdr.innerHTML = chPostsVisible ? '&#128065; Hide Posts' : '&#128065; Show Posts';
        }

        let cfPostsVisible = true;
        function cfTogglePosts() {
            cfPostsVisible = !cfPostsVisible;
            // Toggle the Post title column (col index 1 in cf-table)
            const table = document.getElementById('cf-table');
            if (!table) return;
            table.querySelectorAll('tr').forEach(row => {
                const cells = row.children;
                if (cells[1]) cells[1].style.display = cfPostsVisible ? '' : 'none';
            });
            const hdr = document.getElementById('cf-hideposts-hdr');
            if (hdr) hdr.innerHTML = cfPostsVisible ? '&#128065; Hide Posts' : '&#128065; Show Posts';
        }

        // ── Category Drift ───────────────────────────────────────────────
        const cdNonce = csSeoAdmin.nonce;
        let cdDrift        = [];
        let cdTotalPosts   = 0;
        let cdMovedPostIds = new Set(); // tracks posts moved in any bucket this session

        // cdRender: display already-fetched drift data without any API call
        function cdRender(totalPosts, cachedAt) {
            const cta     = document.getElementById('cd-cta');
            const wrap    = document.getElementById('cd-wrap');
            const summary = document.getElementById('cd-summary');
            const reload  = document.getElementById('cd-reload-hdr');
            cta.style.display     = 'none';
            reload.style.display  = '';
            summary.style.display = 'block';
            if (!cdDrift.length) {
                summary.innerHTML = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;color:#1a7a34;font-size:13px;">&#10003; AI found no category drift across ' + totalPosts + ' posts. Your taxonomy looks semantically consistent.</div>';
                wrap.innerHTML = '';
                return;
            }
            const catchAll = cdDrift.filter(c => c.verdict === 'catch-all').length;
            const drifting = cdDrift.filter(c => c.verdict === 'drifting').length;
            let parts = [];
            if (catchAll) parts.push(`<strong>${catchAll}</strong> catch-all ${catchAll === 1 ? 'category' : 'categories'}`);
            if (drifting) parts.push(`<strong>${drifting}</strong> drifting ${drifting === 1 ? 'category' : 'categories'}`);
            const cacheNote = cachedAt ? ` <span style="font-size:11px;opacity:0.75;">(cached &mdash; ${abEsc(cachedAt)})</span>` : '';
            summary.innerHTML = '<div style="background:#fef9ec;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;color:#92400e;font-size:13px;">'
                + '&#129302; AI identified ' + parts.join(' and ') + ' across ' + totalPosts + ' posts.' + cacheNote + '</div>';
            cdRenderDrift();
        }

        // cdLoadFromCache: called by the Load Cached Results button
        async function cdLoadFromCache() {
            const cta     = document.getElementById('cd-cta');
            const wrap    = document.getElementById('cd-wrap');
            const reload  = document.getElementById('cd-reload-hdr');
            const cacheBtn = document.getElementById('cd-btn-cache');
            const ctaMsg  = document.getElementById('cd-cta-msg');

            // Already have data in memory from this session — just re-render, no AJAX needed
            if (cdDrift && cdDrift.length > 0) {
                cta.style.display = 'none';
                if (reload) reload.style.display = 'inline-block';
                cdRender(cdTotalPosts);
                return;
            }

            // Show loading state on the button only
            if (cacheBtn) { cacheBtn.disabled = true; cacheBtn.innerHTML = '&#128336; Checking cache…'; }

            const fd = new FormData();
            fd.append('action', 'cs_seo_catfix_drift_cache_get');
            fd.append('nonce', cdNonce);
            const r = await fetch(ajaxurl, {method:'POST', body:fd});
            const d = await r.json();

            if (d.success) {
                cta.style.display = 'none';
                cdDrift      = d.drift;
                cdTotalPosts = d.total_posts;
                if (reload) reload.style.display = 'inline-block';
                cdRender(cdTotalPosts, d.cached_at);
            } else if (d.stale) {
                // Taxonomy has changed but data is still useful — just load it silently
                cta.style.display = 'none';
                cdDrift      = d.drift;
                cdTotalPosts = d.total_posts;
                if (reload) reload.style.display = 'inline-block';
                cdRender(cdTotalPosts, d.cached_at);
            } else {
                // No cache at all
                if (cacheBtn) { cacheBtn.disabled = false; cacheBtn.innerHTML = '&#128336; Load Cached Results'; }
                if (ctaMsg) { ctaMsg.innerHTML = '&#9888; No cached results found. Run a fresh AI analysis below.'; ctaMsg.style.color = '#b8860b'; }
            }
        }

        // cdLoad: always makes a fresh API call — called by Re-run Analysis button
        let _cdLoadAbort = null;
        async function cdLoad() {
            const cta     = document.getElementById('cd-cta');
            const wrap    = document.getElementById('cd-wrap');
            const summary = document.getElementById('cd-summary');
            const reload  = document.getElementById('cd-reload-hdr');
            cta.style.display     = 'none';
            summary.style.display = 'none';

            // Live elapsed-time counter and Stop button
            let elapsed = 0;
            const timer = setInterval(() => {
                elapsed++;
                const el = document.getElementById('cd-load-elapsed');
                if (el) el.textContent = elapsed + 's';
            }, 1000);

            wrap.innerHTML =
                '<div style="display:flex;align-items:center;gap:12px;padding:14px 0;flex-wrap:wrap">' +
                '<span style="font-size:16px;animation:ab-spin 1s linear infinite;display:inline-block">&#9696;</span>' +
                '<span style="font-size:13px;color:#555;">Asking AI to analyse your taxonomy&hellip; ' +
                '(<span id="cd-load-elapsed">0s</span>)</span>' +
                '<button type="button" id="cd-stop-load" class="button button-small" ' +
                'style="background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px;">&#9632; Stop</button>' +
                '</div>';

            document.getElementById('cd-stop-load').addEventListener('click', function() {
                if (_cdLoadAbort) _cdLoadAbort.abort();
            });

            _cdLoadAbort = new AbortController();
            const fd = new FormData();
            fd.append('action', 'cs_seo_catfix_drift');
            fd.append('nonce', cdNonce);
            try {
                const r = await fetch(ajaxurl, {method:'POST', body:fd, signal: _cdLoadAbort.signal});
                clearInterval(timer);
                const d = await r.json();
                if (!d.success) {
                    wrap.innerHTML = `<p style="color:#c3372b;font-size:13px;">&#9888; ${abEsc(d.error || 'Error running drift analysis.')}</p>`;
                    cta.style.display = 'block';
                    return;
                }
                cdDrift      = d.drift;
                cdTotalPosts = d.total_posts;
                if (reload) reload.style.display = 'inline-block';
                cdRender(cdTotalPosts);
            } catch(e) {
                clearInterval(timer);
                if (e.name === 'AbortError') {
                    wrap.innerHTML = '<p style="color:#888;font-size:13px;">&#9632; Analysis stopped. ' +
                        '<button type="button" class="button button-small">Try again</button></p>';
                    cta.style.display = 'block';
                } else {
                    wrap.innerHTML = `<p style="color:#c3372b;font-size:13px;">&#9888; ${abEsc(e.message)}</p>`;
                    cta.style.display = 'block';
                }
            } finally {
                _cdLoadAbort = null;
            }
        }

        // cdAnalyseRemaining: analyses unassigned posts for one category and merges moves
        async function cdAnalyseRemaining(btn, catIdx) {
            const c = cdDrift[catIdx];
            if (!c) return;
            const assignedTitles = (c.moves || []).flatMap(m => m.titles || []);
            const allPosts = c.posts || [];

            // Count unanalysed posts so we can show it in the loading label
            const unanalysed = allPosts.filter(p => !assignedTitles.some(t => {
                const n = t.toLowerCase().trim(), h = p.title.toLowerCase().trim();
                return h.includes(n) || n.includes(h);
            }));
            const unCount = unanalysed.length;

            // Show loading state — elapsed counter lives inside the button text so it's visible
            btn.disabled = true;
            let elapsed = 0;
            const updateBtnText = () => {
                btn.innerHTML = `&#129302; Analysing ${unCount} post${unCount !== 1 ? 's' : ''}&hellip; (${elapsed}s)`;
            };
            updateBtnText();
            const timer = setInterval(() => { elapsed++; updateBtnText(); }, 1000);

            const stopBtn = document.createElement('button');
            stopBtn.type = 'button';
            stopBtn.className = 'button button-small';
            stopBtn.style.cssText = 'margin-left:6px;background:#c3372b;color:#fff;border-color:#c3372b;font-size:11px;';
            stopBtn.innerHTML = '&#9632; Stop';
            btn.parentNode.insertBefore(stopBtn, btn.nextSibling);

            const controller = new AbortController();
            stopBtn.addEventListener('click', () => controller.abort());

            const fd = new FormData();
            fd.append('action',          'cs_seo_catfix_drift_analyse_remaining');
            fd.append('nonce',           cdNonce);
            fd.append('cat_id',          c.cat_id);
            fd.append('cat_name',        c.cat_name);
            fd.append('assigned_titles', JSON.stringify(assignedTitles));

            let d;
            try {
                const r = await fetch(ajaxurl, {method:'POST', body:fd, signal: controller.signal});
                clearInterval(timer);
                stopBtn.remove();
                d = await r.json();
            } catch(e) {
                clearInterval(timer);
                stopBtn.remove();
                btn.disabled = false;
                if (e.name === 'AbortError') {
                    btn.innerHTML = `&#129302; Analyse ${unCount} remaining`;
                } else {
                    btn.innerHTML = '&#9888; ' + e.message;
                }
                return;
            }

            if (!d.success) {
                btn.disabled = false;
                btn.innerHTML = '&#9888; ' + (d.error || 'Error');
                return;
            }

            const newMoves = d.moves || [];

            if (!newMoves.length) {
                btn.innerHTML = '&#10003; All posts already in correct categories';
                btn.style.background = '#1a7a34';
                return;
            }

            // Merge into cdDrift state — moves and the flat list of analysed post IDs
            cdDrift[catIdx].moves = [...(cdDrift[catIdx].moves || []), ...newMoves];
            const returnedAnalysedIds = (d.analysed_post_ids || []).map(Number);
            const existingAnalysed    = (cdDrift[catIdx].analysed_post_ids || []).map(Number);
            cdDrift[catIdx].analysed_post_ids = [...new Set([...existingAnalysed, ...returnedAnalysedIds])];

            // Find the moves column for this row and inject new groups without re-rendering
            const movesCell = document.querySelector(`#cd-move-cell-${catIdx}`);
            if (movesCell) {
                const newAssignedIds = new Set();
                newMoves.forEach((m, midx) => {
                    const globalMidx = (c.moves.length - newMoves.length) + midx;
                    const groupId = `cd-move-${catIdx}-${globalMidx}`;
                    // Prefer server-resolved post_ids; fall back to fuzzy title match.
                    const matchedPosts = (m.post_ids && m.post_ids.length)
                        ? m.post_ids.map(id => allPosts.find(p => p.id === id)).filter(Boolean)
                        : (m.titles || []).map(t => cdMatchPost(t, allPosts)).filter(Boolean);
                    matchedPosts.forEach(p => newAssignedIds.add(p.id));
                    const postItems = matchedPosts.map(p =>
                        `<li style="padding:4px 0;border-bottom:1px solid #f0eaff;"><a href="/wp-admin/post.php?post=${p.id}&action=edit" target="_blank" style="color:#2271b1;font-size:12px;">${abEsc(p.title)}</a></li>`
                    ).join('');
                    const postCount = matchedPosts.length;
                    const toggleBtn = postCount > 0
                        ? `<button class="button button-small" onclick="cdTogglePosts('${groupId}', this)" style="font-size:11px;margin:4px 0 0;">&#9660; ${postCount} post${postCount !== 1 ? 's' : ''}</button>`
                        : `<span style="font-size:11px;color:#aaa;">No matched posts</span>`;
                    const postList = postCount > 0
                        ? `<div id="${groupId}" style="display:none;margin-top:6px;"><ul style="margin:0;padding:0;list-style:none;">${postItems}</ul></div>`
                        : '';
                    const div = document.createElement('div');
                    div.style.cssText = 'margin-bottom:12px;padding-bottom:10px;border-bottom:1px dashed #d8c8f0;';
                    div.innerHTML = `<div style="font-weight:600;font-size:12px;color:#fff;background:#6b3fa0;border-radius:4px;padding:3px 8px;display:inline-block;margin-bottom:3px;">&#8594; ${m.to}</div>
                        <div style="font-size:11px;color:#666;font-style:italic;margin-bottom:4px;">${m.because || ''}</div>
                        ${toggleBtn}${postList}`;
                    movesCell.appendChild(div);
                });
            }

            // Update the analyse button and unanalysed toggle to reflect remaining count.
            // Check both ID (exact, for new moves) AND title (fuzzy, for older moves without post_ids).
            const allAssignedIds = new Set(cdDrift[catIdx].moves.flatMap(m => m.post_ids || []));
            const allAssignedTitles = cdDrift[catIdx].moves.flatMap(m => m.titles || []);
            const stillUnassigned = allPosts.filter(p => {
                if (allAssignedIds.has(p.id)) return false;
                return !allAssignedTitles.some(t => {
                    const n = t.toLowerCase().trim(), h = p.title.toLowerCase().trim();
                    return h.includes(n) || n.includes(h);
                });
            });

            // Update the ▼ N unanalysed posts toggle button above the post list
            const postsDiv = document.getElementById(`cd-posts-${catIdx}`);
            if (postsDiv && postsDiv.previousElementSibling) {
                const toggleBtn = postsDiv.previousElementSibling;
                if (stillUnassigned.length > 0) {
                    toggleBtn.innerHTML = `&#9660; ${stillUnassigned.length} unanalysed post${stillUnassigned.length !== 1 ? 's' : ''}`;
                } else {
                    toggleBtn.innerHTML = `&#9660; All ${allPosts.length} posts analysed`;
                }
            }

            // Show a brief result line so the user can see what happened
            const totalMatched = allPosts.length - stillUnassigned.length;
            let resultEl = btn.parentNode.querySelector('.cd-analyse-result');
            if (!resultEl) {
                resultEl = document.createElement('span');
                resultEl.className = 'cd-analyse-result';
                resultEl.style.cssText = 'margin-left:8px;font-size:11px;color:#555;';
                btn.parentNode.appendChild(resultEl);
            }
            if (newMoves.length === 0) {
                resultEl.textContent = '(AI returned no moves)';
                resultEl.style.color = '#888';
            } else {
                const totalIds = newMoves.reduce((n, m) => n + (m.post_ids || []).length, 0);
                const msg = `${newMoves.length} move group${newMoves.length !== 1 ? 's' : ''}, ${totalIds} post${totalIds !== 1 ? 's' : ''} matched`;
                resultEl.textContent = `(${msg})`;
                resultEl.style.color = totalIds > 0 ? '#1a7a34' : '#c3372b';
            }

            if (stillUnassigned.length > 0) {
                btn.disabled = false;
                btn.innerHTML = `&#129302; Analyse ${stillUnassigned.length} remaining`;
            } else {
                btn.innerHTML = '&#10003; All posts classified';
                btn.style.background = '#1a7a34';
                btn.style.borderColor = '#1a7a34';
                btn.disabled = true;
            }
        }

        function cdVerdictBadge(verdict, confidence) {
            const vMap = {
                'catch-all': {bg:'#d63638', label:'Catch-all'},
                'drifting':  {bg:'#e67e00', label:'Drifting'},
            };
            const cMap = {high:'High', medium:'Medium', low:'Low'};
            const v = vMap[verdict] || {bg:'#787c82', label: verdict};
            const cLabel = cMap[confidence] || confidence;
            return `<span style="display:inline-block;background:${v.bg};color:#fff;border-radius:10px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap;margin-bottom:4px;">${v.label}</span>`
                 + `<br><span style="font-size:11px;color:#888;">${cLabel} confidence</span>`;
        }

        // Match an AI title string to a real post object (fuzzy: substring both ways).
        // Defined at outer scope so cdAnalyseRemaining can call it too.
        function cdMatchPost(titleStr, posts) {
            const needle = titleStr.toLowerCase().trim();
            return posts.find(p => {
                const hay = p.title.toLowerCase().trim();
                return hay.includes(needle) || needle.includes(hay);
            }) || null;
        }

        function cdRenderDrift() {
            const wrap = document.getElementById('cd-wrap');

            const rows = cdDrift.map((c, idx) => {
                const allPosts = c.posts || [];
                const totalCount = c.post_count || 0;
                const moves = c.moves || [];

                // Track which post IDs have been assigned to a move group
                const assignedIds = new Set();

                // Merge move groups that share the same destination (AI sometimes splits them)
                // Also strip any suggestion to move posts into "Uncategorized".
                const fromCatId = c.cat_id || 0;
                const mergedMoves = [];
                for (const m of moves) {
                    const dest = (m.to || '').toLowerCase().trim();
                    if (dest === 'uncategorized') continue;
                    const existing = mergedMoves.find(x => (x.to || '').toLowerCase().trim() === dest);
                    if (existing) {
                        existing.post_ids = [...new Set([...(existing.post_ids || []), ...(m.post_ids || [])])];
                        existing.titles   = [...(existing.titles || []), ...(m.titles || [])];
                    } else {
                        mergedMoves.push(Object.assign({}, m, {post_ids: [...(m.post_ids || [])], titles: [...(m.titles || [])]}));
                    }
                }

                // Render each move group with its own collapsible post list + Move buttons
                const movesHtml = mergedMoves.length
                    ? mergedMoves.map((m, midx) => {
                        const groupId = `cd-move-${idx}-${midx}`;
                        // Prefer server-resolved post_ids (exact); fall back to fuzzy title match.
                        const matchedPosts = (m.post_ids && m.post_ids.length)
                            ? m.post_ids.map(id => allPosts.find(p => p.id === id)).filter(Boolean)
                            : (m.titles || []).map(t => cdMatchPost(t, allPosts)).filter(Boolean);
                        matchedPosts.forEach(p => assignedIds.add(p.id));

                        const toAttr = abEsc(m.to);
                        const postItems = matchedPosts.map(p => {
                            const alreadyMoved = cdMovedPostIds.has(p.id);
                            const btnHtml = alreadyMoved
                                ? `<span style="color:#1a7a34;font-size:11px;font-weight:600;flex-shrink:0;">&#10003; Moved</span>`
                                : `<button class="button button-small cd-move-btn" onclick="cdMoveOne(this)" data-post-id="${p.id}" data-from="${fromCatId}" data-to="${toAttr}" data-idx="${idx}" data-midx="${midx}" style="font-size:11px;flex-shrink:0;white-space:nowrap;">&#8594; Move</button>`;
                            return `<li id="cd-post-${p.id}-${idx}-${midx}" style="padding:4px 0;border-bottom:1px solid #f0eaff;display:flex;align-items:center;gap:6px;${alreadyMoved ? 'opacity:0.5;' : ''}"><a href="/wp-admin/post.php?post=${p.id}&action=edit" target="_blank" style="color:#2271b1;font-size:12px;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${abEsc(p.title)}</a>${btnHtml}</li>`;
                        }).join('');
                        const postCount = matchedPosts.length;
                        const toggleBtn = postCount > 0
                            ? `<button class="button button-small" onclick="cdTogglePosts('${groupId}', this)" style="font-size:11px;">&#9660; ${postCount} post${postCount !== 1 ? 's' : ''}</button>`
                            : `<span style="font-size:11px;color:#aaa;">No matched posts</span>`;
                        const moveAllBtn = postCount > 0
                            ? `<button class="button button-small" id="cd-moveall-${idx}-${midx}" onclick="cdMoveAll(this)" data-from="${fromCatId}" data-to="${toAttr}" data-idx="${idx}" data-midx="${midx}" style="font-size:11px;background:#6b3fa0;border-color:#6b3fa0;color:#fff;white-space:nowrap;">&#8594; Move all ${postCount}</button>`
                            : '';
                        const postList = postCount > 0
                            ? `<div id="${groupId}" style="display:none;margin-top:6px;"><ul style="margin:0;padding:0;list-style:none;">${postItems}</ul></div>`
                            : '';

                        return `<div style="margin-bottom:12px;padding-bottom:10px;border-bottom:1px dashed #d8c8f0;"><div style="font-weight:600;font-size:12px;color:#fff;background:#6b3fa0;border-radius:4px;padding:3px 8px;display:inline-block;margin-bottom:3px;">&#8594; ${abEsc(m.to)}</div><div style="font-size:11px;color:#666;font-style:italic;margin-bottom:4px;">${m.because || ''}</div><div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">${toggleBtn}${moveAllBtn}</div>${postList}</div>`;
                    }).join('')
                    : `<span style="color:#888;font-size:11px;">No structured moves returned</span>`;

                // Unanalysed posts: exclude move-group matches AND any post explicitly
                // tracked via analysed_post_ids (set by analyse_remaining on the server
                // regardless of whether title→ID resolution succeeded).
                const analysedIds = new Set((c.analysed_post_ids || []).map(Number));
                const unassigned = allPosts.filter(p => !assignedIds.has(p.id) && !analysedIds.has(p.id));
                const unassignedId = `cd-posts-${idx}`;
                const unassignedHtml = (() => {
                    if (!allPosts.length) return '';
                    const listSrc = unassigned.length ? unassigned : allPosts;
                    const uCount  = listSrc.length;
                    const label   = unassigned.length
                        ? `&#9660; ${uCount} unanalysed post${uCount !== 1 ? 's' : ''}`
                        : `&#9660; All ${uCount} posts analysed`;
                    const items = listSrc.map(p =>
                        `<li style="padding:5px 0;border-bottom:1px solid #ede8f5;"><a href="/wp-admin/post.php?post=${p.id}&action=edit" target="_blank" style="color:#2271b1;font-size:12px;">${abEsc(p.title)}</a></li>`
                    ).join('');
                    const analyseBtn = unassigned.length
                        ? `<div style="margin-top:8px;"><button class="button button-small" onclick="cdAnalyseRemaining(this, ${idx})" style="font-size:11px;background:#6b3fa0;border-color:#6b3fa0;color:#fff;">&#129302; Analyse ${uCount} remaining</button></div>`
                        : '';
                    return `<button class="button button-small" onclick="cdTogglePosts('${unassignedId}', this)" style="font-size:11px;">${label}</button>
                        <div id="${unassignedId}" style="display:none;margin-top:6px;"><ul style="margin:0;padding:0;list-style:none;border-top:1px solid #ede8f5;">${items}</ul></div>
                        ${analyseBtn}`;
                })();

                // Action badge
                const actionStr = (c.action || '').toLowerCase();
                const actionColor = actionStr.startsWith('delete') ? '#d63638' : actionStr === 'rename' ? '#e67e00' : '#1a7a34';
                const actionHtml = c.action
                    ? `<div style="margin-top:8px;"><span style="font-size:11px;font-weight:600;background:${actionColor};color:#fff;border-radius:4px;padding:2px 8px;">${abEsc(c.action)}</span></div>`
                    : '';

                const rowBg = c.verdict === 'catch-all' ? '#fff5f5' : '#fffbf0';
                return `<tr style="border-bottom:2px solid #e0d0f0;vertical-align:top;background:${rowBg};">
                    <td style="padding:12px;font-weight:600;font-size:13px;min-width:130px;">
                        ${abEsc(c.cat_name)}
                        <div style="font-size:11px;font-weight:400;color:#888;margin-top:2px;">${totalCount} posts total</div>
                        ${actionHtml}
                    </td>
                    <td style="padding:12px;min-width:110px;">${cdVerdictBadge(c.verdict, c.confidence)}</td>
                    <td style="padding:12px;min-width:200px;font-size:12px;color:#3c434a;line-height:1.5;">${c.reason || ''}</td>
                    <td id="cd-move-cell-${idx}" style="padding:12px;min-width:260px;">${movesHtml}</td>
                    <td style="padding:12px;min-width:160px;">${unassignedHtml}</td>
                </tr>`;
            }).join('');

            wrap.innerHTML = `<table style="width:100%;min-width:800px;border-collapse:collapse;font-size:13px;">
                <thead><tr style="background:#f0f0f0;">
                    <th style="padding:8px 12px;text-align:left;">Category</th>
                    <th style="padding:8px 12px;text-align:left;width:110px;">Verdict</th>
                    <th style="padding:8px 12px;text-align:left;width:200px;">AI Reasoning</th>
                    <th style="padding:8px 12px;text-align:left;">Where to move posts</th>
                    <th style="padding:8px 12px;text-align:left;width:160px;">Remaining posts</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
        }

        function cdTogglePosts(id, btn) {
            const el = document.getElementById(id);
            if (!el) return;
            const open = el.style.display !== 'none';
            el.style.display = open ? 'none' : 'block';
            btn.innerHTML = open ? btn.innerHTML.replace('&#9650;', '&#9660;').replace('Hide', 'Show') : btn.innerHTML.replace('&#9660;', '&#9650;').replace('Show', 'Hide');
        }

        async function cdMoveOne(btn) {
            const postId    = parseInt(btn.dataset.postId, 10);
            const fromCatId = parseInt(btn.dataset.from, 10);
            const toName    = btn.dataset.to;
            btn.disabled    = true;
            btn.textContent = '\u2026';
            const fd = new FormData();
            fd.append('action',      'cs_seo_catfix_drift_move');
            fd.append('nonce',       cdNonce);
            fd.append('post_id',     postId);
            fd.append('from_cat_id', fromCatId);
            fd.append('to_cat_name', toName);
            try {
                const r = await fetch(ajaxurl, {method:'POST', body:fd});
                const d = await r.json();
                if (d.success) {
                    cdMovedPostIds.add(postId);
                    // Dim every Move button for this post across ALL buckets in the table
                    document.querySelectorAll('.cd-move-btn[data-post-id="' + postId + '"]').forEach(b => {
                        b.style.display = 'none';
                        const parentLi = b.closest('li');
                        if (parentLi) {
                            parentLi.style.opacity = '0.5';
                            if (!parentLi.querySelector('.cd-moved-lbl')) parentLi.insertAdjacentHTML('beforeend', '<span class="cd-moved-lbl" style="color:#1a7a34;font-size:11px;font-weight:600;flex-shrink:0;">&#10003; Moved</span>');
                        }
                    });
                    // Check each group that contained this post — mark Move All done if none left
                    document.querySelectorAll('[id^="cd-move-"]').forEach(groupEl => {
                        const remaining = groupEl.querySelectorAll('.cd-move-btn:not([style*="display: none"])');
                        if (!remaining.length) {
                            const parts   = groupEl.id.replace('cd-move-', '').split('-');
                            const allBtn  = document.getElementById('cd-moveall-' + parts[0] + '-' + parts[1]);
                            if (allBtn && !allBtn.disabled) { allBtn.disabled = true; allBtn.textContent = '\u2713 All moved'; allBtn.style.background = '#1a7a34'; allBtn.style.borderColor = '#1a7a34'; }
                        }
                    });
                } else {
                    btn.disabled = false;
                    btn.textContent = '\u2192 Move';
                    alert('Move failed: ' + (d.error || 'Unknown error'));
                }
            } catch (e) {
                btn.disabled = false;
                btn.textContent = '\u2192 Move';
                console.error('[drift] move failed:', e);
            }
        }

        async function cdMoveAll(btn) {
            const idx     = btn.dataset.idx;
            const midx    = btn.dataset.midx;
            const groupEl = document.getElementById('cd-move-' + idx + '-' + midx);
            if (!groupEl) return;
            groupEl.style.display = 'block'; // expand so user can see progress
            const moveBtns = Array.from(groupEl.querySelectorAll('.cd-move-btn')).filter(b => !b.disabled && b.style.display !== 'none');
            if (!moveBtns.length) return;
            btn.disabled = true;
            let done = 0;
            btn.textContent = '0 / ' + moveBtns.length + '\u2026';
            try {
                for (const moveBtn of moveBtns) {
                    await cdMoveOne(moveBtn);
                    btn.textContent = (++done) + ' / ' + moveBtns.length + '\u2026';
                }
                btn.textContent = '\u2713 All moved';
                btn.style.background  = '#1a7a34';
                btn.style.borderColor = '#1a7a34';
            } catch(e) {
                console.error('[cs-seo] cdMoveAll failed', e);
                btn.disabled = false;
                btn.textContent = '\u2192 Move all (error — retry)';
            }
        }

        // =====================================================================
        // Category Migrate — admin UI
        // =====================================================================

        let cmCurrentCatId   = 0;
        let cmCurrentCatName = '';
        let cmAvailCats      = {}; // id → name map for the target dropdown

        function abEscCm(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ── Phase 1: load and render the category list ────────────────────────
        async function cmLoad() {
            const btn = document.getElementById('cm-load-btn');
            if (btn) { btn.disabled = true; btn.textContent = 'Loading\u2026'; }
            const wrap = document.getElementById('cm-cat-wrap');
            const cta  = document.getElementById('cm-cta');
            try {
                const fd = new FormData();
                fd.append('action', 'cs_seo_catmig_list');
                fd.append('nonce',  csSeoAdmin.nonce);
                const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Load failed');
                if (cta) cta.style.display = 'none';
                cmRenderCatList(d.categories || [], wrap);
            } catch (e) {
                if (wrap) wrap.innerHTML = '<p style="color:#c3372b;">Error: ' + abEscCm(e.message) + '</p>';
                if (btn) { btn.disabled = false; btn.textContent = '\u128260 Load Categories'; }
            }
        }

        function cmRenderCatList(cats, wrap) {
            if (!wrap) return;
            if (!cats.length) {
                wrap.innerHTML = '<p style="color:#888;font-size:13px;">No categories found.</p>';
                return;
            }
            let html = '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
                + '<thead><tr style="background:#f0f0f0;">'
                + '<th style="padding:8px 12px;text-align:left;width:80px;">Category ID</th>'
                + '<th style="padding:8px 12px;text-align:left;">Category</th>'
                + '<th style="padding:8px 12px;text-align:left;width:100px;">Posts</th>'
                + '<th style="padding:8px 12px;text-align:left;width:200px;">Action</th>'
                + '</tr></thead><tbody>';
            cats.forEach(function(c) {
                const countStyle = c.count === 0
                    ? 'color:#c3372b;font-weight:600;'
                    : c.count <= 3 ? 'color:#b35900;font-weight:600;' : 'color:#333;';
                const rowId = 'cm-cat-row-' + c.id;
                let actionHtml;
                if (c.count === 0) {
                    actionHtml = '<button type="button" class="button button-small cm-delete-cat-btn"'
                        + ' data-id="' + c.id + '" data-name="' + abEscCm(c.name) + '"'
                        + ' style="background:#c3372b;border-color:#c3372b;color:#fff;">'
                        + '\uD83D\uDDD1 Delete</button>';
                } else {
                    actionHtml = '<button type="button" class="button button-small cm-migrate-btn"'
                        + ' data-id="' + c.id + '" data-name="' + abEscCm(c.name) + '"'
                        + ' style="background:#b35900;border-color:#b35900;color:#fff;">'
                        + '\u2192 Migrate</button>';
                }
                html += '<tr id="' + rowId + '" style="border-bottom:1px solid #e5e7eb;">'
                    + '<td style="padding:8px 12px;color:#888;font-size:12px;">' + c.id + '</td>'
                    + '<td style="padding:8px 12px;">' + abEscCm(c.name) + '</td>'
                    + '<td style="padding:8px 12px;' + countStyle + '" id="cm-cat-count-' + c.id + '">' + c.count + '</td>'
                    + '<td style="padding:8px 12px;" id="cm-cat-action-' + c.id + '">' + actionHtml + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            wrap.innerHTML = html;
            wrap.querySelectorAll('.cm-migrate-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    cmLoadPosts(parseInt(btn.dataset.id, 10), btn.dataset.name);
                });
            });
            wrap.querySelectorAll('.cm-delete-cat-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    cmDeleteCat(parseInt(btn.dataset.id, 10), btn.dataset.name, btn,
                        document.getElementById('cm-cat-row-' + btn.dataset.id));
                });
            });
        }

        // ── Phase 2: load posts for the selected category ─────────────────────
        async function cmLoadPosts(catId, catName) {
            cmCurrentCatId   = catId;
            cmCurrentCatName = catName;

            const phase1 = document.getElementById('cm-phase1');
            const phase2 = document.getElementById('cm-phase2');
            const title  = document.getElementById('cm-p2-title');
            const status = document.getElementById('cm-p2-status');
            const wrap   = document.getElementById('cm-post-wrap');

            if (phase1) phase1.style.display = 'none';
            if (phase2) phase2.style.display = 'block';
            // Let the browser reflow, then scroll the card header into view.
            setTimeout(() => document.querySelector('.ab-card-catmig')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
            if (title)  title.textContent = 'Migrating posts from: \u201c' + catName + '\u201d';
            if (status) status.textContent = 'Loading posts\u2026';
            if (wrap)   wrap.innerHTML = '';

            // Reset header buttons to their initial state for each new migration session
            const applyAllBtn = document.getElementById('cm-apply-all-btn');
            if (applyAllBtn) { applyAllBtn.textContent = '\u2713 Apply All'; applyAllBtn.disabled = false; }
            const applyBadge = document.getElementById('cm-apply-result');
            if (applyBadge) applyBadge.style.display = 'none';
            const delBtn = document.getElementById('cm-delete-cat-btn');
            if (delBtn) delBtn.style.display = 'none';

            try {
                const fd = new FormData();
                fd.append('action',  'cs_seo_catmig_posts');
                fd.append('nonce',   csSeoAdmin.nonce);
                fd.append('cat_id',  catId);
                const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Load failed');
                cmAvailCats = d.avail_cats || {};
                cmRenderPostTable(d.posts || [], wrap, status);
            } catch (e) {
                if (wrap)   wrap.innerHTML = '<p style="color:#c3372b;">Error: ' + abEscCm(e.message) + '</p>';
                if (status) status.textContent = '';
            }
        }

        function cmRenderPostTable(posts, wrap, statusEl) {
            if (!wrap) return;
            // Sort: single-category posts (require a target selection) first
            posts = posts.slice().sort((a, b) => (b.is_single_cat ? 1 : 0) - (a.is_single_cat ? 1 : 0));
            const total = posts.length;
            if (statusEl) statusEl.textContent = total + ' post' + (total === 1 ? '' : 's') + ' in this category.';

            if (!total) {
                wrap.innerHTML = '<p style="color:#888;font-size:13px;">No published posts found in this category. You can delete it.</p>';
                cmShowDeleteBtn();
                return;
            }

            // Build the target dropdown options (shared across all rows)
            let targetOpts = '<option value="">— select target —</option>';
            Object.keys(cmAvailCats).forEach(function(id) {
                targetOpts += '<option value="' + id + '">' + abEscCm(cmAvailCats[id]) + '</option>';
            });

            let html = '<table style="width:100%;border-collapse:collapse;font-size:13px;min-width:620px;">'
                + '<thead><tr style="background:#f0f0f0;">'
                + '<th style="padding:8px 12px;text-align:left;">Post</th>'
                + '<th style="padding:8px 12px;text-align:left;width:160px;">Current Categories</th>'
                + '<th style="padding:8px 12px;text-align:left;width:220px;">Action</th>'
                + '<th style="padding:8px 12px;text-align:left;width:80px;">Status</th>'
                + '</tr></thead><tbody>';

            posts.forEach(function(p, i) {
                const rowId      = 'cm-row-' + i;
                const selectId   = 'cm-sel-' + i;
                const statusId   = 'cm-st-' + i;
                const isSingle   = p.is_single_cat;

                // Current categories as pills
                const catNames = Object.values(p.current_names || {});
                const catPills = catNames.map(function(n) {
                    const isSrc = (n === cmCurrentCatName);
                    return '<span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;margin:1px;background:'
                        + (isSrc ? '#b35900' : '#6b7280') + ';color:#fff;">' + abEscCm(n) + '</span>';
                }).join(' ');

                // Action select
                let actionHtml;
                if (isSingle) {
                    // Must swap — show only swap options with a label
                    actionHtml = '<span style="font-size:11px;color:#888;display:block;margin-bottom:3px;">Must swap (only category)</span>'
                        + '<select id="' + selectId + '" class="cm-target-sel" data-pid="' + p.post_id + '" data-single="1"'
                        + ' style="width:100%;max-width:200px;font-size:12px;" data-mode="swap">'
                        + targetOpts + '</select>';
                } else {
                    // Offer remove OR swap
                    actionHtml = '<select id="' + selectId + '" class="cm-target-sel" data-pid="' + p.post_id + '" data-single="0"'
                        + ' style="width:100%;max-width:200px;font-size:12px;" data-mode="">'
                        + '<option value="__remove">— Remove from this category —</option>'
                        + '<optgroup label="Swap to\u2026">' + targetOpts + '</optgroup>'
                        + '</select>';
                }

                html += '<tr id="' + rowId + '" style="border-bottom:1px solid #e5e7eb;">'
                    + '<td style="padding:8px 12px;">'
                    +   '<a href="' + abEscCm(p.post_url) + '" target="_blank" style="color:#1a4a7a;text-decoration:none;">' + abEscCm(p.title) + '</a>'
                    +   (p.edit_url ? ' <a href="' + abEscCm(p.edit_url) + '" target="_blank" title="Edit post" style="color:#888;font-size:11px;text-decoration:none;">&#9998;</a>' : '')
                    + '</td>'
                    + '<td style="padding:8px 12px;">' + catPills + '</td>'
                    + '<td style="padding:8px 12px;">'
                    +   actionHtml
                    + '</td>'
                    + '<td style="padding:8px 12px;" id="' + statusId + '">'
                    +   '<span style="color:#6b7280;font-size:11px;">Pending</span>'
                    + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            wrap.innerHTML = html;
        }

        // ── Apply a single row ────────────────────────────────────────────────
        async function cmApplyOne(pid, selectEl, statusEl) {
            const val      = selectEl.value;
            const isSingle = selectEl.dataset.single === '1';

            let migrateAction, toCatId;
            if (val === '__remove') {
                if (isSingle) {
                    statusEl.innerHTML = '<span style="color:#c3372b;font-size:11px;">Must select a target</span>';
                    return false;
                }
                migrateAction = 'remove';
                toCatId       = 0;
            } else if (!val) {
                statusEl.innerHTML = '<span style="color:#c3372b;font-size:11px;">Select an action first</span>';
                return false;
            } else {
                migrateAction = 'swap';
                toCatId       = parseInt(val, 10);
            }

            statusEl.innerHTML = '<span style="color:#888;font-size:11px;">Saving\u2026</span>';
            selectEl.disabled  = true;

            try {
                const fd = new FormData();
                fd.append('action',         'cs_seo_catmig_apply');
                fd.append('nonce',          csSeoAdmin.nonce);
                fd.append('post_id',        pid);
                fd.append('from_cat_id',    cmCurrentCatId);
                fd.append('migrate_action', migrateAction);
                if (toCatId) fd.append('to_cat_id', toCatId);
                const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Failed');

                const label = (migrateAction === 'remove')
                    ? 'Removed'
                    : 'Swapped \u2192 ' + abEscCm(cmAvailCats[toCatId] || toCatId);
                statusEl.innerHTML = '<span style="color:#1a7a34;font-size:11px;font-weight:600;">\u2713 ' + label + '</span>';
                selectEl.closest('tr').style.opacity = '0.45';
                cmCheckIfEmpty();
                return true;
            } catch (e) {
                statusEl.innerHTML = '<span style="color:#c3372b;font-size:11px;">Error: ' + abEscCm(e.message) + '</span>';
                selectEl.disabled  = false;
                return false;
            }
        }

        // ── Apply All ─────────────────────────────────────────────────────────
        async function cmApplyAll() {
            const btn    = document.getElementById('cm-apply-all-btn');
            const badge  = document.getElementById('cm-apply-result');
            const sels   = document.querySelectorAll('#cm-post-wrap .cm-target-sel:not(:disabled)');
            if (!sels.length) return;
            btn.disabled    = true;
            btn.textContent = '0 / ' + sels.length + '\u2026';
            if (badge) badge.style.display = 'none';
            let done = 0, failed = 0;
            for (const sel of sels) {
                const pid      = parseInt(sel.dataset.pid, 10);
                const statusEl = document.getElementById('cm-st-' + sel.id.replace('cm-sel-', ''));
                if (!statusEl) continue;
                if (!sel.value) continue; // skip rows with no selection
                const ok = await cmApplyOne(pid, sel, statusEl);
                if (ok) done++; else failed++;
                btn.textContent = done + ' / ' + sels.length + '\u2026';
            }
            btn.textContent = '\u2713 Apply All';
            btn.disabled    = false;
            if (badge) {
                badge.textContent = '\u2713 ' + done + ' applied' + (failed ? ', ' + failed + ' errors' : '');
                badge.style.background = failed ? '#fee2e2' : '#d1fae5';
                badge.style.color      = failed ? '#c3372b' : '#1a7a34';
                badge.style.display    = '';
            }
            btn.disabled    = false;
            const statusEl  = document.getElementById('cm-p2-status');
            if (statusEl) statusEl.textContent = done + ' post' + (done === 1 ? '' : 's') + ' migrated.';
            cmCheckIfEmpty();
        }

        // ── Show the delete button in Phase 2 ────────────────────────────────
        function cmShowDeleteBtn() {
            const btn = document.getElementById('cm-delete-cat-btn');
            if (!btn) return;
            btn.style.display = '';
            btn.textContent   = '\uD83D\uDDD1 Delete \u201c' + cmCurrentCatName + '\u201d';
            btn.onclick = function() {
                cmDeleteCat(cmCurrentCatId, cmCurrentCatName, btn, null);
            };
        }

        // ── Check if all rows are done; surface the delete button ─────────────
        function cmCheckIfEmpty() {
            const pending = document.querySelectorAll('#cm-post-wrap .cm-target-sel:not([disabled])');
            if (pending.length === 0) cmShowDeleteBtn();
        }

        // ── Delete a category (Phase 1 or Phase 2) ───────────────────────────
        async function cmDeleteCat(catId, catName, btn, rowEl) {
            if (!confirm('Delete the category \u201c' + catName + '\u201d?\n\nThis cannot be undone.')) return;
            btn.disabled    = true;
            btn.textContent = 'Deleting\u2026';
            try {
                const fd = new FormData();
                fd.append('action',  'cs_seo_catmig_delete');
                fd.append('nonce',   csSeoAdmin.nonce);
                fd.append('cat_id',  catId);
                const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) throw new Error(d.error || 'Delete failed');

                if (rowEl) {
                    // Phase 1: fade out the row
                    rowEl.style.opacity = '0.35';
                    const actionCell = document.getElementById('cm-cat-action-' + catId);
                    if (actionCell) actionCell.innerHTML = '<span style="color:#1a7a34;font-size:12px;">\u2713 Deleted</span>';
                } else {
                    // Phase 2: go back and refresh the list
                    const statusEl = document.getElementById('cm-p2-status');
                    if (statusEl) statusEl.innerHTML = '<span style="color:#1a7a34;">\u2713 Category deleted.</span>';
                    btn.style.display = 'none';
                    setTimeout(function() {
                        cmBack();
                        cmLoad(); // reload Phase 1 list with updated counts
                    }, 800);
                }
            } catch (e) {
                btn.disabled    = false;
                btn.textContent = '\uD83D\uDDD1 Delete \u201c' + catName + '\u201d';
                alert('Could not delete: ' + e.message);
            }
        }

        // ── Back to category list ─────────────────────────────────────────────
        let cmListLoaded = false;
        function cmBack() {
            // Hide the delete button so it doesn't carry over to the next migration
            const delBtn = document.getElementById('cm-delete-cat-btn');
            if (delBtn) delBtn.style.display = 'none';
            document.getElementById('cm-phase1').style.display = 'block';
            document.getElementById('cm-phase2').style.display = 'none';
            // Refresh the list so updated counts are reflected
            const wrap = document.getElementById('cm-cat-wrap');
            if (wrap && wrap.innerHTML) cmLoad();
        }

        // ── Wire up events ────────────────────────────────────────────────────
        document.getElementById('cm-load-btn')      ?.addEventListener('click', cmLoad);
        document.getElementById('cm-back-btn')      ?.addEventListener('click', cmBack);
        document.getElementById('cm-apply-all-btn') ?.addEventListener('click', cmApplyAll);

        // =====================================================================
        // Related Articles — admin UI
        // =====================================================================

        function rcCheckCountWarning(input, warnId) {
            const saved = parseInt(input.dataset.saved, 10) || 0;
            const now   = parseInt(input.value, 10) || 0;
            const warn  = document.getElementById(warnId);
            if (warn) warn.style.display = (now > saved) ? 'block' : 'none';
        }

        let rcCurrentFilter = 'all';
        let rcCurrentPage   = 1;
        let rcTotalPages    = 1;
        let rcBatchQueue    = [];
        let rcBatchRunning  = false;
        let rcBatchStop     = false;
        let rcBatchDone     = 0;
        let rcBatchTotal    = 0;

        const rcNonce = csSeoAdmin.nonce;

        // ── Status badge helper
        function rcBadge(status, step) {
            const map = {
                pending:    '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Pending</span>',
                processing: '<span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Processing</span>',
                complete:   '<span style="background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">&#9989; Complete</span>',
                error:      '<span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">&#10060; Error</span>',
                skipped:    '<span style="background:#f0fdf4;color:#16a34a;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">&#9745; Skipped</span>',
            };
            return map[status] || '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">' + status + '</span>';
        }

        // ── Load posts into table
        async function rcLoadTable(page, filter) {
            page   = page   || rcCurrentPage;
            filter = filter || rcCurrentFilter;
            rcCurrentPage   = page;
            rcCurrentFilter = filter;

            const tbody = document.getElementById('rc-posts-tbody');
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#999;">Loading…</td></tr>';

            const fd = new FormData();
            fd.append('action', 'cs_seo_rc_get_posts');
            fd.append('nonce',  rcNonce);
            fd.append('page',   page);
            fd.append('filter', filter);

            try {
                const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                const d = await r.json();
                if (!d.success) { tbody.innerHTML = '<tr><td colspan="6" style="color:#b91c1c;padding:24px;text-align:center;">Error: ' + abEsc(d.data?.message || 'Unknown') + '</td></tr>'; return; }

                rcTotalPages = d.data.total_pages || 1;
                const posts  = d.data.posts || [];

                if (!posts.length) {
                    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#999;">No posts found.</td></tr>';
                    rcRenderPagination();
                    return;
                }

                tbody.innerHTML = posts.map(p => {
                    const err = p.error ? '<br><small style="color:#b91c1c;">' + abEsc(p.error.substring(0, 80)) + '</small>' : '';
                    return '<tr id="rc-row-' + p.id + '">' +
                        '<td><a href="' + safeHref(p.permalink || '') + '" target="_blank" style="font-weight:500;">' + abEsc(p.title) + '</a></td>' +
                        '<td style="text-align:center;">' + rcBadge(p.status, p.last_step) + err + '</td>' +
                        '<td style="text-align:center;color:#6366f1;font-weight:600;">' + (p.top_count || '—') + '</td>' +
                        '<td style="text-align:center;color:#0e7490;font-weight:600;">' + (p.bot_count || '—') + '</td>' +
                        '<td style="text-align:center;color:#6b7280;font-size:12px;">' + (p.generated || '—') + '</td>' +
                        '<td style="text-align:center;">' +
                            '<button class="button" onclick="rcRunOne(' + p.id + ')" style="font-size:11px;padding:2px 8px;">&#9654; Run</button> ' +
                            '<button class="button" onclick="rcResetOne(' + p.id + ')" style="font-size:11px;padding:2px 8px;color:#b91c1c;border-color:#b91c1c;">&#128465;</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');

                rcRenderPagination();
            } catch(e) {
                tbody.innerHTML = '<tr><td colspan="6" style="color:#b91c1c;padding:24px;text-align:center;">Network error: ' + e.message + '</td></tr>';
            }
        }

        function rcRenderPagination() {
            const el = document.getElementById('rc-pagination');
            if (rcTotalPages <= 1) { el.innerHTML = ''; return; }
            let html = '<span style="font-size:13px;color:#1d2327;">Page</span>';
            for (let i = 1; i <= rcTotalPages; i++) {
                const active = i === rcCurrentPage ? 'button-primary' : '';
                html += '<button type="button" class="button ' + active + '" onclick="rcLoadTable(' + i + ')" style="min-width:32px;">' + i + '</button>';
            }
            el.innerHTML = html;
        }

        function rcSetFilter(filter, btn) {
            rcCurrentFilter = filter;
            rcCurrentPage   = 1;
            document.querySelectorAll('.rc-filter-btn').forEach(b => b.classList.remove('rc-filter-active'));
            if (btn) btn.classList.add('rc-filter-active');
            rcLoadTable(1, filter);
        }

        // ── Run all 8 steps for one post, polling until done
        // Updates a single table row in-place without reloading the full table or scrolling.
        function rcUpdateRow(postId, status, topCount, botCount, generated, errorMsg) {
            const row = document.getElementById('rc-row-' + postId);
            if (!row) return;
            row.querySelector('td:nth-child(2)').innerHTML = rcBadge(status) + (errorMsg ? '<br><small style="color:#b91c1c;">' + abEsc(errorMsg.substring(0, 80)) + '</small>' : '');
            row.querySelector('td:nth-child(3)').textContent = topCount || '\u2014';
            row.querySelector('td:nth-child(3)').style.color = topCount ? '#6366f1' : '';
            row.querySelector('td:nth-child(3)').style.fontWeight = topCount ? '600' : '';
            row.querySelector('td:nth-child(4)').textContent = botCount || '\u2014';
            row.querySelector('td:nth-child(4)').style.color = botCount ? '#0e7490' : '';
            row.querySelector('td:nth-child(4)').style.fontWeight = botCount ? '600' : '';
            row.querySelector('td:nth-child(5)').textContent = generated || '\u2014';
        }

        async function rcRunOne(postId) {
            const row = document.getElementById('rc-row-' + postId);
            if (row) {
                const statusCell = row.querySelector('td:nth-child(2)');
                if (statusCell) statusCell.innerHTML = '<span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;">Running\u2026</span>';
            }

            try {
                let done = false;
                while (!done) {
                    const fd = new FormData();
                    fd.append('action',  'cs_seo_rc_step');
                    fd.append('nonce',   rcNonce);
                    fd.append('post_id', postId);

                    const r = await fetch(ajaxurl, { method: 'POST', body: fd });
                    const d = await r.json();

                    if (!d.success) {
                        rcUpdateRow(postId, 'error', 0, 0, '', d.data?.message || d.error || 'Failed');
                        return;
                    }

                    done = d.data.done;
                }

                // Fetch final state for this post to update the row counts.
                // Use rcCurrentFilter (never 'all' which returns 0 in some environments).
                const fetchFilter = rcCurrentFilter && rcCurrentFilter !== 'all' ? rcCurrentFilter : 'complete';
                for (const pg of [rcCurrentPage, 1]) {
                    const fd2 = new FormData();
                    fd2.append('action', 'cs_seo_rc_get_posts');
                    fd2.append('nonce',  rcNonce);
                    fd2.append('page',   pg);
                    fd2.append('filter', fetchFilter);
                    try {
                        const r2 = await fetch(ajaxurl, { method: 'POST', body: fd2 });
                        const d2 = await r2.json();
                        if (d2.success) {
                            const p = (d2.data.posts || []).find(x => x.id === postId);
                            if (p) { rcUpdateRow(postId, p.status, p.top_count, p.bot_count, p.generated, p.error); break; }
                        }
                    } catch(e) { console.error('[cs-seo] rcRunOne: row-refresh failed for post ' + postId, e); }
                }
            } catch(e) {
                console.error('[cs-seo] rcRunOne failed for post ' + postId, e);
                rcUpdateRow(postId, 'error', 0, 0, '', 'Network error: ' + e.message);
            }
        }

        // ── Reset one post
        async function rcResetOne(postId) {
            if (!confirm('Reset Related Articles data for this post?')) return;
            const fd = new FormData();
            fd.append('action',  'cs_seo_rc_reset');
            fd.append('nonce',   rcNonce);
            fd.append('post_id', postId);
            fd.append('mode',    'one');
            await fetch(ajaxurl, { method: 'POST', body: fd });
            rcUpdateRow(postId, 'pending', 0, 0, '', '');
        }

        // ── Reset all posts
        async function rcResetAll() {
            if (!confirm('This will delete all Related Articles data for ALL posts. Are you sure?')) return;
            const fd = new FormData();
            fd.append('action', 'cs_seo_rc_reset');
            fd.append('nonce',  rcNonce);
            fd.append('mode',   'all');
            await fetch(ajaxurl, { method: 'POST', body: fd });
            await rcLoadTable(1, rcCurrentFilter || 'complete');
        }

        // ── Sync Counts (server-side trim, no pipeline) ───────────────────────
        async function rcSyncCounts() {
            const btn = document.getElementById('rc-btn-sync-counts');
            if (btn) { btn.disabled = true; btn.textContent = '⟳ Running…'; }
            const url = (typeof ajaxurl !== 'undefined' ? ajaxurl : null) || csSeoAdmin.ajaxUrl;
            try {
                const fd = new FormData();
                fd.append('action', 'cs_seo_rc_sync_counts');
                fd.append('nonce',  rcNonce);
                const r = await fetch(url, { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    const parts = [];
                    if (d.data.generated > 0) parts.push(d.data.generated + ' generated');
                    if (d.data.synced    > 0) parts.push(d.data.synced    + ' synced');
                    const summary = parts.length ? parts.join(', ') : 'nothing to update';
                    alert('✓ Done — ' + summary + ' (' + d.data.top_count + ' top / ' + d.data.bottom_count + ' bottom)');
                    await rcLoadTable(rcCurrentPage, rcCurrentFilter);
                } else {
                    alert('Failed: ' + (d.data?.message || 'unknown error'));
                }
            } catch(e) {
                alert('Network error: ' + e.message);
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = '⚙ Generate & Sync'; }
            }
        }

        // ── Batch generation
        async function rcBatch(mode) {
            if (rcBatchRunning) return;

            const url = (typeof ajaxurl !== 'undefined' ? ajaxurl : null) || csSeoAdmin.ajaxUrl;

            // ── Phase 1: collect IDs ──────────────────────────────────────────
            // Read from DOM first. If the table loaded with filter='all' and returned
            // zero posts (a known issue in some environments), force-reload with the
            // mode-appropriate filter so the tbody is populated before we scan it.
            const modeFilter = { missing: 'pending', stale: 'complete', failed: 'error' };
            const targetFilter  = modeFilter[mode] || 'complete';
            const prevFilter    = rcCurrentFilter;
            const prevPage      = rcCurrentPage;

            let domIds = Array.from(
                document.querySelectorAll('#rc-posts-tbody tr[id^="rc-row-"]')
            ).map(r => parseInt(r.id.replace('rc-row-', ''))).filter(n => n > 0);

            // Force-reload if the table is empty OR showing a different filter.
            if (!domIds.length || rcCurrentFilter !== targetFilter) {
                await rcLoadTable(1, targetFilter);
                domIds = Array.from(
                    document.querySelectorAll('#rc-posts-tbody tr[id^="rc-row-"]')
                ).map(r => parseInt(r.id.replace('rc-row-', ''))).filter(n => n > 0);
            }

            let allIds = [...domIds];

            // Fetch any additional pages (rcTotalPages updated by the load above).
            for (let pg = 2; pg <= rcTotalPages; pg++) {
                const fd = new FormData();
                fd.append('action', 'cs_seo_rc_get_posts');
                fd.append('nonce',  rcNonce);
                fd.append('page',   pg);
                fd.append('filter', rcCurrentFilter);
                try {
                    const r = await fetch(url, { method: 'POST', body: fd });
                    const d = await r.json();
                    if (d.success) allIds = allIds.concat((d.data.posts || []).map(p => p.id));
                } catch(e) { console.error('[cs-seo] rcBatch: page-fetch failed (pg=' + pg + ')', e); }
            }

            allIds = [...new Set(allIds)];

            // For 'missing' mode keep only rows currently showing Pending status.
            if (mode === 'missing') {
                allIds = allIds.filter(id => {
                    const row = document.getElementById('rc-row-' + id);
                    if (!row) return true;
                    const badge = row.querySelector('td:nth-child(2)');
                    return !badge || badge.textContent.trim() === 'Pending';
                });
            }

            if (!allIds.length) {
                // Restore the table to what it was showing before we force-reloaded.
                if (rcCurrentFilter !== prevFilter) await rcLoadTable(prevPage, prevFilter);
                alert('No posts found to process for this mode.');
                return;
            }

            // ── Phase 2: process ─────────────────────────────────────────────
            document.getElementById('rc-batch-label').textContent = 'Starting — ' + allIds.length + ' posts';
            rcBatchRunning = true;
            rcBatchStop    = false;
            rcBatchDone    = 0;
            rcBatchTotal   = allIds.length;

            document.getElementById('rc-batch-bar').style.display = 'block';
            rcUpdateBatchProgress();

            for (const postId of allIds) {
                if (rcBatchStop) break;
                // Stale and failed modes reset first so the step handler does
                // not short-circuit on status === 'complete'.
                if (mode === 'stale' || mode === 'failed') {
                    const fdR = new FormData();
                    fdR.append('action',  'cs_seo_rc_reset');
                    fdR.append('nonce',   rcNonce);
                    fdR.append('post_id', postId);
                    fdR.append('mode',    'one');
                    try { await fetch(url, { method: 'POST', body: fdR }); } catch(e) {}
                }
                try {
                    await rcRunOne(postId);
                } catch(e) {
                    console.error('[cs-seo] rcBatch: rcRunOne threw for post ' + postId, e);
                }
                rcBatchDone++;
                rcUpdateBatchProgress();
                await new Promise(res => setTimeout(res, 300));
            }

            rcBatchRunning = false;
            document.getElementById('rc-batch-bar').style.display = 'none';
            if (rcBatchDone > 0) abPost('cs_seo_rebuild_health', {}).catch(() => {});
            await rcLoadTable(rcCurrentPage, rcCurrentFilter);
        }

        function rcStopBatch() {
            rcBatchStop = true;
            rcBatchRunning = false;
            document.getElementById('rc-batch-bar').style.display = 'none';
        }

        function rcUpdateBatchProgress() {
            const pct = rcBatchTotal ? Math.round((rcBatchDone / rcBatchTotal) * 100) : 0;
            document.getElementById('rc-batch-progress-bar').style.width = pct + '%';
            document.getElementById('rc-batch-label').textContent = rcBatchDone + ' / ' + rcBatchTotal;
        }

        // ── Auto-load table when SEO tab is opened
        (function() {
            const origAbTab = typeof window.abTab === 'function' ? window.abTab : null;
            window.__rcTabLoaded = false;
            // Hook into tab switching via MutationObserver on the AI Tools panel
            const rcPane = document.getElementById('ab-pane-aitools');
            if (rcPane) {
                const obs = new MutationObserver(() => {
                    if (rcPane.classList.contains('active') && !window.__rcTabLoaded) {
                        window.__rcTabLoaded = true;
                        rcLoadTable(1, 'all');
                    }
                    if (!rcPane.classList.contains('active')) {
                        window.__rcTabLoaded = false;
                    }
                });
                obs.observe(rcPane, { attributes: true, attributeFilter: ['class'] });
                // If already on AI Tools tab on load
                if (rcPane.classList.contains('active')) {
                    window.__rcTabLoaded = true;
                    rcLoadTable(1, 'all');
                }
            }
        })();

        // Attach event listeners for all buttons (replaces inline onclick handlers)
        document.addEventListener('DOMContentLoaded', function() {
            // Helper function to add click listener by ID
            function on(id, handler) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', handler);
            }
            // AI Tools tab - Meta Description panel
            on('ab-test-key-btn', function() { if (typeof abTestKey === 'function') abTestKey(); });
            on('ab-copy-prompt', function() { if (typeof abCopyPrompt === 'function') abCopyPrompt(); });
            on('ab-reload-hdr', function() { if (typeof abLoadPosts === 'function') abLoadPosts(); });
            on('ab-ai-gen-missing', function() { if (typeof abGenAll === 'function') abGenAll(0); });
            on('ab-ai-gen-all', function() { if (typeof abGenAll === 'function') abGenAll(1); });
            on('ab-ai-fix', function() { if (typeof abFixAll === 'function') abFixAll(); });
            on('ab-ai-fix-titles', function() { if (typeof abFixTitles === 'function') abFixTitles(); });
            on('ab-ai-gen-missing-titles', function() { if (typeof abGenMissingTitles === 'function') abGenMissingTitles(); });
            on('ab-ai-static', function() { if (typeof abRegenStatic === 'function') abRegenStatic(); });
            on('ab-ai-score-all', function() { if (typeof abScoreAll === 'function') abScoreAll(); });
            on('ab-ai-stop', function() { if (typeof abStop === 'function') abStop(); });
            on('ab-prev', function() { if (typeof abPage === 'function') abPage(-1); });
            on('ab-next', function() { if (typeof abPage === 'function') abPage(1); });
            // ALT text panel
            on('ab-alt-reload-hdr', function() { if (typeof altLoad === 'function') altLoad(); });
            on('ab-alt-gen-all', function() { if (typeof altGenAll === 'function') altGenAll(false); });
            on('ab-alt-force-all', function() { if (typeof altGenAll === 'function') altGenAll(true); });
            on('ab-alt-stop', function() { if (typeof altStop === 'function') altStop(); });
            var altShowAll = document.getElementById('ab-alt-show-all');
            if (altShowAll) altShowAll.addEventListener('change', function() { if (typeof altState !== 'undefined') { altState.showAll = this.checked; if (typeof altRenderTable === 'function') altRenderTable(); } });
            // Summary panel
            on('ab-sum-reload-hdr', function() { if (typeof sumLoad === 'function') sumLoad(); });
            on('ab-sum-gen-all', function() { if (typeof sumGenAll === 'function') sumGenAll(false); });
            on('ab-sum-force-all', function() { if (typeof sumGenAll === 'function') sumGenAll(true); });
            on('ab-sum-stop', function() { if (typeof sumStop === 'function') sumStop(); });
            // Related Articles filters
            var rcFilters = document.querySelectorAll('.rc-filter-btn');
            rcFilters.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var filter = this.getAttribute('data-filter');
                    if (typeof rcSetFilter === 'function') rcSetFilter(filter, this);
                });
            });
            on('rc-btn-sync-counts', function() { if (typeof rcSyncCounts === 'function') rcSyncCounts(); });
            on('rc-btn-refresh-stale', function() { if (typeof rcBatch === 'function') rcBatch('stale'); });
            on('rc-btn-retry-failed', function() { if (typeof rcBatch === 'function') rcBatch('failed'); });
            on('rc-btn-reset-all', function() { if (typeof rcResetAll === 'function') rcResetAll(); });
            on('rc-btn-stop', function() { if (typeof rcStopBatch === 'function') rcStopBatch(); });
            // RC count inputs
            var rcTopInput = document.getElementById('rc_top_count_input');
            if (rcTopInput) rcTopInput.addEventListener('change', function() { if (typeof rcCheckCountWarning === 'function') rcCheckCountWarning(this, 'rc-top-warn'); });
            var rcBottomInput = document.getElementById('rc_bottom_count_input');
            if (rcBottomInput) rcBottomInput.addEventListener('change', function() { if (typeof rcCheckCountWarning === 'function') rcCheckCountWarning(this, 'rc-bottom-warn'); });
            // Robots.txt
            on('ab-rename-robots-btn', function() { if (typeof abRenameRobots === 'function') abRenameRobots(); });
            on('ab-robots-live-copy', function() { if (typeof abCopyRobotsLive === 'function') abCopyRobotsLive(); });
            on('ab-robots-copy', function() { if (typeof abCopyRobots === 'function') abCopyRobots(); });
            on('ab-robots-reset-btn', function() {
                var textarea = document.getElementById('cs-robots-txt');
                if (textarea && typeof csSeoAdmin !== 'undefined' && csSeoAdmin.defaultRobotsTxt) {
                    textarea.value = csSeoAdmin.defaultRobotsTxt;
                }
            });
            on('ab-robots-refresh-btn', function() { if (typeof abRefreshRobotsPreview === 'function') abRefreshRobotsPreview(); });
            // Sitemap
            on('ab-sitemap-load', function(e) { e.preventDefault(); if (typeof abLoadSitemap === 'function') abLoadSitemap(); });
            on('ab-sitemap-copy', function() { if (typeof abCopySitemap === 'function') abCopySitemap(); });
            // LLMS.txt
            on('ab-llms-copy', function() { if (typeof abCopyLlms === 'function') abCopyLlms(); });
            // Font optimization
            on('ab-font-scan-btn', function() { if (typeof abFontScan === 'function') abFontScan(this); });
            on('ab-font-download-btn', function() { if (typeof abFontDownload === 'function') abFontDownload(this); });
            on('ab-font-fix-btn', function() { if (typeof abFontFix === 'function') abFontFix(this); });
            on('ab-font-clear-btn', function() { if (typeof abFontClearConsole === 'function') abFontClearConsole(); });
            // Schedule toggle
            var schedToggle = document.getElementById('ab-schedule-toggle');
            if (schedToggle) schedToggle.addEventListener('change', function() { if (typeof csToggleSchedDays === 'function') csToggleSchedDays(this.checked); });
            // Category fix
            on('cf-reload-hdr', function() { if (typeof cfLoad === 'function') cfLoad(); });
            on('cf-scan-btn',   function() { if (typeof cfLoad === 'function') cfLoad(); });
            on('cf-f-all', function() { if (typeof cfFilter === 'function') cfFilter('all'); });
            on('cf-f-changed', function() { if (typeof cfFilter === 'function') cfFilter('changed'); });
            on('cf-f-unchanged', function() { if (typeof cfFilter === 'function') cfFilter('unchanged'); });
            on('cf-f-low', function() { if (typeof cfFilter === 'function') cfFilter('low'); });
            on('cf-f-missing', function() { if (typeof cfFilter === 'function') cfFilter('missing'); });
            on('cf-ai-btn', function() { if (typeof cfAiAnalyseAll === 'function') cfAiAnalyseAll(); });
            on('cf-bulk-btn', function() { if (typeof cfBulkApply === 'function') cfBulkApply(); });
            var cfCheckAll = document.getElementById('cf-check-all');
            if (cfCheckAll) cfCheckAll.addEventListener('change', function() { if (typeof cfToggleAll === 'function') cfToggleAll(this); });
            // Category health
            on('ch-reload-hdr',    function() { if (typeof chLoad === 'function') chLoad(); });
            on('ch-analyse-btn',   function() { if (typeof chLoad === 'function') chLoad(); });
            // Category drift
            on('cd-reload-hdr', function() { if (typeof cdLoad === 'function') cdLoad(); });
            on('cd-btn-cache', function() { if (typeof cdLoadFromCache === 'function') cdLoadFromCache(); });
            on('cd-btn-fresh', function() { if (typeof cdLoad === 'function') cdLoad(); });
            // AI provider change
            var providerSelect = document.getElementById('ab-ai-provider');
            if (providerSelect) {
                providerSelect.addEventListener('change', function() { if (typeof abProviderChanged === 'function') abProviderChanged(); });
                if (typeof abProviderChanged === 'function') abProviderChanged();
            }
        });

        // ── Broken Link Checker ──────────────────────────────────────────────
        (function() {
            var _ajax  = csSeoAdmin.ajaxUrl;
            var _nonce = csSeoAdmin.nonce;
            var blcStop           = false;
            var blcTotalLinks     = 0;
            var blcChecked        = 0;
            var blcBroken         = 0; // unique broken URLs (used for all-OK check)
            var blcBrokenRows     = 0; // total broken link instances across all posts
            var blcRedirects      = 0; // unique redirect URLs
            var blcRedirectRows   = 0; // total redirect link instances across all posts
            var blcPostCount      = 0;

            function blcPost(data) {
                return fetch(_ajax, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams(Object.assign({nonce: _nonce}, data)).toString()
                }).then(function(r){ return r.json(); });
            }
            function blcEsc(s) { var d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
            function blcSetStatus(msg) { var el=document.getElementById('blc-status'); if(el) el.textContent=msg; }
            function blcSetProgress(pct) {
                var p=document.getElementById('blc-progress'),f=document.getElementById('blc-progress-fill');
                if(p) p.classList.add('visible');
                if(f) f.style.width=Math.min(100,pct)+'%';
            }
            function blcUpdateSummary() {
                var s=document.getElementById('blc-summary'); if(s) s.style.display='grid';
                function setN(id,v){var e=document.getElementById(id);if(e) e.textContent=v;}
                setN('blc-total-posts', blcPostCount);
                setN('blc-total-links', blcChecked);
                setN('blc-broken-count', blcBrokenRows);
                setN('blc-redirect-count', blcRedirectRows);
            }
            function blcAddRow(item) {
                var tbody=document.getElementById('blc-tbody'), wrap=document.getElementById('blc-results-wrap');
                if(!tbody) return;
                if(wrap) wrap.style.display='block';
                var cls = item.status>=300&&item.status<400 ? 'ab-badge-short' : 'ab-badge-long';
                var tr=document.createElement('tr');
                tr.dataset.postTitle  = (item.post_title||'').toLowerCase();
                tr.dataset.dateTs     = item.post_date_ts||0;
                tr.dataset.statusCode = item.status||0;
                tr.innerHTML=
                    '<td><a href="'+blcEsc(item.post_url)+'" target="_blank" rel="noopener noreferrer">'+blcEsc(item.post_title)+'</a></td>'+
                    '<td style="word-break:break-all"><a href="'+blcEsc(item.url)+'" target="_blank" rel="noopener noreferrer">'+
                        blcEsc(item.url.length>70?item.url.substring(0,70)+'\u2026':item.url)+'</a></td>'+
                    '<td style="font-size:12px;color:#50575e">'+blcEsc(item.anchor||'\u2014')+'</td>'+
                    '<td style="white-space:nowrap;font-size:12px;color:#50575e">'+blcEsc(item.post_date||'\u2014')+'</td>'+
                    '<td><span class="ab-badge '+cls+'">'+blcEsc(item.label)+' ('+item.status+')</span></td>';
                tbody.appendChild(tr);
            }

            async function blcRunScan() {
                blcStop=false; blcChecked=0; blcBroken=0; blcBrokenRows=0; blcRedirects=0; blcRedirectRows=0; blcPostCount=0;
                var tbody=document.getElementById('blc-tbody');
                if(tbody) tbody.innerHTML='';
                document.getElementById('blc-results-wrap').style.display='none';
                document.getElementById('blc-all-ok').style.display='none';
                document.getElementById('blc-summary').style.display='none';
                var scanBtn=document.getElementById('blc-scan-btn'), stopBtn=document.getElementById('blc-stop-btn');
                if(scanBtn) scanBtn.disabled=true;
                if(stopBtn) stopBtn.style.display='inline-block';
                blcSetStatus('Loading posts\u2026'); blcSetProgress(0);
                try {
                    var r=await blcPost({action:'cs_seo_blc_get_posts'});
                    if(!r.success){ blcSetStatus('Error: '+(r.data||'unknown')); return; }
                    var postIds=r.data.post_ids;
                    blcPostCount=postIds.length;
                    if(!postIds.length){ blcSetStatus('No posts found.'); return; }

                    // Phase 1 — extract all links
                    var allLinks=[];
                    for(var i=0;i<postIds.length;i++){
                        if(blcStop) break;
                        blcSetProgress((i/postIds.length)*40);
                        blcSetStatus('Extracting links\u2026 post '+(i+1)+' of '+postIds.length);
                        var er=await blcPost({action:'cs_seo_blc_extract_links',post_id:postIds[i]});
                        if(er.success&&er.data.links){
                            er.data.links.forEach(function(lk){
                                allLinks.push({post_title:er.data.post_title,post_url:er.data.post_url,post_date_ts:er.data.post_date_ts||0,post_date:er.data.post_date||'',url:lk.url,anchor:lk.anchor});
                            });
                        }
                    }

                    // Phase 2 — check unique URLs
                    var uniqueUrls=[...new Set(allLinks.map(function(l){return l.url;}))];
                    blcTotalLinks=uniqueUrls.length;
                    var urlCache={};
                    for(var k=0;k<uniqueUrls.length;k++){
                        if(blcStop) break;
                        blcSetProgress(40+(k/uniqueUrls.length)*60);
                        blcSetStatus('Checking URL '+(k+1)+' of '+uniqueUrls.length+'\u2026');
                        var url=uniqueUrls[k];
                        var res={status:0,ok:false,label:'Error'};
                        try {
                            var cr=await blcPost({action:'cs_seo_blc_check_url',url:url});
                            if(cr&&cr.success) res={status:cr.data.status,ok:cr.data.ok,label:cr.data.label};
                        } catch(urlErr) {
                            console.warn('[BLC] error checking URL',url,urlErr.message);
                            res={status:0,ok:false,label:'Request error'};
                        }
                        urlCache[url]=res;
                        blcChecked++;
                        if(!res.ok){
                            if(res.status>=300&&res.status<400) blcRedirects++;
                            else blcBroken++;
                            allLinks.forEach(function(lk){
                                if(lk.url===url){
                                    blcAddRow(Object.assign({},lk,res));
                                    if(res.status>=300&&res.status<400) blcRedirectRows++;
                                    else blcBrokenRows++;
                                }
                            });
                        }
                        blcUpdateSummary();
                    }
                    blcSetProgress(100);
                    blcSetStatus('Done \u2014 '+blcChecked+' URLs checked.');
                    if(blcBroken===0&&blcRedirects===0){
                        document.getElementById('blc-all-ok').style.display='block';
                    }
                } catch(e){
                    blcSetStatus('Error: '+e.message);
                } finally {
                    if(scanBtn) scanBtn.disabled=false;
                    if(stopBtn) stopBtn.style.display='none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                var sb=document.getElementById('blc-scan-btn');
                if(sb) sb.addEventListener('click', blcRunScan);
                var st=document.getElementById('blc-stop-btn');
                if(st) st.addEventListener('click', function(){ blcStop=true; blcSetStatus('Stopping\u2026'); });
                // ── BLC table sort ────────────────────────────────────────────
                var blcTable=document.getElementById('blc-table');
                if(!blcTable) return;
                var blcSortState={col:'',dir:1};
                blcTable.querySelectorAll('thead [data-blc-sort]').forEach(function(th){
                    th.addEventListener('click',function(){
                        var col=th.dataset.blcSort;
                        var dir=(blcSortState.col===col&&blcSortState.dir===-1)?1:-1;
                        blcSortState={col:col,dir:dir};
                        blcTable.querySelectorAll('thead .blc-sort-icon').forEach(function(ic){ic.textContent='\u2195';ic.style.opacity='0.5';});
                        var icon=th.querySelector('.blc-sort-icon');
                        if(icon){icon.textContent=dir===-1?'\u2193':'\u2191';icon.style.opacity='1';}
                        var tbody=document.getElementById('blc-tbody');
                        if(!tbody) return;
                        var rows=Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                        rows.sort(function(a,b){
                            if(col==='post_title'){
                                var av=a.dataset.postTitle||'', bv=b.dataset.postTitle||'';
                                return av<bv?-dir:av>bv?dir:0;
                            }
                            var av=parseFloat(col==='date_ts'?a.dataset.dateTs:a.dataset.statusCode)||0;
                            var bv=parseFloat(col==='date_ts'?b.dataset.dateTs:b.dataset.statusCode)||0;
                            return(av-bv)*dir;
                        });
                        rows.forEach(function(row){tbody.appendChild(row);});
                    });
                });
            });
        })();

        // ── Image SEO Audit ──────────────────────────────────────────────────
        (function() {
            var _ajax  = csSeoAdmin.ajaxUrl;
            var _nonce = csSeoAdmin.nonce;

            function imgPost(data) {
                return fetch(_ajax, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams(Object.assign({nonce: _nonce}, data)).toString()
                }).then(function(r){ return r.json(); });
            }
            function imgEsc(s){ var d=document.createElement('div'); d.textContent=String(s); return d.innerHTML; }
            function imgFmt(b){ if(b>1048576) return (b/1048576).toFixed(1)+' MB'; if(b>1024) return Math.round(b/1024)+' KB'; return b+' B'; }

            function imgRender(data) {
                function setN(id,v){ var e=document.getElementById(id); if(e) e.textContent=v; }
                setN('imgseo-total', data.total);
                setN('imgseo-missing-alt', data.missing_alt);
                setN('imgseo-bad-fname', data.bad_filename);
                setN('imgseo-large', data.large_file);
                document.getElementById('imgseo-summary').style.display='grid';

                var tbody=document.getElementById('imgseo-tbody');
                var wrap=document.getElementById('imgseo-results-wrap');
                var allOk=document.getElementById('imgseo-all-ok');
                if(!data.images||!data.images.length){
                    if(allOk) allOk.style.display='block';
                    if(wrap) wrap.style.display='none';
                    return;
                }
                if(wrap) wrap.style.display='block';
                if(allOk) allOk.style.display='none';
                tbody.innerHTML='';
                data.images.forEach(function(img){
                    var badges=img.issues.map(function(issue){
                        var lbl=issue==='missing_alt'?'&#10060; No ALT':issue==='bad_filename'?'&#9888;&#65039; Bad filename':'&#128230; Large file';
                        var cls=issue==='missing_alt'?'ab-badge-long':issue==='bad_filename'?'ab-badge-short':'ab-badge-none';
                        return '<span class="ab-badge '+cls+'" style="display:block;margin-bottom:3px">'+lbl+'</span>';
                    }).join('');
                    var thumb=img.thumb_url
                        ? '<img src="'+imgEsc(img.thumb_url)+'" style="width:48px;height:48px;object-fit:cover;border-radius:3px" loading="lazy">'
                        : '<span style="font-size:28px">&#128444;</span>';
                    var parentCell=img.parent_edit
                        ? '<a href="'+imgEsc(img.parent_edit)+'" target="_blank">'+imgEsc(img.parent_title||'(no title)')+'</a>'
                        : '<span style="color:#999">Unattached</span>';
                    var altCell=img.alt
                        ? '<span style="font-size:12px;color:#1a7a34">'+imgEsc(img.alt.substring(0,60))+(img.alt.length>60?'\u2026':'')+'</span>'
                        : '<span style="color:#9b1c1c;font-style:italic;font-size:12px">None</span>';
                    var tr=document.createElement('tr');
                    tr.innerHTML=
                        '<td style="text-align:center">'+thumb+'</td>'+
                        '<td style="font-size:12px;word-break:break-all"><a href="'+imgEsc(img.edit_url)+'" target="_blank">'+imgEsc(img.filename)+'</a></td>'+
                        '<td>'+parentCell+'</td>'+
                        '<td style="white-space:nowrap;font-size:12px">'+imgFmt(img.filesize)+'</td>'+
                        '<td>'+altCell+'</td>'+
                        '<td>'+badges+'</td>'+
                        '<td><a href="'+imgEsc(img.edit_url)+'" class="button button-small" target="_blank">Edit</a></td>';
                    tbody.appendChild(tr);
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                var btn=document.getElementById('imgseo-scan-btn');
                if(!btn) return;
                btn.addEventListener('click', async function(){
                    btn.disabled=true; btn.textContent='\u27f3 Scanning\u2026';
                    var status=document.getElementById('imgseo-status');
                    if(status) status.textContent='Scanning Media Library\u2026';
                    try {
                        var r=await imgPost({action:'cs_seo_imgseo_scan'});
                        if(r.success){
                            imgRender(r.data);
                            if(status) status.textContent='Done \u2014 '+r.data.with_issues+' issue(s) across '+r.data.total+' images.';
                        } else {
                            if(status) status.textContent='Error: '+(r.data||'unknown');
                        }
                    } catch(e){
                        if(status) status.textContent='Error: '+e.message;
                    } finally {
                        btn.disabled=false; btn.textContent='\uD83D\uDD0D Scan Media Library';
                    }
                });
            });
        })();

        // ── Title Optimiser ───────────────────────────────────────────────────
        (function() {
            const toState = { running: false, stopped: false, sessionDone: 0, sortBy: 'date', filter: 'all', threshold: 10, posts: [], page: 0, polling: false, pollTimer: null, lastLoggedId: 0 };
            const TO_PAGE = 50;

            function toLog(msg, cls) {
                const wrap = document.getElementById('ab-titleopt-log-wrap');
                const log  = document.getElementById('ab-titleopt-log');
                if (!wrap || !log) return;
                wrap.style.display = 'block';
                const d = document.createElement('div');
                d.className = 'ab-log-entry' + (cls ? ' ' + cls : '');
                d.textContent = msg;
                log.prepend(d);
            }

            function toLogHtml(html, cls) {
                const wrap = document.getElementById('ab-titleopt-log-wrap');
                const log  = document.getElementById('ab-titleopt-log');
                if (!wrap || !log) return;
                wrap.style.display = 'block';
                const d = document.createElement('div');
                d.className = 'ab-log-entry' + (cls ? ' ' + cls : '');
                d.innerHTML = html;
                log.prepend(d);
            }

            function toSetStatus(msg) {
                var s = document.getElementById('ab-titleopt-status');
                var l = document.getElementById('ab-titleopt-prog-label');
                if (s) s.textContent = msg;
                if (l) l.textContent = msg;
            }

            function toSetProgress(pct) {
                var f = document.getElementById('ab-titleopt-progress-fill');
                if (f) f.style.width = pct + '%';
            }

            function toScoreBadge(score, small) {
                if (!score) return '<span style="color:#aaa;font-size:11px">—</span>';
                var col = score >= 71 ? '#1a7a34' : score >= 41 ? '#b45309' : '#9b1c1c';
                var bg  = score >= 71 ? '#dcfce7' : score >= 41 ? '#fef3c7' : '#fee2e2';
                var sz  = small ? '11px' : '13px';
                return '<span style="display:inline-block;padding:2px 8px;border-radius:10px;background:' + bg + ';color:' + col + ';font-weight:700;font-size:' + sz + '">' + score + '</span>';
            }

            function toStatusBadge(status, stale) {
                var badge = '';
                if (status === 'applied')   badge = '<span class="ab-badge ab-badge-ok">✓ Applied</span>';
                else if (status === 'suggested') badge = '<span class="ab-badge" style="background:#e0e7ff;color:#3730a3">💡 Suggested</span>';
                else badge = '<span class="ab-badge ab-badge-none">Pending</span>';
                if (stale) badge += '<div style="margin-top:3px"><span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;border:1px solid #fcd34d">⚠ Edited since analysis</span></div>';
                return badge;
            }

            function toKwHtml(kws) {
                if (!kws || !kws.length) return '<span style="color:#aaa;font-size:11px">—</span>';
                return kws.map(function(k) {
                    return '<span style="display:inline-block;background:#f0f0f0;border-radius:4px;padding:1px 6px;font-size:11px;margin:1px 2px;color:#374151">' + abEsc(k) + '</span>';
                }).join('');
            }

            /** Returns true if this post would be included in "Apply All" with the current threshold. */
            function toWillApply(p) {
                if (p.status !== 'suggested') return false;
                var thresh = toState.threshold || 0;
                if (thresh <= 0) return true; // no threshold — all suggested titles qualify
                if (!p.score_before || p.score_before <= 0) return false; // guard against division by zero
                return ((p.score_after - p.score_before) / p.score_before * 100) >= thresh;
            }

            window.titleOptSort = function(by) {
                var serverSorts = { date: true, comments: true };
                toState.sortBy = by;
                if (serverSorts[by]) {
                    toLoad();
                } else {
                    if (by === 'score_before' || by === 'score_after') {
                        toState.posts.sort(function(a, b) { return (b[by] || 0) - (a[by] || 0); });
                    } else if (by === 'stale') {
                        toState.posts.sort(function(a, b) {
                            if (a.stale !== b.stale) return (b.stale ? 1 : 0) - (a.stale ? 1 : 0);
                            var sOrd = { suggested: 0, applied: 1 };
                            return (sOrd[a.status] !== undefined ? sOrd[a.status] : 2) - (sOrd[b.status] !== undefined ? sOrd[b.status] : 2);
                        });
                    } else if (by === 'status') {
                        var ord = { applied: 0, suggested: 1 };
                        toState.posts.sort(function(a, b) { return (ord[a.status] !== undefined ? ord[a.status] : 2) - (ord[b.status] !== undefined ? ord[b.status] : 2); });
                    } else if (by === 'title') {
                        toState.posts.sort(function(a, b) { return (a.title || '').localeCompare(b.title || ''); });
                    } else if (by === 'will_apply') {
                        toState.posts.sort(function(a, b) { return (toWillApply(b) ? 1 : 0) - (toWillApply(a) ? 1 : 0); });
                    }
                    toState.page = 0;
                    toRenderTable();
                }
            };

            function toUpdateAnalyseBtn() {
                var btn = document.getElementById('ab-titleopt-analyse-all');
                if (!btn) return;
                var total     = toState.posts.length;
                var analysed  = toState.posts.filter(function(p) { return p.suggested; }).length;
                var remaining = total - analysed;
                if (remaining > 0) {
                    btn.textContent = '🔍 Analyse Remaining ' + remaining;
                    btn.title = '';
                } else {
                    btn.textContent = '🔍 Analyse Remaining';
                    btn.title = 'All posts have suggestions — use Re-analyse All to regenerate';
                }
            }

            async function toLoad() {
                toSetStatus('Loading…');
                var data = await abPost('cs_seo_title_optimiser_load', { sort: toState.sortBy });
                if (!data.success) { toSetStatus('Error: ' + (data.data || 'Unknown')); return; }
                var d = data.data;
                toState.posts = d.posts || [];
                toState.page  = 0;
                var s = d.posts ? d.posts.filter(function(p) { return p.suggested; }).length : 0;
                document.getElementById('titleopt-s-total').textContent    = d.total;
                document.getElementById('titleopt-s-analysed').textContent = s;
                document.getElementById('titleopt-s-applied').textContent  = d.applied;
                var applyBtn = document.getElementById('ab-titleopt-apply-all');
                var suggested = toState.posts.filter(function(p) { return p.status === 'suggested'; }).length;
                if (applyBtn) applyBtn.disabled = suggested === 0;
                toSetStatus(s === 0 ? 'Click "Analyse Remaining" to get SEO title suggestions' : s + ' analysed, ' + d.applied + ' applied');
                document.getElementById('ab-titleopt-reload-hdr').style.visibility = 'visible';
                toUpdateAnalyseBtn();
                toRenderTable();
                // Auto-resume polling if a background job is already running
                if (!toState.polling) {
                    abPost('cs_seo_title_queue_status', {}).then(function(r) {
                        if (r.success && r.data.running) {
                            toLog('⚡ Background analysis in progress — resuming tracking.', 'ab-log-ok');
                            toStartPolling();
                        }
                    });
                }
            }

            function toSetFilter(f) { toState.filter = f; toState.page = 0; toRenderTable(); }

            function toRenderTable() {
                var wrap = document.getElementById('ab-titleopt-posts-wrap');
                if (!wrap) return;
                var allPosts = toState.posts || [];
                if (!allPosts.length) { wrap.innerHTML = '<p style="color:#50575e;margin-top:8px">No published posts found.</p>'; return; }

                // Filter
                var cntSuggested = allPosts.filter(function(p) { return p.status === 'suggested'; }).length;
                var cntApplied   = allPosts.filter(function(p) { return p.status === 'applied'; }).length;
                var cntPending   = allPosts.filter(function(p) { return !p.suggested; }).length;
                var cntWillApply = allPosts.filter(function(p) { return toWillApply(p); }).length;
                var posts = toState.filter === 'suggested'  ? allPosts.filter(function(p) { return p.status === 'suggested'; })
                          : toState.filter === 'applied'    ? allPosts.filter(function(p) { return p.status === 'applied'; })
                          : toState.filter === 'pending'    ? allPosts.filter(function(p) { return !p.suggested; })
                          : toState.filter === 'will_apply' ? allPosts.filter(function(p) { return toWillApply(p); })
                          : allPosts;

                var mkFBtn = function(f, label, cnt, accentColor) {
                    var active = toState.filter === f;
                    var style = active
                        ? 'background:' + (accentColor || '#1d2327') + ';color:#fff;border-color:' + (accentColor || '#1d2327') + ';font-weight:700'
                        : 'background:#fff;color:#50575e;border-color:#ddd';
                    return '<button class="button" style="font-size:12px;padding:3px 10px;' + style + '" onclick="toSetFilter(\'' + f + '\')">' + label + ' <span style="opacity:0.7">(' + cnt + ')</span></button>';
                };
                var willApplyLabel = toState.threshold > 0 ? 'Will Apply ≥' + toState.threshold + '%' : 'Will Apply';
                var filterBar = '<div style="display:flex;gap:6px;align-items:center;margin-bottom:10px;flex-wrap:wrap">' +
                    '<span style="font-size:12px;color:#50575e;font-weight:600;margin-right:2px">Filter:</span>' +
                    mkFBtn('all',        'All',           allPosts.length) +
                    mkFBtn('will_apply', willApplyLabel,  cntWillApply, '#1a7a34') +
                    mkFBtn('suggested',  'Pending Apply', cntSuggested) +
                    mkFBtn('applied',    'Applied',       cntApplied) +
                    mkFBtn('pending',    'Not Analysed',  cntPending) +
                    '</div>';

                var totalPages = Math.ceil(posts.length / TO_PAGE);
                if (toState.page >= totalPages) toState.page = Math.max(0, totalPages - 1);
                var start = toState.page * TO_PAGE;
                var page  = posts.slice(start, start + TO_PAGE);

                var thBase = 'padding:9px 10px;background:#1d2327;color:#fff;font-weight:700;font-size:12px;white-space:nowrap;border-right:1px solid #3a4450;text-align:left';
                var thSort = thBase + ';cursor:pointer;user-select:none';
                var thCenter = thSort + ';text-align:center';
                var arrow = function(by) { return toState.sortBy === by ? ' <span style="opacity:0.9">↓</span>' : ' <span style="opacity:0.25">↕</span>'; };

                var rows = page.map(function(p, idx) {
                    var rowBg = idx % 2 === 0 ? '#fff' : '#f9fafb';
                    // Use original_title (captured at analysis time) so the "before" state is always the pre-optimisation title.
                    // For posts analysed before tracking was added (original_title empty + applied), show a "re-analyse to compare" hint.
                    var displayTitle = p.original_title || p.title;
                    var noOriginal   = !p.original_title && p.status === 'applied';
                    var titleInner   = abEsc(displayTitle)
                        + (noOriginal ? '<div style="font-size:10px;color:#aaa;font-style:italic;margin-top:2px">Title was changed to suggested title</div>' : '');
                    var editIcon = p.edit_link
                        ? ' <a href="' + safeHref(p.edit_link) + '" target="_blank" title="Edit post" style="color:#aaa;font-size:11px;text-decoration:none;margin-left:4px">✏</a>'
                        : '';
                    var titleCell = p.post_url
                        ? '<a href="' + safeHref(p.post_url) + '" target="_blank" style="color:#1d2327;text-decoration:none;border-bottom:1px dotted #aaa" title="View post">' + titleInner + '</a>' + editIcon
                        : titleInner + editIcon;
                    var scoreArrow = (p.score_before && p.score_after && p.score_after > p.score_before)
                        ? ' <span style="color:#1a7a34;font-size:11px">↑' + (p.score_after - p.score_before) + '</span>' : '';
                    var willApply = toWillApply(p);
                    var gainPct   = (p.score_before > 0 && p.score_after > p.score_before)
                        ? Math.round(((p.score_after - p.score_before) / p.score_before) * 100)
                        : null;
                    var willApplyCell = willApply
                        ? '<span style="display:inline-block;background:#dcfce7;color:#1a7a34;font-weight:700;font-size:12px;padding:2px 8px;border-radius:10px;white-space:nowrap">✓ Yes' + (gainPct !== null ? ' +' + gainPct + '%' : '') + '</span>'
                        : (p.status === 'suggested' && toState.threshold > 0
                            ? '<span style="color:#aaa;font-size:11px">Below threshold' + (gainPct !== null ? ' (+' + gainPct + '%)' : '') + '</span>'
                            : '<span style="color:#ddd;font-size:11px">—</span>');
                    var suggestedCell = p.suggested
                        ? '<span style="color:#3730a3;font-weight:600">' + abEsc(p.suggested) + '</span>'
                        : '<span style="color:#aaa;font-size:11px;font-style:italic">Not yet analysed</span>';
                    var notesCell = p.notes
                        ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;font-style:italic">' + abEsc(p.notes) + '</div>'
                        : '';
                    var actionBtns = p.status === 'applied'
                        ? '<button class="button button-small" onclick="toAnalyseOne(' + p.id + ')" style="margin-right:4px" title="Re-analyse this post">🔍 Re-analyse</button>'
                        : '<button class="button button-small ab-action-btn" onclick="toAnalyseOne(' + p.id + ')" id="titleopt-analyse-' + p.id + '" style="margin-right:4px">🔍 Analyse</button>'
                          + (p.suggested && p.status !== 'applied' ? '<button class="button button-small" onclick="toApplyOne(' + p.id + ')" id="titleopt-apply-' + p.id + '" style="background:#1a7a34;border-color:#155724;color:#fff">✅ Apply</button>' : '');
                    var tdStyle = 'padding:8px 10px;border-right:1px solid #ececec';
                    return '<tr id="titleopt-row-' + p.id + '" style="border-bottom:1px solid #ddd;background:' + rowBg + '">' +
                        '<td style="' + tdStyle + ';font-size:13px;max-width:220px;word-break:break-word">' + titleCell + '</td>' +
                        '<td style="' + tdStyle + ';font-size:12px;color:#50575e;white-space:nowrap" class="titleopt-date-' + p.id + '">' + abEsc(p.date || '—') + (p.analysed_at ? '<div style="font-size:10px;color:#aaa;margin-top:1px">analysed ' + abEsc(p.analysed_at) + '</div>' : '') + '</td>' +
                        '<td style="' + tdStyle + ';text-align:center;white-space:nowrap" class="titleopt-score-before-' + p.id + '">' + toScoreBadge(p.score_before) + '</td>' +
                        '<td style="' + tdStyle + ';font-size:13px;max-width:240px;word-break:break-word" class="titleopt-suggested-' + p.id + '">' + suggestedCell + notesCell + '</td>' +
                        '<td style="' + tdStyle + ';max-width:160px" class="titleopt-kw-' + p.id + '">' + toKwHtml(p.keywords) + '</td>' +
                        '<td style="' + tdStyle + ';text-align:center;white-space:nowrap" class="titleopt-score-after-' + p.id + '">' + toScoreBadge(p.score_after) + scoreArrow + '</td>' +
                        '<td style="' + tdStyle + ';text-align:center;white-space:nowrap" class="titleopt-will-apply-' + p.id + '">' + willApplyCell + '</td>' +
                        '<td style="' + tdStyle + ';text-align:center;white-space:nowrap" class="titleopt-status-' + p.id + '">' + toStatusBadge(p.status, p.stale) + '</td>' +
                        '<td style="padding:8px 10px;white-space:nowrap" class="titleopt-actions-' + p.id + '">' + actionBtns + '</td>' +
                        '</tr>';
                }).join('');

                var pager = '';
                if (totalPages > 1) {
                    var from = start + 1, to = Math.min(start + TO_PAGE, posts.length);
                    pager = '<div style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:13px;color:#50575e">' +
                        '<button class="button" onclick="toState.page--;toRenderTable()" ' + (toState.page === 0 ? 'disabled' : '') + '>← Prev</button>' +
                        '<span>Page ' + (toState.page+1) + ' of ' + totalPages + ' &nbsp;·&nbsp; ' + from + '–' + to + ' of ' + posts.length + '</span>' +
                        '<button class="button" onclick="toState.page++;toRenderTable()" ' + (toState.page >= totalPages-1 ? 'disabled' : '') + '>Next →</button>' +
                        '</div>';
                }

                var willApplyHeader = toState.threshold > 0
                    ? 'Will Apply ≥' + toState.threshold + '%'
                    : 'Will Apply';
                wrap.innerHTML = filterBar + '<table style="width:100%;border-collapse:collapse;font-size:13px;border:1px solid #ddd">' +
                    '<thead><tr>' +
                    '<th style="' + thSort + ';min-width:180px" onclick="titleOptSort(\'title\')">Post Title' + arrow('title') + '</th>' +
                    '<th style="' + thSort + ';min-width:90px" onclick="titleOptSort(\'date\')">Date' + arrow('date') + '</th>' +
                    '<th style="' + thCenter + '" onclick="titleOptSort(\'score_before\')">SEO Before' + arrow('score_before') + '</th>' +
                    '<th style="' + thBase + ';min-width:200px">Suggested Title</th>' +
                    '<th style="' + thBase + '">Keywords</th>' +
                    '<th style="' + thCenter + '" onclick="titleOptSort(\'score_after\')">SEO After' + arrow('score_after') + '</th>' +
                    '<th style="' + thCenter + '" onclick="titleOptSort(\'will_apply\')" title="Sort by Will Apply — posts that meet the current threshold first">' + willApplyHeader + arrow('will_apply') + '</th>' +
                    '<th style="' + thCenter + '" onclick="titleOptSort(\'stale\')" title="Sort: edited posts first">Status / Edited' + arrow('stale') + '</th>' +
                    '<th style="' + thBase + '">Actions</th>' +
                    '</tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                    '</table>' + pager;

                // Keep Apply All button in sync with threshold + suggestions
                var applyAllBtnRef = document.getElementById('ab-titleopt-apply-all');
                if (applyAllBtnRef) {
                    var willApplyCount = allPosts.filter(function(p) { return toWillApply(p); }).length;
                    applyAllBtnRef.disabled = willApplyCount === 0;
                    applyAllBtnRef.textContent = '✅ Apply to ' + willApplyCount + ' post' + (willApplyCount !== 1 ? 's' : '');
                }

                window.toState       = toState;
                window.toRenderTable = toRenderTable;
                window.toSetFilter   = toSetFilter;
            }

            window.toAnalyseOne = async function(postId) {
                var row    = document.getElementById('titleopt-row-' + postId);
                var btn    = document.getElementById('titleopt-analyse-' + postId);
                if (btn) { btn.disabled = true; btn.textContent = '⟳ Analysing…'; }
                try {
                    var data = await abPost('cs_seo_title_optimise_one', { post_id: postId });
                    if (data.success) {
                        var d = data.data;
                        var p = (toState.posts || []).find(function(x) { return x.id === postId; });
                        if (p) {
                            p.suggested    = d.suggested_title;
                            p.keywords     = d.keywords;
                            p.score_before = d.score_before;
                            p.score_after  = d.score_after;
                            p.notes        = d.notes;
                            p.status       = 'suggested';
                            p.stale        = false;
                        }
                        var scoreArrow = (d.score_after > d.score_before)
                            ? ' <span style="color:#1a7a34;font-size:11px">↑' + (d.score_after - d.score_before) + '</span>' : '';
                        var el;
                        el = row && row.querySelector('.titleopt-score-before-' + postId);
                        if (el) el.innerHTML = toScoreBadge(d.score_before);
                        el = row && row.querySelector('.titleopt-suggested-' + postId);
                        if (el) el.innerHTML = '<span style="color:#3730a3;font-weight:600">' + abEsc(d.suggested_title) + '</span>'
                            + (d.notes ? '<div style="font-size:11px;color:#6b7280;margin-top:2px;font-style:italic">' + abEsc(d.notes) + '</div>' : '');
                        el = row && row.querySelector('.titleopt-kw-' + postId);
                        if (el) el.innerHTML = toKwHtml(d.keywords);
                        el = row && row.querySelector('.titleopt-score-after-' + postId);
                        if (el) el.innerHTML = toScoreBadge(d.score_after) + scoreArrow;
                        el = row && row.querySelector('.titleopt-status-' + postId);
                        if (el) el.innerHTML = toStatusBadge('suggested', false);
                        el = row && row.querySelector('.titleopt-actions-' + postId);
                        if (el) el.innerHTML =
                            '<button class="button button-small ab-action-btn" onclick="toAnalyseOne(' + postId + ')" style="margin-right:4px">🔍 Analyse</button>' +
                            '<button class="button button-small" onclick="toApplyOne(' + postId + ')" id="titleopt-apply-' + postId + '" style="background:#1a7a34;border-color:#155724;color:#fff">✅ Apply</button>';
                        toState.sessionDone++;
                        document.getElementById('titleopt-s-session').textContent = toState.sessionDone;
                        var analysedCount = (toState.posts || []).filter(function(x) { return x.suggested; }).length;
                        document.getElementById('titleopt-s-analysed').textContent = analysedCount;
                        var applyBtn = document.getElementById('ab-titleopt-apply-all');
                        var suggested = (toState.posts || []).filter(function(x) { return x.status === 'suggested'; }).length;
                        if (applyBtn) applyBtn.disabled = suggested === 0;
                        toLog('✓ ' + abEsc(d.suggested_title) + ' [' + d.score_before + '→' + d.score_after + ']', 'ab-log-ok');
                    } else {
                        toLog('✗ Error for post #' + postId + ': ' + (data.data || 'Unknown'), 'ab-log-error');
                    }
                } catch (e) {
                    toLog('✗ Network error: ' + e.message, 'ab-log-error');
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = '🔍 Analyse'; }
                }
            };

            window.toApplyOne = async function(postId) {
                if (!confirm('Apply this suggested title? This will update the post title and URL slug, and create a 301 redirect from the old URL.')) return;
                var row = document.getElementById('titleopt-row-' + postId);
                var btn = document.getElementById('titleopt-apply-' + postId);
                if (btn) { btn.disabled = true; btn.textContent = '⟳ Applying…'; }
                try {
                    var data = await abPost('cs_seo_title_apply_one', { post_id: postId });
                    if (data.success) {
                        var d = data.data;
                        var p = (toState.posts || []).find(function(x) { return x.id === postId; });
                        if (p) p.status = 'applied';
                        var el = row && row.querySelector('.titleopt-status-' + postId);
                        if (el) el.innerHTML = toStatusBadge('applied');
                        el = row && row.querySelector('.titleopt-actions-' + postId);
                        if (el) el.innerHTML = '<button class="button button-small" onclick="toAnalyseOne(' + postId + ')" title="Re-analyse this post">🔍 Re-analyse</button>';
                        var titleEl = row && row.querySelector('td:first-child');
                        if (titleEl && d.new_title) {
                            var linkEl = titleEl.querySelector('a');
                            if (linkEl) linkEl.textContent = d.new_title;
                        }
                        var appliedCount = (toState.posts || []).filter(function(x) { return x.status === 'applied'; }).length;
                        document.getElementById('titleopt-s-applied').textContent = appliedCount;
                        var suggested = (toState.posts || []).filter(function(x) { return x.status === 'suggested'; }).length;
                        var applyBtn = document.getElementById('ab-titleopt-apply-all');
                        if (applyBtn) applyBtn.disabled = suggested === 0;
                        toLog('✓ Applied: ' + abEsc(d.new_title || '') + (d.redirected ? ' (redirect created)' : ''), 'ab-log-ok');
                    } else {
                        toLog('✗ Apply failed for post #' + postId + ': ' + (data.data || 'Unknown'), 'ab-log-error');
                    }
                } catch (e) {
                    toLog('✗ Network error: ' + e.message, 'ab-log-error');
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = '✅ Apply'; }
                }
            };

            async function toQueueStart(force) {
                if (toState.polling) toStopPollingUI(); // clear any stale polling state before starting fresh
                if (force && !confirm('Re-analyse ALL ' + toState.posts.length + ' posts, overwriting existing suggestions. Continue?')) return;
                toSetProgress(0);
                toLog('⚡ Queuing posts for background analysis…', 'ab-log-ok');
                try {
                    var data = await abPost('cs_seo_title_queue_start', { force: force ? 1 : 0 });
                    if (!data.success) { toLog('✗ ' + (data.data || 'Unknown error'), 'ab-log-error'); return; }
                    var d = data.data;
                    if (d.queued === 0) {
                        toLog('✓ All ' + toState.posts.length + ' posts have suggestions — nothing left to analyse. Use Re-analyse All to refresh.', 'ab-log-ok');
                        toSetStatus('✓ All posts analysed');
                        toUpdateAnalyseBtn();
                        return;
                    }
                    toLog('⚡ ' + d.queued + ' posts queued — running in background. Safe to close this tab.', 'ab-log-ok');
                    toSetStatus('⚡ Background: 0 of ' + d.queued + ' processed');
                    toStartPolling();
                } catch (e) {
                    toLog('✗ Failed to start queue: ' + e.message, 'ab-log-error');
                }
            }

            function toStartPolling() {
                if (toState.polling) return;
                toState.polling     = true;
                toState.lastLoggedId = 0;
                // Keep analyse buttons enabled — clicking them while polling stops and restarts cleanly via toQueueStart → toStopPollingUI
                document.getElementById('ab-titleopt-apply-all').disabled   = true;
                document.getElementById('ab-titleopt-stop').style.display   = '';
                toPollStatus();
                toState.pollTimer = setInterval(toPollStatus, 3000);
            }

            function toStopPollingUI() {
                toState.polling = false;
                if (toState.pollTimer) { clearInterval(toState.pollTimer); toState.pollTimer = null; }
                document.getElementById('ab-titleopt-analyse-all').disabled = false;
                document.getElementById('ab-titleopt-force-all').disabled   = false;
                document.getElementById('ab-titleopt-stop').style.display   = 'none';
                var suggested = (toState.posts || []).filter(function(p) { return p.status === 'suggested'; }).length;
                document.getElementById('ab-titleopt-apply-all').disabled = suggested === 0;
            }

            async function toPollStatus() {
                try {
                    var data = await abPost('cs_seo_title_queue_status', {});
                    if (!data.success) return;
                    var d = data.data;

                    if (d.last_error) toLog('✗ ' + abEsc(d.last_error), 'ab-log-error');

                    if (d.last_post_id && d.last_post_id !== toState.lastLoggedId) {
                        toState.lastLoggedId = d.last_post_id;
                        var scores = (d.last_scores && d.last_scores.length === 2)
                            ? '[' + d.last_scores[0] + '→' + d.last_scores[1] + '] ' : '';
                        toLog('✓ ' + scores + abEsc(d.last_title), 'ab-log-ok');
                        var p = (toState.posts || []).find(function(x) { return x.id === d.last_post_id; });
                        if (p) {
                            p.suggested    = d.last_title;
                            p.score_before = d.last_scores[0];
                            p.score_after  = d.last_scores[1];
                            p.stale        = false;
                            if (p.status !== 'applied') p.status = 'suggested';
                        }
                        toState.sessionDone++;
                        document.getElementById('titleopt-s-session').textContent  = toState.sessionDone;
                        document.getElementById('titleopt-s-analysed').textContent = d.processed;
                        var pct = d.total > 0 ? Math.round((d.processed / d.total) * 100) : 0;
                        toSetProgress(pct);
                        toUpdateAnalyseBtn();
                        toRenderTable();
                    }

                    toSetStatus('⚡ Background: ' + d.processed + ' of ' + d.total + ', ' + d.remaining + ' remaining');

                    if (!d.running) {
                        toStopPollingUI();
                        toSetProgress(100);
                        toSetStatus('✓ Done — ' + d.processed + ' of ' + d.total + ' analysed.');
                        toLog('✓ Background analysis complete — ' + d.processed + ' posts processed.', 'ab-log-ok');
                        await toLoad();
                    }
                } catch (e) {
                    // swallow network errors during poll — will retry on next interval
                }
            }

            async function toQueueStop() {
                if (toState.pollTimer) { clearInterval(toState.pollTimer); toState.pollTimer = null; }
                try {
                    var data = await abPost('cs_seo_title_queue_stop', {});
                    if (data.success) toLog('⏹ Stopped — ' + (data.data.processed || 0) + ' processed this run.', 'ab-log-ok');
                } catch (e) { /* ignore */ }
                toStopPollingUI();
                await toLoad();
            }

            async function toApplyAll() {
                var pending = (toState.posts || []).filter(function(p) { return toWillApply(p); });
                if (pending.length === 0) {
                    if (toState.threshold > 0) {
                        alert('No suggested titles meet the ' + toState.threshold + '% improvement threshold.\n\nTry lowering the threshold or run "Analyse All" to refresh scores.');
                    } else {
                        alert('No suggested titles to apply. Run "Analyse All" first.');
                    }
                    return;
                }
                var allSuggested = (toState.posts || []).filter(function(x) { return x.status === 'suggested'; });
                var confirmMsg = toState.threshold > 0
                    ? 'Apply ' + pending.length + ' of ' + allSuggested.length + ' suggested titles (improvement ≥' + toState.threshold + '%)?\n\nEach URL slug will be updated and a 301 redirect created. This cannot be undone in bulk.'
                    : 'Apply all ' + pending.length + ' suggested titles? Each post\'s URL slug will be updated and a 301 redirect created from the old URL. This cannot be undone in bulk.';
                if (!confirm(confirmMsg)) return;

                var applyBtn = document.getElementById('ab-titleopt-apply-all');
                var total = pending.length, done = 0, redirects = 0, errors = 0;

                function setCounter() {
                    if (applyBtn) applyBtn.textContent = '⟳ Applying ' + done + ' / ' + total + '…';
                }

                if (applyBtn) { applyBtn.disabled = true; setCounter(); }

                for (var i = 0; i < pending.length; i++) {
                    var p = pending[i];
                    try {
                        var data = await abPost('cs_seo_title_apply_one', { post_id: p.id });
                        if (data.success) {
                            var d = data.data;
                            done++;
                            if (d.redirected) redirects++;
                            // Update local state so table re-renders correctly
                            var sp = (toState.posts || []).find(function(x) { return x.id === p.id; });
                            if (sp) { sp.status = 'applied'; sp.stale = false; }
                            toLog('✓ ' + abEsc(d.new_title || p.suggested || '') + (d.redirected ? ' (↪ redirect)' : ''), 'ab-log-ok');
                            // Update stats bar
                            document.getElementById('titleopt-s-applied').textContent =
                                (toState.posts || []).filter(function(x) { return x.status === 'applied'; }).length;
                        } else {
                            errors++;
                            toLog('✗ #' + p.id + ': ' + abEsc(data.data || 'Unknown error'), 'ab-log-error');
                        }
                    } catch (e) {
                        errors++;
                        toLog('✗ #' + p.id + ' network error: ' + abEsc(e.message), 'ab-log-error');
                    }
                    setCounter();
                    toRenderTable();
                }

                toLog('✓ Done — ' + done + ' of ' + total + ' applied, ' + redirects + ' redirect' + (redirects !== 1 ? 's' : '') + (errors ? ', ' + errors + ' error(s)' : ''), 'ab-log-ok');
                if (applyBtn) { applyBtn.disabled = false; toRenderTable(); }
                var remaining = (toState.posts || []).filter(function(p) { return p.status === 'suggested'; }).length;
                if (applyBtn) applyBtn.disabled = remaining === 0;
                await toLoad();
            }

            function toSetLinkScanResult(msg, style) {
                var el = document.getElementById('ab-titleopt-link-scan-result');
                if (!el) return;
                el.textContent = msg;
                el.style.cssText = 'display:inline-block;font-size:12px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap;' + style;
            }

            async function toScanBrokenLinks() {
                var scanBtn = document.getElementById('ab-titleopt-scan-links');
                var fixBtn  = document.getElementById('ab-titleopt-fix-links');
                if (scanBtn) { scanBtn.disabled = true; scanBtn.textContent = '⟳ Scanning…'; }
                if (fixBtn)  { fixBtn.disabled = true; }
                toSetLinkScanResult('Scanning…', 'background:#f0f0f0;color:#50575e');
                var logWrap = document.getElementById('ab-titleopt-log-wrap');
                if (logWrap) logWrap.style.display = '';
                try {
                    var data = await abPost('cs_seo_title_scan_links', {});
                    if (data.success) {
                        var d = data.data;
                        if (d.broken_posts.length === 0) {
                            toSetLinkScanResult('✓ No broken internal links', 'background:#dcfce7;color:#1a7a34');
                            toLog('✓ Scan complete — ' + d.redirects_checked + ' redirect(s) checked, no broken internal links found.', 'ab-log-ok');
                            if (fixBtn) fixBtn.disabled = true;
                        } else {
                            toSetLinkScanResult('⚠ ' + d.broken_posts.length + ' post(s) have broken links', 'background:#fef3c7;color:#92400e');
                            toLog('⚠ Scan complete — ' + d.redirects_checked + ' redirect(s) checked, ' + d.broken_posts.length + ' post(s) with broken links:', 'ab-log-warn');
                            d.broken_posts.forEach(function(p) {
                                var editLink = p.post_edit ? ' <a href="' + abEsc(p.post_edit) + '" target="_blank" rel="noopener" style="color:#2271b1">[edit]</a>' : '';
                                toLogHtml('&nbsp;&nbsp;• ' + abEsc(p.post_title) + editLink + ' — ' + p.old_urls.length + ' old URL(s)', 'ab-log-info');
                            });
                            toLog('Click "Fix Broken Links" to rewrite these links to their current destinations.', 'ab-log-info');
                            if (fixBtn) fixBtn.disabled = false;
                        }
                    } else {
                        toSetLinkScanResult('✗ Scan failed', 'background:#fee2e2;color:#9b1c1c');
                        toLog('✗ Scan failed: ' + abEsc(data.data || 'Unknown error'), 'ab-log-error');
                        if (fixBtn) fixBtn.disabled = true;
                    }
                } catch (e) {
                    toSetLinkScanResult('✗ Network error', 'background:#fee2e2;color:#9b1c1c');
                    toLog('✗ Network error: ' + abEsc(e.message), 'ab-log-error');
                    if (fixBtn) fixBtn.disabled = true;
                }
                if (scanBtn) { scanBtn.disabled = false; scanBtn.textContent = '🔍 Scan Broken Links'; }
            }

            async function toFixInternalLinks() {
                var fixBtn  = document.getElementById('ab-titleopt-fix-links');
                var scanBtn = document.getElementById('ab-titleopt-scan-links');
                if (!confirm('Rewrite internal post links from old redirect sources to their current destinations?\n\nThis updates post content directly. Safe to run multiple times.')) return;
                if (fixBtn)  { fixBtn.disabled = true; fixBtn.textContent = '⟳ Fixing…'; }
                if (scanBtn) { scanBtn.disabled = true; }
                var logWrap = document.getElementById('ab-titleopt-log-wrap');
                if (logWrap) logWrap.style.display = '';
                try {
                    var data = await abPost('cs_seo_title_fix_links', {});
                    if (data.success) {
                        var d = data.data;
                        toSetLinkScanResult('✓ Fixed — ' + d.posts_updated + ' post(s) updated', 'background:#dcfce7;color:#1a7a34');
                        toLog('✓ Internal links fixed — ' + d.processed + ' redirect(s) scanned, ' + d.posts_updated + ' post(s) updated', 'ab-log-ok');
                        if (fixBtn) fixBtn.disabled = true;
                    } else {
                        toSetLinkScanResult('✗ Fix failed', 'background:#fee2e2;color:#9b1c1c');
                        toLog('✗ Fix failed: ' + abEsc(data.data || 'Unknown error'), 'ab-log-error');
                    }
                } catch (e) {
                    toSetLinkScanResult('✗ Network error', 'background:#fee2e2;color:#9b1c1c');
                    toLog('✗ Network error: ' + abEsc(e.message), 'ab-log-error');
                }
                if (fixBtn)  { fixBtn.disabled = false; fixBtn.textContent = '🔗 Fix Broken Links'; }
                if (scanBtn) { scanBtn.disabled = false; }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Auto-load when Title Optimiser tab is activated
                var pane = document.getElementById('ab-pane-titleopt');
                if (!pane) return;
                var obs = new MutationObserver(function() {
                    if (pane.classList.contains('active') && !window.__titleOptLoaded) {
                        window.__titleOptLoaded = true;
                        toLoad();
                    }
                });
                obs.observe(pane, { attributes: true, attributeFilter: ['class'] });
                // Also fire immediately if pane is already active on load (e.g. after page refresh)
                if (pane.classList.contains('active') && !window.__titleOptLoaded) {
                    window.__titleOptLoaded = true;
                    toLoad();
                }

                var btn = document.getElementById('ab-titleopt-analyse-all');
                if (btn) btn.addEventListener('click', function() { toQueueStart(false); });
                var forceBtn = document.getElementById('ab-titleopt-force-all');
                if (forceBtn) forceBtn.addEventListener('click', function() { toQueueStart(true); });
                var applyAllBtn = document.getElementById('ab-titleopt-apply-all');
                if (applyAllBtn) applyAllBtn.addEventListener('click', toApplyAll);
                var threshInput = document.getElementById('ab-titleopt-threshold');
                if (threshInput) {
                    threshInput.addEventListener('input', function() {
                        toState.threshold = parseFloat(this.value) || 0;
                        toRenderTable();
                    });
                }
                var scanLinksBtn = document.getElementById('ab-titleopt-scan-links');
                if (scanLinksBtn) scanLinksBtn.addEventListener('click', toScanBrokenLinks);
                var fixLinksBtn = document.getElementById('ab-titleopt-fix-links');
                if (fixLinksBtn) fixLinksBtn.addEventListener('click', toFixInternalLinks);
                var stopBtn = document.getElementById('ab-titleopt-stop');
                if (stopBtn) stopBtn.addEventListener('click', toQueueStop);
                var reloadBtn = document.getElementById('ab-titleopt-reload-hdr');
                if (reloadBtn) reloadBtn.addEventListener('click', toLoad);
            });
        })();

        <?php wp_add_inline_script('cs-seo-admin-js', ob_get_clean()); ?>
        </div><!-- /wrap -->
        <?php
    }

    private function tr_text(string $k, string $label, array $o, string $placeholder = '', string $hint = ''): void { ?>
        <tr><th><label><?php echo esc_html($label); ?></label></th>
            <td>
                <input class="regular-text"
                    name="<?php echo esc_attr(self::OPT); ?>[<?php echo esc_attr($k); ?>]"
                    value="<?php echo esc_attr((string)($o[$k] ?? '')); ?>"
                    <?php if ($placeholder) echo 'placeholder="' . esc_attr($placeholder) . '"'; ?>>
                <?php if ($hint): ?>
                    <p class="description"><?php echo esc_html($hint); ?></p>
                <?php endif; ?>
            </td></tr>
    <?php }

    private function tr_bool(string $k, string $label, array $o): void { ?>
        <tr><th><?php echo esc_html($label); ?></th>
            <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPT); ?>[<?php echo esc_attr($k); ?>]" value="1" <?php checked((int)($o[$k] ?? 0), 1); ?>> Enabled</label></td></tr>
    <?php }

    // admin_page_css(), llms_preview_js(), sitemap_preview_js() live in trait-settings-assets.php.

}
