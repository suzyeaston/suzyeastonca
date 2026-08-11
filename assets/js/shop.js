(function () {
	'use strict';

	function emit(name, data) {
		if (typeof window.seShopEvent === 'function') {
			window.seShopEvent(name, data || {});
		}
	}

	function slugFromHash() {
		var hash = (window.location.hash || '').replace(/^#/, '');
		return hash || '';
	}

	function getDetail(slug) {
		if (!slug) {
			return null;
		}
		return document.querySelector('[data-shop-product-detail][data-shop-slug="' + slug + '"]');
	}

	function setActiveDetail(slug, source) {
		var panels = document.querySelectorAll('[data-shop-product-detail]');
		var cards = document.querySelectorAll('[data-shop-product-card]');
		var active = getDetail(slug);
		var tracked = false;

		panels.forEach(function (panel) {
			panel.classList.remove('is-active');
		});

		cards.forEach(function (card) {
			card.classList.toggle('is-selected', card.getAttribute('data-shop-slug') === slug);
		});

		if (!active) {
			return;
		}

		active.classList.add('is-active');
		active.focus({ preventScroll: true });

		if (!tracked) {
			emit('product_view', {
				slug: slug,
				sku: active.getAttribute('data-shop-sku') || '',
				source: source || 'hash',
			});
		}

		window.requestAnimationFrame(function () {
			active.scrollIntoView({ behavior: 'smooth', block: 'start' });
		});
	}

	function bindDetailTriggers() {
		document.querySelectorAll('[data-shop-detail-trigger]').forEach(function (trigger) {
			trigger.addEventListener('click', function (event) {
				var slug = trigger.getAttribute('data-shop-slug') || '';
				if (!slug) {
					return;
				}

				event.preventDefault();
				if (window.location.hash.replace(/^#/, '') !== slug) {
					window.location.hash = slug;
				} else {
					setActiveDetail(slug, 'click');
				}

				emit('product_card_click', {
					slug: slug,
					sku: (function () {
						var card = trigger.closest('[data-shop-product-card]');
						return card ? card.getAttribute('data-shop-sku') || '' : '';
					})(),
				});
			});
		});
	}

	function bindCheckoutTracking() {
		document.querySelectorAll('[data-shop-checkout]').forEach(function (link) {
			link.addEventListener('click', function () {
				emit('checkout_click', {
					slug: link.getAttribute('data-shop-slug') || '',
					sku: link.getAttribute('data-shop-sku') || '',
					href: link.getAttribute('href') || '',
				});
			});
		});
	}

	function bindCardTracking() {
		document.querySelectorAll('[data-shop-product-card]').forEach(function (card) {
			card.addEventListener('mouseenter', function once() {
				emit('product_card_hover', {
					slug: card.getAttribute('data-shop-slug') || '',
					sku: card.getAttribute('data-shop-sku') || '',
				});
				card.removeEventListener('mouseenter', once);
			});
		});
	}

	function bindHashNavigation() {
		window.addEventListener('hashchange', function () {
			setActiveDetail(slugFromHash(), 'hash');
		});

		if (slugFromHash()) {
			setActiveDetail(slugFromHash(), 'initial_hash');
		}
	}

	function init() {
		if (!document.querySelector('.shop-page')) {
			return;
		}

		bindDetailTriggers();
		bindCheckoutTracking();
		bindCardTracking();
		bindHashNavigation();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
