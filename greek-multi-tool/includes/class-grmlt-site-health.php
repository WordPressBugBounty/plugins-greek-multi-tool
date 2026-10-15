<?php
/**
 * Greek readiness checks in Site Health, and the same checks for the
 * readiness card on the plugin screen.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Drops every cached readiness measurement.
 */
function grmlt_invalidate_health_cache() {
	foreach ( array( 'grmlt_greek_counts', 'grmlt_health_filenames', 'grmlt_health_collation', 'grmlt_health_html_lang', 'grmlt_health_greek_titles' ) as $key ) {
		delete_transient( $key );
	}
}

class GRMLT_Site_Health {

	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
		add_filter( 'debug_information', array( __CLASS__, 'debug_information' ) );
	}

	/**
	 * Registers the tests.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		$tests['direct']['grmlt_locale']          = array(
			'label' => __( 'Site language for Greek content', 'greek-multi-tool' ),
			'test'  => array( __CLASS__, 'test_locale' ),
		);
		$tests['direct']['grmlt_db_collation']    = array(
			'label' => __( 'Accent-insensitive search in the database', 'greek-multi-tool' ),
			'test'  => array( __CLASS__, 'test_db_collation' ),
		);
		$tests['direct']['grmlt_greek_slugs']     = array(
			'label' => __( 'Greek permalinks', 'greek-multi-tool' ),
			'test'  => array( __CLASS__, 'test_greek_slugs' ),
		);
		$tests['direct']['grmlt_greek_filenames'] = array(
			'label' => __( 'Greek media file names', 'greek-multi-tool' ),
			'test'  => array( __CLASS__, 'test_greek_filenames' ),
		);
		$tests['direct']['grmlt_redirect_health'] = array(
			'label' => __( 'Redirects from old Greek permalinks', 'greek-multi-tool' ),
			'test'  => array( __CLASS__, 'test_redirect_health' ),
		);
		if ( 0 === strpos( get_locale(), 'el' ) ) {
			$tests['async']['grmlt_html_lang'] = array(
				'label'             => __( 'Language attribute of the HTML page', 'greek-multi-tool' ),
				'test'              => rest_url( 'grmlt/v1/site-health/html-lang' ),
				'has_rest'          => true,
				'async_direct_test' => array( __CLASS__, 'test_html_lang' ),
			);
		}
		return $tests;
	}

	/**
	 * Ids of the direct tests, in display order.
	 *
	 * @return string[]
	 */
	public static function direct_test_ids() {
		return array( 'grmlt_locale', 'grmlt_html_lang', 'grmlt_db_collation', 'grmlt_greek_slugs', 'grmlt_greek_filenames', 'grmlt_redirect_health' );
	}

	/**
	 * Runs every check for the readiness card (the async one from its cache).
	 *
	 * @return array<string, array>
	 */
	public static function run_all() {
		$results = array();
		foreach ( self::direct_test_ids() as $id ) {
			if ( 'grmlt_html_lang' === $id ) {
				if ( 0 !== strpos( get_locale(), 'el' ) ) {
					continue;
				}
				$results[ $id ] = self::test_html_lang( true );
				continue;
			}
			$method = 'test_' . substr( $id, 6 );
			$results[ $id ] = call_user_func( array( __CLASS__, $method ) );
		}
		return $results;
	}

	/**
	 * Common result skeleton.
	 *
	 * @param string $id          Test id.
	 * @param string $label       Label.
	 * @param string $status      good|recommended.
	 * @param string $description HTML.
	 * @param string $actions     HTML.
	 * @param string $short       Plain one-line summary for the readiness card.
	 * @param string $action_url  URL for the readiness card action.
	 * @param string $action_text Text for the readiness card action.
	 * @return array
	 */
	private static function result( $id, $label, $status, $description, $actions = '', $short = '', $action_url = '', $action_text = '' ) {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => 'Greek Multi Tool',
				'color' => 'blue',
			),
			'description' => $description,
			'actions'     => $actions,
			'test'        => $id,
			'short'       => $short,
			'action_url'  => $action_url,
			'action_text' => $action_text,
		);
	}

	/**
	 * Whether the site holds Greek content (cached).
	 *
	 * @return bool
	 */
	public static function has_greek_content() {
		global $wpdb;
		$counts = GRMLT_Converter::count_candidates();
		if ( $counts['posts_total'] + $counts['terms_total'] > 0 ) {
			return true;
		}
		$cached = get_transient( 'grmlt_health_greek_titles' );
		if ( false !== $cached ) {
			return (bool) $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$titles = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('post','page') ORDER BY ID DESC LIMIT 30" );
		$found  = 0;
		foreach ( (array) $titles as $title ) {
			if ( grmlt_has_greek( $title ) ) {
				$found = 1;
				break;
			}
		}
		set_transient( 'grmlt_health_greek_titles', $found, self::CACHE_TTL );
		return (bool) $found;
	}

	/**
	 * Test: site language.
	 *
	 * @return array
	 */
	public static function test_locale() {
		$label = __( 'Site language for Greek content', 'greek-multi-tool' );
		$is_el = 0 === strpos( get_locale(), 'el' );
		if ( $is_el ) {
			return self::result( 'grmlt_locale', $label, 'good', '<p>' . esc_html__( 'The site language is Greek, so WordPress shows Greek dates and browsers handle Greek capitals correctly.', 'greek-multi-tool' ) . '</p>', '', __( 'Site language is Greek.', 'greek-multi-tool' ) );
		}
		if ( ! self::has_greek_content() ) {
			return self::result( 'grmlt_locale', $label, 'good', '<p>' . esc_html__( 'The site language is not Greek, but no Greek content was found, so nothing depends on it.', 'greek-multi-tool' ) . '</p>', '', __( 'No Greek content found.', 'greek-multi-tool' ) );
		}
		return self::result(
			'grmlt_locale',
			$label,
			'recommended',
			'<p>' . esc_html__( 'The site language is not Greek, but the content is.', 'greek-multi-tool' ) . '</p><p>' . esc_html__( 'Greek dates, month names in the right grammatical case and accent-free capitals all depend on the site language. Set it to Greek in Settings → General.', 'greek-multi-tool' ) . '</p>',
			'<p><a href="' . esc_url( admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Open Settings → General', 'greek-multi-tool' ) . '</a></p>',
			__( 'The site language is not Greek.', 'greek-multi-tool' ),
			admin_url( 'options-general.php' ),
			__( 'Settings', 'greek-multi-tool' )
		);
	}

	/**
	 * Test: lang attribute of the front page (loopback, cached 12 hours).
	 *
	 * @param bool $cached_only Return the stored result without a request.
	 * @return array
	 */
	public static function test_html_lang( $cached_only = false ) {
		$label  = __( 'Language attribute of the HTML page', 'greek-multi-tool' );
		$stored = get_transient( 'grmlt_health_html_lang' );
		if ( ! is_array( $stored ) && $cached_only ) {
			return self::result( 'grmlt_html_lang', $label, 'good', '', '', __( 'Not checked yet.', 'greek-multi-tool' ), admin_url( 'site-health.php' ), __( 'Check now', 'greek-multi-tool' ) ) + array( 'pending' => true );
		}
		if ( ! is_array( $stored ) ) {
			$stored = self::fetch_html_lang();
			set_transient( 'grmlt_health_html_lang', $stored, self::CACHE_TTL );
		}
		if ( ! empty( $stored['error'] ) ) {
			return self::result( 'grmlt_html_lang', $label, 'good', '<p>' . esc_html__( 'The front page could not be requested from the server, so the language attribute could not be verified.', 'greek-multi-tool' ) . ' ' . esc_html( $stored['error'] ) . '</p>', '', __( 'Could not be verified.', 'greek-multi-tool' ) );
		}
		$lang = isset( $stored['lang'] ) ? $stored['lang'] : '';
		if ( '' !== $lang && 0 === stripos( $lang, 'el' ) ) {
			return self::result( 'grmlt_html_lang', $label, 'good', '<p>' . sprintf( /* translators: %s: lang attribute value */ esc_html__( 'The page declares lang="%s".', 'greek-multi-tool' ), esc_html( $lang ) ) . '</p>', '', __( 'The page declares Greek as its language.', 'greek-multi-tool' ) );
		}
		return self::result(
			'grmlt_html_lang',
			$label,
			'recommended',
			'<p>' . esc_html__( 'The front page does not declare Greek as its language.', 'greek-multi-tool' ) . '</p><p>' . esc_html__( 'Without lang="el" on the <html> element browsers keep the accents on capital letters and screen readers read the text in the wrong language. The theme must call language_attributes() in its <html> tag.', 'greek-multi-tool' ) . ( '' !== $lang ? ' ' . sprintf( /* translators: %s: lang attribute value */ esc_html__( 'Found: lang="%s".', 'greek-multi-tool' ), esc_html( $lang ) ) : '' ) . '</p>',
			'',
			__( 'The theme does not declare Greek as the page language.', 'greek-multi-tool' )
		);
	}

	/**
	 * Loopback request for the html lang attribute.
	 *
	 * @return array{lang?:string, error?:string, checked:int}
	 */
	private static function fetch_html_lang() {
		$url      = home_url( '/' );
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				/** This filter is documented in wp-includes/class-wp-http-streams.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', false, $url ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				'headers'   => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'error'   => $response->get_error_message(),
				'checked' => time(),
			);
		}
		$body = wp_remote_retrieve_body( $response );
		if ( preg_match( '/<html[^>]*\slang\s*=\s*["\']?([a-zA-Z0-9\-]+)/i', $body, $m ) ) {
			return array(
				'lang'    => $m[1],
				'checked' => time(),
			);
		}
		return array(
			'lang'    => '',
			'checked' => time(),
		);
	}

	/**
	 * Test: accent-insensitive collation, verified empirically.
	 *
	 * @return array
	 */
	public static function test_db_collation() {
		global $wpdb;
		$label  = __( 'Accent-insensitive search in the database', 'greek-multi-tool' );
		$cached = get_transient( 'grmlt_health_collation' );
		if ( ! is_array( $cached ) ) {
			$cached = array(
				'collation' => '',
				'charset'   => '',
				'result'    => null,
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT COLLATION_NAME, CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s', $wpdb->posts, 'post_title' ) );
			if ( $row && preg_match( '/^[a-z0-9_]+$/i', (string) $row->COLLATION_NAME ) && preg_match( '/^[a-z0-9_]+$/i', (string) $row->CHARACTER_SET_NAME ) ) {
				$cached['collation'] = $row->COLLATION_NAME;
				$cached['charset']   = $row->CHARACTER_SET_NAME;
				$charset             = $row->CHARACTER_SET_NAME;
				$collation           = $row->COLLATION_NAME;
				$previous = $wpdb->suppress_errors( true );
				// Charset and collation names were validated against /^[a-z0-9_]+$/ above; they cannot be bound as values.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
				$result = $wpdb->get_var( "SELECT _{$charset}'δουλειά' = _{$charset}'δουλεια' COLLATE {$collation}" );
				$wpdb->suppress_errors( $previous );
				$cached['result'] = null === $result ? null : (int) $result;
			}
			set_transient( 'grmlt_health_collation', $cached, self::CACHE_TTL );
		}
		if ( null === $cached['result'] ) {
			return self::result( 'grmlt_db_collation', $label, 'good', '<p>' . esc_html__( 'The database collation could not be tested on this server.', 'greek-multi-tool' ) . '</p>', '', __( 'Collation could not be verified.', 'greek-multi-tool' ) );
		}
		if ( 1 === $cached['result'] ) {
			return self::result( 'grmlt_db_collation', $label, 'good', '<p>' . sprintf( /* translators: %s: collation name */ esc_html__( 'Searching for a word without accents finds it with accents too (collation %s).', 'greek-multi-tool' ), esc_html( $cached['collation'] ) ) . '</p>', '', __( 'Search ignores accents.', 'greek-multi-tool' ) );
		}
		return self::result(
			'grmlt_db_collation',
			$label,
			'recommended',
			'<p>' . esc_html__( 'The site search distinguishes accented from unaccented letters.', 'greek-multi-tool' ) . '</p><p>' . sprintf( /* translators: %s: collation name */ esc_html__( 'The posts table uses the collation %s, which treats “δουλειά” and “δουλεια” as different words. An accent-insensitive collation such as utf8mb4_unicode_ci or utf8mb4_0900_ai_ci fixes this. Ask your hosting provider or database administrator; the plugin never changes the database itself.', 'greek-multi-tool' ), esc_html( $cached['collation'] ) ) . '</p>',
			'',
			__( 'Site search distinguishes accents.', 'greek-multi-tool' )
		);
	}

	/**
	 * Test: Greek slugs remaining.
	 *
	 * @return array
	 */
	public static function test_greek_slugs() {
		$label  = __( 'Greek permalinks', 'greek-multi-tool' );
		$counts = GRMLT_Converter::count_candidates();
		$posts  = (int) $counts['posts_total'];
		$terms  = (int) $counts['terms_total'];
		$url    = admin_url( 'admin.php?page=' . GRMLT_SETTINGS_PAGE . '&tab=conversion' );
		if ( 0 === $posts + $terms ) {
			return self::result( 'grmlt_greek_slugs', $label, 'good', '<p>' . esc_html__( 'No post or term still has a Greek permalink.', 'greek-multi-tool' ) . '</p>', '', __( 'All permalinks are Latin.', 'greek-multi-tool' ) );
		}
		$summary = sprintf(
			/* translators: 1: number of posts, 2: number of terms */
			__( '%1$s and %2$s still have Greek permalinks.', 'greek-multi-tool' ),
			sprintf( /* translators: %s: number */ _n( '%s post', '%s posts', $posts, 'greek-multi-tool' ), number_format_i18n( $posts ) ),
			sprintf( /* translators: %s: number */ _n( '%s term', '%s terms', $terms, 'greek-multi-tool' ), number_format_i18n( $terms ) )
		);
		$extra = grmlt_is_on( 'grmlt_text' ) ? '' : ' ' . esc_html__( 'Greek permalinks are currently not converted for new content either; enable the Greek permalinks feature first.', 'greek-multi-tool' );
		return self::result(
			'grmlt_greek_slugs',
			$label,
			'recommended',
			'<p>' . esc_html( $summary ) . '</p><p>' . esc_html__( 'Greek characters in URLs are shown percent-encoded when shared, which is hard to read. The converter rewrites them to Latin and adds a 301 redirect for every old address.', 'greek-multi-tool' ) . $extra . '</p>',
			'<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the converter', 'greek-multi-tool' ) . '</a></p>',
			$summary,
			$url,
			__( 'Convert', 'greek-multi-tool' )
		);
	}

	/**
	 * Count of attachments whose file name contains Greek characters (cached).
	 *
	 * @return int
	 */
	public static function greek_filename_count() {
		global $wpdb;
		$cached = get_transient( 'grmlt_health_filenames' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND HEX(meta_value) REGEXP '^([0-9A-F]{2})*(CE|CF)[0-9A-F]{2}'" );
		set_transient( 'grmlt_health_filenames', $count, self::CACHE_TTL );
		return $count;
	}

	/**
	 * Test: Greek file names.
	 *
	 * @return array
	 */
	public static function test_greek_filenames() {
		$label = __( 'Greek media file names', 'greek-multi-tool' );
		$count = self::greek_filename_count();
		$url   = admin_url( 'admin.php?page=' . GRMLT_SETTINGS_PAGE . '&tab=settings' );
		if ( $count > 0 && ! grmlt_is_on( 'grmlt_media_file_name' ) ) {
			$summary = sprintf( /* translators: %s: number */ _n( '%s media file has a Greek file name and new uploads are not converted.', '%s media files have Greek file names and new uploads are not converted.', $count, 'greek-multi-tool' ), number_format_i18n( $count ) );
			return self::result(
				'grmlt_greek_filenames',
				$label,
				'recommended',
				'<p>' . esc_html( $summary ) . '</p><p>' . esc_html__( 'Enable the media file name conversion so that every new upload gets a Latin file name. Files that are already uploaded are not renamed.', 'greek-multi-tool' ) . '</p>',
				'<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Open the settings', 'greek-multi-tool' ) . '</a></p>',
				$summary,
				$url,
				__( 'Settings', 'greek-multi-tool' )
			);
		}
		$text = $count > 0
			? sprintf( /* translators: %s: number */ _n( 'New uploads get Latin file names. %s existing file keeps its Greek name (existing files are not renamed).', 'New uploads get Latin file names. %s existing files keep their Greek names (existing files are not renamed).', $count, 'greek-multi-tool' ), number_format_i18n( $count ) )
			: __( 'No media file has a Greek file name.', 'greek-multi-tool' );
		return self::result( 'grmlt_greek_filenames', $label, 'good', '<p>' . esc_html( $text ) . '</p>', '', $text );
	}

	/**
	 * Test: redirect problems from the last check.
	 *
	 * @return array
	 */
	public static function test_redirect_health() {
		$label = __( 'Redirects from old Greek permalinks', 'greek-multi-tool' );
		$total = GRMLT_Redirects::count();
		$url   = admin_url( 'admin.php?page=' . GRMLT_SETTINGS_PAGE . '&tab=conversion&redirect_status=problems' );
		if ( 0 === $total ) {
			return self::result( 'grmlt_redirect_health', $label, 'good', '<p>' . esc_html__( 'There are no redirects yet. They are created automatically when old permalinks are converted.', 'greek-multi-tool' ) . '</p>', '', __( 'No redirects yet.', 'greek-multi-tool' ) );
		}
		$problems = GRMLT_Redirects::problem_count();
		if ( $problems > 0 ) {
			$summary = sprintf( /* translators: %s: number */ _n( '%s redirect points to missing content or forms a chain or loop.', '%s redirects point to missing content or form chains or loops.', $problems, 'greek-multi-tool' ), number_format_i18n( $problems ) );
			return self::result(
				'grmlt_redirect_health',
				$label,
				'recommended',
				'<p>' . esc_html( $summary ) . '</p><p>' . esc_html__( 'Visitors and search engines following those old addresses end up on an error page or go through several hops. Review them in the redirects list.', 'greek-multi-tool' ) . '</p>',
				'<p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Show the problematic redirects', 'greek-multi-tool' ) . '</a></p>',
				$summary,
				$url,
				__( 'Review', 'greek-multi-tool' )
			);
		}
		$counts = GRMLT_Redirects::status_counts();
		$text   = $counts['unchecked'] === $total
			? sprintf( /* translators: %s: number */ _n( '%s redirect exists but has not been checked yet.', '%s redirects exist but have not been checked yet.', $total, 'greek-multi-tool' ), number_format_i18n( $total ) )
			: sprintf( /* translators: %s: number */ _n( '%s redirect works.', 'All %s redirects work.', $total, 'greek-multi-tool' ), number_format_i18n( $total ) );
		return self::result( 'grmlt_redirect_health', $label, 'good', '<p>' . esc_html( $text ) . '</p>', '', $text );
	}

	/**
	 * Site Health → Info section.
	 *
	 * @param array $info Debug info.
	 * @return array
	 */
	public static function debug_information( $info ) {
		$counts    = GRMLT_Converter::count_candidates();
		$collation = get_transient( 'grmlt_health_collation' );
		$last      = GRMLT_Converter::get_last_conversion();
		$modes     = grmlt_get_modes();
		$mode      = grmlt_get_option( 'grmlt_diphthongs' );
		$log       = get_option( 'grmlt_migration_log', array() );
		$statuses  = GRMLT_Redirects::status_counts();

		$fields = array(
			'version'         => array(
				'label' => __( 'Version', 'greek-multi-tool' ),
				'value' => GRMLT_PLUGIN_VERSION,
			),
			'permalinks'      => array(
				'label' => __( 'Greek permalinks', 'greek-multi-tool' ),
				'value' => grmlt_is_on( 'grmlt_text' ) ? __( 'Enabled', 'greek-multi-tool' ) : __( 'Disabled', 'greek-multi-tool' ),
			),
			'mode'            => array(
				'label' => __( 'Transliteration standard', 'greek-multi-tool' ),
				'value' => isset( $modes[ $mode ] ) ? $modes[ $mode ] . ' (' . $mode . ')' : $mode,
			),
			'short_words'     => array(
				'label' => __( 'Short words removed', 'greek-multi-tool' ),
				'value' => ( grmlt_is_on( 'grmlt_one_letter_words' ) ? '1 ' : '' ) . ( grmlt_is_on( 'grmlt_two_letter_words' ) ? '2' : '' ),
			),
			'stopwords'       => array(
				'label' => __( 'Excluded words', 'greek-multi-tool' ),
				'value' => grmlt_get_option( 'grmlt_stwords' ),
			),
			'media'           => array(
				'label' => __( 'Media file names', 'greek-multi-tool' ),
				'value' => grmlt_is_on( 'grmlt_media_file_name' ) ? __( 'Enabled', 'greek-multi-tool' ) : __( 'Disabled', 'greek-multi-tool' ),
			),
			'accent_check'    => array(
				'label' => __( 'Accent check', 'greek-multi-tool' ),
				'value' => grmlt_is_on( 'grmlt_enable_text_analysis' ) ? __( 'Enabled', 'greek-multi-tool' ) : __( 'Disabled', 'greek-multi-tool' ),
			),
			'redirects'       => array(
				'label' => __( '301 redirects', 'greek-multi-tool' ),
				'value' => ( grmlt_is_on( 'grmlt_redirect' ) ? __( 'Enabled', 'greek-multi-tool' ) : __( 'Disabled', 'greek-multi-tool' ) ) . ' — ' . sprintf( 'total %d, ok %d, missing %d, chain %d, loop %d, unchecked %d', GRMLT_Redirects::count(), $statuses['ok'], $statuses['missing'], $statuses['chain'], $statuses['loop'], $statuses['unchecked'] ),
			),
			'last_conversion' => array(
				'label' => __( 'Last conversion', 'greek-multi-tool' ),
				'value' => $last ? sprintf( '%s — %d posts, %d terms (%s)', wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last['time'] ), (int) $last['posts'], (int) $last['terms'], $last['mode'] ) : __( 'None', 'greek-multi-tool' ),
			),
			'greek_slugs'     => array(
				'label' => __( 'Greek permalinks remaining', 'greek-multi-tool' ),
				'value' => sprintf( '%d posts, %d terms', (int) $counts['posts_total'], (int) $counts['terms_total'] ),
			),
			'greek_files'     => array(
				'label' => __( 'Greek media file names', 'greek-multi-tool' ),
				'value' => (string) self::greek_filename_count(),
			),
			'collation'       => array(
				'label' => __( 'posts.post_title collation', 'greek-multi-tool' ),
				'value' => is_array( $collation ) && $collation['collation'] ? $collation['collation'] . ( 1 === $collation['result'] ? ' (accent-insensitive)' : ' (accent-sensitive)' ) : __( 'Not checked yet', 'greek-multi-tool' ),
			),
			'intl'            => array(
				'label' => __( 'PHP intl (Unicode normalization)', 'greek-multi-tool' ),
				'value' => class_exists( 'Normalizer' ) ? __( 'Available', 'greek-multi-tool' ) : __( 'Not available', 'greek-multi-tool' ),
			),
			'mbstring'        => array(
				'label' => __( 'PHP mbstring', 'greek-multi-tool' ),
				'value' => function_exists( 'mb_strtolower' ) ? __( 'Available', 'greek-multi-tool' ) : __( 'Not available: permalinks and redirects work, the accent check needs it', 'greek-multi-tool' ),
			),
		);
		$backups = function_exists( 'grmlt_legacy_backup_tables' ) ? grmlt_legacy_backup_tables() : array();
		if ( $backups ) {
			$fields['legacy_backup'] = array(
				'label' => __( 'Backup of the 3.x redirects table', 'greek-multi-tool' ),
				'value' => implode( ', ', $backups ),
			);
		}
		$failure = function_exists( 'grmlt_upgrade_failure' ) ? grmlt_upgrade_failure() : null;
		if ( $failure ) {
			$fields['migration_error'] = array(
				'label' => __( 'Data update', 'greek-multi-tool' ),
				'value' => sprintf(
					/* translators: 1: date and time, 2: step name, 3: error message */
					__( 'Waiting after an error (%1$s, step %2$s): %3$s', 'greek-multi-tool' ),
					wp_date( 'Y-m-d H:i', (int) $failure['time'] ),
					'' !== $failure['step'] ? $failure['step'] : '-',
					$failure['message']
				),
			);
		}
		if ( is_array( $log ) && ! empty( $log['time'] ) ) {
			$skipped = isset( $log['redirects_skipped'] ) ? $log['redirects_skipped'] : array();
			$lines   = array();
			foreach ( $skipped as $entry ) {
				$lines[] = sprintf( '#%d %s -> %s (%s)', $entry['id'], $entry['old'], $entry['new'], GRMLT_Redirects::reason_label( $entry['reason'] ) );
			}
			// Left out on purpose: rows 3.x wrote for internal posts, or leading nowhere.
			$dropped = isset( $log['redirects_dropped'] ) ? (array) $log['redirects_dropped'] : array();
			foreach ( $dropped as $entry ) {
				$lines[] = sprintf( '#%d %s -> %s (%s)', $entry['id'], $entry['old'], $entry['new'], GRMLT_Redirects::reason_label( $entry['reason'] ) );
			}
			$per_reason = array();
			foreach ( array( 'redirects_skipped_reasons', 'redirects_dropped_reasons' ) as $key ) {
				foreach ( isset( $log[ $key ] ) ? (array) $log[ $key ] : array() as $reason => $count ) {
					$per_reason[] = sprintf( '%s: %d', GRMLT_Redirects::reason_label( (string) $reason ), (int) $count );
				}
			}
			if ( $per_reason ) {
				array_unshift( $lines, implode( "\n", $per_reason ) );
			}
			$fields['migration'] = array(
				'label' => __( 'Migration from 3.x', 'greek-multi-tool' ),
				'value' => sprintf( '%s — redirects: %d total, %d migrated, %d skipped, %d left out (no public address)', wp_date( get_option( 'date_format' ), (int) $log['time'] ), (int) $log['redirects_total'], (int) $log['redirects_migrated'], count( $skipped ) + ( isset( $log['redirects_skipped_more'] ) ? (int) $log['redirects_skipped_more'] : 0 ), isset( $log['redirects_dropped_count'] ) ? (int) $log['redirects_dropped_count'] : 0 ) . ( $lines ? "\n" . implode( "\n", $lines ) : '' ),
			);
		}
		$repair = get_option( 'grmlt_repair_log', array() );
		if ( is_array( $repair ) && isset( $repair['internal_redirects_deleted'] ) ) {
			$fields['repair'] = array(
				'label' => __( 'Data repair', 'greek-multi-tool' ),
				'value' => sprintf( '%s — redirects of posts without a public address: %d deleted, %d kept with a fixed path', wp_date( get_option( 'date_format' ), (int) $repair['time'] ), (int) $repair['internal_redirects_deleted'], isset( $repair['internal_redirects_unlinked'] ) ? (int) $repair['internal_redirects_unlinked'] : 0 ),
			);
		}
		$info['grmlt'] = array(
			'label'  => 'Greek Multi Tool',
			'fields' => $fields,
		);
		return $info;
	}
}
