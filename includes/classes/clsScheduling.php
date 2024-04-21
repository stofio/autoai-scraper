<?php

class Scheduling {

    public function __construct() {
        add_action('save_post_sources_cpt', array($this, 'activate_cron_job'));
    }

    //callback function of each cron run
    public function scheduled_event_callback($interval) {
        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/utilities.php';
        my_second_log('INFO', 'SCHEDULED START');

        $sourcesIds = $this->get_scheduled_sources_by_interval($interval); //return array of ids

        foreach ($sourcesIds as $source_id) {
            $urlsToPost = $this->getAllNewArticlesForSource($source_id); //return array of urls
            $urlsToPost == false ? null : $this->start_scrape_and_rewrite($urlsToPost, $source_id);
        }
        my_second_log('SUCCESS', 'SCHEDULED FINISH');
    }

    public function activate_cron_job() {
        wp_clear_scheduled_hook('scrape_content_event_daily');
        wp_clear_scheduled_hook('scrape_content_event_hourly');
        wp_clear_scheduled_hook('scrape_content_event_twicedaily');
        wp_clear_scheduled_hook('scrape_content_event_weekly');
        //$times_a_day = get_option('times_a_day_run_cron') ?: 'daily';
        wp_schedule_event(time() + 21600, 'daily', 'scrape_content_event_daily');
        wp_schedule_event(time() + 43200, 'hourly', 'scrape_content_event_hourly');
        wp_schedule_event(time() + 64800, 'twicedaily', 'scrape_content_event_twicedaily');
        wp_schedule_event(time() + 86400, 'weekly', 'scrape_content_event_weekly');
    }

    public function display_scheduled_time() {
        // Get the next scheduled timestamp for your cron job
        $next_run_timestamp = wp_next_scheduled('scrape_content_event');
        if ($next_run_timestamp) {
            // Convert the timestamp to a human-readable date
            $next_run_time = date('Y-m-d H:i:s', $next_run_timestamp);
            // Get the current time
            $current_time = date('Y-m-d H:i:s');
            echo "Current time: $current_time<br>";
            echo "Next scheduled run: $next_run_time";
        } else {
            echo "No events scheduled";
        }
    }

    public function get_scheduled_sources_by_interval($interval) {
        $args = array(
            'post_type' => 'sources_cpt',
            'meta_query' => array(
              'relation' => 'AND',
              array(
                'key' => '_content_fetcher_run_daily',
                'value' => '1',
                'compare' => '='
              ),
              array(
                'key' => '_content_fetcher_fetch_interval', 
                'value' => $interval,
                'compare' => '='
              )
            )
        );

        $query = new WP_Query( $args );

        $post_ids = array();

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
            $query->the_post();
            $post_ids[] = get_the_ID(); 
            }
        }
        
        return $post_ids;
    }

    public function display_scheduled_sources() {
        $args = array(
            'post_type' => 'sources_cpt',
            'meta_query' => array(
                array(
                    'key' => '_content_fetcher_run_daily', 
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query( $args );
        
        if ( $query->have_posts() ) {
            echo '<ol>';
            while ( $query->have_posts() ) {
                $query->the_post();
                    echo '<li>';
                    echo '<a href="' . get_edit_post_link(get_the_ID()) . '">' . get_the_title() . '</a>';
                    echo '</li>';
            }
            echo '</ol>';
        } else {
            echo 'Setup scheduling on a source';
        }
        
        wp_reset_postdata();
    }
    

    public function display_scheduled_events() {

        global $wpdb;
      
        $scheduled_events = array();
        $cron_names = array('scrape_content_event_daily', 'scrape_content_event_hourly', 'scrape_content_event_twicedaily', 'scrape_content_event_weekly');
      
        // Get all scheduled events
        $cron_jobs = _get_cron_array();
      
        foreach ($cron_jobs as $timestamp => $cron) {
            foreach ($cron as $hook => $events) {
                //var_dump($hook);
                //echo '<br>';
        
                // Check if cron name matches
                if (in_array($hook, $cron_names)) {
        
                    // Extract source ID from event name
                    $interval = str_replace('scrape_content_event_', '', $hook);
                    $sourcesIds = $this->get_scheduled_sources_by_interval($interval);

                    if(count($sourcesIds)>0) {
                        echo '<h4>';
                        echo strtoupper($interval);
                        echo ':</h4>';
                        foreach($sourcesIds as $source_id){
                            //get source title
                            $source_title = get_the_title($source_id);
                            $next_run_time = date('Y-m-d H:i:s', $timestamp);
                            echo '<a href="' . get_edit_post_link() . '">';
                            echo $source_title;
                            echo ' - ';
                            echo $next_run_time;
                            echo '</a><br>';
                        }
                    }
                }
            }
        }      
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

        require_once dirname(dirname(plugin_dir_path( __FILE__ ))) . '/includes/classes/clsMain.php';
        foreach($urls as $url) {            
            $main = new ScrapeAiMain();
            $main->runScrapeRewriteAndPost($source_settings, $jobSettings, $url);
        }
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
        return $check_type;
    }


      


}