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
 * @since   4.19.143
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
     * @since 4.19.143
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
            if ( $fname && $this->imgseo_is_bad_filename( $fname ) ) {
                $issues[] = 'bad_filename';
                $bad_filename++;
            }
            if ( $fsize > 500 * 1024 ) {
                $issues[] = 'large_file';
                $large_file++;
            }

            if ( empty( $issues ) ) continue;

            $results[] = [
                'id'           => $id,
                'filename'     => $fname,
                'filesize'     => $fsize,
                'alt'          => $alt,
                'parent_title' => $parent_title,
                'parent_edit'  => $parent_edit,
                'thumb_url'    => (string) ( wp_get_attachment_image_url( $id, 'thumbnail' ) ?: '' ),
                'edit_url'     => (string) ( get_edit_post_link( $id, 'raw' ) ?: '' ),
                'issues'       => $issues,
            ];
        }

        // Most issues first.
        usort( $results, static fn( $a, $b ) => count( $b['issues'] ) - count( $a['issues'] ) );

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
    // Helpers
    // =========================================================================

    /**
     * Returns true if the filename looks like a camera default or non-descriptive name.
     *
     * Matches patterns such as: IMG_1234, DSC00001, screenshot2, image, photo-3,
     * untitled, file, bg, banner, header, attachment, download.
     *
     * @since 4.19.143
     * @param string $filename Basename of the attachment file.
     * @return bool
     */
    private function imgseo_is_bad_filename( string $filename ): bool {
        $name = strtolower( pathinfo( $filename, PATHINFO_FILENAME ) );
        return (bool) preg_match(
            '/^(img|image|dsc|screenshot|photo|pic|untitled|file|attachment|download|banner|header|bg|background)[\s_\-]?\d*$/i',
            $name
        );
    }
}
