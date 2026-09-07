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

namespace Uhifadhi\Devkit\Console\Wiring;

use Uhifadhi\Devkit\Console\Package\PackageIntrospector;

/**
 * THE TAG INSPECTOR — for each contribution seam the platform defines, who is
 * registered and how many collected.
 *
 * The seams are known: the module seam, the map layers, the widget surfaces, the
 * department KPIs, the overview providers, and devkit's own two. Which services
 * carry each tag is not known until the container is compiled, so it is collected
 * there ({@see \Uhifadhi\Devkit\DependencyInjection\Compiler\CollectSeamsPass})
 * and handed in as a class-name map — this service turns that map into the seams
 * a surface renders, resolving each contributor back to the module that shipped
 * it.
 *
 * It enumerates every KNOWN seam, including the ones nothing has wired into yet:
 * a seam at zero is the honest state of a fresh installation, and hiding it would
 * make the console lie about what the platform offers.
 */
final class SeamInspector
{
    /**
     * The platform's contribution seams, in the order the Wiring surface lays
     * them out, each with the sentence the console explains it by. The keys are
     * the tag strings the compiler pass collects.
     */
    public const array KNOWN_SEAMS = [
        'uhifadhi.module' => 'Every installed module registers itself here so the host can enumerate the fleet.',
        'uhifadhi.map.layer' => 'Map layers a module draws on the shared plate.',
        'uhifadhi.department.kpi' => 'Per-department KPI providers a module contributes to the lens.',
        'uhifadhi.widget_surface' => 'Surfaces a widget may be placed on.',
        'uhifadhi.overview.provider' => 'Now-tiles and attention items the area overview lays out.',
        'uhifadhi.devkit.content_provider' => 'Demo-content slices devkit seeds through fixtures:demo, in dependency order.',
        'uhifadhi.devkit.command_provider' => 'Dev/maintenance commands devkit registers as real console commands in a dev install.',
    ];

    /**
     * @param array<string, list<string>> $collected tag => the classes registered on it
     */
    public function __construct(
        private readonly array $collected,
        private readonly PackageIntrospector $packages,
    ) {
    }

    /**
     * @return list<Seam>
     */
    public function seams(): array
    {
        $seams = [];
        foreach (self::KNOWN_SEAMS as $tag => $description) {
            $seams[] = new Seam($tag, $description, $this->contributorsFor($tag));
        }

        return $seams;
    }

    public function seamCount(): int
    {
        return \count(self::KNOWN_SEAMS);
    }

    public function providerCount(): int
    {
        return array_sum(array_map(static fn (Seam $s): int => $s->count(), $this->seams()));
    }

    /**
     * How many distinct modules register on at least one seam.
     */
    public function contributorCount(): int
    {
        $packages = [];
        foreach ($this->seams() as $seam) {
            foreach ($seam->contributors as $contributor) {
                if (null !== $contributor->package) {
                    $packages[$contributor->package->name] = true;
                }
            }
        }

        return \count($packages);
    }

    /**
     * The busiest seam and how many it carries — the "widest" headline.
     *
     * @return array{tag: string, count: int}
     */
    public function widest(): array
    {
        $widest = ['tag' => '', 'count' => 0];
        foreach ($this->seams() as $seam) {
            if ($seam->count() > $widest['count']) {
                $widest = ['tag' => $seam->tag, 'count' => $seam->count()];
            }
        }

        return $widest;
    }

    /**
     * @return list<SeamContributor>
     */
    private function contributorsFor(string $tag): array
    {
        $contributors = [];
        foreach ($this->collected[$tag] ?? [] as $class) {
            $contributors[] = new SeamContributor($class, $this->packages->ownerOfClass($class));
        }

        return $contributors;
    }
}
