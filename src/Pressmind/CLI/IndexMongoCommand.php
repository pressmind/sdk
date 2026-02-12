<?php

namespace Pressmind\CLI;

use Pressmind\Search\MongoDB\Indexer;

/**
 * MongoDB Best-Price Index Command
 *
 * Manages the MongoDB best price search index.
 *
 * Usage:
 *   php cli/index_mongo.php all
 *   php cli/index_mongo.php mediaobject 12345,12346
 *   php cli/index_mongo.php destroy 12345,12346
 *   php cli/index_mongo.php indexes
 *   php cli/index_mongo.php flush
 *   php cli/index_mongo.php create_collections
 *   php cli/index_mongo.php remove_temp_collections
 *   php cli/index_mongo.php help
 */
class IndexMongoCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $subcommand = $this->getArgument(0);
        $idsArg = $this->getArgument(1);

        if ($subcommand === null || $subcommand === 'help' || $this->hasOption('help') || $this->hasOption('h')) {
            $this->printHelp();
            return 0;
        }

        $indexer = new Indexer();

        switch ($subcommand) {
            case 'all':
                $indexer->createIndexes();
                break;
            case 'mediaobject':
                $ids = $this->parseIds($idsArg);
                if (empty($ids)) {
                    $this->output->error('mediaobject requires comma-separated id(s) as second argument.');
                    return 1;
                }
                $indexer->upsertMediaObject($ids);
                break;
            case 'destroy':
                $ids = $this->parseIds($idsArg);
                if (empty($ids)) {
                    $this->output->error('destroy requires comma-separated id(s) as second argument.');
                    return 1;
                }
                $indexer->deleteMediaObject($ids);
                break;
            case 'indexes':
                $indexer->createCollectionIndexes();
                break;
            case 'flush':
                $indexer->flushCollections();
                break;
            case 'create_collections':
                $indexer->createCollectionsIfNotExists();
                break;
            case 'remove_temp_collections':
                $count = $indexer->removeTempCollections();
                $this->output->writeln($count . ' collection deleted', null);
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
        $helptext = "usage: index_mongo.php [all | mediaobject | destroy | indexes | create_collections] [<single id or commaseparated list of ids>]\n";
        $helptext .= "Example usages:\n";
        $helptext .= "php index_mongo.php all\n";
        $helptext .= "php index_mongo.php mediaobject 12345,12346  <single/multiple ids allowed  / imports one or more media objects>\n";
        $helptext .= "php index_mongo.php destroy 12345,12346  <single/multiple ids allowed  / removes this objects from the mongodb best price cache>\n";
        $helptext .= "php index_mongo.php indexes  <sets the required indexes for each collection>\n";
        $helptext .= "php index_mongo.php flush  <flushes all collections>\n";
        $helptext .= "php index_mongo.php create_collections  <creates collections for each index definition configured in pm-config>\n";
        $helptext .= "php index_mongo.php create_zip_index  <creates creates the collection departure_startingpoint_zip_index_ >\n";
        $helptext .= "php index_mongo.php remove_temp_collections  <creates deletes collections with temp_*. prefix>\n";
        $this->output->write($helptext, null);
    }
}
