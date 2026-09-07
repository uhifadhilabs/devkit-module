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

namespace Uhifadhi\Devkit\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\DependencyInjection\AddConsoleCommandPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingCommandProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;

/**
 * The smallest installation devkit lives in: FrameworkBundle (for the console
 * machinery devkit's collector plugs into) and devkit itself. No database — the
 * collector owns no entities and seeds nothing of its own; what it seeds is
 * whatever the modules' providers do.
 *
 * The kernel plays the role of the ALWAYS-INSTALLED MODULES: it registers a few
 * fixture providers tagged with devkit's tag strings BY HAND, exactly as a
 * reusable module bundle tags its own inert providers (a reusable bundle is not
 * autoconfigured). devkit then collects them just as it would in a real dev
 * install.
 */
final class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new UhifadhiDevkitBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'test' => true,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
            ]);

            // The always-installed modules' inert content providers, tagged by
            // hand. Declared in an order that is NOT already a valid seed order
            // (incident before its dependencies), so the boot test proves devkit
            // topologically sorts them rather than running them as listed.
            foreach ([
                'devkit.test.content.incident' => ['incident', ['area', 'patrol']],
                'devkit.test.content.patrol' => ['patrol', ['area']],
                'devkit.test.content.area' => ['area', []],
            ] as $id => [$key, $dependsOn]) {
                $container->register($id, RecordingContentProvider::class)
                    ->setArguments([$key, $dependsOn])
                    ->addTag(UhifadhiDevkitBundle::CONTENT_PROVIDER_TAG);
            }

            // An always-installed module's inert command provider, tagged by hand.
            $container->register('devkit.test.command_provider', RecordingCommandProvider::class)
                ->addTag(UhifadhiDevkitBundle::COMMAND_PROVIDER_TAG);
        });
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // The command loader devkit's DecorateCommandLoaderPass wraps is created
        // by symfony/console's AddConsoleCommandPass — registered here, at the
        // same phase and default priority a real installation's ConsoleBundle
        // registers it, so devkit's pass (priority -16) still runs just after it.
        $container->addCompilerPass(new AddConsoleCommandPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/devkit-module-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/devkit-module-tests/log';
    }
}
