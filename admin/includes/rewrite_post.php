<?php

// Hook the function to add the button to the admin bar
add_action('wp_before_admin_bar_render', 'add_custom_button_to_admin_bar');
// Add a button to the admin bar when viewing a single post
function add_custom_button_to_admin_bar() {
        global $wp_admin_bar;

        $post_id = get_the_ID();
        
        // Get the custom meta value
        $original_scr_url = get_post_meta($post_id, 'original_scr_url', true);

        if (!empty($original_scr_url)) {
            // Add the button to the admin bar
            $args = array(
                'id'    => 'rewriteBtn',
                'title' => 'Rewrite from origin',
                'href'  => '#',
                'meta'  => array('onclick' => 'showPopup("' . esc_url($original_scr_url) . '", ' . $post_id . ')'),
            );
            $wp_admin_bar->add_node($args);
        }
}


// Add JavaScript to the footer of the admin panel only if logged in and on a single post page
function add_custom_js_to_footer() {
        echo '<script>
            function showPopup(original_url, post_id) {
                var confirmResult = confirm("Are you sure you want to perform this action?");
                
                if (confirmResult) {
                	//show loading
                    //call ajax

                    //success
                    	//remove loading
                		//show popup to reload
                }
            }
        </script>';
}

// Hook the function to add JavaScript to the footer
add_action('admin_footer', 'add_custom_js_to_footer');


//get the postmeta url
//get post id

//import scraper
require_once plugin_dir_path(__FILE__) . 'scrape-and-post.php';

//run single

//get results

//change content, title and excerpt of the post

?>