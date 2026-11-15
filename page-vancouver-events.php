<?php
/*
Template Name: Vancouver Events
*/
get_header();

$filters = suzy_yvr_events_filters( $_GET );
$events_all = suzy_yvr_events_records( $filters['view'] );
$years = 'archive' === $filters['view'] ? suzy_yvr_events_archive_years( $events_all ) : [];
if ( 'archive' === $filters['view'] && ! in_array( $filters['year'], $years, true ) ) {
    $filters['year'] = $years[0] ?? '';
}
$events_filtered = suzy_yvr_events_filter( $events_all, $filters );
$per_page = 24;
$total = count( $events_filtered );
$pages = max( 1, (int) ceil( $total / $per_page ) );
$page = min( $filters['page'], $pages );
$events_shown = array_slice( $events_filtered, ( $page - 1 ) * $per_page, $per_page );
$topics = suzy_yvr_events_topics();
$base = home_url( '/vancouver-events/' );
$base_args = [
    'yve_view' => $filters['view'],
    'yve_track' => $filters['track'],
    'yve_topic' => $filters['topic'],
    'yve_q' => $filters['q'],
];
if ( 'archive' === $filters['view'] ) {
    $base_args['year'] = $filters['year'];
    $base_args['month'] = $filters['month'];
} else {
    $base_args['period'] = $filters['period'];
}
$link_for_page = static function ( int $number ) use ( $base_args, $base ): string {
    return add_query_arg( array_merge( $base_args, [ 'yve_page' => $number ] ), $base ) . '#events';
};
$months = array_fill( 1, 12, 0 );
if ( 'archive' === $filters['view'] ) {
    foreach ( $events_filtered as $event ) {
        $start = (int) $event['start'];
        ++$months[ (int) wp_date( 'n', $start, suzy_vte_vancouver_timezone( $start ) ) ];
    }
}
?>
<main id="main-content" class="yve" role="main">
    <header class="yve__hero">
        <p class="yve__kicker pixel-font">VANCOUVER // ALL FREQUENCIES</p>
        <h1>Vancouver Events</h1>
        <p class="yve__lead">One city, many signals. Music, AI, poetry, indie gigs, developer meetups, sound art, philosophy, experimental cinema and the conversations between them.</p>
        <p class="yve__sublead">Independent calendars, together. Listings remain linked to their original organizers; this is a discovery index, not proof that an event is happening or was attended.</p>
    </header>

    <nav class="yve__modes" aria-label="Browse upcoming or historical events">
        <a href="<?php echo esc_url( add_query_arg( [ 'yve_view' => 'upcoming', 'yve_track' => $filters['track'], 'yve_topic' => $filters['topic'], 'yve_q' => $filters['q'] ], $base ) . '#events' ); ?>"<?php echo 'upcoming' === $filters['view'] ? ' aria-current="page"' : ''; ?>>Upcoming events</a>
        <a href="<?php echo esc_url( add_query_arg( [ 'yve_view' => 'archive', 'yve_track' => $filters['track'], 'yve_topic' => $filters['topic'], 'yve_q' => $filters['q'] ], $base ) . '#events' ); ?>"<?php echo 'archive' === $filters['view'] ? ' aria-current="page"' : ''; ?>>Historical archive</a>
    </nav>

    <section class="yve__filter-panel" aria-labelledby="yve-filter-heading">
        <div class="yve__filter-heading">
            <h2 id="yve-filter-heading" class="pixel-font">TUNE THE DIAL</h2>
            <p>Find an event by scene, topic, date or venue. Filters combine, and no sign-in is needed.</p>
        </div>
        <form action="<?php echo esc_url( $base ); ?>" class="yve__filters" method="get">
            <input type="hidden" name="yve_view" value="<?php echo esc_attr( $filters['view'] ); ?>">
            <div class="yve__field">
                <label for="yve-track">Scene</label>
                <select id="yve-track" name="yve_track">
                    <?php foreach ( [ 'all' => 'Everything', 'tech' => 'Tech & engineering', 'culture' => 'Music & culture' ] as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>"<?php echo $filters['track'] === $value ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="yve__field">
                <label for="yve-topic">Topic</label>
                <select id="yve-topic" name="yve_topic">
                    <?php foreach ( $topics as $value => $label ) : ?>
                        <option value="<?php echo esc_attr( $value ); ?>"<?php echo $filters['topic'] === $value ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ( 'archive' === $filters['view'] ) : ?>
                <div class="yve__field yve__field--small">
                    <label for="yve-year">Year</label>
                    <select id="yve-year" name="yve_year">
                        <?php foreach ( $years as $year ) : ?>
                            <option value="<?php echo esc_attr( $year ); ?>"<?php echo $filters['year'] === $year ? ' selected' : ''; ?>><?php echo esc_html( $year ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="yve__field yve__field--small">
                    <label for="yve-month">Month</label>
                    <select id="yve-month" name="yve_month">
                        <option value="all">All months</option>
                        <?php foreach ( range( 1, 12 ) as $month ) : ?>
                            <option value="<?php echo (int) $month; ?>"<?php echo (string) $month === $filters['month'] ? ' selected' : ''; ?>><?php echo esc_html( wp_date( 'F', strtotime( sprintf( '2026-%02d-01 12:00 UTC', $month ) ) ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else : ?>
                <div class="yve__field yve__field--small">
                    <label for="yve-period">When</label>
                    <select id="yve-period" name="yve_period">
                        <?php foreach ( [ 'all' => 'All upcoming', '7' => 'Next 7 days', '30' => 'Next 30 days', '90' => 'Next 90 days' ] as $value => $label ) : ?>
                            <option value="<?php echo esc_attr( $value ); ?>"<?php echo $filters['period'] === (string) $value ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="yve__field yve__field--search">
                <label for="yve-search">Search events, venues &amp; organizers</label>
                <input id="yve-search" name="yve_q" type="search" maxlength="80" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Try: jazz, AI, 8EAST, poetry">
            </div>
            <div class="yve__filter-actions">
                <button type="submit">Apply filters</button>
                <a href="<?php echo esc_url( add_query_arg( [ 'yve_view' => $filters['view'] ], $base ) ); ?>">Clear</a>
            </div>
        </form>
    </section>

    <section id="events" class="yve__results" aria-labelledby="yve-events-title">
        <header class="yve__results-header">
            <p class="yve__kicker pixel-font"><?php echo 'archive' === $filters['view'] ? 'PAST SIGNALS' : 'WHAT IS HAPPENING'; ?></p>
            <h2 id="yve-events-title"><?php echo 'archive' === $filters['view'] ? 'Previously listed events' : 'Upcoming across Vancouver'; ?></h2>
            <p class="yve__results-count" role="status"><?php echo (int) $total; ?> matching <?php echo 1 === $total ? 'listing' : 'listings'; ?> from <?php echo (int) count( $events_all ); ?> known <?php echo 'archive' === $filters['view'] ? 'past' : 'upcoming'; ?> events.<?php if ( $total > $per_page ) : ?> Showing <?php echo (int) ( ( $page - 1 ) * $per_page + 1 ); ?>–<?php echo (int) min( $total, $page * $per_page ); ?>.<?php endif; ?></p>
        </header>
        <?php if ( 'archive' === $filters['view'] ) : ?>
            <p class="yve__caveat">This archive counts captured listings, not everything that happened in Vancouver. A missing month means incomplete coverage, <strong>not zero events</strong>.</p>
            <?php if ( $total > 0 ) : ?>
                <?php $max_month = max( 1, max( $months ) ); ?>
                <div class="yve__histogram" role="img" aria-label="Known event listings by month in the selected archive year and filters">
                    <?php foreach ( $months as $month => $count ) : ?>
                        <div class="yve__month">
                            <span class="yve__month-bar" style="height: <?php echo (int) round( 70 * $count / $max_month ); ?>px"></span>
                            <span><?php echo esc_html( wp_date( 'M', strtotime( sprintf( '2026-%02d-01 12:00 UTC', $month ) ) ) ); ?></span>
                            <small><?php echo $count ? (int) $count : '–'; ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ( empty( $events_shown ) ) : ?>
            <p class="yve__empty">No captured listings match these filters. Try a different topic or time window. That doesn't necessarily mean there are no events in town.</p>
        <?php else : ?>
            <ol class="yve__event-list">
                <?php foreach ( $events_shown as $event ) :
                    $start = (int) $event['start'];
                    $timezone = suzy_vte_vancouver_timezone( $start );
                    $end = (int) $event['end'];
                    $crosses_days = $end > $start && wp_date( 'Y-m-d', $end - 1, suzy_vte_vancouver_timezone( $end - 1 ) ) !== wp_date( 'Y-m-d', $start, $timezone );
                    ?>
                    <li class="yve__event">
                        <div class="yve__event-top">
                            <time datetime="<?php echo esc_attr( wp_date( 'c', $start, $timezone ) ); ?>"><?php echo esc_html( wp_date( 'D, M j · g:i a', $start, $timezone ) ); ?> <?php echo $start >= 1772964000 ? 'PCT' : esc_html( wp_date( 'T', $start, $timezone ) ); ?></time>
                            <div class="yve__tags">
                                <?php foreach ( $event['tracks'] as $track ) : ?>
                                    <span class="yve__tag yve__tag--<?php echo esc_attr( $track ); ?>"><?php echo 'tech' === $track ? 'Tech' : 'Music & Culture'; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <h3><a href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['title'] ); ?></a></h3>
                        <?php if ( $crosses_days ) : ?>
                            <p class="yve__multi-day">Through <?php echo esc_html( wp_date( 'M j, Y', $end - 1, suzy_vte_vancouver_timezone( $end - 1 ) ) ); ?></p>
                        <?php endif; ?>
                        <p class="yve__event-meta"><?php echo esc_html( $event['location'] ?: 'Location details with organizer' ); ?> <span aria-hidden="true">·</span> <?php echo esc_html( $event['source'] ); ?></p>
                        <div class="yve__topic-tags">
                            <?php foreach ( $event['topics'] as $topic ) : ?>
                                <span><?php echo esc_html( $topics[ $topic ] ?? $topic ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php if ( ! empty( $event['time_note'] ) ) : ?><p class="yve__time-note"><?php echo esc_html( $event['time_note'] ); ?></p><?php endif; ?>
                        <p class="yve__provenance"><?php echo 'verified' === $event['origin'] ? 'Original organizer listing' : 'Previously captured calendar listing'; ?> · confirm details before attending</p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
        <?php if ( $pages > 1 ) : ?>
            <nav class="yve__pagination" aria-label="Event results pages">
                <?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( $link_for_page( $page - 1 ) ); ?>">Previous page</a><?php endif; ?>
                <span>Page <?php echo (int) $page; ?> of <?php echo (int) $pages; ?></span>
                <?php if ( $page < $pages ) : ?><a href="<?php echo esc_url( $link_for_page( $page + 1 ) ); ?>">Next page</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <footer class="yve__footer">
        <h2>Go deeper</h2>
        <p>The original calendars remain available for focused research, venue browsing and source diagnostics. This hub reads their records without changing their collectors.</p>
        <div class="yve__sources">
            <a href="<?php echo esc_url( home_url( '/vancouver-tech-events/' ) ); ?>">Tech sources &amp; detailed archive</a>
            <a href="<?php echo esc_url( home_url( '/vancouver-music-culture/#sources' ) ); ?>">Music &amp; Culture venue directory</a>
        </div>
        <p class="yve__fine-print">Coverage is incomplete. Some venue calendars block automated access, community sources can be wrong, and doors times may differ from performance times. Please check each organizer.</p>
    </footer>
</main>
<?php get_footer(); ?>
