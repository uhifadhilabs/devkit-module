<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Devkit Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Devkit\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\Console\Package\ComposerPackageIntrospector;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * The production introspector, read against the very installation this suite runs
 * in — no fixtures, because Composer's own metadata is the thing under test.
 */
final class ComposerPackageIntrospectorTest extends TestCase
{
    public function testItPlacesAnObjectInThePackageThatShippedIt(): void
    {
        $owner = new ComposerPackageIntrospector()->ownerOf($this);

        self::assertNotNull($owner, 'This test class ships in the devkit package.');
        self::assertSame('uhifadhi/devkit-module', $owner->name);
        self::assertSame('devkit', $owner->shortName);
    }

    public function testItPlacesAClassTheSameWay(): void
    {
        $owner = new ComposerPackageIntrospector()->ownerOfClass(ResolvedPackage::class);

        self::assertNotNull($owner);
        self::assertSame('uhifadhi/devkit-module', $owner->name);
    }

    public function testTheFleetIsComposersUhifadhiPackages(): void
    {
        $names = array_map(
            static fn (ResolvedPackage $p): string => $p->name,
            new ComposerPackageIntrospector()->fleet(),
        );

        self::assertContains('uhifadhi/module-contracts', $names);
        self::assertContains('uhifadhi/shell-module', $names);
        self::assertContains('uhifadhi/seam-module', $names);
        foreach ($names as $name) {
            self::assertStringStartsWith('uhifadhi/', $name);
        }
    }

    public function testItReadsAPackagesDeclaredRequirements(): void
    {
        $requirements = new ComposerPackageIntrospector()->requirements('uhifadhi/devkit-module');

        self::assertArrayHasKey('uhifadhi/module-contracts', $requirements, 'devkit pins the contracts, and the introspector reads it from composer.json.');
        self::assertArrayHasKey('uhifadhi/shell-module', $requirements);
    }

    public function testAnUnknownPackageIsNull(): void
    {
        self::assertNull(new ComposerPackageIntrospector()->package('uhifadhi/not-installed-module'));
        self::assertSame([], new ComposerPackageIntrospector()->requirements('uhifadhi/not-installed-module'));
    }
}
