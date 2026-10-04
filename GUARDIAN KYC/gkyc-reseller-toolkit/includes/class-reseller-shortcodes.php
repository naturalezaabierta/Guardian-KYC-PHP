<?php
/**
 * Maneja la lógica y el renderizado de los shortcodes del Kit de Reventa.
 * v3.0.0 - Automatización Final con Formulario de Activación.
 */
class GKYC_Reseller_Shortcodes {

    const MOTHERSHIP_API_URL = 'https://guardiankyc.com/wp-json/guardian-kyc/v1/reseller/get-plans';

    public function init() {
        add_shortcode('gkyc_planes_venta', array($this, 'render_plans_shortcode'));
        add_shortcode('gkyc_checkout_pago', array($this, 'render_checkout_shortcode'));
        add_shortcode('gkyc_calculadora_recarga', array($this, 'render_recharge_calculator_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('gkyc_calculadora_saldo_automatica', array($this, 'render_automated_balance_calculator'));
    }

    // REEMPLAZA tu función enqueue_frontend_scripts con esta
    public function enqueue_frontend_scripts() {
        global $post;
        // Nos aseguramos de que el post sea un objeto válido antes de usarlo
        if (is_a($post, 'WP_Post')) {
            // Lógica mejorada para cargar scripts solo donde se necesiten
            // --- INICIO DE LA CORRECCIÓN DEFINITIVA ---
            if (has_shortcode($post->post_content, 'gkyc_checkout_pago') || has_shortcode($post->post_content, 'gkyc_calculadora_recarga') || has_shortcode($post->post_content, 'gkyc_planes_venta') || has_shortcode($post->post_content, 'gkyc_calculadora_saldo_automatica')) {
            // --- FIN DE LA CORRECCIÓN DEFINITIVA ---                
                wp_enqueue_style('gkyc-reseller-styles', GKYC_RESELLER_URL . 'assets/css/reseller-styles.css', array(), '3.1.1'); // Aumentamos la versión
                
                // Cargamos el JS solo si es el checkout o la calculadora
                if(has_shortcode($post->post_content, 'gkyc_checkout_pago') || has_shortcode($post->post_content, 'gkyc_calculadora_recarga')) {

                    wp_enqueue_script('gkyc-reseller-frontend-js', GKYC_RESELLER_URL . 'assets/js/reseller-frontend.js', array('jquery'), '3.1.1', true); // Aumentamos la versión

                    $options = get_option('gkyc_reseller_options');
                    $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';

                    // Pasamos los datos necesarios a nuestro JavaScript
                    wp_localize_script('gkyc-reseller-frontend-js', 'gkyc_reseller_obj', [
                        'api_key'  => $api_key,
                        // ===== ¡AQUÍ ESTÁ LA CORRECCIÓN! =====
                        'mothership_url' => 'https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/get-client-price',
                        'plans_page_url' => get_site_url(null, 'planes-y-precios')
                    ]);
                }
            }
        }
    }


    private function fetch_partner_data() {
        $options = get_option('gkyc_reseller_options');
        $api_key = isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '';
        if (empty($api_key)) { return new WP_Error('no_api_key', 'API Key no configurada.'); }
        $cache_key = 'gkyc_reseller_data_' . md5($api_key);
        if (isset($_GET['no_cache']) && $_GET['no_cache'] === 'true') { delete_transient($cache_key); }
        $cached_data = get_transient($cache_key);
        if (false !== $cached_data) { return $cached_data; }
        $response = wp_remote_post(self::MOTHERSHIP_API_URL, ['method' => 'POST', 'timeout' => 15, 'headers' => ['Content-Type' => 'application/json'], 'body' => json_encode(['api_key' => $api_key])]);
        if (is_wp_error($response)) { return new WP_Error('connection_error', 'Error de conexión con el servidor.'); }
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($response_code !== 200 || empty($data) || !isset($data['status']) || $data['status'] !== 'success') {
            $error_message = isset($data['message']) ? $data['message'] : 'Respuesta inválida del servidor.';
            return new WP_Error('api_error', $error_message);
        }
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
        return $data;
    }

    private function get_whatsapp_button_html($payment_links) {
        $whatsapp_number = isset($payment_links['whatsapp']) ? preg_replace('/[^0-9]/', '', $payment_links['whatsapp']) : '';
        if (!empty($whatsapp_number)) {
            // Usa siempre el logo por defecto guardado en el plugin
            $whatsapp_logo_url = GKYC_RESELLER_URL . 'assets/images/whatsapp-logo.svg';
            return sprintf(
                '<a href="https://wa.me/%s" class="gkyc-whatsapp-float" target="_blank" rel="noopener noreferrer" title="Contactar por WhatsApp">
                    <img src="%s" alt="WhatsApp Support">
                </a>', 
                $whatsapp_number,
                esc_url($whatsapp_logo_url)
            );
        }
        return '';
    }

    public function render_plans_shortcode($atts) {
        $atts = shortcode_atts(['titulo' => 'Elige el Plan Perfecto para Ti', 'subtitulo' => 'Comienza hoy mismo a proteger tu negocio.', 'color_boton' => '#007cba', 'color_texto_boton' => '#ffffff', 'color_precio' => '#007cba', 'pagina_checkout' => '/procesar-licencia'], $atts, 'gkyc_planes_venta');
        $partner_data = $this->fetch_partner_data();
        if (is_wp_error($partner_data)) {
            if ($partner_data->get_error_code() === 'no_api_key' && current_user_can('manage_options')) { return '<div class="gkyc-reseller-notice error"><strong>Atención, administrador:</strong> Por favor, introduce tu API Key.</div>'; }
            return '<div class="gkyc-reseller-notice error"><strong>Error:</strong> ' . esc_html($partner_data->get_error_message()) . '</div>';
        }
        return $this->build_html_table($partner_data, $atts);
    }
    
    private function build_html_table($partner_data, $atts) {
        $plans = isset($partner_data['plans']) ? $partner_data['plans'] : [];
        if (empty($plans)) { return '<div class="gkyc-reseller-notice">No hay planes de venta configurados.</div>'; }
        $custom_styles = sprintf('--gkyc-color-boton: %s; --gkyc-color-texto-boton: %s; --gkyc-color-precio: %s;', esc_attr($atts['color_boton']), esc_attr($atts['color_texto_boton']), esc_attr($atts['color_precio']));
        ob_start();
        ?>
        <div class="gkyc-reseller-wrapper" style="<?php echo $custom_styles; ?>">
            <?php if (!empty($atts['titulo'])): ?><h2 class="gkyc-main-title"><?php echo esc_html($atts['titulo']); ?></h2><?php endif; ?>
            <?php if (!empty($atts['subtitulo'])): ?><p class="gkyc-main-subtitle"><?php echo esc_html($atts['subtitulo']); ?></p><?php endif; ?>
            <div class="gkyc-pricing-table-container">
                <?php foreach ($plans as $plan): ?>
                    <div class="gkyc-plan-card">
                        <h3 class="gkyc-plan-name"><?php echo esc_html($plan['name']); ?></h3>
                        <div class="gkyc-plan-price">$<?php echo esc_html(number_format((float)$plan['price'], 2)); ?></div>
                        <p class="gkyc-plan-description"><?php echo esc_html($plan['description']); ?></p>
                        <div class="gkyc-plan-cta">
                            <?php $selection_url = add_query_arg(['gkyc_select_plan' => $plan['slug'], 'checkout_page' => $atts['pagina_checkout']], site_url('/')); ?>
                            <a href="<?php echo esc_url($selection_url); ?>" class="gkyc-buy-button">Comprar Ahora</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
             <?php echo $this->get_whatsapp_button_html($partner_data['payment_links']); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_checkout_shortcode($atts) {
        $token = isset($_GET['ticket']) ? sanitize_text_field($_GET['ticket']) : null;
        if (!$token) { return '<div class="gkyc-reseller-notice error">Ticket de compra no válido o expirado.</div>'; }
        
        $ticket_data = get_transient($token);
        if (false === $ticket_data) { return '<div class="gkyc-reseller-notice error">Tu sesión de compra ha expirado.</div>'; }
        
        $selected_plan_slug = $ticket_data['plan_slug'];
        $creation_time = $ticket_data['time'];
        
        $partner_data = $this->fetch_partner_data();
        if (is_wp_error($partner_data)) { return '<div class="gkyc-reseller-notice error">No se pudo cargar la información de pago.</div>'; }
        
        $selected_plan = null;
        if (!empty($partner_data['plans'])) {
             foreach ($partner_data['plans'] as $plan) {
                if (isset($plan['slug']) && $plan['slug'] === $selected_plan_slug) { $selected_plan = $plan; break; }
            }
        }
        if (!$selected_plan) { return '<div class="gkyc-reseller-notice error">El plan seleccionado no es válido.</div>'; }
        
        return $this->build_checkout_page($selected_plan, $partner_data, $token, $creation_time);
    }

        private function build_checkout_page($plan, $partner_data, $token, $creation_time) {
    $payment_links = $partner_data['payment_links'];
    $partner_logo_url = $partner_data['partner_logo_2x1_url'];
    $guarantee_logo_url = GKYC_RESELLER_URL . 'assets/images/satisfaction-guarantee-2109235_1280.png';

    ob_start();
    ?>
    <div class="gkyc-checkout-wrapper" 
        data-plan-slug="<?php echo esc_attr($plan['slug']); ?>" 
        data-api-key="<?php echo esc_attr(get_option('gkyc_reseller_options')['mothership_api_key']); ?>"
        data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
        data-endpoint="<?php echo esc_url_raw('https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/submit-activation-request'); ?>"
        data-token="<?php echo esc_attr($token); ?>">

        <h2 class="gkyc-checkout-page-title">Procesar Licencia</h2>

        <?php if (!empty($partner_logo_url)): ?>
            <div class="gkyc-partner-logo-container">
                <img src="<?php echo esc_url($partner_logo_url); ?>" alt="Logo de tu Empresa" class="gkyc-partner-logo">
            </div>
        <?php endif; ?>

        <div class="gkyc-checkout-card">
            <div class="gkyc-order-summary">
                <h3 class="gkyc-summary-title">Resumen de tu Compra</h3>
                <div class="gkyc-countdown-timer" data-creation-time="<?php echo esc_attr($creation_time); ?>">
                    <span>Tu sesión expira en:</span>
                    <span id="gkyc-timer-display">05:00</span>
                </div>
                <div class="gkyc-summary-row"><span>Plan:</span><span><strong><?php echo esc_html($plan['name']); ?></strong></span></div>
                <hr class="gkyc-summary-divider">
                <div class="gkyc-summary-row total"><span>Total a Pagar:</span><span class="plan-price">$<?php echo esc_html(number_format((float)$plan['price'], 2)); ?></span></div>
            </div>

            <div id="gkyc-main-content-container">
                <div class="gkyc-payment-methods">
                    <h3>1. Realiza tu Pago</h3>
                    <div class="gkyc-payment-buttons">
                        <?php 
                        if (!empty($payment_links['paypal'])): 
                            $paypal_base_url = rtrim($payment_links['paypal'], '/');
                            $plan_price = (float)$plan['price'];
                            $dynamic_paypal_url = $paypal_base_url . '/' . $plan_price;
                        ?>
                            <a href="<?php echo esc_url($dynamic_paypal_url); ?>" target="_blank" class="gkyc-payment-btn paypal">Pagar con PayPal</a>
                        <?php endif; ?>
                        <?php if (!empty($payment_links['usdt_wallet'])): ?><button type="button" class="gkyc-payment-btn usdt" onclick="document.getElementById('gkyc-usdt-details').style.display='block'">Pagar con USDT (Red TRC-20)</button><?php endif; ?>
                    </div>
                    <?php if (!empty($payment_links['usdt_wallet'])): ?>
                        <div id="gkyc-usdt-details" class="gkyc-payment-instructions" style="display:none;"><p>Para pagar con USDT, transfiere el monto a la wallet (Red TRC20):</p><code class="gkyc-wallet-address"><?php echo esc_html($payment_links['usdt_wallet']); ?></code></div>
                    <?php endif; ?>
                </div>
                
                <div class="gkyc-other-payments">
                    <h3>Otros Métodos de Pago y Soporte</h3>
                    <?php if (!empty($payment_links['other_payments'])): ?>
                        <div class="gkyc-payment-instructions other-methods">
                            <?php echo wp_kses_post($payment_links['other_payments']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="gkyc-payment-buttons" style="margin-top: 20px;">
                         <?php echo $this->get_whatsapp_static_button_html($payment_links); ?>
                    </div>
                </div>
                <div class="gkyc-customer-form-container">
                    <h3 style="margin-top: 30px;">2. Activa tu Licencia</h3>
                    <p>Una vez realizado el pago, completa tus datos y haz clic en "Activar mi Licencia" para finalizar.</p>
                    <form id="gkyc-activation-form">
                        <div class="gkyc-form-field"><label for="gkyc-customer-name">Nombre y Apellido</label><input type="text" id="gkyc-customer-name" required></div>
                        <div class="gkyc-form-field"><label for="gkyc-customer-email">Correo Electrónico</label><input type="email" id="gkyc-customer-email" required></div>
                        <div class="gkyc-form-field"><label for="gkyc-customer-phone">Teléfono</label><input type="tel" id="gkyc-customer-phone"></div>
                        <div class="gkyc-form-field"><label for="gkyc-customer-web">Página Web (Opcional)</label><input type="url" id="gkyc-customer-web" placeholder="https://ejemplo.com"></div>
                        <hr class="gkyc-summary-divider">
                        <div class="gkyc-form-field"><label for="gkyc-transaction-id">ID de Transacción (PayPal)</label><input type="text" id="gkyc-transaction-id"></div>
                        <div class="gkyc-form-field"><label for="gkyc-usdt-hash">Si pagaste con USDT, pega aquí el Hash</label><input type="text" id="gkyc-usdt-hash"></div>
                        <hr class="gkyc-summary-divider">
                        <div class="gkyc-form-field">
                            <button type="submit" id="gkyc-submit-activation" class="gkyc-submit-btn">Activar mi Licencia Ahora</button>
                        </div>
                    </form>
                </div>
            </div>
            <div id="gkyc-form-response" class="gkyc-form-response"></div>
        </div>

        <div class="gkyc-global-trust-banner">
            <img src="<?php echo esc_url($guarantee_logo_url); ?>" alt="Garantía de Satisfacción" class="gkyc-guarantee-logo-fixed">
        </div>

        </div>
    <?php
    return ob_get_clean();
}

    // Pega esta nueva función DENTRO de la clase GKYC_Reseller_Shortcodes
    private function get_whatsapp_static_button_html($payment_links) {
        $whatsapp_number = isset($payment_links['whatsapp']) ? preg_replace('/[^0-9]/', '', $payment_links['whatsapp']) : '';
        if (!empty($whatsapp_number)) {
            return sprintf(
                '<a href="https://wa.me/%s" class="gkyc-payment-btn whatsapp" target="_blank" rel="noopener noreferrer">Soporte por WhatsApp</a>', 
                $whatsapp_number
            );
        }
        return '';
    }

    // REEMPLAZA tu función render_recharge_calculator_shortcode con esta versión final
    public function render_recharge_calculator_shortcode($atts) {
        $partner_data = $this->fetch_partner_data();

        if (is_wp_error($partner_data)) {
            return '<div class="gkyc-reseller-notice error">Error: No se pudo cargar la configuración del socio.</div>';
        }

        $payment_links = $partner_data['payment_links'];
        $partner_logo_url = $partner_data['partner_logo_2x1_url'];

        ob_start();
        ?>
        <div class="gkyc-reseller-wrapper gkyc-recharge-calculator">
            
            <?php if (!empty($partner_logo_url)): ?>
                <div class="gkyc-partner-logo-container">
                    <img src="<?php echo esc_url($partner_logo_url); ?>" alt="Logo del Socio" class="gkyc-partner-logo">
                </div>
            <?php endif; ?>

            <div class="gkyc-checkout-card">
                <div class="gkyc-order-summary">
                    <h3 class="gkyc-summary-title">Recarga de Saldo para Clientes</h3>
                    <p class="gkyc-summary-subtitle">1. Ingresa tu correo, selecciona el monto y realiza el pago.</p>
                    
                    <div class="gkyc-form-field">
                        <label for="gkyc-customer-email">Tu Correo Electrónico</label>
                        <input type="email" id="gkyc-customer-email" required placeholder="El email asociado a tu licencia">
                        <div id="gkyc-plan-detector-result"></div>
                    </div>

                    <div class="gkyc-form-field">
                        <label for="gkyc-recharge-slider">Desliza para seleccionar el monto</label>
                        <input type="range" min="20" max="5000" value="100" step="10" class="gkyc-slider" id="gkyc-recharge-slider">
                    </div>
                    
                    <div class="gkyc-recharge-totals">
                        <div class="gkyc-total-row">
                            <span>Total a Pagar:</span>
                            <span id="gkyc-display-monto">$100.00</span>
                        </div>
                        <div id="gkyc-display-verifications"></div>
                    </div>
                </div>

                <div class="gkyc-payment-methods">
                    <div class="gkyc-payment-buttons">
                        <?php if (!empty($payment_links['paypal'])): ?>
                            <a id="gkyc-paypal-recharge-btn" 
                            href="<?php echo esc_url($payment_links['paypal']); ?>" 
                            data-base-url="<?php echo esc_url(rtrim($payment_links['paypal'], '/')); ?>" 
                            target="_blank" 
                            class="gkyc-payment-btn paypal">Pagar con PayPal</a>
                        <?php endif; ?>
                        <?php if (!empty($payment_links['usdt_wallet'])): ?><button type="button" class="gkyc-payment-btn usdt" onclick="document.getElementById('gkyc-usdt-details').style.display='block'">Pagar con USDT</button><?php endif; ?>
                    </div>
                    <?php if (!empty($payment_links['usdt_wallet'])): ?>
                        <div id="gkyc-usdt-details" class="gkyc-payment-instructions" style="display:none;"><p>Para pagar con USDT (Red TRC20), transfiere el monto a:</p><code class="gkyc-wallet-address"><?php echo esc_html($payment_links['usdt_wallet']); ?></code></div>
                    <?php endif; ?>
                </div>

                <div class="gkyc-other-payments">
                    <h3>Otros Métodos de Pago y Soporte</h3>
                    <?php if (!empty($payment_links['other_payments'])): ?>
                        <div class="gkyc-payment-instructions other-methods">
                            <?php echo wp_kses_post($payment_links['other_payments']); ?>
                        </div>
                    <?php endif; ?>
                    <div class="gkyc-payment-buttons" style="margin-top: 20px;">
                        <?php echo $this->get_whatsapp_static_button_html($payment_links); ?>
                    </div>
                </div>
                </div>

            <div class="gkyc-checkout-card" style="margin-top: 30px;">
                <div class="gkyc-order-summary">
                    <h3 class="gkyc-summary-title">2. Confirma tu Recarga</h3>
                    <p class="gkyc-summary-subtitle">Una vez realizado el pago, completa tus datos y adjunta el comprobante para procesar tu saldo.</p>
                    
                    <form id="gkyc-recharge-confirmation-form">
                        <div class="gkyc-form-field">
                            <label for="gkyc-customer-name">Tu Nombre y Apellido</label>
                            <input type="text" id="gkyc-customer-name" required>
                        </div>
                        <div class="gkyc-form-field">
                            <label for="gkyc-transaction-id">ID de Transacción (PayPal/Stripe)</label>
                            <input type="text" id="gkyc-transaction-id">
                        </div>
                        <div class="gkyc-form-field">
                            <label for="gkyc-usdt-hash">Hash de Transacción (USDT)</label>
                            <input type="text" id="gkyc-usdt-hash">
                        </div>
                        <div class="gkyc-form-field">
                            <label for="gkyc-comprobante">Adjuntar Comprobante (Opcional)</label>
                            <input type="file" id="gkyc-comprobante" accept="image/png, image/jpeg, image/gif, application/pdf">
                        </div>
                        <div class="gkyc-form-field" style="margin-top: 20px;">
                            <button type="submit" id="gkyc-submit-recharge" class="gkyc-submit-btn">Confirmar Mi Recarga</button>
                        </div>
                    </form>
                    <div id="gkyc-recharge-form-response" style="margin-top: 20px;"></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- INICIO DEL CÓDIGO DE REEMPLAZO ---

    // --- INICIO DEL CÓDIGO DE REEMPLAZO v2 ---

    public function render_automated_balance_calculator($atts) {
        $balance_product_id = 0;
        $args = array( 'post_type' => 'product', 'posts_per_page' => 1, 'meta_query' => [['key' => '_is_gkyc_balance_product', 'value' => 'yes']], 'fields' => 'ids' );
        $products = new WP_Query($args);
        if ($products->have_posts()) { $balance_product_id = $products->posts[0]; }
        wp_reset_postdata();

        if ( !$balance_product_id ) {
            return current_user_can('manage_options') ? '<div class="gkyc-reseller-notice error"><strong>Atención, administrador:</strong> Producto de saldo no encontrado. Desactiva y reactiva el plugin Kit de Reventa.</div>' : '<div class="gkyc-reseller-notice error">Servicio no disponible.</div>';
        }

        $options = get_option('gkyc_reseller_options');
        wp_enqueue_script('gkyc-reseller-calculator-js', GKYC_RESELLER_URL . 'assets/js/reseller-calculator.js', array('jquery'), '1.1.6', true);
        wp_localize_script('gkyc-reseller-calculator-js', 'gkycCalculatorData', [
            'product_id'   => $balance_product_id,
            'checkout_url' => wc_get_checkout_url(),
            'partner_api_key' => isset($options['mothership_api_key']) ? $options['mothership_api_key'] : '',
            'mothership_url'  => 'https://guardiankyc.com/wp-json/guardian-kyc/v1/partner/get-client-price'
        ]);

        $partner_data = $this->fetch_partner_data();
        $partner_logo_url = is_wp_error($partner_data) ? '' : ($partner_data['partner_logo_2x1_url'] ?? '');
        $payment_links = is_wp_error($partner_data) ? [] : ($partner_data['payment_links'] ?? []);

        ob_start();
        ?>
        <div class="gkyc-reseller-wrapper gkyc-recharge-calculator">

            <?php if (!empty($partner_logo_url)): ?>
                <div class="gkyc-partner-logo-container">
                    <img src="<?php echo esc_url($partner_logo_url); ?>" alt="Logo del Socio" class="gkyc-partner-logo">
                </div>
            <?php endif; ?>

            <div class="gkyc-checkout-card">
                <div class="gkyc-order-summary">
                    <h3 class="gkyc-summary-title">Recarga de Saldo Automática</h3>
                    <p class="gkyc-summary-subtitle">1. Ingresa tu correo, selecciona el monto y realiza el pago.</p>

                    <div class="gkyc-form-field">
                        <label for="gkyc-customer-email-auto">Tu Correo Electrónico</label>
                        <input type="email" id="gkyc-customer-email-auto" required placeholder="El email asociado a tu licencia">
                        <div id="gkyc-plan-detector-result-auto" class="gkyc-plan-detector"></div>
                    </div>

                    <div class="gkyc-form-field">
                        <label for="gkyc-recharge-slider-auto">Desliza para seleccionar el monto</label>
                        <input type="range" min="20" max="5000" value="100" step="10" class="gkyc-slider" id="gkyc-recharge-slider-auto">
                    </div>

                    <div class="gkyc-recharge-totals">
                        <div class="gkyc-total-row">
                            <span>Total a Pagar:</span>
                            <span id="gkyc-display-monto-auto">$100.00</span>
                        </div>
                        <div id="gkyc-display-verifications-auto"></div>
                    </div>
                </div>

                <div class="gkyc-payment-methods">
                    <h3 style="margin-top:0; text-align:center;">2. Procede al pago seguro</h3>
                    <div class="gkyc-payment-buttons">
                        <a href="#" id="gkyc-buy-balance-btn-auto" class="gkyc-payment-btn" style="background-color: #005a87; text-decoration: none;">Continuar al Pago</a>
                    </div>
                </div>

                <div class="gkyc-other-payments">
                    <h3>Soporte</h3>
                    <div class="gkyc-payment-buttons" style="margin-top: 20px;">
                        <?php echo $this->get_whatsapp_static_button_html($payment_links); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // --- FIN DEL CÓDIGO DE REEMPLAZO v2 ---
}