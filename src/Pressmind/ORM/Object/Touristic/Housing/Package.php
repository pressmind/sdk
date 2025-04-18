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
 * @property string $anf_code
 * @property integer $nights
 * @property string $text
 * @property string $code_ibe
 * @property string $room_type
 * @property integer $min_age
 * @property Option[] $options
 * @property DescriptionLink[] $description_links
 */
class Package extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_replace_into_on_create = true;

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
            'name' => [
                'title' => 'Name',
                'name' => 'name',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
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
            'anf_code' => [
                'title' => 'anf_code',
                'name' => 'anf_code',
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
            'nights' => [
                'title' => 'Nights',
                'name' => 'nights',
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
            ],
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'code_ibe' => [
                'title' => 'code_ibe',
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
            'room_type' => [
                'title' => 'Room_type',
                'name' => 'room_type',
                'type' => 'string',
                'required' => true,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 255,
                    ],
                ],
                'filters' => NULL,
            ],
            'options' => [
                'title' => 'Options',
                'name' => 'options',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_housing_package',
                    'class' => 'Pressmind\\ORM\\Object\\Touristic\\Option',
                    'filters' => [
                        'type' => 'housing_option',
                    ],
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'description_links' => [
                'title' => 'Description Links',
                'name' => 'description_links',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_housing_package',
                    'class' => 'Pressmind\\ORM\\Object\\Touristic\\Housing\\Package\\DescriptionLink',
                    'filters' => NULL,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'min_age' => [
                'title' => 'min_age',
                'name' => 'min_age',
                'type' => 'integer',
                'required' => false,
                'validators' => null,
                'filters' => null
            ],
        ]
    );

    public static $run_time_cache = [];

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

    /**
     * Human friendly validation
     * @param string $prefix
     * @return array
     */
    public function validate($prefix = ''){
        $result = [];
        if(!in_array($this->room_type, ['room', 'cabin'])){
            $result[] = $prefix . ' ❌  room_type is not valid (' . $this->room_type . '). valid values are: room or cabin, housing package id '.$this->id;
        }
        if(empty($this->options)){
            $result[] = $prefix . ' ❌  housing package has no options, id: ' . $this->getId();
        }
        return $result;
    }

    /**
     * Cached static shorthand for getById
     * @param $id
     * @return Package|null
     * @throws Exception
     */
    public static function getById($id){
        if(isset(self::$run_time_cache[__FUNCTION__][$id])){
            return self::$run_time_cache[__FUNCTION__][$id];
        }
        $package = self::listOne('id = "'.$id.'"');
        self::$run_time_cache[__FUNCTION__][$id] = $package;
        return $package;
    }

    /**
     * @return string
     */
    public function getNameId(){
        return md5($this->name);
    }

    /**
     * @param $id
     * @return mixed|null
     * @throws Exception
     */
    public static function getByNameId($id){
        if(isset(self::$run_time_cache[__FUNCTION__][$id])){
            return self::$run_time_cache[__FUNCTION__][$id];
        }
        $package = self::listOne('md5(name) = "'.$id.'"');
        self::$run_time_cache[__FUNCTION__][$id] = $package;
        return $package;
    }
}
