<?php
/*
Template Name: Shop
*/
get_header();

$products = se_get_shop_products();
?>
<main id="main-content" class="shop-page">
	<article class="page-content shop-content">
		<section class="crt-block shop-hero" aria-labelledby="shop-title">
			<p class="shop-kicker pixel-font shop-eyebrow"><?php echo esc_html( 'consulting storefront // yvr brain rental' ); ?></p>
			<h1 id="shop-title" class="retro-title glow-lite"><?php echo esc_html( 'Shop Suzy' ); ?></h1>
			<p class="shop-lead"><?php echo esc_html( 'Buy focused time. Debug the bug, automate the ritual, or go deep on the messy thing.' ); ?></p>
			<p class="shop-hero-subcopy"><?php echo esc_html( 'Fixed-price sessions. Checkout today via Buy Me a Coffee — swap the payment rail later without rebuilding this page.' ); ?></p>
			<div class="shop-hero-terminal" aria-hidden="true">
				<p class="shop-hero-terminal__line pixel-font"><span class="shop-hero-terminal__sigil">$</span> ./hire-suzy --list-offers<span class="shop-hero-terminal__caret"></span></p>
				<p class="shop-hero-terminal__line shop-hero-terminal__line--muted pixel-font"><?php echo esc_html( '3 sessions loaded · cad pricing · remote-friendly' ); ?></p>
			</div>
		</section>

		<section id="shop-catalog" class="crt-block shop-catalog" aria-labelledby="shop-catalog-title">
			<h2 id="shop-catalog-title" class="pixel-font"><?php echo esc_html( 'Pick your session' ); ?></h2>
			<p class="shop-catalog__lede"><?php echo esc_html( 'Rescue first. Workflow second. Deep dive when the whole system is arguing.' ); ?></p>
			<div class="shop-product-grid">
				<?php foreach ( $products as $product ) : ?>
					<?php get_template_part( 'parts/shop', 'product-card', [ 'product' => $product ] ); ?>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="shop-details" aria-label="<?php echo esc_attr( 'Session details' ); ?>">
			<?php foreach ( $products as $product ) : ?>
				<?php get_template_part( 'parts/shop', 'product-detail', [ 'product' => $product ] ); ?>
			<?php endforeach; ?>
		</section>

		<section class="crt-block shop-custom-work" aria-labelledby="shop-custom-title">
			<h2 id="shop-custom-title" class="pixel-font"><?php echo esc_html( 'Got a bigger problem?' ); ?></h2>
			<p><?php echo esc_html( 'Retainer, multi-week build, senior role, or something that needs a scope call first — that\'s a different door.' ); ?></p>
			<div class="shop-custom-work__actions">
				<a class="pixel-button shop-custom-work__cta" href="<?php echo esc_url( home_url( '/work-with-suzy/' ) ); ?>"><?php echo esc_html( 'Talk to Suzy' ); ?></a>
				<button class="pixel-button shop-custom-work__contact" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Send a note' ); ?></button>
			</div>
		</section>

		<section class="shop-fine-print" aria-label="<?php echo esc_attr( 'Session notes' ); ?>">
			<p><?php echo esc_html( 'Sessions are remote by default. Vancouver timezone. After checkout, you\'ll get scheduling details by email. No crypto pitches. No vague exposure deals.' ); ?></p>
		</section>
	</article>
</main>

<?php get_footer(); ?>
