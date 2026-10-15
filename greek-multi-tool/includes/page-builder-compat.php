<?php
/**
 * Page Builder Compatibility Layer: clean text from page builder content,
 * and the Yoast SEO analysis filter.
 *
 * @package Grmlt_Plugin
 * @since   3.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the current request is a back end (editing) request. Shortcodes
 * are only ever rendered there, never on the front end (3.4.0 regression:
 * rendering inside wp_head broke themes with on-demand CSS).
 *
 * @since 3.4.0
 * @return bool
 */
function grmlt_is_backend_request() {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
		return false;
	}
	return is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST );
}

/**
 * Nesting depth of grmlt_render_isolated(). With a change, adjusts it first.
 *
 * @since 4.0.0
 * @param int $change Optional. +1 on entry, -1 on exit. Default 0 (read only).
 * @return int
 */
function grmlt_render_depth( $change = 0 ) {
	static $depth = 0;
	$depth += (int) $change;
	return $depth;
}

/**
 * Runs a callback that may execute shortcodes, blocks or the_content, and
 * puts back every global a post loop inside it can change.
 *
 * A shortcode with its own loop (the_post(), setup_postdata(), query_posts())
 * leaves $post on another post. On the front end its wp_reset_postdata() fixes
 * that, but in the admin the main query has no post, so the reset does
 * nothing. The edit screen was then built from the other post, and "Update"
 * saved that post's title, slug, author and content over the edited page
 * (3.4.1 with Yoast SEO and page builder shortcodes).
 *
 * The globals are restored in finally, so also when the callback throws.
 * setup_postdata() runs first, so its the_post hooks see the original post
 * again; the saved values are then written back exactly as they were.
 *
 * @since 4.0.0
 * @param callable $fn      Callback.
 * @param mixed    ...$args Arguments for the callback.
 * @return mixed What the callback returns.
 */
function grmlt_render_isolated( callable $fn, ...$args ) {
	$names = array( 'post', 'wp_query', 'wp_the_query', 'id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' );
	$saved = array();
	foreach ( $names as $name ) {
		// A one element array keeps a null value apart from a global that was not set.
		$saved[ $name ] = array_key_exists( $name, $GLOBALS ) ? array( $GLOBALS[ $name ] ) : null;
	}

	$restore = static function () use ( $saved ) {
		foreach ( $saved as $name => $value ) {
			if ( null === $value ) {
				unset( $GLOBALS[ $name ] );
			} else {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- puts back core globals saved above.
				$GLOBALS[ $name ] = $value[0];
			}
		}
	};

	grmlt_render_depth( 1 );
	try {
		return $fn( ...$args );
	} finally {
		grmlt_render_depth( -1 );
		$restore();
		if ( null !== $saved['post'] && $saved['post'][0] instanceof WP_Post ) {
			setup_postdata( $saved['post'][0] );
			$restore();
		}
	}
}

/**
 * Renders blocks (and, when allowed, shortcodes) for grmlt_extract_clean_text().
 * Always called through grmlt_render_isolated().
 *
 * @since 4.0.0
 * @param string $content           Content.
 * @param bool   $render_shortcodes Whether shortcodes may be executed.
 * @return string
 */
function grmlt_render_for_text( $content, $render_shortcodes ) {
	if ( function_exists( 'excerpt_remove_blocks' ) ) {
		$content = excerpt_remove_blocks( $content );
	}
	$content = (string) preg_replace( '/<!--\s*\/?wp:.*?-->/s', '', $content );

	if ( $render_shortcodes && function_exists( 'do_shortcode' ) ) {
		$content = do_shortcode( $content );
		$content = strip_shortcodes( $content );
	}
	return $content;
}

/**
 * Extracts plain text from post content that may contain page builder markup.
 *
 * @since 3.3.0
 * @since 4.0.0 Entities are decoded before tags are stripped, so decoded
 *              markup can never survive into the output (audit S1).
 * @since 4.0.0 Rendering runs through grmlt_render_isolated().
 * @param string    $content           Raw post_content.
 * @param int       $post_id           Optional post id for the grmlt_render_shortcodes_on_frontend filter.
 * @param bool|null $render_shortcodes Optional. Force whether shortcodes may be executed. Default null
 *                                     (back end requests render, front end requests do not).
 * @return string
 */
function grmlt_extract_clean_text( $content, $post_id = 0, $render_shortcodes = null ) {
	if ( empty( $content ) || ! is_string( $content ) ) {
		return '';
	}
	$forced = null !== $render_shortcodes;
	if ( ! $forced ) {
		$render_shortcodes = grmlt_is_backend_request();
	}
	if ( ! $render_shortcodes && ! $forced ) {
		/**
		 * Filters whether page builder shortcodes may be executed on the front end.
		 *
		 * @since 3.4.0
		 * @param bool $render  Default false.
		 * @param int  $post_id Post id or 0.
		 */
		$render_shortcodes = (bool) apply_filters( 'grmlt_render_shortcodes_on_frontend', false, (int) $post_id );
	}

	// Reached again from inside a shortcode that is being rendered: never execute shortcodes twice.
	if ( grmlt_render_depth() > 0 ) {
		$render_shortcodes = false;
	}
	$content = (string) grmlt_render_isolated( 'grmlt_render_for_text', $content, (bool) $render_shortcodes );

	$builder_patterns = array(
		'/\[\/?vc_[^\]]*\]/s',
		'/\[\/?et_[^\]]*\]/s',
		'/\[\/?fl_[^\]]*\]/s',
		'/\[\/?fusion_[^\]]*\]/s',
		'/\[\/?elementor[^\]]*\]/s',
		'/\[\/?cs_[^\]]*\]/s',
		'/\[\/?av_[^\]]*\]/s',
		'/\[\/?mk_[^\]]*\]/s',
		'/\[\/?bt_[^\]]*\]/s',
		'/\[\/?tatsu_[^\]]*\]/s',
		'/\[\/?porto_[^\]]*\]/s',
		'/\[\/?ux_[^\]]*\]/s',
	);
	$content = preg_replace( $builder_patterns, '', $content );
	$content = preg_replace( '/\[[^\]]+\]/', '', $content );

	// Decode first, then strip: an entity-encoded tag must not become live markup.
	$content = html_entity_decode( $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$content = wp_strip_all_tags( $content );

	$content = preg_replace( '/\s+/u', ' ', $content );
	return trim( (string) $content );
}

/**
 * Clean text of a post, including Elementor data; memoized per request.
 *
 * @since 3.3.0
 * @param int $post_id Post id.
 * @return string
 */
function grmlt_get_post_clean_text( $post_id ) {
	static $cache = array();

	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$cache_key = (int) $post->ID . ( grmlt_is_backend_request() ? '|b' : '|f' );
	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$content = $post->post_content;
	if ( grmlt_is_elementor_post( $post->ID ) ) {
		$elementor_content = grmlt_get_elementor_text( $post->ID );
		if ( ! empty( $elementor_content ) ) {
			$clean_elementor = grmlt_extract_clean_text( $elementor_content, $post->ID );
			$clean_regular   = grmlt_extract_clean_text( $content, $post->ID );
			if ( mb_strlen( $clean_elementor ) > mb_strlen( $clean_regular ) ) {
				$cache[ $cache_key ] = $clean_elementor;
				return $clean_elementor;
			}
		}
	}
	$cache[ $cache_key ] = grmlt_extract_clean_text( $content, $post->ID );
	return $cache[ $cache_key ];
}

/**
 * Whether a post was built with Elementor.
 *
 * @param int $post_id Post id.
 * @return bool
 */
function grmlt_is_elementor_post( $post_id ) {
	return ! empty( get_post_meta( $post_id, '_elementor_edit_mode', true ) );
}

/**
 * Text from Elementor's _elementor_data.
 *
 * @param int $post_id Post id.
 * @return string
 */
function grmlt_get_elementor_text( $post_id ) {
	$data = get_post_meta( $post_id, '_elementor_data', true );
	if ( empty( $data ) ) {
		return '';
	}
	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}
	if ( ! is_array( $data ) ) {
		return '';
	}
	$texts = array();
	grmlt_walk_elementor_data( $data, $texts );
	return implode( ' ', $texts );
}

/**
 * Walks Elementor data collecting text fields.
 *
 * @param array $elements Elements.
 * @param array $texts    Collected texts (by reference).
 */
function grmlt_walk_elementor_data( $elements, &$texts ) {
	if ( ! is_array( $elements ) ) {
		return;
	}
	$text_fields = array( 'editor', 'title', 'description', 'text', 'content', 'html', 'inner_text', 'item_description', 'tab_content', 'accordion_content', 'alert_description', 'testimonial_content', 'blockquote_content', 'caption' );
	foreach ( $elements as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}
		if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
			foreach ( $text_fields as $field ) {
				if ( ! empty( $element['settings'][ $field ] ) && is_string( $element['settings'][ $field ] ) ) {
					$texts[] = wp_strip_all_tags( $element['settings'][ $field ] );
				}
			}
			if ( isset( $element['settings']['tabs'] ) && is_array( $element['settings']['tabs'] ) ) {
				foreach ( $element['settings']['tabs'] as $tab ) {
					if ( ! empty( $tab['tab_content'] ) && is_string( $tab['tab_content'] ) ) {
						$texts[] = wp_strip_all_tags( $tab['tab_content'] );
					}
				}
			}
		}
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			grmlt_walk_elementor_data( $element['elements'], $texts );
		}
	}
}

/**
 * Yoast SEO: hand the editor analysis rendered text for page builder content.
 * Front end requests are passed through untouched (3.4.0). Called again
 * from inside a render of ours, it returns the content as it came (4.0.0).
 *
 * @param string $content Content.
 * @param object $post    Post.
 * @return string
 */
function grmlt_yoast_content_filter( $content, $post ) {
	if ( empty( $content ) || ! is_object( $post ) || grmlt_render_depth() > 0 ) {
		return $content;
	}
	$post_id = isset( $post->ID ) ? (int) $post->ID : 0;
	if ( ! grmlt_is_backend_request() && ! apply_filters( 'grmlt_render_shortcodes_on_frontend', false, $post_id ) ) {
		return $content;
	}
	$has_builder_content = preg_match( '/\[(vc_|et_|fl_|fusion_)/', $content ) || grmlt_is_elementor_post( $post_id );
	if ( $has_builder_content ) {
		$clean = grmlt_get_post_clean_text( $post_id );
		if ( ! empty( $clean ) ) {
			return $clean;
		}
	}
	return $content;
}

/**
 * Hooks the Yoast filter when Yoast is active.
 */
function grmlt_register_yoast_filter() {
	if ( defined( 'WPSEO_VERSION' ) || defined( 'WPSEO_PREMIUM_VERSION' ) ) {
		add_filter( 'wpseo_pre_analysis_post_content', 'grmlt_yoast_content_filter', 10, 2 );
	}
}
add_action( 'init', 'grmlt_register_yoast_filter', 20 );
