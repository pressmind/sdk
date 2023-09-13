<?php


namespace Pressmind\DB\Typemapper;

use Exception;

class Mysql
{
    private $_orm_mapping_table = array(
        'int' => 'INT',
        'integer' => 'INT',
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
        'enum' => 'ENUM',
        'relation' => null
    );

    private $_pressmind_mapping_table = array(
        'text' => 'text',
        'integer' => 'int',
        'int' => 'int',
        'table' => 'relation',
        'date' => 'datetime',
        'plaintext' => 'text',
        'wysiwyg' => 'text',
        'picture' => 'relation',
        'objectlink' => 'relation',
        'file' => 'relation',
        'categorytree' => 'relation',
        'location' => 'relation',
        'link' => 'relation',
        'key_value' => 'relation',
        'qrcode' => 'text',
    );

    /**
     * @param $typeName
     * @return mixed
     * @throws Exception
     */
    public function mapTypeFromPressmindToORM ($typeName) {
        if(key_exists($typeName, $this->_pressmind_mapping_table)) {
            return $this->_pressmind_mapping_table[$typeName];
        } else {
            throw new Exception('Type ' . $typeName . ' does not exist in $_pressmind_mapping_table');
        }
    }

    public function mapTypeFromORMToMysqlWithPropertyDefinition($property)
    {
        if(key_exists($property['type'], $this->_orm_mapping_table)) {
            $return = strtolower($this->_orm_mapping_table[$property['type']]);
            if(isset($property['validators']) && is_array($property['validators']) && $property['type'] != 'boolean') {
                foreach ($property['validators'] as $validator) {
                    if($validator['name'] == 'maxlength') {
                        if($property['type'] == 'string') {
                            $return = 'varchar';
                        }
                        $return .= '(' . $validator['params'] . ')';
                        if($property['type'] == 'integer') {
                            $return = 'int';
                            if($validator['params'] > 11) {
                                $return = 'bigint';
                            }
                        }
                    }
                    if($validator['name'] == 'unsigned') {
                        $return .= ' unsigned';
                    }
                    if($validator['name'] == 'inarray') {
                        $return = 'enum(\'' . implode("','", $validator['params']) . '\')';
                    }
                }
            } else {
                if($property['type'] == 'integer') {
                    if(isset($property['validators']) && is_array($property['validators'])) {
                        foreach ($property['validators'] as $validator) {
                            if ($validator['name'] == 'maxlength') {
                                $type = 'int';
                                if($validator['params'] > 11) {
                                    $type = 'bigint';
                                }
                                $return = $type;
                            } else {
                                $return = 'int';
                            }
                            if($validator['name'] == 'unsigned') {
                                $return .= ' unsigned';
                            }
                        }
                    } else {
                        $return = 'int';
                    }
                }
            }
            return $return;
        }
    }

    /**
     * @param $typeName
     * @return mixed
     * @throws Exception
     */
    public function mapTypeFromORMToMysql($typeName) {
        if(key_exists($typeName, $this->_orm_mapping_table)) {
            return $this->_orm_mapping_table[$typeName];
        } else {
            throw new Exception('Type ' . $typeName . ' does not exist in $_orm_mapping_table');
        }
    }

    /**
     * @param $typeName
     * @return mixed
     * @throws Exception
     */
    public function mapTypeFromPressMindToMysql($typeName) {
        return($this->mapTypeFromORMToMysql($this->mapTypeFromPressmindToORM($typeName)));
    }
}
