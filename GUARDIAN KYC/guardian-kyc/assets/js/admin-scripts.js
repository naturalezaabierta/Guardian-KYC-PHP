jQuery(document).ready(function($) {
    'use strict';

    $('#upload_logo_button').on('click', function(e) {
        e.preventDefault();

        var mediaUploader = wp.media({
            title: 'Selecciona tu Logo',
            button: {
                text: 'Usar este Logo'
            },
            multiple: false
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#custom_logo_url').val(attachment.url);
        });

        mediaUploader.open();
    });
});