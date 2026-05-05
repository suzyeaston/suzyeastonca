<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/Sources/SourcePack.php';
require_once dirname(__DIR__) . '/includes/Sources/SourceBudgetManager.php';
require_once dirname(__DIR__) . '/includes/SignalSourceInterface.php';
require_once dirname(__DIR__) . '/includes/Sources/HackerNewsChatterSource.php';
require_once dirname(__DIR__) . '/includes/Sources/ProviderFeedSource.php';

use SuzyEaston\LousyOutages\Sources\SourcePack;

$errors=[];
if(count(SourcePack::statuspage_base_urls()) < 5) $errors[]='statuspage urls <5';
if(count(SourcePack::provider_feed_urls()) < 5) $errors[]='feed urls <5';
if(count(SourcePack::early_warning_queries()) < 20) $errors[]='queries <20';
if(!empty($errors)){ fwrite(STDERR, implode("\n",$errors)."\n"); exit(1);} echo "ok\n";
