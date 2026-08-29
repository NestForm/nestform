/**
 * Shared Forms / Entries hub list: search, sort, filter, copy, pagination.
 */
(function () {
	'use strict';

	var cfg = window.nestformHub || {};
	var i18n = cfg.i18n || {};
	var pageSize = Math.max(1, parseInt(cfg.pageSize, 10) || 20);

	function bootHub(root) {
		var search = root.querySelector('[data-nestform-hub-search]');
		var sort = root.querySelector('[data-nestform-hub-sort]');
		var hasOnly = root.querySelector('[data-nestform-hub-has-count]');
		var list = root.querySelector('[data-nestform-hub-list]');
		var empty = root.querySelector('[data-nestform-hub-empty]');
		var result = root.querySelector('[data-nestform-hub-result]');
		var pagerTop = root.querySelector('[data-nestform-hub-pager]');
		var pagerBottom = root.querySelector('[data-nestform-hub-pager-bottom]');
		if (!list) {
			return;
		}

		var rows = Array.prototype.slice.call(list.querySelectorAll('[data-nestform-hub-row]'));
		var currentPage = 1;
		var filterToken = '';

		function filterKey(q, onlyWith, mode) {
			return [q, onlyWith ? '1' : '0', mode].join('|');
		}

		function renderPager(pager, matched, page, pages) {
			if (!pager) {
				return;
			}
			if (matched === 0) {
				pager.hidden = true;
				pager.innerHTML = '';
				return;
			}

			var from = (page - 1) * pageSize + 1;
			var to = Math.min(matched, page * pageSize);
			var metaTpl = i18n.showingRange || 'Showing %1$s–%2$s of %3$s';
			var meta = metaTpl
				.replace('%1$s', String(from))
				.replace('%2$s', String(to))
				.replace('%3$s', String(matched));

			var html = '<p class="nestform-hub__pager-meta">' + meta + '</p>';
			if (pages > 1) {
				html += '<div class="nestform-hub__pager-links">';
				var start = Math.max(1, page - 2);
				var end = Math.min(pages, page + 2);
				if (page > 1) {
					html +=
						'<button type="button" class="page-numbers" data-nestform-hub-page="' +
						(page - 1) +
						'">&lsaquo;</button>';
				}
				if (start > 1) {
					html += '<button type="button" class="page-numbers" data-nestform-hub-page="1">1</button>';
					if (start > 2) {
						html += '<span class="page-numbers dots">&hellip;</span>';
					}
				}
				for (var i = start; i <= end; i++) {
					if (i === page) {
						html += '<span class="page-numbers current" aria-current="page">' + i + '</span>';
					} else {
						html +=
							'<button type="button" class="page-numbers" data-nestform-hub-page="' +
							i +
							'">' +
							i +
							'</button>';
					}
				}
				if (end < pages) {
					if (end < pages - 1) {
						html += '<span class="page-numbers dots">&hellip;</span>';
					}
					html +=
						'<button type="button" class="page-numbers" data-nestform-hub-page="' +
						pages +
						'">' +
						pages +
						'</button>';
				}
				if (page < pages) {
					html +=
						'<button type="button" class="page-numbers" data-nestform-hub-page="' +
						(page + 1) +
						'">&rsaquo;</button>';
				}
				html += '</div>';
			}

			pager.innerHTML = html;
			pager.hidden = false;
		}

		function apply() {
			var q = search ? String(search.value || '').trim().toLowerCase() : '';
			var onlyWith = hasOnly && hasOnly.checked;
			var mode = sort ? sort.value : 'count';
			var token = filterKey(q, onlyWith, mode);
			if (token !== filterToken) {
				filterToken = token;
				currentPage = 1;
			}

			rows.sort(function (a, b) {
				var ca = parseInt(a.getAttribute('data-count') || '0', 10);
				var cb = parseInt(b.getAttribute('data-count') || '0', 10);
				var fa = parseInt(a.getAttribute('data-fields') || '0', 10);
				var fb = parseInt(b.getAttribute('data-fields') || '0', 10);
				var na = parseInt(a.getAttribute('data-new') || '0', 10);
				var nb = parseInt(b.getAttribute('data-new') || '0', 10);
				var la = parseInt(a.getAttribute('data-last') || '0', 10);
				var lb = parseInt(b.getAttribute('data-last') || '0', 10);
				var ta = (a.getAttribute('data-title') || '').toLowerCase();
				var tb = (b.getAttribute('data-title') || '').toLowerCase();
				var ia = parseInt(a.getAttribute('data-id') || '0', 10);
				var ib = parseInt(b.getAttribute('data-id') || '0', 10);

				if (mode === 'title') {
					return ta < tb ? -1 : ta > tb ? 1 : ia - ib;
				}
				if (mode === 'id') {
					return ia - ib;
				}
				if (mode === 'fields') {
					if (fb !== fa) {
						return fb - fa;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (mode === 'new') {
					if (nb !== na) {
						return nb - na;
					}
					if (cb !== ca) {
						return cb - ca;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (mode === 'last') {
					if (lb !== la) {
						return lb - la;
					}
					return ta < tb ? -1 : ta > tb ? 1 : 0;
				}
				if (cb !== ca) {
					return cb - ca;
				}
				return ta < tb ? -1 : ta > tb ? 1 : 0;
			});

			rows.forEach(function (row) {
				list.appendChild(row);
			});

			var matchedRows = [];
			rows.forEach(function (row) {
				var title = (row.getAttribute('data-title') || '').toLowerCase();
				var id = row.getAttribute('data-id') || '';
				var count = parseInt(row.getAttribute('data-count') || '0', 10);
				var match =
					(!q || title.indexOf(q) !== -1 || id.indexOf(q) !== -1) &&
					(!onlyWith || count > 0);
				row.setAttribute('data-hub-match', match ? '1' : '0');
				if (match) {
					matchedRows.push(row);
				}
			});

			var matched = matchedRows.length;
			var pages = Math.max(1, Math.ceil(matched / pageSize));
			if (currentPage > pages) {
				currentPage = pages;
			}

			var start = (currentPage - 1) * pageSize;
			var end = start + pageSize;
			rows.forEach(function (row) {
				if (row.getAttribute('data-hub-match') !== '1') {
					row.hidden = true;
					return;
				}
				var idx = matchedRows.indexOf(row);
				row.hidden = idx < start || idx >= end;
			});

			if (empty) {
				empty.hidden = matched > 0;
			}
			if (result) {
				result.hidden = true;
			}

			renderPager(pagerTop, matched, currentPage, pages);
			renderPager(pagerBottom, matched, currentPage, pages);
		}

		if (search) {
			search.addEventListener('input', apply);
			window.setTimeout(function () {
				search.focus();
			}, 0);
		}
		if (sort) {
			sort.addEventListener('change', apply);
		}
		if (hasOnly) {
			hasOnly.addEventListener('change', apply);
		}

		function closeMoreMenus(except) {
			root.querySelectorAll('[data-nestform-hub-more]').forEach(function (wrap) {
				if (except && wrap === except) {
					return;
				}
				var menu = wrap.querySelector('.nestform-hub__more-menu');
				var toggle = wrap.querySelector('.nestform-hub__more-toggle');
				if (menu) {
					menu.hidden = true;
				}
				if (toggle) {
					toggle.setAttribute('aria-expanded', 'false');
				}
			});
		}

		function onDocCloseMore(event) {
			if (event.target.closest && event.target.closest('[data-nestform-hub-more]')) {
				return;
			}
			closeMoreMenus();
		}

		function onDocKeyCloseMore(event) {
			if (event.key === 'Escape') {
				closeMoreMenus();
			}
		}

		// Capture on document so clicks outside the hub (sidebar, header, etc.) close the menu.
		document.addEventListener('pointerdown', onDocCloseMore, true);
		document.addEventListener('keydown', onDocKeyCloseMore);

		root.addEventListener('click', function (event) {
			var moreToggle = event.target.closest('.nestform-hub__more-toggle');
			if (moreToggle && root.contains(moreToggle)) {
				event.preventDefault();
				var wrap = moreToggle.closest('[data-nestform-hub-more]');
				var menu = wrap ? wrap.querySelector('.nestform-hub__more-menu') : null;
				var open = menu && menu.hidden;
				closeMoreMenus(wrap);
				if (menu) {
					menu.hidden = !open;
				}
				moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				return;
			}

			var pageBtn = event.target.closest('[data-nestform-hub-page]');
			if (pageBtn && root.contains(pageBtn)) {
				event.preventDefault();
				var next = parseInt(pageBtn.getAttribute('data-nestform-hub-page') || '1', 10);
				if (!isNaN(next) && next >= 1) {
					currentPage = next;
					apply();
					if (list && typeof list.scrollIntoView === 'function') {
						list.scrollIntoView({ block: 'start', behavior: 'smooth' });
					}
				}
				return;
			}

			var del = event.target.closest('[data-nestform-hub-delete]');
			if (del) {
				var confirmMsg = del.getAttribute('data-confirm') || 'Move this form to Trash?';
				if (!window.confirm(confirmMsg)) {
					event.preventDefault();
				}
				return;
			}

			var btn = event.target.closest('[data-nestform-hub-copy]');
			if (btn) {
				event.preventDefault();
				var text = btn.getAttribute('data-nestform-hub-copy') || '';
				if (!text) {
					return;
				}
				var done = function () {
					var prev = btn.textContent;
					btn.textContent = i18n.copied || 'Copied';
					window.setTimeout(function () {
						btn.textContent = prev;
					}, 1200);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(done).catch(function () {
						window.prompt(i18n.copyPrompt || 'Copy:', text);
					});
				} else {
					window.prompt(i18n.copyPrompt || 'Copy:', text);
				}
				return;
			}

			if (event.target.closest('a, button, input, select, textarea, label')) {
				return;
			}

			var row = event.target.closest('[data-nestform-hub-row]');
			if (!row || !list.contains(row)) {
				return;
			}
			var editUrl = row.getAttribute('data-edit-url') || '';
			if (editUrl) {
				window.location.href = editUrl;
			}
		});

		root.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return;
			}
			if (event.target.closest('a, button, input, select, textarea')) {
				return;
			}
			var row = event.target.closest('[data-nestform-hub-row]');
			if (!row || !list.contains(row) || event.target !== row) {
				return;
			}
			var editUrl = row.getAttribute('data-edit-url') || '';
			if (!editUrl) {
				return;
			}
			event.preventDefault();
			window.location.href = editUrl;
		});

		apply();
	}

	function boot() {
		document.querySelectorAll('[data-nestform-hub]').forEach(bootHub);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
