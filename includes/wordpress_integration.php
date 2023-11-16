<?php

//get AI article and post
function getAiAndPost($scrapedArticle) {
    $aiGeneratedContent = generateContentWithAI($scrapedArticle);

    // Check if content generation was successful
    if ($aiGeneratedContent === 'error') {
        my_second_log('ERROR', 'Failed to generate content with AI for article: ' . $scrapedArticle['title']);
        return 'error';
    }

    // Create a new post with the AI-generated content
    return createNewPost($aiGeneratedContent); // Returns 'success' or 'error'
}


function generateContentWithAI($article) {
    $titleAndExcerpt = getAiTitleAndExcerpt($article);

    // Check if title and excerpt were successfully generated
    if ($titleAndExcerpt === 'error') {
        return 'error'; // Propagate the error
    }

    $content = getAiContent($article);

    // Check if content was successfully generated
    if ($content === 'error') {
        return 'error'; // Propagate the error
    }

    // Return the combined AI-generated content
    return [
        'title' => $titleAndExcerpt['title'],
        'excerpt' => $titleAndExcerpt['excerpt'],
        'content' => $content,
        'img-url' => $article['img-url'],
        'img-credit' => $article['img-credit']
    ];
}



function createNewPost($article) {
    try {
        $content = trim($article['content']);
        if (empty($content)) {
            throw new Exception('Content is empty, article cannot be created.');
        }

        $post_id = insertPost($article);
        if (is_wp_error($post_id)) {
            throw new Exception('Failed to insert post: ' . $post_id->get_error_message());
        }

        $attach_id = attachFeaturedImage($post_id, $article);
        if (is_wp_error($attach_id)) {
            throw new Exception('Failed to attach featured image: ' . $attach_id->get_error_message());
        }

        return $post_id;
    } catch (Exception $e) {
        my_second_log('ERROR', $e->getMessage());
        return 'error';
    }
}



function insertPost($article) {
    $post_data = array(
        'post_title'    => $article['title'],
        'post_excerpt'  => $article['excerpt'],
        'post_content'  => $article['content'],
        'post_status'   => 'publish',
        'post_author'   => 1, // or another user ID
        'post_type'     => 'post',
        'post_category' => array(38) // Change to the desired category ID
    );

    $post_id = wp_insert_post($post_data);
    if (is_wp_error($post_id)) {
        my_second_log('ERROR', 'Failed to insert post: ' . $post_id->get_error_message());
        return $post_id; // Return WP_Error object
    }

    return $post_id; // Return the ID of the new post
}



function attachFeaturedImage($post_id, $article) {
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $image_url = $article['img-url'];
    $image_credit = $article['img-credit'];

    // Download and attach the image to the post
    $attach_id = media_sideload_image($image_url, $post_id, $image_credit, 'id');

    if (is_wp_error($attach_id)) {
        my_second_log('ERROR', 'Failed to attach image: ' . $attach_id->get_error_message());
        return $attach_id; // Return WP_Error object
    }

    // Set the image as the featured image of the post
    set_post_thumbnail($post_id, $attach_id);

    return $attach_id; // Return the attachment ID
}
