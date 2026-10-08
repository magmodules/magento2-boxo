<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Controller\Ajax;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Exception\PackagingUnavailableException;
use Magmodules\Boxo\Model\Api\Client as BoxoApiClient;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\ReusableEligibility;
use Magmodules\Boxo\Model\Quote\InStorePickupResolver;
use Magmodules\Boxo\Model\Quote\PackagingCartManager;

class SetSelection implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ConfigRepository $configRepository,
        private readonly BoxoApiClient $apiClient,
        private readonly LogRepository $logRepository,
        private readonly PackagingCartManager $cartManager,
        private readonly InStorePickupResolver $pickupResolver,
        private readonly ReusableEligibility $reusableEligibility
    ) {
    }

    /**
     * Persist customer's BOXO selection on the active quote.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->configRepository->isEnabled()) {
            return $result->setData(['success' => false, 'error' => 'disabled']);
        }

        $selection = $this->resolveSelection(
            strtolower(trim((string)$this->request->getParam('selection', '')))
        );
        if ($selection === false) {
            return $result->setData(['success' => false, 'error' => 'invalid_selection']);
        }

        try {
            $quote = $this->checkoutSession->getQuote();

            if ($selection !== null) {
                // The retailer's product rules can withdraw reusable packaging
                // for the whole cart, and BOXO is then not offered at all —
                // single-use on its own is not a BOXO choice. The frontend hides
                // the block; this makes sure nothing can be attached anyway.
                if (!$this->reusableEligibility->isAllowed($quote)) {
                    return $result->setData(['success' => false, 'error' => 'reusable_not_allowed']);
                }

                // Packaging is a shipping packaging: an order collected in store
                // never gets one, no matter what the frontend asks for.
                if ($this->pickupResolver->isPickup($quote)) {
                    $this->logRepository->addDebugLog('setSelection rejected: in-store pickup', [
                        'selection' => $selection,
                    ]);
                    return $result->setData(['success' => false, 'error' => 'in_store_pickup']);
                }

                // Re-verify availability server-side so the frontend can't be tricked
                // into attaching a deposit/fee for an unsupported postcode/country.
                // Read both from the request — the quote address isn't populated
                // until estimate-shipping-methods commits it.
                $shipping = $quote->getShippingAddress();

                $country = strtoupper(trim((string)$this->request->getParam('country', '')));
                if ($country === '') {
                    $country = $shipping ? strtoupper((string)$shipping->getCountryId()) : '';
                }
                if ($country !== ConfigRepository::SUPPORTED_COUNTRY_CODE) {
                    $this->logRepository->addDebugLog('setSelection rejected: unsupported country', [
                        'country' => $country,
                        'selection' => $selection,
                    ]);
                    return $result->setData(['success' => false, 'error' => 'unsupported_country']);
                }

                $postcode = trim((string)$this->request->getParam('postcode', ''));
                if ($postcode === '') {
                    $postcode = $shipping ? trim((string)$shipping->getPostcode()) : '';
                }
                if ($postcode === '' || $this->apiClient->checkServiceAvailable($postcode) !== true) {
                    return $result->setData(['success' => false, 'error' => 'unavailable']);
                }
            }

            // Sync the cart: add/remove the matching BOXO product line item.
            $this->cartManager->applySelection($quote, $selection);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            return $result->setData([
                'success' => true,
                'selection' => $selection,
            ]);
        } catch (PackagingUnavailableException $e) {
            // A configuration problem the retailer can fix, not a crash: report
            // it as such so the checkout can name it instead of saying the
            // server failed.
            return $result->setData([
                'success' => false,
                'error' => 'packaging_unavailable',
                'detail' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logRepository->addErrorLog('setSelection exception', [
                'message' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return $result->setData(['success' => false, 'error' => 'server_error']);
        }
    }

    /**
     * Selection is bound to the customer's own checkout session — no CSRF risk
     * since there's no cross-origin state change against another user's cart.
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Normalize the textual selection sent by the frontend.
     *
     * @param string $selection
     * @return string|null|false canonical selection key, null for "none"
     *                           (remove any BOXO line), or false for invalid input
     */
    private function resolveSelection(string $selection): string|null|false
    {
        return match ($selection) {
            '', 'none' => null,
            Packaging::SELECTION_REUSABLE, Packaging::SELECTION_DISPOSABLE => $selection,
            default => false,
        };
    }
}
