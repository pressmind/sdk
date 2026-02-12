<?php

namespace Pressmind\CLI;

use Exception;
use Pressmind\Registry;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Route;

/**
 * Rebuild Routes Command
 *
 * Rebuilds pretty URLs / routes for all Media Objects.
 *
 * Usage:
 *   php cli/rebuild_routes.php
 *   php bin/rebuild-routes
 */
class RebuildRoutesCommand extends AbstractCommand
{
    protected function execute(): int
    {
        /** @var \Pressmind\DB\Adapter\Pdo $db */
        $db = Registry::getInstance()->get('db');
        /** @var MediaObject[] $mediaObjects */
        $mediaObjects = MediaObject::listAll();

        foreach ($mediaObjects as $mediaObject) {
            $db->delete('pmt2core_routes', ['id_media_object = ?', $mediaObject->getId()]);
            try {
                $newRoutes = $mediaObject->buildPrettyUrls();
                foreach ($newRoutes as $newRoute) {
                    $route = new Route();
                    $route->language = 'de';
                    $route->id_object_type = $mediaObject->id_object_type;
                    $route->id_media_object = $mediaObject->getId();
                    $route->route = $newRoute;
                    $route->create();
                }
            } catch (Exception $e) {
                $this->output->error('ERROR for MediaObject ID ' . $mediaObject->getId() . ': ' . $e->getMessage());
            }
        }

        return 0;
    }
}
