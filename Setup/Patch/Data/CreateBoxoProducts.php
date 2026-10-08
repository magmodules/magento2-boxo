<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Setup\Patch\Data;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Boxo\Model\Packaging;

/**
 * Creates the two virtual products that represent the BOXO packaging options.
 * These replace the former order-total based fees: the reusable deposit and the
 * disposable fee are now ordinary cart line items.
 */
class CreateBoxoProducts implements DataPatchInterface
{
    /**
     * Magento's built-in "None" tax class has id 0 — used for the refundable
     * deposit so no VAT is applied.
     */
    private const TAX_CLASS_NONE = 0;

    /**
     * Magento's default "Taxable Goods" product tax class (created by the Tax
     * module's install data). VAT for the disposable fee follows store config.
     */
    private const TAX_CLASS_TAXABLE_GOODS = 2;

    /**
     * Definitions for the products to create. Price/tax can be changed afterwards
     * in the catalog; these are the initial values.
     */
    private const PRODUCTS = [
        [
            'sku' => Packaging::SKU_REUSABLE,
            'name' => 'Reusable packaging deposit',
            'price' => 3.95,
            'tax_class_id' => self::TAX_CLASS_NONE,
        ],
        [
            'sku' => Packaging::SKU_DISPOSABLE,
            // The charged amount is the surcharge configured in the admin, not
            // this price — see Observer\Quote\SetDisposableSurcharge.
            'name' => 'Disposable packaging',
            'price' => 0.00,
            // Default "Taxable Goods" tax class; VAT follows store tax config.
            'tax_class_id' => self::TAX_CLASS_TAXABLE_GOODS,
        ],
    ];

    public function __construct(
        private readonly ProductInterfaceFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        // Product save resolves store-scoped attributes, which requires an area.
        $this->appState->emulateAreaCode(Area::AREA_ADMINHTML, [$this, 'createProducts']);

        return $this;
    }

    /**
     * Creates any BOXO product that does not yet exist. Idempotent: re-running
     * the patch never duplicates products.
     *
     * @return void
     */
    public function createProducts(): void
    {
        $websiteIds = $this->getAllWebsiteIds();

        foreach (self::PRODUCTS as $definition) {
            if ($this->productExists($definition['sku'])) {
                continue;
            }

            /** @var ProductInterface|\Magento\Catalog\Model\Product $product */
            $product = $this->productFactory->create();
            $product->setSku($definition['sku']);
            $product->setName($definition['name']);
            $product->setTypeId(Type::TYPE_VIRTUAL);
            $product->setAttributeSetId($product->getDefaultAttributeSetId());
            $product->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
            $product->setStatus(Status::STATUS_ENABLED);
            $product->setPrice($definition['price']);
            $product->setTaxClassId($definition['tax_class_id']);
            $product->setWebsiteIds($websiteIds);
            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
            ]);

            $this->productRepository->save($product);
        }
    }

    /**
     * @param string $sku
     * @return bool
     */
    private function productExists(string $sku): bool
    {
        try {
            $this->productRepository->get($sku);
            return true;
        } catch (NoSuchEntityException $e) {
            return false;
        }
    }

    /**
     * @return int[]
     */
    private function getAllWebsiteIds(): array
    {
        $ids = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $ids[] = (int)$website->getId();
        }
        return $ids;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
