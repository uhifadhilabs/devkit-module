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

namespace Uhifadhi\Devkit\Tests\Unit\Console;

use PHPUnit\Framework\TestCase;
use Uhifadhi\Devkit\Console\Package\ResolvedPackage;
use Uhifadhi\Devkit\Console\Wiring\Seam;
use Uhifadhi\Devkit\Console\Wiring\SeamInspector;
use Uhifadhi\Devkit\Tests\Integration\Console\Fixtures\FixtureModuleProvider;
use Uhifadhi\Devkit\Tests\Integration\Fixtures\RecordingContentProvider;
use Uhifadhi\Devkit\Tests\Unit\Console\Fixtures\FakePackageIntrospector;

final class SeamInspectorTest extends TestCase
{
    public function testItEnumeratesEveryKnownSeamIncludingTheEmptyOnes(): void
    {
        $inspector = $this->inspector();

        $tags = array_map(static fn (Seam $s): string => $s->tag, $inspector->seams());
        self::assertSame(array_keys(SeamInspector::KNOWN_SEAMS), $tags, 'Every defined seam is listed, empty ones included.');

        $kpi = $this->seam($inspector, 'uhifadhi.department.kpi');
        self::assertSame(0, $kpi->count(), 'A seam nothing has wired into reads zero, honestly.');
    }

    public function testItCollectsContributorsAndResolvesTheirModule(): void
    {
        $module = $this->seam($this->inspector(), 'uhifadhi.module');

        self::assertSame(2, $module->count());
        self::assertSame('FixtureModuleProvider', $module->contributors[0]->shortClass());
        self::assertSame('patrol', $module->contributors[0]->package?->shortName);
        self::assertSame('incident', $module->contributors[1]->package?->shortName);
    }

    public function testItSummarisesTheWiringForTheHeadline(): void
    {
        $inspector = $this->inspector();

        self::assertSame(\count(SeamInspector::KNOWN_SEAMS), $inspector->seamCount());
        self::assertSame(3, $inspector->providerCount(), 'two on the module seam, one demo-content provider.');
        self::assertSame(2, $inspector->contributorCount(), 'patrol and incident.');
        self::assertSame(['tag' => 'uhifadhi.module', 'count' => 2], $inspector->widest());
    }

    private function inspector(): SeamInspector
    {
        $packages = new FakePackageIntrospector(ownersByClass: [
            FixtureModuleProvider::class => ResolvedPackage::of('uhifadhi/patrol-module', '0.5.2'),
            RecordingContentProvider::class => ResolvedPackage::of('uhifadhi/incident-module', '0.2.2'),
        ]);

        // The module seam carries two FixtureModuleProvider registrations (patrol
        // and incident, same class, different modules); one demo-content provider.
        // A pass hands the inspector class names, so both module registrations
        // read as the same class — the count is what matters.
        $collected = [
            'uhifadhi.module' => [FixtureModuleProvider::class, RecordingContentProvider::class],
            'uhifadhi.devkit.content_provider' => [RecordingContentProvider::class],
        ];

        return new SeamInspector($collected, $packages);
    }

    private function seam(SeamInspector $inspector, string $tag): Seam
    {
        foreach ($inspector->seams() as $seam) {
            if ($seam->tag === $tag) {
                return $seam;
            }
        }

        self::fail(\sprintf('No seam "%s".', $tag));
    }
}
