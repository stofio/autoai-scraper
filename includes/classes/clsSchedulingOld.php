<?php

class Scheduling {

    public function __construct() {
        add_action('save_post_sources_cpt', array($this, 'update_schedule_on_source_save'));
        add_action('delete_post_sources_cpt', array($this, 'update_schedule_on_source_delete'));
    }

    public function scrape_content_event_callback($source_id) {
        //log start scraping per schedule for source
        my_log('scheduleeed source id: ' . $source_id);
        //$this->startScrapingAndRewritingSource($source_id);

        //log end scraping per schedule for source
    }

    // Callback function for the scheduled event
    public function scrape_content_event_callback2($source_id) {
        // Retrieve post data based on $source_id
        $post = get_post($source_id);

        // Check if the post exists and is of the expected post type
        if ($post && $post->post_type === 'sources_cpt') {
            // Additional processing logic here
            // For example, you can access post metadata using get_post_meta() function:
            $base_url = get_post_meta($source_id, 'baseUrl', true);
            $last_article_selector = get_post_meta($source_id, 'catPageLastArticle', true);

            // Now you have access to the post ID ($source_id) and its metadata
            // Perform your scraping logic here using the retrieved data
        }
    }

    public function update_schedule_on_source_save($source_id) {
        $this->remove_all_scheduled_events_for_source();
        
        $base_url = isset($_REQUEST['baseUrl']) ? $_REQUEST['baseUrl'] : '';
        $last_article_selector = isset($_REQUEST['catPageLastArticle']) ? $_REQUEST['catPageLastArticle'] : '';
        $run_daily = isset($_REQUEST['runDaily']) ? $_REQUEST['runDaily'] : '';
        $fetch_times_a_day = isset($_REQUEST['selectFetchTimesADay']) ? $_REQUEST['selectFetchTimesADay'] : '';
    
        // Calculate the interval based on fetch times
        if ($fetch_times_a_day > 0) {
            $this->set_schedule($source_id, $fetch_times_a_day);
        }
    }    

    public function set_schedule($source_id, $fetch_times_a_day) {
        $intervals_in_day = 24 * 60; // Total minutes in a day
        $interval_minutes = floor($intervals_in_day / $fetch_times_a_day); // Calculate interval in minutes
    
        // Schedule the event
        $args = array('source_id' => $source_id);
        for ($i = 0; $i < $fetch_times_a_day; $i++) {
            $time = strtotime('midnight +' . ($interval_minutes * $i) . ' minutes');
            wp_schedule_event( $time, 'daily', 'scrape_content_event');
            //wp_schedule_event('midnight', 'daily', 'scrape_content_event', $args);
        }
    }  

    public function startScrapingAndRewritingSource($source_id) {
        //include class for rewriting
        //initiate main class
        //add function to ScrapeWebsites class, for scraping the category page and check for new articles
        //if new, foreach new, start rewriting process
    }


    public function update_schedule_on_source_delete($post_id) {
        if (get_post_type($post_id) !== 'sources_cpt') {
            return;
        }

        // Remove scheduled events related to the deleted source
        // wp_clear_scheduled_hook('scrape_content_event');
    }

    public function get_scheduled_events_next_24_hours() {
        $events = array();
    
        // Get all scheduled events
        $all_events = _get_cron_array();
    
        // Calculate the timestamp for 24 hours from now
        $twenty_four_hours_from_now = time() + (24 * 60 * 60);
    
        // Loop through each scheduled event
        foreach ($all_events as $timestamp => $cron) {
            // Check if the event is scheduled within the next 24 hours
            if ($timestamp < $twenty_four_hours_from_now) {
                // Loop through each hook in the event
                foreach ($cron as $hook => $details) {
                    // Check if the hook matches 'scrape_content_event'
                    if ($hook === 'scrape_content_event') {
                        // Get the schedule for the hook
                        $schedule = wp_get_schedule($hook);
    
                        // Check if 'args' key exists in $details
                        $args = isset($details['args']) ? $details['args'] : array();
    
                        // Add the event details to the array
                        $events[] = array(
                            'hook' => $hook,
                            'args' => $args,
                            'timestamp' => $timestamp,
                            'schedule' => $schedule
                        );
                    }
                }
            }
        }
    
        return $events;
    }
    
    

    public function get_all_scheduled_events() {
        global $wpdb;
        $scheduled_events = array();

        // Get all scheduled events
        $cron_jobs = _get_cron_array();

        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                if (strpos($hook, 'scrape_content_event') === 0) {
                    // Extract source ID from event name
                    $source_id = (int)str_replace('scrape_content_event', '', $hook);

                    // Get source post title
                    $source_title = get_the_title($source_id);

                    // Get next run time
                    $next_run_time = date('Y-m-d H:i:s', $timestamp);

                    // Add to scheduled events array
                    $scheduled_events[] = array(
                        'source_id' => $source_id,
                        'source_title' => $source_title,
                        'next_run_time' => $next_run_time
                    );
                }
            }
        }

        return $scheduled_events;
    }

    public function get_all_scheduled_events_for_source($source_id) {
        $scheduled_events = array();

        // Get all scheduled events
        $cron_jobs = _get_cron_array();

        // Loop through scheduled events
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                // Check if the event is associated with the given source
                if (strpos($hook, 'scrape_content_event') === 0) {
                    // Get source post title
                    $source_title = get_the_title($source_id);

                    // Get next run time
                    $next_run_time = date('Y-m-d H:i:s', $timestamp);

                    // Add to scheduled events array
                    $scheduled_events[] = array(
                        'source_id' => $source_id,
                        'source_title' => $source_title,
                        'next_run_time' => $next_run_time
                    );
                }
            }
        }

        return $scheduled_events;
    }

    public function get_scheduled_events_names() {
        $prefix = 'scrape_content_event';
        $scheduled_event_names = array();

        // Get all scheduled events
        $cron_jobs = _get_cron_array();

        // Loop through scheduled events
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                // Check if the event name starts with the given prefix
                if (strpos($hook, $prefix) === 0) {
                    $scheduled_event_names[] = $hook;
                }
            }
        }

        return $scheduled_event_names;
    }

    public function remove_all_scheduled_events_for_source() {
        // Get all scheduled events
        $cron_jobs = _get_cron_array();
    
        // Loop through scheduled events
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                // Check if the event is associated with the given source
                if (strpos($hook, 'scrape_content_event') === 0) {
                    // Unschedule the event
                    wp_unschedule_hook($hook);
                }
            }
        }
    }

    public function remove_all_scheduled_events_for_all_sources() {
        // Get all scheduled events
        $cron_jobs = _get_cron_array();

        // Loop through scheduled events
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                // Check if the event is associated with a source
                if (strpos($hook, 'scrape_content_event_') === 0) {
                    // Extract source ID from event name
                    $source_id = (int) str_replace('scrape_content_event_', '', $hook);

                    // Unschedule the event
                    wp_unschedule_hook($hook);
                }
            }
        }
    }


}

