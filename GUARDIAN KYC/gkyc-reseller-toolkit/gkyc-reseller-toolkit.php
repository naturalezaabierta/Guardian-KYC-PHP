<?php
/**
 * Plugin Name:       Kit de Herramientas de Reventa KYC
 * Plugin URI:        https://guardiankyc.com
 * Description:       Permite a los socios de Guardián KYC mostrar y vender planes de servicio directamente en su sitio web.
 * Version:           3.5.0 (Versión Estable Unificada)
 * Author:            Guardián KYC
 * Author URI:        https://guardiankyc.com
 * License:           GPL v2 or later
 * Text Domain:       gkyc-reseller-toolkit
 */

if (!defined('WPINC')) { die; }

define('GKYC_RESELLER_URL', plugin_dir_url(__FILE__));
define('GKYC_RESELLER_PATH', plugin_dir_path(__FILE__));

// --- Inicialización del Plugin ---
final class GKYC_Reseller_Toolkit {
    private static $instance = null;
    public static function get_instance() { if (null === self::$instance) { self::$instance = new self(); } return self::$instance; }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once GKYC_RESELLER_PATH . 'includes/class-reseller-settings-page.php';
        require_once GKYC_RESELLER_PATH . 'includes/class-reseller-shortcodes.php';
    }

    private function init_hooks() {
        $settings_page = new GKYC_Reseller_Settings_Page();
        $settings_page->init();
        $shortcode_handler = new GKYC_Reseller_Shortcodes();
        $shortcode_handler->init();
        add_action('template_redirect', array($this, 'handle_plan_selection_token'));
    }
    
    public function handle_plan_selection_token() {
        if (isset($_GET['gkyc_select_plan']) && isset($_GET['checkout_page'])) {
            $plan_slug = sanitize_text_field($_GET['gkyc_select_plan']);
            $checkout_page = sanitize_text_field($_GET['checkout_page']);
            $token = 'gkyc_t_' . wp_generate_password(24, false);
            set_transient($token, ['plan_slug' => $plan_slug, 'time' => time()], 5 * MINUTE_IN_SECONDS);
            $checkout_url = add_query_arg('ticket', $token, site_url($checkout_page));
            wp_redirect(esc_url_raw($checkout_url));
            exit;
        }
    }
    
} // <-- ¡ESTA ES LA LLAVE IMPORTANTE! Aquí se cierra la clase GKYC_Reseller_Toolkit.


// --- ESTAS LÍNEAS DEBEN IR FUERA DE LA CLASE ---

function gkyc_reseller_toolkit_run() {
    return GKYC_Reseller_Toolkit::get_instance();
}
gkyc_reseller_toolkit_run();


// --- Lógica ÚNICA de Instalación y Activación ---
register_activation_hook( __FILE__, 'gkyc_reseller_install_and_setup' );
function gkyc_reseller_install_and_setup() {
    // Creación de Páginas
    $pages_to_create = [
        'planes-y-precios' => ['title' => 'Planes y Precios', 'content' => '[gkyc_planes_venta pagina_checkout="/procesar-licencia"]'],
        'procesar-licencia' => ['title' => 'Procesar Licencia', 'content' => '[gkyc_checkout_pago]'],
        'comprar-saldo' => ['title' => 'Comprar Saldo', 'content' => '[gkyc_calculadora_saldo_automatica]'],
    ];
    foreach ($pages_to_create as $slug => $page) {
        if (!get_page_by_path($slug)) {
            wp_insert_post(['post_title' => $page['title'], 'post_name' => $slug, 'post_content' => $page['content'], 'post_status' => 'publish', 'post_type' => 'page']);
        }
    }
    
    // Creación de Producto de Saldo en WooCommerce
    if ( class_exists('WooCommerce') ) {
        $args = ['post_type' => 'product', 'posts_per_page' => 1, 'meta_query' => [['key' => '_is_gkyc_balance_product', 'value' => 'yes']]];
        $existing_products = new WP_Query($args);
        if ( !$existing_products->have_posts() ) {
            $product = new WC_Product_Simple();
            $product->set_name( 'Recarga de Saldo (KYC)' );
            $product->set_slug( 'recarga-saldo-kyc-wc' );
            $product->set_status( 'publish' );
            $product->set_virtual( true );
            $product->set_catalog_visibility( 'hidden' );
            $product->set_regular_price( '1' );
            $product_id = $product->save();
            if ( $product_id ) {
                update_post_meta( $product_id, '_gkyc_product_type', 'balance' );
                update_post_meta( $product_id, '_is_gkyc_balance_product', 'yes' );
            }
        }
        wp_reset_postdata();
    }
}


// ===================================================================
// ===== LÓGICA DE INTEGRACIÓN CON E-COMMERCE (MOTOR ÚNICO Y CORRECTO) =====
// ===================================================================
add_action( 'plugins_loaded', 'gkyc_reseller_initialize_ecommerce_integration' );

// --- INICIO: CÓDIGO NUEVO PARA CARGAR SCRIPTS DE ADMIN ---

/**
 * Carga los scripts necesarios para la página de edición de productos.
 */
add_action('admin_enqueue_scripts', 'gkyc_reseller_admin_scripts');
function gkyc_reseller_admin_scripts($hook) {
    // Nos aseguramos de que solo se cargue en la página de edición de un producto
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }
    global $post;
    if ('product' !== $post->post_type) {
        return;
    }

    // Cargamos nuestro nuevo archivo JavaScript
    wp_enqueue_script(
        'gkyc-reseller-product-admin-js',
        GKYC_RESELLER_URL . 'assets/js/reseller-product-admin.js',
        ['jquery'],
        '1.0.0',
        true
    );
}

// --- FIN: CÓDIGO NUEVO ---
function gkyc_reseller_initialize_ecommerce_integration() {
    // --- Integración Única con WooCommerce ---
    if ( class_exists('WooCommerce') ) {
        add_filter('woocommerce_product_data_tabs', 'gkyc_reseller_wc_add_product_tab');
        add_action('woocommerce_product_data_panels', 'gkyc_reseller_wc_render_product_panel');
        add_action('woocommerce_process_product_meta', 'gkyc_reseller_wc_save_product_panel_data');
        add_action('woocommerce_order_status_completed', 'gkyc_reseller_handle_license_sale', 10, 2);
        add_action('woocommerce_order_status_completed', 'gkyc_reseller_handle_balance_sale', 10, 1);
        add_filter('woocommerce_add_cart_item_data', 'gkyc_reseller_save_return_url_to_cart', 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', 'gkyc_reseller_add_return_url_to_order_item', 10, 4);
        add_action('woocommerce_thankyou', 'gkyc_reseller_thankyou_page_redirect', 10, 1);
    }
}

// --- Pestaña de Producto de WooCommerce (WC) ---
function gkyc_reseller_wc_add_product_tab($tabs) {
    $tabs['guardian_kyc'] = ['label' => 'Guardián KYC', 'target' => 'gkyc_reseller_product_data', 'class' => ['show_if_simple'], 'priority' => 21];
    return $tabs;
}
// --- INICIO: REEMPLAZO DE gkyc_reseller_wc_render_product_panel ---

function gkyc_reseller_wc_render_product_panel() {
    global $post;
    $partner_data = gkyc_reseller_fetch_partner_data();

    echo '<div id="gkyc_reseller_product_data" class="panel woocommerce_options_panel">';

    if (!is_wp_error($partner_data)) {
        echo '<div style="padding: 10px; margin: 0 12px; background: #f6f7f7; border: 1px solid #ddd; text-align: center;"><strong>Saldo Maestro Disponible: <span style="color: #007cba; font-size: 1.2em;">$' . esc_html(number_format($partner_data['master_balance'], 2)) . '</span></strong></div>';
    }

    echo '<div class="options_group">';
    woocommerce_wp_select(['id' => '_gkyc_product_type', 'label' => 'Tipo de Producto KYC', 'options' => ['none' => 'Producto Normal', 'license' => 'Venta de Licencia KYC', 'balance' => 'Venta de Saldo para Recarga'], 'value' => get_post_meta($post->ID, '_gkyc_product_type', true)]);
    echo '</div>';

    echo '<div class="options_group gkyc-product-options-license" style="display:none;">';
    if (!is_wp_error($partner_data)) {
        $dropdown_options = ['' => '-- Seleccionar Plan --'];
        $js_prices = []; // Array para pasar los precios a JavaScript

        if (!empty($partner_data['plans'])) { 
            foreach ($partner_data['plans'] as $plan) { 
                // CORRECCIÓN VISUAL: Añadimos el precio al nombre del plan en el menú
                $dropdown_options[$plan['slug']] = $plan['name'] . ' ($' . number_format((float)$plan['price'], 2) . ')';
                // Guardamos el precio en un formato limpio para el JavaScript
                $js_prices[$plan['slug']] = wc_format_decimal($plan['price']);
            } 
        }
        woocommerce_wp_select(['id' => '_gkyc_assigned_plan_slug', 'label' => 'Asignar Plan de Licencia', 'options' => $dropdown_options, 'value' => get_post_meta($post->ID, '_gkyc_assigned_plan_slug', true)]);

        // Pasamos los datos al script que creamos en el Paso 1
        wp_localize_script('gkyc-reseller-product-admin-js', 'gkyc_reseller_admin_data', [
            'plan_prices' => $js_prices
        ]);
    }
    echo '</div>';

    echo '</div>';
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            function toggle_gkyc_fields(){ 
                if ( $('#_gkyc_product_type').val() === 'license' ) { 
                    $('.gkyc-product-options-license').show(); 
                } else { 
                    $('.gkyc-product-options-license').hide(); 
                } 
            }
            toggle_gkyc_fields();
            $('#_gkyc_product_type').on('change', toggle_gkyc_fields);
        });
    </script>
    <?php
}

// --- FIN: REEMPLAZO DE gkyc_reseller_wc_render_product_panel ---
function gkyc_reseller_wc_save_product_panel_data($post_id) {
    if (isset($_POST['_gkyc_product_type'])) {
        $product_type = sanitize_text_field($_POST['_gkyc_product_type']);
        update_post_meta($post_id, '_gkyc_product_type', $product_type);
        update_post_meta($post_id, '_is_gkyc_balance_product', $product_type === 'balance' ? 'yes' : 'no');
    }
    if (isset($_POST['_gkyc_assigned_plan_slug'])) {
        update_post_meta($post_id, '_gkyc_assigned_plan_slug', sanitize_text_field($_POST['_gkyc_assigned_plan_slug']));
    }
}

// --- "Oyentes" de WooCommerce ---
function gkyc_reseller_handle_license_sale($order_id, $order) {
    if (!$order) { $order = wc_get_order($order_id); }
    $options = get_option('gkyc_reseller_options');
    $partner_api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
    if (empty($partner_api_key)) { $order->add_order_note('GKYC Reseller ERROR: API Key del socio no configurada.'); return; }

    foreach ($order->get_items() as $item) {
        if (get_post_meta($item->get_product_id(), '_gkyc_product_type', true) === 'license') {
            $payload = [
                'partner_api_key'     => $partner_api_key,
                'plan_slug'           => get_post_meta($item->get_product_id(), '_gkyc_assigned_plan_slug', true),
                'customer_name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'customer_email'      => $order->get_billing_email(),
                'customer_phone'      => $order->get_billing_phone(),
                'order_id'            => $order_id, // Añadimos el ID de la orden
            ];
            $response = wp_remote_post('https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/create-license-from-resale', ['method' => 'POST', 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($payload), 'timeout' => 30]);
            
            if (is_wp_error($response)) { $order->add_order_note('GKYC Reseller ERROR (Licencia): Falla de conexión. ' . $response->get_error_message()); } 
            else {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ($response_code >= 300) { $order->add_order_note('GKYC Reseller ERROR (Licencia): ' . ($body['message'] ?? 'Error desconocido del Mothership.')); } 
                else { $order->add_order_note('GKYC Reseller ÉXITO: Licencia creada para ' . esc_html($payload['customer_email'])); }
            }
        }
    }
}
function gkyc_reseller_handle_balance_sale($order_id) {
    $order = wc_get_order($order_id);
    if ( !$order ) { return; }
    $options = get_option('gkyc_reseller_options');
    $partner_api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
    if (empty($partner_api_key)) { $order->add_order_note('GKYC Reseller ERROR: API Key del socio no configurada.'); return; }

    foreach ($order->get_items() as $item) {
        if (get_post_meta($item->get_product_id(), '_is_gkyc_balance_product', true) === 'yes') {
            $payload = ['partner_api_key'  => $partner_api_key, 'sub_client_email' => $order->get_billing_email(), 'amount_paid' => (float) $item->get_total()];
            $response = wp_remote_post('https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/process-balance-sale', ['method' => 'POST', 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode($payload), 'timeout' => 30]);
            
            if (is_wp_error($response)) { $order->add_order_note('GKYC Reseller ERROR (Saldo): Falla de conexión. ' . $response->get_error_message()); } 
            else {
                $response_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if ($response_code >= 300) { $order->add_order_note('GKYC Reseller ERROR (Saldo): ' . ($body['message'] ?? 'Error desconocido del Mothership.')); }
                elseif (isset($body['status']) && $body['status'] === 'success_pending_fulfillment') { $order->add_order_note('GKYC Reseller AVISO: Venta de saldo registrada, pero PENDIENTE por fondos insuficientes del socio.'); } 
                else { $order->add_order_note(sprintf('GKYC Reseller ÉXITO: Se acreditaron $%s de saldo al cliente %s.', number_format((float)$item->get_total(), 2), esc_html($order->get_billing_email()))); }
            }
        }
    }
}

// --- Lógica de Redirección (Común para WC) ---
function gkyc_reseller_save_return_url_to_cart($cart_item_data, $product_id) {
    if (isset($_REQUEST['return_url'])) { $cart_item_data['gkyc_return_url'] = esc_url_raw($_REQUEST['return_url']); }
    return $cart_item_data;
}
function gkyc_reseller_add_return_url_to_order_item($item, $cart_item_key, $values, $order) {
    if (!empty($values['gkyc_return_url'])) { $item->add_meta_data('_gkyc_return_url', $values['gkyc_return_url']); }
}
/**
 * En la página de "Gracias" de WooCommerce, busca el return_url en el pedido
 * y, si existe, ejecuta la redirección inteligente.
 */
function gkyc_reseller_thankyou_page_redirect( $order_id ) {
    if ( ! $order_id ) { return; }

    $order = wc_get_order( $order_id );
    if ( !$order ) { return; }
    
    $return_url = '';

    // Buscamos en cada producto del pedido si alguno tiene nuestra URL de retorno.
    foreach ( $order->get_items() as $item ) {
        $item_return_url = $item->get_meta( '_gkyc_return_url' );
        if ( ! empty( $item_return_url ) ) {
            $return_url = $item_return_url;
            break;
        }
    }

    // Si encontramos una URL, mostramos un mensaje y el script de redirección.
    if ( ! empty( $return_url ) ) {
        ?>
        <div class="gkyc-redirect-notice" style="padding: 20px; text-align: center; border: 2px solid #28a745; border-radius: 8px; margin: 2em 0;">
            <h2 style="margin-top: 0;">¡Pago completado con éxito!</h2>
            <p>Tu saldo ha sido actualizado. Serás redirigido de vuelta a tu panel en <span id="gkyc-redirect-countdown">5</span> segundos...</p>
            <p>Si no eres redirigido, <a href="<?php echo esc_url( $return_url ); ?>">haz clic aquí</a>.</p>
        </div>
        <script type="text/javascript">
            (function() {
                var seconds = 5;
                var countdown = document.getElementById('gkyc-redirect-countdown');
                var redirectUrl = '<?php echo esc_js( $return_url ); ?>';
                var interval = setInterval(function() {
                    seconds--;
                    if (countdown) { countdown.textContent = seconds; }
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = redirectUrl;
                    }
                }, 1000);
            })();
        </script>
        <?php
    }
}

function gkyc_reseller_fetch_partner_data() {
    $options = get_option('gkyc_reseller_options');
    $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
    if (empty($api_key)) { return new WP_Error('no_api_key', 'API Key no configurada.'); }
    
    $cache_key = 'gkyc_reseller_data_' . md5($api_key);
    $cached_data = get_transient($cache_key);
    if (false !== $cached_data) { return $cached_data; }

    // --- INICIO DE LA MEJORA ---
    $response = wp_remote_post('https://guardiankyc.com/wp-json/guardian-kyc/v1/reseller/get-plans', [
        'method'  => 'POST',
        'timeout' => 7, // Le damos un máximo de 7 segundos para responder
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode(['api_key' => $api_key])
    ]);
    // --- FIN DE LA MEJORA ---

    if (is_wp_error($response)) { 
        return new WP_Error('connection_error', 'Error de conexión con el servidor principal. ' . $response->get_error_message()); 
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code !== 200 || !isset($data['status']) || $data['status'] !== 'success') {
        return new WP_Error('api_error', isset($data['message']) ? $data['message'] : 'Respuesta inválida del servidor principal.');
    }

    set_transient($cache_key, $data, HOUR_IN_SECONDS);
    return $data;
}

/**
 * Función "Detective" que identifica la plataforma de e-commerce activa.
 * Devuelve 'woocommerce', 'edd', o false.
 */
function gkyc_reseller_get_active_ecommerce_platform() {
    if ( class_exists('WooCommerce') ) {
        return 'woocommerce';
    }
    if ( class_exists('Easy_Digital_Downloads') ) {
        return 'edd';
    }
    return false;
}



