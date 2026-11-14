<?php
/*
Template Name: Vancouver Music & Culture
*/
get_header();
$categories = suzy_vmc_categories();
$requested_category = isset( $_GET['vmc_category'] ) && is_string( $_GET['vmc_category'] ) ? (string) $_GET['vmc_category'] : 'all';
$category = isset( $categories[ $requested_category ] ) ? $requested_category : 'all';
$upcoming_all = suzy_vmc_events();
$history_all = suzy_vmc_history();
$matches = static fn ( $event ) => $category === 'all' || ( $event['category'] ?? '' ) === $category;
$upcoming = array_values( array_filter( $upcoming_all, $matches ) );
$years = [];
foreach ( $history_all as $event ) {
    $stamp = (int) $event['start'];
    $years[ wp_date( 'Y', $stamp, suzy_vmc_tz( $stamp ) ) ] = true;
}
$years = array_map( 'strval', array_keys( $years ) );
rsort( $years, SORT_NUMERIC );
$requested_year = isset( $_GET['vmc_year'] ) && is_scalar( $_GET['vmc_year'] ) ? (string) $_GET['vmc_year'] : '';
$year = in_array( $requested_year, $years, true ) ? $requested_year : ( $years[0] ?? '' );
$history = array_values( array_filter( $history_all, static function ( $event ) use ( $year, $matches ) {
    $stamp = (int) $event['start'];
    return $matches( $event ) && wp_date( 'Y', $stamp, suzy_vmc_tz( $stamp ) ) === $year;
} ) );
$months = array_fill( 1, 12, 0 );
foreach ( $history as $event ) {
    $stamp = (int) $event['start'];
    ++$months[ (int) wp_date( 'n', $stamp, suzy_vmc_tz( $stamp ) ) ];
}
$limit = 24;
$pages = max( 1, (int) ceil( count( $history ) / $limit ) );
$requested_page = isset( $_GET['vmc_page'] ) && is_scalar( $_GET['vmc_page'] ) ? (int) $_GET['vmc_page'] : 1;
$page = max( 1, min( $pages, $requested_page ) );
$historical_page = array_slice( $history, ( $page - 1 ) * $limit, $limit );
$base = home_url( '/vancouver-music-culture/' );
$format_date = static function ( array $event ): string {
    $stamp = (int) $event['start'];
    return wp_date( 'D, M j · g:i a', $stamp, suzy_vmc_tz( $stamp ) );
};
?>
<link rel="stylesheet" href="<?php echo esc_url( get_template_directory_uri() . '/assets/css/vancouver-music-culture.css' ); ?>">
<main id="main-content" class="vmc" role="main">
    <header class="vmc__hero">
        <p class="vmc__eyebrow pixel-font">VANCOUVER // HUMAN SIGNAL</p>
        <h1>Music, Culture &amp; Curiosity</h1>
        <p class="vmc__lead">Small rooms. Strange sounds. Big ideas. Find experimental concerts, music technology, poetry, literary nights, creative workshops, philosophy and independent arts around Vancouver.</p>
        <nav class="vmc__jumps" aria-label="Calendar sections">
            <a href="#upcoming">Upcoming happenings</a>
            <a href="#history">Historical archive</a>
            <a href="#sources">Explore the sources</a>
            <a href="<?php echo esc_url( home_url( '/vancouver-tech-events/' ) ); ?>">Tech calendar</a>
        </nav>
    </header>

    <form class="vmc__filters" action="<?php echo esc_url( $base ); ?>#upcoming" method="get">
        <label for="vmc-category">What are you curious about?</label>
        <select name="vmc_category" id="vmc-category">
            <?php foreach ( $categories as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>"<?php echo $category === $slug ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Show events</button>
    </form>

    <section id="upcoming" class="vmc__section" aria-labelledby="vmc-upcoming-title">
        <div class="vmc__section-head">
            <p class="vmc__eyebrow pixel-font">ON THE FREQUENCY</p>
            <h2 id="vmc-upcoming-title">Upcoming in Vancouver</h2>
            <p>Independent and community-oriented events first, not an arena tour guide. Entries link to the original organizers for tickets, changes and access information.</p>
        </div>
        <?php if ( empty( $upcoming ) ) : ?>
            <p class="vmc__empty">No confirmed dated listings for this filter yet. Browse the organizer calendars below. Empty results don't mean nothing is happening.</p>
        <?php else : ?>
            <ol class="vmc__events">
                <?php foreach ( $upcoming as $event ) : ?>
                    <li class="vmc__event">
                        <div class="vmc__event-top">
                            <time datetime="<?php echo esc_attr( wp_date( 'Y-m-d\TH:i:sP', (int) $event['start'], suzy_vmc_tz( (int) $event['start'] ) ) ); ?>"><?php echo esc_html( $format_date( $event ) ); ?></time>
                            <span class="vmc__chip"><?php echo esc_html( $categories[ $event['category'] ] ?? 'Culture' ); ?></span>
                        </div>
                        <h3><a href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['title'] ); ?></a></h3>
                        <p class="vmc__event-meta"><?php echo esc_html( $event['location'] ); ?> <span aria-hidden="true">·</span> <?php echo esc_html( $event['source'] ); ?></p>
                        <p class="vmc__origin"><?php echo ( $event['origin'] ?? '' ) === 'verified' ? 'Organizer-verified listing' : 'Calendar feed listing'; ?> · verify before attending</p>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>

    <section id="history" class="vmc__section vmc__section--archive" aria-labelledby="vmc-history-title">
        <div class="vmc__section-head">
            <p class="vmc__eyebrow pixel-font">AFTER THE APPLAUSE</p>
            <h2 id="vmc-history-title">Culture archive</h2>
            <p>Past listings are retained as sources refresh. Trends show what the calendar captured, not attendance or a complete record of Vancouver's cultural life.</p>
        </div>
        <?php if ( empty( $history_all ) ) : ?>
            <p class="vmc__empty">The archive is starting with a small number of source-verified events. New listings will accumulate over time.</p>
        <?php else : ?>
            <form class="vmc__archive-filters" action="<?php echo esc_url( $base ); ?>#history" method="get">
                <input type="hidden" name="vmc_category" value="<?php echo esc_attr( $category ); ?>">
                <label for="vmc-year">Archive year</label>
                <select id="vmc-year" name="vmc_year">
                    <?php foreach ( $years as $option_year ) : ?>
                        <option value="<?php echo esc_attr( $option_year ); ?>"<?php echo $option_year === $year ? ' selected' : ''; ?>><?php echo esc_html( $option_year ); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Browse history</button>
            </form>
            <p class="vmc__count"><?php echo (int) count( $history ); ?> known archived listings in <?php echo esc_html( $year ); ?>. Months with no captured records are <strong>unknown</strong>, not zero cultural activity.</p>
            <div class="vmc__months" role="img" aria-label="<?php echo esc_attr( 'Incomplete monthly archive for ' . $year . ', ' . $categories[ $category ] ); ?>">
                <?php $peak = max( 1, max( $months ) ); ?>
                <?php foreach ( $months as $month => $count ) : ?>
                    <div class="vmc__month">
                        <span class="vmc__month-bar" style="height:<?php echo (int) round( 100 * $count / $peak ); ?>%" title="<?php echo esc_attr( $count . ' captured listings' ); ?>"></span>
                        <span><?php echo esc_html( ( new DateTimeImmutable( '2026-' . sprintf( '%02d', $month ) . '-01' ) )->format( 'M' ) ); ?></span>
                        <small><?php echo $count > 0 ? (int) $count : '–'; ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
            <ol class="vmc__events vmc__events--past">
                <?php foreach ( $historical_page as $event ) : ?>
                    <li class="vmc__event">
                        <div class="vmc__event-top">
                            <time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', (int) $event['start'], suzy_vmc_tz( (int) $event['start'] ) ) ); ?>"><?php echo esc_html( $format_date( $event ) ); ?></time>
                            <span class="vmc__chip"><?php echo esc_html( $categories[ $event['category'] ] ?? 'Culture' ); ?></span>
                        </div>
                        <h3><a href="<?php echo esc_url( $event['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $event['title'] ); ?></a></h3>
                        <p class="vmc__event-meta"><?php echo esc_html( $event['location'] ); ?> · <?php echo esc_html( $event['source'] ); ?></p>
                        <p class="vmc__origin"><?php echo ( $event['origin'] ?? '' ) === 'verified' ? 'Organizer-verified historical listing' : 'Previously captured feed listing'; ?> · not proof the event took place</p>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ( $pages > 1 ) : ?>
                <nav class="vmc__pagination" aria-label="Archive pagination">
                    <?php if ( $page > 1 ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'vmc_category' => $category, 'vmc_year' => $year, 'vmc_page' => $page - 1 ], $base ) . '#history' ); ?>">Newer listings</a><?php endif; ?>
                    <span>Page <?php echo (int) $page; ?> of <?php echo (int) $pages; ?></span>
                    <?php if ( $page < $pages ) : ?><a href="<?php echo esc_url( add_query_arg( [ 'vmc_category' => $category, 'vmc_year' => $year, 'vmc_page' => $page + 1 ], $base ) . '#history' ); ?>">Older listings</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section id="sources" class="vmc__section" aria-labelledby="vmc-sources-title">
        <div class="vmc__section-head">
            <p class="vmc__eyebrow pixel-font">FOLLOW THE VENUES</p>
            <h2 id="vmc-sources-title">Where the good stuff happens</h2>
            <p>There is no single comprehensive feed for grassroots culture. These independent community calendars deserve a look even if their listings cannot always be automatically imported.</p>
        </div>
        <ul class="vmc__directory">
            <?php foreach ( suzy_vmc_directory() as $source ) : ?>
                <li><a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $source['label'] ); ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if ( current_user_can( 'manage_options' ) ) :
            $health = get_option( 'suzy_vmc_source_status_v1', [] ); ?>
            <details class="vmc__health"><summary>Admin: source collection status</summary>
                <p>Last collection: <?php echo ! empty( $health['time'] ) ? esc_html( wp_date( 'M j Y g:i a', (int) $health['time'], suzy_vmc_tz( (int) $health['time'] ) ) ) : 'Not run yet (WP-Cron pending)'; ?></p>
                <ul><?php foreach ( ( $health['sources'] ?? [] ) as $item ) : ?>
                    <li><?php echo esc_html( $item['label'] ?? '' ); ?>: <?php echo esc_html( $item['status'] ?? '' ); ?> (<?php echo (int) ( $item['count'] ?? 0 ); ?>)</li>
                <?php endforeach; ?></ul>
            </details>
        <?php endif; ?>
    </section>
    <p class="vmc__footnote">Independent, incomplete, and evolving. Automated collection uses several public organizer feeds, but websites may block access or change formats. Curated listings are dated against original event pages; always double-check schedule, accessibility and ticket information with the host.</p>
</main>
<?php get_footer(); ?>
