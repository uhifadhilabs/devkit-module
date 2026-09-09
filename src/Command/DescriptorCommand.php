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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Uhifadhi\Contracts\Devkit\CommandDescriptor;

/**
 * A real Symfony console command wrapping ONE {@see CommandDescriptor}.
 *
 * This is devkit's half of the CommandProviderInterface seam. A module ships an
 * inert provider returning descriptors — a name, a help line, and a
 * `\Closure(list<string>): int` — and refuses to name symfony/console so the
 * contract stays framework-free and nothing builds a Command in a production
 * container that has no devkit to run it. devkit, which legitimately requires
 * symfony/console, is where that descriptor finally becomes a Command: this
 * class.
 *
 * THE HANDLER IS THE PROCESS CONTRACT, NOT THE CONSOLE ONE. The descriptor's
 * closure takes the argument tail (everything the person typed after the command
 * name, as a list<string>) and returns a POSIX exit code. So the wrapper defines
 * exactly one thing — a variadic argument that collects that tail — hands it to
 * the closure, and uses the returned int as its own exit status. It does not
 * model options: a command that wants richer input parses the tail itself, or
 * reaches through the service the closure closes over.
 */
final class DescriptorCommand extends Command
{
    public function __construct(private readonly CommandDescriptor $descriptor)
    {
        parent::__construct($descriptor->name);
    }

    protected function configure(): void
    {
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
        /** @var list<string> $arguments */
        $arguments = $input->getArgument('arguments');

        return ($this->descriptor->handler)($arguments);
    }
}
