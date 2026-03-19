<?php
/**
 * AI-powered category analysis, health scoring, and drift detection.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.10.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Category_Fixer {
    // =========================================================================
    // Category Fixer AJAX
    // =========================================================================

    /**
     * Verifies the AJAX nonce and checks manage_options capability; dies on failure.
     *
     * Delegates to ajax_check() from trait-ai-engine.php which verifies the same
     * cs_seo_nonce nonce and manage_options capability.
     *
     * @since 4.0.0
     * @return void
     */
    private function catfix_nonce_check(): void {
        $this->ajax_check();
    }

    /**
     * Tokenises a string into lowercase words, stripping punctuation and common stop words.
     *
     * @since 4.0.0
     * @param string $text The input string to tokenise.
     * @return array Array of lowercase word tokens with stop words removed.
     */
    private function catfix_tokens(string $text): array {
        $text = strtolower(wp_strip_all_tags($text));
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        // Strip very short stop words
        $stop = ['a','an','the','and','or','of','in','to','for','is','are','was','were',
                 'it','its','this','that','with','on','at','by','as','be','not','but',
                 'from','how','what','why','when','which','who','will','can','has','have',
                 'had','do','does','did','so','if','up','out','all','more','some'];
        return array_values(array_diff($words, $stop));
    }

    /**
     * Scores a category against a set of token bags.
     *
     * @since 4.0.0
     * @param string $cat_name The category name to score.
     * @param array  $bags     Token bags keyed by source: 'title', 'summary', 'tags', 'slug'.
     * @return int Score from 0 to 100.
     */
    private function catfix_score(string $cat_name, array $bags): int {
        $cat_tokens = $this->catfix_tokens($cat_name);
        if (empty($cat_tokens)) return 0;
        $score = 0;
        $weights = ['title' => 4, 'summary' => 3, 'tags' => 3, 'slug' => 2, 'current' => 2];
        foreach ($bags as $bag_name => $bag_tokens) {
            $w = $weights[$bag_name] ?? 1;
            $hits = count(array_intersect($cat_tokens, $bag_tokens));
            $score += $hits * $w;
        }
        // Normalise loosely: cap at 20, scale to 0-100
        return (int) min(100, round(($score / max(1, count($cat_tokens))) * 25));
    }

    /**
     * Analyses a single post and returns proposed category IDs with scores, reason, and fingerprint.
     *
     * @since 4.0.0
     * @param int $post_id The post ID to analyse.
     * @return array Associative array with keys 'proposed_ids', 'scores', 'reason', 'fingerprint', or empty on failure.
     */
    private function catfix_analyse_post(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) return [];

        // Build token bags
        $title_tokens   = $this->catfix_tokens($post->post_title);
        $slug_tokens    = $this->catfix_tokens(str_replace('-', ' ', $post->post_name));
        $sum_what       = trim((string) get_post_meta($post_id, self::META_SUM_WHAT, true));
        $summary_tokens = $this->catfix_tokens($sum_what);
        $tags           = wp_get_post_tags($post_id, ['fields' => 'names']);
        $tag_tokens     = [];
        foreach ($tags as $t) $tag_tokens = array_merge($tag_tokens, $this->catfix_tokens($t));
        $current_ids    = wp_get_post_categories($post_id, ['fields' => 'ids']);

        $bags = [
            'title'   => $title_tokens,
            'summary' => $summary_tokens,
            'tags'    => $tag_tokens,
            'slug'    => $slug_tokens,
        ];

        // Score every category
        $all_cats = get_categories(['hide_empty' => false]);
        $scored = [];
        foreach ($all_cats as $cat) {
            if (strtolower($cat->name) === 'uncategorized') continue;
            $b = $bags;
            // Continuity bonus: if already assigned, add it to current bag
            if (in_array((int) $cat->term_id, array_map('intval', $current_ids))) {
                $b['current'] = $this->catfix_tokens($cat->name);
            }
            $s = $this->catfix_score($cat->name, $b);
            if ($s > 0) $scored[(int) $cat->term_id] = $s;
        }
        arsort($scored);

        // Cap at 4, require score >= 8
        $proposed_ids = [];
        foreach ($scored as $cid => $s) {
            if ($s < 8) break;
            $proposed_ids[] = $cid;
            if (count($proposed_ids) >= 4) break;
        }

        // Confidence: top score normalised
        $top_score  = !empty($scored) ? reset($scored) : 0;
        $confidence = min(100, $top_score);
        $source     = 'local';

        // If proposed is empty, keep current (minus Uncategorized)
        if (empty($proposed_ids)) {
            $uncat_id     = (int) get_cat_ID('Uncategorized');
            $proposed_ids = array_values(array_filter(
                array_map('intval', $current_ids),
                fn($id) => $id !== $uncat_id
            ));
            $confidence   = 10;
            $source       = 'fallback';
        }

        // Fingerprint
        $fp = md5($post->post_title . $sum_what . implode(',', $tag_tokens));

        // Reason string
        $top_names = [];
        foreach (array_slice($scored, 0, 3, true) as $cid => $s) {
            $c = get_term($cid);
            if ($c && !is_wp_error($c)) $top_names[] = $c->name . '(' . $s . ')';
        }
        $reason = empty($top_names) ? 'No strong matches found' : 'Top matches: ' . implode(', ', $top_names);

        return [
            'post_id'      => $post_id,
            'proposed_ids' => $proposed_ids,
            'current_ids'  => array_map('intval', $current_ids),
            'confidence'   => $confidence,
            'reason'       => $reason,
            'source'       => $source,
            'fingerprint'  => $fp,
        ];
    }

    /**
     * AJAX handler: returns all published post IDs and the category map.
     *
     * Called first by the JS batch-scan flow so it gets the full list quickly
     * before starting the heavier per-batch analysis.
     *
     * @since 4.19.15
     * @return void
     */
    public function ajax_catfix_list_ids(): void {
        $this->catfix_nonce_check();

        $posts = [];
        $page  = 1;
        $batch = 500;
        do {
            $chunk = get_posts([
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $batch,
                'paged'               => $page++,
                'fields'              => 'ids',
                'orderby'             => 'date',
                'order'               => 'DESC',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ]);
            $posts = array_merge($posts, $chunk);
        } while (count($chunk) === $batch);

        $all_cats = get_categories(['hide_empty' => false]);
        $cat_map  = [];
        foreach ($all_cats as $c) $cat_map[(int) $c->term_id] = $c->name;

        wp_send_json(['success' => true, 'ids' => array_map('intval', $posts), 'cat_map' => $cat_map]);
    }

    /**
     * AJAX handler: loads posts with their current and proposed category assignments.
     *
     * Accepts an optional post_ids[] parameter for batched loading. If supplied,
     * only those posts are processed — used by the JS progress-scan flow.
     *
     * @since 4.10.59
     * @since 4.19.15 Added post_ids[] batch parameter.
     * @return void
     */
    public function ajax_catfix_load(): void {
        $this->catfix_nonce_check();

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $requested_ids = isset($_POST['post_ids'])
            ? array_map('intval', (array) wp_unslash($_POST['post_ids']))
            : [];
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!empty($requested_ids)) {
            $posts = $requested_ids;
        } else {
            $posts = [];
            $page  = 1;
            $batch = 500;
            do {
                $chunk = get_posts([
                    'post_type'           => 'post',
                    'post_status'         => 'publish',
                    'posts_per_page'      => $batch,
                    'paged'               => $page++,
                    'fields'              => 'ids',
                    'orderby'             => 'title',
                    'order'               => 'ASC',
                    'no_found_rows'       => true,
                    'ignore_sticky_posts' => true,
                ]);
                $posts = array_merge($posts, $chunk);
            } while (count($chunk) === $batch);
        }

        $all_cats = get_categories(['hide_empty' => false]);
        $cat_map  = [];
        foreach ($all_cats as $c) $cat_map[(int) $c->term_id] = $c->name;

        $results = [];
        foreach ($posts as $pid) {
            $pid  = (int) $pid;
            $post = get_post($pid);

            // Check cached proposal
            $cached_fp  = (string) get_post_meta($pid, 'cloudscale_categoryfix_fingerprint', true);
            $sum_what   = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
            $tags       = wp_get_post_tags($pid, ['fields' => 'names']);
            $tag_str    = implode(',', array_map(function($t){ return $this->catfix_tokens($t); }, $tags));
            // Flatten tag tokens for fingerprint
            $tag_flat   = implode(',', wp_get_post_tags($pid, ['fields' => 'names']));
            $fp_now     = md5($post->post_title . $sum_what . $tag_flat);
            $use_cache  = ($cached_fp === $fp_now) &&
                          !empty(get_post_meta($pid, 'cloudscale_categoryfix_proposed_ids', true));

            if ($use_cache) {
                $proposed_ids = array_map('intval', (array) get_post_meta($pid, 'cloudscale_categoryfix_proposed_ids', true));
                $current_ids  = array_map('intval', wp_get_post_categories($pid, ['fields' => 'ids']));
                $confidence   = (int) get_post_meta($pid, 'cloudscale_categoryfix_confidence', true);
                $reason       = (string) get_post_meta($pid, 'cloudscale_categoryfix_reason', true);
                $source       = 'cache';
            } else {
                $data         = $this->catfix_analyse_post($pid);
                $proposed_ids = $data['proposed_ids'];
                $current_ids  = $data['current_ids'];
                $confidence   = $data['confidence'];
                $reason       = $data['reason'];
                $source       = $data['source'];
                $fp_now       = $data['fingerprint'];
                // Store
                update_post_meta($pid, 'cloudscale_categoryfix_proposed_ids',  $proposed_ids);
                update_post_meta($pid, 'cloudscale_categoryfix_current_ids',    $current_ids);
                update_post_meta($pid, 'cloudscale_categoryfix_confidence',     $confidence);
                update_post_meta($pid, 'cloudscale_categoryfix_reason',         $reason);
                update_post_meta($pid, 'cloudscale_categoryfix_source',         $source);
                update_post_meta($pid, 'cloudscale_categoryfix_generated_at',   current_time('mysql'));
                update_post_meta($pid, 'cloudscale_categoryfix_fingerprint',    $fp_now);
                update_post_meta($pid, 'cloudscale_categoryfix_status',         'pending');
            }

            $current_names  = array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $current_ids);
            $proposed_names = array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $proposed_ids);
            $add_ids        = array_values(array_diff($proposed_ids, $current_ids));
            $remove_ids     = array_values(array_diff($current_ids, $proposed_ids));
            $unchanged_ids  = array_values(array_intersect($current_ids, $proposed_ids));

            $current_sorted  = $current_ids;  sort($current_sorted);
            $proposed_sorted = $proposed_ids; sort($proposed_sorted);
            $truly_changed   = ($current_sorted !== $proposed_sorted);

            $results[] = [
                'post_id'         => $pid,
                'title'           => get_the_title($pid),
                'current_ids'     => $current_ids,
                'current_names'   => $current_names,
                'proposed_ids'    => $proposed_ids,
                'proposed_names'  => $proposed_names,
                'add_ids'         => $add_ids,
                'add_names'       => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $add_ids),
                'remove_ids'      => $remove_ids,
                'remove_names'    => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $remove_ids),
                'unchanged_names' => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $unchanged_ids),
                'confidence'      => $confidence,
                'reason'          => $reason,
                'source'          => $source,
                'changed'         => $truly_changed,
                'status'          => (string) get_post_meta($pid, 'cloudscale_categoryfix_status', true),
            ];
        }

        wp_send_json(['success' => true, 'posts' => $results, 'cat_map' => $cat_map]);
    }

    /**
     * AJAX handler: applies the proposed category changes for a single post.
     *
     * @since 4.10.59
     * @return void
     */
    public function ajax_catfix_apply(): void {
        $this->catfix_nonce_check();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $pid          = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $proposed_raw = isset($_POST['proposed_ids']) ? (array) wp_unslash($_POST['proposed_ids']) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked via catfix_nonce_check(); array is sanitized via array_map intval below
        if (!$pid) wp_send_json(['success' => false, 'error' => 'No post_id']);
        $ids = array_map('intval', $proposed_raw);
        wp_set_post_categories($pid, $ids);
        update_post_meta($pid, 'cloudscale_categoryfix_status', 'applied');
        wp_send_json(['success' => true]);
    }

    /**
     * AJAX handler: marks the proposed category change for a post as skipped.
     *
     * @since 4.10.59
     * @return void
     */
    public function ajax_catfix_skip(): void {
        $this->catfix_nonce_check();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $pid = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$pid) wp_send_json(['success' => false, 'error' => 'No post_id']);
        update_post_meta($pid, 'cloudscale_categoryfix_status', 'skipped');
        wp_send_json(['success' => true]);
    }

    /**
     * AJAX handler: applies proposed category changes for all selected posts in bulk.
     *
     * @since 4.10.59
     * @return void
     */
    public function ajax_catfix_bulk_apply(): void {
        $this->catfix_nonce_check();
        $items_raw = isset($_POST['items']) ? (array) wp_unslash($_POST['items']) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked via catfix_nonce_check(); array items are sanitized in loop below
        $applied   = 0;
        foreach ($items_raw as $item) {
            $pid  = isset($item['post_id'])     ? absint($item['post_id']) : 0;
            $ids  = isset($item['proposed_ids']) ? array_map('intval', (array) $item['proposed_ids']) : [];
            if (!$pid || empty($ids)) continue;
            wp_set_post_categories($pid, $ids);
            update_post_meta($pid, 'cloudscale_categoryfix_status', 'applied');
            $applied++;
        }
        wp_send_json(['success' => true, 'applied' => $applied]);
    }

    // ajax_catfix_analyse: re-analyse a single post (force)
    /**
     * AJAX handler: runs the local keyword scorer to propose categories for all posts.
     *
     * @since 4.10.59
     * @return void
     */
    public function ajax_catfix_analyse(): void {
        $this->catfix_nonce_check();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $pid = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$pid) wp_send_json(['success' => false, 'error' => 'No post_id']);
        $data = $this->catfix_analyse_post($pid);
        update_post_meta($pid, 'cloudscale_categoryfix_proposed_ids',  $data['proposed_ids']);
        update_post_meta($pid, 'cloudscale_categoryfix_current_ids',    $data['current_ids']);
        update_post_meta($pid, 'cloudscale_categoryfix_confidence',     $data['confidence']);
        update_post_meta($pid, 'cloudscale_categoryfix_reason',         $data['reason']);
        update_post_meta($pid, 'cloudscale_categoryfix_source',         $data['source']);
        update_post_meta($pid, 'cloudscale_categoryfix_generated_at',   current_time('mysql'));
        update_post_meta($pid, 'cloudscale_categoryfix_fingerprint',    $data['fingerprint']);
        update_post_meta($pid, 'cloudscale_categoryfix_status',         'pending');
        wp_send_json(['success' => true, 'data' => $data]);
    }

    /**
     * AJAX handler: uses AI to propose category assignments for a single post.
     *
     * @since 4.10.65
     * @return void
     */
    public function ajax_catfix_ai_one(): void {
        $this->catfix_nonce_check();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $pid = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$pid) wp_send_json(['success' => false, 'error' => 'No post_id']);

        $post = get_post($pid);
        if (!$post) wp_send_json(['success' => false, 'error' => 'Post not found']);

        // ── API credentials ───────────────────────────────────────────────────
        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string) $this->ai_opts['anthropic_key']);
        $model    = trim((string) $this->ai_opts['model']) ?: 'claude-sonnet-4-6';
        if (!$key) wp_send_json(['success' => false, 'error' => 'No API key configured']);

        // ── Build category list for the prompt ───────────────────────────────
        $all_cats = get_categories(['hide_empty' => false]);
        $cat_map  = [];
        foreach ($all_cats as $c) {
            if (strtolower($c->name) === 'uncategorized') continue;
            $cat_map[(int) $c->term_id] = $c->name;
        }
        $cat_list_str = implode(', ', array_map(
            fn($id, $name) => "{$id}:{$name}",
            array_keys($cat_map), array_values($cat_map)
        ));

        // ── Post context ─────────────────────────────────────────────────────
        $title      = get_the_title($pid);
        $slug       = $post->post_name;
        $sum_what   = trim((string) get_post_meta($pid, self::META_SUM_WHAT, true));
        $tags       = wp_get_post_tags($pid, ['fields' => 'names']);
        $tag_str    = implode(', ', $tags);
        $current_ids = wp_get_post_categories($pid, ['fields' => 'ids']);
        $current_names = implode(', ', array_filter(array_map(
            fn($id) => $cat_map[(int)$id] ?? null, $current_ids
        )));

        // ── Prompt ───────────────────────────────────────────────────────────
        $system = 'You are a WordPress category assignment expert. '
            . 'You will be given a blog post and a list of available categories. '
            . 'Respond ONLY with a valid JSON array of integer category IDs, e.g. [12,7,45]. '
            . 'Rules: pick 1 to 4 categories maximum. Only use IDs from the provided list. '
            . 'Never invent new IDs. Never return Uncategorized. '
            . 'Return only the JSON array with no other text, no markdown, no explanation.';

        $user_msg = "AVAILABLE CATEGORIES (id:name):\n{$cat_list_str}\n\n"
            . "POST TITLE: {$title}\n"
            . "POST SLUG: {$slug}\n"
            . ( $sum_what   ? "SUMMARY: {$sum_what}\n"       : '' )
            . ( $tag_str    ? "TAGS: {$tag_str}\n"           : '' )
            . ( $current_names ? "CURRENT CATEGORIES: {$current_names}\n" : '' )
            . "\nReturn the best category IDs as a JSON array.";

        try {
            $raw = $this->call_claude($key, $model, $system, $user_msg, null, 256);
            // Strip any accidental markdown fences
            $raw = preg_replace('/^```[a-z]*\s*/i', '', trim($raw));
            $raw = preg_replace('/\s*```$/', '', $raw);
            $ids = json_decode($raw, true);
            if (!is_array($ids)) throw new \RuntimeException('AI returned non-array: ' . $raw); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

            // Sanitise: only keep valid IDs from our map, cap at 4
            $valid_ids = array_values(array_slice(
                array_filter(array_map('intval', $ids), fn($id) => isset($cat_map[$id])),
                0, 4
            ));
            if (empty($valid_ids)) throw new \RuntimeException('AI returned no valid category IDs'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

            // Store result
            $current_ids_int = array_map('intval', $current_ids);
            $fp = md5($post->post_title . $sum_what . $tag_str);
            update_post_meta($pid, 'cloudscale_categoryfix_proposed_ids', $valid_ids);
            update_post_meta($pid, 'cloudscale_categoryfix_confidence',   90);
            update_post_meta($pid, 'cloudscale_categoryfix_reason',       'AI analysis');
            update_post_meta($pid, 'cloudscale_categoryfix_source',       'ai');
            update_post_meta($pid, 'cloudscale_categoryfix_generated_at', current_time('mysql'));
            update_post_meta($pid, 'cloudscale_categoryfix_fingerprint',  $fp);
            update_post_meta($pid, 'cloudscale_categoryfix_status',       'pending');

            $add_ids      = array_values(array_diff($valid_ids, $current_ids_int));
            $remove_ids   = array_values(array_diff($current_ids_int, $valid_ids));
            $unchanged_ids = array_values(array_intersect($current_ids_int, $valid_ids));

            wp_send_json([
                'success'         => true,
                'post_id'         => $pid,
                'proposed_ids'    => $valid_ids,
                'proposed_names'  => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $valid_ids),
                'current_ids'     => $current_ids_int,
                'current_names'   => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $current_ids_int),
                'add_ids'         => $add_ids,
                'add_names'       => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $add_ids),
                'remove_ids'      => $remove_ids,
                'remove_names'    => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $remove_ids),
                'unchanged_names' => array_map(fn($id) => $cat_map[$id] ?? 'Unknown', $unchanged_ids),
                'confidence'      => 90,
                'source'          => 'ai',
                'changed'         => (function() use ($current_ids_int, $valid_ids) { $a = $current_ids_int; $b = $valid_ids; sort($a); sort($b); return $a !== $b; })(),
            ]);
        } catch (\Exception $e) {
            wp_send_json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // Category Health
    // =========================================================================

    /**
     * AJAX handler: returns a lightweight list of all categories (no post queries).
     *
     * Used by the JS health scanner to get the category list before processing
     * each category individually via ajax_catfix_health_cat().
     *
     * @since 4.19.37
     * @return void
     */
    public function ajax_catfix_health_list(): void {
        $this->catfix_nonce_check();

        $cats = get_categories(['hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC']);
        wp_send_json(['success' => true, 'categories' => array_map(fn($c) => [
            'id'    => (int) $c->term_id,
            'name'  => $c->name,
            'slug'  => $c->slug,
            'count' => (int) $c->count,
        ], $cats)]);
    }

    /**
     * AJAX handler: returns health data for a single category.
     *
     * Accepts `cat_id` (int). Used by the JS scanner to process one category at a
     * time so the UI can show per-category progress and identify slow/stalled queries.
     *
     * @since 4.19.37
     * @return void
     */
    public function ajax_catfix_health_cat(): void {
        $this->catfix_nonce_check();

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $cid = absint(wp_unslash($_POST['cat_id'] ?? 0));
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!$cid) {
            wp_send_json(['success' => false, 'error' => 'Missing cat_id.']);
            return;
        }

        $cat = get_category($cid);
        if (!$cat || is_wp_error($cat)) {
            wp_send_json(['success' => false, 'error' => 'Category not found.']);
            return;
        }

        $count            = (int) $cat->count;
        $is_uncategorized = strtolower($cat->slug) === 'uncategorized';

        $posts = get_posts([
            'category'       => $cid,
            'posts_per_page' => 50,
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $post_list = array_map(fn($p) => [
            'id'    => $p->ID,
            'title' => get_the_title($p->ID),
        ], $posts);

        $is_new = false;
        if (!$is_uncategorized && $count >= 1 && $count <= 3 && !empty($posts)) {
            $days_old = (time() - strtotime($posts[0]->post_date)) / DAY_IN_SECONDS;
            if ($days_old <= 180) $is_new = true;
        }

        if ($is_uncategorized)  $grade = 'uncategorized';
        elseif ($count >= 10)   $grade = 'strong';
        elseif ($count >= 4)    $grade = 'moderate';
        elseif ($is_new)        $grade = 'new';
        elseif ($count >= 2)    $grade = 'weak';
        else                    $grade = 'empty';

        wp_send_json(['success' => true, 'cat' => [
            'id'       => $cid,
            'name'     => $cat->name,
            'slug'     => $cat->slug,
            'count'    => $count,
            'grade'    => $grade,
            'edit_url' => admin_url('edit.php?post_type=post&category_name=' . $cat->slug),
            'posts'    => $post_list,
        ]]);
    }

    /**
     * AJAX handler: returns category health statistics (post counts per category).
     *
     * @since 4.10.65
     * @deprecated 4.19.37 JS now uses ajax_catfix_health_list + ajax_catfix_health_cat for progress.
     * @return void
     */
    public function ajax_catfix_health(): void {
        $this->catfix_nonce_check();

        $all_cats = get_categories(['hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC']);
        $results  = [];

        foreach ($all_cats as $cat) {
            $cid   = (int) $cat->term_id;
            $count = (int) $cat->count;
            $is_uncategorized = strtolower($cat->slug) === 'uncategorized';

            // Get posts for this category (max 50) — needed before grading
            $posts = get_posts([
                'category'       => $cid,
                'posts_per_page' => 50,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $post_list = array_map(fn($p) => [
                'id'    => $p->ID,
                'title' => get_the_title($p->ID),
            ], $posts);

            // Detect "New": low count but most recent post is within 90 days
            $is_new = false;
            if (!$is_uncategorized && $count >= 1 && $count <= 3 && !empty($posts)) {
                $newest_date = strtotime($posts[0]->post_date);
                $days_old    = (time() - $newest_date) / DAY_IN_SECONDS;
                if ($days_old <= 180) {
                    $is_new = true;
                }
            }

            // Health grade
            if ($is_uncategorized) {
                $grade = 'uncategorized';
            } elseif ($count >= 10) {
                $grade = 'strong';
            } elseif ($count >= 4) {
                $grade = 'moderate';
            } elseif ($is_new) {
                $grade = 'new';
            } elseif ($count >= 2) {
                $grade = 'weak';
            } else {
                $grade = 'empty';
            }

            $results[] = [
                'id'         => $cid,
                'name'       => $cat->name,
                'slug'       => $cat->slug,
                'count'      => $count,
                'grade'      => $grade,
                'edit_url'   => admin_url('edit.php?post_type=post&category_name=' . $cat->slug),
                'posts'      => $post_list,
            ];
        }

        // Sort: uncategorized last, then by grade weight desc, then count desc
        $grade_order = ['strong' => 0, 'moderate' => 1, 'new' => 2, 'weak' => 3, 'empty' => 4, 'uncategorized' => 5];
        usort($results, function($a, $b) use ($grade_order) {
            $ga = $grade_order[$a['grade']] ?? 5;
            $gb = $grade_order[$b['grade']] ?? 5;
            if ($ga !== $gb) return $ga - $gb;
            return $b['count'] - $a['count'];
        });

        wp_send_json(['success' => true, 'categories' => $results]);
    }

    // =========================================================================
    // Category Drift Detection
    // =========================================================================

    /**
     * AJAX handler: runs AI taxonomy drift analysis and returns suggested category consolidations.
     *
     * @since 4.10.65
     * @return void
     */
    public function ajax_catfix_drift(): void {
        $this->catfix_nonce_check();

        // Require configured AI provider key
        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string)($this->ai_opts['anthropic_key'] ?? ''));
        if (!$key) {
            wp_send_json(['success' => false, 'error' => 'No AI API key configured. Add your key in the AI Settings tab.']);
            return;
        }

        $model = trim((string)($this->ai_opts['model'] ?? ''))
            ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001');

        // ── Collect categories and their post titles ──────────────────────────
        $all_cats = get_categories(['hide_empty' => false]);
        $cat_map  = [];
        foreach ($all_cats as $c) {
            if (strtolower($c->slug) === 'uncategorized') continue;
            $cat_map[(int) $c->term_id] = [
                'name'     => $c->name,
                'count'    => (int) $c->count,
                'edit_url' => admin_url('edit.php?post_type=post&category_name=' . $c->slug),
            ];
        }

        // For each category fetch up to 15 post titles (published only)
        $cat_payload = [];
        $total_posts = 0;
        foreach ($cat_map as $cid => $cdata) {
            if ($cdata['count'] < 2) continue; // skip empty / single-post categories
            // Fetch up to 15 titles for the AI prompt (keep prompt size manageable)
            $ai_post_ids = get_posts([
                'category'       => $cid,
                'posts_per_page' => 15,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
            ]);
            // Prime post object and term caches in bulk to avoid N+1 per-post lookups below.
            _prime_post_caches($ai_post_ids, true, false);
            // Include other categories each post belongs to so AI doesn't suggest moves already covered
            $titles = array_map(function($pid) use ($cid) {
                $title      = get_the_title($pid);
                $post_cats  = get_the_category($pid);
                $other_cats = array_filter($post_cats, fn($cat) => (int)$cat->term_id !== (int)$cid);
                $other_names = array_map(fn($cat) => $cat->name, $other_cats);
                return empty($other_names)
                    ? $title
                    : $title . ' [also in: ' . implode(', ', $other_names) . ']';
            }, $ai_post_ids);

            // Fetch ALL posts for the user-facing list
            $all_post_ids = get_posts([
                'category'            => $cid,
                'posts_per_page'      => -1,
                'post_status'         => 'publish',
                'orderby'             => 'date',
                'order'               => 'DESC',
                'fields'              => 'ids',
                'no_found_rows'       => true,
                'ignore_sticky_posts' => true,
            ]);
            // Prime post object cache so get_the_title() hits cache not DB for each ID.
            _prime_post_caches($all_post_ids, false, false);
            $post_pairs = array_map(function($pid) {
                return ['id' => $pid, 'title' => get_the_title($pid)];
            }, $all_post_ids);

            $cat_payload[] = [
                'id'     => $cid,
                'name'   => $cdata['name'],
                'count'  => $cdata['count'],
                'titles' => $titles,
                'posts'  => $post_pairs,
            ];
            $total_posts += $cdata['count'];
        }

        if (empty($cat_payload)) {
            wp_send_json(['success' => true, 'drift' => [], 'total_posts' => 0]);
            return;
        }

        // ── Build AI prompt ───────────────────────────────────────────────────
        $all_cat_names = implode(', ', array_column($cat_payload, 'name'));

        $cat_list = '';
        foreach ($cat_payload as $c) {
            $sample   = implode("\n    - ", array_slice($c['titles'], 0, 15));
            $cat_list .= "\n\nCategory: {$c['name']} ({$c['count']} posts total, sample titles below)\n    - {$sample}";
        }

        $system = 'You are a content taxonomy analyst. You assess whether WordPress blog categories '
            . 'are semantically coherent or are being used as catch-alls for unrelated topics. '
            . 'Post titles may include an "[also in: X, Y]" annotation showing their other existing categories. '
            . 'A post already assigned to an appropriate category does NOT need to be moved there — it is already covered. '
            . 'Only suggest moving a post to a category it is NOT already in. '
            . 'NEVER suggest moving posts to the "Uncategorized" category. '
            . 'You respond ONLY with valid JSON and nothing else. No markdown fences, no explanation.';
        $user_msg = 'Analyse the following WordPress blog categories. Each entry shows the category name, total post count, and a sample of post titles.' . "\n\n"
            . 'For each category, determine whether the posts form a coherent topic or whether the category is being used inconsistently as a catch-all.' . "\n"
            . $cat_list . "\n\n"
            . 'The full list of existing categories on this blog is: ' . $all_cat_names . "\n\n"
            . 'Return ONLY a JSON array. Include ONLY categories that are drifting or catch-all — omit coherent and broad-but-valid ones entirely.' . "\n\n"
            . 'Each object in the array must have exactly these fields:' . "\n"
            . '- "category": string — the category name being flagged' . "\n"
            . '- "verdict": one of exactly: "drifting" or "catch-all"' . "\n"
            . '- "confidence": one of exactly: "high", "medium", or "low"' . "\n"
            . '- "reason": string — one or two sentences explaining why this category is problematic' . "\n"
            . '- "moves": array of move groups. Each group is an object with:' . "\n"
            . '    - "to": string — exact name of target category' . "\n"
            . '    - "because": string — one short sentence why these posts belong there' . "\n"
            . '    - "titles": array of 2 to 4 post title strings from the sample' . "\n"
            . '- "action": string — what to do with the category after reassignment. One of: "delete", "rename", or "keep".' . "\n\n"
            . 'Do NOT use a freeform suggestion string. Express all recommendations through the moves array and action field.' . "\n\n"
            . 'If no categories are drifting or catch-all, return an empty array: []';

        // ── Call AI ───────────────────────────────────────────────────────────
        try {
            $raw = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 3000);
        } catch (\Throwable $e) {
            wp_send_json(['success' => false, 'error' => 'AI call failed: ' . $e->getMessage()]);
            return;
        }

        // ── Parse response ────────────────────────────────────────────────────
        // Strip accidental markdown fences if present
        $json_str = trim((string) preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/i', '', trim($raw))));
        $parsed   = json_decode($json_str, true);

        if (!is_array($parsed)) {
            wp_send_json(['success' => false, 'error' => 'AI returned unexpected format. Raw: ' . substr($raw, 0, 200)]);
            return;
        }

        // Enrich each result with edit_url from our cat_map
        $drift = [];
        foreach ($parsed as $item) {
            if (empty($item['category']) || empty($item['verdict'])) continue;
            // Find matching category ID by name
            $matched_cid = null;
            foreach ($cat_map as $cid => $cdata) {
                if (strtolower($cdata['name']) === strtolower($item['category'])) {
                    $matched_cid = $cid;
                    break;
                }
            }
            // Find posts for this category from cat_payload
            $cat_posts = [];
            foreach ($cat_payload as $cp) {
                if ($cp['id'] === $matched_cid) {
                    $cat_posts = $cp['posts'] ?? [];
                    break;
                }
            }
            $drift[] = [
                'cat_id'     => $matched_cid,
                'cat_name'   => $item['category'],
                'verdict'    => $item['verdict']    ?? 'drifting',
                'confidence' => $item['confidence'] ?? 'medium',
                'reason'     => $item['reason']     ?? '',
                'moves'      => $item['moves']      ?? [],
                'action'     => $item['action']     ?? '',
                'post_count' => $cat_map[$matched_cid]['count'] ?? 0,
                'edit_url'   => $matched_cid ? $cat_map[$matched_cid]['edit_url'] : '',
                'posts'      => $cat_posts,
            ];
        }

        // Sort: catch-all first, then drifting; within each group by confidence (high first)
        $verdict_order    = ['catch-all' => 0, 'drifting' => 1];
        $confidence_order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($drift, function($a, $b) use ($verdict_order, $confidence_order) {
            $vdiff = ($verdict_order[$a['verdict']] ?? 9) - ($verdict_order[$b['verdict']] ?? 9);
            if ($vdiff !== 0) return $vdiff;
            return ($confidence_order[$a['confidence']] ?? 9) - ($confidence_order[$b['confidence']] ?? 9);
        });

        // ── Save to cache ─────────────────────────────────────────────────────
        // Hash only on taxonomy structure (id + slug), not post counts.
        // Post count changes do not affect drift analysis — only do so if categories
        // are added, removed, or renamed.
        $cache_hash = md5(serialize(array_map(fn($c) => [$c['id'], $c['slug']], $cat_payload)));
        update_option('cs_seo_drift_cache', [
            'hash'        => $cache_hash,
            'timestamp'   => time(),
            'drift'       => $drift,
            'total_posts' => $total_posts,
        ], false);

        wp_send_json(['success' => true, 'drift' => $drift, 'total_posts' => $total_posts]);
    }

    // ── Drift cache retrieval ─────────────────────────────────────────────────
    /**
     * AJAX handler: returns cached taxonomy drift analysis results.
     *
     * @since 4.10.65
     * @return void
     */
    public function ajax_catfix_drift_cache_get(): void {
        $this->catfix_nonce_check();
        $cache = get_option('cs_seo_drift_cache', null);
        if (!$cache || empty($cache['drift'])) {
            wp_send_json(['success' => false, 'error' => 'No cache found.']);
            return;
        }
        // Validate hash on taxonomy structure only (id + slug), not post counts
        $all_cats = get_categories(['hide_empty' => false]);
        $cat_snap = [];
        foreach ($all_cats as $c) {
            if (strtolower($c->slug) === 'uncategorized') continue;
            $cat_snap[] = ['id' => (int)$c->term_id, 'slug' => $c->slug];
        }
        $current_hash = md5(serialize($cat_snap));
        $is_stale = ($current_hash !== $cache['hash']);
        $cached_at = human_time_diff($cache['timestamp'], time()) . ' ago';
        if ($is_stale) {
            wp_send_json([
                'success'   => false,
                'stale'     => true,
                'error'     => 'stale',
                'drift'     => $cache['drift'],
                'total_posts' => $cache['total_posts'],
                'cached_at' => $cached_at,
            ]);
            return;
        }
        wp_send_json([
            'success'     => true,
            'drift'       => $cache['drift'],
            'total_posts' => $cache['total_posts'],
            'cached'      => true,
            'cached_at'   => $cached_at,
        ]);
    }

    // ── Drift analyse remaining posts for one category ────────────────────────
    /**
     * AJAX handler: runs AI analysis on posts not yet assigned to any drift move group.
     *
     * @since 4.10.65
     * @return void
     */
    public function ajax_catfix_drift_analyse_remaining(): void {
        $this->catfix_nonce_check();

        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked via catfix_nonce_check(); cast to int is sufficient sanitization
        $cat_id   = (int) (wp_unslash($_POST['cat_id']   ?? 0));
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $cat_name = sanitize_text_field(wp_unslash($_POST['cat_name'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$cat_id || !$cat_name) {
            wp_send_json(['success' => false, 'error' => 'Missing category.']);
            return;
        }

        // Fetch all posts for this category with their other categories
        $post_ids = get_posts([
            'category'            => $cat_id,
            'posts_per_page'      => -1,
            'post_status'         => 'publish',
            'orderby'             => 'date',
            'order'               => 'DESC',
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);
        // Prime post object and term caches in bulk to avoid N+1 lookups in the loop.
        _prime_post_caches($post_ids, true, false);
        $titles_with_cats = array_map(function($pid) use ($cat_id) {
            $title      = get_the_title($pid);
            $post_cats  = get_the_category($pid);
            $other_cats = array_filter($post_cats, fn($cat) => (int)$cat->term_id !== $cat_id);
            $other_names = array_map(fn($cat) => $cat->name, $other_cats);
            return empty($other_names) ? $title : $title . ' [also in: ' . implode(', ', $other_names) . ']';
        }, $post_ids);

        // Already-assigned titles passed from JS
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked via catfix_nonce_check(); array items sanitized via array_map sanitize_text_field
        $already_assigned = array_map('sanitize_text_field', (array)json_decode(wp_unslash($_POST['assigned_titles'] ?? '[]'), true));
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $unanalysed = array_filter($titles_with_cats, function($t) use ($already_assigned) {
            $bare = strtolower(preg_replace('/\s*\[also in:.*?\]/', '', $t));
            foreach ($already_assigned as $a) {
                if (str_contains(strtolower($a), $bare) || str_contains($bare, strtolower($a))) return false;
            }
            return true;
        });

        if (empty($unanalysed)) {
            wp_send_json(['success' => true, 'moves' => [], 'message' => 'All posts already assigned.']);
            return;
        }

        $provider = $this->ai_opts['ai_provider'] ?? 'anthropic';
        $key      = $provider === 'gemini'
            ? trim((string)($this->ai_opts['gemini_key'] ?? ''))
            : trim((string)($this->ai_opts['anthropic_key'] ?? ''));
        if (!$key) {
            wp_send_json(['success' => false, 'error' => 'No AI API key configured.']);
            return;
        }
        $model = trim((string)($this->ai_opts['model'] ?? ''))
            ?: ($provider === 'gemini' ? 'gemini-2.0-flash' : 'claude-haiku-4-5-20251001');

        $all_cat_names = implode(', ', array_column(get_categories(['hide_empty' => false]), 'name'));
        $title_list    = implode("\n- ", array_values($unanalysed));

        $system = 'You are a content taxonomy analyst. Post titles may include "[also in: X]" annotations. '
            . 'Only suggest moving a post to a category it is NOT already in. '
            . 'NEVER suggest moving posts to the "Uncategorized" category. '
            . 'You respond ONLY with valid JSON and nothing else. No markdown fences, no explanation.';
        $user_msg = 'The following posts are in the \'' . $cat_name . '\' category and have not yet been assigned to a move group.' . "\n"
            . 'For each post, determine the best existing category it should be moved to from this list: ' . $all_cat_names . "\n\n"
            . 'Posts to classify:' . "\n"
            . '- ' . $title_list . "\n\n"
            . 'Return ONLY a JSON array of move groups. Each object must have:' . "\n"
            . '- "to": string — exact name of the target category' . "\n"
            . '- "because": string — one sentence why these posts belong there' . "\n"
            . '- "titles": array of post title strings (without the [also in:] annotation)' . "\n\n"
            . 'Group posts by target category. If a post already belongs to the right category, omit it.' . "\n"
            . 'If no moves are needed, return: []';

        try {
            $raw = $this->dispatch_ai($provider, $key, $model, $system, $user_msg, null, 2000);
        } catch (\Throwable $e) {
            wp_send_json(['success' => false, 'error' => 'AI call failed: ' . $e->getMessage()]);
            return;
        }

        $clean = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $clean = preg_replace('/```\s*$/', '', $clean);
        $moves = json_decode(trim($clean), true);
        if (!is_array($moves)) {
            wp_send_json(['success' => false, 'error' => 'AI returned invalid JSON. Raw: ' . substr($raw, 0, 200)]);
            return;
        }

        // Normalise a title string for comparison: decode HTML entities, convert
        // curly/smart punctuation to ASCII equivalents, then lowercase and trim.
        $normalise = static function( string $t ): string {
            $t = html_entity_decode( $t, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
            $t = str_replace(
                [ "\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", // left/right single quotes
                  "\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", // left/right double quotes
                  "\u{2013}", "\u{2014}", "\u{2015}",             // en-dash, em-dash, horizontal bar
                  "\u{2026}", "\u{00A0}" ],                       // ellipsis, non-breaking space
                [ "'", "'", "'", "'",
                  '"', '"', '"', '"',
                  '-', '-', '-',
                  '...', ' ' ],
                $t
            );
            return strtolower( trim( $t ) );
        };

        // Build a title→ID map so we can resolve AI-returned titles to post IDs.
        $title_to_id = [];
        foreach ($post_ids as $pid) {
            $bare = $normalise( get_the_title( $pid ) );
            $title_to_id[ $bare ] = $pid;
        }

        $debug_unmatched = [];
        foreach ( $moves as &$move ) {
            $move['post_ids'] = [];
            foreach ( (array) ( $move['titles'] ?? [] ) as $mt ) {
                // Strip any [also in:] annotation the AI may have left in the title.
                $mt_clean = preg_replace( '/\s*\[also in:.*?\]/i', '', $mt );
                $mt_bare  = $normalise( $mt_clean );
                if ( isset( $title_to_id[ $mt_bare ] ) ) {
                    $move['post_ids'][] = $title_to_id[ $mt_bare ];
                    continue;
                }
                // Substring fallback.
                $found = false;
                foreach ( $title_to_id as $stored => $pid ) {
                    if ( str_contains( $stored, $mt_bare ) || str_contains( $mt_bare, $stored ) ) {
                        $move['post_ids'][] = $pid;
                        $found = true;
                        break;
                    }
                }
                if ( ! $found ) {
                    $debug_unmatched[] = $mt;
                }
            }
            $move['post_ids'] = array_values( array_unique( $move['post_ids'] ) );
        }
        unset( $move );

        // Merge into cache — store new moves AND track every post ID that went through
        // this analysis so the JS can exclude them from the "unanalysed" list on reload,
        // even when title-to-ID resolution failed and post_ids is empty on a move group.
        $cache = get_option('cs_seo_drift_cache', []);
        if (!empty($cache['drift'])) {
            foreach ($cache['drift'] as &$entry) {
                if ((int)$entry['cat_id'] !== $cat_id) continue;
                $entry['moves']             = array_merge($entry['moves'] ?? [], $moves);
                $entry['analysed_post_ids'] = array_values(array_unique(array_merge(
                    array_map('intval', $entry['analysed_post_ids'] ?? []),
                    array_map('intval', $post_ids)
                )));
                break;
            }
            unset($entry);
            update_option('cs_seo_drift_cache', $cache, false);
        }

        wp_send_json(['success' => true, 'moves' => $moves, 'analysed_post_ids' => array_map('intval', $post_ids)]);
    }

    /**
     * AJAX handler: moves a single post from its current (drift-flagged) category to the AI-suggested target.
     *
     * Accepts `post_id` (int), `from_cat_id` (int), and `to_cat_name` (string).
     * Resolves the target category by name; creates it if it does not yet exist.
     *
     * @since 4.19.37
     * @return void
     */
    public function ajax_catfix_drift_move(): void {
        $this->catfix_nonce_check();

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce checked via catfix_nonce_check()
        $pid         = isset($_POST['post_id'])     ? absint(wp_unslash($_POST['post_id']))                  : 0;
        $from_cat_id = isset($_POST['from_cat_id']) ? absint(wp_unslash($_POST['from_cat_id']))              : 0;
        $to_cat_name = isset($_POST['to_cat_name']) ? sanitize_text_field(wp_unslash($_POST['to_cat_name'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!$pid) {
            wp_send_json(['success' => false, 'error' => 'Missing post_id.']);
            return;
        }
        if (!$to_cat_name) {
            wp_send_json(['success' => false, 'error' => 'Missing target category name.']);
            return;
        }
        if (strtolower($to_cat_name) === 'uncategorized') {
            wp_send_json(['success' => false, 'error' => 'Cannot move posts to Uncategorized.']);
            return;
        }

        // Resolve target category by name, creating it if it doesn't exist yet.
        $target = get_term_by('name', $to_cat_name, 'category');
        if ($target && !is_wp_error($target)) {
            $to_cat_id = (int) $target->term_id;
        } else {
            $inserted = wp_insert_term($to_cat_name, 'category');
            if (is_wp_error($inserted)) {
                wp_send_json(['success' => false, 'error' => 'Could not create category: ' . $inserted->get_error_message()]);
                return;
            }
            $to_cat_id = (int) $inserted['term_id'];
        }

        // Remove post from the drift-flagged category, add it to the target.
        $current_cats = wp_get_post_categories($pid, ['fields' => 'ids']);
        if ($from_cat_id) {
            $current_cats = array_filter($current_cats, fn($id) => (int) $id !== $from_cat_id);
        }
        $current_cats[] = $to_cat_id;
        wp_set_post_categories($pid, array_unique(array_map('intval', $current_cats)));

        // Remove the moved post from the drift cache so it doesn't reappear on reload.
        $cache = get_option('cs_seo_drift_cache', []);
        if (!empty($cache['drift'])) {
            foreach ($cache['drift'] as &$entry) {
                if ((int) ($entry['cat_id'] ?? 0) !== $from_cat_id) continue;
                // Remove from the top-level posts list.
                $entry['posts'] = array_values(array_filter(
                    $entry['posts'] ?? [],
                    fn($p) => (int) $p['id'] !== $pid
                ));
                // Remove from each move group's post_ids.
                foreach ($entry['moves'] as &$move) {
                    $move['post_ids'] = array_values(array_filter(
                        $move['post_ids'] ?? [],
                        fn($id) => (int) $id !== $pid
                    ));
                }
                unset($move);
                break;
            }
            unset($entry);
            update_option('cs_seo_drift_cache', $cache, false);
        }

        wp_send_json(['success' => true, 'to_cat_id' => $to_cat_id]);
    }
}
