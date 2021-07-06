<?php

namespace Pressmind\DB\Adapter;

use \Exception;
use \PDOStatement;
use Pressmind\HelperFunctions;
use Pressmind\Registry;
use \stdClass;

/**
 * Class Pdo
 * @package Pressmind\DB\Adapter
 */
class Pdo implements AdapterInterface
{
    /**
     * @var PDOStatement
     */
    private $statement;

    /**
     * @var \PDO
     */
    private $databaseConnection;

    /**
     * @var string
     */
    private $table_prefix;

    /**
     * Pdo constructor.
     * @param \Pressmind\DB\Config\Pdo $config
     */
    public function __construct($config)
    {
        $this->databaseConnection = new \PDO('mysql:host=' . $config->host . ';port=' . $config->port . ';dbname=' . $config->dbname . ';charset=utf8', $config->username, $config->password);
        $this->table_prefix = $config->table_prefix;
    }

    /**
     * @param string $query
     * @return bool|PDOStatement|void
     */
    public function prepare($query)
    {
        $this->statement = $this->databaseConnection->prepare($query);
        return $this->statement;
    }

    /**
     * @param string $query
     * @param array|null $parameters
     * @throws Exception
     */
    public function execute($query, $parameters = null)
    {
        $this->prepare($query);
        if (!$this->statement->execute($parameters)) {
            $error = $this->statement->errorInfo();
            throw new Exception('PDO Database Error: ' . $error[0] . ', ' . $error[1] . ', ' . $error[2] . ' Query: ' .  $this->statement->queryString . print_r($parameters, true));
        }
    }

    /**
     * @param null $query
     * @param null $parameters
     * @param null $class_name
     * @return array|void
     * @throws Exception
     */
    public function fetchAll($query = null, $parameters = null, $class_name = null)
    {
        $database_query_log_enabled = Registry::getInstance()->get('config')['logging']['enable_database_query_logging'] ?? false;
        if($database_query_log_enabled) {
            $debug_start_time = microtime(true);
        }
        if (!is_null($query)) {
            $this->statement = $this->databaseConnection->prepare($query);
            $this->statement->execute($parameters);
        }
        if(!is_null($class_name)) {
            $result = $this->statement->fetchAll(\PDO::FETCH_CLASS, $class_name);
        } else {
            $result = $this->statement->fetchAll(\PDO::FETCH_OBJ);
        }
        if($database_query_log_enabled) {
            $now = new \DateTime();
            $logfile = Registry::getInstance()->get('config')['logging']['database_query_log_file'] ?? APPLICATION_PATH . '/logs/db_query_log.txt';
            $debug_end_time = microtime(true);
            file_put_contents(HelperFunctions::replaceConstantsFromConfig($logfile), $now->format(DATE_ISO8601) . ' - ' . ($debug_end_time - $debug_start_time) . ': ' . $query . "\n", FILE_APPEND);
        }
        return $result;
    }

    /**
     * @param null $query
     * @param null $parameters
     * @param null $class_name
     * @return null|stdClass
     * @throws Exception
     */
    public function fetchRow($query = null, $parameters = null, $class_name = null)
    {
        if(strpos($query, 'LIMIT') === false) {
            $query .= ' LIMIT 0,1';
        } else {
            $query = preg_replace('/(LIMIT) (\d*),\d*/', '$1 $2,1', $query);
        }
        $result = $this->fetchAll($query, $parameters, $class_name);
        if (count($result) > 0) {
            return $result[0];
        }
        return null;
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param boolean $replace_into
     * @return mixed|void
     * @throws Exception
     */
    public function insert($tableName, $data, $replace_into = true)
    {
        $database_query_log_enabled = Registry::getInstance()->get('config')['logging']['enable_database_query_logging'] ?? false;
        if($database_query_log_enabled) {
            $debug_start_time = microtime(true);
        }
        $columns = [];
        $values = [];
        $value_replacements = [];
        foreach ($data as $column => $value) {
            $columns[] = $column;
            $values[':' . $column] = $value;
            $value_replacements[] = ':' . $column;
        }
        $insert_statement = 'INSERT';
        if(true === $replace_into) {
            $insert_statement = 'REPLACE';
        }
        $query = $insert_statement . " INTO " . $this->table_prefix . $tableName . "(`" . implode('`, `', $columns) . "`) VALUES(" . implode(', ', $value_replacements) . ")";
        $this->execute($query, $values);
        if($database_query_log_enabled) {
            $now = new \DateTime();
            $logfile = Registry::getInstance()->get('config')['logging']['database_query_log_file'] ?? APPLICATION_PATH . '/logs/db_query_log.txt';
            $debug_end_time = microtime(true);
            $debug_query = $insert_statement . " INTO " . $this->table_prefix . $tableName . "(`" . implode('`, `', $columns) . "`) VALUES(" . implode(', ', array_values($values)) . ")";
            file_put_contents(HelperFunctions::replaceConstantsFromConfig($logfile), $now->format(DATE_ISO8601) . ' - ' . ($debug_end_time - $debug_start_time) . ': ' . $debug_query . "\n", FILE_APPEND);
        }
        return $this->databaseConnection->lastInsertId();
    }

    /**
     * @param string $tableName
     * @param array $data
     * @param array $where
     * @throws Exception
     */
    public function update($tableName, $data, $where = null)
    {
        $database_query_log_enabled = Registry::getInstance()->get('config')['logging']['enable_database_query_logging'] ?? false;
        if($database_query_log_enabled) {
            $debug_start_time = microtime(true);
        }
        $columns = [];
        $parameters = [];
        foreach ($data as $column => $value) {
            $columns[] = $column;
            $parameters[] = $value;
        }
        $query = "UPDATE " . $this->table_prefix . $tableName . " SET `" . implode("` = ?, `", $columns) . "` = ?";
        if(!is_null($where) && is_array($where)) {
            $parameters[] = $where[1];
            $query .= " WHERE " . $where[0];
        }
        $this->execute($query, $parameters);
        if($database_query_log_enabled) {
            $now = new \DateTime();
            $logfile = Registry::getInstance()->get('config')['logging']['database_query_log_file'] ?? APPLICATION_PATH . '/logs/db_query_log.txt';
            $debug_end_time = microtime(true);
            file_put_contents(HelperFunctions::replaceConstantsFromConfig($logfile), $now->format(DATE_ISO8601) . ' - ' . ($debug_end_time - $debug_start_time) . ': ' . $query . "\n", FILE_APPEND);
        }
    }

    /**
     * @param $tableName
     * @param $where
     * @throws Exception
     */
    public function delete($tableName, $where = null)
    {
        $database_query_log_enabled = Registry::getInstance()->get('config')['logging']['enable_database_query_logging'] ?? false;
        if($database_query_log_enabled) {
            $debug_start_time = microtime(true);
        }
        $query = "DELETE FROM " . $this->table_prefix . $tableName;
        $parameters = null;
        if(!is_null($where) && is_array($where)) {
            $query .= " WHERE " . $where[0];
            $parameters[] = $where[1];
        }
        $this->execute($query, $parameters);
        if($database_query_log_enabled) {
            $now = new \DateTime();
            $logfile = Registry::getInstance()->get('config')['logging']['database_query_log_file'] ?? APPLICATION_PATH . '/logs/db_query_log.txt';
            $debug_end_time = microtime(true);
            file_put_contents(HelperFunctions::replaceConstantsFromConfig($logfile), $now->format(DATE_ISO8601) . ' - ' . ($debug_end_time - $debug_start_time) . ': ' . $query . "\n", FILE_APPEND);
        }
    }

    /**
     * @return string
     */
    public function getTablePrefix()
    {
        return $this->table_prefix;
    }
}
