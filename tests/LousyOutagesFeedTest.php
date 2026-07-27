<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if(!defined('DAY_IN_SECONDS'))define('DAY_IN_SECONDS',86400);
if(!defined('YEAR_IN_SECONDS'))define('YEAR_IN_SECONDS',31536000);
$GLOBALS['lo_test_options']=[];
if(!function_exists('get_option')){function get_option($key,$default=false){return $GLOBALS['lo_test_options'][$key]??$default;}}
if(!function_exists('update_option')){function update_option($key,$value,$autoload=null){$GLOBALS['lo_test_options'][$key]=$value;return true;}}
if(!function_exists('delete_option')){function delete_option($key){unset($GLOBALS['lo_test_options'][$key]);return true;}}
if(!function_exists('wp_strip_all_tags')){function wp_strip_all_tags($value){return strip_tags((string)$value);}}

require_once __DIR__.'/../lousy-outages/includes/Storage/IncidentStore.php';
require_once __DIR__.'/../lousy-outages/includes/Feeds.php';
use SuzyEaston\LousyOutages\Feeds;

function assert_true(bool $condition,string $message):void{if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
function build(array $replace=[]):array{
    $base=['now'=>strtotime('2026-01-10T12:00:00Z'),'persist_identities'=>true,'providers'=>['acme'=>['name'=>'Acme','status_url'=>'https://status.acme.test']],'states'=>[],'incidents'=>[],'fused_signals'=>[],'external_signals'=>[],'last_poll'=>'2026-01-10T12:00:00Z'];
    return Feeds::get_status_feed_items(array_replace($base,$replace));
}
function item_with(array $items,string $category):?array{foreach($items as $item)if(in_array($category,$item['categories'],true))return$item;return null;}

// One provider episode survives mutable poll timestamps and severity escalation.
[$a]=build(['states'=>['acme'=>['status'=>'degraded','checked_at'=>'2026-01-10T11:55:00Z','updated_at'=>'2026-01-10T11:55:00Z']]]);$p1=item_with($a,'current-provider-state');
[$b]=build(['now'=>strtotime('2026-01-10T12:30:00Z'),'states'=>['acme'=>['status'=>'outage','checked_at'=>'2026-01-10T12:30:00Z','updated_at'=>'2026-01-10T12:30:00Z']]]);$p2=item_with($b,'current-provider-state');
assert_true($p1['guid']===$p2['guid']&&$p1['pubDate']===$p2['pubDate'],'repeated provider poll changed identity/date');
assert_true(strpos($p2['description'],'Last checked')===false,'provider description remains volatile');

// Recovery closes the provider episode and a later unhealthy transition starts another.
build(['now'=>strtotime('2026-01-10T13:00:00Z'),'states'=>['acme'=>['status'=>'operational']]]);
[$c]=build(['now'=>strtotime('2026-01-10T14:00:00Z'),'last_poll'=>'2026-01-10T14:00:00Z','states'=>['acme'=>['status'=>'degraded']]]);$p3=item_with($c,'current-provider-state');
assert_true($p3['guid']!==$p1['guid'],'provider recurrence reused the prior episode');

$incident=['provider'=>'acme','provider_label'=>'Acme','guid'=>'source-42','title'=>'API unavailable','description'=>'Original','severity'=>'major','status'=>'incident','first_seen'=>strtotime('2026-01-09T08:00:00Z'),'last_seen'=>strtotime('2026-01-10T11:00:00Z'),'published'=>'2026-01-09 08:00:00 UTC'];
[$d]=build(['states'=>['acme'=>['status'=>'outage']],'incidents'=>[$incident]]);$i1=item_with($d,'official_incident');
$changed=array_replace($incident,['last_seen'=>strtotime('2026-01-10T12:00:00Z'),'updated_at'=>'2026-01-10T12:00:00Z','title'=>'API and dashboard unavailable','description'=>'More systems affected','severity'=>'critical']);
[$e]=build(['incidents'=>[$changed]]);$i2=item_with($e,'official_incident');
assert_true($i1['guid']===$i2['guid']&&$i1['pubDate']===$i2['pubDate'],'official update changed identity/date');
assert_true($i1['title']!==$i2['title']&&$i1['description']!==$i2['description'],'official content did not update');
assert_true(item_with($d,'current-provider-state')===null,'provider fallback duplicated official incident');
$new=array_replace($incident,['guid'=>'source-43','first_seen'=>strtotime('2026-01-10T10:00:00Z'),'title'=>'New incident']);[$f]=build(['incidents'=>[$incident,$new]]);
assert_true(count($f)===2&&$f[0]['guid']!==$f[1]['guid']&&$f[0]['pubDate']==='Sat, 10 Jan 2026 10:00:00 +0000','new official incident/order incorrect');

// Fused rows win over their raw observations, and a 120-minute quiet gap resets an episode.
$signal=['provider_id'=>'acme','provider_name'=>'Acme','classification'=>'watch','first_seen_at'=>'2026-01-10T10:30:00Z','last_seen_at'=>'2026-01-10T11:00:00Z'];
[$g,,$gd]=build(['fused_signals'=>[$signal],'external_signals'=>[['provider_id'=>'acme','severity'=>'watch','observed_at'=>'2026-01-10T10:55:00Z']]]);$s1=item_with($g,'unconfirmed');
$signal['last_seen_at']='2026-01-10T11:50:00Z';[$h]=build(['fused_signals'=>[$signal]]);$s2=item_with($h,'unconfirmed');
assert_true($s1['guid']===$s2['guid']&&$s1['pubDate']===$s2['pubDate'],'ongoing signal changed identity/date');
assert_true(count($g)===1&&$gd['excluded']['cross_source_duplicates']===1,'fused/raw duplicate was published');
$later=array_replace($signal,['first_seen_at'=>'2026-01-10T14:00:00Z','last_seen_at'=>'2026-01-10T14:01:00Z']);[$j]=build(['now'=>strtotime('2026-01-10T14:05:00Z'),'fused_signals'=>[$later]]);$s3=item_with($j,'unconfirmed');assert_true($s3['guid']!==$s1['guid'],'signal did not reset after quiet interval');

// Fingerprints ignore polling-only fields (not present in payload), but include new items.
[$same1]=build(['incidents'=>[$incident]]);[$same2]=build(['incidents'=>[array_replace($incident,['last_seen'=>strtotime('2026-01-10T12:00:00Z'),'updated_at'=>'2026-01-10T12:00:00Z'])]]);
assert_true(Feeds::feed_content_fingerprint($same1)===Feeds::feed_content_fingerprint($same2),'poll-only update changed feed fingerprint');
assert_true(Feeds::feed_content_fingerprint($same1)!==Feeds::feed_content_fingerprint($f),'new incident did not change feed fingerprint');

// Output stays bounded and stale identity entries are pruned.
$many=[];for($n=0;$n<25;$n++)$many[]=array_replace($incident,['guid'=>'many-'.$n,'first_seen'=>strtotime('2026-01-10T00:00:00Z')+$n]);[$limited]=build(['incidents'=>$many]);assert_true(count($limited)===15,'feed item limit not retained');
$map=['providers'=>['stale'=>['touched_at'=>1]],'signals'=>['stale'=>['touched_at'=>1]]];[,,$pruned]=build(['identity_map'=>$map,'persist_identities'=>false]);assert_true($pruned['identity_map_size']===0,'identity map was not pruned');

echo "OK: 17 behavioural RSS assertions\n";
