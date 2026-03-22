const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

test('ground rendering no longer uses scaled street polygon curb hack', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('curb.scale.set(1.008, 1.008, 1.008)'), false);
  assert.equal(src.includes('new THREE.Line(new THREE.BufferGeometry().setFromPoints(borderPoints), visualState.curbMaterial)'), true);
});

test('ground rendering builds curb border lines from closed polygon points helper', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('function buildClosedBorderPoints(points, y)'), true);
  assert.equal(src.includes('borderPoints.push(new THREE.Vector3(first.x, y, first.z));'), true);
  assert.equal(src.includes('const borderPoints = buildClosedBorderPoints(zone.polygon, 0.16);'), true);
});

test('route guidance uses a single subtle marker treatment instead of centerline plus stripe clutter', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes('const routePoints = world.route.centerline.map((point) => new THREE.Vector3(point.x, 0.14, point.z));'), false);
  assert.equal(src.includes('const line = new THREE.Line(new THREE.BufferGeometry().setFromPoints(routePoints), visualState.laneMaterial);'), false);
  assert.equal(src.includes('new THREE.PlaneGeometry(0.18, 1.9)'), false);
  assert.equal(src.includes('new THREE.PlaneGeometry(0.7, 2.2)'), true);
});

test('sim default mode falls back to bright morning clear startup values', () => {
  const simPath = path.join(__dirname, '..', 'js', 'gastown-sim.js');
  const src = fs.readFileSync(simPath, 'utf8');

  assert.equal(src.includes("activeWeather: config.defaultWeather || 'clear'"), true);
  assert.equal(src.includes("activeTimeOfDay: config.defaultTimeOfDay || 'morning'"), true);
});
