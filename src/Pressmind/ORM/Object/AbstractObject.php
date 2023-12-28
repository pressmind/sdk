<?php

namespace Pressmind\ORM\Object;

use DateTime;
use Error;
use \Exception;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\DB\IntegrityCheck\Mysql;
use Pressmind\Import\Mapper\Factory;
use Pressmind\Log\Writer;
use Pressmind\Registry;
use Pressmind\ORM\Filter;
use Pressmind\ORM\Validator;
use \stdClass;

/**
 * Class AbstractObject
 * @package PressmindBooking\ORM
 */
#[\AllowDynamicProperties]
abstract class AbstractObject
{
    /**
     * @var array
     */
    protected $_definitions;

    /**
     * @var boolean
     */
    protected $_check_variables_for_existence = true;

    /**
     * @var boolean
     */
    protected $_dont_auto_create_has_one_relations = false;

    /**
     * @var boolean
     */
    protected $_dont_use_autoincrement_on_primary_key = false;

    /**
     * @var boolean
     */
    protected $_use_cache = false;

    /**
     * @var boolean
     */
    protected $_cache_enabled;

    /**
     * @var boolean
     */
    protected $_skip_cache_on_read = false;

    /**
     * @var boolean
     */
    protected $_disable_cache_permanently = false;

    /**
     * @var boolean
     */
    protected $_is_cached = false;

    /**
     * @var boolean
     */
    protected $_read_relations = true;

    /**
     * @var bool
     */
    protected $_replace_into_on_create = false;

    /**
     * @var bool
     * @todo necessary?
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

    /**
     * @var null
     */
    protected $_start_time = null;

    /**
     * AbstractObject constructor.
     * @param null $id
     * @param bool $readRelations
     * @throws Exception
     */
    public function __construct($id = null, $readRelations = false, $skipCache = false)
    {
        $this->_start_time = microtime(true);
        $this->_write_log('__construct(' . (is_null($id) ? 'null' : $id) . ', ' . ($readRelations == true ? 'true' : 'false') . ', ' . ($skipCache == true ? 'true' : 'false') . ')');
        $registry = Registry::getInstance();
        $this->_db = $registry->get('db');
        $this->_cache_enabled = $registry->get('config')['cache']['enabled'] && in_array('OBJECT', $registry->get('config')['cache']['types']) && $this->_use_cache;
        if(true === $this->_disable_cache_permanently) {
            $this->_cache_enabled = false;
        }
        $this->_skip_cache_on_read = $skipCache;
        $this->setReadRelations($readRelations);
        if(!is_null($id)) {
            $this->read($id);
        }
    }

    /**
     * @param string $logText
     */
    private function _write_log($logText)
    {
        $logging_enabled = Registry::getInstance()->get('config')['logging']['enable_advanced_object_log'] ?? false;
        if($logging_enabled) {
            $text = 'Time: ' . number_format(microtime(true) - $this->_start_time, 8) . " " . " Heap: " . bcdiv(memory_get_usage(), (1000 * 1000), 2) . ' MByte ' . get_class($this) . " " . $logText;
            $this->_log[] = $text;
        }
    }

    /**
     * @return array
    */
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
     * @return bool
     */
    public function isValid(){
        if (is_null($this->getId())) {
            return false;
        }
        return true;
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
        $registry = Registry::getInstance();
        $values = [];
        $result = [];
        $order_columns = [];
        $query = "SELECT * FROM " . $this->getDbTableName();
        $class_name = get_class($this);

        if (is_array($where)) {
            $query .= " WHERE ";
            $where_i = 0;
            foreach ($where as $key => $value) {
                $variable_replacement = ' ?';
                if (is_array($value) && count($value) >= 2) {
                    $operator = $value[0];
                    $value = $value[1];
                } elseif(is_array($value) && count($value) == 1) {
                    $value = $value[0];
                    $operator = '=';
                } elseif(is_array($value) && empty($value)) {
                    $value = null;
                    $operator = '=';
                } else {
                    $operator = '=';
                }
                if(strtolower($operator) == 'in' || strtolower($operator) == 'not in') {
                    $value_array = explode(',', $value);
                    $variable_replacement = ' (' . implode(',', array_fill(0,count($value_array),'?')) . ')';
                    foreach ($value_array as $item) {
                        $values[] = $item;
                    }
                }
                if($value === 'CURRENT_DATE') {
                    $now = new DateTime();
                    $value = $now->format('Y-m-d h:i:s');
                }
                if($value === 'IS NULL') {
                    $operator = 'IS NULL';
                    $variable_replacement = '';
                } else if($value === 'IS NOT NULL') {
                    $operator = 'IS NOT NULL';
                    $variable_replacement = '';
                } else if(strtolower($operator) != 'in'  && strtolower($operator) != 'not in') {
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
                $order_columns[] = '`'.$column_name . '` ' . $direction;
            }
        }

        if(!is_null($order) && is_array($order)) {
            foreach ($order as $column_name => $direction) {
                $order_columns[] = '`'.$column_name . '` ' . $direction;
            }
        }

        if((isset($this->_definitions['database']['order_columns']) && !is_null($this->_definitions['database']['order_columns'])) || (!is_null($order) && is_array($order))) {
            $query .= ' ORDER BY ' . implode(', ', $order_columns);
        }

        if(!is_null($limit)) {
            $query .= ' LIMIT ' . $limit[0] . ', ' . $limit[1];
        }

        if($registry->get('config')['cache']['enabled'] && in_array('QUERY', $registry->get('config')['cache']['types']) && $this->_use_cache) {
            $key = 'QUERY:'.md5($query . json_encode($values));
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            if($cache_adapter->exists($key)) {
                Writer::write(get_class($this) . ' loadAll() reading from cache. KEY: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
                $dataset = json_decode($cache_adapter->get($key));
            } else {
                $dataset = $this->_db->fetchAll($query, $values, $class_name);
                $cache_adapter->add($key, json_encode($dataset));
            }
        } else {
            $dataset = $this->_db->fetchAll($query, $values, $class_name);
        }
        /*foreach ($dataset as $stdObject) {
            $class_name = get_class($this);
            $object = new $class_name(null, $this->_read_relations);
            $object->fromStdClass($stdObject);
            $object->readRelations();
            $result[] = $object;
        }*/
        return $dataset;
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
        if ($id != '0' && !empty($id)) {
            if($this->_cache_enabled && !$this->_skip_cache_on_read) {
                return $this->_readFromCache($id);
            } else {
                return $this->_readFromDb($id);
            }
        }
        return null;
    }

    /**
     * @param boolean $skipCache
     */
    public function setSkipCache($skipCache) {
        $this->_skip_cache_on_read = $skipCache;
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    private function _readFromDb($id)
    {
        $this->_write_log('_readFromDb(' . $id . ')');
        Writer::write(get_class($this) . ' _readFromDb() reading from database. ID: ' . $id, Writer::OUTPUT_FILE, 'database', Writer::TYPE_DEBUG);
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
     * @throws Exception
     */
    private function _readFromCache($id)
    {
        $this->_write_log('_readFromCache(' . $id . ')');
        $key = $this->_createCacheKey($id);
        $this->_write_log(Registry::getInstance()->get('config')['cache']['adapter']['name'] . ' cache key: ' . $key);
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        Writer::write(get_class($this) . ' _readFromCache() reading from cache. ID: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
        if($cache_adapter->exists($key)) {
            $data = json_decode($cache_adapter->get($key));
            $this->fromCache($data);
        } else {
            $data = $this->addToCache($id);
        }
        $this->_is_cached = true;
        return $data;
    }

    /**
     * @param $id
     * @return string
     */
    private function _createCacheKey($id) {
        return 'OBJECT:' . $this->getDbTableName() . ':' . $id;
    }

    /**
     * Will return false if Object is not cached, otherwise will return the cache key
     * @return bool|string
     */
    public function isCached() {
        return $this->_is_cached ? $this->_createCacheKey($this->getId()) : false;
    }

    public function getCacheInfo() {
        if ($this->isCached() !== false) {
            $key = $this->_createCacheKey($this->getId());
            $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
            return $cache_adapter->getInfo($key);
        }
        return null;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    public function addToCache($id)
    {
        $key = $this->_createCacheKey($id);
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        Writer::write(get_class($this) . ' addToCache() writing to cache. ID: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
        $this->setReadRelations(true);
        $data = $this->_readFromDb($id);
        $info = new stdClass();
        $info->type = 'OBJECT';
        $info->classname = get_class($this);
        $info->method = 'addToCache';
        $info->parameters = ['id' => $id];
        $cache_adapter->add($key, json_encode($this->toStdClass()), $info);
        return $data;
    }

    /**
     * @param $id
     * @return string
     * @throws Exception
     */
    public function updateCache($id) {

        if(empty(Registry::getInstance()->get('config')['cache']['enabled'])){
            return 'updateCache(), cache is not enabled, see config.cache.enabled';
        }

        $key = $this->_createCacheKey($id);
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        Writer::write(get_class($this) . ' updateCache() writing to cache. ID: ' . $key, Writer::OUTPUT_FILE, strtolower(Registry::getInstance()->get('config')['cache']['adapter']['name']), Writer::TYPE_DEBUG);
        $this->setReadRelations(true);
        $data = $this->_readFromDb($id);
        if(!is_null($data)) {
            $info = new stdClass();
            $info->type = 'OBJECT';
            $info->classname = get_class($this);
            $info->method = 'addToCache';
            $info->parameters = ['id' => $id];
            //$cache_adapter->remove($key);
            $cache_adapter->add($key, json_encode($this->toStdClass()), $info);
            return 'Object ' . $key . ' added to cache';
        } else {
            if($cache_adapter->exists($key)) {
                $cache_adapter->remove($key);
                return 'Key ' . $key . ' deleted from cache. no media object data has been found in db';
            }
        }
    }

    /**
     *
     */
    public function removeFromCache() {
        $cache_adapter = \Pressmind\Cache\Adapter\Factory::create(Registry::getInstance()->get('config')['cache']['adapter']['name']);
        $key = $this->_createCacheKey($this->getId());
        if($cache_adapter->exists($key)) {
            $cache_adapter->remove($key);
        }
    }

    /**
     * @throws Exception
     */
    public function readRelations($exclude = [])
    {
        $this->_write_log('readRelations()');
        if(true === $this->_read_relations) {
            foreach ($this->_definitions['properties'] as $property_name => $property) {
                if($property['type'] == 'relation' && !in_array($property_name, $exclude)) {
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
        $this->_write_log('setReadRelations(' . ($readRelations == true ? 'true' : 'false') . ')');
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
        }
    }
    
    public function truncate()
    {
        $this->_db->truncate($this->getDbTableName());
    }

    /**
     * @throws Exception
     */
    private function _deleteRelations() {
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if($property['type'] == 'relation' && (!isset($property['relation']['prevent_auto_delete']) || false == $property['relation']['prevent_auto_delete'])) {
                switch (strtolower($property['relation']['type'])) {
                    case 'hasmany':
                        $this->_deleteHasManyRelation($property_name);
                        break;
                    case 'belongsto':
                        $this->_deleteBelongsToRelation($property_name);
                        break;
                    case 'manytomany':
                        $this->_unbindManyToManyRelation($property);
                        break;
                }
            }
        }
    }

    /**
     * @param string $property_name
     * @throws Exception
     */
    protected function _deleteHasManyRelation($property_name) {
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

    /**
     * @param string $property_name
     */
    private function _deleteBelongsToRelation($property_name) {
        $relation = $this->$property_name;
        if(!empty($relation)) {
            $relation->delete(true);
        }
    }

    private function _unbindManyToManyRelation($property) {
        $this->_db->delete($property['relation']['relation_table'], [$property['relation']['related_id'] . " = ?", $this->getId()]);
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

    /**
     * @param array $pArray
     */
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

    /**
     * @param boolean $with_relations
     * @return stdClass
     */
    public function toStdClass($with_relations = true)
    {
        $object = new stdClass();
        foreach ($this->_definitions['properties'] as $property_name => $property) {
            if(true == $with_relations) {
                if ($property['type'] == 'relation') {
                    $objects_to_convert = $this->$property_name;
                    if (!empty($objects_to_convert)) {
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
            } else {
                if ($property['type'] != 'relation') {
                    $object->$property_name = $this->$property_name;
                }
            }
        }
        return $object;
    }

    /**
     * @param $data
     * @return $this
     */
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
            throw new Exception('Missing required properties (create): ' . implode(', ', $required_check) . ' in ' . get_class($this));
        }
        $field_list = $this->getPropertyNames();
        $values = [];
        foreach ($field_list as $index => $field_name) {
            if ($field_name != $this->getDbPrimaryKey() || $this->_dont_use_autoincrement_on_primary_key == true) {
                $values[$field_name] = $this->parsePropertyValue($field_name, $this->$field_name, 'output');
            } else {
                unset($field_list[$index]);
            }
        }
        $id = $this->_db->insert($this->getDbTableName(), $values, $this->_replace_into_on_create);
        if($this->_dont_use_autoincrement_on_primary_key == false) {
            $this->setId($id);
        }
        $this->_createHasManyRelations();
        $this->_createHasOneRelations();
        $this->_createBelongsToRelations();
        $this->_createManyToManyRelations();

        return true;
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
                        if(isset($this->_definitions['database']['relation_key']) && $this->_definitions['database']['relation_key'] != $this->_definitions['database']['primary_key']) {
                            $key_name = $this->_definitions['database']['relation_key'];
                            $object->$related_key = $this->$key_name;
                        }
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
                $object = $this->$key;
                if(!empty($object)) {
                    if($this->_dont_auto_create_has_one_relations == false) {
                        $object->create();
                        $this->$related_key = $object->getId();
                        $this->update();
                    }
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

    private function _createManyToManyRelations() {
        foreach ($this->_definitions['properties'] as $property) {
            if ($property['type'] == 'relation' && isset($property['relation']) && strtolower($property['relation']['type']) == 'manytomany') {
                $relation_table_object_class_name = $property['relation']['relation_class'];
                $related_id_name = $property['relation']['related_id'];
                $target_id_name = $property['relation']['target_id'];
                $key = $property['name'];
                $objects = $this->$key;
                if(!empty($objects) && is_array($objects)) {
                    foreach ($objects as $object) {
                        $object->create();
                        $relation_table_object = new $relation_table_object_class_name();
                        $relation_table_object->$related_id_name = $this->getId();
                        $relation_table_object->$target_id_name = $object->id;
                        $relation_table_object->create();
                    }
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
            throw new Exception('Missing required properties (update): ' . implode(', ', $required_check));
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
        try {
            $filter = Filter\Factory::create($property_info['type'], $direction);
            /**
             * it might be that the property has additional filters assigned, so we need to apply them, too.
             */
            if(isset($property_info['filters']) && !empty($property_info['filters'])) {
                $value = $this->filterPropertyValue($name, $filter->filterValue($value), $property_info['filters']);
            } else {
                $value = $filter->filterValue($value);
            }
            if (isset($property_info['validators']) && is_array($property_info['validators'])) {
                $this->validatePropertyValue($name, $value, $property_info['validators']);
            }
            return $value;
        } catch (Exception $e) {
            echo $e->getMessage();
            return null;
        }
    }

    private function _parseHasMany($name, $values) {
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
                try {
                    $validator = Validator\Factory::create($validatorSpec);
                } catch (Exception | Error $e) {
                    throw new Exception('Validator ' . $validatorSpec['name'] . ' could not be created in class ' . get_class($this)."\n".$e->getMessage());
                }
                if (!$validator->isValid($value)) {
                    throw new Exception('Validation for property ' . $name . ' failed for class ' . get_class($this) . ': ' . $validator->getError());
                }
            }
        }
    }

    /**
     * @param $name
     * @return mixed|string|null
     * @throws Exception
     */
    public function __isset($name) {
        return $this->__get($name);
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
                if(isset($property_info['prevent_autoload']) && $property_info['prevent_autoload'] == true) {
                    //throw new Exception(get_class($this) . '::' . $name . ' needs to be called via get(\'' . $name . '\')');
                }
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
                if ($property_info['type'] == 'computed') {
                    if (!isset($this->$name) || empty($this->$name)) {
                        if(isset($property_info['computed']) && is_array($property_info['computed'])) {
                            if(isset($property_info['computed']['getter']['function'])) {
                                if(is_array($property_info['computed']['getter']['parameters'])) {
                                    $this->$name = call_user_func_array([$this, $property_info['computed']['getter']['function']], $property_info['computed']['getter']['parameters']);
                                } else {
                                    $this->$name = call_user_func([$this, $property_info['computed']['getter']['function']]);
                                }
                            }

                        }
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
            if(!empty($relation_object->getId())) {
                if (isset($property_info['relation']['filters']) && is_array($property_info['relation']['filters'])) {
                    foreach ($property_info['relation']['filters'] as $filter_name => $filter_value) {
                        if($relation_object->$filter_name != $filter_value) {
                            return null;
                        }
                    }
                }
                return $relation_object;
            }
        }
        return null;
    }

    private function getRelationBelongsTo($property_info) {
        $this->_write_log('getRelationBelongsTo(' . $property_info['name'] . ')');
        $field_name = $property_info['name'];
        if (!empty($this->getId()) && empty($this->$field_name)) {
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
        $field_name = $property_info['name'];
        if (!empty($this->getId()) && empty($this->$field_name)) {
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
        $objects = [];
        if(!isset($property_info['relation']['is_target']) || $property_info['relation']['is_target'] == false) {
            $this->_write_log('getRelationManyToMany(' . $property_info['name'] . ')');
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
            if ($property_definition['type'] != 'relation' && $property_definition['type'] != 'computed') {
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

    /**
     * @return array
     */
    public function getPropertyDefinitions()
    {
        return $this->_definitions['properties'];
    }

    /**
     * @param string $propertyName
     * @return array|null
     */
    public function getPropertyDefinition($propertyName) {
        return $this->_definitions['properties'][$propertyName] ?? null;
    }

    /**
     * @return array
     */
    public function getStorageDefinitions() {
        return $this->_definitions['database'];
    }

    /**
     * @param string $definitionName
     * @return mixed
     */
    public function getStorageDefinition($definitionName) {
        return isset($this->_definitions['database'][$definitionName]) ? $this->_definitions['database'][$definitionName] : null;
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
     * @return array
     */
    public function getDbTableIndexes()
    {
        return isset($this->_definitions['database']['indexes']) ? $this->_definitions['database']['indexes'] : [];
    }

    /**
     * Returns the column name of the primary key as defined in _definitions['database']['primary_key']
     * @return string
     */
    public function getDbPrimaryKey()
    {
        if(is_array($this->_definitions['database']['primary_key'])) {
            return implode(',', $this->_definitions['database']['primary_key']);
        }
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
     * @return boolean|array
     * @throws Exception
     */
    public function checkStorageIntegrity()
    {
        $integrityCheck = new Mysql($this);
        return $integrityCheck->check();
    }
}
