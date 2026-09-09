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

namespace Uhifadhi\Devkit\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Uhifadhi\Contracts\Devkit\CommandIo;

/**
 * THE CONSOLE, ON THE OTHER SIDE OF THE CONTRACT — where a module's three
 * framework-free verbs finally become a real Symfony input and output.
 *
 * This is the same trade as {@see DescriptorCommand} one level down. A module
 * ships a handler that says what it did through {@see CommandIo} and names no
 * console, because naming one would put symfony/console in the contracts
 * package and build console objects in a production container that has no
 * devkit. devkit is the package that legitimately requires symfony/console, so
 * devkit is where the translation lives: this class, constructed per execution
 * from the input and output the console handed the command.
 *
 * WRITING GOES THROUGH THE REAL OUTPUT, and that is what makes the contract's
 * silence about verbosity correct rather than lossy. `writeln()` consults the
 * output's verbosity itself, so a line a handler writes is suppressed under
 * `--quiet` and captured by a BufferedOutput without the handler knowing either
 * exists. A handler that wrote to \STDOUT instead — which is all it could do
 * before it was handed this — escaped both.
 *
 * DIAGNOSTICS GO TO THE ERROR STREAM WHEN THERE IS ONE. A console run gives a
 * {@see ConsoleOutputInterface}, whose getErrorOutput() is stderr; a
 * BufferedOutput in a test is not one, and there the two streams collapse into
 * the single output that exists, which is the same thing Symfony's own
 * `SymfonyStyle` settles for.
 *
 * READING FOLLOWS THE QUESTION HELPER'S OWN CHOICE OF STREAM:
 *
 *     $inputStream = $input instanceof StreamableInputInterface ? $input->getStream() : null;
 *     $inputStream ??= \STDIN;
 *
 * — vendor/symfony/console/Helper/QuestionHelper.php, lines 68-69. Taking the
 * stream from the input rather than reaching for \STDIN is what lets a
 * CommandTester feed a passphrase in with setInputs(), and it is the whole
 * reason readLine() is on the contract at all. The helper itself is not used:
 * it reads a character at a time to support hidden input, autocompletion and
 * timeouts, and asks its question through the output — none of which a piped
 * passphrase wants. One fgets is the whole of it.
 *
 * @see https://symfony.com/doc/current/console.html#console-input
 */
final class ConsoleCommandIo implements CommandIo
{
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
    ) {
    }

    public function write(string $line): void
    {
        $this->output->writeln($line);
    }

    public function error(string $line): void
    {
        $stream = $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;

        $stream->writeln($line);
    }

    public function readLine(): ?string
    {
        // QuestionHelper::ask(), lines 68-69: the input's own stream when it has
        // one — which is what a CommandTester's setInputs() sets — and the
        // process's standard input otherwise.
        $stream = $this->input instanceof StreamableInputInterface ? $this->input->getStream() : null;
        $stream ??= \STDIN;

        $line = fgets($stream);

        return \is_string($line) ? rtrim($line, "\r\n") : null;
    }
}
