<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Test\Unit\Model\Api;

use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Api\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * How the BOXO API's answers are turned into verdicts.
 *
 * The distinction that matters throughout: false means "BOXO says no", null means "we do not
 * know". They are not interchangeable — a checkout that reads a transport failure as "not
 * available" silently withdraws the option every time the API hiccups.
 */
#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    private const API_KEY = 'key-1234567890';

    /**
     * @return array<string, array{0: int, 1: string, 2: bool|null}>
     */
    public static function serviceAvailabilityProvider(): array
    {
        return [
            'available' => [200, '{"available":true}', true],
            'not available' => [200, '{"available":false}', false],
            // A 200 without the key is BOXO answering; absent means not available.
            'key absent from the payload' => [200, '{}', false],
            // 400 is a verdict on the postcode, not a failure on our side.
            'rejected postcode' => [400, '{"error":"invalid postcode"}', false],
            'unauthorised' => [401, '', null],
            'server error' => [500, 'Internal Server Error', null],
            'html error page instead of json' => [200, '<html>maintenance</html>', false],
        ];
    }

    #[DataProvider('serviceAvailabilityProvider')]
    public function testServiceAvailabilityIsDerivedFromTheResponse(int $status, string $body, ?bool $expected): void
    {
        $curl = $this->curl($status, $body);

        $this->assertSame($expected, $this->client($curl)->checkServiceAvailable('1012AB'));
    }

    /**
     * A transport failure is not an answer. Reading it as "unavailable" would hide an outage
     * behind a checkout that simply stops offering BOXO.
     */
    public function testATransportFailureIsNotAnAnswer(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('get')->willThrowException(new \RuntimeException('Connection timed out'));

        $this->assertNull($this->client($curl)->checkServiceAvailable('1012AB'));
    }

    /**
     * BOXO's endpoint takes the postcode in the path, without a space and upper-cased. Passing
     * what the customer typed straight through turns valid postcodes into 400s.
     */
    public function testThePostcodeIsNormalisedIntoThePath(): void
    {
        $curl = $this->curl(200, '{"available":true}');
        $curl->expects($this->once())
            ->method('get')
            ->with($this->stringEndsWith('/checkout/service-available/1012AB'));

        $this->client($curl)->checkServiceAvailable(' 1012 ab ');
    }

    /**
     * @return array<string, array{0: int, 1: bool, 2: string}>
     */
    public static function apiKeyVerdictProvider(): array
    {
        return [
            // Any answer BOXO is willing to give means the key was accepted; 400 included,
            // since that is a verdict on the probe postcode rather than on the credentials.
            'accepted' => [200, true, ''],
            'accepted, postcode rejected' => [400, true, ''],
            'rejected' => [401, false, 'rejected'],
            'forbidden' => [403, false, 'rejected'],
            'server error' => [500, false, 'unexpected_status'],
        ];
    }

    #[DataProvider('apiKeyVerdictProvider')]
    public function testAnApiKeyVerdictFollowsTheStatus(int $status, bool $success, string $error): void
    {
        $result = $this->client($this->curl($status, ''))->testApiKey(self::API_KEY, false);

        $this->assertSame(
            ['success' => $success, 'status' => $status, 'error' => $error],
            $result
        );
    }

    /**
     * An empty key is our own mistake, not BOXO's — answered without spending a request on it.
     */
    public function testAnEmptyApiKeyIsRejectedWithoutACall(): void
    {
        $factory = $this->createMock(CurlFactory::class);
        $factory->expects($this->never())->method('create');

        $client = new Client(
            $this->createMock(ConfigRepository::class),
            $factory,
            $this->createMock(LogRepository::class)
        );

        $this->assertSame(
            ['success' => false, 'status' => 0, 'error' => 'missing_key'],
            $client->testApiKey('   ', false)
        );
    }

    public function testAnUnreachableApiIsDistinguishedFromARejectedKey(): void
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('get')->willThrowException(new \RuntimeException('Could not resolve host'));

        $this->assertSame(
            ['success' => false, 'status' => 0, 'error' => 'unreachable'],
            $this->client($curl)->testApiKey(self::API_KEY, false)
        );
    }

    /**
     * The key is tested against the environment that is selected in the form, not the one that
     * was last saved — a sandbox key checked against production reads as "rejected".
     */
    public function testTheProbeHitsTheEnvironmentBeingTested(): void
    {
        $curl = $this->curl(200, '');
        $curl->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith(ConfigRepository::SANDBOX_BASE_URL));

        $this->client($curl)->testApiKey(self::API_KEY, true);
    }

    /**
     * Both API paths have to honour the stand-in, or the E2E suite silently reaches the real
     * api.boxo.nu and the run is measuring BOXO's uptime instead of this module.
     */
    public function testTheOverrideReplacesTheAvailabilityHost(): void
    {
        $curl = $this->curl(200, '{"available":true}');
        $curl->expects($this->once())
            ->method('get')
            ->with('http://127.0.0.1:18092/checkout/service-available/1012AB');

        $this->client($curl, override: 'http://127.0.0.1:18092')
            ->checkServiceAvailable('1012AB');
    }

    /**
     * The override outranks the sandbox toggle: the admin's test button has no business reaching
     * a real host while a stand-in is configured.
     */
    public function testTheOverrideOutranksTheSandboxToggle(): void
    {
        $curl = $this->curl(200, '');
        $curl->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith('http://127.0.0.1:18092/'));

        $this->client($curl, override: 'http://127.0.0.1:18092')->testApiKey(self::API_KEY, true);
    }

    /**
     * Keys reach the debug log on every call, so the masking is what keeps a credential out of a
     * log file a merchant may well email to support.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function maskingProvider(): array
    {
        return [
            'long key keeps its ends' => ['abcd12345678wxyz', 'abcd***wxyz'],
            'short key reveals nothing' => ['abc', '***'],
            'boundary, one under the cutoff' => ['abcdefg', '***'],
        ];
    }

    #[DataProvider('maskingProvider')]
    public function testTheApiKeyIsMaskedInTheDebugLog(string $apiKey, string $expected): void
    {
        $logged = [];
        $log = $this->createMock(LogRepository::class);
        $log->method('addDebugLog')->willReturnCallback(
            static function (string $type, $data) use (&$logged): void {
                if (isset($data['api_key_masked'])) {
                    $logged[] = $data['api_key_masked'];
                }
            }
        );

        $client = new Client($this->config(), $this->factory($this->curl(200, '')), $log);
        $client->testApiKey($apiKey, false);

        $this->assertNotEmpty($logged, 'The request was never logged, so nothing was masked.');
        $this->assertSame([$expected], array_unique($logged));
        $this->assertStringNotContainsString($apiKey, implode('', $logged));
    }

    /**
     * @return Curl&MockObject
     */
    private function curl(int $status, string $body): Curl
    {
        $curl = $this->createMock(Curl::class);
        $curl->method('getStatus')->willReturn($status);
        $curl->method('getBody')->willReturn($body);

        return $curl;
    }

    private function factory(Curl $curl): CurlFactory
    {
        $factory = $this->createMock(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        return $factory;
    }

    private function config(string $override = ''): ConfigRepository
    {
        $config = $this->createMock(ConfigRepository::class);
        $config->method('getApiKey')->willReturn(self::API_KEY);
        $config->method('getApiBaseUrlOverride')->willReturn($override);
        $config->method('getApiBaseUrl')->willReturn(
            $override !== '' ? $override : ConfigRepository::API_BASE_URL
        );

        return $config;
    }

    private function client(Curl $curl, string $override = ''): Client
    {
        return new Client(
            $this->config($override),
            $this->factory($curl),
            $this->createMock(LogRepository::class)
        );
    }
}
