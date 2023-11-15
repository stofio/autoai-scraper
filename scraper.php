<?php
/**
 * Plugin Name: News Scraper
 * Plugin URI: #
 * Description: This plugin scrapes news websites, rewrites the article with AI and creates new post
 * Version: 1.0.0
 * Author: Anonymous
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

add_action('admin_enqueue_scripts', 'news_scraper_scripts');
function news_scraper_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('news-scraper-script', plugins_url('/js/js.js', __FILE__), array( 'jquery', 'wp-blocks', 'wp-element', 'wp-data' ));
    wp_enqueue_style('news-scraper-scraper-style', plugins_url('/css/css.css', __FILE__));

    wp_localize_script('news-scraper-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php')));
}

// Hook into the admin_menu action to add the submenu page
add_action('admin_menu', 'news_scraper_tools_submenu');
function news_scraper_tools_submenu() {
    // Add a submenu page under the "Tools" menu
    add_submenu_page(
        'tools.php',                // Parent menu slug (the "Tools" menu)
        'News Scraper',           // Page title
        'News Scraper',           // Menu title
        'manage_options',          // Capability required to access the page
        'news-scraper',     // Unique menu slug
        'news_scraper_render_custom_page' // Callback function to render the page content
    );
}

// Callback function to render the custom page content
function news_scraper_render_custom_page() {
    // check if the form has been submitted
    if (isset($_POST['submit'])) {
        // sanitize and store the input values
        $openai_key = sanitize_text_field($_POST['openai_key']);

        // update options in the database
        update_option('news_scraper_openai_key', $openai_key);
    }

    // retrieve the values from the database
    $value1 = get_option('news_scraper_openai_key', '');

    ?>

<div class="wrap">
    <div class="ai-writer-box">
    	<div class="ai-writer-box_container">
            <h1>News Scraper</h1>
            <form method="post" action="">
                <h2>Plugin Settings</h2>
                <p>
                    <label>OpenAI API Key:</label><br>
                    <input type="text" name="openai_key" value="<?php echo esc_attr($value1); ?>" placeholder="sk-">
                </p>
                <p>
                    <input type="submit" id="news_scraper_save_settings" name="submit" value="Save Settings">
                </p>
            </form>

            <input type="button" id="news_scraper_run_scraper" value="Run Scraper">

            <br><br><br>
            <label>Check if article exist: 
            <input type="checkbox" id="check_article_exist"/></label>
            <input type="text" id="news_scraper_url_input" placeholder="URL" />
            <input type="button" id="news_scraper_run_from_url" value="Run From URL">

        </div>
    </div>
</div>

<?php
}


function ajax_run_scraper() {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
    run_scraper_and_post();
    wp_die(); // Ensure proper AJAX response
}
add_action('wp_ajax_run_scraper', 'ajax_run_scraper');


function ajax_run_scraper_from_url() {
    require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';
    $url = isset($_POST['news_scraper_url']) ? sanitize_text_field($_POST['news_scraper_url']) : '';
    $check_article_exist = $_POST['check_article_exist'];
   if (!empty($url)) {
       run_scraper_from_url($url, $check_article_exist);
   }
    wp_die(); // Ensure proper AJAX response
}
add_action('wp_ajax_run_scraper_from_url', 'ajax_run_scraper_from_url');


?>