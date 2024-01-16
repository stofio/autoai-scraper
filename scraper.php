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

add_action('admin_enqueue_scripts', 'news_scraper_scripts');

// Hook into the admin_menu action to add the new top-level menu and submenus
add_action('admin_menu', 'ai_scraper_custom_menu');

add_action('admin_init', 'save_source_form');

//add_action('wp_ajax_run_scraper', 'ajax_run_scraper');
//add_action('wp_ajax_run_scraper_from_url', 'ajax_run_scraper_from_url');

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


function news_scraper_scripts() {
    if (is_admin()) {
        global $pagenow;

       // Check if on your plugin's pages
       $allowed_pages = array('ai-auto-post', 'autoai-from-url', 'autoai-sources', 'autoai-settings'); // Add your submenu pages here
       if (in_array($pagenow, $allowed_pages) || (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages))) {
           wp_enqueue_script('jquery');
            wp_enqueue_script('news-scraper-script', plugins_url('/js/js.js', __FILE__), array( 'jquery', 'wp-blocks', 'wp-element', 'wp-data' ));
            wp_enqueue_style('news-scraper-scraper-style', plugins_url('/css/css.css', __FILE__));

            wp_localize_script('news-scraper-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
        }        
    }
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


function save_source_form() {
    require_once plugin_dir_path(__FILE__) . '/admin/includes/handle_actions.php';
    save_source(); 
}


function ajax_run_scraper() {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
    run_scraper_and_post();
    wp_die();
}


function ajax_run_scraper_from_url() {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
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
        require_once plugin_dir_path(__FILE__) . '/admin/includes/rewrite_post.php';
    }
}


// Callback function to process each URL in the cron job
function run_auto_post_single_cron_job($indexInSourceArray) {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
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




?>