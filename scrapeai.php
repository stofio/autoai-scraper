<?php
/**
 * Plugin Name: Wordpress GPT Rewriter
 * Plugin URI: #
 * Description: Scrape and rewrite with AI content from the web. Control the prompts and set schedules.
 * Version: 1.0.0
 * Author: Dejan Manasijevski
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */
register_activation_hook(__FILE__, 'scrapeai_activate');
register_deactivation_hook(__FILE__, 'deactivate_cron_job');
register_uninstall_hook( __FILE__, 'uninstall_delete_data' );

add_action('init', 'register_all_ajax', 1);

add_action('admin_enqueue_scripts', 'scrapeai_scripts', 20);

add_action( 'wp_enqueue_scripts', 'enqueue_source_cpt_script' );

add_action( 'admin_bar_menu', 'custom_admin_bar_button', 999 );

add_action('init', 'register_source_cpt');

//scheduling
add_action('scrape_content_event_daily', function() {init_scheduling('daily');});
add_action('scrape_content_event_hourly', function() {init_scheduling('hourly');});
add_action('scrape_content_event_twicedaily', function() {init_scheduling('twicedaily');});
add_action('scrape_content_event_weekly', function() {init_scheduling('weekly');});


add_action('admin_menu', 'ai_scraper_custom_menu');
add_action( 'admin_menu', 'modify_sources_submenu_link' );

//settings page
add_action('admin_init', 'save_scrapeai_settings');

function register_all_ajax() {
    if (is_admin()) {
        require_once plugin_dir_path(__FILE__) . 'admin/admin-includes/ajaxActions.php';
    }
}

function init_scheduling($interval) {
    require_once plugin_dir_path(__FILE__) . 'includes/classes/clsScheduling.php';
    $scheduling = new Scheduling();
    $scheduling->scheduled_event_callback($interval);
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
            processed_on datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            new_post_id bigint(20) UNSIGNED,
            source_id bigint(20),
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
    $cron_names = array('scrape_content_event_daily', 'scrape_content_event_hourly', 'scrape_content_event_twicedaily', 'scrape_content_event_weekly');
    $cron_jobs = _get_cron_array();    
    foreach ($cron_jobs as $timestamp => $cron) {
        foreach ($cron as $hook => $events) {
            
            if (in_array($hook, $cron_names)) {
                foreach ($events as $event) {
                    wp_unschedule_event($timestamp, $hook, $event['args']);
                }
            }
        }
    }
}

function uninstall_delete_data() {
    // Check if the user confirmed to delete data
    if ( ! isset( $_REQUEST['my_plugin_delete_data'] ) ) {
      return;
    }
  
    global $wpdb;
    $table_name = $wpdb->prefix . 'autoai_processed';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" ); 
  
    delete_option( 'open_ai_key_option' );
    delete_option( 'autoai_output_language' );
    delete_option( 'times_a_day_run_cron' );

    $sources_posts = get_posts(array(
        'post_type' => 'sources_cpt',
        'numberposts' => -1,
        'post_status' => 'any'
    ));

    foreach ($sources_posts as $post) {
        wp_delete_post($post->ID, true); 
    }
}

function my_plugin_uninstall_confirmation() {
    return isset( $_REQUEST['my_plugin_delete_data'] ) && $_REQUEST['my_plugin_delete_data'] === '1';
  }

function enqueue_source_cpt_script() {
    if ( is_single() && is_user_logged_in() ) {
        wp_enqueue_script('scrapeai-rewrite-post', plugins_url('/admin/js/rewrite-post.js', __FILE__), array( 
            'jquery', 'wp-blocks', 'wp-element', 'wp-data' 
        ));
        wp_localize_script('scrapeai-rewrite-post', 'rewritePost', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scrapeai_rewrite_nonce'),
        ));
    }
  
  }

function scrapeai_scripts() {
    if (is_admin()) {
        global $pagenow;

        $screen = get_current_screen();

       // Check if on your plugin's pages
       $allowed_pages = array('ai-auto-post', 'scrapeai-bulk', 'scrapeai-processed', 'autoai-settings'); // submenu pages 
       if (in_array($pagenow, $allowed_pages) || (isset($_GET['page']) && in_array($_GET['page'], $allowed_pages)) || $screen->post_type === 'sources_cpt') {
            wp_enqueue_script('jquery');
            wp_enqueue_script('ai-rewriter-script', plugins_url('/js/js.js', __FILE__), array( 
                'jquery', 'wp-blocks', 'wp-element', 'wp-data' 
            ));
            wp_localize_script('ai-rewriter-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
            wp_enqueue_style('ai-rewriter-style', plugins_url('/css/css.css', __FILE__));
        }
        

        if ($screen && $screen->post_type === 'sources_cpt' && $screen->base === 'post') {
            wp_enqueue_script('scrapeai-source-script', plugin_dir_url(__FILE__) . '/admin/js/single-source.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-source-script', 'scrapeaiSingleSource', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_single_source_nonce'),
            ));
        }

        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'scrapeai-bulk') {
            wp_enqueue_script('scrapeai-bulk-scrape', plugin_dir_url(__FILE__) . '/admin/js/page-bulk.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-bulk-scrape', 'scrapeaiBulkScrape', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_bulk_scrape_nonce'),
            ));
        }

        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'scrapeai-processed') {
            wp_enqueue_script('scrapeai-processed', plugin_dir_url(__FILE__) . '/admin/js/page-processed.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-processed', 'scrapeaiProcessed', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_delete_processed_nonce'),
            ));
        }

        if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === 'scrapeai-logs') {
            wp_enqueue_script('scrapeai-processed', plugin_dir_url(__FILE__) . '/admin/js/page-logs.js', array('jquery'), null, true);
            wp_localize_script('scrapeai-processed', 'scrapeaiLogs', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('scrapeai_logs_nonce'),
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
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'label'  => 'Sources',
        'supports' => array('title'),
        'show_in_menu' => false,
    );
    register_post_type('sources_cpt', $args);
    include_once plugin_dir_path(__FILE__) . 'includes/sources.php';
}


function ai_scraper_custom_menu() {
    // Add the top-level menu item
    add_menu_page(
        'AI Rewriter',         // Page title
        'AI Rewriter',         // Menu title
        'manage_options',          // Capability required
        'autoai-settings',     // Menu slug
        'autoai_settings_page',     // Function to display the dashboard page
        'dashicons-admin-site-alt3', // Icon for the menu (WordPress Dashicon)
        6                           // Position in the menu (optional)
    );

    add_submenu_page(
        'autoai-settings',     // Parent slug
        'Sources',                  // Page title
        'Sources',                  // Menu title
        'manage_options',           // Capability required
        'scrapeai-sources',             // Menu slug
        '' // Function to display the page
    );


    add_submenu_page(
        'autoai-settings',     // Parent slug
        'Bulk',                  // Page title
        'Bulk',                  // Menu title
        'manage_options',           // Capability required
        'scrapeai-bulk',             // Menu slug
        'autoai_bulk_page' // Function to display the page
    );

    add_submenu_page(
        'autoai-settings',
        'Processed', 
        'Processed',
        'manage_options',
        'scrapeai-processed',
        'autoai_processed_page'
    );

    //Add 'Logs' submenu
    add_submenu_page(
        'autoai-settings',
        'Logs',
        'Logs',
        'manage_options',
        'scrapeai-logs',
        'ai_scraper_logs_page'
    );


}

function modify_sources_submenu_link() {
    global $submenu;

    if(isset($submenu['autoai-settings'])) {
    foreach($submenu['autoai-settings'] as $key => $item) {
        if($item[2] === 'scrapeai-sources') {
        $submenu['autoai-settings'][$key][2] = 'edit.php?post_type=sources_cpt';
        break;
        }
    }
    }
}

function ai_auto_post_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-auto-post.php';
}

function autoai_bulk_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-bulk.php';
}

function autoai_processed_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-processed.php';
}

function autoai_settings_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-settings.php';
}

function ai_scraper_logs_page() {
    require_once plugin_dir_path(__FILE__) . '/admin/page-logs.php';
}


//save settings page
function save_scrapeai_settings() {
    if (isset($_POST['action']) && $_POST['action'] == 'save_ai_settings') {
        $api_key = isset($_POST['open_ai_key']) ? sanitize_text_field($_POST['open_ai_key']) : '';
        $language = isset($_POST['output_language']) ? sanitize_text_field($_POST['output_language']) : '';
        $timeADay = isset($_POST['times_a_day_run_cron']) ? sanitize_text_field($_POST['times_a_day_run_cron']) : '';
        
        update_option('open_ai_key_option', $api_key);
        update_option('autoai_output_language', $language);
        update_option('times_a_day_run_cron', $timeADay);

        require_once plugin_dir_path(__FILE__) . 'includes/classes/clsScheduling.php';
        $Scheduling = new Scheduling(false);
        $Scheduling->activate_cron_job();
    }
}



// Add a custom button to the admin bar
function custom_admin_bar_button( $wp_admin_bar ) {
    if ( ! is_admin() ) {
        // Get the current post ID
        $post_id = get_the_ID();

        //CHECK IF IS REWRITTEN POST
        
        // Check if it's a single post
        if ( is_singular() && $post_id ) {
            // Add your custom button with a link or action
            $wp_admin_bar->add_menu( array(
                'id'    => $post_id,
                'title' => 'Rewrite post',  
                'href' => '#',
                'meta'  => array(
                    'class' => 'rewrite-post-btn'
                ),
            ));
        }
    }
}





?>