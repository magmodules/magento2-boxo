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
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\LabelProvider;

/**
 * Names the BOXO cart line after the packaging option the customer picked.
 *
 * The catalog product name is a single global value, so it would show up in
 * English on a Dutch store and would ignore the labels configured for the
 * checkout. Quote\Item::setProduct() re-applies that catalog name every time
 * products are assigned to a quote, which is why this hooks the event it fires
 * instead of naming the item once when it is added to the cart.
 *
 * The name is copied onto the order item on conversion, so it also carries into
 * order confirmation mails, invoices and the admin order view.
 */
class SetPackagingItemName implements ObserverInterface
{
    public function __construct(private readonly LabelProvider $labelProvider)
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

        $sku = (string)$item->getSku();
        if ((Packaging::SKU_TO_SELECTION[$sku] ?? null) === null) {
            return;
        }

        $label = $this->labelProvider->getLabelForSku($sku);
        if ($label !== '') {
            $item->setName($label);
        }
    }
}
