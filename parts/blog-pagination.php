<?php
/**
 * Blog pagination
 *
 * @package SuzysMusicTheme
 */

global $wp_query;

$pagination = paginate_links(
	[
		'total'     => max( 1, (int) $wp_query->max_num_pages ),
		'current'   => max( 1, (int) get_query_var( 'paged' ) ),
		'mid_size'  => 1,
		'prev_text' => '← earlier',
		'next_text' => 'later →',
		'type'      => 'array',
	]
);

if ( empty( $pagination ) || ! is_array( $pagination ) ) {
	return;
}
?>
<nav class="se-signal-log__pagination" aria-label="<?php echo esc_attr( 'Blog pagination' ); ?>">
	<ul class="se-signal-log__pagination-list">
		<?php foreach ( $pagination as $link ) : ?>
			<li class="se-signal-log__pagination-item"><?php echo wp_kses_post( $link ); ?></li>
		<?php endforeach; ?>
	</ul>
</nav>
