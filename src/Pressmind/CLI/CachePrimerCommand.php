<?php

namespace Pressmind\CLI;

use Pressmind\ORM\Object\MediaObject;
use Pressmind\Registry;

/**
 * Cache Primer Command
 *
 * Lists all pretty URLs for primary media types.
 * Can prepend a base URL (e.g. --base-url=https://example.com).
 *
 * Usage:
 *   php cli/cache_primer.php
 *   php cli/cache_primer.php --base-url=https://example.com
 *   php bin/cache-primer --base-url=https://example.com
 */
class CachePrimerCommand extends AbstractCommand
{
    protected function execute(): int
    {
        $config = Registry::getInstance()->get('config');
        $baseUrl = $this->getOption('base-url', '');

        $mediaObjects = MediaObject::listAll(
            ['id_object_type in(' . implode(',', $config['data']['primary_media_type_ids']) . ')']
        );

        foreach ($mediaObjects as $mediaObject) {
            $mo = new MediaObject($mediaObject->id);
            $prettyUrl = $mo->getPrettyUrl();
            $this->output->writeln($baseUrl . $prettyUrl, null);
        }

        return 0;
    }
}
