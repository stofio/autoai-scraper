jQuery(document).ready(function($) {

    window.onload = function() {
        handleDefaultCategories();
    };

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
        var startDate = $('#start-date').val();
        var interval = $('#interval').val();

        if(!validateUrls(urls)) return;
        
        
        // Initialize progress bar
        $('#bulk-progress-bar').show();
        $('#progress').css('width', '1%');
        $('#progress span').html('1');
        $('#loadingTest').css('display', 'block'); // Start spin
        $('#progress-messages').empty();

        disableFormInputs(true);

        processNextUrl({
            urls: urls,
            postStatus: postStatus,
            tags: tags,
            isDefaultCategories: isDefaultCategories,
            mainCategory: mainCategory,
            additionalCategories: additionalCategories,
            startDate: startDate,
            interval: interval
        }, 0); // Start processing with the first URL

    });

    
    //process all urls one by one
    function processNextUrl(formData, index, retry) {
        if (index < formData.urls.length) {
            $.ajax({
                url: scrapeaiBulkScrape.ajax_url,
                method: 'POST',
                data: {
                    action: 'scrapeai_process_bulk_ajax',
                    url: formData.urls[index],
                    bulk_settings: { ...formData, urls: undefined, index: index },
                    nonce: scrapeaiBulkScrape.nonce
                },
                success: function(response) {
                    if(response.success) {
                        let postedUrl = response.data.message;
                        let postedLink = '<a href=" ' + postedUrl + ' ">' + postedUrl + '</a>'
                        $('#progress-messages').append( '<p>' + index + ' - URL processed successfully: ' + postedLink + '</p>');
                        updateProgressBar(index, formData.urls.length);
                        processNextUrl(formData, index + 1);
                    }
                    else {
                        $('#progress-messages').append( '<p>' + index + ' - Failed to process URL: ' + formData.urls[index] + '</p>');
                        updateProgressBar(index, formData.urls.length);
                        processNextUrl(formData, index + 1);
                    }
                },
                error: function(xhr, status, error) {
                    // Handle errors, maybe try the URL again or log it
                    console.log(error);
                    $('#progress-messages').append( '<p>' + index + ' - Ajax error for URL: ' + formData.urls[index] + '</p>');
                    updateProgressBar(index, formData.urls.length);
                    processNextUrl(formData, index + 1);
                }
            });
        } else {
            // All URLs processed
            $('#progress-messages').append('<p>All URLs have been processed.</p>');
            $('#progress').css('width', '100%');
            $('#progress span').html('100');
            disableFormInputs(false);
            $('#loadingTest').css('display', 'none'); // Stop spin
        }
    }

    function updateProgressBar(index, totUrls) {
        var progress = (((index + 1) / totUrls) * 100).toFixed(2);
        $('#progress').css('width', progress + '%');
        $('#progress span').html(progress);
    }

    // Function to disable or enable form inputs
    function disableFormInputs(disabled) {
        $('#autoai-bulk-scrape-form :input').prop('disabled', disabled);
    }

    function validateUrls(urls) {
        var totalUrls = urls.length;
        var invalidUrls = 0;
        var duplicatedUrls = 0;
    
        // Check if URLs array is empty
        if (totalUrls === 0) {
            alert("Please enter at least one URL.");
            return;
        }
    
        // Validate each URL
        var uniqueUrls = [];
        urls.forEach(function(url) {
            // Check for empty URL
            if (url === "") {
                invalidUrls++;
            } else {
                try {
                    // Check for valid URL syntax
                    new URL(url);
                    
                    // Check for duplicate URL
                    if (uniqueUrls.indexOf(url) !== -1) {
                        duplicatedUrls++;
                    } else {
                        uniqueUrls.push(url);
                    }
                } catch (error) {
                    invalidUrls++;
                }
            }
        });
    
        // Display validation results
        var message = "Total URLs: " + totalUrls + "\n";
        message += "Invalid URLs: " + invalidUrls + "\n";
        message += "Duplicated URLs: " + duplicatedUrls + "\n";
    
        if (invalidUrls > 0 || duplicatedUrls > 0) {
            message += "\nSome URLs are invalid or duplicated. Please review and correct them.";
        } else {
            message += "\nStart rewriting all the URLs?";
        }
    
        // Optionally, display confirmation message with validation results
        if (confirm(message)) {
            return true;
        }
    }


    function handleDefaultCategories() {
        var isDefaultCheckboxes = document.querySelectorAll('input[name="is_default_categories"]');
        var mainCategoryRadios = document.querySelectorAll('input[name="main_category_id"]');
        var additionalCategoryCheckboxes = document.querySelectorAll('input[name="additional_category_ids[]"]');
    
        isDefaultCheckboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                var isChecked = this.checked;
    
                mainCategoryRadios.forEach(function(radio) {
                    radio.disabled = isChecked;
                });
    
                additionalCategoryCheckboxes.forEach(function(checkbox) {
                    checkbox.disabled = isChecked;
                });
            });
        });
    }

});