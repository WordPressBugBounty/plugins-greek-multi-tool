<?php
/**
 * Greek Date Localization functionality
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
 * Class for Greek date localization
 */
class GRMLT_Date_Localization {
    
    /**
     * Greek month names
     *
     * @var array
     */
    private $greek_months = array(
        1 => 'Ιανουαρίου',
        2 => 'Φεβρουαρίου',
        3 => 'Μαρτίου',
        4 => 'Απριλίου',
        5 => 'Μαΐου',
        6 => 'Ιουνίου',
        7 => 'Ιουλίου',
        8 => 'Αυγούστου',
        9 => 'Σεπτεμβρίου',
        10 => 'Οκτωβρίου',
        11 => 'Νοεμβρίου',
        12 => 'Δεκεμβρίου'
    );
    
    /**
     * Greek month names in nominative case
     *
     * @var array
     */
    private $greek_months_nom = array(
        1 => 'Ιανουάριος',
        2 => 'Φεβρουάριος',
        3 => 'Μάρτιος',
        4 => 'Απρίλιος',
        5 => 'Μάιος',
        6 => 'Ιούνιος',
        7 => 'Ιούλιος',
        8 => 'Αύγουστος',
        9 => 'Σεπτέμβριος',
        10 => 'Οκτώβριος',
        11 => 'Νοέμβριος',
        12 => 'Δεκέμβριος'
    );
    
    /**
     * Greek day names
     *
     * @var array
     */
    private $greek_days = array(
        'Monday' => 'Δευτέρα',
        'Tuesday' => 'Τρίτη',
        'Wednesday' => 'Τετάρτη',
        'Thursday' => 'Πέμπτη',
        'Friday' => 'Παρασκευή',
        'Saturday' => 'Σάββατο',
        'Sunday' => 'Κυριακή'
    );
    
    /**
     * Greek date formats
     *
     * @var array
     */
    private $greek_date_formats = array(
        'default' => 'j F Y',
        'full' => 'l, j F Y',
        'short' => 'j/n/Y',
        'with_time' => 'j F Y, H:i',
        'archive' => 'F Y'
    );
    
    /**
     * Initialize the class
     */
    public function __construct() {
        // Check if date localization is enabled
        if (get_option('grmlt_localize_dates', 'on') === 'on') {
            // Filter date display
            add_filter('the_date', array($this, 'localize_date'), 10, 4);
            add_filter('get_the_date', array($this, 'localize_get_the_date'), 10, 3);
            add_filter('the_time', array($this, 'localize_time'), 10, 2);
            add_filter('get_the_time', array($this, 'localize_get_the_time'), 10, 3);
            add_filter('get_the_archive_title', array($this, 'localize_archive_title'), 10, 1);
            add_filter('date_i18n', array($this, 'localize_date_i18n'), 10, 4);
            
            // Add settings
            add_action('admin_init', array($this, 'register_settings'));
        }
    }
    
    /**
     * Register date localization settings
     */
    public function register_settings() {
        register_setting(
            'grmlt_settings',
            'grmlt_localize_dates',
            array(
                'type' => 'string',
                'description' => __('Enable Greek date localization', 'greek-multi-tool'),
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'on',
            )
        );
        
        register_setting(
            'grmlt_settings',
            'grmlt_date_format',
            array(
                'type' => 'string',
                'description' => __('Greek date format', 'greek-multi-tool'),
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'default',
            )
        );
        
        register_setting(
            'grmlt_settings',
            'grmlt_custom_date_format',
            array(
                'type' => 'string',
                'description' => __('Custom Greek date format', 'greek-multi-tool'),
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
        );
    }
    
    /**
     * Localize date display filter
     *
     * @param string $the_date The date string
     * @param string $format Format to display the date
     * @param string $before HTML output before the date
     * @param string $after HTML output after the date
     * @return string Localized date
     */
    public function localize_date($the_date, $format = '', $before = '', $after = '') {
        if (empty($format)) {
            $format = $this->get_selected_date_format();
        }
        
        $the_date = $this->greek_date(get_post()->post_date, $format);
        return $before . $the_date . $after;
    }
    
    /**
     * Localize get_the_date filter
     *
     * @param string $the_date The formatted date string
     * @param string $format Format to display the date
     * @param WP_Post|int $post The post object or ID
     * @return string Localized date
     */
    public function localize_get_the_date($the_date, $format = '', $post = null) {
        if (empty($format)) {
            $format = $this->get_selected_date_format();
        }
        
        $post = get_post($post);
        if (!$post) {
            return $the_date;
        }
        
        return $this->greek_date($post->post_date, $format);
    }
    
    /**
     * Localize the_time filter
     *
     * @param string $the_time The formatted time string
     * @param string $format Format to display the time
     * @return string Localized time
     */
    public function localize_time($the_time, $format = '') {
        // Check if format contains date elements
        if (strpos($format, 'F') !== false || strpos($format, 'M') !== false || 
            strpos($format, 'l') !== false || strpos($format, 'D') !== false) {
            $the_time = $this->greek_date(get_post()->post_date, $format);
        }
        
        return $the_time;
    }
    
    /**
     * Localize get_the_time filter
     *
     * @param string $the_time The formatted time string
     * @param string $format Format to display the time
     * @param WP_Post|int $post The post object or ID
     * @return string Localized time
     */
    public function localize_get_the_time($the_time, $format = '', $post = null) {
        // Check if format contains date elements
        if (strpos($format, 'F') !== false || strpos($format, 'M') !== false || 
            strpos($format, 'l') !== false || strpos($format, 'D') !== false) {
            
            $post = get_post($post);
            if (!$post) {
                return $the_time;
            }
            
            $the_time = $this->greek_date($post->post_date, $format);
        }
        
        return $the_time;
    }
    
    /**
     * Localize archive title
     *
     * @param string $title The archive title
     * @return string Localized archive title
     */
    public function localize_archive_title($title) {
        if (is_month()) {
            $month = get_the_date($this->greek_date_formats['archive']);
            $title = $month;
        } elseif (is_year()) {
            $year = get_the_date('Y');
            $title = $year;
        }
        
        return $title;
    }
    
    /**
     * Localize date_i18n function
     *
     * @param string $formatted_date The formatted date string
     * @param string $format Format to display the date
     * @param int $timestamp The timestamp to format
     * @param bool $gmt Whether to use GMT timezone
     * @return string Localized date
     */
    public function localize_date_i18n($formatted_date, $format, $timestamp, $gmt) {
        // Only process if contains month or day names
        if (strpos($format, 'F') !== false || strpos($format, 'M') !== false || 
            strpos($format, 'l') !== false || strpos($format, 'D') !== false) {
            
            $date = $gmt ? gmdate('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s', $timestamp);
            $formatted_date = $this->greek_date($date, $format);
        }
        
        return $formatted_date;
    }
    
    /**
 * Format a date with Greek month and day names
 *
 * @param string $mysql_date MySQL format date
 * @param string $format PHP date format
 * @return string Formatted date
 */
public function greek_date($mysql_date, $format) {
    // Get timestamp from MySQL date
    $timestamp = strtotime($mysql_date);
    if ($timestamp === false) {
        // Handle invalid date
        return date($format);
    }
    
    // Process the format one character at a time
    $result = '';
    $length = strlen($format);
    
    for ($i = 0; $i < $length; $i++) {
        $char = $format[$i];
        
        switch ($char) {
            case 'F': // Full month name
                $month_num = (int)date('n', $timestamp);
                $result .= $this->greek_months[$month_num];
                break;
                
            case 'M': // Short month name
                $month_num = (int)date('n', $timestamp);
                $result .= mb_substr($this->greek_months[$month_num], 0, 3, 'UTF-8');
                break;
                
            case 'l': // Full day name
                $day_of_week = date('l', $timestamp);
                $result .= $this->greek_days[$day_of_week];
                break;
                
            case 'D': // Short day name
                $day_of_week = date('l', $timestamp);
                $result .= mb_substr($this->greek_days[$day_of_week], 0, 3, 'UTF-8');
                break;
                
            case '\\': // Escape character
                // Skip the backslash and add the next character literally
                $i++;
                if ($i < $length) {
                    $result .= $format[$i];
                }
                break;
                
            default:
                // For all other format characters, use standard date()
                $result .= date($char, $timestamp);
                break;
        }
    }
    
    return $result;
}
    
    /**
     * Get selected date format from settings
     *
     * @return string Date format
     */
    private function get_selected_date_format() {
        $format_key = get_option('grmlt_date_format', 'default');
        
        if ($format_key === 'custom') {
            $custom_format = get_option('grmlt_custom_date_format', '');
            if (!empty($custom_format)) {
                return $custom_format;
            }
        }
        
        return isset($this->greek_date_formats[$format_key]) ? 
               $this->greek_date_formats[$format_key] : 
               $this->greek_date_formats['default'];
    }
}

// Initialize the date localization
$grmlt_date_localization = new GRMLT_Date_Localization();

/**
 * Add date localization settings to plugin settings page
 */
function grmlt_add_date_localization_settings_tab() {
    $tab_path = plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/settings-page/date-localization-tab.php';

    if ( file_exists( $tab_path ) ) {
        include $tab_path;
    } else {
        echo '<p>' . esc_html__( 'Error: Date localization tab content file not found', 'greek-multi-tool' ) . '</p>';
    }
}

/**
 * Register date localization tab
 */
function grmlt_register_date_localization_tab() {
    add_filter('grmlt_settings_tabs', function($tabs) {
        $tabs['date_localization'] = __('Date Localization', 'greek-multi-tool');
        return $tabs;
    });
    
    add_action('grmlt_settings_tab_date_localization', 'grmlt_add_date_localization_settings_tab');
}
add_action('plugins_loaded', 'grmlt_register_date_localization_tab');

/**
 * Helper function to get a date formatted with Greek month and day names
 *
 * @param string $format Date format
 * @param int|string $date Date to format (timestamp or MySQL date)
 * @return string Formatted date
 */
function grmlt_format_greek_date($format = '', $date = '') {
    global $grmlt_date_localization;
    
    if (empty($date)) {
        $date = current_time('mysql');
    } elseif (is_numeric($date)) {
        $date = date('Y-m-d H:i:s', $date);
    }
    
    if (empty($format)) {
        // Get format from settings
        $format_key = get_option('grmlt_date_format', 'default');
        if ($format_key === 'custom') {
            $format = get_option('grmlt_custom_date_format', 'j F Y');
        } else {
            $formats = array(
                'default' => 'j F Y',
                'full' => 'l, j F Y',
                'short' => 'j/n/Y',
                'with_time' => 'j F Y, H:i',
                'archive' => 'F Y'
            );
            $format = isset($formats[$format_key]) ? $formats[$format_key] : 'j F Y';
        }
    }
    
    return $grmlt_date_localization->greek_date($date, $format);
}