jQuery(document).ready(function($) {
	'use strict';

	// Verificar que Chart.js esté disponible
	if (typeof Chart === 'undefined') {
		console.error('Chart.js no está cargado');
		return;
	}

	// Verificar que los datos estén disponibles
	if (typeof sorteoMetricsData === 'undefined') {
		console.error('sorteoMetricsData no está disponible');
		return;
	}

	let earningsChart = null;
	let prizesChart = null;

	/**
	 * Inicializar gráficos de métricas
	 */
	function initMetricsCharts() {
		// Gráfico de línea: Ganancias por día
		const earningsCanvas = document.getElementById('sorteo-earnings-chart');
		if (earningsCanvas) {
			const ctx = earningsCanvas.getContext('2d');
			earningsChart = new Chart(ctx, {
				type: 'line',
				data: {
					labels: sorteoMetricsData.earnings.labels,
					datasets: [{
						label: sorteoMetricsData.i18n.earnings,
						data: sorteoMetricsData.earnings.data,
						borderColor: 'rgb(34, 113, 177)',
						backgroundColor: 'rgba(34, 113, 177, 0.1)',
						tension: 0.4,
						fill: true
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: true,
							position: 'top'
						},
						tooltip: {
							callbacks: {
								label: function(context) {
									let label = context.dataset.label || '';
									if (label) {
										label += ': ';
									}
									// Usar símbolo de moneda de WooCommerce si está disponible
									const currencySymbol = sorteo_admin_vars.currency_symbol || '$';
									label += currencySymbol + context.parsed.y.toFixed(2);
									return label;
								}
							}
						}
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function(value) {
									const currencySymbol = sorteo_admin_vars.currency_symbol || '$';
									return currencySymbol + value.toFixed(0);
								}
							}
						}
					}
				}
			});
		}

		// Gráfico de torta: Distribución de premios
		const prizesCanvas = document.getElementById('sorteo-prizes-chart');
		if (prizesCanvas) {
			const ctx = prizesCanvas.getContext('2d');
			prizesChart = new Chart(ctx, {
				type: 'pie',
				data: {
					labels: sorteoMetricsData.prizes.labels,
					datasets: [{
						data: sorteoMetricsData.prizes.data,
						backgroundColor: [
							'rgba(34, 113, 177, 0.8)',
							'rgba(124, 58, 237, 0.8)'
						],
						borderColor: [
							'rgb(34, 113, 177)',
							'rgb(124, 58, 237)'
						],
						borderWidth: 1
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: true,
							position: 'bottom'
						}
					}
				}
			});
		}
	}

	/**
	 * Actualizar gráfico de ganancias con AJAX
	 */
	function updateEarningsChart(days) {
		if (!earningsChart) return;

		$.ajax({
			url: sorteo_admin_vars.ajaxurl,
			type: 'POST',
			data: {
				action: 'sorteo_metrics_chart_data',
				nonce: sorteo_admin_vars.nonce,
				days: days
			},
			success: function(response) {
				if (response.success && response.data.earnings) {
					earningsChart.data.labels = response.data.earnings.labels;
					earningsChart.data.datasets[0].data = response.data.earnings.data;
					earningsChart.update();
				}
			},
			error: function(xhr, status, error) {
				console.error('Error al actualizar gráfico:', error);
			}
		});
	}

	/**
	 * Actualizar gráfico con rango de fechas personalizado
	 */
	function updateEarningsChartRange(from, to) {
		if (!earningsChart) return;

		$.ajax({
			url: sorteo_admin_vars.ajaxurl,
			type: 'POST',
			data: {
				action: 'sorteo_metrics_chart_data',
				nonce: sorteo_admin_vars.nonce,
				from: from,
				to: to
			},
			success: function(response) {
				if (response.success && response.data.earnings) {
					earningsChart.data.labels = response.data.earnings.labels;
					earningsChart.data.datasets[0].data = response.data.earnings.data;
					earningsChart.update();
				}
			},
			error: function(xhr, status, error) {
				console.error('Error al actualizar gráfico:', error);
			}
		});
	}

	// Inicializar gráficos cuando se muestra el tab de métricas
	$(document).on('click', '#tab-metricas', function() {
		setTimeout(function() {
			if (!earningsChart && !prizesChart) {
				initMetricsCharts();
			}
		}, 100);
	});

	// Si el tab de métricas está activo al cargar
	if ($('#content-metricas').hasClass('active')) {
		initMetricsCharts();
	}

	// Botones de rango de días
	$(document).on('click', '.sorteo-chart-range-btn', function(e) {
		e.preventDefault();
		const days = $(this).data('days');
		$('.sorteo-chart-range-btn').removeClass('active');
		$(this).addClass('active');
		updateEarningsChart(days);
	});

	// Botón de aplicar rango personalizado
	$(document).on('click', '#sorteo-apply-range', function(e) {
		e.preventDefault();
		const from = $('#sorteo-range-from').val();
		const to = $('#sorteo-range-to').val();
		
		if (!from || !to) {
			alert('Por favor selecciona ambas fechas');
			return;
		}
		
		if (new Date(from) > new Date(to)) {
			alert('La fecha de inicio debe ser anterior a la fecha de fin');
			return;
		}
		
		$('.sorteo-chart-range-btn').removeClass('active');
		updateEarningsChartRange(from, to);
	});
});
