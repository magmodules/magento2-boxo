<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Logger;

use Magento\Framework\Serialize\Serializer\Json;
use Monolog\Logger;

/**
 * DebugLogger uses composition to log debug data via Monolog
 */
class DebugLogger
{
    public function __construct(
        private readonly Logger $logger,
        private readonly Json $json
    ) {
    }

    /**
     * Add debug data to log
     *
     * @param string $type
     * @param mixed $data
     * @return void
     */
    public function addLog(string $type, $data): void
    {
        $message = $type . ': ';

        if (is_array($data) || is_object($data)) {
            $message .= $this->json->serialize($data);
        } else {
            $message .= (string)$data;
        }

        $this->logger->info($message);
    }
}
