<?php
/**
 * Tools tab.
 *
 * @package Grmlt_Plugin
 */

defined( 'ABSPATH' ) || exit;

$grmlt_sources  = grmlt_menu_builder_sources();
$grmlt_commands = array(
	array(
		'cmd'  => 'wp grmlt convert --dry-run',
		'desc' => __( 'Shows what the conversion would change, writes nothing.', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt convert [--post-types=<list>] [--taxonomies=<list>] [--include-attachments] [--batch-size=<n>] [--yes]',
		'desc' => __( 'Converts old permalinks in batches, with 301 redirects. Ideal for large sites.', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt undo-conversion [--yes]',
		'desc' => __( 'Restores the permalinks of the last conversion.', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt redirects check',
		'desc' => __( 'Checks every redirect for missing destinations, chains and loops.', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt redirects export <file>',
		'desc' => __( 'Exports the redirects to a CSV file.', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt redirects import <file>',
		'desc' => __( 'Imports redirects from a CSV file (columns old_path, new_path).', 'greek-multi-tool' ),
	),
	array(
		'cmd'  => 'wp grmlt acf-restore-keys [--write] [--regenerate-keys] [--undo=<file>]',
		'desc' => __( 'Restores ACF and Secure Custom Fields keys damaged by versions before 3.4.1 (fields, sub fields, field groups, post types, taxonomies, options pages), from stored values or acf-json. Dry run by default. --write keeps a backup that --undo restores; --regenerate-keys gives new keys to what cannot be recovered.', 'greek-multi-tool' ),
	),
);
?>
<div class="grmlt-grid">
	<section class="grmlt-card" aria-labelledby="grmlt-card-menu">
		<div class="grmlt-card__header">
			<div class="grmlt-card__heading">
				<h2 id="grmlt-card-menu"><?php esc_html_e( 'Category menu', 'greek-multi-tool' ); ?></h2>
				<p><?php esc_html_e( 'Creates a navigation menu that mirrors a category hierarchy.', 'greek-multi-tool' ); ?></p>
			</div>
		</div>
		<form class="grmlt-card__body" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="grmlt_menu_builder">
			<?php wp_nonce_field( 'grmlt_menu_builder' ); ?>
			<p>
				<label for="grmlt_menu_source"><?php esc_html_e( 'Source', 'greek-multi-tool' ); ?></label><br>
				<select name="grmlt_menu_source" id="grmlt_menu_source">
					<?php foreach ( $grmlt_sources as $grmlt_key => $grmlt_label ) : ?>
						<option value="<?php echo esc_attr( $grmlt_key ); ?>"><?php echo esc_html( $grmlt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="grmlt_menu_name"><?php esc_html_e( 'Menu name', 'greek-multi-tool' ); ?></label><br>
				<input type="text" name="grmlt_menu_name" id="grmlt_menu_name" class="regular-text" required>
			</p>
			<p><label><input type="checkbox" name="grmlt_menu_replace" value="1"> <?php esc_html_e( 'Replace the items of the existing menu with this name', 'greek-multi-tool' ); ?></label></p>
			<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Create menu', 'greek-multi-tool' ); ?></button></p>
		</form>
	</section>

	<section class="grmlt-card" aria-labelledby="grmlt-card-acf">
		<div class="grmlt-card__header">
			<div class="grmlt-card__heading">
				<h2 id="grmlt-card-acf"><?php esc_html_e( 'Restore ACF field keys', 'greek-multi-tool' ); ?></h2>
				<p><?php esc_html_e( 'For sites where “Convert All Old Permalinks” in a version before 3.4.1 damaged the keys of Advanced Custom Fields or Secure Custom Fields. Run it first without --write: it lists every damaged field, field group, post type, taxonomy and options page, the key it would restore and where that key comes from.', 'greek-multi-tool' ); ?></p>
			</div>
		</div>
		<div class="grmlt-card__body">
			<div class="grmlt-cmd">
				<code id="grmlt-cmd-acf">wp grmlt acf-restore-keys</code>
				<button type="button" class="button grmlt-copy" data-copy-target="grmlt-cmd-acf" aria-label="<?php esc_attr_e( 'Copy command', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Copy', 'greek-multi-tool' ); ?></button>
			</div>
			<p class="description"><?php esc_html_e( 'Then add --write to apply. A backup of the changed rows is written to wp-content/uploads/grmlt-acf-backups/, a folder closed to the web, and --undo=<file name> puts them back. Keys that cannot be recovered need the site’s acf-json folder in the theme, or new keys with --write --regenerate-keys. Back up the database first.', 'greek-multi-tool' ); ?></p>
		</div>
	</section>

	<section class="grmlt-card" aria-labelledby="grmlt-card-cli">
		<div class="grmlt-card__header">
			<div class="grmlt-card__heading">
				<h2 id="grmlt-card-cli"><?php esc_html_e( 'WP-CLI commands', 'greek-multi-tool' ); ?></h2>
			</div>
		</div>
		<div class="grmlt-card__body">
			<ul class="grmlt-cmd-list">
				<?php foreach ( $grmlt_commands as $grmlt_i => $grmlt_command ) : ?>
					<li>
						<div class="grmlt-cmd">
							<code id="grmlt-cmd-<?php echo (int) $grmlt_i; ?>"><?php echo esc_html( $grmlt_command['cmd'] ); ?></code>
							<button type="button" class="button grmlt-copy" data-copy-target="grmlt-cmd-<?php echo (int) $grmlt_i; ?>" aria-label="<?php esc_attr_e( 'Copy command', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Copy', 'greek-multi-tool' ); ?></button>
						</div>
						<p class="description"><?php echo esc_html( $grmlt_command['desc'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="grmlt-card" aria-labelledby="grmlt-card-reset">
		<div class="grmlt-card__header">
			<div class="grmlt-card__heading">
				<h2 id="grmlt-card-reset"><?php esc_html_e( 'Restore defaults', 'greek-multi-tool' ); ?></h2>
				<p><?php esc_html_e( 'Resets the plugin settings. Converted permalinks and redirects are not affected.', 'greek-multi-tool' ); ?></p>
			</div>
		</div>
		<form class="grmlt-card__body" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="grmlt_reset_options">
			<?php wp_nonce_field( 'grmlt_reset_options' ); ?>
			<?php if ( 'simple-legacy' === grmlt_get_option( 'grmlt_diphthongs' ) ) : ?>
				<p class="description"><?php esc_html_e( 'The “3.x compatibility” transliteration standard is kept, so new permalinks keep being written the same way.', 'greek-multi-tool' ); ?></p>
			<?php endif; ?>
			<p><button type="submit" class="button button-link-delete" data-grmlt-confirm="<?php echo 'simple-legacy' === grmlt_get_option( 'grmlt_diphthongs' ) ? esc_attr__( 'Restore all settings to their defaults? The 3.x compatibility standard is kept.', 'greek-multi-tool' ) : esc_attr__( 'Restore all settings to their defaults?', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Restore defaults', 'greek-multi-tool' ); ?></button></p>
		</form>
	</section>
</div>
