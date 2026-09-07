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

namespace Uhifadhi\Devkit\Console\Doctor;

use Uhifadhi\Devkit\Console\Module\CoreState;
use Uhifadhi\Devkit\Console\Module\ModuleReach;
use Uhifadhi\Devkit\Console\Module\ModuleRegistry;
use Uhifadhi\Devkit\Console\Module\ModuleRow;
use Uhifadhi\Devkit\Console\Package\PackageIntrospector;

/**
 * TURNS THE REGISTRY INTO PASS / WARN / FAIL — the same facts the Modules page
 * lists, read as conformance against the modular-core checklist.
 *
 * It computes what it can read and defers, out loud, what it cannot. Pinning the
 * core is {@see CoreState} again; the dev-main marker is a scan of the package's
 * own constraints; routes-stamped is read off the registry's route count. The
 * two checks that need source scanning — templates extending the shell, geometry
 * reads guarding for NULL — are reported {@see CheckState::Deferred}, not faked
 * green.
 *
 * ROUTES-STAMPED IS A PARTIAL CHECK, and says so: it confirms a module's routes
 * ARE stamped where it has any (a non-zero stamped count passes), but cannot yet
 * tell "no routes" from "routes present but unstamped" — both read as
 * not-applicable. Detecting an unstamped module route needs mapping every route
 * back to its owning package, a later slice.
 */
final class Conformance
{
    private const string CONTRACTS_PACKAGE = 'uhifadhi/module-contracts';

    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly PackageIntrospector $packages,
    ) {
    }

    public function report(): DoctorReport
    {
        $view = $this->registry->view();

        $matrix = [];
        $attention = [];
        $pinsCorePassing = 0;
        $noDevMainPassing = 0;
        $routesStampedPassing = 0;

        foreach ($view->rows as $row) {
            // The seam itself is not a module and takes no matrix row.
            if (ModuleReach::TheContract === $row->reach) {
                continue;
            }

            $pinsCore = $this->pinsCore($row->coreState);
            $noDevMain = $this->noDevMain($row->package->name);
            $routesStamped = $row->routes > 0 ? CheckState::Pass : CheckState::NotApplicable;

            $matrix[] = new MatrixRow($row->package, [
                ConformanceCheck::PinsCore->value => $pinsCore,
                ConformanceCheck::NoDevMain->value => $noDevMain,
                ConformanceCheck::RoutesStamped->value => $routesStamped,
                ConformanceCheck::ExtendsShell->value => CheckState::Deferred,
                ConformanceCheck::GeomGuards->value => CheckState::Deferred,
            ]);

            if (CheckState::Warn === $pinsCore) {
                $attention[] = $this->behindCoreFinding($row, $view->currentCore);
            } elseif (CheckState::Pass === $pinsCore) {
                ++$pinsCorePassing;
            }

            if (CheckState::Fail === $noDevMain) {
                $attention[] = $this->devMainFinding($row);
            } else {
                ++$noDevMainPassing;
            }

            if (CheckState::Pass === $routesStamped) {
                ++$routesStampedPassing;
            }
        }

        return new DoctorReport(
            matrix: $matrix,
            attention: $this->worstFirst($attention),
            passing: $this->passingFindings($pinsCorePassing, $noDevMainPassing, $routesStampedPassing, $view->totalRoutes()),
            deferred: ConformanceCheck::deferred(),
        );
    }

    private function pinsCore(CoreState $state): CheckState
    {
        return match ($state) {
            CoreState::OnCore => CheckState::Pass,
            CoreState::BehindCore => CheckState::Warn,
            CoreState::NotApplicable => CheckState::NotApplicable,
        };
    }

    private function noDevMain(string $package): CheckState
    {
        return null === $this->devMainRequirement($package) ? CheckState::Pass : CheckState::Fail;
    }

    /**
     * The first constraint that carries a `dev-main` marker, as "name: value", or
     * null when the package carries none.
     */
    private function devMainRequirement(string $package): ?string
    {
        foreach ($this->packages->requirements($package) as $dependency => $constraint) {
            if (str_contains($constraint, 'dev-main')) {
                return \sprintf('%s: %s', $dependency, $constraint);
            }
        }

        return null;
    }

    private function behindCoreFinding(ModuleRow $row, string $currentCore): Finding
    {
        return new Finding(
            CheckState::Warn,
            \sprintf('%s-module still pins the old core', $row->package->shortName),
            \sprintf(
                'Constrains %s: %s — widen to admit %s and re-tag.',
                self::CONTRACTS_PACKAGE,
                $row->contractsConstraint ?? '?',
                $currentCore,
            ),
            $row->package->name.'/composer.json',
        );
    }

    private function devMainFinding(ModuleRow $row): Finding
    {
        $requirement = $this->devMainRequirement($row->package->name) ?? '';

        return new Finding(
            CheckState::Fail,
            \sprintf('%s-module carries a dev-main marker', $row->package->shortName),
            \sprintf('Requires %s — a PoC pin. Drop the “|| dev-main” and re-tag the fleet.', $requirement),
            $row->package->name.'/composer.json',
        );
    }

    /**
     * @return list<Finding>
     */
    private function passingFindings(int $pinsCore, int $noDevMain, int $routesStamped, int $totalRoutes): array
    {
        $passing = [];

        if ($pinsCore > 0) {
            $passing[] = new Finding(CheckState::Pass, 'Modules on the current core', \sprintf('%d %s pin a core the installed contracts satisfies.', $pinsCore, 1 === $pinsCore ? 'module' : 'modules'));
        }
        if ($noDevMain > 0) {
            $passing[] = new Finding(CheckState::Pass, 'No dev-main markers', \sprintf('%d %s carry no dev-main pin in their constraints.', $noDevMain, 1 === $noDevMain ? 'module' : 'modules'));
        }
        if ($routesStamped > 0) {
            $passing[] = new Finding(CheckState::Pass, 'Module routes are stamped', \sprintf('%d module routes across the fleet carry the seam’s _uhifadhi_module stamp.', $totalRoutes));
        }

        return $passing;
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<Finding>
     */
    private function worstFirst(array $findings): array
    {
        usort($findings, static fn (Finding $a, Finding $b): int => self::weight($b->state) <=> self::weight($a->state));

        return $findings;
    }

    private static function weight(CheckState $state): int
    {
        return match ($state) {
            CheckState::Fail => 2,
            CheckState::Warn => 1,
            default => 0,
        };
    }
}
