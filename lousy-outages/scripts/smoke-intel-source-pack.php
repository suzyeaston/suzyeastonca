<?php
declare(strict_types=1);

function get_option(string $key, $default = false) { return $default; }
function apply_filters(string $hook, $value) { return $value; }

require_once dirname(__DIR__) . '/includes/SignalSourceInterface.php';
require_once dirname(__DIR__) . '/includes/ExternalSignals.php';
require_once dirname(__DIR__) . '/includes/Sources/SourcePack.php';
require_once dirname(__DIR__) . '/includes/Sources/ProviderFeedSource.php';
require_once dirname(__DIR__) . '/includes/Sources/SourceBudgetManager.php';
require_once dirname(__DIR__) . '/includes/Sources/StatuspageIntelSource.php';

use SuzyEaston\LousyOutages\Sources\ProviderFeedSource;
use SuzyEaston\LousyOutages\Sources\SourcePack;
use SuzyEaston\LousyOutages\Sources\StatuspageIntelSource;

$errors=[];
if(count(SourcePack::statuspage_base_urls()) < 5) $errors[]='statuspage urls <5';
if(count(SourcePack::provider_feed_urls()) < 5) $errors[]='feed urls <5';
if(count(SourcePack::early_warning_queries()) < 20) $errors[]='queries <20';
if(!(new StatuspageIntelSource())->is_configured()) $errors[]='statuspage source is not configured';
if(!(new ProviderFeedSource())->is_configured()) $errors[]='provider feed source is not configured';
if(!empty($errors)){ fwrite(STDERR, implode("\n",$errors)."\n"); exit(1);} echo "ok\n";
