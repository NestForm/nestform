/**
 * Nestform HTML email designer helpers (preview, presets, logo).
 */
(function ($) {
	'use strict';

	function getBodyContent() {
		if (window.tinymce && window.tinymce.get('nestform_mail_body_template')) {
			return window.tinymce.get('nestform_mail_body_template').getContent();
		}
		var ta = document.getElementById('nestform_mail_body_template');
		return ta ? ta.value : '';
	}

	function setBodyContent(html) {
		if (window.tinymce && window.tinymce.get('nestform_mail_body_template')) {
			window.tinymce.get('nestform_mail_body_template').setContent(html || '');
			return;
		}
		var ta = document.getElementById('nestform_mail_body_template');
		if (ta) {
			ta.value = html || '';
		}
	}

	function insertToken(token) {
		if (window.tinymce && window.tinymce.get('nestform_mail_body_template')) {
			window.tinymce.get('nestform_mail_body_template').insertContent(token);
			return;
		}
		var ta = document.getElementById('nestform_mail_body_template');
		if (!ta) {
			return;
		}
		var start = ta.selectionStart || 0;
		var end = ta.selectionEnd || 0;
		ta.value = ta.value.slice(0, start) + token + ta.value.slice(end);
		ta.focus();
	}

	function presets() {
		var el = document.getElementById('nestform-mail-presets');
		if (!el) {
			return {};
		}
		try {
			return JSON.parse(el.textContent || '{}') || {};
		} catch (err) {
			return {};
		}
	}

	function samplePreviewHtml(body) {
		var sample = {
			name: 'Jane Doe',
			email: 'jane@example.com',
			message: 'Hello — this is a preview.',
			form_title: 'Sample form',
			form_id: '1',
			all_fields:
				'<table><tr><td><strong>name</strong></td><td>Jane Doe</td></tr><tr><td><strong>email</strong></td><td>jane@example.com</td></tr></table>',
		};
		var html = String(body || '');
		Object.keys(sample).forEach(function (key) {
			html = html.split('{' + key + '}').join(sample[key]);
		});
		return (
			'<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:16px;background:#f3f4f6">' +
			html +
			'</body></html>'
		);
	}

	function openPreview(root) {
		var panel = root.querySelector('[data-nestform-mail-preview-panel]');
		var frame = root.querySelector('[data-nestform-mail-preview-frame]');
		if (!panel || !frame) {
			return;
		}
		frame.srcdoc = samplePreviewHtml(getBodyContent());
		panel.hidden = false;
		document.body.classList.add('nestform-mail-preview-open');
		var closeBtn = panel.querySelector('[data-nestform-mail-preview-close].button');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closePreview(root) {
		var panel = (root && root.querySelector('[data-nestform-mail-preview-panel]')) ||
			document.querySelector('[data-nestform-mail-preview-panel]');
		if (!panel || panel.hidden) {
			return;
		}
		panel.hidden = true;
		document.body.classList.remove('nestform-mail-preview-open');
		var frame = panel.querySelector('[data-nestform-mail-preview-frame]');
		if (frame) {
			frame.removeAttribute('srcdoc');
		}
	}

	document.addEventListener('click', function (event) {
		if (event.target.closest('[data-nestform-mail-preview-close]')) {
			event.preventDefault();
			closePreview(event.target.closest('[data-nestform-mail-designer]'));
			return;
		}

		var root = event.target.closest('[data-nestform-mail-designer]');
		if (!root) {
			return;
		}

		if (event.target.closest('[data-nestform-mail-preview]')) {
			event.preventDefault();
			openPreview(root);
			return;
		}

		var tokenBtn = event.target.closest('[data-nestform-mail-token]');
		if (tokenBtn) {
			event.preventDefault();
			insertToken(tokenBtn.getAttribute('data-nestform-mail-token') || '');
			return;
		}

		if (event.target.closest('[data-nestform-mail-logo]')) {
			event.preventDefault();
			if (!window.wp || !wp.media) {
				return;
			}
			var frameMedia = wp.media({
				title: 'Select logo',
				button: { text: 'Use logo' },
				multiple: false,
				library: { type: 'image' },
			});
			frameMedia.on('select', function () {
				var attachment = frameMedia.state().get('selection').first().toJSON();
				var idInput = root.querySelector('[data-nestform-mail-logo-id]');
				var preview = root.querySelector('[data-nestform-mail-logo-preview]');
				var clearBtn = root.querySelector('[data-nestform-mail-logo-clear]');
				if (idInput) {
					idInput.value = String(attachment.id || 0);
				}
				if (preview) {
					preview.innerHTML = attachment.url
						? '<img src="' + attachment.url + '" alt="" />'
						: '';
				}
				if (clearBtn) {
					clearBtn.hidden = !attachment.id;
				}
				if (attachment.url) {
					insertToken('<img src="' + attachment.url + '" alt="" style="max-height:64px" />');
				}
			});
			frameMedia.open();
			return;
		}

		if (event.target.closest('[data-nestform-mail-logo-clear]')) {
			event.preventDefault();
			var idClear = root.querySelector('[data-nestform-mail-logo-id]');
			var previewClear = root.querySelector('[data-nestform-mail-logo-preview]');
			var clear = root.querySelector('[data-nestform-mail-logo-clear]');
			if (idClear) {
				idClear.value = '0';
			}
			if (previewClear) {
				previewClear.innerHTML = '';
			}
			if (clear) {
				clear.hidden = true;
			}
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key !== 'Escape') {
			return;
		}
		if (!document.body.classList.contains('nestform-mail-preview-open')) {
			return;
		}
		closePreview(null);
	});

	document.addEventListener('change', function (event) {
		var select = event.target.closest('[data-nestform-mail-preset]');
		if (!select || !select.value) {
			return;
		}
		var map = presets();
		var preset = map[select.value];
		if (preset && preset.html) {
			setBodyContent(preset.html);
			var htmlToggle = document.querySelector('[data-nestform-mail-html]');
			if (htmlToggle) {
				htmlToggle.checked = true;
			}
		}
		select.value = '';
	});
})(window.jQuery);
