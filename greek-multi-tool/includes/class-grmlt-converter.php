<?php
/**
 * Bulk conversion of existing Greek slugs, in batches, with undo.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

class GRMLT_Converter {

	const STATE_OPTION  = 'grmlt_conversion_run';
	const LAST_OPTION   = 'grmlt_last_conversion';
	const LOCK_OPTION   = 'grmlt_conversion_lock';
	const CANCEL_OPTION = 'grmlt_conversion_cancel';
	const COUNTS_CACHE  = 'grmlt_greek_counts';
	const ACTIVE_WINDOW = 120; // Seconds since the last batch within which a run counts as "running".
	const LOCK_TTL      = 120; // Seconds without a renewal after which a batch lock is considered orphaned.

	/**
	 * True while this class writes slugs, so that the automatic term redirect
	 * hook stands down (the converter records its own redirects).
	 *
	 * @var bool
	 */
	public static $converting = false;

	/**
	 * Internal post types that must never be converted, whatever is registered.
	 *
	 * @var string[]
	 */
	private static $never = array( 'acf-field', 'acf-field-group', 'acf', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'oembed_cache', 'user_request', 'customize_changeset' );

	/**
	 * Internal post types: never converted, never the target of a redirect.
	 *
	 * @return string[]
	 */
	public static function internal_post_types() {
		return self::$never;
	}

	/**
	 * Convertible post types.
	 *
	 * @param bool $include_attachments Include attachments.
	 * @return string[]
	 */
	public static function get_post_types( $include_attachments = false ) {
		$types = get_post_types( array( 'public' => true ), 'names' );
		$types = array_diff( (array) $types, self::$never );
		/**
		 * Filters the post types the converter may rewrite. Only add a post
		 * type whose slug is part of a public URL.
		 *
		 * @since 3.4.1
		 * @param string[] $types Post type names.
		 */
		$types = apply_filters( 'grmlt_convertible_post_types', array_values( $types ) );
		$types = array_values( array_filter( (array) $types, 'is_string' ) );
		$types = array_diff( $types, self::$never );
		if ( ! $include_attachments ) {
			$types = array_diff( $types, array( 'attachment' ) );
		}
		return array_values( $types );
	}

	/**
	 * Convertible taxonomies.
	 *
	 * @return string[]
	 */
	public static function get_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );
		/**
		 * Filters the taxonomies the converter may rewrite term slugs in.
		 *
		 * @since 3.4.1
		 * @param string[] $taxonomies Taxonomy names.
		 */
		$taxonomies = apply_filters( 'grmlt_convertible_taxonomies', array_values( (array) $taxonomies ) );
		return array_values( array_filter( (array) $taxonomies, 'is_string' ) );
	}

	/**
	 * SQL condition matching slugs with Greek characters, percent-encoded
	 * (the way WordPress stores them: %ce.., %cf.., %e1%bc..%e1%bf..) or raw.
	 *
	 * @param string $column Column name (already safe).
	 * @return array{sql:string, args:string[]} Prepared-statement fragment and its arguments.
	 */
	public static function greek_condition( $column ) {
		global $wpdb;
		$args  = array();
		$parts = array();
		foreach ( array( '%ce', '%cf', '%e1%bc', '%e1%bd', '%e1%be', '%e1%bf', '%CE', '%CF' ) as $prefix ) {
			$args[]  = '%' . $wpdb->esc_like( $prefix ) . '%';
			$parts[] = "{$column} LIKE %s";
		}
		$args[]  = '^([0-9A-F]{2})*((CE|CF)[0-9A-F]{2}|E1B[C-F][0-9A-F]{2})';
		$parts[] = "HEX({$column}) REGEXP %s";
		return array(
			'sql'  => '(' . implode( ' OR ', $parts ) . ')',
			'args' => $args,
		);
	}

	/**
	 * Counts of Greek slugs per post type and taxonomy (cached 12 hours).
	 *
	 * @param bool $force Recompute.
	 * @return array{post_types:array<string,int>, taxonomies:array<string,int>, posts_total:int, terms_total:int}
	 */
	public static function count_candidates( $force = false ) {
		global $wpdb;
		$cached = $force ? false : get_transient( self::COUNTS_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$counts = array(
			'post_types'  => array(),
			'taxonomies'  => array(),
			'posts_total' => 0,
			'terms_total' => 0,
		);
		$types  = self::get_post_types( true );
		if ( $types ) {
			$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$cond         = self::greek_condition( 'post_name' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $cond['sql'] is a constant fragment with %s placeholders.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_type, COUNT(*) AS c FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status NOT IN ('auto-draft','trash') AND {$cond['sql']} GROUP BY post_type", array_merge( $types, $cond['args'] ) ) );
			foreach ( $types as $type ) {
				$counts['post_types'][ $type ] = 0;
			}
			foreach ( (array) $rows as $row ) {
				$counts['post_types'][ $row->post_type ] = (int) $row->c;
				if ( 'attachment' !== $row->post_type ) {
					$counts['posts_total'] += (int) $row->c;
				}
			}
		}
		$taxonomies = self::get_taxonomies();
		if ( $taxonomies ) {
			$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
			$cond         = self::greek_condition( 't.slug' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $cond['sql'] is a constant fragment with %s placeholders.
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT tt.taxonomy, COUNT(DISTINCT t.term_id) AS c FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy IN ({$placeholders}) AND {$cond['sql']} GROUP BY tt.taxonomy", array_merge( $taxonomies, $cond['args'] ) ) );
			foreach ( $taxonomies as $taxonomy ) {
				$counts['taxonomies'][ $taxonomy ] = 0;
			}
			foreach ( (array) $rows as $row ) {
				$counts['taxonomies'][ $row->taxonomy ] = (int) $row->c;
				$counts['terms_total']                 += (int) $row->c;
			}
		}
		set_transient( self::COUNTS_CACHE, $counts, 12 * HOUR_IN_SECONDS );
		return $counts;
	}

	/**
	 * Drops the cached counts.
	 */
	public static function invalidate_counts() {
		delete_transient( self::COUNTS_CACHE );
		grmlt_invalidate_health_cache();
	}

	/**
	 * Fetches a batch of candidate posts after a cursor.
	 *
	 * @param string[] $types    Post types.
	 * @param int      $after_id Cursor.
	 * @param int      $limit    Batch size.
	 * @return object[]
	 */
	public static function fetch_posts( $types, $after_id, $limit ) {
		global $wpdb;
		if ( empty( $types ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$cond         = self::greek_condition( 'post_name' );
		$args         = array_merge( $types, $cond['args'], array( (int) $after_id, (int) $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $cond['sql'] is a constant fragment with %s placeholders.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_name, post_title, post_type, post_status, post_parent FROM {$wpdb->posts} WHERE post_type IN ({$placeholders}) AND post_status NOT IN ('auto-draft','trash') AND {$cond['sql']} AND ID > %d ORDER BY ID ASC LIMIT %d", $args ) );
	}

	/**
	 * Fetches a batch of candidate terms after a cursor.
	 *
	 * @param string[] $taxonomies Taxonomies.
	 * @param int      $after_id   Cursor.
	 * @param int      $limit      Batch size.
	 * @return object[]
	 */
	public static function fetch_terms( $taxonomies, $after_id, $limit ) {
		global $wpdb;
		if ( empty( $taxonomies ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
		$cond         = self::greek_condition( 't.slug' );
		$args         = array_merge( $taxonomies, $cond['args'], array( (int) $after_id, (int) $limit ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $cond['sql'] is a constant fragment with %s placeholders.
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT t.term_id, t.slug, t.name, tt.taxonomy, tt.parent, tt.term_taxonomy_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy IN ({$placeholders}) AND {$cond['sql']} AND t.term_id > %d GROUP BY t.term_id ORDER BY t.term_id ASC LIMIT %d", $args ) );
	}

	/**
	 * The Latin slug for a decoded Greek slug: exactly what a new post would get.
	 *
	 * @param string $decoded Decoded current slug.
	 * @return string
	 */
	public static function latin_slug( $decoded ) {
		if ( has_filter( 'sanitize_title', 'grmlt_title_sanitizer' ) ) {
			return sanitize_title( $decoded );
		}
		return grmlt_preview_slug( $decoded );
	}

	/**
	 * Plans the change for a post, or null when nothing should change.
	 *
	 * @param object $post Row from fetch_posts().
	 * @return array{old:string, new:string, suffix:bool}|null
	 */
	public static function plan_post( $post ) {
		$decoded = rawurldecode( (string) $post->post_name );
		if ( ! grmlt_has_greek( $decoded ) ) {
			return null;
		}
		$new = self::latin_slug( $decoded );
		if ( '' === $new ) {
			return null;
		}
		$unique = wp_unique_post_slug( $new, (int) $post->ID, $post->post_status, $post->post_type, (int) $post->post_parent );
		if ( $unique === $post->post_name ) {
			return null;
		}
		return array(
			'old'    => (string) $post->post_name,
			'new'    => $unique,
			'suffix' => $unique !== $new,
		);
	}

	/**
	 * Plans the change for a term, or null.
	 *
	 * @param object $term Row from fetch_terms().
	 * @return array{old:string, new:string, suffix:bool}|null
	 */
	public static function plan_term( $term ) {
		$decoded = rawurldecode( (string) $term->slug );
		if ( ! grmlt_has_greek( $decoded ) ) {
			return null;
		}
		$new = self::latin_slug( $decoded );
		if ( '' === $new ) {
			return null;
		}
		$unique = wp_unique_term_slug(
			$new,
			(object) array(
				'term_id'  => (int) $term->term_id,
				'taxonomy' => $term->taxonomy,
				'parent'   => (int) $term->parent,
			)
		);
		if ( $unique === $term->slug ) {
			return null;
		}
		return array(
			'old'    => (string) $term->slug,
			'new'    => $unique,
			'suffix' => $unique !== $new,
		);
	}

	/**
	 * Dry run. Totals come from the (cached) counts; only a sample is
	 * planned, so the preview stays fast on large sites. The number of
	 * uniqueness suffixes is an estimate extrapolated from the sample.
	 *
	 * @param string[] $post_types   Post types.
	 * @param string[] $taxonomies   Taxonomies.
	 * @param int      $sample_limit Sample rows.
	 * @return array
	 */
	public static function preview( $post_types, $taxonomies, $sample_limit = 50 ) {
		$post_types = array_values( array_intersect( (array) $post_types, self::get_post_types( true ) ) );
		$taxonomies = array_values( array_intersect( (array) $taxonomies, self::get_taxonomies() ) );
		$counts     = self::count_candidates();
		$mode       = grmlt_get_option( 'grmlt_diphthongs' );
		$modes      = grmlt_get_modes();
		$result     = array(
			'mode'            => $mode,
			'mode_label'      => isset( $modes[ $mode ] ) ? $modes[ $mode ] : $mode,
			'post_types'      => array(),
			'taxonomies'      => array(),
			'total'           => 0,
			'suffix_estimate' => 0,
			'sample'          => array(),
			'sample_size'     => 0,
			'approximate'     => false,
		);
		foreach ( $post_types as $type ) {
			$n = isset( $counts['post_types'][ $type ] ) ? (int) $counts['post_types'][ $type ] : 0;
			if ( $n > 0 ) {
				$result['post_types'][ $type ] = $n;
				$result['total']              += $n;
			}
		}
		foreach ( $taxonomies as $taxonomy ) {
			$n = isset( $counts['taxonomies'][ $taxonomy ] ) ? (int) $counts['taxonomies'][ $taxonomy ] : 0;
			if ( $n > 0 ) {
				$result['taxonomies'][ $taxonomy ] = $n;
				$result['total']                  += $n;
			}
		}

		$examined = 0;
		$planned  = 0;
		$suffixed = 0;
		$max      = max( $sample_limit * 4, 200 );

		$cursor = 0;
		while ( count( $result['sample'] ) < $sample_limit && $examined < $max ) {
			$posts = self::fetch_posts( $post_types, $cursor, 50 );
			if ( ! $posts ) {
				break;
			}
			foreach ( $posts as $post ) {
				$cursor = (int) $post->ID;
				++$examined;
				$plan = self::plan_post( $post );
				if ( ! $plan ) {
					continue;
				}
				++$planned;
				if ( $plan['suffix'] ) {
					++$suffixed;
				}
				if ( count( $result['sample'] ) < $sample_limit ) {
					$current            = get_permalink( (int) $post->ID );
					$current            = $current ? $current : '';
					$result['sample'][] = array(
						'type'    => $post->post_type,
						'title'   => $post->post_title,
						'current' => GRMLT_Redirects::normalize_path( $current ),
						'new'     => GRMLT_Redirects::normalize_path( str_replace( $post->post_name, $plan['new'], $current ) ),
					);
				}
			}
		}
		$cursor = 0;
		while ( count( $result['sample'] ) < $sample_limit && $examined < $max ) {
			$terms = self::fetch_terms( $taxonomies, $cursor, 50 );
			if ( ! $terms ) {
				break;
			}
			foreach ( $terms as $term ) {
				$cursor = (int) $term->term_id;
				++$examined;
				$plan = self::plan_term( $term );
				if ( ! $plan ) {
					continue;
				}
				++$planned;
				if ( $plan['suffix'] ) {
					++$suffixed;
				}
				if ( count( $result['sample'] ) < $sample_limit ) {
					$link               = get_term_link( (int) $term->term_id, $term->taxonomy );
					$link               = is_wp_error( $link ) ? '' : $link;
					$result['sample'][] = array(
						'type'    => $term->taxonomy,
						'title'   => $term->name,
						'current' => GRMLT_Redirects::normalize_path( $link ),
						'new'     => GRMLT_Redirects::normalize_path( str_replace( $term->slug, $plan['new'], $link ) ),
					);
				}
			}
		}

		$result['sample_size'] = $planned;
		$result['approximate'] = $result['total'] > $examined;
		if ( $planned > 0 ) {
			$result['suffix_estimate'] = $result['approximate'] ? (int) round( $suffixed / $planned * $result['total'] ) : $suffixed;
		}
		return $result;
	}

	/**
	 * Current run state, if a conversion is in progress or was interrupted.
	 *
	 * @return array|null
	 */
	public static function get_state() {
		$state = get_option( self::STATE_OPTION, null );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Whether a run is actively being processed (a batch finished recently).
	 *
	 * @param array|null $state State.
	 * @return bool
	 */
	public static function is_active( $state ) {
		return is_array( $state ) && isset( $state['last_batch'] ) && ( time() - (int) $state['last_batch'] ) < self::ACTIVE_WINDOW;
	}

	/**
	 * Summary of the run state for the UI.
	 *
	 * @return array|null
	 */
	public static function state_summary() {
		$state = self::get_state();
		if ( ! $state ) {
			return null;
		}
		return array(
			'run_id'    => $state['run_id'],
			'active'    => self::is_active( $state ),
			'converted' => (int) $state['posts'] + (int) $state['terms'],
			'processed' => (int) $state['processed'],
			'started'   => (int) $state['started'],
		);
	}

	/**
	 * Acquires the lock shared by batches, start, cancel and undo (an
	 * INSERT IGNORE row, see grmlt_lock_acquire()). The holder renews it
	 * while it works, so only a request that died leaves it to expire.
	 *
	 * @return bool
	 */
	private static function acquire_lock() {
		return grmlt_lock_acquire( self::LOCK_OPTION, self::LOCK_TTL );
	}

	/**
	 * Releases the lock (only if this request still holds it).
	 */
	private static function release_lock() {
		grmlt_lock_release( self::LOCK_OPTION );
	}

	/**
	 * The "locked" error.
	 *
	 * @return WP_Error
	 */
	private static function locked_error() {
		return new WP_Error( 'grmlt_locked', __( 'Another batch of this conversion is still running. Please wait a moment.', 'greek-multi-tool' ) );
	}

	/**
	 * Whether a cancel was requested for the run, read from the database
	 * (another request writes it while this one holds the batch lock, so the
	 * options cache of this request cannot be trusted).
	 *
	 * @param string $run_id Run id.
	 * @return bool
	 */
	private static function cancel_requested( $run_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::CANCEL_OPTION ) );
		return null !== $value && ( '' === $value || $value === $run_id );
	}

	/**
	 * Starts a new run. The previous run's undo data is kept until the first
	 * object of this run is actually converted.
	 *
	 * @param string[] $post_types          Post types.
	 * @param string[] $taxonomies          Taxonomies.
	 * @param bool     $include_attachments Include attachments.
	 * @return array|WP_Error State.
	 */
	public static function start_run( $post_types, $taxonomies, $include_attachments = false ) {
		if ( ! self::acquire_lock() ) {
			return self::locked_error();
		}
		try {
			if ( self::get_state() ) {
				return new WP_Error( 'grmlt_running', __( 'A conversion is already in progress. Continue it or discard it first.', 'greek-multi-tool' ) );
			}
			return self::write_new_state( $post_types, $taxonomies, $include_attachments );
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Writes the state of a new run (the caller holds the lock).
	 *
	 * @param string[] $post_types          Post types.
	 * @param string[] $taxonomies          Taxonomies.
	 * @param bool     $include_attachments Include attachments.
	 * @return array State.
	 */
	private static function write_new_state( $post_types, $taxonomies, $include_attachments ) {
		$post_types = array_values( array_intersect( (array) $post_types, self::get_post_types( (bool) $include_attachments ) ) );
		$taxonomies = array_values( array_intersect( (array) $taxonomies, self::get_taxonomies() ) );
		delete_option( self::CANCEL_OPTION );

		$state = array(
			'run_id'      => substr( md5( uniqid( 'grmlt', true ) ), 0, 12 ),
			'started'     => time(),
			'last_batch'  => time(),
			'post_types'  => $post_types,
			'taxonomies'  => $taxonomies,
			'post_cursor' => 0,
			'term_cursor' => 0,
			'posts_done'  => false,
			'terms_done'  => false,
			'posts'       => 0,
			'terms'       => 0,
			'skipped'     => 0,
			'processed'   => 0,
			'purged'      => false,
			'mode'        => grmlt_get_option( 'grmlt_diphthongs' ),
		);
		update_option( self::STATE_OPTION, $state, false );
		return $state;
	}

	/**
	 * Batch size.
	 *
	 * @return int
	 */
	public static function batch_size() {
		/**
		 * Filters how many objects one conversion batch processes.
		 *
		 * @since 4.0.0
		 * @param int $size Batch size. Default 100.
		 */
		return max( 1, (int) apply_filters( 'grmlt_conversion_batch_size', 100 ) );
	}

	/**
	 * Discards the undo data of the previous conversion. Called right before
	 * the first object of a new run is written.
	 */
	private static function purge_previous() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', grmlt_conversions_table() ) );
		delete_option( self::LAST_OPTION );
	}

	/**
	 * Processes one batch of the current run.
	 *
	 * @return array{done:bool, state:array}|WP_Error
	 */
	public static function run_batch() {
		if ( ! self::acquire_lock() ) {
			return self::locked_error();
		}
		try {
			wp_cache_delete( self::STATE_OPTION, 'options' );
			$state = self::get_state();
			if ( ! $state ) {
				return new WP_Error( 'no_run', __( 'No conversion is in progress.', 'greek-multi-tool' ) );
			}
			$run_id = $state['run_id'];
			$limit  = self::batch_size();
			$done   = 0;
			$check  = 0.0;

			// Checked before every object (at most twice a second) and before
			// the state is written back: a Cancel clicked while this batch runs
			// is completed by this batch.
			$should_stop = function () use ( $run_id, &$check ) {
				if ( microtime( true ) - $check < 0.5 ) {
					return false;
				}
				$check = microtime( true );
				grmlt_lock_refresh( self::LOCK_OPTION );
				return self::cancel_requested( $run_id );
			};

			if ( $should_stop() ) {
				return self::finish_cancel( $state );
			}

			if ( ! $state['posts_done'] ) {
				$posts = self::fetch_posts( $state['post_types'], $state['post_cursor'], $limit );
				foreach ( $posts as $post ) {
					if ( $should_stop() ) {
						return self::finish_cancel( $state );
					}
					$state['post_cursor'] = (int) $post->ID;
					++$state['processed'];
					++$done;
					$plan = self::plan_post( $post );
					if ( ! $plan ) {
						++$state['skipped'];
						continue;
					}
					if ( ! $state['purged'] ) {
						self::purge_previous();
						$state['purged'] = true;
					}
					if ( self::convert_post( $post, $plan, $run_id ) ) {
						++$state['posts'];
					} else {
						++$state['skipped'];
					}
				}
				if ( count( $posts ) < $limit ) {
					$state['posts_done'] = true;
				}
			}

			if ( $state['posts_done'] && ! $state['terms_done'] && $done < $limit ) {
				$terms = self::fetch_terms( $state['taxonomies'], $state['term_cursor'], $limit - $done );
				foreach ( $terms as $term ) {
					if ( $should_stop() ) {
						return self::finish_cancel( $state );
					}
					$state['term_cursor'] = (int) $term->term_id;
					++$state['processed'];
					$plan = self::plan_term( $term );
					if ( ! $plan ) {
						++$state['skipped'];
						continue;
					}
					if ( ! $state['purged'] ) {
						self::purge_previous();
						$state['purged'] = true;
					}
					if ( self::convert_term( $term, $plan, $run_id ) ) {
						++$state['terms'];
					} else {
						++$state['skipped'];
					}
				}
				if ( count( $terms ) < $limit - $done ) {
					$state['terms_done'] = true;
				}
			}

			if ( function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}

			// Write the state back only if nobody replaced the run meanwhile.
			wp_cache_delete( self::STATE_OPTION, 'options' );
			$current = self::get_state();
			if ( ! $current || $current['run_id'] !== $run_id ) {
				return new WP_Error( 'grmlt_superseded', __( 'The conversion was cancelled or replaced while this batch was running.', 'greek-multi-tool' ) );
			}
			$check = 0.0;
			if ( $should_stop() ) {
				return self::finish_cancel( $state );
			}
			$state['last_batch'] = time();
			$finished            = $state['posts_done'] && $state['terms_done'];
			if ( $finished ) {
				self::finish_run( $state );
			} else {
				update_option( self::STATE_OPTION, $state, false );
			}
			return array(
				'done'  => $finished,
				'state' => $state,
			);
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Records a run as the last (undoable) conversion.
	 *
	 * @param array $state State.
	 */
	private static function record_last( $state ) {
		if ( (int) $state['posts'] + (int) $state['terms'] > 0 ) {
			update_option(
				self::LAST_OPTION,
				array(
					'run_id'   => $state['run_id'],
					'time'     => time(),
					'posts'    => (int) $state['posts'],
					'terms'    => (int) $state['terms'],
					'mode'     => $state['mode'],
					'complete' => ! empty( $state['posts_done'] ) && ! empty( $state['terms_done'] ),
				),
				false
			);
		}
	}

	/**
	 * Records the finished run.
	 *
	 * @param array $state State.
	 */
	private static function finish_run( $state ) {
		delete_option( self::STATE_OPTION );
		self::record_last( $state );
		self::invalidate_counts();
		/**
		 * Fires when a bulk conversion finishes.
		 *
		 * @since 4.0.0
		 * @param array $state Run state (run_id, posts, terms, skipped, mode).
		 */
		do_action( 'grmlt_conversion_finished', $state );
	}

	/**
	 * Ends a run as cancelled (the caller holds the lock): whatever was
	 * converted stays converted and becomes the last undoable conversion.
	 *
	 * @param array $state State as it is right now.
	 * @return array{done:bool, cancelled:bool, state:array}
	 */
	private static function finish_cancel( $state ) {
		delete_option( self::STATE_OPTION );
		delete_option( self::CANCEL_OPTION );
		self::record_last( $state );
		self::invalidate_counts();
		return array(
			'done'      => true,
			'cancelled' => true,
			'state'     => $state,
		);
	}

	/**
	 * Cancels a run, or discards an interrupted one. Whatever was converted
	 * stays converted and becomes the last undoable conversion.
	 *
	 * When a batch is running right now (it holds the lock), the request is
	 * recorded instead and that batch completes the cancel before its next
	 * object; the result then says "pending" and the caller learns the final
	 * figures from the batch's own response.
	 *
	 * @return array{converted:int, pending:bool}|WP_Error
	 */
	public static function cancel_run() {
		$state = self::get_state();
		if ( ! $state ) {
			return array(
				'converted' => 0,
				'pending'   => false,
			);
		}
		if ( ! self::acquire_lock() ) {
			update_option( self::CANCEL_OPTION, (string) $state['run_id'], false );
			return array(
				'converted' => (int) $state['posts'] + (int) $state['terms'],
				'pending'   => true,
			);
		}
		try {
			wp_cache_delete( self::STATE_OPTION, 'options' );
			$state = self::get_state();
			if ( ! $state ) {
				delete_option( self::CANCEL_OPTION );
				return array(
					'converted' => 0,
					'pending'   => false,
				);
			}
			self::finish_cancel( $state );
			return array(
				'converted' => (int) $state['posts'] + (int) $state['terms'],
				'pending'   => false,
			);
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Descendant post ids of a post (hierarchical types), limited.
	 *
	 * @param int    $post_id   Post id.
	 * @param string $post_type Post type.
	 * @return int[]
	 */
	private static function descendants( $post_id, $post_type ) {
		global $wpdb;
		if ( ! is_post_type_hierarchical( $post_type ) ) {
			return array();
		}
		$ids   = array();
		$queue = array( (int) $post_id );
		while ( $queue && count( $ids ) < 500 ) {
			$placeholders = implode( ',', array_fill( 0, count( $queue ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$children = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_parent IN ({$placeholders}) AND post_type = %s AND post_status = 'publish'", array_merge( $queue, array( $post_type ) ) ) );
			$children = array_map( 'intval', (array) $children );
			$ids      = array_merge( $ids, $children );
			$queue    = $children;
		}
		return array_slice( $ids, 0, 500 );
	}

	/**
	 * Fires post_updated for a slug change without letting WordPress create a
	 * revision for it (wp_save_post_revision is hooked to post_updated in every
	 * supported version and would store a first revision for posts without one).
	 *
	 * @param int     $post_id Post id.
	 * @param WP_Post $after   Post after.
	 * @param WP_Post $before  Post before.
	 */
	private static function fire_post_updated( $post_id, $after, $before ) {
		$priority = has_action( 'post_updated', 'wp_save_post_revision' );
		if ( false !== $priority ) {
			remove_action( 'post_updated', 'wp_save_post_revision', $priority );
		}
		/** This action is documented in wp-includes/post.php */
		do_action( 'post_updated', $post_id, $after, $before ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( false !== $priority ) {
			add_action( 'post_updated', 'wp_save_post_revision', $priority, 1 );
		}
	}

	/**
	 * Fires the core term hooks around a slug change, with core's signatures,
	 * so that plugins which track slug changes (WooCommerce attribute terms,
	 * for example) see it exactly like an edit from the admin.
	 *
	 * @param string $when     'before' or 'after'.
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 * @param array  $args     Update args.
	 */
	private static function fire_term_hooks( $when, $term_id, $tt_id, $taxonomy, $args ) {
		// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		if ( 'before' === $when ) {
			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edit_terms', $term_id, $taxonomy, $args );
			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edit_term', $term_id, $tt_id, $taxonomy, $args );
		} else {
			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edited_terms', $term_id, $taxonomy, $args );
			/** This action is documented in wp-includes/taxonomy.php */
			do_action( 'edited_term', $term_id, $tt_id, $taxonomy, $args );
		}
		// phpcs:enable
	}

	/**
	 * Records redirects for a set of old links, after the slug changed.
	 *
	 * @param string $object_type 'post' or 'term'.
	 * @param array  $old_links   id => old URL.
	 * @param string $taxonomy    Taxonomy (terms only).
	 * @param string $run_id      Run id.
	 * @param bool   $cleanup     Delete dead object rows on the new addresses
	 *                            (not during an undo, which keeps 3.x rows).
	 */
	private static function record_redirects( $object_type, $old_links, $taxonomy, $run_id, $cleanup = true ) {
		foreach ( $old_links as $id => $old_link ) {
			$new_link = 'post' === $object_type ? get_permalink( $id ) : get_term_link( $id, $taxonomy );
			if ( ! $old_link || ! $new_link || is_wp_error( $new_link ) ) {
				continue;
			}
			$old_path = GRMLT_Redirects::normalize_path( $old_link );
			$new_path = GRMLT_Redirects::normalize_path( $new_link );
			if ( '' === $old_path || $old_path === $new_path || false !== strpos( $old_link, '?p=' ) ) {
				continue;
			}
			// A redirect left on the address that is now live (from an earlier
			// undo, for example) can never fire again.
			if ( $cleanup ) {
				GRMLT_Redirects::delete_object_rows_at( $new_path );
			}
			GRMLT_Redirects::insert(
				array(
					'object_type' => $object_type,
					'object_id'   => $id,
					'old_path'    => $old_path,
					'new_path'    => $new_path,
					'run_id'      => $run_id,
				)
			);
		}
	}

	/**
	 * Applies a planned change to a post. Redirects are always recorded; the
	 * grmlt_redirect option only decides whether they are served.
	 *
	 * @param object $post   Row from fetch_posts().
	 * @param array  $plan   Plan.
	 * @param string $run_id Run id.
	 * @return bool
	 */
	public static function convert_post( $post, $plan, $run_id ) {
		global $wpdb;
		$post_id   = (int) $post->ID;
		$before    = get_post( $post_id );
		$old_links = array( $post_id => get_permalink( $post_id ) );
		foreach ( self::descendants( $post_id, $post->post_type ) as $child_id ) {
			$old_links[ $child_id ] = get_permalink( $child_id );
		}

		self::$converting = true;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update( $wpdb->posts, array( 'post_name' => $plan['new'] ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			self::$converting = false;
			return false;
		}
		foreach ( array_keys( $old_links ) as $id ) {
			clean_post_cache( $id );
		}

		// Let WordPress keep its own way back as well (wp_old_slug_redirect).
		$existing = get_post_meta( $post_id, '_wp_old_slug' );
		if ( ! in_array( $plan['old'], (array) $existing, true ) ) {
			add_post_meta( $post_id, '_wp_old_slug', $plan['old'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			grmlt_conversions_table(),
			array(
				'run_id'         => $run_id,
				'object_type'    => 'post',
				'object_subtype' => $post->post_type,
				'object_id'      => $post_id,
				'old_slug'       => $plan['old'],
				'new_slug'       => $plan['new'],
				'created_at'     => current_time( 'mysql', true ),
			)
		);

		self::record_redirects( 'post', $old_links, '', $run_id );

		$after = get_post( $post_id );
		if ( $before && $after ) {
			self::fire_post_updated( $post_id, $after, $before );
		}
		self::$converting = false;
		/**
		 * Fires after a post slug was converted (or restored by undo).
		 *
		 * @since 4.0.0
		 * @param int    $post_id Post id.
		 * @param string $old     Old slug (as stored).
		 * @param string $new     New slug.
		 */
		do_action( 'grmlt_post_slug_converted', $post_id, $plan['old'], $plan['new'] );
		return true;
	}

	/**
	 * Applies a planned change to a term.
	 *
	 * @param object $term   Row from fetch_terms().
	 * @param array  $plan   Plan.
	 * @param string $run_id Run id.
	 * @return bool
	 */
	public static function convert_term( $term, $plan, $run_id ) {
		global $wpdb;
		$term_id   = (int) $term->term_id;
		$tt_id     = (int) $term->term_taxonomy_id;
		$taxonomy  = $term->taxonomy;
		$args      = array( 'slug' => $plan['new'] );
		$old_links = array();
		$ids       = array( $term_id );
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$ids = array_merge( $ids, array_slice( array_map( 'intval', (array) get_term_children( $term_id, $taxonomy ) ), 0, 500 ) );
		}
		foreach ( $ids as $id ) {
			$link = get_term_link( $id, $taxonomy );
			if ( ! is_wp_error( $link ) ) {
				$old_links[ $id ] = $link;
			}
		}

		self::$converting = true;
		self::fire_term_hooks( 'before', $term_id, $tt_id, $taxonomy, $args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$updated = $wpdb->update( $wpdb->terms, array( 'slug' => $plan['new'] ), array( 'term_id' => $term_id ), array( '%s' ), array( '%d' ) );
		if ( false === $updated ) {
			// Close what the "before" hooks opened (WooCommerce keeps the term
			// it is editing until edited_term), with the slug unchanged.
			self::fire_term_hooks( 'after', $term_id, $tt_id, $taxonomy, array( 'slug' => $plan['old'] ) );
			self::$converting = false;
			return false;
		}
		clean_term_cache( $ids, $taxonomy );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			grmlt_conversions_table(),
			array(
				'run_id'         => $run_id,
				'object_type'    => 'term',
				'object_subtype' => $taxonomy,
				'object_id'      => $term_id,
				'old_slug'       => $plan['old'],
				'new_slug'       => $plan['new'],
				'created_at'     => current_time( 'mysql', true ),
			)
		);

		self::record_redirects( 'term', $old_links, $taxonomy, $run_id );
		self::fire_term_hooks( 'after', $term_id, $tt_id, $taxonomy, $args );
		self::$converting = false;
		/**
		 * Fires after a term slug was converted (or restored by undo).
		 *
		 * @since 4.0.0
		 * @param int    $term_id  Term id.
		 * @param string $taxonomy Taxonomy.
		 * @param string $old      Old slug.
		 * @param string $new      New slug.
		 */
		do_action( 'grmlt_term_slug_converted', $term_id, $taxonomy, $plan['old'], $plan['new'] );
		return true;
	}

	/**
	 * The last finished (or cancelled) conversion, if it can still be undone.
	 *
	 * @return array|null
	 */
	public static function get_last_conversion() {
		$last = get_option( self::LAST_OPTION, null );
		return is_array( $last ) && ! empty( $last['run_id'] ) ? $last : null;
	}

	/**
	 * Undoes one batch of the last conversion.
	 *
	 * Only objects whose slug is still the one the conversion wrote are
	 * restored. Their Latin addresses keep working: for posts of
	 * non-hierarchical types the Latin slug is added to _wp_old_slug and
	 * WordPress redirects it itself; pages, other hierarchical types and
	 * terms, which core does not redirect, get reverse redirects (Latin to
	 * Greek, run id "undo-{run}") for the object and every descendant whose
	 * address changed. The run's redirects of the restored objects and of
	 * their descendants are deleted; the redirects migrated from 3.x (run
	 * "legacy-3x") never are.
	 *
	 * @param int $cursor Last processed conversion row id.
	 * @return array{done:bool, cursor:int, restored:int, skipped:int, total:int}|WP_Error
	 */
	public static function undo_batch( $cursor = 0 ) {
		if ( ! self::acquire_lock() ) {
			return self::locked_error();
		}
		try {
			return self::undo_batch_locked( (int) $cursor );
		} finally {
			self::$converting = false;
			self::release_lock();
		}
	}

	/**
	 * undo_batch() under the lock.
	 *
	 * @param int $cursor Last processed conversion row id.
	 * @return array|WP_Error
	 */
	private static function undo_batch_locked( $cursor ) {
		global $wpdb;
		wp_cache_delete( self::STATE_OPTION, 'options' );
		if ( self::get_state() ) {
			return new WP_Error( 'grmlt_running', __( 'A conversion is in progress. Finish or discard it before undoing.', 'greek-multi-tool' ) );
		}
		$last = self::get_last_conversion();
		if ( ! $last ) {
			return new WP_Error( 'nothing', __( 'There is no conversion to undo.', 'greek-multi-tool' ) );
		}
		$table     = grmlt_conversions_table();
		$limit     = self::batch_size();
		$run_id    = $last['run_id'];
		$is_legacy = 'legacy' === $run_id;
		$undo_run  = substr( 'undo-' . $run_id, 0, 32 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE run_id = %s', $table, $run_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows     = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE run_id = %s AND id > %d ORDER BY id ASC LIMIT %d', $table, $run_id, (int) $cursor, $limit ) );
		$restored = 0;
		$skipped  = 0;
		self::$converting = true;
		foreach ( $rows as $row ) {
			$cursor = (int) $row->id;
			$id     = (int) $row->object_id;
			grmlt_lock_refresh( self::LOCK_OPTION );
			if ( 'post' === $row->object_type ) {
				$before = get_post( $id );
				if ( ! $before || $before->post_name !== $row->new_slug ) {
					++$skipped;
					continue;
				}
				// Latin addresses before the restore, for the reverse redirects.
				$latin = array();
				if ( is_post_type_hierarchical( $before->post_type ) ) {
					$latin[ $id ] = get_permalink( $id );
					foreach ( self::descendants( $id, $before->post_type ) as $child_id ) {
						$latin[ $child_id ] = get_permalink( $child_id );
					}
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update( $wpdb->posts, array( 'post_name' => $row->old_slug ), array( 'ID' => $id ), array( '%s' ), array( '%d' ) );
				foreach ( array_unique( array_merge( array( $id ), array_keys( $latin ) ) ) as $family_id ) {
					clean_post_cache( $family_id );
				}
				$existing = get_post_meta( $id, '_wp_old_slug' );
				if ( ! in_array( $row->new_slug, (array) $existing, true ) ) {
					add_post_meta( $id, '_wp_old_slug', $row->new_slug );
				}
				$after = get_post( $id );
				if ( $after ) {
					self::fire_post_updated( $id, $after, $before );
				}
				self::clean_after_restore( 'post', $id, array_keys( $latin ), $is_legacy ? '' : $run_id, '' );
				self::record_redirects( 'post', $latin, '', $undo_run, false );
				do_action( 'grmlt_post_slug_converted', $id, $row->new_slug, $row->old_slug );
				++$restored;
			} elseif ( 'term' === $row->object_type ) {
				$term = get_term( $id );
				if ( ! $term || is_wp_error( $term ) || $term->slug !== $row->new_slug ) {
					++$skipped;
					continue;
				}
				$taxonomy = $term->taxonomy;
				$family   = array( $id );
				if ( is_taxonomy_hierarchical( $taxonomy ) ) {
					$family = array_merge( $family, array_slice( array_map( 'intval', (array) get_term_children( $id, $taxonomy ) ), 0, 500 ) );
				}
				$latin = array();
				foreach ( $family as $family_id ) {
					$link = get_term_link( $family_id, $taxonomy );
					if ( ! is_wp_error( $link ) ) {
						$latin[ $family_id ] = $link;
					}
				}
				$args = array( 'slug' => $row->old_slug );
				self::fire_term_hooks( 'before', $id, (int) $term->term_taxonomy_id, $taxonomy, $args );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$updated = $wpdb->update( $wpdb->terms, array( 'slug' => $row->old_slug ), array( 'term_id' => $id ), array( '%s' ), array( '%d' ) );
				if ( false === $updated ) {
					self::fire_term_hooks( 'after', $id, (int) $term->term_taxonomy_id, $taxonomy, array( 'slug' => $row->new_slug ) );
					++$skipped;
					continue;
				}
				clean_term_cache( $family, $taxonomy );
				self::fire_term_hooks( 'after', $id, (int) $term->term_taxonomy_id, $taxonomy, $args );
				self::clean_after_restore( 'term', $id, $family, $is_legacy ? '' : $run_id, $taxonomy );
				self::record_redirects( 'term', $latin, $taxonomy, $undo_run, false );
				do_action( 'grmlt_term_slug_converted', $id, $taxonomy, $row->new_slug, $row->old_slug );
				++$restored;
			}
		}
		self::$converting = false;
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
		$done = count( $rows ) < $limit;
		if ( $done ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->delete( $table, array( 'run_id' => $run_id ), array( '%s' ) );
			delete_option( self::LAST_OPTION );
			self::invalidate_counts();
		}
		return array(
			'done'     => $done,
			'cursor'   => $cursor,
			'restored' => $restored,
			'skipped'  => $skipped,
			'total'    => $total,
		);
	}

	/**
	 * Redirect clean-up after an object was restored by an undo: the run's
	 * redirects of the restored object itself go (unless the run is the 3.x
	 * snapshot, whose redirects are kept), and for the object and each
	 * descendant only the redirects whose old address is the address it has
	 * again: those can never fire, and would loop once the object is deleted.
	 * Other redirects of descendants stay, because the undo may skip them.
	 *
	 * @param string $type     post|term.
	 * @param int    $id       Restored object.
	 * @param int[]  $family   Ids of the object and its descendants.
	 * @param string $run_id   Run to delete the object's redirects of ('': none).
	 * @param string $taxonomy Taxonomy (terms).
	 */
	private static function clean_after_restore( $type, $id, $family, $run_id, $taxonomy ) {
		if ( '' !== $run_id ) {
			GRMLT_Redirects::delete_for_objects( $run_id, $type, array( $id ) );
		}
		foreach ( array_unique( array_merge( array( (int) $id ), array_map( 'intval', (array) $family ) ) ) as $member ) {
			$link = 'post' === $type ? get_permalink( $member ) : get_term_link( $member, $taxonomy );
			if ( ! $link || is_wp_error( $link ) ) {
				continue;
			}
			GRMLT_Redirects::delete_object_rows_at( GRMLT_Redirects::normalize_path( $link ), $type, $member );
		}
	}

	/**
	 * Name of an active SEO plugin that keeps its own URL data, or ''.
	 *
	 * @return string
	 */
	public static function seo_plugin() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'Yoast SEO';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'Rank Math';
		}
		return '';
	}
}
