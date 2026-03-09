<?php

namespace Pressmind\System;

use Exception;
use Pressmind\DB\Adapter\AdapterInterface;
use Pressmind\HelperFunctions;
use Pressmind\Log\Writer;
use Pressmind\ObjectTypeScaffolder;
use Pressmind\ORM\Object\MediaType\Factory;
use Pressmind\Registry;
use Pressmind\REST\Client;

/**
 * SchemaMigrator handles runtime schema migration for MediaTypes.
 *
 * When Pressmind adds new fields to an Object Type, this class:
 * 1. Detects missing fields by comparing API response with local class definition
 * 2. Adds missing database columns via ALTER TABLE
 * 3. Updates the PHP class file in the background for future requests
 *
 * When Pressmind removes fields (deleted in API): obsolete fields are detected and logged only.
 * No columns are dropped during import; INSERT uses only existing table columns so import
 * and templates do not break. Use Database Integrity Check CLI for optional cleanup.
 *
 * Strategy: Add new columns (immediate access to new data), skip missing/obsolete (no DROP in import).
 */
class SchemaMigrator
{
    /** Reserved table columns that must never be dropped. */
    private const RESERVED_COLUMNS = ['id', 'id_media_object', 'language'];

    /**
     * MySQL type mapping for field types from the API.
     * @var array
     */
    private static array $_mysql_type_map = [
        'text' => 'LONGTEXT',
        'integer' => 'INT(11)',
        'int' => 'INT(11)',
        'table' => 'LONGTEXT',  // relation type, stored as JSON if needed
        'date' => 'DATETIME',
        'plaintext' => 'LONGTEXT',
        'wysiwyg' => 'LONGTEXT',
        'qrcode' => 'LONGTEXT',
        'picture' => 'LONGTEXT',  // relation type
        'objectlink' => 'LONGTEXT',  // relation type
        'file' => 'LONGTEXT',  // relation type
        'categorytree' => 'LONGTEXT',  // relation type
        'location' => 'LONGTEXT',  // relation type
        'link' => 'LONGTEXT',  // relation type
        'key_value' => 'LONGTEXT',  // relation type
    ];

    /**
     * Check for schema mismatches and migrate if configured.
     * 
     * @param int $objectTypeId The media object type ID
     * @param array $importData The data array from the API response
     * @return array Result with 'migrated' bool and 'fields' array
     * @throws Exception When mode is 'abort' and schema mismatch is detected
     */
    public static function migrateIfNeeded(int $objectTypeId, array $importData): array
    {
        $config = Registry::getInstance()->get('config');
        $mode = $config['data']['schema_migration']['mode'] ?? 'log_only';
        $logChanges = $config['data']['schema_migration']['log_changes'] ?? true;

        $missingFields = self::detectMissingFields($objectTypeId, $importData);
        $obsoleteFields = self::detectObsoleteFields($objectTypeId, $importData);

        if (empty($missingFields) && empty($obsoleteFields)) {
            return ['migrated' => false, 'fields' => [], 'obsolete_fields' => []];
        }

        // Handle missing fields (new in API, not in class)
        if (!empty($missingFields)) {
            if ($logChanges) {
                Writer::write(
                    'SchemaMigrator: Detected ' . count($missingFields) . ' missing fields for ObjectType ' . $objectTypeId . ': ' . implode(', ', array_keys($missingFields)),
                    Writer::OUTPUT_FILE,
                    'schema_migration',
                    Writer::TYPE_ERROR
                );
            }

            switch ($mode) {
                case 'auto':
                    self::addDatabaseColumns($objectTypeId, $missingFields);
                    self::updateClassFileAsync($objectTypeId);
                    if ($logChanges) {
                        Writer::write(
                            'SchemaMigrator: Successfully added missing fields for ObjectType ' . $objectTypeId,
                            Writer::OUTPUT_FILE,
                            'schema_migration',
                            Writer::TYPE_ERROR
                        );
                    }
                    break;

                case 'log_only':
                    Writer::write(
                        'SchemaMigrator: Schema mismatch for ObjectType ' . $objectTypeId . ' (log_only mode). Ignoring fields: ' . implode(', ', array_keys($missingFields)),
                        Writer::OUTPUT_FILE,
                        'schema_migration',
                        Writer::TYPE_ERROR
                    );
                    break;

                case 'abort':
                default:
                    throw new Exception(
                        'Schema mismatch for ObjectType ' . $objectTypeId .
                        '. Missing fields: ' . implode(', ', array_keys($missingFields)) .
                        '. Run ObjectTypeScaffolder or set schema_migration.mode to "auto".'
                    );
            }
        }

        // Handle obsolete fields (in class/DB but removed in API) - log only, no DROP in import
        if (!empty($obsoleteFields) && $logChanges) {
            Writer::write(
                'SchemaMigrator: Detected ' . count($obsoleteFields) . ' obsolete (removed in API) fields for ObjectType ' . $objectTypeId . ': ' . implode(', ', $obsoleteFields),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
        }

        $migrated = !empty($missingFields) && $mode === 'auto';

        return [
            'migrated' => $migrated,
            'fields' => $missingFields,
            'obsolete_fields' => $obsoleteFields,
        ];
    }

    /**
     * Detect fields that exist in the import data but not in the local class definition.
     *
     * @param int $objectTypeId
     * @param array $importData The 'data' array from the API response
     * @return array Associative array of fieldName => fieldType
     */
    public static function detectMissingFields(int $objectTypeId, array $importData): array
    {
        $config = Registry::getInstance()->get('config');
        $expectedFields = self::buildExpectedFieldsFromImportData($importData, $config);

        try {
            $mediaType = Factory::createById($objectTypeId);
            $existingProperties = array_keys($mediaType->getPropertyDefinitions());
        } catch (\Throwable $e) {
            Writer::write(
                'SchemaMigrator: Could not load MediaType class for ObjectType ' . $objectTypeId . ': ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
            return [];
        }

        $missingFields = [];
        foreach ($expectedFields as $fieldName => $fieldType) {
            if (!in_array($fieldName, $existingProperties, true)) {
                $missingFields[$fieldName] = $fieldType;
            }
        }
        return $missingFields;
    }

    /**
     * Detect fields that exist in the local class but are no longer in the API import data (deleted in Pressmind).
     *
     * @param int $objectTypeId
     * @param array $importData The 'data' array from the API response
     * @return array List of field names that are obsolete and can be dropped
     */
    public static function detectObsoleteFields(int $objectTypeId, array $importData): array
    {
        $config = Registry::getInstance()->get('config');
        $expectedFields = self::buildExpectedFieldsFromImportData($importData, $config);
        $expectedFieldNames = array_merge(self::RESERVED_COLUMNS, array_keys($expectedFields));

        try {
            $mediaType = Factory::createById($objectTypeId);
            $existingProperties = array_keys($mediaType->getPropertyDefinitions());
        } catch (\Throwable $e) {
            Writer::write(
                'SchemaMigrator: Could not load MediaType class for ObjectType ' . $objectTypeId . ': ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
            return [];
        }

        $obsolete = [];
        foreach ($existingProperties as $name) {
            if (in_array($name, self::RESERVED_COLUMNS, true)) {
                continue;
            }
            if (!in_array($name, $expectedFieldNames, true)) {
                $obsolete[] = $name;
            }
        }

        return $obsolete;
    }

    /**
     * Build expected field list from API import data: field name => type.
     * Uses same naming as ObjectTypeScaffolder (section replace, human_to_machine).
     *
     * @param array $importData
     * @param array $config
     * @return array<string, string> fieldName => fieldType
     */
    private static function buildExpectedFieldsFromImportData(array $importData, array $config): array
    {
        $fields = [];
        foreach ($importData as $dataField) {
            if (!isset($dataField->sections) || !is_array($dataField->sections)) {
                continue;
            }
            foreach ($dataField->sections as $section) {
                $sectionName = $section->name;
                if (isset($config['data']['sections']['replace']) && !empty($config['data']['sections']['replace']['regular_expression'])) {
                    $sectionName = preg_replace(
                        $config['data']['sections']['replace']['regular_expression'],
                        $config['data']['sections']['replace']['replacement'],
                        $sectionName
                    );
                }
                $fieldName = HelperFunctions::human_to_machine($dataField->var_name . '_' . $sectionName);
                $fields[$fieldName] = $dataField->type ?? 'text';
            }
        }
        return $fields;
    }

    /**
     * Drop obsolete columns from the database table.
     * Reserved columns (id, id_media_object, language) are never dropped.
     *
     * @param int $objectTypeId
     * @param array $fieldNames
     * @throws Exception
     */
    public static function dropDatabaseColumns(int $objectTypeId, array $fieldNames): void
    {
        /** @var AdapterInterface $db */
        $db = Registry::getInstance()->get('db');
        $tableName = 'objectdata_' . $objectTypeId;

        $existingColumns = [];
        try {
            $columnInfo = $db->fetchAll('DESCRIBE ' . $tableName);
            foreach ($columnInfo as $column) {
                $existingColumns[] = $column->Field;
            }
        } catch (Exception $e) {
            Writer::write(
                'SchemaMigrator: Could not describe table ' . $tableName . ': ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
            throw $e;
        }

        foreach ($fieldNames as $fieldName) {
            if (in_array($fieldName, self::RESERVED_COLUMNS, true)) {
                continue;
            }
            if (!in_array($fieldName, $existingColumns, true)) {
                continue;
            }
            $sql = "ALTER TABLE `{$tableName}` DROP COLUMN `{$fieldName}`";
            try {
                $db->execute($sql);
                Writer::write(
                    'SchemaMigrator: Dropped column ' . $fieldName . ' from ' . $tableName,
                    Writer::OUTPUT_FILE,
                    'schema_migration',
                    Writer::TYPE_ERROR
                );
            } catch (Exception $e) {
                Writer::write(
                    'SchemaMigrator: Failed to drop column ' . $fieldName . ' from ' . $tableName . ': ' . $e->getMessage(),
                    Writer::OUTPUT_FILE,
                    'schema_migration',
                    Writer::TYPE_ERROR
                );
                throw $e;
            }
        }
    }

    /**
     * Add missing columns to the database table.
     * 
     * @param int $objectTypeId
     * @param array $fields Associative array of fieldName => fieldType
     * @throws Exception
     */
    public static function addDatabaseColumns(int $objectTypeId, array $fields): void
    {
        /** @var AdapterInterface $db */
        $db = Registry::getInstance()->get('db');
        $tableName = 'objectdata_' . $objectTypeId;

        // Get existing columns
        $existingColumns = [];
        try {
            $columnInfo = $db->fetchAll('DESCRIBE ' . $tableName);
            foreach ($columnInfo as $column) {
                $existingColumns[] = $column->Field;
            }
        } catch (Exception $e) {
            Writer::write(
                'SchemaMigrator: Could not describe table ' . $tableName . ': ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
            throw $e;
        }

        foreach ($fields as $fieldName => $fieldType) {
            // Skip if column already exists
            if (in_array($fieldName, $existingColumns)) {
                continue;
            }

            $mysqlType = self::mapFieldType($fieldType);
            $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$fieldName}` {$mysqlType} NULL";

            try {
                $db->execute($sql);
                Writer::write(
                    'SchemaMigrator: Added column ' . $fieldName . ' (' . $mysqlType . ') to ' . $tableName,
                    Writer::OUTPUT_FILE,
                    'schema_migration',
                    Writer::TYPE_ERROR
                );
            } catch (Exception $e) {
                Writer::write(
                    'SchemaMigrator: Failed to add column ' . $fieldName . ' to ' . $tableName . ': ' . $e->getMessage(),
                    Writer::OUTPUT_FILE,
                    'schema_migration',
                    Writer::TYPE_ERROR
                );
                throw $e;
            }
        }
    }

    /**
     * Map API field type to MySQL column type.
     * 
     * @param string $fieldType
     * @return string
     */
    public static function mapFieldType(string $fieldType): string
    {
        return self::$_mysql_type_map[$fieldType] ?? 'LONGTEXT';
    }

    /**
     * Update the PHP class file asynchronously.
     * This fetches the current ObjectType definition from the API and regenerates the class.
     * 
     * @param int $objectTypeId
     */
    public static function updateClassFileAsync(int $objectTypeId): void
    {
        try {
            // Fetch the current ObjectType definition from the API
            $client = new Client();
            $response = $client->sendRequest('ObjectType', 'getById', ['ids' => $objectTypeId]);

            if (!empty($response->result) && is_array($response->result)) {
                foreach ($response->result as $result) {
                    if ($result->id == $objectTypeId) {
                        // Use ObjectTypeScaffolder to regenerate the class
                        $scaffolder = new ObjectTypeScaffolder($result, $objectTypeId);
                        $scaffolder->generateORMFile(self::buildDefinitionFields($result));
                        
                        Writer::write(
                            'SchemaMigrator: Updated PHP class file for ObjectType ' . $objectTypeId,
                            Writer::OUTPUT_FILE,
                            'schema_migration',
                            Writer::TYPE_ERROR
                        );
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // Log but don't throw - the dynamic properties will handle this request
            Writer::write(
                'SchemaMigrator: Failed to update PHP class file for ObjectType ' . $objectTypeId . ': ' . $e->getMessage(),
                Writer::OUTPUT_FILE,
                'schema_migration',
                Writer::TYPE_ERROR
            );
        }
    }

    /**
     * Build definition fields array from API response (same logic as ObjectTypeScaffolder).
     * 
     * @param object $objectDefinition
     * @return array
     */
    private static function buildDefinitionFields(object $objectDefinition): array
    {
        $config = Registry::getInstance()->get('config');
        
        $phpTypeMap = [
            'integer' => 'integer',
            'int' => 'integer',
            'int(11)' => 'integer',
            'longtext' => 'string',
            'datetime' => 'DateTime',
            'relation' => 'relation'
        ];

        $mysqlTypeMap = [
            'text' => 'longtext',
            'integer' => 'int(11)',
            'int' => 'int(11)',
            'table' => 'relation',
            'date' => 'datetime',
            'plaintext' => 'longtext',
            'wysiwyg' => 'longtext',
            'qrcode' => 'longtext',
            'picture' => 'relation',
            'objectlink' => 'relation',
            'file' => 'relation',
            'categorytree' => 'relation',
            'location' => 'relation',
            'link' => 'relation',
            'key_value' => 'relation',
        ];

        $definitionFields = [
            ['id', 'integer', 'integer'],
            ['id_media_object', 'integer', 'integer'],
            ['language', 'longtext', 'string'],
        ];

        if (!isset($objectDefinition->fields) || !is_array($objectDefinition->fields)) {
            return $definitionFields;
        }

        // Skip first 3 fields (id, id_media_object, language) - same as ObjectTypeScaffolder
        $fields = $objectDefinition->fields;
        unset($fields[0], $fields[1], $fields[2]);

        foreach ($fields as $fieldDefinition) {
            if (isset($fieldDefinition->sections) && is_array($fieldDefinition->sections)) {
                foreach ($fieldDefinition->sections as $section) {
                    $sectionName = $section->name;
                    if (isset($config['data']['sections']['replace']) && !empty($config['data']['sections']['replace']['regular_expression'])) {
                        $sectionName = preg_replace(
                            $config['data']['sections']['replace']['regular_expression'],
                            $config['data']['sections']['replace']['replacement'],
                            $sectionName
                        );
                    }
                    $fieldName = HelperFunctions::human_to_machine($fieldDefinition->var_name . '_' . $sectionName);
                    $mysqlType = $mysqlTypeMap[$fieldDefinition->type] ?? 'longtext';
                    $phpType = $phpTypeMap[$mysqlType] ?? 'string';
                    $definitionFields[$fieldName] = [$fieldName, $fieldDefinition->type, $phpType];
                }
            }
        }

        return $definitionFields;
    }

    /**
     * Check if schema migration is enabled and set to auto mode.
     * 
     * @return bool
     */
    public static function isAutoMigrationEnabled(): bool
    {
        $config = Registry::getInstance()->get('config');
        return ($config['data']['schema_migration']['mode'] ?? 'abort') === 'auto';
    }
}
