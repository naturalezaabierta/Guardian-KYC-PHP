<?php
/**
 * Maneja la página de Ajustes para el plugin Mothership.
 * v2.2 - CORREGIDO: Unificados todos los campos bajo un solo grupo de ajustes para solucionar el bug de guardado.
 */
class Mothership_Settings_Page {

    private $options;

    public function init() {
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_scripts($hook) {
        // Se asegura de que los scripts solo se carguen en nuestra página de ajustes
        if (strpos($hook, 'gkyc_mothership_settings') === false) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script('gkyc-mothership-admin', MOTHERSHIP_URL . 'assets/js/mothership-admin-scripts.js', array('jquery'), '1.0.1', true);
    }

    public function create_admin_page() {
        // Obtenemos todas las opciones guardadas de nuestro único grupo.
        $this->options = get_option('gkyc_mothership_settings');
        ?>
        <div class="wrap">
            <h1>Ajustes de Guardián KYC - Mothership</h1>
            <p>Desde aquí puedes configurar las notificaciones automáticas, plantillas de correo y otros ajustes generales.</p>
            
            <form method="post" action="options.php">
                <?php
                // Se llama a settings_fields UNA SOLA VEZ para nuestro grupo unificado.
                settings_fields('gkyc_mothership_settings_group');
                
                // Se muestran todas las secciones registradas en nuestra página.
                do_settings_sections('gkyc_mothership_settings_page');
                
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function settings_init() {
    // Registramos UN ÚNICO grupo de opciones que contendrá TODO.
    register_setting(
        'gkyc_mothership_settings_group',      // Nombre del grupo
        'gkyc_mothership_settings',            // Nombre de la opción en la BBDD
        array($this, 'sanitize_all_settings')  // UNA SOLA función de sanitización
    );

    // ===== SECCIÓN 1: AJUSTES GENERALES (INCLUYE WHATSAPP Y API MAESTRA) =====
    add_settings_section(
        'general_settings_section',                  // ID de la sección
        'Ajustes Generales',                         // Título que se muestra
        null,                                        // Función de callback (opcional)
        'gkyc_mothership_settings_page'              // Página donde se mostrará
    );

    add_settings_field(
        'support_whatsapp',
        'Número de WhatsApp para Soporte',
        array($this, 'support_whatsapp_callback'),
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );
    
    // ===== ¡AQUÍ ES DONDE DEBERÍA ESTAR EL NUEVO CAMPO! =====
    add_settings_field(
        'master_didit_api_key',
        'API Key Maestra de Didit',
        array($this, 'master_didit_api_key_callback'),
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );

    // ===== PEGA ESTE BLOQUE NUEVO AQUÍ =====
    add_settings_field(
        'didit_webhook_secret',
        'Didit Webhook Secret Key',
        array($this, 'didit_webhook_secret_callback'), // Su propia función de callback
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );

    // --- INICIO DE LA MODIFICACIÓN ---
    add_settings_field(
        'plugin_download_url_direct', // ID cambiado
        'URL Plugin Cliente (Directos y Socios)', // Etiqueta cambiada
        array($this, 'plugin_download_url_direct_callback'), // Nueva función callback
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );

    add_settings_field(
        'plugin_download_url_reseller', // Nuevo ID
        'URL Complemento KYC (Sub-clientes)', // Nueva etiqueta
        array($this, 'plugin_download_url_reseller_callback'), // Nueva función callback
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );
    // --- FIN DE LA MODIFICACIÓN ---

    // Añade este bloque justo debajo del anterior
    add_settings_field(
        'mini_plugin_download_url',
        'URL de Descarga del Mini-Plugin (Reventa)',
        array($this, 'mini_plugin_download_url_callback'),
        'gkyc_mothership_settings_page',
        'general_settings_section'
    );

    // ===== SECCIÓN 2: NOTIFICACIONES DE SALDO BAJO =====
    add_settings_section(
        'low_balance_notification_section',
        'Notificaciones de Saldo Bajo',
        null,
        'gkyc_mothership_settings_page'
    );
    
    add_settings_field('enable_notifications', 'Activar Notificaciones', array($this, 'enable_notifications_callback'), 'gkyc_mothership_settings_page', 'low_balance_notification_section');
    add_settings_field('balance_threshold', 'Umbral de Saldo (USD)', array($this, 'balance_threshold_callback'), 'gkyc_mothership_settings_page', 'low_balance_notification_section');
    add_settings_field('notification_subject', 'Asunto del Correo', array($this, 'notification_subject_callback'), 'gkyc_mothership_settings_page', 'low_balance_notification_section');
    add_settings_field('notification_body', 'Cuerpo del Correo', array($this, 'notification_body_callback'), 'gkyc_mothership_settings_page', 'low_balance_notification_section');

    // ===== SECCIÓN 3: PLANTILLA DE CORREO PARA PARTNERS =====
    add_settings_section(
        'partner_welcome_email_section',
        'Plantilla de Correo de Bienvenida para Partners',
        null,
        'gkyc_mothership_settings_page' // Se muestra en la MISMA página
    );

    add_settings_field('partner_email_subject', 'Asunto del Correo', array($this, 'partner_email_subject_callback'), 'gkyc_mothership_settings_page', 'partner_welcome_email_section');
    add_settings_field('partner_email_body', 'Cuerpo del Correo', array($this, 'partner_email_body_callback'), 'gkyc_mothership_settings_page', 'partner_welcome_email_section');
    add_settings_field('partner_email_pdf', 'Guía PDF para Adjuntar', array($this, 'partner_email_pdf_callback'), 'gkyc_mothership_settings_page', 'partner_welcome_email_section');

    // ===== AÑADE ESTE NUEVO BLOQUE AL FINAL DE LA SECCIÓN =====
    add_settings_field(
        'default_partner_welcome_subject', 
        'Asunto de Bienvenida (Por Defecto)', 
        array($this, 'default_partner_welcome_subject_callback'), 
        'gkyc_mothership_settings_page', 
        'partner_welcome_email_section'
    );
    add_settings_field(
        'default_partner_welcome_body', 
        'Cuerpo del Correo de Bienvenida (Por Defecto)', 
        array($this, 'default_partner_welcome_body_callback'), 
        'gkyc_mothership_settings_page', 
        'partner_welcome_email_section'
    );

    // ===== SECCIÓN 4: SISTEMA DE BONIFICACIÓN POR RECARGA DE SALDO =====
    add_settings_section(
        'bonus_system_section',
        'Sistema de Bonificación para Socios',
        function() { echo '<p>Define los niveles de bonificación que reciben los socios al comprar Saldo Maestro. El sistema aplicará el primer nivel que coincida de arriba hacia abajo.</p>'; },
        'gkyc_mothership_settings_page'
    );

    // Nivel de Bono 1
    add_settings_field('bonus_tier_1_min', 'Nivel 1: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 1]);
    add_settings_field('bonus_tier_1_percentage', 'Nivel 1: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 1]);
    
    // Nivel de Bono 2
    add_settings_field('bonus_tier_2_min', 'Nivel 2: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 2]);
    add_settings_field('bonus_tier_2_percentage', 'Nivel 2: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 2]);
    
    // Nivel de Bono 3
    add_settings_field('bonus_tier_3_min', 'Nivel 3: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 3]);
    add_settings_field('bonus_tier_3_percentage', 'Nivel 3: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 3]);

    // Nivel de Bono 4
    add_settings_field('bonus_tier_4_min', 'Nivel 4: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 4]);
    add_settings_field('bonus_tier_4_percentage', 'Nivel 4: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 4]);

    // Nivel de Bono 5
    add_settings_field('bonus_tier_5_min', 'Nivel 5: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 5]);
    add_settings_field('bonus_tier_5_percentage', 'Nivel 5: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 5]);

    // Nivel de Bono 6
    add_settings_field('bonus_tier_6_min', 'Nivel 6: Monto Mínimo', array($this, 'bonus_tier_min_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 6]);
    add_settings_field('bonus_tier_6_percentage', 'Nivel 6: Porcentaje de Bono (%)', array($this, 'bonus_tier_percentage_callback'), 'gkyc_mothership_settings_page', 'bonus_system_section', ['tier' => 6]);
}   


    
    // UNA SOLA función que sanitiza todos los campos de todas las secciones
    public function sanitize_all_settings($input) {
        $new_input = [];

        // Campos de Ajustes Generales y Saldo Bajo
        if (isset($input['support_whatsapp'])) { $new_input['support_whatsapp'] = sanitize_text_field($input['support_whatsapp']); }
        if (isset($input['enable_notifications'])) { $new_input['enable_notifications'] = absint($input['enable_notifications']); } else { $new_input['enable_notifications'] = 0; }
        if (isset($input['balance_threshold'])) { $new_input['balance_threshold'] = sanitize_text_field($input['balance_threshold']); }
        if (isset($input['notification_subject'])) { $new_input['notification_subject'] = sanitize_text_field($input['notification_subject']); }
        if (isset($input['notification_body'])) { $new_input['notification_body'] = wp_kses_post($input['notification_body']); }

        // Campos de Email de Partners
        if (isset($input['partner_email_subject'])) { $new_input['partner_email_subject'] = sanitize_text_field($input['partner_email_subject']); }
        if (isset($input['partner_email_body'])) { $new_input['partner_email_body'] = wp_kses_post($input['partner_email_body']); }
        if (isset($input['partner_email_pdf'])) { $new_input['partner_email_pdf'] = esc_url_raw($input['partner_email_pdf']); }

        // Dentro de la función sanitize_all_settings()
        if (isset($input['master_didit_api_key'])) { $new_input['master_didit_api_key'] = sanitize_text_field($input['master_didit_api_key']); }
        // ===== PEGA ESTA LÍNEA NUEVA AQUÍ =====
        if (isset($input['didit_webhook_secret'])) { $new_input['didit_webhook_secret'] = sanitize_text_field($input['didit_webhook_secret']); }
        // AÑADE ESTA LÍNEA
        // --- INICIO DE LA MODIFICACIÓN ---
        if (isset($input['plugin_download_url_direct'])) { 
            $new_input['plugin_download_url_direct'] = esc_url_raw($input['plugin_download_url_direct']); 
        }
        if (isset($input['plugin_download_url_reseller'])) { 
            $new_input['plugin_download_url_reseller'] = esc_url_raw($input['plugin_download_url_reseller']); 
        }
        // --- FIN DE LA MODIFICACIÓN ---
        // AÑADE ESTA LÍNEA
        if (isset($input['mini_plugin_download_url'])) { $new_input['mini_plugin_download_url'] = esc_url_raw($input['mini_plugin_download_url']); }

        if (isset($input['default_partner_welcome_subject'])) { $new_input['default_partner_welcome_subject'] = sanitize_text_field($input['default_partner_welcome_subject']); }
        if (isset($input['default_partner_welcome_body'])) { $new_input['default_partner_welcome_body'] = wp_kses_post($input['default_partner_welcome_body']); }

        // Guardar Sistema de Bonificación
        if (isset($input['bonus_tier_1_min'])) { $new_input['bonus_tier_1_min'] = sanitize_text_field($input['bonus_tier_1_min']); }
        if (isset($input['bonus_tier_1_percentage'])) { $new_input['bonus_tier_1_percentage'] = sanitize_text_field($input['bonus_tier_1_percentage']); }
        if (isset($input['bonus_tier_2_min'])) { $new_input['bonus_tier_2_min'] = sanitize_text_field($input['bonus_tier_2_min']); }
        if (isset($input['bonus_tier_2_percentage'])) { $new_input['bonus_tier_2_percentage'] = sanitize_text_field($input['bonus_tier_2_percentage']); }
        if (isset($input['bonus_tier_3_min'])) { $new_input['bonus_tier_3_min'] = sanitize_text_field($input['bonus_tier_3_min']); }
        if (isset($input['bonus_tier_3_percentage'])) { $new_input['bonus_tier_3_percentage'] = sanitize_text_field($input['bonus_tier_3_percentage']); }   
        if (isset($input['bonus_tier_4_min'])) { $new_input['bonus_tier_4_min'] = sanitize_text_field($input['bonus_tier_4_min']); }
        if (isset($input['bonus_tier_4_percentage'])) { $new_input['bonus_tier_4_percentage'] = sanitize_text_field($input['bonus_tier_4_percentage']); }
        if (isset($input['bonus_tier_5_min'])) { $new_input['bonus_tier_5_min'] = sanitize_text_field($input['bonus_tier_5_min']); }
        if (isset($input['bonus_tier_5_percentage'])) { $new_input['bonus_tier_5_percentage'] = sanitize_text_field($input['bonus_tier_5_percentage']); }
        if (isset($input['bonus_tier_6_min'])) { $new_input['bonus_tier_6_min'] = sanitize_text_field($input['bonus_tier_6_min']); }
        if (isset($input['bonus_tier_6_percentage'])) { $new_input['bonus_tier_6_percentage'] = sanitize_text_field($input['bonus_tier_6_percentage']); }

        return $new_input;
    }

    // --- Callbacks (Funciones que dibujan los campos) ---
    // No cambian mucho, solo los nombres de los campos en el array.

    public function support_whatsapp_callback() {
        printf('<input type="text" id="support_whatsapp" name="gkyc_mothership_settings[support_whatsapp]" value="%s" class="regular-text" placeholder="Ej: 584121234567" />',
            isset($this->options['support_whatsapp']) ? esc_attr($this->options['support_whatsapp']) : ''
        );
        echo '<p class="description">Introduce el número completo, incluyendo el código de país, sin el símbolo de (+).</p>';
    }

    public function enable_notifications_callback() {
        printf('<input type="checkbox" id="enable_notifications" name="gkyc_mothership_settings[enable_notifications]" value="1" %s />',
            checked(1, $this->options['enable_notifications'] ?? 0, false)
        );
        echo '<label for="enable_notifications"> Marcar para activar el envío de correos de saldo bajo.</label>';
    }

    public function balance_threshold_callback() {
        printf('<input type="number" step="0.01" id="balance_threshold" name="gkyc_mothership_settings[balance_threshold]" value="%s" placeholder="Ej: 10.00" />',
            isset($this->options['balance_threshold']) ? esc_attr($this->options['balance_threshold']) : ''
        );
    }

    public function notification_subject_callback() {
        printf('<input type="text" id="notification_subject" name="gkyc_mothership_settings[notification_subject]" value="%s" class="regular-text" placeholder="Tu saldo en Guardián KYC está bajo" />',
            isset($this->options['notification_subject']) ? esc_attr($this->options['notification_subject']) : ''
        );
    }

    public function notification_body_callback() {
        $content = isset($this->options['notification_body']) ? $this->options['notification_body'] : "Hola [nombre_cliente],\n\nHemos notado que tu saldo actual es de [saldo_actual].\n\nPara evitar interrupciones en tu servicio, te recomendamos recargar tu saldo pronto.\n\nGracias,\nEl equipo de Guardián KYC";
        wp_editor($content, 'notification_body', ['textarea_name' => 'gkyc_mothership_settings[notification_body]', 'media_buttons' => false, 'textarea_rows' => 10]);
        echo '<p class="description">Puedes usar los siguientes shortcodes: <code>[nombre_cliente]</code> y <code>[saldo_actual]</code>.</p>';
    }
    
    public function partner_email_subject_callback() {
        printf('<input type="text" id="partner_email_subject" name="gkyc_mothership_settings[partner_email_subject]" value="%s" class="regular-text" placeholder="¡Bienvenido al Programa de Socios!" />',
            isset($this->options['partner_email_subject']) ? esc_attr($this->options['partner_email_subject']) : ''
        );
    }

    public function partner_email_body_callback() {
        $content = isset($this->options['partner_email_body']) ? $this->options['partner_email_body'] : '';
        wp_editor($content, 'partneremailbody', ['textarea_name' => 'gkyc_mothership_settings[partner_email_body]', 'media_buttons' => false, 'textarea_rows' => 15]);
        echo '<p class="description">Shortcodes disponibles: <code>[nombre_del_socio]</code>, <code>[API_KEY_MAESTRA_DEL_PARTNER]</code>, <code>[ENLACE_DE_DESCARGA_PLUGIN_CLIENTE]</code> y <code>[ENLACE_DE_DESCARGA_KIT_REVENTA]</code>.</p>';
    }

    public function partner_email_pdf_callback() {
        $pdf_url = isset($this->options['partner_email_pdf']) ? esc_url($this->options['partner_email_pdf']) : '';
        echo '<input type="text" id="partner_pdf_url" name="gkyc_mothership_settings[partner_email_pdf]" value="' . $pdf_url . '" class="regular-text" readonly />';
        echo '<button type="button" id="upload_pdf_button" class="button">Seleccionar o Subir PDF</button>';
        echo '<p class="description">Selecciona el archivo PDF de la guía para Partners. Este archivo se adjuntará al correo de bienvenida.</p>';
    }

    public function master_didit_api_key_callback() {
    printf('<input type="text" id="master_didit_api_key" name="gkyc_mothership_settings[master_didit_api_key]" value="%s" class="regular-text" />',
        isset($this->options['master_didit_api_key']) ? esc_attr($this->options['master_didit_api_key']) : ''
    );
    echo '<p class="description">Pega aquí la API Key principal que te proporcionó Didit para la creación automática de aplicaciones.</p>';
    }

    // ===== PEGA ESTA FUNCIÓN NUEVA COMPLETA AQUÍ =====
    public function didit_webhook_secret_callback() {
        printf('<input type="password" id="didit_webhook_secret" name="gkyc_mothership_settings[didit_webhook_secret]" value="%s" class="regular-text" />',
            isset($this->options['didit_webhook_secret']) ? esc_attr($this->options['didit_webhook_secret']) : ''
        );
        echo '<p class="description">Pega aquí la Webhook Secret Key que te proporcionó Didit para la validación de seguridad.</p>';
    }

        // --- INICIO DE LA MODIFICACIÓN ---
    public function plugin_download_url_direct_callback() {
        printf('<input type="url" id="plugin_download_url_direct" name="gkyc_mothership_settings[plugin_download_url_direct]" value="%s" class="regular-text" placeholder="https://ruta/a/guardian-kyc.zip" />',
            isset($this->options['plugin_download_url_direct']) ? esc_url($this->options['plugin_download_url_direct']) : ''
        );
        echo '<p class="description">Enlace para el plugin "Guardián KYC". Se enviará a clientes directos y nuevos socios.</p>';
    }

    public function plugin_download_url_reseller_callback() {
        printf('<input type="url" id="plugin_download_url_reseller" name="gkyc_mothership_settings[plugin_download_url_reseller]" value="%s" class="regular-text" placeholder="https://ruta/a/complemento-kyc.zip" />',
            isset($this->options['plugin_download_url_reseller']) ? esc_url($this->options['plugin_download_url_reseller']) : ''
        );
        echo '<p class="description">Enlace para el "Complemento de Verificación KYC". Se enviará a los clientes de tus socios.</p>';
    }
    // La función para el mini-plugin no cambia, solo la movemos aquí para mantener el orden
    public function mini_plugin_download_url_callback() {
        printf('<input type="url" id="mini_plugin_download_url" name="gkyc_mothership_settings[mini_plugin_download_url]" value="%s" class="regular-text" placeholder="https://ruta/a/tu/mini-plugin.zip" />',
            isset($this->options['mini_plugin_download_url']) ? esc_url($this->options['mini_plugin_download_url']) : ''
        );
        echo '<p class="description">Este será el enlace de descarga para el "Kit de Herramientas de Reventa" de los socios (FASE 5).</p>';
    }
        // --- FIN DE LA MODIFICACIÓN ---

    public function default_partner_welcome_subject_callback() {
        printf('<input type="text" id="default_partner_welcome_subject" name="gkyc_mothership_settings[default_partner_welcome_subject]" value="%s" class="regular-text" />',
            isset($this->options['default_partner_welcome_subject']) ? esc_attr($this->options['default_partner_welcome_subject']) : '¡Bienvenido! Tus datos de acceso han sido creados'
        );
        echo '<p class="description">Este será el asunto por defecto para los correos de bienvenida que se envíen en nombre de tus socios.</p>';
    }

    public function default_partner_welcome_body_callback() {
        $content = isset($this->options['default_partner_welcome_body']) ? $this->options['default_partner_welcome_body'] : "<h2>¡Hola, [nombre_cliente]!</h2><p>Tu cuenta ha sido creada por nuestro socio <strong>[nombre_partner]</strong>.</p><p style='background-color:#fffbe6; border-left:4px solid #ffe58f; padding: 1em;'><strong>Importante:</strong> Tu licencia está siendo configurada. Recibirás un segundo correo de confirmación tan pronto como tu plan esté completamente activado.</p><p><strong>Tu API Key para integrar el servicio es:</strong></p><p style='background-color:#f0f0f0; padding:10px; font-family:monospace; border-radius:5px;'>[API_Key]</p>";
        wp_editor($content, 'defaultpartnerwelcomebody', ['textarea_name' => 'gkyc_mothership_settings[default_partner_welcome_body]', 'media_buttons' => false, 'textarea_rows' => 15]);
        echo '<p class="description">Puedes usar los shortcodes: <code>[nombre_cliente]</code>, <code>[nombre_partner]</code>, <code>[API_Key]</code>. Este será el mensaje por defecto si un socio no configura el suyo propio.</p>';
    }

    // ===== AQUÍ ES DONDE DEBES PEGAR LAS DOS NUEVAS FUNCIONES =====
    public function bonus_tier_min_callback($args) {
        $tier = $args['tier'];
        $option_name = "bonus_tier_{$tier}_min";
        printf('<input type="number" step="0.01" id="%s" name="gkyc_mothership_settings[%s]" value="%s" placeholder="Ej: 100.00" />',
            esc_attr($option_name), esc_attr($option_name),
            isset($this->options[$option_name]) ? esc_attr($this->options[$option_name]) : ''
        );
        echo '<p class="description">Monto mínimo de compra para aplicar este bono.</p>';
    }

    public function bonus_tier_percentage_callback($args) {
        $tier = $args['tier'];
        $option_name = "bonus_tier_{$tier}_percentage";
        printf('<input type="number" step="0.01" id="%s" name="gkyc_mothership_settings[%s]" value="%s" placeholder="Ej: 10" />',
            esc_attr($option_name), esc_attr($option_name),
            isset($this->options[$option_name]) ? esc_attr($this->options[$option_name]) : ''
        );
         echo '<p class="description">Porcentaje de bono a otorgar (solo el número).</p>';
    }
    // ===== HASTA AQUÍ =====
}