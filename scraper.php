<?php
/**
 * Plugin Name: Auto Post AI
 * Plugin URI: #
 * Description: This plugin scrapes from selected URLs, a.k.a sources, rewrites the article with AI and creates new post
 * Version: 1.0.0
 * Author: Anonymous
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

add_action('admin_enqueue_scripts', 'news_scraper_scripts');
function news_scraper_scripts() {
    if (is_admin()) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('news-scraper-script', plugins_url('/js/js.js', __FILE__), array( 'jquery', 'wp-blocks', 'wp-element', 'wp-data' ));
        wp_enqueue_style('news-scraper-scraper-style', plugins_url('/css/css.css', __FILE__));

        wp_localize_script('news-scraper-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));

    }
}

// Hook into the admin_menu action to add the new top-level menu and submenus
add_action('admin_menu', 'ai_scraper_custom_menu');

function ai_scraper_custom_menu() {
    // Add the top-level menu item
    add_menu_page(
        'AI Auto Post',         // Page title
        'AI Auto Post',               // Menu title
        'manage_options',           // Capability required
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


add_action('admin_init', 'ai_scraper_handle_form');
function ai_scraper_handle_form() {
    require_once plugin_dir_path(__FILE__) . '/admin/includes/handle_actions.php';
    ai_scraper_handle_form_submission();
}

function ai_scraper_run_scraping_process() {
    $sources = get_option('ai_scraper_sources', []);
    foreach ($sources as $source_config) {
        $scrapedData = $scraper->scrapeWebsite($source_config);
        checkScrapedDataGenerateAiAndPost($scrapedData, 34); // Adjust as needed
    }
}

add_action('wp_ajax_run_scraper', 'ajax_run_scraper');
function ajax_run_scraper() {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
    run_scraper_and_post();
    wp_die(); // Ensure proper AJAX response
}


add_action('wp_ajax_run_scraper_from_url', 'ajax_run_scraper_from_url');
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
add_action('admin_init', 'save_open_ai_key');
function save_open_ai_key() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_open_ai_key') {
        $api_key = isset($_POST['open_ai_key']) ? sanitize_text_field($_POST['open_ai_key']) : '';
        
        update_option('open_ai_key_option', $api_key);
    }
}

add_action('admin_init', 'save_output_language');
function save_output_language() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_output_language') {
        $language = isset($_POST['output_language']) ? sanitize_text_field($_POST['output_language']) : '';
        
        update_option('autoai_output_language', $language);
    }
}


add_action('admin_init', 'save_ai_settings');
function save_ai_settings() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_ai_settings') {
        $open_ai_model = isset($_POST['open_ai_model']) ? sanitize_text_field($_POST['open_ai_model']) : '';
        $word_count_per_open_ai_request = isset($_POST['word_count_per_open_ai_request']) ? sanitize_text_field($_POST['word_count_per_open_ai_request']) : '';
        
        update_option('open_ai_model', $open_ai_model);
        update_option('word_count_per_open_ai_request', $word_count_per_open_ai_request);
    }
}


?>