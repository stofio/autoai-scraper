<?php

//start bulk processing
require_once plugin_dir_path(__FILE__) . 'clsBulkHandler.php';
$bulk = new BulkHandler;
add_action('wp_ajax_scrapeai_process_bulk_ajax', array($bulk, 'scrapeai_process_bulk_ajax'));

//start test source
add_action('wp_ajax_test_source', 'test_source_callback');

//start test category
add_action('wp_ajax_test_category', 'test_category_callback');


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


?>