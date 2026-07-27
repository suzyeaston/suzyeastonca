const { test, expect } = require('@playwright/test');
const fs = require('node:fs');

const sizes = [
  { width: 375, height: 812 },
  { width: 390, height: 844 },
  { width: 435, height: 900 },
  { width: 768, height: 1024 },
  { width: 1280, height: 900 },
];

async function openDashboard(page, size) {
  await page.setViewportSize(size);
  await page.goto('/lousy-outages/', { waitUntil: 'networkidle' });
  await expect(page.locator('.lousy-outages')).toBeVisible();
}

async function stylesheetSources(page, selectors) {
  return page.evaluate((wanted) => {
    const output = {};
    const visit = (rules, href, media = 'all') => {
      for (const rule of Array.from(rules || [])) {
        if (rule.cssRules) visit(rule.cssRules, href, rule.conditionText || media);
        if (!rule.selectorText) continue;
        for (const selector of wanted) {
          if (rule.selectorText.includes(selector)) {
            output[selector] ||= [];
            output[selector].push({ href, media, cssText: rule.cssText });
          }
        }
      }
    };
    for (const sheet of Array.from(document.styleSheets)) {
      try { visit(sheet.cssRules, sheet.href || 'inline'); } catch (_) { /* cross-origin sheet */ }
    }
    return output;
  }, selectors);
}

test('Lousy Outages layouts remain intrinsic at required viewport sizes', async ({ page }, testInfo) => {
  fs.mkdirSync('test-results/lousy-outages-screenshots', { recursive: true });
  for (const size of sizes) {
    await openDashboard(page, size);
    await page.screenshot({ path: `test-results/lousy-outages-screenshots/${size.width}x${size.height}.png`, fullPage: true });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${size.width}px viewport must not scroll horizontally`).toBeLessThanOrEqual(1);
  }

  await openDashboard(page, { width: 435, height: 900 });
  const computed = await page.evaluate(() => {
    const read = (selector, properties) => {
      const node = document.querySelector(selector);
      if (!node) return null;
      const style = getComputedStyle(node);
      return Object.fromEntries(properties.map((property) => [property, style.getPropertyValue(property)]));
    };
    return {
      incidents: read('.lo-grid--section[data-lo-section-grid="incidents"]', ['display', 'grid-template-columns', 'width']),
      services: read('.lo-services__row:not(.lo-services__row--head)', ['display', 'grid-template-columns', 'width']),
      dashboard: read('.lousy-outages', ['width', 'max-width', 'padding', 'font-size']),
      page: read('.lousy-outages-page', ['width', 'padding']),
      header: read('.main-header', ['position', 'height']),
      body: read('body', ['padding-top', 'overflow-x']),
    };
  });
  const sources = await stylesheetSources(page, ['.lo-grid--section[data-lo-section-grid="incidents"]', '.lo-services__row', '.lousy-outages', '.lousy-outages-page', '.main-header', 'body']);
  await testInfo.attach('435px-computed-styles.json', { body: JSON.stringify({ computed, sources }, null, 2), contentType: 'application/json' });

  expect(computed.incidents?.display).toBe('grid');
  expect(computed.incidents?.gridTemplateColumns.split(' ').length).toBe(1);
  expect(computed.services?.display).toBe('grid');
  expect(computed.services?.gridTemplateColumns.split(' ').length).toBe(1);

  const geometry = await page.evaluate(() => {
    const viewport = window.innerWidth;
    const dashboard = document.querySelector('.lousy-outages').getBoundingClientRect();
    const heading = document.querySelector('.lousy-outages-page h1').getBoundingClientRect();
    const header = document.querySelector('.main-header').getBoundingClientRect();
    const cards = [...document.querySelectorAll('.lo-card--incident')].map((node) => node.getBoundingClientRect()).map(({ left, right, top, bottom, width }) => ({ left, right, top, bottom, width }));
    const serviceRows = [...document.querySelectorAll('.lo-services__row:not(.lo-services__row--head)')];
    const formsFit = [...document.querySelectorAll('.lo-subscribe input, .lo-subscribe select, .lo-subscribe textarea, .lo-report input, .lo-report select, .lo-report textarea')]
      .every((node) => node.getBoundingClientRect().right <= node.parentElement.getBoundingClientRect().right + 1);
    const labelsVisible = serviceRows.every((row) => [...row.querySelectorAll('[role="cell"]')].every((cell) => getComputedStyle(cell, '::before').content.replace(/["']/g, '').length > 0));
    const footer = document.querySelector('.site-footer').getBoundingClientRect();
    return { viewport, dashboard, heading, header, cards, formsFit, labelsVisible, footerRight: footer.right };
  });
  expect(geometry.cards.length).toBeGreaterThan(0);
  for (let i = 0; i < geometry.cards.length; i += 1) {
    expect(geometry.cards[i].width).toBeLessThanOrEqual(geometry.dashboard.width + 1);
    expect(geometry.cards[i].right).toBeLessThanOrEqual(geometry.dashboard.right + 1);
    if (i) expect(geometry.cards[i].top).toBeGreaterThanOrEqual(geometry.cards[i - 1].bottom - 1);
  }
  expect(geometry.formsFit).toBe(true);
  expect(geometry.labelsVisible).toBe(true);
  expect(geometry.footerRight).toBeLessThanOrEqual(geometry.viewport + 1);
  expect(geometry.heading.top).toBeGreaterThanOrEqual(geometry.header.bottom - 1);

  await openDashboard(page, { width: 1280, height: 900 });
  const desktop = await page.evaluate(() => ({
    incidentColumns: getComputedStyle(document.querySelector('.lo-grid--section[data-lo-section-grid="incidents"]')).gridTemplateColumns.split(' ').length,
    serviceColumns: getComputedStyle(document.querySelector('.lo-services__row:not(.lo-services__row--head)')).gridTemplateColumns.split(' ').length,
    overflow: document.documentElement.scrollWidth - window.innerWidth,
  }));
  expect(desktop.incidentColumns).toBe(2);
  expect(desktop.serviceColumns).toBe(5);
  expect(desktop.overflow).toBeLessThanOrEqual(1);
});
