jQuery(document).ready(function($) {

    const select = document.getElementById("log-file-select");
    const logCont = document.getElementById("log-display");  
    const refresh = document.getElementById("refresh");  

    // Run on page load
    displayLogs($(select).val()); 

    // Add click handler 
    $(select).on('change', (e) => {
        displayLogs($(e.target).val());
    });

    $(refresh).on('click', () => {
        displayLogs($(select).val());
    });

    function displayLogs(fileName) { 
        $('#refresh').css('disabled', true);
        $.ajax({
            url: scrapeaiLogs.ajax_url,
            method: 'POST',
            data: {
                action: 'display_logs',
                fileName: fileName,
            },
            success: function(logToDisplay) {
                logToDisplay = logToDisplay.endsWith('0') ? logToDisplay.slice(0, -1) : logToDisplay;
                if(logToDisplay) {
                    $(logCont).empty();
                    $(logCont).append(logToDisplay);

                    // Scroll to the bottom of the div
                    $('#log-display .log-container').scrollTop($('#log-display .log-container').prop("scrollHeight"));
                }
                else {
                    $(logCont).append('An error occured while getting and displaying log <br>');
                }                    
                $('#refresh').css('disabled', false);
            },
            error: function(xhr, status, error) {
                console.log(error);
            }
        });
    }


});