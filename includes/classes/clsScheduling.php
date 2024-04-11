<?php

class Scheduling {

    public function __construct() {
        add_action('save_post_sources_cpt', array($this, 'activate_cron_job'));
    }

    //callback function of each cron run
    public function scheduled_event_callback() {
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '../includes/utilities.php';
        my_second_log('INFO', 'SCHEDULED START');

        $sourcesIds = $this->get_scheduled_sources(); //return array of ids

        foreach ($sourcesIds as $source_id) {
            $urlsToPost = $this->getAllNewArticlesForSource($source_id); //return array of urls
            $urlsToPost == false ? null : $this->start_scrape_and_rewrite($urlsToPost, $source_id);
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
        //get post data for scraping cat page
        $source = $this->getSourceDetailsForCatPageById($source_id);

        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsScrapeWebsite.php';
        $scraper = new ScrapaWebsite();
        $catPageUrlsList = $scraper->scrapeCategoryPage($source['base_url'], $source['list_container'], $source['last_article']); //list of urls

        if($this->getSourceCheckTpye($source_id) === 'last') { //if 'check only last article' selected
            $catPageUrlsList = $catPageUrlsList[0];
        }

        //get all posted articles ids for source in array
        $postedArticlesIds = $this->getAllPostedArticleIdsForSource($source_id);

        //compare arrays and get the not posted once
        $notPosted = $this->getNotPostedUrls($catPageUrlsList, $postedArticlesIds);

        return $notPosted;
    }

    private function getSourceDetailsForCatPageById($source_id) {
    
        $base_url = get_post_meta($source_id, '_content_fetcher_scraping_url', true); 
        $last_article = get_post_meta($source_id, '_content_fetcher_category_last_article', true);
        $list_container = get_post_meta($source_id, '_content_fetcher_category_list_container', true);
    
        return array(
            'base_url' => $base_url,
            'last_article' => $last_article, 
            'list_container' => $list_container
        );
    
    }

    private function start_scrape_and_rewrite($urls, $source_id) {
        // Get source settings
        $source_settings = get_post_meta($source_id);

        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '../includes/classes/clsMain.php';
        foreach($urls as $url) {            
            $main = new ScrapeAiMain();
            $main->runScrapeRewriteAndPost($source_settings, $jobSettings, $url);
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

    private function getAllPostedArticleIdsForSource($source_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoai_processed';
    
        $results = $wpdb->get_results("
          SELECT url 
          FROM $table_name 
          WHERE source_id = $source_id
          AND status = 'posted'
        ");
      
        $urls = array();
      
        foreach ($results as $row) {
          $urls[] = $row->url; 
        }
      
        return $urls;
      
    }

    private function getNotPostedUrls($catPageUrlsList, $postedArticlesIds) {
        $notPosted = array();
      
        foreach ($catPageUrlsList as $url) {
          if (!in_array($url, $postedArticlesIds)) {
            $notPosted[] = $url;
          }
        }
      
        return $notPosted;
      
    }

    private function getSourceCheckTpye($source_id) {
        global $wpdb;
        $check_type = get_post_meta($source_id, '_content_fetcher_check_type', true);
    }
      


}