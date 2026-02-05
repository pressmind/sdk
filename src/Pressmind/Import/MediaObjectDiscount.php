<?php


namespace Pressmind\Import;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\ManualDiscount;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Option;

class MediaObjectDiscount extends AbstractImport
{
    /**
     * @param $data
     * @param $id_media_object
     * @param $import_type
     * @return void
     * @throws Exception
     */
    public function import($data, $id_media_object, $import_type)
    {
        $ManualDiscount = new ManualDiscount();
        $ManualDiscount->deleteByMediaObjectId($id_media_object);
        if (!is_array($data) || count($data) === 0) {
            return;
        }
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_MediaObjectDiscount(' . $id_media_object . '): converting manual price infos to touristic data';

        // Collect valid discounts for batch insert
        $discountObjects = [];
        foreach ($data as $discount) {
            if (!empty($discount->value)) {
                $discountObject = new ManualDiscount();
                $discountObject->id = $discount->id;
                $discountObject->id_media_object = $id_media_object;
                $discountObject->travel_date_from = !empty($discount->travel_date_from) ? new \DateTime($discount->travel_date_from) : null;
                $discountObject->travel_date_to = !empty($discount->travel_date_to) ? new \DateTime($discount->travel_date_to) : null;
                $discountObject->booking_date_from = !empty($discount->booking_date_from) ? new \DateTime($discount->booking_date_from) : null;
                $discountObject->booking_date_to = !empty($discount->booking_date_to) ? new \DateTime($discount->booking_date_to) : null;
                $discountObject->description = $discount->description;
                $discountObject->value = $discount->value;
                $discountObject->type = $discount->type;
                $discountObject->agency = $discount->agency;
                $discountObjects[] = $discountObject;
            }
        }

        if (count($discountObjects) === 0) {
            $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_MediaObjectDiscount(' . $id_media_object . '): no valid prices found';
            return;
        }

        // Batch insert all discounts with a single query
        ManualDiscount::batchCreate($discountObjects);
        $this->_log[] = $this->_getElapsedTimeAndHeap() . ' Importer::_MediaObjectDiscount(' . $id_media_object . '): batch created ' . count($discountObjects) . ' discounts';
    }
}
