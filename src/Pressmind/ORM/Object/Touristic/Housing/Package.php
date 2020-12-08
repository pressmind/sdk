<?php

namespace Pressmind\ORM\Object\Touristic\Housing;

use DateTime;
use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\CheapestPriceSpeed;
use Pressmind\ORM\Object\Touristic\Housing\Package\DescriptionLink;
use Pressmind\ORM\Object\Touristic\Option;

/**
 * Class Package
 * @property string $id
 * @property integer $id_media_object
 * @property string $id_booking_package
 * @property string $name
 * @property string $code
 * @property integer $nights
 * @property string $text
 * @property string $code_ibe
 * @property string $room_type
 * @property Option[] $options
 * @property DescriptionLink[] $description_links
 */
class Package extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_definitions = array(
        'class' =>
            array(
                'name' => self::class,
            ),
        'database' =>
            array(
                'table_name' => 'pmt2core_touristic_housing_packages',
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
                'name' =>
                    array(
                        'title' => 'Name',
                        'name' => 'name',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
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
                'nights' =>
                    array(
                        'title' => 'Nights',
                        'name' => 'nights',
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
                'text' =>
                    array(
                        'title' => 'Text',
                        'name' => 'text',
                        'type' => 'string',
                        'required' => false,
                        'validators' => NULL,
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
                'room_type' =>
                    array(
                        'title' => 'Room_type',
                        'name' => 'room_type',
                        'type' => 'string',
                        'required' => true,
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
                'options' => array(
                    'title' => 'Options',
                    'name' => 'options',
                    'type' => 'relation',
                    'relation' => array(
                        'type' => 'hasMany',
                        'related_id' => 'id_housing_package',
                        'class' => Option::class,
                        'filters' => array(
                            'type' => 'housing_option'
                        )
                    ),
                    'required' => false,
                    'validators' => null,
                    'filters' => null
                ),
                'description_links' => array(
                    'title' => 'Description Links',
                    'name' => 'description_links',
                    'type' => 'relation',
                    'relation' => array(
                        'type' => 'hasMany',
                        'related_id' => 'id_housing_package',
                        'class' => DescriptionLink::class,
                        'filters' => null,
                    ),
                    'required' => false,
                    'validators' => null,
                    'filters' => null
                )
            ),
    );

    /**
     * @return mixed
     * @throws Exception
     */
    public function getCheapestPrice()
    {
        $now = new DateTime();
        $where = "id_housing_option = " . $this->getId() . " AND price_total > 0 AND date_departure > '" . $now->format('Y-m-d H:i:s') . "'";
        $cheapest_price = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 2', ['price_total' => 'ASC']);
        if(empty($cheapest_price)) {
            $cheapest_price = CheapestPriceSpeed::listAll($where . ' AND option_occupancy = 1', ['price_total' => 'ASC']);
        }
        if(empty($cheapest_price)) {
            $cheapest_price = CheapestPriceSpeed::listAll($where, ['price_total' => 'ASC']);
        }
        return $cheapest_price[0];
    }
}
