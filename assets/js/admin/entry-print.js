document.addEventListener('DOMContentLoaded', function () {
	var button = document.querySelector('[data-nestform-print]');
	if (!button) {
		return;
	}
	button.addEventListener('click', function () {
		window.print();
	});
});
