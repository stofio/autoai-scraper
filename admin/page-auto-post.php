<?php
require_once plugin_dir_path(__FILE__) . '/includes/handle_actions.php';
?>

<div class="page-container">
    <h1>AI Auto Post</h1>
    <div class="white-container result-cont">

        <?php
        echo '<h2>⇒ Next auto run</h2>';
        $next_cron_job = get_next_custom_cron_job();
        if ($next_cron_job) {
            $time_until_next_run = $next_cron_job['timestamp'] - time();
            $formatted_time_until_next_run = human_time_diff(time(), $next_cron_job['timestamp']);

            echo "<b>Next auto process run: </b> in $formatted_time_until_next_run\n <br>";

            $sources = get_option('ai_scraper_websites', '');
            $idInArray = $next_cron_job['args'][0];
            echo "<b>For source: </b>" . $sources[intval($idInArray)]['baseUrl'];
        } else {
            echo "No upcoming auto process runs found. Try adding a source or activate one\n";
        }


        
        echo '<h2>⇐ Previous auto run</h2>';
        $prev_run = get_option('autoai_last_cron_run', '');
        
        if ($prev_run) {
            $last_run_time = $prev_run['last_run_time'];
            $formatted_time_since_previous_run = human_time_diff(time(), $last_run_time);
            $last_source_url = $prev_run['last_source_url'];
            echo "<b>Previous auto process run:</b> $formatted_time_since_previous_run ago\n <br>";
            echo "<b>For source: </b>" . $last_source_url;
        } else {
            echo "No previous auto process runs found.\n";
        }

        ?>

        <br><br>
        <input type="button" id="runAutoPostBtn" value="Run Manual Auto Post For All Sources">
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


<?php

function get_all_scheduled_auto_post() {
    $cron_jobs = _get_cron_array();
    $custom_cron_jobs = [];

    foreach ($cron_jobs as $timestamp => $events) {
        if (isset($events['autoai_cron_hook'])) {
            foreach ($events['autoai_cron_hook'] as $event_key => $event_data) {
                // Convert the Unix timestamp to a human-readable date and time
                $human_readable_time = date('Y-m-d H:i:s', $timestamp);

                // Add the information to the result array
                $custom_cron_jobs[$timestamp][$event_key] = [
                    'schedule' => $event_data['schedule'],
                    'args' => $event_data['args'],
                    'interval' => $event_data['interval'],
                    'human_readable_time' => $human_readable_time,
                ];
            }
        }
    }

    return $custom_cron_jobs;
}


function get_next_custom_cron_job() {
    $next_cron_job = null;

    // Get all scheduled events
    $cron_jobs = _get_cron_array();

    // Loop through each timestamp and check for 'custom_cron_hook'
    foreach ($cron_jobs as $timestamp => $events) {
        if (isset($events['autoai_cron_hook'])) {
            foreach ($events['autoai_cron_hook'] as $event_key => $event_data) {
                // Check if the cron job is in the future
                if ($timestamp > time()) {
                    if (!$next_cron_job || $timestamp < $next_cron_job['timestamp']) {
                        // Set the current cron job as the next one
                        $next_cron_job = [
                            'schedule' => $event_data['schedule'],
                            'args' => $event_data['args'],
                            'interval' => $event_data['interval'],
                            'timestamp' => $timestamp,
                            'human_readable_time' => date('Y-m-d H:i:s', $timestamp),
                        ];
                    }
                }
            }
        }
    }

    return $next_cron_job;
}



echo '<h2>All daily scheduled processes</h2>';
function display_scheduled_post_jobs_info($scheduled_post_jobs) {
    // Get sources
    $sources = get_option('ai_scraper_websites', []);

    foreach ($scheduled_post_jobs as $timestamp => $events) {
        foreach ($events as $event_key => $event_data) {
            $idInArray = $event_data['args'][0];
            echo '<p>';
            echo 'Source: ' . $sources[$idInArray]['baseUrl'] . '<br>';
            echo 'Scheduled: ' . $event_data['human_readable_time'];
            echo '</p>';
        }
    }
}
$scheduled_post_jobs = get_all_scheduled_auto_post();
// Assuming $scheduled_post_jobs is already populated with the scheduled cron jobs
display_scheduled_post_jobs_info($scheduled_post_jobs);

