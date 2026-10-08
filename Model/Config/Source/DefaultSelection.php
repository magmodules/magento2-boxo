<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\LabelProvider;

/**
 * Packaging options a retailer can pre-select for the checkout.
 *
 * Labels come from the same provider the checkout uses, so the admin dropdown
 * always names the options exactly as the customer sees them.
 */
class DefaultSelection implements OptionSourceInterface
{
    public function __construct(private readonly LabelProvider $labelProvider)
    {
    }

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Packaging::SELECTION_REUSABLE,
                'label' => $this->labelProvider->getLabel(Packaging::SELECTION_REUSABLE),
            ],
            [
                'value' => Packaging::SELECTION_DISPOSABLE,
                'label' => $this->labelProvider->getLabel(Packaging::SELECTION_DISPOSABLE),
            ],
        ];
    }
}
