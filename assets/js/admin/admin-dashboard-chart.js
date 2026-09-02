/**
 * Nestform dashboard Chart.js (Free + Pro series).
 */
(function () {
	'use strict';

	var ChartLib = window.Chart;
	var i18n = (window.nestformDashChart && window.nestformDashChart.i18n) || {};
	var ACCENT = '#2563eb';

	var LOCALE = 'en-US';

	function isDarkAdmin() {
		var body = document.body;
		if (!body) {
			return false;
		}
		var theme = body.getAttribute('data-nestform-theme');
		if (theme === 'dark') {
			return true;
		}
		if (theme === 'light') {
			return false;
		}
		return (
			window.matchMedia &&
			window.matchMedia('(prefers-color-scheme: dark)').matches
		);
	}

	function chartColors() {
		var dark = isDarkAdmin();
		return {
			accentSoft: dark ? 'rgba(59, 130, 246, 0.22)' : 'rgba(37, 99, 235, 0.16)',
			grid: dark ? 'rgba(148, 163, 184, 0.14)' : 'rgba(15, 23, 42, 0.08)',
			tick: dark ? '#9aa3b5' : '#64748b',
			pointBg: dark ? '#161921' : '#ffffff',
			tooltipBg: dark ? '#1c2030' : '#0f172a',
			tooltipTitle: dark ? '#eef0f5' : '#f8fafc',
			tooltipBody: dark ? '#c5cdd8' : '#e2e8f0',
			tooltipBorder: dark ? 'rgba(148, 163, 184, 0.28)' : 'rgba(148, 163, 184, 0.35)',
		};
	}

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
		var colors = chartColors();
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
						backgroundColor: colors.accentSoft,
						borderWidth: 2,
						fill: true,
						tension: 0.35,
						clip: false,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHitRadius: 12,
						pointBackgroundColor: colors.pointBg,
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
						backgroundColor: colors.tooltipBg,
						titleColor: colors.tooltipTitle,
						bodyColor: colors.tooltipBody,
						borderColor: colors.tooltipBorder,
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
							color: colors.tick,
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
							color: colors.grid,
							drawTicks: false,
						},
						ticks: {
							color: colors.tick,
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

		if (window.matchMedia) {
			window
				.matchMedia('(prefers-color-scheme: dark)')
				.addEventListener('change', function () {
					var series = activeSeries();
					if (series) {
						buildChart(series);
					}
				});
		}
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
