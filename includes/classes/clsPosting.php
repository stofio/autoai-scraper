<?php

class clsPosting {
    public function createNewPost($article, $categoryIDs) {
        try {
            $content = trim($article['content']);
            if (empty($content)) {
                throw new Exception('Content is empty, article cannot be created.');
            }
    
            $content = $this->getAndDownloadImagesInNewContent($content);
    
            $post_id = $this->insertPost($article, $categoryIDs);
            if (is_wp_error($post_id)) {
                my_second_log('ERROR', 'Failed to attach insert post');
                throw new Exception('Failed to insert post: ' . $post_id->get_error_message());
            }
    
            $attach_id = $this->attachFeaturedImage($post_id, $article);
            if (is_wp_error($attach_id)) {
                my_second_log('INFO', 'Failed to attach featured image');
            }
    
            return $post_id;
        } catch (Exception $e) {
            my_second_log('ERROR', $e->getMessage());
            return 'error';
        }
    }
    
    private function insertPost($article, $categoryIDs) {
        $categoryIDs = is_string($categoryIDs) ? array((int)$categoryIDs) : $categoryIDs;
    
        $post_data = array(
            'post_title'    => $article['title'],
            'post_excerpt'  => $article['excerpt'],
            'post_content'  => $article['content'],
            'post_status'   => 'publish',
            'post_author'   => 1, // or another user ID
            'post_type'     => 'post',
           'post_category' => $categoryIDs
        );
    
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
    
    
    private function getAndDownloadImagesInNewContent(&$content) {
        // Load the content into a DOMDocument for parsing
        $doc = new DOMDocument();
        @$doc->loadHTML($content);
    
        // Find all <img> tags
        $images = $doc->getElementsByTagName('img');
    
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
    
            // Download the image and get the new URL
            $newUrl = $this->downloadAndUploadImage($src);
    
            if ($newUrl) {
                // Replace the old src with the new URL
                $img->setAttribute('src', $newUrl);
            }
        }
    
        // Save the updated HTML content
        $content = $doc->saveHTML();
    
        return $content;
    }
    
    private function downloadAndUploadImage($imageUrl) {
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
    
        
}