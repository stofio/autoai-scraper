<?php

global $wpdb;
$table_name = $wpdb->prefix . 'autoai_processed';
$results = $wpdb->get_results("SELECT * FROM $table_name");

?>

<div class="wrap" id="autoai-processed">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <h2>Manage processed URLs</h2>
        
        <table>
            <tr>
                <th>#</th>
                <th>URL</th>
                <th>Status</th>
                <th>Processed On</th>
            </tr>
            
            <?php $i = 1; ?>
            <?php foreach ($results as $result): ?>
            <tr>
                <td><?php echo $i; ?></td>
                <td>
                <a href="<?php echo $result->url; ?>" target="_blank"><?php echo $result->url; ?></a>
                </td>
                <td><?php echo $result->status; ?></td>
                <td><?php echo $result->processed_on; ?></td>
                <td><a href="<?php echo get_permalink($result->new_post_id); ?>" target="_blank">View posted</a></td>
                <td><a href="javascript:void(0);" class="delete-processed" data-proc-id="<?php echo $result->id; ?>">Delete</a></td>
            </tr>
            <?php $i++; ?>
            <?php endforeach; ?>
        </table>

</div>