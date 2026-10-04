jQuery(document).ready(function($) {
    if (!$('body.post-type-product').length) {
        return;
    }

    // Obtenemos el ID del campo a observar desde los datos localizados
    const fieldId = window.gkyc_mothership_product_data.field_id || '_gkyc_assigned_package_id';

    $('body').on('change', '#' + fieldId, function() {
        const prices = window.gkyc_mothership_product_data.plan_prices || {};
        const selectedValue = $(this).val(); // Ahora es el ID del paquete
        const price = prices[selectedValue];

        const regularPriceField = $('#_regular_price');

        if (price) {
            regularPriceField.val(price);
            regularPriceField.trigger('change');
        } else {
            regularPriceField.val('').trigger('change');
        }
    });
});