<?php
/**
 * One-shot WordPress post importer for GitHub Actions.
 * Invoked over SSH by publish_meanwhile.py; never serves web requests.
 */
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit;
}
// Keep a diagnostic in CI if WordPress's fatal-error handler replaces the
// real PHP exception with a generic "critical error" HTML page.
register_shutdown_function( static function () {
    $error = error_get_last();
    if ( is_array( $error ) && in_array( $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
        $message = (string) $error['message'];
        $message = preg_replace( '~/(?:home|var|tmp)/[^ ]+~', '[server path]', $message );
        fwrite( STDERR, 'Meanwhile PHP fatal [' . $error['type'] . '] at ' . basename( $error['file'] ) . ':' . $error['line'] . ' ' . $message . PHP_EOL );
    }
} );
function se_import_fail( $message ) {
    fwrite( STDERR, "Meanwhile import: " . $message . PHP_EOL );
    exit( 1 );
}
if ( $argc !== 2 || ! is_file( $argv[1] ) ) {
    se_import_fail( 'Expected one staged JSON manifest.' );
}
$manifest = json_decode( file_get_contents( $argv[1] ), true );
if ( ! is_array( $manifest ) ) {
    se_import_fail( 'Invalid JSON manifest.' );
}
$required = array( 'title', 'slug', 'html', 'photos', 'featured_image' );
foreach ( $required as $key ) {
    if ( ! isset( $manifest[ $key ] ) ) {
        se_import_fail( 'Missing field: ' . $key );
    }
}
$wp_root = dirname( __DIR__, 2 ) . '/public_html';
if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
    se_import_fail( 'Cannot locate WordPress installation.' );
}
define( 'WP_USE_THEMES', false );
fwrite( STDERR, 'Meanwhile bootstrapping WordPress' . PHP_EOL );
require_once $wp_root . '/wp-load.php';
fwrite( STDERR, 'Meanwhile WordPress loaded' . PHP_EOL );
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

$slug = sanitize_title( $manifest['slug'] );
if ( $slug !== $manifest['slug'] || ! $slug ) {
    se_import_fail( 'Invalid slug.' );
}
if ( ! is_array( $manifest['photos'] ) ) {
    se_import_fail( 'Photos must be an array.' );
}
$html = (string) $manifest['html'];
$featured_id = 0;
$stage_media = dirname( $argv[1] ) . '/media';
$attached = array();
foreach ( $manifest['photos'] as $photo ) {
    if ( ! is_array( $photo ) || empty( $photo['name'] ) || empty( $photo['token'] ) || empty( $photo['alt'] ) ) {
        se_import_fail( 'Photo metadata is incomplete.' );
    }
    $name = (string) $photo['name'];
    if ( basename( $name ) !== $name || ! preg_match( '/\.(jpe?g|png|webp)$/i', $name ) ) {
        se_import_fail( 'Unsafe photo filename.' );
    }
    fwrite( STDERR, 'Meanwhile importing media: ' . $name . PHP_EOL );
    $key = $slug . ':' . $name;
    $existing = get_posts( array(
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'meta_key' => '_se_meanwhile_asset_key',
        'meta_value' => $key,
        'fields' => 'ids',
    ) );
    $id = ! empty( $existing ) ? (int) $existing[0] : 0;
    if ( ! $id ) {
        $file = $stage_media . '/' . $name;
        if ( ! is_file( $file ) ) {
            se_import_fail( 'Staged image missing: ' . $name );
        }
        $file_array = array(
            'name' => $name,
            'tmp_name' => $file,
        );
        $id = media_handle_sideload( $file_array, 0, (string) $photo['alt'] );
        if ( is_wp_error( $id ) ) {
            se_import_fail( 'Could not import ' . $name . ': ' . $id->get_error_message() );
        }
        update_post_meta( $id, '_se_meanwhile_asset_key', $key );
    }
    $alt = (string) $photo['alt'];
    update_post_meta( $id, '_wp_attachment_image_alt', $alt );
    $img = wp_get_attachment_image( $id, 'large', false, array(
        'alt' => $alt,
        'loading' => 'lazy',
        'decoding' => 'async',
    ) );
    if ( ! $img ) {
        se_import_fail( 'Could not render image: ' . $name );
    }
    $caption = isset( $photo['caption'] ) ? trim( (string) $photo['caption'] ) : '';
    $figure = '<figure class="wp-block-image size-large se-meanwhile-photo">' . $img
        . ( $caption !== '' ? '<figcaption>' . esc_html( $caption ) . '</figcaption>' : '' )
        . '</figure>';
    $token = (string) $photo['token'];
    if ( strpos( $html, $token ) === false ) {
        se_import_fail( 'Image location token not found: ' . $name );
    }
    $html = str_replace( '<p>' . $token . '</p>', $figure, $html );
    $html = str_replace( $token, $figure, $html );
    if ( $name === $manifest['featured_image'] ) {
        $featured_id = $id;
    }
    $attached[] = $id;
}
if ( strpos( $html, 'MEANWHILE_PHOTO_' ) !== false ) {
    se_import_fail( 'Unresolved image marker found in article.' );
}
fwrite( STDERR, 'Meanwhile images prepared; creating post' . PHP_EOL );
$existing_post = get_page_by_path( $slug, OBJECT, 'post' );
$reference_post = get_page_by_path( 'what-we-leave-open', OBJECT, 'post' );
$author_id = $existing_post instanceof WP_Post ? (int) $existing_post->post_author
    : ( $reference_post instanceof WP_Post ? (int) $reference_post->post_author : 0 );
if ( ! $author_id ) {
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
}
$payload = array(
    'post_title' => (string) $manifest['title'],
    'post_name' => $slug,
    'post_content' => $html,
    'post_excerpt' => (string) ( $manifest['excerpt'] ?? '' ),
    'post_status' => 'publish',
    'post_type' => 'post',
    'post_author' => $author_id,
    'comment_status' => 'closed',
);
if ( $existing_post instanceof WP_Post ) {
    $payload['ID'] = $existing_post->ID;
}
$post_id = wp_insert_post( wp_slash( $payload ), true );
if ( is_wp_error( $post_id ) || ! $post_id ) {
    se_import_fail( 'WordPress rejected the post: ' . ( is_wp_error( $post_id ) ? $post_id->get_error_message() : 'unknown error' ) );
}
$category = trim( (string) ( $manifest['category'] ?? '' ) );
if ( $category ) {
    $term = get_term_by( 'slug', sanitize_title( $category ), 'category' );
    if ( ! $term ) {
        $new_term = wp_insert_term( $category, 'category' );
        if ( is_wp_error( $new_term ) ) {
            se_import_fail( 'Could not assign category: ' . $new_term->get_error_message() );
        }
        $category_id = (int) $new_term['term_id'];
    } else {
        $category_id = (int) $term->term_id;
    }
    wp_set_post_categories( $post_id, array( $category_id ), false );
}
if ( $featured_id ) {
    set_post_thumbnail( $post_id, $featured_id );
}
foreach ( $attached as $attachment_id ) {
    if ( ! wp_get_post_parent_id( $attachment_id ) ) {
        wp_update_post( array( 'ID' => $attachment_id, 'post_parent' => $post_id ) );
    }
}
clean_post_cache( $post_id );
if ( function_exists( 'do_action' ) ) {
    do_action( 'litespeed_purge_all' );
}
echo wp_json_encode( array(
    'post_id' => (int) $post_id,
    'url' => get_permalink( $post_id ),
    'images' => count( $attached ),
    'status' => get_post_status( $post_id ),
), JSON_UNESCAPED_SLASHES ) . PHP_EOL;
