<?php
/**
 * This tab content page is related to Woocommerce Menu Builder functionality of Greeklish Multi Tool Plugin
 */

// Require Functions for Woo Menu Creation
require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'functions/menu-creation.php';
?>
<h6><?php esc_html_e( 'MENU BUILDER', 'greek-multi-tool' ); ?></h6>
<hr>
<form method="post" action="">
	<?php wp_nonce_field( 'grmlt_menu_builder_action', 'grmlt_menu_builder_nonce' ); ?>
	<?php
	// IF Woocommerce Exists and is Active then show the Product Category Menu
	if ( class_exists( 'WooCommerce' ) ) {
	?>
	<!-- Woo Product Categories Menu -->
	<strong class="mb-0"><?php esc_html_e( 'Woocommerce Products Category Menu', 'greek-multi-tool' ); ?></strong>
	<p><?php esc_html_e( 'To create a custom menu for your WooCommerce store effortlessly, simply enter your preferred menu name in the form below. Click \'Create,\' and a new menu with the name you specified will be automatically generated. This menu will intuitively organize all your WooCommerce product categories, following their existing hierarchy.', 'greek-multi-tool' ); ?></p>

	<div class="d-flex flex-row mt-3">
		<input class="col-4" type="text" name="name_of_woo_menu" placeholder="<?php esc_attr_e( 'Enter a name for the Menu...', 'greek-multi-tool' ); ?>">
		<button type="submit" name="create_woo_menu_button" class="ms-auto col-2 btn btn-primary"><?php esc_html_e( 'Create Menu', 'greek-multi-tool' ); ?></button>
	</div>
	<hr class="mb-3">
	<?php
	}
	?>
	<!-- Post Categories Menu -->
	<strong class="mb-0"><?php esc_html_e( 'Posts Category Menu', 'greek-multi-tool' ); ?></strong>
	<p><?php esc_html_e( 'To effortlessly create a custom menu for your WordPress blog, simply enter your preferred menu name in the form below. Click \'Create,\' and a new menu with the name you specified will be automatically generated. This menu will intuitively organize all your WordPress post categories, following their existing hierarchy.', 'greek-multi-tool' ); ?></p>

	<div class="d-flex flex-row mt-3">
		<input class="col-4" type="text" name="name_of_posts_menu" placeholder="<?php esc_attr_e( 'Enter a name for the Menu...', 'greek-multi-tool' ); ?>">
		<button type="submit" name="create_posts_menu_button" class="ms-auto col-2 btn btn-primary"><?php esc_html_e( 'Create Menu', 'greek-multi-tool' ); ?></button>
	</div>
</form>
