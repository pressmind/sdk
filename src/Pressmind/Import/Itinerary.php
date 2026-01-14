<?php


namespace Pressmind\Import;


use Exception;
use Pressmind\HelperFunctions;
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
        parent::__construct();
        $this->_id_media_object = intval($id_media_object);
    }

    public function import()
    {
        $client = new Client();
        $id_media_object = $this->_id_media_object;
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importItinerary(' . $id_media_object . '):  Starting import';
        try {
            $response = $client->sendRequest('Itinerary', 'get', ['id_media_object' => (int)$id_media_object]);
            $this->_checkApiResponse($response);
        } catch (Exception $e) {
            $this->_errors[] = $e->getMessage();
            return;
        }
        if(!empty($response->result)) {
            switch ($response->result->type) {
                case 'itinerary_to_touristic':
                    $this->_importVariants($response->result->variants, $id_media_object);
                    break;
                case 'itinerary_dateless':
                    $this->_importSteps($response->result->steps, $id_media_object);
                    break;
            }
        }
    }

    private function _importVariants($data, $id_media_object)
    {
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importItinerary(' . $id_media_object . '): Found ' . count($data) . ' itinerary variants';
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importItinerary(' . $id_media_object . '):  Deleting existing itinerary_variants';
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
                $img_sort = 0;
                foreach ($step->document_media_objects as &$document_media_object) {
                    foreach($document_media_object->urls as $key => $url) {
                        if($key == "web") {
                            $document_media_object->tmp_url = str_replace('&v=web', '', $url);
                        }
                    }
                    unset($document_media_object->urls);
                    unset($document_media_object->uri);
                    $document_media_object->download_successful = false;
                    $document_media_object->sort = $img_sort;
                    $img_sort++;
                }
            }
            try {
                $variant = new Variant();
                $variant->setReadRelations(true);
                $variant->fromStdClass($result);
                $variant->create();
                $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importItinerary(' . $id_media_object . '): Variant ' . $variant->getId() . ' created';
            } catch (Exception $e) {
                $this->_errors[] = 'Failed to create Itinerary Variant: ' . $e->getMessage();
            }
        }
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::importItinerary(' . $id_media_object . '): Import done.';
    }

    private function _importSteps($data, $id_media_object)
    {
        $old_steps = Step::listAll(['id_media_object' => $id_media_object]);
        foreach ($old_steps as $old_step) {
            $old_step->delete(true);
        }
        $c = 0;
        foreach ($data as &$step) {
            $step->order = $c;
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
            $img_sort = 0;
            foreach ($step->document_media_objects as &$document_media_object) {
                $mapped_object = new \stdClass();
                $mapped_object->id_step = $step->id;
                $mapped_object->id_media_object = $id_media_object;
                $mapped_object->copyright = $document_media_object->copyright;
                $mapped_object->caption = $document_media_object->caption;
                $mapped_object->alt = $document_media_object->alt;
                $mapped_object->uri = $document_media_object->uri;
                $mapped_object->title = $document_media_object->title;
                $mapped_object->download_successful = false;
                $mapped_object->tmp_url = $document_media_object->links->web->url;
                $mapped_object->mime_type = $document_media_object->links->web->mime_type;
                $mapped_object->file_name = 'itinerary_'.$step->id . '_' . $document_media_object->id_media_object .'.'. HelperFunctions::getExtensionFromMimeType($document_media_object->links->web->mime_type);
                $mapped_object->code = $document_media_object->code;
                $mapped_object->name = $document_media_object->name;
                $mapped_object->tags = $document_media_object->tags;
                $mapped_object->width = $document_media_object->width;
                $mapped_object->height = $document_media_object->height;
                $mapped_object->filesize = $document_media_object->filesize;
                $mapped_object->sort = $img_sort;
                $document_media_object = $mapped_object;
                $img_sort++;
            }

            $new_step = new Step();
            $new_step->setReadRelations(true);
            $new_step->fromStdClass($step);
            $new_step->id_media_object = $id_media_object;
            $new_step->create();
            $c++;
        }
    }
}
