<?php
/**
 * Default option values and option-retrieval helpers.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.12.2
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Options {

    /**
     * Returns the full set of default plugin option values.
     *
     * @since 4.0.0
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        $site = get_bloginfo('name');
        return [
            'site_name'               => $site,
            'site_lang'               => 'en-US',
            'title_suffix'            => ' | ' . $site,
            'home_title'              => $site,
            'home_desc'               => '',
            'default_desc'            => '',
            'default_og_image'        => '',
            'twitter_handle'          => '',
            'enable_og'               => 1,
            'enable_schema_person'    => 1,
            'enable_schema_website'   => 1,
            'enable_schema_article'   => 1,
            'enable_schema_breadcrumbs' => 1,
            'show_summary_box'          => 1,
            'strip_tracking_params'   => 1,
            'enable_sitemap'          => 0,
            'enable_llms_txt'         => 0,
            'noindex_search'          => 1,
            'noindex_404'             => 1,
            'noindex_attachment'      => 1,
            'noindex_author_archives' => 0,
            'noindex_tag_archives'    => 0,
            'person_name'             => '',
            'person_job_title'        => '',
            'person_url'              => home_url('/'),
            'person_image'            => '',
            'sameas'                  => '',
            'robots_txt'              => self::default_robots_txt(),
            'block_ai_bots'           => 1,
            'sitemap_post_types'      => ['post', 'page'],
            'sitemap_taxonomies'      => 0,
            'sitemap_exclude'         => '',
            'defer_js'                => 0,
            'defer_js_excludes'       => '',
            'defer_fonts'             => 0,
            'minify_html'             => 0,
            'font_display_enabled'    => 1,
            'font_display_value'      => 'swap',
            'font_metric_overrides'   => 1,
            // Related Articles
            'rc_enable'               => 1,
            'rc_top_enabled'          => 1,
            'rc_bottom_enabled'       => 1,
            'rc_top_count'            => 3,
            'rc_bottom_count'         => 5,
            'rc_pool_size'            => 20,
            'rc_use_categories'       => 1,
            'rc_use_tags'             => 1,
            'rc_use_summary'          => 1,
            'rc_exclude_cats'         => [],
        ];
    }

    /**
     * Returns the full set of default AI option values.
     *
     * @since 4.0.0
     * @return array<string,mixed>
     */
    /**
     * Returns the recommended model ID for a given provider.
     * This is the model used when the model setting is '_auto'.
     *
     * @since 4.20.0
     * @param string $provider 'anthropic' or 'gemini'.
     * @return string Model ID.
     */
    public static function recommended_model(string $provider): string {
        return $provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-sonnet-4-6';
    }

    public static function ai_defaults(): array {
        return [
            'ai_provider'      => 'anthropic',
            'anthropic_key'    => '',
            'gemini_key'       => '',
            'model'            => '_auto',
            'overwrite'        => 0,
            'min_chars'        => 140,
            'max_chars'        => 155,
            'alt_excerpt_chars'=> 600,
            'prompt'           => self::default_prompt(),
            'auto_run_enabled'   => 0,
            'auto_run_on_update' => 0,
            'schedule_enabled' => 0,
            'schedule_days'    => [],
        ];
    }

    /**
     * Returns the default robots.txt content shipped with the plugin.
     *
     * @since 4.0.0
     * @return string
     */
    public static function default_robots_txt(): string {
        return "User-agent: Googlebot\nAllow: /\nDisallow: /wp-admin/\nDisallow: /wp-login.php\nDisallow: /xmlrpc.php\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?prp_page_paginated_recent_posts\n\nUser-agent: *\nAllow: /\nDisallow: /wp-admin/\nDisallow: /wp-login.php\nDisallow: /xmlrpc.php\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?prp_page_paginated_recent_posts";
    }

    /**
     * Returns the default AI meta description prompt text.
     *
     * @since 4.0.0
     * @return string
     */
    private static function default_prompt(): string {
        return 'You are an expert SEO copywriter. Site context will be injected automatically from the site settings below.

Write a single meta description for the article provided. Rules:
- HARD LIMIT: The character range is specified separately — count carefully before outputting. If your draft exceeds the maximum, shorten it. If it is under the minimum, expand it.
- Include the primary keyword or topic naturally in the first half
- Must be a complete, compelling sentence that makes a reader want to click
- No marketing fluff. No "In this post..." or "This article covers..." openers
- Write as a factual, punchy statement about what the article delivers
- Output ONLY the meta description text — no quotes, no labels, nothing else';
    }

    /**
     * Loads saved plugin options merged with defaults.
     *
     * @since 4.0.0
     * @return array<string,mixed>
     */
    private function get_opts(): array {
        $saved = get_option(self::OPT, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    /**
     * Loads saved AI options merged with defaults.
     *
     * @since 4.0.0
     * @return array<string,mixed>
     */
    private function get_ai_opts(): array {
        $saved = get_option(self::AI_OPT, []);
        $merged = array_merge(self::ai_defaults(), is_array($saved) ? $saved : []);

        // Migration: retired/removed models → current replacements.
        $model_migrations = [
            'gemini-2.5-flash-preview-04-17' => 'gemini-2.0-flash',
            'gemini-2.5-pro-preview-03-25'   => 'gemini-2.0-flash',
        ];
        if (isset($model_migrations[$merged['model'] ?? ''])) {
            $merged['model'] = $model_migrations[$merged['model']];
            update_option(self::AI_OPT, $merged);
        }

        return $merged;
    }
}
