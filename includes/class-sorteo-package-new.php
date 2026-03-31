<?php

/**
 * Custom Product Type - Paquete SCO Nueva
 * Producto simple que agrega X productos aleatorios de una categoría al carrito
 * Sin duplicados, stock gestionado por WooCommerce
 *
 * @package Sorteo_SCO
 * @since 1.9.30
 */
if (!defined('ABSPATH')) {
    exit;
}

function sco_pkg_new_get_category_label($product_id)
{
    $label = '';
    $category_raw = get_post_meta($product_id, '_sco_pkg_new_category', true);
    $category_id = intval($category_raw);

    if ($category_id > 0) {
        $term = get_term($category_id, 'product_cat');
        if ($term && !is_wp_error($term)) {
            $label = $term->name;
        }
    } elseif (is_string($category_raw) && $category_raw !== '') {
        $term = get_term_by('slug', $category_raw, 'product_cat');
        if (!$term) {
            $term = get_term_by('name', $category_raw, 'product_cat');
        }
        if ($term && !is_wp_error($term)) {
            $label = $term->name;
        }
    }

    return $label;
}

// 1. Add custom product type to dropdown
add_filter('product_type_selector', 'sco_package_new_add_product_type');
function sco_package_new_add_product_type($types)
{
    $types['paquete_sco_new'] = __('Paquete SCO (Nuevo)', 'sorteo-sco');
    return $types;
}

// 2. Add custom product type class mapping
add_filter('woocommerce_product_class', 'sco_package_new_woocommerce_product_class', 10, 2);
function sco_package_new_woocommerce_product_class($classname, $product_type)
{
    if ($product_type === 'paquete_sco_new') {
        $classname = 'WC_Product_Paquete_Sco_New';
    }
    return $classname;
}

// 3. Create custom product type class AFTER WooCommerce is loaded
add_action('woocommerce_loaded', 'sco_package_new_create_custom_product_class');
function sco_package_new_create_custom_product_class()
{
    if (!class_exists('WC_Product')) {
        return;
    }

    class WC_Product_Paquete_Sco_New extends WC_Product
    {
        protected $product_type = 'paquete_sco_new';

        public function __construct($product = 0)
        {
            $this->product_type = 'paquete_sco_new';
            parent::__construct($product);
        }

        public function get_type()
        {
            return 'paquete_sco_new';
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

// 4. Add custom product data tab
add_filter('woocommerce_product_data_tabs', 'sco_package_new_add_product_data_tab');
function sco_package_new_add_product_data_tab($tabs)
{
    $tabs['paquete_sco_new_tab'] = array(
        'label'  => __('Paquete SCO Nuevo', 'sorteo-sco'),
        'target' => 'paquete_sco_new_product_data',
        'class'  => array('show_if_paquete_sco_new'),
    );
    return $tabs;
}

// Show/hide fields with JavaScript
add_action('admin_footer', 'sco_package_new_custom_product_type_js');
function sco_package_new_custom_product_type_js()
{
    if ('product' != get_post_type()) {
        return;
    }
?>
    <script type='text/javascript'>
        jQuery(document).ready(function($) {
            function togglePaqueteScoNewFields() {
                var type = $('select#product-type').val();
                var isPaqueteNew = type === 'paquete_sco_new';

                if (isPaqueteNew) {
                    $('#paquete_sco_new_product_data').show();
                    $('a[href="#paquete_sco_new_product_data"]').closest('li').show();
                    $('.show_if_paquete_sco_new').show();
                    $('#_virtual').prop('checked', true);
                } else {
                    $('#paquete_sco_new_product_data').hide();
                    $('a[href="#paquete_sco_new_product_data"]').closest('li').hide();
                    $('.show_if_paquete_sco_new').hide();
                }
            }

            $('select#product-type').on('change', function() {
                setTimeout(togglePaqueteScoNewFields, 50);
            });

            $('body').on('woocommerce-product-type-change', function() {
                setTimeout(togglePaqueteScoNewFields, 100);
            });

            togglePaqueteScoNewFields();
        });
    </script>
<?php
}

// 5. Render custom product data panel
add_action('woocommerce_product_data_panels', 'sco_package_new_render_product_data_panel');
function sco_package_new_render_product_data_panel()
{
    global $post;
    $product_id = $post->ID;

    $category_id = intval(get_post_meta($product_id, '_sco_pkg_new_category', true));
    $quantities_str = get_post_meta($product_id, '_sco_pkg_new_quantities', true) ?: '4,8,10,20,25';

?>
    <div id="paquete_sco_new_product_data" class="panel woocommerce_options_panel show_if_paquete_sco_new">
        <div class="options_group">
            <p class="form-field">
                <label for="_sco_pkg_new_category">
                    <?php esc_html_e('Categoría fuente:', 'sorteo-sco'); ?>
                </label>
                <select id="_sco_pkg_new_category" name="_sco_pkg_new_category" class="wc-category-search" style="width: 100%;">
                    <option value="">-- <?php esc_html_e('Selecciona una categoría', 'sorteo-sco'); ?> --</option>
                    <?php
                    // Get all product categories
                    $categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                    ));

                    if (!empty($categories) && !is_wp_error($categories)) {
                        foreach ($categories as $cat) {
                            echo '<option value="' . intval($cat->term_id) . '" ' . selected($category_id, $cat->term_id, false) . '>'
                                . esc_html($cat->name) . '</option>';
                        }
                    }
                    ?>
                </select>
                <span class="woocommerce_help_tip" data-tip="<?php esc_attr_e('Categoría de la cual se seleccionarán productos al azar', 'sorteo-sco'); ?>"></span>
            </p>
        </div>

        <div class="options_group">
            <p class="form-field">
                <label for="_sco_pkg_new_quantities">
                    <?php esc_html_e('Opciones de cantidad (separadas por comas):', 'sorteo-sco'); ?>
                </label>
                <input type="text" id="_sco_pkg_new_quantities" name="_sco_pkg_new_quantities"
                    value="<?php echo esc_attr($quantities_str); ?>" style="width: 100%; padding: 8px;"
                    placeholder="4,8,10,20,25" />
                <span class="woocommerce_help_tip" data-tip="<?php esc_attr_e('Ejemplo: 4,8,10,20,25. Estas serán las opciones que verá el cliente', 'sorteo-sco'); ?>"></span>
            </p>
        </div>

        <div class="options_group">
            <p class="form-field">
                <label><?php esc_html_e('Información:', 'sorteo-sco'); ?></label>
            <div style="background: #f5f5f5; padding: 10px; border-left: 3px solid #4CAF50;">
                <p><?php esc_html_e('Cuando un cliente selecciona una cantidad en el frontend, se agregan ese número de productos aleatorios (sin duplicados) de la categoría fuente al carrito.', 'sorteo-sco'); ?></p>
            </div>
            </p>
        </div>
    </div>
<?php
}

// 6. Save product type correctly
add_action('woocommerce_admin_process_product_object', 'sco_package_new_save_product_type_on_admin', 999);
function sco_package_new_save_product_type_on_admin($product)
{
    if (isset($_POST['product-type']) && $_POST['product-type'] === 'paquete_sco_new') {
        wp_set_object_terms($product->get_id(), 'paquete_sco_new', 'product_type');
    }
}

// 7. Save custom meta fields
add_action('woocommerce_admin_process_product_object', 'sco_package_new_save_product_meta');
function sco_package_new_save_product_meta($product)
{
    if ($product->get_type() !== 'paquete_sco_new') {
        return;
    }

    $product_id = $product->get_id();

    // Save category
    if (isset($_POST['_sco_pkg_new_category'])) {
        $category_input = $_POST['_sco_pkg_new_category'];

        if (is_numeric($category_input)) {
            $category_id = absint($category_input);
        } else {
            $term = get_term_by('slug', $category_input, 'product_cat');
            if (!$term) {
                $term = get_term_by('name', $category_input, 'product_cat');
            }
            $category_id = ($term && !is_wp_error($term)) ? $term->term_id : 0;
        }

        if ($category_id > 0) {
            update_post_meta($product_id, '_sco_pkg_new_category', $category_id);
        } else {
            delete_post_meta($product_id, '_sco_pkg_new_category');
        }
    }

    // Save quantities
    if (isset($_POST['_sco_pkg_new_quantities'])) {
        $quantities = sanitize_text_field($_POST['_sco_pkg_new_quantities']);
        update_post_meta($product_id, '_sco_pkg_new_quantities', $quantities);
    } else {
        update_post_meta($product_id, '_sco_pkg_new_quantities', '4,8,10,20,25');
    }
}

// 8. Custom add to cart button text
add_filter('woocommerce_add_to_cart_text', 'sco_package_new_add_to_cart_text', 10, 2);
function sco_package_new_add_to_cart_text($text, $product)
{
    if ($product && $product->get_type() === 'paquete_sco_new') {
        if ($product->is_purchasable() && $product->is_in_stock()) {
            return __('Agregar al carrito', 'sorteo-sco');
        }
    }
    return $text;
}

// 9a. AJAX nonce endpoint – devuelve un nonce fresco en cada petición.
// Las páginas cacheadas incrustan un nonce que puede expirar; este endpoint
// no se cachea porque es AJAX, por lo que siempre devuelve un token válido.
add_action('wp_ajax_sco_pkg_new_get_nonce', 'sco_pkg_new_get_nonce_handler');
add_action('wp_ajax_nopriv_sco_pkg_new_get_nonce', 'sco_pkg_new_get_nonce_handler');
function sco_pkg_new_get_nonce_handler()
{
    wp_send_json_success(array('nonce' => wp_create_nonce('sco_pkg_new_nonce')));
}

// 9. AJAX handler to get quantities
add_action('wp_ajax_sco_get_package_new_quantities', 'sco_package_new_get_quantities_ajax');
add_action('wp_ajax_nopriv_sco_get_package_new_quantities', 'sco_package_new_get_quantities_ajax');
function sco_package_new_get_quantities_ajax()
{
    if (!isset($_POST['product_id'])) {
        wp_send_json_error(__('Producto no especificado', 'sorteo-sco'));
    }

    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);

    if (!$product || $product->get_type() !== 'paquete_sco_new') {
        wp_send_json_error(__('Producto no válido.', 'sorteo-sco'));
    }

    $quantities_str = get_post_meta($product_id, '_sco_pkg_new_quantities', true) ?: '4,8,10,20,25';
    $quantities = array_filter(array_map('intval', explode(',', $quantities_str)));
    sort($quantities);

    wp_send_json_success(array(
        'quantities' => $quantities,
    ));
}

// 10. AJAX Handler - Step 1: Get product IDs to add (no memory-heavy add_to_cart)
add_action('wp_ajax_sco_get_package_new_products', 'sco_package_new_ajax_get_products');
add_action('wp_ajax_nopriv_sco_get_package_new_products', 'sco_package_new_ajax_get_products');
function sco_package_new_ajax_get_products()
{
    error_log('[SCO PKG NEW v1.9.35] === sco_get_package_new_products CALLED === Memory: ' . round(memory_get_usage() / 1024 / 1024, 1) . 'MB / ' . ini_get('memory_limit'));
    error_log('[SCO PKG NEW] POST data: ' . wp_json_encode($_POST));

    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        error_log('[SCO PKG NEW] ERROR: Datos incompletos');
        wp_send_json_error(__('Datos incompletos.', 'sorteo-sco'));
    }

    $product_id = intval($_POST['product_id']);

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'sco_pkg_new_nonce')) {
        wp_send_json_error(__('Token inválido.', 'sorteo-sco'));
    }
    $selected_quantity = intval($_POST['quantity']);

    // Verificar tipo de producto via taxonomía (sin cargar objeto WC_Product)
    $type_terms = get_the_terms($product_id, 'product_type');
    $product_type = (!empty($type_terms) && !is_wp_error($type_terms)) ? $type_terms[0]->slug : '';

    if ($product_type !== 'paquete_sco_new') {
        error_log('[SCO PKG NEW] ERROR: Tipo de producto incorrecto: ' . $product_type);
        wp_send_json_error(__('Producto no válido.', 'sorteo-sco'));
    }

    // Validar cantidad permitida
    $quantities_str = get_post_meta($product_id, '_sco_pkg_new_quantities', true) ?: '4,8,10,20,25';
    $quantities = array_filter(array_map('intval', explode(',', $quantities_str)));

    if ($selected_quantity <= 0 || !in_array($selected_quantity, $quantities)) {
        wp_send_json_error(__('Cantidad no válida.', 'sorteo-sco'));
    }

    // Validar categoría
    $category_id = intval(get_post_meta($product_id, '_sco_pkg_new_category', true));
    if ($category_id <= 0) {
        wp_send_json_error(__('Este paquete no tiene categoría configurada.', 'sorteo-sco'));
    }

    // Excluir productos del carrito via session raw (sin cargar objetos producto)
    $exclude_ids = array($product_id);
    if (WC()->session) {
        $cart_session = WC()->session->get('cart', array());
        if (is_array($cart_session)) {
            foreach ($cart_session as $item) {
                if (!empty($item['product_id'])) {
                    $exclude_ids[] = intval($item['product_id']);
                }
            }
        }
    }

    // Excluir productos ya vendidos en pedidos activos + reservados en carritos de OTROS usuarios
    if (function_exists('sco_pkg_get_committed_product_ids')) {
        $committed = sco_pkg_get_committed_product_ids();
        if (!empty($committed) && is_array($committed)) {
            $exclude_ids = array_merge($exclude_ids, array_keys($committed));
        }
        error_log('[SCO PKG NEW] Committed products excluded: ' . count($committed));
    }

    $exclude_ids = array_unique($exclude_ids);

    error_log('[SCO PKG NEW] Product ID: ' . $product_id . ', Quantity: ' . $selected_quantity . ', Category: ' . $category_id);
    error_log('[SCO PKG NEW] Exclude IDs (cart + committed + self): ' . count($exclude_ids) . ' total');

    // Obtener productos de la categoría (validación 100% SQL, sin wc_get_product())
    $category_products = sco_pkg_new_get_category_products($category_id, $exclude_ids, $selected_quantity);
    error_log('[SCO PKG NEW] Products found in category: ' . count($category_products));
    if (count($category_products) < $selected_quantity) {
        wp_send_json_error(
            sprintf(
                __('No hay suficientes productos. Se necesitan %d pero solo hay %d.', 'sorteo-sco'),
                $selected_quantity,
                count($category_products)
            )
        );
    }

    shuffle($category_products);
    $product_ids = array_slice($category_products, 0, $selected_quantity);

    // Pre-reservar los productos seleccionados ANTES de enviarlos al frontend
    // Esto cierra la race condition entre Step 1 y Step 2
    if (!empty($product_ids) && function_exists('WC') && WC()->session) {
        $session_id = (string) WC()->session->get_customer_id();
        if (empty($session_id) && method_exists(WC()->session, 'get_session_id')) {
            $session_id = (string) WC()->session->get_session_id();
        }
        if ($session_id) {
            $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
            $session_map = isset($reservations[$session_id]) ? $reservations[$session_id] : array();
            $now = time();
            foreach ($product_ids as $pid) {
                $session_map[(int) $pid] = array(
                    'quantity'  => 1,
                    'timestamp' => $now,
                    'source'    => 'sco_pkg_new_preselect',
                );
            }
            $reservations[$session_id] = $session_map;
            set_transient('bootstrap_theme_stock_reservations', $reservations, 5 * MINUTE_IN_SECONDS);
            error_log('[SCO PKG NEW] Pre-reserved ' . count($product_ids) . ' products for session ' . substr($session_id, 0, 10));
        }
    }

    error_log('[SCO PKG NEW] Returning ' . count($product_ids) . ' product IDs: ' . implode(',', $product_ids));

    wp_send_json_success(array(
        'product_ids' => $product_ids,
        'total'       => count($product_ids),
    ));
}

// 10b. AJAX Handler - Step 2: Add a single product to cart (one request per product)
add_action('wp_ajax_sco_add_single_to_cart', 'sco_package_new_ajax_add_single');
add_action('wp_ajax_nopriv_sco_add_single_to_cart', 'sco_package_new_ajax_add_single');
function sco_package_new_ajax_add_single()
{
    error_log('[SCO PKG NEW v1.9.35] === sco_add_single_to_cart CALLED ===');

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'sco_pkg_new_nonce')) {
        error_log('[SCO PKG NEW] ERROR: Nonce inválido');
        wp_send_json_error(__('Token inválido.', 'sorteo-sco'));
    }

    $prod_id = isset($_POST['prod_id']) ? intval($_POST['prod_id']) : 0;
    if ($prod_id <= 0) {
        error_log('[SCO PKG NEW] ERROR: prod_id inválido: ' . $prod_id);
        wp_send_json_error(__('Producto no válido.', 'sorteo-sco'));
    }

    error_log('[SCO PKG NEW] Adding product #' . $prod_id . ' to cart...');
    $added = WC()->cart->add_to_cart($prod_id, 1);
    if ($added) {
        error_log('[SCO PKG NEW] SUCCESS: Product #' . $prod_id . ' added. Cart key: ' . $added);

        // Sincronizar reservas para que otros usuarios vean este producto como reservado
        if (function_exists('sco_pkg_sync_reservations_with_cart')) {
            sco_pkg_sync_reservations_with_cart(true);
        }
        // Invalidar cache de committed products para el próximo request
        if (function_exists('sco_pkg_reset_committed_cache')) {
            sco_pkg_reset_committed_cache();
        }

        wp_send_json_success(array('added' => $prod_id));
    } else {
        error_log('[SCO PKG NEW] FAIL: Could not add product #' . $prod_id);
        wp_send_json_error(sprintf(__('No se pudo agregar el producto #%d.', 'sorteo-sco'), $prod_id));
    }
}

// 11. Validate to prevent direct add (handled via AJAX)
add_filter('woocommerce_add_to_cart_validation', 'sco_package_new_validate_before_add_to_cart', 10, 3);
function sco_package_new_validate_before_add_to_cart($passed, $product_id, $quantity)
{
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'paquete_sco_new') {
        return $passed;
    }

    // Para este tipo de producto, NO agregar directamente
    // Se agregan los productos individuales vía AJAX
    return false;
}

// 12. Get all products in a category
function sco_pkg_new_get_category_products($category_id, $exclude_ids = array(), $required_count = 0)
{
    $category_id = intval($category_id);
    if ($category_id <= 0) {
        return array();
    }

    $required_count = max(1, intval($required_count));
    $exclude_ids = array_map('intval', array_filter((array) $exclude_ids));

    // Pool suficiente para buena aleatorización sin cargar objetos producto.
    $pool_limit = min(2000, max($required_count * 5, 200));

    $query_args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => $pool_limit,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'tax_query'      => array(
            'relation' => 'AND',
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'term_id',
                'terms'    => array($category_id),
            ),
            array(
                'taxonomy' => 'product_type',
                'field'    => 'slug',
                'terms'    => array('sco_package', 'paquete_sco_new', 'grouped', 'external'),
                'operator' => 'NOT IN',
            ),
        ),
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'   => '_stock_status',
                'value' => 'instock',
            ),
            array(
                'key'     => '_price',
                'value'   => '',
                'compare' => '!=',
            ),
        ),
        'update_post_meta_cache'  => false,
        'update_post_term_cache'  => false,
        'cache_results'           => false,
        'suppress_filters'        => true,
    );

    if (!empty($exclude_ids)) {
        $query_args['post__not_in'] = $exclude_ids;
    }

    $query = new WP_Query($query_args);
    $all_ids = array_map('intval', (array) $query->posts);
    wp_reset_postdata();

    shuffle($all_ids);

    // Retornar solo lo necesario + buffer para posibles fallos en add_to_cart
    $buffer = min(count($all_ids), $required_count + 5);
    return array_slice($all_ids, 0, $buffer);
}

/**
 * 12-16. Composite cart item hooks (display, order meta, stock reduce/restore).
 *
 * These hooks are registered but currently inactive: the AJAX handler
 * (`sco_package_new_ajax_add_to_cart`) adds each component as an individual
 * cart item, so WooCommerce handles stock management natively.
 * The hooks below would activate only if the cart item contained a
 * `sco_pkg_new` key — reserved for a future composite-cart-item approach.
 */

// 12. Display composition in cart
add_filter('woocommerce_get_item_data', 'sco_package_new_display_cart_item_data', 10, 2);
function sco_package_new_display_cart_item_data($item_data, $cart_item)
{
    if (!isset($cart_item['sco_pkg_new'])) {
        return $item_data;
    }

    $pkg = $cart_item['sco_pkg_new'];
    $qty = isset($pkg['selected_quantity']) ? intval($pkg['selected_quantity']) : 0;

    $item_data[] = array(
        'key' => __('Cantidad de productos', 'sorteo-sco'),
        'value' => $qty,
        'display' => '',
    );

    if (isset($pkg['components']) && is_array($pkg['components'])) {
        foreach ($pkg['components'] as $comp) {
            $comp_product = wc_get_product($comp['product_id']);
            if ($comp_product) {
                $item_data[] = array(
                    'key' => __('Producto incluido', 'sorteo-sco'),
                    'value' => $comp_product->get_name(),
                    'display' => '',
                );
            }
        }
    }

    return $item_data;
}

// 14. Save composition to order item meta
add_action('woocommerce_checkout_create_order_line_item', 'sco_package_new_add_order_item_meta', 10, 4);
function sco_package_new_add_order_item_meta($item, $cart_item_key, $values, $order)
{
    if (!isset($values['sco_pkg_new'])) {
        return;
    }

    $pkg = $values['sco_pkg_new'];
    $item->add_meta_data('_sco_pkg_new', $pkg, true);
}

// 15. Reduce stock for package components
add_action('woocommerce_reduce_order_item_stock', 'sco_package_new_reduce_stock', 10, 3);
function sco_package_new_reduce_stock($item, $item_data, $order)
{
    $pkg_meta = $item->get_meta('_sco_pkg_new');
    if (null === $pkg_meta) {
        return;
    }

    $pkg = $pkg_meta;
    $quantity = $item->get_quantity();

    if (isset($pkg['components']) && is_array($pkg['components'])) {
        foreach ($pkg['components'] as $component) {
            $comp_product = wc_get_product($component['product_id']);
            if ($comp_product && $comp_product->get_manage_stock()) {
                // Restar stock multiplicado por la cantidad del item
                $stock_qty = intval($component['qty']) * intval($quantity);
                $comp_product->reduce_stock($stock_qty);
            }
        }
    }
}

// 16. Restore stock for package components on refund/cancellation
add_action('woocommerce_order_item_restoration_requested', 'sco_package_new_restore_stock', 10, 2);
function sco_package_new_restore_stock($item, $order)
{
    $pkg_meta = $item->get_meta('_sco_pkg_new');
    if (null === $pkg_meta) {
        return;
    }

    $pkg = $pkg_meta;
    $quantity = $item->get_quantity();

    if (isset($pkg['components']) && is_array($pkg['components'])) {
        foreach ($pkg['components'] as $component) {
            $comp_product = wc_get_product($component['product_id']);
            if ($comp_product && $comp_product->get_manage_stock()) {
                // Sumar stock multiplicado por la cantidad del item
                $stock_qty = intval($component['qty']) * intval($quantity);
                $comp_product->increase_stock($stock_qty);
            }
        }
    }
}

// 17. Add custom form for single product page
add_action('woocommerce_paquete_sco_new_add_to_cart', 'sco_package_new_add_to_cart_form');
function sco_package_new_add_to_cart_form()
{
    global $product;

    if (!$product || $product->get_type() !== 'paquete_sco_new') {
        return;
    }

    if (!$product->is_purchasable() || !$product->is_in_stock()) {
        return;
    }

    $product_id = $product->get_id();
    $category_label = sco_pkg_new_get_category_label($product_id);
    $quantities_str = get_post_meta($product_id, '_sco_pkg_new_quantities', true) ?: '4,8,10,20,25';
    $quantities = array_filter(array_map('intval', explode(',', $quantities_str)));
    sort($quantities);

    if (empty($quantities)) {
        echo '<p class="alert alert-warning">' . esc_html__('No hay cantidades disponibles configuradas.', 'sorteo-sco') . '</p>';
        return;
    }

?>
    <form class="variations_form cart" method="post" enctype='multipart/form-data' data-product_id="<?php echo esc_attr($product_id); ?>">
        <div class="variations">
            <div class="form-group mb-3">
                <label for="sco_pkg_new_qty" class="form-label">
                    <?php echo $category_label ? esc_html(sprintf(__('Cantidad de %s:', 'sorteo-sco'), $category_label)) : esc_html__('Cantidad de productos:', 'sorteo-sco'); ?>
                </label>
                <select id="sco_pkg_new_qty" name="sco_pkg_new_qty" class="form-select" required>
                    <option value="">
                        <?php echo $category_label ? esc_html(sprintf(__('-- Selecciona una cantidad de %s --', 'sorteo-sco'), $category_label)) : esc_html__('-- Selecciona una cantidad --', 'sorteo-sco'); ?>
                    </option>
                    <?php foreach ($quantities as $qty) : ?>
                        <option value="<?php echo intval($qty); ?>">
                            <?php echo intval($qty); ?> <?php echo $category_label ? esc_html($category_label) : ($qty === 1 ? esc_html__('producto', 'sorteo-sco') : esc_html__('productos', 'sorteo-sco')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="single_variation_wrap">
            <?php do_action('woocommerce_before_add_to_cart_button'); ?>

            <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product_id); ?>" class="single_add_to_cart_button btn btn-primary btn-lg" style="width: 100%;">
                <i class="fa-solid fa-cart-plus"></i>
                <?php echo esc_html($product->single_add_to_cart_text()); ?>
            </button>

            <?php do_action('woocommerce_after_add_to_cart_button'); ?>
        </div>
    </form>

    <script type="text/javascript">
        (function($) {
            'use strict';
            $(document).ready(function() {
                console.log('[SCO PKG NEW v1.9.35] Split-AJAX JS loaded');
                var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                var nonce   = '<?php echo wp_create_nonce('sco_pkg_new_nonce'); ?>';
                var prodId  = '<?php echo esc_attr($product_id); ?>';
                var cartUrl = '<?php echo esc_url(wc_get_cart_url()); ?>';

                $('form.cart[data-product_id="' + prodId + '"]').on('submit', function(e) {
                    e.preventDefault();

                    var $form = $(this);
                    if ($form.data('sco-submitting')) { return false; }

                    var qty = $('#sco_pkg_new_qty').val();
                    if (!qty) {
                        alert('<?php echo esc_js(__('Por favor selecciona una cantidad.', 'sorteo-sco')); ?>');
                        return false;
                    }

                    $form.data('sco-submitting', true);
                    var $button = $form.find('button[name="add-to-cart"]');
                    $button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js(__('Preparando...', 'sorteo-sco')); ?>');

                    // Renovar el nonce antes de Step 1 para que las páginas cacheadas
                    // no fallen con "Token inválido" al usar un nonce envejecido.
                    $.post(ajaxUrl, { action: 'sco_pkg_new_get_nonce' })
                        .always(function(r) {
                            if (r && r.success && r.data && r.data.nonce) {
                                nonce = r.data.nonce;
                            }

                    // Step 1: Get product IDs
                    console.log('[SCO PKG NEW] Step 1: Requesting product IDs. Product:', prodId, 'Qty:', qty);
                    $.ajax({
                        type: 'POST',
                        url: ajaxUrl,
                        data: {
                            action: 'sco_get_package_new_products',
                            product_id: prodId,
                            quantity: qty,
                            nonce: nonce
                        },
                        success: function(response) {
                            console.log('[SCO PKG NEW] Step 1 response:', response);
                            if (!response.success) {
                                alert(response.data || '<?php echo esc_js(__('Error al obtener productos.', 'sorteo-sco')); ?>');
                                resetButton($form, $button);
                                return;
                            }

                            var ids   = response.data.product_ids;
                            var total = ids.length;
                            var added = 0;
                            var failed = 0;

                            // Step 2: Add products one by one
                            function addNext() {
                                if (added + failed >= total) {
                                    // Done
                                    if (added > 0) {
                                        $button.html('<i class="fa-solid fa-check"></i> ' + added + ' <?php echo esc_js(__('agregados', 'sorteo-sco')); ?>');
                                        $('body').trigger('wc_fragment_refresh');
                                        setTimeout(function() {
                                            window.location.href = cartUrl;
                                        }, 500);
                                    } else {
                                        alert('<?php echo esc_js(__('No se pudieron agregar los productos.', 'sorteo-sco')); ?>');
                                        resetButton($form, $button);
                                    }
                                    return;
                                }

                                var currentIndex = added + failed;
                                console.log('[SCO PKG NEW] Step 2: Adding product ' + (currentIndex + 1) + '/' + total + ' (ID: ' + ids[currentIndex] + ')');
                                $button.html('<i class="fa-solid fa-spinner fa-spin"></i> <?php echo esc_js(__('Agregando', 'sorteo-sco')); ?> ' + (currentIndex + 1) + ' / ' + total + '...');

                                $.ajax({
                                    type: 'POST',
                                    url: ajaxUrl,
                                    data: {
                                        action: 'sco_add_single_to_cart',
                                        prod_id: ids[currentIndex],
                                        nonce: nonce
                                    },
                                    success: function(res) {
                                        console.log('[SCO PKG NEW] Step 2 response for product ' + ids[currentIndex] + ':', res);
                                        if (res.success) {
                                            added++;
                                        } else {
                                            failed++;
                                        }
                                        addNext();
                                    },
                                    error: function() {
                                        failed++;
                                        addNext();
                                    }
                                });
                            }

                            addNext();
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Error de conexión.', 'sorteo-sco')); ?>');
                            resetButton($form, $button);
                        }
                    });

                        }); // end $.post nonce refresh
                });

                function resetButton($form, $button) {
                    $button.prop('disabled', false).html('<i class="fa-solid fa-cart-plus"></i> <?php echo esc_js(__('Agregar al carrito', 'sorteo-sco')); ?>');
                    $form.data('sco-submitting', false);
                }
            });
        })(jQuery);
    </script>
<?php
}
