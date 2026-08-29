/**
 * Nestform dashboard Chart.js (Free + Pro series).
 */
(function () {
	'use strict';

	var ChartLib = window.Chart;
	var i18n = (window.nestformDashChart && window.nestformDashChart.i18n) || {};
	var ACCENT = '#2563eb';
	var ACCENT_SOFT = 'rgba(37, 99, 235, 0.16)';
	var GRID = 'rgba(15, 23, 42, 0.08)';
	var TICK = '#64748b';

	var LOCALE = 'en-US';

	var state = {
		wrap: null,
		canvas: null,
		chart: null,
		active: 'submissions',
		series: {},
	};

	function parseJson(node) {
		if (!node) {
			return null;
		}
		try {
			return JSON.parse(node.textContent || '{}');
		} catch (e) {
			return null;
		}
	}

	function formatDate(iso) {
		if (!iso) {
			return '';
		}
		try {
			var d = new Date(iso + 'T12:00:00');
			return d.toLocaleDateString(LOCALE, { day: 'numeric', month: 'short' });
		} catch (e) {
			return iso;
		}
	}

	function formatValue(value, unit) {
		var n = Number(value);
		if (unit === 'percent') {
			return (
				(Math.round(n * 10) / 10).toLocaleString(LOCALE, {
					minimumFractionDigits: n % 1 ? 1 : 0,
					maximumFractionDigits: 1,
				}) + '%'
			);
		}
		if (Math.abs(n - Math.round(n)) < 0.001) {
			return Math.round(n).toLocaleString(LOCALE);
		}
		return n.toLocaleString(LOCALE, { maximumFractionDigits: 1 });
	}

	function seriesLabel(key, data) {
		if (data && data.label) {
			return data.label;
		}
		return i18n[key] || key;
	}

	function activeSeries() {
		return state.series[state.active] || null;
	}

	function seriesToChartData(data) {
		var labels = [];
		var values = [];
		if (data && Array.isArray(data.labels) && Array.isArray(data.values)) {
			labels = data.labels.slice();
			values = data.values.map(function (v) {
				return Number(v) || 0;
			});
		} else if (data && Array.isArray(data.dots)) {
			data.dots.forEach(function (dot) {
				labels.push(dot.date);
				values.push(Number(dot.value) || 0);
			});
		}
		return { labels: labels, values: values };
	}

	function updateLegend(data) {
		if (!state.wrap || !data) {
			return;
		}
		var labelEl = state.wrap.querySelector('[data-nestform-chart-legend-label]');
		var peakEl = state.wrap.querySelector('[data-nestform-chart-peak]');
		var avgEl = state.wrap.querySelector('[data-nestform-chart-avg]');
		if (labelEl) {
			labelEl.textContent = seriesLabel(state.active, data);
		}
		if (peakEl) {
			var peakDate = formatDate(data.peak_day || '');
			peakEl.textContent =
				'Peak ' + (data.peak != null ? data.peak : '—') + (peakDate ? ' · ' + peakDate : '');
		}
		if (avgEl && data.avg != null) {
			avgEl.textContent = 'Avg ' + data.avg + ' / day';
		}
	}

	function buildChart(data) {
		if (!ChartLib || !state.canvas || !data) {
			return;
		}
		var packed = seriesToChartData(data);
		var unit = data.unit || 'count';
		var label = seriesLabel(state.active, data);

		if (state.chart) {
			state.chart.destroy();
			state.chart = null;
		}

		state.chart = new ChartLib(state.canvas.getContext('2d'), {
			type: 'line',
			data: {
				labels: packed.labels,
				datasets: [
					{
						label: label,
						data: packed.values,
						borderColor: ACCENT,
						backgroundColor: ACCENT_SOFT,
						borderWidth: 2,
						fill: true,
						tension: 0.35,
						clip: false,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHitRadius: 12,
						pointBackgroundColor: '#fff',
						pointBorderColor: ACCENT,
						pointBorderWidth: 2,
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				layout: {
					padding: {
						top: 18,
						right: 10,
						bottom: 6,
						left: 4,
					},
				},
				interaction: {
					mode: 'index',
					intersect: false,
				},
				plugins: {
					legend: { display: false },
					tooltip: {
						backgroundColor: '#0f172a',
						titleColor: '#f8fafc',
						bodyColor: '#e2e8f0',
						borderColor: 'rgba(148, 163, 184, 0.35)',
						borderWidth: 1,
						padding: 10,
						displayColors: false,
						caretPadding: 10,
						caretSize: 6,
						callbacks: {
							title: function (items) {
								if (!items.length) {
									return '';
								}
								return formatDate(String(items[0].label || ''));
							},
							label: function (item) {
								return formatValue(item.parsed.y, unit) + ' · ' + label;
							},
						},
					},
				},
				scales: {
					x: {
						grid: { display: false },
						border: { display: false },
						ticks: {
							color: TICK,
							padding: 10,
							maxRotation: 0,
							autoSkip: true,
							maxTicksLimit: packed.labels.length > 40 ? 6 : packed.labels.length > 14 ? 7 : 5,
							callback: function (value) {
								var raw = this.getLabelForValue(value);
								return formatDate(String(raw || ''));
							},
						},
					},
					y: {
						beginAtZero: true,
						grace: '12%',
						border: { display: false },
						grid: {
							color: GRID,
							drawTicks: false,
						},
						ticks: {
							color: TICK,
							padding: 8,
							precision: unit === 'percent' ? 1 : 0,
							maxTicksLimit: 5,
							callback: function (value) {
								if (unit === 'percent') {
									return formatValue(value, 'percent');
								}
								return Number(value).toLocaleString();
							},
						},
					},
				},
			},
		});
	}

	function applyMetric(key) {
		if (!state.series[key]) {
			return;
		}
		state.active = key;
		updateLegend(state.series[key]);
		buildChart(state.series[key]);
	}

	function boot() {
		state.wrap = document.querySelector('[data-nestform-chart-wrap]');
		if (!state.wrap || !ChartLib) {
			return;
		}
		state.canvas = state.wrap.querySelector('[data-nestform-chart-canvas]');

		var free = parseJson(state.wrap.querySelector('[data-nestform-chart]'));
		var pro = parseJson(state.wrap.querySelector('[data-nestform-pro-chart]'));
		if (pro && typeof pro === 'object') {
			state.series = pro;
			state.active = 'submissions';
			if (
				state.series.submissions &&
				!(state.series.submissions.total > 0) &&
				state.series.views &&
				state.series.views.total > 0
			) {
				state.active = 'views';
			}
		} else if (free && free.series) {
			state.series = free.series;
			state.active = free.active || 'submissions';
		}

		if (!activeSeries()) {
			return;
		}
		updateLegend(activeSeries());
		buildChart(activeSeries());
	}

	window.nestformDashChartApi = {
		applyMetric: applyMetric,
		setActive: applyMetric,
		getSeries: function () {
			return state.series;
		},
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
