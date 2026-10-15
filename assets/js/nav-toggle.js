/**
 * Mobile nav toggle — tiny, dependency-free. Opens/closes the primary
 * navigation dropdown on small screens. No frameworks, no build step.
 */
(function () {
	var header = document.querySelector('.site-header');
	var toggle = document.querySelector('.nav-toggle');
	var nav = document.getElementById('primary-nav');
	if (!header || !toggle || !nav) return;

	function closeNav() {
		header.classList.remove('nav-open');
		toggle.setAttribute('aria-expanded', 'false');
	}
	function openNav() {
		header.classList.add('nav-open');
		toggle.setAttribute('aria-expanded', 'true');
	}

	toggle.addEventListener('click', function () {
		if (header.classList.contains('nav-open')) {
			closeNav();
		} else {
			openNav();
		}
	});

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') closeNav();
	});

	document.addEventListener('click', function (e) {
		if (!header.classList.contains('nav-open')) return;
		if (header.contains(e.target)) return;
		closeNav();
	});

	// Collapse back to desktop layout without a stuck-open state if the
	// viewport is resized (e.g. phone rotated to landscape past the
	// breakpoint, or a tablet/desktop window resize).
	window.addEventListener('resize', function () {
		if (window.innerWidth >= 768) closeNav();
	});
})();
