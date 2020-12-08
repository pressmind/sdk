<?php


namespace Pressmind\DB\Config;


class Pdo
{
    public $host;
    public $port;
    public $dbname;
    public $username;
    public $password;
    public $table_prefix;
    private static $instance = null;


    public static function create($host, $dbname, $username, $password, $port = '3306', $tablePrefix = null) {
        if(is_null(self::$instance)) {
            self::$instance = new self();
            self::$instance->host = $host;
            self::$instance->port = $port;
            self::$instance->dbname = $dbname;
            self::$instance->username = $username;
            self::$instance->password = $password;
            self::$instance->table_prefix = $tablePrefix;
        }
        return self::$instance;
    }
}
