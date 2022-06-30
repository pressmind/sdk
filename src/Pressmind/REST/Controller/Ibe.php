<?php
namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\IBE\Booking;
use Pressmind\MVC\AbstractController;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;

class Ibe
{
    public $parameters;

    public function pressmind_ib3_v2_test($params)
    {
        return ['success' => true, 'msg' => 'Test erfolgreich', 'debug' => $params];
    }

    public function pressmind_ib3_v2_get_touristic_object($params)
    {
        $this->parameters = $params['data'];
        if(empty($this->parameters['params']['imo']) || empty($this->parameters['params']['idbp']) || empty($this->parameters['params']['idd'])){
            return ['success' => false, 'msg' => 'error: parameters are missing imo, idbp, idd', 'data' => null];
        }

        $settings = isset($this->parameters['settings']) ? $this->parameters['settings'] : null;
        $booking = new Booking($this->parameters);
        $mediaObject = new MediaObject($this->parameters['params']['imo']);
        $result = [];
        $image_info = null;
        $images = $mediaObject->getValueByTagName('pressmind-ib3.teaser-image');
        if(!empty($images[0])) {
            $image = $images[0];
            $image_info['uri'] = substr($image->getUri('teaser'), 0, 4) == 'http' ? $image->getUri('teaser') : WEBSERVER_HTTP . $image->getUri('teaser');
            $image_info['caption'] = $image->caption;
            $image_info['alt'] = $image->alt;
        }
        $destination_name = null;
        $destination_code = null;
        if(isset($settings['general']['destination_tag_name']['value'])) {
            $destinations = $mediaObject->getValueByTagName($settings['general']['destination_tag_name']['value']);
            if (!is_null($destinations)) {
                foreach ($destinations as $destination_array) {
                    $destination = new \Pressmind\ORM\Object\CategoryTree\Item($destination_array->id_item);
                    if (!empty($destination->code)) {
                        $destination_name = $destination->name;
                        $destination_code = $destination->code;
                    }
                }
            }
        }
        $travel_type_name = null;
        $travel_type_code = null;
        if(isset($settings['general']['travel_type_tag_name']['value'])) {
            $travel_types = $mediaObject->getValueByTagName($settings['general']['travel_type_tag_name']['value']);
            if (!is_null($travel_types)) {
                foreach ($travel_types as $travel_types_array) {
                    $travel_type = new \Pressmind\ORM\Object\CategoryTree\Item($travel_types_array->id_item);
                    $travel_type_name = $travel_type->name;
                    $travel_type_code = $travel_type->code;
                }
            }
        }
        $services_box_title = null;
        $services_box_content = null;
        if(!empty(strip_tags($mediaObject->getValueByTagName('pressmind-ib3.services-box-content')))){
            $services_box_content = $mediaObject->getValueByTagName('pressmind-ib3.services-box-content');
            if(!empty(strip_tags($mediaObject->getValueByTagName('pressmind-ib3.services-box-title')))){
                $services_box_title = strip_tags($mediaObject->getValueByTagName('pressmind-ib3.services-box-title'));
            }else{
                $services_box_title = 'Leistungen';
            }
        }
        $date = $booking->getDate();
        if(empty($date)){
            return ['success' => false, 'data' => null, 'code' => 'not_found', 'msg' => 'date not found, id: '.$this->parameters['params']['idd']];
        }
        $clean_date = $date->toStdClass();
        unset($clean_date->transports); // @TODO we have to clean up, the payload is fullblown with duplicated stuff
        $result['date'] = $clean_date;
        // @TODO, wenn need only one transport pair! there is no need to deliver other transports
        //$result['transports'] = $date->getTransports([0,2,3], array_filter([$booking->id_transport_way_1, $booking->id_transport_way_2]), array_filter([$booking->transport_type]));

        $use_transport_types = [];
        $use_ways = [];
        if(empty($booking->id_transport_way_1) || empty($booking->id_transport_way_2)){
            $use_transport_types = array_filter([$booking->transport_type]);
        }else{
            $use_ways = array_filter(array_merge($booking->id_transport_way_1, $booking->id_transport_way_2));
        }
        $result['transport_pairs'] = $date->getTransportPairs([0,2,3], $use_ways, $use_transport_types, 1);

        $starting_points_limit = 10;
        if(!is_null($settings)) {
            if(isset($settings['steps']['starting_points']['pagination_page_size']['value'])) {
                $starting_points_limit = $settings['steps']['starting_points']['pagination_page_size']['value'];
            }
        }

        $result['starting_points'] = ['total' => 0];
        $result['exit_points'] = ['total' => 0];
        if(!empty($booking->getDate()->id_starting_point)){
            // this way is soon deprecated, because its even better to use the startingpoint from the transport instead from the date
            $result['starting_points'] = $this->_getStartingPointOptionsForId($booking->getDate()->id_starting_point, 0, $starting_points_limit, !empty($this->parameters['params']['iic']) ? $this->parameters['params']['iic'] : null);
            $result['exit_points'] = $this->_getExitPointOptionsForId($booking->getDate()->id_starting_point, 0, $starting_points_limit, !empty($this->parameters['params']['iic']) ? $this->parameters['params']['iic'] : null);
        }elseif(!empty($result['transport_pairs'])){
            $result['starting_points'] = $this->_getStartingPointOptionsForId($result['transport_pairs'][0]['way1']->id_starting_point, 0, $starting_points_limit, !empty($this->parameters['params']['iic']) ? $this->parameters['params']['iic'] : null);
            $result['exit_points'] = $this->_getExitPointOptionsForId($result['transport_pairs'][0]['way2']->id_starting_point, 0, $starting_points_limit, !empty($this->parameters['params']['iic']) ? $this->parameters['params']['iic'] : null);
        }
        $result['has_pickup_services'] = $booking->hasPickServices();
        $result['has_starting_points'] = $result['starting_points']['total'] > 0;

        $result['has_seatplan'] = false;
        if(!empty($result['transport_pairs'])){
            $result['has_seatplan'] = !empty($result['transport_pairs'][0]['way1']->seatplan_required) || !empty($result['transport_pairs'][0]['way2']->seatplan_required);
        }

        $result['product'] = [
            'title' => !empty($mediaObject->getValueByTagName('pressmind-ib3.headline')) ? strip_tags($mediaObject->getValueByTagName('pressmind-ib3.headline')) : $mediaObject->name,
            'subtitle' => '',
            'description' => '',
            'name' => '',
            'code' => $mediaObject->code,
            'teaser_image' => $image_info,
            'destination' => array('name' => $destination_name, 'code' => $destination_code),
            'travel_type' => array('name' => $travel_type_name, 'code' => $travel_type_code),
            'hotel_trust_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text') : null,
            'trustbox_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.trustbox-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.trustbox-text') : null,
            'services_box_title' => $services_box_title,
            'services_box_content' => $services_box_content,
            'duration' => $booking->getBookingPackage()->duration,
            'transport_type' => !empty($booking->transport_type) ? $booking->transport_type : null,
            'price_mix' => $booking->getBookingPackage()->price_mix,
        ];

        $extras = $booking->getAllAvailableExtras($date->departure, $date->arrival, $date->season);
        $result['insurances'] = $booking->getInsurances();

        $predefined_options = [];

        if(isset($this->parameters['params']['iho'])) {
            foreach ($this->parameters['params']['iho'] as $key => $value) {
                $predefined_options[$key] = $value;
            }
        }

        if(isset($this->parameters['params']['ido'])) {
            $predefined_options[$this->parameters['params']['ido']] = 1;
        }

        if($booking->getBookingPackage()->price_mix == 'date_housing') {
            if (isset($this->parameters['params']['idhp'])) {
                // @TODO this not works...
                $housing_packages = [$booking->getHousingPackage($this->parameters['params']['idhp'])->toStdClass()];
            } else {
                $housing_packages = [];
                $housing_packages_list = $booking->getBookingPackage()->housing_packages;
                foreach ($housing_packages_list as $housing_package) {
                    $package = new Package($housing_package->id, true);
                    $valid_options = [];
                    foreach($package->options as $option){
                        $current_saison = trim($option->season);
                        if(in_array($current_saison, [$date->season, '-']) || empty($option->season)){
                            $valid_options[] = $option;
                        }
                    }
                    $package->options = $valid_options;
                    $housing_packages[] = $package;
                }
            }
        } else if($booking->booking_package->price_mix == 'date_transport') {
            $housing_package = new Package();
            $housing_package->name = !empty($booking_package->name) ? $booking_package->name : $result['product']['title'];
            $option = new \Pressmind\ORM\Object\Touristic\Option();
            $option->id = uniqid();
            $option->name = 'Teilnahmegebühr';
            $option->max_pax = 10;
            $option->min_pax = 1;
            $option->type = 'dummy';
            $option->occupancy_max = 10;
            $option->occupancy_min = 1;
            $option->occupancy = 1;
            $option->quota = 15;
            $option->price_due = 'person_stay';
            $option->price = $result['transport_pairs'][0]['way1']->price + $result['transport_pairs'][0]['way2']->price;
            $result['transport_pairs'][0]['way1']->price = $result['transport_pairs'][0]['way2']->price = 0;
            $housing_package->options = [$option];
            $housing_packages[] = $housing_package;
        } else {
            $housing_package = new Package();
            $housing_package->name = !empty($booking_package->name) ? $booking_package->name : $result['product']['title'];
            $housing_package->code_ibe = null;
            $options = [];
            foreach ($extras as $key => $extra) {
                $current_saison = trim($extra->season);
                if(!empty($current_saison)){
                    if(!in_array($current_saison, [$date->season, '-'])){
                        unset($extras[$key]);
                        continue;
                    }
                }
                $extra->occupancy_min = $extra->min_pax > 0 ? $extra->min_pax : 1;
                $extra->occupancy_max = $extra->max_pax > 0 ? $extra->max_pax : 10;
                $extra->occupancy = (isset($predefined_options[$extra->id]) && $predefined_options[$extra->id] > $extra->occupancy_min) ? $predefined_options[$extra->id] : $extra->occupancy_min;
                if ($extra->type == 'ticket' && $booking->booking_package->price_mix == 'date_ticket') {
                    $options[] = $extra;
                    unset($extras[$key]);
                }
                if ($extra->type == 'sightseeing' && $booking->booking_package->price_mix == 'date_sightseeing') {
                    $options[] = $extra;
                    unset($extras[$key]);
                }
                if ($extra->type == 'extra' && $booking->booking_package->price_mix == 'date_extra') {
                    $options[] = $extra;
                    unset($extras[$key]);
                }
            }
            $housing_package->options = $options;
            $housing_packages[] = $housing_package;
        }



        //$result['debug'] = $settings['steps']['starting_points']['pagination_page_size']['value'];
        $result['housing_packages'] = $housing_packages;
        $result['extras'] = $extras;
        $result['id_ibe'] = $booking->getBookingPackage()->ibe_type;
        $result['code_ibe'] = is_null($booking->getHousingPackage()) ? null : $booking->getHousingPackage()->code_ibe;
        $result['product_type_ibe'] = $booking->getBookingPackage()->product_type_ibe;

        return ['success' => true, 'data' => $result];
    }

    /**
     * @deprecated will be removed soon
     * @param $transports
     * @return array
     */
    private function _parseTransportPairs($transports)
    {

        $transports_outwards = [];
        $transports_return = [];
        foreach ($transports as $transport) {
            if($transport->way == 1){
                $transports_outwards[] = $transport;
            }else{
                $transports_return[] = $transport;
            }
        }

        $transport_pairs = [];
        foreach ($transports_outwards as $transport) {
            foreach ($transports_return as $transport_return) {
                $code = uniqid();
                $transport_pairs[$code]['outward_transport'] = $transport;
                $transport_pairs[$code]['return_transport'] = $transport_return;
            }
        }

        // so, we have now some valid transport pairs, BUT: we deliver only the fst pair,
        // this is even necessary to avoid a big complexity ux round trip
        array_splice($transport_pairs, 1);

        return $transport_pairs;
    }

    public function pressmind_ib3_v2_get_exit_point($params) {
        $this->parameters = $params['data'];
        $id_starting_point = $this->parameters['id_starting_point'] ?? null;
        $starting_point_option_code = $this->parameters['starting_point_option_code'] ?? null;
        $exit_point = null;
        $optionObject = new \Pressmind\ORM\Object\Touristic\Startingpoint\Option();
        $exit_point_result = $optionObject->listAll(['id_startingpoint' => $id_starting_point, '`exit`' => 1, 'code' => $starting_point_option_code]);
        if(is_array($exit_point_result) && count($exit_point_result) > 0) {
            $exit_point = $exit_point_result[0];
        }
        return ['exit_point' => $exit_point];
    }

    public function pressmind_ib3_v2_get_starting_point_options($params) {
        $this->parameters = $params['data'];
        $id_starting_point = $this->parameters['id_starting_point'];
        $limit = $this->parameters['limit'] != null ? $this->parameters['limit'] : 10;
        $start = isset($this->parameters['start']) ? $this->parameters['start'] : 0;
        $zip = isset($this->parameters['zip']) ? $this->parameters['zip'] : null;
        //return $zip;
        //return $start;
        $radius = isset($this->parameters['radius']) ? $this->parameters['radius'] : null;
        $ibe_client = isset($this->parameters['iic']) ? $this->parameters['iic'] : null;
        if(!is_null($zip)) {
            return ['success' => true, 'data' => $this->_getZipRangeStartingPoints($id_starting_point, $zip, $radius, $start, $limit, $ibe_client)];
        } else {
            return ['success' => true, 'data' => $this->_getStartingPointOptionsForId($id_starting_point, $start, $limit, $ibe_client)];
        }
    }

    /**
     * @param $params
     * @return array
     * @throws Exception
     */
    public function getCheapestPrice($params) {
        $this->parameters = $params['data'];
        if(empty($this->parameters['id_cheapest_price']) ){
            return ['success' => false, 'msg' => 'error: parameters are missing: id_cheapest_price', 'data' => null, 'params' => $this->parameters];
        }
        $CheapestPriceSpeed = new \Pressmind\ORM\Object\CheapestPriceSpeed($this->parameters['id_cheapest_price']);
        if($CheapestPriceSpeed->isValid()){
            return ['success' => true, 'data' => $CheapestPriceSpeed->toStdClass()];
        }
        return ['success' => true, 'data' => null];
    }

    /**
     * @param $params
     * @return array
     * @throws Exception
     */
    public function getRequestableOffer($params) {
        $this->parameters = $params['data'];
        if(empty($this->parameters['id_cheapest_price']) ){
            return ['success' => false, 'msg' => 'error: parameters are missing: id_cheapest_price', 'data' => null, 'params' => $this->parameters];
        }
        $CheapestPriceSpeed = new \Pressmind\ORM\Object\CheapestPriceSpeed($this->parameters['id_cheapest_price']);
        if($CheapestPriceSpeed->isValid()){
            $Date = new \Pressmind\ORM\Object\Touristic\Date($CheapestPriceSpeed->id_date);
            $Options = $Date->getAllOptionsButExcludePriceMixOptions($CheapestPriceSpeed->price_mix);
            return ['success' => true,
                'CheapestPriceSpeed' => $CheapestPriceSpeed->toStdClass(),
                'Options' => $Options
            ];
        }
        return ['success' => true, 'CheapestPriceSpeed' => null, 'Options' => null];
    }

    /**
     * @param $id_starting_point
     * @param int $start
     * @param int $limit
     * @param string $ibe_client
     * @return array
     * @throws Exception
     */
    private function _getStartingPointOptionsForId($id_starting_point, $start = 0, $limit = 10, $ibe_client = null)
    {
        if(is_array($id_starting_point)){
            $id_starting_point = implode(',"', $id_starting_point);
        }
        $optionObject = new Option();
        $query = '`id_startingpoint` in( "' . $id_starting_point . '") AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0)) AND `is_pickup_service` = 0';
        if(!empty($ibe_client)){
            $query .= ' and FIND_IN_SET("'.$ibe_client.'",ibe_clients)';
        }
        $total_starting_point_options = $optionObject->listAll($query);
        $limited_starting_point_options = $optionObject->listAll($query, ['zip' => 'ASC'], [$start, $limit]);
        return array('total' => count($total_starting_point_options), 'starting_point_options' => $limited_starting_point_options);
    }

    /**
     * @param $id_starting_point
     * @param int $start
     * @param int $limit
     * @param null $ibe_client
     * @return array
     * @throws Exception
     */
    private function _getExitPointOptionsForId($id_starting_point, $start = 0, $limit = 10, $ibe_client = null)
    {
        if(is_array($id_starting_point)){
            $id_starting_point = implode(',"', $id_starting_point);
        }
        $optionObject = new Option();
        $query = '`id_startingpoint` in( "' . $id_starting_point . '") AND (`exit` = 1 OR (`entry` = 0 AND `exit` = 0)) AND `is_pickup_service` = 0';
        if(!empty($ibe_client)){
            $query .= ' and FIND_IN_SET("'.$ibe_client.'",ibe_clients)';
        }
        $total_exit_point_options = $optionObject->listAll($query);
        $limited_exit_point_options = $optionObject->listAll($query, ['zip' => 'ASC'], [$start, $limit]);
        return array('total' => count($total_exit_point_options), 'exit_point_options' => $limited_exit_point_options);
    }

    /**
     * @param $id_starting_point
     * @param $zip
     * @param $radius
     * @param int $start
     * @param int $limit
     * @return array
     */
    private function _getZipRangeStartingPoints($id_starting_point, $zip, $radius, $start = 0 ,$limit = 10, $ibe_client = null)
    {
        /*$TouristicObject = new TouristicObject();
        $starting_point_options = [];
        $found_starting_point_options = $TouristicObject->get_startingpoint_options_around_zip($id_starting_point, $zip, $radius, 60);
        foreach ($found_starting_point_options as $key => $starting_point_option) {
            if($key >= $start && $key < ($start + $limit)) {
                $starting_point_options[] = $starting_point_option;
            }
        }*/
        //return array('total' => count($found_starting_point_options), 'starting_point_options' => $starting_point_options);
        return array('total' => 0, 'starting_point_options' => null);
    }

    /**
     * @param $transports
     * @parame int $way enum (1,2)
     * @return array
     */
    private function _getStartingPointToTransports($transports, $way = 1)
    {
        $map = [];
        foreach($transports as $transport){
            if(empty($transport->id_starting_point) || $transport->way !== $way) continue;
            $map[$transport->id_starting_point]['id_starting_point'] = $transport->id_starting_point;
            $map[$transport->id_starting_point]['valid_transports'][] = $transport->id;
        }
        return $map;
    }

}

