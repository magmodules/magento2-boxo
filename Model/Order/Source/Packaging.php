<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Order\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magmodules\Boxo\Model\Packaging as PackagingModel;
use Magmodules\Boxo\Model\Packaging\LabelProvider;

/**
 * Options behind the packaging column in the order grid.
 *
 * Orders placed before the extension was installed hold no value and render an
 * empty cell, so they are not listed here.
 */
class Packaging implements OptionSourceInterface
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
                'value' => PackagingModel::SELECTION_REUSABLE,
                'label' => $this->labelProvider->getLabel(PackagingModel::SELECTION_REUSABLE),
            ],
            [
                'value' => PackagingModel::SELECTION_DISPOSABLE,
                'label' => $this->labelProvider->getLabel(PackagingModel::SELECTION_DISPOSABLE),
            ],
        ];
    }
}
