<?php
/**
 * Plugin Name:       Guardián KYC - Mothership
 * Plugin URI:        https://guardiankyc.com
 * Description:       El motor central para la gestión de clientes, API y saldo de Guardián KYC.
 * Version:           1.5.0 (ARCH FIXED)
 * Author:            Guardián KYC
 * Author URI:        https://guardiankyc.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       guardian-kyc-mothership
 */

if ( ! defined( 'WPINC' ) ) { die; }

define( 'MOTHERSHIP_URL', plugin_dir_url( __FILE__ ) );
define( 'MOTHERSHIP_PATH', plugin_dir_path( __FILE__ ) );

final class Guardian_KYC_Mothership {

    private static $instance = null;
    
    /**
     * @var Mothership_Settings_Page
     *
     * La instancia única de nuestro manejador de la página de ajustes.
     */
    public $settings_page_handler;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once MOTHERSHIP_PATH . 'includes/class-mothership-api-handler.php';
        require_once MOTHERSHIP_PATH . 'includes/class-mothership-client-cpt.php';
        require_once MOTHERSHIP_PATH . 'includes/class-mothership-plan-cpt.php';
        require_once MOTHERSHIP_PATH . 'includes/class-mothership-settings-page.php';
        require_once MOTHERSHIP_PATH . 'includes/class-mothership-product-handler.php';
    }

    // =======================================================
    // ===== INICIO: PEGA ESTE BLOQUE DE CÓDIGO FALTANTE =====
    // =======================================================
    private function init_hooks() {
        // API Handler
        $api_handler = new Mothership_Api_Handler();
        add_action( 'rest_api_init', array( $api_handler, 'register_routes' ) );

        // CPT Handlers
        $client_cpt_handler = new Mothership_Client_CPT();
        $client_cpt_handler->init();
        $plan_cpt_handler = new Mothership_Plan_CPT();
        $plan_cpt_handler->init();
        
        // Manejador de la página de ajustes
        $this->settings_page_handler = new Mothership_Settings_Page();
        $this->settings_page_handler->init();
        
        // Hooks para los menús y scripts del admin
        add_action('admin_menu', array($this, 'create_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'gkyc_enqueue_dashboard_scripts'));
        
        // Hooks globales y shortcodes
       
        add_shortcode('gkyc_dato', 'gkyc_get_client_data_shortcode');
        add_shortcode('gkyc_calculadora', 'gkyc_render_recharge_calculator_shortcode');
        add_shortcode('gkyc_partner_dashboard', 'gkyc_render_partner_dashboard');
        // --- INICIO DE LA CORRECCIÓN DE CARGA ---
        add_action('plugins_loaded', array($this, 'initialize_woocommerce_handler'));
        // --- FIN DE LA CORRECCIÓN DE CARGA ---
    }
    // =====================================================
    // ===== FIN: PEGA ESTE BLOQUE DE CÓDIGO FALTANTE =====
    // =====================================================

    /**
     * Crea todas las páginas de menú del plugin (Versión Definitiva y Estable).
     * Toma control total sobre el orden de los submenús para evitar conflictos.
     */
    public function create_admin_menus() {
        // 1. CREAMOS EL MENÚ PRINCIPAL
        add_menu_page(
            'Panel de Negocio',
            'Panel de Negocio',
            'manage_options',
            'gkyc_business_dashboard',
            'gkyc_mothership_render_dashboard_page',
            'dashicons-chart-line',
            22
        );

        // 2. AÑADIMOS EL DASHBOARD COMO SU PROPIO PRIMER SUBMENÚ (CORRECCIÓN CLAVE)
        add_submenu_page(
            'gkyc_business_dashboard',
            'Panel de Negocio',
            'Panel de Negocio',
            'manage_options',
            'gkyc_business_dashboard',
            'gkyc_mothership_render_dashboard_page'
        );
        
        // 3. AÑADIMOS MANUALMENTE LOS ENLACES A LOS TIPOS DE POST PERSONALIZADOS
        // Esto nos da control total sobre el orden y el texto.
        
        // Submenú para Solicitudes de Activación
        add_submenu_page(
            'gkyc_business_dashboard',
            'Solicitudes de Activación',
            'Solicitudes de Activación',
            'manage_options',
            'edit.php?post_type=gkyc_act_request' // Enlace directo a la lista del CPT
        );

        // Submenú para Solicitudes de Recarga
        add_submenu_page(
            'gkyc_business_dashboard',
            'Solicitudes de Recarga',
            'Solicitudes de Recarga',
            'manage_options',
            'edit.php?post_type=gkyc_recharge_req' // Enlace directo a la lista del CPT
        );
        
        // 4. AÑADIMOS LOS DEMÁS SUBMENÚS
        // Submenú de "Ajustes"
        add_submenu_page(
            'gkyc_business_dashboard',
            'Ajustes del Mothership',
            'Ajustes',
            'manage_options',
            'gkyc_mothership_settings',
            array( $this->settings_page_handler, 'create_admin_page' )
        );

        // Submenú para la página de "Mis Pagos a Didit"
        add_submenu_page(
            'gkyc_business_dashboard',
            'Mis Pagos a Didit',
            'Mis Pagos a Didit',
            'manage_options',
            'gkyc_didit_payments',
            'gkyc_render_didit_payments_page'
        );

        // Menú principal para "Partners" (sin cambios, es un menú separado)
        add_menu_page(
            'Partners',
            'Partners',
            'manage_options',
            'edit.php?post_type=cliente_kyc&partner_filter=1',
            '',
            'dashicons-groups',
            23
        );

        // Página oculta para el "Historial de Cliente" (sin cambios)
        add_submenu_page(
            null, 
            'Historial de Cliente',
            'Historial de Cliente',
            'manage_options',
            'gkyc_client_history',
            'gkyc_render_client_history_page'
        );
    }
    /**
    * Carga los scripts necesarios para los gráficos del Panel de Negocio.
    */
    function gkyc_enqueue_dashboard_scripts($hook) {
        // Solo cargar en nuestra página específica para no afectar el resto del admin
        if ($hook !== 'toplevel_page_gkyc_business_dashboard') {
            return;
        }

        // Obtener datos de fechas para los cálculos (igual que en la página)
        $current_period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'this_month';
        $end_date = date('Y-m-d');
        switch ($current_period) {
            case 'today': $start_date = date('Y-m-d'); break;
            case 'last_7_days': $start_date = date('Y-m-d', strtotime('-6 days')); break;
            case 'this_quarter': $current_month = date('n'); $current_quarter = ceil($current_month / 3); $first_month_of_quarter = ($current_quarter - 1) * 3 + 1; $start_date = date('Y-m-d', strtotime(date('Y') . '-' . $first_month_of_quarter . '-01')); break;
            case 'last_6_months': $start_date = date('Y-m-01', strtotime('-5 months')); break;
            case 'last_month': $start_date = date('Y-m-01', strtotime('last month')); $end_date = date('Y-m-t', strtotime('last month')); break;
            case 'this_month': default: $start_date = date('Y-m-01'); break;
        }

        $financials = gkyc_calculate_financials($start_date, $end_date);
        $stats = gkyc_mothership_get_business_stats($start_date, $end_date);

        // Encolar y localizar los scripts
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);

        wp_enqueue_script('gkyc-mothership-financials-js', MOTHERSHIP_URL . 'assets/js/mothership-financials.js', ['jquery', 'chart-js'], '1.0.2', true);
        wp_localize_script('gkyc-mothership-financials-js', 'gkycFinancialsData', $financials['chart_data']);

        wp_enqueue_script('gkyc-mothership-stats-js', MOTHERSHIP_URL . 'assets/js/mothership-stats.js', ['jquery', 'chart-js'], '1.2.2', true);
        wp_localize_script('gkyc-mothership-stats-js', 'gkycMothershipStatsData', $stats['chart_data']);
    }

    // --- INICIO DE LA NUEVA FUNCIÓN ---
    /**
    * Inicializa el manejador de productos de WooCommerce en el momento correcto.
    * Se asegura de que WooCommerce ya esté cargado antes de intentar integrarse.
    */
    public function initialize_woocommerce_handler() {
        if (class_exists('WooCommerce')) {
            $product_handler = new GKYC_Mothership_Product_Handler();
            $product_handler->init();
        }
    }
    // --- FIN DE LA NUEVA FUNCIÓN ---

} // <-- LA LLAVE DE CIERRE DE LA CLASE AHORA ESTÁ EN LA POSICIÓN CORRECTA

// Inicializa el plugin
function guardian_kyc_mothership_run() {
    return Guardian_KYC_Mothership::get_instance();
}
guardian_kyc_mothership_run();

/**
 * =================================================================================
 * ===== INICIO: NUEVAS FUNCIONES AYUDANTES PARA EL MODELO DE VALOR FACIAL =====
 * =================================================================================
 */

/**
 * Guarda el token de pago en los datos del carrito de WooCommerce.
 */
function gkyc_save_payment_token_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
    if ( isset( $_REQUEST['gkyc_token'] ) ) {
        $cart_item_data['gkyc_payment_token'] = sanitize_text_field( $_REQUEST['gkyc_token'] );
    }
    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'gkyc_save_payment_token_to_cart_item', 20, 3 );

/**
 * Guarda el token de pago como metadato en la orden final de WooCommerce.
 */
function gkyc_add_payment_token_to_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( ! empty( $values['gkyc_payment_token'] ) ) {
        $item->add_meta_data( '_gkyc_payment_token', $values['gkyc_payment_token'], true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'gkyc_add_payment_token_to_order_item_meta', 20, 4 );

/**
 * ===============================================================================
 * ===== FIN: NUEVAS FUNCIONES AYUDANTES PARA EL MODELO DE VALOR FACIAL =====
 * ===============================================================================
 */

/**
 * Hook que se activa cuando una orden de WooCommerce se marca como "Completada".
 * v5.6 - CORRECCIÓN FINAL: El sistema de bonificación ahora se aplica a TODOS los clientes.
 */
// --- INICIO: CÓDIGO FINAL, COMPLETO Y VERIFICADO ---

add_action('woocommerce_order_status_completed', 'gkyc_handle_completed_purchase_final', 10, 1);

// --- INICIO: CÓDIGO FINAL, COMPLETO Y VERIFICADO ---

function gkyc_handle_completed_purchase_final( $order_id ) {
    if ( ! $order_id ) { return; }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) { return; }

    // --- LÓGICA MEJORADA PARA ENCONTRAR AL USUARIO ---
    $user_id = $order->get_customer_id();
    $user_email = $order->get_billing_email();

    if ( ! $user_id && ! empty( $user_email ) ) {
        $user = get_user_by( 'email', $user_email );
        if ( $user ) {
            $user_id = $user->ID;
            $order->set_customer_id( $user_id );
            $order->save();
        }
    }

    if ( ! $user_id ) { return; }

    // --- LÓGICA DE ARQUITECTURA 1-A-MUCHOS ---
    // Verificamos si la orden contiene CUALQUIER producto que no sea de recarga.
    $contains_license_product = false;
    foreach ( $order->get_items() as $item ) {
        $product_type = get_post_meta( $item->get_product_id(), '_gkyc_product_type', true );
        if ( in_array($product_type, ['new_partner', 'additional_license']) ) {
            $contains_license_product = true;
            break;
        }
    }

    $client_query = new WP_Query([
        'post_type' => 'cliente_kyc', 'post_status' => 'any',
        'meta_query' => [ 'relation' => 'OR', ['key' => '_user_id', 'value' => $user_id], ['key' => '_email', 'value' => $user_email] ]
    ]);

    if ( $client_query->have_posts() && !$contains_license_product ) {
        // --- CASO 1: CLIENTE EXISTENTE (RECARGA DE PAQUETES O SALDO) ---
        // (Esta sección maneja la recarga de paquetes y saldo, y está funcionando correctamente)
        $client_post_id = $client_query->posts[0];
        update_post_meta( $client_post_id, '_user_id', $user_id );
        $is_partner = (stripos(get_post_meta($client_post_id, '_license_type', true), 'Partner') !== false);
        $amount_to_credit = 0;
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $product_type = get_post_meta($product_id, '_gkyc_product_type', true);
            if ( $is_partner && $product_type === 'package' ) {
                $package_id = get_post_meta($product_id, '_gkyc_assigned_package_id', true);
                if ($package_id) {
                    $licenses_in_package = (int) get_post_meta($package_id, '_license_limit', true);
                    if ($licenses_in_package > 0) {
                        $current_limit = (int) get_post_meta($client_post_id, '_license_limit', true);
                        $new_limit = $current_limit + $licenses_in_package;
                        update_post_meta($client_post_id, '_license_limit', $new_limit);
                        $order->add_order_note(sprintf('Paquete de licencias procesado. Se añadieron %d licencias. Nuevo límite: %d.', $licenses_in_package, $new_limit));
                        continue;
                    }
                }
            }
            if (get_post_meta($product_id, '_is_gkyc_balance_product', true) === 'yes' || $product_id == 2093) {
                $amount_to_credit += (float) $item->get_total();
            }
        }
        if ( $amount_to_credit > 0 ) {
            $current_balance = (float) get_post_meta( $client_post_id, '_balance', true );
            $bonus_amount = 0;
            $transaction_type_label = 'Recarga de Saldo';
            $settings = get_option('gkyc_mothership_settings');
            $bonus_tiers = [];
            for ($i = 1; $i <= 6; $i++) {
                if (!empty($settings["bonus_tier_{$i}_min"]) && !empty($settings["bonus_tier_{$i}_percentage"])) {
                    $bonus_tiers[] = [ 'min' => (float)$settings["bonus_tier_{$i}_min"], 'percentage' => (float)$settings["bonus_tier_{$i}_percentage"] ];
                }
            }
            if (!empty($bonus_tiers)) {
                rsort($bonus_tiers);
                foreach ($bonus_tiers as $tier) {
                    if ($amount_to_credit >= $tier['min']) {
                        $bonus_amount = $amount_to_credit * ($tier['percentage'] / 100);
                        break;
                    }
                }
            }
            $total_credit = $amount_to_credit + $bonus_amount;
            $new_balance = $current_balance + $total_credit;
            update_post_meta( $client_post_id, '_balance', $new_balance );
            $order_note = sprintf('Saldo acreditado: $%s. ', number_format($amount_to_credit, 2));
            if ($bonus_amount > 0) { $order_note .= sprintf('Bono aplicado: $%s. ', number_format($bonus_amount, 2)); }
            $order_note .= sprintf('Nuevo saldo total: $%s.', number_format($new_balance, 2));
            $order->add_order_note($order_note);
            $client_post = get_post($client_post_id);
            $transaction_title = sprintf('%s - %s', $transaction_type_label, $client_post->post_title);
            $transaction_post_id = wp_insert_post(['post_title' => $transaction_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
            if (!is_wp_error($transaction_post_id)) {
                update_post_meta($transaction_post_id, '_client_id', $client_post_id);
                update_post_meta($transaction_post_id, '_transaction_type', $transaction_type_label);
                update_post_meta($transaction_post_id, '_transaction_status', 'Completed');
                update_post_meta($transaction_post_id, '_transaction_amount', $amount_to_credit);
                if ($bonus_amount > 0) { update_post_meta($transaction_post_id, '_transaction_bonus', $bonus_amount); }
            }
            $client_site_url = get_post_meta($client_post_id, '_activated_domain', true);
            $mothership_api_key = get_post_meta($client_post_id, '_api_key_mothership', true);
            if (!empty($client_site_url) && !empty($mothership_api_key)) {
                $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
                wp_remote_post($sync_url, [ 'method' => 'POST', 'timeout' => 15, 'blocking' => false, 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode(['mothership_api_key' => $mothership_api_key]) ]);
            }
        }
    } else {
        // --- CASO 2: ES UN CLIENTE NUEVO ---
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            $product_id = $item->get_product_id();
            $product_type = get_post_meta($product_id, '_gkyc_product_type', true);
            
            // El disparador ahora es el tipo de producto.
            if ( in_array($product_type, ['new_partner', 'additional_license']) ) {
                $gift_balance = get_post_meta($product_id, '_gkyc_gift_balance', true) ?: 0; // Aseguramos que sea 0 si no existe

                $user_info = get_userdata($user_id);
                $new_post_id = wp_insert_post(['post_title'  => $user_info->display_name . ' (' . $user_info->user_email . ')', 'post_status' => 'publish', 'post_type'   => 'cliente_kyc']);
                if ( is_wp_error($new_post_id) ) { continue; }

                $license_limit = get_post_meta($product_id, '_gkyc_initial_license_limit', true);
                $product_name = $product->get_name();
                
                update_post_meta($new_post_id, '_user_id', $user_id);
                update_post_meta($new_post_id, '_email', $user_info->user_email);
                update_post_meta($new_post_id, '_license_type', $product_name);
                update_post_meta($new_post_id, '_client_status', 'pending');
                update_post_meta($new_post_id, '_balance', $gift_balance);

                if ($gift_balance > 0) {
                    $transaction_title = sprintf('Saldo de Bienvenida - %s', $user_info->display_name);
                    $transaction_post_id = wp_insert_post(['post_title' => $transaction_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
                    if (!is_wp_error($transaction_post_id)) {
                        update_post_meta($transaction_post_id, '_client_id', $new_post_id);
                        update_post_meta($transaction_post_id, '_transaction_type', 'Saldo Inicial');
                        update_post_meta($transaction_post_id, '_transaction_status', 'Completed');
                        update_post_meta($transaction_post_id, '_transaction_amount', $gift_balance);
                    }
                }
                
                $phone = $order->get_billing_phone();
                if (!empty($phone)) { update_post_meta($new_post_id, '_phone_number', $phone); }
                
                $mothership_api_key = 'gkycm_' . wp_generate_password(32, false);
                update_post_meta($new_post_id, '_api_key_mothership', $mothership_api_key);

                if (is_numeric($license_limit) && $license_limit > 0) {
                    update_post_meta($new_post_id, '_license_limit', absint($license_limit));
                }

                $to = $user_info->user_email;
                $headers = array('Content-Type: text/html; charset=UTF-8');
                $settings = get_option('gkyc_mothership_settings');

                if (stripos($product_name, 'Partner') !== false) {
                    $user_object = new WP_User($user_id);
                    $user_object->set_role('socio');
                    $subject_template = !empty($settings['partner_email_subject']) ? $settings['partner_email_subject'] : '¡Bienvenido al Programa de Socios!';
                    $body_template = !empty($settings['partner_email_body']) ? $settings['partner_email_body'] : 'Bienvenido...';
                    $final_subject = $subject_template;
                    $body = str_replace('[nombre_del_socio]', esc_html($user_info->display_name), $body_template);
                    $body = str_replace('[API_KEY_MAESTRA_DEL_PARTNER]', esc_html($mothership_api_key), $body);
                    $download_link_cliente = $settings['plugin_download_url_direct'] ?? '';
                    $download_link_kit = $settings['mini_plugin_download_url'] ?? '';
                    $body = str_replace('[ENLACE_DE_DESCARGA_PLUGIN_CLIENTE]', esc_url($download_link_cliente), $body);
                    $body = str_replace('[ENLACE_DE_DESCARGA_KIT_REVENTA]', esc_url($download_link_kit), $body);
                    $attachments = array();
                    if (!empty($settings['partner_email_pdf'])) {
                        $pdf_path = str_replace(get_site_url('/'), ABSPATH, $settings['partner_email_pdf']);
                        if (file_exists($pdf_path)) { $attachments[] = $pdf_path; }
                    }
                    $html_body = "<html><body>" . wpautop($body) . "</body></html>";
                    wp_mail( $to, $final_subject, $html_body, $headers, $attachments );
                } else {
                    gkyc_send_whitelabel_email($to, '', '', $new_post_id);
                }
                
                $order->add_order_note('Nueva cuenta de Guardián KYC creada: ' . esc_html($product_name));
                break; 
            }
        }
    }
}


// ============================================================================================
// ===== INICIO: NUEVAS FUNCIONES PARA EL HISTORIAL DE CLIENTE Y MEJORAS EN LAS LISTAS =====
// ============================================================================================

/**
 * Añade nuevas columnas a la lista de Transacciones para hacerla más informativa.
 */
function gkyc_add_transaction_columns($columns) {
    $columns['gkyc_client'] = 'Cliente';
    $columns['gkyc_type'] = 'Tipo';
    $columns['gkyc_amount'] = 'Monto';
    $columns['gkyc_status'] = 'Estado';
    return $columns;
}
add_filter('manage_gkyc_transaction_posts_columns', 'gkyc_add_transaction_columns');

/**
 * Muestra el contenido en las nuevas columnas de la lista de Transacciones.
 * v1.1 - Añadida lógica para mostrar débitos (negativos) en rojo.
 */
function gkyc_render_transaction_columns($column, $post_id) {
    $client_id = get_post_meta($post_id, '_client_id', true);
    
    switch ($column) {
        case 'gkyc_client':
            if ($client_id && $client_post = get_post($client_id)) {
                $history_url = admin_url('admin.php?page=gkyc_client_history&client_id=' . $client_id);
                echo '<a href="' . esc_url($history_url) . '"><strong>' . esc_html($client_post->post_title) . '</strong></a>';
            } else {
                echo 'N/A';
            }
            break;

        case 'gkyc_type':
            echo esc_html(get_post_meta($post_id, '_transaction_type', true));
            break;

        case 'gkyc_amount':
            $amount = (float) get_post_meta($post_id, '_transaction_amount', true);
            if ($amount > 0) { // Es un crédito (recarga)
                echo '<strong style="color: #28a745;">+$' . esc_html(number_format($amount, 2)) . '</strong>';
            } else if ($amount < 0) { // Es un débito (verificación)
                echo '<strong style="color: #dc3545;">-$' . esc_html(number_format(abs($amount), 2)) . '</strong>';
            } else { // Es cero
                echo '$0.00';
            }
            break;

        case 'gkyc_status':
            echo esc_html(get_post_meta($post_id, '_transaction_status', true));
            break;
    }
}
add_action('manage_gkyc_transaction_posts_custom_column', 'gkyc_render_transaction_columns', 10, 2);

/**
 * Añade el enlace "Ver Historial" a la lista de Clientes.
 */
function gkyc_add_history_link_to_clients($actions, $post) {
    if ($post->post_type === 'cliente_kyc') {
        $history_url = admin_url('admin.php?page=gkyc_client_history&client_id=' . $post->ID);
        $history_action = [
            'history' => sprintf(
                '<a href="%s" aria-label="Ver historial de %s" style="color:#007cba;"><strong>Ver Historial</strong></a>',
                esc_url($history_url),
                esc_attr($post->post_title)
            )
        ];
        $actions = array_merge($history_action, $actions);
    }
    return $actions;
}
add_filter('post_row_actions', 'gkyc_add_history_link_to_clients', 10, 2);

/**
 * Dibuja la página de Historial de Cliente.
 * v1.1 - Corregida la lógica para mostrar montos negativos (débitos).
 */
function gkyc_render_client_history_page() {
    if (!isset($_GET['client_id']) || !is_numeric($_GET['client_id'])) {
        wp_die('ID de cliente no válido.');
    }
    $client_id = intval($_GET['client_id']);
    $client = get_post($client_id);

    if (!$client || $client->post_type !== 'cliente_kyc') {
        wp_die('Cliente no encontrado.');
    }

    $balance = get_post_meta($client_id, '_balance', true);
    $email = get_post_meta($client_id, '_email', true);

    $transactions_query = new WP_Query([
        'post_type' => 'gkyc_transaction',
        'posts_per_page' => -1,
        'meta_key' => '_client_id',
        'meta_value' => $client_id,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    ?>
    <div class="wrap">
        <h1>Historial de Cliente: <?php echo esc_html($client->post_title); ?></h1>
        
        <div id="gkyc-client-summary" style="margin-top: 20px; margin-bottom: 30px; background: #fff; padding: 20px; border-left: 4px solid #007cba; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2 style="margin: 0; font-size: 1.5em;">Saldo Actual: <strong style="color: #007cba;">$<?php echo esc_html(number_format((float)$balance, 2)); ?></strong></h2>
            <p style="margin: 5px 0 0 0; font-size: 14px; color: #555;">Email: <?php echo esc_html($email); ?></p>
        </div>

        <h2>Transacciones Registradas</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:20%;">Fecha</th>
                    <th>Descripción</th>
                    <th style="width:15%;">Tipo</th>
                    <th style="width:15%;">Monto</th>
                    <th style="width:15%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions_query->have_posts()) : ?>
                    <?php while ($transactions_query->have_posts()) : $transactions_query->the_post(); ?>
                        <tr>
                            <td><?php echo get_the_date('d/m/Y H:i'); ?></td>
                            <td><?php the_title(); ?></td>
                            <td><?php echo esc_html(get_post_meta(get_the_ID(), '_transaction_type', true)); ?></td>
                            <td>
                                <?php
                                // ==========================================================
                                // ===== INICIO: LÓGICA DE VISUALIZACIÓN CORREGIDA =====
                                // ==========================================================
                                $amount = (float) get_post_meta(get_the_ID(), '_transaction_amount', true);
                                if ($amount > 0) { // Es un crédito (recarga, transferencia entrante)
                                    echo '<strong style="color: #28a745;">+$' . esc_html(number_format($amount, 2)) . '</strong>';
                                } else if ($amount < 0) { // Es un débito (verificación, transferencia saliente)
                                    echo '<strong style="color: #dc3545;">-$' . esc_html(number_format(abs($amount), 2)) . '</strong>';
                                } else { // Es cero
                                    echo '$0.00';
                                }
                                // ========================================================
                                // ===== FIN: LÓGICA DE VISUALIZACIÓN CORREGIDA =====
                                // ========================================================
                                ?>
                            </td>
                            <td><?php echo esc_html(get_post_meta(get_the_ID(), '_transaction_status', true)); ?></td>
                        </tr>
                    <?php endwhile; ?>
                    <?php wp_reset_postdata(); ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No se encontraron transacciones para este cliente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ==========================================================================================
// ===== FIN: NUEVAS FUNCIONES PARA EL HISTORIAL DE CLIENTE Y MEJORAS EN LAS LISTAS =====
// ==========================================================================================


/**
 * Shortcode [gkyc_dato] para mostrar información del cliente.
 * v2.1 - Corregido el registro del shortcode.
 */
function gkyc_get_client_data_shortcode( $atts ) {
    // Definimos los atributos por defecto
    $atts = shortcode_atts( [
        'tipo' => 'nombre', // Opciones: nombre, licencia, tarifa, sitio, saldo
    ], $atts, 'gkyc_dato' );

    // Definimos los datos por defecto (para visitantes)
    $cliente_data = [
        'nombre'   => 'Visitante',
        'licencia' => 'N/A',
        'tarifa'   => 0,
        'sitio'    => 'N/A',
        'saldo'    => '0.00'
    ];
    
    $client_post_id = 0;

    // MÉTODO 1: Buscar cliente por API Key en la URL
    if ( isset($_GET['apikey']) && !empty($_GET['apikey']) ) {
        $api_key = sanitize_text_field($_GET['apikey']);
        $client_query = new WP_Query([
            'post_type'      => 'cliente_kyc',
            'posts_per_page' => 1,
            'meta_query'     => [['key' => '_api_key_mothership', 'value' => $api_key]],
            'fields'         => 'ids'
        ]);
        if ( $client_query->have_posts() ) {
            $client_post_id = $client_query->posts[0];
        }
    } 
    // MÉTODO 2: Si no hay API Key, buscar por usuario con sesión iniciada
    elseif ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $client_query = new WP_Query([
            'post_type'      => 'cliente_kyc',
            'posts_per_page' => 1,
            'meta_key'       => '_user_id',
            'meta_value'     => $user_id,
            'fields'         => 'ids'
        ]);
        if ( $client_query->have_posts() ) {
            $client_post_id = $client_query->posts[0];
        }
    }

    // Si encontramos un cliente por CUALQUIERA de los dos métodos, obtenemos sus datos
    if ( $client_post_id > 0 ) {
        $cliente_data['nombre']   = get_the_title($client_post_id);
        $cliente_data['licencia'] = get_post_meta($client_post_id, '_license_type', true);
        $cliente_data['sitio']    = get_post_meta($client_post_id, '_activated_domain', true);
        $cliente_data['saldo']    = get_post_meta($client_post_id, '_balance', true);

        // Lógica para obtener la tarifa
        $licencia_nombre = $cliente_data['licencia'];
        if (strpos($licencia_nombre, 'Emprendedor') !== false) {
            $cliente_data['tarifa'] = 0.95;
        } elseif (strpos($licencia_nombre, 'Negocios') !== false) {
            $cliente_data['tarifa'] = 1.45;
        } elseif (strpos($licencia_nombre, 'Corporativo') !== false) {
            $cliente_data['tarifa'] = 1.95;
        }
    }

    // Devolvemos el dato específico que se pidió en el shortcode
    switch ( $atts['tipo'] ) {
        case 'nombre':
            return esc_html( strtok($cliente_data['nombre'], ' (') );
        case 'licencia':
            return esc_html( $cliente_data['licencia'] );
        case 'tarifa':
            return '$' . esc_html( number_format((float)$cliente_data['tarifa'], 2) );
        case 'sitio':
            return !empty($cliente_data['sitio']) ? esc_html($cliente_data['sitio']) : 'N/A';
        case 'saldo':
            return '$' . esc_html( number_format((float)$cliente_data['saldo'], 2) );
        default:
            return '';
    }
}
// ===== ¡ESTA ES LA LÍNEA QUE FALTABA Y CORRIGE EL PROBLEMA! =====
add_shortcode('gkyc_dato', 'gkyc_get_client_data_shortcode');
/**
 * Crea el shortcode [gkyc_calculadora] que muestra la "Súper Calculadora" de recarga.
 * v4.5 - FINAL DEFINITIVO: Sin botón interno, se conecta a botones de Elementor.
 */
function gkyc_render_recharge_calculator_shortcode() {
    
    if (!is_user_logged_in()) { return '<p>Por favor, inicia sesión para recargar tu saldo.</p>'; }

    $user_id = get_current_user_id();
    $client_query = new WP_Query([ 'post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_key' => '_user_id', 'meta_value' => $user_id, 'fields' => 'ids' ]);

    if (!$client_query->have_posts()) { return '<p>Tu usuario no está asociado a ninguna cuenta de cliente.</p>'; }
    
    $client_post_id = $client_query->posts[0];
    $user_info = get_userdata($user_id);
    $user_display_name = $user_info->display_name;
    $licencia_nombre = get_post_meta($client_post_id, '_license_type', true);
    $is_partner = (stripos($licencia_nombre, 'Partner') !== false);

    $bonus_tiers_for_js = [];
    $settings = get_option('gkyc_mothership_settings');
    for ($i = 1; $i <= 6; $i++) {
        if (!empty($settings["bonus_tier_{$i}_min"]) && !empty($settings["bonus_tier_{$i}_percentage"])) {
            $bonus_tiers_for_js[] = [ 'min' => (float)$settings["bonus_tier_{$i}_min"], 'percentage' => (float)$settings["bonus_tier_{$i}_percentage"] ];
        }
    }
    if(!empty($bonus_tiers_for_js)) { rsort($bonus_tiers_for_js); }

    $plans_data_for_js = [];
    $all_plans_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1]);
    
    if ($all_plans_query->have_posts()) {
        foreach($all_plans_query->posts as $plan_post) {
            if ($is_partner) {
                if (stripos($plan_post->post_title, 'Partner') === false) {
                    $price_string = get_post_meta($plan_post->ID, '_price', true);
                    preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
                    $public_price = isset($matches[0]) ? (float)$matches[0] : 0;
                    if ($public_price > 0) { $plans_data_for_js[] = [ 'name' => $plan_post->post_title, 'cost' => $public_price ]; }
                }
            } else {
                if (stripos($licencia_nombre, $plan_post->post_title) !== false) {
                    $price_string = get_post_meta($plan_post->ID, '_price', true);
                    preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
                    $public_price = isset($matches[0]) ? (float)$matches[0] : 0;
                    if ($public_price > 0) { $plans_data_for_js[] = [ 'name' => $plan_post->post_title, 'cost' => $public_price ]; break; }
                }
            }
        }
    }
    
    ob_start();
    ?>
    <div class="gkyc-saludo-usuario">
        <span>Hola, <strong><?php echo esc_html($user_display_name); ?></strong></span>
    </div>
    <div class="gkyc-calculadora-wrapper">
        <div class="gkyc-info-plan">
            <p>Estás recargando tu <strong><?php echo esc_html($licencia_nombre); ?></strong>.</p>
        </div>
        
        <label for="montoRecargaSlider" class="gkyc-slider-label">Desliza para seleccionar el monto a recargar:</label>
        <input type="range" min="20" max="10000" value="100" step="10" class="gkyc-slider" id="montoRecargaSlider">
        
        <div class="gkyc-resultados-display">
            <div class="gkyc-resultado-item"><span class="gkyc-etiqueta">Monto a Pagar:</span><span class="gkyc-valor" id="displayMonto">$100.00</span></div>
            <div id="gkyc-bonus-display" style="margin-top: 15px; padding: 10px; background-color: #e8f5e9; border-left: 4px solid #28a745; border-radius: 4px; display: none;"><p style="margin: 0; font-weight: bold; color: #155724;"><span id="gkyc-bonus-percentage-text"></span><span id="gkyc-bonus-amount-text" style="display: block; font-size: 1.2em;"></span></p></div>
            <div class="gkyc-resultado-item" style="margin-top: 15px; font-size: 1.2em; font-weight: bold;"><span class="gkyc-etiqueta">Saldo Total que Recibirás:</span><span class="gkyc-valor-grande" id="displayTotalCredit">$100.00</span></div>
            <div id="gkyc-savings-display" style="margin-top: 15px; padding: 10px; background-color: #e0f2f1; border-left: 4px solid #007cba; border-radius: 4px; display: none;"></div>
        </div>
        </div>
    
    <script>
    window.addEventListener('load', function() {
        const slider = document.getElementById('montoRecargaSlider');
        const savingsDisplay = document.getElementById('gkyc-savings-display');
        const displayMonto = document.getElementById('displayMonto');
        const displayTotalCredit = document.getElementById('displayTotalCredit');
        const bonusDisplay = document.getElementById('gkyc-bonus-display');
        const bonusPercentageText = document.getElementById('gkyc-bonus-percentage-text');
        const bonusAmountText = document.getElementById('gkyc-bonus-amount-text');
        
        // Volvemos a la configuración original para buscar tus botones de Elementor
        const botonCardPaypal = document.getElementById('boton-pago-card-paypal');
        const botonUSDT = document.getElementById('boton-pago-usdt');
        
        const isPartner = <?php echo json_encode($is_partner); ?>;
        const bonusTiers = <?php echo json_encode($bonus_tiers_for_js); ?>;
        const plansData = <?php echo json_encode($plans_data_for_js); ?>;
        const idProductoSaldo = 2093;

        function actualizarTodo() {
            const monto = parseInt(slider.value);
            let bonusAmount = 0;
            let bonusPercentage = 0;

            if (bonusTiers.length > 0) {
                for (const tier of bonusTiers) {
                    if (monto >= tier.min) {
                        bonusPercentage = tier.percentage;
                        bonusAmount = monto * (tier.percentage / 100);
                        break;
                    }
                }
            }
            
            const totalCredit = monto + bonusAmount;
            
            displayMonto.textContent = '$' + monto.toFixed(2);
            displayTotalCredit.textContent = '$' + totalCredit.toFixed(2);
            
            if (bonusAmount > 0) {
                bonusPercentageText.textContent = '¡Calificas para un bono del ' + bonusPercentage + '%!';
                bonusAmountText.textContent = 'Recibirás +$' + bonusAmount.toFixed(2) + ' extra';
                bonusDisplay.style.display = 'block';
            } else {
                bonusDisplay.style.display = 'none';
            }

            let savingsHTML = '';
            if (bonusAmount > 0 && plansData.length > 0) {
                if (isPartner) {
                    savingsHTML += '<p style="margin: 0 0 10px 0; font-weight: bold; color: #333;">Con este bono, tus precios por verificación bajarían a:</p><ul style="margin: 0; padding-left: 20px; color: #333;">';
                    plansData.forEach(plan => {
                        if (plan.cost > 0) {
                            const verifications = totalCredit / plan.cost;
                            const effectiveCost = monto / verifications;
                            savingsHTML += `<li><strong>${plan.name}:</strong> $${effectiveCost.toFixed(2)} (antes $${plan.cost.toFixed(2)})</li>`;
                        }
                    });
                    savingsHTML += '</ul>';
                } else {
                    const plan = plansData[0];
                    if (plan.cost > 0) {
                        const verifications = totalCredit / plan.cost;
                        const effectiveCost = monto / verifications;
                        savingsHTML = `<p style="margin: 0; font-weight: bold; color: #004085;">¡Con este bono, cada verificación te costará solo <strong>$${effectiveCost.toFixed(2)}</strong> en lugar de $${plan.cost.toFixed(2)}!</p>`;
                    }
                }
            }

            if (savingsHTML) {
                savingsDisplay.innerHTML = savingsHTML;
                savingsDisplay.style.display = 'block';
            } else {
                savingsDisplay.style.display = 'none';
            }
            
            const urlBase = '<?php echo home_url("/finalizar-compra/"); ?>';
            let urlDeCompra = `${urlBase}?add-to-cart=${idProductoSaldo}&quantity=${monto}`;

            // --- INICIO DE LA CORRECCIÓN ---
            // Leemos el return_url de la página actual y lo añadimos al enlace de compra
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('return_url')) {
                urlDeCompra += '&return_url=' + encodeURIComponent(urlParams.get('return_url'));
            }
            // --- FIN DE LA CORRECCIÓN ---
            
            if (botonCardPaypal) { botonCardPaypal.href = urlDeCompra; }
            if (botonUSDT) { botonUSDT.href = urlDeCompra + '&payment_method=nowpayments'; }
        }

        actualizarTodo();
        slider.addEventListener('input', actualizarTodo);
    });
    </script>
    <?php
    return ob_get_clean();
}

// ===== ¡ESTA ES LA LÍNEA QUE FALTABA! =====
add_shortcode('gkyc_calculadora', 'gkyc_render_recharge_calculator_shortcode');


// ===== SISTEMA DE NOTIFICACIÓN DE SALDO BAJO (CRON JOB) =====

// Se ejecuta cuando el plugin se activa, para programar nuestra tarea diaria.
register_activation_hook(__FILE__, 'gkyc_schedule_low_balance_check');
function gkyc_schedule_low_balance_check() {
    if (!wp_next_scheduled('gkyc_daily_low_balance_check_event')) {
        wp_schedule_event(time(), 'daily', 'gkyc_daily_low_balance_check_event');
    }
}

// Se ejecuta cuando el plugin se desactiva, para limpiar la tarea programada.
register_deactivation_hook(__FILE__, 'gkyc_unschedule_low_balance_check');
function gkyc_unschedule_low_balance_check() {
    wp_clear_scheduled_hook('gkyc_daily_low_balance_check_event');
}

// Vinculamos nuestra función principal al evento programado.
add_action('gkyc_daily_low_balance_check_event', 'gkyc_do_low_balance_check');

/**
 * La función principal que se ejecuta una vez al día para revisar y notificar.
 * v2.1 - 100% Inteligente: Maneja clientes directos y de partners, usando el enlace de recarga y la firma correctos.
 */
function gkyc_do_low_balance_check() {
    $settings = get_option('gkyc_mothership_settings');

    if (empty($settings['enable_notifications']) || !$settings['enable_notifications']) {
        return;
    }

    $threshold = isset($settings['balance_threshold']) ? (float)$settings['balance_threshold'] : 10.0;
    $subject_template = isset($settings['notification_subject']) ? $settings['notification_subject'] : 'Tu saldo está bajo';
    $body_template = isset($settings['notification_body']) ? $settings['notification_body'] : '';

    if (empty($body_template)) {
        return;
    }

    $all_clients = get_posts(['post_type' => 'cliente_kyc', 'numberposts' => -1, 'post_status' => 'publish']);

    foreach ($all_clients as $client_post) {
        $client_id = $client_post->ID;
        $client_balance = (float) get_post_meta($client_id, '_balance', true);
        
        if ($client_balance < $threshold && is_email(get_post_meta($client_id, '_email', true))) {
            
            $last_notified = get_post_meta($client_id, '_low_balance_notified_date', true);
            $seven_days_ago = strtotime('-7 days');

            if (empty($last_notified) || $last_notified < $seven_days_ago) {
                
                $client_email = get_post_meta($client_id, '_email', true);
                $client_name = strtok($client_post->post_title, ' (');
                
                // ===== INICIO DE LA LÓGICA 100% INTELIGENTE =====
                
                $recharge_url = home_url('/pagos/'); // URL por defecto
                $signature_name = 'El equipo de Guardián KYC'; // Firma por defecto
                
                $partner_id = get_post_meta($client_id, '_reseller_owner_id', true);

                // Si el cliente pertenece a un socio...
                if (!empty($partner_id)) {
                    $partner_recharge_url = get_post_meta($partner_id, '_partner_recharge_url', true);
                    if (!empty($partner_recharge_url)) {
                        $recharge_url = $partner_recharge_url; // ...usamos su URL de recarga.
                    }
                    
                    $partner_name = strtok(get_the_title($partner_id), ' (');
                    if ($partner_name) {
                        $signature_name = 'El equipo de ' . esc_html($partner_name); // ...y usamos su nombre en la firma.
                    }
                }
                
                // Preparamos los shortcodes para el reemplazo
                $replacements = [
                    '[nombre_cliente]'    => esc_html($client_name),
                    '[saldo_actual]'      => '$' . number_format($client_balance, 2),
                    '[enlace_de_recarga]' => esc_url($recharge_url),
                    '[nombre_remitente]'  => $signature_name, // ¡NUEVO SHORTCODE INTELIGENTE!
                ];

                $final_subject = str_replace(array_keys($replacements), array_values($replacements), $subject_template);
                $final_body = str_replace(array_keys($replacements), array_values($replacements), $body_template);
                
                // ===== FIN DE LA LÓGICA 100% INTELIGENTE =====

                $html_body = "<html><body>" . wpautop($final_body) . "</body></html>";
                $headers = array('Content-Type: text/html; charset=UTF-8');
                
                wp_mail($client_email, $final_subject, $html_body, $headers);
                
                update_post_meta($client_id, '_low_balance_notified_date', time());
            }
        }
    }
}

/**
 * Añade un campo personalizado "URL de Descarga" en la edición de productos de WooCommerce.
 */
add_action( 'woocommerce_product_options_general_product_data', 'gkyc_add_download_link_field' );
function gkyc_add_download_link_field() {
    echo '<div class="options_group">';
    woocommerce_wp_text_input( [
        'id'          => 'plugin_download_link',
        'label'       => 'URL de Descarga del Plugin (GKYC)',
        'placeholder' => 'https://ruta/a/tu/plugin.zip',
        'desc_tip'    => 'true',
        'description' => 'Pega aquí el enlace de descarga del plugin asociado a este producto.',
        'type'        => 'url',
    ] );
    echo '</div>';
}

/**
 * Guarda el valor del campo personalizado "URL de Descarga".
 */
add_action( 'woocommerce_process_product_meta', 'gkyc_save_download_link_field' );
function gkyc_save_download_link_field( $post_id ) {
    $download_link = isset( $_POST['plugin_download_link'] ) ? esc_url_raw( $_POST['plugin_download_link'] ) : '';
    $product = wc_get_product( $post_id );
    $product->update_meta_data( 'plugin_download_link', $download_link );
    $product->save();
}

// ===== CÓDIGO PARA EL PANEL FRONTEND DE REVENDEDORES (PARTNERS) =====

/**
 * Registra el shortcode [gkyc_partner_dashboard] que muestra el panel de gestión.
 */
add_shortcode('gkyc_partner_dashboard', 'gkyc_render_partner_dashboard');

/**
 * Dibuja el contenido HTML de la página del Panel de Socio.
 * VERSIÓN 6.0 - FINAL Y UNIFICADA. SIN JAVASCRIPT INTERNO.
 */
function gkyc_render_partner_dashboard() {
    // --- Lógica inicial para encontrar al socio ---
    if (!is_user_logged_in()) { return '<div class="gkyc-notice error">Por favor, inicia sesión para acceder a tu panel de socio.</div>'; }
    $user_id = get_current_user_id();
    $partner_query = new WP_Query([
        'post_type' => 'cliente_kyc', 
        'posts_per_page' => 1, 
        'meta_query' => [ 
            'relation' => 'AND', 
            ['key' => '_user_id', 'value' => $user_id], 
            ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']
        ], 
        'fields' => 'ids'
    ]);
    if (!$partner_query->have_posts()) { return '<div class="gkyc-notice error">Tu usuario de WordPress no está asociado a ningún perfil de Partner.</div>'; }
    
    $partner_client_id = $partner_query->posts[0];
    
    // Obtenemos el número de página actual de la URL para la paginación
    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
    $stats = gkyc_mothership_get_partner_stats($partner_client_id, $paged);
    
    $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';

    // Lógica para obtener los planes para la sección "Vender Licencia"
    $plans_for_sale = [];
    $all_plans_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']);
    if ($all_plans_query->have_posts()) {
        while($all_plans_query->have_posts()) {
            $all_plans_query->the_post();
            $plan_post = get_post();
            if (stripos($plan_post->post_title, 'Partner') !== false) continue;
            $price_meta_key = '_partner_license_price_' . $plan_post->post_name;
            $price = (float) get_post_meta($partner_client_id, $price_meta_key, true);
            if ($price > 0) {
                $plans_for_sale[] = [ 'name' => $plan_post->post_title, 'description' => get_post_meta($plan_post->ID, '_description', true), 'price' => $price ];
            }
        }
    }
    wp_reset_postdata();
    
    ob_start();
    ?>
    <style> 
    /* --- Estilos CSS Completos y Corregidos --- */
    .gkyc-partner-dashboard { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; max-width: 1200px; margin: 2em auto; }
    .gkyc-kpi-cards-partner { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .gkyc-card-partner { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
    .gkyc-card-partner h3 { margin: 0 0 10px 0; font-size: 15px; color: #555; font-weight: 600; }
    .gkyc-card-partner p { margin: 0; font-size: 32px; font-weight: 700; color: #1d2327; }
    .dashboard-section { margin-top: 20px; }
    .dashboard-section > h3 { font-size: 24px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .gkyc-partner-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 40px; }
    .gkyc-widget-card { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.07); }
    .gkyc-client-list-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;}
    .gkyc-client-list-table-wrapper { overflow-x: auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .gkyc-client-list-table { width: 100%; border-collapse: collapse; }
    .gkyc-client-list-table th, .gkyc-client-list-table td { padding: 16px; border-bottom: 1px solid #f0f0f0; text-align: left; white-space: nowrap; }
    .gkyc-client-list-table th { background-color: #f9f9f9; font-weight: 600; color: #333; }
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; color: #fff; text-transform: capitalize; }
    .status-active { background-color: #28a745; } .status-pending { background-color: #ffc107; color: #333; } .status-suspended { background-color: #dc3545; }
    .gkyc-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center;}
    .gkyc-modal-content { background-color: #fefefe; padding: 30px; border: 1px solid #888; width: 90%; max-width: 550px; border-radius: 8px; position: relative; }
    .gkyc-modal-close { color: #aaa; position: absolute; top: 10px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; }
    .gkyc-license-sale-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
    .gkyc-plan-card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 25px; text-align: left; display: flex; flex-direction: column; }
    .nav-tab-wrapper { border-bottom: 1px solid #ccc; padding-left: 10px; margin-bottom: 20px !important; }
    .nav-tab { display: inline-block; padding: 10px 20px; text-decoration: none; border: 1px solid #ccc; border-bottom: none; margin-right: 5px; background: #f0f0f0; border-radius: 4px 4px 0 0; font-size: 14px; font-weight: 600; color: #555; }
    .nav-tab-active, .nav-tab-active:hover { background: #fff; border-bottom: 1px solid #fff; margin-bottom: -1px; color: #007cba; }
    .nav-tab:hover { background: #e0e0e0; color: #333; }
    .gkyc-modal-field label { display: block; margin-bottom: 6px; }
    .gkyc-client-list-table .gkyc-clickable-row {
        cursor: pointer;
        transition: background-color 0.2s ease-in-out;
    }
    .gkyc-client-list-table .gkyc-clickable-row:hover {
        background-color: #f0f8ff !important;
    }

    /* ===== Maquillaje para Botones del Panel v1.1 (Más Específico) ===== */
    .gkyc-partner-dashboard .button.button-primary {
        background: linear-gradient(145deg, #007cba, #005a87) !important;
        border: none !important;
        border-radius: 8px !important;
        box-shadow: 0 4px 15px rgba(0, 124, 186, 0.3) !important;
        color: #ffffff !important;
        font-weight: bold !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        padding: 12px 24px !important;
        transition: all 0.3s ease !important;
    }
    .gkyc-partner-dashboard .button.button-primary:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 6px 20px rgba(0, 124, 186, 0.4) !important;
    }
    .gkyc-partner-dashboard .gkyc-client-list-table .button.button-secondary {
        border-radius: 8px !important;
        font-weight: bold !important;
        transition: all 0.3s ease !important;
        background-color: #f6f7f7 !important;
        border-color: #dcdcde !important;
        color: #50575e !important;
    }
    .gkyc-partner-dashboard .gkyc-client-list-table .button.button-secondary:hover {
        background-color: #e0e0e0 !important;
        border-color: #ccc !important;
    }

    /* ======================================================================== */
    /* ===== Maquillaje Adicional para Componentes del Panel (v1.2) ===== */
    /* ======================================================================== */

    /* --- Estilos para la Calculadora de Rentabilidad --- */
    #gkyc-profit-calculator-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); /* Columnas adaptables */
        gap: 15px;
        align-items: flex-end;
    }
    #gkyc-profit-calculator-form > div {
        display: flex;
        flex-direction: column;
    }
    /* Estilos para los campos de la calculadora y la tabla de precios */
    #gkyc-profit-calculator-form select,
    #gkyc-profit-calculator-form input[type="number"],
    #gkyc-partner-prices-form input[type="number"] {
        padding: 10px 12px;
        border-radius: 6px;
        border: 1px solid #ddd;
        font-size: 16px; /* Letra más grande */
        line-height: 1.5;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    #gkyc-profit-calculator-form select:focus,
    #gkyc-profit-calculator-form input[type="number"]:focus,
    #gkyc-partner-prices-form input[type="number"]:focus {
        border-color: #007cba;
        box-shadow: 0 0 0 1px #007cba;
        outline: none;
    }

    /* --- Estilos para los botones de la calculadora y modales --- */
    #gkyc-profit-calculator-form button,
    #gkyc-submit-transfer-btn {
        background: #50575e !important; /* Un gris oscuro elegante */
        color: #fff !important;
        border: none !important;
        border-radius: 8px !important;
        font-weight: bold !important;
        padding: 10px 20px !important;
        text-transform: uppercase !important;
        letter-spacing: 1px !important;
        cursor: pointer;
        transition: all 0.2s ease !important;
    }
    #gkyc-profit-calculator-form button:hover,
    #gkyc-submit-transfer-btn:hover {
        background: #3c434a !important;
        transform: translateY(-2px);
    }

    /* --- Corrección para el espacio en el modal de transferencia --- */
    #gkyc-submit-transfer-btn {
        margin-top: 15px; /* Añade el espacio que faltaba */
        width: 100%;
    }

    /* ======================================================================== */
    /* ===== Estilos para el Formulario de Branding (v1.0) ===== */
    /* ======================================================================== */

    .gkyc-form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); /* Columnas adaptables */
        gap: 25px 30px; /* Espacio vertical y horizontal */
        background-color: #fff;
        padding: 30px;
        border-radius: 8px;
        border: 1px solid #ddd;
    }

    .gkyc-form-field {
        display: flex;
        flex-direction: column;
    }

    .gkyc-form-field label {
        font-weight: bold;
        margin-bottom: 8px;
    }

    .gkyc-form-field .regular-text {
        width: 100%;
        padding: 8px 10px;
        border-radius: 4px;
        border: 1px solid #ddd;
    }

    .gkyc-form-field .description {
        font-size: 13px;
        color: #666;
        margin-top: 8px;
    }

    .gkyc-media-uploader {
        display: flex;
        gap: 10px;
    }
    .gkyc-media-uploader input {
        flex-grow: 1;
    }

    .gkyc-logo-preview {
        border: 1px solid #ddd;
        padding: 5px;
        background: #fff;
        border-radius: 4px;
        object-fit: contain;
    }
    .gkyc-logo-preview.favicon { max-height: 48px; max-width: 48px; }
    .gkyc-logo-preview:not(.favicon) { max-height: 80px; }
    .gkyc-logo-preview.wide { max-width: 160px; }
</style>

    <div class="gkyc-partner-dashboard">
        <h2>Panel de Socio</h2>
        <p>¡Bienvenido a tu centro de operaciones!</p>
        
        <div class="gkyc-kpi-cards-partner">
            <div class="gkyc-card-partner">
                <h3>Saldo Maestro</h3>
                <p id="partner-master-balance" style="margin-bottom: 15px;">$<?php echo number_format($stats['saldo_maestro'], 2); ?></p>
                <?php
                    $partner_api_key = get_post_meta($partner_client_id, '_api_key_mothership', true);
                    $partner_dashboard_url = get_permalink(); 
                    $recharge_url = add_query_arg(['apikey' => $partner_api_key, 'return_url' => $partner_dashboard_url], home_url('/pagos/'));
                ?>
                <a href="<?php echo esc_url($recharge_url); ?>" target="_blank" class="button button-primary" style="width: 100%; text-align: center;">Recargar Saldo</a>
            </div>
            <div class="gkyc-card-partner"><h3>Licencias Disponibles</h3><p><?php echo $stats['licencias_disponibles']; ?> / <?php echo $stats['limite_licencias']; ?></p></div>
            <div class="gkyc-card-partner"><h3>Clientes Asignados</h3><p><?php echo $stats['clientes_asignados']; ?></p></div>
            <div class="gkyc-card-partner"><h3>Saldo Distribuido</h3><p>$<?php echo number_format($stats['saldo_distribuido'], 2); ?></p></div>
        </div> 
        <h2 class="nav-tab-wrapper">
            <a href="?tab=dashboard" class="nav-tab <?php echo $active_tab == 'dashboard' ? 'nav-tab-active' : ''; ?>">Panel Principal</a>

            <?php
            // Consulta para el contador de solicitudes de LICENCIA
            $pending_act_query = new WP_Query([
                'post_type' => 'gkyc_act_request',
                'post_status' => 'pending',
                'meta_query' => [['key' => '_partner_owner_id', 'value' => $partner_client_id]],
                'fields' => 'ids'
            ]);
            $pending_act_count = $pending_act_query->found_posts;
            ?>
            <a href="?tab=solicitudes" class="nav-tab <?php echo $active_tab == 'solicitudes' ? 'nav-tab-active' : ''; ?>">
                Solicitudes de Licencia <?php if ($pending_act_count > 0) { echo '<span class="update-plugins"><span class="plugin-count">' . $pending_act_count . '</span></span>'; } ?>
            </a>
            
            <?php 
            wp_reset_postdata(); // Comando de limpieza
            
            // Consulta para el contador de solicitudes de RECARGA
            $pending_recharge_query = new WP_Query([
                'post_type' => 'gkyc_recharge_req', 
                'post_status' => 'pending', 
                'meta_query' => [['key' => '_partner_owner_id', 'value' => $partner_client_id]], 
                'fields' => 'ids'
            ]);
            $pending_recharge_count = $pending_recharge_query->found_posts;
            ?>
            <a href="?tab=recargas" class="nav-tab <?php echo $active_tab == 'recargas' ? 'nav-tab-active' : ''; ?>">
                Solicitudes de Recarga <?php if ($pending_recharge_count > 0) { echo '<span class="update-plugins"><span class="plugin-count">' . $pending_recharge_count . '</span></span>'; } ?>
            </a>

            <a href="?tab=saldos" class="nav-tab <?php echo $active_tab == 'saldos' ? 'nav-tab-active' : ''; ?>">Mis Saldos</a>

            <a href="?tab=precios" class="nav-tab <?php echo $active_tab == 'precios' ? 'nav-tab-active' : ''; ?>">Mis Precios y Pagos</a>
            <a href="?tab=libro_contable" class="nav-tab <?php echo $active_tab == 'libro_contable' ? 'nav-tab-active' : ''; ?>">Mi Registro</a>
            <a href="?tab=marca" class="nav-tab <?php echo $active_tab == 'marca' ? 'nav-tab-active' : ''; ?>">Mi Marca</a>
            <a href="?tab=piloto_automatico" class="nav-tab <?php echo $active_tab == 'piloto_automatico' ? 'nav-tab-active' : ''; ?>">Piloto Automático</a>
        </h2>

        <?php if ($active_tab == 'dashboard'): ?>
            <div class="dashboard-section">
                <h3>Vender una Nueva Licencia</h3>
                <div class="gkyc-license-sale-container">
                    <?php if (!empty($plans_for_sale)) : foreach ($plans_for_sale as $plan) : ?>
                        <div class="gkyc-plan-card">
                            <h3><?php echo esc_html($plan['name']); ?></h3>
                            <div class="price">$<?php echo esc_html(number_format($plan['price'], 2)); ?></div>
                            <p class="description"><?php echo esc_html($plan['description']); ?></p>
                            <a href="<?php echo esc_url(get_post_meta($partner_client_id, '_partner_paypal_link', true)); ?>" class="button button-primary" target="_blank">Vender con PayPal</a>
                        </div>
                    <?php endforeach; else : ?>
                        <p>Aún no has configurado precios para la venta de licencias.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gkyc-partner-grid">
                 <div class="gkyc-widget-card"><h3>Actividad de Clientes</h3><canvas id="gkyc-partner-activity-chart"></canvas></div>
                 <div class="gkyc-widget-card"><h3>Desglose de Verificaciones</h3><canvas id="gkyc-partner-status-chart"></canvas></div>
            </div>

            <div class="dashboard-section">
                <h3>Gestión de Clientes Existentes</h3>
                <div class="gkyc-client-list-toolbar">
                    <input type="text" id="gkyc-client-search" placeholder="Buscar cliente...">
                    <button id="gkyc-open-modal-btn" class="button button-primary">Generar Nueva Licencia</button>
                </div>
                <div class="gkyc-client-list-table-wrapper">
                    <table class="gkyc-client-list-table">
                        <thead><tr><th>Cliente</th><th>Email</th><th>Sitio Web</th><th>Estado</th><th>Saldo</th><th>Acciones</th></tr></thead>
                        <tbody id="gkyc-client-list-tbody">
                            <?php if (empty($stats['sub_clients'])) : ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px;">Aún no tienes clientes.</td></tr>   
                            <?php else : foreach ($stats['sub_clients'] as $client) : 
                                $client_status = get_post_meta($client->ID, '_client_status', true) ?: 'pending';
                                $client_domain = get_post_meta($client->ID, '_activated_domain', true);
                                $client_balance = (float) get_post_meta($client->ID, '_balance', true);
                                // Construimos la URL para el historial de este cliente específico
                                $history_url = esc_url(add_query_arg(['tab' => 'historial_cliente', 'cliente_id' => $client->ID], get_permalink()));
                            ?>
                                <tr class="gkyc-clickable-row" data-href="<?php echo $history_url; ?>" data-search-term="<?php echo esc_attr(strtolower($client->post_title . ' ' . get_post_meta($client->ID, '_email', true) . ' ' . $client_domain)); ?>">
                                    
                                    <td>
                                        <strong><?php echo esc_html(strtok($client->post_title, ' (')); ?></strong>
                                    </td>
                                    
                                    <td><?php echo esc_html(get_post_meta($client->ID, '_email', true)); ?></td>
                                    
                                    <td><?php echo esc_html($client_domain ?: 'N/A'); ?></td>
                                    
                                    <td><span class="status-badge status-<?php echo esc_attr($client_status); ?>"><?php echo esc_html($client_status); ?></span></td>
                                    
                                    <td>$<?php echo esc_html(number_format($client_balance, 2)); ?></td>
                                    
                                    <td>
                                        <button class="button button-secondary gkyc-manage-client-btn" 
                                                data-client-id="<?php echo esc_attr($client->ID); ?>" 
                                                data-client-name="<?php echo esc_attr(strtok($client->post_title, ' (')); ?>" 
                                                data-client-balance="<?php echo esc_attr($client_balance); ?>"
                                                data-client-email="<?php echo esc_attr(get_post_meta($client->ID, '_email', true)); ?>"
                                                data-client-website="<?php echo esc_attr($client_domain ?: 'N/A'); ?>">
                                            Gestionar
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="gkyc-pagination" style="margin-top: 20px; text-align: center;">
                    <?php
                    $big = 999999999;
                    echo paginate_links( array(
                        'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                        'format'  => '?paged=%#%',
                        'current' => max( 1, $paged ),
                        'total'   => $stats['sub_clients_query']->max_num_pages,
                        'prev_text' => __('&laquo; Anterior'),
                        'next_text' => __('Siguiente &raquo;'),
                    ) );
                    ?>
                </div>
            </div>
        <?php elseif ($active_tab == 'solicitudes'): ?>
            <div class="dashboard-section">
                <h3>Gestionar Solicitudes de Activación</h3>
                <p>Aquí verás las licencias que tus clientes han solicitado. Verifica que has recibido el pago y luego aprueba la solicitud para activar la licencia.</p>

                <div id="gkyc-request-response-notice" style="margin-top: 20px;"></div>
                <div class="gkyc-client-list-table-wrapper">
                    <table class="gkyc-client-list-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Nombre y Apellido</th>
                                <th>Contacto</th>
                                <th>Plan</th>
                                <th>Referencia de Pago</th>
                                <th>Origen</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="gkyc-requests-tbody">
                            <?php 
                            $solicitudes_query = new WP_Query([ 'post_type' => 'gkyc_act_request', 'post_status' => 'pending', 'meta_query' => [['key' => '_partner_owner_id', 'value' => $partner_client_id]] ]);
                            if ( !$solicitudes_query->have_posts() ) : ?>
                                <tr><td colspan="7" style="text-align: center; padding: 20px;">No tienes solicitudes de licencia pendientes.</td></tr>
                            <?php else : while ($solicitudes_query->have_posts()) : $solicitudes_query->the_post();
                                    $request_id = get_the_ID();
                                    $origin = get_post_meta($request_id, '_request_origin', true);
                                    
                                    $reference = '';
                                    if ($origin === 'woocommerce') {
                                        $order_id = get_post_meta($request_id, '_woocommerce_order_id', true);
                                        $reference = $order_id ? 'Orden WC #' . esc_html($order_id) : 'N/A';
                                    } else {
                                        $transaction_id = get_post_meta($request_id, '_transaction_id', true);
                                        $usdt_hash = get_post_meta($request_id, '_usdt_hash', true);
                                        $reference = !empty($transaction_id) ? 'ID: ' . esc_html($transaction_id) : (!empty($usdt_hash) ? 'Hash: ' . esc_html($usdt_hash) : 'N/A');
                                    }
                            ?>
                                <tr id="request-row-<?php echo esc_attr($request_id); ?>">
                                    <td><?php echo get_the_date('d/m/Y H:i', $request_id); ?></td>
                                    <td><strong><?php echo esc_html(get_post_meta($request_id, '_customer_name', true)); ?></strong></td>
                                    <td><?php echo esc_html(get_post_meta($request_id, '_customer_email', true)); ?></td>
                                    <td><?php echo esc_html(get_post_meta($request_id, '_plan_slug', true)); ?></td>
                                    <td><?php echo $reference; ?></td>
                                    <td><span class="status-badge status-<?php echo $origin === 'woocommerce' ? 'active' : 'pending'; ?>"><?php echo $origin === 'woocommerce' ? 'Automático' : 'Manual'; ?></span></td>
                                    <td style="text-align: right;">
                                        <button class="button button-primary gkyc-approve-request-btn" data-request-id="<?php echo esc_attr($request_id); ?>">Aprobar</button>
                                        <button class="button button-secondary gkyc-reject-request-btn" data-request-id="<?php echo esc_attr($request_id); ?>">Rechazar</button>
                                    </td>
                                </tr>
                            <?php endwhile; endif; wp_reset_postdata(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($active_tab == 'recargas'): ?>
            <div class="dashboard-section">
                <h3>Gestionar Solicitudes de Recarga de Saldo</h3>
                <p>Aquí verás las recargas que tus clientes han solicitado. Verifica que has recibido el pago y luego aprueba la solicitud para acreditar el saldo.</p>
                <div id="gkyc-recharge-response-notice" style="margin-top: 20px;"></div>
                <div class="gkyc-client-list-table-wrapper">
                    <table class="gkyc-client-list-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Monto</th>
                                <th>Referencia de Pago</th>
                                <th>Origen</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recargas_query = new WP_Query([ 'post_type' => 'gkyc_recharge_req', 'post_status' => 'pending', 'meta_query' => [['key' => '_partner_owner_id', 'value' => $partner_client_id]] ]);
                            if ( !$recargas_query->have_posts() ) : ?>
                                <tr><td colspan="6" style="text-align: center; padding: 20px;">No tienes solicitudes de recarga pendientes.</td></tr>
                            <?php else : while ($recargas_query->have_posts()) : $recargas_query->the_post();
                                    $request_id = get_the_ID();
                                    $origin = get_post_meta($request_id, '_request_origin', true);
                                    
                                    $reference = '';
                                    if ($origin === 'woocommerce') {
                                        $order_id = get_post_meta($request_id, '_woocommerce_order_id', true);
                                        $reference = $order_id ? 'Orden WC #' . esc_html($order_id) : 'Fondos Insuficientes';
                                    } else {
                                        $transaction_id = get_post_meta($request_id, '_transaction_id', true);
                                        $usdt_hash = get_post_meta($request_id, '_usdt_hash', true);
                                        $reference = !empty($transaction_id) ? 'ID: ' . esc_html($transaction_id) : (!empty($usdt_hash) ? 'Hash: ' . esc_html($usdt_hash) : 'N/A');
                                    }
                            ?>
                                <tr id="recharge-request-row-<?php echo esc_attr($request_id); ?>">
                                    <td><?php echo get_the_date('d/m/Y H:i', $request_id); ?></td>
                                    <td><strong><?php echo esc_html(get_post_meta($request_id, '_customer_name', true)); ?></strong><br><small><?php echo esc_html(get_post_meta($request_id, '_customer_email', true)); ?></small></td>
                                    <td><strong>$<?php echo esc_html(number_format((float)get_post_meta($request_id, '_recharge_amount', true), 2)); ?></strong></td>
                                    <td><?php echo $reference; ?></td>
                                    <td><span class="status-badge status-<?php echo $origin === 'woocommerce' ? 'active' : 'pending'; ?>"><?php echo $origin === 'woocommerce' ? 'Automático' : 'Manual'; ?></span></td>
                                    <td style="text-align: right;">
                                        <button class="button button-primary gkyc-approve-recharge-btn" data-request-id="<?php echo esc_attr($request_id); ?>">Aprobar</button>
                                        <button class="button button-secondary gkyc-reject-recharge-btn" data-request-id="<?php echo esc_attr($request_id); ?>">Rechazar</button>
                                    </td>
                                </tr>
                            <?php endwhile; endif; wp_reset_postdata(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($active_tab == 'saldos'): 
            $saldos_paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
            
            $sub_clients_query = new WP_Query([
                'post_type' => 'cliente_kyc',
                'posts_per_page' => 20,
                'paged' => $saldos_paged,
                'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]],
                'orderby' => 'title', 
                'order' => 'ASC'
            ]);
            
            $total_clients_query = new WP_Query([
                'post_type' => 'cliente_kyc', 'posts_per_page' => -1,
                'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]],
                'fields' => 'ids'
            ]);
            $total_clients = $total_clients_query->posts;
            
            $master_balance = (float) get_post_meta($partner_client_id, '_balance', true);
            $total_distributed_balance = 0;
            $clients_with_balance_count = 0;
            if (!empty($total_clients)) {
                foreach($total_clients as $client_id) {
                    $client_balance = (float) get_post_meta($client_id, '_balance', true);
                    $total_distributed_balance += $client_balance;
                    if ($client_balance > 0) { $clients_with_balance_count++; }
                }
            }
        ?>
            <div class="dashboard-section">
                <h3>Gestión de Saldos</h3>
                <p>Aquí puedes ver un resumen de tu saldo maestro y el saldo que has distribuido a tus clientes.</p>
                <div class="gkyc-kpi-cards-partner" style="margin-top: 30px;">
                    <div class="gkyc-card-partner"><h3>Tu Saldo Maestro</h3><p>$<?php echo number_format($master_balance, 2); ?></p></div>
                    <div class="gkyc-card-partner"><h3>Saldo Total Distribuido</h3><p>$<?php echo number_format($total_distributed_balance, 2); ?></p></div>
                    <div class="gkyc-card-partner"><h3>Clientes con Saldo</h3><p><?php echo $clients_with_balance_count; ?> / <?php echo count($total_clients); ?></p></div>
                </div>
                <div class="dashboard-section">
                    <h3>Saldos de Sub-Clientes</h3>
                     <div class="gkyc-client-list-table-wrapper">
                        <table class="gkyc-client-list-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Email</th>
                                    <th style="text-align: right;">Saldo Actual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$sub_clients_query->have_posts()): ?>
                                    <tr><td colspan="3" style="text-align: center; padding: 20px;">No tienes sub-clientes asignados.</td></tr>
                                <?php else: while($sub_clients_query->have_posts()): $sub_clients_query->the_post(); 
                                    $client_post = get_post();
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html(strtok($client_post->post_title, ' (')); ?></strong></td>
                                        <td><?php echo esc_html(get_post_meta($client_post->ID, '_email', true)); ?></td>
                                        <td style="text-align: right;">$<?php echo number_format((float)get_post_meta($client_post->ID, '_balance', true), 2); ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="gkyc-pagination" style="margin-top: 20px; text-align: center;">
                        <?php
                        echo paginate_links( array(
                            'base'    => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                            'format'  => '?paged=%#%',
                            'current' => max( 1, $saldos_paged ),
                            'total'   => $sub_clients_query->max_num_pages,
                            'prev_text' => __('&laquo; Anterior'),
                            'next_text' => __('Siguiente &raquo;'),
                            'add_args'  => array('tab' => 'saldos'),
                        ) );
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
            </div>  
        <?php elseif ($active_tab == 'precios'): ?>
            <div class="dashboard-section">
                <h3>Calculadora de Rentabilidad</h3>
                <p>Usa esta herramienta para saber cuánto saldo necesita tu cliente para un monto específico y cuál es tu ganancia neta.</p>
                <form id="gkyc-profit-calculator-form" style="padding: 20px; background: #f9f9f9; border-radius: 8px; display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex-grow: 1; min-width: 200px;"><label for="profit-calc-plan" style="font-weight: bold; display: block; margin-bottom: 5px;">Plan del Cliente</label>
                        <select id="profit-calc-plan" style="width: 100%;"><option value="">-- Seleccionar Plan --</option>
                            <?php
                            $plans_query_calc = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
                            if ($plans_query_calc->have_posts()) :
                                while ($plans_query_calc->have_posts()) : $plans_query_calc->the_post();
                                $plan_post_calc = get_post();
                                if (stripos($plan_post_calc->post_title, 'Partner') !== false) continue;
                                $price_meta_key = '_partner_verification_price_' . $plan_post_calc->ID;
                                // Línea corregida
                                $resale_price = get_post_meta($partner_client_id, '_partner_verification_price_' . $plan_post_calc->ID, true);
                                $price_string = get_post_meta($plan_post_calc->ID, '_price', true);
                                preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
                                $partner_cost = isset($matches[0]) ? (float) $matches[0] : 0.0;
                                ?>
                                <option value="<?php echo esc_attr($plan_post_calc->post_name); ?>" data-resale-price="<?php echo esc_attr($resale_price); ?>" data-partner-cost="<?php echo esc_attr($partner_cost); ?>"><?php echo esc_html($plan_post_calc->post_title); ?></option>
                            <?php endwhile; wp_reset_postdata(); endif; ?>
                        </select>
                    </div>
                    <div style="flex-grow: 1; min-width: 200px;"><label for="profit-calc-amount" style="font-weight: bold; display: block; margin-bottom: 5px;">Monto Recibido (USD)</label><input type="number" id="profit-calc-amount" placeholder="Ej: 100.00" step="0.01" min="1" style="width: 100%;"></div>
                    <div><button type="submit" class="button button-secondary">Calcular</button></div>
                </form>
                <div id="gkyc-profit-results" style="margin-top: 20px; padding: 20px; background: #f0f8ff; border: 1px solid #cce5ff; border-radius: 8px; display: none;"></div>
            </div>
            <div class="dashboard-section">
                <h3>Configuración de Precios de Reventa</h3>
                <p>Define los precios a los que venderás las verificaciones a tus sub-clientes.</p>
                <form id="gkyc-partner-prices-form">
                    <table class="form-table">
                        <tbody>
                        <?php
                        $plans_query_for_prices = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
                        if ($plans_query_for_prices->have_posts()) :
                            while ($plans_query_for_prices->have_posts()) : $plans_query_for_prices->the_post();
                                $plan_post = get_post();
                                if (stripos($plan_post->post_title, 'Partner') !== false) continue;
                                $license_price_meta_key = '_partner_license_price_' . $plan_post->ID;
                                $saved_license_price = get_post_meta($partner_client_id, $license_price_meta_key, true);
                                $verification_price_meta_key = '_partner_verification_price_' . $plan_post->ID;
                                $saved_verification_price = get_post_meta($partner_client_id, $verification_price_meta_key, true);
                                ?>
                                <tr style="border-top: 1px solid #f0f0f0;">
                                    <th scope="row"><label for="<?php echo esc_attr($license_price_meta_key); ?>">Precio Venta Licencia (<?php echo esc_html($plan_post->post_title); ?>)</label></th>
                                    <td><input type="number" step="0.01" min="0" id="<?php echo esc_attr($license_price_meta_key); ?>" name="partner_license_prices[<?php echo esc_attr($license_price_meta_key); ?>]" value="<?php echo esc_attr($saved_license_price); ?>" placeholder="Ej: 150.00" class="regular-text"></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="<?php echo esc_attr($verification_price_meta_key); ?>">Precio por Verificación (<?php echo esc_html($plan_post->post_title); ?>)</label></th>
                                    <td><input type="number" step="0.01" min="0" id="<?php echo esc_attr($verification_price_meta_key); ?>" name="partner_verification_prices[<?php echo esc_attr($verification_price_meta_key); ?>]" value="<?php echo esc_attr($saved_verification_price); ?>" placeholder="Ej: 1.25" class="regular-text"></td>
                                </tr>
                            <?php endwhile; wp_reset_postdata(); endif; ?>
                        </tbody>
                    </table>
                    <?php wp_nonce_field('gkyc_partner_update_prices_nonce', 'gkyc_security_nonce'); ?>
                    <button type="submit" class="button button-primary">Guardar Cambios de Precios</button>
                    <span id="gkyc-prices-form-notice" style="margin-left: 10px; font-weight: 600;"></span>
                </form>
            </div>
        
        <?php elseif ($active_tab == 'libro_contable'): 
            $ledger_paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

            $ledger_query = new WP_Query([
                'post_type' => 'gkyc_transaction', 'posts_per_page' => 20, 'paged' => $ledger_paged,
                'meta_query' => [
                    'relation' => 'AND',
                    [ 'key' => '_client_id', 'value' => $partner_client_id ],
                    [ 'key' => '_transaction_type', 'value' => ['Recarga de Saldo', 'Recarga Simple', 'Recarga (Valor Facial)', 'Bono por Recarga', 'Venta de Saldo', 'Transferencia Saliente', 'Costo Venta de Saldo'], 'compare' => 'IN' ]
                ],
                'orderby' => 'date', 'order' => 'DESC'
            ]);
            
            $running_balance = ($ledger_paged == 1) ? (float) get_post_meta($partner_client_id, '_balance', true) : 0;
        ?>
            <div class="dashboard-section">
                <h3>Mi Registro de Saldo Maestro</h3>
                <div class="gkyc-client-list-table-wrapper">
                    <table class="gkyc-client-list-table">
                        <thead>
                            <tr>
                                <th>Fecha</th><th>Descripción</th>
                                <th style="text-align: right;">Ingreso</th><th style="text-align: right;">Bono</th>
                                <th style="text-align: right;">Costo Plataforma</th><th style="text-align: right;">Ganancia Neta</th>
                                <th style="text-align: right;">Saldo Resultante</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( !$ledger_query->have_posts() ) : ?>
                                <tr><td colspan="7" style="text-align: center; padding: 20px;">No tienes transacciones registradas.</td></tr>
                            <?php else : while ( $ledger_query->have_posts() ) : $ledger_query->the_post(); 
                                $transaction_type = get_post_meta(get_the_ID(), '_transaction_type', true);
                                $amount = (float) get_post_meta(get_the_ID(), '_transaction_amount', true);
                                $bonus = (float) get_post_meta(get_the_ID(), '_transaction_bonus', true);
                                $profit = (float) get_post_meta(get_the_ID(), '_transaction_profit', true);
                                
                                // Definimos los tipos de transacción que son egresos
                                $egress_types = ['Venta de Saldo', 'Transferencia Saliente', 'Costo Venta de Saldo'];
                            ?>
                                <tr>
                                    <td><?php echo get_the_date('d/m/Y H:i'); ?></td>
                                    <td><?php the_title(); ?></td>
                                    
                                    <td style="text-align: right; color: #28a745; font-weight: bold;"><?php if (in_array($transaction_type, ['Recarga de Saldo', 'Recarga (Valor Facial)', 'Recarga Simple'])) { echo '+$' . esc_html(number_format($amount, 2)); } ?></td>
                                    <td style="text-align: right; color: #007cba; font-weight: bold;"><?php if ($bonus > 0) { echo '+$' . esc_html(number_format($bonus, 2)); } ?></td>
                                    
                                    <td style="text-align: right; color: #dc3545; font-weight: bold;"><?php if (in_array($transaction_type, $egress_types)) { echo '-$' . esc_html(number_format($profit > 0 ? $profit : abs($amount), 2)); } ?></td>
                                    <td style="text-align: right; color: #28a745; font-weight: bold;"><?php if (in_array($transaction_type, $egress_types) && $profit > 0) { echo '+$' . esc_html(number_format(abs($amount), 2)); } ?></td>

                                    <td style="text-align: right; font-weight: bold;">
                                        <?php if ($ledger_paged == 1 && $running_balance > 0) {
                                            echo '$' . esc_html(number_format($running_balance, 2));
                                            $running_balance -= ($transaction_type === 'Venta de Saldo' || $transaction_type === 'Recarga de Saldo') ? ($amount + $bonus) : $amount;
                                        } else { echo 'N/A'; } ?>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="gkyc-pagination" style="margin-top: 20px; text-align: center;">
                    <?php echo paginate_links( array( 'base' => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ), 'format' => '?paged=%#%', 'current' => max( 1, $ledger_paged ), 'total' => $ledger_query->max_num_pages, 'prev_text' => __('&laquo; Anterior'), 'next_text' => __('Siguiente &raquo;'), 'add_args' => array('tab' => 'libro_contable') ) ); wp_reset_postdata(); ?>
                </div>
            </div>
            <?php elseif ($active_tab == 'marca'): ?>
                <div class="dashboard-section">
                    <h3>Configuración de Marca Blanca</h3>
                    <p>Desde aquí puedes personalizar la apariencia de tu marca y configurar tus métodos de pago.</p>

                    <form id="gkyc-partner-branding-form">
                        
                        <div class="gkyc-form-grid">
                            <div class="gkyc-form-field">
                                <label for="partner_website_url">Página Web Principal</label>
                                <input type="url" id="partner_website_url" name="partner_website_url" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_website_url', true)); ?>" 
                                    placeholder="https://sitiopartner.com">
                                <p class="description">La página web principal de tu negocio (opcional).</p>
                            </div>
                            <div class="gkyc-form-field">
                                <label for="partner_recharge_url">URL de Recarga para Clientes</label>
                                <input type="url" id="partner_recharge_url" name="partner_recharge_url" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_recharge_url', true)); ?>" 
                                    placeholder="https://tu-sitio.com/recargar-saldo">
                                <p class="description">La página a la que tus clientes serán enviados para recargar saldo.</p>
                            </div>

                            <div class="gkyc-form-field">
                                <label for="partner_support_email">Email de Soporte (para notificaciones)</label>
                                <input type="email" id="partner_support_email" name="partner_support_email" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_support_email', true)); ?>" 
                                    placeholder="soporte@misitio.com">
                                <p class="description">A este correo llegarán las notificaciones de nuevas ventas.</p>
                            </div>
                            <div class="gkyc-form-field">
                                <label for="partner_support_whatsapp">WhatsApp de Soporte</label>
                                <input type="text" id="partner_support_whatsapp" name="partner_support_whatsapp" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_support_whatsapp', true)); ?>" 
                                    placeholder="584121234567">
                                <p class="description">Tu número para el botón flotante de soporte.</p>
                            </div>

                            <div class="gkyc-form-field">
                                <label for="partner_logo_1x1_url">Icono (1x1)</label>
                                <?php $logo_1x1_url = get_post_meta($partner_client_id, '_partner_logo_1x1_url', true); ?>
                                <div class="gkyc-media-uploader">
                                    <input type="text" name="partner_logo_1x1_url" id="partner_logo_1x1_url" value="<?php echo esc_url($logo_1x1_url); ?>" class="regular-text" readonly>
                                    <button type="button" class="button gkyc-upload-button" data-input-id="partner_logo_1x1_url" data-preview-id="gkyc_logo_1x1_preview">Seleccionar</button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($logo_1x1_url); ?>" id="gkyc_logo_1x1_preview" class="gkyc-logo-preview" style="<?php if (!$logo_1x1_url) echo 'display: none;'; ?>">
                                </div>
                                <p class="description">Para el Plugin Cliente y App de Verificación (ICON). Cuadrado (ej: 512x512px).</p>
                            </div>
                            <div class="gkyc-form-field">
                                <label for="partner_logo_2x1_url">Logo Ancho (2x1)</label>
                                <?php $logo_2x1_url = get_post_meta($partner_client_id, '_partner_logo_2x1_url', true); ?>
                                <div class="gkyc-media-uploader">
                                    <input type="text" name="partner_logo_2x1_url" id="partner_logo_2x1_url" value="<?php echo esc_url($logo_2x1_url); ?>" class="regular-text" readonly>
                                    <button type="button" class="button gkyc-upload-button" data-input-id="partner_logo_2x1_url" data-preview-id="gkyc_logo_2x1_preview">Seleccionar</button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($logo_2x1_url); ?>" id="gkyc_logo_2x1_preview" class="gkyc-logo-preview wide" style="<?php if (!$logo_2x1_url) echo 'display: none;'; ?>">
                                </div>
                                <p class="description">Para la App de Verificación (LOGO Principal). Panorámico (ej: 1024x512px).</p>
                            </div>
                            <div class="gkyc-form-field">
                                <label for="partner_favicon_url">Favicon (1x1)</label>
                                <?php $favicon_url = get_post_meta($partner_client_id, '_partner_favicon_url', true); ?>
                                <div class="gkyc-media-uploader">
                                    <input type="text" name="partner_favicon_url" id="partner_favicon_url" value="<?php echo esc_url($favicon_url); ?>" class="regular-text" readonly>
                                    <button type="button" class="button gkyc-upload-button" data-input-id="partner_favicon_url" data-preview-id="gkyc_favicon_preview">Seleccionar</button>
                                </div>
                                <div style="margin-top: 10px;">
                                    <img src="<?php echo esc_url($favicon_url); ?>" id="gkyc_favicon_preview" class="gkyc-logo-preview favicon" style="<?php if (!$favicon_url) echo 'display: none;'; ?>">
                                </div>
                                <p class="description">Para la App de Verificación (Favicon). Cuadrado (ej: 48x48px).</p>
                            </div>
                        </div>

                        <h3 style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px;">Métodos de Pago</h3>
                        <div class="gkyc-form-grid">
                            <div class="gkyc-form-field">
                                <label for="partner_paypal_link">Enlace de PayPal</label>
                                <input type="url" id="partner_paypal_link" name="partner_paypal_link" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_paypal_link', true)); ?>" 
                                    placeholder="https://paypal.me/tu-usuario">
                            </div>
                            <div class="gkyc-form-field">
                                <label for="partner_usdt_wallet">Wallet USDT (TRC20)</label>
                                <input type="text" id="partner_usdt_wallet" name="partner_usdt_wallet" class="regular-text" 
                                    value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_usdt_wallet', true)); ?>" 
                                    placeholder="T...WalletAddress...">
                            </div>

                            <div class="gkyc-form-field" style="grid-column: 1 / -1;">
                                <label for="partner_other_payments">Otros Métodos de Pago e Instrucciones</label>
                                <?php
                                $other_payments_content = get_post_meta($partner_client_id, '_partner_other_payments', true);
                                wp_editor($other_payments_content, 'partner_other_payments', [
                                    'textarea_name' => 'partner_other_payments',
                                    'media_buttons' => false,
                                    'textarea_rows' => 8,
                                    'editor_class' => 'gkyc-wp-editor'
                                ]);
                                ?>
                                <p class="description">Usa este espacio para añadir instrucciones de pago para Pago Móvil, Yape, transferencias bancarias, etc.</p>
                            </div>
                        </div>
                        
                        <p class="submit">
                        <h3 style="margin-top: 40px; border-top: 1px solid #eee; padding-top: 30px;">Configuración de Saldo de Regalo Automático</h3>
                            <div class="gkyc-form-grid">
                                <div class="gkyc-form-field">
                                    <label>Activar Saldo de Regalo por Licencia</label>
                                    <p class="description" style="margin-bottom: 15px;">Si activas esta opción, se le asignará automáticamente un monto de saldo a cada nuevo cliente cuya licencia apruebes. El monto se debitará de tu Saldo Maestro.</p>

                                    <?php $auto_credit_enabled = get_post_meta($partner_client_id, '_partner_auto_credit_enabled', true); ?>
                                    <label class="gkyc-switch">
                                        <input type="checkbox" id="partner_auto_credit_enabled" name="partner_auto_credit_enabled" value="yes" <?php checked($auto_credit_enabled, 'yes'); ?>>
                                        <span class="gkyc-slider round"></span>
                                    </label>
                                </div>
                                <div class="gkyc-form-field">
                                    <label for="partner_auto_credit_amount">Monto a Acreditar Automáticamente (USD)</label>
                                    <input type="number" step="0.01" min="0" id="partner_auto_credit_amount" name="partner_auto_credit_amount" class="regular-text" 
                                        value="<?php echo esc_attr(get_post_meta($partner_client_id, '_partner_auto_credit_amount', true)); ?>" 
                                        placeholder="Ej: 10.00">
                                    <p class="description">Introduce el monto que se le regalará a cada nuevo cliente.</p>
                                </div>
                            </div>

                            <style>
                            /* Estilos para el interruptor (toggle switch) */
                            .gkyc-switch { position: relative; display: inline-block; width: 60px; height: 34px; }
                            .gkyc-switch input { opacity: 0; width: 0; height: 0; }
                            .gkyc-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; }
                            .gkyc-slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; }
                            input:checked + .gkyc-slider { background-color: #28a745; }
                            input:focus + .gkyc-slider { box-shadow: 0 0 1px #28a745; }
                            input:checked + .gkyc-slider:before { transform: translateX(26px); }
                            .gkyc-slider.round { border-radius: 34px; }
                            .gkyc-slider.round:before { border-radius: 50%; }
                            </style>
                            <button type="submit" class="button button-primary">Guardar Cambios de Marca</button>
                            <span id="gkyc-branding-form-notice" style="margin-left: 10px; font-weight: 600;"></span>
                        </p>
                    </form>
                </div>
            <?php elseif ( $active_tab == 'historial_cliente' && !empty($_GET['cliente_id']) ) : 
            $sub_client_id = intval($_GET['cliente_id']);
            
            // =================================================================
            // ===== INICIO: VERIFICACIÓN DE SEGURIDAD CRÍTICA =====
            // =================================================================
            // Nos aseguramos de que el cliente que se quiere ver PERTENECE a este socio.
            $owner_id = get_post_meta($sub_client_id, '_reseller_owner_id', true);
            if ( $owner_id != $partner_client_id ) {
                echo '<div class="gkyc-notice error">No tienes permiso para ver el historial de este cliente.</div>';
            } else {
            // ===============================================================
            // ===== FIN: VERIFICACIÓN DE SEGURIDAD CRÍTICA =====
            // ===============================================================

                $sub_client_post = get_post($sub_client_id);
                $sub_client_balance = get_post_meta($sub_client_id, '_balance', true);
                $sub_client_email = get_post_meta($sub_client_id, '_email', true);

                $history_query = new WP_Query([
                    'post_type' => 'gkyc_transaction',
                    'posts_per_page' => -1,
                    'meta_key' => '_client_id',
                    'meta_value' => $sub_client_id,
                    'orderby' => 'date',
                    'order' => 'DESC'
                ]);
            ?>
                <div class="dashboard-section">
                    <a href="<?php echo esc_url(add_query_arg('tab', 'dashboard', get_permalink())); ?>" style="text-decoration: none; margin-bottom: 20px; display: inline-block;">&laquo; Volver a la lista de clientes</a>
                    <h3>Historial de Cliente: <?php echo esc_html($sub_client_post->post_title); ?></h3>

                    <div id="gkyc-client-summary" style="margin-top: 20px; margin-bottom: 30px; background: #f9f9f9; padding: 20px; border-left: 4px solid #007cba;">
                        <h4 style="margin: 0; font-size: 1.2em;">Saldo Actual: <strong style="color: #007cba;">$<?php echo esc_html(number_format((float)$sub_client_balance, 2)); ?></strong></h4>
                        <p style="margin: 5px 0 0 0; color: #555;">Email: <?php echo esc_html($sub_client_email); ?></p>
                    </div>

                    <div class="gkyc-client-list-table-wrapper">
                        <table class="gkyc-client-list-table">
                            <thead><tr><th>Fecha</th><th>Descripción</th><th>Tipo</th><th>Monto</th><th>Estado</th></tr></thead>
                            <tbody>
                                <?php if (!$history_query->have_posts()) : ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 20px;">Este cliente aún no tiene transacciones.</td></tr>
                                <?php else : while ($history_query->have_posts()) : $history_query->the_post(); ?>
                                    <tr>
                                        <td><?php echo get_the_date('d/m/Y H:i'); ?></td>
                                        <td><?php the_title(); ?></td>
                                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_transaction_type', true)); ?></td>
                                        <td>
                                            <?php
                                            $amount = (float) get_post_meta(get_the_ID(), '_transaction_amount', true);
                                            if ($amount > 0) { echo '<strong style="color: #28a745;">+' . esc_html(number_format($amount, 2)) . '</strong>';
                                            } else if ($amount < 0) { echo '<strong style="color: #dc3545;">' . esc_html(number_format($amount, 2)) . '</strong>';
                                            } else { echo '$0.00'; }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html(get_post_meta(get_the_ID(), '_transaction_status', true)); ?></td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php wp_reset_postdata(); ?>
                </div>
            <?php 
            } // Cierre del 'else' de la verificación de seguridad
            endif; // Cierre del if/elseif de las pestañas
            ?>
    <div id="gkyc-generate-license-modal" class="gkyc-modal">
        <div class="gkyc-modal-content">
            <span class="gkyc-modal-close">&times;</span>
            <h2>Generar Nueva Licencia</h2>
            <div id="gkyc-modal-notice"></div>
            <div class="gkyc-modal-field"><label for="gkyc-new-client-name">Nombre del Cliente</label><input type="text" id="gkyc-new-client-name" required></div>
            <div class="gkyc-modal-field"><label for="gkyc-new-client-email">Email del Cliente</label><input type="email" id="gkyc-new-client-email" required></div>
            <div class="gkyc-modal-field"><label for="gkyc-new-client-website">Sitio Web del Cliente (Opcional)</label><input type="url" id="gkyc-new-client-website" placeholder="https://cliente.com"></div>
            <div class="gkyc-modal-field"><label for="gkyc-new-client-phone">Teléfono del Cliente</label><input type="tel" id="gkyc-new-client-phone" required></div>
            <div class="gkyc-modal-field"><label for="gkyc-new-license-type">Tipo de Licencia a Asignar</label>
                <select id="gkyc-new-license-type" required>
                    <option value="">-- Seleccionar Plan --</option>
                    <?php 
                    $plans_query_modal = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1]);
                    if ($plans_query_modal->have_posts()) : 
                        while ($plans_query_modal->have_posts()) : $plans_query_modal->the_post();
                            if (stripos(get_the_title(), 'Partner') === false) :
                                echo '<option value="' . esc_attr(get_the_title()) . '">' . get_the_title() . '</option>';
                            endif;
                        endwhile; 
                        wp_reset_postdata(); 
                    endif; 
                    ?>
                </select>
            </div>
            <button id="gkyc-submit-new-license" class="button button-primary">Crear Licencia</button>
        </div>
    </div>
    
    <div id="gkyc-transfer-balance-modal" class="gkyc-modal">
        <div class="gkyc-modal-content">
            <span class="gkyc-modal-close">&times;</span>
            <h2>Gestionar Saldo de Cliente</h2>
            <div id="gkyc-transfer-modal-notice"></div>
            <div class="gkyc-transfer-info" style="text-align: left; background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p style="margin: 5px 0;"><strong>Cliente:</strong> <span id="gkyc-transfer-client-name"></span></p>
                <p style="margin: 5px 0;"><strong>Email:</strong> <span id="gkyc-transfer-client-email"></span></p>
                <p style="margin: 5px 0;"><strong>Sitio Web:</strong> <span id="gkyc-transfer-client-website"></span></p>
                <p style="margin: 5px 0;"><strong>Saldo Actual:</strong> <span id="gkyc-transfer-client-balance" style="font-weight: bold; font-size: 1.2em;"></span></p>
            </div>
            <div class="gkyc-modal-field">
                <label for="gkyc-transfer-amount">Monto que el Cliente Recibirá (USD)</label>
                <input type="number" id="gkyc-transfer-amount" step="0.01" min="1" required>
            </div>
            
            <div id="gkyc-transfer-calculator-results" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border: 1px solid #cce5ff; border-radius: 8px; display: none;">
                <h4 style="margin: 0 0 10px 0;">Desglose de la Transacción:</h4>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 14px;">
                    <li style="margin-bottom: 8px;">Verificaciones que obtendrá el cliente: <strong id="gkyc-calc-verifications">--</strong></li>
                    <li style="margin-bottom: 8px;">Costo para ti (débito a Saldo Maestro): <strong id="gkyc-calc-cost" style="color: #dc3545;">$--.--</strong></li>
                    <li style="font-size: 1.1em;">Tu Ganancia Neta: <strong id="gkyc-calc-profit" style="color: #28a745;">$--.--</strong></li>
                </ul>
            </div>
            <input type="hidden" id="gkyc-transfer-client-id" value="">
            <button id="gkyc-submit-transfer-btn" class="button button-primary" style="margin-top: 20px; width: 100%;">Confirmar Transferencia</button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtiene TODAS las estadísticas para el panel de un Partner específico.
 * VERSIÓN 2.1 - Añadida lógica de paginación para los sub-clientes.
 */
function gkyc_mothership_get_partner_stats($partner_client_id, $paged = 1) {
    // 1. Inicializamos la estructura de datos que vamos a devolver.
    $stats = [
        'saldo_maestro'       => (float) get_post_meta($partner_client_id, '_balance', true),
        'limite_licencias'    => get_post_meta($partner_client_id, '_license_limit', true) ?: 'Ilimitadas',
        'sub_clients'         => [], // Para la lista de la página actual
        'clientes_asignados'  => 0,  // El total de clientes
        'saldo_distribuido'   => 0,  // El total de saldo
        'sub_clients_query'   => null, // Para guardar el objeto de la consulta para la paginación
    ];

    // 2. HACEMOS DOS CONSULTAS PARA SER MÁS EFICIENTES
    
    // Consulta A: Obtenemos solo los clientes de la PÁGINA ACTUAL para mostrar en la tabla.
    $sub_clients_query = new WP_Query([
        'post_type'      => 'cliente_kyc',
        'posts_per_page' => 10, // Mostramos 10 clientes por página
        'paged'          => $paged, // Le decimos qué página mostrar
        'meta_query'     => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]],
        'orderby'        => 'title',
        'order'          => 'ASC'
    ]);
    $stats['sub_clients'] = $sub_clients_query->get_posts();
    $stats['sub_clients_query'] = $sub_clients_query; // Guardamos el objeto completo para usarlo en la paginación

    // Consulta B: Obtenemos el TOTAL de clientes para los KPIs (tarjetas superiores).
    $total_clients_query = new WP_Query([
        'post_type' => 'cliente_kyc', 
        'posts_per_page' => -1, 
        'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]], 
        'fields' => 'ids' // Solo necesitamos los IDs, es más rápido
    ]);
    $all_sub_client_ids = $total_clients_query->posts;

    // 3. ACTUALIZAMOS LOS KPIs CON LOS TOTALES
    $stats['clientes_asignados'] = $total_clients_query->found_posts;
    $stats['licencias_disponibles'] = is_numeric($stats['limite_licencias']) ? ($stats['limite_licencias'] - $stats['clientes_asignados']) : 'Ilimitadas';

    // 4. CALCULAMOS EL SALDO DISTRIBUIDO TOTAL
    if (!empty($all_sub_client_ids)) {
        foreach ($all_sub_client_ids as $sub_client_id) {
            $stats['saldo_distribuido'] += (float) get_post_meta($sub_client_id, '_balance', true);
        }
    }

    // 5. LÓGICA DE GRÁFICOS (usa el total de IDs de clientes para ser precisa)
    $stats['status_chart'] = [ 'labels' => ['Aprobadas', 'Rechazadas', 'En Revisión'], 'data' => [0, 0, 0] ];
    $stats['activity_chart'] = [ 'labels' => [], 'data' => [] ];

    if (!empty($all_sub_client_ids)) {
        $transactions_query = new WP_Query([ 
            'post_type' => 'gkyc_transaction', 
            'posts_per_page' => -1, 
            'meta_query' => [ [ 'key' => '_client_id', 'value' => $all_sub_client_ids, 'compare' => 'IN' ] ] 
        ]);
        if ($transactions_query->have_posts()) {
            $activity_by_day = [];
            $thirty_days_ago = strtotime('-30 days');
            while ($transactions_query->have_posts()) {
                $transactions_query->the_post();
                $status = get_post_meta(get_the_ID(), '_transaction_status', true);
                if ($status === 'Approved') $stats['status_chart']['data'][0]++;
                if ($status === 'Rejected' || $status === 'Declined') $stats['status_chart']['data'][1]++;
                if ($status === 'In Review') $stats['status_chart']['data'][2]++;
                
                $transaction_date = get_the_date('Y-m-d');
                if (strtotime($transaction_date) >= $thirty_days_ago) {
                    if (!isset($activity_by_day[$transaction_date])) { $activity_by_day[$transaction_date] = 0; }
                    $activity_by_day[$transaction_date]++;
                }
            }
            wp_reset_postdata();
            
            for ($i = 29; $i >= 0; $i--) {
                $date_key = date('Y-m-d', strtotime("-$i days"));
                $stats['activity_chart']['labels'][] = date('d M', strtotime($date_key));
                $stats['activity_chart']['data'][] = $activity_by_day[$date_key] ?? 0;
            }
        }
    }
    
    // 6. DEVOLVEMOS TODOS LOS DATOS
    return $stats;
}



/**
 * ===================================================================
 * FUNCIONALIDAD DE SUPERVISIÓN DE PARTNERS (VERSIÓN FINAL UNIFICADA)
 * Tarea 2.2 del Plan de Desarrollo
 * ===================================================================
 */

// 1. Añade la nueva columna "Asignado a" en la lista de clientes.
add_filter('manage_cliente_kyc_posts_columns', 'gkyc_add_partner_owner_column');
function gkyc_add_partner_owner_column($columns) {
    $new_columns = [];
    foreach ($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['reseller_owner'] = 'Asignado a (Partner)';
        }
    }
    return $new_columns;
}

// 2. Muestra el contenido mejorado en la nueva columna.
add_action('manage_cliente_kyc_posts_custom_column', 'gkyc_show_partner_owner_column_content', 10, 2);
function gkyc_show_partner_owner_column_content($column, $post_id) {
    if ($column == 'reseller_owner') {
        $partner_id = get_post_meta($post_id, '_reseller_owner_id', true);
        if (!empty($partner_id) && is_numeric($partner_id)) {
            $partner_title = get_the_title($partner_id);
            $edit_link = get_edit_post_link($partner_id);
            echo '<strong><a href="' . esc_url($edit_link) . '">' . esc_html($partner_title) . '</a></strong>';
        } else {
            $license_type = get_post_meta($post_id, '_license_type', true);
            if (stripos($license_type, 'Partner') !== false) {
                 echo '— (Este es un Partner) —';
            } else {
                 echo 'Cliente Directo';
            }
        }
    }
}

/**
 * ===================================================================
 * TAREA 2.3: MODELO DE LICENCIAS POR NIVELES
 * Paso 1: Añadir campo personalizado a productos de WooCommerce.
 * ===================================================================
 */



/**
 * Dibuja el contenido HTML de la página del Panel de Negocio (Cabina de Mando).
 * v6.0 - Versión final con todos los KPIs, gráficos y tablas de inteligencia.
 */
function gkyc_mothership_render_dashboard_page() {
    // --- Lógica de Fechas ---
    $current_period = isset($_GET['period']) ? sanitize_key($_GET['period']) : 'this_month';
    $end_date = date('Y-m-d');
    switch ($current_period) {
        case 'today':
            $start_date = date('Y-m-d'); $title = 'Hoy'; break;
        case 'last_7_days':
            $start_date = date('Y-m-d', strtotime('-6 days')); $title = 'Últimos 7 Días'; break;
        case 'this_quarter':
            $current_month = date('n'); $current_quarter = ceil($current_month / 3);
            $first_month_of_quarter = ($current_quarter - 1) * 3 + 1;
            $start_date = date('Y-m-d', strtotime(date('Y') . '-' . $first_month_of_quarter . '-01'));
            $title = 'Este Trimestre'; break;
        case 'last_6_months':
            $start_date = date('Y-m-01', strtotime('-5 months')); $title = 'Últimos 6 Meses'; break;
        case 'last_month':
            $start_date = date('Y-m-01', strtotime('last month')); $end_date = date('Y-m-t', strtotime('last month'));
            $title = 'Mes Pasado'; break;
        case 'this_month': default:
            $start_date = date('Y-m-01'); $title = 'Este Mes'; break;
    }
    
    // Llamamos a nuestros dos motores de cálculo con el rango de fechas
    $financials = gkyc_calculate_financials($start_date, $end_date);
    $stats = gkyc_mothership_get_business_stats($start_date, $end_date);
    $active_installs = gkyc_get_active_install_counts();

    ?>
    <div class="wrap">
        <h1>Panel de Negocio de Guardián KYC</h1>
        
        <div id="gkyc-business-dashboard">
            <style>
                .gkyc-period-filters { margin: 20px 0; } .gkyc-period-filters a { text-decoration: none; padding: 8px 15px; background: #f0f0f1; border: 1px solid #ccc; margin-right: 5px; border-radius: 4px; } .gkyc-period-filters a.current { background: #007cba; color: #fff; border-color: #007cba; font-weight: bold; } .gkyc-mothership-kpi-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; } .gkyc-mothership-card { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); } .gkyc-mothership-card h3 { margin: 0 0 10px 0; font-size: 15px; color: #555; } .gkyc-mothership-card p { margin: 0; font-size: 32px; font-weight: 600; line-height: 1; color: #1d2327; } .gkyc-mothership-card.profit { border-left: 5px solid #28a745; } .gkyc-mothership-card.revenue { border-left: 5px solid #17a2b8; } .gkyc-mothership-card.costs { border-left: 5px solid #dc3545; } .gkyc-mothership-card.payments { border-left: 5px solid #6f42c1; } .gkyc-mothership-card.clients { border-left: 5px solid #2271b1; } .gkyc-mothership-card.partners { border-left: 5px solid #8E44AD; } .gkyc-mothership-layout-container { display: grid; grid-template-columns: 1fr; gap: 20px; margin-bottom: 20px; } .gkyc-layout-grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; } .gkyc-mothership-widget-card { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); } .gkyc-mothership-widget-card h3 { margin: 0; padding: 20px; font-size: 16px; border-bottom: 1px solid #eee;} .gkyc-mothership-widget-card .inside { padding: 20px; } .gkyc-mothership-widget-card table { width: 100%; } .gkyc-mothership-widget-card th, .gkyc-mothership-widget-card td { padding: 10px 15px; text-align: left; border-bottom: 1px solid #f0f0f0; } .gkyc-mothership-widget-card tbody tr:last-child td { border: none; }
            </style>

            <div class="gkyc-period-filters">
                <a href="?page=gkyc_business_dashboard&period=today" class="<?php echo $current_period == 'today' ? 'current' : ''; ?>">Hoy</a>
                <a href="?page=gkyc_business_dashboard&period=last_7_days" class="<?php echo $current_period == 'last_7_days' ? 'current' : ''; ?>">Últimos 7 Días</a>
                <a href="?page=gkyc_business_dashboard&period=this_month" class="<?php echo $current_period == 'this_month' ? 'current' : ''; ?>">Este Mes</a>
                <a href="?page=gkyc_business_dashboard&period=last_month" class="<?php echo $current_period == 'last_month' ? 'current' : ''; ?>">Mes Pasado</a>
                <a href="?page=gkyc_business_dashboard&period=this_quarter" class="<?php echo $current_period == 'this_quarter' ? 'current' : ''; ?>">Este Trimestre</a>
                <a href="?page=gkyc_business_dashboard&period=last_6_months" class="<?php echo $current_period == 'last_6_months' ? 'current' : ''; ?>">Últimos 6 Meses</a>
            </div>

            <h2>Rendimiento Financiero (<?php echo $title; ?>)</h2>
            <div class="gkyc-mothership-kpi-cards">
                <div class="gkyc-mothership-card profit"><h3>Ganancia Neta</h3><p>$<?php echo esc_html(number_format($financials['totals']['ganancia_neta'], 2)); ?></p></div>
                <div class="gkyc-mothership-card revenue"><h3>Ingresos</h3><p>$<?php echo esc_html(number_format($financials['totals']['ingresos'], 2)); ?></p></div>
                <div class="gkyc-mothership-card costs"><h3>Costos (Deuda a Didit)</h3><p>$<?php echo esc_html(number_format($financials['totals']['costos'], 2)); ?></p></div>
                <div class="gkyc-mothership-card payments"><h3>Pagos a Didit</h3><p>$<?php echo esc_html(number_format($financials['totals']['pagos_a_didit'], 2)); ?></p></div>
            </div>

            <div class="gkyc-mothership-layout-container">
                <div class="gkyc-mothership-widget-card">
                    <h3>Desglose de Rentabilidad (<?php echo $title; ?>)</h3>
                    <div class="inside"><canvas id="gkyc-financials-chart" height="100"></canvas></div>
                </div>
            </div>

            <h2>Crecimiento y Ecosistema (<?php echo $title; ?>)</h2>
            <div class="gkyc-mothership-kpi-cards">
                <div class="gkyc-mothership-card clients"><h3>Nuevos Clientes</h3><p><?php echo esc_html($stats['new_clients_count']); ?></p></div>
                <div class="gkyc-mothership-card partners"><h3>Nuevos Partners</h3><p><?php echo esc_html($stats['new_partners_count']); ?></p></div>
                <div class="gkyc-mothership-card profit"><h3>Instalaciones Activas (Directas)</h3><p><?php echo esc_html($active_installs['direct_actives']); ?></p></div>
                <div class="gkyc-mothership-card payments"><h3>Instalaciones Activas (Sub-Clientes)</h3><p><?php echo esc_html($active_installs['subclient_actives']); ?></p></div>
            </div>
            
            <div class="gkyc-layout-grid-2-col">
                <div class="gkyc-mothership-widget-card">
                    <h3>Distribución de Clientes por Plan (Total Global)</h3>
                    <div class="inside"><canvas id="gkyc-plans-chart"></canvas></div>
                </div>
                <div class="gkyc-mothership-widget-card">
                    <h3>Top 10 Partners por Saldo</h3>
                    <div class="inside">
                        <table><tbody>
                        <?php if(empty($stats['top_partners'])): ?>
                            <tr><td>No hay partners con saldo.</td></tr>
                        <?php else: foreach($stats['top_partners'] as $client): ?>
                            <tr><td><?php echo esc_html(strtok($client['name'], ' (')); ?></td><td style="text-align:right;"><strong>$<?php echo esc_html(number_format($client['balance'], 2)); ?></strong></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody></table>
                    </div>
                </div>
            </div>

            <div class="gkyc-mothership-layout-container">
                <div class="gkyc-mothership-widget-card">
                    <h3>Top 10 Clientes Directos por Saldo</h3>
                    <div class="inside">
                        <table><tbody>
                        <?php if(empty($stats['top_regular_clients'])): ?>
                            <tr><td>No hay clientes directos con saldo.</td></tr>
                        <?php else: foreach($stats['top_regular_clients'] as $client): ?>
                            <tr><td><?php echo esc_html(strtok($client['name'], ' (')); ?></td><td style="text-align:right;"><strong>$<?php echo esc_html(number_format($client['balance'], 2)); ?></strong></td></tr>
                        <?php endforeach; endif; ?>
                        </tbody></table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Dibuja la página para registrar los pagos a Didit y maneja el guardado.
 */
function gkyc_render_didit_payments_page() {
    // --- Lógica para manejar el guardado del formulario ---
    if (isset($_POST['gkyc_submit_payment']) && check_admin_referer('gkyc_add_didit_payment_nonce')) {
        $amount = isset($_POST['gkyc_payment_amount']) ? (float)$_POST['gkyc_payment_amount'] : 0;
        $date = isset($_POST['gkyc_payment_date']) ? sanitize_text_field($_POST['gkyc_payment_date']) : date('Y-m-d');
        $note = isset($_POST['gkyc_payment_note']) ? sanitize_textarea_field($_POST['gkyc_payment_note']) : '';

        if ($amount > 0) {
            $payment_title = sprintf('Pago a Didit - $%s - %s', number_format($amount, 2), $date);
            $payment_post_id = wp_insert_post([
                'post_title' => $payment_title,
                'post_status' => 'publish',
                'post_type' => 'gkyc_transaction',
                'post_date' => $date,
            ]);
            if (!is_wp_error($payment_post_id)) {
                update_post_meta($payment_post_id, '_transaction_type', 'Pago a Didit');
                update_post_meta($payment_post_id, '_transaction_status', 'Completed');
                update_post_meta($payment_post_id, '_transaction_amount', $amount);
                if (!empty($note)) {
                    update_post_meta($payment_post_id, '_transaction_note', $note);
                }
                echo '<div class="notice notice-success is-dismissible"><p>Pago registrado exitosamente.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error: El monto debe ser mayor a cero.</p></div>';
        }
    }
    
    // --- Lógica para mostrar los pagos ya registrados ---
    $payments_query = new WP_Query([
        'post_type' => 'gkyc_transaction',
        'posts_per_page' => 50,
        'meta_key' => '_transaction_type',
        'meta_value' => 'Pago a Didit',
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    ?>
    <div class="wrap">
        <h1>Mis Pagos a Didit</h1>
        <p>Usa este formulario para llevar un registro de los pagos que realizas a tu proveedor Didit. Esto te ayudará a reconciliar tus costos.</p>

        <div id="col-container" class="wp-clearfix">
            <div id="col-left" style="float: left; width: 48%; margin-right: 2%;">
                <div class="col-wrap">
                    <h2>Registrar Nuevo Pago</h2>
                    <form method="post" action="" class="form-wrap">
                        <?php wp_nonce_field('gkyc_add_didit_payment_nonce'); ?>
                        <div class="form-field form-required">
                            <label for="gkyc_payment_amount">Monto Pagado (USD)</label>
                            <input name="gkyc_payment_amount" id="gkyc_payment_amount" type="number" step="0.01" required style="width: 100%;">
                        </div>
                        <div class="form-field form-required">
                            <label for="gkyc_payment_date">Fecha de Pago</label>
                            <input name="gkyc_payment_date" id="gkyc_payment_date" type="date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%;">
                        </div>
                        <div class="form-field">
                            <label for="gkyc_payment_note">Nota / Referencia (Opcional)</label>
                            <input name="gkyc_payment_note" id="gkyc_payment_note" type="text" placeholder="Ej: Transferencia #12345" style="width: 100%;">
                        </div>
                        <?php submit_button('Registrar Pago', 'primary', 'gkyc_submit_payment'); ?>
                    </form>
                </div>
            </div>
            <div id="col-right" style="float: left; width: 50%;">
                <div class="col-wrap">
                    <h2>Pagos Recientes</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th>Fecha</th><th>Descripción</th><th>Monto</th></tr></thead>
                        <tbody>
                        <?php if ($payments_query->have_posts()): while ($payments_query->have_posts()): $payments_query->the_post(); ?>
                            <tr>
                                <td><?php echo get_the_date('Y-m-d'); ?></td>
                                <td>
                                    <?php the_title(); ?>
                                    <?php $note = get_post_meta(get_the_ID(), '_transaction_note', true); if(!empty($note)) echo '<p class="description">Nota: '.esc_html($note).'</p>'; ?>
                                </td>
                                <td><strong>$<?php echo esc_html(number_format((float)get_post_meta(get_the_ID(), '_transaction_amount', true), 2)); ?></strong></td>
                            </tr>
                        <?php endwhile; wp_reset_postdata(); else: ?>
                            <tr><td colspan="3">Aún no has registrado pagos.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Obtiene las estadísticas generales de negocio (no financieras) para la cabina de mando.
 * v3.0 - Adaptada para filtros de fecha, KPIs de crecimiento y Top 10.
 */
function gkyc_mothership_get_business_stats($start_date, $end_date) {
    // Usamos una clave de caché dinámica que incluye las fechas para evitar datos incorrectos
    $cache_key = 'gkyc_stats_' . md5($start_date . $end_date);
    $cached_stats = get_transient($cache_key);
    if (false !== $cached_stats) {
        return $cached_stats;
    }

    $stats = [
        'new_clients_count' => 0,
        'new_partners_count' => 0,
        'top_regular_clients' => [],
        'top_partners' => [],
        'chart_data' => [
            'plan_labels' => [],
            'plan_data' => [],
        ],
    ];

    // --- CALCULAR NUEVOS CLIENTES Y PARTNERS EN EL PERÍODO ---
    $new_clients_query = new WP_Query([
        'post_type' => 'cliente_kyc',
        'date_query' => [['after' => $start_date, 'before' => $end_date, 'inclusive' => true]],
        'meta_query' => [['key' => '_license_type', 'value' => 'Partner', 'compare' => 'NOT LIKE']],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $stats['new_clients_count'] = $new_clients_query->found_posts;

    $new_partners_query = new WP_Query([
        'post_type' => 'cliente_kyc',
        'date_query' => [['after' => $start_date, 'before' => $end_date, 'inclusive' => true]],
        'meta_query' => [['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    $stats['new_partners_count'] = $new_partners_query->found_posts;
    wp_reset_postdata();

    // --- CALCULAR GRÁFICO DE DISTRIBUCIÓN Y TOP 10 (ESTOS SON GLOBALES, NO POR PERÍODO) ---
    $all_clients_posts = get_posts([ 'post_type' => 'cliente_kyc', 'numberposts' => -1, 'post_status' => 'publish' ]);
    $clients_by_plan_counts = [];
    foreach ($all_clients_posts as $client_post) {
        $balance = (float) get_post_meta($client_post->ID, '_balance', true);
        $license_type = get_post_meta($client_post->ID, '_license_type', true);
        if (empty($license_type)) { $license_type = 'Sin Asignar'; }
        
        if (stripos($license_type, 'Partner') !== false) {
            if ($balance > 0) $stats['top_partners'][] = ['name' => $client_post->post_title, 'balance' => $balance];
        } else {
            if ($balance > 0) $stats['top_regular_clients'][] = ['name' => $client_post->post_title, 'balance' => $balance];
        }

        if (!isset($clients_by_plan_counts[$license_type])) { $clients_by_plan_counts[$license_type] = 0; }
        $clients_by_plan_counts[$license_type]++;
    }
    
    $stats['chart_data']['plan_labels'] = array_keys($clients_by_plan_counts);
    $stats['chart_data']['plan_data'] = array_values($clients_by_plan_counts);
    
    usort($stats['top_regular_clients'], function($a, $b) { return $b['balance'] <=> $a['balance']; });
    $stats['top_regular_clients'] = array_slice($stats['top_regular_clients'], 0, 10); // Ampliado a 10
    usort($stats['top_partners'], function($a, $b) { return $b['balance'] <=> $a['balance']; });
    $stats['top_partners'] = array_slice($stats['top_partners'], 0, 10); // Ampliado a 10
    
    set_transient($cache_key, $stats, HOUR_IN_SECONDS);

    return $stats;
}

/**
 * ===================================================================
 * TAREA 2.6: ESTADÍSTICAS DETALLADAS (Cimientos)
 * Paso 1.1: Corregido el registro del CPT para evitar conflicto de menú.
 * ===================================================================
 */

/**
 * Registra el CPT 'Transacción KYC' para llevar un registro de cada verificación.
 */
function gkyc_register_transaction_cpt() {
    $labels = array(
        'name'                  => 'Transacciones KYC',
        'singular_name'         => 'Transacción',
        'menu_name'             => 'Transacciones',
        'name_admin_bar'        => 'Transacción KYC',
        'all_items'             => 'Todas las Transacciones',
        'new_item'              => 'Nueva Transacción',
        'edit_item'             => 'Editar Transacción',
        'update_item'           => 'Actualizar Transacción',
        'view_item'             => 'Ver Transacción',
    );
    $args = array(
        'label'                 => 'Transacciones',
        'description'           => 'Registro de cada intento de verificación KYC.',
        'labels'                => $labels,
        'supports'              => array( 'title', 'custom-fields' ),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        
        // ===== ¡AQUÍ ESTÁ LA CORRECCIÓN! =====
        'show_in_menu'          => true, // Le decimos que cree su propio menú principal.
        'menu_position'         => 23,   // Lo posicionamos en el menú.
        'menu_icon'             => 'dashicons-list-view', // Le damos un ícono.
        // =====================================

        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'post',
        'rewrite'               => false,
    );
    register_post_type( 'gkyc_transaction', $args );
}
add_action( 'init', 'gkyc_register_transaction_cpt', 0 );

/**
 * ===================================================================
 * TAREA 2.5: PÁGINA DE PAGO DE PARTNER (VERSIÓN SHORTCODE)
 * ===================================================================
 */

/**
 * Registra el shortcode [gkyc_partner_payment_page] que muestra la página de pago.
 */
function gkyc_register_partner_payment_shortcode() {
    add_shortcode( 'gkyc_partner_payment_page', 'gkyc_render_partner_payment_page' );
}
add_action( 'init', 'gkyc_register_partner_payment_shortcode' );

/**
 * Renderiza el contenido de la página de pago del partner (con auto-detección de plan y precio).
 * v2.7 - Muestra el precio del plan detectado.
 */
function gkyc_render_partner_payment_page() {
    ob_start();

    $partner_found = false;
    $partner_data = [];

    if ( isset( $_GET['partner'] ) && ! empty( $_GET['partner'] ) ) {
        $partner_slug = sanitize_title( $_GET['partner'] );
        $args = [
            'post_type' => 'cliente_kyc', 'name' => $partner_slug,
            'posts_per_page' => 1, 'post_status' => 'publish',
            'meta_query' => [['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']]
        ];
        $found_posts = get_posts( $args );

        if ( ! empty($found_posts) ) {
            $partner_found = true;
            $partner_post_id = $found_posts[0]->ID;
            
            $partner_data = [
                'id' => $partner_post_id,
                'name' => get_the_title( $partner_post_id ),
                'paypal_link' => get_post_meta( $partner_post_id, '_partner_paypal_link', true ),
                'stripe_link' => get_post_meta( $partner_post_id, '_partner_stripe_link', true ),
                'usdt_wallet' => get_post_meta( $partner_post_id, '_partner_usdt_wallet', true ),
            ];
        }
    }

    ?>
    <style>
        .gkyc-payment-buttons { margin-top: 30px; display: flex; flex-wrap: wrap; justify-content: center; gap: 15px; }
        .gkyc-payment-button { display: inline-block; padding: 15px 30px; font-size: 1.1em; font-weight: bold; color: #fff; text-decoration: none; border-radius: 8px; transition: opacity 0.3s; }
        .gkyc-payment-button.paypal { background-color: #00457C; }
        .gkyc-payment-button.stripe { background-color: #635BFF; }
        .gkyc-payment-button.usdt { background-color: #26A17B; cursor: pointer; }
        .gkyc-payment-button:hover { opacity: 0.85; color: #fff; }
        .gkyc-usdt-details { display: none; margin-top: 20px; padding: 15px; background-color: #fff; border: 1px solid #ddd; border-radius: 5px; word-break: break-all; }
        .gkyc-customer-details { margin-top: 40px; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto; }
        .gkyc-customer-details .field { margin-bottom: 15px; }
        .gkyc-customer-details label { display: block; margin-bottom: 5px; font-weight: bold; }
        .gkyc-customer-details input { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; }
        .gkyc-plan-detector { margin-top: 15px; text-align: left; max-width: 500px; margin-left: auto; margin-right: auto; font-weight: bold; }
        .gkyc-plan-detector .success { color: #28a745; }
        .gkyc-plan-detector .error { color: #dc3545; font-size: 0.9em; }
    </style>

    <div class="partner-payment-page-container" style="max-width: 800px; margin: 40px auto; padding: 20px; text-align: center;">
        <?php if ( $partner_found ) : ?>
            
            <h1>Recarga tu Saldo con <?php echo esc_html( strtok($partner_data['name'], ' (') ); ?></h1>
            <p>Ingresa tu correo para detectar tu plan y luego selecciona el monto a recargar.</p>
            
            <div class="gkyc-calculadora-wrapper" style="margin-top: 40px; padding: 30px; background-color: #f7f7f7; border-radius: 8px;">
                
                <div class="gkyc-customer-details">
                    <div class="field">
                        <label for="customer_email">Tu Correo Electrónico (para detectar tu plan)</label>
                        <input type="email" id="customer_email" required>
                    </div>
                    <div id="plan-detector-result" class="gkyc-plan-detector"></div>
                </div>

                <label for="montoRecargaSlider" class="gkyc-slider-label" style="font-size: 1.1em; font-weight: bold; color: #333; display: block; margin-top: 30px;">Desliza para seleccionar el monto a recargar:</label>
                <input type="range" min="20" max="5000" value="100" step="10" class="gkyc-slider" id="montoRecargaSlider" style="width: 100%; margin: 20px 0;">
                
                <div class="gkyc-resultados-display" style="margin-top: 20px;">
                    <span style="font-size: 1.2em;">Recargarás:</span>
                    <span class="gkyc-valor-grande" id="displayMonto" style="font-size: 2.5em; font-weight: bold; color: #007cba; display: block;">$100.00</span>
                    <span id="displayVerifications" style="font-size: 1.1em; color: #555; font-weight: 600;">Escribe tu email para calcular</span>
                </div>

                <div class="gkyc-payment-buttons">
                    <?php if ( ! empty($partner_data['paypal_link']) ) : ?>
                        <a href="<?php echo esc_url($partner_data['paypal_link']); ?>" class="gkyc-payment-button paypal" target="_blank">Pagar con PayPal</a>
                    <?php endif; ?>
                    <?php if ( ! empty($partner_data['stripe_link']) ) : ?>
                        <a href="<?php echo esc_url($partner_data['stripe_link']); ?>" class="gkyc-payment-button stripe" target="_blank">Pagar con Tarjeta (Stripe)</a>
                    <?php endif; ?>
                    <?php if ( ! empty($partner_data['usdt_wallet']) ) : ?>
                        <a href="#" id="gkyc-usdt-btn" class="gkyc-payment-button usdt">Pagar con USDT</a>
                    <?php endif; ?>
                </div>

                <?php if ( ! empty($partner_data['usdt_wallet']) ) : ?>
                    <div id="gkyc-usdt-details" class="gkyc-usdt-details">
                        <p>Para pagar, por favor transfiere el monto seleccionado a la siguiente wallet (Red TRC20):</p>
                        <strong id="usdt-wallet-address"><?php echo esc_html($partner_data['usdt_wallet']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const slider = document.getElementById('montoRecargaSlider');
                const displayMonto = document.getElementById('displayMonto');
                const displayVerifications = document.getElementById('displayVerifications');
                const customerEmailInput = document.getElementById('customer_email');
                const planDetectorResult = document.getElementById('plan-detector-result');
                const partnerId = <?php echo (int)$partner_data['id']; ?>;
                const usdtBtn = document.getElementById('gkyc-usdt-btn');
                const usdtDetails = document.getElementById('gkyc-usdt-details');
                
                let pricePerVerification = 0;

                function actualizarTodo() {
                    const monto = parseInt(slider.value);
                    displayMonto.textContent = '$' + monto.toFixed(2);
                    
                    if (pricePerVerification > 0) {
                        const verifications = Math.floor(monto / pricePerVerification);
                        displayVerifications.textContent = '≈ ' + verifications + ' Verificaciones';
                    } else {
                        displayVerifications.textContent = 'Ingresa un correo válido para calcular';
                    }
                }

                customerEmailInput.addEventListener('blur', function() {
                    const email = this.value;
                    if (!email) return;

                    planDetectorResult.innerHTML = '<span class="loading">Buscando tu plan...</span>';
                    displayVerifications.textContent = 'Calculando...';

                    fetch('<?php echo esc_url(get_rest_url(null, "guardian-kyc/v1/partner/get-client-price")); ?>', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ email: email, partner_id: partnerId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            pricePerVerification = parseFloat(data.price_per_verification);
                            // ===== ¡AQUÍ ESTÁ LA MAGIA! =====
                            planDetectorResult.innerHTML = '<span class="success">Plan Detectado: ' + data.plan_name + ' ($' + pricePerVerification.toFixed(2) + ' por verificación)</span>';
                            actualizarTodo();
                        } else {
                            pricePerVerification = 0;
                            planDetectorResult.innerHTML = '<span class="error">' + (data.message || 'Cliente no encontrado.') + '</span>';
                            actualizarTodo();
                        }
                    })
                    .catch(error => {
                        pricePerVerification = 0;
                        planDetectorResult.innerHTML = '<span class="error">Error de conexión. Intenta de nuevo.</span>';
                        actualizarTodo();
                    });
                });

                if(usdtBtn) {
                    usdtBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (usdtDetails) {
                            usdtDetails.style.display = usdtDetails.style.display === 'block' ? 'none' : 'block';
                        }
                    });
                }

                actualizarTodo();
                slider.addEventListener('input', actualizarTodo);
            });
            </script>

        <?php else : ?>
            <h1>Página de Pagos no Válida</h1>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Función Maestra que se comunica con la API de Didit para crear una nueva aplicación.
 * Tarea de Automatización - Esqueleto Centralizado.
 * VERSIÓN CORREGIDA: Incluye modo de simulación si la API Key Maestra no existe.
 */
function gkyc_create_didit_application( $client_name ) {
    error_log('[GKYC Mothership] Iniciando proceso de creación de app en Didit para: ' . $client_name);

    $settings = get_option('gkyc_mothership_settings');
    $master_api_key = isset($settings['master_didit_api_key']) ? $settings['master_didit_api_key'] : '';

    // MODO DE SIMULACIÓN: Si no hay una API Key, no damos error, sino que generamos una clave falsa.
    if ( empty($master_api_key) ) {
        error_log('[GKYC Mothership] ADVERTENCIA: La API Key Maestra de Didit no está configurada. Operando en MODO SIMULACIÓN.');
        $simulated_new_key = 'simulated_didit_key_' . wp_generate_password(24, false);
        error_log('[GKYC Mothership] Simulación: Se ha generado la clave: ' . $simulated_new_key);
        return $simulated_new_key; // Devolvemos la clave simulada y terminamos la función.
    }

    // El siguiente código solo se ejecutará si SÍ hay una API Key Maestra.
    $didit_api_url = 'https://api.didit.me/v1/applications'; // (URL de ejemplo)
    $headers = [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $master_api_key ];
    $body = [ 'name' => $client_name ];

    // Aquí iría la llamada real a wp_remote_post() cuando la integración esté activa.
    // Por ahora, simulamos también la respuesta exitosa.
    error_log('[GKYC Mothership] Simulación de llamada a la API de Didit (con API Key real).');
    
    $simulated_new_key_prod = 'didit_key_prod_' . wp_generate_password(24, false);
    error_log('[GKYC Mothership] Simulación: Didit habría devuelto la clave: ' . $simulated_new_key_prod);

    return $simulated_new_key_prod;
}

// AÑADE ESTA NUEVA FUNCIÓN COMPLETA AL FINAL DEL ARCHIVO:
/**
 * Función de ayuda para encontrar un cliente por su API Key de Didit.
 */
function gkyc_get_client_by_didit_key($api_key) {
    if (empty($api_key)) {
        return null;
    }
    $client_query = new WP_Query([
        'post_type'      => 'cliente_kyc',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => '_api_key_didit', 'value' => $api_key]],
    ]);
    if ($client_query->have_posts()) {
        return $client_query->posts[0];
    }
    return null;
}

// REEMPLAZA TU FUNCIÓN gkyc_check_duplicate_meta_callback CON ESTA VERSIÓN FINAL
function gkyc_check_duplicate_meta_callback() {
    check_ajax_referer('gkyc_metabox_nonce', 'security');
    // ... (la parte de recibir datos se queda igual) ...
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $meta_key = isset($_POST['meta_key']) ? sanitize_key($_POST['meta_key']) : '';
    $meta_value = isset($_POST['meta_value']) ? sanitize_text_field($_POST['meta_value']) : '';
    if (empty($meta_key) || empty($meta_value) || empty($post_id)) { wp_send_json_error(['message' => 'Faltan datos.']); return; }

    $args = ['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'post__not_in' => [$post_id], 'meta_query' => [['key' => $meta_key, 'value' => $meta_value]]];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        // ===== INICIO DE LA MEJORA =====
        $duplicate_post = $query->posts[0];
        $duplicate_name = $duplicate_post->post_title;
        $duplicate_email = get_post_meta($duplicate_post->ID, '_email', true);
        
        $owner_id = get_post_meta($duplicate_post->ID, '_reseller_owner_id', true);
        if (!empty($owner_id)) {
            $partner_name = get_the_title($owner_id);
            $client_type_info = ' (Sub-cliente de ' . esc_html($partner_name) . ')';
        } else {
            $client_type_info = ' (Cliente Directo)';
        }
        
        wp_reset_postdata();

        $error_message = sprintf(
            '¡Este valor ya está en uso por %s (%s)%s!',
            esc_html($duplicate_name),
            esc_html($duplicate_email),
            $client_type_info
        );

        wp_send_json_error(['message' => $error_message]);
        // ===== FIN DE LA MEJORA =====
    } else {
        wp_send_json_success(['message' => 'Valor disponible.']);
    }
}

/**
 * Carga scripts JS solo en la página de edición del CPT 'cliente_kyc'.
 */
add_action('admin_enqueue_scripts', 'gkyc_enqueue_metabox_scripts');
function gkyc_enqueue_metabox_scripts($hook) {
    // Obtenemos la información de la pantalla actual
    $screen = get_current_screen();

    // Verificamos que estamos en la página de edición de un 'cliente_kyc'
    if ('post.php' === $hook && 'cliente_kyc' === $screen->post_type) {
        
        wp_enqueue_script(
            'gkyc-metabox-scripts',
            MOTHERSHIP_URL . 'assets/js/mothership-metabox-scripts.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Pasamos datos de PHP a JavaScript de forma segura
        wp_localize_script('gkyc-metabox-scripts', 'gkyc_metabox_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('gkyc_metabox_nonce')
        ]);
    }
}

/**
 * Guarda un mensaje de notificación para mostrarlo después de la recarga.
 */
function gkyc_set_admin_notice($type, $message) {
    set_transient('gkyc_admin_notice', ['type' => $type, 'message' => $message], 30);
}

/**
 * Añade un campo de búsqueda personalizado a la lista de Clientes en el admin.
 */
add_action('restrict_manage_posts', 'gkyc_add_admin_client_search_field');
function gkyc_add_admin_client_search_field($post_type) {
    // Solo mostramos el campo en nuestro CPT de 'cliente_kyc'.
    if ('cliente_kyc' === $post_type) {
        $search_term = isset($_GET['gkyc_admin_search']) ? sanitize_text_field($_GET['gkyc_admin_search']) : '';
        echo '<input type="search" name="gkyc_admin_search" value="' . esc_attr($search_term) . '" placeholder="Buscar por nombre...">';
    }
}

/**
 * Muestra el mensaje de notificación si existe.
 */
add_action('admin_notices', 'gkyc_display_admin_notice');
function gkyc_display_admin_notice() {
    if ($notice = get_transient('gkyc_admin_notice')) {
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($notice['type']),
            wp_kses_post($notice['message'])
        );
        delete_transient('gkyc_admin_notice');
    }
}

// 2. MENÚ DESPLEGABLE: Muestra el filtro de Partners solo en la página correcta.
add_action('restrict_manage_posts', 'gkyc_add_partner_filter_dropdown_to_clients');
function gkyc_add_partner_filter_dropdown_to_clients($post_type) {
    // Solo mostramos el dropdown si estamos en el CPT 'cliente_kyc' Y NO estamos en la página de Partners.
    if ($post_type === 'cliente_kyc' && !isset($_GET['partner_filter'])) {
        $args = [
            'post_type'      => 'cliente_kyc', 'posts_per_page' => -1,
            'meta_query'     => [['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']],
            'orderby'        => 'title', 'order'          => 'ASC'
        ];
        $partners = get_posts($args);
        $current_filter = isset($_GET['partner_filter_id']) ? $_GET['partner_filter_id'] : '';

        echo '<select name="partner_filter_id" style="width: 200px;">';
        echo '<option value="">Filtrar por Partner...</option>';
        foreach ($partners as $partner) {
            printf('<option value="%s"%s>%s</option>', esc_attr($partner->ID), selected($current_filter, $partner->ID, false), esc_html($partner->post_title));
        }
        echo '</select>';
    }
}

// REEMPLAZA LA VERSIÓN ANTERIOR DE ESTA FUNCIÓN CON ESTA
add_action('admin_head', 'gkyc_fix_partners_menu_highlight');
function gkyc_fix_partners_menu_highlight() {
    // Usamos PHP para revisar la URL actual
    $current_url = $_SERVER['REQUEST_URI'];

    // Si la URL contiene los parámetros de nuestra página de "Partners"
    if (strpos($current_url, 'edit.php?post_type=cliente_kyc&partner_filter=1') !== false) {
        ?>
        <script type.text/javascript>
            jQuery(document).ready(function($) {
                // Usamos !important para máxima prioridad y selectores más directos
                var css = `
                    /* Forzar la eliminación del resaltado de otros menús */
                    #toplevel_page_gkyc_business_dashboard,
                    #toplevel_page_gkyc_business_dashboard > a {
                        background-color: transparent !important;
                        color: #a7aaad !important;
                    }
                    /* Forzar el resaltado del menú de Partners */
                    #toplevel_page_edit-php-post_type-cliente_kyc-partner_filter-1,
                    #toplevel_page_edit-php-post_type-cliente_kyc-partner_filter-1 > a {
                        background-color: #007cba !important;
                        color: #fff !important;
                    }
                `;
                $('head').append('<style>' + css + '</style>');
            });
        </script>
        <?php
    }
}

/**
 * ===================================================================
 * AÑADE EL ENLACE "SUB-CLIENTES" A LA LISTA DE PARTNERS
 * ===================================================================
 */
add_filter('post_row_actions', 'gkyc_add_subclients_link_to_partners', 10, 2);
function gkyc_add_subclients_link_to_partners($actions, $post) {
    // Solo aplicamos esto a nuestro Custom Post Type 'cliente_kyc'
    if ($post->post_type === 'cliente_kyc') {
        
        // Verificamos si el cliente actual es un Partner
        $license_type = get_post_meta($post->ID, '_license_type', true);
        
        if (stripos($license_type, 'Partner') !== false) {
            // Si es un Partner, creamos el enlace
            $subclients_url = admin_url('edit.php?post_type=cliente_kyc&partner_filter_id=' . $post->ID);
            
            // Creamos el HTML del enlace
            $actions['subclients'] = sprintf(
                '<a href="%s" aria-label="Ver sub-clientes de %s"><strong>Sub-clientes</strong></a>',
                esc_url($subclients_url),
                esc_attr($post->post_title)
            );
        }
    }
    
    // Devolvemos el array de acciones (modificado o no)
    return $actions;
}

/**
 * FUNCIÓN MAESTRA Y DEFINITIVA DE FILTROS PARA EL PANEL DE CLIENTES
 * VERSIÓN 2.0 - Añadida la lógica para el buscador personalizado del admin.
 */
add_action('pre_get_posts', 'gkyc_master_client_list_filter');
function gkyc_master_client_list_filter($query) {
    // Solo actuamos en la consulta principal del panel de administración para el CPT 'cliente_kyc'
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'cliente_kyc') {
        return;
    }

    // ===== INICIO DE LA MODIFICACIÓN =====
    // 1. Lógica para nuestro nuevo buscador personalizado
    if (!empty($_GET['gkyc_admin_search'])) {
        $search_term = sanitize_text_field($_GET['gkyc_admin_search']);
        // Usamos el parámetro de búsqueda nativo 's' de WordPress, que busca en el título.
        $query->set('s', $search_term);
        // Al usar nuestro propio buscador, ya no necesitamos los otros filtros de abajo.
        return;
    }
    // ===== FIN DE LA MODIFICACIÓN =====

    // Si el administrador está usando el buscador nativo de WordPress, no aplicamos ningún filtro.
    if ($query->is_search()) {
        return;
    }

    // --- LÓGICA DE FILTRADO BASADA EN LA PÁGINA ACTUAL (se mantiene igual) ---

    // CASO 1: El usuario está en la página de "Partners"
    if (isset($_GET['partner_filter'])) {
        $query->set('meta_query', [
            ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']
        ]);
        return;
    }

    // CASO 2: El usuario está en "Todos los Clientes" y usa el dropdown para ver los sub-clientes
    if (!empty($_GET['partner_filter_id'])) {
        $partner_id = sanitize_text_field($_GET['partner_filter_id']);
        $query->set('meta_query', [
            ['key' => '_reseller_owner_id', 'value' => $partner_id]
        ]);
        return;
    }

    // CASO 3: Vista por defecto de "Todos los Clientes" (ningún filtro aplicado)
    $query->set('meta_query', [
        'relation' => 'AND',
        ['key' => '_reseller_owner_id', 'compare' => 'NOT EXISTS'],
        ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'NOT LIKE']
    ]);
}

/**
 * Función de ayuda para obtener el costo de una verificación.
 * Es inteligente: busca el precio del plan para un cliente directo,
 * o el precio especial que un Partner le ha asignado a un sub-cliente.
 *
 * @param int $client_post_id El ID del cliente que se está verificando.
 * @param string $plan_slug El slug del plan que se usó.
 * @return float El costo de la verificación.
 */
function gkyc_get_verification_cost($client_post_id, $plan_slug) {
    if (empty($plan_slug)) {
        return 0.0;
    }

    $owner_id = get_post_meta($client_post_id, '_reseller_owner_id', true);
    $cost = 0.0;

    if (!empty($owner_id)) {
        // Es un SUB-CLIENTE: Buscamos el precio especial del Partner.
        $plan_query = new WP_Query([
            'post_type' => 'plan_kyc',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_plan_slug', 'value' => $plan_slug]]
        ]);
        if ($plan_query->have_posts()) {
            $plan_post_name = $plan_query->posts[0]->post_name;
            $price_meta_key = '_partner_verification_price_' . $plan_post_name;
            $cost = (float) get_post_meta($owner_id, $price_meta_key, true);
        }
        wp_reset_postdata();
    } else {
        // Es un CLIENTE DIRECTO: Buscamos el precio del plan general.
        $plan_query = new WP_Query([
            'post_type' => 'plan_kyc',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_plan_slug', 'value' => $plan_slug]]
        ]);
        if ($plan_query->have_posts()) {
            $price_string = get_post_meta($plan_query->posts[0]->ID, '_price', true);
            preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
            $cost = isset($matches[0]) ? (float) $matches[0] : 0.0;
        }
        wp_reset_postdata();
    }
    return $cost;
}

/**
 * ===================================================================
 * FASE 4.2: LÓGICA PARA EL FLUJO DE RECARGA DE SALDO AUTOMÁTICO
 * ===================================================================
 */

/**
 * Paso 1: Captura la 'return_url' del enlace y la añade a los datos del producto en el carrito.
 */
add_filter( 'woocommerce_add_cart_item_data', 'gkyc_save_return_url_to_cart_item', 10, 3 );
function gkyc_save_return_url_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
    // Verificamos si nuestra URL de retorno está presente en la petición
    if ( isset( $_REQUEST['return_url'] ) ) {
        // La guardamos en el array de datos del item del carrito.
        $cart_item_data['gkyc_return_url'] = esc_url_raw( $_REQUEST['return_url'] );
    }
    return $cart_item_data;
}

/**
 * Paso 2: Guarda la 'return_url' del carrito como metadato en el item del pedido final.
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'gkyc_add_return_url_to_order_item_meta', 10, 4 );
function gkyc_add_return_url_to_order_item_meta( $item, $cart_item_key, $values, $order ) {
    // Si guardamos nuestra URL en el paso anterior, la añadimos ahora al pedido.
    if ( ! empty( $values['gkyc_return_url'] ) ) {
        $item->add_meta_data( '_gkyc_return_url', $values['gkyc_return_url'] );
    }
}

/**
 * Paso 3: En la página de "Gracias", busca la URL en el pedido y ejecuta la redirección.
 */
add_action( 'woocommerce_thankyou', 'gkyc_thankyou_page_redirect', 10, 1 );
function gkyc_thankyou_page_redirect( $order_id ) {
    if ( ! $order_id ) {
        return;
    }

    $order = wc_get_order( $order_id );
    $return_url = '';

    // Buscamos en cada producto del pedido si alguno tiene nuestra URL de retorno guardada.
    foreach ( $order->get_items() as $item ) {
        $item_return_url = $item->get_meta( '_gkyc_return_url' );
        if ( ! empty( $item_return_url ) ) {
            $return_url = $item_return_url;
            break; // La encontramos, no necesitamos seguir buscando.
        }
    }

    // Si encontramos una URL de retorno, mostramos un mensaje y el script de redirección.
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
                    if (countdown) {
                        countdown.textContent = seconds;
                    }
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

/**
 * Hook de AJAX para obtener el saldo maestro del socio actual.
 */
add_action('wp_ajax_gkyc_get_partner_balance', 'gkyc_get_partner_balance_ajax');
function gkyc_get_partner_balance_ajax() {
    // Verificamos que el usuario esté logueado.
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'No autorizado.']);
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Buscamos el perfil de socio asociado a este usuario de WordPress.
    $partner_query = new WP_Query([
        'post_type' => 'cliente_kyc', 
        'posts_per_page' => 1, 
        'meta_query' => [ 
            ['key' => '_user_id', 'value' => $user_id], 
            ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']
        ], 
        'fields' => 'ids'
    ]);

    if (!$partner_query->have_posts()) { 
        wp_send_json_error(['message' => 'Perfil de socio no encontrado.']);
        return;
    }
    
    $partner_client_id = $partner_query->posts[0];
    $balance = get_post_meta($partner_client_id, '_balance', true);
    
    // Enviamos el saldo en un formato JSON exitoso.
    wp_send_json_success(['balance' => (float)$balance]);
}


/**
 * Carga los scripts y estilos necesarios para el Panel de Socio.
 * (VERSIÓN CORREGIDA PARA CARGA DE MEDIOS)
 */
/**
 * Carga los scripts y datos para el Panel de Socio.
 */
function gkyc_partner_dashboard_scripts() {
    // Solo ejecutar en la página del panel y si el usuario está logueado.
    if ( is_page('panel-de-socio') && is_user_logged_in() ) {

        // ¡AÑADIMOS ESTA LÍNEA CRUCIAL!
        wp_enqueue_media();

        // 1. Cargar la librería externa de Chart.js para los gráficos.
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);

        // 2. Carga el script principal del panel
        wp_enqueue_script(
            'gkyc-partner-dashboard-js',
            MOTHERSHIP_URL . 'assets/js/partner-dashboard-v2.js',
            ['jquery', 'chart-js'],
            '2.0.2', // Incrementamos versión para evitar caché
            true
        );

        // 3. Obtenemos los datos del socio para pasarlos al script.
        $user_id = get_current_user_id();
        $partner_query = new WP_Query([
            'post_type' => 'cliente_kyc', 
            'posts_per_page' => 1, 
            'meta_query' => [ ['key' => '_user_id', 'value' => $user_id] ],
            'fields' => 'ids'
        ]);

        if ($partner_query->have_posts()) {
            $partner_client_id = $partner_query->posts[0];
            $stats_data = gkyc_mothership_get_partner_stats($partner_client_id);

            wp_localize_script(
                'gkyc-partner-dashboard-js',
                'gkyc_partner_obj',
                [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'    => wp_create_nonce('wp_rest'),
                    'urls'     => [
                        'generate_license' => esc_url_raw(rest_url('guardian-kyc/v1/partner/generate-license')),
                        'transfer_balance' => esc_url_raw(rest_url('guardian-kyc/v1/partner/transfer-balance')),
                        'calculate_transfer_profit' => esc_url_raw(rest_url('guardian-kyc/v1/partner/calculate-transfer-profit')), // ¡La URL que faltaba!
                        'update_prices'    => esc_url_raw(rest_url('guardian-kyc/v1/partner/update-prices')),
                        'update_branding'  => esc_url_raw(rest_url('guardian-kyc/v1/partner/update-branding')),
                        'checkout'         => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
                        'generate_payment_token' => esc_url_raw(rest_url('guardian-kyc/v1/partner/generate-payment-token')),
                        'process_request' => esc_url_raw(rest_url('guardian-kyc/v1/partner/process-activation-request')),
                        'process_recharge' => esc_url_raw(rest_url('guardian-kyc/v1/partner/process-recharge-request')),
                    ],
                    'chart_data' => [
                        'activity' => $stats_data['activity_chart'],
                        'status'   => $stats_data['status_chart'],
                    ]
                ]
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'gkyc_partner_dashboard_scripts');



/**
 * Calcula los indicadores financieros clave y prepara los datos para los gráficos.
 * v2.1 - CORREGIDO: Ahora lee el costo directamente desde la transacción registrada.
 */
function gkyc_calculate_financials($start_date, $end_date) {
    // La primera parte de la función para calcular INGRESOS se queda igual.
    $results = [
        'totals' => [
            'ingresos' => 0,
            'costos' => 0,
            'ganancia_neta' => 0,
            'margen' => 0,
            // ===== CORRECCIÓN: Añadimos el campo que faltaba en el informe =====
            'pagos_a_didit' => 0,
        ],
        'chart_data' => [
            'labels' => [],
            'ingresos' => [],
            'costos' => [],
            'ganancia' => [],
        ],
    ];

    // --- PASO 1: CALCULAR INGRESOS (DESDE WOOCOMMERCE) - SIN CAMBIOS ---
    $recharge_product_id = 2093;
    $orders = wc_get_orders([
        'limit' => -1, 'status' => ['wc-completed'], 'date_created' => $start_date . '...' . $end_date,
    ]);
    
    $daily_ingresos = [];
    foreach ($orders as $order) {
        $date = $order->get_date_created()->format('Y-m-d');
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $recharge_product_id) {
                if (!isset($daily_ingresos[$date])) $daily_ingresos[$date] = 0;
                $daily_ingresos[$date] += $order->get_total();
                break;
            }
        }
    }
    $results['totals']['ingresos'] = array_sum($daily_ingresos);

    // =====================================================================
    // ===== INICIO DE LA CORRECCIÓN EN EL CÁLCULO DE COSTOS =====
    // =====================================================================

    // --- PASO 2: CALCULAR COSTOS (DESDE TRANSACCIONES DE VERIFICACIÓN) ---
    $transactions_query = new WP_Query([
        'post_type' => 'gkyc_transaction', 'posts_per_page' => -1,
        'date_query' => [['after' => $start_date . ' 00:00:00', 'before' => $end_date . ' 23:59:59', 'inclusive' => true]],
        // La consulta para encontrar las transacciones correctas se mantiene igual.
        'meta_query' => [['key' => '_transaction_type', 'value' => 'Verificación'], ['key' => '_transaction_status', 'value' => 'Approved']],
    ]);

    $daily_costos = [];
    if ($transactions_query->have_posts()) {
        while ($transactions_query->have_posts()) {
            $transactions_query->the_post();
            $date = get_the_date('Y-m-d');
            
            // Lógica Simplificada: Leemos el costo que YA FUE GUARDADO.
            // El costo se guarda como un número negativo (ej: -0.85).
            $costo_transaccion = (float) get_post_meta(get_the_ID(), '_transaction_amount', true);
            
            // Usamos abs() para convertirlo en un número positivo para sumarlo.
            $admin_cost = abs($costo_transaccion);

            if (!isset($daily_costos[$date])) $daily_costos[$date] = 0;
            $daily_costos[$date] += $admin_cost;
        }
    }
    wp_reset_postdata();
    $results['totals']['costos'] = array_sum($daily_costos);

    // ===================================================================
    // ===== FIN DE LA CORRECCIÓN EN EL CÁLCULO DE COSTOS =====
    // ===================================================================

    // --- PASO 2.5: CALCULAR PAGOS A DIDIT (YA REGISTRADOS) ---
    $payments_query = new WP_Query([
        'post_type' => 'gkyc_transaction', 'posts_per_page' => -1,
        'date_query' => [['after' => $start_date . ' 00:00:00', 'before' => $end_date . ' 23:59:59', 'inclusive' => true]],
        'meta_query' => [['key' => '_transaction_type', 'value' => 'Pago a Didit']],
    ]);

    if ($payments_query->have_posts()) {
        while ($payments_query->have_posts()) {
            $payments_query->the_post();
            $results['totals']['pagos_a_didit'] += (float) get_post_meta(get_the_ID(), '_transaction_amount', true);
        }
    }
    wp_reset_postdata();


    // --- PASO 3: PREPARAR DATOS PARA GRÁFICOS Y TOTALES FINALES (SIN CAMBIOS) ---
    $period = new DatePeriod(new DateTime($start_date), new DateInterval('P1D'), (new DateTime($end_date))->modify('+1 day'));
    foreach ($period as $date) {
        $day_key = $date->format('Y-m-d');
        $results['chart_data']['labels'][] = $date->format('d M');
        $results['chart_data']['ingresos'][] = $daily_ingresos[$day_key] ?? 0;
        $results['chart_data']['costos'][] = $daily_costos[$day_key] ?? 0;
        $results['chart_data']['ganancia'][] = ($daily_ingresos[$day_key] ?? 0) - ($daily_costos[$day_key] ?? 0);
    }

    $results['totals']['ganancia_neta'] = $results['totals']['ingresos'] - $results['totals']['costos'];
    if ($results['totals']['ingresos'] > 0) {
        $results['totals']['margen'] = ($results['totals']['ganancia_neta'] / $results['totals']['ingresos']) * 100;
    }

    return $results;
}

/**
 * Crea el rol de "Socio" con el permiso para subir archivos.
 * Esta función solo necesita ejecutarse una vez.
 */
function gkyc_add_partner_role() {
    // Obtenemos las capacidades del rol 'customer' para usarlas como base.
    $customer_role = get_role('customer');
    $capabilities = $customer_role ? $customer_role->capabilities : [];

    // Añadimos el nuevo permiso que necesitamos.
    $capabilities['upload_files'] = true;

    // Creamos el nuevo rol 'socio' si no existe.
    add_role(
        'socio',
        'Socio', // El nombre que se verá en el panel de WordPress
        $capabilities
    );
}
// Registramos la función para que se ejecute una vez.
register_activation_hook( __FILE__, 'gkyc_add_partner_role' );

/**
 * Filtra la consulta de la Biblioteca de Medios para los socios en el frontend.
 * Esto asegura que un socio solo pueda ver sus propias subidas de medios
 * cuando accede a la biblioteca desde su panel de socio.
 */
add_filter( 'ajax_query_attachments_args', 'gkyc_filter_partner_media_library' );
function gkyc_filter_partner_media_library( $query ) {
    // Obtenemos el ID del usuario que está haciendo la petición
    $user_id = get_current_user_id();

    // Si no hay un usuario conectado, no hacemos nada
    if ( !$user_id ) {
        return $query;
    }

    // Obtenemos el objeto de usuario para poder revisar su rol
    $user = get_userdata( $user_id );

    // Solo aplicamos este filtro si el usuario tiene el rol 'socio'
    // Y si no es un administrador (para que el admin pueda seguir viendo todo)
    if ( $user && in_array( 'socio', (array) $user->roles ) && !current_user_can('manage_options') ) {
        // Forzamos la consulta para que solo devuelva los archivos del usuario actual
        $query['author'] = $user_id;
    }

    return $query;
}

/**
 * Bloquea el acceso al panel de administración para el rol 'socio'.
 * Los redirige a la página de inicio si intentan acceder a /wp-admin/.
 */
add_action( 'admin_init', 'gkyc_block_partner_admin_access' );
function gkyc_block_partner_admin_access() {
    // Si el usuario tiene el rol 'socio' y no es una petición AJAX...
    if ( current_user_can('socio') && !current_user_can('manage_options') && !wp_doing_ajax() ) {
        // ...lo redirigimos a la página de inicio.
        wp_redirect( home_url() );
        exit;
    }
}

/**
 * Oculta la barra de administración para el rol 'socio'.
 * Esto nos permite darles permisos internos (como edit_posts)
 * sin afectar su experiencia en el frontend.
 */
add_action('after_setup_theme', 'gkyc_hide_admin_bar_for_partners');
function gkyc_hide_admin_bar_for_partners() {
    if ( current_user_can('socio') && !current_user_can('manage_options') ) {
        add_filter('show_admin_bar', '__return_false');
    }
}

// --- INICIO DE LA MODIFICACIÓN: LÍMITE DE SUBIDA CONDICIONAL ---

/**
 * Establece el límite de tamaño de subida de archivos a 1.8 MB ÚNICAMENTE para el rol 'socio'.
 */
add_filter( 'upload_size_limit', 'gkyc_custom_upload_size_limit', 20 ); // Prioridad 20 para asegurar que se ejecute
function gkyc_custom_upload_size_limit( $size ) {
    // Si el usuario actual tiene el rol 'socio' Y NO es un administrador...
    if ( current_user_can('socio') && !current_user_can('manage_options') ) {
        // ...aplicamos el límite de 1.8 MB.
        return 1887436; // 1.8 * 1024 * 1024 bytes
    }

    // Para todos los demás usuarios (incluyendo al admin), devolvemos el límite original sin cambios.
    return $size;
}

// --- FIN DE LA MODIFICACIÓN ---

/**
 * Registra el Custom Post Type 'Solicitud de Activación'.
 * Este CPT no será público, solo se usará internamente para gestionar las solicitudes.
 */
add_action( 'init', 'gkyc_register_activation_request_cpt' );
function gkyc_register_activation_request_cpt() {
    $args = array(
        'label'                 => 'Solicitudes de Activación',
        'public'                => false,
        'show_ui'               => true, // Lo mostramos en el menú de admin para que puedas verlas
        'show_in_menu'          => false,
        'supports'              => array( 'title', 'custom-fields' ),
        'hierarchical'          => false,
        'publicly_queryable'    => false,
        'exclude_from_search'   => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'gkyc_act_request', $args );
}

// Registra el Custom Post Type 'Solicitud de Recarga'
add_action( 'init', 'gkyc_register_recharge_request_cpt' );
function gkyc_register_recharge_request_cpt() {
    $args = array(
        'label'                 => 'Solicitudes de Recarga',
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => false,
        'supports'              => array( 'title', 'custom-fields' ),
        'hierarchical'          => false,
        'publicly_queryable'    => false,
        'exclude_from_search'   => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'gkyc_recharge_req', $args );
}

/**
 * Función ayudante para limpiar la caché de un socio específico.
 * Esta caché es la que usa el Kit de Reventa.
 *
 * @param int $partner_post_id El ID del post del socio.
 */
function gkyc_mothership_clear_partner_cache_by_id( $partner_post_id ) {
    // Nos aseguramos de que el ID corresponde a un socio.
    $license_type = get_post_meta( $partner_post_id, '_license_type', true );
    if ( stripos( $license_type, 'Partner' ) === false ) {
        return;
    }

    $api_key = get_post_meta( $partner_post_id, '_api_key_mothership', true );
    if ( ! empty( $api_key ) ) {
        $cache_key = 'gkyc_reseller_data_' . md5( $api_key );
        delete_transient( $cache_key );
    }
}

/**
 * Hook que se dispara cuando un administrador guarda un perfil de Cliente/Socio.
 * Llama a la función "limpiadora" para refrescar la caché.
 */
add_action( 'save_post_cliente_kyc', 'gkyc_clear_partner_cache_on_admin_save' );
function gkyc_clear_partner_cache_on_admin_save( $post_id ) {
    // Si es un autoguardado, no hacemos nada.
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    // Verificamos permisos.
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    
    gkyc_mothership_clear_partner_cache_by_id( $post_id );
}

/**
 * Calcula el número de instalaciones activas (Versión 2.0 - Corregida).
 * - Corrige el typo de '_key' a 'key' en la meta_query.
 * - Ajusta la lógica para incluir Partners en el conteo de "Directos".
 * @return array Un array con las cuentas de clientes directos y sub-clientes.
 */
function gkyc_get_active_install_counts() {
    // Se considera "activo" si el plugin se ha reportado en los últimos 7 días.
    $seven_days_ago = strtotime('-7 days');

    // Contar clientes DIRECTOS y PARTNERS activos
    $direct_clients_query = new WP_Query([
        'post_type' => 'cliente_kyc',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            ['key' => '_last_heartbeat_timestamp', 'value' => $seven_days_ago, 'compare' => '>='],
            // La única condición para ser "Directo" o "Partner" es NO ser un sub-cliente.
            ['key' => '_reseller_owner_id', 'compare' => 'NOT EXISTS'],
        ]
    ]);

    // Contar SUB-CLIENTES activos
    $sub_clients_query = new WP_Query([
        'post_type' => 'cliente_kyc',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => [
            'relation' => 'AND',
            ['key' => '_last_heartbeat_timestamp', 'value' => $seven_days_ago, 'compare' => '>='],
            // La única condición para ser "Sub-Cliente" es TENER un dueño.
            ['key' => '_reseller_owner_id', 'compare' => 'EXISTS']
        ]
    ]);
    
    return [
        'direct_actives' => $direct_clients_query->found_posts,
        'subclient_actives' => $sub_clients_query->found_posts,
    ];
}

/**
 * ===================================================================
 * GESTOR DE PAQUETES DE LICENCIAS PARA PARTNERS
 * ===================================================================
 */

// 1. Registra el nuevo Tipo de Post Personalizado "Paquete de Licencia"
add_action('init', 'gkyc_register_license_package_cpt');
function gkyc_register_license_package_cpt() {
    $labels = array('name' => 'Paquetes de Licencias', 'singular_name' => 'Paquete de Licencia');
    $args = array(
        'label' => 'Paquetes de Licencias',
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 22, // Justo debajo de Panel de Negocio
        'menu_icon' => 'dashicons-archive',
        'supports' => array('title'),
        'rewrite' => false,
    );
    register_post_type('gkyc_license_pack', $args);
}

// 2. Añade la caja de opciones (metabox) para los detalles del paquete
add_action('add_meta_boxes', 'gkyc_add_license_package_metabox');
function gkyc_add_license_package_metabox() {
    add_meta_box(
        'gkyc_package_details',
        'Detalles del Paquete de Licencia',
        'gkyc_render_license_package_metabox_html',
        'gkyc_license_pack',
        'normal',
        'high'
    );
}

// 3. Dibuja el contenido del metabox (los campos a rellenar)
function gkyc_render_license_package_metabox_html($post) {
    wp_nonce_field('gkyc_save_package_details', 'gkyc_package_nonce');
    $price = get_post_meta($post->ID, '_package_price', true);
    $limit = get_post_meta($post->ID, '_license_limit', true);
    ?>
    <table class="form-table">
        <tbody>
            <tr>
                <th><label for="gkyc_package_price">Precio de Venta del Paquete (USD)</label></th>
                <td><input type="number" step="0.01" id="gkyc_package_price" name="gkyc_package_price" value="<?php echo esc_attr($price); ?>" class="regular-text" placeholder="Ej: 299.00"></td>
            </tr>
            <tr>
                <th><label for="gkyc_license_limit">Número de Licencias que Otorga</label></th>
                <td><input type="number" step="1" id="gkyc_license_limit" name="gkyc_license_limit" value="<?php echo esc_attr($limit); ?>" class="regular-text" placeholder="Ej: 15 o dejar en blanco para ilimitadas"></td>
            </tr>
        </tbody>
    </table>
    <?php
}

// 4. Guarda los datos del metabox
add_action('save_post_gkyc_license_pack', 'gkyc_save_license_package_metabox_data');
function gkyc_save_license_package_metabox_data($post_id) {
    if (!isset($_POST['gkyc_package_nonce']) || !wp_verify_nonce($_POST['gkyc_package_nonce'], 'gkyc_save_package_details')) {
        return;
    }

    // Usamos la función de WooCommerce para limpiar y guardar el precio de forma segura
    if (isset($_POST['gkyc_package_price'])) {
        $price = wc_clean( wp_unslash( $_POST['gkyc_package_price'] ) );
        update_post_meta($post_id, '_package_price', wc_format_decimal($price));
    }

    if (isset($_POST['gkyc_license_limit'])) {
        update_post_meta($post_id, '_license_limit', sanitize_text_field($_POST['gkyc_license_limit']));
    }
}

/**
 * ===================================================================
 * FUNCIÓN CENTRALIZADA DE ENVÍO DE CORREOS (WHITELABEL)
 * ===================================================================
 * Esta es la única función que se debe usar para enviar correos a clientes
 * finales, ya que contiene toda la lógica de marca blanca.
 *
 /**
 * Función centralizada para enviar correos con marca blanca.
 * Determina si el cliente pertenece a un socio y aplica las plantillas y cabeceras correspondientes.
 *
 * @param string $to                 El email del destinatario.
 * @param string $subject_template   La plantilla para el asunto.
 * @param string $body_template      La plantilla para el cuerpo del correo.
 * @param int    $client_post_id     El ID del post del cliente para obtener sus datos.
 * @param array  $extra_replacements Un array de shortcodes y valores extra para reemplazar.
 */
// --- INICIO: REEMPLAZO DE gkyc_send_whitelabel_email (LÓGICA UNIFICADA) ---

// --- INICIO: REEMPLAZO DE gkyc_send_whitelabel_email (LÓGICA UNIFICADA) ---

function gkyc_send_whitelabel_email( $to, $subject_template, $body_template, $client_post_id, $extra_replacements = [] ) {
    if ( !is_email($to) || empty($client_post_id) ) { return; }

    $client_post = get_post($client_post_id);
    if (!$client_post) { return; }

    $partner_id = get_post_meta($client_post_id, '_reseller_owner_id', true);
    $settings = get_option('gkyc_mothership_settings');
    
    $final_subject = $subject_template;
    $final_body = $body_template;

    // Lógica de Selección de Plantillas
    if (empty($final_subject) || empty($final_body)) {
        if ($partner_id) { // Es un sub-cliente
            $partner_subject_custom = get_post_meta($partner_id, '_partner_welcome_subject', true);
            $partner_body_custom = get_post_meta($partner_id, '_partner_welcome_body', true);

            if (!empty($partner_subject_custom) && !empty($partner_body_custom)) {
                // Opción 1: El socio SÍ tiene una plantilla personalizada. La usamos.
                $final_subject = $partner_subject_custom;
                $final_body = $partner_body_custom;
            } else {
                // Opción 2: El socio NO tiene plantilla. Usamos la de cliente directo como base.
                $final_subject = !empty($settings['default_partner_welcome_subject']) ? $settings['default_partner_welcome_subject'] : '¡Bienvenido! Tu licencia ha sido creada';
                // (El cuerpo del correo para clientes directos se genera más adelante)
            }
        } else { // Es un cliente directo
            $final_subject = '¡Bienvenido a Guardián KYC! Tus datos de acceso';
            // (El cuerpo del correo se genera más adelante)
        }
    }
    
    // Lógica de Cabeceras y Shortcodes
    $client_name = strtok($client_post->post_title, ' (');
    $mothership_api_key = get_post_meta($client_post_id, '_api_key_mothership', true);

    if ($partner_id) {
        $plugin_download_url = $settings['plugin_download_url_reseller'] ?? '';
    } else {
        $plugin_download_url = $settings['plugin_download_url_direct'] ?? '';
    }

    $replacements = [
        '[nombre_cliente]' => $client_name,
        '[email_cliente]'  => $to,
        '[API_Key]'        => $mothership_api_key,
        '[enlace_descarga_plugin]' => esc_url($plugin_download_url),
    ];
    
    if (is_array($extra_replacements)) {
        $replacements = array_merge($replacements, $extra_replacements);
    }
    
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    if ( $partner_id ) {
        $partner_post = get_post( $partner_id );
        $partner_name = strtok( $partner_post->post_title, ' (' );
        $from_name = get_post_meta( $partner_id, '_partner_from_name', true ) ?: $partner_name;
        $reply_to = get_post_meta( $partner_id, '_partner_reply_to_email', true );
        $replacements['[nombre_partner]'] = $partner_name;
        $headers[] = 'From: ' . $from_name . ' <' . get_option( 'admin_email' ) . '>';
        if ( is_email( $reply_to ) ) { $headers[] = 'Reply-To: ' . $reply_to; }
    } else {
        $replacements['[nombre_partner]'] = get_bloginfo('name');
    }
    
    // Si el cuerpo del correo no fue definido por una plantilla personalizada, construimos el de cliente directo
    if(empty($final_body)) {
        if ($partner_id) {
            $final_body = !empty($settings['default_partner_welcome_body']) ? $settings['default_partner_welcome_body'] : "Hola [nombre_cliente], tu cuenta con [nombre_partner] ha sido creada. Tu API Key es [API_Key].";
        } else {
            // Plantilla para cliente directo (puedes crear una en los ajustes si quieres)
            $final_body = "<html><body>" . "<h2>¡Hola, [nombre_cliente]!</h2>" . "<p>Gracias por unirte a Guardián KYC. Estamos muy contentos de tenerte a bordo.</p>" . "<p><strong>Nota importante:</strong> Estamos procesando la activación de tu plan. Recibirás un segundo correo de confirmación tan pronto como tu cuenta esté completamente activa.</p>" . "<hr>" . "<p><strong>Tu API Key de Mothership es:</strong></p>" . "<p style='background-color:#f0f0f0; padding: 10px; font-family: monospace; border-radius: 5px;'>[API_Key]</p>" . "<hr>" . "<h3>Siguientes Pasos:</h3>" . "<ol>" . "<li><strong>Descarga el complemento:</strong> <a href='[enlace_descarga_plugin]'>Haz clic aquí para descargar</a>.</li>" . "<li>Sube e instala el archivo .zip en tu sitio de WordPress.</li>" . "<li>Activa el complemento y ve a la página de 'Guardián KYC'.</li>" . "<li>Pega tu API Key y haz clic en 'Guardar y Sincronizar' una vez que recibas el correo de activación.</li>" . "</ol>" . "<p>Si tienes alguna pregunta, no dudes en contactarnos.</p>" . "</body></html>";
        }
    }

    // Aplicar Reemplazos y Enviar Correo
    $final_subject = str_replace( array_keys($replacements), array_values($replacements), $final_subject );
    $final_body = str_replace( array_keys($replacements), array_values($replacements), $final_body );
    
    // Asegurarnos de que el cuerpo sea HTML
    if (strpos($final_body, '<html>') === false) {
        $final_body = "<html><body>" . wpautop($final_body) . "</body></html>";
    }
    
    wp_mail( $to, $final_subject, $final_body, $headers );
}

// --- FIN: REEMPLAZO DE gkyc_send_whitelabel_email ---

// --- INICIO: LÓGICA DE LOGIN PERSONALIZADO Y PROTECCIÓN PARA CLIENTES DIRECTOS ---

/**
 * Protege la página de recarga de saldo del Mothership.
 * Si el usuario no está logueado, lo redirige a la página de login personalizada.
 */
add_action('template_redirect', 'gkyc_mothership_protect_recharge_page');
function gkyc_mothership_protect_recharge_page() {
    // IMPORTANTE: Cambia 'pagos' por el slug real de tu página de recarga en el Mothership.
    $recharge_page_slug = 'pagos'; 
    // IMPORTANTE: Cambia 'mi-cuenta' por el slug real de tu página de login personalizada.
    $login_page_slug = 'mi-cuenta'; 

    // Si estamos en la página de recarga Y el usuario NO ha iniciado sesión...
    if ( is_page($recharge_page_slug) && !is_user_logged_in() ) {
        
        $login_page_url = home_url('/' . $login_page_slug . '/');
        
        // Le añadimos un parámetro 'redirect_to' para que sepa a dónde volver.
        $redirect_url = add_query_arg('redirect_to', urlencode(get_permalink()), $login_page_url);

        wp_redirect($redirect_url);
        exit;
    }
}
// --- FIN: LÓGICA DE LOGIN PERSONALIZADO ---

/**
 * ===================================================================
 * HERRAMIENTA DE DEPURACIÓN TEMPORAL: "CAJA NEGRA" DE CORREOS
 * ===================================================================
 */
add_filter( 'wp_mail', 'gkyc_log_email_attempts', 9999 );
function gkyc_log_email_attempts( $args ) {
    // Definimos la ruta del archivo de registro
    $log_file = WP_CONTENT_DIR . '/email_log.txt';
    
    // Preparamos el contenido que vamos a registrar
    $log_entry = "============================================\n";
    $log_entry .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "Destinatario: " . (is_array($args['to']) ? implode(', ', $args['to']) : $args['to']) . "\n";
    $log_entry .= "Asunto: " . $args['subject'] . "\n";
    
    // Registramos las cabeceras para verificar el 'From' y 'Reply-To'
    $headers_string = '';
    if (is_array($args['headers'])) {
        foreach($args['headers'] as $header) {
            if(is_string($header)) {
                $headers_string .= $header . "\r\n";
            }
        }
    } else {
        $headers_string = $args['headers'];
    }
    $log_entry .= "Cabeceras (Headers):\n" . $headers_string . "\n";
    
    // Registramos una parte del cuerpo del correo
    $log_entry .= "Cuerpo (extracto): " . substr(wp_strip_all_tags($args['message']), 0, 200) . "...\n";
    $log_entry .= "============================================\n\n";

    // Escribimos en el archivo de registro
    file_put_contents( $log_file, $log_entry, FILE_APPEND );

    // Devolvemos los argumentos originales para que el correo se intente enviar normalmente
    return $args;
}

// --- INICIO: CÓDIGO PARA RESTRINGIR VISIBILIDAD DE PAQUETES DE LICENCIAS ---

/**
 * Modifica la consulta de productos de WooCommerce en el frontend para
 * ocultar los paquetes de licencias a los usuarios que no son socios.
 */
add_action( 'woocommerce_product_query', 'gkyc_hide_packages_from_shop' );
function gkyc_hide_packages_from_shop( $q ) {

    // Solo se ejecuta en el frontend y en la consulta principal
    if ( is_admin() || ! $q->is_main_query() ) {
        return;
    }

    // Verificamos si el usuario actual NO es un socio
    if ( ! current_user_can('socio') ) {
        
        // Obtenemos la meta query actual (si existe)
        $meta_query = $q->get( 'meta_query' );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }

        // Añadimos nuestra nueva regla: excluir productos de tipo 'package'
        $meta_query[] = [
            'key'     => '_gkyc_product_type',
            'value'   => 'package',
            'compare' => '!='
        ];

        // Establecemos la nueva meta query en la consulta principal
        $q->set( 'meta_query', $meta_query );
    }
}

/**
 * Protege las páginas individuales de los paquetes de licencias para que
 * solo los socios puedan acceder a ellas directamente.
 */
add_action( 'template_redirect', 'gkyc_protect_single_package_pages' );
function gkyc_protect_single_package_pages() {

    // Solo se ejecuta si estamos viendo la página de un producto y el usuario no es socio
    if ( is_product() && ! current_user_can('socio') ) {
        
        // Obtenemos el ID del producto que se está viendo
        $product_id = get_the_ID();
        
        // Verificamos si el producto es un "paquete de licencias"
        $product_type = get_post_meta($product_id, '_gkyc_product_type', true);

        if ( $product_type === 'package' ) {
            // Si es un paquete y el usuario no es socio, lo redirigimos a la tienda
            wp_redirect( get_permalink( wc_get_page_id( 'shop' ) ) );
            exit();
        }
    }
}

// --- FIN: CÓDIGO PARA RESTRINGIR VISIBILIDAD DE PAQUETES DE LICENCIAS ---
