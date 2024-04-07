<?php

require_once plugin_dir_path(__FILE__) . '/admin-includes/clsBulkHandler.php';
$bulk = new BulkHandler();

?>

<div class="wrap" id="autoai-bulk-scrape-form">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
        <h2>Enter URLs</h2>
        <p>(One URL per line, no empty spaces and no empty new-line)</p>
        <textarea name="urls" rows="10" class="large-text code"></textarea>
        
        <div class="double-column-container">
            <div class="half-col">
                <h2>Select Categories</h2>
                <label><input type='checkbox' name='is_default_categories'>Use source default categories</label>
                <?php $bulk->get_posts_categories(); ?>
            </div>

            <div class="half-col">
                <!-- <h2>Tags</h2>
                <input type="text" name="tags" class="large-text" placeholder="(separated by commas)"> -->
                <h2>Posting</h2>
                <div>
                    <input type="radio" id="default" name="post_status" value="default" checked>
                    <label for="default">Source default</label>
                </div>
                <div>
                    <input type="radio" id="publish" name="post_status" value="publish">
                    <label for="publish">Publish</label>
                </div>
                <div>
                    <input type="radio" id="draft" name="post_status" value="draft">
                    <label for="draft">Draft</label>
                </div>
                <div>
                    <input type="radio" id="schedule" name="post_status" value="schedule">
                    <label for="start-date">Schedule from</label>
                    <input type="datetime-local" id="start-date" name="start-date">
                    <label for="interval">each (hours): </label>
                    <input type="number" id="interval" name="interval" min="1" max="999" value="24">
                </div>
            </div>
        </div>
        <br>
        <p>
            <input type="button" id="submit-bulk-scrape" class="button button-primary" value="Start Bulk Scrape">
            <!-- <input type="button" id="stop-bulk-scrape" class="button" value="Stop Bulk Scrape" disabled> -->
            <p id="loadingTest" style="display:none">Processing... <span><img style="width: 30px" src="<?php echo plugins_url('../assets/loading-gif.gif', __FILE__); ?>"></span></p>
        </p>
        
</div>

<div id="bulk-scrape-progress" class="wrap">
    <br>
    <h3>Progress</h3>
        
    <!-- Progress bar -->
    <div id="bulk-progress-bar">
        <div id="progress" style="width: 0%;"><span>0</span>%</div>
    </div>

    <div id="progress-messages">

    </div>
</div>