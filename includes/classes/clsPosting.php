<?php
//this class is used for operations related to wordpress posting or the articles, with image processing

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


class clsPosting {
    public function createNewPost($article, $sourceSettings, $jobSettings) {
        try {
            $content = trim($article['content']);
            if (empty($content)) {
                throw new Exception('Content is empty, article cannot be created.');
            }

            $newContent = $this->getAndDownloadImagesInNewContent($content, $sourceSettings['_content_fetcher_images_credit'][0]);
            
            $article['content'] = $newContent;

            $post_id = $this->insertPost($article, $jobSettings, $sourceSettings);

            if (is_wp_error($post_id)) {
                my_second_log('ERROR', 'Failed to insert post');
                throw new Exception('Failed to insert post: ' . $post_id->get_error_message());
            }
    
            $attach_id = $this->attachFeaturedImage($post_id, $article);
            if (is_wp_error($attach_id)) {
                my_second_log('INFO', 'Failed to attach featured image');
            }
    
            return $post_id;
        } catch (Exception $e) {
            my_second_log('ERROR', $e->getMessage());
            return false;
        }
    }

 
    private function insertPost($article, $jobSettings, $sourceSettings) {
        //categories
        if(isset($jobSettings['isDefaultCategories'])) {
            $mainCategoryID = $sourceSettings['_content_fetcher_main_category'];
            $additionalCategoryIDs = $sourceSettings['_content_fetcher_additional_categories'];
        }
        else {
            $mainCategoryID = $jobSettings['mainCategory'];
            $additionalCategoryIDs = $jobSettings['additionalCategories'];
        }
    
        // Merge main category ID with additional category IDs
        $mainCategoryID = $mainCategoryID ?? [];
        $additionalCategoryIDs = $additionalCategoryIDs ?? [];

        // Ensure $mainCategoryID and $additionalCategoryIDs are arrays
        if (!is_array($mainCategoryID)) {
            $mainCategoryID = [$mainCategoryID];
        }
        if (!is_array($additionalCategoryIDs)) {
            $additionalCategoryIDs = [$additionalCategoryIDs];
        }

        $categoryIDs = array_merge($mainCategoryID, $additionalCategoryIDs);



        //POST STATUS
        // Check if scheduling options are provided
        if ($jobSettings['startDate'] != null) {
            $startDate = strtotime($jobSettings['startDate']);
            $interval = intval($jobSettings['interval']) * 3600;

            // Calculate post date based on position and interval
            $post_date = $startDate + ($interval * $jobSettings['index']);

            // Check if current time is after the calculated post date
            if (current_time('timestamp') < $post_date) {
                // Schedule the post for future publishing
                $post_status = 'future';
            }
        }
        else {
            if($jobSettings['postStatus'] == 'default') {
                $post_status = $sourceSettings['_content_fetcher_post_status'][0];
            }
            else {
                $post_status = $jobSettings['postStatus'];
            }
        }

    
        // Insert post
        $post_data = array(
            'post_title'    => $article['title'],
            'post_excerpt'  => $article['excerpt'],
            'post_content'  => $article['content'],
            'post_status'   => $post_status,
            'post_author'   => 1,
            'post_category' => $categoryIDs
        );

        if ($post_status == 'future') {
            $post_data['post_date'] = date('Y-m-d H:i:s', $post_date);
        }
    
        $post_id = wp_insert_post($post_data);
        if (is_wp_error($post_id)) {
            my_second_log('ERROR', 'Failed to insert post: ' . $post_id->get_error_message());
            return $post_id; // Return WP_Error object
        }
    
        return $post_id; // Return the ID of the new post
    } 
    
    
    
    
    private function updatePost($article, $post_id) {
        $post_data = array(
            'ID'            => $post_id,
            'post_title'    => $article['title'],
            'post_excerpt'  => $article['excerpt'],
            'post_content'  => $article['content'],
            'post_status'   => 'publish',
            'post_author'   => 1, // or another user ID
            'post_type'     => 'post',
            'post_category' => array($categoryID)
        );
    
        $updated_post_id = wp_update_post($post_data);
    
        if (is_wp_error($updated_post_id)) {
            my_second_log('ERROR', 'Failed to update post: ' . $updated_post_id->get_error_message());
            return $updated_post_id; // Return WP_Error object
        }
    
        return $updated_post_id; // Return the ID of the updated post
    }
    
    
    
    private function attachFeaturedImage($post_id, $article) {
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
    
    
    private function getAndDownloadImagesInNewContent(&$content, $imgCaption) {
        $content = mb_convert_encoding('<?xml encoding="UTF-8">' . $content, 'UTF-8', 'auto');
        // Load the content into a DOMDocument for parsing
        $doc = new DOMDocument();
        @$doc->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $doc->encoding = 'UTF-8';
    
        // Find all <img> tags
        $images = $doc->getElementsByTagName('img');
    
        foreach ($images as $img) {
            $src = $img->getAttribute('src');

           // if(!isValidUrl($src)) continue;

            $newUrl = $this->downloadAndUploadImage($src, $imgCaption);

            if ($newUrl) {
                // Replace the old src with the new URL
                $img->setAttribute('src', $newUrl);
            }
        }
        // Save the updated HTML content
        $content = mb_convert_encoding($doc->saveHTML(), 'UTF-8', 'HTML-ENTITIES');

        return $content;
    }
    
    private function downloadAndUploadImage($imageUrl, $imgCaption) {
       if(!$this->isValidImageUrl($imageUrl)) return null;

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
    
        // Download the image
        $imageData = file_get_contents($imageUrl);
        if ($imageData === false) {
            my_second_log('ERROR', 'Failed to download image: ' . $imageUrl);
            return false;
        }
    
        // Determine the filename and extension
        $filename = basename(parse_url($imageUrl, PHP_URL_PATH));
        $upload = wp_upload_bits($filename, null, $imageData);
    
        if ($upload['error']) {
            my_second_log('ERROR', 'Error in uploading image: ' . $upload['error']);
            return false;
        }
        // Prepare an array for the attachment
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_excerpt' => $imgCaption,
            'post_content' => '',
            'post_status' => 'inherit'
        );

        // Insert the attachment
        $attach_id = wp_insert_attachment($attachment, $upload['file']);
    
        // Generate attachment metadata and update the attachment metadata
        $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
    
        // Return the URL of the uploaded image
        return wp_get_attachment_url($attach_id);
    }

    function isValidImageUrl($url) {
        

        if(!filter_var($url, FILTER_VALIDATE_URL)) {
         return false;
        }
      
        $path = parse_url($url, PHP_URL_PATH);
        
        // Check common image extensions
        $imageExtensions = array('jpg', 'jpeg', 'png', 'gif', 'bmp'); 
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        if(!in_array($ext, $imageExtensions)) {
            return false;
        }
      
        // Attempt to get image size
        $imageSize = getimagesize($url);
        
        if($imageSize === false) {
            return false; 
        }
        
        return true;
      
    }
    
    
    
        
}