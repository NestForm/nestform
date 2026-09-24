/**
 * Apply the collapsed sidebar class before first paint.
 */
(function () {
	'use strict';

	try {
		if (window.localStorage.getItem('nestform_sidebar_collapsed') === '1') {
			document.documentElement.classList.add('nestform-sidebar-collapsed');
		}
	} catch (err) {
		/* ignore */
	}
})();
