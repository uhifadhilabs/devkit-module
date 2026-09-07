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
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;

final class ResolvedPackageTest extends TestCase
{
    public function testItCallsAModuleByItsSlug(): void
    {
        self::assertSame('patrol', ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2')->shortName);
        self::assertSame('shell', ResolvedPackage::of('uhifadhi/shell-module', '0.8.0')->shortName);
    }

    public function testTheSeamKeepsItsOwnName(): void
    {
        self::assertSame(
            'module-contracts',
            ResolvedPackage::of('uhifadhi/module-contracts', 'v0.5.1')->shortName,
            'The contract is not a module and is not called one.',
        );
    }
}
