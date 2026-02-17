<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://bigdrop.gr
 * @since      1.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin
 * @author     BigDrop.gr <info@bigdrop.gr>
 */
class Grmlt_Plugin_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Database Initialization
		$this->init_db_myplugin();

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Grmlt_Plugin_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Grmlt_Plugin_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		$current_screen = get_current_screen();

		if ( ! $current_screen || strpos( $current_screen->base, 'grmlt-main-settings' ) === false ) {
			return;
		}

		// Enqueue stylesheet of bootstrap V5.2.2
		wp_enqueue_style( 'grmlt_bootstrap_css', plugins_url( 'admin/css/bootstrap.min.css', dirname( __FILE__ ) ), array(), '5.2.2' );

		// Enqueue stylesheet of settings-page-body
		wp_enqueue_style( 'grmlt_settings_page_body_css', plugins_url( 'admin/css/settings-page-body.css', dirname( __FILE__ ) ), array(), $this->version );

		// Enqueue stylesheet of settings-page-switches
		wp_enqueue_style( 'grmlt_settings_page_switches_css', plugins_url( 'admin/css/settings-page-switches.css', dirname( __FILE__ ) ), array(), $this->version );

		// Enqueue stylesheet of Custom CSS for grmlt plugin admin area
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/grmlt-plugin-admin.css', array(), $this->version, 'all' );

		// Enqueue Font Awesome (moved from inline CDN link)
		wp_enqueue_style( 'grmlt_fontawesome_css', plugins_url( 'admin/css/fontawesome.min.css', dirname( __FILE__ ) ), array(), '5.15.4' );
    
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

	    /**
	     * This function is provided for demonstration purposes only.
	     *
	     * An instance of this class should be passed to the run() function
	     * defined in Grmlt_Plugin_Loader as all of the hooks are defined
	     * in that particular class.
	     *
	     * The Grmlt_Plugin_Loader will then create the relationship
	     * between the defined hooks and the functions defined in this
	     * class.
	     */
	    $current_screen = get_current_screen();

		if ( ! $current_screen || strpos( $current_screen->base, 'grmlt-main-settings' ) === false ) {
			return;
		}

		// Enqueue Popper.min.js (Used for Settings Page of the Plugin for TAB-PANE)
		wp_enqueue_script( 'grmlt_popper_js', plugins_url( 'admin/js/popper.min.js', dirname( __FILE__ ) ), array(), '2.11.6', true );

		// Enqueue Bootstrap.min.js
		wp_enqueue_script( 'grmlt_bootstrap_js', plugins_url( 'admin/js/bootstrap.min.js', dirname( __FILE__ ) ), array( 'grmlt_popper_js' ), '5.2.2', true );

		// Enqueue Javascript for Custom JS Scripts in Admin area
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/grmlt-plugin-admin.js', array( 'jquery' ), $this->version, true );

		// Add security nonces for AJAX operations
		wp_localize_script(
			$this->plugin_name,
			'grmlt_vars',
			array(
				'ajaxurl'                => admin_url( 'admin-ajax.php' ),
				'permalink_delete_nonce' => wp_create_nonce( 'grmlt_permalink_delete_nonce' ),
				'permalink_edit_nonce'   => wp_create_nonce( 'grmlt_permalink_edit_nonce' ),
				'delete_confirm_text'    => __( 'Are you sure you want to delete this permalink?', 'greek-multi-tool' ),
			)
		);
	}
	
	/**
	 * Register plugin menu for the admin area
	 *
	 * @since    1.0.0
	 */
	public function register_grmlt_menu() {

		/**
		 * Require plugin's admin area display loader ( for loading settings page, etc... ) 
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/grmlt-plugin-admin-display-loader.php';

		add_action('admin_menu', 'grmlt_admin_menu');

	}

	/**
	 * On admin page initialization check for database existence
	 *
	 * @since    1.0.0
	 */
	public function init_db_myplugin() {

		global $wpdb;

		$table_name = $wpdb->prefix . 'grmlt';
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( $table_exists !== $table_name ) {

			$sql = "CREATE TABLE `{$table_name}` (
				`permalink_id` int(11) NOT NULL AUTO_INCREMENT,
				`post_id` int(25) NOT NULL,
				`redirect_type` varchar(255) NOT NULL,
				`old_permalink` text NOT NULL,
				`new_permalink` text NOT NULL,
				PRIMARY KEY (`permalink_id`)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

	}

	/**
	 * Function Triggers based on user Options
	 *
	 * @since    1.0.0
	 */
	public static function register_grmlt_option_triggers() {

	    // Check if grmlt_text is set to on ( The Greeklish Permalinks Switch in settings page ).
	    if  (get_option( 'grmlt_text' ) == 'on'){

	        // Check if grmlt_diphthongs is set to `Enabled` ( The Greeklish Permalinks Convert Diphthongs Switch in settings page ).
	        if  (get_option( 'grmlt_diphthongs' ) == 'simple'){
	            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/functions/translator-diphthongs.php';
	            add_filter('sanitize_title', 'grmlt_title_sanitizer_diphthongs_simple', 1);
	        } else {
	            require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/functions/translator-diphthongs.php';
	            add_filter('sanitize_title', 'grmlt_title_sanitizer_diphthongs_advanced', 1);
	        }

	        // Call and add_filter for title sanitization when text sanitize option is 'enabled'.
	        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/functions/translator.php';
	        add_filter('sanitize_title', 'grmlt_title_sanitizer', 1);
	    }

	    // Call old permalink conversion function (with nonce verification).
		if (
			isset( $_POST['oldpermalinks'] )
			&& current_user_can( 'manage_options' )
			&& isset( $_POST['grmlt_convert_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['grmlt_convert_nonce'] ) ), 'grmlt_convert_old_permalinks' )
		) {
		    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/functions/oldtranslator.php';
		}

	}

	/**
	 * Function Triggers based on user Options ( For Uppercase Accent Remover )
	 *
	 * @since    1.0.0
	 */
	public static function register_grmlt_option_trigger_uppercase_accent_remover() {

		// Check if grmlt_uar_js is set to on ( The Remove Uppercase Accents Switch in settings page ).
		if ( get_option( 'grmlt_uar_js' ) === 'on' ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_uppercase_accent_remover_script' ) );
		}

	}

	/**
	 * Enqueue the uppercase accent remover front-end script.
	 *
	 * @since 3.2.0
	 */
	public static function enqueue_uppercase_accent_remover_script() {
		wp_enqueue_script(
			'grmlt_custom_js',
			plugins_url( 'admin/functions/function.js', dirname( __FILE__ ) ),
			array(),
			GRMLT_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Function Caller for Redirect 301
	 *
	 * Hooks the redirect logic into template_redirect so it fires
	 * on the front-end before any output is sent.
	 *
	 * @since    1.0.0
	 */
	public static function call_grmlt_redirect_301() {

		// Only run on the front-end, never in admin or AJAX/cron contexts.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// Check if Redirect 301 option is set to on.
		if ( get_option( 'grmlt_redirect' ) == '1' ) {

			// Load the redirect function file.
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/functions/greek-multi-tool-redirect.php';

			// Hook into template_redirect which fires before output on front-end.
			add_action( 'template_redirect', 'grmlt_redirect', 1 );
		}

	}

}
