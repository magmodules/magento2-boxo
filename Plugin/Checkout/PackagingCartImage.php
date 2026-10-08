<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Plugin\Checkout;

use Magento\Checkout\Model\Cart\ImageProvider as CheckoutImageProvider;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magmodules\Boxo\Model\Packaging\ImageProvider;

/**
 * Same swap as the mini-cart, for the order summary on the checkout page.
 *
 * The summary asks for images by cart id and gets them back keyed by item id,
 * so the cart items are read again here to find which of them are BOXO lines.
 */
class PackagingCartImage
{
    public function __construct(
        private readonly ImageProvider $imageProvider,
        private readonly CartItemRepositoryInterface $cartItemRepository
    ) {
    }

    /**
     * @param CheckoutImageProvider $subject
     * @param array $result
     * @param int $cartId
     * @return array
     */
    public function afterGetImages(CheckoutImageProvider $subject, array $result, $cartId): array
    {
        if ($result === []) {
            return $result;
        }

        try {
            $items = $this->cartItemRepository->getList((int)$cartId);
        } catch (\Exception $e) {
            // A summary with the stock placeholder beats a broken checkout.
            return $result;
        }

        foreach ($items as $item) {
            $itemId = $item->getItemId();
            if (!isset($result[$itemId])) {
                continue;
            }

            $image = $this->imageProvider->getImageDataForSku((string)$item->getSku());
            if ($image !== null) {
                $result[$itemId] = $image;
            }
        }

        return $result;
    }
}
