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

namespace Uhifadhi\Devkit\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\File\File;
use Uhifadhi\Bundle\AreaBundle\Exception\ZoneImportException;
use Uhifadhi\Bundle\AreaBundle\Repository\AreaOfInterestRepository;
use Uhifadhi\Bundle\AreaBundle\Service\ZoneImportService;

/**
 * `area:zones:import` — one GeoJSON FeatureCollection becomes an area's zoning
 * scheme, from the console.
 *
 * The core carries the import itself and ships NO command for it: an
 * installation's console holds one command, and a zoning scheme is not the
 * account an installation is bootstrapped with. The screen that will offer this
 * is waiting on its design. Between those two facts sits a developer with a
 * scheme file and an area that has no zones in it, which is exactly the gap
 * devkit exists to close — and closing it here rather than in the core means the
 * path is absent from a production build, because require-dev is the firewall.
 *
 * IT DECIDES NOTHING. Every rule about what a zoning scheme may be — the name
 * property, the altitudes dropped, WGS84, the zone invariant, what may be added
 * to a set that already has zones in it — belongs to {@see ZoneImportService},
 * and every word printed here is that service's own.
 *
 * AN IMPORT ADDS AND NEVER OVERWRITES, so this run has two ordinary endings.
 * The features that fit arrive; the ones that do not are printed with the
 * reason beside them, and the run still succeeds — a scheme that grew by nine
 * of eleven is what was asked for, and nothing was destroyed to make room for
 * it. A non-zero exit is reserved for the file the import refused whole, the
 * one nobody can act on feature by feature.
 *
 * THE SUMMARY HAS BOTH HALVES, because {@see \Uhifadhi\Bundle\AreaBundle\Model\ZoneImportResult}
 * has both: the zones that were made, the ones that were left out and why, the
 * property the names came out of, and every property the file carried that the
 * import read past. A file exported from a desktop GIS is full of description,
 * altitudeMode and merge fields, and somebody who is not told they were ignored
 * will go looking for them.
 *
 * THERE IS NO `--dry-run`, and adding one is not this command's call. The
 * service previews a file through {@see ZoneImportService::plan()}, which is
 * what the screen confirms against; offering a second shape of that from out
 * here would be a second answer to one question. A developer who wants the
 * preview calls the service.
 *
 * Nobody is recorded as the importer: provenance names a person where one is
 * known, and a console run is a machine with a file path.
 */
#[AsCommand(
    name: 'area:zones:import',
    description: 'Import an area\'s zoning scheme from one GeoJSON FeatureCollection, one feature per zone (dev-only).',
)]
final class ZoneImportCommand extends Command
{
    public function __construct(
        private readonly AreaOfInterestRepository $areas,
        private readonly ZoneImportService $import,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('area', InputArgument::REQUIRED, 'The uuid of the area the scheme subdivides')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the GeoJSON file holding one polygon per zone');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $uuid a required, non-array argument is always a string */
        $uuid = $input->getArgument('area');
        /** @var string $path */
        $path = $input->getArgument('file');

        $area = $this->areas->findOneByUuid($uuid);
        if (null === $area) {
            $io->error(\sprintf('No area has the uuid "%s". A scheme is imported into an area that already exists.', $uuid));

            return Command::FAILURE;
        }

        if (!is_file($path) || !is_readable($path)) {
            $io->error(\sprintf('There is no readable file at "%s".', $path));

            return Command::FAILURE;
        }

        try {
            // The original name is what the file is called, because that is what
            // the provenance row keeps and what the refusals quote — a temporary
            // upload path would be provenance of nothing.
            $result = $this->import->importInto($area, new File($path), basename($path));
        } catch (ZoneImportException $e) {
            // A whole-file refusal: nothing was read, so the area has exactly
            // the zones it had, and the sentence is the import's own.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title(\sprintf('Zones imported into %s', $area->getName() ?? 'the area'));

        $io->text(\sprintf('Read from <info>%s</info>.', $result->fileName));

        if ([] !== $result->added) {
            $io->listing($result->added);
        }

        $io->text(\sprintf('Names came from the <info>%s</info> property.', $result->nameProperty));

        $io->text([] === $result->ignoredProperties
            ? 'The file carried no other properties.'
            : \sprintf('Properties read past and not stored: %s.', implode(', ', $result->ignoredProperties)));

        if ([] !== $result->skipped) {
            $rows = [];
            foreach ($result->skipped as $name => $why) {
                $rows[] = [$name, $why];
            }

            $io->table(['Left out', 'Why'], $rows);
        }

        $io->success(\sprintf(
            '%d added · %d skipped, in %s.',
            $result->count(),
            $result->skippedCount(),
            $area->getName() ?? 'the area',
        ));

        return Command::SUCCESS;
    }
}
