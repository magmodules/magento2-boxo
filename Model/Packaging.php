<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model;

/**
 * Central definition of the BOXO packaging products and the selection keys the
 * frontend uses to refer to them. The cart line item (one of these SKUs) is the
 * single source of truth for what the customer chose — there is no separate
 * selection flag on the quote.
 */
class Packaging
{
    /**
     * Reusable packaging: a refundable deposit, charged without VAT.
     */
    public const SKU_REUSABLE = 'boxo-deposit';
    public const SELECTION_REUSABLE = 'reusable';

    /**
     * Disposable packaging: a regular (taxable) fee.
     */
    public const SKU_DISPOSABLE = 'boxo-disposable';
    public const SELECTION_DISPOSABLE = 'disposable';

    /**
     * Selection key => product SKU.
     *
     * @var array<string, string>
     */
    public const SELECTION_TO_SKU = [
        self::SELECTION_REUSABLE => self::SKU_REUSABLE,
        self::SELECTION_DISPOSABLE => self::SKU_DISPOSABLE,
    ];

    /**
     * Product SKU => selection key.
     *
     * @var array<string, string>
     */
    public const SKU_TO_SELECTION = [
        self::SKU_REUSABLE => self::SELECTION_REUSABLE,
        self::SKU_DISPOSABLE => self::SELECTION_DISPOSABLE,
    ];
}
