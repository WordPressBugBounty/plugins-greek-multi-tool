<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://bigdrop.gr
 * @since      1.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/includes
 * @author     BigDrop.gr <info@bigdrop.gr>
 */
class Grmlt_Plugin_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {

        // flush rewrite rules
        flush_rewrite_rules();
        
	}

}
