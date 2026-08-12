<?php
/**
 * Homepage — latest Signal Log notes
 *
 * @package SuzysMusicTheme
 */

$blog_url = se_blog_archive_url();
$query    = se_blog_latest_query( 3 );

if ( ! $query->have_posts() ) {
	return;
}
?>
<section class="se-signal-log-home" aria-labelledby="home-signal-log-title">
	<div class="se-signal-log-home__shell">
		<p class="se-signal-log-home__kicker pixel-font"><?php echo esc_html( 'latest notes' ); ?></p>
		<h2 id="home-signal-log-title" class="se-signal-log-home__title pixel-font"><?php echo esc_html( 'SIGNAL LOG' ); ?></h2>
		<p class="se-signal-log-home__intro"><?php echo esc_html( se_blog_intro_copy() ); ?></p>

		<div class="se-signal-log-home__grid">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				$cat       = se_blog_primary_category();
				$cat_class = $cat instanceof WP_Term ? se_blog_category_tone_class( $cat->slug ) : 'se-signal-log__cat--default';
				?>
				<article <?php post_class( 'se-signal-log-home__card' ); ?>>
					<div class="se-signal-log-home__card-meta">
						<?php if ( $cat instanceof WP_Term ) : ?>
							<span class="se-signal-log__cat <?php echo esc_attr( $cat_class ); ?>"><?php echo esc_html( $cat->name ); ?></span>
						<?php endif; ?>
						<time class="se-signal-log__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( se_blog_format_date() ); ?></time>
					</div>
					<h3 class="se-signal-log-home__card-title">
						<a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
					</h3>
					<p class="se-signal-log-home__card-excerpt"><?php echo esc_html( se_blog_excerpt( get_the_ID(), 24 ) ); ?></p>
					<a class="se-signal-log-home__card-link" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( 'read transmission →' ); ?></a>
				</article>
			<?php endwhile; ?>
		</div>

		<p class="se-signal-log-home__more">
			<a class="se-signal-log-home__archive-link" href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( 'open signal log →' ); ?></a>
		</p>
	</div>
</section>
<?php
wp_reset_postdata();
