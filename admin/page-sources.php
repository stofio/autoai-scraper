<?php

require_once plugin_dir_path(__FILE__) . '/includes/handle_actions.php';

//check if its posted website ID, then edit
if(isset($_POST['selectedWebsite']) && $_POST['selectedWebsite'] == 'all') {
    $websiteIdToEdit = null;
}
else {
    $websiteIdToEdit = isset($_POST['selectedWebsite']) ? intval($_POST['selectedWebsite']) : null;

}
//get websites
$websites = get_option('ai_scraper_websites', '');

?>

<div class="page-container">
    <h1>Sources Management</h1>
    <form method="post" action="">
        <input type="hidden" name="selectedWebsite" value="<?php echo $websiteIdToEdit; ?>">
        <div class="select-site">
            <h2>Choose to edit from saved sources</h2>
            <select name="selectedWebsite" required>
                <option value="" disabled selected>- Select a source -</option>
                <option value="all">Add new</option>
                <?php
                foreach ($websites as $key => $website) {  
                    $websiteCatUrl = $website['baseUrl'];

                    if(is_int($websiteIdToEdit) && $websiteIdToEdit == $key) {
                        echo "<option value='$key' selected>$websiteCatUrl</option>";
                    }
                    else {
                        echo "<option value='$key'>$websiteCatUrl</option>";
                    }   
                }
                ?>
            </select>
            <input type="submit" name="" value="Select source">
        </div>
    </form>
    <br>

    <form method="post" action="">
        <h2><?php if(is_int($websiteIdToEdit)) : echo 'Edit'; else : echo 'Add new'; endif; ?> source configuration</h2>
        <div class="single-site container">
            <div class="left-column">

                <input type="hidden" name="action" value="add_website">
                <input type="hidden" name="current_website_id" value="<?php if(is_int($websiteIdToEdit)) echo $websiteIdToEdit; ?>">

                <div class="white-container">
                    <h3>Category page selectors</h3>

                    <label for="base_url">Category page URL: </label>
                    <input type="text" required name="baseUrl" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['baseUrl']; ?>"><br>


                    <label for="catPageLastArticle">Last article cat page selector: </label>
                    <input type="text" required name="catPageLastArticle" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['catPageLastArticle']; ?>">

                </div>
                <label for="categoryId">Save new post to category: </label>
                <select name="categoryId">
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
                            
                            if(is_int($websiteIdToEdit) && $cat_id == $websites[$websiteIdToEdit]['categoryId']) {
                                echo "<option value='$cat_id' selected>$cat_name</option>";
                            }
                            else {
                                echo "<option value='$cat_id'>$cat_name</option>";
                            }

                         }
                    ?>
                </select>
            </div>
            <div class="right-column">
                

                <div class="white-container">

                    <h3>Article page selectors</h3>                

                    <label for="base_url">Title selector: </label>
                    <input type="text" required name="title" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['title']; ?>">

                    <label for="base_url">Content selector: </label>
                    <input type="text" required name="content" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['content']; ?>">

                    <label for="base_url">Featured image url selector: </label>
                    <input type="text" required name="imageUrl" value="<?php if(is_int($websiteIdToEdit)) echo stripslashes(htmlspecialchars($websites[$websiteIdToEdit]['imageUrl'])); ?>">

                    <div style="display:flex;flex-wrap:nowrap; justify-content: space-between;">
                        <div style="flex: 1; margin-right: 20px;">
                            <label for="base_url">Article type selector: </label>
                            <input type="text" name="newsLabelSel" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['newsLabelSel']; ?>">
                        </div>
                        <div>
                            <label for="base_url">Article type text: </label>
                            <input type="text" name="newsLabelText" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['newsLabelText']; ?>"><br>
                        </div>
                    </div>
                    
                </div>
                

                <label for="base_url">Default Image Credit: </label>
                <input type="text" required name="defaultImageCredit" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['defaultImageCredit']; ?>">
            </div>
        </div>


        


        <input type="submit" value="Save <?php if(is_int($websiteIdToEdit)) : echo 'changes'; else : echo 'sources to list'; endif; ?>  ">
    </form>
</div>


<?php

if(is_int($websiteIdToEdit)) {
    echo '<h2>Editing current website configuration</h2>';
    ?>
        
    <?php
}
else {
    echo '<h2>All websites configuration list</h2>';
}

if(is_int($websiteIdToEdit)) {
    echo '<pre>';
    print_r($websites[$websiteIdToEdit]);
    echo '</pre>';
}
else {
    echo '<pre>';
    print_r($websites);
    echo '</pre>';
}

?>

<?php if(is_int($websiteIdToEdit)): ?>
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
                    website_id_in_array: <?php echo $websiteIdToEdit; ?>
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