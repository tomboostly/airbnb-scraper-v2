jQuery(document).ready(function($) {

    function toggleButtonState() {
        if ($('#sync_data').val()) {
            $('#sync_button').removeAttr('disabled');
        } else {
            $('#sync_button').attr('disabled', 'disabled');
        }
    }

    // Check the state on page load in case the textbox is pre-filled
    toggleButtonState();

    // Add an event listener to the textbox for input change
    $('#sync_data').on('input', function() {
        toggleButtonState();
    });

    // Function to handle the AJAX request
    $('#sync_button').click(function(event) {
        event.preventDefault();
        // Show loading screen
        $('#loadingScreen').show();

        // Get the data from the input field
        var data = $('input[name="sync_data"]').val();

        // Perform the AJAX request
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'boostly_sync_action',
                data: data
            },
            success: function(response) {
                // Handle the response here
                console.log(response);
                reloadTemplate();
            },
            error: function(xhr, status, error) {
                // Handle errors here
                console.error(xhr.responseText);
            },
            complete: function(){
                $('#loadingScreen').hide();                
            }
        });
    });

    $('#sync-form').on('submit', function(e) {
        e.preventDefault();

        var ical_sync_value = $('#ical_sync').val();
        var property_sync_value = $('#property_sync').val();

        $.ajax({
            url: ajax_object.ajax_url, // Use the correct ajax URL in WordPress admin
            type: 'POST',
            data: {
                action: 'boostly_airbnb_v2_save_sync_options',
                ical_sync: ical_sync_value,
                property_sync: property_sync_value
            },
            success: function(response) {
                alert('Settings saved!');
            },
            error: function() {
                alert('Failed to save settings.');
            }
        });
    });

    // This assumes the select elements have the correct values set in their value attributes
    $('#ical_sync').val(boostly_settings_defaults(ajax_object.boostly_airbnb_v2_ical_sync_setting, 60));
    $('#property_sync').val(boostly_settings_defaults(ajax_object.boostly_airbnb_v2_property_sync_setting, 1440));    

    function boostly_settings_defaults(value, defaultValue){
        var intValue = parseInt(value);
        if(!isNaN(intValue) && intValue > 1){
            return intValue / 60;
        } else {
            return parseInt(defaultValue);
        }
    }

    // Function to reload the template
    function reloadTemplate() {
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'boostly_reload_list_template'
            },
            success: function(response) {
                // Assuming 'response' contains the HTML of the template
                $('#boostly_airbnb_v2').html(response); // Replace '#container' with the actual ID of your template container
            },
            error: function(xhr, status, error) {
                console.error('Error reloading template:', xhr.responseText);
            }
        });
    }    
});
