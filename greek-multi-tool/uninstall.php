<?php
/**
 * Fired when the plugin is uninstalled: removes every table, option,
 * transient and user meta the plugin ever created (3.x and 4.x).
 *
 * The _wp_old_slug post meta added by the converter is left in place: it is
 * WordPress data and keeps the old post URLs working without the plugin.
 *
 * @package Grmlt_Plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleans the current site.
 */
function grmlt_uninstall_site() {
	global $wpdb;

	$tables = array( $wpdb->prefix . 'grmlt_redirects', $wpdb->prefix . 'grmlt_conversions', $wpdb->prefix . 'grmlt' );
	// Every dated backup of the 3.x table (grmlt_legacy_backup_YYYYmmddHHiiss).
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$backups = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'grmlt_legacy_backup' ) . '%' ) );
	foreach ( array_merge( $tables, $backups ) as $table ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	}

	$options = array(
		// 4.x.
		'grmlt_text',
		'grmlt_diphthongs',
		'grmlt_one_letter_words',
		'grmlt_two_letter_words',
		'grmlt_stwords',
		'grmlt_media_file_name',
		'grmlt_enable_text_analysis',
		'grmlt_redirect',
		'grmlt_version',
		'grmlt_db_version',
		'grmlt_removed_features',
		'grmlt_migration_log',
		'grmlt_last_conversion',
		'grmlt_conversion_run',
		'grmlt_conversion_lock',
		'grmlt_migration_state',
		'grmlt_upgrade_lock',
		'grmlt_conversion_cancel',
		'grmlt_upgrade_db_failures',
		'grmlt_legacy_undo_backup',
		'grmlt_options_written',
		'grmlt_db_revision',
		'grmlt_repair_log',
		// 3.x.
		'grmlt_uar_js',
		'grmlt_enable_excerpts',
		'grmlt_excerpt_length',
		'grmlt_excerpt_more',
		'grmlt_enhance_search',
		'grmlt_accent_insensitive_search',
		'grmlt_search_post_types',
		'grmlt_localize_dates',
		'grmlt_date_format',
		'grmlt_custom_date_format',
		'grmlt_last_conversion_undo',
	);
	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Transients (and their timeouts), including per-user notice transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_grmlt_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_grmlt_' ) . '%'
		)
	);

	delete_metadata( 'user', 0, 'grmlt_dismissed_notice_400', '', true );
	delete_metadata( 'user', 0, 'grmlt_dismissed_migration_notice_400', '', true );

	// Backups of wp grmlt acf-restore-keys (uploads/grmlt-acf-backups).
	$uploads = wp_upload_dir( null, false );
	$dir     = empty( $uploads['error'] ) ? trailingslashit( $uploads['basedir'] ) . 'grmlt-acf-backups' : '';
	if ( '' !== $dir && is_dir( $dir ) ) {
		foreach ( (array) scandir( $dir ) as $name ) {
			if ( is_file( $dir . '/' . $name ) ) {
				wp_delete_file( $dir . '/' . $name );
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- empty folder; left in place if the host refuses.
	}
}

if ( is_multisite() ) {
	$grmlt_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $grmlt_site_ids as $grmlt_site_id ) {
		switch_to_blog( $grmlt_site_id );
		grmlt_uninstall_site();
		restore_current_blog();
	}
} else {
	grmlt_uninstall_site();
}
