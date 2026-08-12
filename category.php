<?php
/**
 * Category archive — Meanwhile
 *
 * @package SuzysMusicTheme
 */

get_header();

$term = get_queried_object();
$name = ( $term instanceof WP_Term ) ? $term->name : 'Category';

$hero_args = [
	'title'      => $name,
	'kicker'     => 'category filter',
	'show_intro' => false,
];
?>
<main id="main-content" class="se-signal-log se-signal-log--archive se-signal-log--category">
	<div class="se-signal-log__shell">
		<?php get_template_part( 'parts/blog', 'hero', $hero_args ); ?>

		<p class="se-signal-log__filter-note">
			<a class="se-signal-log__back-link" href="<?php echo esc_url( se_blog_archive_url() ); ?>"><?php echo esc_html( '← all posts' ); ?></a>
		</p>

		<?php if ( have_posts() ) : ?>
			<section class="se-signal-log__feed" aria-label="<?php echo esc_attr( 'Category posts' ); ?>">
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
