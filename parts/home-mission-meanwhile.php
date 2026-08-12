<?php
/**
 * Homepage mission-select — Meanwhile field terminal card
 *
 * @package SuzysMusicTheme
 */

if ( ! function_exists( 'se_blog_home_mission_data' ) ) {
	return;
}

$mission = se_blog_home_mission_data();
$blog_url = $mission['blog_url'];
$has_post = ! empty( $mission['has_post'] );
?>
<article
	class="home-project-card selected-work__card home-mission-card home-mission-card--meanwhile<?php echo $has_post ? ' home-mission-card--meanwhile-live' : ''; ?>"
	aria-labelledby="home-mission-meanwhile-title"
>
	<p class="home-mission-card__label pixel-font"><?php echo esc_html( 'field notes // east van' ); ?></p>
	<h3 id="home-mission-meanwhile-title" class="pixel-font"><?php echo esc_html( 'Meanwhile' ); ?></h3>
	<p class="home-mission-meanwhile__tagline"><?php echo esc_html( 'Field notes from East Vancouver — what I\'m building, questioning, and thinking out loud.' ); ?></p>

	<div class="home-mission-meanwhile__terminal" role="status" aria-live="polite">
		<div class="home-mission-meanwhile__scan" aria-hidden="true"></div>
		<div class="home-mission-meanwhile__shell">
			<div class="home-mission-meanwhile__shell-bar pixel-font">
				<span class="home-mission-meanwhile__status">
					<span class="home-mission-meanwhile__led" aria-hidden="true"></span>
					<span class="home-mission-meanwhile__status-label"><?php echo esc_html( $mission['status_label'] ); ?></span>
				</span>
				<span class="home-mission-meanwhile__path">meanwhile@yvr — tx --latest</span>
			</div>
			<div class="home-mission-meanwhile__body">
				<p class="home-mission-meanwhile__prompt pixel-font">
					<span class="home-mission-meanwhile__sigil" aria-hidden="true">$</span>
					<span class="home-mission-meanwhile__cmd">tail -1 ./field-log</span>
					<span class="home-mission-meanwhile__caret" aria-hidden="true"></span>
				</p>

				<?php if ( $has_post ) : ?>
					<p class="home-mission-meanwhile__entry-label pixel-font"><?php echo esc_html( 'LATEST ENTRY' ); ?></p>
					<div class="home-mission-meanwhile__entry">
						<?php if ( $mission['post_date'] ) : ?>
							<time class="home-mission-meanwhile__date" datetime="<?php echo esc_attr( $mission['post_date_iso'] ); ?>"><?php echo esc_html( $mission['post_date'] ); ?></time>
						<?php endif; ?>
						<?php if ( $mission['category_name'] ) : ?>
							<span class="home-mission-meanwhile__cat pixel-font"><?php echo esc_html( $mission['category_name'] ); ?></span>
						<?php endif; ?>
						<a class="home-mission-meanwhile__entry-title" href="<?php echo esc_url( $mission['post_url'] ); ?>">
							<?php echo esc_html( $mission['post_title'] ); ?>
						</a>
					</div>
				<?php else : ?>
					<p class="home-mission-meanwhile__empty-label pixel-font"><?php echo esc_html( 'NO CARRIER' ); ?></p>
					<p class="home-mission-meanwhile__empty-copy"><?php echo esc_html( 'Frequency open. First transmission pending.' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="home-mission-meanwhile__actions">
		<a class="pixel-button home-mission-meanwhile__cta" href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( 'Open channel' ); ?></a>
		<?php if ( $has_post ) : ?>
			<a class="home-mission-meanwhile__latest-link" href="<?php echo esc_url( $mission['post_url'] ); ?>"><?php echo esc_html( 'read latest →' ); ?></a>
		<?php endif; ?>
	</div>
</article>
