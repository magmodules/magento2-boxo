/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { expect, type Page } from '@playwright/test';

/**
 * A sample-data simple product: no configurable options to pick, in stock, and present in every
 * Magento sample-data install the runner uses. A configurable would add option-selection noise to
 * specs that are about packaging, not about the catalogue.
 */
export const SIMPLE_PRODUCT = {
  sku: '24-MB01',
  urlKey: 'joust-duffle-bag',
  name: 'Joust Duffle Bag',
};

/**
 * A Dutch address. BOXO only services the Netherlands, so every other country short-circuits the
 * availability check before it reaches the API — which is a different spec, not this one.
 */
export const NL_ADDRESS = {
  email: 'boxo-e2e@example.com',
  firstname: 'Boxo',
  lastname: 'Tester',
  street: 'Teststraat 1',
  city: 'Amsterdam',
  postcode: '1012 AB',
  countryId: 'NL',
  telephone: '0201234567',
};

export default class Storefront {
  /** Empty the cart, so a spec never inherits the lines another one left behind. */
  async emptyCart(page: Page): Promise<void> {
    await page.goto('checkout/cart/');

    // Luma renders no remove buttons for an empty cart, so this loop simply does nothing then.
    for (;;) {
      const remove = page.locator('a.action-delete').first();
      if (await remove.count() === 0) break;
      await remove.click();
      await page.waitForLoadState('domcontentloaded');
    }
  }

  async addSimpleProductToCart(page: Page): Promise<void> {
    await page.goto(`${SIMPLE_PRODUCT.urlKey}.html`);
    await page.locator('#product-addtocart-button').click();

    // The success banner is the only reliable signal the quote actually has the item: the minicart
    // counter updates from a separate customer-data request that may land later.
    await expect(page.locator('.message-success')).toContainText('added', { timeout: 30000 });
  }

  /**
   * Reach the checkout with a shipping address filled in and a shipping method chosen, which is
   * the state in which the BOXO options become meaningful.
   */
  async openCheckoutAsGuest(page: Page, postcode = NL_ADDRESS.postcode): Promise<void> {
    await page.goto('checkout/');

    const email = page.locator('#customer-email');
    await email.waitFor({ state: 'visible', timeout: 60000 });
    await email.fill(NL_ADDRESS.email);

    await page.locator('input[name=firstname]').fill(NL_ADDRESS.firstname);
    await page.locator('input[name=lastname]').fill(NL_ADDRESS.lastname);
    await page.locator('input[name="street[0]"]').fill(NL_ADDRESS.street);
    await page.locator('input[name=city]').fill(NL_ADDRESS.city);
    await page.locator('select[name=country_id]').selectOption(NL_ADDRESS.countryId);
    await page.locator('input[name=postcode]').fill(postcode);
    await page.locator('input[name=telephone]').fill(NL_ADDRESS.telephone);

    // Blur the postcode: the availability check fires on the address becoming complete, and a
    // field still holding focus has not been committed to the Knockout model yet.
    await page.locator('input[name=telephone]').blur();

    await this.chooseFirstShippingMethod(page);
  }

  async chooseFirstShippingMethod(page: Page): Promise<void> {
    const method = page.locator('#checkout-shipping-method-load input[type=radio]').first();
    await method.waitFor({ state: 'visible', timeout: 60000 });
    await method.check();
  }

  /** The BOXO block, which renders in the shipping step's `shippingAdditional` area. */
  boxo(page: Page) {
    return {
      fieldset: page.locator('.boxo-fieldset'),
      reusable: page.locator('#boxo-option-reusable'),
      disposable: page.locator('#boxo-option-disposable'),
      reusableLabel: page.locator('label[for=boxo-option-reusable]'),
      disposableLabel: page.locator('label[for=boxo-option-disposable]'),
      error: page.locator('.boxo-error'),
      disposablePrice: page.locator('#boxo-option-disposable ~ .boxo-option-price, '
        + '.boxo-option:has(#boxo-option-disposable) .boxo-option-price'),
    };
  }

  /**
   * Choose an option the way a customer does — by clicking the label rather than setting the
   * input. The label is what is actually on screen; driving the input directly would pass even if
   * the label were detached from it.
   */
  async chooseBoxoOption(page: Page, option: 'reusable' | 'disposable'): Promise<void> {
    const boxo = this.boxo(page);
    const label = option === 'reusable' ? boxo.reusableLabel : boxo.disposableLabel;
    const input = option === 'reusable' ? boxo.reusable : boxo.disposable;

    await label.click();
    await expect(input).toBeChecked();

    // The selection is persisted over AJAX; the totals block reloading is the signal it landed.
    await page.waitForLoadState('networkidle');
  }

  /** The order summary's line items, where a charged packaging option shows up. */
  summaryItems(page: Page) {
    return page.locator('.opc-block-summary .minicart-items .product-item-name');
  }

  async openOrderSummary(page: Page): Promise<void> {
    const toggle = page.locator('.opc-block-summary .items-in-cart .title');
    if (await toggle.count() > 0 && await toggle.getAttribute('aria-expanded') !== 'true') {
      await toggle.click();
    }
  }
}
