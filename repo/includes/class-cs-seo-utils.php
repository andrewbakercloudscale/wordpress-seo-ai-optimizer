<?php
/**
 * Shared utility helpers for CloudScale SEO AI Optimizer.
 *
 * All helpers used in more than one class or trait belong here.
 * Call Cs_Seo_Utils::method_name() from any context that needs them.
 *
 * Note: the plugin's primary shared functionality is organised into focused traits
 * (CS_SEO_Options, CS_SEO_AI_Engine, etc.). This class holds stateless helpers
 * that do not belong to any single trait domain.
 *
 * @package Cs_Seo_Plugin
 * @since   4.15.5
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Stateless utility helpers.
 *
 * @since 4.15.5
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- class name IS prefixed with the plugin slug
class Cs_Seo_Utils {

	/**
	 * Logs a debug message when WP_DEBUG_LOG is enabled.
	 *
	 * Never call error_log() directly elsewhere — use this method.
	 *
	 * @since  4.15.5
	 * @param  string $message The message to log.
	 * @param  mixed  $context Optional context data to JSON-encode alongside the message.
	 * @return void
	 */
	public static function log( string $message, mixed $context = null ): void {
		if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
			return;
		}
		$entry = '[CloudScale SEO] ' . $message;
		if ( null !== $context ) {
			$entry .= ' | ' . wp_json_encode( $context );
		}
		error_log( $entry ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Returns a sanitised integer from a superglobal array.
	 *
	 * @since  4.15.5
	 * @param  array<string,mixed> $source  Superglobal ($_POST, $_GET, etc.).
	 * @param  string              $key     Key to retrieve.
	 * @param  int                 $default Default value if the key is absent.
	 * @return int
	 */
	public static function get_int( array $source, string $key, int $default = 0 ): int {
		return isset( $source[ $key ] ) ? absint( $source[ $key ] ) : $default;
	}

	/**
	 * Returns a sanitised plain-text string from a superglobal array.
	 *
	 * @since  4.15.5
	 * @param  array<string,mixed> $source  Superglobal ($_POST, $_GET, etc.).
	 * @param  string              $key     Key to retrieve.
	 * @param  string              $default Default value if the key is absent.
	 * @return string
	 */
	public static function get_text( array $source, string $key, string $default = '' ): string {
		return isset( $source[ $key ] )
			? sanitize_text_field( wp_unslash( (string) $source[ $key ] ) )
			: $default;
	}

	/**
	 * Strips HTML tags and decodes entities to produce a plain-text excerpt.
	 *
	 * Used by AI scoring and meta description generation to extract content text
	 * without running through the full WP content filters.
	 *
	 * @since  4.15.5
	 * @param  string $html     Raw HTML content.
	 * @param  int    $max_chars Maximum characters to return. 0 = no limit.
	 * @return string
	 */
	public static function plain_text( string $html, int $max_chars = 0 ): string {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );
		if ( $max_chars > 0 && mb_strlen( $text ) > $max_chars ) {
			$text = mb_substr( $text, 0, $max_chars );
		}
		return $text;
	}

	/**
	 * Strips shortcodes and HTML tags from raw post content, collapsing whitespace.
	 *
	 * Used across multiple traits to extract plain text for AI prompts and meta
	 * description generation without running full WP content filters.
	 *
	 * @since  4.19.4
	 * @param  string $raw Raw post content (may contain shortcodes and HTML).
	 * @return string Plain text with normalised whitespace.
	 */
	public static function text_from_html( string $raw ): string {
		$raw = strip_shortcodes( $raw );
		$raw = wp_strip_all_tags( $raw );
		return (string) preg_replace( '/\s+/', ' ', $raw );
	}
}
