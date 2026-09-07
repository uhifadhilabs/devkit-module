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

namespace Uhifadhi\Devkit;

use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Uhifadhi\Devkit\DependencyInjection\Compiler\DecorateCommandLoaderPass;
use Uhifadhi\ModuleContracts\Devkit\CommandProviderInterface;
use Uhifadhi\ModuleContracts\Devkit\ContentProviderInterface;

/**
 * Devkit — THE dev-only module, and a collector.
 *
 * It installs through require-dev, so it and everything it registers are absent
 * from a production build: require-dev IS the production firewall. Its job is to
 * gather the INERT provider classes other modules ship — declared through the
 * two contracts in uhifadhi/module-contracts, which live there precisely so an
 * always-installed module can name them even when devkit is not present — and
 * materialise them into things a developer can run:
 *
 *   - every {@see ContentProviderInterface} becomes a step of `fixtures:demo`,
 *     seeded in dependsOn() topological order;
 *   - every {@see CommandProviderInterface}'s descriptors become real console
 *     commands.
 *
 * Both only ever exist where devkit is installed, which is only ever dev and CI.
 *
 * Explicit wiring, no autowire/autoconfigure — the reusable-bundle rule (see
 * config/services.php). Modules tag their inert providers with the tag STRINGS
 * below, written as literals in the module's own service config: a module cannot
 * reference these constants, because devkit is absent from the production build
 * the module also ships into. The constants exist for devkit's own wiring and
 * for tests.
 */
final class UhifadhiDevkitBundle extends AbstractBundle
{
    /**
     * Tag for {@see ContentProviderInterface} services. `fixtures:demo` collects
     * everything carrying it. Modules add it as a literal string.
     */
    public const string CONTENT_PROVIDER_TAG = 'uhifadhi.devkit.content_provider';

    /**
     * Tag for {@see CommandProviderInterface} services. The command loader turns
     * each collected provider's descriptors into console commands. Modules add it
     * as a literal string.
     */
    public const string COMMAND_PROVIDER_TAG = 'uhifadhi.devkit.command_provider';

    protected string $extensionAlias = 'devkit';

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // The collector has no host-facing configuration in this slice; the
        // static wiring is all there is. (The dev-console UI, a later slice,
        // is where configuration first appears.)
        $container->import('../config/services.php');
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /*
         * A COURTESY for hosts and modules that DO autoconfigure: an application
         * whose services default to autoconfigure:true gets its providers tagged
         * just by implementing the interface. Modules shipped as reusable bundles
         * are NOT autoconfigured (config/services.php explains why) and still tag
         * their providers by hand with the literal tag strings above — but a
         * host's own app-level provider need not.
         */
        $container->registerForAutoconfiguration(ContentProviderInterface::class)
            ->addTag(self::CONTENT_PROVIDER_TAG);
        $container->registerForAutoconfiguration(CommandProviderInterface::class)
            ->addTag(self::COMMAND_PROVIDER_TAG);

        /*
         * Wrap the framework's command loader so the modules' CommandDescriptors
         * become console commands. It runs at TYPE_BEFORE_REMOVING with a lower
         * priority than symfony/console's AddConsoleCommandPass (default 0), so
         * it runs just AFTER the loader it decorates has been created — the loader
         * does not exist during the ordinary decoration phase, which is why this
         * is a hand-written pass (see DecorateCommandLoaderPass).
         */
        $container->addCompilerPass(new DecorateCommandLoaderPass(), PassConfig::TYPE_BEFORE_REMOVING, -16);
    }
}
