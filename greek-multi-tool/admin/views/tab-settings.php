<?php
/**
 * Settings tab.
 *
 * @package Grmlt_Plugin
 */

defined( 'ABSPATH' ) || exit;

$grmlt_text_on   = grmlt_is_on( 'grmlt_text' );
$grmlt_media_on  = grmlt_is_on( 'grmlt_media_file_name' );
$grmlt_accent_on = grmlt_is_on( 'grmlt_enable_text_analysis' );
$grmlt_mode      = grmlt_get_option( 'grmlt_diphthongs' );
$grmlt_modes     = grmlt_get_modes();
$grmlt_structure = get_option( 'permalink_structure' );
$grmlt_rules     = grmlt_text_analysis_rules();
$grmlt_checks    = GRMLT_Site_Health::run_all();
$grmlt_attention = array_filter(
	$grmlt_checks,
	function ( $check ) {
		return 'good' !== $check['status'] || ! empty( $check['pending'] );
	}
);
$grmlt_mode_descriptions = array(
	'elot743'       => __( 'The official Greek standard (ELOT 743 / ISO 843), as used in passports and road signs. Recommended.', 'greek-multi-tool' ),
	'advanced'      => __( 'Closer to pronunciation: αι → e, ει/οι → i, μπ → b everywhere.', 'greek-multi-tool' ),
	'simple-legacy' => __( 'Keeps new permalinks identical to those created by version 3.x (for example μπλούζα → mplouza). If you switch to ELOT 743, new permalinks follow the standard (blouza); existing ones do not change.', 'greek-multi-tool' ),
);
?>
<div class="grmlt-columns">
	<div class="grmlt-main">
		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="grmlt-settings-form" class="grmlt-settings-form" data-grmlt-track>
			<?php settings_fields( 'grmlt_settings_main' ); ?>

			<section class="grmlt-card grmlt-card--feature<?php echo $grmlt_text_on ? '' : ' grmlt-card--off'; ?>" aria-labelledby="grmlt-card-permalinks">
				<div class="grmlt-card__header">
					<div class="grmlt-card__heading">
						<h2 id="grmlt-card-permalinks"><?php esc_html_e( 'Greek permalinks', 'greek-multi-tool' ); ?></h2>
						<p>
							<?php esc_html_e( 'Converts Greek characters to Latin in the permalinks of posts, pages, custom post types, terms and attachments as they are created. Existing permalinks do not change;', 'greek-multi-tool' ); ?>
							<a href="<?php echo esc_url( GRMLT_Admin::tab_url( 'conversion' ) ); ?>"><?php esc_html_e( 'convert them in the Conversion tab.', 'greek-multi-tool' ); ?></a>
						</p>
					</div>
					<label class="grmlt-switch">
						<input type="checkbox" role="switch" name="grmlt_text" id="grmlt_text" value="1" <?php checked( $grmlt_text_on ); ?> aria-labelledby="grmlt-card-permalinks" data-grmlt-feature="grmlt-card-permalinks">
						<span class="grmlt-switch__ui" aria-hidden="true"></span>
					</label>
				</div>
				<div class="grmlt-card__body"<?php echo $grmlt_text_on ? '' : ' inert'; ?>>
					<div class="grmlt-row">
						<div class="grmlt-row__label">
							<span class="grmlt-row__title" id="grmlt-mode-label"><?php esc_html_e( 'Transliteration standard', 'greek-multi-tool' ); ?></span>
							<span class="grmlt-row__help"><?php esc_html_e( 'Also used for media file names.', 'greek-multi-tool' ); ?></span>
						</div>
						<div class="grmlt-row__field">
							<fieldset class="grmlt-radio-cards" aria-labelledby="grmlt-mode-label">
								<legend class="screen-reader-text"><?php esc_html_e( 'Transliteration standard', 'greek-multi-tool' ); ?></legend>
								<?php
								foreach ( $grmlt_modes as $grmlt_mode_key => $grmlt_mode_label ) :
									if ( 'simple-legacy' === $grmlt_mode_key && 'simple-legacy' !== $grmlt_mode ) {
										continue;
									}
									$grmlt_examples = grmlt_get_mode_examples( $grmlt_mode_key );
									?>
									<label class="grmlt-radio-card<?php echo $grmlt_mode_key === $grmlt_mode ? ' is-selected' : ''; ?>">
										<input type="radio" name="grmlt_diphthongs" value="<?php echo esc_attr( $grmlt_mode_key ); ?>" <?php checked( $grmlt_mode_key, $grmlt_mode ); ?>>
										<span class="grmlt-radio-card__title"><?php echo esc_html( $grmlt_mode_label ); ?></span>
										<span class="grmlt-radio-card__desc"><?php echo esc_html( $grmlt_mode_descriptions[ $grmlt_mode_key ] ); ?></span>
										<code class="grmlt-radio-card__example">
											<?php
											$grmlt_parts = array();
											foreach ( $grmlt_examples as $grmlt_greek => $grmlt_latin ) {
												$grmlt_parts[] = $grmlt_greek . ' → ' . $grmlt_latin;
											}
											echo esc_html( implode( '  ·  ', $grmlt_parts ) );
											?>
										</code>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<?php if ( 'simple-legacy' === $grmlt_mode ) : ?>
								<div class="notice notice-warning inline grmlt-legacy-warning" id="grmlt-legacy-warning" hidden>
									<p><?php esc_html_e( 'After saving, the “3.x compatibility” option will no longer be available.', 'greek-multi-tool' ); ?></p>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="grmlt-row">
						<div class="grmlt-row__label">
							<span class="grmlt-row__title" id="grmlt-short-words-label"><?php esc_html_e( 'Short words', 'greek-multi-tool' ); ?></span>
							<span class="grmlt-row__help"><?php esc_html_e( 'Numbers are never removed.', 'greek-multi-tool' ); ?></span>
						</div>
						<div class="grmlt-row__field grmlt-checks" role="group" aria-labelledby="grmlt-short-words-label">
							<label><input type="checkbox" name="grmlt_one_letter_words" id="grmlt_one_letter_words" value="1" <?php checked( grmlt_is_on( 'grmlt_one_letter_words' ) ); ?>> <?php esc_html_e( 'Remove one-letter words', 'greek-multi-tool' ); ?></label>
							<label><input type="checkbox" name="grmlt_two_letter_words" id="grmlt_two_letter_words" value="1" <?php checked( grmlt_is_on( 'grmlt_two_letter_words' ) ); ?>> <?php esc_html_e( 'Remove two-letter words', 'greek-multi-tool' ); ?></label>
						</div>
					</div>

					<div class="grmlt-row">
						<div class="grmlt-row__label">
							<label class="grmlt-row__title" for="grmlt_stwords"><?php esc_html_e( 'Excluded words', 'greek-multi-tool' ); ?></label>
							<span class="grmlt-row__help"><?php esc_html_e( 'Never appear in permalinks. Matched regardless of accents and letter case.', 'greek-multi-tool' ); ?></span>
						</div>
						<div class="grmlt-row__field">
							<input type="text" class="regular-text grmlt-chips-source" id="grmlt_stwords" name="grmlt_stwords" value="<?php echo esc_attr( grmlt_get_option( 'grmlt_stwords' ) ); ?>" placeholder="<?php esc_attr_e( 'e.g. και, με, σε', 'greek-multi-tool' ); ?>" aria-describedby="grmlt-stwords-help">
							<p class="description" id="grmlt-stwords-help"><?php esc_html_e( 'Separate words with commas.', 'greek-multi-tool' ); ?></p>
						</div>
					</div>

					<div class="grmlt-row">
						<div class="grmlt-row__label">
							<span class="grmlt-row__title"><?php esc_html_e( 'Permalink structure', 'greek-multi-tool' ); ?></span>
						</div>
						<div class="grmlt-row__field">
							<?php if ( '' === (string) $grmlt_structure ) : ?>
								<div class="notice notice-warning inline"><p><?php esc_html_e( 'The site uses plain permalinks (?p=123), so transliteration has no visible effect on URLs.', 'greek-multi-tool' ); ?></p></div>
							<?php else : ?>
								<code class="grmlt-path"><?php echo esc_html( $grmlt_structure ); ?></code>
							<?php endif; ?>
							<p class="description"><a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php esc_html_e( 'Change it in Settings → Permalinks', 'greek-multi-tool' ); ?></a></p>
						</div>
					</div>
				</div>
			</section>

			<section class="grmlt-card grmlt-card--feature<?php echo $grmlt_media_on ? '' : ' grmlt-card--off'; ?>" aria-labelledby="grmlt-card-media">
				<div class="grmlt-card__header">
					<div class="grmlt-card__heading">
						<h2 id="grmlt-card-media"><?php esc_html_e( 'Media file names', 'greek-multi-tool' ); ?></h2>
						<p><?php esc_html_e( 'Renames uploaded files with Greek names to Latin, for example φωτογραφία.jpg → fotografia.jpg, using the transliteration standard above. Files that are already uploaded are not renamed.', 'greek-multi-tool' ); ?></p>
					</div>
					<label class="grmlt-switch">
						<input type="checkbox" role="switch" name="grmlt_media_file_name" id="grmlt_media_file_name" value="1" <?php checked( $grmlt_media_on ); ?> aria-labelledby="grmlt-card-media" data-grmlt-feature="grmlt-card-media">
						<span class="grmlt-switch__ui" aria-hidden="true"></span>
					</label>
				</div>
			</section>

			<section class="grmlt-card grmlt-card--feature<?php echo $grmlt_accent_on ? '' : ' grmlt-card--off'; ?>" aria-labelledby="grmlt-card-accent">
				<div class="grmlt-card__header">
					<div class="grmlt-card__heading">
						<h2 id="grmlt-card-accent"><?php esc_html_e( 'Accent check', 'greek-multi-tool' ); ?></h2>
						<p><?php esc_html_e( 'Adds a panel to the editor sidebar that points out words whose accent is probably wrong.', 'greek-multi-tool' ); ?></p>
					</div>
					<label class="grmlt-switch">
						<input type="checkbox" role="switch" name="grmlt_enable_text_analysis" id="grmlt_enable_text_analysis" value="1" <?php checked( $grmlt_accent_on ); ?> aria-labelledby="grmlt-card-accent" data-grmlt-feature="grmlt-card-accent">
						<span class="grmlt-switch__ui" aria-hidden="true"></span>
					</label>
				</div>
				<div class="grmlt-card__body"<?php echo $grmlt_accent_on ? '' : ' inert'; ?>>
					<details class="grmlt-details">
						<summary>
							<?php
							printf(
								/* translators: %d: number of rules */
								esc_html__( 'Which rules are checked (%d)', 'greek-multi-tool' ),
								count( $grmlt_rules )
							);
							?>
						</summary>
						<ul class="grmlt-list">
							<?php foreach ( $grmlt_rules as $grmlt_rule ) : ?>
								<li><?php echo esc_html( $grmlt_rule ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				</div>
			</section>
		</form>

		<div class="grmlt-savebar" data-grmlt-savebar="grmlt-settings-form">
			<button type="submit" class="button button-primary" form="grmlt-settings-form"><?php esc_html_e( 'Save changes', 'greek-multi-tool' ); ?></button>
			<span class="grmlt-savebar__status" role="status" aria-live="polite" data-unsaved="<?php esc_attr_e( 'There are unsaved changes.', 'greek-multi-tool' ); ?>"></span>
		</div>
	</div>

	<aside class="grmlt-side">
		<section class="grmlt-card" aria-labelledby="grmlt-card-try">
			<div class="grmlt-card__header">
				<div class="grmlt-card__heading">
					<h2 id="grmlt-card-try"><?php esc_html_e( 'Try it', 'greek-multi-tool' ); ?></h2>
					<p><?php esc_html_e( 'Shows the result with the settings as they are on this screen, even before you save.', 'greek-multi-tool' ); ?></p>
				</div>
			</div>
			<div class="grmlt-card__body grmlt-try" data-grmlt-preview>
				<p>
					<label for="grmlt-try-title"><?php esc_html_e( 'Post title', 'greek-multi-tool' ); ?></label>
					<input type="text" id="grmlt-try-title" class="widefat" value="<?php esc_attr_e( 'Οι καλύτερες ιδέες για το σαλόνι σας', 'greek-multi-tool' ); ?>">
					<output for="grmlt-try-title" class="grmlt-try__out" id="grmlt-try-title-out"><code class="grmlt-path"><?php echo esc_html( user_trailingslashit( home_url( '/' . grmlt_preview_slug( __( 'Οι καλύτερες ιδέες για το σαλόνι σας', 'greek-multi-tool' ) ) ) ) ); ?></code></output>
				</p>
				<p>
					<label for="grmlt-try-file"><?php esc_html_e( 'File name', 'greek-multi-tool' ); ?></label>
					<input type="text" id="grmlt-try-file" class="widefat" value="<?php esc_attr_e( 'Φωτογραφία προϊόντος.JPG', 'greek-multi-tool' ); ?>">
					<output for="grmlt-try-file" class="grmlt-try__out" id="grmlt-try-file-out"><code class="grmlt-path"><?php echo esc_html( grmlt_preview_filename( __( 'Φωτογραφία προϊόντος.JPG', 'greek-multi-tool' ) ) ); ?></code></output>
				</p>
				<p class="description grmlt-try__status" role="status" aria-live="polite"></p>
			</div>
		</section>

		<section class="grmlt-card" aria-labelledby="grmlt-card-readiness">
			<div class="grmlt-card__header">
				<div class="grmlt-card__heading">
					<h2 id="grmlt-card-readiness"><?php esc_html_e( 'Greek site readiness', 'greek-multi-tool' ); ?></h2>
				</div>
			</div>
			<div class="grmlt-card__body">
				<?php if ( empty( $grmlt_attention ) ) : ?>
					<p class="grmlt-ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Everything looks right.', 'greek-multi-tool' ); ?></p>
				<?php else : ?>
					<ul class="grmlt-readiness">
						<?php foreach ( $grmlt_attention as $grmlt_check ) : ?>
							<li class="<?php echo ! empty( $grmlt_check['pending'] ) ? 'is-pending' : 'is-warning'; ?>">
								<span class="dashicons <?php echo ! empty( $grmlt_check['pending'] ) ? 'dashicons-clock' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
								<span class="grmlt-readiness__text"><?php echo esc_html( $grmlt_check['short'] ); ?></span>
								<?php if ( ! empty( $grmlt_check['action_url'] ) ) : ?>
									<a class="grmlt-readiness__action" href="<?php echo esc_url( $grmlt_check['action_url'] ); ?>"><?php echo esc_html( $grmlt_check['action_text'] ); ?></a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="description"><a href="<?php echo esc_url( admin_url( 'site-health.php' ) ); ?>"><?php esc_html_e( 'All checks in Site Health', 'greek-multi-tool' ); ?></a></p>
			</div>
		</section>
	</aside>
</div>

