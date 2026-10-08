<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Log;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigProvider;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepositoryInterface;
use Magmodules\Boxo\Logger\DebugLogger;
use Magmodules\Boxo\Logger\ErrorLogger;

class Repository implements LogRepositoryInterface
{
    public function __construct(
        private readonly DebugLogger $debugLogger,
        private readonly ErrorLogger $errorLogger,
        private readonly DirectoryList $dir,
        private readonly File $file,
        private readonly DateTime $dateTime,
        private readonly ConfigProvider $configProvider
    ) {
    }

    /**
     * @inheritDoc
     */
    public function addErrorLog(string $type, $data)
    {
        $this->errorLogger->addLog($type, $data);
    }

    /**
     * @inheritDoc
     */
    public function addDebugLog(string $type, $data)
    {
        if ($this->configProvider->isDebugEnabled()) {
            $this->debugLogger->addLog($type, $data);
        }
    }

    /**
     * @inheritDoc
     */
    public function getLogFilePath(string $type): ?string
    {
        try {
            return sprintf(LogRepositoryInterface::LOG_FILE, $this->dir->getPath('var'), $type);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getLogEntriesAsArray(string $path, ?int $limit = null): ?array
    {
        try {
            if (!$this->file->isExists($path)) {
                return null;
            }

            $stream = $this->file->fileOpen($path, 'r');
            $this->file->fileSeek($stream, 0, SEEK_END);
            $pos = $this->file->fileTell($stream);
            $numberOfLines = LogRepository::STREAM_DEFAULT_LIMIT;
            while ($pos >= 0 && $numberOfLines > 0) {
                $this->file->fileSeek($stream, $pos);
                $char = $this->file->fileRead($stream, 1);
                if ($char === "\n") {
                    $numberOfLines--;
                }
                $pos--;
            }

            $result = [];
            $lines = [];
            if ($numberOfLines < LogRepository::STREAM_DEFAULT_LIMIT) {
                $offset = $this->file->fileTell($stream);
                $this->file->fileSeek($stream, 0, SEEK_END);
                $length = $this->file->fileTell($stream) - $offset;
                $this->file->fileSeek($stream, $offset);
                $lines = $length > 0 ? explode("\n", $this->file->fileRead($stream, $length)) : [];
            }
            foreach ($lines as $line) {
                if ($line !== '') {
                    $data = explode('] ', $line);
                    $date = ltrim(array_shift($data), '[');
                    $data = implode('] ', $data);
                    $data = explode(': ', $data);
                    unset($data[0]);
                    $type = $data[1] ?? '--';
                    array_shift($data);

                    $result[] = [
                        'date' => $this->dateTime->date('Y-m-d H:i:s', $date) . ' - ' . $type,
                        'msg' => implode(': ', $data)
                    ];
                }
            }

            $this->file->fileClose($stream);
            return array_reverse($result);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
