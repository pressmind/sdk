<?php

namespace Pressmind\ORM\Object\Touristic;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;
use Pressmind\ORM\Object\Touristic\Date\Attribute;
use Pressmind\Registry;

/**
 * Class Date
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $id_starting_point
 * @property string $code
 * @property DateTime $departure
 * @property DateTime $arrival
 * @property string $text
 * @property integer $pax_min
 * @property integer $pax_max
 * @property string $season
 * @property string $url
 * @property integer $state
 * @property string $code_ibe
 * @property integer $id_early_bird_discount_group
 * @property string $link_pib
 * @property boolean $guaranteed
 * @property boolean $saved
 * @property boolean $flex
 * @property string $touroperator
 * @property string $agencies
 * @property Startingpoint $startingpoint
 * @property Transport[] $transports
 * @property EarlyBirdDiscountGroup $early_bird_discount_group
 * @property Attribute[] $attributes
 */
class Date extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_dates',
            'primary_key' => 'id',
        ],
        'properties' => [
            'id' => [
                'title' => 'Id',
                'name' => 'id',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
            ],
            'id_media_object' => [
                'title' => 'Id_media_object',
                'name' => 'id_media_object',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 22,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_media_object' => 'index'
                ]
            ],
            'id_booking_package' => [
                'title' => 'Id_booking_package',
                'name' => 'id_booking_package',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_booking_package' => 'index'
                ]
            ],
            'id_starting_point' => [
                'title' => 'Id_starting_point',
                'name' => 'id_starting_point',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'id_starting_point' => 'index'
                ]
            ],
            'code' => [
                'title' => 'Code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'departure' => [
                'title' => 'Departure',
                'name' => 'departure',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'arrival' => [
                'title' => 'Arrival',
                'name' => 'arrival',
                'type' => 'date',
                'required' => true,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'pax_min' => [
                'title' => 'Pax_min',
                'name' => 'pax_min',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'pax_max' => [
                'title' => 'Pax_max',
                'name' => 'pax_max',
                'type' => 'integer',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'season' => [
                'title' => 'Season',
                'name' => 'season',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 100,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'season' => 'index'
                ]
            ],
            'url' => [
                'title' => 'Url',
                'name' => 'url',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'state' => [
                'title' => 'State',
                'name' => 'state',
                'type' => 'integer',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 11,
                    ],
                    [
                        'name' => 'unsigned',
                        'params' => null,
                    ]
                ],
                'filters' => NULL,
            ],
            'code_ibe' => [
                'title' => 'Code_ibe',
                'name' => 'code_ibe',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
                'index' => [
                    'code_ibe' => 'index'
                ]
            ],
            'id_early_bird_discount_group' => [
                'title' => 'id_early_bird_discount_group',
                'name' => 'id_early_bird_discount_group',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 32,
                    ]
                ],
                'filters' => NULL,
                'index' => [
                    'id_early_bird_discount_group' => 'index'
                ]
            ],
            'link_pib' => [
                'title' => 'Link_pib',
                'name' => 'link_pib',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'guaranteed' => [
                'title' => 'Guaranteed',
                'name' => 'guaranteed',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'flex' => [
                'title' => 'flex',
                'name' => 'flex',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'saved' => [
                'title' => 'Saved',
                'name' => 'saved',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
                'filters' => NULL,
            ],
            'agencies' => [
                'title' => 'agencies',
                'name' => 'agencies',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'touroperator' => [
                'title' => 'Touroperator',
                'name' => 'touroperator',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'startingpoint' => [
                'title' => 'Startingpoint',
                'name' => 'startingpoint',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_starting_point',
                    'class' => Startingpoint::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'transports' => [
                'title' => 'transports',
                'name' => 'transports',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_date',
                    'class' => Transport::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'early_bird_discount_group' => [
                'title' => 'Early Bird Discount',
                'name' => 'early_bird_discount_group',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasOne',
                    'related_id' => 'id_early_bird_discount_group',
                    'class' => EarlyBirdDiscountGroup::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'attributes' => [
                'title' => 'attributes',
                'name' => 'attributes',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_date',
                    'class' => Attribute::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );

    /**
     * @param $state_filter
     * @param $is_offer_query
     * @param $agency
     * @return Option[]
     * @throws Exception
     */
    public function getHousingOptions($state_filter = [0,1,2,3], $is_offer_query = false, $agency = null)
    {
        $housing_options = [];
        $housingOptions = Option::listAll(
            "id_booking_package = '" . $this->id_booking_package . "' 
            AND type = 'housing_option' AND season = '" . $this->season . "'"
            .(!empty($state_filter) ? " AND state in(".implode(',', $state_filter).")" : "")
            .(!empty($is_offer_query) ? " AND dont_use_for_offers = 0" : "")
            .(!empty($agency) ? " AND (FIND_IN_SET('".$agency."', agencies) > 0 or agencies is null)" : "")
        );
        foreach ($housingOptions as $housing_option) {
            $housing_options[] = $housing_option;
        }
        return $housing_options;
    }

    /**
     * @param bool $is_query_offer
     * @param string|null $agency
     * @return Option[]
     * @throws Exception
     */
    public function getSightseeings($is_query_offer = false, $agency = null)
    {
        return $this->getOptions(['sightseeing'], $is_query_offer, $agency);
    }

    /**
     * @param bool $is_query_offer
     * @param string|null $agency
     * @return Option[]
     * @throws Exception
     */
    public function getExtras($is_query_offer = false, $agency = null)
    {
        return $this->getOptions(['extra'], $is_query_offer, $agency);
    }


    /**
     * @param bool $is_query_offer
     * @param string|null $agency
     * @return Option[]
     * @throws Exception
     */
    public function getTickets($is_query_offer = false, $agency = null)
    {
        return $this->getOptions(['ticket'], $is_query_offer, $agency);
    }

    /**
     * @param $types
     * @param $is_offer_query
     * @param string|null $agency
     * @return Option[]
     * @throws Exception
     */
    public function getOptions($types, $is_offer_query = false, $agency = null)
    {
        $options = [];
        $options_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' 
                                                AND type in ('".implode("','", $types)."')
                                                AND (   
                                                        (          
                                                        reservation_date_from is not null
                                                        AND reservation_date_to is not null
                                                        AND date(reservation_date_from) = '".$this->departure->format('Y-m-d')."'
                                                        AND date(reservation_date_to) = '".$this->arrival->format('Y-m-d')."'
                                                        ) OR (
                                                        reservation_date_from is null 
                                                        AND reservation_date_to is null 
                                                        AND (season in ('" . $this->season . "','-', '') or season is null)
                                                        )
                                                    )"
                                                    .(!empty($is_offer_query) ? " AND dont_use_for_offers = 0" : "")
                                                    .(!empty($agency) ? " AND (FIND_IN_SET('".$agency."', agencies) > 0 or agencies is null)" : "")
        );
        foreach ($options_list as $option) {
            $options[] = $option;
        }
        return $options;
    }

    /**
     * @param $price_mix
     * @param bool $is_query_offer
     * @param string|null $agency
     * @return Option[]
     * @throws Exception
     */
    public function getAllOptionsButExcludePriceMixOptions($price_mix, $is_query_offer = false, $agency = null){
        $option_set['date_transport'] = ['ticket', 'sightseeing', 'extra'];
        $option_set['date_housing'] = ['ticket', 'sightseeing', 'extra'];
        $option_set['date_extra'] = ['ticket', 'sightseeing',];
        $option_set['date_ticket'] = ['sightseeing', 'extra'];
        $option_set['date_sightseeing'] = ['ticket', 'extra'];
        return isset($option_set[$price_mix]) ? $this->getOptions($option_set[$price_mix], $is_query_offer, $agency) : [];
    }

    /**
     * @param $price_mix
     * @param $is_query_offer
     * @param $agency
     * @return array|Option[]
     * @throws Exception
     */
    public function getAllOptionsByPriceMix($price_mix, $is_query_offer = false, $agency = null){
        $option_set['date_transport'] = ['transport'];
        $option_set['date_housing'] = ['housing_option'];
        $option_set['date_extra'] = ['extra'];
        $option_set['date_ticket'] = ['ticket'];
        $option_set['date_sightseeing'] = ['sightseeing'];
        return isset($option_set[$price_mix]) ? $this->getOptions($option_set[$price_mix], $is_query_offer, $agency) : [];
    }


    /**
     * @param array $state_filter
     * @param array $ids
     * @param array $types
     * @param bool $is_query_offer
     * @param string|null $agency (null means all agencies)
     * @return Transport[]
     */
    public function getTransports($state_filter = [0,2,3], $ids = [], $types = [], $is_query_offer = false, $agency = null ){
        $valid_transports = [];
        foreach($this->transports as $transport){
            if(!empty($agency) &&
                $transport->agencies != null &&
                !in_array($agency, explode(',', (string)$transport->agencies))
            ){
                continue;
            }
            if(
                (in_array($transport->state, $state_filter) || count($state_filter) == 0) AND
                (in_array($transport->getId(), $ids) || count($ids) == 0) AND
                (in_array($transport->type, $types) || count($types) == 0) AND
                ($is_query_offer === false || $is_query_offer && !$transport->dont_use_for_offers)
            ){
                $valid_transports[] = $transport;
            }
        }
        return $valid_transports;
    }

    /**
     * @param array $state_filter (0 = kein Status, 1 = Gesperrt, 2 = Anfrage, 3 = Buchbar)
     * @param array $ids
     * @param array $types
     * @param int $max_pairs
     * @param bool $is_query_offer
     * @param string|null $agency
     * @return array
     */
    public function getTransportPairs($state_filter = [0,2,3], $ids = [], $types = [], $max_pairs = null, $is_query_offer = false, $agency = null){
        $valid_transports = $this->getTransports($state_filter, $ids, $types, $is_query_offer, $agency);
        $transport_pairs = [];

        // some transports can contain more than one groups, this multiple groups are marked with the "#"-sign
        // so we extract them first. e.g. transport_group = "Bus1#Flug1", common real world cases are one-way cruise-routes
        $extracted_transports = [];
        foreach($valid_transports as $transport){
            if(empty($transport->transport_group)){
                $extracted_transports[] = $transport;
            }else{
                $p = explode('#', $transport->transport_group);
                foreach($p as $t){
                    $tn = $transport->toStdClass();
                    $tn->transport_group = trim($t);
                    $newTransport = new Transport();
                    $newTransport->fromStdClass($tn);
                    $extracted_transports[] = $newTransport;
                }
            }
        }

        // remove orphans
        $c = [];
        foreach($extracted_transports as $extracted_transport){
            if(empty($extracted_transport->transport_group)){
                $extracted_transport->transport_group = 'empty';
            }
            if(!isset($c[$extracted_transport->transport_group])){
                $c[$extracted_transport->transport_group] = [];
            }
            if(!isset($c[$extracted_transport->transport_group][$extracted_transport->way])){
                $c[$extracted_transport->transport_group][$extracted_transport->way] = 1;
            }else{
                $c[$extracted_transport->transport_group][$extracted_transport->way]++;
            }
        }
        $invalid_groups = [];
        foreach($c as $group => $item){
            if(!isset($item[1]) || !isset($item[2])){
                $invalid_groups[] = $group;
            }
        }
        if(!empty($invalid_groups)){
            $extracted_transports_clean = [];
            foreach($extracted_transports as $extracted_transport){
                if(!in_array($extracted_transport->transport_group, $invalid_groups)){
                    $extracted_transports_clean[] = $extracted_transport;
                }
            }
            $extracted_transports = $extracted_transports_clean;
        }
        $transports_without_groups = [];
        $transports_with_groups = [];
        foreach($extracted_transports as $transport){
            $transport->transport_group = $transport->transport_group == 'empty' ? null : $transport->transport_group;
            if(empty($transport->transport_group)){
                $transports_without_groups[] = $transport;
            }else{
                $transports_with_groups[] = $transport;
            }
        }
        // mix transports that are not grouped
        // don't mix transport types, so we reduce this fst.
        $transports_by_type = [];
        foreach($transports_without_groups as $transport){
            $transports_by_type[trim($transport->type)][] = $transport;
        }

        foreach($transports_by_type as $type => $transports){
            if($type === 'FLUG'){
                $transport_pairs = array_merge($transport_pairs, $this->_collectAirportPairs($transports));
            }else{
                $transport_pairs = array_merge($transport_pairs, $this->_collectPairs($transports));
            }
        }

        // mix transport that are grouped
        $transports_by_group = [];
        foreach($transports_with_groups as $transport){
            $transports_by_group[trim($transport->transport_group)][] = $transport;
        }
        // compress multiple transports to one transport offer (stopover flights, etc)
        $compressed_group = [];
        foreach($transports_by_group as $k => $transports){
            $transports_ordered = [];
            foreach($transports as $k1 => $transport){
                $key = ((float)is_null($transport->transport_date_from) ? '0.'.$k1 : $transport->transport_date_from->format('U').'.'.$k1) * 10;
                $transports_ordered[$key] = $transport;
            }
            ksort($transports_ordered, SORT_NUMERIC);
            $way_1 = null;
            $way_2 = null;
            foreach($transports_ordered as $transport){
                /**
                 * @var Transport $transport
                 */
                /**
                 * @var Transport $way_1
                 */
                /**
                 * @var Transport $way_2
                 */
                if($transport->way == 1){
                    if(is_null($way_1)){
                        $way_1 = $transport->toStdClass();
                        $way_1->description = $way_1->transport_group;
                    }else{
                        $way_1->price += $transport->price;
                        $way_1->code_ibe .= '#'.$transport->code_ibe;
                        $way_1->id .= ','.$transport->id;
                        $way_1->airline .= ','.$transport->airline;
                        $way_1->flight .= ','.$transport->flight;
                        $way_1->code .= ','.$transport->code;
                        $way_1->transport_date_to = $transport->transport_date_to;
                    }
                }else{
                    if(is_null($way_2)){
                        $way_2 = $transport->toStdClass();
                        $way_2->description = $way_2->transport_group;
                    }else{
                        $way_2->price += $transport->price;
                        $way_2->code_ibe .= '#'.$transport->code_ibe;
                        $way_2->id .= ','.$transport->id;
                        $way_2->airline .= ','.$transport->airline;
                        $way_2->flight .= ','.$transport->flight;
                        $way_2->code .= ','.$transport->code;
                        $way_2->transport_date_to = $transport->transport_date_to;
                    }
                }
            }
            $compressed_group[$k][] = $way_1;
            $compressed_group[$k][] = $way_2;
        }
        foreach($compressed_group as $transports){
            $transport_pairs = array_merge($transport_pairs, $this->_collectPairs($transports));
        }
        if(is_int($max_pairs)){
           array_splice($transport_pairs, $max_pairs);
        }
        foreach($transport_pairs as $key => $pair){
            $transport_pairs[$key]['way1'] = new Transport();
            $transport_pairs[$key]['way1']->fromStdClass($pair['way1']);
            $transport_pairs[$key]['way2'] = new Transport();
            $transport_pairs[$key]['way2']->fromStdClass($pair['way2']);
        }
        return $transport_pairs;
    }

    /**
     * @param $transports
     * @return Transport[]
     */
    private function _collectPairs($transports){
        $transport_pairs = [];
        $transports_outwards = [];
        $transports_return = [];
        foreach ($transports as $transport) {
            if($transport->way == 1){
                $transports_outwards[] = $transport;
            }else{
                $transports_return[] = $transport;
            }
        }
        $i = 0;
        foreach ($transports_outwards as $transport) {
            foreach ($transports_return as $transport_return) {
                $transport_pairs[$i]['way1'] = $transport;
                $transport_pairs[$i]['way2'] = $transport_return;
                $i++;
            }
        }
        return $transport_pairs;
    }

    /**
     * @param $transports
     * @return Transport[]
     */
    private function _collectAirportPairs($transports){
        $transport_pairs = [];
        $transports_by_base_airport = [];
        foreach ($transports as $transport) {
            if($transport->way == 1){
                $base_airport = substr((string)$transport->code, 0, 3);
            }else{
                $base_airport = substr((string)$transport->code, -3, 3);
            }
            $transports_by_base_airport[$base_airport][] = $transport;
        }
        foreach($transports_by_base_airport as $transports){
            $transport_pairs = array_merge($transport_pairs, $this->_collectPairs($transports));
        }
        return $transport_pairs;
    }


    /**
     * Human friendly validation
     * @param string $prefix
     * @return array
     */
    public function validate($prefix = ''){
        $result = [];
        $transport_allowed_states = [0, 2, 3];
        if(!empty(Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['active'])) {
            $transport_allowed_states = empty(Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['allowed_states']) ? $transport_allowed_states : Registry::getInstance()->get('config')['data']['touristic']['transport_filter']['allowed_states'];
        }
        $agency_based_option_and_prices_enabled = !isset(Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['enabled']) ? false : Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['enabled'];
        $agencies = empty(Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['allowed_agencies']) || $agency_based_option_and_prices_enabled === false ? [null] : Registry::getInstance()->get('config')['data']['touristic']['agency_based_option_and_prices']['allowed_agencies'];
        foreach ($agencies as $agency) {
            $pairs = count($this->transports) > 0 ? $this->getTransportPairs($transport_allowed_states, [], [], null, true, $agency) : [null];
            $pair_count = count($pairs);
            $result[] = $prefix.($pair_count > 0 ? '✅' : '❌') . '  Transport Pair Check for Agency: ' . $agency. ' ('.$pair_count.' pairs found) Date ID: ' . $this->id. ' ('.$this->departure->format('Y-m-d').')';
            if($pair_count == 0){
                $transport_count = count($this->transports);
                $result[] = $prefix.''.($transport_count > 0 ? '✅' : '❌') . '  Transport Check for Agency: ' . $agency. ' ('.$transport_count.' transports found)';
                if($transport_count > 0){
                    $pairs = $this->getTransportPairs($transport_allowed_states, [], [], null, false, $agency);
                    $pair_count = count($pairs);
                    $result[] = $prefix.($pair_count > 0 ? '✅' : '❌') . '  Property transport.dont_use_for_offers valid';
                    $pairs = $this->getTransportPairs([0,2,3], [], [], null, true, $agency);
                    $pair_count = count($pairs);
                    $result[] = $prefix.($pair_count > 0 ? '✅' : '❌') . '  Property transport.state valid';
                }
            }
            foreach($pairs as $pair){
                if(!empty($pair['way1'])){
                    $result = array_merge($result, $pair['way1']->validate($prefix.'  '));
                }else{
                    $result[] = $prefix.' ❌ ' . '  Property transport.pair[way1] not valid';
                }
                if(!empty($pair['way2'])){
                    $result = array_merge($result, $pair['way2']->validate($prefix.'  '));
                }else{
                    $result[] = $prefix.' ❌ ' . '  Property transport.pair[way2] not valid';
                }
            }
        }
        return $result;
    }



}
