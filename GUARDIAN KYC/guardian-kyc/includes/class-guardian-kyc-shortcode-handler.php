<?php
/**
 * Maneja la creación y renderizado de los shortcodes de Guardián KYC.
 * v2.0 - Refactorizado para usar un formulario de envío en lugar de AJAX para iniciar.
 */
class Guardian_KYC_Shortcode_Handler {

    public function __construct() {
        // Añadimos un hook que se ejecuta ANTES de que se cargue la página.
        add_action( 'template_redirect', array( $this, 'handle_verification_form_submit' ) );
    }

    public function register_shortcode() {
        add_shortcode( 'guardian_kyc_verificacion', array( $this, 'render_shortcode' ) );
        add_shortcode( 'guardian_kyc_resultado', array( $this, 'render_result_shortcode' ) );
    }

    /**
     * Esta función se ejecuta antes de que la página se muestre.
     * Revisa si nuestro formulario de verificación fue enviado.
     */
    public function handle_verification_form_submit() {
        // Solo continuamos si estamos en la página de verificación y si el formulario fue enviado.
        if ( is_page('verificacion-de-identidad') && isset( $_POST['gkyc_start_verification_nonce'] ) ) {
            
            // Verificamos el nonce de seguridad
            if ( ! wp_verify_nonce( $_POST['gkyc_start_verification_nonce'], 'gkyc_start_verification_action' ) ) {
                // Si la seguridad falla, no hacemos nada. Podríamos mostrar un error.
                return;
            }
            
            // Si la seguridad pasa, iniciamos el proceso del lado del servidor.
            $api_handler = new Guardian_KYC_Api_Handler();
            $result = $api_handler->start_verification_process();

            if ( ! is_wp_error( $result ) && ! empty( $result['redirect_url'] ) ) {
                // ¡Éxito! Redirigimos al usuario a la URL de Didit y detenemos la ejecución.
                wp_redirect( $result['redirect_url'] );
                exit;
            } else {
                // Si hay un error, lo guardamos para mostrarlo en la página.
                // (Esta parte se puede mejorar con un manejo de errores más elegante).
                wp_die( 'Hubo un error al iniciar la verificación: ' . $result->get_error_message() );
            }
        }
    }

    /**
     * Renderiza el shortcode [guardian_kyc_verificacion] que ahora es un formulario.
     */
    public function render_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Por favor, inicia sesión para verificar tu identidad.</p>';
        }

        // El resto del código para obtener logos y URLs se queda igual...
        $options = get_option('guardian_kyc_options');
        $license_plan = isset($options['license_plan']) ? $options['license_plan'] : 'Básico';
        // --- INICIO DEL CÓDIGO DE REEMPLAZO ---
        $partner_logo_url = isset($options['partner_logo_url']) ? esc_url($options['partner_logo_url']) : '';
        $custom_logo_url = isset($options['custom_logo']) ? esc_url($options['custom_logo']) : '';
        $default_logo_url = GUARDIAN_KYC_URL . 'assets/images/guardian-kyc-logo.png';

        $logo_to_display = $default_logo_url; // Empezamos con el logo por defecto

        if (!empty($partner_logo_url)) {
            // Si existe un logo de socio, tiene la máxima prioridad
            $logo_to_display = $partner_logo_url;
        } elseif ($license_plan === 'Premium' && !empty($custom_logo_url)) {
            // Si no, usamos el logo personalizado del cliente (si es Premium)
            $logo_to_display = $custom_logo_url;
        }
        // --- FIN DEL CÓDIGO DE REEMPLAZO ---
        $terms_url = isset($options['terms_url']) && !empty($options['terms_url']) ? esc_url($options['terms_url']) : '';
        $privacy_url = isset($options['privacy_url']) && !empty($options['privacy_url']) ? esc_url($options['privacy_url']) : get_privacy_policy_url();
        
        ob_start();
        ?>
        <form id="guardian-kyc-form" method="POST" action="">
            <div class="guardian-kyc-wrapper" style="max-width: 480px; margin: 0 auto; padding: 2.5em; border: 1px solid #e0e0e0; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.07); text-align: center; font-family: sans-serif;">
                
                <?php wp_nonce_field( 'gkyc_start_verification_action', 'gkyc_start_verification_nonce' ); ?>
                
                <img src="<?php echo esc_url($logo_to_display); ?>" alt="Logo de Verificación" style="max-width: 160px; height: auto; margin-bottom: 1.5em; border-radius: 50%;">
                <p style="color: #555; margin-bottom: 2em; font-size: 16px;">Para mantener una comunidad segura, te agradecemos realizar este breve proceso de verificación de identidad.</p>
                <p style="font-size: 14px; color: #333; background-color: #f7f7f7; border-left: 4px solid #007cba; padding: 12px 18px; margin-bottom: 2em; text-align: left;">
                    <strong>Por favor, ten a mano tu documento de identidad.</strong><br>
                    El proceso solo tomará alrededor de 1 minuto.
                </p>
                <div class="guardian-kyc-consent-wrapper" style="text-align: left; margin-bottom: 2em; font-size: 14px; color: #333;">
                    <?php if ($privacy_url) : ?>
                        <p><label><input type="checkbox" id="guardian_kyc_privacy_consent" name="guardian_kyc_privacy_consent"> Acepto las <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer">Políticas de Privacidad</a>.</label></p>
                    <?php endif; ?>
                    <?php if ($terms_url) : ?>
                        <p><label><input type="checkbox" id="guardian_kyc_terms_consent" name="guardian_kyc_terms_consent"> Acepto los <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer">Términos y Condiciones</a>.</label></p>
                    <?php endif; ?>
                </div>

                <button type="submit" id="guardian-kyc-start-verification-btn" class="button button-primary" disabled style="width: 100%; padding: 12px; font-size: 16px; font-weight: bold; cursor: not-allowed; border-radius: 8px;">
                    Iniciar Verificación Segura
                </button>
                <p style="font-size: 12px; color: #999; margin-top: 2.5em; margin-bottom: 0;">Proceso seguro potenciado por <strong>Guardián KYC</strong></p>
            </div>
        </form>
        <?php
        return ob_get_clean();
    }

    public function render_result_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Por favor, inicia sesión para ver el resultado de tu verificación.</p>';
        }
        $html = '<div class="guardian-kyc-result-page-wrapper">';
        $html .= '<div id="guardian-kyc-result-container" style="text-align: center; padding: 40px 20px;">Cargando resultado...</div>';
        $html .= '</div>';
        return $html;
    }
}