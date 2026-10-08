/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, type Page } from '@playwright/test';
import { adminConfigUrl } from '../../helpers/AdminUrl';

/**
 * Magento's system configuration screen.
 *
 * Groups render as collapsed fieldsets, so every field and button inside one is present in the DOM
 * but not visible until its header is clicked. A spec that goes straight for a button gets
 * "element is not visible" and retries until it times out — which says nothing about the button
 * and everything about the page never having been opened.
 */
export default class SystemConfig {
  /**
   * Open a section and expand one of its groups, leaving the group's contents interactable.
   *
   * @param page Playwright page.
   * @param section Section id, e.g. `magmodules_boxo`.
   * @param group Group id within that section, e.g. `general`.
   */
  async openGroup(page: Page, section: string, group: string): Promise<void> {
    await page.goto(adminConfigUrl(section));

    const head = page.locator(`#${section}_${group}-head`);
    await head.waitFor({ state: 'visible', timeout: 60000 });

    const fieldset = page.locator(`#${section}_${group}`);

    // Magento remembers which groups a user last had open, so the group may already be expanded.
    // Clicking a second time would collapse it again.
    if (!(await fieldset.isVisible())) {
      await head.click();
    }

    await expect(fieldset).toBeVisible({ timeout: 30000 });
  }
}
