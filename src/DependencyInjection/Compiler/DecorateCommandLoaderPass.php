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

namespace Uhifadhi\Devkit\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Uhifadhi\Devkit\Command\ProviderCommandLoader;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/**
 * Wraps the framework's command loader with {@see ProviderCommandLoader} so the
 * modules' CommandDescriptors become console commands.
 *
 * WHY A HAND-WRITTEN PASS RATHER THAN `->decorate()` IN services.php. The
 * container's `console.command_loader` does not exist during the ordinary
 * decoration phase: symfony/console's ConsoleBundle creates it in
 * AddConsoleCommandPass, which runs at TYPE_BEFORE_REMOVING — after the core
 * DecoratorServicePass has already processed every `->decorate()`. A declared
 * decoration would therefore fail with "a dependency on a non-existent service
 * console.command_loader". So devkit does the decoration by hand, in a pass
 * registered at the SAME phase but a lower priority, so it runs just after the
 * loader has been created (see UhifadhiDevkitBundle::build()).
 *
 * The command providers are gathered here into an already-resolved
 * IteratorArgument rather than a TaggedIteratorArgument: this pass runs after
 * ResolveTaggedIteratorArgumentPass (an optimization pass), so a tag argument
 * added now would never be resolved and the iterator would be silently empty.
 * Building the references from findTaggedServiceIds() sidesteps that; the
 * IteratorArgument still instantiates each provider lazily.
 *
 * Guarded on the loader's presence: an installation without the console (no
 * ConsoleBundle) has no loader to wrap, and there is nothing for devkit's
 * command seam to do there.
 */
final class DecorateCommandLoaderPass implements CompilerPassInterface
{
    private const string LOADER_ID = 'console.command_loader';
    private const string INNER_ID = 'devkit.command_loader.inner';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::LOADER_ID)) {
            return;
        }

        // Move the framework's loader aside under a private id, then rebind the
        // canonical id to devkit's decorator wrapping it. The Application resolves
        // the command loader by the canonical id at runtime, so it gets ours.
        $inner = $container->getDefinition(self::LOADER_ID);
        $container->setDefinition(self::INNER_ID, $inner);

        $providers = [];
        foreach (array_keys($container->findTaggedServiceIds(UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG)) as $id) {
            $providers[] = new Reference($id);
        }

        $decorator = new Definition(ProviderCommandLoader::class, [
            new Reference(self::INNER_ID),
            new IteratorArgument($providers),
        ]);
        $decorator->setPublic($inner->isPublic());

        $container->setDefinition(self::LOADER_ID, $decorator);
    }
}
