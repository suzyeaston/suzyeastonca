<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
$canonical = 'lousy-outages/lousy-outages.php';
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$pluginHeaders = [];
$mainFiles = [];
foreach ($rii as $file) {
    if (!$file->isFile()) { continue; }
    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
    if (str_starts_with($path, '.git/') || str_starts_with($path, 'node_modules/') || str_starts_with($path, 'dist/')) { continue; }
    if (basename($path) === 'lousy-outages.php') { $mainFiles[] = $path; }
    $body = file_get_contents($file->getPathname(), false, null, 0, 4096);
    if (is_string($body) && str_contains($body, ('Plugin Name: '.'Lousy Outages'))) { $pluginHeaders[] = $path; }
}
$fail = static function(string $message): void { fwrite(STDERR, "FAIL: $message\n"); exit(1); };
if ($pluginHeaders !== [$canonical]) { $fail('expected only canonical Lousy Outages plugin header, found: '.json_encode($pluginHeaders)); }
if (!in_array($canonical, $mainFiles, true) || count(array_filter($mainFiles, fn($p) => $p !== $canonical)) > 0) { $fail('unexpected lousy-outages.php files: '.json_encode($mainFiles)); }
echo json_encode(['ok'=>true,'plugin_headers'=>$pluginHeaders], JSON_PRETTY_PRINT), "\n";
