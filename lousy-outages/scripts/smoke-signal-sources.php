<?php
declare(strict_types=1);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/smoke-signal-sources.php /path/to/wp-load.php\n");
    exit(2);
}

$wpLoad = $argv[1];
if (!is_file($wpLoad)) {
    fwrite(STDERR, "wp-load.php not found at: {$wpLoad}\n");
    exit(2);
}

require_once $wpLoad;

use SuzyEaston\LousyOutages\SignalCollector;
use SuzyEaston\LousyOutages\SignalSourceInterface;

$sources = SignalCollector::sources();
$errors = [];

foreach ($sources as $index => $source) {
    if (!$source instanceof SignalSourceInterface) {
        $errors[] = sprintf('Source index %d (%s) is not a SignalSourceInterface instance.', $index, is_object($source) ? get_class($source) : gettype($source));
        continue;
    }

    $id = $source->id();
    if (!is_string($id) || trim($id) === '') {
        $errors[] = sprintf('%s::id() returned an empty/non-string value.', get_class($source));
    }

    $label = $source->label();
    if (!is_string($label) || trim($label) === '') {
        $errors[] = sprintf('%s::label() returned an empty/non-string value.', get_class($source));
    }

    $configured = $source->is_configured();
    if (!is_bool($configured)) {
        $errors[] = sprintf('%s::is_configured() did not return bool.', get_class($source));
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Signal source smoke test FAILED:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Signal source smoke test passed (%d sources).\n", count($sources)));
exit(0);
