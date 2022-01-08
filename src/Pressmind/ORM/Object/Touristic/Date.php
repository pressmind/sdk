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
        $sightseeings = [];
        $sightseeings_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'sightseeing' AND season = '" . $this->season . "'");
        foreach ($sightseeings_list as $sightseeing) {
            $sightseeings[] = $sightseeing;
        }
        return $sightseeings;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getExtras()
    {
        $extras = [];
        $extras_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'extra' AND season = '" . $this->season . "'");
        foreach ($extras_list as $extra) {
            $extras[] = $extra;
        }
        return $extras;
    }

    /**
     * @return Option[]
     * @throws Exception
     */
    public function getTickets()
    {
        $tickets = [];
        $tickets_list = Option::listAll("id_booking_package = '" . $this->id_booking_package . "' AND type = 'ticket' AND season = '" . $this->season . "'");
        foreach ($tickets_list as $ticket) {
            $tickets[] = $ticket;
        }
        return $tickets;
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
        $transports_without_groups = [];
        $transports_with_groups = [];
        foreach($extracted_transports as $transport){
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
        foreach($transports_by_group as $transports){
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
