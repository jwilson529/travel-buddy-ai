(function( $ ) {
    'use strict';

    jQuery(document).ready(function($) {
        var isProcessing = false;

        $('#travelbuddy-search-form').on('submit', function(e) {
            e.preventDefault();

            if (isProcessing) {
                alert('Please wait for the current request to complete.');
                return;
            }

            var query = $('#travelbuddy-query').val();
            $('#travelbuddy-loading').show();
            isProcessing = true;

            $.ajax({
                url: travelbuddy_ajax_object.ajax_url,
                type: 'POST',
                data: {
                    action: 'travelbuddy_search',
                    query: query,
                    nonce: travelbuddy_ajax_object.nonce
                },
                success: function(response) {
                    $('#travelbuddy-loading').hide();
                    isProcessing = false;

                    if (response.success) {
                        var results = response.data;
                        var resultsContainer = $('#travelbuddy-results');
                        resultsContainer.empty();

                        // Display results (customize this as needed)
                        resultsContainer.append('<pre>' + JSON.stringify(results, null, 2) + '</pre>');
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    $('#travelbuddy-loading').hide();
                    isProcessing = false;
                    alert('AJAX Error: ' + error);
                }
            });
        });

        // Trigger form submit on Enter key press
        $('#travelbuddy-query').on('keypress', function(e) {
            if (e.which === 13) {
                $('#travelbuddy-search-form').submit();
                return false;
            }
        });
    });

})( jQuery );
