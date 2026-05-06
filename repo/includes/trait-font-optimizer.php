<?php
/**
 * Font-display optimisation — scans CSS files and injects font-display: swap.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.9.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Font_Optimizer {
    // =========================================================================
    // Font Display Optimization - Scanner & Fixer
    // =========================================================================

    /**
     * Scans all enqueued stylesheets for @font-face blocks missing font-display.
     *
     * @since 4.9.0
     * @return array {
     *     @type int   $total_files      CSS files that contain @font-face blocks needing fixes.
     *     @type int   $total_fonts      Total @font-face blocks found across all scanned files.
     *     @type int   $missing_fonts    @font-face blocks missing a font-display property.
     *     @type array $files            Per-file detail records including handle, path, fonts, savings.
     *     @type int   $total_savings_ms Estimated LCP saving in milliseconds.
     * }
     */
    private function scan_enqueued_css(): array {
        global $wp_styles;
        self::debug_log('Font Display Scan: Initializing CSS file scanner');

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $results = [
            'total_files' => 0, 'total_fonts' => 0, 'missing_fonts' => 0,
            'files' => [], 'total_savings_ms' => 0,
        ];
        if (!$wp_styles || !isset($wp_styles->queue)) {
            self::debug_log('Font Display Scan: No styles queue found');
            return $results;
        }
        
        $savings_map = [
            'Montserrat' => ['400' => 1820, '700' => 780, 'italic' => 760],
            'Merriweather' => ['400' => 1000, '700' => 780, '400i' => 760, '700i' => 730],
        ];
        
        self::debug_log('Font Display Scan: Scanning ' . count($wp_styles->queue) . ' stylesheets');
        
        foreach ($wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                self::debug_log('Font Display Scan: Handle not registered: ' . $handle);
                continue;
            }
            $src = $wp_styles->registered[$handle]->src;
            if (!$src) {
                self::debug_log('Font Display Scan: No src for handle: ' . $handle);
                continue;
            }
            
            $file_path = $this->resolve_css_path($src);
            if (!$file_path || !file_exists($file_path)) {
                self::debug_log('Font Display Scan: File not found or unresolvable: ' . $src);
                continue;
            }
            
            self::debug_log('Font Display Scan: Processing file: ' . basename($file_path));
            
            $css_content = $wp_filesystem ? $wp_filesystem->get_contents($file_path) : '';
            if (!$css_content) {
                self::debug_log('Font Display Scan: Cannot read file: ' . $file_path);
                continue;
            }
            
            $file_info = [
                'handle' => $handle, 'url' => $src, 'path' => $file_path,
                'writable' => wp_is_writable(dirname($file_path)), 'fonts' => [],
                'missing_count' => 0, 'total_savings' => 0,
            ];
            
            if (preg_match_all('/@font-face\s*\{([^}]+)\}/i', $css_content, $matches)) {
                self::debug_log('Font Display Scan: Found ' . count($matches[0]) . ' @font-face blocks in ' . basename($file_path));
                
                foreach ($matches[0] as $idx => $full_block) {
                    $block = $matches[1][$idx];
                    $family = 'Unknown';
                    if (preg_match('/font-family\s*:\s*[\'"]?([^\'";\n]+)/i', $block, $m)) {
                        $family = trim($m[1], '\'"');
                    }
                    $weight = '400';
                    if (preg_match('/font-weight\s*:\s*(\d+|bold|normal)/i', $block, $m)) {
                        $weight = trim($m[1]);
                        if ($weight === 'normal') $weight = '400';
                        if ($weight === 'bold') $weight = '700';
                    }
                    $style = 'normal';
                    if (preg_match('/font-style\s*:\s*(\w+)/i', $block, $m)) {
                        $style = trim($m[1]);
                    }
                    $has_display = strpos($block, 'font-display') !== false;
                    $savings = 0;
                    foreach ($savings_map as $fname => $weights) {
                        if (stripos($family, $fname) !== false) {
                            $key = $style === 'italic' ? $weight . 'i' : $weight;
                            $savings = $weights[$key] ?? $weights[$weight] ?? 0;
                            break;
                        }
                    }
                    
                    $font_status = $has_display ? 'has font-display' : 'MISSING font-display';
                    self::debug_log('Font Display Scan:   • ' . $family . ' ' . $weight . '/' . $style . ' (' . $font_status . ', ' . $savings . 'ms potential)');
                    
                    $file_info['fonts'][] = [
                        'family' => $family, 'weight' => $weight, 'style' => $style,
                        'has_display' => $has_display, 'savings_ms' => $has_display ? 0 : $savings,
                    ];
                    if (!$has_display) {
                        $file_info['missing_count']++;
                        $file_info['total_savings'] += $savings;
                        $results['total_savings_ms'] += $savings;
                    }
                }
            // Add to results regardless of missing fonts
            if (count($file_info['fonts']) > 0) {
                // Only add files that have fonts (even if all optimized)
                if ($file_info['missing_count'] > 0) {
                    $results['total_files']++;
                    $results['missing_fonts'] += $file_info['missing_count'];
                    self::debug_log('Font Display Scan: File needs fixing: ' . basename($file_path) . ' (' . $file_info['missing_count'] . ' fonts)');
                } else {
                    // File has fonts but all are optimized - still track it
                    self::debug_log('Font Display Scan: File optimized: ' . basename($file_path) . ' (' . count($file_info['fonts']) . ' fonts already have font-display)');
                }
                $results['files'][] = $file_info;
            }
            }
            $results['total_fonts'] += count($file_info['fonts']);
        }
        
        self::debug_log('Font Display Scan: Complete - ' . $results['total_files'] . ' files need fixing, ' . $results['missing_fonts'] . ' fonts total, ' . $results['total_savings_ms'] . 'ms potential savings');
        return $results;
    }

    /**
     * Resolves a stylesheet URL or path to an absolute server filesystem path.
     *
     * Handles content-relative, site-relative, root-relative, and absolute paths.
     * Returns null when the source cannot be mapped to a local file.
     *
     * @since 4.9.0
     * @param string $src Stylesheet URL or server path.
     * @return string|null Absolute filesystem path, or null if unresolvable.
     */
    private function resolve_css_path(string $src): ?string {
        if (strpos($src, ABSPATH) === 0) return $src;
        $site_url = home_url('/');
        $content_url = content_url('/');
        if (strpos($src, $content_url) === 0) {
            $rel_path = str_replace($content_url, '', $src);
            return WP_CONTENT_DIR . '/' . $rel_path;
        }
        if (strpos($src, $site_url) === 0) {
            $rel_path = str_replace($site_url, '', $src);
            return ABSPATH . $rel_path;
        }
        if ($src[0] === '/') return ABSPATH . ltrim($src, '/');
        return null;
    }

    /**
     * Patches a CSS file by injecting font-display and optional metric overrides into @font-face blocks.
     *
     * Creates a restorable backup in wp_options before writing. Returns a result array
     * with 'success' => false and an 'error' message on failure.
     *
     * @since 4.9.0
     * @param string $file_path      Absolute path to the CSS file to patch.
     * @param string $display_value  Value for the font-display property (default 'swap').
     * @param bool   $add_metrics    When true, also injects ascent/descent/line-gap overrides.
     * @return array { @type bool   $success   Whether the file was written successfully.
     *                 @type string $file_path Absolute path of the patched file (on success).
     *                 @type string $backup_key wp_options key holding the original content.
     *                 @type bool   $changed   Whether any @font-face blocks were modified. }
     */
    private function fix_css_fonts(string $file_path, string $display_value = 'swap', bool $add_metrics = true): array {
        self::debug_log('Font Fix: Starting fix for ' . basename($file_path) . ' with display=' . $display_value . ', metrics=' . ($add_metrics ? 'yes' : 'no'));

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $original = $wp_filesystem ? $wp_filesystem->get_contents($file_path) : '';
        if (!$original) {
            self::debug_log('Font Fix ERROR: Cannot read file ' . $file_path);
            return ['success' => false, 'error' => 'Cannot read file'];
        }
        
        $backup_key = 'cs_seo_font_backup_' . md5($file_path);
        self::debug_log('Font Fix: Creating backup with key ' . $backup_key);
        
        update_option($backup_key, [
            'file_path' => $file_path, 'content' => $original, 'date' => current_time('mysql'),
        ]);
        
        $patched = $this->patch_font_face_blocks($original, $display_value, $add_metrics);
        if ($patched === $original) {
            self::debug_log('Font Fix WARNING: No changes detected in ' . basename($file_path));
            return ['success' => false, 'error' => 'No changes needed'];
        }
        
        $written = $wp_filesystem ? $wp_filesystem->put_contents($file_path, $patched, FS_CHMOD_FILE) : false;
        if (!$written) {
            self::debug_log('Font Fix ERROR: Cannot write file ' . $file_path . ' (permission denied)');
            delete_option($backup_key);
            return ['success' => false, 'error' => 'Cannot write file (permission denied)'];
        }
        
        self::debug_log('Font Fix: Successfully wrote changes to ' . basename($file_path));
        wp_cache_flush();
        if (function_exists('wp_cache_clear_cache')) wp_cache_clear_cache();
        
        self::debug_log('Font Fix: Backup saved. Can be restored with backup key: ' . $backup_key);
        return ['success' => true, 'file_path' => $file_path, 'backup_key' => $backup_key, 'changed' => true];
    }

    /**
     * Rewrites every @font-face block in a CSS string to add font-display and optional metric overrides.
     *
     * Blocks that already contain font-display are left unchanged.
     *
     * @since 4.9.0
     * @param string $css            Raw CSS content.
     * @param string $display_value  Value for the font-display property (default 'swap').
     * @param bool   $add_metrics    When true, appends ascent-override, descent-override, and line-gap-override.
     * @return string Modified CSS with font-display injected into all eligible @font-face blocks.
     */
    private function patch_font_face_blocks(string $css, string $display_value = 'swap', bool $add_metrics = true): string {
        self::debug_log('Font Patch: Starting patch with display=' . $display_value . ', metrics=' . ($add_metrics ? 'yes' : 'no'));
        
        $patched_count = 0;
        $result = preg_replace_callback('/@font-face\s*\{([^}]+)\}/i', function($matches) use ($display_value, $add_metrics, &$patched_count) {
            $block = $matches[1];
            
            if (strpos($block, 'font-display') !== false) {
                self::debug_log('Font Patch: Skipping block with existing font-display');
                return $matches[0];
            }
            
            $patched_count++;
            self::debug_log('Font Patch: Patching block #' . $patched_count);
            
            // Add font-display after the first property (more reliable)
            // If font-style exists, add after it; otherwise add after font-family or src
            if (preg_match('/(font-style\s*:\s*[^;]+;)/i', $block)) {
                $block = preg_replace('/(font-style\s*:\s*[^;]+;)/i', '$1' . "\n    font-display: $display_value;", $block);
            } elseif (preg_match('/(font-weight\s*:\s*[^;]+;)/i', $block)) {
                $block = preg_replace('/(font-weight\s*:\s*[^;]+;)/i', '$1' . "\n    font-display: $display_value;", $block);
            } else {
                // Fallback: add before the closing brace
                $block = rtrim($block, ';') . ";\n    font-display: $display_value;";
            }
            
            if ($add_metrics && strpos($block, 'ascent-override') === false) {
                self::debug_log('Font Patch: Adding metric overrides to block #' . $patched_count);
                $block .= "\n    ascent-override: 108%;\n    descent-override: 27%;\n    line-gap-override: 0%;";
            }
            return "@font-face {" . $block . "}";
        }, $css);
        
        self::debug_log('Font Patch: Complete - patched ' . $patched_count . ' @font-face blocks');
        return $result;
    }

    /**
     * Restores a CSS file to its pre-patch state using the backup stored in wp_options.
     *
     * Deletes the backup option and flushes the object cache on success.
     *
     * @since 4.9.0
     * @param string $file_path Absolute path to the CSS file to restore.
     * @return array { @type bool   $success Whether the file was restored.
     *                 @type string $error   Error message when success is false. }
     */
    private function undo_font_fixes(string $file_path): array {
        self::debug_log('Font Undo: Attempting to restore ' . basename($file_path));

        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $backup_key = 'cs_seo_font_backup_' . md5($file_path);
        $backup = get_option($backup_key);
        if (!$backup) {
            self::debug_log('Font Undo ERROR: No backup found for key ' . $backup_key);
            return ['success' => false, 'error' => 'No backup found'];
        }

        self::debug_log('Font Undo: Found backup from ' . ($backup['date'] ?? 'unknown date'));

        $written = $wp_filesystem ? $wp_filesystem->put_contents($file_path, $backup['content'], FS_CHMOD_FILE) : false;
        if (!$written) {
            self::debug_log('Font Undo ERROR: Cannot write file ' . $file_path . ' (permission denied)');
            return ['success' => false, 'error' => 'Cannot write file'];
        }
        
        delete_option($backup_key);
        wp_cache_flush();
        
        self::debug_log('Font Undo: Successfully restored ' . basename($file_path) . ' and deleted backup');
        return ['success' => true];
    }

    /**
     * Defer font CSS loading to prevent render-blocking
     * Loads fonts asynchronously so they don't block initial page render
     */
    public function defer_font_css(string $html, string $handle): string {
        // List of font-related stylesheet handles to defer
        $font_handles = [
            'twentysixteen-fonts',      // Twenty Sixteen theme fonts
            'custom-fonts',             // Generic custom fonts
            'google-fonts',             // Google Fonts
            'local-fonts',              // Locally hosted fonts
        ];
        
        // Check if this is a font stylesheet
        $is_font = false;
        foreach ($font_handles as $font_handle) {
            if (strpos($handle, $font_handle) !== false) {
                $is_font = true;
                break;
            }
        }
        
        // Also check if the handle contains 'font' or 'merriweather' or 'montserrat'
        if (!$is_font && (strpos($handle, 'font') !== false || strpos($handle, 'merriweather') !== false || strpos($handle, 'montserrat') !== false)) {
            $is_font = true;
        }
        
        if (!$is_font) {
            return $html;
        }
        
        // Check if it's already deferred/async
        if (strpos($html, 'media=') !== false && strpos($html, 'print') !== false) {
            return $html; // Already deferred
        }
        
        // Defer the font loading: load as print media, then switch to all via JS
        // This prevents render-blocking while still loading the fonts
        $html = str_replace(
            "media='all'",
            "media='print' onload=\"this.media='all'\"",
            $html
        );
        
        // Add noscript fallback for users without JavaScript.
        // Extract href before composing — concatenation has higher precedence than ternary,
        // so inline use of preg_match() in a string-concat ternary always evaluates the
        // non-empty left operand as truthy and returns $m[1] with no surrounding HTML.
        $noscript_href = '';
        preg_match( '/href=["\']([^"\']+)["\']/', $html, $noscript_m );
        if ( ! empty( $noscript_m[1] ) ) {
            $noscript_href = $noscript_m[1];
        }
        // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- This is a noscript fallback for an already-enqueued style
        $html .= '<noscript><link rel="stylesheet" href="' . esc_attr( $noscript_href ) . '" /></noscript>';
        
        return $html;
    }

    /**
     * Auto-detect and download Google Fonts from CDN to local
     */
    public function ajax_download_fonts(): void {
        try {
            self::debug_log('Font Download: Starting auto-download process');
            
            check_ajax_referer('cs_seo_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Permission denied']);
            }
            
            global $wp_styles;
            if (!$wp_styles || !isset($wp_styles->queue)) {
                wp_send_json_error(['message' => 'No CSS files found']);
            }
            
            $downloaded = 0;
            $messages = [];
            
            foreach ($wp_styles->queue as $handle) {
                if (!isset($wp_styles->registered[$handle])) continue;
                
                $src = $wp_styles->registered[$handle]->src ?? '';
                if (empty($src)) continue;
                
                // Check if it's a Google Fonts URL
                if (strpos($src, 'fonts.googleapis.com') !== false || strpos($src, 'fonts.gstatic.com') !== false) {
                    self::debug_log('Font Download: Found Google Fonts URL: ' . $src);
                    $messages[] = 'Detected: ' . basename($src);
                    
                    // Download the CSS file
                    $response = wp_remote_get($src, ['timeout' => 30]);
                    
                    if (is_wp_error($response)) {
                        self::debug_log('Font Download ERROR: ' . $response->get_error_message());
                        continue;
                    }
                    
                    $css_content = wp_remote_retrieve_body($response);
                    if (empty($css_content)) {
                        self::debug_log('Font Download ERROR: Empty response');
                        continue;
                    }
                    
                    // Create fonts directory if it doesn't exist
                    $fonts_dir = WP_CONTENT_DIR . '/fonts';
                    if (!file_exists($fonts_dir)) {
                        wp_mkdir_p($fonts_dir);
                        self::debug_log('Font Download: Created fonts directory');
                    }
                    
                    // Save CSS file with optimizations
                    $css_file = $fonts_dir . '/' . sanitize_file_name(basename($src));
                    
                    // Add font-display and metric overrides to all @font-face blocks
                    $optimized_css = preg_replace_callback(
                        '/@font-face\s*\{([^}]+)\}/i',
                        function($matches) {
                            $block = $matches[1];
                            // Add font-display if missing
                            if (strpos($block, 'font-display') === false) {
                                $block = preg_replace(
                                    '/(font-style\s*:\s*[^;]+;)/i',
                                    '$1' . "\n    font-display: swap;",
                                    $block
                                );
                            }
                            // Add metric overrides if missing
                            if (strpos($block, 'ascent-override') === false) {
                                $block .= "\n    ascent-override: 108%;\n    descent-override: 27%;\n    line-gap-override: 0%;";
                            }
                            return "@font-face {" . $block . "}";
                        },
                        $css_content
                    );
                    
                    // Write to local file
                    global $wp_filesystem;
                    if (empty($wp_filesystem)) {
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        WP_Filesystem();
                    }
                    if ($wp_filesystem && $wp_filesystem->put_contents($css_file, $optimized_css, FS_CHMOD_FILE)) {
                        self::debug_log('Font Download: Successfully saved ' . $css_file);
                        $messages[] = '✓ Downloaded: ' . basename($css_file);
                        $downloaded++;
                    } else {
                        self::debug_log('Font Download ERROR: Cannot write file ' . $css_file);
                        $messages[] = '✗ Failed to save: ' . basename($css_file);
                    }
                }
            }
            
            if ($downloaded > 0) {
                self::debug_log('Font Download: Complete - downloaded ' . $downloaded . ' file(s)');
                $messages[] = '';
                $messages[] = '✓ Fonts downloaded successfully!';
                $messages[] = '✓ Font-display and metric overrides added automatically';
                $messages[] = '✓ Next step: Run "Scan CSS Files" again to verify';
                wp_send_json(['success' => true, 'downloaded' => $downloaded, 'messages' => $messages]);
            } else {
                self::debug_log('Font Download: No Google Fonts found to download');
                $messages[] = '';
                $messages[] = 'ℹ No Google Fonts CDN URLs detected';
                wp_send_json(['success' => false, 'message' => 'No fonts to download', 'messages' => $messages]);
            }
            
        } catch (Exception $e) {
            self::debug_log('Font Download Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler: scans enqueued CSS files for @font-face rules missing font-display.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_font_scan(): void {
        self::debug_log('AJAX Handler: font_scan started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('AJAX Handler: font_scan - permission denied');
            wp_die();
        }
        
        $results = $this->scan_enqueued_css();
        $console_lines = [];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => 'FONT-DISPLAY OPTIMIZATION SCANNER'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        if ($results['total_files'] === 0) {
            self::debug_log('AJAX Handler: font_scan - checking results');
            
            // Show summary first
            $total_checked = count($results['files']);
            $console_lines[] = ['type' => 'info', 'text' => sprintf('✓ Scanned %d CSS file(s) total', $total_checked)];
            $console_lines[] = ['type' => 'info', 'text' => sprintf('✓ Found %d @font-face block(s) overall', $results['total_fonts'])];
            
            if (count($results['files']) > 0) {
                $console_lines[] = ['type' => 'ok', 'text' => '✓ All fonts already have font-display property'];
                $console_lines[] = ['type' => 'info', 'text' => ''];
                $console_lines[] = ['type' => 'info', 'text' => 'OPTIMIZED FONTS:'];
                $console_lines[] = ['type' => 'info', 'text' => ''];
                
                // Show all fonts with details
                foreach ($results['files'] as $file) {
                    $console_lines[] = ['type' => 'info', 'text' => '📄 ' . basename($file['path'])];
                    if (!empty($file['fonts'])) {
                        foreach ($file['fonts'] as $font) {
                            $console_lines[] = ['type' => 'ok', 'text' => sprintf('  ✓ %s %s/%s - has font-display', $font['family'], $font['weight'], $font['style'])];
                        }
                    } else {
                        $console_lines[] = ['type' => 'skip', 'text' => '  (No fonts found in this file)'];
                    }
                    $console_lines[] = ['type' => 'info', 'text' => ''];
                }
            } else {
                $console_lines[] = ['type' => 'warn', 'text' => 'ℹ No @font-face blocks found in any CSS files'];
                $console_lines[] = ['type' => 'skip', 'text' => 'This means either:'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • No fonts are loaded in WordPress'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • Fonts are loaded from external CDN (not locally)'];
                $console_lines[] = ['type' => 'skip', 'text' => '  • Font CSS files aren\'t registered with WordPress'];
            }
            
            self::debug_log('AJAX Handler: font_scan - all fonts optimized or no fonts found');
            wp_send_json(['success' => true, 'console' => $console_lines, 'findings' => $results]);
        }
        
        self::debug_log('AJAX Handler: font_scan - found ' . $results['total_files'] . ' files needing fixes');
        
        $console_lines[] = ['type' => 'warn', 'text' => sprintf('⚠ Found %d file(s) with %d font(s) missing font-display', $results['total_files'], $results['missing_fonts'])];
        $console_lines[] = ['type' => 'warn', 'text' => sprintf('⚠ Potential PageSpeed savings: %d ms', $results['total_savings_ms'])];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        $console_lines[] = ['type' => 'info', 'text' => 'SCANNING FILES:'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        foreach ($results['files'] as $file) {
            $writable_status = $file['writable'] ? '✓' : '✗';
            $console_lines[] = ['type' => 'info', 'text' => sprintf('%s %s', $writable_status, basename($file['path']))];
            foreach ($file['fonts'] as $font) {
                $status = $font['has_display'] ? '✓ has' : '✗ missing';
                $savings = $font['savings_ms'] > 0 ? ' (' . $font['savings_ms'] . 'ms)' : '';
                $console_lines[] = ['type' => $font['has_display'] ? 'ok' : 'err', 'text' => sprintf('  • %s %s %s/%s%s', $status, $font['family'], $font['weight'], $font['style'], $savings)];
            }
            $console_lines[] = ['type' => 'info', 'text' => ''];
        }
        
        $console_lines[] = ['type' => 'info', 'text' => 'WHAT WILL BE FIXED:'];
        $console_lines[] = ['type' => 'skip', 'text' => 'Each @font-face block missing font-display will be patched with:'];
        $console_lines[] = ['type' => 'skip', 'text' => ''];
        $console_lines[] = ['type' => 'skip', 'text' => '  font-display: swap;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  ascent-override: 108%;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  descent-override: 27%;'];
        $console_lines[] = ['type' => 'skip', 'text' => '  line-gap-override: 0%;'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        self::debug_log('AJAX Handler: font_scan complete, sending response');
        wp_send_json(['success' => true, 'console' => $console_lines, 'findings' => $results]);
    }

    /**
     * AJAX handler: patches all CSS files with missing font-display and metric overrides.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_font_fix(): void {
        self::debug_log('AJAX Handler: font_fix started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('AJAX Handler: font_fix - permission denied');
            wp_die();
        }
        
        $results = $this->scan_enqueued_css();
        $console_lines = [];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => 'FONT-DISPLAY OPTIMIZATION: AUTO-FIX'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'info', 'text' => ''];
        
        $fixed_count = 0;
        $failed_count = 0;
        
        self::debug_log('AJAX Handler: font_fix - processing ' . count($results['files']) . ' files');
        
        foreach ($results['files'] as $file) {
            self::debug_log('AJAX Handler: font_fix - processing ' . basename($file['path']));
            
            $console_lines[] = ['type' => 'info', 'text' => 'Processing: ' . basename($file['path'])];
            if (!$file['writable']) {
                self::debug_log('AJAX Handler: font_fix ERROR - ' . basename($file['path']) . ' not writable');
                $console_lines[] = ['type' => 'err', 'text' => '  ✗ ERROR: File not writable (permission denied)'];
                $failed_count++;
                continue;
            }
            
            $fix_result = $this->fix_css_fonts($file['path'], 'swap', true);
            if ($fix_result['success']) {
                self::debug_log('AJAX Handler: font_fix SUCCESS - fixed ' . $file['missing_count'] . ' fonts in ' . basename($file['path']));
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Fixed ' . $file['missing_count'] . ' @font-face block(s)'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Added font-display: swap'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Added metric overrides (CLS prevention)'];
                $console_lines[] = ['type' => 'ok', 'text' => '  ✓ Backup created (can undo)'];
                $fixed_count++;
            } else {
                self::debug_log('AJAX Handler: font_fix ERROR - ' . $fix_result['error']);
                $console_lines[] = ['type' => 'err', 'text' => '  ✗ ERROR: ' . $fix_result['error']];
                $failed_count++;
            }
            $console_lines[] = ['type' => 'info', 'text' => ''];
        }
        
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        $console_lines[] = ['type' => 'ok', 'text' => sprintf('✓ Fixed: %d file(s)', $fixed_count)];
        if ($failed_count > 0) {
            $console_lines[] = ['type' => 'err', 'text' => sprintf('✗ Failed: %d file(s)', $failed_count)];
        }
        $console_lines[] = ['type' => 'warn', 'text' => '⚠ Estimated savings: ' . $results['total_savings_ms'] . 'ms on first page load'];
        $console_lines[] = ['type' => 'skip', 'text' => 'Next: Run a PageSpeed test to verify improvement'];
        $console_lines[] = ['type' => 'info', 'text' => '═══════════════════════════════════════════════════════════'];
        
        self::debug_log('AJAX Handler: font_fix complete - fixed ' . $fixed_count . ', failed ' . $failed_count);
        wp_send_json(['success' => true, 'console' => $console_lines, 'fixed' => $fixed_count, 'failed' => $failed_count]);
    }

    /**
     * AJAX handler: restores a CSS file from its backup, undoing font-display patches.
     *
     * @since 4.10.0
     * @return void
     */
    public function ajax_font_undo(): void {
        self::debug_log('AJAX Handler: font_undo started');
        
        check_ajax_referer('cs_seo_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            self::debug_log('AJAX Handler: font_undo - permission denied');
            wp_die();
        }
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field(wp_unslash($_POST['file_path'])) : '';
        if (!$file_path) {
            self::debug_log('AJAX Handler: font_undo - no file path provided');
            wp_send_json(['success' => false, 'error' => 'No file specified']);
        }

        // Prevent path traversal: file must reside within the WordPress installation.
        $real_path = realpath($file_path);
        $real_base = realpath(ABSPATH);
        if ($real_path === false || $real_base === false || strpos($real_path, $real_base) !== 0) {
            self::debug_log('AJAX Handler: font_undo - path traversal attempt blocked: ' . $file_path);
            wp_send_json(['success' => false, 'error' => 'Invalid file path']);
        }
        
        self::debug_log('AJAX Handler: font_undo - restoring ' . basename($file_path));
        $result = $this->undo_font_fixes($file_path);
        wp_send_json($result);
    }
}
