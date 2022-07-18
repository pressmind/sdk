<?php

namespace Pressmind\ORM\Object\Touristic;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;

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
 * @property integer $guaranteed
 * @property integer $saved
 * @property string $touroperator
 * @property Startingpoint $startingpoint
 * @property Transport[] $transports
 * @property EarlyBirdDiscountGroup $early_bird_discount_group
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
                'required' => false,
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
            'saved' => [
                'title' => 'Saved',
                'name' => 'saved',
                'type' => 'boolean',
                'required' => false,
                'validators' => null,
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
        ]
    );

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getHousingOptions()
    {
        $housing_options = [];
        $housingOptions = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'housing_option' AND season = '" . $this->season . "'");
        foreach ($housingOptions as $housing_option) {
            $housing_options[] = $housing_option;
        }
        return $housing_options;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getSightseeings()
    {
        return $this->getOptions(['sightseeing']);
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getExtras()
    {
        return $this->getOptions(['extra']);
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getTickets()
    {
        return $this->getOptions(['ticket']);
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getOptions($types)
    {
        $options = [];
        $options_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' 
                                                AND type in ('".implode("','", $types)."')
                                                AND (   
                                                        (          
                                                        reservation_date_from is not null
                                                        AND reservation_date_to is not null
                                                        AND reservation_date_from = '".$this->departure->format('Y-m-d 00:00:00')."'
                                                        AND reservation_date_to = '".$this->departure->format('Y-m-d 00:00:00')."'
                                                        ) OR (
                                                        reservation_date_from is null 
                                                        AND reservation_date_to is null 
                                                        AND (season in ('" . $this->season . "','-', '') or season is null)
                                                        )
                                                    )
                                                ");
        foreach ($options_list as $option) {
            $options[] = $option;
        }
        return $options;
    }

    /**
     * @param $price_mix
     * @return Option[]
     * @throws Exception
     */
    public function getAllOptionsButExcludePriceMixOptions($price_mix){
        $option_set['date_transport'] = ['ticket', 'sightseeing', 'extra'];
        $option_set['date_housing'] = ['ticket', 'sightseeing', 'extra'];
        $option_set['date_extra'] = ['ticket', 'sightseeing',];
        $option_set['date_ticket'] = ['sightseeing', 'extra'];
        $option_set['date_sightseeing'] = ['ticket', 'extra'];
        return isset($option_set[$price_mix]) ? $this->getOptions($option_set[$price_mix]) : [];
    }


    /**
     * @param array $state_filter
     * @param array $ids
     * @param array $types
     * @return Transport[]
     */
    public function getTransports($state_filter = [0,2,3], $ids = [], $types = []){
        $valid_transports = [];
        foreach($this->transports as $transport){
            if(
                (in_array($transport->state, $state_filter) || count($state_filter) == 0) AND
                (in_array($transport->getId(), $ids) || count($ids) == 0) AND
                (in_array($transport->type, $types) || count($types) == 0)
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
     * @return array
     */
    public function getTransportPairs($state_filter = [0,2,3], $ids = [], $types = [], $max_pairs = null){
        $valid_transports = $this->getTransports($state_filter, $ids, $types);
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
        foreach($transports_by_type as $transports){
            $transport_pairs = array_merge($transport_pairs, $this->_collectPairs($transports));
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
        return $transport_pairs;
    }

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

}
