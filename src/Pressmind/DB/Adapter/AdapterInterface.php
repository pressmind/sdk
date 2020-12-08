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
     * Inserts associated array $data in table $tableName.
     * Keys of $data array must match the table's column names
     * @param string $tableName
     * @param array $data
     * @return mixed
     */
    public function insert($tableName, $data);

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
     * @return string
     */
    public function getTablePrefix();
}
