<?php
/**
 * Homepage teaser — upcoming Vancouver tech events.
 */

if ( ! function_exists( 'suzy_get_vancouver_tech_events' ) ) {
	return;
}

$vancouver_events_payload = suzy_get_vancouver_tech_events();
$vancouver_events         = [];
if ( isset( $vancouver_events_payload['events'] ) && is_array( $vancouver_events_payload['events'] ) ) {
	$vancouver_events = $vancouver_events_payload['events'];
}
$vancouver_events_top = array_slice( $vancouver_events, 0, 3 );
$events_url           = home_url( '/vancouver-tech-events/' );
?>
<section class="vancouver-tech-home crt-block" aria-labelledby="vancouver-tech-home-title">
	<p class="home-section-kicker pixel-font"><?php echo esc_html( 'yvr calendar' ); ?></p>
	<h2 id="vancouver-tech-home-title" class="pixel-font"><?php echo esc_html( 'VANCOUVER TECH EVENTS' ); ?></h2>
	<p class="vancouver-tech-home__intro"><?php echo esc_html( 'Meetup tabs multiply. One feed keeps them honest.' ); ?></p>

	<?php if ( empty( $vancouver_events_top ) ) : ?>
		<p><?php echo esc_html( 'Nothing upcoming in the cache. Full calendar still loads.' ); ?></p>
	<?php else : ?>
		<ul class="vancouver-tech-home__list">
			<?php foreach ( $vancouver_events_top as $event ) : ?>
				<?php
				$event_start = isset( $event['start'] ) ? (int) $event['start'] : 0;
				$event_url   = isset( $event['url'] ) ? (string) $event['url'] : '';
				?>
				<li class="vancouver-tech-home__item">
					<p class="vancouver-tech-home__time">
						<?php echo esc_html( $event_start > 0 ? wp_date( 'D, M j • g:i A T', $event_start ) : 'Date/time TBD' ); ?>
					</p>
					<a href="<?php echo esc_url( $event_url ); ?>" target="_blank" rel="noopener noreferrer" class="vancouver-tech-home__title">
						<?php echo esc_html( $event['title'] ?? 'Upcoming event' ); ?>
					</a>
					<p class="vancouver-tech-home__meta">
						<?php if ( ! empty( $event['location'] ) ) : ?>
							<span><?php echo esc_html( (string) $event['location'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $event['source'] ) ) : ?>
							<span class="vancouver-tech-home__source"><?php echo esc_html( (string) $event['source'] ); ?></span>
						<?php endif; ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<a class="pixel-button vancouver-tech-home__cta" href="<?php echo esc_url( $events_url ); ?>"><?php echo esc_html( 'Open full calendar' ); ?></a>
</section>
