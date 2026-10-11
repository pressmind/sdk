<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
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
        $media_object = new \Pressmind\ORM\Object\MediaObject();
        $media_object->id = $data->id_media_object;
        $media_object->id_pool = $data->id_pool;
        $media_object->id_object_type = $data->id_media_objects_data_type;
        $media_object->id_client = 0;
        $media_object->id_season = $data->id_saison;
        $media_object->id_brand = $data->id_brand;
        $media_object->id_insurance_group = $data->id_insurance_group;
        $media_object->name = $data->name;
        $media_object->code = $data->code;
        $media_object->tags = $data->tags;
        $media_object->visibility = $data->visibility;
        $media_object->state = $data->state;
        $media_object->valid_from = $data->valid_from;
        $media_object->valid_to = $data->valid_to;
        $media_object->is_reference = $data->is_reference;
        $media_object->reference_media_object = $data->reference_media_object;
        $seasonFrom = ($data->diffent_saison_from != '0000-00-00') ? \DateTime::createFromFormat('Y-m-d', $data->diffent_saison_from) : false;
        $media_object->different_season_from = $seasonFrom instanceof \DateTime ? $seasonFrom->setTime(0,0,0) : null;
        $seasonTo = ($data->different_saison_to != '0000-00-00') ? \DateTime::createFromFormat('Y-m-d', $data->different_saison_to) : false;
        $media_object->different_season_to = $seasonTo instanceof \DateTime ? $seasonTo->setTime(23,59,59) : null;
        $media_object->booking_type = $data->booking_type == 'on-request' ? 'request' : $data->booking_type;
        $media_object->booking_link = $data->booking_link;
        $media_object->sales_priority = $data->sales_prio;
        $media_object->sales_position = $data->position;
        $media_object->recommendation_rate = $data->recommendation_rate;
        $media_object->section_control = self::extractSectionControl($data->data ?? null);
        /*try {
            $old_media_object = new \Pressmind\ORM\Object\MediaObject();
            $old_media_object->read($data->id_media_object);
            $old_media_object->delete();
            unset($old_media_object);
        } catch (Exception $e) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $media_object->getId() . '):  Deleting old object failed';
        }*/
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object';
        try {
            $media_object->create();
        } catch (Exception $e) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating media object failed: ' . $e->getMessage();
        }
        //$media_object->setReadRelations(true);
        //$media_object->readRelations();

        /*try {
            $media_object->createSearchIndex();
        } catch (Exception $e) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $media_object->getId() . '):  Creating search index failed: ' . $e->getMessage();
            $this->_errors[] = 'Importer::importMediaObject(' . $media_object->getId() . '):  Creating search index failed: ' . $e->getMessage();
        }*/
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importMediaObject(' . $media_object->getId() . '):  CheapestPriceSpeed table updated';

        return $media_object;
    }

    /**
     * Section control flags (Webcore v2-34) of all controlled fields: [var_name => ['possibly_outdated' => bool]].
     * Returns null if no field is controlled or the response has no section control information.
     * @param mixed $fields data[] of the Text/getById response
     * @return array|null
     */
    public static function extractSectionControl($fields): ?array
    {
        if (!is_array($fields)) {
            return null;
        }
        $result = [];
        foreach ($fields as $field) {
            if (!is_object($field) || ($field->section_control ?? false) !== true || empty($field->var_name)) {
                continue;
            }
            $result[HelperFunctions::human_to_machine($field->var_name)] = [
                'possibly_outdated' => ($field->possibly_outdated ?? false) === true,
            ];
        }
        return $result === [] ? null : $result;
    }
}
