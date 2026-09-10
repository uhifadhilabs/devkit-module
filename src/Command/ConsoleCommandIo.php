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

use Symfony\Component\Console\Exception\MissingInputException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
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
 * A SECRET IS THE ONE READ THAT DOES USE THE HELPER, because switching a
 * terminal's echo off is exactly the machinery this class exists to reach:
 *
 *     $question = new Question('What is the database password?');
 *     $question->setHidden(true);
 *     $question->setHiddenFallback(false);
 *
 * — https://symfony.com/doc/current/components/console/helpers/questionhelper.html,
 * whose rule for the second line is: "Symfony will use either a binary, change
 * stty mode or use another trick to hide the response. If none is available, it
 * will fallback and allow the response to be visible unless you set this
 * behavior to false… In this case, a RuntimeException would be thrown."
 *
 * THE FALLBACK IS LEFT ON — the documented default, and not the page's example.
 * A refusal is the wrong end of that trade here: the command this serves makes
 * the one account an installation cannot make through a screen, and somebody on
 * a terminal that cannot hide input is better served by a passphrase they can
 * see than by an installation they cannot finish. What they are not left with
 * is a surprise, so the fallback says on the ERROR stream that the typing will
 * show.
 *
 * NOTHING OF THE ASKING TOUCHES STANDARD OUTPUT. QuestionHelper::ask() writes
 * the prompt through the output it is handed (QuestionHelper.php, line 122,
 * writePrompt()), so it is handed the error output — the same stream error()
 * uses, and for the same reason: a caller piping this command's result must not
 * collect a passphrase prompt along with it.
 *
 * NULL IS EVERYTHING THAT IS NOT A SECRET. An empty answer becomes the
 * question's default (QuestionHelper.php, line 168:
 * `$ret = \strlen($ret) > 0 ? $ret : $question->getDefault();`), a closed stream
 * reaches the same place through the MissingInputException ask() catches, and a
 * `--no-interaction` run never reads at all (line 64). The default is left null
 * so all three answer null, which is what the contract promises: with the echo
 * off, nothing typed and nothing to type look alike.
 *
 * @see https://symfony.com/doc/current/console.html#console-input
 * @see https://symfony.com/doc/current/components/console/helpers/questionhelper.html
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
        $this->diagnostics()->writeln($line);
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

    public function readSecret(): ?string
    {
        $helper = new QuestionHelper();
        $diagnostics = $this->diagnostics();

        try {
            return self::answer($helper->ask($this->input, $diagnostics, self::hidden(true)));
        } catch (MissingInputException) {
            // Nothing typed and nothing left to type. With the echo off the two
            // are one answer, which the contract spells null.
            return null;
        } catch (RuntimeException) {
            // 'Unable to hide the response.' (QuestionHelper.php, line 463),
            // reachable only because the fallback is off above: no stty, or a
            // terminal that will not give its settings up. The documented
            // fallback is to ask again in the open — done here rather than
            // inside the helper so that the person is told first.
            $diagnostics->writeln('This terminal cannot hide what is typed; the next answer will be shown as you type it.');
        }

        try {
            return self::answer($helper->ask($this->input, $diagnostics, self::hidden(false)));
        } catch (MissingInputException) {
            return null;
        }
    }

    /**
     * The question a secret is asked with, hidden unless hiding has already
     * proved impossible.
     *
     * THE FALLBACK IS TURNED OFF SO IT CAN BE ANNOUNCED. Left on — the
     * documented default — the helper silently asks in the open, and somebody
     * types a passphrase onto a screen expecting it not to appear. Off, the
     * same case arrives here as a RuntimeException, which is the documented
     * consequence: "In this case, a RuntimeException would be thrown."
     *
     * The question text is empty because the handler wrote its own prompt
     * through error() before asking; the contract's readSecret() carries no
     * prompt of its own, exactly as readLine() carries none.
     */
    private static function hidden(bool $hidden): Question
    {
        return new Question('')
            ->setHidden($hidden)
            ->setHiddenFallback(false);
    }

    /** What was typed, or null for anything that is not a secret. */
    private static function answer(mixed $given): ?string
    {
        return \is_string($given) && '' !== $given ? $given : null;
    }

    /**
     * The stream a refusal, a question and a warning all belong on: a person
     * still reads it when the command's output is being piped somewhere, and it
     * never lands in that pipe. A console run gives a
     * {@see ConsoleOutputInterface} whose getErrorOutput() is stderr; a
     * BufferedOutput in a test is not one, and there the two streams collapse
     * into the single output that exists, which is what Symfony's own
     * `SymfonyStyle` settles for.
     */
    private function diagnostics(): OutputInterface
    {
        return $this->output instanceof ConsoleOutputInterface
            ? $this->output->getErrorOutput()
            : $this->output;
    }
}
