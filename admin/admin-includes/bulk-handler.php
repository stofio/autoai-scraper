<?php

add_action('wp_ajax_scrapeai_process_url', 'scrapeai_process_url');

function scrapeai_process_url() {
    // Verify the nonce for security
    check_ajax_referer('scrapeai_bulk_scrape_nonce', 'nonce');
    
    require_once plugin_dir_path(__FILE__) . '../../includes/classes/clsMain.php';
    $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
    $theSource = getSourceByUrl($url);

    $main = new ScrapeAiMain();
    $postedUrl = $main->runSingleAutoPost($theSource, $url);

    if ($postedUrl) {
        wp_send_json_success(['message' => "URL processed successfully: $postedUrl"]);
    } else {
        wp_send_json_error(['message' => "Failed to process URL: $postedUrl"]);
    }
}



function get_posts_categories() {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    //get all post categories
     $categories = get_categories( array(
        'orderby' => 'name',
        'order'   => 'ASC'
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