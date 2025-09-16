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
              provider: 'github',
              name: 'GitHub',
              statusCode: 'operational',
              status: 'Operational',
              message: '',
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
    endpoint: '/api/status',
    pollInterval: 25,
    providers: [{ id: 'github', name: 'GitHub' }],
    strings: {},
    fallbackStrings: {},
    fetchTimeout: 100,
    debug: true
  });

  await flushPromises();
  assert.equal(mockFetch.callCount(), 1);

  await wait(30);
  await flushPromises();
  assert.equal(mockFetch.callCount(), 2);

  await wait(30);
  await flushPromises();
  assert.equal(mockFetch.callCount(), 3);
});
