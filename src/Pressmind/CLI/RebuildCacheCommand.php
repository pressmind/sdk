<?php

namespace Pressmind\CLI;

use Pressmind\ORM\Object\MediaObject;

/**
 * Rebuild Cache Command
 *
 * Rebuilds the object cache: removes and re-adds all Media Objects to cache.
 *
 * Usage:
 *   php cli/rebuild_cache.php
 *   php bin/rebuild-cache
 */
class RebuildCacheCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $mediaObjects = MediaObject::listAll();

        foreach ($mediaObjects as $mediaObject) {
            $this->output->writeln('deleting ' . $mediaObject->getId() . ' from cache', null);
            $mediaObject->removeFromCache();
            $this->output->writeln('adding ' . $mediaObject->getId() . ' to cache', null);
            $mediaObject->addToCache($mediaObject->getId());
            $this->output->writeln($mediaObject->getId() . ' added to cache', null);
        }

        return 0;
    }
}
