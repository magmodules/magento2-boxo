<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Quote;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Tells whether a quote is being picked up in store rather than shipped.
 *
 * BOXO packaging is a shipping packaging: an order collected at the counter
 * needs none, so no deposit or disposable fee may be attached to it.
 *
 * The delivery method is matched by its string identifier instead of through
 * Magento\InventoryInStorePickupShippingApi so the module keeps working on
 * installations where the MSI in-store pickup modules are absent.
 */
class InStorePickupResolver
{
    /**
     * Magento's native In-Store Pickup delivery method: carrier "instore",
     * method "pickup". Extend this list when a store offers pickup through
     * another carrier.
     */
    public const PICKUP_CARRIER_CODE = 'instore';
    public const PICKUP_METHOD_CODE = 'pickup';
    public const PICKUP_DELIVERY_METHOD = self::PICKUP_CARRIER_CODE . '_' . self::PICKUP_METHOD_CODE;

    /**
     * @param CartInterface $quote
     * @return bool
     */
    public function isPickup(CartInterface $quote): bool
    {
        $address = method_exists($quote, 'getShippingAddress') ? $quote->getShippingAddress() : null;
        if ($address === null) {
            return false;
        }

        return $this->isPickupMethod((string)$address->getShippingMethod());
    }

    /**
     * @param string $shippingMethod carrier_method identifier, e.g. "instore_pickup"
     * @return bool
     */
    public function isPickupMethod(string $shippingMethod): bool
    {
        return trim($shippingMethod) === self::PICKUP_DELIVERY_METHOD;
    }

    /**
     * @param string|null $carrierCode
     * @param string|null $methodCode
     * @return bool
     */
    public function isPickupCarrier(?string $carrierCode, ?string $methodCode): bool
    {
        return $carrierCode === self::PICKUP_CARRIER_CODE && $methodCode === self::PICKUP_METHOD_CODE;
    }
}
