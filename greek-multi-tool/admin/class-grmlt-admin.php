<?php
/**
 * The settings screen: menu, tabs, assets, admin-post handlers.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once GRMLT_PLUGIN_DIR . 'admin/menu-builder.php';

class GRMLT_Admin {

	const HOOK = 'toplevel_page_grmlt-main-settings';

	/**
	 * Core notices that stay on the plugin screen: security, and anything that
	 * stops the site or a plugin from working. From wp-admin/includes/admin-filters.php
	 * and ms-admin-filters.php of WordPress 7.1 (the same names since 6.2).
	 * Left out: the privacy policy suggestions (WP_Privacy_Policy_Content).
	 */
	const CORE_NOTICES = array(
		'wp_recovery_mode_nag',        // A plugin or theme crashed: recovery mode is on.
		'update_nag',                  // A WordPress update is available.
		'deactivated_plugins_notice',  // Plugins deactivated because of a WordPress update.
		'paused_plugins_notice',       // Plugins paused after a fatal error.
		'paused_themes_notice',        // Themes paused after a fatal error.
		'maintenance_nag',             // An automatic WordPress update failed.
		'default_password_nag',        // The user still has the generated password.
		'new_user_email_admin_notice', // A change of the account email is waiting for confirmation.
		'site_admin_notice',           // Multisite: the network needs an upgrade.
	);

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ) );
		// Late, so notices other plugins add in the header are removed too.
		add_action( 'in_admin_header', array( __CLASS__, 'screen_notices' ), PHP_INT_MAX );
		add_action( 'admin_post_grmlt_menu_builder', array( __CLASS__, 'handle_menu_builder' ) );
		add_action( 'admin_post_grmlt_reset_options', array( __CLASS__, 'handle_reset_options' ) );
		add_action( 'admin_post_grmlt_redirects_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_grmlt_redirects_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_grmlt_redirect_delete', array( __CLASS__, 'handle_delete' ) );
	}

	/**
	 * Registers the top-level menu (same slug, icon and position as before).
	 */
	public static function menu() {
		$hook = add_menu_page(
			'Greek Multi Tool',
			'Greek Multi Tool',
			'manage_options',
			GRMLT_SETTINGS_PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-superhero',
			40
		);
		add_action( 'load-' . $hook, array( __CLASS__, 'on_load' ) );

		// One item per tab, to the same URLs as the tab bar. The first one has
		// the page's own slug, so it takes the place of the item WordPress adds
		// with the menu's name (no second "Greek Multi Tool").
		foreach ( self::get_tabs() as $tab => $label ) {
			$slug = self::submenu_slug( $tab );
			add_submenu_page( GRMLT_SETTINGS_PAGE, 'Greek Multi Tool', $label, 'manage_options', $slug, GRMLT_SETTINGS_PAGE === $slug ? array( __CLASS__, 'render_page' ) : '' );
		}
	}

	/**
	 * Submenu slug of a tab: the page itself for the first tab, the tab's URL
	 * (relative to wp-admin, as WordPress links it) for the others.
	 *
	 * @param string $tab Tab id.
	 * @return string
	 */
	public static function submenu_slug( $tab ) {
		$tabs = array_keys( self::get_tabs() );
		if ( ! $tabs || $tab === $tabs[0] ) {
			return GRMLT_SETTINGS_PAGE;
		}
		return 'admin.php?page=' . GRMLT_SETTINGS_PAGE . '&tab=' . $tab;
	}

	/**
	 * Highlights the submenu item of the current tab.
	 *
	 * @param string|null $submenu_file Submenu item WordPress would highlight.
	 * @return string|null
	 */
	public static function submenu_file( $submenu_file ) {
		return self::is_screen() ? self::submenu_slug( self::current_tab() ) : $submenu_file;
	}

	/**
	 * Dashicon of a tab (none for tabs added through grmlt_settings_tabs).
	 *
	 * @param string $tab Tab id.
	 * @return string
	 */
	public static function tab_icon( $tab ) {
		$icons = array(
			'settings'   => 'dashicons-admin-settings',
			'conversion' => 'dashicons-admin-links',
			'tools'      => 'dashicons-admin-tools',
		);
		return isset( $icons[ $tab ] ) ? $icons[ $tab ] : '';
	}

	/**
	 * The tabs: id => label. Extensible through the grmlt_settings_tabs filter.
	 *
	 * @return array<string, string>
	 */
	public static function get_tabs() {
		$tabs = array(
			'settings'   => __( 'Settings', 'greek-multi-tool' ),
			'conversion' => __( 'Conversion & redirects', 'greek-multi-tool' ),
			'tools'      => __( 'Tools', 'greek-multi-tool' ),
		);
		/**
		 * Filters the tabs of the settings screen. Each extra tab is rendered
		 * through the grmlt_settings_tab_{$id} action.
		 *
		 * @since 2.4.0
		 * @param array $tabs id => label.
		 */
		$tabs = apply_filters( 'grmlt_settings_tabs', $tabs );
		return array_filter( (array) $tabs, 'is_string' );
	}

	/**
	 * Current tab id.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = self::get_tabs();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		return isset( $tabs[ $tab ] ) ? $tab : 'settings';
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab  Tab id.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function tab_url( $tab = 'settings', $args = array() ) {
		return add_query_arg( array_merge( array( 'tab' => $tab ), $args ), admin_url( 'admin.php?page=' . GRMLT_SETTINGS_PAGE ) );
	}

	/**
	 * Runs before the page renders: bulk deletions from the redirects table.
	 */
	public static function on_load() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
		$action = isset( $_POST['action'] ) && '-1' !== $_POST['action'] ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $action && isset( $_POST['action2'] ) && '-1' !== $_POST['action2'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$action = sanitize_key( wp_unslash( $_POST['action2'] ) );
		}
		if ( 'delete' === $action && isset( $_POST['redirect'] ) ) {
			check_admin_referer( 'bulk-redirects' );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- absint below.
			$ids     = array_map( 'absint', (array) wp_unslash( $_POST['redirect'] ) );
			$deleted = GRMLT_Redirects::delete( $ids );
			grmlt_invalidate_health_cache();
			self::add_notice( 'success', sprintf( /* translators: %s: number */ _n( '%s redirect deleted.', '%s redirects deleted.', $deleted, 'greek-multi-tool' ), number_format_i18n( $deleted ) ) );
			wp_safe_redirect( self::tab_url( 'conversion' ) );
			exit;
		}
	}

	/**
	 * Whether the current admin screen is the plugin screen (any tab).
	 *
	 * @return bool
	 */
	public static function is_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && self::HOOK === $screen->id;
	}

	/**
	 * Whether notices of other plugins are hidden on the plugin screen.
	 *
	 * @return bool
	 */
	public static function hide_third_party_notices() {
		/**
		 * Filters whether notices of other plugins are hidden on the Greek Multi
		 * Tool screen, as WooCommerce, Rank Math and Elementor do on theirs. The
		 * WordPress notices about security and updates are always shown.
		 *
		 * @since 4.0.0
		 * @param bool $hide Default true.
		 */
		return (bool) apply_filters( 'grmlt_hide_third_party_notices', true );
	}

	/**
	 * Adds the body class the notice fallback CSS is scoped to.
	 *
	 * @param string $classes Space separated classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		if ( self::is_screen() && self::hide_third_party_notices() ) {
			$classes .= ' grmlt-hide-notices';
		}
		return $classes;
	}

	/**
	 * On the plugin screen only: our own notices move into the screen header,
	 * and the notice hooks keep only the core notices of CORE_NOTICES.
	 */
	public static function screen_notices() {
		if ( ! self::is_screen() ) {
			return;
		}
		// Printed by views/header.php, under the tabs.
		remove_action( 'admin_notices', 'grmlt_removed_features_notice' );
		remove_action( 'admin_notices', 'grmlt_migration_notice' );
		if ( ! self::hide_third_party_notices() ) {
			return;
		}
		global $wp_filter;
		foreach ( array( 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ) as $hook ) {
			$keep = array();
			if ( isset( $wp_filter[ $hook ] ) ) {
				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( is_string( $callback['function'] ) && in_array( $callback['function'], self::CORE_NOTICES, true ) ) {
							$keep[] = array( $callback['function'], $priority );
						}
					}
				}
			}
			remove_all_actions( $hook );
			foreach ( $keep as $kept ) {
				add_action(
					$hook,
					function () use ( $kept ) {
						self::print_core_notice( $kept[0] );
					},
					$kept[1]
				);
			}
		}
	}

	/**
	 * Prints a core notice with a class the fallback CSS leaves visible.
	 *
	 * @param callable $callback Core notice function.
	 */
	private static function print_core_notice( $callback ) {
		ob_start();
		call_user_func( $callback );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core output, only a class added.
		echo preg_replace( '/<div\b([^>]*?)\bclass=(["\'])/i', '<div$1class=$2grmlt-core-notice ', (string) ob_get_clean() );
	}

	/**
	 * Enqueues CSS/JS on the plugin screen only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public static function assets( $hook ) {
		if ( self::HOOK !== $hook ) {
			return;
		}
		wp_enqueue_style( 'grmlt-admin', GRMLT_PLUGIN_URL . 'admin/css/grmlt-admin.css', array(), GRMLT_PLUGIN_VERSION );
		wp_enqueue_script( 'grmlt-admin', GRMLT_PLUGIN_URL . 'admin/js/grmlt-admin.js', array( 'wp-api-fetch', 'wp-i18n' ), GRMLT_PLUGIN_VERSION, array( 'in_footer' => true ) );
		wp_set_script_translations( 'grmlt-admin', 'greek-multi-tool', GRMLT_PLUGIN_DIR . 'languages' );

		$modes = grmlt_get_modes();
		wp_add_inline_script(
			'grmlt-admin',
			'window.grmltAdmin = ' . wp_json_encode(
				array(
					'tab'         => self::current_tab(),
					'homeUrl'     => home_url( '/' ),
					'currentMode' => grmlt_get_option( 'grmlt_diphthongs' ),
					'modeLabels'  => $modes,
					'batchSize'   => GRMLT_Converter::batch_size(),
					'tabUrl'      => self::tab_url( self::current_tab() ),
					'run'         => GRMLT_Converter::state_summary(),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Stores a notice for the current user, shown on the next page view.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Plain text or limited HTML.
	 */
	public static function add_notice( $type, $message ) {
		$key     = 'grmlt_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		$notices = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Returns and clears the stored notices.
	 *
	 * @return array
	 */
	public static function take_notices() {
		$key     = 'grmlt_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		delete_transient( $key );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Renders the screen.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'greek-multi-tool' ), 403 );
		}
		$tab  = self::current_tab();
		$tabs = self::get_tabs();
		include GRMLT_PLUGIN_DIR . 'admin/views/header.php';
		echo '<div class="grmlt-content">';
		$view = GRMLT_PLUGIN_DIR . 'admin/views/tab-' . $tab . '.php';
		if ( in_array( $tab, array( 'settings', 'conversion', 'tools' ), true ) && file_exists( $view ) ) {
			include $view;
		} else {
			/**
			 * Renders the content of a tab added through grmlt_settings_tabs.
			 *
			 * @since 2.4.0
			 */
			do_action( 'grmlt_settings_tab_' . $tab );
		}
		echo '</div></div>';
	}

	/**
	 * Redirects back to a tab after an admin-post action.
	 *
	 * @param string $tab Tab id.
	 */
	private static function back( $tab ) {
		wp_safe_redirect( self::tab_url( $tab ) );
		exit;
	}

	/**
	 * admin-post: menu builder.
	 */
	public static function handle_menu_builder() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
		}
		check_admin_referer( 'grmlt_menu_builder' );
		$source  = isset( $_POST['grmlt_menu_source'] ) ? sanitize_key( wp_unslash( $_POST['grmlt_menu_source'] ) ) : 'category';
		$name    = isset( $_POST['grmlt_menu_name'] ) ? sanitize_text_field( wp_unslash( $_POST['grmlt_menu_name'] ) ) : '';
		$replace = ! empty( $_POST['grmlt_menu_replace'] );
		$result  = grmlt_create_category_menu( $source, $name, $replace );
		if ( is_wp_error( $result ) ) {
			self::add_notice( 'error', $result->get_error_message() );
		} else {
			self::add_notice(
				'success',
				sprintf(
					/* translators: 1: menu name, 2: number of items, 3: URL of the Menus screen */
					_n( 'The menu “%1$s” was created with %2$s item. <a href="%3$s">Open it in Menus</a>.', 'The menu “%1$s” was created with %2$s items. <a href="%3$s">Open it in Menus</a>.', $result['count'], 'greek-multi-tool' ),
					esc_html( $result['name'] ),
					number_format_i18n( $result['count'] ),
					esc_url( admin_url( 'nav-menus.php?menu=' . (int) $result['menu_id'] ) )
				)
			);
		}
		self::back( 'tools' );
	}

	/**
	 * admin-post: reset options.
	 */
	public static function handle_reset_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
		}
		check_admin_referer( 'grmlt_reset_options' );
		grmlt_reset_options();
		self::add_notice( 'success', __( 'The settings were restored to their defaults.', 'greek-multi-tool' ) );
		self::back( 'tools' );
	}

	/**
	 * admin-post: CSV export.
	 */
	public static function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
		}
		check_admin_referer( 'grmlt_redirects_export' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="greek-multi-tool-redirects-' . gmdate( 'Y-m-d' ) . '.csv"' );
		$handle = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		GRMLT_Redirects::export_csv( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * admin-post: CSV import.
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
		}
		check_admin_referer( 'grmlt_redirects_import' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below.
		$file = isset( $_FILES['grmlt_csv'] ) ? $_FILES['grmlt_csv'] : null;
		if ( ! $file || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			self::add_notice( 'error', __( 'No file was uploaded.', 'greek-multi-tool' ) );
			self::back( 'conversion' );
		}
		if ( (int) $file['size'] > 2 * MB_IN_BYTES ) {
			self::add_notice( 'error', __( 'The file is larger than 2 MB.', 'greek-multi-tool' ) );
			self::back( 'conversion' );
		}
		$name = sanitize_file_name( (string) $file['name'] );
		if ( 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::add_notice( 'error', __( 'Only .csv files can be imported.', 'greek-multi-tool' ) );
			self::back( 'conversion' );
		}
		$mime = function_exists( 'mime_content_type' ) ? (string) mime_content_type( $file['tmp_name'] ) : 'text/plain';
		if ( '' !== $mime && 0 !== strpos( $mime, 'text/' ) && ! in_array( $mime, array( 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream' ), true ) ) {
			self::add_notice( 'error', __( 'The file does not look like a CSV text file.', 'greek-multi-tool' ) );
			self::back( 'conversion' );
		}
		$summary = GRMLT_Redirects::import_csv( $file['tmp_name'] );
		if ( is_wp_error( $summary ) ) {
			self::add_notice( 'error', $summary->get_error_message() );
			self::back( 'conversion' );
		}
		$message = sprintf(
			/* translators: 1: imported rows, 2: total rows */
			__( '%1$s of %2$s rows were imported.', 'greek-multi-tool' ),
			number_format_i18n( $summary['imported'] ),
			number_format_i18n( $summary['total'] )
		);
		if ( $summary['skipped'] ) {
			$lines = array();
			foreach ( array_slice( $summary['skipped'], 0, 10 ) as $skip ) {
				$lines[] = sprintf( /* translators: 1: line number in the CSV file, 2: old path, 3: reason */ __( 'line %1$d (%2$s): %3$s', 'greek-multi-tool' ), $skip['line'], '' === $skip['old'] ? '-' : $skip['old'], GRMLT_Redirects::reason_label( $skip['reason'] ) );
			}
			if ( count( $summary['skipped'] ) > 10 ) {
				$lines[] = sprintf( /* translators: %d: number */ __( '… and %d more.', 'greek-multi-tool' ), count( $summary['skipped'] ) - 10 );
			}
			$message .= '<br>' . esc_html__( 'Not imported:', 'greek-multi-tool' ) . '<br>' . implode( '<br>', array_map( 'esc_html', $lines ) );
		}
		self::add_notice( $summary['imported'] > 0 ? 'success' : 'warning', $message );
		self::back( 'conversion' );
	}

	/**
	 * admin-post: delete one redirect (row action).
	 */
	public static function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
		}
		$id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked next line.
		check_admin_referer( 'grmlt_redirect_delete_' . $id );
		$deleted = GRMLT_Redirects::delete( array( $id ) );
		grmlt_invalidate_health_cache();
		self::add_notice( $deleted ? 'success' : 'error', $deleted ? __( 'The redirect was deleted.', 'greek-multi-tool' ) : __( 'Redirect not found.', 'greek-multi-tool' ) );
		self::back( 'conversion' );
	}
}

GRMLT_Admin::init();
