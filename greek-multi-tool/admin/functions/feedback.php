<?php
/**
 * Greek Multi-Tool Plugin - Feedback Module
 *
 * @link       https://bigdrop.gr
 * @since      2.4.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Feedback tab to the plugin settings page as the last tab
 */
function grmlt_add_feedback_tab($tabs) {
    // Add feedback tab at the end of the array to make it last
    $tabs['feedback'] = __('Feedback', 'greek-multi-tool');
    return $tabs;
}
// Use a LOWER priority number (like 20) to ensure this runs AFTER other tab additions
add_filter('grmlt_settings_tabs', 'grmlt_add_feedback_tab', 20);

/**
 * Display Feedback tab content
 */
function grmlt_display_feedback_tab_content() {
    // Include the tab content file
    include plugin_dir_path(dirname(dirname(__FILE__))) . 'admin/partials/settings-page/feedback-tab.php';
}
add_action('grmlt_settings_tab_feedback', 'grmlt_display_feedback_tab_content');