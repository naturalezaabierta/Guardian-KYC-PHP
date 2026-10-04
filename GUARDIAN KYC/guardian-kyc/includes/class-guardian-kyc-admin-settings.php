<?php
/**
 * Maneja el menú de administración y los ajustes del plugin.
 * v2.2 - Versión final y completa sin duplicados.
 */
class Guardian_KYC_Admin_Settings {

    const MOTHERSHIP_API_URL = 'https://guardiankyc.com/wp-json/guardian-kyc/v1/sync';

    public function add_admin_menu() {
        add_menu_page('Guardián KYC', 'Guardián KYC', 'manage_options', 'guardian_kyc', array($this, 'create_admin_page'), 'dashicons-shield-alt', 80);
        add_submenu_page('guardian_kyc', 'Estadísticas', 'Estadísticas', 'manage_options', 'guardian_kyc_stats', array($this, 'create_stats_page'));
    }

    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1>Ajustes de Guardián KYC</h1>
            <p class="description" style="font-size: 16px; margin-top: -0.5em; margin-bottom: 2em;">
                ¡Bienvenido! Desde aquí puedes gestionar la conexión con tu cuenta, ver tu saldo y configurar los planes de verificación.
            </p>

            <?php
            $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'conexion';
            ?>

            <h2 class="nav-tab-wrapper">
                <a href="?page=guardian_kyc&tab=conexion" class="nav-tab <?php echo $active_tab == 'conexion' ? 'nav-tab-active' : ''; ?>">Conexión y Cuenta</a>
                <a href="?page=guardian_kyc&tab=configuracion" class="nav-tab <?php echo $active_tab == 'configuracion' ? 'nav-tab-active' : ''; ?>">Configuración</a>
                <a href="?page=guardian_kyc&tab=personalizacion" class="nav-tab <?php echo $active_tab == 'personalizacion' ? 'nav-tab-active' : ''; ?>">Personalización</a>
            </h2>

            <form method="post" action="options.php">
                <?php
                settings_fields('guardian_kyc_settings');
                if ($active_tab == 'conexion') { do_settings_sections('guardian_kyc_conexion'); }
                elseif ($active_tab == 'configuracion') { do_settings_sections('guardian_kyc_configuracion'); }
                elseif ($active_tab == 'personalizacion') { do_settings_sections('guardian_kyc_personalizacion'); }
                submit_button('Guardar y Sincronizar');
                ?>
            </form>
        </div>
        <?php
    }
    
    public function settings_init() {
        register_setting('guardian_kyc_settings', 'guardian_kyc_options', array($this, 'sanitize_and_sync_options'));

        // Pestaña 1: Conexión y Cuenta
        add_settings_section('gkyc_connection_section', 'Ajustes de Conexión', null, 'guardian_kyc_conexion');
        add_settings_field('api_key_mothership', 'API Key de Mothership', array($this, 'api_key_field_render'), 'guardian_kyc_conexion', 'gkyc_connection_section');
        
        $options = get_option('guardian_kyc_options');
        if (!empty($options['api_key_mothership'])) {
            add_settings_section('gkyc_billing_section', 'Tu Cuenta', null, 'guardian_kyc_conexion');
            add_settings_field('account_balance', 'Saldo Actual', array($this, 'balance_field_render'), 'guardian_kyc_conexion', 'gkyc_billing_section');
            add_settings_field('recharge_balance', 'Recargar Saldo', array($this, 'recharge_field_render'), 'guardian_kyc_conexion', 'gkyc_billing_section');
        }

        // Pestaña 2: Configuración
        add_settings_section('gkyc_gatekeeper_section', 'Modo de Seguridad', null, 'guardian_kyc_configuracion');
        add_settings_field('gatekeeper_mode', 'Modo de Operación', array($this, 'gatekeeper_mode_field_render'), 'guardian_kyc_configuracion', 'gkyc_gatekeeper_section');
        
        if (!empty($options['api_key_mothership'])) {
            add_settings_section('gkyc_plan_section', 'Configuración de Verificación', null, 'guardian_kyc_configuracion');
            add_settings_field('active_plan', 'Seleccionar Plan Activo', array($this, 'plans_field_render'), 'guardian_kyc_configuracion', 'gkyc_plan_section');
        }

        // Pestaña 3: Personalización
        add_settings_section('gkyc_display_section', 'Enlaces de Consentimiento', null, 'guardian_kyc_personalizacion');
        add_settings_field('terms_url', 'URL de Términos y Condiciones', array($this, 'terms_url_field_render'), 'guardian_kyc_personalizacion', 'gkyc_display_section');
        add_settings_field('privacy_url', 'URL de Políticas de Privacidad', array($this, 'privacy_url_field_render'), 'guardian_kyc_personalizacion', 'gkyc_display_section');
    }

    public function sanitize_and_sync_options($input) {
        $current_options = get_option('guardian_kyc_options');
        $new_input = $current_options;
        if (isset($input['api_key_mothership'])) { $new_input['api_key_mothership'] = sanitize_text_field($input['api_key_mothership']); }
        if (isset($input['gatekeeper_mode'])) { $new_input['gatekeeper_mode'] = sanitize_text_field($input['gatekeeper_mode']); }
        if (isset($input['active_plan'])) { $new_input['active_plan'] = sanitize_text_field($input['active_plan']); }
        if (isset($input['terms_url'])) { $new_input['terms_url'] = esc_url_raw($input['terms_url']); }
        if (isset($input['privacy_url'])) { $new_input['privacy_url'] = esc_url_raw($input['privacy_url']); }

        if (!empty($new_input['api_key_mothership'])) {
            $response = wp_remote_post(self::MOTHERSHIP_API_URL, [
                'method' => 'POST', 'timeout' => 20,
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'body' => json_encode(['api_key' => $new_input['api_key_mothership'], 'site_url' => home_url()]),
            ]);
            if (is_wp_error($response)) {
                add_settings_error('guardian_kyc', 'api_connection_error', 'Error de conexión: ' . $response->get_error_message(), 'error');
                return $new_input;
            }
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $data = json_decode($response_body, true);
            if ($response_code === 200 && $data) {
                add_settings_error('guardian_kyc', 'sync_success', '¡Sincronización exitosa! Tus planes y saldo han sido actualizados.', 'success');
                $new_input['api_key_didit'] = isset($data['api_key_didit']) ? sanitize_text_field($data['api_key_didit']) : '';
                $new_input['balance'] = isset($data['balance']) ? $data['balance'] : '0.00';
                $new_input['usage_today'] = isset($data['usage_today']) ? $data['usage_today'] : 0;
                $new_input['synced_plans'] = isset($data['plans']) ? $data['plans'] : [];
                $new_input['partner_logo_url'] = isset($data['partner_logo_url']) ? esc_url_raw($data['partner_logo_url']) : '';
            } else {
                $error_message = isset($data['message']) ? $data['message'] : 'Respuesta inesperada del servidor.';
                add_settings_error('guardian_kyc', 'api_error', 'Error de sincronización: ' . $error_message, 'error');
            }
        }
        return $new_input;
    }
    
    public function api_key_field_render() {
        $options = get_option('guardian_kyc_options');
        $api_key = isset($options['api_key_mothership']) ? esc_attr($options['api_key_mothership']) : '';
        echo '<input type="text" name="guardian_kyc_options[api_key_mothership]" value="' . $api_key . '" class="regular-text" placeholder="Pega aquí la API Key de tu cuenta">';
        if (empty($api_key)) {
            echo '<p class="description">La API Key que encuentras en tu panel de guardiankyc.com.</p>';
        }
    }

    public function gatekeeper_mode_field_render() {
        $options = get_option('guardian_kyc_options');
        $mode = isset($options['gatekeeper_mode']) ? $options['gatekeeper_mode'] : 'strict';
        ?>
        <fieldset>
            <p><label><input type="radio" name="guardian_kyc_options[gatekeeper_mode]" value="strict" <?php checked($mode, 'strict'); ?>> <strong>Máxima Seguridad (Estricto)</strong></label><br>
            <span class="description" style="margin-left: 20px;">Redirige forzosamente a los usuarios no verificados a la página de verificación.</span></p>
            <p><label><input type="radio" name="guardian_kyc_options[gatekeeper_mode]" value="flexible" <?php checked($mode, 'flexible'); ?>> <strong>Verificación Manual (Flexible)</strong></label><br>
            <span class="description" style="margin-left: 20px;">Permite navegar. Usa el shortcode <code>[guardian_kyc_verificacion]</code> en las páginas que quieras proteger.</span></p>
        </fieldset>
        <?php
    }

    public function balance_field_render() {
        $options = get_option('guardian_kyc_options');
        $balance = isset($options['balance']) ? $options['balance'] : 'N/A';
        
        // Mostramos el saldo inicial que se carga con la página.
        echo '<div id="gkyc-balance-container" class="guardian-kyc-balance-display">';
        echo '$' . esc_html(is_numeric($balance) ? number_format((float)$balance, 2) : $balance) . ' USD';
        echo '</div>';
        
        // ===== INICIO DEL SCRIPT DE ACTUALIZACIÓN EN TIEMPO REAL =====
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Función para formatear el número como moneda
                function formatCurrency(number) {
                    // Aseguramos que el número tenga 2 decimales y formato de miles
                    return '$' + parseFloat(number).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' USD';
                }

                // Función que pregunta por el nuevo saldo
                function checkBalance() {
                    $.ajax({
                        url: ajaxurl, // URL estándar de AJAX en WordPress
                        type: 'POST',
                        data: {
                            action: 'gkyc_get_current_balance', // La acción que creamos en PHP
                        },
                        success: function(response) {
                            if (response.success) {
                                var newBalance = response.data.balance;
                                var container = $('#gkyc-balance-container');
                                var currentBalanceText = container.text().replace(/[^0-9.]/g, '');
                                
                                // Solo actualizamos la pantalla si el saldo es realmente diferente
                                if (parseFloat(currentBalanceText) !== newBalance) {
                                    // Pequeña animación para que el cambio sea notorio
                                    container.css('transition', 'transform 0.2s ease-in-out').css('transform', 'scale(1.1)');
                                    container.text(formatCurrency(newBalance));
                                    setTimeout(function() { container.css('transform', 'scale(1)'); }, 200);
                                }
                            }
                        }
                    });
                }

                // Ejecutamos la función cada 5 segundos.
                setInterval(checkBalance, 5000); 
            });
        </script>
        <?php
        // ===== FIN DEL SCRIPT DE ACTUALIZACIÓN EN TIEMPO REAL =====
    }

    public function usage_today_field_render() {
        $options = get_option('guardian_kyc_options');
        $usage = isset($options['usage_today']) ? $options['usage_today'] : 'N/A';
        echo '<p><strong>' . esc_html($usage) . '</strong></p>';
    }

    public function recharge_field_render() {
        $options = get_option('guardian_kyc_options');
        $api_key = isset($options['api_key_mothership']) ? $options['api_key_mothership'] : '';
        $base_recharge_url = isset($options['recharge_url']) && !empty($options['recharge_url']) 
                           ? $options['recharge_url'] 
                           : '#';

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        // --- LA LÍNEA CLAVE ---
        // Definimos la dirección a la que queremos que el usuario regrese.
        $return_url = admin_url('admin.php?page=guardian_kyc');

        // Añadimos TODOS los parámetros a la URL final, incluyendo el return_url.
        $final_recharge_url = add_query_arg(
            [
                'apikey'         => $api_key,
                'return_url'     => $return_url // <-- AÑADIMOS LA DIRECCIÓN DE RETORNO
            ],
            $base_recharge_url
        );

        echo '<a href="' . esc_url($final_recharge_url) . '" target="_blank" class="button button-primary">Recargar Saldo Ahora</a>';
    }

    public function plans_field_render() {
        $options = get_option('guardian_kyc_options');
        $synced_plans = isset($options['synced_plans']) ? $options['synced_plans'] : [];
        $active_plan = isset($options['active_plan']) ? $options['active_plan'] : '';
        if (empty($synced_plans)) {
            echo '<p>Aún no se han sincronizado planes. Por favor, asegúrate de que tu API Key sea correcta y haz clic en "Guardar y Sincronizar".</p>';
        } else {
            echo '<fieldset>';
            foreach ($synced_plans as $slug => $plan) {
                $name = isset($plan['name']) ? $plan['name'] : 'Plan sin nombre';
                $price = isset($plan['price']) ? $plan['price'] : '';
                $description = isset($plan['description']) ? $plan['description'] : '';
                echo '<p><label><input type="radio" name="guardian_kyc_options[active_plan]" value="'.esc_attr($slug).'" '.checked($active_plan, $slug, false).'>';
                echo '<strong>' . esc_html($name) . '</strong> (' . esc_html($price) . ')';
                echo '</label><br><span class="description" style="margin-left: 20px;">' . esc_html($description) . '</span></p>';
            }
            echo '</fieldset>';
        }
    }

    public function terms_url_field_render() {
        $options = get_option('guardian_kyc_options');
        $terms_url = isset($options['terms_url']) ? esc_attr($options['terms_url']) : '';
        ?>
        <input type="url" name="guardian_kyc_options[terms_url]" value="<?php echo $terms_url; ?>" class="regular-text" placeholder="https://tu-sitio.com/terminos">
        <?php
    }

    public function privacy_url_field_render() {
        $options = get_option('guardian_kyc_options');
        $privacy_url = isset($options['privacy_url']) ? esc_attr($options['privacy_url']) : '';
        ?>
        <input type="url" name="guardian_kyc_options[privacy_url]" value="<?php echo $privacy_url; ?>" class="regular-text" placeholder="https://tu-sitio.com/privacidad">
        <?php
    }
    
    public function create_stats_page() {
        $stats = $this->get_verification_stats();
        ?>
        <div class="wrap gkyc-stats-wrap">
            <h1>Estadísticas de Verificación</h1>
            <p class="description" style="font-size: 16px; margin-top: -0.5em; margin-bottom: 2em;">
                Aquí puedes ver un resumen de la actividad de verificación de tus usuarios y el consumo de saldo.
            </p>
            <div id="gkyc-stats-dashboard">
                <div class="gkyc-kpi-cards">
                    <div class="gkyc-card"><h3 class="gkyc-card-title">Verificaciones Hoy</h3><p class="gkyc-card-value"><?php echo esc_html($stats['today']); ?></p></div>
                    <div class="gkyc-card"><h3 class="gkyc-card-title">Últimos 7 Días</h3><p class="gkyc-card-value"><?php echo esc_html($stats['week']); ?></p></div>
                    <div class="gkyc-card"><h3 class="gkyc-card-title">Mes Actual</h3><p class="gkyc-card-value"><?php echo esc_html($stats['month']); ?></p></div>
                    <div class="gkyc-card consumed"><h3 class="gkyc-card-title">Saldo Consumido (Mes Actual)</h3><p class="gkyc-card-value">$<?php echo esc_html(number_format((float)$stats['consumed_balance_month'], 2)); ?></p></div>
                    <div class="gkyc-card approved"><h3 class="gkyc-card-title">Total Aprobadas</h3><p class="gkyc-card-value"><?php echo esc_html($stats['total_approved']); ?></p></div>
                </div>
                <div class="gkyc-charts-container">
                    <div class="gkyc-chart-card"><h3>Actividad (Últimos 30 días)</h3><canvas id="gkyc-daily-chart"></canvas></div>
                    <div class="gkyc-chart-card status-chart"><h3>Desglose por Estado</h3><canvas id="gkyc-status-chart"></canvas></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Obtiene las estadísticas de verificación.
     * v2.3 - Corregida la lógica del bucle para leer correctamente el ID de usuario.
     */
    private function get_verification_stats() {
        $stats = [
            'today' => 0,
            'week' => 0,
            'month' => 0,
            'total_approved' => 0,
            'consumed_balance_month' => 0,
        ];

        $now = current_time('mysql');
        $current_month_start = date('Y-m-01 00:00:00', strtotime($now));

        // Obtener el precio por verificación del plan activo
        $options = get_option('guardian_kyc_options');
        $active_plan_slug = isset($options['active_plan']) ? $options['active_plan'] : '';
        $synced_plans = isset($options['synced_plans']) ? $options['synced_plans'] : [];
        $cost_per_verification = 0;
        if (!empty($active_plan_slug) && isset($synced_plans[$active_plan_slug]['price_per_verification'])) {
            $cost_per_verification = (float)$synced_plans[$active_plan_slug]['price_per_verification'];
        }

        // --- Consulta para buscar SOLO usuarios APROBADOS ---
        $approved_users_query = new WP_User_Query([
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key'   => 'guardian_kyc_verification_status',
                    'value' => 'Approved',
                ],
                [
                    'key'     => 'guardian_kyc_verification_date',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        
        $approved_users = $approved_users_query->get_results();
        $stats['total_approved'] = count($approved_users);

        // ===== ¡AQUÍ ESTÁ LA CORRECCIÓN! =====
        // El bucle ahora usa $user (que es un objeto) y leemos su ID con $user->ID
        foreach ($approved_users as $user) {
            $verification_date_str = get_user_meta($user->ID, 'guardian_kyc_verification_date', true);
            if (empty($verification_date_str) || !strtotime($verification_date_str)) { continue; }
            $verification_timestamp = strtotime($verification_date_str);

            // Contar para el mes actual
            if (date('Y-m', $verification_timestamp) == date('Y-m', strtotime($now))) {
                $stats['month']++;
                $stats['consumed_balance_month'] += $cost_per_verification;
            }
            // Contar para los últimos 7 días
            if ($verification_timestamp >= strtotime('-7 days', strtotime($now))) {
                $stats['week']++;
            }
            // Contar para hoy
            if (date('Y-m-d', $verification_timestamp) == date('Y-m-d', strtotime($now))) {
                $stats['today']++;
            }
        }
        return $stats;
    }

    public function get_chart_data() {
        $daily_labels = []; $daily_data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $daily_labels[] = date('d M', strtotime($date));
            $users_on_day = get_users(['fields' => 'ID', 'meta_query' => [['key' => 'guardian_kyc_verification_date', 'value' => $date, 'compare' => 'LIKE']]]);
            $daily_data[] = count($users_on_day);
        }
        $status_labels = ['Aprobadas', 'Rechazadas', 'Declinadas', 'Pendientes', 'No Verificadas'];
        $status_data = [];
        $all_stati = ['Approved', 'Rejected', 'Declined', 'Pending'];
        foreach ($all_stati as $status) {
            $status_data[] = count(get_users(['meta_key' => 'guardian_kyc_verification_status', 'meta_value' => $status, 'fields' => 'ID']));
        }
        $not_verified_query = new WP_User_Query(['meta_query' => [['key' => 'guardian_kyc_verification_status', 'compare' => 'NOT EXISTS']], 'count_total' => true]);
        $status_data[] = $not_verified_query->get_total();
        return ['daily' => ['labels' => $daily_labels, 'data' => $daily_data], 'status' => ['labels' => $status_labels, 'data' => $status_data]];
    }
}