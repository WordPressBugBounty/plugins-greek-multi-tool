<?php
/**
 * Greek Text Analysis Tool for accent rules
 *
 * @link       https://bigdrop.gr
 * @since      2.4.0
 *
 * @package    Grmlt_Plugin
 * @subpackage Grmlt_Plugin/admin/functions
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to count syllables in Greek word
 * 
 * @param string $word Word to count syllables for
 * @return int Number of syllables
 */
function grmlt_count_syllables($word) {
    // Strip accents for counting
    $word = mb_strtolower(strtr($word, 
        array('ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω', 
              'ΐ' => 'ι', 'ϊ' => 'ι', 'ϋ' => 'υ', 'ΰ' => 'υ')));
    
    // Words that are treated as one syllable
    $one_syllable = array('μια', 'γεια', 'ποιος', 'γιος', 'βιος', 'δυο', 'πιο', 'για');
    
    if (in_array($word, $one_syllable)) {
        return 1;
    }
    
    // Words that are treated as two syllables
    $two_syllable = array('ολα', 'ενα', 'οταν', 'ολες', 'ολοι', 'ολη', 'εχω', 'εχει', 'ειμαι', 'ειναι', 'ηταν');
    
    if (in_array($word, $two_syllable)) {
        return 2;
    }
    
    // Common diphthongs counted as one vowel
    $diphthongs = array('αι', 'ει', 'οι', 'υι', 'αυ', 'ευ', 'ου');
    foreach ($diphthongs as $diphthong) {
        $word = str_replace($diphthong, 'α', $word);
    }
    
    // Count remaining vowels
    $count = preg_match_all('/[αεηιουω]/u', $word, $matches);
    return max(1, $count);
}

/**
 * Analyze Greek text for accent rule compliance
 *
 * @param string $text Text to analyze
 * @return array Analysis results
 */
function grmlt_analyze_text($text) {
    // Initialize results array
    $results = array(
        'stats' => array(
            'total_chars' => mb_strlen($text),
            'total_words' => count(preg_split('/\s+/u', trim($text))),
            'greek_chars' => 0,
            'accented_chars' => 0,
            'missing_accents' => array(),
            'unnecessary_accents' => array(),
            'capital_accent_issues' => array()
        ),
        'issues' => array()
    );
    
    // Words that should never have accents (regardless of context)
    $never_accented = array(
        'μου', 'σου', 'του', 'της', 'τον', 'την', 'το', 'τα', 'μας', 'σας', 'τους',
        'για', 'πιο', 'ναι', 'και', 'οι', 'θα', 'στην', 'αν', 'στη', 'στο', 'στον',
        'με', 'σε', 'τη', 'δε', 'ως', 'μη', 'μην', 'να', 'τις'
    );
    
    // Words with correct accent that should never be flagged
    $correctly_accented = array(
        'Όλα', 'όλα', 'Ένα', 'ένα', 'Όταν', 'όταν', 'Όλοι', 'όλοι', 'Όλες', 'όλες',
        'Είναι', 'είναι', 'Έχω', 'έχω', 'Έχει', 'έχει', 'Ήταν', 'ήταν', 'Έχουν', 'έχουν',
        'Όλη', 'όλη', 'Όλο', 'όλο', 'Ίδια', 'ίδια', 'Ίδιο', 'ίδιο', 'Ίδιος', 'ίδιος',
        'Άλλο', 'άλλο', 'Άλλα', 'άλλα', 'Άλλη', 'άλλη', 'Άλλος', 'άλλος', 'Άλλες', 'άλλες'
    );
    
    // Words that need special accent despite being monosyllabic
    $special_monosyllabic = array(
        'ή' => true,  // Disjunctive conjunction
        'πώς' => true, // Interrogative adverb (how)
        'πού' => true  // Interrogative adverb (where)
    );
    
    // Special cases for πού/πώς that need accents
    $special_pou_pws_phrases = array(
        'πού να', 'πού και πού', 'από πού', 'ως πού', 'αραιά και πού',
        'πώς και πώς', 'περιμένω πώς', 'πώς!'
    );
    
    // Analyze text word by word
    $words = preg_split('/\s+/u', $text);
    $word_index = 0;
    
    foreach ($words as $word) {
        // Skip empty words
        if (mb_strlen($word) == 0) {
            $word_index++;
            continue;
        }
        
        // Remove punctuation for analysis but keep original for display
        $clean_word = preg_replace('/[,\.;:!?\(\)\[\]{}"\'\-]/u', '', $word);
        if (mb_strlen($clean_word) == 0) {
            $word_index++;
            continue;
        }
        
        // If this is one of our specially handled correctly accented words, skip analysis
        if (in_array($clean_word, $correctly_accented)) {
            $word_index++;
            continue;
        }
        
        // Check if word is all caps
        $is_all_caps = (mb_strtoupper($clean_word) == $clean_word);
        
        // Character analysis for statistics
        $has_accent = false;
        $has_greek = false;
        
        for ($i = 0; $i < mb_strlen($clean_word); $i++) {
            $char = mb_substr($clean_word, $i, 1);
            
            // Check if character is Greek
            if (preg_match('/\p{Greek}/u', $char)) {
                $has_greek = true;
                $results['stats']['greek_chars']++;
                
                // Check for accented characters
                if (in_array($char, array('ά', 'έ', 'ή', 'ί', 'ό', 'ύ', 'ώ', 'ΐ', 'ϊ', 'ϋ', 'ΰ', 
                                           'Ά', 'Έ', 'Ή', 'Ί', 'Ό', 'Ύ', 'Ώ', 'Ϊ', 'Ϋ'))) {
                    $has_accent = true;
                    $results['stats']['accented_chars']++;
                    
                    // Check if it's an all-caps word that shouldn't have accents
                    // Exception: disjunctive "Ή" keeps accent even in all-caps
                    if ($is_all_caps && !(mb_strtolower($clean_word) === 'ή')) {
                        $results['stats']['capital_accent_issues'][] = array(
                            'word' => $word,
                            'word_index' => $word_index
                        );
                        
                        $results['issues'][] = sprintf(
                            __('Word "%s" is all-caps and should not have accents', 'greek-multi-tool'),
                            $word
                        );
                    }
                }
            }
        }
        
        // Skip non-Greek words
        if (!$has_greek) {
            $word_index++;
            continue;
        }
        
        // Get syllable count and lowercase version
        $syllable_count = grmlt_count_syllables($clean_word);
        $word_lower = mb_strtolower($clean_word);
        
        // Check if the word is in the list of never-accented words (regardless of capitalization)
        if (in_array($word_lower, $never_accented)) {
            // These words should never have accents
            if ($has_accent) {
                $results['stats']['unnecessary_accents'][] = array(
                    'word' => $word,
                    'word_index' => $word_index
                );
                
                $results['issues'][] = sprintf(
                    __('Word "%s" should not have an accent', 'greek-multi-tool'),
                    $word
                );
            }
            
            $word_index++;
            continue;
        }
        
        // Special case for capital letters at beginning of sentences or proper nouns
        $first_letter_capital = mb_strtoupper(mb_substr($clean_word, 0, 1)) === mb_substr($clean_word, 0, 1);
        $rest_lowercase = mb_strlen($clean_word) > 1 && 
                          mb_strtolower(mb_substr($clean_word, 1)) === mb_substr($clean_word, 1);
        $is_capitalized = $first_letter_capital && $rest_lowercase;
        
        // Check if the word is truncated (αποκοπή, έκθλιψη, αφαίρεση)
        $is_truncated = false;
        $lost_accent = false;
        
        // Check for words like μέσ', κάν', θα 'θελα
        if (mb_substr($word, -1) === "'" || 
            (mb_strlen($word) > 1 && mb_substr($word, 0, 1) === "'")) {
            $is_truncated = true;
            
            // If it's like 'θελα, the accent is lost
            if (mb_substr($word, 0, 1) === "'") {
                $lost_accent = true;
            }
        }
        
        // Case 1: Multi-syllable words (2 or more syllables)
        if ($syllable_count > 1 || ($is_truncated && !$lost_accent)) {
            // Multi-syllable words should have an accent unless they are all-caps
            if (!$has_accent && !$is_all_caps) {
                $results['stats']['missing_accents'][] = array(
                    'word' => $word,
                    'word_index' => $word_index
                );
                
                $results['issues'][] = sprintf(
                    __('Multi-syllable word "%s" should have an accent', 'greek-multi-tool'),
                    $word
                );
            }
        }
        // Case 2: Monosyllabic words
        else if ($syllable_count == 1 || ($is_truncated && $lost_accent)) {
            // Check if this is an exception that should be accented
            $should_have_accent = false;
            
            // The disjunctive conjunction "ή" always gets an accent
            if ($word_lower === 'ή' || $word_lower === 'η' && $has_accent) {
                $should_have_accent = true;
            }
            
            // Check for interrogative or special πού/πώς
            else if ($word_lower === 'που' || $word_lower === 'πως' || 
                     $word_lower === 'πού' || $word_lower === 'πώς') {
                
                // Check for interrogative context (question marks nearby)
                $is_question = false;
                $context_window = 10;
                $start = max(0, $word_index - $context_window);
                $end = min(count($words) - 1, $word_index + $context_window);
                
                for ($i = $start; $i <= $end; $i++) {
                    if (strpos($words[$i], ';') !== false || strpos($words[$i], '?') !== false) {
                        $is_question = true;
                        break;
                    }
                }
                
                // Check for special phrases
                $in_special_phrase = false;
                $context = '';
                for ($i = max(0, $word_index - 3); $i <= min(count($words) - 1, $word_index + 3); $i++) {
                    $context .= $words[$i] . ' ';
                }
                
                foreach ($special_pou_pws_phrases as $phrase) {
                    if (mb_stripos($context, $phrase) !== false) {
                        $in_special_phrase = true;
                        break;
                    }
                }
                
                // If it's interrogative or in a special phrase, it should have an accent
                if ($is_question || $in_special_phrase) {
                    $should_have_accent = true;
                }
            }
            
            // Check for θά before abbreviated forms
            else if ($word_lower === 'θα' && $has_accent) {
                if (isset($words[$word_index + 1]) && 
                    mb_strlen($words[$word_index + 1]) > 0 && 
                    mb_substr($words[$word_index + 1], 0, 1) === "'") {
                    $should_have_accent = true;
                }
            }
            
            // Check for capitalized words (like at the beginning of a sentence)
            else if ($is_capitalized) {
                $should_have_accent = false; // Capitalized monosyllables don't need accents
            }
            
            // Report issues
            if ($should_have_accent && !$has_accent) {
                $results['stats']['missing_accents'][] = array(
                    'word' => $word,
                    'word_index' => $word_index
                );
                
                $results['issues'][] = sprintf(
                    __('Word "%s" should have an accent in this context', 'greek-multi-tool'),
                    $word
                );
            }
            else if (!$should_have_accent && $has_accent) {
                // Exception for "ή" in all-caps context
                if (!($is_all_caps && $word_lower === 'ή')) {
                    $results['stats']['unnecessary_accents'][] = array(
                        'word' => $word,
                        'word_index' => $word_index
                    );
                    
                    $results['issues'][] = sprintf(
                        __('Monosyllabic word "%s" should not have an accent', 'greek-multi-tool'),
                        $word
                    );
                }
            }
        }
        
        $word_index++;
    }
    
    // Calculate percentage of Greek characters
    if ($results['stats']['total_chars'] > 0) {
        $results['stats']['percent_greek'] = round(($results['stats']['greek_chars'] / $results['stats']['total_chars']) * 100);
    } else {
        $results['stats']['percent_greek'] = 0;
    }
    
    return $results;
}

/**
 * Register the text analysis setting
 */
function grmlt_register_text_analysis_settings() {
    register_setting(
        'grmlt_settings',
        'grmlt_enable_text_analysis',
        array(
            'type' => 'string',
            'description' => __('Enable Greek text analysis', 'greek-multi-tool'),
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '0',
        )
    );
}
add_action('admin_init', 'grmlt_register_text_analysis_settings');

/**
 * Add text analysis metabox to post editing screen - only if enabled
 */
function grmlt_add_text_analysis_metabox() {
    // Only add metabox if text analysis is enabled
    if (get_option('grmlt_enable_text_analysis', '0') !== '1') {
        return;
    }
    
    $screens = array('post', 'page');
    
    foreach ($screens as $screen) {
        add_meta_box(
            'grmlt_text_analysis',
            __('Greek Text Analysis', 'greek-multi-tool'),
            'grmlt_text_analysis_metabox_callback',
            $screen,
            'side'
        );
    }
}
add_action('add_meta_boxes', 'grmlt_add_text_analysis_metabox');

/**
 * Callback for the text analysis metabox
 */
function grmlt_text_analysis_metabox_callback($post) {
    // Add a nonce field for security
    wp_nonce_field('grmlt_text_analysis_nonce', 'grmlt_text_analysis_nonce');
    
    // Output metabox content
    ?>
    <div id="grmlt-text-analysis">
        <p><?php _e('Analyze your content for proper Greek accent rules.', 'greek-multi-tool'); ?></p>
        <button type="button" id="grmlt-analyze-button" class="button"><?php _e('Analyze Text', 'greek-multi-tool'); ?></button>
        <div id="grmlt-analysis-results" style="display: none; margin-top: 10px;"></div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        /**
         * Extract content from the current editor, supporting:
         * - WordPress Gutenberg (Block Editor)
         * - Classic Editor (TinyMCE / textarea)
         * - WP Bakery Page Builder (backend & frontend)
         * - Elementor Page Builder
         */
        function grmltGetEditorContent() {
            var content = '';

            // --- 1. Gutenberg Block Editor ---
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                try {
                    var editor = wp.data.select('core/editor');
                    if (editor && typeof editor.getEditedPostContent === 'function') {
                        content = editor.getEditedPostContent();
                        if (content && content.trim().length > 0) {
                            return content;
                        }
                    }
                } catch(e) {}

                // Try core/block-editor for newer WP versions
                try {
                    var blockEditor = wp.data.select('core/block-editor');
                    if (blockEditor && typeof blockEditor.getBlocks === 'function') {
                        var blocks = blockEditor.getBlocks();
                        if (blocks && blocks.length > 0) {
                            content = wp.blocks.serialize(blocks);
                            if (content && content.trim().length > 0) {
                                return content;
                            }
                        }
                    }
                } catch(e) {}
            }

            // --- 2. WP Bakery Page Builder ---
            // WP Bakery backend editor: content is in the #content textarea (raw shortcodes)
            // Also try WP Bakery's own content areas
            if (typeof vc !== 'undefined' || $('#wpb_visual_composer').length > 0 || $('[data-vc-shortcode]').length > 0) {
                // Try to get content from WP Bakery's internal storage
                if (typeof vc !== 'undefined' && typeof vc.builder !== 'undefined') {
                    try {
                        content = vc.builder.getContent();
                        if (content && content.trim().length > 0) {
                            return content;
                        }
                    } catch(e) {}
                }

                // Try the Visual Composer shortcode textarea
                if ($('#wpb_vc_js_status').length && $('#wpb_vc_js_status').val() === 'true') {
                    // WP Bakery visual mode is active, get from hidden textarea
                    content = $('#content').val();
                    if (content && content.trim().length > 0) {
                        return content;
                    }
                }

                // Try to scrape text from WP Bakery visual editor elements
                var vcTexts = [];
                $('.wpb_text_column .wpb_wrapper, .vc_element .wpb_wrapper, [data-vc-content] .wpb_wrapper').each(function() {
                    var txt = $(this).text().trim();
                    if (txt.length > 0) {
                        vcTexts.push(txt);
                    }
                });
                if (vcTexts.length > 0) {
                    content = vcTexts.join(' ');
                    if (content.trim().length > 0) {
                        return content;
                    }
                }
            }

            // --- 3. Elementor ---
            // When editing with Elementor, the main editor is not present;
            // content comes from the server via post_id fallback.
            // But try the preview iframe if available
            if (typeof elementor !== 'undefined' || typeof elementorFrontend !== 'undefined') {
                try {
                    var $previewFrame = $('#elementor-preview-iframe');
                    if ($previewFrame.length) {
                        var iframeContent = $previewFrame.contents().find('.elementor-widget-container');
                        if (iframeContent.length) {
                            var elTexts = [];
                            iframeContent.each(function() {
                                var txt = $(this).text().trim();
                                if (txt.length > 0) {
                                    elTexts.push(txt);
                                }
                            });
                            if (elTexts.length > 0) {
                                content = elTexts.join(' ');
                                return content;
                            }
                        }
                    }
                } catch(e) {}
            }

            // --- 4. Classic Editor (TinyMCE) ---
            if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                content = tinyMCE.activeEditor.getContent();
                if (content && content.trim().length > 0) {
                    return content;
                }
            }

            // --- 5. Plain textarea fallback ---
            if ($('#content').length && $('#content').val()) {
                content = $('#content').val();
                if (content && content.trim().length > 0) {
                    return content;
                }
            }

            return content || '';
        }

        /**
         * Strip HTML tags and shortcodes from content client-side.
         */
        function grmltStripContent(content) {
            if (!content) return '';

            // Remove Gutenberg block comments
            content = content.replace(/<!--\s*\/?wp:.*?-->/gs, '');

            // Remove shortcode tags but keep their inner content
            // Matches [shortcode attr="val"]...[/shortcode] and standalone [shortcode /]
            content = content.replace(/\[\/?[^\]]+\]/g, ' ');

            // Strip HTML tags
            var div = document.createElement('div');
            div.innerHTML = content;
            content = div.textContent || div.innerText || '';

            // Normalize whitespace
            content = content.replace(/\s+/g, ' ').trim();

            return content;
        }

        $('#grmlt-analyze-button').on('click', function() {
            // Get content from the editor
            var rawContent = grmltGetEditorContent();
            var content = grmltStripContent(rawContent);

            // Get post ID for server-side fallback
            var postId = 0;
            if ($('#post_ID').length) {
                postId = $('#post_ID').val();
            } else if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                try {
                    postId = wp.data.select('core/editor').getCurrentPostId();
                } catch(e) {}
            }

            // AJAX request to analyze text
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'grmlt_analyze_text',
                    content: content,
                    post_id: postId,
                    nonce: $('#grmlt_text_analysis_nonce').val()
                },
                beforeSend: function() {
                    $('#grmlt-analysis-results').html('<p><?php _e('Analyzing...', 'greek-multi-tool'); ?></p>').show();
                },
                success: function(response) {
                    if (response.success) {
                        var html = '<div class="grmlt-analysis-stats">';
                        html += '<h4><?php _e('Text Statistics', 'greek-multi-tool'); ?></h4>';
                        html += '<p><?php _e('Characters:', 'greek-multi-tool'); ?> ' + response.data.stats.total_chars + '</p>';
                        html += '<p><?php _e('Words:', 'greek-multi-tool'); ?> ' + response.data.stats.total_words + '</p>';
                        html += '<p><?php _e('Greek characters:', 'greek-multi-tool'); ?> ' + response.data.stats.greek_chars;
                        html += ' (' + response.data.stats.percent_greek + '%)</p>';
                        html += '<p><?php _e('Accented characters:', 'greek-multi-tool'); ?> ' + response.data.stats.accented_chars + '</p>';

                        if (response.data.issues.length > 0) {
                            html += '<h4><?php _e('Accent Rule Issues', 'greek-multi-tool'); ?></h4>';
                            html += '<ul class="grmlt-issues-list">';

                            $.each(response.data.issues, function(index, issue) {
                                html += '<li>' + $('<span>').text(issue).html() + '</li>';
                            });

                            html += '</ul>';
                        } else {
                            html += '<p class="grmlt-no-issues"><?php _e('No accent rule issues found!', 'greek-multi-tool'); ?></p>';
                        }

                        html += '</div>';
                        $('#grmlt-analysis-results').html(html);
                    } else {
                        $('#grmlt-analysis-results').html('<p class="grmlt-error">' + $('<span>').text(response.data).html() + '</p>');
                    }
                },
                error: function() {
                    $('#grmlt-analysis-results').html('<p class="grmlt-error"><?php _e('Error analyzing text.', 'greek-multi-tool'); ?></p>');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX handler for text analysis
 *
 * Accepts content from the client-side editor, but also supports a post_id
 * fallback for page builders (WP Bakery, Elementor, etc.) where client-side
 * content extraction may not capture the full text.
 */
function grmlt_ajax_analyze_text() {
    // Check nonce for security
    check_ajax_referer('grmlt_text_analysis_nonce', 'nonce');

    // Check if user has permission
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'greek-multi-tool'));
    }

    // Get content from request
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : '';
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

    // If content is empty or too short, try server-side extraction from the post
    if ((empty($content) || mb_strlen($content) < 10) && $post_id > 0) {
        // Load page builder compat if not already loaded
        if (!function_exists('grmlt_get_post_clean_text')) {
            require_once plugin_dir_path(__FILE__) . 'page-builder-compat.php';
        }

        $server_content = grmlt_get_post_clean_text($post_id);

        // Use server content if it's longer than client content
        if (mb_strlen($server_content) > mb_strlen($content)) {
            $content = $server_content;
        }
    }

    // If content still contains shortcode-like patterns, clean them
    if (!empty($content) && preg_match('/\[\w+/', $content)) {
        if (!function_exists('grmlt_extract_clean_text')) {
            require_once plugin_dir_path(__FILE__) . 'page-builder-compat.php';
        }
        $content = grmlt_extract_clean_text($content);
    }

    if (empty($content)) {
        wp_send_json_error(__('No content to analyze. If you are using a page builder, please save the post first and try again.', 'greek-multi-tool'));
    }

    // Analyze text
    $results = grmlt_analyze_text($content);

    // Send results
    wp_send_json_success($results);
}
add_action('wp_ajax_grmlt_analyze_text', 'grmlt_ajax_analyze_text');

/**
 * Add Text Analysis tab to plugin settings page
 */
function grmlt_add_text_analysis_tab($tabs) {
    $tabs['textanalysis'] = __('Text Analysis', 'greek-multi-tool');
    return $tabs;
}
add_filter('grmlt_settings_tabs', 'grmlt_add_text_analysis_tab');

/**
 * Display Text Analysis settings tab content
 */
function grmlt_display_text_analysis_tab_content() {
    $tab_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'admin/partials/settings-page/text-analysis-tab.php';

    if ( file_exists( $tab_path ) ) {
        include $tab_path;
    } else {
        echo '<p>' . esc_html__( 'Error: Text analysis tab content file not found', 'greek-multi-tool' ) . '</p>';
    }
}
add_action('grmlt_settings_tab_textanalysis', 'grmlt_display_text_analysis_tab_content');