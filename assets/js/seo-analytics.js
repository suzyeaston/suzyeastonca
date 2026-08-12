(function () {
	'use strict';

	function getUtmParams() {
		var params = new URLSearchParams(window.location.search);
		var utm = {};
		params.forEach(function (value, key) {
			if (key.indexOf('utm_') === 0) {
				utm[key] = value;
			}
		});
		return utm;
	}

	function appendUtmToUrl(url, utm) {
		if (!url || !utm || !Object.keys(utm).length) {
			return url;
		}

		try {
			var parsed = new URL(url, window.location.origin);
			Object.keys(utm).forEach(function (key) {
				parsed.searchParams.set(key, utm[key]);
			});
			return parsed.toString();
		} catch (error) {
			return url;
		}
	}

	function preserveUtmOnLinks() {
		var utm = getUtmParams();
		if (!Object.keys(utm).length) {
			return;
		}

		document.querySelectorAll('[data-preserve-utm], [data-hire-cta]').forEach(function (link) {
			var href = link.getAttribute('href');
			if (!href || href.indexOf('mailto:') === 0 || href.indexOf('tel:') === 0) {
				return;
			}

			var next = appendUtmToUrl(href, utm);
			if (next !== href) {
				link.setAttribute('href', next);
			}
		});

		document.querySelectorAll('[data-shop-checkout]').forEach(function (link) {
			var href = link.getAttribute('href');
			if (!href) {
				return;
			}
			var next = appendUtmToUrl(href, utm);
			if (next !== href) {
				link.setAttribute('href', next);
			}
		});
	}

	function trackHireCtas() {
		document.querySelectorAll('[data-hire-cta]').forEach(function (cta) {
			cta.addEventListener('click', function () {
				if (typeof window.seTrackEvent !== 'function') {
					return;
				}

				window.seTrackEvent('hire_cta_click', {
					label: cta.getAttribute('data-hire-cta-label') || '',
					href: cta.getAttribute('href') || '',
					text: (cta.textContent || '').trim(),
				});
			});
		});
	}

	function init() {
		preserveUtmOnLinks();
		trackHireCtas();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
