<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magmodules\Boxo\Api\Config\RepositoryInterface;
use Magmodules\Boxo\Model\Config\Source\AllowMode;
use Magmodules\Boxo\Model\Packaging;
use Throwable;

class Repository implements RepositoryInterface
{

    /**
     * Lazy-loaded contents of the module composer.json.
     *
     * @var array|null
     */
    private ?array $composerData = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly FileDriver $fileDriver,
        private readonly Json $json,
        private readonly ProductMetadataInterface $metadata
    ) {
    }

    public function getExtensionVersion(): string
    {
        return 'v' . (string)($this->getComposerData()['version'] ?? '0.0.0');
    }

    public function getMagentoVersion(): string
    {
        return $this->metadata->getVersion();
    }

    public function getSupportLink(): string
    {
        $links = $this->getComposerData()['extra']['magmodules']['links'] ?? [];

        return (string)($links['docs']['en'] ?? $links['support']['en'] ?? '');
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ACTIVE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isSandboxMode(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SANDBOX_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Sandbox has no field in the admin: retailers are issued a single
     * production key. The paths below stay readable so plugin developers can
     * point an installation at the sandbox through config, without exposing a
     * switch that a retailer has no key for.
     */
    public function getApiKey(?int $storeId = null): string
    {
        $path = $this->isSandboxMode($storeId)
            ? self::XML_PATH_SANDBOX_API_KEY
            : self::XML_PATH_API_KEY;

        $value = $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value ? $this->encryptor->decrypt($value) : '';
    }

    public function getApiBaseUrl(?int $storeId = null): string
    {
        $override = $this->getApiBaseUrlOverride($storeId);
        if ($override !== '') {
            return $override;
        }

        return $this->isSandboxMode($storeId)
            ? self::SANDBOX_BASE_URL
            : self::API_BASE_URL;
    }

    public function getApiBaseUrlOverride(?int $storeId = null): string
    {
        $override = trim((string)$this->scopeConfig->getValue(
            self::XML_PATH_API_BASE_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        // A trailing slash would double up against the paths the client appends.
        return rtrim($override, '/');
    }

    public function getInfoUrl(?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_INFO_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDefaultSelection(?int $storeId = null): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_DEFAULT_SELECTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // Guard against a stale or hand-edited value: reusable is the documented
        // default and the option BOXO exists for.
        return (Packaging::SELECTION_TO_SKU[$value] ?? null) !== null
            ? $value
            : Packaging::SELECTION_REUSABLE;
    }

    public function getAllowMode(?int $storeId = null): string
    {
        $mode = (string)$this->scopeConfig->getValue(
            self::XML_PATH_ALLOW_MODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $known = [AllowMode::MODE_ALL, AllowMode::MODE_EXCLUDE, AllowMode::MODE_INCLUDE];

        // An unknown mode must not silently restrict or open up the catalog;
        // fall back to the documented default.
        return in_array($mode, $known, true) ? $mode : AllowMode::MODE_ALL;
    }

    public function getMaxReusableQty(?int $storeId = null): int
    {
        $max = (int)$this->scopeConfig->getValue(
            self::XML_PATH_MAX_QTY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // 0 (and anything nonsensical) means the retailer set no limit.
        return $max > 0 ? $max : 0;
    }

    public function isDisposableSurchargeEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DISPOSABLE_SURCHARGE_ENABLE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getDisposableSurcharge(?int $storeId = null): float
    {
        if (!$this->isDisposableSurchargeEnabled($storeId)) {
            return 0.0;
        }

        $amount = (float)$this->scopeConfig->getValue(
            self::XML_PATH_DISPOSABLE_SURCHARGE_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        // A negative surcharge would discount the order; treat it as unset.
        return $amount > 0 ? $amount : 0.0;
    }

    public function isDebugEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG);
    }

    /**
     * Read and cache the module composer.json, never throwing on a missing or malformed file.
     *
     * @return array
     */
    private function getComposerData(): array
    {
        if ($this->composerData !== null) {
            return $this->composerData;
        }

        $this->composerData = [];
        $path = $this->componentRegistrar->getPath('module', self::EXTENSION_CODE);

        if (!$path) {
            return $this->composerData;
        }

        try {
            $filePath = $path . '/composer.json';
            if ($this->fileDriver->isExists($filePath)) {
                $this->composerData = (array)$this->json->unserialize(
                    $this->fileDriver->fileGetContents($filePath)
                );
            }
        } catch (Throwable $e) {
            $this->composerData = [];
        }

        return $this->composerData;
    }
}
