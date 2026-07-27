<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../lousy-outages/includes/Entitlements.php';
use SuzyEaston\LousyOutages\Entitlements;

$assert=static function(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); };
$assert(Entitlements::allows('free','public_dashboard'),'Free must retain the public dashboard');
$assert(Entitlements::allows('free','rss'),'Free must retain RSS');
$assert(!Entitlements::allows('free','watchlists'),'Free must not save watchlists');
$assert(Entitlements::allows('pro','watchlists'),'Pro must save watchlists');
$assert(Entitlements::destination_limit('pro')===1,'Pro must allow exactly one destination');
$assert(!Entitlements::allows('pro','api_tokens'),'Pro must not generate API tokens');
$assert(Entitlements::allows('team','api_tokens'),'Team must generate API tokens');
$assert(Entitlements::allows('team','private_board'),'Team must expose the private board scaffold');
$assert(Entitlements::normalize_plan('garbage')==='free','Unknown plans must fail closed');
echo "ok - entitlement matrix and fail-closed plan normalization\n";
