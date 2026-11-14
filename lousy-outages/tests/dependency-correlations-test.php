<?php
declare(strict_types=1);

namespace SuzyEaston\LousyOutages;

const HOUR_IN_SECONDS = 3600;
const MINUTE_IN_SECONDS = 60;
$GLOBALS['options'] = [];
$GLOBALS['sent'] = [];
$GLOBALS['fail_next'] = false;
function sanitize_key($s) { return trim(preg_replace('/[^a-z0-9_-]/','',strtolower((string)$s)),'_-'); }
function sanitize_email($s) { return strtolower(trim((string)$s)); }
function is_email($s) { return filter_var($s,FILTER_VALIDATE_EMAIL)!==false; }
function get_option($k,$default=false) { return $GLOBALS['options'][$k] ?? $default; }
function update_option($k,$value,$autoload=false) { $GLOBALS['options'][$k]=$value; return true; }
function esc_html($s) { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function esc_url($s) { return (string)$s; }
function esc_url_raw($s) { return (string)$s; }
function home_url($s='') { return 'https://example.test'.$s; }
function add_query_arg($args,$url) { return $url.'?'.http_build_query($args); }
function chk(bool $condition,string $msg):void { if (!$condition) { fwrite(STDERR,"FAIL: ".$msg."\n");exit(1); } }

class IncidentAlerts {
    public static function recipient_preview(string $providerId=''):array {
        return ['subscriber_recipients'=>['subscriber@example.test']];
    }
    public static function build_unsubscribe_token(string $email):string { return 'fixture-token'; }
}
class Mailer {
    public static function send(string $email,string $subject,string $text,string $html,array $headers=[]):bool {
        $GLOBALS['sent'][]=['email'=>$email,'subject'=>$subject,'text'=>$text,'html'=>$html,'headers'=>$headers];
        if ($GLOBALS['fail_next']) { $GLOBALS['fail_next']=false; return false; }
        return true;
    }
}
require __DIR__.'/../includes/DependencyCorrelations.php';
$now = strtotime('2026-11-13T21:00:00Z');
$make = static fn($id,$title,$seconds) => [
    'provider_id'=>$id,'provider_label'=>ucfirst($id),
    'title'=>$title,'description'=>$title,
    'active'=>true,'status'=>'investigating',
    'first_detected'=>$now-$seconds,
    'url'=>'https://status.'.$id.'.test/incidents/'.$id.'-report',
];
$a=$make('zoom','Microsoft 365 connector authentication errors',600);
$b=$make('netlify','Mail delivery to Outlook delayed',1400);
$results=DependencyCorrelations::detect_all([$a,$b],$now);
chk(isset($results['microsoft365']),'two genuine independent upstream references correlate');
chk($results['microsoft365']['count']===2,'two providers counted separately');
chk($results['microsoft365']['confidence']==='moderate','two first-party notices are not verified causal proof');
chk(count($results['microsoft365']['evidence'])===2,'source URLs survive normalization');
chk(DependencyCorrelations::detect_all([$a,$a],$now)===[],'same provider cannot corroborate itself');
$separated=DependencyCorrelations::detect_all([$a,$make('netlify','Microsoft 365 connector errors',11000)],$now);
chk(isset($separated['microsoft365']) && $separated['microsoft365']['correlation_tier']==='related_reports','onsets hours apart get WATCH, not simultaneous correlation');
chk(DependencyCorrelations::detect_all([$a,$make('netlify','Microsoft 365 integration announcement',1200)],$now)===[],'ordinary dependency mention does not prove a fault');
$resolved=$b;$resolved['status']='resolved';
chk(DependencyCorrelations::detect_all([$a,$resolved],$now)===[],'resolved issue cannot inflate current evidence');
$maintenance=$b;$maintenance['title']='Scheduled maintenance for Outlook connector';
chk(DependencyCorrelations::detect_all([$a,$maintenance],$now)===[],'planned work excluded');
// Real-world wording: a generic "Microsoft Integrations" incident only
// becomes evidence when the provider explicitly cites an underlying vendor.
// These incidents are active together, but started several hours apart.
$netlify = $make('netlify','Delayed Support Email Delivery to Microsoft 365 and Outlook Mailboxes',7*HOUR_IN_SECONDS);
$zoom = $make('zoom','Service Degradation Affecting Microsoft Integrations due to an underlying vendor issue.',14*HOUR_IN_SECONDS);
$watch = DependencyCorrelations::detect_all([$netlify,$zoom],$now);
chk(isset($watch['microsoft365']), 'Netlify and Zoom active incidents should appear as a related Microsoft watch');
chk($watch['microsoft365']['correlation_tier']==='related_reports', 'distant onsets must not masquerade as a simultaneous cluster');
chk($watch['microsoft365']['confidence']==='watch', 'watch confidence is distinct from onset cluster');
chk($watch['microsoft365']['count']===2, 'related watch links both independent providers');
chk(count(array_filter($watch['microsoft365']['evidence'],static fn($row):bool=>($row['evidence_type']??'')==='vendor_linked_integration'))===1, 'ambiguous Microsoft integration wording requires cited vendor dependency');
$ambiguous=$make('zoom','Service Degradation Affecting Microsoft Integrations',14*HOUR_IN_SECONDS);
chk(DependencyCorrelations::detect_all([$netlify,$ambiguous],$now)===[], 'generic Microsoft integrations mention without vendor attribution is insufficient');
$notActive=$zoom; $notActive['active']=false;
chk(DependencyCorrelations::detect_all([$netlify,$notActive],$now)===[], 'inactive provider incident must not maintain related watch');
$farZoom=$zoom; $farZoom['first_detected']=$now-20*HOUR_IN_SECONDS;
chk(DependencyCorrelations::detect_all([$netlify,$farZoom],$now)===[], 'more than 12 hours apart cannot form an active watch');
$watchMail=$watch['microsoft365'];
$watchMail['first_detected']=$now-1200;
$watchMail['timestamp']=$now-60;
$GLOBALS['options']['lousy_outages_email']='owner@example.test';
$beforeWatchEmail=count($GLOBALS['sent']);
$watchResult=DependencyCorrelations::send_alerts($now,['microsoft365'=>$watchMail]);
chk($watchResult['accepted']===0 && $watchResult['skipped_related_watch']===1 && count($GLOBALS['sent'])===$beforeWatchEmail, 'weaker watch never sends urgent correlated email even when fresh');

$other=$make('vercel','Cloudflare network errors disrupting builds',2000);
$other2=$make('github','Cloudflare DNS connection failures impacting deployments',2200);
chk(isset(DependencyCorrelations::detect_all([$other,$other2],$now)['cloudflare']),'engine extends to explicit other upstream providers');

$state=['signals'=>[],'providers'=>[],'meta'=>[]];
$state=DependencyCorrelations::augment_state($state,$results);
chk(count($state['signals'])===1 && $state['signals'][0]['verification_status']==='unconfirmed','board signal remains unconfirmed');
chk($state['signals'][0]['correlation_count']===2,'board surfaces independent source count');
$state=DependencyCorrelations::augment_state($state,$results);
chk(count($state['signals'])===1,'repeated aggregation does not add duplicate tiles');
$cloudflareHit=DependencyCorrelations::detect_all([$other,$other2],$now)['cloudflare'];
$alreadyTracked=['signals'=>[],'outages'=>[],'providers'=>[['provider_id'=>'cloudflare','name'=>'Cloudflare','status'=>'operational']],'meta'=>[]];
$observed=DependencyCorrelations::augment_state($alreadyTracked,['cloudflare'=>$cloudflareHit]);
chk(count($observed['signals'])===1 && count($observed['providers'])===1,'existing upstream provider remains green while downstream correlation is visible');
$confirmed=['signals'=>[],'outages'=>[['provider_id'=>'cloudflare','title'=>'Official Cloudflare incident']],'providers'=>[],'meta'=>[]];
$verified=DependencyCorrelations::augment_state($confirmed,['cloudflare'=>$cloudflareHit]);
chk($verified['signals']===[],'direct upstream official incident suppresses redundant correlated signal');

$GLOBALS['options']['lousy_outages_email']='owner@example.test';
$first=DependencyCorrelations::send_alerts($now,$results);
chk($first['accepted']===2,'new evidence sends opt-in subscriber and configured owner correlation email');
chk(count($GLOBALS['sent'])===2,'two recipients see one correlation each');
chk(str_contains($GLOBALS['sent'][0]['text'],'NOT an upstream-confirmed incident'),'email explains uncertainty');
chk(str_contains($GLOBALS['sent'][0]['text'],'Netlify') && str_contains($GLOBALS['sent'][0]['text'],'Zoom'),'email contains independent notices');
chk(str_contains($GLOBALS['sent'][0]['text'],'Unsubscribe:'),'subscriber can unsubscribe');
$second=DependencyCorrelations::send_alerts($now+60,$results);
chk($second['accepted']===0 && count($GLOBALS['sent'])===2,'repeat scan cannot re-email same episode');
$old = DependencyCorrelations::detect_all([$make('zoom','Microsoft 365 connector errors',8000),$make('netlify','Microsoft 365 connector errors',8100)],$now);
$stale=DependencyCorrelations::send_alerts($now,$old);
chk($stale['accepted']===0,'historical correlation does not cause retroactive email blast');
// A failed recipient remains pending and is retried, while accepted recipients are not resent.
$GLOBALS['options']['lo_dependency_correlation_delivery_v1']=[];
$GLOBALS['sent']=[];
$GLOBALS['fail_next']=true;
$f=DependencyCorrelations::send_alerts($now,$results);
chk($f['accepted']===1 && $f['failed']===1,'failed first recipient does not suppress others');
$retry=DependencyCorrelations::send_alerts($now+30,$results);
chk($retry['accepted']===1 && count($GLOBALS['sent'])===3,'only failed recipient retried');
echo "Shared dependency detector and correlation email regression passed\n";
