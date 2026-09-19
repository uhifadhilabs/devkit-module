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

namespace Uhifadhi\Devkit\Tests\Integration\Installation;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Uhifadhi\Bundle\AreaBundle\AreaBundle;
use Uhifadhi\Bundle\AtlasBundle\AtlasBundle;
use Uhifadhi\Bundle\RegistryBundle\RegistryBundle;
use Uhifadhi\Bundle\ShellBundle\ShellBundle;
use Uhifadhi\Bundle\TeamBundle\Entity\User;
use Uhifadhi\Bundle\TeamBundle\Security\ApiTokenAuthenticator;
use Uhifadhi\Bundle\TeamBundle\TeamBundle;
use Uhifadhi\Devkit\UhifadhiDevkitBundle;
use UtafitiLabs\PostGISBundle\UtafitiLabsPostGISBundle;

/**
 * A REAL INSTALLATION WITH DEVKIT IN IT — the whole core, a real database, and
 * the collector, so what this suite asks is what a developer asks on their own
 * machine.
 *
 * The other kernels in this suite are deliberately small: one boots the
 * collector on FrameworkBundle alone with hand-tagged fixture providers, the
 * other boots the dev console's frame without a database. Both prove devkit
 * collects whatever is tagged. Neither proves the thing devkit exists for —
 * that what the core actually ships turns into content somebody can look at
 * and a console a developer can work through — because in both, the providers are
 * this suite's own and the assertions can only be about themselves.
 *
 * So this one installs the core: the registry, the shell, the roster, areas and
 * the atlas, plus the security file an installation gets from the skeleton,
 * because the roster's provider entity is a security provider and TeamBundle
 * does not boot without one. What is asserted through it is the core's own
 * content, materialised, and its commands reachable.
 *
 * It has no route of its own. Nothing here renders a page; the console
 * application is the surface under test.
 */
final class InstallationKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new UtafitiLabsPostGISBundle();
        yield new TwigBundle();
        yield new UXIconsBundle();
        yield new StimulusBundle();
        yield new SecurityBundle();
        yield new RegistryBundle();
        yield new ShellBundle();
        yield new AtlasBundle();
        yield new TeamBundle();
        yield new AreaBundle();
        yield new UhifadhiDevkitBundle();
    }

    /**
     * The asset side of a Flex-installed application, and nothing else: the
     * shell's document renders the importmap of whatever application it is
     * installed in, so a kernel that boots the shell needs one.
     */
    public function getProjectDir(): string
    {
        return \dirname(__DIR__).'/Console/Fixtures/app';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection' => ['enabled' => true],
            'assets' => true,
            'asset_mapper' => [
                'paths' => [$this->getProjectDir().'/assets' => ''],
            ],
        ]);

        // A console run through a debug kernel narrates every dispatched event
        // to standard output, which would bury the command's own words in the
        // one place this suite reads them.
        $container->services()->set('logger', NullLogger::class);

        // No network from a boot: a name no file answers to resolves to nothing
        // rather than an HTTP call.
        $container->extension('ux_icons', [
            'iconify' => ['enabled' => false],
            'ignore_not_found' => true,
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => '%env(UHIFADHI_TEST_DATABASE_URL)%'],
            'orm' => [
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore',
                'controller_resolver' => ['auto_mapping' => false],
            ],
        ]);

        // The security file an installation gets from the skeleton. The roster
        // owns the account entity and every mechanism over it; the firewall
        // shape is the installation's, so a kernel standing in for one writes it.
        $container->extension('security', [
            'password_hashers' => [
                PasswordAuthenticatedUserInterface::class => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
            'providers' => [
                'team_user_provider' => [
                    'entity' => ['class' => User::class, 'property' => 'email'],
                ],
            ],
            'firewalls' => [
                'api_auth' => [
                    'pattern' => '^/api/auth/token$',
                    'security' => false,
                ],
                'api' => [
                    'pattern' => '^/api',
                    'stateless' => true,
                    'provider' => 'team_user_provider',
                    'user_checker' => 'team.user_checker',
                    'custom_authenticators' => [ApiTokenAuthenticator::class],
                    'entry_point' => ApiTokenAuthenticator::class,
                ],
                'main' => [
                    'lazy' => true,
                    'provider' => 'team_user_provider',
                    'user_checker' => 'team.user_checker',
                    'form_login' => [
                        'login_path' => 'team_login',
                        'check_path' => 'team_login',
                        'enable_csrf' => true,
                        'default_target_path' => '/',
                    ],
                    'logout' => ['path' => 'team_logout', 'target' => 'team_login'],
                ],
            ],
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_USER'],
                'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN', 'ROLE_ALLOWED_TO_SWITCH'],
            ],
            'access_control' => [
                ['path' => '^/login', 'roles' => 'PUBLIC_ACCESS'],
                ['path' => '^/', 'roles' => 'ROLE_USER'],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The sign-in screen, because the firewall names its route and a
        // firewall pointing at a route no router knows will not compile.
        $routes->import('@TeamBundle/Controller/', 'attribute');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/devkit-module-tests/installation-cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/devkit-module-tests/installation-log';
    }
}
