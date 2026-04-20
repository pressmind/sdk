<?php

namespace Pressmind\ORM\Object\Touristic;

use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\Geodata;
use \Pressmind\ORM\Object\Touristic\Startingpoint\Option;
use Pressmind\Registry;

/**
 * Class Startingpoint
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $text
 * @property integer $logic \@deprecated
 * @property Option[] $options
 */
class Startingpoint extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;

    protected $_check_variables_for_existence = false;

    protected $_replace_into_on_create = true;

    protected $_definitions = array(
        'class' => [
            'name' => self::class,
        ],
        'database' => [
            'table_name' => 'pmt2core_touristic_startingpoints',
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
            'code' => [
                'title' => 'Code',
                'name' => 'code',
                'type' => 'string',
                'required' => false,
                'validators' => [
                    [
                        'name' => 'maxlength',
                        'params' => 45,
                    ],
                ],
                'filters' => NULL,
            ],
            'name' => [
                'title' => 'Name',
                'name' => 'name',
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
            'text' => [
                'title' => 'Text',
                'name' => 'text',
                'type' => 'string',
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
            'logic' => [
                'title' => 'Logic',
                'name' => 'logic',
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
            'options' => [
                'title' => 'Options',
                'name' => 'options',
                'type' => 'relation',
                'relation' => [
                    'type' => 'hasMany',
                    'related_id' => 'id_startingpoint',
                    'class' => Option::class,
                ],
                'required' => false,
                'validators' => NULL,
                'filters' => NULL,
            ],
        ]
    );

    protected static $_run_time_cache = [];

    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @return bool
     * @throws \Exception
     */
    public static function hasPickupService($id_starting_point, $ibe_client = null){
        $options = self::getOptions($id_starting_point, 0, null, $ibe_client, null, 20, false,  true);
        foreach($options  as $option){
            if($option->is_pickup_service){
                    return true;
            }
        }
        return false;
    }

    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @return bool
     * @throws \Exception
     */
    public static function hasOptions($id_starting_point, $ibe_client = null){
        return self::getOptions($id_starting_point, 0, null, $ibe_client) > 0;
    }

    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @param null $zip
     * @param int $radius
     * @param int $start
     * @param int $limit
     * @param array $order_by_code_list
     * @return Option[]
     * @throws \Exception
     */
    public static function getOptionsByZipRadius($id_starting_point, $ibe_client = null, $zip = null, $radius = 20, $start = 0, $limit = 10, $order_by_code_list = []){
        return self::getOptions($id_starting_point, $start, $limit, $ibe_client, $zip, $radius, false, false, 'radius', $order_by_code_list);
    }

    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @param string $zip
     * @param int $radius
     * @param int $start
     * @param int $limit
     * @return Option[]
     * @throws \Exception
     */
    public static function getExitOptionsByZipRadius($id_starting_point, $ibe_client = null, $zip = null, $radius = 20, $start = 0, $limit = 10){
        return self::getOptions($id_starting_point, $start, $limit, $ibe_client, $zip, $radius, true);
    }


    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @param string $zip
     * @param int $start
     * @param int $limit
     * @param array $order_by_code_list
     * @return Option[]
     * @throws \Exception
     */
    public static function getPickupOptionByZip($id_starting_point, $ibe_client = null, $zip = null, $start = 0, $limit = 1, $order_by_code_list = []){
        return self::getOptions($id_starting_point, $start, $limit, $ibe_client, $zip, 20, false, true, 'range', $order_by_code_list);
    }

    /**
     * @param $id_starting_point
     * @param string|null $ibe_client
     * @param string $zip
     * @param array $order_by_code_list
     * @return Option[]
     * @throws \Exception
     */
    public static function getPickupOptionsByZip($id_starting_point, $ibe_client = null, $zip = null, $order_by_code_list = []){
        return self::getOptions($id_starting_point, 0, null, $ibe_client, $zip, 20, false, true, 'range', $order_by_code_list);
    }

    /**
     * @param array $id_starting_point
     * @param int $start
     * @param int|null $limit
     * @param string|null $ibe_client
     * @param string $zip
     * @param int $radius
     * @param boolean $list_exits
     * @param boolean $list_pickup_service
     * @param string $zip_search radius | range
     * @param array $order_by_code_list
     * @return Option[]
     * @throws \Exception
     */
    public static function getOptions($id_starting_point, $start = 0, $limit = 10, $ibe_client = null, $zip = null, $radius = 20, $list_exits = false, $list_pickup_service = false, $zip_search = 'radius', $order_by_code_list = []){
        $values = [];
        $id_starting_point = (array)$id_starting_point;
        $placeholders = implode(',', array_fill(0, count($id_starting_point), '?'));
        $values = array_merge($values, array_values($id_starting_point));

        $query = 'SELECT o.* FROM pmt2core_touristic_startingpoint_options o';
        if(!empty($zip) && $zip_search == 'range') {
            $query .= ' LEFT JOIN pmt2core_touristic_startingpoint_options_zip_ranges zr ON(o.id = zr.id_option)';
        }
        $query .= ' WHERE `id_startingpoint` IN(' . $placeholders . ')';
        if($list_exits){
            $query .= ' AND (`exit` = 1 OR (`entry` = 0 AND `exit` = 0))';
        }else{
            $query .= ' AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0))';
        }
        $query .= ' AND `is_pickup_service` = ?';
        $values[] = $list_pickup_service ? 1 : 0;

        if(!empty($ibe_client)){
            $query .= ' AND FIND_IN_SET(?, ibe_clients)';
            $values[] = $ibe_client;
        }
        $Geodata = new Geodata();
        $zips = [];
        if(!empty($zip) && $zip_search == 'radius'){
            foreach($Geodata->getZipsAroundZip($zip, $radius) as $item){
                $zips[] = $item->postleitzahl;
            }
            if(!empty($zips)){
                $zipPlaceholders = implode(',', array_fill(0, count($zips), '?'));
                $query .= ' AND zip IN(' . $zipPlaceholders . ')';
                $values = array_merge($values, $zips);
            }
        }
        $currentCity = false;
        if(!empty($zip) && $zip_search == 'range') {
            $query .= ' AND ? BETWEEN CAST(zr.`from` AS UNSIGNED) AND CAST(zr.`to` AS UNSIGNED)';
            $values[] = (int)$zip;
            $currentCity = $Geodata->getByZip($zip);
        }
        $order_by_field_list = ' ';
        if(!empty($order_by_code_list)){
            $sanitized_codes = array_filter($order_by_code_list, function($code) {
                return preg_match('/^[a-zA-Z0-9_\-]+$/', $code);
            });
            if(!empty($sanitized_codes)){
                $codePlaceholders = implode(',', array_fill(0, count($sanitized_codes), '?'));
                $order_by_field_list = ' FIELD(o.code, ' . $codePlaceholders . ') = 0, FIELD(o.code, ' . $codePlaceholders . '), ';
                $values = array_merge($values, array_values($sanitized_codes), array_values($sanitized_codes));
            }
        }
        if(!empty($zips)){
            $zipOrderPlaceholders = implode(',', array_fill(0, count($zips), '?'));
            $query .= ' ORDER BY' . $order_by_field_list . 'start_time ASC, FIELD(zip, ' . $zipOrderPlaceholders . '), price ASC';
            $values = array_merge($values, $zips);
        }else{
            $query .= ' ORDER BY' . $order_by_field_list . 'start_time ASC, price ASC, zip ASC';
        }
        if(!empty($limit)){
            $query .= ' LIMIT ' . (int)$start . ',' . (int)$limit;
        }
        $registry = Registry::getInstance();
        $db = $registry->get('db');
        $result = $db->fetchAll($query, $values);
        $output = [];
        foreach($result as $row){
            $Option = new Startingpoint\Option();
            $Option->fromStdClass($row);
            if($list_pickup_service) {
                $Option->zip = $zip;
                if (!empty($currentCity->gemeinde_name)) {
                    $Option->city = $currentCity->gemeinde_name;
                }
            }
            $output[] = $Option;
        }
        return $output;
    }

    /**
     * @param string|array  $id_starting_point
     * @param string $ibe_client
     * @param boolean $gte
     * @return Option|null
     * @throws \Exception
     */
    public static function getCheapestOption($id_starting_point, $ibe_client = null, $price_gte = true){
        $key = md5(__FUNCTION__.'-'.serialize($id_starting_point).'-'.$ibe_client);
        if(isset(self::$_run_time_cache[$key])){
            return self::$_run_time_cache[$key];
        }
        $values = [];
        $id_starting_point = (array)$id_starting_point;
        $placeholders = implode(',', array_fill(0, count($id_starting_point), '?'));
        $values = array_merge($values, array_values($id_starting_point));

        $query = 'SELECT o.* FROM pmt2core_touristic_startingpoint_options o
                    WHERE `id_startingpoint` IN(' . $placeholders . ')
                    AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0))';
        if(!empty($ibe_client)){
            $query .= ' AND FIND_IN_SET(?, ibe_clients)';
            $values[] = $ibe_client;
        }
        if($price_gte){
            $query .= ' AND price >= 0';
        }
        $query .= ' ORDER BY price ASC';
        $registry = Registry::getInstance();
        $db = $registry->get('db');
        $result = $db->fetchRow($query, $values);
        if(!empty($result)){
            $Option = new Startingpoint\Option();
            $Option->fromStdClass($result);
            self::$_run_time_cache[$key] = $Option;
            return $Option;
        }
        return null;
    }

    /**
     * @param string $id_starting_point
     * @param string $ibe_client
     * @param boolean $exit
     * @param boolean $pickup_service
     * @return Option|false
     * @throws \Exception
     */
    public static function getOptionByIdPlus($id_starting_point_option, $ibe_client = null, $exit = false, $pickup_service = false){
        $values = [];
        $query = 'SELECT o.* FROM pmt2core_touristic_startingpoint_options o WHERE `id` = ?';
        $values[] = $id_starting_point_option;
        if($exit){
            $query .= ' AND (`exit` = 1 OR (`entry` = 0 AND `exit` = 0))';
        }else{
            $query .= ' AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0))';
        }
        $query .= ' AND `is_pickup_service` = ?';
        $values[] = $pickup_service ? 1 : 0;

        if(!empty($ibe_client)){
            $query .= ' AND FIND_IN_SET(?, ibe_clients)';
            $values[] = $ibe_client;
        }
        $query .= ' LIMIT 1';
        $registry = Registry::getInstance();
        $db = $registry->get('db');
        $result = $db->fetchAll($query, $values);
        if(!empty($result)){
            $Option = new Startingpoint\Option();
            $Option->fromStdClass($result[0]);
            return $Option;
        }
        return false;
    }

    /**
     * Human friendly validation
     * @param string $prefix
     * @param int $way
     * @return array
     */
    public function validate($prefix = '    ', $way = 1): array
    {
        $result = [];
        if(empty($this->options)){
            $result[] = $prefix.' ❌ StartingPoint ID: ' . $this->id. ' starting point has no options (options are empty)';
            return $result;
        }
        $has_valid_options = false;
        foreach($this->options as $Option){
            if(empty($Option->entry) && empty($Option->exit)){
                $has_valid_options = true;
            }
            if($way == 1 && !empty($Option->entry)){
                $has_valid_options = true;
            }
            if($way == 2 && !empty($Option->exit)){
                $has_valid_options = true;
            }
        }
        if($has_valid_options === false){
            $result[] = $prefix.' ❌  StartingPoint ID: ' . $this->id. ' starting point has no valid options '.($way == 1 ? 'for entry' : 'for exit') ;
        }
        return $result;
    }
}
