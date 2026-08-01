<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use function SuzyEaston\LousyOutages\Board\render as render_board;

/**
 * Determine the filesystem path and URL for standalone plugin assets.
 *
 * @return array{string, string}
 */
function locate_assets_base(): array
{
    $plugin_path = defined('LOUSY_OUTAGES_PATH') ? rtrim(LOUSY_OUTAGES_PATH, '/\\') . '/assets/' : rtrim(plugin_dir_path(__DIR__), '/\\') . '/assets/';
    $plugin_url = defined('LOUSY_OUTAGES_URL') ? rtrim(LOUSY_OUTAGES_URL, '/\\') . '/assets/' : rtrim(plugin_dir_url(__DIR__), '/\\') . '/assets/';
    return [$plugin_path, $plugin_url];
}

function asset_version(string $base_path, string $asset): string
{
    $version = defined('LOUSY_OUTAGES_VERSION') ? (string) LOUSY_OUTAGES_VERSION : '0.0.0';
    $path = rtrim($base_path, '/\\') . '/' . ltrim($asset, '/\\');
    return $version . '-' . (file_exists($path) ? (string) filemtime($path) : '0');
}

function is_board_page(): bool
{
    return is_page_template('page-lousy-outages.php') || is_page('lousy-outages');
}

/**
 * Register board assets early so full-page caches keep the versioned URLs in <head>.
 */
function enqueue_dashboard_assets(): void
{
    if (!is_board_page()) {
        return;
    }
    register_board_assets();
    wp_enqueue_style('lousy-outages-board');
    wp_enqueue_script('lousy-outages-board');
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_dashboard_assets', 20);

function register_board_assets(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    [$base_path, $base_url] = locate_assets_base();
    wp_register_style('lousy-outages-board', $base_url . 'board.css', [], asset_version($base_path, 'board.css'));
    wp_register_script('lousy-outages-board', $base_url . 'board.js', [], asset_version($base_path, 'board.js'), true);
    wp_localize_script('lousy-outages-board', 'LousyOutagesBoard', board_script_data());
}

/**
 * Registry metadata the client needs when it re-renders the provider matrix.
 *
 * @return array<string, mixed>
 */
function board_script_data(): array
{
    $providers = [];
    foreach (Providers::enabled() as $id => $provider) {
        $slug = sanitize_key((string) $id);
        if ('' === $slug) {
            continue;
        }
        $providers[$slug] = [
            'name'     => (string) ($provider['name'] ?? $slug),
            'category' => strtolower((string) ($provider['category'] ?? 'other')),
            'source'   => strtolower((string) ($provider['source_type'] ?? $provider['type'] ?? 'unknown')),
            'url'      => (string) ($provider['status_url'] ?? ''),
        ];
    }

    return [
        'providers' => $providers,
        'version'   => defined('LOUSY_OUTAGES_VERSION') ? (string) LOUSY_OUTAGES_VERSION : '0.0.0',
    ];
}

function board_config(): array
{
    $can_refresh = current_user_can('manage_options');

    return [
        'summaryEndpoint' => esc_url_raw(rest_url('lousy-outages/v1/summary')),
        'historyEndpoint' => esc_url_raw(rest_url('lousy-outages/v1/history')),
        'refreshEndpoint' => $can_refresh ? esc_url_raw(rest_url('lousy-outages/v1/refresh')) : '',
        'refreshNonce'    => $can_refresh ? wp_create_nonce('wp_rest') : '',
        'reportEndpoint'  => esc_url_raw(rest_url('lousy-outages/v1/report')),
        'subscribeEndpoint' => esc_url_raw(rest_url('lousy-outages/v1/subscribe')),
        'rssUrl'          => esc_url_raw(home_url('/?feed=lousy_outages_status')),
        'pollInterval'    => (int) apply_filters('lo_board_poll_interval', 60000),
        'version'         => defined('LOUSY_OUTAGES_VERSION') ? (string) LOUSY_OUTAGES_VERSION : '0.0.0',
    ];
}

add_shortcode('lousy_outages', __NAMESPACE__ . '\render_shortcode');
add_shortcode('lousy_outages_subscribe', __NAMESPACE__ . '\render_subscribe_shortcode');
add_shortcode('lousy_outages_report', __NAMESPACE__ . '\render_report_shortcode');

function render_shortcode(): string
{
    register_board_assets();
    wp_enqueue_style('lousy-outages-board');
    wp_enqueue_script('lousy-outages-board');

    $state = function_exists('lousy_outages_get_current_state') ? \lousy_outages_get_current_state() : [];
    if (!is_array($state)) {
        $state = [];
    }

    return render_board($state, board_config());
}

/**
 * Alert signup. Kept on the board so the ask sits next to the thing people came for.
 */
function render_subscribe_shortcode(): string
{
    register_board_assets();
    wp_enqueue_style('lousy-outages-board');
    wp_enqueue_script('lousy-outages-board');

    $config = board_config();
    $providers = Providers::enabled();
    if (empty($providers)) {
        $providers = Providers::list();
    }

    $challenge_choices = [];
    foreach (\lo_lyric_fragment_bank() as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $answer = isset($entry['answer']) ? trim((string) $entry['answer']) : '';
        if ('' !== $answer) {
            $challenge_choices[] = $answer;
        }
    }
    $challenge_phrase = $challenge_choices ? (string) $challenge_choices[array_rand($challenge_choices)] : '';

    $uid = uniqid('lox-sub-');

    ob_start();
    ?>
    <section class="lox-section lox-section--dim" id="alerts">
        <div class="lox-section__head">
            <h2 class="lox-section__title"><span class="lox-section__marker" aria-hidden="true">&gt;</span> ALERTS</h2>
            <p class="lox-section__note">Know before your group chat turns into an incident channel.</p>
        </div>
        <form class="lox-form" method="post" action="<?php echo esc_url($config['subscribeEndpoint']); ?>" data-lox-form>
            <label class="lox-form__wide" for="<?php echo esc_attr($uid); ?>-email">
                <span>Email</span>
                <input id="<?php echo esc_attr($uid); ?>-email" type="email" name="email" placeholder="you@example.com" autocomplete="email" required>
            </label>

            <fieldset class="lox-checkgrid">
                <legend class="lox-field__label">Providers to watch</legend>
                <?php foreach ($providers as $provider) :
                    $provider_id = sanitize_key((string) ($provider['id'] ?? ''));
                    if ('' === $provider_id) { continue; }
                    ?>
                    <label class="lox-check">
                        <input type="checkbox" name="providers[]" value="<?php echo esc_attr($provider_id); ?>" checked>
                        <span><?php echo esc_html((string) ($provider['name'] ?? $provider_id)); ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <label class="lox-check"><input type="checkbox" name="realtime_alerts" value="1" checked> <span>Alert me when something breaks</span></label>
            <label class="lox-check"><input type="checkbox" name="daily_digest" value="1"> <span>Daily digest</span></label>
            <label class="lox-check"><input type="checkbox" name="newsletter" value="1"> <span>Product updates</span></label>

            <?php if ('' !== $challenge_phrase) : ?>
                <label class="lox-form__wide" for="<?php echo esc_attr($uid); ?>-challenge">
                    <span>Type this line so I know you have a pulse</span>
                    <span class="lox-form__note">“<?php echo esc_html($challenge_phrase); ?>”</span>
                    <input id="<?php echo esc_attr($uid); ?>-challenge" type="text" name="challenge_response" autocomplete="off" placeholder="<?php echo esc_attr($challenge_phrase); ?>" required>
                </label>
            <?php endif; ?>

            <input type="text" name="website" class="lox-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr(wp_create_nonce('lousy_outages_subscribe')); ?>">

            <div class="lox-form__wide">
                <button type="submit" class="lox-btn">Send me alerts</button>
            </div>
            <p class="lox-form__status" data-lox-form-status aria-live="polite"></p>
            <p class="lox-form__note">Confirm by email. One click to leave, always.</p>
        </form>
    </section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Community report form. Reports stay labelled unconfirmed until a provider says otherwise.
 */
function render_report_shortcode(): string
{
    register_board_assets();
    wp_enqueue_style('lousy-outages-board');
    wp_enqueue_script('lousy-outages-board');

    $config = board_config();
    $providers = Providers::enabled();
    if (empty($providers)) {
        $providers = Providers::list();
    }

    ob_start();
    ?>
    <section class="lox-section lox-section--dim" id="report">
        <div class="lox-section__head">
            <h2 class="lox-section__title"><span class="lox-section__marker" aria-hidden="true">&gt;</span> SEEING SOMETHING WE AREN’T?</h2>
            <p class="lox-section__note">Reports stay marked unconfirmed until the provider admits it.</p>
        </div>
        <form class="lox-form" method="post" action="<?php echo esc_url($config['reportEndpoint']); ?>" data-lox-form>
            <label>
                <span>Provider</span>
                <select name="provider_id" required>
                    <option value="">pick one…</option>
                    <?php foreach ($providers as $provider) :
                        $provider_id = sanitize_key((string) ($provider['id'] ?? ''));
                        if ('' === $provider_id) { continue; }
                        ?>
                        <option value="<?php echo esc_attr($provider_id); ?>"><?php echo esc_html((string) ($provider['name'] ?? $provider_id)); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>What broke</span>
                <select name="symptom" required>
                    <option value="full_outage">Nothing loads</option>
                    <option value="api">API errors</option>
                    <option value="login">Can’t sign in</option>
                    <option value="dashboard">Dashboard</option>
                    <option value="payments">Payments</option>
                    <option value="checkout">Checkout</option>
                    <option value="dns">DNS</option>
                    <option value="email">Email</option>
                    <option value="slow">Just slow</option>
                    <option value="other">Something else</option>
                </select>
            </label>
            <label>
                <span>How bad</span>
                <select name="severity">
                    <option value="unknown">Not sure</option>
                    <option value="minor">Annoying</option>
                    <option value="degraded">Slowing us down</option>
                    <option value="major">Work stopped</option>
                </select>
            </label>
            <label>
                <span>Where</span>
                <input type="text" name="region" maxlength="80" placeholder="Vancouver / BC / Canada">
            </label>
            <label class="lox-form__wide">
                <span>Details (optional)</span>
                <textarea name="details" maxlength="500" placeholder="What you saw, when it started."></textarea>
            </label>
            <label>
                <span>Email (optional)</span>
                <input type="email" name="email" autocomplete="email">
            </label>
            <div>
                <button type="submit" class="lox-btn">File report</button>
            </div>
            <p class="lox-form__status" data-lox-form-status aria-live="polite"></p>
        </form>
    </section>
    <?php
    return (string) ob_get_clean();
}
