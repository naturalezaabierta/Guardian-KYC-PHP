<?php
/**
 * Plugin Name:       Guardián KYC
 * Plugin URI:        https://guardiankyc.com
 * Description:       Integra un sistema de verificación de identidad profesional en tu sitio WordPress.
 * Version:           1.8.0 (Cache Buster Final)
 * Author:            Guardián KYC
 * Author URI:        https://guardiankyc.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       guardian-kyc
 */

if ( ! defined( 'WPINC' ) ) { die; }

// ¡LA CLAVE ANTI-CACHÉ!
define( 'GUARDIAN_KYC_VERSION', '1.8.0' );
define( 'GUARDIAN_KYC_URL', plugin_dir_url( __FILE__ ) );
define( 'GUARDIAN_KYC_PATH', plugin_dir_path( __FILE__ ) );

function guardian_kyc_activate() {
    $verification_page_slug = 'verificacion-de-identidad';
    if ( ! get_page_by_path( $verification_page_slug ) ) {
        $page = ['post_title' => 'Verificación de Identidad', 'post_name' => $verification_page_slug, 'post_content' => '[guardian_kyc_verificacion]', 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed'];
        wp_insert_post($page);
    }
    $result_page_slug = 'verificacion-completa';
    if ( ! get_page_by_path( $result_page_slug ) ) {
        $page = ['post_title' => 'Verificación Completa', 'post_name' => $result_page_slug, 'post_content' => '[guardian_kyc_resultado]', 'post_status' => 'publish', 'post_type' => 'page', 'comment_status' => 'closed'];
        wp_insert_post($page);
    }
}
register_activation_hook( __FILE__, 'guardian_kyc_activate' );

final class Guardian_KYC {
    private static $instance = null;
    public static function get_instance() { if ( null === self::$instance ) { self::$instance = new self(); } return self::$instance; }
    private function __construct() { $this->load_dependencies(); $this->init_hooks(); }

    private function load_dependencies() {
        require_once GUARDIAN_KYC_PATH . 'includes/class-guardian-kyc-api-handler.php';
        require_once GUARDIAN_KYC_PATH . 'includes/class-guardian-kyc-shortcode-handler.php';
        require_once GUARDIAN_KYC_PATH . 'includes/class-guardian-kyc-admin-settings.php';
    }

    public function init_hooks() {
        $admin_settings = new Guardian_KYC_Admin_Settings();
        add_action( 'admin_menu', array( $admin_settings, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $admin_settings, 'settings_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        $shortcode_handler = new Guardian_KYC_Shortcode_Handler();
        add_action( 'init', array( $shortcode_handler, 'register_shortcode' ) );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        // Hook para registrar nuestras rutas de la API REST
        add_action( 'rest_api_init', array( $this, 'register_custom_rest_routes' ) );
        
        // Hooks de AJAX que aún usamos (para la página de resultados, etc.)
        add_action( 'wp_ajax_gkyc_check_status_from_mothership', array( $this, 'handle_check_status_ajax' ) );
        add_action( 'wp_ajax_save_gkyc_balance', array( $this, 'handle_save_balance_ajax' ) );
        
        add_action( 'template_redirect', array( $this, 'gatekeeper_check' ) );
        add_filter( 'get_the_author_display_name', array( $this, 'add_verification_badge_to_name' ) );
        add_filter( 'get_comment_author', array( $this, 'add_verification_badge_to_name' ), 10, 2 );
        add_action( 'show_user_profile', array( $this, 'add_verification_status_to_profile' ) );
        add_action( 'edit_user_profile', array( $this, 'add_verification_status_to_profile' ) );

        add_filter( 'manage_users_columns', array( $this, 'add_user_status_column' ) );
        add_filter( 'manage_users_custom_column', array( $this, 'render_user_status_column' ), 10, 3 );
        add_action( 'restrict_manage_users', array( $this, 'add_user_status_filter' ) );
        add_filter( 'pre_get_users', array( $this, 'filter_users_by_status' ) );

        // Nuevo hook para el actualizador de saldo en tiempo real
        add_action( 'wp_ajax_gkyc_get_current_balance', array( $this, 'get_current_balance_ajax' ) );
    }

    public function enqueue_frontend_scripts() {
        // Carga el estilo y el script principal del frontend
        wp_enqueue_style( 'guardian-kyc-frontend-styles', GUARDIAN_KYC_URL . 'assets/css/frontend-styles.css', array(), GUARDIAN_KYC_VERSION );
        wp_enqueue_script( 'guardian-kyc-frontend-scripts', GUARDIAN_KYC_URL . 'assets/js/frontend-scripts.js', array( 'jquery' ), GUARDIAN_KYC_VERSION, true );
        
        // ¡CAMBIO CLAVE! Pasamos la nueva URL de la API REST y el nonce a nuestro script principal
        wp_localize_script( 'guardian-kyc-frontend-scripts', 'gkyc_rest_obj', array( 
            'rest_url' => esc_url_raw( rest_url( 'guardian-kyc/v1/start-verification' ) ), 
            'nonce'    => wp_create_nonce( 'wp_rest' )
        ));

        // El resto de la lógica para otras páginas se queda igual
        if ( is_page('verificacion-completa') ) {
            wp_enqueue_script( 'guardian-kyc-result-logic', GUARDIAN_KYC_URL . 'assets/js/result-page.js', array('jquery'), GUARDIAN_KYC_VERSION, true );
            wp_localize_script( 'guardian-kyc-result-logic', 'guardian_kyc_result_obj', array( 
                'ajax_url'      => admin_url( 'admin-ajax.php' ), 
                'nonce'         => wp_create_nonce( 'guardian_kyc_result_nonce' ),
                'verification_page_url' => home_url('/verificacion-de-identidad/'),
                'account_page_url'      => get_permalink( get_option('woocommerce_myaccount_page_id') ) ?: home_url('/mi-cuenta/')
            ));
        }
    }

    public function enqueue_admin_scripts($hook) {
        if ( strpos($hook, 'guardian_kyc') === false && $hook !== 'users.php' ) { return; }
        wp_enqueue_style('guardian-kyc-admin-styles', GUARDIAN_KYC_URL . 'assets/css/admin-styles.css', [], GUARDIAN_KYC_VERSION);
        if ( strpos($hook, 'guardian_kyc_stats') !== false ) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
            wp_enqueue_script('guardian-kyc-admin-stats', GUARDIAN_KYC_URL . 'assets/js/admin-stats.js', ['jquery', 'chart-js'], GUARDIAN_KYC_VERSION, true);
            $admin_settings = new Guardian_KYC_Admin_Settings(); 
            $stats_data = $admin_settings->get_chart_data(); 
            wp_localize_script('guardian-kyc-admin-stats', 'gkycStatsData', $stats_data);
        }
        if ( strpos($hook, 'page_guardian_kyc') !== false && strpos($hook, 'stats') === false ) { 
            wp_enqueue_media(); 
            wp_enqueue_script('guardian-kyc-admin-scripts', GUARDIAN_KYC_URL . 'assets/js/admin-scripts.js', ['jquery'], GUARDIAN_KYC_VERSION, true); 
        }
    }

    public function handle_check_status_ajax() {
        check_ajax_referer( 'guardian_kyc_result_nonce', '_wpnonce' );
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Usuario no conectado.']);
            return;
        }

        $session_id = get_user_meta($user_id, 'guardian_kyc_pending_session_id', true);

        if (empty($session_id)) {
            $final_status = get_user_meta($user_id, 'guardian_kyc_verification_status', true);
            if (!empty($final_status) && !in_array($final_status, ['Pending', 'In Progress', 'Not Started', ''])) {
                wp_send_json_success(['status' => $final_status, 'reason' => get_user_meta($user_id, 'guardian_kyc_failure_reason', true)]);
                return;
            }
            wp_send_json_error(['message' => 'No se encontró un ID de sesión pendiente.']);
            return;
        }

        $options = get_option('guardian_kyc_options');
        $mothership_api_key = isset($options['api_key_mothership']) ? $options['api_key_mothership'] : '';
        
        $mothership_url = 'https://guardiankyc.com/wp-json/guardian-kyc/v1/check-didit-status';
        
        $body_data = [
            'mothership_api_key' => $mothership_api_key,
            'session_id'         => $session_id,
            'cache_buster'       => microtime(true)
        ];
        
        $response = wp_remote_post($mothership_url, [
            'method'    => 'POST',
            'timeout'   => 25,
            'headers'   => ['Content-Type' => 'application/json'],
            'body'      => json_encode($body_data)
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Error de conexión: ' . $response->get_error_message()]);
            return;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code >= 300) {
            wp_send_json_error(['message' => $response_body['message'] ?? 'Error en el Mothership.']);
            return;
        }

        if (isset($response_body['status']) && in_array($response_body['status'], ['Approved', 'Rejected', 'Declined', 'In Review'])) {
            update_user_meta($user_id, 'guardian_kyc_verification_status', $response_body['status']);
            update_user_meta( $user_id, 'guardian_kyc_verification_date', current_time( 'mysql' ) );
            if (isset($response_body['reason'])) {
                update_user_meta($user_id, 'guardian_kyc_failure_reason', $response_body['reason']);
            } else {
                delete_user_meta($user_id, 'guardian_kyc_failure_reason');
            }
            delete_user_meta($user_id, 'guardian_kyc_pending_session_id');
        }

        wp_send_json_success($response_body);
    }
    
    public function handle_save_balance_ajax() {
        check_ajax_referer('guardian_kyc_save_balance_nonce', 'security');
        if (isset($_POST['new_balance'])) {
            $options = get_option('guardian_kyc_options');
            $options['balance'] = sanitize_text_field($_POST['new_balance']);
            update_option('guardian_kyc_options', $options);
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    public function gatekeeper_check() {
        $options = get_option('guardian_kyc_options');
        $mode = isset($options['gatekeeper_mode']) ? $options['gatekeeper_mode'] : 'strict';
        if ( $mode === 'strict' && is_user_logged_in() && ! current_user_can( 'manage_options' ) ) {
            $status = get_user_meta( get_current_user_id(), 'guardian_kyc_verification_status', true );
            if ( $status !== 'Approved' ) {
                $allowed_pages = ['verificacion-de-identidad', 'verificacion-completa'];
                if ( ! is_page( $allowed_pages ) ) {
                    wp_redirect( home_url( '/verificacion-de-identidad/' ) );
                    exit();
                }
            }
        }
    }

    public function add_verification_status_to_profile( $user ) {
        $status = get_user_meta( $user->ID, 'guardian_kyc_verification_status', true );
        if ( empty( $status ) ) { $status = 'No Verificado'; }
        ?>
        <h3>Estado de Verificación Guardián KYC</h3>
        <table class="form-table"><tr><th><label>Estado Actual</label></th><td><strong><?php echo esc_html( $status ); ?></strong></td></tr></table>
        <?php
    }

    public function add_verification_badge_to_name( $name ) {
        $user_id = 0;
        if ( is_author() || is_singular() ) {
            global $post;
            if ( isset( $post->post_author ) ) { $user_id = $post->post_author; }
        }
        elseif (function_exists('bp_displayed_user_id') && bp_displayed_user_id()) {
            $user_id = bp_displayed_user_id();
        }
        elseif ( in_the_loop() && get_comment() ) {
            $comment = get_comment();
            if ( isset( $comment->user_id ) ) { $user_id = $comment->user_id; }
        }
        if ( $user_id > 0 ) {
            $status = get_user_meta( $user_id, 'guardian_kyc_verification_status', true );
            if ( 'Approved' === $status ) {
                $name .= ' <span class="guardian-kyc-verified-badge" title="Usuario Verificado">✔️</span>';
            }
        }
        return $name;
    }

    public function add_user_status_column($columns) {
        $columns['verification_status'] = 'Estado Verificación';
        return $columns;
    }

    public function render_user_status_column($value, $column_name, $user_id) {
        if ('verification_status' === $column_name) {
            $status = get_user_meta($user_id, 'guardian_kyc_verification_status', true);
            if (empty($status)) {
                return '<span class="gkyc-status-badge status-not-verified">No Verificado</span>';
            }
            $status_class = 'status-' . strtolower(str_replace(' ', '-', esc_attr($status)));
            return '<span class="gkyc-status-badge ' . $status_class . '">' . esc_html($status) . '</span>';
        }
        return $value;
    }

    public function add_user_status_filter() {
        if (strpos($_SERVER['REQUEST_URI'], 'users.php') === false) return;
        $current_filter = isset($_GET['verification_status_filter']) ? $_GET['verification_status_filter'] : '';
        ?>
        <select name="verification_status_filter" style="float:none; margin-left:10px;">
            <option value="">Filtrar por estado KYC</option>
            <?php
            $stati = ['Approved', 'Rejected', 'Declined', 'Pending', 'Not Verified'];
            foreach ($stati as $status) {
                printf('<option value="%s"%s>%s</option>', esc_attr($status), selected($current_filter, $status, false), esc_html($status));
            }
            ?>
        </select>
        <?php
        submit_button('Filtrar', 'action', 'filter_action', false);
    }

    public function filter_users_by_status($query) {
        global $pagenow;
        if (is_admin() && 'users.php' == $pagenow && !empty($_GET['verification_status_filter'])) {
            $status_filter = sanitize_text_field($_GET['verification_status_filter']);
            $meta_query = $query->get('meta_query') ?: [];
            if ($status_filter === 'Not Verified') {
                 $meta_query[] = ['relation' => 'OR', ['key' => 'guardian_kyc_verification_status', 'compare' => 'NOT EXISTS'], ['key' => 'guardian_kyc_verification_status', 'value' => '', 'compare' => '=']];
            } else {
                $meta_query[] = ['key' => 'guardian_kyc_verification_status', 'value' => $status_filter];
            }
            $query->set('meta_query', $meta_query);
        }
    }

    // ===== INICIO DEL CÓDIGO CORREGIDO Y MOVIDO =====
    // Estas funciones ahora están DENTRO de la clase Guardian_KYC.

    public function register_custom_rest_routes() {
        register_rest_route( 'guardian-kyc/v1', '/start-verification', [
            'methods'  => 'GET',
            'callback' => [$this, 'handle_start_verification_rest'],
            'permission_callback' => 'is_user_logged_in'
        ]);

    // ===== INICIO DEL NUEVO CÓDIGO =====
        // Esta es la nueva ruta secreta que el Mothership usará para forzar una sincronización
        register_rest_route( 'guardian-kyc/v1', '/force-sync', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_force_sync_request'],
            'permission_callback' => '__return_true' // La seguridad la manejamos dentro de la función
        ]);
    }    
    
    public function handle_start_verification_rest( WP_REST_Request $request ) {
        $api_handler = new Guardian_KYC_Api_Handler();
        $result = $api_handler->start_verification_process();
    
        if (is_wp_error($result)) {
            return new WP_Error('verification_failed', $result->get_error_message(), ['status' => 500]);
        } else {
            return new WP_REST_Response($result, 200);
        }
    }
    // ===== FIN DEL CÓDIGO CORREGIDO Y MOVIDO =====

      // ===== LA NUEVA FUNCIÓN VA AQUÍ, ANTES DE LA LLAVE DE CIERRE DE LA CLASE =====
   /**
     * [VERSIÓN FINAL 2.1 - BLINDADA]
     * Reemplaza home_url() por get_option('siteurl') para máxima compatibilidad en el contexto de la API REST.
     */
    public function handle_force_sync_request( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $sent_key = $params['mothership_api_key'] ?? '';

        if (empty($sent_key)) {
            return new WP_Error('bad_request', 'API Key no proporcionada.', ['status' => 400]);
        }
        
        $options = get_option('guardian_kyc_options');
        $stored_key = isset($options['api_key_mothership']) ? $options['api_key_mothership'] : '';
        
        // Medida de seguridad: la clave enviada debe coincidir con la guardada.
        if (empty($stored_key) || !hash_equals($stored_key, $sent_key)) {
            return new WP_Error('unauthorized', 'API Key inválida.', ['status' => 403]);
        }
        
        // ===== INICIO DE LA LÓGICA DE SINCRONIZACIÓN CORREGIDA =====
        
        $mothership_api_url = 'https://guardiankyc.com/wp-json/guardian-kyc/v1/sync';
        
        // Usamos get_option('siteurl') en lugar de home_url() para evitar errores fatales.
        $site_url_to_send = get_option('siteurl');

        // Hacemos la llamada al Mothership para obtener los datos más recientes.
        $response = wp_remote_post($mothership_api_url, [
            'method'    => 'POST',
            'timeout'   => 20,
            'headers'   => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'      => json_encode(['api_key' => $stored_key, 'site_url' => $site_url_to_send]),
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('sync_call_failed', $response->get_error_message(), ['status' => 502]);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code === 200 && $data) {
            // Éxito: Obtenemos una copia fresca de las opciones y las actualizamos.
            $current_options = get_option('guardian_kyc_options');
            $current_options['api_key_didit'] = isset($data['api_key_didit']) ? sanitize_text_field($data['api_key_didit']) : '';
            $current_options['balance']       = isset($data['balance']) ? $data['balance'] : '0.00';
            $current_options['synced_plans']  = isset($data['plans']) ? $data['plans'] : [];
            // La URL de recarga también se actualiza aquí
            $current_options['recharge_url']  = isset($data['recharge_url']) ? esc_url_raw($data['recharge_url']) : '';
            
            update_option('guardian_kyc_options', $current_options);
            
            return new WP_REST_Response(['status' => 'success', 'message' => 'Sincronización forzada completada.'], 200);
        } else {
            // El Mothership devolvió un error.
            $error_message = isset($data['message']) ? $data['message'] : 'Respuesta inesperada del Mothership.';
            return new WP_Error('sync_failed', $error_message, ['status' => 502]);
        }
        // ===== FIN DE LA LÓGICA DE SINCRONIZACIÓN CORREGIDA =====
    }

        /**
    * Manejador AJAX que devuelve el saldo actual guardado en las opciones.
    */
    public function get_current_balance_ajax() {
        // No necesitamos nonce aquí, ya que solo devuelve información pública para el usuario ya logueado.
        $options = get_option('guardian_kyc_options');
        $balance = isset($options['balance']) ? (float)$options['balance'] : 0.0;

        wp_send_json_success(['balance' => $balance]);
    }

} // <-- LA LLAVE DE CIERRE DE LA CLASE VA AQUÍ, DESPUÉS DE LAS NUEVAS FUNCIONES

function guardian_kyc_run() { 
    return Guardian_KYC::get_instance(); 
}
guardian_kyc_run();

add_action('admin_init', 'gkyc_check_for_forced_sync');
function gkyc_check_for_forced_sync() {
    if (isset($_GET['page']) && $_GET['page'] === 'guardian_kyc' && isset($_GET['sync_now'])) {
        $options = get_option('guardian_kyc_options');
        if (!empty($options['api_key_mothership'])) {
            $admin_settings = new Guardian_KYC_Admin_Settings();
            $admin_settings->sanitize_and_sync_options($options);
            wp_redirect(admin_url('admin.php?page=guardian_kyc&sync_status=success'));
            exit;
        }
    }
}

/**
 * ===================================================================
 * SISTEMA DE REPORTE DE ACTIVIDAD (HEARTBEAT)
 * ===================================================================
 */

// 1. Al activar el plugin, programamos la tarea diaria.
register_activation_hook( __FILE__, 'gkyc_schedule_daily_heartbeat' );
function gkyc_schedule_daily_heartbeat() {
    if ( ! wp_next_scheduled( 'gkyc_daily_heartbeat_event' ) ) {
        wp_schedule_event( time(), 'daily', 'gkyc_daily_heartbeat_event' );
    }
}

// 2. Al desactivar, limpiamos la tarea para no dejar basura.
register_deactivation_hook( __FILE__, 'gkyc_clear_daily_heartbeat' );
function gkyc_clear_daily_heartbeat() {
    wp_clear_scheduled_hook( 'gkyc_daily_heartbeat_event' );
}

// 3. Vinculamos nuestra tarea programada a la función que enviará el reporte.
add_action( 'gkyc_daily_heartbeat_event', 'gkyc_send_daily_heartbeat' );

/**
 * La función que se ejecuta una vez al día para reportarse al Mothership.
 */
function gkyc_send_daily_heartbeat() {
    $options = get_option( 'guardian_kyc_options' );
    $mothership_api_key = isset( $options['api_key_mothership'] ) ? $options['api_key_mothership'] : '';

    // Si no hay API key, no hacemos nada.
    if ( empty( $mothership_api_key ) ) {
        return;
    }

    // La URL del nuevo endpoint que crearemos en el Mothership.
    $heartbeat_url = 'https://guardiankyc.com/wp-json/guardian-kyc/v1/heartbeat';

    // Enviamos el "ping" de forma no bloqueante para no afectar el rendimiento del sitio cliente.
    wp_remote_post( $heartbeat_url, [
        'method'    => 'POST',
        'timeout'   => 15,
        'blocking'  => false, // Importante: no esperamos respuesta.
        'headers'   => ['Content-Type' => 'application/json'],
        'body'      => json_encode( ['mothership_api_key' => $mothership_api_key] )
    ]);
}





