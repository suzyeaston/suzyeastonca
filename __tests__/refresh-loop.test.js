const { test, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { setTimeout: wait } = require('node:timers/promises');
const { installMockFetch, buildDashboardDom } = require('./helpers');
const app = require('../lousy-outages/assets/lousy-outages.js');

let mockFetch;

async function flushPromises() {
  await Promise.resolve();
  await Promise.resolve();
}

beforeEach(() => {
  mockFetch = installMockFetch();
  mockFetch.mockClear();
  const dom = buildDashboardDom([
    { id: 'github', name: 'GitHub', statusCode: 'operational', statusLabel: 'Operational' }
  ]);
  global.document = dom.document;

  mockFetch.mockImplementation(() =>
    Promise.resolve({
      ok: true,
      json: () =>
        Promise.resolve({
          providers: [
            {
              id: 'github',
              provider: 'GitHub',
              name: 'GitHub',
              stateCode: 'operational',
              state: 'Operational',
              summary: '',
              incidents: [],
              updatedAt: new Date().toISOString()
            }
          ],
          meta: { fetchedAt: new Date().toISOString() }
        })
    })
  );
});

afterEach(() => {
  app.stopAutoRefresh();
  delete global.document;
  mockFetch.mockClear();
});

test('polls at configured interval without stacking timers', async () => {
  app.init({
    endpoint: '/api/outages',
    pollInterval: 220,
    providers: [{ id: 'github', name: 'GitHub' }],
    strings: {},
    fallbackStrings: {},
    fetchTimeout: 100,
    debug: true
  });

  await flushPromises();
  await wait(250);
  await flushPromises();
  const firstCount = mockFetch.callCount();
  assert.ok(firstCount >= 1);

  await wait(250);
  await flushPromises();
  const secondCount = mockFetch.callCount();
  assert.equal(secondCount, firstCount + 1);

  await wait(250);
  await flushPromises();
  const thirdCount = mockFetch.callCount();
  assert.equal(thirdCount, secondCount + 1);
});
