<?php
/**
 * Recovery tool for ACF field keys damaged by the pre-3.4.1 permalink converter.
 *
 * Before 3.4.1 the "Convert All Old Permalinks" tool rewrote the post_name of
 * every post whose slug contained a character outside [A-Za-z0-9-], with no
 * post type restriction. ACF stores each field as an acf-field post whose
 * post_name IS the field key (field_5f8a1b2c...), so the converter overwrote
 * those keys with a slug built from the field's label and every get_field()
 * call started returning nothing.
 *
 * The damage is recoverable without a backup because the converter touched
 * ONLY wp_posts.post_name:
 *
 *  - the field NAME survives in the acf-field post's post_excerpt, and
 *  - for every post that ever saved a value for the field, ACF wrote a
 *    postmeta row  _<fieldname> => <original field key>  which the converter
 *    never touched.
 *
 * Joining the two restores the original key deterministically.
 *
 * This file is loaded ONLY under WP-CLI (see grmlt-plugin.php). It registers
 * the command:
 *
 *     wp grmlt acf-restore-keys [--write]
 *
 * Dry-run is the default; nothing is written unless --write is passed.
 *
 * Known limits, also printed by the command:
 *  - Group keys (group_...) leave no copy in postmeta and are not recoverable
 *    this way. If the theme has an acf-json/ folder, ACF's Sync restores
 *    groups AND fields authoritatively - prefer it when available.
 *  - A field that was never saved on any post has no postmeta row and cannot
 *    be recovered.
 *  - Legacy ACF4 'acf' posts and acf-post-type / acf-taxonomy /
 *    acf-ui-options-page posts are reported but not restored.
 *
 * @since   3.4.1
 * @package Grmlt_Plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Build the restore plan and optionally apply it.
 *
 * @since 3.4.1
 * @param bool $write When true, broken keys are written back. Default false.
 * @return array{rows: array, notes: string[]} Report rows and general notes.
 */
function grmlt_acf_restore_keys_run( $write = false ) {

	global $wpdb;

	$report = array(
		'rows'  => array(),
		'notes' => array(),
	);

	// Every original field key that survives in postmeta, grouped by the
	// field name it was stored under. The converter never touched postmeta.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$meta_rows = $wpdb->get_results(
		"SELECT DISTINCT meta_key, meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key LIKE '\_%' AND meta_value LIKE 'field\_%'"
	);

	$keys_by_name = array();
	foreach ( (array) $meta_rows as $row ) {
		$name = substr( $row->meta_key, 1 );
		if ( '' === $name ) {
			continue;
		}
		$keys_by_name[ $name ][ $row->meta_value ] = true;
	}

	// The acf-field posts themselves. Read directly: this must see the rows
	// exactly as they are in the database, with no filter interference.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$fields = $wpdb->get_results(
		"SELECT ID, post_name, post_excerpt, post_title FROM {$wpdb->posts}
		 WHERE post_type = 'acf-field'"
	);

	// Keys already in use, so a restore can never create a duplicate key.
	$keys_in_use = array();
	foreach ( (array) $fields as $field ) {
		$keys_in_use[ $field->post_name ] = true;
	}

	// Count how many BROKEN fields claim each field name. ACF field names are
	// not unique across field groups, and the lookup below joins by name only.
	// If two broken fields share a name while a single key survives in
	// postmeta, that key could belong to either of them - writing it to the
	// wrong one would permanently attach the surviving data to a field with
	// different settings. Such names must go to a human, never be guessed.
	$broken_by_name = array();
	foreach ( (array) $fields as $field ) {
		if ( 0 !== strpos( $field->post_name, 'field_' ) && '' !== $field->post_excerpt ) {
			$broken_by_name[ $field->post_excerpt ] = isset( $broken_by_name[ $field->post_excerpt ] )
				? $broken_by_name[ $field->post_excerpt ] + 1
				: 1;
		}
	}

	foreach ( (array) $fields as $field ) {

		$row = array(
			'ID'     => (int) $field->ID,
			'name'   => $field->post_excerpt,
			'label'  => $field->post_title,
			'slug'   => $field->post_name,
			'status' => '',
			'key'    => '',
		);

		// A real ACF field key always starts with "field_". The converter's
		// output never contains an underscore at all, so the prefix test
		// cleanly separates intact keys from overwritten ones.
		if ( 0 === strpos( $field->post_name, 'field_' ) ) {
			$row['status'] = 'already-correct';
			$row['key']    = $field->post_name;
			$report['rows'][] = $row;
			continue;
		}

		if ( '' === $field->post_excerpt ) {
			$row['status'] = 'not-recoverable-no-name';
			$report['rows'][] = $row;
			continue;
		}

		$candidates = isset( $keys_by_name[ $field->post_excerpt ] )
			? array_keys( $keys_by_name[ $field->post_excerpt ] )
			: array();

		if ( isset( $broken_by_name[ $field->post_excerpt ] ) && $broken_by_name[ $field->post_excerpt ] > 1 ) {
			// See the $broken_by_name comment above: with several broken
			// fields sharing this name, no candidate key can be attributed
			// safely, even when only one survives.
			$row['status'] = 'ambiguous-duplicate-name';
			$row['key']    = implode( ', ', $candidates );
			$report['rows'][] = $row;
			continue;
		}

		if ( empty( $candidates ) ) {
			// The field was never saved on any post, so no postmeta row exists.
			$row['status'] = 'not-recoverable-never-used';
			$report['rows'][] = $row;
			continue;
		}

		if ( count( $candidates ) > 1 ) {
			// Two different fields shared this name at some point. Writing a
			// guess could attach the wrong data, so require a human decision.
			$row['status'] = 'ambiguous';
			$row['key']    = implode( ', ', $candidates );
			$report['rows'][] = $row;
			continue;
		}

		$key = $candidates[0];

		if ( isset( $keys_in_use[ $key ] ) ) {
			// Another acf-field post already owns this key (a re-created
			// replacement field, most likely). Overwriting would duplicate it.
			$row['status'] = 'skipped-key-in-use';
			$row['key']    = $key;
			$report['rows'][] = $row;
			continue;
		}

		$row['key'] = $key;

		if ( $write ) {
			// Only post_name changes. A raw update on purpose: wp_update_post
			// would run sanitize_title over the key, and this plugin's own
			// slug filters must never see an ACF key again.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$updated = $wpdb->update(
				$wpdb->posts,
				array( 'post_name' => $key ),
				array( 'ID' => (int) $field->ID )
			);

			if ( false === $updated ) {
				$row['status'] = 'write-failed';
			} else {
				clean_post_cache( (int) $field->ID );
				$keys_in_use[ $key ] = true;
				$row['status']       = 'restored';
			}
		} else {
			$row['status'] = 'would-restore';
		}

		$report['rows'][] = $row;
	}

	// Damage this tool cannot repair, surfaced so nobody assumes it did.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$broken_groups = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		 WHERE post_type = 'acf-field-group' AND post_name NOT LIKE 'group\_%'"
	);
	if ( $broken_groups > 0 ) {
		$report['notes'][] = sprintf(
			'%d field group key(s) (group_...) are damaged. Group keys leave no copy in postmeta and cannot be restored by this tool. If your theme has an acf-json/ folder, use ACF > Field Groups > Sync to restore them.',
			$broken_groups
		);
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$broken_other = (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts}
		 WHERE post_type IN ('acf', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page')
		   AND post_name LIKE '%-%'"
	);
	if ( $broken_other > 0 ) {
		$report['notes'][] = sprintf(
			'%d other ACF internal post(s) (legacy acf, acf-post-type, acf-taxonomy, acf-ui-options-page) look damaged. They are not restored by this tool.',
			$broken_other
		);
	}

	return $report;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Restores ACF field keys damaged by the pre-3.4.1 permalink converter.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Actually write the restored keys. Without this flag the command is a
	 * dry run and changes nothing.
	 *
	 * ## EXAMPLES
	 *
	 *     # See what would be restored (writes nothing).
	 *     wp grmlt acf-restore-keys
	 *
	 *     # Restore the keys.
	 *     wp grmlt acf-restore-keys --write
	 *
	 * @when after_wp_load
	 */
	$grmlt_acf_restore_keys_command = function ( $args, $assoc_args ) {

		$write = ! empty( $assoc_args['write'] );

		WP_CLI::warning( 'Back up your database before using --write. This tool rewrites wp_posts.post_name on acf-field posts.' );

		// acf-json makes ACF itself the better recovery path: Sync restores
		// groups AND fields from the JSON files, keys included.
		$json_dirs = array_unique( array( get_stylesheet_directory() . '/acf-json', get_template_directory() . '/acf-json' ) );
		foreach ( $json_dirs as $dir ) {
			if ( is_dir( $dir ) && glob( trailingslashit( $dir ) . '*.json' ) ) {
				WP_CLI::warning( sprintf( 'Found %s. Prefer ACF > Field Groups > Sync: it restores field groups AND fields from JSON, including group keys this tool cannot recover.', $dir ) );
			}
		}

		if ( ! $write ) {
			WP_CLI::log( 'Dry run (default): nothing will be written. Re-run with --write to apply.' );
		}

		$report = grmlt_acf_restore_keys_run( $write );

		if ( empty( $report['rows'] ) ) {
			WP_CLI::success( 'No acf-field posts found in this database.' );
			return;
		}

		$counts = array();
		foreach ( $report['rows'] as $row ) {
			$counts[ $row['status'] ] = isset( $counts[ $row['status'] ] ) ? $counts[ $row['status'] ] + 1 : 1;
		}

		\WP_CLI\Utils\format_items(
			'table',
			$report['rows'],
			array( 'ID', 'name', 'label', 'slug', 'status', 'key' )
		);

		foreach ( $report['notes'] as $note ) {
			WP_CLI::warning( $note );
		}

		$summary = array();
		foreach ( $counts as $status => $count ) {
			$summary[] = $status . ': ' . $count;
		}
		WP_CLI::log( 'Summary - ' . implode( ', ', $summary ) );

		if ( $write ) {
			WP_CLI::success( 'Done. If an object cache is in use, run: wp cache flush' );
		} else {
			WP_CLI::success( 'Dry run complete. Nothing was written.' );
		}
	};

	WP_CLI::add_command( 'grmlt acf-restore-keys', $grmlt_acf_restore_keys_command );
}
