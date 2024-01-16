<?php

function schedule_cron_jobs_for_sources() {
    // first unschedule all existing cron jobs associated with 'custom_cron_hook'
    for ($index = 0; $index <= 100; $index++) {
        // Clear all scheduled events for the specified hook and index
        wp_clear_scheduled_hook('autoai_cron_hook', array($index));
    }


    $timeADay = get_option('times_a_day_run_cron');
    if($timeADay === null || $timeADay === '' || !$timeADay) {
        $timeADay = 1;
    }

    // Calculate the interval between each cron job
    $interval = intval(86400 / $timeADay); // 86400 seconds in a day, dividing by n for n times a day
    $secInBetween = 0;

    // Get sources
    $sources = get_option('ai_scraper_websites', []);

    // Loop through each source and schedule a cron job
    for ($i = 1; $i <= $timeADay; $i++) {
        foreach ($sources as $index => $source) {
            if($source['runDaily'] === 'on') {
                // Calculate the scheduled time for each of the runs
                $scheduled_time = strtotime("today") + ($i * $interval) + ($index * $interval) + $secInBetween;

                wp_schedule_event($scheduled_time, 'daily', 'autoai_cron_hook', array($index));
                $secInBetween = $secInBetween + 120;    
            }  
        }
    }
}




