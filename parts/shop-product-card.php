<?php
/**
 * Shop product card partial.
 *
 * @var array<string, mixed> $args
 */

$product = isset( $args['product'] ) && is_array( $args['product'] ) ? $args['product'] : [];

$slug      = (string) ( $product['slug'] ?? '' );

if ( '' === $slug ) {
	return;
}

$tier      = sanitize_html_class( (string) ( $product['tier'] ?? 'default' ) );
$featured  = ! empty( $product['featured'] );
$detail_id = 'shop-detail-' . $slug;
?>
<article
	class="shop-product-card shop-product-card--<?php echo esc_attr( $tier ); ?><?php echo $featured ? ' shop-product-card--featured' : ''; ?>"
	data-shop-product-card
	data-shop-slug="<?php echo esc_attr( $slug ); ?>"
	data-shop-sku="<?php echo esc_attr( (string) ( $product['sku'] ?? '' ) ); ?>"
	aria-labelledby="shop-card-<?php echo esc_attr( $slug ); ?>"
>
	<div class="shop-product-card__shell">
		<p class="shop-product-card__tier pixel-font"><?php echo esc_html( (string) ( $product['tier'] ?? '' ) ); ?></p>
		<h2 id="shop-card-<?php echo esc_attr( $slug ); ?>" class="shop-product-card__title pixel-font">
			<?php echo esc_html( (string) ( $product['title'] ?? '' ) ); ?>
		</h2>
		<p class="shop-product-card__subtitle pixel-font"><?php echo esc_html( (string) ( $product['subtitle'] ?? '' ) ); ?></p>
		<p class="shop-product-card__tagline"><?php echo esc_html( (string) ( $product['tagline'] ?? '' ) ); ?></p>
		<div class="shop-product-card__meta">
			<span class="shop-product-card__price pixel-font"><?php echo esc_html( se_format_shop_price( $product ) ); ?></span>
			<span class="shop-product-card__duration pixel-font"><?php echo esc_html( (string) ( $product['duration'] ?? '' ) ); ?></span>
		</div>
		<p class="shop-product-card__prompt pixel-font" aria-hidden="true">
			<span class="shop-product-card__sigil">$</span>
			<?php echo esc_html( ltrim( (string) ( $product['prompt'] ?? '' ), '$ ' ) ); ?>
		</p>
	</div>
	<div class="shop-product-card__actions">
		<a
			class="pixel-button shop-product-card__detail-link"
			href="<?php echo esc_url( home_url( '/shop/#' . $slug ) ); ?>"
			data-shop-detail-trigger
			data-shop-slug="<?php echo esc_attr( $slug ); ?>"
			aria-controls="<?php echo esc_attr( $detail_id ); ?>"
		><?php echo esc_html( 'Details' ); ?></a>
		<a
			class="pixel-button shop-product-card__buy"
			href="<?php echo esc_url( se_shop_product_checkout_url( $product ) ); ?>"
			target="_blank"
			rel="noopener noreferrer"
			data-shop-checkout
			data-shop-slug="<?php echo esc_attr( $slug ); ?>"
			data-shop-sku="<?php echo esc_attr( (string) ( $product['sku'] ?? '' ) ); ?>"
		><?php echo esc_html( (string) ( $product['cta_label'] ?? 'Book now' ) ); ?></a>
	</div>
</article>
