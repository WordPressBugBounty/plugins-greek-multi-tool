<?php
/**
 * Core transliteration functions for Greek Multi Tool.
 *
 * Contains the main Greek-to-Latin character mapping, ACF context detection,
 * sanitize_title callback, and media file name sanitizer.
 *
 * @since    1.0.0
 * @since    3.3.0 Added ACF compatibility, media file name support, and shared helpers.
 * @package  Grmlt_Plugin
 */

/**
 * Check if the current context is an ACF (Advanced Custom Fields) internal operation.
 *
 * ACF stores field definitions as custom post types (acf-field, acf-field-group).
 * When ACF generates field names from labels, it calls sanitize_title() internally.
 * This function detects those contexts so transliteration can be skipped, preventing
 * ACF field keys from being converted to Greeklish.
 *
 * @since 3.3.0
 * @return bool True if we're inside an ACF operation, false otherwise.
 */
function grmlt_is_acf_context() {

	// If ACF is not active, no need to check further
	if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf' ) ) {
		return false;
	}

	// Check for ACF AJAX actions (field saving, field group updates, etc.)
	if ( wp_doing_ajax() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- We're only reading, not processing
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( strpos( $action, 'acf/' ) === 0 || strpos( $action, 'acf_' ) === 0 ) {
			return true;
		}
	}

	// Check if the post being saved is an ACF internal post type
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- We're only reading, not processing
	if ( isset( $_POST['post_type'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ) );
		if ( strpos( $post_type, 'acf-' ) === 0 ) {
			return true;
		}
	}

	// Check the current admin screen for ACF post types
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && ! empty( $screen->post_type ) && strpos( $screen->post_type, 'acf-' ) === 0 ) {
			return true;
		}
	}

	return false;

}

/**
 * Get the Greek-to-Latin character expression map.
 *
 * @since 3.3.0
 * @return array Associative array of regex patterns to Latin replacements.
 */
function grmlt_get_greek_expressions() {

	return array(

		'/[αάΑΆ]/u'   => 'a',
		'/[βΒ]/u'     => 'v',
		'/[γΓ]/u'     => 'g',
		'/[δΔ]/u'     => 'd',
		'/[εέΕΈ]/u'   => 'e',
		'/[ζΖ]/u'     => 'z',
		'/[ηήΗΉ]/u'   => 'i',
		'/[θΘ]/u'     => 'th',
		'/[ιίϊΙΊΪ]/u' => 'i',
		'/[κΚ]/u'     => 'k',
		'/[λΛ]/u'     => 'l',
		'/[μΜ]/u'     => 'm',
		'/[νΝ]/u'     => 'n',
		'/[ξΞ]/u'     => 'x',
		'/[οόΟΌ]/u'   => 'o',
		'/[πΠ]/u'     => 'p',
		'/[ρΡ]/u'     => 'r',
		'/[σςΣ]/u'    => 's',
		'/[τΤ]/u'     => 't',
		'/[υύϋΥΎΫ]/u' => 'y',
		'/[φΦ]/iu'    => 'f',
		'/[χΧ]/u'     => 'ch',
		'/[ψΨ]/u'     => 'ps',
		'/[ωώ]/iu'    => 'o',

	);

}

/**
 * Core transliteration function - converts Greek characters to Latin.
 * This is a pure transliteration helper without any context/stop-word checks.
 * Used by the file name sanitizer and can be reused by other features.
 *
 * @since 3.3.0
 * @param string $text The text to transliterate.
 * @return string The transliterated text.
 */
function grmlt_transliterate_greek($text) {

	// Apply diphthongs first (based on settings)
	$diphthong_mode = get_option( 'grmlt_diphthongs' );
	if ( $diphthong_mode === 'simple' ) {
		$text = grmlt_apply_diphthongs_simple( $text );
	} else {
		$text = grmlt_apply_diphthongs_advanced( $text );
	}

	// Apply individual character transliteration
	$expressions = grmlt_get_greek_expressions();
	$text = preg_replace( array_keys($expressions), array_values($expressions), $text );

	return $text;

}

/**
 * sanitize_title callback - main Greek-to-Latin character conversion.
 * Processes individual Greek characters after diphthongs have been handled.
 *
 * @since 1.0.0
 * @since 3.3.0 Added $raw_title and $context parameters for ACF compatibility.
 *
 * @param string $text      The sanitized title.
 * @param string $raw_title The title prior to sanitization.
 * @param string $context   The context for which the title is being sanitized.
 * @return string The transliterated text.
 */
function grmlt_title_sanitizer($text, $raw_title = '', $context = 'save') {

	// Only transliterate when saving slugs, not for display or query contexts
	if ( $context !== 'save' ) {
		return $text;
	}

	// Skip transliteration for ACF internal operations
	if ( grmlt_is_acf_context() ) {
		return $text;
	}

    	$expressions = grmlt_get_greek_expressions();

    	// Stop Words functionality
    	$stop_words = get_option( 'grmlt_stwords' );
        if ( isset( $stop_words ) &&  $stop_words != '') {
            $stop_words = explode( ',' , $stop_words );
            $text = str_replace( $stop_words, '', $text );
        }

    	$text = preg_replace( array_keys($expressions), array_values($expressions), $text );

    	// Remove one letter words
        if ( get_option( 'grmlt_one_letter_words' ) == 1 ) {
            $text = preg_replace('/\s+\D{1}(?!\S)|(?<!\S)\D{1}\s+/', '', $text);
        }

        // Remove two letter words
        if ( get_option( 'grmlt_two_letter_words' ) == 1 ) {
            $text = preg_replace('/\s+\D{2}(?!\S)|(?<!\S)\D{2}\s+/', '', $text);
        }

    	return $text;

}

/**
 * sanitize_file_name callback - converts Greek characters in uploaded media file names.
 * Only processes the file name portion (not the extension).
 *
 * @since 3.3.0
 * @param string $filename The file name to sanitize.
 * @return string The sanitized file name with Greek characters converted to Latin.
 */
function grmlt_file_name_sanitizer($filename) {

	// Separate file name and extension
	$file_info = pathinfo( $filename );
	$name = isset( $file_info['filename'] ) ? $file_info['filename'] : '';
	$ext  = isset( $file_info['extension'] ) ? '.' . $file_info['extension'] : '';

	// Only process if the name contains Greek characters
	if ( ! preg_match( '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u', $name ) ) {
		return $filename;
	}

	// Apply full Greek-to-Latin transliteration (diphthongs + individual characters)
	$name = grmlt_transliterate_greek( $name );

	return $name . $ext;

}