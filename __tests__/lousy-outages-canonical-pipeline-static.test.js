const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');
const plugin=readFileSync('lousy-outages/lousy-outages.php','utf8');
const pipe=readFileSync('lousy-outages/includes/Cron/CanonicalPipeline.php','utf8');
const cron=readFileSync('lousy-outages/includes/Cron/Refresh.php','utf8');

test('one authoritative 15 minute cadence drives schedule health and lease',()=>{
 assert.match(pipe,/canonical_interval', 15 \* MINUTE_IN_SECONDS/);
 assert.match(plugin,/\$interval = CanonicalPipeline::cadence\(\)/);
 assert.match(cron,/'interval' => 15 \* MINUTE_IN_SECONDS/);
 assert.match(pipe,/self::budget\(\) \+ 20/);
});
test('healthy recurring schedule is preserved and recovery is a distinct singleton',()=>{
 assert.match(plugin,/if \( ! wp_next_scheduled\( CanonicalPipeline::RECURRING_HOOK \) \)/);
 assert.doesNotMatch(plugin,/function lousy_outages_schedule_canonical_refresh[\s\S]{0,250}wp_clear_scheduled_hook/);
 assert.match(plugin,/wp_next_scheduled\( CanonicalPipeline::RECOVERY_HOOK \)/);
});
test('provider batches persist cursor and partial batches only continue',()=>{
 assert.match(pipe,/provider_batch_size', 4/);assert.match(pipe,/saveCycle\(\$cycle\)/);
 const partial=pipe.slice(pipe.indexOf("if ((int)$cycle['provider_cursor'] <"),pipe.indexOf("self::$invocation['phase'] = 'snapshot_commit'"));
 assert.match(partial,/CONTINUATION_HOOK/);assert.doesNotMatch(partial,/process_snapshot|refresh_status_feed_cache|commit_collected_states/);
});
test('owner lease blocks overlap, permits expiry reclamation, and shutdown checkpoints interruption',()=>{
 assert.match(pipe,/expires_at'\]\?\?0\)>\$now\) return false/);assert.match(pipe,/hash_equals/);
 assert.match(pipe,/last_interrupted_invocation/);assert.match(pipe,/register_shutdown_function/);
});
