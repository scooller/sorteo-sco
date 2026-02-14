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

// 10. AJAX Handler to add random products to cart
add_action('wp_ajax_sco_add_package_new_to_cart', 'sco_package_new_ajax_add_to_cart');
add_action('wp_ajax_nopriv_sco_add_package_new_to_cart', 'sco_package_new_ajax_add_to_cart');
function sco_package_new_ajax_add_to_cart()
{
    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        wp_send_json_error(__('Datos incompletos.', 'sorteo-sco'));
    }

    $product_id = intval($_POST['product_id']);

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'sco_pkg_new_nonce')) {
        error_log('SCO_PKG_NEW ERROR: Nonce inválido para producto ' . $product_id);
        wp_send_json_error(__('Token inválido.', 'sorteo-sco'));
    }
    $selected_quantity = intval($_POST['quantity']);
    $product = wc_get_product($product_id);

    if (!$product || $product->get_type() !== 'paquete_sco_new') {
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
        error_log('SCO_PKG_NEW ERROR: Producto ' . $product_id . ' sin categoría configurada');
        wp_send_json_error(__('Este paquete no tiene categoría configurada.', 'sorteo-sco'));
    }

    // Obtener productos de la categoría
    $category_products = sco_pkg_new_get_category_products($category_id, array($product_id));
    if (count($category_products) < $selected_quantity) {
        error_log('SCO_PKG_NEW ERROR: Productos insuficientes en categoría ' . $category_id . '. Necesarios: ' . $selected_quantity . ', Disponibles: ' . count($category_products));
        wp_send_json_error(
            sprintf(
                __('No hay suficientes productos. Se necesitan %d pero solo hay %d.', 'sorteo-sco'),
                $selected_quantity,
                count($category_products)
            )
        );
    }

    // Barajar productos y agregar sin duplicados
    shuffle($category_products);
    $added_count = 0;
    $failed_products = array();
    foreach ($category_products as $prod_id) {
        if ($added_count >= $selected_quantity) {
            break;
        }

        $added = WC()->cart->add_to_cart($prod_id, 1);
        if ($added) {
            $added_count++;
        } else {
            $failed_products[] = $prod_id;
        }
    }

    if ($added_count === $selected_quantity) {
        wp_send_json_success(array(
            'message' => sprintf(__('%d productos agregados al carrito.', 'sorteo-sco'), $added_count),
            'redirect' => wc_get_cart_url(),
        ));
    } else {
        error_log('SCO_PKG_NEW ERROR: Falló agregar productos al carrito. Agregados: ' . $added_count . '/' . $selected_quantity . '. Fallidos: ' . implode(',', $failed_products));
        wp_send_json_error(__('Error al agregar productos al carrito.', 'sorteo-sco'));
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
function sco_pkg_new_get_category_products($category_id, $exclude_ids = array())
{
    $args = array(
        'post_type' => 'product',
        'numberposts' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'product_cat',
                'field' => 'id',
                'terms' => $category_id,
            ),
        ),
        'post__not_in' => array(),
        'fields' => 'ids',
    );

    $product_ids = get_posts($args);
    $exclude_ids = array_map('intval', (array) $exclude_ids);
    $filtered = array();

    foreach ($product_ids as $prod_id) {
        if (!empty($exclude_ids) && in_array($prod_id, $exclude_ids, true)) {
            continue;
        }

        $prod = wc_get_product($prod_id);
        if (!$prod || !$prod->is_purchasable() || !$prod->is_in_stock()) {
            continue;
        }

        $filtered[] = $prod_id;
    }

    return $filtered;
}

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
                // Manejar submit del form con AJAX
                $('form.cart[data-product_id="<?php echo esc_attr($product_id); ?>"]').on('submit', function(e) {
                    e.preventDefault();

                    var qty = $('#sco_pkg_new_qty').val();
                    if (!qty) {
                        alert('<?php echo esc_js(__('Por favor selecciona una cantidad.', 'sorteo-sco')); ?>');
                        return false;
                    }

                    var $button = $(this).find('button[name="add-to-cart"]');
                    $button.prop('disabled', true).text('<?php echo esc_js(__('Agregando...', 'sorteo-sco')); ?>');

                    $.ajax({
                        type: 'POST',
                        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                        data: {
                            action: 'sco_add_package_new_to_cart',
                            product_id: '<?php echo esc_attr($product_id); ?>',
                            quantity: qty,
                            nonce: '<?php echo wp_create_nonce('sco_pkg_new_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('body').trigger('wc_fragment_refresh');
                                window.location.href = response.data.redirect;
                            } else {
                                alert(response.data.message || '<?php echo esc_js(__('Error al agregar productos.', 'sorteo-sco')); ?>');
                                $button.prop('disabled', false).text('<?php echo esc_js(__('Agregar al carrito', 'sorteo-sco')); ?>');
                            }
                        },
                        error: function() {
                            alert('<?php echo esc_js(__('Error de conexión.', 'sorteo-sco')); ?>');
                            $button.prop('disabled', false).text('<?php echo esc_js(__('Agregar al carrito', 'sorteo-sco')); ?>');
                        }
                    });
                });
            });
        })(jQuery);
    </script>
<?php
}
