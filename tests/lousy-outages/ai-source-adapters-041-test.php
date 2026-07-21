<?php
if (!defined('MINUTE_IN_SECONDS')) define('MINUTE_IN_SECONDS',60);
require_once __DIR__ . '/../../lousy-outages/includes/ProviderRegistry.php';
require_once __DIR__ . '/../../lousy-outages/includes/Adapters.php';
use SuzyEaston\LousyOutages\ProviderRegistry;
use function SuzyEaston\LousyOutages\Adapters\from_better_stack_index;
function fail($m){fwrite(STDERR,$m."\n"); exit(1);} 
$providers=[]; foreach (ProviderRegistry::all() as $p) { $providers[$p['id']]=$p; }
if (($providers['anthropic']['status_url'] ?? '') !== 'https://status.claude.com/') fail('Anthropic must use Claude status URL');
if (($providers['huggingface']['adapter'] ?? '') !== 'better_stack') fail('Hugging Face must use Better Stack adapter');
foreach (['mistral','groq','replicate','elevenlabs'] as $id) { if (!empty($providers[$id]['enabled'])) fail($id.' must stay disabled until structured source is verified'); }
$parsed = from_better_stack_index(file_get_contents(__DIR__.'/../fixtures/lousy-outages/huggingface-better-stack-index.json'));
if (($parsed['state'] ?? '') !== 'degraded' || count($parsed['incidents'] ?? []) !== 1 || empty($parsed['schema_valid'])) fail('Better Stack fixture did not parse');
echo "ai-source-adapters-041 ok\n";
