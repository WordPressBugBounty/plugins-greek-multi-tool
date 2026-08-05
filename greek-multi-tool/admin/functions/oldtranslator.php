<?php
/**
 * Bulk conversion of existing (old) permalinks to Latin slugs.
 *
 * @since   1.0.0
 * @since   3.4.1 Restricted to a post type whitelist, added dry-run preview and
 *                undo state. See grmlt_get_convertible_post_types() for why.
 * @package Grmlt_Plugin
 */

function grmlt_old_sanitizer($text) {
    $expressions = array(

        '/[αάΑΆ]/u'   => 'a',
        '/[βΒ]/u'     => 'v',
        '/[γΓ]/u'     => 'g',
        '/[δΔ]/u'     => 'd',
        '/[εέΕΈ]/u'   => 'e',
        '/[ζΖ]/u'     => 'z',
        '/[ηήΗΉ]/u'   => 'i',
        '/[θΘ]/u'     => 'th',
        '/[ιίϊΙΊΪ]/u' => 'i',
        '/[κΚ]/u'     => 'k',
        '/[λΛ]/u'     => 'l',
        '/[μΜ]/u'     => 'm',
        '/[νΝ]/u'     => 'n',
        '/[ξΞ]/u'     => 'x',
        '/[οόΟΌ]/u'   => 'o',
        '/[πΠ]/u'     => 'p',
        '/[ρΡ]/u'     => 'r',
        '/[σςΣ]/u'    => 's',
        '/[τΤ]/u'     => 't',
        '/[υύϋΥΎΫ]/u' => 'y',
        '/[φΦ]/iu'    => 'f',
        '/[χΧ]/u'     => 'ch',
        '/[ψΨ]/u'     => 'ps',
        '/[ωώ]/iu'    => 'o',
        '/[ ]/'       => '-',
        '/[--]/'      => '-',
        '/[.]/'       => '',

    );

    // Stop Words functionality
    $stop_words = get_option( 'grmlt_stwords' );
    if ( isset( $stop_words ) &&  $stop_words != '') {
        $stop_words = explode( ',' , $stop_words );
        $text = str_replace( $stop_words, '', $text );
    }

    $text = preg_replace( array_keys($expressions), array_values($expressions), $text );

    // Remove one letter words
    if ( get_option( 'grmlt_one_letter_words' ) == 1 ) {
        $text = preg_replace('/\s+\D{1}(?!\S)|(?<!\S)\D{1}\s+/', '', $text);
    }

    // Remove two letter words
    if ( get_option( 'grmlt_two_letter_words' ) == 1 ) {
        $text = preg_replace('/\s+\D{2}(?!\S)|(?<!\S)\D{2}\s+/', '', $text);
    }

    return $text;

}

/**
 * Post types the bulk converter is allowed to rewrite.
 *
 * CRITICAL - this is a whitelist on purpose. Before 3.4.1 the converter ran a
 * raw query against wp_posts with no post_type condition at all, which swept in
 * every internal post type registered by any plugin.
 *
 * The worst case was Advanced Custom Fields. ACF stores each field as a post of
 * type acf-field whose post_name IS the field key (field_5f8a1b2c...), and each
 * field group as acf-field-group whose post_name is the group key. Both are
 * post_status 'publish', and both keys contain an underscore, so they matched
 * the old query on every site. The converter then overwrote the key with a slug
 * built from the post_title - the field's human label - permanently detaching
 * every field from the postmeta rows that reference it by key. get_field()
 * returns nothing afterwards and the original keys are unrecoverable.
 *
 * Restricting to 'public' => true post types excludes ACF, revisions, menu
 * items, templates, reusable blocks and every other internal type in one go,
 * because none of them are publicly queryable and none of them have a slug that
 * a visitor ever sees.
 *
 * @since 3.4.1
 * @return string[] Post type names that may be converted.
 */
function grmlt_get_convertible_post_types() {

	$types = get_post_types( array( 'public' => true ), 'names' );

	// Belt and braces: never touch ACF internals even if something registers
	// them as public, and never touch revisions.
	$never = array(
		'acf-field',
		'acf-field-group',
		'acf',
		'acf-post-type',
		'acf-taxonomy',
		'acf-ui-options-page',
		'revision',
	);

	$types = array_diff( (array) $types, $never );

	/**
	 * Filters the post types the old permalink converter is allowed to rewrite.
	 *
	 * Only add a post type here if its slug is part of a public URL. Adding a
	 * post type whose post_name is used as an internal identifier will corrupt
	 * that plugin's data.
	 *
	 * @since 3.4.1
	 * @param string[] $types Post type names.
	 */
	$types = apply_filters( 'grmlt_convertible_post_types', array_values( $types ) );

	return array_values( array_filter( (array) $types, 'is_string' ) );

}

/**
 * Taxonomies the bulk converter is allowed to rewrite term slugs in.
 *
 * Same reasoning as grmlt_get_convertible_post_types(): only a slug that is
 * part of a public URL should ever be rewritten. Internal taxonomies
 * (nav_menu, link_category, post_format, and any plugin-private taxonomy that
 * uses the slug as an identifier) must never be touched.
 *
 * @since 3.4.1
 * @return string[] Taxonomy names whose term slugs may be converted.
 */
function grmlt_get_convertible_taxonomies() {

	$taxonomies = get_taxonomies( array( 'public' => true ), 'names' );

	/**
	 * Filters the taxonomies the old permalink converter is allowed to rewrite.
	 *
	 * Only add a taxonomy here if its term slugs appear in public URLs.
	 *
	 * @since 3.4.1
	 * @param string[] $taxonomies Taxonomy names.
	 */
	$taxonomies = apply_filters( 'grmlt_convertible_taxonomies', array_values( (array) $taxonomies ) );

	return array_values( array_filter( (array) $taxonomies, 'is_string' ) );

}

/**
 * Build the list of slug changes the converter would make.
 *
 * Performs no writes, so it can back both the preview and the real run.
 *
 * @since 3.4.1
 * @return array{posts: array, terms: array} Planned changes.
 */
function grmlt_collect_permalink_changes() {

	global $wpdb;

	$plan = array( 'posts' => array(), 'terms' => array() );

	$types = grmlt_get_convertible_post_types();

	// An empty post type list (possible via the grmlt_convertible_post_types
	// filter) only skips the posts query. Terms have their own whitelist below
	// and must still be collected.
	if ( ! empty( $types ) ) {

		$placeholders = implode( ', ', array_fill( 0, count( $types ), '%s' ) );

		// Note the underscore inside the character class: an underscore is a legal
		// WordPress slug character, not a leftover from a non-Latin title. Treating
		// it as "needs conversion" is what pulled ACF keys and ordinary slugs like
		// my_page into the result set before 3.4.1.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_name, post_title, post_type FROM {$wpdb->posts}
				 WHERE post_name REGEXP '[^A-Za-z0-9_-]+'
				   AND post_status IN ('publish', 'future', 'private', 'inherit')
				   AND post_type IN ({$placeholders})",
				$types
			)
		);

		foreach ( (array) $posts as $post ) {
			$sanitized_name = preg_replace( '/[^A-Za-z0-9-]+/', '', grmlt_old_sanitizer( $post->post_title ) );
			$sanitized_name = trim( $sanitized_name, '-' );

			// Never write an empty slug: a post with no post_name loses its
			// permalink entirely. Titles made up only of stop words, or of
			// characters the map does not cover, can reduce to nothing.
			if ( '' === $sanitized_name || $post->post_name === $sanitized_name ) {
				continue;
			}

			$plan['posts'][] = array(
				'ID'        => absint( $post->ID ),
				'post_type' => $post->post_type,
				'title'     => $post->post_title,
				'from'      => $post->post_name,
				'to'        => $sanitized_name,
			);
		}
	}

	$taxonomies = grmlt_get_convertible_taxonomies();

	if ( empty( $taxonomies ) ) {
		return $plan;
	}

	$tax_placeholders = implode( ', ', array_fill( 0, count( $taxonomies ), '%s' ) );

	// Join through term_taxonomy so that terms belonging only to internal
	// taxonomies are never rewritten. DISTINCT because a term row can be
	// referenced by more than one taxonomy.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$terms = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT DISTINCT t.term_id, t.slug FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			 WHERE t.slug REGEXP '[^A-Za-z0-9_-]+'
			   AND tt.taxonomy IN ({$tax_placeholders})",
			$taxonomies
		)
	);

	foreach ( (array) $terms as $term ) {
		$sanitized_slug = trim( grmlt_old_sanitizer( urldecode( $term->slug ) ), '-' );

		if ( '' === $sanitized_slug || $term->slug === $sanitized_slug ) {
			continue;
		}

		$plan['terms'][] = array(
			'term_id' => absint( $term->term_id ),
			'from'    => $term->slug,
			'to'      => $sanitized_slug,
		);
	}

	return $plan;

}

/**
 * Translate all old permalinks.
 *
 * @since 1.0.0
 * @since 3.4.1 Added the $dry_run parameter and post type whitelisting.
 * @param bool $dry_run When true, returns the planned changes without writing.
 * @return array The changes made, or that would be made when $dry_run is true.
 */
function grmlt_trans_old_call( $dry_run = false ) {
    // Check if the user has administrative permissions
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'greek-multi-tool'));
    }

    global $wpdb;

    $plan = grmlt_collect_permalink_changes();

    if ( $dry_run ) {
        return $plan;
    }

    if ( empty( $plan['posts'] ) && empty( $plan['terms'] ) ) {
        // Nothing to convert. Return WITHOUT touching the undo option: after a
        // successful run every slug is Latin, so a re-submitted CONVERT form
        // (browser refresh - the nonce is still valid) reaches this point and
        // would otherwise overwrite the just-written snapshot with an empty
        // one, destroying the only record of the pre-conversion term slugs.
        return $plan;
    }

    // Undo state, written before the first change. Raw slug writes bypass
    // wp_update_post, so without this there is no way back short of a backup.
    update_option( 'grmlt_last_conversion_undo', array(
        'time'  => time(),
        'posts' => $plan['posts'],
        'terms' => $plan['terms'],
    ), false );

    foreach ( $plan['posts'] as $change ) {
        $wpdb->update(
            $wpdb->posts,
            array('post_name' => $change['to']),
            array('ID' => $change['ID'])
        );
        clean_post_cache($change['ID']);

        // Give WordPress its own way back: wp_old_slug_redirect() serves the
        // previous URL from this meta. The raw UPDATE above cannot write it.
        add_post_meta( $change['ID'], '_wp_old_slug', $change['from'] );

        // Create 301 redirect for non-attachment post types only.
        // Attachment URLs are served from /wp-content/uploads/ and don't use slugs in the same way.
        $red_option = get_option('grmlt_redirect');
        if ($red_option == 1 && $change['post_type'] !== 'attachment') {
            // Build the OLD url from the slug that was actually in use, not
            // from the title: the title was never part of the permalink.
            $old_permalink = esc_url_raw( get_site_url() . '/' . rawurldecode( $change['from'] ) . '/' );
            $new_permalink = esc_url_raw( get_site_url() . '/' . $change['to'] . '/' );

            // Insert into database with proper sanitization
            $wpdb->insert(
                $wpdb->prefix . 'grmlt',
                array(
                    'post_id' => $change['ID'],
                    'redirect_type' => '301',
                    'old_permalink' => $old_permalink,
                    'new_permalink' => $new_permalink,
                )
            );
        }
    }

    foreach ( $plan['terms'] as $change ) {
        $wpdb->update(
            $wpdb->terms,
            array('slug' => $change['to']),
            array('term_id' => $change['term_id'])
        );
        clean_term_cache( $change['term_id'] );
    }

    return $plan;
}

// Check user permissions and nonce before execution.
if (
	isset( $_POST['oldpermalinks'] )
	&& current_user_can( 'manage_options' )
	&& isset( $_POST['grmlt_convert_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['grmlt_convert_nonce'] ) ), 'grmlt_convert_old_permalinks' )
) {
	// A preview run writes nothing; the result is rendered by the settings page.
	$GLOBALS['grmlt_conversion_result'] = grmlt_trans_old_call( isset( $_POST['grmlt_preview'] ) );
	$GLOBALS['grmlt_conversion_was_preview'] = isset( $_POST['grmlt_preview'] );
}
