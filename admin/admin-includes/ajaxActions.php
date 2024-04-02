<?php


require_once plugin_dir_path(__FILE__) . 'clsBulkHandler.php';
$bulk = new BulkHandler;
add_action('wp_ajax_scrapeai_process_bulk_ajax', array($bulk, 'scrapeai_process_bulk_ajax'));




?>