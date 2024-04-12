<?php
//this class is used to initialize the 3 main components, scraping, rewriting and posting of a single article

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class ScrapeAiMain {

    // Constructor
    public function __construct() {
        require_once plugin_dir_path(__DIR__) . '../vendor/autoload.php';
        require_once plugin_dir_path(__DIR__) . '../includes/utilities.php';
        require_once plugin_dir_path(__DIR__) . '../includes/classes/clsScrapeWebsite.php';
        require_once plugin_dir_path(__DIR__) . '../includes/classes/clsManageRewriting.php';
        require_once plugin_dir_path(__DIR__) . '../includes/classes/clsPosting.php';
        require_once plugin_dir_path(__DIR__) . '../includes/classes/clsUrlProcessor.php';
    }

    public function runScrapeRewriteAndPost($sourceSettings, $jobSettings, $url) {
        
        $processor = new UrlProcessor();

        //check if url is published/pending
        $processed = $processor->get_processed_url($url);
        if($processed && $processed->status == 'posted') {
            my_second_log('ERROR', 'URL skipped, already posted with ID = ' . $processed->new_post_id);
            return;
        }
        
        $scrapedArticle = $this->getScrapedData($sourceSettings, $url);

        if($scrapedArticle == 'error') {
            return;
        }

        $rewrittenArticle = $this->getAiRewritten($scrapedArticle, $sourceSettings);

        if($rewrittenArticle == 'error') {
            return;
        }

        $postID = $this->savePost($rewrittenArticle, $sourceSettings, $jobSettings); // return post_id or false

        if($postID) {
            //add to published
            $processor->insert_processed_url($url, 'posted', $jobSettings['job'], $postID, $sourceSettings['source_id']);
        }
        else {
            //add to published
            $processor->insert_processed_url($url, 'failed', $jobSettings['job'], '', $sourceSettings['source_id']);
        }
        
        $post_url = get_permalink($postID);
        return $post_url;
    }

    public function getScrapedData($sourceSettings, $url) {
        // Prepare args for scraping

        $websiteConfig = [
            'baseUrl' => $sourceSettings['_content_fetcher_scraping_url'][0],
            'selectors' => [
                'catPageLastArticle' => stripslashes($sourceSettings['_content_fetcher_category_last_article'][0]),
                'title' => stripslashes($sourceSettings['_content_fetcher_title_selector'][0]),
                'content' => stripslashes($sourceSettings['_content_fetcher_content_selector'][0]),
                'imageUrl' => stripslashes(stripslashes($sourceSettings['_content_fetcher_image_url_selector'][0])),
                'typeArticle' => stripslashes($sourceSettings['_content_fetcher_article_type_selector'][0]),
                'typeArticleText' => stripslashes($sourceSettings['_content_fetcher_article_type_text'][0])
            ],
            'getImages' => stripslashes($sourceSettings['_content_fetcher_get_images'][0]),
            'getTables' => stripslashes($sourceSettings['_content_fetcher_get_tables'][0]),
            'excludeFirstImage' => stripslashes($sourceSettings['_content_fetcher_exclude_first_image'][0]),
            'defaultImageCredit' => stripslashes($sourceSettings['_content_fetcher_images_credit'][0])
        ];

        $scraper = new ScrapaWebsite();
        return $scraper->scrapeWebsite($websiteConfig, $url);
    }


    private function getAiRewritten($scrapedArticle, $source) {
        $rewritingManager = new clsManageRewriting($source['_content_fetcher_ai_model'][0]);
        $aiGeneratedContent = $rewritingManager->generateContentWithAI($scrapedArticle, $source);

        if (strlen($aiGeneratedContent['content']) < 500) {
            my_second_log('ERROR', 'Content generated by AI too low, post not created for title: ' . $scrapedArticle['title']);
            return 'error';
        }
    
        //fix AI excerpt fail, take first words of content
        if( $aiGeneratedContent['excerpt'] == null ) {
            $aiGeneratedContent['excerpt'] = getSubstringBeforeFirstDot($aiGeneratedContent['content']);
        }
    
        // Check if content generation was successful
        if ($aiGeneratedContent === 'error') {
            my_second_log('ERROR', 'Failed to generate content with AI for article: ' . $scrapedArticle['title']);
            return 'error';
        }
    
        my_second_log('INFO', 'AI content created success');

        return $aiGeneratedContent;
    }


    private function savePost($aiGeneratedContent, $sourceSettings, $jobSettings) {
        require_once plugin_dir_path(__DIR__) . '../includes/classes/clsPosting.php';
        $posting = new clsPosting();
        $post_id = $posting->createNewPost($aiGeneratedContent, $sourceSettings, $jobSettings); // Returns post_id or false
        return $post_id;
    }
}
