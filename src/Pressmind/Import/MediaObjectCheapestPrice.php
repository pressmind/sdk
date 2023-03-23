<?php


namespace Pressmind\Import;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Option;

class MediaObjectCheapestPrice extends AbstractImport
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
        if(!is_array($data) || count($data) === 0){
            return;
        }
        $this->_log[] = ' Importer::_importMediaObjectCheapestPrice(' . $id_media_object . '): converting manual price infos to touristic data';
        \Pressmind\ORM\Object\MediaObject::deleteTouristic($id_media_object);
       foreach ($data as $priceObject) {
           if(empty($priceObject->price)){
               continue;
           }
           if($priceObject->valid_from == '1970-01-01 00:00:00'){
               $date  = new \DateTime();
               $date->setTime(0,0,0);
               $date->add(new \DateInterval('P1D'));
               $priceObject->valid_from = $date->format('Y-m-d 00:00:00');
           }
           if($priceObject->valid_to == '1970-01-01 00:00:00'){
               $date  = new \DateTime();
               $date->setTime(0,0,0);
               $date->add(new \DateInterval('P90D'));
               $priceObject->valid_to = $date->format('Y-m-d 00:00:00');
           }
           /**
            * @var \Pressmind\ORM\Object\Touristic\Booking\Package $BookingPackage
            */
           $BookingPackage = new \stdClass();
           $BookingPackage->id = uniqid();
           $BookingPackage->id_media_object = $id_media_object;
           $BookingPackage->duration = $priceObject->duration;
           $BookingPackage->price_mix = 'date_housing';
           $BookingPackage->is_virtual_created_price = true;
           /**
            * @var Date $Date
            */
           $Date = new \stdClass();
           $Date->id = uniqid();
           $Date->id_media_object = $id_media_object;
           $Date->id_booking_package = $BookingPackage->id;
           $Date->departure = new \DateTime($priceObject->valid_from);
           $Date->arrival = new \DateTime($priceObject->valid_to);
           $Date->season = 'A';
           $BookingPackage->dates = [$Date];
           /**
            * @var Option $Option
            */
           $Option = new \stdClass();
           $Option->id = uniqid();
           $Option->id_booking_package = $BookingPackage->id;
           $Option->id_media_object = $id_media_object;
           $Option->type = 'housing_option';
           $Option->occupancy_min = $priceObject->occupancy_min;
           $Option->occupancy_max = $priceObject->occupancy_max;
           $Option->occupancy = $priceObject->occupancy;
           $Option->price = $priceObject->price;
           $Option->price_pseudo = $priceObject->price_pseudo;
           $Option->name = $priceObject->description_1;
           $Option->description_long = $priceObject->description_2;
           $Option->season = 'A';
           /**
            * @var Package $HousingPackage
            */
           $HousingPackage = new \stdClass();
           $HousingPackage->id = uniqid();
           $HousingPackage->id_media_object = $id_media_object;
           $HousingPackage->id_booking_package = uniqid();
           $HousingPackage->nights = $priceObject->duration === 0 ? 0 : $priceObject->duration - 1;
           $HousingPackage->room_type = 'room';
           $HousingPackage->options = [$Option];
           $BookingPackage->housing_packages = [$HousingPackage];
           $FinalBookingPackage = new \Pressmind\ORM\Object\Touristic\Booking\Package();
           $FinalBookingPackage->fromStdClass($BookingPackage);
           $FinalBookingPackage->create();
        }
    }
}
