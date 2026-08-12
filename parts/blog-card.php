<?php
/**
 * Blog post card
 *
 * @package SuzysMusicTheme
 */

$cat       = se_blog_primary_category();
$cat_class = $cat instanceof WP_Term ? se_blog_category_tone_class( $cat->slug ) : 'se-signal-log__cat--default';
?>
<article <?php post_class( 'se-signal-log__card' ); ?>>
	<div class="se-signal-log__card-meta">
		<?php if ( $cat instanceof WP_Term ) : ?>
			<span class="se-signal-log__cat <?php echo esc_attr( $cat_class ); ?>">
				<a href="<?php echo esc_url( get_category_link( $cat->term_id ) ); ?>"><?php echo esc_html( $cat->name ); ?></a>
			</span>
		<?php endif; ?>
		<time class="se-signal-log__date" datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( se_blog_format_date() ); ?></time>
	</div>

	<h2 class="se-signal-log__card-title">
		<a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a>
	</h2>

	<p class="se-signal-log__card-excerpt"><?php echo esc_html( se_blog_excerpt() ); ?></p>

	<a class="se-signal-log__card-link" href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( 'keep reading →' ); ?></a>
</article>
