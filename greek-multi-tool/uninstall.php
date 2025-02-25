<?php

/**
 * Fired when the plugin is uninstalled.
 *
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

// Delete Plugin's Database.
global $wpdb;
$table_name = $wpdb->prefix."grmlt";
$wpdb->query( "DROP TABLE $table_name" );

// Delete Options

    // Global Transliteration Option
    delete_option('grmlt_text');
    // Dipthong Option
    delete_option('grmlt_diphthongs');
    // One Letter Words Option
    delete_option('grmlt_one_letter_words');
    // Two Letter Words Option
    delete_option('grmlt_two_letter_words');
    // Stop Words Option
    delete_option('grmlt_stwords');
    // Remove Uppercase Accents Option
    delete_option('grmlt_uar_js');
    // Redirect 301 Option
    delete_option('grmlt_redirect');