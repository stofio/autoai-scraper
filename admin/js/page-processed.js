jQuery(document).ready(function($) {

    $('.delete-processed').click(function(e) {

        if(!confirm('Are you sure you want to delete this processed item?')) return;
  
        var procId = $(this).data('proc-id');

        $.ajax({
            url: scrapeaiProcessed.ajax_url, 
            method: 'POST',
            data: {
                action: 'delete_processed',
                proc_id: procId,
                nonce: scrapeaiProcessed.nonce
            },
            success: function(response) {
                $(e.target).closest('tr').remove();
                console.log('Deleted processed');

            }
        });
  
    });
  
});