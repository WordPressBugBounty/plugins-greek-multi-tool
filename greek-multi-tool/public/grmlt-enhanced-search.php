<?php
/**
 * Enhanced Greek Search functionality
 *
 * @link       https://bigdrop.gr
 * @since      3.0.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/public
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main class for Enhanced Greek Search
 */
class GRMLT_Enhanced_Search {
    /**
     * Initialize the class
     */
    public function __construct() {
        // Check if enhanced search is enabled
        if (get_option('grmlt_enhance_search', 'on') !== 'on') {
            return;
        }

        // Add filters to modify search functionality
        add_filter('posts_search', array($this, 'modify_search_query'), 10, 2);
        
        // If accent insensitive search is enabled, add the relevant filters
        if (get_option('grmlt_accent_insensitive_search', 'on') === 'on') {
            add_filter('get_search_query', array($this, 'normalize_search_term'));
            add_filter('posts_where', array($this, 'modify_search_where'), 10, 2);
            add_filter('posts_join', array($this, 'modify_search_join'), 10, 2);
            add_filter('posts_distinct', array($this, 'modify_search_distinct'), 10, 2);
        }
    }

    /**
     * Remove accents and normalize Greek characters for comparison
     * 
     * @param string $text The text to normalize
     * @return string Normalized text
     */
    public function normalize_greek_text($text) {
        if (empty($text)) {
            return $text;
        }

        // Convert text to lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // Replace accented Greek characters with non-accented versions
        $accented = array('ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ', 'ΐ', 'ϊ', 'ϋ', 'ΰ');
        $non_accented = array('α', 'ε', 'η', 'ι', 'ο', 'υ', 'ω', 'ι', 'ι', 'υ', 'υ');
        
        $text = str_replace($accented, $non_accented, $text);
        
        return $text;
    }

    /**
     * Normalize search term by removing accents
     * 
     * @param string $query The search query
     * @return string Normalized search query
     */
    public function normalize_search_term($query) {
        return $this->normalize_greek_text($query);
    }

    /**
     * Modify the search query to include accent-insensitive matches
     * 
     * @param string $search The WHERE clause of the query
     * @param WP_Query $wp_query The WP_Query instance
     * @return string Modified WHERE clause
     */
    public function modify_search_query($search, $wp_query) {
        if (!$wp_query->is_search() || empty($wp_query->query_vars['s'])) {
            return $search;
        }

        global $wpdb;
        
        // Get search terms
        $search_terms = $this->get_search_terms($wp_query->query_vars['s']);
        
        if (empty($search_terms)) {
            return $search;
        }
        
        // This is a flag to indicate we're modifying the search
        $wp_query->query_vars['grmlt_enhanced_search'] = true;
        
        return $search;
    }

    /**
     * Get search terms from a search string
     * 
     * @param string $search_string The search string
     * @return array Array of search terms
     */
    private function get_search_terms($search_string) {
        $search_string = $this->normalize_greek_text($search_string);
        
        // Split the search string into terms
        $terms = explode(' ', $search_string);
        $terms = array_filter($terms, function($term) {
            return strlen($term) > 1; // Filter out terms that are too short
        });
        
        return $terms;
    }

    /**
     * Modify the WHERE clause for accent-insensitive search
     * 
     * @param string $where The WHERE clause
     * @param WP_Query $wp_query The WP_Query instance
     * @return string Modified WHERE clause
     */
    public function modify_search_where($where, $wp_query) {
        // Only process if it's a search and our flag is set
        if (!$wp_query->is_search() || empty($wp_query->query_vars['s']) || 
            !isset($wp_query->query_vars['grmlt_enhanced_search'])) {
            return $where;
        }

        global $wpdb;
        
        // Get post types to search
        $post_types = get_option('grmlt_search_post_types', array('post', 'page', 'product'));
        if (empty($post_types)) {
            $post_types = array('post', 'page', 'product');
        }
        
        $post_type_conditions = array();
        foreach ($post_types as $post_type) {
            $post_type_conditions[] = $wpdb->prepare("post_type = %s", $post_type);
        }
        $post_type_sql = "(" . implode(" OR ", $post_type_conditions) . ")";
        
        // Get search terms
        $search_terms = $this->get_search_terms($wp_query->query_vars['s']);
        
        if (empty($search_terms)) {
            return $where;
        }
        
        // Build the WHERE clause
        $search_clauses = array();
        
        foreach ($search_terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            
            // Create accent-insensitive search conditions
            // Using LOWER() and REPLACE() functions to normalize Greek text
            $title_search = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                          "$wpdb->posts.post_title, " .
                          "'ά', 'α'), 'έ', 'ε'), 'ή', 'η'), 'ί', 'ι'), 'ό', 'ο'), 'ύ', 'υ'), 'ώ', 'ω'), 'ΐ', 'ι'), 'ϊ', 'ι'), 'ϋ', 'υ'), 'ΰ', 'υ')) LIKE %s";
            
            $content_search = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                            "$wpdb->posts.post_content, " .
                            "'ά', 'α'), 'έ', 'ε'), 'ή', 'η'), 'ί', 'ι'), 'ό', 'ο'), 'ύ', 'υ'), 'ώ', 'ω'), 'ΐ', 'ι'), 'ϊ', 'ι'), 'ϋ', 'υ'), 'ΰ', 'υ')) LIKE %s";
            
            $excerpt_search = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                            "$wpdb->posts.post_excerpt, " .
                            "'ά', 'α'), 'έ', 'ε'), 'ή', 'η'), 'ί', 'ι'), 'ό', 'ο'), 'ύ', 'υ'), 'ώ', 'ω'), 'ΐ', 'ι'), 'ϊ', 'ι'), 'ϋ', 'υ'), 'ΰ', 'υ')) LIKE %s";
            
            // Meta search
            $meta_search = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                         "grmlt_pm.meta_value, " .
                         "'ά', 'α'), 'έ', 'ε'), 'ή', 'η'), 'ί', 'ι'), 'ό', 'ο'), 'ύ', 'υ'), 'ώ', 'ω'), 'ΐ', 'ι'), 'ϊ', 'ι'), 'ϋ', 'υ'), 'ΰ', 'υ')) LIKE %s";
            
            $search_clauses[] = $wpdb->prepare(
                "($title_search OR $content_search OR $excerpt_search OR $meta_search)",
                $like, $like, $like, $like
            );
        }
        
        // Combine all search terms with OR
        $search_clause = "(" . implode(" OR ", $search_clauses) . ")";
        
        // Combine everything
        $new_where = " AND $post_type_sql AND $search_clause ";
        
        // Replace the old WHERE clause with our new one
        $where = preg_replace('/AND \(\([^\)]+\) OR \([^\)]+\)\)/', $new_where, $where);
        
        if ($where === null) {
            // If the regex replacement failed, just append our clause
            $where .= $new_where;
        }
        
        return $where;
    }

    /**
     * Modify the JOIN clause to include postmeta in the search
     * 
     * @param string $join The JOIN clause
     * @param WP_Query $wp_query The WP_Query instance
     * @return string Modified JOIN clause
     */
    public function modify_search_join($join, $wp_query) {
        // Only process if it's a search and our flag is set
        if (!$wp_query->is_search() || empty($wp_query->query_vars['s']) || 
            !isset($wp_query->query_vars['grmlt_enhanced_search'])) {
            return $join;
        }

        global $wpdb;
        
        // Join with postmeta to search in meta values as well
        if (strpos($join, 'grmlt_pm') === false) {
            $join .= " LEFT JOIN $wpdb->postmeta AS grmlt_pm ON ($wpdb->posts.ID = grmlt_pm.post_id AND grmlt_pm.meta_key IN ('_sku', '_title', '_variation_description'))";
        }
        
        return $join;
    }

    /**
     * Add DISTINCT to prevent duplicate results
     * 
     * @param string $distinct The DISTINCT part of the query
     * @param WP_Query $wp_query The WP_Query instance
     * @return string Modified DISTINCT part
     */
    public function modify_search_distinct($distinct, $wp_query) {
        // Only process if it's a search and our flag is set
        if (!$wp_query->is_search() || empty($wp_query->query_vars['s']) || 
            !isset($wp_query->query_vars['grmlt_enhanced_search'])) {
            return $distinct;
        }

        return "DISTINCT";
    }

    /**
     * Alternative approach with pre_get_posts
     * This approach modifies the search query before it's executed
     * 
     * @param WP_Query $query The WP_Query instance
     */
    public function pre_get_posts_search($query) {
        // Only modify search queries on the frontend
        if (!is_admin() && $query->is_search() && $query->is_main_query()) {
            
            // Get original search term
            $original_search = $query->get('s');
            
            // If the search term contains Greek characters
            if (preg_match('/\p{Greek}/u', $original_search)) {
                
                // Create both accented and unaccented versions
                $normalized_search = $this->normalize_greek_text($original_search);
                
                // Use a meta query to search in product attributes and other meta
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_sku',
                        'value' => $normalized_search,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => '_title',
                        'value' => $normalized_search,
                        'compare' => 'LIKE'
                    ),
                    array(
                        'key' => '_variation_description',
                        'value' => $normalized_search,
                        'compare' => 'LIKE'
                    )
                );
                
                // Set the meta query
                $query->set('meta_query', $meta_query);
                
                // Also search in taxonomy terms
                $tax_query = array(
                    'relation' => 'OR',
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'name',
                        'terms' => $normalized_search,
                        'operator' => 'LIKE'
                    ),
                    array(
                        'taxonomy' => 'product_tag',
                        'field' => 'name',
                        'terms' => $normalized_search,
                        'operator' => 'LIKE'
                    )
                );
                
                // Set the tax query
                $query->set('tax_query', $tax_query);
            }
        }
    }
}

// Initialize the enhanced search
$grmlt_enhanced_search = new GRMLT_Enhanced_Search();

/**
 * Register settings for enhanced search
 */
function grmlt_register_enhanced_search_settings() {
    // Register setting for enabling/disabling enhanced search
    register_setting(
        'grmlt_settings',
        'grmlt_enhance_search',
        array(
            'type' => 'string',
            'description' => __('Enable enhanced Greek search', 'greek-multi-tool'),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'on',
        )
    );
    
    // Register setting for enabling/disabling accent-insensitive search
    register_setting(
        'grmlt_settings',
        'grmlt_accent_insensitive_search',
        array(
            'type' => 'string',
            'description' => __('Enable accent-insensitive Greek search', 'greek-multi-tool'),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'on',
        )
    );
    
    // Register setting for post types to include in search
    register_setting(
        'grmlt_settings',
        'grmlt_search_post_types',
        array(
            'type' => 'array',
            'description' => __('Post types to include in enhanced search', 'greek-multi-tool'),
            'sanitize_callback' => function($value) {
                if (empty($value)) {
                    return array('post', 'page', 'product');
                }
                return array_map('sanitize_text_field', $value);
            },
            'default' => array('post', 'page', 'product'),
        )
    );
}

/**
 * Add Search tab to plugin settings page
 */
function grmlt_add_search_tab($tabs) {
    $tabs['search'] = __('Search', 'greek-multi-tool');
    return $tabs;
}
add_filter('grmlt_settings_tabs', 'grmlt_add_search_tab');

/**
 * Display Search settings tab content
 */
function grmlt_display_search_tab_content() {
    include plugin_dir_path(dirname(__FILE__)) . 'admin/partials/settings-page/search-tab.php';
}
add_action('grmlt_settings_tab_search', 'grmlt_display_search_tab_content');

/**
 * Save search settings via AJAX
 */
function grmlt_ajax_save_search_settings() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'grmlt_search_settings_nonce')) {
        wp_send_json_error(__('Security check failed', 'greek-multi-tool'));
    }
    
    // Check user permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Permission denied', 'greek-multi-tool'));
    }
    
    // Save enable/disable setting for enhanced search
    $enable_search = isset($_POST['enable_search']) ? sanitize_text_field($_POST['enable_search']) : 'off';
    update_option('grmlt_enhance_search', $enable_search);
    
    // Save enable/disable setting for accent-insensitive search
    $accent_insensitive = isset($_POST['accent_insensitive']) ? sanitize_text_field($_POST['accent_insensitive']) : 'off';
    update_option('grmlt_accent_insensitive_search', $accent_insensitive);
    
    // Save post types setting
    $post_types = isset($_POST['post_types']) ? $_POST['post_types'] : array();
    if (!empty($post_types)) {
        $post_types = array_map('sanitize_text_field', $post_types);
    } else {
        $post_types = array('post', 'page', 'product');
    }
    update_option('grmlt_search_post_types', $post_types);
    
    wp_send_json_success(__('Settings saved successfully', 'greek-multi-tool'));
}
add_action('admin_init', 'grmlt_register_enhanced_search_settings');
add_action('wp_ajax_grmlt_save_search_settings', 'grmlt_ajax_save_search_settings');