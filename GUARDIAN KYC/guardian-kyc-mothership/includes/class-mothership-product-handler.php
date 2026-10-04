<?php
/**
 * Maneja la integración con los productos de WooCommerce desde el Mothership.
 * Crea una pestaña de datos de producto para asignar planes KYC.
 */
class GKYC_Mothership_Product_Handler {

    public function init() {
        add_filter('woocommerce_product_data_tabs', array($this, 'add_kyc_product_tab'));
        add_action('woocommerce_product_data_panels', array($this, 'render_kyc_product_panel_html'));
        add_action('woocommerce_process_product_meta', array($this, 'save_kyc_product_panel_data'));
    }

    public function add_kyc_product_tab($tabs) {
        $tabs['guardian_kyc'] = array(
            'label'    => 'Guardián KYC',
            'target'   => 'guardian_kyc_product_data',
            'class'    => array('show_if_simple', 'show_if_external', 'show_if_grouped', 'show_if_variable'),
            'priority' => 80,
        );
        return $tabs;
    }

    public function render_kyc_product_panel_html() {
        global $post;

        echo '<div id="guardian_kyc_product_data" class="panel woocommerce_options_panel">';

        // --- Selector Principal ---
        echo '<div class="options_group">';
        woocommerce_wp_select([
            'id'      => '_gkyc_product_type',
            'label'   => 'Tipo de Producto KYC',
            'options' => [
                'none'     => 'Producto Normal de WooCommerce',
                'package'  => 'Paquete de Licencias (para socios existentes)',
                'new_partner' => 'Creación de Nueva Cuenta (Cliente o Socio)',
                'additional_license' => 'Licencia Adicional (para clientes existentes)',
            ],
            'value'   => get_post_meta($post->ID, '_gkyc_product_type', true),
            'desc_tip' => true,
            'description' => 'Elige qué acción realizará este producto al ser comprado.',
        ]);
        echo '</div>';

        // --- Opciones para "Paquete de Licencias" ---
        echo '<div class="gkyc_options_group gkyc_options_for_package">';
        $all_packages = get_posts([ 'post_type' => 'gkyc_license_pack', 'numberposts' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC' ]);
        $assigned_package_id = get_post_meta($post->ID, '_gkyc_assigned_package_id', true);
        $dropdown_options = ['' => '-- Seleccionar Paquete --'];
        $js_prices = [];
        if ($all_packages) {
            foreach ($all_packages as $package_post) {
                $price = get_post_meta($package_post->ID, '_package_price', true);
                $dropdown_options[$package_post->ID] = $package_post->post_title;
                $js_prices[$package_post->ID] = wc_format_decimal($price);
            }
        }
        woocommerce_wp_select([
            'id'          => '_gkyc_assigned_package_id',
            'label'       => 'Asignar Paquete de Licencia',
            'options'     => $dropdown_options,
            'value'       => $assigned_package_id,
        ]);
        echo '</div>';

        // --- Opciones para "Creación de Nueva Cuenta" ---
        echo '<div class="gkyc_options_group gkyc_options_for_new_partner">';
        woocommerce_wp_text_input([
            'id'          => '_gkyc_initial_license_limit',
            'label'       => 'Límite de Licencias Inicial',
            'placeholder' => 'Ej: 20',
            'desc_tip'    => 'true',
            'description' => 'Si este producto es para un nuevo Socio, define cuántas licencias tendrá al empezar.',
            'type'        => 'number',
            'custom_attributes' => ['step' => '1', 'min' => '0'],
            'value'       => get_post_meta($post->ID, '_gkyc_initial_license_limit', true),
        ]);
        woocommerce_wp_text_input([
            'id'          => '_gkyc_gift_balance',
            'label'       => 'Saldo de Regalo (USD)',
            'placeholder' => 'Ej: 50.00',
            'desc_tip'    => 'true',
            'description' => 'Monto de saldo que se regalará con la compra inicial de esta cuenta.',
            'type'        => 'number',
            'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            'value'       => get_post_meta($post->ID, '_gkyc_gift_balance', true),
        ]);
        echo '</div>';

        echo '</div>'; // Cierre del panel

        // Script para la lógica de mostrar/ocultar
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                function toggle_gkyc_fields(){
                    var type = $('#_gkyc_product_type').val();
                    $('.gkyc_options_group').hide();
                    if (type === 'package') {
                        $('.gkyc_options_for_package').show();
                    } else if (type === 'new_partner') {
                        $('.gkyc_options_for_new_partner').show();
                    }
                }
                toggle_gkyc_fields();
                $('#_gkyc_product_type').on('change', toggle_gkyc_fields);

                // Lógica para auto-rellenar el precio desde el paquete
                $('#_gkyc_assigned_package_id').on('change', function() {
                    var prices = <?php echo json_encode($js_prices); ?>;
                    var selected_id = $(this).val();
                    var price = prices[selected_id];
                    if (price) {
                        $('#_regular_price').val(price).trigger('change');
                    }
                });
            });
        </script>
        <?php
    }

    public function save_kyc_product_panel_data($post_id) {
        if (isset($_POST['_gkyc_product_type'])) {
            update_post_meta($post_id, '_gkyc_product_type', sanitize_text_field($_POST['_gkyc_product_type']));
        }
        if (isset($_POST['_gkyc_assigned_package_id'])) {
            update_post_meta($post_id, '_gkyc_assigned_package_id', sanitize_text_field($_POST['_gkyc_assigned_package_id']));
        }
        if (isset($_POST['_gkyc_initial_license_limit'])) {
            update_post_meta($post_id, '_gkyc_initial_license_limit', sanitize_text_field($_POST['_gkyc_initial_license_limit']));
        }
        if (isset($_POST['_gkyc_gift_balance'])) {
            update_post_meta($post_id, '_gkyc_gift_balance', sanitize_text_field($_POST['_gkyc_gift_balance']));
        }
    }

    // --- FIN DE LA MODIFICACIÓN ---
}