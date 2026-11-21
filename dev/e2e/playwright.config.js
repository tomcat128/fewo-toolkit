// Browser-Tests gegen das lokale Testprojekt (siehe ../docker-compose.yml und ../setup.sh).
//   npm install && npx playwright install chromium && npx playwright test
const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests',
  // Die Tests teilen sich eine Concrete-Installation und laufen deshalb nacheinander.
  workers: 1,
  fullyParallel: false,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  globalSetup: require.resolve('./global-setup.js'),
  reporter: [['list']],
  outputDir: './test-results',
  use: {
    baseURL: process.env.FEWO_BASE_URL || 'http://localhost:8095',
    locale: 'de-DE',
    timezoneId: 'Europe/Berlin',
    viewport: { width: 1400, height: 1000 },
    storageState: '.auth/admin.json',
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
  },
});
