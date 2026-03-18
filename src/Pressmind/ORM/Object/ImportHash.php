<?php

namespace Pressmind\ORM\Object;

use DateTime;
use Exception;
use Pressmind\Registry;

/**
 * Central entity for import change-detection hashes.
 * Table: pmt2core_import_hashes
 * Scopes: media_object, category_tree, config
 *
 * @package Pressmind\ORM\Object
 * @property string $id
 * @property string $scope
 * @property string $hash
 * @property DateTime $updated_at
 */
class ImportHash extends AbstractObject
{
    protected $_dont_use_autoincrement_on_primary_key = true;
    protected $_disable_cache_permanently = true;

    protected $_definitions = [
        'class' => [
            'name' => self::class
        ],
        'database' => [
            'table_name' => 'pmt2core_import_hashes',
            'primary_key' => ['id', 'scope'],
            'storage_engine' => 'InnoDB',
        ],
        'properties' => [
            'id' => [
                'title' => 'id',
                'name' => 'id',
                'type' => 'varchar',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 64],
                ],
            ],
            'scope' => [
                'title' => 'scope',
                'name' => 'scope',
                'type' => 'varchar',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 32],
                ],
            ],
            'hash' => [
                'title' => 'hash',
                'name' => 'hash',
                'type' => 'varchar',
                'required' => true,
                'filters' => null,
                'validators' => [
                    ['name' => 'maxlength', 'params' => 64],
                ],
            ],
            'updated_at' => [
                'title' => 'updated_at',
                'name' => 'updated_at',
                'type' => 'datetime',
                'required' => true,
                'filters' => null,
                'validators' => null,
            ],
        ]
    ];

    /**
     * Check if hash has changed (no stored hash or different from stored).
     *
     * @param string $id
     * @param string $scope
     * @param string $newHash
     * @return bool true if changed or no previous hash
     */
    public static function hasChanged(string $id, string $scope, string $newHash): bool
    {
        $stored = self::get($id, $scope);
        if ($stored === null) {
            return true;
        }
        return $stored !== $newHash;
    }

    /**
     * Get stored hash for id/scope.
     *
     * @param string $id
     * @param string $scope
     * @return string|null hash or null if not found
     */
    public static function get(string $id, string $scope): ?string
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        $row = $db->fetchRow(
            'SELECT hash FROM ' . $table . ' WHERE id = ? AND scope = ?',
            [$id, $scope]
        );
        return $row && isset($row->hash) ? $row->hash : null;
    }

    /**
     * Store or update hash for id/scope.
     *
     * @param string $id
     * @param string $scope
     * @param string $hash
     * @throws Exception
     */
    public static function store(string $id, string $scope, string $hash): void
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        $now = date('Y-m-d H:i:s');
        $db->execute(
            'INSERT INTO ' . $table . ' (id, scope, hash, updated_at) VALUES (?, ?, ?, ?) ' .
            'ON DUPLICATE KEY UPDATE hash = VALUES(hash), updated_at = VALUES(updated_at)',
            [$id, $scope, $hash, $now]
        );
    }

    /**
     * Delete single hash entry (static helper; name avoids overriding AbstractObject::delete()).
     *
     * @param string $id
     * @param string $scope
     * @throws Exception
     */
    public static function deleteHash(string $id, string $scope): void
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        $db->execute('DELETE FROM ' . $table . ' WHERE id = ? AND scope = ?', [$id, $scope]);
    }

    /**
     * Delete all hash entries in scope that are NOT in the given valid IDs.
     *
     * @param string $scope
     * @param array $validIds list of IDs that should remain
     * @throws Exception
     */
    public static function deleteOrphans(string $scope, array $validIds): void
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        if (empty($validIds)) {
            $db->execute('DELETE FROM ' . $table . ' WHERE scope = ?', [$scope]);
            return;
        }
        $placeholders = implode(',', array_fill(0, count($validIds), '?'));
        $params = array_merge([$scope], $validIds);
        $db->execute(
            'DELETE FROM ' . $table . ' WHERE scope = ? AND id NOT IN (' . $placeholders . ')',
            $params
        );
    }

    /**
     * Clear all hashes for a scope.
     *
     * @param string $scope
     * @throws Exception
     */
    public static function clear(string $scope): void
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        $db->execute('DELETE FROM ' . $table . ' WHERE scope = ?', [$scope]);
    }

    /**
     * Clear entire hash table.
     *
     * @throws Exception
     */
    public static function clearAll(): void
    {
        $db = Registry::getInstance()->get('db');
        $table = (new self())->getDbTableName();
        $db->execute('DELETE FROM ' . $table);
    }
}
