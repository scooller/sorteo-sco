<?php
/**
 * Theme Compatibility Helper
 * Detecta si el tema Bootstrap está activo y adapta el HTML según corresponda
 * 
 * @package Sorteo_SCO
 * @since 1.9.9
 */

if (!defined('ABSPATH')) exit;

class Sorteo_Theme_Compat {
    
    /**
     * Verifica si el tema Bootstrap está activo
     * 
     * @return bool
     */
    public static function is_bootstrap_theme_active() {
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
    public static function get_classes($context, $variant = 'default') {
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
    public static function get_data_toggle($toggle = 'dropdown') {
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
    public static function render_quantity_selector($product_id, $add_url, $max_qty = 10) {
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
                                <i class="fa-solid fa-dice"></i> <?php echo esc_html( $q . ' ' . __('paq', 'sorteo-sco') ); ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>
            <?php
        } else {
            // Versión HTML nativo con select
            ?>
            <form class="sco-package-add-to-cart" method="post" enctype="multipart/form-data">
                <div class="quantity-selector">
                    <label for="sco_qty_<?php echo esc_attr($product_id); ?>" style="display:block;margin-bottom:5px;">
                        <?php echo esc_html__('Cantidad:', 'sorteo-sco'); ?>
                    </label>
                    <select name="quantity" id="sco_qty_<?php echo esc_attr($product_id); ?>" style="width:60px;margin-right:10px;">
                        <?php for ($q = 1; $q <= $max_qty; $q++): ?>
                            <option value="<?php echo esc_attr($q); ?>"><?php echo esc_html($q); ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="button" 
                            class="button add_to_cart_button ajax_add_to_cart product_type_sco_package" 
                            data-product_id="<?php echo esc_attr($product_id); ?>" 
                            data-url="<?php echo esc_url($add_url); ?>">
                        <?php echo esc_html__('Agregar al carrito', 'sorteo-sco'); ?>
                    </button>
                </div>
            </form>
            <script>
            (function() {
                var btn = document.querySelector('.product_type_sco_package[data-product_id="<?php echo esc_js($product_id); ?>"]');
                var select = document.getElementById('sco_qty_<?php echo esc_js($product_id); ?>');
                if (btn && select) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var qty = select.value;
                        var url = this.getAttribute('data-url');
                        var productId = this.getAttribute('data-product_id');
                        
                        jQuery(this).attr('data-quantity', qty);
                    });
                }
            })();
            </script>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Agrega CSS fallback para temas no-Bootstrap
     */
    public static function add_fallback_styles() {
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
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
                align-items: center;
                flex-wrap: wrap;
            }
            </style>
            <?php
        }
    }
}

// Agregar estilos fallback en frontend
add_action('wp_head', array('Sorteo_Theme_Compat', 'add_fallback_styles'));
