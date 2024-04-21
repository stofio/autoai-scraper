jQuery(document).ready(function($) {

    $('.rewrite-post-btn').on('click', function(e) {
        var fullID = $(e.target).closest('li').attr('id');
        var postID = fullID.replace('wp-admin-bar-', '');
        startRewritingPost(postID);
    });

    function startRewritingPost(postID) {
        //get original post url
        $.ajax({
            url: rewritePost.ajax_url, 
            method: 'POST',
            data: {
                action: 'get_original_url_by_id',
                post_id: postID,
                nonce: rewritePost.nonce
            },
            success: function(response) {
                var originalUrl = response.data;
                if(!confirm(`Rewrite the article ${originalUrl}? This article will be replaced with the new one.`)) return;
                var overlay = document.createElement("div");
                addOverlay(overlay);

                $.ajax({
                    url: rewritePost.ajax_url, 
                    method: 'POST',
                    data: {
                        action: 'rewrite_post',
                        post_id: postID,
                        nonce: rewritePost.nonce
                    },
                    success: function(response) {
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

                    }
                });

            }
        });

        return;


        
        
    }

    function addOverlay(overlay) {
        overlay.style.position = "fixed";
        overlay.style.top = "0";
        overlay.style.left = "0";
        overlay.style.width = "100%";
        overlay.style.height = "100%";
        overlay.style.background = "rgba(255, 255, 255, 0.75)";
        overlay.style.zIndex = "9999";
        overlay.innerHTML = `<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;"><h3>Please wait, rewriting...<br>It could take a few minutes.</h3></div>`;

        document.body.appendChild(overlay);
    }


});