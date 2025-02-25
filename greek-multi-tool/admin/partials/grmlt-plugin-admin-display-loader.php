<?php

/**
 * Call all plugin's admin area partial files here
 *
 *
 * @link       https://bigdrop.gr
 * @since      1.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/partials
 */

function grmlt_admin_menu() {

    add_menu_page('Greek Multi Tool', 'Greek Multi Tool', 'manage_options', 'grmlt-main-settings', 'grmlt_main_page', 'dashicons-superhero', 40);

    /**
     * This function calls and registers the main settings page of the plugin 
     */
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'partials/settings-page/grmlt-plugin-admin-main-settings-page.php';

}