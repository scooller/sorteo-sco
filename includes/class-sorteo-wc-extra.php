<?php

/**
 * Sorteo WooCommerce Extra - Configuraciones adicionales de WooCommerce
 *
 * @package Sorteo_SCO
 * @since 1.9.17
 */

if (!defined('ABSPATH')) {
    exit;
}

class Sorteo_WC_Extra
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_submenu_page'], 20);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_sorteo_update_prices', [$this, 'ajax_update_prices']);
        add_action('wp_ajax_nopriv_sorteo_update_prices', [$this, 'ajax_update_prices']);
        add_action('wp_ajax_sorteo_update_prices_batch', [$this, 'ajax_update_prices_batch']);
        add_action('wp_ajax_sorteo_export_sales', [$this, 'ajax_export_sales']);
        add_action('wp_ajax_sorteo_fix_package_duplicates', [$this, 'ajax_fix_package_duplicates']);
        add_action('wp_ajax_sorteo_get_package_metrics', [$this, 'ajax_get_package_metrics']);
        add_action('wp_ajax_sorteo_get_reserved_stock', [$this, 'ajax_get_reserved_stock']);
        add_action('wp_ajax_sorteo_release_reservation', [$this, 'ajax_release_reservation']);
        add_action('wp_ajax_sorteo_restore_orphan_stock', [$this, 'ajax_restore_orphan_stock']);
    }

    public function add_submenu_page()
    {
        add_submenu_page(
            'sorteo-sco-settings',
            __('Extra WooCommerce', 'sorteo-sco'),
            __('Extra WooCommerce', 'sorteo-sco'),
            'manage_options',
            'sorteo-wc-extra',
            [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        register_setting('sorteo_wc_extra_group', 'sorteo_wc_price_update_log');
        register_setting('sorteo_wc_stock_group', 'sorteo_wc_enable_stock_management');
        register_setting('sorteo_wc_stock_group', 'sorteo_wc_stock_product_types');
        register_setting('sorteo_wc_stock_group', 'sorteo_wc_enable_stock_reservation');
        register_setting('sorteo_wc_stock_group', 'sorteo_wc_product_order_by');
        register_setting('sorteo_wc_stock_group', 'sorteo_wc_product_order_dir');
    }

    public function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'price_updater';
?>
        <div class="wrap">
            <h1><?php esc_html_e('Extra WooCommerce', 'sorteo-sco'); ?></h1>
            <p><?php esc_html_e('Herramientas adicionales para gestionar productos de WooCommerce.', 'sorteo-sco'); ?></p>

            <h2 class="nav-tab-wrapper">
                <a href="?page=sorteo-wc-extra&tab=price_updater"
                    class="nav-tab <?php echo $active_tab === 'price_updater' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Actualizar Precios', 'sorteo-sco'); ?>
                </a>
                <a href="?page=sorteo-wc-extra&tab=export_sales"
                    class="nav-tab <?php echo $active_tab === 'export_sales' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Exportar Ventas', 'sorteo-sco'); ?>
                </a>
                <a href="?page=sorteo-wc-extra&tab=package_metrics"
                    class="nav-tab <?php echo $active_tab === 'package_metrics' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Métricas Paquetes', 'sorteo-sco'); ?>
                </a>
                <a href="?page=sorteo-wc-extra&tab=stock_config"
                    class="nav-tab <?php echo $active_tab === 'stock_config' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Stock y Orden', 'sorteo-sco'); ?>
                </a>
                <a href="?page=sorteo-wc-extra&tab=reserved_stock"
                    class="nav-tab <?php echo $active_tab === 'reserved_stock' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Monitor Reservas', 'sorteo-sco'); ?>
                </a>
            </h2>

            <div class="tab-content" style="background:#fff;border:1px solid #ccd0d4;border-top:none;padding:20px;margin-bottom:20px;">
                <?php
                if ($active_tab === 'price_updater') {
                    $this->render_price_updater_tab();
                } elseif ($active_tab === 'export_sales') {
                    $this->render_export_sales_tab();
                } elseif ($active_tab === 'package_metrics') {
                    $this->render_package_metrics_tab();
                } elseif ($active_tab === 'stock_config') {
                    $this->render_stock_config_tab();
                } elseif ($active_tab === 'reserved_stock') {
                    $this->render_reserved_stock_tab();
                }
                ?>
            </div>
        </div>
    <?php
    }

    private function render_price_updater_tab()
    {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
    ?>
        <h2><?php esc_html_e('Actualización Masiva de Precios por Categoría', 'sorteo-sco'); ?></h2>
        <p><?php esc_html_e('Actualiza precios de productos de una categoría específica, con opción de excluir productos de otras categorías.', 'sorteo-sco'); ?></p>

        <form id="sorteo-price-update-form" method="post" style="max-width:800px;">
            <?php wp_nonce_field('sorteo_price_update', 'sorteo_price_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="target_category"><?php esc_html_e('Categoría Objetivo', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <select name="target_category" id="target_category" class="regular-text" required>
                            <option value=""><?php esc_html_e('-- Seleccionar Categoría --', 'sorteo-sco'); ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>">
                                    <?php echo esc_html($cat->name); ?> (<?php echo esc_html($cat->count); ?> productos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Categoría de productos a actualizar.', 'sorteo-sco'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="exclude_categories"><?php esc_html_e('Excluir Categorías', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <select name="exclude_categories[]" id="exclude_categories" class="regular-text" multiple style="min-height:120px;">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Productos que TAMBIÉN pertenezcan a estas categorías NO serán actualizados (mantén Ctrl/Cmd para seleccionar múltiples).', 'sorteo-sco'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="update_type"><?php esc_html_e('Tipo de Actualización', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <select name="update_type" id="update_type" class="regular-text" required>
                            <option value="percentage"><?php esc_html_e('Porcentaje (%)', 'sorteo-sco'); ?></option>
                            <option value="fixed"><?php esc_html_e('Cantidad Fija ($)', 'sorteo-sco'); ?></option>
                            <option value="set"><?php esc_html_e('Establecer Precio Exacto', 'sorteo-sco'); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="update_value"><?php esc_html_e('Valor', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <input type="number" step="0.01" name="update_value" id="update_value" class="regular-text" required />
                        <p class="description">
                            <span id="value_hint_percentage" style="display:none;"><?php esc_html_e('Ejemplo: 10 para aumentar 10%, -15 para reducir 15%.', 'sorteo-sco'); ?></span>
                            <span id="value_hint_fixed" style="display:none;"><?php esc_html_e('Ejemplo: 50 para aumentar $50, -20 para reducir $20.', 'sorteo-sco'); ?></span>
                            <span id="value_hint_set" style="display:none;"><?php esc_html_e('Ejemplo: 99.99 para establecer precio exacto.', 'sorteo-sco'); ?></span>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="apply_to"><?php esc_html_e('Aplicar a', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" name="apply_to" value="regular" checked />
                            <?php esc_html_e('Precio Regular', 'sorteo-sco'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="apply_to" value="sale" />
                            <?php esc_html_e('Precio de Oferta', 'sorteo-sco'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="apply_to" value="both" />
                            <?php esc_html_e('Ambos', 'sorteo-sco'); ?>
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="dry_run"><?php esc_html_e('Modo Prueba', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="dry_run" id="dry_run" value="1" checked />
                            <?php esc_html_e('Simular sin aplicar cambios (recomendado primero)', 'sorteo-sco'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary" id="btn-update-prices">
                    <?php esc_html_e('Ejecutar Actualización', 'sorteo-sco'); ?>
                </button>
                <span class="spinner" style="float:none;margin:0 10px;"></span>
            </p>
        </form>

        <div id="price-update-progress" style="margin-top:30px;display:none;">
            <h3><?php esc_html_e('Progreso de Actualización', 'sorteo-sco'); ?></h3>
            <div style="background:#f5f5f5;border:1px solid #ddd;border-radius:4px;padding:15px;">
                <div style="display:flex;align-items:center;gap:15px;">
                    <div style="flex:1;">
                        <div style="background:#e0e0e0;border-radius:4px;height:25px;overflow:hidden;">
                            <div id="progress-bar" style="background:#4caf50;height:100%;width:0%;transition:width 0.3s ease;display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:bold;"></div>
                        </div>
                    </div>
                    <div style="min-width:80px;text-align:right;">
                        <span id="progress-text">0/0</span>
                    </div>
                </div>
            </div>
        </div>

        <div id="price-update-results" style="margin-top:30px;"></div>

        <script>
            jQuery(document).ready(function($) {
                // Mostrar hints dinámicos
                $('#update_type').on('change', function() {
                    var type = $(this).val();
                    $('[id^="value_hint_"]').hide();
                    $('#value_hint_' + type).show();
                }).trigger('change');

                // AJAX submit
                $('#sorteo-price-update-form').on('submit', function(e) {
                    e.preventDefault();

                    var $form = $(this);
                    var $btn = $('#btn-update-prices');
                    var $spinner = $form.find('.spinner');
                    var $results = $('#price-update-results');
                    var $progress = $('#price-update-progress');

                    // Obtener datos del formulario
                    var formData = {
                        target_category: $form.find('[name="target_category"]').val(),
                        exclude_categories: $form.find('[name="exclude_categories[]"]').val() || [],
                        update_type: $form.find('[name="update_type"]').val(),
                        update_value: parseFloat($form.find('[name="update_value"]').val()),
                        apply_to: $form.find('[name="apply_to"]:checked').val(),
                        dry_run: $form.find('[name="dry_run"]').is(':checked')
                    };

                    $btn.prop('disabled', true);
                    $spinner.addClass('is-active');
                    $results.html('');
                    $progress.show();
                    $('#progress-bar').css('width', '0%');
                    $('#progress-text').text('0/0');

                    // Primero, contar total de productos
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sorteo_update_prices',
                            nonce: '<?php echo wp_create_nonce('sorteo_price_update_ajax'); ?>',
                            target_category: formData.target_category,
                            exclude_categories: formData.exclude_categories,
                            update_type: formData.update_type,
                            update_value: formData.update_value,
                            apply_to: formData.apply_to,
                            dry_run: formData.dry_run ? '1' : '0',
                            step: 'count'
                        },
                        success: function(response) {
                            if (!response.success) {
                                $btn.prop('disabled', false);
                                $spinner.removeClass('is-active');
                                $results.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                                return;
                            }

                            var totalProducts = response.data.total;
                            var processed = 0;
                            var batchSize = 50;
                            var allProducts = [];

                            function processBatch(batch) {
                                $.ajax({
                                    url: ajaxurl,
                                    type: 'POST',
                                    data: {
                                        action: 'sorteo_update_prices_batch',
                                        nonce: '<?php echo wp_create_nonce('sorteo_price_update_batch'); ?>',
                                        target_category: formData.target_category,
                                        exclude_categories: formData.exclude_categories,
                                        update_type: formData.update_type,
                                        update_value: formData.update_value,
                                        apply_to: formData.apply_to,
                                        dry_run: formData.dry_run ? '1' : '0',
                                        offset: batch * batchSize,
                                        limit: batchSize
                                    },
                                    success: function(batchResponse) {
                                        if (batchResponse.success) {
                                            allProducts = allProducts.concat(batchResponse.data.products);
                                            processed += batchResponse.data.count;

                                            // Actualizar barra de progreso
                                            var percentage = Math.min((processed / totalProducts) * 100, 100);
                                            $('#progress-bar').css('width', percentage + '%').text(Math.round(percentage) + '%');
                                            $('#progress-text').text(processed + '/' + totalProducts);

                                            // Si hay más productos, procesar siguiente lote
                                            if (processed < totalProducts) {
                                                processBatch(batch + 1);
                                            } else {
                                                // Completado
                                                $btn.prop('disabled', false);
                                                $spinner.removeClass('is-active');
                                                $progress.hide();

                                                // Mostrar resultados
                                                var html = '<div class="notice notice-success"><p><strong>' + (formData.dry_run ? '<?php esc_html_e('Simulación completada (no se aplicaron cambios)', 'sorteo-sco'); ?>' : '<?php esc_html_e('Precios actualizados correctamente', 'sorteo-sco'); ?>') + '</strong></p>';
                                                html += '<h3><?php esc_html_e('Productos Procesados:', 'sorteo-sco'); ?> ' + allProducts.length + '</h3>';
                                                html += '<table class="wp-list-table widefat fixed striped" style="max-width:900px;">';
                                                html += '<thead><tr>';
                                                html += '<th><?php esc_html_e('ID', 'sorteo-sco'); ?></th>';
                                                html += '<th><?php esc_html_e('Producto', 'sorteo-sco'); ?></th>';
                                                html += '<th><?php esc_html_e('Precio Anterior', 'sorteo-sco'); ?></th>';
                                                html += '<th><?php esc_html_e('Precio Nuevo', 'sorteo-sco'); ?></th>';
                                                html += '</tr></thead><tbody>';

                                                allProducts.forEach(function(prod) {
                                                    html += '<tr>';
                                                    html += '<td>' + prod.id + '</td>';
                                                    html += '<td><a href="post.php?post=' + prod.id + '&action=edit" target="_blank">' + prod.name + '</a></td>';
                                                    html += '<td>' + prod.old_price + '</td>';
                                                    html += '<td><strong>' + prod.new_price + '</strong></td>';
                                                    html += '</tr>';
                                                });

                                                html += '</tbody></table>';

                                                if (formData.dry_run) {
                                                    html += '<p><strong><?php esc_html_e('MODO PRUEBA: No se aplicaron cambios. Desactiva "Modo Prueba" para aplicar.', 'sorteo-sco'); ?></strong></p>';
                                                }

                                                html += '</div>';
                                                $results.html(html);
                                            }
                                        } else {
                                            $btn.prop('disabled', false);
                                            $spinner.removeClass('is-active');
                                            $progress.hide();
                                            $results.html('<div class="notice notice-error"><p>' + batchResponse.data.message + '</p></div>');
                                        }
                                    },
                                    error: function() {
                                        $btn.prop('disabled', false);
                                        $spinner.removeClass('is-active');
                                        $progress.hide();
                                        $results.html('<div class="notice notice-error"><p><?php esc_html_e('Error de comunicación con el servidor.', 'sorteo-sco'); ?></p></div>');
                                    }
                                });
                            }

                            // Iniciar procesamiento por lotes
                            processBatch(0);
                        },
                        error: function() {
                            $btn.prop('disabled', false);
                            $spinner.removeClass('is-active');
                            $results.html('<div class="notice notice-error"><p><?php esc_html_e('Error de comunicación con el servidor.', 'sorteo-sco'); ?></p></div>');
                        }
                    });
                });
            });
        </script>
    <?php
    }

    private function render_export_sales_tab()
    {
    ?>
        <h2><?php esc_html_e('Exportar Ventas con Desglose de Paquetes', 'sorteo-sco'); ?></h2>
        <p><?php esc_html_e('Exporta todas las ventas. Si hay paquetes SCO, desglosa cada producto del paquete como una línea separada con advertencia de duplicados.', 'sorteo-sco'); ?></p>

        <form id="sorteo-export-form" method="post" style="max-width:900px;">
            <?php wp_nonce_field('sorteo_export_sales', 'sorteo_export_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Rango de Fechas', 'sorteo-sco'); ?></th>
                    <td>
                        <label><?php esc_html_e('Desde:', 'sorteo-sco'); ?></label>
                        <input type="date" id="export_date_from" value="" />
                        <label style="margin-left:20px;"><?php esc_html_e('Hasta:', 'sorteo-sco'); ?></label>
                        <input type="date" id="export_date_to" value="" />
                        <p class="description"><?php esc_html_e('Deja vacío para exportar todos los pedidos', 'sorteo-sco'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Estados de Pedido', 'sorteo-sco'); ?></th>
                    <td>
                        <label><input type="checkbox" id="export_status_completed" value="completed" checked /> <?php esc_html_e('Completado', 'sorteo-sco'); ?></label><br>
                        <label><input type="checkbox" id="export_status_processing" value="processing" checked /> <?php esc_html_e('Procesando', 'sorteo-sco'); ?></label><br>
                        <label><input type="checkbox" id="export_status_pending" value="pending" /> <?php esc_html_e('Pendiente de Pago', 'sorteo-sco'); ?></label><br>
                        <label><input type="checkbox" id="export_status_cancelled" value="cancelled" /> <?php esc_html_e('Cancelado', 'sorteo-sco'); ?></label>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Opciones', 'sorteo-sco'); ?></th>
                    <td>
                        <label><input type="checkbox" id="export_show_duplicates" value="1" checked /> <?php esc_html_e('Mostrar advertencia de duplicados', 'sorteo-sco'); ?></label>
                        <p class="description"><?php esc_html_e('Si está activado, marcará en rojo los productos que aparecen en múltiples paquetes en la misma venta', 'sorteo-sco'); ?></p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" id="btn-export-sales" class="button button-primary">
                    <?php esc_html_e('Generar y Descargar CSV', 'sorteo-sco'); ?>
                </button>
                <span id="export-spinner" class="spinner" style="display:none;float:none;margin-top:5px;"></span>
            </p>

            <hr style="margin:30px 0;">

            <h3><?php esc_html_e('Regenerar Productos Duplicados', 'sorteo-sco'); ?></h3>
            <p><?php esc_html_e('Esta herramienta busca paquetes con productos duplicados (mismo SKU aparece múltiples veces) y los reemplaza por productos diferentes.', 'sorteo-sco'); ?></p>

            <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:15px 0;">
                <strong>⚠️ <?php esc_html_e('Advertencia:', 'sorteo-sco'); ?></strong>
                <ul style="margin:10px 0 0 20px;">
                    <li><?php esc_html_e('Solo procesa pedidos en estado "Procesando" o "Completado"', 'sorteo-sco'); ?></li>
                    <li><?php esc_html_e('Genera una nota en cada pedido modificado', 'sorteo-sco'); ?></li>
                    <li><?php esc_html_e('No afecta el stock (solo actualiza la composición del paquete)', 'sorteo-sco'); ?></li>
                </ul>
            </div>

            <p>
                <button type="button" id="btn-fix-duplicates" class="button button-secondary">
                    <?php esc_html_e('🔄 Buscar y Regenerar Duplicados', 'sorteo-sco'); ?>
                </button>
                <span id="fix-spinner" class="spinner" style="display:none;float:none;margin-top:5px;"></span>
            </p>

            <div id="fix-result" style="margin-top:20px;"></div>
            <div id="export-result" style="margin-top:20px;"></div>
        </form>

        <script>
            jQuery(document).ready(function($) {
                // Establecer fechas por defecto (últimos 30 días)
                var today = new Date();
                var thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);

                $('#export_date_to').val(today.toISOString().split('T')[0]);
                $('#export_date_from').val(thirtyDaysAgo.toISOString().split('T')[0]);

                $('#btn-export-sales').on('click', function(e) {
                    e.preventDefault();

                    var $btn = $(this);
                    var $spinner = $('#export-spinner');
                    var $result = $('#export-result');

                    $btn.prop('disabled', true);
                    $spinner.show();
                    $result.html('');

                    var dateFrom = $('#export_date_from').val();
                    var dateTo = $('#export_date_to').val();
                    var statuses = [];

                    $('input[id^="export_status_"]:checked').each(function() {
                        statuses.push($(this).val());
                    });

                    var showDuplicates = $('#export_show_duplicates').is(':checked') ? 1 : 0;

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sorteo_export_sales',
                            nonce: '<?php echo wp_create_nonce('sorteo_export_sales'); ?>',
                            date_from: dateFrom,
                            date_to: dateTo,
                            statuses: statuses,
                            show_duplicates: showDuplicates
                        },
                        xhrFields: {
                            responseType: 'blob'
                        },
                        success: function(response, status, xhr) {
                            // Crear descarga
                            var filename = 'export_ventas_' + new Date().toISOString().split('T')[0] + '.csv';
                            var url = window.URL.createObjectURL(response);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = filename;
                            document.body.appendChild(a);
                            a.click();
                            window.URL.revokeObjectURL(url);
                            a.remove();

                            $btn.prop('disabled', false);
                            $spinner.hide();
                            $result.html('<div class="notice notice-success is-dismissible"><p><?php esc_html_e('✓ Archivo descargado correctamente', 'sorteo-sco'); ?></p></div>');
                        },
                        error: function() {
                            $btn.prop('disabled', false);
                            $spinner.hide();
                            $result.html('<div class="notice notice-error"><p><?php esc_html_e('Error al generar el archivo. Intenta de nuevo.', 'sorteo-sco'); ?></p></div>');
                        }
                    });
                });

                // Botón de regenerar duplicados
                $('#btn-fix-duplicates').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('<?php esc_attr_e('¿Estás seguro de que deseas regenerar productos duplicados en paquetes? Esto modificará los pedidos existentes.', 'sorteo-sco'); ?>')) {
                        return;
                    }

                    var $btn = $(this);
                    var $spinner = $('#fix-spinner');
                    var $result = $('#fix-result');

                    $btn.prop('disabled', true);
                    $spinner.show();
                    $result.html('<div class="notice notice-info"><p><?php esc_html_e('🔍 Buscando paquetes con duplicados...', 'sorteo-sco'); ?></p></div>');

                    var dateFrom = $('#export_date_from').val();
                    var dateTo = $('#export_date_to').val();
                    var statuses = [];

                    $('input[id^="export_status_"]:checked').each(function() {
                        statuses.push($(this).val());
                    });

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'sorteo_fix_package_duplicates',
                            nonce: '<?php echo wp_create_nonce('sorteo_fix_duplicates'); ?>',
                            date_from: dateFrom,
                            date_to: dateTo,
                            statuses: statuses
                        },
                        success: function(response) {
                            $btn.prop('disabled', false);
                            $spinner.hide();

                            if (response.success) {
                                var data = response.data;
                                var html = '<div class="notice notice-success"><p><strong>✓ ' + data.message + '</strong></p>';

                                if (data.total_fixed > 0) {
                                    html += '<h3>📋 Pedidos Modificados: ' + data.total_fixed + '</h3>';
                                    html += '<table class="wp-list-table widefat striped" style="max-width:100%;">';
                                    html += '<thead><tr>';
                                    html += '<th>Pedido</th>';
                                    html += '<th>Paquete</th>';
                                    html += '<th>Duplicados Encontrados</th>';
                                    html += '<th>Productos Reemplazados</th>';
                                    html += '<th>Detalles</th>';
                                    html += '</tr></thead><tbody>';

                                    data.log.forEach(function(entry) {
                                        html += '<tr>';
                                        html += '<td><a href="post.php?post=' + entry.order_id + '&action=edit" target="_blank">#' + entry.order_number + '</a></td>';
                                        html += '<td>' + entry.package_name + '</td>';
                                        html += '<td>' + entry.duplicates_count + '</td>';
                                        html += '<td>' + entry.replaced_count + '</td>';
                                        html += '<td><ul style="margin:0;">';

                                        entry.changes.forEach(function(change) {
                                            html += '<li><strong>Reemplazado:</strong> ' + change.old_name + ' (SKU: ' + change.old_sku + ')<br>';
                                            html += '<strong>Por:</strong> ' + change.new_name + ' (SKU: ' + change.new_sku + ')</li>';
                                        });

                                        html += '</ul></td>';
                                        html += '</tr>';
                                    });

                                    html += '</tbody></table>';
                                }

                                html += '</div>';
                                $result.html(html);
                            } else {
                                $result.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false);
                            $spinner.hide();
                            $result.html('<div class="notice notice-error"><p><?php esc_html_e('Error al procesar la solicitud.', 'sorteo-sco'); ?></p></div>');
                        }
                    });
                });
            });
        </script>
    <?php
    }

    private function render_package_metrics_tab()
    {
    ?>
        <h2><?php esc_html_e('Métricas de Productos Paquete (sco_package)', 'sorteo-sco'); ?></h2>
        <p><?php esc_html_e('Estadísticas de ventas, stock y emails de paquetes sorteo.', 'sorteo-sco'); ?></p>

        <div id="package-metrics-loading" style="text-align:center;padding:40px;">
            <span class="spinner is-active" style="float:none;"></span>
            <p><?php esc_html_e('Cargando métricas...', 'sorteo-sco'); ?></p>
        </div>

        <div id="package-metrics-content" style="display:none;"></div>

        <script>
            jQuery(document).ready(function($) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'sorteo_get_package_metrics',
                        nonce: '<?php echo wp_create_nonce('sorteo_package_metrics'); ?>'
                    },
                    success: function(response) {
                        $('#package-metrics-loading').hide();

                        if (response.success) {
                            var data = response.data;
                            var html = '';

                            // Cards de resumen
                            html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:30px;">';
                            html += '<div style="background:#f0f6fc;border-left:4px solid #0073aa;padding:20px;border-radius:4px;">';
                            html += '<h3 style="margin:0 0 10px;color:#0073aa;"><?php esc_html_e('Paquetes Vendidos', 'sorteo-sco'); ?></h3>';
                            html += '<p style="font-size:32px;margin:0;font-weight:bold;">' + data.total_sold + '</p>';
                            html += '</div>';

                            html += '<div style="background:#f7fcf0;border-left:4px solid #46b450;padding:20px;border-radius:4px;">';
                            html += '<h3 style="margin:0 0 10px;color:#46b450;"><?php esc_html_e('Productos Descontados', 'sorteo-sco'); ?></h3>';
                            html += '<p style="font-size:32px;margin:0;font-weight:bold;">' + data.total_components_reduced + '</p>';
                            html += '</div>';

                            html += '<div style="background:#fffbf0;border-left:4px solid #f56e28;padding:20px;border-radius:4px;">';
                            html += '<h3 style="margin:0 0 10px;color:#f56e28;"><?php esc_html_e('Emails Enviados', 'sorteo-sco'); ?></h3>';
                            html += '<p style="font-size:32px;margin:0;font-weight:bold;">' + data.total_emails_sent + '</p>';
                            html += '</div>';

                            html += '<div style="background:#fcf0f1;border-left:4px solid #dc3232;padding:20px;border-radius:4px;">';
                            html += '<h3 style="margin:0 0 10px;color:#dc3232;"><?php esc_html_e('Ingresos Totales', 'sorteo-sco'); ?></h3>';
                            html += '<p style="font-size:24px;margin:0;font-weight:bold;">' + data.total_revenue + '</p>';
                            html += '</div>';
                            html += '</div>';

                            // Tabla de pedidos
                            html += '<h3><?php esc_html_e('Últimos Pedidos con Paquetes', 'sorteo-sco'); ?></h3>';
                            html += '<table class="wp-list-table widefat fixed striped">';
                            html += '<thead><tr>';
                            html += '<th><?php esc_html_e('Pedido', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Paquete', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Cantidad', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Componentes', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Stock Reducido', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Email Enviado', 'sorteo-sco'); ?></th>';
                            html += '<th><?php esc_html_e('Fecha', 'sorteo-sco'); ?></th>';
                            html += '</tr></thead><tbody>';

                            if (data.orders.length > 0) {
                                data.orders.forEach(function(order) {
                                    html += '<tr>';
                                    html += '<td><a href="post.php?post=' + order.order_id + '&action=edit" target="_blank">#' + order.order_number + '</a></td>';
                                    html += '<td>' + order.package_name + '</td>';
                                    html += '<td>' + order.quantity + '</td>';
                                    html += '<td>' + order.components_count + '</td>';
                                    html += '<td>' + (order.stock_reduced ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#dc3232;">✗</span>') + '</td>';
                                    html += '<td>' + (order.email_sent ? '<span style="color:#46b450;">✓</span>' : '<span style="color:#dc3232;">✗</span>') + '</td>';
                                    html += '<td>' + order.date + '</td>';
                                    html += '</tr>';
                                });
                            } else {
                                html += '<tr><td colspan="7" style="text-align:center;"><?php esc_html_e('No hay pedidos con paquetes.', 'sorteo-sco'); ?></td></tr>';
                            }

                            html += '</tbody></table>';

                            $('#package-metrics-content').html(html).fadeIn();
                        } else {
                            $('#package-metrics-content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>').fadeIn();
                        }
                    },
                    error: function() {
                        $('#package-metrics-loading').hide();
                        $('#package-metrics-content').html('<div class="notice notice-error"><p><?php esc_html_e('Error al cargar métricas.', 'sorteo-sco'); ?></p></div>').fadeIn();
                    }
                });
            });
        </script>
    <?php
    }

    public function ajax_update_prices()
    {
        check_ajax_referer('sorteo_price_update_ajax', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado', 'sorteo-sco')]);
        }

        $step = isset($_POST['step']) ? sanitize_text_field($_POST['step']) : '';
        $target_category = isset($_POST['target_category']) ? intval($_POST['target_category']) : 0;

        if (!$target_category) {
            wp_send_json_error(['message' => __('Categoría objetivo requerida', 'sorteo-sco')]);
        }

        // Solo contar si step='count'
        if ($step === 'count') {
            $args = [
                'post_type' => 'product',
                'posts_per_page' => -1,
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $target_category
                    ]
                ],
                'fields' => 'ids'
            ];

            $product_ids = get_posts($args);
            $count = 0;

            // Procesar categorías excluidas
            $exclude_categories = isset($_POST['exclude_categories']) ? (array) $_POST['exclude_categories'] : [];
            $exclude_categories = array_map('intval', $exclude_categories);

            foreach ($product_ids as $product_id) {
                $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
                $has_excluded = array_intersect($product_categories, $exclude_categories);
                if (empty($has_excluded)) {
                    $count++;
                }
            }

            wp_send_json_success(['total' => $count]);
        }
    }

    public function ajax_update_prices_batch()
    {
        check_ajax_referer('sorteo_price_update_batch', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado', 'sorteo-sco')]);
        }

        $target_category = isset($_POST['target_category']) ? intval($_POST['target_category']) : 0;
        $exclude_categories = isset($_POST['exclude_categories']) ? (array) $_POST['exclude_categories'] : [];
        $exclude_categories = array_map('intval', $exclude_categories);
        $update_type = isset($_POST['update_type']) ? sanitize_text_field($_POST['update_type']) : 'percentage';
        $update_value = isset($_POST['update_value']) ? floatval($_POST['update_value']) : 0;
        $apply_to = isset($_POST['apply_to']) ? sanitize_text_field($_POST['apply_to']) : 'regular';
        $dry_run = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        if (!$target_category) {
            wp_send_json_error(['message' => __('Categoría objetivo requerida', 'sorteo-sco')]);
        }

        // Obtener productos de la categoría con paginación
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'offset' => 0,
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $target_category
                ]
            ],
            'fields' => 'ids'
        ];

        $all_product_ids = get_posts($args);
        $filtered_products = [];

        // Filtrar por categorías excluidas
        foreach ($all_product_ids as $product_id) {
            $product_categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            $has_excluded = array_intersect($product_categories, $exclude_categories);
            if (empty($has_excluded)) {
                $filtered_products[] = $product_id;
            }
        }

        // Aplicar offset y limit
        $batch_products = array_slice($filtered_products, $offset, $limit);
        $processed_products = [];

        foreach ($batch_products as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product) continue;

            $old_regular = $product->get_regular_price();
            $old_sale = $product->get_sale_price();

            $new_regular = $old_regular;
            $new_sale = $old_sale;

            // Calcular nuevos precios
            if ($apply_to === 'regular' || $apply_to === 'both') {
                if ($old_regular) {
                    $new_regular = $this->calculate_new_price($old_regular, $update_type, $update_value);
                }
            }

            if ($apply_to === 'sale' || $apply_to === 'both') {
                if ($old_sale) {
                    $new_sale = $this->calculate_new_price($old_sale, $update_type, $update_value);
                }
            }

            // Aplicar cambios si no es dry run
            if (!$dry_run) {
                if ($apply_to === 'regular' || $apply_to === 'both') {
                    $product->set_regular_price($new_regular);
                }
                if ($apply_to === 'sale' || $apply_to === 'both') {
                    if ($new_sale) {
                        $product->set_sale_price($new_sale);
                    }
                }
                $product->save();
            }

            $processed_products[] = [
                'id' => $product_id,
                'name' => $product->get_name(),
                'old_price' => $apply_to === 'sale' ? wc_price($old_sale) : wc_price($old_regular),
                'new_price' => $apply_to === 'sale' ? wc_price($new_sale) : wc_price($new_regular)
            ];
        }

        wp_send_json_success([
            'count' => count($batch_products),
            'products' => $processed_products
        ]);
    }

    private function calculate_new_price($old_price, $type, $value)
    {
        switch ($type) {
            case 'percentage':
                return max(0, $old_price + ($old_price * $value / 100));
            case 'fixed':
                return max(0, $old_price + $value);
            case 'set':
                return max(0, $value);
            default:
                return $old_price;
        }
    }

    public function ajax_get_package_metrics()
    {
        check_ajax_referer('sorteo_package_metrics', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado', 'sorteo-sco')]);
        }

        global $wpdb;

        // Obtener pedidos con paquetes
        $orders_query = "
			SELECT p.ID as order_id, p.post_date
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_id
			INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
			WHERE p.post_type = 'shop_order'
			AND oim.meta_key = '_sco_package'
			AND p.post_status IN ('wc-processing', 'wc-completed')
			GROUP BY p.ID
			ORDER BY p.post_date DESC
			LIMIT 50
		";

        $orders = $wpdb->get_results($orders_query);

        $total_sold = 0;
        $total_components_reduced = 0;
        $total_emails_sent = 0;
        $total_revenue = 0;
        $orders_data = [];

        foreach ($orders as $order_row) {
            $order = wc_get_order($order_row->order_id);
            if (!$order) continue;

            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product || $product->get_type() !== 'sco_package') continue;

                $package_data = $item->get_meta('_sco_package', true);
                $stock_reduced = $item->get_meta('_sco_pkg_stock_reduced', true) === 'yes';
                $email_sent_key = '_sco_pkg_components_email_sent_' . $item_id;
                $email_sent = $order->get_meta($email_sent_key) === 'yes';

                $total_sold += $item->get_quantity();
                $total_revenue += $item->get_total();

                if ($stock_reduced) {
                    $components_count = is_array($package_data) && isset($package_data['components'])
                        ? count($package_data['components'])
                        : 0;
                    $total_components_reduced += $components_count * $item->get_quantity();
                }

                if ($email_sent) {
                    $total_emails_sent++;
                }

                $orders_data[] = [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                    'package_name' => $product->get_name(),
                    'quantity' => $item->get_quantity(),
                    'components_count' => is_array($package_data) && isset($package_data['components'])
                        ? count($package_data['components'])
                        : 0,
                    'stock_reduced' => $stock_reduced,
                    'email_sent' => $email_sent,
                    'date' => $order->get_date_created()->date_i18n('d/m/Y H:i')
                ];
            }
        }

        wp_send_json_success([
            'total_sold' => $total_sold,
            'total_components_reduced' => $total_components_reduced,
            'total_emails_sent' => $total_emails_sent,
            'total_revenue' => wc_price($total_revenue),
            'orders' => $orders_data
        ]);
    }

    private function render_stock_config_tab()
    {
        // Procesar formulario
        if (isset($_POST['sorteo_stock_config_nonce']) && wp_verify_nonce($_POST['sorteo_stock_config_nonce'], 'sorteo_stock_config')) {
            $enable_stock = isset($_POST['enable_stock_management']) ? '1' : '0';
            $enable_reservation = isset($_POST['enable_stock_reservation']) ? '1' : '0';
            $product_types = isset($_POST['stock_product_types']) ? array_map('sanitize_text_field', $_POST['stock_product_types']) : [];
            $order_by = isset($_POST['product_order_by']) ? sanitize_text_field($_POST['product_order_by']) : 'date';
            $order_dir = isset($_POST['product_order_dir']) ? sanitize_text_field($_POST['product_order_dir']) : 'DESC';

            update_option('sorteo_wc_enable_stock_management', $enable_stock);
            update_option('sorteo_wc_enable_stock_reservation', $enable_reservation);
            update_option('sorteo_wc_stock_product_types', $product_types);
            update_option('sorteo_wc_product_order_by', $order_by);
            update_option('sorteo_wc_product_order_dir', $order_dir);

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Configuración guardada correctamente.', 'sorteo-sco') . '</p></div>';
        }

        $enable_stock = get_option('sorteo_wc_enable_stock_management', '0');
        $enable_reservation = get_option('sorteo_wc_enable_stock_reservation', '1');
        $selected_types = get_option('sorteo_wc_stock_product_types', []);
        $order_by = get_option('sorteo_wc_product_order_by', 'date');
        $order_dir = get_option('sorteo_wc_product_order_dir', 'DESC');

        if (!is_array($selected_types)) {
            $selected_types = [];
        }

        $product_types = [
            'simple' => __('Simple', 'sorteo-sco'),
            'variable' => __('Variable', 'sorteo-sco'),
            'grouped' => __('Agrupado', 'sorteo-sco'),
            'external' => __('Externo/Afiliado', 'sorteo-sco'),
            'sco_package' => __('Paquete SCO', 'sorteo-sco'),
        ];

        // Agregar tipos virtuales/descargables como filtros adicionales
        $type_filters = [
            'virtual' => __('Virtual', 'sorteo-sco'),
            'downloadable' => __('Descargable', 'sorteo-sco'),
        ];

        $order_options = [
            'date' => __('Más Recientes', 'sorteo-sco'),
            'rand' => __('Orden Aleatorio', 'sorteo-sco'),
            'title' => __('Nombre (A-Z)', 'sorteo-sco'),
            'price' => __('Precio', 'sorteo-sco'),
            'popularity' => __('Popularidad', 'sorteo-sco'),
            'rating' => __('Calificación', 'sorteo-sco'),
        ];
    ?>
        <h2><?php esc_html_e('Configuración de Stock y Ordenamiento', 'sorteo-sco'); ?></h2>
        <p><?php esc_html_e('Configura la gestión de stock y el orden de visualización de productos en WooCommerce con compatibilidad HPOS.', 'sorteo-sco'); ?></p>

        <form method="post" style="max-width:900px;">
            <?php wp_nonce_field('sorteo_stock_config', 'sorteo_stock_config_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_stock_management">
                            <?php esc_html_e('Habilitar Gestión de Stock', 'sorteo-sco'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_stock_management" id="enable_stock_management" value="1" <?php checked($enable_stock, '1'); ?> />
                            <?php esc_html_e('El plugin se hará cargo de la gestión de stock', 'sorteo-sco'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Cuando está activo, el plugin gestionará el stock de los tipos de producto seleccionados abajo con compatibilidad HPOS completa.', 'sorteo-sco'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="enable_stock_reservation">
                            <?php esc_html_e('Reserva de Stock', 'sorteo-sco'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_stock_reservation" id="enable_stock_reservation" value="1" <?php checked($enable_reservation, '1'); ?> />
                            <?php esc_html_e('Reservar stock al crear el pedido (Recomendado)', 'sorteo-sco'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('Previene race conditions: el stock se reserva cuando el usuario hace checkout, no cuando completa el pago. Si el pedido se cancela/falla, el stock se libera automáticamente.', 'sorteo-sco'); ?>
                        </p>
                        <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:10px;margin-top:10px;">
                            <strong><?php esc_html_e('¿Qué previene esto?', 'sorteo-sco'); ?></strong><br>
                            <span style="font-size:13px;">
                                <?php esc_html_e('Si Usuario A agrega producto al carrito y Usuario B compra un paquete con ese producto, Usuario A aún puede completar su compra porque el stock se reservó al hacer checkout.', 'sorteo-sco'); ?>
                            </span>
                        </div>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Tipos de Producto', 'sorteo-sco'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php esc_html_e('Seleccionar tipos de producto', 'sorteo-sco'); ?></span>
                            </legend>

                            <p><strong><?php esc_html_e('Tipos Base:', 'sorteo-sco'); ?></strong></p>
                            <?php foreach ($product_types as $type => $label): ?>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" name="stock_product_types[]" value="<?php echo esc_attr($type); ?>"
                                        <?php checked(in_array($type, $selected_types)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>

                            <p style="margin-top:15px;"><strong><?php esc_html_e('Filtros Adicionales:', 'sorteo-sco'); ?></strong></p>
                            <?php foreach ($type_filters as $filter => $label): ?>
                                <label style="display:block;margin-bottom:8px;">
                                    <input type="checkbox" name="stock_product_types[]" value="<?php echo esc_attr($filter); ?>"
                                        <?php checked(in_array($filter, $selected_types)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>

                            <p class="description" style="margin-top:10px;">
                                <?php esc_html_e('Selecciona qué tipos de producto serán gestionados por el plugin. Los filtros "Virtual" y "Descargable" se aplican adicionalmente a los tipos base.', 'sorteo-sco'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Compatibilidad HPOS', 'sorteo-sco'); ?>
                    </th>
                    <td>
                        <p style="padding:12px;background:#e7f5e7;border-left:4px solid #4caf50;margin:0;">
                            <span class="dashicons dashicons-yes-alt" style="color:#4caf50;"></span>
                            <strong><?php esc_html_e('HPOS Habilitado', 'sorteo-sco'); ?></strong><br>
                            <span style="font-size:13px;color:#666;">
                                <?php esc_html_e('Este plugin es totalmente compatible con High-Performance Order Storage (HPOS) en cualquier tema de WordPress/WooCommerce.', 'sorteo-sco'); ?>
                            </span>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Estado Actual', 'sorteo-sco'); ?>
                    </th>
                    <td>
                        <?php
                        $wc_using_hpos = false;
                        if (class_exists('\\Automattic\\WooCommerce\\Utilities\\OrderUtil')) {
                            $wc_using_hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                        }
                        ?>
                        <p style="padding:12px;background:#f0f0f1;border-left:4px solid #72aee6;margin:0;">
                            <span class="dashicons dashicons-info" style="color:#72aee6;"></span>
                            <strong><?php esc_html_e('WooCommerce HPOS:', 'sorteo-sco'); ?></strong>
                            <?php if ($wc_using_hpos): ?>
                                <span style="color:#4caf50;"><?php esc_html_e('Activo ✓', 'sorteo-sco'); ?></span>
                            <?php else: ?>
                                <span style="color:#ff9800;"><?php esc_html_e('No activo (usando posts tradicional)', 'sorteo-sco'); ?></span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>

                <tr style="border-top:2px solid #ccd0d4;">
                    <th scope="row" colspan="2" style="padding:15px 0 0 0;background-color:#f9f9f9;">
                        <h3 style="margin:0 0 15px 0;color:#333;">
                            <span class="dashicons dashicons-sort" style="vertical-align:middle;"></span>
                            <?php esc_html_e('Ordenamiento de Productos', 'sorteo-sco'); ?>
                        </h3>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="product_order_by"><?php esc_html_e('Ordenar Por', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <select name="product_order_by" id="product_order_by" class="regular-text">
                            <?php foreach ($order_options as $value => $label): ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($order_by, $value); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Selecciona cómo se ordenarán los productos en el catálogo.', 'sorteo-sco'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="product_order_dir"><?php esc_html_e('Dirección de Orden', 'sorteo-sco'); ?></label>
                    </th>
                    <td>
                        <label style="margin-right:30px;">
                            <input type="radio" name="product_order_dir" value="ASC" <?php checked($order_dir, 'ASC'); ?> />
                            <?php esc_html_e('Ascendente (A→Z, 0→9, antiguo→nuevo)', 'sorteo-sco'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="product_order_dir" value="DESC" <?php checked($order_dir, 'DESC'); ?> />
                            <?php esc_html_e('Descendente (Z→A, 9→0, nuevo→antiguo)', 'sorteo-sco'); ?>
                        </label>
                        <p class="description" style="margin-top:10px;">
                            <?php esc_html_e('Nota: Los productos destacados siempre aparecerán primero, sin importar el ordenamiento.', 'sorteo-sco'); ?>
                        </p>
                    </td>
                </tr>

                <tr style="border-top:2px solid #ccd0d4;">
                    <th scope="row" colspan="2" style="padding:15px 0 0 0;background-color:#f9f9f9;">
                        <h3 style="margin:0 0 15px 0;color:#333;">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            <?php esc_html_e('Restaurar Stock Huérfano', 'sorteo-sco'); ?>
                        </h3>
                    </th>
                </tr>

                <tr>
                    <th scope="row">
                        <?php esc_html_e('Productos Huérfanos', 'sorteo-sco'); ?>
                    </th>
                    <td>
                        <p><?php esc_html_e('Productos que tienen "Gestionar stock" desactivado pero deberían tenerlo según la configuración actual.', 'sorteo-sco'); ?></p>

                        <p>
                            <label for="orphan-stock-category"><?php esc_html_e('Filtrar por categoría:', 'sorteo-sco'); ?></label><br>
                            <select id="orphan-stock-category" style="width:250px;">
                                <option value=""><?php esc_html_e('Todas las categorías', 'sorteo-sco'); ?></option>
                                <?php
                                $categories = get_terms([
                                    'taxonomy' => 'product_cat',
                                    'hide_empty' => false,
                                ]);
                                if (!is_wp_error($categories)) {
                                    foreach ($categories as $cat) {
                                        echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" id="orphan-stock-out-of-stock" value="1" />
                                <?php esc_html_e('Solo productos agotados', 'sorteo-sco'); ?>
                            </label>
                            <span class="description" style="display:block;margin-top:5px;"><?php esc_html_e('Filtra solo productos sin stock disponible', 'sorteo-sco'); ?></span>
                        </p>

                        <p>
                            <label for="orphan-stock-quantity"><?php esc_html_e('Cantidad de stock a establecer:', 'sorteo-sco'); ?></label><br>
                            <input type="number" id="orphan-stock-quantity" min="0" step="1" value="0" style="width:100px;" />
                            <span class="description"><?php esc_html_e('Stock inicial para productos restaurados', 'sorteo-sco'); ?></span>
                        </p>

                        <button type="button" id="btn-restore-orphan-stock" class="button button-secondary">
                            <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                            <?php esc_html_e('Restaurar Stock a Productos Huérfanos', 'sorteo-sco'); ?>
                        </button>
                        <span class="spinner" style="float:none;margin:0 0 0 10px;"></span>
                        <p class="description" style="margin-top:10px;">
                            <?php esc_html_e('Esto habilitará "Gestionar stock" en productos que coincidan con los tipos seleccionados pero actualmente no lo tengan activado.', 'sorteo-sco'); ?>
                        </p>
                        <div id="orphan-stock-result" style="margin-top:15px;"></div>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Guardar Configuración', 'sorteo-sco'); ?>" />
            </p>
        </form>

        <script>
            jQuery(document).ready(function($) {
                $('#btn-restore-orphan-stock').on('click', function(e) {
                    e.preventDefault();

                    if (!confirm('<?php esc_attr_e('¿Estás seguro de que deseas restaurar stock a productos huérfanos? Esto habilitará "Gestionar stock" en productos que coincidan con tu configuración.', 'sorteo-sco'); ?>')) {
                        return;
                    }

                    var $btn = $(this);
                    var $spinner = $btn.next('.spinner');
                    var $result = $('#orphan-stock-result');
                    var stockQuantity = parseInt($('#orphan-stock-quantity').val()) || 0;
                    var categoryId = $('#orphan-stock-category').val();
                    var outOfStockOnly = $('#orphan-stock-out-of-stock').is(':checked') ? 1 : 0;

                    if (stockQuantity < 0) {
                        alert('<?php esc_attr_e('La cantidad de stock debe ser un número positivo.', 'sorteo-sco'); ?>');
                        return;
                    }

                    $btn.prop('disabled', true);
                    $spinner.css('visibility', 'visible');
                    $result.html('');

                    $.post(ajaxurl, {
                        action: 'sorteo_restore_orphan_stock',
                        nonce: '<?php echo wp_create_nonce('sorteo_restore_orphan_stock'); ?>',
                        stock_quantity: stockQuantity,
                        category_id: categoryId,
                        out_of_stock_only: outOfStockOnly
                    }, function(response) {
                        $btn.prop('disabled', false);
                        $spinner.css('visibility', 'hidden');

                        if (response.success) {
                            $result.html(
                                '<div class="notice notice-success inline"><p><strong>' +
                                response.data.message +
                                '</strong></p>' +
                                '<ul style="margin-left:20px;">' +
                                '<li><?php esc_html_e('Productos procesados:', 'sorteo-sco'); ?> ' + response.data.processed + '</li>' +
                                '<li><?php esc_html_e('Stock restaurado:', 'sorteo-sco'); ?> ' + response.data.restored + '</li>' +
                                '<li><?php esc_html_e('Ya gestionados:', 'sorteo-sco'); ?> ' + response.data.already_managing + '</li>' +
                                '</ul></div>'
                            );
                        } else {
                            $result.html(
                                '<div class="notice notice-error inline"><p>' +
                                (response.data.message || '<?php esc_attr_e('Error al restaurar stock', 'sorteo-sco'); ?>') +
                                '</p></div>'
                            );
                        }
                    });
                });
            });
        </script>

        <hr style="margin:30px 0;">

        <div style="background:#fff9e6;border-left:4px solid #ffc107;padding:15px;margin-top:20px;">
            <h3 style="margin-top:0;">
                <span class="dashicons dashicons-warning" style="color:#ffc107;"></span>
                <?php esc_html_e('Información Importante', 'sorteo-sco'); ?>
            </h3>
            <ul style="margin-left:20px;line-height:1.8;">
                <li><?php esc_html_e('La gestión de stock solo se aplicará a los tipos de producto seleccionados.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Los productos tipo "Paquete SCO" siempre gestionan el stock de sus componentes internos.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('El plugin respeta las configuraciones individuales de "Gestionar stock" de cada producto.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Compatible con HPOS y con el sistema tradicional de posts de WooCommerce.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Si usas filtros "Virtual" o "Descargable", el plugin solo afectará productos que cumplan AMBAS condiciones (tipo base + filtro).', 'sorteo-sco'); ?></li>
            </ul>
        </div>

        <div style="background:#e7f5ff;border-left:4px solid #2196f3;padding:15px;margin-top:20px;">
            <h3 style="margin-top:0;">
                <span class="dashicons dashicons-info" style="color:#2196f3;"></span>
                <?php esc_html_e('Sistema de Reserva de Stock', 'sorteo-sco'); ?>
            </h3>
            <p><strong><?php esc_html_e('¿Cómo funciona?', 'sorteo-sco'); ?></strong></p>
            <ol style="margin-left:20px;line-height:1.8;">
                <li><strong><?php esc_html_e('Checkout:', 'sorteo-sco'); ?></strong> <?php esc_html_e('El stock se RESERVA (no se descuenta todavía)', 'sorteo-sco'); ?></li>
                <li><strong><?php esc_html_e('Pago pendiente:', 'sorteo-sco'); ?></strong> <?php esc_html_e('El stock permanece reservado por X minutos (configurable en WooCommerce)', 'sorteo-sco'); ?></li>
                <li><strong><?php esc_html_e('Pago completado:', 'sorteo-sco'); ?></strong> <?php esc_html_e('El stock se DESCUENTA de forma permanente', 'sorteo-sco'); ?></li>
                <li><strong><?php esc_html_e('Cancelado/Fallido:', 'sorteo-sco'); ?></strong> <?php esc_html_e('El stock se LIBERA automáticamente', 'sorteo-sco'); ?></li>
            </ol>

            <p><strong><?php esc_html_e('Ejemplo del problema que soluciona:', 'sorteo-sco'); ?></strong></p>
            <table style="width:100%;border-collapse:collapse;margin-top:10px;">
                <tr style="background:#f0f0f0;">
                    <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Sin Reserva', 'sorteo-sco'); ?></th>
                    <th style="padding:8px;border:1px solid #ddd;text-align:left;"><?php esc_html_e('Con Reserva (Recomendado)', 'sorteo-sco'); ?></th>
                </tr>
                <tr>
                    <td style="padding:8px;border:1px solid #ddd;vertical-align:top;">
                        ❌ Usuario A agrega Sticker (stock: 1) al carrito<br>
                        ❌ Usuario B compra paquete con ese Sticker<br>
                        ❌ Stock = 0<br>
                        ❌ Usuario A intenta pagar → <strong style="color:red;">ERROR</strong>
                    </td>
                    <td style="padding:8px;border:1px solid #ddd;vertical-align:top;">
                        ✅ Usuario A agrega Sticker al carrito<br>
                        ✅ Usuario A hace checkout → Stock reservado<br>
                        ✅ Usuario B intenta comprar → <em>Stock no disponible</em><br>
                        ✅ Usuario A completa pago → <strong style="color:green;">ÉXITO</strong>
                    </td>
                </tr>
            </table>

            <p style="margin-top:15px;">
                <strong><?php esc_html_e('Tiempo de reserva:', 'sorteo-sco'); ?></strong>
                <?php esc_html_e('WooCommerce mantiene las reservas por 60 minutos por defecto. Puedes ajustar esto en:', 'sorteo-sco'); ?>
                <code>WooCommerce → Ajustes → Productos → Inventario → "Mantener stock (minutos)"</code>
            </p>
        </div>
    <?php
    }

    private function render_reserved_stock_tab()
    {
    ?>
        <h2><?php esc_html_e('Monitor de Reservas de Stock', 'sorteo-sco'); ?></h2>
        <p><?php esc_html_e('Visualiza y gestiona los productos que actualmente tienen stock reservado.', 'sorteo-sco'); ?></p>

        <div style="margin-bottom:20px;">
            <button type="button" class="button button-primary" id="btn-refresh-reservations">
                <span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
                <?php esc_html_e('Actualizar', 'sorteo-sco'); ?>
            </button>
            <button type="button" class="button button-danger" id="btn-release-all-reservations" style="background:#dc3545;border-color:#dc3545;color:white;">
                <span class="dashicons dashicons-trash" style="vertical-align:middle;"></span>
                <?php esc_html_e('Liberar Todas', 'sorteo-sco'); ?>
            </button>
            <span class="spinner" style="float:none;margin-left:10px;"></span>

            <div style="float:right;">
                <label><?php esc_html_e('Filtrar:', 'sorteo-sco'); ?></label>
                <button type="button" class="button filter-status" data-status="all" style="margin-left:5px;"><?php esc_html_e('Todas', 'sorteo-sco'); ?></button>
                <button type="button" class="button filter-status" data-status="active"><?php esc_html_e('Activas', 'sorteo-sco'); ?></button>
                <button type="button" class="button filter-status" data-status="expiring"><?php esc_html_e('Por Expirar', 'sorteo-sco'); ?></button>
                <button type="button" class="button filter-status" data-status="expired"><?php esc_html_e('Expiradas', 'sorteo-sco'); ?></button>
            </div>
        </div>

        <table class="wp-list-table widefat striped" id="reserved-stock-table" style="margin-top:20px;">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" id="select-all-reservations" /></th>
                    <th><?php esc_html_e('Producto', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Pedido', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Estado', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Cantidad', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Reservado', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Expira en', 'sorteo-sco'); ?></th>
                    <th><?php esc_html_e('Acciones', 'sorteo-sco'); ?></th>
                </tr>
            </thead>
            <tbody id="reserved-stock-list">
                <tr>
                    <td colspan="8" style="text-align:center;padding:40px;">
                        <em><?php esc_html_e('Cargando...', 'sorteo-sco'); ?></em>
                    </td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:20px;padding:15px;background:#f0f0f1;border-left:4px solid #72aee6;">
            <strong><?php esc_html_e('Información:', 'sorteo-sco'); ?></strong><br>
            <ul style="margin-left:20px;margin-top:10px;">
                <li><?php esc_html_e('Las reservas se crean automáticamente cuando un cliente hace checkout.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Las reservas expiran después del tiempo configurado en WooCommerce (default: 60 minutos).', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Si liberas una reserva, el stock volverá a estar disponible inmediatamente.', 'sorteo-sco'); ?></li>
                <li><?php esc_html_e('Usa "Liberar Todas" solo si hay reservas defectuosas que bloquean ventas.', 'sorteo-sco'); ?></li>
            </ul>
        </div>

        <script type="text/javascript">
            jQuery(function($) {
                var ajaxurl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                var nonce = '<?php echo wp_create_nonce('sorteo_reservations'); ?>';

                function loadReservations() {
                    $.post(ajaxurl, {
                        action: 'sorteo_get_reserved_stock',
                        nonce: nonce
                    }, function(response) {
                        if (response.success) {
                            window.lastReservationsData = response.data;
                            renderReservationsTable(response.data);
                        } else {
                            $('#reserved-stock-list').html(
                                '<tr><td colspan="8" style="text-align:center;color:red;">' +
                                response.data.message +
                                '</td></tr>'
                            );
                        }
                        $('.spinner').css('visibility', 'hidden');
                    });
                }

                var currentFilter = 'all';

                function renderReservationsTable(data) {
                    if (data.length === 0) {
                        $('#reserved-stock-list').html(
                            '<tr><td colspan="8" style="text-align:center;padding:40px;">' +
                            '<em><?php esc_html_e('No hay reservas activas', 'sorteo-sco'); ?></em>' +
                            '</td></tr>'
                        );
                        return;
                    }

                    var html = '';
                    var visibleCount = 0;
                    $.each(data, function(i, item) {
                        // Aplicar filtro
                        var shouldShow = true;
                        if (currentFilter === 'active' && (item.expires_soon || item.expired)) {
                            shouldShow = false;
                        } else if (currentFilter === 'expiring' && !item.expires_soon) {
                            shouldShow = false;
                        } else if (currentFilter === 'expired' && !item.expired) {
                            shouldShow = false;
                        }

                        if (!shouldShow) return;
                        visibleCount++;

                        var expiresClass = 'color:';
                        if (item.expires_soon) {
                            expiresClass += 'orange';
                        } else if (item.expired) {
                            expiresClass += 'red';
                        } else {
                            expiresClass += 'green';
                        }

                        var statusBadge = '<span class="order-status" style="padding:3px 8px;border-radius:3px;font-size:11px;font-weight:bold;background:' + item.order_status_color + ';color:white;">' + item.order_status_name + '</span>';

                        html += '<tr data-reservation-id="' + item.id + '">' +
                            '<td><input type="checkbox" class="select-reservation" value="' + item.id + '" /></td>' +
                            '<td><strong>' + escapeHtml(item.product_name) + '</strong><br><small>#' + item.product_id + '</small></td>' +
                            '<td><a href="' + item.order_edit_url + '" target="_blank">#' + item.order_id + '</a></td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td style="text-align:center;">' + item.quantity + '</td>' +
                            '<td>' + item.reserved_at + '</td>' +
                            '<td style="' + expiresClass + '"><strong>' + item.expires_in + '</strong></td>' +
                            '<td>' +
                            '<button type="button" class="button button-small btn-release-single" data-id="' + item.id + '">' +
                            '<?php esc_attr_e('Liberar', 'sorteo-sco'); ?>' +
                            '</button>' +
                            '</td>' +
                            '</tr>';
                    });

                    if (visibleCount === 0) {
                        html = '<tr><td colspan="8" style="text-align:center;padding:40px;"><em><?php esc_html_e('No hay reservas con este filtro', 'sorteo-sco'); ?></em></td></tr>';
                    }

                    $('#reserved-stock-list').html(html);

                    // Bind single release buttons
                    $('.btn-release-single').on('click', function(e) {
                        e.preventDefault();
                        var id = $(this).data('id');
                        if (confirm('<?php esc_attr_e('¿Seguro que deseas liberar esta reserva?', 'sorteo-sco'); ?>')) {
                            releaseReservation(id);
                        }
                    });
                }

                function releaseReservation(reservationId) {
                    $('.spinner').css('visibility', 'visible');
                    $.post(ajaxurl, {
                        action: 'sorteo_release_reservation',
                        nonce: nonce,
                        reservation_id: reservationId
                    }, function(response) {
                        if (response.success) {
                            loadReservations();
                        } else {
                            alert(response.data.message);
                            $('.spinner').css('visibility', 'hidden');
                        }
                    });
                }

                function escapeHtml(unsafe) {
                    return unsafe
                        .replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;");
                }

                // Event listeners
                $('#btn-refresh-reservations').on('click', function(e) {
                    e.preventDefault();
                    $('.spinner').css('visibility', 'visible');
                    loadReservations();
                });

                $('#btn-release-all-reservations').on('click', function(e) {
                    e.preventDefault();
                    if (confirm('<?php esc_attr_e('¿Seguro que deseas liberar TODAS las reservas? Esta acción no se puede deshacer.', 'sorteo-sco'); ?>')) {
                        $('.spinner').css('visibility', 'visible');
                        $.post(ajaxurl, {
                            action: 'sorteo_release_reservation',
                            nonce: nonce,
                            release_all: true
                        }, function(response) {
                            if (response.success) {
                                alert('<?php esc_attr_e('Todas las reservas fueron liberadas.', 'sorteo-sco'); ?>');
                                loadReservations();
                            } else {
                                alert(response.data.message);
                                $('.spinner').css('visibility', 'hidden');
                            }
                        });
                    }
                });

                // Select all checkbox
                $('#select-all-reservations').on('change', function() {
                    $('.select-reservation').prop('checked', $(this).is(':checked'));
                });

                // Filter buttons
                $('.filter-status').on('click', function() {
                    $('.filter-status').removeClass('button-primary');
                    $(this).addClass('button-primary');
                    currentFilter = $(this).data('status');

                    // Re-render con el filtro actual sin hacer nueva petición AJAX
                    if (window.lastReservationsData) {
                        renderReservationsTable(window.lastReservationsData);
                    }
                });

                // Marcar botón "Todas" como activo por defecto
                $('.filter-status[data-status="all"]').addClass('button-primary');

                // Initial load
                loadReservations();
            });
        </script>
<?php
    }

    public function ajax_get_reserved_stock()
    {
        check_ajax_referer('sorteo_reservations', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permiso denegado', 'sorteo-sco')]);
        }

        global $wpdb;

        // Usar tabla nativa de WooCommerce (si existe)
        $table = $wpdb->prefix . 'wc_reserved_stock';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

        $reservations = array();
        if ($table_exists) {
            $reservations = $wpdb->get_results(
                "SELECT CONCAT(rs.order_id, '-', rs.product_id) as id, rs.product_id, rs.order_id, rs.expires, rs.stock_quantity
                 FROM {$table} rs
                 ORDER BY rs.expires ASC",
                ARRAY_A
            );
        }

        $data = array();
        $current_time = current_time('timestamp', true);

        // 1) Reservas creadas por WooCommerce (tabla wc_reserved_stock)
        foreach ($reservations as $res) {
            $product = wc_get_product($res['product_id']);
            $order = wc_get_order($res['order_id']);

            if (!$product || !$order) {
                continue;
            }

            // Obtener cantidad del item del pedido
            $quantity = 0;
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $res['product_id']) {
                    $quantity = $item->get_quantity();
                    break;
                }
            }

            if ($quantity <= 0) {
                $quantity = isset($res['stock_quantity']) ? (int) $res['stock_quantity'] : 0;
            }

            if ($quantity <= 0) {
                continue;
            }

            // Fecha de reserva = fecha del pedido
            $order_date = $order->get_date_created();
            $reserved_at = $order_date ? $order_date->format('Y-m-d H:i:s') : current_time('mysql');

            $expires_timestamp = strtotime($res['expires'] . ' UTC');
            $seconds_remaining = $expires_timestamp - $current_time;
            $expires_soon = $seconds_remaining < 600; // menos de 10 minutos
            $expired = $seconds_remaining < 0;

            if ($seconds_remaining > 0) {
                $minutes = floor($seconds_remaining / 60);
                $hours = floor($minutes / 60);

                if ($hours > 0) {
                    $expires_in = $hours . 'h ' . ($minutes % 60) . 'm';
                } else {
                    $expires_in = $minutes . 'm';
                }
            } else {
                $expires_in = __('Expirado', 'sorteo-sco');
            }

            // Obtener estado del pedido y su color
            $order_status = $order->get_status();
            $order_status_name = wc_get_order_status_name($order_status);
            $status_colors = [
                'pending' => '#f0ad4e',
                'processing' => '#5bc0de',
                'on-hold' => '#999',
                'completed' => '#5cb85c',
                'cancelled' => '#d9534f',
                'refunded' => '#777',
                'failed' => '#d9534f'
            ];
            $order_status_color = isset($status_colors[$order_status]) ? $status_colors[$order_status] : '#999';

            $data[] = [
                'id' => $res['id'],
                'product_id' => $res['product_id'],
                'product_name' => $product->get_name(),
                'order_id' => $res['order_id'],
                'order_edit_url' => $order->get_edit_order_url(),
                'order_status' => $order_status,
                'order_status_name' => $order_status_name,
                'order_status_color' => $order_status_color,
                'quantity' => $quantity,
                'reserved_at' => date_i18n('d/m/Y H:i', strtotime($reserved_at)),
                'expires_in' => $expires_in,
                'expires_soon' => $expires_soon,
                'expired' => $expired
            ];
        }

        // 2) Reservas en carrito (transient bootstrap_theme_stock_reservations) para mostrar componentes + sueltos
        $transient_reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
        $transient_expiry = 5 * MINUTE_IN_SECONDS; // 5 minutos
        $transient_changed = false;

        foreach ($transient_reservations as $session_id => $items) {
            foreach ($items as $product_id => $entry) {
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }

                $quantity = isset($entry['quantity']) ? (int) $entry['quantity'] : 0;
                if ($quantity <= 0) {
                    unset($transient_reservations[$session_id][$product_id]);
                    if (empty($transient_reservations[$session_id])) {
                        unset($transient_reservations[$session_id]);
                    }
                    $transient_changed = true;
                    continue;
                }

                $timestamp = isset($entry['timestamp']) ? (int) $entry['timestamp'] : $current_time;
                $expires_timestamp = $timestamp + $transient_expiry;
                $seconds_remaining = $expires_timestamp - $current_time;
                $expires_soon = $seconds_remaining < 600;
                $expired = $seconds_remaining < 0;

                if ($seconds_remaining > 0) {
                    $minutes = floor($seconds_remaining / 60);
                    $hours = floor($minutes / 60);

                    if ($hours > 0) {
                        $expires_in = $hours . 'h ' . ($minutes % 60) . 'm';
                    } else {
                        $expires_in = $minutes . 'm';
                    }
                } else {
                    $expires_in = __('Expirado', 'sorteo-sco');
                }

                $session_label = substr((string) $session_id, 0, 8);
                $source = isset($entry['source']) ? $entry['source'] : 'cart';

                $data[] = array(
                    'id' => 'cart|' . rawurlencode((string) $session_id) . '|' . (int) $product_id,
                    'product_id' => (int) $product_id,
                    'product_name' => $product->get_name(),
                    'order_id' => __('Carrito', 'sorteo-sco') . ' (' . $session_label . ')',
                    'order_edit_url' => '#',
                    'order_status' => 'cart',
                    'order_status_name' => __('En carrito', 'sorteo-sco') . ' / ' . $source,
                    'order_status_color' => '#6c757d',
                    'quantity' => $quantity,
                    'reserved_at' => date_i18n('d/m/Y H:i', $timestamp),
                    'expires_in' => $expires_in,
                    'expires_soon' => $expires_soon,
                    'expired' => $expired
                );
            }
        }

        if ($transient_changed) {
            set_transient('bootstrap_theme_stock_reservations', $transient_reservations, 5 * MINUTE_IN_SECONDS);
        }

        wp_send_json_success($data);
    }

    public function ajax_release_reservation()
    {
        check_ajax_referer('sorteo_reservations', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permiso denegado', 'sorteo-sco')]);
        }

        global $wpdb;

        $table = $wpdb->prefix . 'wc_reserved_stock';

        if (isset($_POST['release_all']) && $_POST['release_all'] === 'true') {
            // Liberar todas las reservas
            $result = $wpdb->query("DELETE FROM {$table}");

            // Limpiar reservas en carrito (transient)
            delete_transient('bootstrap_theme_stock_reservations');

            if ($result !== false) {
                wp_send_json_success(['message' => __('Todas las reservas fueron liberadas.', 'sorteo-sco')]);
            } else {
                wp_send_json_error(['message' => __('Error al liberar reservas.', 'sorteo-sco')]);
            }
        } elseif (isset($_POST['reservation_id']) && !empty($_POST['reservation_id'])) {
            // Formatos: "order_id-product_id" (tabla wc_reserved_stock) o "cart|session|product"
            $reservation_id = sanitize_text_field($_POST['reservation_id']);

            if (strpos($reservation_id, 'cart|') === 0) {
                $parts = explode('|', $reservation_id);
                if (count($parts) !== 3) {
                    wp_send_json_error(['message' => __('Identificador de reserva inválido.', 'sorteo-sco')]);
                }

                $session_id = rawurldecode($parts[1]);
                $product_id = intval($parts[2]);

                $reservations = get_transient('bootstrap_theme_stock_reservations') ?: array();
                if (isset($reservations[$session_id][$product_id])) {
                    unset($reservations[$session_id][$product_id]);
                    if (empty($reservations[$session_id])) {
                        unset($reservations[$session_id]);
                    }
                    set_transient('bootstrap_theme_stock_reservations', $reservations, 5 * MINUTE_IN_SECONDS);
                }

                wp_send_json_success(['message' => __('Reserva liberada.', 'sorteo-sco')]);
            }

            // Liberar reserva individual usando formato "order_id-product_id"
            $parts = explode('-', $reservation_id);

            if (count($parts) !== 2) {
                wp_send_json_error(['message' => __('Identificador de reserva inválido.', 'sorteo-sco')]);
            }

            $order_id = intval($parts[0]);
            $product_id = intval($parts[1]);

            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$table} WHERE order_id = %d AND product_id = %d",
                $order_id,
                $product_id
            ));

            if ($result !== false) {
                wp_send_json_success(['message' => __('Reserva liberada.', 'sorteo-sco')]);
            } else {
                wp_send_json_error(['message' => __('Error al liberar la reserva.', 'sorteo-sco')]);
            }
        } else {
            wp_send_json_error(['message' => __('Parámetros inválidos.', 'sorteo-sco')]);
        }
    }

    public function ajax_restore_orphan_stock()
    {
        check_ajax_referer('sorteo_restore_orphan_stock', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permiso denegado', 'sorteo-sco')]);
        }

        // Obtener cantidad de stock desde el request
        $stock_quantity = isset($_POST['stock_quantity']) ? absint($_POST['stock_quantity']) : 0;
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $out_of_stock_only = isset($_POST['out_of_stock_only']) ? absint($_POST['out_of_stock_only']) : 0;

        // Obtener configuración actual
        $enabled = get_option('sorteo_wc_enable_stock_management', '0');
        $selected_types = get_option('sorteo_wc_stock_product_types', []);

        if ($enabled !== '1' || empty($selected_types)) {
            wp_send_json_error(['message' => __('La gestión de stock debe estar habilitada y tener tipos seleccionados.', 'sorteo-sco')]);
        }

        // Obtener todos los productos
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        );

        // Filtrar por categoría si se especificó
        if ($category_id > 0) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ),
            );
        }

        // Filtrar por stock agotado si se especificó
        if ($out_of_stock_only) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '=',
                ),
                array(
                    'key' => '_stock',
                    'value' => 0,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ),
            );
        }

        $product_ids = get_posts($args);

        $processed = 0;
        $restored = 0;
        $already_managing = 0;

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                continue;
            }

            // Excluir productos tipo sco_package
            if ($product->get_type() === 'sco_package') {
                continue;
            }

            $processed++;

            // Verificar si el producto debería ser gestionado según configuración
            if (!function_exists('sorteo_sco_should_manage_stock')) {
                continue;
            }

            $should_manage = sorteo_sco_should_manage_stock($product);

            // Si se filtró por "solo agotados", forzar actualización de stock
            if ($out_of_stock_only) {
                $product->set_manage_stock(true);
                $product->set_stock_quantity($stock_quantity);
                $product->save();
                $restored++;
                continue;
            }

            // Si el producto ya está gestionando stock, contar
            if ($product->managing_stock()) {
                if ($should_manage) {
                    $already_managing++;
                }
                continue;
            }

            // Si debería gestionar pero no lo hace, restaurar
            if ($should_manage) {
                $product->set_manage_stock(true);

                // Si no tiene stock definido, establecer la cantidad especificada
                if ($product->get_stock_quantity() === null || $product->get_stock_quantity() === '') {
                    $product->set_stock_quantity($stock_quantity);
                }

                $product->save();
                $restored++;
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Stock restaurado correctamente. %d productos actualizados.', 'sorteo-sco'),
                $restored
            ),
            'processed' => $processed,
            'restored' => $restored,
            'already_managing' => $already_managing
        ]);
    }

    /**
     * Exportar ventas con desglose de paquetes y detección de duplicados
     */
    public function ajax_export_sales()
    {
        check_ajax_referer('sorteo_export_sales', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permiso denegado', 'sorteo-sco')]);
        }

        $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
        $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
        $statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', $_POST['statuses']) : array('completed', 'processing');
        $show_duplicates = isset($_POST['show_duplicates']) ? intval($_POST['show_duplicates']) : 1;

        // Construir query
        $args = array(
            'limit' => -1,
            'return' => 'ids',
        );

        if (!empty($statuses)) {
            $args['status'] = $statuses;
        }

        // Filtrar por rango de fechas
        if (!empty($date_from) && !empty($date_to)) {
            $args['date_created'] = date('Y-m-d', strtotime($date_from)) . '...' . date('Y-m-d', strtotime($date_to));
        } elseif (!empty($date_from)) {
            $args['date_created'] = '>=' . date('Y-m-d 00:00:00', strtotime($date_from));
        } elseif (!empty($date_to)) {
            $args['date_created'] = '<=' . date('Y-m-d 23:59:59', strtotime($date_to));
        }

        $order_ids = wc_get_orders($args);

        // PRIMER PASADA: Contar cuántas veces aparece cada SKU a través de TODOS los pedidos
        $global_sku_count = array();

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                if ($product->get_type() === 'sco_package') {
                    $pkg = $item->get_meta('_sco_package', true);

                    if (!empty($pkg) && isset($pkg['components'])) {
                        foreach ($pkg['components'] as $component) {
                            $comp_product_id = (int) $component['product_id'];
                            $comp_product = wc_get_product($comp_product_id);

                            if (!$comp_product) continue;

                            $comp_sku = $comp_product->get_sku();

                            if (!isset($global_sku_count[$comp_sku])) {
                                $global_sku_count[$comp_sku] = 0;
                            }

                            $global_sku_count[$comp_sku]++;
                        }
                    }
                } else {
                    $sku = $product->get_sku();

                    if (!isset($global_sku_count[$sku])) {
                        $global_sku_count[$sku] = 0;
                    }

                    $global_sku_count[$sku]++;
                }
            }
        }

        // SEGUNDA PASADA: Generar filas y marcar duplicados según conteo global
        $rows = array();

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                if ($product->get_type() === 'sco_package') {
                    $pkg = $item->get_meta('_sco_package', true);
                    $pkg_name = $product->get_name();

                    if (!empty($pkg) && isset($pkg['components'])) {
                        foreach ($pkg['components'] as $component) {
                            $comp_product_id = (int) $component['product_id'];
                            $comp_product = wc_get_product($comp_product_id);

                            if (!$comp_product) continue;

                            $comp_sku = $comp_product->get_sku();
                            $is_duplicate = isset($global_sku_count[$comp_sku]) && $global_sku_count[$comp_sku] > 1;

                            $rows[] = array(
                                'order_id' => $order->get_order_number(),
                                'order_date' => $order->get_date_created()->date_i18n('d/m/Y H:i'),
                                'customer' => $order->get_formatted_billing_full_name(),
                                'email' => $order->get_billing_email(),
                                'product_name' => $comp_product->get_name(),
                                'product_id' => $comp_product_id,
                                'product_sku' => $comp_sku,
                                'quantity' => 1,
                                'price' => $comp_product->get_price(),
                                'source' => 'Paquete: ' . $pkg_name,
                                'is_duplicate' => $is_duplicate ? '⚠️ SÍ' : 'No',
                            );
                        }
                    }
                } else {
                    $product_id = $product->get_id();
                    $sku = $product->get_sku();

                    $is_duplicate = isset($global_sku_count[$sku]) && $global_sku_count[$sku] > 1;

                    $rows[] = array(
                        'order_id' => $order->get_order_number(),
                        'order_date' => $order->get_date_created()->date_i18n('d/m/Y H:i'),
                        'customer' => $order->get_formatted_billing_full_name(),
                        'email' => $order->get_billing_email(),
                        'product_name' => $product->get_name(),
                        'product_id' => $product_id,
                        'product_sku' => $sku,
                        'quantity' => $item->get_quantity(),
                        'price' => $product->get_price(),
                        'source' => 'Venta directa',
                        'is_duplicate' => $is_duplicate ? '⚠️ SÍ' : 'No',
                    );
                }
            }
        }

        // Generar CSV
        $csv_headers = array(
            'Pedido',
            'Fecha',
            'Cliente',
            'Email',
            'Producto',
            'ID Producto',
            'SKU',
            'Cantidad',
            'Precio Unitario',
            'Origen',
            'Duplicado' . ($show_duplicates ? '' : ''),
        );

        $output = fopen('php://memory', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para UTF-8
        fputcsv($output, $csv_headers);

        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        // Enviar como descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export_ventas_' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($csv_content));

        echo $csv_content;
        wp_die();
    }

    /**
     * AJAX: Buscar y regenerar productos duplicados en paquetes
     */
    public function ajax_fix_package_duplicates()
    {
        check_ajax_referer('sorteo_fix_duplicates', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado', 'sorteo-sco')]);
        }

        try {

            $date_from = isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '';
            $date_to = isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '';
            $statuses = isset($_POST['statuses']) ? array_map('sanitize_text_field', (array) $_POST['statuses']) : ['processing', 'completed'];

            // Usar WP_Query directamente para mejor control de fechas
            $query_args = array(
                'post_type' => 'shop_order',
                'posts_per_page' => -1,
                'post_status' => array_map(function ($s) {
                    return 'wc-' . $s;
                }, $statuses),
                'orderby' => 'date',
                'order' => 'DESC',
            );

            // Agregar filtros de fecha
            if (!empty($date_from) || !empty($date_to)) {
                $date_query = array();
                if (!empty($date_from)) {
                    $date_query[] = array(
                        'after' => $date_from . ' 00:00:00',
                        'inclusive' => true,
                    );
                }
                if (!empty($date_to)) {
                    $date_query[] = array(
                        'before' => $date_to . ' 23:59:59',
                        'inclusive' => true,
                    );
                }
                if (!empty($date_query)) {
                    $query_args['date_query'] = $date_query;
                }
            }

            $query = new WP_Query($query_args);
            $order_ids = $query->posts;
            $orders = array();

            foreach ($order_ids as $post) {
                $order = wc_get_order($post->ID);
                if ($order) {
                    $orders[] = $order;
                }
            }

            // PRIMERA PASADA: Contar SKUs GLOBALMENTE en todos los pedidos
            $global_sku_count = array();
            $sku_locations = array(); // Map de SKU => [{'order_id', 'order_number', 'item_id', 'comp_index', 'product', ...}]

            foreach ($orders as $order) {
                $order_id = $order->get_id();
                $order_number = $order->get_order_number();

                foreach ($order->get_items() as $item_id => $item) {
                    $product = $item->get_product();
                    if (!$product || $product->get_type() !== 'sco_package') {
                        continue;
                    }

                    $pkg = $item->get_meta('_sco_package', true);
                    if (empty($pkg) || empty($pkg['components'])) {
                        continue;
                    }

                    foreach ($pkg['components'] as $comp_index => $comp) {
                        $comp_product_id = (int) $comp['product_id'];
                        $comp_product = wc_get_product($comp_product_id);

                        if (!$comp_product) {
                            continue;
                        }

                        $sku = $comp_product->get_sku();
                        if (empty($sku)) {
                            $sku = 'product_' . $comp_product_id;
                        }

                        if (!isset($global_sku_count[$sku])) {
                            $global_sku_count[$sku] = 0;
                            $sku_locations[$sku] = array();
                        }

                        $global_sku_count[$sku]++;
                        $sku_locations[$sku][] = array(
                            'order' => $order,
                            'order_id' => $order_id,
                            'order_number' => $order_number,
                            'item_id' => $item_id,
                            'item' => $item,
                            'package_product' => $product,
                            'comp_index' => $comp_index,
                            'comp_product_id' => $comp_product_id,
                            'comp_product' => $comp_product,
                        );
                    }
                }
            }

            // Encontrar SKUs que aparecen múltiples veces GLOBALMENTE
            $duplicate_skus = array_filter($global_sku_count, function ($count) {
                return $count > 1;
            });

            if (empty($duplicate_skus)) {
                wp_send_json_success([
                    'message' => __('No se encontraron productos duplicados en los pedidos seleccionados.', 'sorteo-sco'),
                    'total_fixed' => 0,
                    'log' => []
                ]);
                return;
            }

            // SEGUNDA PASADA: Regenerar instancias extras de duplicados
            $log = array();
            $total_fixed = 0;
            $processed_orders = array(); // Track de órdenes ya guardadas

            foreach ($duplicate_skus as $dup_sku => $global_count) {
                $locations = $sku_locations[$dup_sku];

                // Mantener la PRIMERA instancia, reemplazar las demás
                for ($i = 1; $i < count($locations); $i++) {
                    $loc = $locations[$i];
                    $order = $loc['order'];
                    $order_id = $loc['order_id'];
                    $order_number = $loc['order_number'];
                    $item_id = $loc['item_id'];
                    $item = $loc['item'];
                    $comp_index = $loc['comp_index'];
                    $package_product = $loc['package_product'];
                    $comp_product = $loc['comp_product'];

                    // Obtener el paquete completo
                    $pkg = $item->get_meta('_sco_package', true);
                    if (empty($pkg) || empty($pkg['components'])) {
                        continue;
                    }

                    // Buscar reemplazo que NO sea duplicado
                    $mode = get_post_meta($package_product->get_id(), '_sco_pkg_mode', true) ?: 'random';
                    $skus_to_exclude = array_keys($duplicate_skus); // Excluir todos los SKUs duplicados

                    $replacement = $this->find_replacement_product(
                        $package_product->get_id(),
                        $mode,
                        $skus_to_exclude
                    );

                    if ($replacement) {
                        $new_product = wc_get_product($replacement);
                        $old_name = $comp_product->get_name();
                        $old_sku = $dup_sku;

                        // Actualizar el componente
                        $pkg['components'][$comp_index] = array(
                            'product_id' => $replacement,
                            'qty' => 1
                        );

                        $item->update_meta_data('_sco_package', $pkg);
                        $item->save();

                        // Agregar nota al pedido (una sola vez por pedido)
                        $note = sprintf(
                            __('Duplicado regenerado - %s (SKU: %s) reemplazado por %s (SKU: %s) en paquete "%s"', 'sorteo-sco'),
                            $old_name,
                            $old_sku,
                            $new_product->get_name(),
                            $new_product->get_sku(),
                            $package_product->get_name()
                        );
                        $order->add_order_note($note);

                        // Guardar la orden solo una vez al final
                        if (!isset($processed_orders[$order_id])) {
                            $processed_orders[$order_id] = true;
                        }

                        $log[] = array(
                            'order_id' => $order_id,
                            'order_number' => $order_number,
                            'package_name' => $package_product->get_name(),
                            'duplicates_count' => $global_count,
                            'replaced_count' => 1,
                            'changes' => array(
                                array(
                                    'old_id' => $comp_product->get_id(),
                                    'old_name' => $old_name,
                                    'old_sku' => $old_sku,
                                    'new_id' => $replacement,
                                    'new_name' => $new_product->get_name(),
                                    'new_sku' => $new_product->get_sku()
                                )
                            )
                        );

                        $total_fixed++;
                    }
                }
            }

            // Guardar todas las órdenes procesadas
            foreach ($processed_orders as $order_id => $true) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $order->save();
                }
            }

            if ($total_fixed > 0) {
                wp_send_json_success([
                    'message' => sprintf(
                        __('Se regeneraron %d producto(s) duplicado(s) en %d pedido(s).', 'sorteo-sco'),
                        $total_fixed,
                        count($processed_orders)
                    ),
                    'total_fixed' => $total_fixed,
                    'log' => $log
                ]);
            } else {
                wp_send_json_success([
                    'message' => __('No se encontraron productos duplicados para regenerar.', 'sorteo-sco'),
                    'total_fixed' => 0,
                    'log' => []
                ]);
            }
        } catch (Exception $e) {
            error_log('Sorteo SCO - Error en ajax_fix_package_duplicates: ' . $e->getMessage());
            wp_send_json_error([
                'message' => __('Error: ', 'sorteo-sco') . $e->getMessage()
            ]);
        }
    }

    /**
     * Buscar producto de reemplazo para evitar duplicados
     */
    private function find_replacement_product($package_id, $mode, $exclude_skus)
    {
        if ($mode === 'manual') {
            // Obtener productos del paquete manual
            $csv = (string) get_post_meta($package_id, '_sco_pkg_products', true);
            $product_ids = array_filter(array_map('intval', explode(',', $csv)));
        } else {
            // Obtener categorías del paquete random
            $cat_csv = get_post_meta($package_id, '_sco_pkg_categories', true);
            $cat_ids = $cat_csv ? array_map('intval', explode(',', $cat_csv)) : array();

            if (empty($cat_ids)) {
                return null;
            }

            // Buscar productos en las categorías
            $args = array(
                'post_type' => 'product',
                'posts_per_page' => 100,
                'post_status' => 'publish',
                'orderby' => 'rand',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $cat_ids,
                    )
                ),
                'meta_query' => array(
                    array(
                        'key' => '_stock_status',
                        'value' => 'instock'
                    )
                )
            );

            $products = get_posts($args);
            $product_ids = wp_list_pluck($products, 'ID');
        }

        if (empty($product_ids)) {
            return null;
        }

        // Filtrar productos por SKU excluido
        foreach ($product_ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p || !$p->is_purchasable() || $p->is_type('variable')) {
                continue;
            }

            $sku = $p->get_sku();
            if (empty($sku)) {
                $sku = 'product_' . $pid; // Fallback
            }

            if (in_array($sku, $exclude_skus)) {
                continue; // Skip productos con SKU excluido
            }

            // Verificar stock
            if (!$p->is_in_stock()) {
                continue;
            }

            return $pid; // Producto encontrado
        }

        return null; // No se encontró reemplazo
    }
}

// Inicializar
if (is_admin()) {
    new Sorteo_WC_Extra();
}
