<?php
/**
 * Maneja la creación y gestión del Custom Post Type 'Clientes'.
 * v1.8 - Sincronizado el panel de admin (backend) con el panel de socio (frontend).
 */
class Mothership_Client_CPT {

    /**
     * Registra los hooks necesarios para el CPT.
     */
    public function init() {
        add_action('init', array($this, 'register_cpt'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_cliente_kyc', array($this, 'save_meta_data'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_uploader_scripts'));
    }

    /**
     * Carga los scripts de WordPress necesarios para el selector de medios.
     */
    public function enqueue_media_uploader_scripts($hook) {
        if ($hook == 'post.php' || $hook == 'post-new.php') {
            global $post;
            if (isset($post->post_type) && $post->post_type == 'cliente_kyc') {
                wp_enqueue_media();
            }
        }
    }

    /**
     * Registra el Custom Post Type 'cliente_kyc'.
     */
    public function register_cpt() {
        $labels = array( 'name' => 'Clientes', 'singular_name' => 'Cliente', 'add_new_item' => 'Añadir Nuevo Cliente', 'edit_item' => 'Editar Cliente', 'all_items' => 'Todos los Clientes' );
        $args = array( 'label' => 'Clientes', 'labels' => $labels, 'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 20, 'menu_icon' => 'dashicons-businessperson', 'supports' => array('title'), 'rewrite' => false );
        register_post_type('cliente_kyc', $args);
    }

    /**
     * Añade el metabox para los datos del cliente.
     */
    public function add_meta_boxes() {
        add_meta_box( 'kyc_client_data_metabox', 'Datos de Conexión y Cuenta del Cliente', array($this, 'render_metabox_html'), 'cliente_kyc', 'normal', 'high' );
    }

    /**
     * Dibuja el contenido del metabox.
     */
    public function render_metabox_html($post) {
        wp_nonce_field('kyc_client_data_nonce_action', 'kyc_client_data_nonce');

        $email = get_post_meta($post->ID, '_email', true);
        $api_key_mothership = get_post_meta($post->ID, '_api_key_mothership', true);
        $api_key_didit = get_post_meta($post->ID, '_api_key_didit', true);
        $balance = get_post_meta($post->ID, '_balance', true);
        $phone = get_post_meta($post->ID, '_phone_number', true);
        $license_type = get_post_meta($post->ID, '_license_type', true);
        $is_partner = (stripos($license_type, 'Partner') !== false);
        $associated_user_id = get_post_meta($post->ID, '_user_id', true);
        ?>
        <table class="form-table">
            <tbody>
                <tr>
                    <th><label for="kyc_email">Correo Electrónico del Cliente</label></th>
                    <td><input type="email" id="kyc_email" name="kyc_email" value="<?php echo esc_attr($email); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th><label for="kyc_phone">Teléfono del Cliente</label></th>
                    <td><input type="tel" id="kyc_phone" name="kyc_phone" value="<?php echo esc_attr($phone); ?>" class="regular-text"></td>
                </tr>
                <tr style="background-color: #f8f9fa;">
                    <th><label for="kyc_partner_support_whatsapp">WhatsApp de Soporte del Socio</label></th>
                    <td>
                        <input type="text" id="kyc_partner_support_whatsapp" name="kyc_partner_support_whatsapp" value="<?php echo esc_attr( get_post_meta($post->ID, '_partner_support_whatsapp', true) ); ?>" class="regular-text" placeholder="Ej: 584121234567">
                        <p class="description">El número al que los clientes escribirán para soporte. Incluye el código de país sin el (+).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="kyc_balance">Saldo Actual (USD)</label></th>
                    <td><input type="number" step="0.01" id="kyc_balance" name="kyc_balance" value="<?php echo esc_attr($balance); ?>" placeholder="Ej: 100.00"></td>
                </tr>
                
                <?php if ($is_partner) : ?>
                    <tr style="border-top: 2px solid #999; background-color: #f8f9fa;">
                        <th><label for="kyc_associated_user">Usuario de WordPress del Socio</label></th>
                        <td>
                            <?php wp_dropdown_users(['name' => 'kyc_associated_user', 'id' => 'kyc_associated_user', 'show_option_none' => ' -- No Asociado -- ', 'selected' => $associated_user_id, 'role__in' => ['customer', 'editor', 'administrator', 'author', 'contributor', 'socio']]); ?>
                            <p class="description">Asocia este perfil de Partner con un usuario real de WordPress para que pueda iniciar sesión y ver su panel.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <th><label for="kyc_license_limit">Límite de Licencias (Partner)</label></th>
                        <td>
                            <input type="number" id="kyc_license_limit" name="kyc_license_limit" value="<?php echo esc_attr( get_post_meta($post->ID, '_license_limit', true) ); ?>" placeholder="Ej: 20">
                            <p class="description">Si este cliente es un Partner, define cuántas licencias puede generar.</p>
                        </td>
                    </tr>
                <?php endif; ?>

                <tr style="background-color: #f8f9fa;">
                    <th><label for="kyc_client_status">Estado del Cliente</label></th>
                    <td>
                        <?php $status = get_post_meta($post->ID, '_client_status', true); ?>
                        <select id="kyc_client_status" name="kyc_client_status">
                            <option value="active" <?php selected($status, 'active'); ?>>Activo</option>
                            <option value="pending" <?php selected($status, 'pending'); ?>>Pendiente de Aprobación</option>
                            <option value="suspended" <?php selected($status, 'suspended'); ?>>Suspendido</option>
                        </select>
                    </td>
                </tr>
                <tr style="background-color: #f8f9fa; border-bottom: 2px solid #999;">
                    <th><label for="kyc_reseller_owner">Asignar a Revendedor (Partner)</label></th>
                    <td>
                        <?php
                        $reseller_owner_id = get_post_meta($post->ID, '_reseller_owner_id', true);
                        $args = ['post_type' => 'cliente_kyc', 'posts_per_page' => -1, 'meta_query' => [['key' => '_license_type', 'value' => 'Partner', 'compare' => 'LIKE']]];
                        $partners = get_posts($args);
                        echo '<select id="kyc_reseller_owner" name="kyc_reseller_owner">';
                        echo '<option value="">-- Cliente Directo (Sin Revendedor) --</option>';
                        if ($partners) {
                            foreach ($partners as $partner) {
                                $partner_post_id = $partner->ID;
                                if ($post->ID === $partner_post_id) continue;
                                echo '<option value="' . esc_attr($partner_post_id) . '" ' . selected($reseller_owner_id, $partner_post_id, false) . '>' . esc_html($partner->post_title) . '</option>';
                            }
                        }
                        echo '</select>';
                        wp_reset_postdata();
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="kyc_api_key_mothership">API Key (Mothership)</label></th>
                    <td><input type="text" id="kyc_api_key_mothership" name="kyc_api_key_mothership" value="<?php echo esc_attr($api_key_mothership); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="kyc_api_key_didit">API Key (Proveedor)</label></th>
                    <td><input type="text" id="kyc_api_key_didit" name="kyc_api_key_didit" value="<?php echo esc_attr($api_key_didit); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="gkyc_webhook_secret">Webhook Secret Key</label></th>
                    <td>
                        <input type="text" id="gkyc_webhook_secret" name="gkyc_webhook_secret" value="<?php echo esc_attr( get_post_meta($post->ID, '_webhook_secret_key', true) ); ?>" class="regular-text">
                        <p class="description">La clave secreta del webhook para esta aplicación específica.</p>
                    </td>
                </tr>
                
                <?php if ($is_partner) : ?>
                    <tr style="border-top: 2px solid #999; background-color: #f0f8ff;">
                        <th colspan="2">
                            <h3 style="padding: 10px 0 0 0; margin: 0;">Configuración de Precios y Pagos del Partner</h3>
                            <p class="description">Estos datos se usarán en la página/widget público del partner.</p>
                        </th>
                    </tr>
                    <?php
                    $plans_query = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
                    if ($plans_query->have_posts()) :
                        while ($plans_query->have_posts()) : $plans_query->the_post();
                            $plan_post = get_post();
                            $plan_title = $plan_post->post_title;
                            if (stripos($plan_title, 'Partner') !== false) continue;
                            $license_price_key = '_partner_license_price_' . $plan_post->ID;
                            $saved_license_price = get_post_meta($post->ID, $license_price_key, true);
                            ?>
                            <tr style="background-color: #f0f8ff;">
                                <th><label for="kyc_<?php echo esc_attr($license_price_key); ?>">Precio Venta (Licencia) "<?php echo esc_html($plan_title); ?>"</label></th>
                                <td><input type="number" step="0.01" id="kyc_<?php echo esc_attr($license_price_key); ?>" name="partner_license_prices[<?php echo esc_attr($license_price_key); ?>]" value="<?php echo esc_attr($saved_license_price); ?>" placeholder="Ej: 49.00"></td>
                            </tr>
                            <?php
                            $verification_price_key = '_partner_verification_price_' . $plan_post->ID;
                            $saved_verification_price = get_post_meta($post->ID, $verification_price_key, true);
                            ?>
                            <tr style="background-color: #f0f8ff;">
                                <th><label for="kyc_<?php echo esc_attr($verification_price_key); ?>">Precio por Verificación para "<?php echo esc_html($plan_title); ?>"</label></th>
                                <td><input type="number" step="0.01" id="kyc_<?php echo esc_attr($verification_price_key); ?>" name="partner_verification_prices[<?php echo esc_attr($verification_price_key); ?>]" value="<?php echo esc_attr($saved_verification_price); ?>" placeholder="Ej: 1.25"></td>
                            </tr>
                            <?php
                        endwhile;
                        wp_reset_postdata();
                    endif;
                    ?>
                    <tr style="background-color: #f0f8ff;">
                        <th><label for="kyc_partner_paypal_link">Enlace de PayPal</label></th>
                        <td><input type="url" id="kyc_partner_paypal_link" name="kyc_partner_paypal_link" value="<?php echo esc_attr( get_post_meta($post->ID, '_partner_paypal_link', true) ); ?>" class="regular-text" placeholder="https://paypal.me/tu-usuario"></td>
                    </tr>
                    <tr style="background-color: #f0f8ff;">
                        <th><label for="kyc_partner_usdt_wallet">Wallet USDT (TRC20)</label></th>
                        <td><input type="text" id="kyc_partner_usdt_wallet" name="kyc_partner_usdt_wallet" value="<?php echo esc_attr( get_post_meta($post->ID, '_partner_usdt_wallet', true) ); ?>" class="regular-text" placeholder="TYourWalletAddressHere..."></td>
                    </tr>
                    
                    <tr style="border-top: 2px solid #999; background-color: #e8f5e9;">
                        <th colspan="2">
                            <h3 style="padding: 10px 0 0 0; margin: 0;">Configuración de Marca Blanca (Branding)</h3>
                            <p class="description">Define la imagen y los enlaces principales de este socio.</p>
                        </th>
                    </tr>

                    <tr style="background-color: #fff8e1;">
                        <th><label for="kyc_partner_support_email">Email de Soporte (para Notificaciones)</label></th>
                        <td>
                            <input type="email" id="kyc_partner_support_email" name="kyc_partner_support_email" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_support_email', true)); ?>" class="regular-text" placeholder="Ej: soporte@mimarca.com">
                            <p class="description"><strong>Importante:</strong> A este correo llegarán las notificaciones de nuevas ventas. Si se deja en blanco, se enviarán al email principal del cliente.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #e8f5e9;">
                        <th><label for="kyc_partner_website_url">Página Web del Socio</label></th>
                        <td><input type="url" id="kyc_partner_website_url" name="kyc_partner_website_url" value="<?php echo esc_attr( get_post_meta($post->ID, '_partner_website_url', true) ); ?>" class="regular-text" placeholder="https://sitiopartner.com"></td>
                    </tr>
                    <tr style="background-color: #e8f5e9;">
                        <th><label for="kyc_partner_recharge_url">URL de Recarga para Clientes</label></th>
                        <td><input type="url" id="kyc_partner_recharge_url" name="kyc_partner_recharge_url" value="<?php echo esc_attr( get_post_meta($post->ID, '_partner_recharge_url', true) ); ?>" class="regular-text" placeholder="https://tu-sitio.com/recargar-saldo"></td>
                    </tr>
                    <tr style="background-color: #e8f5e9;">
                        <th><label for="kyc_partner_logo_1x1_url">Icono (1x1)</label></th>
                        <td>
                            <input type="text" name="kyc_partner_logo_1x1_url" id="kyc_partner_logo_1x1_url" value="<?php echo esc_url(get_post_meta($post->ID, '_partner_logo_1x1_url', true)); ?>" class="regular-text">
                            <button type="button" class="button gkyc-admin-upload-button" data-input-id="kyc_partner_logo_1x1_url">Subir Icono</button>
                        </td>
                    </tr>
                    <tr style="background-color: #e8f5e9;">
                        <th><label for="kyc_partner_logo_2x1_url">Logo Ancho (2x1)</label></th>
                        <td>
                            <input type="text" name="kyc_partner_logo_2x1_url" id="kyc_partner_logo_2x1_url" value="<?php echo esc_url(get_post_meta($post->ID, '_partner_logo_2x1_url', true)); ?>" class="regular-text">
                            <button type="button" class="button gkyc-admin-upload-button" data-input-id="kyc_partner_logo_2x1_url">Subir Logo</button>
                        </td>
                    </tr>
                    <tr style="background-color: #e8f5e9;">
                        <th><label for="kyc_partner_favicon_url">Favicon (1x1)</label></th>
                        <td>
                            <input type="text" name="kyc_partner_favicon_url" id="kyc_partner_favicon_url" value="<?php echo esc_url(get_post_meta($post->ID, '_partner_favicon_url', true)); ?>" class="regular-text">
                            <button type="button" class="button gkyc-admin-upload-button" data-input-id="kyc_partner_favicon_url">Subir Favicon</button>
                        </td>
                    </tr>

                    <tr style="background-color: #e8f5e9;">
                        <th><label for="admin_partner_other_payments">Otros Métodos de Pago</label></th>
                        <td>
                            <?php
                            $other_payments_content = get_post_meta($post->ID, '_partner_other_payments', true);
                            wp_editor($other_payments_content, 'admin_partner_other_payments', [
                                'textarea_name' => 'kyc_partner_other_payments', // Nombre para el POST
                                'media_buttons' => false,
                                'textarea_rows' => 8,
                            ]);
                            ?>
                        </td>
                    </tr>

                    <tr style="border-top: 2px solid #999; background-color: #fff8e1;">
                        <th colspan="2">
                            <h3 style="padding: 10px 0 0 0; margin: 0;">Configuración de Marca Blanca (Correos)</h3>
                            <p class="description">Personaliza la comunicación que reciben tus clientes. Si dejas un campo en blanco, se usará la plantilla por defecto del sistema.</p>
                        </th>
                    </tr>
                    <tr style="background-color: #fff8e1;">
                        <th><label for="kyc_partner_from_name">Nombre del Remitente</label></th>
                        <td>
                            <input type="text" id="kyc_partner_from_name" name="kyc_partner_from_name" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_from_name', true)); ?>" class="regular-text" placeholder="Ej: Soporte de Mi Marca">
                            <p class="description">El nombre que tus clientes verán en su bandeja de entrada.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #fff8e1;">
                        <th><label for="kyc_partner_reply_to_email">Email de Respuesta</label></th>
                        <td>
                            <input type="email" id="kyc_partner_reply_to_email" name="kyc_partner_reply_to_email" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_reply_to_email', true)); ?>" class="regular-text" placeholder="Ej: soporte@mimarca.com">
                            <p class="description">El correo al que los clientes responderán. Debe ser tu email de soporte.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #fff8e1; border-top: 1px solid #f0e6c2;">
                        <th><label for="kyc_partner_welcome_subject">Asunto del Correo de Bienvenida</label></th>
                        <td><input type="text" id="kyc_partner_welcome_subject" name="kyc_partner_welcome_subject" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_welcome_subject', true)); ?>" class="large-text"></td>
                    </tr>
                    <tr style="background-color: #fff8e1;">
                        <th><label for="partner_welcome_body">Cuerpo del Correo de Bienvenida</label></th>
                        <td>
                            <?php
                            $welcome_content = get_post_meta($post->ID, '_partner_welcome_body', true);
                            wp_editor($welcome_content, 'partner_welcome_body', ['textarea_name' => 'partner_welcome_body', 'media_buttons' => false, 'textarea_rows' => 10]);
                            ?>
                            <p class="description">Shortcodes disponibles: <code>[nombre_cliente]</code>, <code>[API_Key]</code>, <code>[email_cliente]</code>, <code>[nombre_partner]</code>.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #fff8e1; border-top: 1px solid #f0e6c2;">
                        <th><label for="kyc_partner_low_balance_subject">Asunto de Alerta de Saldo Bajo</label></th>
                        <td><input type="text" id="kyc_partner_low_balance_subject" name="kyc_partner_low_balance_subject" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_low_balance_subject', true)); ?>" class="large-text"></td>
                    </tr>
                    <tr style="background-color: #fff8e1;">
                        <th><label for="partner_low_balance_body">Cuerpo de Alerta de Saldo Bajo</label></th>
                        <td>
                            <?php
                            $low_balance_content = get_post_meta($post->ID, '_partner_low_balance_body', true);
                            wp_editor($low_balance_content, 'partner_low_balance_body', ['textarea_name' => 'partner_low_balance_body', 'media_buttons' => false, 'textarea_rows' => 10]);
                            ?>
                            <p class="description">Shortcodes disponibles: <code>[nombre_cliente]</code>, <code>[saldo_actual]</code>, <code>[nombre_partner]</code>.</p>
                        </td>
                    </tr>

                    <tr style="border-top: 2px solid #999; background-color: #e0f2f1;">
                        <th colspan="2">
                            <h3 style="padding: 10px 0 0 0; margin: 0;">Configuración de Saldo de Regalo Automático</h3>
                            <p class="description">Define si se asignará un saldo de regalo a las licencias vendidas por este socio.</p>
                        </th>
                    </tr>
                    <tr style="background-color: #e0f2f1;">
                        <th><label for="kyc_partner_auto_credit_enabled">Activar Saldo de Regalo por Licencia</label></th>
                        <td>
                            <?php $auto_credit_enabled = get_post_meta($post->ID, '_partner_auto_credit_enabled', true); ?>
                            <input type="checkbox" id="kyc_partner_auto_credit_enabled" name="partner_auto_credit_enabled" value="yes" <?php checked($auto_credit_enabled, 'yes'); ?>>
                            <p class="description">Si está marcado, se transferirá el monto de abajo desde el Saldo Maestro del socio a cada nuevo cliente aprobado.</p>
                        </td>
                    </tr>
                    <tr style="background-color: #e0f2f1;">
                        <th><label for="kyc_partner_auto_credit_amount">Monto a Acreditar (USD)</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" id="kyc_partner_auto_credit_amount" name="partner_auto_credit_amount" value="<?php echo esc_attr(get_post_meta($post->ID, '_partner_auto_credit_amount', true)); ?>" placeholder="Ej: 10.00">
                            <p class="description">El monto exacto que se regalará. Solo se aplicará si la opción de arriba está activada.</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <hr>
        <h3 style="padding-left: 0;">IDs de Workflow Asignados</h3>
        <p class="description">Asigna el ID de Workflow de Didit correspondiente para cada plan de servicio que este cliente tenga disponible.</p>
        <table class="form-table">
            <tbody>
            <?php
            $plans_query_wf = new WP_Query(['post_type' => 'plan_kyc', 'posts_per_page' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']);
            if ($plans_query_wf->have_posts()) :
                while ($plans_query_wf->have_posts()) : $plans_query_wf->the_post();
                    $plan_id = get_the_ID();
                    $plan_slug = get_post_meta($plan_id, '_plan_slug', true);
                    if (empty($plan_slug)) continue;
                    $field_name = 'kyc_wf_' . $plan_slug;
                    $meta_key = '_wf_' . $plan_slug;
                    $workflow_id = get_post_meta($post->ID, $meta_key, true);
                    ?>
                    <tr>
                        <th><label for="<?php echo esc_attr($field_name); ?>">ID Workflow <?php the_title(); ?></label></th>
                        <td><input type="text" id="<?php echo esc_attr($field_name); ?>" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($workflow_id); ?>" class="regular-text"></td>
                    </tr>
                    <?php
                endwhile;
                wp_reset_postdata();
            else:
                ?>
                <tr><td colspan="2">No se han encontrado planes. Por favor, créalos primero en el panel de "Planes de Servicio".</td></tr>
                <?php
            endif;
            ?>
            </tbody>
        </table>
        
        <script>
        jQuery(document).ready(function($){
            var mediaUploader;
            $('.gkyc-admin-upload-button').on('click', function(e) {
                e.preventDefault();
                var inputId = $(this).data('input-id');
                mediaUploader = wp.media({
                    title: 'Seleccionar Imagen', button: { text: 'Usar esta Imagen' }, multiple: false
                }).on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + inputId).val(attachment.url);
                }).open();
            });
        });
        </script>
        <?php
    }

    // --- REEMPLAZA ESTA FUNCIÓN COMPLETA ---
    public function save_meta_data($post_id) {
        if (!isset($_POST['kyc_client_data_nonce']) || !wp_verify_nonce($_POST['kyc_client_data_nonce'], 'kyc_client_data_nonce_action')) { return; }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (get_post_type($post_id) !== 'cliente_kyc') { return; }

        $old_status = get_post_meta($post_id, '_client_status', true);
        $new_status = isset($_POST['kyc_client_status']) ? sanitize_text_field($_POST['kyc_client_status']) : '';

        // Guardado del campo de usuario asociado
        if (isset($_POST['kyc_associated_user'])) {
            $user_id = absint($_POST['kyc_associated_user']);
            if ($user_id > 0) {
                update_post_meta($post_id, '_user_id', $user_id);
            } else {
                delete_post_meta($post_id, '_user_id');
            }
        }
        
        // Validación de duplicados (se mantiene igual)
        if (isset($_POST['kyc_api_key_didit']) && !empty($_POST['kyc_api_key_didit'])) {
            $didit_key = sanitize_text_field($_POST['kyc_api_key_didit']);
            $query = new WP_Query(['post_type' => 'cliente_kyc', 'post__not_in' => [$post_id], 'meta_query' => [['key' => '_api_key_didit', 'value' => $didit_key]]]);
            if ($query->have_posts()) {
                $duplicate_post = $query->posts[0];
                $duplicate_name = $duplicate_post->post_title;
                $duplicate_email = get_post_meta($duplicate_post->ID, '_email', true);
                $owner_id = get_post_meta($duplicate_post->ID, '_reseller_owner_id', true);
                $client_type_info = !empty($owner_id) ? ' (Sub-cliente de ' . esc_html(get_the_title($owner_id)) . ')' : ' (Cliente Directo)';
                wp_reset_postdata();
                $error_message = sprintf('<strong>Error:</strong> La API Key de Didit ya está en uso por otro cliente: <strong>%s (%s)</strong>%s. No se guardaron los cambios.', esc_html($duplicate_name), esc_html($duplicate_email), $client_type_info);
                set_transient('gkyc_admin_notice', ['type' => 'error', 'message' => $error_message], 30);
                return;
            }
        }
        
        $fixed_fields = [
            'kyc_email' => '_email', 'kyc_api_key_mothership' => '_api_key_mothership', 'kyc_api_key_didit' => '_api_key_didit', 
            'kyc_balance' => '_balance', 'kyc_phone' => '_phone_number', 'kyc_license_limit' => '_license_limit',
            'kyc_client_status' => '_client_status', 'kyc_partner_paypal_link' => '_partner_paypal_link', 
            'kyc_partner_support_whatsapp' => '_partner_support_whatsapp',
            'kyc_partner_support_email' => '_partner_support_email', // <-- AÑADE ESTA LÍNEA
        ];
        
        foreach ($fixed_fields as $form_field => $meta_key) {
            if (isset($_POST[$form_field])) {
                if (strpos($form_field, '_link') !== false || strpos($form_field, '_url') !== false) {
                    update_post_meta($post_id, $meta_key, esc_url_raw($_POST[$form_field]));
                } else {
                    update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_field]));
                }
            }
        }

        if (isset($_POST['kyc_reseller_owner'])) {
            $owner_id = sanitize_text_field($_POST['kyc_reseller_owner']);
            if (!empty($owner_id)) {
                update_post_meta($post_id, '_reseller_owner_id', $owner_id);
            } else {
                delete_post_meta($post_id, '_reseller_owner_id');
            }
        }

        if (isset($_POST['partner_license_prices']) && is_array($_POST['partner_license_prices'])) {
            foreach ($_POST['partner_license_prices'] as $meta_key => $price) {
                update_post_meta($post_id, sanitize_key($meta_key), sanitize_text_field($price));
            }
        }

        if (isset($_POST['partner_verification_prices']) && is_array($_POST['partner_verification_prices'])) {
            foreach ($_POST['partner_verification_prices'] as $meta_key => $price) {
                update_post_meta($post_id, sanitize_key($meta_key), sanitize_text_field($price));
            }
        }

        if ( isset( $_POST['gkyc_webhook_secret'] ) ) {
                update_post_meta( $post_id, '_webhook_secret_key', sanitize_text_field( $_POST['gkyc_webhook_secret'] ) );
        }

        // Guardar campos de texto simple
        $whitelabel_text_fields = [
            'kyc_partner_from_name' => '_partner_from_name',
            'kyc_partner_welcome_subject' => '_partner_welcome_subject',
            'kyc_partner_low_balance_subject' => '_partner_low_balance_subject',
        ];
        foreach ($whitelabel_text_fields as $form_field => $meta_key) {
            if (isset($_POST[$form_field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_field]));
            }
        }

        // Guardar campo de email
        if (isset($_POST['kyc_partner_reply_to_email'])) {
            update_post_meta($post_id, '_partner_reply_to_email', sanitize_email($_POST['kyc_partner_reply_to_email']));
        }

        // Guardar campos del editor de WordPress (permitiendo HTML seguro)
        if (isset($_POST['partner_welcome_body'])) {
            update_post_meta($post_id, '_partner_welcome_body', wp_kses_post($_POST['partner_welcome_body']));
        }
        if (isset($_POST['partner_low_balance_body'])) {
            update_post_meta($post_id, '_partner_low_balance_body', wp_kses_post($_POST['partner_low_balance_body']));
        }

        // ===== LÓGICA DE GUARDADO COMPLETA PARA BRANDING Y PAGOS =====
        if (isset($_POST['kyc_partner_website_url'])) { update_post_meta($post_id, '_partner_website_url', esc_url_raw($_POST['kyc_partner_website_url'])); }
        if (isset($_POST['kyc_partner_recharge_url'])) { update_post_meta($post_id, '_partner_recharge_url', esc_url_raw($_POST['kyc_partner_recharge_url'])); }
        if (isset($_POST['kyc_partner_logo_1x1_url'])) { update_post_meta($post_id, '_partner_logo_1x1_url', esc_url_raw($_POST['kyc_partner_logo_1x1_url'])); }
        if (isset($_POST['kyc_partner_logo_2x1_url'])) { update_post_meta($post_id, '_partner_logo_2x1_url', esc_url_raw($_POST['kyc_partner_logo_2x1_url'])); }
        if (isset($_POST['kyc_partner_favicon_url'])) { update_post_meta($post_id, '_partner_favicon_url', esc_url_raw($_POST['kyc_partner_favicon_url'])); }
        if (isset($_POST['kyc_partner_paypal_link'])) { update_post_meta($post_id, '_partner_paypal_link', esc_url_raw($_POST['kyc_partner_paypal_link'])); }
        if (isset($_POST['kyc_partner_stripe_link'])) { update_post_meta($post_id, '_partner_stripe_link', esc_url_raw($_POST['kyc_partner_stripe_link'])); }
        if (isset($_POST['kyc_partner_usdt_wallet'])) { update_post_meta($post_id, '_partner_usdt_wallet', sanitize_text_field($_POST['kyc_partner_usdt_wallet'])); }
        if (isset($_POST['kyc_partner_other_payments'])) { update_post_meta($post_id, '_partner_other_payments', wp_kses_post($_POST['kyc_partner_other_payments'])); }

        // --- Guardado de Correos ---
        $whitelabel_text_fields = [
            'kyc_partner_from_name' => '_partner_from_name',
            'kyc_partner_welcome_subject' => '_partner_welcome_subject',
            'kyc_partner_low_balance_subject' => '_partner_low_balance_subject',
        ];
        foreach ($whitelabel_text_fields as $form_field => $meta_key) {
            if (isset($_POST[$form_field])) { update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$form_field])); }
        }
        if (isset($_POST['kyc_partner_reply_to_email'])) { update_post_meta($post_id, '_partner_reply_to_email', sanitize_email($_POST['kyc_partner_reply_to_email'])); }
        if (isset($_POST['partner_welcome_body'])) { update_post_meta($post_id, '_partner_welcome_body', wp_kses_post($_POST['partner_welcome_body'])); }
        if (isset($_POST['partner_low_balance_body'])) { update_post_meta($post_id, '_partner_low_balance_body', wp_kses_post($_POST['partner_low_balance_body'])); }

        // --- INICIO: CÓDIGO DE NOTIFICACIÓN DE ACTIVACIÓN ---

        // --- INICIO DE LA LÓGICA DE NOTIFICACIÓN CORREGIDA ---
    if ( $new_status === 'active' && $old_status === 'pending' ) {
        $client_email = get_post_meta($post_id, '_email', true);
        
        // Correo al cliente final
        if (is_email($client_email)) {
            // La función centralizada se encarga de todo, solo necesitamos llamarla.
            gkyc_send_whitelabel_email($client_email, '¡Tu licencia ha sido activada!', '¡Buenas noticias! Tu cuenta y tu plan han sido completamente activados y están listos para usarse.', $post_id);
        }

        // Notificación al socio (con lógica de fallback para el email)
        if ($partner_id) {
            $partner_email = get_post_meta($partner_id, '_partner_support_email', true);
            if (!is_email($partner_email)) {
                $partner_email = get_post_meta($partner_id, '_email', true); // Plan B: usar el email principal del socio
            }

            if(is_email($partner_email)){
                $client_name = strtok(get_the_title($post_id), ' (');
                $partner_name = strtok(get_the_title($partner_id), ' (');
                $subject_partner = "¡Licencia Activada! Tu cliente " . $client_name . " está listo.";
                $body_partner = "<html><body><p>Hola ".esc_html($partner_name).",</p><p>Te notificamos que la licencia para tu cliente <strong>".esc_html($client_name)." (".esc_html($client_email).")</strong> ha sido configurada y activada. Ya ha sido notificado.</p></body></html>";
                $headers_partner = ['Content-Type: text/html; charset=UTF-8', 'From: Guardián KYC <'.get_option('admin_email').'>'];
                wp_mail( $partner_email, $subject_partner, $body_partner, $headers_partner );
            }
        }
    }
    // --- FIN DE LA LÓGICA DE NOTIFICACIÓN CORREGIDA ---

        // --- FIN: CÓDIGO DE NOTIFICACIÓN DE ACTIVACIÓN ---

        // Sincronización automática (se mantiene igual)
        // --- Guardado de la Configuración de Saldo Automático ---
        // --- Guardado de la Configuración de Saldo Automático ---
        if (isset($_POST['partner_auto_credit_enabled'])) {
            update_post_meta($post_id, '_partner_auto_credit_enabled', 'yes');
        } else {
            update_post_meta($post_id, '_partner_auto_credit_enabled', 'no');
        }

        if (isset($_POST['partner_auto_credit_amount'])) {
            $amount = filter_var($_POST['partner_auto_credit_amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            update_post_meta($post_id, '_partner_auto_credit_amount', $amount);
        }
        $client_site_url = get_post_meta($post_id, '_activated_domain', true);
        $mothership_api_key = get_post_meta($post_id, '_api_key_mothership', true);

        if (!empty($client_site_url) && !empty($mothership_api_key)) {
            $sync_url = rtrim($client_site_url, '/') . '/wp-json/guardian-kyc/v1/force-sync';
            
            wp_remote_post($sync_url, [
                'method'    => 'POST',
                'timeout'   => 15,
                'blocking'  => false,
                'headers'   => ['Content-Type' => 'application/json'],
                'body'      => json_encode(['mothership_api_key' => $mothership_api_key])
            ]);
        }
    }
}
