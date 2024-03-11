<?php

require_once plugin_dir_path(__FILE__) . '/admin-includes/bulk-handler.php';

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <form id="autoai-bulk-scrape-form" method="post">
        
        <h2>Enter URLs</h2>
        <p>(One URL per line, no empty spaces and no empty new-line)</p>
        <textarea name="urls" rows="10" class="large-text code"></textarea>
        
        <h2>Select Categories</h2>
        <label><input type='checkbox' name='is_default_categories' value='$cat_id'>Use source categories</label>
        <?php get_posts_categories(); ?>

        <h2>Tags (separated by commas)</h2>
        <input type="text" name="tags" class="large-text">
        
        <h2>Post Options</h2>
        <div>
            <input type="radio" id="publish" name="post_status" value="publish" checked>
            <label for="publish">Publish Immediately</label>
        </div>
        <div>
            <input type="radio" id="draft" name="post_status" value="draft">
            <label for="draft">Save as Draft</label>
        </div>
        
        <p>
            <input type="button" id="submit-bulk-scrape" class="button button-primary" value="Start Bulk Scrape">
        </p>
    </form>
</div>

<div id="bulk-scrape-progress">

</div>