<?php
/**
 * Recovery tool for ACF / Secure Custom Fields keys damaged by the bulk
 * permalink converter of versions before 3.4.1.
 *
 * That converter wrote a slug built from the post title over the post_name
 * of every post whose slug contained a character outside [A-Za-z0-9-], with
 * no post type restriction. ACF stores the KEY of every field, field group,
 * post type, taxonomy and options page in post_name (field_…, group_…,
 * post_type_…, taxonomy_…, ui_options_page_…), so all of them were renamed.
 * Names (post_excerpt), settings and values were not touched.
 *
 * Detection: an ACF post whose post_name does not start with the prefix of
 * its own post type is damaged.
 *
 * Repair, in order of preference:
 *  1. postmeta (fields and sub fields): for every saved value ACF also wrote
 *     _<name> => <field key> (_<parent>_<name>, _<parent>_<row>_<name> for
 *     sub fields; the same in termmeta, usermeta and _options_… options).
 *     One surviving key restores the field; several different keys are a
 *     conflict, listed with how often each occurs, and never guessed.
 *  2. acf-json: the Local JSON files of the site (acf/settings/load_json,
 *     acf/json/load_paths, the theme's acf-json folder) hold the original
 *     keys. Matched by title plus the field keys a group contains, or the
 *     post type / taxonomy / options page slug; only a unique match counts.
 *  3. --regenerate-keys (with --write): a new key with the right prefix, in
 *     ACF's own format (uniqid). Only for what has no surviving key at all;
 *     conflicts are never regenerated.
 *
 * Every write is preceded by a JSON backup of the rows it changes, in
 * uploads/grmlt-acf-backups/ (closed to the web by .htaccess and index.php)
 * under an unguessable name, and can be undone with --undo=<file>.
 *
 * This file is loaded ONLY under WP-CLI.
 *
 * @since   3.4.1
 * @since   4.0.0 Sub fields, all ACF post types, acf-json, --regenerate-keys, backup and --undo.
 * @package Grmlt_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Key prefix of each ACF internal post type.
 *
 * @return array<string, string>
 */
function grmlt_acf_key_prefixes() {
	return array(
		'acf-field'           => 'field_',
		'acf-field-group'     => 'group_',
		'acf-post-type'       => 'post_type_',
		'acf-taxonomy'        => 'taxonomy_',
		'acf-ui-options-page' => 'ui_options_page_',
	);
}

/**
 * Every ACF internal post, read directly from the database (no filters).
 *
 * @return array<int, object> ID => row.
 */
function grmlt_acf_load_posts() {
	global $wpdb;
	$types        = array_keys( grmlt_acf_key_prefixes() );
	$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %s per post type, built above.
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_type, post_name, post_excerpt, post_title, post_parent, post_content FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) ORDER BY ID", $types ) );
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->ID ] = $row;
	}
	return $out;
}

/**
 * Whether a key has the prefix its post type requires.
 *
 * @param string $post_type ACF post type.
 * @param string $key       post_name.
 * @return bool
 */
function grmlt_acf_key_is_valid( $post_type, $key ) {
	$prefixes = grmlt_acf_key_prefixes();
	return isset( $prefixes[ $post_type ] ) && 0 === strpos( (string) $key, $prefixes[ $post_type ] ) && strlen( (string) $key ) > strlen( $prefixes[ $post_type ] );
}

/**
 * Field names from the top-level field down to this one (sub fields sit
 * under other acf-field posts), or null when a name is missing.
 *
 * @param int   $id    Field post ID.
 * @param array $posts All ACF posts.
 * @return string[]|null
 */
function grmlt_acf_field_path( $id, $posts ) {
	$names = array();
	$guard = 0;
	while ( isset( $posts[ $id ] ) && 'acf-field' === $posts[ $id ]->post_type && $guard++ < 20 ) {
		if ( '' === (string) $posts[ $id ]->post_excerpt ) {
			return null;
		}
		array_unshift( $names, (string) $posts[ $id ]->post_excerpt );
		$id = (int) $posts[ $id ]->post_parent;
	}
	return $names ? $names : null;
}

/**
 * The field group a field belongs to (walking up through parent fields).
 *
 * @param int   $id    Field post ID.
 * @param array $posts All ACF posts.
 * @return int Group post ID, 0 if none.
 */
function grmlt_acf_field_group_id( $id, $posts ) {
	$guard = 0;
	while ( isset( $posts[ $id ] ) && $guard++ < 20 ) {
		if ( 'acf-field-group' === $posts[ $id ]->post_type ) {
			return $id;
		}
		$id = (int) $posts[ $id ]->post_parent;
	}
	return 0;
}

/**
 * Field keys ACF stored next to saved values for a field path, with how
 * often each occurs: _<a>_<b> (group), _<a>_<n>_<b> (repeater, flexible
 * content) in postmeta, termmeta and usermeta, and _options_… in options.
 *
 * @param string[] $names Field path.
 * @return array<string, int> key => occurrences.
 */
function grmlt_acf_meta_evidence( $names ) {
	global $wpdb;
	$quoted = array_map(
		function ( $name ) {
			return preg_quote( $name, '/' );
		},
		$names
	);
	$tail   = implode( '_(?:[0-9]+_)?', $quoted ) . '$/u';
	$like   = $wpdb->esc_like( 'field_' ) . '%';
	$counts = array();
	$tables = array(
		array( $wpdb->postmeta, 'meta_key', 'meta_value', '_' ),
		array( $wpdb->termmeta, 'meta_key', 'meta_value', '_' ),
		array( $wpdb->usermeta, 'meta_key', 'meta_value', '_' ),
		array( $wpdb->options, 'option_name', 'option_value', '_options_' ),
	);
	foreach ( $tables as $t ) {
		list( $table, $key_col, $value_col, $prefix ) = $t;
		if ( 1 === count( $names ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS v, COUNT(*) AS c FROM %i WHERE %i = %s AND %i LIKE %s GROUP BY %i', $value_col, $table, $key_col, $prefix . $names[0], $value_col, $like, $value_col ) );
			foreach ( (array) $rows as $row ) {
				$counts[ $row->v ] = ( isset( $counts[ $row->v ] ) ? $counts[ $row->v ] : 0 ) + (int) $row->c;
			}
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS k, %i AS v FROM %i WHERE %i LIKE %s AND %i LIKE %s', $key_col, $value_col, $table, $key_col, $wpdb->esc_like( $prefix . $names[0] . '_' ) . '%', $value_col, $like ) );
		$pattern = '/^' . preg_quote( $prefix, '/' ) . $tail;
		foreach ( (array) $rows as $row ) {
			if ( preg_match( $pattern, (string) $row->k ) ) {
				$counts[ $row->v ] = ( isset( $counts[ $row->v ] ) ? $counts[ $row->v ] : 0 ) + 1;
			}
		}
	}
	arsort( $counts );
	return $counts;
}

/**
 * Folders ACF loads Local JSON from, plus the theme's acf-json folders.
 *
 * @return string[]
 */
function grmlt_acf_json_dirs() {
	if ( function_exists( 'acf_get_setting' ) ) {
		$dirs = (array) acf_get_setting( 'load_json' );
	} else {
		$dirs = (array) apply_filters( 'acf/settings/load_json', array() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- ACF's own hook.
	}
	$dirs   = (array) apply_filters( 'acf/json/load_paths', $dirs ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- ACF's own hook.
	$dirs[] = get_stylesheet_directory() . '/acf-json';
	$dirs[] = get_template_directory() . '/acf-json';
	$out    = array();
	foreach ( $dirs as $dir ) {
		if ( is_string( $dir ) && '' !== $dir && is_dir( $dir ) ) {
			$out[] = untrailingslashit( wp_normalize_path( $dir ) );
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Field paths (names joined by "/") => keys, from a JSON field list.
 *
 * @param array  $fields Fields (with sub_fields / layouts).
 * @param string $prefix Path so far.
 * @param array  $map    Map (by reference).
 */
function grmlt_acf_json_field_paths( $fields, $prefix, &$map ) {
	foreach ( (array) $fields as $field ) {
		if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['key'] ) ) {
			continue;
		}
		$path           = '' === $prefix ? (string) $field['name'] : $prefix . '/' . $field['name'];
		$map[ $path ][] = (string) $field['key'];
		if ( ! empty( $field['sub_fields'] ) ) {
			grmlt_acf_json_field_paths( $field['sub_fields'], $path, $map );
		}
		if ( ! empty( $field['layouts'] ) ) {
			foreach ( (array) $field['layouts'] as $layout ) {
				if ( is_array( $layout ) && ! empty( $layout['sub_fields'] ) ) {
					grmlt_acf_json_field_paths( $layout['sub_fields'], $path, $map );
				}
			}
		}
	}
}

/**
 * Everything the Local JSON files describe, by ACF post type. Each entry:
 * key, title, slug (post type / taxonomy / menu slug) and, for groups, the
 * field keys and field paths it contains.
 *
 * @return array{dirs: string[], items: array<string, array>}
 */
function grmlt_acf_json_index() {
	$index = array(
		'dirs'  => grmlt_acf_json_dirs(),
		'items' => array(),
	);
	$types = array_flip( grmlt_acf_key_prefixes() );
	foreach ( $index['dirs'] as $dir ) {
		foreach ( (array) glob( $dir . '/*.json' ) as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
			if ( ! is_array( $json ) ) {
				continue;
			}
			$list = isset( $json['key'] ) ? array( $json ) : array_values( $json ); // A file is one post, an export is a list.
			foreach ( $list as $item ) {
				if ( ! is_array( $item ) || empty( $item['key'] ) || ! is_string( $item['key'] ) ) {
					continue;
				}
				$type = '';
				foreach ( $types as $prefix => $post_type ) {
					if ( 0 === strpos( $item['key'], $prefix ) && ( '' === $type || strlen( $prefix ) > strlen( grmlt_acf_key_prefixes()[ $type ] ) ) ) {
						$type = $post_type;
					}
				}
				if ( '' === $type || 'acf-field' === $type ) {
					continue;
				}
				$entry = array(
					'key'   => $item['key'],
					'title' => isset( $item['title'] ) ? (string) $item['title'] : '',
					'slug'  => '',
					'file'  => $file,
				);
				if ( 'acf-post-type' === $type && isset( $item['post_type'] ) ) {
					$entry['slug'] = (string) $item['post_type'];
				} elseif ( 'acf-taxonomy' === $type && isset( $item['taxonomy'] ) ) {
					$entry['slug'] = (string) $item['taxonomy'];
				} elseif ( 'acf-ui-options-page' === $type && isset( $item['menu_slug'] ) ) {
					$entry['slug'] = (string) $item['menu_slug'];
				} elseif ( 'acf-field-group' === $type ) {
					$paths = array();
					grmlt_acf_json_field_paths( isset( $item['fields'] ) ? $item['fields'] : array(), '', $paths );
					$entry['paths']      = $paths;
					$entry['field_keys'] = array();
					foreach ( $paths as $keys ) {
						foreach ( $keys as $k ) {
							$entry['field_keys'][ $k ] = true;
						}
					}
				}
				$index['items'][ $type ][ $entry['key'] ] = $entry;
			}
		}
	}
	return $index;
}

/**
 * The registration slug stored in an ACF post's settings (post_content).
 *
 * @param object $post ACF post row.
 * @return string
 */
function grmlt_acf_settings_slug( $post ) {
	$settings = maybe_unserialize( $post->post_content );
	if ( ! is_array( $settings ) ) {
		return '';
	}
	$field = array(
		'acf-post-type'       => 'post_type',
		'acf-taxonomy'        => 'taxonomy',
		'acf-ui-options-page' => 'menu_slug',
	);
	return isset( $field[ $post->post_type ], $settings[ $field[ $post->post_type ] ] ) ? (string) $settings[ $field[ $post->post_type ] ] : '';
}

/**
 * Builds the repair plan.
 *
 * @param bool $regenerate Plan new keys for what has no surviving key.
 * @return array{rows: array<int, array>, intact: int, json_dirs: string[]}
 */
function grmlt_acf_restore_plan( $regenerate = false ) {
	$posts    = grmlt_acf_load_posts();
	$prefixes = grmlt_acf_key_prefixes();
	$in_use   = array();
	$rows     = array();
	$intact   = 0;
	foreach ( $posts as $id => $post ) {
		$in_use[ $post->post_name ] = $id;
		if ( grmlt_acf_key_is_valid( $post->post_type, $post->post_name ) ) {
			++$intact;
			continue;
		}
		$rows[ $id ] = array(
			'ID'       => $id,
			'type'     => $post->post_type,
			'title'    => $post->post_title,
			'current'  => $post->post_name,
			'proposed' => '',
			'source'   => '',
			'action'   => '',
			'note'     => '',
		);
	}
	$planned = function ( $id ) use ( $posts, &$rows ) {
		if ( isset( $rows[ $id ] ) ) {
			return '' !== $rows[ $id ]['proposed'] ? $rows[ $id ]['proposed'] : '';
		}
		return isset( $posts[ $id ] ) ? $posts[ $id ]->post_name : '';
	};

	// 1. Fields and sub fields from the keys stored next to their values.
	foreach ( $rows as $id => $row ) {
		if ( 'acf-field' !== $row['type'] ) {
			continue;
		}
		$path = grmlt_acf_field_path( $id, $posts );
		if ( null === $path ) {
			$rows[ $id ]['note'] = 'no field name';
			continue;
		}
		$rows[ $id ]['path'] = $path;
		$evidence            = grmlt_acf_meta_evidence( $path );
		if ( count( $evidence ) > 1 ) {
			$list = array();
			foreach ( $evidence as $key => $n ) {
				$list[] = $key . ' (' . $n . ')';
			}
			$rows[ $id ]['source'] = 'conflict';
			$rows[ $id ]['action'] = 'conflict';
			$rows[ $id ]['note']   = implode( ', ', $list );
		} elseif ( 1 === count( $evidence ) ) {
			$rows[ $id ]['proposed'] = (string) key( $evidence );
			$rows[ $id ]['source']   = 'postmeta';
			$rows[ $id ]['action']   = 'restore';
			$rows[ $id ]['note']     = current( $evidence ) . ' stored values';
		}
	}
	// Several damaged fields with the same name path cannot be told apart.
	$by_name = array();
	foreach ( $rows as $id => $row ) {
		if ( ! empty( $row['path'] ) ) {
			$by_name[ implode( '/', $row['path'] ) ][] = $id;
		}
	}
	foreach ( $by_name as $name => $ids ) {
		if ( count( $ids ) < 2 ) {
			continue;
		}
		foreach ( $ids as $id ) {
			if ( 'restore' === $rows[ $id ]['action'] ) {
				$rows[ $id ]['source']   = 'conflict';
				$rows[ $id ]['action']   = 'conflict';
				$rows[ $id ]['note']     = count( $ids ) . ' damaged fields are named "' . $name . '"; the surviving key ' . $rows[ $id ]['proposed'] . ' could belong to any of them';
				$rows[ $id ]['proposed'] = '';
			}
		}
	}

	// 2. Field groups, post types, taxonomies and options pages from acf-json.
	$json = grmlt_acf_json_index();
	foreach ( $rows as $id => $row ) {
		if ( 'acf-field' === $row['type'] || '' !== $row['action'] ) {
			continue;
		}
		$items   = isset( $json['items'][ $row['type'] ] ) ? $json['items'][ $row['type'] ] : array();
		$matches = array();
		foreach ( $items as $item ) {
			if ( $item['title'] !== $posts[ $id ]->post_title ) {
				continue;
			}
			if ( 'acf-field-group' === $row['type'] ) {
				$overlap = 0;
				$fields  = 0;
				foreach ( $posts as $fid => $p ) {
					if ( 'acf-field' === $p->post_type && (int) $p->post_parent === $id ) {
						++$fields;
						$overlap += isset( $item['field_keys'][ $planned( $fid ) ] ) ? 1 : 0;
					}
				}
				if ( $overlap > 0 || ( 0 === $fields && empty( $item['field_keys'] ) ) ) {
					$matches[] = $item;
				}
			} elseif ( '' !== $item['slug'] && $item['slug'] === grmlt_acf_settings_slug( $posts[ $id ] ) ) {
				$matches[] = $item;
			}
		}
		if ( 1 === count( $matches ) ) {
			$rows[ $id ]['proposed'] = $matches[0]['key'];
			$rows[ $id ]['source']   = 'acf-json';
			$rows[ $id ]['action']   = 'restore';
			$rows[ $id ]['note']     = basename( $matches[0]['file'] );
		} elseif ( count( $matches ) > 1 ) {
			$rows[ $id ]['source'] = 'conflict';
			$rows[ $id ]['action'] = 'conflict';
			$rows[ $id ]['note']   = implode( ', ', wp_list_pluck( $matches, 'key' ) ) . ' (acf-json)';
		}
	}

	// 3. Fields no value was ever saved for: from the acf-json of their group.
	foreach ( $rows as $id => $row ) {
		if ( 'acf-field' !== $row['type'] || '' !== $row['action'] || empty( $row['path'] ) ) {
			continue;
		}
		$group_key = $planned( grmlt_acf_field_group_id( $id, $posts ) );
		$path      = implode( '/', $row['path'] );
		if ( isset( $json['items']['acf-field-group'][ $group_key ]['paths'][ $path ] ) ) {
			$keys = array_values( array_unique( $json['items']['acf-field-group'][ $group_key ]['paths'][ $path ] ) );
			if ( 1 === count( $keys ) ) {
				$rows[ $id ]['proposed'] = $keys[0];
				$rows[ $id ]['source']   = 'acf-json';
				$rows[ $id ]['action']   = 'restore';
				$rows[ $id ]['note']     = basename( $json['items']['acf-field-group'][ $group_key ]['file'] );
			}
		}
	}

	// A proposed key must be free, and proposed only once.
	$proposed = array();
	foreach ( $rows as $id => $row ) {
		if ( 'restore' === $row['action'] ) {
			$proposed[ $row['proposed'] ][] = $id;
		}
	}
	foreach ( $proposed as $key => $ids ) {
		foreach ( $ids as $id ) {
			if ( isset( $in_use[ $key ] ) && $in_use[ $key ] !== $id ) {
				$rows[ $id ]['action'] = 'conflict';
				$rows[ $id ]['source'] = 'conflict';
				$rows[ $id ]['note']   = $key . ' is already used by post ' . $in_use[ $key ];
			} elseif ( count( $ids ) > 1 ) {
				$rows[ $id ]['action'] = 'conflict';
				$rows[ $id ]['source'] = 'conflict';
				$rows[ $id ]['note']   = $key . ' found for posts ' . implode( ', ', $ids );
			}
		}
	}

	// 4. Nothing survived: a new key, only when asked for.
	foreach ( $rows as $id => $row ) {
		if ( '' !== $row['action'] ) {
			continue;
		}
		$rows[ $id ]['action'] = 'regenerate';
		$rows[ $id ]['source'] = 'new';
		if ( $regenerate ) {
			do {
				$key = uniqid( $prefixes[ $row['type'] ] );
			} while ( isset( $in_use[ $key ] ) );
			$in_use[ $key ]          = $id;
			$rows[ $id ]['proposed'] = $key;
		}
	}

	return array(
		'rows'      => $rows,
		'intact'    => $intact,
		'json_dirs' => $json['dirs'],
	);
}

/**
 * Writes the planned keys, after a JSON backup of the rows that change.
 *
 * @param array $rows       Plan rows.
 * @param bool  $regenerate Also write new keys.
 * @return array{changed: array, backup: string}|WP_Error
 */
function grmlt_acf_restore_apply( $rows, $regenerate ) {
	global $wpdb;
	$changes = array();
	foreach ( $rows as $row ) {
		if ( '' === $row['proposed'] || ( 'restore' !== $row['action'] && ! ( $regenerate && 'regenerate' === $row['action'] ) ) ) {
			continue;
		}
		$changes[] = array(
			'ID'        => (int) $row['ID'],
			'post_type' => $row['type'],
			'title'     => $row['title'],
			'old'       => $row['current'],
			'new'       => $row['proposed'],
			'source'    => $row['source'],
		);
	}
	if ( ! $changes ) {
		return array(
			'changed' => array(),
			'backup'  => '',
		);
	}
	$backup = grmlt_acf_write_backup( $changes );
	if ( is_wp_error( $backup ) ) {
		return $backup;
	}
	foreach ( $changes as $i => $change ) {
		// Raw update on purpose: wp_update_post() would run sanitize_title()
		// over the key, and no slug filter may ever see an ACF key again.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok                       = $wpdb->update( $wpdb->posts, array( 'post_name' => $change['new'] ), array( 'ID' => $change['ID'] ) );
		$changes[ $i ]['written'] = false !== $ok;
	}
	grmlt_acf_flush_caches( $changes );
	return array(
		'changed' => $changes,
		'backup'  => $backup,
	);
}

/**
 * Path of the backup folder, uploads/grmlt-acf-backups (not created).
 *
 * @return string Empty when the uploads folder is not available.
 */
function grmlt_acf_backup_dir() {
	$uploads = wp_upload_dir( null, false );
	return empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . 'grmlt-acf-backups' : '';
}

/**
 * Creates the backup folder, closed to the web: index.php against directory
 * listing, .htaccess denying every request (Apache 2.4 and 2.2, LiteSpeed).
 *
 * @return string|WP_Error Folder path.
 */
function grmlt_acf_prepare_backup_dir() {
	$dir = grmlt_acf_backup_dir();
	if ( '' === $dir || ! wp_mkdir_p( $dir ) || ! wp_is_writable( $dir ) ) {
		return new WP_Error( 'grmlt_backup', 'The folder uploads/grmlt-acf-backups could not be created, so no backup could be written. Nothing was changed.' );
	}
	$guards = array(
		'index.php' => "<?php // Silence is golden.\n",
		'.htaccess' => "# Greek Multi Tool: backups of wp grmlt acf-restore-keys. Not served to anyone.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\n",
	);
	foreach ( $guards as $name => $content ) {
		$path = $dir . '/' . $name;
		if ( ! is_file( $path ) && false === file_put_contents( $path, $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI only.
			return new WP_Error( 'grmlt_backup', 'The file uploads/grmlt-acf-backups/' . $name . ' could not be written, so the folder is not protected and no backup was written. Nothing was changed.' );
		}
	}
	return $dir;
}

/**
 * Writes the backup file for a set of changes.
 *
 * @param array $changes Changes (ID, post_type, title, old, new, source).
 * @return string|WP_Error File path.
 */
function grmlt_acf_write_backup( $changes ) {
	$dir = grmlt_acf_prepare_backup_dir();
	if ( is_wp_error( $dir ) ) {
		return $dir;
	}
	// An unguessable name as well, should the server ignore .htaccess (nginx).
	$file = $dir . '/grmlt-acf-backup-' . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 16, false ) . '.json';
	$data = array(
		'created' => gmdate( 'c' ),
		'site'    => home_url( '/' ),
		'tool'    => 'wp grmlt acf-restore-keys',
		'rows'    => $changes,
	);
	if ( false === file_put_contents( $file, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI only.
		return new WP_Error( 'grmlt_backup', 'The backup file could not be written. Nothing was changed.' );
	}
	return $file;
}

/**
 * Restores the keys recorded in a backup file. A row is put back only when
 * the post still has the key this tool wrote; anything changed since is left.
 *
 * @param string $file Backup file: a path, or just its name, looked up in uploads/grmlt-acf-backups.
 * @return array{restored: array, skipped: array}|WP_Error
 */
function grmlt_acf_restore_undo( $file ) {
	global $wpdb;
	if ( ! is_file( $file ) && '' !== grmlt_acf_backup_dir() ) {
		$file = grmlt_acf_backup_dir() . '/' . ltrim( $file, '/\\' );
	}
	$data = is_file( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	if ( ! is_array( $data ) || empty( $data['rows'] ) || ! is_array( $data['rows'] ) ) {
		return new WP_Error( 'grmlt_undo', 'Not a backup file of this tool: ' . $file );
	}
	$restored = array();
	$skipped  = array();
	foreach ( $data['rows'] as $row ) {
		if ( ! isset( $row['ID'], $row['old'], $row['new'] ) ) {
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", (int) $row['ID'] ) );
		if ( $current !== $row['new'] ) {
			$row['reason'] = null === $current ? 'post deleted' : 'changed since (now ' . $current . ')';
			$skipped[]     = $row;
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->posts, array( 'post_name' => $row['old'] ), array( 'ID' => (int) $row['ID'] ) );
		$restored[] = $row;
	}
	grmlt_acf_flush_caches( $restored );
	return array(
		'restored' => $restored,
		'skipped'  => $skipped,
	);
}

/**
 * Flushes the WordPress and ACF caches of the posts whose key changed, under
 * their old and their new key.
 *
 * @param array $changes Changes (ID, post_type, old, new).
 */
function grmlt_acf_flush_caches( $changes ) {
	global $wpdb;
	foreach ( $changes as $change ) {
		$id = (int) $change['ID'];
		clean_post_cache( $id );
		foreach ( array( $change['old'], $change['new'] ) as $key ) {
			if ( 'acf-field' === $change['post_type'] && function_exists( 'acf_flush_field_cache' ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$post = $wpdb->get_row( $wpdb->prepare( "SELECT post_excerpt, post_parent FROM {$wpdb->posts} WHERE ID = %d", $id ) );
				acf_flush_field_cache(
					array(
						'key'    => $key,
						'name'   => $post ? $post->post_excerpt : '',
						'parent' => $post ? (int) $post->post_parent : 0,
					)
				);
			} elseif ( 'acf-field' !== $change['post_type'] && function_exists( 'acf_flush_internal_post_type_cache' ) && function_exists( 'acf_get_internal_post_type_instance' ) && acf_get_internal_post_type_instance( $change['post_type'] ) ) {
				acf_flush_internal_post_type_cache(
					array(
						'ID'  => $id,
						'key' => $key,
					),
					$change['post_type']
				);
			}
		}
	}
	if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
		wp_cache_flush_group( 'acf' );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Restores ACF / Secure Custom Fields keys damaged by the permalink
	 * converter of versions before 3.4.1.
	 *
	 * Finds every field, field group, post type, taxonomy and options page
	 * whose key lost its prefix, and restores the original key from the
	 * keys ACF stored next to saved values (fields and sub fields) or from
	 * the site's acf-json files. Dry run by default.
	 *
	 * ## OPTIONS
	 *
	 * [--write]
	 * : Write the keys that can be restored safely. A JSON backup of the
	 * changed rows is written first, to wp-content/uploads/grmlt-acf-backups/.
	 *
	 * [--regenerate-keys]
	 * : With --write: give a NEW key (right prefix, ACF's format) to what has
	 * no surviving key. Conflicts are never regenerated.
	 *
	 * [--undo=<file>]
	 * : Put back the keys recorded in a backup file written by --write. Just
	 * the file name is enough: it is looked up in uploads/grmlt-acf-backups/.
	 *
	 * ## EXAMPLES
	 *
	 *     wp grmlt acf-restore-keys
	 *     wp grmlt acf-restore-keys --write
	 *     wp grmlt acf-restore-keys --write --regenerate-keys
	 *     wp grmlt acf-restore-keys --undo=grmlt-acf-backup-2026-10-13-101500-xxxx.json
	 *
	 * @when after_wp_load
	 */
	$grmlt_acf_restore_keys_command = function ( $args, $assoc_args ) {
		$write      = ! empty( $assoc_args['write'] );
		$regenerate = ! empty( $assoc_args['regenerate-keys'] );

		if ( ! empty( $assoc_args['undo'] ) ) {
			$result = grmlt_acf_restore_undo( (string) $assoc_args['undo'] );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			foreach ( $result['skipped'] as $row ) {
				WP_CLI::warning( sprintf( 'Post %d (%s) not restored: %s.', $row['ID'], $row['title'], $row['reason'] ) );
			}
			WP_CLI::success( sprintf( 'Restored %d key(s) from the backup, %d skipped.', count( $result['restored'] ), count( $result['skipped'] ) ) );
			return;
		}
		if ( $regenerate && ! $write ) {
			WP_CLI::error( '--regenerate-keys writes new keys and needs --write as well. Run without both first to see the plan.' );
		}

		$plan = grmlt_acf_restore_plan( $regenerate );
		if ( $plan['json_dirs'] ) {
			WP_CLI::log( 'acf-json folders read: ' . implode( ', ', $plan['json_dirs'] ) );
		}
		if ( ! $plan['rows'] ) {
			WP_CLI::success( sprintf( 'No damaged ACF keys found (%d intact). Nothing to do.', $plan['intact'] ) );
			return;
		}

		$labels = array(
			'restore'    => $write ? 'restored' : 'would-restore',
			'regenerate' => $regenerate ? 'regenerated (NEW key)' : 'needs --regenerate-keys or acf-json',
			'conflict'   => 'conflict: not changed',
		);
		$table  = array();
		foreach ( $plan['rows'] as $row ) {
			$table[] = array(
				'ID'       => $row['ID'],
				'type'     => $row['type'],
				'title'    => $row['title'],
				'current'  => $row['current'],
				'proposed' => $row['proposed'],
				'source'   => $row['source'],
				'status'   => $labels[ $row['action'] ],
				'details'  => $row['note'],
			);
		}

		if ( $write ) {
			WP_CLI::warning( 'Back up the database first. This rewrites wp_posts.post_name of ACF posts (a JSON backup of the changed rows is also written).' );
			$result = grmlt_acf_restore_apply( $plan['rows'], $regenerate );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			foreach ( $table as $i => $line ) {
				foreach ( $result['changed'] as $change ) {
					if ( $change['ID'] === $line['ID'] && empty( $change['written'] ) ) {
						$table[ $i ]['status'] = 'write-failed';
					}
				}
			}
		} else {
			WP_CLI::log( 'Dry run (default): nothing will be written.' );
		}

		\WP_CLI\Utils\format_items( 'table', $table, array( 'ID', 'type', 'title', 'current', 'proposed', 'source', 'status', 'details' ) );

		$counts = array();
		foreach ( $plan['rows'] as $row ) {
			$counts[ $row['action'] ] = isset( $counts[ $row['action'] ] ) ? $counts[ $row['action'] ] + 1 : 1;
		}
		WP_CLI::log( sprintf( 'Damaged: %d (restorable: %d, no surviving key: %d, conflicts: %d). Intact: %d.', count( $plan['rows'] ), isset( $counts['restore'] ) ? $counts['restore'] : 0, isset( $counts['regenerate'] ) ? $counts['regenerate'] : 0, isset( $counts['conflict'] ) ? $counts['conflict'] : 0, $plan['intact'] ) );

		if ( ! empty( $counts['regenerate'] ) && ! $regenerate ) {
			WP_CLI::log( 'Keys with no surviving copy can be restored from acf-json (put the site\'s acf-json folder back in the theme and run again), or replaced with NEW keys: wp grmlt acf-restore-keys --write --regenerate-keys' );
		}
		if ( ! empty( $counts['regenerate'] ) && $regenerate && $write ) {
			WP_CLI::warning( 'Some keys are NEW. If the site uses acf-json or registers these field groups in PHP (export), save the affected field groups, post types and taxonomies again in ACF, or export them again.' );
		}
		if ( ! empty( $counts['conflict'] ) ) {
			WP_CLI::warning( 'Conflicts were not changed: the details column lists every candidate key and how often it occurs. Decide which is right and set it in wp_posts.post_name by hand.' );
		}
		if ( $write ) {
			if ( '' !== $result['backup'] ) {
				WP_CLI::log( 'Backup of the changed rows: ' . $result['backup'] );
				WP_CLI::log( 'To undo: wp grmlt acf-restore-keys --undo=' . basename( $result['backup'] ) );
			}
			WP_CLI::success( sprintf( 'Done: %d key(s) written. If a persistent object cache is in use, run: wp cache flush', count( $result['changed'] ) ) );
		} else {
			WP_CLI::success( 'Dry run complete. Nothing was written. Add --write to apply.' );
		}
	};

	WP_CLI::add_command( 'grmlt acf-restore-keys', $grmlt_acf_restore_keys_command );
}
