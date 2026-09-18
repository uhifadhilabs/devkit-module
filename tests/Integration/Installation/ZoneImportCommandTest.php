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
use Uhifadhi\Bundle\AreaBundle\Entity\Zone;
use Uhifadhi\Bundle\AreaBundle\Entity\ZoneImport;
use Uhifadhi\Bundle\AreaBundle\Service\AreaCreator;

/**
 * A ZONING SCHEME IMPORTED FROM THE CONSOLE, against a real installation.
 *
 * The core carries the import and deliberately ships no command for it, and the
 * zones screen is not settled yet — so a developer standing a park up has no way
 * to get a subdivision into an area. devkit is where that path belongs, and this
 * suite asks the question a developer asks: the file on disk, the command they
 * type, the rows that appear and the words on the screen.
 *
 * IT ASKS THE CONSOLE APPLICATION, not the command class, for the same reason
 * {@see CoreProvidersMaterialiseTest} does: what is in question is whether
 * `bin/console` finds it and whether the summary a person reads is the import's
 * own.
 *
 * THE FILES ARE SYNTHETIC. Two unit squares in the ocean off nobody's coast,
 * carrying the residue a real export carries — a capitalised name column, KML
 * leftovers, a merge field and an altitude on every vertex — because those are
 * the things the summary has to account for.
 */
final class ZoneImportCommandTest extends TestCase
{
    private KernelInterface $kernel;
    private Application $console;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        $this->kernel = new InstallationKernel('test', true);
        $this->kernel->boot();

        $this->console = new Application($this->kernel);
        $this->console->setAutoExit(false);
        $this->console->setCatchExceptions(false);

        $this->rebuildSchema();
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        $this->files = [];

        $this->kernel->shutdown();
    }

    public function testTheConsoleListsTheImport(): void
    {
        $output = new BufferedOutput();
        $this->console->run(new ArrayInput(['command' => 'list']), $output);

        self::assertStringContainsString('area:zones:import', $output->fetch());
    }

    /**
     * The whole path: a file a desktop GIS could have exported becomes zones, and
     * the summary states what was made, where the names came from and what was
     * read past.
     */
    public function testItImportsAFeatureCollectionAndSummarisesWhatHappened(): void
    {
        $area = $this->area();
        $file = $this->geoJson([
            $this->feature('Northern block', 0, 0),
            $this->feature('Southern block', 0, 2),
        ]);

        $output = new BufferedOutput();
        $exitCode = $this->console->run(
            new ArrayInput(['command' => 'area:zones:import', 'area' => $area, 'file' => $file]),
            $output,
        );
        $printed = $output->fetch();

        self::assertSame(0, $exitCode, $printed);

        $zones = $this->entityManager()->getRepository(Zone::class)->findAll();
        self::assertCount(2, $zones, 'One feature is one zone, and the file held two.');

        $names = array_map(static fn (Zone $zone): ?string => $zone->getName(), $zones);
        sort($names);
        self::assertSame(['Northern block', 'Southern block'], $names);

        self::assertStringContainsString('Northern block', $printed, 'A summary that does not name the zones cannot be checked against the file.');
        self::assertStringContainsString('Southern block', $printed);
        self::assertStringContainsString('Name', $printed, 'The property the names came out of, because the file offered more than one plausible answer.');
        self::assertStringContainsString('description', $printed, 'What was read past has to be said, or somebody is left wondering where it went.');
        self::assertStringContainsString('layer', $printed);
        self::assertStringContainsString('altitudeMode', $printed);

        $import = $this->entityManager()->getRepository(ZoneImport::class)->findAll();
        self::assertCount(1, $import, 'One file read is one row of provenance beside the geometry.');
        self::assertSame('Name', $import[0]->getNameProperty());
    }

    /**
     * A refused scheme leaves the area exactly as it was, and the person reading
     * the console gets the import's own sentence rather than a stack trace.
     */
    public function testItRefusesOverlappingZonesWithTheImportsOwnSentence(): void
    {
        $area = $this->area();
        $file = $this->geoJson([
            $this->feature('Northern block', 0, 0),
            $this->feature('Overlapping block', 0, 0.5),
        ]);

        $output = new BufferedOutput();
        $exitCode = $this->console->run(
            new ArrayInput(['command' => 'area:zones:import', 'area' => $area, 'file' => $file]),
            $output,
        );
        $printed = $output->fetch();

        self::assertNotSame(0, $exitCode, 'A refused import that exits 0 tells a script the subdivision is in.');
        $this->assertSaid(
            'Zone "Overlapping block" overlaps zone "Northern block" — zones of one area may touch along an edge or leave gaps, but never share interior.',
            $printed,
            'The words are the import\'s, not the command\'s.',
        );

        self::assertSame([], $this->entityManager()->getRepository(Zone::class)->findAll(), 'All or nothing: half a subdivision is a wrong one.');
        self::assertSame([], $this->entityManager()->getRepository(ZoneImport::class)->findAll());
    }

    /** An area nobody has heard of is a typed uuid, not an exception. */
    public function testItRefusesAnAreaThatDoesNotExist(): void
    {
        $file = $this->geoJson([$this->feature('Northern block', 0, 0)]);

        $output = new BufferedOutput();
        $exitCode = $this->console->run(
            new ArrayInput([
                'command' => 'area:zones:import',
                'area' => '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
                'file' => $file,
            ]),
            $output,
        );

        self::assertNotSame(0, $exitCode);
        self::assertStringContainsString('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b', $output->fetch());
    }

    /** A path nobody can read is worth its own sentence too. */
    public function testItRefusesAFileThatIsNotThere(): void
    {
        $path = sys_get_temp_dir().'/devkit-module-tests/no-such-scheme.geojson';

        $output = new BufferedOutput();
        $exitCode = $this->console->run(
            new ArrayInput(['command' => 'area:zones:import', 'area' => $this->area(), 'file' => $path]),
            $output,
        );

        self::assertNotSame(0, $exitCode);
        $this->assertSaid($path, $output->fetch(), 'The path typed is the path named back.');
    }

    /**
     * A console block is wrapped and padded to the terminal width, so a sentence
     * is asserted on with the whitespace taken out of both sides — the words are
     * what is under test, not where the renderer broke the lines.
     */
    private function assertSaid(string $sentence, string $printed, string $why): void
    {
        self::assertStringContainsString(
            (string) preg_replace('/\s+/u', '', $sentence),
            (string) preg_replace('/\s+/u', '', $printed),
            $why,
        );
    }

    /** The uuid of a fresh area, boundaryless — an area is named before it is drawn. */
    private function area(): string
    {
        $creator = $this->testContainer()->get(AreaCreator::class);
        self::assertInstanceOf(AreaCreator::class, $creator);

        $uuid = $creator->create('Sandbox reserve')->getUuidString();
        self::assertNotNull($uuid);

        return $uuid;
    }

    /**
     * One square degree at the given corner, with the residue an export carries:
     * a capitalised name column, KML leftovers, a merge field, and an altitude on
     * every vertex.
     *
     * @return array<string, mixed>
     */
    private function feature(string $name, float $lon, float $lat): array
    {
        $ring = [
            [$lon, $lat, 0],
            [$lon + 1, $lat, 0],
            [$lon + 1, $lat + 1, 0],
            [$lon, $lat + 1, 0],
            [$lon, $lat, 0],
        ];

        return [
            'type' => 'Feature',
            'properties' => [
                'Name' => $name,
                'description' => 'Synthetic block, for the suite.',
                'altitudeMode' => 'clampToGround',
                'tessellate' => -1,
                'layer' => 'scheme-v1',
            ],
            'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
        ];
    }

    /**
     * @param list<array<string, mixed>> $features
     *
     * @return string the path of a file this test will delete again
     */
    private function geoJson(array $features): string
    {
        $path = sys_get_temp_dir().'/devkit-module-tests/'.uniqid('scheme-', true).'.geojson';
        @mkdir(\dirname($path), 0o777, true);
        file_put_contents($path, (string) json_encode(
            ['type' => 'FeatureCollection', 'features' => $features],
            \JSON_THROW_ON_ERROR,
        ));

        $this->files[] = $path;

        return $path;
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
        $registry = $this->testContainer()->get('doctrine');
        self::assertInstanceOf(ManagerRegistry::class, $registry);

        $manager = $registry->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }

    private function testContainer(): ContainerInterface
    {
        $testContainer = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);

        return $testContainer;
    }
}
