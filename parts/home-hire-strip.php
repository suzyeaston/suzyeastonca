<?php
$shop_url  = home_url( '/shop/' );
$lab_url   = home_url( '/work-with-suzy/' );
$projects  = home_url( '/projects/' );
?>
<section class="home-hire-strip crt-block" aria-label="<?php echo esc_attr( 'Hire Suzy and shop consulting' ); ?>">
	<p class="home-section-kicker pixel-font"><?php echo esc_html( 'brain rental' ); ?></p>
	<h2 class="home-hire-strip__title pixel-font"><?php echo esc_html( 'Buy my brain' ); ?></h2>
	<p class="home-hire-strip__lede"><?php echo esc_html( 'Debug the bug. Automate the ritual. Go deep on the mess. Fixed-price sessions — no corporate services deck.' ); ?></p>
	<nav class="home-hire-strip__actions" aria-label="<?php echo esc_attr( 'Hire and shop links' ); ?>">
		<a class="pixel-button home-hire-strip__cta home-hire-strip__cta--primary" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( 'HIRE SUZY' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( 'SHOP' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $projects ); ?>"><?php echo esc_html( 'ENTER THE LAB' ); ?></a>
	</nav>
	<p class="home-hire-strip__footnote pixel-font">
		<a href="<?php echo esc_url( $lab_url ); ?>"><?php echo esc_html( 'bigger project?' ); ?></a>
		<span aria-hidden="true"> · </span>
		<a href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( 'view sessions' ); ?></a>
	</p>
</section>
