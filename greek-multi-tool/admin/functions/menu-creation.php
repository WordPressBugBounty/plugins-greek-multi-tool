<?php
/**
 * WOOCOMMERCE
 */

// Check if the button was clicked
if (isset($_POST['create_woo_menu_button'])) {
    create_woocommerce_menu($_POST['name_of_woo_menu']);
}

function create_woocommerce_menu($name_of_menu) {
    // Fetch all product categories
    $args = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => 0, // Only fetch top-level categories
    );

    $top_level_categories = get_categories($args);

    // Create a new menu if it doesn't exist
    $menu_name = "$name_of_menu";
    $menu_exists = wp_get_nav_menu_object($menu_name);

    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = $menu_exists->term_id;
    }

    // Add top-level and child categories to the menu
    foreach ($top_level_categories as $category) {
        add_category_to_menu($menu_id, 0, $category);
    }
}

function add_category_to_menu($menu_id, $parent_menu_item_id, $category) {
    // Add the category to the menu
    $menu_item_data = array(
        'menu-item-object' => 'product_cat',
        'menu-item-parent-id' => $parent_menu_item_id,
        'menu-item-type' => 'taxonomy',
        'menu-item-object-id' => $category->term_id,
        'menu-item-title' => $category->name,
        'menu-item-url' => get_term_link($category),
        'menu-item-status' => 'publish',
    );

    $menu_item_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);

    // Fetch child categories of the current category
    $child_args = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'parent'     => $category->term_id,
    );

    $child_categories = get_categories($child_args);

    // Add child categories to the menu recursively
    foreach ($child_categories as $child_category) {
        add_category_to_menu($menu_id, $menu_item_id, $child_category);
    }
}

/**
 * POSTS
 */

// Check if the button was clicked
if (isset($_POST['create_posts_menu_button'])) {
    create_post_categories_menu($_POST['name_of_posts_menu']);
}

function create_post_categories_menu($name_of_menu) {
    // Fetch all top-level post categories
    $args = array(
        'taxonomy'   => 'category',
        'hide_empty' => false,
        'parent'     => 0, // Only fetch top-level categories
    );

    $top_level_categories = get_categories($args);

    // Create a new menu if it doesn't exist
    $menu_name = "$name_of_menu";
    $menu_exists = wp_get_nav_menu_object($menu_name);

    if (!$menu_exists) {
        $menu_id = wp_create_nav_menu($menu_name);
    } else {
        $menu_id = $menu_exists->term_id;
    }

    // Add top-level and child categories to the menu
    foreach ($top_level_categories as $category) {
        add_post_category_to_menu($menu_id, 0, $category);
    }
}

function add_post_category_to_menu($menu_id, $parent_menu_item_id, $category) {
    // Add the category to the menu
    $menu_item_data = array(
        'menu-item-object' => 'category',
        'menu-item-parent-id' => $parent_menu_item_id,
        'menu-item-type' => 'taxonomy',
        'menu-item-object-id' => $category->term_id,
        'menu-item-title' => $category->name,
        'menu-item-url' => get_term_link($category),
        'menu-item-status' => 'publish',
    );

    $menu_item_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_data);

    // Fetch child categories of the current category
    $child_args = array(
        'taxonomy'   => 'category',
        'hide_empty' => false,
        'parent'     => $category->term_id,
    );

    $child_categories = get_categories($child_args);

    // Add child categories to the menu recursively
    foreach ($child_categories as $child_category) {
        add_post_category_to_menu($menu_id, $menu_item_id, $child_category);
    }
}
