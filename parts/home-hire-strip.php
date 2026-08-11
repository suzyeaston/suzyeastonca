<?php
$shop_url = home_url( '/shop/' );
$work_url = home_url( '/work-with-suzy/' );
$projects = home_url( '/projects/' );
?>
<section class="home-hire-strip" aria-label="<?php echo esc_attr( 'Hire Suzy and book consulting' ); ?>">
	<p class="home-section-kicker pixel-font"><?php echo esc_html( 'available // consulting' ); ?></p>
	<h2 class="home-hire-strip__title"><?php echo esc_html( 'Need another brain on it?' ); ?></h2>
	<p class="home-hire-strip__lede"><?php echo esc_html( 'Debug something broken, map an automation, or dig into the complicated system problem. Fixed-price technical sessions, remote from Vancouver.' ); ?></p>
	<nav class="home-hire-strip__actions" aria-label="<?php echo esc_attr( 'Consulting links' ); ?>">
		<a class="pixel-button home-hire-strip__cta home-hire-strip__cta--primary" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( 'View sessions' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $work_url ); ?>"><?php echo esc_html( 'Bigger project' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $projects ); ?>"><?php echo esc_html( 'Enter the lab' ); ?></a>
	</nav>
	<p class="home-hire-strip__footnote">
		<?php echo esc_html( '30 min debug · 45 min workflow · 90 min deep dive' ); ?>
	</p>
</section>
