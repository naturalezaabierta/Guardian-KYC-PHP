<?php
/**
 * Maneja todos los endpoints de la API REST del Mothership.
 * v2.2 - FINAL: Webhook unificado con lógica de débito dual para Partners y sub-clientes.
 */
class Mothership_Api_Handler {

    /**
     * Registra todas las rutas de la API REST para el Mothership.
     */
    /**
 * Registra todas las rutas de la API REST para el Mothership.
 */
public function register_routes() {
    // ===== NUEVO: Ruta de prueba para diagnóstico =====
    register_rest_route( 'guardian-kyc/v1', '/health-check', [ 
        'methods'  => 'GET', 
        'callback' => [$this, 'handle_health_check_request'], 
        'permission_callback' => '__return_true' 
    ]);

    // Rutas de gestión y sincronización (se quedan igual)
    register_rest_route( 'guardian-kyc/v1', '/sync', [ 'methods' => 'POST', 'callback' => [$this, 'handle_sync_request'], 'permission_callback' => '__return_true' ]);

    // Rutas de gestión de Partners (se quedan igual)
    register_rest_route( 'guardian-kyc/v1', '/partner/generate-license', [ 'methods' => 'POST', 'callback' => [$this, 'handle_partner_generate_license'], 'permission_callback' => [$this, 'check_partner_permission'] ]);
    register_rest_route( 'guardian-kyc/v1', '/partner/transfer-balance', [ 'methods' => 'POST', 'callback' => [$this, 'handle_partner_transfer_balance'], 'permission_callback' => [$this, 'check_partner_permission'] ]);
    register_rest_route( 'guardian-kyc/v1', '/partner/get-client-price', [ 
        'methods' => ['POST', 'OPTIONS'], 
        'callback' => [$this, 'handle_get_partner_client_price'], 
        'permission_callback' => '__return_true' 
    ]);
    // Rutas de la arquitectura "Plan C" (se quedan igual)
    register_rest_route( 'guardian-kyc/v1', '/didit-webhook-receiver', [ 'methods'  => ['POST', 'GET'], 'callback' => [$this, 'handle_didit_webhook_receiver'], 'permission_callback' => '__return_true' ]);
    register_rest_route( 'guardian-kyc/v1', '/check-didit-status', [ 'methods'  => 'POST', 'callback' => [$this, 'handle_check_didit_status'], 'permission_callback' => '__return_true' ]);

    // ===== AÑADE ESTE BLOQUE NUEVO AQUÍ =====
    register_rest_route( 'guardian-kyc/v1', '/partner/update-prices', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_partner_update_prices'], 
        'permission_callback' => [$this, 'check_partner_permission'] 
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/generate-payment-token', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_generate_payment_token'], 
        'permission_callback' => [$this, 'check_partner_permission'] 
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/update-branding', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_partner_update_branding'], 
        'permission_callback' => 'is_user_logged_in' 
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/submit-activation-request', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_submit_activation_request'], 
        'permission_callback' => '__return_true' 
    ]);

    // Ruta para que el mini-plugin de reventa obtenga los planes del socio.
    register_rest_route( 'guardian-kyc/v1', '/reseller/get-plans', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_reseller_get_plans'], 
        'permission_callback' => '__return_true' 
    ]);

    // ===== NUEVO ENDPOINT PARA LA AUTOMATIZACIÓN FINAL =====
    register_rest_route( 'guardian-kyc/v1', '/partner/create-license-from-resale', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_create_license_from_resale'],
        'permission_callback' => '__return_true' // La seguridad se maneja con la API Key
    ]);

        register_rest_route( 'guardian-kyc/v1', '/partner/process-activation-request', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_process_activation_request'],
        'permission_callback' => [$this, 'check_partner_permission'] 
    ]);

    // Dentro de public function register_routes()
    register_rest_route( 'guardian-kyc/v1', '/partner/submit-recharge-request', [ 
        'methods'  => ['POST', 'OPTIONS'], 
        'callback' => [$this, 'handle_submit_recharge_request'],
        'permission_callback' => '__return_true' 
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/process-recharge-request', [ 
        'methods'  => 'POST', 
        'callback' => [$this, 'handle_process_recharge_request'], 
        'permission_callback' => [$this, 'check_partner_permission'] 
    ]);

    // Pega esta nueva ruta dentro de la función register_routes()
    register_rest_route( 'guardian-kyc/v1', '/heartbeat', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_heartbeat'],
        'permission_callback' => '__return_true'
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/process-balance-sale', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_process_balance_sale'],
        'permission_callback' => '__return_true' // La seguridad se maneja con la API Key
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/auto-approve-request', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_auto_approve_request'],
        'permission_callback' => '__return_true'
    ]);

    register_rest_route( 'guardian-kyc/v1', '/partner/calculate-transfer-profit', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_calculate_transfer_profit'],
        'permission_callback' => [$this, 'check_partner_permission']
    ]);

    // --- INICIO: NUEVA RUTA PARA PILOTO AUTOMÁTICO ---
    register_rest_route( 'guardian-kyc/v1', '/partner/update-autopilot', [
        'methods'  => 'POST',
        'callback' => [$this, 'handle_update_autopilot_settings'],
        'permission_callback' => [$this, 'check_partner_permission']
    ]);
    // --- FIN: NUEVA RUTA PARA PILOTO AUTOMÁTICO ---

}

/**
 * Callback de permisos para las rutas de socios.
 * Verifica si el usuario está logueado Y tiene un nonce válido.
 */
public function check_partner_permission( WP_REST_Request $request ) {
    // Primero, la comprobación básica de que el usuario ha iniciado sesión.
    if (!is_user_logged_in()) {
        return false;
    }
    // Segundo, la comprobación del nonce para proteger contra CSRF y problemas de carga.
    return check_ajax_referer('wp_rest', false, false);
}

/**
 * Responde a la prueba de salud para confirmar que la API está activa.
 */
public function handle_health_check_request( WP_REST_Request $request ) {
    return new WP_REST_Response(['status' => 'Mothership API is running!'], 200);
}

    /**
     * Procesa los webhooks de Didit y registra las transacciones de verificación.
     * v7.2 - Unificado el registro de costos usando _transaction_amount.
     */
    public function handle_didit_webhook_receiver( WP_REST_Request $request ) {
        if ( $request->get_method() === 'GET' ) {
            wp_redirect( home_url( '/' ), 302 );
            exit;
        }

        $payload = $request->get_body();
        $data = json_decode($payload, true);

        if ( ! isset($data['vendor_data'], $data['session_id'], $data['status']) ) {
            return new WP_Error('incomplete_data', 'Faltan datos cruciales.', ['status' => 400]);
        }
        
        parse_str(sanitize_text_field($data['vendor_data']), $vendor_parts);
        $didit_key = $vendor_parts['didit_key'] ?? '';
        if (empty($didit_key)) { return new WP_Error('no_didit_key', 'Falta didit_key.', ['status' => 400]); }

        $client_post = gkyc_get_client_by_didit_key($didit_key);
        if (!$client_post) { return new WP_Error('client_not_found', 'Cliente no encontrado.', ['status' => 404]); }
        
        $webhook_secret = get_post_meta($client_post->ID, '_webhook_secret_key', true);
        if (empty($webhook_secret)) { return new WP_Error('client_no_secret', 'Cliente sin Webhook Secret Key.', ['status' => 500]); }

        $signature = $request->get_header('X-Signature') ?: ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        if (empty($signature)) { return new WP_Error('no_signature', 'Petición sin firma.', ['status' => 401]); }
        
        $computed_signature = hash_hmac('sha256', $payload, $webhook_secret);
        if (!hash_equals($computed_signature, $signature)) {
            return new WP_Error('invalid_signature', 'Firma inválida.', ['status' => 403]);
        }

        $session_id = sanitize_text_field($data['session_id']);
        $existing_transaction = get_posts(['post_type' => 'gkyc_transaction', 'posts_per_page' => -1, 'meta_key' => '_session_id', 'meta_value' => $session_id]);
        if ($existing_transaction) {
            return new WP_REST_Response(['status' => 'success', 'message' => 'Sesión ya procesada.'], 200);
        }

        $status = sanitize_text_field($data['status']);
        
        if ( in_array($status, ['Approved', 'Rejected', 'Declined', 'In Review']) ) {
            
            wp_set_current_user(1); // Asumir permisos de admin

            $client_post_id = $client_post->ID;
            $reason = $data['decision']['id_verification']['warnings'][0]['short_description'] ?? 'N/A';
            $plan_slug = $vendor_parts['plan_slug'] ?? '';
            $cost_per_verification = 0;

            if ( $status === 'Approved' ) {
                $cost_per_verification = gkyc_get_verification_cost($client_post_id, $plan_slug);
                if ($cost_per_verification > 0) {
                    $client_balance = (float) get_post_meta( $client_post_id, '_balance', true );
                    if ( $client_balance >= $cost_per_verification ) {
                        $new_balance = bcsub($client_balance, $cost_per_verification, 2);
                        update_post_meta($client_post_id, '_balance', $new_balance);
                    }
                }
            }

            $transaction_post_id = wp_insert_post([
                'post_title' => sprintf('Verificación %s - %s', $status, strtok($client_post->post_title, ' (')),
                'post_status' => 'publish',
                'post_type' => 'gkyc_transaction'
            ]);

            if ( !is_wp_error($transaction_post_id) ) {
                update_post_meta( $transaction_post_id, '_client_id', $client_post_id );
                update_post_meta( $transaction_post_id, '_session_id', $session_id );
                update_post_meta( $transaction_post_id, '_transaction_status', $status );
                update_post_meta( $transaction_post_id, '_failure_reason', $reason );
                
                // =================================================================
                // ===== INICIO: UNIFICACIÓN DEL REGISTRO DE TRANSACCIONES =====
                // =================================================================
                update_post_meta( $transaction_post_id, '_transaction_type', 'Verificación' );
                // Guardamos el costo como un número negativo para identificarlo como un débito
                update_post_meta( $transaction_post_id, '_transaction_amount', -$cost_per_verification );
                // ===============================================================
                // ===== FIN: UNIFICACIÓN DEL REGISTRO DE TRANSACCIONES =====
                // ===============================================================
                
                $owner_id = get_post_meta($client_post_id, '_reseller_owner_id', true);
                if (!empty($owner_id)) {
                    update_post_meta( $transaction_post_id, '_partner_owner_id', $owner_id );
                }
            
                delete_transient('gkyc_mothership_stats_cache');

                $client_site_url = get_post_meta($client_post_id, '_activated_domain', true);
                $mothership_api_key = get_post_meta($client_post_id, '_api_key_mothership', true);
                if (!empty($client_site_url) && !empty($mothership_api_key)) {
                    $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
                    wp_remote_post($sync_url, [
                        'method' => 'POST', 'timeout' => 15, 'blocking' => false,
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => json_encode(['mothership_api_key' => $mothership_api_key])
                    ]);
                }
            }
        }
        
        return new WP_REST_Response(['status' => 'success', 'message' => 'Webhook procesado.'], 200);
    }
    
    /**
     * Maneja la solicitud de sincronización desde un plugin cliente.
     * Busca al cliente por su API Key de Mothership, recupera sus datos,
     * guarda el dominio desde donde se activa y devuelve la URL de recarga correcta.
     */
    // --- INICIO DEL CÓDIGO DE REEMPLAZO ---
    public function handle_sync_request( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $api_key = isset($params['api_key']) ? sanitize_text_field($params['api_key']) : '';
        $site_url = isset($params['site_url']) ? esc_url_raw($params['site_url']) : '';

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API Key no proporcionada.', ['status' => 400]);
        }

        $args = [
            'post_type' => 'cliente_kyc',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_api_key_mothership', 'value' => $api_key]]
        ];
        $client_query = new WP_Query($args);

        if (!$client_query->have_posts()) {
            return new WP_Error('invalid_api_key', 'API Key de Mothership inválida o no encontrada.', ['status' => 403]);
        }

        $client_post_id = $client_query->posts[0]->ID;

        if (!empty($site_url)) {
            update_post_meta($client_post_id, '_activated_domain', $site_url);
        }

        // --- LÓGICA MEJORADA PARA OBTENER DATOS DE MARCA BLANCA ---
        $recharge_url = 'https://www.guardiankyc.com/pagos/'; // URL por defecto.
        $partner_logo_url = ''; // Logo por defecto vacío.

        $owner_id = get_post_meta($client_post_id, '_reseller_owner_id', true);
        if (!empty($owner_id)) {
            // Es un sub-cliente, obtenemos los datos de su socio.
            $partner_recharge_url = get_post_meta($owner_id, '_partner_recharge_url', true);
            if (!empty($partner_recharge_url)) {
                $recharge_url = $partner_recharge_url;
            }
            // Obtenemos el logo del socio. Usamos el logo 2x1 como principal.
            $partner_logo_url = get_post_meta($owner_id, '_partner_logo_2x1_url', true);
        }
        // --- FIN DE LA LÓGICA MEJORADA ---

        $response_data = [
            'api_key_didit' => get_post_meta($client_post_id, '_api_key_didit', true),
            'balance'       => get_post_meta($client_post_id, '_balance', true),
            'plans'         => $this->get_client_plans_data($client_post_id),
            'recharge_url'  => $recharge_url,
            'partner_logo_url' => $partner_logo_url, // <-- AÑADIMOS EL LOGO A LA RESPUESTA
        ];

        return new WP_REST_Response($response_data, 200);
    }
    // --- FIN DEL CÓDIGO DE REEMPLAZO ---
    
    public function handle_partner_generate_license( WP_REST_Request $request ) {
    // --- Pasos del 1 al 10 (Validación y Creación del Cliente) se mantienen igual ---
    $user_id = get_current_user_id();
    $partner_query = new WP_Query([
        'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
        'meta_query' => [['key' => '_user_id', 'value' => $user_id], ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']],
        'fields' => 'ids'
    ]);
    if (!$partner_query->have_posts()) { return new WP_Error('not_a_partner', 'No tienes permisos de Partner.', ['status' => 403]); }
    $partner_client_id = $partner_query->posts[0];

    $params = $request->get_json_params();
    $new_client_name = sanitize_text_field($params['client_name'] ?? '');
    $new_client_email = sanitize_email($params['client_email'] ?? '');
    $license_type = sanitize_text_field($params['license_type'] ?? '');
    $new_client_phone = sanitize_text_field($params['client_phone'] ?? '');
    $new_client_website = esc_url_raw($params['client_website'] ?? '');

    if (empty($new_client_name) || empty($license_type) || !is_email($new_client_email) || empty($new_client_phone)) {
        return new WP_Error('bad_request', 'Faltan datos obligatorios o el formato del correo es incorrecto.', ['status' => 400]);
    }

    $existing_client_query = new WP_Query([
        'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
        'meta_query' => [['key' => '_email', 'value' => $new_client_email], ['key' => '_reseller_owner_id', 'value' => $partner_client_id]]
    ]);
    if ( $existing_client_query->have_posts() ) {
        $existing_client_name = $existing_client_query->posts[0]->post_title;
        $error_message = sprintf('Error: El correo electrónico ya está en uso por el cliente "%s".', esc_html($existing_client_name));
        wp_reset_postdata();
        return new WP_Error('client_exists', $error_message, ['status' => 409]);
    }

    $limit = get_post_meta($partner_client_id, '_license_limit', true);
    $sub_clients_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => -1, 'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]], 'fields' => 'ids']);
    $assigned_licenses = $sub_clients_query->found_posts;
    if (is_numeric($limit) && $assigned_licenses >= (int)$limit) {
        return new WP_Error('limit_reached', 'Has alcanzado tu límite de licencias.', ['status' => 403]);
    }

    $didit_api_key_response = gkyc_create_didit_application($new_client_name); 
    if (is_wp_error($didit_api_key_response)) { return $didit_api_key_response; }

    $new_mothership_api_key = 'gkycm_' . wp_generate_password(32, false);

    $new_client_post = [ 'post_title'  => $new_client_name . ' (' . $new_client_email . ')', 'post_status' => 'draft', 'post_type'   => 'cliente_kyc' ];
    $new_post_id = wp_insert_post($new_client_post, true);

    if (is_wp_error($new_post_id)) { return new WP_Error('creation_failed_initial', 'Error crítico al crear el post: ' . $new_post_id->get_error_message(), ['status' => 500]); }

    update_post_meta($new_post_id, '_email', $new_client_email);
    update_post_meta($new_post_id, '_api_key_mothership', $new_mothership_api_key);
    update_post_meta($new_post_id, '_api_key_didit', $didit_api_key_response);
    update_post_meta($new_post_id, '_license_type', $license_type);
    update_post_meta($new_post_id, '_reseller_owner_id', $partner_client_id);
    update_post_meta($new_post_id, '_client_status', 'pending');
    update_post_meta($new_post_id, '_phone_number', $new_client_phone);
    if (!empty($new_client_website)) { update_post_meta($new_post_id, '_activated_domain', $new_client_website); }

    $verify_post = get_post($new_post_id);
    if ( !$verify_post || $verify_post->post_type !== 'cliente_kyc' ) {
        wp_delete_post($new_post_id, true);
        return new WP_Error('consistency_error', 'El cliente fue creado pero no se pudo verificar. La operación ha sido revertida.', ['status' => 500]);
    }

    wp_update_post(['ID' => $new_post_id, 'post_status' => 'publish']);

    // =============================================================================
    // ===== INICIO: LÓGICA DE CORREO DE BIENVENIDA WHITELABEL (FASE 4) =====
    // =============================================================================
    
    // Obtenemos los datos del socio para usarlos en el correo
    $partner_post = get_post($partner_client_id);
    $partner_name = strtok($partner_post->post_title, ' (');

    // 1. Buscamos la configuración de marca blanca del socio
    $partner_from_name = get_post_meta($partner_client_id, '_partner_from_name', true);
    $partner_reply_to = get_post_meta($partner_client_id, '_partner_reply_to_email', true);
    $partner_subject = get_post_meta($partner_client_id, '_partner_welcome_subject', true);
    $partner_body = get_post_meta($partner_client_id, '_partner_welcome_body', true);
    
    // ===== ¡NUEVO! Obtenemos el enlace de descarga global =====
    $mothership_settings = get_option('gkyc_mothership_settings');
    $plugin_download_url = isset($mothership_settings['plugin_download_url']) ? esc_url($mothership_settings['plugin_download_url']) : '';

    // 2. Preparamos las variables para los shortcodes (añadimos el nuevo)
    $replacements = [
        '[nombre_cliente]' => $new_client_name,
        '[API_Key]'        => $new_mothership_api_key,
        '[email_cliente]'  => $new_client_email,
        '[nombre_partner]' => $partner_name,
        '[enlace_descarga_plugin]' => $plugin_download_url, // <-- NUEVO SHORTCODE
    ];
    
    $to = $new_client_email;
    $subject = '';
    $body = '';
    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // 3. Verificamos si el socio tiene una plantilla personalizada
    if (!empty($partner_subject) && !empty($partner_body)) {
        // -- CASO A: USAR PLANTILLA PERSONALIZADA DEL SOCIO --
        $subject = str_replace(array_keys($replacements), array_values($replacements), $partner_subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $partner_body);
        
        $from_name = !empty($partner_from_name) ? $partner_from_name : get_bloginfo('name');
        $from_email = get_option('admin_email');
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        if (!empty($partner_reply_to) && is_email($partner_reply_to)) {
            $headers[] = 'Reply-To: ' . $partner_reply_to;
        }

    } else {
        // -- CASO B: USAR PLANTILLA POR DEFECTO DEL SISTEMA --
        $subject = '¡Bienvenido! Tus datos de acceso han sido creados';
        $body = "<html><body>";
        $body .= "<h2>¡Hola, " . esc_html($new_client_name) . "!</h2>";
        $body .= "<p>Tu cuenta ha sido creada por nuestro socio <strong>" . esc_html($partner_name) . "</strong>.</p>";
        $body .= "<hr>";
        $body .= "<p><strong>Tu API Key de Mothership es:</strong></p>";
        $body .= "<p style='background-color:#f0f0f0; padding: 10px; font-family: monospace; border-radius: 5px;'>" . esc_html($new_mothership_api_key) . "</p>";
        if ($plugin_download_url) { // Solo mostramos la descarga si el enlace está configurado
             $body .= "<p><strong>Descarga el plugin necesario aquí:</strong> <a href='" . $plugin_download_url . "'>Descargar Plugin</a></p>";
        }
        $body .= "<p>Si tienes alguna pregunta, por favor contacta directamente a " . esc_html($partner_name) . ".</p>";
        $body .= "</body></html>";
    }
    
    // 4. Enviamos el correo al nuevo cliente
    wp_mail($to, $subject, wpautop($body), $headers);

    // ===========================================================================
    // ===== FIN: LÓGICA DE CORREO DE BIENVENIDA WHITELABEL (FASE 4) =====
    // ===========================================================================

    // 11. ENVIAR NOTIFICACIÓN AL ADMIN (Se mantiene igual)
    $admin_email = get_option('admin_email');
    $subject_admin = sprintf('[Guardián KYC] Nuevo Cliente Generado por Socio: %s', $partner_name);
    $message_admin = "Hola Admin,\n\nEl socio '".esc_html($partner_name)."' acaba de generar una nueva licencia.\n\n--- Detalles del Nuevo Cliente ---\nNombre: ".esc_html($new_client_name)."\nEmail: ".esc_html($new_client_email)."\nTipo de Licencia: ".esc_html($license_type)."\nTeléfono: ".esc_html($new_client_phone)."\n\nGracias,\nEl Sistema Guardián KYC.";
    wp_mail($admin_email, $subject_admin, $message_admin);

    // 12. Devolver respuesta de éxito
    return new WP_REST_Response(['status' => 'success', 'message' => 'Licencia generada y credenciales asignadas exitosamente.'], 200);
}
    
    /**
     * Maneja la transferencia de saldo de un Partner a un sub-cliente bajo el modelo de Valor Facial.
     * v2.4 - CORRECCIÓN CRÍTICA FINAL: Asegura que el débito al Saldo Maestro sea por el costo real (monto grande).
     */
    public function handle_partner_transfer_balance( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $sub_client_id = isset($params['client_id']) ? absint($params['client_id']) : 0;
        $amount_to_credit = isset($params['amount']) ? (float)$params['amount'] : 0;

        if (empty($sub_client_id) || $amount_to_credit <= 0) {
            return new WP_Error('bad_request', 'Datos inválidos.', ['status' => 400]);
        }

        $partner_query = new WP_Query(['post_type' => 'cliente_kyc','posts_per_page' => 1, 'meta_query' => [['key' => '_user_id', 'value' => $user_id],['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) { return new WP_Error('not_a_partner', 'No tienes permisos de Partner.', ['status' => 403]); }
        $partner_client_id = $partner_query->posts[0];

        if ((int)get_post_meta($sub_client_id, '_reseller_owner_id', true) !== (int)$partner_client_id) {
            return new WP_Error('client_mismatch', 'Este cliente no te pertenece.', ['status' => 403]);
        }

        $license_type = get_post_meta($sub_client_id, '_license_type', true);
        $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'title' => $license_type]);
        if (!$plan_query->have_posts()) { return new WP_Error('plan_not_found', 'No se encontró el plan del cliente para calcular el costo.', ['status' => 500]); }
        $plan_post = $plan_query->posts[0];

        $cost_per_verification = (float) get_post_meta($plan_post->ID, '_admin_didit_cost', true);
        $partner_resale_price = (float) get_post_meta($partner_client_id, '_partner_verification_price_' . $plan_post->ID, true);
        if ($partner_resale_price <= 0) { return new WP_Error('pricing_not_set', 'No has definido un precio de reventa para este plan.', ['status' => 400]); }

        $verifications = floor($amount_to_credit / $partner_resale_price);
        $cost_for_partner = $verifications * $cost_per_verification;
        $partner_profit = $amount_to_credit - $cost_for_partner;
        
        $partner_balance = (float) get_post_meta($partner_client_id, '_balance', true);
        
        // =================================================================
        // ===== INICIO DE LA CORRECCIÓN MATEMÁTICA =====
        // =================================================================
        // ANTES: Comprobaba y restaba el valor de 'cost_for_partner' (el pequeño).
        // AHORA: Comprueba y resta el valor de 'partner_profit' (el grande, que es el costo real para el socio).

        if ($partner_balance < $partner_profit) {
            return new WP_Error('insufficient_funds', 'Saldo Maestro insuficiente para cubrir el costo de $' . number_format($partner_profit, 2) . '.', ['status' => 402]);
        }

        $sub_client_balance = (float) get_post_meta($sub_client_id, '_balance', true);
        $new_partner_balance = $partner_balance - $partner_profit; // Se resta el monto grande
        $new_sub_client_balance = $sub_client_balance + $amount_to_credit; 
        // ===============================================================
        // ===== FIN DE LA CORRECCIÓN MATEMÁTICA =====
        // ===============================================================

        update_post_meta($partner_client_id, '_balance', $new_partner_balance);
        update_post_meta($sub_client_id, '_balance', $new_sub_client_balance);

        $partner_post = get_post($partner_client_id);
        $sub_client_post = get_post($sub_client_id);

        $debit_title = sprintf('Venta de saldo a %s', strtok($sub_client_post->post_title, ' ('));
        $debit_trans_id = wp_insert_post(['post_title' => $debit_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
        if (!is_wp_error($debit_trans_id)) {
            update_post_meta($debit_trans_id, '_client_id', $partner_client_id);
            update_post_meta($debit_trans_id, '_transaction_type', 'Venta de Saldo');
            update_post_meta($debit_trans_id, '_transaction_status', 'Completed');
            update_post_meta($debit_trans_id, '_transaction_amount', -$cost_for_partner);
            // La ganancia neta se calcula como el monto que pagó el cliente MENOS el costo para el socio.
            update_post_meta($debit_trans_id, '_transaction_profit', $partner_profit);
        }

        $credit_title = sprintf('Recarga de saldo de %s', strtok($partner_post->post_title, ' ('));
        $credit_trans_id = wp_insert_post(['post_title' => $credit_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
        if (!is_wp_error($credit_trans_id)) {
            update_post_meta($credit_trans_id, '_client_id', $sub_client_id);
            update_post_meta($credit_trans_id, '_transaction_type', 'Recarga de Saldo');
            update_post_meta($credit_trans_id, '_transaction_status', 'Completed');
            update_post_meta($credit_trans_id, '_transaction_amount', $amount_to_credit);
            update_post_meta($credit_trans_id, '_partner_owner_id', $partner_client_id);
        }

        // --- INICIO: CÓDIGO DE SINCRONIZACIÓN AUTOMÁTICA PARA SUB-CLIENTE ---

        // Después de acreditar el saldo, le ordenamos al plugin del sub-cliente que se sincronice.
        $client_site_url = get_post_meta($sub_client_id, '_activated_domain', true);
        $mothership_api_key = get_post_meta($sub_client_id, '_api_key_mothership', true);

        if (!empty($client_site_url) && !empty($mothership_api_key)) {
            $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
            wp_remote_post($sync_url, [
                'method'    => 'POST',
                'timeout'   => 15,
                'blocking'  => false, // No esperamos respuesta para no ralentizar al socio.
                'headers'   => ['Content-Type' => 'application/json'],
                'body'      => json_encode(['mothership_api_key' => $mothership_api_key])
            ]);
        }

        // --- FIN: CÓDIGO DE SINCRONIZACIÓN AUTOMÁTICA ---
                
        return new WP_REST_Response(['status' => 'success', 'message' => 'Transferencia realizada con éxito.'], 200);
    }

    public function handle_get_partner_client_price( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $email = $params['email'] ?? '';
        
        // --- INICIO DE LA MEJORA ---
        // Aceptamos tanto un ID de socio como una API Key de socio
        $partner_id = isset($params['partner_id']) ? absint($params['partner_id']) : 0;
        $partner_api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';

        if (empty($partner_id) && !empty($partner_api_key)) {
            // Si no nos dan el ID pero sí la API Key, buscamos al socio.
            $partner_query = new WP_Query([
                'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
                'meta_query' => [['key' => '_api_key_mothership', 'value' => $partner_api_key]],
                'fields' => 'ids'
            ]);
            if ($partner_query->have_posts()) {
                $partner_id = $partner_query->posts[0];
            }
        }
        // --- FIN DE LA MEJORA ---

        if ( ! is_email($email) || empty($partner_id) ) {
            return new WP_Error('bad_request', 'Faltan datos o el socio es inválido.', ['status' => 400]);
        }

        $client_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_email', 'value' => $email],['key' => '_reseller_owner_id', 'value' => $partner_id]]]);
        if (!$client_query->have_posts()) {
            return new WP_Error('not_found', 'Cliente no encontrado para este socio.', ['status' => 404]);
        }
        $client_post = $client_query->posts[0];
        $client_plan_name = get_post_meta($client_post->ID, '_license_type', true);

        $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'title' => $client_plan_name]);
        if (!$plan_query->have_posts()) {
            return new WP_Error('plan_not_found', 'El plan del cliente no se encontró.', ['status' => 404]);
        }
        $plan_post = $plan_query->posts[0];
        
        // Corrección importante: la clave del precio debe usar el ID del plan.
        $price_meta_key = '_partner_verification_price_' . $plan_post->ID; 
        $price = get_post_meta($partner_id, $price_meta_key, true);

        if ( $price === '' || (float)$price <= 0 ) {
            return new WP_Error('price_not_set', 'El socio no ha definido un precio para este plan.', ['status' => 404]);
        }
        
        return new WP_REST_Response(['status' => 'success', 'plan_name' => $client_plan_name, 'price_per_verification' => (float)$price], 200);
    }
    
    /**
     * [VERSIÓN FINAL 2.1 - BLINDADA Y A PRUEBA DE FALLOS]
     * Utiliza un método de consulta más directo (get_posts) y asegura que la respuesta
     * nunca sea vacía, manejando todos los casos posibles.
     */
    public function handle_check_didit_status( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $mothership_api_key = $params['mothership_api_key'] ?? '';
        $session_id = $params['session_id'] ?? '';

        // 1. Autenticar al cliente
        $client_query = new WP_Query([
            'post_type' => 'cliente_kyc', 
            'posts_per_page' => 1, 
            'meta_query' => [['key' => '_api_key_mothership', 'value' => $mothership_api_key]]
        ]);
        if (!$client_query->have_posts()) {
            return new WP_Error('unauthorized', 'API Key de Mothership inválida.', ['status' => 403]);
        }

        // ===== INICIO DE LA MODIFICACIÓN CLAVE (CÓDIGO BLINDADO) =====
        
        $data_to_return = [];

        // Usamos get_posts, que es más directo y a menudo más fiable en contextos de API.
        $found_posts = get_posts([
            'post_type'      => 'gkyc_transaction',
            'posts_per_page' => 1,
            'meta_key'       => '_session_id',
            'meta_value'     => $session_id,
            'fields'         => 'ids' // Es más eficiente, solo necesitamos el ID.
        ]);

        if ( ! empty( $found_posts ) ) {
            // SI SE ENCUENTRA LA TRANSACCIÓN: Obtenemos sus datos
            $transaction_id = $found_posts[0];
            $status = get_post_meta($transaction_id, '_transaction_status', true);
            $reason = get_post_meta($transaction_id, '_failure_reason', true);

            // Nos aseguramos de que los valores no sean nulos para una respuesta JSON consistente.
            $data_to_return['status'] = $status ?: 'Unknown'; 
            $data_to_return['reason'] = $reason ?: '';

        } else {
            // SI NO SE ENCUENTRA LA TRANSACCIÓN: Devolvemos "In Progress"
            $data_to_return['status'] = 'In Progress';
            $data_to_return['reason'] = 'Esperando resultado del webhook.';
        }

        $response = new WP_REST_Response($data_to_return, 200);

        // Mantenemos las cabeceras anti-cache
        $response->set_headers([
            'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);

        return $response;
        // ===== FIN DE LA MODIFICACIÓN CLAVE =====
    }
    
    /**
     * Construye la estructura de planes para un cliente específico.
     * Incluye una lógica para extraer el valor numérico del precio para cálculos futuros.
     */
    private function get_client_plans_data($client_post_id) {
        $master_plans = $this->get_master_plans_from_db();
        $final_plans_data = [];

        foreach ($master_plans as $slug => $plan_data) {
            // Construye la clave del metadato, ej: '_wf_plan-basico'
            $workflow_meta_key = '_wf_' . $slug;
            $workflow_id = get_post_meta($client_post_id, $workflow_meta_key, true);

            // Solo añade el plan a la respuesta si el cliente tiene un workflow_id para él
            if (!empty($workflow_id)) {
                
                // LÓGICA CLAVE: Extraer el número del texto del precio.
                // Busca un patrón de números (ej. 1.55 o 0.95) dentro del string de precio.
                preg_match('/[0-9]+\.?[0-9]*/', $plan_data['price'], $matches);
                $numeric_price = isset($matches[0]) ? (float) $matches[0] : 0.0;

                $final_plans_data[$slug] = [
                    'name'              => $plan_data['name'],
                    'description'       => $plan_data['description'],
                    'price'             => $plan_data['price'], // El precio como texto para mostrar
                    'didit_workflow_id' => $workflow_id,
                    'price_per_verification' => $numeric_price // El precio como número para calcular
                ];
            }
        }
        return $final_plans_data;
    }

    private function get_master_plans_from_db() {
        $plans_array = [];
        $plans_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'post_status' => 'publish']);
        if ($plans_query->have_posts()) {
            foreach ($plans_query->posts as $plan_post) {
                $slug = get_post_meta($plan_post->ID, '_plan_slug', true);
                if (!empty($slug)) {
                    $plans_array[$slug] = [
                        'name'        => $plan_post->post_title,
                        'price'       => get_post_meta($plan_post->ID, '_price', true),
                        'description' => get_post_meta($plan_post->ID, '_description', true),
                    ];
                }
            }
        }
        return $plans_array;
    }

    /**
     * Maneja la actualización de los precios de reventa de un Partner desde su panel.
     */
    /**
     * Maneja la actualización de los precios de reventa de un Partner desde su panel.
     * VERSIÓN 2.0: Ahora guarda tanto precios de verificación como de venta de licencia.
     */
    public function handle_partner_update_prices( WP_REST_Request $request ) {
        // 1. Verificar que el usuario sea un Partner válido
        $user_id = get_current_user_id();
        $partner_query = new WP_Query([
            'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
            'meta_query' => [
                ['key' => '_user_id', 'value' => $user_id],
                ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']
            ],
            'fields' => 'ids'
        ]);

        if (!$partner_query->have_posts()) {
            return new WP_Error('not_a_partner', 'No tienes permisos de Partner para realizar esta acción.', ['status' => 403]);
        }
        $partner_client_id = $partner_query->posts[0];

        // 2. Procesar los datos del formulario
        $params = $request->get_params();
        $verification_prices = $params['partner_verification_prices'] ?? [];
        $license_prices = $params['partner_license_prices'] ?? [];

        // 3. Guardar cada precio de verificación
        if ( is_array($verification_prices) ) {
            foreach ($verification_prices as $meta_key => $price) {
                update_post_meta($partner_client_id, sanitize_key($meta_key), sanitize_text_field($price));
            }
        }

        // 4. Guardar cada precio de licencia
        if ( is_array($license_prices) ) {
            foreach ($license_prices as $meta_key => $price) {
                update_post_meta($partner_client_id, sanitize_key($meta_key), sanitize_text_field($price));
            }
        }

        // 5. Devolver una respuesta de éxito
        if (function_exists('gkyc_mothership_clear_partner_cache_by_id')) {
            gkyc_mothership_clear_partner_cache_by_id($partner_client_id);
        }
        return new WP_REST_Response(['status' => 'success', 'message' => 'Precios actualizados correctamente.'], 200);
    }

    /**
     * Genera un token de pago seguro para el modelo de "Valor Facial".
    * Guarda los detalles de la transacción en un transient con una vida útil corta.
    * v1.1 - CORREGIDO: La validación ahora permite un costo de cero para el partner.
    */
    public function handle_generate_payment_token( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();

        // 1. Validar los datos recibidos
        $amount_to_charge = isset($params['charge']) ? (float)$params['charge'] : 0;
        $amount_to_credit = isset($params['credit']) ? (float)$params['credit'] : 0;

        // ===== LA CORRECCIÓN CLAVE ESTÁ AQUÍ =====
        // Permitimos que el costo a cobrar sea 0 (un regalo), pero el crédito debe ser siempre positivo.
        if ($amount_to_charge < 0 || $amount_to_credit <= 0) {
            return new WP_Error('invalid_amounts', 'Los montos para la transacción no son válidos.', ['status' => 400]);
        }

        // 2. Crear un token único y seguro
        $token = 'gkyc_token_' . wp_generate_password(32, false, false);

        // 3. Preparar los datos que se guardarán temporalmente
        $transaction_data = [
            'partner_user_id'    => $user_id,
            'amount_to_charge'   => round($amount_to_charge, 2),
            'amount_to_credit'   => round($amount_to_credit, 2),
            'timestamp'          => time(),
        ];

        // 4. Guardar los datos en la base de datos temporal (transient) por 15 minutos
        set_transient($token, $transaction_data, 15 * MINUTE_IN_SECONDS);

        // 5. Devolver solo el token al frontend
        return new WP_REST_Response(['status' => 'success', 'token' => $token], 200);
    }

    /**
    * Maneja la actualización de los ajustes de branding y pagos de un socio desde su panel.
    */
        public function handle_partner_update_branding( WP_REST_Request $request ) {
        // 1. Validar que el usuario es un Partner activo
        $user_id = get_current_user_id();
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
            return new WP_Error('not_a_partner', 'No tienes permisos para realizar esta acción.', ['status' => 403]);
        }
        $partner_client_id = $partner_query->posts[0];

        // 2. Recoger, sanitizar y guardar cada uno de los campos del formulario
        $params = $request->get_params();

        // Guardar todos los campos anteriores
        if (isset($params['partner_website_url'])) { update_post_meta($partner_client_id, '_partner_website_url', esc_url_raw($params['partner_website_url'])); }
        if (isset($params['partner_recharge_url'])) { update_post_meta($partner_client_id, '_partner_recharge_url', esc_url_raw($params['partner_recharge_url'])); }
        if (isset($params['partner_logo_1x1_url'])) { update_post_meta($partner_client_id, '_partner_logo_1x1_url', esc_url_raw($params['partner_logo_1x1_url'])); }
        if (isset($params['partner_logo_2x1_url'])) { update_post_meta($partner_client_id, '_partner_logo_2x1_url', esc_url_raw($params['partner_logo_2x1_url'])); }
        if (isset($params['partner_favicon_url'])) { update_post_meta($partner_client_id, '_partner_favicon_url', esc_url_raw($params['partner_favicon_url'])); }
        if (isset($params['partner_paypal_link'])) { update_post_meta($partner_client_id, '_partner_paypal_link', esc_url_raw($params['partner_paypal_link'])); }
        if (isset($params['partner_usdt_wallet'])) { update_post_meta($partner_client_id, '_partner_usdt_wallet', sanitize_text_field($params['partner_usdt_wallet'])); }
        if (isset($params['partner_other_payments'])) { update_post_meta($partner_client_id, '_partner_other_payments', wp_kses_post($params['partner_other_payments'])); }
        if (isset($params['partner_support_email'])) { update_post_meta($partner_client_id, '_partner_support_email', sanitize_email($params['partner_support_email'])); }
        if (isset($params['partner_support_whatsapp'])) { update_post_meta($partner_client_id, '_partner_support_whatsapp', sanitize_text_field($params['partner_support_whatsapp'])); }

        // ===== ¡AQUÍ GUARDAMOS LOS NUEVOS DATOS! =====
        if (isset($params['partner_auto_credit_enabled'])) {
            update_post_meta($partner_client_id, '_partner_auto_credit_enabled', sanitize_text_field($params['partner_auto_credit_enabled']));
        }
        if (isset($params['partner_auto_credit_amount'])) {
            // Aseguramos que el valor guardado sea un número válido
            $amount = filter_var($params['partner_auto_credit_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            update_post_meta($partner_client_id, '_partner_auto_credit_amount', $amount);
        }

        // 3. Devolver respuesta de éxito
        if (function_exists('gkyc_mothership_clear_partner_cache_by_id')) {
            gkyc_mothership_clear_partner_cache_by_id($partner_client_id);
        }

        // --- INICIO: CÓDIGO DE SINCRONIZACIÓN EN CASCADA PARA SUB-CLIENTES ---

        // Después de que un socio actualiza su marca, buscamos a todos sus sub-clientes.
        $sub_clients_query = new WP_Query([
            'post_type' => 'cliente_kyc',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]],
            'fields' => 'ids' // Solo necesitamos los IDs, es más rápido.
        ]);

        if ($sub_clients_query->have_posts()) {
            // Si encontramos sub-clientes, iteramos sobre cada uno.
            foreach ($sub_clients_query->posts as $sub_client_id) {
                $client_site_url = get_post_meta($sub_client_id, '_activated_domain', true);
                $mothership_api_key = get_post_meta($sub_client_id, '_api_key_mothership', true);

                // Si el sub-cliente tiene un sitio activado y una API Key...
                if (!empty($client_site_url) && !empty($mothership_api_key)) {
                    // ...le enviamos la orden de forzar la sincronización.
                    $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
                    wp_remote_post($sync_url, [
                        'method'    => 'POST',
                        'timeout'   => 15,
                        'blocking'  => false, // Importante: No esperamos respuesta para no ralentizar al socio.
                        'headers'   => ['Content-Type' => 'application/json'],
                        'body'      => json_encode(['mothership_api_key' => $mothership_api_key])
                    ]);
                }
            }
        }
        wp_reset_postdata();

        // --- FIN: CÓDIGO DE SINCRONIZACIÓN EN CASCADA ---
        return new WP_REST_Response(['status' => 'success', 'message' => 'Ajustes guardados con éxito.'], 200);
    }

    /**
    * Endpoint para el mini-plugin de reventa.
    * Valida la API Key de un socio y devuelve los planes y datos de configuración.
    * v2.3 - Añadido el logo del partner a la respuesta.
    */
    public function handle_reseller_get_plans( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $api_key = isset($params['api_key']) ? sanitize_text_field($params['api_key']) : '';

        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API Key no proporcionada.', ['status' => 401]);
        }

        $partner_query = new WP_Query([
            'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
            'meta_query' => [ 'relation' => 'AND', ['key' => '_api_key_mothership', 'value' => $api_key], ['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']],
            'fields' => 'ids'
        ]);

        if (!$partner_query->have_posts()) {
            return new WP_Error('invalid_api_key', 'API Key de Socio inválida.', ['status' => 403]);
        }
        $partner_client_id = $partner_query->posts[0];
        // --- NUEVO: CÁLCULO DE STOCK DE LICENCIAS ---
        $license_limit = get_post_meta($partner_client_id, '_license_limit', true);
        $sub_clients_query = new WP_Query([
            'post_type' => 'cliente_kyc',
            'posts_per_page' => -1,
            'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]],
            'fields' => 'ids'
        ]);
        $assigned_licenses = $sub_clients_query->found_posts;

        $licenses_remaining = is_numeric($license_limit) ? ($license_limit - $assigned_licenses) : 'Ilimitadas';
        // --- FIN DEL NUEVO CÓDIGO ---

        $plans_for_sale = [];
        $all_plans_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']);

        if ($all_plans_query->have_posts()) {
            foreach ($all_plans_query->posts as $plan_post) {
                if (stripos($plan_post->post_title, 'Partner') !== false) { continue; }
                $price_meta_key = '_partner_license_price_' . $plan_post->ID;
                $price = (float) get_post_meta($partner_client_id, $price_meta_key, true);
                if ($price > 0) {
                    $plans_for_sale[] = [
                        'name'        => $plan_post->post_title,
                        'slug'        => $plan_post->post_name,
                        'description' => get_post_meta($plan_post->ID, '_description', true),
                        'price'       => $price
                    ];
                }
            }
        }
        wp_reset_postdata();

        // Esta es la única y correcta declaración de $response_data
        $response_data = [
            'status' => 'success',
            'partner_logo_2x1_url' => get_post_meta($partner_client_id, '_partner_logo_2x1_url', true),
            // --- NUEVOS DATOS ---
            'master_balance' => (float) get_post_meta($partner_client_id, '_balance', true),
            'licenses_remaining' => $licenses_remaining,
            // --- FIN DE NUEVOS DATOS ---
            'plans' => $plans_for_sale,
            'payment_links' => [
                'paypal'         => get_post_meta($partner_client_id, '_partner_paypal_link', true),
                'stripe'         => get_post_meta($partner_client_id, '_partner_stripe_link', true),
                'usdt_wallet'    => get_post_meta($partner_client_id, '_partner_usdt_wallet', true),
                'other_payments' => get_post_meta($partner_client_id, '_partner_other_payments', true),
                'whatsapp'       => get_post_meta($partner_client_id, '_partner_support_whatsapp', true),
                'whatsapp_logo'  => get_post_meta($partner_client_id, '_partner_whatsapp_logo_url', true)
            ]
        ];

        return new WP_REST_Response($response_data, 200);
    }

    /**
     * v2.1 - CON FAIL-SAFE: Recibe los datos del cliente, valida el stock del socio.
     * Si no hay stock, crea una solicitud pendiente. Si hay stock, crea el cliente.
     */
    // --- REEMPLAZA ESTA FUNCIÓN COMPLETA ---
public function handle_create_license_from_resale( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    
    $partner_api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';
    $plan_slug = isset($params['plan_slug']) ? sanitize_key($params['plan_slug']) : '';
    $customer_name = isset($params['customer_name']) ? sanitize_text_field($params['customer_name']) : '';
    $customer_email = isset($params['customer_email']) ? sanitize_email($params['customer_email']) : '';
    $customer_phone = isset($params['customer_phone']) ? sanitize_text_field($params['customer_phone']) : '';
    $order_id = isset($params['order_id']) ? absint($params['order_id']) : 0;

    if (empty($partner_api_key) || empty($plan_slug) || empty($customer_name) || !is_email($customer_email)) {
        return new WP_Error('bad_request', 'Faltan datos obligatorios o el email es inválido.', ['status' => 400]);
    }
    
    $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_api_key_mothership', 'value' => $partner_api_key]], 'fields' => 'ids']);
    if (!$partner_query->have_posts()) { return new WP_Error('invalid_partner', 'API Key de Socio inválida.', ['status' => 403]); }
    $partner_client_id = $partner_query->posts[0];
    
    // La lógica de "fail-safe" por falta de stock se queda intacta y funcional
    // --- INICIO: LÓGICA DE PRE-APROBACIÓN AUTOMÁTICA POR INVENTARIO ---
    // Esta es la lógica que se ejecuta cuando la venta viene de WooCommerce (Kit de Reventa)
    // y decide si la solicitud debe ser aprobada automáticamente o quedar pendiente.

    $is_auto_approved = false; // Bandera para saber si se aprobó automáticamente

    $limit = get_post_meta($partner_client_id, '_license_limit', true);
    if ( is_numeric($limit) ) {
        $sub_clients_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => -1, 'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]], 'fields' => 'ids']);
        if ( $sub_clients_query->found_posts >= (int)$limit ) {
            $request_title = sprintf('Solicitud PENDIENTE (SIN STOCK) de %s para %s', $plan_slug, $customer_email);
            $request_id = wp_insert_post(['post_title' => $request_title, 'post_status' => 'pending', 'post_type' => 'gkyc_act_request',]);
            if (!is_wp_error($request_id)) {
                update_post_meta($request_id, '_partner_owner_id', $partner_client_id);
                update_post_meta($request_id, '_customer_name', $customer_name);
                update_post_meta($request_id, '_customer_email', $customer_email);
                update_post_meta($request_id, '_customer_phone', $customer_phone);
                update_post_meta($request_id, '_plan_slug', $plan_slug);
                update_post_meta($request_id, '_woocommerce_order_id', $order_id);
                update_post_meta($request_id, '_request_origin', 'woocommerce');
            }
            $partner_email = get_post_meta($partner_client_id, '_partner_support_email', true) ?: get_post_meta($partner_client_id, '_email', true);
            if(is_email($partner_email)){
                $subject = '¡Acción Requerida! Venta de Licencia sin Stock Disponible';
                $message = "Hola, has realizado una venta de licencia a ".esc_html($customer_name)." a través de WooCommerce, pero no tienes inventario de licencias disponible...";
                wp_mail($partner_email, $subject, $message);
            }
            return new WP_REST_Response(['status' => 'success_pending_fulfillment', 'message' => 'Venta registrada, pero pendiente de stock del socio.'], 202);
        }
    } else {
        // Si el socio tiene licencias ilimitadas o tiene stock, la pre-aprobamos.
        $is_auto_approved = true;
    }
    
    $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'name' => $plan_slug, 'fields' => 'ids']);
    if (!$plan_query->have_posts()) { return new WP_Error('plan_not_found', 'El plan solicitado no existe.', ['status' => 404]); }
    $license_type = get_post($plan_query->posts[0])->post_title;
    
    // Si fue pre-aprobada, creamos la licencia directamente.
    // Si no, la lógica de abajo creará la solicitud pendiente como antes.
    if ($is_auto_approved) {
        // Creamos la licencia para el cliente final y notificamos a todos.
        // Esta lógica es similar a la de la aprobación manual, pero se dispara aquí.
        $new_mothership_api_key = 'gkycm_' . wp_generate_password(32, false);
        $new_client_post_id = wp_insert_post(['post_title' => $customer_name . ' (' . $customer_email . ')', 'post_status' => 'publish', 'post_type' => 'cliente_kyc']);
        
        // Guardamos todos los datos del nuevo cliente
        update_post_meta($new_client_post_id, '_email', $customer_email);
        update_post_meta($new_client_post_id, '_api_key_mothership', $new_mothership_api_key);
        update_post_meta($new_client_post_id, '_license_type', $license_type);
        update_post_meta($new_client_post_id, '_reseller_owner_id', $partner_client_id);
        update_post_meta($new_client_post_id, '_client_status', 'pending'); // Sigue pendiente para ti, el admin
        update_post_meta($new_client_post_id, '_phone_number', $customer_phone);

        // Notificamos al socio que se ha realizado una venta automática
        $partner_email = get_post_meta($partner_client_id, '_partner_support_email', true) ?: get_post_meta($partner_client_id, '_email', true);
        if (is_email($partner_email)) {
            $partner_name = strtok(get_the_title($partner_client_id), ' (');
            $subject_partner = "¡Venta de Licencia Automática Exitosa!";
            $body_partner = "<html><body><h2>¡Felicidades, " . esc_html($partner_name) . "!</h2><p>Has realizado una nueva venta de licencia a <strong>".esc_html($customer_name)."</strong>. Como tenías inventario disponible, la hemos procesado por ti. La solicitud ya está en manos del administrador para la configuración final.</p></body></html>";
            wp_mail($partner_email, $subject_partner, $body_partner, ['Content-Type: text/html; charset=UTF-8']);
        }

        // Notificamos al cliente final
        gkyc_send_whitelabel_email($customer_email, '', '', $new_client_post_id);

        // Creamos una solicitud para el admin, pero con un estado que indique que está lista.
        $request_title = sprintf('Solicitud LISTA PARA CONFIGURAR de %s para %s', $plan_slug, $customer_email);
        $request_id = wp_insert_post(['post_title' => $request_title, 'post_status' => 'private', 'post_type' => 'gkyc_act_request']); // 'private' para diferenciarla
        update_post_meta($request_id, '_partner_owner_id', $partner_client_id);
        update_post_meta($request_id, '_customer_name', $customer_name);
        update_post_meta($request_id, '_customer_email', $customer_email);
        update_post_meta($request_id, '_plan_slug', $plan_slug);
        update_post_meta($request_id, '_woocommerce_order_id', $order_id);
        update_post_meta($request_id, '_request_origin', 'woocommerce_auto'); // Nuevo origen

        return new WP_REST_Response(['status' => 'success', 'message' => 'Licencia pre-aprobada y solicitud enviada al administrador.'], 200);
    }
    // Si no se auto-aprobó (porque no había stock), el código original de abajo se encarga de crear la solicitud pendiente.
    // Esta parte del código ya no es necesaria porque la lógica de "sin stock" ya está arriba.
    /*
    $new_mothership_api_key = 'gkycm_' . wp_generate_password(32, false);
    $new_client_post = ['post_title' => $customer_name . ' (' . $customer_email . ')', 'post_status' => 'publish', 'post_type' => 'cliente_kyc'];
    $new_post_id = wp_insert_post($new_client_post);
    
    update_post_meta($new_post_id, '_email', $customer_email);
    update_post_meta($new_post_id, '_api_key_mothership', $new_mothership_api_key);
    update_post_meta($new_post_id, '_license_type', $license_type);
    update_post_meta($new_post_id, '_reseller_owner_id', $partner_client_id);
    update_post_meta($new_post_id, '_client_status', 'pending');
    update_post_meta($new_post_id, '_phone_number', $customer_phone);
    
    $partner_post = get_post($partner_client_id);
    $partner_name = strtok($partner_post->post_title, ' (');
    

    // 1. Notificación al Socio (CORREGIDA Y COMPLETA)
    $partner_email = get_post_meta($partner_client_id, '_partner_support_email', true) ?: get_post_meta($partner_client_id, '_email', true);
    if (is_email($partner_email)) {
        $subject_partner = "¡Nueva Venta de Licencia Automática: " . $license_type . "!";
        $body_partner = "<html><body><h2>¡Felicidades, " . esc_html($partner_name) . "!</h2><p>Has realizado una nueva venta automática a través de tu sitio web...</p></body></html>";
        $headers_partner = [ 'Content-Type: text/html; charset=UTF-8', 'From: Guardián KYC <' . get_option('admin_email') . '>' ];
        wp_mail($partner_email, $subject_partner, $body_partner, $headers_partner);
    }

    // Notificación al Administrador
    $admin_email = get_option('admin_email');
    $subject_admin = sprintf('[Guardián KYC] Nueva Venta Automática de Socio: %s', $partner_name);
    $message_admin = "<html><body><h2>Nueva Venta Automática</h2><p><strong>Socio:</strong> ".esc_html($partner_name)."</p><h3>Detalles:</h3><ul><li>Nombre: ".esc_html($customer_name)."</li><li>Email: ".esc_html($customer_email)."</li><li>Licencia: ".esc_html($license_type)."</li></ul><p>El cliente está 'Pendiente'.</p></body></html>";
    wp_mail($admin_email, $subject_admin, $message_admin, ['Content-Type: text/html; charset=UTF-8']);
    
    // Correo de Bienvenida al Sub-Cliente (LLAMADA CORREGIDA)
    if (function_exists('gkyc_send_whitelabel_email')) {
        gkyc_send_whitelabel_email($customer_email, '', '', $new_post_id);
    }

    return new WP_REST_Response(['status' => 'success', 'message' => 'Licencia creada y registrada.'], 200);
    */
}

// --- INICIO: NUEVA FUNCIÓN PARA GUARDAR AJUSTES DE PILOTO AUTOMÁTICO ---
/**
 * Maneja la actualización de los ajustes del Piloto Automático de un socio.
 */
public function handle_update_autopilot_settings( WP_REST_Request $request ) {
    // 1. Validar que el usuario es un Partner activo
    $user_id = get_current_user_id();
    $partner_query = new WP_Query([
        'post_type' => 'cliente_kyc', 'posts_per_page' => 1,
        'meta_query' => [['key' => '_user_id', 'value' => $user_id]],
        'fields' => 'ids'
    ]);

    if (!$partner_query->have_posts()) {
        return new WP_Error('not_a_partner', 'No tienes permisos para esta acción.', ['status' => 403]);
    }
    $partner_client_id = $partner_query->posts[0];

    // 2. Recoger, sanitizar y guardar cada uno de los campos del formulario
    $params = $request->get_params();

    $is_enabled = isset($params['autopilot_enabled']) && $params['autopilot_enabled'] === 'yes' ? 'yes' : 'no';
    update_post_meta($partner_client_id, '_autopilot_enabled', $is_enabled);

    if (isset($params['autopilot_threshold'])) {
        update_post_meta($partner_client_id, '_autopilot_threshold', absint($params['autopilot_threshold']));
    }
    if (isset($params['autopilot_recharge_amount'])) {
        update_post_meta($partner_client_id, '_autopilot_recharge_amount', absint($params['autopilot_recharge_amount']));
    }

    // 3. Devolver respuesta de éxito
    return new WP_REST_Response(['status' => 'success', 'message' => 'Reglas del Piloto Automático guardadas con éxito.'], 200);
}
// --- FIN: NUEVA FUNCIÓN ---

// --- AHORA REEMPLAZA ESTA OTRA FUNCIÓN EN EL MISMO ARCHIVO ---

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
                $final_subject = $partner_subject_custom;
                $final_body = $partner_body_custom;
            } else {
                // Opción 2: El socio NO tiene plantilla. Usamos la de cliente directo como base.
                $final_subject = '¡Bienvenido a Guardián KYC! Tus datos de acceso';
            }
        } else { // Es un cliente directo
            $final_subject = '¡Bienvenido a Guardián KYC! Tus datos de acceso';
        }
    }
    
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
    
    if(empty($body_template) && empty($partner_body_custom)) {
        // Generamos el cuerpo del correo de cliente directo, que ahora sirve como base para sub-clientes sin plantilla personalizada
        $base_body = "<html><body>" . "<h2>¡Hola, [nombre_cliente]!</h2>";
        if($partner_id) {
            $base_body .= "<p>Gracias por unirte. Tu cuenta ha sido creada a través de nuestro socio <strong>[nombre_partner]</strong>.</p>";
        } else {
            $base_body .= "<p>Gracias por unirte a Guardián KYC. Estamos muy contentos de tenerte a bordo.</p>";
        }
        $base_body .= "<p><strong>Nota importante:</strong> Estamos procesando la activación de tu plan. Recibirás un segundo correo de confirmación tan pronto como tu cuenta esté completamente activa.</p>" . "<hr>" . "<p><strong>Tu API Key de Mothership es:</strong></p>" . "<p style='background-color:#f0f0f0; padding: 10px; font-family: monospace; border-radius: 5px;'>[API_Key]</p>" . "<hr>" . "<h3>Siguientes Pasos:</h3>" . "<ol>" . "<li><strong>Descarga el complemento:</strong> <a href='[enlace_descarga_plugin]'>Haz clic aquí para descargar</a>.</li>" . "<li>Sube e instala el archivo .zip en tu sitio de WordPress.</li>" . "<li>Activa el complemento y ve a la página de 'Guardián KYC'.</li>" . "<li>Pega tu API Key y haz clic en 'Guardar y Sincronizar' una vez que recibas el correo de activación.</li>" . "</ol>";
        if($partner_id){
             $base_body .= "<p>Si tienes alguna pregunta, contacta directamente a [nombre_partner].</p>";
        } else {
             $base_body .= "<p>Si tienes alguna pregunta, no dudes en contactarnos.</p>";
        }
        $base_body .= "</body></html>";
        $final_body = $base_body;
    }

    $final_subject = str_replace( array_keys($replacements), array_values($replacements), $final_subject );
    $final_body = str_replace( array_keys($replacements), array_values($replacements), $final_body );
    
    wp_mail( $to, $final_subject, wpautop( $final_body ), $headers );
}

    /**
    * Maneja la recepción de una nueva solicitud de activación desde el Kit de Reventa.
    * No crea la licencia, solo guarda la solicitud y notifica al socio.
    */
    public function handle_submit_activation_request( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        // 1. Recibir y validar datos básicos
        $api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';
        $customer_email = isset($params['customer_email']) ? sanitize_email($params['customer_email']) : '';

        if (empty($api_key) || !is_email($customer_email)) {
            return new WP_Error('bad_request', 'Faltan datos o el email es inválido.', ['status' => 400]);
        }

        // 2. Encontrar al socio por su API Key
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_api_key_mothership', 'value' => $api_key]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) {
            return new WP_Error('invalid_api_key', 'API Key de Socio inválida.', ['status' => 403]);
        }
        $partner_client_id = $partner_query->posts[0];

        // 3. Crear el post de "Solicitud de Activación"
        $request_title = sprintf('Solicitud de %s para %s', sanitize_text_field($params['plan_slug']), $customer_email);
        $request_id = wp_insert_post([
            'post_title'    => $request_title,
            'post_status'   => 'pending', // Usamos el estado 'pendiente'
            'post_type'     => 'gkyc_act_request',
        ]);

        if (is_wp_error($request_id)) {
            return new WP_Error('creation_failed', 'No se pudo crear la solicitud.', ['status' => 500]);
        }

        // 4. Guardar todos los datos del cliente en la solicitud
        update_post_meta($request_id, '_partner_owner_id', $partner_client_id);
        update_post_meta($request_id, '_customer_name', sanitize_text_field($params['customer_name']));
        update_post_meta($request_id, '_customer_email', $customer_email);
        update_post_meta($request_id, '_customer_phone', sanitize_text_field($params['customer_phone']));
        update_post_meta($request_id, '_customer_web', esc_url_raw($params['customer_web']));
        update_post_meta($request_id, '_usdt_hash', sanitize_text_field($params['usdt_hash']));
        update_post_meta($request_id, '_plan_slug', sanitize_key($params['plan_slug']));
        // Guardamos el nuevo ID de transacción
        update_post_meta($request_id, '_transaction_id', sanitize_text_field($params['transaction_id'])); // <-- NUEVA LÍNEA
        
        // 5. Notificar al socio por correo electrónico
        $partner_post = get_post($partner_client_id);
        $partner_name = strtok($partner_post->post_title, ' (');
        $partner_email = get_post_meta($partner_client_id, '_partner_support_email', true);
        if (!is_email($partner_email)) { $partner_email = get_post_meta($partner_client_id, '_email', true); }

        if (is_email($partner_email)) {
            $subject = '¡Tienes una nueva solicitud de licencia pendiente!';
            $message = "<html><body>";
            $message .= "<h2>Hola, " . esc_html($partner_name) . "</h2>";
            $message .= "<p>Has recibido una nueva solicitud de activación de licencia a través de tu Kit de Reventa.</p>";
            $message .= "<h3>Detalles de la Solicitud:</h3>";
            $message .= "<ul>";
            $message .= "<li><strong>Cliente:</strong> " . esc_html($params['customer_name']) . "</li>";
            $message .= "<li><strong>Email:</strong> " . esc_html($customer_email) . "</li>";
            $message .= "<li><strong>Plan Solicitado:</strong> " . esc_html($params['plan_slug']) . "</li>";
            // Añadimos los IDs al correo para fácil verificación
            if (!empty($params['transaction_id'])) {
                $message .= "<li><strong>ID Transacción (PayPal/Stripe):</strong> " . esc_html($params['transaction_id']) . "</li>"; // <-- NUEVA LÍNEA
            }
            if (!empty($params['usdt_hash'])) {
                $message .= "<li><strong>Hash (USDT):</strong> " . esc_html($params['usdt_hash']) . "</li>"; // <-- NUEVA LÍNEA
            }
            $message .= "</ul>";
            $message .= "<p>Por favor, verifica que has recibido el pago de este cliente y luego ve a tu Panel de Socio para aprobar la solicitud.</p>";
            $message .= "</body></html>";

            wp_mail($partner_email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
        }

        return new WP_REST_Response(['status' => 'success', 'message' => 'Solicitud enviada correctamente.'], 200);
    }

    // Reemplaza tu función con esta versión final
    public function handle_process_activation_request( WP_REST_Request $request ) {
        // ... (todo el código de validación inicial se queda igual) ...
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $request_id = isset($params['request_id']) ? absint($params['request_id']) : 0;
        $action = isset($params['action']) ? sanitize_key($params['action']) : '';

        if (empty($request_id) || !in_array($action, ['approve', 'reject'])) {
            return new WP_Error('bad_request', 'Faltan datos en la solicitud.', ['status' => 400]);
        }
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_user_id', 'value' => $user_id]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) { return new WP_Error('not_a_partner', 'No eres un socio válido.', ['status' => 403]); }
        $partner_client_id = $partner_query->posts[0];

        if ((int)get_post_meta($request_id, '_partner_owner_id', true) !== $partner_client_id) {
            return new WP_Error('permission_denied', 'Esta solicitud no te pertenece.', ['status' => 403]);
        }
        
        $current_status = get_post_status($request_id);

        // ===== INICIO DE LA MEJORA FINAL =====
        // Si la solicitud ya no está 'pendiente', significa que ya fue procesada por la primera petición.
        // En lugar de devolver un error, devolvemos una respuesta de ÉXITO.
        // Esto hace que la segunda petición (el "eco") termine silenciosamente sin crear un duplicado.
        if ($current_status !== 'pending') {
            return new WP_REST_Response(['status' => 'success', 'message' => 'La solicitud ya ha sido procesada.'], 200);
        }
        // ===== FIN DE LA MEJORA FINAL =====

        if ($action === 'approve') {
            wp_update_post(['ID' => $request_id, 'post_status' => 'private']);
        }

        if ($action === 'reject') {
            wp_update_post(['ID' => $request_id, 'post_status' => 'trash']);
            return new WP_REST_Response(['status' => 'success', 'message' => 'Solicitud rechazada correctamente.'], 200);
        }

        if ($action === 'approve') {
            // ... (TODA LA LÓGICA DE APROBACIÓN QUE YA TENÍAMOS SE QUEDA EXACTAMENTE IGUAL AQUÍ) ...
            // A. Verificar si el socio tiene licencias disponibles
            $limit = get_post_meta($partner_client_id, '_license_limit', true);
            $sub_clients_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => -1, 'meta_query' => [['key' => '_reseller_owner_id', 'value' => $partner_client_id]], 'fields' => 'ids']);
            if (is_numeric($limit) && $sub_clients_query->found_posts >= (int)$limit) {
                wp_update_post(['ID' => $request_id, 'post_status' => 'pending']);
                return new WP_Error('limit_reached', 'Has alcanzado tu límite de licencias. No se puede aprobar.', ['status' => 402]);
            }

            // B. Recoger los datos de la solicitud
            $customer_name = get_post_meta($request_id, '_customer_name', true);
            $customer_email = get_post_meta($request_id, '_customer_email', true);
            $customer_phone = get_post_meta($request_id, '_customer_phone', true);
            $customer_web = get_post_meta($request_id, '_customer_web', true);
            $plan_slug = get_post_meta($request_id, '_plan_slug', true);
            $transaction_id = get_post_meta($request_id, '_transaction_id', true);
            $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_plan_slug', 'value' => $plan_slug]]]);
            $license_type = $plan_query->have_posts() ? $plan_query->posts[0]->post_title : 'Licencia Estándar';

            // C. Crear la nueva licencia para el cliente final
            $new_mothership_api_key = 'gkycm_' . wp_generate_password(32, false);
            $new_client_post_id = wp_insert_post([
                'post_title' => $customer_name . ' (' . $customer_email . ')',
                'post_status' => 'publish',
                'post_type' => 'cliente_kyc'
            ]);

            update_post_meta($new_client_post_id, '_email', $customer_email);
            update_post_meta($new_client_post_id, '_api_key_mothership', $new_mothership_api_key);
            update_post_meta($new_client_post_id, '_license_type', $license_type);
            update_post_meta($new_client_post_id, '_reseller_owner_id', $partner_client_id);
            update_post_meta($new_client_post_id, '_client_status', 'active');
            update_post_meta($new_client_post_id, '_phone_number', $customer_phone);
            if (!empty($customer_web)) { update_post_meta($new_client_post_id, '_activated_domain', $customer_web); }
            if (!empty($transaction_id)) { update_post_meta($new_client_post_id, '_transaction_id', $transaction_id); }

            // D. Lógica de Saldo de Regalo Automático
            $auto_credit_enabled = get_post_meta($partner_client_id, '_partner_auto_credit_enabled', true);
            if ($auto_credit_enabled === 'yes') {
                $auto_credit_amount = (float)get_post_meta($partner_client_id, '_partner_auto_credit_amount', true);
                $partner_balance = (float)get_post_meta($partner_client_id, '_balance', true);
                if ($auto_credit_amount > 0 && $partner_balance >= $auto_credit_amount) {
                    update_post_meta($partner_client_id, '_balance', $partner_balance - $auto_credit_amount);
                    update_post_meta($new_client_post_id, '_balance', $auto_credit_amount);
                }
            }

            // E. Enviar correo de bienvenida al cliente final (Lógica Whitelabel Corregida)
            $partner_post = get_post($partner_client_id);
            $partner_name = strtok($partner_post->post_title, ' (');

            // Obtenemos los datos de marca blanca del socio
            $partner_subject = get_post_meta($partner_client_id, '_partner_welcome_subject', true);
            $partner_body = get_post_meta($partner_client_id, '_partner_welcome_body', true);
            $partner_from_name = get_post_meta($partner_client_id, '_partner_from_name', true);

            // Si el socio no definió un asunto o cuerpo, usamos unos por defecto.
            $final_subject = !empty($partner_subject) ? $partner_subject : '¡Bienvenido! Tu licencia ha sido activada por [nombre_partner]';
            $final_body = !empty($partner_body) ? $partner_body : "Hola [nombre_cliente],\n\nTu licencia ha sido activada por nuestro socio [nombre_partner].\n\nTu API Key para integrar el servicio es:\n[API_Key]\n\nPara empezar, descarga el plugin cliente desde este enlace:\n[enlace_descarga_plugin]";

            // Si el socio no definió un nombre de remitente, usamos el nombre de su cuenta.
            $final_from_name = !empty($partner_from_name) ? $partner_from_name : $partner_name;

            // Preparamos las cabeceras del correo con el nombre del remitente correcto.
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $final_from_name . ' <' . get_option('admin_email') . '>'
            ];

            // Obtenemos la URL de descarga del plugin
            $mothership_settings = get_option('gkyc_mothership_settings');
            $plugin_download_url = $mothership_settings['plugin_download_url'] ?? '';

            // Reemplazamos los shortcodes
            $replacements = [
                '[nombre_cliente]' => $customer_name,
                '[API_Key]' => $new_mothership_api_key,
                '[nombre_partner]' => $partner_name,
                '[enlace_descarga_plugin]' => esc_url($plugin_download_url)
            ];

            $final_subject = str_replace(array_keys($replacements), array_values($replacements), $final_subject);
            $final_body = str_replace(array_keys($replacements), array_values($replacements), $final_body);

            // Enviamos el correo con el asunto y remitente correctos.
            wp_mail($customer_email, $final_subject, wpautop($final_body), $headers);

            // F. Marcar la solicitud como completada
            wp_update_post(['ID' => $request_id, 'post_status' => 'publish']);

            return new WP_REST_Response(['status' => 'success', 'message' => 'Licencia aprobada y creada exitosamente.'], 200);
        }

        return new WP_Error('invalid_action', 'Acción no válida.', ['status' => 400]);
    }

    public function handle_submit_recharge_request( WP_REST_Request $request ) {
        // Esta función ahora maneja un 'multipart/form-data' en lugar de JSON
        $params = $request->get_params();
        $files = $request->get_file_params();

        // 1. Recibir y validar datos del formulario
        $api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';
        $customer_email = isset($params['customer_email']) ? sanitize_email($params['customer_email']) : '';
        $amount = isset($params['monto']) ? (float)$params['monto'] : 0;

        if (empty($api_key) || !is_email($customer_email) || $amount <= 0) {
            return new WP_Error('bad_request', 'Faltan datos o el email es inválido.', ['status' => 400]);
        }

        // 2. Encontrar al socio por su API Key
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_api_key_mothership', 'value' => $api_key]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) {
            return new WP_Error('invalid_api_key', 'API Key de Socio inválida.', ['status' => 403]);
        }
        $partner_client_id = $partner_query->posts[0];

        // 3. Subir el archivo de comprobante (si existe)
        $comprobante_url = '';
        if (!empty($files['comprobante'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($files['comprobante'], $upload_overrides);
            if ($movefile && !isset($movefile['error'])) {
                $comprobante_url = $movefile['url'];
            }
        }

        // 4. Crear el post de "Solicitud de Recarga"
        $request_title = sprintf('Solicitud de Recarga de $%s para %s', number_format($amount, 2), $customer_email);
        $request_id = wp_insert_post([
            'post_title'    => $request_title,
            'post_status'   => 'pending',
            'post_type'     => 'gkyc_recharge_req',
        ]);

        if (is_wp_error($request_id)) {
            return new WP_Error('creation_failed', 'No se pudo crear la solicitud de recarga.', ['status' => 500]);
        }

        // 5. Guardar todos los metadatos en la solicitud
        update_post_meta($request_id, '_partner_owner_id', $partner_client_id);
        update_post_meta($request_id, '_customer_name', sanitize_text_field($params['customer_name']));
        update_post_meta($request_id, '_customer_email', $customer_email);
        update_post_meta($request_id, '_recharge_amount', $amount);
        update_post_meta($request_id, '_transaction_id', sanitize_text_field($params['transaction_id']));
        update_post_meta($request_id, '_usdt_hash', sanitize_text_field($params['usdt_hash']));
        if (!empty($comprobante_url)) {
            update_post_meta($request_id, '_comprobante_url', $comprobante_url);
        }
        
        // 6. Notificar al socio por correo
        // (Aquí iría la lógica de email para notificar al socio que tiene una nueva solicitud pendiente)

        return new WP_REST_Response(['status' => 'success', 'message' => 'Solicitud de recarga enviada correctamente.', 'request_id' => $request_id], 200);
    }

    // Pega esta nueva función completa en la clase Mothership_Api_Handler
    public function handle_process_recharge_request( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $request_id = isset($params['request_id']) ? absint($params['request_id']) : 0;
        $action = isset($params['action']) ? sanitize_key($params['action']) : '';

        if (empty($request_id) || !in_array($action, ['approve', 'reject'])) {
            return new WP_Error('bad_request', 'Faltan datos en la solicitud.', ['status' => 400]);
        }

        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_user_id', 'value' => $user_id]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) { return new WP_Error('not_a_partner', 'No eres un socio válido.', ['status' => 403]); }
        $partner_client_id = $partner_query->posts[0];

        if ((int)get_post_meta($request_id, '_partner_owner_id', true) !== $partner_client_id) {
            return new WP_Error('permission_denied', 'Esta solicitud de recarga no te pertenece.', ['status' => 403]);
        }

        $current_status = get_post_status($request_id);
        if ($current_status !== 'pending') {
            return new WP_REST_Response(['status' => 'success', 'message' => 'Esta solicitud ya ha sido procesada.'], 200);
        }

        if ($action === 'reject') {
            wp_update_post(['ID' => $request_id, 'post_status' => 'trash']);
            return new WP_REST_Response(['status' => 'success', 'message' => 'Solicitud de recarga rechazada.'], 200);
        }

        if ($action === 'approve') {
            // --- INICIO DE LA LÓGICA DE SEGURIDAD FINANCIERA ---
            $customer_email = get_post_meta($request_id, '_customer_email', true);
            $amount_paid_by_client = (float) get_post_meta($request_id, '_recharge_amount', true);
            
            // Buscamos al sub-cliente para saber su plan y calcular el costo
            $sub_client_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_email', 'value' => $customer_email], ['key' => '_reseller_owner_id', 'value' => $partner_client_id]], 'fields' => 'ids']);
            if (!$sub_client_query->have_posts()) {
                return new WP_Error('client_not_found', 'Error crítico: No se encontró el cliente asociado a esta recarga.', ['status' => 404]);
            }
            $sub_client_id = $sub_client_query->posts[0];

            // Reutilizamos la lógica de cálculo de costo que ya funciona
            $license_type = get_post_meta($sub_client_id, '_license_type', true);
            $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'title' => $license_type]);
            if (!$plan_query->have_posts()) { return new WP_Error('plan_not_found', 'No se encontró el plan del cliente para calcular el costo.', ['status' => 500]); }
            $plan_id = $plan_query->posts[0]->ID;
            $price_string = get_post_meta($plan_id, '_price', true);
            preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
            $cost_per_verification_for_partner = isset($matches[0]) ? (float)$matches[0] : 0;
            $partner_resale_price = (float) get_post_meta($partner_client_id, '_partner_verification_price_' . $plan_id, true);

            if ($partner_resale_price <= 0) { return new WP_Error('pricing_error', 'No has configurado un precio de reventa para este plan.', ['status' => 400]); }
            
            $verifications_bought = floor($amount_paid_by_client / $partner_resale_price);
            $cost_for_partner = $verifications_bought * $cost_per_verification_for_partner;

            // ¡La verificación clave!
            $partner_balance = (float) get_post_meta($partner_client_id, '_balance', true);
            if ($partner_balance < $cost_for_partner) {
                return new WP_Error('insufficient_funds', 'No puedes aprobar esta solicitud. Tu Saldo Maestro ('.number_format($partner_balance,2).') es menor que el costo de la operación ('.number_format($cost_for_partner,2).'). Por favor, recarga tu saldo.', ['status' => 402]);
            }
            // --- FIN DE LA LÓGICA DE SEGURIDAD FINANCIERA ---

            // Si pasa la verificación, procedemos a transferir y registrar
            wp_update_post(['ID' => $request_id, 'post_status' => 'private']); // Bloqueo anti-duplicados

            $new_partner_balance = $partner_balance - $cost_for_partner;
            update_post_meta($partner_client_id, '_balance', $new_partner_balance);

            $current_client_balance = (float) get_post_meta($sub_client_id, '_balance', true);
            update_post_meta($sub_client_id, '_balance', $current_client_balance + $amount_paid_by_client);

            // ... (Aquí iría la lógica para registrar las transacciones, la dejaremos para el pulido final) ...
            
            wp_update_post(['ID' => $request_id, 'post_status' => 'publish']);
            return new WP_REST_Response(['status' => 'success', 'message' => 'Recarga aprobada y saldo acreditado exitosamente.'], 200);
        }

        return new WP_Error('invalid_action', 'Acción no válida.', ['status' => 400]);
    }

    /**
     * Recibe el "ping" de un plugin cliente y actualiza su fecha de última actividad.
     */
    public function handle_heartbeat( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $api_key = isset($params['mothership_api_key']) ? sanitize_text_field($params['mothership_api_key']) : '';

        if (empty($api_key)) {
            return new WP_REST_Response(['status' => 'error', 'message' => 'API Key no proporcionada.'], 200);
        }

        // Buscamos al cliente por su API Key
        $client_query = new WP_Query([
            'post_type' => 'cliente_kyc',
            'posts_per_page' => 1,
            'meta_query' => [['key' => '_api_key_mothership', 'value' => $api_key]],
            'fields' => 'ids'
        ]);
        
        if ($client_query->have_posts()) {
            $client_id = $client_query->posts[0];
            // Si lo encontramos, actualizamos su "última vez visto" con la hora actual.
            update_post_meta($client_id, '_last_heartbeat_timestamp', time());
            return new WP_REST_Response(['status' => 'success'], 200);
        }

        return new WP_REST_Response(['status' => 'not_found'], 200);
    }

    /**
     * Procesa una venta de saldo automatizada desde el Kit de Reventa.
     * Valida al socio, calcula costos y ganancias, transfiere saldos y registra todo.
     * Incluye manejo inteligente para cuando el socio no tiene fondos suficientes.
     */
    public function handle_process_balance_sale( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        // 1. Recibir y validar datos de la venta
        $partner_api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';
        $sub_client_email = isset($params['sub_client_email']) ? sanitize_email($params['sub_client_email']) : '';
        $amount_paid_by_client = isset($params['amount_paid']) ? (float)$params['amount_paid'] : 0;

        if (empty($partner_api_key) || !is_email($sub_client_email) || $amount_paid_by_client <= 0) {
            return new WP_Error('bad_request', 'Faltan datos cruciales para procesar la venta de saldo.', ['status' => 400]);
        }

        // 2. Encontrar al socio y al sub-cliente
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_api_key_mothership', 'value' => $partner_api_key]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) {
            return new WP_Error('invalid_partner', 'API Key del socio inválida.', ['status' => 403]);
        }
        $partner_id = $partner_query->posts[0];

        $sub_client_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_email', 'value' => $sub_client_email], ['key' => '_reseller_owner_id', 'value' => $partner_id]], 'fields' => 'ids']);
        if (!$sub_client_query->have_posts()) {
            return new WP_Error('client_not_found', 'El cliente no pertenece a este socio.', ['status' => 404]);
        }
        $sub_client_id = $sub_client_query->posts[0];

        // 3. Calcular costo y ganancia (Lógica de Valor Facial)
        $license_type = get_post_meta($sub_client_id, '_license_type', true);
        $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'title' => $license_type]);
        if (!$plan_query->have_posts()) {
            return new WP_Error('plan_not_found', 'No se encontró el plan del cliente para calcular el costo.', ['status' => 500]);
        }
        $plan_id = $plan_query->posts[0]->ID;
        $price_string = get_post_meta($plan_id, '_price', true);
        preg_match('/[0-9]+\.?[0-9]*/', $price_string, $matches);
        $cost_per_verification_for_partner = isset($matches[0]) ? (float)$matches[0] : 0;
        
        $partner_resale_price = (float) get_post_meta($partner_id, '_partner_verification_price_' . $plan_id, true);

        if ($cost_per_verification_for_partner <= 0 || $partner_resale_price <= 0) {
            return new WP_Error('pricing_not_configured', 'El socio o el sistema no tienen configurado un precio de venta para este plan.', ['status' => 500]);
        }

        $verifications_bought = floor($amount_paid_by_client / $partner_resale_price);
        $cost_for_partner = $verifications_bought * $cost_per_verification_for_partner;
        $partner_profit = $amount_paid_by_client - $cost_for_partner;

        // 4. Verificar fondos del socio y actuar en consecuencia
        $partner_balance = (float) get_post_meta($partner_id, '_balance', true);
        
        // --- INICIO DE LA LÓGICA INTELIGENTE DE FONDOS ---
        if ($partner_balance < $cost_for_partner) {
            
            // Si el socio NO tiene fondos, creamos una solicitud pendiente
            $request_title = sprintf('Recarga PENDIENTE de $%s para %s', number_format($amount_paid_by_client, 2), $sub_client_email);
            $request_id = wp_insert_post([
                'post_title'    => $request_title,
                'post_status'   => 'pending',
                'post_type'     => 'gkyc_recharge_req',
            ]);
            update_post_meta($request_id, '_partner_owner_id', $partner_id);
            update_post_meta($request_id, '_customer_email', $sub_client_email);
            update_post_meta($request_id, '_recharge_amount', $amount_paid_by_client);
            update_post_meta($request_id, '_reason', 'Fondos insuficientes del socio.');
            // Guardamos el ID de la orden de WooCommerce si viene en el payload
            if (isset($params['order_id'])) {
                update_post_meta($request_id, '_woocommerce_order_id', absint($params['order_id']));
            }
            
            // --- Notificaciones por Correo para Fondos Insuficientes ---
            $partner_email = get_post_meta($partner_id, '_partner_support_email', true) ?: get_post_meta($partner_id, '_email', true);
            $sub_client_post = get_post( $sub_client_id ); // Necesitamos obtener el post del cliente
            $sub_client_name = strtok($sub_client_post->post_title, ' (');

            // Correo de ALERTA para el Socio
            if (is_email($partner_email)) {
                $partner_name = strtok(get_the_title($partner_id), ' (');
                $subject_partner = '¡Acción Requerida! Fondos Insuficientes para Venta de Saldo';
                $body_partner = "<html><body><h2>Alerta de Saldo Maestro Insuficiente</h2>";
                $body_partner .= "<p>Hola ".esc_html($partner_name).",</p>";
                $body_partner .= "<p>Tu cliente <strong>".esc_html($sub_client_name)."</strong> intentó comprar <strong>$".number_format($amount_paid_by_client, 2)."</strong> de saldo.</p>";
                $body_partner .= "<p>El costo de esta operación para ti es de <strong>$".number_format($cost_for_partner, 2)."</strong>, pero tu Saldo Maestro actual es de solo <strong>$".number_format($partner_balance, 2)."</strong>.</p>";
                $body_partner .= "<p><strong>La recarga para tu cliente está PENDIENTE.</strong> Por favor, <a href='URL_DE_RECARGA_SOCIO'>recarga tu Saldo Maestro</a> lo antes posible. Una vez que tengas fondos, ve a tu panel de socio, a la pestaña 'Solicitudes de Recarga', y aprueba la solicitud manualmente.</p>";
                $body_partner .= "</body></html>";
                wp_mail($partner_email, $subject_partner, $body_partner, ['Content-Type: text/html; charset=UTF-8']);
            }

            // Correo de AVISO para el Cliente Final (usando la función centralizada)
            $subject_client = 'Tu recarga de saldo está siendo procesada';
            $body_client = "<h2>Recarga en Proceso</h2><p>Hola ".esc_html($sub_client_name).",</p><p>Hemos recibido tu pago de <strong>$".number_format($amount_paid_by_client, 2)."</strong>. Tu saldo será acreditado en tu cuenta en breve, tan pronto como nuestro socio proveedor confirme la transacción.</p><p>Gracias por tu paciencia.</p>";
            gkyc_send_whitelabel_email($sub_client_email, $subject_client, $body_client, $sub_client_id);

            return new WP_REST_Response([
                'status' => 'success_pending_fulfillment', 
                'message' => 'Venta registrada, pero pendiente de fondos del socio. Se ha creado una solicitud.'
            ], 202); // 202: Aceptado para procesamiento futuro

        } else {
            // Si el socio SÍ tiene fondos, procesamos la venta normalmente
            $new_partner_balance = $partner_balance - $cost_for_partner;
            update_post_meta($partner_id, '_balance', $new_partner_balance);

            $sub_client_balance = (float) get_post_meta($sub_client_id, '_balance', true);
            $new_sub_client_balance = $sub_client_balance + $amount_paid_by_client;
            update_post_meta($sub_client_id, '_balance', $new_sub_client_balance);

            // Registrar en el libro contable
            $partner_post = get_post($partner_id);
            $sub_client_post = get_post($sub_client_id);

            $debit_title = sprintf('Costo por venta de saldo a %s', $sub_client_post->post_title);
            $debit_trans_id = wp_insert_post(['post_title' => $debit_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
            update_post_meta($debit_trans_id, '_client_id', $partner_id);
            update_post_meta($debit_trans_id, '_transaction_type', 'Costo Venta de Saldo');
            update_post_meta($debit_trans_id, '_transaction_amount', -$cost_for_partner);
            // ¡LA CORRECCIÓN CLAVE! Guardamos la ganancia neta en la transacción.
            update_post_meta($debit_trans_id, '_transaction_profit', $partner_profit);

            $credit_title = sprintf('Recarga de saldo comprada a %s', $partner_post->post_title);
            $credit_trans_id = wp_insert_post(['post_title' => $credit_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
            update_post_meta($credit_trans_id, '_client_id', $sub_client_id);
            update_post_meta($credit_trans_id, '_transaction_type', 'Recarga de Saldo');
            update_post_meta($credit_trans_id, '_transaction_amount', $amount_paid_by_client);
            update_post_meta($credit_trans_id, '_partner_owner_id', $partner_id);
            
            // --- Notificaciones por Correo para Venta de Saldo Exitosa ---
            $partner_email = get_post_meta($partner_id, '_partner_support_email', true) ?: get_post_meta($partner_id, '_email', true);
            $sub_client_name = strtok($sub_client_post->post_title, ' (');

            // Correo para el Socio con desglose de ganancia
            if (is_email($partner_email)) {
                $subject_partner = sprintf('¡Venta de Saldo Exitosa! $%s para %s', number_format($amount_paid_by_client, 2), $sub_client_name);
                $body_partner = "<html><body><h2>Resumen de Venta de Saldo</h2>";
                $body_partner .= "<p>Tu cliente <strong>".esc_html($sub_client_name)."</strong> ha comprado <strong>$".number_format($amount_paid_by_client, 2)."</strong> de saldo.</p>";
                $body_partner .= "<hr>";
                $body_partner .= "<p>Costo para ti: $" . number_format($cost_for_partner, 2) . "</p>";
                $body_partner .= "<p><strong>Tu Ganancia Neta: $" . number_format($partner_profit, 2) . "</strong></p>";
                $body_partner .= "<hr>";
                $body_partner .= "<p>Tu nuevo Saldo Maestro es: <strong>$" . number_format($new_partner_balance, 2) . "</strong></p>";
                $body_partner .= "</body></html>";
                wp_mail($partner_email, $subject_partner, $body_partner, ['Content-Type: text/html; charset=UTF-8']);
            }

            // Correo para el Cliente Final con marca blanca
            $subject_client = 'Tu recarga de saldo ha sido completada';
            $body_client = "<h2>¡Recarga Exitosa!</h2><p>Hola ".esc_html($sub_client_name).",</p><p>Hemos añadido <strong>$".number_format($amount_paid_by_client, 2)."</strong> a tu saldo.</p><p>Tu nuevo saldo total es: <strong>$".number_format($new_sub_client_balance, 2)."</strong></p>";
            gkyc_send_whitelabel_email($sub_client_email, $subject_client, $body_client, $sub_client_id);

            // --- INICIO: CÓDIGO DE SINCRONIZACIÓN AUTOMÁTICA PARA SUB-CLIENTE ---
            $client_site_url = get_post_meta($sub_client_id, '_activated_domain', true);
            $mothership_api_key = get_post_meta($sub_client_id, '_api_key_mothership', true);

            if (!empty($client_site_url) && !empty($mothership_api_key)) {
                $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
                wp_remote_post($sync_url, [
                    'method'    => 'POST',
                    'timeout'   => 15,
                    'blocking'  => false, // No esperamos respuesta para no ralentizar.
                    'headers'   => ['Content-Type' => 'application/json'],
                    'body'      => json_encode(['mothership_api_key' => $mothership_api_key])
                ]);
            }
            // --- FIN: CÓDIGO DE SINCRONIZACIÓN AUTOMÁTICA ---

            return new WP_REST_Response([
                'status' => 'success', 
                'message' => 'Venta de saldo procesada correctamente.'
            ], 200);
        }
        // --- FIN DE LA LÓGICA INTELIGENTE DE FONDOS ---
    }

    /**
    * Procesa una aprobación automática de una solicitud de recarga.
    * Reutiliza la lógica de la aprobación manual para máxima fiabilidad.
    */
    public function handle_auto_approve_request( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $partner_api_key = isset($params['partner_api_key']) ? sanitize_text_field($params['partner_api_key']) : '';
        $request_id = isset($params['request_id']) ? absint($params['request_id']) : 0;

        if (empty($partner_api_key) || empty($request_id)) {
            return new WP_Error('bad_request', 'Faltan datos para la auto-aprobación.', ['status' => 400]);
        }

        // Buscamos al socio para validar la clave
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_api_key_mothership', 'value' => $partner_api_key]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) {
            return new WP_Error('invalid_partner', 'API Key del socio inválida.', ['status' => 403]);
        }
        $partner_id = $partner_query->posts[0];

        // Verificamos que la solicitud pertenece al socio
        if ((int)get_post_meta($request_id, '_partner_owner_id', true) !== $partner_id) {
            return new WP_Error('permission_denied', 'Esta solicitud no pertenece al socio.', ['status' => 403]);
        }

        // --- LÓGICA DE APROBACIÓN (reutilizada del sistema manual) ---
        $customer_email = get_post_meta($request_id, '_customer_email', true);
        $amount_to_credit = (float) get_post_meta($request_id, '_recharge_amount', true);

        $client_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_email', 'value' => $customer_email], ['key' => '_reseller_owner_id', 'value' => $partner_id]], 'fields' => 'ids']);

        if (!$client_query->have_posts()) {
            wp_update_post(['ID' => $request_id, 'post_status' => 'trash']); // La solicitud es inválida, se rechaza
            return new WP_Error('client_not_found', 'Cliente no encontrado para la recarga.', ['status' => 404]);
        }
        $sub_client_id = $client_query->posts[0];

        // Acreditamos el saldo
        $current_balance = (float) get_post_meta($sub_client_id, '_balance', true);
        update_post_meta($sub_client_id, '_balance', $current_balance + $amount_to_credit);

        // Creamos la transacción
        $transaction_title = sprintf('Recarga Automática (WooCommerce) - $%s', number_format($amount_to_credit, 2));
        $transaction_id = wp_insert_post(['post_title' => $transaction_title, 'post_status' => 'publish', 'post_type' => 'gkyc_transaction']);
        update_post_meta($transaction_id, '_client_id', $sub_client_id);
        update_post_meta($transaction_id, '_partner_owner_id', $partner_id);
        update_post_meta($transaction_id, '_transaction_type', 'Recarga de Saldo');
        update_post_meta($transaction_id, '_transaction_amount', $amount_to_credit);

        // Marcamos la solicitud como completada
        wp_update_post(['ID' => $request_id, 'post_status' => 'publish']);

        return new WP_REST_Response(['status' => 'success', 'message' => 'Solicitud auto-aprobada.'], 200);
    }

    /**
    * Calcula el costo y la ganancia para una transferencia de saldo en tiempo real.
    * Reutiliza la lógica de "Valor Facial".
    */
    public function handle_calculate_transfer_profit( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $params = $request->get_json_params();
        $sub_client_id = isset($params['client_id']) ? absint($params['client_id']) : 0;
        $amount_to_transfer = isset($params['amount']) ? (float)$params['amount'] : 0;

        if (empty($sub_client_id) || $amount_to_transfer <= 0) {
            return new WP_Error('bad_request', 'Datos inválidos.', ['status' => 400]);
        }

        // 1. Validar que el usuario es un socio y que el cliente le pertenece
        $partner_query = new WP_Query(['post_type' => 'cliente_kyc', 'posts_per_page' => 1, 'meta_query' => [['key' => '_user_id', 'value' => $user_id]], 'fields' => 'ids']);
        if (!$partner_query->have_posts()) { return new WP_Error('not_a_partner', 'No eres un socio válido.', ['status' => 403]); }
        $partner_id = $partner_query->posts[0];

        if ((int)get_post_meta($sub_client_id, '_reseller_owner_id', true) !== $partner_id) {
            return new WP_Error('permission_denied', 'Este cliente no te pertenece.', ['status' => 403]);
        }

        // 2. Reutilizar la lógica de cálculo de costo y ganancia
        $license_type = get_post_meta($sub_client_id, '_license_type', true);
        $plan_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => 1, 'title' => $license_type]);
        if (!$plan_query->have_posts()) { return new WP_Error('plan_not_found', 'No se encontró el plan del cliente.', ['status' => 404]); }
        $plan_post = $plan_query->posts[0];
        
        $cost_per_verification_for_partner = (float) get_post_meta($plan_post->ID, '_admin_didit_cost', true);
        $partner_resale_price = (float) get_post_meta($partner_id, '_partner_verification_price_' . $plan_post->ID, true);

        if ($partner_resale_price <= 0) {
            return new WP_Error('pricing_not_set', 'No has definido un precio de reventa para este plan.', ['status' => 400]);
        }

        $verifications = floor($amount_to_transfer / $partner_resale_price);
        $cost_for_partner = $verifications * $cost_per_verification_for_partner;
        $partner_profit = $amount_to_transfer - $cost_for_partner;

        // 3. Devolver los resultados
        return new WP_REST_Response([
            'status'        => 'success',
            'verifications' => $verifications,
            'cost'          => round($cost_for_partner, 2),
            'profit'        => round($partner_profit, 2)
        ], 200);
    }
}
