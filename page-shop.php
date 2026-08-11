<?php
/*
Template Name: Shop
*/
get_header();

$products = se_get_shop_products();
?>
<main id="main-content" class="shop-page">
	<article class="page-content shop-content">
		<section class="shop-hero" aria-labelledby="shop-title">
			<p class="shop-kicker shop-eyebrow"><?php echo esc_html( 'consulting // vancouver // remote' ); ?></p>
			<h1 id="shop-title" class="retro-title glow-lite"><?php echo esc_html( 'Hire Suzy' ); ?></h1>
			<p class="shop-lead"><?php echo esc_html( 'Focused technical help without turning every problem into a six-week engagement.' ); ?></p>
			<p class="shop-hero-subcopy"><?php echo esc_html( 'Debug something broken, map an AI or automation workflow, or go deep on the complicated system problem.' ); ?></p>
			<div class="shop-hero-terminal" aria-hidden="true">
				<p class="shop-hero-terminal__line"><span class="shop-hero-terminal__sigil">&gt;</span> <?php echo esc_html( 'AVAILABLE · 3 FIXED-PRICE SESSIONS' ); ?></p>
				<p class="shop-hero-terminal__line shop-hero-terminal__line--muted"><?php echo esc_html( '30 min debug · 45 min workflow · 90 min deep dive · CAD' ); ?></p>
			</div>
		</section>

		<section id="shop-catalog" class="shop-catalog" aria-labelledby="shop-catalog-title">
			<h2 id="shop-catalog-title"><?php echo esc_html( 'Available sessions' ); ?></h2>
			<p class="shop-catalog__lede"><?php echo esc_html( 'Pick the size of the problem. You can see the details before booking.' ); ?></p>
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

		<section class="shop-custom-work" aria-labelledby="shop-custom-title">
			<h2 id="shop-custom-title"><?php echo esc_html( 'Need more than a session?' ); ?></h2>
			<p><?php echo esc_html( 'For larger builds, consulting, technical roles, creative technology, or work that needs a real scope first, use the bigger-project door.' ); ?></p>
			<div class="shop-custom-work__actions">
				<a class="pixel-button shop-custom-work__cta" href="<?php echo esc_url( home_url( '/work-with-suzy/' ) ); ?>"><?php echo esc_html( 'Work with Suzy' ); ?></a>
				<button class="pixel-button shop-custom-work__contact" type="button" data-contact-trigger aria-haspopup="dialog" aria-controls="contact-suzy-modal"><?php echo esc_html( 'Send a note' ); ?></button>
			</div>
		</section>

		<section class="shop-fine-print" aria-label="<?php echo esc_attr( 'Session notes' ); ?>">
			<p><?php echo esc_html( 'Sessions are remote by default and run on Vancouver time. Checkout is handled through Buy Me a Coffee; scheduling details follow by email.' ); ?></p>
		</section>
	</article>
</main>

<?php get_footer(); ?>
