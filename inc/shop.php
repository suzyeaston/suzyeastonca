<?php
/**
 * Shop page module — assets, analytics, SEO helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_template_directory() . '/inc/shop-products.php';

/**
 * @param string               $event_name
 * @param array<string, mixed> $payload
 */
function se_shop_track_event( $event_name, $payload = [] ) {
	$payload = array_merge(
		[
			'page' => 'shop',
		],
		is_array( $payload ) ? $payload : []
	);

	/**
	 * Server-side shop analytics hook.
	 *
	 * @param string               $event_name
	 * @param array<string, mixed> $payload
	 */
	do_action( 'se_shop_event', $event_name, $payload );
}

function se_shop_is_active() {
	return is_page_template( 'page-shop.php' ) || is_page( 'shop' );
}

function se_enqueue_shop_assets() {
	$dir = get_template_directory();
	$uri = get_template_directory_uri();

	$css_path = '/assets/css/shop.css';
	if ( file_exists( $dir . $css_path ) ) {
		wp_enqueue_style(
			'se-shop',
			$uri . $css_path,
			[],
			filemtime( $dir . $css_path )
		);
	}

	$console_css_path = '/assets/css/shop-console.css';
	if ( file_exists( $dir . $console_css_path ) ) {
		wp_enqueue_style(
			'se-shop-console',
			$uri . $console_css_path,
			[ 'se-shop' ],
			filemtime( $dir . $console_css_path )
		);
	}

	if ( ! se_shop_is_active() ) {
		return;
	}

	$js_path = '/assets/js/shop.js';
	if ( file_exists( $dir . $js_path ) ) {
		wp_enqueue_script(
			'se-shop',
			$uri . $js_path,
			[],
			filemtime( $dir . $js_path ),
			true
		);

		wp_localize_script(
			'se-shop',
			'SeShopConfig',
			[
				'products' => array_map(
					static function ( $product ) {
						return [
							'slug'  => (string) ( $product['slug'] ?? '' ),
							'sku'   => (string) ( $product['sku'] ?? '' ),
							'title' => (string) ( $product['title'] ?? '' ),
							'tier'  => (string) ( $product['tier'] ?? '' ),
						];
					},
					se_get_shop_products()
				),
			]
		);
	}

	se_shop_track_event( 'page_view', [] );
}
add_action( 'wp_enqueue_scripts', 'se_enqueue_shop_assets', 25 );

/**
 * @return array<string, mixed>
 */
function se_shop_meta() {
	return [
		'title'       => 'Technical Consulting Sessions in Vancouver | Suzy Easton',
		'description' => 'Book focused technical help with Suzy Easton in Vancouver: debugging, AI workflows, automation, integrations and deep technical problem-solving. Remote sessions available.',
		'keywords'    => 'technical consulting Vancouver, hire Suzy Easton, WordPress debugging Vancouver, workflow automation, AI consulting session, East Vancouver consultant',
	];
}

/**
 * Product structured data for the shop page.
 *
 * @return array<string, mixed>
 */
function se_shop_structured_data() {
	$meta  = se_shop_meta();
	$items = [];

	foreach ( se_get_shop_products() as $product ) {
		$product_name = (string) ( $product['title'] ?? '' ) . ' · ' . (string) ( $product['subtitle'] ?? '' );

		$items[] = [
			'@type'       => 'Product',
			'name'        => $product_name,
			'description' => (string) ( $product['description'] ?? '' ),
			'sku'         => (string) ( $product['sku'] ?? '' ),
			'url'         => home_url( '/shop/#' . (string) ( $product['slug'] ?? '' ) ),
			'offers'      => [
				'@type'         => 'Offer',
				'price'         => (string) ( $product['price'] ?? 0 ),
				'priceCurrency' => (string) ( $product['currency'] ?? 'CAD' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => se_shop_product_checkout_url( $product ),
			],
		];

		$items[] = [
			'@type'       => 'Service',
			'name'        => $product_name,
			'description' => (string) ( $product['description'] ?? '' ),
			'provider'    => [
				'@type' => 'Person',
				'name'  => 'Suzy Easton',
				'url'   => home_url( '/' ),
			],
			'serviceType' => (string) ( $product['subtitle'] ?? 'Technical consulting' ),
			'areaServed'  => se_service_area_served(),
			'offers'      => [
				'@type'         => 'Offer',
				'price'         => (string) ( $product['price'] ?? 0 ),
				'priceCurrency' => (string) ( $product['currency'] ?? 'CAD' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => se_shop_product_checkout_url( $product ),
			],
		];
	}

	return [
		'@context' => 'https://schema.org',
		'@graph'   => array_merge(
			[
				se_website_schema( $meta['description'] ),
				se_person_schema(),
				[
					'@type'       => 'WebPage',
					'name'        => $meta['title'],
					'url'         => home_url( '/shop/' ),
					'description' => $meta['description'],
				],
			],
			$items
		),
	];
}

function se_shop_analytics_bootstrap() {
	if ( ! se_shop_is_active() ) {
		return;
	}
	?>
	<script>
	(function () {
		var conversionMap = {
			page_view: 'shop_view',
			product_view: 'consulting_product_view',
			checkout_click: 'checkout_click'
		};

		function seShopEvent(name, data) {
			var detail = Object.assign({ event: name }, data || {});
			window.dispatchEvent(new CustomEvent('se-shop:event', { detail: detail }));
			if (window.dataLayer) {
				window.dataLayer.push(Object.assign({ event: 'shop_' + name }, data || {}));
			}
			if (typeof window.seTrackEvent === 'function' && conversionMap[name]) {
				window.seTrackEvent(conversionMap[name], Object.assign({ page: 'shop' }, data || {}));
			}
		}
		window.seShopEvent = seShopEvent;
		window.addEventListener('se-shop:event', function (e) {
			var d = e.detail || {};
			if (window.dataLayer && d.event) {
				window.dataLayer.push(Object.assign({}, d, { event: 'shop_' + d.event }));
			}
		});
		seShopEvent('page_view', { surface: 'shop' });
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'se_shop_analytics_bootstrap', 99 );
