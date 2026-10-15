<?php
/**
 * Accent check in the editor: block editor panel, classic editor meta box.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the accent check applies to a post type.
 *
 * @param string $post_type Post type.
 * @return bool
 */
function grmlt_accent_check_supports( $post_type ) {
	return $post_type && post_type_supports( $post_type, 'editor' ) && 0 !== strpos( $post_type, 'acf-' );
}

/**
 * Block editor: the sidebar panel script.
 */
function grmlt_enqueue_editor_panel() {
	if ( ! grmlt_is_on( 'grmlt_enable_text_analysis' ) ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || empty( $screen->post_type ) || ! grmlt_accent_check_supports( $screen->post_type ) ) {
		return;
	}
	wp_enqueue_script(
		'grmlt-editor-panel',
		GRMLT_PLUGIN_URL . 'admin/js/grmlt-editor-panel.js',
		array( 'wp-plugins', 'wp-editor', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-i18n' ),
		GRMLT_PLUGIN_VERSION,
		true
	);
	wp_set_script_translations( 'grmlt-editor-panel', 'greek-multi-tool', GRMLT_PLUGIN_DIR . 'languages' );
}
add_action( 'enqueue_block_editor_assets', 'grmlt_enqueue_editor_panel' );

/**
 * Classic editor: meta box (only when the block editor is not used).
 *
 * @param string $post_type Post type.
 */
function grmlt_add_classic_metabox( $post_type ) {
	if ( ! grmlt_is_on( 'grmlt_enable_text_analysis' ) || ! grmlt_accent_check_supports( $post_type ) ) {
		return;
	}
	if ( use_block_editor_for_post_type( $post_type ) ) {
		return;
	}
	add_meta_box( 'grmlt_accent_check', __( 'Accent check', 'greek-multi-tool' ), 'grmlt_render_classic_metabox', $post_type, 'side' );
}
add_action( 'add_meta_boxes', 'grmlt_add_classic_metabox' );

/**
 * Meta box markup (the logic lives in grmlt-classic-metabox.js).
 *
 * @param WP_Post $post Post.
 */
function grmlt_render_classic_metabox( $post ) {
	?>
	<div class="grmlt-classic-metabox" data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>">
		<p><?php esc_html_e( 'Checks the text for words whose accent is probably wrong.', 'greek-multi-tool' ); ?></p>
		<p><button type="button" class="button grmlt-classic-metabox__run"><?php esc_html_e( 'Check text', 'greek-multi-tool' ); ?></button></p>
		<div class="grmlt-classic-metabox__result" aria-live="polite"></div>
	</div>
	<?php
}

/**
 * Classic editor: the meta box script.
 *
 * @param string $hook Hook suffix.
 */
function grmlt_enqueue_classic_metabox_script( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! grmlt_is_on( 'grmlt_enable_text_analysis' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || ! grmlt_accent_check_supports( $screen->post_type ) || use_block_editor_for_post_type( $screen->post_type ) ) {
		return;
	}
	wp_enqueue_script( 'grmlt-classic-metabox', GRMLT_PLUGIN_URL . 'admin/js/grmlt-classic-metabox.js', array( 'wp-api-fetch', 'wp-i18n' ), GRMLT_PLUGIN_VERSION, true );
	wp_set_script_translations( 'grmlt-classic-metabox', 'greek-multi-tool', GRMLT_PLUGIN_DIR . 'languages' );
}
add_action( 'admin_enqueue_scripts', 'grmlt_enqueue_classic_metabox_script' );
