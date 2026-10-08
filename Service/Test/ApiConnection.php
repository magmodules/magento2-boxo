<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Service\Test;

use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Model\Api\Client;

/**
 * Service class to check if the BOXO API can be reached with the configured key.
 */
class ApiConnection
{
    public const TYPE = 'api_connection';
    public const TEST = 'Check if a connection to the BOXO API can be established';
    public const VISIBLE = true;
    public const SUCCESS_MSG = 'Connection to the BOXO %1 API established';
    public const NO_KEY_MSG = 'No %1 API key configured, add the key from your BOXO account!';
    public const FAILED_MSG = 'Could not reach the BOXO %1 API, check the API key and see the error log for details.';
    public const SUPPORT_LINK = 'https://www.magmodules.eu/support';

    /**
     * Postcode used as probe, only the transport and the key are being validated here.
     */
    public const PROBE_POSTCODE = '1012AB';

    public function __construct(
        private readonly ConfigRepository $configProvider,
        private readonly Client $client
    ) {
    }

    /**
     * Executes the API connection test.
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

        $environment = $this->configProvider->isSandboxMode() ? 'sandbox' : 'production';

        if ($this->configProvider->getApiKey() === '') {
            $result['result_msg'] = (string)__(self::NO_KEY_MSG, $environment);
            $result['result_code'] = 'failed';

            return $result;
        }

        // Any definitive answer means the request was accepted and authenticated, null means it was not.
        if ($this->client->checkServiceAvailable(self::PROBE_POSTCODE) === null) {
            $result['result_msg'] = (string)__(self::FAILED_MSG, $environment);
            $result['result_code'] = 'failed';
            $result['support_link'] = self::SUPPORT_LINK;

            return $result;
        }

        $result['result_msg'] = (string)__(self::SUCCESS_MSG, $environment);
        $result['result_code'] = 'success';

        return $result;
    }
}
