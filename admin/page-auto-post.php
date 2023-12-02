<?php
require_once plugin_dir_path(__FILE__) . '/includes/handle_actions.php';
?>

<div class="page-container">
    <h1>AI Auto Post</h1>
    <div class="white-container result-cont">

        <input type="button" id="runAutoPostBtn" value="Run Manual Auto Post">
        <p id="loadingTest" style="display:none">Processing... <span><img style="width: 30px" src="<?php echo plugins_url('../loading-gif.gif', __FILE__); ?>"></span></p>

    </div>

</div>



<script type="text/javascript">
    jQuery(document).ready(function($) {
        
        $('#runAutoPostBtn').on('click', async () => {
        $('#loadingTest').css('display', 'block');
        $('#runAutoPostBtn').css('display', 'none');

        try {
            // Get all sources
            const savedSources = await getSavedSources();
            
            for (const singleSource of savedSources) {
                const publishedData = await runSingleAutoPost(singleSource);
                $('.result-cont').append(`<h3>Source: ${singleSource['baseUrl']}</h3>`);
                console.log(publishedData);
                displayTodayLog();
                
                if (publishedData !== null) {
                    $('.result-cont').append(`
                        <p><b>Original:</b><a target="_blank" href="${publishedData.scraped_url}">
                            ${publishedData.scraped_url} <br></p>
                        </a>
                        <b>Posted:</b> <a target="_blank" href="${publishedData.post_url}">
                            ${publishedData.post_url}
                        </a>
                    `);
                } else {
                    // Show response error maybe
                    $('.result-cont').append('<p><b>Skipped</b> - check logs for more info</p>');
                }
            }

            $('#loadingTest').css('display', 'none');
        } catch (error) {
            console.error(error);
            $('.result-cont').empty();
            $('.result-cont').append(error);
        }
    });

    // Function to get saved sources
    function getSavedSources() {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_saved_sources'
                },
                success: resolve,
                error: reject
            });
        });
    }

    // Function to run a single auto post
    function runSingleAutoPost(singleSource) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                async: true,
                data: {
                    action: 'run_single_auto_post',
                    single_source: singleSource
                },
                success: resolve,
                error: reject
            });
        });
    }

        $('#refreshLog').on('click', () => {
            displayTodayLog();
        });

        $(document).ready(function() {
            displayTodayLog();
        });

        function displayTodayLog() {
            $('#refreshLog').css('disabled', true);
            //get
            $.ajax({
                url: ajaxurl, 
                type: 'POST',
                data: {
                    action: 'display_today_log'
                },
                success: function(logToDisplay) {
                    logToDisplay = logToDisplay.endsWith('0') ? logToDisplay.slice(0, -1) : logToDisplay;
                    if(logToDisplay) {
                        $('.last-run').empty();
                        $('.last-run').append(logToDisplay);

                        // Scroll to the bottom of the div
                        $('.last-run .log-container').scrollTop($('.last-run .log-container').prop("scrollHeight"));
                    }
                    else {
                        $('.last-run').append('An error occured while getting and displaying log <br>');
                    }                    
                    $('#refreshLog').css('disabled', false);
                },
                error: function(error) {
                    console.error(error);
                   // $('.last-run').append(error);
                }
            });
        }
    });
</script>

<h2>Logs</h2>
<input type="button" id="refreshLog" value="Refresh logs" />
<div class="last-run"></div>