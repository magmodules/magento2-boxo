<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Controller\Adminhtml\VersionCheck;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * AJAX controller to check the latest available extension version
 */
class Index extends Action
{

    public const ADMIN_RESOURCE = 'Magmodules_Boxo::config';
    public const VERSION_URL = 'https://version.magmodules.eu/%s.json';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var JsonSerializer
     */
    private $json;
    /**
     * @var File
     */
    private $file;

    /**
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ConfigRepository $configRepository
     * @param JsonSerializer $json
     * @param File $file
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        ConfigRepository $configRepository,
        JsonSerializer $json,
        File $file
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->configRepository = $configRepository;
        $this->json = $json;
        $this->file = $file;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();
        $current = $latest = preg_replace('/^v/', '', $this->configRepository->getExtensionVersion());
        $changeLog = [];
        $result = $this->getVersions();

        if ($result) {
            try {
                $data = (array)$this->json->unserialize($result);
                $versions = array_keys($data);

                if ($versions) {
                    $latest = preg_replace('/^v/', '', (string)reset($versions));
                }

                foreach ($data as $version => $changes) {
                    if (version_compare(preg_replace('/^v/', '', (string)$version), $current) == 0) {
                        break;
                    }
                    $changeLog[] = [
                        $version => $changes['changelog'] ?? ''
                    ];
                }
            } catch (Exception $exception) {
                $latest = $current;
            }
        }

        return $resultJson->setData([
            'result' => [
                'current_version' => 'v' . $current,
                'last_version' => 'v' . $latest,
                'changelog' => $changeLog,
            ]
        ]);
    }

    /**
     * @return string
     */
    private function getVersions(): string
    {
        try {
            return $this->file->fileGetContents(
                sprintf(self::VERSION_URL, ConfigRepository::EXTENSION_CODE)
            );
        } catch (Exception $exception) {
            return '';
        }
    }
}
