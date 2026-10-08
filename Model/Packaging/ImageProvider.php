<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Packaging;

use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use Magmodules\Boxo\Model\Packaging;

/**
 * Supplies the thumbnail shown for a BOXO packaging line.
 *
 * The image ships with the extension rather than sitting on the product, so the
 * packaging still looks right after a catalog re-import and no media gallery
 * has to be maintained per shop.
 *
 * Because the asset bypasses Magento's image processor it is never physically
 * resized; the dimensions are read from the theme's view.xml for the image role
 * being rendered, so the thumbnail lands at exactly the size the surrounding
 * layout expects. An SVG scales into that box, a raster asset is letterboxed
 * rather than distorted.
 */
class ImageProvider
{
    /**
     * Selection key => module asset.
     *
     * One square BOXO mark serves both options; the line item name is what
     * tells them apart. Kept as a map so a per-option image is a one-line
     * change if the artwork ever diverges.
     *
     * @var array<string, string>
     */
    private const IMAGES = [
        Packaging::SELECTION_REUSABLE => 'Magmodules_Boxo::images/boxo-product.svg',
        Packaging::SELECTION_DISPOSABLE => 'Magmodules_Boxo::images/boxo-product.svg',
    ];

    /**
     * Used when the theme declares no size for the requested image role.
     */
    private const FALLBACK_SIZE = 150;

    /**
     * Resolved dimensions per image role.
     *
     * @var array<string, array{width: int, height: int}>
     */
    private array $dimensions = [];

    public function __construct(
        private readonly AssetRepository $assetRepository,
        private readonly ViewConfig $viewConfig,
        private readonly LabelProvider $labelProvider
    ) {
    }

    /**
     * Image data for a BOXO SKU, or null when the SKU is not a BOXO product.
     *
     * @param string $sku
     * @param string $imageId image role declared in the theme's view.xml
     * @return array{src: string, alt: string, width: int, height: int}|null
     */
    public function getImageDataForSku(string $sku, string $imageId = 'mini_cart_product_thumbnail'): ?array
    {
        $url = $this->getUrlForSku($sku);
        if ($url === null) {
            return null;
        }

        $size = $this->getDimensions($imageId);

        return [
            'src' => $url,
            'alt' => $this->labelProvider->getLabelForSku($sku),
            'width' => $size['width'],
            'height' => $size['height'],
        ];
    }

    /**
     * @param string $sku
     * @return string|null
     */
    public function getUrlForSku(string $sku): ?string
    {
        $selection = (Packaging::SKU_TO_SELECTION[$sku] ?? null);
        if ($selection === null || !isset(self::IMAGES[$selection])) {
            return null;
        }

        return $this->assetRepository->getUrl(self::IMAGES[$selection]);
    }

    /**
     * Size the active theme declares for an image role.
     *
     * @param string $imageId
     * @return array{width: int, height: int}
     */
    public function getDimensions(string $imageId): array
    {
        if (isset($this->dimensions[$imageId])) {
            return $this->dimensions[$imageId];
        }

        $attributes = [];
        try {
            $attributes = $this->viewConfig
                ->getViewConfig(['area' => 'frontend'])
                ->getMediaAttributes('Magento_Catalog', 'images', $imageId);
        } catch (\Exception $e) {
            $attributes = [];
        }

        $width = (int)($attributes['width'] ?? 0);
        $height = (int)($attributes['height'] ?? 0);

        // Themes may declare only one side; keep the box square rather than
        // collapsing the missing dimension to zero.
        $width = $width > 0 ? $width : ($height > 0 ? $height : self::FALLBACK_SIZE);
        $height = $height > 0 ? $height : $width;

        return $this->dimensions[$imageId] = ['width' => $width, 'height' => $height];
    }
}
