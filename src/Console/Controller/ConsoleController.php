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

namespace Uhifadhi\Devkit\Console\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Uhifadhi\Devkit\Console\Command\CommandInventory;
use Uhifadhi\Devkit\Console\Doctor\Conformance;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Wiring\ContributionPointInspector;

/**
 * THE DEV CONSOLE'S CONTROLLER — the four inspector surfaces, and their home.
 *
 * A PRESENTATION CONTROLLER, like the shell's: it reads what the introspection
 * services assemble — the command list, the module registry, the conformance
 * report, the contribution-point inspector — and renders one of devkit's own templates in the
 * shell frame. It runs nothing and writes nothing; v1 is an inspector.
 *
 * TWO FIREWALLS KEEP IT OUT OF PRODUCTION. The first is Composer: devkit installs
 * through require-dev, so this class is not in a production build at all. The
 * second is here, belt to that braces — every action refuses unless the kernel is
 * in debug, so even an application that mistakenly required devkit outside dev
 * serves a 404, not a console. Debug is on in dev and test and off in prod, which
 * is exactly the line the console must not cross.
 *
 * NO BASE CLASS, wired explicitly in config/services.php — the reusable-bundle
 * rule the whole fleet follows.
 */
final class ConsoleController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly CommandInventory $commands,
        private readonly ModuleRegistry $modules,
        private readonly Conformance $conformance,
        private readonly ContributionPointInspector $points,
        private readonly bool $debug,
    ) {
    }

    public function home(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/home.html.twig', [
            'catalogue' => $this->commands->catalogue(),
            'registry' => $this->modules->view(),
            'report' => $this->conformance->report(),
            'inspector' => $this->points,
        ]);
    }

    public function commands(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/commands.html.twig', [
            'catalogue' => $this->commands->catalogue(),
        ]);
    }

    public function modules(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/modules.html.twig', [
            'registry' => $this->modules->view(),
            'report' => $this->conformance->report(),
        ]);
    }

    public function doctor(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/doctor.html.twig', [
            'report' => $this->conformance->report(),
        ]);
    }

    public function wiring(): Response
    {
        $this->assertAvailable();

        return $this->render('@UhifadhiDevkit/console/wiring.html.twig', [
            'inspector' => $this->points,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): Response
    {
        return new Response($this->twig->render($template, $context));
    }

    /**
     * The debug firewall: outside a debug kernel the console does not exist.
     */
    private function assertAvailable(): void
    {
        if (!$this->debug) {
            throw new NotFoundHttpException('The devkit console is a dev-only surface.');
        }
    }
}
