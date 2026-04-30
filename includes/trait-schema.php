<?php
/**
 * JSON-LD structured data output (WebSite, Person, Article, BreadcrumbList schemas).
 *
 * Schema tags are printed via wp_print_inline_script_tag() on wp_head to satisfy
 * PCP compliance — no raw <script> strings are echoed from PHP.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Schema {
    /**
     * Prints all applicable JSON-LD structured data blocks via wp_print_inline_script_tag().
     *
     * Called from render_head() after the rest of the SEO head block is echoed.
     * Using wp_print_inline_script_tag() avoids echoing raw <script> strings, which
     * is required for WordPress.org PCP compliance.
     *
     * @since 4.15.4
     * @return void
     */
    private function print_schema_tags(): void {
        $noindex = $this->is_noindexed();

        // Per-page JSON-LD schema stored by the help-doc generator in _cs_schema_json post meta.
        // This bypasses wp_kses_post (which strips <script> tags) by keeping schema out of content entirely.
        if (is_singular() && !$noindex) {
            $raw = (string) get_post_meta(get_the_ID(), self::META_PAGE_SCHEMA, true);
            if ($raw !== '') {
                $schema = json_decode($raw, true);
                if (is_array($schema)) {
                    $this->print_schema_tag($schema);
                }
            }
        }

        if ((int) $this->opts['enable_schema_website'] && (is_front_page() || is_home())) {
            $this->print_schema_tag($this->schema_website());
        }
        if ((int) $this->opts['enable_schema_person'] && !$noindex) {
            $this->print_schema_tag($this->schema_person());
        }
        if ((int) $this->opts['enable_schema_breadcrumbs'] && !$noindex) {
            $bc = $this->schema_breadcrumbs();
            if ($bc) $this->print_schema_tag($bc);
        }
        if ((int) $this->opts['enable_schema_article'] && is_singular('post') && !$noindex) {
            $art = $this->schema_article();
            if ($art) $this->print_schema_tag($art);
        }
    }

    /**
     * Outputs a single JSON-LD schema block using the WordPress API.
     *
     * wp_print_inline_script_tag() (WP 5.7+) is the correct API for arbitrary
     * script types — it sets type="application/ld+json" and escapes correctly.
     *
     * @since 4.15.4
     * @param array $schema Associative array conforming to schema.org structure.
     * @return void
     */
    private function print_schema_tag(array $schema): void {
        wp_print_inline_script_tag(
            (string) wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG),
            ['type' => 'application/ld+json']
        );
    }

    private function schema_website(): array {
        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => (string) $this->opts['site_name'],
            'url'             => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => home_url('/?s={search_term_string}')],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    private function schema_person(): array {
        $sameAs = array_values(array_filter(
            array_map('trim', (array) preg_split('/\r\n|\r|\n/', (string) $this->opts['sameas']))
        ));
        $s = [
            '@context' => 'https://schema.org',
            '@type'    => 'Person',
            'name'     => (string) $this->opts['person_name'],
            'jobTitle' => (string) $this->opts['person_job_title'],
            'url'      => (string) $this->opts['person_url'],
        ];
        if ($sameAs) $s['sameAs'] = $sameAs;
        $img = trim((string) $this->opts['person_image']);
        if ($img) $s['image'] = $img;
        return $s;
    }

    private function schema_article(): ?array {
        if (!is_singular()) return null;
        $pid  = (int) get_queried_object_id();
        $post = get_post($pid);
        if (!$post) return null;

        $img        = $this->og_image_data();
        $cats       = get_the_category($pid);
        $tags       = wp_get_post_tags($pid, ['fields' => 'names']);
        $word_count = str_word_count(wp_strip_all_tags((string) $post->post_content));
        $mins       = max(1, (int) ceil($word_count / 200));
        $published  = get_post_time('c', true, $pid);
        $modified   = get_post_modified_time('c', true, $pid);

        $s = [
            '@context'         => 'https://schema.org',
            '@type'            => 'BlogPosting',
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $this->canonical_url()],
            'headline'         => get_the_title($pid),
            'description'      => $this->meta_desc(),
            'author'           => [
                '@type' => 'Person',
                'name'  => get_the_author_meta('display_name', (int) $post->post_author) ?: (string) $this->opts['person_name'],
                'url'   => (string) $this->opts['person_url'],
            ],
            'publisher' => [
                '@type' => 'Person',
                'name'  => (string) $this->opts['person_name'],
                'url'   => (string) $this->opts['person_url'],
            ],
            'wordCount'    => $word_count,
            'timeRequired' => 'PT' . $mins . 'M',
        ];

        if ($published) $s['datePublished'] = $published;
        if ($modified)  $s['dateModified']  = $modified;

        if ($img['url']) {
            $image = ['@type' => 'ImageObject', 'url' => $img['url']];
            if ($img['width'])  $image['width']  = $img['width'];
            if ($img['height']) $image['height'] = $img['height'];
            $s['image'] = [$image];
        }

        $pimg = trim((string) $this->opts['person_image']);
        if ($pimg) $s['publisher']['logo'] = ['@type' => 'ImageObject', 'url' => $pimg];

        if (!empty($cats[0])) $s['articleSection'] = $cats[0]->name;
        if (!empty($tags))    $s['keywords']       = implode(', ', $tags);

        // Enrich schema with AI summary fields if available.
        $sum_what = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        $sum_why  = trim((string) get_post_meta($pid, self::META_SUM_WHY,  true));
        $sum_key  = trim((string) get_post_meta($pid, self::META_SUM_KEY,  true));
        if ($sum_what) $s['description']              = $sum_what;
        if ($sum_why)  $s['abstract']                 = $sum_why;
        if ($sum_key)  $s['disambiguatingDescription'] = $sum_key;

        return $s;
    }

    private function schema_breadcrumbs(): ?array {
        $items = [];
        $pos   = 1;
        $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => 'Home', 'item' => home_url('/')];

        if (is_singular('post')) {
            $pid  = (int) get_queried_object_id();
            $cats = get_the_category($pid);
            if (!empty($cats[0])) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $cats[0]->name, 'item' => get_category_link($cats[0]->term_id)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($pid), 'item' => get_permalink($pid)];
        } elseif (is_page()) {
            $pid = (int) get_queried_object_id();
            foreach (array_reverse(get_post_ancestors($pid)) as $anc) {
                $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($anc), 'item' => get_permalink($anc)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => get_the_title($pid), 'item' => get_permalink($pid)];
        } elseif (is_category() || is_tag() || is_author()) {
            $items[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $this->page_title(), 'item' => $this->canonical_url()];
        } else {
            return null;
        }

        if (count($items) <= 1) return null;
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function page_title(): string {
        if (is_singular()) {
            $pid    = (int) get_queried_object_id();
            $custom = trim((string) get_post_meta($pid, self::META_TITLE, true));
            if ($custom !== '') return $custom;
            return get_the_title($pid);
        }
        if (is_front_page() || is_home()) {
            $t = trim((string) $this->opts['home_title']);
            return $t ?: (string) $this->opts['site_name'];
        }
        return wp_get_document_title();
    }

    private function clip(string $s, int $max): string {
        $s = trim((string) preg_replace('/\s+/', ' ', $s));
        if ($s === '' || mb_strlen($s) <= $max) return $s;
        return rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }

}
