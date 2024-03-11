<?php
/**
 * Plugin Name: AI-powered Content Creation with RSS and Scraping
 * Plugin URI: #
 * Description: This plugin scrapes from selected URLs/sources, rewrites the articles with AI and creates new posts
 * Version: 1.0.0
 * Author: Dejan Manasijevski
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

add_action('admin_enqueue_scripts', 'scrapeai_scripts');

add_action('init', 'register_source_cpt'); //custom post type for Sources

add_action('admin_menu', 'ai_scraper_custom_menu'); // Hook into the admin_menu action to add the new top-level menu and submenus

add_action('admin_init', 'save_source_form');

//add_action('wp_ajax_run_scraper', 'ajax_run_scraper');
//add_action('wp_ajax_run_scraper_from_url', 'ajax_run_scraper_from_url');

add_action('wp_ajax_scrapeai_handle_bulk_scrape_ajax', 'scrapeai_handle_bulk_scrape_ajax'); //start bulk scraping

//
//settings page 
//
add_action('admin_init', 'save_open_ai_key');
add_action('admin_init', 'save_output_language');
add_action('admin_init', 'save_ai_settings');
add_action('admin_init', 'save_cron_job_settings');

// Hook the function to add the button to the admin bar
add_action('wp', 'run_rewrite_post');


// Add an action hook to handle the cron job
add_action('autoai_cron_hook', 'run_auto_post_single_cron_job');


function scrapeai_scripts() {
    if (is_admin()) {
        global $pagenow;

        $screen = get_current_screen();

       // Check if on your plugin's pages
       $allowed_pages = array('ai-auto-post', 'autoai-from-url', 'autoai-sources', 'autoai-settings', 'autoai-bulk'); // Add your submenu pages here
       if (in_array($pagenow, $allowed_pages) || (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)) || $screen->post_type === 'sources_cpt') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('ai-rewriter-script', plugins_url('/js/js.js', __FILE__), array( 
                'jquery', 'wp-blocks', 'wp-element', 'wp-data' 
            ));
            wp_localize_script('ai-rewriter-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));

            wp_enqueue_style('ai-rewriter-style', plugins_url('/css/css.css', __FILE__));

        }
        
        if(isset($_GET['page']) && $_GET['page'] == 'autoai-bulk') {
            wp_enqueue_script('scrapeai-bulk-scrape', plugin_dir_url(__FILE__) . '/admin/js/page-bulk.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-bulk-scrape', 'scrapeaiBulkScrape', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_bulk_scrape_nonce'),
            ));
        }
    }
}



function register_source_cpt() {
     $labels = array(
        'name'               => _x('Sources', 'post type general name'),
        'singular_name'      => _x('Source', 'post type singular name'),
        'add_new'            => _x('Add Source', 'source'),
        'add_new_item'       => __('Add New Source'),
        'edit_item'          => __('Edit Source'),
        'new_item'           => __('New Source'),
        'all_items'          => __('All Sources'),
        'view_item'          => __('View Source'),
        'search_items'       => __('Search Sources'),
        'not_found'          => __('No sources found'),
        'not_found_in_trash' => __('No sources found in Trash'),
        'parent_item_colon'  => '',
        'menu_name'          => 'Sources'
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'label'  => 'Sources',
        'supports' => array('title')
    );
    register_post_type('sources_cpt', $args);
    include_once plugin_dir_path(__FILE__) . 'includes/sources.php';
}


function ai_scraper_custom_menu() {
    // Add the top-level menu item
    add_menu_page(
        'AI Auto Rewrite',         // Page title
        'AI Auto Rewrite',         // Menu title
        'manage_options',          // Capability required
        'ai-auto-post',     // Menu slug
        'ai_auto_post_page',     // Function to display the dashboard page
        'dashicons-admin-site-alt3', // Icon for the menu (WordPress Dashicon)
        6                           // Position in the menu (optional)
    );

    // Add the 'Scraper' submenu - this can be your existing page
    add_submenu_page(
        'ai-auto-post',     // Parent slug
        'From URL',                  // Page title
        'From URL',                  // Menu title
        'manage_options',           // Capability required
        'autoai-from-url',             // Menu slug
        'autoai_from_url_page' // Function to display the page
    );

    // Add 'sources' submenu
    add_submenu_page(
        'ai-auto-post',
        'Sources',
        'Sources',
        'manage_options',
        'autoai-sources',
        'autoai_sources_page'
    );

    // Add 'Logs' submenu
    // add_submenu_page(
    //     'ai-auto-post',
    //     'Logs',
    //     'Logs',
    //     'manage_options',
    //     'ai-scraper-logs',
    //     'ai_scraper_logs_page'
    // );

    // Add 'Settings' submenu
    add_submenu_page(
        'ai-auto-post',
        'Settings',
        'Settings',
        'manage_options',
        'autoai-settings',
        'autoai_settings_page'
    );

    add_submenu_page(
        'ai-auto-post',     // Parent slug
        'Bulk',                  // Page title
        'Bulk',                  // Menu title
        'manage_options',           // Capability required
        'autoai-bulk',             // Menu slug
        'autoai_bulk_page' // Function to display the page
    );
}


function autoai_sources_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-sources.php';
}

function ai_auto_post_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-auto-post.php';
}

function autoai_from_url_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-from-url.php';
}

function autoai_settings_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-settings.php';
}

function autoai_bulk_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-bulk.php';
}

function save_source_form() {
    require_once plugin_dir_path(__FILE__) . '/admin/admin-includes/handle-actions.php';
    save_source(); 
}

function ajax_run_scraper() {
    require_once plugin_dir_path(__FILE__) . 'includes/main.php';
    run_scraper_and_post();
    wp_die();
}

function ajax_run_scraper_from_url() {
    require_once plugin_dir_path(__FILE__) . 'includes/main.php';
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    if (!empty($url)) {
        $posted_url = run_scraper_from_url($url);
       echo $posted_url;
    }
    wp_die(); 
}


//
//settings page
//
function save_open_ai_key() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_open_ai_key') {
        $api_key = isset($_POST['open_ai_key']) ? sanitize_text_field($_POST['open_ai_key']) : '';
        update_option('open_ai_key_option', $api_key);
    }
}

function save_output_language() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_output_language') {
        $language = isset($_POST['output_language']) ? sanitize_text_field($_POST['output_language']) : '';
        
        update_option('autoai_output_language', $language);
    }
}

function save_ai_settings() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_ai_settings') {
        $open_ai_model = isset($_POST['open_ai_model']) ? sanitize_text_field($_POST['open_ai_model']) : '';
        $word_count_per_open_ai_request = isset($_POST['word_count_per_open_ai_request']) ? sanitize_text_field($_POST['word_count_per_open_ai_request']) : '';
        $prompt_partial_text = isset($_POST['prompt_partial_text']) ? $_POST['prompt_partial_text'] : '';
        
        update_option('open_ai_model', $open_ai_model);
        update_option('word_count_per_open_ai_request', $word_count_per_open_ai_request);
        update_option('prompt_partial_text', $prompt_partial_text);
    }
}


function save_cron_job_settings() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_cron_job_settings') {
        $times_a_day_run_cron = isset($_POST['times_a_day_run_cron']) ? $_POST['times_a_day_run_cron'] : '';
        update_option('times_a_day_run_cron', $times_a_day_run_cron);
    }
}


function run_rewrite_post() {
    if (is_single() && is_user_logged_in()) {
        require_once plugin_dir_path(__FILE__) . '/admin/admin-includes/rewrite_post.php';
    }
}


// Callback function to process each URL in the cron job
function run_auto_post_single_cron_job($indexInSourceArray) {
    require_once plugin_dir_path(__FILE__) . 'includes/main.php';
    $sources = get_option('ai_scraper_websites', '');

    my_log('CRON JOB N: ');
    my_log($sources[$indexInSourceArray]);

    run_single_auto_post($sources[$indexInSourceArray]);
    my_second_log('INFO', 'Cron job run for: ' . $sources[$indexInSourceArray]['baseUrl']);


    $runTimeAndSourceUrl = array(
        'last_run_time' => time(), 
        'last_source_url' => $sources[$indexInSourceArray]['baseUrl']
    );
    update_option('autoai_last_cron_run', $runTimeAndSourceUrl);
}


function scrapeai_handle_bulk_scrape_ajax() {
    // Verify the nonce for security
    check_ajax_referer('my_plugin_bulk_scrape_nonce', 'nonce');

    // Extract and sanitize form data
    $urls = isset($_POST['urls']) ? sanitize_textarea_field($_POST['urls']) : '';
    $is_default_categories = isset($_POST['is_default_categories']) ? sanitize_text_field($_POST['is_default_categories']) : '';
    $main_category = isset($_POST['main_category']) ? absint($_POST['main_category']) : 0; // Assuming this is an ID
    $additional_categories = isset($_POST['additional_categories']) ? array_map('absint', $_POST['additional_categories']) : array();
    $post_status = isset($_POST['post_status']) ? sanitize_text_field($_POST['post_status']) : 'draft';
    $tags = isset($_POST['tags']) ? sanitize_text_field($_POST['tags']) : '';

    require_once plugin_dir_path(__FILE__) . 'includes/main.php';
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    if (!empty($url)) {
        $posted_url = run_scraper_from_url($url);
       echo $posted_url;
    }
}


?>