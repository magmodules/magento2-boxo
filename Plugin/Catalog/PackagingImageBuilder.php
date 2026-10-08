<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Plugin\Catalog;

use Magento\Catalog\Block\Product\Image as ImageBlock;
use Magento\Catalog\Block\Product\ImageBuilder;
use Magento\Catalog\Model\Product;
use Magmodules\Boxo\Model\Packaging\ImageProvider;

/**
 * Swaps the packaging artwork into every block-rendered product image.
 *
 * This is the path the cart page and any other template using getImage() take,
 * so it covers them all at once rather than one renderer at a time.
 */
class PackagingImageBuilder
{
    public function __construct(private readonly ImageProvider $imageProvider)
    {
    }

    /**
     * @param ImageBuilder $subject
     * @param ImageBlock $result
     * @param Product|null $product
     * @param string|null $imageId
     * @return ImageBlock
     */
    public function afterCreate(
        ImageBuilder $subject,
        ImageBlock $result,
        ?Product $product = null,
        ?string $imageId = null
    ): ImageBlock {
        if ($product === null) {
            return $result;
        }

        $url = $this->imageProvider->getUrlForSku((string)$product->getSku());
        if ($url === null) {
            return $result;
        }

        $result->setData('image_url', $url);

        // Keep the placeholder flag off, so themes that hide "no image" boxes
        // still render this one.
        $result->setData('product_id', $product->getId());

        return $result;
    }
}
