<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Packaging;

use Magento\Quote\Api\Data\CartInterface;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Config\Source\AllowMode;
use Magmodules\Boxo\Model\Packaging;

/**
 * Decides whether a cart may be shipped in reusable BOXO packaging.
 *
 * A single disallowed product withdraws the reusable option for the whole cart:
 * the order ships as one parcel, so the strictest product in it wins.
 */
class ReusableEligibility
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly LogRepository $logRepository
    ) {
    }

    /**
     * @param CartInterface|null $quote
     * @return bool
     */
    public function isAllowed(?CartInterface $quote): bool
    {
        return $this->getBlockReason($quote) === null;
    }

    /**
     * Why reusable packaging is withheld, or null when it is offered.
     *
     * Reported rather than just answered yes/no, so the checkout can name the
     * cause in the browser console and support can read it off a customer's
     * screen instead of guessing.
     *
     * @param CartInterface|null $quote
     * @return array{code: string, product_id?: string, sku?: string, qty?: float, max?: int}|null
     */
    public function getBlockReason(?CartInterface $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $overLimit = $this->getQtyOverage($quote);
        if ($overLimit !== null) {
            return $overLimit;
        }

        $blocking = $this->findBlockingItem($quote);
        if ($blocking !== null) {
            return [
                'code' => 'product_not_eligible',
                'product_id' => $blocking['id'],
                'sku' => $blocking['sku'],
            ];
        }

        return null;
    }

    /**
     * Whether the cart holds more products than fit in one reusable container.
     *
     * Counted as a total quantity rather than a number of cart lines: what
     * matters is how many physical items go into the box, so 5× one product
     * fills it the same as 5 different ones.
     *
     * @param CartInterface $quote
     * @return array{code: string, qty: float, max: int}|null
     */
    private function getQtyOverage(CartInterface $quote): ?array
    {
        $storeId = $quote->getStoreId() !== null ? (int)$quote->getStoreId() : null;
        $max = $this->configRepository->getMaxReusableQty($storeId);
        if ($max === 0) {
            return null;
        }

        $qty = 0.0;
        foreach ($quote->getAllItems() as $item) {
            // The packaging line is not one of the products being shipped, and
            // child rows are counted through their parent.
            if ((Packaging::SKU_TO_SELECTION[(string)$item->getSku()] ?? null) !== null
                || $item->getParentItemId()
            ) {
                continue;
            }

            $qty += (float)$item->getQty();
        }

        if ($qty <= $max) {
            return null;
        }

        $this->logRepository->addDebugLog('reusable packaging not offered: cart exceeds maximum', [
            'qty' => $qty,
            'max_qty' => $max,
            'quote_id' => (string)$quote->getId(),
        ]);

        return [
            'code' => 'cart_limit_exceeded',
            'qty' => $qty,
            'max' => $max,
        ];
    }

    /**
     * The first cart item that withdraws the reusable option, or null when the
     * whole cart qualifies.
     *
     * @param CartInterface|null $quote
     * @return array{id: string, sku: string}|null
     */
    public function findBlockingItem(?CartInterface $quote): ?array
    {
        if ($quote === null) {
            return null;
        }

        $storeId = $quote->getStoreId() !== null ? (int)$quote->getStoreId() : null;
        $mode = $this->configRepository->getAllowMode($storeId);
        if ($mode === AllowMode::MODE_ALL) {
            return null;
        }

        $flag = $mode === AllowMode::MODE_EXCLUDE ? Attribute::EXCLUDE : Attribute::INCLUDE;

        foreach ($quote->getAllItems() as $item) {
            $sku = (string)$item->getSku();

            // The packaging line itself is never subject to the rules.
            if ((Packaging::SKU_TO_SELECTION[$sku] ?? null) !== null) {
                continue;
            }

            // Child rows of a configurable/bundle inherit the parent's answer,
            // and are counted through it.
            if ($item->getParentItemId()) {
                continue;
            }

            $product = $item->getProduct();
            $flagged = $product !== null && (bool)$product->getData($flag);

            // Excluding blocks the flagged products; including blocks everything
            // that is not flagged.
            $blocks = $mode === AllowMode::MODE_EXCLUDE ? $flagged : !$flagged;

            if ($blocks) {
                $blocking = [
                    'id' => (string)$item->getProductId(),
                    'sku' => $sku,
                ];

                $this->logRepository->addDebugLog('reusable packaging not offered', [
                    'mode' => $mode,
                    'attribute' => $flag,
                    'product_id' => $blocking['id'],
                    'product_sku' => $blocking['sku'],
                    'quote_id' => (string)$quote->getId(),
                ]);

                return $blocking;
            }
        }

        return null;
    }
}
