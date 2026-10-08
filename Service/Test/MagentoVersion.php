<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Service class to check the Magento version compatibility.
 */
class MagentoVersion
{
    public const TYPE = 'magento_version';
    public const TEST = 'Check if current Magento version is supported for this module version';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Magento version match';
    public const FAILED_MSG = 'Minimum required Magento 2 version is %1, current version is %2!';
    public const SUPPORT_LINK = 'https://www.magmodules.eu/help/magento2/minimum-magento-version.html';
    public const EXPECTED = '2.4';

    public function __construct(private readonly ConfigRepository $configProvider)
    {
    }

    /**
     * Executes the Magento version compatibility test.
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

        $magentoVersion = $this->configProvider->getMagentoVersion();

        if (version_compare($magentoVersion, self::EXPECTED, '>=')) {
            $result['result_msg'] = (string)__(self::SUCCESS_MSG);
            $result['result_code'] = 'success';
        } else {
            $result['result_msg'] = (string)__(self::FAILED_MSG, self::EXPECTED, $magentoVersion);
            $result['result_code'] = 'failed';
            $result['support_link'] = self::SUPPORT_LINK;
        }

        return $result;
    }
}
