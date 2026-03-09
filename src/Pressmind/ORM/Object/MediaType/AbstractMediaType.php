<?php

namespace Pressmind\ORM\Object\MediaType;

use Exception;
use Pressmind\ORM\Object\AbstractObject;
use Pressmind\ORM\Object\MediaObject\DataType\Picture;
use Pressmind\Registry;

class AbstractMediaType extends AbstractObject
{
    /** Cache of existing table columns per table name (avoid repeated DESCRIBE) */
    private static array $_tableColumnsCache = [];
    /**
     * Allow dynamic properties for fields that are not yet defined in the class.
     * This enables runtime schema migration when new fields are added in Pressmind.
     * @var bool
     */
    protected $_check_variables_for_existence = false;

    /**
     * Storage for dynamic properties that are not defined in $_definitions.
     * These are fields that exist in the API response but not yet in the local class.
     * @var array
     */
    protected $_dynamic_properties = [];

    /**
     * Override __set to handle dynamic properties for schema migration.
     * Known properties are handled by parent, unknown properties are stored dynamically.
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value)
    {
        if (isset($this->_definitions['properties'][$name])) {
            // Known property - let parent handle it
            parent::__set($name, $value);
        } else {
            // Unknown property - store dynamically for schema migration
            $this->_dynamic_properties[$name] = $value;
        }
    }

    /**
     * Override __get to also check dynamic properties.
     * Known properties are delegated to parent (includes relation loading).
     *
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->_definitions['properties'][$name])) {
            // Known property - let parent handle it (includes relation loading)
            return parent::__get($name);
        }
        return $this->_dynamic_properties[$name] ?? null;
    }

    /**
     * Get all dynamic properties (fields not defined in class but received from API).
     *
     * @return array
     */
    public function getDynamicProperties(): array
    {
        return $this->_dynamic_properties;
    }

    /**
     * Check if there are any dynamic properties that need schema migration.
     *
     * @return bool
     */
    public function hasDynamicProperties(): bool
    {
        return !empty($this->_dynamic_properties);
    }

    /**
     * Get column names that exist in the database table (so INSERT does not break when class has obsolete properties).
     *
     * @return array<string>
     */
    protected function getExistingTableColumns(): array
    {
        $tableName = $this->getDbTableName();
        if (!isset(self::$_tableColumnsCache[$tableName])) {
            $columnInfo = $this->_db->fetchAll('DESCRIBE ' . $tableName);
            self::$_tableColumnsCache[$tableName] = [];
            foreach ($columnInfo as $column) {
                self::$_tableColumnsCache[$tableName][] = $column->Field;
            }
        }
        return self::$_tableColumnsCache[$tableName];
    }

    /**
     * Filter values to only include keys that exist as columns in the table.
     * Prevents "Unknown column" when the class has more properties than the table (obsolete fields).
     *
     * @param array $values
     * @return array
     */
    protected function filterValuesToExistingColumns(array $values): array
    {
        $existingColumns = $this->getExistingTableColumns();
        return array_intersect_key($values, array_flip($existingColumns));
    }

    /**
     * Override create() to include dynamic properties and to INSERT only existing table columns.
     * So import and templates do not break when the class has obsolete properties (more than table).
     *
     * @return bool
     * @throws Exception
     */
    public function create()
    {
        $field_list = $this->getPropertyNames();
        $values = [];

        foreach ($field_list as $index => $field_name) {
            if ($field_name != $this->getDbPrimaryKey() || $this->_dont_use_autoincrement_on_primary_key == true) {
                $values[$field_name] = $this->parsePropertyValue($field_name, $this->$field_name, 'output');
            }
        }

        if (!empty($this->_dynamic_properties)) {
            foreach ($this->_dynamic_properties as $field_name => $field_value) {
                if (is_object($field_value) || (is_array($field_value) && !empty($field_value) && is_object(reset($field_value)))) {
                    continue;
                }
                $values[$field_name] = $field_value;
            }
        }

        $values = $this->filterValuesToExistingColumns($values);

        $id = $this->_db->insert($this->getDbTableName(), $values, $this->_replace_into_on_create);
        if ($this->_dont_use_autoincrement_on_primary_key == false) {
            $this->setId($id);
        }

        $this->_createHasManyRelations();
        $this->_createHasOneRelations();
        $this->_createBelongsToRelations();
        $this->_createManyToManyRelations();

        return true;
    }

    /**
     * Get the object type ID for this media type.
     * Extracts the ID from the table name (objectdata_XXX).
     *
     * @return int|null
     */
    public function getObjectTypeId(): ?int
    {
        $tableName = $this->getDbTableName();
        if (preg_match('/objectdata_(\d+)/', $tableName, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Override fromImport to also handle unknown properties (new fields from API).
     * Known properties are processed normally, unknown properties are stored in _dynamic_properties
     * and will be persisted via the overridden create() method.
     *
     * @param array|object $pImport
     */
    public function fromImport($pImport)
    {
        foreach ($pImport as $key => $value) {
            if (isset($this->_definitions['properties'][$key])) {
                // Known property - handle normally (including relation mapping)
                if ($this->_definitions['properties'][$key]['type'] == 'relation') {
                    $class_array = explode('\\', $this->_definitions['properties'][$key]['relation']['class']);
                    $class_name = $class_array[count($class_array) - 1];
                    if ($mapper = \Pressmind\Import\Mapper\Factory::create($class_name)) {
                        $this->$key = $mapper->map($this->id_media_object, $this->language, $key, $value);
                    } else {
                        $this->$key = $value;
                    }
                } else {
                    $this->$key = $value;
                }
            } else {
                // Unknown property - store dynamically for schema migration
                // The __set method will handle storing in _dynamic_properties
                $this->$key = $value;
            }
        }
    }

    public function read($pIdMediaObject, $pLanguage = null)
    {
        if(is_null($pLanguage)) {
            $conf = Registry::getInstance()->get('config');
            $pLanguage = $conf['data']['languages']['default'];
        }
        if ($pIdMediaObject != 0 && !empty($pIdMediaObject)) {
            $query = "SELECT * FROM " .
                $this->getDbTableName() .
                " WHERE id_media_object = ? AND language = ?";
            $dataset = $this->_db->fetchRow($query, [$pIdMediaObject, $pLanguage]);
            $this->fromStdClass($dataset);
        }
    }

    protected function _deleteHasManyRelation($property_name)
    {
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
}
