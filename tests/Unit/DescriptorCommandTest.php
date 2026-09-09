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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;
use Uhifadhi\Contracts\Devkit\CommandIo;
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

    /**
     * WHAT THE HANDLER SAYS REACHES THE CONSOLE'S OUTPUT — the point of handing
     * it a channel at all. A handler that wrote to \STDOUT instead would put
     * this text somewhere no tester can see and no flag can govern.
     */
    public function testWhatTheHandlerWritesGoesToTheConsoleOutput(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([]);

        self::assertStringContainsString('made the thing', $tester->getDisplay());
    }

    /**
     * AND `--quiet` SILENCES IT, which is the whole argument for the contract
     * carrying three plain verbs and no verbosity of its own: the adapter writes
     * through the real output, so the flag the person typed is obeyed by a
     * handler that has never heard of it.
     */
    public function testQuietSilencesWhatTheHandlerWrites(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

        self::assertSame('', $tester->getDisplay());
    }

    /**
     * DIAGNOSTICS GO TO THE ERROR STREAM, so a person still reads them when the
     * command's output is being piped somewhere — and so they never land in
     * that pipe.
     */
    public function testDiagnosticsGoToTheErrorStream(): void
    {
        $tester = new CommandTester(self::speaking());
        $tester->execute([], ['capture_stderr_separately' => true]);

        self::assertStringContainsString('could not make the other', $tester->getErrorOutput());
        self::assertStringNotContainsString('could not make the other', $tester->getDisplay());
    }

    /**
     * AND INPUT COMES FROM THE CONSOLE'S STREAM, not from \STDIN — which is what
     * lets a passphrase be piped in, and what lets this assert it at all.
     */
    public function testTheHandlerReadsALineFromTheConsolesInput(): void
    {
        $read = null;
        $command = new DescriptorCommand(new CommandDescriptor(
            'demo:read',
            'Reads one line of input.',
            static function (array $arguments, CommandIo $io) use (&$read): int {
                $read = $io->readLine();

                return 0;
            },
        ));

        $tester = new CommandTester($command);
        $tester->setInputs(['a-piped-passphrase']);
        $tester->execute([]);

        self::assertSame('a-piped-passphrase', $read);
    }

    /**
     * A HANDLER THAT WANTS NO CHANNEL STILL RUNS. The io was added to the
     * descriptor's signature additively: PHP passes the extra argument to a
     * closure that does not declare it, so a provider written against the older
     * one-parameter handler is not broken by this wrapper passing two.
     */
    public function testAHandlerThatDeclaresNoIoStillRuns(): void
    {
        $command = new DescriptorCommand(new CommandDescriptor(
            'demo:oblivious',
            'Ignores the io entirely.',
            static fn (array $arguments): int => 7,
        ));

        self::assertSame(7, new CommandTester($command)->execute([]));
    }

    /** A command that says one thing on each stream. */
    private static function speaking(): DescriptorCommand
    {
        return new DescriptorCommand(new CommandDescriptor(
            'demo:say',
            'Says one thing on each stream.',
            static function (array $arguments, CommandIo $io): int {
                $io->write('made the thing');
                $io->error('could not make the other thing');

                return 0;
            },
        ));
    }
}
