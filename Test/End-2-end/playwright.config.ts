/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  globalSetup: require.resolve('./global-setup.ts'),

  testDir: './tests',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  // No retries: a retry of a describe.serial block replays it against the data its own earlier
  // tests wrote, so retries produce failures instead of absorbing them, and a test that only
  // passes on the second attempt is reported as flaky rather than as the defect it is.
  retries: 0,
  workers: 1,
  // MAX_FAILURES lifts the cap for a run that has to show the whole picture.
  maxFailures: process.env.MAX_FAILURES
    ? Number(process.env.MAX_FAILURES)
    : (process.env.CI ? 10 : undefined),
  reporter: process.env.CI
    ? [['list'], ['html']]
    : [['html', { open: 'never' }]],
  use: {
    baseURL: process.env.BASE_URL || 'https://magento.test/',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },

  timeout: 120000,

  projects: [
    { name: 'setup', testMatch: /.*\.setup\.ts/ },

    {
      name: 'chromium',
      use: {
        ...devices['Desktop Chrome'],
        storageState: '.auth/backend.json',
      },
      dependencies: ['setup'],
      testMatch: /.*\.spec\.ts/,
    },
  ],
});
