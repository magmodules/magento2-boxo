<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

/**
 * Service class to check PHP version compatibility.
 */
class PhpVersion
{
    public const TYPE = 'php_version';
    public const TEST = 'Check if current PHP version is supported for this module version';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Version match';
    public const FAILED_MSG = 'Minimum required PHP version: %1, current version is %2!';
    public const EXPECTED = '8.1';
    public const SUPPORT_LINK = 'https://www.magmodules.eu/help/magento2/minimum-server-php-requirements.html';

    /**
     * Executes the PHP version compatibility test.
     *
     * @return array
     */
    public function execute(): array
    {
        $result = [
            'type' => self::TYPE,
            'test' => (string)__(self::TEST),
            'visible' => self::VISIBLE,
        ];

        if (version_compare(PHP_VERSION, self::EXPECTED, '>=')) {
            $result['result_msg'] = (string)__(self::SUCCESS_MSG);
            $result['result_code'] = 'success';
        } else {
            $result['result_msg'] = (string)__(self::FAILED_MSG, self::EXPECTED, PHP_VERSION);
            $result['result_code'] = 'failed';
            $result['support_link'] = self::SUPPORT_LINK;
        }

        return $result;
    }
}
