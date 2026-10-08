<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Determines which products may be shipped in reusable BOXO packaging.
 */
class AllowMode implements OptionSourceInterface
{
    /**
     * Every product qualifies; no list is consulted.
     */
    public const MODE_ALL = 'all';

    /**
     * Every product qualifies except the ones on the exclude list.
     */
    public const MODE_EXCLUDE = 'exclude';

    /**
     * Only products on the include list qualify.
     */
    public const MODE_INCLUDE = 'include';

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::MODE_ALL,
                'label' => __('All products'),
            ],
            [
                'value' => self::MODE_EXCLUDE,
                'label' => __('All products except selected'),
            ],
            [
                'value' => self::MODE_INCLUDE,
                'label' => __('Only selected products'),
            ],
        ];
    }
}
