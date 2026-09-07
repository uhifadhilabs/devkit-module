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

namespace Uhifadhi\Devkit\Tests\Integration\Fixtures;

use Uhifadhi\ModuleContracts\Devkit\CommandDescriptor;
use Uhifadhi\ModuleContracts\Devkit\CommandProviderInterface;

/**
 * A container-managed command provider whose one descriptor records the tail it
 * was run with, so a boot test can prove a descriptor became a real console
 * command the application can find and run.
 */
final class RecordingCommandProvider implements CommandProviderInterface
{
    /** @var list<string>|null the tail the command was last run with */
    public static ?array $ranWith = null;

    public static function reset(): void
    {
        self::$ranWith = null;
    }

    public function commands(): array
    {
        return [
            new CommandDescriptor(
                'devkit:test:echo',
                'Records its argument tail (test fixture).',
                static function (array $arguments): int {
                    self::$ranWith = $arguments;

                    return 7;
                },
            ),
        ];
    }
}
