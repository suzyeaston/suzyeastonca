<?php
declare(strict_types=1);
const DAY_IN_SECONDS=86400;
$GLOBALS['opts']=[];
function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;} function update_option($k,$v,$autoload=false){$GLOBALS['opts'][$k]=$v;return true;}
function sanitize_key($v){return trim(preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v)),'_-');}
function wp_generate_uuid4(){static $n=0;return sprintf('00000000-0000-4000-8000-%012d',++$n);}
require __DIR__.'/../includes/Model/Incident.php'; require __DIR__.'/../includes/Storage/EpisodeStore.php';
use SuzyEaston\LousyOutages\Model\Incident; use SuzyEaston\LousyOutages\Storage\EpisodeStore;
function incident($id,$title='API outage',$status='major_outage',$impact='critical',$url='https://status.test/incidents/1'){return new Incident('acme',$id,$title,$status,$url,null,$impact,1000,null);}
function ok($condition,$message){if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$store=new EpisodeStore();$first=$store->observe([incident('acme:one')],['acme'=>'major_outage'],1000);ok(count($first['opened'])===1,'first poll opens');$guid=$first['opened'][0]['episode_guid'];$pub=$first['opened'][0]['first_detected'];
$repeat=$store->observe([incident('acme:one','Renamed','investigating','minor')],['acme'=>'degraded'],1100);ok(count($repeat['opened'])===0&&$repeat['continuing'][0]['episode_guid']===$guid&&$repeat['continuing'][0]['first_detected']===$pub,'mutable fields retain episode');
$second=$store->observe([incident('acme:one','Renamed'),incident('acme:two','Second incident')],['acme'=>'outage'],1150);ok(count($second['opened'])===1,'distinct same-provider incident opens without cooldown');
$store->saveDelivery($guid,['a@example.test'],['b@example.test'],['a@example.test','b@example.test']);ok($store->pendingRecipients($guid,['a@example.test','b@example.test'])===['b@example.test'],'partial batch retries only failure');
$closed=$store->observe([],['acme'=>'operational'],1200);ok(count($closed['closed'])===2,'recovery closes active episodes');
$fallback=$store->observe([incident('acme:status:abc','Acme degraded','degraded','minor','https://status.test')],['acme'=>'degraded'],1300);$fallbackGuid=$fallback['opened'][0]['episode_guid'];
$store->observe([],['acme'=>'operational'],1400);$recurrence=$store->observe([incident('acme:status:def','Changed wording','major_outage','critical','https://status.test')],['acme'=>'major_outage'],1500);ok($recurrence['opened'][0]['episode_guid']!==$fallbackGuid,'fallback recurrence gets new GUID');
ok(count($store->publications(2))===2,'feed publications are bounded');
echo "incident episode behavioural tests passed\n";
