<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
use DateTime;
use Pressmind\ORM\Object\Touristic\Startingpoint;
use Pressmind\ORM\Object\Touristic\Transport;

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
 * @property integer $id_earlybird_discount
 * @property string $link_pib
 * @property integer $guaranteed
 * @property integer $saved
 * @property string $touroperator
 * @property Startingpoint $startingpoint
 * @property Transport[] $transports
 */
class Date extends AbstractObject
{

    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Date',
                'namespace' => '\Pressmind\ORM\Object\Touristic'
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_dates',
                'primary_key' => 'id',
            ),
        'properties' =>
            array(
                'id' =>
                    array(
                        'title' => 'Id',
                        'name' => 'id',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_media_object' =>
                    array(
                        'title' => 'Id_media_object',
                        'name' => 'id_media_object',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 22,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_booking_package' =>
                    array(
                        'title' => 'Id_booking_package',
                        'name' => 'id_booking_package',
                        'type' => 'string',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_starting_point' =>
                    array(
                        'title' => 'Id_starting_point',
                        'name' => 'id_starting_point',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 32,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code' =>
                    array(
                        'title' => 'Code',
                        'name' => 'code',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'departure' =>
                    array(
                        'title' => 'Departure',
                        'name' => 'departure',
                        'type' => 'date',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'arrival' =>
                    array(
                        'title' => 'Arrival',
                        'name' => 'arrival',
                        'type' => 'date',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'pax_min' =>
                    array(
                        'title' => 'Pax_min',
                        'name' => 'pax_min',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'pax_max' =>
                    array(
                        'title' => 'Pax_max',
                        'name' => 'pax_max',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'season' =>
                    array(
                        'title' => 'Season',
                        'name' => 'season',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 100,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'url' =>
                    array(
                        'title' => 'Url',
                        'name' => 'url',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'state' =>
                    array(
                        'title' => 'State',
                        'name' => 'state',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 11,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'code_ibe' =>
                    array(
                        'title' => 'Code_ibe',
                        'name' => 'code_ibe',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'id_earlybird_discount' =>
                    array(
                        'title' => 'Id_earlybird_discount',
                        'name' => 'id_earlybird_discount',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 22,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'link_pib' =>
                    array(
                        'title' => 'Link_pib',
                        'name' => 'link_pib',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'guaranteed' =>
                    array(
                        'title' => 'Guaranteed',
                        'name' => 'guaranteed',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 1,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'saved' =>
                    array(
                        'title' => 'Saved',
                        'name' => 'saved',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 1,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'touroperator' =>
                    array(
                        'title' => 'Touroperator',
                        'name' => 'touroperator',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 255,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'startingpoint' => array(
                    'title' => 'Startingpoint',
                    'name' => 'startingpoint',
                    'type' => 'relation',
                    'relation' => array(
                        'type' => 'hasOne',
                        'related_id' => 'id_starting_point',
                        'class' => '\\Pressmind\\ORM\\Object\\Touristic\\Startingpoint'
                    ),
                    'required' => false,
                    'validators' => null,
                    'filters' => null
                ),
                'transports' => array(
                    'title' => 'transports',
                    'name' => 'transports',
                    'type' => 'relation',
                    'relation' => array(
                        'type' => 'hasMany',
                        'related_id' => 'id_date',
                        'class' => '\\Pressmind\\ORM\\Object\\Touristic\\Transport'
                    ),
                    'required' => false,
                    'validators' => null,
                    'filters' => null
                )
            ),
    );

    /**
     * @return Option[]
     * @throws \Exception
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
     * @throws \Exception
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
     * @throws \Exception
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
     * @throws \Exception
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
}
