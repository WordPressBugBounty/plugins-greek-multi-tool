<?php
/**
 * Activation, database tables, versioning, locks and the 4.0.0 migration.
 *
 * The migration is resumable: its progress lives in the option
 * grmlt_migration_state (step + cursor) and every step is idempotent, so a
 * request that stops halfway is picked up by the next one. It runs on init
 * for every request, per site, under an atomic lock, with a time budget: a
 * few seconds on the front end, longer in wp-admin and cron, unlimited in
 * WP-CLI. An error never takes the site down: it is logged, the migration
 * waits an hour (or until an administrator retries it) and options keep
 * being read the way 3.x read them meanwhile.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'GRMLT_MIGRATION_LOG_EXAMPLES' ) ) {
	/**
	 * Skipped and left-out rows the migration log lists as examples; beyond
	 * them only the counts per reason grow. The log is not autoloaded.
	 */
	define( 'GRMLT_MIGRATION_LOG_EXAMPLES', 100 );
}

if ( ! defined( 'GRMLT_DB_REVISION' ) ) {
	/**
	 * Revision of the stored data for one-off repairs on sites already on the
	 * current version (see grmlt_maybe_repair()).
	 */
	define( 'GRMLT_DB_REVISION', 3 );
}

/**
 * Redirects table name.
 *
 * @return string
 */
function grmlt_redirects_table() {
	global $wpdb;
	return $wpdb->prefix . 'grmlt_redirects';
}

/**
 * Conversions table name.
 *
 * @return string
 */
function grmlt_conversions_table() {
	global $wpdb;
	return $wpdb->prefix . 'grmlt_conversions';
}

/**
 * The pre-4.0 redirects table name.
 *
 * @return string
 */
function grmlt_legacy_table() {
	global $wpdb;
	return $wpdb->prefix . 'grmlt';
}

/**
 * Common prefix of the tables the pre-4.0 table is renamed to when it cannot
 * be migrated completely. Each backup gets its own, dated name, so a later
 * migration never overwrites an earlier backup.
 *
 * @return string
 */
function grmlt_legacy_backup_table() {
	global $wpdb;
	return $wpdb->prefix . 'grmlt_legacy_backup';
}

/**
 * Backup tables that exist right now.
 *
 * @return string[]
 */
function grmlt_legacy_backup_tables() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( grmlt_legacy_backup_table() ) . '%' ) ) );
}

/**
 * Whether a table exists.
 *
 * @param string $table Table name.
 * @return bool
 */
function grmlt_table_exists( $table ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) === $table;
}

/* Locks ----------------------------------------------------------------- */

/**
 * Tokens of the locks held by this request, keyed by lock name.
 *
 * @param string      $name  Lock name.
 * @param string|null $token New token; '' forgets it.
 * @return string Current token ('' when not held).
 */
function grmlt_lock_token( $name, $token = null ) {
	static $held = array();
	if ( null !== $token ) {
		if ( '' === $token ) {
			unset( $held[ $name ] );
		} else {
			$held[ $name ] = $token;
		}
	}
	return isset( $held[ $name ] ) ? $held[ $name ] : '';
}

/**
 * Acquires a lock stored as a row in the options table, the way
 * WP_Upgrader::create_lock() does: INSERT IGNORE succeeds for exactly one
 * request. A lock older than $ttl seconds is taken over with a conditional
 * UPDATE that also succeeds for exactly one request.
 *
 * @param string $name Lock name (option name).
 * @param int    $ttl  Seconds after which a lock counts as orphaned.
 * @return bool
 */
function grmlt_lock_acquire( $name, $ttl ) {
	global $wpdb;
	$token = time() . '|' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 12 );
	for ( $attempt = 0; $attempt < 2; $attempt++ ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $token ) );
		if ( 1 === (int) $inserted ) {
			wp_cache_delete( $name, 'options' );
			grmlt_lock_token( $name, $token );
			return true;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( null === $current ) {
			continue; // Released in the meantime: try the INSERT once more.
		}
		$since = (int) $current; // "time|token"; a pre-release value is a bare time.
		if ( $since > 0 && ( time() - $since ) <= $ttl ) {
			return false;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stolen = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, $name, $current ) );
		if ( 1 === (int) $stolen ) {
			wp_cache_delete( $name, 'options' );
			grmlt_lock_token( $name, $token );
			return true;
		}
		return false;
	}
	return false;
}

/**
 * Renews the timestamp of a lock this request holds. Returns false when the
 * lock was taken over by another request (this one should stop writing).
 *
 * @param string $name Lock name.
 * @return bool
 */
function grmlt_lock_refresh( $name ) {
	global $wpdb;
	$token = grmlt_lock_token( $name );
	if ( '' === $token ) {
		return false;
	}
	if ( (int) $token >= time() ) {
		return true; // Renewed this very second already.
	}
	$new = time() . '|' . substr( $token, strpos( $token, '|' ) + 1 );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$ok = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, $name, $token ) );
	if ( 1 !== (int) $ok ) {
		grmlt_lock_token( $name, '' );
		return false;
	}
	grmlt_lock_token( $name, $new );
	return true;
}

/**
 * Releases a lock, only if this request still holds it.
 *
 * @param string $name Lock name.
 */
function grmlt_lock_release( $name ) {
	global $wpdb;
	$token = grmlt_lock_token( $name );
	if ( '' === $token ) {
		return;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $token ) );
	wp_cache_delete( $name, 'options' );
	grmlt_lock_token( $name, '' );
}

/* Tables ---------------------------------------------------------------- */

/**
 * Creates or updates the plugin tables with dbDelta.
 *
 * @return bool Whether both tables exist afterwards.
 */
function grmlt_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$redirects       = grmlt_redirects_table();
	$conversions     = grmlt_conversions_table();

	$sql = "CREATE TABLE {$redirects} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	object_type varchar(20) NOT NULL DEFAULT 'manual',
	object_id bigint(20) unsigned NOT NULL DEFAULT 0,
	old_path text NOT NULL,
	old_path_hash char(32) NOT NULL,
	new_path text NOT NULL,
	run_id varchar(32) DEFAULT NULL,
	hits bigint(20) unsigned NOT NULL DEFAULT 0,
	last_hit datetime DEFAULT NULL,
	status varchar(20) DEFAULT NULL,
	checked_at datetime DEFAULT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY old_path_hash (old_path_hash),
	KEY run_id (run_id),
	KEY object (object_type,object_id)
) {$charset_collate};
CREATE TABLE {$conversions} (
	id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
	run_id varchar(32) NOT NULL,
	object_type varchar(20) NOT NULL,
	object_subtype varchar(50) NOT NULL DEFAULT '',
	object_id bigint(20) unsigned NOT NULL,
	old_slug text NOT NULL,
	new_slug text NOT NULL,
	created_at datetime NOT NULL,
	PRIMARY KEY  (id),
	KEY run_id (run_id)
) {$charset_collate};";

	dbDelta( $sql );
	$ok = grmlt_table_exists( $redirects ) && grmlt_table_exists( $conversions );
	if ( $ok ) {
		update_option( 'grmlt_db_version', GRMLT_PLUGIN_VERSION );
	}
	return $ok;
}

/**
 * Whether this site ran a 2.x/3.x version (options or the old table exist).
 *
 * @return bool
 */
function grmlt_is_legacy_install() {
	return false !== get_option( 'grmlt_text', false )
		|| false !== get_option( 'grmlt_diphthongs', false )
		|| grmlt_table_exists( grmlt_legacy_table() );
}

/**
 * Writes the schema defaults for a fresh install.
 */
function grmlt_install_defaults() {
	foreach ( grmlt_options_schema() as $name => $def ) {
		add_option( $name, $def['default'] );
	}
}

/* Upgrade driver -------------------------------------------------------- */

/**
 * Activation hook. On a network activation only the current site is set up
 * here; every other site is set up lazily by grmlt_maybe_upgrade() on its
 * first request.
 *
 * @param bool $network_wide Network activation.
 */
function grmlt_activate( $network_wide = false ) {
	unset( $network_wide );
	grmlt_maybe_upgrade();
	flush_rewrite_rules();
}

/**
 * Deactivation hook.
 */
function grmlt_deactivate() {
	delete_option( 'grmlt_upgrade_lock' );
	flush_rewrite_rules();
}

/**
 * Seconds the migration may work in this request (0 = no limit).
 *
 * @return int
 */
function grmlt_upgrade_time_budget() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		$budget = 0;
	} elseif ( is_admin() || wp_doing_cron() ) {
		$budget = 15; // Leaves room under the common 30 s max_execution_time.
	} else {
		$budget = 3; // A visitor's request: keep it short, the rest follows.
	}
	/**
	 * Filters how long the 4.0 migration may run in one request.
	 *
	 * @since 4.0.0
	 * @param int $budget Seconds; 0 means no limit.
	 */
	return max( 0, (int) apply_filters( 'grmlt_upgrade_time_budget', $budget ) );
}

/**
 * Runs install/upgrade once per site when the stored version is older than
 * the plugin version. Cheap when nothing is pending (two autoloaded options).
 */
function grmlt_maybe_upgrade() {
	$installed = get_option( 'grmlt_version', false );
	if ( false !== $installed && version_compare( $installed, GRMLT_PLUGIN_VERSION, '>=' ) ) {
		if ( (int) get_option( 'grmlt_db_revision', 0 ) < GRMLT_DB_REVISION ) {
			// The repairs look at post types, so they wait until every one is registered.
			if ( did_action( 'wp_loaded' ) ) {
				grmlt_maybe_repair();
			} elseif ( ! has_action( 'wp_loaded', 'grmlt_maybe_repair' ) ) {
				add_action( 'wp_loaded', 'grmlt_maybe_repair' );
			}
		}
		return;
	}
	if ( false !== get_transient( 'grmlt_upgrade_backoff' ) ) {
		return; // A previous attempt failed; waiting (an administrator can retry now).
	}
	if ( ! grmlt_lock_acquire( 'grmlt_upgrade_lock', 5 * MINUTE_IN_SECONDS ) ) {
		return;
	}
	try {
		$budget   = grmlt_upgrade_time_budget();
		$deadline = $budget > 0 ? microtime( true ) + $budget : 0;
		if ( false === $installed && ! grmlt_is_legacy_install() ) {
			if ( grmlt_create_tables_or_back_off() ) {
				grmlt_install_defaults();
				update_option( 'grmlt_db_revision', GRMLT_DB_REVISION );
				update_option( 'grmlt_version', GRMLT_PLUGIN_VERSION );
			}
			return;
		}
		$from = false === $installed ? '3.4.1' : $installed;
		if ( version_compare( $from, '4.0.0', '<' ) ) {
			grmlt_upgrade_400( $deadline ); // Writes the version itself when every step is done.
			return;
		}
		if ( grmlt_create_tables_or_back_off() ) {
			update_option( 'grmlt_version', GRMLT_PLUGIN_VERSION );
		}
	} catch ( Throwable $e ) {
		grmlt_migration_transaction( 'rollback' );
		grmlt_upgrade_failed( $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')' );
	} finally {
		grmlt_migration_writing( false );
		grmlt_lock_release( 'grmlt_upgrade_lock' );
	}
}

/**
 * One-off repairs for sites already on the current version (bumped with
 * GRMLT_DB_REVISION), run on wp_loaded. Revision 2: removes "auto" redirects
 * that pre-release builds of 4.0 recorded for single posts and products
 * whose address started with a Greek category (see
 * GRMLT_Redirects::delete_misplaced_auto_rows()). Revision 3: no redirect
 * stays linked to a post without a public address, e.g. an ACF field that
 * pre-release builds migrated from 3.x: rows that still lead to public
 * content keep a fixed path, the rest is deleted (see
 * GRMLT_Redirects::repair_rows_without_public_post()). The numbers are kept
 * in grmlt_repair_log.
 */
function grmlt_maybe_repair() {
	if ( ! grmlt_lock_acquire( 'grmlt_upgrade_lock', 5 * MINUTE_IN_SECONDS ) ) {
		return;
	}
	try {
		$revision = (int) get_option( 'grmlt_db_revision', 0 );
		if ( grmlt_table_exists( grmlt_redirects_table() ) ) {
			if ( $revision < 2 ) {
				GRMLT_Redirects::delete_misplaced_auto_rows();
			}
			if ( $revision < 3 ) {
				$result = GRMLT_Redirects::repair_rows_without_public_post();
				update_option(
					'grmlt_repair_log',
					array(
						'time'                        => time(),
						'internal_redirects_deleted'  => $result['deleted'],
						'internal_redirects_unlinked' => $result['unlinked'],
					),
					false
				);
				grmlt_invalidate_health_cache();
			}
		}
		update_option( 'grmlt_db_revision', GRMLT_DB_REVISION );
	} catch ( Throwable $e ) {
		unset( $e ); // Retried on the next request; nothing depends on it.
	} finally {
		grmlt_lock_release( 'grmlt_upgrade_lock' );
	}
}

/**
 * Opens, commits or rolls back the migration's transaction, remembering
 * whether one is open so an error can roll it back.
 *
 * @param string $action begin|commit|rollback.
 */
function grmlt_migration_transaction( $action ) {
	global $wpdb;
	static $open = false;
	if ( 'begin' === $action ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );
		$open = true;
	} elseif ( $open && 'commit' === $action ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );
		$open = false;
	} elseif ( $open && 'rollback' === $action ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'ROLLBACK' );
		$open = false;
	}
}

/**
 * Creates the tables; after three failures in a row the migration backs off
 * for an hour instead of retrying dbDelta on every request.
 *
 * @return bool
 */
function grmlt_create_tables_or_back_off() {
	if ( grmlt_create_tables() ) {
		delete_option( 'grmlt_upgrade_db_failures' );
		return true;
	}
	$failures = (int) get_option( 'grmlt_upgrade_db_failures', 0 ) + 1;
	update_option( 'grmlt_upgrade_db_failures', $failures, false );
	if ( $failures >= 3 ) {
		global $wpdb;
		grmlt_upgrade_failed( 'The database tables could not be created' . ( $wpdb->last_error ? ': ' . $wpdb->last_error : '.' ) );
		delete_option( 'grmlt_upgrade_db_failures' );
	}
	return false;
}

/**
 * Records a failed migration attempt and backs off for an hour.
 *
 * @param string $message What went wrong.
 */
function grmlt_upgrade_failed( $message ) {
	$state = get_option( 'grmlt_migration_state', array() );
	$state = is_array( $state ) ? $state : array();
	$state['failed'] = array(
		'time'    => time(),
		'step'    => isset( $state['step'] ) ? (string) $state['step'] : '',
		'message' => substr( (string) $message, 0, 500 ),
	);
	update_option( 'grmlt_migration_state', $state, false );
	set_transient( 'grmlt_upgrade_backoff', 1, HOUR_IN_SECONDS );
	if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
		error_log( 'Greek Multi Tool: the 4.0 migration stopped and will be retried in an hour: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

/**
 * The failed attempt of the migration, if it is waiting after an error.
 *
 * @return array{time:int, step:string, message:string}|null
 */
function grmlt_upgrade_failure() {
	if ( false !== get_option( 'grmlt_version', false ) ) {
		return null;
	}
	$state = get_option( 'grmlt_migration_state', array() );
	return is_array( $state ) && ! empty( $state['failed'] ) && is_array( $state['failed'] ) ? $state['failed'] : null;
}

/**
 * Lets an administrator retry a migration that is waiting after an error.
 */
function grmlt_upgrade_retry() {
	delete_transient( 'grmlt_upgrade_backoff' );
	$state = get_option( 'grmlt_migration_state', array() );
	if ( is_array( $state ) ) {
		unset( $state['failed'], $state['attempt_key'], $state['attempts'] );
		update_option( 'grmlt_migration_state', $state, false );
	}
}

/**
 * Kept for backwards compatibility with 4.0 pre-releases.
 *
 * @param string $from Previously installed version.
 */
function grmlt_run_upgrade( $from ) {
	unset( $from );
	grmlt_maybe_upgrade();
}

/* Migration to 4.0.0 ---------------------------------------------------- */

/**
 * Migration to 4.0.0, resumable. The settings are migrated first: they need
 * no tables, so a host that refuses CREATE TABLE still gets them right.
 * Writes grmlt_version (before deleting its state) and returns true when
 * every step is complete.
 *
 * @param float $deadline microtime() after which no new chunk starts (0 = none).
 * @return bool
 */
function grmlt_upgrade_400( $deadline = 0 ) {
	$state = get_option( 'grmlt_migration_state', array() );
	$state = is_array( $state ) ? $state : array();
	$state = wp_parse_args(
		$state,
		array(
			'step'         => 'features',
			'cursor'       => 0,
			'options_done' => false,
			'log'          => array(
				'time'                    => time(),
				'from'                    => '3.x',
				'redirects_total'         => 0,
				'redirects_migrated'      => 0,
				'redirects_skipped'       => array(),
				'redirects_dropped'       => array(),
				'redirects_dropped_count' => 0,
				'legacy_table_dropped'    => false,
				'legacy_table_backup'     => '',
			),
		)
	);
	unset( $state['failed'] );

	$steps = array( 'features', 'options', 'tables', 'redirects', 'legacy_table', 'snapshot', 'snapshot_terms', 'done' );
	while ( 'done' !== $state['step'] ) {
		$index = array_search( $state['step'], $steps, true );
		if ( false === $index ) {
			$state['step'] = 'features'; // Unknown state: every step is idempotent.
			continue;
		}
		$next = $steps[ $index + 1 ];

		// Redirects need every post type (whether a post has a public address)
		// and the rewrite rules; terms of custom taxonomies (WooCommerce
		// registers its own on init 5) every taxonomy. All are registered by
		// wp_loaded, which runs in the same request.
		if ( in_array( $state['step'], array( 'redirects', 'snapshot_terms' ), true ) && ! did_action( 'wp_loaded' ) ) {
			if ( ! has_action( 'wp_loaded', 'grmlt_maybe_upgrade' ) ) {
				add_action( 'wp_loaded', 'grmlt_maybe_upgrade' );
			}
			update_option( 'grmlt_migration_state', $state, false );
			return false;
		}

		// A step that dies (memory, time limit) three times at the same point
		// is not retried on every request: the migration waits and says so.
		$key = $state['step'] . ':' . (int) $state['cursor'] . ':' . ( isset( $state['snap_cursor'] ) ? (int) $state['snap_cursor'] : 0 );
		if ( isset( $state['attempt_key'] ) && $state['attempt_key'] === $key ) {
			$state['attempts'] = isset( $state['attempts'] ) ? (int) $state['attempts'] + 1 : 1;
		} else {
			$state['attempt_key'] = $key;
			$state['attempts']    = 1;
		}
		if ( $state['attempts'] > 3 ) {
			$state['attempts'] = 0;
			update_option( 'grmlt_migration_state', $state, false );
			grmlt_upgrade_failed( sprintf( 'The step "%s" stopped three times at the same point, probably because of the PHP memory or time limit.', $state['step'] ) );
			return false;
		}
		update_option( 'grmlt_migration_state', $state, false ); // Saved before the work, so a fatal error is counted.

		switch ( $state['step'] ) {
			case 'features':
				grmlt_upgrade_400_features( $state );
				$state['step'] = $next;
				break;
			case 'options':
				grmlt_upgrade_400_options( $state );
				$state['options_done'] = true;
				$state['step']         = $next;
				break;
			case 'tables':
				if ( ! grmlt_create_tables_or_back_off() ) {
					// Settings are migrated; redirects wait for the tables. The state
					// was saved before this attempt (and may now carry the failure).
					return false;
				}
				$state['step'] = $next;
				break;
			case 'redirects':
				// One batch per iteration; the state is saved after each one.
				$finished = grmlt_upgrade_400_redirects_batch( $state );
				if ( ! $finished ) {
					update_option( 'grmlt_migration_state', $state, false );
					if ( grmlt_upgrade_out_of_time( $deadline ) ) {
						return false; // The migrated rows already work.
					}
					continue 2;
				}
				$state['step'] = $next;
				break;
			case 'legacy_table':
				grmlt_upgrade_400_legacy_table( $state['log'] );
				$state['step'] = $next;
				break;
			case 'snapshot':
				if ( ! grmlt_upgrade_400_snapshot_chunk( $state ) ) {
					update_option( 'grmlt_migration_state', $state, false );
					if ( grmlt_upgrade_out_of_time( $deadline ) ) {
						return false;
					}
					continue 2;
				}
				$state['step'] = $next;
				break;
			case 'snapshot_terms':
				if ( ! grmlt_upgrade_400_term_redirects( $state ) ) {
					update_option( 'grmlt_migration_state', $state, false );
					if ( grmlt_upgrade_out_of_time( $deadline ) ) {
						return false;
					}
					continue 2;
				}
				$state['step'] = $next;
				break;
		}
		update_option( 'grmlt_migration_state', $state, false );
	}

	// Done. The version is written before the state is deleted: a request
	// that stops in between leaves a finished site, never a second mapping.
	update_option( 'grmlt_migration_log', $state['log'], false );
	update_option( 'grmlt_db_revision', GRMLT_DB_REVISION );
	update_option( 'grmlt_version', GRMLT_PLUGIN_VERSION );
	delete_option( 'grmlt_migration_state' );
	delete_option( 'grmlt_options_written' );
	if ( function_exists( 'grmlt_invalidate_health_cache' ) ) {
		grmlt_invalidate_health_cache();
	}
	return true;
}

/**
 * Whether the request's time budget is used up (renews the lock otherwise;
 * when another request took the lock over, this one stops too).
 *
 * @param float $deadline Deadline (0 = none).
 * @return bool
 */
function grmlt_upgrade_out_of_time( $deadline ) {
	if ( $deadline > 0 && microtime( true ) >= $deadline ) {
		return true;
	}
	return ! grmlt_lock_refresh( 'grmlt_upgrade_lock' );
}

/**
 * Step 1: keeps a copy of the 2.x/3.x settings exactly as they are stored
 * (step 2 maps from that copy only, so running it twice cannot map a value
 * twice), and records which removed features were active. Reproduces the
 * 3.x checks: dates and search were on unless switched off (both appeared
 * in 3.0; a 2.x site cannot be told apart from a 3.x site that never saved
 * those tabs), the uppercase script only with grmlt_uar_js 'on', excerpts
 * when the option was truthy.
 *
 * @param array $state Migration state (by reference).
 */
function grmlt_upgrade_400_features( &$state ) {
	if ( ! isset( $state['raw_options'] ) || ! is_array( $state['raw_options'] ) ) {
		$raw = array();
		foreach ( array_keys( grmlt_options_schema() ) as $name ) {
			$raw[ $name ] = get_option( $name, null );
		}
		$state['raw_options'] = $raw;
	}
	if ( false !== get_option( 'grmlt_removed_features', false ) ) {
		return;
	}
	$removed = array();
	if ( 'on' === get_option( 'grmlt_localize_dates', 'on' ) ) {
		$removed[] = 'dates';
	}
	if ( 'on' === get_option( 'grmlt_uar_js', false ) ) {
		$removed[] = 'uppercase';
	}
	if ( 'on' === get_option( 'grmlt_enhance_search', 'on' ) ) {
		$removed[] = 'search';
	}
	if ( (bool) get_option( 'grmlt_enable_excerpts', false ) ) {
		$removed[] = 'excerpts';
	}
	update_option( 'grmlt_removed_features', $removed, false );
}

/**
 * Step 2: writes the 4.0 value of every setting, mapped from the copy of
 * step 1 exactly as 2.x/3.x read it (grmlt_legacy_option_value()). Settings
 * that 4.0 itself saved in the meantime (form, reset) are kept. Deletes the
 * options of the removed features.
 *
 * @param array $state Migration state.
 */
function grmlt_upgrade_400_options( $state ) {
	$raw     = isset( $state['raw_options'] ) && is_array( $state['raw_options'] ) ? $state['raw_options'] : array();
	$written = grmlt_options_written();
	grmlt_migration_writing( true );
	try {
		foreach ( array_keys( grmlt_options_schema() ) as $name ) {
			if ( in_array( $name, $written, true ) ) {
				continue;
			}
			$value = array_key_exists( $name, $raw ) ? $raw[ $name ] : get_option( $name, null );
			update_option( $name, grmlt_legacy_option_value( $name, $value ) );
		}
		foreach ( array( 'grmlt_uar_js', 'grmlt_enable_excerpts', 'grmlt_excerpt_length', 'grmlt_excerpt_more', 'grmlt_enhance_search', 'grmlt_accent_insensitive_search', 'grmlt_search_post_types', 'grmlt_localize_dates', 'grmlt_date_format', 'grmlt_custom_date_format' ) as $name ) {
			delete_option( $name );
		}
	} finally {
		grmlt_migration_writing( false );
	}
}

/**
 * Columns of the old table, lower case.
 *
 * @return string[]
 */
function grmlt_legacy_table_columns() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$columns = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', grmlt_legacy_table() ) );
	return array_map( 'strtolower', array_map( 'strval', $columns ) );
}

/**
 * Step 4: moves one batch of rows from the old table. The table has the
 * same layout from 2.3.1 to 3.4.1 (permalink_id, post_id, redirect_type,
 * old_permalink, new_permalink); a table without the columns we need is not
 * read and is kept as a backup. Only posts were ever written to it (terms
 * were converted without a redirect), so post_id is only ever a post id.
 * The rows always belonged to this site: the host is ignored, the path kept.
 *
 * @param array $state Migration state (by reference).
 * @return bool True when the old table has been read completely.
 */
function grmlt_upgrade_400_redirects_batch( &$state ) {
	global $wpdb;
	$legacy = grmlt_legacy_table();
	if ( ! grmlt_table_exists( $legacy ) ) {
		return true;
	}
	$columns = grmlt_legacy_table_columns();
	foreach ( array( 'permalink_id', 'old_permalink', 'new_permalink' ) as $needed ) {
		if ( ! in_array( $needed, $columns, true ) ) {
			$state['log']['legacy_schema'] = 'unknown';
			return true; // Step 5 keeps the table as a backup.
		}
	}
	$limit = 200;
	if ( 0 === (int) $state['cursor'] ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$state['log']['redirects_total'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $legacy ) );
	}
	if ( in_array( 'post_id', $columns, true ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT permalink_id, post_id, old_permalink, new_permalink FROM %i WHERE permalink_id > %d ORDER BY permalink_id ASC LIMIT %d', $legacy, (int) $state['cursor'], $limit ) );
	} else {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT permalink_id, 0 AS post_id, old_permalink, new_permalink FROM %i WHERE permalink_id > %d ORDER BY permalink_id ASC LIMIT %d', $legacy, (int) $state['cursor'], $limit ) );
	}

	$items    = array();
	$hashes   = array();
	$post_ids = array();
	foreach ( $rows as $row ) {
		$state['cursor'] = (int) $row->permalink_id;
		$old             = trim( (string) $row->old_permalink );
		$new             = trim( (string) $row->new_permalink );
		if ( '' === $old || '' === $new ) {
			grmlt_migration_skip( $state['log'], $row, 'empty' );
			continue;
		}
		$old_path = GRMLT_Redirects::normalize_path( $old );
		if ( '' === $old_path ) {
			grmlt_migration_skip( $state['log'], $row, 'empty_path' );
			continue;
		}
		$hash       = GRMLT_Redirects::hash( $old_path );
		$items[]    = array( $row, $old_path, GRMLT_Redirects::normalize_path( $new ), $hash );
		$hashes[]   = $hash;
		$post_ids[] = absint( $row->post_id );
	}

	// Existing paths and live post ids: one query each for the whole batch.
	$existing = array();
	if ( $hashes ) {
		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$found = $wpdb->get_results( $wpdb->prepare( "SELECT id, old_path_hash, object_type, run_id, old_path, new_path FROM %i WHERE old_path_hash IN ({$placeholders})", array_merge( array( grmlt_redirects_table() ), $hashes ) ) );
		foreach ( (array) $found as $found_row ) {
			$existing[ $found_row->old_path_hash ] = array(
				'id'          => (int) $found_row->id,
				'object_type' => (string) $found_row->object_type,
				'run_id'      => (string) $found_row->run_id,
				'legacy'      => (object) array(
					'permalink_id'  => 0,
					'old_permalink' => (string) $found_row->old_path,
					'new_permalink' => (string) $found_row->new_path,
				),
			);
		}
	}
	$post_ids = array_values( array_filter( array_unique( $post_ids ) ) );
	if ( $post_ids ) {
		_prime_post_caches( $post_ids, false, false );
	}

	// One transaction per batch: much faster than a commit per row, and a
	// request that dies halfway leaves neither rows nor a moved cursor behind
	// (the state is saved by the caller after this function returns).
	grmlt_migration_transaction( 'begin' );
	foreach ( $items as $item ) {
		list( $row, $old_path, $new_path, $hash ) = $item;
		// Linked to the post only when it has a public address. Otherwise
		// (3.x also rewrote ACF fields, field groups and other internal posts,
		// and posts may have been deleted since) the row is kept only with a
		// fixed path that leads to public content, and left out on purpose
		// otherwise: such a row could only ever send visitors nowhere.
		$post_id = absint( $row->post_id );
		$post    = $post_id ? get_post( $post_id ) : null;
		$is_post = $post && GRMLT_Redirects::post_is_public( $post );
		if ( isset( $existing[ $hash ] ) ) {
			$prev = $existing[ $hash ];
			// A fixed-path row this migration made for the same old address
			// (an ACF post type named like a page, for example) gives way to
			// the row of the public post: the redirect then follows the post.
			// The earlier 3.x row counts as left out, this one as migrated.
			if ( $is_post && 'manual' === $prev['object_type'] && 'legacy-3x' === $prev['run_id'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- our own table.
				$wpdb->update(
					grmlt_redirects_table(),
					array(
						'object_type' => 'post',
						'object_id'   => $post_id,
						'new_path'    => $new_path,
					),
					array( 'id' => $prev['id'] ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
				grmlt_migration_drop( $state['log'], $prev['legacy'], 'replaced' );
				$existing[ $hash ]['object_type'] = 'post';
				continue;
			}
			grmlt_migration_skip( $state['log'], $row, 'duplicate' );
			continue;
		}
		if ( ! $is_post && ! GRMLT_Redirects::is_public_target( $new_path ) ) {
			grmlt_migration_drop( $state['log'], $row, $post ? 'internal_object' : 'no_public_target' );
			continue;
		}
		$id = GRMLT_Redirects::insert_row( $is_post ? 'post' : 'manual', $is_post ? $post_id : 0, $old_path, $new_path, 'legacy-3x' );
		if ( ! $id ) {
			grmlt_migration_skip( $state['log'], $row, 'db_error' );
			continue;
		}
		$existing[ $hash ] = array(
			'id'          => $id,
			'object_type' => $is_post ? 'post' : 'manual',
			'run_id'      => 'legacy-3x',
			'legacy'      => $row,
		);
		++$state['log']['redirects_migrated'];
	}
	grmlt_migration_transaction( 'commit' );
	return count( $rows ) < $limit;
}

/**
 * Step 5: drops the old table only when every row was accounted for and the
 * new table really holds the migrated rows; otherwise renames it to a new,
 * dated backup table so nothing is lost.
 *
 * @param array $log Migration log (by reference).
 */
function grmlt_upgrade_400_legacy_table( &$log ) {
	global $wpdb;
	$legacy = grmlt_legacy_table();
	if ( ! grmlt_table_exists( $legacy ) ) {
		return;
	}
	// Benign: only duplicates and empty rows were skipped (counted per reason,
	// so it holds beyond the examples kept in the log).
	$benign = empty( $log['legacy_schema'] );
	if ( isset( $log['redirects_skipped_reasons'] ) ) {
		$reasons = array_keys( (array) $log['redirects_skipped_reasons'] );
	} else {
		// A log written before the counts per reason: the reasons beyond the list are unknown.
		$reasons = wp_list_pluck( (array) $log['redirects_skipped'], 'reason' );
		$benign  = $benign && empty( $log['redirects_skipped_more'] );
	}
	if ( array_diff( $reasons, array( 'duplicate', 'empty', 'empty_path' ) ) ) {
		$benign = false;
	}
	// Rows left out on purpose count as handled; they never keep the table.
	$dropped = isset( $log['redirects_dropped_count'] ) ? (int) $log['redirects_dropped_count'] : 0;
	$skipped = count( (array) $log['redirects_skipped'] ) + ( isset( $log['redirects_skipped_more'] ) ? (int) $log['redirects_skipped_more'] : 0 );
	$handled = (int) $log['redirects_migrated'] + $skipped + $dropped;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$stored = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE run_id = %s', grmlt_redirects_table(), 'legacy-3x' ) );

	if ( $benign && $handled >= (int) $log['redirects_total'] && $stored >= (int) $log['redirects_migrated'] ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $legacy ) );
		$log['legacy_table_dropped'] = true;
		return;
	}
	$base   = grmlt_legacy_backup_table() . '_' . gmdate( 'YmdHis' );
	$backup = $base;
	for ( $n = 2; grmlt_table_exists( $backup ) && $n < 100; $n++ ) {
		$backup = $base . '_' . $n;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $legacy, $backup ) );
	$log['legacy_table_backup'] = grmlt_table_exists( $backup ) ? $backup : '';
}

/**
 * An array view of a value that should have been an array (3.x data can be
 * an object after a serialize round trip, or something else entirely).
 *
 * @param mixed $value Value.
 * @return array
 */
function grmlt_as_array( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_object( $value ) ) {
		return get_object_vars( $value );
	}
	return array();
}

/**
 * Step 6, in chunks of 300 entries: the 3.4.1 undo snapshot becomes the
 * last undoable conversion (run "legacy"). Undoing it restores slugs but
 * never deletes the migrated redirects, because the 3.x rows cannot be
 * attributed to it with certainty. Each chunk is one transaction that also
 * saves the cursor, so an interrupted request never inserts an entry twice.
 * The snapshot is deleted at the end; when not every entry could be
 * migrated, a copy is kept as grmlt_legacy_undo_backup. The converted terms
 * are collected for step 7.
 *
 * @param array $state Migration state (by reference).
 * @return bool True when the snapshot is done.
 */
function grmlt_upgrade_400_snapshot_chunk( &$state ) {
	global $wpdb;
	$raw = get_option( 'grmlt_last_conversion_undo', null );
	if ( null === $raw || false === $raw ) {
		return true;
	}
	$snapshot = grmlt_as_array( $raw );
	$entries  = array();
	foreach ( array(
		'post' => 'posts',
		'term' => 'terms',
	) as $type => $key ) {
		foreach ( array_values( isset( $snapshot[ $key ] ) ? grmlt_as_array( $snapshot[ $key ] ) : array() ) as $change ) {
			$entries[] = array( $type, $change );
		}
	}
	$time    = isset( $snapshot['time'] ) && is_numeric( $snapshot['time'] ) ? (int) $snapshot['time'] : time();
	$created = gmdate( 'Y-m-d H:i:s', $time );
	$table   = grmlt_conversions_table();
	$cursor  = isset( $state['snap_cursor'] ) ? (int) $state['snap_cursor'] : 0;
	if ( 0 === $cursor ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'run_id' => 'legacy' ), array( '%s' ) ); // Idempotent on rerun.
		$state['snap']           = array(
			'post' => 0,
			'term' => 0,
			'bad'  => ( is_array( $raw ) || is_object( $raw ) ) ? 0 : 1,
		);
		$state['snapshot_terms'] = array();
	}

	grmlt_migration_transaction( 'begin' );
	foreach ( array_slice( $entries, $cursor, 300 ) as $item ) {
		++$cursor;
		list( $type, $change ) = $item;
		$change                = grmlt_as_array( $change );
		$id_key                = 'post' === $type ? 'ID' : 'term_id';
		$id                    = isset( $change[ $id_key ] ) && is_numeric( $change[ $id_key ] ) ? absint( $change[ $id_key ] ) : 0;
		$from                  = isset( $change['from'] ) && is_scalar( $change['from'] ) ? (string) $change['from'] : '';
		$to                    = isset( $change['to'] ) && is_scalar( $change['to'] ) ? (string) $change['to'] : '';
		if ( ! $id || '' === $from || '' === $to ) {
			++$state['snap']['bad'];
			continue;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			$table,
			array(
				'run_id'         => 'legacy',
				'object_type'    => $type,
				'object_subtype' => 'post' === $type && isset( $change['post_type'] ) && is_scalar( $change['post_type'] ) ? substr( (string) $change['post_type'], 0, 50 ) : '',
				'object_id'      => $id,
				'old_slug'       => $from,
				'new_slug'       => $to,
				'created_at'     => $created,
			)
		);
		if ( ! $ok ) {
			++$state['snap']['bad'];
			continue;
		}
		++$state['snap'][ $type ];
		if ( 'term' === $type ) {
			$state['snapshot_terms'][ $id ] = array(
				'from' => $from,
				'to'   => $to,
			);
		}
	}
	$state['snap_cursor'] = $cursor;
	update_option( 'grmlt_migration_state', $state, false ); // Inside the transaction: rows and cursor together.
	grmlt_migration_transaction( 'commit' );
	if ( $cursor < count( $entries ) ) {
		return false;
	}

	if ( $state['snap']['post'] + $state['snap']['term'] > 0 ) {
		update_option(
			'grmlt_last_conversion',
			array(
				'run_id'   => 'legacy',
				'time'     => $time,
				'posts'    => $state['snap']['post'],
				'terms'    => $state['snap']['term'],
				'mode'     => 'simple-legacy',
				'complete' => true,
			),
			false
		);
	}
	if ( $state['snap']['bad'] > 0 ) {
		update_option( 'grmlt_legacy_undo_backup', $raw, false );
		$state['log']['snapshot_skipped'] = $state['snap']['bad'];
	}
	delete_option( 'grmlt_last_conversion_undo' );
	$state['terms_cursor'] = 0;
	return true;
}

/**
 * Step 7, after wp_loaded (every taxonomy registered), in chunks of 200:
 * redirects from the old Greek addresses of the terms 3.4.1 converted (3.x
 * never wrote one; its old term URLs only worked through on-the-fly
 * transliteration). The old address is the current one with the converted
 * slugs (the term's and its converted ancestors') put back. Terms of a
 * taxonomy that is not registered (its plugin is inactive) are skipped.
 *
 * @param array $state Migration state (by reference).
 * @return bool True when every term was handled.
 */
function grmlt_upgrade_400_term_redirects( &$state ) {
	$terms  = isset( $state['snapshot_terms'] ) && is_array( $state['snapshot_terms'] ) ? $state['snapshot_terms'] : array();
	$ids    = array_keys( $terms );
	$cursor = isset( $state['terms_cursor'] ) ? (int) $state['terms_cursor'] : 0;
	foreach ( array_slice( $ids, $cursor, 200 ) as $term_id ) {
		++$cursor;
		$term = get_term( (int) $term_id );
		if ( ! ( $term instanceof WP_Term ) || ! taxonomy_exists( $term->taxonomy ) ) {
			$state['log']['snapshot_terms_skipped'] = isset( $state['log']['snapshot_terms_skipped'] ) ? $state['log']['snapshot_terms_skipped'] + 1 : 1;
			continue;
		}
		if ( $term->slug !== $terms[ $term_id ]['to'] ) {
			continue; // Changed again since; its current address is all we know.
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		$new_path = GRMLT_Redirects::normalize_path( $link );
		$segments = explode( '/', $new_path );
		$chain    = array_merge( array( (int) $term_id ), array_map( 'intval', get_ancestors( (int) $term_id, $term->taxonomy, 'taxonomy' ) ) );
		$changed  = false;
		foreach ( $chain as $id ) {
			if ( ! isset( $terms[ $id ] ) ) {
				continue;
			}
			$latin = GRMLT_Redirects::normalize_path( $terms[ $id ]['to'] );
			$greek = GRMLT_Redirects::normalize_path( $terms[ $id ]['from'] );
			for ( $i = count( $segments ) - 1; $i >= 0; $i-- ) {
				if ( $segments[ $i ] === $latin ) {
					$segments[ $i ] = $greek;
					$changed        = true;
					break;
				}
			}
		}
		$old_path = implode( '/', $segments );
		if ( ! $changed || $old_path === $new_path ) {
			continue;
		}
		$id = GRMLT_Redirects::insert(
			array(
				'object_type' => 'term',
				'object_id'   => (int) $term_id,
				'old_path'    => $old_path,
				'new_path'    => $new_path,
				'run_id'      => 'legacy-3x',
			)
		);
		if ( ! is_wp_error( $id ) ) {
			$state['log']['snapshot_term_redirects'] = isset( $state['log']['snapshot_term_redirects'] ) ? $state['log']['snapshot_term_redirects'] + 1 : 1;
		}
	}
	$state['terms_cursor'] = $cursor;
	if ( $cursor < count( $ids ) ) {
		return false;
	}
	unset( $state['snapshot_terms'] );
	return true;
}

/**
 * Records a legacy redirect left out on purpose, because it can lead
 * nowhere: internal_object (a post without a public address, e.g. an ACF
 * field), no_public_target (no such post, and the new address leads to no
 * public content), or replaced (a public post's row for the same old
 * address took its place). Counted in full and per reason; the first
 * GRMLT_MIGRATION_LOG_EXAMPLES are listed.
 *
 * @param array  $log    Log (by reference).
 * @param object $row    Legacy row.
 * @param string $reason Reason.
 */
function grmlt_migration_drop( &$log, $row, $reason ) {
	$log['redirects_dropped_count'] = isset( $log['redirects_dropped_count'] ) ? (int) $log['redirects_dropped_count'] + 1 : 1;
	grmlt_migration_count_reason( $log, 'redirects_dropped_reasons', $reason );
	if ( ! isset( $log['redirects_dropped'] ) || ! is_array( $log['redirects_dropped'] ) ) {
		$log['redirects_dropped'] = array();
	}
	if ( count( $log['redirects_dropped'] ) < GRMLT_MIGRATION_LOG_EXAMPLES ) {
		$log['redirects_dropped'][] = array(
			'id'     => (int) $row->permalink_id,
			'old'    => (string) $row->old_permalink,
			'new'    => (string) $row->new_permalink,
			'reason' => $reason,
		);
	}
}

/**
 * Adds one to the count of a reason in the migration log.
 *
 * @param array  $log    Log (by reference).
 * @param string $key    Log key of the counts.
 * @param string $reason Reason.
 */
function grmlt_migration_count_reason( &$log, $key, $reason ) {
	if ( ! isset( $log[ $key ] ) || ! is_array( $log[ $key ] ) ) {
		$log[ $key ] = array();
	}
	$log[ $key ][ $reason ] = isset( $log[ $key ][ $reason ] ) ? (int) $log[ $key ][ $reason ] + 1 : 1;
}

/**
 * Records a skipped legacy redirect: counted in full and per reason, the
 * first GRMLT_MIGRATION_LOG_EXAMPLES listed.
 *
 * @param array  $log    Log (by reference).
 * @param object $row    Legacy row.
 * @param string $reason Reason.
 */
function grmlt_migration_skip( &$log, $row, $reason ) {
	grmlt_migration_count_reason( $log, 'redirects_skipped_reasons', $reason );
	if ( count( $log['redirects_skipped'] ) < GRMLT_MIGRATION_LOG_EXAMPLES ) {
		$log['redirects_skipped'][] = array(
			'id'     => (int) $row->permalink_id,
			'old'    => (string) $row->old_permalink,
			'new'    => (string) $row->new_permalink,
			'reason' => $reason,
		);
	} else {
		$log['redirects_skipped_more'] = isset( $log['redirects_skipped_more'] ) ? $log['redirects_skipped_more'] + 1 : 1;
	}
}
