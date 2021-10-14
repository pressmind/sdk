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
        $settings = isset($this->parameters['settings']) ? $this->parameters['settings'] : null;
        $booking = new Booking($this->parameters);
        $mediaObject = new MediaObject($this->parameters['params']['imo']);
        $result = [];
        $image_info = null;
        $images = $mediaObject->getValueByTagName('pressmind-ib3.teaser-image');
        if(!is_null($images)) {
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
        $result['date'] = $booking->getDate();
        $result['product'] = [
            'title' => !empty($mediaObject->getValueByTagName('pressmind-ib3.headline')) ? strip_tags($mediaObject->getValueByTagName('pressmind-ib3.headline')) : $mediaObject->name,
            'subtitle' => '',
            'description' => '',
            'name' => '',
            'code' => $mediaObject->code,
            'teaser_image' => $image_info,
            'destination' => array('name' => $destination_name, 'code' => $destination_code),
            'hotel_trust_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text') : null,
            'trustbox_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.trustbox-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.trustbox-text') : null,
            'services_box_title' => !empty($mediaObject->getValueByTagName('pressmind-ib3.services-box-title')) ? $mediaObject->getValueByTagName('pressmind-ib3.services-box-title') : null,
            'services_box_content' => !empty($mediaObject->getValueByTagName('pressmind-ib3.services-box-content')) ? $mediaObject->getValueByTagName('pressmind-ib3.services-box-content') : null,
            'duration' => $booking->getBookingPackage()->duration,
            'transport_type' => 'BUS',
            'price_mix' => $booking->getBookingPackage()->price_mix,
        ];

        $result['transports'] = $booking->getTransports();
        $extras = $booking->getAllAvailableExtras();
        $insurances = $booking->getInsurances();

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
                $housing_packages = [$booking->getHousingPackage($this->parameters['params']['idhp'])->toStdClass()];
            } else {
                /** @TODO: Check if loading up a full Haousing\Package Object really is necessary*/
                $housing_packages = [];
                $housing_packages_list = $booking->getBookingPackage()->housing_packages;
                foreach ($housing_packages_list as $housing_package) {
                    $housing_packages[] = new Package($housing_package->id, true);
                }
            }
        } else if($booking->booking_package->price_mix == 'date_transport') {
            $housing_package = new Package();
            $housing_package->name = !empty($booking_package->name) ? $booking_package->name : $result['product']['title'];
            $option = [];
            foreach ($result['transports'] as $transport) {
                if(key_exists($transport->id, $predefined_options)) {
                    $option = new \Pressmind\ORM\Object\Touristic\Option();
                    $option->id = $transport->id;
                    $option->name = null;
                    $option->description_long = $transport->description_long;
                    $option->code_ibe = $transport->code_ibe;
                    $option->code = $transport->code;
                    $option->max_pax = 10;
                    $option->min_pax = 1;
                    $option->occupancy_max = 10;
                    $option->occupancy_min = 1;
                    $option->occupancy = isset($predefined_options[$transport->id]) ? $predefined_options[$transport->id] : 1;
                    $option->price = $transport->price;
                    $option->price_due = 'person_stay';
                    $transport->price = 0;
                }
            }
            $options = [$option];
            $housing_package->options = $options;
            $housing_packages[] = $housing_package;
        } else {
            $housing_package = new Package();
            $housing_package->name = !empty($booking_package->name) ? $booking_package->name : $result['product']['title'];
            $options = [];
            foreach ($extras as $key => $extra) {
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

        $result['debug'] = $booking;
        $result['housing_packages'] = $housing_packages;
        $result['insurances'] = $insurances;
        $result['starting_points'] = $this->_getStartingPointOptionsForId($booking->getDate()->id_starting_point, 0, 10);
        $result['exit_points'] = $this->_getExitPointOptionsForId($booking->getDate()->id_starting_point, 0, 10);
        $result['extras'] = $extras;
        $result['has_pickup_services'] = $booking->hasPickServices();
        $result['has_starting_points'] = $booking->hasStartingPoints();
        $result['id_ibe'] = $booking->getBookingPackage()->ibe_type;
        $result['code_ibe'] = is_null($booking->getHousingPackage()) ? null : $booking->getHousingPackage()->code_ibe;

        return ['success' => true, 'data' => $result];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function pressmind_ib3_get_touristic_object($params)
    {
        $this->parameters = $params['data'];
        $booking = new Booking($this->parameters);
        $mediaObject = new MediaObject($this->parameters['params']['imo']);
        $settings = $this->parameters['settings'];
        /**@var MediaObject\DataType\Picture $image**/
        $image_info = [
            'uri' => null,
            'caption' => null,
            'alt' => null
        ];
        $image = $mediaObject->getValueByTagName('truetravel.teaser.image')[0];
        if(!is_null($image)) {
            $image_info['uri'] = substr($image->getUri('teaser'), 0, 4) == 'http' ? $image->getUri('teaser') : WEBSERVER_HTTP . $image->getUri('teaser');
            $image_info['caption'] = $image->caption;
            $image_info['alt'] = $image->alt;
        }
        $destination_name = null;
        $destination_code = null;
        $destinations = $mediaObject->getValueByTagName($settings['general']['destination_tag_name']['value']);
        if(!is_null($destinations)) {
            foreach ($destinations as $destination_array) {
                $destination = new \Pressmind\ORM\Object\CategoryTree\Item($destination_array->id_item);
                if(!empty($destination->code)) {
                    $destination_name = $destination->name;
                    $destination_code = $destination->code;
                }
            }
        }
        $result = [];
        $result['booking_package'] = $booking->getBookingPackage();
        $result['date'] = $booking->getDate();
        $insurances = $booking->getInsurances();
        $result['available_insurances'] = $insurances;
        $result['available_housing_options'] = $booking->getAvailableHousingOptionsForDate();
        $result['available_transports'] = $booking->getTransports();
        $result['available_starting_points'] = $this->_getStartingPointOptionsForId($booking->getDate()->id_starting_point, 0, $this->parameters['settings']['steps']['starting_points']['pagination_page_size']['value']);
        $result['available_exit_points'] = $this->_getExitPointOptionsForId($booking->getDate()->id_starting_point, 0, 10);//$this->_getParameter('settings')['steps']['starting_points']['pagination_page_size']['value']);
        $result['has_pickup_services'] = $booking->hasPickServices();
        $result['has_starting_points'] = $booking->hasStartingPoints();
        $result['available_extras'] = $booking->getAllAvailableExtras();
        $result['product'] = [
            'title' => !empty($mediaObject->getValueByTagName('truetravel.headline')) ? strip_tags($mediaObject->getValueByTagName('truetravel.headline')) : $mediaObject->name,
            'subtitle' => '',
            'description' => '',
            'name' => '',
            'code' => $mediaObject->code,
            'teaser_image' => $image_info,
            'destination' => array('name' => $destination_name, 'code' => $destination_code),
            'hotel_trust_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.hotel-trust-text') : null,
            'trustbox_text' => !empty($mediaObject->getValueByTagName('pressmind-ib3.trustbox-text')) ? $mediaObject->getValueByTagName('pressmind-ib3.trustbox-text') : null,
            'services_box_title' => !empty($mediaObject->getValueByTagName('pressmind-ib3.services-box-title')) ? $mediaObject->getValueByTagName('pressmind-ib3.services-box-title') : null,
            'services_box_content' => !empty($mediaObject->getValueByTagName('pressmind-ib3.services-box-content')) ? $mediaObject->getValueByTagName('pressmind-ib3.services-box-content') : null
        ];
        return $result;
    }

    /**
     * @param $id_starting_point
     * @param int $start
     * @param int $limit
     * @return array
     * @throws Exception
     */
    private function _getStartingPointOptionsForId($id_starting_point, $start = 0 ,$limit = 10)
    {
        $optionObject = new Option();
        $total_starting_point_options = $optionObject->listAll('`id_startingpoint` = ' . $id_starting_point . ' AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0)) AND `is_pickup_service` = 0');
        $limited_starting_point_options = $optionObject->listAll('`id_startingpoint` = ' . $id_starting_point . ' AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0)) AND `is_pickup_service` = 0', ['zip' => 'ASC'], [$start, $limit]);
        return array('total' => count($total_starting_point_options), 'starting_point_options' => $limited_starting_point_options);
    }

    private function _getExitPointOptionsForId($id_starting_point, $start = 0 ,$limit = 10)
    {
        $optionObject = new Option();
        $total_exit_point_options = $optionObject->listAll(['id_startingpoint' => $id_starting_point, '`exit`' => 1]);
        $limited_exit_point_options = $optionObject->listAll(['id_startingpoint' => $id_starting_point, '`exit`' => 1], ['zip' => 'ASC'], [$start, $limit]);
        return array('total' => count($total_exit_point_options), 'exit_point_options' => $limited_exit_point_options);
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
        if(!is_null($zip)) {
            return ['success' => true, 'data' => $this->_getZipRangeStartingPoints($id_starting_point, $zip, $radius, $start, $limit)];
        } else {
            return ['success' => true, 'data' => $this->_getStartingPointOptionsForId($id_starting_point, $start, $limit)];
        }
    }

    private function _getZipRangeStartingPoints($id_starting_point, $zip, $radius, $start = 0 ,$limit = 10)
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


}

