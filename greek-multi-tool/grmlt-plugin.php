<?php
/**
 * @link              https://bigdrop.gr
 * @since             1.0.0
 * @package           Grmlt_Plugin
 *
 * @wordpress-plugin
 * Plugin Name:       Greek Multi Tool
 * Plugin URI:        https://bigdrop.gr/greek-multi-tool
 * Description:       Greek-to-Latin permalinks and media file names (ELOT 743), one-click conversion of old Greek permalinks with 301 redirects, a Greek accent checker for the editor, and Greek readiness checks in Site Health.
 * Version:           4.0.0
 * Author:            BigDrop.gr
 * Author URI:        https://bigdrop.gr
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       greek-multi-tool
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'GRMLT_PLUGIN_VERSION', '4.0.0' );
define( 'GRMLT_PLUGIN_FILE', __FILE__ );
define( 'GRMLT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRMLT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GRMLT_SETTINGS_PAGE', 'grmlt-main-settings' );

require_once GRMLT_PLUGIN_DIR . 'includes/options.php';
require_once GRMLT_PLUGIN_DIR . 'includes/install.php';
require_once GRMLT_PLUGIN_DIR . 'includes/transliteration.php';
require_once GRMLT_PLUGIN_DIR . 'includes/page-builder-compat.php';
require_once GRMLT_PLUGIN_DIR . 'includes/text-analysis.php';
require_once GRMLT_PLUGIN_DIR . 'includes/class-grmlt-redirects.php';
require_once GRMLT_PLUGIN_DIR . 'includes/class-grmlt-converter.php';
require_once GRMLT_PLUGIN_DIR . 'includes/class-grmlt-site-health.php';
require_once GRMLT_PLUGIN_DIR . 'includes/class-grmlt-rest.php';
require_once GRMLT_PLUGIN_DIR . 'includes/deprecated.php';

if ( is_admin() ) {
	require_once GRMLT_PLUGIN_DIR . 'includes/notices.php';
	require_once GRMLT_PLUGIN_DIR . 'admin/class-grmlt-admin.php';
	require_once GRMLT_PLUGIN_DIR . 'admin/editor.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once GRMLT_PLUGIN_DIR . 'includes/class-grmlt-cli.php';
	require_once GRMLT_PLUGIN_DIR . 'includes/cli-acf-restore.php';
}

register_activation_hook( __FILE__, 'grmlt_activate' );
register_deactivation_hook( __FILE__, 'grmlt_deactivate' );

// The upgrade check runs on every request (front end, REST, cron, CLI): it is
// one autoloaded option comparison unless a migration is actually pending.
add_action( 'init', 'grmlt_load_textdomain', 0 );
add_action( 'change_locale', 'grmlt_load_textdomain' );
add_action( 'init', 'grmlt_maybe_upgrade', 1 );
add_action( 'init', 'grmlt_register_transliteration_filters' );
add_action( 'init', array( 'GRMLT_Redirects', 'init' ) );
add_action( 'admin_init', 'grmlt_register_settings' );
add_action( 'rest_api_init', array( 'GRMLT_REST', 'register_routes' ) );
add_action( 'plugins_loaded', array( 'GRMLT_Site_Health', 'init' ) );

/**
 * Adds the "Settings" link on the Plugins screen.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function grmlt_plugin_action_links( $links ) {
	$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . GRMLT_SETTINGS_PAGE ) ) . '">' . esc_html__( 'Settings', 'greek-multi-tool' ) . '</a>';
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'grmlt_plugin_action_links' );

/**
 * Loads the plugin's translations.
 *
 * A language pack from translate.wordpress.org stays first, as in core. The
 * translations bundled in languages/ are loaded after it, so strings the pack
 * does not have yet (a pack made for an older version) are still translated.
 * WordPress before 6.8 also needs this to find the bundled files at all.
 *
 * @param string $locale Optional. Locale from change_locale; current locale otherwise.
 */
function grmlt_load_textdomain( $locale = '' ) {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- needed: WordPress before 6.8 does not find the bundled files on its own.
	load_plugin_textdomain( 'greek-multi-tool', false, dirname( plugin_basename( GRMLT_PLUGIN_FILE ) ) . '/languages' );

	$locale = $locale ? $locale : determine_locale();
	if ( 'en_US' === $locale ) {
		return;
	}
	$mofile = 'greek-multi-tool-' . $locale . '.mo';
	if ( ! is_textdomain_loaded( 'greek-multi-tool' ) ) {
		load_textdomain( 'greek-multi-tool', WP_LANG_DIR . '/plugins/' . $mofile, $locale );
	}
	load_textdomain( 'greek-multi-tool', GRMLT_PLUGIN_DIR . 'languages/' . $mofile, $locale );
}
