<?php

namespace Pressmind\ORM\Object\Touristic;
use Pressmind\ORM\Object\AbstractObject;
use DateTime;
use Pressmind\System\I18n;

/**
 * Class Transport
 * @property string $id
 * @property string $id_date
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $code
 * @property string $description
 * @property string $type
 * @property integer $way
 * @property float $price
 * @property integer $order
 * @property integer $state
 * @property string $code_ibe
 * @property integer $auto_book
 * @property integer $required
 * @property string $required_group
 * @property string $transport_group
 * @property string $description_long
 * @property integer $id_starting_point
 * @property DateTime $transport_date_from
 * @property DateTime $transport_date_to
 * @property integer $id_early_bird
 */
class Transport extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => 'Transport',
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_transports',
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
                'id_date' =>
                    array(
                        'title' => 'Id_date',
                        'name' => 'id_date',
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
                'description' =>
                    array(
                        'title' => 'Description',
                        'name' => 'description',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'type' =>
                    array(
                        'title' => 'Type',
                        'name' => 'type',
                        'type' => 'string',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 5,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'way' =>
                    array(
                        'title' => 'Way',
                        'name' => 'way',
                        'type' => 'integer',
                        'required' => true,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'price' =>
                    array(
                        'title' => 'Price',
                        'name' => 'price',
                        'type' => 'float',
                        'required' => true,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'order' =>
                    array(
                        'title' => 'Order',
                        'name' => 'order',
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
                'state' =>
                    array(
                        'title' => 'State',
                        'name' => 'state',
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
                'auto_book' =>
                    array(
                        'title' => 'Auto_book',
                        'name' => 'auto_book',
                        'type' => 'integer',
                        'required' => false,
                        'validators' =>
                            array(
                                0 =>
                                    array(
                                        'name' => 'maxlength',
                                        'params' => 4,
                                    ),
                            ),
                        'filters' => NULL,
                    ),
                'required' =>
                    array(
                        'title' => 'Required',
                        'name' => 'required',
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
                'required_group' =>
                    array(
                        'title' => 'Required_group',
                        'name' => 'required_group',
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
                'transport_group' =>
                    array(
                        'title' => 'Transport_group',
                        'name' => 'transport_group',
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
                'description_long' =>
                    array(
                        'title' => 'Description_long',
                        'name' => 'description_long',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'id_starting_point' =>
                    array(
                        'title' => 'Id_starting_point',
                        'name' => 'id_starting_point',
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
                'transport_date_from' =>
                    array(
                        'title' => 'Transport_date_from',
                        'name' => 'transport_date_from',
                        'type' => 'datetime',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'transport_date_to' =>
                    array(
                        'title' => 'Transport_date_to',
                        'name' => 'transport_date_to',
                        'type' => 'datetime',
                        'required' => false,
                        'validators' => NULL,
                        'filters' => NULL,
                    ),
                'id_early_bird' =>
                    array(
                        'title' => 'Id_early_bird',
                        'name' => 'id_early_bird',
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
            ),
    );

    public function mapTypeToString() {
        $mapping = [
            'BUS' => 'Busreise',
            'PKW' => 'Eigenanreise',
            'FLUG' => 'Flugreise',
            'SCHIFF' => 'Schiffsreise'
        ];
        return I18n::translate($mapping[$this->type]);
    }
}
