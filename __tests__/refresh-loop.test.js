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

test('manual refresh falls back to status endpoint when refresh endpoint fails', async () => {
  const now = new Date().toISOString();
  mockFetch.mockImplementation((url, options = {}) => {
    if (options && options.method === 'POST') {
      return Promise.resolve({
        ok: false,
        status: 401,
        json: () => Promise.resolve({})
      });
    }
    return Promise.resolve({
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
              updatedAt: now
            }
          ],
          meta: { fetchedAt: now }
        })
    });
  });

  app.init({
    endpoint: '/wp-json/lousy-outages/v1/status',
    pollInterval: 200,
    refreshEndpoint: '/wp-json/lousy/v1/refresh',
    refreshNonce: 'test-nonce',
    initial: {
      meta: { fetchedAt: now },
      providers: [
        {
          id: 'github',
          name: 'GitHub',
          state: 'Operational',
          summary: '',
          incidents: []
        }
      ]
    }
  });

  await flushPromises();

  const button = document.querySelector('.coin-btn');
  button.dispatchEvent({ type: 'click' });

  await flushPromises();
  await wait(50);
  await flushPromises();

  const calls = mockFetch.calls;
  assert.ok(calls.length >= 2);
  const refreshCall = calls.find(call => call[0] === '/wp-json/lousy/v1/refresh');
  assert.ok(refreshCall);
  const summaryCall = calls.find(call => {
    const target = String(call[0] || '');
    return target.includes('/wp-json/lousy-outages/v1/status') && target.includes('refresh=1');
  });
  assert.ok(summaryCall);

  const countdown = document.querySelector('.board-subtitle');
  assert.ok(!countdown.textContent.startsWith('Status fetch failed'));
  assert.equal(button.disabled, false);
});

test('manual refresh treats skipped lock as success and keeps timestamp aligned', async () => {
  const isoInitial = '2025-11-25T01:55:30-08:00';
  const isoUpdated = '2025-11-25T02:25:30-08:00';
  let fetchCount = 0;

  mockFetch.mockImplementation((url, options = {}) => {
    if (options && options.method === 'POST') {
      return Promise.resolve({
        ok: true,
        json: () =>
          Promise.resolve({
            ok: false,
            skipped: true,
            refreshedAt: isoUpdated,
            refreshed_at: Math.floor(new Date(isoUpdated).getTime() / 1000)
          })
      });
    }

    fetchCount += 1;
    const isRefresh = String(url || '').includes('refresh=1');
    const currentIso = isRefresh || fetchCount > 1 ? isoUpdated : isoInitial;
    return Promise.resolve({
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
              updatedAt: currentIso
            }
          ],
          meta: { fetchedAt: currentIso }
        })
    });
  });

  app.init({
    endpoint: '/api/outages',
    pollInterval: 200,
    refreshEndpoint: '/wp-json/lousy-outages/v1/refresh',
    refreshNonce: 'skip-nonce',
    providers: [{ id: 'github', name: 'GitHub' }],
    strings: {},
    fallbackStrings: {},
    fetchTimeout: 150,
    initial: {
      fetchedAt: isoInitial,
      meta: { fetchedAt: isoInitial },
      providers: [
        {
          id: 'github',
          name: 'GitHub',
          state: 'Operational',
          summary: '',
          incidents: []
        }
      ]
    }
  });

  await flushPromises();
  await wait(50);
  await flushPromises();

  const formatter = new Intl.DateTimeFormat(undefined, {
    weekday: 'short',
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });

  const lastUpdated = document.querySelector('.last-updated span');
  const initialText = lastUpdated.textContent;
  assert.equal(initialText, formatter.format(new Date(isoInitial)));

  const button = document.querySelector('.coin-btn');
  button.dispatchEvent({ type: 'click' });

  await flushPromises();
  await wait(120);
  await flushPromises();

  const refreshCall = mockFetch.calls.find(call => call[0] === '/wp-json/lousy-outages/v1/refresh');
  assert.ok(refreshCall);

  const updatedText = lastUpdated.textContent;
  assert.equal(updatedText, formatter.format(new Date(isoUpdated)));
  const countdown = document.querySelector('.board-subtitle');
  assert.ok(!countdown.textContent.toLowerCase().startsWith('status fetch failed'));
});
