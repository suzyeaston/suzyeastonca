<?php
/**
 * Shop product detail panel partial.
 *
 * @var array<string, mixed> $args
 */

$product = isset( $args['product'] ) && is_array( $args['product'] ) ? $args['product'] : [];

$slug     = (string) ( $product['slug'] ?? '' );

if ( '' === $slug ) {
	return;
}

$tier     = sanitize_html_class( (string) ( $product['tier'] ?? 'default' ) );
$motif    = sanitize_html_class( (string) ( $product['motif'] ?? 'terminal' ) );
$good_for = is_array( $product['good_for'] ?? null ) ? $product['good_for'] : [];
$prep     = is_array( $product['prep'] ?? null ) ? $product['prep'] : [];
?>
<section
	id="<?php echo esc_attr( $slug ); ?>"
	class="shop-product-detail shop-product-detail--<?php echo esc_attr( $tier ); ?> shop-product-detail--<?php echo esc_attr( $motif ); ?>"
	data-shop-product-detail
	data-shop-slug="<?php echo esc_attr( $slug ); ?>"
	data-shop-sku="<?php echo esc_attr( (string) ( $product['sku'] ?? '' ) ); ?>"
	aria-labelledby="shop-detail-<?php echo esc_attr( $slug ); ?>-title"
	tabindex="-1"
>
	<div class="shop-product-detail__terminal">
		<div class="shop-product-detail__shell-bar pixel-font">
			<span class="shop-product-detail__dots" aria-hidden="true"><i></i><i></i><i></i></span>
			<span class="shop-product-detail__path">suzy@yvr — <?php echo esc_html( $slug ); ?></span>
			<span class="shop-product-detail__build"><?php echo esc_html( se_format_shop_price( $product ) ); ?></span>
		</div>
		<div class="shop-product-detail__shell-body">
			<p class="shop-product-detail__prompt pixel-font">
				<span class="shop-product-detail__sigil">$</span>
				<?php echo esc_html( ltrim( (string) ( $product['prompt'] ?? '' ), '$ ' ) ); ?>
				<span class="shop-product-detail__caret" aria-hidden="true"></span>
			</p>
			<h3 id="shop-detail-<?php echo esc_attr( $slug ); ?>-title" class="shop-product-detail__title pixel-font">
				<?php echo esc_html( (string) ( $product['title'] ?? '' ) ); ?>
				<span class="shop-product-detail__subtitle"><?php echo esc_html( (string) ( $product['subtitle'] ?? '' ) ); ?></span>
			</h3>
			<p class="shop-product-detail__description"><?php echo esc_html( (string) ( $product['description'] ?? '' ) ); ?></p>

			<div class="shop-product-detail__specs">
				<div class="shop-product-detail__spec">
					<span class="shop-product-detail__spec-label pixel-font"><?php echo esc_html( 'Duration' ); ?></span>
					<span class="shop-product-detail__spec-value"><?php echo esc_html( (string) ( $product['duration'] ?? '' ) ); ?></span>
				</div>
				<div class="shop-product-detail__spec">
					<span class="shop-product-detail__spec-label pixel-font"><?php echo esc_html( 'Price' ); ?></span>
					<span class="shop-product-detail__spec-value pixel-font"><?php echo esc_html( se_format_shop_price( $product ) ); ?></span>
				</div>
			</div>

			<?php if ( ! empty( $good_for ) ) : ?>
				<div class="shop-product-detail__block">
					<h4 class="pixel-font"><?php echo esc_html( 'Good for' ); ?></h4>
					<ul class="shop-product-detail__list">
						<?php foreach ( $good_for as $item ) : ?>
							<li><?php echo esc_html( (string) $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $prep ) ) : ?>
				<div class="shop-product-detail__block">
					<h4 class="pixel-font"><?php echo esc_html( 'Before we meet' ); ?></h4>
					<ul class="shop-product-detail__list">
						<?php foreach ( $prep as $item ) : ?>
							<li><?php echo esc_html( (string) $item ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="shop-product-detail__motif shop-product-detail__motif--<?php echo esc_attr( $motif ); ?>" aria-hidden="true"></div>
		</div>
	</div>

	<div class="shop-product-detail__cta-row">
		<a
			class="pixel-button shop-product-detail__buy"
			href="<?php echo esc_url( se_shop_product_checkout_url( $product ) ); ?>"
			target="_blank"
			rel="noopener noreferrer"
			data-shop-checkout
			data-shop-slug="<?php echo esc_attr( $slug ); ?>"
			data-shop-sku="<?php echo esc_attr( (string) ( $product['sku'] ?? '' ) ); ?>"
		><?php echo esc_html( (string) ( $product['cta_label'] ?? 'Book now' ) ); ?></a>
		<a class="pixel-button shop-product-detail__back" href="#shop-catalog"><?php echo esc_html( 'Back to catalog' ); ?></a>
	</div>
</section>
