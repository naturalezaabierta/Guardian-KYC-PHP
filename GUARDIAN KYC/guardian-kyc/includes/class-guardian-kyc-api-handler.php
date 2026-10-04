<?php
/**
 * Maneja la lógica de llamadas a la API de Didit para iniciar la verificación.
 * v4.0 - VERSIÓN FINAL Y FUNCIONAL
 */
class Guardian_KYC_Api_Handler {
    
    // ===== LA CORRECCIÓN MÁGICA ESTÁ AQUÍ =====
    // Usamos el endpoint en SINGULAR con la barra al final, que sabemos que funciona.
    private $api_url = 'https://verification.didit.me/v2/session/'; 
    private $api_key;
    private $workflow_id;

    public function __construct() {
        $options = get_option('guardian_kyc_options');
        
        // --- INICIO DE LA LÓGICA DE CNAME DINÁMICO ---

        // Por defecto, usamos la URL de Didit
        $this->api_url = 'https://verification.didit.me/v2/session/';

        // Obtenemos la URL de recarga que nos envió el Mothership durante la sincronización
        $recharge_url = isset($options['recharge_url']) ? $options['recharge_url'] : '';

        // Si la URL de recarga NO es la de guardiankyc.com, significa que es un sub-cliente
        // y la URL pertenece al sitio del socio.
        if (!empty($recharge_url) && strpos($recharge_url, 'guardiankyc.com') === false) {
            
            // Extraemos el dominio del socio desde su URL de recarga (ej: saca "revendedor-a.com" de "https://revendedor-a.com/pagos")
            $partner_domain = parse_url($recharge_url, PHP_URL_HOST);

            if ($partner_domain) {
                // Construimos la URL de verificación personalizada para ese socio
                $this->api_url = 'https://verificacion.' . $partner_domain . '/v2/session/';
            }
        }

        // --- FIN DE LA LÓGICA DE CNAME DINÁMICO ---
        
        $this->api_key = isset($options['api_key_didit']) ? $options['api_key_didit'] : '';
        $active_plan_slug = isset($options['active_plan']) ? $options['active_plan'] : null;
        $synced_plans = isset($options['synced_plans']) ? $options['synced_plans'] : [];

        if ($active_plan_slug && !empty($synced_plans) && isset($synced_plans[$active_plan_slug])) {
            $this->workflow_id = $synced_plans[$active_plan_slug]['didit_workflow_id'];
        } else {
            $this->workflow_id = null;
        }
    }

    public function start_verification_process() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'La API Key de Didit no está configurada.');
        }
        if (empty($this->workflow_id)) {
            return new WP_Error('no_plan_selected', 'No hay ningún plan de verificación válido seleccionado.');
        }
        $current_user = wp_get_current_user();
        if (!$current_user->ID) {
            return new WP_Error('not_logged_in', 'El usuario no ha iniciado sesión.');
        }
        
        $headers = [
            'accept'       => 'application/json',
            'content-type' => 'application/json',
            'x-api-key'    => $this->api_key,
        ];
        
        $options = get_option('guardian_kyc_options');
        $active_plan_slug = isset($options['active_plan']) ? $options['active_plan'] : '';
        
        // El vendor_data necesita la API Key de Didit (para el Mothership) y el user_id
        $vendor_data_string = 'didit_key=' . $this->api_key . '&plan_slug=' . $active_plan_slug . '&user_id=' . $current_user->ID;

        // ===== LA LÓGICA DE REDIRECCIÓN CORRECTA =====
        $body = [
            'workflow_id'  => $this->workflow_id,
            'vendor_data'  => $vendor_data_string,
            'callback'     => home_url('/verificacion-completa/'), // El usuario vuelve al sitio cliente.
        ];
        
        $response = wp_remote_post($this->api_url, [
            'method'  => 'POST',
            'headers' => $headers,
            'body'    => json_encode($body),
            'timeout' => 20,
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_connection_error', $response->get_error_message());
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        $response_code = wp_remote_retrieve_response_code($response);

        if ($response_code >= 300) {
            $error_message = isset($response_body['detail']) ? $response_body['detail'] : 'Respuesta de error de la API de Didit.';
            return new WP_Error('api_http_error', 'Error ' . $response_code . ': ' . $error_message);
        }
        
        if (!isset($response_body['url']) || !isset($response_body['session_id'])) {
            return new WP_Error('api_response_error', 'La API de Didit no devolvió una URL o un session_id.');
        }
        
        update_user_meta( $current_user->ID, 'guardian_kyc_pending_session_id', $response_body['session_id'] );
        
        $response_body['redirect_url'] = $response_body['url'];
        return $response_body;
    }
}