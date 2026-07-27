<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages\Storage;

use SuzyEaston\LousyOutages\Model\Incident;

/** Persistent lifecycle ledger shared by realtime mail and RSS. */
final class EpisodeStore
{
    public const OPTION = 'lousy_outages_incident_episodes_v1';
    private const MAX_RECORDS = 250;
    private const CLOSED_RETENTION = 90 * DAY_IN_SECONDS;

    /** @return array{opened:array,continuing:array,closed:array,active:array} */
    public function observe(array $incidents, array $providerStates, ?int $now = null): array
    {
        $now = $now ?: time();
        $episodes = $this->all();
        $migration = !get_option('lousy_outages_episode_migration_complete', false);
        $suppressImported = $migration && (bool)(get_option('lousy_outages_alerted_incidents', []) || get_option('lousy_outages_alerted_subjects', []));
        $opened = $continuing = $closed = $seen = [];

        foreach ($incidents as $incident) {
            if (!$incident instanceof Incident || $this->isHealthy($incident->status)) {
                continue;
            }
            $provider = sanitize_key($incident->provider) ?: 'provider';
            $identity = $this->identity($incident, $provider);
            $key = $this->findActive($episodes, $provider, $identity);
            if ($key === '') {
                $guid = 'lo-episode-' . wp_generate_uuid4();
                $key = $guid;
                $episodes[$key] = [
                    'provider_id' => $provider, 'source_incident_id' => $identity['source_id'],
                    'canonical_url' => $identity['url'], 'fallback_signature' => $identity['fallback'],
                    'identity_type' => $identity['type'], 'identity_value' => $identity['value'],
                    'episode_guid' => $guid, 'first_detected' => $now, 'last_observed' => $now,
                    'active' => true, 'closed_at' => null, 'rss_published' => !$suppressImported,
                    'legacy_suppressed' => $suppressImported,
                    'email_successful_recipients' => [], 'email_pending_recipients' => [],
                    'email_failed_recipients' => [], 'title' => $incident->title,
                    'description' => $incident->title, 'status' => $incident->status,
                    'severity' => (string)($incident->impact ?: $incident->status), 'url' => $incident->url,
                ];
                $opened[] = $key;
            } else {
                $continuing[] = $key;
            }
            $episodes[$key]['last_observed'] = $now;
            $episodes[$key]['title'] = $incident->title;
            $episodes[$key]['description'] = $incident->title;
            $episodes[$key]['status'] = $incident->status;
            $episodes[$key]['severity'] = (string)($incident->impact ?: $incident->status);
            $episodes[$key]['url'] = $incident->url;
            $seen[$key] = true;
        }

        foreach ($providerStates as $provider => $status) {
            $provider = sanitize_key((string)$provider);
            if (!$this->isHealthy((string)$status)) continue;
            foreach ($episodes as $key => &$episode) {
                if (!empty($episode['active']) && ($episode['provider_id'] ?? '') === $provider) {
                    $episode['active'] = false; $episode['closed_at'] = $now; $closed[] = $key;
                }
            }
            unset($episode);
        }

        $episodes = $this->prune($episodes, $now);
        update_option(self::OPTION, $episodes, false);
        update_option('lousy_outages_episode_migration_complete', 1, false);
        return ['opened'=>$this->select($episodes,$opened),'continuing'=>$this->select($episodes,$continuing),
            'closed'=>$this->select($episodes,$closed),'active'=>array_values(array_filter($episodes, static fn($e)=>!empty($e['active'])))];
    }

    public function all(): array { $v=get_option(self::OPTION,[]); return is_array($v)?$v:[]; }
    public function publications(int $limit = 15): array
    {
        $rows=array_values(array_filter($this->all(),static fn($e)=>!empty($e['rss_published'])));
        usort($rows,static fn($a,$b)=>(int)($b['first_detected']??0)<=>(int)($a['first_detected']??0));
        return array_slice($rows,0,max(1,$limit));
    }
    public function saveDelivery(string $guid, array $successful, array $failed, array $eligible): void
    {
        $all=$this->all(); if(!isset($all[$guid])) return;
        $ok=array_values(array_unique(array_merge((array)$all[$guid]['email_successful_recipients'],$successful)));
        $all[$guid]['email_successful_recipients']=$ok;
        $all[$guid]['email_failed_recipients']=array_values(array_unique($failed));
        $all[$guid]['email_pending_recipients']=array_values(array_diff(array_unique($eligible),$ok));
        update_option(self::OPTION,$all,false);
    }
    public function pendingRecipients(string $guid,array $eligible): array
    { $all=$this->all(); $sent=(array)($all[$guid]['email_successful_recipients']??[]); return array_values(array_diff(array_unique($eligible),$sent)); }

    private function identity(Incident $i,string $provider): array
    {
        $id=trim($i->id); $isFallback=(bool)preg_match('/(^|:)status:/',$id);
        if($id!==''&&!$isFallback) return ['type'=>'source_id','value'=>$id,'source_id'=>$id,'url'=>'','fallback'=>''];
        $url=$this->canonicalUrl($i->url);
        if($url!==''&&!$isFallback) return ['type'=>'url','value'=>$url,'source_id'=>'','url'=>$url,'fallback'=>''];
        // Provider-state fallbacks represent one explicit active transition. Mutable
        // status, prose, severity and poll timestamps deliberately are not identity.
        $fallback=$provider.'|provider-state';
        return ['type'=>'fallback','value'=>$fallback,'source_id'=>'','url'=>$url,'fallback'=>$fallback];
    }
    private function findActive(array $all,string $provider,array $identity): string
    { foreach($all as $key=>$e) if(!empty($e['active'])&&($e['provider_id']??'')===$provider&&($e['identity_type']??'')===$identity['type']&&($e['identity_value']??'')===$identity['value']) return (string)$key; return ''; }
    private function canonicalUrl(string $url): string { $url=trim(strtolower($url)); return preg_match('#^https?://#',$url)?rtrim($url,'/'):''; }
    private function isHealthy(string $status): bool { return in_array(strtolower(trim($status)),['operational','resolved','none','ok'],true); }
    private function select(array $all,array $keys): array { $out=[]; foreach(array_unique($keys) as $k) if(isset($all[$k]))$out[]=$all[$k]; return $out; }
    private function prune(array $all,int $now): array
    {
        foreach($all as $k=>$e) if(empty($e['active'])&&(int)($e['closed_at']??0)<$now-self::CLOSED_RETENTION)unset($all[$k]);
        if(count($all)>self::MAX_RECORDS){uasort($all,static fn($a,$b)=>(int)($b['last_observed']??0)<=>(int)($a['last_observed']??0));$all=array_slice($all,0,self::MAX_RECORDS,true);}
        return $all;
    }
}
