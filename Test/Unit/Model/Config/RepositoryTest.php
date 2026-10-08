<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Serialize\Serializer\Json;
use Magmodules\Boxo\Api\Config\RepositoryInterface;
use Magmodules\Boxo\Model\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Resolving which host the module talks to.
 *
 * The stand-in override is what makes the integration testable at all, and it has exactly one job
 * a merchant could be hurt by getting wrong: it must never win unless it was deliberately set.
 */
#[CoversClass(Repository::class)]
class RepositoryTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function baseUrlProvider(): array
    {
        return [
            'no override, production' => ['', false, RepositoryInterface::API_BASE_URL],
            'no override, sandbox' => ['', true, RepositoryInterface::SANDBOX_BASE_URL],
            'override wins over production' => ['http://127.0.0.1:18092', false, 'http://127.0.0.1:18092'],
            'override wins over sandbox' => ['http://127.0.0.1:18092', true, 'http://127.0.0.1:18092'],
            // The client appends paths that start with a slash, so a trailing one would double up.
            'trailing slash stripped' => ['http://127.0.0.1:18092/', false, 'http://127.0.0.1:18092'],
            // An empty admin field stores a whitespace string often enough to matter; treating it
            // as an override would point a live shop at nothing.
            'whitespace is not an override' => ['   ', false, RepositoryInterface::API_BASE_URL],
        ];
    }

    #[DataProvider('baseUrlProvider')]
    public function testTheBaseUrlPrefersADeliberateOverride(string $override, bool $sandbox, string $expected): void
    {
        $repository = $this->repository($override, $sandbox);

        $this->assertSame($expected, $repository->getApiBaseUrl());
    }

    public function testAnAbsentOverrideReadsAsAnEmptyString(): void
    {
        $this->assertSame('', $this->repository('', false)->getApiBaseUrlOverride());
    }

    private function repository(string $override, bool $sandbox): Repository
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $path === RepositoryInterface::XML_PATH_API_BASE_URL ? $override : null
        );
        $scopeConfig->method('isSetFlag')->willReturn($sandbox);

        return new Repository(
            $scopeConfig,
            $this->createMock(EncryptorInterface::class),
            $this->createMock(ComponentRegistrarInterface::class),
            $this->createMock(FileDriver::class),
            $this->createMock(Json::class),
            $this->createMock(ProductMetadataInterface::class)
        );
    }
}
