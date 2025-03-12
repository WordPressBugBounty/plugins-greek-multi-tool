<?php
/**
 * Enhanced Greek Search functionality
 *
 * @link       https://bigdrop.gr
 * @since      2.4.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/public
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class for enhancing WordPress search for Greek content
 */
class GRMLT_Enhanced_Search {
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Check if search enhancement is enabled
        if (get_option('grmlt_enhance_search', 'on') === 'on') {
            // Filter search query
            add_filter('posts_search', array($this, 'enhance_search_query'), 10, 2);
            
            // Add settings
            add_action('admin_init', array($this, 'register_settings'));
        }
    }
    
    /**
     * Register search enhancement settings
     */
    public function register_settings() {
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
        
        register_setting(
            'grmlt_settings',
            'grmlt_search_post_types',
            array(
                'type' => 'array',
                'description' => __('Post types to include in search', 'greek-multi-tool'),
                'sanitize_callback' => array($this, 'sanitize_post_types'),
                'default' => array('post', 'page'),
            )
        );
    }
    
    /**
     * Sanitize post types array
     *
     * @param array $post_types Post types array
     * @return array Sanitized post types
     */
    public function sanitize_post_types($post_types) {
        if (!is_array($post_types)) {
            return array('post', 'page');
        }
        
        return array_map('sanitize_text_field', $post_types);
    }
    
    /**
     * Enhance search query for Greek text
     *
     * @param string $search Search SQL
     * @param WP_Query $wp_query WordPress query object
     * @return string Modified search SQL
     */
    public function enhance_search_query($search, $wp_query) {
        global $wpdb;
        
        // Only process main search query
        if (empty($search) || !$wp_query->is_search() || !$wp_query->is_main_query()) {
            return $search;
        }
        
        $search_term = $wp_query->query_vars['s'];
        
        // No need to process if not Greek
        if (!$this->contains_greek($search_term)) {
            return $search;
        }
        
        // Get normalized variants of the search term
        $terms = $this->get_search_term_variants($search_term);
        
        // Build custom search conditions
        $search_conditions = array();
        $relevance_clauses = array();
        
        // Get selected post types
        $post_types = get_option('grmlt_search_post_types', array('post', 'page'));
        $post_type_clause = '';
        
        if (!empty($post_types) && is_array($post_types)) {
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $post_type_clause = $wpdb->prepare("AND $wpdb->posts.post_type IN ($placeholders)", $post_types);
        }
        
        foreach ($terms as $term) {
            $like = '%' . $wpdb->esc_like($term) . '%';
            
            // Search in post title
            $search_conditions[] = $wpdb->prepare("($wpdb->posts.post_title LIKE %s)", $like);
            // With higher relevance for exact matches
            $relevance_clauses[] = $wpdb->prepare("(CASE WHEN $wpdb->posts.post_title LIKE %s THEN 5 ELSE 0 END)", $like);
            
            // Search in post content
            $search_conditions[] = $wpdb->prepare("($wpdb->posts.post_content LIKE %s)", $like);
            // With medium relevance
            $relevance_clauses[] = $wpdb->prepare("(CASE WHEN $wpdb->posts.post_content LIKE %s THEN 2 ELSE 0 END)", $like);
            
            // Search in post excerpt
            $search_conditions[] = $wpdb->prepare("($wpdb->posts.post_excerpt LIKE %s)", $like);
            // With medium relevance
            $relevance_clauses[] = $wpdb->prepare("(CASE WHEN $wpdb->posts.post_excerpt LIKE %s THEN 2 ELSE 0 END)", $like);
        }
        
        // Combine conditions
        $search = " AND ((" . implode(') OR (', $search_conditions) . ")) 
            AND $wpdb->posts.post_status = 'publish'
            $post_type_clause";
        
        // Add ordering by relevance
        add_filter('posts_orderby', function($orderby, $query) use ($relevance_clauses, $wp_query) {
            if ($query->is_search() && $query->is_main_query()) {
                $relevance = implode(' + ', $relevance_clauses);
                return "($relevance) DESC, " . $orderby;
            }
            return $orderby;
        }, 10, 2);
        
        return $search;
    }
    
    /**
     * Check if string contains Greek characters
     *
     * @param string $string String to check
     * @return bool True if string contains Greek characters
     */
    private function contains_greek($string) {
        return (bool) preg_match('/\p{Greek}/u', $string);
    }
    
    /**
     * Get search term variants (with and without accents)
     *
     * @param string $search_term Original search term
     * @return array Array of search term variants
     */
    private function get_search_term_variants($search_term) {
        $variants = array($search_term);
        
        // Add lowercase variant
        $variants[] = mb_strtolower($search_term);
        
        // Add unaccented variant
        $normalized = $this->normalize_greek_text($search_term);
        if ($normalized !== $search_term) {
            $variants[] = $normalized;
            $variants[] = mb_strtolower($normalized);
        }
        
        // Handle common diphthongs
        $with_diphthongs = $this->handle_diphthongs($search_term);
        if ($with_diphthongs !== $search_term) {
            $variants[] = $with_diphthongs;
            $variants[] = mb_strtolower($with_diphthongs);
            
            // Also add normalized version of diphthong variant
            $normalized_diphthongs = $this->normalize_greek_text($with_diphthongs);
            if ($normalized_diphthongs !== $with_diphthongs) {
                $variants[] = $normalized_diphthongs;
                $variants[] = mb_strtolower($normalized_diphthongs);
            }
        }
        
        return array_unique($variants);
    }
    
    /**
     * Normalize Greek text by removing accents
     *
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    private function normalize_greek_text($text) {
        $replacements = array(
            'ά' => 'α', 'Ά' => 'Α',
            'έ' => 'ε', 'Έ' => 'Ε',
            'ή' => 'η', 'Ή' => 'Η',
            'ί' => 'ι', 'Ί' => 'Ι',
            'ό' => 'ο', 'Ό' => 'Ο',
            'ύ' => 'υ', 'Ύ' => 'Υ',
            'ώ' => 'ω', 'Ώ' => 'Ω',
            'ϊ' => 'ι', 'Ϊ' => 'Ι',
            'ϋ' => 'υ', 'Ϋ' => 'Υ',
            'ΐ' => 'ι',
            'ΰ' => 'υ'
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
    
    /**
     * Handle common Greek diphthongs in search terms
     *
     * @param string $text Text to process
     * @return string Processed text
     */
    private function handle_diphthongs($text) {
        $diphthongs = array(
            // Standard to phonetic
            'αι' => 'ε', 'αί' => 'έ', 'Αι' => 'Ε', 'Αί' => 'Έ',
            'ει' => 'ι', 'εί' => 'ί', 'Ει' => 'Ι', 'Εί' => 'Ί',
            'οι' => 'ι', 'οί' => 'ί', 'Οι' => 'Ι', 'Οί' => 'Ί',
            'υι' => 'ι', 'υί' => 'ί', 'Υι' => 'Ι', 'Υί' => 'Ί',
            'μπ' => 'b', 'Μπ' => 'B', 'ΜΠ' => 'B',
            'ντ' => 'd', 'Ντ' => 'D', 'ΝΤ' => 'D',
            'γκ' => 'g', 'Γκ' => 'G', 'ΓΚ' => 'G',
            
            // Phonetic to standard
            'β' => 'b', 'Β' => 'B',
            'γ' => 'g', 'Γ' => 'G',
            'δ' => 'd', 'Δ' => 'D'
        );
        
        return str_replace(array_keys($diphthongs), array_values($diphthongs), $text);
    }
}

// Initialize the enhanced search
$grmlt_enhanced_search = new GRMLT_Enhanced_Search();

/**
 * Add search settings to plugin settings page
 */
function grmlt_add_search_settings_tab() {
    $tab_path = WP_PLUGIN_DIR . '/greek-multi-tool/admin/partials/settings-page/search-tab.php';
    
    // Include the tab content file
    if (file_exists($tab_path)) {
        include $tab_path;
    } else {
        echo "<p>" . __('Error: Search tab content file not found', 'greek-multi-tool') . "</p>";
    }
}

/**
 * Register search settings tab
 */
function grmlt_register_search_tab() {
    add_filter('grmlt_settings_tabs', function($tabs) {
        $tabs['search'] = __('Search', 'greek-multi-tool');
        return $tabs;
    });
    
    add_action('grmlt_settings_tab_search', 'grmlt_add_search_settings_tab');
}
add_action('plugins_loaded', 'grmlt_register_search_tab');