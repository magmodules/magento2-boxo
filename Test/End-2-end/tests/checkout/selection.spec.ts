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
const SURCHARGE_AMOUNT = 'magmodules_boxo/general/disposable_surcharge_amount';

/**
 * Picking packaging at checkout, and what that does to the cart.
 *
 * The invariant worth the whole spec: at most one BOXO line is ever in the quote. Switching
 * options leaving both behind would charge a customer a deposit *and* a bag fee, which is the kind
 * of defect that reaches a merchant as a refund request rather than a bug report.
 */
test.describe.serial('BOXO packaging selection at checkout', () => {
  test.beforeAll(() => {
    mock.ensureRunning();
    db.setConfig(SURCHARGE_ENABLE, '1');
    db.setConfig(SURCHARGE_AMOUNT, '0.25');
  });

  test.beforeEach(async ({ page }) => {
    mock.reset();
    await shop.emptyCart(page);
  });

  test('the options are offered for a serviceable Dutch postcode', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await shop.openCheckoutAsGuest(page);

    const boxo = shop.boxo(page);
    await expect(boxo.fieldset).toBeVisible({ timeout: 60000 });
    await expect(boxo.reusableLabel).toContainText('Reusable (deposit)');
    await expect(boxo.disposableLabel).toContainText('Single-use');

    // The options are only meaningful if BOXO was actually asked about this postcode.
    const probes = mock.requestsMatching('service-available');
    expect(probes.length).toBeGreaterThan(0);
    expect(probes[probes.length - 1].path).toContain('1012AB');
  });

  test('choosing reusable puts the deposit in the order summary', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await shop.openCheckoutAsGuest(page);
    await shop.chooseBoxoOption(page, 'reusable');

    await shop.openOrderSummary(page);
    await expect(shop.summaryItems(page)).toContainText(['Reusable packaging']);
  });

  /**
   * The switch, and the reason this spec is serial: it asserts the *absence* of the option the
   * previous step selected, so it has to run against a cart that really had it.
   */
  test('switching to single-use leaves exactly one packaging line', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await shop.openCheckoutAsGuest(page);

    await shop.chooseBoxoOption(page, 'reusable');
    await shop.chooseBoxoOption(page, 'disposable');

    await shop.openOrderSummary(page);
    const items = shop.summaryItems(page);
    await expect(items).toContainText(['Disposable packaging']);
    await expect(items.filter({ hasText: 'Reusable packaging' })).toHaveCount(0);
  });

  /**
   * BOXO services the Netherlands only. A Belgian address must not even reach the API — and must
   * not leave the customer looking at packaging options they cannot have.
   */
  test('the options are withheld outside the Netherlands', async ({ page }) => {
    await shop.addSimpleProductToCart(page);
    await page.goto('checkout/');

    const email = page.locator('#customer-email');
    await email.waitFor({ state: 'visible', timeout: 60000 });
    await email.fill('boxo-e2e-be@example.com');
    await page.locator('input[name=firstname]').fill('Boxo');
    await page.locator('input[name=lastname]').fill('Tester');
    await page.locator('input[name="street[0]"]').fill('Teststraat 1');
    await page.locator('input[name=city]').fill('Brussel');
    await page.locator('select[name=country_id]').selectOption('BE');
    await page.locator('input[name=postcode]').fill('1000');
    await page.locator('input[name=telephone]').fill('020123456');
    await page.locator('input[name=telephone]').blur();
    await shop.chooseFirstShippingMethod(page);

    await expect(shop.boxo(page).fieldset).toBeHidden();
  });
});
