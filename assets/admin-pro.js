/**
 * Nestform Pro upsells: modal, chart tabs, billing toggle, Freemius overlay checkout.
 */
(function () {
	'use strict';

	var cfg = window.nestformPro || {};
	var i18n = cfg.i18n || {};
	var fsCfg = cfg.fs || {};
	var checkout = null;
	var billingCycle = 'monthly';

	function modal() {
		return document.querySelector('[data-nestform-pro-modal]');
	}

	function setList(items) {
		var list = document.querySelector('[data-nestform-pro-modal-list]');
		if (!list) {
			return;
		}
		list.innerHTML = '';
		(items || []).forEach(function (item) {
			var li = document.createElement('li');
			li.textContent = item;
			list.appendChild(li);
		});
	}

	function openPro(opts) {
		opts = opts || {};
		var el = modal();
		if (!el) {
			if (cfg.url) {
				window.location.href = cfg.url;
			}
			return;
		}
		var title = el.querySelector('[data-nestform-pro-modal-title]');
		var text = el.querySelector('[data-nestform-pro-modal-text]');
		var viz = el.querySelector('[data-nestform-pro-modal-viz]');
		if (title) {
			title.textContent = opts.title || i18n.conversionTitle || '';
		}
		if (text) {
			text.textContent = opts.text || i18n.conversionText || '';
		}
		if (viz) {
			viz.hidden = !opts.viz;
		}
		setList(
			opts.list || [
				'Rating & signature fields',
				'NPS, scale & ranking',
				'Form views & conversion',
				'Lead insights',
			]
		);
		el.hidden = false;
		document.body.classList.add('nestform-pro-modal-open');
	}

	function closePro() {
		var el = modal();
		if (el) {
			el.hidden = true;
		}
		document.body.classList.remove('nestform-pro-modal-open');
	}

	function ensureCheckout() {
		if (checkout) {
			return checkout;
		}
		if (!window.FS || typeof window.FS.Checkout !== 'function') {
			return null;
		}
		var plans = fsCfg.plans || {};
		var defaultPlan = (plans.pro && plans.pro.planId) || 0;
		checkout = new window.FS.Checkout({
			product_id: fsCfg.productId,
			plan_id: defaultPlan || undefined,
			public_key: fsCfg.publicKey,
		});
		return checkout;
	}

	function openCheckoutOverlay(planKey) {
		var plans = fsCfg.plans || {};
		var plan = plans[planKey];
		if (!plan || !plan.planId) {
			return false;
		}

		var instance = ensureCheckout();
		if (!instance) {
			return false;
		}

		var opts = {
			plan_id: plan.planId,
			billing_cycle: billingCycle === 'yearly' ? 'annual' : 'monthly',
			title: plan.title || 'Nestform',
			success: function () {
				closePro();
				if (cfg.accountUrl) {
					window.location.href = cfg.accountUrl;
					return;
				}
				window.location.reload();
			},
		};

		if (plan.pricingId) {
			opts.pricing_id = plan.pricingId;
		}
		if (cfg.userEmail) {
			opts.user_email = cfg.userEmail;
		}

		try {
			instance.open(opts);
			return true;
		} catch (err) {
			return false;
		}
	}

	window.nestformOpenPro = openPro;

	document.addEventListener('click', function (event) {
		if (event.target.closest('[data-nestform-pro-dismiss]')) {
			event.preventDefault();
			closePro();
			return;
		}

		var bill = event.target.closest('[data-nestform-billing] [data-plan]');
		if (bill) {
			event.preventDefault();
			var wrap = bill.closest('[data-nestform-billing]');
			wrap.querySelectorAll('[data-plan]').forEach(function (btn) {
				btn.classList.toggle('is-active', btn === bill);
			});
			var yearly = bill.getAttribute('data-plan') === 'yearly';
			billingCycle = yearly ? 'yearly' : 'monthly';
			var page = document.querySelector('.nestform-upgrade');
			if (page) {
				page.querySelectorAll('[data-nestform-price-monthly]').forEach(function (el) {
					el.hidden = yearly;
				});
				page.querySelectorAll('[data-nestform-price-yearly]').forEach(function (el) {
					el.hidden = !yearly;
				});
				page.querySelectorAll('[data-nestform-billed-yearly]').forEach(function (el) {
					el.hidden = !yearly;
				});
				page.querySelectorAll('[data-nestform-checkout]').forEach(function (link) {
					var href = yearly
						? link.getAttribute('data-nestform-checkout-yearly')
						: link.getAttribute('data-nestform-checkout-monthly');
					if (href) {
						link.setAttribute('href', href);
					}
				});
			}
			return;
		}

		var checkoutLink = event.target.closest('[data-nestform-checkout]');
		if (checkoutLink) {
			var planKey = checkoutLink.getAttribute('data-nestform-checkout') || 'pro';
			if (openCheckoutOverlay(planKey)) {
				event.preventDefault();
				return;
			}
			// Fallback: follow href (in-dashboard Freemius checkout).
		}

		var metric = event.target.closest('[data-nestform-chart-metric]');
		if (metric) {
			event.preventDefault();
			openPro({
				title: i18n.conversionTitle,
				text: i18n.conversionText,
				list: ['Form views', 'Conversion rate', 'Lead insights'],
			});
			return;
		}

		var upsell = event.target.closest('[data-nestform-pro-upsell]');
		if (upsell) {
			event.preventDefault();
			var kind = upsell.getAttribute('data-nestform-pro-upsell') || '';
			if (kind === 'email') {
				openPro({
					title: i18n.emailTitle,
					text: i18n.emailText,
					list: ['HTML templates', 'Logo & media', 'Live preview'],
				});
				return;
			}
			if (kind === 'pdf') {
				openPro({
					title: i18n.pdfTitle,
					text: i18n.pdfText,
					list: ['Download entry PDF', 'Attach PDF to mail'],
				});
				return;
			}
			if (kind === 'quiz_survey' || kind === 'quiz') {
				openPro({
					title: i18n.quizTitle,
					text: i18n.quizText,
					list: [
						'Scoring & result bands',
						'Timer, attempts & resume',
						'Shareable results',
						'NPS & survey charts',
					],
				});
				return;
			}
			if (kind === 'webhook') {
				openPro({
					title: i18n.webhookTitle,
					text: i18n.webhookText,
					list: ['Zapier / Make / n8n', 'Custom HTTPS endpoint', 'Shared secret header'],
				});
				return;
			}
			if (kind === 'automations' || kind === 'automation') {
				openPro({
					title: i18n.autoTitle || 'Automations',
					text: i18n.autoText || 'Run actions when a submission matches a condition.',
					list: [
						'When submitted → if field matches',
						'Set entry status',
						'Alert email & conditional webhook',
					],
				});
				return;
			}
			openPro({
				title: i18n.stepsTitle,
				text: i18n.stepsText,
				viz: true,
				list: ['Multi-step wizard', 'Branch rules', 'Higher completion rate'],
			});
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closePro();
		}
	});

	// Init early so Freemius cart-recovery links can reopen checkout.
	ensureCheckout();

	(function initSidebarToggle() {
		var STORAGE_KEY = 'nestform_sidebar_collapsed';
		var app = document.querySelector('[data-nestform-app]');
		var btn = document.querySelector('[data-nestform-sidebar-toggle]');
		if (!app || !btn) {
			return;
		}

		var labels = (cfg.i18n && cfg.i18n.sidebar) || {};
		var collapseLabel = labels.collapse || 'Collapse';
		var expandLabel = labels.expand || 'Expand';

		function isCollapsed() {
			return app.classList.contains('nestform-app--sidebar-collapsed');
		}

		function applyState(collapsed) {
			app.classList.toggle('nestform-app--sidebar-collapsed', collapsed);
			btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
			btn.setAttribute('title', collapsed ? expandLabel : collapseLabel);
			var text = btn.querySelector('.nestform-app__sidebar-toggle-label');
			if (text) {
				text.textContent = collapsed ? expandLabel : collapseLabel;
			}
			try {
				window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
			} catch (err) {}
		}

		try {
			applyState(window.localStorage.getItem(STORAGE_KEY) === '1');
		} catch (err) {
			applyState(false);
		}

		btn.addEventListener('click', function () {
			applyState(!isCollapsed());
		});
	})();
})();
