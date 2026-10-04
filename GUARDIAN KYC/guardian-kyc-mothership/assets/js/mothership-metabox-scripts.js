jQuery(document).ready(function($) {
    'use strict';

    // Función reutilizable para verificar duplicados
    function checkDuplicate(field) {
        var $field = $(field);
        var $parent = $field.parent();
        var value = $field.val().trim();
        var post_id = $('#post_ID').val();
        var meta_key = '';

        // Limpiamos mensajes anteriores
        $parent.find('.gkyc-duplicate-notice').remove();
        $('#publish').prop('disabled', false).removeClass('disabled');

        if (value === '') {
            return; // No hacemos nada si el campo está vacío
        }

        // Determinamos qué campo estamos validando
        if ($field.attr('id') === 'kyc_api_key_didit') {
            meta_key = '_api_key_didit';
        } else if ($field.attr('name').includes('kyc_wf_')) {
            // Esto es para los campos de workflow. Extraemos el meta_key del nombre del campo.
            var nameAttr = $field.attr('name'); // ej: kyc_wf_plan_basico
            meta_key = nameAttr.replace('kyc_wf_', '_wf_');
        } else {
            return; // No es un campo que necesitemos validar
        }

        // Añadimos un indicador de "cargando"
        $field.addClass('gkyc-loading');

        // Hacemos la llamada AJAX
        $.post(gkyc_metabox_obj.ajax_url, {
            action: 'gkyc_check_duplicate_meta',
            security: gkyc_metabox_obj.nonce,
            post_id: post_id,
            meta_key: meta_key,
            meta_value: value
        })
        .fail(function(response) {
            // .fail() se activa con wp_send_json_error
            var message = response.responseJSON.data.message || 'Error desconocido';
            $parent.append('<p class="description gkyc-duplicate-notice" style="color: #dc3545; font-weight: bold;">' + message + '</p>');
            $('#publish').prop('disabled', true).addClass('disabled'); // Desactivamos el botón de Guardar
        })
        .always(function() {
            // Se ejecuta siempre, al final de la llamada
            $field.removeClass('gkyc-loading');
        });
    }

    // Escuchamos el evento "blur" (cuando el usuario sale del campo)
    // para los campos que nos interesan.
    $('#kyc_api_key_didit, input[name^="kyc_wf_"]').on('blur', function() {
        checkDuplicate(this);
    });

    // Añadimos un poco de estilo para el indicador de carga
    $('head').append('<style>.gkyc-loading { background-image: url(/wp-admin/images/spinner.gif); background-repeat: no-repeat; background-position: center right 10px; }</style>');
});