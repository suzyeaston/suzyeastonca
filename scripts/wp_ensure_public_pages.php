<?php
/**
 * One-shot, CLI-only WordPress Page registration for the public events hub.
 *
 * Staged over SSH outside public_html by scripts/ensure_wordpress_public_pages.py.
 * Idempotent: does not replace content/title/author of an existing page.
 */
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit;
}
if ( $argc !== 2 || ! is_dir( $argv[1] ) || ! is_file( rtrim( $argv[1], '/' ) . '/wp-load.php' ) ) {
    fwrite( STDERR, "Expected WordPress root as the only argument.\n" );
    exit( 2 );
}

define( 'WP_USE_THEMES', false );
require_once rtrim( $argv[1], '/' ) . '/wp-load.php';

$slug = 'vancouver-events';
$template = 'page-vancouver-events.php';
$title = 'Vancouver Events';
$current = get_page_by_path( $slug, OBJECT, 'page' );

if ( $current instanceof WP_Post ) {
    $id = (int) $current->ID;
    $changes = array( 'ID' => $id );
    if ( 'publish' !== $current->post_status ) {
        $changes['post_status'] = 'publish';
    }
    if ( get_page_template_slug( $id ) !== $template ) {
        $changes['page_template'] = $template;
    }
    if ( count( $changes ) > 1 ) {
        $result = wp_update_post( wp_slash( $changes ), true );
        if ( is_wp_error( $result ) || ! $result ) {
            fwrite( STDERR, "Cannot update Vancouver Events WordPress Page.\n" );
            exit( 1 );
        }
    }
    $action = count( $changes ) > 1 ? 'updated' : 'unchanged';
} else {
    // Authorship is assigned through the site's own administrator account;
    // the script never creates users or requires an administrator password.
    $admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
    $author_id = ! empty( $admins ) ? (int) $admins[0] : 0;
    $result = wp_insert_post( wp_slash( array(
        'post_type' => 'page',
        'post_title' => $title,
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_content' => '<!-- wp:paragraph --><p>A local calendar for Vancouver technology, independent music, culture and creative ideas. Browse upcoming events and the historical archive using the filters above.</p><!-- /wp:paragraph -->',
        'post_author' => $author_id,
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'page_template' => $template,
    ) ), true );
    if ( is_wp_error( $result ) || ! $result ) {
        fwrite( STDERR, "Cannot create Vancouver Events WordPress Page.\n" );
        exit( 1 );
    }
    $id = (int) $result;
    $action = 'created';
}

// Read back the database state. A theme file alone is not success.
clean_post_cache( $id );
$page = get_post( $id );
$url = (string) get_permalink( $id );
$actual_template = (string) get_page_template_slug( $id );
$path = (string) wp_parse_url( $url, PHP_URL_PATH );
if ( ! $page instanceof WP_Post || 'page' !== $page->post_type
    || 'publish' !== $page->post_status || $slug !== $page->post_name
    || $template !== $actual_template || '/' . $slug !== rtrim( $path, '/' ) ) {
    fwrite( STDERR, "WordPress read-back did not confirm the expected published Page, template and permalink.\n" );
    exit( 1 );
}
if ( function_exists( 'do_action' ) ) {
    do_action( 'litespeed_purge_all' );
}
echo 'SUZY_WP_PAGE:' . wp_json_encode( array(
    'id' => $id,
    'slug' => $slug,
    'status' => 'publish',
    'template' => $actual_template,
    'url' => $url,
    'action' => $action,
), JSON_UNESCAPED_SLASHES ) . PHP_EOL;
