<?php

require_once plugin_dir_path(__FILE__) . '/admin-includes/handle-actions.php';

?>

<div class="page-container">
    <h1>AI Auto Post From URL</h1>
    <div class="white-container">

        <p>First add configurations in Sources for the website URL, otherwise it will not work</p>
        <input type="text" style="width: 100%;" id="autoai_url_input" placeholder="URL" />
        <input type="button" id="runFromUrlBtn" value="Run Auto Post From URL">
        <p id="loadingTest" style="display:none">Processing... <span><img style="width: 30px" src="<?php echo plugins_url('../assets/loading-gif.gif', __FILE__); ?>"></span></p>

    </div>

    <ol class="test-results">
    </ol>
</div>


<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#runFromUrlBtn').on('click', () => {
            articleUrl = $('#autoai_url_input').val();

            $('#loadingTest').css('display', 'block');
            $('#autoai_url_input').prop('disabled', true);
            $('#runFromUrlBtn').prop('disabled', true);

            $.ajax({
                url: ajaxurl, 
                type: 'POST',
                data: {
                    action: 'run_post_from_url', 
                    url: articleUrl
                },
                success: function(postedArticleUrl) {
                    console.log(postedArticleUrl);
                    if(postedArticleUrl == 'errorNoConfiguration') {
                        $('.test-results').append('<li><p><b>FAILED</b> - no configuration for website url saved</p></li>');
                    }

                    else if(postedArticleUrl == null || postedArticleUrl == '') {
                        $('.test-results').append('<li><p><b>FAILED</b> - check logs for info</p></li>');
                    }
                    else {
                        $('.test-results').append(`<li>Article posted with url <a target="_blank" href="${postedArticleUrl}">${postedArticleUrl}</a></li>`);
                    }
                    
                    $('#loadingTest').css('display', 'none');
                    $('#autoai_url_input').prop('disabled', false);
                    $('#runFromUrlBtn').prop('disabled', false);

                },
                error: function(error) {
                    console.error(error);
                }
            });
        });
    });
</script>