<?php
/**
 * 301 redirects: storage, matching, checking, CSV.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

class GRMLT_Redirects {

	const MAX_HOPS = 10;

	/**
	 * Hooks the front-end matcher.
	 */
	public static function init() {
		$tables  = false !== get_option( 'grmlt_db_version', false );
		$pending = false === get_option( 'grmlt_version', false );
		if ( $tables ) {
			// A term whose Greek slug is changed outside the converter (editing
			// it in wp-admin transliterates it silently) gets a redirect too.
			add_action( 'edit_terms', array( __CLASS__, 'remember_term_link' ), 10, 2 );
			add_action( 'edited_terms', array( __CLASS__, 'record_term_change' ), 10, 2 );
		}

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! grmlt_is_on( 'grmlt_redirect' ) ) {
			// No redirects are served, but Greek addresses of converted terms
			// keep resolving, as they did in 2.x/3.x.
			if ( $tables ) {
				add_filter( 'request', array( __CLASS__, 'resolve_converted_term_vars' ) );
			}
			return;
		}
		if ( $tables ) {
			add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
			// After core's own redirects (old slugs, canonical: priority 10).
			add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_greek_term' ), 11 );
		}
		if ( $pending ) {
			// While the 3.x rows are being moved, the ones not moved yet are
			// served from the old table, so there is no 404 window.
			add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_legacy' ), 12 );
		}
	}

	/**
	 * Old links of terms being edited, keyed by term id.
	 *
	 * @var array<int, array{slug:string, link:string}>
	 */
	private static $editing_terms = array();

	/**
	 * edit_terms: remembers the link a term has before it is updated.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function remember_term_link( $term_id, $taxonomy ) {
		if ( GRMLT_Converter::$converting ) {
			return;
		}
		$term = get_term( (int) $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return;
		}
		self::$editing_terms[ (int) $term_id ] = array(
			'slug' => (string) $term->slug,
			'link' => $link,
		);
	}

	/**
	 * edited_terms: when the slug changed and the old one was Greek, records
	 * a redirect from the old link.
	 *
	 * @param int    $term_id  Term id.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function record_term_change( $term_id, $taxonomy ) {
		$term_id = (int) $term_id;
		if ( GRMLT_Converter::$converting || ! isset( self::$editing_terms[ $term_id ] ) ) {
			return;
		}
		$old = self::$editing_terms[ $term_id ];
		unset( self::$editing_terms[ $term_id ] );
		clean_term_cache( $term_id, $taxonomy );
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) || $term->slug === $old['slug'] ) {
			return;
		}
		if ( ! grmlt_has_greek( rawurldecode( $old['slug'] ) ) ) {
			return;
		}
		$new_link = get_term_link( $term );
		if ( is_wp_error( $new_link ) ) {
			return;
		}
		$old_path = self::normalize_path( $old['link'] );
		$new_path = self::normalize_path( $new_link );
		if ( '' === $old_path || $old_path === $new_path ) {
			return;
		}
		self::insert(
			array(
				'object_type' => 'term',
				'object_id'   => $term_id,
				'old_path'    => $old_path,
				'new_path'    => $new_path,
				'run_id'      => 'auto',
			)
		);
	}

	/**
	 * Sanitizes a user-supplied path or URL without destroying percent
	 * encoding (sanitize_text_field() strips %xx sequences). Decoding and
	 * normalization happen later in normalize_path().
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function sanitize_path_input( $value ) {
		// Callers unslash superglobals themselves; REST parameters and CSV
		// cells are not slashed, so unslashing here would eat backslashes.
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = wp_check_invalid_utf8( $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]+/u', '', $value );
		return trim( (string) $value );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		return grmlt_redirects_table();
	}

	/**
	 * Path of the home URL, without trailing slash ('' for a root install).
	 *
	 * @return string
	 */
	public static function home_path() {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		return rtrim( is_string( $path ) ? $path : '/', '/' );
	}

	/**
	 * Host of the site, lower case, without "www.".
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private static function host_of( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$host = is_string( $host ) ? strtolower( $host ) : '';
		return preg_replace( '/^www\./', '', $host );
	}

	/**
	 * Whether a value is a path or an absolute URL on this site.
	 *
	 * @param string $url URL or path.
	 * @return bool
	 */
	public static function is_same_host( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return false;
		}
		if ( ! preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $url ) && '//' !== substr( $url, 0, 2 ) ) {
			return true; // A path.
		}
		$host = self::host_of( $url );
		return '' !== $host && self::host_of( home_url( '/' ) ) === $host;
	}

	/**
	 * Normalizes a URL or path to the canonical form stored in old_path:
	 * path only, relative to the home path, decoded, NFC, lower case,
	 * no surrounding slashes, no query string.
	 *
	 * @param string $input URL or path.
	 * @return string
	 */
	public static function normalize_path( $input ) {
		$input = trim( (string) $input );
		if ( '' === $input ) {
			return '';
		}
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*://#i', $input ) || '//' === substr( $input, 0, 2 ) ) {
			$path = wp_parse_url( $input, PHP_URL_PATH );
		} else {
			$path = $input;
			foreach ( array( '?', '#' ) as $sep ) {
				$pos = strpos( $path, $sep );
				if ( false !== $pos ) {
					$path = substr( $path, 0, $pos );
				}
			}
		}
		$path = is_string( $path ) ? $path : '';
		$path = rawurldecode( $path );
		$path = grmlt_normalize_unicode( $path );
		$path = grmlt_lower( $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = '/' . trim( (string) $path, '/' );

		$home = grmlt_lower( self::home_path() );
		if ( '' !== $home && ( $path === $home || 0 === strpos( $path, $home . '/' ) ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return trim( (string) $path, '/' );
	}

	/**
	 * Hash used by the unique index.
	 *
	 * @param string $path Normalized path.
	 * @return string
	 */
	public static function hash( $path ) {
		return md5( $path );
	}

	/**
	 * Absolute URL for a normalized path.
	 *
	 * @param string $path Normalized path.
	 * @return string
	 */
	public static function url_for_path( $path ) {
		if ( '' === $path ) {
			return home_url( '/' );
		}
		$encoded = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
		$url     = home_url( '/' . $encoded );
		return preg_match( '/\.[a-z0-9]{2,5}$/i', $path ) ? $url : user_trailingslashit( $url );
	}

	/**
	 * Row by normalized path.
	 *
	 * @param string $path Normalized path.
	 * @return object|null
	 */
	public static function get_by_path( $path ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE old_path_hash = %s LIMIT 1', $table, self::hash( $path ) ) );
	}

	/**
	 * Row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ) );
	}

	/**
	 * Inserts a redirect.
	 *
	 * When a redirect of a post or term already exists for the same old path
	 * and the new one also belongs to a post or term, the existing row is
	 * updated (object, destination, run, status) instead: a row left behind
	 * by an earlier conversion, an undo or 3.x must not block the redirect
	 * of a later conversion. Manual and imported rows are never overwritten;
	 * for them, and for a manual or imported row arriving on an existing
	 * path, the result is a "duplicate" error.
	 *
	 * @param array $args old_path, new_path (normalized), object_type, object_id, run_id.
	 * @return int|WP_Error Row id.
	 */
	public static function insert( $args ) {
		global $wpdb;
		$args = wp_parse_args(
			$args,
			array(
				'object_type' => 'manual',
				'object_id'   => 0,
				'old_path'    => '',
				'new_path'    => '',
				'run_id'      => null,
			)
		);
		$type     = in_array( $args['object_type'], array( 'post', 'term', 'manual' ), true ) ? $args['object_type'] : 'manual';
		$old_path = self::normalize_path( $args['old_path'] );
		$new_path = self::normalize_path( $args['new_path'] );
		$run_id   = $args['run_id'] ? substr( (string) $args['run_id'], 0, 32 ) : null;
		if ( '' === $old_path ) {
			return new WP_Error( 'empty_path', __( 'The old path is empty.', 'greek-multi-tool' ) );
		}
		if ( $old_path === $new_path && 'manual' === $type ) {
			return new WP_Error( 'same_path', __( 'The old and the new path are the same.', 'greek-multi-tool' ) );
		}
		$existing = self::get_by_path( $old_path );
		if ( $existing ) {
			if ( 'manual' === $type || ! in_array( $existing->object_type, array( 'post', 'term' ), true ) ) {
				return new WP_Error( 'duplicate', __( 'A redirect for this path already exists.', 'greek-multi-tool' ) );
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- our own table; nothing to cache.
			$wpdb->update(
				self::table(),
				array(
					'object_type' => $type,
					'object_id'   => absint( $args['object_id'] ),
					'new_path'    => $new_path,
					'run_id'      => $run_id,
					'status'      => null,
					'checked_at'  => null,
				),
				array( 'id' => (int) $existing->id ),
				array( '%s', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
			return (int) $existing->id;
		}
		$id = self::insert_row( $type, absint( $args['object_id'] ), $old_path, $new_path, $run_id );
		if ( ! $id ) {
			return new WP_Error( 'db_error', __( 'The redirect could not be saved.', 'greek-multi-tool' ) );
		}
		return $id;
	}

	/**
	 * Writes a row without any check (the caller has normalized the paths and
	 * made sure the old path is free).
	 *
	 * @param string      $object_type post|term|manual.
	 * @param int         $object_id   Object id.
	 * @param string      $old_path    Normalized old path.
	 * @param string      $new_path    Normalized new path.
	 * @param string|null $run_id      Run id.
	 * @return int Row id, 0 on failure.
	 */
	public static function insert_row( $object_type, $object_id, $old_path, $new_path, $run_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'object_type'   => $object_type,
				'object_id'     => (int) $object_id,
				'old_path'      => $old_path,
				'old_path_hash' => self::hash( $old_path ),
				'new_path'      => $new_path,
				'run_id'        => $run_id,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		return false === $ok ? 0 : (int) $wpdb->insert_id;
	}

	/**
	 * Deletes the post/term redirects whose old path is now the live address
	 * of converted or restored content: they can never fire again and would
	 * only show up as loops. Manual rows are left alone.
	 *
	 * @param string      $path        Normalized path.
	 * @param string|null $object_type Only rows of this object (post|term).
	 * @param int         $object_id   Object id (with $object_type).
	 * @return int Rows deleted.
	 */
	public static function delete_object_rows_at( $path, $object_type = null, $object_id = 0 ) {
		global $wpdb;
		if ( '' === $path ) {
			return 0;
		}
		if ( null !== $object_type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE old_path_hash = %s AND object_type = %s AND object_id = %d', self::table(), self::hash( $path ), $object_type, (int) $object_id ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE old_path_hash = %s AND object_type IN ('post','term')", self::table(), self::hash( $path ) ) );
	}

	/**
	 * Deletes the redirects of a run for a set of objects.
	 *
	 * @param string $run_id      Run id.
	 * @param string $object_type post|term.
	 * @param int[]  $ids         Object ids.
	 * @return int Rows deleted.
	 */
	public static function delete_for_objects( $run_id, $object_type, $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		if ( ! $ids ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %d per id, built above.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE run_id = %s AND object_type = %s AND object_id IN ({$placeholders})", array_merge( array( self::table(), $run_id, $object_type ), $ids ) ) );
	}

	/**
	 * Changes the fallback destination of a redirect.
	 *
	 * @param int    $id       Row id.
	 * @param string $new_path URL or path on this site.
	 * @return true|WP_Error
	 */
	public static function update_destination( $id, $new_path ) {
		global $wpdb;
		if ( ! self::is_same_host( $new_path ) ) {
			return new WP_Error( 'other_host', __( 'The destination must be a path on this site.', 'greek-multi-tool' ) );
		}
		$row = self::get( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Redirect not found.', 'greek-multi-tool' ) );
		}
		$path = self::normalize_path( $new_path );
		if ( '' === $path ) {
			return new WP_Error( 'home', __( 'The home page cannot be the destination of a redirect. Choose a specific page of the site as the destination.', 'greek-multi-tool' ) );
		}
		if ( $path === $row->old_path ) {
			return new WP_Error( 'loop', __( 'The destination cannot be the old path itself.', 'greek-multi-tool' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			self::table(),
			array(
				'new_path'    => $path,
				'object_type' => 'manual',
				'object_id'   => 0,
				'status'      => null,
				'checked_at'  => null,
			),
			array( 'id' => (int) $id ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
		return true;
	}

	/**
	 * Deletes redirects by id.
	 *
	 * @param int[] $ids Ids.
	 * @return int Rows deleted.
	 */
	public static function delete( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', (array) $ids ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ({$placeholders})", array_merge( array( $table ), $ids ) ) );
	}

	/**
	 * Deletes the redirects created by a conversion run.
	 *
	 * @param string $run_id Run id.
	 * @return int
	 */
	public static function delete_by_run( $run_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->delete( self::table(), array( 'run_id' => $run_id ), array( '%s' ) );
	}

	/**
	 * Number of redirects, without the rows the cleanup of revision 3 deletes
	 * (see orphan_ids()). Site Health and the plugin screen show this one.
	 *
	 * @return int
	 */
	public static function count() {
		return max( 0, self::count_all() - count( self::orphan_ids() ) );
	}

	/**
	 * Number of rows in the table, all of them (for loops over the table).
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %i is a valid placeholder since WP 6.2.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * Rows of posts that have no public address, and no fixed path that
	 * leads to public content either: rows 3.x wrote for ACF / Secure Custom
	 * Fields objects and other internal posts, and rows of deleted posts
	 * whose address leads nowhere. They can never redirect anywhere.
	 *
	 * @param bool $refresh Recompute instead of the cached result.
	 * @return int[] Row ids.
	 */
	public static function orphan_ids( $refresh = false ) {
		static $cache = null;
		if ( null !== $cache && ! $refresh ) {
			return $cache;
		}
		$cache = array();
		foreach ( self::rows_without_public_post() as $row ) {
			if ( ! self::is_public_target( (string) $row->new_path ) ) {
				$cache[] = (int) $row->id;
			}
		}
		return $cache;
	}

	/**
	 * Rows linked to a post that has no public address (missing, an internal
	 * type, not publicly viewable). The SQL keeps only rows whose post is
	 * missing, of a type that is not public, or not published; each candidate
	 * is then checked in PHP. Run after the post types are registered
	 * (wp_loaded or later).
	 *
	 * @return object[] Rows (id, object_id, new_path).
	 */
	private static function rows_without_public_post() {
		global $wpdb;
		$types = array_values( array_diff( get_post_types( array( 'public' => true ), 'names' ), GRMLT_Converter::internal_post_types() ) );
		$types = $types ? $types : array( '' );
		$stati = array_values( get_post_stati( array( 'public' => true ), 'names' ) );
		$stati = $stati ? $stati : array( 'publish' );
		$type_in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$status_in = implode( ',', array_fill( 0, count( $stati ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %s per type and status, built above.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT r.id, r.object_id, r.new_path FROM %i r LEFT JOIN {$wpdb->posts} p ON p.ID = r.object_id WHERE r.object_type = 'post' AND ( p.ID IS NULL OR p.post_type NOT IN ({$type_in}) OR ( p.post_status NOT IN ({$status_in}) AND NOT ( p.post_type = 'attachment' AND p.post_status = 'inherit' ) ) )", array_merge( array( self::table() ), $types, $stati ) ) );
		$out = array();
		foreach ( (array) $rows as $row ) {
			if ( ! self::post_is_public( (int) $row->object_id ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Revision 3 of the stored data: no row stays linked to a post without a
	 * public address. A row whose fixed path still leads to public content
	 * keeps working as a fixed-path row (object_type manual, as the migration
	 * now stores it); the rest (orphan_ids()) is deleted.
	 *
	 * @return array{deleted:int, unlinked:int}
	 */
	public static function repair_rows_without_public_post() {
		global $wpdb;
		$unlinked = 0;
		$orphans  = array();
		foreach ( self::rows_without_public_post() as $row ) {
			if ( ! self::is_public_target( (string) $row->new_path ) ) {
				$orphans[] = (int) $row->id;
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- our own table.
			$unlinked += (int) $wpdb->update(
				self::table(),
				array(
					'object_type' => 'manual',
					'object_id'   => 0,
					'status'      => null,
					'checked_at'  => null,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
		}
		$deleted = self::delete( $orphans );
		self::orphan_ids( true );
		return array(
			'deleted'  => $deleted,
			'unlinked' => $unlinked,
		);
	}

	/**
	 * Stored statuses of the rows of orphan_ids(), counted: the counters
	 * subtract them.
	 *
	 * @return array<string, int>
	 */
	private static function orphan_status_counts() {
		global $wpdb;
		$ids = self::orphan_ids();
		$out = array();
		if ( ! $ids ) {
			return $out;
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one %d per id, built above.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(status,'unchecked') AS s, COUNT(*) AS c FROM %i WHERE id IN ({$placeholders}) GROUP BY s", array_merge( array( self::table() ), $ids ) ) );
		foreach ( (array) $rows as $row ) {
			$out[ $row->s ] = (int) $row->c;
		}
		return $out;
	}

	/**
	 * Count of redirects whose last check found a problem (without the rows
	 * of orphan_ids()).
	 *
	 * @return int
	 */
	public static function problem_count() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %i is a valid placeholder since WP 6.2.
		$count   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE status IN ('missing','chain','loop')", $table ) );
		$orphans = self::orphan_status_counts();
		foreach ( array( 'missing', 'chain', 'loop' ) as $status ) {
			$count -= isset( $orphans[ $status ] ) ? $orphans[ $status ] : 0;
		}
		return max( 0, $count );
	}

	/**
	 * Counts per status.
	 *
	 * @return array<string, int>
	 */
	public static function status_counts() {
		global $wpdb;
		$table = self::table();
		$out   = array(
			'ok'        => 0,
			'missing'   => 0,
			'chain'     => 0,
			'loop'      => 0,
			'unchecked' => 0,
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %i is a valid placeholder since WP 6.2.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(status,'unchecked') AS s, COUNT(*) AS c FROM %i GROUP BY s", $table ) );
		foreach ( (array) $rows as $row ) {
			$out[ $row->s ] = (int) $row->c;
		}
		// Without the rows of orphan_ids().
		foreach ( self::orphan_status_counts() as $status => $count ) {
			if ( isset( $out[ $status ] ) ) {
				$out[ $status ] = max( 0, $out[ $status ] - $count );
			}
		}
		return $out;
	}

	/**
	 * Resolves where a redirect points today. "valid" is false when there is
	 * nowhere to send the visitor: the matcher then leaves the 404 alone.
	 *
	 * - A post with a public address: its current link.
	 * - A post that exists without one (an internal type such as an ACF field,
	 *   a draft, a private page): the fixed path, only if it leads to public
	 *   content.
	 * - A deleted post or term, and manual rows: the fixed path.
	 *
	 * The target is never the home page (a path is valid only when it is not
	 * empty, see is_valid_path()), with one exception: the row of the page
	 * that is the static front page today (is_front_page_post()) leads to
	 * home_url( '/' ), "home" is true and the path is empty. It is worked out
	 * on every request and never stored, so the row follows the page to its
	 * own permalink again when another front page is chosen.
	 *
	 * @param object $row Row.
	 * @return array{path:string, source:string, object_exists:bool, valid:bool, home:bool}
	 */
	public static function destination( $row ) {
		$fixed = (string) $row->new_path;
		if ( 'post' === $row->object_type && $row->object_id ) {
			$post = get_post( (int) $row->object_id );
			if ( $post && self::post_is_public( $post ) ) {
				$home = self::is_front_page_post( $post );
				$path = $home ? '' : self::normalize_path( (string) get_permalink( $post ) );
				return array(
					'path'          => $path,
					'source'        => 'object',
					'object_exists' => true,
					'valid'         => $home || self::is_valid_path( $path ),
					'home'          => $home,
				);
			}
			if ( $post ) {
				$valid = self::is_public_target( $fixed );
				return array(
					'path'          => $valid ? $fixed : '',
					'source'        => 'fallback',
					'object_exists' => false,
					'valid'         => $valid,
					'home'          => false,
				);
			}
		} elseif ( 'term' === $row->object_type && $row->object_id ) {
			$term = get_term( (int) $row->object_id );
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$path = self::normalize_path( $link );
					return array(
						'path'          => $path,
						'source'        => 'object',
						'object_exists' => true,
						'valid'         => self::is_valid_path( $path ),
						'home'          => false,
					);
				}
			}
		}
		return array(
			'path'          => $fixed,
			'source'        => 'fallback',
			'object_exists' => false,
			'valid'         => self::is_valid_path( $fixed ),
			'home'          => false,
		);
	}

	/**
	 * Whether a post is the static front page today: Settings → Reading shows
	 * a static page, this one, and it is publicly viewable. Its redirects lead
	 * to the home page, the only redirects that may.
	 *
	 * @param WP_Post|int $post Post.
	 * @return bool
	 */
	public static function is_front_page_post( $post ) {
		$post = get_post( $post );
		return $post
			&& 'page' === get_option( 'show_on_front' )
			&& (int) get_option( 'page_on_front' ) === (int) $post->ID
			&& self::post_is_public( $post );
	}

	/**
	 * Whether a post has a public address a redirect may lead to: it is not
	 * one of the internal types the converter never touches (ACF and Secure
	 * Custom Fields objects, revisions, templates, menus…) and is publicly
	 * viewable (a viewable type and a public status).
	 *
	 * @param WP_Post|int $post Post.
	 * @return bool
	 */
	public static function post_is_public( $post ) {
		$post = get_post( $post );
		if ( ! $post || in_array( $post->post_type, GRMLT_Converter::internal_post_types(), true ) ) {
			return false;
		}
		return is_post_publicly_viewable( $post );
	}

	/**
	 * Whether a stored path can be a redirect target: normalized and not
	 * empty. An empty path is the home page, which a redirect never targets
	 * ('/', '//' and empty values all end up here).
	 *
	 * @param string $path Stored path.
	 * @return bool
	 */
	public static function is_valid_path( $path ) {
		$path = (string) $path;
		return '' !== $path && self::normalize_path( $path ) === $path;
	}

	/**
	 * Whether a path is a valid target that leads to public content.
	 *
	 * @param string $path Stored path.
	 * @return bool
	 */
	public static function is_public_target( $path ) {
		return self::is_valid_path( $path ) && self::path_resolves( $path );
	}

	/**
	 * Normalized path of the current request.
	 *
	 * @return string
	 */
	public static function current_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- normalized below.
		return self::normalize_path( (string) $uri );
	}

	/**
	 * Splits a trailing pagination / feed / embed endpoint off a path, so that
	 * /old-term/page/2/ and /old-term/feed/ find the redirect of /old-term/
	 * and keep their endpoint on the destination.
	 *
	 * @param string $path Normalized path.
	 * @return array{0:string, 1:string} Base path and endpoint suffix ('' if none).
	 */
	public static function split_endpoint( $path ) {
		if ( preg_match( '#^(.*?)/(page/[0-9]+|feed(?:/(?:feed|rdf|rss|rss2|atom))?|embed)$#', $path, $m ) ) {
			return array( $m[1], $m[2] );
		}
		return array( $path, '' );
	}

	/**
	 * template_redirect handler (priority 1): redirects from the table, only for
	 * requests WordPress could not resolve.
	 */
	public static function maybe_redirect() {
		if ( ! is_404() && ! self::is_unresolved_feed() ) {
			return;
		}
		$path = self::current_path();
		if ( '' === $path ) {
			return;
		}
		$suffix = '';
		$row    = self::get_by_path( $path );
		if ( ! $row ) {
			list( $base, $suffix ) = self::split_endpoint( $path );
			if ( '' === $suffix || '' === $base ) {
				return;
			}
			$row = self::get_by_path( $base );
			if ( ! $row || 'manual' === $row->object_type ) {
				return; // Endpoints are only carried over for posts and terms.
			}
		}
		self::send( $row, $path, $suffix );
	}

	/**
	 * Sends the 301 for a row, keeping an endpoint suffix and the query
	 * string. A row flagged "loop" by the last check is still followed: the
	 * checks below refuse a redirect to the requested address itself, or to
	 * a fixed destination whose own redirect points straight back.
	 *
	 * @param object $row    Redirect row.
	 * @param string $path   Normalized request path.
	 * @param string $suffix Endpoint suffix ('' if none).
	 */
	private static function send( $row, $path, $suffix ) {
		$destination = self::destination( $row );
		if ( ! $destination['valid'] ) {
			return; // Nowhere to go: the 404 stays.
		}
		$target = '' === $suffix ? $destination['path'] : trim( $destination['path'] . '/' . $suffix, '/' );
		if ( $target === $path ) {
			return; // Would loop. (The home page itself never gets here: an empty request path is never looked up.)
		}
		if ( 'fallback' === $destination['source'] ) {
			$next = self::get_by_path( $destination['path'] );
			if ( $next && (int) $next->id !== (int) $row->id ) {
				$back = self::destination( $next );
				if ( $back['path'] === $row->old_path || $back['path'] === $path ) {
					return; // Two rows pointing at each other.
				}
			}
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', $table, current_time( 'mysql', true ), $row->id ) );

		wp_safe_redirect( self::url_for_path( $target ) . self::current_query_string(), 301, 'Greek Multi Tool' );
		exit;
	}

	/**
	 * template_redirect handler (priority 11, after core's old-slug and
	 * canonical redirects): Greek addresses of term archives whose term was
	 * converted without a redirect (2.x/3.x converted terms silently and
	 * served their old addresses by transliterating them on the fly), and
	 * Greek tag addresses WordPress resolved through its own transliteration.
	 *
	 * Safe against crafted requests: single posts, pages and products are
	 * never touched; only the taxonomy of the rewrite rule that matched the
	 * address counts (never a query string); a row is stored only for the
	 * term's exact Greek address, other spellings are redirected without
	 * one; a miss is cached for an hour where an object cache exists.
	 */
	public static function maybe_redirect_greek_term() {
		$unresolved = is_404() || self::is_unresolved_feed();
		$alias      = ! $unresolved && self::is_greek_term_alias();
		if ( ! $unresolved && ! $alias ) {
			return;
		}
		if ( self::is_singular_request() ) {
			return;
		}
		$path = self::current_path();
		if ( '' === $path || ! grmlt_has_greek( $path ) ) {
			return;
		}
		list( $base, $suffix ) = self::split_endpoint( $path );
		if ( '' === $suffix ) {
			$base = $path;
		}
		$miss_key = 'greek_term_miss_' . md5( $path );
		if ( $unresolved && wp_cache_get( $miss_key, 'grmlt' ) ) {
			return;
		}

		$term  = null;
		$slugs = self::greek_term_query_slugs();
		if ( $alias ) {
			$queried = get_queried_object();
			if ( $queried instanceof WP_Term && isset( $slugs[ $queried->taxonomy ] ) ) {
				$term = $queried;
			}
		} else {
			foreach ( $slugs as $taxonomy => $slug ) {
				$term = self::find_converted_term( $slug, $taxonomy );
				if ( $term ) {
					break;
				}
			}
		}
		if ( ! $term ) {
			if ( $unresolved ) {
				wp_cache_set( $miss_key, 1, 'grmlt', HOUR_IN_SECONDS );
			}
			return;
		}

		$row = self::get_by_path( $base );
		if ( $row && ( 'term' !== $row->object_type || (int) $row->object_id !== (int) $term->term_id ) ) {
			return; // The address belongs to something else.
		}
		if ( ! $row && self::is_canonical_greek_address( $base, $term ) ) {
			$row = self::record_term_alias( $base, $term );
		}
		if ( $row ) {
			self::send( $row, $path, $row->old_path === $path ? '' : $suffix );
			return;
		}
		// Another spelling of the address (a stray parent, punctuation): send
		// the visitor to the term without remembering the address.
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return;
		}
		$target = self::normalize_path( $link );
		$target = '' === $suffix ? $target : $target . '/' . $suffix;
		if ( '' === $target || $target === $path ) {
			return;
		}
		wp_safe_redirect( self::url_for_path( $target ) . self::current_query_string(), 301, 'Greek Multi Tool' );
		exit;
	}

	/**
	 * Whether the request is for a single post, page, attachment or any
	 * other single object, judged from its query vars (on a 404 the query
	 * flags are already reset).
	 *
	 * @return bool
	 */
	private static function is_singular_request() {
		global $wp_query;
		if ( $wp_query instanceof WP_Query && $wp_query->is_singular ) {
			return true;
		}
		$vars = isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && is_array( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : array();
		return self::vars_are_singular( array_merge( $vars, self::matched_vars() ) );
	}

	/**
	 * Whether a set of query vars asks for a single object.
	 *
	 * @param array $vars Query vars.
	 * @return bool
	 */
	private static function vars_are_singular( $vars ) {
		foreach ( array( 'name', 'pagename', 'p', 'page_id', 'attachment', 'attachment_id', 'subpost', 'subpost_id' ) as $key ) {
			if ( ! empty( $vars[ $key ] ) ) {
				return true;
			}
		}
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( ! empty( $type->query_var ) && ! empty( $vars[ $type->query_var ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Query vars produced by the rewrite rule that matched the request (no
	 * query string: anybody can add ?category_name=… to any address).
	 *
	 * @return array
	 */
	private static function matched_vars() {
		$vars = array();
		if ( isset( $GLOBALS['wp'] ) && $GLOBALS['wp'] instanceof WP && is_string( $GLOBALS['wp']->matched_query ) && '' !== $GLOBALS['wp']->matched_query ) {
			parse_str( $GLOBALS['wp']->matched_query, $vars );
		}
		return is_array( $vars ) ? $vars : array();
	}

	/**
	 * Whether WordPress resolved a term archive from an address that is
	 * still Greek (it transliterates tag slugs while looking them up).
	 *
	 * @return bool
	 */
	private static function is_greek_term_alias() {
		if ( ! ( is_category() || is_tag() || is_tax() ) ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only tested for Greek.
		return grmlt_has_greek( rawurldecode( (string) wp_parse_url( (string) $uri, PHP_URL_PATH ) ) );
	}

	/**
	 * The Greek slug (last path segment) requested for each public taxonomy,
	 * taken from the matched rewrite rule only.
	 *
	 * @param array|null $vars Query vars; default: the matched rule's.
	 * @return array<string, string> taxonomy => decoded Greek slug.
	 */
	private static function greek_term_query_slugs( $vars = null ) {
		$vars = null === $vars ? self::matched_vars() : $vars;
		$out  = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			if ( empty( $taxonomy->query_var ) || empty( $vars[ $taxonomy->query_var ] ) || ! is_string( $vars[ $taxonomy->query_var ] ) ) {
				continue;
			}
			$parts = explode( '/', trim( rawurldecode( $vars[ $taxonomy->query_var ] ), '/' ) );
			$slug  = (string) end( $parts );
			if ( grmlt_has_greek( $slug ) ) {
				$out[ $taxonomy->name ] = $slug;
			}
		}
		return $out;
	}

	/**
	 * The term a Greek slug was converted to, if the Greek slug itself no
	 * longer exists in the taxonomy. One query: the Greek slug and every
	 * candidate Latin slug, in order of preference.
	 *
	 * @param string $slug     Decoded Greek slug.
	 * @param string $taxonomy Taxonomy.
	 * @return WP_Term|null
	 */
	private static function find_converted_term( $slug, $taxonomy ) {
		$greek      = grmlt_core_sanitize_title( $slug );
		$candidates = grmlt_latin_slug_candidates( $slug );
		if ( '' === $greek || ! $candidates ) {
			return null;
		}
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'slug'       => array_values( array_unique( array_merge( array( $greek ), $candidates ) ) ),
				'orderby'    => 'slug__in',
				'number'     => 1,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) || ! ( $terms[0] instanceof WP_Term ) ) {
			return null;
		}
		return $terms[0]->slug === $greek ? null : $terms[0]; // Still Greek: WordPress serves it itself.
	}

	/**
	 * Whether a path is exactly the Greek address of a term: its current
	 * address with the Greek slug in place of the Latin one, for the term
	 * and for any converted parent, each Greek segment a clean slug that
	 * transliterates to the Latin one.
	 *
	 * @param string  $path Normalized path without endpoint.
	 * @param WP_Term $term Term.
	 * @return bool
	 */
	private static function is_canonical_greek_address( $path, $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return false;
		}
		$latin = explode( '/', self::normalize_path( $link ) );
		$greek = explode( '/', $path );
		if ( count( $latin ) !== count( $greek ) ) {
			return false;
		}
		$chain = array( self::normalize_path( $term->slug ) );
		foreach ( get_ancestors( (int) $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor_id ) {
			$ancestor = get_term( (int) $ancestor_id, $term->taxonomy );
			if ( $ancestor instanceof WP_Term ) {
				$chain[] = self::normalize_path( $ancestor->slug );
			}
		}
		$differs = false;
		foreach ( $greek as $i => $segment ) {
			if ( $segment === $latin[ $i ] ) {
				continue;
			}
			if ( ! grmlt_has_greek( $segment ) || ! in_array( $latin[ $i ], $chain, true ) ) {
				return false;
			}
			if ( self::normalize_path( grmlt_core_sanitize_title( $segment ) ) !== $segment ) {
				return false; // Punctuation or other noise around the slug.
			}
			if ( ! in_array( $latin[ $i ], grmlt_latin_slug_candidates( $segment ), true ) ) {
				return false;
			}
			$differs = true;
		}
		return $differs;
	}

	/**
	 * Records an "auto" redirect from the Greek address of a term to its
	 * current address, so the next request is a single lookup.
	 *
	 * @param string  $base Normalized address without endpoint.
	 * @param WP_Term $term Term.
	 * @return object|null The row, or null when there is nothing to redirect.
	 */
	private static function record_term_alias( $base, $term ) {
		$link = get_term_link( $term );
		if ( is_wp_error( $link ) ) {
			return null;
		}
		$new_path = self::normalize_path( $link );
		if ( '' === $new_path || $new_path === $base ) {
			return null;
		}
		$id = self::insert(
			array(
				'object_type' => 'term',
				'object_id'   => (int) $term->term_id,
				'old_path'    => $base,
				'new_path'    => $new_path,
				'run_id'      => 'auto',
			)
		);
		return is_wp_error( $id ) ? null : self::get( $id );
	}

	/**
	 * request filter, used only while redirects are switched off: a Greek
	 * term address whose term was converted resolves to that term (200), as
	 * it did in 2.x/3.x through on-the-fly transliteration. With redirects on,
	 * the same address gets a 301 instead. Only taxonomy vars of the matched
	 * rewrite rule are touched, and single objects never.
	 *
	 * @param array $query_vars Query vars.
	 * @return array
	 */
	public static function resolve_converted_term_vars( $query_vars ) {
		if ( ! is_array( $query_vars ) || self::vars_are_singular( $query_vars ) ) {
			return $query_vars;
		}
		$by_taxonomy = self::greek_term_query_slugs();
		if ( ! $by_taxonomy ) {
			return $query_vars;
		}
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			if ( ! isset( $by_taxonomy[ $taxonomy->name ] ) || empty( $query_vars[ $taxonomy->query_var ] ) || ! is_string( $query_vars[ $taxonomy->query_var ] ) ) {
				continue;
			}
			$parts = explode( '/', trim( rawurldecode( $query_vars[ $taxonomy->query_var ] ), '/' ) );
			foreach ( $parts as $i => $part ) {
				if ( ! grmlt_has_greek( $part ) ) {
					continue;
				}
				$term = self::find_converted_term( $part, $taxonomy->name );
				if ( $term ) {
					$parts[ $i ] = $term->slug;
				}
			}
			$query_vars[ $taxonomy->query_var ] = implode( '/', $parts );
		}
		return $query_vars;
	}

	/**
	 * template_redirect handler (priority 12) while the 2.x/3.x migration is
	 * pending: a 404 whose address is in the old table but not moved yet is
	 * redirected from there, so the minutes of the migration have no 404
	 * window. Paths are compared after the same normalization the migration
	 * uses; only addresses on this site are followed.
	 */
	public static function maybe_redirect_legacy() {
		global $wpdb;
		if ( ! is_404() ) {
			return;
		}
		$legacy = grmlt_legacy_table();
		if ( ! grmlt_table_exists( $legacy ) ) {
			return;
		}
		$path = self::current_path();
		if ( '' === $path ) {
			return;
		}
		$last  = (string) substr( strrchr( '/' . $path, '/' ), 1 );
		$likes = array_unique( array( '%' . $wpdb->esc_like( $last ) . '%', '%' . $wpdb->esc_like( rawurlencode( $last ) ) . '%' ) );
		$where = implode( ' OR ', array_fill( 0, count( $likes ), 'old_permalink LIKE %s' ) );
		if ( in_array( 'post_id', grmlt_legacy_table_columns(), true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per LIKE pattern, built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT old_permalink, new_permalink, post_id FROM %i WHERE {$where} LIMIT 50", array_merge( array( $legacy ), $likes ) ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %s per LIKE pattern, built above.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT old_permalink, new_permalink, 0 AS post_id FROM %i WHERE {$where} LIMIT 50", array_merge( array( $legacy ), $likes ) ) );
		}
		foreach ( (array) $rows as $row ) {
			if ( self::normalize_path( (string) $row->old_permalink ) !== $path ) {
				continue;
			}
			// The page that is the static front page: the home page, as after the migration.
			if ( (int) $row->post_id && self::is_front_page_post( (int) $row->post_id ) ) {
				wp_safe_redirect( home_url( '/' ) . self::current_query_string(), 301, 'Greek Multi Tool' );
				exit;
			}
			$target = self::normalize_path( (string) $row->new_permalink );
			if ( $target === $path || ! self::is_public_target( $target ) ) {
				return; // Rows the migration leaves out are not followed either.
			}
			wp_safe_redirect( self::url_for_path( $target ) . self::current_query_string(), 301, 'Greek Multi Tool' );
			exit;
		}
	}

	/**
	 * Query vars a path would be parsed into by the current rewrite rules
	 * (what WP::parse_request() does for a request).
	 *
	 * @param string $path Normalized path.
	 * @return array
	 */
	public static function path_query_vars( $path ) {
		$rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $rules ) || '' === $path ) {
			return array();
		}
		$encoded = implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) );
		foreach ( $rules as $match => $query ) {
			if ( preg_match( "#^$match#", $path, $matches ) || preg_match( "#^$match#", $encoded, $matches ) ) {
				$query = preg_replace( '!^.+\?!', '', $query );
				$query = WP_MatchesMapRegex::apply( $query, $matches );
				parse_str( (string) $query, $vars );
				return is_array( $vars ) ? $vars : array();
			}
		}
		return array();
	}

	/**
	 * Deletes "auto" redirects whose old address is a single post, page or
	 * product (pre-release builds recorded those for addresses like
	 * /greek-category/post-slug/ and sent them to the category).
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_misplaced_auto_rows() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, old_path FROM %i WHERE run_id = %s AND object_type = 'term'", self::table(), 'auto' ) );
		$ids  = array();
		foreach ( (array) $rows as $row ) {
			if ( self::vars_are_singular( self::path_query_vars( (string) $row->old_path ) ) ) {
				$ids[] = (int) $row->id;
			}
		}
		return self::delete( $ids );
	}

	/**
	 * Whether the current request is a feed WordPress could not resolve.
	 *
	 * WP::handle_404() never flags a feed as 404: /old-term/feed/ and
	 * /old-post/feed/ are served as empty feeds instead. Those are the only
	 * non-404 requests the matcher looks at.
	 *
	 * @return bool
	 */
	private static function is_unresolved_feed() {
		global $wp_query;
		if ( ! is_feed() || ! ( $wp_query instanceof WP_Query ) ) {
			return false;
		}
		return 0 === (int) $wp_query->post_count && ! $wp_query->get_queried_object();
	}

	/**
	 * The query string of the current request (with the leading '?'), so
	 * tracking parameters survive the redirect. Empty when there is none.
	 *
	 * @return string
	 */
	private static function current_query_string() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the query part is reused, and wp_safe_redirect() sanitizes the URL.
		$query = (string) wp_parse_url( (string) $uri, PHP_URL_QUERY );
		return '' === $query ? '' : '?' . $query;
	}

	/**
	 * Whether a normalized path resolves to published content.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	public static function path_resolves( $path ) {
		if ( '' === $path ) {
			return true;
		}
		$url     = self::url_for_path( $path );
		$post_id = url_to_postid( $url );
		if ( $post_id ) {
			return self::post_is_public( $post_id );
		}
		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $type ) {
			if ( ! is_post_type_hierarchical( $type ) ) {
				continue;
			}
			$page = get_page_by_path( $path, OBJECT, $type );
			if ( $page && self::post_is_public( $page ) ) {
				return true;
			}
		}
		$slug = basename( $path );
		foreach ( get_taxonomies( array( 'public' => true ), 'names' ) as $taxonomy ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'slug', rawurlencode( $slug ), $taxonomy );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) && self::normalize_path( $link ) === $path ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Computes the status of one redirect without HTTP requests.
	 *
	 * @param object $row Row.
	 * @return string ok|missing|chain|loop
	 */
	public static function compute_status( $row ) {
		$destination = self::destination( $row );
		$target      = $destination['path'];
		if ( ! $destination['valid'] ) {
			return 'missing';
		}

		// The destination is live content: redirects only fire on addresses
		// WordPress cannot resolve, so a row on that address can never take
		// over. The only problem left is a row pointing at its own address
		// (content that was restored to it, for example).
		if ( $destination['object_exists'] ) {
			return $target === $row->old_path ? 'loop' : 'ok';
		}

		// Chains and loops: does the destination itself have a redirect?
		$visited = array( $row->old_path );
		$current = $target;
		$hops    = 0;
		while ( $hops < self::MAX_HOPS ) {
			if ( in_array( $current, $visited, true ) ) {
				return 'loop';
			}
			$next = self::get_by_path( $current );
			if ( ! $next ) {
				break;
			}
			$visited[] = $current;
			$current   = self::destination( $next );
			$current   = $current['path'];
			++$hops;
		}
		if ( $hops > 0 ) {
			return 'chain';
		}
		return self::path_resolves( $target ) ? 'ok' : 'missing';
	}

	/**
	 * Checks a batch of redirects and stores their status.
	 *
	 * @param int $offset Offset.
	 * @param int $limit  Batch size.
	 * @return array{processed:int, done:bool, total:int, offset:int}
	 */
	public static function check_batch( $offset = 0, $limit = 200 ) {
		global $wpdb;
		$table = self::table();
		$total = self::count_all();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT %d OFFSET %d', $table, $limit, $offset ) );
		$now  = current_time( 'mysql', true );
		foreach ( (array) $rows as $row ) {
			$status = self::compute_status( $row );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table,
				array(
					'status'     => $status,
					'checked_at' => $now,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
		$processed = count( (array) $rows );
		$done      = $offset + $processed >= $total || 0 === $processed;
		if ( $done ) {
			grmlt_invalidate_health_cache();
		}
		return array(
			'processed' => $processed,
			'done'      => $done,
			'total'     => $total,
			'offset'    => $offset + $processed,
		);
	}

	/**
	 * CSV header.
	 *
	 * @return string[]
	 */
	public static function csv_columns() {
		return array( 'old_path', 'new_path', 'object_type', 'object_id', 'hits', 'last_hit', 'status' );
	}

	/**
	 * Streams all redirects as CSV to the given handle.
	 *
	 * @param resource $handle Output stream.
	 */
	public static function export_csv( $handle ) {
		global $wpdb;
		$table = self::table();
		fputcsv( $handle, self::csv_columns(), ',', '"', '\\' );
		$offset = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id ASC LIMIT 500 OFFSET %d', $table, $offset ) );
			foreach ( (array) $rows as $row ) {
				fputcsv(
					$handle,
					array(
						'/' . $row->old_path . '/',
						'/' . $row->new_path . '/',
						$row->object_type,
						(int) $row->object_id,
						(int) $row->hits,
						(string) $row->last_hit,
						(string) $row->status,
					),
					',',
					'"',
					'\\'
				);
			}
			$offset += 500;
		} while ( count( (array) $rows ) === 500 );
	}

	/**
	 * Imports redirects from a CSV file.
	 *
	 * @param string $file Path to the uploaded file.
	 * @return array{total:int, imported:int, skipped:array<int, array{line:int, reason:string}>}|WP_Error
	 */
	public static function import_csv( $file ) {
		$handle = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return new WP_Error( 'unreadable', __( 'The file could not be read.', 'greek-multi-tool' ) );
		}
		$summary = array(
			'total'    => 0,
			'imported' => 0,
			'skipped'  => array(),
		);
		$header  = fgetcsv( $handle, 0, ',', '"', '\\' );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'empty', __( 'The file is empty.', 'greek-multi-tool' ) );
		}
		$header = array_map( 'strtolower', array_map( 'trim', $header ) );
		if ( isset( $header[0] ) ) {
			$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
		}
		$old_idx = array_search( 'old_path', $header, true );
		$new_idx = array_search( 'new_path', $header, true );
		if ( false === $old_idx || false === $new_idx ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return new WP_Error( 'header', __( 'The first line must contain the columns old_path and new_path.', 'greek-multi-tool' ) );
		}
		$line = 1;
		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$line;
			if ( array( null ) === $row ) {
				continue;
			}
			++$summary['total'];
			$old = isset( $row[ $old_idx ] ) ? self::sanitize_path_input( $row[ $old_idx ] ) : '';
			$new = isset( $row[ $new_idx ] ) ? self::sanitize_path_input( $row[ $new_idx ] ) : '';
			if ( '' === $old || '' === $new ) {
				$summary['skipped'][] = array(
					'line'   => $line,
					'old'    => $old,
					'reason' => 'empty',
				);
				continue;
			}
			if ( ! self::is_same_host( $old ) || ! self::is_same_host( $new ) ) {
				$summary['skipped'][] = array(
					'line'   => $line,
					'old'    => $old,
					'reason' => 'other_host',
				);
				continue;
			}
			if ( '' === self::normalize_path( $new ) ) {
				$summary['skipped'][] = array(
					'line'   => $line,
					'old'    => $old,
					'reason' => 'home',
				);
				continue;
			}
			$result = self::insert(
				array(
					'object_type' => 'manual',
					'old_path'    => $old,
					'new_path'    => $new,
					'run_id'      => 'import',
				)
			);
			if ( is_wp_error( $result ) ) {
				$summary['skipped'][] = array(
					'line'   => $line,
					'old'    => $old,
					'reason' => $result->get_error_code(),
				);
				continue;
			}
			++$summary['imported'];
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		grmlt_invalidate_health_cache();
		return $summary;
	}

	/**
	 * Human readable reason for a skipped import/migration row.
	 *
	 * @param string $reason Reason code.
	 * @return string
	 */
	public static function reason_label( $reason ) {
		switch ( $reason ) {
			case 'empty':
			case 'empty_path':
				return __( 'Empty path', 'greek-multi-tool' );
			case 'other_host':
				return __( 'Not a path on this site', 'greek-multi-tool' );
			case 'duplicate':
				return __( 'A redirect for this path already exists', 'greek-multi-tool' );
			case 'same_path':
			case 'loop':
				return __( 'Old and new path are the same', 'greek-multi-tool' );
			case 'home':
				return __( 'The destination is the home page; choose a specific page of the site as the destination', 'greek-multi-tool' );
			case 'internal_object':
				return __( 'Internal object without a public address (e.g. an ACF field)', 'greek-multi-tool' );
			case 'no_public_target':
				return __( 'The new address leads to no public content', 'greek-multi-tool' );
			case 'replaced':
				return __( 'A post with a public address had the same old address; its redirect was kept', 'greek-multi-tool' );
		}
		return $reason;
	}
}
