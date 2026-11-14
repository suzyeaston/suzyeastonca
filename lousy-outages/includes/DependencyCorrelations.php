<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

use SuzyEaston\LousyOutages\Storage\EpisodeStore;

/**
 * Early warnings from independent, first-party downstream incident notices.
 * Explicit shared dependencies, not generic similarity or causal attribution.
 *
 * Publicly visible provider notices are not a substitute for the upstream
 * provider's authenticated service-health channel.
 */
final class DependencyCorrelations
{
    private const LOOKBACK = 36 * HOUR_IN_SECONDS;
    private const ONSET_WINDOW = 90 * MINUTE_IN_SECONDS;
    private const EMAIL_FRESHNESS = 30 * MINUTE_IN_SECONDS;
    private const EMAIL_MAX_AGE = 75 * MINUTE_IN_SECONDS;
    private const OPTION_DELIVERY = 'lo_dependency_correlation_delivery_v1';
    private const OPTION_DIAGNOSTICS = 'lo_dependency_correlation_diagnostics_v1';
    private const RECIPIENT_BATCH = 25;

    public static function catalogue(): array
    {
        // More upstream dependencies must be added explicitly, with fixture tests.
        return [
            'microsoft365' => ['name'=>'Microsoft 365', 'needle'=>'/\b(?:microsoft\s*365|office\s*365|exchange\s*online|outlook)\b/i'],
            'cloudflare' => ['name'=>'Cloudflare', 'needle'=>'/\bcloudflare\b/i'],
            'aws' => ['name'=>'AWS', 'needle'=>'/\b(?:aws|amazon\s+web\s+services|amazon\s+s3|amazon\s+ec2)\b/i'],
            'google_workspace' => ['name'=>'Google Workspace', 'needle'=>'/\b(?:google\s+workspace|google\s+meet|gmail)\b/i'],
        ];
    }

    public static function detect_all(array $events, int $now = 0): array
    {
        $now = $now ?: time();
        $matches = [];
        foreach ($events as $event) {
            if (!is_array($event)) continue;
            $source = sanitize_key((string)($event['provider_id'] ?? $event['provider'] ?? ''));
            if ($source === '') continue;
            if (array_key_exists('active', $event) && !$event['active']) continue;
            $status = strtolower((string)($event['status'] ?? ''));
            if (preg_match('/resolved|restored|closed|completed|operational|maintenance|scheduled|postmortem/i', $status)) continue;
            if (($event['identity_type'] ?? '') === 'fallback' || preg_match('/(?:^|:)status:/i', (string)($event['source_incident_id'] ?? ''))) continue;
            $title = trim(strip_tags((string)($event['title'] ?? '')));
            $description = trim(strip_tags((string)($event['description'] ?? '')));
            $text = $title.' '.$description;
            // A mere mention, integration announcement or routine maintenance is not an outage.
            if (!preg_match('/\b(?:error|errors|fail|failed|failing|failure|degraded|degradation|disruption|disrupted|outage|unavailable|timeout|timed out|unable|affected|impacting|issues|problem|trouble|delayed|delay|connectivity|connection|login|sign.in|authentication|intermittent)\b/i', $text)) continue;
            if (preg_match('/\b(?:scheduled maintenance|planned maintenance|upcoming maintenance|feature release|new integration)\b/i', $text)) continue;
            $rawTime = $event['first_detected'] ?? $event['first_seen'] ?? $event['published'] ?? 0;
            $ts = is_numeric($rawTime) ? (int)$rawTime : (strtotime((string)$rawTime) ?: 0);
            if ($ts <= $now - self::LOOKBACK || $ts > $now + 300) continue;
            $url = trim((string)($event['incident_url'] ?? $event['url'] ?? ''));
            if (!preg_match('#^https://#i', $url)) $url = '';
            foreach (self::catalogue() as $id => $rule) {
                if ($id === $source || !preg_match($rule['needle'], $text)) continue;
                $matches[$id][] = [
                    'provider_id'=>$source,
                    'name'=>trim((string)($event['provider_label'] ?? ucfirst($source))),
                    'title'=>substr($title, 0, 180),
                    'url'=>$url,
                    'timestamp'=>$ts,
                ];
            }
        }

        $results = [];
        foreach ($matches as $id => $rows) {
            usort($rows, static fn($a,$b) => $a['timestamp'] <=> $b['timestamp']);
            $best = [];
            // Prefer the largest genuine onset cluster, not unrelated mentions
            // scattered across a day. Count each reporting provider only once.
            foreach ($rows as $anchor) {
                $group = [];
                foreach ($rows as $row) {
                    if ($row['timestamp'] < $anchor['timestamp'] || $row['timestamp'] > $anchor['timestamp'] + self::ONSET_WINDOW) continue;
                    $pid = $row['provider_id'];
                    if (!isset($group[$pid]) || $group[$pid]['timestamp'] < $row['timestamp']) $group[$pid] = $row;
                }
                if (count($group) > count($best) || (count($group) === count($best) && $group && max(array_column($group, 'timestamp')) > (int)max(array_column($best ?: [['timestamp'=>0]], 'timestamp')))) {
                    $best = $group;
                }
            }
            if (count($best) < 2) continue;
            ksort($best);
            $times = array_column($best, 'timestamp');
            $results[$id] = [
                'dependency_id'=>$id,
                'dependency_name'=>self::catalogue()[$id]['name'],
                'provider_ids'=>array_keys($best),
                'providers'=>array_values(array_column($best, 'name')),
                'evidence'=>array_values($best),
                'count'=>count($best),
                'first_detected'=>min($times),
                'timestamp'=>max($times),
                'confidence'=>count($best) >= 3 ? 'high' : 'moderate',
                'verification_status'=>'unconfirmed',
                'source_type'=>'cross_provider_corroboration',
            ];
        }
        return $results;
    }

    public static function current(int $now = 0): array
    {
        if (!class_exists(EpisodeStore::class)) return [];
        return self::detect_all((new EpisodeStore())->publications(150), $now);
    }

    /** Add correlation signals without changing official outage counts or fabricating upstream health. */
    public static function augment_state(array $state, array $correlations = []): array
    {
        $correlations = $correlations ?: self::current();
        foreach ($correlations as $id => $hit) {
            $exists = false;
            foreach ((array)($state['providers'] ?? []) as $provider) {
                if (sanitize_key((string)($provider['provider_id'] ?? $provider['id'] ?? '')) === $id) { $exists = true; break; }
            }
            if ($exists) continue;
            $tile = [
                'id'=>$id,'provider_id'=>$id,'name'=>$hit['dependency_name'],
                'stateCode'=>'degraded','status'=>'degraded','state'=>'Correlated downstream disruptions',
                'lane'=>'signals','tile_kind'=>'signal','verification_status'=>'unconfirmed',
                'source_type'=>'cross_provider_corroboration','category'=>'cloud',
                'summary'=>sprintf('%d independent providers report %s-related failures within 90 minutes. Shared dependency suspected; upstream cause unverified.', $hit['count'], $hit['dependency_name']),
                'correlation_count'=>$hit['count'],'correlation_sources'=>$hit['evidence'],
                'correlation_confidence'=>$hit['confidence'],
                'url'=>home_url('/lousy-outages/#degraded'),
                'incidents'=>[], 'data_observed_at'=>gmdate('c',$hit['timestamp']),
                'updated_at'=>gmdate('c',$hit['timestamp']),
            ];
            $state['signals'][] = $tile;
            $state['providers'][] = $tile;
        }
        $state['meta']['signal_count'] = count((array)($state['signals'] ?? []));
        return $state;
    }

    /**
     * Realtime subscriber notifications for genuinely *new* clusters only.
     * First-party upstream confirmation is NOT implied. No retroactive email
     * storm on deployment; each recipient is checkpointed on accepted send.
     */
    public static function send_alerts(int $now = 0, ?array $correlations = null): array
    {
        $now = $now ?: time();
        $result = ['correlations'=>0,'eligible'=>0,'attempted'=>0,'accepted'=>0,'failed'=>0,'skipped_stale'=>0,'at'=>gmdate('c',$now)];
        if ((string)get_option('lousy_outages_dependency_alerts_enabled','1') === '0') return $result;
        $correlations = $correlations ?? self::current($now);
        $result['correlations'] = count($correlations);
        if (!class_exists(IncidentAlerts::class) || !class_exists(Mailer::class)) return $result;
        $ledger = (array)get_option(self::OPTION_DELIVERY,[]);
        foreach ($correlations as $id => $hit) {
            // Authenticated upstream notice takes precedence over a suspicion.
            if ($id === 'microsoft365' && class_exists(Microsoft365ServiceHealth::class)
                && Microsoft365ServiceHealth::active_issues($now)) continue;
            $first = (int)($hit['first_detected'] ?? 0);
            $last = (int)($hit['timestamp'] ?? 0);
            if ($first <= 0 || $first > $now + 300 || $last > $now + 300
                || $now - $first > self::EMAIL_MAX_AGE || $now - $last > self::EMAIL_FRESHNESS) {
                $result['skipped_stale']++; continue;
            }
            $episode = (array)($ledger[$id] ?? []);
            if (empty($episode['start']) || $now - (int)$episode['start'] > 6 * HOUR_IN_SECONDS) {
                $episode = ['start'=>$now, 'sent'=>[]];
            }
            $sent = (array)($episode['sent'] ?? []);
            $preview = IncidentAlerts::recipient_preview($id);
            $recipients = (array)($preview['subscriber_recipients'] ?? []);
            // Owner explicitly configured notification inbox; never use admin_email as
            // an implicit opt-in to unconfirmed correlation alerts.
            $owner = sanitize_email((string)get_option('lousy_outages_email',''));
            if ($owner !== '' && is_email($owner)) $recipients[] = $owner;
            $recipients = array_values(array_unique(array_filter($recipients, 'is_email')));
            $subject = '[Lousy Outages / CORRELATED] Likely '.$hit['dependency_name'].' dependency disruption';
            $link = home_url('/lousy-outages/#degraded');
            $names = implode(', ',array_slice($hit['providers'],0,6));
            $copy = $hit['count'].' independent first-party provider notices ('.$names.') appeared within 90 minutes. This is a suspected shared dependency, NOT an upstream-confirmed incident or established root cause.';
            $sourceText = []; $sourceHtml = [];
            foreach (array_slice($hit['evidence'],0,6) as $source) {
                $line = $source['name'].' — '.$source['title'];
                $sourceText[] = $line.(!empty($source['url'])?' ('.$source['url'].')':'');
                $sourceHtml[] = '<li>'.esc_html($line).(!empty($source['url'])?' <a href="'.esc_url($source['url']).'">source</a>':'').'</li>';
            }
            foreach ($recipients as $email) {
                if ($result['attempted'] >= self::RECIPIENT_BATCH) break;
                $email = strtolower(sanitize_email((string)$email));
                if (!is_email($email) || isset($sent[hash('sha256',$email)])) continue;
                $result['eligible']++;
                $token = IncidentAlerts::build_unsubscribe_token($email);
                $unsubscribe = add_query_arg(['lo_unsub'=>1,'email'=>rawurlencode($email),'token'=>$token],home_url('/lousy-outages/'));
                $text = $copy."\n\n".implode("\n",$sourceText)."\n\nEvidence: ".$link."\nUnsubscribe: ".$unsubscribe;
                $html = '<p>'.esc_html($copy).'</p><ul>'.implode('',$sourceHtml).'</ul><p><a href="'.esc_url($link).'">Review correlated evidence</a></p><p><a href="'.esc_url($unsubscribe).'">Unsubscribe</a></p>';
                $result['attempted']++;
                try { $accepted = Mailer::send($email,$subject,$text,$html,['List-Unsubscribe: <'.esc_url_raw($unsubscribe).'>']); }
                catch (\Throwable $e) { $accepted = false; }
                if ($accepted) {
                    $sent[hash('sha256',$email)] = $now; $result['accepted']++;
                    $episode['sent'] = $sent; $ledger[$id] = $episode;
                    update_option(self::OPTION_DELIVERY,$ledger,false);
                } else { $result['failed']++; }
            }
        }
        // Bounded diagnostics, never public email addresses or tokens.
        update_option(self::OPTION_DIAGNOSTICS,$result,false);
        return $result;
    }
}
