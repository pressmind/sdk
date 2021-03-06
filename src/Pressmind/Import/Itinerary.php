<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\Import;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Itinerary\Step;
use Pressmind\ORM\Object\Itinerary\Step\TextMediaObject;
use Pressmind\ORM\Object\Itinerary\Variant;
use Pressmind\REST\Client;

class Itinerary extends AbstractImport implements ImportInterface
{
    /**
     * @var integer
     */
    private $_id_media_object;

    /**
     * Itinerary constructor.
     * @param integer $id_media_object
     */
    public function __construct($id_media_object)
    {
        $this->_id_media_object = intval($id_media_object);
    }

    public function import()
    {
        $client = new Client();
        $id_media_object = $this->_id_media_object;
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '):  Starting import';
        try {
            $response = $client->sendRequest('Itinerary', 'get', ['id_media_object' => (int)$id_media_object]);
            $this->_checkApiResponse($response);
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            return;
        }
        switch ($response->result->type) {
            case 'itinerary_to_touristic':
                $this->_importVariants($response->result->variants, $id_media_object);
                break;
            case 'itinerary_dateless':
                $this->_importSteps($response->result->steps, $id_media_object);
                break;
        }
    }

    private function _importVariants($data, $id_media_object)
    {
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Found ' . count($data) . ' itinerary variants';
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '):  Deleting existing itinerary_variants';
        /** @var Variant[] $variants */
        $variants = Variant::listAll(['id_media_object' => $id_media_object]);
        foreach ($variants as $variant) {
            $variant->delete(true);
        }
        foreach ($data as $result) {
            $result->id_media_object = $id_media_object;
            $id_booking_package = $result->id_booking_packages;
            $result->id_booking_package = $id_booking_package;
            unset($result->id_booking_packages);
            foreach ($result->steps as &$step) {
                $new_text_objects = [];
                if(isset($step->text_media_objects)) {
                    $text_objects = $step->text_media_objects;
                    $step->text_media_objects = [];
                }
                foreach ($text_objects as $text_object) {
                    foreach ($text_object->media_objects as $text_media_object_id) {
                        $new_text_object = new TextMediaObject();
                        $new_text_object->id_media_object = $text_media_object_id;
                        $new_text_object->id_object_type = $text_object->id_object_type;
                        $new_text_object->var_name = $text_object->var_name;
                        $new_text_object->name = $text_object->name;
                        $new_text_objects[] = $new_text_object->toStdClass();
                        $importer = new Import('mediaobject');
                        $importer->importMediaObject($text_media_object_id, false);
                    }
                }
                $step->text_media_objects = $new_text_objects;
                if(is_a($step->document_media_objects, 'stdClass')) {
                    $step->document_media_objects = [$step->document_media_objects];
                }
                foreach ($step->sections as &$section) {
                    $id_section = $section->id;
                    $section->id_section = $id_section;
                    unset($section->id);
                }
                foreach ($step->document_media_objects as &$document_media_object) {
                    unset($document_media_object->urls);
                }
            }
            try {
                $variant = new Variant();
                $variant->setReadRelations(true);
                $variant->fromStdClass($result);
                $variant->create();
                $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Variant ' . $variant->getId() . ' created';
            } catch (Exception $e) {
                $this->_errors[] = 'Failed to create Itinerary Variant: ' . $e->getMessage();
            }
        }
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Import done.';
    }

    private function _importSteps($data, $id_media_object)
    {
        $old_steps = Step::listAll(['id_media_object' => $id_media_object]);
        foreach ($old_steps as $old_step) {
            $old_step->delete(true);
        }
        foreach ($data as &$step) {
            $new_text_objects = [];
            if(isset($step->text_media_objects)) {
                $text_objects = $step->text_media_objects;
                $step->text_media_objects = [];
            }
            foreach ($text_objects as $text_object) {
                foreach ($text_object->media_objects as $text_media_object_id) {
                    $new_text_object = new TextMediaObject();
                    $new_text_object->id_media_object = $text_media_object_id;
                    $new_text_object->id_object_type = $text_object->id_object_type;
                    $new_text_object->var_name = $text_object->var_name;
                    $new_text_object->name = $text_object->name;
                    $new_text_objects[] = $new_text_object->toStdClass();
                    $importer = new Import('mediaobject');
                    $importer->importMediaObject($text_media_object_id, false);
                }
            }
            $step->text_media_objects = $new_text_objects;
            if(is_a($step->document_media_objects, 'stdClass')) {
                $step->document_media_objects = [$step->document_media_objects];
            }
            foreach ($step->sections as &$section) {
                $id_section = $section->id;
                $section->id_section = $id_section;
                unset($section->id);
            }
            foreach ($step->document_media_objects as &$document_media_object) {
                unset($document_media_object->urls);
            }

            $new_step = new Step();
            $new_step->setReadRelations(true);
            $new_step->fromStdClass($step);
            $new_step->id_media_object = $id_media_object;
            $new_step->create();
        }
    }
}
