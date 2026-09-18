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
 * property, the altitudes dropped, WGS84, the zone invariant, all-or-nothing —
 * belongs to {@see ZoneImportService}, and every refusal printed here is that
 * service's own sentence. What this command adds is the two things a console run
 * needs that a service call does not: an area named by uuid rather than handed
 * over as an object, and a readable summary of what the import did with the file.
 *
 * THE SUMMARY HAS BOTH HALVES, because {@see \Uhifadhi\Bundle\AreaBundle\Model\ZoneImportResult}
 * has both: the zones that were made and the property their names came out of,
 * then every property the file carried that the import read past. A file exported
 * from a desktop GIS is full of description, altitudeMode and merge fields, and
 * somebody who is not told they were ignored will go looking for them.
 *
 * THERE IS NO `--dry-run`, and adding one is not this command's call. The import
 * validates as it writes — the zone invariant is measured against what is already
 * stored, so a feature is checked against the features written before it inside
 * the import's own transaction — and the only honest dry run is one the service
 * offers itself. Asking for one from out here would mean either a second code
 * path through the core or a transaction rolled back behind the service's back,
 * and a scheme that reported clean through one path and refused through the other
 * would be worse than no dry run at all. Until the service offers it, a developer
 * imports into a throwaway area.
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
            // The import's own sentence, which names the offending feature. A
            // refused scheme leaves the area with exactly the zones it had.
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title(\sprintf('Zones imported into %s', $area->getName() ?? 'the area'));

        $io->text(\sprintf('Read from <info>%s</info>.', $result->fileName));
        $io->listing($result->zoneNames);
        $io->text(\sprintf('Names came from the <info>%s</info> property.', $result->nameProperty));

        $io->text([] === $result->ignoredProperties
            ? 'The file carried no other properties.'
            : \sprintf('Properties read past and not stored: %s.', implode(', ', $result->ignoredProperties)));

        $io->success(\sprintf('%d zone(s) created in %s.', $result->count(), $area->getName() ?? 'the area'));

        return Command::SUCCESS;
    }
}
