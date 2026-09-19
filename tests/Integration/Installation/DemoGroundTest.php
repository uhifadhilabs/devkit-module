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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Uhifadhi\Bundle\AreaBundle\Entity\AreaOfInterest;
use Uhifadhi\Bundle\AreaBundle\Entity\Posting;
use Uhifadhi\Bundle\AreaBundle\Entity\Station;
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;

/**
 * THE GROUND UNDER THE DEMO ORGANISATION — areas, the zones that subdivide them,
 * the stations standing in those zones, and the people posted to them.
 *
 * FOUR PROVIDERS, ONE ORDER, ASKED THROUGH THE COMMAND. Each of them is a slice
 * that only makes sense on top of another: a zone needs an area to subdivide, a
 * station needs zones for its own to be derived from, a posting needs both a
 * station and somebody to stand at it. That order is stated as dependsOn edges
 * in the core and resolved here by devkit, so what this suite asks is whether
 * the graph the core declares produces the ground a developer expects to find.
 *
 * WHAT IS COUNTED IS WHAT THE SCREENS READ. Zones per area, stations per area,
 * the one station left unstaffed and the one leader standing at each of the
 * others: every number here is a fact one of the area's own pages prints, so a
 * provider that seeded half of it would show as a half-drawn page rather than
 * as a passing test.
 *
 * AND IT IS ASKED TWICE. `fixtures:demo` is a command a developer re-runs
 * without thinking about it, and a second run that doubled the stations would
 * be a bug discovered on a screen days later.
 */
final class DemoGroundTest extends TestCase
{
    private KernelInterface $kernel;
    private Application $console;

    protected function setUp(): void
    {
        $this->kernel = new InstallationKernel('test', true);
        $this->kernel->boot();

        $this->console = new Application($this->kernel);
        $this->console->setAutoExit(false);
        $this->console->setCatchExceptions(false);

        $this->rebuildSchema();
        $this->seed();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testEveryDemoAreaIsSubdividedIntoAZoningScheme(): void
    {
        $areas = $this->areas();

        self::assertNotEmpty($areas, 'The zones and the stations hang on the demo areas, so an empty register is the whole slice missing.');

        foreach ($areas as $area) {
            self::assertCount(
                6,
                $this->zonesOf($area),
                \sprintf('%s is seeded with a whole zoning scheme, imported as one FeatureCollection.', (string) $area->getName()),
            );
        }
    }

    public function testEveryStationStandsInTheAreaAndAllButOneInAZone(): void
    {
        foreach ($this->areas() as $area) {
            $stations = $this->stationsOf($area);

            self::assertCount(8, $stations, 'Eight posts to look at on the area\'s own map.');

            $unzoned = array_filter($stations, static fn (Station $station): bool => null === $station->getZone());

            self::assertCount(
                1,
                $unzoned,
                'A station on ground no zone covers is a real state the screens have to draw, so the demo carries exactly one.',
            );
        }
    }

    public function testAStationCarriesTheFactsItsPagePrints(): void
    {
        $stations = $this->stationsOf($this->areas()[0]);

        foreach ($stations as $station) {
            self::assertNotNull($station->getCode(), \sprintf('%s is addressed by a code on the roster.', (string) $station->getName()));
            self::assertNotNull($station->getElevationM(), \sprintf('%s prints its elevation.', (string) $station->getName()));
            self::assertNotNull($station->getLocality(), \sprintf('%s prints where it stands.', (string) $station->getName()));
        }
    }

    public function testEveryStaffedStationHasExactlyOneLeaderAndOneStationHasNobody(): void
    {
        foreach ($this->areas() as $area) {
            $unstaffed = 0;

            foreach ($this->stationsOf($area) as $station) {
                $standing = $this->standingAt($station);

                if ([] === $standing) {
                    ++$unstaffed;

                    continue;
                }

                $leaders = array_filter($standing, static fn (Posting $posting): bool => $posting->isLeader());

                self::assertCount(
                    1,
                    $leaders,
                    \sprintf('One person leads at %s — no more, and never none.', (string) $station->getName()),
                );
            }

            self::assertSame(
                1,
                $unstaffed,
                \sprintf('A post nobody works out of is a state %s has to draw too.', (string) $area->getName()),
            );
        }
    }

    public function testASecondRunSeedsNothingAgain(): void
    {
        $before = [
            'areas' => \count($this->areas()),
            'zones' => \count($this->entityManager()->getRepository(Zone::class)->findAll()),
            'stations' => \count($this->entityManager()->getRepository(Station::class)->findAll()),
            'postings' => \count($this->entityManager()->getRepository(Posting::class)->findAll()),
        ];

        self::assertGreaterThan(0, $before['postings'], 'Nobody posted anywhere is the staffing slice missing.');

        $this->seed();
        $this->entityManager()->clear();

        self::assertSame($before, [
            'areas' => \count($this->areas()),
            'zones' => \count($this->entityManager()->getRepository(Zone::class)->findAll()),
            'stations' => \count($this->entityManager()->getRepository(Station::class)->findAll()),
            'postings' => \count($this->entityManager()->getRepository(Posting::class)->findAll()),
        ], 'A developer re-running the seeder is repeating a command, not asking for a second copy of the ground.');
    }

    /** @return list<AreaOfInterest> */
    private function areas(): array
    {
        return $this->entityManager()->getRepository(AreaOfInterest::class)->findBy([], ['name' => 'ASC']);
    }

    /** @return list<Zone> */
    private function zonesOf(AreaOfInterest $area): array
    {
        return $this->entityManager()->getRepository(Zone::class)->findBy(['area' => $area]);
    }

    /** @return list<Station> */
    private function stationsOf(AreaOfInterest $area): array
    {
        return $this->entityManager()->getRepository(Station::class)->findBy(['area' => $area]);
    }

    /** @return list<Posting> */
    private function standingAt(Station $station): array
    {
        return $this->entityManager()->getRepository(Posting::class)->findBy(['station' => $station, 'endedAt' => null]);
    }

    private function seed(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->console->run(new ArrayInput(['command' => 'fixtures:demo']), $output);

        self::assertSame(0, $exitCode, $output->fetch());
    }

    private function rebuildSchema(): void
    {
        $manager = $this->entityManager();
        $metadata = $manager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($manager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    private function entityManager(): EntityManagerInterface
    {
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);

        $registry = $testContainer->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
