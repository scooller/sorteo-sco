<?php
if (! defined('ABSPATH')) {
	exit;
}

class Sorteo_SCO_Core
{
	private static $instance = null;

	public static function get_instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct()
	{
		// Inicialización de funcionalidades principales
		add_action('init', [$this, 'init_plugin']);
		// Mostrar aviso en múltiples ubicaciones
		add_action('woocommerce_before_main_content', [$this, 'show_winner_notice'], 5);
		add_action('woocommerce_thankyou', [$this, 'show_winner_notice'], 5);
		add_action('wp_head', [$this, 'show_winner_notice_global']);
		add_action('wp_footer', [$this, 'show_winner_notice_footer']);
		add_action('woocommerce_order_status_changed', [$this, 'check_and_show_notice_on_status_change'], 10, 3);
		add_action('wp_loaded', [$this, 'check_recent_purchase']);
		// Añadir marco visual en diferentes ubicaciones (múltiples hooks para compatibilidad)
		add_action('woocommerce_before_single_product_summary', [$this, 'add_product_frame_single'], 5);
		add_action('woocommerce_before_shop_loop_item', [$this, 'add_product_frame_shop'], 3); // Antes que bootstrap_theme_product_badges (prioridad 5)
		// Hook específico para Bootstrap Theme en la imagen del producto
		add_action('wp_footer', [$this, 'add_bootstrap_theme_single_product_script']);
		// Marco visual en carrito
		add_action('woocommerce_cart_item_thumbnail', [$this, 'add_product_frame_cart'], 10, 3);
		// Sorteo automático en cada carga de admin
		add_action('admin_init', [$this, 'maybe_run_auto_draw']);
		// Cargar estilos y scripts del frontend
		add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
		// Actualizar métricas cuando se carga la página de admin del plugin
		add_action('load-toplevel_page_sorteo-sco-settings', [$this, 'update_metrics']);
		// Hook adicional para actualizar al cargar cualquier página del admin
		add_action('admin_init', [$this, 'update_metrics_on_admin_init']);
		// Actualizar métricas automáticamente cuando cambian estados de pedidos
		add_action('woocommerce_order_status_changed', [$this, 'update_metrics_on_order_change'], 10, 3);
		// Actualizar métricas cuando se crea un nuevo pedido
		add_action('woocommerce_new_order', [$this, 'update_metrics']);
		// Actualizar métricas cuando se modifica un pedido  
		add_action('woocommerce_update_order', [$this, 'update_metrics']);
		// Actualizar métricas cuando se guarda un pedido (incluye nuevos y editados)
		add_action('woocommerce_process_shop_order_meta', [$this, 'update_metrics']);
		// Actualizar métricas cuando se crea un pedido desde admin
		add_action('woocommerce_admin_order_data_after_order_details', [$this, 'update_metrics']);
		// Actualizar métricas cuando se completa el checkout (pedidos nuevos)
		add_action('woocommerce_checkout_order_processed', [$this, 'update_metrics']);
		// Actualizar métricas cuando se procesa un pago
		add_action('woocommerce_payment_complete', [$this, 'update_metrics']);
		// Hook adicional para asegurar actualización en cualquier cambio de estado importante
		add_action('transition_post_status', [$this, 'update_metrics_on_post_transition'], 10, 3);
		// Hook para when order status is updated via admin
		add_action('woocommerce_order_edit_status', [$this, 'update_metrics']);
	}

	public function init_plugin()
	{
		// Aquí se inicializan los componentes principales
		load_plugin_textdomain('sorteo-sco', false, dirname(plugin_basename(__FILE__)) . '/../languages/');
		// Hook para ejecutar sorteo manual desde admin
		add_action('admin_post_sorteo_sco_run_draw', [$this, 'run_draw']);
	}

	/**
	 * Ejecuta el sorteo entre usuarios compradores en el periodo/categoría/producto
	 */
	public function run_draw()
	{
		// Obtener opciones con las nuevas opciones separadas
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		// Convertir estados de 'wc-completed' a 'completed' para wc_get_orders
		$statuses_for_query = array();
		foreach ($order_statuses as $status) {
			// Quitar el prefijo 'wc-' si existe
			$clean_status = str_replace('wc-', '', $status);
			$statuses_for_query[] = $clean_status;
		}

		// Preparar argumentos para WC_Order_Query
		$args = [
			'limit' => -1,
			'status' => $statuses_for_query,
		];

		// Solo agregar filtro de fecha si ambas fechas están configuradas
		if ($periodo_inicio && $periodo_fin) {
			$args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}

		$orders = wc_get_orders($args);
		$user_ids = [];
		foreach ($orders as $order) {
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				// Filtrar por producto/categoría si corresponde
				if ($productos && !in_array($product_id, explode(',', $productos))) continue;
				if ($categorias) {
					$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					$cat_ids = explode(',', $categorias);
					if (!array_intersect($terms, $cat_ids)) continue;
				}
				$user_id = $order->get_customer_id();
				if ($user_id) $user_ids[] = $user_id;
			}
		}
		$user_ids = array_unique($user_ids);
		if (empty($user_ids)) {
			wp_redirect(admin_url('admin.php?page=sorteo-sco-settings&draw=none'));
			exit;
		}
		// Sorteo aleatorio
		$winner_id = $user_ids[array_rand($user_ids)];

		// Guardar el ganador actual
		update_option('sorteo_sco_last_winner', $winner_id);

		// Guardar en el historial de sorteos
		$this->save_draw_result($winner_id, 'manual');

		// Enviar email al ganador usando su último pedido
		if (class_exists('Sorteo_SCO_Email')) {
			// Buscar el último pedido del ganador
			$last_order_id = $this->get_last_order_for_user($winner_id);
			if ($last_order_id) {
				Sorteo_SCO_Email::send_winner_email($last_order_id, __('Premio sorpresa', 'sorteo-sco'));
			}
		}
		wp_redirect(admin_url('admin.php?page=sorteo-sco-settings&draw=' . $winner_id));
		exit;
	}

	/**
	 * Obtener el último pedido de un usuario (por user_id o criterios del sorteo)
	 */
	private function get_last_order_for_user($user_id)
	{
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Convertir estados
		$statuses_for_query = array();
		if (is_array($order_statuses)) {
			foreach ($order_statuses as $status) {
				$clean_status = str_replace('wc-', '', $status);
				$statuses_for_query[] = $clean_status;
			}
		}

		$order_args = array(
			'customer_id' => $user_id,
			'status' => $statuses_for_query,
			'limit' => 10,
			'orderby' => 'date',
			'order' => 'DESC'
		);

		if ($periodo_inicio && $periodo_fin) {
			$order_args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}

		$orders = wc_get_orders($order_args);

		// Buscar una orden con productos elegibles
		foreach ($orders as $order) {
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();

				if ($productos) {
					$productos_array = is_array($productos) ? $productos : explode(',', $productos);
					$productos_array = array_map('intval', $productos_array);
					if (in_array($product_id, $productos_array)) {
						return $order->get_id();
					}
				}

				if ($categorias) {
					$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
					$selected_categories = array_map('intval', $selected_categories);

					if (!empty(array_intersect($terms, $selected_categories))) {
						return $order->get_id();
					}
				}
			}
		}

		// Si no se encuentra con criterios, retornar el primer pedido
		if (!empty($orders)) {
			return $orders[0]->get_id();
		}

		return null;
	}

	/**
	 * Guardar resultado de sorteo en el historial
	 */
	private function save_draw_result($winner_id, $type = 'manual')
	{
		$draws_history = get_option('sorteo_sco_draws_history', array());

		// Obtener información del usuario de forma segura
		$user_data = get_userdata($winner_id);
		$first_name = get_user_meta($winner_id, 'first_name', true);
		$last_name = get_user_meta($winner_id, 'last_name', true);
		$winner_name = trim($first_name . ' ' . $last_name);

		// Si no hay nombre, usar el display_name o un fallback
		if (empty($winner_name) && $user_data) {
			$winner_name = $user_data->display_name;
		}
		if (empty($winner_name)) {
			$winner_name = 'Usuario #' . $winner_id;
		}

		// Obtener email e información de pedido desde WooCommerce
		$winner_email = '';
		$order_id = null;
		$purchase_summary = '';

		// Buscar la última orden del usuario que coincida con los criterios del sorteo
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Convertir estados para la consulta
		$statuses_for_query = array();
		if (is_array($order_statuses)) {
			foreach ($order_statuses as $status) {
				$clean_status = str_replace('wc-', '', $status);
				$statuses_for_query[] = $clean_status;
			}
		}

		$order_args = array(
			'customer_id' => $winner_id,
			'status' => $statuses_for_query,
			'limit' => 10, // Buscar en las últimas 10 órdenes
			'orderby' => 'date',
			'order' => 'DESC'
		);

		if ($periodo_inicio && $periodo_fin) {
			$order_args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}

		$orders = wc_get_orders($order_args);

		// Buscar una orden que contenga productos elegibles para el sorteo
		foreach ($orders as $order) {
			$order_items = array();
			$has_eligible_product = false;

			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				$product_name = $item->get_name();
				$quantity = $item->get_quantity();

				// Verificar si este producto es elegible para el sorteo
				$is_eligible = false;

				if ($productos) {
					$productos_array = is_array($productos) ? $productos : explode(',', $productos);
					$productos_array = array_map('intval', $productos_array);
					if (in_array($product_id, $productos_array)) {
						$is_eligible = true;
					}
				}

				if (!$is_eligible && $categorias) {
					$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
					$selected_categories = array_map('intval', $selected_categories);

					if (!empty(array_intersect($terms, $selected_categories))) {
						$is_eligible = true;
					}
				}

				if ($is_eligible) {
					$has_eligible_product = true;
					$order_items[] = $product_name . ($quantity > 1 ? " (x{$quantity})" : '');
				}
			}

			// Si encontramos una orden con productos elegibles, usar esa
			if ($has_eligible_product) {
				$winner_email = $order->get_billing_email();
				$order_id = $order->get_id();
				$purchase_summary = implode(', ', $order_items);
				break;
			}
		}

		// Fallback al email del usuario si no hay órdenes elegibles
		if (empty($winner_email) && $user_data) {
			$winner_email = $user_data->user_email;
		}

		if (empty($purchase_summary)) {
			$purchase_summary = 'No se encontraron detalles de compra';
		}

		$draw_data = array(
			'winner_id' => $winner_id,
			'winner_name' => $winner_name,
			'winner_email' => $winner_email,
			'date' => current_time('mysql'),
			'timestamp' => time(),
			'type' => $type, // 'manual' o 'automatic'
			'periodo_inicio' => get_option('sorteo_sco_periodo_inicio'),
			'periodo_fin' => get_option('sorteo_sco_periodo_fin'),
			'prize_price' => floatval(get_option('sorteo_sco_prize_price', 0)), // Precio del premio actual
			'prize_name' => get_option('sorteo_sco_prize_name', 'Premio sin nombre'), // Nombre del premio actual
			'order_id' => $order_id, // ID de la orden de compra
			'purchase_summary' => $purchase_summary // Resumen de lo que compró
		);

		// Agregar al historial
		$draws_history[] = $draw_data;

		// Mantener solo los últimos 100 sorteos para evitar que la base de datos se llene demasiado
		if (count($draws_history) > 100) {
			$draws_history = array_slice($draws_history, -100);
		}

		update_option('sorteo_sco_draws_history', $draws_history);

		// Actualizar métricas
		$this->update_metrics();
	}

	/**
	 * Actualizar métricas del plugin
	 */
	public function update_metrics()
	{
		$draws_history = get_option('sorteo_sco_draws_history', array());

		// Contar sorteos realizados
		$total_draws = count($draws_history);
		update_option('sorteo_sco_total_draws', $total_draws);

		// Calcular ganancia total (suma de todas las compras en el periodo)
		$this->calculate_total_earnings();

		// Guardar timestamp de última actualización
		update_option('sorteo_sco_last_metrics_update', time());
	}

	/**
	 * Actualizar métricas cuando cambia el estado de un pedido
	 */
	public function update_metrics_on_order_change($order_id, $old_status, $new_status)
	{
		// Obtener estados configurados
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Normalizar estados (pueden venir con o sin prefijo 'wc-')
		$normalized_new_status = str_replace('wc-', '', $new_status);
		$normalized_old_status = str_replace('wc-', '', $old_status);

		// Crear array con ambas versiones de cada estado configurado
		$relevant_statuses = array();
		foreach ($order_statuses as $status) {
			$clean_status = str_replace('wc-', '', $status);
			$relevant_statuses[] = $clean_status;
			$relevant_statuses[] = 'wc-' . $clean_status;
		}

		// Verificar si el nuevo estado está en los estados configurados
		if (in_array($normalized_new_status, $relevant_statuses) || in_array($new_status, $relevant_statuses)) {
			$this->update_metrics();

			// Verificar si debe ejecutar sorteo automático
			$this->check_and_execute_auto_draw($order_id, $normalized_new_status);
		}
	}

	/**
	 * Actualizar métricas cuando cambia el estado de un pedido
	 * Compatible con HPOS
	 */
	public function update_metrics_on_post_transition($new_status, $old_status, $post)
	{
		// Solo procesar si es un pedido de WooCommerce
		// Compatible con HPOS: verificar tanto posts como orders
		if (isset($post->post_type) && $post->post_type === 'shop_order') {
			$this->update_metrics();
		}
	}

	/**
	 * Verificar y ejecutar sorteo automático cuando corresponda
	 */
	public function check_and_execute_auto_draw($order_id, $new_status)
	{
		// Obtener estados configurados y verificar si el nuevo estado es relevante
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Normalizar estados configurados
		$relevant_statuses = array();
		foreach ($order_statuses as $status) {
			$clean_status = str_replace('wc-', '', $status);
			$relevant_statuses[] = $clean_status;
		}

		if (!in_array($new_status, $relevant_statuses)) {
			return;
		}

		// Obtener configuración
		$min_ganancia = floatval(get_option('sorteo_sco_min_ganancia', 0));
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');

		// Verificar si hay configuración de categorías o productos
		if (empty($categorias) && empty($productos)) {
			error_log('Sorteo SCO: ERROR - No hay categorías ni productos configurados');
			return;
		}

		// Verificar si el pedido contiene productos de categoría especial
		$order = wc_get_order($order_id);
		if (!$order) {
			error_log('Sorteo SCO: ERROR - Pedido no encontrado');
			return;
		}

		// VALIDAR PERÍODO: Si hay período configurado, el pedido DEBE estar dentro del rango
		if ($periodo_inicio && $periodo_fin) {
			$order_date = $order->get_date_created()->getTimestamp();
			$inicio_timestamp = strtotime($periodo_inicio);
			$fin_timestamp = strtotime($periodo_fin . ' 23:59:59');

			if ($order_date < $inicio_timestamp || $order_date > $fin_timestamp) {
				return;
			}
		}

		$contains_special_products = false;
		foreach ($order->get_items() as $item) {
			$product_id = $item->get_product_id();

			// Verificar productos especiales
			if ($productos) {
				$productos_array = is_array($productos) ? $productos : explode(',', $productos);
				$productos_array = array_map('intval', $productos_array);

				if (in_array($product_id, $productos_array)) {
					$contains_special_products = true;
					break;
				}
			}

			// Verificar categorías especiales
			if ($categorias) {
				$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
				$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
				$selected_categories = array_map('intval', $selected_categories);

				if (!empty(array_intersect($terms, $selected_categories))) {
					$contains_special_products = true;
					break;
				}
			}
		}

		if (!$contains_special_products) {
			return;
		}

		// Lógica de sorteo automático
		if ($min_ganancia <= 0) {
			// Si mínimo es 0 o nulo, ejecutar sorteo automático inmediatamente
			$this->execute_automatic_draw_for_order($order);
		} else {
			// Si hay mínimo configurado, verificar si se alcanzó o superó
			$current_earnings = floatval(get_option('sorteo_sco_total_earnings', 0));

			if ($current_earnings >= $min_ganancia) {
				$this->execute_automatic_draw_for_threshold();
			}
		}
	}

	/**
	 * Ejecutar sorteo automático inmediato para un pedido específico (cuando mínimo es 0)
	 */
	private function execute_automatic_draw_for_order($order)
	{
		$order_id = $order->get_id();

		// Obtener nombre y email del billing (funciona con guest checkout)
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$winner_name = trim($first_name . ' ' . $last_name);
		$winner_email = $order->get_billing_email();

		if (empty($winner_email)) {
			error_log('Sorteo SCO: ERROR - Pedido #' . $order_id . ' sin email');
			return;
		}

		// Usar user_id si existe, sino 0 para guest
		$user_id = $order->get_user_id();
		if (!$user_id) {
			$user_id = 0;
		}

		// Obtener información del premio y período
		$prize_name = get_option('sorteo_sco_prize_name', 'Premio del sorteo');
		$prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio', '');
		$periodo_fin = get_option('sorteo_sco_periodo_fin', '');

		// Registrar el sorteo
		$draw_data = array(
			'winner_name' => $winner_name,
			'winner_email' => $winner_email,
			'winner_id' => $user_id,
			'date' => current_time('mysql'),
			'prize_name' => $prize_name,
			'prize_price' => $prize_price,
			'periodo_inicio' => $periodo_inicio,
			'periodo_fin' => $periodo_fin,
			'type' => 'automatic_immediate',
			'order_id' => $order_id
		);

		// Guardar en historial
		$draws_history = get_option('sorteo_sco_draws_history', array());
		$draws_history[] = $draw_data;
		update_option('sorteo_sco_draws_history', $draws_history);
		update_option('sorteo_sco_total_draws', count($draws_history));

		// Actualizar métricas (ganancia total, costos, etc.)
		$this->update_metrics();

		// Marcar el pedido directamente con meta data (más confiable que cookies/sesiones)
		update_post_meta($order_id, '_sorteo_winner', '1');
		update_post_meta($order_id, '_sorteo_winner_timestamp', time());
		update_post_meta($order_id, '_sorteo_winner_shown', '0');

		// Para usuarios registrados, también guardar en user_meta
		if ($user_id > 0) {
			update_user_meta($user_id, '_sorteo_show_notice', time());
			update_user_meta($user_id, '_sorteo_auto_winner', time());
		}

		// Intentar guardar en sesión WC si está disponible
		if (function_exists('WC') && WC()->session) {
			try {
				WC()->session->set('sorteo_winner_notice', array(
					'order_id' => $order_id,
					'timestamp' => time(),
					'shown' => false
				));
			} catch (Exception $e) {
				error_log('Sorteo SCO: ERROR al guardar sesión WC: ' . $e->getMessage());
			}
		}

		// Cookie con paths más permisivos para producción
		@setcookie('sorteo_winner_' . $order_id, '1', time() + 3600, '/', '', is_ssl(), false);

		// Enviar email al ganador usando el order_id
		if (class_exists('Sorteo_SCO_Email')) {
			$email_sent = Sorteo_SCO_Email::send_winner_email($order_id, $prize_name);
			if (!$email_sent) {
				error_log('Sorteo SCO: ERROR - Falló envío de email para pedido #' . $order_id);
			}
		} else {
			error_log('Sorteo SCO: ERROR - Clase Sorteo_SCO_Email no existe');
		}

		// Enviar notificación por email a administradores
		$this->send_admin_notification_auto_draw($draw_data);
	}

	/**
	 * Ejecutar sorteo automático cuando se alcanza el umbral de ganancia
	 */
	private function execute_automatic_draw_for_threshold()
	{
		// Verificar que no se haya ejecutado un sorteo recientemente (evitar duplicados)
		$last_draw_time = get_option('sorteo_sco_last_auto_draw', 0);
		if ((time() - $last_draw_time) < 60) { // No ejecutar si ya se ejecutó en el último minuto
			return;
		}

		// Ejecutar el sorteo usando la función existente
		$result = $this->run_automatic_draw();

		if ($result) {
			// Actualizar timestamp del último sorteo automático
			update_option('sorteo_sco_last_auto_draw', time());
		}
	}

	/**
	 * Enviar notificación por email cuando se ejecuta sorteo automático
	 */
	private function send_admin_notification_auto_draw($draw_data)
	{
		$admins = get_users(array('role' => 'administrator'));
		if (empty($admins)) return;

		$site_name = get_bloginfo('name');
		$subject = sprintf('[%s] Sorteo Automático Ejecutado', $site_name);

		$message = sprintf(
			"<html><body style='font-family: Arial, sans-serif; line-height: 1.6;'>
			<h2 style='color: #2271b1;'>Se ha ejecutado un sorteo automático</h2>
			<table style='border-collapse: collapse; width: 100%%; max-width: 600px;'>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Ganador:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>%s (%s)</td></tr>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Premio:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>%s</td></tr>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Valor:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>%s</td></tr>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Fecha:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>%s</td></tr>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Tipo:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>%s</td></tr>
				<tr><td style='padding: 10px; border-bottom: 1px solid #ddd;'><strong>Pedido:</strong></td><td style='padding: 10px; border-bottom: 1px solid #ddd;'>#%s</td></tr>
			</table>
			</body></html>",
			esc_html($draw_data['winner_name']),
			esc_html($draw_data['winner_email']),
			esc_html($draw_data['prize_name']),
			sorteo_sco_format_price($draw_data['prize_price']),
			esc_html($draw_data['date']),
			esc_html($draw_data['type']),
			esc_html($draw_data['order_id'])
		);

		$headers = array('Content-Type: text/html; charset=UTF-8');

		foreach ($admins as $admin) {
			wp_mail($admin->user_email, $subject, $message, $headers);
		}
	}

	/**
	 * Actualizar métricas ocasionalmente en admin_init para asegurar sincronización
	 */
	public function update_metrics_on_admin_init()
	{
		// Solo actualizar si estamos en la página del plugin o no se han actualizado recientemente
		$current_screen = get_current_screen();
		if ($current_screen && strpos($current_screen->id, 'sorteo-sco') !== false) {
			// Verificar si hace más de 5 minutos que no se actualizan
			$last_update = get_option('sorteo_sco_last_metrics_update', 0);
			if (time() - $last_update > 300) { // 5 minutos = 300 segundos
				$this->update_metrics();
				update_option('sorteo_sco_last_metrics_update', time());
			}
		}
	}

	/**
	 * Calcular ganancia total del periodo actual
	 */
	private function calculate_total_earnings()
	{
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		if (!$periodo_inicio || !$periodo_fin) {
			update_option('sorteo_sco_total_earnings', 0);
			return;
		}

		// Convertir estados de 'wc-completed' a 'completed' para wc_get_orders
		$statuses_for_query = array();
		if (is_array($order_statuses)) {
			foreach ($order_statuses as $status) {
				// Quitar el prefijo 'wc-' si existe
				$clean_status = str_replace('wc-', '', $status);
				$statuses_for_query[] = $clean_status;
			}
		}

		$args = array(
			'status' => $statuses_for_query,
			'date_created' => $periodo_inicio . '...' . $periodo_fin,
			'limit' => -1,
		);

		$orders = wc_get_orders($args);
		$total_earnings = 0;

		foreach ($orders as $order) {
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				$item_total = $item->get_total();

				// Verificar si el producto está en las categorías o productos especiales
				$include_item = false;

				if ($productos) {
					$productos_array = is_array($productos) ? $productos : explode(',', $productos);
					$productos_array = array_map('intval', $productos_array);
					if (in_array($product_id, $productos_array)) {
						$include_item = true;
					}
				}

				if (!$include_item && $categorias) {
					$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
					$selected_categories = array_map('intval', $selected_categories);

					if (!empty(array_intersect($terms, $selected_categories))) {
						$include_item = true;
					}
				}

				if ($include_item) {
					$total_earnings += floatval($item_total);
				}
			}
		}

		update_option('sorteo_sco_total_earnings', $total_earnings);
	}

	/**
	 * Calcula las ganancias netas restando el costo de los premios entregados
	 */
	public function calculate_net_earnings()
	{
		$total_earnings = get_option('sorteo_sco_total_earnings', 0);
		$draws_history = get_option('sorteo_sco_draws_history', array());

		$total_prizes_cost = 0;

		// Sumar el costo de todos los premios entregados
		foreach ($draws_history as $draw) {
			if (isset($draw['prize_price'])) {
				$total_prizes_cost += floatval($draw['prize_price']);
			}
		}

		$net_earnings = $total_earnings - $total_prizes_cost;

		return array(
			'gross_earnings' => $total_earnings,
			'total_prizes_cost' => $total_prizes_cost,
			'net_earnings' => $net_earnings
		);
	}

	/**
	 * Ejecuta sorteo automático cuando se alcanza el mínimo de ganancia en el periodo/categoría
	 */
	public function maybe_run_auto_draw()
	{
		$min_ganancia = get_option('sorteo_sco_min_ganancia');
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		$args = [
			'limit' => -1,
			'status' => $order_statuses,
		];

		// Solo agregar filtro de fecha si ambas fechas están configuradas
		if ($periodo_inicio && $periodo_fin) {
			$args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}

		$orders = function_exists('wc_get_orders') ? wc_get_orders($args) : [];
		$total = 0;
		foreach ($orders as $order) {
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				if ($productos && !in_array($product_id, explode(',', $productos))) continue;
				if ($categorias) {
					$terms = function_exists('wp_get_post_terms') ? wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids')) : [];
					$cat_ids = explode(',', $categorias);
					if (!array_intersect($terms, $cat_ids)) continue;
				}
				$total += $order->get_total();
			}
		}
		if ($min_ganancia && $total >= $min_ganancia) {
			// Ejecutar sorteo automático
			$this->run_automatic_draw();
		}
	}

	/**
	 * Ejecutar sorteo automático
	 */
	private function run_automatic_draw()
	{
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		if (!$periodo_inicio || !$periodo_fin) return false;
		if (!$categorias && !$productos) return false;

		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		$args = array(
			'status' => $order_statuses,
			'date_created' => $periodo_inicio . '...' . $periodo_fin,
			'limit' => -1,
		);

		$orders = wc_get_orders($args);
		$eligible_orders = array();

		foreach ($orders as $order) {
			$order_eligible = false;
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();

				if ($productos) {
					$productos_array = is_array($productos) ? $productos : explode(',', $productos);
					$productos_array = array_map('intval', $productos_array);
					if (in_array($product_id, $productos_array)) {
						$order_eligible = true;
						break;
					}
				}

				if ($categorias) {
					$terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
					$selected_categories = array_map('intval', $selected_categories);

					if (!empty(array_intersect($terms, $selected_categories))) {
						$order_eligible = true;
						break;
					}
				}
			}

			if ($order_eligible) {
				$eligible_orders[] = $order->get_id();
			}
		}

		if (empty($eligible_orders)) return false;

		$winner_order_id = $eligible_orders[array_rand($eligible_orders)];
		$winner_order = wc_get_order($winner_order_id);
		if (!$winner_order) return false;

		$winner_user_id = $winner_order->get_user_id();
		update_option('sorteo_sco_last_winner', $winner_user_id);
		$this->save_draw_result($winner_user_id, 'automatic');

		if (class_exists('Sorteo_SCO_Email')) {
			$prize = __('Premio sorpresa', 'sorteo-sco');
			Sorteo_SCO_Email::send_winner_email($winner_order_id, $prize);
		}

		return true;
	}

	/**
	 * Mostrar aviso personalizado en la tienda tras comprar el producto especial
	 */
	public function show_winner_notice()
	{
		$user_id = is_user_logged_in() ? get_current_user_id() : 0;
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Si no hay categorías ni productos configurados, no mostrar nada
		if (empty($categorias) && empty($productos)) {
			return;
		}

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		$should_show = false;

		// Para usuarios registrados: verificar compras
		if ($user_id > 0) {
			$should_show = $this->check_user_purchased_sorteo_products($user_id, $categorias, $productos, $order_statuses);
		} else {
			// Para invitados: verificar sesión o cookie
			$should_show = $this->check_guest_winner_status();
		}

		if ($should_show) {
			$notice_raw = get_option('sorteo_sco_aviso_personalizado');
			$notice = $user_id > 0 ? $this->process_custom_fields($notice_raw, $user_id) : $this->process_custom_fields_for_guest($notice_raw);
			$notice = do_shortcode($notice);
			if ($notice) {
				// Opciones de diseño del mensaje
				$titulo = get_option('sorteo_sco_mensaje_titulo', '¡Felicidades!');
				$bg_color = get_option('sorteo_sco_mensaje_bg_color', '#4caf50');
				$text_color = get_option('sorteo_sco_mensaje_text_color', '#ffffff');
				$position = get_option('sorteo_sco_mensaje_position', 'top');
				$font_family = get_option('sorteo_sco_mensaje_font_family', 'inherit');
				$effect = get_option('sorteo_sco_mensaje_effect', 'none');

				include plugin_dir_path(__FILE__) . '../templates/winner-notice.php';
			} else {
				error_log('Sorteo SCO: ERROR - Mensaje vacío después de procesar');
			}
		}
	}

	/**
	 * Mostrar aviso global en el head para toda la página
	 */
	public function show_winner_notice_global()
	{
		if (!is_user_logged_in()) return;

		$user_id = get_current_user_id();
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		if (!$categorias && !$productos) return;

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		// Verificar si el usuario ha comprado productos de la categoría o productos especiales
		$has_purchased = $this->check_user_purchased_sorteo_products($user_id, $categorias, $productos, $order_statuses);

		if ($has_purchased) {
			$notice_raw = get_option('sorteo_sco_aviso_personalizado');
			$notice = $this->process_custom_fields($notice_raw, $user_id);
			if ($notice) {
				// Solo agregar un pequeño CSS básico si no se han cargado los estilos aún
				echo '<style>.sorteo-notice-global { position: fixed; top: 0; left: 0; right: 0; z-index: 9999; text-align: center; padding: 15px; } .sorteo-notice-global p { margin: 0; }</style>';
			}
		}
	}

	/**
	 * Mostrar aviso en el footer si el usuario ha comprado productos del sorteo
	 */
	public function show_winner_notice_footer()
	{
		if (!is_user_logged_in()) return;

		$user_id = get_current_user_id();
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		if (!$categorias && !$productos) return;

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		// Verificar si el usuario ha comprado productos de la categoría o productos especiales
		$has_purchased = $this->check_user_purchased_sorteo_products($user_id, $categorias, $productos, $order_statuses);

		if ($has_purchased) {
			$notice_raw = get_option('sorteo_sco_aviso_personalizado');
			$notice = $this->process_custom_fields($notice_raw, $user_id);
			$notice = do_shortcode($notice);
			if ($notice) {
				echo '<div class="sorteo-notice-global">';
				echo '<p>' . wp_kses_post($notice) . '</p>';
				echo '<button class="sorteo-notice-close" onclick="this.parentElement.style.display=\'none\'">×</button>';
				echo '</div>';
			}
		}
	}

	/**
	 * Función auxiliar para verificar si el usuario ha comprado productos del sorteo
	 */
	private function check_user_purchased_sorteo_products($user_id, $categorias, $productos, $order_statuses)
	{
		// Validar que el usuario existe
		if (!$user_id || $user_id <= 0) {
			return false;
		}

		// Validar que hay categorías o productos configurados
		if (empty($categorias) && empty($productos)) {
			return false;
		}

		// Validar que hay estados de orden configurados
		if (empty($order_statuses) || !is_array($order_statuses)) {
			return false;
		}

		// Obtener órdenes del usuario con los estados configurados
		$customer_orders = wc_get_orders(array(
			'customer' => $user_id,
			'status' => $order_statuses,
			'limit' => -1,
		));

		// Si no hay órdenes, no ha comprado nada
		if (empty($customer_orders)) {
			return false;
		}

		foreach ($customer_orders as $order) {
			// Verificar que la orden es válida
			if (!$order || !is_object($order)) {
				continue;
			}

			// CRÍTICO: Solo mostrar mensaje si este pedido específico es ganador
			$is_winner = get_post_meta($order->get_id(), '_sorteo_winner', true);
			if ($is_winner !== '1') {
				continue; // Este pedido no es ganador, saltar
			}

			// Verificar si ya se mostró el mensaje para este pedido
			$already_shown = get_post_meta($order->get_id(), '_sorteo_winner_shown', true);
			if ($already_shown === '1') {
				continue; // Ya se mostró, saltar
			}

			$order_items = $order->get_items();
			if (empty($order_items)) {
				continue;
			}

			foreach ($order_items as $item) {
				$product_id = $item->get_product_id();

				// Validar que el producto existe
				if (!$product_id || $product_id <= 0) {
					continue;
				}

				// Verificar si el producto está en la lista de productos especiales
				if (!empty($productos)) {
					$productos_array = is_array($productos) ? $productos : explode(',', $productos);
					$productos_array = array_map('intval', array_filter($productos_array));
					if (!empty($productos_array) && in_array($product_id, $productos_array)) {
						// Marcar como mostrado antes de retornar
						update_post_meta($order->get_id(), '_sorteo_winner_shown', '1');
						return true;
					}
				}

				// Verificar si el producto pertenece a una categoría seleccionada
				if (!empty($categorias)) {
					$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
					if (is_wp_error($product_categories)) {
						continue;
					}

					$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
					$selected_categories = array_map('intval', array_filter($selected_categories));

					if (!empty($selected_categories) && !empty($product_categories) && !empty(array_intersect($product_categories, $selected_categories))) {
						// Marcar como mostrado antes de retornar
						update_post_meta($order->get_id(), '_sorteo_winner_shown', '1');
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Verificar si un invitado es ganador (usando sesión o cookie)
	 */
	private function check_guest_winner_status()
	{
		// MÉTODO 1: Verificar pedidos recientes del usuario actual por email de billing
		if (is_user_logged_in()) {
			$user = wp_get_current_user();
			$user_email = $user->user_email;
		} else {
			// Para invitados, intentar obtener email de la última sesión
			$user_email = WC()->session ? WC()->session->get('customer_email', '') : '';
		}

		if (!empty($user_email)) {
			// Buscar pedidos recientes (últimas 2 horas) con este email que sean ganadores
			$recent_orders = wc_get_orders(array(
				'billing_email' => $user_email,
				'date_created' => '>' . (time() - 7200), // Últimas 2 horas
				'limit' => 5,
				'orderby' => 'date',
				'order' => 'DESC'
			));

			foreach ($recent_orders as $order) {
				$is_winner = get_post_meta($order->get_id(), '_sorteo_winner', true);
				$shown = get_post_meta($order->get_id(), '_sorteo_winner_shown', true);

				if ($is_winner === '1' && $shown !== '1') {
					// Marcar como mostrado
					update_post_meta($order->get_id(), '_sorteo_winner_shown', '1');
					return true;
				}
			}
		}

		// MÉTODO 2: Verificar sesión de WooCommerce
		if (function_exists('WC') && WC()->session) {
			$winner_data = WC()->session->get('sorteo_winner_notice');

			if ($winner_data && isset($winner_data['shown']) && !$winner_data['shown']) {
				$winner_data['shown'] = true;
				WC()->session->set('sorteo_winner_notice', $winner_data);
				return true;
			}
		}

		// MÉTODO 3: Fallback con cookies
		if (isset($_COOKIE) && !empty($_COOKIE)) {
			foreach ($_COOKIE as $key => $value) {
				if (strpos($key, 'sorteo_winner_') === 0 && $value == '1') {
					@setcookie($key, '', time() - 3600, '/', '', is_ssl(), false);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Procesar campos personalizados para invitados (sin user_id)
	 */
	private function process_custom_fields_for_guest($message)
	{
		$message = wp_specialchars_decode((string) $message);

		// Obtener datos del premio
		$prize_name = get_option('sorteo_sco_prize_name', 'Premio sorpresa');
		$prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
		$prize_value = sorteo_sco_format_price($prize_price);

		// Obtener fecha actual
		$fecha_sorteo = date_i18n('d/m/Y H:i');

		// Obtener nombre del sitio
		$site_name = get_bloginfo('name');

		// Reemplazos disponibles (sin nombre específico para invitados)
		$replacements = array(
			'{nombre}' => __('Ganador', 'sorteo-sco'),
			'{premio}' => $prize_name,
			'{valor}' => $prize_value,
			'{fecha}' => $fecha_sorteo,
			'{sitio}' => $site_name,
			'{nombre_usuario}' => __('Ganador', 'sorteo-sco'),
			'{nombre_premio}' => $prize_name,
			'{valor_premio}' => $prize_value,
			'{fecha_sorteo}' => $fecha_sorteo,
			'{nombre_sitio}' => $site_name
		);

		return str_replace(array_keys($replacements), array_values($replacements), $message);
	}

	/**
	 * Agregar marco visual en la página de la tienda (listado de productos)
	 */
	public function add_product_frame_shop()
	{
		$frame_url = get_option('sorteo_sco_marco_visual');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');

		if (!$frame_url) return;

		global $product;
		if (!$product) return;

		$show = false;
		$product_id = $product->get_id();

		// Verificar si el producto está en la lista de productos especiales
		if ($productos) {
			$productos_array = is_array($productos) ? $productos : explode(',', $productos);
			$productos_array = array_map('intval', $productos_array); // Asegurar que son enteros
			if (in_array($product_id, $productos_array)) {
				$show = true;
			}
		}

		// Verificar si el producto pertenece a una categoría seleccionada
		if ($categorias) {
			$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
			$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
			$selected_categories = array_map('intval', $selected_categories); // Asegurar que son enteros

			if (!empty(array_intersect($product_categories, $selected_categories))) {
				$show = true;
			}
		}

		if ($show) {
			// Usar JavaScript para mover el marco dentro del enlace de la imagen
			ob_start();
?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Buscar el producto actual y mover el marco al enlace correcto
					setTimeout(function() {
						$('.product[data-product-id="<?php echo $product_id; ?>"], .product.post-<?php echo $product_id; ?>').each(function() {
							var $product = $(this);
							var $overlay = $product.find('.sorteo-sco-frame-overlay');
							var $imageLink = $product.find('.woocommerce-loop-product__link').first();

							if ($overlay.length && $imageLink.length) {
								// Asegurar posición relativa en el enlace
								$imageLink.css('position', 'relative');

								// Mover el marco dentro del enlace y ajustar estilos
								$overlay.detach().appendTo($imageLink).css({
									'position': 'absolute',
									'top': '5px',
									'left': '5px',
									'z-index': '10',
									'pointer-events': 'none'
								});

								// Ajustar tamaño de la imagen del marco
								$overlay.find('.sorteo-frame-image').css({
									'width': '50px',
									'height': 'auto',
									'opacity': '0.95'
								});
							}
						});
					}, 100);
				});
			</script>
			<div class="sorteo-sco-frame-overlay">
				<img src="<?php echo esc_url($frame_url); ?>" alt="Marco especial" class="sorteo-frame-image" />
			</div>
		<?php
			echo ob_get_clean();
		}
	}

	/**
	 * Cargar CSS y JS del frontend
	 */
	public function enqueue_frontend_assets()
	{
		// CSS del frontend
		wp_enqueue_style(
			'sorteo-sco-frontend',
			plugin_dir_url(__FILE__) . '../assets/css/sorteo-frontend.css',
			array(),
			'1.4.1'
		);

		// Agregar estilos dinámicos para el mensaje personalizable
		$this->add_dynamic_message_styles();
	}

	/**
	 * Agregar estilos dinámicos para el mensaje personalizable
	 */
	private function add_dynamic_message_styles()
	{
		$bg_color = get_option('sorteo_sco_mensaje_bg_color', '#4caf50');
		$text_color = get_option('sorteo_sco_mensaje_text_color', '#ffffff');
		$font_family = get_option('sorteo_sco_mensaje_font_family', 'inherit');
		$position = get_option('sorteo_sco_mensaje_position', 'top');
		$effect = get_option('sorteo_sco_mensaje_effect', 'none');

		// Posicionamiento basado en la configuración
		$position_css = '';
		switch ($position) {
			case 'top':
				$position_css = 'top: 20px; bottom: auto;';
				break;
			case 'center':
				$position_css = 'top: 50%; bottom: auto; transform: translateY(-50%);';
				break;
			case 'bottom':
				$position_css = 'bottom: 20px; top: auto;';
				break;
			default:
				$position_css = 'top: 20px; bottom: auto;';
		}

		// Efectos CSS
		$effect_css = '';
		$keyframes = '';
		switch ($effect) {
			case 'fade':
				$effect_css = 'animation: sorteoFadeIn 0.5s ease-in;';
				$keyframes = '@keyframes sorteoFadeIn { from { opacity: 0; } to { opacity: 1; } }';
				break;
			case 'slide':
				$effect_css = 'animation: sorteoSlideIn 0.5s ease-out;';
				$keyframes = '@keyframes sorteoSlideIn { from { transform: translateY(-100%); } to { transform: translateY(0); } }';
				break;
			case 'bounce':
				$effect_css = 'animation: sorteoBounce 1s ease;';
				$keyframes = '@keyframes sorteoBounce { 0%, 60%, 75%, 90%, 100% { transform: translateY(0); } 15% { transform: translateY(-30px); } 30% { transform: translateY(-15px); } 45% { transform: translateY(-5px); } }';
				break;
			case 'pulse':
				$effect_css = 'animation: sorteoPulse 2s infinite;';
				$keyframes = '@keyframes sorteoPulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }';
				break;
			case 'shake':
				$effect_css = 'animation: sorteoShake 0.8s ease-in-out;';
				$keyframes = '@keyframes sorteoShake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); } 20%, 40%, 60%, 80% { transform: translateX(10px); } }';
				break;
		}

		$custom_css = "
		{$keyframes}
		.sorteo-notice-global, .sorteo-immediate-notice {
			background: {$bg_color} !important;
			color: {$text_color} !important;
			font-family: {$font_family} !important;
			{$position_css}
			{$effect_css}
			position: fixed;
			right: 0;
			left: 0;
            margin: 0 auto;
			z-index: 9999;
			max-width: 400px;
			padding: 20px;
			border-radius: 5px;
			box-shadow: 0 4px 8px rgba(0,0,0,0.1);
		}
		.sorteo-notice-close {
			color: {$text_color} !important;
			border-color: rgba(255, 255, 255, 0.5) !important;
		}
		.sorteo-notice-close:hover {
			background: rgba(255, 255, 255, 0.2) !important;
			border-color: rgba(255, 255, 255, 0.8) !important;
		}
		";

		wp_add_inline_style('sorteo-sco-frontend', $custom_css);
	}

	/**
	 * Agregar marco visual en página individual del producto
	 */
	public function add_product_frame_single()
	{
		// Solo ejecutar en páginas de producto individual y en el contexto correcto
		if (!is_product() || is_admin()) return;

		$frame_url = get_option('sorteo_sco_marco_visual');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');

		if (!$frame_url) return;

		global $product;
		if (!$product || !is_object($product)) return;

		$show = false;
		$product_id = $product->get_id();

		// Verificar si el producto está en la lista de productos especiales
		if ($productos) {
			$productos_array = is_array($productos) ? $productos : explode(',', $productos);
			$productos_array = array_map('intval', $productos_array);
			if (in_array($product_id, $productos_array)) {
				$show = true;
			}
		}

		// Verificar si el producto pertenece a una categoría seleccionada
		if ($categorias) {
			$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
			$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
			$selected_categories = array_map('intval', $selected_categories);

			if (!empty(array_intersect($product_categories, $selected_categories))) {
				$show = true;
			}
		}

		if ($show) {
			ob_start();
		?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Buscar todos los enlaces de productos en loops/grids
					$('.woocommerce-loop-product__link').each(function() {
						var $link = $(this);

						// Verificar si ya tiene marco para evitar duplicados
						if ($link.find('.sorteo-sco-frame-single').length === 0) {
							// Crear el marco
							var frameHtml = '<div class="sorteo-sco-frame-single" style="position: absolute; top: 5px; left: 5px; z-index: 10; pointer-events: none;">' +
								'<img src="<?php echo esc_url($frame_url); ?>" alt="Marco especial" style="width: 60px; height: auto; opacity: 0.9;" />' +
								'</div>';

							// Asegurar que el enlace tenga posición relativa
							if ($link.css('position') === 'static') {
								$link.css('position', 'relative');
							}

							// Insertar el marco dentro del enlace
							$link.prepend(frameHtml);
						}
					});
				});
			</script>
		<?php
			echo ob_get_clean();
		}
	}

	/**
	 * Agregar marco visual en el carrito de compras
	 */
	public function add_product_frame_cart($product_thumbnail, $cart_item, $cart_item_key)
	{
		$frame_url = get_option('sorteo_sco_marco_visual');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');

		if (!$frame_url) return $product_thumbnail;

		$product_id = $cart_item['product_id'];
		$show = false;

		// Verificar si el producto está en la lista de productos especiales
		if ($productos) {
			$productos_array = is_array($productos) ? $productos : explode(',', $productos);
			$productos_array = array_map('intval', $productos_array);
			if (in_array($product_id, $productos_array)) {
				$show = true;
			}
		}

		// Verificar si el producto pertenece a una categoría seleccionada
		if ($categorias) {
			$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
			$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
			$selected_categories = array_map('intval', $selected_categories);

			if (!empty(array_intersect($product_categories, $selected_categories))) {
				$show = true;
			}
		}

		if ($show) {
			$thumbnail_with_frame = '<div class="sorteo-cart-item-frame">';
			$thumbnail_with_frame .= $product_thumbnail;
			$thumbnail_with_frame .= '<div class="sorteo-sco-frame-cart-overlay">';
			$thumbnail_with_frame .= '<img src="' . esc_url($frame_url) . '" alt="Marco especial" class="sorteo-frame-cart-image" />';
			$thumbnail_with_frame .= '</div>';
			$thumbnail_with_frame .= '</div>';
			return $thumbnail_with_frame;
		}

		return $product_thumbnail;
	}

	/**
	 * Script específico para Bootstrap Theme - insertar marco en la imagen del producto
	 */
	public function add_bootstrap_theme_single_product_script()
	{
		// Solo ejecutar en páginas de producto individual
		if (!is_product() || is_admin()) return;

		$frame_url = get_option('sorteo_sco_marco_visual');
		$categorias = get_option('sorteo_sco_categorias');
		$productos = get_option('sorteo_sco_productos_especiales');

		if (!$frame_url) return;

		global $product;
		if (!$product || !is_object($product)) return;

		$show = false;
		$product_id = $product->get_id();

		// Verificar si el producto está en la lista de productos especiales
		if ($productos) {
			$productos_array = is_array($productos) ? $productos : explode(',', $productos);
			$productos_array = array_map('intval', $productos_array);
			if (in_array($product_id, $productos_array)) {
				$show = true;
			}
		}

		// Verificar si el producto pertenece a una categoría seleccionada
		if ($categorias) {
			$product_categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
			$selected_categories = is_array($categorias) ? $categorias : explode(',', $categorias);
			$selected_categories = array_map('intval', $selected_categories);

			if (!empty(array_intersect($product_categories, $selected_categories))) {
				$show = true;
			}
		}

		if ($show) {
			ob_start();
		?>
			<script type="text/javascript">
				jQuery(document).ready(function($) {
					// Script específico para Bootstrap Theme

					// Buscar el contenedor de imagen específico del tema Bootstrap
					var $imageContainer = $('.col-md-6').first(); // Columna izquierda donde está la imagen
					var $imageWrapper = $imageContainer.find('.p-3.product-image'); // Wrapper específico del tema

					if ($imageContainer.length && $imageWrapper.length) {

						// Crear el marco con estilos específicos para Bootstrap Theme
						var frameHtml = '<div class="sorteo-bootstrap-frame">' +
							'<img src="<?php echo esc_url($frame_url); ?>" class="sorteo-frame-image" alt="Marco especial" />' +
							'</div>';

						// Insertar el marco DENTRO del wrapper de la imagen
						$imageWrapper.prepend(frameHtml);

						// Agregar animación suave
						$('.sorteo-bootstrap-frame').css({
							'animation': 'sorteoBootstrapGlow 2s ease-in-out infinite alternate',
							'opacity': '0'
						}).animate({
							'opacity': '1'
						}, 500);

					} else {

						// Fallback para otros themes o estructuras
						var fallbackHtml = '<div class="sorteo-fallback-frame">' +
							'<img src="<?php echo esc_url($frame_url); ?>" class="sorteo-frame-image" alt="Marco especial" />' +
							'</div>';

						// Intentar varias ubicaciones fallback
						if ($('.woocommerce-product-gallery').length) {
							$('.woocommerce-product-gallery').before(fallbackHtml);
						} else if ($('h1.h2.fw-bold, .product_title').length) {
							$('h1.h2.fw-bold, .product_title').before(fallbackHtml);
						} else if ($('.single-product').length) {
							$('.single-product').prepend(fallbackHtml);
						}
					}
				});
			</script>
<?php
			echo ob_get_clean();
		}
	}

	/**
	 * Verificar cambio de estado de orden y mostrar aviso si corresponde
	 */
	public function check_and_show_notice_on_status_change($order_id, $old_status, $new_status)
	{
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

		// Asegurar que order_statuses es un array
		if (!is_array($order_statuses)) {
			$order_statuses = array('wc-completed', 'wc-processing');
		}

		// Verificar si el nuevo estado está en los estados configurados
		if (in_array('wc-' . $new_status, $order_statuses)) {
			// Actualizar métricas cuando una orden cambia a un estado válido
			$this->update_metrics();

			// La lógica del mensaje ahora se maneja en check_and_execute_auto_draw
			// que se ejecuta desde update_metrics_on_order_change
		}
	}

	/**
	 * Verificar compra reciente y mostrar aviso
	 */
	public function check_recent_purchase()
	{
		if (!is_user_logged_in()) return;

		$user_id = get_current_user_id();
		$show_notice_time = get_user_meta($user_id, '_sorteo_show_notice', true);

		// Solo mostrar si la marca fue establecida en los últimos 5 minutos
		if ($show_notice_time && (time() - $show_notice_time) < 300) {
			// Verificar si efectivamente compró productos del sorteo
			$categorias = get_option('sorteo_sco_categorias');
			$productos = get_option('sorteo_sco_productos_especiales');
			$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

			if ($this->check_user_purchased_sorteo_products($user_id, $categorias, $productos, $order_statuses)) {
				// Verificar si ya registramos este evento para evitar duplicados
				$already_recorded = get_user_meta($user_id, '_sorteo_already_recorded', true);

				if (!$already_recorded) {
					// Registrar el evento en las métricas usando el user_id
					$this->save_draw_result($user_id, 'automatic');

					// Marcar como ya registrado
					update_user_meta($user_id, '_sorteo_already_recorded', time());
				}

				// Agregar JavaScript para mostrar el aviso inmediatamente
				add_action('wp_footer', function () use ($user_id) {
					$notice_raw = get_option('sorteo_sco_aviso_personalizado');
					$notice = $this->process_custom_fields($notice_raw, $user_id);
					$notice = do_shortcode($notice);
					$duration = get_option('sorteo_sco_mensaje_duration', '10') * 1000; // Convertir a milisegundos

					if ($notice) {
						echo '<script>
						document.addEventListener("DOMContentLoaded", function() {
							var notice = document.createElement("div");
							notice.className = "sorteo-immediate-notice";
							notice.innerHTML = "<p>' . esc_js(wp_kses_post($notice)) . '</p><button class=\"sorteo-notice-close\" onclick=\"this.parentElement.style.display=\'none\'\">×</button>";
							document.body.appendChild(notice);
							
							// Auto-ocultar después del tiempo configurado
							setTimeout(function() {
								if (notice.parentElement) {
									notice.style.display = "none";
								}
							}, ' . $duration . ');
						});
						</script>';
					}
					// Limpiar la marca para que no se muestre nuevamente
					delete_user_meta($user_id, '_sorteo_show_notice');
				});
			}
		}
	}

	/**
	 * Procesar campos personalizados en el mensaje
	 * Reemplaza {nombre}, {premio}, {valor}, {fecha}, {sitio}
	 */
	private function process_custom_fields($message, $user_id = null)
	{
		return self::process_custom_fields_static($message, $user_id);
	}

	/**
	 * Versión estática de process_custom_fields para uso en otras clases
	 */
	public static function process_custom_fields_static($message, $user_id = null)
	{
		$message = wp_specialchars_decode((string) $message);

		// Obtener datos del usuario ganador
		$user_name = '';
		if ($user_id) {
			$user_data = get_userdata($user_id);
			$first_name = get_user_meta($user_id, 'first_name', true);
			$last_name = get_user_meta($user_id, 'last_name', true);
			$user_name = trim($first_name . ' ' . $last_name);

			if (empty($user_name) && $user_data) {
				$user_name = $user_data->display_name;
			}
			if (empty($user_name)) {
				$user_name = 'Usuario #' . $user_id;
			}
		} else {
			$user_name = 'Estimado cliente';
		}

		// Obtener datos del premio
		$prize_name = get_option('sorteo_sco_prize_name', 'Premio sorpresa');
		$prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
		$prize_value = sorteo_sco_format_price($prize_price);

		// Obtener fecha actual
		$fecha_sorteo = date_i18n('d/m/Y H:i');

		// Obtener nombre del sitio
		$site_name = get_bloginfo('name');

		// Reemplazos disponibles
		$replacements = array(
			'{nombre}' => $user_name,
			'{premio}' => $prize_name,
			'{valor}' => $prize_value,
			'{fecha}' => $fecha_sorteo,
			'{sitio}' => $site_name,
			// Alias largos para compatibilidad
			'{nombre_usuario}' => $user_name,
			'{nombre_premio}' => $prize_name,
			'{valor_premio}' => $prize_value,
			'{fecha_sorteo}' => $fecha_sorteo,
			'{nombre_sitio}' => $site_name
		);

		// Aplicar reemplazos
		return str_replace(array_keys($replacements), array_values($replacements), $message);
	}
}
