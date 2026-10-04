jQuery(document).ready(function($) {
    'use strict';

    var verificationBtn = $('#guardian-kyc-start-verification-btn');
    var statusDiv = $('#guardian-kyc-verification-status');
    var privacyCheckbox = $('#guardian_kyc_privacy_consent');
    var termsCheckbox = $('#guardian_kyc_terms_consent');

    function checkConsents() {
        var privacyOk = privacyCheckbox.length ? privacyCheckbox.is(':checked') : true;
        var termsOk = termsCheckbox.length ? termsCheckbox.is(':checked') : true;

        if (privacyOk && termsOk) {
            verificationBtn.prop('disabled', false).css('cursor', 'pointer');
        } else {
            verificationBtn.prop('disabled', true).css('cursor', 'not-allowed');
        }
    }

    // Comprueba el estado al cargar la página
    checkConsents();

    // Vuelve a comprobar cada vez que se hace clic en un checkbox
    $('#guardian_kyc_privacy_consent, #guardian_kyc_terms_consent').on('change', checkConsents);

    // ===== INICIO DE LA NUEVA LÓGICA PARA EL CLIC DEL BOTÓN =====
    verificationBtn.on('click', function(e) {
        e.preventDefault();

        // Desactivamos el botón y mostramos un spinner para que el usuario sepa que algo pasa.
        verificationBtn.prop('disabled', true).text('Iniciando...');
        statusDiv.html('<span class="spinner is-active" style="float:none;"></span> Conectando con el servicio de verificación...');

        // Hacemos la llamada AJAX usando la nueva API REST
        $.ajax({
            url: gkyc_verification_obj.rest_url, // <-- Usa la nueva URL REST que pasamos desde PHP
            type: 'POST',
            beforeSend: function (xhr) {
                // El nonce de seguridad ahora se envía en la cabecera (header)
                xhr.setRequestHeader('X-WP-Nonce', gkyc_verification_obj.nonce);
            }
        }).done(function(response) {
            // Si todo sale bien, la respuesta contendrá la URL de Didit.
            if (response.redirect_url) {
                // Redirigimos al usuario a la página de verificación de Didit.
                window.location.href = response.redirect_url;
            } else {
                 statusDiv.html('<div class="notice notice-error"><p>No se recibió una URL de redirección. Por favor, intenta de nuevo.</p></div>');
                 verificationBtn.prop('disabled', false).text('Iniciar Verificación Segura');
            }
        }).fail(function(response) {
            // Si hay un error, lo mostramos en pantalla.
            var errorMsg = response.responseJSON && response.responseJSON.message ? response.responseJSON.message : 'Ocurrió un error inesperado al iniciar el proceso.';
            statusDiv.html('<div class="notice notice-error"><p>' + errorMsg + '</p></div>');
            verificationBtn.prop('disabled', false).text('Iniciar Verificación Segura');
        });
    });
    // ===== FIN DE LA NUEVA LÓGICA =====
});