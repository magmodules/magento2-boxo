/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, test } from '@playwright/test';
import { MagentoDb } from 'Helpers/MagentoDb';
import Storefront from 'Pages/frontend/Storefront';
import BoxoMock from 'Services/BoxoMock';

const mock = new BoxoMock();
const db = new MagentoDb(process.env.MAGENTO_CONTAINER as string);
const shop = new Storefront();

const SURCHARGE_ENABLE = 'magmodules_boxo/general/disposable_surcharge_enable';

/**
 * The free single-use option, which is the case the database schema exists for.
 *
 * With the surcharge disabled the choice produces no line item, so there is nothing in the cart to
 * read it back from. The selection is therefore stored on the quote — and if that ever stops
 * working, the customer silently falls back to the configured default on the next page load and
 * gets packaging they did not pick. Nothing but a real reload can catch it.
 */
test.describe.serial('a free packaging option', () => {
  test.beforeAll(() => {
    mock.ensureRunning();
    db.setConfig(SURCHARGE_ENABLE, '0');
  });

  test.afterAll(() => {
    db.setConfig(SURCHARGE_ENABLE, '1');
  });

  test.beforeEach(async ({ page }) => {
    mock.reset();
    await shop.emptyCart(page);
  });

  test('is offered as free rather than at nil', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await shop.openCheckoutAsGuest(page);

    await expect(shop.boxo(page).fieldset).toBeVisible({ timeout: 60000 });
    await expect(shop.boxo(page).disposablePrice.first()).toContainText('Free');
  });

  test('survives a checkout reload without a line item to carry it', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await shop.openCheckoutAsGuest(page);
    await shop.chooseBoxoOption(page, 'disposable');

    // No charge means no line: the summary must not gain a nil-priced packaging row.
    await shop.openOrderSummary(page);
    await expect(shop.summaryItems(page).filter({ hasText: 'Disposable packaging' })).toHaveCount(0);

    // The quote is the only place the choice can have been kept.
    const stored = db.query(`
      $row = $pdo->query("SELECT boxo_packaging FROM quote ORDER BY entity_id DESC LIMIT 1")->fetchColumn();
      echo $row === false || $row === null ? '__NULL__' : $row;
    `).trim();
    expect(stored).toBe('disposable');

    await page.reload();
    await expect(shop.boxo(page).fieldset).toBeVisible({ timeout: 60000 });
    await expect(shop.boxo(page).disposable).toBeChecked();
  });
});
