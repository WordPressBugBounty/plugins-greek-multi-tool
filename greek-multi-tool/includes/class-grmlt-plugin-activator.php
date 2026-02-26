<?php

/**
 * Fired during plugin activation
 *
 * @link       https://bigdrop.gr
 * @since      1.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/includes
 * @author     BigDrop.gr <info@bigdrop.gr>
 */
class Grmlt_Plugin_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

        // Add Global Transliteration Option
        add_option( 'grmlt_text', 'on');

        // Add Dipthong Option
        add_option('grmlt_diphthongs', 'simple');

        // Remove Uppercase Accents Option
        add_option('grmlt_uar_js', 'on');

        // Redirect 301 Option
        add_option('grmlt_redirect', 1);

        // Media File Name Conversion Option
        add_option('grmlt_media_file_name', 'on');

        // flush rewrite rules
        flush_rewrite_rules();

    }

}
