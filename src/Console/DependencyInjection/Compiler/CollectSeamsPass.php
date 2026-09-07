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

namespace Uhifadhi\Devkit\Console\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Uhifadhi\Devkit\Console\Wiring\SeamInspector;

/**
 * READS THE TAGGED-SERVICE MAP AT COMPILE TIME and hands it to the seam inspector
 * as data.
 *
 * Which services carry a seam's tag is a compile-time fact — `findTaggedServiceIds`
 * is the only place it can be read, and a service cannot be given a live list of
 * "every class tagged X" the way it can be given a tagged_iterator of instances,
 * because the Wiring surface wants the CLASSES that registered, not the objects
 * (it never builds them, and some — a map layer, a KPI provider — would drag half
 * the platform into a dev page if it did). So the pass collects the class names
 * for every known seam and replaces the inspector's first argument with the map.
 *
 * It runs at TYPE_BEFORE_REMOVING so every tag — including the ones devkit adds
 * by registerForAutoconfiguration — has settled and none has been optimised away.
 */
final class CollectSeamsPass implements CompilerPassInterface
{
    private const string INSPECTOR_ID = 'devkit.console.seam_inspector';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::INSPECTOR_ID)) {
            return;
        }

        $collected = [];
        foreach (array_keys(SeamInspector::KNOWN_SEAMS) as $tag) {
            $classes = [];
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $id) {
                $class = $this->classOf($container, $id);
                if (null !== $class) {
                    $classes[] = $class;
                }
            }
            $collected[$tag] = $classes;
        }

        $container->getDefinition(self::INSPECTOR_ID)->replaceArgument(0, $collected);
    }

    /**
     * The concrete class a service id resolves to — following one alias, and
     * falling back to the id itself when it names a class (the common
     * one-class-one-service case).
     */
    private function classOf(ContainerBuilder $container, string $id): ?string
    {
        if ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        if (!$container->hasDefinition($id)) {
            return class_exists($id) || interface_exists($id) ? $id : null;
        }

        $class = $container->getDefinition($id)->getClass();
        if (null !== $class) {
            return $class;
        }

        return class_exists($id) || interface_exists($id) ? $id : null;
    }
}
