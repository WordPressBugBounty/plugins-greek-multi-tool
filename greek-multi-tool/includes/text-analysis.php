<?php
/**
 * Greek accent rules analysis engine (no UI here).
 *
 * @package Grmlt_Plugin
 * @since   2.4.0
 * @since   4.0.0 Returns structured issues; UI moved to the block editor panel.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The rules the analysis checks, keyed by rule id, for display.
 *
 * @return array<string, string>
 */
function grmlt_text_analysis_rules() {
	return array(
		'multisyllable'   => __( 'Words with two or more syllables must carry an accent.', 'greek-multi-tool' ),
		'elision'         => __( 'Words that become monosyllabic after elision or apocope keep their accent.', 'greek-multi-tool' ),
		'aphaeresis'      => __( 'Words that lost their accent through aphaeresis are exceptions.', 'greek-multi-tool' ),
		'monosyllable'    => __( 'Monosyllabic words do not take an accent, with specific exceptions.', 'greek-multi-tool' ),
		'known_correct'   => __( 'Common words such as “όλα”, “ένα”, “όταν” are recognized as correctly accented.', 'greek-multi-tool' ),
		'never_accented'  => __( 'Words such as “μου”, “σου”, “του”, “το”, “τα”, “για”, “πιο”, “ναι”, “και”, “οι” never take an accent.', 'greek-multi-tool' ),
		'disjunctive'     => __( 'The disjunctive conjunction “ή” always takes an accent.', 'greek-multi-tool' ),
		'interrogative'   => __( 'Interrogative “πού” and “πώς” take an accent in questions.', 'greek-multi-tool' ),
		'special_phrases' => __( '“πού” and “πώς” keep their accent in set phrases such as “πού και πού”.', 'greek-multi-tool' ),
		'all_caps'        => __( 'Words written in capitals do not carry accents, except “Ή”.', 'greek-multi-tool' ),
	);
}

/**
 * Counts syllables in a Greek word.
 *
 * @param string $word Word.
 * @return int
 */
function grmlt_count_syllables( $word ) {
	$word = mb_strtolower( grmlt_strip_greek_accents( $word ), 'UTF-8' );

	$one_syllable = array( 'μια', 'γεια', 'ποιος', 'γιος', 'βιος', 'δυο', 'πιο', 'για' );
	if ( in_array( $word, $one_syllable, true ) ) {
		return 1;
	}
	$two_syllable = array( 'ολα', 'ενα', 'οταν', 'ολες', 'ολοι', 'ολη', 'εχω', 'εχει', 'ειμαι', 'ειναι', 'ηταν' );
	if ( in_array( $word, $two_syllable, true ) ) {
		return 2;
	}
	foreach ( array( 'αι', 'ει', 'οι', 'υι', 'αυ', 'ευ', 'ου' ) as $diphthong ) {
		$word = str_replace( $diphthong, 'α', $word );
	}
	$count = preg_match_all( '/[αεηιουω]/u', $word, $matches );
	return max( 1, (int) $count );
}

/**
 * Analyzes Greek text for accent rule compliance.
 *
 * @param string $text Plain text.
 * @return array{stats:array, issues:array<int, array{word:string, rule:string, message:string, suggestion:string|null}>}
 */
function grmlt_analyze_text( $text ) {
	$text    = (string) $text;
	$results = array(
		'stats'  => array(
			'total_chars'    => mb_strlen( $text, 'UTF-8' ),
			'total_words'    => 0,
			'greek_chars'    => 0,
			'accented_chars' => 0,
			'percent_greek'  => 0,
		),
		'issues' => array(),
	);

	$never_accented = array( 'μου', 'σου', 'του', 'της', 'τον', 'την', 'το', 'τα', 'μας', 'σας', 'τους', 'για', 'πιο', 'ναι', 'και', 'οι', 'θα', 'στην', 'αν', 'στη', 'στο', 'στον', 'με', 'σε', 'τη', 'δε', 'ως', 'μη', 'μην', 'να', 'τις' );

	$correctly_accented = array( 'όλα', 'ένα', 'όταν', 'όλοι', 'όλες', 'είναι', 'έχω', 'έχει', 'ήταν', 'έχουν', 'όλη', 'όλο', 'ίδια', 'ίδιο', 'ίδιος', 'άλλο', 'άλλα', 'άλλη', 'άλλος', 'άλλες' );

	$special_phrases = array( 'πού να', 'πού και πού', 'από πού', 'ως πού', 'αραιά και πού', 'πώς και πώς', 'περιμένω πώς', 'πώς!' );

	$accented_chars = array( 'ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ', 'ΐ', 'ϊ', 'ϋ', 'ΰ', 'Ά', 'Έ', 'Ή', 'Ί', 'Ό', 'Ύ', 'Ώ', 'Ϊ', 'Ϋ' );

	$words = preg_split( '/\s+/u', trim( $text ) );
	$words = is_array( $words ) ? $words : array();
	$results['stats']['total_words'] = count( array_filter( $words, 'strlen' ) );

	$add_issue = function ( $word, $rule, $message, $suggestion = null ) use ( &$results ) {
		$results['issues'][] = array(
			'word'       => $word,
			'rule'       => $rule,
			'message'    => $message,
			'suggestion' => $suggestion,
		);
	};

	foreach ( $words as $index => $word ) {
		if ( '' === $word ) {
			continue;
		}
		$clean_word = preg_replace( '/[,\.;:!?\(\)\[\]{}"\'«»\-–—…]/u', '', $word );
		if ( '' === $clean_word ) {
			continue;
		}
		if ( in_array( mb_strtolower( $clean_word, 'UTF-8' ), $correctly_accented, true ) ) {
			continue;
		}

		$is_all_caps = mb_strtoupper( $clean_word, 'UTF-8' ) === $clean_word;
		$has_accent  = false;
		$has_greek   = false;
		$length      = mb_strlen( $clean_word, 'UTF-8' );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = mb_substr( $clean_word, $i, 1, 'UTF-8' );
			if ( preg_match( '/\p{Greek}/u', $char ) ) {
				$has_greek = true;
				++$results['stats']['greek_chars'];
				if ( in_array( $char, $accented_chars, true ) ) {
					$has_accent = true;
					++$results['stats']['accented_chars'];
				}
			}
		}
		if ( ! $has_greek ) {
			continue;
		}

		$word_lower = mb_strtolower( $clean_word, 'UTF-8' );
		$stripped   = grmlt_strip_greek_accents( $clean_word );

		if ( $is_all_caps && $has_accent && $length > 1 && 'ή' !== $word_lower ) {
			$add_issue( $clean_word, 'all_caps', sprintf( /* translators: %s: word */ __( '“%s” is written in capitals and should not carry an accent.', 'greek-multi-tool' ), $clean_word ), $stripped );
			continue;
		}

		if ( in_array( $word_lower, $never_accented, true ) ) {
			if ( $has_accent ) {
				$add_issue( $clean_word, 'never_accented', sprintf( /* translators: %s: word */ __( '“%s” never takes an accent.', 'greek-multi-tool' ), $clean_word ), $stripped );
			}
			continue;
		}

		$syllables      = grmlt_count_syllables( $clean_word );
		$first_capital  = mb_strtoupper( mb_substr( $clean_word, 0, 1, 'UTF-8' ), 'UTF-8' ) === mb_substr( $clean_word, 0, 1, 'UTF-8' );
		$rest_lowercase = $length > 1 && mb_strtolower( mb_substr( $clean_word, 1, null, 'UTF-8' ), 'UTF-8' ) === mb_substr( $clean_word, 1, null, 'UTF-8' );
		$is_capitalized = $first_capital && $rest_lowercase;

		$is_truncated = "'" === mb_substr( $word, -1, 1, 'UTF-8' ) || ( mb_strlen( $word, 'UTF-8' ) > 1 && "'" === mb_substr( $word, 0, 1, 'UTF-8' ) );
		$lost_accent  = "'" === mb_substr( $word, 0, 1, 'UTF-8' );

		if ( $syllables > 1 || ( $is_truncated && ! $lost_accent ) ) {
			if ( ! $has_accent && ! $is_all_caps ) {
				$add_issue( $clean_word, $is_truncated ? 'elision' : 'multisyllable', sprintf( /* translators: %s: word */ __( '“%s” has more than one syllable and should carry an accent.', 'greek-multi-tool' ), $clean_word ), null );
			}
			continue;
		}

		// Monosyllables.
		$should_have_accent = false;
		$suggestion         = null;
		$rule               = 'monosyllable';
		if ( 'ή' === $word_lower || 'η' === $word_lower ) {
			if ( 'ή' === $word_lower ) {
				$should_have_accent = true;
				$rule               = 'disjunctive';
			}
		} elseif ( in_array( $word_lower, array( 'που', 'πως', 'πού', 'πώς' ), true ) ) {
			$is_question = false;
			$start       = max( 0, $index - 10 );
			$end         = min( count( $words ) - 1, $index + 10 );
			for ( $i = $start; $i <= $end; $i++ ) {
				if ( false !== strpos( $words[ $i ], ';' ) || false !== strpos( $words[ $i ], '?' ) ) {
					$is_question = true;
					break;
				}
			}
			$context = '';
			for ( $i = max( 0, $index - 3 ); $i <= min( count( $words ) - 1, $index + 3 ); $i++ ) {
				$context .= $words[ $i ] . ' ';
			}
			$in_phrase = false;
			foreach ( $special_phrases as $phrase ) {
				if ( false !== mb_stripos( $context, $phrase, 0, 'UTF-8' ) ) {
					$in_phrase = true;
					break;
				}
			}
			if ( $is_question || $in_phrase ) {
				$should_have_accent = true;
				$rule               = $in_phrase ? 'special_phrases' : 'interrogative';
				$suggestion         = 'που' === grmlt_strip_greek_accents( $word_lower ) ? 'πού' : 'πώς';
			}
		} elseif ( 'θα' === grmlt_strip_greek_accents( $word_lower ) && $has_accent ) {
			if ( isset( $words[ $index + 1 ] ) && "'" === mb_substr( $words[ $index + 1 ], 0, 1, 'UTF-8' ) ) {
				$should_have_accent = true;
				$rule               = 'aphaeresis';
			}
		} elseif ( $is_capitalized ) {
			$should_have_accent = false;
		}

		if ( $should_have_accent && ! $has_accent ) {
			$add_issue( $clean_word, $rule, sprintf( /* translators: %s: word */ __( '“%s” should carry an accent in this context.', 'greek-multi-tool' ), $clean_word ), $suggestion );
		} elseif ( ! $should_have_accent && $has_accent && ! ( $is_all_caps && 'ή' === $word_lower ) ) {
			$add_issue( $clean_word, 'monosyllable', sprintf( /* translators: %s: word */ __( '“%s” is monosyllabic and should not carry an accent.', 'greek-multi-tool' ), $clean_word ), $stripped );
		}
	}

	if ( $results['stats']['total_chars'] > 0 ) {
		$results['stats']['percent_greek'] = (int) round( $results['stats']['greek_chars'] / $results['stats']['total_chars'] * 100 );
	}

	return $results;
}
