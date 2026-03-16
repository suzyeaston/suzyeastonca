const test = require('node:test');
const assert = require('node:assert/strict');

const { parseHblockStreet, isTargetCorridorBlock, getArterialBias } = require('../scripts/generate-gastown-world');

function feature(hblock, streetuse = 'ARTERIAL') {
  return {
    properties: {
      hblock,
      streetuse,
    },
  };
}

test('parseHblockStreet parses direction/street/suffix from hblock', () => {
  const parsed = parseHblockStreet(feature('800-900 W CORDOVA ST'));
  assert.equal(parsed.street, 'w cordova st');
  assert.equal(parsed.block, '800-900');
});

test('target corridor matcher includes confirmed true positives', () => {
  const positives = [
    '0 W CORDOVA ST',
    '100 W CORDOVA ST',
    '300 W CORDOVA ST',
    '400 W CORDOVA ST',
    '500 W CORDOVA ST',
    '600 W CORDOVA ST',
    '700 W CORDOVA ST',
    '800-900 W CORDOVA ST',
    '0 WATER ST',
    '100 WATER ST',
    '200-300 WATER ST',
  ];

  positives.forEach((hblock) => {
    const parsed = parseHblockStreet(feature(hblock));
    assert.equal(isTargetCorridorBlock(parsed), true, hblock);
  });
});

test('target corridor matcher excludes confirmed false positives', () => {
  const negatives = [
    '0 E CORDOVA ST',
    '100 CORDOVA DIVERSION',
    '200 WATERLOO ST',
    '300 BAYSWATER ST',
    '0 WATERFRONT ROAD',
  ];

  negatives.forEach((hblock) => {
    const parsed = parseHblockStreet(feature(hblock));
    assert.equal(isTargetCorridorBlock(parsed), false, hblock);
  });
});

test('arterial bias prefers arterial segments', () => {
  assert.equal(getArterialBias(feature('100 W CORDOVA ST', 'ARTERIAL')), 75);
  assert.equal(getArterialBias(feature('100 W CORDOVA ST', 'LOCAL')), 0);
});
