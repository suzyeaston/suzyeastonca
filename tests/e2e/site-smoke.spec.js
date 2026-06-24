const { test, expect } = require('@playwright/test');

const criticalPages = [
  ['home', '/'],
  ['lousy outages', '/lousy-outages/'],
  ['work with suzy', '/work-with-suzy/'],
  ['projects', '/projects/'],
  ['track analyzer', '/suzys-track-analyzer/'],
  ['gastown simulator', '/gastown-sim/'],
  ['asmr lab', '/asmr-lab/'],
  ['albini qa', '/albini-qa/'],
];

test.describe('critical page smoke checks', () => {
  for (const [name, path] of criticalPages) {
    test(`${name} page loads without client-side errors`, async ({ page }) => {
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('console', (message) => {
        if (message.type() === 'error') {
          errors.push(message.text());
        }
      });

      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      expect(response, `${path} should return a response`).not.toBeNull();
      expect(response.status(), `${path} should not 404/500`).toBeLessThan(400);
      await expect(page.locator('body')).toBeVisible();
      expect(errors, `${path} console/page errors`).toEqual([]);
    });
  }
});

test('contact modal opens from every visible contact trigger', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const triggers = page.locator('[data-contact-trigger]:visible');
  await expect(triggers.first()).toBeVisible();
  const triggerCount = await triggers.count();
  expect(triggerCount).toBeGreaterThan(0);

  for (let index = 0; index < triggerCount; index += 1) {
    await triggers.nth(index).click();
    await expect(page.locator('#contact-suzy-modal')).toBeVisible();
    await expect(page.locator('#se-contact-name')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(page.locator('#contact-suzy-modal')).toBeHidden();
  }
});

test('lousy outages dashboard exposes status surface and report form area', async ({ page }) => {
  await page.goto('/lousy-outages/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: /lousy outages/i })).toBeVisible();
  await expect(page.locator('.lousy-outages, [data-lo-endpoint]').first()).toBeVisible();
});

test('track analyzer upload form exposes expected controls', async ({ page }) => {
  await page.goto('/suzys-track-analyzer/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByLabel(/upload mp3 file/i)).toBeVisible();
  await expect(page.getByRole('button', { name: /analyze track/i })).toBeVisible();
});
