<?php
//this class is used for bulk operations

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class BulkHandler {

    public function __construct() {
        require_once plugin_dir_path(__DIR__) . '../includes/utilities.php';
    }

    public function scrapeai_process_bulk_ajax() {
        check_ajax_referer('scrapeai_bulk_scrape_nonce', 'nonce');
        
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsMain.php';
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsUrlProcessor.php';
        
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';        
        $jobSettings = $_POST['bulk_settings'];   

        $urlProc = new UrlProcessor();
        $source = $urlProc->getSourceIdByUrl($url);

        if($source->ID == null) {
            my_second_log('ERROR', 'Missing source configuration for URL: ' . $url);
            wp_send_json_error(['message' => "Failed to process URL: $url"]);
        }

        $sourceSettings = $urlProc->getSourceConfig($source->ID);
        
        $main = new ScrapeAiMain();
        $postedUrl = $main->runScrapeRewriteAndPost($sourceSettings, $jobSettings, $url);

        //return to ajax
        if ($postedUrl) {
            wp_send_json_success(['message' => $postedUrl]);
        } else {
            wp_send_json_error(['message' => "Failed to process URL: $url"]);
        }
    }

    public function get_posts_categories() {
        wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

        //get all post categories
        $categories = get_categories( array(
            'orderby' => 'name',
            'order'   => 'ASC',
            'hide_empty'=> false
        ) );

        // Start the select element for the main category
        echo '<h4>Select the Main Category:</h4>';
        echo '<div>';
        foreach ($categories as $category) {
            $cat_id = $category->term_id;
            $cat_name = $category->name;

            if ($cat_id == 1) continue; // Skip the "Uncategorized" category

            echo "<label><input type='radio' name='main_category_id' value='$cat_id'> $cat_name</label><br>";
        }
        echo '</div>';

        // Start the checkboxes for additional categories
        echo '<h4>Select Additional Categories:</h4>';
        echo '<div>';
        foreach ($categories as $category) {
            $cat_id = $category->term_id;
            $cat_name = $category->name;

            if ($cat_id == 1) continue;

            echo "<label><input type='checkbox' name='additional_category_ids[]' value='$cat_id'> $cat_name</label><br>";
        }
        echo '</div>';
    }
    
}

?>