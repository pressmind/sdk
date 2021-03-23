<?php


namespace Pressmind\Import;


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
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '):  Deleting existing itinerary_variants';
        /** @var Variant[] $variants */
        $variants = Variant::listAll(['id_media_object' => $id_media_object]);
        foreach ($variants as $variant) {
            $variant->delete(true);
        }
        try {
            $response = $client->sendRequest('Itinerary', 'get', ['id_media_object' => (int)$id_media_object]);
            $this->_checkApiResponse($response);
        } catch (\Exception $e) {
            $this->_errors[] = $e->getMessage();
        }
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Found ' . count($response->result) . ' itinerary variants';
        foreach ($response->result as $result) {
            $result->id_media_object = $id_media_object;
            $id_booking_package = $result->id_booking_packages;
            $result->id_booking_package = $id_booking_package;
            unset($result->id_booking_packages);
            foreach ($result->steps as &$step) {
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
            $variant = new Variant();
            $variant->setReadRelations(true);
            $variant->fromStdClass($result);
            $variant->create();
            $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Variant ' . $variant->getId() . ' created';
        }
        $this->_log[] = ' Importer::importItinerary(' . $id_media_object . '): Import done.';
    }
}
