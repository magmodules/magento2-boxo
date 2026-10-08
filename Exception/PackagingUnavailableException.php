<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * The packaging product cannot be put in the cart in this store.
 *
 * Distinct from a generic failure so the checkout can name the cause instead of
 * reporting an internal error: the usual reasons are a product that is missing,
 * disabled, or left out of the stock serving the website — all of them
 * configuration, all of them fixable by the retailer once they are told.
 */
class PackagingUnavailableException extends LocalizedException
{
}
