<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Model\Order;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\LabelProvider;
use Magmodules\Boxo\Model\Quote\PackagingCartManager;

class BoxoInfo extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LabelProvider $labelProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Orders placed before this extension was installed hold no selection and
     * show no packaging section at all.
     *
     * @return bool
     */
    public function isBoxoSelected(): bool
    {
        return $this->getSelection() !== null;
    }

    /**
     * The packaging recorded on the order, or null when none was chosen.
     *
     * @return string|null
     */
    public function getSelection(): ?string
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        $stored = $order->getData(PackagingCartManager::SELECTION_FIELD);
        if (is_string($stored) && (Packaging::SELECTION_TO_SKU[$stored] ?? null) !== null) {
            return $stored;
        }

        // Orders placed before the selection was stored carry only their line item.
        $item = $this->getBoxoItem();

        return $item !== null ? (Packaging::SKU_TO_SELECTION[(string)$item->getSku()] ?? null) : null;
    }

    /**
     * The BOXO packaging line on the order, or null when none was chosen.
     *
     * @return OrderItemInterface|null
     */
    public function getBoxoItem(): ?OrderItemInterface
    {
        $order = $this->getOrder();
        if (!$order) {
            return null;
        }

        foreach ($order->getAllItems() as $item) {
            if ((Packaging::SKU_TO_SELECTION[(string)$item->getSku()] ?? null) !== null) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Human-readable packaging type the customer picked.
     *
     * @return string
     */
    public function getSelectionLabel(): string
    {
        $item = $this->getBoxoItem();

        // A charged option carries the label as it read when the order was
        // placed; a free one has no line item, so name it from the current
        // wording instead.
        if ($item !== null) {
            $name = trim((string)$item->getName());
            if ($name !== '') {
                return $name;
            }
        }

        return $this->labelProvider->getLabel($this->getSelection());
    }

    /**
     * Charged amount for the BOXO line, formatted in the order currency.
     *
     * @return string
     */
    public function getAmountFormatted(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }

        // No line item means the option was free, which is an amount worth
        // stating rather than leaving blank.
        $item = $this->getBoxoItem();

        return $this->priceCurrency->format(
            $item !== null ? (float)$item->getRowTotalInclTax() : 0.0,
            false,
            2,
            null,
            $order->getOrderCurrencyCode()
        );
    }
}
