<?php
/**
 * Single source of truth for the plugin's options: names, types, defaults,
 * sanitization and the Settings API group each one belongs to.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Transliteration modes, in display order.
 *
 * @return array<string, string> mode => label.
 */
function grmlt_get_modes() {
	return array(
		'elot743'       => __( 'ELOT 743', 'greek-multi-tool' ),
		'advanced'      => __( 'Phonetic', 'greek-multi-tool' ),
		'simple-legacy' => __( '3.x compatibility', 'greek-multi-tool' ),
	);
}

/**
 * The options schema.
 *
 * @return array<string, array<string, mixed>>
 */
function grmlt_options_schema() {
	static $schema = null;

	if ( null === $schema ) {
		$schema = array(
			'grmlt_text'                 => array(
				'type'    => 'bool',
				'default' => '1',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_diphthongs'           => array(
				'type'    => 'enum',
				'default' => 'elot743',
				'values'  => array( 'elot743', 'advanced', 'simple-legacy' ),
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_one_letter_words'     => array(
				'type'    => 'bool',
				'default' => '0',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_two_letter_words'     => array(
				'type'    => 'bool',
				'default' => '0',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_stwords'              => array(
				'type'    => 'list',
				'default' => '',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_media_file_name'      => array(
				'type'    => 'bool',
				'default' => '1',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_enable_text_analysis' => array(
				'type'    => 'bool',
				'default' => '0',
				'group'   => 'grmlt_settings_main',
			),
			'grmlt_redirect'             => array(
				'type'    => 'bool',
				'default' => '1',
				'group'   => 'grmlt_settings_redirects',
			),
		);
	}

	return $schema;
}

/**
 * Whether a raw option value means "on". Accepts the legacy values
 * ('on', 1, true, '1') written by versions before 4.0.0.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function grmlt_value_is_on( $value ) {
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_int( $value ) ) {
		return $value > 0;
	}
	$value = strtolower( trim( (string) $value ) );
	return in_array( $value, array( '1', 'on', 'true', 'yes' ), true );
}

/**
 * Whether a boolean option is on.
 *
 * @param string $option Option name.
 * @return bool
 */
function grmlt_is_on( $option ) {
	return grmlt_value_is_on( grmlt_get_option( $option ) );
}

/**
 * Reads an option, applying the schema default and normalizing the value.
 *
 * @param string $option Option name.
 * @return mixed
 */
function grmlt_get_option( $option ) {
	$schema = grmlt_options_schema();
	if ( ! isset( $schema[ $option ] ) ) {
		return get_option( $option );
	}
	$value = get_option( $option, null );
	if ( grmlt_is_migration_pending() && ! in_array( $option, grmlt_options_written(), true ) ) {
		// A 2.x/3.x site whose options have not been migrated yet (for example
		// right after an automatic update, or while the migration waits after
		// an error): read the values 3.x stored exactly the way 3.x read them.
		// Values 4.0 itself wrote in the meantime (settings form, reset) are
		// read normally.
		return grmlt_legacy_option_value( $option, $value );
	}
	if ( null === $value || false === $value ) {
		return $schema[ $option ]['default'];
	}
	return grmlt_sanitize_option_value( $option, $value );
}

/**
 * The 4.0 value of an option, read with the exact comparisons of 2.3.1 to
 * 3.4.1 (see RELEASE-4.0.0.md section 6 for the table per version):
 *
 * - grmlt_text, grmlt_media_file_name: on only for the string 'on'
 *   (`== 'on'` in 2.x/3.1, `=== 'on'` from 3.3); grmlt_media_file_name did
 *   not exist before 3.3, so it is missing and off there;
 * - grmlt_redirect: `== '1'`; grmlt_one_letter_words / two_letter_words: `== 1`;
 * - grmlt_enable_text_analysis: `=== '1'` (3.1+);
 * - grmlt_diphthongs: `'simple'` selects the simple mode, anything else,
 *   including a missing or empty value, the advanced one;
 * - grmlt_stwords: split on commas.
 *
 * @param string $option Option name.
 * @param mixed  $raw    Stored value (null when missing).
 * @return string
 */
function grmlt_legacy_option_value( $option, $raw ) {
	// phpcs:disable Universal.Operators.StrictComparisons.LooseEqual -- reproduces the 3.x comparisons on purpose.
	switch ( $option ) {
		case 'grmlt_text':
		case 'grmlt_media_file_name':
			return ( true === $raw || 'on' === $raw ) ? '1' : '0';
		case 'grmlt_redirect':
			return ( null !== $raw && false !== $raw && '1' == $raw ) ? '1' : '0';
		case 'grmlt_one_letter_words':
		case 'grmlt_two_letter_words':
			return ( null !== $raw && false !== $raw && 1 == $raw ) ? '1' : '0';
		case 'grmlt_enable_text_analysis':
			return '1' === $raw ? '1' : '0';
		case 'grmlt_diphthongs':
			return 'simple' === $raw ? 'simple-legacy' : 'advanced';
		case 'grmlt_stwords':
			return ( is_string( $raw ) && '' !== $raw ) ? grmlt_sanitize_word_list( $raw ) : '';
	}
	// phpcs:enable
	return null === $raw || false === $raw ? '' : (string) $raw;
}

/**
 * Whether this is a 2.x/3.x site whose options have not been migrated yet.
 * True until the "options" step of the 4.0 migration has run; the later
 * steps (redirects, old table, undo snapshot) do not change how options
 * are read.
 *
 * @return bool
 */
function grmlt_is_migration_pending() {
	static $legacy = null;
	if ( false !== get_option( 'grmlt_version', false ) ) {
		return false;
	}
	if ( null === $legacy ) {
		$legacy = function_exists( 'grmlt_is_legacy_install' ) && grmlt_is_legacy_install();
	}
	if ( ! $legacy ) {
		return false;
	}
	$state = get_option( 'grmlt_migration_state', array() );
	return ! ( is_array( $state ) && ! empty( $state['options_done'] ) );
}

/**
 * Options of the schema that 4.0 itself wrote while the migration was still
 * pending: they hold 4.0 values and must be neither read with the 3.x rules
 * nor mapped again by the migration.
 *
 * @return string[]
 */
function grmlt_options_written() {
	if ( false !== get_option( 'grmlt_version', false ) ) {
		return array();
	}
	$written = get_option( 'grmlt_options_written', array() );
	return is_array( $written ) ? $written : array();
}

/**
 * Re-entrant switch the migration sets while it rewrites the options, so its
 * own writes are not taken for 4.0 writes.
 *
 * @param bool|null $set New value.
 * @return bool
 */
function grmlt_migration_writing( $set = null ) {
	static $writing = false;
	if ( null !== $set ) {
		$writing = (bool) $set;
	}
	return $writing;
}

/**
 * add_option / update_option actions: remembers a schema option written by
 * 4.0 (settings form, reset, WP-CLI) while the migration is pending.
 *
 * @param string $option Option name.
 */
function grmlt_track_option_write( $option ) {
	if ( grmlt_migration_writing() || ! array_key_exists( (string) $option, grmlt_options_schema() ) || ! grmlt_is_migration_pending() ) {
		return;
	}
	$written = grmlt_options_written();
	if ( ! in_array( $option, $written, true ) ) {
		$written[] = (string) $option;
		update_option( 'grmlt_options_written', $written, false );
	}
}
add_action( 'add_option', 'grmlt_track_option_write' );
add_action( 'update_option', 'grmlt_track_option_write' );

/**
 * Lower case for UTF-8 text, also without the mbstring extension (ASCII and
 * Greek are covered then, which is all a slug or path needs).
 *
 * @param string $text Text.
 * @return string
 */
function grmlt_lower( $text ) {
	$text = (string) $text;
	if ( function_exists( 'mb_strtolower' ) ) {
		return mb_strtolower( $text, 'UTF-8' );
	}
	static $greek = null;
	if ( null === $greek ) {
		$greek = array();
		$upper = array( 'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ', 'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ', 'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ', 'Ψ', 'Ω', 'Ά', 'Έ', 'Ή', 'Ί', 'Ό', 'Ύ', 'Ώ', 'Ϊ', 'Ϋ' );
		$lower = array( 'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω', 'ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ', 'ϊ', 'ϋ' );
		$greek = array_combine( $upper, $lower );
	}
	// ASCII only: before PHP 8.2 strtolower() follows the locale and may alter UTF-8 bytes.
	return strtr( strtr( $text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz' ), $greek );
}

/**
 * Sanitizes a value according to the schema. Used both by the Settings API
 * and by the migration.
 *
 * @param string $option Option name.
 * @param mixed  $value  Raw value.
 * @return mixed
 */
function grmlt_sanitize_option_value( $option, $value ) {
	$schema = grmlt_options_schema();
	if ( ! isset( $schema[ $option ] ) ) {
		return $value;
	}
	switch ( $schema[ $option ]['type'] ) {
		case 'bool':
			return grmlt_value_is_on( $value ) ? '1' : '0';
		case 'enum':
			return grmlt_sanitize_mode( $value, $schema[ $option ]['default'] );
		case 'list':
			return grmlt_sanitize_word_list( $value );
	}
	return $value;
}

/**
 * Sanitizes the transliteration mode. 'simple' (3.x) maps to 'simple-legacy'.
 *
 * @param mixed  $value   Raw value.
 * @param string $default Default mode.
 * @return string
 */
function grmlt_sanitize_mode( $value, $default = 'elot743' ) {
	$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';
	if ( 'simple' === $value ) {
		return 'simple-legacy';
	}
	return array_key_exists( $value, grmlt_get_modes() ) ? $value : $default;
}

/**
 * Normalizes a comma or newline separated word list: trimmed, no empty
 * entries, no duplicates (compared case- and accent-insensitively).
 *
 * @param mixed $value Raw value.
 * @return string Comma separated list.
 */
function grmlt_sanitize_word_list( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( ',', $value );
	}
	$value = sanitize_textarea_field( (string) $value );
	$parts = preg_split( '/[,\r\n]+/u', $value );
	$seen  = array();
	$out   = array();
	foreach ( (array) $parts as $part ) {
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		$key = function_exists( 'grmlt_word_key' ) ? grmlt_word_key( $part ) : grmlt_lower( $part );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		$out[]        = $part;
	}
	return implode( ', ', $out );
}

/**
 * The stop words as an array.
 *
 * @return string[]
 */
function grmlt_get_stopwords() {
	$list = grmlt_get_option( 'grmlt_stwords' );
	if ( '' === $list ) {
		return array();
	}
	return array_values( array_filter( array_map( 'trim', explode( ',', $list ) ), 'strlen' ) );
}

/**
 * Registers every option with the Settings API, one group per settings form.
 */
function grmlt_register_settings() {
	foreach ( grmlt_options_schema() as $name => $def ) {
		register_setting(
			$def['group'],
			$name,
			array(
				'type'              => 'bool' === $def['type'] ? 'string' : 'string',
				'default'           => $def['default'],
				'sanitize_callback' => function ( $value ) use ( $name ) {
					return grmlt_sanitize_option_value( $name, $value );
				},
				'show_in_rest'      => false,
			)
		);
	}
}

/**
 * Restores every option to its default. Converted slugs and redirects are
 * not touched. A site on "3.x compatibility" keeps that standard: resetting
 * must not silently change how new permalinks are written.
 */
function grmlt_reset_options() {
	$mode = grmlt_get_option( 'grmlt_diphthongs' );
	foreach ( grmlt_options_schema() as $name => $def ) {
		if ( 'grmlt_diphthongs' === $name && 'simple-legacy' === $mode ) {
			continue;
		}
		update_option( $name, $def['default'] );
	}
}
