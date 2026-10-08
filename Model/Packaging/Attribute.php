<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Packaging;

/**
 * Product attributes carrying the reusable-packaging rules.
 *
 * Two independent flags rather than one tri-state, so each allow mode keeps its
 * own list: a shop can mark its exclusions, switch to "only selected products"
 * to try something out, and switch back without having lost anything.
 */
class Attribute
{
    /**
     * Blocks the product in "All products except selected" mode.
     */
    public const EXCLUDE = 'boxo_reusable_exclude';

    /**
     * Qualifies the product in "Only selected products" mode.
     */
    public const INCLUDE = 'boxo_reusable_include';

    /**
     * @var string[]
     */
    public const ALL = [self::EXCLUDE, self::INCLUDE];
}
