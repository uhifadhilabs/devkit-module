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

namespace Uhifadhi\Devkit\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Devkit\Command\DescriptorCommand;

/**
 * A CommandDescriptor becomes a runnable console command: its name and help are
 * the command's, the argument tail reaches the handler as a list<string>, and
 * the handler's return value is the command's exit code.
 */
final class DescriptorCommandTest extends TestCase
{
    public function testItTakesItsNameAndDescriptionFromTheDescriptor(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'patrol:demo:reset',
            'Wipe and reseed the patrol demo content.',
            static fn (array $arguments): int => 0,
        ));

        self::assertSame('patrol:demo:reset', $command->getName());
        self::assertSame('Wipe and reseed the patrol demo content.', $command->getDescription());
    }

    public function testItReturnsTheHandlersExitCode(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'demo:fail',
            'Always fails.',
            static fn (array $arguments): int => 42,
        ));

        $tester = new CommandTester($command);

        self::assertSame(42, $tester->execute([]));
    }

    public function testItPassesTheArgumentTailToTheHandler(): void
    {
        $received = null;
        $command = new DescriptorCommand(new CommandDescriptor(
            'demo:echo',
            'Captures its argument tail.',
            static function (array $arguments) use (&$received): int {
                $received = $arguments;

                return 0;
            },
        ));

        $tester = new CommandTester($command);
        $tester->execute(['arguments' => ['alpha', 'beta']]);

        self::assertSame(['alpha', 'beta'], $received);
    }
}
