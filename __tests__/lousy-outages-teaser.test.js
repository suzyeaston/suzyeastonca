const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function load() {
  const listeners = {};
  const sandbox = { window: {}, document: { addEventListener: (n, cb) => { listeners[n] = cb; }, getElementById: () => null, hidden: false }, console, URL, Date, setInterval: () => 1 };
  sandbox.window = sandbox;
  vm.runInNewContext(fs.readFileSync('assets/js/lousy-outages-teaser.js', 'utf8'), sandbox);
  return sandbox.window.LousyOutagesTeaser;
}

test('selector includes active incident, limits and dedupes', () => {
  const teaser = load();
  const providers = Array.from({ length: 6 }, (_, i) => ({ id: `p${i}`, name: `P${i}`, tile_kind: 'outage', stateCode: 'degraded', updatedAt: `2026-07-19T0${i}:00:00Z`, incidents: [{ title: `Incident ${i}`, status: 'investigating', startedAt: `2026-07-19T0${i}:00:00Z`, updatedAt: `2026-07-19T0${i}:30:00Z` }] }));
  providers.push({ id: 'p0', name: 'P0', tile_kind: 'outage', stateCode: 'degraded', incidents: [{ title: 'Incident 0 details', status: 'identified', startedAt: '2026-07-19T00:00:10Z', updatedAt: '2026-07-19T00:40:00Z' }] });
  const items = teaser.currentItems({ providers });
  assert.equal(items.length, 5);
  assert.equal(items[0].provider, 'P5');
  assert.equal(items.filter((i) => i.provider === 'P0').length, 0);
});

test('clear, degraded signal, unknown and resolved states are distinct', () => {
  const teaser = load();
  assert.equal(teaser.currentItems({ providers: [{ id: 'ok', name: 'OK', tile_kind: 'operational', stateCode: 'operational', incidents: [] }] }).length, 0);
  assert.equal(teaser.currentItems({ providers: [{ id: 'slow', name: 'Slow', tile_kind: 'signal', stateCode: 'degraded', summary: 'Latency', incidents: [] }] })[0].type, 'signal');
  assert.equal(teaser.currentItems({ providers: [{ id: 'u', name: 'Unknown', tile_kind: 'unknown', stateCode: 'unknown', error: 'failed', incidents: [] }] }).length, 0);
  assert.equal(teaser.currentItems({ providers: [{ id: 'r', name: 'Resolved', tile_kind: 'operational', stateCode: 'operational', incidents: [{ title: 'Old', status: 'resolved' }] }] }).length, 0);
});
