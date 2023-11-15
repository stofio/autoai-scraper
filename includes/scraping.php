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


    //then save the images and change urls
    //my_log($tomshardwarePC);
    checkScrapedDataGenerateAiAndPost($digitaltrendsMOBILE);


    // my_log($digiralTrendsPC);
    // my_log($tomshardwarePC);
    // my_log($digitaltrendsMOBILE);

}


function checkScrapedDataGenerateAiAndPost($scrapedSite) {
    if(is_array($scrapedSite)) {
        if(!isTitleInPublishedList($scrapedSite['title'])) {
            getAiAndPost($scrapedSite);
            addToPublishedList($scrapedSite['title']);
            my_log('POSTED: ' . $scrapedSite['title']);
        }
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

    return;


    if($article == null) {
        echo "The URL doesn't belong to any of the provided sites.";
        return;
    }
    else if($article == 'error') {
        echo "Something went wrong with scraping, check logs.";
        return;
    }


    //check if article exist
    if($check_exist == 'true') {
        $articleExist = checkIfArticleExist($article['title']);
        if($articleExist == 'error') {
            my_log('Error in checkIfArticleExist');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }
        else if(!$articleExist) {
            my_log('Error in articleExist returned null');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }
    }
    else {
        $articleExist = '0';
    }

    if($articleExist == '1') {
        //EXIST
        my_log('Article exist');
        echo "The article already exist between the last 15 articles.";
        return;
    }
    else if($articleExist == '0') {

        //get title and excerpt
        $titleAndExcerptArray = getAiTitleAndExcerpt($article);
        if($titleAndExcerptArray == 'error') {
            my_log('Error in getAiTitleAndExcerpt');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }
        else if(!$titleAndExcerptArray) {
            my_log('Error in getAiTitleAndExcerpt returned null');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }

        //get content
        $theContent = getAiContent($article);
        if($theContent == 'error') {
            my_log('Error in getAiContent');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }
        else if(!$theContent) {
            my_log('Error in getAiContent returned null');
            echo 'Error GPT-3.5 response, check log file, or try again.';
            return;
        }

        $newArticle = $titleAndExcerptArray;
        $newArticle['content'] = $theContent;
        $newArticle['img-url'] = $article['img-url'];
        $newArticle['img-credit'] = $article['img-credit'];


        //create post
        create_new_post($newArticle);
    }
    else {
        my_log('Check if article exist went wrong');
        echo "Something went wrong #1, try again";
    }

}