<?php
/**
 * Menu builder: a navigation menu from a category hierarchy.
 *
 * @package Grmlt_Plugin
 * @since   4.0.0 Rewritten (previously menu-creation.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomies the menu builder can use.
 *
 * @return array<string, string> taxonomy => label.
 */
function grmlt_menu_builder_sources() {
	$sources = array( 'category' => __( 'Post categories', 'greek-multi-tool' ) );
	if ( taxonomy_exists( 'product_cat' ) ) {
		$sources['product_cat'] = __( 'Product categories (WooCommerce)', 'greek-multi-tool' );
	}
	return $sources;
}

/**
 * Creates (or refills) a menu with the hierarchy of a taxonomy.
 *
 * @param string $taxonomy Taxonomy.
 * @param string $name     Menu name.
 * @param bool   $replace  Replace the items of an existing menu with this name.
 * @return array{menu_id:int, name:string, count:int}|WP_Error
 */
function grmlt_create_category_menu( $taxonomy, $name, $replace = false ) {
	$sources = grmlt_menu_builder_sources();
	if ( ! isset( $sources[ $taxonomy ] ) ) {
		return new WP_Error( 'source', __( 'Unknown menu source.', 'greek-multi-tool' ) );
	}
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return new WP_Error( 'name', __( 'Please enter a name for the menu.', 'greek-multi-tool' ) );
	}

	$existing = null;
	foreach ( wp_get_nav_menus() as $menu ) {
		if ( grmlt_lower( $menu->name ) === grmlt_lower( $name ) ) {
			$existing = $menu;
			break;
		}
	}
	if ( $existing && ! $replace ) {
		return new WP_Error( 'exists', sprintf( /* translators: %s: menu name */ __( 'A menu named “%s” already exists. Tick “Replace the items of the existing menu” to refill it, or choose another name.', 'greek-multi-tool' ), $name ) );
	}
	if ( $existing ) {
		$menu_id = (int) $existing->term_id;
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
	} else {
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}
	}

	$count = 0;
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}
	foreach ( $terms as $term ) {
		grmlt_add_term_to_menu( $menu_id, 0, $term, $taxonomy, $count );
	}
	return array(
		'menu_id' => (int) $menu_id,
		'name'    => $existing ? $existing->name : $name,
		'count'   => $count,
	);
}

/**
 * Adds a term and its children to a menu.
 *
 * @param int     $menu_id   Menu id.
 * @param int     $parent_id Parent menu item id.
 * @param WP_Term $term      Term.
 * @param string  $taxonomy  Taxonomy.
 * @param int     $count     Items counter (by reference).
 */
function grmlt_add_term_to_menu( $menu_id, $parent_id, $term, $taxonomy, &$count ) {
	$item_id = wp_update_nav_menu_item(
		$menu_id,
		0,
		array(
			'menu-item-object'    => $taxonomy,
			'menu-item-object-id' => (int) $term->term_id,
			'menu-item-type'      => 'taxonomy',
			'menu-item-title'     => $term->name,
			'menu-item-parent-id' => (int) $parent_id,
			'menu-item-status'    => 'publish',
		)
	);
	if ( is_wp_error( $item_id ) ) {
		return;
	}
	++$count;
	$children = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'parent'     => (int) $term->term_id,
			'orderby'    => 'name',
		)
	);
	if ( is_wp_error( $children ) ) {
		return;
	}
	foreach ( $children as $child ) {
		grmlt_add_term_to_menu( $menu_id, $item_id, $child, $taxonomy, $count );
	}
}
