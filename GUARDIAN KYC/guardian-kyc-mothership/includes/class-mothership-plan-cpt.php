<?php
/**
 * Maneja la creación y gestión del Custom Post Type 'Planes'.
 * v1.1 - Añadido el campo de costo para el admin.
 */
class Mothership_Plan_CPT {

    public function init() {
        add_action('init', array($this, 'register_cpt'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_plan_kyc', array($this, 'save_meta_data'));
    }

    public function register_cpt() {
        $labels = array(
            'name' => 'Planes de Servicio',
            'singular_name' => 'Plan',
            'add_new_item' => 'Añadir Nuevo Plan',
            'edit_item' => 'Editar Plan',
            'all_items' => 'Todos los Planes',
        );
        $args = array(
            'label' => 'Planes',
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_position' => 21,
            'menu_icon' => 'dashicons-tag',
            'supports' => array('title', 'page-attributes'),
            'rewrite' => false,
        );
        register_post_type('plan_kyc', $args);
    }

    public function add_meta_boxes() {
        add_meta_box(
            'kyc_plan_details_metabox',
            'Detalles del Plan',
            array($this, 'render_metabox_html'),
            'plan_kyc',
            'normal',
            'high'
        );
    }

    public function render_metabox_html($post) {
        wp_nonce_field('kyc_plan_data_nonce_action', 'kyc_plan_data_nonce');

        $slug = get_post_meta($post->ID, '_plan_slug', true);
        $price_text = get_post_meta($post->ID, '_price', true);
        $description = get_post_meta($post->ID, '_description', true);
        $admin_cost = get_post_meta($post->ID, '_admin_didit_cost', true);
        // === NUEVO CAMPO ===
        $license_price = get_post_meta($post->ID, '_license_purchase_price', true);
        ?>
        <table class="form-table">
            <tbody>
                <tr style="background-color: #e0f2f1;">
                    <th><label for="kyc_license_purchase_price">Precio de Compra de Licencia (USD)</label></th>
                    <td>
                        <input type="number" step="0.01" id="kyc_license_purchase_price" name="kyc_license_purchase_price" value="<?php echo esc_attr($license_price); ?>" placeholder="Ej: 49.00">
                        <p class="description"><strong>Este es el precio de venta inicial que aparecerá en WooCommerce.</strong></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="kyc_plan_slug">Slug del Plan</label></th>
                    <td>
                        <input type="text" id="kyc_plan_slug" name="kyc_plan_slug" value="<?php echo esc_attr($slug); ?>" class="regular-text">
                        <p class="description">Identificador único en minúsculas (ej: <code>plan_basico</code>). <strong>Importante que no se repita.</strong></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="kyc_price">Precio por Verificación (texto)</label></th>
                    <td>
                        <input type="text" id="kyc_price" name="kyc_price" value="<?php echo esc_attr($price_text); ?>" class="regular-text">
                         <p class="description">El texto descriptivo del costo de uso (ej: <code>$0.95 por verificación</code>).</p>
                    </td>
                </tr>

                <tr style="background-color: #f0f8ff;">
                    <th><label for="kyc_admin_didit_cost">Costo por Verificación (USD)</label></th>
                    <td>
                        <input type="number" step="0.001" id="kyc_admin_didit_cost" name="kyc_admin_didit_cost" value="<?php echo esc_attr($admin_cost); ?>" placeholder="Ej: 0.85">
                        <p class="description"><strong>Campo Privado:</strong> Lo que te cuesta a TI cada verificación. Es la base para calcular tu ganancia neta.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="kyc_description">Descripción</label></th>
                    <td>
                        <textarea id="kyc_description" name="kyc_description" rows="4" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                        <p class="description">La descripción de las características del plan.</p>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_meta_data($post_id) {
        if (!isset($_POST['kyc_plan_data_nonce']) || !wp_verify_nonce($_POST['kyc_plan_data_nonce'], 'kyc_plan_data_nonce_action')) {
            return;
        }

        // === GUARDADO DEL NUEVO CAMPO ===
        if (isset($_POST['kyc_license_purchase_price'])) {
            update_post_meta($post_id, '_license_purchase_price', sanitize_text_field($_POST['kyc_license_purchase_price']));
        }
        
        if (isset($_POST['kyc_description'])) {
            update_post_meta($post_id, '_description', sanitize_textarea_field($_POST['kyc_description']));
        }

        if (isset($_POST['kyc_price'])) {
            update_post_meta($post_id, '_price', sanitize_text_field($_POST['kyc_price']));
        }

        if (isset($_POST['kyc_plan_slug'])) {
            update_post_meta($post_id, '_plan_slug', sanitize_key($_POST['kyc_plan_slug']));
        }
        
        if (isset($_POST['kyc_admin_didit_cost'])) {
            $cost_value = filter_var($_POST['kyc_admin_didit_cost'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            update_post_meta($post_id, '_admin_didit_cost', $cost_value);
        }
    }
}