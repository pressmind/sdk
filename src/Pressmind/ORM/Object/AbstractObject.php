<?php

namespace Pressmind\ORM\Object;

use \Exception;
use Pressmind\Cache\Adapter\Redis;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\DB\Typemapper\Mysql;
use Pressmind\Import\Mapper\Factory;
use Pressmind\Registry;
use Pressmind\ORM\Filter;
use Pressmind\ORM\Validator;
use \stdClass;
use \SplSubject;
use \SplObserver;

/**
 * Class AbstractObject
 * @package PressmindBooking\ORM
 */
abstract class AbstractObject implements SplSubject
{
    /**
     * @var array
     */
    protected $_definitions;

    /**
     * @var bool
     */
    protected $_check_variables_for_existence = true;

    /**
     * @var bool
     */
    protected $_dont_use_autoincrement_on_primary_key = false;

    /**
     * @var boolean
     */
    protected $_cache_enabled;

    protected $_disable_cache_permanently = false;

    protected $_read_relations = true;

    /**
     * @var bool
     * @todo neccessary?
     */
    protected $_permissions = array(
        'read' => 'all',
        'write' => 'system'
    );

    /**
     * @var AdapterInterface
     */
    protected $_db;

    /**
     * @var array
     */
    protected $_log = [];

    protected $_start_time = null;

    /**
     * AbstractObject constructor.
     * @param null $id
     * @param bool $readRelations
     * @throws Exception
     */
    public function __construct($id = null, $readRelations = false)
    {
        $this->_start_time = microtime(true);
        $this->_write_log('__constuct()');
        $registry = Registry::getInstance();
        $this->_db = $registry->get('db');
        $this->_cache_enabled = $registry->get('config')['cache']['enabled'];
        if(true === $this->_disable_cache_permanently) {
            $this->_cache_enabled = false;
        }
        $this->setReadRelations($readRelations);
        if(!is_null($id)) {
            $this->read($id);
        }
    }

    private function _write_log($logtext)
    {
        $text =  number_format(microtime(true) - $this->_start_time, 8) . get_class($this) . " " . $logtext;
        $this->_log[] = $text;
    }

    public function getLog()
    {
        return $this->_log;
    }

    public function __call($name, $arguments)
    {
        $this->_write_log($name);
    }

    /**
     * Convenient function for persisting the dataset.
     * Will create a new dataset if primary key is not set, otherwise will update
     * @return string|void
     * @throws Exception
     */
    public function save()
    {
        if (is_null($this->getId())) {
            return $this->create();
        } else {
            return $this->update();
        }
    }

    /**
     * @param string|array $where
     * @param array $order
     * @param array $limit
     * @return array
     * @throws Exception
     */
    public function loadAll($where = null, $order = null, $limit = null)
    {
        $values = [];
        $result = [];
        $order_columns = [];
        $query = "SELECT * FROM " . $this->getDbTableName();

        if (is_array($where)) {
            $query .= " WHERE ";
            $where_i = 0;
            foreach ($where as $key => $value) {
                $variable_replacement = ' ?';
                if(is_array($value)) {
                    $operator = $value[0];
                    $value = $value[1];
                } else {
                    $operator = '=';
                }
                if(strtolower($operator) == 'in') {
                    $value_array = explode(',', $value);
                    $variable_replacement = ' (' . implode(',', array_fill(0,count($value_array),'?')) . ')';
                    foreach ($value_array as $item) {
                        $values[] = $item;
                    }
                }
                if($value === 'CURRENT_DATE') {
                    $now = new \DateTime();
                    $value = $now->format('Y-m-d h:i:s');
                }
                if($value === 'IS NULL') {
                    $operator = 'IS NULL';
                    $variable_replacement = '';
                } else if($value === 'IS NOT NULL') {
                    $operator = 'IS NOT NULL';
                    $variable_replacement = '';
                } else if(strtolower($operator) != 'in') {
                    $values[] = $value;
                }
                $keys[] = $key;
                if($where_i > 0) {
                    $query .= ' AND ';
                }
                $query .= $key . ' ' . $operator . $variable_replacement;
                $where_i++;
            }
        } else if(!is_null($where)) {
            $query .= " WHERE " . $where;
        }
        if(isset($this->_definitions['database']['order_columns']) && !is_null($this->_definitions['database']['order_columns'])) {
            foreach ($this->_definitions['database']['order_columns'] as $column_name => $direction) {
                $order_columns[] = $column_name . ' ' . $direction;
            }
        }

        if(!is_null($order) && is_array($order)) {
            foreach ($order as $column_name => $direction) {
                $order_columns[] = $column_name . ' ' . $direction;
            }
        }

        if((isset($this->_definitions['database']['order_columns']) && !is_null($this->_definitions['database']['order_columns'])) || (!is_null($order) && is_array($order))) {
            $query .= ' ORDER BY ' . implode(', ', $order_columns);
        }

        if(!is_null($limit)) {
            $query .= ' LIMIT ' . $limit[0] . ', ' . $limit[1];
        }

        if($this->_cache_enabled) {
            $key = md5($query . json_encode($values));
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if($cache_adapter->exists($key)) {
                $dataset = json_decode($cache_adapter->get($key));
            } else {
                $dataset = $this->_db->fetchAll($query, $values);
                $cache_adapter->add($key, json_encode($dataset));
            }
        } else {
            $dataset = $this->_db->fetchAll($query, $values);
        }
        /*echo $query;
        print_r($values);
        print_r($dataset);*/
        foreach ($dataset as $stdObject) {
            /**@var AbstractObject $object * */
            $class_name = get_class($this);
            $object = new $class_name(null, $this->_read_relations);
            $object->fromStdClass($stdObject);
            $object->readRelations();
            $result[] = $object;
        }
        return $result;
    }

    /**
     * Static wrapper for loadAll function
     * @param string $where
     * @uses AbstractObject::loadAll()
     * @return array
     * @throws Exception
     */
    public static function listAll($where = null, $order = null, $limit = null) {
        /**@var AbstractObject $object**/
        $object_name = get_called_class();
        $object = new $object_name();
        return $object->loadAll($where, $order, $limit);
    }

    /**
     * @param null $where
     * @param null $order
     * @return mixed|null
     * @throws Exception
     */
    public static function listOne($where = null, $order = null) {
        $return = null;
        /**@var AbstractObject $object**/
        $object_name = get_called_class();
        $object = new $object_name();
        $result = $object->loadAll($where, $order, [0,1]);
        if(count($result) > 0) {
            $return = $result[0];
        }
        return $return;
    }

    /**
     * @param mixed $id
     * @throws Exception
     */
    public function read($id)
    {
        $this->_write_log('read(' . $id . ')');
        $cache_adapter = null;
        if ($id != '0' && !empty($id)) {
            if($this->_cache_enabled) {
                $this->_readFromCache($id);
            } else {
                $this->_readFromDb($id);
            };
        }
        return true;
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    private function _readFromDb($id)
    {
        $this->_write_log('_readFromDb( ' . $id . ' )');
        $query = "SELECT * FROM " .
            $this->getDbTableName() .
            " WHERE " . $this->_definitions['database']['primary_key'] .
            " = ?";
        $data = $this->_db->fetchRow($query, [$id]);
        $this->fromStdClass($data);
        return $data;
    }

    /**
     * @param $id
     * @return mixed
     */
    private function _readFromCache($id)
    {
        $this->_write_log('_readFromCache( ' . $id . ' )');
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if($cache_adapter->exists($this->getDbTableName() . '_' . $id)) {
            //var_dump($cache_adapter->get($this->getDbTableName() . '_' . $id));
            $data = json_decode($cache_adapter->get($this->getDbTableName() . '_' . $id));
            $this->fromCache($data);
        } else {
            $data = $this->addToCache($id);
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function addToCache($id)
    {
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        $this->setReadRelations(true);
        $data = $this->_readFromDb($id);
        $cache_adapter->add($this->getDbTableName() . '_' . $id, json_encode($this->toStdClass()));
        return $data;
    }

    /**
     *
     */
    public function removeFromCache() {
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        if($cache_adapter->exists($this->getDbTableName() . '_' . $this->getId())) {
            $cache_adapter->remove($this->getDbTableName() . '_' . $this->getId());
        }
    }

    /**
     * @throws Exception
     */
    public function readRelations()
    {
        $this->_write_log('readRelations()');
        if(true === $this->_read_relations) {
            foreach ($this->_definitions['properties'] as $property_name => $property) {
                if($property['type'] == 'relation') {
                    $this->__get($property_name);
                }
            }
        }
    }

    /**
     * @param boolean $readRelations
     */
    public function setReadRelations($readRelations)
    {
        $this->_write_log('setReadRelations(' . $readRelations . ')');
        $this->_read_relations = $readRelations;
    }

    /**
     * @param boolean $deleteRelations
     * @throws Exception
     */
    public function delete($deleteRelations = false)
    {
        $this->_db->delete($this->getDbTableName(), [$this->getDbPrimaryKey() . " = ?", $this->getId()]);
        if(true === $deleteRelations) {
            $this->_deleteRelations();
            /*$this->_deleteHasManyRelations($deleteRelations);
            $this->_deleteHasOneRelations();
            $this->_deleteManyToManyRelations();*/
        }
    }

    /**
     * @throws Exception
     */
    private function _deleteRelations() {
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if($property['type'] == 'relation') {
                switch ($property['relation']['type']) {
                    case 'hasMany':
                        $this->_deleteHasManyRelation($property_name);
                        break;
                    case 'belongsTo':
                        $this->_deleteBelongsToRelation($property_name);
                        break;
                }
            }
        }
    }

    /**
     * @param string $property_name
     * @throws Exception
     */
    private function _deleteHasManyRelation($property_name) {
        /** @var AbstractObject[] $relations */
        $relations = $this->$property_name;
        if(is_array($relations)) {
            foreach ($relations as $relation) {
                if (!empty($relation)) {
                    $relation->delete(true);
                }
            }
        }
    }

    private function _deleteBelongsToRelation($property_name) {
        $relation = $this->$property_name;
        if(!empty($relation)) {
            $relation->delete(true);
        }
    }


    /**
     * @param bool $deleteRelations
     */
    private function _deleteHasManyRelations($deleteRelations = false)
    {
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if($property['type'] == 'relation' && $property['relation']['type'] == 'hasMany') {
                foreach ($this->$property_name as $relation) {
                    $relation->delete($deleteRelations);
                }
            }
        }
    }

    private function _deleteHasOneRelations()
    {

    }

    private function _deleteManyToManyRelations()
    {

    }

    /**
     * @param stdClass $object
     * @throws Exception
     */
    public function fromStdClass($object)
    {
        if (is_a($object, 'stdClass')) {
            foreach ($object as $key => $value) {
                $this->$key = $value;
            }
        }
        $this->readRelations();
    }

    public function fromArray($pArray) {
        foreach ($pArray as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * @param stdClass $pImport
     */
    public function fromImport($pImport) {
        foreach ($pImport as $key => $value) {
            if(isset($this->_definitions['properties'][$key])) {
                if ($this->_definitions['properties'][$key]['type'] == 'relation') {
                    $class_array = explode('\\', $this->_definitions['properties'][$key]['relation']['class']);
                    $class_name = $class_array[count($class_array) - 1];
                    if ($mapper = Factory::create($class_name)) {
                        $this->$key = $mapper->map($this->id_media_object, $this->language, $key, $value);
                    } else {
                        $this->$key = $value;
                    }
                } else {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * @param $jsonString
     * @throws Exception
     */
    public function fromJson($jsonString) {
        if($object = json_decode($jsonString)) {
            $this->fromStdClass($object);
        } else {
            throw new Exception('Decoding of JSON String failed: ' . json_last_error_msg());
        }
    }

    public function toStdClass()
    {
        $object = new stdClass();
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if($property['type'] == 'relation') {
                $objects_to_convert = $this->$property_name;
                if(!empty($objects_to_convert)) {
                    if ($property['relation']['type'] == 'hasOne' || $property['relation']['type'] == 'belongsTo') {
                        $objects_to_convert = [$this->$property_name];
                    }
                    foreach ($objects_to_convert as $object_to_convert) {
                        if ($property['relation']['type'] == 'hasOne' || $property['relation']['type'] == 'belongsTo') {
                            $object->$property_name = $object_to_convert->toStdClass();
                        } else {
                            $object->$property_name[] = $object_to_convert->toStdClass();
                        }
                    }
                } else {
                    $object->$property_name = null;
                }
            } else {
                $object->$property_name = $this->$property_name;
            }
        }
        return $object;
    }

    public function fromCache($data) {
        $this->_write_log('fromCache()');
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if($property['type'] == 'relation') {
                $objects_to_convert = $data->$property_name;
                if(!empty($objects_to_convert)) {
                    if ($property['relation']['type'] == 'hasOne' || $property['relation']['type'] == 'belongsTo') {
                        $objects_to_convert = [$objects_to_convert];
                    }
                    $value = null;
                    foreach ($objects_to_convert as $object_to_convert) {
                        $class_name = $property['relation']['class'];
                        $object = new $class_name();
                        //$this->$property_name[] = null;
                        if(isset($property['relation']['from_factory']) && $property['relation']['from_factory'] === true) {
                            $factory_class_name = $property['relation']['class'];
                            $parameters = [];
                            foreach ($property['relation']['factory_parameters'] as $parameter) {
                                $parameters[] = $this->$parameter;
                            }
                            $parameters[] = $object_to_convert;
                            $value[] = call_user_func_array([$factory_class_name, 'readFromCache'], $parameters);
                        } else if ($property['relation']['type'] == 'hasOne' || $property['relation']['type'] == 'belongsTo') {
                            $value = $object->fromCache($object_to_convert);
                        } else {
                            $value[] = $object->fromCache($object_to_convert);
                        }
                        $this->$property_name = $value;
                    }
                } else {
                    $this->$property_name = null;
                }
            } else {
                $this->$property_name = $data->$property_name;
            }
        }
        return $this;
    }

    /**
     * Returns a JSON string of the current object
     * @return false|string
     */
    public function toJson()
    {
        return json_encode($this->toStdClass());
    }

    /**
     * @throws Exception
     */
    public function create()
    {
        $required_check = $this->checkForRequiredProperties();
        if ($required_check !== true) {
            throw new Exception('Missing required properties: ' . implode(', ', $required_check) . ' in ' . get_class($this));
        }
        $fieldlist = $this->getPropertyNames();
        $values = [];
        foreach ($fieldlist as $index => $fieldname) {
            if ($fieldname != $this->getDbPrimaryKey() || $this->_dont_use_autoincrement_on_primary_key == true) {
                $values[$fieldname] = $this->parsePropertyValue($fieldname, $this->$fieldname, 'output');
            } else {
                unset($fieldlist[$index]);
            }
        }
        $id = $this->_db->insert($this->getDbTableName(), $values);
        if($this->_dont_use_autoincrement_on_primary_key == false) {
            $this->setId($id);
        }
        $this->_createHasManyRelations();
        //$this->_createHasOneRelations();
        $this->_createBelongsToRelations();
    }

    /**
     * @throws Exception
     */
    private function _createHasManyRelations()
    {
        foreach ($this->_definitions['properties'] as $property) {
            if($property['type'] == 'relation' && isset($property['relation']) && $property['relation']['type'] == 'hasMany') {
                $key = $property['name'];
                $related_key = $property['relation']['related_id'];
                if(!empty($this->$key)) {
                    foreach ($this->$key as $object) {
                        /**@var AbstractObject $object**/
                        if(isset($property['relation']['on_save_related_properties']) && is_array($property['relation']['on_save_related_properties'])) {
                            foreach($property['relation']['on_save_related_properties'] as $local_property_name => $foreign_property_name) {
                                $object->$foreign_property_name = $this->$local_property_name;
                            }
                        }
                        $object->$related_key = $this->getId();
                        $object->create();
                    }
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function _createHasOneRelations() {
        foreach ($this->_definitions['properties'] as $property) {
            if ($property['type'] == 'relation' && isset($property['relation']) && $property['relation']['type'] == 'hasOne') {
                $key = $property['name'];
                $related_key = $property['relation']['related_id'];
                if(!empty($this->$key)) {
                    /**@var AbstractObject $object**/
                    $object = $this->$key;
                    print_r($object);
                    if(isset($property['relation']['on_save_related_properties']) && is_array($property['relation']['on_save_related_properties'])) {
                        foreach($property['relation']['on_save_related_properties'] as $local_property_name => $foreign_property_name) {
                            $object->$foreign_property_name = $this->$local_property_name;
                        }
                    }
                    $object->$related_key = $this->getId();
                    $object->create();
                }
            }
        }
    }

    /**
     * @throws Exception
     */
    private function _createBelongsToRelations() {
        foreach ($this->_definitions['properties'] as $property) {
            if ($property['type'] == 'relation' && isset($property['relation']) && $property['relation']['type'] == 'belongsTo') {
                $key = $property['name'];
                $related_key = $property['relation']['related_id'];
                if(!empty($this->$key)) {
                    /**@var AbstractObject $object**/
                    $object = $this->$key;
                    if(isset($property['relation']['on_save_related_properties']) && is_array($property['relation']['on_save_related_properties'])) {
                        foreach($property['relation']['on_save_related_properties'] as $local_property_name => $foreign_property_name) {
                            $object->$foreign_property_name = $this->$local_property_name;
                        }
                    }
                    $object->$related_key = $this->getId();
                    $object->create();
                }
            }
        }
    }

    /**
     * @return string|void
     * @throws Exception
     */
    public function update()
    {
        $required_check = $this->checkForRequiredProperties();
        if ($required_check != true) {
            throw new Exception('Missing required properties: ' . implode(', ', $required_check));
        }
        $field_list = $this->getPropertyNames();
        $values = [];
        foreach ($field_list as $field_name) {
            if ($field_name != $this->getDbPrimaryKey()) {
                $values[$field_name] = $this->parsePropertyValue($field_name, $this->$field_name, 'output');
            }
        }
        return $this->_db->update($this->getDbTableName(), $values, ["id = ?" , $this->getId()]);
    }

    /**
     * @return array|bool
     */
    private function checkForRequiredProperties()
    {
        $missing_properties = [];
        foreach ($this->_definitions['properties'] as $property_name => $property_definition) {
            if ($property_definition['required'] == true) {
                switch($property_definition['type']) {
                    case 'string':
                        if(empty($this->$property_name)) $missing_properties[] = $property_name;
                        break;
                    case 'boolean':
                    case 'date':
                    case 'datetime':
                    case 'time':
                    case 'float':
                    case 'integer':
                        if($this->$property_name === '') $missing_properties[] = $property_name;
                        break;
                }
            }
        }
        if (count($missing_properties) > 0) {
            return $missing_properties;
        }
        return true;
    }

    /**
     * Just a helper function for debugging, will return the print_r of an clone of the object with only the properties remaining
     * @return string
     */
    public function dumpObject()
    {
        return print_r($this->toObject(), true);
    }

    /**
     * @return AbstractObject
     */
    public function toObject()
    {
        $object = clone($this);
        unset($object->_db);
        unset($object->_dont_use_autoincrement_on_primary_key);
        unset($object->_check_variables_for_existence);
        unset($object->_permissions);
        unset($object->_read_relations);
        unset($object->_cache_enabled);
        unset($object->_definitions);
        return $object;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->{$this->_definitions['database']['primary_key']};
    }

    public function setId($pId)
    {
        $this->{$this->_definitions['database']['primary_key']} = $pId;
    }

    private function _getRelationId()
    {
        return isset ($this->_definitions['database']['relation_key']) ? $this->{$this->_definitions['database']['relation_key']} : $this->getId();
    }

    /**
     * @param string $name
     * @param mixed $value
     * @throws Exception
     */
    public function __set($name, $value)
    {
        $this->_write_log('__set(' . $name . ')');
        if (isset($this->_definitions['properties'][$name]) ) {
            try {
                $this->$name = $this->parsePropertyValue($name, $value);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        } else if($this->_check_variables_for_existence == true) {
            throw new Exception('Variable ' . $name . ' does not exist in class ' . $this->_definitions['class']['name']);
        }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param string $direction
     * @return mixed|null
     * @throws Exception
     */
    private function parsePropertyValue($name, $value, $direction = 'input')
    {
        $property_info = $this->_definitions['properties'][$name];
        if ($property_info['type'] == 'relation') {
            $class = $property_info['relation']['class'];
            if(is_a($value, $class)) {
                return $value;
            }
            if($property_info['relation']['type'] == 'hasOne' || $property_info['relation']['type'] == 'belongsTo') {
                $value = [$value];
            }
            $new_values = [];
            if(is_array($value)) {
                foreach ($value as $tmp_value) {
                    if (is_a($tmp_value, $class)) {
                        $new_values[] = $tmp_value;
                    } else if (is_a($tmp_value, 'stdClass') && (!isset($property_info['relation']['from_factory']) || ($property_info['relation']['from_factory'] != true))) {
                        $object = new $class();
                        $object->fromStdClass($tmp_value);
                        $new_values[] = $object;
                    } else {
                        $new_values[] = $tmp_value;
                    }
                }
                if ($property_info['relation']['type'] == 'hasOne' || $property_info['relation']['type'] == 'belongsTo') {
                    return $new_values[0];
                }
            }
            return $new_values;
        }
        if(empty($value) && (isset($property_info['default_value']) && !is_null($property_info['default_value']))) {
            $value = $property_info['default_value'];
        }
        if (isset($property_info['validators']) && is_array($property_info['validators'])) {
            $this->validatePropertyValue($name, $value, $property_info['validators']);
        }
        try {
            $filter = Filter\Factory::create($property_info['type'], $direction);
            /**
             * it might be that the property has additional filters assigned, so we need to apply them, too.
             */
            return $this->filterPropertyValue($name, $filter->filterValue($value), $property_info['filters']);
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }

    private function parseHasMany($name, $values) {
        $property_info = $this->_definitions['properties'][$name];
        $class_name = $property_info['relation']['class'];
        $result = [];
        if(!class_exists($class_name)) {
            throw new Exception('Class ' . $class_name . ' does not exist!');
        }
        if(!is_array($values)) $values = [$values];
        foreach ($values as $value) {
            /**@var AbstractObject $class**/
            $class = new $class_name();
            $class->fromStdClass($value);
            $result[] = $class;
        }
        return $result;
    }

    /**
     * @param $name
     * @param $value
     * @param $filterSpecs
     * @param string $direction
     * @return mixed
     * @throws Exception
     */
    private function filterPropertyValue($name, $value, $filterSpecs, $direction = 'input')
    {
        if(is_array($filterSpecs)) {
            foreach ($filterSpecs as $filterSpec) {
                try {
                    $filter = Filter\Factory::create($filterSpec['name'], $direction, $filterSpec['params']);
                    return $filter->filterValue($value);
                } catch (Exception $e) {
                    throw new Exception('Filter for property ' . $name . ' failed for class ' . get_class($this) . ': ' . $filter->getErrors());
                }
            }
        }
        return $value;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param array $validatorSpecs
     * @throws Exception
     */
    private function validatePropertyValue($name, $value, $validatorSpecs)
    {
        if(!empty($value)) {
            foreach ($validatorSpecs as $validatorSpec) {
                $validator = Validator\Factory::create($validatorSpec);
                if (!$validator->isValid($value)) {
                    throw new Exception('Validation for property ' . $name . ' failed for class ' . get_class($this) . ': ' . $validator->getError());
                }
            }
        }
    }

    /**
     * @param string $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        $this->_write_log('__get(' . $name . ')');
        if(empty($this->$name)) {
            if ($name != '_definitions' && isset($this->_definitions['properties'][$name])) {
                $property_info = $this->_definitions['properties'][$name];
                if ($property_info['type'] == 'relation') {
                    $relation = null;
                    if (!isset($this->$name) || empty($this->$name)) {
                        if ($property_info['relation']['type'] == 'hasOne') {
                            $relation = $this->getRelationHasOne($property_info);
                        } else if ($property_info['relation']['type'] == 'hasMany') {
                            $relation = $this->getRelationHasMany($property_info);
                        } else if ($property_info['relation']['type'] == 'belongsTo') {
                            $relation = $this->getRelationBelongsTo($property_info);
                        } else if ($property_info['relation']['type'] == 'ManyToMany') {
                            $relation = $this->getRelationManyToMany($property_info);
                        }
                        $this->$name = $relation;
                    }
                }
            } else if ($name != '_definitions' || $this->_check_variables_for_existence == false) {
                return '### ' . $name . ' does not exist in object type ' . $this->_definitions['class']['name'].' ###';
            }
        }
        return isset($this->$name) ? $this->$name : null;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->dumpObject();
    }

    /**
     * @param array $property_info
     * @return AbstractObject
     * @throws Exception
     */
    private function getRelationHasOne($property_info)
    {
        $this->_write_log('getRelationHasOne(' . $property_info['name'] . ')');
        $relation_object_id_name = $property_info['relation']['related_id'];
        if(!empty($this->$relation_object_id_name)) {
            $relation_class_name = $property_info['relation']['class'];
            /**@var $relation_object AbstractObject* */
            $relation_object = new $relation_class_name($this->$relation_object_id_name, $this->_read_relations);
            //$relation_object->read()
            return $relation_object;
        }
        return null;
    }

    private function getRelationBelongsTo($property_info) {
        $this->_write_log('getRelationBelongsTo(' . $property_info['name'] . ')');
        $fieldname = $property_info['name'];
        if (!empty($this->getId()) && empty($this->$fieldname)) {
            if(isset($property_info['relation']['from_factory']) && $property_info['relation']['from_factory'] === true) {
                $factory_class_name = $property_info['relation']['class'];
                $parameters = [];
                foreach ($property_info['relation']['factory_parameters'] as $parameter) {
                    $parameters[] = $this->$parameter;
                }
                $relation_object = call_user_func_array([$factory_class_name, $property_info['relation']['factory_method']], $parameters);
            } else {
                $relation_class_name = $property_info['relation']['class'];
                /**@var $relation_object AbstractObject* */
                $relation_object = new $relation_class_name(null, $this->_read_relations);
            }
            $filters = [$property_info['relation']['related_id'] => $this->_getRelationId()];
            if(isset($property_info['relation']['related_foreign_id'])) {
                $local_relation_name = $property_info['relation']['related_id'];
                $filters = [$property_info['relation']['related_foreign_id'] => $this->$local_relation_name];
            }
            if (isset($property_info['relation']['filters']) && is_array($property_info['relation']['filters'])) {
                $filters = array_merge($filters, $property_info['relation']['filters']);
            }
            $order = null;
            if (isset($property_info['relation']['order_columns']) && is_array($property_info['relation']['order_columns'])) {
                $order = $property_info['relation']['order_columns'];
            }
            $array_of_objects = $relation_object->loadAll($filters, $order);
            return count($array_of_objects) > 0 ? $array_of_objects[0] : null;
        } else {
            return null;
        }
    }

    /**
     * @param array $property_info
     * @return array
     * @throws Exception
     */
    private function getRelationHasMany($property_info)
    {
        $this->_write_log('getRelationHasMany(' . $property_info['name'] . ')');
        $fieldname = $property_info['name'];
        if (!empty($this->getId()) && empty($this->$fieldname)) {
            if(isset($property_info['relation']['from_factory']) && $property_info['relation']['from_factory'] === true) {
                $factory_class_name = $property_info['relation']['class'];
                $parameters = [];
                foreach ($property_info['relation']['factory_parameters'] as $parameter) {
                    $parameters[] = $this->$parameter;
                }
                $parameters[] = $this->_read_relations;
                $relation_object = call_user_func_array([$factory_class_name, $property_info['relation']['factory_method']], $parameters);
            } else {
                $relation_class_name = $property_info['relation']['class'];
                /**@var $relation_object AbstractObject* */
                $relation_object = new $relation_class_name(null, $this->_read_relations);
            }
            $filters = [$property_info['relation']['related_id'] => $this->_getRelationId()];
            if(isset($property_info['relation']['related_foreign_id'])) {
                $local_relation_name = $property_info['relation']['related_id'];
                $filters = [$property_info['relation']['related_foreign_id'] => $this->$local_relation_name];
            }
            if (isset($property_info['relation']['filters']) && is_array($property_info['relation']['filters'])) {
                $filters = array_merge($filters, $property_info['relation']['filters']);
            }
            $order = null;
            if (isset($property_info['relation']['order_columns']) && is_array($property_info['relation']['order_columns'])) {
                $order = $property_info['relation']['order_columns'];
            }
            $array_of_objects = $relation_object->loadAll($filters, $order);
            return $array_of_objects;
        } else {
            return [];
        }
    }

    /**
     * @param array $property_info
     * @return array
     * @throws Exception
     */
    private function getRelationManyToMany($property_info)
    {
        $this->_write_log('getRelationManyToMany(' . $property_info['name'] . ')');
        $objects = [];
        $object_name = $property_info['relation']['class'];
        /**@var AbstractObject $object * */
        $object = new $object_name();
        $table = $object->getDbTableName();
        $primary_key = $object->getDbPrimaryKey();
        $join_table = $property_info['relation']['relation_table'];
        $target_id = $property_info['relation']['target_id'];
        $related_id = $property_info['relation']['related_id'];
        $properties = $object->getPropertyNames();
        $sql = "SELECT " . $table . "." . implode(', ' . $table . ".", $properties) . " FROM " . $table . " 
            INNER JOIN " . $join_table . " 
            ON " . $join_table . "." . $target_id . " = " . $table . "." . $primary_key . " 
            AND " . $join_table . "." . $related_id . " = ?";
        $result = $this->_db->fetchAll($sql, [$this->getId()]);
        foreach ($result as $row) {
            /**@var AbstractObject $new_object * */
            $new_object = new $object_name(null, $this->_read_relations);
            $new_object->fromStdClass($row);
            $objects[] = $new_object;
        }
        return $objects;
    }

    /**
     * Returns all registered property names as defined in _definitions['properties'], will omit the property name, if type is 'relation'
     * @return array
     */
    public function getPropertyNames()
    {
        $property_names = [];
        foreach ($this->_definitions['properties'] as $property_name => $property_definition) {
            if ($property_definition['type'] != 'relation') {
                $property_names[] = $property_name;
            }
        }
        return $property_names;
    }

    /**
     * Check if a property exists in this classes property definitions
     * @param string $propertyName
     * @return bool
     */
    public function hasProperty($propertyName)
    {
        return isset($this->_definitions['properties'][$propertyName]);
    }

    public function getPropertyDefinitions()
    {
        return $this->_definitions['properties'];
    }

    public function getPropertyDefinition($propertyName) {
        return $this->_definitions['properties'][$propertyName];
    }

    /**
     * Returns the table name of the representing database table in _definitions['database']['table_name']
     * @return string
     */
    public function getDbTableName()
    {
        return $this->_db->getTablePrefix() . $this->_definitions['database']['table_name'];
    }

    /**
     * Returns the column name of the primary key as defined in _definitions['database']['primary_key']
     * @return string
     */
    public function getDbPrimaryKey()
    {
        return $this->_definitions['database']['primary_key'];
    }

    /**
     * Finds an object by key and value in an array, mainly used to find a specific object with unique key in an array of relations (HasMany or ManyToMany).
     * Returns exactly one object, if multiple objects are found the first one is returned.
     * To find multiple objects
     * @param string $property_name
     * @param string $key
     * @param mixed $value
     * @return AbstractObject|null
     * @throws Exception
     * @see self::findObjectsInArray
     */
    public function findObjectInArray($property_name, $key, $value)
    {
        if (isset($this->_definitions['properties'][$property_name])) {
            if (is_array($this->$property_name)) {
                foreach ($this->$property_name as $object) {
                    if (isset($object->$key) && $value == $object->$key) {
                        return $object;
                    }
                }
                return null;
            } else {
                throw new Exception('Property ' . $property_name . ' in class ' . $this->_definitions['class']['name'] . ' is not an array');
            }
        } else {
            throw new Exception('Property ' . $property_name . ' does not exist in class ' . $this->_definitions['class']['name']);
        }
    }

    /**
     * Find one or more objects by key and value in an array, mainly used to find objects in an array of relations (HasMany or ManyToMany).
     * Returns an array of matching objects
     * @param $property_name
     * @param $key
     * @param $value
     * @return array
     * @throws Exception
     */
    public function findObjectsInArray($property_name, $key, $value)
    {
        $objects = [];
        if (isset($this->_definitions['properties'][$property_name])) {
            if (is_array($this->$property_name)) {
                foreach ($this->$property_name as $object) {
                    if (isset($object->$key) && $value == $object->$key) {
                        $objects[] = $object;
                    }
                }
                return $objects;
            } else {
                throw new Exception('Property ' . $property_name . ' in class ' . $this->_definitions['class']['name'] . ' is not an array');
            }
        } else {
            throw new Exception('Property ' . $property_name . ' does not exist in class ' . $this->_definitions['class']['name']);
        }
    }

    /**SplSubject interface**/

    /**
     * @param SplObserver $observer
     */
    public function attach(SplObserver $observer)
    {
        // TODO: Implement attach() method.
    }

    /**
     * @param SplObserver $observer
     */
    public function detach(SplObserver $observer)
    {
        // TODO: Implement detach() method.
    }

    public function notify()
    {
        // TODO: Implement notify() method.
    }

    /**
     * @return bool
     */
    public function dontUseAutoincrementOnPrimaryKey()
    {
        return $this->_dont_use_autoincrement_on_primary_key;
    }

    public function renderApiOutputTemplate($templateName) {
        $config = Registry::getInstance()->get('config');
        $script_path = str_replace('APPLICATION_PATH', APPLICATION_PATH, $config['view_scripts']['base_path']) . DIRECTORY_SEPARATOR . ucfirst($templateName);
        require_once $script_path . '.php';
        $classname = ucfirst($templateName);
        $renderer = new $classname($this);
        return $renderer->render();
    }

    /**
     * @throws Exception
     */
    public function checkStorageIntegrity()
    {
        /**@var Pdo $db**/
        $db = Registry::getInstance()->get('db');
        $type_mapper = new Mysql();
        $table = $db->fetchAll('DESCRIBE ' . $this->getDbTableName());
        $database_table_info = [];
        $differences = [];
        foreach ($table as $field) {
            $database_table_info[$field->Field] = $field;
        }
        foreach ($this->getPropertyDefinitions() as $definition) {
            if($definition['type'] != 'relation') {
                $column_type = $type_mapper->mapTypeFromORMToMysqlWithPropertyDefinition($definition);
                $column_name = $definition['name'];
                $column_required = isset($definition['required']) ? $definition['required'] : false;
                if (!is_null($column_type)) {
                    if(isset($database_table_info[$column_name])) {
                        $database_required = strtolower($database_table_info[$column_name]->Null) == 'no' ? true : false;
                        if($column_required != $database_required) {
                            $change_from = $database_required ? 'NOT NULL' : 'NULL';
                            $change_to = $column_required ? 'NOT NULL' : 'NULL';
                            $differences[] = ['action' => 'alter_column_null', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different IS NULL setting and needs to be altered from ' . $change_from . ' to ' . $change_to];
                        }
                        if($column_type != $database_table_info[$column_name]->Type) {
                            $differences[] = ['action' => 'alter_column_type', 'column_name' => $column_name, 'column_type' => $column_type, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different type and needs to be altered from ' . $database_table_info[$column_name]->Type . ' to ' . strtolower($column_type)];
                        }
                    } else {
                        $differences[] = ['action' => 'create_column', 'column_name' => $column_name, 'column_type' => $column_type, 'msg' => get_class($this) . ': database column ' . $column_name . ' does not exist in database and needs to be created'];
                    }
                }
            }
        }
        return count($differences) > 0 ? $differences : true;
    }
}
