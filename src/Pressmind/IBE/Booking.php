<?php


namespace Pressmind\IBE;


use Exception;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking\Package;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Insurance;
use Pressmind\ORM\Object\Touristic\Option;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Transport;

#[\AllowDynamicProperties]
class Booking
{
    /**
     * @var int
     */
    public $id_media_object;
    /**
     * @var int
     */
    public $id_booking_package;
    /**
     * @var int
     */
    public $id_date;
    /**
     * @var int
     */
    public $id_season;
    /**
     * @var array
     */
    public $ids_housing_options;
    /**
     * @var int
     */
    public $id_housing_package;
    /**
     * @var int
     */
    public $id_transport_way_1;
    /**
     * @var int
     */
    public $id_transport_way_2;
    /**
     * @var int
     */
    public $id_option;
    /**
     * @var string
     * the type of the request (booking|request|basic_request)
     */
    public $request_type;

    /**
     * @var Package
     */
    public $booking_package;

    /**
     * @var Option[]
     */
    public $available_housing_options_for_date;

    /**
     * @var array
     */
    public $settings;

    /**
     * @var Date
     */
    public $date = null;

    /**
     * Booking constructor.
     * @param array $data
     */
    public function __construct($data)
    {
        $this->id_media_object = isset($data['params']['imo']) ? $data['params']['imo'] : null;
        $this->id_booking_package =  isset($data['params']['idbp']) ? $data['params']['idbp'] : null;
        $this->id_housing_package =  isset($data['params']['idhp']) ? $data['params']['idhp'] : null;
        $this->id_date =  isset($data['params']['idd']) ? $data['params']['idd'] : null;
        $this->ids_housing_options =  isset($data['params']['iho']) ? $data['params']['iho'] : null;
        $this->id_season =  isset($data['params']['ids']) ? $data['params']['ids'] : null;
        $this->id_option =  isset($data['params']['ido']) ? $data['params']['ido'] : null;
        $this->id_transport_way_1 =  isset($data['params']['idt1']) ? explode(',',$data['params']['idt1']) : null;
        $this->id_transport_way_2 =  isset($data['params']['idt2']) ? explode(',', $data['params']['idt2']) : null;
        $this->transport_type =  isset($data['params']['tt']) ? $data['params']['tt'] : null;
        $this->request_type =  isset($data['params']['t']) ? $data['params']['t'] : null;
        $this->settings = isset($data['settings']) ? $data['settings'] : null;
    }

    /**
     * @return Package
     * @throws Exception
     */
    public function getBookingPackage() {
        if(is_null($this->booking_package)) {
            $this->booking_package = new Package($this->id_booking_package, true);
        }
        return $this->booking_package;
    }

    /**
     * @param null $pId
     * @return Date
     * @throws Exception
     */
    public function getDate($pId = null)
    {
        if (is_null($pId) && !is_null($this->id_date)) {
            $pId = $this->id_date;
        } else if (is_null($pId) && is_null($this->id_date)) {
            throw new Exception('Es konnte kein gültiges Reisedatum gefunden werden.');
        }
        if (is_null($this->date)) {
            /**@var Date $date * */
            if (is_null($this->id_booking_package)) {
                $this->date = new Date($pId);
                $this->id_booking_package = $this->date->id_booking_package;
                //$this->getBookingPackage();
            } else {
                $this->date = $this->getBookingPackage()->findObjectInArray('dates', 'id', $pId);
            }
        }
        return $this->date;
    }

    /**
     * @return Insurance[]
     * @throws Exception
     */
    public function getInsurances() {
        if(!is_null($this->getBookingPackage()->insurance_group)) {
            $all_available_insurances = $this->getBookingPackage()->insurance_group->insurances;
            foreach ($all_available_insurances as $insurance) {
                $insurance->price_tables;
            }
        } else {
            $all_available_insurances = [];
        }
        if(isset($this->settings['steps']['insurances']['show_no_insurance_option']['value']) && $this->settings['steps']['insurances']['show_no_insurance_option']['value'] == true ) {
            $no_insurance_pricetable = new Insurance\PriceTable();
            $no_insurance = new Insurance();
            $no_insurance->name = isset($this->settings['steps']['insurances']['no_insurance_title']['value']) ? $this->settings['steps']['insurances']['no_insurance_title']['value'] : 'Keine Versicherung gewünscht';
            $no_insurance->description = isset($this->settings['steps']['insurances']['no_insurance_text']['value']) ? $this->settings['steps']['insurances']['no_insurance_text']['value'] : 'Ich wünsche keine Versicherung und trage die Kosten im Falle von Krankheit oder Stornierung selbst.';
            $no_insurance->id = 0;
            $no_insurance_pricetable->travel_date_from = $this->getDate()->departure->format('Y-m-d h:i:s');
            $no_insurance_pricetable->travel_date_to = $this->getDate()->arrival->format('Y-m-d h:i:s');
            $no_insurance_pricetable->booking_date_from = '1970-01-01 00:00:00';
            $no_insurance_pricetable->booking_date_to = '2999-01-01 00:00:00';
            $no_insurance_pricetable->price = 0;
            $no_insurance_pricetable->unit = 'per_person';
            $no_insurance_pricetable->travel_price_min = 0;
            $no_insurance_pricetable->travel_price_max = 1000000;
            $no_insurance_pricetable->age_from = 0;
            $no_insurance_pricetable->age_to = 999;
            $no_insurance_pricetable->id = 0;
            $no_insurance->price_tables = array($no_insurance_pricetable);

            $all_available_insurances[] = $no_insurance;
        }

        return $all_available_insurances;
    }

    /**
     * @return ValidPriceTablePackage[]
     * @throws Exception
     */
    public function getInsurancePriceTablePackages() {
        $validPriceTablePackages= [];
        if(!is_null($this->getBookingPackage()->insurance_group)) {
            $Package = new Insurance\Package();
            $validPriceTablePackages = $Package->getValidPackagesByInsuranceGroup($this->getBookingPackage()->insurance_group->id);
        }
        return $validPriceTablePackages;
    }

    /**
     * @param null $pId
     * @return \Pressmind\ORM\Object\Touristic\Housing\Package
     * @throws Exception
     */
    public function getHousingPackage($pId = null) {
        if(is_null($pId) && !is_null($this->id_housing_package)) {
            $pId = $this->id_housing_package;
        }
        /**@var \Pressmind\ORM\Object\Touristic\Housing\Package $housingPackage**/
        $housingPackage = $this->booking_package->findObjectInArray('housing_packages', 'id', $pId);
        //print_r($this->booking_package->toStdClass());
        return $housingPackage;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function getAllHousingOptions() {
        if(!is_null($this->id_housing_package)) {
            return $this->getHousingPackage()->options;
        }
        return null;
    }

    /**
     * Gets housingoptions from GET parameters ido=123456 or iho[123456]=x
     * if iho is set housingoptions are multiplied by amount (iho[123456]=2 will add 2 options for 123456)
     * @return Option[]
     * @throws Exception
     */
    public function getHousingOptions() {
        $housing_options = array();
        if(!is_null($this->id_option)) {
            $housing_option_ids = array($this->id_option => 1);
        } else if (!is_null($this->ids_housing_options)) {
            $housing_option_ids = $this->ids_housing_options;
        } else {
            return $this->getAllHousingOptions();
        }
        foreach($housing_option_ids as $id_housing_option => $amount) {
            for($i=0;$i<$amount;$i++) {
                $housingOption = $this->getHousingPackage()->findObjectInArray('options', 'id', $id_housing_option);
                $housing_options[] = $housingOption;
            }
        }

        return $housing_options;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getAvailableHousingOptionsForDate() {
        if(is_null($this->available_housing_options_for_date)) {
            $this->available_housing_options_for_date = $this->getDate()->getHousingOptions($this->id_housing_package);
        }
        return $this->available_housing_options_for_date;
    }

    /**
     * @return Startingpoint
     * @throws Exception
     */
    public function getStartingpoint() {
        return $this->getDate()->startingpoint;
    }

    /**
     * @deprecated
     * @param null $pIdDate
     * @return Transport[]
     * @throws Exception
     */
    public function getTransports($pIdDate = null)
    {
        $transports = array();
        foreach ($this->getDate($pIdDate)->transports as $transport) {
            if($transport->type == $this->transport_type){
                if(!is_null($this->id_transport_way_1) && !is_null($this->id_transport_way_2)) {
                    if(($transport->id == $this->id_transport_way_1 || $transport->id == $this->id_transport_way_2)) {
                        $transports[] = $transport;
                    }
                }else{
                    $transports[] = $transport;
                }
            }
        }
        return $transports;
    }

    /**
     * @deprecated
     * @return bool
     * @throws Exception
     */
    public function hasPickServices() {
        $has_pickup_services = false;
        if(!is_null($this->getStartingpoint())) {
            foreach ($this->getStartingpoint()->options as $option) {
                if ($option->is_pickup_service == true) {
                    $has_pickup_services = true;
                }
            }
        }
        return $has_pickup_services;
    }

    /**
     * @deprecated
     * @return bool
     * @throws Exception
     */
    public function hasStartingPoints() {
        $has_starting_points = false;
        if(!is_null($this->getStartingpoint())) {
            foreach ($this->getStartingpoint()->options as $option) {
                if ($option->is_pickup_service == false) {
                    $has_starting_points = true;
                }
            }
        }

        if(!is_null($this->getTransports())) {
            foreach ($this->getTransports() as $transport) {
                if (empty($transport->id_starting_point) == false) {
                    $has_starting_points = true;
                }
            }
        }

        return $has_starting_points;
    }


    /**
     * @param \DateTime $reservation_date_from
     * @param \DateTime $reservation_date_to
     * @param string $season
     * @param string $agency
     * @return Option[]
     * @throws Exception
     */
    public function getAllAvailableExtras($reservation_date_from = null, $reservation_date_to = null, $season = null, $agency = null) {
        $extras = $this->getBookingPackage()->extras;
        $tickets = $this->getBookingPackage()->tickets;
        $sightseeings = $this->getBookingPackage()->sightseeings;
        $all_extras = array_merge($sightseeings, array_merge($extras, $tickets));
        $valid_extras = [];
        if($season == '-'){
            $season = null;
        }
        foreach ($all_extras as $extra){
            if(!empty($extra->agencies) && !empty($agency)){
                if(!in_array($agency, explode(',', $extra->agencies))){
                    continue;
                }
            }
            if($extra->season == '-'){
                $extra->season = null;
            }
            if(!empty($extra->reservation_date_from) && !empty($extra->reservation_date_to)){
                if($extra->reservation_date_from->format('Ymd') == $reservation_date_from->format('Ymd') &&
                   $extra->reservation_date_to->format('Ymd') == $reservation_date_to->format('Ymd')
                ){
                    $valid_extras[] = $extra;
                }
            }elseif($extra->season == $season || empty($extra->season)){
                $valid_extras[] = $extra;
            }
        }
        return $valid_extras;
    }

    /**
     * @param Option[] $extras
     * @param float $duration
     * @param int $nights
     * @return Option[]
     */
    public function calculateExtras($extras, $duration, $nights){
        foreach($extras as $extra){
            $extra->calculatePrice($duration, $nights);
        }
        return $extras;
    }

    /**
     * @return \Pressmind\ORM\Object\Touristic\EarlyBirdDiscountGroup|null
     */
    public function getEarlyBird(){
        $group = null;
        if(empty($this->date->early_bird_discount_group)){
            return $group;
        }
        $MediaObject = new MediaObject();
        $item = $MediaObject->getEarlyBirdDiscount($this->date->early_bird_discount_group->items, $this->date);
        if(!empty($item)){
            $group = $this->date->early_bird_discount_group->toStdClass();
            $group->items = [$item->toStdClass()];
        }
        return $group;
    }
}
