<?php
/**
 * Blog archive — Meanwhile posts index at /blog/
 *
 * @package SuzysMusicTheme
 */

get_header();

$hero_args = [
	'title'      => 'MEANWHILE',
	'kicker'     => 'writing // east vancouver',
	'show_intro' => true,
];
?>
<main id="main-content" class="se-signal-log se-signal-log--archive">
	<div class="se-signal-log__shell">
		<?php get_template_part( 'parts/blog', 'hero', $hero_args ); ?>

		<?php if ( have_posts() ) : ?>
			<section class="se-signal-log__feed" aria-label="<?php echo esc_attr( 'Meanwhile posts' ); ?>">
				<div class="se-signal-log__grid">
					<?php
					while ( have_posts() ) :
						the_post();
						get_template_part( 'parts/blog', 'card' );
					endwhile;
					?>
				</div>

				<?php get_template_part( 'parts/blog', 'pagination' ); ?>
			</section>
		<?php else : ?>
			<?php get_template_part( 'parts/blog', 'empty' ); ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
