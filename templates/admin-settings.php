<?php
// Manejar borrado de historial completo
if (isset($_POST['sorteo_clear_history_action']) && $_POST['sorteo_clear_history_action'] == '1') {
	// Verificar nonce
	if (wp_verify_nonce($_POST['sorteo_clear_nonce_direct'], 'sorteo_clear_history_direct')) {
		// Verificar permisos
		if (current_user_can('manage_options')) {
			// Obtener historial antes de borrarlo
			$draws_history = get_option('sorteo_sco_draws_history', array());
			$total_draws = count($draws_history);

			// Borrar historial
			delete_option('sorteo_sco_draws_history');
			update_option('sorteo_sco_total_draws', 0);

			// Enviar email a administradores
			$admins = get_users(array('role' => 'administrator'));
			if (!empty($admins)) {
				$current_user = wp_get_current_user();
				$site_name = get_bloginfo('name');
				$date_time = current_time('d/m/Y H:i:s');

				$subject = sprintf('[%s] Historial de Sorteos Eliminado', $site_name);
				$message = sprintf(
					'El historial completo de sorteos ha sido eliminado.\n\nDetalles:\n- Usuario: %s (%s)\n- Fecha: %s\n- Registros eliminados: %d',
					$current_user->display_name,
					$current_user->user_email,
					$date_time,
					$total_draws
				);

				foreach ($admins as $admin) {
					wp_mail($admin->user_email, $subject, $message);
				}
			}

			// Mostrar mensaje de éxito
			echo '<div class="notice notice-success is-dismissible"><p><strong>Historial eliminado correctamente.</strong> Se ha enviado una notificación por email a los administradores.</p></div>';
		} else {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No tienes permisos para realizar esta acción.</p></div>';
		}
	} else {
		echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Token de seguridad inválido.</p></div>';
	}
}

// Manejar borrado de registro individual
if (isset($_POST['sorteo_delete_single_draw'])) {
	// Verificar nonce
	if (wp_verify_nonce($_POST['sorteo_delete_nonce'], 'sorteo_delete_single')) {
		// Verificar permisos
		if (current_user_can('manage_options')) {
			$draw_id_to_delete = sanitize_text_field($_POST['sorteo_delete_single_draw']);
			$draws_history = get_option('sorteo_sco_draws_history', array());

			// Buscar y eliminar el registro por índice del array
			$found = false;
			$draw_index = intval($draw_id_to_delete);

			// Verificar que el índice sea válido
			if (isset($draws_history[$draw_index])) {
				$deleted_draw = $draws_history[$draw_index];
				unset($draws_history[$draw_index]);
				$draws_history = array_values($draws_history); // Reindexar array
				$found = true;
			}

			if ($found) {
				// Actualizar opciones
				update_option('sorteo_sco_draws_history', $draws_history);
				update_option('sorteo_sco_total_draws', count($draws_history));

				// Enviar email a administradores
				$admins = get_users(array('role' => 'administrator'));
				if (!empty($admins)) {
					$current_user = wp_get_current_user();
					$site_name = get_bloginfo('name');
					$date_time = current_time('d/m/Y H:i:s');

					$subject = sprintf('[%s] Registro de Sorteo Eliminado', $site_name);
					$message = sprintf(
						'Un registro individual de sorteo ha sido eliminado.\n\nDetalles:\n- Usuario: %s (%s)\n- Fecha: %s\n- Ganador eliminado: %s (%s)\n- Premio: %s\n- Valor: %s',
						$current_user->display_name,
						$current_user->user_email,
						$date_time,
						$deleted_draw['winner_name'],
						$deleted_draw['winner_email'],
						isset($deleted_draw['prize_name']) ? $deleted_draw['prize_name'] : 'Sin nombre',
						sorteo_sco_format_price(isset($deleted_draw['prize_price']) ? $deleted_draw['prize_price'] : 0)
					);

					foreach ($admins as $admin) {
						wp_mail($admin->user_email, $subject, $message);
					}
				}

				echo '<div class="notice notice-success is-dismissible"><p><strong>Registro eliminado correctamente.</strong> Se ha enviado una notificación por email a los administradores.</p></div>';
			} else {
				echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No se pudo encontrar el registro a eliminar.</p></div>';
			}
		} else {
			echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> No tienes permisos para realizar esta acción.</p></div>';
		}
	} else {
		echo '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> Token de seguridad inválido.</p></div>';
	}
}

// Mostrar mensaje de éxito si se otorgó premio manualmente
if (isset($_GET['manual_prize']) && $_GET['manual_prize'] === 'success' && isset($_GET['order_id'])) {
	$order_id = intval($_GET['order_id']);
	$order = wc_get_order($order_id);
	if ($order) {
		$winner_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		echo '<div class="notice notice-success is-dismissible"><p><strong>✅ ' . __('Premio otorgado correctamente', 'sorteo-sco') . '</strong><br>';
		echo sprintf(__('Se ha otorgado el premio a <strong>%s</strong> (Pedido #%d). Se ha enviado un email de notificación al ganador.', 'sorteo-sco'), esc_html($winner_name), $order_id);
		echo '</p></div>';
	}
}
?>

<div class="wrap">
	<h1>Sorteo - Configuración</h1>

	<!-- Tabs Navigation -->
	<h2 class="nav-tab-wrapper">
		<a href="#configuracion" class="nav-tab nav-tab-active" id="tab-configuracion"><?php _e('Configuración', 'sorteo-sco'); ?></a>
		<a href="#mensaje" class="nav-tab" id="tab-mensaje"><?php _e('Mensaje', 'sorteo-sco'); ?></a>
		<a href="#metricas" class="nav-tab" id="tab-metricas"><?php _e('Métricas', 'sorteo-sco'); ?></a>
		<a href="#premios" class="nav-tab" id="tab-premios"><?php _e('Premios', 'sorteo-sco'); ?></a>
		<a href="#logs" class="nav-tab" id="tab-logs"><?php _e('Logs', 'sorteo-sco'); ?></a>
		<a href="#exportar" class="nav-tab" id="tab-exportar"><?php _e('Exportar', 'sorteo-sco'); ?></a>
	</h2>

	<!-- Formulario único para todos los tabs -->
	<form method="post" action="options.php" id="sorteo-settings-form">
		<?php settings_fields('sorteo_sco_settings_group'); ?>

		<!-- Tab Content: Configuración -->
		<div id="content-configuracion" class="tab-content active">
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e('Periodo de sorteo', 'sorteo-sco'); ?></th>
					<td>
						<input type="date" name="sorteo_sco_periodo_inicio" value="<?php echo esc_attr(get_option('sorteo_sco_periodo_inicio')); ?>" />
						<span><?php _e('a', 'sorteo-sco'); ?></span>
						<input type="date" name="sorteo_sco_periodo_fin" value="<?php echo esc_attr(get_option('sorteo_sco_periodo_fin')); ?>" />
						<p class="description"><?php _e('Selecciona el rango de fechas para el sorteo', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Categorías de productos', 'sorteo-sco'); ?></th>
					<td>
						<?php
						$selected_cats = get_option('sorteo_sco_categorias', '');
						$selected_cats_array = $selected_cats ? explode(',', $selected_cats) : [];
						$categories = get_terms('product_cat', array('hide_empty' => false));
						?>
						<select name="sorteo_sco_categorias[]" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php _e('Buscar categorías...', 'sorteo-sco'); ?>" style="width: 300px; height: 150px;">
							<?php foreach ($categories as $category): ?>
								<option value="<?php echo $category->term_id; ?>" <?php echo in_array($category->term_id, $selected_cats_array) ? 'selected' : ''; ?>>
									<?php echo $category->name; ?> (<?php echo $category->count; ?>)
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e('Categorías disponibles para sorteo ganador, cualquier producto en esta categoria sera ganadora', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Productos especiales', 'sorteo-sco'); ?></th>
					<td>
						<?php
						$selected_products = get_option('sorteo_sco_productos_especiales', '');
						$selected_products_array = $selected_products ? explode(',', $selected_products) : [];
						$products = wc_get_products(array(
							'limit' => -1,
							'status' => 'publish',
							'stock_status' => 'instock'
						));
						?>
						<select name="sorteo_sco_productos_especiales[]" id="sorteo_products_select" class="wc-enhanced-select" multiple="multiple" style="width: 380px; height: 150px;" data-placeholder="<?php _e('Buscar productos...', 'sorteo-sco'); ?>">
							<?php foreach ($products as $product): ?>
								<option value="<?php echo $product->get_id(); ?>" <?php echo in_array($product->get_id(), $selected_products_array) ? 'selected' : ''; ?>>
									<?php echo $product->get_name(); ?> - <?php echo wc_price($product->get_price()); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e('Productos ganadores, si se compra estos productos el usuario ganara automaticamente', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Mínimo de ganancia para sorteo automático', 'sorteo-sco'); ?></th>
					<td>
						<input type="number" name="sorteo_sco_min_ganancia" value="<?php echo esc_attr(get_option('sorteo_sco_min_ganancia')); ?>" min="0" step="0.01" />
						<p class="description"><?php _e('Cuando se alcance esta ganancia, se ejecutará automáticamente un sorteo', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Premio actual', 'sorteo-sco'); ?></th>
					<td>
						<div style="display: flex; align-items: center; gap: 5px; margin-bottom: 10px;">
							<span style="color: #666; font-weight: bold;"><?php echo function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€'; ?></span>
							<input type="number" name="sorteo_sco_prize_price" value="<?php echo esc_attr(get_option('sorteo_sco_prize_price', '0')); ?>" min="0" step="0.01" placeholder="0.00" style="width: 120px;" />
							<span style="color: #666; font-weight: bold;"><?php echo function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR'; ?></span>
						</div>
						<input type="text" name="sorteo_sco_prize_name" value="<?php echo esc_attr(get_option('sorteo_sco_prize_name', '')); ?>" placeholder="Nombre del premio (ej: iPhone 15, Tarjeta Regalo, etc.)" style="width: 400px;" />
						<p class="description"><?php _e('Valor y nombre del premio que se entrega en cada sorteo. Se usará para calcular ganancias netas y llevar mejor registro.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Email de envío', 'sorteo-sco'); ?></th>
					<td>
						<input type="email" name="sorteo_sco_from_email" value="<?php echo esc_attr(get_option('sorteo_sco_from_email', get_option('admin_email'))); ?>" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>" style="width: 400px;" />
						<p class="description"><?php _e('Email que aparecerá como remitente en los emails de sorteo. Si está vacío, se usará el email de administrador de WordPress.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Email extra: todas las descargas', 'sorteo-sco'); ?></th>
					<td>
						<?php $email_dl_enabled = get_option('sorteo_sco_email_downloads_enabled', 'yes'); ?>
						<input type="hidden" name="sorteo_sco_email_downloads_enabled" value="no" />
						<label>
							<input type="checkbox" name="sorteo_sco_email_downloads_enabled" value="yes" <?php checked($email_dl_enabled, 'yes'); ?> />
							<?php _e('Enviar un email adicional al marcar el pedido como Completado con TODOS los enlaces de descarga (incluye componentes de Paquetes).', 'sorteo-sco'); ?>
						</label>
						<p class="description"><?php _e('Si se desactiva, no se enviará el email adicional y el cliente solo recibirá el correo estándar de WooCommerce.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Nombre del remitente', 'sorteo-sco'); ?></th>
					<td>
						<input type="text" name="sorteo_sco_from_name" value="<?php echo esc_attr(get_option('sorteo_sco_from_name', get_bloginfo('name'))); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" style="width: 400px;" />
						<p class="description"><?php _e('Nombre que aparecerá como remitente en los emails de sorteo. Si está vacío, se usará el nombre del sitio.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Estados de pedido para mostrar mensaje/email', 'sorteo-sco'); ?></th>
					<td>
						<?php
						$selected_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));
						$selected_statuses = is_array($selected_statuses) ? $selected_statuses : explode(',', $selected_statuses);

						// Estados de WooCommerce disponibles
						$wc_statuses = array(
							'wc-pending' => __('Pendiente de pago', 'sorteo-sco'),
							'wc-processing' => __('Procesando', 'sorteo-sco'),
							'wc-on-hold' => __('En espera', 'sorteo-sco'),
							'wc-completed' => __('Completado', 'sorteo-sco'),
							'wc-cancelled' => __('Cancelado', 'sorteo-sco'),
							'wc-refunded' => __('Reembolsado', 'sorteo-sco'),
							'wc-failed' => __('Fallido', 'sorteo-sco')
						);
						?>
						<select name="sorteo_sco_order_statuses[]" multiple class="wc-enhanced-select" data-placeholder="<?php _e('Buscar estados...', 'sorteo-sco'); ?>" style="width: 100%; height: 120px;">
							<?php foreach ($wc_statuses as $status => $label): ?>
								<option value="<?php echo esc_attr($status); ?>" <?php echo in_array($status, $selected_statuses) ? 'selected' : ''; ?>>
									<?php echo esc_html($label); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php _e('Selector mejorado: buscar dentro del campo y quitar con “x”.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Marco visual', 'sorteo-sco'); ?></th>
					<td>
						<div class="marco-visual-selector">
							<input type="url" name="sorteo_sco_marco_visual" id="sorteo_sco_marco_visual" value="<?php echo esc_attr(get_option('sorteo_sco_marco_visual')); ?>" class="regular-text" />
							<button type="button" class="button" id="seleccionar_marco_visual"><?php _e('Seleccionar imagen', 'sorteo-sco'); ?></button>
							<button type="button" class="button" id="quitar_marco_visual"><?php _e('Quitar', 'sorteo-sco'); ?></button>
						</div>
						<?php if (get_option('sorteo_sco_marco_visual')): ?>
							<img src="<?php echo esc_url(get_option('sorteo_sco_marco_visual')); ?>" class="marco-visual-preview" />
						<?php endif; ?>
						<p class="description"><?php _e('Marco visual que aparecerá en productos especiales (SVG, PNG, WEBP)', 'sorteo-sco'); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Tab Content: Mensaje -->
		<div id="content-mensaje" class="tab-content">
			<h3><?php _e('Configuración del Mensaje', 'sorteo-sco'); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e('Mensaje producto reservado', 'sorteo-sco'); ?></th>
					<td>
						<textarea name="sorteo_sco_mensaje_producto_reservado" rows="3" style="width: 100%; max-width: 700px;"><?php echo esc_textarea(get_option('sorteo_sco_mensaje_producto_reservado', __('No hay suficientes productos disponibles en este momento (algunos pueden estar reservados por otros usuarios). Vuelve a intentar para generar una nueva combinación al azar.', 'sorteo-sco'))); ?></textarea>
						<p class="description"><?php _e('Texto que se mostrará cuando un paquete no pueda armarse o deba reintentar por productos reservados. Solo aplica a paquetes.', 'sorteo-sco'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php _e('Mostrar mensaje de reemplazos', 'sorteo-sco'); ?></th>
					<td>
						<?php $show_replacements = get_option('sorteo_sco_mostrar_mensaje_reemplazos', 'yes'); ?>
						<label>
							<input type="checkbox" name="sorteo_sco_mostrar_mensaje_reemplazos" value="yes" <?php checked($show_replacements, 'yes'); ?> />
							<?php _e('Mostrar notificación cuando se reemplazan productos por reservas', 'sorteo-sco'); ?>
						</label>
						<p class="description"><?php _e('Si está desmarcado, no se mostrará el mensaje cuando algunos productos fueron reemplazados en el paquete.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Mensaje reemplazos por reservas', 'sorteo-sco'); ?></th>
					<td>
						<textarea name="sorteo_sco_mensaje_reemplazos" rows="3" style="width: 100%; max-width: 700px;"><?php echo esc_textarea(get_option('sorteo_sco_mensaje_reemplazos', __('Nota: %d producto(s) estaban reservados por otros usuarios y se eligieron alternativas para completar tu paquete. Si deseas una nueva combinación al azar, elimina este paquete del carrito y vuelve a agregarlo.', 'sorteo-sco'))); ?></textarea>
						<p class="description"><?php _e('Texto informativo cuando algunos productos fueron reemplazados por reservas pero el paquete se completó. Usa %d para mostrar la cantidad. Solo aplica a paquetes en modo sorpresa.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Título del mensaje', 'sorteo-sco'); ?></th>
					<td>
						<input type="text" name="sorteo_sco_mensaje_titulo" value="<?php echo esc_attr(get_option('sorteo_sco_mensaje_titulo', '¡Felicidades!')); ?>" style="width: 100%; max-width: 500px;" />
						<p class="description"><?php _e('Título que aparecerá en el mensaje de ganador (ej: "¡Felicidades!", "¡Eres un ganador!", etc.)', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Mensaje personalizado', 'sorteo-sco'); ?></th>
					<td>
						<?php
						$content = get_option('sorteo_sco_aviso_personalizado', '');
						wp_editor($content, 'sorteo_sco_aviso_personalizado', array(
							'textarea_name' => 'sorteo_sco_aviso_personalizado',
							'media_buttons' => true,
							'textarea_rows' => 8,
							'teeny' => false
						));
						?>
						<p class="description">
							<?php _e('Mensaje que verán los ganadores. Puedes usar estos campos personalizados:', 'sorteo-sco'); ?><br>
							<strong>{nombre}</strong> - <?php _e('Nombre del ganador', 'sorteo-sco'); ?><br>
							<strong>{premio}</strong> - <?php _e('Nombre del premio', 'sorteo-sco'); ?><br>
							<strong>{valor}</strong> - <?php _e('Valor del premio formateado', 'sorteo-sco'); ?><br>
							<strong>{fecha}</strong> - <?php _e('Fecha del sorteo', 'sorteo-sco'); ?><br>
							<strong>{sitio}</strong> - <?php _e('Nombre del sitio web', 'sorteo-sco'); ?><br><br>
							<em><?php _e('Ejemplo: "¡Felicidades {nombre}! Has ganado {premio} valorado en {valor}. ¡Gracias por tu compra en {sitio}!"', 'sorteo-sco'); ?></em>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Asunto del email', 'sorteo-sco'); ?></th>
					<td>
						<input type="text" name="sorteo_sco_email_subject" value="<?php echo esc_attr(get_option('sorteo_sco_email_subject', '[{sitio}] ¡Felicidades, eres ganador!')); ?>" style="width: 100%; max-width: 700px;" />
						<p class="description">
							<?php _e('Asunto del email que recibirán los ganadores. Puedes usar:', 'sorteo-sco'); ?><br>
							<strong>{nombre}</strong>, <strong>{premio}</strong>, <strong>{valor}</strong>, <strong>{fecha}</strong>, <strong>{sitio}</strong>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Contenido del email', 'sorteo-sco'); ?></th>
					<td>
						<?php
						$email_content = get_option('sorteo_sco_email_content', '');
						if (empty($email_content)) {
							// Valor por defecto
							$email_content = "¡Hola {nombre}!\n\n¡Felicidades! Has ganado el premio: {premio} valorado en {valor}.\n\nFecha del sorteo: {fecha}\n\nGracias por tu compra en {sitio}.";
						}
						wp_editor($email_content, 'sorteo_sco_email_content', array(
							'textarea_name' => 'sorteo_sco_email_content',
							'media_buttons' => true,
							'textarea_rows' => 8,
							'teeny' => false
						));
						?>
						<p class="description">
							<?php _e('Contenido del email. Puedes usar estos campos personalizados:', 'sorteo-sco'); ?><br>
							<strong>{nombre}</strong> - <?php _e('Nombre del ganador', 'sorteo-sco'); ?><br>
							<strong>{premio}</strong> - <?php _e('Nombre del premio', 'sorteo-sco'); ?><br>
							<strong>{valor}</strong> - <?php _e('Valor del premio formateado', 'sorteo-sco'); ?><br>
							<strong>{fecha}</strong> - <?php _e('Fecha del sorteo', 'sorteo-sco'); ?><br>
							<strong>{sitio}</strong> - <?php _e('Nombre del sitio web', 'sorteo-sco'); ?><br>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Color de fondo', 'sorteo-sco'); ?></th>
					<td>
						<input type="color" name="sorteo_sco_mensaje_bg_color" value="<?php echo esc_attr(get_option('sorteo_sco_mensaje_bg_color', '#4caf50')); ?>" />
						<p class="description"><?php _e('Color de fondo del mensaje de notificación', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Color del texto', 'sorteo-sco'); ?></th>
					<td>
						<input type="color" name="sorteo_sco_mensaje_text_color" value="<?php echo esc_attr(get_option('sorteo_sco_mensaje_text_color', '#ffffff')); ?>" />
						<p class="description"><?php _e('Color del texto del mensaje', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Reponer stock en Cancelado/Reembolsado', 'sorteo-sco'); ?></th>
					<td>
						<?php $restock_on_cancel = get_option('sorteo_sco_restock_on_cancel', 'yes'); ?>
						<label>
							<input type="checkbox" name="sorteo_sco_restock_on_cancel" value="yes" <?php checked($restock_on_cancel, 'yes'); ?> />
							<?php _e('Al cancelar o reembolsar un pedido con Paquetes, devolver al stock los productos componentes.', 'sorteo-sco'); ?>
						</label>
						<p class="description"><?php _e('Si está marcado, cuando un pedido se marque como Cancelado o Reembolsado, se incrementará el stock de los productos componentes del paquete una sola vez.', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Posición del mensaje', 'sorteo-sco'); ?></th>
					<td>
						<select name="sorteo_sco_mensaje_position">
							<?php
							$current_position = get_option('sorteo_sco_mensaje_position', 'top');
							$positions = array(
								'top' => 'Arriba (Top)',
								'center' => 'Centro (Center)',
								'bottom' => 'Abajo (Bottom)'
							);
							foreach ($positions as $position => $label) {
								echo '<option value="' . esc_attr($position) . '" ' . selected($current_position, $position, false) . '>' . esc_html($label) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php _e('Posición vertical donde aparecerá el mensaje en pantalla', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Familia de fuente', 'sorteo-sco'); ?></th>
					<td>
						<select name="sorteo_sco_mensaje_font_family">
							<?php
							$current_family = get_option('sorteo_sco_mensaje_font_family', 'inherit');
							$font_families = array(
								'inherit' => 'Fuente del tema',
								'Arial, sans-serif' => 'Arial',
								'Helvetica, sans-serif' => 'Helvetica',
								'Georgia, serif' => 'Georgia',
								'Times New Roman, serif' => 'Times New Roman',
								'Courier New, monospace' => 'Courier New',
								'Verdana, sans-serif' => 'Verdana'
							);
							foreach ($font_families as $family => $label) {
								echo '<option value="' . esc_attr($family) . '" ' . selected($current_family, $family, false) . '>' . esc_html($label) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php _e('Familia de fuentes para el mensaje', 'sorteo-sco'); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php _e('Efecto CSS', 'sorteo-sco'); ?></th>
					<td>
						<select name="sorteo_sco_mensaje_effect">
							<?php
							$current_effect = get_option('sorteo_sco_mensaje_effect', 'none');
							$effects = array(
								'none' => 'Sin efecto',
								'fade' => 'Aparecer gradualmente (Fade)',
								'slide' => 'Deslizar desde arriba (Slide)',
								'bounce' => 'Rebotar (Bounce)',
								'pulse' => 'Pulsar (Pulse)',
								'shake' => 'Vibrar (Shake)'
							);
							foreach ($effects as $effect => $label) {
								echo '<option value="' . esc_attr($effect) . '" ' . selected($current_effect, $effect, false) . '>' . esc_html($label) . '</option>';
							}
							?>
						</select>
						<p class="description"><?php _e('Efecto de animación CSS cuando aparece el mensaje', 'sorteo-sco'); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Botón de guardar para todos los tabs configurables -->
		<div class="sorteo-save-section" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin: 20px 0;">
			<?php submit_button(__('Guardar Configuración', 'sorteo-sco'), 'primary', 'submit', true, array('style' => 'font-size: 16px; padding: 12px 24px;')); ?>
			<p class="description"><?php _e('Guarda la configuración de todos los tabs (Configuración y Mensaje)', 'sorteo-sco'); ?></p>
		</div>
	</form>

	<!-- Tab Content: Métricas -->
	<div id="content-metricas" class="tab-content">
		<h3><?php _e('Métricas de Sorteo', 'sorteo-sco'); ?></h3>

		<?php
		// Las métricas se actualizan automáticamente, pero forzamos actualización al entrar a la pestaña
		$core = Sorteo_SCO_Core::get_instance();
		$core->update_metrics(); // Siempre actualizar al cargar la pestaña de métricas

		// Obtener métricas directamente desde las opciones
		$ganancia_total = floatval(get_option('sorteo_sco_total_earnings', 0));
		$sorteos_realizados = intval(get_option('sorteo_sco_total_draws', 0));
		$draws_history = get_option('sorteo_sco_draws_history', array());
		// Calcular ganancias netas
		$core = Sorteo_SCO_Core::get_instance();
		$earnings_data = $core->calculate_net_earnings();

		// Obtener el precio del premio actual configurado
		$current_prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
		?>

		<div class="metrics-container" style="display: flex; gap: 20px; margin-bottom: 30px;">
			<div class="metric-card" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; flex: 1;">
				<h4><?php _e('Ganancia Bruta', 'sorteo-sco'); ?></h4>
				<p style="font-size: 24px; font-weight: bold; color: #2271b1;"><?php echo sorteo_sco_format_price($earnings_data['gross_earnings']); ?></p>
			</div>
			<div class="metric-card" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; flex: 1;">
				<h4><?php _e('Costo de Premios Entregados', 'sorteo-sco'); ?></h4>
				<p style="font-size: 24px; font-weight: bold; color: #dc3545;">-<?php echo sorteo_sco_format_price($earnings_data['total_prizes_cost']); ?></p>
				<small style="color: #666;">Premios ya otorgados</small>
			</div>
			<div class="metric-card" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; flex: 1;">
				<h4><?php _e('Valor Premio Actual', 'sorteo-sco'); ?></h4>
				<p style="font-size: 24px; font-weight: bold; color: #ff9800;"><?php echo sorteo_sco_format_price($current_prize_price); ?></p>
				<small style="color: #666;">Próximo sorteo</small>
			</div>
			<div class="metric-card" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; flex: 1;">
				<h4><?php _e('Ganancia Neta', 'sorteo-sco'); ?></h4>
				<p style="font-size: 24px; font-weight: bold; color: <?php echo $earnings_data['net_earnings'] >= 0 ? '#00a32a' : '#dc3545'; ?>;"><?php echo sorteo_sco_format_price($earnings_data['net_earnings']); ?></p>
				<small style="color: #666;">Bruta - Premios entregados</small>
			</div>
			<div class="metric-card" style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; flex: 1;">
				<h4><?php _e('Sorteos Realizados', 'sorteo-sco'); ?></h4>
				<p style="font-size: 24px; font-weight: bold; color: #7c3aed;"><?php echo $sorteos_realizados; ?></p>
				<small style="color: #666;">Total de sorteos</small>
			</div>
		</div>

		<!-- Progreso hacia próximo sorteo automático -->
		<?php
		$min_ganancia = floatval(get_option('sorteo_sco_min_ganancia', 0));
		if ($min_ganancia > 0) {
			$progreso_porcentaje = min(100, ($ganancia_total / $min_ganancia) * 100);
		?>
			<div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
				<h4><?php _e('Progreso hacia próximo sorteo automático', 'sorteo-sco'); ?></h4>
				<div style="background: #f0f0f1; border-radius: 10px; height: 20px; margin: 10px 0;">
					<div style="background: #2271b1; height: 100%; border-radius: 10px; width: <?php echo $progreso_porcentaje; ?>%;"></div>
				</div>
				<p><?php echo round($progreso_porcentaje, 1); ?>% (<?php echo sorteo_sco_format_price($ganancia_total); ?> / <?php echo sorteo_sco_format_price($min_ganancia); ?>)</p>
			</div>
		<?php } ?>

		<!-- Gráficos de Métricas -->
		<div class="row mb-4" style="display: flex; gap: 20px; margin-bottom: 20px;">
			<div class="col-md-8 mb-3" style="flex: 2;">
				<div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
						<h4 style="margin: 0;"><?php _e('Ganancias por día', 'sorteo-sco'); ?></h4>
						<div style="display: flex; gap: 10px;">
							<button type="button" class="button sorteo-chart-range-btn" data-days="7">7d</button>
							<button type="button" class="button sorteo-chart-range-btn active" data-days="30">30d</button>
							<button type="button" class="button sorteo-chart-range-btn" data-days="90">90d</button>
						</div>
					</div>
					<div style="height: 300px;">
						<canvas id="sorteo-earnings-chart"></canvas>
					</div>
					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
						<label style="font-weight: bold; margin-right: 10px;"><?php _e('Rango personalizado:', 'sorteo-sco'); ?></label>
						<input type="date" id="sorteo-range-from" style="margin-right: 5px;" />
						<span style="margin: 0 5px;"><?php _e('a', 'sorteo-sco'); ?></span>
						<input type="date" id="sorteo-range-to" style="margin-right: 10px;" />
						<button type="button" id="sorteo-apply-range" class="button button-primary"><?php _e('Aplicar', 'sorteo-sco'); ?></button>
					</div>
				</div>
			</div>
			<div class="col-md-4 mb-3" style="flex: 1;">
				<div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
					<h4 style="margin-top: 0;"><?php _e('Sorteos por tipo', 'sorteo-sco'); ?></h4>
					<div style="height: 300px;">
						<canvas id="sorteo-prizes-chart"></canvas>
					</div>
				</div>
			</div>
		</div>

		<!-- Historial de Sorteos -->
		<div style="background: #fff; border: 1px solid #ddd; border-radius: 5px; padding: 20px;">
			<h4><?php _e('Historial de Sorteos', 'sorteo-sco'); ?></h4>
			<?php if (!empty($draws_history)) { ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e('Fecha', 'sorteo-sco'); ?></th>
							<th><?php _e('Ganador', 'sorteo-sco'); ?></th>
							<th><?php _e('Email', 'sorteo-sco'); ?></th>
							<th><?php _e('Tipo', 'sorteo-sco'); ?></th>
							<th><?php _e('Premio', 'sorteo-sco'); ?></th>
							<th><?php _e('Valor', 'sorteo-sco'); ?></th>
							<th><?php _e('Pedido', 'sorteo-sco'); ?></th>
							<th><?php _e('Período', 'sorteo-sco'); ?></th>
							<th><?php _e('Acciones', 'sorteo-sco'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						// Mostrar los últimos 10 sorteos en orden descendente
						$recent_draws = array_slice(array_reverse($draws_history), 0, 10);
						foreach ($recent_draws as $index => $draw) {
							// Usar índice directo del array original como ID
							// Necesitamos calcular el índice real en el array original
							$total_draws = count($draws_history);
							$real_index = $total_draws - 1 - $index; // Índice real en el array original
							$draw_id = $real_index;
						?>
							<tr>
								<td><?php echo esc_html(date('d/m/Y H:i', strtotime($draw['date']))); ?></td>
								<td><?php echo esc_html($draw['winner_name']); ?></td>
								<td><?php echo esc_html($draw['winner_email']); ?></td>
								<td>
									<span class="sorteo-type-<?php echo esc_attr($draw['type']); ?>">
										<?php echo $draw['type'] == 'manual' ? __('Manual', 'sorteo-sco') : __('Automático', 'sorteo-sco'); ?>
									</span>
								</td>
								<td style="font-weight: bold; color: #2271b1;">
									<?php echo esc_html(isset($draw['prize_name']) ? $draw['prize_name'] : 'Sin nombre'); ?>
								</td>
								<td style="font-weight: bold; color: #dc3545;">
									<?php echo sorteo_sco_format_price(isset($draw['prize_price']) ? floatval($draw['prize_price']) : 0); ?>
								</td>
								<td>
									<?php if (isset($draw['order_id']) && $draw['order_id']) { ?>
										<a href="<?php echo admin_url('post.php?post=' . $draw['order_id'] . '&action=edit'); ?>"
											target="_blank"
											class="button button-small"
											style="font-size: 11px; padding: 2px 8px;">
											🛍️ Ver Pedido #<?php echo $draw['order_id']; ?>
										</a>
									<?php } else { ?>
										<span style="color: #666; font-style: italic;">Sin pedido</span>
									<?php } ?>
								</td>
								<td>
									<?php
									// Mostrar período del sorteo si existe, sino mostrar la fecha del sorteo
									if (isset($draw['periodo_inicio']) && isset($draw['periodo_fin'])) {
										echo esc_html($draw['periodo_inicio'] . ' - ' . $draw['periodo_fin']);
									} else {
										// Mostrar solo la fecha del sorteo
										echo esc_html(date('d/m/Y', strtotime($draw['date'])));
									}
									?>
								</td>
								<td>
									<form method="post" style="display: inline;" onsubmit="return confirm('¿Estás seguro de que quieres eliminar este registro?');">
										<input type="hidden" name="sorteo_delete_single_draw" value="<?php echo esc_attr($draw_id); ?>" />
										<?php wp_nonce_field('sorteo_delete_single', 'sorteo_delete_nonce'); ?>
										<button type="submit" class="button button-small" style="background: #dc3545; border-color: #dc3545; color: white; font-size: 11px; padding: 2px 8px;">
											🗑️ Eliminar
										</button>
									</form>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>

				<?php if (count($draws_history) > 10) { ?>
					<p><em><?php printf(__('Mostrando los últimos 10 de %d sorteos realizados.', 'sorteo-sco'), count($draws_history)); ?></em></p>
				<?php } ?>

			<?php } else { ?>
				<p><?php _e('No se han realizado sorteos aún.', 'sorteo-sco'); ?></p>
			<?php } ?>
		</div>
	</div>

	<!-- Tab Content: Premios -->
	<div id="content-premios" class="tab-content">
		<h3><?php _e('Historial de Premios Entregados', 'sorteo-sco'); ?></h3>
		<?php
		$draws_history = get_option('sorteo_sco_draws_history', array());
		if (!empty($draws_history)) {
			// Mostrar solo sorteos con premios entregados (reversed para más recientes primero)
			$draws_history = array_reverse($draws_history);
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . __('Fecha', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Ganador', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Email', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Premio', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Valor', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Tipo', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Pedido', 'sorteo-sco') . '</th>';
			echo '</tr></thead><tbody>';

			foreach ($draws_history as $draw) {
				$date = isset($draw['date']) ? $draw['date'] : (isset($draw['timestamp']) ? date('Y-m-d H:i:s', $draw['timestamp']) : 'N/A');
				$winner_name = isset($draw['winner_name']) ? $draw['winner_name'] : 'N/A';
				$winner_email = isset($draw['winner_email']) ? $draw['winner_email'] : 'N/A';
				$prize_name = isset($draw['prize_name']) ? $draw['prize_name'] : 'Premio sin nombre';
				$prize_price = isset($draw['prize_price']) ? floatval($draw['prize_price']) : 0;
				$type = isset($draw['type']) ? $draw['type'] : 'manual';
				$order_id = isset($draw['order_id']) ? $draw['order_id'] : 'N/A';

				// Traducir tipo
				$type_label = $type === 'automatic_immediate' ? __('Automático Inmediato', 'sorteo-sco') : ($type === 'automatic_threshold' ? __('Automático por Umbral', 'sorteo-sco') :
						__('Manual', 'sorteo-sco'));

				echo '<tr>';
				echo '<td>' . esc_html($date) . '</td>';
				echo '<td>' . esc_html($winner_name) . '</td>';
				echo '<td>' . esc_html($winner_email) . '</td>';
				echo '<td>' . esc_html($prize_name) . '</td>';
				echo '<td>' . wc_price($prize_price) . '</td>';
				echo '<td>' . esc_html($type_label) . '</td>';
				echo '<td>';
				if ($order_id && $order_id !== 'N/A') {
					echo '<a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '" target="_blank">#' . esc_html($order_id) . '</a>';
				} else {
					echo 'N/A';
				}
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		} else {
			echo '<p>' . __('No se han realizado sorteos aún.', 'sorteo-sco') . '</p>';
		}
		?>
		<p class="description"><?php _e('Lista de todos los premios entregados a través del sistema de sorteos.', 'sorteo-sco'); ?></p>
	</div>

	<!-- Tab Content: Logs -->
	<div id="content-logs" class="tab-content">
		<h3><?php _e('Logs de Stock de Paquetes', 'sorteo-sco'); ?></h3>
		<?php
		$logs = get_option('sorteo_sco_stock_logs', array());
		if (!empty($logs)) {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>';
			echo '<th>' . __('Fecha/Hora', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Acción', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Pedido', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Producto', 'sorteo-sco') . '</th>';
			echo '<th>' . __('Cantidad', 'sorteo-sco') . '</th>';
			echo '</tr></thead><tbody>';
			$recent_logs = array_slice(array_reverse($logs), 0, 30);
			foreach ($recent_logs as $log) {
				echo '<tr>';
				echo '<td>' . esc_html($log['time']) . '</td>';
				echo '<td>' . esc_html($log['action'] === 'reduce' ? __('Descontado', 'sorteo-sco') : __('Restaurado', 'sorteo-sco')) . '</td>';
				echo '<td>' . esc_html($log['order_number']) . '</td>';
				echo '<td>' . esc_html($log['product_name']) . ' (#' . esc_html($log['product_id']) . ')</td>';
				echo '<td>' . esc_html($log['qty']) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . __('No hay logs de stock registrados aún.', 'sorteo-sco') . '</p>';
		}
		?>
		<p class="description"><?php _e('Mostrando los últimos 30 eventos de stock para productos de paquetes (descontados/restaurados).', 'sorteo-sco'); ?></p>

		<hr style="margin: 30px 0;">

		<h3><?php _e('Logs de Errores del Sistema', 'sorteo-sco'); ?></h3>
		<?php
		// Leer debug.log y filtrar solo errores de Sorteo SCO
		$debug_log_path = WP_CONTENT_DIR . '/debug.log';
		$sorteo_errors = array();

		if (file_exists($debug_log_path)) {
			$log_lines = file($debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($log_lines !== false) {
				// Filtrar solo líneas de Sorteo SCO con ERROR
				$log_lines = array_reverse($log_lines); // Más recientes primero
				foreach ($log_lines as $line) {
					if (stripos($line, 'Sorteo SCO') !== false && stripos($line, 'ERROR') !== false) {
						$sorteo_errors[] = $line;
						if (count($sorteo_errors) >= 50) break; // Mostrar últimos 50 errores
					}
				}
			}
		}

		if (!empty($sorteo_errors)) {
			echo '<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
			foreach ($sorteo_errors as $error) {
				// Extraer timestamp si existe
				preg_match('/\[(.*?)\]/', $error, $matches);
				$timestamp = isset($matches[1]) ? $matches[1] : '';

				// Resaltar ERROR en rojo
				$error_highlighted = str_replace('ERROR', '<strong style="color: #dc3232;">ERROR</strong>', esc_html($error));

				echo '<div style="padding: 8px 0; border-bottom: 1px solid #f0f0f1;">';
				if ($timestamp) {
					echo '<span style="color: #646970;">[' . esc_html($timestamp) . ']</span> ';
				}
				echo $error_highlighted;
				echo '</div>';
			}
			echo '</div>';
			echo '<p class="description" style="margin-top: 10px;">' . sprintf(__('Mostrando los últimos %d errores del sistema relacionados con Sorteo SCO.', 'sorteo-sco'), count($sorteo_errors)) . '</p>';
		} else {
			echo '<div class="notice notice-success inline" style="margin: 10px 0; padding: 12px;">';
			echo '<p>' . __('✓ No hay errores registrados. El sistema funciona correctamente.', 'sorteo-sco') . '</p>';
			echo '</div>';
		}
		?>

		<?php if (file_exists($debug_log_path)): ?>
			<div style="margin-top: 20px;">
				<a href="<?php echo admin_url('admin.php?page=sorteo-sco-settings&view_full_log=1'); ?>" class="button" style="margin-right: 10px;">
					<?php _e('Ver Log Completo', 'sorteo-sco'); ?>
				</a>
				<span class="description"><?php _e('Ruta del archivo:', 'sorteo-sco'); ?> <code><?php echo esc_html($debug_log_path); ?></code></span>
			</div>
		<?php endif; ?>

		<hr style="margin: 30px 0;">

		<h3><?php _e('Log de Sorteos Ejecutados', 'sorteo-sco'); ?></h3>
		<?php
		$sorteo_logs = array();

		if (file_exists($debug_log_path)) {
			$log_lines = file($debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($log_lines !== false) {
				$log_lines = array_reverse($log_lines);
				foreach ($log_lines as $line) {
					if (
						stripos($line, 'Sorteo SCO') !== false &&
						(stripos($line, 'EJECUTANDO SORTEO') !== false ||
							stripos($line, 'Ganador:') !== false ||
							stripos($line, 'Guardado en historial') !== false)
					) {
						$sorteo_logs[] = $line;
						if (count($sorteo_logs) >= 30) break;
					}
				}
			}
		}

		if (!empty($sorteo_logs)) {
			echo '<div style="background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
			foreach ($sorteo_logs as $log) {
				preg_match('/\[(.*?)\]/', $log, $matches);
				$timestamp = isset($matches[1]) ? $matches[1] : '';

				$log_highlighted = esc_html($log);
				$log_highlighted = str_replace('Ganador:', '<strong style="color: #0073aa;">Ganador:</strong>', $log_highlighted);
				$log_highlighted = str_replace('EJECUTANDO SORTEO', '<strong style="color: #00a32a;">EJECUTANDO SORTEO</strong>', $log_highlighted);
				$log_highlighted = str_replace('Guardado en historial', '<strong style="color: #00a32a;">Guardado en historial</strong>', $log_highlighted);

				echo '<div style="padding: 8px 0; border-bottom: 1px solid #c3e6f5;">';
				if ($timestamp) {
					echo '<span style="color: #646970;">[' . esc_html($timestamp) . ']</span> ';
				}
				echo $log_highlighted;
				echo '</div>';
			}
			echo '</div>';
			echo '<p class="description" style="margin-top: 10px;">' . sprintf(__('Mostrando los últimos %d registros de sorteos ejecutados.', 'sorteo-sco'), count($sorteo_logs)) . '</p>';
		} else {
			echo '<p>' . __('No hay registros de sorteos ejecutados en el log.', 'sorteo-sco') . '</p>';
		}
		?>

		<hr style="margin: 30px 0;">

		<h3><?php _e('Log de Envío de Emails', 'sorteo-sco'); ?></h3>
		<?php
		$email_logs = array();

		if (file_exists($debug_log_path)) {
			$log_lines = file($debug_log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			if ($log_lines !== false) {
				$log_lines = array_reverse($log_lines);
				foreach ($log_lines as $line) {
					if (
						stripos($line, 'Sorteo SCO') !== false &&
						(stripos($line, 'email') !== false ||
							stripos($line, 'Email') !== false ||
							stripos($line, 'Sorteo_SCO_Email') !== false)
					) {
						$email_logs[] = $line;
						if (count($email_logs) >= 30) break;
					}
				}
			}
		}

		if (!empty($email_logs)) {
			echo '<div style="background: #f8f5ff; border: 1px solid #7c3aed; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
			foreach ($email_logs as $log) {
				preg_match('/\[(.*?)\]/', $log, $matches);
				$timestamp = isset($matches[1]) ? $matches[1] : '';

				$log_highlighted = esc_html($log);
				if (stripos($log, 'ERROR') !== false) {
					$log_highlighted = str_replace('ERROR', '<strong style="color: #dc3232;">ERROR</strong>', $log_highlighted);
				} elseif (stripos($log, 'SUCCESS') !== false || stripos($log, 'ENVIADO') !== false) {
					$log_highlighted = str_replace('SUCCESS', '<strong style="color: #00a32a;">SUCCESS</strong>', $log_highlighted);
					$log_highlighted = str_replace('ENVIADO', '<strong style="color: #00a32a;">ENVIADO</strong>', $log_highlighted);
				}
				$log_highlighted = str_replace('Email destino:', '<strong style="color: #7c3aed;">Email destino:</strong>', $log_highlighted);

				echo '<div style="padding: 8px 0; border-bottom: 1px solid #e9d8fd;">';
				if ($timestamp) {
					echo '<span style="color: #646970;">[' . esc_html($timestamp) . ']</span> ';
				}
				echo $log_highlighted;
				echo '</div>';
			}
			echo '</div>';
			echo '<p class="description" style="margin-top: 10px;">' . sprintf(__('Mostrando los últimos %d registros de envío de emails.', 'sorteo-sco'), count($email_logs)) . '</p>';
		} else {
			echo '<p>' . __('No hay registros de envío de emails en el log.', 'sorteo-sco') . '</p>';
		}
		?>
	</div>

	<!-- Tab Content: Exportar -->
	<div id="content-exportar" class="tab-content">
		<h3><?php _e('Exportar Ganadores', 'sorteo-sco'); ?></h3>

		<?php
		// Mostrar mensaje de éxito si se borró el historial
		if (isset($_GET['history_cleared']) && $_GET['history_cleared'] == '1') {
			echo '<div class="notice notice-success is-dismissible" style="margin: 20px 0; padding: 12px;">
				<p><strong>' . __('Historial eliminado correctamente.', 'sorteo-sco') . '</strong> ' . __('Se ha enviado una notificación por email a todos los administradores.', 'sorteo-sco') . '</p>
			</div>';
		}
		?>

		<div style="display: flex; gap: 10px; margin-bottom: 20px;">
			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
				<input type="hidden" name="action" value="sorteo_sco_export_winners" />
				<?php wp_nonce_field('sorteo_export_winners'); ?>
				<button type="submit" class="button button-primary">
					📥 <?php _e('Descargar Ganadores CSV', 'sorteo-sco'); ?>
				</button>
			</form>

			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display: inline-block;">
				<input type="hidden" name="action" value="sorteo_sco_export_purchases" />
				<?php wp_nonce_field('sorteo_export_purchases'); ?>
				<button type="submit" class="button button-primary">
					📊 <?php _e('Descargar Usuario+Compras CSV', 'sorteo-sco'); ?>
				</button>
			</form>
		</div>

		<p class="description" style="margin-bottom: 20px;">
			<strong><?php _e('📥 Ganadores CSV:', 'sorteo-sco'); ?></strong> <?php _e('Exporta solo usuarios elegibles para sorteos', 'sorteo-sco'); ?><br>
			<strong><?php _e('📊 Usuario+Compras CSV:', 'sorteo-sco'); ?></strong> <?php _e('Exporta detalle completo de compras (cada producto como línea separada)', 'sorteo-sco'); ?>
		</p>

		<!-- Botón para ejecutar sorteo manual -->
		<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-bottom: 20px;">
			<input type="hidden" name="action" value="sorteo_sco_run_draw" />
			<button type="submit" class="button button-secondary"><?php _e('Ejecutar Sorteo Manual', 'sorteo-sco'); ?></button>
		</form>

		<!-- Otorgar premio a pedido específico -->
		<div style="background: #fff; border: 1px solid #0073aa; border-radius: 5px; padding: 20px; margin-bottom: 20px;">
			<h4 style="color: #0073aa; margin-top: 0;">🎁 Otorgar Premio a Pedido Específico</h4>
			<p><?php _e('Selecciona un pedido del período configurado para otorgarle un premio manualmente.', 'sorteo-sco'); ?></p>

			<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="sorteo-manual-prize-form">
				<input type="hidden" name="action" value="sorteo_sco_manual_prize" />
				<?php wp_nonce_field('sorteo_manual_prize', 'sorteo_manual_prize_nonce'); ?>

				<div style="margin-bottom: 15px;">
					<label style="display: block; margin-bottom: 5px; font-weight: bold;">
						<?php _e('Buscar Pedido:', 'sorteo-sco'); ?>
					</label>
					<input type="text" id="sorteo_order_search" placeholder="<?php _e('Escribe número de pedido o nombre de cliente...', 'sorteo-sco'); ?>" style="width: 100%; max-width: 400px; padding: 8px; margin-bottom: 10px;" />

					<select name="sorteo_order_id" id="sorteo_order_select" required style="width: 100%; max-width: 400px; height: 150px;">
						<option value=""><?php _e('-- Selecciona un pedido --', 'sorteo-sco'); ?></option>
						<?php
						// Obtener pedidos del período configurado
						$periodo_inicio = get_option('sorteo_sco_periodo_inicio');
						$periodo_fin = get_option('sorteo_sco_periodo_fin');
						$order_statuses = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));

						// Remover prefijo wc- si existe
						if (is_array($order_statuses)) {
							$order_statuses = array_map(function ($status) {
								return str_replace('wc-', '', $status);
							}, $order_statuses);
						}

						$args = array(
							'limit' => -1,
							'status' => $order_statuses,
							'orderby' => 'date',
							'order' => 'DESC',
						);

						// Agregar filtro de fecha si está configurado
						if ($periodo_inicio && $periodo_fin) {
							$args['date_created'] = $periodo_inicio . '...' . $periodo_fin;
						}

						$orders = wc_get_orders($args);

						foreach ($orders as $order) {
							$order_id = $order->get_id();
							$order_number = $order->get_order_number();
							$customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
							$order_total = $order->get_total();
							$order_date = $order->get_date_created()->date('d/m/Y');

							echo '<option value="' . esc_attr($order_id) . '" data-search="' . esc_attr(strtolower($order_number . ' ' . $customer_name)) . '">';
							echo '#' . esc_html($order_number) . ' - ' . esc_html($customer_name) . ' - ' . wc_price($order_total) . ' (' . esc_html($order_date) . ')';
							echo '</option>';
						}
						?>
					</select>
					<p class="description"><?php _e('Lista de pedidos en el período configurado. Usa el buscador para filtrar rápidamente.', 'sorteo-sco'); ?></p>
				</div>

				<button type="submit" class="button button-primary" onclick="return confirm('¿Confirmas que deseas otorgar el premio configurado a este pedido?');">
					<?php _e('🎁 Otorgar Premio', 'sorteo-sco'); ?>
				</button>
			</form>
		</div>

		<!-- Botón para borrar historial -->
		<div style="margin-bottom: 20px; padding: 20px; border: 2px solid #dc3545; border-radius: 5px; background: #fff5f5;">
			<h4 style="color: #dc3545; margin-top: 0;">⚠️ Zona de Peligro</h4>
			<p><strong>Borrar todo el historial de sorteos</strong></p>
			<p>Esta acción eliminará permanentemente todos los registros de sorteos y enviará una notificación por email a los administradores.</p>

			<form method="post" style="margin-top: 15px;">
				<input type="hidden" name="sorteo_clear_history_action" value="1" />
				<?php wp_nonce_field('sorteo_clear_history_direct', 'sorteo_clear_nonce_direct'); ?>
				<button type="submit" class="button" style="background: #dc3545; border-color: #dc3545; color: white;"
					onclick="return confirm('¿ESTÁS COMPLETAMENTE SEGURO de que quieres borrar TODO el historial de sorteos?\n\nEsta acción NO se puede deshacer.\n\nSe enviará un email de notificación a todos los administradores.\n\n¿Continuar?');">
					<?php _e('🗑️ Borrar Historial Completo', 'sorteo-sco'); ?>
				</button>
			</form>
		</div>

		<div class="export-info" style="background: #f0f8ff; border: 1px solid #0073aa; border-radius: 5px; padding: 15px; margin: 20px 0;">
			<h4 style="margin-top: 0; color: #0073aa;">ℹ️ Información sobre las Exportaciones</h4>
			<ul style="margin-bottom: 0;">
				<li><strong>Formato CSV:</strong> Compatible con Excel, Google Sheets y otros programas de análisis</li>
				<li><strong>Codificación UTF-8:</strong> Incluye BOM para correcta visualización de caracteres especiales</li>
				<li><strong>Nombres de archivo:</strong> Incluyen fecha y hora para fácil identificación</li>
				<li><strong>Respeta filtros:</strong> Solo exporta según categorías y productos configurados</li>
				<li><strong>Descarga directa:</strong> Los archivos se descargan automáticamente al hacer clic</li>
			</ul>
		</div>
	</div>
</div>

<!-- Los estilos CSS se cargan automáticamente desde assets/css/sorteo-admin.css -->
<!-- Los scripts JS se cargan automáticamente desde assets/js/sorteo-admin.js -->