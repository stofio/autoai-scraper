<?php

function addToPublishedList($title) {
    // Define the JSON file path
    $filePath = plugin_dir_path(__FILE__) . 'last-article-track.json';

    return;

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