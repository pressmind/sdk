<?php
namespace Pressmind\REST\Controller;


use Exception;
use Pressmind\IBE\Booking;
use Pressmind\MVC\AbstractController;
use Pressmind\ORM\Object\Geodata;
use Pressmind\ORM\Object\MediaObject;
use Pressmind\ORM\Object\Touristic\Housing\Package;
use Pressmind\ORM\Object\Touristic\Option\Discount;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Startingpoint\Option;
use Pressmind\REST\Controller\Touristic\Date;

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
        if(!empty(strip_tags((string)$mediaObject->getValueByTagName('pressmind-ib3.services-box-content')))){
            $services_box_content = $mediaObject->getValueByTagName('pressmind-ib3.services-box-content');
            if(!empty(strip_tags((string)$mediaObject->getValueByTagName('pressmind-ib3.services-box-title')))){
                $services_box_title = strip_tags($mediaObject->getValueByTagName('pressmind-ib3.services-box-title'));
            }else{
                $services_box_title = 'Leistungen';
            }
        }

        $booking_transaction_recipients = null;
        if(!empty(strip_tags((string)$mediaObject->getValueByTagName('pressmind-ib3.booking-transaction-recipients')))){
            $booking_transaction_recipients = $mediaObject->getValueByTagName('pressmind-ib3.booking-transaction-recipients');
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

        $ida = !empty($this->parameters['params']['ida']) ? $this->parameters['params']['ida'] : null;
        $use_transport_types = [];
        $use_ways = [];
        if(empty($booking->id_transport_way_1) || empty($booking->id_transport_way_2)){
            $use_transport_types = array_filter([$booking->transport_type]);
        }else{
            $use_ways = array_filter(array_merge($booking->id_transport_way_1, $booking->id_transport_way_2));
        }
        $result['transport_pairs'] = $date->getTransportPairs([0,2,3], $use_ways, $use_transport_types, 1, false, $ida);

        $starting_points_limit = 10;
        $starting_points_order_by_code_list = [];
        if(!is_null($settings)) {
            if(isset($settings['steps']['starting_points']['pagination_page_size']['value'])) {
                $starting_points_limit = $settings['steps']['starting_points']['pagination_page_size']['value'];
            }
            if(!empty($settings['steps']['starting_points']['default_starting_point_codes']['value']) && is_array($settings['steps']['starting_points']['default_starting_point_codes']['value'])) {
                $starting_points_order_by_code_list = $settings['steps']['starting_points']['default_starting_point_codes']['value'];
            }
        }

        $result['starting_points'] = ['total' => 0];
        $result['exit_points'] = ['total' => 0];
        $icc = !empty($this->parameters['params']['iic']) ? $this->parameters['params']['iic'] : null;
        if(!empty($booking->getDate()->id_starting_point) && empty($result['transport_pairs'])){
            // this way is soon deprecated, because its even better to use the startingpoint from the transport instead from the date
            $id_starting_point = $id_exit_point = $booking->getDate()->id_starting_point;
        }elseif(!empty($result['transport_pairs'])){
            $id_starting_point = $result['transport_pairs'][0]['way1']->id_starting_point;
            $id_exit_point= $result['transport_pairs'][0]['way2']->id_starting_point;
        }
        $result['starting_points']['total'] = count(StartingPoint::getOptionsByZipRadius($id_starting_point, $icc, null, 20, 0, null));
        $result['starting_points']['starting_point_options'] = StartingPoint::getOptionsByZipRadius($id_starting_point, $icc, null, 20,0, $starting_points_limit, $starting_points_order_by_code_list);
        $result['exit_points']['total'] = count(StartingPoint::getExitOptionsByZipRadius($id_exit_point, $icc, null, 20, 0, null));
        $result['exit_points']['exit_point_options'] = StartingPoint::getExitOptionsByZipRadius($id_exit_point, $icc, null, 20,0, $starting_points_limit);
        $result['has_pickup_services'] = Startingpoint::hasPickupService($id_starting_point, $icc);
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
            'booking_package_name' => $booking->getBookingPackage()->name,
            'transport_type' => !empty($booking->transport_type) ? $booking->transport_type : null,
            'price_mix' => $booking->getBookingPackage()->price_mix,
        ];

        $extras = $booking->getAllAvailableExtras($date->departure, $date->arrival, $date->season);
        $result['insurances'] = $booking->getInsurances();
        $result['insurance_price_table_packages'] = $booking->getInsurancePriceTablePackages();
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
            if(!empty($this->parameters['params']['iho'])){
                $Option = new \Pressmind\ORM\Object\Touristic\Option(array_key_first($this->parameters['params']['iho']));
                $id_housing_package = $Option->id_housing_package;
            }else{
                $id_housing_package = $booking->getBookingPackage()->housing_packages[0]->getId();
            }
            $housing_packages = [];
            $package = new Package($id_housing_package, true);
            $valid_options = [];
            foreach($package->options as $option){
                $current_saison = trim($option->season);
                if(in_array($current_saison, [$date->season, '-']) || empty($option->season)){
                    $valid_options[] = new \Pressmind\ORM\Object\Touristic\Option($option->id, true);
                }
            }
            $package->options = $valid_options;
            $housing_packages[] = $package;
        } else if($booking->booking_package->price_mix == 'date_transport') {
            $housing_package = new Package();
            $housing_package->name = !empty($booking_package->name) ? $booking_package->name : $result['product']['title'];
            $option = (new \Pressmind\ORM\Object\Touristic\Option())->toStdClass();
            $option->id = uniqid();
            $option->name = 'Teilnahmegebühr';
            $option->type = 'dummy';
            $option->occupancy_max = 1;
            $option->occupancy_min = 1;
            $option->occupancy = 1;
            $option->quota = 15;
            $option->price_due = 'person_stay';
            $option->price = $result['transport_pairs'][0]['way1']->price + $result['transport_pairs'][0]['way2']->price;
            $result['transport_pairs'][0]['way1']->price = $result['transport_pairs'][0]['way2']->price = 0;
            $option->id_touristic_option_discount = $result['transport_pairs'][0]['way1']->id_touristic_option_discount;
            $option->discount = $result['transport_pairs'][0]['way1']->discount;
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

        $result['housing_packages'] = $this->filterValidHousingPackages($housing_packages, $date->departure);
        $result['option_discounts'] = $this->getOptionDiscounts($housing_packages, $date->departure);
        $result['earlybird'] = $booking->getEarlyBird();
        $result['extras'] = $booking->calculateExtras($extras, $booking->getBookingPackage()->duration, $housing_packages[0]->nights);
        $result['id_ibe'] = $booking->getBookingPackage()->ibe_type;
        $result['code_ibe'] = is_null($booking->getHousingPackage()) ? null : $booking->getHousingPackage()->code_ibe;
        $result['product_type_ibe'] = $booking->getBookingPackage()->product_type_ibe;
        $result['booking_transaction_recipients'] = $booking_transaction_recipients;
        return ['success' => true, 'data' => $result];
    }


    /**
     * @param Package[] $housing_packages
     * @return void
     */
    private function getOptionDiscounts($housing_packages, $departure){
        $discounts = [];
        foreach($housing_packages as $housing_package){
            foreach($housing_package->options as $option){
                if(empty($option->discount)){
                    continue;
                }
                $discount = $this->filterValidOptionDiscounts($option->discount->id, $departure);
                $discounts[$discount->id] = $discount;
            }
        }
        return array_values($discounts);
    }

    /**
     * @param int $id_option_discount
     * @return null|Discount
     * @throws Exception
     */
    private function filterValidOptionDiscounts($id_option_discount, $departure){
        $discount = new Discount($id_option_discount, true);
        if(!$discount->isValid()){
           return null;
        }
        $valid_scales = [];
        foreach($discount->scales as $scale){
            if(($scale->valid_from === null || $departure >= $scale->valid_from) &&
                ($scale->valid_to === null || $scale->valid_to >= $departure)
            ){
                $valid_scales[] = $scale;
            }
        }
        $discount->scales = $valid_scales;
        return $discount;
    }

    /**
     * @param Package $housing_packages[]
     * @return void
     */
    function filterValidHousingPackages($housing_packages, $departure){
        foreach($housing_packages as $k => $housing_package){
            /**
             * @var Package $housing_package
             */
            foreach($housing_package->options as $k2 => $option){
                if(!empty($option->id_touristic_option_discount)){
                    $housing_packages[$k]->options[$k2]->discount = $this->filterValidOptionDiscounts($option->id_touristic_option_discount, $departure);
                }
            }
        }
        return $housing_packages;
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

    public function pressmind_ib3_v2_get_starting_point_options($params) {
        $this->parameters = $params['data'];
        $id_starting_point = $this->parameters['id_starting_point'];
        $limit = $this->parameters['limit'] != null ? $this->parameters['limit'] : 10;
        $start = isset($this->parameters['start']) ? $this->parameters['start'] : 0;
        $zip = isset($this->parameters['zip']) ? $this->parameters['zip'] : null;
        $radius = isset($this->parameters['radius']) ? $this->parameters['radius'] : null;
        $ibe_client = isset($this->parameters['iic']) ? $this->parameters['iic'] : null;
        //$list_exits = !empty($this->parameters['list_exits']);
        $order_by_code_list = !empty($this->parameters['order_by_code_list']) && is_array($this->parameters['order_by_code_list']) ? $this->parameters['order_by_code_list'] : [];
        $data = [];
        $data['total'] = count(Startingpoint::getOptionsByZipRadius($id_starting_point, $ibe_client, $zip, $radius, 0, null));
        $data['starting_point_options'] = Startingpoint::getOptionsByZipRadius($id_starting_point, $ibe_client, $zip, $radius, $start, $limit, $order_by_code_list);
        return ['success' => true, 'data' => $data];
    }

    public function pressmind_ib3_v2_find_pickup_service($params) {
        $this->parameters = $params['data'];
        $id_starting_point = $this->parameters['id_starting_point'];
        $zip = isset($this->parameters['zip']) ? $this->parameters['zip'] : null;
        $ibe_client = isset($this->parameters['iic']) ? $this->parameters['iic'] : null;
        $data = [];
        $data['total'] = count(Startingpoint::getPickupOptionByZip($id_starting_point, $ibe_client, $zip));
        $data['starting_point_options'] = Startingpoint::getPickupOptionByZip($id_starting_point, $ibe_client, $zip);
        return ['success' => true, 'data' => $data];
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

