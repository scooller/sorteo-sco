<?php

/**
 * Custom Product Type - Versión simplificada y funcional
 * https://www.businessbloomer.com/woocommerce-how-to-create-a-new-product-type/
 */

if (!defined('ABSPATH')) exit;

// Log stock reduction/restoration events for sco_package components
// Debe estar antes de cualquier uso
function sco_package_log_event($action, $order_id, $item_id, $product_id, $qty, $order)
{
    $logs = get_option('sorteo_sco_stock_logs', array());
    if (!is_array($logs)) $logs = array();
    $product = wc_get_product($product_id);
    $product_name = $product ? $product->get_name() : '';
    $order_number = $order ? $order->get_order_number() : $order_id;
    $timestamp = current_time('mysql');
    $logs[] = array(
        'time' => $timestamp,
        'action' => $action,
        'order_id' => $order_id,
        'order_number' => $order_number,
        'item_id' => $item_id,
        'product_id' => $product_id,
        'product_name' => $product_name,
        'qty' => $qty,
    );
    // Keep only last 100 logs
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }
    update_option('sorteo_sco_stock_logs', $logs);
}

// 1. Add custom product type to dropdown
add_filter('product_type_selector', 'sco_package_add_product_type');
function sco_package_add_product_type($types)
{
    $types['sco_package'] = __('Paquete (Sorteo)', 'sorteo-sco');
    return $types;
}

// 2. Add custom product type class mapping
add_filter('woocommerce_product_class', 'sco_package_woocommerce_product_class', 10, 2);
function sco_package_woocommerce_product_class($classname, $product_type)
{
    if ($product_type === 'sco_package') {
        $classname = 'WC_Product_Sco_package';
    }
    return $classname;
}

// 3. Create custom product type class AFTER WooCommerce is loaded
add_action('woocommerce_loaded', 'sco_package_create_custom_product_class');
function sco_package_create_custom_product_class()
{
    if (!class_exists('WC_Product')) {
        return;
    }

    class WC_Product_Sco_package extends WC_Product
    {
        protected $product_type = 'sco_package';

        public function __construct($product = 0)
        {
            $this->product_type = 'sco_package';
            parent::__construct($product);
        }

        public function get_type()
        {
            return 'sco_package';
        }

        public function is_purchasable()
        {
            return true;
        }

        public function is_virtual()
        {
            return true;
        }

        public function supports($feature)
        {
            return $feature === 'ajax_add_to_cart' ? true : parent::supports($feature);
        }
    }
}

// Force "Add to cart" button text in product loop for sco_package
add_filter('woocommerce_product_add_to_cart_text', 'sco_package_add_to_cart_text', 10, 2);
function sco_package_add_to_cart_text($text, $product)
{
    if ($product && $product->get_type() === 'sco_package') {
        if ($product->is_purchasable() && $product->is_in_stock()) {
            return __('Agregar al carrito', 'sorteo-sco');
        }
    }
    return $text;
}

// Custom add to cart button with quantity dropdown for sco_package in loop
add_filter('woocommerce_loop_add_to_cart_link', 'sco_package_custom_loop_add_to_cart', 20, 3);
function sco_package_custom_loop_add_to_cart($html, $product, $args)
{
    if ($product && $product->get_type() === 'sco_package' && $product->is_purchasable() && $product->is_in_stock()) {
        $product_id = $product->get_id();
        $add_url = $product->add_to_cart_url();

        // Usar helper de compatibilidad de tema
        return Sorteo_Theme_Compat::render_quantity_selector($product_id, $add_url, 10);
    }
    return $html;
}

// 4. Show/hide tabs and fields with JavaScript
add_action('admin_footer', 'sco_package_custom_product_type_js');
function sco_package_custom_product_type_js()
{
    if ('product' != get_post_type()) return;
?>
    <script type='text/javascript'>
        jQuery(document).ready(function($) {
            function toggleScoPackageFields() {
                var type = $('select#product-type').val();
                var isSco = type === 'sco_package';

                if (isSco) {
                    // Show simple product fields (for pricing)
                    $('.show_if_simple').show();
                    $('.show_if_external').hide();
                    $('.show_if_grouped').hide();
                    $('.show_if_variable').hide();
                    $('#general_product_data .pricing').addClass('show_if_sco_package').show();

                    // Show inventory tab
                    $('.inventory_options').addClass('show_if_sco_package').show();

                    // Hide unnecessary tabs for package products
                    $('.attribute_tab').hide(); // Atributos
                    $('.linked_product_tab').hide(); // Productos vinculados
                } else {
                    // Restore tabs for other product types
                    $('.attribute_tab').show();
                    $('.linked_product_tab').show();
                }

                if (isSco) {
                    var mode = $('#_sco_pkg_mode').val();
                    $('.sco_pkg_random_only').toggle(mode === 'random');
                    $('.sco_pkg_manual_only').toggle(mode === 'manual');
                } else {
                    $('.sco_pkg_random_only, .sco_pkg_manual_only').hide();
                }
            }

            $('select#product-type').on('change', toggleScoPackageFields);
            $(document).on('change', '#_sco_pkg_mode', toggleScoPackageFields);
            toggleScoPackageFields();
        });
    </script>
<?php
}

// 5. Save product type correctly
add_action('woocommerce_admin_process_product_object', 'sco_package_save_product_type_on_admin', 999);
function sco_package_save_product_type_on_admin($product)
{
    if (isset($_POST['product-type']) && $_POST['product-type'] === 'sco_package') {
        wp_set_object_terms($product->get_id(), 'sco_package', 'product_type');
    }
}

// 6. Add custom product data tab
add_filter('woocommerce_product_data_tabs', 'sco_package_add_product_data_tab');
function sco_package_add_product_data_tab($tabs)
{
    $tabs['sco_package_tab'] = array(
        'label'  => __('Paquete Sorteo', 'sorteo-sco'),
        'target' => 'sco_package_product_data',
        'class'  => array('show_if_sco_package'),
    );
    return $tabs;
}

// 7. Render custom product data panel
add_action('woocommerce_product_data_panels', 'sco_package_render_product_data_panel');
function sco_package_render_product_data_panel()
{
    global $post;
    $product_id = $post->ID;

    $mode = get_post_meta($product_id, '_sco_pkg_mode', true);
    $count = max(1, intval(get_post_meta($product_id, '_sco_pkg_count', true)));
    $selected_products = get_post_meta($product_id, '_sco_pkg_products', true);
    $selected_cats = get_post_meta($product_id, '_sco_pkg_categories', true);
    $allow_oos = get_post_meta($product_id, '_sco_pkg_allow_oos', true) === 'yes' ? 'yes' : 'no';

    echo '<div id="sco_package_product_data" class="panel woocommerce_options_panel hidden">';
    echo '<div class="options_group">';

    // Mode selector
    woocommerce_wp_select(array(
        'id' => '_sco_pkg_mode',
        'label' => __('Modo de selección', 'sorteo-sco'),
        'options' => array(
            'random' => __('Sorpresa (aleatorio por categoría)', 'sorteo-sco'),
            'manual' => __('Manual (productos fijos)', 'sorteo-sco'),
        ),
        'value' => $mode ? $mode : 'random',
    ));

    // Product count
    woocommerce_wp_text_input(array(
        'id' => '_sco_pkg_count',
        'label' => __('Productos por paquete', 'sorteo-sco'),
        'type' => 'number',
        'custom_attributes' => array('min' => '1', 'step' => '1'),
        'value' => $count,
        'description' => __('Cantidad total de productos que incluirá cada paquete.', 'sorteo-sco'),
    ));

    // Category selector (for random mode)
    $terms = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
    echo '<p class="form-field _sco_pkg_categories_field sco_pkg_random_only">';
    echo '<label for="_sco_pkg_categories">' . esc_html__('Categorías fuente', 'sorteo-sco') . '</label>';
    echo '<select id="_sco_pkg_categories" name="_sco_pkg_categories[]" class="wc-enhanced-select" multiple="multiple" data-placeholder="' . esc_attr__('Seleccionar categorías', 'sorteo-sco') . '">';
    $selected_array = $selected_cats ? explode(',', $selected_cats) : array();
    foreach ($terms as $term) {
        $selected = in_array($term->term_id, $selected_array) ? 'selected' : '';
        echo '<option value="' . esc_attr($term->term_id) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
    }
    echo '</select>';
    echo '</p>';

    // Product selector (for manual mode)
    echo '<p class="form-field _sco_pkg_products_field sco_pkg_manual_only">';
    echo '<label for="_sco_pkg_products">' . esc_html__('Productos fijos', 'sorteo-sco') . '</label>';
    echo '<select id="_sco_pkg_products" name="_sco_pkg_products[]" class="wc-product-search" multiple="multiple" data-placeholder="' . esc_attr__('Buscar productos', 'sorteo-sco') . '" data-action="woocommerce_json_search_products_and_variations">';
    if ($selected_products) {
        $product_ids = explode(',', $selected_products);
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                echo '<option value="' . esc_attr($product_id) . '" selected>' . esc_html($product->get_name()) . '</option>';
            }
        }
    }
    echo '</select>';
    echo '</p>';

    // Allow out-of-stock
    woocommerce_wp_checkbox(array(
        'id' => '_sco_pkg_allow_oos',
        'label' => __('Permitir productos sin stock', 'sorteo-sco'),
        'description' => __('Si está activo, se incluirán productos sin stock en el paquete.', 'sorteo-sco'),
        'value' => $allow_oos,
    ));

    // Show products in cart/checkout
    $show_products = get_post_meta($product_id, '_sco_pkg_show_products', true);
    if (!$show_products) {
        $show_products = 'yes';
    } // Default: mostrar productos

    woocommerce_wp_checkbox(array(
        'id' => '_sco_pkg_show_products',
        'label' => __('Mostrar productos en carrito', 'sorteo-sco'),
        'description' => __('Si está activo, se mostrará la lista de productos incluidos en el carrito y checkout.', 'sorteo-sco'),
        'value' => $show_products,
    ));

    echo '</div>';
    echo '</div>';
}

// 8. Save custom meta fields
add_action('woocommerce_admin_process_product_object', 'sco_package_save_product_meta');
function sco_package_save_product_meta($product)
{
    if ($product->get_type() !== 'sco_package') {
        return;
    }

    $product_id = $product->get_id();

    // Save mode
    if (isset($_POST['_sco_pkg_mode'])) {
        update_post_meta($product_id, '_sco_pkg_mode', sanitize_text_field($_POST['_sco_pkg_mode']));
    }

    // Save count
    if (isset($_POST['_sco_pkg_count'])) {
        update_post_meta($product_id, '_sco_pkg_count', absint($_POST['_sco_pkg_count']));
    }

    // Save categories (as CSV)
    if (isset($_POST['_sco_pkg_categories']) && is_array($_POST['_sco_pkg_categories'])) {
        $cat_ids = array_map('absint', $_POST['_sco_pkg_categories']);
        update_post_meta($product_id, '_sco_pkg_categories', implode(',', $cat_ids));
    } else {
        update_post_meta($product_id, '_sco_pkg_categories', '');
    }

    // Save products (as CSV)
    if (isset($_POST['_sco_pkg_products']) && is_array($_POST['_sco_pkg_products'])) {
        $prod_ids = array_map('absint', $_POST['_sco_pkg_products']);
        update_post_meta($product_id, '_sco_pkg_products', implode(',', $prod_ids));
    } else {
        update_post_meta($product_id, '_sco_pkg_products', '');
    }

    // Save allow out-of-stock
    update_post_meta($product_id, '_sco_pkg_allow_oos', isset($_POST['_sco_pkg_allow_oos']) ? 'yes' : 'no');

    // Save show products
    update_post_meta($product_id, '_sco_pkg_show_products', isset($_POST['_sco_pkg_show_products']) ? 'yes' : 'no');
}

// ============================================================================
// CART INTEGRATION
// ============================================================================

// Static storage for pending compositions
global $sco_package_pending_compositions;
$sco_package_pending_compositions = array();

// 9. Validate before adding to cart
add_filter('woocommerce_add_to_cart_validation', 'sco_package_validate_before_add_to_cart', 10, 3);
function sco_package_validate_before_add_to_cart($passed, $product_id, $quantity)
{
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'sco_package') {
        return $passed;
    }
    // Generar composición única global si se agrega más de 1 paquete
    $per_count = max(1, intval(get_post_meta($product_id, '_sco_pkg_count', true)) ?: 1);
    $need_total = max(1, (int)$quantity) > 1 ? ($per_count * max(1, (int)$quantity)) : null;
    $composition = sco_package_generate_composition($product_id, $need_total);
    if (is_wp_error($composition)) {
        // Aviso claro: no se puede ampliar el paquete por falta de productos
        $code = $composition->get_error_code();
        if (in_array($code, array('sco_pkg_not_enough', 'sco_pkg_insufficient'), true)) {
            wc_add_notice(__('No hay suficientes productos para agregar más a este paquete ahora. Reduce la cantidad o inténtalo más tarde.', 'sorteo-sco'), 'error');
        } else {
            wc_add_notice($composition->get_error_message(), 'error');
        }
        return false;
    }

    // Marcar cuando es composición "aplanada" (única global para toda la cantidad)
    if (!empty($need_total)) {
        $composition['meta']['flat'] = true;
        $composition['meta']['per_count'] = $per_count;
    }

    // Store composition for next step
    global $sco_package_pending_compositions;
    $sco_package_pending_compositions[$product_id] = $composition;

    // EARLY RESERVATION: reserve selected components for this session for the requested qty of packages
    if (isset($composition['components']) && is_array($composition['components'])) {
        $reserve_qty = (!empty($need_total)) ? 1 : max(1, (int)$quantity);
        sco_pkg_reserve_components_for_session($composition['components'], $reserve_qty);
    }

    // Aviso informativo: si hubo reemplazos por reservas en modo sorpresa
    $reserved_skipped = isset($composition['meta']['reserved_skipped']) ? (int) $composition['meta']['reserved_skipped'] : 0;
    $is_random = isset($composition['source']['type']) && $composition['source']['type'] === 'random';
    $show_replacements_msg = get_option('sorteo_sco_mostrar_mensaje_reemplazos', 'yes') === 'yes';

    if ($is_random && $reserved_skipped > 0 && $show_replacements_msg) {
        $custom_msg_template = get_option('sorteo_sco_mensaje_reemplazos', __('Nota: %d producto(s) estaban reservados por otros usuarios y se eligieron alternativas para completar tu paquete. Si deseas una nueva combinación al azar, elimina este paquete del carrito y vuelve a agregarlo.', 'sorteo-sco'));
        wc_add_notice(
            wp_kses_post(sprintf($custom_msg_template, $reserved_skipped)),
            'notice'
        );
    }

    return $passed;
}

// 10. Add composition data to cart item
add_filter('woocommerce_add_cart_item_data', 'sco_package_add_cart_item_data', 10, 3);
function sco_package_add_cart_item_data($cart_item_data, $product_id, $variation_id)
{
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'sco_package') {
        return $cart_item_data;
    }

    global $sco_package_pending_compositions;
    $composition = isset($sco_package_pending_compositions[$product_id])
        ? $sco_package_pending_compositions[$product_id]
        : sco_package_generate_composition($product_id);

    if (is_wp_error($composition)) {
        wc_add_notice($composition->get_error_message(), 'error');
        return $cart_item_data;
    }

    $cart_item_data['sco_package'] = array(
        'components' => $composition['components'],
        'mode' => $composition['mode'],
        'count' => $composition['count'],
        'source' => $composition['source'],
        'meta' => isset($composition['meta']) ? $composition['meta'] : array(),
        'uid' => uniqid('sco_pkg_', true),
    );

    // Make each package unique so they don't combine
    $cart_item_data['unique_key'] = md5(maybe_serialize($cart_item_data['sco_package']));

    // Clean up
    unset($sco_package_pending_compositions[$product_id]);

    return $cart_item_data;
}

// 11. Display composition in cart
add_filter('woocommerce_get_item_data', 'sco_package_display_cart_item_data', 10, 2);
function sco_package_display_cart_item_data($item_data, $cart_item)
{
    if (!isset($cart_item['sco_package'])) {
        return $item_data;
    }

    $pkg = $cart_item['sco_package'];
    $product_id = $cart_item['product_id'];

    // Check if we should show products
    $show_products = get_post_meta($product_id, '_sco_pkg_show_products', true);
    if (!$show_products) {
        $show_products = 'yes';
    } // Default: mostrar

    if ($show_products === 'yes') {
        $names = array();

        // Quantity of packages in cart
        $cart_qty = isset($cart_item['quantity']) ? max(1, intval($cart_item['quantity'])) : 1;

        $is_flat = isset($pkg['meta']['flat']) && $pkg['meta']['flat'];
        $labels = array();
        $products_per_package = 0;
        foreach ($pkg['components'] as $comp) {
            $p = wc_get_product($comp['product_id']);
            if ($p) {
                if ($is_flat) {
                    // Lista completa de productos únicos
                    $labels[] = $p->get_name();
                } else {
                    // Mostrar multiplicador por cantidad de paquetes
                    $per_pkg_qty = isset($comp['qty']) ? max(1, intval($comp['qty'])) : 1;
                    $total_for_item = $per_pkg_qty * $cart_qty;
                    $labels[] = sprintf('%s × %d', $p->get_name(), $total_for_item);
                    $products_per_package += $per_pkg_qty;
                }
            }
        }

        if (!empty($labels)) {
            $total_products = $is_flat ? count($pkg['components']) : ($products_per_package * $cart_qty);

            // Texto plano de respaldo
            $plain_value = implode(', ', $labels) . ' (' . sprintf(__('total: %d', 'sorteo-sco'), $total_products) . ')';

            // Compactar en carrito/checkout cuando la lista es muy larga
            $display_html = '';
            if ($is_flat && count($labels) > 3) {
                $brief_arr = array_slice($labels, 0, 3);
                $brief_html = esc_html(implode(', ', $brief_arr));
                $rest_count = count($labels) - 3;
                $full_html = esc_html(implode(', ', $labels));
                $details = '<details class="d-inline sco-pkg-details"><summary class="d-inline link-primary">' . esc_html__('ver todos', 'sorteo-sco') . '</summary>' . $full_html . '</details>';
                $display_html = $brief_html . ', ' . sprintf(esc_html__('y %d más', 'sorteo-sco'), $rest_count) . ' ' . $details . ' (' . sprintf(esc_html__('total: %d', 'sorteo-sco'), $total_products) . ')';
            } else {
                $display_html = esc_html(implode(', ', $labels)) . ' (' . sprintf(esc_html__('total: %d', 'sorteo-sco'), $total_products) . ')';
            }

            $item_data[] = array(
                'key' => __('Productos incluidos', 'sorteo-sco'),
                'value' => wc_clean($plain_value),
                'display' => $display_html,
            );
        }

        $item_data[] = array(
            'key' => __('Modo', 'sorteo-sco'),
            'value' => $pkg['mode'] === 'manual' ? __('Manual', 'sorteo-sco') : __('Sorpresa', 'sorteo-sco'),
            'display' => '',
        );
    }

    return $item_data;
}

// 12. Save composition to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'sco_package_add_order_item_meta', 10, 4);
function sco_package_add_order_item_meta($item, $cart_item_key, $values, $order)
{
    if (!isset($values['sco_package'])) {
        return;
    }

    $pkg = $values['sco_package'];
    $item->add_meta_data('_sco_package', $pkg, true);

    // Add friendly display - soporta modo "flat" (únicos globales)
    $is_flat = isset($pkg['meta']['flat']) && $pkg['meta']['flat'];
    $labels = array();
    $qty_packages = max(1, intval($item->get_quantity()));
    $products_per_package = 0;

    foreach ($pkg['components'] as $comp) {
        $p = wc_get_product($comp['product_id']);
        if ($p) {
            if ($is_flat) {
                $labels[] = $p->get_name();
            } else {
                $per_pkg_qty = isset($comp['qty']) ? max(1, intval($comp['qty'])) : 1;
                $total_for_item = $per_pkg_qty * $qty_packages;
                $labels[] = sprintf('%s × %d', $p->get_name(), $total_for_item);
                $products_per_package += $per_pkg_qty;
            }
        }
    }

    if (!empty($labels)) {
        $total_products = $is_flat ? count($pkg['components']) : ($products_per_package * $qty_packages);

        $item->add_meta_data(
            __('Productos incluidos', 'sorteo-sco'),
            implode(', ', $labels) . ' (' . sprintf(__('total: %d', 'sorteo-sco'), $total_products) . ')',
            true
        );
    }
}

// 13. Reduce component stock when order is completed
add_action('woocommerce_order_status_changed', 'sco_package_reduce_components_stock', 10, 4);
function sco_package_reduce_components_stock($order_id, $old_status, $new_status, $order)
{
    $target_statuses = array('processing', 'completed');
    if (!in_array($new_status, $target_statuses, true)) {
        return;
    }

    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }

        if ($product->get_type() !== 'sco_package') {
            continue;
        }

        // Avoid double reduction
        $already = $item->get_meta('_sco_pkg_stock_reduced', true);
        if ($already === 'yes') {
            continue;
        }

        $pkg = $item->get_meta('_sco_package', true);
        if (empty($pkg) || empty($pkg['components'])) {
            continue;
        }

        $qty_packages = max(1, intval($item->get_quantity()));
        $is_flat = isset($pkg['meta']['flat']) && $pkg['meta']['flat'];
        $effective_packages = $is_flat ? 1 : $qty_packages;

        // Logging para debugging
        error_log(sprintf(
            'Sorteo SCO PACKAGE: Procesando paquete item_id=%d, qty_packages=%d, is_flat=%s, effective_packages=%d, components=%d',
            $item_id,
            $qty_packages,
            $is_flat ? 'yes' : 'no',
            $effective_packages,
            count($pkg['components'])
        ));

        foreach ($pkg['components'] as $comp) {
            $pid = intval($comp['product_id']);
            $per_pkg_qty = isset($comp['qty']) ? max(1, intval($comp['qty'])) : 1;
            $total_to_reduce = $per_pkg_qty * $effective_packages;
            $component_product = wc_get_product($pid);
            if ($component_product && $component_product->managing_stock()) {
                wc_update_product_stock($component_product, $total_to_reduce, 'decrease');
                sco_package_log_event('reduce', $order_id, $item_id, $pid, $total_to_reduce, $order);
            }

            // Grant download permissions for downloadable component products
            if ($component_product && $component_product->is_downloadable()) {
                $downloads = $component_product->get_downloads();

                error_log(sprintf(
                    'Sorteo SCO PACKAGE: Producto descargable pid=%d (%s), archivos=%d',
                    $pid,
                    $component_product->get_name(),
                    count($downloads)
                ));

                if (!empty($downloads)) {
                    $customer_email = $order->get_billing_email();
                    $customer_id = $order->get_customer_id();

                    foreach ($downloads as $download_id => $file) {
                        // Check if permission already exists
                        global $wpdb;
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT download_id FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions 
                            WHERE order_id = %d AND product_id = %d AND download_id = %s",
                            $order_id,
                            $pid,
                            $download_id
                        ));

                        if (!$existing) {
                            // Insert download permission
                            $result = $wpdb->insert(
                                $wpdb->prefix . 'woocommerce_downloadable_product_permissions',
                                array(
                                    'download_id'         => $download_id,
                                    'product_id'          => $pid,
                                    'order_id'            => $order_id,
                                    'order_key'           => $order->get_order_key(),
                                    'user_email'          => $customer_email,
                                    'user_id'             => $customer_id,
                                    'downloads_remaining' => '',
                                    'access_granted'      => current_time('mysql'),
                                    'access_expires'      => null,
                                    'download_count'      => 0,
                                ),
                                array('%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d')
                            );

                            if ($result) {
                                // permission created
                            } else {
                                // Log only real DB errors
                                if (! empty($wpdb->last_error) && function_exists('error_log')) {
                                    error_log(sprintf(
                                        'Sorteo SCO: ERROR DB al crear permiso - order_id=%d, product_id=%d, download_id=%s, error=%s',
                                        $order_id,
                                        $pid,
                                        $download_id,
                                        $wpdb->last_error
                                    ));
                                }
                            }
                        } else {
                            // permission already exists
                        }
                    }
                }
            }
        }

        // Mark as processed
        $item->add_meta_data('_sco_pkg_stock_reduced', 'yes', true);
        // Clear any previous restoration flag so future re-reductions are allowed if status flips back
        $item->update_meta_data('_sco_pkg_stock_restored', 'no');
        $item->save();
    }
}

// Restore component stock when order is cancelled or refunded (if option enabled)
add_action('woocommerce_order_status_changed', 'sco_package_restore_components_stock', 10, 4);
function sco_package_restore_components_stock($order_id, $old_status, $new_status, $order)
{
    // Option toggle (default yes)
    $enabled = get_option('sorteo_sco_restock_on_cancel', 'yes') === 'yes';
    if (!$enabled) {
        return;
    }

    $target_statuses = array('cancelled', 'refunded');
    if (!in_array($new_status, $target_statuses, true)) {
        return;
    }

    if (!$order instanceof WC_Order) {
        $order = wc_get_order($order_id);
    }
    if (!$order) {
        return;
    }

    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if (!$product) {
            continue;
        }
        if ($product->get_type() !== 'sco_package') {
            continue;
        }

        // Only restore if we previously reduced and haven't restored yet
        $was_reduced = $item->get_meta('_sco_pkg_stock_reduced', true) === 'yes';
        $already_restored = $item->get_meta('_sco_pkg_stock_restored', true) === 'yes';
        if (!$was_reduced || $already_restored) {
            continue;
        }

        $pkg = $item->get_meta('_sco_package', true);
        if (empty($pkg) || empty($pkg['components'])) {
            continue;
        }

        $qty_packages = max(1, intval($item->get_quantity()));
        $is_flat = isset($pkg['meta']['flat']) && $pkg['meta']['flat'];
        $effective_packages = $is_flat ? 1 : $qty_packages;
        foreach ($pkg['components'] as $comp) {
            $pid = intval($comp['product_id']);
            $per_pkg_qty = isset($comp['qty']) ? max(1, intval($comp['qty'])) : 1;
            $total_to_increase = $per_pkg_qty * $effective_packages;
            $component_product = wc_get_product($pid);
            if ($component_product && $component_product->managing_stock()) {
                wc_update_product_stock($component_product, $total_to_increase, 'increase');
                sco_package_log_event('restore', $order_id, $item_id, $pid, $total_to_increase, $order);
            }
        }

        // Mark as restored and allow future reductions if status flips back
        $item->update_meta_data('_sco_pkg_stock_restored', 'yes');
        $item->update_meta_data('_sco_pkg_stock_reduced', 'no');
        $item->save();
    }
}

// ============================================================================
// COMPOSITION GENERATION
// ============================================================================

/**
 * Generate product composition for a package
 * @param int $product_id
 * @param int|null $override_total Total de productos únicos necesarios (null = usar count del meta)
 * @return array|WP_Error
 */
function sco_package_generate_composition($product_id, $override_total = null)
{
    $mode = get_post_meta($product_id, '_sco_pkg_mode', true) ?: 'random';
    $count = max(1, intval(get_post_meta($product_id, '_sco_pkg_count', true)) ?: 1);
    $need_total = $override_total ? max(1, intval($override_total)) : $count;
    $allow_oos = get_post_meta($product_id, '_sco_pkg_allow_oos', true) === 'yes';

    $components = array();
    $source = array();
    $reserved_skipped = 0;

    if ($mode === 'manual') {
        // Manual mode: use fixed products
        $csv = (string) get_post_meta($product_id, '_sco_pkg_products', true);
        $ids = array_filter(array_map('intval', explode(',', $csv)));

        // ✅ FIX: Eliminar duplicados PRIMERO
        $ids = array_unique($ids);

        if (empty($ids)) {
            return new WP_Error('sco_pkg_empty', __('Este paquete no tiene productos definidos.', 'sorteo-sco'));
        }

        // ✅ FIX: Validar productos ANTES de limitar cantidad
        $valid_ids = array();
        foreach ($ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p || !$p->is_purchasable() || $p->is_type('variable')) {
                continue;
            }
            if (!$allow_oos && !$p->is_in_stock()) {
                continue;
            }
            if (sco_pkg_is_reserved_by_others_blocking($pid, 1)) {
                $reserved_skipped++;
                continue;
            }
            $valid_ids[] = $pid;
        }

        // ✅ FIX: Verificar que hay suficientes ANTES de slice
        if (count($valid_ids) < $need_total) {
            return new WP_Error(
                'sco_pkg_insufficient',
                sprintf(
                    __('No hay suficientes productos válidos. Se necesitan %d pero solo hay %d disponibles.', 'sorteo-sco'),
                    $need_total,
                    count($valid_ids)
                )
            );
        }

        $final_ids = array_slice($valid_ids, 0, $need_total);

        foreach ($final_ids as $pid) {
            $components[] = array('product_id' => $pid, 'qty' => 1);
        }

        $source = array('type' => 'manual', 'products' => $final_ids);
    } else {
        // Random mode: pick from categories
        $csv = (string) get_post_meta($product_id, '_sco_pkg_categories', true);
        $cat_ids = array_filter(array_map('intval', explode(',', $csv)));

        if (empty($cat_ids)) {
            return new WP_Error('sco_pkg_no_cat', __('No hay categorías seleccionadas para el paquete sorpresa.', 'sorteo-sco'));
        }

        $query_args = array(
            'post_type' => 'product',
            'posts_per_page' => 500,
            'fields' => 'ids',
            'post_status' => 'publish',
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $cat_ids,
                ),
            ),
        );

        $product_ids = get_posts($query_args);

        // ✅ FIX: Eliminar duplicados PRIMERO
        $product_ids = array_unique($product_ids);

        $eligible = array();

        foreach ($product_ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p || !$p->is_type('simple')) {
                continue;
            }
            if ($p->get_type() === 'sco_package') {
                continue;
            }
            if (!$p->is_purchasable()) {
                continue;
            }
            if (!$allow_oos && !$p->is_in_stock()) {
                continue;
            }
            if (sco_pkg_is_reserved_by_others_blocking($pid, 1)) {
                $reserved_skipped++;
                continue;
            }
            $eligible[] = $pid;
        }

        // ✅ FIX: Mensaje de error descriptivo
        if (count($eligible) < $need_total) {
            $cat_names = array();
            foreach ($cat_ids as $cid) {
                $term = get_term($cid, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $cat_names[] = $term->name;
                }
            }
            return new WP_Error(
                'sco_pkg_not_enough',
                sprintf(
                    __('No hay suficientes productos. Se necesitan %d de "%s", pero solo hay %d disponibles.', 'sorteo-sco'),
                    $need_total,
                    implode(', ', $cat_names),
                    count($eligible)
                )
            );
        }

        shuffle($eligible);
        $pick = array_slice($eligible, 0, $need_total);

        // ✅ FIX: Verificación final
        if (count($pick) !== count(array_unique($pick))) {
            error_log('SORTEO SCO WARNING: Duplicados detectados. Forzando unicidad.');
            $pick = array_values(array_unique($pick));
        }

        foreach ($pick as $pid) {
            $components[] = array('product_id' => $pid, 'qty' => 1);
        }

        $source = array('type' => 'random', 'categories' => $cat_ids);
    }

    // ✅ FIX: Logging
    error_log(sprintf(
        'SORTEO SCO: product_id=%d, mode=%s, need=%d, got=%d, skipped=%d',
        $product_id,
        $mode,
        $need_total,
        count($components),
        $reserved_skipped
    ));

    return array(
        'mode' => $mode,
        'count' => $count,
        'components' => $components,
        'source' => $source,
        'meta' => array(
            'reserved_skipped' => $reserved_skipped,
        ),
    );
}


// ============================================================================
// RESERVATIONS (sync with theme transient "bootstrap_theme_stock_reservations")
// ============================================================================

/**
 * Check if a product is reserved by other sessions in a way that blocks 'needed' units.
 */
function sco_pkg_is_reserved_by_others_blocking($product_id, $needed = 1)
{
    $p = wc_get_product($product_id);
    if (!$p || !$p->managing_stock()) {
        return false;
    }
    $stock = (int) $p->get_stock_quantity();
    $reserved_by_others = sco_pkg_get_reserved_by_others($product_id);
    $available = $stock - $reserved_by_others;
    return ($available < $needed);
}

function sco_pkg_get_reserved_by_others($product_id)
{
    $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
    $current_session = WC()->session ? WC()->session->get_customer_id() : '';
    $total = 0;
    foreach ($reservations as $session_id => $items) {
        if ($session_id === $current_session) continue;
        if (isset($items[$product_id])) {
            $total += (int) ($items[$product_id]['quantity'] ?? 0);
        }
    }
    return $total;
}

/**
 * Compute required reservations for this session based on package items in cart
 * and sync them into the theme transient used by stock control.
 */
function sco_pkg_sync_reservations_with_cart()
{
    if (!function_exists('WC') || !WC()->cart) {
        return;
    }
    $session_id = WC()->session ? WC()->session->get_customer_id() : '';
    if (!$session_id) return;

    $needed = array(); // product_id => quantity
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (!isset($cart_item['sco_package'])) continue;
        $pkg = $cart_item['sco_package'];
        $pkg_qty = max(1, (int) ($cart_item['quantity'] ?? 1));
        $is_flat = isset($pkg['meta']['flat']) && $pkg['meta']['flat'];
        foreach ($pkg['components'] as $comp) {
            $pid = (int) $comp['product_id'];
            $unit = max(1, (int) ($comp['qty'] ?? 1));
            $effective = $is_flat ? $unit : ($unit * $pkg_qty);
            $needed[$pid] = ($needed[$pid] ?? 0) + $effective;
        }
    }

    // Merge into existing reservations, preserving other products reserved by this session
    $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
    $existing_map = isset($reservations[$session_id]) ? $reservations[$session_id] : array();

    // Start from existing entries but remove previous package-sourced ones; keep others (e.g., non-package reservations)
    $session_map = array();
    foreach ($existing_map as $pid => $entry) {
        $source = isset($entry['source']) ? $entry['source'] : '';
        if ($source !== 'sco_package') {
            $session_map[$pid] = $entry; // preserve
        }
    }

    // Insert current package needs with source marker
    $now = time();
    foreach ($needed as $pid => $qty) {
        $session_map[$pid] = array('quantity' => (int) $qty, 'timestamp' => $now, 'source' => 'sco_package');
    }

    $reservations[$session_id] = $session_map;
    set_transient('bootstrap_theme_stock_reservations', $reservations, 30 * MINUTE_IN_SECONDS);
}

// Hook sync on cart changes
add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $product = wc_get_product($product_id);
    if ($product && $product->get_type() === 'sco_package') {
        sco_pkg_sync_reservations_with_cart();
    }
}, 20, 6);

add_action('woocommerce_cart_item_removed', function ($cart_item_key) {
    sco_pkg_sync_reservations_with_cart();
}, 10, 1);

add_action('woocommerce_before_calculate_totals', function ($cart) {
    sco_pkg_sync_reservations_with_cart();
}, 10, 1);

// Re-sincronizar cuando cambian cantidades
add_action('woocommerce_after_cart_item_quantity_update', function ($cart_item_key, $quantity, $old_quantity, $cart) {
    // Ajustar composición cuando cambia la cantidad en el carrito (soporte modo "flat")
    sco_pkg_maybe_regenerate_flat_on_qty_change($cart, $cart_item_key, $quantity, $old_quantity);
    // Luego sincronizar reservas
    sco_pkg_sync_reservations_with_cart();
}, 10, 4);

/**
 * Si el usuario cambia la cantidad de paquetes desde el carrito, regenerar la
 * composición para que el total de productos únicos sea per_count × quantity
 * cuando quantity > 1 (modo "flat"). Si baja a 1, reducir la lista a per_count.
 */
function sco_pkg_maybe_regenerate_flat_on_qty_change($cart, $cart_item_key, $quantity, $old_quantity)
{
    if (!$cart || !isset($cart->cart_contents[$cart_item_key])) return;
    $ci = &$cart->cart_contents[$cart_item_key];
    if (!isset($ci['sco_package'])) return;
    $product_id = isset($ci['product_id']) ? (int)$ci['product_id'] : 0;
    if ($product_id <= 0) return;

    // per_count del producto (con fallback)
    if (isset($ci['sco_package']['meta']['per_count']) && (int)$ci['sco_package']['meta']['per_count'] > 0) {
        $per_count = (int)$ci['sco_package']['meta']['per_count'];
    } else {
        $meta_count = (int) get_post_meta($product_id, '_sco_pkg_count', true);
        $per_count = $meta_count > 0 ? $meta_count : max(1, (int) (isset($ci['sco_package']['components']) ? count($ci['sco_package']['components']) : 1));
        $ci['sco_package']['meta']['per_count'] = $per_count;
    }

    $q = max(1, (int)$quantity);
    $need_total = $per_count * $q;
    $current_count = isset($ci['sco_package']['components']) ? count($ci['sco_package']['components']) : 0;
    $is_flat = !empty($ci['sco_package']['meta']['flat']);
    $should_be_flat = ($q > 1);

    // Si ya coincide el tamaño y el modo, no hacer nada
    if ($current_count === $need_total && $is_flat === $should_be_flat) {
        return;
    }

    // Generar nueva composición para el total requerido
    $composition = sco_package_generate_composition($product_id, $need_total);
    if (is_wp_error($composition)) {
        // Revertir cantidad si no se puede satisfacer
        if ($cart && method_exists($cart, 'set_quantity')) {
            $cart->set_quantity($cart_item_key, $old_quantity, true);
        }
        $code = $composition->get_error_code();
        if (in_array($code, array('sco_pkg_not_enough', 'sco_pkg_insufficient'), true)) {
            wc_add_notice(__('No quedan productos disponibles para agregar al paquete actual. Disminuye la cantidad o vuelve más tarde.', 'sorteo-sco'), 'error');
        } else {
            wc_add_notice($composition->get_error_message(), 'error');
        }
        return;
    }

    // Marcar/ajustar modo flat según la cantidad actual
    if ($should_be_flat) {
        $composition['meta']['flat'] = true;
        $composition['meta']['per_count'] = $per_count;
    } else {
        if (isset($composition['meta']['flat'])) {
            unset($composition['meta']['flat']);
        }
        $composition['meta']['per_count'] = $per_count;
    }

    // Actualizar el ítem del carrito con la nueva composición
    $ci['sco_package'] = array(
        'components' => $composition['components'],
        'mode'       => $composition['mode'],
        'count'      => $composition['count'],
        'source'     => $composition['source'],
        'meta'       => isset($composition['meta']) ? $composition['meta'] : array(),
        'uid'        => isset($ci['sco_package']['uid']) ? $ci['sco_package']['uid'] : uniqid('sco_pkg_', true),
    );
}

// Limpiar reservas de la sesión cuando el carrito se vacía
add_action('woocommerce_cart_emptied', function () {
    sco_pkg_clear_session_reservations();
});

// También limpiar al finalizar pedido (redundante con tema, seguro si el tema cambia)
add_action('woocommerce_thankyou', function () {
    sco_pkg_clear_session_reservations();
}, 20);

/**
 * Remove all reservations for current session from the shared transient
 */
function sco_pkg_clear_session_reservations()
{
    $session_id = WC()->session ? WC()->session->get_customer_id() : '';
    if (!$session_id) return;
    $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
    if (isset($reservations[$session_id])) {
        unset($reservations[$session_id]);
        set_transient('bootstrap_theme_stock_reservations', $reservations, 30 * MINUTE_IN_SECONDS);
    }
}

/**
 * Add or increase reservations for current session for given components
 */
function sco_pkg_reserve_components_for_session($components, $packages_qty = 1)
{
    $session_id = WC()->session ? WC()->session->get_customer_id() : '';
    if (!$session_id) return;
    $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
    $session_map = isset($reservations[$session_id]) ? $reservations[$session_id] : array();
    $now = time();
    foreach ($components as $comp) {
        $pid = (int) ($comp['product_id'] ?? 0);
        if ($pid <= 0) continue;
        $qty = max(1, (int) ($comp['qty'] ?? 1)) * max(1, (int) $packages_qty);
        $existing = isset($session_map[$pid]['quantity']) ? (int) $session_map[$pid]['quantity'] : 0;
        $session_map[$pid] = array('quantity' => $existing + $qty, 'timestamp' => $now, 'source' => 'sco_package');
    }
    $reservations[$session_id] = $session_map;
    set_transient('bootstrap_theme_stock_reservations', $reservations, 30 * MINUTE_IN_SECONDS);
}

// ============================================================================
// EMAILS: Adjuntar descargas en correos de cliente para pedidos con sco_package
// ============================================================================

/**
 * Muestra la sección de descargas solo en el correo de "Pedido completado"
 * cuando el pedido contiene un producto sco_package y ya existen permisos de descarga
 * (que nosotros generamos al cambiar a processing/completed).
 */
add_action('woocommerce_email_after_order_table', 'sco_pkg_email_append_downloads', 9, 4);
function sco_pkg_email_append_downloads($order, $sent_to_admin, $plain_text, $email)
{
    if ($sent_to_admin) {
        return;
    }

    // Solo en correo de completado para evitar casos de ventas anónimas sin cuenta
    $allowed_emails = array('customer_completed_order');
    $email_id = is_object($email) && isset($email->id) ? $email->id : '';
    if (!in_array($email_id, $allowed_emails, true)) {
        return;
    }

    if (!$order instanceof WC_Order) {
        return;
    }

    // Verificar que el pedido tenga al menos un item de tipo sco_package
    $has_pkg = false;
    foreach ($order->get_items() as $it) {
        $p = $it->get_product();
        if ($p && $p->get_type() === 'sco_package') {
            $has_pkg = true;
            break;
        }
    }
    if (!$has_pkg) {
        return;
    }

    // Obtener descargas disponibles para este pedido (usa permisos existentes)
    $downloads = method_exists($order, 'get_downloadable_items') ? $order->get_downloadable_items() : array();

    error_log(sprintf(
        'Sorteo SCO PACKAGE EMAIL: Pedido #%d, get_downloadable_items() retornó %d items',
        $order->get_id(),
        count($downloads)
    ));

    if (empty($downloads)) {
        // Fallback: leer permisos directamente de la BD y construir URLs
        global $wpdb;
        $perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT product_id, download_id, order_key, user_email FROM {$perm_table} WHERE order_id = %d", $order->get_id()), ARRAY_A);

        error_log(sprintf(
            'Sorteo SCO PACKAGE EMAIL: Query BD retornó %d permisos para pedido #%d',
            count($rows),
            $order->get_id()
        ));

        $downloads = array();
        if (!empty($rows)) {
            foreach ($rows as $r) {
                $pid = (int) $r['product_id'];
                $download_id = $r['download_id'];
                $order_key = $r['order_key'];
                $user_email = $r['user_email'];

                $prod = wc_get_product($pid);
                $name = $prod ? $prod->get_name() : __('Descarga', 'sorteo-sco');
                if ($prod) {
                    $files = $prod->get_downloads();
                    if (isset($files[$download_id])) {
                        $name = $files[$download_id]->get_name();
                    }
                }

                // Construir URL estándar de WooCommerce para descargas
                $base = home_url('/');
                $url  = add_query_arg(
                    array(
                        'download_file' => $pid,
                        'order'         => $order_key,
                        'email'         => rawurlencode($user_email),
                        'key'           => $download_id,
                    ),
                    $base
                );

                $downloads[] = array(
                    'download_url'  => $url,
                    'download_name' => $name,
                );
            }
        }
        if (empty($downloads)) {
            error_log(sprintf('Sorteo SCO PACKAGE EMAIL: No se encontraron permisos en BD para pedido #%d', $order->get_id()));
            return;
        }
    }

    error_log(sprintf(
        'Sorteo SCO PACKAGE EMAIL: Total de %d descargas para mostrar en email pedido #%d',
        count($downloads),
        $order->get_id()
    ));

    if ($plain_text) {
        echo "\n" . esc_html__('Descargas', 'sorteo-sco') . ":\n";
        foreach ($downloads as $d) {
            $name = isset($d['download_name']) ? $d['download_name'] : '';
            $url  = isset($d['download_url']) ? $d['download_url'] : '';
            echo '- ' . wp_kses_post($name) . ': ' . esc_url_raw($url) . "\n";
        }
        echo "\n";
    } else {
        // Render simple y robusto, sin depender de la plantilla de WooCommerce
        echo '<h2>' . esc_html__('Descargas', 'sorteo-sco') . '</h2><ul style="margin:0 0 1em 1em;">';
        foreach ($downloads as $d) {
            $name = isset($d['download_name']) ? $d['download_name'] : '';
            $url  = isset($d['download_url']) ? $d['download_url'] : '';
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($name) . '</a></li>';
        }
        echo '</ul>';
    }
}
