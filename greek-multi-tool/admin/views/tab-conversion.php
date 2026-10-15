<?php
/**
 * Conversion & redirects tab.
 *
 * @package Grmlt_Plugin
 */

defined( 'ABSPATH' ) || exit;

require_once GRMLT_PLUGIN_DIR . 'admin/class-grmlt-redirects-list-table.php';

$grmlt_counts      = GRMLT_Converter::count_candidates();
$grmlt_post_types  = GRMLT_Converter::get_post_types( false );
$grmlt_taxonomies  = GRMLT_Converter::get_taxonomies();
$grmlt_attachments = in_array( 'attachment', GRMLT_Converter::get_post_types( true ), true );
$grmlt_last        = GRMLT_Converter::get_last_conversion();
$grmlt_running     = GRMLT_Converter::get_state();
$grmlt_modes       = grmlt_get_modes();
$grmlt_mode        = grmlt_get_option( 'grmlt_diphthongs' );
$grmlt_redirect_on = grmlt_is_on( 'grmlt_redirect' );
$grmlt_table       = new GRMLT_Redirects_List_Table();
$grmlt_table->prepare_items();
?>
<section class="grmlt-card" aria-labelledby="grmlt-card-convert">
	<div class="grmlt-card__header">
		<div class="grmlt-card__heading">
			<h2 id="grmlt-card-convert"><?php esc_html_e( 'Convert old permalinks', 'greek-multi-tool' ); ?></h2>
			<p>
				<?php esc_html_e( 'New posts get Latin permalinks automatically. Content published before the plugin was enabled keeps its Greek permalinks until you convert it here, once.', 'greek-multi-tool' ); ?>
				<?php
				printf(
					/* translators: %s: transliteration standard */
					esc_html__( 'The conversion uses the current transliteration standard: %s.', 'greek-multi-tool' ),
					'<strong>' . esc_html( isset( $grmlt_modes[ $grmlt_mode ] ) ? $grmlt_modes[ $grmlt_mode ] : $grmlt_mode ) . '</strong>'
				);
				?>
			</p>
		</div>
	</div>
	<div class="grmlt-card__body grmlt-steps" id="grmlt-conversion" data-grmlt-conversion>
		<?php if ( ! grmlt_is_on( 'grmlt_text' ) ) : ?>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Greek permalinks are disabled in Settings. The conversion still works, but new content will keep Greek permalinks until you enable the feature.', 'greek-multi-tool' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $grmlt_redirect_on ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'Redirects are disabled. The conversion still records a redirect for every old permalink, but old links return a 404 error until you enable them.', 'greek-multi-tool' ); ?> <a href="#grmlt-redirects"><?php esc_html_e( 'Enable redirects', 'greek-multi-tool' ); ?></a></p></div>
		<?php endif; ?>
		<div class="notice notice-warning inline" id="grmlt-run-notice" hidden>
			<p id="grmlt-run-text"></p>
			<p>
				<button type="button" class="button button-primary" data-action="resume"><?php esc_html_e( 'Continue', 'greek-multi-tool' ); ?></button>
				<button type="button" class="button" data-action="discard"><?php esc_html_e( 'Discard', 'greek-multi-tool' ); ?></button>
				<span class="spinner"></span>
			</p>
		</div>

		<div class="grmlt-step">
			<h3 class="grmlt-step__title"><span class="grmlt-step__num is-active" aria-hidden="true">1</span> <?php esc_html_e( 'What to convert', 'greek-multi-tool' ); ?></h3>
			<div class="grmlt-step__body">
				<fieldset class="grmlt-checks grmlt-checks--grid" id="grmlt-conversion-targets">
					<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'greek-multi-tool' ); ?></legend>
					<?php
					foreach ( $grmlt_post_types as $grmlt_type ) :
						$grmlt_obj   = get_post_type_object( $grmlt_type );
						$grmlt_count = isset( $grmlt_counts['post_types'][ $grmlt_type ] ) ? (int) $grmlt_counts['post_types'][ $grmlt_type ] : 0;
						?>
						<label>
							<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $grmlt_type ); ?>" <?php checked( $grmlt_count > 0 ); ?> <?php disabled( 0 === $grmlt_count ); ?>>
							<?php echo esc_html( $grmlt_obj ? $grmlt_obj->labels->name : $grmlt_type ); ?> <span class="grmlt-count">(<?php echo esc_html( number_format_i18n( $grmlt_count ) ); ?>)</span>
						</label>
					<?php endforeach; ?>
					<?php
					foreach ( $grmlt_taxonomies as $grmlt_taxonomy ) :
						$grmlt_obj   = get_taxonomy( $grmlt_taxonomy );
						$grmlt_count = isset( $grmlt_counts['taxonomies'][ $grmlt_taxonomy ] ) ? (int) $grmlt_counts['taxonomies'][ $grmlt_taxonomy ] : 0;
						?>
						<label>
							<input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr( $grmlt_taxonomy ); ?>" <?php checked( $grmlt_count > 0 ); ?> <?php disabled( 0 === $grmlt_count ); ?>>
							<?php echo esc_html( $grmlt_obj ? $grmlt_obj->labels->name : $grmlt_taxonomy ); ?> <span class="grmlt-count">(<?php echo esc_html( number_format_i18n( $grmlt_count ) ); ?>)</span>
						</label>
					<?php endforeach; ?>
					<?php if ( $grmlt_attachments ) : ?>
						<?php $grmlt_count = isset( $grmlt_counts['post_types']['attachment'] ) ? (int) $grmlt_counts['post_types']['attachment'] : 0; ?>
						<label>
							<input type="checkbox" name="include_attachments" value="1" <?php disabled( 0 === $grmlt_count ); ?>>
							<?php esc_html_e( 'Media attachment pages', 'greek-multi-tool' ); ?> <span class="grmlt-count">(<?php echo esc_html( number_format_i18n( $grmlt_count ) ); ?>)</span>
						</label>
					<?php endif; ?>
				</fieldset>
				<p class="description"><?php esc_html_e( 'Internal post types, such as those of ACF, are always excluded.', 'greek-multi-tool' ); ?></p>
			</div>
		</div>

		<div class="grmlt-step">
			<h3 class="grmlt-step__title"><span class="grmlt-step__num" aria-hidden="true">2</span> <?php esc_html_e( 'Preview', 'greek-multi-tool' ); ?></h3>
			<div class="grmlt-step__body">
				<p><button type="button" class="button" id="grmlt-preview-btn"><?php esc_html_e( 'Preview changes', 'greek-multi-tool' ); ?></button> <span class="spinner"></span></p>
				<div id="grmlt-preview-result" aria-live="polite"></div>
			</div>
		</div>

		<div class="grmlt-step">
			<h3 class="grmlt-step__title"><span class="grmlt-step__num" aria-hidden="true">3</span> <?php esc_html_e( 'Convert', 'greek-multi-tool' ); ?></h3>
			<div class="grmlt-step__body">
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Back up your database before you continue.', 'greek-multi-tool' ); ?></p></div>
				<p><label><input type="checkbox" id="grmlt-backup-confirm"> <?php esc_html_e( 'I have backed up the database.', 'greek-multi-tool' ); ?></label></p>
				<p>
					<button type="button" class="button button-primary" id="grmlt-convert-btn" disabled aria-describedby="grmlt-convert-hint"><?php esc_html_e( 'Convert permalinks', 'greek-multi-tool' ); ?></button>
					<span class="spinner"></span>
				</p>
				<p class="description" id="grmlt-convert-hint"><?php esc_html_e( 'Run the preview and confirm the backup first.', 'greek-multi-tool' ); ?></p>
				<div class="grmlt-progress" id="grmlt-convert-progress" hidden>
					<progress max="100" value="0"></progress>
					<p class="grmlt-progress__text" aria-live="polite"></p>
					<p>
						<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'Cancel', 'greek-multi-tool' ); ?></button>
						<button type="button" class="button" data-action="continue" hidden><?php esc_html_e( 'Continue', 'greek-multi-tool' ); ?></button>
					</p>
				</div>
				<div id="grmlt-convert-result" aria-live="polite"></div>
			</div>
		</div>
	</div>
</section>

<?php if ( $grmlt_last ) : ?>
<section class="grmlt-card" aria-labelledby="grmlt-card-last" id="grmlt-last-conversion">
	<div class="grmlt-card__header">
		<div class="grmlt-card__heading">
			<h2 id="grmlt-card-last"><?php esc_html_e( 'Last conversion', 'greek-multi-tool' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: date, 2: posts, 3: terms, 4: standard */
					esc_html__( '%1$s — %2$s and %3$s were converted (%4$s).', 'greek-multi-tool' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $grmlt_last['time'] ) ),
					esc_html( sprintf( /* translators: %s: number */ _n( '%s post', '%s posts', (int) $grmlt_last['posts'], 'greek-multi-tool' ), number_format_i18n( (int) $grmlt_last['posts'] ) ) ),
					esc_html( sprintf( /* translators: %s: number */ _n( '%s term', '%s terms', (int) $grmlt_last['terms'], 'greek-multi-tool' ), number_format_i18n( (int) $grmlt_last['terms'] ) ) ),
					esc_html( isset( $grmlt_modes[ $grmlt_last['mode'] ] ) ? $grmlt_modes[ $grmlt_last['mode'] ] : $grmlt_last['mode'] )
				);
				?>
			</p>
		</div>
	</div>
	<div class="grmlt-card__body" data-grmlt-undo>
		<?php if ( empty( $grmlt_last['complete'] ) && 'legacy' !== $grmlt_last['run_id'] ) : ?>
			<p><strong><?php esc_html_e( 'This conversion was cancelled before it finished; the permalinks converted until then can still be undone.', 'greek-multi-tool' ); ?></strong></p>
		<?php endif; ?>
		<?php if ( 'legacy' === $grmlt_last['run_id'] ) : ?>
			<p><?php esc_html_e( 'This conversion was made by version 3.x. Undo restores the previous permalinks and keeps the redirects migrated from 3.x; the Latin addresses then redirect to the restored ones. Permalinks that were changed again afterwards are left as they are.', 'greek-multi-tool' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Undo restores the previous permalinks and deletes the redirects this conversion created for them and for their child pages and terms. The Latin addresses keep working: they redirect to the restored ones (posts through WordPress’ own old-slug redirect, pages and terms through new redirects). Permalinks that were changed again afterwards are left as they are.', 'greek-multi-tool' ); ?></p>
		<?php endif; ?>
		<p><button type="button" class="button" id="grmlt-undo-btn" data-grmlt-confirm="<?php esc_attr_e( 'Undo the last conversion?', 'greek-multi-tool' ); ?>"><?php esc_html_e( 'Undo', 'greek-multi-tool' ); ?></button> <span class="spinner"></span></p>
		<div class="grmlt-progress" id="grmlt-undo-progress" hidden>
			<progress max="100" value="0"></progress>
			<p class="grmlt-progress__text" aria-live="polite"></p>
		</div>
		<div id="grmlt-undo-result" aria-live="polite"></div>
	</div>
</section>
<?php endif; ?>

<section class="grmlt-card grmlt-card--feature<?php echo $grmlt_redirect_on ? '' : ' grmlt-card--off'; ?>" aria-labelledby="grmlt-card-redirects" id="grmlt-redirects">
	<div class="grmlt-card__header">
		<div class="grmlt-card__heading">
			<h2 id="grmlt-card-redirects"><?php esc_html_e( '301 redirects', 'greek-multi-tool' ); ?></h2>
			<p><?php esc_html_e( 'Old Greek permalinks lead permanently to the new ones, so search engine rankings are kept. Redirects follow the post or term, so they keep working if the permalink structure changes later.', 'greek-multi-tool' ); ?></p>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="grmlt-redirect-form" data-grmlt-track>
			<?php settings_fields( 'grmlt_settings_redirects' ); ?>
			<label class="grmlt-switch">
				<input type="checkbox" role="switch" name="grmlt_redirect" id="grmlt_redirect" value="1" <?php checked( $grmlt_redirect_on ); ?> aria-labelledby="grmlt-card-redirects" data-grmlt-feature="grmlt-card-redirects">
				<span class="grmlt-switch__ui" aria-hidden="true"></span>
			</label>
		</form>
	</div>
	<div class="grmlt-card__body">
		<div class="grmlt-toolbar">
			<button type="button" class="button" id="grmlt-check-btn" <?php disabled( 0 === GRMLT_Redirects::count() ); ?>><?php esc_html_e( 'Check redirects', 'greek-multi-tool' ); ?></button>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="grmlt-inline-form">
				<input type="hidden" name="action" value="grmlt_redirects_export">
				<?php wp_nonce_field( 'grmlt_redirects_export' ); ?>
				<button type="submit" class="button" <?php disabled( 0 === GRMLT_Redirects::count() ); ?>><?php esc_html_e( 'Export CSV', 'greek-multi-tool' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="grmlt-inline-form grmlt-import-form">
				<input type="hidden" name="action" value="grmlt_redirects_import">
				<?php wp_nonce_field( 'grmlt_redirects_import' ); ?>
				<label for="grmlt_csv" class="screen-reader-text"><?php esc_html_e( 'CSV file', 'greek-multi-tool' ); ?></label>
				<input type="file" name="grmlt_csv" id="grmlt_csv" accept=".csv,text/csv" required>
				<button type="submit" class="button"><?php esc_html_e( 'Import CSV', 'greek-multi-tool' ); ?></button>
			</form>
		</div>
		<div class="grmlt-progress" id="grmlt-check-progress" hidden>
			<progress max="100" value="0"></progress>
			<p class="grmlt-progress__text" aria-live="polite"></p>
		</div>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="grmlt-search-form">
			<input type="hidden" name="page" value="<?php echo esc_attr( GRMLT_SETTINGS_PAGE ); ?>">
			<input type="hidden" name="tab" value="conversion">
			<?php $grmlt_table->views(); ?>
			<?php $grmlt_table->search_box( __( 'Search redirects', 'greek-multi-tool' ), 'grmlt-redirect' ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( GRMLT_Admin::tab_url( 'conversion' ) ); ?>" class="grmlt-table-form">
			<?php $grmlt_table->display(); ?>
		</form>
	</div>
</section>

<div class="grmlt-savebar" data-grmlt-savebar="grmlt-redirect-form" hidden>
	<button type="submit" class="button button-primary" form="grmlt-redirect-form"><?php esc_html_e( 'Save changes', 'greek-multi-tool' ); ?></button>
	<span class="grmlt-savebar__status" role="status" aria-live="polite" data-unsaved="<?php esc_attr_e( 'There are unsaved changes.', 'greek-multi-tool' ); ?>"></span>
</div>
