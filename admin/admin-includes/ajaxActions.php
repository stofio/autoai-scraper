<?php

//start bulk processing
require_once plugin_dir_path(__FILE__) . 'clsBulkHandler.php';
$bulk = new BulkHandler;
add_action('wp_ajax_scrapeai_process_bulk_ajax', array($bulk, 'scrapeai_process_bulk_ajax'));

//start test source
add_action('wp_ajax_test_source', 'test_source_callback');

//start test category
add_action('wp_ajax_test_category', 'test_category_callback');

add_action('wp_ajax_delete_processed', 'delete_processed_callback');

add_action('wp_ajax_display_logs', 'display_logs_callback');

add_action('wp_ajax_display_full_prompt', 'display_full_prompt_callback');

add_action('wp_ajax_get_original_url_by_id', 'get_original_url_by_id_callback');

add_action('wp_ajax_rewrite_post', 'rewrite_post_callback');


function test_source_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_single_source_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check if URL is provided
    if (!isset($_POST['url']) || empty($_POST['url'])) {
        wp_send_json_error('URL is required');
    }

    $url = esc_url($_POST['url']);


    $sourceSettings = [
        'selectors' => [
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'imageUrl' => str_replace('\\"', '"', $_POST['imageUrl']),
            'typeArticle' => '',
            'typeArticleText' => ''
        ],
        'getImages' => 1,
        'getTables' => 1
    ];

    require_once plugin_dir_path(__DIR__) . '../vendor/autoload.php';
    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsScrapeWebsite.php';
    $scraper = new ScrapaWebsite();
    $scrapedData = $scraper->scrapeWebsite($sourceSettings, $url);

    
    if (!is_wp_error($scrapedData)) {
        wp_send_json_success($scrapedData);
    } else {
        wp_send_json_error('Error fetching content');
    }
}


function test_category_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_single_source_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check if URL is provided
    if (!isset($_POST['url']) || empty($_POST['url'])) {
        wp_send_json_error('URL is required');
    }

    $url = esc_url($_POST['url']);

    require_once plugin_dir_path(__DIR__) . '../vendor/autoload.php';
    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsScrapeWebsite.php';
    $scraper = new ScrapaWebsite();
    $scrapedData = $scraper->scrapeCategoryPage($url, $_POST['container'], $_POST['href']); //returns list of urls
    
    if (!is_wp_error($scrapedData)) {
        wp_send_json_success($scrapedData);
    } else {
        wp_send_json_error('Error fetching content');
    }
}

function delete_processed_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_delete_processed_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check if URL is provided
    if (!isset($_POST['proc_id']) || empty($_POST['proc_id'])) {
        wp_send_json_error('Processed ID is required');
    }

    $proc_id = $_POST['proc_id'];

    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsUrlProcessor.php';
    $proccessor = new UrlProcessor();
    $proccessor->delete_processed($proc_id);        
}

function display_full_prompt_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_single_source_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsAiRewriting.php';
    $aiRewr = new OpenAIRewriting(null);
    $prompt = $aiRewr->getChunkFullPrompt();

    if (!is_wp_error($prompt)) {
        wp_send_json_success($prompt);
    } else {
        wp_send_json_error('Error getting prompt');
    }
}


function display_logs_callback() {
    
    // Define the path to the log file
    $log_file_path = dirname(dirname(plugin_dir_path( __FILE__ ))) . '/logs/' . $_POST['fileName'];

    // Initialize an empty string to store log content
    $log_content = '';

    // Check if file exists
    if (file_exists($log_file_path)) {
        // Open the file
        $handle = fopen($log_file_path, "r");

        if ($handle) {
            // Read line by line
            while (($line = fgets($handle)) !== false) {
                // Append each line to the log content
                $log_content .= htmlspecialchars($line);
            }

            fclose($handle);
        } else {
            $log_content = 'Error opening the log file.';
        }
    } else {
        $log_content = 'Log file is empty';
    }

    // Return the log content within a scrollable div
    echo '<div style="height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;" class="log-container">' . $log_content . '</div>';
    die();
}

function get_original_url_by_id_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_rewrite_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!isset($_POST['post_id']) || empty($_POST['post_id'])) {
        wp_send_json_error('Post ID is required');
    }

    $post_id = $_POST['post_id'];

    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsUrlProcessor.php';
    $proccessor = new UrlProcessor();
    $url = $proccessor->get_url_by_post_id($post_id);  

    if (!is_wp_error($url)) {
        wp_send_json_success($url);
    } else {
        wp_send_json_error('Error getting prompt');
    }

}

function rewrite_post_callback() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'scrapeai_rewrite_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    if (!isset($_POST['post_id']) || empty($_POST['post_id'])) {
        wp_send_json_error('Post ID is required');
    }

    $post_id = $_POST['post_id'];

    require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsMain.php';
    $main = new ScrapeAiMain();
    $res = $main->runRewritingOfPostedPost($post_id);

    if (!is_wp_error($res)) {
        wp_send_json_success($res);
    } else {
        wp_send_json_error('Error getting prompt');
    }

}



?>