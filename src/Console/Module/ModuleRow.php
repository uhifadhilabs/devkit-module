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

namespace Uhifadhi\Devkit\Console\Module;

use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * One row of the module registry — a package as the fleet register presents it.
 *
 * `contractsConstraint` is the version constraint the package declares on
 * `uhifadhi/module-contracts`, or null when it declares none (the contract
 * itself, and any infrastructure that does not pin the seam). `permissions` and
 * `routes` are counted only where a module provider was found for the package —
 * infrastructure contributes neither through the module seam.
 */
final readonly class ModuleRow
{
    public function __construct(
        public ResolvedPackage $package,
        public ?string $contractsConstraint,
        public CoreState $coreState,
        public int $permissions,
        public int $routes,
        public ModuleReach $reach,
    ) {
    }
}
