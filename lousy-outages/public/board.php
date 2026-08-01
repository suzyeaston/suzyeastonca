<?php
/**
 * Server-rendered markup for the public Lousy Outages board.
 *
 * The board is rendered here in full so the page is useful without JavaScript and
 * so search engines see real content. assets/board.js re-renders the same sections
 * from /lousy-outages/v1/summary on refresh, using identical class names.
 */
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Board;

use SuzyEaston\LousyOutages\Providers;
use SuzyEaston\LousyOutages\Storage\HistoryStore;

if (!defined('ABSPATH')) { exit; }

const HISTORY_PAGE_SIZE = 12;

/**
 * Canonical presentation for every state the board can show.
 *
 * @return array<string, array{label: string, tone: string, blurb: string}>
 */
function state_vocabulary(): array
{
    return [
        'outage'      => ['label' => 'DOWN', 'tone' => 'down', 'blurb' => 'Provider is reporting an outage.'],
        'degraded'    => ['label' => 'DEGRADED', 'tone' => 'warn', 'blurb' => 'Provider is reporting reduced service.'],
        'maintenance' => ['label' => 'MAINTENANCE', 'tone' => 'warn', 'blurb' => 'Planned work in progress.'],
        'advisory'    => ['label' => 'ADVISORY', 'tone' => 'advisory', 'blurb' => 'Open notice the provider stopped updating.'],
        'operational' => ['label' => 'UP', 'tone' => 'ok', 'blurb' => 'No open incidents.'],
        'unknown'     => ['label' => 'NO ANSWER', 'tone' => 'unknown', 'blurb' => 'We could not read the provider status.'],
    ];
}

function state_key(array $provider): string
{
    $lane = strtolower((string) ($provider['lane'] ?? ''));
    if ('unverified' === $lane) { return 'unknown'; }
    if ('long_running' === $lane) { return 'advisory'; }

    $raw = strtolower((string) ($provider['stateCode'] ?? $provider['status'] ?? 'unknown'));
    $map = [
        'major' => 'outage', 'major_outage' => 'outage', 'critical' => 'outage', 'outage' => 'outage',
        'partial' => 'degraded', 'partial_outage' => 'degraded', 'degraded' => 'degraded',
        'degraded_performance' => 'degraded', 'minor' => 'degraded',
        'maintenance' => 'maintenance', 'scheduled' => 'maintenance',
        'operational' => 'operational', 'ok' => 'operational', 'none' => 'operational',
        'advisory' => 'advisory',
    ];
    $key = $map[$raw] ?? 'unknown';
    if ('operational' === $key && !empty($provider['incidents'])) { return 'degraded'; }
    return $key;
}

function severity_key(string $raw): string
{
    $raw = strtolower(trim($raw));
    $map = [
        'critical' => 'outage', 'major' => 'outage', 'major_outage' => 'outage', 'outage' => 'outage',
        'partial' => 'degraded', 'partial_outage' => 'degraded', 'degraded' => 'degraded',
        'degraded_performance' => 'degraded', 'minor' => 'degraded', 'incident' => 'degraded',
        'investigating' => 'degraded', 'identified' => 'degraded', 'monitoring' => 'degraded',
        'maintenance' => 'maintenance', 'scheduled' => 'maintenance',
    ];
    return $map[$raw] ?? 'degraded';
}

function timestamp_from($value): int
{
    if (is_int($value)) { return $value > 0 ? $value : 0; }
    if (is_string($value) && ctype_digit($value)) { return (int) $value; }
    if (empty($value)) { return 0; }
    $parsed = strtotime((string) $value);
    return $parsed ?: 0;
}

function first_timestamp(array $source, array $keys): int
{
    foreach ($keys as $key) {
        $timestamp = timestamp_from($source[$key] ?? null);
        if ($timestamp > 0) { return $timestamp; }
    }
    return 0;
}

/**
 * "4 min ago" / "3 days ago". Deliberately short; the exact time is in the tooltip.
 */
function relative_time(int $timestamp, ?int $now = null): string
{
    if ($timestamp <= 0) { return 'never'; }
    $now = $now ?? time();
    $delta = $now - $timestamp;
    if ($delta < 0) { return 'just now'; }
    if ($delta < 60) { return $delta . 's ago'; }
    if ($delta < HOUR_IN_SECONDS) { return (int) floor($delta / 60) . 'm ago'; }
    if ($delta < DAY_IN_SECONDS) { return (int) floor($delta / HOUR_IN_SECONDS) . 'h ago'; }
    $days = (int) floor($delta / DAY_IN_SECONDS);
    if ($days < 30) { return $days . 'd ago'; }
    $months = (int) floor($days / 30);
    return $months < 12 ? $months . 'mo ago' : (int) floor($months / 12) . 'y ago';
}

function absolute_time(int $timestamp): string
{
    if ($timestamp <= 0) { return 'no timestamp'; }
    return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
}

function iso_time(int $timestamp): string
{
    return $timestamp > 0 ? gmdate('c', $timestamp) : '';
}

/**
 * Strip provider boilerplate so a card reads like a sentence rather than a changelog.
 */
function tidy(string $text, int $limit = 260): string
{
    $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = (string) preg_replace('/\*\*\s*(Summary|Description|Symptoms?|Workaround|Impact|Status|Updates?)\s*\*\*\s*:?/i', ' ', $text);
    $text = (string) preg_replace('/\s+/', ' ', $text);
    $text = trim($text, " \t\n\r\0\x0B*:-");
    if ('' === $text || strlen($text) <= $limit) { return $text; }
    $slice = substr($text, 0, $limit - 1);
    $space = strrpos($slice, ' ');
    if (false !== $space && $space > ($limit * 0.6)) { $slice = substr($slice, 0, $space); }
    return rtrim($slice, " \t.,;:-") . '…';
}

/**
 * Flatten an incident record into everything a card needs.
 *
 * @return array<string, mixed>
 */
function incident_view(array $incident): array
{
    $title = tidy((string) ($incident['display_title'] ?? $incident['displayTitle'] ?? $incident['title'] ?? $incident['name'] ?? ''), 130);
    $summary = tidy((string) ($incident['summary'] ?? $incident['body'] ?? $incident['message'] ?? ''), 320);
    if ('' === $title) { $title = $summary !== '' ? $summary : 'Incident reported'; }
    if ($summary !== '' && 0 === strcasecmp($summary, $title)) { $summary = ''; }

    $updated = first_timestamp($incident, ['last_official_update', 'updated_at', 'updatedAt', 'started_at', 'startedAt']);
    $started = first_timestamp($incident, ['started_at', 'startedAt', 'first_seen']);
    $checked = first_timestamp($incident, ['checked_at', 'checkedAt']);

    $scope = [];
    foreach ((array) ($incident['affected_services'] ?? []) as $service) {
        if (is_string($service) && '' !== trim($service)) { $scope[] = trim($service); }
    }
    foreach (['region_name', 'region_code', 'scope'] as $field) {
        $value = trim((string) ($incident[$field] ?? ''));
        if ('' !== $value) { $scope[] = $value; }
    }

    $severity = severity_key((string) ($incident['impact'] ?? $incident['status'] ?? ''));

    return [
        'id'           => (string) ($incident['id'] ?? ''),
        'provider_id'  => sanitize_key((string) ($incident['provider_id'] ?? '')),
        'provider'     => (string) ($incident['provider'] ?? $incident['provider_name'] ?? ''),
        'title'        => $title,
        'summary'      => $summary,
        'severity'     => $severity,
        'lifecycle'    => ucwords(str_replace(['_', '-'], ' ', (string) ($incident['status'] ?? $severity))),
        'scope'        => implode(' · ', array_slice(array_unique($scope), 0, 4)),
        'updated_ts'   => $updated,
        'started_ts'   => $started,
        'checked_ts'   => $checked,
        'url'          => (string) ($incident['url'] ?? $incident['provider_url'] ?? ''),
        'long_running' => !empty($incident['is_long_running']),
    ];
}

/**
 * Merge registry metadata (category, source type) onto snapshot tiles.
 *
 * @return array<int, array<string, mixed>>
 */
function provider_rows(array $state): array
{
    $config = Providers::enabled();
    $rows = [];
    foreach ((array) ($state['providers'] ?? []) as $provider) {
        if (!is_array($provider)) { continue; }
        $id = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
        if ('' === $id) { continue; }
        $registry = is_array($config[$id] ?? null) ? $config[$id] : [];

        $checked = first_timestamp($provider, ['checked_at', 'checkedAt', 'last_successful_at', 'fetched_at']);
        $observed = first_timestamp($provider, ['data_observed_at', 'updatedAt', 'updated_at', 'last_official_update']);
        $key = state_key($provider);

        $rows[] = [
            'id'          => $id,
            'name'        => (string) ($provider['name'] ?? $provider['provider'] ?? $registry['name'] ?? $id),
            'state'       => $key,
            'summary'     => tidy((string) ($provider['summary'] ?? $provider['message'] ?? ''), 220),
            'category'    => strtolower((string) ($provider['category'] ?? $registry['category'] ?? 'other')),
            'source'      => strtolower((string) ($provider['sourceType'] ?? $provider['source_type'] ?? $registry['source_type'] ?? 'unknown')),
            'checked_ts'  => $checked,
            'observed_ts' => $observed,
            'url'         => (string) ($provider['url'] ?? $registry['status_url'] ?? ''),
            'error'       => (string) ($provider['fetch_error'] ?? $provider['error'] ?? ''),
        ];
    }

    $order = ['outage' => 0, 'degraded' => 1, 'advisory' => 2, 'maintenance' => 3, 'unknown' => 4, 'operational' => 5];
    usort($rows, static function (array $left, array $right) use ($order): int {
        $delta = ($order[$left['state']] ?? 9) <=> ($order[$right['state']] ?? 9);
        return 0 !== $delta ? $delta : strcasecmp($left['name'], $right['name']);
    });

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function history_rows(int $days = 30, int $limit = HISTORY_PAGE_SIZE): array
{
    global $wpdb;
    if (!class_exists(HistoryStore::class) || !$wpdb) { return []; }
    $store = new HistoryStore();
    if (!$store->tableExists()) { return []; }

    $page = $store->queryPage([
        'cutoff'         => $days > 0 ? time() - ($days * DAY_IN_SECONDS) : 0,
        'important_only' => false,
        'per_page'       => $limit,
        'offset'         => 0,
    ]);

    $rows = [];
    foreach ((array) ($page['events'] ?? []) as $event) {
        if (!is_array($event)) { continue; }
        $status = strtolower((string) ($event['status'] ?? ''));
        if (in_array($status, ['operational', 'ok', 'none'], true)) { continue; }
        $rows[] = [
            'provider' => (string) ($event['provider_label'] ?? $event['provider'] ?? ''),
            'title'    => tidy((string) ($event['title'] ?? ''), 150),
            'severity' => severity_key((string) ($event['severity'] ?? $status)),
            'start_ts' => (int) ($event['first_seen'] ?? 0),
            'end_ts'   => (int) ($event['last_seen'] ?? 0),
            'url'      => (string) ($event['url'] ?? ''),
        ];
    }
    return $rows;
}

function verdict(array $state): array
{
    $meta = is_array($state['meta'] ?? null) ? $state['meta'] : [];
    $active = (int) ($meta['active_outage_count'] ?? 0);
    $signals = (int) ($meta['signal_count'] ?? 0);
    $unverified = (int) ($meta['unverified_count'] ?? 0);
    $advisories = (int) ($meta['long_running_count'] ?? 0);
    $total = count((array) ($state['providers'] ?? []));

    if ($active > 0) {
        $providers = count(array_unique((array) ($meta['current_official_provider_ids'] ?? [])));
        return [
            'tone' => 'down',
            'line' => $providers === 1 ? '1 PROVIDER IS DOWN' : $providers . ' PROVIDERS ARE DOWN',
            'sub'  => sprintf('%d open %s the provider is still updating.', $active, 1 === $active ? 'incident' : 'incidents'),
        ];
    }
    if ($signals > 0) {
        return [
            'tone' => 'warn',
            'line' => 'DEGRADED',
            'sub'  => sprintf('%d %s flagged trouble without filing an incident.', $signals, 1 === $signals ? 'provider' : 'providers'),
        ];
    }
    if ($unverified > 0) {
        return [
            'tone' => 'unknown',
            'line' => 'PARTIAL READ',
            'sub'  => sprintf('%d %s did not answer. Everything else is up.', $unverified, 1 === $unverified ? 'provider' : 'providers'),
        ];
    }
    return [
        'tone' => 'ok',
        'line' => 'ALL CLEAR',
        'sub'  => $advisories > 0
            ? sprintf('%d of %d up. %d old %s still open.', $total - $advisories, $total, $advisories, 1 === $advisories ? 'advisory' : 'advisories')
            : sprintf('All %d providers up. Nothing on fire.', $total),
    ];
}

function tone_for(string $stateKey): string
{
    $vocabulary = state_vocabulary();
    return (string) ($vocabulary[$stateKey]['tone'] ?? 'unknown');
}

function label_for(string $stateKey): string
{
    $vocabulary = state_vocabulary();
    return (string) ($vocabulary[$stateKey]['label'] ?? 'UNKNOWN');
}

function print_time(int $timestamp): void
{
    printf(
        '<time class="lox-time" datetime="%s" title="%s" data-lox-rel>%s</time>',
        esc_attr(iso_time($timestamp)),
        esc_attr(absolute_time($timestamp)),
        esc_html(relative_time($timestamp))
    );
}

function render_incident_card(array $incident, string $variant = 'active'): void
{
    $view = incident_view($incident);
    $tone = 'advisory' === $variant ? 'advisory' : ('outage' === $view['severity'] ? 'down' : 'warn');
    ?>
    <article class="lox-card lox-card--<?php echo esc_attr($tone); ?>" id="incident-<?php echo esc_attr(sanitize_title($view['provider_id'] . '-' . $view['id'])); ?>">
        <header class="lox-card__head">
            <h3 class="lox-card__provider"><?php echo esc_html($view['provider'] ?: $view['provider_id']); ?></h3>
            <span class="lox-chip lox-chip--<?php echo esc_attr($tone); ?>"><?php echo esc_html('advisory' === $variant ? 'NO RECENT UPDATE' : strtoupper($view['severity'])); ?></span>
        </header>
        <p class="lox-card__title"><?php echo esc_html($view['title']); ?></p>
        <?php if ('' !== $view['summary']) : ?>
            <p class="lox-card__body"><?php echo esc_html($view['summary']); ?></p>
        <?php endif; ?>
        <dl class="lox-facts">
            <?php if ('' !== $view['scope']) : ?>
                <div><dt>Scope</dt><dd><?php echo esc_html($view['scope']); ?></dd></div>
            <?php endif; ?>
            <div><dt>Lifecycle</dt><dd><?php echo esc_html($view['lifecycle']); ?></dd></div>
            <div><dt>Provider last spoke</dt><dd><?php print_time($view['updated_ts']); ?></dd></div>
            <div><dt>We last checked</dt><dd><?php print_time($view['checked_ts']); ?></dd></div>
        </dl>
        <?php if ('' !== $view['url']) : ?>
            <a class="lox-out" href="<?php echo esc_url($view['url']); ?>" target="_blank" rel="noopener">Read the provider notice <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </article>
    <?php
}

function render_signal_card(array $provider): void
{
    $id = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
    $name = (string) ($provider['name'] ?? $provider['provider'] ?? $id);
    $summary = tidy((string) ($provider['summary'] ?? $provider['message'] ?? ''), 320);
    $checked = first_timestamp($provider, ['checked_at', 'checkedAt', 'last_successful_at']);
    $observed = first_timestamp($provider, ['data_observed_at', 'updatedAt', 'updated_at']);
    $url = (string) ($provider['url'] ?? '');
    ?>
    <article class="lox-card lox-card--warn" id="provider-<?php echo esc_attr($id); ?>">
        <header class="lox-card__head">
            <h3 class="lox-card__provider"><?php echo esc_html($name); ?></h3>
            <span class="lox-chip lox-chip--warn">DEGRADED</span>
        </header>
        <p class="lox-card__title">Status page says something is off. No incident filed.</p>
        <?php if ('' !== $summary) : ?>
            <p class="lox-card__body"><?php echo esc_html($summary); ?></p>
        <?php endif; ?>
        <dl class="lox-facts">
            <div><dt>Provider page updated</dt><dd><?php print_time($observed); ?></dd></div>
            <div><dt>We last checked</dt><dd><?php print_time($checked); ?></dd></div>
        </dl>
        <?php if ('' !== $url) : ?>
            <a class="lox-out" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Open status page <span aria-hidden="true">↗</span></a>
        <?php endif; ?>
    </article>
    <?php
}

function render_unverified_card(array $provider): void
{
    $id = sanitize_key((string) ($provider['provider_id'] ?? $provider['id'] ?? ''));
    $name = (string) ($provider['name'] ?? $provider['provider'] ?? $id);
    $reason = tidy((string) ($provider['fetch_error'] ?? $provider['stale_label'] ?? ''), 200);
    $checked = first_timestamp($provider, ['last_attempted_at', 'checked_at', 'checkedAt']);
    ?>
    <article class="lox-card lox-card--unknown" id="provider-<?php echo esc_attr($id); ?>">
        <header class="lox-card__head">
            <h3 class="lox-card__provider"><?php echo esc_html($name); ?></h3>
            <span class="lox-chip lox-chip--unknown">NO ANSWER</span>
        </header>
        <p class="lox-card__title">We could not read this provider’s status. Not a claim that it is down.</p>
        <?php if ('' !== $reason) : ?>
            <p class="lox-card__body"><?php echo esc_html($reason); ?></p>
        <?php endif; ?>
        <dl class="lox-facts">
            <div><dt>Last attempt</dt><dd><?php print_time($checked); ?></dd></div>
        </dl>
    </article>
    <?php
}

function render_section_open(string $key, string $title, string $note, int $count, string $tone = 'dim'): void
{
    ?>
    <section class="lox-section lox-section--<?php echo esc_attr($tone); ?>" data-lox-section="<?php echo esc_attr($key); ?>">
        <div class="lox-section__head">
            <h2 class="lox-section__title"><span class="lox-section__marker" aria-hidden="true">&gt;</span> <?php echo esc_html($title); ?><span class="lox-section__count"><?php echo esc_html((string) $count); ?></span></h2>
            <p class="lox-section__note"><?php echo esc_html($note); ?></p>
        </div>
    <?php
}

function render_section_close(): void
{
    echo '</section>';
}

/**
 * Render the whole board.
 */
function render(array $state, array $config): string
{
    $meta = is_array($state['meta'] ?? null) ? $state['meta'] : [];
    $providers = provider_rows($state);
    $active = array_values(array_filter((array) ($state['outages'] ?? []), 'is_array'));
    $advisories = array_values(array_filter((array) ($state['long_running'] ?? []), 'is_array'));
    $signals = array_values(array_filter((array) ($state['signals'] ?? []), 'is_array'));
    $unverified = array_values(array_filter((array) ($state['unverified'] ?? []), 'is_array'));
    $history = history_rows(30);
    $verdict = verdict($state);

    $fetchedTs = timestamp_from($state['fetched_at'] ?? '');
    $counts = [
        'down'        => count(array_filter($providers, static fn(array $p): bool => 'outage' === $p['state'])),
        'degraded'    => count(array_filter($providers, static fn(array $p): bool => in_array($p['state'], ['degraded', 'maintenance'], true))),
        'advisory'    => count(array_filter($providers, static fn(array $p): bool => 'advisory' === $p['state'])),
        'unknown'     => count(array_filter($providers, static fn(array $p): bool => 'unknown' === $p['state'])),
        'operational' => count(array_filter($providers, static fn(array $p): bool => 'operational' === $p['state'])),
    ];

    $categories = [];
    foreach ($providers as $row) {
        if ('' !== $row['category']) { $categories[$row['category']] = ucfirst($row['category']); }
    }
    ksort($categories);

    ob_start();
    ?>
    <div class="lox"
         data-lox
         data-lox-summary="<?php echo esc_url($config['summaryEndpoint']); ?>"
         data-lox-history="<?php echo esc_url($config['historyEndpoint']); ?>"
         data-lox-refresh="<?php echo esc_url((string) $config['refreshEndpoint']); ?>"
         data-lox-nonce="<?php echo esc_attr((string) $config['refreshNonce']); ?>"
         data-lox-poll="<?php echo esc_attr((string) $config['pollInterval']); ?>">
        <div class="lox-scan" aria-hidden="true"></div>

        <header class="lox-shell">
            <div class="lox-shell__bar">
                <span class="lox-shell__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="lox-shell__path">lousy-outages@yvr — status --all</span>
                <span class="lox-shell__build">build <?php echo esc_html((string) $config['version']); ?></span>
            </div>
            <div class="lox-shell__body">
                <p class="lox-prompt"><span class="lox-prompt__sigil">$</span> watch --every=15m ./poll-official-status<span class="lox-caret" aria-hidden="true"></span></p>
                <p class="lox-verdict lox-verdict--<?php echo esc_attr($verdict['tone']); ?>" data-lox-verdict><?php echo esc_html($verdict['line']); ?></p>
                <p class="lox-verdict__sub" data-lox-verdict-sub><?php echo esc_html($verdict['sub']); ?></p>
                <p class="lox-shell__meta">
                    Official provider feeds only. Last sync <?php print_time($fetchedTs); ?>.
                    <span class="lox-shell__next" data-lox-countdown></span>
                </p>
            </div>
        </header>

        <section class="lox-readout" aria-label="Status totals">
            <?php
            $cells = [
                ['down', 'DOWN', $counts['down'], 'down'],
                ['degraded', 'DEGRADED', $counts['degraded'], 'warn'],
                ['advisory', 'ADVISORIES', $counts['advisory'], 'advisory'],
                ['unknown', 'NO ANSWER', $counts['unknown'], 'unknown'],
                ['operational', 'UP', $counts['operational'], 'ok'],
                ['tracked', 'TRACKED', count($providers), 'dim'],
            ];
            foreach ($cells as [$key, $label, $value, $tone]) : ?>
                <div class="lox-readout__cell lox-readout__cell--<?php echo esc_attr($tone); ?>" data-lox-count="<?php echo esc_attr($key); ?>">
                    <span class="lox-readout__value"><?php echo esc_html(str_pad((string) $value, 2, '0', STR_PAD_LEFT)); ?></span>
                    <span class="lox-readout__label"><?php echo esc_html($label); ?></span>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="lox-toolbar">
            <button type="button" class="lox-btn" data-lox-reload>Re-poll now</button>
            <a class="lox-btn lox-btn--ghost" href="<?php echo esc_url($config['summaryEndpoint']); ?>" target="_blank" rel="noopener">JSON</a>
            <a class="lox-btn lox-btn--ghost" href="<?php echo esc_url($config['rssUrl']); ?>" target="_blank" rel="noopener">RSS</a>
            <button type="button" class="lox-btn lox-btn--ghost" data-lox-csv>CSV</button>
            <span class="lox-toolbar__status" role="status" aria-live="polite" data-lox-status></span>
        </div>

        <div class="lox-lanes" data-lox-lanes>
            <?php render_section_open('active', 'ACTIVE INCIDENTS', 'Open, and the provider is still posting updates.', count($active), $active ? 'down' : 'dim'); ?>
            <div class="lox-cards" data-lox-grid="active">
                <?php if ($active) : ?>
                    <?php foreach ($active as $incident) { render_incident_card($incident, 'active'); } ?>
                <?php else : ?>
                    <p class="lox-empty">Nothing on fire. Enjoy it.</p>
                <?php endif; ?>
            </div>
            <?php render_section_close(); ?>

            <?php render_section_open('degraded', 'DEGRADED, NO INCIDENT FILED', 'The status page is yellow but nobody wrote it up.', count($signals), $signals ? 'warn' : 'dim'); ?>
            <div class="lox-cards" data-lox-grid="degraded">
                <?php if ($signals) : ?>
                    <?php foreach ($signals as $provider) { render_signal_card($provider); } ?>
                <?php else : ?>
                    <p class="lox-empty">No unexplained yellow lights.</p>
                <?php endif; ?>
            </div>
            <?php render_section_close(); ?>

            <?php render_section_open('advisories', 'OPEN ADVISORIES', 'Still open on paper. The provider went quiet.', count($advisories), $advisories ? 'advisory' : 'dim'); ?>
            <div class="lox-cards" data-lox-grid="advisories">
                <?php if ($advisories) : ?>
                    <?php foreach ($advisories as $incident) { render_incident_card($incident, 'advisory'); } ?>
                <?php else : ?>
                    <p class="lox-empty">No stale notices hanging around.</p>
                <?php endif; ?>
            </div>
            <?php render_section_close(); ?>

            <?php if ($unverified) : ?>
                <?php render_section_open('unverified', 'NO ANSWER', 'We could not read these. That is our problem, not a verdict.', count($unverified), 'unknown'); ?>
                <div class="lox-cards" data-lox-grid="unverified">
                    <?php foreach ($unverified as $provider) { render_unverified_card($provider); } ?>
                </div>
                <?php render_section_close(); ?>
            <?php endif; ?>
        </div>

        <section class="lox-section lox-section--dim" id="providers" data-lox-section="matrix">
            <div class="lox-section__head">
                <h2 class="lox-section__title"><span class="lox-section__marker" aria-hidden="true">&gt;</span> PROVIDER MATRIX<span class="lox-section__count"><?php echo esc_html((string) count($providers)); ?></span></h2>
                <p class="lox-section__note">Every provider we poll, the source we poll, and when we last got an answer.</p>
            </div>
            <div class="lox-filters">
                <label class="lox-field">
                    <span class="lox-field__label">grep</span>
                    <input type="search" data-lox-filter="query" placeholder="provider name" autocomplete="off">
                </label>
                <label class="lox-field">
                    <span class="lox-field__label">category</span>
                    <select data-lox-filter="category">
                        <option value="">all</option>
                        <?php foreach ($categories as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="lox-field">
                    <span class="lox-field__label">state</span>
                    <select data-lox-filter="state">
                        <option value="">all</option>
                        <?php foreach (state_vocabulary() as $value => $definition) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($definition['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span class="lox-filters__count" data-lox-matrix-count><?php echo esc_html(count($providers) . ' shown'); ?></span>
            </div>
            <div class="lox-table-wrap">
                <table class="lox-table">
                    <caption class="lox-sr">Monitored providers and their current state</caption>
                    <thead>
                        <tr>
                            <th scope="col">state</th>
                            <th scope="col">provider</th>
                            <th scope="col">detail</th>
                            <th scope="col">category</th>
                            <th scope="col">source</th>
                            <th scope="col">checked</th>
                            <th scope="col">page</th>
                        </tr>
                    </thead>
                    <tbody data-lox-matrix>
                        <?php foreach ($providers as $row) : ?>
                            <tr data-lox-row
                                data-name="<?php echo esc_attr(strtolower($row['name'] . ' ' . $row['id'])); ?>"
                                data-category="<?php echo esc_attr($row['category']); ?>"
                                data-state="<?php echo esc_attr($row['state']); ?>">
                                <td data-label="state">
                                    <span class="lox-led lox-led--<?php echo esc_attr(tone_for($row['state'])); ?>" aria-hidden="true"></span>
                                    <span class="lox-state lox-state--<?php echo esc_attr(tone_for($row['state'])); ?>"><?php echo esc_html(label_for($row['state'])); ?></span>
                                </td>
                                <th scope="row" data-label="provider"><?php echo esc_html($row['name']); ?></th>
                                <td data-label="detail" class="lox-table__detail"><?php echo esc_html($row['summary'] ?: '—'); ?></td>
                                <td data-label="category"><?php echo esc_html($row['category']); ?></td>
                                <td data-label="source"><?php echo esc_html(str_replace('_', ' ', $row['source'])); ?></td>
                                <td data-label="checked"><?php print_time($row['checked_ts']); ?></td>
                                <td data-label="page"><?php if ('' !== $row['url']) : ?><a class="lox-out" href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener">open ↗</a><?php else : ?>—<?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="lox-empty" data-lox-matrix-empty hidden>No provider matches that filter.</p>
        </section>

        <section class="lox-section lox-section--dim" id="history" data-lox-section="history">
            <div class="lox-section__head">
                <h2 class="lox-section__title"><span class="lox-section__marker" aria-hidden="true">&gt;</span> INCIDENT LOG</h2>
                <p class="lox-section__note">Everything we recorded, newest first.</p>
            </div>
            <div class="lox-filters">
                <label class="lox-field">
                    <span class="lox-field__label">window</span>
                    <select data-lox-history-window>
                        <option value="1">24h</option>
                        <option value="7">7d</option>
                        <option value="30" selected>30d</option>
                        <option value="90">90d</option>
                        <option value="365">1y</option>
                        <option value="0">all</option>
                    </select>
                </label>
                <label class="lox-field lox-field--check">
                    <input type="checkbox" data-lox-history-major>
                    <span>major only</span>
                </label>
                <span class="lox-filters__count" data-lox-history-count><?php echo esc_html(count($history) . ' entries'); ?></span>
            </div>
            <ol class="lox-log" data-lox-log>
                <?php if ($history) : ?>
                    <?php foreach ($history as $row) : ?>
                        <li class="lox-log__row lox-log__row--<?php echo esc_attr($row['severity']); ?>">
                            <span class="lox-log__when"><?php print_time($row['start_ts']); ?></span>
                            <span class="lox-log__provider"><?php echo esc_html($row['provider']); ?></span>
                            <span class="lox-log__sev"><?php echo esc_html(strtoupper($row['severity'])); ?></span>
                            <span class="lox-log__title">
                                <?php if ('' !== $row['url']) : ?>
                                    <a href="<?php echo esc_url($row['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($row['title']); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($row['title']); ?>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                <?php else : ?>
                    <li class="lox-empty">No incidents recorded in this window.</li>
                <?php endif; ?>
            </ol>
            <div class="lox-log__more">
                <button type="button" class="lox-btn lox-btn--ghost" data-lox-history-more hidden>Load more</button>
            </div>
        </section>
    </div>
    <?php
    return (string) ob_get_clean();
}
