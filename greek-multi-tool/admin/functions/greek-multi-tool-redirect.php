<?php
/**
 * 301 Redirect handler.
 *
 * Hooked into `template_redirect` to create a bridge of URL identification
 * for 301 Redirections stored in the grmlt database table.
 *
 * @since   1.0.0
 * @package Grmlt_Plugin
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Perform 301 redirect if the current URL matches a stored old permalink.
 *
 * @since 1.0.0
 */
function grmlt_redirect() {
	// Don't run in admin, AJAX, or CLI contexts.
	if ( is_admin() || wp_doing_ajax() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	global $wpdb;

	// Build the current URL from server variables.
	$scheme = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
	$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	if ( empty( $host ) || empty( $uri ) ) {
		return;
	}

	$current_url           = esc_url_raw( $scheme . '://' . $host . $uri );
	$current_url_untrailed = untrailingslashit( $current_url );

	$table = $wpdb->prefix . 'grmlt';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$results = $wpdb->get_results(
		"SELECT `old_permalink`, `new_permalink` FROM `{$table}` WHERE 1"
	);

	if ( empty( $results ) || ! is_array( $results ) ) {
		return;
	}

	foreach ( $results as $row ) {
		$old_permalink = isset( $row->old_permalink ) ? trim( $row->old_permalink ) : '';
		$new_permalink = isset( $row->new_permalink ) ? trim( $row->new_permalink ) : '';

		if ( empty( $old_permalink ) || empty( $new_permalink ) ) {
			continue;
		}

		// Normalize for comparison: compare with and without trailing slashes,
		// and also compare URL-decoded versions for Greek character URLs.
		$old_untrailed = untrailingslashit( $old_permalink );

		if (
			$current_url === $old_permalink
			|| $current_url_untrailed === $old_untrailed
			|| rawurldecode( $current_url ) === rawurldecode( $old_permalink )
			|| rawurldecode( $current_url_untrailed ) === rawurldecode( $old_untrailed )
		) {
			$safe_redirect = esc_url_raw( $new_permalink );
			if ( ! empty( $safe_redirect ) ) {
				wp_redirect( $safe_redirect, 301 );
				exit;
			}
		}
	}
}
