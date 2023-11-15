<?php

require_once plugin_dir_path(__FILE__) . 'clsWebsites.php';


function run_scraper_and_post() {
    
    //scrape
    $clsScraper = new ScrapaWebsite();

    //PC
    $tomshardwarePC = $clsScraper->tomshardwarePC(); //getting last post
    //$pcworldPC = $clsScraper->pcworldPC();
    $digitaltrendsPC = $clsScraper->digitaltrendsPC();

    $digitaltrendsMOBILE = $clsScraper->digitaltrendsMOBILE();


    if(is_array($tomshardwarePC)) {
        if(!isTitleInPublishedList($tomshardwarePC['title'])) {
            //  getAiAndPost($tomshardwarePC);
            addToPublishedList($tomshardwarePC['title']);
            my_log('tomshardwarePC POSTED: ' . $tomshardwarePC['title']);
        }
    }

    if(is_array($digitaltrendsPC)) {
        if(!isTitleInPublishedList($digitaltrendsPC['title'])) {
            //  getAiAndPost($digitaltrendsPC);
            addToPublishedList($digitaltrendsPC['title']);
            my_log('digitaltrendsPC POSTED' . $digitaltrendsPC['title']);
        }
    }

    if(is_array($digitaltrendsMOBILE)) {
        if(!isTitleInPublishedList($digitaltrendsMOBILE['title'])) {
            //  getAiAndPost($digitaltrendsMOBILE);
            addToPublishedList($digitaltrendsMOBILE['title']);
            my_log('digitaltrendsMOBILE POSTED' . $digitaltrendsMOBILE['title']);
        }
    }

}

function run_scraper_from_url($url, $check_exist) {
    $clsScraper = new ScrapaWebsite();

    $article = $clsScraper->fromUrl($url);


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

function get_articles_from_sites() {
    //scrape latest news

    //check if 
}

function postOnFb() {
    require_once(__DIR__ . '/Facebook/autoload.php');

    $fb = new \Facebook\Facebook([
      'app_id' => '379573567726516',
      'app_secret' => '2c042125ab7a4dca8069aee6e9c932d7',
      'default_graph_version' => 'v10.0',  // adjust the version accordingly
    ]);

    $data = [
      'message' => 'test example',
      'link' => 'https://www.google.com',
    ];

    try {
      // Replace {page-id} with the ID of your page
      $response = $fb->post('/132886263246153/feed', $data, 'EAAFZAOFuro7QBOZBrLdQ5ZAWffRLw71nsSuZBZBcNsZAJBCyWSvDJEoWFRSuibj2i0p10MhBsZB6aZAQDVtWysvefbZCy4QJxY1fr8m6t9jkhcoOZAgFrZCFDkikZB2c90vMuRLP5ZBJaS8BM0BLtvOwTQHOkwpeD6DQnz8NyHiXphE0e3QUPNU7iYK8qZALNIpV05R6c25hsjWUPjimpLYZBA1vpqaAZCDywE2rGSwbq799o3jZBQZAEZD');
    } catch(Facebook\Exceptions\FacebookResponseException $e) {
        my_log('Graph returned an error: ' . $e->getMessage());
      exit;
    } catch(Facebook\Exceptions\FacebookSDKException $e) {
        my_log('Facebook SDK returned an error: ' . $e->getMessage());
      exit;
    }

    $graphNode = $response->getGraphNode();

    my_log('Posted with id: ' . $graphNode['id']);
}


function getAiTitleAndExcerpt($contentAndTitle) {

    $api_key = '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';

    // The prompt you want to send to GPT-3.5
    $prompt = 'Im giving you next a news article in the PC niche. 
    I need you to rewrite only the excerpt and title, the title should be very similar to the original, and I need you to return it in JSON format (only json), exactly like the example, without the "content". Remember to make a similar title as the original.
    So this is the original article:
    {
        "title": "' . $contentAndTitle['title'] . '",
        "excerpt": "' . $contentAndTitle['excerpt'] . '",
        "content": "' . $contentAndTitle['content'] . '"
    }
    So, I need you to return ONLY json for title and excerpt, nothing else before or after, like this example:
    {
        "title": "",
        "excerpt": ""
    }
    ';

    // cURL setup
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set the headers, including your API key
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $api_key;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the data you want to send
    $data = json_encode(array(
    'model' => 'gpt-3.5-turbo',
    'messages' => array(
        array('role' => 'user', 'content' => $prompt)
    )
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute the cURL request
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        my_log('curl error in getAiTitleAndExcerpt: ' . curl_error($ch));
        curl_close($ch);
        return 'error';
    }
    curl_close($ch);

    $responseArray = json_decode($result, true);

    // Check if json_decode was successful and the expected keys exist
    if (json_last_error() === JSON_ERROR_NONE &&
        isset($responseArray['choices'][0]['message']['content'])) {
        $contentJsonString = $responseArray['choices'][0]['message']['content'];
        // Output the content
        $titleAndExcerptArray = json_decode($contentJsonString, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            my_log("JSON for titleAndExcerptArray returned by AI error");
            return null;
        } else {
            return $titleAndExcerptArray;
        }
    } else {
        my_log("Error decoding JSON in getAiTitleAndExcerpt or accessing content field.");
        return null;
    }
}

function getAiContent($contentAndTitle) {

    $api_key = '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';

    // The prompt you want to send to GPT-3.5
    $prompt = 'Im giving you next a news article in the PC niche. 
    I need you to rewrite the content, making it longer, and I need you to return only the content, without comments before or after. 
    The "content" must be html formatted, and longer then the original. Dont make up facts and data.
    So this is the original article:
    {
        "title": "' . $contentAndTitle['title'] . '",
        "excerpt": "' . $contentAndTitle['excerpt'] . '",
        "content": "' . $contentAndTitle['content'] . '"
    }
    So, I need you to return ONLY the content, nothing else before or after:
    ';


    // cURL setup
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set the headers, including your API key
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $api_key;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the data you want to send
    $data = json_encode(array(
    'model' => 'gpt-3.5-turbo',
    'messages' => array(
        array('role' => 'user', 'content' => $prompt)
    )
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute the cURL request
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        my_log('curl error in getAiTitleAndExcerpt: ' . curl_error($ch));
        curl_close($ch);
        return 'error';
    }
    curl_close($ch);

    $responseArray = json_decode($result, true);

    // Check if json_decode was successful and the expected keys exist
    if (json_last_error() === JSON_ERROR_NONE &&
        isset($responseArray['choices'][0]['message']['content'])) {
        $theContent = $responseArray['choices'][0]['message']['content'];
        return $theContent;
    } else {
        my_log("Error decoding JSON getAiContent or accessing content field.");
        return null;
    }
}

function checkIfArticleExist($articleTitle) {
    //get last 15 articles
    $args = array(
        'posts_per_page' => 15, // Number of posts to fetch
        'order' => 'DESC',      // Fetch in descending order of post date
    );

    $query = new WP_Query($args);
    $titles = array();

    if($query->have_posts()) {
        while($query->have_posts()) {
            $query->the_post();
            $titles[] = get_the_title(); // Add the title to the titles array
        }
        wp_reset_postdata();
    }

    $titlesString = "";
    foreach($titles as $index => $title) {
        // Replace smart single quotes with regular single quotes
        $title = str_replace("&#8217;", '\'', $title);
        
        $titlesString .= ($index + 1) . ". " . $title . "\n"; 
    }

    //ask gpt
    $isExistent = askGptIfArticleExist($titlesString, $articleTitle);

    if($isExistent == null) {

    }
    else {
        return $isExistent;
    }
}

function askGptIfArticleExist($titlesString, $testTitle) {
    $api_key = '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';

    // The prompt you want to send to GPT-3.5
    $prompt = 'Given the list of titles below:
    \'' . $titlesString . '\'

    Determine if the following title is similar to or matches any in the list:
    \'' . $testTitle . '\'
    If a similar or matching title is found, respond with "1", otherwise "0".
    Only answer with 1 or 0, nothing else, only 1 or 0.';


    // cURL setup
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);

    // Set the headers, including your API key
    $headers = array();
    $headers[] = 'Content-Type: application/json';
    $headers[] = 'Authorization: Bearer ' . $api_key;
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the data you want to send
    $data = json_encode(array(
    'model' => 'gpt-3.5-turbo',
    'messages' => array(
        array('role' => 'user', 'content' => $prompt)
    )
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute the cURL request
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        my_log('curl error in askGptIfArticleExist: ' . curl_error($ch));
        curl_close($ch);
        return 'error';
    }
    curl_close($ch);

    $responseArray = json_decode($result, true);

    // Check if json_decode was successful and the expected keys exist
    if (json_last_error() === JSON_ERROR_NONE &&
        isset($responseArray['choices'][0]['message']['content'])) {
        $finalResponse = $responseArray['choices'][0]['message']['content'];
        return $finalResponse;
    } else {
        echo "Error decoding JSON checkIfArticleExist or accessing content field.";
        return null;
    }
}

function create_new_post($articleArray) {
    $thecontent = trim($articleArray['content']);
    if($thecontent == '' || $thecontent == null) {
        echo 'Content empty, article not created';
        my_log('Content empty, article not created');
        return null;
    }
    // Set the post content
    $my_post = array(
        'post_title'    => $articleArray['title'],
        'post_excerpt'  => $articleArray['excerpt'],
        'post_content'  => $thecontent,
        'post_status'   => 'publish', // Set the status to publish, or use 'draft' or 'pending'.
        'post_author'   => 1, // The user ID of the author. Change to the correct user ID.
        'post_type'     => 'post'
    );

    // INSERT POST
    $post_id = wp_insert_post( $my_post );

    //set category
    $category_id = 38; // This is the category ID you wish to assign to the post.
    wp_set_post_terms( $post_id, array( $category_id ), 'category' );

    // Check for errors
    if ( is_wp_error( $post_id ) ) {
        // The post could not be inserted. Output the WordPress error.
        my_log('Error inserting post: ' . $post_id->get_error_message() );
        echo 'Error inserting post';
    } else {
        // The post was inserted successfully
        my_log( 'Post was inserted successfully with ID: ' . $post_id );

       // Assuming $articleArray['img-url'] contains the URL of the image
       $image_url = $articleArray['img-url'];

       
       // Get the file name and extension
       $filename = time() . '.' . pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
       
       if (wp_mkdir_p(wp_upload_dir()['path'])) {
           $file = wp_upload_dir()['path'] . '/' . $filename;
       } else {
           $file = wp_upload_dir()['basedir'] . '/' . $filename;
       }

       // Download file to temp dir
       file_put_contents($file, file_get_contents($image_url));


       $wp_filetype = wp_check_filetype($filename, null);

       // Set attachment data
       $attachment = array(
           'post_mime_type' => $wp_filetype['type'],
           'post_title' => sanitize_file_name($filename),
           'post_content' => '',
           'post_status' => 'inherit'
       );

       // Create the attachment
       $attach_id = wp_insert_attachment($attachment, $file, $post_id);

       // Add alt text for the attachment
        $alt_text = $articleArray['title'];  // Replace with your desired alt text
        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);

       // Include image.php
       require_once(ABSPATH . 'wp-admin/includes/image.php');

       // Define attachment metadata
       $attach_data = wp_generate_attachment_metadata($attach_id, $file);

       // Assign metadata to attachment
       wp_update_attachment_metadata($attach_id, $attach_data);

       // And finally assign featured image to post
       set_post_thumbnail($post_id, $attach_id);

       // Add caption to the image
       $attachment_data = array(
           'ID' => $attach_id,
           'post_excerpt' => $articleArray['img-credit'] // Setting the caption
       );
       
       wp_update_post($attachment_data);
    }
}

//get AI article and post
function getAiAndPost($scrapedArticle) {
    //check if article exist
    if($check_exist == 'true') {
        $articleExist = checkIfArticleExist($scrapedArticle['title']);
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
        $titleAndExcerptArray = getAiTitleAndExcerpt($scrapedArticle);
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
        $theContent = getAiContent($scrapedArticle);
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
        $newArticle['img-url'] = $scrapedArticle['img-url'];
        $newArticle['img-credit'] = $scrapedArticle['img-credit'];


        //create post
        create_new_post($newArticle);

    }
    else {
        my_log('Check if article exist went wrong');
        echo "Something went wrong #1, try again";
    }
}


function addToPublishedList($title) {
    // Define the JSON file path
    $filePath = plugin_dir_path(__FILE__) . 'last-article-track.json';

    // Check if the file exists
    if (file_exists($filePath)) {
        // Read the existing content
        $jsonData = file_get_contents($filePath);
        // Decode the JSON data into an array
        $data = json_decode($jsonData, true);
    } else {
        // Initialize an empty array if the file does not exist
        $data = array();
    }

    // Add the new title to the array
    $data[] = $title;

    // Encode the array back into JSON
    $jsonData = json_encode($data, JSON_PRETTY_PRINT);


    // Write the JSON data back to the file
    file_put_contents($filePath, $jsonData);
}


function isTitleInPublishedList($title) {
    // Define the JSON file path
    $filePath = plugin_dir_path(__FILE__) . 'last-article-track.json';

    // Check if the file exists
    if (!file_exists($filePath)) {
        // If the file does not exist, the title is definitely not in the list
        return false;
    }

    // Read the existing content
    $jsonData = file_get_contents($filePath);
    // Decode the JSON data into an array
    $data = json_decode($jsonData, true);


    // Check if the title is in the array
    if (in_array($title, $data)) {
        // The title is in the list
        return true;
    } else {
        // The title is not in the list
        return false;
    }
}





?>