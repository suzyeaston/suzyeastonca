<?php
/**
 * Standalone CLI test for scripts/wp_ensure_public_pages.php.
 * A fake wp-load.php persists the Page record between separate PHP processes,
 * exercising create, no-op and repair operations without a real WordPress DB.
 */
$script = dirname( __DIR__ ) . '/scripts/wp_ensure_public_pages.php';
$dir = sys_get_temp_dir() . '/se-wp-page-test-' . uniqid( '', true );
mkdir( $dir, 0700, true );
$fixture = <<<'PHP'
<?php
define( 'OBJECT', 'OBJECT' );
class WP_Post {
    public $ID, $post_title, $post_name, $post_status, $post_type, $post_content, $post_author, $page_template;
    public function __construct( $data ) {
        foreach ( $data as $key => $value ) { $this->$key = $value; }
    }
}
function mock_load() {
    $file = __DIR__ . '/saved.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
}
function mock_save($value) { file_put_contents(__DIR__ . '/saved.json', json_encode($value)); }
function get_page_by_path($slug,$type,$post_type) {
    $value=mock_load();
    return $value && $slug === $value['post_name'] && $post_type === $value['post_type'] ? new WP_Post($value) : null;
}
function get_page_template_slug($id) { return mock_load()['page_template'] ?? ''; }
function wp_insert_post($input,$wp_error=false) {
    if (mock_load()) return false;
    $input['ID']=42;
    mock_save($input);
    return 42;
}
function wp_update_post($input,$wp_error=false) {
    $current=mock_load();
    if (!$current || $current['ID'] !== $input['ID']) return false;
    mock_save(array_merge($current,$input));
    return $input['ID'];
}
function wp_slash($input) { return $input; }
function get_users($input) { return [7]; }
function is_wp_error($value) { return false; }
function clean_post_cache($id) { }
function get_post($id) { $current=mock_load(); return $current && $id === $current['ID'] ? new WP_Post($current) : null; }
function get_permalink($id) { return 'https://www.suzyeaston.ca/vancouver-events/'; }
function wp_parse_url($url,$component) { return parse_url($url,$component); }
function wp_json_encode($value,$flags=0) { return json_encode($value,$flags); }
function do_action($name) { }
PHP;
file_put_contents( $dir . '/wp-load.php', $fixture );
$checks = 0;
function check_page( bool $good, string $label ): void {
    global $checks;
    if ( ! $good ) { fwrite( STDERR, "FAILED: $label\n" ); exit( 1 ); }
    ++$checks;
}
function invoke_page( string $script, string $dir ): array {
    $lines = [];
    $status = 0;
    exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' ' . escapeshellarg( $dir ) . ' 2>&1', $lines, $status );
    check_page( 0 === $status, 'page provisioning command succeeds: ' . implode( ' / ', $lines ) );
    $answers = array_values( array_filter( $lines, static fn ( $l ) => str_starts_with( $l, 'SUZY_WP_PAGE:' ) ) );
    check_page( count( $answers ) === 1, 'exactly one WordPress Page verification marker' );
    return json_decode( substr( $answers[0], strlen( 'SUZY_WP_PAGE:' ) ), true ) ?? [];
}
try {
    $first = invoke_page( $script, $dir );
    check_page( $first['action'] === 'created', 'first deployment creates the Page' );
    check_page( $first['id'] === 42 && $first['status'] === 'publish', 'Page is publicly published' );
    check_page( $first['url'] === 'https://www.suzyeaston.ca/vancouver-events/', 'permalink matches requested URL' );
    check_page( $first['template'] === 'page-vancouver-events.php', 'new Page uses the custom template' );
    $original = json_decode( file_get_contents( $dir . '/saved.json' ), true );
    check_page( str_contains( $original['post_content'], 'local calendar' ), 'new Page gets editable fallback content' );
    check_page( $original['post_author'] === 7, 'site administrator owns the Page' );

    $again = invoke_page( $script, $dir );
    check_page( $again['action'] === 'unchanged' && $again['id'] === 42, 'repeated deployments are idempotent' );

    $manually_edited = $original;
    $manually_edited['post_status'] = 'draft';
    $manually_edited['page_template'] = 'default';
    $manually_edited['post_title'] = 'Vancouver Events (custom editorial title)';
    $manually_edited['post_content'] = 'Editorial content that must survive deployment';
    file_put_contents( $dir . '/saved.json', json_encode( $manually_edited ) );
    $repaired = invoke_page( $script, $dir );
    check_page( $repaired['action'] === 'updated' && $repaired['status'] === 'publish', 'existing draft repaired and published' );
    $saved = json_decode( file_get_contents( $dir . '/saved.json' ), true );
    check_page( $saved['page_template'] === 'page-vancouver-events.php', 'existing Page assigned correct template' );
    check_page( $saved['post_content'] === $manually_edited['post_content'], 'existing Page body preserved' );
    check_page( $saved['post_title'] === $manually_edited['post_title'], 'existing editorial title preserved' );
} finally {
    @unlink( $dir . '/saved.json' );
    @unlink( $dir . '/wp-load.php' );
    @rmdir( $dir );
}
echo "Passed $checks WordPress Page provisioning checks.\n";
