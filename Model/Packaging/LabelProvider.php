<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Packaging;

use Magmodules\Boxo\Model\Packaging;

/**
 * Supplies every customer-facing text of the checkout packaging block.
 *
 * Single source of truth for the section heading, the description, the radio
 * labels and the cart line item name, so the summary never contradicts the
 * option the customer picked.
 *
 * None of it is configurable: the wording belongs to the BOXO proposition and
 * must read the same in every shop. Adjusting it stays possible where it
 * belongs — in a translation file, which BOXO controls — and that keeps a
 * multi-language store localized instead of freezing one merchant-typed string
 * into every language.
 */
class LabelProvider
{
    /**
     * Heading above the packaging options.
     *
     * @return string
     */
    public function getHeading(): string
    {
        return (string)__('Choose your shipping packaging');
    }

    /**
     * Wording for a checkout radio row.
     *
     * @param string|null $selection 'reusable' | 'disposable' | null
     * @return string empty when the selection is unknown
     */
    public function getCheckoutLabel(?string $selection): string
    {
        return match ($selection) {
            Packaging::SELECTION_REUSABLE => (string)__('Reusable (deposit)'),
            Packaging::SELECTION_DISPOSABLE => (string)__('Single-use'),
            default => '',
        };
    }

    /**
     * @param string|null $selection 'reusable' | 'disposable' | null
     * @return string empty when the selection is unknown
     */
    public function getLabel(?string $selection): string
    {
        return match ($selection) {
            Packaging::SELECTION_REUSABLE => (string)__('Reusable packaging'),
            Packaging::SELECTION_DISPOSABLE => (string)__('Disposable packaging'),
            default => '',
        };
    }

    /**
     * @param string $sku
     * @return string empty when the SKU is not a BOXO product
     */
    public function getLabelForSku(string $sku): string
    {
        return $this->getLabel(Packaging::SKU_TO_SELECTION[$sku] ?? null);
    }
}
