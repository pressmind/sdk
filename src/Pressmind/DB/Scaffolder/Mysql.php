<?php


namespace Pressmind\DB\Scaffolder;


use Exception;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\Registry;

/**
 * Class Mysql
 * @package Pressmind\DB\Scaffolder
 * Creates or alters MySQL/MariaDB database tables based on the definitions of an ORM object
 */
class Mysql
{

    /**
     * @var AbstractObject
     */
    private $_orm_object;

    /**
     * @var array
     */
    private $_log = [];

    private $_columns_to_be_altered_after_create = [];

    private $_maximum_columns_to_be_created_in_one_statement = 40;

    private $_database_table_allready_exists = false;

    /**
     * Mysql constructor.
     * @param AbstractObject $ormObject
     */
    public function __construct($ormObject)
    {
        $this->_orm_object = $ormObject;
        /**@var AdapterInterface $db**/
        /*$db = Registry::getInstance()->get('db');
        $this->_database_table_allready_exists = $db->fetchRow("SHOW TABLES like '" . $this->_orm_object->getDbTableName() . "'") != null ? true : false;
        if($this->_database_table_allready_exists) {
            $is_object_ok = $this->_orm_object->checkStorageIntegrity();
        }*/
    }

    public function getLog()
    {
        return $this->_log;
    }

    /**
     * @throws Exception
     */
    public function run($dropTablesIfExist = false) {
        /**@var AdapterInterface $db**/
        $db = Registry::getInstance()->get('db');
        if(true === $dropTablesIfExist) {
            $drop_sql = "DROP TABLE IF EXISTS " . $this->_orm_object->getDbTableName();
            $db->execute($drop_sql);
            $this->_log[] = 'Table ' . $this->_orm_object->getDbTableName() . ' dropped';
        }
        $sql = $this->_parseTableInfoToSQL($this->_orm_object->getPropertyDefinitions());
        $db->execute($sql);
        $this->_addAdditionalColumns();
        $this->_log[] = 'Table ' . $this->_orm_object->getDbTableName() . ' created';
    }

    private function _addAdditionalColumns() {
        if(count($this->_columns_to_be_altered_after_create) > 0) {
            /**@var AdapterInterface $db**/
            $sql = 'DESCRIBE ' . $this->_orm_object->getDbTableName();
            $db = Registry::getInstance()->get('db');
            $existing_columns = [];
            foreach ($db->fetchAll($sql) as $existing_column) {
                $existing_columns[] = $existing_column->Field;
            }
            foreach ($this->_columns_to_be_altered_after_create as $column_name => $sql) {
                if(!in_array($column_name, $existing_columns)) {
                    $db->execute($sql);
                }
            }
        }
    }

    /**
     * @param array $pFields
     * @param bool $pAlterTable
     * @return string
     * @throws Exception
     */
    private function _parseTableInfoToSQL($pFields, $pAlterTable = false)
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . $this->_orm_object->getDbTableName() . " (";
        if (true == $pAlterTable) {
            $sql = "ALTER TABLE " . $this->_orm_object->getDbTableName() . " ";
        }
        $unique = array();

        $index = $this->_orm_object->getDbTableIndexes();

        $i = 0;
        $column_counter = 0;
        foreach ($pFields as $fieldName => $fieldInfo) {
            if($fieldName == $this->_orm_object->getDbPrimaryKey()) {
                $index[$fieldName] = [
                    'type' => 'index',
                    'columns' => [$fieldName]
                ];
            }
            if ($fieldInfo['type'] != 'relation' && $fieldInfo['type'] != 'computed') {
                $additional_sql = array();
                $null_allowed = '';
                if (isset($fieldInfo['encrypt']) && $fieldInfo['encrypt'] == true) {
                    $fieldInfo['type'] = 'encrypted';
                    $fieldInfo['unique'] = false;
                    $fieldInfo['length'] = null;
                }
                if (TRUE === $fieldInfo['required']) {
                    $null_allowed .= "NOT NULL";
                } else if (FALSE === $fieldInfo['required']) {
                    $null_allowed .= "NULL";
                }
                if (isset($fieldInfo['unique']) && TRUE == $fieldInfo['unique']) {
                    $unique[] = $fieldName;
                }
                if (isset($fieldInfo['index']) && is_array($fieldInfo['index'])) {
                    foreach ($fieldInfo['index'] as $index_name => $index_type) {
                        if(!isset($index[$index_name])) {
                            $index[$index_name] = [];
                        }
                        $index[$index_name] = [
                            'type' => $index_type,
                            'columns' => [$fieldName]
                        ];
                    }
                }
                $additional_sql[] = $null_allowed;
                if (isset($fieldInfo['default_value'])) {
                    $default_value = $fieldInfo['default_value'];
                    if($fieldInfo['type'] == 'boolean') {
                        $default_value = intval($fieldInfo['default_value']);
                    }
                    $additional_sql[] = "DEFAULT '" . $default_value . "'";
                }
                if (true == $pAlterTable) {
                    if ($i > 0) {
                        $sql .= ", ";
                    }
                    $sql .= "ADD COLUMN $fieldName " . $this->_mapDbFieldType($fieldName, $fieldInfo) . " " . implode(" ", $additional_sql);
                } else {
                    if ($column_counter < $this->_maximum_columns_to_be_created_in_one_statement) {
                        $sql .= "`$fieldName` " . $this->_mapDbFieldType($fieldName, $fieldInfo) . " " . implode(" ", $additional_sql) . ",";
                    }  else {
                        $this->_columns_to_be_altered_after_create[$fieldName] = "ALTER TABLE " . $this->_orm_object->getDbTableName() . " ADD `$fieldName` " . $this->_mapDbFieldType($fieldName, $fieldInfo) . " " . implode(" ", $additional_sql);
                    }
                }
                $column_counter++;
            }
            $i++;
        }
        if (false == $pAlterTable) {
            $sql .= " PRIMARY KEY (" . $this->_orm_object->getDbPrimaryKey() . ")";
        }
        if (count($unique) > 0 && false == $pAlterTable) {
            foreach ($unique as $unique_field_name) {
                $sql .= ", UNIQUE KEY " . $unique_field_name . " (" . $unique_field_name . ")";
            }
        }
        if (count($index) > 0 && false == $pAlterTable) {
            foreach ($index as $index_name => $index_info) {
                //foreach ($index_field_names as $index_field_name) {
                    $sql .= ", " . strtoupper($index_info['type']) . " " . $index_name .  " (" . implode(',', $index_info['columns']) . ")";
                //}
            }
        }
        if (false == $pAlterTable) {
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
        }
        return $sql;
    }

    /**
     * @param string $pFieldName
     * @param array $pFieldInfo
     * @param bool $pReturnOnlyType
     * @return string
     */
    private function _mapDbFieldType($pFieldName, $pFieldInfo, $pReturnOnlyType = false)
    {
        $mapping_table = array(
            'int' => 'INT',
            'integer' => 'INT',
            'bigint' => 'BIGINT',
            'float' => 'FLOAT',
            'varchar' => 'VARCHAR',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'DateTime' => 'DATETIME',
            'time' => 'TIME',
            'boolean' => 'TINYINT(1)',
            'text' => 'TEXT',
            'string' => 'TEXT',
            'longtext' => 'LONGTEXT',
            'blob' => 'BLOB',
            'longblob' => 'LONGBLOB',
            'encrypted' => 'BLOB',
            'enum' => 'ENUM'
        );
        $return = $mapping_table[$pFieldInfo['type']];
        if (true === $pReturnOnlyType) {
            return $return;
        }
        if (isset($pFieldInfo['validators']) && is_array($pFieldInfo['validators'])) {
            foreach ($pFieldInfo['validators'] as $validator_info) {
                if($validator_info['name'] == 'maxlength') {
                    if($pFieldInfo['type'] == 'string') {
                        $return = 'VARCHAR';
                    }
                    if(($pFieldInfo['type'] == 'integer' || $pFieldInfo['type'] == 'int') && intval($validator_info['params']) > 11) {
                        $return = 'BIGINT';
                    }
                    if($pFieldInfo['type'] != 'boolean') {
                        $return .= '(' . $validator_info['params'] . ')';
                    }
                }
                if($validator_info['name'] == 'inarray') {
                    $return = "ENUM('" . implode("','", $validator_info['params']) . "')";
                }
                if($validator_info['name'] == 'unsigned') {
                    $return .= ' unsigned';
                }
            }
        }
        if ($pFieldName == $this->_orm_object->getDbPrimaryKey() && false === $this->_orm_object->dontUseAutoIncrementOnPrimaryKey()) {
            $return .= ' AUTO_INCREMENT';
        }
        return $return;
    }

}
