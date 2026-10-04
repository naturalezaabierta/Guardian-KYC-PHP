<?php
class GKYC_Reseller_Settings_Page {

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        // NUEVO: Hook para manejar la acción del botón
        add_action('admin_init', array($this, 'handle_clear_cache'));
    }

    // NUEVA FUNCIÓN: Borra el transient del plugin
    public function handle_clear_cache() {
        if (isset($_GET['page']) && $_GET['page'] === 'gkyc_reseller_settings' && isset($_GET['action']) && $_GET['action'] === 'clear_cache') {
            if (isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'gkyc_clear_reseller_cache')) {
                $options = get_option('gkyc_reseller_options');
                $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
                if ($api_key) {
                    $cache_key = 'gkyc_reseller_data_' . md5($api_key);
                    delete_transient($cache_key);
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success is-dismissible"><p>La caché del Kit de Reventa ha sido limpiada. Los datos se actualizarán en la próxima visita a la página de planes.</p></div>';
                    });
                }
            }
        }
    }

    public function add_admin_menu() {
        add_menu_page('Kit de Reventa KYC', 'Kit de Reventa', 'manage_options', 'gkyc_reseller_settings', array($this, 'create_admin_page'), 'dashicons-store', 80);
    }

    public function create_admin_page() {
    // Obtenemos los datos del socio para mostrarlos
    $partner_data = $this->fetch_partner_data();
    ?>
    <div class="wrap">
        <h1>Ajustes del Kit de Reventa KYC</h1>
        <p>Conecta tu sitio con tu cuenta de socio de Guardián KYC para empezar a vender.</p>

        <div id="gkyc-partner-status" style="margin-top: 20px; margin-bottom: 30px;">
            <h2>Estado de la Cuenta</h2>
            <?php if (is_wp_error($partner_data)): ?>
                <div class="notice notice-error inline">
                    <p>No se pudo cargar el estado de la cuenta: <?php echo esc_html($partner_data->get_error_message()); ?></p>
                </div>
            <?php else: ?>
                <div style="display: flex; gap: 20px; background: #fff; padding: 20px; border-radius: 4px; border: 1px solid #c3c4c7;">
                    <div class="gkyc-stat-item" style="text-align: center; flex-grow: 1;">
                        <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;">Licencias Disponibles</h3>
                        <p style="margin: 0; font-size: 2.5em; font-weight: 600; color: #1d2327;"><?php echo esc_html($partner_data['licenses_remaining']); ?></p>
                    </div>
                    <div class="gkyc-stat-item" style="text-align: center; flex-grow: 1;">
                        <h3 style="margin: 0 0 5px 0; font-size: 14px; color: #50575e;">Saldo Maestro</h3>
                        <p style="margin: 0; font-size: 2.5em; font-weight: 600; color: #007cba;">$<?php echo esc_html(number_format($partner_data['master_balance'], 2)); ?></p>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <?php
                        $api_key = get_option('gkyc_reseller_options')['mothership_api_key'] ?? '';
                        $recharge_url = add_query_arg(['apikey' => $api_key], 'https://guardiankyc.com/pagos/');
                        $buy_licenses_url = 'https://guardiankyc.com/planes-de-socios/';
                    ?>
                    <a href="<?php echo esc_url($recharge_url); ?>" target="_blank" class="button button-primary">Recargar Saldo Maestro</a>
                    <a href="<?php echo esc_url($buy_licenses_url); ?>" target="_blank" class="button button-secondary">Comprar Paquetes de Licencias</a>
                </div>
            <?php endif; ?>
        </div>

        <form method="post" action="options.php">
            <?php
            settings_fields('gkyc_reseller_settings_group');
            do_settings_sections('gkyc_reseller_settings_page');
            submit_button('Guardar Cambios');
            ?>
        </form>

        <div id="gkyc-reseller-tools" style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
            <h2>Herramientas</h2>
            <p>Si has hecho cambios en tu perfil de socio (como comprar más licencias) y no se reflejan aquí, usa este botón para forzar una actualización.</p>
            <?php
            $clear_cache_url = wp_nonce_url(admin_url('admin.php?page=gkyc_reseller_settings&action=clear_cache'), 'gkyc_clear_reseller_cache');
            ?>
            <a href="<?php echo esc_url($clear_cache_url); ?>" class="button button-secondary">Limpiar Caché y Refrescar Datos</a>
        </div>
    </div>
    <?php
}

    public function settings_init() {
        register_setting('gkyc_reseller_settings_group', 'gkyc_reseller_options', array($this, 'sanitize_options'));
        add_settings_section('gkyc_reseller_connection_section', 'Datos de Conexión', null, 'gkyc_reseller_settings_page');
        add_settings_field('mothership_api_key', 'API Key de Socio (Mothership)', array($this, 'api_key_field_render'), 'gkyc_reseller_settings_page', 'gkyc_reseller_connection_section');
    }

    public function api_key_field_render() {
        $options = get_option('gkyc_reseller_options');
        $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
        ?>
        <input type="text" name="gkyc_reseller_options[mothership_api_key]" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
        <p class="description">Pega aquí la API Key que te llegó en tu correo de bienvenida al Panel de Socio de Guardián KYC.</p>
        <?php
    }

    public function sanitize_options($input) {
        $new_input = [];
        if (isset($input['mothership_api_key'])) {
            $new_input['mothership_api_key'] = sanitize_text_field($input['mothership_api_key']);
        }

        // Limpiamos la caché automáticamente cada vez que se guarda una opción
        if(isset($new_input['mothership_api_key'])) {
            $cache_key = 'gkyc_reseller_data_' . md5($new_input['mothership_api_key']);
            delete_transient($cache_key);
        }
        
        return $new_input;
    }

    /**
    * Obtiene los datos del socio (planes, etc.) desde el Mothership y los guarda en caché.
    */
    private function fetch_partner_data() {
        $options = get_option('gkyc_reseller_options');
        $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
        if (empty($api_key)) { 
            return new WP_Error('no_api_key', 'API Key no configurada.');
        }

        $cache_key = 'gkyc_reseller_data_' . md5($api_key);
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) {
            return $cached_data;
        }

        $response = wp_remote_post('https://guardiankyc.com/wp-json/guardian-kyc/v1/reseller/get-plans', [
            'method' => 'POST', 'timeout' => 15, 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode(['api_key' => $api_key])
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('connection_error', 'Error de conexión con el servidor.');
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($response_code !== 200 || empty($data) || !isset($data['status']) || $data['status'] !== 'success') {
            $error_message = isset($data['message']) ? $data['message'] : 'Respuesta inválida del servidor.';
            return new WP_Error('api_error', $error_message);
        }

        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

}