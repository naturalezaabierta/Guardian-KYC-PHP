jQuery(document).ready(function($) {
    'use strict';

    // --- LÓGICA PARA LA CALCULADORA DE RECARGA DE SALDO ---
    var calculator = $('.gkyc-recharge-calculator');
    if (calculator.length) {

        var pricePerVerification = 0;
        var emailInput = $('#gkyc-customer-email');
        var slider = $('#gkyc-recharge-slider');
        var displayMonto = $('#gkyc-display-monto');
        var displayVerifications = $('#gkyc-display-verifications');
        var resultDiv = $('#gkyc-plan-detector-result');

        // Función para actualizar los textos en pantalla Y el enlace de PayPal
        function updateDisplay() {
            var amount = parseInt(slider.val());
            displayMonto.text('$' + amount.toFixed(2));

            if (pricePerVerification > 0) {
                var verifications = Math.floor(amount / pricePerVerification);
                displayVerifications.html('Obtendrás aproximadamente <strong>' + verifications + '</strong> verificaciones.');
            } else {
                displayVerifications.text('Ingresa tu email para calcular las verificaciones.');
            }

            // ===== LÓGICA CLAVE PARA EL BOTÓN DINÁMICO =====
            var paypalButton = $('#gkyc-paypal-recharge-btn');
            if (paypalButton.length) {
                var baseUrl = paypalButton.data('base-url');
                var newUrl = baseUrl + '/' + amount; // Creamos la URL con el monto del slider
                paypalButton.attr('href', newUrl); // Actualizamos el enlace del botón en tiempo real
            }
            // ===== FIN DE LA LÓGICA CLAVE =====
        }

        // Evento que se dispara cuando el usuario escribe su email y sale del campo
        emailInput.on('blur', function() {
            var email = $(this).val();
            if (!email) return;

            resultDiv.text('Buscando tu plan...').removeClass('success error');
            pricePerVerification = 0; // Reseteamos el precio para un nuevo cálculo
            updateDisplay();

            // Llamada AJAX al Mothership para obtener el precio del cliente
            $.ajax({
                url: gkyc_reseller_obj.mothership_url,
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    email: email,
                    partner_api_key: gkyc_reseller_obj.api_key
                }),
                success: function(response) {
                    if (response.status === 'success') {
                        pricePerVerification = parseFloat(response.price_per_verification);

                        // MEJORA #2: MOSTRAR PLAN Y PRECIO POR VERIFICACIÓN
                        var priceText = pricePerVerification.toFixed(2);
                        resultDiv.html('Plan Detectado: <strong>' + response.plan_name + '</strong> ($' + priceText + ' / verificación)').addClass('success').removeClass('error');

                        updateDisplay(); // Actualizamos la pantalla con el nuevo precio
                    } else {
                        // Esto se usa si el servidor responde con éxito pero con un mensaje de error interno
                        resultDiv.text(response.message || 'Cliente no encontrado.').addClass('error').removeClass('success');
                    }
                },
                // MEJORA #3: MANEJAR ERRORES ESPECÍFICOS DEL SERVIDOR
                error: function(jqXHR) {
                    // Esta lógica ahora puede leer el mensaje de error que envía WordPress (ej: "Cliente no encontrado")
                    var errorMsg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Error de comunicación con el servidor.';
                    resultDiv.text('Error: ' + errorMsg).addClass('error').removeClass('success');
                }
            });
        });

        // Evento que se dispara cada vez que el usuario mueve el slider
        slider.on('input', updateDisplay);

        // Llamamos a la función una vez al cargar la página para inicializar los textos
        updateDisplay();
    }
    
    // --- LÓGICA PARA LA PÁGINA DE CHECKOUT (formulario de activación) ---
    var checkoutWrapper = $('.gkyc-checkout-wrapper');
    if (checkoutWrapper.length && checkoutWrapper.data('endpoint')) {
        
        // Lógica del formulario de activación
        $('#gkyc-activation-form').on('submit', function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $button = $form.find('#gkyc-submit-activation');
            var $responseDiv = $('#gkyc-form-response');
            var mainContent = $('#gkyc-main-content-container');

            $button.prop('disabled', true).text('Procesando activación...');
            $responseDiv.hide().removeClass('success error');

            var customerData = {
                partner_api_key: checkoutWrapper.data('api-key'),
                plan_slug:       checkoutWrapper.data('plan-slug'),
                customer_name:   $('#gkyc-customer-name').val(),
                customer_email:  $('#gkyc-customer-email').val(),
                customer_phone:  $('#gkyc-customer-phone').val(),
                customer_web:    $('#gkyc-customer-web').val(),
                usdt_hash:       $('#gkyc-usdt-hash').val(),
                transaction_id:  $('#gkyc-transaction-id').val(),
                token:           checkoutWrapper.data('token')
            };

            $.ajax({
                url: checkoutWrapper.data('endpoint'),
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(customerData),
                success: function(response) {
                    mainContent.hide();
                    $responseDiv.addClass('success')
                        .html('<h3>¡Solicitud Recibida!</h3><p>Hemos enviado tu solicitud de activación al socio. Recibirás un correo electrónico con los detalles de tu licencia tan pronto como el socio confirme tu pago. ¡Gracias por tu compra!</p>')
                        .show();
                },
                error: function(jqXHR) {
                    var errorMsg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Ocurrió un error desconocido. Por favor, contacta a soporte.';
                    $responseDiv.addClass('error')
                        .html('<strong>Error:</strong> ' + errorMsg)
                        .show();
                    $button.prop('disabled', false).text('Activar mi Licencia Ahora');
                }
            });
        });

        // --- INICIO DEL NUEVO CÓDIGO ---

        // Lógica para auto-rellenar y bloquear el email en la página de pago manual
        var activationForm = $('#gkyc-activation-form');
        if (activationForm.length) {
            var urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('customer_email')) {
                var customerEmail = urlParams.get('customer_email');
                var emailField = $('#gkyc-customer-email');

                if (customerEmail) {
                    emailField.val(customerEmail);
                    emailField.prop('readonly', true); // Bloqueamos el campo

                    // Opcional: Añadir un pequeño texto informativo
                    emailField.after('<p class="description" style="font-size: 12px; color: #555;">Email verificado desde la calculadora.</p>');
                }
            }
        }

        // --- FIN DEL NUEVO CÓDIGO ---

        // Lógica del reloj de cuenta regresiva
        var timerContainer = $('.gkyc-countdown-timer');
        if (timerContainer.length) {
            var creationTime = parseInt(timerContainer.data('creation-time'), 10);
            var display = timerContainer.find('#gkyc-timer-display');
            var mainContent = $('#gkyc-main-content-container');
            var plansPageUrl = gkyc_reseller_obj.plans_page_url;

            var intervalId = setInterval(function () {
                var now = Math.floor(Date.now() / 1000);
                var elapsed = now - creationTime;
                var remaining = (60 * 5) - elapsed;

                if (remaining >= 0) {
                    var minutes = Math.floor(remaining / 60);
                    var seconds = remaining % 60;
                    minutes = minutes < 10 ? "0" + minutes : minutes;
                    seconds = seconds < 10 ? "0" + seconds : seconds;
                    display.text(minutes + ":" + seconds);
                } else {
                    clearInterval(intervalId);
                    mainContent.html('<div class="gkyc-reseller-notice error" style="text-align:center;">Tu sesión ha expirado. Por favor, <a href="' + plansPageUrl + '">vuelve a la página de planes</a> para iniciar de nuevo.</div>');
                }
            }, 1000);
        }
    }

    // --- LÓGICA PARA EL NUEVO FORMULARIO DE CONFIRMACIÓN DE RECARGA ---
    $('#gkyc-recharge-confirmation-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('#gkyc-submit-recharge');
        var $responseDiv = $('#gkyc-recharge-form-response');
        
        $button.prop('disabled', true).text('Enviando...');
        $responseDiv.hide().removeClass('success error').text('');

        // Usamos FormData para poder enviar archivos
        var formData = new FormData();
        
        // Añadimos los datos del formulario al objeto FormData
        formData.append('partner_api_key', gkyc_reseller_obj.api_key);
        formData.append('customer_email', $('#gkyc-customer-email').val());
        formData.append('monto', parseInt($('#gkyc-recharge-slider').val()).toFixed(2));
        formData.append('customer_name', $('#gkyc-customer-name').val());
        formData.append('transaction_id', $('#gkyc-transaction-id').val());
        formData.append('usdt_hash', $('#gkyc-usdt-hash').val());
        
        // Añadimos el archivo si el usuario seleccionó uno
        var file_data = $('#gkyc-comprobante').prop('files')[0];
        if(file_data){
            formData.append('comprobante', file_data);
        }

        $.ajax({
            url: 'https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/submit-recharge-request',
            type: 'POST',
            data: formData,
            contentType: false, // Necesario para FormData
            processData: false, // Necesario para FormData
            success: function(response) {
                $form.hide();
                $responseDiv.addClass('success')
                    .html('<h3>¡Solicitud de Recarga Enviada!</h3><p>Hemos recibido tu confirmación. Tu saldo será acreditado tan pronto como el socio verifique tu pago. ¡Gracias!</p>')
                    .show();
            },
            error: function(jqXHR) {
                var errorMsg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Ocurrió un error desconocido.';
                $responseDiv.addClass('error').html('<strong>Error:</strong> ' + errorMsg).show();
                $button.prop('disabled', false).text('Confirmar Mi Recarga');
            }
        });
    });
});

