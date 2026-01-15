<?php

namespace Pressmind\CLI;

use Pressmind\System\TouristicOrphans;

/**
 * CLI command to find orphaned products.
 *
 * Orphans are visible Media Objects without Cheapest-Price entries,
 * which therefore do not appear in search results.
 *
 * Usage:
 *   php bin/touristic-orphans [--object-types=1212,1214] [--visibility=30] [--details=ID]
 *
 * Options:
 *   --object-types=IDS   Comma-separated list of object type IDs (default: from config)
 *   --visibility=INT     Visibility value (default: 30)
 *   --details=ID         Shows details for a specific Media Object
 *   --stats-only         Shows only statistics, no individual list
 *   -n, --non-interactive  No interactive prompts
 */
class TouristicOrphansCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $this->output->newLine();
        $this->output->writeln('=== Pressmind SDK Touristic Orphans Check ===', 'cyan');
        $this->output->newLine();

        // Details mode for single Media Object
        if ($this->hasOption('details')) {
            return $this->showDetails((int)$this->getOption('details'));
        }

        $orphansFinder = new TouristicOrphans();

        // Object types from option or config
        $objectTypes = $this->getObjectTypes($orphansFinder);
        if (empty($objectTypes)) {
            $this->output->error('No object types configured. Use --object-types=1212,1214');
            return 1;
        }

        $visibility = (int)$this->getOption('visibility', 30);

        $this->output->writeln('Checking object types: ' . implode(', ', $objectTypes));
        $this->output->writeln('Visibility: ' . $visibility);
        $this->output->newLine();

        // Show statistics
        $this->showStatistics($orphansFinder, $objectTypes, $visibility);

        // With --stats-only don't show individual list
        if ($this->hasOption('stats-only')) {
            return 0;
        }

        // Find and list orphans
        return $this->listOrphans($orphansFinder, $objectTypes, $visibility);
    }

    /**
     * Gets object types from option or config.
     */
    private function getObjectTypes(TouristicOrphans $orphansFinder): array
    {
        if ($this->hasOption('object-types')) {
            $types = $this->getOption('object-types');
            return array_map('intval', explode(',', $types));
        }

        return $orphansFinder->getPrimaryMediaTypeIds();
    }

    /**
     * Shows orphan statistics.
     */
    private function showStatistics(TouristicOrphans $orphansFinder, array $objectTypes, int $visibility): void
    {
        $this->output->writeln('Statistics:', 'cyan');
        $this->output->writeln(str_repeat('-', 70));

        $stats = $orphansFinder->getStatistics($objectTypes, $visibility);

        // Table with statistics per object type
        $headers = ['Object Type', 'Name', 'Visible', 'With Prices', 'Orphans', '%'];
        $rows = [];

        foreach ($stats['by_object_type'] as $stat) {
            $rows[] = [
                $stat['id_object_type'],
                $stat['name'],
                $stat['visible_count'],
                $stat['with_prices_count'],
                $stat['orphans_count'],
                $stat['percentage_orphans'] . '%'
            ];
        }

        $this->output->table($headers, $rows);

        // Total
        $this->output->newLine();
        $this->output->writeln(sprintf(
            'Total: %d orphans of %d visible objects (%s%%)',
            $stats['total']['orphans_count'],
            $stats['total']['visible_count'],
            $stats['total']['percentage_orphans']
        ), 'yellow');
        $this->output->newLine();
    }

    /**
     * Lists all orphans.
     */
    private function listOrphans(TouristicOrphans $orphansFinder, array $objectTypes, int $visibility): int
    {
        $orphans = $orphansFinder->findOrphans($objectTypes, $visibility);

        if (empty($orphans)) {
            $this->output->success('No orphans found! All visible products have prices.');
            return 0;
        }

        $this->output->writeln('Orphans list:', 'cyan');
        $this->output->writeln(str_repeat('-', 70));

        $headers = ['ID', 'Name', 'Type', 'BP', 'Dates', 'Options'];
        $rows = [];

        foreach ($orphans as $orphan) {
            $name = mb_strlen($orphan->name) > 40 
                ? mb_substr($orphan->name, 0, 37) . '...' 
                : $orphan->name;
            
            $rows[] = [
                $orphan->id,
                $name,
                $orphan->id_object_type,
                $orphan->booking_packages_count,
                $orphan->dates_count,
                $orphan->options_count
            ];
        }

        $this->output->table($headers, $rows);

        $this->output->newLine();
        $this->output->warning(sprintf('%d orphans found.', count($orphans)));
        $this->output->info('Show details with: php bin/touristic-orphans --details=ID');

        return count($orphans) > 0 ? 1 : 0;
    }

    /**
     * Shows details for a specific Media Object.
     */
    private function showDetails(int $idMediaObject): int
    {
        $this->output->writeln('Details for Media Object ID: ' . $idMediaObject, 'cyan');
        $this->output->writeln(str_repeat('-', 70));

        $orphansFinder = new TouristicOrphans();
        $details = $orphansFinder->getOrphanDetails($idMediaObject);

        if (isset($details['error'])) {
            $this->output->error($details['error']);
            return 1;
        }

        // Basic info
        $mo = $details['media_object'];
        $this->output->writeln('Name: ' . $mo->name);
        $this->output->writeln('Code: ' . ($mo->code ?? '-'));
        $this->output->writeln('Object Type: ' . $mo->id_object_type);
        $this->output->writeln('Visibility: ' . $mo->visibility);
        $this->output->writeln('Cheapest Price Entries: ' . $details['cheapest_price_count']);
        $this->output->newLine();

        // Diagnosis
        $diagnosis = $details['diagnosis'];
        $this->output->writeln('Diagnosis:', 'cyan');
        
        foreach ($diagnosis['issues'] as $issue) {
            $this->output->writeln('  - ' . $issue, 'red');
        }
        
        if (!empty($diagnosis['recommendations'])) {
            $this->output->newLine();
            $this->output->writeln('Recommendations:', 'yellow');
            foreach ($diagnosis['recommendations'] as $rec) {
                $this->output->writeln('  -> ' . $rec);
            }
        }

        $this->output->newLine();
        $this->output->writeln('Summary:', 'cyan');
        $summary = $diagnosis['summary'];
        $this->output->writeln('  Booking Packages: ' . $summary['booking_packages']);
        $this->output->writeln('  Travel Dates (total): ' . $summary['dates_total']);
        $this->output->writeln('  Travel Dates (future): ' . $summary['dates_future']);
        $this->output->writeln('  Options (direct): ' . $summary['options_direct']);
        $this->output->writeln('  Options (Booking Package): ' . $summary['options_booking_package']);

        // List Booking Packages
        if (!empty($details['booking_packages'])) {
            $this->output->newLine();
            $this->output->writeln('Booking Packages:', 'cyan');
            
            $headers = ['ID', 'Name', 'Duration', 'IBE Type'];
            $rows = [];
            foreach ($details['booking_packages'] as $bp) {
                $rows[] = [
                    substr($bp->id, 0, 8) . '...',
                    mb_strlen($bp->name) > 30 ? mb_substr($bp->name, 0, 27) . '...' : $bp->name,
                    $bp->duration ?? '-',
                    $bp->ibe_type ?? '-'
                ];
            }
            $this->output->table($headers, $rows);
        }

        return 0;
    }
}
