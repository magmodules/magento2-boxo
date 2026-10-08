<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magmodules\Boxo\Exception\PackagingUnavailableException;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Packaging;

/**
 * Keeps a quote's BOXO line item in sync with the customer's checkout selection.
 *
 * The choice itself is stored on the quote, because a free option produces no
 * line item to read it back from. Line items carry the charge only.
 */
class PackagingCartManager
{
    /**
     * Column on quote / sales_order holding the customer's choice.
     */
    public const SELECTION_FIELD = 'boxo_packaging';

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LogRepository $logRepository,
        private readonly ConfigRepository $configRepository
    ) {
    }

    /**
     * Make the quote reflect the given selection: at most one BOXO product is
     * present at qty 1. A null/empty/unknown selection removes any BOXO line.
     *
     * @param CartInterface $quote
     * @param string|null $selection 'reusable' | 'disposable' | null
     * @throws PackagingUnavailableException when the product cannot be added
     */
    public function applySelection(CartInterface $quote, ?string $selection): void
    {
        // Recorded on the quote regardless of price: a free option leaves no
        // line item behind, and the choice must survive a checkout reload.
        $quote->setData(self::SELECTION_FIELD, $selection);

        $targetSku = $this->costsMoney($quote, $selection)
            ? (Packaging::SELECTION_TO_SKU[$selection] ?? null)
            : null;

        // Drop every BOXO line that doesn't match the target so switching
        // options never leaves both in the cart. Flag the item as deleted
        // (rather than removeItem(), which relies on a persisted item id) so it
        // is excluded from totals and dropped on save in every state.
        foreach ($quote->getAllItems() as $item) {
            $sku = $item->getSku();
            if ((Packaging::SKU_TO_SELECTION[(string)$sku] ?? null) === null) {
                continue;
            }
            if ($sku === $targetSku) {
                continue;
            }
            $item->isDeleted(true);
        }

        if ($targetSku === null) {
            return;
        }

        // Already present (option unchanged) — nothing to add.
        if ($this->findItemBySku($quote, $targetSku) !== null) {
            return;
        }

        try {
            $product = $this->productRepository->get($targetSku, false, $quote->getStoreId());
        } catch (NoSuchEntityException $e) {
            $this->logRepository->addErrorLog('BOXO packaging product missing', [
                'sku' => $targetSku,
                'store_id' => (string)$quote->getStoreId(),
                'message' => $e->getMessage(),
            ]);
            throw new PackagingUnavailableException(
                __('The BOXO packaging product %1 is not available in this store.', $targetSku)
            );
        }

        try {
            $result = $quote->addProduct($product, 1);
        } catch (LocalizedException $e) {
            // Magento refuses unsalable products here — typically the product is
            // not assigned to a source in the stock that serves this website.
            $this->logRepository->addErrorLog('BOXO packaging not salable', [
                'sku' => $targetSku,
                'store_id' => (string)$quote->getStoreId(),
                'message' => $e->getMessage(),
            ]);
            throw new PackagingUnavailableException(
                __('The BOXO packaging product %1 cannot be added in this store: %2', $targetSku, $e->getMessage())
            );
        }

        if (is_string($result)) {
            // addProduct() returns an error string instead of throwing.
            $this->logRepository->addErrorLog('BOXO packaging add to cart failed', [
                'sku' => $targetSku,
                'store_id' => (string)$quote->getStoreId(),
                'error' => $result,
            ]);
            throw new PackagingUnavailableException(
                __('The BOXO packaging product %1 could not be added: %2', $targetSku, $result)
            );
        }
    }

    /**
     * The customer's recorded choice, or null when none was made.
     *
     * @param CartInterface $quote
     * @return string|null
     */
    public function getCurrentSelection(CartInterface $quote): ?string
    {
        $stored = $quote->getData(self::SELECTION_FIELD);
        if (is_string($stored) && (Packaging::SELECTION_TO_SKU[$stored] ?? null) !== null) {
            return $stored;
        }

        // Carts created before the choice was stored still carry their line item.
        foreach ($quote->getAllItems() as $item) {
            $selection = (Packaging::SKU_TO_SELECTION[(string)$item->getSku()] ?? null);
            if ($selection !== null) {
                return $selection;
            }
        }

        return null;
    }

    /**
     * Whether the selected option carries a charge and therefore needs a line
     * item. A free option is recorded on the quote alone, so the customer is not
     * shown a nil amount in the order summary.
     *
     * @param CartInterface $quote
     * @param string|null $selection
     * @return bool
     */
    private function costsMoney(CartInterface $quote, ?string $selection): bool
    {
        if ($selection === null) {
            return false;
        }

        if ($selection !== Packaging::SELECTION_DISPOSABLE) {
            // The reusable deposit is fixed by BOXO and always charged.
            return true;
        }

        $storeId = $quote->getStoreId() !== null ? (int)$quote->getStoreId() : null;

        return $this->configRepository->getDisposableSurcharge($storeId) > 0;
    }

    /**
     * @param CartInterface $quote
     * @param string $sku
     * @return \Magento\Quote\Api\Data\CartItemInterface|null
     */
    private function findItemBySku(CartInterface $quote, string $sku)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getSku() === $sku) {
                return $item;
            }
        }
        return null;
    }
}
