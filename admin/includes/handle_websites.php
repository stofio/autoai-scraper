<?php
function my_log($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    $message = date('Y-m-d H:i:s') . " " . $message;
    file_put_contents(dirname(plugin_dir_path(__FILE__)) . '/debug.log', $message . PHP_EOL, FILE_APPEND);
}



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