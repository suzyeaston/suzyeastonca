<?php
/**
 * Read-only WordPress verification for the November Meanwhile series.
 * Called by the GitHub publishing action after all post imports complete.
 */
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit( 1 );
}
$wp_root = dirname( __DIR__, 2 ) . '/public_html';
if ( ! is_file( $wp_root . '/wp-load.php' ) ) {
    fwrite( STDERR, 'Cannot locate WordPress.' . PHP_EOL );
    exit( 1 );
}
define( 'WP_USE_THEMES', false );
require_once $wp_root . '/wp-load.php';

$slugs = array(
    'an-orchestra-in-8gb',
    'the-toaster-was-a-decoy',
    'what-we-leave-open',
);
$posts = array();
foreach ( $slugs as $slug ) {
    $post = get_page_by_path( $slug, OBJECT, 'post' );
    if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status ) {
        fwrite( STDERR, 'Published Meanwhile article not found: ' . $slug . PHP_EOL );
        exit( 1 );
    }
    $posts[] = $post;
}
if ( ! ( $posts[0]->post_date > $posts[1]->post_date
    && $posts[1]->post_date > $posts[2]->post_date ) ) {
    fwrite( STDERR, 'Incorrect Meanwhile series publication order.' . PHP_EOL );
    foreach ( $posts as $post ) {
        fwrite( STDERR, $post->post_name . ': ' . $post->post_date . PHP_EOL );
    }
    exit( 1 );
}
$query = new WP_Query( array(
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    'ignore_sticky_posts' => true,
    'no_found_rows' => true,
) );
$latest = ! empty( $query->posts ) ? $query->posts[0] : null;
echo wp_json_encode( array(
    'series_order' => array_map(
        static function ( $post ) {
            return array(
                'title' => $post->post_title,
                'slug' => $post->post_name,
                'post_date' => $post->post_date,
                'url' => get_permalink( $post ),
            );
        },
        $posts
    ),
    'homepage_latest_post' => $latest instanceof WP_Post ? $latest->post_name : null,
    'checked' => true,
), JSON_UNESCAPED_SLASHES ) . PHP_EOL;
