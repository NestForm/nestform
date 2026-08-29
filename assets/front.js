/**
 * Nestform front: custom select + AJAX submit + CustomEvents.
 *
 * Events (bubble from <form data-nest-form>, cancelable where noted):
 * - nestform:ready
 * - nestform:before-submit       (cancelable — before client validation)
 * - nestform:validation-error
 * - nestform:submit              (cancelable — after validation + captcha, before fetch; detail.formData is the payload)
 * - nestform:success             (detail.values = submitted fields; fired before form reset)
 * - nestform:error
 * - nestform:network-error
 * - nestform:redirect            (cancelable — stop location change)
 * - nestform:before-step-change  (cancelable — before multi-step index changes)
 * - nestform:step-change
 * - nestform:select-change
 * - nestform:reset
 */
(function () {
	'use strict';

	function formIdOf(form) {
		return parseInt(form.getAttribute('data-form-id') || '0', 10) || 0;
	}

	/**
	 * Plain object snapshot of FormData (multi-value keys become arrays).
	 *
	 * @param {HTMLFormElement} form
	 * @return {Object<string, *>}
	 */
	function formValuesOf(form) {
		var data = new FormData(form);
		var out = {};
		data.forEach(function (value, key) {
			if (Object.prototype.hasOwnProperty.call(out, key)) {
				if (!Array.isArray(out[key])) {
					out[key] = [out[key]];
				}
				out[key].push(value);
			} else {
				out[key] = value;
			}
		});
		return out;
	}

	/**
	 * Client-side guard before location change (server already sanitizes).
	 * Allows relative paths and http(s) only — blocks javascript:/data:.
	 *
	 * @param {string} url
	 * @return {boolean}
	 */
	function isSafeRedirectUrl(url) {
		url = String(url || '').trim();
		if (!url) {
			return false;
		}
		if (url.charAt(0) === '/' && url.charAt(1) !== '/') {
			return true;
		}
		try {
			var parsed = new URL(url, window.location.href);
			return parsed.protocol === 'http:' || parsed.protocol === 'https:';
		} catch (err) {
			return false;
		}
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {string} name
	 * @param {object} [detail]
	 * @param {boolean} [cancelable]
	 * @return {boolean} false if canceled
	 */
	function emit(form, name, detail, cancelable) {
		if (!form) {
			return true;
		}
		detail = detail || {};
		if (detail.formId == null) {
			detail.formId = formIdOf(form);
		}
		detail.form = form;
		return form.dispatchEvent(
			new CustomEvent(name, {
				bubbles: true,
				cancelable: !!cancelable,
				detail: detail,
			})
		);
	}

	function setFieldInvalid(wrap, on) {
		wrap.classList.toggle('nest-form__field--invalid', on);
		wrap.querySelectorAll('.input, .textarea, .select, .nest-form__input, .nest-form-select__trigger').forEach(function (el) {
			el.setAttribute('aria-invalid', on ? 'true' : 'false');
			if (el.classList.contains('textarea') || el.classList.contains('nest-form__textarea')) {
				el.classList.toggle('textarea--error', on);
				el.classList.toggle('input--error', on);
			} else if (el.classList.contains('select') || el.classList.contains('nest-form-select__trigger')) {
				el.classList.toggle('select--error', on);
				el.classList.toggle('input--error', on);
			} else {
				el.classList.toggle('input--error', on);
			}
		});
	}

	function clearErrors(form) {
		form.querySelectorAll('[data-field-name]').forEach(function (wrap) {
			setFieldInvalid(wrap, false);
			var err = wrap.querySelector('[data-nest-form-error]');
			var errId = err ? err.id : '';
			if (err) {
				err.hidden = true;
				err.textContent = '';
			}
			if (errId) {
				wrap.querySelectorAll('[aria-describedby="' + errId + '"]').forEach(function (el) {
					el.removeAttribute('aria-describedby');
				});
			}
		});
	}

	function showFieldErrors(form, errors) {
		var first = null;
		Object.keys(errors || {}).forEach(function (name) {
			var wrap = form.querySelector('[data-field-name="' + name + '"]');
			if (!wrap) {
				return;
			}
			setFieldInvalid(wrap, true);
			var err = wrap.querySelector('[data-nest-form-error]');
			if (err) {
				err.hidden = false;
				err.textContent = errors[name];
				wrap.querySelectorAll('.nest-form__input, .nest-form__textarea, .nest-form__checkbox, .nest-form-select__trigger').forEach(function (el) {
					if (err.id) {
						el.setAttribute('aria-describedby', err.id);
					}
				});
			}
			if (!first) {
				first =
					wrap.querySelector('[data-nest-form-select-trigger]') ||
					wrap.querySelector(
						'.nest-form__input, .nest-form__textarea, .nest-form-select__native, .nest-form__checkbox'
					);
			}
		});
		if (first && typeof first.focus === 'function') {
			first.focus({ preventScroll: false });
		}
	}

	function setStatus(form, message, type) {
		var status = form.querySelector('[data-nest-form-status]');
		if (!status) {
			return;
		}
		status.hidden = !message;
		status.textContent = message || '';
		status.classList.remove('nest-form__status--success', 'nest-form__status--error');
		if (type) {
			status.classList.add('nest-form__status--' + type);
		}
		if (type === 'error') {
			status.setAttribute('role', 'alert');
		} else {
			status.setAttribute('role', 'status');
		}
	}

	function successDisplay(form) {
		var mode = (form.getAttribute('data-success-display') || 'inline').toLowerCase();
		if (mode === 'replace' || mode === 'popup') {
			return mode;
		}
		return 'inline';
	}

	function clearSuccessUi(form) {
		form.classList.remove('nest-form--success-replace');
		var result = form.querySelector('[data-nest-form-result]');
		if (result) {
			result.hidden = true;
			result.textContent = '';
			result.classList.remove('nest-form__result--success');
		}
		var modal = form.querySelector('[data-nest-form-success-modal]');
		if (modal) {
			modal.remove();
		}
	}

	function showSuccessMessage(form, message) {
		var mode = successDisplay(form);
		clearSuccessUi(form);

		if (mode === 'replace') {
			setStatus(form, '', '');
			form.classList.add('nest-form--success-replace');
			var result = form.querySelector('[data-nest-form-result]');
			if (result) {
				result.hidden = false;
				result.textContent = message || '';
				result.classList.add('nest-form__result--success');
				result.setAttribute('role', 'status');
				result.setAttribute('tabindex', '-1');
				if (typeof result.focus === 'function') {
					result.focus({ preventScroll: false });
				}
			} else {
				setStatus(form, message, 'success');
			}
			return;
		}

		if (mode === 'popup') {
			setStatus(form, '', '');
			var i18n = (window.nestform && window.nestform.i18n) || {};
			var overlay = document.createElement('div');
			overlay.className = 'nest-form-success-modal';
			overlay.setAttribute('data-nest-form-success-modal', '');
			overlay.setAttribute('role', 'dialog');
			overlay.setAttribute('aria-modal', 'true');
			overlay.setAttribute('aria-label', i18n.successTitle || 'Thank you');

			var dialog = document.createElement('div');
			dialog.className = 'nest-form-success-modal__dialog';

			var title = document.createElement('p');
			title.className = 'nest-form-success-modal__title';
			title.textContent = i18n.successTitle || 'Thank you';

			var body = document.createElement('p');
			body.className = 'nest-form-success-modal__body';
			body.textContent = message || '';

			var closeBtn = document.createElement('button');
			closeBtn.type = 'button';
			closeBtn.className = 'button button--primary nest-form-success-modal__close';
			closeBtn.textContent = i18n.close || 'Close';

			function closeModal() {
				document.removeEventListener('keydown', onKey);
				if (overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
			}

			function onKey(event) {
				if (event.key === 'Escape') {
					closeModal();
				}
			}

			closeBtn.addEventListener('click', closeModal);
			overlay.addEventListener('click', function (event) {
				if (event.target === overlay) {
					closeModal();
				}
			});
			document.addEventListener('keydown', onKey);

			dialog.appendChild(title);
			dialog.appendChild(body);
			dialog.appendChild(closeBtn);
			overlay.appendChild(dialog);
			form.appendChild(overlay);
			closeBtn.focus({ preventScroll: false });
			return;
		}

		setStatus(form, message, 'success');
	}

	function formMsg(form, key, fallback) {
		if (!form) {
			return fallback || '';
		}
		var map = {
			required: 'data-msg-required',
			invalid_email: 'data-msg-invalid-email',
			invalid_tel: 'data-msg-invalid-tel',
			invalid_url: 'data-msg-invalid-url',
			invalid_number: 'data-msg-invalid-number',
			invalid_date: 'data-msg-invalid-date',
			invalid_time: 'data-msg-invalid-time',
			invalid_file: 'data-msg-invalid-file',
			file_too_large: 'data-msg-file-too-large',
			too_many_files: 'data-msg-too-many-files',
			error_generic: 'data-error-generic',
		};
		var attr = map[key];
		var val = attr ? form.getAttribute(attr) : '';
		return (val && String(val).trim()) || fallback || '';
	}

	function clientHints(form, opts) {
		opts = opts || {};
		var stepOnly = opts.step != null ? String(opts.step) : null;
		var errors = {};
		form.querySelectorAll('[data-field-name]').forEach(function (wrap) {
			if (wrap.hasAttribute('data-nest-form-layout')) {
				return;
			}
			if (wrap.classList.contains('nest-form__field--condition-hidden')) {
				return;
			}
			var name = wrap.getAttribute('data-field-name');
			if (!name) {
				return;
			}
			if (stepOnly !== null) {
				var fieldStep = wrap.getAttribute('data-field-step') || '1';
				if (fieldStep !== stepOnly) {
					return;
				}
			}

			var custom = wrap.querySelector('[data-nest-form-select]');
			if (custom) {
				var native = custom.querySelector('[data-nest-form-select-native]');
				if (native && native.required && !String(native.value || '').trim()) {
					errors[name] = formMsg(form, 'required', 'This field is required.');
				}
				return;
			}

			var choices = wrap.querySelector('[data-nest-form-choices]');
			if (choices) {
				if (choices.getAttribute('data-required') === '1') {
					var anyChecked = !!choices.querySelector('input:checked');
					if (!anyChecked) {
						errors[name] = formMsg(form, 'required', 'This field is required.');
					}
				}
				return;
			}

			var input = wrap.querySelector(
				'.nest-form__input, .nest-form__textarea, .nest-form__checkbox, .nest-form__file, .nest-form__range'
			);
			if (!input || input.tagName === 'BUTTON') {
				return;
			}

			var msg = validateControl(form, input);
			if (msg) {
				errors[name] = msg;
			}
		});
		return errors;
	}

	/* ---------- Conditional show/hide ---------- */

	function readWatchValue(form, name) {
		var wrap = form.querySelector('[data-field-name="' + name + '"]');
		if (!wrap) {
			var loose = form.elements[name];
			if (!loose) {
				return '';
			}
			if (loose instanceof RadioNodeList || (loose.length && loose[0] && loose[0].name === name)) {
				var picked = '';
				Array.prototype.forEach.call(loose, function (el) {
					if (el.checked) {
						picked = el.value;
					}
				});
				return String(picked || '');
			}
			if (loose.type === 'checkbox') {
				return loose.checked ? '1' : '';
			}
			return String(loose.value || '').trim();
		}

		var checks = wrap.querySelectorAll('input[type="checkbox"]');
		if (checks.length > 1) {
			var parts = [];
			checks.forEach(function (el) {
				if (el.checked) {
					parts.push(el.value || '1');
				}
			});
			return parts.join(', ');
		}
		if (checks.length === 1) {
			return checks[0].checked ? '1' : '';
		}

		var radios = wrap.querySelectorAll('input[type="radio"]');
		if (radios.length) {
			var radioVal = '';
			radios.forEach(function (el) {
				if (el.checked) {
					radioVal = el.value;
				}
			});
			return String(radioVal || '');
		}

		var phoneVal = wrap.querySelector('[data-nestform-phone-value]');
		if (phoneVal) {
			return String(phoneVal.value || '').trim();
		}

		var select = wrap.querySelector('select');
		if (select) {
			return String(select.value || '').trim();
		}

		var input = wrap.querySelector(
			'.nest-form__input, .nest-form__textarea, .nest-form__range, input, textarea'
		);
		if (!input) {
			return '';
		}
		return String(input.value || '').trim();
	}

	function conditionPasses(wrap, form) {
		var watch = wrap.getAttribute('data-condition-field');
		if (!watch) {
			return true;
		}
		var op = wrap.getAttribute('data-condition-op') || 'equals';
		var want = wrap.getAttribute('data-condition-value') || '';
		var value = readWatchValue(form, watch);

		if (op === 'empty') {
			return value === '';
		}
		if (op === 'not_empty') {
			return value !== '';
		}
		if (op === 'not_equals') {
			return value.toLowerCase() !== String(want).toLowerCase();
		}
		if (op === 'contains') {
			if (!want) {
				return true;
			}
			return value.toLowerCase().indexOf(String(want).toLowerCase()) !== -1;
		}
		return value.toLowerCase() === String(want).toLowerCase();
	}

	function setConditionRequired(wrap, enabled) {
		wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
			if (!el.hasAttribute('data-nestform-was-required') && el.required) {
				el.setAttribute('data-nestform-was-required', '1');
			}
			if (el.hasAttribute('data-nestform-was-required')) {
				el.required = !!enabled;
			}
		});
		var choices = wrap.querySelector('[data-nest-form-choices]');
		if (choices && choices.hasAttribute('data-required')) {
			if (!choices.hasAttribute('data-nestform-was-req-flag')) {
				choices.setAttribute('data-nestform-was-req-flag', choices.getAttribute('data-required') || '0');
			}
			choices.setAttribute('data-required', enabled ? choices.getAttribute('data-nestform-was-req-flag') || '0' : '0');
		}
	}

	function applyConditions(form) {
		form.querySelectorAll('[data-condition-field]').forEach(function (wrap) {
			var show = conditionPasses(wrap, form);
			wrap.classList.toggle('nest-form__field--condition-hidden', !show);
			wrap.setAttribute('aria-hidden', show ? 'false' : 'true');
			setConditionRequired(wrap, show);
		});
	}

	function initConditions(form) {
		if (!form.querySelector('[data-condition-field]')) {
			return;
		}
		applyConditions(form);
		form.addEventListener('change', function () {
			applyConditions(form);
		});
		form.addEventListener('input', function () {
			applyConditions(form);
		});
		form.addEventListener('nestform:select-change', function () {
			applyConditions(form);
		});
		form.addEventListener('nestform:step-change', function () {
			applyConditions(form);
		});
	}

	/**
	 * Manual validation — do not rely on checkValidity() alone:
	 * fields inside [hidden] step panels are barred from constraint validation.
	 * Messages come from form settings (not browser locale).
	 */
	function validateControl(form, input) {
		var type = String(input.type || input.tagName || '').toLowerCase();
		var required = !!input.required;
		var raw = input.value == null ? '' : String(input.value);
		var val = raw.trim();

		if (type === 'checkbox') {
			if (required && !input.checked) {
				return formMsg(form, 'required', 'This field is required.');
			}
			return '';
		}

		if (required && val === '') {
			return formMsg(form, 'required', 'This field is required.');
		}
		if (val === '') {
			return '';
		}

		if (type === 'email') {
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || /\s/.test(val)) {
				return formMsg(form, 'invalid_email', 'Please enter a valid email address.');
			}
		} else if (type === 'url') {
			if (!/^https?:\/\/\S+/i.test(val)) {
				return formMsg(form, 'invalid_url', 'Please enter a valid URL.');
			}
		} else if (type === 'number') {
			if (isNaN(Number(val))) {
				return formMsg(form, 'invalid_number', 'Please enter a valid number.');
			}
		} else if (type === 'date') {
			if (!/^\d{4}-\d{2}-\d{2}$/.test(val)) {
				return formMsg(form, 'invalid_date', 'Please enter a valid date.');
			}
		} else if (type === 'time') {
			if (!/^\d{2}:\d{2}(:\d{2})?$/.test(val)) {
				return formMsg(form, 'invalid_time', 'Please enter a valid time.');
			}
		} else if (type === 'tel') {
			var digits = val.replace(/\D+/g, '');
			if (digits.length < 7 || digits.length > 15) {
				return formMsg(form, 'invalid_tel', 'Please enter a valid phone number.');
			}
		} else if (type === 'file') {
			var files = input.files;
			if (required && (!files || !files.length)) {
				return formMsg(form, 'required', 'This field is required.');
			}
			if (files && files.length) {
				var maxFiles = parseInt(input.getAttribute('data-max-files') || '1', 10) || 1;
				if (files.length > maxFiles) {
					return formMsg(form, 'too_many_files', 'Too many files selected.');
				}
				var maxMb = parseFloat(input.getAttribute('data-max-mb') || '10', 10);
				for (var fi = 0; fi < files.length; fi++) {
					if (maxMb > 0 && files[fi].size > maxMb * 1024 * 1024) {
						return formMsg(form, 'file_too_large', 'File is too large.');
					}
				}
			}
		} else if (typeof input.checkValidity === 'function' && input.willValidate && !input.checkValidity()) {
			return formMsg(form, 'error_generic', 'Something went wrong. Please try again.');
		}

		return '';
	}

	/* ---------- Multi-step ---------- */

	function getSteps(form) {
		try {
			var raw = form.getAttribute('data-steps');
			var steps = raw ? JSON.parse(raw) : [];
			return Array.isArray(steps) ? steps.map(Number) : [];
		} catch (e) {
			return [];
		}
	}

	function getStepIndex(form) {
		return parseInt(form.getAttribute('data-step-index') || '0', 10) || 0;
	}

	function setStepIndex(form, index, meta) {
		var steps = getSteps(form);
		if (!steps.length) {
			return false;
		}
		meta = meta || {};
		var previousIndex = getStepIndex(form);
		index = Math.max(0, Math.min(index, steps.length - 1));
		var willChange = previousIndex !== index;

		if (willChange && !meta.force) {
			if (
				!emit(
					form,
					'nestform:before-step-change',
					{
						index: index,
						previousIndex: previousIndex,
						step: steps[index],
						previousStep: steps[previousIndex],
						total: steps.length,
						reason: meta.reason || 'set',
					},
					true
				)
			) {
				return false;
			}
		}

		form.setAttribute('data-step-index', String(index));
		var current = String(steps[index]);

		// Prefer step panels; fall back to per-field hiding.
		var panels = form.querySelectorAll('[data-nest-form-step-panel]');
		if (panels.length) {
			panels.forEach(function (panel) {
				var match = String(panel.getAttribute('data-step') || '') === current;
				panel.hidden = !match;
				panel.classList.toggle('is-active', match);
				panel.classList.toggle('is-step-hidden', !match);
			});
		} else {
			form.querySelectorAll('[data-field-name][data-field-step]').forEach(function (el) {
				var step = String(el.getAttribute('data-field-step') || '1');
				var match = step === current;
				el.hidden = !match;
				el.classList.toggle('is-step-hidden', !match);
			});
		}

		var prev = form.querySelector('[data-nest-form-prev]');
		var next = form.querySelector('[data-nest-form-next]');
		var submit = form.querySelector('[data-nest-form-submit]');
		var captcha = form.querySelector('[data-nest-form-captcha-wrap]');
		var isFirst = index === 0;
		var isLast = index === steps.length - 1;

		if (prev) {
			prev.hidden = isFirst;
		}
		if (next) {
			next.hidden = isLast;
		}
		if (submit) {
			submit.hidden = !isLast;
		}
		if (captcha) {
			captcha.hidden = !isLast;
		}

		var fill = form.querySelector('[data-nest-form-progress-fill]');
		if (fill) {
			fill.style.width = ((index + 1) / steps.length) * 100 + '%';
		}
		form.querySelectorAll('[data-nest-form-progress-step]').forEach(function (li, i) {
			li.classList.toggle('is-active', i === index);
			li.classList.toggle('is-done', i < index);
		});

		if (willChange || meta.force) {
			emit(form, 'nestform:step-change', {
				index: index,
				previousIndex: previousIndex,
				step: steps[index],
				previousStep: steps[previousIndex],
				total: steps.length,
				reason: meta.reason || 'set',
			});
			updateVisitedSteps(form, steps[index]);
			window.setTimeout(function () {
				focusStepStart(form);
			}, 0);
		}
		return true;
	}

	function updateVisitedSteps(form, step) {
		var input = form.querySelector('[data-nest-form-visited-steps]');
		if (!input) {
			return;
		}
		var map = {};
		String(input.value || '')
			.split(',')
			.forEach(function (part) {
				var n = parseInt(part, 10);
				if (n > 0) {
					map[n] = true;
				}
			});
		map[parseInt(step, 10) || 1] = true;
		input.value = Object.keys(map)
			.map(Number)
			.sort(function (a, b) {
				return a - b;
			})
			.join(',');
	}

	function focusStepStart(form) {
		var panel = form.querySelector('[data-nest-form-step-panel].is-active') || form;
		var title = panel.querySelector('.nest-form__step-title');
		if (title) {
			if (!title.hasAttribute('tabindex')) {
				title.setAttribute('tabindex', '-1');
			}
			title.focus({ preventScroll: false });
			return;
		}
		var first = panel.querySelector(
			'.nest-form__input, .nest-form__textarea, .nest-form-select__trigger, .nest-form__checkbox'
		);
		if (first && typeof first.focus === 'function') {
			first.focus({ preventScroll: false });
		}
	}

	function getBranchRules(form) {
		try {
			var raw = form.getAttribute('data-branch-rules');
			var rules = raw ? JSON.parse(raw) : [];
			return Array.isArray(rules) ? rules : [];
		} catch (e) {
			return [];
		}
	}

	function resolveBranchTarget(form, fromStep) {
		var rules = getBranchRules(form);
		var probe = document.createElement('div');
		for (var i = 0; i < rules.length; i++) {
			var rule = rules[i];
			if (Number(rule.from) !== Number(fromStep)) {
				continue;
			}
			probe.setAttribute('data-condition-field', rule.field || '');
			probe.setAttribute('data-condition-op', rule.op || 'equals');
			probe.setAttribute('data-condition-value', rule.value || '');
			if (conditionPasses(probe, form)) {
				return Number(rule.to);
			}
		}
		return null;
	}

	function stepIndexOf(form, stepNum) {
		var steps = getSteps(form);
		for (var i = 0; i < steps.length; i++) {
			if (Number(steps[i]) === Number(stepNum)) {
				return i;
			}
		}
		return -1;
	}

	function initSteps(form) {
		if (!form.hasAttribute('data-nest-form-steps')) {
			return;
		}
		form._liteStepHistory = [];
		setStepIndex(form, getStepIndex(form), { reason: 'init', force: true });

		var next = form.querySelector('[data-nest-form-next]');
		var prev = form.querySelector('[data-nest-form-prev]');

		if (next) {
			next.addEventListener('click', function () {
				clearErrors(form);
				setStatus(form, '', '');
				var steps = getSteps(form);
				var index = getStepIndex(form);
				var current = steps[index];
				var hints = clientHints(form, { step: current });
				if (Object.keys(hints).length) {
					showFieldErrors(form, hints);
					var msg =
						form.getAttribute('data-error-generic') || 'Please check the highlighted fields.';
					setStatus(form, msg, 'error');
					emit(form, 'nestform:validation-error', {
						errors: hints,
						message: msg,
						source: 'step-next',
						step: current,
						stepIndex: index,
					});
					return;
				}
				var targetStep = resolveBranchTarget(form, current);
				var targetIndex = targetStep ? stepIndexOf(form, targetStep) : -1;
				form._liteStepHistory = form._liteStepHistory || [];
				var fromIndex = index;
				var moved = false;
				if (targetIndex >= 0 && targetIndex !== index) {
					moved = setStepIndex(form, targetIndex, { reason: 'branch' });
				} else {
					moved = setStepIndex(form, index + 1, { reason: 'next' });
				}
				if (moved) {
					form._liteStepHistory.push(fromIndex);
				}
			});
		}

		if (prev) {
			prev.addEventListener('click', function () {
				clearErrors(form);
				setStatus(form, '', '');
				var history = form._liteStepHistory || [];
				if (history.length) {
					setStepIndex(form, history.pop(), { reason: 'prev' });
					return;
				}
				setStepIndex(form, getStepIndex(form) - 1, { reason: 'prev' });
			});
		}
	}

	/* ---------- Custom select ---------- */

	function closeSelect(root) {
		if (!root) {
			return;
		}
		root.classList.remove('is-open');
		var trigger = root.querySelector('[data-nest-form-select-trigger]');
		var list = root.querySelector('[data-nest-form-select-list]');
		if (trigger) {
			trigger.setAttribute('aria-expanded', 'false');
			trigger.removeAttribute('aria-activedescendant');
		}
		if (list) {
			list.hidden = true;
		}
		root.querySelectorAll('.nest-form-select__option.is-active').forEach(function (opt) {
			opt.classList.remove('is-active');
		});
	}

	function closeAll(except) {
		document.querySelectorAll('[data-nest-form-select].is-open').forEach(function (root) {
			if (root !== except) {
				closeSelect(root);
			}
		});
	}

	function syncFromNative(root) {
		var native = root.querySelector('[data-nest-form-select-native]');
		var valueEl = root.querySelector('[data-nest-form-select-value]');
		var list = root.querySelector('[data-nest-form-select-list]');
		if (!native || !valueEl) {
			return;
		}
		var value = native.value;
		var placeholder = valueEl.getAttribute('data-placeholder') || '';
		if (!value) {
			valueEl.textContent = placeholder;
			valueEl.classList.add('is-placeholder');
		} else {
			valueEl.textContent = value;
			valueEl.classList.remove('is-placeholder');
		}
		if (list) {
			list.querySelectorAll('[role="option"]').forEach(function (opt) {
				var selected = opt.getAttribute('data-value') === value;
				opt.setAttribute('aria-selected', selected ? 'true' : 'false');
			});
		}
	}

	function setValue(root, value, focusTrigger) {
		var native = root.querySelector('[data-nest-form-select-native]');
		if (!native) {
			return;
		}
		var previous = native.value;
		native.value = value;
		native.dispatchEvent(new Event('change', { bubbles: true }));
		syncFromNative(root);
		closeSelect(root);
		if (focusTrigger) {
			var trigger = root.querySelector('[data-nest-form-select-trigger]');
			if (trigger) {
				trigger.focus();
			}
		}
		var form = root.closest('[data-nest-form]');
		if (form && previous !== value) {
			var wrap = root.closest('[data-field-name]');
			emit(form, 'nestform:select-change', {
				name: wrap ? wrap.getAttribute('data-field-name') || '' : '',
				value: value,
				previous: previous,
				select: root,
			});
		}
	}

	function openSelect(root) {
		closeAll(root);
		var trigger = root.querySelector('[data-nest-form-select-trigger]');
		var list = root.querySelector('[data-nest-form-select-list]');
		if (!trigger || !list) {
			return;
		}
		root.classList.add('is-open');
		trigger.setAttribute('aria-expanded', 'true');
		list.hidden = false;

		var selected = list.querySelector('[aria-selected="true"]');
		var first = list.querySelector('[role="option"]');
		var active = selected || first;
		if (active) {
			list.querySelectorAll('.is-active').forEach(function (el) {
				el.classList.remove('is-active');
			});
			active.classList.add('is-active');
			trigger.setAttribute('aria-activedescendant', active.id || '');
			active.focus();
		}
	}

	function moveActive(root, delta) {
		var list = root.querySelector('[data-nest-form-select-list]');
		var trigger = root.querySelector('[data-nest-form-select-trigger]');
		if (!list) {
			return;
		}
		var options = Array.prototype.slice.call(list.querySelectorAll('[role="option"]'));
		if (!options.length) {
			return;
		}
		var index = options.findIndex(function (opt) {
			return opt.classList.contains('is-active');
		});
		if (index < 0) {
			index = options.findIndex(function (opt) {
				return opt.getAttribute('aria-selected') === 'true';
			});
		}
		var next = options[(index + delta + options.length) % options.length];
		options.forEach(function (opt) {
			opt.classList.remove('is-active');
		});
		next.classList.add('is-active');
		if (trigger) {
			trigger.setAttribute('aria-activedescendant', next.id || '');
		}
		next.focus();
	}

	function initSelect(root) {
		if (root.dataset.liteFormSelectReady) {
			return;
		}
		root.dataset.liteFormSelectReady = '1';
		syncFromNative(root);

		var trigger = root.querySelector('[data-nest-form-select-trigger]');
		var list = root.querySelector('[data-nest-form-select-list]');
		var native = root.querySelector('[data-nest-form-select-native]');
		if (!trigger || !list || !native) {
			return;
		}

		trigger.addEventListener('click', function () {
			if (root.classList.contains('is-open')) {
				closeSelect(root);
			} else {
				openSelect(root);
			}
		});

		trigger.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				if (!root.classList.contains('is-open')) {
					openSelect(root);
				} else if (event.key === 'ArrowDown') {
					moveActive(root, 1);
				}
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				if (!root.classList.contains('is-open')) {
					openSelect(root);
				} else {
					moveActive(root, -1);
				}
			} else if (event.key === 'Escape') {
				closeSelect(root);
			}
		});

		list.addEventListener('click', function (event) {
			var opt = event.target.closest('[role="option"]');
			if (!opt || !list.contains(opt)) {
				return;
			}
			setValue(root, opt.getAttribute('data-value') || '', true);
		});

		list.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown') {
				event.preventDefault();
				moveActive(root, 1);
			} else if (event.key === 'ArrowUp') {
				event.preventDefault();
				moveActive(root, -1);
			} else if (event.key === 'Enter' || event.key === ' ') {
				event.preventDefault();
				var active = list.querySelector('.is-active') || list.querySelector('[aria-selected="true"]');
				if (active) {
					setValue(root, active.getAttribute('data-value') || '', true);
				}
			} else if (event.key === 'Escape') {
				event.preventDefault();
				closeSelect(root);
				trigger.focus();
			} else if (event.key === 'Tab') {
				closeSelect(root);
			}
		});

		native.addEventListener('change', function () {
			syncFromNative(root);
		});
	}

	function initAllSelects(scope) {
		(scope || document).querySelectorAll('[data-nest-form-select]').forEach(initSelect);
	}

	function closePhonePanels(except) {
		document.querySelectorAll('[data-nestform-phone]').forEach(function (root) {
			if (except && root === except) {
				return;
			}
			var panel = root.querySelector('[data-nestform-phone-panel]');
			var toggle = root.querySelector('[data-nestform-phone-toggle]');
			if (panel) {
				panel.hidden = true;
			}
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			root.classList.remove('is-open');
		});
	}

	function syncPhone(root) {
		if (!root) {
			return;
		}
		var iso = root.getAttribute('data-iso') || '';
		var dial = root.getAttribute('data-dial') || '';
		var nationalEl = root.querySelector('[data-nestform-phone-national]');
		var valueEl = root.querySelector('[data-nestform-phone-value]');
		var isoEl = root.querySelector('[data-nestform-phone-iso]');
		var digits = nationalEl ? String(nationalEl.value || '').replace(/\D+/g, '') : '';
		var e164 = '';
		if (digits) {
			if (dial && digits.indexOf(dial) === 0) {
				e164 = '+' + digits;
			} else if (dial === '7' && digits.length >= 10 && (digits.charAt(0) === '8' || digits.charAt(0) === '7')) {
				e164 = '+7' + digits.slice(1);
			} else {
				e164 = '+' + dial + digits;
			}
		}
		if (valueEl) {
			valueEl.value = e164;
		}
		if (isoEl) {
			isoEl.value = iso;
		}
	}

	function initPhone(root) {
		if (!root || root.dataset.phoneBound === '1') {
			return;
		}
		root.dataset.phoneBound = '1';
		var toggle = root.querySelector('[data-nestform-phone-toggle]');
		var panel = root.querySelector('[data-nestform-phone-panel]');
		var search = root.querySelector('[data-nestform-phone-search]');
		var national = root.querySelector('[data-nestform-phone-national]');
		var flagEl = root.querySelector('[data-nestform-phone-flag]');
		var dialEl = root.querySelector('[data-nestform-phone-dial]');

		function setCountry(iso, dial, flagUrl) {
			root.setAttribute('data-iso', iso);
			root.setAttribute('data-dial', dial);
			if (flagEl) {
				var img = flagEl.querySelector('.nest-form-phone__flag-img');
				if (!img && flagUrl) {
					img = document.createElement('img');
					img.className = 'nest-form-phone__flag-img';
					img.width = 20;
					img.height = 15;
					img.alt = '';
					flagEl.textContent = '';
					flagEl.appendChild(img);
				}
				if (img && flagUrl) {
					img.src = flagUrl;
				}
			}
			if (dialEl) {
				dialEl.textContent = '+' + dial;
			}
			root.querySelectorAll('.nest-form-phone__opt').forEach(function (btn) {
				btn.classList.toggle('is-active', btn.getAttribute('data-iso') === iso);
			});
			syncPhone(root);
		}

		if (toggle && panel) {
			toggle.addEventListener('click', function (event) {
				event.preventDefault();
				var open = panel.hidden;
				closePhonePanels(open ? root : null);
				panel.hidden = !open;
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				root.classList.toggle('is-open', open);
				if (open && search) {
					search.value = '';
					root.querySelectorAll('.nest-form-phone__opt').forEach(function (btn) {
						btn.parentElement.hidden = false;
					});
					search.focus();
				}
			});
		}

		root.querySelectorAll('.nest-form-phone__opt').forEach(function (btn) {
			btn.addEventListener('click', function () {
				setCountry(
					btn.getAttribute('data-iso') || '',
					btn.getAttribute('data-dial') || '',
					btn.getAttribute('data-flag') || ''
				);
				closePhonePanels();
				if (national) {
					national.focus();
				}
			});
		});

		if (search) {
			search.addEventListener('keydown', function (event) {
				if (event.key === 'Enter') {
					event.preventDefault();
				}
			});
			search.addEventListener('input', function () {
				var q = String(search.value || '').toLowerCase().trim();
				root.querySelectorAll('.nest-form-phone__opt').forEach(function (btn) {
					var hay = btn.getAttribute('data-search') || '';
					var li = btn.parentElement;
					if (li) {
						li.hidden = q !== '' && hay.indexOf(q) === -1;
					}
				});
			});
		}

		if (national) {
			national.addEventListener('input', function () {
				syncPhone(root);
			});
			national.addEventListener('change', function () {
				syncPhone(root);
			});
		}

		syncPhone(root);
	}

	function initAllPhones(scope) {
		(scope || document).querySelectorAll('[data-nestform-phone]').forEach(initPhone);
	}

	function ensureCaptchaToken(form) {
		return new Promise(function (resolve, reject) {
			var box = form.querySelector('[data-nest-form-captcha]');
			if (!box) {
				resolve();
				return;
			}

			var provider =
				(box.getAttribute('data-nest-form-captcha') || '') ||
				(window.nestformCaptcha && window.nestformCaptcha.provider) ||
				'';

			if (provider === 'recaptcha_v3') {
				var input = form.querySelector('[data-nest-form-captcha-token]');
				var cfg = window.nestformCaptcha || {};
				if (!input) {
					reject(new Error('Captcha token field missing'));
					return;
				}
				if (typeof grecaptcha === 'undefined' || !cfg.siteKey) {
					reject(new Error('reCAPTCHA is not loaded'));
					return;
				}
				grecaptcha.ready(function () {
					grecaptcha
						.execute(cfg.siteKey, { action: cfg.action || 'nestform' })
						.then(function (token) {
							input.value = token;
							resolve();
						})
						.catch(reject);
				});
				return;
			}

			// v2 checkbox — token is injected into textarea[name=g-recaptcha-response]
			var tokenField = form.querySelector('[name="g-recaptcha-response"]');
			if (tokenField && !tokenField.value) {
				reject(new Error('Please complete the captcha'));
				return;
			}
			resolve();
		});
	}

	function onSubmit(event) {
		var form = event.target.closest('[data-nest-form]');
		if (!form) {
			return;
		}
		event.preventDefault();
		form.querySelectorAll('[data-nestform-phone]').forEach(syncPhone);

		if (!emit(form, 'nestform:before-submit', {}, true)) {
			return;
		}

		clearErrors(form);
		clearSuccessUi(form);
		setStatus(form, '', '');

		// On multi-step, Enter / implicit submit acts like Next until the last step.
		if (form.hasAttribute('data-nest-form-steps')) {
			var steps = getSteps(form);
			var index = getStepIndex(form);
			if (index < steps.length - 1) {
				var nextBtn = form.querySelector('[data-nest-form-next]');
				if (nextBtn && !nextBtn.hidden) {
					nextBtn.click();
				}
				return;
			}
			var lastHints = clientHints(form, { step: steps[index] });
			if (Object.keys(lastHints).length) {
				showFieldErrors(form, lastHints);
				var lastMsg =
					form.getAttribute('data-error-generic') || 'Please check the highlighted fields.';
				setStatus(form, lastMsg, 'error');
				emit(form, 'nestform:validation-error', {
					errors: lastHints,
					message: lastMsg,
					source: 'submit-step',
					step: steps[index],
					stepIndex: index,
				});
				return;
			}
		}

		var hints = clientHints(form);
		if (Object.keys(hints).length) {
			showFieldErrors(form, hints);
			// Jump to first errored step if multi-step.
			if (form.hasAttribute('data-nest-form-steps')) {
				var firstName = Object.keys(hints)[0];
				var wrap = form.querySelector('[data-field-name="' + firstName + '"]');
				if (wrap) {
					var errStep = parseInt(wrap.getAttribute('data-field-step') || '1', 10);
					var allSteps = getSteps(form);
					var jump = allSteps.indexOf(errStep);
					if (jump >= 0) {
						setStepIndex(form, jump, { reason: 'validation' });
					}
				}
			}
			var hintMsg =
				form.getAttribute('data-error-generic') || 'Please check the highlighted fields.';
			setStatus(form, hintMsg, 'error');
			emit(form, 'nestform:validation-error', {
				errors: hints,
				message: hintMsg,
				source: 'submit',
			});
			return;
		}

		form.classList.add('is-submitting');

		ensureCaptchaToken(form)
			.then(function () {
				var body = new FormData(form);
				if (
					!emit(
						form,
						'nestform:submit',
						{
							formData: body,
						},
						true
					)
				) {
					form.classList.remove('is-submitting');
					return null;
				}

				var ajaxUrl =
					(window.nestform && window.nestform.ajaxUrl) || form.getAttribute('action');

				return fetch(ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: body,
				}).then(function (res) {
					return res.json().then(function (json) {
						return { ok: res.ok, json: json };
					});
				});
			})
			.then(function (result) {
				if (!result) {
					return;
				}
				form.classList.remove('is-submitting');
				var json = result.json || {};
				var data = json.data || {};

				if (json.success) {
					var values = formValuesOf(form);
					var redirect = data.redirect || '';
					emit(form, 'nestform:success', {
						message: data.message || '',
						redirect: redirect,
						entryId: data.entry_id || 0,
						display: successDisplay(form),
						values: values,
						data: data,
					});
					form.reset();
					form.querySelectorAll('[data-nest-form-select]').forEach(syncFromNative);
					var token = form.querySelector('[data-nest-form-captcha-token]');
					if (token) {
						token.value = '';
					}
					if (form.hasAttribute('data-nest-form-steps')) {
						setStepIndex(form, 0, { reason: 'success', force: true });
					}
					// Quiz/pro may render a rich result panel; otherwise show thank-you UI.
					if (!data.result) {
						showSuccessMessage(form, data.message || '');
					}
					if (redirect) {
						var go = function () {
							if (!isSafeRedirectUrl(redirect)) {
								return;
							}
							if (
								emit(
									form,
									'nestform:redirect',
									{
										url: redirect,
										message: data.message || '',
										entryId: data.entry_id || 0,
										values: values,
										data: data,
									},
									true
								)
							) {
								window.location.href = redirect;
							}
						};
						if (data.result) {
							window.setTimeout(go, 2800);
						} else if (successDisplay(form) === 'popup' || successDisplay(form) === 'replace') {
							window.setTimeout(go, 1600);
						} else {
							go();
						}
					}
					return;
				}

				showFieldErrors(form, data.errors || {});
				if (form.hasAttribute('data-nest-form-steps') && data.errors) {
					var errName = Object.keys(data.errors)[0];
					var errWrap = form.querySelector('[data-field-name="' + errName + '"]');
					if (errWrap) {
						var s = parseInt(errWrap.getAttribute('data-field-step') || '1', 10);
						var idx = getSteps(form).indexOf(s);
						if (idx >= 0) {
							setStepIndex(form, idx, { reason: 'server-error' });
						}
					}
				}
				setStatus(form, data.message || 'Error', 'error');
				emit(form, 'nestform:error', {
					message: data.message || '',
					errors: data.errors || {},
					data: data,
				});
			})
			.catch(function (err) {
				form.classList.remove('is-submitting');
				var netMsg = (err && err.message) || 'Network error. Please try again.';
				setStatus(form, netMsg, 'error');
				emit(form, 'nestform:network-error', {
					message: netMsg,
					error: err || null,
				});
			});
	}

	document.addEventListener('submit', onSubmit, true);

	document.addEventListener(
		'click',
		function (event) {
			var link = event.target.closest('.nest-form__check a');
			if (link) {
				// Prevent label from toggling checkbox when opening a policy link.
				event.stopPropagation();
			}
		},
		true
	);

	document.addEventListener('click', function (event) {
		if (!event.target.closest('[data-nest-form-select]')) {
			closeAll();
		}
		if (!event.target.closest('[data-nestform-phone]')) {
			closePhonePanels();
		}
	});

	document.addEventListener('reset', function (event) {
		var form = event.target;
		if (!form || !form.matches || !form.matches('[data-nest-form]')) {
			return;
		}
		window.setTimeout(function () {
			form.querySelectorAll('[data-nest-form-select]').forEach(syncFromNative);
			form.querySelectorAll('[data-nestform-phone]').forEach(syncPhone);
			if (form.hasAttribute('data-nest-form-steps')) {
				setStepIndex(form, 0, { reason: 'reset', force: true });
			}
			emit(form, 'nestform:reset', {});
		}, 0);
	});

	function syncOtherField(wrap) {
		if (!wrap) {
			return;
		}
		var other = wrap.querySelector('[data-nest-form-other]');
		if (!other) {
			return;
		}
		var on = false;
		var native = wrap.querySelector('[data-nest-form-select-native]');
		if (native) {
			on = native.value === '__other';
		} else {
			var checked = wrap.querySelectorAll('input[data-nest-form-other-trigger]:checked, input[value="__other"]:checked');
			on = checked.length > 0;
		}
		other.hidden = !on;
		if (!on) {
			other.value = '';
		}
	}

	function initOtherFields(root) {
		(root || document).querySelectorAll('[data-nest-form-allow-other]').forEach(function (wrap) {
			var scope = wrap.closest('[data-field-name]') || wrap;
			syncOtherField(scope);
			wrap.addEventListener('change', function () {
				syncOtherField(scope);
			});
		});
		document.addEventListener('nestform:select-change', function (event) {
			var form = event.target && event.target.closest ? event.target.closest('[data-nest-form]') : null;
			if (!form) {
				return;
			}
			form.querySelectorAll('[data-field-name]').forEach(syncOtherField);
		});
	}

	function boot() {
		initAllSelects(document);
		initAllPhones(document);
		initOtherFields(document);
		document.querySelectorAll('[data-nest-form]').forEach(function (form) {
			initSteps(form);
			initConditions(form);
			initCalculated(form);
			initRepeaters(form);
			emit(form, 'nestform:ready', {
				steps: getSteps(form),
			});
		});
	}

	function numericValue(raw) {
		var s = String(raw == null ? '' : raw).trim();
		if (s === '' || isNaN(s)) {
			return 0;
		}
		return parseFloat(s);
	}

	function evalFormula(formula, values) {
		var expr = String(formula || '').replace(/\{([a-zA-Z0-9_]+)\}/g, function (_, key) {
			return String(numericValue(values[key]));
		});
		expr = expr.toLowerCase().replace(/\s+/g, '');
		if (!/^[0-9+\-*/().,minaxroud]+$/.test(expr)) {
			return '';
		}
		try {
			/* eslint-disable no-new-func */
			var fn = new Function(
				'min',
				'max',
				'round',
				'"use strict"; return (' +
					expr
						.replace(/min\(/g, 'min(')
						.replace(/max\(/g, 'max(')
						.replace(/round\(/g, 'round(') +
					');'
			);
			/* eslint-enable no-new-func */
			var result = fn(Math.min, Math.max, Math.round);
			if (typeof result !== 'number' || !isFinite(result)) {
				return '';
			}
			return String(Math.round(result * 1e8) / 1e8);
		} catch (err) {
			return '';
		}
	}

	function collectScopeValues(scope) {
		var values = {};
		if (!scope) {
			return values;
		}
		scope.querySelectorAll('input, select, textarea').forEach(function (el) {
			if (!el.name || el.disabled) {
				return;
			}
			var name = el.name;
			var m = name.match(/(?:^|\[)([a-zA-Z0-9_]+)\]?$/);
			var key = m ? m[1] : name;
			if (el.type === 'checkbox') {
				if (el.name.slice(-2) === '[]') {
					return;
				}
				values[key] = el.checked ? 1 : 0;
				return;
			}
			if (el.type === 'radio') {
				if (el.checked) {
					values[key] = el.value;
				}
				return;
			}
			values[key] = el.value;
		});
		return values;
	}

	function recalculateIn(scope) {
		if (!scope) {
			return;
		}
		scope.querySelectorAll('[data-nestform-calculated]').forEach(function (input) {
			var formula = input.getAttribute('data-nestform-formula') || '';
			var row = input.closest('[data-nestform-repeater-row]');
			var values = collectScopeValues(row || scope.closest('[data-nest-form]') || scope);
			// Merge form-level values for top-level calculated fields.
			if (!row) {
				var form = scope.closest('[data-nest-form]') || scope;
				values = Object.assign({}, collectScopeValues(form), values);
			} else {
				var form2 = scope.closest('[data-nest-form]');
				if (form2) {
					values = Object.assign({}, collectScopeValues(form2), values);
				}
			}
			input.value = evalFormula(formula, values);
		});
	}

	function initCalculated(form) {
		if (!form.querySelector('[data-nestform-calculated]')) {
			return;
		}
		var run = function () {
			recalculateIn(form);
		};
		form.addEventListener('input', run);
		form.addEventListener('change', run);
		run();
	}

	function syncRepeaterRemoveButtons(repeater) {
		var rowsWrap = repeater.querySelector('[data-nestform-repeater-rows]');
		if (!rowsWrap) {
			return;
		}
		var list = rowsWrap.querySelectorAll(':scope > [data-nestform-repeater-row]');
		var onlyOne = list.length <= 1;
		list.forEach(function (row) {
			var btn = row.querySelector('[data-nestform-repeater-remove]');
			if (btn) {
				btn.hidden = onlyOne;
			}
		});
	}

	function reindexRepeater(repeater) {
		var rows = repeater.querySelector('[data-nestform-repeater-rows]');
		if (!rows) {
			return;
		}
		var base = repeater.getAttribute('data-nestform-repeater-name') || '';
		rows.querySelectorAll(':scope > [data-nestform-repeater-row]').forEach(function (row, index) {
			row.querySelectorAll('[name]').forEach(function (input) {
				var name = input.nameAttribute('name') || '';
				if (!base || name.indexOf(base + '[') !== 0) {
					return;
				}
				input.name = name.replace(
					new RegExp('^' + base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[\\d+\\]'),
					base + '[' + index + ']'
				);
			});
			row.querySelectorAll('[id]').forEach(function (el) {
				var id = el.id || '';
				el.id = id.replace(/-\d+-/, '-' + index + '-');
			});
			row.querySelectorAll('label[for]').forEach(function (lab) {
				var f = lab.getAttribute('for') || '';
				lab.setAttribute('for', f.replace(/-\d+-/, '-' + index + '-'));
			});
		});
		syncRepeaterRemoveButtons(repeater);
	}

	function initRepeaters(form) {
		form.querySelectorAll('[data-nestform-repeater]').forEach(function (repeater) {
			syncRepeaterRemoveButtons(repeater);
			if (repeater.getAttribute('data-nestform-repeater-bound')) {
				return;
			}
			repeater.setAttribute('data-nestform-repeater-bound', '1');
		});
	}

	document.addEventListener('click', function (event) {
		var addBtn = event.target.closest('[data-nestform-repeater-add]');
		if (addBtn) {
			event.preventDefault();
			var repeater = addBtn.closest('[data-nestform-repeater]');
			if (!repeater) {
				return;
			}
			var rows = repeater.querySelector('[data-nestform-repeater-rows]');
			var tpl = repeater.querySelector('[data-nestform-repeater-template]');
			if (!rows || !tpl) {
				return;
			}
			var index = rows.querySelectorAll(':scope > [data-nestform-repeater-row]').length;
			var html = tpl.innerHTML.replace(/__INDEX__/g, String(index));
			var wrap = document.createElement('div');
			wrap.innerHTML = html.trim();
			var node = wrap.firstElementChild;
			if (node) {
				rows.appendChild(node);
				initRepeaters(node);
				reindexRepeater(repeater);
				var form = repeater.closest('[data-nest-form]');
				if (form) {
					recalculateIn(form);
				}
			}
			return;
		}
		var removeBtn = event.target.closest('[data-nestform-repeater-remove]');
		if (removeBtn) {
			event.preventDefault();
			var row = removeBtn.closest('[data-nestform-repeater-row]');
			var rep = removeBtn.closest('[data-nestform-repeater]');
			if (!row || !rep || removeBtn.hidden) {
				return;
			}
			var list = rep.querySelector('[data-nestform-repeater-rows]');
			if (!list || list.querySelectorAll(':scope > [data-nestform-repeater-row]').length <= 1) {
				return;
			}
			row.remove();
			reindexRepeater(rep);
			var form2 = rep.closest('[data-nest-form]');
			if (form2) {
				recalculateIn(form2);
			}
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.nestformSelect = {
		init: initAllSelects,
		sync: syncFromNative,
		closeAll: closeAll,
	};

	window.nestformEvents = [
		'nestform:ready',
		'nestform:before-submit',
		'nestform:validation-error',
		'nestform:submit',
		'nestform:success',
		'nestform:error',
		'nestform:network-error',
		'nestform:redirect',
		'nestform:before-step-change',
		'nestform:step-change',
		'nestform:select-change',
		'nestform:reset',
	];
})();
