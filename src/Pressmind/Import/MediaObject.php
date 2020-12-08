<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\Log\Writer;
use Pressmind\ORM\Object\Route;
use Pressmind\Registry;
use stdClass;

class MediaObject extends AbstractImport
{

    /**
     * @param stdClass $data
     */
    public function import($data)
    {
        $db = Registry::getInstance()->get('db');

        $media_object = new \Pressmind\ORM\Object\MediaObject();
        $media_object->id = $data->id_media_object;
        $media_object->id_pool = $data->id_pool;
        $media_object->id_object_type = $data->id_media_objects_data_type;
        $media_object->id_client = 0;
        $media_object->id_season = $data->id_saison;
        $media_object->id_brand = $data->id_brand;
        $media_object->name = $data->name;
        $media_object->code = $data->code;
        $media_object->tags = $data->tags;
        $media_object->visibility = $data->visibility;
        $media_object->state = $data->state;
        $media_object->valid_from = $data->valid_from;
        $media_object->valid_to = $data->valid_to;
        $media_object->is_reference = $data->is_reference;
        $media_object->reference_media_object = $data->reference_media_object;
        $media_object->different_season_from = $data->diffent_saison_from;
        $media_object->different_season_to = $data->different_saison_to;
        try {
            $old_media_object = new \Pressmind\ORM\Object\MediaObject();
            $old_media_object->read($data->id_media_object);
            $old_media_object->delete();
            unset($old_media_object);
        } catch (Exception $e) {
            $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Deleting old object failed';
        }
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object';
        try {
            $media_object->create();
        } catch (Exception $e) {
            $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object failed: ' . $e->getMessage();
        }
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Deleting Route entries';
        $db->delete('pmt2core_routes', ['id_media_object = ?', $media_object->getId()]);
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Inserting Route entries';
        $media_object->setReadRelations(true);
        $media_object->readRelations();
        try {
            $urls = $media_object->buildPrettyUrls();
            foreach ($urls as $url) {
                $route = new Route();
                $route->id_media_object = $media_object->getId();
                $route->id_object_type = $media_object->id_object_type;
                $route->route = $url;
                $route->language = 'de';
                $route->create();
            }
        } catch (Exception $e) {
            $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating routes failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating routes failed: ' . $e->getMessage();
        }
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Routes updated';
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Deleting CheapestPriceSpeed entries';
        /**@var Pdo $db**/
        $db->delete('pmt2core_cheapest_price_speed', ['id_media_object = ?', $media_object->getId()]);
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Inserting CheapestPriceSpeed entries';
        try {
            $media_object->insertCheapestPrice();
        } catch (Exception $e) {
            $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating cheapest price failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating cheapest price failed: ' . $e->getMessage();
        }
        try {
            $media_object->createSearchIndex();
        } catch (Exception $e) {
            $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating search index failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating search index failed: ' . $e->getMessage();
        }
        $this->_log[] = ' Importer::importMediaObject(' . $media_object->getId() . '):  CheapestPriceSpeed table updated';
    }
}
