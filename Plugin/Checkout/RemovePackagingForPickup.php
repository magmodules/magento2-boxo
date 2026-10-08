<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Quote\InStorePickupResolver;
use Magmodules\Boxo\Model\Quote\PackagingCartManager;

/**
 * Drops the BOXO packaging line when the customer switches to in-store pickup.
 *
 * The checkout hides the packaging block as soon as pickup is selected, but a
 * choice made before that switch would otherwise stay on the quote and bill a
 * deposit for an order that is never shipped. Runs before the core method so
 * the totals it returns already exclude the removed line.
 */
class RemovePackagingForPickup
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PackagingCartManager $cartManager,
        private readonly InStorePickupResolver $pickupResolver,
        private readonly ConfigRepository $configRepository,
        private readonly LogRepository $logRepository
    ) {
    }

    /**
     * @param ShippingInformationManagementInterface $subject
     * @param int $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return array
     */
    public function beforeSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): array {
        if (!$this->configRepository->isEnabled()) {
            return [$cartId, $addressInformation];
        }

        $isPickup = $this->pickupResolver->isPickupCarrier(
            $addressInformation->getShippingCarrierCode(),
            $addressInformation->getShippingMethodCode()
        );
        if (!$isPickup) {
            return [$cartId, $addressInformation];
        }

        try {
            $quote = $this->cartRepository->getActive((int)$cartId);
            if ($this->cartManager->getCurrentSelection($quote) === null) {
                return [$cartId, $addressInformation];
            }

            // Same in-request quote instance the core method operates on, so the
            // removal is picked up by its collectTotals()/save().
            $this->cartManager->applySelection($quote, null);

            $this->logRepository->addDebugLog('packaging removed: in-store pickup selected', [
                'cart_id' => $cartId,
            ]);
        } catch (\Exception $e) {
            // Never block checkout over this — the selection block is hidden for
            // pickup anyway, so at worst a stale line survives one more step.
            $this->logRepository->addErrorLog('removePackagingForPickup exception', [
                'message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'cart_id' => $cartId,
            ]);
        }

        return [$cartId, $addressInformation];
    }
}
