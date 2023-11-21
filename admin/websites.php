<?php

require_once plugin_dir_path(__FILE__) . '/includes/handle_websites.php';

//check if is selected website to edit
if($_POST['selectedWebsite'] == 'all') {
    $websiteIdToEdit = null;
}
else {
    $websiteIdToEdit = isset($_POST['selectedWebsite']) ? intval($_POST['selectedWebsite']) : null;

}

//get websites
$websites = get_option('ai_scraper_websites', '');




?>

<div class="page-container">
    <h1>Websites Management</h1>
    <form method="post" action="">
        <input type="hidden" name="selectedWebsite" value="<?php echo $websiteIdToEdit; ?>">
        <div class="select-site">
            <h2>Choose a website to edit from your list</h2>
            <select name="selectedWebsite" required>
                <option value="" disabled selected>- Select a website -</option>
                <option value="all">New</option>
                <?php

                //var_dump($websites[0]);
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
            <input type="submit" name="" value="Select website">
        </div>
    </form>
    <br>

    <form method="post" action="">
        <h2><?php if(is_int($websiteIdToEdit)) : echo 'Edit'; else : echo 'Add new'; endif; ?> website configuration</h2>
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

                            if($cat_id == $websites[$websiteIdToEdit]['categoryId']) {
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
                            <label for="base_url">Breadcrumb category label selector: </label>
                            <input type="text" required name="newsLabelSel" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['newsLabelSel']; ?>">
                        </div>
                        <div>
                            <label for="base_url">Category label to check text: </label>
                            <input type="text" required name="newsLabelText" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['newsLabelText']; ?>"><br>
                        </div>
                    </div>
                    
                </div>
                

                <label for="base_url">Default Image Credit: </label>
                <input type="text" required name="defaultImageCredit" value="<?php if(is_int($websiteIdToEdit)) echo $websites[$websiteIdToEdit]['defaultImageCredit']; ?>">
            </div>
        </div>


        


        <input type="submit" value="Save <?php if(is_int($websiteIdToEdit)) : echo 'changes'; else : echo 'websites to list'; endif; ?>  ">
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
<?php endif; ?>


<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#testWebsite').on('click', () => {
            
        });
    });
</script>


<div class="test-results">
    <p>Processing... <span><img style="width: 30px" src="<?php echo plugins_url('../loading-gif.gif', __FILE__); ?>"></span></p>
</div>