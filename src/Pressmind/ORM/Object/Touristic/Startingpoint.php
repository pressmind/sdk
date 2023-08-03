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
 * @property integer $logic
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
     * @return Option[]
     * @throws \Exception
     */
    public static function getOptionsByZipRadius($id_starting_point, $ibe_client = null, $zip = null, $radius = 20, $start = 0, $limit = 10){
        return self::getOptions($id_starting_point, $start, $limit, $ibe_client, $zip, $radius);
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
     * @return Option[]
     * @throws \Exception
     */
    public static function getPickupOptionByZip($id_starting_point, $ibe_client = null, $zip = null, $start = 0, $limit = 1){
        return self::getOptions($id_starting_point, $start, $limit, $ibe_client, $zip, 20, false, true, 'range');

    }

    /**
     * @param array $id_starting_point
     * @param int $start
     * @param int $limit
     * @param string|null $ibe_client
     * @param string $zip
     * @param int $radius
     * @param boolean $list_exits
     * @param boolean $list_pickup_service
     * @param string $zip_search radius | range
     * @return Option[]
     * @throws \Exception
     */
    public static function getOptions($id_starting_point, $start = 0, $limit = 10, $ibe_client = null, $zip = null, $radius = 20, $list_exits = false, $list_pickup_service = false, $zip_search = 'radius'){
        if(is_array($id_starting_point)){
            $id_starting_point = implode(',"', $id_starting_point);
        }
        $query = 'select o.* from pmt2core_touristic_startingpoint_options o';
        if(!empty($zip) && $zip_search == 'range') {
            $query .= ' left join pmt2core_touristic_startingpoint_options_zip_ranges zr on(o.id = zr.id_option)';
        }
        $query .= ' where `id_startingpoint` in( "' . $id_starting_point . '")';
        if($list_exits){
            $query .= ' AND (`exit` = 1 OR (`entry` = 0 AND `exit` = 0))';
        }else{
            $query .= ' AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0)) ';
        }
        $query .= 'AND `is_pickup_service` = '.($list_pickup_service ? '1' : '0');

        if(!empty($ibe_client)){
            $query .= ' and FIND_IN_SET("'.$ibe_client.'",ibe_clients)';
        }
        $Geodata = new Geodata();
        if(!empty($zip) && $zip_search == 'radius'){
            $zips = [];
            foreach($Geodata->getZipsAroundZip($zip, $radius) as $item){
                $zips[] = $item->postleitzahl;
            }
            if(!empty($zips)){
                $query .= ' and zip in("'.implode('","', $zips).'")';
            }
        }
        $currentCity = false;
        if(!empty($zip) && $zip_search == 'range') {
            $query .= ' and ' . (int)$zip . ' between cast(zr.`from` as UNSIGNED) and cast(zr.`to` as UNSIGNED)';
            $currentCity = $Geodata->getByZip($zip);
        }
        if(!empty($zips)){
            $query .= ' order by start_time ASC, FIELD(zip, "'.implode('","', $zips).'"), price ASC';
        }else{
            $query .= ' order by start_time ASC, price ASC, zip ASC';
        }
        if(!empty($limit)){
            $query .= ' limit '.$start.','.$limit;
        }
        $registry = Registry::getInstance();
        $db = $registry->get('db');
        $result = $db->fetchAll($query);
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
     * @return Option|null
     * @throws \Exception
     */
    public static function getCheapestOption($id_starting_point, $ibe_client = null, $price_gte = true){
        $key = md5(__FUNCTION__.'-'.serialize($id_starting_point).'-'.$ibe_client);
        if(isset(self::$_run_time_cache[$key])){
            return self::$_run_time_cache[$key];
        }
        if(is_array($id_starting_point)){
            $id_starting_point = implode(',"', $id_starting_point);
        }
        $query = 'select o.* from pmt2core_touristic_startingpoint_options o 
                    where `id_startingpoint` in( "' . $id_starting_point . '") 
                    AND (`entry` = 1 OR (`entry` = 0 AND `exit` = 0))';
        if(!empty($ibe_client)){
            $query .= ' and FIND_IN_SET("'.$ibe_client.'",ibe_clients)';
        }
        if($price_gte){
            $query .= ' and price >= 0';
        }
        $query .= ' order by price ASC';
        $registry = Registry::getInstance();
        $db = $registry->get('db');
        $result = $db->fetchRow($query);
        if(!empty($result)){
            $Option = new Startingpoint\Option();
            $Option->fromStdClass($result);
            self::$_run_time_cache[$key] = $Option;
            return $Option;
        }
        return null;
    }

}
