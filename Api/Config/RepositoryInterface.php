<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Api\Config;

interface RepositoryInterface
{
    public const EXTENSION_CODE = 'Magmodules_Boxo';

    public const XML_PATH_ACTIVE = 'magmodules_boxo/general/active';
    public const XML_PATH_SANDBOX_MODE = 'magmodules_boxo/general/sandbox_mode';
    public const XML_PATH_API_KEY = 'magmodules_boxo/general/api_key';
    public const XML_PATH_SANDBOX_API_KEY = 'magmodules_boxo/general/sandbox_api_key';
    public const XML_PATH_DEFAULT_SELECTION = 'magmodules_boxo/general/default_selection';
    public const XML_PATH_ALLOW_MODE = 'magmodules_boxo/general/allow_mode';
    public const XML_PATH_MAX_QTY = 'magmodules_boxo/general/max_qty';
    public const XML_PATH_DISPOSABLE_SURCHARGE_ENABLE = 'magmodules_boxo/general/disposable_surcharge_enable';
    public const XML_PATH_DISPOSABLE_SURCHARGE_AMOUNT = 'magmodules_boxo/general/disposable_surcharge_amount';
    public const XML_PATH_INFO_URL = 'magmodules_boxo/general/info_url';
    public const XML_PATH_DEBUG = 'magmodules_boxo/debug/enable';

    /**
     * Points the integration at a stand-in for the BOXO API, used by the E2E suite. Never exposed
     * to a merchant — see etc/adminhtml/system/developer.xml.
     */
    public const XML_PATH_API_BASE_URL = 'magmodules_boxo/developer/api_base_url';

    /**
     * Default landing page used by the info icon when the retailer has not
     * configured a URL of their own.
     */
    public const BOXO_RETURN_PAGE_URL = 'https://www.boxo.nu/inleverpunten';

    public const API_BASE_URL = 'https://api.boxo.nu';
    public const SANDBOX_BASE_URL = 'https://sandbox-api.boxo.nu';

    /**
     * BOXO Returns is only available for shipping addresses in the Netherlands.
     */
    public const SUPPORTED_COUNTRY_CODE = 'NL';

    /**
     * Installed extension version, prefixed with a "v".
     *
     * @return string
     */
    public function getExtensionVersion(): string;

    /**
     * Manual & support page for this extension.
     *
     * @return string
     */
    public function getSupportLink(): string;

    /**
     * Version of the Magento installation this extension runs on.
     *
     * @return string
     */
    public function getMagentoVersion(): string;

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool;

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isSandboxMode(?int $storeId = null): bool;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey(?int $storeId = null): string;

    /**
     * @param int|null $storeId
     * @return string
     */
    public function getApiBaseUrl(?int $storeId = null): string;

    /**
     * Base URL standing in for the BOXO API, or an empty string when the real API is to be used.
     *
     * Separate from {@see self::getApiBaseUrl()} because the admin's API-key test has to honour
     * the override while still choosing its environment from what is typed into the form rather
     * than from what is stored.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiBaseUrlOverride(?int $storeId = null): string;

    /**
     * Optional retailer-defined URL behind the info icon at checkout. Empty
     * means the customer is sent to {@see self::BOXO_RETURN_PAGE_URL}.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getInfoUrl(?int $storeId = null): string;

    /**
     * Packaging option pre-selected at checkout.
     *
     * @param int|null $storeId
     * @return string 'reusable' | 'disposable'
     */
    public function getDefaultSelection(?int $storeId = null): string;

    /**
     * Which products may be shipped in reusable packaging.
     *
     * @param int|null $storeId
     * @return string 'all' | 'exclude' | 'include'
     */
    public function getAllowMode(?int $storeId = null): string;

    /**
     * How many products fit in one reusable container.
     *
     * @param int|null $storeId
     * @return int 0 when no limit is configured
     */
    public function getMaxReusableQty(?int $storeId = null): int;

    /**
     * @param int|null $storeId
     * @return bool
     */
    public function isDisposableSurchargeEnabled(?int $storeId = null): bool;

    /**
     * Surcharge charged for disposable packaging, excluding VAT.
     *
     * @param int|null $storeId
     * @return float 0.0 when the surcharge is disabled
     */
    public function getDisposableSurcharge(?int $storeId = null): float;

    /**
     * @return bool
     */
    public function isDebugEnabled(): bool;
}
