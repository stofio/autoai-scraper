jQuery(document).ready(function($) {
    $('#submit-bulk-scrape').click(function(e) {

        e.preventDefault();
        var urls = $('textarea[name="urls"]').val().split("\n").map($.trim).filter(Boolean); // Split URLs by line, trim whitespace, and remove empty lines
        var postStatus = $('input[name="post_status"]:checked').val();
        var tags = $('input[name="tags"]').val();
        var isDefaultCategories = $('input[name="is_default_categories"]:checked').val();
        var mainCategory = $('input[name="main_category_id"]:checked').val();
        var additionalCategories = $('input[name="additional_category_ids[]"]:checked').map(function() {
            return this.value;
        }).get();

        processNextUrl({
            urls: urls,
            postStatus: postStatus,
            tags: tags,
            isDefaultCategories: isDefaultCategories,
            mainCategory: mainCategory,
            additionalCategories: additionalCategories,
        }, 0); // Start processing with the first URL

    });

    //process all urls one by one
    function processNextUrl(formData, index) {
        if (index < formData.urls.length) {
            $.ajax({
                url: scrapeaiBulkScrape.ajax_url,
                method: 'POST',
                data: {
                    action: 'scrapeai_process_url',
                    url: formData.urls[index],
                    nonce: scrapeaiBulkScrape.nonce,
                },
                success: function(response) {
                    $('#bulk-scrape-progress').append('<p>' + response.data.message + '</p>');
                    // Process the next URL
                    processNextUrl(formData, index + 1);
                },
                error: function(xhr, status, error) {
                    // Handle errors, maybe try the URL again or log it
                    $('#bulk-scrape-progress').append('<p>Error processing URL: ' + formData.urls[index] + '</p>');
                    processNextUrl(formData, index + 1);
                }
            });
        } else {
            // All URLs processed
            $('#bulk-scrape-progress').append('<p>All URLs have been processed.</p>');
        }
    }
    
});