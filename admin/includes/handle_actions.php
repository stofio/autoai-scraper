<?php


function ai_scraper_handle_form_submission() {    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add_website') {

        $submitted_baseUrl = sanitize_text_field($_POST['baseUrl']);

        // Get existing websites
        $websites = get_option('ai_scraper_websites', []);
        if (!is_array($websites)) {
            $websites = [];
        }


        $website_config = [
            'baseUrl' => $_POST['baseUrl'],
            'categoryId' => $_POST['categoryId'],
            'catPageLastArticle' => $_POST['catPageLastArticle'],
            'title' => $_POST['title'],
            'content' => $_POST['content'],
            'imageUrl' => $_POST['imageUrl'],
            'newsLabelSel' => $_POST['newsLabelSel'],
            'newsLabelText' => $_POST['newsLabelText'],
            'defaultImageCredit' => $_POST['defaultImageCredit'],
            'tested' => false
        ];
        

        if (isset($_POST['current_website_id']) && $_POST['current_website_id'] != '') {
            //UPDATE
            $website_position_in_array = intval($_POST['current_website_id']);


            // Check if the index is valid
            if (isset($websites[$website_position_in_array])) {
                // Perform the swap
                $websites[$website_position_in_array] = $website_config;
            } else {
                // Handle the case where the index is not valid
                echo "Invalid website position in array";
            }
            update_option('ai_scraper_websites', $websites);
        }
        else {
            //ADD  NEW
            // Add the new configuration to the list and update the option
            $websites[] = $website_config;
            update_option('ai_scraper_websites', $websites);
        }
    }
}

add_action('wp_ajax_test_current_site', 'get_single_website_test_handler');
function get_single_website_test_handler() {
    
    if (isset($_POST['action']) && $_POST['action'] === 'test_current_site') {
        $websiteId = intval($_POST['website_id_in_array']);

        //get website configuration
        $websites = get_option('ai_scraper_websites', '');
        $websiteConfiguration = $websites[$websiteId];

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../scrape-and-post.php';
        $scrapedData = test_website_scrape($websiteConfiguration);

        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();
    }

}


add_action('wp_ajax_run_auto_post', 'run_auto_post_handler');
function run_auto_post_handler() {
    
        //get all websites configuration
        $websites = get_option('ai_scraper_websites', '');

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../scrape-and-post.php';

        $scrapedData = run_auto_post_all($websites);

        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();

}


add_action('wp_ajax_run_single_auto_post', 'run_single_auto_post_handler');
function run_single_auto_post_handler() {
    
        //get source
        $source = $_POST['single_source'];

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../scrape-and-post.php';

        $scrapedData = run_single_auto_post($source);
        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();

}


add_action('wp_ajax_display_today_log', 'autoai_display_today_log_shortcode');
//add_shortcode('autoai_display_today_log', 'autoai_display_today_log_shortcode');
function autoai_display_today_log_shortcode() {
    
    // Define the path to the log file
    $log_file_path = WP_PLUGIN_DIR . '/scraper/logs/' . date('Y-m-d') . '.log';

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
                $log_content .= htmlspecialchars($line) . "<br>";
            }

            fclose($handle);
        } else {
            $log_content = 'Error opening the log file.';
        }
    } else {
        $log_content = 'Todays log file still empty, try refresh or run a process.';
    }

    // Return the log content within a scrollable div
    echo '<div style="height: 300px; overflow-y: scroll; border: 1px solid #ccc; padding: 10px;" class="log-container">' . $log_content . '</div>';
    die();
}

add_action('wp_ajax_get_saved_sources', 'autoai_get_saved_sources_shortcode');
function autoai_get_saved_sources_shortcode() {
    
    //get sources option meta
    $sources = get_option('ai_scraper_websites', '');

    wp_send_json($sources);
    wp_die();    
}

