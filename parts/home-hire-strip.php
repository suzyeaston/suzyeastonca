<?php
$shop_url = se_preserve_utm_url( home_url( '/shop/' ) );
$work_url = home_url( '/work-with-suzy/' );
$projects = home_url( '/projects/' );
?>
<section class="home-hire-strip" aria-label="<?php echo esc_attr( 'Hire Suzy and book consulting' ); ?>">
	<p class="home-section-kicker pixel-font"><?php echo esc_html( 'available // consulting' ); ?></p>
	<p class="home-hire-strip__location pixel-font"><?php echo esc_html( 'Based in East Vancouver · working across Vancouver and remotely' ); ?></p>
	<h2 class="home-hire-strip__title"><?php echo esc_html( 'Need another brain on it?' ); ?></h2>
	<p class="home-hire-strip__lede"><?php echo esc_html( 'AI, automation, systems and creative technology consulting. Debug something broken, map a workflow, or dig into the complicated system problem. Available for Vancouver projects and remote work now.' ); ?></p>
	<nav class="home-hire-strip__actions" aria-label="<?php echo esc_attr( 'Consulting links' ); ?>">
		<a class="pixel-button home-hire-strip__cta home-hire-strip__cta--primary" href="<?php echo esc_url( $shop_url ); ?>" data-hire-cta data-hire-cta-label="home_view_sessions"><?php echo esc_html( 'View sessions' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $work_url ); ?>" data-hire-cta data-hire-cta-label="home_bigger_project"><?php echo esc_html( 'Bigger project' ); ?></a>
		<a class="pixel-button home-hire-strip__cta" href="<?php echo esc_url( $projects ); ?>"><?php echo esc_html( 'Enter the lab' ); ?></a>
	</nav>
	<p class="home-hire-strip__footnote">
		<?php echo esc_html( '30 min debug · 45 min workflow · 90 min deep dive' ); ?>
	</p>
</section>
