<?php
/**
 * Screen header: brand, links, tabs, notices.
 *
 * @var string $tab  Current tab id.
 * @var array  $tabs Tabs.
 *
 * @package Grmlt_Plugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="grmlt-admin">
	<header class="grmlt-header">
		<div class="grmlt-header__top">
			<div class="grmlt-header__brand">
				<span class="grmlt-header__icon" aria-hidden="true"><span class="dashicons dashicons-superhero"></span></span>
				<h1 class="grmlt-header__title">Greek Multi Tool</h1>
				<span class="grmlt-badge"><?php echo esc_html( GRMLT_PLUGIN_VERSION ); ?></span>
			</div>
			<nav class="grmlt-header__links" aria-label="<?php esc_attr_e( 'Plugin links', 'greek-multi-tool' ); ?>">
				<a href="https://wordpress.org/support/plugin/greek-multi-tool/" target="_blank" rel="noopener"><?php esc_html_e( 'Support', 'greek-multi-tool' ); ?></a>
				<a href="https://wordpress.org/support/plugin/greek-multi-tool/reviews/#new-post" target="_blank" rel="noopener"><?php esc_html_e( 'Rate the plugin', 'greek-multi-tool' ); ?></a>
			</nav>
		</div>
		<nav class="grmlt-tabs" aria-label="<?php esc_attr_e( 'Greek Multi Tool sections', 'greek-multi-tool' ); ?>">
			<?php foreach ( $tabs as $grmlt_tab_id => $grmlt_tab_label ) : ?>
				<a class="grmlt-tab<?php echo $grmlt_tab_id === $tab ? ' is-active' : ''; ?>" href="<?php echo esc_url( GRMLT_Admin::tab_url( $grmlt_tab_id ) ); ?>"<?php echo $grmlt_tab_id === $tab ? ' aria-current="page"' : ''; ?>>
					<?php if ( GRMLT_Admin::tab_icon( $grmlt_tab_id ) ) : ?>
						<span class="grmlt-tab__icon dashicons <?php echo esc_attr( GRMLT_Admin::tab_icon( $grmlt_tab_id ) ); ?>" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="grmlt-tab__label"><?php echo esc_html( $grmlt_tab_label ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</header>
	<div class="grmlt-notices">
		<?php // WordPress moves the notices printed above the screen right after this line. ?>
		<hr class="wp-header-end">
		<?php
		settings_errors();
		if ( function_exists( 'grmlt_removed_features_notice' ) ) {
			grmlt_removed_features_notice();
		}
		if ( function_exists( 'grmlt_migration_notice' ) ) {
			grmlt_migration_notice();
		}
		foreach ( GRMLT_Admin::take_notices() as $grmlt_notice ) {
			$grmlt_type = in_array( $grmlt_notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $grmlt_notice['type'] : 'info';
			echo '<div class="notice notice-' . esc_attr( $grmlt_type ) . ' grmlt-notice is-dismissible"><p>' . wp_kses_post( $grmlt_notice['message'] ) . '</p></div>';
		}
		?>
	</div>
