<?php
/**
 * Notices about the 4.0.0 upgrade: the features that were removed, and
 * anything the migration could not do on its own.
 *
 * TODO: remove in 4.1.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

const GRMLT_NOTICE_META           = 'grmlt_dismissed_notice_400';
const GRMLT_MIGRATION_NOTICE_META = 'grmlt_dismissed_migration_notice_400';

/**
 * Whether upgrade notices may be shown on the current screen.
 *
 * @return bool
 */
function grmlt_notice_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	$screen = get_current_screen();
	return $screen && in_array( $screen->id, array( 'dashboard', 'plugins', 'toplevel_page_' . GRMLT_SETTINGS_PAGE ), true );
}

/**
 * Prints the notice about the removed features.
 */
function grmlt_removed_features_notice() {
	if ( ! grmlt_notice_screen() ) {
		return;
	}
	$removed = get_option( 'grmlt_removed_features', array() );
	if ( empty( $removed ) || ! is_array( $removed ) ) {
		return;
	}
	if ( get_user_meta( get_current_user_id(), GRMLT_NOTICE_META, true ) ) {
		return;
	}

	$labels = array(
		'dates'     => __( 'Greek dates', 'greek-multi-tool' ),
		'uppercase' => __( 'Uppercase accent removal', 'greek-multi-tool' ),
		'search'    => __( 'Enhanced search', 'greek-multi-tool' ),
		'excerpts'  => __( 'Greek excerpts', 'greek-multi-tool' ),
	);
	$names  = array();
	foreach ( $removed as $key ) {
		if ( isset( $labels[ $key ] ) ) {
			$names[] = $labels[ $key ];
		}
	}
	if ( empty( $names ) ) {
		return;
	}

	$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=grmlt_dismiss_notice&notice=features' ), 'grmlt_dismiss_notice' );
	$health_url  = admin_url( 'site-health.php' );
	$locale_note = '';
	if ( in_array( 'dates', $removed, true ) && 0 !== strpos( get_locale(), 'el' ) ) {
		$locale_note = sprintf(
			/* translators: %s: URL of Settings > General */
			__( 'Greek dates were enabled but the site language is not Greek. Set the Site Language to Greek in <a href="%s">Settings &rarr; General</a> so WordPress shows Greek dates.', 'greek-multi-tool' ),
			esc_url( admin_url( 'options-general.php' ) )
		);
	}
	?>
	<div class="notice notice-info grmlt-notice">
		<p>
			<strong><?php esc_html_e( 'Greek Multi Tool 4.0', 'greek-multi-tool' ); ?></strong>
			<?php
			printf(
				/* translators: %s: comma separated feature names */
				esc_html__( 'The following features were removed because WordPress now covers them: %s.', 'greek-multi-tool' ),
				esc_html( implode( ', ', $names ) )
			);
			echo ' ';
			printf(
				/* translators: %s: URL of Site Health */
				wp_kses_post( __( 'Whether your site meets the requirements is checked in <a href="%s">Site Health</a>.', 'greek-multi-tool' ) ),
				esc_url( $health_url )
			);
			?>
		</p>
		<?php if ( $locale_note ) : ?>
			<p><?php echo wp_kses_post( $locale_note ); ?></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'Do not show again', 'greek-multi-tool' ); ?></a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'grmlt_removed_features_notice' );

/**
 * Prints what the migration could not do on its own: an error it is waiting
 * on (with a retry button), a backup of the old table, old redirects that
 * were skipped, 3.x undo data that was kept aside. Independent of the
 * removed-features notice.
 */
function grmlt_migration_notice() {
	if ( ! grmlt_notice_screen() ) {
		return;
	}
	$failure = function_exists( 'grmlt_upgrade_failure' ) ? grmlt_upgrade_failure() : null;
	if ( $failure ) {
		$retry_url = wp_nonce_url( admin_url( 'admin-post.php?action=grmlt_retry_migration' ), 'grmlt_retry_migration' );
		?>
		<div class="notice notice-error grmlt-notice">
			<p>
				<strong><?php esc_html_e( 'Greek Multi Tool 4.0', 'greek-multi-tool' ); ?></strong>
				<?php
				if ( function_exists( 'grmlt_is_legacy_install' ) && grmlt_is_legacy_install() ) {
					esc_html_e( 'The update of the plugin data stopped because of an error and will be retried in an hour. Until then the plugin keeps working with your 3.x settings, and nothing was lost.', 'greek-multi-tool' );
				} else {
					esc_html_e( 'The setup of the plugin data stopped because of an error and will be retried in an hour. Until then new permalinks are converted with the default settings; redirects and the converter need the database tables.', 'greek-multi-tool' );
				}
				?>
			</p>
			<p><code><?php echo esc_html( $failure['message'] ); ?></code></p>
			<p><a href="<?php echo esc_url( $retry_url ); ?>" class="button button-primary"><?php esc_html_e( 'Retry now', 'greek-multi-tool' ); ?></a></p>
		</div>
		<?php
		return;
	}
	if ( get_user_meta( get_current_user_id(), GRMLT_MIGRATION_NOTICE_META, true ) ) {
		return;
	}
	$log = get_option( 'grmlt_migration_log', array() );
	if ( ! is_array( $log ) ) {
		return;
	}
	$health_url = admin_url( 'site-health.php?tab=debug' );
	$lines      = array();
	if ( ! empty( $log['legacy_table_backup'] ) ) {
		$lines[] = sprintf(
			/* translators: %s: database table name */
			__( 'Not every old redirect could be migrated, so the old table was kept as %s. Nothing was deleted.', 'greek-multi-tool' ),
			'<code>' . esc_html( $log['legacy_table_backup'] ) . '</code>'
		);
	}
	if ( ! empty( $log['redirects_skipped'] ) ) {
		$skipped = count( $log['redirects_skipped'] ) + ( isset( $log['redirects_skipped_more'] ) ? (int) $log['redirects_skipped_more'] : 0 );
		$lines[] = sprintf(
			/* translators: 1: number of redirects, 2: URL of Site Health > Info */
			_n( '%1$d old redirect could not be migrated. The details are listed under <a href="%2$s">Site Health &rarr; Info &rarr; Greek Multi Tool</a>.', '%1$d old redirects could not be migrated. The details are listed under <a href="%2$s">Site Health &rarr; Info &rarr; Greek Multi Tool</a>.', $skipped, 'greek-multi-tool' ),
			$skipped,
			esc_url( $health_url )
		);
	}
	if ( ! empty( $log['snapshot_skipped'] ) ) {
		$lines[] = sprintf(
			/* translators: %s: option name */
			__( 'Some entries of the 3.x undo data could not be read; a copy was kept in the option %s.', 'greek-multi-tool' ),
			'<code>grmlt_legacy_undo_backup</code>'
		);
	}
	if ( ! $lines ) {
		return;
	}
	$dismiss_url = wp_nonce_url( admin_url( 'admin-post.php?action=grmlt_dismiss_notice&notice=migration' ), 'grmlt_dismiss_notice' );
	?>
	<div class="notice notice-warning grmlt-notice">
		<p><strong><?php esc_html_e( 'Greek Multi Tool 4.0', 'greek-multi-tool' ); ?></strong></p>
		<?php foreach ( $lines as $line ) : ?>
			<p><?php echo wp_kses_post( $line ); ?></p>
		<?php endforeach; ?>
		<p><a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'Do not show again', 'greek-multi-tool' ); ?></a></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'grmlt_migration_notice' );

/**
 * Dismisses a notice for the current user.
 */
function grmlt_dismiss_notice_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
	}
	check_admin_referer( 'grmlt_dismiss_notice' );
	$which = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : 'features';
	update_user_meta( get_current_user_id(), 'migration' === $which ? GRMLT_MIGRATION_NOTICE_META : GRMLT_NOTICE_META, 1 );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_grmlt_dismiss_notice', 'grmlt_dismiss_notice_handler' );

/**
 * Retries a migration that is waiting after an error, right away.
 */
function grmlt_retry_migration_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'greek-multi-tool' ), 403 );
	}
	check_admin_referer( 'grmlt_retry_migration' );
	grmlt_upgrade_retry();
	grmlt_maybe_upgrade();
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
	exit;
}
add_action( 'admin_post_grmlt_retry_migration', 'grmlt_retry_migration_handler' );
