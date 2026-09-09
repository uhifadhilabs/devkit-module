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

namespace Uhifadhi\Devkit\Tests\Fixtures;

use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandProviderInterface;

/**
 * A command provider standing in for the inert ones real modules ship: it
 * returns the descriptors handed to its constructor.
 */
final class FakeCommandProvider implements CommandProviderInterface
{
    /**
     * @param list<CommandDescriptor> $descriptors
     */
    public function __construct(private readonly array $descriptors)
    {
    }

    public function commands(): array
    {
        return $this->descriptors;
    }
}
