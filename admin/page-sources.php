<?php

require_once plugin_dir_path(__FILE__) . '/includes/handle_actions.php';

//check if its posted website ID, then edit
if(isset($_POST['selectedWebsite']) && $_POST['selectedWebsite'] == 'all') {
    $websiteIdToEditInJson = null;
}
else {
    $websiteIdToEditInJson = isset($_POST['selectedWebsite']) ? intval($_POST['selectedWebsite']) : null;

}
//get websites
$websites = get_option('ai_scraper_websites', '');

?>

<div class="page-container">
    <h1>Sources Management</h1>
    <form method="post" action="">
        <input type="hidden" name="selectedWebsite" value="<?php echo $websiteIdToEditInJson; ?>">
        <div class="select-site">
            <h2>Choose to edit from saved sources</h2>
            <select name="selectedWebsite" required>
                <option value="" disabled selected>- Select a source -</option>
                <option value="all">Add new</option>
                <?php
                foreach ($websites as $key => $website) {  
                    $websiteCatUrl = $website['baseUrl'];

                    if(is_int($websiteIdToEditInJson) && $websiteIdToEditInJson == $key) {
                        echo "<option value='$key' selected>$websiteCatUrl</option>";
                    }
                    else {
                        echo "<option value='$key'>$websiteCatUrl</option>";
                    }   
                }
                ?>
            </select>
            <input type="submit" name="" value="Select source"><br>

            

            <?php if(is_int($websiteIdToEditInJson)) : ?>
                    <a id="deleteSource" href="#" onclick="deleteSource(<?php echo $websiteIdToEditInJson; ?>)">Delete selected source</a>
            <?php endif; ?>
        </div>
    </form>
    <br>

    <form method="post" action="">
        <h2><?php if(is_int($websiteIdToEditInJson)) : echo 'Edit'; else : echo 'Add new'; endif; ?> source configuration</h2>
        <div class="single-site container">
            <div class="left-column">

                <input type="hidden" name="action" value="add_website">
                <input type="hidden" name="current_website_id" value="<?php if(is_int($websiteIdToEditInJson)) echo $websiteIdToEditInJson; ?>">

                <div class="white-container">
                    <h3>Category page selectors</h3>

                    <label for="base_url">Category page URL: </label>
                    <input type="text" required name="baseUrl" value="<?php if(is_int($websiteIdToEditInJson)) echo $websites[$websiteIdToEditInJson]['baseUrl']; ?>"><br>


                    <label for="catPageLastArticle">Last article cat page selector (takes first a href in the selected box): </label>
                    <input type="text" required name="catPageLastArticle" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['catPageLastArticle'])); ?>">

                </div>
                <label for="categoryId">Save new post to category: </label>
                <select name="categoryId[]" multiple required>
                    <?php 
                        //get all post categories
                         $categories = get_categories( array(
                            'orderby' => 'name',
                            'order'   => 'ASC'
                        ) );


                         foreach ($categories as $key => $category) {
                            $cat_id = $category->term_id;
                            $cat_name = $category->name;

                            if($cat_id == 1) continue;
                            

                            if( is_int($websiteIdToEditInJson) && in_array($cat_id, $websites[$websiteIdToEditInJson]['categoryId']) ) {
                                echo "<option value='$cat_id' selected>$cat_name</option>";
                            }
                            else {
                                echo "<option value='$cat_id'>$cat_name</option>";
                            }

                         }
                    ?>
                </select>

                <br><br>
                <div class="white-container">
                    <h3>Other sattings</h3>
                    <label>Number of paragraphs (html p tags) to rewrite per request - best with range from 300-800 words in total per p-tags group</label>
                    <input type="number" name="pTagsNumber" min="1" max="50" step="1" placeholder="from 1 to 50" 
                    value="<?php if(is_int($websiteIdToEditInJson) && isset($websites[$websiteIdToEditInJson]['pTagsNumber'])) echo $websites[$websiteIdToEditInJson]['pTagsNumber']; ?>">

                    <label>Prompt to append to each request (p tags group), give instructions on the rewritten Output like tone or style.</label>
                    <textarea name="promptToAppend"><?php 
                        if(is_int($websiteIdToEditInJson) && isset($websites[$websiteIdToEditInJson]['promptToAppend'])) {
                            echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['promptToAppend']));
                        }
                        ?></textarea>



                    <br><br><br><br><br>
                    <label>Run daily check
                        <input type="checkbox" name="runDaily" <?php if(is_int($websiteIdToEditInJson) && $websites[$websiteIdToEditInJson]['runDaily'] === 'on') echo 'checked'; ?> />
                    </label>
                </div>
            </div>
            <div class="right-column">
                
                <div class="white-container">

                    <h3>Article page selectors</h3>                

                    <label for="base_url">Title selector: </label>
                    <input type="text" required name="title" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['title'])); ?>">

                    <label for="base_url">Content selector: </label>
                    <input type="text" required name="content" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['content'])); ?>">

                    <label for="base_url">Featured image url selector: </label>
                    <input type="text" required name="imageUrl" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['imageUrl'])); ?>">

                    <div style="display:flex;flex-wrap:nowrap; justify-content: space-between;">
                        <div style="flex: 1; margin-right: 20px;">
                            <label for="base_url">Article type selector (optional): </label>
                            <input type="text" name="newsLabelSel" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['newsLabelSel'])); ?>">
                        </div>
                        <div>
                            <label for="base_url">Article type text (optional): </label>
                            <input type="text" name="newsLabelText" value="<?php if(is_int($websiteIdToEditInJson)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEditInJson]['newsLabelText'])); ?>"><br>
                        </div>
                    </div>
                    
                </div>
                
                <label for="base_url">Default Image Credit: </label>
                <input type="text" required name="defaultImageCredit" value="<?php if(is_int($websiteIdToEditInJson)) echo $websites[$websiteIdToEditInJson]['defaultImageCredit']; ?>">
            </div>
        </div>


        


        <input type="submit" value="Save <?php if(is_int($websiteIdToEditInJson)) : echo 'changes'; else : echo 'sources to list'; endif; ?>  ">
    </form>
</div>


<?php

if(is_int($websiteIdToEditInJson)) {
    echo '<h2>Editing current website configuration</h2>';
    ?>
        
    <?php
}
else {
    echo '<h2>All websites configuration list</h2>';
}

if(is_int($websiteIdToEditInJson)) {
    echo '<pre>';
    print_r($websites[$websiteIdToEditInJson]);
    echo '</pre>';
}
else {
    echo '<pre>';
    print_r($websites);
    echo '</pre>';
}

?>

<?php if(is_int($websiteIdToEditInJson)): ?>
<div class="test-wesite">
    <button id="testWebsite">Test the current site</button>   
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#testWebsite').on('click', () => {
            $('#loadingTest').css('display', 'block');

            $.ajax({
                url: ajaxurl, 
                type: 'POST',
                data: {
                    action: 'test_current_site', 
                    website_id_in_array: <?php echo $websiteIdToEditInJson; ?>
                },
                success: function(scrapedData) {
                    if(!scrapedData) {
                        //error, check logs
                        $('.test-results').append('<p><b>SCRAPING FAILED</b> - check logs for info</p>');
                    }
                    else if (typeof scrapedData === 'object' && scrapedData !== null) {
                        $('.test-results .the_title').append(scrapedData.title);
                        $('.test-results .the_content').append(scrapedData.content);
                        $('.test-results .the_image_url').append(scrapedData['img-url']);
                        $('.test-results .the_image_credit').append(scrapedData['img-credit']);
                        $('.test-results .the_post_url').append(scrapedData['original-url']);
                        $('.test-results .white-container').css('display', 'block');
                    }
                    else {
                        //show response error maybe
                        $('.test-results').append('<p><b>ERROR</b> - an error occured with scraped data</p>');
                    }

                    
                    $('#loadingTest').css('display', 'none');    
                    $('#testWebsite').css('display', 'none')
                    $('.test-results').css('display', 'block');
                },
                error: function(error) {
                    console.error(error);
                }
            });
        });

        $('#deleteSource').on('click', () => {
            if(confirm('Delete this source?')) {

                $.ajax({
                    url: ajaxurl, 
                    type: 'POST',
                    data: {
                        action: 'delete_source', 
                        sourceId: <?php echo $websiteIdToEditInJson; ?>,
                    },
                    success: function(response) { 
                        if (response.success) {
                            location.reload();
                        }
                    },
                    error: function(error) {
                        console.error(error);
                    }
                });
            }
        });
    });
</script>
<?php endif; ?>



<p id="loadingTest" style="display:none">Processing... <span><img style="width: 30px" src="<?php echo plugins_url('../loading-gif.gif', __FILE__); ?>"></span></p>
<div class="test-results" style="margin-top: 30px;display:none">
    <div class="white-container" style="display:none">
        <h3>[ Scraped article URL ]:</h3>
        <p class="the_post_url"></p>
        <h3>[ Title ]:</h3>
        <p class="the_title"></p>
        <h3>[ Content ]:</h3>
        <p class="the_content"></p>
        <h3>[ Image URL ]:</h3>
        <p class="the_image_url"></p>
    </div>

</div>