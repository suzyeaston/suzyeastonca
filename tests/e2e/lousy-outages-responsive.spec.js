const { test, expect } = require('@playwright/test');
const fs = require('node:fs');
const sizes = [
  { width: 248, height: 2048 },
  { width: 435, height: 900 },
  { width: 768, height: 1024 },
  { width: 1440, height: 1000 },
  { width: 1920, height: 1080 },
];
async function openDashboard(page, size) {
  await page.setViewportSize(size);
  await page.goto(process.env.LO_RESPONSIVE_FIXTURE ? '/tests/fixtures/lousy-outages-responsive.html' : '/lousy-outages/', { waitUntil: 'networkidle' });
  await expect(page.locator('.lousy-outages')).toBeVisible();
}

test('monitored services cards and operational rows remain intrinsic', async ({ page }) => {
  fs.mkdirSync('test-results/lousy-outages-screenshots', { recursive: true });
  for (const size of sizes) {
    await openDashboard(page, size);
    await page.screenshot({ path: `test-results/lousy-outages-screenshots/${size.width}x${size.height}.png`, fullPage: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth - innerWidth), `${size.width}px horizontal overflow`).toBeLessThanOrEqual(1);
  }

  await openDashboard(page, sizes.find((size) => size.width === 1440));
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
  const desktopThemeIsolation = await page.evaluate(() => {
    const item = document.querySelector('.lo-attention-services__item').getBoundingClientRect();
    const card = document.querySelector('.lo-service-card').getBoundingClientRect();
    const prose = getComputedStyle(document.querySelector('.lo-service-card__diagnostic p'));
    return { itemWidth: item.width, cardWidth: card.width, fontFamily: prose.fontFamily,
      backgroundColor: prose.backgroundColor, wordBreak: prose.wordBreak, writingMode: prose.writingMode };
  });
  expect(Math.abs(desktopThemeIsolation.itemWidth - desktopThemeIsolation.cardWidth)).toBeLessThanOrEqual(1);
  expect(desktopThemeIsolation.fontFamily).toContain('Inter');
  expect(desktopThemeIsolation.backgroundColor).toBe('rgba(0, 0, 0, 0)');
  expect(desktopThemeIsolation.wordBreak).toBe('normal');
  expect(desktopThemeIsolation.writingMode).toBe('horizontal-tb');

  await openDashboard(page, sizes.find((size) => size.width === 435));
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

test('248px viewport stacks every dashboard grid without crushing prose', async ({ page }) => {
  await openDashboard(page, sizes[0]);
  const report = await page.evaluate(() => {
    const selectors = [
      '.lo-grid--section[data-lo-section-grid="incidents"]', '.lo-card--incident',
      '.lo-attention-services__grid', '.lo-attention-services__item', '.lo-service-card',
      '.lo-service-card__header', '.lo-service-card__meta', '.lo-service-card__diagnostic',
      '.lo-service-card__footer', '.lo-attention-summary', '.lo-history__controls',
      '.lo-operational-table', '.lousy-outages-page', '.lousy-outages-root', '.lousy-outages'
    ];
    const inspect = (selector) => {
      const node = document.querySelector(selector);
      if (!node) return null;
      const css = getComputedStyle(node);
      const box = node.getBoundingClientRect();
      return { selector, box: { x: box.x, y: box.y, width: box.width, height: box.height },
        display: css.display, gridTemplateColumns: css.gridTemplateColumns,
        gridAutoColumns: css.gridAutoColumns, gridAutoFlow: css.gridAutoFlow, gap: css.gap,
        width: css.width, minWidth: css.minWidth, maxWidth: css.maxWidth,
        overflow: css.overflow, contain: css.contain, containerType: css.containerType };
    };
    const text = ['.lo-service-card__title', '.lo-service-card__badge', '.lo-service-card__meta dd', '.lo-service-card__diagnostic p']
      .map((selector) => { const node = document.querySelector(selector); const css = getComputedStyle(node); const box = node.getBoundingClientRect();
        return { selector, width: box.width, whiteSpace: css.whiteSpace, overflowWrap: css.overflowWrap,
          wordBreak: css.wordBreak, writingMode: css.writingMode, fontSize: css.fontSize, lineHeight: css.lineHeight }; });
    return { viewport: { innerWidth, devicePixelRatio, documentScrollWidth: document.documentElement.scrollWidth,
      bodyScrollWidth: document.body.scrollWidth, max900: matchMedia('(max-width: 900px)').matches,
      max680: matchMedia('(max-width: 680px)').matches, max320: matchMedia('(max-width: 320px)').matches },
      elements: selectors.map(inspect), text };
  });
  console.log(JSON.stringify(report, null, 2));
  expect(report.viewport).toMatchObject({ innerWidth: 248, max900: true, max680: true, max320: true });
  expect(report.viewport.documentScrollWidth).toBeLessThanOrEqual(248);
  expect(report.viewport.bodyScrollWidth).toBeLessThanOrEqual(248);
  for (const selector of ['.lo-grid--section[data-lo-section-grid="incidents"]', '.lo-attention-services__grid', '.lo-service-card__header', '.lo-service-card__meta', '.lo-attention-summary']) {
    expect(report.elements.find((item) => item?.selector === selector)?.gridTemplateColumns.split(' ').length, selector).toBe(1);
  }
  expect(report.text.find((item) => item.selector === '.lo-service-card__diagnostic p').width).toBeGreaterThan(140);
  await page.screenshot({ path: 'test-results/lousy-outages-screenshots/248x2048.png', fullPage: true });
});
