<?php
/**
 * Single post — Meanwhile
 *
 * @package SuzysMusicTheme
 */

get_header();

while ( have_posts() ) :
	the_post();

	$cat       = se_blog_primary_category();
	$cat_class = $cat instanceof WP_Term ? se_blog_category_tone_class( $cat->slug ) : 'se-signal-log__cat--default';
	$prev_post = get_previous_post();
	$next_post = get_next_post();
	?>
<main id="main-content" class="se-signal-log se-signal-log--single">
	<div class="se-signal-log__shell">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'se-signal-log__article' ); ?>>
			<header class="se-signal-log__article-head">
				<p class="se-signal-log__article-kicker">
					<a class="se-signal-log__back-link" href="<?php echo esc_url( se_blog_archive_url() ); ?>"><?php echo esc_html( 'meanwhile' ); ?></a>
					<span class="se-signal-log__article-sep" aria-hidden="true">/</span>
					<span class="se-signal-log__article-date"><?php echo esc_html( se_blog_format_date() ); ?></span>
				</p>

				<?php if ( $cat instanceof WP_Term ) : ?>
					<p class="se-signal-log__cat <?php echo esc_attr( $cat_class ); ?>">
						<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
					</p>
				<?php endif; ?>

				<h1 class="se-signal-log__article-title"><?php the_title(); ?></h1>
			</header>

			<div class="se-signal-log__article-body entry-content">
				<?php the_content(); ?>
			</div>

			<footer class="se-signal-log__article-foot">
				<p class="se-signal-log__article-byline"><?php echo esc_html( 'Suzy Easton · East Vancouver' ); ?></p>
			</footer>
		</article>

		<nav class="se-signal-log__post-nav" aria-label="<?php echo esc_attr( 'Post navigation' ); ?>">
			<?php if ( $prev_post instanceof WP_Post ) : ?>
				<a class="se-signal-log__post-nav-link se-signal-log__post-nav-link--prev" href="<?php echo esc_url( get_permalink( $prev_post ) ); ?>">
					<span class="se-signal-log__post-nav-label"><?php echo esc_html( '← previous' ); ?></span>
					<span class="se-signal-log__post-nav-title"><?php echo esc_html( get_the_title( $prev_post ) ); ?></span>
				</a>
			<?php else : ?>
				<span class="se-signal-log__post-nav-link se-signal-log__post-nav-link--empty" aria-hidden="true"></span>
			<?php endif; ?>

			<?php if ( $next_post instanceof WP_Post ) : ?>
				<a class="se-signal-log__post-nav-link se-signal-log__post-nav-link--next" href="<?php echo esc_url( get_permalink( $next_post ) ); ?>">
					<span class="se-signal-log__post-nav-label"><?php echo esc_html( 'next →' ); ?></span>
					<span class="se-signal-log__post-nav-title"><?php echo esc_html( get_the_title( $next_post ) ); ?></span>
				</a>
			<?php else : ?>
				<span class="se-signal-log__post-nav-link se-signal-log__post-nav-link--empty" aria-hidden="true"></span>
			<?php endif; ?>
		</nav>
	</div>
</main>
	<?php
endwhile;

get_footer();
