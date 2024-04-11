<?php
add_action('add_meta_boxes', 'content_fetcher_add_meta_boxes');
add_action('save_post', 'content_fetcher_save_source_meta');
add_action('save_post', 'content_fetcher_save_post_categories');


function content_fetcher_add_meta_boxes() {
    // General Source Information
    add_meta_box(
        'content_fetcher_general_info', 
        'General Source Information', 
        'content_fetcher_general_info_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );

    // RSS Feed Specific Fields
    /*add_meta_box(
        'content_fetcher_rss_feed', 
        'RSS Feed Specific Fields', 
        'content_fetcher_rss_feed_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );*/

    // Web Scraping Specific Fields
    add_meta_box(
        'content_fetcher_web_scraping', 
        'Web Scraping Specific Fields', 
        'content_fetcher_web_scraping_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );

    // Scheduling and Publishing
    add_meta_box(
        'content_fetcher_scheduling_publishing', 
        'Scheduling and Publishing', 
        'content_fetcher_scheduling_publishing_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );

    // Content Handling and AI Processing
    add_meta_box(
        'content_fetcher_ai_processing', 
        'Content Handling and AI Processing', 
        'content_fetcher_ai_processing_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );

    // Miscellaneous
    add_meta_box(
        'content_fetcher_miscellaneous', 
        'Miscellaneous', 
        'content_fetcher_miscellaneous_callback', 
        'sources_cpt', 
        'normal', 
        'default'
    );

    //Category
    add_meta_box(
        'content_fetcher_category',          
        'Category to save',      
        'content_fetcher_category_callback', 
        'sources_cpt',                    
        'side',                    
        'default'                  
    );
}

function content_fetcher_general_info_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');


    $saved_source_type = get_post_meta($post->ID, '_content_fetcher_source_type', true);
    $source_types = [
        'scraping' => 'Scraping',
       // 'rss' => 'RSS'
    ];

    ?>
        <label>Source type: </label>
        <select name="selectSourceType">
            <?php foreach ($source_types as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" 
                    <?php selected($saved_source_type, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>
    <?php
}

function content_fetcher_rss_feed_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');
    ?>
    <div>
        <label>RSS feed URL: </label>
        <input type="text" name="rssUrl" placeholder="https://..." value=""><br>
    </div>
    <?php
}

function content_fetcher_web_scraping_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    $saved_base_url = get_post_meta($post->ID, '_content_fetcher_scraping_url', true);
    $saved_title_selector = get_post_meta($post->ID, '_content_fetcher_title_selector', true);
    $saved_content_selector = get_post_meta($post->ID, '_content_fetcher_content_selector', true);
    $saved_image_url_selector = get_post_meta($post->ID, '_content_fetcher_image_url_selector', true);
    $saved_news_label_selector = get_post_meta($post->ID, '_content_fetcher_article_type_selector', true);
    $saved_news_label_text = get_post_meta($post->ID, '_content_fetcher_article_type_text', true);

    ?>
    <div>

        <div class="meta-box-inner">
            <h3><b>Source page</b><img class="question-hint" id="hintCatPage" src="<?php echo plugins_url('../assets/question.svg', __FILE__); ?>" /></h3>

            <label>URL: </label>
            <input type="text" name="baseUrl" placeholder="https://..." value="<?php echo esc_attr($saved_base_url); ?>"><br>
            
        </div>

        <div class="meta-box-inner">
            <h3><b>Article page selectors</b></h3>

            <label>Title selector: </label>
            <input type="text" name="title" placeholder="CSS selector" value="<?php echo esc_attr($saved_title_selector); ?>"><br><br>

            <label>Content selector: </label>
            <input type="text" name="content" placeholder="CSS selector" value="<?php echo esc_attr($saved_content_selector); ?>"><br><br>

            <label>Featured image url selector: </label>
            <input type="text" name="imageUrl" placeholder="CSS selector" value="<?php echo esc_attr($saved_image_url_selector); ?>"><br><br>

            <label>Article type selector (optional): </label>
            <input type="text" name="newsLabelSel" placeholder="CSS selector" value="<?php echo esc_attr($saved_news_label_selector); ?>"><br><br>

            <label>Article type text (optional): </label>
            <input type="text" name="newsLabelText" placeholder="ex. News" value="<?php echo esc_attr($saved_news_label_text); ?>"><br><br>
        </div>

        <div class="meta-box-inner test-url">
            <label for="test_source_url">Test the source by entering the URL of a source article</label><br>
            <input type="text" id="test_source_url" name="test_source_url" placeholder="URL">
            <button id="testSourceButton" class="button">Test</button>
        </div>

    </div>
    <?php
}


function content_fetcher_scheduling_publishing_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    $saved_cat_page_last_article = get_post_meta($post->ID, '_content_fetcher_category_last_article', true);
    $saved_cat_page_list_container = get_post_meta($post->ID, '_content_fetcher_category_list_container', true);
    $saved_run_daily = get_post_meta($post->ID, '_content_fetcher_run_daily', true);
    //$saved_fetch_times = get_post_meta($post->ID, '_content_fetcher_fetch_times', true);
    $saved_post_status = get_post_meta($post->ID, '_content_fetcher_post_status', true);

    // Define the options for fetch times and post status
    $fetch_times_options = [1, 2, 3, 10, 30];
    $post_status_options = ['publish' => 'Publish', 'draft' => 'Draft'];

    ?>
    <div>

        <h3><b>Schedule</b></h3>
        <label>Run daily check 
            <input type="checkbox" name="runDaily" <?php checked($saved_run_daily, '1'); ?> />
        </label><br>
        <div class="meta-box-inner">
            <h4>Category page</h4>
            <label>Articles list container: </label>
            <input type="text" name="postsListContainer" placeholder="CSS selector" value="<?php echo esc_attr($saved_cat_page_list_container); ?>"><br> 
            <label>Last article selector from source URL (the first a href of the first article): </label>
            <input type="text" name="catPageLastArticleHref" placeholder="CSS selector" value="<?php echo esc_attr($saved_cat_page_last_article); ?>"><br> 
            <button id="testCategoryButton" class="button">Test</button>
        </div>
        

        <h3><b>Publishing</b></h3>

        <label>After rewriting: </label>
        <select name="selectPostStatus">
            <?php foreach ($post_status_options as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" 
                    <?php selected($saved_post_status, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>


    </div>
    <?php
}

function content_fetcher_ai_processing_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    $saved_splitting_type = get_post_meta($post->ID, '_content_fetcher_splitting_type', true);
    $saved_splitting_words = get_post_meta($post->ID, '_content_fetcher_splitting_words', true);
    $saved_title_excerpt = get_post_meta($post->ID, '_content_fetcher_title_excerpt', true);
    $saved_piece_article = get_post_meta($post->ID, '_content_fetcher_piece_article', true);
    $saved_table = get_post_meta($post->ID, '_content_fetcher_table', true);

    $splitting_types = [
        '0' => 'Automatically (recommended)',
        '1' => '1 paragraph at a time'
    ];

    ?>
    <div>

        <h3><b>AI rewriting settings</b></h3>

        <!-- <label>Content splitting: </label>
        <select name="selectSplittingType">
            <?php //foreach ($splitting_types as $value => $label): ?>
                <option value="<?php //echo esc_attr($value); ?>" 
                    <?php //selected($saved_splitting_type, $value); ?>>
                    <?php// echo esc_html($label); ?>
                </option>
            <?php //endforeach; ?>
        </select><br><br> -->

        <label>Max words per chunk (aprox.): </label>
        <input type="number" name="maxChunkWords" min="20" max="3000" value="<?php echo $saved_splitting_words ? esc_attr($saved_splitting_words) : 300 ?>"><br><br>


        <h3><b>Prompts instructions</b></h3>

        <label>Title and excerpt: </label><br>
        <textarea name="promptTitleExcerpt"><?php echo esc_textarea($saved_title_excerpt); ?></textarea><br><br>

        <label>Article chunk: </label><br>
        <textarea name="promptPieceArticle"><?php echo esc_textarea($saved_piece_article); ?></textarea><br><br>

        <label>Table: </label><br>
        <textarea name="promptTable"><?php echo esc_textarea($saved_table); ?></textarea><br><br>

    </div>
    <?php
}

function content_fetcher_miscellaneous_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    $saved_get_images = get_post_meta($post->ID, '_content_fetcher_get_images', true);
    $saved_get_tables = get_post_meta($post->ID, '_content_fetcher_get_tables', true);
    $saved_images_credit = get_post_meta($post->ID, '_content_fetcher_images_credit', true);

    ?>
    <div>

        <h3><b>Other settings</b></h3>

        <label>Get tables
            <input type="checkbox" name="toGetTables" <?php echo ($saved_get_tables === '1' || $saved_get_tables === '') ? 'checked' : ''; ?> />
        </label><br>

        <label>Get images   
            <input type="checkbox" name="toGetImages" <?php echo ($saved_get_images === '1' || $saved_get_images === '') ? 'checked' : ''; ?> />
        </label><br><br>

        <label>Custom image caption: </label>
        <input type="text" name="imagesCredit" placeholder="image credits" value="<?php echo esc_attr($saved_images_credit); ?>"><br><br>

        
    </div>
    <?php
}

function content_fetcher_category_callback($post) {
    wp_nonce_field(plugin_basename(__FILE__), 'content_fetcher_nonce');

    //get all post categories
     $categories = get_categories( array(
        'orderby' => 'name',
        'order'   => 'ASC'
    ) );

    // Retrieve saved category data
    $current_main_cat_id = get_post_meta($post->ID, '_content_fetcher_main_category', true);
    $current_additional_cat_ids = get_post_meta($post->ID, '_content_fetcher_additional_categories', true);
    if (!is_array($current_additional_cat_ids)) {
        $current_additional_cat_ids = array();
    }

    // Start the select element for the main category
    echo '<p>Select the Main Category:</p>';
    echo '<div>';
    foreach ($categories as $category) {
        $cat_id = $category->term_id;
        $cat_name = $category->name;

        if ($cat_id == 1) continue; // Skip the "Uncategorized" category

        // Use radio buttons for selecting the main category
        $checked = ($cat_id == $current_main_cat_id) ? 'checked' : '';
        echo "<label><input type='radio' name='main_category_id' value='$cat_id' $checked> $cat_name</label><br>";
    }
    echo '</div>';

    // Start the checkboxes for additional categories
    echo '<p>Select Additional Categories:</p>';
    echo '<div>';
    foreach ($categories as $category) {
        $cat_id = $category->term_id;
        $cat_name = $category->name;

        if ($cat_id == 1 || $cat_id == $current_main_cat_id) continue;

        // Use checkboxes for additional categories
        $checked = in_array($cat_id, $current_additional_cat_ids) ? 'checked' : '';
        echo "<label><input type='checkbox' name='additional_category_ids[]' value='$cat_id' $checked> $cat_name</label><br>";
    }
    echo '</div>';

}

function content_fetcher_save_source_meta($post_id) {
    // Verify the nonce before proceeding.
    if (!isset($_POST['content_fetcher_nonce']) || !wp_verify_nonce($_POST['content_fetcher_nonce'], plugin_basename(__FILE__)))
        return;

    // Save Scraping URL
    if (isset($_POST['baseUrl'])) {
        update_post_meta($post_id, '_content_fetcher_scraping_url', sanitize_text_field($_POST['baseUrl']));
    }

    // Save Last Article href Selector
    if (isset($_POST['catPageLastArticleHref'])) {
        update_post_meta($post_id, '_content_fetcher_category_last_article', sanitize_text_field($_POST['catPageLastArticleHref']));
    }

    // Save Posts List Container
    if (isset($_POST['postsListContainer'])) {
        update_post_meta($post_id, '_content_fetcher_category_list_container', sanitize_text_field($_POST['postsListContainer']));
    }

    // Save Source Type
    if (isset($_POST['selectSourceType'])) {
        update_post_meta($post_id, '_content_fetcher_source_type', sanitize_text_field($_POST['selectSourceType']));
    }

    // Save Title Selector
    if (isset($_POST['title'])) {
        update_post_meta($post_id, '_content_fetcher_title_selector', sanitize_text_field($_POST['title']));
    }

    // Save Content Selector
    if (isset($_POST['content'])) {
        update_post_meta($post_id, '_content_fetcher_content_selector', sanitize_text_field($_POST['content']));
    }

    // Save Featured Image URL Selector
    if (isset($_POST['imageUrl'])) {
        update_post_meta($post_id, '_content_fetcher_image_url_selector', sanitize_text_field($_POST['imageUrl']));
    }

    // Save Article Type Selector
    if (isset($_POST['newsLabelSel'])) {
        update_post_meta($post_id, '_content_fetcher_article_type_selector', sanitize_text_field($_POST['newsLabelSel']));
    }

    // Save Article Type Text
    if (isset($_POST['newsLabelText'])) {
        update_post_meta($post_id, '_content_fetcher_article_type_text', sanitize_text_field($_POST['newsLabelText']));
    }

    // Save Content Splitting Type
    // if (isset($_POST['selectSplittingType'])) {
    //     update_post_meta($post_id, '_content_fetcher_splitting_type', sanitize_text_field($_POST['selectSplittingType']));
    // }

    // Save Content Splitting Words Number
    if (isset($_POST['maxChunkWords'])) {
        update_post_meta($post_id, '_content_fetcher_splitting_words', sanitize_text_field($_POST['maxChunkWords']));
    }

    // Save Title and Excerpt
    if (isset($_POST['promptTitleExcerpt'])) {
        update_post_meta($post_id, '_content_fetcher_title_excerpt', sanitize_textarea_field($_POST['promptTitleExcerpt']));
    }

    // Save Piece of Article
    if (isset($_POST['promptPieceArticle'])) {
        update_post_meta($post_id, '_content_fetcher_piece_article', sanitize_textarea_field($_POST['promptPieceArticle']));
    }

    // Save Table
    if (isset($_POST['promptTable'])) {
        update_post_meta($post_id, '_content_fetcher_table', sanitize_textarea_field($_POST['promptTable']));
    }

    // Save Run Daily Check
    $runDaily = isset($_POST['runDaily']) ? '1' : '0';
    update_post_meta($post_id, '_content_fetcher_run_daily', $runDaily);

    // Save Fetch Source Times a Day
    // if (isset($_POST['selectFetchTimesADay'])) {
    //     update_post_meta($post_id, '_content_fetcher_fetch_times', intval($_POST['selectFetchTimesADay']));
    // }

    // Save Post Status After Rewriting
    if (isset($_POST['selectPostStatus'])) {
        update_post_meta($post_id, '_content_fetcher_post_status', sanitize_text_field($_POST['selectPostStatus']));
    }

    // Save Get Images
    $toGetImages = isset($_POST['toGetImages']) ? '1' : '0';
    update_post_meta($post_id, '_content_fetcher_get_images', $toGetImages);

    // Save Images Credit
    if (isset($_POST['imagesCredit'])) {
        update_post_meta($post_id, '_content_fetcher_images_credit', sanitize_text_field($_POST['imagesCredit']));
    }

    // Save Get Tables
    $toGetTables = isset($_POST['toGetTables']) ? '1' : '0';
    update_post_meta($post_id, '_content_fetcher_get_tables', $toGetTables);
}


function content_fetcher_save_post_categories($post_id) {
    // Verify the nonce before proceeding.
    if (!isset($_POST['content_fetcher_nonce']) || !wp_verify_nonce($_POST['content_fetcher_nonce'], plugin_basename(__FILE__)))
        return;

    // Save Main Category
    if (isset($_POST['main_category_id'])) {
        $main_category_id = intval($_POST['main_category_id']);
        update_post_meta($post_id, '_content_fetcher_main_category', $main_category_id);
    }

    // Save Additional Categories
    if (isset($_POST['additional_category_ids'])) {
        $additional_category_ids = array_map('intval', $_POST['additional_category_ids']);
        update_post_meta($post_id, '_content_fetcher_additional_categories', $additional_category_ids);
    }
}

