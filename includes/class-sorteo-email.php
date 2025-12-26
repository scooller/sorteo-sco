<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sorteo_SCO_Email {
	/**
	 * Enviar email al ganador
	 * @param int $order_id ID del pedido (no user_id)
	 * @param string $prize Nombre del premio (no usado, se usa config)
	 */
	public static function send_winner_email( $order_id, $prize = '' ) {
		// debug log removed
		
		// Obtener el pedido de WooCommerce
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			// debug log removed
			return false;
		}
		
		// Obtener email del billing (funciona con usuarios registrados y guest)
		$to = $order->get_billing_email();
		if ( empty( $to ) ) {
			// debug log removed
			return false;
		}
		
		// debug logs removed
		
		// Obtener nombre del billing
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$user_name = trim( $first_name . ' ' . $last_name );
		if ( empty( $user_name ) ) {
			$user_name = $to; // Usar email como fallback
		}
		
		$site_name = get_bloginfo( 'name' );
		
		// Obtener el asunto del email de la configuración
		$subject_template = get_option('sorteo_sco_email_subject', '[{sitio}] ¡Felicidades, eres ganador!');
		
		// Obtener el contenido del email de la configuración
		$notice_raw = get_option('sorteo_sco_email_content', '');
		if ( empty( $notice_raw ) ) {
			// Fallback: usar el mensaje del aviso si no hay contenido de email
			$notice_raw = get_option('sorteo_sco_aviso_personalizado', '');
			if ( empty( $notice_raw ) ) {
				// Fallback final
				$notice_raw = __( '¡Felicidades {nombre}! Has ganado el premio: {premio} valorado en {valor}', 'sorteo-sco' );
			}
		}
		
		// Procesar campos personalizados para el contenido
		$message = self::process_custom_fields_for_order( $notice_raw, $order );
		
		// Procesar campos personalizados para el asunto
		$subject = self::process_custom_fields_for_order( $subject_template, $order );
		
		// Headers con From personalizado
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		// Obtener from email y name de la configuración
		$from_email = get_option('sorteo_sco_from_email', get_option('admin_email'));
		$from_name = get_option('sorteo_sco_from_name', get_bloginfo('name'));
		
		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}
		
		// Enviar email
		$sent = wp_mail( $to, $subject, $message, $headers );
		if ( ! $sent && function_exists( 'error_log' ) ) {
			// Log only if there's an error sending the message
			error_log( sprintf( 'Sorteo SCO: ERROR al enviar email de descargas para pedido #%d al email %s', $order->get_id(), $to ) );
		}
		
		// Log del envío
		if ( $sent ) {
			// removed debug log
		} else {
			// removed debug log
		}
		
		return $sent;
	}
	
	/**
	 * Procesar campos personalizados usando datos del pedido
	 */
	private static function process_custom_fields_for_order( $message, $order ) {
		// Obtener nombre del cliente
		$first_name = $order->get_billing_first_name();
		$last_name = $order->get_billing_last_name();
		$user_name = trim( $first_name . ' ' . $last_name );
		if ( empty( $user_name ) ) {
			$user_name = $order->get_billing_email();
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

	/**
	 * Enviar email independiente con TODAS las descargas del pedido
	 * Se dispara cuando el pedido pasa a "completado" y solo una vez por pedido.
	 * Incluye descargas normales y las generadas por sco_package (permiso en tabla WC).
	 */
	public static function send_order_downloads_email( $order_id ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// debug logs removed

		// Early exit if already sent
		if ( 'yes' === $order->get_meta( '_sco_pkg_downloads_email_sent' ) ) {
				// skip debug log
			return true;
		}

		// Evitar duplicados
		if ( 'yes' === $order->get_meta( '_sco_pkg_downloads_email_sent' ) ) {
			return true;
		}

		// Reunir descargas: método nativo + permisos (merge sin duplicados por product_id|download_id)
		$std_downloads = method_exists( $order, 'get_downloadable_items' ) ? (array) $order->get_downloadable_items() : array();
		$downloads      = array();
		$seen_keys      = array(); // product_id|download_id
		$seen_urls      = array(); // fallback URL

		foreach ( $std_downloads as $d ) {
			$product_id  = isset( $d['product_id'] ) ? (int) $d['product_id'] : 0;
			$download_id = isset( $d['download_id'] ) ? (string) $d['download_id'] : '';
			$name        = isset( $d['download_name'] ) ? $d['download_name'] : '';
			$url         = isset( $d['download_url'] ) ? $d['download_url'] : '';
			$key         = $product_id && $download_id ? ( $product_id . '|' . $download_id ) : '';
			if ( $key ) {
				if ( isset( $seen_keys[ $key ] ) ) { continue; }
				$seen_keys[ $key ] = true;
			}
			elseif ( $url ) {
				if ( isset( $seen_urls[ $url ] ) ) { continue; }
				$seen_urls[ $url ] = true;
			}
			$downloads[] = array( 'download_url' => $url, 'download_name' => $name );
		}

		global $wpdb;
		$perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, download_id, order_key, user_email FROM {$perm_table} WHERE order_id = %d", $order->get_id() ), ARRAY_A );
		$perm_count = is_array( $rows ) ? count( $rows ) : 0;
		if ( ! empty( $rows ) ) {
			foreach ( $rows as $r ) {
				$pid         = (int) $r['product_id'];
				$download_id = $r['download_id'];
				$order_key   = $r['order_key'];
				$user_email  = $r['user_email'];

				$prod = wc_get_product( $pid );
				$name = $prod ? $prod->get_name() : __( 'Descarga', 'sorteo-sco' );
				if ( $prod ) {
					$files = $prod->get_downloads();
					if ( isset( $files[ $download_id ] ) ) {
						$name = $files[ $download_id ]->get_name();
					}
				}

				$url = add_query_arg( array(
					'download_file' => $pid,
					'order'         => $order_key,
					'email'         => rawurlencode( $user_email ),
					'key'           => $download_id,
				), home_url( '/' ) );

				$key = $pid . '|' . $download_id;
				if ( isset( $seen_keys[ $key ] ) ) { continue; }
				$seen_keys[ $key ] = true;
				$downloads[] = array( 'download_url' => $url, 'download_name' => $name );
			}
		}

		if ( empty( $downloads ) ) {
			$order->add_order_note( sprintf( 'Descargas no disponibles: no se envió email de descargas para el pedido #%s (ID %d).', $order->get_order_number(), $order->get_id() ) );
			return false;
		}

		$std_count    = is_array( $std_downloads ) ? count( $std_downloads ) : 0;
		$merged_count = is_array( $downloads ) ? count( $downloads ) : 0;
		// removed debug log for counts

		// Si el pedido tiene paquetes pero aún no hay permisos, reintentar vía cron en breve
		$has_pkg = false;
		foreach ( $order->get_items() as $it ) {
			$p = $it->get_product();
			if ( $p && $p->get_type() === 'sco_package' ) { $has_pkg = true; break; }
		}
		if ( $has_pkg && isset( $perm_count ) && (int) $perm_count === 0 ) {
			if ( function_exists( 'wp_schedule_single_event' ) ) {
				$scheduled_ts = time() + 45;
				wp_schedule_single_event( $scheduled_ts, 'sorteo_sco_send_downloads_email_event', array( (int) $order->get_id() ) );
			}
			$order->add_order_note( sprintf( 'Descargas pendientes para pedido #%s (ID %d): reintento programado para %s (hook: %s).', $order->get_order_number(), $order->get_id(), date_i18n( 'd/m/Y H:i', $scheduled_ts ), 'sorteo_sco_send_downloads_email_event' ) );
			return false;
		}

		$to         = $order->get_billing_email();
		$first_name = $order->get_billing_first_name();
		$last_name  = $order->get_billing_last_name();
		$user_name  = trim( $first_name . ' ' . $last_name );
		if ( empty( $user_name ) ) {
			$user_name = $to;
		}

		$site_name = get_bloginfo( 'name' );
		$subject   = sprintf( __( '[%s] Tus descargas del pedido #%s', 'sorteo-sco' ), $site_name, $order->get_order_number() );

		// Obtener colores del tema WooCommerce
		$base_color     = get_option( 'woocommerce_email_base_color', '#96588a' );
		$bg_color       = get_option( 'woocommerce_email_background_color', '#f7f7f7' );
		$body_bg        = get_option( 'woocommerce_email_body_background_color', '#ffffff' );
		$text_color     = get_option( 'woocommerce_email_text_color', '#3c3c3c' );

		// Construir HTML con estilo de WooCommerce
		ob_start();
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>" />
				<title><?php echo esc_html( $subject ); ?></title>
			</head>
			<body <?php echo is_rtl() ? 'rightmargin' : 'leftmargin'; ?>="0" marginwidth="0" topmargin="0" marginheight="0" offset="0" style="padding:0;margin:0;background-color:<?php echo esc_attr( $bg_color ); ?>;">
				<div id="wrapper" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>" style="background-color:<?php echo esc_attr( $bg_color ); ?>;margin:0;padding:70px 0;width:100%;-webkit-text-size-adjust:none;">
					<table border="0" cellpadding="0" cellspacing="0" height="100%" width="100%">
						<tr>
							<td align="center" valign="top">
								<div id="template_header_image">
									<?php if ( $img = get_option( 'woocommerce_email_header_image' ) ) : ?>
										<p style="margin-top:0;"><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" style="height: 250px;" /></p>
									<?php endif; ?>
								</div>
								<table border="0" cellpadding="0" cellspacing="0" width="600" id="template_container" style="background-color:<?php echo esc_attr( $body_bg ); ?>;border:1px solid #dedede;border-radius:3px;box-shadow:0 1px 4px rgba(0,0,0,0.1);">
									<tr>
										<td align="center" valign="top">
											<!-- Header -->
											<table border="0" cellpadding="0" cellspacing="0" width="100%" id="template_header" style="background-color:<?php echo esc_attr( $base_color ); ?>;color:#ffffff;border-bottom:0;font-weight:bold;line-height:100%;vertical-align:middle;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;border-radius:3px 3px 0 0;">
												<tr>
													<td id="header_wrapper" style="padding:36px 48px;display:block;">
														<h1 style="font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:30px;font-weight:300;line-height:150%;margin:0;text-align:left;color:#ffffff;background-color:inherit;">
															<?php esc_html_e( 'Tus descargas', 'sorteo-sco' ); ?>
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
													<td valign="top" id="body_content" style="background-color:<?php echo esc_attr( $body_bg ); ?>;">
														<!-- Content -->
														<table border="0" cellpadding="20" cellspacing="0" width="100%">
															<tr>
																<td valign="top" style="padding:48px 48px 32px;">
																	<div id="body_content_inner" style="color:<?php echo esc_attr( $text_color ); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;line-height:150%;text-align:left;">
																		<p style="margin:0 0 16px;"><?php echo esc_html( sprintf( __( 'Hola %s', 'sorteo-sco' ), $user_name ) ); ?>,</p>
																		<p style="margin:0 0 16px;"><?php echo esc_html( sprintf( __( 'Aquí están tus enlaces de descarga para el pedido #%s', 'sorteo-sco' ), $order->get_order_number() ) ); ?>:</p>
																		
																		<h2 style="color:<?php echo esc_attr( $base_color ); ?>;display:block;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:18px;font-weight:bold;line-height:130%;margin:0 0 18px;text-align:left;">
																			<?php esc_html_e( 'Descargas', 'sorteo-sco' ); ?> (<?php echo esc_html( count( $downloads ) ); ?>)
																		</h2>
																		
																		<table cellspacing="0" cellpadding="6" border="1" style="width:100%;border:1px solid #e5e5e5;border-collapse:collapse;">
																			<thead>
																				<tr>
																					<th scope="col" style="text-align:left;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;color:<?php echo esc_attr( $text_color ); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																						<?php esc_html_e( 'Producto', 'sorteo-sco' ); ?>
																					</th>
																					<th scope="col" style="text-align:left;border:1px solid #e5e5e5;padding:12px;background-color:#f8f8f8;color:<?php echo esc_attr( $text_color ); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																						<?php esc_html_e( 'Descarga', 'sorteo-sco' ); ?>
																					</th>
																				</tr>
																			</thead>
																			<tbody>
																				<?php foreach ( $downloads as $d ) :
																					$name = isset( $d['download_name'] ) ? $d['download_name'] : '';
																					$url  = isset( $d['download_url'] ) ? $d['download_url'] : '';
																				?>
																				<tr>
																					<td style="text-align:left;border:1px solid #e5e5e5;padding:12px;color:<?php echo esc_attr( $text_color ); ?>;font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;font-size:14px;">
																						<?php echo esc_html( $name ); ?>
																					</td>
																					<td style="text-align:left;border:1px solid #e5e5e5;padding:12px;">
																						<a href="<?php echo esc_url( $url ); ?>" style="color:<?php echo esc_attr( $base_color ); ?>;font-weight:normal;text-decoration:underline;">
																							<?php esc_html_e( 'Descargar', 'sorteo-sco' ); ?>
																						</a>
																					</td>
																				</tr>
																				<?php endforeach; ?>
																			</tbody>
																		</table>
																		
																		<p style="margin:24px 0 0;">
																			<?php esc_html_e( 'También puedes acceder a estas descargas desde', 'sorteo-sco' ); ?>
																			<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>" style="color:<?php echo esc_attr( $base_color ); ?>;font-weight:normal;text-decoration:underline;">
																				<?php esc_html_e( 'tu cuenta', 'sorteo-sco' ); ?>
																			</a>.
																		</p>
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
														<?php echo wp_kses_post( wpautop( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) ); ?>
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
		$message = ob_get_clean();

		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$from_email = get_option( 'sorteo_sco_from_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'sorteo_sco_from_name', get_bloginfo( 'name' ) );
		if ( ! empty( $from_email ) && is_email( $from_email ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		$sent = wp_mail( $to, $subject, $message, $headers );
		if ( $sent ) {
			$order->update_meta_data( '_sco_pkg_downloads_email_sent', 'yes' );
			$order->save();
			$order->add_order_note( sprintf( 'Email de descargas del pedido #%s (ID %d) enviado a %s con %d enlace(s).', $order->get_order_number(), $order->get_id(), $to, is_array( $downloads ) ? count( $downloads ) : 0 ) );
		} else {
			$order->add_order_note( sprintf( 'Error al enviar email de descargas del pedido #%s (ID %d) a %s. Revisa configuración de correo.', $order->get_order_number(), $order->get_id(), $to ) );
		}
		return $sent;
	}
}

// Enviar email independiente con todas las descargas según estados configurados
// Función helper para decidir y enviar el email de descargas
function sorteo_sco_maybe_send_downloads_email( $order_id, $force = false ) {
    if ( get_option( 'sorteo_sco_email_downloads_enabled', 'yes' ) !== 'yes' ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $order->add_order_note( sprintf( 'Email de descargas desactivado: no se envió para el pedido #%s (ID %d).', $order->get_order_number(), $order->get_id() ) );
        }
        return;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

	// maybe_send start - debug removed

	// Verificar estado actual del pedido contra los configurados
	$configured = get_option( 'sorteo_sco_order_statuses', array( 'wc-completed', 'wc-processing' ) );
	if ( ! is_array( $configured ) ) {
		$configured = explode( ',', (string) $configured );
	}
	$configured = array_filter( array_map( function( $st ) {
		$st = trim( (string) $st );
		return $st ? str_replace( 'wc-', '', strtolower( $st ) ) : '';
	}, $configured ) );

	$current_status = method_exists( $order, 'get_status' ) ? strtolower( (string) $order->get_status() ) : '';
	// status check - debug removed
    if ( ! $force ) {
        if ( empty( $configured ) || ! in_array( $current_status, $configured, true ) ) {
            $order->add_order_note( sprintf( 'Estado %s no configurado para enviar descargas: pedido #%s (ID %d).', $current_status, $order->get_order_number(), $order->get_id() ) );
            return;
        }
    }

	// Requiere al menos un producto de tipo sco_package
	$has_pkg = false;
	foreach ( $order->get_items() as $it ) {
		$p = $it->get_product();
		if ( $p && $p->get_type() === 'sco_package' ) { $has_pkg = true; break; }
	}
    if ( ! $has_pkg ) {
        $order->add_order_note( sprintf( 'Pedido sin paquetes: no se envió email de descargas para pedido #%s (ID %d).', $order->get_order_number(), $order->get_id() ) );
        return;
    }

	// Si tiene paquetes pero aún no se han concedido permisos, posponer el envío salvo force
	$granted = $order->get_meta( '_sco_pkg_downloads_granted' );
	// debug removed

	if ( $granted !== 'yes' ) {
		// Verificar directamente si existen permisos en la tabla WC para este pedido
		global $wpdb; $perm_table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
		$perm_rows = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$perm_table} WHERE order_id = %d", $order->get_id() ) );
		$perm_rows = intval( $perm_rows );
		// debug removed
		if ( $perm_rows > 0 ) {
			// Marcar como concedidos aunque meta no se haya establecido aún
			$order->update_meta_data( '_sco_pkg_downloads_granted', 'yes' );
			$order->save();
			// debug removed
        } elseif ( ! $force ) {
            if ( function_exists( 'wp_schedule_single_event' ) ) {
                $scheduled_ts = time() + 60;
                wp_schedule_single_event( $scheduled_ts, 'sorteo_sco_send_downloads_email_event', array( (int) $order->get_id() ) );
                $order->add_order_note( sprintf( 'Descargas pendientes para pedido #%s (ID %d): reintento programado para %s (hook: %s).', $order->get_order_number(), $order->get_id(), date_i18n( 'd/m/Y H:i', $scheduled_ts ), 'sorteo_sco_send_downloads_email_event' ) );
            }
            return;
        } else {
            // debug removed
        }
	}

	// debug removed

	Sorteo_SCO_Email::send_order_downloads_email( $order_id );
}

// Evento programado para reintento
add_action( 'sorteo_sco_send_downloads_email_event', function( $order_id ) {
	// Reutiliza la lógica de verificación y estados configurados
	sorteo_sco_maybe_send_downloads_email( (int) $order_id );
}, 10, 1 );

// Nota: No usamos woocommerce_order_status_changed para evitar ejecutar antes
// de que los permisos de descarga sean concedidos. Usamos hooks por estado.

// Disparar en hooks específicos de estados comunes (después de permisos)
foreach ( array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ) as $st ) {
    add_action( 'woocommerce_order_status_' . $st, function( $order_id ) {
        sorteo_sco_maybe_send_downloads_email( $order_id );
    }, 999, 1 );
}

// Al cancelar el pedido, permitir reenvío futuro eliminando la marca de enviado
add_action( 'woocommerce_order_status_cancelled', function( $order_id ) {
	$order = function_exists('wc_get_order') ? wc_get_order( $order_id ) : null;
	if ( ! $order ) return;
	$sent = $order->get_meta( '_sco_pkg_downloads_email_sent' );
	if ( 'yes' === $sent ) {
		$order->delete_meta_data( '_sco_pkg_downloads_email_sent' );
		$order->save();
		// debug removed
	}
}, 20 );
