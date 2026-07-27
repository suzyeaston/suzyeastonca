const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const sizes = [
  { width: 435, height: 900 },
  { width: 768, height: 1024 },
  { width: 1440, height: 1000 },
  { width: 1920, height: 1080 },
];
async function openDashboard(page, size) {
  await page.setViewportSize(size);
  await page.goto('/lousy-outages/', { waitUntil: 'networkidle' });
  await expect(page.locator('.lousy-outages')).toBeVisible();
}

test('monitored services cards and operational rows remain intrinsic', async ({ page }) => {
  fs.mkdirSync('test-results/lousy-outages-screenshots', { recursive: true });
  for (const size of sizes) {
    await openDashboard(page, size);
    await page.screenshot({ path: `test-results/lousy-outages-screenshots/${size.width}x${size.height}.png`, fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), `${size.width}px horizontal overflow`).toBeLessThanOrEqual(1);
  }

  await openDashboard(page, sizes[2]);
  const desktop = await page.evaluate(() => {
    const cards = [...document.querySelectorAll('.lo-service-card')];
    const list = document.querySelector('.lo-attention-services__grid');
    const safe = cards.every((card) => {
      const cardBox = card.getBoundingClientRect();
      const badge = card.querySelector('.lo-service-card__badge').getBoundingClientRect();
      const meta = card.querySelector('.lo-service-card__meta').getBoundingClientRect();
      const diagnostic = card.querySelector('.lo-service-card__diagnostic').getBoundingClientRect();
      return badge.left >= cardBox.left && badge.right <= cardBox.right + 1 &&
        !(badge.left < meta.right && badge.right > meta.left && badge.top < meta.bottom && badge.bottom > meta.top) &&
        diagnostic.width >= cardBox.width - 40 && cardBox.right <= list.getBoundingClientRect().right + 1;
    });
    return {
      count: cards.length,
      columns: getComputedStyle(list).gridTemplateColumns.split(' ').length,
      safe,
      operationalColumns: document.querySelectorAll('.lo-operational-table thead th').length,
      localLabels: [...document.querySelectorAll('.lo-service-card')].every((card) => card.textContent.match(/Checked by Lousy Outages:/g)?.length === 1),
      providerUpdatesSeparate: [...document.querySelectorAll('.lo-service-card__meta dt')].every((dt) => dt.textContent !== 'Last checked'),
    };
  });
  expect(desktop.count).toBeGreaterThan(0);
  expect(desktop.columns).toBe(2);
  expect(desktop.safe).toBe(true);
  expect(desktop.operationalColumns).toBe(4);
  expect(desktop.localLabels).toBe(true);
  expect(desktop.providerUpdatesSeparate).toBe(true);

  await openDashboard(page, sizes[0]);
  const mobile = await page.evaluate(() => ({
    columns: getComputedStyle(document.querySelector('.lo-attention-services__grid')).gridTemplateColumns.split(' ').length,
    headerColumns: getComputedStyle(document.querySelector('.lo-service-card__header')).gridTemplateColumns.split(' ').length,
    labelledOperational: [...document.querySelectorAll('.lo-operational-table tbody td')].every((cell) => getComputedStyle(cell, '::before').content.replace(/["']/g, '').length > 0),
    controlsFit: [...document.querySelectorAll('[data-lo-services] input, [data-lo-services] select')].every((node) => node.getBoundingClientRect().right <= innerWidth + 1),
  }));
  expect(mobile.columns).toBe(1);
  expect(mobile.headerColumns).toBe(1);
  expect(mobile.labelledOperational).toBe(true);
  expect(mobile.controlsFit).toBe(true);
});
