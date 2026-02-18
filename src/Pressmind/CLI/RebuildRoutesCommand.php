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
 * When primary_media_type_ids is configured, only primary types get routes; non-primary routes are removed.
 * Channel strategy requires a full re-import to rebuild routes (no API data in this command).
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
        $config = Registry::getInstance()->get('config');
        $primary_ids = $config['data']['primary_media_type_ids'] ?? [];

        if (!empty($primary_ids)) {
            $db->execute(
                'DELETE FROM pmt2core_routes WHERE id_object_type NOT IN (' . implode(',', array_map('intval', $primary_ids)) . ')'
            );
        }

        /** @var MediaObject[] $mediaObjects */
        $mediaObjects = MediaObject::listAll();

        foreach ($mediaObjects as $mediaObject) {
            if (!empty($primary_ids) && !$mediaObject->isAPrimaryType()) {
                continue;
            }
            $db->delete('pmt2core_routes', ['id_media_object = ?', $mediaObject->getId()]);
            try {
                $newRoutes = $mediaObject->buildPrettyUrls();
                if (empty($newRoutes) && $this->_isChannelStrategy($config, $mediaObject->id_object_type)) {
                    $this->output->warning(
                        'MediaObject #' . $mediaObject->getId() . ' (type ' . $mediaObject->id_object_type . '): URL strategy "channel" requires a full re-import to rebuild routes. Run the importer instead.'
                    );
                }
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

    /**
     * @param array $config
     * @param int $id_object_type
     * @return bool
     */
    private function _isChannelStrategy($config, $id_object_type): bool
    {
        $prettyUrlConfig = $config['data']['media_types_pretty_url'] ?? [];
        if (empty($prettyUrlConfig)) {
            return false;
        }
        $first = reset($prettyUrlConfig);
        $isLegacy = !isset($first['id_object_type']);
        if ($isLegacy) {
            return ($prettyUrlConfig[$id_object_type]['strategy'] ?? '') === 'channel';
        }
        foreach ($prettyUrlConfig as $v) {
            if ((int) $v['id_object_type'] === (int) $id_object_type) {
                return ($v['strategy'] ?? '') === 'channel';
            }
        }
        return false;
    }
}
