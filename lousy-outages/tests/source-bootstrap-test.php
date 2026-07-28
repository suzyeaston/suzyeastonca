<?php
declare(strict_types=1);

$bootstrap = file_get_contents(dirname(__DIR__) . '/lousy-outages.php');
if ($bootstrap === false) {
    fwrite(STDERR, "FAIL: could not read plugin bootstrap\n");
    exit(1);
}

$includes = [
    'includes/SignalSourceInterface.php',
    'includes/ExternalSignals.php',
    'includes/Sources/SourcePack.php',
    'includes/Sources/ProviderFeedSource.php',
    'includes/Sources/StatuspageIntelSource.php',
];
$positions = [];
foreach ($includes as $include) {
    $needle = "lousy_outages_require( '" . $include . "'";
    $position = strpos($bootstrap, $needle);
    if ($position === false) {
        fwrite(STDERR, "FAIL: missing bootstrap include: {$include}\n");
        exit(1);
    }
    $positions[$include] = $position;
}

$sourcePack = $positions['includes/Sources/SourcePack.php'];
foreach (['includes/Sources/ProviderFeedSource.php', 'includes/Sources/StatuspageIntelSource.php'] as $concreteSource) {
    if ($sourcePack >= $positions[$concreteSource]) {
        fwrite(STDERR, "FAIL: SourcePack must load before {$concreteSource}\n");
        exit(1);
    }
}
if ($positions['includes/SignalSourceInterface.php'] >= $sourcePack || $positions['includes/ExternalSignals.php'] >= $sourcePack) {
    fwrite(STDERR, "FAIL: SourcePack must load after its signal infrastructure dependencies\n");
    exit(1);
}

echo "source bootstrap dependency test passed\n";
