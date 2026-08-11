<?php
/**
 * Shop product catalog — single source of truth for consulting offers.
 *
 * Checkout URLs are config values. Swap Buy Me a Coffee links for Stripe,
 * WooCommerce, SureCart, or native checkout without touching templates.
 *
 * Override any checkout URL via wp-config.php:
 *   define( 'SE_SHOP_CHECKOUT_DEBUG', 'https://...' );
 *   define( 'SE_SHOP_CHECKOUT_AUTOMATE', 'https://...' );
 *   define( 'SE_SHOP_CHECKOUT_DEEP_DIVE', 'https://...' );
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @return array<int, array<string, mixed>>
 */
function se_shop_products_raw() {
	return [
		[
			'slug'          => 'debug-with-suzy',
			'sku'           => 'debug-30',
			'title'         => 'Debug With Suzy',
			'subtitle'      => '30 Minute Tech Rescue',
			'tier'          => 'rescue',
			'price'         => 75,
			'currency'      => 'CAD',
			'duration'      => '30 minutes',
			'tagline'       => 'Something broke. You need a second brain now.',
			'description'   => 'A focused half-hour to find the failure path, explain it plainly, and point at the fix — or tell you honestly if it needs more time.',
			'good_for'      => [
				'WordPress white screens and plugin pile-ups',
				'API or integration acting weird in production',
				'Deployment panic and “it works on my machine”',
				'Log soup, timeout loops, and mystery 500s',
			],
			'prep'          => [
				'Send the URL, exact error message, and what you already tried.',
				'Screenshots or a short screen recording help.',
				'Don\'t sandbag the timeline — say when you need relief.',
			],
			'checkout_url'  => 'https://buymeacoffee.com/wi0amge/extras/REPLACE_DEBUG_30MIN',
			'checkout_const'=> 'SE_SHOP_CHECKOUT_DEBUG',
			'motif'         => 'terminal',
			'prompt'        => '$ suzy --debug --urgent',
			'cta_label'     => 'Book rescue',
			'featured'      => true,
		],
		[
			'slug'          => 'automate-with-suzy',
			'sku'           => 'automate-ai',
			'title'         => 'Automate This With Suzy',
			'subtitle'      => 'AI + Workflow Session',
			'tier'          => 'workflow',
			'price'         => 125,
			'currency'      => 'CAD',
			'duration'      => '60 minutes',
			'tagline'       => 'Stop copy-pasting your Tuesday.',
			'description'   => 'Map the workflow, spot the boring manual steps, and sketch what automation or AI could actually help — without the magic-thinking deck.',
			'good_for'      => [
				'Spreadsheet rituals and copy-paste loops',
				'Slack/email handoffs that should be one button',
				'Internal tool gaps and lightweight automation scoping',
				'Practical AI prototype planning with reviewable outputs',
			],
			'prep'          => [
				'List the steps you hate — bullet points are fine.',
				'Share one example file, screenshot, or short Loom of the current flow.',
				'Name the tools already in the stack (Sheets, Notion, Zapier, etc.).',
			],
			'checkout_url'  => 'https://buymeacoffee.com/wi0amge/extras/REPLACE_AUTOMATE_SESSION',
			'checkout_const'=> 'SE_SHOP_CHECKOUT_AUTOMATE',
			'motif'         => 'waveform',
			'prompt'        => '$ suzy --automate --workflow',
			'cta_label'     => 'Book session',
			'featured'      => false,
		],
		[
			'slug'          => 'technical-deep-dive',
			'sku'           => 'deep-dive-90',
			'title'         => 'Technical Deep Dive',
			'subtitle'      => '90 Minute Intensive',
			'tier'          => 'intensive',
			'price'         => 225,
			'currency'      => 'CAD',
			'duration'      => '90 minutes',
			'tagline'       => 'The complicated one. Bring the whole mess.',
			'description'   => 'Ninety minutes to dig into architecture, integrations, identity, data flows, or the bug everyone has opinions about but nobody has pinned down.',
			'good_for'      => [
				'SSO/SAML/OAuth/SCIM identity puzzles',
				'Multi-system outages and cross-team blame storms',
				'Release confidence, QA gaps, and architecture decisions',
				'WordPress + API + ops collisions that need a map',
			],
			'prep'          => [
				'Links to repos, dashboards, tickets, or status pages.',
				'Context doc or Loom optional — not required.',
				'Know your hard deadline and who needs the answer.',
			],
			'checkout_url'  => 'https://buymeacoffee.com/wi0amge/extras/REPLACE_DEEP_DIVE_90',
			'checkout_const'=> 'SE_SHOP_CHECKOUT_DEEP_DIVE',
			'motif'         => 'orca',
			'prompt'        => '$ suzy --deep-dive --intensive',
			'cta_label'     => 'Book intensive',
			'featured'      => false,
		],
	];
}

/**
 * Resolve checkout URL with optional wp-config constant override.
 *
 * @param array<string, mixed> $product
 */
function se_shop_product_checkout_url( array $product ) {
	$const = (string) ( $product['checkout_const'] ?? '' );
	if ( $const && defined( $const ) && constant( $const ) ) {
		$url = (string) constant( $const );
	} else {
		$url = (string) ( $product['checkout_url'] ?? '' );
	}

	/**
	 * Filter a product checkout URL before output.
	 *
	 * @param string $url
	 * @param array  $product
	 */
	return apply_filters( 'se_shop_checkout_url', $url, $product );
}

/**
 * @return array<int, array<string, mixed>>
 */
function se_get_shop_products() {
	$products = se_shop_products_raw();

	foreach ( $products as $index => $product ) {
		$products[ $index ]['checkout_url'] = se_shop_product_checkout_url( $product );
	}

	/**
	 * Filter the full shop catalog.
	 *
	 * @param array<int, array<string, mixed>> $products
	 */
	return apply_filters( 'se_shop_products', $products );
}

/**
 * @return array<string, mixed>|null
 */
function se_get_shop_product( $slug ) {
	$slug = sanitize_title( (string) $slug );

	foreach ( se_get_shop_products() as $product ) {
		if ( (string) ( $product['slug'] ?? '' ) === $slug ) {
			return $product;
		}
	}

	return null;
}

/**
 * @param array<string, mixed> $product
 */
function se_format_shop_price( array $product ) {
	$price    = (float) ( $product['price'] ?? 0 );
	$currency = strtoupper( (string) ( $product['currency'] ?? 'CAD' ) );

	return sprintf( '%s $%s', $currency, number_format( $price, 0 ) );
}
