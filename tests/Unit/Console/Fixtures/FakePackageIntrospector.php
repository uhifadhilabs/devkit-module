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

namespace Uhifadhi\Devkit\Tests\Unit\Console\Fixtures;

use Uhifadhi\Devkit\Console\Package\PackageIntrospector;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * A {@see PackageIntrospector} the unit tests drive — so the registry, the doctor
 * and the command inventory can be shown a fleet the test composes (a module
 * behind the core, one carrying a dev-main marker, two modules that ship the same
 * fixture class) without a fixture package on disk for every case.
 *
 * Instances are placed by object identity (so two providers of the same class can
 * belong to different modules); classes by name (for the wiring inspector, which
 * knows contributors by class). The fleet, the versions and the requirement maps
 * are handed in whole.
 */
final class FakePackageIntrospector implements PackageIntrospector
{
    /** @var \SplObjectStorage<object, ResolvedPackage> */
    private \SplObjectStorage $owners;

    /**
     * @param list<ResolvedPackage>                $fleet         the installed fleet, in order
     * @param array<class-string, ResolvedPackage> $ownersByClass class => the package that ships it
     * @param array<string, array<string, string>> $requirements  package name => its require map
     */
    public function __construct(
        private readonly array $fleet = [],
        private readonly array $ownersByClass = [],
        private readonly array $requirements = [],
    ) {
        $this->owners = new \SplObjectStorage();
    }

    public function place(object $service, ResolvedPackage $package): void
    {
        $this->owners[$service] = $package;
    }

    public function ownerOf(object $service): ?ResolvedPackage
    {
        if ($this->owners->offsetExists($service)) {
            return $this->owners[$service];
        }

        return $this->ownersByClass[$service::class] ?? null;
    }

    public function ownerOfClass(string $class): ?ResolvedPackage
    {
        return $this->ownersByClass[$class] ?? null;
    }

    public function package(string $name): ?ResolvedPackage
    {
        foreach ($this->fleet as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }

    public function fleet(): array
    {
        return $this->fleet;
    }

    public function requirements(string $name): array
    {
        return $this->requirements[$name] ?? [];
    }
}
