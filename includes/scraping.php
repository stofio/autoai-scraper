<?php

function test_website_scrape($websiteArray) {
    $scraper = new ScrapaWebsite();

    my_second_log('INFO', 'START TEST WEBSITE: ' . $website['baseUrl']);


    $websiteConfig = [
        'baseUrl' => $websiteArray['baseUrl'],
        'selectors' => [
            'catPageLastArticle' => stripslashes($websiteArray['catPageLastArticle']),
            'title' => stripslashes($websiteArray['title']),
            'content' => stripslashes($websiteArray['content']),
            'imageUrl' => stripslashes($websiteArray['imageUrl']),
            'newsLabelSel' => stripslashes($websiteArray['newsLabelSel']),
            'newsLabelText' => stripslashes($websiteArray['newsLabelText'])
        ],
        'defaultImageCredit' => stripslashes($websiteArray['newsLabelText'])
    ];

    $scrapedData = $scraper->scrapeWebsite($websiteConfig);
    return $scrapedData;
}


//return
function run_auto_post_all($websitesArray) {
    $scraper = new ScrapaWebsite();
    
    $scrapedDataResult = [];
    
    foreach($websitesArray as $website) {
        $categoryIDToPost = $website['catPageLastArticle'];
        
        $websiteConfig = [
            'baseUrl' => $website['baseUrl'],
            'selectors' => [
                'catPageLastArticle' => stripslashes($website['catPageLastArticle']),
                'title' => stripslashes($website['title']),
                'content' => stripslashes($website['content']),
                'imageUrl' => stripslashes($website['imageUrl']),
                'newsLabelSel' => stripslashes($website['newsLabelSel']),
                'newsLabelText' => stripslashes($website['newsLabelText'])
            ],
            'defaultImageCredit' => stripslashes($website['newsLabelText'])
        ];

        
        my_second_log('INFO', 'START WITH SOURCE: ' . $website['baseUrl']);
        
        $scraper = new ScrapaWebsite();
        $scrapedData = $scraper->scrapeWebsite($websiteConfig);

        $AIGeneratedData = checkScrapedDataGenerateAiAndPost($scrapedData, $categoryIDToPost);

        $scrapedDataResult[] = $AIGeneratedData;
    }
    
    return $scrapedDataResult;
}

//return
function run_single_auto_post($singleSource) {
    $scraper = new ScrapaWebsite();
 
        $categoryIDToPost = $singleSource['catPageLastArticle'];
        
        $singleSourceConfig = [
            'baseUrl' => $singleSource['baseUrl'],
            'selectors' => [
                'catPageLastArticle' => stripslashes($singleSource['catPageLastArticle']),
                'title' => stripslashes($singleSource['title']),
                'content' => stripslashes($singleSource['content']),
                'imageUrl' => stripslashes(stripslashes($singleSource['imageUrl'])),
                'newsLabelSel' => stripslashes($singleSource['newsLabelSel']),
                'newsLabelText' => stripslashes($singleSource['newsLabelText'])
            ],
            'defaultImageCredit' => stripslashes($singleSource['newsLabelText'])
        ];
        
        
        my_second_log('INFO', 'START WITH SOURCE: ' . $singleSource['baseUrl']);
        
        $scraper = new ScrapaWebsite();
        $scrapedData = $scraper->scrapeWebsite($singleSourceConfig);

        $AIGeneratedData = checkScrapedDataGenerateAiAndPost($scrapedData, $categoryIDToPost);
     
        return $AIGeneratedData;
}


function run_scraper_and_post() {

    //add delition of logs after 1 month, last 10
    //fix delition of scraped urls json
    //
    //add credits
    //button to redo the scraping
    //
    //add to be able to change the prompts (advanced settings)
    //
    //
    //check with gpt if chrome job could slow site while running.. or something like that
    //
    //add plugin images smusher
    //
    //echo settings for cron jobs, based on total sources
}


function checkScrapedDataGenerateAiAndPost($scrapedData, $categoryID) {
    if (is_array($scrapedData)) {
        $title = $scrapedData['title'];
        $url = $scrapedData['original-url'];

        if (!isUrlInPublishedList($url)) {
            my_second_log('INFO', 'Scraped data success');

            $post_id = getAiAndPost($scrapedData, $categoryID); // return post ID
            if ($post_id !== 'error') {
                addToPublishedList($scrapedData['original-url']);

                //add original url to meta
                add_post_meta($post_id, 'original_scr_url', $url, true);

                
                my_second_log('INFO', 'SUCCESS POST posted with ID: ' . $post_id);
                
                
                //get created post url
                $post_url = get_permalink($post_id);
                return array('post_url' => $post_url, 'scraped_url' => $url);
            } else {
                my_second_log('ERROR', 'Failed to process and post article: ' . $url);
            }
        }
        else {
            my_second_log('INFO', 'Skipped, already scraped: ' . $url);
        }
    } else {
        my_second_log('ERROR', 'Invalid scraped data format: ');
    }
}




function run_scraper_from_url($url) {
    $clsScraper = new ScrapaWebsite();

    //get all sources
    //remove everything except baseURL

    //take the base url from the url given
    //search in the baseURLs array if the base url is there
    //if is there, get the data to scrape and scrape
    //return POST URL

    //else if its not in anyone
    //return 'errorNoConfigurationForUrl'

    //else 
    //return null;

    $digitaltrendsMobileConfig = [
        'baseUrl' => 'https://www.digitaltrends.com/mobile-news/',
        'selectors' => [
            'catPageLastArticle' => '.b-mem__post--xl',
            'title' => 'header#dt-post-title h1',
            'content' => '#dt-post-content',
            'imageUrl' => 'meta[property="og:image"]',
            'newsLabelSel' => '.b-headline__top li a span',
            'newsLabelText' => 'News'
        ],
        'defaultImageCredit' => 'Digital Trends'
    ];

    $article = $clsScraper->scrapeWebsite($digitaltrendsMobileConfig, $url);
    $scrapedAndPosted = checkScrapedDataGenerateAiAndPost($article, 34);
    my_log('LOOG');
    my_log($scrapedAndPosted['post_url']);
    my_log('LOOGggggg');
    my_log($scrapedAndPosted);
    return $scrapedAndPosted['post_url'];
}



//get AI article and post
function getAiAndPost($scrapedArticle, $categoryID) {

    //
    //get AI ARTICLE
    //
    $aiGeneratedContent = generateContentWithAI($scrapedArticle);


    if (strlen($aiGeneratedContent['content']) < 500) {
        my_second_log('ERROR', 'Content generated by AI too low, post not created for title: ' . $scrapedArticle['title']);
        return;
    }

    // Check if content generation was successful
    if ($aiGeneratedContent === 'error') {
        my_second_log('ERROR', 'Failed to generate content with AI for article: ' . $scrapedArticle['title']);
        return 'error';
    }

    my_second_log('INFO', 'AI content created success');

    //
    // Create a NEW POST with the AI-generated content
    //
    return createNewPost($aiGeneratedContent, $categoryID); // Returns 'success' or 'error'
}