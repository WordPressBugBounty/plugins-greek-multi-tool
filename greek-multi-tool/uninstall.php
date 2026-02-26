<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://bigdrop.gr
 * @since      2.1.0
 *
 * @package    Grmlt_Plugin
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete Plugin's Database Table.
global $wpdb;
$table_name = $wpdb->prefix . 'grmlt';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `%1s`', $table_name ) );

// Delete Options.
delete_option( 'grmlt_text' );
delete_option( 'grmlt_diphthongs' );
delete_option( 'grmlt_one_letter_words' );
delete_option( 'grmlt_two_letter_words' );
delete_option( 'grmlt_stwords' );
delete_option( 'grmlt_uar_js' );
delete_option( 'grmlt_redirect' );
delete_option( 'grmlt_media_file_name' );
delete_option( 'grmlt_enhance_search' );
delete_option( 'grmlt_accent_insensitive_search' );
delete_option( 'grmlt_search_post_types' );
delete_option( 'grmlt_localize_dates' );
delete_option( 'grmlt_date_format' );
delete_option( 'grmlt_custom_date_format' );
delete_option( 'grmlt_enable_text_analysis' );
delete_option( 'grmlt_enable_excerpts' );
delete_option( 'grmlt_excerpt_length' );
delete_option( 'grmlt_excerpt_more' );
