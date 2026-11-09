'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const src = fs.readFileSync(path.join(__dirname, '..', 'js/gastown-sim.js'), 'utf8');

function getActualNpcHitTest() {
  const start = src.indexOf('  function findLookedAtNpc() {');
  const end = src.indexOf('  function updateInteractionTarget() {', start);
  assert.ok(start > 0 && end > start, 'NPC camera hit test is extractable');
  return new Function('state', 'camera', 'lookTarget', 'tempCameraWorldPosition',
    'sharedRaycaster', 'scoreNpcInteractionCandidate', 'THREE',
    src.slice(start, end) + '\nreturn findLookedAtNpc;');
}

test('NPC hit-testing supplies a camera when speech-bubble sprites are raycast', () => {
  const camera = {
    matrixWorld: { isMatrix4: true },
    getWorldDirection(out) { out.x = 0; out.y = 0; out.z = -1; },
    getWorldPosition(out) { out.x = 0; out.y = 1.5; out.z = 0; },
  };
  const group = {
    position: { x: 0, y: 0, z: -2 },
    children: [{ isSprite: true }, { isMesh: true }],
  };
  const npc = { id: 'chat-walker', mesh: group };
  const raycaster = {
    camera: null,
    set(origin, direction) { this.origin = origin; this.direction = direction; },
    intersectObject(object, recursive) {
      assert.equal(object, group);
      assert.equal(recursive, true);
      // Reproduce the Three.js Sprite.raycast invariant that failed in Chrome.
      assert.equal(this.camera, camera,
        'THREE.Sprite must have a camera, even for manually positioned rays');
      return [
        { distance: 0.6, object: group.children[0] },
        { distance: 1.2, object: group.children[1] },
      ];
    },
  };
  const get = getActualNpcHitTest();
  const hitTest = get(
    { npcs: [npc] },
    camera,
    {},
    {},
    raycaster,
    (target, options) => ({ npc: target, distance: 2, score: 3, ...options }),
    { Vector3: class {} },
  );
  const result = hitTest();
  assert.equal(result.npc, npc);
  assert.equal(result.hitDistance, 1.2, 'ignore the decorative bubble hit');
  assert.equal(result.rayHit, true);
  assert.equal(raycaster.camera, camera);
});

test('interaction raycast failure is isolated from subsequent WebGL rendering', () => {
  const start = src.indexOf('        // Optional NPC hit tests must never take down the 3D renderer.');
  const end = src.indexOf('        renderer.render(scene, camera);', start);
  assert.ok(start > 0 && end > start);
  const frame = src.slice(start, end);
  assert.match(frame, /if \(!state\.interactionRaycastUnavailable\)\s*\{\s*try\s*\{\s*updateInteractionTarget\(\)/);
  assert.match(frame, /catch \(error\) \{\s*state\.interactionRaycastUnavailable = true/);
  assert.match(frame, /state\.hoveredNpcId = '';/);
  assert.match(frame, /updateLandmarkDiscoveries\(\)/,
    'scene updates continue when picking is unavailable');
});
