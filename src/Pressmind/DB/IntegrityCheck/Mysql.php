<?php


namespace Pressmind\DB\IntegrityCheck;


use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

class Mysql
{

    /**
     * @var AbstractObject
     */
    private $_object = null;
    
    private $_differences = [];

    /**
     * @var Pdo
     */
    private $_db = null;
    
    public function __construct($pObject) {
        $this->_object = $pObject;
        $this->_db =  Registry::getInstance()->get('db');
    }

    /**
     * @return array|bool
     * @throws Exception
     */
    public function check() {
        $type_mapper = new \Pressmind\DB\Typemapper\Mysql();
        $table = $this->_db->fetchAll('SHOW tables like "' . $this->_object->getDbTableName().'"');
        if(empty($table)){
            $this->_differences[] = ['action' => 'create_table', 'table' => $this->_object->getDbTableName(), 'msg' => get_class($this) . ': database table ' . $this->_object->getDbTableName() . ' does not exist in database and needs to be created'];
        }else{
            $table = $this->_db->fetchAll('DESCRIBE ' . $this->_object->getDbTableName());
            $this->_checkPrimaryKey();
            $database_table_info = [];
            foreach ($table as $field) {
                $field->Type = preg_replace('/^(bigint|int)(\([0-9]+)\)/', '$1', $field->Type);
                $database_table_info[$field->Field] = $field;
                if(!$this->_object->hasProperty($field->Field)) {
                    $this->_differences[] = ['action' => 'drop_column', 'column_name' => $field->Field, 'msg' => get_class($this) . ': database column ' . $field->Field . ' does not exist in objects property definition and needs to be dropped'];
                }
            }
            foreach ($this->_object->getPropertyDefinitions() as $definition) {
                if($definition['type'] != 'relation') {
                    $column_type = $type_mapper->mapTypeFromORMToMysqlWithPropertyDefinition($definition);
                    $column_name = $definition['name'];
                    $column_required = isset($definition['required']) ? $definition['required'] : false;
                    $change_to = $column_required ? 'NOT NULL' : 'NULL';
                    if (!is_null($column_type)) {
                        if(isset($database_table_info[$column_name])) {
                            $is_auto_increment = strtolower($database_table_info[$column_name]->Extra) == 'auto_increment';
                            $is_primary_key = strtolower($database_table_info[$column_name]->Key) == 'pri';
                            $database_required = strtolower($database_table_info[$column_name]->Null) == 'no';

                            if($is_auto_increment && $this->_object->dontUseAutoincrementOnPrimaryKey() == true && (!is_null($this->_object->getStorageDefinition('primary_key')) && $column_name == $this->_object->getStorageDefinition('primary_key'))) {
                                $this->_differences[] = ['action' => 'remove_auto_increment', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different auto_increment setting. auto_increment needs to be removed from ' . $column_name];
                            }
                            if(!$is_auto_increment && $this->_object->dontUseAutoincrementOnPrimaryKey() == false && (!is_null($this->_object->getStorageDefinition('primary_key')) && $column_name == $this->_object->getStorageDefinition('primary_key'))) {
                                $this->_differences[] = ['action' => 'set_auto_increment', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different auto_increment setting. auto_increment needs to be added to ' . $column_name];
                            }
                            if($column_required != $database_required) {
                                $change_from = $database_required ? 'NOT NULL' : 'NULL';
                                $this->_differences[] = ['action' => 'alter_column_null', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different IS NULL setting and needs to be altered from ' . $change_from . ' to ' . $change_to];
                            }
                            if($column_type != $database_table_info[$column_name]->Type) {
                                $this->_differences[] = ['action' => 'alter_column_type', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' has different type and needs to be altered from ' . $database_table_info[$column_name]->Type . ' to ' . strtolower($column_type)];
                            }
                        } else {
                            $this->_differences[] = ['action' => 'create_column', 'column_name' => $column_name, 'column_type' => $column_type, 'column_null' => $change_to, 'msg' => get_class($this) . ': database column ' . $column_name . ' does not exist in database and needs to be created'];
                        }
                    }
                }
            }
            $this->_checkIndexes();
        }
        return count($this->_differences) > 0 ? $this->_differences : true;
    }

    private function _checkPrimaryKey() {
        $primary_keys = $this->_db->fetchAll('SHOW KEYS FROM ' . $this->_object->getDbTableName() . ' WHERE Key_name = \'PRIMARY\'');
        $primary_key_column_names = [];
        foreach ($primary_keys as $primary_key) {
            $primary_key_column_names[] = $primary_key->Column_name;
        }
        $primary_key_column_names_string = implode(',', $primary_key_column_names);
        if(!empty($primary_key_column_names_string) && $primary_key_column_names_string != $this->_object->getDbPrimaryKey()) {
            $this->_differences[] = ['action' => 'alter_primary_key', 'column_names' => $this->_object->getDbPrimaryKey(), 'old_column_names' => $primary_key_column_names_string, 'msg' => get_class($this) . ': Current primary key is set to column \'' . $primary_key_column_names_string . '\' but is configured for: \'' . $this->_object->getDbPrimaryKey() . '\'. Primary key needs to be altered.'];
        }
    }

    /**
     * @throws Exception
     */
    private function _checkIndexes() {
        $indexes = [];
        $tmp_indexes = $this->_db->fetchAll('SHOW INDEX FROM ' . $this->_object->getDbTableName());
        foreach ($tmp_indexes as $index) {
            $indexes[$index->Key_name] = $index;
        }
        foreach ($this->_object->getPropertyDefinitions() as $definition) {
            if ($definition['type'] != 'relation') {
                if(isset($definition['index']) && is_array($definition['index'])) {
                    foreach ($definition['index'] as $index_name => $index_type) {
                        if(!isset($indexes[$index_name])) {
                            $this->_differences[] = ['action' => 'add_index', 'column_names' => [$definition['name']], 'index_name' => $index_name, 'msg' => get_class($this) . ': database column ' . $definition['name'] . ' has no index set. Index needs to be set.'];
                        }
                    }
                }
            }
        }
        $global_object_indexes = $this->_object->getStorageDefinition('indexes');
        if(!is_null($global_object_indexes) && is_array($global_object_indexes)) {
            foreach ($global_object_indexes as $index_name => $index_definition) {
                if(!isset($indexes[$index_name])) {
                    $this->_differences[] = ['action' => 'add_index', 'column_names' => $index_definition['columns'], 'index_name' => $index_name, 'msg' => get_class($this) . ': index ' . $index_name . ' for columns ' . implode(', ', $index_definition['columns']) . ' is not set.'];
                }
            }
        }
    }
}
