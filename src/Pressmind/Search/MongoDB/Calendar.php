<?php

namespace Pressmind\Search\MongoDB;

use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Booking;
use Pressmind\ORM\Object\Touristic\Date;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\Registry;
use Pressmind\Search\CheapestPrice;

class Calendar extends AbstractIndex
{

    public function __construct() {
        parent::__construct();
    }

    /**
     * sets a index to a collection
     * @param $collection_name
     * @param $key
     * @throws \MongoDB\Driver\Exception\Exception
     */
    public function createCollectionIndex($collection_name){

        //  $this->db->$collection_name->createIndex( ['prices.price_total' => 1]);

    }

    public function createCalendars()
    {
        $ids = [];
        foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
            $mediaObjects = MediaObject::listAll(['id_object_type' => $id_object_type]);
            foreach ($mediaObjects as $mediaObject) {
                echo $mediaObject->id."\n";
                $ids[] = $mediaObject->id;
            }
        }
        $this->upsertMediaObject($ids);
    }


    /**
     * @param string|int|array $id_media_objects
     * @throws \Exception
     */
    public function upsertMediaObject($id_media_objects)
    {
        if(!is_array($id_media_objects)){
            $id_media_objects = [$id_media_objects];
        }
        $mediaObjects = MediaObject::listAll(['id' => ['in', implode(',', $id_media_objects)]]);

        $ids = [];
        foreach($mediaObjects as $mediaObject){
            if(empty($this->_config['search']['build_for'][$mediaObject->id_object_type])){
                continue;
            }
            foreach ($this->_config['search']['build_for'][$mediaObject->id_object_type] as $build_info) {
                foreach($this->_agencies as $agency) {
                    $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                    $collection = $this->db->$collection_name;
                    $collection->deleteMany(['id_media_object' => $mediaObject->id]);
                    $this->createCalendar($mediaObject->id, $build_info['language'], $build_info['origin'], $agency);
                    $ids[] = $mediaObject->id;
                }
            }
        }

        // remove the possible delta to aware the consistent
        foreach($id_media_objects as $id_media_object){
            if(in_array($id_media_object, $ids)){
                continue;
            }
            foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
                foreach ($build_infos as $build_info) {
                    foreach($this->_agencies as $agency) {
                        $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                        $collection = $this->db->$collection_name;
                        $collection->deleteMany(['id_media_object' => $mediaObject->id]);
                    }
                }
            }
        }
    }


    /**
     * @param string|int|array $id_media_object
     * @throws \Exception
     */
    public function deleteMediaObject($id_media_objects)
    {
        if(!is_array($id_media_objects)){
            $id_media_objects = array_map('intval', explode(',', $id_media_objects));
        }
        foreach($id_media_objects as $id_media_object){
            foreach ($this->_config['search']['build_for'] as $id_object_type => $build_infos) {
                foreach ($build_infos as $build_info) {
                    foreach($this->_agencies as $agency) {
                        $collection_name = $this->getCollectionName($build_info['origin'], $build_info['language'], $agency);
                        $collection = $this->db->$collection_name;
                        $collection->deleteMany(['id_media_object' => $id_media_object]);
                    }
                }
            }
        }
    }

    /**
     * @param int $origin
     * @param string $language
     * @param string $agency
     * @return string
     */
    public function getCollectionName($origin = 0, $language = null, $agency = null){
        return 'calendar_' . (!empty($language) ? $language.'_' : '') . 'origin_' . $origin.(!empty($agency) ? '_agency_'. $agency: '');
    }


    /**
     * @param int $idMediaObject
     * @param string $language
     * @param string $origin
     * @param string $agency
     * @return void
     * @throws \Exception
     */
    public function createCalendar($idMediaObject, $language, $origin, $agency = null)
    {
        /** @var Pdo $db */
        $db = Registry::getInstance()->get('db');
        $config = $this->_config['search']['touristic'];
        $this->mediaObject = new MediaObject($idMediaObject, true, true);
        $collection_name = $this->getCollectionName($origin, $language, $agency);
        $collection = $this->db->$collection_name;
        $items = [];
        foreach ($config['occupancies'] as $occupancy) {
            $query = 'select distinct 
                        IFNULL(transport_type, \'-\') as transport_type,
                        IFNULL(transport_1_airport, \'-\') as transport_1_airport,
                        IFNULL(transport_2_airport, \'-\') as transport_2_airport,
                        IFNULL(id_startingpoint_option, \'-\') as id_startingpoint_option
                      from pmt2core_cheapest_price_speed 
                      where 
                        id_media_object = :id_media_object
                        AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                        AND (option_occupancy = :occupancy ) 
                        AND id_origin = :id_origin';
            $values = [
                ':id_media_object' => $this->mediaObject->id,
                ':id_origin' => $origin,
                ':occupancy' => $occupancy,
            ];

            $results = $db->fetchAll($query, $values);

            if(!is_null($results)) {
                foreach($results as $result){
                    $items[] = [
                        'occupancy' => $occupancy,
                        'transport_type' => $result->transport_type == '-' ? null : $result->transport_type,
                        'transport_1_airport' => $result->transport_1_airport == '-' ? null : $result->transport_1_airport,
                        'transport_2_airport' => $result->transport_2_airport == '-' ? null : $result->transport_2_airport,
                        'id_startingpoint_option' => $result->id_startingpoint_option == '-' ? null : $result->id_startingpoint_option,
                        'durations' => []
                    ];
                }
            }
            if(empty($items)){
                continue;
            }
            foreach($items as $k => $item){
                $query = 'select 
                    distinct duration, id_booking_package, id_housing_package from pmt2core_cheapest_price_speed 
                    where 
                        id_media_object = :id_media_object
                        AND (earlybird_discount = 0 OR earlybird_discount_date_to >= NOW())
                        AND (option_occupancy = :occupancy ) 
                        AND id_origin = :id_origin';
                $values = [
                    ':id_media_object' => $this->mediaObject->id,
                    ':id_origin' => $origin,
                    ':occupancy' => $occupancy
                ];
                if(!empty($item['transport_type'])){
                    $query .= ' AND transport_type = :transport_type';
                    $values[':transport_type'] = $item['transport_type'];
                }
                if(!empty($item['transport_1_airport'])){
                    $query .= ' AND transport_1_airport = :transport_1_airport';
                    $values[':transport_1_airport'] = $item['transport_1_airport'];
                }
                if(!empty($item['transport_2_airport'])){
                    $query .= ' AND transport_2_airport = :transport_2_airport';
                    $values[':transport_2_airport'] = $item['transport_2_airport'];
                }
                if(!empty($item['id_startingpoint_option'])){
                    $query .= ' AND id_startingpoint_option = :id_startingpoint_option';
                    $values[':id_startingpoint_option'] = $item['id_startingpoint_option'];
                }
                $query .= ' order by duration ASC';
                $results = $db->fetchAll($query, $values);
                if(!is_null($results)) {
                    foreach($results as $result){
                        $items[$k]['durations'][] = [
                            'duration' => $result->duration,
                            'id_booking_package' => $result->id_booking_package,
                            'id_housing_package' => $result->id_housing_package
                        ];
                    }
                }

            }
        }

        foreach($items as $item){
            foreach($item['durations'] as $duration){
                $document = new \stdClass();
                $document->_id = uniqid();
                $document->id_object_type = $this->mediaObject->id_object_type;
                $document->id_media_object = $this->mediaObject->id;
                $document->occupancy = $item['occupancy'];
                $document->transport_type = $item['transport_type'];
                $document->airport = $item['transport_1_airport'];
                $document->id_startingpoint_option = $item['id_startingpoint_option'];
                $document->booking_package = (new Booking\Package($duration['id_booking_package'], false))->toStdClass(false);
                $document->housing_package = (new Package($duration['id_housing_package'], false))->toStdClass(false);
                $filter = new CheapestPrice();
                $filter->occupancies_disable_fallback = false;
                $filter->occupancies = [$item['occupancy']];
                $filter->id_housing_package = $duration['id_housing_package'];
                $filter->id_booking_package = $duration['id_booking_package'];
                $filter->id_startingpoint_option = $item['id_startingpoint_option'];
                if(!empty($item['transport_type'])) {
                    $filter->transport_types = [$item['transport_type']];
                }
                $offers = $this->mediaObject->getCheapestPrices($filter, ['date_departure' => 'ASC', 'price_total' => 'ASC']);
                /**
                 * @var \Pressmind\ORM\Object\CheapestPriceSpeed[] $date_to_cheapest_price
                 */
                $date_to_cheapest_price = [];
                foreach($offers as $offer){
                    // if the date has multiple prices, display only the cheapest
                    if (!empty($date_to_cheapest_price[$offer->date_departure->format('Y-m-d')]) &&
                        $offer->price_total < $date_to_cheapest_price[$offer->date_departure->format('Y-m-d')]->price_total
                    ) {
                        // set the cheapier price
                        $date_to_cheapest_price[$offer->date_departure->format('Y-m-d')] = $offer;
                    } elseif (empty($date_to_cheapest_price[$offer->date_departure->format('Y-m-d')])
                    ){
                        $date_to_cheapest_price[$offer->date_departure->format('Y-m-d')] = $offer;
                    }
                }
                $from = new \DateTime(array_key_first($date_to_cheapest_price));
                $from->modify('first day of this month');
                $to = new \DateTime(array_key_last($date_to_cheapest_price));
                $to->modify('first day of next month');
                $document->from = $from->format(DATE_RFC3339_EXTENDED);
                $document->to = $to->format(DATE_RFC3339_EXTENDED);
                $document->bookable_date_count = 0;
                foreach (new \DatePeriod($from, new \DateInterval('P1M'), $to) as $dt) {
                    $days = range(1, $dt->format('t'));
                    $month = new \stdClass();
                    $month->year = $dt->format('Y');
                    $month->month = $dt->format('n');
                    $month->days = [];
                    $month->is_bookable = false;
                    foreach ($days as $day) {
                        $date = new \stdClass();
                        if(empty($day)){
                            $month->dates[] = $date;
                            continue;
                        }
                        $current_date = $dt->format('Y-m-').str_pad($day, 2, '0', STR_PAD_LEFT);
                        $date->date = $current_date;
                        if (!empty($date_to_cheapest_price[$current_date])) {
                            $month->is_bookable = true;
                            $document->bookable_date_count++;
                            /**
                             * @var CheapestPriceSpeed $cheapestPriceReduced
                             */
                            $cheapestPriceReduced = new \stdClass();
                            $cheapestPriceReduced->id = $date_to_cheapest_price[$current_date]->getId();
                            $cheapestPriceReduced->id_media_object = $date_to_cheapest_price[$current_date]->id_media_object;
                            $cheapestPriceReduced->id_booking_package = $date_to_cheapest_price[$current_date]->id_booking_package;
                            $cheapestPriceReduced->id_housing_package = $date_to_cheapest_price[$current_date]->id_housing_package;
                            $cheapestPriceReduced->id_date = $date_to_cheapest_price[$current_date]->id_date;
                            $cheapestPriceReduced->option_occupancy = $date_to_cheapest_price[$current_date]->option_occupancy;
                            $cheapestPriceReduced->transport_type = $date_to_cheapest_price[$current_date]->transport_type;
                            $cheapestPriceReduced->price_total = $date_to_cheapest_price[$current_date]->price_total;
                            $cheapestPriceReduced->date_arrival = $date_to_cheapest_price[$current_date]->date_arrival->format(DATE_RFC3339_EXTENDED);
                            $cheapestPriceReduced->date_departure = $date_to_cheapest_price[$current_date]->date_departure->format(DATE_RFC3339_EXTENDED);
                            $cheapestPriceReduced->earlybird_discount_date_to = $date_to_cheapest_price[$current_date]->earlybird_discount_date_to;
                            $cheapestPriceReduced->guaranteed = $date_to_cheapest_price[$current_date]->guaranteed;
                            $cheapestPriceReduced->saved = $date_to_cheapest_price[$current_date]->saved;
                            $cheapestPriceReduced->id_option = $date_to_cheapest_price[$current_date]->id_option;
                            $cheapestPriceReduced->earlybird_discount_date_to = $date_to_cheapest_price[$current_date]->earlybird_discount_date_to;
                            $cheapestPriceReduced->earlybird_discount = $date_to_cheapest_price[$current_date]->earlybird_discount;
                            $cheapestPriceReduced->earlybird_discount_f = $date_to_cheapest_price[$current_date]->earlybird_discount_f;
                            $cheapestPriceReduced->earlybird_name = $date_to_cheapest_price[$current_date]->earlybird_name;
                            $cheapestPriceReduced->price_regular_before_discount = $date_to_cheapest_price[$current_date]->price_regular_before_discount;
                            $cheapestPriceReduced->price_option_pseudo = $date_to_cheapest_price[$current_date]->price_option_pseudo;
                            $cheapestPriceReduced->id_transport_1 = $date_to_cheapest_price[$current_date]->id_transport_1;
                            $cheapestPriceReduced->id_transport_2 = $date_to_cheapest_price[$current_date]->id_transport_2;
                            $date->cheapest_price = $cheapestPriceReduced;
                        }
                        $month->days[] = $date;
                    }
                    $document->month[] = $month;
                }
                try{
                    $collection->updateOne(['_id' => $document->_id], ['$set' => $document], ['upsert' => true]);
                }catch (\Exception $exception){
                    print_r($document);
                }
            }
        }
    }


}
