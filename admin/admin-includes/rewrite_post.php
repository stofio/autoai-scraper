<?php

// Hook the function to add the button and JavaScript to the footer
add_action('wp_footer', 'add_custom_js_to_footer');
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
            'meta'  => array('onclick' => 'showPopup(`' . esc_url($original_scr_url) . '`, ' . $post_id . ')'),
        );
        $wp_admin_bar->add_node($args);
    }
}

// Add JavaScript to the footer of the admin panel only if logged in and on a single post page
function add_custom_js_to_footer() {
    echo '<script>
        function showPopup(original_url, post_id) {
            var confirmResult = confirm(`Rewrite the article ${original_url}? This article will be replaced with the new one`);
            
            if (confirmResult) {
                startRewriting(original_url, post_id);
            }
        }

        function startRewriting(original_url, post_id) {
            var overlay = document.createElement("div");
            overlay.style.position = "fixed";
            overlay.style.top = "0";
            overlay.style.left = "0";
            overlay.style.width = "100%";
            overlay.style.height = "100%";
            overlay.style.background = "rgba(255, 255, 255, 0.75)";
            overlay.style.zIndex = "9999";
            overlay.innerHTML = `<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;"><h3>Please wait, rewriting...<br>It may take up to 1 minute.</h3></div>`;

            document.body.appendChild(overlay);


            var data = {
                action: `rewrite_the_post`,
                original_url: original_url,
                post_id: post_id
            };

            var ajaxurl = `' . admin_url('admin-ajax.php') . '`;

            // Make WP AJAX call
            jQuery.post(ajaxurl, data, function(response) {
                console.log(response);
                if(response == `error`) {
                    alert(`Failed rewriting, error response. An error occured, check logs`);
                }

                
                else if(response == null) {
                    alert(`Failed rewriting, null response. An error occured, check logs`);
                }
                else {
                    // Handle the AJAX response
                    alert(`The article is rewritten. Reload the page`);    
                }

                
                // Remove the overlay after the AJAX call is complete
                document.body.removeChild(overlay);

                // You can add additional logic based on the AJAX response if needed
                // For example, show a reload popup or perform other actions
            });
        }

    </script>';
}


