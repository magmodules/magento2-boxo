<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Controller\Adminhtml\ApiTest;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Model\Api\Client as BoxoApiClient;

/**
 * AJAX controller verifying an API key against the BOXO API.
 *
 * Tests what is currently entered in the configuration form, so a key can be
 * checked before it is saved.
 */
class Index extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magmodules_Boxo::config';

    /**
     * Value an unchanged obscured field posts back.
     *
     * @see \Magento\Framework\Data\Form\Element\Obscure
     */
    private const OBSCURED_VALUE = '******';

    public function __construct(
        Action\Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly ConfigRepository $configRepository,
        private readonly BoxoApiClient $apiClient
    ) {
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        // Sandbox is a developer-only setting with no field in the admin, so it
        // is always read from the stored configuration.
        $sandbox = $this->configRepository->isSandboxMode();
        $apiKey = $this->resolveApiKey();

        $result = $this->apiClient->testApiKey($apiKey, $sandbox);
        $result['environment'] = $sandbox ? 'sandbox' : 'production';

        return $this->resultJsonFactory->create()->setData(['result' => $result]);
    }

    /**
     * An untouched obscured field posts asterisks rather than the key, which
     * means the admin wants the saved key tested.
     *
     * @return string
     */
    private function resolveApiKey(): string
    {
        $posted = trim((string)$this->getRequest()->getParam('api_key', ''));

        if ($posted !== '' && $posted !== self::OBSCURED_VALUE) {
            return $posted;
        }

        return $this->configRepository->getApiKey();
    }
}
