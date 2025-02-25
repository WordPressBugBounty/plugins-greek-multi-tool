<?php
/**
 * @link              https://bigdrop.gr
 * @since             1.0.0
 * @package           Grmlt_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Greek Multi Tool
 * Plugin URI:        https://bigdrop.gr/greek-multi-tool
 * Description:       This plugin provides a handful of tools and key functionalities to simplify and fix the greek language used in your webpage. For example it change the greek character urls to latin, remove the uppercase accents.
 * Version:           2.3.1
 * Author:            BigDrop.gr
 * Author URI:        https://bigdrop.gr
 * Tags: greek, permalinks, accent remover, accent remover, multi tool
 * Tested up to:      6.4.3
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       grmlt-plugin
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'GRMLT_PLUGIN_VERSION', '2.3.1' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-grmlt-plugin-activator.php
 */
function activate_grmlt_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-grmlt-plugin-activator.php';
	Grmlt_Plugin_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-grmlt-plugin-deactivator.php
 */
function deactivate_grmlt_plugin() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-grmlt-plugin-deactivator.php';
	Grmlt_Plugin_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_grmlt_plugin' );
register_deactivation_hook( __FILE__, 'deactivate_grmlt_plugin' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-grmlt-plugin.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    2.1.1
 */
function run_grmlt_plugin() {

	$plugin = new Grmlt_Plugin();
	$plugin->run();

}
run_grmlt_plugin();

add_filter( "plugin_action_links_". plugin_basename(__FILE__), 'grmlt_settings_link' );

// This is the settings_link method which is responsible for creating the settings page button along with the url used for it.
function grmlt_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=grmlt-main-settings">Settings</a>';
    
    array_push( $links, $settings_link );
    
    return $links;
}

// Database AJAX Endpoint Function
function grmlt_database_301_redirect_deletion_handler()
{   
    // Check if the AJAX action is set
    if (isset($_POST['action']) && $_POST['action'] === 'grmlt_database_301_redirect_deletion_handler') {

        global $wpdb;

        // Get the record ID from the AJAX request
        $record_id = $_POST['record_id'];

        // Database Table Name
        $grml_table_name = $wpdb->prefix . 'grmlt';

        // Perform the database deletion operation based on the record ID
        $result = $wpdb->delete($grml_table_name, array('permalink_id' => $record_id));

    } else {
      echo 'Invalid request';
    }
    wp_die();
}
add_action('wp_ajax_grmlt_database_301_redirect_deletion_handler', 'grmlt_database_301_redirect_deletion_handler');
add_action('wp_ajax_nopriv_grmlt_database_301_redirect_deletion_handler', 'grmlt_database_301_redirect_deletion_handler');

// Database AJAX Endpoint Function
function grmlt_database_301_redirect_edit_handler()
{   
    // Check if the AJAX action is set
    if (isset($_POST['action']) && $_POST['action'] === 'grmlt_database_301_redirect_edit_handler') {

        global $wpdb;

        // Database Table Name
        $grml_table_name = $wpdb->prefix . 'grmlt';

        // Get the record values from the AJAX request
        $record_id = $_POST['record_id'];
        $record_old_permalink_value = $_POST['record_oldPermalinkValue'];
        $record_new_permalink_value = $_POST['record_newPermalinkValue'];

        // Query Database for record update
        $wpdb->update($grml_table_name, array('old_permalink' => $record_old_permalink_value,'new_permalink' => $record_new_permalink_value), array('permalink_id' => $record_id));

    } else {
      echo 'Invalid request';
    }
    wp_die();
}
add_action('wp_ajax_grmlt_database_301_redirect_edit_handler', 'grmlt_database_301_redirect_edit_handler');
add_action('wp_ajax_nopriv_grmlt_database_301_redirect_edit_handler', 'grmlt_database_301_redirect_edit_handler');