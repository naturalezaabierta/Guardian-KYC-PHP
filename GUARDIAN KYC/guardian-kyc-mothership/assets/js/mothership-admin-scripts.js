jQuery(document).ready(function($) {
    'use strict';

    $('#upload_pdf_button').on('click', function(e) {
        e.preventDefault();

        var mediaUploader = wp.media({
            title: 'Selecciona la Guía PDF para Partners',
            button: {
                text: 'Usar este Archivo'
            },
            multiple: false,
            library: {
                type: 'application/pdf' // Solo muestra archivos PDF
            }
        });

        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#partner_pdf_url').val(attachment.url);
        });

        mediaUploader.open();
    });
});