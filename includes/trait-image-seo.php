<?php
/**
 * Image SEO Audit — scans the Media Library for filename, file-size, and ALT text issues.
 *
 * Flags three categories of problem:
 *   - missing_alt   : no _wp_attachment_image_alt value set.
 *   - bad_filename  : camera default or non-descriptive name (IMG_001, screenshot2, etc.).
 *   - large_file    : file on disk exceeds 500 KB.
 *
 * Only images with at least one issue are returned so the results table stays focused.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.19.145
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_Image_SEO {

    // =========================================================================
    // AJAX — scan
    // =========================================================================

    /**
     * Scans the entire Media Library and returns images that have at least one SEO issue.
     *
     * @since 4.19.145
     * @return void
     */
    public function ajax_imgseo_scan(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $attachments = get_posts([
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        // Build a map of attach_id → post_id for all featured images in one query.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $featured_rows = $wpdb->get_results(
            "SELECT meta_value AS attach_id, post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'",
            ARRAY_A
        );
        $featured_map = [];
        foreach ( $featured_rows as $row ) {
            $featured_map[ (int) $row['attach_id'] ] = (int) $row['post_id'];
        }

        $results      = [];
        $missing_alt  = 0;
        $bad_filename = 0;
        $large_file   = 0;

        foreach ( $attachments as $id ) {
            $file  = get_attached_file( $id );
            $fname = $file ? basename( $file ) : '';
            $fsize = ( $file && file_exists( $file ) ) ? (int) filesize( $file ) : 0;
            $alt   = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

            $parent_id    = (int) get_post_field( 'post_parent', $id );
            $parent_title = $parent_id ? (string) get_the_title( $parent_id ) : '';
            $parent_edit  = $parent_id ? (string) get_edit_post_link( $parent_id, 'raw' ) : '';

            $issues = [];
            if ( '' === trim( $alt ) ) {
                $issues[] = 'missing_alt';
                $missing_alt++;
            }
            $is_bad_fname = $fname && $this->imgseo_is_bad_filename( $fname );
            if ( $is_bad_fname ) {
                $issues[] = 'bad_filename';
                $bad_filename++;
            }
            if ( $fsize > 500 * 1024 ) {
                $issues[] = 'large_file';
                $large_file++;
            }

            if ( empty( $issues ) ) continue;

            // Rename suggestion: featured images get -featured suffix; other parent-attached images use parent slug.
            $is_featured       = isset( $featured_map[ $id ] );
            $suggested_name    = '';
            $featured_post_id  = $is_featured ? $featured_map[ $id ] : 0;
            if ( $is_bad_fname ) {
                $name_post_id = $is_featured ? $featured_map[ $id ] : $parent_id;
                if ( $name_post_id ) {
                    $name_post = get_post( $name_post_id );
                    if ( $name_post ) {
                        $slug           = $name_post->post_name ?: sanitize_title( $name_post->post_title );
                        $ext            = strtolower( pathinfo( $fname, PATHINFO_EXTENSION ) );
                        $suffix         = $is_featured ? '-featured' : '';
                        $suggested_name = $this->imgseo_short_slug( $slug ) . $suffix . '.' . $ext;
                    }
                }
            }

            $results[] = [
                'id'               => $id,
                'filename'         => $fname,
                'filesize'         => $fsize,
                'alt'              => $alt,
                'parent_title'     => $parent_title,
                'parent_edit'      => $parent_edit,
                'thumb_url'        => (string) ( wp_get_attachment_image_url( $id, 'thumbnail' ) ?: '' ),
                'edit_url'         => (string) ( get_edit_post_link( $id, 'raw' ) ?: '' ),
                'issues'           => $issues,
                'is_featured'      => $is_featured,
                'featured_post_id' => $featured_post_id,
                'suggested_name'   => $suggested_name,
            ];
        }

        // Most issues first; featured-image bad-filenames bubble to top within same count.
        usort( $results, static function ( $a, $b ) {
            $diff = count( $b['issues'] ) - count( $a['issues'] );
            if ( $diff !== 0 ) return $diff;
            return (int) $b['is_featured'] - (int) $a['is_featured'];
        } );

        wp_send_json_success([
            'total'        => count( $attachments ),
            'with_issues'  => count( $results ),
            'missing_alt'  => $missing_alt,
            'bad_filename' => $bad_filename,
            'large_file'   => $large_file,
            'images'       => $results,
        ]);
    }

    // =========================================================================
    // AJAX — rename featured image
    // =========================================================================

    /**
     * Renames a featured-image attachment to {post-slug}-featured.{ext}.
     * Updates the physical file, _wp_attached_file meta, attachment metadata,
     * and the attachment guid. Does NOT create a PHP-level redirect because
     * static image files are served by the web server and bypass WordPress.
     *
     * @since 4.19.160
     * @return void
     */
    public function ajax_imgseo_rename_featured(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );

        $attach_id = isset( $_POST['attach_id'] ) ? (int) $_POST['attach_id'] : 0;
        if ( ! $attach_id ) {
            wp_send_json_error( 'Invalid attachment ID.' );
        }

        $attachment = get_post( $attach_id );
        if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
            wp_send_json_error( 'Attachment not found.' );
        }

        // Resolve the associated post: featured image lookup first, then post_parent.
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $feat_post_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d LIMIT 1",
            $attach_id
        ) );
        $is_featured = $feat_post_id > 0;
        $post_id     = $is_featured ? $feat_post_id : (int) $attachment->post_parent;

        if ( ! $post_id ) {
            wp_send_json_error( 'Image is not attached to any post — cannot determine a slug for renaming.' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            wp_send_json_error( 'Parent post not found.' );
        }

        $post_slug = $post->post_name ?: sanitize_title( $post->post_title );
        if ( ! $post_slug ) {
            wp_send_json_error( 'Could not determine post slug — save the post first.' );
        }

        $old_path = get_attached_file( $attach_id );
        if ( ! $old_path || ! file_exists( $old_path ) ) {
            wp_send_json_error( 'Attachment file not found on disk.' );
        }

        $dir = trailingslashit( dirname( $old_path ) );
        $ext = strtolower( pathinfo( $old_path, PATHINFO_EXTENSION ) );

        // Resolve collision-free target filename.
        $short_slug     = $this->imgseo_short_slug( $post_slug );
        $name_suffix    = $is_featured ? '-featured' : '';
        $new_filename   = $short_slug . $name_suffix . '.' . $ext;
        $new_path       = $dir . $new_filename;
        $suffix         = 2;
        while ( file_exists( $new_path ) && $new_path !== $old_path ) {
            $new_filename = $short_slug . $name_suffix . '-' . $suffix . '.' . $ext;
            $new_path     = $dir . $new_filename;
            $suffix++;
        }

        if ( $old_path === $new_path ) {
            wp_send_json_success( [ 'filename' => $new_filename, 'skipped' => true ] );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- no WP_Filesystem equivalent for simple file rename.
        if ( ! rename( $old_path, $new_path ) ) {
            wp_send_json_error( 'rename() failed — check file permissions on the uploads directory.' );
        }

        // Compute relative path from uploads base.
        $uploads      = wp_upload_dir();
        $new_relative = ltrim( str_replace( trailingslashit( $uploads['basedir'] ), '', $new_path ), '/' );

        update_post_meta( $attach_id, '_wp_attached_file', $new_relative );

        $meta = wp_get_attachment_metadata( $attach_id );
        if ( is_array( $meta ) ) {
            $meta['file'] = $new_relative;
            wp_update_attachment_metadata( $attach_id, $meta );
        }

        $new_url = trailingslashit( $uploads['baseurl'] ) . $new_relative;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update( $wpdb->posts, [ 'guid' => $new_url ], [ 'ID' => $attach_id ], [ '%s' ], [ '%d' ] );

        wp_send_json_success( [ 'filename' => $new_filename, 'new_url' => $new_url ] );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Returns true if the filename looks like a camera default or non-descriptive name.
     *
     * Matches patterns such as: IMG_1234, DSC00001, screenshot2, image, photo-3,
     * untitled, file, bg, banner, header, attachment, download.
     *
     * @since 4.19.145
     * @param string $filename Basename of the attachment file.
     * @return bool
     */
    /**
     * Truncates a post slug to at most 5 hyphen-separated words for use as a filename.
     * e.g. "the-courage-to-be-honest-why-giving-hard-feedback-..." → "the-courage-to-be-honest"
     */
    private function imgseo_short_slug( string $slug ): string {
        $parts = explode( '-', $slug );
        return implode( '-', array_slice( $parts, 0, 5 ) );
    }

    private function imgseo_is_bad_filename( string $filename ): bool {
        $name = strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );
        // Camera defaults: IMG_1234, DSC00001, DCIM_5, etc.
        if ( preg_match( '/^(img|image|dsc|dcim|screenshot|photo|pic|untitled|file|attachment|download|banner|header|bg|background)[\s_\-]?\d*$/i', $name ) ) {
            return true;
        }
        // WhatsApp: whatsapp-image-2024-01-01-at-12.00.00, whatsapp image 2024...
        if ( preg_match( '/^whatsapp[\s_\-]/i', $name ) ) {
            return true;
        }
        // Telegram: photo_2024-01-01_12-00-00
        if ( preg_match( '/^(photo|video|document)_\d{4}[\-_]\d{2}/i', $name ) ) {
            return true;
        }
        // Pure numeric: 12345678
        if ( preg_match( '/^\d+$/', $name ) ) {
            return true;
        }
        // iOS/Android share dumps: image001, image (1), img001
        if ( preg_match( '/^(img|image)\s*[\(\[]\d+[\)\]]$/i', $name ) ) {
            return true;
        }
        return false;
    }
}
