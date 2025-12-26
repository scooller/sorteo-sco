<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sorteo_SCO_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'wp_ajax_sorteo_metrics_chart_data', [ $this, 'ajax_metrics_chart_data' ] );
	}

	public function add_admin_menu() {
		add_menu_page(
			'Sorteo',
			'Sorteo',
			'manage_options',
			'sorteo-sco-settings',
			[ $this, 'render_settings_page' ],
			'dashicons-awards',
			56
		);
	}

	public function render_settings_page() {
		include plugin_dir_path( __FILE__ ) . '../templates/admin-settings.php';
	}

	/**
	 * AJAX: Obtener datos de gráficos con diferentes rangos
	 */
	public function ajax_metrics_chart_data() {
		// Verificar nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sorteo_sco_nonce')) {
			wp_send_json_error(array('message' => __('Nonce inválido', 'sorteo-sco')), 400);
		}

		// Verificar permisos
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('No autorizado', 'sorteo-sco')), 401);
		}

		$metrics = new Sorteo_SCO_Metrics();

		// Verificar si es rango personalizado o preestablecido
		if (isset($_POST['from']) && isset($_POST['to'])) {
			$from = sanitize_text_field($_POST['from']);
			$to = sanitize_text_field($_POST['to']);
			$earnings = $metrics->get_earnings_by_date_range($from, $to);
		} else {
			$days = isset($_POST['days']) ? intval($_POST['days']) : 30;
			$allowed = array(7, 30, 90);
			if (!in_array($days, $allowed, true)) {
				$days = 30;
			}
			$earnings = $metrics->get_earnings_by_day($days);
		}

		wp_send_json_success(array('earnings' => $earnings));
	}
}

// Inicializa la clase en el admin
if ( is_admin() ) {
	new Sorteo_SCO_Admin();
	add_action('admin_init', function() {
		// Asegurar que las opciones del mensaje existen con valores por defecto
		if (false === get_option('sorteo_sco_mensaje_bg_color')) {
			add_option('sorteo_sco_mensaje_bg_color', '#4caf50');
		}
		if (false === get_option('sorteo_sco_mensaje_text_color')) {
			add_option('sorteo_sco_mensaje_text_color', '#ffffff');
		}
		if (false === get_option('sorteo_sco_mensaje_font_family')) {
			add_option('sorteo_sco_mensaje_font_family', 'inherit');
		}
		if (false === get_option('sorteo_sco_mensaje_position')) {
			add_option('sorteo_sco_mensaje_position', 'top');
		}
		if (false === get_option('sorteo_sco_mensaje_effect')) {
			add_option('sorteo_sco_mensaje_effect', 'none');
		}
		if (false === get_option('sorteo_sco_mensaje_duration')) {
			add_option('sorteo_sco_mensaje_duration', '10');
		}
		
		// Registrar las nuevas opciones separadas para fechas
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_periodo_inicio');
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_periodo_fin');
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_categorias', array(
			'sanitize_callback' => function($value) {
				if (is_array($value)) {
					return implode(',', array_map('intval', $value));
				}
				return $value;
			}
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_productos_especiales', array(
			'sanitize_callback' => function($value) {
				if (is_array($value)) {
					return implode(',', array_map('intval', $value));
				}
				return $value;
			}
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_aviso_personalizado');
		// Mensaje cuando el paquete no puede armarse por productos reservados
		if (false === get_option('sorteo_sco_mensaje_producto_reservado')) {
			add_option(
				'sorteo_sco_mensaje_producto_reservado',
				__('No hay suficientes productos disponibles en este momento (algunos pueden estar reservados por otros usuarios). Vuelve a intentar para generar una nueva combinación al azar.', 'sorteo-sco')
			);
		}
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_producto_reservado', array(
			'sanitize_callback' => 'sanitize_textarea_field'
		));
		// Mensaje informativo cuando hubo reemplazos por productos reservados
		if (false === get_option('sorteo_sco_mensaje_reemplazos')) {
			add_option(
				'sorteo_sco_mensaje_reemplazos',
				__('Nota: %d producto(s) estaban reservados por otros usuarios y se eligieron alternativas para completar tu paquete. Si deseas una nueva combinación al azar, elimina este paquete del carrito y vuelve a agregarlo.', 'sorteo-sco')
			);
		}
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_reemplazos', array(
			'sanitize_callback' => 'sanitize_textarea_field'
		));
		// Toggle: Mostrar mensaje de reemplazos (default yes)
		if (false === get_option('sorteo_sco_mostrar_mensaje_reemplazos')) {
			add_option('sorteo_sco_mostrar_mensaje_reemplazos', 'yes');
		}
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mostrar_mensaje_reemplazos', array(
			'sanitize_callback' => function($value) { return $value === 'yes' ? 'yes' : 'no'; },
			'default' => 'yes'
		));
		// Toggle: Restock components when order cancelled/refunded (default yes)
		if (false === get_option('sorteo_sco_restock_on_cancel')) {
			add_option('sorteo_sco_restock_on_cancel', 'yes');
		}
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_restock_on_cancel', array(
			'sanitize_callback' => function($value) { return $value === 'yes' ? 'yes' : 'no'; },
			'default' => 'yes'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_min_ganancia');
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_prize_price'); // Precio del premio
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_prize_name'); // Nombre del premio
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_from_email', array(
			'sanitize_callback' => 'sanitize_email',
			'default' => get_option('admin_email')
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_from_name', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => get_bloginfo('name')
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_order_statuses', array(
			'sanitize_callback' => function($value) {
				if (is_array($value)) {
					return $value; // Mantener como array
				}
				return array('wc-completed', 'wc-processing'); // Valor por defecto
			}
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_marco_visual');

			// Toggle: Email extra con todas las descargas (default yes)
			if (false === get_option('sorteo_sco_email_downloads_enabled')) {
				add_option('sorteo_sco_email_downloads_enabled', 'yes');
			}
			register_setting('sorteo_sco_settings_group', 'sorteo_sco_email_downloads_enabled', array(
				'sanitize_callback' => function($value) { return $value === 'yes' ? 'yes' : 'no'; },
				'default' => 'yes'
			));
		
		// Nuevas opciones del tab Mensaje
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_bg_color', array(
			'sanitize_callback' => 'sanitize_hex_color',
			'default' => '#4caf50'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_text_color', array(
			'sanitize_callback' => 'sanitize_hex_color',
			'default' => '#ffffff'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_font_family', array(
			'sanitize_callback' => 'sanitize_text_field',
			'default' => 'inherit'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_position', array(
			'sanitize_callback' => function($value) {
				$allowed_positions = array('top', 'center', 'bottom');
				return in_array($value, $allowed_positions) ? $value : 'top';
			},
			'default' => 'top'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_effect', array(
			'sanitize_callback' => function($value) {
				$allowed_effects = array('none', 'fade', 'slide', 'bounce', 'pulse', 'shake');
				return in_array($value, $allowed_effects) ? $value : 'none';
			},
			'default' => 'none'
		));
		register_setting('sorteo_sco_settings_group', 'sorteo_sco_mensaje_duration', array(
			'sanitize_callback' => function($value) {
				$duration = intval($value);
				return ($duration >= 3 && $duration <= 60) ? $duration : 10;
			},
			'default' => 10
		));
	});
	
	// Encolar scripts necesarios para el media uploader y estilos del admin
	add_action('admin_enqueue_scripts', function($hook) {
		if ('toplevel_page_sorteo-sco-settings' === $hook) {
			// WordPress media library
			wp_enqueue_media();
			wp_enqueue_script('jquery');
			
			// Chart.js CDN
			wp_register_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
			wp_enqueue_script('chartjs');
			
			// CSS del admin
			wp_enqueue_style(
				'sorteo-sco-admin',
				plugin_dir_url(__FILE__) . '../assets/css/sorteo-admin.css',
				array(),
				'1.4.1'
			);
			
			// Select2 / SelectWoo (WooCommerce)
			if ( wp_script_is( 'selectWoo', 'registered' ) ) {
				wp_enqueue_script( 'selectWoo' );
			} else {
				wp_register_script( 'selectWoo', plugins_url( 'woocommerce/assets/js/selectWoo/selectWoo.full.min.js' ), array( 'jquery' ), '1.0.6', true );
				wp_enqueue_script( 'selectWoo' );
			}
			if ( wp_style_is( 'select2', 'registered' ) ) {
				wp_enqueue_style( 'select2' );
			} else {
				wp_register_style( 'select2', plugins_url( 'woocommerce/assets/css/select2.css' ), array(), '4.0.13' );
				wp_enqueue_style( 'select2' );
			}

			// JS del admin
			wp_enqueue_script(
				'sorteo-sco-admin',
				plugin_dir_url(__FILE__) . '../assets/js/sorteo-admin.js',
				array('jquery','selectWoo'),
				'1.4.1',
				true
			);
			
			// JS para los gráficos de métricas
			wp_enqueue_script(
				'sorteo-sco-metrics',
				plugin_dir_url(__FILE__) . '../assets/js/sorteo-metrics.js',
				array('jquery', 'chartjs'),
				'1.4.1',
				true
			);
			
			// Preparar datos iniciales de gráficos (últimos 30 días)
			$metrics_instance = new Sorteo_SCO_Metrics();
			$earnings_by_day = $metrics_instance->get_earnings_by_day(30);
			$prizes_data = $metrics_instance->get_prizes_breakdown();
			
			// Pasar datos de gráficos al frontend
			wp_localize_script('sorteo-sco-metrics', 'sorteoMetricsData', array(
				'earnings' => $earnings_by_day,
				'prizes' => $prizes_data,
				'i18n' => array(
					'earnings' => __('Ganancias', 'sorteo-sco'),
					'prizes' => __('Premios', 'sorteo-sco'),
				),
			));
			
			// Variables para JavaScript
			wp_localize_script('sorteo-sco-admin', 'sorteo_admin_vars', array(
				'media_title' => __('Seleccionar Marco Visual', 'sorteo-sco'),
				'media_button' => __('Seleccionar', 'sorteo-sco'),
				'nonce' => wp_create_nonce('sorteo_sco_nonce'),
				'ajaxurl' => admin_url('admin-ajax.php'),
				'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$',
			));
		}
	});
}
