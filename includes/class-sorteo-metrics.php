<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sorteo_SCO_Metrics {
	public static function get_metrics() {
		$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = get_option('sorteo_sco_periodo_fin');
		
		$args = [
			'limit' => -1,
			'status' => array('wc-completed','wc-processing'),
		];
		
		// Solo agregar filtro de fecha si ambas fechas están configuradas
		if ($periodo_inicio && $periodo_fin) {
			$args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}
		
		$orders = function_exists('wc_get_orders') ? wc_get_orders($args) : [];
		$ganancia = 0.0;
		$premios = 0;
		
		foreach ($orders as $order) {
			$total = $order->get_total();
			$ganancia += is_numeric($total) ? floatval($total) : 0;
		}
		
		$last_winner = get_option('sorteo_sco_last_winner');
		if ($last_winner) {
			$premios = 1; // Solo cuenta el último sorteo, se puede ampliar
		}
		
		return array(
			'ganancia' => $ganancia,
			'premios' => $premios
		);
	}

	/**
	 * Obtener ganancias por día para gráfico de línea
	 * @param int $days Número de días hacia atrás
	 * @return array Array con labels (fechas) y data (ganancias)
	 */
	public function get_earnings_by_day($days = 30) {
		$labels = array();
		$data = array();
		
		// Obtener estados de pedido configurados
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));
		if (empty($order_statuses)) {
			$order_statuses = array('completed', 'processing');
		} else {
			// Remover prefijo wc- si existe
			$order_statuses = array_map(function($status) {
				return str_replace('wc-', '', $status);
			}, $order_statuses);
		}
		
		$end_date = current_time('Y-m-d');
		$start_date = date('Y-m-d', strtotime("-{$days} days"));
		
		// Obtener pedidos usando WooCommerce API
		$orders = wc_get_orders(array(
			'limit' => -1,
			'status' => $order_statuses,
			'date_created' => $start_date . '...' . $end_date,
			'return' => 'objects'
		));
		
		// Agrupar por fecha
		$date_totals = array();
		foreach ($orders as $order) {
			$order_date = $order->get_date_created()->date('Y-m-d');
			if (!isset($date_totals[$order_date])) {
				$date_totals[$order_date] = 0;
			}
			$date_totals[$order_date] += floatval($order->get_total());
		}
		
		// Generar labels y data para todos los días
		for ($i = $days - 1; $i >= 0; $i--) {
			$date = date('Y-m-d', strtotime("-{$i} days"));
			$labels[] = date('d/m', strtotime($date));
			$data[] = isset($date_totals[$date]) ? $date_totals[$date] : 0;
		}
		
		return array(
			'labels' => $labels,
			'data' => $data
		);
	}

	/**
	 * Obtener desglose de premios por tipo para gráfico de torta
	 * @return array Array con labels y data
	 */
	public function get_prizes_breakdown() {
		$draws_history = get_option('sorteo_sco_draws_history', array());
		
		$manual_count = 0;
		$automatic_count = 0;
		
		foreach ($draws_history as $draw) {
			$type = isset($draw['type']) ? $draw['type'] : 'manual';
			if ($type === 'manual') {
				$manual_count++;
			} else {
				$automatic_count++;
			}
		}
		
		return array(
			'labels' => array(__('Manual', 'sorteo-sco'), __('Automático', 'sorteo-sco')),
			'data' => array($manual_count, $automatic_count)
		);
	}

	/**
	 * Obtener ganancias por día en un rango personalizado
	 * @param string $from Fecha inicio (Y-m-d)
	 * @param string $to Fecha fin (Y-m-d)
	 * @return array Array con labels (fechas) y data (ganancias)
	 */
	public function get_earnings_by_date_range($from, $to) {
		$labels = array();
		$data = array();
		
		// Validar fechas
		$start = strtotime($from);
		$end = strtotime($to);
		
		if (!$start || !$end || $end < $start) {
			return array('labels' => array(), 'data' => array());
		}
		
		// Obtener estados de pedido configurados
		$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));
		if (empty($order_statuses)) {
			$order_statuses = array('completed', 'processing');
		} else {
			// Remover prefijo wc- si existe
			$order_statuses = array_map(function($status) {
				return str_replace('wc-', '', $status);
			}, $order_statuses);
		}
		
		// Obtener pedidos usando WooCommerce API
		$orders = wc_get_orders(array(
			'limit' => -1,
			'status' => $order_statuses,
			'date_created' => $from . '...' . $to,
			'return' => 'objects'
		));
		
		// Agrupar por fecha
		$date_totals = array();
		foreach ($orders as $order) {
			$order_date = $order->get_date_created()->date('Y-m-d');
			if (!isset($date_totals[$order_date])) {
				$date_totals[$order_date] = 0;
			}
			$date_totals[$order_date] += floatval($order->get_total());
		}
		
		// Generar labels y data para todos los días en el rango
		$current = $start;
		while ($current <= $end) {
			$date = date('Y-m-d', $current);
			$labels[] = date('d/m', $current);
			$data[] = isset($date_totals[$date]) ? $date_totals[$date] : 0;
			$current = strtotime('+1 day', $current);
		}
		
		return array(
			'labels' => $labels,
			'data' => $data
		);
	}
}

