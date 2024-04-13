<?php
$logFolder = dirname(plugin_dir_path(__FILE__)) . '/logs';
$logFiles = glob($logFolder . '/*.log');
$logFiles = array_reverse($logFiles);
?>

<div class="wrap" id="autoai-processed">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <h2>View logs</h2>
    <select id="log-file-select">
        <?php
            
            foreach ($logFiles as $file) {
            $filename = basename($file);
            echo "<option value='$filename'>$filename</option>";
            }

        ?>
    </select>

    <button type="button" id="refresh">Refresh</button>

    
    <pre id="log-display"></pre>



</div>

