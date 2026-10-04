jQuery(document).ready(function($) {
    'use strict';

    var slider = $('#gkyc-recharge-slider-auto');
    if (!slider.length) { return; }

    var displayAmount = $('#gkyc-display-monto-auto');
    var buyButton = $('#gkyc-buy-balance-btn-auto');
    var emailInput = $('#gkyc-customer-email-auto');
    var resultDiv = $('#gkyc-plan-detector-result-auto');
    var verificationsDiv = $('#gkyc-display-verifications-auto');
    var pricePerVerification = 0;

    // Función para actualizar los textos y el enlace del botón
    function updateCalculator() {
        var amount = parseInt(slider.val());
        displayAmount.text('$' + amount.toFixed(2));

        if (pricePerVerification > 0) {
            var verifications = Math.floor(amount / pricePerVerification);
            verificationsDiv.html('Obtendrás aproximadamente <strong>' + verifications + '</strong> verificaciones.');
        } else {
            verificationsDiv.text('Ingresa tu email para calcular las verificaciones.');
        }

        var checkoutUrl = new URL(gkycCalculatorData.checkout_url);
        checkoutUrl.searchParams.set('add-to-cart', gkycCalculatorData.product_id);
        checkoutUrl.searchParams.set('quantity', amount);
        // --- INICIO DE LA MODIFICACIÓN ---
        var email = emailInput.val();
        if (email) {
            checkoutUrl.searchParams.set('customer_email', email);
        }
        // --- FIN DE LA MODIFICACIÓN ---

        // --- LÓGICA DE REDIRECCIÓN INTELIGENTE ---
        // Buscamos el return_url en la página actual y lo añadimos al enlace de compra
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('return_url')) {
            checkoutUrl.searchParams.set('return_url', urlParams.get('return_url'));
        }
        
        buyButton.attr('href', checkoutUrl.href);
    }

    // Función para buscar el plan del cliente
    function fetchClientPlan() {
        var email = emailInput.val();
        if (!email) return;

        resultDiv.text('Buscando tu plan...').removeClass('success error');
        pricePerVerification = 0;
        updateCalculator();

        $.ajax({
            url: gkycCalculatorData.mothership_url,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                email: email,
                partner_api_key: gkycCalculatorData.partner_api_key
            }),
            success: function(response) {
                if (response.status === 'success') {
                    pricePerVerification = parseFloat(response.price_per_verification);
                    var priceText = pricePerVerification.toFixed(2);
                    resultDiv.html('Plan Detectado: <strong>' + response.plan_name + '</strong> ($' + priceText + ' / verificación)').addClass('success');
                    updateCalculator();
                } else {
                    resultDiv.text(response.message || 'Cliente no encontrado.').addClass('error');
                }
            },
            error: function(jqXHR) {
                var errorMsg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Error de comunicación.';
                resultDiv.text('Error: ' + errorMsg).addClass('error');
            }
        });
    }

    // --- LÓGICA DE AUTO-RELLENO DE EMAIL ---
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('customer_email')) {
        var customerEmail = urlParams.get('customer_email');
        emailInput.val(customerEmail);
        fetchClientPlan(); // Si el email viene en la URL, busca el plan de inmediato
    }
    
    // Eventos
    emailInput.on('blur', fetchClientPlan);
    slider.on('input', updateCalculator);
    updateCalculator(); // Inicializar
});