<?php
/**
 * HTTPS fixer — scans the database for http:// assets and rewrites them to https://.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_HTTPS_Fixer {
    /**
     * AJAX handler: scans the database for HTTP references and returns a domain-grouped summary.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_https_scan(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        try {
            global $wpdb;

            $comment_tables  = [$wpdb->comments, $wpdb->commentmeta];
            // Core URL options — fixing these in the DB is overridden by wp-config.php constants
            $core_url_option_names = ['siteurl', 'home'];

            $tables = [
                $wpdb->posts       => ['post_content', 'post_excerpt', 'guid'],
                $wpdb->postmeta    => ['meta_value'],
                $wpdb->options     => ['option_value'],
                $wpdb->comments    => ['comment_content', 'comment_author_url'],
                $wpdb->commentmeta => ['meta_value'],
            ];

            // $by_domain[domain] = list of {table, column, url} entries
            // $domain_tables[domain] = set of distinct tables the domain appears in
            // $domain_option_names[domain] = list of wp_options.option_name values where this domain appears
            $by_domain          = [];
            $domain_tables      = [];
            $domain_option_names = [];
            $counts             = [];
            $total              = 0;

            foreach ($tables as $table => $cols) {
                foreach ($cols as $col) {
                    // For wp_options, also select option_name so we can detect siteurl/home
                    $is_options_table = ($table === $wpdb->options);
                    $select_cols = $is_options_table
                        ? "`option_name`, `{$col}`"
                        : "`{$col}`";

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                    $count = (int) $wpdb->get_var($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                        "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` LIKE %s",
                        '%' . $wpdb->esc_like('http://') . '%'
                    ));
                    if ($count === 0) continue;

                    $counts[] = ['table' => $table, 'column' => $col, 'count' => $count];
                    $total   += $count;

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object properties
                    $rows = $wpdb->get_results($wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col/select_cols from $wpdb object properties
                        "SELECT {$select_cols} FROM `{$table}` WHERE `{$col}` LIKE %s LIMIT 20",
                        '%' . $wpdb->esc_like('http://') . '%'
                    ), ARRAY_A);

                    foreach ((array)$rows as $row) {
                        $val         = $row[$col];
                        $option_name = $is_options_table ? ($row['option_name'] ?? '') : '';
                        preg_match_all('#http://[^\s\'"<>()\[\]]+#', $val, $matches);
                        foreach ($matches[0] as $url) {
                            $url    = rtrim($url, '.,;');
                            $domain = (string) wp_parse_url($url, PHP_URL_HOST);
                            if (!$domain) continue;
                            if (!isset($by_domain[$domain]))          $by_domain[$domain]          = [];
                            if (!isset($domain_tables[$domain]))      $domain_tables[$domain]      = [];
                            if (!isset($domain_option_names[$domain])) $domain_option_names[$domain] = [];
                            $entry = ['table' => $table, 'column' => $col, 'url' => $url];
                            if (!in_array($entry, $by_domain[$domain], true)) {
                                $by_domain[$domain][] = $entry;
                            }
                            $domain_tables[$domain][$table] = true;
                            if ($option_name && in_array($option_name, $core_url_option_names, true)) {
                                $domain_option_names[$domain][$option_name] = true;
                            }
                        }
                    }
                }
            }

            // Detect if WP_HOME or WP_SITEURL are hardcoded in wp-config.php
            // (defined as http:// constants — these override the DB and cause perpetual reversion)
            $wp_config_overrides = [];
            if (defined('WP_HOME') && strncmp((string)WP_HOME, 'http://', 7) === 0) {
                $wp_config_overrides['home'] = (string)WP_HOME;
            }
            if (defined('WP_SITEURL') && strncmp((string)WP_SITEURL, 'http://', 7) === 0) {
                $wp_config_overrides['siteurl'] = (string)WP_SITEURL;
            }

            $home_host   = (string) wp_parse_url(home_url(), PHP_URL_HOST);
            $domain_meta = [];
            foreach (array_keys($by_domain) as $domain) {
                $is_ip   = (bool) filter_var($domain, FILTER_VALIDATE_IP);
                $is_own  = (stripos($domain, $home_host) !== false);

                // A domain is spam-only when every table it appears in is a comment table
                $tables_found    = array_keys($domain_tables[$domain]);
                $all_in_comments = !$is_own && !empty($tables_found) && empty(
                    array_diff($tables_found, $comment_tables)
                );

                // Detect if this domain appears in siteurl/home options
                $option_names_found = array_keys($domain_option_names[$domain] ?? []);
                // Check if any of those options are overridden by a wp-config.php constant
                $overridden_by_wpconfig = [];
                foreach ($option_names_found as $opt_name) {
                    if (isset($wp_config_overrides[$opt_name])) {
                        $overridden_by_wpconfig[] = $opt_name;
                    }
                }

                $domain_meta[$domain] = [
                    'is_ip'                  => $is_ip,
                    'is_own'                 => $is_own,
                    'is_spam'                => $all_in_comments,
                    'core_url_options'       => $option_names_found,     // e.g. ['home', 'siteurl']
                    'overridden_by_wpconfig' => $overridden_by_wpconfig, // e.g. ['home']
                    'count'                  => count($by_domain[$domain]),
                    'urls'                   => array_slice($by_domain[$domain], 0, 20),
                ];
            }

            wp_send_json_success([
                'total'              => $total,
                'counts'             => $counts,
                'domain_meta'        => $domain_meta,
                'wp_config_overrides' => $wp_config_overrides,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

    /**
     * Replaces http:// with https:// in a value, safely handling PHP-serialized strings.
     *
     * When the serialized graph contains objects with unknown classes, PHP creates
     * __PHP_Incomplete_Class instances that cannot be traversed safely. In that case
     * falls back to a raw string replacement and fixes up the s:<len> byte-count prefixes
     * so the result remains a valid serialized string.
     *
     * @since 4.10.0
     * @param string   $value   The raw database column value (may be serialized).
     * @param string[] $domains Optional list of domains to restrict replacement to.
     * @return string Value with http:// replaced by https:// for the target domains.
     */
    private function https_replace_value(string $value, array $domains): string {
        if (!is_serialized($value)) {
            return $this->https_replace_string($value, $domains);
        }

        // Attempt to unserialize with allowed_classes => false.  Unknown classes
        // become __PHP_Incomplete_Class objects rather than triggering a fatal.
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- intentional: restoring serialized DB data with safe options
        $data = @unserialize($value, ['allowed_classes' => false]);

        if ($data === false) {
            // Corrupt serialized data — patch URLs in-place at the string level.
            return $this->https_replace_serialized_raw($value, $domains);
        }

        // If the unserialized graph contains any __PHP_Incomplete_Class objects
        // we cannot safely re-serialize (properties may be lost).  Fall back to
        // the raw string approach which is class-agnostic.
        if ($this->has_incomplete_class($data)) {
            return $this->https_replace_serialized_raw($value, $domains);
        }

        $data = $this->https_replace_recursive($data, $domains);
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- intentional: writing back safely modified serialized data
        return serialize($data);
    }

    /**
     * Recursively checks whether a value contains any __PHP_Incomplete_Class instances.
     *
     * Used to decide whether safe re-serialization is possible after unserializing with
     * allowed_classes => false. Objects and arrays are traversed; scalars return false.
     *
     * @since 4.10.0
     * @param mixed $data The value to inspect.
     * @return bool True if any node in the graph is an __PHP_Incomplete_Class.
     */
    private function has_incomplete_class(mixed $data): bool {
        if (is_object($data)) {
            if ($data instanceof \__PHP_Incomplete_Class) {
                return true;
            }
            foreach (@get_object_vars($data) as $v) {
                if ($this->has_incomplete_class($v)) return true;
            }
        }
        if (is_array($data)) {
            foreach ($data as $v) {
                if ($this->has_incomplete_class($v)) return true;
            }
        }
        return false;
    }

    /**
     * Replaces http:// URLs inside a raw serialized PHP string without deserializing.
     *
     * Performs the substitution at the string level, then corrects every s:<len>:"…"
     * byte-count prefix that was invalidated by the http→https length change.
     *
     * @since 4.10.0
     * @param string   $value   Raw serialized PHP string.
     * @param string[] $domains Optional list of domains to restrict replacement to.
     * @return string Serialized string with corrected URL schemes and updated byte counts.
     */
    private function https_replace_serialized_raw(string $value, array $domains): string {
        // Do the URL substitution on the raw string first.
        $replaced = $this->https_replace_string($value, $domains);

        if ($replaced === $value) {
            return $value; // Nothing changed — skip the expensive regex fix-up.
        }

        // Fix s:<len>:"<value>" byte-count prefixes that may now be wrong because
        // "http://" (7 bytes) was replaced with "https://" (8 bytes).
        // We rebuild every string token with the correct length.
        return preg_replace_callback(
            '/s:(\d+):"(.*?)";/s',
            static function (array $m): string {
                return 's:' . strlen($m[2]) . ':"' . $m[2] . '";';
            },
            $replaced
        ) ?? $replaced;
    }

    /**
     * Recursively replaces http:// with https:// throughout a deserialized PHP value.
     *
     * Traverses strings, arrays, and objects. Non-string scalars are returned unchanged.
     *
     * @since 4.10.0
     * @param mixed    $data    The value to process (string, array, object, or scalar).
     * @param string[] $domains Optional list of domains to restrict replacement to.
     * @return mixed The same structure with http:// replaced by https:// in all strings.
     */
    private function https_replace_recursive(mixed $data, array $domains): mixed {
        if (is_string($data)) {
            return $this->https_replace_string($data, $domains);
        }
        if (is_array($data)) {
            return array_map(fn($v) => $this->https_replace_recursive($v, $domains), $data);
        }
        if (is_object($data)) {
            // Use get_object_vars with error suppression in case of edge-case objects.
            foreach (@get_object_vars($data) as $k => $v) {
                $data->$k = $this->https_replace_recursive($v, $domains);
            }
        }
        return $data;
    }

    /**
     * Replaces http:// with https:// in a plain string, optionally scoped to specific domains.
     *
     * When $domains is empty every http:// occurrence is replaced. Otherwise only
     * occurrences of http://<domain> are replaced, one domain at a time.
     *
     * @since 4.10.0
     * @param string   $value   The string to process.
     * @param string[] $domains Optional list of domains to restrict replacement to.
     * @return string String with matching http:// schemes replaced by https://.
     */
    private function https_replace_string(string $value, array $domains): string {
        if (empty($domains)) {
            return preg_replace('#http://#', 'https://', $value);
        }
        foreach ($domains as $domain) {
            $value = str_replace('http://' . $domain, 'https://' . $domain, $value);
        }
        return $value;
    }

    /**
     * AJAX handler: replaces HTTP with HTTPS for the specified domains across the database.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_https_fix(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );


        try {
            global $wpdb;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via ajax_check()
            $domains_raw = isset($_POST['domains']) ? sanitize_text_field(wp_unslash($_POST['domains'])) : '';
            $domains = array_filter(array_map('trim', explode(',', $domains_raw)));

            $tables = [
                $wpdb->posts       => ['post_content', 'post_excerpt', 'guid'],
                $wpdb->postmeta    => ['meta_value'],
                $wpdb->options     => ['option_value'],
                $wpdb->comments    => ['comment_content', 'comment_author_url'],
                $wpdb->commentmeta => ['meta_value'],
            ];

            $changes = [];
            $total   = 0;

            foreach ($tables as $table => $cols) {
                foreach ($cols as $col) {
                    if (!empty($domains)) {
                        $where_parts = array_map(function($d) use ($wpdb, $col) {
                            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                            return $wpdb->prepare("`{$col}` LIKE %s", '%' . $wpdb->esc_like('http://' . $d) . '%');
                        }, $domains);
                        $where = implode(' OR ', $where_parts);
                    } else {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        $where = $wpdb->prepare("`{$col}` LIKE %s", '%' . $wpdb->esc_like('http://') . '%');
                    }

                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object; where built via $wpdb->prepare()
                    $affected_rows = $wpdb->get_results(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- table/col from $wpdb object; where built via $wpdb->prepare()
                        "SELECT * FROM `{$table}` WHERE {$where}",
                        ARRAY_A
                    );

                    if (empty($affected_rows)) continue;

                    foreach ($affected_rows as $row) {
                        $old_val = $row[$col];
                        preg_match_all('#http://[^\s\'"<>()\[\]]+#', $old_val, $old_url_matches);

                        $new_val = $this->https_replace_value($old_val, $domains);
                        if ($new_val === $old_val) continue;

                        $pk = null; $pk_val = null;
                        foreach (['ID', 'meta_id', 'option_id', 'comment_ID'] as $pk_name) {
                            if (isset($row[$pk_name])) { $pk = $pk_name; $pk_val = $row[$pk_name]; break; }
                        }
                        if (!$pk) continue;

                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- direct update required for HTTPS migration across arbitrary tables
                        $updated = $wpdb->update($table, [$col => $new_val], [$pk => $pk_val]);

                        if ($updated !== false && $updated > 0) {
                            foreach (array_unique($old_url_matches[0]) as $url) {
                                $url = rtrim($url, '.,;');
                                $domain = (string) wp_parse_url($url, PHP_URL_HOST);
                                if (!empty($domains) && !in_array($domain, $domains, true)) continue;
                                $changes[] = [
                                    'table'  => $table,
                                    'column' => $col,
                                    'id'     => $pk_val,
                                    'from'   => $url,
                                    'to'     => preg_replace('#^http://#', 'https://', $url),
                                ];
                            }
                            $total++;
                        }
                    }
                }
            }

            wp_cache_flush();

            wp_send_json_success([
                'fixed'   => $total,
                'changes' => $changes,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

    /**
     * Remove all database references to a given domain.
     *
     * For comment tables: deletes the entire comment (and its meta) via wp_delete_comment().
     * For posts/postmeta/options: strips the bare http://domain... URL pattern from the
     * column value rather than deleting the whole row, since those rows contain real content.
     * GUIDs in wp_posts are set to empty string (WordPress re-generates them on save).
     */
    /**
     * AJAX handler: removes all database rows containing HTTP references for the specified domains.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_https_delete(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked via ajax_check()
        $domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        if (empty($domain)) {
            wp_send_json_error('No domain provided.');
        }

        try {
            global $wpdb;
            $like    = '%' . $wpdb->esc_like($domain) . '%';
            $deleted = 0;

            // 1. Delete entire comments (and meta) that reference this domain
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $comment_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT comment_ID FROM {$wpdb->comments}
                  WHERE comment_content LIKE %s OR comment_author_url LIKE %s",
                $like, $like
            ));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $meta_comment_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT comment_id FROM {$wpdb->commentmeta} WHERE meta_value LIKE %s",
                $like
            ));
            $all_comment_ids = array_unique(array_merge(
                array_map('intval', (array)$comment_ids),
                array_map('intval', (array)$meta_comment_ids)
            ));
            foreach ($all_comment_ids as $cid) {
                if (wp_delete_comment($cid, true)) $deleted++;
            }

            // 2. Strip URL references from posts (post_content, post_excerpt)
            foreach (['post_content', 'post_excerpt'] as $col) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- col is a hardcoded string literal from foreach
                $rows = $wpdb->get_results($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- col is a hardcoded string literal from foreach
                    "SELECT ID, `{$col}` FROM {$wpdb->posts} WHERE `{$col}` LIKE %s",
                    $like
                ), ARRAY_A);
                foreach ((array)$rows as $row) {
                    $new_val = preg_replace(
                        '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                        '',
                        $row[$col]
                    );
                    if ($new_val !== $row[$col]) {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $wpdb->update($wpdb->posts, [$col => $new_val], ['ID' => (int)$row['ID']]);
                        $deleted++;
                    }
                }
            }

            // 3. Clear guid entirely for attachment rows that used this IP
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $guid_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$guid_rows as $row) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($wpdb->posts, ['guid' => ''], ['ID' => (int)$row['ID']]);
                $deleted++;
            }

            // 4. Strip from postmeta values
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- direct query required for domain removal; meta_value scan is intentional
            $meta_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$meta_rows as $row) {
                $new_val = preg_replace(
                    '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                    '',
                    $row['meta_value']
                );
                if ($new_val !== $row['meta_value']) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- update by meta_id PK, not a slow scan
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $new_val], ['meta_id' => (int)$row['meta_id']]);
                    $deleted++;
                }
            }

            // 5. Strip from options
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $opt_rows = $wpdb->get_results($wpdb->prepare(
                "SELECT option_id, option_value FROM {$wpdb->options} WHERE option_value LIKE %s",
                $like
            ), ARRAY_A);
            foreach ((array)$opt_rows as $row) {
                $new_val = preg_replace(
                    '#https?://' . preg_quote($domain, '#') . '[^\s\'"<>()\[\]]*#i',
                    '',
                    $row['option_value']
                );
                if ($new_val !== $row['option_value']) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->update($wpdb->options, ['option_value' => $new_val], ['option_id' => (int)$row['option_id']]);
                    $deleted++;
                }
            }

            wp_cache_flush();

            wp_send_json_success([
                'deleted' => $deleted,
                'domain'  => $domain,
            ]);

        } catch (\Throwable $e) {
            wp_send_json_error(sprintf(
                '%s in %s on line %d',
                $e->getMessage(),
                str_replace(ABSPATH, '', $e->getFile()),
                $e->getLine()
            ));
        }
    }

}
