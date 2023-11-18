<?php

function run_scraper_and_post() {

    $scraper = new ScrapaWebsite();


    $digitalTrendsPCConfig = [
        'baseUrl' => 'https://www.digitaltrends.com/computing-news/',
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

   // $digiralTrendsPC = $scraper->scrapeWebsite($digitalTrendsPCConfig);



    $tomshardwarePCConfig = [
        'baseUrl' => 'https://www.tomshardware.com/desktops',
        'selectors' => [
            'catPageLastArticle' => '.listingResults .listingResult',
            'title' => '.news-article h1',
            'content' => '#article-body',
            'imageUrl' => 'meta[property="og:image"]',
            'newsLabelSel' => '.byline-social .byline',
            'newsLabelText' => 'News'
        ],
        'defaultImageCredit' => 'Tom’s Hardware'
    ];

   // $tomshardwarePC = $scraper->scrapeWebsite($tomshardwarePCConfig);


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

    $digitaltrendsMOBILE = $scraper->scrapeWebsite($digitaltrendsMobileConfig);


    checkScrapedDataGenerateAiAndPost($digitaltrendsMOBILE);

    //
    //
    //save images and add credits
    //button to redo the scraping
    //
    //
    //
    //add the dashboard
    //add possibility to choose between gpt-3-turbo (0.0015$ x 1k token) and gpt-3-turbo-16k (0.003-0.004$ x 1k token)
    //add to be able to change the promts (advanced)
    //
    //add chrome job
    //
    //check with gpt if chrome job could slow site while running.. or something like that
    //
    //
}


function checkScrapedDataGenerateAiAndPost($scrapedData) {
    if (is_array($scrapedData)) {
        $title = $scrapedData['title'];
        $url = $scrapedData['original-url'];

        if (!isUrlInPublishedList($url)) {
            $post_id = getAiAndPost($scrapedData); // return post ID
            if ($post_id !== 'error') {
                addToPublishedList($scrapedData['original-url']);

                //add original url to meta
                add_post_meta($post_id, 'original_scr_url', $url, true);

                my_second_log('INFO', 'Scraped and posted ID: ' . $post_id . ' ' . $url);
            } else {
                my_second_log('ERROR', 'Failed to process and post article: ' . $url);
            }
        }
        else {
            my_second_log('INFO', 'Skipped, already scraped: ' . $url);
        }
    } else {
        my_second_log('ERROR', 'Invalid scraped data format');
    }
}




function run_scraper_from_url($url, $check_exist) {
    $clsScraper = new ScrapaWebsite();

     $digitalConfig = [
         'baseUrl' => 'https://www.digitaltrends.com/computing-news/',
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

     $tomshardwarePCConfig = [
        'baseUrl' => 'https://www.tomshardware.com/desktops',
        'selectors' => [
            'catPageLastArticle' => '.listingResults .listingResult',
            'title' => '.news-article h1',
            'content' => '#article-body',
            'imageUrl' => 'meta[property="og:image"]',
            'newsLabelSel' => '.byline-social .byline',
            'newsLabelText' => 'News'
        ],
        'defaultImageCredit' => 'Tom’s Hardware'
    ];

    $article = $clsScraper->scrapeWebsite($tomshardwarePCConfig, $url);

    checkScrapedDataGenerateAiAndPost($article);
}



//get AI article and post
function getAiAndPost($scrapedArticle) {

    //
    //get AI ARTICLE
    //
    $aiGeneratedContent = generateContentWithAI($scrapedArticle);


    if (strlen($aiGeneratedContent['content']) < 500) {
        my_second_log('ERROR', 'Content : ' . $url);
        return;
    }

    // Check if content generation was successful
    if ($aiGeneratedContent === 'error') {
        my_second_log('ERROR', 'Failed to generate content with AI for article: ' . $scrapedArticle['title']);
        return 'error';
    }

    //
    // Create a NEW POST with the AI-generated content
    //
    return createNewPost($aiGeneratedContent); // Returns 'success' or 'error'
}