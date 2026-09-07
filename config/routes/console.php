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

namespace Symfony\Component\Routing\Loader\Configurator;

/*
 * THE DEV CONSOLE'S ADDRESSES — shipped as a resource, loaded by nobody here.
 *
 * Like the shell's welcome route, nothing in this bundle imports this file. A dev
 * application asks for it, in a `when@dev` block it owns:
 *
 *     # config/routes/devkit.yaml (your application)
 *     when@dev:
 *         devkit:
 *             resource: '@UhifadhiDevkitBundle/config/routes/console.php'
 *
 * That is the second brace on the dev-only firewall. The first is Composer:
 * devkit is require-dev, so on a production build this file is not on disk to
 * import; the controller's debug guard is the third. The `_devkit` prefix marks
 * the surface as a dev tool, the way `/_profiler` does.
 *
 * PHP, NOT YAML, for the reason the whole fleet's route resources are: a reusable
 * bundle must not force symfony/yaml onto the applications that install it.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('devkit_console', '/_devkit')
        ->controller(['devkit.console.controller', 'home']);

    $routes->add('devkit_console_commands', '/_devkit/commands')
        ->controller(['devkit.console.controller', 'commands']);

    $routes->add('devkit_console_modules', '/_devkit/modules')
        ->controller(['devkit.console.controller', 'modules']);

    $routes->add('devkit_console_doctor', '/_devkit/doctor')
        ->controller(['devkit.console.controller', 'doctor']);

    $routes->add('devkit_console_wiring', '/_devkit/wiring')
        ->controller(['devkit.console.controller', 'wiring']);
};
