<?php

function addToPublishedList($url) {
    // Define the JSON file path
    $filePath = dirname(plugin_dir_path(__FILE__)) . '/scraped_urls.json';


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
    $data[] = $url;

    // Encode the array back into JSON
    $jsonData = json_encode($data, JSON_PRETTY_PRINT);

    // Write the JSON data back to the file
    file_put_contents($filePath, $jsonData);

    checkScrapedUelsJsonSizeAndShorten();
    checkLogsFolderSizeAndShorten();
}



function isUrlInPublishedList($title) {
    // Define the JSON file path
    $filePath = dirname(plugin_dir_path(__FILE__)) . '/scraped_urls.json';

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


function checkScrapedUelsJsonSizeAndShorten() {
    $filePath = dirname(plugin_dir_path(__FILE__)) . '/scraped_urls.json';


    if (file_exists($filePath)) {
        $jsonData = file_get_contents($filePath);
        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decode error
            my_second_log('ERROR', 'Error decoding JSON in checkJsonSizeAndShorten');
            return;
        }

        // Check if there are more than 130 URLs
        if (count($data) > 250) {
            // Keep only the most recent 100 URLs
            $newdata = array_slice($data, -100);

            my_log($newdata);

            // Encode the array back into JSON
            $newJsonData = json_encode($newdata, JSON_PRETTY_PRINT);
            file_put_contents($filePath, $newJsonData);
        }
    }
}


function checkLogsFolderSizeAndShorten() {
    $logFolder = dirname(plugin_dir_path(__FILE__)) . '/logs';
    $maxFiles = 30;
    $filesToDelete = 15;

    // Get all files in the logs folder
    $files = glob($logFolder . '/*');

    // Check the number of files
    $fileCount = count($files);

    // If the number of files exceeds the maximum, delete the specified number of oldest files
    if ($fileCount > $maxFiles) {
        // Sort the files by modification time (oldest first)
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // Delete the specified number of oldest files
        for ($i = 0; $i < $filesToDelete; $i++) {
            unlink($files[$i]);
        }
    }
}



function my_log($message) {
    if (is_array($message) || is_object($message)) {
        $message = print_r($message, true);
    }

    $message = date('Y-m-d H:i:s') . " " . $message;
    file_put_contents(dirname(plugin_dir_path(__FILE__)) . '/debug.log', $message . PHP_EOL, FILE_APPEND);
}


function my_second_log($level, $message) {
   $logFile = dirname(plugin_dir_path(__FILE__)) . '/logs/' . date('Y-m-d') . '.log';
   $timestamp = date('Y-m-d H:i:s');
   $logMessage = "[$timestamp] [$level] $message\n";
   file_put_contents($logFile, $logMessage, FILE_APPEND);
}



function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function getBaseUrl($url) {
   // $url = 'https://www.tomshardware.com/pc-components/news';

    // Parse the URL
    $parsed_url = parse_url($url);

    // Get the host component
    $host = $parsed_url['host'];

    // Remove 'www.' if it exists
    $host_without_www = preg_replace('/^www\./', '', $host);

    return $host_without_www;
}


function getSubstringBeforeFirstDot($inputString) {
    // Find the position of the first dot in the string
    $firstDotPosition = strpos($inputString, '.');

    // Check if a dot was found
    if ($firstDotPosition !== false) {
        // Extract the substring before the first dot
        $substringBeforeDot = substr($inputString, 0, $firstDotPosition);

        return $substringBeforeDot;
    } else {
        // If no dot is found, handle the situation accordingly
        return "No dot found in the string.";
    }
}