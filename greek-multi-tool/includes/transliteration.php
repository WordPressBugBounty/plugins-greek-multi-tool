<?php
/**
 * The single transliteration engine.
 *
 * grmlt_slugify() is used by the sanitize_title filter (new permalinks), the
 * sanitize_file_name filter (media uploads), the bulk converter and the REST
 * preview, so every path produces identical output.
 *
 * Modes:
 *  - elot743:       ELOT 743:2001 type 2 (transcription), identical to ISO 843:1997,
 *                   as used by the UN (resolution V/19). Accents and dialytika are
 *                   dropped because URLs cannot carry them.
 *  - advanced:      "Phonetic": αι→e, ει/οι→i, μπ→b everywhere.
 *  - simple-legacy: what 3.x produced (μπ→mp everywhere, γξ/γχ→gx/gch), with
 *                   the 3.x bugs fixed (capital Τ/Φ/Ρ in the voiceless class,
 *                   ΐ/ΰ, ηυ, word boundary).
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a string contains Greek characters.
 *
 * @param string $text Text.
 * @return bool
 */
function grmlt_has_greek( $text ) {
	return (bool) preg_match( '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u', (string) $text );
}

/**
 * Unicode NFC normalization when the intl extension is available.
 *
 * @param string $text Text.
 * @return string
 */
function grmlt_normalize_unicode( $text ) {
	if ( class_exists( 'Normalizer' ) && ! Normalizer::isNormalized( $text, Normalizer::FORM_C ) ) {
		$normalized = Normalizer::normalize( $text, Normalizer::FORM_C );
		if ( false !== $normalized ) {
			return $normalized;
		}
	}
	return $text;
}

/**
 * Converts polytonic Greek to monotonic: breathings, iota subscript, macron
 * and vrachy are dropped, varia and perispomeni become tonos, dialytika stay.
 * Requires the intl extension; without it polytonic text is left as is.
 *
 * @param string $text Text.
 * @return string
 */
function grmlt_monotonize( $text ) {
	if ( ! preg_match( '/[\x{1F00}-\x{1FFF}]/u', $text ) ) {
		return $text;
	}
	if ( ! class_exists( 'Normalizer' ) ) {
		// Without intl: the same result for precomposed characters, from a
		// table generated with the code below.
		return strtr( $text, grmlt_polytonic_map() );
	}
	$decomposed = Normalizer::normalize( $text, Normalizer::FORM_D );
	if ( false === $decomposed ) {
		return $text;
	}
	$decomposed = str_replace( array( "\u{0300}", "\u{0342}" ), "\u{0301}", $decomposed );
	$decomposed = preg_replace( '/[\x{0313}\x{0314}\x{0345}\x{0304}\x{0306}]/u', '', $decomposed );
	if ( null === $decomposed ) {
		return $text;
	}
	$composed = Normalizer::normalize( $decomposed, Normalizer::FORM_C );
	return false === $composed ? $text : $composed;
}

/**
 * Polytonic to monotonic for precomposed characters, used when the intl
 * extension is missing. Generated with the algorithm of grmlt_monotonize()
 * (dev/tests/r2-gen-polytonic.php); spacing breathings and accents map to ''.
 *
 * @return array<string, string>
 */
function grmlt_polytonic_map() {
	static $map = array(
		'ἀ' => 'α',
		'ἁ' => 'α',
		'ἂ' => 'ά',
		'ἃ' => 'ά',
		'ἄ' => 'ά',
		'ἅ' => 'ά',
		'ἆ' => 'ά',
		'ἇ' => 'ά',
		'Ἀ' => 'Α',
		'Ἁ' => 'Α',
		'Ἂ' => 'Ά',
		'Ἃ' => 'Ά',
		'Ἄ' => 'Ά',
		'Ἅ' => 'Ά',
		'Ἆ' => 'Ά',
		'Ἇ' => 'Ά',
		'ἐ' => 'ε',
		'ἑ' => 'ε',
		'ἒ' => 'έ',
		'ἓ' => 'έ',
		'ἔ' => 'έ',
		'ἕ' => 'έ',
		'Ἐ' => 'Ε',
		'Ἑ' => 'Ε',
		'Ἒ' => 'Έ',
		'Ἓ' => 'Έ',
		'Ἔ' => 'Έ',
		'Ἕ' => 'Έ',
		'ἠ' => 'η',
		'ἡ' => 'η',
		'ἢ' => 'ή',
		'ἣ' => 'ή',
		'ἤ' => 'ή',
		'ἥ' => 'ή',
		'ἦ' => 'ή',
		'ἧ' => 'ή',
		'Ἠ' => 'Η',
		'Ἡ' => 'Η',
		'Ἢ' => 'Ή',
		'Ἣ' => 'Ή',
		'Ἤ' => 'Ή',
		'Ἥ' => 'Ή',
		'Ἦ' => 'Ή',
		'Ἧ' => 'Ή',
		'ἰ' => 'ι',
		'ἱ' => 'ι',
		'ἲ' => 'ί',
		'ἳ' => 'ί',
		'ἴ' => 'ί',
		'ἵ' => 'ί',
		'ἶ' => 'ί',
		'ἷ' => 'ί',
		'Ἰ' => 'Ι',
		'Ἱ' => 'Ι',
		'Ἲ' => 'Ί',
		'Ἳ' => 'Ί',
		'Ἴ' => 'Ί',
		'Ἵ' => 'Ί',
		'Ἶ' => 'Ί',
		'Ἷ' => 'Ί',
		'ὀ' => 'ο',
		'ὁ' => 'ο',
		'ὂ' => 'ό',
		'ὃ' => 'ό',
		'ὄ' => 'ό',
		'ὅ' => 'ό',
		'Ὀ' => 'Ο',
		'Ὁ' => 'Ο',
		'Ὂ' => 'Ό',
		'Ὃ' => 'Ό',
		'Ὄ' => 'Ό',
		'Ὅ' => 'Ό',
		'ὐ' => 'υ',
		'ὑ' => 'υ',
		'ὒ' => 'ύ',
		'ὓ' => 'ύ',
		'ὔ' => 'ύ',
		'ὕ' => 'ύ',
		'ὖ' => 'ύ',
		'ὗ' => 'ύ',
		'Ὑ' => 'Υ',
		'Ὓ' => 'Ύ',
		'Ὕ' => 'Ύ',
		'Ὗ' => 'Ύ',
		'ὠ' => 'ω',
		'ὡ' => 'ω',
		'ὢ' => 'ώ',
		'ὣ' => 'ώ',
		'ὤ' => 'ώ',
		'ὥ' => 'ώ',
		'ὦ' => 'ώ',
		'ὧ' => 'ώ',
		'Ὠ' => 'Ω',
		'Ὡ' => 'Ω',
		'Ὢ' => 'Ώ',
		'Ὣ' => 'Ώ',
		'Ὤ' => 'Ώ',
		'Ὥ' => 'Ώ',
		'Ὦ' => 'Ώ',
		'Ὧ' => 'Ώ',
		'ὰ' => 'ά',
		'ά' => 'ά',
		'ὲ' => 'έ',
		'έ' => 'έ',
		'ὴ' => 'ή',
		'ή' => 'ή',
		'ὶ' => 'ί',
		'ί' => 'ί',
		'ὸ' => 'ό',
		'ό' => 'ό',
		'ὺ' => 'ύ',
		'ύ' => 'ύ',
		'ὼ' => 'ώ',
		'ώ' => 'ώ',
		'ᾀ' => 'α',
		'ᾁ' => 'α',
		'ᾂ' => 'ά',
		'ᾃ' => 'ά',
		'ᾄ' => 'ά',
		'ᾅ' => 'ά',
		'ᾆ' => 'ά',
		'ᾇ' => 'ά',
		'ᾈ' => 'Α',
		'ᾉ' => 'Α',
		'ᾊ' => 'Ά',
		'ᾋ' => 'Ά',
		'ᾌ' => 'Ά',
		'ᾍ' => 'Ά',
		'ᾎ' => 'Ά',
		'ᾏ' => 'Ά',
		'ᾐ' => 'η',
		'ᾑ' => 'η',
		'ᾒ' => 'ή',
		'ᾓ' => 'ή',
		'ᾔ' => 'ή',
		'ᾕ' => 'ή',
		'ᾖ' => 'ή',
		'ᾗ' => 'ή',
		'ᾘ' => 'Η',
		'ᾙ' => 'Η',
		'ᾚ' => 'Ή',
		'ᾛ' => 'Ή',
		'ᾜ' => 'Ή',
		'ᾝ' => 'Ή',
		'ᾞ' => 'Ή',
		'ᾟ' => 'Ή',
		'ᾠ' => 'ω',
		'ᾡ' => 'ω',
		'ᾢ' => 'ώ',
		'ᾣ' => 'ώ',
		'ᾤ' => 'ώ',
		'ᾥ' => 'ώ',
		'ᾦ' => 'ώ',
		'ᾧ' => 'ώ',
		'ᾨ' => 'Ω',
		'ᾩ' => 'Ω',
		'ᾪ' => 'Ώ',
		'ᾫ' => 'Ώ',
		'ᾬ' => 'Ώ',
		'ᾭ' => 'Ώ',
		'ᾮ' => 'Ώ',
		'ᾯ' => 'Ώ',
		'ᾰ' => 'α',
		'ᾱ' => 'α',
		'ᾲ' => 'ά',
		'ᾳ' => 'α',
		'ᾴ' => 'ά',
		'ᾶ' => 'ά',
		'ᾷ' => 'ά',
		'Ᾰ' => 'Α',
		'Ᾱ' => 'Α',
		'Ὰ' => 'Ά',
		'Ά' => 'Ά',
		'ᾼ' => 'Α',
		'᾽' => '',
		'ι' => 'ι',
		'᾿' => '',
		'῀' => '',
		'῁' => '΅',
		'ῂ' => 'ή',
		'ῃ' => 'η',
		'ῄ' => 'ή',
		'ῆ' => 'ή',
		'ῇ' => 'ή',
		'Ὲ' => 'Έ',
		'Έ' => 'Έ',
		'Ὴ' => 'Ή',
		'Ή' => 'Ή',
		'ῌ' => 'Η',
		'῍' => '',
		'῎' => '',
		'῏' => '',
		'ῐ' => 'ι',
		'ῑ' => 'ι',
		'ῒ' => 'ΐ',
		'ΐ' => 'ΐ',
		'ῖ' => 'ί',
		'ῗ' => 'ΐ',
		'Ῐ' => 'Ι',
		'Ῑ' => 'Ι',
		'Ὶ' => 'Ί',
		'Ί' => 'Ί',
		'῝' => '',
		'῞' => '',
		'῟' => '',
		'ῠ' => 'υ',
		'ῡ' => 'υ',
		'ῢ' => 'ΰ',
		'ΰ' => 'ΰ',
		'ῤ' => 'ρ',
		'ῥ' => 'ρ',
		'ῦ' => 'ύ',
		'ῧ' => 'ΰ',
		'Ῠ' => 'Υ',
		'Ῡ' => 'Υ',
		'Ὺ' => 'Ύ',
		'Ύ' => 'Ύ',
		'Ῥ' => 'Ρ',
		'῭' => '΅',
		'΅' => '΅',
		'`' => '',
		'ῲ' => 'ώ',
		'ῳ' => 'ω',
		'ῴ' => 'ώ',
		'ῶ' => 'ώ',
		'ῷ' => 'ώ',
		'Ὸ' => 'Ό',
		'Ό' => 'Ό',
		'Ὼ' => 'Ώ',
		'Ώ' => 'Ώ',
		'ῼ' => 'Ω',
		'´' => '',
		'῾' => '',
	);
	return $map;
}

/**
 * Removes tonos and dialytika from Greek letters.
 *
 * @param string $text Text.
 * @return string
 */
function grmlt_strip_greek_accents( $text ) {
	static $map = array(
		'ά' => 'α',
		'έ' => 'ε',
		'ή' => 'η',
		'ί' => 'ι',
		'ό' => 'ο',
		'ύ' => 'υ',
		'ώ' => 'ω',
		'ϊ' => 'ι',
		'ϋ' => 'υ',
		'ΐ' => 'ι',
		'ΰ' => 'υ',
		'Ά' => 'Α',
		'Έ' => 'Ε',
		'Ή' => 'Η',
		'Ί' => 'Ι',
		'Ό' => 'Ο',
		'Ύ' => 'Υ',
		'Ώ' => 'Ω',
		'Ϊ' => 'Ι',
		'Ϋ' => 'Υ',
	);
	return strtr( $text, $map );
}

/**
 * Comparison key for a word: NFC, monotonic, no accents, lower case, no final sigma.
 *
 * @param string $word Word.
 * @return string
 */
function grmlt_word_key( $word ) {
	$word = grmlt_monotonize( grmlt_normalize_unicode( (string) $word ) );
	$word = grmlt_lower( grmlt_strip_greek_accents( $word ) );
	return str_replace( 'ς', 'σ', trim( $word ) );
}

/**
 * Removes stop words and short words from text, before transliteration.
 *
 * Only words that contain Greek letters are ever removed: Latin words,
 * numbers and percent-encoded slugs pass through untouched, so running the
 * filter over an existing slug can never change it. Words are runs of
 * letters not attached to digits, so "10ος" is never touched. Matching is
 * case- and accent-insensitive.
 *
 * If removing words would leave no letter or digit at all (a title made only
 * of excluded words), the text is returned unchanged.
 *
 * @param string   $text       Text.
 * @param string[] $stopwords  Stop words.
 * @param bool     $one_letter Remove one-letter words.
 * @param bool     $two_letter Remove two-letter words.
 * @return string
 */
function grmlt_remove_words( $text, $stopwords, $one_letter, $two_letter ) {
	$set = array();
	foreach ( (array) $stopwords as $word ) {
		$key = grmlt_word_key( $word );
		if ( '' !== $key ) {
			$set[ $key ] = true;
		}
	}
	if ( empty( $set ) && ! $one_letter && ! $two_letter ) {
		return $text;
	}

	$result = preg_replace_callback(
		'/(?<![\p{L}\p{M}\p{N}])[\p{L}\p{M}]+(?![\p{L}\p{M}\p{N}])/u',
		function ( $m ) use ( $set, $one_letter, $two_letter ) {
			$word = $m[0];
			if ( ! preg_match( '/\p{Greek}/u', $word ) ) {
				return $word;
			}
			$length = mb_strlen( (string) preg_replace( '/\p{M}/u', '', $word ), 'UTF-8' );
			if ( ( $one_letter && 1 === $length ) || ( $two_letter && 2 === $length ) ) {
				return ' ';
			}
			if ( isset( $set[ grmlt_word_key( $word ) ] ) ) {
				return ' ';
			}
			return $word;
		},
		$text
	);
	if ( null === $result || ! preg_match( '/[\p{L}\p{N}]/u', $result ) ) {
		return $text;
	}
	return $result;
}

/**
 * pre_get_terms action: keeps Greek slug arguments of a term query intact.
 *
 * WP_Term_Query::get_terms() runs every requested slug through
 * sanitize_title() in the 'save' context (after pre_get_terms, before
 * terms_clauses). Transliterating there would turn a lookup for a stored
 * Greek slug into a lookup for a Latin one, which breaks every term archive
 * that has not been converted yet. So the Greek slugs are sanitized here
 * the way core does it without this plugin (percent-encoded, lower case,
 * exactly how they are stored); the later sanitize_title() call then sees
 * no Greek and leaves them alone.
 *
 * @param WP_Term_Query $query Term query (by reference).
 */
function grmlt_term_query_keep_greek_slugs( $query ) {
	if ( ! is_object( $query ) || empty( $query->query_vars['slug'] ) ) {
		return;
	}
	$slugs   = $query->query_vars['slug'];
	$is_list = is_array( $slugs );
	$changed = false;
	foreach ( (array) $slugs as $key => $slug ) {
		if ( is_string( $slug ) && grmlt_has_greek( $slug ) ) {
			$encoded = grmlt_core_sanitize_title( $slug );
			if ( $is_list ) {
				$slugs[ $key ] = $encoded;
			} else {
				$slugs = $encoded;
			}
			$changed = true;
		}
	}
	if ( $changed ) {
		$query->query_vars['slug'] = $slugs;
	}
}

/**
 * Re-entrant switch that makes grmlt_title_sanitizer() stand aside. Used
 * instead of removing and re-adding the filter, which would move the
 * callback to the end of its priority and could leave it removed if
 * sanitize_title() threw.
 *
 * @param int $delta +1 to enter, -1 to leave, 0 to read.
 * @return bool Whether transliteration is suspended.
 */
function grmlt_transliteration_suspended( $delta = 0 ) {
	static $depth = 0;
	$depth = max( 0, $depth + (int) $delta );
	return $depth > 0;
}

/**
 * sanitize_title() as core (and other plugins) would run it without this
 * plugin's transliteration filter.
 *
 * @param string $title Raw title or slug.
 * @return string
 */
function grmlt_core_sanitize_title( $title ) {
	grmlt_transliteration_suspended( 1 );
	try {
		return sanitize_title( $title );
	} finally {
		grmlt_transliteration_suspended( -1 );
	}
}

/**
 * What sanitize_term() is working on, learnt from the fields it sanitizes
 * before the slug (term_id, then name): a save of an existing term, a save
 * of a new term, or (slug only) a lookup.
 *
 * @param string|null $field 'term_id', 'name', or null to read and reset.
 * @param mixed       $value Field value.
 * @return array{saving:bool, term_id:int}
 */
function grmlt_term_save_context( $field = null, $value = null ) {
	static $context = array(
		'saving'  => false,
		'term_id' => 0,
	);
	if ( 'term_id' === $field ) {
		$context = array(
			'saving'  => true,
			'term_id' => (int) $value,
		);
	} elseif ( 'name' === $field ) {
		$context['saving'] = true;
	} elseif ( null === $field ) {
		$current = $context;
		$context = array(
			'saving'  => false,
			'term_id' => 0,
		);
		return $current;
	}
	return $context;
}

/**
 * pre_term_term_id filter: notes the term being saved.
 *
 * @param mixed $value Term id.
 * @return mixed
 */
function grmlt_note_term_id( $value ) {
	grmlt_term_save_context( 'term_id', $value );
	return $value;
}

/**
 * pre_term_name filter: notes that a term is being saved.
 *
 * @param mixed $value Name.
 * @return mixed
 */
function grmlt_note_term_name( $value ) {
	grmlt_term_save_context( 'name', $value );
	return $value;
}

/**
 * pre_term_slug filter (priority 9, right before core's sanitize_title).
 *
 * - Saving an existing term (edit form, Quick Edit, REST): a Greek slug
 *   submitted unchanged, i.e. exactly the slug that very term has, stays
 *   as it is. The forms show the stored slug decoded, so saving any field
 *   of a term whose slug was never converted would otherwise transliterate
 *   it silently. A slug the user changed is transliterated as usual.
 * - Saving a new term: always transliterated.
 * - A lookup (WP_Query looks tags up through this filter): the Greek slug
 *   stays when a term of the taxonomy has it, so unconverted Greek tags
 *   keep resolving.
 *
 * (Posts need no help: wp_insert_post() already keeps an unchanged slug.)
 *
 * @param mixed  $value    Submitted slug.
 * @param string $taxonomy Taxonomy.
 * @return mixed
 */
function grmlt_keep_existing_term_slug( $value, $taxonomy = '' ) {
	$context = grmlt_term_save_context();
	if ( ! is_string( $value ) || '' === $value || ! is_string( $taxonomy ) || '' === $taxonomy || ! grmlt_has_greek( $value ) ) {
		return $value;
	}
	$encoded = grmlt_core_sanitize_title( $value );
	if ( '' === $encoded || grmlt_has_greek( $encoded ) ) {
		return $value;
	}
	if ( $context['saving'] ) {
		if ( $context['term_id'] <= 0 ) {
			return $value; // A new term.
		}
		$term = get_term( $context['term_id'], $taxonomy );
		return ( $term instanceof WP_Term && $term->slug === $encoded ) ? $encoded : $value;
	}
	return term_exists( $encoded, $taxonomy ) ? $encoded : $value;
}

/**
 * The digraph rules of a mode, as ordered regex => replacement pairs.
 * Applied to monotonic text that still carries its accents, because the
 * αυ/ευ/ηυ rules depend on where the accent sits (ELOT 743 notes 9 and 10).
 *
 * @param string $mode Mode.
 * @return array<string, string>
 */
function grmlt_get_digraph_rules( $mode ) {
	static $cache = array();
	if ( isset( $cache[ $mode ] ) ) {
		return $cache[ $mode ];
	}

	$vowels    = 'αάεέηήιίϊΐοόυύϋΰωώΑΆΕΈΗΉΙΊΪΟΌΥΎΫΩΏ';
	$voiced    = 'βγδζλμνρΒΓΔΖΛΜΝΡ';
	$upsilon   = 'υύΥΎ'; // ϋ/ΰ excluded on purpose (note 9).
	$no_letter = '(?![\p{L}\p{M}])';
	$rules     = array();

	if ( 'advanced' === $mode ) {
		$rules['/[αΑ][ιίΙΊ]/u']   = 'e';
		$rules['/[οΟεΕ][ιίΙΊ]/u'] = 'i';
	}

	// αυ, ευ, ηυ: v before vowels and voiced consonants (note 3), f elsewhere:
	// before voiceless consonants, at the end of the word, before punctuation (note 4).
	foreach ( array( 'αΑ' => 'a', 'εΕ' => 'e', 'ηΗ' => 'i' ) as $first => $latin ) {
		$rules[ '/[' . $first . '][' . $upsilon . '](?=[' . $vowels . $voiced . '])/u' ] = $latin . 'v';
		$rules[ '/[' . $first . '][' . $upsilon . ']/u' ]                               = $latin . 'f';
	}

	// ου (note 9: όυ and οϋ are converted letter by letter, which the character map does).
	$rules[ '/[οΟ][' . $upsilon . ']/u' ] = 'ou';

	// μπ.
	if ( 'elot743' === $mode ) {
		$rules['/(?<![\p{L}\p{M}])[μΜ][πΠ]/u']    = 'b';  // Note 5: beginning of the word.
		$rules[ '/[μΜ][πΠ]' . $no_letter . '/u' ] = 'b';  // Note 7: end of the word.
		$rules['/[μΜ][πΠ]/u']                     = 'mp'; // Note 6: middle of the word.
	} elseif ( 'advanced' === $mode ) {
		$rules['/[μΜ][πΠ]/u'] = 'b';
	} else {
		$rules['/[μΜ][πΠ]/u'] = 'mp';
	}

	// γγ, γξ, γχ (γκ and ντ are letter by letter: gk, nt).
	$rules['/[γΓ][γΓ]/u'] = 'ng';
	if ( 'elot743' === $mode ) {
		$rules['/[γΓ][ξΞ]/u'] = 'nx';
		$rules['/[γΓ][χΧ]/u'] = 'nch';
	}

	$cache[ $mode ] = $rules;
	return $rules;
}

/**
 * Single-character map (applied after accents are stripped).
 *
 * @return array<string, string>
 */
function grmlt_get_character_map() {
	static $map = array(
		'α' => 'a',
		'β' => 'v',
		'γ' => 'g',
		'δ' => 'd',
		'ε' => 'e',
		'ζ' => 'z',
		'η' => 'i',
		'θ' => 'th',
		'ι' => 'i',
		'κ' => 'k',
		'λ' => 'l',
		'μ' => 'm',
		'ν' => 'n',
		'ξ' => 'x',
		'ο' => 'o',
		'π' => 'p',
		'ρ' => 'r',
		'σ' => 's',
		'ς' => 's',
		'τ' => 't',
		'υ' => 'y',
		'φ' => 'f',
		'χ' => 'ch',
		'ψ' => 'ps',
		'ω' => 'o',
		'Α' => 'a',
		'Β' => 'v',
		'Γ' => 'g',
		'Δ' => 'd',
		'Ε' => 'e',
		'Ζ' => 'z',
		'Η' => 'i',
		'Θ' => 'th',
		'Ι' => 'i',
		'Κ' => 'k',
		'Λ' => 'l',
		'Μ' => 'm',
		'Ν' => 'n',
		'Ξ' => 'x',
		'Ο' => 'o',
		'Π' => 'p',
		'Ρ' => 'r',
		'Σ' => 's',
		'Τ' => 't',
		'Υ' => 'y',
		'Φ' => 'f',
		'Χ' => 'ch',
		'Ψ' => 'ps',
		'Ω' => 'o',
	);
	return $map;
}

/**
 * Transliterates Greek text to Latin.
 *
 * @param string $text Text.
 * @param array  $args {
 *     @type string|null   $mode       Mode; default: the grmlt_diphthongs option.
 *     @type string        $context    'slug' (stop/short words removed) or 'filename'. Default 'slug'.
 *     @type string[]|null $stopwords  Stop words; default: the grmlt_stwords option.
 *     @type bool|null     $one_letter Remove one-letter words; default: option.
 *     @type bool|null     $two_letter Remove two-letter words; default: option.
 * }
 * @return string Latin text. Spacing and punctuation are left for the core sanitizers.
 */
function grmlt_slugify( $text, $args = array() ) {
	$text = (string) $text;
	if ( '' === $text ) {
		return '';
	}
	$args = wp_parse_args(
		$args,
		array(
			'mode'       => null,
			'context'    => 'slug',
			'stopwords'  => null,
			'one_letter' => null,
			'two_letter' => null,
		)
	);
	$mode = grmlt_sanitize_mode( null === $args['mode'] ? grmlt_get_option( 'grmlt_diphthongs' ) : $args['mode'] );

	// Nothing Greek (including percent-encoded Greek, which is plain ASCII)
	// means nothing to do: Latin titles, existing slugs and invalid UTF-8
	// pass through untouched.
	if ( ! grmlt_has_greek( $text ) || ! preg_match( '//u', $text ) ) {
		return $text;
	}

	$text = grmlt_monotonize( grmlt_normalize_unicode( $text ) );

	if ( 'slug' === $args['context'] ) {
		$stopwords  = null === $args['stopwords'] ? grmlt_get_stopwords() : (array) $args['stopwords'];
		$one_letter = null === $args['one_letter'] ? grmlt_is_on( 'grmlt_one_letter_words' ) : (bool) $args['one_letter'];
		$two_letter = null === $args['two_letter'] ? grmlt_is_on( 'grmlt_two_letter_words' ) : (bool) $args['two_letter'];
		$text       = grmlt_remove_words( $text, $stopwords, $one_letter, $two_letter );
	}

	$rules       = grmlt_get_digraph_rules( $mode );
	$replaced    = preg_replace( array_keys( $rules ), array_values( $rules ), $text );
	$text        = null === $replaced ? $text : $replaced;
	$text        = grmlt_strip_greek_accents( $text );
	$text        = strtr( $text, grmlt_get_character_map() );
	$text        = strtr( $text, grmlt_get_extra_character_map() );

	// Whatever is still in the Greek blocks (signs, spacing diacritics) has
	// no Latin form: drop it, so that a converted slug never counts as Greek
	// again.
	$stripped = preg_replace( '/[\x{0370}-\x{03FF}\x{1F00}-\x{1FFF}]/u', '', $text );
	return null === $stripped ? $text : $stripped;
}

/**
 * Letters of the Greek block outside the modern alphabet: letter variants
 * used in older and scholarly texts, archaic letters, and the Coptic letters
 * Unicode keeps in the Greek block. Applied after the character map.
 *
 * @return array<string, string>
 */
function grmlt_get_extra_character_map() {
	static $map = array(
		// Ano teleia (U+0387) becomes a middle dot (U+00B7) under NFC; in Greek
		// text it separates words (λέξη·λέξη), and core would otherwise keep it as %c2%b7.
		"\u{00B7}" => '-',
		"\u{0387}" => '-',
		'Ͱ' => 'h',
		'ͱ' => 'h',
		'Ͳ' => 'ss',
		'ͳ' => 'ss',
		'Ͷ' => 'w',
		'ͷ' => 'w',
		'ͻ' => 's',
		'ͼ' => 's',
		'ͽ' => 's',
		'Ϳ' => 'j',
		'Ϗ' => 'kai',
		'ϐ' => 'v',
		'ϑ' => 'th',
		'ϒ' => 'y',
		'ϓ' => 'y',
		'ϔ' => 'y',
		'ϕ' => 'f',
		'ϖ' => 'p',
		'ϗ' => 'kai',
		'Ϙ' => 'q',
		'ϙ' => 'q',
		'Ϛ' => 'st',
		'ϛ' => 'st',
		'Ϝ' => 'w',
		'ϝ' => 'w',
		'Ϟ' => 'q',
		'ϟ' => 'q',
		'Ϡ' => 'ss',
		'ϡ' => 'ss',
		'Ϣ' => 'sh',
		'ϣ' => 'sh',
		'Ϥ' => 'f',
		'ϥ' => 'f',
		'Ϧ' => 'kh',
		'ϧ' => 'kh',
		'Ϩ' => 'h',
		'ϩ' => 'h',
		'Ϫ' => 'j',
		'ϫ' => 'j',
		'Ϭ' => 'ch',
		'ϭ' => 'ch',
		'Ϯ' => 'ti',
		'ϯ' => 'ti',
		'ϰ' => 'k',
		'ϱ' => 'r',
		'ϲ' => 's',
		'ϳ' => 'j',
		'ϴ' => 'th',
		'ϵ' => 'e',
		'Ϸ' => 'sh',
		'ϸ' => 'sh',
		'Ϲ' => 's',
		'Ϻ' => 's',
		'ϻ' => 's',
		'ϼ' => 'r',
		'Ͻ' => 's',
		'Ͼ' => 's',
		'Ͽ' => 's',
	);
	return $map;
}

/**
 * The slug the bulk converter of 2.x/3.x produced for a decoded Greek slug
 * (grmlt_old_sanitizer(): one letter at a time, no digraphs, no word
 * removal). Only used to find terms that converter renamed.
 *
 * @param string $text Decoded Greek slug.
 * @return string
 */
function grmlt_legacy_old_slug( $text ) {
	static $map = null;
	if ( null === $map ) {
		$map    = array();
		$groups = array(
			'a'  => 'αάΑΆ',
			'v'  => 'βΒ',
			'g'  => 'γΓ',
			'd'  => 'δΔ',
			'e'  => 'εέΕΈ',
			'z'  => 'ζΖ',
			'i'  => 'ηήΗΉιίϊΙΊΪ',
			'th' => 'θΘ',
			'k'  => 'κΚ',
			'l'  => 'λΛ',
			'm'  => 'μΜ',
			'n'  => 'νΝ',
			'x'  => 'ξΞ',
			'o'  => 'οόΟΌωώΩΏ',
			'p'  => 'πΠ',
			'r'  => 'ρΡ',
			's'  => 'σςΣ',
			't'  => 'τΤ',
			'y'  => 'υύϋΥΎΫ',
			'f'  => 'φΦ',
			'ch' => 'χΧ',
			'ps' => 'ψΨ',
		);
		foreach ( $groups as $latin => $letters ) {
			foreach ( preg_split( '//u', $letters, -1, PREG_SPLIT_NO_EMPTY ) as $letter ) {
				$map[ $letter ] = $latin;
			}
		}
		$map[' '] = '-';
		$map['.'] = '';
	}
	return strtr( (string) $text, $map );
}

/**
 * Latin slugs a decoded Greek slug may have been converted to: every
 * standard, with and without the configured word removal, and the 2.x/3.x
 * bulk converter's own map. Used to find a term whose Greek address has no
 * redirect.
 *
 * @param string $greek Decoded Greek slug.
 * @return string[]
 */
function grmlt_latin_slug_candidates( $greek ) {
	$out = array();
	foreach ( array_keys( grmlt_get_modes() ) as $mode ) {
		$out[] = sanitize_title_with_dashes( grmlt_slugify( $greek, array( 'mode' => $mode ) ), '', 'save' );
		$out[] = sanitize_title_with_dashes(
			grmlt_slugify(
				$greek,
				array(
					'mode'       => $mode,
					'stopwords'  => array(),
					'one_letter' => false,
					'two_letter' => false,
				)
			),
			'',
			'save'
		);
	}
	$legacy = grmlt_legacy_old_slug( $greek );
	$out[]  = trim( $legacy, '-' );
	$out[]  = sanitize_title_with_dashes( $legacy, '', 'save' );
	return array_values( array_unique( array_filter( $out, 'strlen' ) ) );
}

/**
 * Public helper for themes: plain Greek-to-Latin transliteration of a string
 * using the configured mode, without stop/short word removal.
 *
 * @since 3.3.0
 * @param string $text Text.
 * @return string
 */
function grmlt_transliterate_greek( $text ) {
	return grmlt_slugify( $text, array( 'context' => 'filename' ) );
}

/**
 * Whether the current request is an ACF internal operation (field keys must
 * never be transliterated).
 *
 * @since 3.3.0
 * @return bool
 */
function grmlt_is_acf_context() {
	if ( ! class_exists( 'ACF' ) && ! function_exists( 'acf' ) ) {
		return false;
	}
	if ( wp_doing_ajax() ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only.
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( 0 === strpos( $action, 'acf/' ) || 0 === strpos( $action, 'acf_' ) ) {
			return true;
		}
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only.
	if ( isset( $_POST['post_type'] ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post_type = sanitize_text_field( wp_unslash( $_POST['post_type'] ) );
		if ( 0 === strpos( $post_type, 'acf-' ) ) {
			return true;
		}
	}
	if ( is_admin() && function_exists( 'get_current_screen' ) ) {
		$screen = get_current_screen();
		if ( $screen && ! empty( $screen->post_type ) && 0 === strpos( $screen->post_type, 'acf-' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * sanitize_title filter (priority 1, before sanitize_title_with_dashes).
 *
 * @param string $title     Title after remove_accents().
 * @param string $raw_title Original title.
 * @param string $context   Context.
 * @return string
 */
function grmlt_title_sanitizer( $title, $raw_title = '', $context = 'save' ) {
	if ( 'save' !== $context || ! grmlt_has_greek( $title ) ) {
		return $title; // Latin titles and existing (percent-encoded) slugs are never touched.
	}
	if ( grmlt_transliteration_suspended() || grmlt_is_acf_context() ) {
		return $title;
	}
	return grmlt_slugify( $title );
}

/**
 * sanitize_file_name filter (runs on the name core has already cleaned).
 *
 * @param string $filename     Sanitized file name.
 * @param string $filename_raw Original file name.
 * @return string
 */
function grmlt_file_name_sanitizer( $filename, $filename_raw = '' ) {
	$info = pathinfo( $filename );
	$name = isset( $info['filename'] ) ? $info['filename'] : '';
	$ext  = isset( $info['extension'] ) && '' !== $info['extension'] ? '.' . $info['extension'] : '';
	if ( ! grmlt_has_greek( $name ) ) {
		return $filename;
	}
	return grmlt_slugify( $name, array( 'context' => 'filename' ) ) . $ext;
}

/**
 * Registers the sanitize_title / sanitize_file_name filters (init).
 */
function grmlt_register_transliteration_filters() {
	if ( grmlt_is_on( 'grmlt_text' ) ) {
		add_filter( 'sanitize_title', 'grmlt_title_sanitizer', 1, 3 );
		add_action( 'pre_get_terms', 'grmlt_term_query_keep_greek_slugs', 1 );
		add_filter( 'pre_term_term_id', 'grmlt_note_term_id', 9 );
		add_filter( 'pre_term_name', 'grmlt_note_term_name', 9 );
		add_filter( 'pre_term_slug', 'grmlt_keep_existing_term_slug', 9, 2 );
	}
	if ( grmlt_is_on( 'grmlt_media_file_name' ) ) {
		add_filter( 'sanitize_file_name', 'grmlt_file_name_sanitizer', 1, 2 );
	}
}

/**
 * Previews the slug a title would get, with optional unsaved settings.
 * Mirrors what core does around our sanitize_title filter.
 *
 * @param string $title Title.
 * @param array  $args  See grmlt_slugify().
 * @return string Slug.
 */
function grmlt_preview_slug( $title, $args = array() ) {
	$title = remove_accents( (string) $title );
	$title = grmlt_slugify( $title, $args );
	return sanitize_title_with_dashes( $title, '', 'save' );
}

/**
 * Previews the file name an upload would get, with optional unsaved settings.
 *
 * @param string $filename File name.
 * @param array  $args     See grmlt_slugify().
 * @return string
 */
function grmlt_preview_filename( $filename, $args = array() ) {
	$had = has_filter( 'sanitize_file_name', 'grmlt_file_name_sanitizer' );
	if ( $had ) {
		remove_filter( 'sanitize_file_name', 'grmlt_file_name_sanitizer', 1 );
	}
	$core = sanitize_file_name( (string) $filename );
	if ( $had ) {
		add_filter( 'sanitize_file_name', 'grmlt_file_name_sanitizer', 1, 2 );
	}
	$info = pathinfo( $core );
	$name = isset( $info['filename'] ) ? $info['filename'] : '';
	$ext  = isset( $info['extension'] ) && '' !== $info['extension'] ? '.' . $info['extension'] : '';
	if ( ! grmlt_has_greek( $name ) ) {
		return $core;
	}
	$args['context'] = 'filename';
	return grmlt_slugify( $name, $args ) . $ext;
}

/**
 * Example outputs for the mode cards in the settings screen.
 *
 * @param string $mode Mode.
 * @return array<string, string> Greek word => Latin.
 */
function grmlt_get_mode_examples( $mode ) {
	$out = array();
	foreach ( array( 'μπλούζα', 'οικονομία', 'σύγχρονος' ) as $word ) {
		$out[ $word ] = grmlt_slugify( $word, array( 'mode' => $mode, 'context' => 'filename' ) );
	}
	return $out;
}
