<?php

namespace Pressmind\CLI;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

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
        foreach ($this->getMediaObjectIds() as $id) {
            $mediaObject = $this->createMediaObject($id);
            $this->output->writeln('deleting ' . $id . ' from cache', null);
            $mediaObject->removeFromCache();
            $this->output->writeln('adding ' . $id . ' to cache', null);
            $mediaObject->addToCache($id);
            $this->output->writeln($id . ' added to cache', null);
            unset($mediaObject);
        }

        return 0;
    }

    protected function getMediaObjectIds(): iterable
    {
        $mediaObject = new MediaObject();
        $primaryKey = $mediaObject->getDbPrimaryKey();
        $tableName = $mediaObject->getDbTableName();
        $db = Registry::getInstance()->get('db');
        $rows = $db->fetchAll(
            sprintf('SELECT `%1$s` FROM `%2$s` ORDER BY `%1$s` ASC', $primaryKey, $tableName)
        );
        unset($mediaObject);

        foreach ($rows as $row) {
            yield (int) (is_array($row) ? $row[$primaryKey] : $row->{$primaryKey});
        }
    }

    protected function createMediaObject(int $id): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->setId($id);
        return $mediaObject;
    }
}
