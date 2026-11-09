const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../js/gastown-sim.js'), 'utf8');

test('menu is keyboard accessible even when the renderer holds pointer lock', () => {
  assert.match(source, /event\.key\.toLowerCase\(\) !== 'm'/);
  assert.match(source, /menu\.open = !menu\.open/);
  assert.match(source, /document\.exitPointerLock\(\)/);
  assert.match(source, /target\.isContentEditable/);
});

test('parking NPC ambience tells real parking-lot stories rather than generic high fives', () => {
  assert.match(source, /I extended my parking and still got a ticket/);
  assert.match(source, /The stalls are ridiculously narrow/);
  assert.match(source, /Great view of the port and North Van/);
  assert.match(source, /The app did not recognize the lot number/);
});
