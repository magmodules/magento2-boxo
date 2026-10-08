<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Service class to check the extension status.
 */
class ExtensionStatus
{
    public const TYPE = 'extension_status';
    public const TEST = 'Check if the extension is enabled in the configuration';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Extension enabled';
    public const FAILED_MSG = 'Extension disabled, please enable it!';

    public function __construct(private readonly ConfigRepository $configProvider)
    {
    }

    /**
     * Executes the extension status test.
     *
     * @return array
     */
    public function execute(): array
    {
        $isEnabled = $this->configProvider->isEnabled();

        return [
            'type' => self::TYPE,
            'test' => (string)__(self::TEST),
            'visible' => self::VISIBLE,
            'result_msg' => $isEnabled ? (string)__(self::SUCCESS_MSG) : (string)__(self::FAILED_MSG),
            'result_code' => $isEnabled ? 'success' : 'failed',
        ];
    }
}
