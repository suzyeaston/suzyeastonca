<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Sources;

use SuzyEaston\LousyOutages\SignalSourceInterface;

/**
 * Publicly reported incidents, NOT network probing or agency monitoring.
 *
 * Uses a server-side, read-only Google News RSS search. No visitor requests,
 * cookies, credentials, agency endpoints, or claims of internal system health.
 */
final class PublicIncidentNewsSource implements SignalSourceInterface {
    public function id(): string { return 'public_incident_news'; }
    public function label(): string { return 'Public incident news (Canada + agencies)'; }
    public function is_configured(): bool {
        return (string) get_option('lo_public_incident_news_enabled', '1') === '1';
    }

    /** Queries are grouped to stay inside WP-Cron runtime budgets. */
    public static function groups(): array {
        return [
            ['id'=>'us_intelligence','region'=>'US','category'=>'government','query'=>'("National Security Agency" OR "NSA" OR "NSA Net") (outage OR disruption OR "network down" OR "systems down" OR "technical issue") when:2d','entities'=>[
                'nsa'=>['name'=>'NSA','pattern'=>'/\b(?:NSA(?:\s+Net)?|National Security Agency)\b/i']]],
            ['id'=>'ca_intelligence','region'=>'CA','category'=>'government','query'=>'("CSIS" OR "Canadian Security Intelligence Service" OR "Communications Security Establishment" OR "CSE Canada") (outage OR disruption OR "systems down" OR "network down" OR "technical issue") when:2d','entities'=>[
                'csis'=>['name'=>'CSIS','pattern'=>'/\b(?:CSIS|Canadian Security Intelligence Service)\b/i','context'=>'/\b(?:Canada|Canadian|Ottawa|intelligence|security service|spy agency)\b/i'],
                'cse'=>['name'=>'Communications Security Establishment','pattern'=>'/\b(?:Communications Security Establishment|CSE Canada)\b/i']]],
            ['id'=>'ca_payments','region'=>'CA','category'=>'payments','query'=>'("Interac" OR "e-Transfer" OR "Moneris" OR "Global Payments Canada") (outage OR down OR disruption OR failure OR unavailable) when:2d','entities'=>[
                'interac'=>['name'=>'Interac / e-Transfer / Debit','pattern'=>'/\b(?:Interac|e[\s-]?Transfer)\b/i'],
                'moneris'=>['name'=>'Moneris','pattern'=>'/\bMoneris\b/i'],
                'global_payments'=>['name'=>'Global Payments Canada','pattern'=>'/\bGlobal Payments(?: Canada)?\b/i']]],
            ['id'=>'ca_banks_major','region'=>'CA','category'=>'banking','query'=>'("RBC" OR "Royal Bank of Canada" OR "TD Bank" OR "Scotiabank" OR "BMO" OR "CIBC") (outage OR down OR disruption OR "online banking" OR "technical issue") when:2d','entities'=>[
                'rbc'=>['name'=>'RBC / Royal Bank','pattern'=>'/\b(?:RBC|Royal Bank(?: of Canada)?)\b/i'],
                'td_canada_trust'=>['name'=>'TD Canada Trust','pattern'=>'/\b(?:TD Bank|TD Canada Trust|TD EasyWeb)\b/i'],
                'scotiabank'=>['name'=>'Scotiabank','pattern'=>'/\bScotiabank\b/i'],
                'bmo'=>['name'=>'BMO','pattern'=>'/\b(?:BMO|Bank of Montreal)\b/i'],
                'cibc'=>['name'=>'CIBC','pattern'=>'/\bCIBC\b/i']]],
            ['id'=>'ca_banks_other','region'=>'CA','category'=>'banking','query'=>'("Tangerine" OR "Simplii Financial" OR "EQ Bank" OR "National Bank of Canada" OR "Desjardins" OR "Vancity" OR "Coast Capital") (outage OR down OR disruption OR "online banking" OR "technical issue") when:2d','entities'=>[
                'tangerine'=>['name'=>'Tangerine','pattern'=>'/\bTangerine(?: Bank)?\b/i'],
                'simplii'=>['name'=>'Simplii Financial','pattern'=>'/\bSimplii\b/i'],
                'eq_bank'=>['name'=>'EQ Bank','pattern'=>'/\bEQ Bank\b/i'],
                'national_bank'=>['name'=>'National Bank of Canada','pattern'=>'/\bNational Bank of Canada\b/i'],
                'desjardins'=>['name'=>'Desjardins','pattern'=>'/\bDesjardins\b/i'],
                'vancity'=>['name'=>'Vancity','pattern'=>'/\bVancity\b/i'],
                'coast_capital'=>['name'=>'Coast Capital','pattern'=>'/\bCoast Capital\b/i']]],
            ['id'=>'ca_telecom_major','region'=>'CA','category'=>'telecom','query'=>'("Rogers" OR "Shaw" OR "TELUS" OR "Bell Canada") (network outage OR internet outage OR mobile outage OR "service disruption" OR "network down") when:2d','entities'=>[
                'rogers'=>['name'=>'Rogers','pattern'=>'/\bRogers\b/i','context'=>'/\b(?:Canada|Canadian|internet|network|telecom|mobile|wireless|cellular|customers|subscribers)\b/i'],
                'shaw'=>['name'=>'Shaw','pattern'=>'/\bShaw\b/i','context'=>'/\b(?:Canada|Canadian|internet|network|telecom|mobile|wireless|customers)\b/i'],
                'telus'=>['name'=>'TELUS','pattern'=>'/\bTELUS\b/i'],
                'bell'=>['name'=>'Bell Canada','pattern'=>'/\b(?:Bell Canada|Bell Mobility|Bell internet|Bell network)\b/i']]],
            ['id'=>'ca_telecom_other','region'=>'CA','category'=>'telecom','query'=>'("Freedom Mobile" OR "Videotron" OR "SaskTel" OR "Eastlink" OR "TekSavvy" OR "Public Mobile") (outage OR "network down" OR "service disruption" OR "internet down") when:2d','entities'=>[
                'freedom_mobile'=>['name'=>'Freedom Mobile','pattern'=>'/\bFreedom Mobile\b/i'],
                'videotron'=>['name'=>'Videotron','pattern'=>'/\b(?:Vidéotron|Videotron)\b/ui'],
                'sasktel'=>['name'=>'SaskTel','pattern'=>'/\bSaskTel\b/i'],
                'eastlink'=>['name'=>'Eastlink','pattern'=>'/\bEastlink\b/i'],
                'teksavvy'=>['name'=>'TekSavvy','pattern'=>'/\bTekSavvy\b/i'],
                'public_mobile'=>['name'=>'Public Mobile','pattern'=>'/\bPublic Mobile\b/i']]],
            ['id'=>'ca_public_services','region'=>'CA','category'=>'government_services','query'=>'("CRA My Account" OR "Service Canada" OR "BC Services Card" OR "GCKey" OR "Canada Post") (outage OR down OR disruption OR "technical issue" OR unavailable) when:2d','entities'=>[
                'cra_my_account'=>['name'=>'CRA My Account','pattern'=>'/\b(?:CRA My Account|CRA login|Canada Revenue Agency)\b/i'],
                'service_canada'=>['name'=>'Service Canada','pattern'=>'/\bService Canada\b/i'],
                'bc_services_card'=>['name'=>'BC Services Card','pattern'=>'/\bBC Services Card\b/i'],
                'gckey'=>['name'=>'GCKey','pattern'=>'/\bGCKey\b/i'],
                'canada_post'=>['name'=>'Canada Post','pattern'=>'/\bCanada Post\b/i']]],
            ['id'=>'ca_critical_services','region'=>'CA','category'=>'critical_infrastructure','query'=>'("E-Comm 911" OR "911 service" OR "BC Hydro" OR "Hydro-Québec" OR "NAV CANADA") (outage OR down OR disruption OR "technical issue" OR failure) when:2d','entities'=>[
                'ecomm_911'=>['name'=>'E-Comm 911','pattern'=>'/\b(?:E-Comm 911|EComm 911)\b/i'],
                'emergency_911'=>['name'=>'911 emergency services','pattern'=>'/\b911 (?:service|system|call|network|dispatch)\b/i','context'=>'/\b(?:Canada|Canadian|BC|British Columbia|emergency|dispatch)\b/i'],
                'bc_hydro'=>['name'=>'BC Hydro','pattern'=>'/\bBC Hydro\b/i'],
                'hydro_quebec'=>['name'=>'Hydro-Québec','pattern'=>'/\bHydro[\s-]?(?:Quebec|Québec)\b/ui'],
                'nav_canada'=>['name'=>'NAV CANADA','pattern'=>'/\bNAV CANADA\b/i']]],
        ];
    }

    /** Pure matcher, usable by fixture tests. Headlines must describe impact. */
    public static function match_item(array $item, array $group, int $now): array {
        $title = trim(strip_tags((string) ($item['title'] ?? '')));
        $description = trim(strip_tags((string) ($item['description'] ?? '')));
        $published = strtotime((string) ($item['published_at'] ?? ''));
        if ($title === '' || $published === false || $published > $now + 300 || $published < $now - 48 * 3600) return [];
        if (!preg_match('/\b(?:outage|outages|offline|degrad(?:ed|ation)|disrupt(?:ed|ion|ions)|unavailable|technical (?:issue|issues|glitch|problem)|service (?:interruption|failure)|network (?:down|failure)|systems? (?:down|offline)|(?:website|app|network|banking|internet|services?|payments?|transfers?|e[\s-]?transfer|debit) (?:is |are |goes? |went )?down)\b/i', $title)) return [];
        if (preg_match('/\b(?:outage drill|mock outage|outage simulation|outage exercise)\b/i', $title)) return [];
        $matches = [];
        $whole = $title . ' ' . $description;
        foreach ((array) ($group['entities'] ?? []) as $id => $entity) {
            if (empty($entity['pattern']) || !preg_match((string) $entity['pattern'], $title)) continue;
            if (!empty($entity['context']) && !preg_match((string) $entity['context'], $whole)) continue;
            $matches[] = ['id'=>$id,'name'=>(string)$entity['name']];
        }
        return array_slice($matches, 0, 2);
    }

    public function collect(array $options = []): array {
        $groups = self::groups();
        $diag = ['configured'=>$this->is_configured(),'attempted'=>false,'groups_checked'=>[],'feed_items_seen'=>0,'incidents_matched'=>0,'stored_candidates'=>0,'errors'=>[],'last_checked_at'=>gmdate('c')];
        if (!$this->is_configured() || !empty($options['dry_run']) || !empty($options['preflight'])) {
            $diag['skipped_reason'] = !$this->is_configured() ? 'disabled' : 'dry_run';
            update_option('lo_diag_'.$this->id(), $diag, false);
            return [];
        }
        $cursor = (int) get_option('lo_public_incident_news_cursor', 0);
        $batchSize = max(1, min(3, (int) apply_filters('lo_public_incident_news_batch_size', 3)));
        $out = [];
        for ($step = 0; $step < min($batchSize, count($groups)); $step++) {
            $group = $groups[($cursor + $step) % count($groups)];
            $diag['groups_checked'][] = $group['id'];
            $diag['attempted'] = true;
            $url = add_query_arg(['q'=>$group['query'],'hl'=>'en-CA','gl'=>'CA','ceid'=>'CA:en'], 'https://news.google.com/rss/search');
            $response = wp_remote_get($url, ['timeout'=>6, 'redirection'=>2, 'limit_response_size'=>250000, 'headers'=>['Accept'=>'application/rss+xml, application/xml']]);
            if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
                $diag['errors'][] = $group['id'].':fetch_failed';
                continue;
            }
            $body = (string) wp_remote_retrieve_body($response);
            if (!function_exists('simplexml_load_string')) { $diag['errors'][] = 'simplexml_missing'; break; }
            $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false || !isset($xml->channel->item)) { $diag['errors'][] = $group['id'].':invalid_rss'; continue; }
            $itemsInFeed = 0;
            foreach ($xml->channel->item as $entry) {
                if (++$itemsInFeed > 20) break;
                $diag['feed_items_seen']++;
                $item = ['title'=>(string)$entry->title,'description'=>(string)$entry->description,'published_at'=>(string)$entry->pubDate,'url'=>(string)$entry->link];
                $entities = self::match_item($item, $group, time());
                if (!$entities) continue;
                $diag['incidents_matched']++;
                $headline = trim((string) preg_replace('/\s+-\s+[^-]{2,65}$/u', '', strip_tags($item['title'])));
                foreach ($entities as $entity) {
                    $id = hash('sha256', $entity['id'].'|'.mb_strtolower($headline).'|'.$item['published_at']);
                    $link = (string)$item['url'];
                    if (!preg_match('#^https://news\.google\.com/#i', $link)) continue;
                    $out[$id] = [
                        'source'=>'public_incident_news','source_type'=>'public_news','signal_lane'=>'open_web','adapter_id'=>'google_news_rss',
                        'source_id'=>substr($id,0,40),'raw_hash'=>$id,'provider_id'=>$entity['id'],'provider_name'=>$entity['name'],
                        'category'=>$group['category'],'region'=>$group['region'],'signal_type'=>'reported_disruption',
                        'severity'=>'watch','confidence'=>42,'title'=>$headline,
                        'message'=>'Public news report; agency/provider operational status is not independently verified.',
                        'url'=>$link,'source_urls'=>[$link],'domains'=>['news.google.com'],'snippets'=>[$headline],
                        'observed_at'=>gmdate('Y-m-d H:i:s'),'expires_at'=>gmdate('Y-m-d H:i:s',time()+12*3600),
                        'official_confirmed'=>false,'evidence_quality'=>'weak','confidence_reason'=>'Unconfirmed third-party public reporting, not an official status feed.',
                        'unconfirmed_note'=>'Do not infer internal system health from website availability.',
                        'metadata_json'=>['article_published_at'=>$item['published_at'],'search_group'=>$group['id']],
                    ];
                }
            }
        }
        update_option('lo_public_incident_news_cursor', ($cursor + $batchSize) % count($groups), false);
        $diag['stored_candidates'] = count($out);
        update_option('lo_diag_'.$this->id(), $diag, false);
        return array_values($out);
    }
}
