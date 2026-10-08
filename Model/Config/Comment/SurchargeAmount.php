<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Tax\Model\Config as TaxConfig;

/**
 * Explains how the disposable surcharge is read, in the store's own terms.
 *
 * Whether the amount entered here is net or gross is not a property of the
 * extension: Magento interprets it the same way it interprets a catalog price,
 * which is decided by Sales > Tax > "Catalog Prices". Stating one or the other
 * unconditionally would be wrong on half the installations, so the comment
 * follows the setting.
 */
class SurchargeAmount implements CommentInterface
{
    public function __construct(private readonly TaxConfig $taxConfig)
    {
    }

    /**
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue): string
    {
        $note = $this->taxConfig->priceIncludesTax()
            ? __('Amount including VAT, matching the "Catalog Prices" tax setting of this store.')
            : __('Amount excluding VAT, matching the "Catalog Prices" tax setting of this store. VAT is added on top.');

        return (string)$note . ' ' . __('Enter it in the base currency of the store.');
    }
}
