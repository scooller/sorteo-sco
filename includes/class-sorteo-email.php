<?php

/**
 * Sorteo SCO Email Handler
 * 
 * Gestiona el envío de emails de ganadores y descargas
 * Versión mejorada con compatibilidad universal de temas
 * 
 * @package Sorteo_SCO
 * @since 1.9.11
 */

if (! defined('ABSPATH')) {
	exit;
}

class Sorteo_SCO_Email
{

	/**
	 * Verifica si Font Awesome está cargado en el sitio
	 * 
	 * @return bool
	 * @since 1.9.11
	 */
	private static function has_fontawesome()
	{
		global $wp_styles;
		if (! isset($wp_styles->registered)) {
			return false;
		}
		foreach ($wp_styles->registered as $style) {
			if (strpos($style->src, 'font-awesome') !== false || strpos($style->src, 'fontawesome') !== false) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Envía email EXCLUSIVO con descargas de componentes de paquetes
	 * Procesa TODOS los paquetes del pedido en un solo email
	 * 
	 * @param int $order_id ID del pedido
	 * @param WC_Order_Item_Product $package_item Item del paquete (opcional, si no se pasa procesa todos)
	 * @return bool True si se envió correctamente
	 * @since 1.9.16
	 */
	public static function send_package_component_downloads_email($order_id, $package_item = null)
	{
		$order = wc_get_order($order_id);
		if (!$order) return false;

		// Si se proporciona un item específico, procesarlo solo a él (backward compatibility)
		// Si no, procesar TODOS los paquetes del pedido
		$items_to_process = array();

		if ($package_item) {
			// Modo legacy: procesar solo un paquete
			$items_to_process[] = $package_item;
		} else {
			// Modo nuevo: procesar todos los paquetes
			foreach ($order->get_items() as $item_id => $item) {
				$product = $item->get_product();
				if ($product && $product->get_type() === 'sco_package') {
					if (
						function_exists('sorteo_sco_package_needs_custom_downloads_email') &&
						sorteo_sco_package_needs_custom_downloads_email($product)
					) {
						$items_to_process[] = $item;
					}
				}
			}
		}

		if (empty($items_to_process)) {
			return false;
		}

		// Recolectar todas las descargas de todos los paquetes
		$all_downloads = array();
		$package_names = array();

		foreach ($items_to_process as $item) {
			$downloads = sorteo_sco_get_package_component_downloads($order, $item);
			if (!empty($downloads)) {
				$all_downloads = array_merge($all_downloads, $downloads);
				$pkg_product = $item->get_product();
				if ($pkg_product) {
					$package_names[] = $pkg_product->get_name();
				}
			}
		}

		if (empty($all_downloads)) {
			$order->add_order_note(__('No hay descargas de componentes para enviar.', 'sorteo-sco'));
			return false;
		}

		$to = $order->get_billing_email();
		$site_name = get_bloginfo('name');

		// Si hay múltiples paquetes, usar nombre genérico
		if (count($package_names) > 1) {
			$package_display = sprintf(__('%d paquetes', 'sorteo-sco'), count($package_names));
		} else {
			$package_display = $package_names[0];
		}

		$subject = sprintf(
			__('[%s] Descargas de tu pedido #%s', 'sorteo-sco'),
			$site_name,
			$order->get_order_number()
		);

		// Renderizar HTML personalizado con todas las descargas
		$message = self::render_component_downloads_email_html($order, $all_downloads, $package_display);

		$headers = array('Content-Type: text/html; charset=UTF-8');
		$from_email = get_option('sorteo_sco_from_email', get_option('admin_email'));
		$from_name = get_option('sorteo_sco_from_name', get_bloginfo('name'));

		if (!empty($from_email) && is_email($from_email)) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		$sent = wp_mail($to, $subject, $message, $headers);

		if ($sent) {
			// Crear lista de productos para la nota
			$product_list = array();
			foreach ($all_downloads as $d) {
				$product_list[] = sprintf('%s - %s', $d['product_name'], $d['download_name']);
			}

			$order->add_order_note(sprintf(
				__('Email de descargas de componentes enviado con %d archivo(s) de %s:', 'sorteo-sco') . "\n%s",
				count($all_downloads),
				$package_display,
				implode("\n", $product_list)
			));

			// Guardar orden para persistir nota
			$order->save();
		} else {
			$order->add_order_note(__('ERROR: No se pudo enviar email de descargas de componentes.', 'sorteo-sco'));
			$order->save();

			if (function_exists('error_log')) {
				error_log(sprintf('Sorteo SCO: ERROR al enviar email de componentes para pedido #%d', $order_id));
			}
		}

		return $sent;
	}

	/**
	 * Renderiza HTML del email de descargas de componentes
	 * Reutiliza estilos del email principal pero con contenido diferenciado
	 * 
	 * @param WC_Order $order Pedido
	 * @param array $downloads Array de descargas
	 * @param string $package_name Nombre del paquete
	 * @return string HTML del email
	 * @since 1.9.16
	 */
	private static function render_component_downloads_email_html($order, $downloads, $package_name)
	{
		$colors = self::get_email_colors();
		$header_img = self::get_header_image();
		$user_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		if (empty($user_name)) {
			$user_name = $order->get_billing_email();
		}
		$site_name = get_bloginfo('name');

		ob_start();
?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0" />
			<title><?php echo esc_html(sprintf(__('[%s] Descargas de tu paquete', 'sorteo-sco'), $site_name)); ?></title>
		</head>

		<body style="padding:0;margin:0;background-color:<?php echo esc_attr($colors['bg']); ?>;">
			<div id="wrapper" style="background-color:<?php echo esc_attr($colors['bg']); ?>;padding:70px 0;">
				<table border="0" cellpadding="0" cellspacing="0" width="100%">
					<tr>
						<td align="center">
							<?php if ($header_img): ?>
								<div style="margin-bottom:20px;">
									<img src="<?php echo esc_url($header_img['url']); ?>" alt="<?php echo esc_attr($header_img['alt']); ?>" style="max-height:250px;width:auto;" />
								</div>
							<?php endif; ?>

							<table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:<?php echo esc_attr($colors['body_bg']); ?>;border:1px solid #dedede;border-radius:3px;">
								<tr>
									<td>
										<!-- Header -->
										<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:<?php echo esc_attr($colors['base']); ?>;border-radius:3px 3px 0 0;">
											<tr>
												<td style="padding:36px 48px;">
													<h1 style="font-size:30px;color:#ffffff;margin:0;">
														<?php esc_html_e('Descargas de tu paquete', 'sorteo-sco'); ?>
													</h1>
												</td>
											</tr>
										</table>

										<!-- Body -->
										<table border="0" cellpadding="20" cellspacing="0" width="100%">
											<tr>
												<td style="padding:48px;">
													<p style="margin:0 0 16px;"><?php echo esc_html(sprintf(__('Hola %s', 'sorteo-sco'), $user_name)); ?>,</p>
													<p style="margin:0 0 16px;">
														<?php echo esc_html(sprintf(
															__('Aquí están los archivos descargables de los productos incluidos en tu paquete "%s" (pedido #%s):', 'sorteo-sco'),
															$package_name,
															$order->get_order_number()
														)); ?>
													</p>

													<h2 style="color:<?php echo esc_attr($colors['base']); ?>;font-size:18px;margin:18px 0;">
														<?php esc_html_e('Archivos incluidos', 'sorteo-sco'); ?> (<?php echo count($downloads); ?>)
													</h2>

													<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border:1px solid #e5e5e5;border-collapse:collapse;">
														<thead>
															<tr>
																<th style="text-align:left;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;">
																	<?php esc_html_e('Producto', 'sorteo-sco'); ?>
																</th>
																<th style="text-align:left;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;">
																	<?php esc_html_e('Archivo', 'sorteo-sco'); ?>
																</th>
																<th style="text-align:left;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;">
																	<?php esc_html_e('Descarga', 'sorteo-sco'); ?>
																</th>
															</tr>
														</thead>
														<tbody>
															<?php foreach ($downloads as $d): ?>
																<tr>
																	<td style="text-align:left;border:1px solid #e5e5e5;padding:12px;">
																		<?php echo esc_html($d['product_name']); ?>
																	</td>
																	<td style="text-align:left;border:1px solid #e5e5e5;padding:12px;">
																		<?php echo esc_html($d['download_name']); ?>
																	</td>
																	<td style="text-align:left;border:1px solid #e5e5e5;padding:12px;">
																		<a href="<?php echo esc_url($d['download_url']); ?>" style="color:<?php echo esc_attr($colors['base']); ?>;">
																			<?php esc_html_e('Descargar', 'sorteo-sco'); ?> ⬇
																		</a>
																	</td>
																</tr>
															<?php endforeach; ?>
														</tbody>
													</table>

													<p style="margin:24px 0 0;font-size:12px;color:#666;">
														<strong><?php esc_html_e('Nota:', 'sorteo-sco'); ?></strong>
														<?php esc_html_e('Este email contiene las descargas de los productos dentro de tu paquete. El archivo del paquete principal se envía en el email estándar de WooCommerce.', 'sorteo-sco'); ?>
													</p>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</div>
		</body>

		</html>
	<?php
		return ob_get_clean();
	}

	/**
	 * Obtiene un icono compatible según disponibilidad
	 * 
	 * @param string $type Tipo de icono (cart, download, check)
	 * @return string HTML del icono o fallback unicode
	 * @since 1.9.11
	 */
	private static function get_icon($type = 'download')
	{
		$icons = array(
			'cart'     => self::has_fontawesome() ? '<i class="fa-solid fa-cart-plus"></i>' : '🛒',
			'download' => self::has_fontawesome() ? '<i class="fa-solid fa-download"></i>' : '⬇',
			'check'    => self::has_fontawesome() ? '<i class="fa-solid fa-check"></i>' : '✓',
			'dice'     => self::has_fontawesome() ? '<i class="fa-solid fa-dice"></i>' : '🎲',
		);
		return isset($icons[$type]) ? $icons[$type] : '';
	}

	/**
	 * Obtiene colores de email con fallback a configuración del tema
	 * 
	 * @return array Colores para usar en emails
	 * @since 1.9.11
	 */
	private static function get_email_colors()
	{
		// Prioridad 1: Opciones de WooCommerce
		$base_color = get_option('woocommerce_email_base_color');
		$bg_color   = get_option('woocommerce_email_background_color');
		$body_bg    = get_option('woocommerce_email_body_background_color');
		$text_color = get_option('woocommerce_email_text_color');

		// Prioridad 2: Theme mods (para temas que usan Customizer)
		if (empty($base_color)) {
			$base_color = get_theme_mod('primary_color', get_theme_mod('accent_color'));
		}

		// Fallback a valores por defecto
		$defaults = array(
			'base'      => '#96588a',
			'bg'        => '#f7f7f7',
			'body_bg'   => '#ffffff',
			'text'      => '#3c3c3c',
		);

		return array(
			'base'      => ! empty($base_color) ? $base_color : $defaults['base'],
			'bg'        => ! empty($bg_color) ? $bg_color : $defaults['bg'],
			'body_bg'   => ! empty($body_bg) ? $body_bg : $defaults['body_bg'],
			'text'      => ! empty($text_color) ? $text_color : $defaults['text'],
		);
	}

	/**
	 * Obtiene imagen de cabecera con validación y fallback
	 * 
	 * @return array|false Array con 'url' y 'alt' o false si no hay imagen
	 * @since 1.9.11
	 */
	private static function get_header_image()
	{
		$img_url = get_option('woocommerce_email_header_image');

		// Si no hay imagen configurada, intentar usar el logo del sitio
		if (empty($img_url) && function_exists('get_custom_logo')) {
			$custom_logo_id = get_theme_mod('custom_logo');
			if ($custom_logo_id) {
				$logo = wp_get_attachment_image_src($custom_logo_id, 'full');
				if ($logo) {
					$img_url = $logo[0];
				}
			}
		}

		if (empty($img_url)) {
			return false;
		}

		return array(
			'url' => esc_url($img_url),
			'alt' => get_bloginfo('name'),
		);
	}

	/**
	 * Enviar email al ganador
	 * @param int $order_id ID del pedido (no user_id)
	 * @param string $prize Nombre del premio (no usado, se usa config)
	 */
	public static function send_winner_email($order_id, $prize = '')
	{
		$order = wc_get_order($order_id);
		if (! $order) {
			return false;
		}

		$to = $order->get_billing_email();
		if (empty($to)) {
			return false;
		}

		// Obtener nombre del billing
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$user_name = trim($first_name . ' ' . $last_name);
		if (empty($user_name)) {
			$user_name = $to;
		}

		$site_name = get_bloginfo('name');

		// Obtener el asunto del email de la configuración
		$subject_template = get_option('sorteo_sco_email_subject', '[{sitio}] ¡Felicidades, eres ganador!');

		// Obtener el contenido del email de la configuración
		$notice_raw = get_option('sorteo_sco_email_content', '');
		if (empty($notice_raw)) {
			$notice_raw = get_option('sorteo_sco_aviso_personalizado', '');
			if (empty($notice_raw)) {
				$notice_raw = __('¡Felicidades {nombre}! Has ganado el premio: {premio} valorado en {valor}', 'sorteo-sco');
			}
		}

		// Procesar campos personalizados
		$message = self::process_custom_fields_for_order($notice_raw, $order);
		$subject = self::process_custom_fields_for_order($subject_template, $order);

		// Headers con From personalizado
		$headers = array('Content-Type: text/plain; charset=UTF-8');

		$from_email = get_option('sorteo_sco_from_email', get_option('admin_email'));
		$from_name = get_option('sorteo_sco_from_name', get_bloginfo('name'));

		if (! empty($from_email) && is_email($from_email)) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		// Enviar email
		$sent = wp_mail($to, $subject, $message, $headers);
		if (! $sent && function_exists('error_log')) {
			error_log(sprintf('Sorteo SCO: ERROR al enviar email de ganador para pedido #%d al email %s', $order->get_id(), $to));
		}

		return $sent;
	}

	/**
	 * Procesar campos personalizados usando datos del pedido
	 */
	private static function process_custom_fields_for_order($message, $order)
	{
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$user_name = trim($first_name . ' ' . $last_name);
		if (empty($user_name)) {
			$user_name = $order->get_billing_email();
		}

		$prize_name = get_option('sorteo_sco_prize_name', 'Premio sorpresa');
		$prize_price = floatval(get_option('sorteo_sco_prize_price', 0));
		$prize_value = sorteo_sco_format_price($prize_price);
		$fecha_sorteo = date_i18n('d/m/Y H:i');
		$site_name = get_bloginfo('name');

		$replacements = array(
			'{nombre}' => $user_name,
			'{premio}' => $prize_name,
			'{valor}' => $prize_value,
			'{fecha}' => $fecha_sorteo,
			'{sitio}' => $site_name,
			'{nombre_usuario}' => $user_name,
			'{nombre_premio}' => $prize_name,
			'{valor_premio}' => $prize_value,
			'{fecha_sorteo}' => $fecha_sorteo,
			'{nombre_sitio}' => $site_name
		);

		return str_replace(array_keys($replacements), array_values($replacements), $message);
	}

	/**
	 * Obtiene permisos de descarga con caché
	 * 
	 * @param int $order_id ID del pedido
	 * @return int Cantidad de permisos
	 * @since 1.9.11
	 */
	private static function get_permissions_count($order_id)
	{
		$cache_key = 'sco_perms_count_' . $order_id;
		$perm_count = wp_cache_get($cache_key);

		if (false === $perm_count) {
			global $wpdb;
			$perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
			$perm_count = $wpdb->get_var($wpdb->prepare(
				"SELECT COUNT(*) FROM {$perm_table} WHERE order_id = %d",
				$order_id
			));
			$perm_count = intval($perm_count);

			// Cachear por 1 hora
			wp_cache_set($cache_key, $perm_count, '', 3600);
		}

		return $perm_count;
	}

	/**
	 * Renderiza HTML del email de descargas
	 * Usa template sobrescribible por temas
	 * 
	 * @param WC_Order $order Pedido
	 * @param array $downloads Array de descargas
	 * @return string HTML del email
	 * @since 1.9.11
	 */
	private static function render_email_html($order, $downloads)
	{
		$colors = self::get_email_colors();
		$header_img = self::get_header_image();

		$to = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$user_name = trim($first_name . ' ' . $last_name);
		if (empty($user_name)) {
			$user_name = $to;
		}

		$site_name = get_bloginfo('name');

		// Permitir que temas personalicen via template
		$template_path = locate_template(array(
			'sorteo-sco/emails/downloads-email.php',
			'woocommerce/emails/sorteo-downloads.php',
		));

		if ($template_path) {
			ob_start();
			include $template_path;
			return ob_get_clean();
		}

		// Template por defecto
		ob_start();
	?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>

		<head>
			<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
			<meta name="viewport" content="width=device-width, initial-scale=1.0" />
			<title><?php echo esc_html(sprintf(__('[%s] Tus descargas del pedido #%s', 'sorteo-sco'), $site_name, $order->get_order_number())); ?></title>
			<!--[if mso]>
		<style type="text/css">
			body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
		</style>
		<![endif]-->
		</head>

		<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="padding:0;margin:0;background-color:<?php echo esc_attr($colors['bg']); ?>;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
			<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="background-color:<?php echo esc_attr($colors['bg']); ?>;margin:0;padding:70px 0 70px 0;width:100%;">
				<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
					<tr>
						<td align="center" valign="top">
							<?php if ($header_img) : ?>
								<div id="template_header_image" style="margin-bottom:20px;">
									<p style="margin-top:0;"><img src="<?php echo esc_url($header_img['url']); ?>" alt="<?php echo esc_attr($header_img['alt']); ?>" style="border:none;display:inline-block;font-size:14px;font-weight:bold;max-height:250px;width:auto;height:auto;outline:none;text-decoration:none;" /></p>
								</div>
							<?php endif; ?>

							<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="background-color:<?php echo esc_attr($colors['body_bg']); ?>;border:1px solid #dedede;border-radius:3px;box-shadow:0 1px 4px rgba(0,0,0,0.1);">
								<tr>
									<td align="center" valign="top">
										<!-- Header -->
										<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background-color:<?php echo esc_attr($colors['base']); ?>;color:#ffffff;border-bottom:0;font-weight:bold;line-height:100%;vertical-align:middle;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;border-radius:3px 3px 0 0;">
											<tr>
												<td id="header_wrapper" style="padding:36px 48px;display:block;">
													<h1 style="font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:30px;font-weight:300;line-height:150%;margin:0;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;color:#ffffff;background-color:inherit;">
														<?php esc_html_e('Tus descargas', 'sorteo-sco'); ?>
													</h1>
												</td>
											</tr>
										</table>
										<!-- End Header -->
									</td>
								</tr>
								<tr>
									<td align="center" valign="top">
										<!-- Body -->
										<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_body">
											<tr>
												<td valign="top" id="body_content" style="background-color:<?php echo esc_attr($colors['body_bg']); ?>;">
													<!-- Content -->
													<table border="0" cellpadding="20" cellspacing="0" width="100%">
														<tr>
															<td valign="top" style="padding:48px 48px 32px;">
																<div id="body_content_inner" style="color:<?php echo esc_attr($colors['text']); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
																	<p style="margin:0 0 16px;"><?php echo esc_html(sprintf(__('Hola %s', 'sorteo-sco'), $user_name)); ?>,</p>
																	<p style="margin:0 0 16px;"><?php echo esc_html(sprintf(__('Aquí están tus enlaces de descarga para el pedido #%s', 'sorteo-sco'), $order->get_order_number())); ?>:</p>

																	<h2 style="color:<?php echo esc_attr($colors['base']); ?>;display:block;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;">
																		<?php esc_html_e('Descargas', 'sorteo-sco'); ?> (<?php echo esc_html(count($downloads)); ?>)
																	</h2>

																	<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border:1px solid #e5e5e5;border-collapse:collapse;">
																		<thead>
																			<tr>
																				<th scope="col" style="text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;color:<?php echo esc_attr($colors['text']); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																					<?php esc_html_e('Producto', 'sorteo-sco'); ?>
																				</th>
																				<th scope="col" style="text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;color:<?php echo esc_attr($colors['text']); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																					<?php esc_html_e('Descarga', 'sorteo-sco'); ?>
																				</th>
																			</tr>
																		</thead>
																		<tbody>
																			<?php foreach ($downloads as $d) :
																				$name = isset($d['download_name']) ? $d['download_name'] : '';
																				$url  = isset($d['download_url']) ? $d['download_url'] : '';
																			?>
																				<tr>
																					<td style="text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;border:1px solid #e5e5e5;padding:12px;color:<?php echo esc_attr($colors['text']); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																						<?php echo esc_html($name); ?>
																					</td>
																					<td style="text-align:<?php echo is_rtl() ? 'right' : 'left'; ?>;border:1px solid #e5e5e5;padding:12px;">
																						<a href="<?php echo esc_url($url); ?>" style="color:<?php echo esc_attr($colors['base']); ?>;font-weight:normal;text-decoration:underline;">
																							<?php echo self::get_icon('download'); ?> <?php esc_html_e('Descargar', 'sorteo-sco'); ?>
																						</a>
																					</td>
																				</tr>
																			<?php endforeach; ?>
																		</tbody>
																	</table>

																	<?php if (function_exists('wc_get_account_endpoint_url')) : ?>
																		<p style="margin:24px 0 0;">
																			<?php esc_html_e('También puedes acceder a estas descargas desde', 'sorteo-sco'); ?>
																			<a href="<?php echo esc_url(wc_get_account_endpoint_url('downloads')); ?>" style="color:<?php echo esc_attr($colors['base']); ?>;font-weight:normal;text-decoration:underline;">
																				<?php esc_html_e('tu cuenta', 'sorteo-sco'); ?>
																			</a>.
																		</p>
																	<?php endif; ?>
																</div>
															</td>
														</tr>
													</table>
													<!-- End Content -->
												</td>
											</tr>
										</table>
										<!-- End Body -->
									</td>
								</tr>
							</table>
						</td>
					</tr>
					<tr>
						<td align="center" valign="top">
							<!-- Footer -->
							<table border="0" cellpadding="10" cellspacing="0" width="600" id="template_footer">
								<tr>
									<td valign="top" style="padding:0;border-radius:6px;">
										<table border="0" cellpadding="10" cellspacing="0" width="100%">
											<tr>
												<td colspan="2" valign="middle" id="credit" style="border-radius:6px;border:0;color:#8a8a8a;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:12px;line-height:150%;text-align:center;padding:24px 0;">
													<?php
													$footer_text = apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
													if ($footer_text) {
														echo wp_kses_post(wpautop(wptexturize($footer_text)));
													} else {
														echo esc_html($site_name) . ' - ' . esc_html(get_bloginfo('description'));
													}
													?>
												</td>
											</tr>
										</table>
									</td>
								</tr>
							</table>
							<!-- End Footer -->
						</td>
					</tr>
				</table>
			</div>
		</body>

		</html>
<?php
		return ob_get_clean();
	}

	/**
	 * Enviar email independiente con TODAS las descargas del pedido
	 * Se dispara cuando el pedido pasa a estado configurado y solo una vez por pedido.
	 * Incluye descargas normales y las generadas por sco_package (permiso en tabla WC).
	 * 
	 * Mejoras v1.9.11:
	 * - Lock transient para evitar duplicados por race conditions
	 * - Caché de permisos
	 * - Colores con fallback a theme mods
	 * - DOCTYPE compatible con clientes de email
	 * - Iconos condicionales según disponibilidad de Font Awesome
	 * - Template sobrescribible por temas
	 */
	public static function send_order_downloads_email($order_id)
	{
		if (! function_exists('wc_get_order')) {
			return false;
		}

		$order = wc_get_order($order_id);
		if (! $order) {
			return false;
		}

		// Control de envío único con transient lock
		$lock_key = 'sco_sending_email_' . $order_id;
		if (get_transient($lock_key)) {
			return false; // Ya se está enviando
		}
		set_transient($lock_key, true, 120); // Lock por 2 minutos

		// Early exit if already sent
		if ('yes' === $order->get_meta('_sco_pkg_downloads_email_sent')) {
			delete_transient($lock_key);
			return true;
		}

		// Reunir descargas: método nativo + permisos (merge sin duplicados por product_id|download_id)
		$std_downloads = method_exists($order, 'get_downloadable_items') ? (array) $order->get_downloadable_items() : array();
		$downloads      = array();
		$seen_keys      = array(); // product_id|download_id
		$seen_urls      = array(); // fallback URL

		foreach ($std_downloads as $d) {
			$product_id  = isset($d['product_id']) ? (int) $d['product_id'] : 0;
			$download_id = isset($d['download_id']) ? (string) $d['download_id'] : '';
			$name        = isset($d['download_name']) ? $d['download_name'] : '';
			$url         = isset($d['download_url']) ? $d['download_url'] : '';
			$key         = $product_id && $download_id ? ($product_id . '|' . $download_id) : '';
			if ($key) {
				if (isset($seen_keys[$key])) {
					continue;
				}
				$seen_keys[$key] = true;
			} elseif ($url) {
				if (isset($seen_urls[$url])) {
					continue;
				}
				$seen_urls[$url] = true;
			}
			$downloads[] = array('download_url' => $url, 'download_name' => $name);
		}

		// Obtener permisos adicionales de la tabla con caché
		global $wpdb;
		$perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
		$cache_key = 'sco_perms_' . $order_id;
		$rows = wp_cache_get($cache_key);

		if (false === $rows) {
			$rows = $wpdb->get_results($wpdb->prepare("SELECT product_id, download_id, order_key, user_email FROM {$perm_table} WHERE order_id = %d", $order->get_id()), ARRAY_A);
			wp_cache_set($cache_key, $rows, '', 3600); // Cachear 1 hora
		}

		$perm_count = is_array($rows) ? count($rows) : 0;

		if (! empty($rows)) {
			foreach ($rows as $r) {
				$pid         = (int) $r['product_id'];
				$download_id = $r['download_id'];
				$order_key   = $r['order_key'];
				$user_email  = $r['user_email'];

				$prod = wc_get_product($pid);
				$name = $prod ? $prod->get_name() : __('Descarga', 'sorteo-sco');
				if ($prod) {
					$files = $prod->get_downloads();
					if (isset($files[$download_id])) {
						$name = $files[$download_id]->get_name();
					}
				}

				$url = add_query_arg(array(
					'download_file' => $pid,
					'order'         => $order_key,
					'email'         => rawurlencode($user_email),
					'key'           => $download_id,
				), home_url('/'));

				$key = $pid . '|' . $download_id;
				if (isset($seen_keys[$key])) {
					continue;
				}
				$seen_keys[$key] = true;
				$downloads[] = array('download_url' => $url, 'download_name' => $name);
			}
		}

		// Filtrar paquetes que tienen email personalizado
		$filtered_downloads = array();
		foreach ($downloads as $d) {
			$product = wc_get_product($d['product_id'] ?? 0);
			// Solo incluir si NO es paquete con email personalizado
			if (!$product || !sorteo_sco_package_needs_custom_downloads_email($product)) {
				$filtered_downloads[] = $d;
			}
		}

		if (empty($filtered_downloads)) {
			delete_transient($lock_key);
			$order->add_order_note('Email descargas: Todas las descargas se envían por emails personalizados.');
			return true; // No es error, solo que todo va por email personalizado
		}

		// Si el pedido tiene paquetes pero aún no hay permisos, reintentar vía cron en breve
		$has_pkg = false;
		foreach ($order->get_items() as $it) {
			$p = $it->get_product();
			if ($p && $p->get_type() === 'sco_package') {
				$has_pkg = true;
				break;
			}
		}
		if ($has_pkg && isset($perm_count) && (int) $perm_count === 0) {
			delete_transient($lock_key);
			if (function_exists('wp_schedule_single_event')) {
				$scheduled_ts = time() + 45;
				wp_schedule_single_event($scheduled_ts, 'sorteo_sco_send_downloads_email_event', array((int) $order->get_id()));
			}
			$order->add_order_note(sprintf('Descargas pendientes para pedido #%s (ID %d): reintento programado para %s (hook: %s).', $order->get_order_number(), $order->get_id(), date_i18n('d/m/Y H:i', $scheduled_ts), 'sorteo_sco_send_downloads_email_event'));
			return false;
		}

		$to = $order->get_billing_email();
		$site_name = get_bloginfo('name');
		$subject = sprintf(__('[%s] Tus descargas del pedido #%s', 'sorteo-sco'), $site_name, $order->get_order_number());

		// Renderizar HTML usando sistema mejorado con descargas filtradas
		$message = self::render_email_html($order, $filtered_downloads);

		// Permitir filtrado del HTML final
		$message = apply_filters('sorteo_sco_downloads_email_html', $message, $order, $downloads);

		$headers   = array('Content-Type: text/html; charset=UTF-8');
		$from_email = get_option('sorteo_sco_from_email', get_option('admin_email'));
		$from_name  = get_option('sorteo_sco_from_name', get_bloginfo('name'));
		if (! empty($from_email) && is_email($from_email)) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		$sent = wp_mail($to, $subject, $message, $headers);

		// Liberar lock
		delete_transient($lock_key);

		if ($sent) {
			$order->update_meta_data('_sco_pkg_downloads_email_sent', 'yes');
			$order->save();
			$order->add_order_note(sprintf('Email de descargas del pedido #%s (ID %d) enviado a %s con %d enlace(s).', $order->get_order_number(), $order->get_id(), $to, is_array($filtered_downloads) ? count($filtered_downloads) : 0));

			// Limpiar caché de permisos
			wp_cache_delete('sco_perms_' . $order_id);
			wp_cache_delete('sco_perms_count_' . $order_id);
		} else {
			$order->add_order_note(sprintf('Error al enviar email de descargas del pedido #%s (ID %d) a %s. Revisa configuración de correo.', $order->get_order_number(), $order->get_id(), $to));
		}
		return $sent;
	}
}

// Enviar email independiente con todas las descargas según estados configurados
// Función helper para decidir y enviar el email de descargas
function sorteo_sco_maybe_send_downloads_email($order_id, $force = false)
{
	if (get_option('sorteo_sco_email_downloads_enabled', 'yes') !== 'yes') {
		$order = wc_get_order($order_id);
		if ($order) {
			$order->add_order_note(sprintf('Email de descargas desactivado: no se envió para el pedido #%s (ID %d).', $order->get_order_number(), $order->get_id()));
		}
		return;
	}
	$order = wc_get_order($order_id);
	if (! $order) return;

	// Verificar estado actual del pedido contra los configurados
	$configured = get_option('sorteo_sco_order_statuses', array('wc-completed', 'wc-processing'));
	if (! is_array($configured)) {
		$configured = explode(',', (string) $configured);
	}
	$configured = array_filter(array_map(function ($st) {
		$st = trim((string) $st);
		return $st ? str_replace('wc-', '', strtolower($st)) : '';
	}, $configured));

	$current_status = method_exists($order, 'get_status') ? strtolower((string) $order->get_status()) : '';

	if (! $force) {
		if (empty($configured) || ! in_array($current_status, $configured, true)) {
			$order->add_order_note(sprintf('Estado %s no configurado para enviar descargas: pedido #%s (ID %d).', $current_status, $order->get_order_number(), $order->get_id()));
			return;
		}
	}

	// Requiere al menos un producto de tipo sco_package
	$has_pkg = false;
	foreach ($order->get_items() as $it) {
		$p = $it->get_product();
		if ($p && $p->get_type() === 'sco_package') {
			$has_pkg = true;
			break;
		}
	}
	if (! $has_pkg) {
		$order->add_order_note(sprintf('Pedido sin paquetes: no se envió email de descargas para pedido #%s (ID %d).', $order->get_order_number(), $order->get_id()));
		return;
	}

	// Si tiene paquetes pero aún no se han concedido permisos, posponer el envío salvo force
	$granted = $order->get_meta('_sco_pkg_downloads_granted');

	if ($granted !== 'yes') {
		// Usar método con caché
		$perm_rows = Sorteo_SCO_Email::get_permissions_count($order->get_id());

		if ($perm_rows > 0) {
			// Marcar como concedidos aunque meta no se haya establecido aún
			$order->update_meta_data('_sco_pkg_downloads_granted', 'yes');
			$order->save();
		} elseif (! $force) {
			if (function_exists('wp_schedule_single_event')) {
				$scheduled_ts = time() + 60;
				wp_schedule_single_event($scheduled_ts, 'sorteo_sco_send_downloads_email_event', array((int) $order->get_id()));
				$order->add_order_note(sprintf('Descargas pendientes para pedido #%s (ID %d): reintento programado para %s (hook: %s).', $order->get_order_number(), $order->get_id(), date_i18n('d/m/Y H:i', $scheduled_ts), 'sorteo_sco_send_downloads_email_event'));
			}
			return;
		}
	}

	Sorteo_SCO_Email::send_order_downloads_email($order_id);
}

// Evento programado para reintento
add_action('sorteo_sco_send_downloads_email_event', function ($order_id) {
	sorteo_sco_maybe_send_downloads_email((int) $order_id);
}, 10, 1);

// Disparar en hooks específicos de estados comunes (después de permisos)
foreach (array('pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed') as $st) {
	add_action('woocommerce_order_status_' . $st, function ($order_id) {
		sorteo_sco_maybe_send_downloads_email($order_id);
	}, 999, 1);
}

// Al cancelar el pedido, permitir reenvío futuro eliminando la marca de enviado
add_action('woocommerce_order_status_cancelled', function ($order_id) {
	$order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
	if (! $order) return;
	$sent = $order->get_meta('_sco_pkg_downloads_email_sent');
	if ('yes' === $sent) {
		$order->delete_meta_data('_sco_pkg_downloads_email_sent');
		$order->save();

		// Limpiar caché de permisos
		wp_cache_delete('sco_perms_' . $order_id);
		wp_cache_delete('sco_perms_count_' . $order_id);
	}

	// Limpiar también metas de email de componentes
	foreach ($order->get_items() as $item_id => $item) {
		$sent_key = '_sco_pkg_components_email_sent_' . $item_id;
		if ($order->get_meta($sent_key)) {
			$order->delete_meta_data($sent_key);
		}
	}
	$order->save();
}, 20);

/**
 * Verifica si un paquete necesita email personalizado de descargas de componentes
 * Solo si el paquete es virtual + descargable + tiene archivo descargable propio
 * 
 * @param WC_Product $product Producto paquete
 * @return bool True si necesita email personalizado
 * @since 1.9.16
 */
function sorteo_sco_package_needs_custom_downloads_email($product)
{
	if (!$product || $product->get_type() !== 'sco_package') {
		return false;
	}

	// El email debe enviarse si el paquete es virtual Y tiene componentes descargables
	// NO depende de si el paquete padre tiene archivos propios
	return $product->is_virtual();
}

/**
 * Obtiene descargas de productos componentes del paquete
 * EXCLUYE archivos del paquete padre
 * 
 * @param WC_Order $order Pedido
 * @param WC_Order_Item_Product $item Item del paquete
 * @return array Array de descargas con [download_url, download_name, product_name]
 * @since 1.9.16
 */
function sorteo_sco_get_package_component_downloads($order, $item)
{
	$downloads = array();
	$seen_keys = array(); // Dedupe por product_id|download_id

	// Leer composición
	$pkg = $item->get_meta('_sco_package', true);
	if (empty($pkg) || empty($pkg['components'])) {
		return $downloads;
	}

	// Obtener permisos SOLO de componentes
	global $wpdb;
	$perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';

	foreach ($pkg['components'] as $comp) {
		$comp_product_id = (int)$comp['product_id'];

		// Query permisos de este componente específico
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT product_id, download_id, order_key, user_email 
			 FROM {$perm_table} 
			 WHERE order_id = %d AND product_id = %d",
			$order->get_id(),
			$comp_product_id
		), ARRAY_A);

		if (empty($rows)) continue;

		foreach ($rows as $r) {
			$pid = (int)$r['product_id'];
			$download_id = $r['download_id'];
			$key = $pid . '|' . $download_id;

			if (isset($seen_keys[$key])) continue;
			$seen_keys[$key] = true;

			$prod = wc_get_product($pid);
			$file_name = '';
			$product_name = '';

			if ($prod) {
				$product_name = $prod->get_name();
				$files = $prod->get_downloads();
				if (isset($files[$download_id])) {
					$file_name = $files[$download_id]->get_name();
				}
			}

			$url = add_query_arg(array(
				'download_file' => $pid,
				'order' => $r['order_key'],
				'email' => rawurlencode($r['user_email']),
				'key' => $download_id,
			), home_url('/'));

			$downloads[] = array(
				'download_url' => $url,
				'download_name' => $file_name ?: __('Descarga', 'sorteo-sco'),
				'product_name' => $product_name,
			);
		}
	}

	return $downloads;
}
