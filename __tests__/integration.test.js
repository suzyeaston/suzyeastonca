const { test, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { setTimeout: wait } = require('node:timers/promises');
const { installMockFetch, buildDashboardDom } = require('./helpers');
const app = require('../lousy-outages/assets/lousy-outages.js');

let mockFetch;

async function flushPromises() {
  await Promise.resolve();
  await Promise.resolve();
  await wait(0);
}

beforeEach(() => {
  mockFetch = installMockFetch();
  mockFetch.mockClear();
  const dom = buildDashboardDom([
    { id: 'openai', name: 'OpenAI', statusCode: 'operational', statusLabel: 'Operational', message: 'All systems go' },
    { id: 'github', name: 'GitHub', statusCode: 'operational', statusLabel: 'Operational' }
  ]);
  global.document = dom.document;
});

afterEach(() => {
  app.stopAutoRefresh();
  delete global.document;
  mockFetch.mockClear();
});

test('renders Unknown when a provider times out', async () => {
  const now = new Date().toISOString();
  mockFetch.mockImplementation(() =>
    Promise.resolve({
      ok: true,
      json: () =>
        Promise.resolve({
          providers: [
            {
              id: 'openai',
              provider: 'OpenAI',
              name: 'OpenAI',
              stateCode: 'unknown',
              state: 'Unknown',
              summary: 'Request timed out',
              incidents: [],
              error: 'timeout',
              updatedAt: now
            },
            {
              id: 'github',
              provider: 'GitHub',
              name: 'GitHub',
              stateCode: 'operational',
              state: 'Operational',
              summary: '',
              incidents: [],
              updatedAt: now
            }
          ],
          meta: { fetchedAt: now }
        })
    })
  );

  app.init({
    endpoint: '/api/outages',
    pollInterval: 50,
    providers: [
      { id: 'openai', name: 'OpenAI' },
      { id: 'github', name: 'GitHub' }
    ],
    strings: {},
    fallbackStrings: {},
    debug: true,
    fetchTimeout: 100
  });

  await flushPromises();
  await wait(150);
  await flushPromises();

  const statusBadge = document.querySelector('.provider-card[data-id="openai"] .status-badge');
  const summary = document.querySelector('.provider-card[data-id="openai"] .provider-card__summary');
  assert.equal(statusBadge.textContent, 'Unknown');
  assert.ok(statusBadge.className.includes('status--unknown'));
  assert.equal(summary.textContent, 'Request timed out');
});
