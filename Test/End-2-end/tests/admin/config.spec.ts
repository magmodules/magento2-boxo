/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, test } from '@playwright/test';
import { MagentoDb } from 'Helpers/MagentoDb';
import SystemConfig from 'Pages/backend/SystemConfig';
import BoxoMock, { CONFIG_PATH_API_KEY, MOCK_API_KEY } from 'Services/BoxoMock';

const mock = new BoxoMock();
const db = new MagentoDb(process.env.MAGENTO_CONTAINER as string);
const config = new SystemConfig();

const SECTION = 'magmodules_boxo';
const GROUP = 'general';

/**
 * The admin's API-key test, driven against the stand-in.
 *
 * This is the one screen a merchant uses to find out whether their key works, so its verdicts
 * have to be right for the right reason: "rejected" and "cannot reach BOXO" send a merchant down
 * completely different paths, and the difference is a status code neither of us can produce
 * against the real API on demand.
 */
test.describe.serial('BOXO admin configuration', () => {
  test.beforeAll(() => {
    mock.ensureRunning();
  });

  test.beforeEach(() => {
    mock.reset();
  });

  test('the key test reports a working connection', async ({ page }) => {
    await config.openGroup(page, SECTION, GROUP);

    await page.locator('#mm-ui-button_apikey').click();

    await expect(page.locator('#mm-ui-result_apikey'))
      .toContainText('Connection to the BOXO production API established');

    // Proof it was the stand-in that answered, not api.boxo.nu.
    const probes = mock.requestsMatching('service-available');
    expect(probes.length).toBe(1);
    expect(probes[0].authorized).toBe(true);
  });

  test('a rejected key is named as rejected', async ({ page }) => {
    mock.injectFaults([{ match: 'service-available', status: 401 }]);

    await config.openGroup(page, SECTION, GROUP);
    await page.locator('#mm-ui-button_apikey').click();

    await expect(page.locator('#mm-ui-result_apikey'))
      .toContainText('The BOXO production API rejected this key');
  });

  /**
   * A 500 must not read as "your key is wrong" — that sends the merchant to BOXO support to
   * re-issue a key that was fine all along. The multi-line HTML body the mock returns for a 5xx
   * is deliberate: it is the shape that broke error handling in four other modules.
   */
  test('an outage is not reported as a bad key', async ({ page }) => {
    mock.injectFaults([{ match: 'service-available', status: 500 }]);

    await config.openGroup(page, SECTION, GROUP);
    await page.locator('#mm-ui-button_apikey').click();

    const result = page.locator('#mm-ui-result_apikey');
    await expect(result).toContainText('Unexpected response from the BOXO production API (status 500)');
    await expect(result).not.toContainText('rejected');
  });

  test('a missing key is answered without calling BOXO', async ({ page }) => {
    db.setConfig(CONFIG_PATH_API_KEY, '');

    try {
      await config.openGroup(page, SECTION, GROUP);
      await page.locator('#mm-ui-button_apikey').click();

      await expect(page.locator('#mm-ui-result_apikey'))
        .toContainText('No production API key configured');

      expect(mock.requestsMatching('service-available')).toHaveLength(0);
    } finally {
      db.setConfig(CONFIG_PATH_API_KEY, MOCK_API_KEY);
    }
  });

  /**
   * The admin resolves store id 0, so a value saved only at website scope is invisible to it and
   * the module falls back to the `default` row. Modules have shipped an admin screen that refused
   * to work for exactly this reason while the storefront was fine.
   */
  test('a website-scoped key is used by the storefront scope that owns it', async () => {
    db.setScopedConfig(CONFIG_PATH_API_KEY, 'website-scoped-key', 'websites', 1);

    try {
      expect(db.getScopedConfig(CONFIG_PATH_API_KEY, 'websites', 1)).toBe('website-scoped-key');
      // The default row is untouched, which is what makes the admin read something different.
      expect(db.getScopedConfig(CONFIG_PATH_API_KEY, 'default', 0)).toBe(MOCK_API_KEY);
    } finally {
      db.setScopedConfig(CONFIG_PATH_API_KEY, '', 'websites', 1);
    }
  });
});
