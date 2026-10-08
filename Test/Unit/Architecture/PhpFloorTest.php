<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Test\Unit\Architecture;

use Magmodules\Boxo\Service\Test\PhpVersion;
use PHPUnit\Framework\TestCase;

/**
 * The supported PHP floor is stated in three places, and they have to agree.
 *
 * `composer.json` decides who can install the module, `PhpVersion::EXPECTED` decides what the
 * selftest tells a merchant, and `phpstan.neon` decides which language features analysis accepts.
 * Drift between them is silent and lands on the merchant: raise the composer constraint alone and
 * a shop on the old version gets a green selftest beside a module that cannot run; raise the
 * selftest alone and analysis keeps waving through syntax the oldest supported leg rejects.
 */
class PhpFloorTest extends TestCase
{
    /**
     * Bump all three together, or not at all.
     */
    private const FLOOR = '8.1';

    public function testTheSelftestReportsTheSupportedFloor(): void
    {
        $this->assertSame(
            self::FLOOR,
            PhpVersion::EXPECTED,
            'The selftest would tell merchants a different minimum than the module supports.'
        );
    }

    public function testComposerRequiresTheSupportedFloor(): void
    {
        $composer = json_decode((string)file_get_contents($this->moduleDir() . '/composer.json'), true);
        $constraint = $composer['require']['php'] ?? '';

        $this->assertNotSame('', $constraint, 'composer.json states no PHP constraint at all.');
        $this->assertStringContainsString(
            self::FLOOR,
            $constraint,
            sprintf('composer.json requires "%s", which does not name the %s floor.', $constraint, self::FLOOR)
        );
    }

    /**
     * PHPStan analyses against the floor rather than against whatever PHP the container runs, so
     * an 8.2+ construct fails here instead of only on the oldest matrix leg.
     */
    public function testPhpstanAnalysesAgainstTheFloor(): void
    {
        $neon = (string)file_get_contents($this->moduleDir() . '/phpstan.neon');

        $this->assertMatchesRegularExpression(
            '/^\s*phpVersion:\s*' . str_replace('.', '0', self::FLOOR) . '00\s*$/m',
            $neon,
            'phpstan.neon does not pin phpVersion to the supported floor.'
        );
    }

    /**
     * Nothing may claim support for a version the floor excludes.
     */
    public function testNoSupportForPhpBelowTheFloor(): void
    {
        $offenders = [];

        foreach (['composer.json', 'phpstan.neon', '.github/workflows/linting.yml'] as $file) {
            $contents = (string)file_get_contents($this->moduleDir() . '/' . $file);
            foreach (['7.4', '8.0'] as $dropped) {
                if (str_contains($contents, $dropped)) {
                    $offenders[] = sprintf('%s mentions PHP %s', $file, $dropped);
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    private function moduleDir(): string
    {
        return dirname(__DIR__, 3);
    }
}
