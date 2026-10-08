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
 * AJAX controller to fetch the extension changelog
 */
class Changelog extends Action
{

    public const ADMIN_RESOURCE = 'Magmodules_Boxo::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
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
     * @param JsonSerializer $json
     * @param File $file
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        JsonSerializer $json,
        File $file
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
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

        try {
            $data = (array)$this->json->unserialize(
                $this->file->fileGetContents(
                    sprintf(Index::VERSION_URL, ConfigRepository::EXTENSION_CODE)
                )
            );
        } catch (Exception $exception) {
            $data = [];
        }

        return $resultJson->setData($data);
    }
}
