<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Api;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;

class Client
{
    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly CurlFactory $curlFactory,
        private readonly LogRepository $logRepository
    ) {
    }

    /**
     * Postcode used to probe the API. Only the transport and the key are being
     * validated, so any well-formed Dutch postcode does.
     */
    private const PROBE_POSTCODE = '1012AB';

    /**
     * Verify that a key is accepted by BOXO, without saving it first.
     *
     * Takes the credentials explicitly so the admin can test what is currently
     * typed into the form rather than what was last stored.
     *
     * @param string $apiKey
     * @param bool $sandbox
     * @return array{success: bool, status: int, error: string}
     */
    public function testApiKey(string $apiKey, bool $sandbox): array
    {
        $environment = $sandbox ? 'sandbox' : 'production';

        if (trim($apiKey) === '') {
            return ['success' => false, 'status' => 0, 'error' => 'missing_key'];
        }

        $baseUrl = $this->baseUrlFor($sandbox);
        $url = $baseUrl . '/checkout/service-available/' . self::PROBE_POSTCODE;

        $this->logRepository->addDebugLog('testApiKey request', [
            'url' => $url,
            'environment' => $environment,
            'api_key_masked' => $this->maskKey($apiKey),
        ]);

        try {
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $curl->addHeader('X-Api-Key', $apiKey);
            $curl->addHeader('Accept', 'application/json');
            $curl->setTimeout(10);
            $curl->get($url);

            $status = (int)$curl->getStatus();

            $this->logRepository->addDebugLog('testApiKey response', [
                'url' => $url,
                'environment' => $environment,
                'status' => $status,
            ]);

            // Any answer the API is willing to give means the key was accepted;
            // 400 included, since that is a verdict on the postcode, not on us.
            if ($status === 200 || $status === 400) {
                return ['success' => true, 'status' => $status, 'error' => ''];
            }

            if ($status === 401 || $status === 403) {
                return ['success' => false, 'status' => $status, 'error' => 'rejected'];
            }

            return ['success' => false, 'status' => $status, 'error' => 'unexpected_status'];
        } catch (\Exception $e) {
            $this->logRepository->addErrorLog('testApiKey exception', [
                'message' => $e->getMessage(),
                'url' => $url,
                'exception_class' => get_class($e),
            ]);

            return ['success' => false, 'status' => 0, 'error' => 'unreachable'];
        }
    }

    /**
     * Base URL to probe for the environment selected in the form.
     *
     * The environment comes from the form rather than from stored config — that is the whole
     * point of the test button — but a configured stand-in still wins over both, or the E2E suite
     * would reach for the real api.boxo.nu.
     *
     * @param bool $sandbox
     * @return string
     */
    private function baseUrlFor(bool $sandbox): string
    {
        $override = $this->configRepository->getApiBaseUrlOverride();
        if ($override !== '') {
            return $override;
        }

        return $sandbox ? ConfigRepository::SANDBOX_BASE_URL : ConfigRepository::API_BASE_URL;
    }

    /**
     * @param string $apiKey
     * @return string
     */
    private function maskKey(string $apiKey): string
    {
        if (strlen($apiKey) < 8) {
            return '***';
        }

        return substr($apiKey, 0, 4) . '***' . substr($apiKey, -4);
    }

    /**
     * Check if BOXO service is available for the given postal code.
     *
     * @param string $postalCode
     * @param int|null $storeId
     * @return bool|null null on API error
     */
    public function checkServiceAvailable(string $postalCode, ?int $storeId = null): ?bool
    {
        $postalCode = strtoupper(str_replace(' ', '', $postalCode));
        $baseUrl = $this->configRepository->getApiBaseUrl($storeId);
        $url = $baseUrl . '/checkout/service-available/' . $postalCode;

        $apiKey = $this->configRepository->getApiKey($storeId);
        $maskedKey = substr($apiKey, 0, 4) . '***' . substr($apiKey, -4);
        $this->logRepository->addDebugLog('checkServiceAvailable request', [
            'url' => $url,
            'api_key_masked' => $maskedKey,
            'api_key_length' => strlen($apiKey),
            'base_url' => $baseUrl,
        ]);

        try {
            $curl = $this->curlFactory->create();
            $curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            $curl->addHeader('X-Api-Key', $apiKey);
            $curl->addHeader('Accept', 'application/json');
            $curl->setTimeout(10);
            $curl->get($url);

            $status = $curl->getStatus();
            $body = $curl->getBody();

            $this->logRepository->addDebugLog('checkServiceAvailable response', [
                'url' => $url,
                'status' => $status,
                'body' => $body,
            ]);

            if ($status === 200) {
                $response = json_decode($body, true);
                return $response['available'] ?? false;
            }

            if ($status === 400) {
                $this->logRepository->addDebugLog('checkServiceAvailable invalid postcode', [
                    'postcode' => $postalCode,
                    'body' => $body,
                ]);
                return false;
            }

            $this->logRepository->addErrorLog('checkServiceAvailable unexpected response', [
                'status' => $status,
                'body' => $body,
                'url' => $url,
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logRepository->addErrorLog('checkServiceAvailable exception', [
                'message' => $e->getMessage(),
                'url' => $url,
                'exception_class' => get_class($e),
            ]);
            return null;
        }
    }
}
