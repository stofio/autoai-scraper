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
register_activation_hook(__FILE__, 'scrapeai_activate');
register_deactivation_hook(__FILE__, 'deactivate_cron_job');

add_action('init', 'register_all_ajax', 1);

add_action('admin_enqueue_scripts', 'scrapeai_scripts', 20);

add_action('init', 'register_source_cpt');

add_action('init', 'init_scheduling');

add_action('admin_menu', 'ai_scraper_custom_menu');

add_action('admin_init', 'save_source_form');


//
//settings page 
//
add_action('admin_init', 'save_open_ai_key');
add_action('admin_init', 'save_output_language');
add_action('admin_init', 'save_ai_settings');
add_action('admin_init', 'save_cron_job_settings');

// Hook the function to add the button to the admin bar
add_action('wp', 'run_rewrite_post');


function register_all_ajax() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'admin/admin-includes/ajaxActions.php';
    }
}

function init_scheduling() {
    require_once plugin_dir_path(__FILE__) . 'includes/classes/clsScheduling.php';
    $Scheduling = new Scheduling();
    add_action('scrape_content_event', array($Scheduling, 'scheduled_event_callback'));
}

function scrapeai_activate() {
    //CREATE TABLE
    global $wpdb;
    $table_name = $wpdb->prefix . 'autoai_processed';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(255) NOT NULL,
            status varchar(100) NOT NULL,
            bulk_group int(9),
            processed_on datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            new_post_id bigint(20) UNSIGNED,
            PRIMARY KEY  (id),
            KEY url (url(191)),
            CONSTRAINT fk_new_post_id FOREIGN KEY (new_post_id) REFERENCES wp_posts(ID)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}


//remove cron job
function deactivate_cron_job() {
    $cron_jobs = _get_cron_array();    
    foreach ($cron_jobs as $timestamp => $cron) {
        foreach ($cron as $hook => $events) {
            if ($hook === 'scrape_content_event') {
                foreach ($events as $event) {
                    wp_unschedule_event($timestamp, $hook, $event['args']);
                }
            }
        }
    }
}


function scrapeai_scripts() {
    if (is_admin()) {
        global $pagenow;

        $screen = get_current_screen();

       // Check if on your plugin's pages
       $allowed_pages = array('ai-auto-post', 'autoai-from-url', 'autoai-sources', 'autoai-settings', 'scrapeai-bulk'); // Add your submenu pages here
       if (in_array($pagenow, $allowed_pages) || (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)) || $screen->post_type === 'sources_cpt') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('ai-rewriter-script', plugins_url('/js/js.js', __FILE__), array( 
                'jquery', 'wp-blocks', 'wp-element', 'wp-data' 
            ));
            wp_localize_script('ai-rewriter-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
            wp_enqueue_style('ai-rewriter-style', plugins_url('/css/css.css', __FILE__));
        }
        
        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'scrapeai-bulk') {
            wp_enqueue_script('scrapeai-bulk-scrape', plugin_dir_url(__FILE__) . '/admin/js/page-bulk.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-bulk-scrape', 'scrapeaiBulkScrape', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_bulk_scrape_nonce'),
            ));
        }

        if ($screen && $screen->post_type === 'sources_cpt' && $screen->base === 'post') {
            wp_enqueue_script('scrapeai-source-script', plugin_dir_url(__FILE__) . '/admin/js/single-source.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-source-script', 'scrapeaiSingleSource', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_single_source_nonce'),
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
        'scrapeai-bulk',             // Menu slug
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




?>