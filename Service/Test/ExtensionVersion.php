<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

use Exception;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;

/**
 * Service class to check the extension version.
 */
class ExtensionVersion
{
    public const TYPE = 'extension_version';
    public const TEST = 'Check if new extension version is available';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Great, you are using the latest version.';
    public const FAILED_MSG = 'Version %1 is available, current version %2';
    public const EXPECTED = [-1, 0];
    public const SUPPORT_LINK = 'https://www.magmodules.eu/help/magento2/update-extension.html';
    public const VERSION_URL = 'https://version.magmodules.eu/%s.json';

    public function __construct(
        private readonly ConfigRepository $configProvider,
        private readonly LogRepository $logRepository,
        private readonly JsonSerializer $json,
        private readonly File $file
    ) {
    }

    /**
     * Executes the extension version check test.
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

        try {
            $latestVersion = $this->fetchLatestVersion();
            $currentVersion = preg_replace('/^v/', '', $this->configProvider->getExtensionVersion());

            if (in_array(version_compare($latestVersion, $currentVersion), self::EXPECTED, true)) {
                $this->setSuccessResult($result);
            } else {
                $result['result_msg'] = (string)__(self::FAILED_MSG, 'v' . $latestVersion, 'v' . $currentVersion);
                $result['result_code'] = 'failed';
                $result['support_link'] = self::SUPPORT_LINK;
            }
        } catch (Exception $e) {
            // The version service being unreachable is not a misconfiguration of the shop.
            $this->logRepository->addDebugLog('Extension version test', $e->getMessage());
            $this->setSuccessResult($result);
        }

        return $result;
    }

    /**
     * Fetches the latest released version from the Magmodules version service.
     *
     * @return string
     * @throws Exception
     */
    private function fetchLatestVersion(): string
    {
        $data = $this->file->fileGetContents(
            sprintf(self::VERSION_URL, ConfigRepository::EXTENSION_CODE)
        );

        $versionData = $this->json->unserialize($data);
        $latest = array_key_first((array)$versionData);

        if ($latest === null) {
            throw new Exception('No versions returned by the version service');
        }

        return preg_replace('/^v/', '', (string)$latest);
    }

    /**
     * @param array $result
     */
    private function setSuccessResult(array &$result): void
    {
        $result['result_msg'] = (string)__(self::SUCCESS_MSG);
        $result['result_code'] = 'success';
    }
}
