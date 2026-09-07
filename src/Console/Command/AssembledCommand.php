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

namespace Uhifadhi\Devkit\Console\Command;

/**
 * One line in the assembled command list: an identifier, what kind of
 * contribution it is, and a sentence.
 *
 * The identifier is the descriptor's console name for a command
 * ({@see CommandKind::Command}), and the content provider's key for a
 * demo-content step ({@see CommandKind::DemoContent}) — a content provider ships
 * no command name, because it is not a command, and the console shows the honest
 * key rather than inventing one.
 *
 * ON `deprecated`: it is always false in v1, and that is a FLAGGED GAP, not a
 * finding. Neither devkit contract — {@see \Uhifadhi\ModuleContracts\Devkit\CommandDescriptor}
 * nor {@see \Uhifadhi\ModuleContracts\Devkit\ContentProviderInterface} — carries
 * a deprecation signal, so devkit has nothing to read. Marking a dying command
 * needs a `deprecated` flag added to the descriptor contract (an owner decision),
 * and until then the console cannot mark one without guessing from its name.
 */
final readonly class AssembledCommand
{
    public function __construct(
        public string $name,
        public CommandKind $kind,
        public string $description,
        public bool $deprecated = false,
    ) {
    }
}
