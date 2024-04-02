<?php

add_action('wp_ajax_test_current_site', 'get_single_website_test_handler');

add_action('wp_ajax_delete_source', 'delete_source_handler');

add_action('wp_ajax_run_auto_post', 'run_auto_post_handler'); //deprecated?

add_action('wp_ajax_runSingleAutoPost', 'runSingleAutoPost_handler');

add_action('wp_ajax_run_post_from_url', 'run_post_from_url');

add_action('wp_ajax_display_today_log', 'autoai_display_today_log_shortcode');
//add_shortcode('autoai_display_today_log', 'autoai_display_today_log_shortcode');

add_action('wp_ajax_get_saved_sources', 'autoai_get_saved_sources_shortcode');

// Handle the AJAX call
add_action('wp_ajax_rewrite_the_post', 'rewrite_the_post_callback');



//function used in main plugin file
function save_source() {    
    if(isset($_POST['action'])) :
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['action'] == 'add_website') {

        $submitted_baseUrl = sanitize_text_field($_POST['baseUrl']);

        // Get existing websites
        $websites = get_option('ai_scraper_websites', []);
        if (!is_array($websites)) {
            $websites = [];
        }

        $website_config = [
            'baseUrl' => sanitize_text_field($_POST['baseUrl']),
            'categoryId' => $_POST['categoryId'],
            'catPageLastArticle' => sanitize_text_field($_POST['catPageLastArticle']),
            'title' => sanitize_text_field($_POST['title']),
            'content' => sanitize_text_field($_POST['content']),
            'imageUrl' => sanitize_text_field($_POST['imageUrl']),
            'newsLabelSel' => sanitize_text_field($_POST['newsLabelSel']),
            'newsLabelText' => sanitize_text_field($_POST['newsLabelText']),
            'defaultImageCredit' => sanitize_text_field($_POST['defaultImageCredit']),
            'runDaily' => isset($_POST['runDaily']) ? $_POST['runDaily'] : '',
            'pTagsNumber' => $_POST['pTagsNumber'],
            'promptToAppend' => sanitize_text_field($_POST['promptToAppend'])
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

        //schedule cron jobs
        require_once plugin_dir_path(__FILE__) . 'schedule_cron_jobs_for_websites.php';
        schedule_cron_jobs_for_sources();
    }
    endif;
}



function get_single_website_test_handler() {
    
    if (isset($_POST['action']) && $_POST['action'] === 'test_current_site') {
        $websiteId = intval($_POST['website_id_in_array']);

        //get website configuration
        $websites = get_option('ai_scraper_websites', '');
        $websiteConfiguration = $websites[$websiteId];

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../includes/main.php';
        $scrapedData = test_website_scrape($websiteConfiguration);

        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();
    }

}

function delete_source_handler() {
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete_source') {
        $sourceId = intval($_POST['sourceId']);

        //get website configuration
        $websites = get_option('ai_scraper_websites', '');

        if (isset($websites[$sourceId])) {
            unset($websites[$sourceId]);
           // $websites = array_values($websites); // Optional: re-index the array if needed
        }

        update_option('ai_scraper_websites', $websites);

        //schedule cron jobs
        require_once plugin_dir_path(__FILE__) . 'schedule_cron_jobs_for_websites.php';
        schedule_cron_jobs_for_sources();
        
        wp_send_json_success(array('message' => 'Deleted!'));

        wp_die();
    }

}


function run_auto_post_handler() {
    
        //get all websites configuration
        $websites = get_option('ai_scraper_websites', '');

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../includes/main.php';

        $scrapedData = run_auto_post_all($websites);

        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();

}


function runSingleAutoPost_handler() {
    
        //get source
        $source = $_POST['single_source'];

        //scrape
        require_once plugin_dir_path(__FILE__) . '../../includes/main.php';

        $scrapedData = runSingleAutoPost($source);
        // Send the array back to JavaScript
        wp_send_json($scrapedData);

        wp_die();

}


function run_post_from_url() {

        require_once plugin_dir_path(__FILE__) . '../../includes/main.php';
    
        $url = $_POST['url'];

        //get source
        $theSource = getSourceByUrl($url);

        if($theSource == null) {
            my_second_log('ERROR', 'There is no configuration for this website url');
            echo 'errorNoConfiguration';
        }
        else {
            $postedUrl = runSingleAutoPost($theSource, $url);
            if($postedUrl == null) {
                echo '';
            }
            else {
                echo $postedUrl;
            }
        }
        wp_die();
}




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


function autoai_get_saved_sources_shortcode() {
    
    //get sources option meta
    $sources = get_option('ai_scraper_websites', '');

    wp_send_json($sources);
    wp_die();    
}

function rewrite_the_post_callback() {
    // Get the data sent in the AJAX request
    $original_url = $_POST['original_url'];
    $post_id = $_POST['post_id'];

    require_once plugin_dir_path(__FILE__) . '../../includes/main.php';

    $theSource = getSourceByUrl($original_url);

    rewritePostAndPost($theSource, $original_url, $post_id);
    
    // Send a response back to the JavaScript
    wp_send_json_success('Rewriting completed successfully.');
    
    // Make sure to exit after sending the JSON response
    exit();
}