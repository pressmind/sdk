<?php

namespace Pressmind\REST\Controller;
use Exception;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\Registry;
use Pressmind\Search\CalendarFilter;
use Pressmind\Search\CheapestPrice;
use Pressmind\ORM\Object\Airport;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;
use Pressmind\ORM\Object\Touristic\Transport;
use stdClass;

class Entrypoint
{
    /**
     * @param $params
     * @return array
     */
    public function getBookingLink($params){
        $config = Registry::getInstance()->get('config');
        try {
            set_error_handler(function($errno, $errstr, $errfile, $errline) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            });
            if(empty($params['id_offer']) || !is_numeric($params['id_offer'])){
                throw new Exception('Parameter id_offer is missing or not an integer');
            }
            if(empty($params['pax']) || !is_numeric($params['pax'])){
                throw new Exception('Parameter pax is missing or not an integer');
            }
            $CheapestPrice = new \Pressmind\ORM\Object\CheapestPriceSpeed($params['id_offer']);
            if(!$CheapestPrice->isValid()){
                throw new Exception('No cheapest price for offer id '.$params['id_offer'].' available');
            }
            $url  = \Pressmind\ORM\Object\MediaObject::getBookingLink($CheapestPrice, null, null, null, true);
            $url .= '&px='.$params['pax'];
            if(!empty($CheapestPrice->startingpoint_id_city)){
                $url .= '&idspc='.$CheapestPrice->startingpoint_id_city;
            }
            if(!empty($params['ida']) && $params['ida'] != 'null') {
                $url .= '&ida='.trim($params['ida']);
            }
            $curl = curl_init();
            $request = new stdClass();
            $request->checks = [];
            $check = new stdClass();
            $check->id_offer = $CheapestPrice->id;
            $check->quantity = $params['pax'];
            $check->quantity_unit = 'pax';
            $request->checks[] = $check;
            curl_setopt_array($curl, array(
                CURLOPT_URL => $config['ibe3']['url'].'/api/external/checkAvailability',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($request),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json'
                ),
                CURLOPT_USERAGENT => __CLASS__.':'.__FUNCTION__
            ));
            $raw = curl_exec($curl);
            $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            if($status_code != 200){
                throw new Exception('Invalid response code: '.$status_code);
            }
            $response = json_decode($raw);
            if(json_last_error() > 0){
                throw new Exception('Invalid json response');
            }
            $payload = $response->data[0];
            $payload->url = $url;
            if(empty($response)){
                throw new Exception('No availability check response. '.$raw);
            }
        } catch (Exception $e) {
            return [
                'msg' => $e->getMessage()
            ];
        }
        return [
            'error' => false,
            'payload' => $payload,
            'msg' => null
        ];
    }

    /**
     * @param $params
     * @return array
     */
    public function calendarMap($params) : array {
        try {
            $map = [
                'startingpoint_id_cities' => [],
                'housing_package_id_names' => [],
                'id_housing_packages' => [],
                'airports' => [],
                'durations' => [],
            ];
            if(empty($params['id_media_object']) || !is_numeric($params['id_media_object'])){
                throw new Exception('Parameter id_media_object is missing or not an integer');
            }
            $HousingPackages = Package::listAll(['id_media_object' => (int)$params['id_media_object']]);
            /**
             * @var Package[] $HousingPackages
             */
            foreach($HousingPackages as $HousingPackage){
                unset($HousingPackage->options);
                $tmp = new stdClass();
                $tmp->value = $HousingPackage->name;
                $map['housing_package_id_names'][md5((string)$HousingPackage->name)] = $tmp;
                $map['id_housing_packages'][$HousingPackage->id] = $tmp;
            }

            $TransportOptions = Transport::listAll(['id_media_object' => (int)$params['id_media_object']]);
            /**
             * @var Transport[] $TransportOptions
             */
            $id_starting_points = [];
            foreach($TransportOptions as $TransportOption) {
                if (!empty($TransportOption->id_starting_point)){
                    $id_starting_points[] = $TransportOption->id_starting_point;
                }
                $_3LCode = substr($TransportOption->code, 0, 3);
                $Airport = Airport::getByIata($_3LCode);
                $tmp = new stdClass();
                $tmp->value = empty($Airport->name) ? 'Unbekannt' : $Airport->name;
                $map['airports'][$_3LCode] = $tmp;
            }
            $StartingPointOptions = Option::listAll('id_startingpoint in ("'.implode('","', $id_starting_points).'")', ['zip' => 'ASC', 'city' => 'ASC']);
            /**
             * @var Option[] $StartingPointOptions
             */
            foreach($StartingPointOptions as $StartingPointOption){
                $tmp = new stdClass();
                $tmp->value = trim($StartingPointOption->zip.' '.$StartingPointOption->city);
                $map['startingpoint_id_cities'][md5($StartingPointOption->city)] = $tmp;
            }
            $BookingPackages = \Pressmind\ORM\Object\Touristic\Booking\Package::listAll(['id_media_object' => (int)$params['id_media_object']]);
            /**
             * @var \Pressmind\ORM\Object\Touristic\Booking\Package[] $BookingPackages
             */
            foreach($BookingPackages as $BookingPackage) {
                $tmp = new stdClass();
                $tmp->value = $BookingPackage->duration.($BookingPackage->duration > 1 ? ' Tage ': ' Tag ').$BookingPackage->name;
                $map['durations'][$BookingPackage->duration] = $tmp;
            }
        } catch (Exception $e) {
            return [
                'msg' => $e->getMessage()
            ];
        }
        return [
            'error' => false,
            'payload' => $map,
            'msg' => null
        ];
    }

    /**
     * @param $params
     * @return array
     */
    public function calendar($params) : array {
        try {
            if(empty($params['id_media_object']) || !is_numeric($params['id_media_object'])){
                throw new Exception('Parameter id_media_object is missing or not an integer');
            }
            $PriceFilter = new CheapestPrice();
            $MediaObject = new \Pressmind\ORM\Object\MediaObject($params['id_media_object']);
            if(!$MediaObject->isValid()){
                throw new Exception('Media Object with id '.$params['id_media_object'].' not found');
            }
            $CheapestPrice = $MediaObject->getCheapestPrice($PriceFilter);
            $CalendarFilter = new CalendarFilter();
            if(!$CalendarFilter->initFromGet()){
                throw new Exception('Could not initialize CalendarFilter please set some of these parameters: id, id_booking_package, id_housing_package, housing_package_code_ibe, occupancy,transport_type, duration, airport, startingpoint_id_city, agency');
            }
            $CalendarFilter->occupancy = $CheapestPrice->option_occupancy;
            $custom_query = [];
            if(!empty($params['filter_transport_type'])){
                $custom_query['transport_type'] = $params['filter_transport_type'];
            }
            if(!empty($params['agency'])) {
                $CalendarFilter->agency = $params['agency'];
            }
            $Calendar = $MediaObject->getCalendar($CalendarFilter, 3,0, null, $custom_query);
            if(!empty($params['filter_transport_type'])){
                unset($Calendar->filter['transport_types']);
            }
            if ( !empty($MediaObject->touristic_base->booking_on_request)) {
                throw new Exception('booking_on_request is active');
            }
            if (empty($CheapestPrice)) {
                throw new Exception('No cheapest price available');
            }
            if ($CheapestPrice->is_virtual_created_price) {
                throw new Exception('Cheapest price is a virtual created price');
            }
        } catch (Exception $e) {
            return [
                'error' => true,
                'payload' => null,
                'msg' => $e->getMessage()
            ];
        }
        return [
            'error' => false,
            'payload' => $Calendar,
            'msg' => null
        ];
    }

    /**
     * Cheapest Price for a Media Object
     * @param $params
     * @return array
     */
    public function price($params) : array {
        try {
            if(empty($params['id_media_object']) || !is_numeric($params['id_media_object'])){
                throw new Exception('Parameter id_media_object is missing or not an integer');
            }
            $Filter = new CheapestPrice();
            $Filter->initFromGet();
            $Filter->initFromGetShortCodes();
            $MediaObject = new \Pressmind\ORM\Object\MediaObject($params['id_media_object']);
            if(!$MediaObject->isValid()){
                throw new Exception('Media Object with id '.$params['id_media_object'].' not found');
            }
            $CheapestPrice = $MediaObject->getCheapestPrice($Filter);
        } catch (Exception $e) {
           return [
                'msg' => $e->getMessage()
            ];
        }
        return [
            'error' => false,
            'payload' => $CheapestPrice,
            'msg' => null
        ];
    }

    /**
     * @return string
     */
    public function listAll(){
        return 'listAll() not implemented yet';
    }

}