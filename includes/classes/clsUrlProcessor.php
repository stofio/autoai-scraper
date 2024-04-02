<?php
//this class is used for managing processed urls

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class UrlProcessor {

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'autoai_processed';
    }

    public function insert_processed_url($url, $status) {
        global $wpdb;

        $url = $this->normalize_url($url);

        $wpdb->insert(
            $this->table_name,
            array(
                'url' => $url,
                'status' => $status,
                'processed_on' => current_time('mysql', 1),
            ),
            array('%s', '%s', '%s')
        );
    }

    public function get_processed_url($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoai_processed';
        
        // Normalize the URL
        $url = $this->normalize_url($url);
    
        $query = $wpdb->prepare("SELECT * FROM $table_name WHERE url = %s", $url);
        return $wpdb->get_row($query);
    }

    public function update_processed_url($url, $new_status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoai_processed';
        
        // Normalize the URL
        $url = $this->normalize_url($url);
    
        $wpdb->update(
            $table_name,
            array('status' => $new_status), // Data
            array('url' => $url), // Where
            array('%s'), // Data format
            array('%s') // Where format
        );
    }

    public function delete_processed_url($url) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'autoai_processed';
        
        // Normalize the URL
        $url = $this->normalize_url($url);
    
        $wpdb->delete(
            $table_name,
            array('url' => $url),
            array('%s')
        );
    }


    //get source closest to the given url or null
    public function getSourceIdByUrl($url) {
        // Ensure the URL has a proper structure for comparison
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'];
        $path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        

        // Get all posts that have the '_content_fetcher_scraping_url' meta key
        $args = array(
            'post_type' => 'sources_cpt',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_content_fetcher_scraping_url',
                    'compare' => 'EXISTS',
                ),
            ),
        );
        
        $posts = get_posts($args);
        $bestMatch = null;
        $bestMatchScore = 0;


        foreach ($posts as $post) {
            $scrapingUrl = get_post_meta($post->ID, '_content_fetcher_scraping_url', true);

            // Preprocess URLs to ensure they're properly formatted
            $formattedUrl = $this->formatUrl($scrapingUrl);
        
            $scrapingParsedUrl = parse_url($formattedUrl);

            $baseUrl = rtrim($scrapingParsedUrl["scheme"] . '://' . $scrapingParsedUrl["host"], '/');
            $inputBaseUrl = rtrim($parsedUrl["scheme"] . '://' . $parsedUrl["host"], '/');
        
            // Compare base URLs
            if ($baseUrl !== $inputBaseUrl) {
                continue; // Skip if base URLs don't match
            }
            
            if (isset($scrapingParsedUrl["path"])) {
                $scrapingPathSegments = explode('/', trim($scrapingParsedUrl["path"], '/'));
            } else {
                continue; // Skip if no path is available
            }
        
            // Assuming $path is already set to the path you're comparing against
            $pathSegments = explode('/', trim($path, '/'));
        
            // Compare path segments to find the length of the longest common prefix
            $i = 0;
            while (isset($pathSegments[$i], $scrapingPathSegments[$i]) && $pathSegments[$i] === $scrapingPathSegments[$i]) {
                $i++;
            }
        
            // Use the count of matching segments as the match score
            $pathMatchScore = $i;
        
            // Update best match if this score is higher
            if ($pathMatchScore > $bestMatchScore) {
                $bestMatchScore = $pathMatchScore;
                $bestMatch = $post;
            }
        }

        if($bestMatch == null) {
            //check for the base domain if there is a match
            foreach ($posts as $post) {
                $scrapingUrl = get_post_meta($post->ID, '_content_fetcher_scraping_url', true);
                $formattedUrl = $this->formatUrl($scrapingUrl);
                $scrapingParsedUrl = parse_url($formattedUrl);
                $baseUrl = rtrim($scrapingParsedUrl["scheme"] . '://' . $scrapingParsedUrl["host"], '/');

                $inputBaseUrl = rtrim($parsedUrl["scheme"] . '://' . $parsedUrl["host"], '/');

                if($baseUrl == $inputBaseUrl) {
                    $bestMatch = $post;
                    break;
                }
            }
        }

        return $bestMatch; // This will be null if no match found, or the post object of the best match
    }

    public function getSourceConfig($sourceID) {
        // Retrieve all meta data for the given post ID
        $meta_data = get_post_meta($sourceID);
        
        // Return the meta data
        return $meta_data;
    }    

    private function normalize_url($url) {
        $url = strtolower($url);
        $url = rtrim($url, '/');
        return $url;
    }

    private function formatUrl($url) {
        $formattedUrl = filter_var($url, FILTER_SANITIZE_URL);
        if (!preg_match('~^https?://~', $formattedUrl)) {
            $formattedUrl = "http://" . $formattedUrl;
        }
        return $formattedUrl;
    }
    
}
