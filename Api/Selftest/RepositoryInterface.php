<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Api\Selftest;

/**
 * Self-test repository interface
 * @api
 */
interface RepositoryInterface
{

    /**
     * Run all registered self-tests
     *
     * @return array
     */
    public function test(): array;
}
