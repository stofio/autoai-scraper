<?php

class Scheduling {

    public function __construct() {
        add_action('save_post_sources_cpt', array($this, 'activate_cron_job'));
    }

    //callback function of each cron run
    public function scheduled_event_callback() {
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '../includes/utilities.php';
        my_log('CRON RUUUN');
        return;
        my_second_log('INFO', 'SCHEDULED START');
        $sourcesIds = $this->get_scheduled_sources(); //return array of ids

        foreach ($sourcesIds as $id) {
            $urlsToPost = $this->getAllNewArticlesForSource($id); //return array of urls
            $urlsToPost == false ? null : $this->start_scrape_and_rewrite($urlsToPost);
        }
        my_second_log('SUCCESS', 'SCHEDULED FINISH');
    }

    public function activate_cron_job() {
        //
        //
        // TO CHECK
        //
        //
        wp_clear_scheduled_hook('scrape_content_event');
        $times_a_day = get_option('times_a_day_run_cron'); 
        wp_schedule_event(time(), $times_a_day, 'scrape_content_event');
    }


    public function get_scheduled_sources() {
        return get_posts(array(
            'post_type'  => 'sources_cpt',
            'meta_key'   => '_content_fetcher_run_daily',
            'meta_value' => '1',
            'fields'     => 'ids', // Retrieve only IDs
        ));
    }

    private function getAllNewArticlesForSource($source_id) {
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsScrapeWebsite.php';
        $scraper = new ScrapaWebsite();
        $scrapedData = $scraper->scrapeCategoryPage($url, $_POST['container'], $_POST['href']); //list of urls

        //get all posted articles ids for source in array

        //compare arrays and get the not posted once
        
        //return list of urls to rewrite
        
    }

    private function start_scrape_and_rewrite($urls) {
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '../includes/classes/clsMain.php';
        foreach($urls as $url) {
            $main = new ScrapeAiMain();
            $main->runScrapeRewriteAndPost($sourceSettings, $jobSettings, $url);
        }
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




}