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

        self::assertContains('uhifadhi/uhifadhi', $names);
        self::assertContains('uhifadhi/devkit-module', $names);
        foreach ($names as $name) {
            self::assertStringStartsWith('uhifadhi/', $name);
        }
    }

    /**
     * A package may hold the names of the packages it could be split into, and
     * Composer reports every one of them as installed — with no version and no
     * directory, because nothing was ever downloaded under that name. The fleet
     * is what is on disk, so those names are not in it: a console that listed
     * them would show a reader six packages they cannot open, cannot version and
     * did not install.
     */
    public function testTheFleetLeavesOutTheNamesAPackageStandsInFor(): void
    {
        $names = array_map(
            static fn (ResolvedPackage $p): string => $p->name,
            new ComposerPackageIntrospector()->fleet(),
        );

        // Names the core answers to without ever being installed under them.
        self::assertNotContains('uhifadhi/registry-bundle', $names);
        self::assertNotContains('uhifadhi/shell-bundle', $names);
        self::assertNotContains('uhifadhi/contracts', $names);
    }

    public function testItReadsAPackagesDeclaredRequirements(): void
    {
        $requirements = new ComposerPackageIntrospector()->requirements('uhifadhi/devkit-module');

        self::assertArrayHasKey('uhifadhi/uhifadhi', $requirements, 'devkit pins the core, and the introspector reads it from composer.json.');
    }

    public function testAnUnknownPackageIsNull(): void
    {
        self::assertNull(new ComposerPackageIntrospector()->package('uhifadhi/not-installed-module'));
        self::assertSame([], new ComposerPackageIntrospector()->requirements('uhifadhi/not-installed-module'));
    }
}
