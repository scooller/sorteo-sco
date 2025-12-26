<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sorteo_SCO_Export {
	public static function export_winners( $args = array() ) {
		// Exporta ganadores en CSV por periodo/categoría
		$periodo_inicio = isset($args['periodo_inicio']) ? $args['periodo_inicio'] : get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = isset($args['periodo_fin']) ? $args['periodo_fin'] : get_option('sorteo_sco_periodo_fin');
		$categorias = isset($args['categorias']) ? $args['categorias'] : get_option('sorteo_sco_categorias');
		$productos = isset($args['productos']) ? $args['productos'] : get_option('sorteo_sco_productos_especiales');
		
		$args_wc = [
			'limit' => -1,
			'status' => array('wc-completed','wc-processing'),
		];
		
		// Solo agregar filtro de fecha si ambas fechas están configuradas
		if ($periodo_inicio && $periodo_fin) {
			$args_wc['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}
		
		$orders = function_exists('wc_get_orders') ? wc_get_orders($args_wc) : [];
		$winners = [];
		foreach ($orders as $order) {
			foreach ($order->get_items() as $item) {
				$product_id = $item->get_product_id();
				if ($productos && !in_array($product_id, explode(',', $productos))) continue;
				if ($categorias) {
					$terms = function_exists('wp_get_post_terms') ? wp_get_post_terms($product_id, 'product_cat', array('fields'=>'ids')) : [];
					$cat_ids = explode(',', $categorias);
					if (!array_intersect($terms, $cat_ids)) continue;
				}
				$user_id = $order->get_customer_id();
				if ($user_id) {
					$user_data = get_userdata($user_id);
					if ($user_data) {
						$winners[$user_id] = $user_data->user_email;
					}
				}
			}
		}
		// Generar CSV
		$csv = "user_id,email\n";
		foreach ($winners as $uid => $email) {
			$csv .= "$uid,$email\n";
		}
		return $csv;
	}

	public static function export_users_purchases( $args = array() ) {
		// Exporta usuarios y sus compras detalladas
		$periodo_inicio = isset($args['periodo_inicio']) ? $args['periodo_inicio'] : get_option('sorteo_sco_periodo_inicio');
		$periodo_fin = isset($args['periodo_fin']) ? $args['periodo_fin'] : get_option('sorteo_sco_periodo_fin');
		$categorias = isset($args['categorias']) ? $args['categorias'] : get_option('sorteo_sco_categorias');
		$productos = isset($args['productos']) ? $args['productos'] : get_option('sorteo_sco_productos_especiales');
		
		$args_wc = [
			'limit' => -1,
			'status' => array('wc-completed','wc-processing'),
		];
		
		// Solo agregar filtro de fecha si ambas fechas están configuradas
		if ($periodo_inicio && $periodo_fin) {
			$args_wc['date_created'] = $periodo_inicio . '...' . $periodo_fin;
		}
		
		$orders = function_exists('wc_get_orders') ? wc_get_orders($args_wc) : [];
		$purchase_data = [];
		
		// Verificar que tenemos pedidos
		if (empty($orders)) {
			return "ID Usuario,Nombre Usuario,Email Usuario,ID Pedido,Fecha Compra,ID Producto,Nombre Producto,Cantidad,Total Línea,Total Pedido,Estado Pedido,Categorías\n";
		}
		
		foreach ($orders as $order) {
			// Verificar que el pedido es válido
			if (!is_object($order) || !method_exists($order, 'get_items')) {
				continue;
			}
			$user_id = $order->get_customer_id();
			$user_data = null;
			$user_name = '';
			$user_email = '';
			
			// Obtener datos del usuario si está registrado
			if ($user_id) {
				$user_data = get_userdata($user_id);
				if ($user_data) {
					$user_name = $user_data->display_name;
					$user_email = $user_data->user_email;
				}
			}
			
			// Si no hay usuario registrado, usar datos de facturación
			if (!$user_data) {
				$user_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
				$user_email = $order->get_billing_email();
				$user_id = 0; // Usuario invitado
			}
			
			// Procesar cada producto del pedido
			foreach ($order->get_items() as $item) {
				// Verificar que el item es válido
				if (!is_object($item) || !method_exists($item, 'get_product_id')) {
					continue;
				}
				$product_id = $item->get_product_id();
				if (!$product_id || $product_id <= 0) {
					continue;
				}
				$product = wc_get_product($product_id);
				if (!$product || !is_object($product) || !method_exists($product, 'get_name')) {
					continue;
				}
				$is_package = ($product->get_type() === 'sco_package');
				// Si es paquete, desglosar componentes
				if ($is_package) {
					$pkg = $item->get_meta('_sco_package', true);
					$package_name = str_replace(array(',', '"', "'"), '', $product->get_name());
					if (!empty($pkg) && !empty($pkg['components'])) {
						$is_first = true;
						foreach ($pkg['components'] as $comp) {
							$comp_product = wc_get_product($comp['product_id']);
							if (!$comp_product) continue;
							$comp_name = str_replace(array(',', '"', "'"), '', $comp_product->get_name());
							$product_terms = wp_get_post_terms($comp['product_id'], 'product_cat', array('fields' => 'names'));
							$categories_string = 'Sin categoria';
							if (!is_wp_error($product_terms) && is_array($product_terms) && !empty($product_terms)) {
								$clean_categories = array();
								foreach ($product_terms as $term) {
									$clean_term = trim(str_replace(array(',', '"', "'"), '', $term));
									if (!empty($clean_term)) {
										$clean_categories[] = $clean_term;
									}
								}
								if (!empty($clean_categories)) {
									$categories_string = implode(' ', $clean_categories);
								}
							}
							$clean_user_name = trim($user_name);
							if (empty($clean_user_name)) {
								$clean_user_name = 'Usuario sin nombre';
							}
							$clean_user_name = str_replace(array(',', '"', "'"), '', $clean_user_name);
							$clean_user_email = trim($user_email);
							if (empty($clean_user_email)) {
								$clean_user_email = 'sin-email@ejemplo.com';
							}
							// Mostrar como "Paquete: nombre paquete + nombre producto"
							$full_product_name = $package_name . ' + ' . $comp_name;
							$line_total = $is_first ? floatval($item->get_total()) : 0.0;
							$order_total = $is_first ? floatval($order->get_total()) : 0.0;
							if (($clean_user_email !== 'sin-email@ejemplo.com' || $user_id > 0) &&
								!empty($full_product_name) &&
								$order->get_id() > 0 &&
								$comp['product_id'] > 0 &&
								$item->get_quantity() > 0) {
								$purchase_data[] = [
									'user_id' => intval($user_id),
									'user_name' => $clean_user_name,
									'user_email' => $clean_user_email,
									'order_id' => intval($order->get_id()),
									'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
									'product_id' => intval($comp['product_id']),
									'product_name' => $full_product_name,
									'quantity' => intval($item->get_quantity()),
									'line_total' => $line_total,
									'order_total' => $order_total,
									'order_status' => str_replace(array(',', '"', "'"), '', trim($order->get_status())),
									'product_categories' => $categories_string
								];
							}
							$is_first = false;
						}
						continue; // Ya procesado el paquete, saltar item normal
					}
				}
				// Lógica normal para productos no paquete
				// Aplicar filtros de categorías y productos si están configurados
				$include_item = true;
				if ($productos) {
					$productos_array = explode(',', $productos);
					if (!in_array($product_id, $productos_array)) {
						$include_item = false;
					}
				}
				if ($categorias && $include_item) {
					$terms = function_exists('wp_get_post_terms') ? wp_get_post_terms($product_id, 'product_cat', array('fields'=>'ids')) : [];
					$cat_ids = explode(',', $categorias);
					if (!array_intersect($terms, $cat_ids)) {
						$include_item = false;
					}
				}
				if (!$productos && !$categorias) {
					$include_item = true;
				}
				if ($include_item) {
					$product_terms = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));
					$categories_string = 'Sin categoria';
					if (!is_wp_error($product_terms) && is_array($product_terms) && !empty($product_terms)) {
						$clean_categories = array();
						foreach ($product_terms as $term) {
							$clean_term = trim(str_replace(array(',', '"', "'"), '', $term));
							if (!empty($clean_term)) {
								$clean_categories[] = $clean_term;
							}
						}
						if (!empty($clean_categories)) {
							$categories_string = implode(' ', $clean_categories);
						}
					}
					$clean_user_name = trim($user_name);
					if (empty($clean_user_name)) {
						$clean_user_name = 'Usuario sin nombre';
					}
					$clean_user_name = str_replace(array(',', '"', "'"), '', $clean_user_name);
					$clean_user_email = trim($user_email);
					if (empty($clean_user_email)) {
						$clean_user_email = 'sin-email@ejemplo.com';
					}
					$clean_product_name = $product->get_name() ? trim($product->get_name()) : 'Producto sin nombre';
					$clean_product_name = str_replace(array(',', '"', "'"), '', $clean_product_name);
					if (($clean_user_email !== 'sin-email@ejemplo.com' || $user_id > 0) && 
						!empty($clean_product_name) && 
						$clean_product_name !== 'Producto sin nombre' &&
						$order->get_id() > 0 && 
						$product_id > 0 &&
						$item->get_quantity() > 0) {
						$purchase_data[] = [
							'user_id' => intval($user_id),
							'user_name' => $clean_user_name,
							'user_email' => $clean_user_email,
							'order_id' => intval($order->get_id()),
							'order_date' => $order->get_date_created()->format('Y-m-d H:i:s'),
							'product_id' => intval($product_id),
							'product_name' => $clean_product_name,
							'quantity' => intval($item->get_quantity()),
							'line_total' => floatval($item->get_total()),
							'order_total' => floatval($order->get_total()),
							'order_status' => str_replace(array(',', '"', "'"), '', trim($order->get_status())),
							'product_categories' => $categories_string
						];
					}
				}
			}
		}
		
		// Filtrar datos vacíos o inválidos con validación estricta
		$valid_purchase_data = array();
		
		foreach ($purchase_data as $row) {
			// Validación muy estricta para evitar filas vacías
			if (isset($row['user_email']) && !empty(trim($row['user_email'])) &&
			    isset($row['product_name']) && !empty(trim($row['product_name'])) &&
			    isset($row['order_id']) && intval($row['order_id']) > 0 &&
			    isset($row['product_id']) && intval($row['product_id']) > 0 &&
			    isset($row['user_name']) && !empty(trim($row['user_name'])) &&
			    isset($row['order_date']) && !empty(trim($row['order_date'])) &&
			    isset($row['quantity']) && intval($row['quantity']) > 0) {
			    
			    $valid_purchase_data[] = $row;
			}
		}
		
		// Si no hay datos válidos, retornar solo encabezados
		if (empty($valid_purchase_data)) {
			return "ID Usuario,Nombre Usuario,Email Usuario,ID Pedido,Fecha Compra,ID Producto,Nombre Producto,Cantidad,Total Linea,Total Pedido,Estado Pedido,Categorias\n";
		}
		
		// Generar CSV con encabezados en español (sin comillas)
		$csv_lines = array();
		$csv_lines[] = "ID Usuario,Nombre Usuario,Email Usuario,ID Pedido,Fecha Compra,ID Producto,Nombre Producto,Cantidad,Total Linea,Total Pedido,Estado Pedido,Categorias";
		
		foreach ($valid_purchase_data as $row) {
			// Validar cada campo individualmente antes de agregarlo
			$user_id = isset($row['user_id']) ? intval($row['user_id']) : 0;
			$user_name = isset($row['user_name']) ? trim(str_replace(',', ' ', $row['user_name'])) : 'Sin nombre';
			$user_email = isset($row['user_email']) ? trim($row['user_email']) : 'sin-email@ejemplo.com';
			$order_id = isset($row['order_id']) ? intval($row['order_id']) : 0;
			$order_date = isset($row['order_date']) ? trim($row['order_date']) : '';
			$product_id = isset($row['product_id']) ? intval($row['product_id']) : 0;
			$product_name = isset($row['product_name']) ? trim(str_replace(',', ' ', $row['product_name'])) : 'Sin nombre';
			$quantity = isset($row['quantity']) ? intval($row['quantity']) : 0;
			$line_total = isset($row['line_total']) ? number_format(floatval($row['line_total']), 2, '.', '') : '0.00';
			$order_total = isset($row['order_total']) ? number_format(floatval($row['order_total']), 2, '.', '') : '0.00';
			$order_status = isset($row['order_status']) ? trim($row['order_status']) : 'unknown';
			$categories = isset($row['product_categories']) ? trim(str_replace(',', ' ', $row['product_categories'])) : 'Sin categoria';
			
			// Verificar que no tenemos campos críticos vacíos
			if (!empty($user_email) && $user_email !== 'sin-email@ejemplo.com' && 
			    !empty($product_name) && $product_name !== 'Sin nombre' && 
			    $order_id > 0 && $product_id > 0 && $quantity > 0) {
			    
				$clean_data = array(
					$user_id,
					$user_name,
					$user_email,
					$order_id,
					$order_date,
					$product_id,
					$product_name,
					$quantity,
					$line_total,
					$order_total,
					$order_status,
					$categories
				);
				
				// Solo agregar líneas que no estén vacías
				$csv_line = implode(',', $clean_data);
				if (!empty(trim($csv_line)) && $csv_line !== ',,,,,,,,,,,' && strpos($csv_line, ',,,') === false) {
					$csv_lines[] = $csv_line;
				}
			}
		}
		
		// Unir todas las líneas válidas
		$csv = implode("\n", $csv_lines) . "\n";
		
		return $csv;
	}

	public static function download_winners_csv( $args = array() ) {
		// Genera y descarga CSV de ganadores
		$csv = self::export_winners( $args );
		$filename = 'sorteo-ganadores-' . date('Y-m-d-H-i-s') . '.csv';
		
		self::send_csv_download( $csv, $filename );
	}

	public static function download_users_purchases_csv( $args = array() ) {
		// Genera y descarga CSV de usuarios+compras
		$csv = self::export_users_purchases( $args );
		$filename = 'sorteo-usuarios-compras-' . date('Y-m-d-H-i-s') . '.csv';
		
		self::send_csv_download( $csv, $filename );
	}

	private static function send_csv_download( $csv_content, $filename ) {
		// Limpiar completamente cualquier salida previa y buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		
		// Limpiar el contenido CSV de espacios y líneas vacías
		$csv_content = trim($csv_content);
		
		// Eliminar líneas completamente vacías del CSV
		$lines = explode("\n", $csv_content);
		$clean_lines = array();
		
		foreach ($lines as $line) {
			$trimmed_line = trim($line);
			// Solo agregar líneas que no estén vacías y no sean solo comas
			if (!empty($trimmed_line) && $trimmed_line !== ',,,,,,,,,,,') {
				$clean_lines[] = $trimmed_line;
			}
		}
		
		$csv_content = implode("\n", $clean_lines);
		
		// Configurar headers para descarga
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Length: ' . strlen($csv_content));
		header('Pragma: no-cache');
		header('Expires: 0');
		
		// Enviar BOM para UTF-8 (para compatibilidad con Excel)
		echo "\xEF\xBB\xBF";
		
		// Enviar contenido CSV limpio
		echo $csv_content;
		
		// Terminar ejecución para evitar contenido adicional
		exit;
	}
}
