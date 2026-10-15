<?php
/**
 * REST API: grmlt/v1.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

class GRMLT_REST {

	const NS = 'grmlt/v1';

	/**
	 * Registers the routes.
	 */
	public static function register_routes() {
		$manage = array( __CLASS__, 'can_manage' );
		$list   = array(
			'type'              => 'array',
			'default'           => array(),
			'items'             => array( 'type' => 'string' ),
			'sanitize_callback' => array( __CLASS__, 'sanitize_string_list' ),
		);
		$bool   = array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		);

		register_rest_route(
			self::NS,
			'/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'preview' ),
				'args'                => array(
					'title'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'filename'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'mode'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
					'one_letter' => $bool,
					'two_letter' => $bool,
					'stopwords'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'grmlt_sanitize_word_list',
					),
				),
			)
		);

		if ( grmlt_is_on( 'grmlt_enable_text_analysis' ) ) {
			register_rest_route(
				self::NS,
				'/analyze',
				array(
					'methods'             => 'POST',
					'permission_callback' => array( __CLASS__, 'can_analyze' ),
					'callback'            => array( __CLASS__, 'analyze' ),
					'args'                => array(
						'content' => array(
							'type'              => 'string',
							'default'           => '',
							'validate_callback' => function ( $value ) {
								return is_string( $value ) && strlen( $value ) <= MB_IN_BYTES;
							},
						),
						'post_id' => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				)
			);
		}

		register_rest_route(
			self::NS,
			'/conversion/counts',
			array(
				'methods'             => 'GET',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'counts' ),
				'args'                => array( 'force' => $bool ),
			)
		);

		register_rest_route(
			self::NS,
			'/conversion/preview',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'conversion_preview' ),
				'args'                => array(
					'post_types' => $list,
					'taxonomies' => $list,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/conversion/run',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'conversion_run' ),
				'args'                => array(
					'step'                => array(
						'type'              => 'string',
						'default'           => 'continue',
						'enum'              => array( 'start', 'continue', 'cancel', 'status' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'post_types'          => $list,
					'taxonomies'          => $list,
					'include_attachments' => $bool,
					'backup_confirmed'    => $bool,
				),
			)
		);

		register_rest_route(
			self::NS,
			'/conversion/undo',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'conversion_undo' ),
				'args'                => array(
					'cursor' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/redirects/check',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'redirects_check' ),
				'args'                => array(
					'offset' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/redirects/update',
			array(
				'methods'             => 'POST',
				'permission_callback' => $manage,
				'callback'            => array( __CLASS__, 'redirects_update' ),
				'args'                => array(
					'id'       => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return absint( $value ) > 0;
						},
					),
					'new_path' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => array( 'GRMLT_Redirects', 'sanitize_path_input' ),
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && strlen( $value ) <= 2048;
						},
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/site-health/html-lang',
			array(
				'methods'             => 'GET',
				'permission_callback' => function () {
					return current_user_can( 'view_site_health_checks' );
				},
				'callback'            => function () {
					delete_transient( 'grmlt_health_html_lang' );
					return rest_ensure_response( GRMLT_Site_Health::test_html_lang() );
				},
			)
		);
	}

	/**
	 * Permission: administrators.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission: the analysis endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function can_analyze( $request ) {
		$post_id = absint( $request->get_param( 'post_id' ) );
		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Sanitizes a list of keys.
	 *
	 * @param mixed $value Value.
	 * @return string[]
	 */
	public static function sanitize_string_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		return array_values( array_filter( array_map( 'sanitize_key', (array) $value ), 'strlen' ) );
	}

	/**
	 * POST /preview
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function preview( $request ) {
		$mode = $request->get_param( 'mode' );
		$args = array(
			'mode'       => '' !== $mode ? grmlt_sanitize_mode( $mode ) : null,
			'one_letter' => (bool) $request->get_param( 'one_letter' ),
			'two_letter' => (bool) $request->get_param( 'two_letter' ),
			'stopwords'  => array_values( array_filter( array_map( 'trim', explode( ',', (string) $request->get_param( 'stopwords' ) ) ), 'strlen' ) ),
		);
		$slug = grmlt_preview_slug( (string) $request->get_param( 'title' ), $args );
		$file = grmlt_preview_filename( (string) $request->get_param( 'filename' ), $args );
		return rest_ensure_response(
			array(
				'slug'     => $slug,
				'url'      => '' === $slug ? '' : user_trailingslashit( home_url( '/' . $slug ) ),
				'filename' => $file,
			)
		);
	}

	/**
	 * POST /analyze
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function analyze( $request ) {
		if ( ! function_exists( 'mb_strtoupper' ) ) {
			return new WP_Error( 'grmlt_no_mbstring', __( 'The accent check needs the PHP mbstring extension, which is not available on this server.', 'greek-multi-tool' ), array( 'status' => 501 ) );
		}
		$content = (string) $request->get_param( 'content' );
		$post_id = absint( $request->get_param( 'post_id' ) );
		// Shortcodes are only rendered for content that belongs to a post the
		// user may edit; arbitrary content (post_id 0) is stripped, not executed.
		$text = grmlt_extract_clean_text( $content, $post_id, $post_id > 0 ? null : false );
		if ( '' === $text && $post_id > 0 ) {
			$text = grmlt_get_post_clean_text( $post_id );
		}
		if ( '' === $text ) {
			return new WP_Error( 'grmlt_no_content', __( 'There is no text to check yet.', 'greek-multi-tool' ), array( 'status' => 400 ) );
		}
		return rest_ensure_response( grmlt_analyze_text( $text ) );
	}

	/**
	 * GET /conversion/counts
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function counts( $request ) {
		return rest_ensure_response( GRMLT_Converter::count_candidates( (bool) $request->get_param( 'force' ) ) );
	}

	/**
	 * POST /conversion/preview
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function conversion_preview( $request ) {
		return rest_ensure_response( GRMLT_Converter::preview( $request->get_param( 'post_types' ), $request->get_param( 'taxonomies' ) ) );
	}

	/**
	 * POST /conversion/run
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function conversion_run( $request ) {
		$step = $request->get_param( 'step' );
		if ( 'status' === $step ) {
			return rest_ensure_response( array( 'run' => GRMLT_Converter::state_summary() ) );
		}
		if ( 'cancel' === $step ) {
			// Cancels a running run or discards an interrupted one. Whatever
			// was converted stays converted and becomes undoable.
			$result = GRMLT_Converter::cancel_run();
			if ( is_wp_error( $result ) ) {
				$result->add_data( array( 'status' => 409 ) );
				return $result;
			}
			return rest_ensure_response(
				array(
					'cancelled' => ! $result['pending'],
					'pending'   => $result['pending'],
					'converted' => $result['converted'],
				)
			);
		}
		if ( 'start' === $step ) {
			if ( ! $request->get_param( 'backup_confirmed' ) ) {
				return new WP_Error( 'grmlt_backup', __( 'Please confirm that you have a database backup.', 'greek-multi-tool' ), array( 'status' => 400 ) );
			}
			$state = GRMLT_Converter::start_run( $request->get_param( 'post_types' ), $request->get_param( 'taxonomies' ), (bool) $request->get_param( 'include_attachments' ) );
			if ( is_wp_error( $state ) ) {
				$state->add_data(
					array(
						'status' => 409,
						'run'    => GRMLT_Converter::state_summary(),
					)
				);
				return $state;
			}
		}
		$result = GRMLT_Converter::run_batch();
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 'grmlt_locked' === $result->get_error_code() ? 423 : 409 ) );
			return $result;
		}
		$result['seo_plugin'] = GRMLT_Converter::seo_plugin();
		return rest_ensure_response( $result );
	}

	/**
	 * POST /conversion/undo
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function conversion_undo( $request ) {
		$result = GRMLT_Converter::undo_batch( (int) $request->get_param( 'cursor' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 'grmlt_locked' === $result->get_error_code() ? 423 : 409 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST /redirects/check
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function redirects_check( $request ) {
		return rest_ensure_response( GRMLT_Redirects::check_batch( (int) $request->get_param( 'offset' ), 200 ) );
	}

	/**
	 * POST /redirects/update
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function redirects_update( $request ) {
		$result = GRMLT_Redirects::update_destination( (int) $request->get_param( 'id' ), (string) $request->get_param( 'new_path' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		$row = GRMLT_Redirects::get( (int) $request->get_param( 'id' ) );
		return rest_ensure_response(
			array(
				'id'       => (int) $row->id,
				'new_path' => '/' . $row->new_path . '/',
			)
		);
	}
}
