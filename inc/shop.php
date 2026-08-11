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
		'title'       => 'Shop Suzy | Hire Suzy Easton — Debug, Automate, Deep Dive',
		'description' => 'Book focused consulting time with Suzy Easton: 30-minute tech rescue, AI + workflow sessions, and 90-minute deep dives. Vancouver-built, no corporate brochureware.',
		'keywords'    => 'hire Suzy Easton, tech consulting Vancouver, WordPress debugging, workflow automation, AI strategy session',
	];
}

/**
 * Product structured data for the shop page.
 *
 * @return array<string, mixed>
 */
function se_shop_structured_data() {
	$items = [];

	foreach ( se_get_shop_products() as $product ) {
		$items[] = [
			'@type'           => 'Product',
			'name'            => (string) ( $product['title'] ?? '' ) . ' · ' . (string) ( $product['subtitle'] ?? '' ),
			'description'     => (string) ( $product['description'] ?? '' ),
			'sku'             => (string) ( $product['sku'] ?? '' ),
			'url'             => home_url( '/shop/#' . (string) ( $product['slug'] ?? '' ) ),
			'offers'          => [
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
				[
					'@type'       => 'WebPage',
					'name'        => 'Shop Suzy',
					'url'         => home_url( '/shop/' ),
					'description' => se_shop_meta()['description'],
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
		function seShopEvent(name, data) {
			var detail = Object.assign({ event: name }, data || {});
			window.dispatchEvent(new CustomEvent('se-shop:event', { detail: detail }));
			if (window.dataLayer) {
				window.dataLayer.push(Object.assign({ event: 'shop_' + name }, data || {}));
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
