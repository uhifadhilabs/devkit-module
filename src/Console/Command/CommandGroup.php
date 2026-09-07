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

namespace Uhifadhi\Devkit\Console\Command;

use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * The assembled commands one module contributed, under that module's name.
 *
 * The package is null only when devkit cannot place the contributing service in
 * an installed package — an app-level provider a host wrote itself. Everything a
 * module ships is placed, so the grid heads each group with the module's package
 * and version.
 */
final readonly class CommandGroup
{
    /**
     * @param list<AssembledCommand> $commands
     */
    public function __construct(
        public string $label,
        public ?ResolvedPackage $package,
        public array $commands,
    ) {
    }

    public function count(): int
    {
        return \count($this->commands);
    }
}
