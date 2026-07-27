// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const baseURL = process.env.PLAYWRIGHT_BASE_URL || `http://localhost:${process.env.LOCAL_WP_PORT || '8080'}`;
const executablePath = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;

module.exports = defineConfig({
  testDir: './tests/e2e',
  timeout: 30_000,
  expect: {
    timeout: 5_000,
  },
  use: {
    baseURL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'chromium-desktop',
      use: { ...devices['Desktop Chrome'], ...(executablePath ? { executablePath } : {}) },
    },
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'], ...(executablePath ? { executablePath } : {}) },
    },
  ],
  webServer: process.env.PLAYWRIGHT_SKIP_WEB_SERVER
    ? undefined
    : {
        command: 'docker compose up -d wordpress',
        url: baseURL,
        reuseExistingServer: true,
        timeout: 120_000,
      },
});
