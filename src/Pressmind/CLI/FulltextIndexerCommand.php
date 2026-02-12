<?php

namespace Pressmind\CLI;

use Pressmind\ORM\Object\MediaObject;

/**
 * Fulltext Indexer Command
 *
 * Creates search index for Media Objects (all or by comma-separated IDs).
 *
 * Usage:
 *   php cli/fulltext_indexer.php
 *   php cli/fulltext_indexer.php 123456,78901234
 *   php cli/fulltext_indexer.php help
 */
class FulltextIndexerCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $idsArg = $this->getArgument(0);

        if ($idsArg === 'help' || $this->hasOption('help') || $this->hasOption('h')) {
            $this->printHelp();
            return 0;
        }

        $mediaObjects = $this->resolveMediaObjects($idsArg);

        foreach ($mediaObjects as $mediaObject) {
            $mediaObject->createSearchIndex();
        }

        return 0;
    }

    /**
     * Resolves media objects from argument (null = all, or comma-separated IDs).
     *
     * @return MediaObject[]
     */
    private function resolveMediaObjects(?string $idsArg): array
    {
        if ($idsArg === null || $idsArg === '') {
            return MediaObject::listAll();
        }

        $ids = array_map('trim', explode(',', $idsArg));
        $mediaObjects = [];
        foreach ($ids as $id) {
            $mediaObjects[] = new MediaObject((int) $id);
        }
        return $mediaObjects;
    }

    private function printHelp(): void
    {
        $helptext = "usage: fulltext_indexer.php [<single id or commaseparated list of ids>]\n";
        $helptext .= "Example usages:\n";
        $helptext .= "php fulltext_indexer.php\n";
        $helptext .= "php fulltext_indexer.php 123456,78901234 <single or multiple ids allowed>\n";
        $this->output->write($helptext, null);
    }
}
