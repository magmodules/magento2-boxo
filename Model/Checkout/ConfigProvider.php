<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Checkout;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\UrlInterface;
use Magento\Tax\Model\Config as TaxConfig;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\LabelProvider;
use Magmodules\Boxo\Model\Packaging\ReusableEligibility;
use Magmodules\Boxo\Model\Quote\PackagingCartManager;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly UrlInterface $urlBuilder,
        private readonly PricingHelper $pricingHelper,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly PackagingCartManager $cartManager,
        private readonly LabelProvider $labelProvider,
        private readonly CatalogHelper $catalogHelper,
        private readonly TaxConfig $taxConfig,
        private readonly ReusableEligibility $reusableEligibility
    ) {
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        if (!$this->configRepository->isEnabled()) {
            return ['boxo' => ['enabled' => false]];
        }

        // The deposit is fixed by BOXO and lives on the product; the disposable
        // surcharge is the retailer's to set, so it comes from the config.
        // Both are shown the way the cart shows prices, so the amount next to
        // the option matches what the order total actually moves by.
        $deposit = $this->getDisplayPrice(Packaging::SKU_REUSABLE, $this->getProductPrice(Packaging::SKU_REUSABLE));
        $disposable = $this->getDisplayPrice(
            Packaging::SKU_DISPOSABLE,
            $this->configRepository->getDisposableSurcharge()
        );

        $retailerUrl = trim($this->configRepository->getInfoUrl());
        $infoUrl = $retailerUrl !== '' ? $retailerUrl : ConfigRepository::BOXO_RETURN_PAGE_URL;

        return [
            'boxo' => [
                'enabled' => true,
                'supportedCountry' => ConfigRepository::SUPPORTED_COUNTRY_CODE,
                'depositAmount' => $deposit,
                'depositFormatted' => $this->pricingHelper->currency($deposit, true, false),
                'disposableAmount' => $disposable,
                'disposableFormatted' => $disposable > 0
                    ? $this->pricingHelper->currency($disposable, true, false)
                    : '',
                // Every text below is fixed and localized through i18n, so the
                // BOXO proposition reads the same in every shop.
                'heading' => $this->labelProvider->getHeading(),
                'singleUseLabel' => $this->labelProvider->getCheckoutLabel(Packaging::SELECTION_DISPOSABLE),
                'reusableLabel' => $this->labelProvider->getCheckoutLabel(Packaging::SELECTION_REUSABLE),
                'infoUrl' => $infoUrl,
                // False when the cart holds a product the retailer excluded from
                // reusable packaging; the option is then not offered at all.
                'reusableAllowed' => $this->getBlockReason() === null,
                // Names the cause so the checkout can report it in the browser
                // console for support, instead of failing silently.
                'blockReason' => $this->getBlockReason(),
                // Pre-selected once BOXO turns out to be available for the
                // address, unless the cart already holds a choice.
                'defaultSelection' => $this->configRepository->getDefaultSelection(),
                // Restores the radio when the customer returns to checkout with a
                // BOXO product already in the cart.
                'currentSelection' => $this->getCurrentSelection(),
                'urls' => [
                    'checkAvailability' => $this->urlBuilder->getUrl('boxo/ajax/checkAvailability'),
                    'setSelection' => $this->urlBuilder->getUrl('boxo/ajax/setSelection'),
                ],
            ],
        ];
    }

    /**
     * Final price of a BOXO product in the current store, or 0.0 when the product
     * is missing (e.g. the data patch has not run yet).
     */
    private function getProductPrice(string $sku): float
    {
        $product = $this->getProduct($sku);

        return $product !== null ? (float)$product->getPrice() : 0.0;
    }

    /**
     * @param string $sku
     * @return ProductInterface|null null when the product is missing (e.g. the
     *                               data patch has not run yet)
     */
    private function getProduct(string $sku): ?ProductInterface
    {
        try {
            return $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Apply VAT to a net amount when the cart is configured to show prices
     * including tax. The reusable deposit sits in a non-taxable class so it is
     * unaffected; the disposable surcharge is taxable and would otherwise be
     * advertised lower than the amount the order total rises by.
     *
     * @param string $sku
     * @param float $amount net amount
     * @return float
     */
    private function getDisplayPrice(string $sku, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $product = $this->getProduct($sku);
        if ($product === null) {
            return $amount;
        }

        $includingTax = $this->taxConfig->displayCartPricesInclTax()
            || $this->taxConfig->displayCartPricesBoth();

        return (float)$this->catalogHelper->getTaxPrice($product, $amount, $includingTax);
    }

    /**
     * @return string|null
     */
    private function getCurrentSelection(): ?string
    {
        try {
            return $this->cartManager->getCurrentSelection($this->checkoutSession->getQuote());
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Resolved once: the eligibility check walks the cart and logs, and the
     * config is assembled in a single pass.
     *
     * @var array|null
     */
    private ?array $blockReason = null;

    private bool $blockReasonResolved = false;

    /**
     * @return array|null
     */
    private function getBlockReason(): ?array
    {
        if ($this->blockReasonResolved) {
            return $this->blockReason;
        }

        $this->blockReasonResolved = true;

        try {
            $this->blockReason = $this->reusableEligibility->getBlockReason(
                $this->checkoutSession->getQuote()
            );
        } catch (\Exception $e) {
            // Never take the option away because the cart could not be read.
            $this->blockReason = null;
        }

        return $this->blockReason;
    }
}
