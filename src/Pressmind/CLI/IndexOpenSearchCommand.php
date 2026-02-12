<?php

namespace Pressmind\CLI;

use Pressmind\Search\OpenSearch;
use Pressmind\Search\OpenSearch\Indexer;

/**
 * OpenSearch Fulltext Index Command
 *
 * Manages the OpenSearch fulltext index for Pressmind Media Objects.
 *
 * Usage:
 *   php cli/index_opensearch.php all
 *   php cli/index_opensearch.php mediaobject 12345,12346
 *   php cli/index_opensearch.php search "search term" de
 *   php cli/index_opensearch.php create_index_templates
 *   php cli/index_opensearch.php help
 */
class IndexOpenSearchCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $subcommand = $this->getArgument(0);
        $arg1 = $this->getArgument(1);
        $arg2 = $this->getArgument(2);

        if ($subcommand === null || $subcommand === 'help' || $this->hasOption('help') || $this->hasOption('h')) {
            $this->printHelp();
            return 0;
        }

        switch ($subcommand) {
            case 'all':
                $indexer = new Indexer();
                $indexer->createIndexes();
                break;
            case 'mediaobject':
                $ids = $this->parseIds($arg1);
                if (empty($ids)) {
                    $this->output->error('mediaobject requires comma-separated id(s) as second argument.');
                    return 1;
                }
                $indexer = new Indexer();
                $indexer->upsertMediaObject($ids);
                break;
            case 'search':
                $term = $arg1 ?? '';
                $language = $arg2 ?? null;
                $openSearch = new OpenSearch($term, $language, 100);
                $ids = $openSearch->getResult();
                if (is_array($ids) && count($ids) > 0) {
                    $this->output->writeln("Found " . count($ids) . " results for term '" . $term . "'", null);
                    foreach ($ids as $id) {
                        $this->output->writeln((string) $id, null);
                    }
                } else {
                    $this->output->writeln("No results found for term '" . $term . "'", null);
                }
                break;
            case 'create_index_templates':
                $indexer = new Indexer();
                $indexer->createIndexTemplates();
                $indexes = $indexer->getIndexes();
                if (is_array($indexes) && count($indexes) > 0) {
                    $this->output->writeln('Current indexes:', null);
                    foreach ($indexes as $index) {
                        $indexName = $index['index'] ?? $index['index_name'] ?? '?';
                        $status = $index['status'] ?? '?';
                        $health = $index['health'] ?? '?';
                        $this->output->writeln('  - ' . $indexName . ' | status ' . $status . ' | health: ' . $health, null);
                    }
                } else {
                    $this->output->writeln('No indexes found.', null);
                }
                break;
            default:
                $this->printHelp();
                return 0;
        }

        return 0;
    }

    /**
     * @return int[]
     */
    private function parseIds(?string $idsArg): array
    {
        if ($idsArg === null || $idsArg === '') {
            return [];
        }
        return array_map('intval', array_map('trim', explode(',', $idsArg)));
    }

    private function printHelp(): void
    {
        $helptext = "usage: index_opensearch.php [all | mediaobject | destroy | indexes | create_collections] [<single id or commaseparated list of ids>]\n";
        $helptext .= "Example usages:\n";
        $helptext .= "php index_opensearch.php all\n";
        $helptext .= "php index_opensearch.php mediaobject 12345,12346  <single/multiple ids allowed  / imports one or more media objects>\n";
        $helptext .= "php index_opensearch.php search \"search term\" de  <searches for a term in the OpenSearch index and returns the ids of the matching media objects>\n";
        $helptext .= "php index_opensearch.php create_index_templates <creates the index templates for the OpenSearch indexes and sets the index>\n";
        $this->output->write($helptext, null);
    }
}
