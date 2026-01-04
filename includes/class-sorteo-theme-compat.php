<?php

/**
 * Theme Compatibility Helper
 * Detecta si el tema Bootstrap está activo y adapta el HTML según corresponda
 * 
 * @package Sorteo_SCO
 * @since 1.9.9
 */

if (!defined('ABSPATH')) exit;

class Sorteo_Theme_Compat
{

    /**
     * Verifica si el tema Bootstrap está activo
     * 
     * @return bool
     */
    public static function is_bootstrap_theme_active()
    {
        $theme = wp_get_theme();
        $theme_name = $theme->get('Name');
        $parent_theme = $theme->parent();

        // Verifica tema actual o padre
        if (stripos($theme_name, 'Bootstrap Theme') !== false) {
            return true;
        }

        if ($parent_theme && stripos($parent_theme->get('Name'), 'Bootstrap Theme') !== false) {
            return true;
        }

        // Verifica constante del tema
        if (defined('BOOTSTRAP_THEME_VERSION')) {
            return true;
        }

        return false;
    }

    /**
     * Retorna clases apropiadas según el tema activo
     * 
     * @param string $context Contexto del elemento (button, dropdown, alert, etc)
     * @param string $variant Variante del elemento (primary, success, etc)
     * @return string Clases CSS
     */
    public static function get_classes($context, $variant = 'default')
    {
        $is_bootstrap = self::is_bootstrap_theme_active();

        switch ($context) {
            case 'button':
                if ($is_bootstrap) {
                    $variants = array(
                        'primary' => 'btn btn-primary',
                        'success' => 'btn btn-success',
                        'outline-success' => 'btn btn-outline-success',
                        'sm' => 'btn-sm',
                        'default' => 'btn'
                    );
                    return isset($variants[$variant]) ? $variants[$variant] : $variants['default'];
                } else {
                    return 'button';
                }

            case 'dropdown-button':
                if ($is_bootstrap) {
                    return 'btn btn-outline-success btn-sm dropdown-toggle w-100';
                } else {
                    return '';
                }

            case 'dropdown-menu':
                if ($is_bootstrap) {
                    return 'dropdown-menu';
                } else {
                    return 'sorteo-dropdown-menu';
                }

            case 'dropdown-item':
                if ($is_bootstrap) {
                    return 'dropdown-item';
                } else {
                    return 'sorteo-dropdown-item';
                }

            case 'btn-group':
                if ($is_bootstrap) {
                    return 'btn-group';
                } else {
                    return 'sorteo-btn-group';
                }

            case 'alert':
                if ($is_bootstrap) {
                    $variants = array(
                        'success' => 'alert alert-success',
                        'danger' => 'alert alert-danger',
                        'warning' => 'alert alert-warning',
                        'info' => 'alert alert-info',
                        'default' => 'alert'
                    );
                    return isset($variants[$variant]) ? $variants[$variant] : $variants['default'];
                } else {
                    return 'notice notice-' . ($variant === 'danger' ? 'error' : $variant);
                }

            case 'form-control':
                if ($is_bootstrap) {
                    return 'form-control';
                } else {
                    return '';
                }

            case 'form-select':
                if ($is_bootstrap) {
                    return 'form-select';
                } else {
                    return '';
                }

            default:
                return '';
        }
    }

    /**
     * Retorna atributo data-bs-toggle si Bootstrap está activo
     * 
     * @param string $toggle Tipo de toggle (dropdown, modal, etc)
     * @return string
     */
    public static function get_data_toggle($toggle = 'dropdown')
    {
        $is_bootstrap = self::is_bootstrap_theme_active();
        return $is_bootstrap ? 'data-bs-toggle="' . esc_attr($toggle) . '"' : '';
    }

    /**
     * Renderiza dropdown de cantidad con compatibilidad de tema
     * 
     * @param int $product_id ID del producto
     * @param string $add_url URL de agregar al carrito
     * @param int $max_qty Cantidad máxima
     * @return string HTML del dropdown
     */
    public static function render_quantity_selector($product_id, $add_url, $max_qty = 10)
    {
        $is_bootstrap = self::is_bootstrap_theme_active();

        ob_start();

        if ($is_bootstrap) {
            // Versión Bootstrap con dropdown
?>
            <div class="<?php echo esc_attr(self::get_classes('btn-group', '')); ?> sco-pkg-qty-dropdown w-100" style="position:relative;z-index:10;">
                <button type="button" class="<?php echo esc_attr(self::get_classes('dropdown-button', '')); ?>" <?php echo self::get_data_toggle('dropdown'); ?> aria-expanded="false" style="position:relative;z-index:11;">
                    <i class="fa-solid fa-cart-plus"></i> <?php echo esc_html__('Cantidad', 'sorteo-sco'); ?>
                </button>
                <ul class="<?php echo esc_attr(self::get_classes('dropdown-menu', '')); ?>" style="z-index:12;">
                    <?php for ($q = 1; $q <= $max_qty; $q++): ?>
                        <li>
                            <a class="<?php echo esc_attr(self::get_classes('dropdown-item', '')); ?> add_to_cart_button ajax_add_to_cart product_type_sco_package"
                                href="<?php echo esc_url($add_url); ?>"
                                data-product_id="<?php echo esc_attr($product_id); ?>"
                                data-quantity="<?php echo esc_attr($q); ?>">
                                <i class="fa-solid fa-dice"></i> <?php echo esc_html($q . ' ' . __('paq', 'sorteo-sco')); ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
        <?php
        } else {
            // Versión HTML nativo con select y AJAX
        ?>
            <form class="sco-package-add-to-cart-form" method="post" enctype="multipart/form-data">
                <div class="quantity-selector">
                    <div class="col d-flex">
                        <label for="sco_qty_<?php echo esc_attr($product_id); ?>" style="width:60%;margin-bottom:5px;">
                            <?php echo esc_html__('Cantidad:', 'sorteo-sco'); ?>
                        </label>
                        <select name="quantity" id="sco_qty_<?php echo esc_attr($product_id); ?>" class="sco-qty-select" style="width:40%;margin-right:10px;--wp--preset--spacing--16: 5px;">
                            <?php for ($q = 1; $q <= $max_qty; $q++): ?>
                                <option value="<?php echo esc_attr($q); ?>"><?php echo esc_html($q); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col">
                        <button type="button"
                            class="button add_to_cart_button sco_add_to_cart_btn wp-block-button__link"
                            data-product_id="<?php echo esc_attr($product_id); ?>"
                            data-product_type="sco_package">
                            <i class="fa-solid fa-cart-plus"></i> <?php echo esc_html__('Agregar', 'sorteo-sco'); ?>
                        </button>
                    </div>
                </div>
            </form>
            <script>
                (function($) {
                    $(document).ready(function() {
                        $('.sco_add_to_cart_btn[data-product_id="<?php echo esc_js($product_id); ?>"]').on('click', function(e) {
                            e.preventDefault();

                            var $btn = $(this);
                            var $select = $('#sco_qty_<?php echo esc_js($product_id); ?>');
                            var qty = $select.val();
                            var productId = $btn.data('product_id');

                            // Deshabilitar botón
                            $btn.prop('disabled', true).addClass('loading');

                            // Agregar vía URL nativa de WooCommerce (más compatible)
                            var cartUrl = '<?php echo esc_url(wc_get_cart_url()); ?>?add-to-cart=' + productId + '&quantity=' + qty;

                            $.get(cartUrl, function(response) {
                                // Actualizar fragmentos del carrito
                                $(document.body).trigger('wc_fragment_refresh');
                                $(document.body).trigger('added_to_cart', [null, null, $btn]);

                                // Feedback visual
                                $btn.removeClass('loading').addClass('added');
                                var originalHtml = $btn.html();
                                $btn.html('<i class="fa-solid fa-check"></i> <?php echo esc_js(__('¡Agregado!', 'sorteo-sco')); ?>');

                                setTimeout(function() {
                                    $btn.removeClass('added').html(originalHtml).prop('disabled', false);
                                }, 2000);
                            }).fail(function() {
                                $btn.removeClass('loading').prop('disabled', false);
                                alert('<?php echo esc_js(__('Error al agregar al carrito', 'sorteo-sco')); ?>');
                            });
                        });
                    });
                })(jQuery);
            </script>
        <?php
        }

        return ob_get_clean();
    }

    /**
     * Agrega CSS fallback para temas no-Bootstrap
     */
    public static function add_fallback_styles()
    {
        if (!self::is_bootstrap_theme_active()) {
        ?>
            <style>
                .sorteo-dropdown-menu {
                    display: none;
                    position: absolute;
                    background: #fff;
                    border: 1px solid #ddd;
                    padding: 5px 0;
                    margin-top: 5px;
                    z-index: 1000;
                    min-width: 150px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                }

                .sorteo-dropdown-menu.show {
                    display: block;
                }

                .sorteo-dropdown-item {
                    display: block;
                    padding: 8px 15px;
                    text-decoration: none;
                    color: #333;
                }

                .sorteo-dropdown-item:hover {
                    background: #f5f5f5;
                }

                .sorteo-btn-group {
                    position: relative;
                    display: inline-block;
                }

                .notice {
                    padding: 12px;
                    margin: 10px 0;
                    border-left: 4px solid;
                }

                .notice-success {
                    background: #d4edda;
                    border-color: #28a745;
                    color: #155724;
                }

                .notice-error {
                    background: #f8d7da;
                    border-color: #dc3545;
                    color: #721c24;
                }

                .notice-warning {
                    background: #fff3cd;
                    border-color: #ffc107;
                    color: #856404;
                }

                .notice-info {
                    background: #d1ecf1;
                    border-color: #17a2b8;
                    color: #0c5460;
                }

                .sco-package-add-to-cart {
                    margin: 10px 0;
                }

                .quantity-selector {
                    display: flex;
                    flex-wrap: wrap;
                    align-items: end;
                }

                .quantity-selector label {
                    white-space: nowrap;
                    text-overflow: ellipsis;
                    overflow: hidden;
                }

                .quantity-selector .col {
                    width: 50%;
                    flex: 1 1 auto !important;
                }

                .quantity-selector .col.d-flex {
                    display: flex;
                    align-items: center;
                }


                .add_to_cart_button.loading {
                    opacity: 0.6;
                    pointer-events: none;
                }

                .add_to_cart_button.added {
                    background-color: #28a745;
                    color: white;
                }

                @media (max-width: 480px) {
                    .quantity-selector {
                        flex-direction: column;
                        align-items: stretch;
                    }

                    .quantity-selector .col {
                        width: 100%;
                        margin-bottom: 10px;
                    }
                }
            </style>
    <?php
        }
    }
}

// Agregar estilos fallback en frontend
add_action('wp_head', array('Sorteo_Theme_Compat', 'add_fallback_styles'));

// ============================================================================
// SINGLE PRODUCT PAGE SUPPORT (SOLO PARA TEMAS QUE NO SON BOOTSTRAP THEME)
// ============================================================================

/**
 * Custom add to cart template for sco_package in single product page
 * Solo se activa si NO es Bootstrap Theme (tu tema propio)
 */
add_action('woocommerce_sco_package_add_to_cart', 'sco_package_single_add_to_cart_button');
function sco_package_single_add_to_cart_button()
{
    global $product;

    if (!$product || $product->get_type() !== 'sco_package') {
        return;
    }

    if (!$product->is_purchasable() || !$product->is_in_stock()) {
        return;
    }

    // IMPORTANTE: Solo ejecutar si NO es Bootstrap Theme
    // Tu tema Bootstrap ya tiene su propia implementación
    if (Sorteo_Theme_Compat::is_bootstrap_theme_active()) {
        return;
    }

    do_action('woocommerce_before_add_to_cart_form');
    ?>

    <form class="cart sco-package-cart-form" method="post" enctype="multipart/form-data">
        <?php do_action('woocommerce_before_add_to_cart_button'); ?>

        <div class="quantity-wrapper" style="margin-bottom: 15px;">
            <label for="sco_package_qty" style="display: block; margin-bottom: 5px; font-weight: bold;">
                <?php echo esc_html__('Cantidad:', 'sorteo-sco'); ?>
            </label>

            <select name="quantity" id="sco_package_qty" class="qty" style="width: 100px; padding: 8px; font-size: 16px;">
                <?php for ($q = 1; $q <= 10; $q++): ?>
                    <option value="<?php echo esc_attr($q); ?>"><?php echo esc_html($q); ?></option>
                <?php endfor; ?>
            </select>
        </div>

        <button type="submit"
            name="add-to-cart"
            value="<?php echo esc_attr($product->get_id()); ?>"
            class="single_add_to_cart_button button alt"
            style="width: 100%; padding: 12px;">
            <i class="fa-solid fa-cart-plus"></i>
            <?php echo esc_html($product->single_add_to_cart_text()); ?>
        </button>

        <?php do_action('woocommerce_after_add_to_cart_button'); ?>
    </form>

<?php
    do_action('woocommerce_after_add_to_cart_form');
}

/**
 * Ensure WooCommerce recognizes our custom product type template
 */
add_filter('woocommerce_locate_template', 'sco_package_locate_template', 10, 3);
function sco_package_locate_template($template, $template_name, $template_path)
{
    if ($template_name === 'single-product/add-to-cart/sco_package.php') {
        return $template;
    }
    return $template;
}
