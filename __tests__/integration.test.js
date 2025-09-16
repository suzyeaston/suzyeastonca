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
              provider: 'openai',
              name: 'OpenAI',
              statusCode: 'unknown',
              status: 'Unknown',
              message: 'Request timed out',
              error: 'timeout',
              updatedAt: now
            },
            {
              provider: 'github',
              name: 'GitHub',
              statusCode: 'operational',
              status: 'Operational',
              message: '',
              updatedAt: now
            }
          ],
          meta: { fetchedAt: now }
        })
    })
  );

  app.init({
    endpoint: '/api/status',
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

  const openAiRow = document.querySelector('tr[data-id="openai"] .status');
  const messageCell = document.querySelector('tr[data-id="openai"] .msg');
  assert.equal(openAiRow.textContent, 'Unknown');
  assert.ok(openAiRow.className.includes('status--unknown'));
  assert.equal(messageCell.textContent, 'Request timed out');
});
