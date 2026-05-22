<?php

declare(strict_types=1);

namespace Pressmind\MCP;

use Exception;
use Pressmind\Config;
use Pressmind\DB\Adapter\Pdo;
use Pressmind\HelperFunctions;
use Pressmind\Registry;

/**
 * Minimal SDK bootstrap for CLI MCP server (registry: config, db).
 * Mirrors Travelshop bootstrap.php essentials without WordPress.
 */
class Bootstrap
{
    /**
     * Load pm-config and register config + PDO in Registry.
     *
     * @param  string  $applicationPath  Directory containing pm-config.php (or path from PM_CONFIG)
     * @param  string|null  $pmConfigBasename  e.g. pm-config.php; if null uses getenv('PM_CONFIG') or pm-config.php
     * @param  string|null  $env  development|testing|production; default from getenv('APP_ENV') or development
     *
     * @throws Exception When database connection fails
     */
    public static function init(string $applicationPath, ?string $pmConfigBasename = null, ?string $env = null): void
    {
        $applicationPath = rtrim($applicationPath, DIRECTORY_SEPARATOR);
        if (! is_dir($applicationPath)) {
            throw new \InvalidArgumentException("Application path is not a directory: {$applicationPath}");
        }

        $pmConfig = $pmConfigBasename ?? getenv('PM_CONFIG');
        if ($pmConfig === false || $pmConfig === '') {
            $pmConfig = 'pm-config.php';
        }

        $configFile = $applicationPath . DIRECTORY_SEPARATOR . $pmConfig;
        if (! is_file($configFile)) {
            throw new \InvalidArgumentException("Config file not found: {$configFile}");
        }

        $env = $env ?? (getenv('APP_ENV') !== false && getenv('APP_ENV') !== '' ? getenv('APP_ENV') : 'development');

        $configAdapter = new Config('php', HelperFunctions::buildPathString([$applicationPath, $pmConfig]), $env);
        $config = $configAdapter->read();

        $webserverHttp = $config['server']['webserver_http'] ?? 'https://localhost';
        if (! defined('WEBSERVER_HTTP')) {
            define('WEBSERVER_HTTP', $webserverHttp);
        }
        if (! defined('ENV')) {
            define('ENV', $env);
        }

        $dbConfig = \Pressmind\DB\Config\Pdo::create(
            $config['database']['host'],
            $config['database']['dbname'],
            $config['database']['username'],
            $config['database']['password']
        );

        $db = new Pdo($dbConfig);
        if (isset($config['database']['engine']) && strtolower((string) $config['database']['engine']) === 'mysql') {
            $db->execute('SET SESSION sql_mode = "NO_ENGINE_SUBSTITUTION"');
            $db->execute('SET SESSION group_concat_max_len = 1000000000;');
        }

        if (! defined('APPLICATION_PATH')) {
            define('APPLICATION_PATH', $applicationPath);
        }

        $customAutoloader = $applicationPath . DIRECTORY_SEPARATOR . 'Custom' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        if (is_file($customAutoloader)) {
            require_once $customAutoloader;
            if (class_exists('\\Custom\\Autoloader', false)) {
                \Custom\Autoloader::register();
            }
        }

        $registry = Registry::getInstance();
        $registry->add('config', $config);
        $registry->add('config_adapter', $configAdapter);
        $registry->add('db', $db);
    }
}
