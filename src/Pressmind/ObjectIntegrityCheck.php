<?php
namespace Pressmind;

use Exception;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\DB\Typemapper\Mysql;

class ObjectIntegrityCheck
{

    /**
     * @var array
     */
    private $_database_table_info = [];

    /**
     * @var array
     */
    private $_differences = [];

    /**
     * ObjectIntegrityCheck constructor.
     * @param $objectDefinition
     * @param $databaseTableName
     * @throws Exception
     */
    public function __construct($objectDefinition, $databaseTableName)
    {
        /**@var Pdo $db**/
        $db = Registry::getInstance()->get('db');
        $conf = Registry::getInstance()->get('config');
        $table = $db->fetchAll('DESCRIBE ' . $databaseTableName);
        foreach ($table as $field) {
            $this->_database_table_info[$field->Field] = $field;
        }
        $type_mapper = new Mysql();
        $pm_columns = ['id', 'id_media_object', 'language'];
        foreach ($objectDefinition->fields as $field) {
            if(isset($field->sections)) {
                foreach ($field->sections as $section) {
                    $section_name = $section->name;
                    if(isset($conf['data']['sections']['replace']) && !empty($conf['data']['sections']['replace']['regular_expression'])) {
                        $section_name = preg_replace($conf['data']['sections']['replace']['regular_expression'], $conf['data']['sections']['replace']['replacement'], $section_name);
                    }
                    $column_name = HelperFunctions::human_to_machine($field->var_name) . '_' . HelperFunctions::human_to_machine($section_name);
                    $column_type = $type_mapper->mapTypeFromPressMindToMysql($field->type);
                    $pm_columns[] = $column_name;
                    if (!is_null($column_type)) {
                        if (isset($this->_database_table_info[$column_name])) {
                            if ($this->_database_table_info[$column_name]->Type !== strtolower($column_type)) {
                                $this->_differences[] = ['action' => 'alter_column_type', 'column_name' => $column_name, 'column_type' => $column_type, 'msg' => 'database column ' . $column_name . ' has different type and needs to be altered from ' . $this->_database_table_info[$column_name]->Type . ' to ' . strtolower($column_type)];
                            }
                        } else {
                            $this->_differences[] = ['action' => 'create_column', 'column_name' => $column_name, 'column_type' => $column_type, 'msg' => 'column ' . $column_name . ' does not exist in database and needs to be created'];
                        }
                    }
                }
            }
        }
        foreach ($table as $column) {
            if(!in_array($column->Field, $pm_columns)) {
                $this->_differences[] = ['action' => 'drop_column', 'column_name' => $column->Field, 'msg' => 'database column ' . $column->Field . ' does not exist pressminds datascheme anymore, column needs to be deleted.'];
            }
        }
    }

    /**
     * @return array
     */
    public function getDifferences()
    {
        return $this->_differences;
    }
}
