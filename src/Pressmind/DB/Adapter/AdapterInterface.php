<?php


namespace Pressmind\DB\Adapter;


interface AdapterInterface
{
    /**
     * Executes a query and returns all result datasets
     * @param string $query
     * @param null $params
     * @return mixed
     */
    public function fetchAll($query, $params = null);

    /**
     * Executes a query and returns the first dataset from the result
     * @param string $query
     * @param null $params
     * @return mixed
     */
    public function fetchRow($query, $params = null);

    /**
     * Executes a query, does not return anything, for select queries better use fetchAll or fetchRow
     * @param string $query
     * @param null $params
     * @return void
     */
    public function execute($query, $params = null);

    /**
     * Inserts or Replaces associated array $data in table $tableName.
     * Keys of $data array must match the table's column names
     * @param string $tableName
     * @param array $data
     * @param boolean $replace_into
     * @return mixed
     */
    public function insert($tableName, $data, $replace_into = true);


    /**
     * Replaces associated array $data in table $tableName.
     * Keys of $data array must match the table's column names
     * @param string $tableName
     * @param array $data
     * @return mixed
     */
    public function replace($tableName, $data);

    /**
     * Updates associated array $data in table $tableName.
     * Keys of $data array must match the table's column names
     * @param string $tableName
     * @param array $data
     * @param null $where
     * @return mixed
     */
    public function update($tableName, $data, $where = null);

    /**
     * Deletes datasets from the database based ob where query
     * @param string $tableName
     * @param null $where
     * @return mixed
     */
    public function delete($tableName, $where = null);

    /**
     * Truncates all data in database table
     * @param string $tableName
     * @return mixed
     */
    public function truncate($tableName);

    /**
     * Batch inserts multiple rows into table with a single query
     * @param string $tableName Table name
     * @param array $columns Array of column names
     * @param array $rows Array of value arrays (each inner array corresponds to one row)
     * @param boolean $replace_into Use REPLACE INTO instead of INSERT INTO
     * @return int Number of affected rows
     */
    public function batchInsert($tableName, $columns, $rows, $replace_into = true);

    /**
     * @return string
     */
    public function getTablePrefix();

    /**
     * Begin a database transaction. Supports nested calls (only the outermost commit/rollback has effect).
     * @return void
     */
    public function beginTransaction();

    /**
     * Commit the current transaction. With nested transactions, only commits when the nesting level reaches zero.
     * @return void
     */
    public function commit();

    /**
     * Roll back the current transaction. Resets nesting level; all changes since beginTransaction() are discarded.
     * @return void
     */
    public function rollback();

    /**
     * Check if a transaction is currently active.
     * @return bool
     */
    public function inTransaction();
}
