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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;

/**
 * A real Symfony console command wrapping ONE {@see CommandDescriptor}.
 *
 * This is devkit's half of the CommandProviderInterface contract. A module ships an
 * inert provider returning descriptors — a name, a help line, and a
 * `\Closure(list<string>): int` — and refuses to name symfony/console so the
 * contract stays framework-free and nothing builds a Command in a production
 * container that has no devkit to run it. devkit, which legitimately requires
 * symfony/console, is where that descriptor finally becomes a Command: this
 * class.
 *
 * THE HANDLER IS THE PROCESS CONTRACT, NOT THE CONSOLE ONE. The descriptor's
 * closure takes the argument tail (everything the person typed after the command
 * name, as a list<string>) and a CommandIo to speak through, and returns a POSIX
 * exit code. So the wrapper defines exactly one thing — a variadic argument that
 * collects that tail — hands it over with the io, and uses the returned int as
 * its own exit status. It does not model options: a command that wants richer
 * input parses the tail itself, or reaches through the service the closure
 * closes over.
 *
 * THE IO IS WHERE THIS COMMAND EARNS ITS KEEP. A handler holds no console, so
 * without a channel its only way to say what it did is \STDOUT — output that no
 * `--quiet` can silence and no tester can capture. {@see ConsoleCommandIo} binds
 * the contract's three verbs to the very input and output this execution was
 * given, so a module's messages obey the flags the person actually typed.
 */
final class DescriptorCommand extends Command
{
    public function __construct(private readonly CommandDescriptor $descriptor)
    {
        parent::__construct($descriptor->name);
    }

    protected function configure(): void
    {
        // Core's own HelpCommand::configure() opens the same way
        // (vendor/symfony/console/Command/HelpCommand.php, line 32) and for the
        // same reason: a command whose arguments are not known until runtime
        // cannot let the binder reject them. Command::run() catches the binding
        // exception when this is set (Command.php, lines 236-243) and carries on
        // to execute().
        $this->ignoreValidationErrors();

        $this
            ->setDescription($this->descriptor->description)
            ->addArgument(
                'arguments',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'The argument tail passed straight through to the command handler.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return ($this->descriptor->handler)(
            self::tail($input),
            new ConsoleCommandIo($input, $output),
        );
    }

    /**
     * EXACTLY WHAT THE PERSON TYPED AFTER THE COMMAND NAME — options included,
     * in the order they wrote them.
     *
     * The bound `arguments` argument cannot answer this. Binding is where the
     * console decides that `--password=x` is an option, and an option this
     * command deliberately never declared is one the binder refuses outright —
     * so until the raw tokens were forwarded, every descriptor taking an option
     * died on `The "--password" option does not exist.` with its handler never
     * reached. ignoreValidationErrors() stops the refusal, but it does not put
     * the token back into the argument: the parse that would have placed it
     * there is the parse that threw.
     *
     * ArgvInput::getRawTokens(true) is the sanctioned answer — added in Symfony
     * 7.1 for exactly this, handing a command line to something that will parse
     * it itself. `true` strips everything up to and including the first
     * argument, which is the command name, so what remains is the tail and never
     * the application's own options (ArgvInput.php, lines 361-381).
     *
     * An input that is not an ArgvInput has no raw tokens to give — the
     * ArrayInput a CommandTester builds, say — and there the bound argument is
     * both available and right, because nothing was typed to be misread.
     *
     * @return list<string>
     */
    private static function tail(InputInterface $input): array
    {
        if ($input instanceof ArgvInput) {
            return $input->getRawTokens(true);
        }

        $arguments = $input->hasArgument('arguments') ? $input->getArgument('arguments') : [];
        if (!\is_array($arguments)) {
            return [];
        }

        $tail = [];
        foreach ($arguments as $argument) {
            if (\is_string($argument)) {
                $tail[] = $argument;
            }
        }

        return $tail;
    }
}
