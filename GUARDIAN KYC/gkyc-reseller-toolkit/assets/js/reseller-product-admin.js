jQuery(document).ready(function($) {
    // Esta función se asegura de que el código solo se ejecute en la página de edición de productos
    if (!$('body.post-type-product').length) {
        return;
    }

    // Cuando el socio cambie la selección en el menú de la pestaña KYC...
    $('body').on('change', '#_gkyc_assigned_plan_slug', function() {
        // gkyc_reseller_admin_data es un objeto que pasaremos desde PHP
        const prices = gkyc_reseller_admin_data.plan_prices || {};
        const selectedSlug = $(this).val();
        const price = prices[selectedSlug];

        const regularPriceField = $('#_regular_price');

        if (price) {
            // Si encontramos un precio para el plan seleccionado, lo ponemos en el campo de "Precio normal"
            regularPriceField.val(price).trigger('change');
        }
    });
});