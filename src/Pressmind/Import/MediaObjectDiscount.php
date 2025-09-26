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
        if(!is_array($data) || count($data) === 0){
            return;
        }
        $this->_log[] = ' Importer::_MediaObjectDiscount(' . $id_media_object . '): converting manual price infos to touristic data';
        $validData = [];
        foreach($data as $discount){
            if(!empty($discount->value)){
                $validData[] = $discount;
            }
        }
        if(count($validData) === 0){
            $this->_log[] = ' Importer::_MediaObjectDiscount(' . $id_media_object . '): no valid prices found';
            return;
        }
        foreach($validData as $discount){
            $ManualDiscount = new ManualDiscount();
            $ManualDiscount->id = $discount->id;
            $ManualDiscount->id_media_object = $id_media_object;
            $ManualDiscount->travel_date_from = !empty($discount->travel_date_from) ? new \DateTime($discount->travel_date_from) : null;
            $ManualDiscount->travel_date_to = !empty($discount->travel_date_to) ? new \DateTime($discount->travel_date_to) : null;
            $ManualDiscount->booking_date_from =!empty($discount->booking_date_from) ? new \DateTime($discount->booking_date_from) : null;
            $ManualDiscount->booking_date_to = !empty($discount->booking_date_to) ? new \DateTime($discount->booking_date_to) : null;
            $ManualDiscount->description = $discount->description;
            $ManualDiscount->value = $discount->value;
            $ManualDiscount->type = $discount->type;
            $ManualDiscount->agency = $discount->agency;
            $ManualDiscount->create();
        }
    }
}
