jQuery(document).ready(function($) {

    // --- LÓGICA DE GRÁFICOS ---
    if (typeof gkyc_partner_obj !== 'undefined' && gkyc_partner_obj.chart_data) {
        var activityCanvas = document.getElementById('gkyc-partner-activity-chart');
        if (activityCanvas) {
            new Chart(activityCanvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: gkyc_partner_obj.chart_data.activity.labels,
                    datasets: [{
                        label: 'Verificaciones por Día',
                        data: gkyc_partner_obj.chart_data.activity.data,
                        backgroundColor: 'rgba(0, 124, 186, 0.1)',
                        borderColor: 'rgba(0, 124, 186, 1)',
                        borderWidth: 2, tension: 0.3
                    }]
                },
                options: { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
            });
        }
        var statusCanvas = document.getElementById('gkyc-partner-status-chart');
        if (statusCanvas) {
            new Chart(statusCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: gkyc_partner_obj.chart_data.status.labels,
                    datasets: [{
                        label: 'Desglose de Verificaciones',
                        data: gkyc_partner_obj.chart_data.status.data,
                        backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
                    }]
                },
                options: { plugins: { legend: { position: 'bottom' } } }
            });
        }
    }

    // --- LÓGICA DE MODALES (BÚSQUEDA Y CREACIÓN DE LICENCIA) ---
    $('#gkyc-client-search').on('keyup', function() { var searchTerm = $(this).val().toLowerCase(); $('#gkyc-client-list-tbody tr').each(function() { var rowText = $(this).data('search-term').toLowerCase(); if (rowText.includes(searchTerm)) { $(this).show(); } else { $(this).hide(); } }); });
    var licenseModal = $('#gkyc-generate-license-modal');
    $('#gkyc-open-modal-btn').on('click', function(e) { e.preventDefault(); licenseModal.css('display', 'flex'); });

    $('#gkyc-submit-new-license').off('click').on('click', function(e) {
        e.preventDefault();
        var $button = $(this); 
        var $notice = $('#gkyc-modal-notice');
        $notice.text('').hide();
        $button.prop('disabled', true).text('Generando...');

        var clientData = { 
            client_name: $('#gkyc-new-client-name').val(), 
            client_email: $('#gkyc-new-client-email').val(), 
            license_type: $('#gkyc-new-license-type').val(), 
            client_website: $('#gkyc-new-client-website').val(),
            client_phone: $('#gkyc-new-client-phone').val() 
        };

        $.ajax({
            url: gkyc_partner_obj.urls.generate_license,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            contentType: 'application/json', 
            data: JSON.stringify(clientData),
            success: function(response) { 
                $notice.text(response.message).css('color', 'green').show(); 
                setTimeout(function() { location.reload(); }, 2500); 
            },
            error: function(response) { 
                var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Ocurrió un error.'; 
                $notice.text('Error: ' + errorMsg).css('color', 'red').show(); 
                $button.prop('disabled', false).text('Crear Licencia'); 
            }
        });
    });

    // --- INICIO: NUEVA LÓGICA COMPLETA PARA EL MODAL DE TRANSFERENCIA ---
    var transferModal = $('#gkyc-transfer-balance-modal');
    var transferAmountInput = $('#gkyc-transfer-amount');
    var calcResults = $('#gkyc-transfer-calculator-results');
    var calcVerifications = $('#gkyc-calc-verifications');
    var calcCost = $('#gkyc-calc-cost');
    var calcProfit = $('#gkyc-calc-profit');
    var transferClientIdField = $('#gkyc-transfer-client-id');
    var transferNotice = $('#gkyc-transfer-modal-notice');

    // 1. Cuando se abre el modal
    $('.gkyc-manage-client-btn').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        transferClientIdField.val($button.data('client-id'));
        $('#gkyc-transfer-client-name').text($button.data('client-name'));
        $('#gkyc-transfer-client-email').text($button.data('client-email')); // <-- LÍNEA NUEVA
        $('#gkyc-transfer-client-website').text($button.data('client-website') || 'N/A'); // <-- LÍNEA NUEVA
        $('#gkyc-transfer-client-balance').text('$' + parseFloat($button.data('client-balance')).toFixed(2));
        
        // Reseteamos el formulario
        transferAmountInput.val('');
        calcResults.hide();
        transferNotice.hide().removeClass('success error');
        $('#gkyc-submit-transfer-btn').prop('disabled', false).text('Confirmar Transferencia');

        transferModal.css('display', 'flex');
    });

    // 2. Cuando el socio escribe en el campo de monto (v3 - VERSIÓN PROFESIONAL ESTABLE)
    var debounceTimeout;
    var currentRequest = null; // Variable para gestionar la petición activa

    transferAmountInput.on('keyup', function() {
        clearTimeout(debounceTimeout);
        var $input = $(this);

        debounceTimeout = setTimeout(function() {
            // Cancelamos cualquier petición anterior que aún esté en curso
            if (currentRequest) {
                currentRequest.abort();
            }

            var amount = parseFloat($input.val());
            var clientId = transferClientIdField.val();

            if (isNaN(amount) || amount <= 0 || !clientId) {
                calcResults.hide();
                return;
            }
            
            // Mostramos un feedback visual INMEDIATO al usuario
            calcResults.show();
            calcVerifications.text('...');
            calcCost.text('Calculando...');
            calcProfit.text('Calculando...');

            // Hacemos la llamada AJAX y la guardamos en nuestra variable
            currentRequest = $.ajax({
                url: gkyc_partner_obj.urls.calculate_transfer_profit,
                type: 'POST',
                beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
                contentType: 'application/json',
                data: JSON.stringify({ client_id: clientId, amount: amount }),
                success: function(response) {
                    if (response.status === 'success') {
                        calcVerifications.text(response.verifications);
                        calcCost.text('$' + response.profit.toFixed(2));
                        calcProfit.text('$' + response.cost.toFixed(2));
                    } else {
                        calcResults.hide();
                    }
                },
                error: function(jqXHR) {
                    // Nos aseguramos de no mostrar un error si nosotros mismos cancelamos la petición
                    if (jqXHR.statusText !== 'abort') {
                        calcResults.hide();
                    }
                },
                complete: function() {
                    // Limpiamos la variable para la siguiente petición
                    currentRequest = null;
                }
            });
        }, 350); // Ajustamos ligeramente el tiempo de espera para un balance óptimo
    });

    // 3. Cuando se confirma la transferencia (lógica de envío)
    $('#gkyc-submit-transfer-btn').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        $button.prop('disabled', true).text('Procesando...');
        transferNotice.hide().removeClass('success error');

        var clientId = transferClientIdField.val();
        var amount = transferAmountInput.val();

        $.ajax({
            url: gkyc_partner_obj.urls.transfer_balance,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            contentType: 'application/json',
            data: JSON.stringify({ client_id: clientId, amount: amount }),
            success: function(response) {
                transferNotice.text(response.message || 'Transferencia completada.').addClass('success').show();
                setTimeout(function() { location.reload(); }, 2000);
            },
            error: function(response) {
                var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Ocurrió un error.';
                transferNotice.text('Error: ' + errorMsg).addClass('error').show();
                $button.prop('disabled', false).text('Confirmar Transferencia');
            }
        });
    });
    // --- FIN: NUEVA LÓGICA COMPLETA PARA EL MODAL DE TRANSFERENCIA ---

    // Lógica para cerrar los modales (MUY IMPORTANTE NO BORRAR)
    $('.gkyc-modal-close').on('click', function() { $(this).closest('.gkyc-modal').hide(); });
    $(window).on('click', function(e) { if ($(e.target).hasClass('gkyc-modal')) { $(e.target).hide(); } });

    // --- LÓGICA PARA GUARDAR PRECIOS ---
    $('#gkyc-partner-prices-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this); var $button = $form.find('button[type="submit"]'); var $notice = $('#gkyc-prices-form-notice');
        $button.prop('disabled', true).text('Guardando...'); $notice.text('').hide();
        var formData = $form.serialize();
        $.ajax({
            url: gkyc_partner_obj.urls.update_prices, type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            data: formData,
            success: function(response) { $notice.text(response.message || 'Precios guardados.').css('color', 'green').show(); },
            error: function(response) { var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Error.'; $notice.text('Error: ' + errorMsg).css('color', 'red').show(); },
            complete: function() { $button.prop('disabled', false).text('Guardar Cambios de Precios'); }
        });
    });

    // --- LÓGICA PARA AUTO-RELLENAR HTTPS EN EL CAMPO DE SITIO WEB ---
    $('#gkyc-generate-license-modal').on('blur', '#gkyc-new-client-website', function() {
        var $field = $(this);
        var url = $field.val().trim();
        if (url !== '' && !url.startsWith('http://') && !url.startsWith('https://')) {
            $field.val('https://' + url);
        }
    });

    // --- LÓGICA DEL ACTUALIZADOR DE SALDO EN TIEMPO REAL ---
    function formatPartnerCurrency(number) { return '$' + parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
    function checkPartnerBalance() {
    $.ajax({
        url: gkyc_partner_obj.ajax_url, type: 'POST',
        data: { action: 'gkyc_get_partner_balance' },
        success: function(response) {
            if (response.success) {
                var newBalance = response.data.balance;
                var container = $('#partner-master-balance');
                if (container.length > 0) {
                    var currentBalanceText = container.text().replace(/[^0-9.]/g, '');

                    // Si el saldo que vemos es diferente al saldo real del servidor...
                    if (parseFloat(currentBalanceText) !== newBalance) {
                        // ... ¡recargamos la página completa para actualizar todo!
                        location.reload();
                    }
                }
            }
        }
    });
}
// --- FIN: REEMPLAZO DE checkPartnerBalance ---
setInterval(checkPartnerBalance, 5000);

    // --- LÓGICA PARA LA CALCULADORA DE RENTABILIDAD ---
    $('#gkyc-profit-calculator-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var resultsContainer = $('#gkyc-profit-results');
        resultsContainer.hide().html('');
        $button.prop('disabled', true).text('Generando enlace...');
        var selectedOption = $('#profit-calc-plan').find('option:selected');
        var amountReceived = parseFloat($('#profit-calc-amount').val());
        var resalePrice = parseFloat(selectedOption.data('resale-price'));
        var partnerCost = parseFloat(selectedOption.data('partner-cost'));
        if (!selectedOption.val()) { resultsContainer.html('<p style="color: red;">Por favor, selecciona un plan para calcular.</p>').show(); $button.prop('disabled', false).text('Calcular'); return; }
        if (isNaN(amountReceived) || amountReceived <= 0) { resultsContainer.html('<p style="color: red;">Por favor, introduce un monto válido que has recibido.</p>').show(); $button.prop('disabled', false).text('Calcular'); return; }
        if (isNaN(resalePrice) || resalePrice <= 0) { resultsContainer.html('<p style="color: red;">No se ha configurado un precio de reventa para este plan. Por favor, ve a "Mis Precios y Pagos" y define un precio mayor a cero.</p>').show(); $button.prop('disabled', false).text('Calcular'); return; }
        var balanceForClient = amountReceived;
        var verificationsForClient = Math.floor(balanceForClient / resalePrice);
        var masterBalanceNeeded = verificationsForClient * partnerCost;
        var netProfit = balanceForClient - masterBalanceNeeded;
        $.ajax({
            url: gkyc_partner_obj.urls.generate_payment_token,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            contentType: 'application/json',
            data: JSON.stringify({ charge: masterBalanceNeeded, credit: balanceForClient }),
            success: function(response) {
                if (response.token) {
                    var checkoutUrl = new URL(gkyc_partner_obj.urls.checkout);
                    checkoutUrl.searchParams.set('add-to-cart', 2093);
                    var amountToCharge = Math.ceil(masterBalanceNeeded > 0 ? masterBalanceNeeded : 0);
                    checkoutUrl.searchParams.set('quantity', amountToCharge);
                    checkoutUrl.searchParams.set('return_url', window.location.href);
                    checkoutUrl.searchParams.set('gkyc_token', response.token);
                    var resultsHTML = `<h4>Resultados del Cálculo:</h4><ul style="list-style: none; padding-left: 0; text-align: left;"><li style="margin-bottom: 10px;">Monto recibido de tu cliente: <strong>$${balanceForClient.toFixed(2)}</strong></li><li style="margin-bottom: 10px;">Tu costo (lo que nos pagarás): <strong>$${masterBalanceNeeded.toFixed(2)}</strong></li><li style="font-size: 1.2em; margin-bottom: 20px;"><strong>Tu Ganancia Neta: <span style="color: #28a745;">$${netProfit.toFixed(2)}</span></strong></li></ul><a href="${checkoutUrl.href}" class="button button-primary">Pagar ${amountToCharge}.00 USD para recibir ${balanceForClient.toFixed(2)} USD de Saldo</a>`;
                    resultsContainer.html(resultsHTML).show();
                } else { resultsContainer.html('<p style="color: red;">Error: No se recibió un token de pago del servidor.</p>').show(); }
            },
            error: function() { resultsContainer.html('<p style="color: red;">No se pudo generar un enlace de pago seguro. Intenta de nuevo.</p>').show(); },
            complete: function() { $button.prop('disabled', false).text('Calcular'); }
        });
    });

    // --- LÓGICA MEJORADA PARA FILAS CLICABLES ---
    $('#gkyc-client-list-tbody').on('click', '.gkyc-clickable-row', function(e) {
        if ($(e.target).closest('.gkyc-manage-client-btn').length > 0) { return; }
        var href = $(this).data('href');
        if (href) { window.location.href = href; }
    });

    // --- LÓGICA PARA EL FORMULARIO DE "MI MARCA" ---
    var mediaUploader;
    $('body').on('click', '.gkyc-upload-button', function(e) {
        e.preventDefault();
        var inputId = $(this).data('input-id');
        var previewId = $(this).data('preview-id');
        if (mediaUploader) {
            mediaUploader.off('select').on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#' + inputId).val(attachment.url);
                $('#' + previewId).attr('src', attachment.url).show();
            });
            mediaUploader.open(); return;
        }
        mediaUploader = wp.media({ title: 'Seleccionar Logo', button: { text: 'Usar este Logo' }, library: { type: 'image' }, multiple: false });
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#' + inputId).val(attachment.url);
            $('#' + previewId).attr('src', attachment.url).show();
        });
        mediaUploader.open();
    });

    // --- Lógica para guardar el formulario de Branding y Pagos ---
    $('#gkyc-partner-branding-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $notice = $('#gkyc-branding-form-notice');
        $button.prop('disabled', true).text('Guardando...');
        $notice.text('').hide();
        if (typeof tinymce !== 'undefined' && tinymce.get('partner_other_payments')) { tinymce.get('partner_other_payments').save(); }

        var formData = {
            partner_website_url: $('#partner_website_url').val(),
            partner_recharge_url: $('#partner_recharge_url').val(),
            partner_support_email: $('#partner_support_email').val(),
            partner_support_whatsapp: $('#partner_support_whatsapp').val(),
            partner_logo_1x1_url: $('#partner_logo_1x1_url').val(),
            partner_logo_2x1_url: $('#partner_logo_2x1_url').val(),
            partner_favicon_url: $('#partner_favicon_url').val(),
            partner_paypal_link: $('#partner_paypal_link').val(),
            partner_usdt_wallet: $('#partner_usdt_wallet').val(),
            partner_other_payments: $('#partner_other_payments').val(),
            partner_auto_credit_enabled: $('#partner_auto_credit_enabled').is(':checked') ? 'yes' : 'no',
            partner_auto_credit_amount: $('#partner_auto_credit_amount').val()
        };

        $.ajax({
            url: gkyc_partner_obj.urls.update_branding,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) { $notice.text(response.message || 'Guardado con éxito.').css('color', 'green').show(); },
            error: function(response) {
                var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Ocurrió un error.';
                $notice.text('Error: ' + errorMsg).css('color', 'red').show();
            },
            complete: function() { $button.prop('disabled', false).text('Guardar Cambios'); }
        });
    });

    // --- LÓGICA UNIFICADA PARA APROBAR Y RECHAZAR SOLICITUDES (LICENCIAS Y RECARGAS) ---
    $('.gkyc-partner-dashboard').on('click', '.gkyc-approve-request-btn, .gkyc-reject-request-btn, .gkyc-approve-recharge-btn, .gkyc-reject-recharge-btn', function(e) {
        e.preventDefault();
        var $button = $(this);
        var requestId = $button.data('request-id');
        var isLicense = $button.hasClass('gkyc-approve-request-btn') || $button.hasClass('gkyc-reject-request-btn');
        var action = $button.hasClass('gkyc-approve-request-btn') || $button.hasClass('gkyc-approve-recharge-btn') ? 'approve' : 'reject';
        
        var apiUrl = isLicense ? gkyc_partner_obj.urls.process_request : gkyc_partner_obj.urls.process_recharge;
        var $row = isLicense ? $('#request-row-' + requestId) : $('#recharge-request-row-' + requestId);
        var $notice = isLicense ? $('#gkyc-request-response-notice') : $('#gkyc-recharge-response-notice');

        if (action === 'reject' && !confirm('¿Estás seguro de que quieres rechazar esta solicitud? Esta acción no se puede deshacer.')) {
            return;
        }

        $row.find('button').prop('disabled', true);
        $button.text('Procesando...');
        $notice.hide().removeClass('success error').text('');

        $.ajax({
            url: apiUrl,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            contentType: 'application/json',
            data: JSON.stringify({
                request_id: requestId,
                action: action
            }),
            success: function(response) {
                if (response.status === 'success') {
                    $notice.text(response.message).removeClass('error').addClass('success').show();
                    $row.fadeOut(500, function() {
                        $(this).remove();
                        if (isLicense && $('#gkyc-requests-tbody tr').length === 0) {
                            $('#gkyc-requests-tbody').html('<tr><td colspan="7" style="text-align: center; padding: 20px;">No tienes solicitudes de licencia pendientes.</td></tr>');
                        } else if (!isLicense && $row.closest('tbody').find('tr').length === 0) {
                            $row.closest('tbody').html('<tr><td colspan="6" style="text-align: center; padding: 20px;">No tienes solicitudes de recarga pendientes.</td></tr>');
                        }
                    });
                } else {
                    $notice.text('Info: ' + response.message).removeClass('error').addClass('success').show();
                    $row.find('button').prop('disabled', false);
                    $button.text(action === 'approve' ? 'Aprobar' : 'Rechazar');
                }
            },
            error: function(response) {
                var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Ocurrió un error.';
                $notice.text('Error: ' + errorMsg).removeClass('success').addClass('error').show();
                $row.find('button').prop('disabled', false);
                $button.text(action === 'approve' ? 'Aprobar' : 'Rechazar');
            }
        });
    });

    // --- INICIO: LÓGICA PARA GUARDAR AJUSTES DE PILOTO AUTOMÁTICO ---
    $('#gkyc-autopilot-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $button = $form.find('button[type="submit"]');
        var $notice = $('#gkyc-autopilot-form-notice');

        $button.prop('disabled', true).text('Guardando...');
        $notice.text('').hide();

        var formData = $form.serialize();

        $.ajax({
            url: gkyc_partner_obj.urls.update_autopilot,
            type: 'POST',
            beforeSend: function(xhr) { xhr.setRequestHeader('X-WP-Nonce', gkyc_partner_obj.nonce); },
            data: formData,
            success: function(response) { $notice.text(response.message || 'Ajustes guardados.').css('color', 'green').show(); },
            error: function(response) { var errorMsg = (response.responseJSON && response.responseJSON.message) ? response.responseJSON.message : 'Error.'; $notice.text('Error: ' + errorMsg).css('color', 'red').show(); },
            complete: function() { $button.prop('disabled', false).text('Guardar Reglas del Piloto Automático'); }
        });
    });
    // --- FIN: LÓGICA PARA GUARDAR AJUSTES DE PILOTO AUTOMÁTICO ---

});