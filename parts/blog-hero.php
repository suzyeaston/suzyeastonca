<?php
/**
 * Blog archive hero
 *
 * @package SuzysMusicTheme
 *
 * @var array<string, mixed> $args
 */

$args = isset( $args ) && is_array( $args ) ? $args : [];

$title      = isset( $args['title'] ) ? (string) $args['title'] : 'MEANWHILE';
$kicker     = isset( $args['kicker'] ) ? (string) $args['kicker'] : 'writing // east vancouver';
$show_intro = ! empty( $args['show_intro'] );
?>
<header class="se-signal-log__hero">
	<p class="se-signal-log__kicker pixel-font"><?php echo esc_html( $kicker ); ?></p>
	<h1 class="se-signal-log__title pixel-font"><?php echo esc_html( $title ); ?></h1>
	<?php if ( $show_intro ) : ?>
		<p class="se-signal-log__intro"><?php echo esc_html( se_blog_intro_copy() ); ?></p>
	<?php endif; ?>
</header>
