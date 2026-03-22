const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('minimap keeps a north-up frame and rotates only the player arrow', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.match(src, /function getPlayerArrowAngle\(\) \{/);
  assert.match(src, /return Math\.atan2\(-heading\.z, heading\.x\);/);
  assert.match(src, /const centerX = metrics\.minX \+ \(metrics\.width \/ 2\);/);
  assert.match(src, /const centerZ = metrics\.minZ \+ \(metrics\.height \/ 2\);/);
  assert.match(src, /ctx\.fillText\('N', minimapState\.width \/ 2, 9\);/);
  assert.match(src, /ctx\.fillText\('W', 10, minimapState\.height \/ 2\);/);
});

test('committed world attaches Steam Clock and Water/Cambie structure to minimap corridor data', () => {
  const worldPath = path.join(__dirname, '..', 'assets', 'world', 'gastown-water-street.json');
  const world = JSON.parse(fs.readFileSync(worldPath, 'utf8'));
  const focusIds = new Set((world.navigator && world.navigator.focusCorridor || []).map((segment) => segment.id));

  ['water-street-west-leg', 'water-street-east-leg', 'cambie-crossing', 'steam-clock-plaza-loop'].forEach((id) => {
    assert.equal(focusIds.has(id), true, id + ' should exist on the minimap corridor graph');
  });

  const steamClock = world.nodes.find((node) => node.id === 'steam-clock');
  const intersection = world.nodes.find((node) => node.id === 'water-cambie-intersection');
  assert.ok(steamClock);
  assert.ok(intersection);
  assert.equal(Math.hypot(steamClock.x - intersection.x, steamClock.z - intersection.z) > 4, true);
});
