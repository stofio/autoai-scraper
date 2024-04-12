jQuery(document).ready(function($) {
    // When the image inside the metabox is clicked
    $('#hintCatPage').on('click', function() {
        // Create a modal overlay
        var overlay = $('<div id="custom-overlay"></div>');

        // Create a container for the content
        var contentContainer = $('<div id="custom-content"></div>');

        // Add long scrollable text content
        contentContainer.append('<p>Long scrollable text goes here...</p>');

        // Append content container to the overlay
        overlay.append(contentContainer);

        // Append overlay to the body
        $('body').append(overlay);

        // Close the modal when overlay is clicked
        overlay.on('click', function() {
            $(this).remove();
        });
    });


    $('#testSourceButton').on('click', function(e) {
        e.preventDefault();
        var url = $('#test_source_url').val();
        var title = $('input[name="title"]').val();
        var content = $('input[name="content"]').val();
        var imageUrl = $('input[name="imageUrl"]').val();
        
        // AJAX request
        $.ajax({
            url: scrapeaiSingleSource.ajax_url,
            type: 'post',
            data: {
                action: 'test_source',
                url: url,
                nonce: scrapeaiSingleSource.nonce,
                title: title,
                content: content,
                imageUrl: imageUrl
            },
            success: function(response) {
                if (response.success) {
                    var scrapedData = response.data;
                    var content = `<div id="overlay-content">
                        <h1>${scrapedData.title}</h1>
                        <img src="${scrapedData['img-url']}"/>
                        ${scrapedData.content}
                    </div>`;
                    var overlay = $('<div id="overlay"></div>');
                    var contentContainer = $('<div id="overlay-content"></div>');

                    contentContainer.html(content);
                    overlay.append(contentContainer);
                    $('body').append(overlay);
                    // Close the modal when overlay is clicked
                    overlay.on('click', function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error fetching content');
                }
            },
            error: function() {
                alert('Error fetching content');
            }
        });
    });


    $('#testCategoryButton').on('click', function(e) {
        e.preventDefault();
        var url = $('input[name="baseUrl"]').val();
        var container = $('input[name="postsListContainer"]').val();
        var href = $('input[name="catPageLastArticleHref"]').val();
        
        // AJAX request
        $.ajax({
            url: scrapeaiSingleSource.ajax_url,
            type: 'post',
            data: {
                action: 'test_category',
                nonce: scrapeaiSingleSource.nonce,
                url: url,
                container: container,
                href: href
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    if(response.data.length === 0) {
                        alert('No articles found. Please check the category URL and container selector.');
                    } else {
                        var articlesList = $('<ol></ol>');
                        response.data.forEach(function(url) {
                            articlesList.append(`<li><a href="${url}">${url}</a></li>`);
                        });
                        var content = `<div id="overlay-content">
                            <ol>
                                ${articlesList[0].innerHTML}
                            </ol>
                        </div>`;
                        var overlay = $('<div id="overlay"></div>');
                        var contentContainer = $('<div id="overlay-content"></div>');
                        
                        contentContainer.html(content);
                        overlay.append(contentContainer);
                        $('body').append(overlay);
                        // Close the modal when overlay is clicked
                        overlay.on('click', function() {
                            $(this).remove();
                        });
                    }   
                } else {
                    alert('Error fetching category page');
                }
            },
            error: function() {
                alert('Error fetching category page');
            }
        });
    });

    //
    $('#seeChunkFullPrompt').on('click', function() {
        //get prompt
        $.ajax({
            url: scrapeaiSingleSource.ajax_url,
            type: 'post',
            data: {
                action: 'display_full_prompt',
                nonce: scrapeaiSingleSource.nonce
            },
            success: function(response) {
                console.log(response);
                
                var overlay = $('<div id="overlay"></div>');
                var contentContainer = $('<div id="overlay-content"></div>');
                const div = document.createElement('div');

                response.data.split('\n').forEach(line => {
                    div.appendChild(document.createTextNode(line));
                    div.appendChild(document.createElement('br')); 
                  });

                contentContainer.html(div);
                overlay.append(contentContainer);
                $('body').append(overlay);
                // Close the modal when overlay is clicked
                overlay.on('click', function() {
                    $(this).remove();
                });
                
            }
        });

        
    });

});
