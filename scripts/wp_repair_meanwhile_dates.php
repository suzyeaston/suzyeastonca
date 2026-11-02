<?php
/**
 * One-shot, read-after-write repair for the November 2026 Meanwhile post dates.
 *
 * Bootstrap WordPress exactly once to avoid triggering the host's CLI rate
 * limiter during back-to-back wp-load calls. Doesn't change post content.
 */
if ( PHP_SAPI !== 'cli' || 2 !== $argc || ! is_file( $argv[1] ) ) {
    fwrite( STDERR, 'CLI manifest required.' . PHP_EOL );
    exit( 1 );
}
$payload = json_decode( file_get_contents( $argv[1] ), true );
if ( ! is_array( $payload ) || ! isset( $payload['updates'] )
    || ! is_array( $payload['updates'] ) || 2 !== count( $payload['updates'] ) ) {
    fwrite( STDERR, 'Expected exactly two approved post updates.' . PHP_EOL );
    exit( 1 );
}
$wp_root = dirname( __DIR__, 2 ) . '/public_html';
if ( ! is_file( $wp_root . '/wp-load.php' ) ) {
    fwrite( STDERR, 'WordPress installation not found.' . PHP_EOL );
    exit( 1 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_root . '/wp-load.php';
$allowed = array( 'the-toaster-was-a-decoy', 'an-orchestra-in-8gb' );
$dates = array();
$updated = array();
foreach ( $payload['updates'] as $entry ) {
    $slug = isset( $entry['slug'] ) ? (string) $entry['slug'] : '';
    $date = isset( $entry['published_at'] ) ? (string) $entry['published_at'] : '';
    if ( ! in_array( $slug, $allowed, true ) || isset( $dates[ $slug ] ) ) {
        fwrite( STDERR, 'Unapproved or duplicate post slug.' . PHP_EOL );
        exit( 1 );
    }
    $parsed = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $date, wp_timezone() );
    if ( ! $parsed || $parsed->format( 'Y-m-d H:i:s' ) !== $date ) {
        fwrite( STDERR, 'Invalid WordPress-local publish date.' . PHP_EOL );
        exit( 1 );
    }
    $post = get_page_by_path( $slug, OBJECT, 'post' );
    if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
        fwrite( STDERR, 'Existing published post not found: ' . $slug . PHP_EOL );
        exit( 1 );
    }
    $dates[$slug] = $date;
    $updated[] = array( 'id' => $post->ID, 'slug' => $slug );
}
if ( count( $dates ) !== count( $allowed )
    || $dates['an-orchestra-in-8gb'] <= $dates['the-toaster-was-a-decoy'] ) {
    fwrite( STDERR, 'Date order is not valid.' . PHP_EOL );
    exit( 1 );
}

foreach ( $updated as $item ) {
    $date = $dates[$item['slug']];
    $result = wp_update_post( wp_slash( array(
        'ID' => $item['id'],
        'post_date' => $date,
        'post_date_gmt' => get_gmt_from_date( $date ),
        'edit_date' => true,
    ) ), true );
    if ( is_wp_error( $result ) || ! $result ) {
        fwrite( STDERR, 'WordPress could not update date: ' . $item['slug'] . PHP_EOL );
        exit( 1 );
    }
    clean_post_cache( $item['id'] );
}
// Purge site-wide page cache so both /blog/ and the homepage see the new query.
do_action( 'litespeed_purge_all' );

$part2 = get_page_by_path( 'an-orchestra-in-8gb', OBJECT, 'post' );
$part1 = get_page_by_path( 'the-toaster-was-a-decoy', OBJECT, 'post' );
$intro = get_page_by_path( 'what-we-leave-open', OBJECT, 'post' );
if ( ! ( $part2 instanceof WP_Post && $part1 instanceof WP_Post && $intro instanceof WP_Post
    && $part2->post_date === $dates['an-orchestra-in-8gb']
    && $part1->post_date === $dates['the-toaster-was-a-decoy']
    && $part2->post_date > $part1->post_date
    && $part1->post_date > $intro->post_date ) ) {
    fwrite( STDERR, 'Post dates failed the WordPress read-back verification.' . PHP_EOL );
    exit( 1 );
}
$query = new WP_Query( array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 3,
    'orderby' => 'date',
    'order' => 'DESC',
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
) );
$slugs = array_map( static function( $post ) { return $post->post_name; }, $query->posts );
if ( empty( $slugs ) || $slugs[0] !== 'an-orchestra-in-8gb' ) {
    fwrite( STDERR, 'The homepage latest-post query still returns an older post.' . PHP_EOL );
    exit( 1 );
}
echo wp_json_encode( array(
    'verified' => true,
    'homepage_latest' => $slugs[0],
    'blog_order' => $slugs,
    'part_ii_date' => $part2->post_date,
    'part_i_date' => $part1->post_date,
    'part_ii_url' => get_permalink( $part2->ID ),
    'part_i_url' => get_permalink( $part1->ID ),
), JSON_UNESCAPED_SLASHES ) . PHP_EOL;
