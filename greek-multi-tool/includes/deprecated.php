<?php
/**
 * Shims for public functions of removed features, kept so themes that call
 * them keep working.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'grmlt_format_greek_date' ) ) :
	/**
	 * Formats a date with Greek month and day names.
	 *
	 * @deprecated 4.0.0 Use wp_date() with the site language set to Greek.
	 *
	 * @param string     $format Date format. Default: the site date format.
	 * @param int|string $date   Timestamp or MySQL date. Default: now.
	 * @return string
	 */
	function grmlt_format_greek_date( $format = '', $date = '' ) {
		_deprecated_function( __FUNCTION__, '4.0.0', 'wp_date()' );

		$format = is_string( $format ) ? $format : '';
		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'j F Y' );
			if ( '' === $format ) {
				$format = 'j F Y';
			}
		}
		if ( null === $date || '' === $date || false === $date ) {
			$timestamp = time();
		} elseif ( is_numeric( $date ) ) {
			$timestamp = (int) $date;
		} else {
			$timestamp = strtotime( (string) $date );
			if ( false === $timestamp ) {
				$timestamp = time();
			}
		}

		$switched = false;
		if ( 0 !== strpos( get_locale(), 'el' ) ) {
			$switched = switch_to_locale( 'el' );
		}
		$formatted = wp_date( $format, $timestamp );
		if ( $switched ) {
			restore_previous_locale();
		}
		return (string) $formatted;
	}
endif;

if ( ! function_exists( 'grmlt_greek_excerpt' ) ) :
	/**
	 * Creates an excerpt from text (page builder markup removed).
	 *
	 * @deprecated 4.0.0 Use wp_trim_words().
	 *
	 * @param string   $text   Text.
	 * @param int|null $length Words. Default 55.
	 * @param string   $more   Appended when trimmed. Default "&hellip;".
	 * @return string
	 */
	function grmlt_greek_excerpt( $text, $length = null, $more = null ) {
		_deprecated_function( __FUNCTION__, '4.0.0', 'wp_trim_words()' );

		$length = null === $length ? 55 : (int) $length;
		$more   = null === $more ? '&hellip;' : (string) $more;
		return wp_trim_words( grmlt_extract_clean_text( (string) $text, 0, false ), $length, $more );
	}
endif;
