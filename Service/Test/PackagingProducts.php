<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Store\Model\StoreManagerInterface;
use Magmodules\Boxo\Model\Packaging;

/**
 * Service class to check that the packaging products can actually be sold.
 *
 * Existing is not enough: the products are added to the cart like any other, so
 * they also have to be enabled and salable in the store doing the selling. A
 * multi-source setup is the usual way this breaks — moving a website's sales
 * channel to a new stock leaves these virtual, invisible products behind on the
 * old one, and the only symptom is a failure at checkout.
 */
class PackagingProducts
{
    public const TYPE = 'packaging_products';
    public const TEST = 'Check if the BOXO packaging products can be added to the cart';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Packaging products available';
    public const MISSING_MSG = 'Packaging product %1 is missing, re-run setup:upgrade!';
    public const DISABLED_MSG = 'Packaging product %1 is disabled, enable it in the catalog!';
    public const NOT_SALABLE_MSG = 'Packaging product %1 is not salable in store %2, '
        . 'assign it to a source in the stock serving this website!';
    public const SUPPORT_LINK = 'https://www.magmodules.eu/support';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Executes the packaging product availability test.
     *
     * @return array
     */
    public function execute(): array
    {
        $result = [
            'type' => self::TYPE,
            'test' => (string)__(self::TEST),
            'visible' => self::VISIBLE,
        ];

        foreach ($this->getSellingStoreIds() as $storeId => $storeName) {
            foreach (array_values(Packaging::SELECTION_TO_SKU) as $sku) {
                $failure = $this->check($sku, $storeId, $storeName);
                if ($failure !== null) {
                    $result['result_msg'] = $failure;
                    $result['result_code'] = 'failed';
                    $result['support_link'] = self::SUPPORT_LINK;

                    return $result;
                }
            }
        }

        $result['result_msg'] = (string)__(self::SUCCESS_MSG);
        $result['result_code'] = 'success';

        return $result;
    }

    /**
     * @param string $sku
     * @param int $storeId
     * @param string $storeName
     * @return string|null the failure message, or null when the product is fine
     */
    private function check(string $sku, int $storeId, string $storeName): ?string
    {
        try {
            $product = $this->productRepository->get($sku, false, $storeId, true);
        } catch (\Exception $e) {
            return (string)__(self::MISSING_MSG, $sku);
        }

        if ((int)$product->getStatus() === Status::STATUS_DISABLED) {
            return (string)__(self::DISABLED_MSG, $sku);
        }

        // The same check Magento\Quote\Model\Quote::addProduct() makes before
        // refusing an item, so this fails exactly when the checkout would.
        if (!$product->isSalable()) {
            return (string)__(self::NOT_SALABLE_MSG, $sku, $storeName);
        }

        return null;
    }

    /**
     * Store views that actually sell, so the check mirrors what a customer hits.
     *
     * @return array<int, string>
     */
    private function getSellingStoreIds(): array
    {
        $stores = [];

        foreach ($this->storeManager->getStores() as $store) {
            if ($store->getIsActive()) {
                $stores[(int)$store->getId()] = (string)$store->getName();
            }
        }

        return $stores;
    }
}
