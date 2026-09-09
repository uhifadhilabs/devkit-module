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

namespace Uhifadhi\Devkit\Console\Wiring;

use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * One service registered on a contribution point — the class that was collected, and the module
 * that shipped it.
 *
 * The package is null when the contributor belongs to no placeable package (an
 * app-level service a host tagged itself); the inspector then shows the bare
 * class, because a contributor with no provenance is still a contributor.
 */
final readonly class Contributor
{
    public function __construct(
        public string $class,
        public ?ResolvedPackage $package,
    ) {
    }

    /** The class without its namespace — what the inspector prints. */
    public function shortClass(): string
    {
        $class = $this->class;

        return str_contains($class, '\\') ? substr($class, strrpos($class, '\\') + 1) : $class;
    }
}
