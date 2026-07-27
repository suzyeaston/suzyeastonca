<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
if(!function_exists('get_option')) { function get_option($key,$default=false){ return $default; } }
require_once __DIR__.'/../lousy-outages/includes/StripeBilling.php';
use SuzyEaston\LousyOutages\StripeBilling;

$payload='{"id":"evt_123","type":"checkout.session.completed"}'; $secret='whsec_test'; $timestamp=1700000000;
$valid=hash_hmac('sha256',$timestamp.'.'.$payload,$secret);
if(!StripeBilling::valid_signature($payload,"t={$timestamp},v1={$valid}",$secret,$timestamp)) throw new RuntimeException('Valid Stripe signature rejected');
if(StripeBilling::valid_signature($payload,"t={$timestamp},v1=bad",$secret,$timestamp)) throw new RuntimeException('Invalid signature accepted');
if(StripeBilling::valid_signature($payload,"t={$timestamp},v1={$valid}",$secret,$timestamp+301)) throw new RuntimeException('Replay outside tolerance accepted');
if(StripeBilling::valid_signature($payload.'x',"t={$timestamp},v1={$valid}",$secret,$timestamp)) throw new RuntimeException('Tampered body accepted');
echo "ok - Stripe webhook signature, tamper, and replay handling\n";
