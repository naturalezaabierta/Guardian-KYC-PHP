(function($) {
    'use strict';

    $(function() {
        var $container = $('#guardian-kyc-result-container');
        if (!$container.length) { return; }

        var retries = 0;
        var maxRetries = 15;
        var retryInterval = 5000;

        function checkSessionStatus() {
            if (retries === 0) {
                 $container.html('<h2>Procesando resultado...</h2><p>Esto puede tomar unos segundos. Por favor, no recargues la página.</p><span class="spinner is-active" style="float:none; margin-top:15px;"></span>');
            } else {
                 $container.find('h2').text('Confirmando con el servidor... (Intento ' + (retries + 1) + ' de ' + maxRetries + ')');
            }

            $.ajax({
                url: guardian_kyc_result_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'gkyc_check_status_from_mothership',
                    _wpnonce: guardian_kyc_result_obj.nonce
                }
            }).done(function(response) {
                if (response.success) {
                    var status = response.data.status;
                    var reason = response.data.reason || 'No se proporcionó un motivo específico.';
                    var html = '';

                    // ===== INICIO DE LA MODIFICACIÓN FINAL =====
                    
                    if (status === 'Approved') {
                        html = '<h2><span style="color: #28a745;">¡Verificación Aprobada!</span></h2>' +
                               '<p>Gracias, tu identidad ha sido confirmada exitosamente. Serás redirigido en 3 segundos...</p>';
                        $container.html(html);
                        setTimeout(function() { window.location.href = guardian_kyc_result_obj.account_page_url; }, 3000);

                    } else if (status === 'Rejected' || status === 'Declined') {
                        html = '<h2><span style="color: #dc3545;">Verificación no Completada</span></h2>' +
                               '<p><strong>Motivo:</strong> ' + reason + '</p>' +
                               '<a href="' + guardian_kyc_result_obj.verification_page_url + '" class="button button-primary" style="margin-top:20px;">Intentar de Nuevo</a>';
                        $container.html(html);
                    
                    } else if (status === 'In Review') {
                        // ¡NUEVO! Manejamos el estado "En Revisión Manual".
                        html = '<h2><span style="color: #007cba;">Verificación en Revisión Manual</span></h2>' +
                               '<p>Tus documentos han sido recibidos y están siendo revisados por nuestro equipo. Este proceso puede tardar unos minutos.</p>' +
                               '<p>Puedes cerrar esta ventana. Tu estado de verificación se actualizará automáticamente en tu perfil tan pronto como finalice la revisión.</p>' +
                               '<a href="' + guardian_kyc_result_obj.account_page_url + '" class="button button-secondary" style="margin-top:20px;">Ir a Mi Panel de Cuenta</a>';
                        $container.html(html);

                    } else { 
                        // Si el estado sigue siendo "In Progress" o desconocido, continuamos el bucle.
                        retries++;
                        if (retries < maxRetries) {
                            setTimeout(checkSessionStatus, retryInterval);
                        } else {
                            // Si se agotan los reintentos, mostramos el mensaje de tiempo de espera.
                            html = '<h2>La verificación está tardando más de lo esperado.</h2>' +
                                   '<p>¡No te preocupes! Tu resultado se está procesando en segundo plano. Tu estado se actualizará automáticamente en tu perfil. Ya puedes cerrar esta ventana.</p>' +
                                   '<a href="' + guardian_kyc_result_obj.account_page_url + '" class="button button-secondary" style="margin-top:20px;">Ir a Mi Panel de Cuenta</a>';
                            $container.html(html);
                        }
                    }
                    // ===== FIN DE LA MODIFICACIÓN FINAL =====

                } else {
                    var errorMsg = response.data.message || 'Ocurrió un error inesperado.';
                    $container.html('<h2><span style="color: #dc3545;">Error</span></h2><p>' + errorMsg + '</p><a href="' + guardian_kyc_result_obj.verification_page_url + '" class="button button-secondary" style="margin-top:20px;">Volver a Intentar</a>');
                }
            }).fail(function() {
                $container.html('<h2><span style="color: #dc3545;">Error de Comunicación</span></h2><p>No se pudo conectar con el servidor para obtener el resultado.</p><a href="' + guardian_kyc_result_obj.verification_page_url + '" class="button button-secondary" style="margin-top:20px;">Volver a Intentar</a>');
            });
        }
        
        checkSessionStatus();
    });
})(jQuery);