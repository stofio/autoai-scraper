jQuery(document).ready(function($) {
    $('#news_scraper_run_scraper').click(function(e){
        e.preventDefault();
        $.post(ajax_object.ajax_url, { action: 'run_scraper' }, function(response) {
            alert('Scraper run complete: ' + response);
        });
    });

    $('#news_scraper_run_from_url').click(function(e){
        e.preventDefault();
        var url = $('#news_scraper_url_input').val();
        var check_article_exist = $('#check_article_exist').is(':checked');
        $.post(ajax_object.ajax_url, { action: 'run_scraper_from_url', news_scraper_url: url, check_article_exist }, function(response) {
            alert('Scrape from URL complete: ' + response);
        });
    });


});