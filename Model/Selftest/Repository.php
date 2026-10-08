<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Selftest;

use Magmodules\Boxo\Api\Selftest\RepositoryInterface as SelftestRepositoryInterface;

/**
 * Selftest repository class
 */
class Repository implements SelftestRepositoryInterface
{

    /**
     * @var array
     */
    private $testList;

    /**
     * @param array $testList
     */
    public function __construct(
        array $testList = []
    ) {
        $this->testList = $testList;
    }

    /**
     * @inheritDoc
     */
    public function test(): array
    {
        $result = [];
        foreach ($this->testList as $test) {
            $result[] = $test->execute();
        }

        return $result;
    }
}
