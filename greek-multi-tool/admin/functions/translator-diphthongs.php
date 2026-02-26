<?php
/**
 * Diphthong transliteration functions for Greek Multi Tool.
 *
 * Contains both reusable helper functions for pure transliteration
 * and sanitize_title callbacks with context checking.
 *
 * @since    1.0.0
 * @since    3.3.0 Refactored for ACF compatibility and media support.
 * @package  Grmlt_Plugin
 */

/**
 * Pure diphthong transliteration - simple mode.
 * Reusable helper used by both sanitize_title callback and file name sanitizer.
 *
 * @since 3.3.0
 * @param string $text The text to transliterate.
 * @return string The transliterated text.
 */
function grmlt_apply_diphthongs_simple($text) {

	$diphthongs = array(

		'/[αΑ][ἰἱἸἹἴἵἼἽῖἶἷἾἿἲἳἺἻὶιίΙΊ]/u'                        => 'ai',
		'/[οΟ][ἰἱἸἹἴἵἼἽῖἶἷἾἿἲἳἺἻὶιίΙΊ]/u'                        => 'oi',
		'/[Εε][ἰἱἸἹἴἵἼἽῖἶἷἾἿἲἳἺἻὶιίΙΊ]/u'                        => 'ei',
		'/[αΑ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυύΥΎ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u' => 'af$1',
		'/[αΑ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυύΥΎ]/u'                             => 'av',
		'/[εΕ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυύΥΎ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u' => 'ef$1',
		'/[εΕ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυύΥΎ]/u'                             => 'ev',
		'/[οΟ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυύΥΎ]/u'                             => 'ou',
		'/(^|\s)[μΜ][πΠ]/u'                         			 => '$1mp',
		'/[μΜ][πΠ](\s|$)/u'                         			 => 'mp$1',
		'/[μΜ][πΠ]/u'                               			 => 'mp',
		'/[νΝ][τΤ]/u'                               			 => 'nt',
		'/[τΤ][σΣ]/u'                               			 => 'ts',
		'/[τΤ][ζΖ]/u'                               			 => 'tz',
		'/[γΓ][γΓ]/u'                               			 => 'ng',
		'/[γΓ][κΚ]/u'                               			 => 'gk',
		'/[ηΗ][ὐὑὙὔὕὝῦὖὗὒὓὛὺυΥ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u'   => 'if$1',
		'/[ηΗ][υΥ]/u'                               			 => 'iu',
	);

	$text = preg_replace( array_keys($diphthongs), array_values($diphthongs), $text );
	return $text;

}

/**
 * Pure diphthong transliteration - advanced mode.
 * Reusable helper used by both sanitize_title callback and file name sanitizer.
 *
 * @since 3.3.0
 * @param string $text The text to transliterate.
 * @return string The transliterated text.
 */
function grmlt_apply_diphthongs_advanced($text) {

	$diphthongs = array(

		'/[αΑ][ιίΙΊ]/u'                             => 'e',
		'/[οΟΕε][ιίΙΊ]/u'                           => 'i',
		'/[αΑ][υύΥΎ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u' => 'af$1',
		'/[αΑ][υύΥΎ]/u'                             => 'av',
		'/[εΕ][υύΥΎ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u' => 'ef$1',
		'/[εΕ][υύΥΎ]/u'                             => 'ev',
		'/[οΟ][υύΥΎ]/u'                             => 'ou',
		'/(^|\s)[μΜ][πΠ]/u'                         => '$1b',
		'/[μΜ][πΠ](\s|$)/u'                         => 'b$1',
		'/[μΜ][πΠ]/u'                               => 'b',
		'/[νΝ][τΤ]/u'                               => 'nt',
		'/[τΤ][σΣ]/u'                               => 'ts',
		'/[τΤ][ζΖ]/u'                               => 'tz',
		'/[γΓ][γΓ]/u'                               => 'ng',
		'/[γΓ][κΚ]/u'                               => 'gk',
		'/[ηΗ][υΥ]([θΘκΚξΞπΠσςΣτTφΡχΧψΨ]|\s|$)/u'   => 'if$1',
		'/[ηΗ][υΥ]/u'                               => 'iu',
	);

	$text = preg_replace( array_keys($diphthongs), array_values($diphthongs), $text );
	return $text;

}

/**
 * sanitize_title callback - simple diphthong mode.
 * Accepts all 3 sanitize_title arguments to check context and skip ACF operations.
 *
 * @since 1.0.0
 * @since 3.3.0 Added $raw_title and $context parameters for ACF compatibility.
 *
 * @param string $text      The sanitized title.
 * @param string $raw_title The title prior to sanitization.
 * @param string $context   The context for which the title is being sanitized.
 * @return string The transliterated text.
 */
function grmlt_title_sanitizer_diphthongs_simple($text, $raw_title = '', $context = 'save') {

	// Only transliterate when saving slugs
	if ( $context !== 'save' ) {
		return $text;
	}

	// Skip transliteration for ACF internal operations
	if ( grmlt_is_acf_context() ) {
		return $text;
	}

	return grmlt_apply_diphthongs_simple($text);

}

/**
 * sanitize_title callback - advanced diphthong mode.
 * Accepts all 3 sanitize_title arguments to check context and skip ACF operations.
 *
 * @since 1.0.0
 * @since 3.3.0 Added $raw_title and $context parameters for ACF compatibility.
 *
 * @param string $text      The sanitized title.
 * @param string $raw_title The title prior to sanitization.
 * @param string $context   The context for which the title is being sanitized.
 * @return string The transliterated text.
 */
function grmlt_title_sanitizer_diphthongs_advanced($text, $raw_title = '', $context = 'save') {

	// Only transliterate when saving slugs
	if ( $context !== 'save' ) {
		return $text;
	}

	// Skip transliteration for ACF internal operations
	if ( grmlt_is_acf_context() ) {
		return $text;
	}

	return grmlt_apply_diphthongs_advanced($text);

}