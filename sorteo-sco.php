<?php
/*
Plugin Name: Sorteo
Description: Plugin para sorteos automáticos, productos sorpresa, avisos personalizados, exportación de ganadores, métricas y marcos visuales en WooCommerce.
Version: 1.9.28
Author: scooller
Author URI: https://scooller.bio
Plugin URI: https://scooller.bio
Text Domain: sorteo-sco
Domain Path: /languages
*/

if (! defined('ABSPATH')) {
	exit;
}

// Declarar compatibilidad HPOS (High-Performance Order Storage)
add_action('before_woocommerce_init', function () {
	if (class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

// Definir constantes del plugin
define('SORTEO_SCO_VERSION', '1.9.28');
define('SORTEO_SCO_PLUGIN_FILE', __FILE__);
define('SORTEO_SCO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SORTEO_SCO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Hook de activación para inicializar opciones
register_activation_hook(__FILE__, 'sorteo_sco_activate');

/**
 * Función helper para formatear precios con símbolo de WooCommerce
 */
function sorteo_sco_format_price($amount)
{
	// Verificar si WooCommerce está activo y usar su función nativa
	if (function_exists('wc_price')) {
		return wc_price($amount);
	}

	// Fallback: usar símbolo de WooCommerce si está disponible
	if (function_exists('get_woocommerce_currency_symbol')) {
		$symbol = get_woocommerce_currency_symbol();
		$currency_pos = get_option('woocommerce_currency_pos', 'left');
		$price_format = number_format($amount, 2);

		switch ($currency_pos) {
			case 'left':
				return $symbol . $price_format;
			case 'right':
				return $price_format . $symbol;
			case 'left_space':
				return $symbol . ' ' . $price_format;
			case 'right_space':
				return $price_format . ' ' . $symbol;
			default:
				return $symbol . $price_format;
		}
	}

	// Último fallback: usar € como símbolo por defecto
	return '€' . number_format($amount, 2);
}

/**
 * Verifica si el plugin debe gestionar el stock de un producto
 * 
 * @param WC_Product $product Producto a verificar
 * @return bool True si el plugin debe gestionar el stock
 */
function sorteo_sco_should_manage_stock($product)
{
	if (!$product) {
		return false;
	}

	// Verificar si la gestión está habilitada
	$enabled = get_option('sorteo_wc_enable_stock_management', '0');
	if ($enabled !== '1') {
		return false;
	}

	// Obtener tipos configurados
	$allowed_types = get_option('sorteo_wc_stock_product_types', []);
	if (!is_array($allowed_types) || empty($allowed_types)) {
		return false;
	}

	$product_type = $product->get_type();

	// Verificar tipo base
	$base_types = ['simple', 'variable', 'grouped', 'external', 'sco_package'];
	$has_base_type = false;

	foreach ($base_types as $type) {
		if (in_array($type, $allowed_types) && $product_type === $type) {
			$has_base_type = true;
			break;
		}
	}

	// Si no tiene tipo base permitido, retornar false
	if (!$has_base_type) {
		return false;
	}

	// Verificar filtros adicionales (virtual, downloadable)
	$needs_virtual = in_array('virtual', $allowed_types);
	$needs_downloadable = in_array('downloadable', $allowed_types);

	if ($needs_virtual && !$product->is_virtual()) {
		return false;
	}

	if ($needs_downloadable && !$product->is_downloadable()) {
		return false;
	}

	return true;
}

/**
 * Hook para gestionar stock cuando se completa un pedido (compatible con HPOS)
 */
function sorteo_sco_manage_stock_on_order_complete($order_id, $order = null)
{
	if (!$order) {
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

		// Solo gestionar si está configurado para este tipo
		if (!sorteo_sco_should_manage_stock($product)) {
			continue;
		}

		// Verificar que el producto gestione stock
		if (!$product->managing_stock()) {
			continue;
		}

		// Evitar reducción doble
		$already = $item->get_meta('_sorteo_stock_reduced', true);
		if ($already === 'yes') {
			continue;
		}

		$quantity = $item->get_quantity();

		// Reducir stock usando función nativa de WooCommerce (compatible con HPOS)
		wc_update_product_stock($product, $quantity, 'decrease');

		// Marcar como procesado
		$item->add_meta_data('_sorteo_stock_reduced', 'yes', true);
		$item->save();

		// Agregar nota al pedido
		$order->add_order_note(sprintf(
			__('Stock descontado por Sorteo SCO: %s x%d', 'sorteo-sco'),
			$product->get_name(),
			$quantity
		));
	}

	$order->save();
}

/**
 * Reservar stock cuando se crea el pedido (prevenir race conditions)
 * WooCommerce maneja esto nativamente con wc_reserve_stock_for_order()
 */
function sorteo_sco_reserve_stock_on_checkout($order)
{
	try {
		// Obtener el order_id primero
		if ($order instanceof WC_Order) {
			$order_id = $order->get_id();
		} else {
			$order_id = $order;
			$order = wc_get_order($order_id);
		}

		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log("=== SCO RESERVE STOCK START === Order: $order_id");
		}

		if (!$order) {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("SCO Reserve: Order #$order_id not found");
			}
			return;
		}

		$blocked_reason = '';
		if (function_exists('WC') && WC()->session) {
			$blocked_reason = (string) WC()->session->get('sco_pkg_checkout_blocked');
			if ($blocked_reason !== '') {
				if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					error_log("SCO Reserve: Skipping reservation due to checkout block ($blocked_reason) for order #$order_id");
				}
				WC()->session->set('sco_pkg_checkout_blocked', '');
				return;
			}
		}

		// WooCommerce ya maneja reserva de stock nativamente desde v3.5+
		// Esta función es un backup por si el tema/plugin lo desactiva
		$reserve_enabled = get_option('sorteo_wc_enable_stock_reservation', '1');
		if ($reserve_enabled !== '1') {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("SCO Reserve: Stock reservation disabled in settings");
			}
			return;
		}

		// Verificar si WooCommerce ya reservó el stock
		$stock_reserved = $order->get_meta('_stock_reserved', true);
		if ($stock_reserved === 'yes') {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("SCO Reserve: Stock already reserved for order #$order_id");
			}
			return;
		}

		// Verificar si la orden contiene paquetes
		$has_packages = false;
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product && $product->get_type() === 'sco_package') {
				$has_packages = true;
				break;
			}
		}

		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log("SCO Reserve: Order contains packages: " . ($has_packages ? 'yes' : 'no'));
		}

		// Solo reservar stock si hay paquetes o si WooCommerce no lo hizo automáticamente
		if (function_exists('wc_reserve_stock_for_order')) {
			// Si NO hay paquetes, dejar que WooCommerce maneje la reserva nativamente
			if (!$has_packages) {
				if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					error_log("SCO Reserve: Skipping reservation - no packages in order, WooCommerce will handle it");
				}
				return;
			}

			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("SCO Reserve: Calling wc_reserve_stock_for_order() for package order");
			}

			wc_reserve_stock_for_order($order);

			// Reservar también los componentes de paquetes que no están como line items
			if (function_exists('sco_pkg_reserve_components_for_order')) {
				if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
					error_log("SCO Reserve: Calling sco_pkg_reserve_components_for_order()");
				}
				sco_pkg_reserve_components_for_order($order);
			}

			$order->update_meta_data('_stock_reserved', 'yes');
			$order->save();

			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("=== SCO RESERVE STOCK END - SUCCESS === Order: $order_id");
			}
		} else {
			if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
				error_log("SCO Reserve: wc_reserve_stock_for_order() function not available");
			}
		}
	} catch (Exception $e) {
		if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
			error_log('SCO Reserve FATAL ERROR: ' . $e->getMessage());
			error_log('SCO Reserve STACK TRACE: ' . $e->getTraceAsString());
			error_log('=== SCO RESERVE STOCK END - FAILED === Order: ' . $order_id);
		}
		// NO re-lanzar el error para no bloquear el checkout
		// El pedido se procesará aunque la reserva falle
	}
}

/**
 * Liberar stock reservado si el pedido se cancela o falla
 */
function sorteo_sco_release_reserved_stock($order_id)
{
	$order = wc_get_order($order_id);
	if (!$order) {
		return;
	}

	// Usar función nativa de WooCommerce si está disponible
	if (function_exists('wc_release_stock_for_order')) {
		try {
			wc_release_stock_for_order($order);
			$order->delete_meta_data('_stock_reserved');
			$order->save();
		} catch (Exception $e) {
			error_log('Sorteo SCO: ERROR al liberar stock - ' . $e->getMessage());
		}
	}
}

// Hook para reducir stock cuando se completa el pedido
add_action('woocommerce_order_status_processing', 'sorteo_sco_manage_stock_on_order_complete', 10, 2);
add_action('woocommerce_order_status_completed', 'sorteo_sco_manage_stock_on_order_complete', 10, 2);

// Hook para reservar stock al crear el pedido (prevenir race conditions)
add_action('woocommerce_checkout_order_created', 'sorteo_sco_reserve_stock_on_checkout', 10, 1);

// Hook para liberar stock si el pedido se cancela/falla
add_action('woocommerce_order_status_cancelled', 'sorteo_sco_release_reserved_stock', 10, 1);
add_action('woocommerce_order_status_failed', 'sorteo_sco_release_reserved_stock', 10, 1);

function sorteo_sco_activate()
{
	// Inicializar opciones del mensaje con valores por defecto
	add_option('sorteo_sco_mensaje_bg_color', '#4caf50');
	add_option('sorteo_sco_mensaje_text_color', '#ffffff');
	add_option('sorteo_sco_mensaje_font_size', '16px');
	add_option('sorteo_sco_mensaje_font_family', 'inherit');
	add_option('sorteo_sco_mensaje_font_weight', 'normal');
	add_option('sorteo_sco_mensaje_duration', '10');

	// Forzar flush de rewrite rules si es necesario
	flush_rewrite_rules();
}

// Carga archivos principales
require_once __DIR__ . '/includes/class-sorteo-theme-compat.php';
require_once __DIR__ . '/includes/class-sorteo-core.php';
require_once __DIR__ . '/includes/class-sorteo-admin.php';
require_once __DIR__ . '/includes/class-sorteo-email.php';
require_once __DIR__ . '/includes/class-sorteo-export.php';
require_once __DIR__ . '/includes/class-sorteo-metrics.php';
require_once __DIR__ . '/includes/class-sorteo-product-frame.php';
require_once __DIR__ . '/includes/class-sorteo-package-simple.php';
require_once __DIR__ . '/includes/class-sorteo-markdown.php';
require_once __DIR__ . '/includes/class-sorteo-wc-extra.php';

/**
 * Aplicar ordenamiento personalizado de productos en categorías
 */
add_filter('woocommerce_get_catalog_ordering_args', 'sorteo_sco_apply_product_ordering');
add_filter('posts_clauses', 'sorteo_sco_featured_first_orderby', 9999, 2);

function sorteo_sco_apply_product_ordering($args)
{
	// Solo en frontend (no en admin)
	if (is_admin()) {
		return $args;
	}

	// Obtener configuración de ordenamiento
	$order_by = get_option('sorteo_wc_product_order_by', '');
	$order_dir = get_option('sorteo_wc_product_order_dir', 'DESC');

	// Si no hay configuración, retornar
	if (empty($order_by)) {
		return $args;
	}

	// Mapeo de campos ordenables
	$orderby_map = array(
		'date' => 'date',
		'title' => 'title',
		'price' => 'price',
		'popularity' => 'popularity',
		'rating' => 'rating',
		'rand' => 'rand',
	);

	// Verificar que el orden_by sea válido
	if (!isset($orderby_map[$order_by])) {
		return $args;
	}

	// Aplicar ordenamiento
	$args['orderby'] = $orderby_map[$order_by];
	$args['order'] = ($order_dir === 'DESC') ? 'DESC' : 'ASC';

	return $args;
}

function sorteo_sco_featured_first_orderby($clauses, $query)
{
	global $wpdb;

	// Solo en frontend
	if (is_admin()) {
		return $clauses;
	}

	// Detectar si es una query de productos de WooCommerce
	$is_product_query = false;

	// Método 1: Verificar post_type en query_vars
	if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'product') {
		$is_product_query = true;
	}

	// Método 2: Verificar si WooCommerce está activo y es página de productos
	if (!$is_product_query && function_exists('is_shop') && function_exists('is_product_category')) {
		if (is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy()) {
			$is_product_query = true;
		}
	}

	// Método 3: Verificar si la query tiene taxonomy de productos
	if (!$is_product_query && isset($query->tax_query) && !empty($query->tax_query->queries)) {
		foreach ($query->tax_query->queries as $tax_query) {
			if (isset($tax_query['taxonomy']) && in_array($tax_query['taxonomy'], ['product_cat', 'product_tag'])) {
				$is_product_query = true;
				break;
			}
		}
	}

	if (!$is_product_query) {
		return $clauses;
	}

	// Obtener el term_id de "featured" en la taxonomía product_visibility
	$featured_term = get_term_by('name', 'featured', 'product_visibility');

	if (!$featured_term) {
		return $clauses;
	}

	$featured_term_id = $featured_term->term_id;

	// Solo loggear la primera query principal
	static $logged = false;
	if (!$logged && !empty($clauses['orderby']) && strpos($clauses['orderby'], 'RAND()') !== false) {
		// Se consultan productos destacados solo una vez, sin log de depuración en producción
		$logged = true;
	}

	// Verificar si ya existe el JOIN para featured
	if (strpos($clauses['join'], 'tr_featured') === false) {
		// JOIN con term_relationships para obtener productos destacados
		$clauses['join'] .= " LEFT JOIN {$wpdb->term_relationships} AS tr_featured ON (
			{$wpdb->posts}.ID = tr_featured.object_id 
			AND tr_featured.term_taxonomy_id = {$featured_term_id}
		)";
	}

	// Modificar el ORDER BY para que featured vaya primero
	if (!empty($clauses['orderby'])) {
		// Si tr_featured.object_id NO ES NULL, el producto es destacado (valor 0), sino es normal (valor 1)
		$clauses['orderby'] = "CASE WHEN tr_featured.object_id IS NOT NULL THEN 0 ELSE 1 END, " . $clauses['orderby'];
	}

	return $clauses;
}

// Granting download permissions for sco_package components (priority 5 to run BEFORE emails)
add_action('woocommerce_order_status_processing', 'sorteo_sco_grant_package_downloads', 5, 2);
add_action('woocommerce_order_status_completed', 'sorteo_sco_grant_package_downloads', 5, 2);

function sorteo_sco_grant_package_downloads($order_id, $order = null)
{
	if (!$order) {
		$order = wc_get_order($order_id);
	}
	if (!$order) {
		return;
	}

	// Check if already granted
	if ('yes' === $order->get_meta('_sco_pkg_downloads_granted')) {
		return;
	}

	$granted_count = 0; // nuevos permisos creados
	$processed_files = 0; // total de archivos descargables procesados (existan o no)
	foreach ($order->get_items() as $item_id => $item) {
		$product = $item->get_product();
		if (!$product || $product->get_type() !== 'sco_package') {
			continue;
		}

		// Read composition from order item meta (_sco_package contiene todo)
		$pkg = $item->get_meta('_sco_package', true);
		if (empty($pkg) || empty($pkg['components']) || !is_array($pkg['components'])) {
			continue;
		}

		// Processing package components (debug logs removed)

		// Grant downloads for each component
		foreach ($pkg['components'] as $comp) {
			$comp_product_id = isset($comp['product_id']) ? (int)$comp['product_id'] : 0;
			if ($comp_product_id <= 0) {
				continue;
			}

			$comp_product = wc_get_product($comp_product_id);
			if (!$comp_product || !$comp_product->is_downloadable()) {
				continue;
			}

			$files = $comp_product->get_downloads();
			if (empty($files)) {
				continue;
			}

			$comp_product_name = $comp_product->get_name();
			$file_count = count($files);
			$customer_email = $order->get_billing_email();
			$customer_id = $order->get_customer_id();

			foreach ($files as $download_id => $file) {
				// Check if permission already exists
				global $wpdb;
				$existing = $wpdb->get_var($wpdb->prepare(
					"SELECT download_id FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions 
					WHERE order_id = %d AND product_id = %d AND download_id = %s",
					$order->get_id(),
					$comp_product_id,
					$download_id
				));

				if (!$existing) {
					// Insert download permission
					$result = $wpdb->insert(
						$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
						array(
							'download_id'         => $download_id,
							'product_id'          => $comp_product_id,
							'order_id'            => $order->get_id(),
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
						$processed_files++;
						$granted_count++;
					} else {
						// Log only real DB errors
						if (! empty($wpdb->last_error) && function_exists('error_log')) {
							error_log(sprintf(
								'Sorteo SCO: ERROR DB al crear permiso - order_id=%d, product_id=%d, download_id=%s, error=%s',
								$order->get_id(),
								$comp_product_id,
								$download_id,
								$wpdb->last_error
							));
						}
					}
				} else {
					$processed_files++;
				}
			}
		}
	}

	// Si procesamos al menos un archivo (exista o recién creado) consideramos permisos listos
	if ($processed_files > 0) {
		$order->update_meta_data('_sco_pkg_downloads_granted', 'yes');
		$order->save();
	}

	// Enviar email personalizado UNA SOLA VEZ por pedido (no por cada paquete)
	// Se ejecuta solo si hay paquetes con componentes descargables Y no se ha enviado previamente
	if (class_exists('Sorteo_SCO_Email') && function_exists('sorteo_sco_package_needs_custom_downloads_email')) {
		// Verificar si ya se envió el email general del pedido
		if ('yes' !== $order->get_meta('_sco_pkg_downloads_email_sent')) {
			$has_downloadable_packages = false;

			// Verificar si hay al menos un paquete con componentes descargables
			foreach ($order->get_items() as $item_id => $item) {
				$product = $item->get_product();
				if ($product && $product->get_type() === 'sco_package') {
					if (sorteo_sco_package_needs_custom_downloads_email($product)) {
						$has_downloadable_packages = true;
						break;
					}
				}
			}

			// Enviar UNA SOLA VEZ si hay paquetes descargables
			// Pasar NULL para que procese TODOS los paquetes del pedido
			if ($has_downloadable_packages) {
				$sent = Sorteo_SCO_Email::send_package_component_downloads_email($order_id, null);
				if ($sent) {
					// Marcar como enviado a nivel de pedido
					$order->update_meta_data('_sco_pkg_downloads_email_sent', 'yes');
					$order->save();
				}
			}
		}
	}

	if ($processed_files > 0) {
		if ('yes' !== $order->get_meta('_sco_pkg_downloads_email_sent') && function_exists('sorteo_sco_maybe_send_downloads_email')) {
			// Forzamos envío inmediato tras confirmar permisos
			sorteo_sco_maybe_send_downloads_email($order->get_id(), true);
		}
	}
}

// Inicializa el núcleo del plugin
add_action('plugins_loaded', function () {
	Sorteo_SCO_Core::get_instance();
});

// Enqueue frontend assets for sco_package quantity selector
add_action('wp_enqueue_scripts', function () {
	if (is_admin()) return;
	if (!class_exists('WooCommerce')) return;
	wp_enqueue_script(
		'sco-package-frontend',
		SORTEO_SCO_PLUGIN_URL . 'assets/js/sco-package-frontend.js',
		array('jquery'),
		SORTEO_SCO_VERSION,
		true
	);
});

// Registrar manejadores de acciones administrativas inmediatamente
add_action('woocommerce_order_status_refunded', function ($order_id) {
	$order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
	if (! $order) return;
	if ('yes' === $order->get_meta('_sco_pkg_downloads_email_sent')) {
		$order->delete_meta_data('_sco_pkg_downloads_email_sent');
		$order->save();
	}
}, 20);

add_action('admin_post_sorteo_sco_clear_history', 'sorteo_sco_handle_clear_history');
add_action('admin_post_sorteo_sco_export_winners', 'sorteo_sco_handle_export_winners');
add_action('admin_post_sorteo_sco_export_purchases', 'sorteo_sco_handle_export_purchases');

// Añadir enlaces de acción en la fila del plugin (Ajustes)
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
	$settings_url = admin_url('admin.php?page=sorteo-sco-settings');
	$settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Ajustes', 'sorteo-sco') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
});

// Endpoint de reenvío manual (admin-post)
add_action('admin_post_sorteo_sco_resend_downloads_email', function () {
	if (! current_user_can('manage_options')) {
		wp_die(__('No tienes permiso para realizar esta acción.', 'sorteo-sco'));
	}

	$order_id = isset($_REQUEST['order_id']) ? absint($_REQUEST['order_id']) : 0;
	$nonce = isset($_REQUEST['_wpnonce']) ? $_REQUEST['_wpnonce'] : '';
	if (! $order_id || ! wp_verify_nonce($nonce, 'sorteo_sco_resend_downloads_email_' . $order_id)) {
		wp_die(__('Token inválido o pedido no especificado.', 'sorteo-sco'));
	}

	$order = wc_get_order($order_id);
	if (! $order) {
		wp_redirect(wp_get_referer() ?: admin_url());
		exit;
	}

	// Permitir reenviar: limpiar meta para permitir nuevo envío
	$order->delete_meta_data('_sco_pkg_downloads_email_sent');
	$order->save();

	// Intentar enviar inmediatamente y registrar únicamente si hay error
	$sent =
		class_exists('Sorteo_SCO_Email') && method_exists('Sorteo_SCO_Email', 'send_order_downloads_email')
		? Sorteo_SCO_Email::send_order_downloads_email($order_id)
		: false;

	$current_user = wp_get_current_user();
	$actor = $current_user && $current_user->exists() ? $current_user->display_name : 'admin';
	if ($sent) {
		$order->add_order_note(sprintf('Reenvío manual del pedido #%s (ID %d): email de descargas enviado a %s por %s.', $order->get_order_number(), $order->get_id(), $order->get_billing_email(), $actor));
	} else {
		$order->add_order_note(sprintf('Reenvío manual del pedido #%s (ID %d): error al enviar email de descargas a %s por %s.', $order->get_order_number(), $order->get_id(), $order->get_billing_email(), $actor));
	}

	if (! $sent) {
		if (function_exists('error_log')) {
			error_log(sprintf('Sorteo SCO: ERROR al reenviar email de descargas para pedido #%d', $order_id));
		}
	}

	// Redirigir de vuelta al pedido con parámetro para notificación en admin
	$redirect = admin_url('post.php?post=' . $order_id . '&action=edit&resend_downloads=' . ($sent ? '1' : '0'));
	wp_safe_redirect($redirect);
	exit;
});

// Mostrar notificación admin tras reenviar desde endpoint
add_action('admin_notices', function () {
	if (! current_user_can('manage_options')) return;
	if (! isset($_GET['resend_downloads'])) return;
	if ($_GET['resend_downloads'] == '1') {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Email de descargas reenviado correctamente.', 'sorteo-sco') . '</p></div>';
	} else {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('ERROR: No se pudo reenviar el email de descargas. Revisa permisos y revisa registros.', 'sorteo-sco') . '</p></div>';
	}
});

// Agregar acción rápida en la lista de pedidos para reenviar email de descargas
add_filter('woocommerce_admin_order_actions', function ($actions, $order) {
	if (! current_user_can('manage_options')) return $actions;
	if (! $order instanceof WC_Order) return $actions;

	// Mostrar acción solo si el pedido tiene paquetes o permisos
	$has_pkg = false;
	foreach ($order->get_items() as $it) {
		$p = $it->get_product();
		if ($p && $p->get_type() === 'sco_package') {
			$has_pkg = true;
			break;
		}
	}
	if (! $has_pkg) return $actions;

	$url = wp_nonce_url(admin_url('admin-post.php?action=sorteo_sco_resend_downloads_email&order_id=' . $order->get_id()), 'sorteo_sco_resend_downloads_email_' . $order->get_id());
	$actions['sco_resend_downloads'] = array(
		'url'    => $url,
		'name'   => __('Reenviar descargas', 'sorteo-sco'),
		'action' => 'sco-resend-downloads',
	);

	return $actions;
}, 10, 2);

// Añadir acción al dropdown de acciones del pedido (editor de pedido)
add_filter('woocommerce_order_actions', function ($actions) {
	$actions['sco_resend_downloads'] = __('Reenviar descargas', 'sorteo-sco');
	return $actions;
}, 20);

// Handler para la acción del dropdown
add_action('woocommerce_order_action_sco_resend_downloads', function ($order) {
	// $order puede ser un WC_Order o un ID
	if (! is_a($order, 'WC_Order')) {
		$order = wc_get_order($order);
	}
	if (! $order) return;

	// Limpiar meta que evita reenvío
	$order->delete_meta_data('_sco_pkg_downloads_email_sent');
	$order->save();

	// Forzar envío inmediato
	if (class_exists('Sorteo_SCO_Email')) {
		$sent = Sorteo_SCO_Email::send_order_downloads_email($order->get_id());
		if ($sent) {
			$order->add_order_note(__('Sorteo: Reenvío de email de descargas ejecutado correctamente.', 'sorteo-sco'));
		} else {
			$order->add_order_note(__('Sorteo: ERROR al reenviar email de descargas (chequear logs).', 'sorteo-sco'));
		}
	}
}, 10, 1);

// Añadir enlaces meta (Documentación y Opciones del plugin)
add_filter('plugin_row_meta', function ($links, $file) {
	if ($file === plugin_basename(__FILE__)) {
		$docs_url = admin_url('admin.php?page=sorteo-sco-docs');
		$docs_link = '<a href="' . esc_url($docs_url) . '" aria-label="' . esc_attr__('Ver documentación', 'sorteo-sco') . '">' . esc_html__('Documentación', 'sorteo-sco') . '</a>';
		$links[] = $docs_link;

		$settings_url = admin_url('admin.php?page=sorteo-sco-settings');
		$settings_link = '<a href="' . esc_url($settings_url) . '" aria-label="' . esc_attr__('Ver opciones del plugin', 'sorteo-sco') . '">' . esc_html__('Opciones del plugin', 'sorteo-sco') . '</a>';
		$links[] = $settings_link;
	}
	return $links;
}, 10, 2);

// Submenú y página de Documentación (muestra README.md)
add_action('admin_menu', function () {
	// Agregar como submenú bajo el menú principal del plugin
	add_submenu_page(
		'sorteo-sco-settings',
		__('Documentación', 'sorteo-sco'),
		__('Documentación', 'sorteo-sco'),
		'manage_options',
		'sorteo-sco-docs',
		'sorteo_sco_render_docs_page'
	);
});

function sorteo_sco_render_docs_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}
	$readme_path = SORTEO_SCO_PLUGIN_DIR . 'README.md';
	$content = '';
	if (file_exists($readme_path)) {
		$content = file_get_contents($readme_path);
	} else {
		$content = __('No se encontró el archivo README.md en el plugin.', 'sorteo-sco');
	}
	echo '<div class="wrap">';
	echo '<h1>' . esc_html__('Documentación de Sorteo', 'sorteo-sco') . '</h1>';
	echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=sorteo-sco-settings')) . '">' . esc_html__('Ir a Ajustes', 'sorteo-sco') . '</a></p>';
	echo '<div style="background:#fff;border:1px solid #ccd0d4; padding:16px; max-width:1100px;">';
	$html = class_exists('Sorteo_SCO_Markdown') ? Sorteo_SCO_Markdown::to_html($content) : '<pre>' . esc_html($content) . '</pre>';
	echo '<style>.sco-md-table table{width:100%;border-collapse:collapse}.sco-md-table th,.sco-md-table td{border:1px solid #e1e1e1;padding:8px}.sco-md-table thead th{background:#f6f7f7}.wrap pre code{display:block;overflow:auto;padding:12px;background:#f6f7f7;border:1px solid #e1e1e1;border-radius:3px}</style>';
	echo wp_kses_post($html);
	echo '</div>';
	echo '</div>';
}

function sorteo_sco_handle_clear_history()
{
	// Borrar historial directamente sin verificaciones complejas
	$draws_history = get_option('sorteo_sco_draws_history', array());
	$total_draws = count($draws_history);

	// Borrar historial
	delete_option('sorteo_sco_draws_history');
	update_option('sorteo_sco_total_draws', 0);

	// Enviar email a administradores
	sorteo_sco_send_clear_history_notification($total_draws, $draws_history);

	// Redirigir con mensaje de éxito
	$redirect_url = add_query_arg(
		array(
			'page' => 'sorteo-sco',
			'tab' => 'exportar',
			'history_cleared' => '1'
		),
		admin_url('admin.php')
	);

	wp_redirect($redirect_url);
	exit;
}

function sorteo_sco_send_clear_history_notification($total_draws, $deleted_history)
{
	// Obtener todos los administradores
	$admins = get_users(array('role' => 'administrator'));
	$admin_emails = array();

	foreach ($admins as $admin) {
		$admin_emails[] = $admin->user_email;
	}

	if (empty($admin_emails)) {
		return; // No hay administradores
	}

	// Preparar el contenido del email
	$current_user = wp_get_current_user();
	$site_name = get_bloginfo('name');
	$site_url = home_url();
	$date_time = current_time('d/m/Y H:i:s');

	$subject = sprintf('[%s] Historial de Sorteos Eliminado', $site_name);

	$message = "
	<html>
	<head>
		<style>
			body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
			.header { background: #dc3545; color: white; padding: 20px; text-align: center; }
			.content { padding: 20px; }
			.warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 20px 0; border-radius: 5px; }
			.details { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; }
			.history-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
			.history-table th, .history-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			.history-table th { background: #f2f2f2; }
		</style>
	</head>
	<body>
		<div class='header'>
			<h1>⚠️ HISTORIAL DE SORTEOS ELIMINADO</h1>
		</div>
		
		<div class='content'>
			<div class='warning'>
				<strong>ATENCIÓN:</strong> El historial completo de sorteos ha sido eliminado del sistema.
			</div>
			
			<div class='details'>
				<h3>Detalles de la acción:</h3>
				<ul>
					<li><strong>Sitio web:</strong> {$site_name} ({$site_url})</li>
					<li><strong>Usuario responsable:</strong> {$current_user->display_name} ({$current_user->user_email})</li>
					<li><strong>Fecha y hora:</strong> {$date_time}</li>
					<li><strong>Total de registros eliminados:</strong> {$total_draws}</li>
				</ul>
			</div>";

	// Agregar tabla con los últimos registros eliminados si había historial
	if (!empty($deleted_history)) {
		$message .= "
			<h3>Últimos registros eliminados:</h3>
			<table class='history-table'>
				<thead>
					<tr>
						<th>Fecha</th>
						<th>Ganador</th>
						<th>Email</th>
						<th>Tipo</th>
					</tr>
				</thead>
				<tbody>";

		// Mostrar máximo los últimos 10 registros
		$recent_history = array_slice($deleted_history, -10);
		foreach ($recent_history as $record) {
			$type_label = $record['type'] == 'manual' ? 'Manual' : 'Automático';
			$message .= "
					<tr>
						<td>" . date('d/m/Y H:i', strtotime($record['date'])) . "</td>
						<td>" . esc_html($record['winner_name']) . "</td>
						<td>" . esc_html($record['winner_email']) . "</td>
						<td>{$type_label}</td>
					</tr>";
		}

		$message .= "
				</tbody>
			</table>";

		if (count($deleted_history) > 10) {
			$message .= "<p><em>Se muestran solo los últimos 10 de {$total_draws} registros eliminados.</em></p>";
		}
	}

	$message .= "
			<div class='warning'>
				<strong>Nota importante:</strong> Esta acción es irreversible. Si necesitas restaurar los datos, deberás hacerlo desde una copia de seguridad de la base de datos.
			</div>
		</div>
	</body>
	</html>";

	// Enviar email a todos los administradores
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $site_name . ' <noreply@' . parse_url($site_url, PHP_URL_HOST) . '>'
	);

	foreach ($admin_emails as $admin_email) {
		wp_mail($admin_email, $subject, $message, $headers);
	}
}

// Manejador de exportación de ganadores
function sorteo_sco_handle_export_winners()
{
	// Verificar permisos
	if (!current_user_can('manage_options')) {
		wp_die(__('No tienes permisos para realizar esta acción.', 'sorteo-sco'));
	}

	// Verificar nonce si se envía
	if (isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'sorteo_export_winners')) {
		wp_die(__('Token de seguridad inválido.', 'sorteo-sco'));
	}

	// Llamar a la exportación
	if (class_exists('Sorteo_SCO_Export')) {
		Sorteo_SCO_Export::download_winners_csv();
	}

	// Si llegamos aquí, algo salió mal
	wp_die(__('Error en la exportación.', 'sorteo-sco'));
}

// Manejador de exportación de usuarios+compras
function sorteo_sco_handle_export_purchases()
{
	// Verificar permisos
	if (!current_user_can('manage_options')) {
		wp_die(__('No tienes permisos para realizar esta acción.', 'sorteo-sco'));
	}

	// Verificar nonce si se envía
	if (isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'sorteo_export_purchases')) {
		wp_die(__('Token de seguridad inválido.', 'sorteo-sco'));
	}

	// Llamar a la exportación
	if (class_exists('Sorteo_SCO_Export')) {
		Sorteo_SCO_Export::download_users_purchases_csv();
	}

	// Si llegamos aquí, algo salió mal
	wp_die(__('Error en la exportación.', 'sorteo-sco'));
}

// Manejador de premio manual a pedido específico
add_action('admin_post_sorteo_sco_manual_prize', 'sorteo_sco_handle_manual_prize');
function sorteo_sco_handle_manual_prize()
{
	// Verificar permisos
	if (!current_user_can('manage_options')) {
		wp_die(__('No tienes permisos para realizar esta acción.', 'sorteo-sco'));
	}

	// Verificar nonce
	if (!isset($_POST['sorteo_manual_prize_nonce']) || !wp_verify_nonce($_POST['sorteo_manual_prize_nonce'], 'sorteo_manual_prize')) {
		wp_die(__('Token de seguridad inválido.', 'sorteo-sco'));
	}

	// Obtener ID del pedido
	$order_id = isset($_POST['sorteo_order_id']) ? intval($_POST['sorteo_order_id']) : 0;

	if (!$order_id) {
		wp_die(__('Debe seleccionar un pedido.', 'sorteo-sco'));
	}

	// Verificar que el pedido existe
	$order = wc_get_order($order_id);
	if (!$order) {
		wp_die(__('El pedido seleccionado no existe.', 'sorteo-sco'));
	}

	// Obtener información del premio configurado
	$prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
	$prize_name = get_option('sorteo_sco_prize_name', __('Premio', 'sorteo-sco'));

	// Obtener información del ganador
	$winner_email = $order->get_billing_email();
	$winner_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

	// Obtener período configurado
	$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
	$periodo_fin = get_option('sorteo_sco_periodo_fin');

	// Crear registro del sorteo
	$draw_record = array(
		'date' => current_time('mysql'),
		'winner_email' => $winner_email,
		'winner_name' => $winner_name,
		'order_id' => $order_id,
		'type' => 'manual',
		'prize_name' => $prize_name,
		'prize_price' => $prize_price,
		'periodo_inicio' => $periodo_inicio,
		'periodo_fin' => $periodo_fin,
	);

	// Guardar en historial
	$draws_history = get_option('sorteo_sco_draws_history', array());
	$draws_history[] = $draw_record;
	update_option('sorteo_sco_draws_history', $draws_history);

	// Actualizar contador de sorteos
	$total_draws = count($draws_history);
	update_option('sorteo_sco_total_draws', $total_draws);

	// Guardar último ganador
	update_option('sorteo_sco_last_winner', array(
		'name' => $winner_name,
		'email' => $winner_email,
		'order_id' => $order_id,
		'date' => current_time('mysql'),
		'prize_name' => $prize_name,
		'prize_price' => $prize_price,
	));

	// Enviar email al ganador
	if (class_exists('Sorteo_SCO_Email')) {
		$email_handler = new Sorteo_SCO_Email();
		$email_handler->send_winner_email($winner_email, $winner_name, $prize_name, $prize_price);
	}

	// Redirigir de vuelta con mensaje de éxito
	$redirect_url = add_query_arg(
		array(
			'page' => 'sorteo-sco-settings',
			'tab' => 'exportar',
			'manual_prize' => 'success',
			'order_id' => $order_id
		),
		admin_url('admin.php')
	);

	wp_redirect($redirect_url);
	exit;
}
