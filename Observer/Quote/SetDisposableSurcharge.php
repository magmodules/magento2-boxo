<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Observer\Quote;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote\Item;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Model\Packaging;

/**
 * Prices the disposable packaging line from the store configuration.
 *
 * The retailer sets the surcharge in the admin, not on the catalog product, so
 * the price is applied to the quote item instead of read from the product. The
 * product keeps the taxable tax class, so VAT follows the store's tax rules —
 * unlike the reusable deposit, which is charged without VAT.
 *
 * Quote\Item::setProduct() re-applies the catalog price whenever products are
 * assigned to a quote, which happens on every load, so this hooks the event it
 * fires rather than pricing the item once when it is added.
 */
class SetDisposableSurcharge implements ObserverInterface
{
    public function __construct(private readonly ConfigRepository $configRepository)
    {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $item = $observer->getEvent()->getData('quote_item');
        if (!$item instanceof Item) {
            return;
        }

        if ((Packaging::SKU_TO_SELECTION[(string)$item->getSku()] ?? null) !== Packaging::SELECTION_DISPOSABLE) {
            return;
        }

        $storeId = $item->getStoreId() !== null ? (int)$item->getStoreId() : null;
        $surcharge = $this->configRepository->getDisposableSurcharge($storeId);

        $item->setCustomPrice($surcharge);
        $item->setOriginalCustomPrice($surcharge);

        // Custom prices are rejected outside super mode in some quote flows.
        $product = $observer->getEvent()->getData('product');
        if ($product !== null) {
            $product->setIsSuperMode(true);
        }
    }
}
