<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Controller\Ajax;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Api\Client as BoxoApiClient;

class CheckAvailability implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Dutch postcode: 4 digits (never leading zero) followed by 2 letters.
     */
    private const POSTCODE_PATTERN = '/^[1-9][0-9]{3}\s*[A-Za-z]{2}$/';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ConfigRepository $configRepository,
        private readonly BoxoApiClient $apiClient,
        private readonly LogRepository $logRepository
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->configRepository->isEnabled()) {
            return $result->setData(['available' => false]);
        }

        $country = strtoupper(trim((string)$this->request->getParam('country', '')));
        if ($country !== '' && $country !== ConfigRepository::SUPPORTED_COUNTRY_CODE) {
            // BOXO Returns is only available in the Netherlands — short-circuit
            // before hitting the API so we don't waste API quota on unsupported
            // shipping countries.
            $this->logRepository->addDebugLog('checkAvailability skipped: unsupported country', [
                'country' => $country,
            ]);
            return $result->setData(['available' => false, 'error' => 'unsupported_country']);
        }

        $postcode = trim((string)$this->request->getParam('postcode', ''));
        if ($postcode === '') {
            return $result->setData(['available' => false, 'error' => 'missing_postcode']);
        }

        // Only complete Dutch postcodes (1234 AB) reach the API; partially typed
        // values would return a 400 and burn API quota for nothing.
        if (!preg_match(self::POSTCODE_PATTERN, $postcode)) {
            $this->logRepository->addDebugLog('checkAvailability skipped: incomplete postcode', [
                'postcode' => $postcode,
            ]);
            return $result->setData(['available' => false, 'error' => 'invalid_postcode']);
        }

        $available = $this->apiClient->checkServiceAvailable($postcode);

        // null = API error → treat as unavailable but flag separately so the frontend
        // can decide to retry instead of caching a false negative.
        if ($available === null) {
            return $result->setData(['available' => false, 'error' => 'api_error']);
        }

        return $result->setData(['available' => (bool)$available]);
    }

    /**
     * Read-only availability probe — no state change, safe to skip form_key.
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
