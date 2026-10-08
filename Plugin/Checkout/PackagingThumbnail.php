<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Plugin\Checkout;

use Magento\Checkout\CustomerData\DefaultItem;
use Magento\Quote\Model\Quote\Item;
use Magmodules\Boxo\Model\Packaging\ImageProvider;

/**
 * Shows the extension's own artwork for the BOXO packaging line in the
 * mini-cart, instead of the "no image" placeholder the products would get.
 */
class PackagingThumbnail
{
    public function __construct(private readonly ImageProvider $imageProvider)
    {
    }

    /**
     * @param DefaultItem $subject
     * @param array $result
     * @param Item $item
     * @return array
     */
    public function afterGetItemData(DefaultItem $subject, array $result, Item $item): array
    {
        $image = $this->imageProvider->getImageDataForSku((string)$item->getSku());
        if ($image !== null) {
            $result['product_image'] = $image;
        }

        return $result;
    }
}
