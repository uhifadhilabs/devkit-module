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

namespace Uhifadhi\Devkit\Console\Doctor;

use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

/**
 * One module's row in the compatibility matrix: the module, and its state under
 * every check.
 */
final readonly class MatrixRow
{
    /**
     * @param array<string, CheckState> $cells check value => state
     */
    public function __construct(
        public ResolvedPackage $package,
        public array $cells,
    ) {
    }

    public function cell(ConformanceCheck $check): CheckState
    {
        return $this->cells[$check->value] ?? CheckState::NotApplicable;
    }
}
